<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/activity-log.php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/activity_log.php';

function ok($d,$m='Success'){ echo json_encode(['success'=>true,'message'=>$m,'data'=>$d]); exit(); }
function er($m,$c=400){ http_response_code($c); echo json_encode(['success'=>false,'message'=>$m]); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') er('GET required', 405);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check administrator privileges
$isAdmin = false;
if (isset($_SESSION['user_data']['role']) && $_SESSION['user_data']['role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['user_data']['role_slugs']) && in_array('admin', $_SESSION['user_data']['role_slugs'])) {
    $isAdmin = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $isAdmin = true;
} elseif (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_roles ur
        JOIN roles r ON r.id = ur.role_id
        WHERE ur.user_id = ? AND r.slug = 'admin'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    if ((int)$stmt->fetchColumn() > 0) {
        $isAdmin = true;
    } else {
        $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $uStmt->execute([$_SESSION['user_id']]);
        if ($uStmt->fetchColumn() === 'admin') {
            $isAdmin = true;
        }
    }
}

if (!$isAdmin) {
    er('Access denied: Activity logs can only be viewed by administrators.', 403);
}

$module  = trim($_GET['module'] ?? '');
$userId  = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : 0;
$from    = !empty($_GET['from']) ? trim($_GET['from']) : date('Y-m-d');
$to      = !empty($_GET['to'])   ? trim($_GET['to'])   : date('Y-m-d');
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page']  ?? 1));
$limit   = min(100, max(5, (int)($_GET['limit'] ?? 50)));
$offset  = ($page - 1) * $limit;

$w = ["a.created_at >= ? AND a.created_at < DATE_ADD(?, INTERVAL 1 DAY)"];
$p = [$from, $to];

al_schema($pdo);   // changes / endpoint columns
// 'deletions' tab = deletion/edit-request decisions plus every DELETE
if ($module === 'deletions') { $w[] = "(a.module = 'deletions' OR a.action = 'DELETE')"; }
elseif ($module) { $w[] = "a.module = ?";   $p[] = $module; }
if ($userId) { $w[] = "a.user_id = ?";  $p[] = $userId; }
if ($search) {
    $w[] = "(a.description LIKE ? OR a.user_name LIKE ? OR a.record_ref LIKE ? OR a.action LIKE ?)";
    $like = "%$search%";
    $p[]  = $like; $p[] = $like; $p[] = $like; $p[] = $like;
}

$ws = 'WHERE ' . implode(' AND ', $w);

// Total count
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_log a $ws");
$cStmt->execute($p);
$total = (int)$cStmt->fetchColumn();

// Rows
$stmt = $pdo->prepare(
    "SELECT a.id, a.user_id, a.user_name, a.action, a.module, a.description,
            a.record_id, a.record_ref, a.ip_address, a.created_at, a.changes, a.endpoint
     FROM activity_log a
     $ws
     ORDER BY a.created_at DESC
     LIMIT $limit OFFSET $offset"
);
$stmt->execute($p);
$rows = $stmt->fetchAll();

// Distinct modules for filter dropdown
$modules = $pdo->query("SELECT DISTINCT module FROM activity_log ORDER BY module")
               ->fetchAll(PDO::FETCH_COLUMN);

// Users who have activity (for filter dropdown)
$users = $pdo->query(
    "SELECT DISTINCT user_id, user_name FROM activity_log WHERE user_id IS NOT NULL ORDER BY user_name"
)->fetchAll();

ok([
    'logs'        => $rows,
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => (int)ceil($total / max($limit, 1)),
    'modules'     => $modules,
    'users'       => $users,
], 'Activity log loaded');
