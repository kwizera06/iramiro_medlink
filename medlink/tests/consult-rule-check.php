<?php
// Consultation 3/14-day rule self-check. CLI only: php tests/consult-rule-check.php
// Runs against the configured DB inside a transaction that is always rolled back. Needs the current migrate.php (departments.visit_kind).
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/billing-calc.php';

$check = function (bool $ok, string $what) { echo ($ok ? 'ok   ' : 'FAIL ') . "$what\n"; if (!$ok) $GLOBALS['failed'] = true; };
$failed = false;

foreach ([[null, 'full'], [0, 'free'], [3, 'free'], [4, 'follow_up'], [14, 'follow_up'], [15, 'full']] as [$d, $want])
    $check(consultRuleForDays($d) === $want, 'day ' . ($d ?? 'never') . " => $want");

$gm = (int)$pdo->query("SELECT id FROM departments WHERE name = 'General Medicine'")->fetchColumn();
$peds = (int)$pdo->query("SELECT id FROM departments WHERE name = 'Pediatrics'")->fetchColumn();
$lab = (int)$pdo->query("SELECT id FROM departments WHERE visit_kind = 'no_fee' LIMIT 1")->fetchColumn();
$svc = fn(string $name) => (int)$pdo->query("SELECT id FROM service_catalog WHERE speciality = 'Consultation' AND procedure_name = " . $pdo->quote($name))->fetchColumn();
$gpFull = $svc('Consultation by a general practioner'); $gpFu = $svc('Follow up consultations by GP');

$pdo->beginTransaction();
try {
    $pdo->exec("INSERT INTO patients (patient_no, first_name, last_name, date_of_birth, gender, phone) VALUES ('TEST-" . uniqid() . "', 'Rule', 'Check', '1990-01-01', 'male', '0780000000')");
    $pid = (int)$pdo->lastInsertId();
    $visit = function (int $daysAgo, int $dept, string $status = 'completed') use ($pdo, $pid) {
        $pdo->prepare("INSERT INTO visits (visit_no, patient_id, visit_date, visit_time, payment_type, tariff_category, status, department_id) VALUES (?, ?, CURDATE() - INTERVAL ? DAY, CURTIME(), 'self_pay', 'rwanda', ?, ?)")
            ->execute(['TEST-' . uniqid(), $pid, $daysAgo, $status, $dept]);
        return (int)$pdo->lastInsertId();
    };

    $check(consultRule($pdo, $pid, $gm)['rule'] === 'full', 'first visit => full');
    $check(consultRule($pdo, $pid, $lab)['rule'] === 'no_fee', 'lab / imaging department => no fee');

    $v = $visit(9, $gm);
    createConsultationInvoice($pdo, $v, $gpFull, null);
    $r = consultRule($pdo, $pid, $gm);
    $check($r['rule'] === 'follow_up' && $r['days'] === 9, 'full consultation 9 days ago => follow-up');
    $check(consultRule($pdo, $pid, $peds)['rule'] === 'full', 'other department => full');
    $check(consultRule($pdo, $pid, $gm, $v)['rule'] === 'full', 'the visit itself is not counted');
    $fu = consultServicesFor($pdo, $gm, 'follow_up');
    $check($fu && !array_filter($fu, fn($s) => !consultIsFollowUp($s['name'])), 'follow-up list only has follow-up fees');
    $check(in_array($gpFu, array_map('intval', array_column($fu, 'id')), true), 'GP follow-up offered for General Medicine');

    $f = $visit(2, $gm);   // follow-up visit 2 days ago does not restart the count
    createConsultationInvoice($pdo, $f, $gpFu, null);
    $check(consultRule($pdo, $pid, $gm)['rule'] === 'follow_up', 'a follow-up does not restart the 3/14 days');

    $c = $visit(1, $gm, 'cancelled');
    createConsultationInvoice($pdo, $c, $gpFull, null);
    $check(consultRule($pdo, $pid, $gm)['rule'] === 'follow_up', 'cancelled visit is ignored');

    $x = $visit(3, $gm);
    createConsultationInvoice($pdo, $x, $gpFull, null);
    $check(consultRule($pdo, $pid, $gm)['rule'] === 'free', 'full consultation 3 days ago => free');

    $err = fn(string $rule, ?int $s) => (function () use ($pdo, $rule, $s) { try { consultCheckService($pdo, $rule, $s); return null; } catch (RuntimeException $e) { return $e->getMessage(); } })();
    $check($err('free', $gpFull) !== null, 'free: a fee is refused');
    $check($err('free', null) === null, 'free: no fee accepted');
    $check($err('full', null) === null, 'full: "No consultation fee" accepted');
    $check($err('full', $gpFu) !== null, 'full: follow-up fee refused');
    $check($err('follow_up', $gpFull) !== null, 'follow-up: full fee refused');
    $check($err('follow_up', $gpFu) === null, 'follow-up: follow-up fee accepted');

    // Triage change: unpaid fee replaced, paid fee kept
    $o = $visit(0, $gm, 'open');
    createConsultationInvoice($pdo, $o, $gpFull, null);
    $res = setVisitConsultationFee($pdo, $o, $peds, $svc('Consultation by a specialist'), null);
    $check($res['invoice_no'] !== null, 'new department billed');
    $res2 = setVisitConsultationFee($pdo, $o, $gm, $gpFu, null);
    $live = (int)$pdo->query("SELECT COUNT(*) FROM invoices i WHERE i.visit_id = $o AND i.status <> 'cancelled' AND i.department_id = $gm")->fetchColumn();
    $check($res2['note'] === 'Consultation fee changed' && $live === 1, 'unpaid fee replaced, one live invoice left');
    $inv = (int)$pdo->query("SELECT id FROM invoices WHERE visit_id = $o AND status <> 'cancelled' AND department_id = $gm")->fetchColumn();
    $pdo->exec("UPDATE invoices SET paid_amount = 100 WHERE id = $inv");
    $res3 = setVisitConsultationFee($pdo, $o, $gm, $gpFull, null);
    $check(str_contains($res3['note'], 'already paid'), 'paid fee is not changed');
} finally {
    $pdo->rollBack();
}
echo $failed ? "FAILED\n" : "All checks passed\n";
exit($failed ? 1 : 0);
