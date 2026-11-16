<?php
// handlers/hr/departments.php — GET: active list for dropdowns (?all=1: full rows + staff count for Add Department page); POST/PUT/DELETE: manage departments
require_once __DIR__ . '/../../includes/database.php';
function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $id = (int)($_GET['id'] ?? 0);

    if ($method === 'GET') {
        if (!empty($_GET['all'])) {
            $st = $_GET['status'] ?? 'active';   // active | inactive | all
            $flt = $st === 'inactive' ? 'd.is_active = 0' : ($st === 'all' ? '1=1' : 'd.is_active = 1');
            success($pdo->query("SELECT d.id, d.is_active, d.code, d.name, d.description, d.location, d.phone, d.email,
                                        (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.status <> 'terminated') AS staff
                                 FROM departments d WHERE $flt ORDER BY d.name")->fetchAll());
        }
        // ?consult=1: only departments that see patients (have a doctor or a consultation), not support services
        if (!empty($_GET['consult'])) {
            success($pdo->query("SELECT d.id, d.name, d.code FROM departments d
                                 WHERE EXISTS (SELECT 1 FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
                                               WHERE u.department_id = d.id AND (LOWER(u.role) = 'doctor' OR r.slug = 'doctor'))
                                    OR EXISTS (SELECT 1 FROM consultations c WHERE c.department_id = d.id)
                                 ORDER BY d.name ASC")->fetchAll());
        }
        success($pdo->query("SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name ASC")->fetchAll());
    }

    if ($method === 'DELETE') {
        if (!$id) error('Missing id');
        $c = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ? AND status <> 'terminated'");
        $c->execute([$id]);
        if ((int)$c->fetchColumn() > 0) error('This department still has employees. Move them first.', 409);
        try { $pdo->prepare("DELETE FROM departments WHERE id = ?")->execute([$id]); }
        catch (PDOException $e) { error('This department is used by existing records and cannot be deleted. Deactivate it instead.', 409); }
        success(null, 'Department deleted');
    }

    if ($method === 'POST' && ($_GET['action'] ?? '') === 'set_active') {   // activate / deactivate (hidden from lists, records kept)
        $b = json_decode(file_get_contents('php://input'), true) ?: [];
        $on = !empty($b['is_active']) ? 1 : 0;
        if (!$id) error('Missing id');
        if (!$on) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ? AND status <> 'terminated'");
            $c->execute([$id]);
            if ((int)$c->fetchColumn() > 0) error('This department still has employees. Move them first.', 409);
        }
        $pdo->prepare("UPDATE departments SET is_active = ? WHERE id = ?")->execute([$on, $id]);
        success(null, $on ? 'Department activated' : 'Department deactivated');
    }

    if ($method !== 'POST' && $method !== 'PUT') error('Method not allowed', 405);
    $b = json_decode(file_get_contents('php://input'), true) ?: [];
    $name = trim((string)($b['name'] ?? ''));
    $code = strtoupper(trim((string)($b['code'] ?? '')));
    if ($name === '') error('Department name is required', 422);
    if ($code === '') $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));
    if (!empty($b['email']) && !filter_var($b['email'], FILTER_VALIDATE_EMAIL)) error('Invalid email', 422);
    $dup = $pdo->prepare("SELECT 1 FROM departments WHERE code = ? AND id <> ?");
    $dup->execute([$code, $id]);
    if ($dup->fetchColumn()) error("Code $code is already used. Enter a different code.", 422);
    $f = [$code, $name, trim((string)($b['description'] ?? '')) ?: null, trim((string)($b['location'] ?? '')) ?: null,
          trim((string)($b['phone'] ?? '')) ?: null, trim((string)($b['email'] ?? '')) ?: null];
    if ($method === 'POST') {
        $pdo->prepare("INSERT INTO departments (code, name, description, location, phone, email, is_active) VALUES (?,?,?,?,?,?,1)")->execute($f);
        success(['id' => (int)$pdo->lastInsertId()], 'Department added', 201);
    }
    if (!$id) error('Missing id');
    $pdo->prepare("UPDATE departments SET code=?, name=?, description=?, location=?, phone=?, email=? WHERE id=?")->execute(array_merge($f, [$id]));
    success(null, 'Department updated');
} catch (PDOException $e) { error('DB error: '.$e->getMessage(), 500); }
?>
