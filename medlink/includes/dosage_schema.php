<?php
// includes/dosage_schema.php — prescribed_dosage (doctor's active dose per drug) and dosage_alerts (nurse gave a different dose). Idempotent.

function ensure_dosage_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS prescribed_dosage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        visit_id INT NULL,
        item_id INT NULL,
        drug_code VARCHAR(60) NULL,
        drug_name VARCHAR(200) NOT NULL,
        dose DECIMAL(12,3) NOT NULL,
        unit VARCHAR(20) NOT NULL DEFAULT 'mg',
        frequency VARCHAR(60) NULL,
        route VARCHAR(40) NULL,
        start_date DATE NOT NULL,
        stop_date DATE NULL,
        prescribed_by INT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pd_patient (patient_id, active), KEY idx_pd_visit (visit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS dosage_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        visit_id INT NULL,
        drug VARCHAR(200) NOT NULL,
        prescribed_dose VARCHAR(60) NOT NULL,
        given_dose VARCHAR(60) NOT NULL,
        reason VARCHAR(500) NOT NULL,
        nurse_id INT NULL,
        prescribed_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        acknowledged_by INT NULL,
        acknowledged_at DATETIME NULL,
        KEY idx_da_ack (acknowledged_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
