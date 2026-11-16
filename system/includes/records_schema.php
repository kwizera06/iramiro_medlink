<?php
// includes/records_schema.php — clinical archive columns on documents + access log. Additive only (idempotent).
function rec_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    foreach (['patient_id' => 'INT NULL', 'visit_id' => 'INT NULL', 'category' => "VARCHAR(40) NOT NULL DEFAULT 'general'",
              'retention_until' => 'DATE NULL', 'archived_at' => 'DATETIME NULL', 'archived_by' => 'INT NULL'] as $c => $def) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN IF NOT EXISTS $c $def");
    }
    $pdo->exec("ALTER TABLE documents ADD INDEX IF NOT EXISTS idx_documents_patient (patient_id)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS document_access_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        document_id INT NOT NULL,
        version_number INT NULL,
        user_id INT NULL,
        action ENUM('upload','new_version','download','archive','restore','delete','update') NOT NULL,
        ip VARCHAR(45) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_dal_doc (document_id), KEY idx_dal_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
