<?php
// includes/duty_status.php — "on duty / off duty" for users, from HR data (attendance, roster, leave).
// users.employee_id links to employees.employee_no. Never throws: any HR table problem means "off duty".

/** @param int[] $userIds  @return array<int,bool> user_id => on duty now */
function duty_on_duty_map(PDO $pdo, array $userIds): array {
    $out = [];
    foreach ($userIds as $uid) $out[(int)$uid] = false;
    if (!$userIds) return $out;
    try {
        $in = implode(',', array_map('intval', $userIds));
        $emp = $pdo->query("SELECT u.id AS uid, e.id AS eid FROM users u JOIN employees e ON e.employee_no = u.employee_id WHERE u.id IN ($in)")->fetchAll(PDO::FETCH_KEY_PAIR);
        $att = $pdo->prepare("SELECT 1 FROM attendance_records WHERE employee_id=? AND work_date=CURDATE() AND check_in IS NOT NULL AND check_out IS NULL LIMIT 1");
        $ros = $pdo->prepare("SELECT 1 FROM roster_shift_assignments a JOIN shift_types st ON st.id=a.shift_type_id
            WHERE a.employee_id=? AND COALESCE(a.status,'scheduled') NOT IN ('cancelled','absent')
              AND ((a.shift_date=CURDATE() AND ((st.start_time<=st.end_time AND CURTIME() BETWEEN st.start_time AND st.end_time) OR (st.start_time>st.end_time AND CURTIME()>=st.start_time)))
                OR (a.shift_date=CURDATE()-INTERVAL 1 DAY AND st.start_time>st.end_time AND CURTIME()<=st.end_time)) LIMIT 1");
        $lv = $pdo->prepare("SELECT 1 FROM leave_requests WHERE employee_id=? AND status='approved' AND start_date<=CURDATE() AND end_date>=CURDATE() LIMIT 1");
        foreach ($emp as $uid => $eid) {
            $lv->execute([$eid]);
            if ($lv->fetchColumn()) continue;
            $att->execute([$eid]);
            if ($att->fetchColumn()) { $out[(int)$uid] = true; continue; }
            $ros->execute([$eid]);
            if ($ros->fetchColumn()) $out[(int)$uid] = true;
        }
    } catch (Throwable $e) { /* off duty */ }
    return $out;
}
