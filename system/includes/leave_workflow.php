<?php
// includes/leave_workflow.php
// Two-step approval shared by leave_requests and permission_requests:
//   pending (awaiting HR) → hr_reviewed (awaiting manager) → approved | rejected
//   HR may also reject at its step; the employee may cancel until the final decision.
// HR step: hr_manager or admin. Final step: admin.

function lw_me(PDO $pdo): array {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $st = $pdo->prepare("SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?
                         UNION SELECT role FROM users WHERE id = ?");
    $st->execute([$uid, $uid]);
    $slugs = $st->fetchAll(PDO::FETCH_COLUMN);
    $emp = $pdo->prepare("SELECT e.id FROM users u JOIN employees e ON e.employee_no = u.employee_id WHERE u.id = ?");
    $emp->execute([$uid]);
    $isAdmin = in_array('admin', $slugs, true);
    return ['uid' => $uid, 'emp_id' => (int)$emp->fetchColumn(), 'is_admin' => $isAdmin,
            'is_hr' => $isAdmin || in_array('hr_manager', $slugs, true)];
}

function lw_users_with_role(PDO $pdo, string $slug): array {
    $st = $pdo->prepare("SELECT DISTINCT u.id FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
                         WHERE u.is_active = 1 AND (r.slug = ? OR u.role = ?)");
    $st->execute([$slug, $slug]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

// HR officers, or the admins when nobody holds the HR role yet
function lw_hr_users(PDO $pdo): array {
    return lw_users_with_role($pdo, 'hr_manager') ?: lw_users_with_role($pdo, 'admin');
}

function lw_notify(PDO $pdo, array $userIds, string $title, string $msg): void {
    $st = $pdo->prepare("INSERT INTO notifications (type, title, message, target_user_id) VALUES ('leave', ?, ?, ?)");
    foreach (array_unique($userIds) as $u) if ($u) $st->execute([$title, $msg, $u]);
}

function lw_employee_user(PDO $pdo, int $empId): int {
    $st = $pdo->prepare("SELECT u.id FROM employees e JOIN users u ON u.employee_id = e.employee_no WHERE e.id = ?");
    $st->execute([$empId]);
    return (int)$st->fetchColumn();
}

// Leave balance for the year of $date: allowed, used (approved), pending (in workflow), remaining (null = unlimited)
function lw_balance(PDO $pdo, int $empId, ?string $date = null): array {
    $year = (int)date('Y', strtotime($date ?: 'today'));
    $st = $pdo->prepare("SELECT lt.id, lt.name, lt.code, lt.days_allowed, lt.is_paid,
               COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN lr.days_requested END), 0) AS used,
               COALESCE(SUM(CASE WHEN lr.status IN ('pending','hr_reviewed') THEN lr.days_requested END), 0) AS pending
        FROM leave_types lt
        LEFT JOIN leave_requests lr ON lr.leave_type_id = lt.id AND lr.employee_id = ? AND YEAR(lr.start_date) = ?
        WHERE lt.is_active = 1 GROUP BY lt.id ORDER BY lt.name");
    $st->execute([$empId, $year]);
    $out = [];
    foreach ($st->fetchAll() as $r) {
        $r['used'] = (int)$r['used']; $r['pending'] = (int)$r['pending']; $r['days_allowed'] = (int)$r['days_allowed'];
        // ponytail: carry_forward is not applied, each year starts from days_allowed; add when HR needs carried days
        $r['remaining'] = $r['days_allowed'] > 0 ? $r['days_allowed'] - $r['used'] : null;
        $r['year'] = $year;
        $out[(int)$r['id']] = $r;
    }
    return $out;
}

// Move a request one step. $kind: 'leave' | 'permission'. Returns the success message.
function lw_act(PDO $pdo, string $kind, int $id, string $action, string $notes, array $me): string {
    $table = $kind === 'leave' ? 'leave_requests' : 'permission_requests';
    $label = $kind === 'leave' ? 'leave request' : 'permission request';
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT * FROM $table WHERE id = ? FOR UPDATE");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) throw new RuntimeException('Request not found', 404);
        $emp = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM employees WHERE id = ?");
        $emp->execute([$r['employee_id']]);
        $empName = (string)$emp->fetchColumn();
        $when = $kind === 'leave' ? "{$r['start_date']} to {$r['end_date']} ({$r['days_requested']} day(s))"
                                  : $r['request_date'] . ' ' . substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5);
        $ownerUser = lw_employee_user($pdo, (int)$r['employee_id']);
        $st = $r['status'];

        if ($action === 'forward' || ($action === 'reject' && $st === 'pending')) {
            if (!$me['is_hr']) throw new RuntimeException('Only HR can review this request', 403);
            if ($st !== 'pending') throw new RuntimeException('This request is no longer awaiting HR review', 409);
            if ($action === 'reject' && trim($notes) === '') throw new RuntimeException('Give the reason for rejecting', 422);
            $new = $action === 'forward' ? 'hr_reviewed' : 'rejected';
            $pdo->prepare("UPDATE $table SET status = ?, hr_reviewed_by = ?, hr_reviewed_at = NOW(), hr_notes = ?"
                . ($new === 'rejected' ? ", reviewed_by = ?, reviewed_at = NOW(), review_notes = ?" : '') . " WHERE id = ?")
                ->execute($new === 'rejected' ? [$new, $me['uid'], $notes, $me['uid'], $notes, $id] : [$new, $me['uid'], $notes, $id]);
            if ($new === 'hr_reviewed') {
                lw_notify($pdo, array_diff(lw_users_with_role($pdo, 'admin'), [$me['uid']]), ucfirst($label) . ' awaiting your approval',
                    "$empName — $when. Reviewed by HR" . ($notes !== '' ? ": $notes" : '.'));
                $msg = 'Forwarded to the manager for approval';
            } else {
                lw_notify($pdo, [$ownerUser], 'Your ' . $label . ' was rejected', "$when. Reason: $notes");
                $msg = 'Request rejected';
            }
        } elseif ($action === 'approve' || $action === 'reject') {
            if (!$me['is_admin']) throw new RuntimeException('Only the manager (admin) can give the final decision', 403);
            if ($st !== 'hr_reviewed') throw new RuntimeException('HR must review this request first', 409);
            if ($action === 'reject' && trim($notes) === '') throw new RuntimeException('Give the reason for rejecting', 422);
            $extra = '';
            if ($kind === 'leave' && $action === 'approve') {
                $bal = lw_balance($pdo, (int)$r['employee_id'], $r['start_date'])[(int)$r['leave_type_id']] ?? null;
                if ($bal && $bal['remaining'] !== null && $r['days_requested'] > $bal['remaining'])
                    throw new RuntimeException("Not enough balance: {$bal['remaining']} day(s) of {$bal['name']} left", 409);
                if ($bal && $bal['remaining'] !== null) $extra = ' ' . ($bal['remaining'] - $r['days_requested']) . " day(s) of {$bal['name']} left for {$bal['year']}.";
            }
            $new = $action === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare("UPDATE $table SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_notes = ? WHERE id = ?")
                ->execute([$new, $me['uid'], $notes, $id]);
            lw_notify($pdo, [$ownerUser], "Your $label was $new", $when . '.' . ($notes !== '' ? " Note: $notes." : '') . $extra);
            lw_notify($pdo, [(int)$r['hr_reviewed_by']], ucfirst($label) . " $new", "$empName — $when.");
            $msg = 'Request ' . $new;
        } elseif ($action === 'cancel') {
            if ((int)$r['employee_id'] !== $me['emp_id'] && !$me['is_hr']) throw new RuntimeException('You can only cancel your own request', 403);
            if (!in_array($st, ['pending', 'hr_reviewed'], true)) throw new RuntimeException('Only a request still in review can be cancelled', 409);
            $pdo->prepare("UPDATE $table SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            $msg = 'Request cancelled';
        } else {
            throw new RuntimeException('Unknown action', 422);
        }
        $pdo->commit();
        return $msg;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Submitted → each HR officer gets a notification
function lw_submitted(PDO $pdo, string $kind, int $empId, string $when): void {
    $emp = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM employees WHERE id = ?");
    $emp->execute([$empId]);
    lw_notify($pdo, lw_hr_users($pdo), 'New ' . ($kind === 'leave' ? 'leave' : 'permission') . ' request to review', $emp->fetchColumn() . " — $when");
}

// Schema (idempotent; called from migrate.php)
function lw_migrate(PDO $pdo, callable $log): void {
    $col = function (string $t, string $c) use ($pdo): bool {
        $q = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $q->execute([$t, $c]);
        return (bool)$q->fetchColumn();
    };
    $pdo->exec("CREATE TABLE IF NOT EXISTS permission_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        request_date DATE NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL,
        reason TEXT,
        status ENUM('pending','hr_reviewed','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
        proof_document LONGBLOB NULL, proof_document_name VARCHAR(255) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_employee (employee_id), KEY idx_status (status)
    ) DEFAULT CHARSET=utf8mb4");
    foreach (['leave_requests', 'permission_requests'] as $t) {
        $q = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'status'");
        $q->execute([$t]);
        if (strpos((string)$q->fetchColumn(), 'hr_reviewed') === false) {
            $pdo->exec("ALTER TABLE $t MODIFY status ENUM('pending','hr_reviewed','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'");
            $log("~ $t.status: added hr_reviewed stage");
        }
        foreach (['created_by' => 'INT NULL', 'hr_reviewed_by' => 'INT NULL', 'hr_reviewed_at' => 'DATETIME NULL', 'hr_notes' => 'TEXT NULL',
                  'reviewed_by' => 'INT NULL', 'reviewed_at' => 'DATETIME NULL', 'review_notes' => 'TEXT NULL'] as $c => $def) {
            if (!$col($t, $c)) { $pdo->exec("ALTER TABLE $t ADD COLUMN $c $def"); $log("+ $t.$c"); }
        }
    }
    if (!$col('notifications', 'target_user_id')) {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN target_user_id INT NULL, ADD KEY idx_notif_user (target_user_id, is_read)");
        $log('+ notifications.target_user_id');
    }
    // Every staff member requests their own leave: give all roles the Leave page (the page itself scopes what they see)
    $n = $pdo->exec("INSERT INTO role_menu_access (role_id, menu_item_id)
        SELECT r.id, i.id FROM roles r JOIN nav_menu_items i ON i.url = 'hr.php?module=hr&sub=leave'
        WHERE NOT EXISTS (SELECT 1 FROM role_menu_access a WHERE a.role_id = r.id AND a.menu_item_id = i.id)");
    if ($n) $log("+ Leave page for $n role(s)");
}
