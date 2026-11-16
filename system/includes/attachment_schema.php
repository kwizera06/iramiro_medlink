<?php
// includes/attachment_schema.php — patient_attachments: signed consent forms etc. attached to a patient / visit. Idempotent.

function ensure_patient_attachments(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        visit_id INT NULL,
        type VARCHAR(30) NOT NULL DEFAULT 'consent',
        file_path VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        note VARCHAR(500) NULL,
        uploaded_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_pa_patient (patient_id), KEY idx_pa_visit (visit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
