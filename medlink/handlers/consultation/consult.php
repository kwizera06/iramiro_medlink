<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/consultation/consult.php
// Phase 2: Enhanced consultation system with forms and prescriptions
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/billing-calc.php'; // recalcInvoice: lab-consumption / Rx-quantity blocks kept from medlink
require_once __DIR__ . '/../../includes/request_schema.php';
require_once __DIR__ . '/../../includes/finance_posting.php';
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/tariff.php';
require_once __DIR__ . '/../../includes/numbering.php';
require_once __DIR__ . '/../../includes/allergy_check.php';
require_once __DIR__ . '/../../includes/vital_rules.php';
require_once __DIR__ . '/../../includes/lab_rules.php';
lab_ensure_schema($pdo);
require_once __DIR__ . '/../../includes/consult_events.php';
consult_events_boot($pdo); // DDL: must run before any transaction opens
require_once __DIR__ . '/../../includes/consult_schema.php';
require_once __DIR__ . '/../../includes/dosage_schema.php';
if (preg_match('/^(prescribed_dosage|dosage_alert|nursing_record$)/', (string)($_GET['action'] ?? ''))) ensure_dosage_schema($pdo); // DDL: before any transaction
require_once __DIR__ . '/../../includes/stock.php';
require_once __DIR__ . '/../../includes/upload_safe.php';
require_once __DIR__ . '/../../includes/record_lock.php';
if (!$pdo->inTransaction()) record_lock_schema($pdo);

// invoice_items.item_id (drug id) comes from the billing schema; ensured here, before any transaction opens (ALTER commits).
$GLOBALS['_ii_item_col'] = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && is_file(__DIR__ . '/../../includes/finance_schema.php')) {
    require_once __DIR__ . '/../../includes/finance_schema.php';
    if (function_exists('ensure_invoice_item_columns')) { ensure_invoice_item_columns($pdo); $GLOBALS['_ii_item_col'] = true; }
}
/** Insert an invoice line; a drug id goes to item_id, a service id to service_id (never mixed). */
function consult_invoice_line(PDO $pdo, int $invId, $serviceId, $itemId, string $desc, string $cat, $qty, $unit, $total): void {
    $serviceId = $serviceId ?: null; $itemId = $itemId ?: null;
    if ($GLOBALS['_ii_item_col']) {
        $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, item_id, description, category, quantity, unit_price, total) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$invId, $serviceId, $itemId, $desc, $cat, $qty, $unit, $total]);
    } else {
        $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, total) VALUES (?,?,?,?,?,?,?)")
            ->execute([$invId, $serviceId, $desc, $cat, $qty, $unit, $total]);
    }
}
/** Deduct lab consumables FIFO from $storeId: one stock movement and one lab_item_consumption row per item. The caller owns the transaction.
 *  $strict = refuse (RuntimeException) when the store holds less than asked; otherwise the shortfall goes negative on the last batch. */
function lab_consume_items(PDO $pdo, array $items, ?int $orderId, ?int $visitId, ?int $patientId, ?int $serviceId, int $storeId, string $notes, $userId, bool $strict = false): array {
    $deductedItems = [];

    foreach ($items as $it) {
        $itemId = (int)($it['item_id'] ?? 0);
        $qty = (float)($it['quantity'] ?? 0);
        $itemNotes = trim($it['notes'] ?? '');
        if (!$itemId || $qty <= 0) continue;
        if ($strict) {
            $av = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM stock_batches WHERE item_id = ? AND store_id = ? AND is_active = 1 AND quantity > 0");
            $av->execute([$itemId, $storeId]);
            $have = (float)$av->fetchColumn();
            if ($have + 0.00001 < $qty) {
                $nm = $pdo->prepare("SELECT name FROM items WHERE id = ?"); $nm->execute([$itemId]);
                $sn = $pdo->prepare("SELECT name FROM stores WHERE id = ?"); $sn->execute([$storeId]);
                throw new RuntimeException('Not enough ' . ($nm->fetchColumn() ?: 'item #' . $itemId) . ' in ' . ($sn->fetchColumn() ?: 'the store') . ' (available ' . $have . ', needed ' . $qty . ')');
            }
        }

        // FIFO deduction from stock_batches in selected store
        $batchesStmt = $pdo->prepare("
            SELECT id, quantity, unit_cost, batch_number 
            FROM stock_batches 
            WHERE item_id = ? AND store_id = ? AND is_active = 1 AND quantity > 0 
            ORDER BY received_date ASC, created_at ASC, id ASC
        ");
        $batchesStmt->execute([$itemId, $storeId]);
        $batches = $batchesStmt->fetchAll();

        $remaining = $qty;
        $totalCost = 0;
        $lastSmId = null;
        $lastBatchId = null;

        if (!empty($batches)) {
            foreach ($batches as $b) {
                if ($remaining <= 0) break;
                $deduct = min((float)$b['quantity'], $remaining);
                $newQty = (float)$b['quantity'] - $deduct;
                $pdo->prepare("UPDATE stock_batches SET quantity = ? WHERE id = ?")->execute([$newQty, $b['id']]);

                // Record stock movement for this batch
                $movementNote = 'Lab consumption' . ($orderId ? " [Order #$orderId]" : '') . ($itemNotes ? ": $itemNotes" : '');
                $pdo->prepare("
                    INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
                    VALUES (?, ?, ?, NULL, 'consumption', ?, ?, 'lab_consumption', ?, ?, ?, NOW())
                ")->execute([$itemId, $b['id'], $storeId, $deduct, (float)$b['unit_cost'], $orderId, $movementNote, $userId]);
                $lastSmId = (int)$pdo->lastInsertId();
                $lastBatchId = (int)$b['id'];

                $totalCost += $deduct * (float)$b['unit_cost'];
                $remaining -= $deduct;
            }
        }

        // If remaining quantity not covered by positive batches
        if ($remaining > 0) {
            // Find any batch in store or create default batch
            $anyBatch = $pdo->prepare("SELECT id, unit_cost FROM stock_batches WHERE item_id = ? AND store_id = ? ORDER BY id DESC LIMIT 1");
            $anyBatch->execute([$itemId, $storeId]);
            $bRow = $anyBatch->fetch();

            if ($bRow) {
                $batchId = (int)$bRow['id'];
                $unitCost = (float)$bRow['unit_cost'];
                $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ? WHERE id = ?")->execute([$remaining, $batchId]);
            } else {
                $unitCost = 0.0;
                $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, is_active, created_by, created_at) VALUES (?, ?, 'DEFAULT', ?, ?, 1, ?, NOW())")
                    ->execute([$itemId, $storeId, -$remaining, $unitCost, $userId]);
                $batchId = (int)$pdo->lastInsertId();
            }

            $movementNote = 'Lab consumption' . ($orderId ? " [Order #$orderId]" : '') . ($itemNotes ? ": $itemNotes" : '');
            $pdo->prepare("
                INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
                VALUES (?, ?, ?, NULL, 'consumption', ?, ?, 'lab_consumption', ?, ?, ?, NOW())
            ")->execute([$itemId, $batchId, $storeId, $remaining, $unitCost, $orderId, $movementNote, $userId]);
            $lastSmId = (int)$pdo->lastInsertId();
            $lastBatchId = $batchId;

            $totalCost += $remaining * $unitCost;
        }

        $unitCostAvg = $qty > 0 ? ($totalCost / $qty) : 0.0;

        // Record in lab_item_consumption
        $licStmt = $pdo->prepare("
            INSERT INTO lab_item_consumption (order_id, visit_id, patient_id, service_id, item_id, store_id, batch_id, quantity, unit_cost, status, stock_movement_id, consumed_by, consumed_at, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, NOW(), ?)
        ");
        $licStmt->execute([$orderId, $visitId, $patientId, $serviceId, $itemId, $storeId, $lastBatchId, $qty, $unitCostAvg, $lastSmId, $userId, $notes ?: $itemNotes]);
        $licId = (int)$pdo->lastInsertId();

        $deductedItems[] = [
            'consumption_id' => $licId,
            'item_id' => $itemId,
            'quantity' => $qty,
            'unit_cost' => $unitCostAvg,
            'store_id' => $storeId
        ];
    }

    return $deductedItems;
}

/** An X-ray order (not ultrasound / other imaging) must carry an image before it counts as done. */
function consult_is_xray(string $desc): bool { return (bool)preg_match('/x-?ray|radiograph/i', $desc); }
function consult_result_has_attachment($result): bool {
    $d = is_array($result) ? $result : json_decode((string)$result, true);
    return is_array($d) && !empty($d['attachments']);
}
function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

function syncInvoiceAndJournal(PDO $pdo, int $invoiceId): void {
    if (!$invoiceId) return;
    
    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoice_items WHERE invoice_id = ?");
    $sumStmt->execute([$invoiceId]);
    $subtotal = (float)$sumStmt->fetchColumn();

    $invStmt = $pdo->prepare("
        SELECT i.id, i.visit_id, i.patient_id, i.insurance_id, i.discount, i.tax, i.paid_amount,
               COALESCE(
                   (SELECT ip.copayment_percentage FROM patient_insurance pi JOIN insurance_packages ip ON ip.id = pi.package_id WHERE pi.patient_id = p.id AND (pi.id = i.insurance_id OR pi.id = v.insurance_id OR pi.company_id = i.insurance_id OR pi.company_id = v.insurance_id) ORDER BY pi.is_primary DESC LIMIT 1),
                   (SELECT ip.copayment_percentage FROM patient_insurance pi JOIN insurance_packages ip ON ip.id = pi.package_id WHERE pi.patient_id = p.id AND pi.is_primary = 1 LIMIT 1),
                   (SELECT ip.copayment_percentage FROM insurance_packages ip WHERE (ip.company_id = i.insurance_id OR ip.company_id = v.insurance_id) AND ip.status = 'active' LIMIT 1),
                   100
               ) AS copayment_percentage
        FROM invoices i
        JOIN patients p ON p.id = i.patient_id
        JOIN visits v ON v.id = i.visit_id
        WHERE i.id = ?
    ");
    $invStmt->execute([$invoiceId]);
    $inv = $invStmt->fetch();
    if (!$inv) return;

    $discount = (float)($inv['discount'] ?? 0);
    $tax = (float)($inv['tax'] ?? 0);
    $total = max(0, $subtotal - $discount + $tax);
    $paidAmount = (float)($inv['paid_amount'] ?? 0);
    $copayPct = (float)$inv['copayment_percentage'];

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?");
    $countStmt->execute([$invoiceId]);
    $remainCount = (int)$countStmt->fetchColumn();

    if ($remainCount === 0 || $total <= 0) {
        fin_void_invoice($pdo, $invoiceId);
        $pdo->prepare("DELETE FROM payment_records WHERE invoice_id=?")->execute([$invoiceId]);
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$invoiceId]);
        $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$invoiceId]);
        return;
    }

    $patientDue = fin_split($total, $copayPct)['patient'];
    $balance = max(0, $patientDue - $paidAmount);
    $status = $balance <= 0 && $total > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'issued');

    $pdo->prepare("UPDATE invoices SET subtotal=?, total=?, balance=?, status=? WHERE id=?")
        ->execute([$subtotal, $total, $balance, $status, $invoiceId]);

    fin_post_invoice_safe($pdo, $invoiceId);
}

/**
 * Bill the consultation fee when a consultation is completed.
 * Idempotent: a consultation already invoiced/paid/waived is skipped.
 * Uses consultation_fees for the department/type, with a system default when
 * the department has no fee configured.
 */
function bill_consultation_fee(PDO $pdo, int $consultId, int $visitId, int $patientId, int $deptId, ?int $userId): void {
    $q = $pdo->prepare("SELECT id, fee_status FROM consultations WHERE id = ?");
    $q->execute([$consultId]);
    $c = $q->fetch();
    if (!$c || in_array($c['fee_status'], ['invoiced', 'paid', 'waived'], true)) return;

    $vq = $pdo->prepare("SELECT visit_type, payment_type, insurance_id FROM visits WHERE id = ?");
    $vq->execute([$visitId]);
    $v = $vq->fetch();
    if (!$v) return;

    $type = 'initial';
    if (($v['visit_type'] ?? '') === 'emergency') {
        $type = 'emergency';
    } elseif (($v['visit_type'] ?? '') === 'follow_up') {
        $type = 'follow_up';
    } else {
        $prior = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE patient_id=? AND department_id=? AND status='completed' AND id<>? AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $prior->execute([$patientId, $deptId, $consultId]);
        if ((int)$prior->fetchColumn() > 0) $type = 'follow_up';
    }

    $fq = $pdo->prepare("SELECT * FROM consultation_fees WHERE department_id=? AND consultation_type=? AND is_active=1 AND effective_from<=CURDATE() AND (effective_to IS NULL OR effective_to>=CURDATE()) ORDER BY effective_from DESC LIMIT 1");
    $fq->execute([$deptId, $type]);
    $fee = $fq->fetch();
    if (!$fee) {
        $fq = $pdo->prepare("SELECT * FROM consultation_fees WHERE department_id=? AND is_active=1 AND effective_from<=CURDATE() AND (effective_to IS NULL OR effective_to>=CURDATE()) ORDER BY effective_from DESC LIMIT 1");
        $fq->execute([$deptId]);
        $fee = $fq->fetch();
    }
    if (!$fee) {
        $base = $type === 'follow_up' ? 75 : ($type === 'emergency' ? 200 : 150);
        $fee = ['id' => null, 'base_fee' => $base, 'insurance_fee' => $type === 'follow_up' ? 50 : 100, 'emergency_surcharge' => 0];
    }

    $insured = in_array($v['payment_type'] ?? '', ['insurance', 'mixed'], true) && !empty($v['insurance_id']);
    $amount = (float)($insured ? $fee['insurance_fee'] : $fee['base_fee']);
    if ($type === 'emergency') $amount += (float)($fee['emergency_surcharge'] ?? 0);
    if ($amount <= 0) return;

    $iq = $pdo->prepare("SELECT id FROM invoices WHERE visit_id=? AND patient_id=? AND status IN ('draft','issued','partial') ORDER BY id DESC LIMIT 1");
    $iq->execute([$visitId, $patientId]);
    $invId = (int)$iq->fetchColumn();
    if ($invId) {
        // Per-consultation idempotency: a visit can legitimately have more than one
        // consultation (referrals), so do not key this off the invoice alone.
        $dup = $pdo->prepare("SELECT COUNT(*) FROM consultation_fee_history WHERE consultation_id=?");
        $dup->execute([$consultId]);
        if ((int)$dup->fetchColumn() > 0) {
            $pdo->prepare("UPDATE consultations SET fee_status='invoiced' WHERE id=? AND fee_status='pending'")->execute([$consultId]);
            return;
        }
    } else {
        $invNo = next_invoice_no($pdo);
        $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, department_id, consultation_fee_included, consultation_fee_amount, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,'issued',?,?,1,?,?,NOW())")
            ->execute([$invNo, $visitId, $patientId, $v['payment_type'], $v['insurance_id'], $amount, $amount, $amount, 'Consultation fee', $deptId, $amount, $userId]);
        $invId = (int)$pdo->lastInsertId();
    }

    $desc = 'Consultation - ' . ucfirst($type);
    $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, category, quantity, unit_price, total) VALUES (?,?,?,1,?,?)")
        ->execute([$invId, $desc, 'consultation', $amount, $amount]);
    $pdo->prepare("UPDATE invoices SET consultation_fee_included=1, consultation_fee_amount=COALESCE(consultation_fee_amount,0)+?, department_id=COALESCE(department_id,?) WHERE id=?")
        ->execute([$amount, $deptId, $invId]);
    $pdo->prepare("UPDATE consultations SET consultation_fee_id=?, fee_amount=?, fee_status='invoiced' WHERE id=?")
        ->execute([!empty($fee['id']) ? (int)$fee['id'] : null, $amount, $consultId]);
    $pdo->prepare("INSERT INTO consultation_fee_history (consultation_id, consultation_fee_id, original_fee, adjusted_fee, adjustment_reason, adjusted_by) VALUES (?,?,?,?,?,?)")
        ->execute([$consultId, !empty($fee['id']) ? (int)$fee['id'] : null, $amount, $amount, 'Billed on completion', $userId]);
    syncInvoiceAndJournal($pdo, $invId);
}

