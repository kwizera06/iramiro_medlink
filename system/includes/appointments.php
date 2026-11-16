<?php
// includes/appointments.php
// Follow-up appointments (rendez-vous): doctor schedules after a consultation → reminders → reception confirms
// arrival by opening a visit (patients/visits POST with appointment_id). Scheduling never creates a visit.
// Status: scheduled → arrived (visit registered) | cancelled | missed. Every change is kept in appointment_history.

const AP_ACTIVE = "('scheduled','arrived')";

function ap_me(PDO $pdo): array {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $st = $pdo->prepare("SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? UNION SELECT role FROM users WHERE id = ?");
    $st->execute([$uid, $uid]);
    $s = $st->fetchAll(PDO::FETCH_COLUMN);
    $admin = in_array('admin', $s, true);
    $d = $pdo->prepare("SELECT department_id FROM users WHERE id = ?"); $d->execute([$uid]);
    $isDoc = in_array('doctor', $s, true);
    $isFront = $admin || (bool)array_intersect(['receptionist', 'nurse'], $s);
    // A doctor (who is not also front desk / admin) works inside own department: sees and handles its appointments plus own
    return ['uid' => $uid, 'dept' => (int)$d->fetchColumn(), 'scoped' => $isDoc && !$isFront, 'is_admin' => $admin, 'is_doctor' => $isDoc,
            'can_schedule' => $admin || (bool)array_intersect(['doctor', 'nurse', 'receptionist'], $s),
            'is_front' => $admin || (bool)array_intersect(['receptionist', 'nurse'], $s)];
}

function ap_log(PDO $pdo, int $id, string $action, ?array $old, ?array $new, string $notes, int $uid): void {
    $pdo->prepare("INSERT INTO appointment_history (appointment_id, action, old_date, old_time, new_date, new_time, notes, user_id) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$id, $action, $old['appointment_date'] ?? null, $old['appointment_time'] ?? null, $new['appointment_date'] ?? null, $new['appointment_time'] ?? null, $notes, $uid ?: null]);
}

// Throws RuntimeException when the slot is in the past or clashes with the doctor's or the patient's other appointments
function ap_check_slot(PDO $pdo, int $doctorId, int $patientId, string $date, string $time, int $minutes, int $exceptId = 0): void {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}/', $time)) throw new RuntimeException('Choose a valid date and time', 422);
    if (strtotime("$date $time") < time()) throw new RuntimeException('The appointment time is in the past', 422);
    $q = $pdo->prepare("SELECT a.appointment_time, CONCAT(p.first_name,' ',p.last_name) AS patient FROM appointments a JOIN patients p ON p.id = a.patient_id
        WHERE a.doctor_id = ? AND a.id <> ? AND a.status IN " . AP_ACTIVE . " AND a.appointment_date = ?
          AND a.appointment_time < ADDTIME(?, SEC_TO_TIME(? * 60)) AND ADDTIME(a.appointment_time, SEC_TO_TIME(a.duration_min * 60)) > ? LIMIT 1");
    $q->execute([$doctorId, $exceptId, $date, $time, $minutes, $time]);
    if ($c = $q->fetch()) throw new RuntimeException('The doctor already has an appointment at ' . substr($c['appointment_time'], 0, 5) . " ({$c['patient']}). Choose another time.", 409);
    $q = $pdo->prepare("SELECT a.appointment_time, CONCAT(u.first_name,' ',u.last_name) AS doctor FROM appointments a LEFT JOIN users u ON u.id = a.doctor_id
        WHERE a.patient_id = ? AND a.id <> ? AND a.status = 'scheduled' AND a.appointment_date = ? LIMIT 1");
    $q->execute([$patientId, $exceptId, $date]);
    if ($c = $q->fetch()) throw new RuntimeException('This patient already has an appointment that day at ' . substr($c['appointment_time'], 0, 5) . " with {$c['doctor']}. Reschedule that one instead.", 409);
}

// Called inside the visit-opening transaction: links the new visit and marks the patient as arrived
function ap_mark_arrived(PDO $pdo, int $apptId, int $visitId, int $patientId, int $uid): void {
    $st = $pdo->prepare("SELECT * FROM appointments WHERE id = ? FOR UPDATE");
    $st->execute([$apptId]);
    $a = $st->fetch();
    if (!$a) throw new RuntimeException('Appointment not found', 404);
    if ((int)$a['patient_id'] !== $patientId) throw new RuntimeException('This appointment belongs to another patient', 422);
    if ($a['status'] !== 'scheduled') throw new RuntimeException("This appointment is already {$a['status']}", 409);
    $pdo->prepare("UPDATE appointments SET status = 'arrived', visit_id = ?, arrived_at = NOW(), arrived_by = ? WHERE id = ?")->execute([$visitId, $uid ?: null, $apptId]);
    ap_log($pdo, $apptId, 'arrived', null, null, 'Arrival confirmed, visit registered', $uid);
}

// Bell reminders for a doctor: the day before and on the day (each sent once)
function ap_send_reminders(PDO $pdo, int $doctorId): void {
    foreach ([['reminded_before', 'CURDATE() + INTERVAL 1 DAY', 'Tomorrow'], ['reminded_day', 'CURDATE()', 'Today']] as [$flag, $day, $label]) {
        $st = $pdo->prepare("SELECT a.id, a.appointment_time, a.reason, CONCAT(p.first_name,' ',p.last_name) AS patient, p.patient_no
            FROM appointments a JOIN patients p ON p.id = a.patient_id
            WHERE a.doctor_id = ? AND a.status = 'scheduled' AND a.appointment_date = $day AND a.$flag = 0");
        $st->execute([$doctorId]);
        foreach ($st->fetchAll() as $a) {
            $pdo->prepare("INSERT INTO notifications (type, title, message, patient_id, target_user_id) SELECT 'appointment', ?, ?, patient_id, ? FROM appointments WHERE id = ?")
                ->execute(["$label at " . substr($a['appointment_time'], 0, 5) . ": follow-up with {$a['patient']}", "{$a['patient_no']} — {$a['reason']}", $doctorId, $a['id']]);
            $pdo->prepare("UPDATE appointments SET $flag = 1 WHERE id = ?")->execute([$a['id']]);
        }
    }
}

function ap_migrate(PDO $pdo, callable $log): void {
    $exists = $pdo->query("SHOW TABLES LIKE 'appointments'")->fetchColumn();
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        department_id INT NULL,
        source_visit_id INT NULL COMMENT 'consultation the follow-up was booked from',
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        duration_min SMALLINT NOT NULL DEFAULT 15,
        reason VARCHAR(255) NOT NULL,
        instructions TEXT NULL,
        status ENUM('scheduled','arrived','cancelled','missed') NOT NULL DEFAULT 'scheduled',
        visit_id INT NULL COMMENT 'visit registered when the patient came back',
        arrived_at DATETIME NULL, arrived_by INT NULL,
        cancel_reason VARCHAR(255) NULL,
        reminded_before TINYINT(1) NOT NULL DEFAULT 0, reminded_day TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_doctor_date (doctor_id, appointment_date), KEY idx_patient (patient_id), KEY idx_date_status (appointment_date, status), KEY idx_visit (visit_id)
    ) DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT NOT NULL,
        action VARCHAR(20) NOT NULL,
        old_date DATE NULL, old_time TIME NULL, new_date DATE NULL, new_time TIME NULL,
        notes VARCHAR(500) NULL,
        user_id INT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_appt (appointment_id)
    ) DEFAULT CHARSET=utf8mb4");
    if (!$exists) $log('+ appointments, appointment_history tables');
}
