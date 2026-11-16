<?php
// handlers/finance/payment-reports.php
// Generate payment and invoice reports for department dashboards

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
$report_type = $_GET['report'] ?? 'summary';
$action = $_GET['action'] ?? '';

try {

    if ($method === 'GET') {

        // ===== DAILY PAYMENT SUMMARY REPORT =====
        if ($report_type === 'daily_summary') {
            $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
            $date = $_GET['date'] ?? date('Y-m-d');

            $where = ['ps.summary_date = ?'];
            $params = [$date];

            if ($deptId) {
                $where[] = 'ps.department_id = ?';
                $params[] = $deptId;
            }

            $ws = implode(' AND ', $where);

            $stmt = $pdo->prepare("
                SELECT ps.*, d.name AS department_name
                FROM payment_summary ps
                LEFT JOIN departments d ON d.id = ps.department_id
                WHERE $ws
                ORDER BY ps.department_id, ps.payment_method
            ");
            $stmt->execute($params);
            $summaryData = $stmt->fetchAll();

            // Calculate totals
            $totalPayments = 0;
            $totalCount = 0;
            $methodBreakdown = [];

            foreach ($summaryData as $row) {
                $totalPayments += (float)$row['total_payments'];
                $totalCount += (int)$row['payment_count'];
                $method = $row['payment_method'];
                if (!isset($methodBreakdown[$method])) {
                    $methodBreakdown[$method] = ['count' => 0, 'amount' => 0];
                }
                $methodBreakdown[$method]['count'] += (int)$row['payment_count'];
                $methodBreakdown[$method]['amount'] += (float)$row['total_payments'];
            }

            success([
                'daily_summary' => $summaryData,
                'totals' => [
                    'total_amount' => $totalPayments,
                    'total_count' => $totalCount,
                    'method_breakdown' => $methodBreakdown
                ],
                'report_date' => $date
            ]);
        }

        // ===== DEPARTMENT PAYMENT STATISTICS =====
        if ($report_type === 'department_stats') {
            $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');

            $whereClause = 'WHERE pr.payment_date BETWEEN ? AND ? AND pr.status = "completed"';
            $params = [$dateFrom, $dateTo];

            if ($deptId) {
                $whereClause .= ' AND pr.department_id = ?';
                $params[] = $deptId;
            }

            // Total payments by payment method
            $stmt = $pdo->prepare("
                SELECT pr.payment_method, COUNT(*) as count, SUM(pr.amount_paid) as total
                FROM payment_records pr
                $whereClause
                GROUP BY pr.payment_method
                ORDER BY total DESC
            ");
            $stmt->execute($params);
            $paymentMethods = $stmt->fetchAll();

            // Daily payment trend
            $trendStmt = $pdo->prepare("
                SELECT DATE(pr.payment_date) as payment_date, COUNT(*) as count, SUM(pr.amount_paid) as total
                FROM payment_records pr
                $whereClause
                GROUP BY DATE(pr.payment_date)
                ORDER BY payment_date ASC
            ");
            $trendStmt->execute($params);
            $dailyTrend = $trendStmt->fetchAll();

            // Department breakdown
            $deptStmt = $pdo->prepare("
                SELECT d.id, d.name, COUNT(pr.id) as payment_count, SUM(pr.amount_paid) as total_amount
                FROM payment_records pr
                LEFT JOIN departments d ON d.id = pr.department_id
                WHERE pr.payment_date BETWEEN ? AND ? AND pr.status = 'completed'
                GROUP BY d.id, d.name
                ORDER BY total_amount DESC
            ");
            $deptStmt->execute([$dateFrom, $dateTo]);
            $departmentBreakdown = $deptStmt->fetchAll();

            success([
                'payment_methods' => $paymentMethods,
                'daily_trend' => $dailyTrend,
                'department_breakdown' => $departmentBreakdown,
                'period' => ['from' => $dateFrom, 'to' => $dateTo]
            ]);
        }

        // ===== INVOICE STATUS REPORT =====
        if ($report_type === 'invoice_status') {
            $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;

            $whereClause = 'WHERE i.department_id IS NOT NULL';
            $params = [];

            if ($deptId) {
                $whereClause = 'WHERE i.department_id = ?';
                $params = [$deptId];
            }

            // Invoice status breakdown
            $stmt = $pdo->prepare("
                SELECT i.status, COUNT(*) as count, SUM(i.total) as total_amount, SUM(i.balance) as pending_amount
                FROM invoices i
                $whereClause
                GROUP BY i.status
                ORDER BY count DESC
            ");
            $stmt->execute($params);
            $statusBreakdown = $stmt->fetchAll();

            // Overdue invoices (30+ days)
            $overdueStmt = $pdo->prepare("
                SELECT i.*, p.first_name, p.last_name, p.patient_no, d.name as department_name,
                       DATEDIFF(CURDATE(), i.created_at) as days_overdue
                FROM invoices i
                JOIN patients p ON p.id = i.patient_id
                LEFT JOIN departments d ON d.id = i.department_id
                $whereClause AND i.status IN ('issued', 'partial')
                  AND DATEDIFF(CURDATE(), i.created_at) >= 30
                ORDER BY i.created_at ASC
            ");
            $overdueStmt->execute($params);
            $overdueInvoices = $overdueStmt->fetchAll();

            success([
                'status_breakdown' => $statusBreakdown,
                'overdue_invoices' => $overdueInvoices,
                'overdue_count' => count($overdueInvoices)
            ]);
        }

        // ===== PAYMENT TRACKING REPORT =====
        if ($report_type === 'payment_tracking') {
            $invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : null;
            $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;

            if (!$invoiceId && !$patientId) {
                error('Either invoice_id or patient_id required', 422);
            }

            $whereClause = 'WHERE 1=1';
            $params = [];

            if ($invoiceId) {
                $whereClause .= ' AND i.id = ?';
                $params[] = $invoiceId;
            } elseif ($patientId) {
                $whereClause .= ' AND i.patient_id = ?';
                $params[] = $patientId;
            }

            // Get invoices with payment details
            $stmt = $pdo->prepare("
                SELECT i.id, i.invoice_no, i.total, i.paid_amount, i.balance, i.status, i.created_at,
                       p.first_name, p.last_name, p.patient_no,
                       COUNT(DISTINCT pr.id) as payment_count,
                       GROUP_CONCAT(DISTINCT pr.payment_method SEPARATOR ', ') as payment_methods,
                       SUM(pr.amount_paid) as total_paid
                FROM invoices i
                JOIN patients p ON p.id = i.patient_id
                LEFT JOIN payment_records pr ON pr.invoice_id = i.id AND pr.status = 'completed'
                $whereClause
                GROUP BY i.id
                ORDER BY i.created_at DESC
            ");
            $stmt->execute($params);
            $invoices = $stmt->fetchAll();

            // Get detailed payment records
            if ($invoiceId) {
                $payStmt = $pdo->prepare("
                    SELECT pr.*, e.name as created_by_name
                    FROM payment_records pr
                    LEFT JOIN employees e ON e.id = pr.created_by
                    WHERE pr.invoice_id = ?
                    ORDER BY pr.payment_date DESC, pr.id DESC
                ");
                $payStmt->execute([$invoiceId]);
                $payments = $payStmt->fetchAll();
            } else {
                $payments = [];
            }

            success([
                'invoices' => $invoices,
                'payments' => $payments
            ]);
        }

        // ===== CONSULTATION FEE REPORT =====
        if ($report_type === 'consultation_fees') {
            $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
            $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');

            $whereClause = 'WHERE c.created_at BETWEEN ? AND ? AND c.fee_status IN ("invoiced", "paid")';
            $params = [$dateFrom, $dateTo];

            if ($deptId) {
                $whereClause .= ' AND c.department_id = ?';
                $params[] = $deptId;
            }

            // Consultation fees collected
            $stmt = $pdo->prepare("
                SELECT d.name as department_name, c.consultation_type, COUNT(*) as count,
                       SUM(c.fee_amount) as total_fees, AVG(c.fee_amount) as avg_fee
                FROM consultations c
                LEFT JOIN departments d ON d.id = c.department_id
                $whereClause
                GROUP BY c.department_id, c.consultation_type
                ORDER BY total_fees DESC
            ");
            $stmt->execute($params);
            $consultationFeeData = $stmt->fetchAll();

            success([
                'consultation_fees' => $consultationFeeData,
                'period' => ['from' => $dateFrom, 'to' => $dateTo]
            ]);
        }

        // ===== DEFAULT: OVERALL SUMMARY REPORT =====
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');

        // Total statistics
        $totalStmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT i.id) as total_invoices,
                SUM(i.total) as total_amount_invoiced,
                SUM(i.paid_amount) as total_amount_paid,
                SUM(i.balance) as total_outstanding,
                COUNT(DISTINCT CASE WHEN i.status = 'paid' THEN i.id END) as paid_count,
                COUNT(DISTINCT CASE WHEN i.status = 'partial' THEN i.id END) as partial_count,
                COUNT(DISTINCT CASE WHEN i.status = 'issued' THEN i.id END) as pending_count
            FROM invoices i
            WHERE i.created_at BETWEEN ? AND ?
        ");
        $totalStmt->execute([$dateFrom, $dateTo]);
        $totals = $totalStmt->fetch();

        // Payment summary
        $payStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_payments,
                SUM(amount_paid) as total_amount,
                COUNT(DISTINCT payment_method) as methods_used
            FROM payment_records
            WHERE payment_date BETWEEN ? AND ? AND status = 'completed'
        ");
        $payStmt->execute([$dateFrom, $dateTo]);
        $paymentSummary = $payStmt->fetch();

        success([
            'summary' => [
                'invoices' => $totals,
                'payments' => $paymentSummary
            ],
            'period' => ['from' => $dateFrom, 'to' => $dateTo]
        ]);

    }

    error('Method not allowed', 405);

} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error('Server error: ' . $e->getMessage(), 500);
}
?>
