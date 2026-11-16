<?php
// handlers/insurance/companies.php
require_once __DIR__ . '/../../includes/database.php';

// ============================================
// HELPERS
// ============================================
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

        if ($id) {
            // Single company
            $stmt = $pdo->prepare("SELECT * FROM insurance_companies WHERE id = ?");
            $stmt->execute([$id]);
            $company = $stmt->fetch();
            if (!$company) error('Company not found', 404);
            success($company);
        }

        // List + stats
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = "(name LIKE ? OR code LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
            $like     = "%{$search}%";
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($status !== '') {
            $where[]  = "status = ?";
            $params[] = $status;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM insurance_companies $whereSQL");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Rows
        $stmt = $pdo->prepare("
            SELECT * FROM insurance_companies
            $whereSQL
            ORDER BY created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute($params);
        $companies = $stmt->fetchAll();

        // Stats
        $statsStmt = $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'active')   AS active,
                SUM(status = 'inactive') AS inactive,
                SUM(status = 'pending')  AS pending,
                SUM(status = 'expired')  AS expired
            FROM insurance_companies
        ");
        $stats = $statsStmt->fetch();

        success([
            'companies'  => $companies,
            'stats'      => $stats,
            'pagination' => [
                'total'        => $total,
                'page'         => $page,
                'limit'        => $limit,
                'total_pages'  => ceil($total / $limit)
            ]
        ]);
    }

    // ==================== POST ====================
    if ($method === 'POST') {
        if (empty($input['name'])) error("Company name is required", 422);
        $baseCode = strtoupper(trim($input['code'] ?? substr(preg_replace('/[^A-Z]/', '', strtoupper($input['name'])), 0, 4)));
        
        // Auto-generate unique code if duplicate
        $code = $baseCode;
        $suffix = 1;
        while (true) {
            $chk = $pdo->prepare("SELECT id FROM insurance_companies WHERE code = ?");
            $chk->execute([$code]);
            if (!$chk->fetch()) break;
            $suffix++;
            $code = $baseCode . $suffix;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO insurance_companies
                    (name, code, contact_person, email, phone, website, address,
                     agreement_start_date, agreement_end_date, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($input['name']),
                $code,
                trim($input['contact_person'] ?? ''),
                trim($input['email'] ?? ''),
                trim($input['phone'] ?? ''),
                trim($input['website'] ?? ''),
                trim($input['address'] ?? ''),
                $input['agreement_start_date'] ?? null,
                $input['agreement_end_date'] ?? null,
                $input['status'] ?? 'active',
                trim($input['notes'] ?? '')
            ]);

            $newId = (int)$pdo->lastInsertId();

            // Create COA account under Accounts Receivable (parent_id=7) for this insurance
            // Find next available account code
            $lastCode = $pdo->query("SELECT account_code FROM chart_of_accounts WHERE account_code LIKE '1120%' AND parent_id=7 ORDER BY account_code DESC LIMIT 1")->fetchColumn();
            $nextNum = 1;
            if ($lastCode) {
                $num = (int)substr($lastCode, 4);
                $nextNum = $num + 1;
            }
            $accCode = '1120' . str_pad($nextNum, 2, '0', STR_PAD_LEFT);
            $accName = 'Receivable - ' . trim($input['name']);
            $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_id, status, is_default, has_sub, linked_insurance_id) VALUES (?,?,'asset',7,'active',0,0,?)")
                ->execute([$accCode, $accName, $newId]);

            $pdo->commit();
            $fetchSt = $pdo->prepare("SELECT * FROM insurance_companies WHERE id = ?");
            $fetchSt->execute([$newId]);
            success($fetchSt->fetch(), 'Insurance company created with COA account', 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Failed: ' . $e->getMessage());
        }
    }

    // ==================== PUT ====================
    if ($method === 'PUT') {
        if (!$id) error('Company ID is required', 422);

        $chkExist = $pdo->prepare("SELECT id FROM insurance_companies WHERE id = ?");
        $chkExist->execute([$id]);
        if (!$chkExist->fetch()) error('Company not found', 404);

        $required = ['name','code','contact_person','email','phone','address','agreement_start_date','agreement_end_date','status'];
        foreach ($required as $f) {
            if (empty($input[$f])) error("Field '{$f}' is required", 422);
        }
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) error('Invalid email address', 422);

        // Duplicate code check (exclude self)
        $chk = $pdo->prepare("SELECT id FROM insurance_companies WHERE code = ? AND id != ?");
        $chk->execute([strtoupper($input['code']), $id]);
        if ($chk->fetch()) error('Company code already used by another company', 409);

        $stmt = $pdo->prepare("
            UPDATE insurance_companies SET
                name = ?, code = ?, contact_person = ?, email = ?, phone = ?,
                website = ?, address = ?, agreement_start_date = ?,
                agreement_end_date = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($input['name']),
            strtoupper(trim($input['code'])),
            trim($input['contact_person']),
            trim($input['email']),
            trim($input['phone']),
            trim($input['website'] ?? ''),
            trim($input['address']),
            $input['agreement_start_date'],
            $input['agreement_end_date'],
            $input['status'],
            trim($input['notes'] ?? ''),
            $id
        ]);

        $fetchSt = $pdo->prepare("SELECT * FROM insurance_companies WHERE id = ?");
        $fetchSt->execute([$id]);
        success($fetchSt->fetch(), 'Insurance company updated successfully');
    }

    // ==================== DELETE ====================
    if ($method === 'DELETE') {
        if (!$id) error('Company ID is required', 422);

        $chkExist = $pdo->prepare("SELECT id FROM insurance_companies WHERE id = ?");
        $chkExist->execute([$id]);
        if (!$chkExist->fetch()) error('Company not found', 404);

        // Check linked packages
        $pkgChk = $pdo->prepare("SELECT COUNT(*) FROM insurance_packages WHERE company_id = ?");
        $pkgChk->execute([$id]);
        if ((int)$pkgChk->fetchColumn() > 0) {
            error('Cannot delete company with existing packages. Delete packages first.', 409);
        }

        $stmt = $pdo->prepare("DELETE FROM insurance_companies WHERE id = ?");
        $stmt->execute([$id]);
        success(null, 'Insurance company deleted successfully');
    }

    error('Method not allowed', 405);

} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error('Server error: ' . $e->getMessage(), 500);
}
?>
