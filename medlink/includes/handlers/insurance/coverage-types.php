<?php
// handlers/insurance/coverage-types.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}
function success($data = null, $message = 'Success', $status = 200) {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
}
function error($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

    // ==================== GET ====================
    if ($method === 'GET') {
        $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';
        $where = $onlyActive ? 'WHERE is_active = 1' : '';

        $stmt = $pdo->query("SELECT * FROM coverage_types $where ORDER BY name ASC");
        success($stmt->fetchAll());
    }

    // ==================== POST ====================
    if ($method === 'POST') {
        if (empty($input['name'])) error("Field 'name' is required", 422);
        if (empty($input['code'])) error("Field 'code' is required", 422);

        $code = strtoupper(trim($input['code']));

        $chk = $pdo->prepare("SELECT id FROM coverage_types WHERE code = ?");
        $chk->execute([$code]);
        if ($chk->fetch()) error('Coverage type code already exists', 409);

        $stmt = $pdo->prepare("
            INSERT INTO coverage_types (name, code, description, is_active)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($input['name']),
            $code,
            trim($input['description'] ?? ''),
            isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1
        ]);

        $newId   = (int)$pdo->lastInsertId();
        $fetchSt = $pdo->prepare("SELECT * FROM coverage_types WHERE id = ?");
        $fetchSt->execute([$newId]);
        success($fetchSt->fetch(), 'Coverage type created successfully', 201);
    }

    // ==================== PUT ====================
    if ($method === 'PUT') {
        if (!$id) error('Coverage type ID is required', 422);

        $chkExist = $pdo->prepare("SELECT id FROM coverage_types WHERE id = ?");
        $chkExist->execute([$id]);
        if (!$chkExist->fetch()) error('Coverage type not found', 404);

        if (empty($input['name'])) error("Field 'name' is required", 422);
        if (empty($input['code'])) error("Field 'code' is required", 422);

        $code = strtoupper(trim($input['code']));

        $chk = $pdo->prepare("SELECT id FROM coverage_types WHERE code = ? AND id != ?");
        $chk->execute([$code, $id]);
        if ($chk->fetch()) error('Coverage type code already used', 409);

        $stmt = $pdo->prepare("
            UPDATE coverage_types SET name = ?, code = ?, description = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($input['name']),
            $code,
            trim($input['description'] ?? ''),
            isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1,
            $id
        ]);

        $fetchSt = $pdo->prepare("SELECT * FROM coverage_types WHERE id = ?");
        $fetchSt->execute([$id]);
        success($fetchSt->fetch(), 'Coverage type updated successfully');
    }

    // ==================== DELETE ====================
    if ($method === 'DELETE') {
        if (!$id) error('Coverage type ID is required', 422);

        $chkExist = $pdo->prepare("SELECT id FROM coverage_types WHERE id = ?");
        $chkExist->execute([$id]);
        if (!$chkExist->fetch()) error('Coverage type not found', 404);

        $stmt = $pdo->prepare("DELETE FROM coverage_types WHERE id = ?");
        $stmt->execute([$id]);
        success(null, 'Coverage type deleted successfully');
    }

    error('Method not allowed', 405);

} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error('Server error: ' . $e->getMessage(), 500);
}
?>
