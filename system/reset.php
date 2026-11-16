<?php
// reset.php — ADMIN ONLY. Wipes every patient who has a visit in the last 3 days, COMPLETELY
// (patient, ALL visits incl. older ones, all linked clinical/billing/pharmacy/lab data),
// restores the stock those patients consumed and removes their journal entries.
// Deletes only on POST + CSRF token, in ONE transaction (rollback on any error).
//
// Deliberately NOT touched (no direct link to these patients/invoices):
//  - finance_remittances, finance_cash_close, insurance_settlements (header totals), insurance_claims
//  - journal entries of type 'consumption' (lab/nurse COGS: keyed by item, not patient), 'insurance_settlement',
//    and any deposit/write-off journal (their reference_id is not an invoice id)
//  - chart_of_accounts rows linked to a patient (linked_client_id), deletion_requests / edit_requests history
//  - stock not linked by reference (lab consumption with NULL order_id, movements with unknown reference_type)
require_once __DIR__ . '/includes/session_check.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';

if (empty($_SESSION['user_id']) || !isUserAdmin($pdo)) {
    http_response_code(403);
    exit('403 Forbidden: administrators only.');
}

const RESET_WHERE = "COALESCE(visit_date, DATE(created_at)) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function inl(array $ids): string { return implode(',', array_map('intval', $ids)); }
function col(PDO $pdo, string $sql): array { return array_map('intval', $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN)); }

function hasCol(PDO $pdo, string $t, string $c): bool {
    static $cache = [];
    if (!isset($cache[$t])) {
        $st = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $st->execute([$t]);
        $cache[$t] = $st->fetchAll(PDO::FETCH_COLUMN);
    }
    return in_array($c, $cache[$t], true);
}
function hasTable(PDO $pdo, string $t): bool {
    static $cache = [];
    if (!isset($cache[$t])) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $st->execute([$t]);
        $cache[$t] = (bool)$st->fetchColumn();
    }
    return $cache[$t];
}

/** DELETE FROM $t WHERE c1 IN (..) OR c2 IN (..). $map = [column => ids]. Missing table/column/empty ids skipped. */
function purge(PDO $pdo, array &$counts, string $t, array $map): void {
    if (!hasTable($pdo, $t)) return;
    $parts = [];
    foreach ($map as $c => $ids) {
        if ($ids && hasCol($pdo, $t, $c)) $parts[] = "`$c` IN (" . inl($ids) . ")";
    }
    if (!$parts) return;
    $n = $pdo->exec("DELETE FROM `$t` WHERE " . implode(' OR ', $parts));
    if ($n) $counts[$t] = ($counts[$t] ?? 0) + $n;
}

