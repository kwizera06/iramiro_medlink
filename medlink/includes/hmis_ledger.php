<?php
// includes/hmis_ledger.php — the records behind every HMIS number.
//
// handlers/reports/hmis.php calls lg() each time it counts something. The page then lets the user open any cell
// and see WHO (patient, doctor) and WHERE (module, record number) the number came from.
//
// Cells are keyed the way the page finds them: a td id ("hv-nc-u5m") or "<tbody id>|<row index>|<value column>".
// $REC: record key => record.  $LG: section => cell => [record keys].  A record may carry q (counted q times) or amt (RWF).

$REC = [];
$LG  = [];

function rec_put(string $key, array $r): string { global $REC; if (!isset($REC[$key])) $REC[$key] = $r; return $key; }
function lg(string $sec, string $cell, ?string $rk): void { global $LG; if ($rk !== null) $LG[$sec][$cell][] = $rk; }
function lg_cell(string $tb, int $row, int $col): string { return "$tb|$row|$col"; }
function lcut($s, int $n = 220): string { $s = trim(preg_replace('/\s+/u', ' ', (string)$s)); return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s; }
function ldate($d): string { return substr((string)$d, 0, 10); }

// Visit row
function recV(array $v): string {
    $id = (int)$v['id'];
    $dx = trim((string)($v['chief_complaint'] ?? ''));
    if ($dx === '') $dx = 'OPD visit · ' . ($v['visit_type'] ?? '') . ' · ' . ($v['payment_type'] ?? '');
    return rec_put("v$id", ['t' => 'Visit', 'pid' => (int)$v['patient_id'], 'pn' => $v['pname'] ?? '', 'pno' => $v['patient_no'] ?? '',
        'dx' => lcut($dx), 'dr' => $v['doctor'] ?? '', 'd' => ldate($v['visit_date'] ?? ''), 'vn' => $v['visit_no'] ?? '',
        'src' => "Reception › visit #$id"]);
}
// Consultation row (consult_id is 0 for an admission-request diagnosis)
function recC(array $c): string {
    $cid = (int)($c['consult_id'] ?? 0);
    $key = $cid ? "c$cid" : 'ar' . (int)($c['visit_id'] ?? 0) . '-' . (int)($c['patient_id'] ?? 0);
    $icd = trim((string)($c['icd_code'] ?? ''));
    return rec_put($key, ['t' => $cid ? 'Consultation' : 'Admission request', 'pid' => (int)$c['patient_id'], 'pn' => $c['pname'] ?? '', 'pno' => $c['patient_no'] ?? '',
        'dx' => lcut(trim(($c['diagnosis'] ?? '') . ($icd !== '' ? " [$icd]" : ''))),
        'dr' => $c['doctor'] ?? '', 'd' => ldate($c['visit_date'] ?? ''), 'vn' => $c['visit_no'] ?? '',
        'src' => $cid ? "Consultation module › consultation #$cid" : 'Ward module › admission request']);
}
// Pharmacy prescription line
function recRx(array $p): string {
    $id = (int)$p['id'];
    return rec_put("rx$id", ['t' => 'Prescription', 'pid' => (int)$p['patient_id'], 'pn' => $p['pname'] ?? '', 'pno' => $p['patient_no'] ?? '',
        'dx' => lcut(trim(($p['med_name'] ?? '') . ' ' . ($p['dosage'] ?? ''))), 'dr' => $p['doctor'] ?? '', 'd' => ldate($p['visit_date'] ?? ''), 'vn' => $p['visit_no'] ?? '',
        'src' => "Pharmacy module › prescription line #$id"]);
}
// Ward admission (bed assignment). $o = extra fields, e.g. ['q' => days]
function recBA(array $b, array $o = []): string {
    $id = (int)$b['id'];
    $x = [];
    if (!empty($b['ward_name'])) $x[] = $b['ward_name'];
    if (!empty($b['diagnosis'])) $x[] = $b['diagnosis'];
    $x[] = 'admitted ' . ldate($b['created_at'] ?? '');
    if (!empty($b['discharged_at'])) $x[] = 'discharged ' . ldate($b['discharged_at']);
    $key = "ba$id" . (isset($o['q']) ? 'q' . $o['q'] : '');
    return rec_put($key, array_merge(['t' => 'Admission', 'pid' => (int)$b['patient_id'], 'pn' => $b['pname'] ?? '', 'pno' => $b['patient_no'] ?? '',
        'dx' => lcut(implode(' · ', $x)), 'dr' => $b['doctor'] ?? '', 'd' => ldate($b['created_at'] ?? ''), 'vn' => $b['visit_no'] ?? '',
        'src' => "Ward module › bed assignment #$id"], $o));
}
// Surgery
function recSurg(array $s): string {
    $id = (int)$s['id'];
    return rec_put("su$id", ['t' => 'Surgery', 'pid' => (int)$s['patient_id'], 'pn' => $s['pname'] ?? '', 'pno' => $s['patient_no'] ?? '',
        'dx' => lcut(($s['procedure_name'] ?? '') . (($s['status'] ?? '') !== '' ? ' · ' . $s['status'] : '')), 'dr' => ($s['surgeon_name'] ?? '') !== '' ? $s['surgeon_name'] : ($s['doctor'] ?? ''),
        'd' => ldate($s['scheduled_date'] ?? ''), 'vn' => $s['visit_no'] ?? '', 'src' => "Theatre module › surgery #$id"]);
}
// Lab order
function recLab(array $o): string {
    $id = (int)$o['id'];
    $res = trim((string)($o['result'] ?? ''));
    if ($res !== '' && $res[0] === '[') {
        $j = json_decode($res, true);
        if (is_array($j)) $res = implode('; ', array_map(fn($p) => trim(($p['test_name'] ?? '') . ': ' . ($p['value'] ?? '')), $j));
    }
    return rec_put("lo$id", ['t' => 'Lab order', 'pid' => (int)($o['patient_id'] ?? 0), 'pn' => $o['pname'] ?? '', 'pno' => $o['patient_no'] ?? '',
        'dx' => lcut(($o['description'] ?? '') . ($res !== '' ? ' → ' . $res : ''), 260), 'dr' => $o['doctor'] ?? '', 'd' => ldate($o['visit_date'] ?? ''), 'vn' => $o['visit_no'] ?? '',
        'src' => "Laboratory module › order #$id"]);
}
// Invoice line / payment / settlement / expense / employee / bed: anything that is not a clinical record
function recOther(string $key, string $type, array $f): string {
    return rec_put($key, array_merge(['t' => $type, 'pid' => 0, 'pn' => '', 'pno' => '', 'dx' => '', 'dr' => '', 'd' => '', 'vn' => '', 'src' => ''], $f));
}
function recAny(array $r): string {
    if (isset($r['med_name'])) return recRx($r);
    if (isset($r['consult_id'])) return recC($r);
    return recV($r);
}

