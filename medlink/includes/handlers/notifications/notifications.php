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

// GET — list notifications
if ($method === 'GET') {
    $targetRole = $_GET['target_role'] ?? '';
    $isRead = isset($_GET['is_read']) ? (int)$_GET['is_read'] : null;
    $limit = (int)($_GET['limit'] ?? 50);
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
        $userId = (int)($input['user_id'] ?? 0);

        if (!$id) error('Notification id required', 422);

        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_by = ?, read_at = NOW() WHERE id = ?");
        $stmt->execute([$userId ?: null, $id]);

        success(null, 'Notification marked as read');
    }

    if ($action === 'mark_all_read') {
        $targetRole = $input['target_role'] ?? '';
        $userId = (int)($input['user_id'] ?? 0);

        $where = ["is_read = 0"];
        $params = [];
        if ($targetRole) {
            $where[] = "(target_role = ? OR target_role = 'all')";
            $params[] = $targetRole;
        }

        $ws = implode(' AND ', $where);
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_by = ?, read_at = NOW() WHERE $ws");
        $stmt->execute(array_merge([$userId ?: null], $params));

        success(null, 'All notifications marked as read');
    }

    error('Invalid action', 400);
}

error('Method not allowed', 405);
