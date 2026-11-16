<?php
// handlers/settings/vital-signs.php
// CRUD for vital signs settings + recording patient vitals
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {

// ===== GET =====
if ($method === 'GET') {

    // GET ?action=settings - List all vital sign settings
    if ($action === 'settings' || (!$action && !$id)) {
        $status = $_GET['status'] ?? 'active';
        $where = $status ? "WHERE status = ?" : "";
        $params = $status ? [$status] : [];
        
        $stmt = $pdo->prepare("SELECT * FROM vital_signs_settings $where ORDER BY sort_order, name");
        $stmt->execute($params);
        $signs = $stmt->fetchAll();
        success($signs);
    }

    // GET ?action=records&visit_id=X - Get vital sign records for a visit
    if ($action === 'records') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : null;
        $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;
        
        if (!$visitId && !$patientId) error('visit_id or patient_id required', 422);
        
        $where = [];
        $params = [];
        if ($visitId) { $where[] = "vr.visit_id = ?"; $params[] = $visitId; }
        if ($patientId) { $where[] = "vr.patient_id = ?"; $params[] = $patientId; }
        $ws = implode(' AND ', $where);
        
        $stmt = $pdo->prepare("
            SELECT vr.*, vs.name AS sign_name, vs.slug AS sign_slug, vs.unit,
                   TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS recorded_by_name
            FROM vital_signs_records vr
            JOIN vital_signs_settings vs ON vs.id = vr.vital_sign_id
            LEFT JOIN users u ON u.id = vr.recorded_by
            WHERE $ws
            ORDER BY vs.sort_order, vr.recorded_at DESC
        ");
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        success($records);
    }

    // GET ?id=X - single setting
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM vital_signs_settings WHERE id = ?");
        $stmt->execute([$id]);
        $sign = $stmt->fetch();
        if (!$sign) error('Vital sign not found', 404);
        success($sign);
    }
}

// ===== POST =====
if ($method === 'POST') {

    // POST ?action=record - Record vital signs for a visit
    if ($action === 'record') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        
        $visitId = $input['visit_id'] ?? null;
        $patientId = $input['patient_id'] ?? null;
        $readings = $input['readings'] ?? [];
        
        if (!$visitId || !$patientId) error('visit_id and patient_id are required', 422);
        if (empty($readings)) error('At least one vital sign reading is required', 422);
        
        $recordedBy = $_SESSION['user_id'] ?? null;
        
        // Delete existing records for this visit (overwrite)
        $pdo->prepare("DELETE FROM vital_signs_records WHERE visit_id = ?")->execute([$visitId]);
        
        // Insert new readings
        $stmt = $pdo->prepare("
            INSERT INTO vital_signs_records (visit_id, patient_id, vital_sign_id, value, recorded_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $count = 0;
        foreach ($readings as $reading) {
            $signId = (int)($reading['vital_sign_id'] ?? 0);
            $value = trim($reading['value'] ?? '');
            if ($signId > 0 && $value !== '') {
                $stmt->execute([$visitId, $patientId, $signId, $value, $recordedBy]);
                $count++;
            }
        }
        
        success(['recorded' => $count], "Vital signs recorded successfully ($count readings)");
    }

    // POST - Create new vital sign setting
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (empty($input['name'])) error("Field 'name' is required", 422);
    
    $slug = !empty($input['slug']) ? trim($input['slug']) : strtolower(preg_replace('/[^a-z0-9]+/', '_', strtolower($input['name'])));
    
    // Check duplicate
    $chk = $pdo->prepare("SELECT id FROM vital_signs_settings WHERE slug = ?");
    $chk->execute([$slug]);
    if ($chk->fetch()) error('Vital sign with this slug already exists', 409);

    $stmt = $pdo->prepare("
        INSERT INTO vital_signs_settings (name, slug, unit, min_value, max_value, sort_order, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        trim($input['name']),
        $slug,
        trim($input['unit'] ?? ''),
        $input['min_value'] ?? null,
        $input['max_value'] ?? null,
        (int)($input['sort_order'] ?? 0),
        $input['status'] ?? 'active'
    ]);
    
    success(['id' => (int)$pdo->lastInsertId()], 'Vital sign created successfully', 201);
}

// ===== PUT =====
if ($method === 'PUT') {
    if (!$id) error('ID required', 422);
    
    $chk = $pdo->prepare("SELECT id FROM vital_signs_settings WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('Vital sign not found', 404);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (empty($input['name'])) error("Field 'name' is required", 422);

    $slug = !empty($input['slug']) ? trim($input['slug']) : strtolower(preg_replace('/[^a-z0-9]+/', '_', strtolower($input['name'])));
    
    // Check duplicate (exclude self)
    $dupChk = $pdo->prepare("SELECT id FROM vital_signs_settings WHERE slug = ? AND id != ?");
    $dupChk->execute([$slug, $id]);
    if ($dupChk->fetch()) error('Slug already used by another vital sign', 409);

    $stmt = $pdo->prepare("
        UPDATE vital_signs_settings 
        SET name = ?, slug = ?, unit = ?, min_value = ?, max_value = ?, sort_order = ?, status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        trim($input['name']),
        $slug,
        trim($input['unit'] ?? ''),
        $input['min_value'] ?? null,
        $input['max_value'] ?? null,
        (int)($input['sort_order'] ?? 0),
        $input['status'] ?? 'active',
        $id
    ]);
    
    success(null, 'Vital sign updated successfully');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('ID required', 422);
    
    $chk = $pdo->prepare("SELECT id FROM vital_signs_settings WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('Vital sign not found', 404);
    
    $pdo->prepare("DELETE FROM vital_signs_settings WHERE id = ?")->execute([$id]);
    success(null, 'Vital sign deleted successfully');
}

error('Method not allowed', 405);

} catch (PDOException $e) {
    error('Database error: '.$e->getMessage(), 500);
} catch (Exception $e) {
    error('Server error: '.$e->getMessage(), 500);
}
?>
