<?php
// includes/edit_audit_schema.php
// Tables for patient edit requests and the edit audit trail. They were used by the code but never created,
// so every patient edit failed after saving. Idempotent; DDL commits, so call it outside a transaction.

function edit_audit_boot(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS edit_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(40) NOT NULL,
        entity_id INT NOT NULL,
        entity_name VARCHAR(200) NULL,
        reason TEXT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        requested_by INT NULL,
        requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT NULL,
        reviewed_at DATETIME NULL,
        review_notes TEXT NULL,
        KEY idx_er_entity (entity_type, entity_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS edit_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(40) NOT NULL,
        entity_id INT NOT NULL,
        entity_name VARCHAR(200) NULL,
        edit_request_id INT NULL,
        data_before LONGTEXT NULL,
        data_after LONGTEXT NULL,
        changed_fields TEXT NULL,
        edited_by INT NULL,
        reason VARCHAR(255) NULL,
        edited_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_eal_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}
