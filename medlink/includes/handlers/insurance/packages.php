<?php
// handlers/insurance/packages.php
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

// Fetch a package with its coverage types
function fetchPackageWithCoverage(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS company_name, c.code AS company_code
        FROM insurance_packages p
        JOIN insurance_companies c ON c.id = p.company_id
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $pkg = $stmt->fetch();
    if (!$pkg) return null;

    $ctStmt = $pdo->prepare("
        SELECT ct.id, ct.name, ct.code, ct.description
        FROM package_coverage_types pct
        JOIN coverage_types ct ON ct.id = pct.coverage_type_id
        WHERE pct.package_id = ?
        ORDER BY ct.name ASC
    ");
    $ctStmt->execute([$id]);
    $pkg['coverage_types'] = $ctStmt->fetchAll();
    return $pkg;
}

// Sync coverage types for a package (replace all)
function syncCoverageTypes(PDO $pdo, int $packageId, array $coverageTypeIds): void {
    $del = $pdo->prepare("DELETE FROM package_coverage_types WHERE package_id = ?");
    $del->execute([$packageId]);

    if (!empty($coverageTypeIds)) {
        $ins = $pdo->prepare("INSERT INTO package_coverage_types (package_id, coverage_type_id) VALUES (?, ?)");
        foreach ($coverageTypeIds as $ctId) {
            $ins->execute([$packageId, (int)$ctId]);
        }
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

    // ==================== GET ====================
    if ($method === 'GET') {

        if ($id) {
            $pkg = fetchPackageWithCoverage($pdo, $id);
            if (!$pkg) error('Package not found', 404);
            success($pkg);
        }

        $search    = isset($_GET['search'])     ? trim($_GET['search'])     : '';
        $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
        $status    = isset($_GET['status'])     ? $_GET['status']           : '';
        $page      = max(1, (int)($_GET['page']  ?? 1));
        $limit     = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset    = ($page - 1) * $limit;

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = "(p.name LIKE ? OR p.code LIKE ? OR c.name LIKE ?)";
            $like     = "%{$search}%";
            $params   = array_merge($params, [$like, $like, $like]);
        }
        if ($companyId > 0) {
            $where[]  = "p.company_id = ?";
            $params[] = $companyId;
        }
        if ($status !== '') {
            $where[]  = "p.status = ?";
            $params[] = $status;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM insurance_packages p
            JOIN insurance_companies c ON c.id = p.company_id
            $whereSQL
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS company_name, c.code AS company_code
            FROM insurance_packages p
            JOIN insurance_companies c ON c.id = p.company_id
            $whereSQL
            ORDER BY p.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $packages = $stmt->fetchAll();

        // Attach coverage types to each package
        foreach ($packages as &$pkg) {
            $ctStmt = $pdo->prepare("
                SELECT ct.id, ct.name, ct.code
                FROM package_coverage_types pct
                JOIN coverage_types ct ON ct.id = pct.coverage_type_id
                WHERE pct.package_id = ?
                ORDER BY ct.name ASC
            ");
            $ctStmt->execute([$pkg['id']]);
            $pkg['coverage_types'] = $ctStmt->fetchAll();
        }
        unset($pkg);

        // Stats
        $statsStmt = $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'active')   AS active,
                SUM(status = 'inactive') AS inactive,
                SUM(status = 'pending')  AS pending
            FROM insurance_packages
        ");
        $stats = $statsStmt->fetch();

        success([
            'packages'   => $packages,
            'stats'      => $stats,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
    }

    // ==================== POST ====================
    if ($method === 'POST') {
        $required = ['company_id','name'];
        foreach ($required as $f) {
            if (!isset($input[$f]) || $input[$f] === '' || $input[$f] === null) {
                error("Field '{$f}' is required", 422);
            }
        }

        // Verify company exists
        $chkCo = $pdo->prepare("SELECT id FROM insurance_companies WHERE id = ?");
        $chkCo->execute([(int)$input['company_id']]);
        if (!$chkCo->fetch()) error('Insurance company not found', 404);

        // Auto-generate code if not provided
        $code = !empty($input['code']) ? strtoupper(trim($input['code'])) : strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', $input['name']), 0, 6)) . '-' . rand(10,99);

        // Duplicate code check
        $chk = $pdo->prepare("SELECT id FROM insurance_packages WHERE code = ?");
        $chk->execute([$code]);
        if ($chk->fetch()) $code .= '-' . rand(100,999); // append random if exists

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO insurance_packages
                (company_id, name, code, coverage_limit, copayment_percentage,
                 effective_date, covered_services, exclusions, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$input['company_id'],
            trim($input['name']),
            $code,
            (float)($input['coverage_limit'] ?? 0),
            (float)($input['copayment_percentage'] ?? 0),
            $input['effective_date'] ?? date('Y-m-d'),
            trim($input['covered_services'] ?? ''),
            trim($input['exclusions'] ?? ''),
            $input['status'] ?? 'active',
            trim($input['notes'] ?? '')
        ]);

        $newId = (int)$pdo->lastInsertId();

        // Sync coverage types
        $coverageTypeIds = $input['coverage_type_ids'] ?? [];
        if (!is_array($coverageTypeIds)) $coverageTypeIds = [];
        syncCoverageTypes($pdo, $newId, $coverageTypeIds);

        $pdo->commit();

        $pkg = fetchPackageWithCoverage($pdo, $newId);
        success($pkg, 'Insurance package created successfully', 201);
    }

    // ==================== PUT ====================
    if ($method === 'PUT') {
        if (!$id) error('Package ID is required', 422);

        $chkExist = $pdo->prepare("SELECT id FROM insurance_packages WHERE id = ?");
        $chkExist->execute([$id]);
        if (!$chkExist->fetch()) error('Package not found', 404);

        $required = ['company_id','name','code','coverage_limit','copayment_percentage','effective_date','status'];
        foreach ($required as $f) {
            if (!isset($input[$f]) || $input[$f] === '' || $input[$f] === null) {
                error("Field '{$f}' is required", 422);
            }
        }

        // Verify company exists
        $chkCo = $pdo->prepare("SELECT id FROM insurance_companies WHERE id = ?");
        $chkCo->execute([(int)$input['company_id']]);
        if (!$chkCo->fetch()) error('Insurance company not found', 404);

        // Duplicate code check (exclude self)
        $chk = $pdo->prepare("SELECT id FROM insurance_packages WHERE code = ? AND id != ?");
        $chk->execute([strtoupper($input['code']), $id]);
        if ($chk->fetch()) error('Package code already used by another package', 409);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE insurance_packages SET
                company_id = ?, name = ?, code = ?, coverage_limit = ?,
                copayment_percentage = ?, effective_date = ?, covered_services = ?,
                exclusions = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            (int)$input['company_id'],
            trim($input['name']),
            strtoupper(trim($input['code'])),
            (float)$input['coverage_limit'],
            (float)$input['copayment_percentage'],
            $input['effective_date'],
            trim($input['covered_services'] ?? ''),
            trim($input['exclusions'] ?? ''),
            $input['status'],
            trim($input['notes'] ?? ''),
            $id
        ]);

        $coverageTypeIds = $input['coverage_type_ids'] ?? [];
        if (!is_array($coverageTypeIds)) $coverageTypeIds = [];
        syncCoverageTypes($pdo, $id, $coverageTypeIds);

        $pdo->commit();

        $pkg = fetchPackageWithCoverage($pdo, $id);
        success($pkg, 'Insurance package updated successfully');
    }

    // ==================== DELETE ====================
    if ($method === 'DELETE') {
        if (!$id) error('Package ID is required', 422);

        $chkExist = $pdo->prepare("SELECT id FROM insurance_packages WHERE id = ?");
        $chkExist->execute([$id]);
        if (!$chkExist->fetch()) error('Package not found', 404);

        // CASCADE handles package_coverage_types via FK
        $stmt = $pdo->prepare("DELETE FROM insurance_packages WHERE id = ?");
        $stmt->execute([$id]);
        success(null, 'Insurance package deleted successfully');
    }

    error('Method not allowed', 405);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Server error: ' . $e->getMessage(), 500);
}
?>