ensureOrderInvoiceLink($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';
if ($method === 'CLI') return;

// For POST requests, also check action from JSON body
if ($method === 'POST' && !$action) {
    $rawBody = file_get_contents('php://input');
    $bodyData = json_decode($rawBody, true) ?? [];
    if (!empty($bodyData['action'])) {
        $action = $bodyData['action'];
    }
    // Store for later use so we don't read php://input twice
    $GLOBALS['_parsed_input'] = $bodyData;
}
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ============================================================
// PHASE 2 ACTIONS: Enhanced Consultation System
// ============================================================

// GET REQUIRED FORMS FOR A DEPARTMENT
// GET /consultation/consult?action=get_forms&department_id=X&visit_id=X
if ($method === 'GET' && $action === 'get_forms') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
    
    if (!$deptId) error('department_id required', 422);
    
    // Get all active form templates for this department
    $stmt = $pdo->prepare("
        SELECT t.* FROM department_form_templates t
        WHERE t.department_id = ? AND t.status = 'active'
        ORDER BY t.sort_order ASC
    ");
    $stmt->execute([$deptId]);
    $templates = $stmt->fetchAll();
    
    // For each template, get fields and check if already submitted
    foreach ($templates as &$t) {
        $fStmt = $pdo->prepare("
            SELECT * FROM department_form_fields
            WHERE template_id = ?
            ORDER BY sort_order ASC
        ");
        $fStmt->execute([$t['id']]);
        $t['fields'] = $fStmt->fetchAll();
        
        // Decode field options
        foreach ($t['fields'] as &$f) {
            if ($f['field_options']) {
                $f['field_options'] = json_decode($f['field_options'], true);
            }
            if ($f['validation_rules']) {
                $f['validation_rules'] = json_decode($f['validation_rules'], true);
            }
        }
        
        // Check if already submitted
        if ($visitId) {
            $sStmt = $pdo->prepare("
                SELECT id, form_data, status FROM visit_department_forms
                WHERE visit_id = ? AND template_id = ?
            ");
            $sStmt->execute([$visitId, $t['id']]);
            $submitted = $sStmt->fetch();
            if ($submitted) {
                $t['is_submitted'] = true;
                $t['submission_id'] = $submitted['id'];
                $t['submission_status'] = $submitted['status'];
                $t['form_data'] = json_decode($submitted['form_data'], true);
            } else {
                $t['is_submitted'] = false;
                $t['submission_id'] = null;
                $t['form_data'] = [];
            }
        }
    }
    
    success($templates);
}

// Visits created without department_id: recover it from the visit, then its latest queue row
function consult_is_admin(PDO $pdo): bool {
    $q = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug IN ('admin','administrator') LIMIT 1");
    $q->execute([(int)($_SESSION['user_id'] ?? 0)]);
    return (bool)$q->fetchColumn();
}
/** Invoices on the visit still owing money (cancelled ones ignored). */
function consult_unpaid_count(PDO $pdo, int $visitId): int {
    $q = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE visit_id = ? AND status <> 'cancelled' AND balance > 0");
    $q->execute([$visitId]);
    return (int)$q->fetchColumn();
}
/** Final Diagnosis (10) and Medication (11) cannot change while the visit has unpaid invoices; admin is exempt. $oldDx / $oldRx = what is saved now. */
function consult_pay_lock(PDO $pdo, int $visitId, ?string $newDx, $newRx, string $oldDx, $oldRx): void {
    $norm = function ($v) { if (is_string($v)) $v = json_decode($v, true); return json_encode(is_array($v) ? array_values($v) : []); };
    $changed = ($newDx !== null && trim($newDx) !== trim($oldDx)) || ($newRx !== null && $norm($newRx) !== $norm($oldRx));
    if ($changed && !consult_is_admin($pdo) && ($n = consult_unpaid_count($pdo, $visitId)) > 0) {
        error("Final Diagnosis and Medication are locked until all invoices of this visit are paid ($n unpaid).", 423);
    }
}

function fillMissingDeptId(PDO $pdo, array $input): array {
    if (empty($input['department_id']) && !empty($input['visit_id'])) {
        $s = $pdo->prepare("SELECT COALESCE(NULLIF(v.department_id,0), (SELECT pq.department_id FROM patient_queue pq WHERE pq.visit_id=v.id ORDER BY pq.id DESC LIMIT 1)) FROM visits v WHERE v.id=?");
        $s->execute([(int)$input['visit_id']]);
        $input['department_id'] = (int)$s->fetchColumn();
    }
    return $input;
}

// SAVE CONSULTATION WITH FORMS AND PRESCRIPTIONS (ATOMIC)
// POST /consultation/consult?action=save_with_forms
if ($method === 'POST' && $action === 'save_with_forms') {
    $input = fillMissingDeptId($pdo, json_decode(file_get_contents('php://input'), true) ?? []);

    foreach (['visit_id', 'patient_id', 'department_id'] as $f) {
        if (empty($input[$f])) error("Field '$f' required", 422);
    }
    
    try {
        $pdo->beginTransaction();
        
        $visitId = (int)$input['visit_id'];
        $patientId = (int)$input['patient_id'];
        $deptId = (int)$input['department_id'];
        $oq = $pdo->prepare("SELECT diagnosis FROM consultations WHERE visit_id=? AND department_id=?");
        $oq->execute([$visitId, $deptId]);
        consult_pay_lock($pdo, $visitId, isset($input['diagnosis']) || isset($input['final_diagnosis']) ? (string)($input['final_diagnosis'] ?? $input['diagnosis']) : null,
            !empty($input['prescriptions']) ? $input['prescriptions'] : null, (string)$oq->fetchColumn(), null);
        // Ensure created_by is set from session if not provided
        if (empty($input['created_by']) && !empty($_SESSION['user_id'])) {
            $input['created_by'] = (int)$_SESSION['user_id'];
        }
        
        // 1. Save or update consultation
        $consultFields = [
            'visit_id', 'patient_id', 'department_id', 'doctor_id', 'doctor_name',
            'bp_systolic', 'bp_diastolic', 'pulse', 'temperature', 'weight', 'height', 
            'oxygen_sat', 'respiratory_rate', 'chief_complaint', 'history', 'examination', 
            'differential_diagnosis', 'diagnosis', 'icd_code', 'treatment_plan', 'notes', 'outcome', 'referred_to_dept', 
            'admit_ward', 'follow_up_date', 'status', 'created_by',
            'step1_by', 'step1_at', 'step1_name',
            'step3_by', 'step3_at', 'step3_name',
            'completed_by', 'completed_at', 'completed_name'
        ];

        // Resolve current user name for audit
        $auditUserId = $_SESSION['user_id'] ?? null;
        $auditUserName = $input['doctor_name'] ?? null;
        if (!$auditUserName && $auditUserId) {
            $uStmt = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?");
            $uStmt->execute([$auditUserId]);
            $auditUserName = $uStmt->fetchColumn() ?: ('User #' . $auditUserId);
        }

        // Determine which step is being saved and stamp it
        $isComplete = !empty($input['action']) && $input['action'] === 'complete';
        $hasStep1Fields = isset($input['chief_complaint']) || isset($input['history']) || isset($input['examination']) || isset($input['differential_diagnosis']);
        $hasStep3Fields = isset($input['diagnosis']) || isset($input['final_diagnosis']) || isset($input['treatment_plan']) || isset($input['prescription']);

        if ($isComplete) {
            $input['completed_by']   = $auditUserId;
            $input['completed_at']   = date('Y-m-d H:i:s');
            $input['completed_name'] = $auditUserName;
            // Also stamp step3 if not already set
            $input['step3_by']   = $input['step3_by']   ?? $auditUserId;
            $input['step3_at']   = $input['step3_at']   ?? date('Y-m-d H:i:s');
            $input['step3_name'] = $input['step3_name'] ?? $auditUserName;
        } elseif ($hasStep1Fields && !$hasStep3Fields) {
            $input['step1_by']   = $auditUserId;
            $input['step1_at']   = date('Y-m-d H:i:s');
            $input['step1_name'] = $auditUserName;
        } elseif ($hasStep3Fields) {
            $input['step3_by']   = $auditUserId;
            $input['step3_at']   = date('Y-m-d H:i:s');
            $input['step3_name'] = $auditUserName;
        }
        
        // Check if consultation exists
        $chk = $pdo->prepare("SELECT id FROM consultations WHERE visit_id = ? AND department_id = ?");
        $chk->execute([$visitId, $deptId]);
        $existingId = $chk->fetchColumn();
        
        $consultId = $existingId;
        
        if ($existingId) {
            // Update existing
            $sets = [];
            $params = [];
            foreach ($consultFields as $f) {
                if (array_key_exists($f, $input) && !in_array($f, ['visit_id', 'patient_id', 'department_id', 'created_by'])) {
                    // the doctor who started the file stays on record; a later doctor is logged in consultation_events
                    $sets[] = $f === 'doctor_id' ? "$f = COALESCE($f, ?)" : ($f === 'doctor_name' ? "$f = COALESCE(NULLIF($f, ''), ?)" : "$f = ?");
                    $params[] = $input[$f] === '' ? null : $input[$f];
                }
            }
            if ($sets) {
                $params[] = $existingId;
                $pdo->prepare("UPDATE consultations SET " . implode(', ', $sets) . " WHERE id = ?")
                    ->execute($params);
            }
            log_activity($pdo, 'UPDATE', 'consultations', 'Consultation updated for visit #' . $visitId, $existingId, null);
            if (!$isComplete) consult_event($pdo, $visitId, $hasStep3Fields ? 'Step 3 saved (Diagnosis & Prescription)' : 'Step 1 saved (Notes & History)', '', (int)$existingId);
        } else {
            // Create new
            $cols = [];
            $vals = [];
            $params = [];
            foreach ($consultFields as $f) {
                if (array_key_exists($f, $input)) {
                    $cols[] = $f;
                    $vals[] = '?';
                    $params[] = $input[$f] === '' ? null : $input[$f];
                }
            }
            $pdo->prepare("INSERT INTO consultations (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")")
                ->execute($params);
            $consultId = (int)$pdo->lastInsertId();
            log_activity($pdo, 'CREATE', 'consultations', 'Consultation recorded for visit #'.$visitId, $consultId);
            consult_event($pdo, $visitId, 'Consultation started', '', (int)$consultId);
        }
        
        // 2. Submit forms (if provided)
        if (!empty($input['forms'])) {
            foreach ($input['forms'] as $form) {
                $templateId = (int)$form['template_id'];
                $formData = $form['form_data'] ?? [];
                
                // Check if form already submitted
                $existing = $pdo->prepare("SELECT id FROM visit_department_forms WHERE visit_id = ? AND template_id = ?");
                $existing->execute([$visitId, $templateId]);
                $existingFormId = $existing->fetchColumn();
                
                if ($existingFormId) {
                    // Update
                    $pdo->prepare("
                        UPDATE visit_department_forms
                        SET form_data = ?, status = ?, filled_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([
                        json_encode($formData),
                        $form['status'] ?? 'completed',
                        $input['created_by'] ?? null,
                        $existingFormId
                    ]);
                } else {
                    // Insert new
                    $pdo->prepare("
                        INSERT INTO visit_department_forms (visit_id, patient_id, template_id, department_id, form_data, status, filled_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $visitId,
                        $patientId,
                        $templateId,
                        $deptId,
                        json_encode($formData),
                        $form['status'] ?? 'completed',
                        $input['created_by'] ?? null
                    ]);
                    
                    $formId = (int)$pdo->lastInsertId();
                    
                    // Link form to consultation
                    $pdo->prepare("
                        INSERT INTO consultation_form_references (consultation_id, form_id)
                        VALUES (?, ?)
                    ")->execute([$consultId, $formId]);
                }
            }
        }
        
        // 3. Save prescription items (if provided)
        $prescriptionBatchId = null;
        if (!empty($input['prescriptions']) && is_array($input['prescriptions'])) {
            // Check if prescription batch exists
            $pBatch = $pdo->prepare("SELECT id FROM prescription_batches WHERE consultation_id = ?");
            $pBatch->execute([$consultId]);
            $existingBatchId = $pBatch->fetchColumn();
            
            if (!$existingBatchId) {
                // Create new prescription batch
                $prescriptionNo = 'RX-' . date('YmdHis') . '-' . $consultId;
                $pdo->prepare("
                    INSERT INTO prescription_batches (consultation_id, visit_id, patient_id, doctor_id, prescription_no, status)
                    VALUES (?, ?, ?, ?, ?, 'draft')
                ")->execute([$consultId, $visitId, $patientId, $input['doctor_id'] ?? null, $prescriptionNo]);
                $prescriptionBatchId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'CREATE', 'consultations', 'Prescription created: ' . $prescriptionNo . ' for visit #' . $visitId, $prescriptionBatchId, $prescriptionNo);
            } else {
                $prescriptionBatchId = $existingBatchId;
                // Delete existing items for this batch
                $pdo->prepare("DELETE FROM prescription_items WHERE batch_id = ?")->execute([$prescriptionBatchId]);
            }
            
            // Add prescription items
            foreach ($input['prescriptions'] as $presc) {
                $pdo->prepare("
                    INSERT INTO prescription_items (batch_id, item_id, dosage, frequency, duration, quantity, unit, route, instructions, is_controlled, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $prescriptionBatchId,
                    (int)$presc['item_id'],
                    $presc['dosage'] ?? '',
                    $presc['frequency'] ?? '',
                    $presc['duration'] ?? '',
                    $presc['quantity'] ?? 0,
                    $presc['unit'] ?? 'tablet(s)',
                    $presc['route'] ?? 'oral',
                    $presc['instructions'] ?? '',
                    $presc['is_controlled'] ?? 0,
                    'pending'
                ]);
            }
        }
        
        // 4. Update consultation batch references
        if ($prescriptionBatchId) {
            $pdo->prepare("UPDATE consultations SET prescription_batch_id = ? WHERE id = ?")
                ->execute([$prescriptionBatchId, $consultId]);
        }
        
        // 5. Handle consultation completion
        if (($input['status'] ?? '') === 'completed') {
            $outcome = $input['outcome'] ?? null;
            $vStatus = match($outcome) {
                'admitted' => 'in_progress',
                'referred' => 'in_progress',
                'discharged', 'treated', 'follow_up' => 'completed',
                default => 'in_progress'
            };
            $pdo->prepare("UPDATE visits SET status = ?, consultation_status = 'done' WHERE id = ?")
                ->execute([$vStatus, $visitId]);
            
            $pdo->prepare("UPDATE patient_queue SET status = 'done', done_at = NOW() WHERE visit_id = ? AND department_id = ?")
                ->execute([$visitId, $deptId]);

            log_activity($pdo, 'UPDATE', 'consultations', 'Consultation completed for visit #' . $visitId . ($outcome ? ', outcome: ' . $outcome : ''), $consultId, null);
            consult_event($pdo, $visitId, 'Consultation completed', $outcome ? 'Outcome: ' . $outcome : '', (int)$consultId);
            
            // Update department history
            $pdo->prepare("UPDATE visit_department_history SET status = 'completed', completed_at = NOW() WHERE visit_id = ? AND department_id = ? AND status IN ('pending','in_progress')") /* DAT-07: a hand-off never started via Consult stays pending */
                ->execute([$visitId, $deptId]);

            // MON-01: bill the consultation fee in the same transaction
            bill_consultation_fee($pdo, $consultId, $visitId, $patientId, $deptId, $auditUserId ? (int)$auditUserId : null);
        }
        
        // 6. Fetch complete consultation with all data
        $fetchStmt = $pdo->prepare("
            SELECT c.*, d.name AS dept_name
            FROM consultations c
            LEFT JOIN departments d ON d.id = c.department_id
            WHERE c.id = ?
        ");
        $fetchStmt->execute([$consultId]);
        $result = $fetchStmt->fetch();
        
        // Get prescriptions
        if ($prescriptionBatchId) {
            $pStmt = $pdo->prepare("
                SELECT pi.*, i.name AS item_name
                FROM prescription_items pi
                LEFT JOIN items i ON i.id = pi.item_id
                WHERE pi.batch_id = ?
            ");
            $pStmt->execute([$prescriptionBatchId]);
            $result['prescriptions'] = $pStmt->fetchAll();
        }
        
        // Get orders
        $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id = ? ORDER BY ordered_at DESC");
        $oStmt->execute([$consultId]);
        $result['orders'] = $oStmt->fetchAll();
        
        $pdo->commit();
        
        success($result, $existingId ? 'Consultation updated with forms and prescriptions' : 'Consultation created with forms and prescriptions', 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ============================================================
// LEGACY CONSULTATION OPERATIONS
// ============================================================

if (!function_exists('consult_dashboard_counts')) {
    /** Clinic-wide counters used by the dashboard KPI cards (same rules as the screens they mirror). */
    function consult_dashboard_counts(PDO $pdo, string $f, string $t): array {
        $n = function (string $sql, array $p) use ($pdo) { $q = $pdo->prepare($sql); $q->execute($p); return (int)$q->fetchColumn(); };
        return [
            'patients_today'  => $n("SELECT COUNT(DISTINCT patient_id) FROM visits WHERE visit_date BETWEEN ? AND ? AND status <> 'cancelled'", [$f, $t]),
            'in_queue'        => $n("SELECT COUNT(*) FROM patient_queue WHERE queue_date BETWEEN ? AND ? AND status IN ('waiting','called','in_consultation')", [$f, $t]),
            'hospitalization' => $n("SELECT COUNT(*) FROM bed_assignments WHERE status = 'active'", []),
            'emergency'       => $n("SELECT COUNT(*) FROM emergency_cases WHERE DATE(created_at) BETWEEN ? AND ?", [$f, $t]),
            'completed'       => $n("SELECT COUNT(*) FROM visits WHERE visit_date BETWEEN ? AND ? AND status = 'completed'", [$f, $t]),
            'unclosed'        => $n("SELECT COUNT(*) FROM visits WHERE visit_date BETWEEN ? AND ? AND status IN ('open','in_progress')", [$f, $t]),
            'lab_pending'     => $n("SELECT COUNT(*) FROM medical_orders WHERE order_type = 'lab' AND status IN ('ordered','in_progress') AND DATE(ordered_at) BETWEEN ? AND ?", [$f, $t]),
            'lab_tests'       => $n("SELECT COUNT(*) FROM medical_orders WHERE order_type = 'lab' AND status <> 'cancelled' AND DATE(ordered_at) BETWEEN ? AND ?", [$f, $t]),
            'procedures'      => $n("SELECT COUNT(*) FROM medical_orders WHERE order_type = 'procedure' AND status <> 'cancelled' AND DATE(ordered_at) BETWEEN ? AND ?", [$f, $t]),
            'drugs_given'     => $n("SELECT COUNT(*) FROM nursing_records WHERE record_type IN ('medication','injection') AND DATE(administered_at) BETWEEN ? AND ?", [$f, $t])
                               + $n("SELECT COUNT(*) FROM medication_administrations WHERE DATE(administered_at) BETWEEN ? AND ?", [$f, $t]),
        ];
    }
}

// GET — load consultation + orders + patient history
// ---- Dental: tooth chart + treatment plans (writes only on Dental Clinic visits) ----
$DENTAL_CONDS = ['healthy','cavity','filled','crown','bridge','implant','missing','root_canal','extraction_needed','fractured','impacted'];
function dental_require_visit(PDO $pdo, int $visitId): void {
    $q = $pdo->prepare("SELECT d.name FROM visits v JOIN departments d ON d.id = v.department_id WHERE v.id = ?");
    $q->execute([$visitId]);
    if (stripos((string)$q->fetchColumn(), 'dent') === false) error('Dental records can only be written on a Dental Clinic visit', 403);
}
function dental_sync_plan(PDO $pdo, int $planId): void {
    $c = $pdo->prepare("SELECT COALESCE(SUM(status='done'),0), COALESCE(SUM(status<>'cancelled'),0) FROM dental_plan_items WHERE plan_id=?");
    $c->execute([$planId]);
    [$done, $live] = array_map('intval', $c->fetch(PDO::FETCH_NUM));
    $st = ($live > 0 && $done === $live) ? 'completed' : ($done > 0 ? 'in_progress' : 'approved');
    $pdo->prepare("UPDATE dental_treatment_plans SET status=? WHERE id=? AND status IN ('approved','in_progress','completed')")->execute([$st, $planId]);
}
// A finished treatment updates the tooth chart by itself. Only single-tooth work maps to a condition; scaling, X-rays etc. leave the chart alone.
function dental_chart_cond(string $rhic): ?string {
    if (in_array($rhic, ['RHIC-DENT-017','RHIC-DENT-018','RHIC-DENT-019','RHIC-DENT-020','RHIC-DENT-021','RHIC-DENT-022'], true)) return 'missing';
    if (in_array($rhic, ['RHIC-DENT-036','RHIC-DENT-037','RHIC-DENT-038','RHIC-DENT-039','RHIC-DENT-040','RHIC-DENT-041','RHIC-DENT-048','RHIC-DENT-043','RHIC-DENT-005','RHIC-DENT-007','RHIC-DENT-042'], true)) return 'filled';
    if ($rhic === 'RHIC-DENT-008') return 'crown';
    if (in_array($rhic, ['RHIC-DENT-045','RHIC-DENT-046','RHIC-DENT-047'], true)) return 'root_canal';
    if ($rhic === 'RHIC-DENT-044') return 'healthy';
    return null;
}
/** Take the service's linked supplies (service_catalog_items) out of the Dental Clinic store, oldest batch first. Never blocks: a shortage goes negative and is returned as a warning. */
function dental_consume_supplies(PDO $pdo, int $serviceId, int $visitId, int $planItemId, string $label): array {
    $warn = [];
    $store = $pdo->query("SELECT id FROM stores WHERE code='DEPT-26' LIMIT 1")->fetchColumn();
    if (!$store) return ['Dental store not found: no supplies deducted'];
    $links = $pdo->prepare("SELECT sci.item_id, sci.default_quantity, i.name FROM service_catalog_items sci JOIN items i ON i.id = sci.item_id WHERE sci.service_id = ?");
    $links->execute([$serviceId]);
    $bat = $pdo->prepare("SELECT id, quantity, unit_cost FROM stock_batches WHERE item_id=? AND store_id=? AND is_active=1 ORDER BY (quantity>0) DESC, received_date, id");
    $mov = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by) VALUES (?,?,?,'consumption',?,?,'dental_consumption',?,?,?)");
    foreach ($links->fetchAll() as $l) {
        $need = (float)$l['default_quantity']; $have = 0.0;
        $bat->execute([$l['item_id'], $store]);
        $batches = $bat->fetchAll();
        foreach ($batches as $b) $have += max(0, (float)$b['quantity']);
        if ($have + 0.00001 < $need) $warn[] = "Low stock: {$l['name']} (have $have, used $need)";
        foreach ($batches as $k => $b) {
            if ($need <= 0) break;
            $last = ($k === array_key_last($batches));
            $take = $last ? $need : min(max(0, (float)$b['quantity']), $need);   // the last batch absorbs any shortage (goes negative)
            if ($take <= 0) continue;
            $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ? WHERE id=?")->execute([$take, $b['id']]);
            $mov->execute([$l['item_id'], $b['id'], $store, $take, $b['unit_cost'], $planItemId, "Dental: $label (visit #$visitId)", $_SESSION['user_id'] ?? null]);
            $need -= $take;
        }
        if ($need > 0 && !$batches) $warn[] = "No stock record for {$l['name']}: nothing deducted";
    }
    return $warn;
}
// POST — after a dental order is billed: tie it to its invoice line (and create a plan item when it was ordered directly, so it is tracked too)
if ($method === 'POST' && $action === 'dental_order_link') {
    consult_ensure_schema($pdo);
    $in = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $vid = (int)($in['visit_id'] ?? 0); $pid = (int)($in['patient_id'] ?? 0); $sid = (int)($in['service_id'] ?? 0);
    $tooth = trim((string)($in['tooth'] ?? '')); $itemId = (int)($in['plan_item_id'] ?? 0);
    dental_require_visit($pdo, $vid);
    $pv = $pdo->prepare("SELECT patient_id FROM visits WHERE id=?"); $pv->execute([$vid]); $pid = (int)$pv->fetchColumn();   // the visit decides the patient
    $sv = $pdo->prepare("SELECT procedure_name FROM service_catalog WHERE id=? AND speciality LIKE 'Dentist%'"); $sv->execute([$sid]);
    $name = $sv->fetchColumn(); if (!$name) error('Not a dental service', 422);
    $c = $pdo->prepare("SELECT ii.id, ii.description FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
        WHERE i.visit_id=? AND i.status<>'cancelled' AND ii.service_id=? AND ii.id NOT IN (SELECT invoice_item_id FROM dental_plan_items WHERE invoice_item_id IS NOT NULL) ORDER BY ii.id");
    $c->execute([$vid, $sid]);
    $invId = 0; $want = "$name — Tooth $tooth";
    foreach ($c->fetchAll() as $r) if ($r['description'] === $want || str_starts_with($r['description'], "$want — ")) { $invId = (int)$r['id']; break; }
    if (!$invId) error('Invoice line not found for this treatment', 404);
    if (!$itemId) {   // ordered directly: one auto plan per visit holds these items
        $p = $pdo->prepare("SELECT id FROM dental_treatment_plans WHERE visit_id=? AND title='Visit treatments' LIMIT 1"); $p->execute([$vid]);
        $planId = (int)$p->fetchColumn();
        if (!$planId) { $pdo->prepare("INSERT INTO dental_treatment_plans (patient_id, visit_id, title, status, created_by) VALUES (?,?,'Visit treatments','approved',?)")->execute([$pid, $vid, $_SESSION['user_id'] ?? null]); $planId = (int)$pdo->lastInsertId(); }
        $pdo->prepare("INSERT INTO dental_plan_items (plan_id, service_id, procedure_name, tooth, stage, est_price, invoice_item_id) VALUES (?,?,?,?,1,?,?)")
            ->execute([$planId, $sid, $name, mb_substr($tooth, 0, 40), max(0, (float)($in['price'] ?? 0)), $invId]);
    } else {
        $pdo->prepare("UPDATE dental_plan_items SET invoice_item_id=? WHERE id=? AND invoice_item_id IS NULL")->execute([$invId, $itemId]);
    }
    success(null, 'Linked');
}
if ($method === 'GET' && $action === 'dental_data') {
    consult_ensure_schema($pdo);
    $pid = (int)($_GET['patient_id'] ?? 0);
    if (!$pid) error('patient_id required', 422);
    $r = $pdo->prepare("SELECT r.*, CONCAT(u.first_name,' ',u.last_name) AS by_name FROM dental_tooth_records r LEFT JOIN users u ON u.id = r.recorded_by WHERE r.patient_id=? ORDER BY r.recorded_at DESC, r.id DESC");
    $r->execute([$pid]);
    $ps = $pdo->prepare("SELECT * FROM dental_treatment_plans WHERE patient_id=? ORDER BY id DESC");
    $ps->execute([$pid]);
    $plans = $ps->fetchAll();
    // pay: unbilled (no invoice line yet) | unpaid | paid (invoice balance cleared, or fully covered by the insurer)
    $it = $pdo->prepare("SELECT x.*, IF(x.invoice_item_id IS NULL, 'unbilled', IF(inv.status <> 'cancelled' AND inv.balance <= 0, 'paid', 'unpaid')) AS pay
        FROM dental_plan_items x LEFT JOIN invoice_items ii ON ii.id = x.invoice_item_id LEFT JOIN invoices inv ON inv.id = ii.invoice_id WHERE x.plan_id=? ORDER BY x.stage, x.id");
    foreach ($plans as &$p) { $it->execute([$p['id']]); $p['items'] = $it->fetchAll(); }
    unset($p);
    success(['records' => $r->fetchAll(), 'plans' => $plans]);
}
if ($method === 'GET') {

    // Is the Final Diagnosis / Medication part of this visit locked by unpaid invoices?
    if ($action === 'visit_pay_gate') {
        $vid = (int)($_GET['visit_id'] ?? 0);
        if (!$vid) error('visit_id required', 422);
        success(['unpaid' => consult_unpaid_count($pdo, $vid), 'admin' => consult_is_admin($pdo)]);
    }

    // Dashboard cards for doctor / nurse / admin. Clinic-wide counts; each uses the same rule as the screen it mirrors.
    if ($action === 'dashboard_counts') {
        $f = preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-d');
        $t = preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['date_to'] ?? '') ? $_GET['date_to'] : date('Y-m-d');
        success(consult_dashboard_counts($pdo, $f, $t));
    }

    // Full doctor / nurse dashboard: KPI counters, department patient list, gender split,
    // top diagnoses, drugs administered and emergency / admission escalations.
    if ($action === 'dashboard_data') {
        $f = preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : date('Y-m-d');
        $t = preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['date_to'] ?? '') ? $_GET['date_to'] : date('Y-m-d');
        $dept = max(0, (int)($_GET['department_id'] ?? 0));
        $uid  = (int)($_SESSION['user_id'] ?? 0);

        $counts = consult_dashboard_counts($pdo, $f, $t);

        // The logged-in doctor / nurse department (users.department_id).
        $myDeptId = 0; $myDeptName = '';
        if ($uid) {
            $q = $pdo->prepare("SELECT u.department_id, d.name FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ?");
            $q->execute([$uid]);
            if ($row = $q->fetch()) {
                $myDeptId = (int)($row['department_id'] ?? 0);
                $myDeptName = (string)($row['name'] ?? '');
            }
        }

        // Departments that actually have visits in this range (for the filter).
        $dq = $pdo->prepare("SELECT d.id, d.name FROM departments d
            WHERE d.is_active = 1 OR d.id IN (SELECT v.department_id FROM visits v WHERE v.visit_date BETWEEN ? AND ? AND v.status <> 'cancelled')
            ORDER BY d.name");
        $dq->execute([$f, $t]);
        $departments = $dq->fetchAll();
        if ($myDeptId && $myDeptName) {
            $has = false;
            foreach ($departments as $d) { if ((int)$d['id'] === $myDeptId) { $has = true; break; } }
            if (!$has) array_unshift($departments, ['id' => $myDeptId, 'name' => $myDeptName]);
        }

        // Department patient list (one row per visit), with patient number, age and insurance.
        $vq = $pdo->prepare("
            SELECT v.id, v.visit_no, v.visit_date, v.visit_time, v.visit_type, v.status, v.consultation_status,
                   v.payment_type, v.department_id,
                   p.id AS patient_id, p.first_name, p.last_name, p.patient_no, p.gender, p.date_of_birth, p.phone, p.created_at AS registered_at,
                   d.name AS department_name,
                   COALESCE(ic.name, '') AS insurance_name,
                   COALESCE((SELECT ip.copayment_percentage FROM insurance_packages ip WHERE ip.id = COALESCE(pi.package_id, pif.package_id)),
                            (SELECT ip.copayment_percentage FROM insurance_packages ip WHERE ip.company_id = ic.id AND ip.status = 'active' ORDER BY ip.id LIMIT 1)) AS copay_pct,
                   EXISTS(SELECT 1 FROM bed_assignments ba WHERE ba.visit_id = v.id AND ba.status = 'active') AS is_admitted,
                   EXISTS(SELECT 1 FROM emergency_cases ec WHERE ec.visit_id = v.id) AS is_er,
                   (SELECT COUNT(*) FROM medical_orders mo WHERE mo.visit_id = v.id AND mo.order_type = 'lab'
                      AND mo.status IN ('ordered','in_progress')) AS lab_pending
            FROM visits v
            JOIN patients p ON p.id = v.patient_id
            LEFT JOIN departments d ON d.id = v.department_id
            LEFT JOIN patient_insurance pi ON pi.id = (
                SELECT pi2.id FROM patient_insurance pi2
                WHERE pi2.patient_id = v.patient_id AND (pi2.id = v.insurance_id OR pi2.company_id = v.insurance_id)
                ORDER BY (pi2.id = v.insurance_id) DESC, pi2.is_primary DESC, pi2.id DESC
                LIMIT 1
            )
            LEFT JOIN patient_insurance pif ON pif.id = (
                SELECT pi3.id FROM patient_insurance pi3
                WHERE pi3.patient_id = v.patient_id AND pi3.is_primary = 1
                ORDER BY pi3.id DESC LIMIT 1
            ) AND pi.id IS NULL
            LEFT JOIN insurance_companies ic ON ic.id = COALESCE(pi.company_id, pif.company_id, v.insurance_id)
            WHERE v.visit_date BETWEEN ? AND ? AND v.status <> 'cancelled'
              AND (? = 0 OR v.department_id = ?)
            ORDER BY v.visit_date DESC, v.visit_time DESC
            LIMIT 400
        ");
        $vq->execute([$f, $t, $dept, $dept]);
        $visits = $vq->fetchAll();

        $firstTimeRaw = '';
        foreach ($visits as $vv) {
            if (!empty($vv['visit_time']) && ($firstTimeRaw === '' || $vv['visit_time'] < $firstTimeRaw)) $firstTimeRaw = $vv['visit_time'];
        }

        $patients = [];
        foreach ($visits as $v) {
            $age = 0;
            if (!empty($v['date_of_birth'])) {
                try { $age = (new DateTime($v['date_of_birth']))->diff(new DateTime('today'))->y; } catch (Throwable $e) {}
            }
            $cs = (string)($v['consultation_status'] ?? '');
            if ($v['status'] === 'completed') {
                $progress = 'completed'; $step = 4; $statusLabel = 'Completed';
            } elseif ((int)$v['lab_pending'] > 0) {
                $progress = 'lab'; $step = 3; $statusLabel = 'Lab pending';
            } elseif ($cs === 'in_consultation' || $cs === 'now_serving' || $v['status'] === 'in_progress') {
                $progress = 'consult'; $step = 3; $statusLabel = 'In consultation';
            } elseif ($cs === 'waiting') {
                $progress = 'waiting'; $step = 2; $statusLabel = 'Waiting';
            } elseif ($cs === 'done') {
                $progress = 'consult'; $step = 3; $statusLabel = 'In progress';
            } else {
                $progress = 'scheduled'; $step = 1; $statusLabel = 'Scheduled';
            }
            $ins = trim((string)($v['insurance_name'] ?? ''));
            $cover = null;
            if ($ins === '') {
                $ins = $v['payment_type'] === 'self_pay' ? 'Cash' : ($v['payment_type'] === 'mixed' ? 'Mixed' : 'Insurance');
            } elseif ($v['copay_pct'] !== null) {
                $cover = max(0, min(100, 100 - (float)$v['copay_pct'])); // package stores the patient's share; cover is the rest
            }
            // Type: Admitted (active bed) > Emergency > IPD > OPD (new visits and follow-ups)
            $typeLabel = (int)$v['is_admitted'] ? 'Admitted'
                : (((int)$v['is_er'] || $v['visit_type'] === 'emergency') ? 'Emergency'
                : ($v['visit_type'] === 'inpatient' ? 'IPD' : 'OPD'));
            $patients[] = [
                'id'              => (int)$v['id'],
                'patient_id'      => (int)$v['patient_id'],
                'name'            => trim($v['first_name'] . ' ' . $v['last_name']),
                'age'             => $age,
                'gender'          => (string)$v['gender'],
                'patient_no'      => (string)$v['patient_no'],
                'phone'           => (string)($v['phone'] ?? ''),
                'registered'      => !empty($v['registered_at']) ? date('d M Y', strtotime($v['registered_at'])) : date('d M Y', strtotime($v['visit_date'])),
                'visit_no'        => (string)$v['visit_no'],
                'visit_type'      => $typeLabel,
                'time'            => !empty($v['visit_time']) ? date('g:i A', strtotime($v['visit_time'])) : '',
                'insurance'       => $ins,
                'cover'           => $cover,
                'progress'        => $progress,
                'step'            => $step,
                'status_label'    => $statusLabel,
                'department_name' => (string)($v['department_name'] ?? ''),
            ];
        }

        // Gender / age split for today's patient activity donut (children under 5 counted separately).
        $gq = $pdo->prepare("
            SELECT
              SUM(CASE WHEN p.gender = 'male'   AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) >= 5 THEN 1 ELSE 0 END) AS male,
              SUM(CASE WHEN p.gender = 'female' AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) >= 5 THEN 1 ELSE 0 END) AS female,
              SUM(CASE WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 5 THEN 1 ELSE 0 END) AS child
            FROM (
                SELECT DISTINCT p.id, p.gender, p.date_of_birth
                FROM visits v JOIN patients p ON p.id = v.patient_id
                WHERE v.visit_date BETWEEN ? AND ? AND v.status <> 'cancelled' AND (? = 0 OR v.department_id = ?)
            ) p
        ");
        $gq->execute([$f, $t, $dept, $dept]);
        $g = $gq->fetch() ?: ['male' => 0, 'female' => 0, 'child' => 0];
        $gender = ['male' => (int)$g['male'], 'female' => (int)$g['female'], 'child' => (int)$g['child']];

        // Top diagnoses in range (doctor donut): top 3 + Other.
        $dxWhere = " FROM consultations c JOIN visits v ON v.id = c.visit_id
            WHERE v.visit_date BETWEEN ? AND ? AND v.status <> 'cancelled'
              AND c.diagnosis IS NOT NULL AND TRIM(c.diagnosis) <> ''
              AND (? = 0 OR v.department_id = ?)";
        $dxq = $pdo->prepare("SELECT TRIM(c.diagnosis) AS label, COUNT(*) AS c" . $dxWhere . " GROUP BY TRIM(c.diagnosis) ORDER BY c DESC LIMIT 3");
        $dxq->execute([$f, $t, $dept, $dept]);
        $dxRows = $dxq->fetchAll();
        $dxTotalQ = $pdo->prepare("SELECT COUNT(*)" . $dxWhere);
        $dxTotalQ->execute([$f, $t, $dept, $dept]);
        $dxTotal = (int)$dxTotalQ->fetchColumn();
        $shorten = function (string $s): string {
            $s = trim(preg_replace('/\s+/', ' ', $s));
            if (function_exists('mb_strlen') && mb_strlen($s) > 28) return mb_substr($s, 0, 27) . '…';
            if (!function_exists('mb_strlen') && strlen($s) > 28) return substr($s, 0, 27) . '…';
            return $s;
        };
        $diagnoses = [];
        $dxSum = 0;
        foreach ($dxRows as $r) {
            $diagnoses[] = ['label' => $shorten((string)$r['label']), 'full' => (string)$r['label'], 'count' => (int)$r['c']];
            $dxSum += (int)$r['c'];
        }
        if ($dxTotal > $dxSum) $diagnoses[] = ['label' => 'Other', 'full' => 'Other diagnoses', 'count' => $dxTotal - $dxSum];

        // Top drugs administered in range (nurse donut): nursing records + medication administrations.
        $drugWhere = " FROM (
                SELECT TRIM(nr.description) AS name, v.department_id
                FROM nursing_records nr LEFT JOIN visits v ON v.id = nr.visit_id
                WHERE nr.record_type IN ('medication','injection') AND DATE(nr.administered_at) BETWEEN ? AND ?
                UNION ALL
                SELECT COALESCE(i.name, 'Medication') AS name, v.department_id
                FROM medication_administrations ma
                LEFT JOIN visits v ON v.id = ma.visit_id
                LEFT JOIN pharmacy_prescription_items pi ON pi.id = ma.prescription_item_id
                LEFT JOIN items i ON i.id = pi.item_id
                WHERE DATE(ma.administered_at) BETWEEN ? AND ?
            ) x WHERE x.name <> '' AND (? = 0 OR x.department_id = ?)";
        $drq = $pdo->prepare("SELECT x.name AS label, COUNT(*) AS c" . $drugWhere . " GROUP BY x.name ORDER BY c DESC LIMIT 3");
        $drq->execute([$f, $t, $f, $t, $dept, $dept]);
        $drRows = $drq->fetchAll();
        $drTotalQ = $pdo->prepare("SELECT COUNT(*)" . $drugWhere);
        $drTotalQ->execute([$f, $t, $f, $t, $dept, $dept]);
        $drTotal = (int)$drTotalQ->fetchColumn();
        $drugs = [];
        $drSum = 0;
        foreach ($drRows as $r) {
            $drugs[] = ['label' => $shorten((string)$r['label']), 'full' => (string)$r['label'], 'count' => (int)$r['c']];
            $drSum += (int)$r['c'];
        }
        if ($drTotal > $drSum) $drugs[] = ['label' => 'Other', 'full' => 'Other drugs', 'count' => $drTotal - $drSum];

        // Escalations: emergency cases in range + currently admitted patients.
        $eq = $pdo->prepare("
            SELECT 'emergency' AS kind, e.patient_id, e.visit_id, e.triage_level, e.created_at AS at,
                   TRIM(CONCAT(p.first_name, ' ', p.last_name)) AS name, p.patient_no, v.visit_no, '' AS ward
            FROM emergency_cases e
            JOIN patients p ON p.id = e.patient_id
            LEFT JOIN visits v ON v.id = e.visit_id
            WHERE DATE(e.created_at) BETWEEN ? AND ? AND (? = 0 OR v.department_id = ?)
            ORDER BY e.created_at DESC LIMIT 6
        ");
        $eq->execute([$f, $t, $dept, $dept]);
        $aq = $pdo->prepare("
            SELECT 'admitted' AS kind, b.patient_id, b.visit_id, '' AS triage_level, b.assigned_at AS at,
                   TRIM(CONCAT(p.first_name, ' ', p.last_name)) AS name, p.patient_no, v.visit_no, COALESCE(w.name, '') AS ward
            FROM bed_assignments b
            JOIN patients p ON p.id = b.patient_id
            LEFT JOIN visits v ON v.id = b.visit_id
            LEFT JOIN wards w ON w.id = b.ward_id
            WHERE b.status = 'active' AND (? = 0 OR v.department_id = ?)
            ORDER BY b.assigned_at DESC LIMIT 6
        ");
        $aq->execute([$dept, $dept]);
        $escRows = array_merge($eq->fetchAll(), $aq->fetchAll());
        usort($escRows, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));
        $escRows = array_slice($escRows, 0, 8);
        $escalations = [];
        foreach ($escRows as $e) {
            $escalations[] = [
                'kind'       => (string)$e['kind'],
                'name'       => (string)$e['name'],
                'patient_no' => (string)$e['patient_no'],
                'visit_no'   => (string)($e['visit_no'] ?? ''),
                'time'       => !empty($e['at']) ? date('g:i A', strtotime($e['at'])) : '',
                'ward'       => (string)($e['ward'] ?? ''),
                'triage'     => (string)($e['triage_level'] ?? ''),
                'visit_id'   => (int)($e['visit_id'] ?? 0),
                'patient_id' => (int)($e['patient_id'] ?? 0),
            ];
        }

        success([
            'counts'          => $counts,
            'my_department'   => ['id' => $myDeptId, 'name' => $myDeptName],
            'departments'     => $departments,
            'patients'        => $patients,
            'first_time'      => $firstTimeRaw !== '' ? date('g:i A', strtotime($firstTimeRaw)) : '',
            'gender'          => $gender,
            'diagnoses'       => $diagnoses,
            'diagnoses_total' => $dxTotal,
            'drugs'           => $drugs,
            'drugs_total'     => $drTotal,
            'escalations'     => $escalations,
        ]);
    }

    // Consultation tabs: everything for one patient across visits (follow-ups included)
    if (in_array($action, ['patient_results', 'patient_meds', 'patient_invoicing', 'patient_visits', 'patient_tabs_sig', 'patient_nurse_links'], true)) {
        $pid = (int)($_GET['patient_id'] ?? 0);
        if (!$pid) error('patient_id required', 422);
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $nm = "COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username)";

        // Previous visits table: no billing fields. Doctors + departments come from every consultation on the visit.
        if ($action === 'patient_visits') {
            $q = $pdo->prepare("SELECT v.id AS visit_id, v.visit_no, v.visit_date, v.visit_type, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.phone,
                    COALESCE(ic.name, ic2.name) AS insurance,
                    (SELECT c.diagnosis FROM consultations c WHERE c.visit_id = v.id ORDER BY c.id DESC LIMIT 1) AS diagnosis,
                    (SELECT c.treatment_plan FROM consultations c WHERE c.visit_id = v.id ORDER BY c.id DESC LIMIT 1) AS doctor_notes,
                    (SELECT d.name FROM departments d WHERE d.id = v.department_id) AS department_name, v.doctor_name
                FROM visits v JOIN patients p ON p.id = v.patient_id
                LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
                LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
                LEFT JOIN insurance_companies ic2 ON ic2.id = v.insurance_id AND pi.id IS NULL
                WHERE v.patient_id = ? AND v.id <> ? AND v.status <> 'cancelled' ORDER BY v.visit_date DESC, v.id DESC LIMIT 200");
            $q->execute([$pid, (int)($_GET['visit_id'] ?? 0)]);
            $rows = $q->fetchAll();
            $dq = $pdo->prepare("SELECT DISTINCT COALESCE(NULLIF(c.doctor_name,''), CONCAT(u.first_name,' ',u.last_name)) AS doctor, d.name AS department
                FROM consultations c LEFT JOIN users u ON u.id = c.doctor_id LEFT JOIN departments d ON d.id = c.department_id WHERE c.visit_id = ?");
            foreach ($rows as &$r) {
                $dq->execute([$r['visit_id']]);
                $r['doctors'] = array_values(array_filter($dq->fetchAll(), fn($x) => trim((string)$x['doctor']) !== ''));
                if (!$r['doctors'] && trim((string)$r['doctor_name']) !== '') $r['doctors'] = [['doctor' => $r['doctor_name'], 'department' => $r['department_name']]];
            }
            unset($r);
            success($rows);
        }

        // Cheap change-detector the page polls instead of reloading every tab: 3 aggregate queries on indexed patient_id columns, one hash back.
        if ($action === 'patient_tabs_sig') {
            $one = function (string $sql) use ($pdo, $pid) { $q = $pdo->prepare($sql); $q->execute([$pid]); return implode('|', $q->fetch(PDO::FETCH_NUM)); };
            success(['sig' => md5(
                $one("SELECT COUNT(*), COALESCE(SUM(paid_amount),0), COALESCE(SUM(balance),0), MAX(updated_at), COALESCE(SUM(approved_at IS NOT NULL),0) FROM invoices WHERE patient_id = ? AND status <> 'cancelled'") . '#' .
                $one("SELECT COUNT(*), COALESCE(SUM(result IS NOT NULL),0), COALESCE(SUM(status='completed'),0), MAX(COALESCE(completed_at, ordered_at)) FROM medical_orders WHERE patient_id = ? AND order_type IN ('lab','radiology')") . '#' .
                $one("SELECT COUNT(*), COALESCE(SUM(pi.status='pending'),0), COALESCE(SUM(pi.status='dispensed'),0) FROM pharmacy_prescription_items pi JOIN pharmacy_prescriptions p ON p.id = pi.prescription_id WHERE p.patient_id = ?")
            )]);
        }

        if ($action === 'patient_results') {
            $q = $pdo->prepare("SELECT mo.id, mo.visit_id, v.visit_no, mo.order_type, mo.description, mo.status, mo.result, mo.ordered_at, mo.completed_at, $nm AS ordered_by_name
                FROM medical_orders mo JOIN visits v ON v.id = mo.visit_id LEFT JOIN users u ON u.id = mo.ordered_by
                WHERE mo.patient_id = ? AND mo.order_type IN ('lab','radiology') AND mo.status <> 'cancelled' ORDER BY mo.ordered_at DESC LIMIT 300");
            $q->execute([$pid]);
            success($q->fetchAll());
        }

        // Procedures / prescriptions / samples a nurse recorded against a doctor for this patient
        if ($action === 'patient_nurse_links') {
            try {
                $q = $pdo->prepare("SELECT l.id, l.kind, l.summary, l.created_at, l.doctor_id, v.visit_no,
                        CONCAT(d.first_name,' ',d.last_name) AS doctor_name, CONCAT(n.first_name,' ',n.last_name) AS nurse_name
                    FROM nurse_doctor_links l
                    LEFT JOIN visits v ON v.id = l.visit_id
                    LEFT JOIN users d ON d.id = l.doctor_id
                    LEFT JOIN users n ON n.id = l.nurse_id
                    WHERE l.patient_id = ? ORDER BY l.created_at DESC LIMIT 300");
                $q->execute([$pid]);
                $rows = $q->fetchAll();
            } catch (PDOException $e) { $rows = []; }   // table is created on the first nurse entry
            foreach ($rows as &$r) $r['mine'] = ((int)$r['doctor_id'] === $uid);
            success($rows);
        }

        if ($action === 'patient_meds') {
            $g = $pdo->prepare("SELECT x.* FROM (
                    SELECT nr.visit_id, nr.description AS medication, nr.dosage, nr.quantity, nr.administered_at AS given_at, $nm AS given_by
                      FROM nursing_records nr LEFT JOIN users u ON u.id = nr.administered_by
                     WHERE nr.patient_id = ? AND nr.record_type IN ('medication','injection')
                    UNION ALL
                    SELECT ma.visit_id, COALESCE(i.name,'Medication'), CONCAT_WS(' ', ma.dose_value, ma.dose_unit), NULL, ma.administered_at, $nm
                      FROM medication_administrations ma
                      LEFT JOIN pharmacy_prescription_items pi ON pi.id = ma.prescription_item_id LEFT JOIN items i ON i.id = pi.item_id
                      LEFT JOIN users u ON u.id = ma.administered_by
                     WHERE ma.patient_id = ?
                ) x ORDER BY x.given_at DESC LIMIT 300");
            $g->execute([$pid, $pid]);
            $h = $pdo->prepare("SELECT x.* FROM (
                    SELECT p.visit_id, p.created_at, COALESCE(i.name, pi.instructions, 'Medication') AS medication, pi.dosage, pi.frequency, pi.duration, pi.quantity
                      FROM pharmacy_prescription_items pi JOIN pharmacy_prescriptions p ON p.id = pi.prescription_id LEFT JOIN items i ON i.id = pi.item_id
                     WHERE p.patient_id = ? AND pi.destination = 'external' AND pi.status <> 'cancelled'
                    UNION ALL
                    SELECT e.visit_id, e.created_at, ei.medication_name, ei.dosage, ei.frequency, ei.duration, ei.quantity
                      FROM external_prescription_items ei JOIN external_prescriptions e ON e.id = ei.prescription_id
                     WHERE e.patient_id = ? AND e.status = 'active'
                ) x ORDER BY x.created_at DESC LIMIT 300");
            $h->execute([$pid, $pid]);
            $pq = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_prescription_items pi JOIN pharmacy_prescriptions p ON p.id = pi.prescription_id
                WHERE p.patient_id = ? AND pi.status = 'pending' AND p.status IN ('active','dispensed_partial')");
            $pq->execute([$pid]);
            success(['given' => $g->fetchAll(), 'take_home' => $h->fetchAll(), 'pending' => (int)$pq->fetchColumn()]);
        }

        // patient_invoicing (OPD tab: visits never admitted; Hospitalization lives in hosp-invoicing.php): bills raised by the logged-in doctor (admin sees all). A follow-up is a visit with its own doctor_id, so it is covered.
        $rq = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug IN ('admin','administrator') LIMIT 1");
        $rq->execute([$uid]);
        $isAdm = (bool)$rq->fetchColumn();
        $mine = $isAdm ? '1=1' : "(i.created_by = :u1 OR v.doctor_id = :u2 OR EXISTS (SELECT 1 FROM invoice_items x WHERE x.invoice_id = i.id AND (x.ordered_by = :u3 OR x.added_by = :u4)))";
        $q = $pdo->prepare("SELECT i.id, i.invoice_no, i.visit_id, v.visit_no, v.visit_type, i.total, i.paid_amount, i.balance, i.payment_type, i.approved_at, i.created_at
            FROM invoices i JOIN visits v ON v.id = i.visit_id
            WHERE i.patient_id = :p AND i.status <> 'cancelled' AND $mine AND NOT EXISTS (SELECT 1 FROM bed_assignments b WHERE b.visit_id = i.visit_id) ORDER BY i.created_at DESC LIMIT 200");
        $params = [':p' => $pid];
        if (!$isAdm) $params += [':u1' => $uid, ':u2' => $uid, ':u3' => $uid, ':u4' => $uid];
        $q->execute($params);
        $rows = $q->fetchAll();
        $it = $pdo->prepare("SELECT description, category, quantity, total FROM invoice_items WHERE invoice_id = ?");
        foreach ($rows as &$r) {
            $bal = (float)$r['balance']; $paid = (float)$r['paid_amount'];
            $r['pay_status'] = $bal <= 0 ? ($paid == 0 && $r['payment_type'] === 'insurance' ? 'covered' : 'paid')
                : (empty($r['approved_at']) ? ($paid > 0 ? 'partial' : 'unpaid') : 'approved');
            $it->execute([$r['id']]);
            $r['items'] = $it->fetchAll();
            if (!$isAdm) {   // prices stay admin-only, like the rest of the consultation page
                foreach (['total', 'paid_amount', 'balance'] as $k) unset($r[$k]);
                foreach ($r['items'] as &$li) unset($li['total']);
                unset($li);
            }
        }
        unset($r);
        success($rows);
    }

    // Get prescriptions for a visit (emergency drug prescriptions)
    if ($action === 'prescriptions') {
        $vId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        if (!$vId) error('visit_id required', 422);
        $stmt = $pdo->prepare("
            SELECT pp.*,
                   COALESCE(CONCAT(d.first_name,' ',d.last_name), d.username) AS prescribed_by_name,
                   COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS created_by_name
            FROM pharmacy_prescriptions pp
            LEFT JOIN users d  ON d.id  = pp.doctor_id
            LEFT JOIN users cb ON cb.id = pp.created_by
            WHERE pp.visit_id = ? ORDER BY pp.created_at DESC");
        $stmt->execute([$vId]);
        $prescriptions = $stmt->fetchAll();
        foreach ($prescriptions as &$rx) {
            $iStmt = $pdo->prepare("SELECT pi.*, i.name AS item_name, i.item_code FROM pharmacy_prescription_items pi LEFT JOIN items i ON i.id = pi.item_id WHERE pi.prescription_id = ?");
            $iStmt->execute([$rx['id']]);
            $rx['items'] = $iStmt->fetchAll();
        }
        unset($rx);
        success($prescriptions);
    }

    // Get completed consultations for a department
    if ($action === 'completed_list') {
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $date = $_GET['date'] ?? '';
        if (!$deptId) error('department_id required', 422);
        $where = ["c.department_id = ?", "c.status = 'completed'"];
        $params = [$deptId];
        if ($date) {
            $where[] = "DATE(c.created_at) = ?";
            $params[] = $date;
        } else {
            // Default to today
            $where[] = "c.created_at >= CURDATE() AND c.created_at < CURDATE() + INTERVAL 1 DAY";
        }
        $ws = 'WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare("
            SELECT c.id, c.visit_id, c.patient_id, c.diagnosis, c.created_at, c.status,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no,
                   v.visit_no, v.payment_type, v.insurance_id,
                   COALESCE(ic.name, ic2.name) AS company_name,
                   COALESCE(ip.name, ip2.name) AS package_name,
                   COALESCE(ip.copayment_percentage, ip2.copayment_percentage) AS copayment_percentage
            FROM consultations c
            JOIN patients p ON p.id = c.patient_id
            JOIN visits v ON v.id = c.visit_id
            LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            LEFT JOIN insurance_companies ic2 ON ic2.id = v.insurance_id AND pi.id IS NULL
            LEFT JOIN patient_insurance pi2 ON pi2.patient_id = v.patient_id AND pi2.is_primary = 1 AND pi.id IS NULL
            LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
            $ws
            ORDER BY c.created_at DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // Get referrals made by a specific doctor
    if ($action === 'my_referrals') {
        $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
        if (!$doctorId) error('doctor_id required', 422);
        $stmt = $pdo->prepare("
            SELECT c.id, c.visit_id, c.patient_id, c.diagnosis, c.created_at, c.outcome,
                   c.referred_to_dept,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no,
                   v.visit_no, v.payment_type, v.insurance_id,
                   d.name AS referred_dept_name,
                   pq.status AS queue_status,
                   COALESCE(ic.name, ic2.name) AS company_name,
                   COALESCE(ip.name, ip2.name) AS package_name,
                   COALESCE(ip.copayment_percentage, ip2.copayment_percentage) AS copayment_percentage
            FROM consultations c
            JOIN patients p ON p.id = c.patient_id
            JOIN visits v ON v.id = c.visit_id
            LEFT JOIN departments d ON d.id = c.referred_to_dept
            LEFT JOIN patient_queue pq ON pq.visit_id = c.visit_id AND pq.department_id = c.referred_to_dept
            LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            LEFT JOIN insurance_companies ic2 ON ic2.id = v.insurance_id AND pi.id IS NULL
            LEFT JOIN patient_insurance pi2 ON pi2.patient_id = v.patient_id AND pi2.is_primary = 1 AND pi.id IS NULL
            LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
            WHERE c.doctor_id = ? AND c.outcome = 'referred'
            ORDER BY c.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$doctorId]);
        success($stmt->fetchAll());
    }

    // Get consultation record for a visit
    if ($action === 'consultation') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        if (!$visitId) error('visit_id required', 422);
        $stmt = $pdo->prepare("
            SELECT c.*,
                   d.name AS department_name,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS created_by_name,
                   COALESCE(CONCAT(u1.first_name,' ',u1.last_name), u1.username) AS step1_by_name,
                   COALESCE(CONCAT(u3.first_name,' ',u3.last_name), u3.username) AS step3_by_name,
                   COALESCE(CONCAT(uc.first_name,' ',uc.last_name), uc.username) AS completed_by_name
            FROM consultations c
            LEFT JOIN departments d ON d.id = c.department_id
            LEFT JOIN users u  ON u.id  = c.created_by
            LEFT JOIN users u1 ON u1.id = c.step1_by
            LEFT JOIN users u3 ON u3.id = c.step3_by
            LEFT JOIN users uc ON uc.id = c.completed_by
            WHERE c.visit_id = ?
            ORDER BY c.id DESC
        ");
        $stmt->execute([$visitId]);
        $results = $stmt->fetchAll();
        success($results);
    }

    // Consultation timeline: every user who touched the file (audit)
    if ($action === 'events') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        if (!$visitId) error('visit_id required', 422);
        $stmt = $pdo->prepare("SELECT id, event, detail, user_id, user_name, is_handover, created_at FROM consultation_events WHERE visit_id = ? ORDER BY id ASC");
        $stmt->execute([$visitId]);
        success($stmt->fetchAll());
    }

    // Get lab results for a visit (from medical_orders)
    if ($action === 'lab_results') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        if (!$visitId) error('visit_id required', 422);
        $stmt = $pdo->prepare("
            SELECT mo.id, mo.description AS test_name, mo.order_type, mo.status, mo.result, mo.completed_at, mo.ordered_at,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS ordered_by_name,
                   COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS completed_by_name
            FROM medical_orders mo
            LEFT JOIN users u ON u.id = mo.ordered_by
            LEFT JOIN users cb ON cb.id = mo.completed_by
            WHERE mo.visit_id = ? AND mo.order_type = 'lab'
            ORDER BY mo.id ASC
        ");
        $stmt->execute([$visitId]);
        success($stmt->fetchAll());
    }

    // Stock note for the prescribing picker (warning only)
    if ($action === 'stock_warning') {
        $itemId = (int)($_GET['item_id'] ?? 0);
        if (!$itemId) error('item_id required', 422);
        $nq = $pdo->prepare("SELECT name FROM items WHERE id=?"); $nq->execute([$itemId]);
        success(['warning' => stock_warning($pdo, $itemId, max(1, (float)($_GET['qty'] ?? 1)), (string)$nq->fetchColumn())]);
    }

    // Repeated-illness alert: how often this patient had this diagnosis inside the window (settings diag_recurrence_threshold / _window_days)
    if ($action === 'diagnosis_recurrence') {
        $pid = (int)($_GET['patient_id'] ?? 0);
        $diag = trim($_GET['diagnosis'] ?? '');
        if (!$pid || $diag === '') error('patient_id and diagnosis required', 422);
        success(recurrence_count($pdo, $pid, $diag, (int)($_GET['visit_id'] ?? 0)));
    }

    // Get imaging results for a visit or single order
    if ($action === 'imaging_results' || $action === 'imaging_result') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        if ($orderId) {
            $stmt = $pdo->prepare("
                SELECT mo.*,
                       COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS ordered_by_name,
                       COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS completed_by_name
                FROM medical_orders mo
                LEFT JOIN users u ON u.id = mo.ordered_by
                LEFT JOIN users cb ON cb.id = mo.completed_by
                WHERE mo.id = ?
            ");
            $stmt->execute([$orderId]);
            $row = $stmt->fetch();
            if ($row) success($row);
            else error('Order not found', 404);
        }
        if (!$visitId) error('visit_id required', 422);
        $stmt = $pdo->prepare("
            SELECT mo.id, mo.description AS imaging_name, mo.order_type, mo.status, mo.result, mo.details, mo.completed_at, mo.ordered_at,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS ordered_by_name,
                   COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS completed_by_name
            FROM medical_orders mo
            LEFT JOIN users u ON u.id = mo.ordered_by
            LEFT JOIN users cb ON cb.id = mo.completed_by
            WHERE mo.visit_id = ? AND mo.order_type = 'radiology'
            ORDER BY mo.id ASC
        ");
        $stmt->execute([$visitId]);
        success($stmt->fetchAll());
    }

    // Get medical orders for a visit or single order
    if ($action === 'orders') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        if ($orderId) {
            $stmt = $pdo->prepare("SELECT mo.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM medical_orders mo LEFT JOIN patients p ON p.id = mo.patient_id WHERE mo.id = ?");
            $stmt->execute([$orderId]);
            success($stmt->fetchAll());
        }
        if (!$visitId) error('visit_id required', 422);
        $stmt = $pdo->prepare("SELECT mo.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM medical_orders mo LEFT JOIN patients p ON p.id = mo.patient_id WHERE mo.visit_id = ? ORDER BY mo.id DESC");
        $stmt->execute([$visitId]);
        success($stmt->fetchAll());
    }

    // Get all lab/radiology orders for lab.php
    if ($action === 'lab_orders') {
        $status = $_GET['status'] ?? '';
        $type = $_GET['type'] ?? 'lab';
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $limit = isset($_GET['limit']) ? min(1000, max(1, (int)$_GET['limit'])) : 500;

        $where = [];
        $params = [];
        if ($type && $type !== 'all') {
            $where[] = "mo.order_type = ?";
            $params[] = $type;
        } else {
            $where[] = "mo.order_type = 'lab'";
        }
        if ($status) { $where[] = "mo.status = ?"; $params[] = $status; }
        if ($visitId) { $where[] = "mo.visit_id = ?"; $params[] = $visitId; }
        if ($patientId) { $where[] = "mo.patient_id = ?"; $params[] = $patientId; }
        if ($dateFrom) {
            $where[] = "COALESCE(DATE(mo.ordered_at), v.visit_date) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = "COALESCE(DATE(mo.ordered_at), v.visit_date) <= ?";
            $params[] = $dateTo;
        }
        if ($search) {
            $cleanSearch = ltrim($search, '#');
            $like = "%$search%";
            $cleanLike = "%$cleanSearch%";
            $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR CONCAT(p.last_name, ' ', p.first_name) LIKE ? OR p.patient_no LIKE ? OR mo.description LIKE ? OR mo.details LIKE ? OR v.visit_no LIKE ? OR mo.result LIKE ? OR ic.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR mo.id = ?)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $cleanLike;
            $params[] = $like;
            $params[] = $like;
            $params[] = $cleanLike;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = is_numeric($cleanSearch) ? (int)$cleanSearch : 0;
        }
        $ws = 'WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare("
            SELECT mo.*, 
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                   p.patient_no, p.gender, p.date_of_birth,
                   ic.name AS insurance_company,
                   v.visit_no, v.visit_date,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS ordered_by_name,
                   COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS completed_by_name,
                   " . payStatusSql('mo.visit_id', "ii.category IN ('lab','laboratory','radiology','imaging')
                       AND (ii.id = mo.invoice_item_id OR (mo.invoice_item_id IS NULL AND (ii.service_id = mo.service_id OR ii.description = mo.description OR ii.description LIKE CONCAT('% — ', mo.description, '%'))))") . "
            FROM medical_orders mo
            LEFT JOIN patients p ON p.id = mo.patient_id
            LEFT JOIN visits v ON v.id = mo.visit_id
            LEFT JOIN patient_insurance pi ON pi.id = (SELECT pi9.id FROM patient_insurance pi9 WHERE pi9.patient_id = p.id ORDER BY (pi9.id = v.insurance_id) DESC, (pi9.company_id = v.insurance_id) DESC, pi9.is_primary DESC, pi9.id DESC LIMIT 1)
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN users u ON u.id = mo.ordered_by
            LEFT JOIN users cb ON cb.id = mo.completed_by
            $ws
            ORDER BY mo.ordered_at DESC
            LIMIT $limit
        ");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // --- EMERGENCY CASES ---
    // ===== LAB CONSUMPTION ENDPOINTS =====
    // Get lab orders with mapped consumables and consumption status
    if ($action === 'lab_consumption_orders') {
        $statusFilter = $_GET['consumption_status'] ?? 'all'; // all, pending, consumed
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';
        $search   = trim($_GET['search'] ?? '');
        $storeId  = (int)($_GET['store_id'] ?? 0) ?: lab_store_id($pdo); // default: Laboratory department store

        $where = ["mo.order_type IN ('lab','radiology')"];
        $params = [];

        if ($dateFrom) { $where[] = "(v.visit_date >= ? OR DATE(mo.ordered_at) >= ?)"; $params[] = $dateFrom; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = "(v.visit_date <= ? OR DATE(mo.ordered_at) <= ?)"; $params[] = $dateTo;   $params[] = $dateTo; }
        if ($search) {
            $like = "%$search%";
            $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR mo.description LIKE ? OR v.visit_no LIKE ? OR CAST(mo.id AS CHAR) LIKE ?)";
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        }

        $ws = 'WHERE ' . implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT mo.*,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                   p.patient_no, p.gender, p.date_of_birth,
                   ic.name AS insurance_company,
                   v.visit_no, v.visit_date, v.status AS visit_status,
                   TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS ordered_by_name
            FROM medical_orders mo
            LEFT JOIN patients p ON p.id = mo.patient_id
            LEFT JOIN visits v ON v.id = mo.visit_id
            LEFT JOIN patient_insurance pi ON pi.id = (SELECT pi9.id FROM patient_insurance pi9 WHERE pi9.patient_id = p.id ORDER BY (pi9.id = v.insurance_id) DESC, (pi9.company_id = v.insurance_id) DESC, pi9.is_primary DESC, pi9.id DESC LIMIT 1)
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN users u ON u.id = mo.ordered_by
            $ws
            ORDER BY mo.ordered_at DESC, mo.id DESC
            LIMIT 300
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Load consumption records and mapped consumables for these orders
        $orderIds = array_map(function($o) { return (int)$o['id']; }, $orders);
        $consumptionsMap = [];
        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $cStmt = $pdo->prepare("
                SELECT lic.*, i.name AS item_name, i.item_code, iu.abbreviation AS uom_abbr,
                       s.name AS store_name,
                       TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS consumed_by_name
                FROM lab_item_consumption lic
                JOIN items i ON i.id = lic.item_id
                LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                LEFT JOIN stores s ON s.id = lic.store_id
                LEFT JOIN users u ON u.id = lic.consumed_by
                WHERE lic.order_id IN ($placeholders) AND lic.status = 'confirmed'
                ORDER BY lic.consumed_at ASC
            ");
            $cStmt->execute($orderIds);
            while ($row = $cStmt->fetch()) {
                $consumptionsMap[$row['order_id']][] = $row;
            }
        }

        // Cache service catalog item mappings
        $serviceMappings = [];
        $scAll = $pdo->query("
            SELECT sci.service_id, sci.item_id, sci.default_quantity, sci.notes,
                   sc.procedure_name, sc.speciality,
                   i.name AS item_name, i.item_code, ic.name AS category_name, iu.abbreviation AS uom_abbr,
                   COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND is_active = 1 AND quantity > 0), 0) AS total_stock,
                   COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND store_id = $storeId AND is_active = 1 AND quantity > 0), 0) AS store_stock
            FROM service_catalog_items sci
            JOIN service_catalog sc ON sc.id = sci.service_id
            JOIN items i ON i.id = sci.item_id
            LEFT JOIN item_categories ic ON ic.id = i.category_id
            LEFT JOIN item_uoms iu ON iu.id = i.uom_id
            ORDER BY sc.id, i.name
        ")->fetchAll();

        foreach ($scAll as $row) {
            $key = strtolower(trim($row['procedure_name']));
            $serviceMappings[$key][] = $row;
            $serviceMappings['sid_' . $row['service_id']][] = $row;
        }

        $resultOrders = [];
        foreach ($orders as $ord) {
            $consumedItems = $consumptionsMap[$ord['id']] ?? [];
            $isConsumed = !empty($consumedItems);

            // Find linked consumables by service_id or test description match
            $linkedConsumables = [];
            if (!empty($ord['service_id']) && !empty($serviceMappings['sid_' . $ord['service_id']])) {
                $linkedConsumables = $serviceMappings['sid_' . $ord['service_id']];
            } else {
                $testKey = strtolower(trim($ord['description'] ?? ''));
                $linkedConsumables = $serviceMappings[$testKey] ?? [];

                // If no exact match, try partial match
                if (empty($linkedConsumables)) {
                    foreach ($serviceMappings as $nameKey => $itemsList) {
                        if (strpos($nameKey, 'sid_') === 0) continue;
                        if (strpos($nameKey, $testKey) !== false || strpos($testKey, $nameKey) !== false) {
                            $linkedConsumables = $itemsList;
                            break;
                        }
                    }
                }
            }

            $ord['is_consumed'] = $isConsumed ? 1 : 0;
            $ord['consumed_items'] = $consumedItems;
            $ord['consumed_items_count'] = count($consumedItems);
            $ord['consumed_at'] = $isConsumed ? $consumedItems[0]['consumed_at'] : null;
            $ord['consumed_by_name'] = $isConsumed ? $consumedItems[0]['consumed_by_name'] : null;
            $ord['linked_consumables'] = $linkedConsumables;
            $ord['linked_consumables_count'] = count($linkedConsumables);

            // Filter by consumption status
            if ($statusFilter === 'pending' && $isConsumed) continue;
            if ($statusFilter === 'consumed' && !$isConsumed) continue;

            $resultOrders[] = $ord;
        }

        success($resultOrders);
    }

    // Get consumables details for a specific lab order or test
    if ($action === 'lab_order_consumables') {
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        $serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
        $storeId = (int)($_GET['store_id'] ?? 0) ?: lab_store_id($pdo); // default: Laboratory department store
        $testName = trim($_GET['test_name'] ?? '');

        $order = null;
        if ($orderId > 0) {
            $oStmt = $pdo->prepare("
                SELECT mo.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no,
                       v.visit_no, v.visit_date
                FROM medical_orders mo
                LEFT JOIN patients p ON p.id = mo.patient_id
                LEFT JOIN visits v ON v.id = mo.visit_id
                WHERE mo.id = ?
            ");
            $oStmt->execute([$orderId]);
            $order = $oStmt->fetch();
            if ($order && !$testName) $testName = $order['description'];
        }

        // Find service catalog entry if not explicitly provided
        $service = null;
        if ($serviceId > 0) {
            $sStmt = $pdo->prepare("SELECT * FROM service_catalog WHERE id = ?");
            $sStmt->execute([$serviceId]);
            $service = $sStmt->fetch();
        } elseif ($testName) {
            $sStmt = $pdo->prepare("SELECT * FROM service_catalog WHERE procedure_name = ? OR procedure_name LIKE ? ORDER BY LENGTH(procedure_name) ASC LIMIT 1");
            $sStmt->execute([$testName, "%$testName%"]);
            $service = $sStmt->fetch();
            if ($service) $serviceId = (int)$service['id'];
        }

        // Get linked items from service_catalog_items
        $linkedItems = [];
        if ($serviceId > 0) {
            $liStmt = $pdo->prepare("
                SELECT sci.*, 
                       i.name AS item_name, i.item_code, i.generic_name, i.selling_price, i.unit_price,
                       ic.name AS category_name, iu.name AS uom_name, iu.abbreviation AS uom_abbr,
                       COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND is_active = 1 AND quantity > 0), 0) AS total_stock,
                       COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND store_id = ? AND is_active = 1 AND quantity > 0), 0) AS store_stock
                FROM service_catalog_items sci
                JOIN items i ON i.id = sci.item_id
                LEFT JOIN item_categories ic ON ic.id = i.category_id
                LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                WHERE sci.service_id = ?
                ORDER BY i.name ASC
            ");
            $liStmt->execute([$storeId, $serviceId]);
            $linkedItems = $liStmt->fetchAll();
        }

        // Check if already consumed for this order
        $consumedRecords = [];
        if ($orderId > 0) {
            $crStmt = $pdo->prepare("
                SELECT lic.*, i.name AS item_name, i.item_code, iu.abbreviation AS uom_abbr,
                       s.name AS store_name,
                       TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS consumed_by_name
                FROM lab_item_consumption lic
                JOIN items i ON i.id = lic.item_id
                LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                LEFT JOIN stores s ON s.id = lic.store_id
                LEFT JOIN users u ON u.id = lic.consumed_by
                WHERE lic.order_id = ? AND lic.status = 'confirmed'
                ORDER BY lic.consumed_at ASC
            ");
            $crStmt->execute([$orderId]);
            $consumedRecords = $crStmt->fetchAll();
        }

        success([
            'order' => $order,
            'service' => $service,
            'linked_items' => $linkedItems,
            'is_consumed' => !empty($consumedRecords),
            'consumed_records' => $consumedRecords,
            'store_id' => $storeId
        ]);
    }

    // Get consumption history log
    if ($action === 'lab_consumption_history') {
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $stmt = $pdo->query("
            SELECT lic.*, 
                   i.name AS item_name, i.item_code, iu.abbreviation AS uom_abbr,
                   s.name AS store_name, s.code AS store_code,
                   mo.description AS test_name,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_no,
                   v.visit_no,
                   TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS consumed_by_name
            FROM lab_item_consumption lic
            JOIN items i ON i.id = lic.item_id
            LEFT JOIN item_uoms iu ON iu.id = i.uom_id
            LEFT JOIN stores s ON s.id = lic.store_id
            LEFT JOIN medical_orders mo ON mo.id = lic.order_id
            LEFT JOIN patients p ON p.id = lic.patient_id
            LEFT JOIN visits v ON v.id = lic.visit_id
            LEFT JOIN users u ON u.id = lic.consumed_by
            ORDER BY lic.consumed_at DESC, lic.id DESC
            LIMIT $limit
        ");
        success($stmt->fetchAll());
    }

    // Stores the lab draws consumables from: the Laboratory department store first, then Microbiology
    if ($action === 'lab_stores') {
        $labId = lab_store_id($pdo);
        $stmt = $pdo->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM stock_batches sb WHERE sb.store_id = s.id AND sb.is_active = 1 AND sb.quantity > 0) AS active_batches_count
            FROM stores s
            WHERE s.is_active = 1 AND (s.id = ? OR s.code = 'DEPT-37')
            ORDER BY (s.id = ?) DESC, s.name ASC
        ");
        $stmt->execute([$labId, $labId]);
        success($stmt->fetchAll());
    }

    if ($action === 'emergency') {
        $status = $_GET['status'] ?? '';
        $subAction = $_GET['sub_action'] ?? '';
        
        // Get treatments for a case
        // Staff who can close a case; role is doctor/nurse when assigned (UI falls back to everyone if a role has nobody)
        if ($subAction === 'staff') {
            $stmt = $pdo->query("SELECT u.id, CONCAT(u.first_name,' ',u.last_name) AS name, MAX(CASE WHEN r.slug IN ('doctor','nurse') THEN r.slug END) AS role FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id WHERE u.is_active=1 GROUP BY u.id ORDER BY name");
            success(['staff' => $stmt->fetchAll(), 'me' => (int)($_SESSION['user_id'] ?? 0)]);
        }

        if ($subAction === 'treatments') {
            $caseId = (int)($_GET['case_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM emergency_treatments WHERE case_id=? ORDER BY administered_at DESC");
            $stmt->execute([$caseId]);
            success($stmt->fetchAll());
        }
        
        $where = []; $params = [];
        if ($status === 'active') { $where[] = "e.status IN ('triage','treatment','observation')"; }
        else if ($status) { $where[] = "e.status=?"; $params[] = $status; }
        $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $stmt = $pdo->prepare("SELECT e.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no FROM emergency_cases e LEFT JOIN patients p ON p.id=e.patient_id $ws ORDER BY FIELD(e.triage_level,'red','orange','yellow','green','blue'), e.created_at DESC LIMIT 50");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // --- SURGERIES ---
    if ($action === 'surgery') {
        $status = $_GET['status'] ?? '';
        $where = []; $params = [];
        if ($status) { $where[] = "s.status=?"; $params[] = $status; }
        $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $stmt = $pdo->prepare("SELECT s.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no, " . payStatusSql('s.visit_id', "ii.category = 'procedure' AND (ii.service_id = s.service_id OR ii.description = s.procedure_name)") . " FROM surgeries s LEFT JOIN patients p ON p.id=s.patient_id $ws ORDER BY s.scheduled_date DESC, s.scheduled_time DESC LIMIT 50");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // --- DOSAGE: doctor's prescribed doses for a visit, and the nurse-deviation alerts waiting for a doctor ---
    if ($action === 'prescribed_dosage') {
        $q = $pdo->prepare("SELECT * FROM prescribed_dosage WHERE visit_id = ? ORDER BY active DESC, id DESC");
        $q->execute([(int)($_GET['visit_id'] ?? 0)]);
        success($q->fetchAll());
    }
    // Patients whose same diagnosis (ICD code when present) was treated threshold+ times in the window and who were not referred / justified since
    if ($action === 'repeat_visit_alerts') {
        consult_ensure_schema($pdo);
        $thr = recurrence_setting($pdo, 'diag_recurrence_threshold', 4);
        $win = recurrence_setting($pdo, 'diag_recurrence_window_days', 30);
        $q = $pdo->prepare("SELECT c.patient_id, CONCAT(p.first_name,' ',p.last_name) AS patient_name, COALESCE(NULLIF(TRIM(c.icd_code),''), TRIM(c.diagnosis)) AS diagnosis, COUNT(*) AS visits
            FROM consultations c JOIN patients p ON p.id = c.patient_id
            WHERE c.status = 'completed' AND c.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND COALESCE(NULLIF(TRIM(c.icd_code),''), TRIM(c.diagnosis)) <> ''
              AND NOT EXISTS (SELECT 1 FROM consultations r WHERE r.patient_id = c.patient_id AND r.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) AND r.referred_to_dept IS NOT NULL AND r.referred_to_dept <> '')
              AND NOT EXISTS (SELECT 1 FROM diagnosis_recurrence_flags f WHERE f.patient_id = c.patient_id AND f.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY))
            GROUP BY c.patient_id, diagnosis HAVING COUNT(*) >= ? ORDER BY visits DESC LIMIT 50");
        $q->execute([$win, $win, $win, $thr]);
        success($q->fetchAll());
    }
    if ($action === 'dosage_alerts') {
        $q = $pdo->query("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS nurse_name
            FROM dosage_alerts a JOIN patients p ON p.id = a.patient_id LEFT JOIN users u ON u.id = a.nurse_id
            WHERE a.acknowledged_at IS NULL ORDER BY a.id DESC LIMIT 50");
        success($q->fetchAll());
    }

    // --- NURSING RECORDS ---
    if ($action === 'nursing_records') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        $where = [];
        $params = [];
        if ($visitId) { $where[] = "nr.visit_id = ?"; $params[] = $visitId; }
        if ($dateFrom) { $where[] = "nr.created_at >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $where[] = "nr.created_at < DATE_ADD(?, INTERVAL 1 DAY)"; $params[] = $dateTo; }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $pdo->prepare("SELECT nr.*, 
            CONCAT(p.first_name,' ',p.last_name) AS patient_name, 
            p.patient_no,
            v.visit_no,
            CONCAT(u.first_name,' ',u.last_name) AS nurse_name,
            CONCAT(u.first_name,' ',u.last_name) AS created_by_name,
            u.role AS entered_by_role,
            " . payStatusSql('nr.visit_id', "ii.invoice_id = nr.invoice_id") . "
            FROM nursing_records nr 
            LEFT JOIN patients p ON p.id=nr.patient_id 
            LEFT JOIN visits v ON v.id=nr.visit_id
            LEFT JOIN users u ON u.id=nr.administered_by
            $whereSQL
            ORDER BY nr.created_at DESC LIMIT ?");
        $params[] = $limit;
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // --- VISIT ORDERS SUMMARY (Active items added for current visit) ---
    if ($action === 'visit_orders_summary') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        if (!$visitId) error('visit_id required', 422);

        $vStmt = $pdo->prepare("
            SELECT v.id, v.visit_no, v.status, v.patient_id, v.payment_type, v.insurance_id,
                   COALESCE(ic.name, ic2.name) AS company_name,
                   COALESCE(ip.name, ip2.name) AS package_name,
                   COALESCE(ip.copayment_percentage, ip2.copayment_percentage) AS copayment_percentage
            FROM visits v
            LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            LEFT JOIN insurance_companies ic2 ON ic2.id = v.insurance_id AND pi.id IS NULL
            LEFT JOIN patient_insurance pi2 ON pi2.patient_id = v.patient_id AND pi2.is_primary = 1 AND pi.id IS NULL
            LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
            WHERE v.id = ?
        ");
        $vStmt->execute([$visitId]);
        $visit = $vStmt->fetch();
        if (!$visit) error('Visit not found', 404);

        $isCompleted = ($visit['status'] === 'completed');

        // 1. Pharmacy / Prescriptions
        $rxStmt = $pdo->prepare("
            SELECT pi.id AS item_id, pi.prescription_id, pi.item_id AS inventory_item_id,
                   pi.dosage, pi.frequency, pi.duration, pi.quantity, pi.instructions, pi.status AS item_status,
                   p.prescription_no, p.created_at, p.status AS prescription_status,
                   (SELECT COALESCE(CONCAT(pu.first_name,' ',pu.last_name), pu.username) FROM users pu WHERE pu.id = p.doctor_id) AS prescribed_by_name,
                   COALESCE(i.name, pi.instructions, 'Medication') AS medication_name,
                   ii.id AS invoice_item_id, ii.invoice_id, ii.unit_price, ii.total AS invoice_total, ii.invoice_status, ii.invoice_balance
            FROM pharmacy_prescription_items pi
            JOIN pharmacy_prescriptions p ON p.id = pi.prescription_id
            LEFT JOIN items i ON i.id = pi.item_id
            LEFT JOIN (
                SELECT ii2.id, ii2.invoice_id, ii2.service_id, ii2.unit_price, ii2.total, ii2.description, inv.status AS invoice_status, inv.balance AS invoice_balance
                FROM invoice_items ii2
                JOIN invoices inv ON inv.id = ii2.invoice_id
                WHERE inv.visit_id = ? AND ii2.category IN ('medication', 'pharmacy', 'drug')
            ) ii ON (ii.service_id = pi.item_id OR ii.description LIKE CONCAT('%', COALESCE(i.name, pi.instructions), '%'))
            WHERE p.visit_id = ?
            ORDER BY pi.id DESC
        ");
        $rxStmt->execute([$visitId, $visitId]);
        $pharmacyItems = $rxStmt->fetchAll();

        // 2. Lab Orders
        $labStmt = $pdo->prepare("
            SELECT mo.id AS order_id, mo.description AS test_name, mo.details, mo.status, mo.ordered_at,
                   COALESCE(CONCAT(lu.first_name,' ',lu.last_name), lu.username) AS ordered_by_name,
                   ii.id AS invoice_item_id, ii.invoice_id, ii.unit_price, ii.total AS invoice_total, ii.invoice_status, ii.invoice_balance
            FROM medical_orders mo
            LEFT JOIN users lu ON lu.id = mo.ordered_by
            LEFT JOIN (
                SELECT ii2.id, ii2.invoice_id, ii2.unit_price, ii2.total, ii2.description, inv.status AS invoice_status, inv.balance AS invoice_balance
                FROM invoice_items ii2
                JOIN invoices inv ON inv.id = ii2.invoice_id
                WHERE inv.visit_id = ? AND ii2.category = 'lab'
            ) ii ON (ii.description LIKE CONCAT('%', mo.description, '%'))
            WHERE mo.visit_id = ? AND mo.order_type = 'lab'
            ORDER BY mo.id DESC
        ");
        $labStmt->execute([$visitId, $visitId]);
        $labItems = $labStmt->fetchAll();

        // 3. Procedures
        $procStmt = $pdo->prepare("
            SELECT ii.id AS invoice_item_id, ii.invoice_id, ii.service_id, ii.description AS procedure_name,
                   ii.quantity, ii.unit_price, ii.total, ii.added_at, inv.invoice_no, inv.status AS invoice_status, inv.balance AS invoice_balance
            FROM invoice_items ii
            JOIN invoices inv ON inv.id = ii.invoice_id
            WHERE inv.visit_id = ? AND ii.category = 'procedure'
            ORDER BY ii.id DESC
        ");
        $procStmt->execute([$visitId]);
        $procItems = $procStmt->fetchAll();

        // 4. Imaging / Radiology
        $imgStmt = $pdo->prepare("
            SELECT mo.id AS order_id, mo.description AS imaging_name, mo.details, mo.status, mo.ordered_at,
                   mo.result, mo.completed_at, mo.completed_by,
                   COALESCE(CONCAT(cb.first_name,' ',cb.last_name), cb.username) AS completed_by_name,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS ordered_by_name,
                   ii.id AS invoice_item_id, ii.invoice_id, ii.unit_price, ii.total AS invoice_total, ii.invoice_status, ii.invoice_balance
            FROM medical_orders mo
            LEFT JOIN users u ON u.id = mo.ordered_by
            LEFT JOIN users cb ON cb.id = mo.completed_by
            LEFT JOIN (
                SELECT ii2.id, ii2.invoice_id, ii2.unit_price, ii2.total, ii2.description, inv.status AS invoice_status, inv.balance AS invoice_balance
                FROM invoice_items ii2
                JOIN invoices inv ON inv.id = ii2.invoice_id
                WHERE inv.visit_id = ? AND ii2.category = 'radiology'
            ) ii ON (ii.description LIKE CONCAT('%', mo.description, '%'))
            WHERE mo.visit_id = ? AND mo.order_type = 'radiology'
            ORDER BY mo.id DESC
        ");
        $imgStmt->execute([$visitId, $visitId]);
        $imgItems = $imgStmt->fetchAll();

        $imgInvStmt = $pdo->prepare("
            SELECT NULL AS order_id, ii.description AS imaging_name, '' AS details, 'ordered' AS status,
                   NULL AS result, NULL AS completed_at, NULL AS completed_by,
                   NULL AS completed_by_name, NULL AS ordered_by_name,
                   ii.added_at AS ordered_at, ii.id AS invoice_item_id, ii.invoice_id, ii.unit_price, ii.total AS invoice_total, inv.status AS invoice_status, inv.balance AS invoice_balance
            FROM invoice_items ii
            JOIN invoices inv ON inv.id = ii.invoice_id
            WHERE inv.visit_id = ? AND ii.category = 'radiology'
            ORDER BY ii.id DESC
        ");
        $imgInvStmt->execute([$visitId]);
        $imgInvItems = $imgInvStmt->fetchAll();

        $seenInvIds = array_filter(array_column($imgItems, 'invoice_item_id'));
        $seenDescriptions = array_map(function($n){ return strtolower(trim($n)); }, array_column($imgItems, 'imaging_name'));
        foreach ($imgInvItems as $ii) {
            $descKey = strtolower(trim($ii['imaging_name']));
            if (!in_array($ii['invoice_item_id'], $seenInvIds) && !in_array($descKey, $seenDescriptions)) {
                // Ensure a medical_orders row exists for this invoice item
                try {
                    $insMo = $pdo->prepare("INSERT INTO medical_orders (visit_id, patient_id, order_type, description, details, status, ordered_at) VALUES (?, ?, 'radiology', ?, '', 'ordered', ?)");
                    $insMo->execute([$visitId, $visit['patient_id'], $ii['imaging_name'], $ii['ordered_at'] ?: date('Y-m-d H:i:s')]);
                    $ii['order_id'] = (int)$pdo->lastInsertId();
                } catch (Exception $e) {}
                $imgItems[] = $ii;
            }
        }

        // 5. All billed lines with their invoice's payment state (read-only view, e.g. nurse portal)
        $biStmt = $pdo->prepare("
            SELECT ii.description, ii.category, ii.quantity, ii.unit_price, ii.total,
                   inv.invoice_no, inv.status AS invoice_status, inv.balance, ii.added_at,
                   COALESCE(CONCAT(ab.first_name,' ',ab.last_name), ab.username) AS added_by_name
            FROM invoice_items ii
            JOIN invoices inv ON inv.id = ii.invoice_id
            LEFT JOIN users ab ON ab.id = COALESCE(ii.added_by, inv.created_by)
            WHERE inv.visit_id = ? AND inv.status <> 'cancelled'
            ORDER BY ii.id DESC
        ");
        $biStmt->execute([$visitId]);
        $billedItems = $biStmt->fetchAll();

        success([
            'visit' => $visit,
            'is_completed' => $isCompleted,
            'pharmacy' => $pharmacyItems,
            'labs' => $labItems,
            'procedures' => $procItems,
            'imaging' => $imgItems,
            'billed_items' => $billedItems
        ]);
    }

    // Patient full history (past consultations)
    if ($action === 'history') {
        $pid = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
        if (!$pid) error('patient_id required', 422);
        $stmt = $pdo->prepare("
            SELECT c.id, c.visit_id, c.created_at, c.diagnosis, c.treatment_plan, c.notes, c.outcome, c.status,
                   d.name AS department_name, c.doctor_name,
                   v.visit_no, v.visit_date
            FROM consultations c
            JOIN visits v ON v.id=c.visit_id
            LEFT JOIN departments d ON d.id=c.department_id
            WHERE c.patient_id=? AND c.status='completed'
            ORDER BY c.created_at DESC LIMIT 20
        ");
        $stmt->execute([$pid]);
        success($stmt->fetchAll());
    }

    // Single consultation with orders
    if ($id) {
        $stmt = $pdo->prepare("SELECT c.*, d.name AS dept_name FROM consultations c LEFT JOIN departments d ON d.id=c.department_id WHERE c.id=?");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) error('Consultation not found', 404);
        $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id=? ORDER BY ordered_at DESC");
        $oStmt->execute([$id]);
        $c['orders'] = $oStmt->fetchAll();
        success($c);
    }

    // By visit_id
    $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
    $deptId  = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    if ($visitId) {
        $where = 'WHERE c.visit_id=?'; $params = [$visitId];
        if ($deptId) { $where .= ' AND c.department_id=?'; $params[] = $deptId; }
        $stmt = $pdo->prepare("
            SELECT c.*, d.name AS dept_name,
                   COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username)   AS created_by_name,
                   COALESCE(CONCAT(u1.first_name,' ',u1.last_name), u1.username) AS step1_by_name,
                   COALESCE(CONCAT(u3.first_name,' ',u3.last_name), u3.username) AS step3_by_name,
                   COALESCE(CONCAT(uc.first_name,' ',uc.last_name), uc.username) AS completed_by_name
            FROM consultations c
            LEFT JOIN departments d  ON d.id  = c.department_id
            LEFT JOIN users u  ON u.id  = c.created_by
            LEFT JOIN users u1 ON u1.id = c.step1_by
            LEFT JOIN users u3 ON u3.id = c.step3_by
            LEFT JOIN users uc ON uc.id = c.completed_by
            $where ORDER BY c.created_at DESC LIMIT 1
        ");
        $stmt->execute($params);
        $c = $stmt->fetch();
        if ($c) {
            $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id=? ORDER BY ordered_at");
            $oStmt->execute([$c['id']]);
            $c['orders'] = $oStmt->fetchAll();
        }
        success($c ?: null);
    }

    error('Missing parameters', 422);
}

// POST — save lab result
// POST — emergency case or treatment
// POST — confirm lab item consumption (deduct quantity from stock)
if ($method === 'POST' && $action === 'confirm_lab_consumption') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $orderId = !empty($input['order_id']) ? (int)$input['order_id'] : null;
    $visitId = !empty($input['visit_id']) ? (int)$input['visit_id'] : null;
    $patientId = !empty($input['patient_id']) ? (int)$input['patient_id'] : null;
    $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
    $storeId = (int)($input['store_id'] ?? 0) ?: lab_store_id($pdo);
    $items = $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    if (empty($items) || !is_array($items)) {
        error('At least one item is required for consumption', 422);
    }

    if ($orderId && (!$patientId || !$visitId)) {
        $ordStmt = $pdo->prepare("SELECT patient_id, visit_id, description FROM medical_orders WHERE id = ?");
        $ordStmt->execute([$orderId]);
        if ($oRow = $ordStmt->fetch()) {
            if (!$patientId) $patientId = (int)$oRow['patient_id'];
            if (!$visitId) $visitId = (int)$oRow['visit_id'];
        }
    }

    $pdo->beginTransaction();
    try {
        $deductedItems = lab_consume_items($pdo, $items, $orderId, $visitId, $patientId, $serviceId, $storeId, $notes, $userId);

        $pdo->commit();
        success([
            'order_id' => $orderId,
            'deducted_items' => $deductedItems,
            'count' => count($deductedItems)
        ], 'Stock quantity deducted and consumption confirmed successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to deduct stock: ' . $e->getMessage(), 500);
    }
}

// POST — reverse lab item consumption
if ($method === 'POST' && $action === 'reverse_lab_consumption') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $consumptionId = (int)($input['consumption_id'] ?? 0);
    $reason = trim($input['reason'] ?? 'Lab consumption reversal');
    $userId = $_SESSION['user_id'] ?? null;

    if (!$consumptionId) error('consumption_id required', 422);

    $stmt = $pdo->prepare("SELECT * FROM lab_item_consumption WHERE id = ?");
    $stmt->execute([$consumptionId]);
    $lic = $stmt->fetch();
    if (!$lic) error('Consumption record not found', 404);
    if ($lic['status'] === 'reversed') error('Consumption record already reversed', 400);

    $pdo->beginTransaction();
    try {
        // Restore stock to stock_batches
        if (!empty($lic['batch_id'])) {
            $pdo->prepare("UPDATE stock_batches SET quantity = quantity + ? WHERE id = ?")
                ->execute([(float)$lic['quantity'], (int)$lic['batch_id']]);
        } else {
            $pdo->prepare("UPDATE stock_batches SET quantity = quantity + ? WHERE item_id = ? AND store_id = ? ORDER BY id DESC LIMIT 1")
                ->execute([(float)$lic['quantity'], (int)$lic['item_id'], (int)$lic['store_id']]);
        }

        // Record reversing stock movement
        $pdo->prepare("
            INSERT INTO stock_movements (item_id, batch_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
            VALUES (?, ?, ?, 'adjustment', ?, ?, 'lab_consumption_reversal', ?, ?, ?, NOW())
        ")->execute([(int)$lic['item_id'], $lic['batch_id'] ?: null, (int)$lic['store_id'], (float)$lic['quantity'], (float)$lic['unit_cost'], $consumptionId, "Reversal: $reason", $userId]);

        // Mark as reversed
        $pdo->prepare("UPDATE lab_item_consumption SET status = 'reversed', notes = CONCAT(COALESCE(notes,''), ' [REVERSED: ', ?, ']') WHERE id = ?")
            ->execute([$reason, $consumptionId]);

        $pdo->commit();
        success(null, 'Consumption reversed and stock restored successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to reverse consumption: ' . $e->getMessage(), 500);
    }
}

// POST — update prescription item quantity (syncs prescription, invoice item, invoice totals, and journal entries)
if ($method === 'POST' && ($action === 'update_rx_item_quantity' || $action === 'update_prescription_quantity' || $action === 'update_prescription_item_qty')) {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $itemId = (int)($input['prescription_item_id'] ?? $input['item_id'] ?? $_GET['id'] ?? 0);
    $newQty = (int)($input['quantity'] ?? $_GET['quantity'] ?? 0);

    if (!$itemId || $newQty < 1) {
        error('Valid prescription item ID and quantity (minimum 1) are required.', 422);
    }

    // 1. Fetch prescription item details
    $piStmt = $pdo->prepare("
        SELECT ppi.*, pp.visit_id, pp.patient_id, pp.prescription_no, i.name AS item_name, i.item_code
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_prescriptions pp ON pp.id = ppi.prescription_id
        LEFT JOIN items i ON i.id = ppi.item_id
        WHERE ppi.id = ?
    ");
    $piStmt->execute([$itemId]);
    $pi = $piStmt->fetch(PDO::FETCH_ASSOC);

    if (!$pi) {
        error('Prescription item not found.', 404);
    }

    $visitId = (int)$pi['visit_id'];
    $oldQty = (int)$pi['quantity'];
    $medItemId = (int)($pi['item_id'] ?? 0);
    $medName = $pi['item_name'] ?? $pi['instructions'] ?? '';

    $pdo->beginTransaction();
    try {
        // 2. Update pharmacy_prescription_items
        $pdo->prepare("UPDATE pharmacy_prescription_items SET quantity = ? WHERE id = ?")
            ->execute([$newQty, $itemId]);

        // 3. Find corresponding invoice item
        $invItemStmt = $pdo->prepare("
            SELECT ii.*, i.id AS inv_id
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE i.visit_id = ?
              AND ii.category IN ('medication', 'pharmacy', 'drug')
              AND (
                  ii.service_id = ?
                  OR (ii.description LIKE ? AND ii.description != '')
                  OR (ii.description LIKE ? AND ? != '')
              )
            ORDER BY ii.id DESC
            LIMIT 1
        ");
        $invItemStmt->execute([
            $visitId,
            $medItemId,
            "%{$medName}%",
            "%{$pi['item_code']}%",
            $pi['item_code'] ?? ''
        ]);
        $invItem = $invItemStmt->fetch(PDO::FETCH_ASSOC);

        $invoiceUpdated = false;
        $invoiceId = null;

        if ($invItem) {
            $invoiceId = (int)$invItem['inv_id'];
            $unitPrice = (float)$invItem['unit_price'];
            $discount = (float)($invItem['discount'] ?? 0);
            $newItemTotal = max(0, ($unitPrice * $newQty) - $discount);

            // Update invoice item
            $pdo->prepare("UPDATE invoice_items SET quantity = ?, total = ? WHERE id = ?")
                ->execute([$newQty, $newItemTotal, $invItem['id']]);

            // Totals, patient / insurance shares and the sale journal (includes/billing-calc.php)
            recalcInvoice($pdo, $invoiceId);
            $invoiceUpdated = true;
        }

        $pdo->commit();
        success([
            'prescription_item_id' => $itemId,
            'old_quantity' => $oldQty,
            'new_quantity' => $newQty,
            'invoice_updated' => $invoiceUpdated,
            'invoice_id' => $invoiceId
        ], "Quantity updated from $oldQty to $newQty.");
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to update quantity: ' . $e->getMessage());
    }
}

if ($method === 'POST' && $action === 'emergency') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $subAction = $_GET['sub_action'] ?? '';
    
    // Add treatment to a case
    if ($subAction === 'add_treatment') {
        $caseId = (int)($input['case_id'] ?? 0);
        if (!$caseId) error('case_id required', 422);
        consult_ensure_schema($pdo);
        $stmt = $pdo->prepare("INSERT INTO emergency_treatments (case_id, treatment_type, item_id, service_id, description, dosage, quantity, unit_price, notes, administered_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $caseId, $input['treatment_type']??'other',
            !empty($input['item_id'])?(int)$input['item_id']:null,
            !empty($input['service_id'])?(int)$input['service_id']:null,
            $input['description']??'Treatment', $input['dosage']??'',
            (float)($input['quantity']??1), (float)($input['unit_price']??0), $input['notes']??'',
            $_SESSION['user_id'] ?? null
        ]);
        // Update case status to treatment
        $pdo->prepare("UPDATE emergency_cases SET status='treatment' WHERE id=? AND status='triage'")->execute([$caseId]);
        success(['id'=>(int)$pdo->lastInsertId()], 'Treatment added');
    }
    
    // New emergency case
    $stmt = $pdo->prepare("INSERT INTO emergency_cases (patient_id, triage_level, chief_complaint, arrival_mode, consciousness, vitals_bp, vitals_pulse, vitals_temp, vitals_spo2, vitals_rr) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        !empty($input['patient_id'])?(int)$input['patient_id']:null,
        $input['triage_level']??'yellow', $input['chief_complaint']??'',
        $input['arrival_mode']??'walk_in', $input['consciousness']??'alert',
        $input['vitals_bp']??null, $input['vitals_pulse']??null,
        $input['vitals_temp']??null, $input['vitals_spo2']??null, $input['vitals_rr']??null
    ]);
    success(['id'=>(int)$pdo->lastInsertId()], 'Emergency case registered');
}

// PUT — update emergency case (discharge)
if ($method === 'PUT' && $action === 'emergency') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) error('Case ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $status = $input['status'] ?? 'discharged';
    
    $pdo->prepare("UPDATE emergency_cases SET status=?, discharged_at=NOW() WHERE id=?")->execute([$status, $id]);
    
    // Create invoice for all treatments
    if (($input['create_invoice']??false)) {
        $treatments = $pdo->prepare("SELECT * FROM emergency_treatments WHERE case_id=? AND unit_price > 0"); $treatments->execute([$id]);
        $items = $treatments->fetchAll();
        if (!empty($items)) {
            $case = $pdo->prepare("SELECT * FROM emergency_cases WHERE id=?"); $case->execute([$id]); $caseData = $case->fetch();
            $total = 0;
            foreach ($items as $it) { $total += (float)$it['quantity'] * (float)$it['unit_price']; }
            
            $invoiceNo = next_invoice_no($pdo);
            
            $pdo->prepare("INSERT INTO invoices (invoice_no, patient_id, subtotal, total, balance, status, notes, created_at) VALUES (?,?,?,?,?,'issued',?,NOW())")
                ->execute([$invoiceNo, $caseData['patient_id'], $total, $total, $total, 'Emergency treatments']);
            $invId = (int)$pdo->lastInsertId();
            
            foreach ($items as $it) {
                $lineTotal = (float)$it['quantity'] * (float)$it['unit_price'];
                consult_invoice_line($pdo, $invId, $it['service_id'] ?? null, $it['item_id'] ?? null, $it['description'], 'emergency', (float)$it['quantity'], (float)$it['unit_price'], $lineTotal);
            }
            fin_post_invoice_safe($pdo, $invId);
        }
    }
    success(null, 'Case updated');
}

// POST — schedule surgery
if ($method === 'POST' && $action === 'surgery') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['patient_id']) || empty($input['procedure_name'])) error('patient_id and procedure_name required', 422);
    $stmt = $pdo->prepare("INSERT INTO surgeries (patient_id, visit_id, procedure_name, service_id, total_cost, surgeon_name, anesthetist_name, theatre_room, scheduled_date, scheduled_time, priority, pre_op_notes, anesthesia_type, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'scheduled')");
    $stmt->execute([
        (int)$input['patient_id'], $input['visit_id']??null, $input['procedure_name'],
        $input['service_id']??null, (float)($input['total_cost']??0),
        $input['surgeon_name']??null, $input['anesthetist_name']??null, $input['theatre_room']??null,
        $input['scheduled_date']??date('Y-m-d'), $input['scheduled_time']??null,
        $input['priority']??'elective', $input['pre_op_notes']??null,
        in_array(strtolower((string)($input['anesthesia_type']??'')),['general','local','spinal','epidural','sedation'],true) ? strtolower($input['anesthesia_type']) : null // HMIS anesthesia table
    ]);
    $surgeryId = (int)$pdo->lastInsertId();
    
    // Save additional items if any
    $items = $input['items'] ?? [];
    if (!empty($items)) {
        $itemTotal = 0;
        foreach ($items as $it) {
            $itemTotal += (float)($it['unit_price']??0) * (int)($it['quantity']??1);
        }
        // Store items as JSON in pre_op_notes (append)
        $itemsJson = json_encode($items);
        $pdo->prepare("UPDATE surgeries SET pre_op_notes = CONCAT(COALESCE(pre_op_notes,''), '\n[ITEMS]', ?) , total_cost = total_cost + ? WHERE id = ?")->execute([$itemsJson, $itemTotal, $surgeryId]);
    }
    
    success(['id'=>$surgeryId], 'Surgery scheduled');
}

// PUT — update surgery status
if ($method === 'PUT' && $action === 'surgery') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) error('Surgery ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $fields = []; $params = [];
    $allowed = ['status','actual_start','actual_end','post_op_notes','complications','duration_minutes','anesthesia_type'];
    foreach ($allowed as $f) { if (array_key_exists($f,$input)) { $fields[]="$f=?"; $params[]=$input[$f]; } }
    if ($fields) { $params[]=$id; $pdo->prepare("UPDATE surgeries SET ".implode(',',$fields)." WHERE id=?")->execute($params); }
    
    // Create invoice on completion (procedure + items)
    if (($input['status']??'') === 'completed' && ($input['create_invoice']??false)) {
        $sg = $pdo->prepare("SELECT * FROM surgeries WHERE id=?"); $sg->execute([$id]); $surgery = $sg->fetch();
        if ($surgery && $surgery['total_cost'] > 0) {
            $invoiceNo = next_invoice_no($pdo);
            $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, subtotal, total, balance, status, notes, created_at) VALUES (?,?,?,?,?,?,'issued',?,NOW())")
                ->execute([$invoiceNo, $surgery['visit_id'], $surgery['patient_id'], $surgery['total_cost'], $surgery['total_cost'], $surgery['total_cost'], 'Surgery: '.$surgery['procedure_name']]);
            $invId = (int)$pdo->lastInsertId();
            
            // Add procedure as first item
            $procCost = (float)($surgery['total_cost']);
            // Check if items were stored
            $itemsData = [];
            if (strpos($surgery['pre_op_notes']??'', '[ITEMS]') !== false) {
                $parts = explode('[ITEMS]', $surgery['pre_op_notes']);
                if (isset($parts[1])) { try { $itemsData = json_decode($parts[1], true) ?? []; } catch(Exception $e){} }
                $itemsTotal = 0;
                foreach ($itemsData as $it) { $itemsTotal += (float)($it['unit_price']??0) * (int)($it['quantity']??1); }
                $procCost = $surgery['total_cost'] - $itemsTotal;
            }
            
            // Procedure line item
            if ($procCost > 0) {
                $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, total) VALUES (?,?,?,?,1,?,?)")
                    ->execute([$invId, $surgery['service_id'], $surgery['procedure_name'], 'procedure', $procCost, $procCost]);
            }
            
            // Additional items
            foreach ($itemsData as $it) {
                $qty = (int)($it['quantity']??1);
                $price = (float)($it['unit_price']??0);
                consult_invoice_line($pdo, $invId, null, $it['item_id'] ?? null, $it['name']??'Item', 'pharmacy', $qty, $price, $qty*$price);
            }
            
            fin_post_invoice_safe($pdo, $invId);
            $pdo->prepare("UPDATE surgeries SET invoice_id=? WHERE id=?")->execute([$invId, $id]);
        }
    }
    success(null, 'Surgery updated');
}

// POST — save nursing record with invoice
if ($method === 'POST' && $action === 'prescribed_dosage') {
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $pid = (int)($in['patient_id'] ?? 0); $vid = (int)($in['visit_id'] ?? 0);
    $drug = trim($in['drug_name'] ?? ''); $dose = (float)($in['dose'] ?? 0);
    if (!$pid || !$drug || $dose <= 0) error('patient_id, drug_name and a dose above 0 are required', 422);
    $itemId = !empty($in['item_id']) ? (int)$in['item_id'] : null;
    // A new dose for the same drug replaces the active one
    $pdo->prepare("UPDATE prescribed_dosage SET active = 0, stop_date = CURDATE() WHERE patient_id = ? AND active = 1 AND (drug_name = ? OR (? IS NOT NULL AND item_id = ?))")->execute([$pid, $drug, $itemId, $itemId]);
    $pdo->prepare("INSERT INTO prescribed_dosage (patient_id, visit_id, item_id, drug_code, drug_name, dose, unit, frequency, route, start_date, stop_date, prescribed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$pid, $vid ?: null, $itemId, trim($in['drug_code'] ?? '') ?: null, $drug, $dose, trim($in['unit'] ?? '') ?: 'mg', trim($in['frequency'] ?? '') ?: null, trim($in['route'] ?? '') ?: null,
                   $in['start_date'] ?? date('Y-m-d'), ($in['stop_date'] ?? '') ?: null, $_SESSION['user_id'] ?? null]);
    $newId = (int)$pdo->lastInsertId();
    log_activity($pdo, 'CREATE', 'consultations', 'Prescribed dosage recorded: ' . $drug . ' ' . $dose . ' ' . ($in['unit'] ?? 'mg') . ' for visit #' . $vid, $newId);
    success(['id' => $newId], 'Dosage saved');
}
if ($method === 'POST' && $action === 'prescribed_dosage_stop') {
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $pdo->prepare("UPDATE prescribed_dosage SET active = 0, stop_date = CURDATE() WHERE id = ?")->execute([(int)($in['id'] ?? 0)]);
    log_activity($pdo, 'UPDATE', 'consultations', 'Prescribed dosage stopped', (int)($in['id'] ?? 0));
    success(null, 'Dosage stopped');
}
if ($method === 'POST' && $action === 'dosage_alert_ack') {
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $pdo->prepare("UPDATE dosage_alerts SET acknowledged_by = ?, acknowledged_at = NOW() WHERE id = ? AND acknowledged_at IS NULL")->execute([$_SESSION['user_id'] ?? null, (int)($in['id'] ?? 0)]);
    log_activity($pdo, 'UPDATE', 'consultations', 'Dosage alert acknowledged', (int)($in['id'] ?? 0));
    success(null, 'Acknowledged');
}

if ($method === 'POST' && $action === 'nursing_record') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $patientId = (int)($input['patient_id'] ?? 0);
    $visitId = (int)($input['visit_id'] ?? 0);
    $assignId = (int)($input['bed_assignment_id'] ?? 0);
    $recordType = $input['record_type'] ?? 'note';
    $itemId = !empty($input['item_id']) ? (int)$input['item_id'] : null;
    $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
    $description = trim($input['description'] ?? '');
    $dosage = $input['dosage'] ?? '';
    $qty = (float)($input['quantity'] ?? 1);
    $unitPrice = (float)($input['unit_price'] ?? 0);
    $notes = $input['notes'] ?? '';
    
    if (!$patientId) error('patient_id required', 422);
    if (!$description) error('Description required', 422);

    // Dose check: a given dose that differs from the doctor's active prescription needs a reason and raises a dosage alert
    $doseAlert = null;
    if (in_array($recordType, ['medication', 'injection'], true) && preg_match('/\d+(?:\.\d+)?/', (string)$dosage, $dm)) {
        $rx = $pdo->prepare("SELECT * FROM prescribed_dosage WHERE patient_id = ? AND active = 1 AND start_date <= CURDATE() AND (stop_date IS NULL OR stop_date >= CURDATE())
                             AND ((? IS NOT NULL AND item_id = ?) OR drug_name = ?) ORDER BY id DESC LIMIT 1");
        $rx->execute([$patientId, $itemId, $itemId, $description]);
        $rxRow = $rx->fetch();
        if ($rxRow && abs((float)$dm[0] - (float)$rxRow['dose']) > 0.0005) {
            $reason = trim($input['dose_reason'] ?? '');
            $presc = (float)$rxRow['dose'] . ' ' . $rxRow['unit'];
            if ($reason === '') error('DOSE_MISMATCH: prescribed ' . $presc . ', given ' . $dosage . '. A reason is required.', 422);
            $doseAlert = [$rxRow['drug_name'], $presc, (string)$dosage, mb_substr($reason, 0, 500), $rxRow['prescribed_by']];
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO nursing_records (patient_id, visit_id, bed_assignment_id, record_type, item_id, service_id, description, dosage, quantity, unit_price, notes, administered_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $adminBy = $_SESSION['user_id'] ?? ($input['administered_by'] ?? null);
        $stmt->execute([$patientId, $visitId, $assignId, $recordType, $itemId, $serviceId, $description, $dosage, $qty, $unitPrice, $notes, $adminBy]);
        $recordId = (int)$pdo->lastInsertId();
        if ($doseAlert) {
            $pdo->prepare("INSERT INTO dosage_alerts (patient_id, visit_id, drug, prescribed_dose, given_dose, reason, nurse_id, prescribed_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$patientId, $visitId ?: null, $doseAlert[0], $doseAlert[1], $doseAlert[2], $doseAlert[3], $adminBy, $doseAlert[4]]);
            log_activity($pdo, 'CREATE', 'consultations', 'Dosage alert: ' . $doseAlert[0] . ' prescribed ' . $doseAlert[1] . ', given ' . $doseAlert[2] . ' - ' . $doseAlert[3], (int)$pdo->lastInsertId());
        }

        $invoiceNo = null;
        $total = $qty * $unitPrice;
        if ($total > 0) {
            $invoiceNo = next_invoice_no($pdo);
            
            $payType = 'self_pay'; $insId = null; $copayPct = 100; $companyId = null;
            if ($visitId) {
                $vs = $pdo->prepare("SELECT v.payment_type, v.insurance_id, pi.company_id, ip.copayment_percentage 
                    FROM visits v 
                    LEFT JOIN patient_insurance pi ON pi.id = (SELECT pi9.id FROM patient_insurance pi9 WHERE pi9.patient_id = v.patient_id ORDER BY (pi9.id = v.insurance_id) DESC, (pi9.company_id = v.insurance_id) DESC, pi9.is_primary DESC, pi9.id DESC LIMIT 1) 
                    LEFT JOIN insurance_packages ip ON ip.id = pi.package_id 
                    WHERE v.id=?");
                $vs->execute([$visitId]);
                $vd = $vs->fetch();
                if ($vd) {
                    $payType = $vd['payment_type'] ?? 'self_pay';
                    $insId = $vd['insurance_id'];
                    $companyId = $vd['company_id'] ?? null;
                    $copayPct = (float)($vd['copayment_percentage'] ?? 100);
                }
            }
            
            $insPriced = false;
            $isInsurance = $companyId && ($payType === 'insurance' || $payType === 'mixed');
            $consultTariff = visitTariff($pdo, $visitId, $patientId);

            // #41: one price rule for catalog services (insurer price, regional price, tariff group, facility column)
            if ($serviceId && !$itemId) {
                $rulePrice = servicePrice($pdo, $serviceId, $isInsurance ? (int)$companyId : null, $consultTariff);
                if ($rulePrice > 0) {
                    $unitPrice = $rulePrice;
                    $insPriced = true;
                    $total = $qty * $unitPrice;
                }
            } elseif ($isInsurance && $itemId) {
                // Drugs keep their insurer item price
                $insItemStmt = $pdo->prepare("SELECT price FROM item_insurance_prices WHERE insurance_id = ? AND item_id = ?");
                $insItemStmt->execute([$companyId, $itemId]);
                $insPrice = $insItemStmt->fetchColumn();
                if ($insPrice !== false && (float)$insPrice > 0) {
                    $unitPrice = (float)$insPrice;
                    $insPriced = true;
                    $total = $qty * $unitPrice;
                }
            }

            // Foreign patient without an insurer price: the server's regional price wins over the browser's
            if ($serviceId && !$itemId && !$insPriced) {
                $fp = foreignServicePrice($pdo, $serviceId, $consultTariff);
                if ($fp !== null) { $unitPrice = $fp; $total = $qty * $unitPrice; }
            }

            // Calculate patient balance based on copayment
            $patientBalance = $total;
            if ($isInsurance) {
                $patientBalance = fin_split($total, $copayPct)['patient'];
            }
            
            $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_at) VALUES (?,?,?,?,?,?,?,?,'issued',?,NOW())")
                ->execute([$invoiceNo, $visitId, $patientId, $isInsurance ? 'insurance' : 'self_pay', $isInsurance ? $companyId : null, $total, $total, $patientBalance, "Nursing: $description"]);
            $invoiceId = (int)$pdo->lastInsertId();
            consult_invoice_line($pdo, $invoiceId, $serviceId, $itemId, $description, $recordType==='service'?'nursing':'pharmacy', $qty, $unitPrice, $total);
            $pdo->prepare("UPDATE nursing_records SET invoice_id=? WHERE id=?")->execute([$invoiceId, $recordId]);
            fin_post_invoice_safe($pdo, $invoiceId);
        }
        $pdo->commit();
        success(['id' => $recordId, 'invoice_no' => $invoiceNo], 'Nursing record saved');
    } catch (Exception $e) { $pdo->rollBack(); error('Failed: ' . $e->getMessage()); }
}

// POST — second-person verification of a completed lab/imaging result
if ($method === 'POST' && $action === 'order_verify') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    order_verify_result($pdo, (int)($input['order_id'] ?? 0), ['lab', 'radiology']);
}

// POST — lab: sample not taken. Test gets no result, leaves the invoice, and the doctor is told.
if ($method === 'POST' && $action === 'lab_no_sample') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $orderId = (int)($input['order_id'] ?? 0);
    if (!$orderId) error('order_id required', 422);
    $st = $pdo->prepare("SELECT mo.id, mo.status, mo.description, mo.visit_id, mo.patient_id, mo.ordered_by, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=? AND mo.order_type='lab'");
    $st->execute([$orderId]);
    $o = $st->fetch();
    if (!$o) error('Lab order not found', 404);
    if (in_array($o['status'], ['completed', 'cancelled', 'no_sample'], true)) error('A ' . $o['status'] . ' order cannot be marked as no sample.', 409);
    $visitId = (int)$o['visit_id'];
    if ($visitId) record_require_unlocked($pdo, 'visit', $visitId);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE medical_orders SET status='no_sample', result=NULL, completed_at=NOW(), completed_by=? WHERE id=?")->execute([$_SESSION['user_id'] ?? null, $orderId]);
        // Remove one matching invoice line (this order's) and re-total
        $li = $pdo->prepare("SELECT ii.id, ii.invoice_id FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE i.visit_id = ? AND i.status <> 'cancelled' AND ii.category IN ('lab','procedure') AND (ii.description = ? OR ii.description = ?) ORDER BY ii.id DESC LIMIT 1");
        $li->execute([$visitId, $o['description'], 'Lab Test — ' . $o['description']]);
        if ($line = $li->fetch()) {
            record_require_unlocked($pdo, 'invoice', (int)$line['invoice_id']);
            $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$line['id']]);
            syncInvoiceAndJournal($pdo, (int)$line['invoice_id']);
        }
        $nq = $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role, target_user_id) VALUES ('lab_no_sample', ?, ?, ?, ?, 'doctor', ?)");
        $nq->execute(['Lab sample not taken', 'Test "' . $o['description'] . '" for ' . $o['patient_name'] . ' was NOT done: sample not taken. It has been removed from billing.', $visitId ?: null, $o['patient_id'], $o['ordered_by'] ?: null]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error('Failed: ' . $e->getMessage(), 500); }

    if ($visitId) consult_event($pdo, $visitId, 'Lab sample not taken', $o['description']);
    log_activity($pdo, 'UPDATE', 'lab', 'Lab sample not taken: "' . $o['description'] . '" for ' . $o['patient_name'] . ' — removed from billing', $orderId, null);
    success(['order_id' => $orderId], 'Marked as no sample');
}

if ($method === 'POST' && $action === 'lab_result') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $orderId = (int)($input['order_id'] ?? 0);
    $result = trim($input['result'] ?? '');
    $status = $input['status'] ?? 'completed';
    
    if (!$orderId) error('order_id required', 422);
    if (!$result) error('Result text required', 422);
    lab_result_guard($pdo, $orderId, (string)$status, !empty($input['sample_collected']) || $status === 'in_progress');
    
    // Collect Sample: consumables used for the sample are deducted from the Laboratory store together with the status change,
    // all or nothing (a store that holds too little refuses, nothing is saved).
    $consumables = is_array($input['consumables'] ?? null) ? $input['consumables'] : [];
    $consTx = false;
    if ($consumables) {
        $ord = $pdo->prepare("SELECT visit_id, patient_id, service_id FROM medical_orders WHERE id = ?");
        $ord->execute([$orderId]);
        $oRow = $ord->fetch();
        if (!$oRow) error('Order not found', 404);
        $done = $pdo->prepare("SELECT COUNT(*) FROM lab_item_consumption WHERE order_id = ? AND status = 'confirmed'");
        $done->execute([$orderId]);
        if ((int)$done->fetchColumn()) error('Consumables were already recorded for this order.', 422);
        $consStore = (int)($input['store_id'] ?? 0) ?: lab_store_id($pdo);
        $pdo->beginTransaction();
        $consTx = true;
        try {
            lab_consume_items($pdo, $consumables, $orderId, (int)$oRow['visit_id'] ?: null, (int)$oRow['patient_id'] ?: null, (int)$oRow['service_id'] ?: null,
                              $consStore, trim($input['notes'] ?? '') ?: 'Sample collection', $_SESSION['user_id'] ?? null, true);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error($e->getMessage(), 422);
        }
    }

    $stmt = $pdo->prepare("UPDATE medical_orders SET result = ?, status = ?, completed_at = NOW(), completed_by = ? WHERE id = ?");
    $stmt->execute([$result, $status, $_SESSION['user_id'] ?? null, $orderId]);
    if ($consTx) $pdo->commit();

    // Fetch test name for log
    $tstStmt = $pdo->prepare("SELECT mo.description, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM medical_orders mo LEFT JOIN patients p ON p.id=mo.patient_id WHERE mo.id=?");
    $tstStmt->execute([$orderId]);
    $tst = $tstStmt->fetch();
    $mvStmt = $pdo->prepare("SELECT visit_id FROM medical_orders WHERE id = ?");
    $mvStmt->execute([$orderId]);
    if ($mvId = (int)$mvStmt->fetchColumn()) consult_event($pdo, $mvId, 'Lab result entered', ($tst['description'] ?? 'Order #'.$orderId));
    log_activity($pdo, 'UPDATE', 'lab',
        'Lab result entered: "' . ($tst['description'] ?? 'Order #'.$orderId) . '" for ' . ($tst['patient_name'] ?? 'patient') .
        ' | Result: ' . substr($result, 0, 80) . ' | Status: ' . $status,
        $orderId, null
    );
    
    // If this result is marked as completed, check if ALL lab orders for this visit are done
    if ($status === 'completed') {
        // Get the visit_id and patient_id for this order
        $orderStmt = $pdo->prepare("SELECT visit_id, patient_id FROM medical_orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        
        if ($order && $order['visit_id']) {
            $visitId = (int)$order['visit_id'];
            $patientId = (int)$order['patient_id'];
            
            // Count total lab/radiology orders for this visit
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM medical_orders WHERE visit_id = ? AND order_type IN ('lab','radiology')");
            $totalStmt->execute([$visitId]);
            $totalOrders = (int)$totalStmt->fetchColumn();
            
            // Count completed lab/radiology orders for this visit
            $completedStmt = $pdo->prepare("SELECT COUNT(*) FROM medical_orders WHERE visit_id = ? AND order_type IN ('lab','radiology') AND status = 'completed'");
            $completedStmt->execute([$visitId]);
            $completedOrders = (int)$completedStmt->fetchColumn();
            
            // If all orders are completed, create a notification
            if ($totalOrders > 0 && $completedOrders >= $totalOrders) {
                // Check if a notification already exists for this visit (avoid duplicates)
                $existsStmt = $pdo->prepare("SELECT id FROM notifications WHERE visit_id = ? AND type = 'lab_ready' AND is_read = 0");
                $existsStmt->execute([$visitId]);
                
                if (!$existsStmt->fetch()) {
                    // Get patient name
                    $ptStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM patients WHERE id = ?");
                    $ptStmt->execute([$patientId]);
                    $ptName = $ptStmt->fetchColumn() ?: 'Patient';
                    
                    $notifTitle = "Lab Results Ready";
                    $notifMessage = "All $totalOrders lab test(s) for $ptName are completed and ready for review.";
                    
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role) VALUES ('lab_ready', ?, ?, ?, ?, 'nurse')");
                    $notifStmt->execute([$notifTitle, $notifMessage, $visitId, $patientId]);
                }
            }
        }
    }
    
    success(['order_id' => $orderId], 'Result saved');
}

// POST — reopen a completed consultation
if ($method === 'POST' && $action === 'reopen') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    if (!$visitId) error('visit_id required', 422);
    
    // Re-open the consultation record
    $pdo->prepare("UPDATE consultations SET status='draft' WHERE visit_id=? AND status='completed' ORDER BY id DESC LIMIT 1")
        ->execute([$visitId]);
    
    // Re-open the visit - update visit_date and queued_at to now so it shows in today's queue
    $pdo->prepare("UPDATE visits SET status='in_progress', consultation_status='waiting', visit_date=CURDATE(), queued_at=NOW() WHERE id=?")
        ->execute([$visitId]);
    
    log_activity($pdo, 'UPDATE', 'consultations', 'Consultation re-opened for visit #' . $visitId, null, null);
    consult_event($pdo, $visitId, 'Consultation re-opened');
    success(null, 'Consultation re-opened');
}

// POST — pharmacy order (create prescription + items)
if ($method === 'POST' && $action === 'pharmacy_order') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $deptId = (int)($input['department_id'] ?? 0);
    $items = $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');

    if (!$visitId || !$patientId) error('visit_id and patient_id required', 422);
    if (empty($items)) error('At least one medication required', 422);

    // Allergy check: block unless the doctor gives a written reason (logged)
    allergy_gate($pdo, $patientId, allergy_drug_names($pdo, array_column($items, 'item_id'), array_column($items, 'medication')), (string)($input['allergy_override_reason'] ?? ''), "Prescribing, visit #$visitId");

    // Double click / resend: the same medicines for the same visit within 10 minutes is the same prescription
    $wantIds = array_map(fn($i) => (int)($i['item_id'] ?? 0), $items); sort($wantIds);
    $recent = $pdo->prepare("SELECT p.id, p.prescription_no, GROUP_CONCAT(i.item_id ORDER BY i.item_id) ids FROM pharmacy_prescriptions p JOIN pharmacy_prescription_items i ON i.prescription_id = p.id
        WHERE p.visit_id = ? AND p.status <> 'cancelled' AND p.created_at > NOW() - INTERVAL 10 MINUTE GROUP BY p.id");
    $recent->execute([$visitId]);
    foreach ($recent->fetchAll() as $r) {
        if ($r['ids'] === implode(',', $wantIds)) error('This prescription was already sent (' . $r['prescription_no'] . ').', 409);
    }

    $pdo->beginTransaction();
    try {
        // Generate prescription number
        $lastRx = $pdo->query("SELECT prescription_no FROM pharmacy_prescriptions ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 1;
        if ($lastRx && preg_match('/(\d+)$/', $lastRx, $m)) $seq = (int)$m[1] + 1;
        $rxNo = 'RX-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        // Get consultation_id if exists
        $cStmt = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? LIMIT 1");
        $cStmt->execute([$visitId]);
        $consultId = $cStmt->fetchColumn() ?: null;

        $pdo->prepare("INSERT INTO pharmacy_prescriptions (prescription_no, visit_id, patient_id, doctor_id, consultation_id, diagnosis, notes, status, prescription_date, created_by, created_at) VALUES (?,?,?,?,?,?,?,'active',CURDATE(),?,NOW())")
            ->execute([$rxNo, $visitId, $patientId, $input['doctor_id'] ?? 0, $consultId, '', $notes, $_SESSION['user_id'] ?? null]);
        $rxId = (int)$pdo->lastInsertId();
        log_activity($pdo, 'CREATE', 'consultations', 'Pharmacy prescription created: ' . $rxNo . ' for visit #' . $visitId . ' (' . count($items) . ' items)', $rxId, $rxNo);

        // Insert items. Stock is a warning only (client decision): the prescription is always saved.
        $stockWarnings = [];
        $itemStmt = $pdo->prepare("INSERT INTO pharmacy_prescription_items (prescription_id, item_id, dosage, frequency, duration, quantity, instructions, status, destination) VALUES (?,?,?,?,?,?,?,'pending',?)");
        foreach ($items as $item) {
            if (($item['destination'] ?? 'hospital') !== 'external' && (int)($item['item_id'] ?? 0)) {
                $w = stock_warning($pdo, (int)$item['item_id'], (float)($item['quantity'] ?? 1), (string)($item['medication'] ?? ''));
                if ($w) $stockWarnings[] = $w;
            }
            $itemStmt->execute([
                $rxId,
                (int)($item['item_id'] ?? 0),
                $item['dosage'] ?? '',
                $item['frequency'] ?? '',
                $item['duration'] ?? '',
                (int)($item['quantity'] ?? 1),
                $item['medication'] ?? '',
                $item['destination'] ?? 'hospital'
            ]);
        }

        $pdo->commit();
        success(['prescription_id' => $rxId, 'prescription_no' => $rxNo, 'warnings' => $stockWarnings], 'Prescription created: ' . $rxNo);
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed: ' . $e->getMessage());
    }
}

// POST — lab order (create medical_orders)
if ($method === 'POST' && $action === 'lab_order') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $deptId = (int)($input['department_id'] ?? 0);
    $tests = $input['tests'] ?? $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');

    if (!$visitId || !$patientId) error('visit_id and patient_id required', 422);
    if (empty($tests)) error('At least one test required', 422);

    $labSpecialities = ['Bacteriology','BIOCHEMISTRY','Hematology','Immuno-Serology','SEROLOGY','Parasitology','Molecular','Anatomical Pathology','Blood Transfusion'];
    $chkStmt = $pdo->prepare("SELECT speciality, primary_health_facility, secondary_health_facility, tertiary_health_facility FROM service_catalog WHERE LOWER(TRIM(procedure_name)) = LOWER(TRIM(?)) LIMIT 1");
    foreach ($tests as $test) {
        $tName = trim($test['test_name'] ?? '');
        if ($tName) {
            $chkStmt->execute([$tName]);
            $catalog = $chkStmt->fetch();
            $spec = $catalog['speciality'] ?? null;
            if ($spec && !in_array($spec, $labSpecialities)) {
                error('"' . $tName . '" is listed under ' . $spec . ' and cannot be ordered as a laboratory test.', 422);
            }
        }
    }

    $pdo->beginTransaction();
    try {
        // Get consultation_id if exists
        $cStmt = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? LIMIT 1");
        $cStmt->execute([$visitId]);
        $consultId = $cStmt->fetchColumn() ?: null;

        $orderStmt = $pdo->prepare("INSERT INTO medical_orders (consultation_id, visit_id, patient_id, order_type, description, details, status, ordered_at, ordered_by) VALUES (?,?,?,?,?,?,'ordered',NOW(),?)");

        foreach ($tests as $test) {
            $orderStmt->execute([
                $consultId,
                $visitId,
                $patientId,
                'lab',
                $test['test_name'] ?? '',
                $notes . ($test['priority'] && $test['priority'] !== 'normal' ? ' [Priority: ' . $test['priority'] . ']' : ''),
                $_SESSION['user_id'] ?? null
            ]);
        }

        log_activity($pdo, 'CREATE', 'consultations', count($tests) . ' lab test(s) ordered for visit #' . $visitId . ': ' . implode(', ', array_column($tests, 'test_name')), $visitId, null);

        $pdo->commit();
        linkVisitOrders($pdo, $visitId);

        // Create notification for lab department
        try {
            $ptStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM patients WHERE id = ?");
            $ptStmt->execute([$patientId]);
            $ptName = $ptStmt->fetchColumn() ?: 'Patient';

            $testCount = count($tests);
            $testNames = array_map(function($t){ return $t['test_name'] ?? ''; }, $tests);
            $testList = implode(', ', array_slice($testNames, 0, 3));
            if($testCount > 3) $testList .= '...';

            $notifTitle = "New Lab Request";
            $notifMessage = "$testCount test(s) ordered for $ptName: $testList";

            $notifStmt = $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role) VALUES ('lab_request', ?, ?, ?, ?, 'lab')");
            $notifStmt->execute([$notifTitle, $notifMessage, $visitId, $patientId]);
        } catch (Exception $e) {
            // Non-critical — don't fail the order if notification fails
        }

        success(null, count($tests) . ' lab test(s) ordered');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed: ' . $e->getMessage());
    }
}

// POST — imaging order (create medical_orders)
if ($method === 'POST' && $action === 'imaging_order') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $deptId = (int)($input['department_id'] ?? 0);
    $items = $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');

    if (!$visitId || !$patientId) error('visit_id and patient_id required', 422);
    if (empty($items)) error('At least one imaging item required', 422);

    $pdo->beginTransaction();
    try {
        $cStmt = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? LIMIT 1");
        $cStmt->execute([$visitId]);
        $consultId = $cStmt->fetchColumn() ?: null;

        $orderStmt = $pdo->prepare("INSERT INTO medical_orders (consultation_id, visit_id, patient_id, order_type, description, details, status, ordered_at, ordered_by) VALUES (?,?,?,?,?,?,'ordered',NOW(),?)");

        foreach ($items as $it) {
            $desc = trim($it['name'] ?? 'Imaging');
            $detailParts = [];
            if (!empty($it['body_part'])) $detailParts[] = 'Body Part: ' . trim($it['body_part']);
            if (!empty($it['priority']) && $it['priority'] !== 'normal') $detailParts[] = 'Priority: ' . trim($it['priority']);
            if (!empty($it['notes'])) $detailParts[] = trim($it['notes']);
            if (!empty($notes) && empty($it['notes'])) $detailParts[] = $notes;
            $details = implode(' | ', $detailParts);

            $orderStmt->execute([
                $consultId,
                $visitId,
                $patientId,
                'radiology',
                $desc,
                $details,
                $_SESSION['user_id'] ?? null
            ]);
        }

        log_activity($pdo, 'CREATE', 'consultations', count($items) . ' imaging order(s) placed for visit #' . $visitId, $visitId, null);
        $pdo->commit();
        linkVisitOrders($pdo, $visitId);
        success(null, count($items) . ' imaging order(s) placed');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed: ' . $e->getMessage());
    }
}

if ($method === 'POST' && $action === 'dental_tooth_delete') {
    consult_ensure_schema($pdo);
    $in = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $vid = (int)($in['visit_id'] ?? 0); $pid = (int)($in['patient_id'] ?? 0); $rid = (int)($in['record_id'] ?? 0);
    if (!$vid || !$pid || !$rid) error('visit_id patient_id record_id required', 422);
    dental_require_visit($pdo, $vid);
    $pdo->prepare("DELETE FROM dental_tooth_records WHERE id=? AND patient_id=?")->execute([$rid, $pid]);
    log_activity($pdo, 'DELETE', 'consultations', "Dental chart: record #$rid removed", $vid, null);
    success(null, 'Removed');
}
if ($method === 'POST' && $action === 'dental_tooth_save') {
    consult_ensure_schema($pdo);
    $in = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $vid = (int)($in['visit_id'] ?? 0); $pid = (int)($in['patient_id'] ?? 0);
    $tooth = trim((string)($in['tooth'] ?? '')); $cond = (string)($in['cond'] ?? '');
    $surface = strtoupper(trim((string)($in['surface'] ?? '')));
    if (!$vid || !$pid) error('visit_id and patient_id required', 422);
    if (!preg_match('/^(1[1-8]|2[1-8]|3[1-8]|4[1-8]|5[1-5]|6[1-5]|7[1-5]|8[1-5])$/', $tooth)) error('Invalid tooth number', 422);
    if (!in_array($cond, $DENTAL_CONDS, true)) error('Invalid condition', 422);
    if (!preg_match('/^(|FULL|[MODBL]{1,5})$/', $surface)) error('Invalid surface', 422);
    dental_require_visit($pdo, $vid);
    $pdo->prepare("INSERT INTO dental_tooth_records (patient_id, visit_id, tooth, surface, cond, notes, recorded_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([$pid, $vid, $tooth, $surface, $cond, mb_substr(trim((string)($in['notes'] ?? '')), 0, 255) ?: null, $_SESSION['user_id'] ?? null]);
    log_activity($pdo, 'CREATE', 'consultations', "Dental chart: tooth $tooth $surface = $cond", $vid, null);
    success(null, 'Saved');
}
if ($method === 'POST' && $action === 'dental_plan_save') {
    consult_ensure_schema($pdo);
    $in = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $vid = (int)($in['visit_id'] ?? 0); $pid = (int)($in['patient_id'] ?? 0);
    $title = trim((string)($in['title'] ?? '')); $items = $in['items'] ?? [];
    if (!$vid || !$pid || $title === '') error('visit, patient and title required', 422);
    if (!is_array($items) || !$items) error('Add at least one item', 422);
    dental_require_visit($pdo, $vid);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO dental_treatment_plans (patient_id, visit_id, title, created_by) VALUES (?,?,?,?)")->execute([$pid, $vid, mb_substr($title, 0, 200), $_SESSION['user_id'] ?? null]);
        $planId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO dental_plan_items (plan_id, service_id, procedure_name, tooth, stage, est_price) VALUES (?,?,?,?,?,?)");
        $sv = $pdo->prepare("SELECT procedure_name FROM service_catalog WHERE id=? AND speciality LIKE 'Dentist%'");
        foreach ($items as $i) {
            $sid = (int)($i['service_id'] ?? 0);
            $sv->execute([$sid]); $name = $sv->fetchColumn();
            if (!$name) throw new Exception('Not a dental service');
            $ins->execute([$planId, $sid, $name, mb_substr(trim((string)($i['tooth'] ?? '')), 0, 40), max(1, (int)($i['stage'] ?? 1)), max(0, (float)($i['price'] ?? 0))]);
        }
        log_activity($pdo, 'CREATE', 'consultations', "Dental treatment plan \"$title\" (" . count($items) . ' items)', $vid, null);
        $pdo->commit();
        success(['id' => $planId], 'Plan saved');
    } catch (Exception $e) { $pdo->rollBack(); error($e->getMessage(), 422); }
}
if ($method === 'POST' && $action === 'dental_plan_update') {
    consult_ensure_schema($pdo);
    $in = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    dental_require_visit($pdo, (int)($in['visit_id'] ?? 0));
    $vid = (int)$in['visit_id'];
    if (!empty($in['item_id'])) {   // mark one item done / cancelled / planned
        $st = (string)($in['status'] ?? '');
        if (!in_array($st, ['planned','done','cancelled'], true)) error('Invalid status', 422);
        $q = $pdo->prepare("SELECT x.*, IF(x.invoice_item_id IS NULL, 'unbilled', IF(inv.status <> 'cancelled' AND inv.balance <= 0, 'paid', 'unpaid')) AS pay, sc.rhic
            FROM dental_plan_items x LEFT JOIN invoice_items ii ON ii.id = x.invoice_item_id LEFT JOIN invoices inv ON inv.id = ii.invoice_id LEFT JOIN service_catalog sc ON sc.id = x.service_id WHERE x.id=?");
        $q->execute([(int)$in['item_id']]);
        $it = $q->fetch(); if (!$it) error('Item not found', 404);
        $planId = (int)$it['plan_id'];
        if ($it['status'] === 'done') error('Already done', 422);
        $warn = [];
        if ($st === 'done') {
            // done = the treatment was given AND its invoice is paid
            if ($it['pay'] === 'unbilled') error('Bill this treatment first: Order now, Add to Billing List, Send to Billing.', 422);
            if ($it['pay'] !== 'paid') error('The invoice for this treatment is not paid yet.', 422);
            $pdo->beginTransaction();
            try {
                $cond = dental_chart_cond((string)$it['rhic']);
                if ($cond && preg_match('/^(1[1-8]|2[1-8]|3[1-8]|4[1-8]|5[1-5]|6[1-5]|7[1-5]|8[1-5])$/', trim((string)$it['tooth']))) {
                    $pdo->prepare("INSERT INTO dental_tooth_records (patient_id, visit_id, tooth, surface, cond, notes, recorded_by) VALUES ((SELECT patient_id FROM dental_treatment_plans WHERE id=?),?,?,'',?,?,?)")
                        ->execute([$planId, $vid, trim($it['tooth']), $cond, 'Auto: ' . $it['procedure_name'], $_SESSION['user_id'] ?? null]);
                }
                $warn = dental_consume_supplies($pdo, (int)$it['service_id'], $vid, (int)$it['id'], $it['procedure_name']);
                $pdo->prepare("UPDATE dental_plan_items SET status='done', done_visit_id=?, done_at=NOW() WHERE id=?")->execute([$vid, $it['id']]);
                $pdo->commit();
            } catch (Throwable $e) { $pdo->rollBack(); error('Could not complete: ' . $e->getMessage(), 500); }
        } else {
            $pdo->prepare("UPDATE dental_plan_items SET status=? WHERE id=?")->execute([$st, $it['id']]);
        }
        dental_sync_plan($pdo, $planId);
        success(['warnings' => $warn], 'Updated');
    }
    $st = (string)($in['status'] ?? '');   // plan status: approve / cancel
    if (!in_array($st, ['approved','cancelled'], true)) error('Invalid status', 422);
    $pdo->prepare("UPDATE dental_treatment_plans SET status=? WHERE id=? AND status IN ('proposed','approved')")->execute([$st, (int)($in['plan_id'] ?? 0)]);
    success(null, 'Updated');
}

// POST — doctor's decision on a repeated diagnosis: refer, transfer, or continue with a written justification
if ($method === 'POST' && $action === 'diagnosis_recurrence_flag') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $diag = trim($input['diagnosis'] ?? '');
    $act = $input['action_taken'] ?? '';
    $just = trim($input['justification'] ?? '');
    if (!$visitId || !$patientId || $diag === '') error('visit_id, patient_id and diagnosis required', 422);
    if (!in_array($act, ['referred', 'transferred', 'justified'], true)) error('Choose refer, transfer or continue', 422);
    if ($act === 'justified' && $just === '') error('A justification is required to continue treatment', 422);
    consult_ensure_schema($pdo);
    $rc = recurrence_count($pdo, $patientId, $diag, $visitId);
    $pdo->prepare("DELETE FROM diagnosis_recurrence_flags WHERE visit_id=? AND diagnosis=?")->execute([$visitId, $diag]);
    $pdo->prepare("INSERT INTO diagnosis_recurrence_flags (patient_id, visit_id, diagnosis, occurrences, window_days, action, justification, user_id) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$patientId, $visitId, $diag, $rc['count'], $rc['window_days'], $act, $just !== '' ? $just : null, $_SESSION['user_id'] ?? null]);
    consult_event($pdo, $visitId, 'Repeated diagnosis: ' . $act, $diag . ' (' . $rc['count'] . ' in ' . $rc['window_days'] . ' days)' . ($just !== '' ? ' - ' . $just : ''));
    success(['occurrences' => $rc['count']], 'Decision saved');
}

// POST — save imaging result
if ($method === 'POST' && $action === 'imaging_result') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $orderId = (int)($input['order_id'] ?? 0);
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $imagingName = trim($input['imaging_name'] ?? '');
    $status = $input['status'] ?? 'completed';
    $resultData = $input['result'] ?? null;
    // Attachments may only point at files this handler stored
    if (is_array($resultData) && isset($resultData['attachments'])) {
        $resultData['attachments'] = array_values(array_filter((array)$resultData['attachments'], function ($a) {
            return is_array($a) && preg_match('#^uploads/imaging/[\w.\-]+$#', (string)($a['path'] ?? ''));
        }));
    }
    $resultJson = is_array($resultData) ? json_encode($resultData, JSON_UNESCAPED_UNICODE) : trim((string)$resultData);
    $completedBy = $_SESSION['user_id'] ?? null;

    if (!$resultJson) error('Result data required', 422);

    if ($status === 'completed') {
        $xDesc = $imagingName;
        if ($orderId) { $dq = $pdo->prepare("SELECT description FROM medical_orders WHERE id=?"); $dq->execute([$orderId]); $xDesc = (string)$dq->fetchColumn(); }
        if (consult_is_xray($xDesc) && !consult_result_has_attachment($resultData)) error('An X-ray image must be attached', 422);
    }

    if (!$orderId && $visitId) {
        $chk = $pdo->prepare("SELECT id FROM medical_orders WHERE visit_id = ? AND order_type = 'radiology' AND description LIKE ? ORDER BY id DESC LIMIT 1");
        $chk->execute([$visitId, '%' . $imagingName . '%']);
        $orderId = (int)$chk->fetchColumn();
        if (!$orderId) {
            $ins = $pdo->prepare("INSERT INTO medical_orders (visit_id, patient_id, order_type, description, details, status, ordered_at, ordered_by) VALUES (?, ?, 'radiology', ?, 'Imaging order', 'ordered', NOW(), ?)");
            $ins->execute([$visitId, $patientId, $imagingName ?: 'Ultrasound Examination', $completedBy]);
            $orderId = (int)$pdo->lastInsertId();
        }
    }

    if (!$orderId) error('order_id required', 422);

    // One transaction = one disk sync instead of one per write (the save felt laggy). Schema checks first: DDL would commit it.
    al_schema($pdo);
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE medical_orders SET result = ?, status = ?, completed_at = NOW(), completed_by = ? WHERE id = ?");
    $stmt->execute([$resultJson, $status, $completedBy, $orderId]);

    log_activity($pdo, 'UPDATE', 'consultations', 'Imaging result recorded for order #' . $orderId, $visitId, null);
    if ($visitId) consult_event($pdo, $visitId, 'Imaging result recorded', 'Order #' . $orderId);

    try {
        $notifStmt = $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role) VALUES ('imaging_result', 'Imaging Result Ready', ?, ?, ?, 'doctor')");
        $notifStmt->execute(["Imaging report completed for order #$orderId", $visitId, $patientId]);
    } catch(Exception $e) {}
    $pdo->commit();

    success(['order_id' => $orderId, 'status' => $status], 'Imaging result saved successfully');
}

// POST — upload an imaging scan or diagnostic attachment.
// The returned metadata is stored inside the imaging result JSON when the
// radiology report is finalized, so no additional database table is required.
if ($method === 'POST' && $action === 'upload_imaging_attachment') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        error('File upload failed', 400);
    }

    $file = $_FILES['file'];
    if ((int)$file['size'] > 30 * 1024 * 1024) {
        error('File size exceeds the 30MB limit', 422);
    }

    $originalName = (string)$file['name'];
    // The stored extension comes from the detected content (includes/upload_safe.php), never from the client's file name.
    $extension = upload_ext_from_mime($file['tmp_name']);
    $isDicom = false;
    if ($extension === null) {
        // DICOM is not in the safe map: accept it only by its "DICM" marker at byte 128
        $fh = @fopen($file['tmp_name'], 'rb');
        $isDicom = $fh && fseek($fh, 128) === 0 && fread($fh, 4) === 'DICM';
        if ($fh) fclose($fh);
        if (!$isDicom) error('Unsupported file type. Allowed: images (JPG, PNG, GIF), PDF, Word, DICOM', 422);
        $extension = 'dcm';
    }
    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $isDicom ? 'application/dicom' : (string)finfo_file($fi, $file['tmp_name']);

    $uploadDir = __DIR__ . '/../../uploads/imaging/';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
        error('Unable to create the imaging upload directory', 500);
    }
    if (!is_writable($uploadDir)) error('The imaging upload directory is not writable', 500);

    try {
        $uniqueName = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    } catch (Throwable $e) {
        $uniqueName = 'img_' . date('Ymd_His') . '_' . uniqid('', true) . '.' . $extension;
    }
    $target = $uploadDir . $uniqueName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        error('Failed to save the imaging attachment', 500);
    }

    success([
        'name' => $originalName,
        'path' => 'uploads/imaging/' . $uniqueName,
        'size' => (int)$file['size'],
        'type' => $mime,
        'uploaded_at' => date('Y-m-d H:i:s')
    ], 'Imaging attachment uploaded successfully');
}

// POST — create/update consultation
if ($method === 'POST' && (!$action || $action === 'complete')) {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];

    // If action is 'complete', require final_diagnosis, set status to completed and map fields
    if ($action === 'complete') {
        $finalDiag = trim($input['final_diagnosis'] ?? $input['diagnosis'] ?? '');
        if ($finalDiag === '') {
            error("Final Diagnosis is required to complete consultation", 422);
        }
        $input['status'] = 'completed';
        $input['diagnosis'] = $finalDiag;
        // Convert prescription array to JSON string for storage
        if (isset($input['prescription']) && is_array($input['prescription'])) {
            $input['prescription'] = json_encode($input['prescription']);
        }
    }
    $input = fillMissingDeptId($pdo, $input);
    foreach (['visit_id','patient_id','department_id'] as $f) {
        if (empty($input[$f])) error("Field '$f' required", 422);
    }

    // Dental visit: every X-ray ordered for it must have an image attached before the consultation can be completed
    if ($action === 'complete') {
        $dn = $pdo->prepare("SELECT name FROM departments WHERE id=?");
        $dn->execute([(int)$input['department_id']]);
        if (stripos((string)$dn->fetchColumn(), 'dent') !== false) {
            $xo = $pdo->prepare("SELECT description, status, result FROM medical_orders WHERE visit_id=? AND order_type='radiology' AND status<>'cancelled'");
            $xo->execute([(int)$input['visit_id']]);
            foreach ($xo->fetchAll() as $o) {
                if (consult_is_xray($o['description']) && !($o['status'] === 'completed' && consult_result_has_attachment($o['result']))) {
                    error('An X-ray image must be attached: "' . $o['description'] . '" has no image yet. Upload it in Imaging before completing.', 422);
                }
            }
        }
    }

    // Check existing
    $chk = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? AND department_id=?");
    $chk->execute([(int)$input['visit_id'], (int)$input['department_id']]);
    $existing = $chk->fetchColumn();
    $ox = $pdo->prepare("SELECT diagnosis, prescription FROM consultations WHERE visit_id=? AND department_id=?");
    $ox->execute([(int)$input['visit_id'], (int)$input['department_id']]);
    $old = $ox->fetch() ?: ['diagnosis' => '', 'prescription' => ''];
    consult_pay_lock($pdo, (int)$input['visit_id'], array_key_exists('diagnosis', $input) ? (string)$input['diagnosis'] : null,
        array_key_exists('prescription', $input) ? $input['prescription'] : null, (string)$old['diagnosis'], $old['prescription']);

    // A signed (completed) consultation is a clinical record: it is never signed again or overwritten from here
    if ($action === 'complete' && $existing) {
        $stc = $pdo->prepare("SELECT status FROM consultations WHERE id=?");
        $stc->execute([$existing]);
        if ($stc->fetchColumn() === 'completed') error('This consultation is already signed. Ask for an approved correction (edit request) or reopen it.', 409);
    }
    // A diagnosis typed freely (not in the diagnosis list, no code given) is kept but marked UNCLASSIFIED for reports
    if ($action === 'complete' && trim((string)($input['icd_code'] ?? '')) === '') {
        $dq = $pdo->prepare("SELECT code FROM diagnoses WHERE status='active' AND (LOWER(TRIM(name)) = LOWER(TRIM(?)) OR LOWER(TRIM(?)) LIKE CONCAT(LOWER(code), '%')) LIMIT 1");
        $dq->execute([$input['diagnosis'], $input['diagnosis']]);
        $input['icd_code'] = $dq->fetchColumn() ?: 'UNCLASSIFIED';
    }

    $fields = [
        'visit_id','patient_id','department_id','doctor_id','doctor_name',
        'bp_systolic','bp_diastolic','pulse','temperature','weight','height','oxygen_sat','respiratory_rate',
        'chief_complaint','history','examination','differential_diagnosis','diagnosis','icd_code','treatment_plan','prescription','notes',
        'outcome','referred_to_dept','admit_ward','follow_up_date','status','created_by',
        'step1_by','step1_at','step1_name',
        'step3_by','step3_at','step3_name',
        'completed_by','completed_at','completed_name'
    ];

    // Resolve audit user
    $auditUserId   = $_SESSION['user_id'] ?? null;
    $auditUserName = $input['doctor_name'] ?? null;
    if (!$auditUserName && $auditUserId) {
        $uStmt = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=?");
        $uStmt->execute([$auditUserId]);
        $auditUserName = $uStmt->fetchColumn() ?: ('User #' . $auditUserId);
    }

    // Stamp audit fields based on action
    if ($action === 'complete') {
        $input['completed_by']   = $auditUserId;
        $input['completed_at']   = date('Y-m-d H:i:s');
        $input['completed_name'] = $auditUserName;
        if (empty($input['step3_by'])) {
            $input['step3_by']   = $auditUserId;
            $input['step3_at']   = date('Y-m-d H:i:s');
            $input['step3_name'] = $auditUserName;
        }
    }

    if ($existing) {
        // Update
        $sets = []; $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f,$input) && !in_array($f,['visit_id','patient_id','department_id','created_by'])) {
                $sets[] = $f === 'doctor_id' ? "$f=COALESCE($f,?)" : ($f === 'doctor_name' ? "$f=COALESCE(NULLIF($f,''),?)" : "$f=?"); $params[] = $input[$f] === '' ? null : $input[$f];
            }
        }
        if ($sets) { $params[] = $existing; $pdo->prepare("UPDATE consultations SET ".implode(',',$sets)." WHERE id=?")->execute($params); }
        $cid = $existing;
    } else {
        // Insert
        $cols = []; $vals = []; $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f,$input)) { $cols[] = $f; $vals[] = '?'; $params[] = $input[$f] === '' ? null : $input[$f]; }
        }
        $pdo->prepare("INSERT INTO consultations (".implode(',',$cols).") VALUES (".implode(',',$vals).")")->execute($params);
        $cid = (int)$pdo->lastInsertId();
    }
    consult_event($pdo, (int)$input['visit_id'], $action === 'complete' ? 'Consultation completed' : ($existing ? 'Consultation updated' : 'Consultation started'), '', (int)$cid);

    // If completed, update visit status
    if (($input['status'] ?? '') === 'completed') {
        $outcome = $input['outcome'] ?? null;
        $vStatus = match($outcome) {
            'admitted'  => 'in_progress',
            'referred'  => 'in_progress',
            'discharged','treated','follow_up' => 'completed',
            default     => 'in_progress'
        };
        $pdo->prepare("UPDATE visits SET status=?, consultation_status='done' WHERE id=?")->execute([$vStatus, (int)$input['visit_id']]);
        // Mark queue done
        $pdo->prepare("UPDATE patient_queue SET status='done', done_at=NOW() WHERE visit_id=? AND department_id=?")
            ->execute([(int)$input['visit_id'], (int)$input['department_id']]);
    }

    $fetchSt = $pdo->prepare("SELECT c.*, d.name AS dept_name FROM consultations c LEFT JOIN departments d ON d.id=c.department_id WHERE c.id=?");
    $fetchSt->execute([$cid]);
    $c = $fetchSt->fetch();
    $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id=? ORDER BY ordered_at");
    $oStmt->execute([$cid]);
    $c['orders'] = $oStmt->fetchAll();
    success($c, $existing ? 'Consultation updated' : 'Consultation created', 201);
}

