<?php
// includes/referral_schema.php — nurse referral follow-up (#31): who referred, priority, critical info, and nurse comments on doctor notes.
// Idempotent; called from handlers/patients/visits.php so it self-heals.

function ensure_referral_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $has = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='visit_department_history' AND COLUMN_NAME=?");
    foreach ([
        'referred_by'   => 'INT NULL',
        'priority'      => "VARCHAR(10) NOT NULL DEFAULT 'routine'",
        'critical_info' => 'TEXT NULL',
        'referred_at'   => 'DATETIME NULL',
    ] as $col => $def) {
        $has->execute([$db, $col]);
        if (!(int)$has->fetchColumn()) $pdo->exec("ALTER TABLE visit_department_history ADD COLUMN `$col` $def");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinical_note_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        consultation_id INT NULL,
        user_id INT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_visit (visit_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
