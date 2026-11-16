<?php
// Foreign-tariff billing self-check. CLI only: php tests/foreign-tariff-check.php
// Runs against the configured DB inside a transaction that is always rolled back. Needs migrate.php step 19.
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/billing-calc.php';

$svc = fn(string $rhic) => (int)$pdo->query("SELECT id FROM service_catalog WHERE rhic = " . $pdo->quote($rhic))->fetchColumn();
$selfPay = fn(int $id) => (float)$pdo->query("SELECT " . facilityPriceColumn($pdo) . " FROM service_catalog WHERE id = $id")->fetchColumn();
$check = function (bool $ok, string $what) { echo ($ok ? 'ok   ' : 'FAIL ') . "$what\n"; if (!$ok) $GLOBALS['failed'] = true; };
$failed = false;

$gp = $svc('RHIC-CONS-013'); $fbc = $svc('RLTC-HEMA-095'); $surgery = $svc('RHIC-GENS-106');
$patientId = (int)$pdo->query("SELECT id FROM patients LIMIT 1")->fetchColumn();

$pdo->beginTransaction();
try {
    $check(tariffServicePrice($pdo, $gp, 'world') == 21937, 'GP consultation, Rest of the World = 21,937');
    $check(tariffServicePrice($pdo, $gp, 'east_africa') == 6855, 'GP consultation, East Africa = 6,855');
    $check(tariffServicePrice($pdo, $gp, 'rwanda') == $selfPay($gp), 'GP consultation, Rwanda = self-pay price');
    $check(tariffServicePrice($pdo, $surgery, 'world') == $selfPay($surgery), 'service without foreign price = self-pay price');
    $pdo->exec("INSERT INTO insurance_procedure_prices (service_id, insurance_id, price) VALUES ($surgery, " . PRIVE_INSURANCE_ID . ", " . ($selfPay($surgery) + 1000) . ")");
    $check(tariffServicePrice($pdo, $surgery, 'world') == $selfPay($surgery) + 1000, '... or the PRIVE price when it is higher');
    $check(tariffServicePrice($pdo, $surgery, 'rwanda') == $selfPay($surgery), 'PRIVE price does not change the Rwanda self-pay price');

    $visit = function (string $tariff) use ($pdo, $patientId) {
        $pdo->prepare("INSERT INTO visits (visit_no, patient_id, visit_date, visit_time, payment_type, tariff_category, status) VALUES (?, ?, CURDATE(), CURTIME(), 'self_pay', ?, 'open')")
            ->execute(['TEST-' . uniqid(), $patientId, $tariff]);
        return (int)$pdo->lastInsertId();
    };

    // Foreign visit: the server prices services, the browser's price is ignored; drugs keep their price
    $v = $visit('world');
    $inv = createConsultationInvoice($pdo, $v, $gp, null);
    $check($inv && (float)$inv['total'] == 21937 && (float)$inv['balance'] == 21937, 'consultation invoice of a Rest of the World visit = 21,937, all paid by the patient');
    $check(tariffLinePrice($pdo, $v, $fbc, 6278, 'lab') == 25112, 'browser sends Rwandan FBC price 6,278 => billed 25,112');
    $check(tariffLinePrice($pdo, $v, $fbc, 500, 'pharmacy') == 500, 'drug line keeps its price');
    $check(tariffLinePrice($pdo, $visit('rwanda'), $fbc, 6278, 'lab') == 6278, 'Rwanda visit keeps the price as before');

    // Rwanda -> Rest of the World after the consultation was paid and the FBC was not
    $v = $visit('rwanda');
    $paid = createConsultationInvoice($pdo, $v, $gp, null);
    $pdo->prepare("INSERT INTO payment_records (invoice_id, patient_id, amount_paid, payment_date, status) VALUES (?, ?, ?, CURDATE(), 'completed')")
        ->execute([$paid['id'], $patientId, $paid['total']]);
    recalcInvoice($pdo, (int)$paid['id']);
    $unpaid = createConsultationInvoice($pdo, $v, $fbc, null);
    $change = changeVisitTariff($pdo, $v, 'world', 'test', null);
    $row = fn($id) => $pdo->query("SELECT total, balance FROM invoices WHERE id = " . (int)$id)->fetch(PDO::FETCH_ASSOC);
    $adj = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM invoice_items WHERE invoice_id = {$paid['id']} AND category = 'tariff_adjustment'")->fetchColumn();
    $gpDiff = 21937 - $selfPay($gp);
    $check($adj == $gpDiff && (float)$row($paid['id'])['balance'] == $gpDiff, "paid invoice: adjustment line and balance = $gpDiff");
    $check((float)$row($unpaid['id'])['total'] == 25112, 'unpaid FBC invoice re-priced to 25,112');
    $check($change == $gpDiff + 25112 - $selfPay($fbc), 'returned amount change matches');
    $check($pdo->query("SELECT tariff_category FROM visits WHERE id = $v")->fetchColumn() === 'world', 'visit moved to Rest of the World');
} finally {
    $pdo->rollBack();
}
echo $failed ? "FAILED\n" : "All checks passed\n";
exit($failed ? 1 : 0);
