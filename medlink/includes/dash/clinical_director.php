<?php
// includes/dash/clinical_director.php — Clinical Director dashboard (sees every clinical figure, patient names included).
// Definitions mirror the doctor/nurse dashboard (handlers/consultation/consult.php consult_dashboard_counts) so the numbers agree.
require_once __DIR__ . '/../hmis_dxmap.php';   // hmis_dx_tokens(): splits "URTI, malaria" into separate diagnoses
[$from, $to] = dx_range('today');
$ward = 'ward.php?module=ward&sub=admitted';

// Patients in the period: distinct patients with a non-cancelled visit.
$patients = (int)dx_val($pdo, "SELECT COUNT(DISTINCT patient_id) FROM visits WHERE visit_date BETWEEN ? AND ? AND status <> 'cancelled'", [$from, $to]);
// Admissions / discharges in the period: bed_assignments of type admission, by assigned_at / discharged_at.
$adm = (int)dx_val($pdo, "SELECT COUNT(*) FROM bed_assignments WHERE assignment_type = 'admission' AND DATE(assigned_at) BETWEEN ? AND ?", [$from, $to]);
$dis = (int)dx_val($pdo, "SELECT COUNT(*) FROM bed_assignments WHERE discharged_at IS NOT NULL AND DATE(discharged_at) BETWEEN ? AND ?", [$from, $to]);
// Bed occupancy now: beds with an active assignment / active beds.
$occ = (int)dx_val($pdo, "SELECT COUNT(DISTINCT bed_id) FROM bed_assignments WHERE status = 'active'");
$beds = (int)dx_val($pdo, "SELECT COUNT(*) FROM beds WHERE is_active = 1");
$occPct = $beds ? round($occ / $beds * 100) : 0;
// Average length of stay: days between admission and discharge, for patients discharged in the period.
$alos = (float)dx_val($pdo, "SELECT AVG(TIMESTAMPDIFF(HOUR, assigned_at, discharged_at)) / 24 FROM bed_assignments WHERE discharged_at IS NOT NULL AND DATE(discharged_at) BETWEEN ? AND ?", [$from, $to]);
// Repeated-illness alerts raised in the period (doctor refer / transfer / justify decisions).
$flags = (int)dx_val($pdo, "SELECT COUNT(*) FROM diagnosis_recurrence_flags WHERE DATE(created_at) BETWEEN ? AND ?", [$from, $to]);
// Consultations left unsigned: draft consultations on visits that are not cancelled (all open ones, not period-bound).
$unsigned = (int)dx_val($pdo, "SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id = c.visit_id WHERE c.status = 'draft' AND v.status <> 'cancelled'");
// Emergency patients not yet discharged.
$emerg = (int)dx_val($pdo, "SELECT COUNT(*) FROM emergency_cases WHERE discharged_at IS NULL AND status IN ('triage','treatment','observation')");

echo dx_head('Clinical overview', $greeting . ', ' . $firstName . ' · clinic-wide, ' . date('d M', strtotime($from)) . ($from !== $to ? ' – ' . date('d M', strtotime($to)) : ''), [$from, $to]);
echo dx_attention([
    ['consultations left unsigned', 'department.php', $unsigned],
    ['repeated-illness alerts', 'consultation.php', $flags],
    ['emergency patients waiting', 'emergency.php', $emerg],
]);
echo dx_cards([
    ['Patients', dx_num($patients), 'fa-user-check', '#2563eb', 'Distinct patients with a visit', 'department.php'],
    ['Admissions / discharges', dx_num($adm) . ' / ' . dx_num($dis), 'fa-bed', '#dc2626', 'In the period', $ward],
    ['Bed occupancy', $occPct . '%', 'fa-hospital', '#0891b2', "$occ of $beds beds in use now", $ward],
    ['Average length of stay', $dis ? number_format($alos, 1) . ' days' : '—', 'fa-calendar-day', '#7c3aed', 'Patients discharged in the period'],
    ['Repeated-illness alerts', dx_num($flags), 'fa-repeat', '#d97706', 'Raised in the period'],
    ['Unsigned consultations', dx_num($unsigned), 'fa-file-signature', '#ea580c', 'Draft, all open visits', 'department.php'],
]);

