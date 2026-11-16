<?php
// handlers/finance/consultation-fees.php
// Manage consultation fees for departments and consultation types

require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d, $s = 200) {
    http_response_code($s);
    echo json_encode($d);
    exit();
}

function success($d = null, $m = 'Success', $s = 200) {
    jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s);
}

function error($m, $s = 400) {
    jsonResponse(['success' => false, 'message' => $m], $s);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

    // ===== GET — list consultation fees or single fee =====
    if ($method === 'GET') {
        // Get single consultation fee
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT cf.*, d.name AS department_name
                FROM consultation_fees cf
                JOIN departments d ON d.id = cf.department_id
                WHERE cf.id = ?
            ");
            $stmt->execute([$id]);
            $fee = $stmt->fetch();
            if (!$fee) error('Consultation fee not found', 404);
            success($fee);
        }

        // Get fees for specific department
        if ($action === 'by_department') {
            $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
            if (!$deptId) error('department_id required', 422);
            
            $stmt = $pdo->prepare("
                SELECT cf.*, d.name AS department_name
                FROM consultation_fees cf
                JOIN departments d ON d.id = cf.department_id
                WHERE cf.department_id = ? AND cf.is_active = 1
                ORDER BY cf.consultation_type, cf.effective_from DESC
            ");
            $stmt->execute([$deptId]);
            success($stmt->fetchAll());
        }

        // Get current active fee for consultation type
        if ($action === 'current') {
            $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
            $type = $_GET['type'] ?? 'initial';
            if (!$deptId) error('department_id required', 422);
            
            $stmt = $pdo->prepare("
                SELECT cf.*, d.name AS department_name
                FROM consultation_fees cf
                JOIN departments d ON d.id = cf.department_id
                WHERE cf.department_id = ? AND cf.consultation_type = ? 
                  AND cf.is_active = 1
                  AND cf.effective_from <= CURDATE()
                  AND (cf.effective_to IS NULL OR cf.effective_to >= CURDATE())
                ORDER BY cf.effective_from DESC
                LIMIT 1
            ");
            $stmt->execute([$deptId, $type]);
            $fee = $stmt->fetch();
            if (!$fee) error('No active fee found', 404);
            success($fee);
        }

        // List all consultation fees
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $type = $_GET['type'] ?? '';
        $active = isset($_GET['active']) ? (bool)$_GET['active'] : true;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if ($deptId) {
            $where[] = 'cf.department_id = ?';
            $params[] = $deptId;
        }
        if ($type) {
            $where[] = 'cf.consultation_type = ?';
            $params[] = $type;
        }
        if ($active) {
            $where[] = 'cf.is_active = 1';
        }

        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM consultation_fees cf $ws");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT cf.*, d.name AS department_name
            FROM consultation_fees cf
            JOIN departments d ON d.id = cf.department_id
            $ws
            ORDER BY cf.department_id, cf.consultation_type, cf.effective_from DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $fees = $stmt->fetchAll();

        success([
            'fees' => $fees,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / max($limit, 1))
            ]
        ]);
    }

    // ===== POST — create consultation fee =====
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        foreach (['department_id', 'consultation_type', 'base_fee'] as $f) {
            if (empty($input[$f])) error("Field '$f' required", 422);
        }

        $deptId = (int)$input['department_id'];
        $type = $input['consultation_type'];
        $baseFee = (float)$input['base_fee'];
        $insuranceFee = (float)($input['insurance_fee'] ?? $baseFee * 0.7);
        $emergencySurcharge = (float)($input['emergency_surcharge'] ?? 0);
        $effectiveFrom = $input['effective_from'] ?? date('Y-m-d');
        $effectiveTo = $input['effective_to'] ?? null;

        // Verify department exists
        $deptChk = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
        $deptChk->execute([$deptId]);
        if (!$deptChk->fetch()) error('Department not found', 404);

        $pdo->beginTransaction();

        // Deactivate existing fees for this department/type/date combination
        $pdo->prepare("
            UPDATE consultation_fees 
            SET is_active = 0 
            WHERE department_id = ? AND consultation_type = ? 
            AND effective_from <= ? 
            AND (effective_to IS NULL OR effective_to >= ?)
        ")->execute([$deptId, $type, $effectiveFrom, $effectiveFrom]);

        // Insert new fee
        $stmt = $pdo->prepare("
            INSERT INTO consultation_fees 
            (department_id, consultation_type, base_fee, insurance_fee, emergency_surcharge, 
             effective_from, effective_to, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $deptId, $type, $baseFee, $insuranceFee, $emergencySurcharge,
            $effectiveFrom, $effectiveTo,
            $input['created_by'] ?? null
        ]);

        $feeId = (int)$pdo->lastInsertId();
        $pdo->commit();

        // Fetch created fee
        $fetchStmt = $pdo->prepare("
            SELECT cf.*, d.name AS department_name
            FROM consultation_fees cf
            JOIN departments d ON d.id = cf.department_id
            WHERE cf.id = ?
        ");
        $fetchStmt->execute([$feeId]);
        success($fetchStmt->fetch(), 'Consultation fee created successfully', 201);
    }

    // ===== PUT — update consultation fee =====
    if ($method === 'PUT') {
        if (!$id) error('ID required', 422);

        $stmt = $pdo->prepare("SELECT id FROM consultation_fees WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) error('Consultation fee not found', 404);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $fields = [];
        $params = [];

        $allowedFields = ['base_fee', 'insurance_fee', 'emergency_surcharge', 'effective_to', 'is_active'];
        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f = ?";
                $params[] = $input[$f];
            }
        }

        if (!$fields) error('No fields to update', 422);

        $params[] = $id;
        $pdo->prepare("UPDATE consultation_fees SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);

        success(null, 'Consultation fee updated successfully');
    }

    // ===== DELETE =====
    if ($method === 'DELETE') {
        if (!$id) error('ID required', 422);

        $stmt = $pdo->prepare("SELECT id FROM consultation_fees WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) error('Consultation fee not found', 404);

        $pdo->prepare("DELETE FROM consultation_fees WHERE id = ?")->execute([$id]);
        success(null, 'Consultation fee deleted successfully');
    }

    error('Method not allowed', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Server error: ' . $e->getMessage(), 500);
}
?>
