<?php
// includes/lab_rules.php — order-state rules shared by every place that records a lab/imaging result.
// medical_orders.status: ordered -> in_progress (sample collected / being processed) -> completed | cancelled.

// Adds the audit columns once (self-healing; DDL, so call outside a transaction).
function lab_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    schema_once('lab_orders', function () use ($pdo) {
    $have = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medical_orders'")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['collected_by' => 'INT NULL', 'collected_at' => 'DATETIME NULL', 'verified_by' => 'INT NULL', 'verified_at' => 'DATETIME NULL'] as $c => $def) {
        if (!in_array($c, $have, true)) $pdo->exec("ALTER TABLE medical_orders ADD COLUMN $c $def");
    }
    $ct = (string)$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'medical_orders' AND COLUMN_NAME = 'status'")->fetchColumn();
    if (stripos($ct, "'no_sample'") === false && stripos($ct, 'enum(') === 0) {
        $pdo->exec("ALTER TABLE medical_orders MODIFY COLUMN status ENUM('ordered','in_progress','completed','cancelled','no_sample') NOT NULL DEFAULT 'ordered'");
    }
    });
}

// Ends the request (409) when the order may not receive this result. $sampleCollected is the caller's explicit statement
// that the specimen has been collected (the results form's "Sample collected: Yes").
function lab_result_guard(PDO $pdo, int $orderId, string $newStatus, bool $sampleCollected): void {
    $st = $pdo->prepare("SELECT status, verified_by FROM medical_orders WHERE id = ?");
    $st->execute([$orderId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $cur = $row ? $row['status'] : false;
    if ($cur === false) { if (!empty($GLOBALS['lab_bulk'])) throw new RuntimeException('Order not found'); http_response_code(404); echo json_encode(['success' => false, 'message' => 'Order not found']); exit(); }
    $msg = null;
    // A completed result stays editable (a corrected result may be saved again) until a second person verifies it.
    if ($cur === 'completed' && $row['verified_by']) $msg = 'This result was verified and cannot be overwritten. Ask for an approved correction (edit request).';
    elseif ($cur === 'completed' && $newStatus !== 'completed') $msg = 'A completed result cannot be moved back to draft.';
    elseif ($cur === 'no_sample') $msg = 'Sample was marked as not taken; this test is removed from billing and cannot receive a result.';
    elseif ($cur === 'cancelled') $msg = 'This order was cancelled and cannot receive a result.';
    elseif ($cur === 'ordered' && !$sampleCollected) $msg = 'Collect the sample first: a result cannot be entered for an order whose sample has not been collected.';
    if ($msg && !empty($GLOBALS['lab_bulk'])) throw new RuntimeException($msg); // Submit All reports per order instead of ending the request
    if ($msg) { http_response_code(409); echo json_encode(['success' => false, 'message' => $msg]); exit(); }
}

// Second-person verification of a completed result (the person who entered it cannot verify it). Ends the request itself.
function order_verify_result(PDO $pdo, int $orderId, array $types): void {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $in = implode(',', array_fill(0, count($types), '?'));
    $st = $pdo->prepare("SELECT status, completed_by, verified_by, order_type, description FROM medical_orders WHERE id = ? AND order_type IN ($in)");
    $st->execute(array_merge([$orderId], $types));
    $o = $st->fetch();
    $fail = function (string $m, int $c) { http_response_code($c); echo json_encode(['success' => false, 'message' => $m]); exit(); };
    if (!$o) $fail('Order not found', 404);
    if ($o['status'] !== 'completed') $fail('Only a completed result can be verified.', 409);
    if ($o['verified_by']) $fail('This result is already verified.', 409);
    if ((int)$o['completed_by'] === $uid) $fail('The person who entered the result cannot verify it. Ask a colleague or the head of department.', 403);
    $pdo->prepare("UPDATE medical_orders SET verified_by = ?, verified_at = NOW() WHERE id = ?")->execute([$uid, $orderId]);
    if (function_exists('log_activity')) log_activity($pdo, 'APPROVE', $o['order_type'] === 'lab' ? 'lab' : 'imaging', 'Result verified: "' . $o['description'] . '"', $orderId, null);
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Result verified', 'data' => ['id' => $orderId]]);
    exit();
}
