<?php
// handlers/finance/payment-records.php
// Record and track patient payments

require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d, $s = 200) {
    http_response_code($s);
    echo json_encode($d);
    exit();
}

function success($d = null, $m = 'Success', $s = 200) {
    jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s);
}

function error($m, $s = 400) {
    jsonResponse(['success' => false, 'message' => $m], $s);
}

/**
 * Generate receipt number
 */
function generateReceiptNumber(PDO $pdo): string {
    $prefix = 'RCP-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("
        SELECT receipt_number FROM payment_records 
        WHERE receipt_number LIKE ? 
        ORDER BY receipt_number DESC LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int)substr($last, -4) + 1) : 1;
    return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Update invoice balance after payment
 */
function updateInvoiceBalance(PDO $pdo, int $invoiceId): void {
    // Get total paid amount
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) 
        FROM payment_records 
        WHERE invoice_id = ? AND status = 'completed'
    ");
    $stmt->execute([$invoiceId]);
    $totalPaid = (float)$stmt->fetchColumn();

    // Get invoice total
    $invStmt = $pdo->prepare("SELECT total FROM invoices WHERE id = ?");
    $invStmt->execute([$invoiceId]);
    $total = (float)$invStmt->fetchColumn();

    $balance = max(0, $total - $totalPaid);
    $status = $balance <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'issued');

    $pdo->prepare("
        UPDATE invoices 
        SET paid_amount = ?, balance = ?, status = ?, updated_at = NOW()
        WHERE id = ?
    ")->execute([$totalPaid, $balance, $status, $invoiceId]);
}

/**
 * Allocate payment to invoice items
 */
