<?php
// handlers/finance/invoices-enhanced.php
// Enhanced invoice generation with consultation fee integration

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
 * Get current consultation fee for a department and type
 */
function getConsultationFee(PDO $pdo, int $deptId, string $type = 'initial'): ?array {
    $stmt = $pdo->prepare("
        SELECT cf.* 
        FROM consultation_fees cf
        WHERE cf.department_id = ? 
          AND cf.consultation_type = ? 
          AND cf.is_active = 1
          AND cf.effective_from <= CURDATE()
          AND (cf.effective_to IS NULL OR cf.effective_to >= CURDATE())
        ORDER BY cf.effective_from DESC
        LIMIT 1
    ");
    $stmt->execute([$deptId, $type]);
    return $stmt->fetch() ?: null;
}

/**
 * Get consultation fee for a patient (insurance vs self-pay)
 */
function getApplicableConsultationFee(PDO $pdo, int $consultationId, int $deptId, string $paymentType = 'self_pay'): float {
    $fee = getConsultationFee($pdo, $deptId, 'initial');
    if (!$fee) return 0;

    // Use insurance fee if payment is through insurance
    if ($paymentType === 'insurance' && $fee['insurance_fee']) {
        return (float)$fee['insurance_fee'];
    }

    return (float)$fee['base_fee'];
}

/**
 * Recalculate invoice totals including consultation fees
 */
function recalcInvoiceWithConsultationFee(PDO $pdo, int $invoiceId): void {
    // Get all invoice items total
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $itemsSubtotal = (float)$stmt->fetchColumn();

    // Get invoice details
    $invStmt = $pdo->prepare("
        SELECT i.discount, i.tax, i.paid_amount, i.consultation_fee_amount, i.payment_type
        FROM invoices i
        WHERE i.id = ?
    ");
    $invStmt->execute([$invoiceId]);
    $inv = $invStmt->fetch();

    $consultationFeeAmount = (float)($inv['consultation_fee_amount'] ?? 0);
    $subtotal = $itemsSubtotal + $consultationFeeAmount;
    $discount = (float)($inv['discount'] ?? 0);
    $tax = (float)($inv['tax'] ?? 0);
    $total = max(0, $subtotal - $discount + $tax);
    $balance = max(0, $total - (float)($inv['paid_amount'] ?? 0));

    $status = $balance <= 0 && $total > 0 ? 'paid' : ($inv['paid_amount'] > 0 ? 'partial' : 'issued');

    $pdo->prepare("
        UPDATE invoices 
        SET subtotal = ?, total = ?, balance = ?, status = ? 
        WHERE id = ?
    ")->execute([$subtotal, $total, $balance, $status, $invoiceId]);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

    // ===== GET =====
    if ($method === 'GET') {
        // Get single invoice with full details including consultation fee
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       TRIM(CONCAT(COALESCE(uc.first_name,''),' ',COALESCE(uc.last_name,''))) AS created_by_name,
                       TRIM(CONCAT(COALESCE(ur.first_name,''),' ',COALESCE(ur.last_name,''))) AS payment_received_by_name,
                       p.first_name, p.last_name, p.patient_no,
                       v.visit_no, v.visit_date, v.visit_type,
                       COALESCE(ic.name, ic2.name, ic3.name) AS insurance_company,
                       COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage,
                       d.name AS department_name
                FROM invoices i
                JOIN patients p ON p.id = i.patient_id
                JOIN visits v ON v.id = i.visit_id
                LEFT JOIN users uc ON uc.id = i.created_by
                LEFT JOIN users ur ON ur.id = i.payment_received_by
                LEFT JOIN patient_insurance pi ON pi.id = i.insurance_id AND pi.patient_id = i.patient_id
                LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
                LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                LEFT JOIN patient_insurance pi2 ON pi2.company_id = i.insurance_id AND pi2.patient_id = i.patient_id AND pi.id IS NULL
                LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
                LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
                LEFT JOIN patient_insurance pi3 ON pi3.patient_id = i.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
                LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
                LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
                LEFT JOIN departments d ON d.id = i.department_id
                WHERE i.id = ?
            ");
            $stmt->execute([$id]);
            $inv = $stmt->fetch();
            if (!$inv) error('Invoice not found', 404);

            // Get invoice items
            $iStmt = $pdo->prepare("
                SELECT ii.*, s.code AS service_code, s.name AS service_name
                FROM invoice_items ii
                LEFT JOIN service_catalog s ON s.id = ii.service_id
                WHERE ii.invoice_id = ?
                ORDER BY ii.id
            ");
            $iStmt->execute([$id]);
            $inv['items'] = $iStmt->fetchAll();

            // Get payment records
            $pStmt = $pdo->prepare("
                SELECT pr.*, 
                       TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS created_by_name
                FROM payment_records pr
                LEFT JOIN users u ON u.id = pr.created_by
                WHERE pr.invoice_id = ?
                ORDER BY pr.payment_date DESC, pr.id DESC
            ");
            $pStmt->execute([$id]);
            $inv['payments'] = $pStmt->fetchAll();

            success($inv);
        }

        // List invoices
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $status = $_GET['status'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if ($visitId) {
            $where[] = 'i.visit_id = ?';
            $params[] = $visitId;
        }
        if ($patientId) {
            $where[] = 'i.patient_id = ?';
            $params[] = $patientId;
        }
        if ($deptId) {
            $where[] = 'i.department_id = ?';
            $params[] = $deptId;
        }
        if ($status) {
            $where[] = 'i.status = ?';
            $params[] = $status;
        }

        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM invoices i $ws");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT i.id, i.invoice_no, i.visit_id, i.payment_type, i.subtotal, i.total,
                   i.paid_amount, i.balance, i.status, i.created_at, i.created_by, i.payment_received_by,
                   TRIM(CONCAT(COALESCE(uc.first_name,''),' ',COALESCE(uc.last_name,''))) AS created_by_name,
                   TRIM(CONCAT(COALESCE(ur.first_name,''),' ',COALESCE(ur.last_name,''))) AS payment_received_by_name,
                   i.consultation_fee_included, i.consultation_fee_amount,
                   p.first_name, p.last_name, p.patient_no,
                   v.visit_no, d.name AS department_name
            FROM invoices i
            JOIN patients p ON p.id = i.patient_id
            JOIN visits v ON v.id = i.visit_id
            LEFT JOIN users uc ON uc.id = i.created_by
            LEFT JOIN users ur ON ur.id = i.payment_received_by
            LEFT JOIN departments d ON d.id = i.department_id
            $ws
            ORDER BY i.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

        success([
            'invoices' => $invoices,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / max($limit, 1))
            ]
        ]);
    }

    // ===== POST — Create invoice with consultation fee =====
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        foreach (['visit_id', 'patient_id', 'department_id'] as $f) {
            if (empty($input[$f])) error("Field '$f' required", 422);
        }

        $visitId = (int)$input['visit_id'];
        $patientId = (int)$input['patient_id'];
        $deptId = (int)$input['department_id'];

        // Verify visit belongs to patient
        $chk = $pdo->prepare("SELECT id, insurance_id, payment_type FROM visits WHERE id = ? AND patient_id = ?");
        $chk->execute([$visitId, $patientId]);
        $visit = $chk->fetch();
        if (!$visit) error('Visit not found for this patient', 404);

        $paymentType = $input['payment_type'] ?? $visit['payment_type'] ?? 'self_pay';
        $insId = $input['insurance_id'] ?? $visit['insurance_id'] ?? null;
        $discount = (float)($input['discount'] ?? 0);
        $tax = (float)($input['tax'] ?? 0);
        $items = $input['items'] ?? [];
        $notes = $input['notes'] ?? null;

        // Calculate consultation fee
        $consultationFeeAmount = 0;
        $includeConsultationFee = $input['include_consultation_fee'] ?? true;

        if ($includeConsultationFee) {
            $consultationType = $input['consultation_type'] ?? 'initial';
            $consultationFeeAmount = getApplicableConsultationFee($pdo, 0, $deptId, $paymentType);
        }

        $pdo->beginTransaction();

        // Generate invoice number
        $prefix = 'INV-' . date('Ymd') . '-';
        $stmt = $pdo->prepare("
            SELECT invoice_no FROM invoices 
            WHERE invoice_no LIKE ? 
            ORDER BY invoice_no DESC LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        $seq = $last ? ((int)substr($last, -4) + 1) : 1;
        $invoiceNo = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

        // Create invoice
        $invStmt = $pdo->prepare("
            INSERT INTO invoices 
            (invoice_no, visit_id, patient_id, department_id, payment_type, insurance_id,
             discount, tax, subtotal, total, balance, status, notes, 
             consultation_fee_included, consultation_fee_amount, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 'issued', ?, ?, ?, ?, NOW())
        ");
        $invStmt->execute([
            $invoiceNo, $visitId, $patientId, $deptId, $paymentType, $insId,
            $discount, $tax, $notes,
            $includeConsultationFee ? 1 : 0,
            $consultationFeeAmount,
            $input['created_by'] ?? null
        ]);

        $invoiceId = (int)$pdo->lastInsertId();

        // Add items if provided
        if (is_array($items) && count($items)) {
            $iStmt = $pdo->prepare("
                INSERT INTO invoice_items 
                (invoice_id, service_id, description, category, quantity, unit_price, discount, total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($items as $item) {
                $qty = max(1, (int)($item['quantity'] ?? 1));
                $price = (float)($item['unit_price'] ?? 0);
                $itemDisc = (float)($item['discount'] ?? 0);
                $total = max(0, ($qty * $price) - $itemDisc);

                $iStmt->execute([
                    $invoiceId,
                    !empty($item['service_id']) ? (int)$item['service_id'] : null,
                    $item['description'] ?? 'Service',
                    $item['category'] ?? 'consultation',
                    $qty, $price, $itemDisc, $total
                ]);
            }
        }

        // Recalculate invoice with consultation fee
        recalcInvoiceWithConsultationFee($pdo, $invoiceId);

        // Update visit
        $pdo->prepare("
            UPDATE visits 
            SET status = 'in_progress', consultation_completed_at = NOW()
            WHERE id = ?
        ")->execute([$visitId]);

        $pdo->commit();

        // Fetch created invoice
            $stmt = $pdo->prepare("
                SELECT i.*, 
                       TRIM(CONCAT(COALESCE(uc.first_name,''),' ',COALESCE(uc.last_name,''))) AS created_by_name,
                       TRIM(CONCAT(COALESCE(ur.first_name,''),' ',COALESCE(ur.last_name,''))) AS payment_received_by_name,
                       p.first_name, p.last_name, p.patient_no,
                       v.visit_no, v.visit_date, v.visit_type,
                       d.name AS department_name
                FROM invoices i
                JOIN patients p ON p.id = i.patient_id
                JOIN visits v ON v.id = i.visit_id
                LEFT JOIN users uc ON uc.id = i.created_by
                LEFT JOIN users ur ON ur.id = i.payment_received_by
                LEFT JOIN departments d ON d.id = i.department_id
                WHERE i.id = ?
            ");
        $fetchStmt = $stmt;
        $fetchStmt->execute([$invoiceId]);
        success($fetchStmt->fetch(), 'Invoice created with consultation fee', 201);
    }

    // ===== PUT — Update invoice =====
    if ($method === 'PUT') {
        if (!$id) error('Invoice ID required', 422);

        $chk = $pdo->prepare("SELECT id FROM invoices WHERE id = ?");
        $chk->execute([$id]);
        if (!$chk->fetch()) error('Invoice not found', 404);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $action2 = $input['action'] ?? 'update';

        // Add item to invoice
        if ($action2 === 'add_item') {
            $qty = max(1, (int)($input['quantity'] ?? 1));
            $price = (float)($input['unit_price'] ?? 0);
            $disc = (float)($input['discount'] ?? 0);
            $total = max(0, ($qty * $price) - $disc);

            $pdo->prepare("
                INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, discount, total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $id,
                !empty($input['service_id']) ? (int)$input['service_id'] : null,
                $input['description'] ?? 'Service',
                $input['category'] ?? 'consultation',
                $qty, $price, $disc, $total
            ]);

            recalcInvoiceWithConsultationFee($pdo, $id);
            success(null, 'Item added to invoice');
        }

        // Remove item from invoice
        if ($action2 === 'remove_item') {
            $itemId = (int)($input['item_id'] ?? 0);
            if (!$itemId) error('item_id required', 422);

            $pdo->prepare("DELETE FROM invoice_items WHERE id = ? AND invoice_id = ?")
                ->execute([$itemId, $id]);

            recalcInvoiceWithConsultationFee($pdo, $id);
            success(null, 'Item removed from invoice');
        }

        // Update consultation fee
        if ($action2 === 'update_consultation_fee') {
            $consultationFeeAmount = (float)($input['consultation_fee_amount'] ?? 0);

            $pdo->prepare("
                UPDATE invoices 
                SET consultation_fee_amount = ? 
                WHERE id = ?
            ")->execute([$consultationFeeAmount, $id]);

            recalcInvoiceWithConsultationFee($pdo, $id);
            success(null, 'Consultation fee updated');
        }

        // General field update
        $allowed = ['payment_type', 'insurance_id', 'discount', 'tax', 'status', 'notes'];
        $fields = [];
        $params = [];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f = ?";
                $params[] = $input[$f];
            }
        }

        if ($fields) {
            $params[] = $id;
            $pdo->prepare("UPDATE invoices SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
            recalcInvoiceWithConsultationFee($pdo, $id);
        }

        success(null, 'Invoice updated');
    }

    // ===== DELETE =====
    if ($method === 'DELETE') {
        if (!$id) error('Invoice ID required', 422);

        $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
        success(null, 'Invoice deleted');
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
