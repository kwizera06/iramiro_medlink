<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/patients/locations.php
// Cascading location data: provinces → districts → sectors → cells → villages
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$parentId = isset($_GET['parent_id']) ? $_GET['parent_id'] : '';

try {
    switch ($type) {
        case 'provinces':
            $stmt = $pdo->query("SELECT id, name FROM provinces ORDER BY name");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $data]);
            break;

        case 'districts':
            if (!empty($parentId)) {
                $stmt = $pdo->prepare("SELECT id, name, province_id FROM districts WHERE province_id = ? AND name NOT LIKE '--%' ORDER BY name");
                $stmt->execute([$parentId]);
            } else {
                $stmt = $pdo->query("SELECT id, name, province_id FROM districts WHERE name NOT LIKE '--%' ORDER BY name");
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $data]);
            break;

        case 'sectors':
            if (!empty($parentId)) {
                $stmt = $pdo->prepare("SELECT id, name, district_id FROM sectors WHERE district_id = ? AND name NOT LIKE '--%' ORDER BY name");
                $stmt->execute([$parentId]);
            } else {
                $stmt = $pdo->query("SELECT id, name, district_id FROM sectors WHERE name NOT LIKE '--%' ORDER BY name");
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $data]);
            break;

        case 'cells':
            if (empty($parentId)) {
                echo json_encode(['success' => false, 'message' => 'sector_id (parent_id) is required']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, name FROM cells WHERE sector_id = ? AND name NOT LIKE '--%' ORDER BY name");
            $stmt->execute([$parentId]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $data]);
            break;

        case 'villages':
            if (empty($parentId)) {
                echo json_encode(['success' => false, 'message' => 'cell_id (parent_id) is required']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT id, name FROM villages WHERE cell_id = ? AND name NOT LIKE '--%' ORDER BY name");
            $stmt->execute([$parentId]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'message' => 'Success', 'data' => $data]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid type. Use: provinces, districts, sectors, cells, or villages']);
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
