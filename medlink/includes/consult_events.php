<?php
// includes/consult_events.php
// Per-visit consultation timeline (who did what, when). The consultations row keeps only the latest
// user per step, so when a second doctor continues a file both doctors must be recorded here for audit.

function consult_events_boot(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    schema_once('consult_events', fn() => $pdo->exec("CREATE TABLE IF NOT EXISTS consultation_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visit_id INT NOT NULL,
        consultation_id INT NULL,
        event VARCHAR(80) NOT NULL,
        detail TEXT NULL,
        user_id INT NULL,
        user_name VARCHAR(150) NULL,
        is_handover TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ce_visit (visit_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));
    $done = true;
}

// Records an event for the signed-in user. Flags a handover when that user is not the doctor who
// started the consultation, and mirrors handovers into activity_log.
function consult_event(PDO $pdo, int $visitId, string $event, string $detail = '', ?int $consultId = null): void {
    try {
        consult_events_boot($pdo);
        $uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $uname = '';
        if ($uid) {
            $s = $pdo->prepare("SELECT TRIM(CONCAT(first_name,' ',last_name)) FROM users WHERE id=?");
            $s->execute([$uid]);
            $uname = $s->fetchColumn() ?: ('User #' . $uid);
        }
        $c = $pdo->prepare("SELECT id, doctor_id, doctor_name FROM consultations WHERE visit_id=? ORDER BY id LIMIT 1");
        $c->execute([$visitId]);
        $cons = $c->fetch();
        $consultId = $consultId ?: ($cons ? (int)$cons['id'] : null);
        $handover = ($cons && !empty($cons['doctor_id']) && $uid && (int)$cons['doctor_id'] !== $uid) ? 1 : 0;
        $pdo->prepare("INSERT INTO consultation_events (visit_id, consultation_id, event, detail, user_id, user_name, is_handover) VALUES (?,?,?,?,?,?,?)")
            ->execute([$visitId, $consultId, $event, $detail, $uid, $uname, $handover]);
        if ($handover) {
            log_activity($pdo, 'UPDATE', 'consultations',
                'Handover: ' . $event . ' by ' . $uname . ' on file started by ' . ($cons['doctor_name'] ?: ('doctor #' . $cons['doctor_id'])) . ' (visit #' . $visitId . ')' . ($detail ? ' | ' . $detail : ''),
                $consultId, null);
        }
    } catch (Throwable $e) { /* audit must never break the clinical action */ }
}
