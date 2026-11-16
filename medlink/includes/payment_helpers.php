<?php
// includes/payment_helpers.php
// Receipt numbers, invoice balance and item allocation shared by finance/payment-records and finance/finance-ops.
require_once __DIR__ . '/finance_posting.php';

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
 * True when the user holds admin or one of the given role slugs
 */
function user_has_role_slug(PDO $pdo, int $uid, array $slugs): bool {
    if ($uid <= 0) return false;
    $slugs[] = 'admin';
    $in = implode(',', array_fill(0, count($slugs), '?'));
    $q = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug IN ($in) LIMIT 1");
    $q->execute(array_merge([$uid], $slugs));
    return (bool)$q->fetchColumn();
}

/**
 * Surplus the patient paid beyond their share of the invoice (0 when none)
 */
function invoice_overpaid(PDO $pdo, int $invoiceId): float {
    $plan = fin_invoice_plan($pdo, $invoiceId);
    if (!$plan) return 0.0;
    $q = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id = ? AND status = 'completed'");
    $q->execute([$invoiceId]);
    $o = round((float)$q->fetchColumn() - (float)$plan['patient_due'], 2);
    return $o >= 0.5 ? $o : 0.0; // under half a franc is rounding, not a surplus
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

    // Get invoice details and resolved copayment percentage
    $invStmt = $pdo->prepare("
        SELECT i.total,
               COALESCE(
                   (SELECT ip.copayment_percentage FROM patient_insurance pi JOIN insurance_packages ip ON ip.id = pi.package_id WHERE pi.patient_id = p.id AND (pi.id = i.insurance_id OR pi.id = v.insurance_id OR pi.company_id = i.insurance_id OR pi.company_id = v.insurance_id) ORDER BY pi.is_primary DESC LIMIT 1),
                   (SELECT ip.copayment_percentage FROM patient_insurance pi JOIN insurance_packages ip ON ip.id = pi.package_id WHERE pi.patient_id = p.id AND pi.is_primary = 1 LIMIT 1),
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
    $total = (float)$inv['total'];
    $copayPct = (float)$inv['copayment_percentage'];

    $patientDue = fin_split($total, $copayPct)['patient'];
    // balance stays >= 0; a surplus is never lost: paid_amount keeps the full total and paid - patient due is the overpayment
    $balance = max(0, $patientDue - $totalPaid);
    $status = $balance <= 0 && $total > 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'issued');

    $pmStmt = $pdo->prepare("SELECT payment_method FROM payment_records WHERE invoice_id = ? AND status = 'completed' ORDER BY id DESC LIMIT 1");
    $pmStmt->execute([$invoiceId]);
    $lastPm = $pmStmt->fetchColumn() ?: null;

    $pdo->prepare("
        UPDATE invoices 
        SET paid_amount = ?, balance = ?, status = ?, payment_method = COALESCE(?, payment_method), updated_at = NOW()
        WHERE id = ?
    ")->execute([$totalPaid, $balance, $status, $lastPm, $invoiceId]);
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
