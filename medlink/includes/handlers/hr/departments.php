<?php
// handlers/hr/departments.php — read-only list for dropdowns
require_once __DIR__ . '/../../includes/database.php';
function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') error('Method not allowed', 405);
try {
    $stmt = $pdo->query("SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC");
    success($stmt->fetchAll());
} catch (PDOException $e) { error('DB error: '.$e->getMessage(), 500); }
?>
