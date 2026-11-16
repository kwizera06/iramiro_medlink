<?php
// handlers/finance/department-payment-config.php
// Manage payment workflows and configurations by department

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

    // ===== GET =====
    if ($method === 'GET') {
        // Get single configuration
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT dpc.*, d.name AS department_name
                FROM department_payment_config dpc
                JOIN departments d ON d.id = dpc.department_id
                WHERE dpc.id = ?
            ");
            $stmt->execute([$id]);
            $config = $stmt->fetch();
            if (!$config) error('Configuration not found', 404);

            // Parse JSON payment methods
            if (isset($config['payment_methods'])) {
                $config['payment_methods'] = json_decode($config['payment_methods'], true);
            }

            success($config);
        }

        // Get config by department
        if ($action === 'by_department') {
            $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
            if (!$deptId) error('department_id required', 422);

            $stmt = $pdo->prepare("
                SELECT dpc.*, d.name AS department_name
                FROM department_payment_config dpc
                JOIN departments d ON d.id = dpc.department_id
                WHERE dpc.department_id = ?
            ");
            $stmt->execute([$deptId]);
            $config = $stmt->fetch();

            if ($config && isset($config['payment_methods'])) {
                $config['payment_methods'] = json_decode($config['payment_methods'], true);
            }

            success($config);
        }

        // List all configurations
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $cnt = $pdo->query("SELECT COUNT(*) FROM department_payment_config");
        $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT dpc.*, d.name AS department_name
            FROM department_payment_config dpc
            JOIN departments d ON d.id = dpc.department_id
            ORDER BY d.name
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute();
        $configs = $stmt->fetchAll();

        // Decode JSON payment methods
        foreach ($configs as &$config) {
            if (isset($config['payment_methods'])) {
                $config['payment_methods'] = json_decode($config['payment_methods'], true);
            }
        }

        success([
            'configurations' => $configs,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int)ceil($total / max($limit, 1))
            ]
        ]);
    }

    // ===== POST — Create configuration =====
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($input['department_id'])) error('department_id required', 422);

        $deptId = (int)$input['department_id'];
        $paymentMethods = $input['payment_methods'] ?? ['cash', 'card'];
        $allowsCredit = $input['allows_credit'] ?? 0;
        $creditLimit = $input['credit_limit'] ?? null;
        $requiresDeposit = $input['requires_deposit'] ?? 0;
        $depositPercentage = $input['deposit_percentage'] ?? 0;
        $requiresApprovalAbove = $input['requires_approval_above'] ?? null;
        $approvalRole = $input['approval_role'] ?? null;
        $autoInvoiceOnCheckout = $input['auto_invoice_on_checkout'] ?? 1;
        $invoicePrefix = $input['invoice_prefix'] ?? 'INV';

        // Verify department exists
        $deptChk = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
        $deptChk->execute([$deptId]);
        if (!$deptChk->fetch()) error('Department not found', 404);

        // Check if config already exists
        $existingStmt = $pdo->prepare("SELECT id FROM department_payment_config WHERE department_id = ?");
        $existingStmt->execute([$deptId]);
        if ($existingStmt->fetch()) error('Configuration already exists for this department', 409);

        // Insert configuration
        $stmt = $pdo->prepare("
            INSERT INTO department_payment_config 
            (department_id, payment_methods, allows_credit, credit_limit, requires_deposit,
             deposit_percentage, requires_approval_above, approval_role, auto_invoice_on_checkout,
             invoice_prefix, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $deptId,
            json_encode($paymentMethods),
            $allowsCredit,
            $creditLimit,
            $requiresDeposit,
            $depositPercentage,
            $requiresApprovalAbove,
            $approvalRole,
            $autoInvoiceOnCheckout,
            $invoicePrefix,
            $input['created_by'] ?? null
        ]);

        $configId = (int)$pdo->lastInsertId();

        // Fetch created configuration
        $fetchStmt = $pdo->prepare("
            SELECT dpc.*, d.name AS department_name
            FROM department_payment_config dpc
            JOIN departments d ON d.id = dpc.department_id
            WHERE dpc.id = ?
        ");
        $fetchStmt->execute([$configId]);
        $config = $fetchStmt->fetch();

        if ($config && isset($config['payment_methods'])) {
            $config['payment_methods'] = json_decode($config['payment_methods'], true);
        }

        success($config, 'Department payment configuration created', 201);
    }

    // ===== PUT — Update configuration =====
    if ($method === 'PUT') {
        if (!$id) error('ID required', 422);

        $stmt = $pdo->prepare("SELECT id FROM department_payment_config WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) error('Configuration not found', 404);

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $fields = [];
        $params = [];

        $allowedFields = [
            'payment_methods',
            'allows_credit',
            'credit_limit',
            'requires_deposit',
            'deposit_percentage',
            'requires_approval_above',
            'approval_role',
            'auto_invoice_on_checkout',
            'invoice_prefix'
        ];

        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'payment_methods') {
                    // Handle JSON encoding for payment methods
                    $fields[] = "$f = ?";
                    $params[] = json_encode($input[$f]);
                } else {
                    $fields[] = "$f = ?";
                    $params[] = $input[$f];
                }
            }
        }

        if (!$fields) error('No fields to update', 422);

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;

        $pdo->prepare("
            UPDATE department_payment_config 
            SET " . implode(', ', $fields) . " 
            WHERE id = ?
        ")->execute($params);

        success(null, 'Configuration updated successfully');
    }

    // ===== DELETE =====
    if ($method === 'DELETE') {
        if (!$id) error('ID required', 422);

        $stmt = $pdo->prepare("SELECT id FROM department_payment_config WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) error('Configuration not found', 404);

        $pdo->prepare("DELETE FROM department_payment_config WHERE id = ?")->execute([$id]);
        success(null, 'Configuration deleted successfully');
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
