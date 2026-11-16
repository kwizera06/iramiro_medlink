<?php
// includes/hmis_schema.php — columns / tables the HMIS report needs. Idempotent; call outside a transaction (DDL commits).
//  - hmis_dx_map           diagnosis -> HMIS priority row (see hmis_dxmap.php)
//  - visits.referred_from  'facility' (counter-referral from another facility) | 'school_counselor' | NULL
//  - visits.referred_from_name  name of the referring facility / counselor
//  - employees.nationality
//  - department "Antenatal Care (ANC)"  (the HMIS ANC table counts visits registered in it)

function hmis_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $has = function (string $t, string $c) use ($pdo): bool {
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $q->execute([$t, $c]);
        return (bool)$q->fetchColumn();
    };
    $pdo->exec("CREATE TABLE IF NOT EXISTS hmis_dx_map (
        dx_key VARCHAR(190) NOT NULL PRIMARY KEY,
        row_key VARCHAR(10) NOT NULL,
        updated_by INT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) DEFAULT CHARSET=utf8mb4");
    if (!$has('visits', 'referred_from')) $pdo->exec("ALTER TABLE visits ADD COLUMN referred_from VARCHAR(30) NULL");
    if (!$has('visits', 'referred_from_name')) $pdo->exec("ALTER TABLE visits ADD COLUMN referred_from_name VARCHAR(150) NULL");
    if (!$has('employees', 'nationality')) $pdo->exec("ALTER TABLE employees ADD COLUMN nationality VARCHAR(60) NOT NULL DEFAULT 'Rwandan'");
    $q = $pdo->query("SELECT COUNT(*) FROM departments WHERE LOWER(name) REGEXP '(^|[^a-z])anc([^a-z]|$)|pr[eé]natal|ante ?natal'");
    if (!(int)$q->fetchColumn()) {
        $pdo->exec("INSERT INTO departments (code, name, description, is_active) VALUES ('ANC', 'Antenatal Care (ANC)', 'Antenatal care clinic: visits registered here feed the HMIS ANC table', 1)");
    }
}