// POST orders via ?action=order
if ($method === 'POST' && $action === 'order') {
    // handled below — unreachable, merged into POST
}

// PUT — add medical order
if ($method === 'PUT' && $action === 'order') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach (['consultation_id','visit_id','patient_id','order_type','description'] as $f) {
        if (empty($input[$f])) error("Field '$f' required", 422);
    }
    $pdo->prepare("INSERT INTO medical_orders (consultation_id,visit_id,patient_id,order_type,description,details,ordered_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([(int)$input['consultation_id'],(int)$input['visit_id'],(int)$input['patient_id'],$input['order_type'],trim($input['description']),trim($input['details']??''),$input['ordered_by']??null]);
    success(['id'=>(int)$pdo->lastInsertId()], 'Order added');
}

// DELETE order
if ($method === 'DELETE') {
    if (!$id) error('ID required', 422);
    $action2 = $_GET['action'] ?? '';
    if ($action2 === 'order') {
        $lockStmt = $pdo->prepare('SELECT visit_id FROM medical_orders WHERE id = ?');
        $lockStmt->execute([$id]);
        $orderVisit = (int)$lockStmt->fetchColumn();
        if ($orderVisit) record_require_unlocked($pdo, 'visit', $orderVisit);
        record_require_unlocked($pdo, 'lab_order', $id);
        $pdo->prepare("DELETE FROM medical_orders WHERE id=?")->execute([$id]);
    } else {
        $lockStmt = $pdo->prepare('SELECT visit_id FROM consultations WHERE id = ?');
        $lockStmt->execute([$id]);
        $consultVisit = (int)$lockStmt->fetchColumn();
        if ($consultVisit) record_require_unlocked($pdo, 'visit', $consultVisit);
        $pdo->prepare("DELETE FROM consultations WHERE id=?")->execute([$id]);
    }
    success(null, 'Deleted');
}

