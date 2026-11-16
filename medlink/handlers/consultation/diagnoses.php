<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/consultation/diagnoses.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS diagnoses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20),
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // GET - list/search
    if ($method === 'GET') {
        $search = trim($_GET['search'] ?? '');
        $where = ["status='active'"];
        $params = [];
        if ($search) {
            $like = "%$search%";
            $where[] = "(name LIKE ? OR code LIKE ? OR category LIKE ?)";
            $params = [$like, $like, $like];
        }
        $ws = 'WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM diagnoses $ws ORDER BY name ASC LIMIT 100");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // POST - create new diagnosis
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = trim($input['name'] ?? '');
        if (!$name) error('Diagnosis name required', 422);
        $code = trim($input['code'] ?? '');
        $category = trim($input['category'] ?? '');
        
        // Check duplicate
        $chk = $pdo->prepare("SELECT id FROM diagnoses WHERE name=?");
        $chk->execute([$name]);
        if ($chk->fetch()) {
            // Already exists, just return it
            $stmt = $pdo->prepare("SELECT * FROM diagnoses WHERE name=?");
            $stmt->execute([$name]);
            success($stmt->fetch(), 'Diagnosis already exists');
        }
        
        $pdo->prepare("INSERT INTO diagnoses (code, name, category) VALUES (?,?,?)")
            ->execute([$code, $name, $category]);
        $id = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM diagnoses WHERE id=?");
        $stmt->execute([$id]);
        success($stmt->fetch(), 'Diagnosis created', 201);
    }

    error('Method not allowed', 405);
} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
}
?>
