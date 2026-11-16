<?php
// includes/visit-routing.php
// A visit can be with several departments at once: one visit_department_history row per department.
// Row status: pending (waiting) -> in_progress (doctor started) -> completed, or cancelled (nurse re-routed / removed it).
// visits.department_id stays the "primary" active department so screens that read it keep working.

// SQL join for department queues: h = this department's latest (non-cancelled) assignment of visit v
function vrDeptJoin(int $deptId): string {
    // 0 = all departments (admin queue view): the latest non-cancelled assignment of each visit per department
    if ($deptId === 0) return "LEFT JOIN visit_department_history h ON h.visit_id = v.id AND h.status <> 'cancelled'"
            . " AND h.id = (SELECT MAX(h2.id) FROM visit_department_history h2 WHERE h2.visit_id = v.id AND h2.department_id = h.department_id AND h2.status <> 'cancelled')";
    return "LEFT JOIN visit_department_history h ON h.id = (SELECT MAX(h2.id) FROM visit_department_history h2
            WHERE h2.visit_id = v.id AND h2.department_id = $deptId AND h2.status <> 'cancelled')";
}

// History rows (with department + doctor names) for the given visits
function vrRows(PDO $pdo, array $visitIds, array $statuses = ['pending', 'in_progress', 'completed']): array {
    $visitIds = array_map('intval', $visitIds);
    if (!$visitIds) return [];
    $in = implode(',', $visitIds);
    $st = implode(',', array_map([$pdo, 'quote'], $statuses));
    return $pdo->query("
        SELECT h.id, h.visit_id, h.department_id, h.assigned_to, h.status, h.doctor_notes, h.created_at,
               d.name AS department_name,
               TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS doctor_name
        FROM visit_department_history h
        LEFT JOIN departments d ON d.id = h.department_id
        LEFT JOIN users u ON u.id = h.assigned_to
        WHERE h.visit_id IN ($in) AND h.status IN ($st)
        ORDER BY h.id
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function vrActive(PDO $pdo, int $visitId, ?int $deptId = null): array {
    $rows = vrRows($pdo, [$visitId], ['pending', 'in_progress']);
    return $deptId ? array_values(array_filter($rows, fn($r) => (int)$r['department_id'] === $deptId)) : $rows;
}

// Send the patient to a department. One active doctor per department.
function vrAssign(PDO $pdo, int $visitId, int $deptId, ?int $doctorId, ?string $notes = null): int {
    if ($dup = vrActive($pdo, $visitId, $deptId)) {
        throw new RuntimeException('Patient is already with ' . ($dup[0]['department_name'] ?: 'this department'));
    }
    $pid = $pdo->prepare("SELECT patient_id FROM visits WHERE id = ?");
    $pid->execute([$visitId]);
    $pdo->prepare("
        INSERT INTO visit_department_history (visit_id, patient_id, department_id, assigned_by, assigned_to, status, doctor_notes)
        VALUES (?, ?, ?, ?, ?, 'pending', ?)
    ")->execute([$visitId, (int)$pid->fetchColumn(), $deptId, $_SESSION['user_id'] ?? null, $doctorId ?: null, $notes]);
    return (int)$pdo->lastInsertId();
}

// Nurse takes a department back. Refused once the doctor has started (row in progress, or anything written/ordered).
function vrCancel(PDO $pdo, int $historyId): array {
    $st = $pdo->prepare("SELECT h.*, d.name AS department_name FROM visit_department_history h LEFT JOIN departments d ON d.id = h.department_id WHERE h.id = ?");
    $st->execute([$historyId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !in_array($row['status'], ['pending', 'in_progress'], true)) throw new RuntimeException('Assignment not found');

    $c = $pdo->prepare("
        SELECT c.id, c.status,
               CONCAT_WS('', c.chief_complaint, c.history, c.examination, c.diagnosis, c.treatment_plan, c.notes, c.prescription) AS written,
               (SELECT COUNT(*) FROM medical_orders o WHERE o.consultation_id = c.id) AS orders
        FROM consultations c WHERE c.visit_id = ? AND c.department_id = ?
    ");
    $c->execute([$row['visit_id'], $row['department_id']]);
    $consult = $c->fetch(PDO::FETCH_ASSOC);

    $started = $row['status'] !== 'pending'
        || ($consult && ($consult['status'] !== 'draft' || trim((string)$consult['written']) !== '' || $consult['orders'] > 0));
    if ($started) {
        throw new RuntimeException(($row['department_name'] ?: 'The doctor') . ' already started this consultation. Ask the doctor to refer the patient.');
    }

    if ($consult) $pdo->prepare("DELETE FROM consultations WHERE id = ?")->execute([$consult['id']]);
    $pdo->prepare("UPDATE visit_department_history SET status = 'cancelled', completed_at = NOW() WHERE id = ?")->execute([$historyId]);
    return $row;
}

// Empty consultation so the assigned doctor sees the patient (what assign_consultation always did)
function vrDraftConsult(PDO $pdo, int $visitId, int $deptId, ?int $doctorId, ?string $doctorName): void {
    $chk = $pdo->prepare("SELECT id FROM consultations WHERE visit_id = ? AND department_id = ?");
    $chk->execute([$visitId, $deptId]);
    if ($chk->fetch()) return;
    $pdo->prepare("
        INSERT INTO consultations (visit_id, patient_id, department_id, doctor_id, doctor_name, status, created_at)
        SELECT id, patient_id, ?, ?, ?, 'draft', NOW() FROM visits WHERE id = ?
    ")->execute([$deptId, $doctorId ?: null, $doctorName ?: null, $visitId]);
}

// Point the visit at one of its active departments. Returns false when no department has the patient any more.
function vrSync(PDO $pdo, int $visitId): bool {
    $active = vrActive($pdo, $visitId);
    if (!$active) return false;

    $st = $pdo->prepare("SELECT department_id, doctor_name, consultation_status FROM visits WHERE id = ?");
    $st->execute([$visitId]);
    $v = $st->fetch(PDO::FETCH_ASSOC);

    $primary = $active[0];
    foreach ($active as $r) if ((int)$r['department_id'] === (int)$v['department_id']) { $primary = $r; break; }
    $sameDept = (int)$primary['department_id'] === (int)$v['department_id'];
    $doctor = trim($primary['doctor_name']) ?: ($sameDept ? $v['doctor_name'] : null);
    $cs = in_array($v['consultation_status'], ['waiting', 'in_consultation', 'now_serving'], true) ? $v['consultation_status'] : 'waiting';

    $pdo->prepare("UPDATE visits SET department_id = ?, doctor_name = ?, consultation_status = ?, status = 'in_progress' WHERE id = ? AND status <> 'cancelled'")
        ->execute([$primary['department_id'], $doctor, $cs, $visitId]);
    return true;
}

// Nothing active left after a nurse removed a department: close the visit if someone already finished, else back to the nurse queue
function vrRelease(PDO $pdo, int $visitId): void {
    if (vrRows($pdo, [$visitId], ['completed'])) {
        vrCloseVisit($pdo, $visitId, null);
        return;
    }
    $pdo->prepare("UPDATE visits SET status = 'open', department_id = NULL, doctor_name = NULL, consultation_status = NULL WHERE id = ? AND status = 'in_progress'")
        ->execute([$visitId]);
}

// Any doctor of the department can continue a colleague's patient: whoever saves the consultation
// becomes this department's doctor for the visit (queue, consultation record and visit show the new name).
function vrTakeOver(PDO $pdo, int $visitId, int $deptId): void {
    $slugs = array_map('strtolower', (array)($_SESSION['user_data']['role_slugs'] ?? []));
    if (!in_array('doctor', $slugs, true)) return;
    $me = (int)($_SESSION['user_id'] ?? 0);
    $u = $pdo->prepare("SELECT TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) FROM users WHERE id = ? AND department_id = ?");
    $u->execute([$me, $deptId]);
    $name = $u->fetchColumn();
    if ($name === false) return;
    $pdo->prepare("UPDATE visit_department_history SET assigned_to = ? WHERE visit_id = ? AND department_id = ? AND status IN ('pending','in_progress')")
        ->execute([$me, $visitId, $deptId]);
    $pdo->prepare("UPDATE consultations SET doctor_id = ?, doctor_name = ? WHERE visit_id = ? AND department_id = ? AND COALESCE(doctor_id, 0) <> ?")
        ->execute([$me, $name, $visitId, $deptId, $me]);
    $pdo->prepare("UPDATE visits SET doctor_name = ? WHERE id = ? AND department_id = ?")->execute([$name, $visitId, $deptId]);
}

function vrStart(PDO $pdo, int $visitId, int $deptId): void {
    $pdo->prepare("UPDATE visit_department_history SET status = 'in_progress' WHERE visit_id = ? AND department_id = ? AND status = 'pending'")
        ->execute([$visitId, $deptId]);
    $pdo->prepare("UPDATE visits SET consultation_status = 'in_consultation', status = 'in_progress' WHERE id = ?")
        ->execute([$visitId]);
}

// Department done. Returns true when the patient is still with another department.
function vrFinish(PDO $pdo, int $visitId, int $deptId): bool {
    $pdo->prepare("UPDATE visit_department_history SET status = 'completed', completed_at = NOW() WHERE visit_id = ? AND department_id = ? AND status IN ('pending','in_progress')")
        ->execute([$visitId, $deptId]);
    return vrSync($pdo, $visitId);
}

/**
 * Update the visit when a department completes its consultation.
 * No outcome given (the consultation screen never sends one) means "treated", unless the patient
 * is waiting for admission. While another department still has the patient (sent to several doctors,
 * or referred on), the visit is left open so the patient stays in that department's queue.
 */
function closeVisitAfterConsultation(PDO $pdo, int $visitId, int $deptId, ?string $outcome): void {
    if (vrFinish($pdo, $visitId, $deptId)) return;
    $v = $pdo->prepare("SELECT department_id FROM visits WHERE id = ?");
    $v->execute([$visitId]);
    if ((int)$v->fetchColumn() !== $deptId) return;
    vrCloseVisit($pdo, $visitId, $outcome);
}

// All doctors are done. The visit closes only once nothing else is waiting (see vrCloseBlockers);
// otherwise it stays in_progress with consultation_status 'done' and closes later from vrTryClose.
function vrCloseVisit(PDO $pdo, int $visitId, ?string $outcome): void {
    if (!$outcome) {
        $adm = $pdo->prepare("SELECT COUNT(*) FROM admission_requests WHERE visit_id = ? AND status IN ('pending','assigned')");
        $adm->execute([$visitId]);
        $outcome = $adm->fetchColumn() ? 'admitted' : 'treated';
    }
    $pdo->prepare("UPDATE visits SET status = 'in_progress', consultation_status = 'done' WHERE id = ? AND status <> 'cancelled'")
        ->execute([$visitId]);
    // admitted / referred out / deceased: the ward or staff close it, not this rule
    if (in_array($outcome, ['discharged', 'treated', 'follow_up'], true)) vrTryClose($pdo, $visitId);
}

// What still keeps a visit open once the doctors are done: lab / imaging results not completed,
// money the patient still owes the clinic (deferred "unpaid" amounts and debts moved to Finance don't count),
// or an admission.
function vrCloseBlockers(PDO $pdo, int $visitId): array {
    $q = function (string $sql) use ($pdo, $visitId) { $s = $pdo->prepare($sql); $s->execute(array_fill(0, substr_count($sql, '?'), $visitId)); return $s->fetchColumn(); };
    $why = [];
    if ($q("SELECT COUNT(*) FROM visit_department_history WHERE visit_id = ? AND status IN ('pending','in_progress')") > 0) $why[] = 'doctor';
    if ($q("SELECT COUNT(*) FROM medical_orders WHERE visit_id = ? AND order_type IN ('lab','radiology') AND status IN ('ordered','in_progress')") > 0) $why[] = 'results';
    $owed = (float)$q("
        SELECT COALESCE(SUM(GREATEST(0, i.balance
            - COALESCE((SELECT SUM(pr.amount_paid) FROM payment_records pr WHERE pr.invoice_id = i.id AND pr.status = 'pending'), 0)
            - COALESCE((SELECT SUM(d.amount) FROM patient_debts d WHERE d.invoice_id = i.id AND d.status = 'outstanding'), 0))), 0)
        FROM invoices i WHERE i.visit_id = ? AND i.status IN ('issued','partial')");
    if ($owed > 1.05) $why[] = 'payment';
    if ($q("SELECT (SELECT COUNT(*) FROM admission_requests WHERE visit_id = ? AND status IN ('pending','assigned'))
               + (SELECT COUNT(*) FROM bed_assignments WHERE visit_id = ? AND status = 'active')") > 0) $why[] = 'admitted';
    return $why;
}

// Close a visit whose doctors are done as soon as nothing is waiting any more.
// Called after a consultation completes, a lab result is completed, and a payment / deferral is recorded.
function vrTryClose(PDO $pdo, ?int $visitId): bool {
    if (!$visitId) return false;
    $v = $pdo->prepare("SELECT status, consultation_status FROM visits WHERE id = ?");
    $v->execute([$visitId]);
    $v = $v->fetch(PDO::FETCH_ASSOC);
    if (!$v || $v['status'] !== 'in_progress' || $v['consultation_status'] !== 'done' || vrCloseBlockers($pdo, $visitId)) return false;
    $pdo->prepare("UPDATE visits SET status = 'completed', closed_at = NOW() WHERE id = ? AND status = 'in_progress'")->execute([$visitId]);
    return true;
}
function vrTryCloseForInvoice(PDO $pdo, int $invoiceId): bool {
    $s = $pdo->prepare("SELECT visit_id FROM invoices WHERE id = ?");
    $s->execute([$invoiceId]);
    return vrTryClose($pdo, (int)$s->fetchColumn() ?: null);
}