// ===== SCHEDULE TIMETABLE =====
if ($method === 'GET' && $action === 'schedule') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS nursing_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        visit_id INT,
        schedule_type VARCHAR(50) DEFAULT 'medication',
        description VARCHAR(255) NOT NULL,
        frequency VARCHAR(50),
        scheduled_times JSON,
        administered_times JSON,
        start_date DATE NOT NULL,
        end_date DATE,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->prepare("
        SELECT ns.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name
        FROM nursing_schedule ns
        JOIN patients p ON p.id=ns.patient_id
        WHERE ns.start_date <= ? AND (ns.end_date IS NULL OR ns.end_date >= ?)
        ORDER BY ns.patient_id, ns.schedule_type, ns.description
    ");
    $stmt->execute([$date, $date]);
    $schedules = $stmt->fetchAll();
    
    // For each schedule, get today's administered times
    foreach ($schedules as &$s) {
        $admStmt = $pdo->prepare("SELECT administered_at FROM nursing_schedule_log WHERE schedule_id=? AND DATE(administered_at)=?");
        $admStmt->execute([$s['id'], $date]);
        $admTimes = $admStmt->fetchAll(PDO::FETCH_COLUMN);
        $s['administered_times'] = json_encode(array_map(function($t){return date('H:i',strtotime($t));}, $admTimes));
    }
    success($schedules);
}