function runReset(PDO $pdo, int $adminId): array {
    $counts = [];
    $P = col($pdo, "SELECT DISTINCT patient_id FROM visits WHERE " . RESET_WHERE);
    if (!$P) return $counts;
    $pl = inl($P);

    $V   = col($pdo, "SELECT id FROM visits WHERE patient_id IN ($pl)");
    $vl  = $V ? inl($V) : '0';
    $C   = col($pdo, "SELECT id FROM consultations WHERE patient_id IN ($pl) OR visit_id IN ($vl)");
    $cl  = $C ? inl($C) : '0';
    $INV = col($pdo, "SELECT id FROM invoices WHERE patient_id IN ($pl) OR visit_id IN ($vl)");
    $il  = $INV ? inl($INV) : '0';
    $PAY = col($pdo, "SELECT id FROM payment_records WHERE patient_id IN ($pl) OR invoice_id IN ($il)");
    $RX  = col($pdo, "SELECT id FROM pharmacy_prescriptions WHERE patient_id IN ($pl) OR visit_id IN ($vl) OR consultation_id IN ($cl)");
    $rl  = $RX ? inl($RX) : '0';
    $SALE = col($pdo, "SELECT id FROM pharmacy_sales WHERE patient_id IN ($pl) OR prescription_id IN ($rl)");
    $RXI = $RX ? col($pdo, "SELECT id FROM pharmacy_prescription_items WHERE prescription_id IN ($rl)") : [];
    $ORD = col($pdo, "SELECT id FROM medical_orders WHERE patient_id IN ($pl) OR visit_id IN ($vl) OR consultation_id IN ($cl)");
    $BED = col($pdo, "SELECT id FROM bed_assignments WHERE patient_id IN ($pl) OR visit_id IN ($vl)");
    $EXT = col($pdo, "SELECT id FROM external_prescriptions WHERE patient_id IN ($pl) OR visit_id IN ($vl)");
    $DC  = col($pdo, "SELECT id FROM dept_consumption WHERE patient_id IN ($pl)");
    // lab consumption still 'confirmed' = stock still deducted (reversed rows were already restored)
    $LABORD = col($pdo, "SELECT DISTINCT order_id FROM lab_item_consumption WHERE status = 'confirmed' AND order_id IS NOT NULL AND (patient_id IN ($pl) OR visit_id IN ($vl) OR order_id IN (" . ($ORD ? inl($ORD) : '0') . "))");
    $LIC = col($pdo, "SELECT id FROM lab_item_consumption WHERE patient_id IN ($pl) OR visit_id IN ($vl)");

    // Snapshot patients for audit before anything is deleted
    $snap = $pdo->query("SELECT * FROM patients WHERE id IN ($pl)")->fetchAll(PDO::FETCH_ASSOC);

    // ---- 1. STOCK RESTORE (no shared helper exists; same logic as inventory-management consumption revert) ----
    $or = [];
    if ($RX)     $or[] = "(reference_type='prescription' AND reference_id IN (" . inl($RX) . "))";
    if ($SALE)   $or[] = "(reference_type='pharmacy_sale' AND reference_id IN (" . inl($SALE) . "))";
    if ($ORD)    $or[] = "(reference_type='lab_consumption' AND reference_id IN (" . inl($ORD) . "))";
    if ($DC)     $or[] = "(reference_type='dept_use' AND reference_id IN (" . inl($DC) . "))";
    if ($V)      $or[] = "(reference_type='nurse_use' AND reference_id IN ($vl))";
    $mvIds = [];
    $restored = 0; $unrestored = 0;
    if ($or) {
        $rows = $pdo->query("SELECT sm.id, sm.batch_id, sm.quantity, sm.reference_type, sm.reference_id,
                (SELECT COUNT(*) FROM stock_movements r WHERE r.reference_type='consumption_reversal' AND r.reference_id = sm.id) AS reverted
            FROM stock_movements sm WHERE sm.movement_type IN ('dispensing','consumption') AND (" . implode(' OR ', $or) . ")")->fetchAll(PDO::FETCH_ASSOC);
        $add = $pdo->prepare("UPDATE stock_batches SET quantity = quantity + ? WHERE id = ?");
        foreach ($rows as $m) {
            $mvIds[] = (int)$m['id'];
            if ((int)$m['reverted'] > 0) continue;                       // already restored by a reversal
            if ($m['reference_type'] === 'lab_consumption' && !in_array((int)$m['reference_id'], $LABORD, true)) continue; // lab reversed
            $add->execute([(float)$m['quantity'], (int)$m['batch_id']]);
            if ($add->rowCount() > 0) $restored++; else $unrestored++;   // batch row gone
        }
    }
    if ($mvIds) {
        $ml = inl($mvIds);
        purge($pdo, $counts, 'stock_movements', ['id' => $mvIds]);
        $n = $pdo->exec("DELETE FROM stock_movements WHERE reference_type='consumption_reversal' AND reference_id IN ($ml)");
        if ($n) $counts['stock_movements'] = ($counts['stock_movements'] ?? 0) + $n;
    }
    if ($LIC) {
        $n = $pdo->exec("DELETE FROM stock_movements WHERE reference_type='lab_consumption_reversal' AND reference_id IN (" . inl($LIC) . ")");
        if ($n) $counts['stock_movements'] = ($counts['stock_movements'] ?? 0) + $n;
    }
    $counts['_stock_movements_restored_to_batch'] = $restored;
    $counts['_stock_movements_batch_missing'] = $unrestored;

    // ---- 2. JOURNALS tied to deleted invoices / payments / pharmacy sales ----
    $q = [];
    if ($INV) {
        $strs = implode(',', array_map(fn($i) => "'" . (int)$i . "'", $INV));
        $q[] = "(reference_type IN ('invoice','invoice_adj','payment','momo_payment','insurance_payment','afrirem_payment','write_off') AND reference_id IN ($strs))";
    }
    if ($SALE) {
        $strs = implode(',', array_map(fn($i) => "'" . (int)$i . "'", $SALE));
        $q[] = "(reference_type = 'pharmacy_sale' AND reference_id IN ($strs))";
    }
    if ($q) {
        $JE = col($pdo, "SELECT id FROM journal_entries WHERE " . implode(' OR ', $q));
        if ($JE) {   // plus reversal entries that point at them
            $JE = array_values(array_unique(array_merge($JE, col($pdo, "SELECT id FROM journal_entries WHERE reference_type='reversal' AND reference_id IN (" . implode(',', array_map(fn($i) => "'$i'", $JE)) . ")"))));
            if (hasTable($pdo, 'bank_statement_lines') && hasCol($pdo, 'bank_statement_lines', 'journal_line_id')) {
                $pdo->exec("UPDATE bank_statement_lines SET journal_line_id = NULL WHERE journal_line_id IN (SELECT id FROM journal_entry_lines WHERE journal_entry_id IN (" . inl($JE) . "))");
            }
            purge($pdo, $counts, 'journal_entry_lines', ['journal_entry_id' => $JE]);
            purge($pdo, $counts, 'journal_entries', ['id' => $JE]);
        }
    }

    // ---- 3. lab / insurance / billing ----
    $K = ['patient_id' => $P, 'visit_id' => $V];
    purge($pdo, $counts, 'lab_item_consumption', $K + ['order_id' => $ORD]);
    purge($pdo, $counts, 'insurance_claim_rejections', ['invoice_id' => $INV]);
    purge($pdo, $counts, 'insurance_settlement_lines', ['invoice_id' => $INV]);
    purge($pdo, $counts, 'payment_disputes', ['payment_record_id' => $PAY]);
    purge($pdo, $counts, 'payment_allocations', ['payment_record_id' => $PAY]);
    purge($pdo, $counts, 'payment_records', ['id' => $PAY]);
    purge($pdo, $counts, 'invoice_adjustments', ['invoice_id' => $INV]);
    purge($pdo, $counts, 'invoice_edit_logs', ['invoice_id' => $INV]);
    purge($pdo, $counts, 'invoice_items', ['invoice_id' => $INV]);
    purge($pdo, $counts, 'patient_debts', ['patient_id' => $P, 'invoice_id' => $INV]);
    purge($pdo, $counts, 'nursing_records', $K + ['bed_assignment_id' => $BED, 'invoice_id' => $INV]);

    // ---- 4. pharmacy ----
    purge($pdo, $counts, 'pharmacy_dispensing_log', ['patient_id' => $P, 'sale_id' => $SALE, 'prescription_item_id' => $RXI]);
    purge($pdo, $counts, 'dispense_shortfalls', ['prescription_item_id' => $RXI]);
    purge($pdo, $counts, 'medication_administrations', $K + ['prescription_item_id' => $RXI, 'bed_assignment_id' => $BED]);
    purge($pdo, $counts, 'pharmacy_sale_items', ['sale_id' => $SALE]);
    purge($pdo, $counts, 'pharmacy_sales', ['id' => $SALE]);
    purge($pdo, $counts, 'pharmacy_prescription_items', ['prescription_id' => $RX]);
    purge($pdo, $counts, 'pharmacy_prescriptions', ['id' => $RX]);
    purge($pdo, $counts, 'external_prescription_items', ['prescription_id' => $EXT]);
    purge($pdo, $counts, 'external_prescriptions', ['id' => $EXT]);

    // ---- 5. consultations / orders ----
    purge($pdo, $counts, 'clinical_note_comments', ['visit_id' => $V, 'consultation_id' => $C]);
    purge($pdo, $counts, 'consultation_events', ['visit_id' => $V, 'consultation_id' => $C]);
    purge($pdo, $counts, 'consultation_fee_history', ['consultation_id' => $C]);
    purge($pdo, $counts, 'medical_orders', ['id' => $ORD]);
    purge($pdo, $counts, 'consultations', ['id' => $C]);

    // ---- 6. everything else keyed by patient_id / visit_id (dependents of bed_assignments first) ----
    $G = ['patient_id' => $P, 'visit_id' => $V, 'source_visit_id' => $V, 'consultation_id' => $C, 'invoice_id' => $INV, 'bed_assignment_id' => $BED];
    foreach (['admission_requests', 'appointments', 'dept_consumption', 'diagnosis_recurrence_flags', 'diagnoses', 'dosage_alerts',
              'documents', 'emergency_cases', 'inpatient_lab_requests', 'inpatient_progress_notes', 'inpatient_vitals',
              'lab_special_reports', 'medical_documents', 'notifications', 'patient_attachments', 'patient_insurance',
              'patient_queue', 'patient_transfers', 'prescribed_dosage', 'surgeries', 'visit_department_forms',
              'visit_department_history', 'visit_tariff_changes', 'vital_signs_records', 'finance_deposits', 'finance_writeoffs',
              'bed_assignments'] as $t) {
        purge($pdo, $counts, $t, $G);
    }
    purge($pdo, $counts, 'invoices', ['id' => $INV]);
    purge($pdo, $counts, 'visits', ['id' => $V]);
    purge($pdo, $counts, 'patients', ['id' => $P]);

    // ---- 7. audit ----
    if (hasTable($pdo, 'deletion_audit_log')) {
        $ins = $pdo->prepare("INSERT INTO deletion_audit_log (deletion_request_id, entity_type, entity_id, entity_data, deleted_by, deleted_at) VALUES (0, ?, ?, ?, ?, NOW())");
        foreach ($snap as $row) $ins->execute(['patient_reset', (int)$row['id'], json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR), $adminId]);
        $ins->execute(['reset_batch', count($P), json_encode(['patient_ids' => $P, 'counts' => $counts], JSON_PARTIAL_OUTPUT_ON_ERROR), $adminId]);
    }
    return $counts;
}

