<?php
// handlers/reports/performance.php — Doctor performance, Nurse performance, Repeat diagnoses.
// Included by handlers/reports/general.php (uses its ok()/err() helpers and $pdo/$action).
if (!function_exists('user_has_role_slug')) {
function user_has_role_slug(PDO $pdo, int $uid, array $slugs): bool {
    if ($uid <= 0) return false;
    $slugs[] = 'admin';
    $in = implode(',', array_fill(0, count($slugs), '?'));
    $q = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug IN ($in) LIMIT 1");
    $q->execute(array_merge([$uid], $slugs));
    return (bool)$q->fetchColumn();
}
}

const PERF_PRIVATE_INSURER = 13; // same rule as hmis.php isInsured()
const PERF_FULL_ROLES = ['department_head', 'md', 'managing_director', 'clinical_director', 'head_nurse', 'head-nurse'];

function perf_insured($payType, $insId): bool {
    return in_array($payType, ['insurance', 'mixed'], true) && (int)$insId > 0 && (int)$insId !== PERF_PRIVATE_INSURER;
}
function perf_table(PDO $pdo, string $t): bool {
    $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");
    $q->execute([$t]);
    return (int)$q->fetchColumn() > 0;
}
// Staff member sees only their own row unless admin / department head / MD / head nurse
function perf_self_only(PDO $pdo): int {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    return user_has_role_slug($pdo, $uid, PERF_FULL_ROLES) ? 0 : $uid;
}
function perf_age($dob, $at): int {
    if (!$dob || !strtotime($dob)) return 99;
    return (new DateTime($dob))->diff(new DateTime($at ?: 'now'))->y;
}
// Shared columns every source query returns
const PERF_COLS = "v.id AS visit_id, v.visit_no, v.payment_type, v.insurance_id, ic.name AS ins_name,
    p.id AS pid, p.patient_no, CONCAT(p.first_name,' ',p.last_name) AS pname, p.gender, p.date_of_birth AS dob";
const PERF_JOINS = "LEFT JOIN insurance_companies ic ON ic.id = v.insurance_id";

function perf_row(array $r): array {
    $age = perf_age($r['dob'] ?? null, $r['d'] ?? null);
    $ins = perf_insured($r['payment_type'] ?? '', $r['insurance_id'] ?? 0);
    return [
        'date' => substr((string)($r['d'] ?? ''), 0, 10), 'visit_no' => $r['visit_no'] ?? '', 'patient_no' => $r['patient_no'] ?? '',
        'patient' => $r['pname'] ?? '', 'gender' => $r['gender'] ?? '', 'age' => $age === 99 ? '' : $age,
        'payer' => $ins ? ($r['ins_name'] ?: 'Insurance') : 'Self-pay', 'info' => $r['info'] ?? '',
        'fee' => isset($r['fee']) ? (float)$r['fee'] : null, 'visit_rev' => isset($r['visit_rev']) ? (float)$r['visit_rev'] : null, 'visit_paid' => isset($r['visit_paid']) ? (float)$r['visit_paid'] : null,
        // keys used for counting
        '_pid' => (int)($r['pid'] ?? 0), '_age' => $age, '_f' => strtoupper(substr((string)($r['gender'] ?? ''), 0, 1)) === 'F',
        '_ins' => $ins, '_insid' => (int)($r['insurance_id'] ?? 0), '_insname' => $r['ins_name'] ?: 'Insurance', '_vid' => (int)($r['visit_id'] ?? 0),
    ];
}
function perf_strip(array $rows): array {
    return array_map(fn($r) => array_filter($r, fn($k) => $k[0] !== '_', ARRAY_FILTER_USE_KEY), array_values($rows));
}
// Patient-mix metrics from rows (one row per patient is picked for patient-based counts)
function perf_mix(array $rows, array &$lists): array {
    $seen = [];
    foreach ($rows as $r) {
        if (!$r['_pid'] || isset($seen[$r['_pid']])) continue;
        $seen[$r['_pid']] = true;
        $lists['patients'][] = $r;
        $a = $r['_age'];
        if ($a < 15) $lists['children'][] = $r;
        if ($a < 5) $lists['under5'][] = $r;
        if ($a >= 15) { $lists['adults'][] = $r; ($r['_f'] ? $lists['women'][] = $r : $lists['men'][] = $r); }
        if ($r['_ins']) { $lists['insured'][] = $r; $lists['ins:' . $r['_insid']][] = $r; } else $lists['self_pay'][] = $r;
    }
    return $lists;
}
function perf_counts(array $lists): array {
    $m = [];
    foreach ($lists as $k => $l) $m[$k] = count($l);
    return $m;
}
function perf_insurers(array $lists, array $names): array {
    $o = [];
    foreach ($lists as $k => $l) if (strpos($k, 'ins:') === 0) $o[] = ['key' => $k, 'name' => $names[$k] ?? 'Insurance', 'n' => count($l)];
    return $o;
}