if ($method === 'POST' && $action === 'schedule_save') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $patientId = (int)($input['patient_id'] ?? 0);
    $visitId = !empty($input['visit_id']) ? (int)$input['visit_id'] : null;
    $type = $input['schedule_type'] ?? 'medication';
    $desc = trim($input['description'] ?? '');
    $freq = $input['frequency'] ?? 'once';
    $times = $input['scheduled_times'] ?? '["08:00"]';
    $startDate = $input['start_date'] ?? date('Y-m-d');
    $endDate = !empty($input['end_date']) ? $input['end_date'] : null;
    $notes = $input['notes'] ?? '';
    
    if (!$patientId || !$desc) error('Patient and description required', 422);
    
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS nursing_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        visit_id INT,
        schedule_type VARCHAR(50) DEFAULT 'medication',
        description VARCHAR(255) NOT NULL,
        frequency VARCHAR(50),
        scheduled_times JSON,
        administered_times JSON,
        start_date DATE NOT NULL,
        end_date DATE,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->prepare("INSERT INTO nursing_schedule (patient_id, visit_id, schedule_type, description, frequency, scheduled_times, start_date, end_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$patientId, $visitId, $type, $desc, $freq, $times, $startDate, $endDate, $notes, $_SESSION['user_id'] ?? null]);
    success(['id' => (int)$pdo->lastInsertId()], 'Schedule created');
}