function allocatePayment(PDO $pdo, int $paymentRecordId, int $invoiceId, float $amountPaid, array $allocations = []): void {
    if (empty($allocations)) {
        // Auto-allocate proportionally if no specific allocations provided
        $stmt = $pdo->prepare("SELECT id, total FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll();

        if (empty($items)) return;

        // Get invoice total for calculation
        $invStmt = $pdo->prepare("SELECT total FROM invoices WHERE id = ?");
        $invStmt->execute([$invoiceId]);
        $invoiceTotal = (float)$invStmt->fetchColumn();

        if ($invoiceTotal <= 0) return;

        $remaining = $amountPaid;
        foreach ($items as $item) {
            $proportion = (float)$item['total'] / $invoiceTotal;
            $allocation = min($remaining, $amountPaid * $proportion);

            $pdo->prepare("
                INSERT INTO payment_allocations (payment_record_id, invoice_item_id, allocated_amount)
                VALUES (?, ?, ?)
            ")->execute([$paymentRecordId, $item['id'], $allocation]);

            $remaining -= $allocation;
        }
    } else {
        // Use specific allocations
        foreach ($allocations as $alloc) {
            $pdo->prepare("
                INSERT INTO payment_allocations (payment_record_id, invoice_item_id, allocated_amount, allocation_note)
                VALUES (?, ?, ?, ?)
            ")->execute([
                $paymentRecordId,
                $alloc['invoice_item_id'] ?? null,
                $alloc['amount'],
                $alloc['note'] ?? null
            ]);
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

    // ===== GET =====
    if ($method === 'GET') {
        // Get single payment record
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT pr.*, 
                       i.invoice_no, i.total, i.balance,
                       p.first_name, p.last_name, p.patient_no,
                       cb.name AS created_by_name,
                       vb.name AS verified_by_name
                FROM payment_records pr
                LEFT JOIN invoices i ON i.id = pr.invoice_id
                LEFT JOIN patients p ON p.id = pr.patient_id
                LEFT JOIN employees cb ON cb.id = pr.created_by
                LEFT JOIN employees vb ON vb.id = pr.verified_by
                WHERE pr.id = ?
            ");
            $stmt->execute([$id]);
            $payment = $stmt->fetch();
            if (!$payment) error('Payment record not found', 404);

            // Get payment allocations
            $allocStmt = $pdo->prepare("
                SELECT pa.*, ii.description, ii.unit_price, ii.quantity
                FROM payment_allocations pa
                LEFT JOIN invoice_items ii ON ii.id = pa.invoice_item_id
                WHERE pa.payment_record_id = ?
            ");
            $allocStmt->execute([$id]);
            $payment['allocations'] = $allocStmt->fetchAll();

            success($payment);
        }

        // List payment records
        $invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
        $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $status = $_GET['status'] ?? '';
        $method_filter = $_GET['payment_method'] ?? '';
        $date_from = $_GET['date_from'] ?? '';
        $date_to = $_GET['date_to'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if ($invoiceId) {
            $where[] = 'pr.invoice_id = ?';
            $params[] = $invoiceId;
        }
        if ($patientId) {
            $where[] = 'pr.patient_id = ?';
            $params[] = $patientId;
        }
        if ($deptId) {
            $where[] = 'pr.department_id = ?';
            $params[] = $deptId;
        }
        if ($status) {
            $where[] = 'pr.status = ?';
            $params[] = $status;
        }
        if ($method_filter) {
            $where[] = 'pr.payment_method = ?';
            $params[] = $method_filter;
        }
        if ($date_from) {
            $where[] = 'pr.payment_date >= ?';
            $params[] = $date_from;
        }
        if ($date_to) {
            $where[] = 'pr.payment_date <= ?';
            $params[] = $date_to;
        }

        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM payment_records pr $ws");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT pr.id, pr.invoice_id, pr.patient_id, pr.department_id, pr.amount_paid,
                   pr.payment_method, pr.payment_date, pr.payment_time, pr.reference_number,
                   pr.receipt_number, pr.status, pr.created_at,
                   i.invoice_no, p.first_name, p.last_name, p.patient_no,
                   d.name AS department_name
            FROM payment_records pr
            LEFT JOIN invoices i ON i.id = pr.invoice_id
            LEFT JOIN patients p ON p.id = pr.patient_id
            LEFT JOIN departments d ON d.id = pr.department_id
            $ws
            ORDER BY pr.payment_date DESC, pr.id DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        // Calculate summary stats
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) AS total_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN status = 'completed' THEN amount_paid ELSE 0 END) AS total_amount,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                SUM(CASE WHEN status = 'reversed' THEN amount_paid ELSE 0 END) AS reversed_amount
            FROM payment_records
            $ws
        ");
        $statsStmt->execute($params);
        $stats = $statsStmt->fetch();

        success([
            'payments' => $payments,
            'stats' => $stats,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / max($limit, 1))
            ]
        ]);
    }

    // ===== POST — Record new payment =====
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        foreach (['invoice_id', 'patient_id', 'amount_paid', 'payment_method'] as $f) {
            if (!array_key_exists($f, $input) || ($f === 'amount_paid' && $input[$f] <= 0)) {
                error("Field '$f' required and must be valid", 422);
            }
        }

        $invoiceId = (int)$input['invoice_id'];
        $patientId = (int)$input['patient_id'];
        $deptId = isset($input['department_id']) ? (int)$input['department_id'] : null;
        $amountPaid = (float)$input['amount_paid'];
        $paymentMethod = $input['payment_method'];
        $paymentDate = $input['payment_date'] ?? date('Y-m-d');
        $paymentTime = $input['payment_time'] ?? date('H:i:s');
        $referenceNumber = $input['reference_number'] ?? null;
        $bankName = $input['bank_name'] ?? null;
        $checkNumber = $input['check_number'] ?? null;
        $cardLastFour = $input['card_last_four'] ?? null;
        $notes = $input['notes'] ?? null;

        // Verify invoice
        $invStmt = $pdo->prepare("SELECT id, total, patient_id FROM invoices WHERE id = ?");
        $invStmt->execute([$invoiceId]);
        $inv = $invStmt->fetch();
        if (!$inv) error('Invoice not found', 404);
        if ((int)$inv['patient_id'] !== $patientId) error('Invoice does not belong to this patient', 409);

        // Check payment doesn't exceed balance
        $invBalStmt = $pdo->prepare("SELECT balance FROM invoices WHERE id = ?");
        $invBalStmt->execute([$invoiceId]);
        $balance = (float)$invBalStmt->fetchColumn();

        if ($amountPaid > $balance + 0.01) { // Allow small rounding difference
            error('Payment amount exceeds invoice balance', 422);
        }

        $pdo->beginTransaction();

        // Generate receipt number
        $receiptNumber = generateReceiptNumber($pdo);

        $userIdPR = $input['created_by'] ?? ($_SESSION['user_id'] ?? null);
        $empIdPR = null;
        if ($userIdPR) {
            $chk = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
            $chk->execute([$userIdPR]);
            if ($chk->fetchColumn()) {
                $empIdPR = (int)$userIdPR;
            } else {
                $uStmt = $pdo->prepare("SELECT e.id FROM users u JOIN employees e ON e.employee_no = u.employee_id WHERE u.id = ?");
                $uStmt->execute([$userIdPR]);
                $val = $uStmt->fetchColumn();
                if ($val) $empIdPR = (int)$val;
            }
        }

        // Record payment
        $stmt = $pdo->prepare("
            INSERT INTO payment_records 
            (invoice_id, patient_id, department_id, amount_paid, payment_method, 
             payment_date, payment_time, reference_number, receipt_number, 
             bank_name, check_number, card_last_four, status, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
        ");
        $stmt->execute([
            $invoiceId, $patientId, $deptId, $amountPaid, $paymentMethod,
            $paymentDate, $paymentTime, $referenceNumber, $receiptNumber,
            $bankName, $checkNumber, $cardLastFour,
            $notes, $empIdPR
        ]);

        $paymentRecordId = (int)$pdo->lastInsertId();

        // Allocate payment to invoice items
        $allocations = $input['allocations'] ?? [];
        allocatePayment($pdo, $paymentRecordId, $invoiceId, $amountPaid, $allocations);

        // Update invoice balance
        updateInvoiceBalance($pdo, $invoiceId);

        // Update daily payment summary
        $summaryStmt = $pdo->prepare("
            INSERT INTO payment_summary 
            (summary_date, department_id, payment_method, total_payments, payment_count, completed_amount)
            VALUES (?, ?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE 
                total_payments = total_payments + VALUES(total_payments),
                payment_count = payment_count + 1,
                completed_amount = completed_amount + VALUES(completed_amount),
                updated_at = NOW()
        ");
        $summaryStmt->execute([
            $paymentDate, $deptId, $paymentMethod, $amountPaid, $amountPaid
        ]);

        $pdo->commit();

        // Fetch created payment record
        $fetchStmt = $pdo->prepare("
            SELECT pr.*, i.invoice_no, p.first_name, p.last_name
            FROM payment_records pr
            LEFT JOIN invoices i ON i.id = pr.invoice_id
            LEFT JOIN patients p ON p.id = pr.patient_id
            WHERE pr.id = ?
        ");
        $fetchStmt->execute([$paymentRecordId]);
        success($fetchStmt->fetch(), 'Payment recorded successfully', 201);
    }

    // ===== PUT — Update payment record =====
    if ($method === 'PUT') {
        if (!$id) error('Payment ID required', 422);

        $stmt = $pdo->prepare("SELECT id FROM payment_records WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) error('Payment record not found', 404);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $action2 = $input['action'] ?? 'update';

        // Reverse/dispute payment
        if ($action2 === 'reverse') {
            $pdo->beginTransaction();

            // Get payment details
            $payStmt = $pdo->prepare("SELECT invoice_id, amount_paid FROM payment_records WHERE id = ?");
            $payStmt->execute([$id]);
            $payment = $payStmt->fetch();

            // Mark payment as reversed
            $pdo->prepare("UPDATE payment_records SET status = 'reversed', updated_at = NOW() WHERE id = ?")
                ->execute([$id]);

            // Update invoice balance
            updateInvoiceBalance($pdo, $payment['invoice_id']);

            $pdo->commit();
            success(null, 'Payment reversed successfully');
        }

        // Mark as verified
        if ($action2 === 'verify') {
            $pdo->prepare("
                UPDATE payment_records 
                SET status = 'completed', verified_by = ?, verified_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ")->execute([$input['verified_by'] ?? null, $id]);

            success(null, 'Payment verified');
        }

        // Update notes
        if ($action2 === 'update_notes') {
            $pdo->prepare("UPDATE payment_records SET notes = ? WHERE id = ?")
                ->execute([$input['notes'] ?? null, $id]);
            success(null, 'Notes updated');
        }

        error('Invalid action', 422);
    }

    // ===== DELETE =====
    if ($method === 'DELETE') {
        if (!$id) error('Payment ID required', 422);

        // Only allow deletion of failed payments
        $stmt = $pdo->prepare("SELECT invoice_id, status FROM payment_records WHERE id = ?");
        $stmt->execute([$id]);
        $payment = $stmt->fetch();

        if (!$payment) error('Payment record not found', 404);
        if ($payment['status'] !== 'failed') error('Only failed payments can be deleted', 409);

        $pdo->prepare("DELETE FROM payment_records WHERE id = ?")->execute([$id]);
        success(null, 'Payment record deleted');
    }

    error('Method not allowed', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Server error: ' . $e->getMessage(), 500);
}
?>
