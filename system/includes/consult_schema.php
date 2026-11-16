<?php
// includes/consult_schema.php — consultation extras: repeated-illness alert (same diagnosis treated N times in D days) and emergency_treatments.administered_by.
// Settings (hospital_settings): diag_recurrence_threshold (default 4), diag_recurrence_window_days (default 30).
// Idempotent; call outside a transaction (DDL commits).

function consult_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    schema_once('consult_schema', function () use ($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS diagnosis_recurrence_flags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        visit_id INT NOT NULL,
        diagnosis VARCHAR(255) NOT NULL,
        occurrences INT NOT NULL DEFAULT 0,
        window_days INT NOT NULL DEFAULT 30,
        action ENUM('referred','transferred','justified') NOT NULL,
        justification TEXT NULL,
        user_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_drf_patient (patient_id), KEY idx_drf_visit (visit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // who gave the treatment (nurse performance report)
    $has = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='emergency_treatments' AND COLUMN_NAME='administered_by'")->fetchColumn();
    $tbl = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='emergency_treatments'")->fetchColumn();
    if ($tbl && !$has) $pdo->exec("ALTER TABLE emergency_treatments ADD COLUMN administered_by INT NULL");
    });
    // Dental chart + treatment plans (simple tables). Created once.
    schema_once('dental_schema', function () use ($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS dental_tooth_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL, visit_id INT NULL,
            tooth VARCHAR(4) NOT NULL, surface VARCHAR(10) NOT NULL DEFAULT '',
            cond VARCHAR(24) NOT NULL, notes VARCHAR(255) NULL,
            recorded_by INT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_dtr_patient (patient_id, tooth)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS dental_treatment_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL, visit_id INT NULL, title VARCHAR(200) NOT NULL,
            status ENUM('proposed','approved','in_progress','completed','cancelled') NOT NULL DEFAULT 'proposed',
            created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_dtp_patient (patient_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS dental_plan_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            plan_id INT NOT NULL, service_id INT NULL, procedure_name VARCHAR(255) NOT NULL,
            tooth VARCHAR(40) NOT NULL DEFAULT '', stage INT NOT NULL DEFAULT 1, est_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('planned','done','cancelled') NOT NULL DEFAULT 'planned',
            done_visit_id INT NULL, done_at DATETIME NULL,
            KEY idx_dpi_plan (plan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    });
    // Dental plan item <-> the invoice line that bills it (done only once that invoice is paid)
    schema_once('dental_schema2', function () use ($pdo) {
        if (!$pdo->query("SHOW COLUMNS FROM dental_plan_items LIKE 'invoice_item_id'")->fetch())
            $pdo->exec("ALTER TABLE dental_plan_items ADD COLUMN invoice_item_id INT NULL, ADD KEY idx_dpi_inv (invoice_item_id)");
    });
    // Dental tariff repair (Final Tariff Tables FEB2026): DENT-031/033 were loaded without group prices and with one flat facility price; DENT-050 is "per arch".
    $dfix = $pdo->prepare("UPDATE service_catalog SET secondary_health_facility=?, tertiary_health_facility=?, price_public=?, price_private=?, price_non_insured=? WHERE rhic=? AND price_public IS NULL");
    $dfix->execute([34951, 36597, 33377, 36513, 18257, 'RHIC-DENT-031']);
    $dfix->execute([33748, 35829, 32173, 35205, 17602, 'RHIC-DENT-033']);
    $pdo->exec("UPDATE service_catalog SET procedure_name='Wire splinting per arch' WHERE rhic='RHIC-DENT-050' AND procedure_name LIKE '%per quadrant'");
    // The two dental X-ray services go through Imaging (so they can carry an image). Only these two names, only while still under Dentistry.
    $pdo->exec("UPDATE service_catalog SET speciality='Imaging - Dental X-ray'
        WHERE speciality LIKE 'Dentist%' AND (LOWER(TRIM(procedure_name)) LIKE 'panoramic dental radiograph%' OR LOWER(TRIM(procedure_name)) LIKE 'intra%oral%peri%apical%x-ray%')");
}

function recurrence_setting(PDO $pdo, string $key, int $default): int {
    try {
        $s = $pdo->prepare("SELECT setting_value FROM hospital_settings WHERE setting_key=? LIMIT 1");
        $s->execute([$key]);
        $v = (int)$s->fetchColumn();
        return $v > 0 ? $v : $default;
    } catch (Throwable $e) { return $default; }
}

/** Completed consultations (and admission requests) of this patient with the same diagnosis tag inside the window, excluding the current visit. */
function recurrence_count(PDO $pdo, int $patientId, string $tag, int $excludeVisitId = 0): array {
    $threshold = recurrence_setting($pdo, 'diag_recurrence_threshold', 4);
    $window = recurrence_setting($pdo, 'diag_recurrence_window_days', 30);
    $tag = trim($tag);
    $count = 0;
    if ($patientId && $tag !== '') {
        $s = $pdo->prepare("SELECT COUNT(*) FROM consultations
            WHERE patient_id=? AND status='completed' AND visit_id<>? AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND FIND_IN_SET(LOWER(?), LOWER(REPLACE(diagnosis, ', ', ','))) > 0");
        $s->execute([$patientId, $excludeVisitId, $window, $tag]);
        $count = (int)$s->fetchColumn();
        try {
            $col = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='admission_requests' AND COLUMN_NAME IN ('created_at','requested_at') LIMIT 1")->fetchColumn();
            if ($col) {
                $a = $pdo->prepare("SELECT COUNT(*) FROM admission_requests
                    WHERE patient_id=? AND visit_id<>? AND `$col` >= DATE_SUB(NOW(), INTERVAL ? DAY) AND LOWER(diagnosis) LIKE LOWER(?)");
                $a->execute([$patientId, $excludeVisitId, $window, '%' . $tag . '%']);
                $count += (int)$a->fetchColumn();
            }
        } catch (Throwable $e) {}
    }
    // +1 for the visit being treated now
    return ['count' => $count + 1, 'threshold' => $threshold, 'window_days' => $window, 'triggered' => ($count + 1) >= $threshold];
}