// SELECT fragments so every record carries patient, doctor, date and visit number
const L_PAT   = "CONCAT_WS(' ',p.first_name,p.last_name) AS pname, p.patient_no";
const L_DOC_C = "COALESCE(NULLIF(c.doctor_name,''),NULLIF(v.doctor_name,''),(SELECT CONCAT_WS(' ',u.first_name,u.last_name) FROM users u WHERE u.id=COALESCE(c.doctor_id,v.doctor_id))) AS doctor";
const L_DOC_V = "COALESCE(NULLIF(v.doctor_name,''),(SELECT CONCAT_WS(' ',u.first_name,u.last_name) FROM users u WHERE u.id=v.doctor_id)) AS doctor";
const L_CSEL  = "c.id AS consult_id, c.visit_id, c.patient_id, c.diagnosis, c.icd_code, c.outcome, v.visit_date, v.visit_no, p.gender, p.date_of_birth, " . L_PAT . ", " . L_DOC_C;

// Consultations of the month matching $cond, one row per consultation. $join adds extra joins.
function cq(PDO $pdo, string $from, string $to, string $cond = '1=1', array $params = [], string $join = '', string $extraSel = ''): array {
    $s = $pdo->prepare("SELECT DISTINCT " . L_CSEL . ($extraSel !== '' ? ", $extraSel" : '') . " FROM consultations c JOIN visits v ON v.id=c.visit_id JOIN patients p ON p.id=c.patient_id $join
        WHERE v.visit_date >= ? AND v.visit_date < DATE_ADD(?, INTERVAL 1 DAY) AND v.status<>'cancelled' AND $cond ORDER BY v.visit_date, c.id");
    $s->execute(array_merge([$from, $to], $params));
    return $s->fetchAll(PDO::FETCH_ASSOC);
}
// Put consultation rows into one cell; returns how many
function lgc(string $sec, string $cell, array $rows): int { foreach ($rows as $r) lg($sec, $cell, recC($r)); return count($rows); }

// Ward admissions (bed assignments) matching $cond
function baq(PDO $pdo, string $cond = '1=1', array $params = []): array {
    $s = $pdo->prepare("SELECT ba.id, ba.patient_id, ba.visit_id, ba.created_at, ba.discharged_at, ba.status, ba.notes, ba.assignment_type, p.date_of_birth, v.visit_no, v.payment_type, v.insurance_id,
            COALESCE((SELECT MIN(b2.created_at) FROM bed_assignments b2 WHERE b2.visit_id=ba.visit_id), ba.created_at) AS stay_start,
            " . L_PAT . ", " . L_DOC_V . ", w.name AS ward_name,
            (SELECT c.diagnosis FROM consultations c WHERE c.visit_id=ba.visit_id ORDER BY c.id DESC LIMIT 1) AS diagnosis
        FROM bed_assignments ba JOIN patients p ON p.id=ba.patient_id LEFT JOIN visits v ON v.id=ba.visit_id LEFT JOIN wards w ON w.id=ba.ward_id
        WHERE $cond ORDER BY ba.id");
    $s->execute($params);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

// Which section each ledger cell belongs to is stored in $LG itself (section => cell => records).
// Keep only the sections the user may see, and only the records those cells use.
function ledger_for(array $allowedSections): array {
    global $REC, $LG;
    $cells = []; $used = [];
    foreach ($LG as $sec => $byCell) {
        if (!in_array($sec, $allowedSections, true)) continue;
        foreach ($byCell as $cell => $keys) { $cells[$cell] = $keys; foreach ($keys as $k) $used[$k] = true; }
    }
    return ['cells' => $cells, 'rec' => array_intersect_key($REC, $used)];
}