// Inpatients by ward: beds are in rooms, rooms in wards. Occupied beds, total beds, and the patient who has stayed longest.
$wardRows = [];
foreach (dx_rows($pdo, "
    SELECT w.name,
           COUNT(DISTINCT b.id) total,
           COUNT(DISTINCT CASE WHEN ba.id IS NOT NULL THEN b.id END) occupied,
           TIMESTAMPDIFF(DAY, MIN(ba.assigned_at), NOW()) days,   -- in SQL: PHP and MySQL clocks may use different time zones
           SUBSTRING_INDEX(GROUP_CONCAT(CONCAT(p.first_name, ' ', p.last_name) ORDER BY ba.assigned_at SEPARATOR '|'), '|', 1) longest
    FROM wards w
    JOIN rooms r ON r.ward_id = w.id
    JOIN beds b ON b.room_id = r.id AND b.is_active = 1
    LEFT JOIN bed_assignments ba ON ba.bed_id = b.id AND ba.status = 'active'
    LEFT JOIN patients p ON p.id = ba.patient_id
    WHERE w.is_active = 1
    GROUP BY w.id, w.name ORDER BY occupied DESC, w.name") as $w) {
    $wardRows[] = [$w['name'], $w['occupied'] . ' / ' . $w['total'], $w['longest'] ? $w['longest'] . ' · ' . (int)$w['days'] . ' d' : '—'];
}
// Repeated-diagnosis flags in the period, newest first.
$flagRows = [];
foreach (dx_rows($pdo, "SELECT f.created_at, f.diagnosis, f.occurrences, f.window_days, f.action, CONCAT(p.first_name, ' ', p.last_name) patient
    FROM diagnosis_recurrence_flags f LEFT JOIN patients p ON p.id = f.patient_id
    WHERE DATE(f.created_at) BETWEEN ? AND ? ORDER BY f.created_at DESC LIMIT 10", [$from, $to]) as $f) {
    $col = ['referred' => '#2563eb', 'transferred' => '#7c3aed', 'justified' => '#d97706'][$f['action']] ?? '#475569';
    $flagRows[] = [$f['patient'] ?: '—', $f['diagnosis'], $f['occurrences'] . ' in ' . $f['window_days'] . ' d', ['html' => dx_badge(ucfirst($f['action']), $col)], date('d M H:i', strtotime($f['created_at']))];
}
echo dx_grid([
    dx_table('Inpatients by ward', ['Ward', 'Occupied', 'Longest stay'], $wardRows, 'No wards configured', $ward),
    dx_table('Repeated-diagnosis flags', ['Patient', 'Diagnosis', 'Count', 'Decision', 'When'], $flagRows, 'No repeated-illness alerts in this period'),
], 2);

// Top diagnoses in the period: consultations.diagnosis may hold several ("URTI, malaria"), so split like the HMIS report does.
$dx = []; $label = [];
foreach (dx_rows($pdo, "SELECT c.diagnosis FROM consultations c JOIN visits v ON v.id = c.visit_id
    WHERE c.diagnosis IS NOT NULL AND c.diagnosis <> '' AND v.visit_date BETWEEN ? AND ? AND v.status <> 'cancelled'", [$from, $to]) as $r)
    foreach (hmis_dx_tokens($r['diagnosis']) as $key => $typed) { $dx[$key] = ($dx[$key] ?? 0) + 1; $label[$key] ??= $typed; }   // count by normalised key, show the first spelling
arsort($dx);
$dxTop = []; foreach (array_slice($dx, 0, 10, true) as $key => $n) $dxTop[$label[$key]] = $n;
// Visits by department in the period.
$byDept = [];
foreach (dx_rows($pdo, "SELECT COALESCE(d.name, 'No department') n, COUNT(*) c FROM visits v LEFT JOIN departments d ON d.id = v.department_id
    WHERE v.visit_date BETWEEN ? AND ? AND v.status <> 'cancelled' GROUP BY n ORDER BY c DESC LIMIT 10", [$from, $to]) as $r) $byDept[$r['n']] = (int)$r['c'];
// Admissions per day: bed occupancy history cannot be rebuilt reliably (assignments are edited in place), so show admissions instead.
$perDay = [];
foreach (dx_rows($pdo, "SELECT DATE(assigned_at) d, COUNT(*) c FROM bed_assignments WHERE assignment_type = 'admission' AND DATE(assigned_at) BETWEEN ? AND ? GROUP BY d", [$from, $to]) as $r) $perDay[$r['d']] = (int)$r['c'];
[$dl, $dv] = dx_fill_days($perDay, $from, $to);
echo dx_grid([
    dx_bars('Top diagnoses', $dxTop, null, '#2563eb'),
    dx_bars('Visits by department', $byDept, null, '#059669'),
    dx_cols('Admissions per day', $dl, ['Admissions' => $dv], ['type' => 'bar']),
], 3);
echo dx_end();