function perf_staff_names(PDO $pdo, array $ids): array {
    if (!$ids) return [];
    $q = $pdo->query("SELECT id, TRIM(CONCAT(first_name,' ',last_name)) AS n FROM users WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")");
    return $q->fetchAll(PDO::FETCH_KEY_PAIR);
}
function perf_emit(array $staff, array $filters, string $note = ''): void {
    $detail = $_GET['detail'] ?? '';
    if ($detail !== '') { // drill-down: one staff member + one metric
        $sid = (int)($_GET['staff_id'] ?? 0);
        $rows = $staff[$sid]['lists'][$detail] ?? [];
        ok(['rows' => perf_strip($rows)]);
    }
    $out = [];
    foreach ($staff as $id => $s) {
        $out[] = ['id' => $id, 'name' => $s['name'], 'counts' => perf_counts($s['lists']) + ($s['extra'] ?? []),
                  'insurers' => perf_insurers($s['lists'], $s['ins_names'] ?? []), 'depts' => $s['depts'] ?? []];
    }
    ok(['staff' => $out] + $filters + ['note' => $note]);
}
function perf_filters(PDO $pdo): array {
    return [
        'departments' => $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
        'insurers' => $pdo->query("SELECT id, name FROM insurance_companies WHERE id <> " . PERF_PRIVATE_INSURER . " ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
    ];
}

// ===== DOCTOR PERFORMANCE =====
if ($action === 'doctor_performance') {
    $from = $_GET['date_from'] ?? date('Y-m-01'); $to = $_GET['date_to'] ?? date('Y-m-d');
    $self = perf_self_only($pdo);
    $docId = $self ?: (int)($_GET['doctor_id'] ?? 0);
    $deptId = (int)($_GET['department_id'] ?? 0);
    $insF = $_GET['insurance_id'] ?? ''; // '', 'self', or insurer id

    $w = ["v.visit_date >= ? AND v.visit_date < DATE_ADD(?, INTERVAL 1 DAY)", "c.doctor_id IS NOT NULL", "c.doctor_id > 0"];
    $p = [$from, $to];
    if ($docId) { $w[] = "c.doctor_id = ?"; $p[] = $docId; }
    if ($deptId) { $w[] = "c.department_id = ?"; $p[] = $deptId; }
    $hasPh = perf_table($pdo, 'pharmacy_prescriptions');
    $rxSql = $hasPh ? "EXISTS (SELECT 1 FROM pharmacy_prescriptions pp WHERE pp.consultation_id = c.id OR (pp.visit_id = c.visit_id AND pp.doctor_id = c.doctor_id))" : "0";
    $q = $pdo->prepare("SELECT c.id AS cid, c.doctor_id, c.fee_amount AS fee, c.outcome, v.visit_date AS d,
            (c.prescription IS NOT NULL AND c.prescription NOT IN ('', '[]', 'null')) AS legacy_rx, $rxSql AS rx,
            (SELECT COALESCE(SUM(GREATEST(i.total - COALESCE(i.consultation_fee_amount,0), 0)),0) FROM invoices i WHERE i.visit_id = v.id AND i.status <> 'cancelled') AS visit_rev,
            (SELECT COALESCE(SUM(i.paid_amount),0) FROM invoices i WHERE i.visit_id = v.id AND i.status <> 'cancelled') AS visit_paid,
            " . PERF_COLS . "
        FROM consultations c JOIN visits v ON v.id = c.visit_id JOIN patients p ON p.id = v.patient_id " . PERF_JOINS . "
        WHERE " . implode(' AND ', $w));
    $q->execute($p);
    $byDoc = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $row = perf_row($r + ['info' => (string)$r['outcome']]);
        if ($insF === 'self' && $row['_ins']) continue;
        if (ctype_digit((string)$insF) && (!$row['_ins'] || $row['_insid'] !== (int)$insF)) continue;
        $row += ['_doc' => (int)$r['doctor_id'], '_cid' => (int)$r['cid'], '_rx' => $r['rx'] || $r['legacy_rx'], '_outcome' => $r['outcome']];
        $byDoc[(int)$r['doctor_id']][] = $row;
    }
    // orders by these doctors (lab / radiology / procedure), same visit-date window
    $ordersBy = [];
    $o = $pdo->prepare("SELECT mo.ordered_by, mo.order_type, mo.description AS info, v.visit_date AS d, " . PERF_COLS . "
        FROM medical_orders mo JOIN visits v ON v.id = mo.visit_id JOIN patients p ON p.id = v.patient_id " . PERF_JOINS . "
        WHERE mo.ordered_by > 0 AND mo.status <> 'cancelled' AND mo.order_type IN ('lab','radiology','procedure')
          AND v.visit_date >= ? AND v.visit_date < DATE_ADD(?, INTERVAL 1 DAY)" . ($docId ? " AND mo.ordered_by = " . $docId : ""));
    $o->execute([$from, $to]);
    foreach ($o->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $row = perf_row($r); $row['_type'] = $r['order_type'];
        $ordersBy[(int)$r['ordered_by']][] = $row;
    }
    $dxSet = []; // [doctor][visit] => ordered lab/imaging
    foreach ($ordersBy as $d => $ords) foreach ($ords as $r) if ($r['_type'] !== 'procedure') $dxSet[$d][$r['_vid']] = true;
    $names = perf_staff_names($pdo, array_keys($byDoc));
    $staff = [];
    $groups = $byDoc;
    $allCons = []; $allOrd = [];
    foreach ($byDoc as $d => $_) { $allCons = array_merge($allCons, $byDoc[$d]); $allOrd = array_merge($allOrd, $ordersBy[$d] ?? []); }
    if (count($byDoc) > 1) { $groups[0] = $allCons; $ordersBy[0] = $allOrd; $names[0] = 'ALL DOCTORS (distinct)'; }
    foreach ($groups as $d => $cons) {
        $lists = ['consultations' => $cons];
        perf_mix($cons, $lists);
        $ords = $ordersBy[$d] ?? [];
        $labPt = []; $imgPt = [];
        foreach ($ords as $r) {
            if ($r['_type'] === 'procedure') { $lists['procedures'][] = $r; continue; }
            if ($r['_type'] === 'lab' && !isset($labPt[$r['_pid']])) { $labPt[$r['_pid']] = true; $lists['lab'][] = $r; }
            if ($r['_type'] === 'radiology' && !isset($imgPt[$r['_pid']])) { $imgPt[$r['_pid']] = true; $lists['imaging'][] = $r; }
        }
        $seenV = []; $fee = 0; $shared = 0; $paid = 0; $ins = [];
        foreach ($cons as $r) {
            if ($r['_outcome'] === 'admitted') $lists['admitted'][] = $r;
            if ($r['_outcome'] === 'referred') $lists['referred_out'][] = $r;
            $fee += (float)$r['fee'];
            if (!isset($seenV[$r['_vid']])) { $seenV[$r['_vid']] = true; $shared += (float)$r['visit_rev']; $paid += (float)$r['visit_paid']; } // once per visit
            if ($r['_ins']) $ins['ins:' . $r['_insid']] = $r['_insname'];
            // Rx-only: prescription and no lab/imaging order by this doctor on the visit
            if ($r['_rx'] && empty($dxSet[$r['_doc']][$r['_vid']])) $lists['rx_only'][] = $r;
        }
        $staff[$d] = ['name' => $names[$d] ?? ('Doctor #' . $d), 'lists' => $lists, 'ins_names' => $ins,
                      'extra' => ['fee' => $fee, 'shared_rev' => $shared, 'paid_rev' => $paid]];
    }
    if ($self) $note = 'Showing your own figures only.'; else $note = '';
    $f = perf_filters($pdo);
    unset($names[0]);
    $f['doctors'] = $self ? [] : array_map(fn($id, $n) => ['id' => $id, 'name' => $n], array_keys($names), $names);
    perf_emit($staff, $f, $note);
}

// ===== NURSE PERFORMANCE =====
if ($action === 'nurse_performance') {
    $from = $_GET['date_from'] ?? date('Y-m-01'); $to = $_GET['date_to'] ?? date('Y-m-d');
    $self = perf_self_only($pdo);
    $win = "v.visit_date >= ? AND v.visit_date < DATE_ADD(?, INTERVAL 1 DAY)";
    $byN = []; // nurse id => metric => rows
    $add = function (array $rows, string $uk, string $metric) use (&$byN) {
        foreach ($rows as $r) {
            $u = (int)$r[$uk];
            if ($u <= 0) continue;
            $byN[$u][$metric][] = perf_row($r);
        }
    };
    $run = function (string $sql, array $prm) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($prm); return $s->fetchAll(PDO::FETCH_ASSOC); };
    $dateWin = fn($col) => "$col >= ? AND $col < DATE_ADD(?, INTERVAL 1 DAY)";

    // patients received / triaged: distinct visits with vitals recorded by the nurse
    $add($run("SELECT x.recorded_by AS u, 'Vitals recorded' AS info, v.visit_date AS d, " . PERF_COLS . "
        FROM (SELECT visit_id, recorded_by FROM vital_signs_records WHERE recorded_by > 0 GROUP BY visit_id, recorded_by) x
        JOIN visits v ON v.id = x.visit_id JOIN patients p ON p.id = v.patient_id " . PERF_JOINS . " WHERE $win", [$from, $to]), 'u', 'received');

    // referrals to departments: history rows, then activity_log rows not already covered
    $depts = $pdo->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_KEY_PAIR);
    $refSeen = [];
    $refs = $run("SELECT h.assigned_by AS u, h.department_id AS dept, d.name AS info, v.visit_date AS d, " . PERF_COLS . "
        FROM visit_department_history h JOIN visits v ON v.id = h.visit_id JOIN patients p ON p.id = v.patient_id " . PERF_JOINS . "
        LEFT JOIN departments d ON d.id = h.department_id WHERE h.assigned_by > 0 AND $win", [$from, $to]);
    foreach ($refs as $r) $refSeen[$r['visit_id'] . '|' . $r['dept'] . '|' . $r['u']] = true;
    $acts = !perf_table($pdo, 'activity_log') ? [] : $run("SELECT a.user_id AS u, a.description AS info0, v.visit_date AS d, " . PERF_COLS . "
        FROM activity_log a JOIN visits v ON v.id = a.record_id JOIN patients p ON p.id = v.patient_id " . PERF_JOINS . "
        WHERE a.module = 'visits' AND a.description LIKE '%assigned to consultation: dept_id=%' AND a.user_id > 0 AND $win", [$from, $to]);
    foreach ($acts as $r) {
        if (!preg_match('/dept_id=(\d+)/', $r['info0'], $m)) continue;
        if (isset($refSeen[$r['visit_id'] . '|' . $m[1] . '|' . $r['u']])) continue;
        $refSeen[$r['visit_id'] . '|' . $m[1] . '|' . $r['u']] = true;
        $refs[] = $r + ['dept' => $m[1], 'info' => $depts[$m[1]] ?? ('Dept #' . $m[1])];
    }
    foreach ($refs as $r) { $add([$r], 'u', 'referrals'); $add([$r], 'u', 'ref:' . $r['dept']); }

    // medications / injections given
    $add($run("SELECT nr.administered_by AS u, COALESCE(nr.description, nr.record_type) AS info, nr.created_at AS d, " . PERF_COLS . "
        FROM nursing_records nr JOIN patients p ON p.id = nr.patient_id LEFT JOIN visits v ON v.id = nr.visit_id " . PERF_JOINS . "
        WHERE nr.administered_by > 0 AND " . $dateWin('nr.created_at'), [$from, $to]), 'u', 'meds');
    // other nursing records (notes, vitals, services): the medication / injection rows are the 'meds' metric above
    $add($run("SELECT nr.administered_by AS u, COALESCE(nr.description, nr.record_type) AS info, nr.created_at AS d, " . PERF_COLS . "
        FROM nursing_records nr JOIN patients p ON p.id = nr.patient_id LEFT JOIN visits v ON v.id = nr.visit_id " . PERF_JOINS . "
        WHERE nr.administered_by > 0 AND nr.record_type NOT IN ('medication','injection') AND " . $dateWin('nr.created_at'), [$from, $to]), 'u', 'nursing_notes');
    if (perf_table($pdo, 'emergency_treatments')) {
        $add($run("SELECT et.administered_by AS u, COALESCE(et.description, et.treatment_type) AS info, et.administered_at AS d, " . PERF_COLS . "
            FROM emergency_treatments et JOIN emergency_cases ec ON ec.id = et.case_id JOIN patients p ON p.id = ec.patient_id
            LEFT JOIN visits v ON v.id = 0 " . PERF_JOINS . "
            WHERE et.administered_by > 0 AND " . $dateWin('et.administered_at'), [$from, $to]), 'u', 'meds');
    }
    if (perf_table($pdo, 'medication_administrations')) {
        $add($run("SELECT ma.administered_by AS u, 'Ward dose given' AS info, ma.administered_at AS d, " . PERF_COLS . "
            FROM medication_administrations ma JOIN patients p ON p.id = ma.patient_id LEFT JOIN visits v ON v.id = ma.visit_id " . PERF_JOINS . "
            WHERE ma.administered_by > 0 AND " . $dateWin('ma.administered_at'), [$from, $to]), 'u', 'meds');
    }
    // procedures completed
    $add($run("SELECT mo.completed_by AS u, mo.description AS info, mo.completed_at AS d, " . PERF_COLS . "
        FROM medical_orders mo JOIN patients p ON p.id = mo.patient_id LEFT JOIN visits v ON v.id = mo.visit_id " . PERF_JOINS . "
        WHERE mo.order_type = 'procedure' AND mo.completed_by > 0 AND " . $dateWin('mo.completed_at'), [$from, $to]), 'u', 'procedures');
    // visits completed
    $add($run("SELECT v.completed_by AS u, v.status AS info, v.visit_date AS d, " . PERF_COLS . "
        FROM visits v JOIN patients p ON p.id = v.patient_id " . PERF_JOINS . " WHERE v.completed_by > 0 AND $win", [$from, $to]), 'u', 'completed');

    // everything else the nurse did in the system (activity_log rows by user in the date range)
    $actN = [];
    if (perf_table($pdo, 'activity_log')) {
        foreach ($run("SELECT user_id AS u, COUNT(*) AS n FROM activity_log WHERE user_id > 0 AND action <> 'DENIED' AND " . $dateWin('created_at') . " GROUP BY user_id", [$from, $to]) as $r) $actN[(int)$r['u']] = (int)$r['n'];
    }
    $ids = $self ? [$self] : array_values(array_unique(array_merge(array_keys($byN), array_keys(array_intersect_key($actN, $byN)))));
    $names = perf_staff_names($pdo, $ids);
    $staff = [];
    foreach ($ids as $u) {
        $m = $byN[$u] ?? [];
        $lists = $m;
        $mix = [];
        perf_mix($m['received'] ?? [], $mix); // patient mix of the patients received
        $lists += $mix;
        $dpt = [];
        foreach ($m as $k => $l) if (strpos($k, 'ref:') === 0) $dpt[] = ['key' => $k, 'name' => $depts[(int)substr($k, 4)] ?? 'Unknown', 'n' => count($l)];
        $ins = [];
        foreach ($mix as $k => $l) if (strpos($k, 'ins:') === 0) $ins[$k] = $l[0]['_insname'];
        $staff[$u] = ['name' => $names[$u] ?? ('User #' . $u), 'lists' => $lists, 'depts' => $dpt, 'ins_names' => $ins, 'extra' => ['activity' => $actN[$u] ?? 0]];
    }
    $f = perf_filters($pdo);
    ok(perf_emit_nurse($staff, $f, $self));
}
function perf_emit_nurse(array $staff, array $f, int $self): array {
    $detail = $_GET['detail'] ?? '';
    if ($detail !== '') {
        $rows = $staff[(int)($_GET['staff_id'] ?? 0)]['lists'][$detail] ?? [];
        return ['rows' => perf_strip($rows)];
    }
    $out = [];
    foreach ($staff as $id => $s) {
        $c = perf_counts($s['lists']) + ($s['extra'] ?? []);
        $out[] = ['id' => $id, 'name' => $s['name'], 'counts' => array_filter($c, fn($k) => strpos($k, 'ref:') !== 0, ARRAY_FILTER_USE_KEY),
                  'insurers' => perf_insurers($s['lists'], $s['ins_names']), 'depts' => $s['depts']];
    }
    return ['staff' => $out, 'note' => ($self ? 'Showing your own figures only. ' : '') . 'Figures recorded before the recording fixes are incomplete (some actions were not attributed to a user).'];
}

// ===== REPEAT DIAGNOSES (flags raised when a diagnosis recurs) =====
if ($action === 'repeat_diagnoses') {
    $from = $_GET['date_from'] ?? date('Y-m-01'); $to = $_GET['date_to'] ?? date('Y-m-d');
    if (!perf_table($pdo, 'diagnosis_recurrence_flags')) ok([]);
    $q = $pdo->prepare("SELECT f.id, f.created_at, f.diagnosis, f.occurrences, f.window_days, f.action, f.justification,
            p.patient_no, CONCAT(p.first_name,' ',p.last_name) AS patient_name, v.visit_no,
            TRIM(CONCAT(u.first_name,' ',u.last_name)) AS user_name
        FROM diagnosis_recurrence_flags f LEFT JOIN patients p ON p.id = f.patient_id LEFT JOIN visits v ON v.id = f.visit_id LEFT JOIN users u ON u.id = f.user_id
        WHERE f.created_at >= ? AND f.created_at < DATE_ADD(?, INTERVAL 1 DAY) ORDER BY f.created_at DESC");
    $q->execute([$from, $to]);
    ok($q->fetchAll(PDO::FETCH_ASSOC));
}
