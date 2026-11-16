<?php
// handlers/notifications/notifications.php

function success($data = null, $msg = 'OK') {
    echo json_encode(['success' => true, 'data' => $data, 'message' => $msg]);
    exit();
}
function error($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

function notification_roles(PDO $pdo, int $userId): array {
    if (!$userId) return [];
    $st = $pdo->prepare("SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?
                         UNION SELECT role FROM users WHERE id = ?");
    $st->execute([$userId, $userId]);
    return array_values(array_filter(array_map('strtolower', $st->fetchAll(PDO::FETCH_COLUMN))));
}

// GET ?action=my_lab_results — lab tests this user ordered that completed in the last 5 min (doctor toast)
if ($method === 'GET' && $action === 'my_lab_results') {
    $st = $pdo->prepare("
        SELECT mo.id, mo.description, CONCAT(p.first_name,' ',p.last_name) AS patient_name, v.visit_no,
               300 - TIMESTAMPDIFF(SECOND, mo.completed_at, NOW()) AS seconds_left
        FROM medical_orders mo
        JOIN patients p ON p.id = mo.patient_id
        LEFT JOIN visits v ON v.id = mo.visit_id
        WHERE mo.order_type = 'lab' AND mo.status = 'completed' AND mo.ordered_by = ?
          AND mo.completed_at >= NOW() - INTERVAL 5 MINUTE
        ORDER BY mo.completed_at DESC LIMIT 20");
    $st->execute([$currentUserId]);
    success($st->fetchAll());
}

// GET — list notifications
if ($method === 'GET') {
    $targetRole = $_GET['target_role'] ?? '';
    $isRead = isset($_GET['is_read']) ? (int)$_GET['is_read'] : null;
    $limit = (int)($_GET['limit'] ?? 50);
    if ($limit < 1 || $limit > 200) error('limit must be between 1 and 200', 422);
    $type = $_GET['type'] ?? '';

    $where = [];
    $params = [];

    if ($targetRole) {
        $where[] = "(n.target_role = ? OR n.target_role = 'all')";
        $params[] = $targetRole;
    }
    if ($isRead !== null) {
        $where[] = "n.is_read = ?";
        $params[] = $isRead;
    }
    if ($type) {
        $where[] = "n.type = ?";
        $params[] = $type;
    }

    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT n.*, 
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               p.patient_no,
               v.visit_no
        FROM notifications n
        LEFT JOIN patients p ON p.id = n.patient_id
        LEFT JOIN visits v ON v.id = n.visit_id
        $ws
        ORDER BY n.created_at DESC
        LIMIT $limit
    ");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    // Also get unread count
    $countWhere = $where;
    $countParams = $params;
    if ($isRead === null) {
        $countWhere[] = "n.is_read = 0";
    }
    $cws = $countWhere ? 'WHERE ' . implode(' AND ', $countWhere) : 'WHERE n.is_read = 0';
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications n $cws");
    $countStmt->execute($isRead === null ? $params : $countParams);
    $unreadCount = (int)$countStmt->fetchColumn();

    success(['notifications' => $notifications, 'unread_count' => $unreadCount]);
}

// POST — mark as read
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? $action;

    if ($action === 'mark_read') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) error('Notification id required', 422);

        $stmt = $pdo->prepare("SELECT id, target_user_id, target_role FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        $note = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$note) error('Notification not found', 404);

        // A notification addressed to one person may only be read by that person.
        if ($note['target_user_id'] !== null && (int)$note['target_user_id'] !== $currentUserId) {
            error('You cannot mark another user\'s notification as read.', 403);
        }
        if ($note['target_user_id'] === null && !empty($note['target_role']) && $note['target_role'] !== 'all'
            && !in_array(strtolower($note['target_role']), notification_roles($pdo, $currentUserId), true)) {
            error('You cannot mark another user\'s notification as read.', 403);
        }

        $pdo->prepare("UPDATE notifications SET is_read = 1, read_by = ?, read_at = NOW() WHERE id = ?")
            ->execute([$currentUserId ?: null, $id]);

        success(null, 'Notification marked as read');
    }

    if ($action === 'mark_all_read') {
        $targetRole = $input['target_role'] ?? '';
        $roles = notification_roles($pdo, $currentUserId);

        // Never mark notifications addressed to other users/roles as read.
        $where = ["is_read = 0", "(
            target_user_id = ?
            OR (target_user_id IS NULL AND (target_role IS NULL OR target_role = 'all'"
                . ($roles ? " OR target_role IN (" . implode(',', array_fill(0, count($roles), '?')) . ")" : '')
                . "))
        )"];
        $params = array_merge([$currentUserId], $roles);
        if ($targetRole) {
            $where[] = "(target_role = ? OR target_role = 'all')";
            $params[] = $targetRole;
        }

        $ws = implode(' AND ', $where);
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_by = ?, read_at = NOW() WHERE $ws");
        $stmt->execute(array_merge([$currentUserId ?: null], $params));

        success(null, 'All notifications marked as read');
    }

    error('Invalid action', 400);
}

error('Method not allowed', 405);