$msg = ''; $err = ''; $counts = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(app_csrf_token(), (string)($_POST['csrf'] ?? '')) || ($_POST['confirm'] ?? '') !== 'RESET') {
        http_response_code(403);
        exit('403 Forbidden: bad token.');
    }
    try {
        $pdo->beginTransaction();
        $counts = runReset($pdo, (int)$_SESSION['user_id']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
        $counts = null;
    }
}
$n = (int)$pdo->query("SELECT COUNT(DISTINCT patient_id) FROM visits WHERE " . RESET_WHERE)->fetchColumn();
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Reset</title></head>
<body style="font-family:sans-serif;max-width:640px;margin:40px auto">
<h2>Reset recent patients</h2>
<?php if ($err): ?><p style="color:#b00;font-weight:bold">Rolled back, nothing deleted. Error: <?= h($err) ?></p><?php endif; ?>
<?php if ($counts !== null): ?>
  <p style="color:#070;font-weight:bold">Done.</p>
  <table border="1" cellpadding="4" cellspacing="0">
  <?php foreach ($counts as $t => $c): ?><tr><td><?= h($t) ?></td><td><?= (int)$c ?></td></tr><?php endforeach; ?>
  <?php if (!$counts): ?><tr><td>No patients matched</td></tr><?php endif; ?>
  </table>
<?php else: ?>
  <p>Patients with a visit in the last 3 days (all their data will be deleted, stock restored): <b><?= $n ?></b></p>
  <form method="post" onsubmit="return confirm('Permanently delete <?= $n ?> patient(s) and ALL their data? This cannot be undone.')">
    <input type="hidden" name="csrf" value="<?= h(app_csrf_token()) ?>">
    <input type="hidden" name="confirm" value="RESET">
    <button type="submit" <?= $n ? '' : 'disabled' ?> style="background:#c00;color:#fff;font-size:28px;padding:20px 50px;border:0;border-radius:8px;cursor:pointer">Reset</button>
  </form>
<?php endif; ?>
</body></html>
