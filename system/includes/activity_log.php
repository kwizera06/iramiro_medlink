<?php
// Idempotent: adds the changes / endpoint columns once (one SHOW COLUMNS per request that logs)
if (!function_exists('al_schema')) {
    function al_schema($pdo) {
        static $done = false;
        if ($done) return true;
        if (!$pdo || $pdo->inTransaction()) return false;   // ALTER would implicitly commit the caller's transaction
        $done = true;
        schema_once('activity_log', function () use ($pdo) {
            $have = $pdo->query("SHOW COLUMNS FROM activity_log")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('changes', $have, true))  $pdo->exec("ALTER TABLE activity_log ADD changes TEXT NULL");
            if (!in_array('endpoint', $have, true)) $pdo->exec("ALTER TABLE activity_log ADD endpoint VARCHAR(120) NULL");
        });
        return true;
    }
}
if (!function_exists('log_activity')) {
    // $changes (optional): field => [old, new]; unchanged fields are dropped, rest shown as an expandable row in activity-log.php
    function log_activity($pdo, $action, $module, $description = '', $record_id = null, $record_ref = null, $changes = null) {
        $GLOBALS['__al_logged'] = true;   // tells the api.php shutdown hook this request already logged itself
        try {
            $cols = al_schema($pdo);
            // Ensure session is started
            if (session_status() === PHP_SESSION_NONE && empty($_SESSION['user_id'])) {
                @session_start();
            }

            // If still no session, just return silently
            if (session_status() === PHP_SESSION_NONE && empty($_SESSION['user_id'])) {
                return;
            }

            $userId   = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $userName = '';

            if ($userId) {
                // Try to get user name from session
                if (!empty($_SESSION['first_name']) && !empty($_SESSION['last_name'])) {
                    $userName = trim($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);
                } elseif (!empty($_SESSION['user_name'])) {
                    $userName = $_SESSION['user_name'];
                } elseif (!empty($_SESSION['username'])) {
                    $userName = $_SESSION['username'];
                }

                // If no name found, query database
                if (!$userName && $pdo) {
                    $s = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM users WHERE id=? LIMIT 1");
                    $s->execute([$userId]);
                    $userName = $s->fetchColumn() ?: ('User #' . $userId);
                }
                $userName = trim($userName);
            }

            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
            if ($ip && strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

            // Ensure PDO is available
            if (!$pdo) {
                return;
            }

            if (is_array($changes)) {
                $changes = array_filter($changes, fn($c) => !is_array($c) || count($c) < 2 || (string)$c[0] !== (string)$c[1]);
                $changes = $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null;
            }
            $endpoint = isset($_GET['endpoint']) ? substr((string)$_GET['endpoint'], 0, 120) : null;

            if (!$cols) {   // columns not installed yet and we are inside a transaction: plain row
                $pdo->prepare("INSERT INTO activity_log (user_id, user_name, action, module, description, record_id, record_ref, ip_address, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                    ->execute([$userId, $userName, $action, $module, $description, $record_id, $record_ref, $ip]);
                return;
            }
            $pdo->prepare(
                "INSERT INTO activity_log (user_id, user_name, action, module, description, record_id, record_ref, ip_address, created_at, changes, endpoint)
                 VALUES (?,?,?,?,?,?,?,?,NOW(),?,?)"
            )->execute([$userId, $userName, $action, $module, $description, $record_id, $record_ref, $ip, $changes, $endpoint]);
        } catch (Exception $e) {
            // Never let logging break the main operation
            // Silently fail - don't throw or log elsewhere
        }
    }
}