if ($method === 'POST' && $action === 'schedule_administer') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $schedId = (int)($input['schedule_id'] ?? 0);
    $timeSlot = $input['time_slot'] ?? '';
    if (!$schedId) error('schedule_id required', 422);
    
    // Ensure log table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS nursing_schedule_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        administered_at DATETIME NOT NULL,
        administered_by INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Record administration
    $adminTime = date('Y-m-d') . ' ' . ($timeSlot ?: date('H:i')) . ':00';
    $pdo->prepare("INSERT INTO nursing_schedule_log (schedule_id, administered_at, administered_by) VALUES (?,?,?)")
        ->execute([$schedId, $adminTime, $_SESSION['user_id'] ?? null]);
    success(null, 'Marked as administered');
}

// ============================================================
// EDIT CONSULTATION ORDER ITEM (When visit is not completed)
// ============================================================
if ($method === 'POST' && $action === 'edit_order_item') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $itemType = $input['item_type'] ?? '';
    $itemId = (int)($input['item_id'] ?? 0);
    $newQty = max(1, (float)($input['quantity'] ?? 1));
    $dosage = trim($input['dosage'] ?? '');
    $frequency = trim($input['frequency'] ?? '');
    $duration = trim($input['duration'] ?? '');
    $notes = trim($input['notes'] ?? '');

    if (!$visitId || !$itemId || !$itemType) error('visit_id, item_type, and item_id required', 422);

    $vStmt = $pdo->prepare("SELECT status FROM visits WHERE id = ?");
    $vStmt->execute([$visitId]);
    $visitStatus = $vStmt->fetchColumn();
    if (!$visitStatus) error('Visit not found', 404);
    if ($visitStatus === 'completed') error('Cannot edit item: this visit is already completed.', 403);

    $pdo->beginTransaction();
    try {
        if ($itemType === 'pharmacy') {
            $piStmt = $pdo->prepare("SELECT pi.*, p.visit_id, i.name AS item_name FROM pharmacy_prescription_items pi JOIN pharmacy_prescriptions p ON p.id = pi.prescription_id LEFT JOIN items i ON i.id = pi.item_id WHERE pi.id = ?");
            $piStmt->execute([$itemId]);
            $pi = $piStmt->fetch();
            if (!$pi) error('Prescription item not found', 404);

            $updateFields = ["quantity = ?"];
            $params = [$newQty];
            if ($dosage) { $updateFields[] = "dosage = ?"; $params[] = $dosage; }
            if ($frequency) { $updateFields[] = "frequency = ?"; $params[] = $frequency; }
            if ($duration) { $updateFields[] = "duration = ?"; $params[] = $duration; }
            if ($notes) { $updateFields[] = "instructions = ?"; $params[] = $notes; }
            $params[] = $itemId;
            $pdo->prepare("UPDATE pharmacy_prescription_items SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($params);

            $medName = $pi['item_name'] ?: $pi['instructions'];
            $invItemStmt = $pdo->prepare("
                SELECT ii.*, i.id AS invoice_id
                FROM invoice_items ii
                JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.visit_id = ? AND ii.category IN ('medication', 'pharmacy', 'drug')
                  AND (ii.service_id = ? OR ii.description LIKE ?)
                ORDER BY ii.id DESC LIMIT 1
            ");
            $invItemStmt->execute([$visitId, (int)$pi['item_id'], '%' . $medName . '%']);
            $invItem = $invItemStmt->fetch();
            if ($invItem) {
                $unitPrice = (float)$invItem['unit_price'];
                $discount = (float)$invItem['discount'];
                $newTotal = max(0, ($newQty * $unitPrice) - $discount);
                $pdo->prepare("UPDATE invoice_items SET quantity = ?, total = ? WHERE id = ?")
                    ->execute([(int)$newQty, $newTotal, (int)$invItem['id']]);
                syncInvoiceAndJournal($pdo, (int)$invItem['invoice_id']);
            }
            log_activity($pdo, 'UPDATE', 'consultations', 'Edited prescription item #' . $itemId . ' quantity to ' . $newQty . ' for visit #' . $visitId, $visitId, null);
        } elseif ($itemType === 'lab') {
            $pdo->prepare("UPDATE medical_orders SET details = ? WHERE id = ? AND visit_id = ?")
                ->execute([$notes, $itemId, $visitId]);
            log_activity($pdo, 'UPDATE', 'consultations', 'Edited lab order #' . $itemId . ' for visit #' . $visitId, $visitId, null);
        } elseif ($itemType === 'procedure' || $itemType === 'imaging') {
            $iiStmt = $pdo->prepare("SELECT ii.*, i.id AS invoice_id FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE ii.id = ? AND i.visit_id = ?");
            $iiStmt->execute([$itemId, $visitId]);
            $invItem = $iiStmt->fetch();
            if (!$invItem) error('Invoice item not found', 404);

            $unitPrice = (float)$invItem['unit_price'];
            $discount = (float)$invItem['discount'];
            $newTotal = max(0, ($newQty * $unitPrice) - $discount);
            $pdo->prepare("UPDATE invoice_items SET quantity = ?, total = ? WHERE id = ?")
                ->execute([(int)$newQty, $newTotal, $itemId]);
            syncInvoiceAndJournal($pdo, (int)$invItem['invoice_id']);
            log_activity($pdo, 'UPDATE', 'consultations', 'Edited ' . $itemType . ' item #' . $itemId . ' quantity to ' . $newQty . ' for visit #' . $visitId, $visitId, null);
        }

        $pdo->commit();
        success(null, 'Item updated successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to update item: ' . $e->getMessage());
    }
}

// ============================================================
// DELETE CONSULTATION ORDER ITEM (When visit is not completed)
// ============================================================
if ($method === 'POST' && $action === 'delete_order_item') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $itemType = $input['item_type'] ?? '';
    $itemId = (int)($input['item_id'] ?? 0);

    if (!$visitId || !$itemId || !$itemType) error('visit_id, item_type, and item_id required', 422);

    $vStmt = $pdo->prepare("SELECT status FROM visits WHERE id = ?");
    $vStmt->execute([$visitId]);
    $visitStatus = $vStmt->fetchColumn();
    if (!$visitStatus) error('Visit not found', 404);
    if ($visitStatus === 'completed') error('Cannot delete items: this visit is already completed.', 403);
    record_require_unlocked($pdo, 'visit', $visitId);

    $pdo->beginTransaction();
    try {
        if ($itemType === 'pharmacy') {
            $piStmt = $pdo->prepare("SELECT pi.*, p.visit_id, i.name AS item_name FROM pharmacy_prescription_items pi JOIN pharmacy_prescriptions p ON p.id = pi.prescription_id LEFT JOIN items i ON i.id = pi.item_id WHERE pi.id = ?");
            $piStmt->execute([$itemId]);
            $pi = $piStmt->fetch();
            if (!$pi) error('Prescription item not found', 404);

            $rxId = (int)$pi['prescription_id'];
            $medName = $pi['item_name'] ?: $pi['instructions'];
            if (($pi['status'] ?? '') === 'dispensed') error('Dispensed medicines cannot be deleted; use a return or correction workflow', 409);

            $pdo->prepare("DELETE FROM pharmacy_prescription_items WHERE id = ?")->execute([$itemId]);

            $chkRx = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_prescription_items WHERE prescription_id = ?");
            $chkRx->execute([$rxId]);
            if ((int)$chkRx->fetchColumn() === 0) {
                $pdo->prepare("DELETE FROM pharmacy_prescriptions WHERE id = ?")->execute([$rxId]);
            }

            $invItemStmt = $pdo->prepare("
                SELECT ii.id, ii.invoice_id
                FROM invoice_items ii
                JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.visit_id = ? AND ii.category IN ('medication', 'pharmacy', 'drug')
                  AND (ii.service_id = ? OR ii.description LIKE ?)
                ORDER BY ii.id DESC LIMIT 1
            ");
            $invItemStmt->execute([$visitId, (int)$pi['item_id'], '%' . $medName . '%']);
            $invItem = $invItemStmt->fetch();
            if ($invItem) {
                $invId = (int)$invItem['invoice_id'];
                record_require_unlocked($pdo, 'invoice', $invId);
                $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([(int)$invItem['id']]);
                syncInvoiceAndJournal($pdo, $invId);
            }
            log_activity($pdo, 'DELETE', 'consultations', 'Deleted prescription item: ' . $medName . ' for visit #' . $visitId, $visitId, null);
        } elseif ($itemType === 'lab') {
            $moStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE id = ? AND visit_id = ?");
            $moStmt->execute([$itemId, $visitId]);
            $mo = $moStmt->fetch();
            if (!$mo) error('Lab order not found', 404);

            $testName = $mo['description'];
            $pdo->prepare("DELETE FROM medical_orders WHERE id = ?")->execute([$itemId]);

            $invItemStmt = $pdo->prepare("
                SELECT ii.id, ii.invoice_id
                FROM invoice_items ii
                JOIN invoices i ON i.id = ii.invoice_id
                WHERE i.visit_id = ? AND ii.category = 'lab'
                  AND (ii.description LIKE ? OR ii.description LIKE ?)
                ORDER BY ii.id DESC LIMIT 1
            ");
            $invItemStmt->execute([$visitId, '%' . $testName . '%', $testName]);
            $invItem = $invItemStmt->fetch();
            if ($invItem) {
                $invId = (int)$invItem['invoice_id'];
                record_require_unlocked($pdo, 'invoice', $invId);
                $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([(int)$invItem['id']]);
                syncInvoiceAndJournal($pdo, $invId);
            }
            log_activity($pdo, 'DELETE', 'consultations', 'Deleted lab order: ' . $testName . ' for visit #' . $visitId, $visitId, null);
        } elseif ($itemType === 'procedure' || $itemType === 'imaging') {
            $iiStmt = $pdo->prepare("SELECT ii.*, i.id AS invoice_id FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE ii.id = ? AND i.visit_id = ?");
            $iiStmt->execute([$itemId, $visitId]);
            $invItem = $iiStmt->fetch();
            if (!$invItem) error('Invoice item not found', 404);

            $invId = (int)$invItem['invoice_id'];
            $desc = $invItem['description'];
            record_require_unlocked($pdo, 'invoice', $invId);
            $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$itemId]);

            if ($itemType === 'imaging') {
                $pdo->prepare("DELETE FROM medical_orders WHERE visit_id = ? AND order_type = 'radiology' AND description LIKE ? LIMIT 1")
                    ->execute([$visitId, '%' . $desc . '%']);
            }

            syncInvoiceAndJournal($pdo, $invId);
            log_activity($pdo, 'DELETE', 'consultations', 'Deleted ' . $itemType . ' item: ' . $desc . ' for visit #' . $visitId, $visitId, null);
        }

        $pdo->commit();
        success(null, 'Item deleted successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to delete item: ' . $e->getMessage());
    }
}

// POST — doctor fills vital signs the nurse left empty (insert only; existing values need an approved correction)
if ($method === 'POST' && $action === 'add_vitals') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$visitId || !$patientId || empty($input['readings'])) error('visit_id, patient_id and readings required', 422);
    vital_check_readings($pdo, (array)$input['readings']);
    $exists = $pdo->prepare("SELECT 1 FROM vital_signs_records WHERE visit_id = ? AND vital_sign_id = ? LIMIT 1");
    $name = $pdo->prepare("SELECT name FROM vital_signs_settings WHERE id = ? AND status = 'active'");
    $ins = $pdo->prepare("INSERT INTO vital_signs_records (visit_id, patient_id, vital_sign_id, value, recorded_by) VALUES (?,?,?,?,?)");
    $added = [];
    foreach ((array)$input['readings'] as $r) {
        $sid = (int)($r['vital_sign_id'] ?? 0);
        $val = trim((string)($r['value'] ?? ''));
        if (!$sid || $val === '' || strlen($val) > 100) continue;
        $exists->execute([$visitId, $sid]);
        if ($exists->fetchColumn()) continue;
        $name->execute([$sid]);
        if (!($n = $name->fetchColumn())) continue;
        $ins->execute([$visitId, $patientId, $sid, $val, $uid ?: null]);
        $added[] = $n . ': ' . $val;
    }
    if (!$added) error('Nothing to save (values already recorded need an approved correction)', 422);
    log_activity($pdo, 'CREATE', 'consultations', 'Vital signs added by doctor for visit #' . $visitId . ' | ' . implode(', ', $added), $visitId, null);
    consult_event($pdo, $visitId, 'Vital signs added', implode(', ', $added));
    success(['added' => count($added)], 'Vital signs saved');
}

// POST — another doctor opens a file (e.g. to read results and finish it): record them once per visit
if ($method === 'POST' && $action === 'touch') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$visitId || !$uid) error('visit_id required', 422);
    $seen = $pdo->prepare("SELECT 1 FROM consultation_events WHERE visit_id = ? AND user_id = ? LIMIT 1");
    $seen->execute([$visitId, $uid]);
    if (!$seen->fetchColumn()) consult_event($pdo, $visitId, 'Opened the file');
    success(null);
}

// POST — apply an admin-approved correction (diagnosis, lab/imaging order result, vital sign).
// The approved edit_request is consumed; before/after goes to edit_audit_log and activity_log.
if ($method === 'POST' && $action === 'edit_record') {
    require_once __DIR__ . '/../../includes/edit_audit_schema.php';
    edit_audit_boot($pdo);
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $type = $input['entity_type'] ?? '';
    $eid = (int)($input['entity_id'] ?? 0);
    $reqId = (int)($input['edit_request_id'] ?? 0);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!in_array($type, ['consult_diagnosis', 'consult_order', 'consult_vital'], true) || !$eid || !$reqId) error('entity_type, entity_id and edit_request_id required', 422);

    $erStmt = $pdo->prepare("SELECT er.*, TRIM(CONCAT(u.first_name,' ',u.last_name)) AS reviewer FROM edit_requests er LEFT JOIN users u ON u.id = er.reviewed_by WHERE er.id = ? AND er.entity_type = ? AND er.entity_id = ? AND er.status = 'approved'");
    $erStmt->execute([$reqId, $type, $eid]);
    $er = $erStmt->fetch();
    if (!$er) error('Edit request is not approved', 403);
    if ($uid && (int)$er['requested_by'] !== $uid) error('Only the requester can apply this approved edit', 403);

    if ($type === 'consult_diagnosis') {
        $st = $pdo->prepare("SELECT id, visit_id, diagnosis, differential_diagnosis FROM consultations WHERE id = ?");
        $table = 'consultations'; $fields = ['diagnosis', 'differential_diagnosis'];
        $label = 'Diagnosis';
    } elseif ($type === 'consult_order') {
        $st = $pdo->prepare("SELECT id, visit_id, description, result, details FROM medical_orders WHERE id = ?");
        $table = 'medical_orders'; $fields = ['result', 'details'];
        $label = 'Order';
    } else {
        $st = $pdo->prepare("SELECT vr.id, vr.visit_id, vr.value, vs.name AS sign_name FROM vital_signs_records vr JOIN vital_signs_settings vs ON vs.id = vr.vital_sign_id WHERE vr.id = ?");
        $table = 'vital_signs_records'; $fields = ['value'];
        $label = 'Vital sign';
    }
    $st->execute([$eid]);
    $before = $st->fetch();
    if (!$before) error('Record not found', 404);

    $sets = []; $params = []; $changes = []; $after = $before;
    foreach ($fields as $f) {
        if (!array_key_exists($f, $input)) continue;
        $new = trim((string)$input[$f]);
        if ($new === trim((string)$before[$f])) continue;
        if ($type === 'consult_diagnosis' && $f === 'diagnosis' && $new === '') error('Diagnosis cannot be empty', 422);
        if ($type === 'consult_vital' && ($new === '' || strlen($new) > 100)) error('Invalid value', 422);
        $sets[] = "$f = ?"; $params[] = $new === '' ? null : $new;
        $changes[] = $f . ': "' . $before[$f] . '" → "' . $new . '"';
        $after[$f] = $new;
    }
    if (!$sets) error('No changes to save', 422);

    $visitId = (int)$before['visit_id'];
    $name = $label . ($type === 'consult_vital' ? ' ' . $before['sign_name'] : ($type === 'consult_order' ? ' ' . $before['description'] : '')) . ' — visit #' . $visitId;
    $pdo->beginTransaction();
    try {
        $params[] = $eid;
        $pdo->prepare("UPDATE $table SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        $pdo->prepare("INSERT INTO edit_audit_log (entity_type, entity_id, entity_name, edit_request_id, data_before, data_after, changed_fields, edited_by, reason) VALUES (?,?,?,?,?,?,?,?,?)")
            ->execute([$type, $eid, $name, $reqId, json_encode($before), json_encode($after), implode("\n", $changes), $uid ?: null,
                substr('Approved edit request #' . $reqId . ' (approved by ' . ($er['reviewer'] ?: 'admin') . '): ' . $er['reason'], 0, 255)]);
        $pdo->prepare("DELETE FROM edit_requests WHERE id = ?")->execute([$reqId]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to apply edit: ' . $e->getMessage());
    }
    log_activity($pdo, 'UPDATE', 'consultations', 'Approved correction (' . $name . ') | ' . implode(' | ', $changes), $visitId, null);
    consult_event($pdo, $visitId, 'Correction applied: ' . $name, implode(' | ', $changes));
    success(null, 'Correction saved and logged');
}

error('Method not allowed', 405);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('DB error: '.$e->getMessage(), 500);
}
?>
