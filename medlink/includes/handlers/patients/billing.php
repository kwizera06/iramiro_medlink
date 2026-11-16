<?php
// handlers/patients/billing.php
require_once __DIR__ . '/../../includes/database.php';

if (!function_exists('jsonResponse')) {
    function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
}
if (!function_exists('success')) {
    function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
}
if (!function_exists('error')) {
    function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}
}


function ensureInvoiceEditLogsTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_edit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            user_id INT NULL,
            user_name VARCHAR(150) NULL,
            action_type VARCHAR(50) NOT NULL DEFAULT 'edit',
            edit_summary TEXT NULL,
            old_values LONGTEXT NULL,
            new_values LONGTEXT NULL,
            edit_reason TEXT NULL,
            ip_address VARCHAR(50) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_invoice_id (invoice_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Clean up fractional amounts in invoices and payment_records (RWF has no cents)
        $pdo->exec("
            UPDATE invoices 
            SET balance = ROUND(balance, 0),
                paid_amount = ROUND(paid_amount, 0),
                total = ROUND(total, 0),
                subtotal = ROUND(subtotal, 0)
            WHERE balance != ROUND(balance, 0) 
               OR paid_amount != ROUND(paid_amount, 0) 
               OR total != ROUND(total, 0)
        ");
        $pdo->exec("
            UPDATE invoices
            SET balance = 0, status = 'paid'
            WHERE balance > 0 AND balance <= 1.05 AND paid_amount > 0 AND status IN ('issued','partial','draft')
        ");
        $pdo->exec("
            UPDATE invoices
            SET balance = 0
            WHERE status = 'paid' AND balance != 0
        ");
        $pdo->exec("
            UPDATE payment_records
            SET amount_paid = ROUND(amount_paid, 0)
            WHERE amount_paid != ROUND(amount_paid, 0)
        ");
        $done = true;
    } catch(Exception $e) {}
}

function isUserAdmin(PDO $pdo, ?int $userId = null): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    }

    $adminRoles = ['admin', 'administrator', 'isadmin'];

    // Check is_admin boolean / truthy flag
    if (!empty($_SESSION['user_data']['is_admin']) || !empty($_SESSION['is_admin'])) {
        return true;
    }

    // Check role slugs in session
    if (isset($_SESSION['user_data']['role_slugs']) && is_array($_SESSION['user_data']['role_slugs'])) {
        foreach ($_SESSION['user_data']['role_slugs'] as $slug) {
            if (in_array(strtolower(trim($slug)), $adminRoles, true)) {
                return true;
            }
        }
    }

    // Check role names in session
    if (isset($_SESSION['user_data']['role_names']) && is_array($_SESSION['user_data']['role_names'])) {
        foreach ($_SESSION['user_data']['role_names'] as $name) {
            if (in_array(strtolower(trim($name)), $adminRoles, true)) {
                return true;
            }
        }
    }

    // Check single role in session
    if (isset($_SESSION['user_data']['role']) && in_array(strtolower(trim($_SESSION['user_data']['role'])), $adminRoles, true)) {
        return true;
    }
    if (isset($_SESSION['role_name']) && in_array(strtolower(trim($_SESSION['role_name'])), $adminRoles, true)) {
        return true;
    }
    if (isset($_SESSION['role']) && in_array(strtolower(trim($_SESSION['role'])), $adminRoles, true)) {
        return true;
    }

    if (!$userId) return false;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND (
                LOWER(r.slug) IN ('admin', 'administrator', 'isadmin')
                OR LOWER(r.name) IN ('admin', 'administrator', 'isadmin')
            )
        ");
        $stmt->execute([$userId]);
        if ((int)$stmt->fetchColumn() > 0) return true;

        $stmt2 = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt2->execute([$userId]);
        $uRole = strtolower(trim((string)$stmt2->fetchColumn()));
        if (in_array($uRole, $adminRoles, true)) {
            return true;
        }
    } catch(Exception $e) {}

    return false;
}

function generateInvoiceNo(PDO $pdo): string {
    $prefix = 'INV-'.date('Ymd').'-';
    $stmt = $pdo->prepare("SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY invoice_no DESC LIMIT 1");
    $stmt->execute([$prefix.'%']);
    $last = $stmt->fetchColumn();
    $seq  = $last ? ((int)substr($last,-4)+1) : 1;
    return $prefix.str_pad($seq,4,'0',STR_PAD_LEFT);
}

function getPatientCopayPercentage(PDO $pdo, ?int $patientId, ?int $insuranceId): float {
    if (!$patientId) return 100.0;
    
    // 1. Try matching patient_insurance by id AND patient_id
    if ($insuranceId) {
        $stmt = $pdo->prepare("
            SELECT ip.copayment_percentage
            FROM patient_insurance pi
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            WHERE pi.id = ? AND pi.patient_id = ?
        ");
        $stmt->execute([$insuranceId, $patientId]);
        $row = $stmt->fetch();
        if ($row && $row['copayment_percentage'] !== null) {
            return (float)$row['copayment_percentage'];
        }

        // 2. Try matching patient_insurance by company_id AND patient_id
        $stmt2 = $pdo->prepare("
            SELECT ip.copayment_percentage
            FROM patient_insurance pi
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            WHERE pi.company_id = ? AND pi.patient_id = ?
            ORDER BY pi.is_primary DESC, pi.created_at DESC
            LIMIT 1
        ");
        $stmt2->execute([$insuranceId, $patientId]);
        $row2 = $stmt2->fetch();
        if ($row2 && $row2['copayment_percentage'] !== null) {
            return (float)$row2['copayment_percentage'];
        }
    }

    // 3. Fallback to patient's primary insurance record
    $stmt3 = $pdo->prepare("
        SELECT ip.copayment_percentage
        FROM patient_insurance pi
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        WHERE pi.patient_id = ? AND pi.is_primary = 1
        LIMIT 1
    ");
    $stmt3->execute([$patientId]);
    $row3 = $stmt3->fetch();
    if ($row3 && $row3['copayment_percentage'] !== null) {
        return (float)$row3['copayment_percentage'];
    }

    // 4. Fallback to any active insurance for this patient
    $stmt4 = $pdo->prepare("
        SELECT ip.copayment_percentage
        FROM patient_insurance pi
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        WHERE pi.patient_id = ? AND pi.status = 'active'
        ORDER BY pi.created_at DESC
        LIMIT 1
    ");
    $stmt4->execute([$patientId]);
    $row4 = $stmt4->fetch();
    if ($row4 && $row4['copayment_percentage'] !== null) {
        return (float)$row4['copayment_percentage'];
    }

    // 5. Fallback to active package for this company
    if ($insuranceId) {
        $stmt5 = $pdo->prepare("
            SELECT ip.copayment_percentage
            FROM insurance_packages ip
            JOIN patient_insurance pi ON (pi.company_id = ip.company_id)
            WHERE (pi.id = ? OR pi.company_id = ?) AND pi.patient_id = ? AND ip.status = 'active'
            ORDER BY ip.id ASC
            LIMIT 1
        ");
        $stmt5->execute([$insuranceId, $insuranceId, $patientId]);
        $row5 = $stmt5->fetch();
        if ($row5 && $row5['copayment_percentage'] !== null) {
            return (float)$row5['copayment_percentage'];
        }
    }

    return 100.0;
}

function recalcInvoice(PDO $pdo, int $invoiceId): void {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id=?");
    $stmt->execute([$invoiceId]);
    $subtotal = (float)$stmt->fetchColumn();

    $invStmt = $pdo->prepare("SELECT discount, tax, paid_amount, insurance_id, patient_id, payment_type FROM invoices WHERE id=?");
    $invStmt->execute([$invoiceId]);
    $inv = $invStmt->fetch();

    $discount   = (float)($inv['discount']    ?? 0);
    $tax        = (float)($inv['tax']         ?? 0);
    $paidAmount = round((float)($inv['paid_amount'] ?? 0));
    $total      = round(max(0, $subtotal - $discount + $tax));
    $insId      = $inv['insurance_id'] ?? null;
    $patId      = $inv['patient_id']   ?? null;

    // ── Resolve patient's copay percentage ──────────────────────────────────
    $copayPct = 100.0;
    if ($insId && ($inv['payment_type'] === 'insurance' || $inv['payment_type'] === 'mixed')) {
        $copayPct = getPatientCopayPercentage($pdo, (int)$patId, (int)$insId);
    }

    // ── Check if invoice items have explicit insurance override fields ──────
    $itemStmt = $pdo->prepare("SELECT quantity, unit_price, insurance_price, patient_topup, apply_insurance FROM invoice_items WHERE invoice_id = ?");
    $itemStmt->execute([$invoiceId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    $hasInsuranceFields = false;
    foreach ($items as $item) {
        if (intval($item['apply_insurance'] ?? 0) > 0 || floatval($item['insurance_price'] ?? 0) > 0) {
            $hasInsuranceFields = true;
            break;
        }
    }

    // ── Patient share = the amount the patient is responsible for ──────────
    if ($hasInsuranceFields) {
        $patientShare = 0;
        foreach ($items as $item) {
            $qty = intval($item['quantity'] ?: 1);
            $insPrice = floatval($item['insurance_price'] ?? 0);
            $topup = floatval($item['patient_topup'] ?? 0);
            $applyIns = intval($item['apply_insurance'] ?? 0);
            if ($applyIns && $insPrice > 0) {
                $patientShare += round(($insPrice * ($copayPct / 100) * $qty) + ($topup * $qty));
            } elseif ($applyIns) {
                $patientShare += round(floatval($item['unit_price'] ?? 0) * ($copayPct / 100) * $qty);
            } else {
                $patientShare += round(floatval($item['unit_price'] ?? 0) * $qty);
            }
        }
        $patientShare = round($patientShare);
    } elseif ($insId && $copayPct < 100) {
        $patientShare = round($total * $copayPct / 100);
    } else {
        $patientShare = $total; // self-pay or 100 % copay
    }

    // ── Get completed paid and written-off from payment_records ─────────────
    $prStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=? AND status='completed'");
    $prStmt->execute([$invoiceId]);
    $prPaid = round((float)$prStmt->fetchColumn());

    $woStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=? AND status='written_off'");
    $woStmt->execute([$invoiceId]);
    $prWrittenOff = round((float)$woStmt->fetchColumn());

    $effectivePaid = ($prPaid > 0 || $prWrittenOff > 0) ? $prPaid : $paidAmount;
    $totalSettled = $effectivePaid + $prWrittenOff;

    // ── Balance = what the patient still owes (patient share − settled amount) ──
    $balance = max(0, round($patientShare - $totalSettled));
    if ($balance <= 1.05 && ($totalSettled > 0 || $patientShare == 0)) {
        $balance = 0;
    }

    // ── Status: paid when patient's share is fully covered ─────────────────
    if ($total <= 0) {
        $status = 'issued';
    } elseif ($balance <= 0 && ($totalSettled > 0 || $patientShare == 0)) {
        $status = 'paid';          // patient paid/deferred their part → invoice is done
    } elseif ($totalSettled > 0) {
        $status = 'partial';
    } else {
        $status = 'issued';
    }

    $pdo->prepare("UPDATE invoices SET subtotal=?, total=?, balance=?, status=? WHERE id=?")
        ->execute([round($subtotal), $total, $balance, $status, $invoiceId]);

    // Update journal entry to match new totals
    recalcJournalEntry($pdo, $invoiceId, $total, $patId, $insId);
}

function recalcJournalEntry(PDO $pdo, int $invoiceId, float $newTotal, ?int $patientId, ?int $insId): void {
    // Find the journal entry for this invoice
    $jeStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type='invoice' AND reference_id=?");
    $jeStmt->execute([$invoiceId]);
    $jeId = $jeStmt->fetchColumn();

    if (!$jeId) {
        if ($newTotal <= 0) return;
        $invStmt = $pdo->prepare("SELECT invoice_no FROM invoices WHERE id=?");
        $invStmt->execute([$invoiceId]);
        $invNo = $invStmt->fetchColumn() ?: ('INV-'.$invoiceId);
        $saleRef = 'SALE-' . date('Ymd') . '-' . str_pad($invoiceId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(), ?, ?, 'invoice', ?, 1, NOW(), NOW())")
            ->execute([$saleRef, 'Sale - ' . $invNo, $invoiceId]);
        $jeId = (int)$pdo->lastInsertId();
    } else {
        $invStmt = $pdo->prepare("SELECT invoice_no FROM invoices WHERE id=?");
        $invStmt->execute([$invoiceId]);
        $invNo = $invStmt->fetchColumn() ?: ('INV-'.$invoiceId);
        $pdo->prepare("UPDATE journal_entries SET description=?, updated_at=NOW() WHERE id=?")
            ->execute(['Sale - ' . $invNo, $jeId]);
    }

    // Get copayment percentage
    $copayPct = getPatientCopayPercentage($pdo, $patientId, $insId);

    // Delete old lines and recreate
    $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id=?")->execute([$jeId]);

    if ($newTotal <= 0) return; // Invoice is empty, no entries needed

    // Find patient's receivable account
    $patientAccId = 7; // fallback
    if ($patientId) {
        $accId = fin_private_ar_acct($pdo);
        if ($accId) $patientAccId = (int)$accId;
    }

    if ($insId && $copayPct < 100) {
        $patientPortion = round($newTotal * $copayPct / 100);
        $insPortion = round($newTotal - $patientPortion);

        // Dr: Patient Receivable
        if ($patientPortion > 0) {
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                ->execute([$jeId, (int)$patientAccId, 'Patient portion ('.$copayPct.'%)', $patientPortion]);
        }
        // Dr: Insurance Receivable
        $insAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE linked_insurance_id=$insId AND parent_id=7 LIMIT 1")->fetchColumn();
        if (!$insAccId) $insAccId = 7;
        if ($insPortion > 0) {
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                ->execute([$jeId, (int)$insAccId, 'Insurance portion ('.(100-$copayPct).'%)', $insPortion]);
        }
    } else {
        // Self-pay: Dr Patient Receivable full amount
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
            ->execute([$jeId, (int)$patientAccId, 'Patient receivable (full)', $newTotal]);
    }

    // Cr: Income account
    $incomeAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE account_type='income' AND status='active' ORDER BY account_code ASC LIMIT 1")->fetchColumn();
    if (!$incomeAccId) $incomeAccId = 1007;

    $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")
        ->execute([$jeId, (int)$incomeAccId, 'Revenue - ' . $invNo, $newTotal]);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
if (empty($action) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $rawInputJson = @json_decode(file_get_contents('php://input'), true);
    if (!empty($rawInputJson['action'])) {
        $action = $rawInputJson['action'];
    }
}
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ===== Dashboard: today's revenue summary =====
if ($method === 'GET' && $action === 'today_summary') {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
    $dateTo   = $_GET['date_to']   ?? date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(i.total), 0) AS total_amount,
            COALESCE(SUM(i.paid_amount), 0) AS total_paid,
            COALESCE(SUM(i.total - i.paid_amount), 0) AS total_outstanding,
            COUNT(*) AS invoice_count
        FROM invoices i
        JOIN visits v ON v.id = i.visit_id
        WHERE v.visit_date BETWEEN ? AND ?
    ");
    $stmt->execute([$dateFrom, $dateTo]);
    $row = $stmt->fetch();
    success([
        'total_revenue' => $row['total_amount'],
        'total_paid' => $row['total_paid'],
        'total_outstanding' => $row['total_outstanding'],
        'invoice_count' => $row['invoice_count']
    ]);
}


// ===== Lab Test Parameters CRUD =====
if ($method === 'GET' && $action === 'lab_params') {
    $serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
    if (!$serviceId) error('service_id required', 422);
    $stmt = $pdo->prepare("SELECT * FROM lab_test_parameters WHERE service_id=? ORDER BY sort_order, id");
    $stmt->execute([$serviceId]);
    success($stmt->fetchAll());
}

if ($method === 'POST' && $action === 'lab_params') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $serviceId = (int)($input['service_id'] ?? 0);
    $testName = trim($input['test_name'] ?? '');
    if (!$serviceId || !$testName) error('service_id and test_name required', 422);
    $unit = trim($input['unit'] ?? '');
    $normalMin = isset($input['normal_min']) && $input['normal_min'] !== '' && $input['normal_min'] !== null ? (float)$input['normal_min'] : null;
    $normalMax = isset($input['normal_max']) && $input['normal_max'] !== '' && $input['normal_max'] !== null ? (float)$input['normal_max'] : null;

    if (!empty($input['id'])) {
        $pdo->prepare("UPDATE lab_test_parameters SET test_name=?, unit=?, normal_min=?, normal_max=? WHERE id=?")
            ->execute([$testName, $unit, $normalMin, $normalMax, (int)$input['id']]);
        success(null, 'Parameter updated');
    } else {
        $pdo->prepare("INSERT INTO lab_test_parameters (service_id, test_name, unit, normal_min, normal_max) VALUES (?,?,?,?,?)")
            ->execute([$serviceId, $testName, $unit, $normalMin, $normalMax]);
        success(['id' => (int)$pdo->lastInsertId()], 'Parameter created');
    }
}

if ($method === 'DELETE' && $action === 'lab_params') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $paramId = (int)($input['id'] ?? 0);
    if (!$paramId) error('id required', 422);
    $pdo->prepare("DELETE FROM lab_test_parameters WHERE id=?")->execute([$paramId]);
    success(null, 'Parameter deleted');
}

// ===== Dashboard: pending lab tests =====
if ($method === 'GET' && $action === 'pending_labs') {
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) AS total
            FROM invoice_items 
            WHERE category = 'laboratory' AND DATE(added_at) = CURDATE()
        ");
        $row = $stmt->fetch();
        success(['total' => (int)($row['total'] ?? 0), 'urgent' => 0]);
    } catch(Exception $e) {
        success(['total' => 0, 'urgent' => 0]);
    }
}

// ===== GET invoice edit logs (Audit history) =====
if ($method === 'GET' && $action === 'invoice_logs') {
    $invId = $id ?: (int)($_GET['invoice_id'] ?? 0);
    if (!$invId) error('Invoice ID required', 422);
    ensureInvoiceEditLogsTable($pdo);
    $stmt = $pdo->prepare("
        SELECT l.*, 
               COALESCE(l.user_name, TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), 'Administrator') AS editor_name
        FROM invoice_edit_logs l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE l.invoice_id = ?
        ORDER BY l.created_at DESC, l.id DESC
    ");
    $stmt->execute([$invId]);
    $logs = $stmt->fetchAll();
    foreach ($logs as &$lg) {
        $lg['old_data'] = json_decode($lg['old_values'] ?? '', true);
        $lg['new_data'] = json_decode($lg['new_values'] ?? '', true);
    }
    success($logs);
}

// ===== GET visits with invoice summary (for billing list) =====
if ($method === 'GET' && $action === 'visits_billing') {
    $dateFrom  = $_GET['date_from'] ?? '';
    $dateTo    = $_GET['date_to']   ?? '';
    $statusFilter = $_GET['inv_status'] ?? 'pending'; // pending | paid | all
    $search    = trim($_GET['search'] ?? '');

    $where = ["v.visit_date >= '1970-01-01'"]; // always true base
    $params = [];

    if ($dateFrom) { $where[] = 'v.visit_date >= ?'; $params[] = $dateFrom; }
    if ($dateTo)   { $where[] = 'v.visit_date <= ?'; $params[] = $dateTo; }

    if ($statusFilter === 'pending') {
        $where[] = "i.status IN ('issued','partial','draft')";
    } elseif ($statusFilter === 'paid') {
        $where[] = "i.status = 'paid'";
    }

    if ($search) {
        $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR v.visit_no LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s]);
    }

    $ws = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            v.id AS visit_id,
            v.visit_no,
            v.visit_date,
            v.payment_type AS visit_payment_type,
            p.id AS patient_id,
            p.first_name,
            p.last_name,
            p.patient_no,
            p.date_of_birth,
            COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
            COALESCE(
                MAX(COALESCE(ip_chk.copayment_percentage, ip2_chk.copayment_percentage, ip3_chk.copayment_percentage)),
                (SELECT copayment_percentage FROM insurance_packages WHERE company_id = COALESCE(ic.id, ic2.id, ic3.id) AND status = 'active' ORDER BY id ASC LIMIT 1),
                100
            ) AS copayment_percentage,
            GROUP_CONCAT(DISTINCT NULLIF(TRIM(CONCAT(COALESCE(uc.first_name,''),' ',COALESCE(uc.last_name,''))),'') SEPARATOR ', ') AS created_by_name,
            GROUP_CONCAT(DISTINCT NULLIF(TRIM(CONCAT(COALESCE(ur.first_name,''),' ',COALESCE(ur.last_name,''))),'') SEPARATOR ', ') AS payment_received_by_name,
            COUNT(DISTINCT i.id) AS invoice_count,
            COALESCE(SUM(i.total), 0) AS total_amount,
            COALESCE(SUM(i.paid_amount), 0) AS total_paid,
            COALESCE(SUM(
                GREATEST(0,
                    ROUND(i.total * COALESCE(
                        ip_chk.copayment_percentage,
                        ip2_chk.copayment_percentage,
                        ip3_chk.copayment_percentage,
                        (SELECT copayment_percentage FROM insurance_packages WHERE company_id = COALESCE(ic.id, ic2.id, ic3.id) AND status = 'active' ORDER BY id ASC LIMIT 1),
                        100
                    ) / 100, 0)
                    - ROUND(i.paid_amount, 0)
                )
            ), 0) AS total_balance
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        JOIN invoices i ON i.visit_id = v.id
        LEFT JOIN users uc ON uc.id = i.created_by
        LEFT JOIN users ur ON ur.id = i.payment_received_by
        LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
        LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
        LEFT JOIN insurance_packages ip_chk ON ip_chk.id = pi.package_id
        LEFT JOIN patient_insurance pi2 ON pi2.company_id = v.insurance_id AND pi2.patient_id = v.patient_id AND pi.id IS NULL
        LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
        LEFT JOIN insurance_packages ip2_chk ON ip2_chk.id = pi2.package_id AND pi.id IS NULL
        LEFT JOIN patient_insurance pi3 ON pi3.patient_id = v.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_packages ip3_chk ON ip3_chk.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
        $ws
        GROUP BY v.id, v.visit_no, v.visit_date, v.payment_type, p.id, p.first_name, p.last_name, p.patient_no, p.date_of_birth, ic.name, ic2.name, ic3.name, ic.id, ic2.id, ic3.id
        HAVING COUNT(DISTINCT i.id) > 0
        ORDER BY v.visit_date DESC, v.id DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recalculate total_balance for each visit using invoice items
    foreach ($visits as &$visit) {
        $visitBalance = 0;
        // Get all invoices for this visit with copayment info
        $invStmt = $pdo->prepare("
            SELECT i.id, i.payment_type, i.paid_amount, i.balance, i.insurance_id, i.status,
                   COALESCE(
                        ip.copayment_percentage,
                        ip2.copayment_percentage,
                        ip3.copayment_percentage,
                        (SELECT copayment_percentage FROM insurance_packages WHERE company_id = COALESCE(ic.id, ic2.id, ic3.id) AND status = 'active' ORDER BY id ASC LIMIT 1),
                        100
                    ) as copayment_percentage
            FROM invoices i
            LEFT JOIN patient_insurance pi ON pi.id = i.insurance_id AND pi.patient_id = i.patient_id
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            LEFT JOIN patient_insurance pi2 ON pi2.company_id = i.insurance_id AND pi2.patient_id = i.patient_id AND pi.id IS NULL
            LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
            LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
            LEFT JOIN patient_insurance pi3 ON pi3.patient_id = i.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
            WHERE i.visit_id = ? AND i.status IN ('issued','partial','draft')
        ");
        $invStmt->execute([$visit['visit_id']]);
        $invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);
        if (isset($invoices[0]['copayment_percentage'])) {
            $visit['copayment_percentage'] = $invoices[0]['copayment_percentage'];
        }
        
        foreach ($invoices as $inv) {
            $patientShare = 0;
            $copayPct = floatval($inv['copayment_percentage'] ?? 100);
            $isInsurance = ($inv['payment_type'] === 'insurance' || $inv['payment_type'] === 'mixed');

            // ── Auto-close: if copayPct=0 (insurance 100%), patient owes nothing → mark paid ──
            if ($isInsurance && $copayPct == 0 && in_array($inv['status'], ['issued','partial','draft'])) {
                $pdo->prepare("UPDATE invoices SET balance=0, status='paid' WHERE id=?")->execute([$inv['id']]);
                continue; // skip — no patient balance
            }
            
            // Get invoice items
            $itemStmt = $pdo->prepare("SELECT quantity, unit_price, insurance_price, patient_topup, apply_insurance FROM invoice_items WHERE invoice_id = ?");
            $itemStmt->execute([$inv['id']]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check if this is a NEW invoice (has insurance fields set)
            $hasInsuranceFields = false;
            foreach ($items as $item) {
                if (intval($item['apply_insurance'] ?? 0) > 0 || floatval($item['insurance_price'] ?? 0) > 0) {
                    $hasInsuranceFields = true;
                    break;
                }
            }
            
            // NEW invoices with insurance fields: calculate precisely from items
            if ($hasInsuranceFields) {
                $patientShare = 0;
                foreach ($items as $item) {
                    $qty = intval($item['quantity'] ?: 1);
                    $insPrice = floatval($item['insurance_price'] ?? 0);
                    $topup = floatval($item['patient_topup'] ?? 0);
                    $applyIns = intval($item['apply_insurance'] ?? 0);
                    
                    if ($applyIns && $insPrice > 0) {
                        // Item has insurance: patient pays copay% + topup
                        $patientShare += round(($insPrice * ($copayPct / 100) * $qty) + ($topup * $qty));
                    } elseif ($applyIns) {
                        // Covered by insurance with standard unit price
                        $unitPrice = floatval($item['unit_price'] ?? 0);
                        $patientShare += round($unitPrice * ($copayPct / 100) * $qty);
                    } else {
                        // No insurance: patient pays full price
                        $unitPrice = floatval($item['unit_price'] ?? 0);
                        $patientShare += round($unitPrice * $qty);
                    }
                }
                $patientShare = round($patientShare);
            } 
            // OLD invoices without insurance fields: calculate from total if insurance visit
            elseif ($isInsurance && $copayPct < 100) {
                // For OLD insurance invoices, calculate patient share from total: total × copay%
                $invoiceTotal = 0;
                foreach ($items as $item) {
                    $invoiceTotal += floatval($item['unit_price'] ?? 0) * intval($item['quantity'] ?: 1);
                }
                $patientShare = round($invoiceTotal * ($copayPct / 100));
            } 
            // Self-pay or 100% copay: patient pays full amount
            else {
                foreach ($items as $item) {
                    $patientShare += round(floatval($item['unit_price'] ?? 0) * intval($item['quantity'] ?: 1));
                }
                $patientShare = round($patientShare);
            }
            
            // Get total paid including write-offs (they reduce patient balance)
            $payStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payment_records WHERE invoice_id = ? AND status IN ('completed', 'written_off')");
            $payStmt->execute([$inv['id']]);
            $totalPaid = round(floatval($payStmt->fetchColumn()));
            
            $itemBal = round($patientShare) - $totalPaid;
            if ($itemBal <= 1.05 || $inv['status'] === 'paid') $itemBal = 0;
            $visitBalance += max(0, $itemBal);
        }
        
        $visit['total_balance'] = round($visitBalance);
    }
    
    // Filter out visits with zero balance
    $visits = array_filter($visits, function($v) {
        return floatval($v['total_balance']) > 1.05;
    });
    
    success(array_values($visits));
}

// ===== GET services catalog =====
if ($method === 'GET' && $action === 'services') {
    $cat    = $_GET['category'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $where  = []; $params = [];
    
    // Map category names to speciality patterns
    if ($cat) {
        $labSpecialities = ['Bacteriology','BIOCHEMISTRY','Hematology','Immuno-Serology','Parasitology','Molecular','Anatomical Pathology','Blood Transfusion'];
        if (strpos($cat, ',') !== false) {
            $cats = array_map('trim', explode(',', $cat));
            $likeClauses = [];
            foreach ($cats as $c) {
                if ($c === 'lab' || $c === 'laboratory') {
                    $labPlaceholders = implode(',', array_fill(0, count($labSpecialities), '?'));
                    $likeClauses[] = "s.speciality IN ($labPlaceholders)";
                    $params = array_merge($params, $labSpecialities);
                } elseif ($c === 'radiology' || $c === 'imaging') {
                    $likeClauses[] = "s.speciality LIKE 'Imaging%'";
                } else {
                    $likeClauses[] = "s.speciality LIKE ?";
                    $params[] = "%$c%";
                }
            }
            $where[] = '(' . implode(' OR ', $likeClauses) . ')';
        } else {
            if ($cat === 'lab' || $cat === 'laboratory') {
                $labPlaceholders = implode(',', array_fill(0, count($labSpecialities), '?'));
                $where[] = "s.speciality IN ($labPlaceholders)";
                $params = array_merge($params, $labSpecialities);
            } elseif ($cat === 'radiology' || $cat === 'imaging') {
                $where[] = "s.speciality LIKE 'Imaging%'";
            } else {
                $where[] = 's.speciality LIKE ?'; $params[] = "%$cat%";
            }
        }
    }
    if ($search) { $where[] = '(s.procedure_name LIKE ? OR s.ichi_code LIKE ? OR s.rhic LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
    
    // Get hospital facility category for pricing
    $facilityCategory = 'primary';
    try {
        $fcStmt = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='facility_category' LIMIT 1");
        $fc = $fcStmt->fetchColumn();
        if ($fc) $facilityCategory = $fc;
    } catch(Exception $e) {}
    
    $priceCol = $facilityCategory === 'tertiary' ? 'tertiary_health_facility' : ($facilityCategory === 'secondary' ? 'secondary_health_facility' : 'primary_health_facility');
    
    $stmt = $pdo->prepare("SELECT s.id, s.procedure_name AS name, s.ichi_code AS code, s.speciality AS category, s.$priceCol AS unit_price, s.rhic FROM service_catalog s $ws ORDER BY s.speciality, s.procedure_name LIMIT 2000");
    $stmt->execute($params);
    success($stmt->fetchAll());
}

// ===== GET invoice list or single =====
if ($method === 'GET' && $action !== 'services') {
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   TRIM(CONCAT(COALESCE(uc.first_name,''),' ',COALESCE(uc.last_name,''))) AS created_by_name,
                   TRIM(CONCAT(COALESCE(ur.first_name,''),' ',COALESCE(ur.last_name,''))) AS payment_received_by_name,
                   p.first_name, p.last_name, p.patient_no, p.date_of_birth, p.gender, p.phone, p.national_id,
                   v.visit_no, v.visit_date, v.visit_type,
                   COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
                   COALESCE(ip.name, ip2.name, ip3.name) AS package_name,
                   COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage
            FROM invoices i
            JOIN patients p ON p.id=i.patient_id
            JOIN visits v ON v.id=i.visit_id
            LEFT JOIN users uc ON uc.id=i.created_by
            LEFT JOIN users ur ON ur.id=i.payment_received_by
            LEFT JOIN patient_insurance pi ON pi.id=i.insurance_id AND pi.patient_id=i.patient_id
            LEFT JOIN insurance_companies ic ON ic.id=pi.company_id
            LEFT JOIN insurance_packages ip ON ip.id=pi.package_id
            LEFT JOIN patient_insurance pi2 ON pi2.company_id=i.insurance_id AND pi2.patient_id=i.patient_id AND pi.id IS NULL
            LEFT JOIN insurance_companies ic2 ON ic2.id=pi2.company_id AND pi.id IS NULL
            LEFT JOIN insurance_packages ip2 ON ip2.id=pi2.package_id AND pi.id IS NULL
            LEFT JOIN patient_insurance pi3 ON pi3.patient_id=i.patient_id AND pi3.is_primary=1 AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_companies ic3 ON ic3.id=pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
            LEFT JOIN insurance_packages ip3 ON ip3.id=pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
            WHERE i.id=?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) error('Invoice not found',404);
        $iStmt = $pdo->prepare("SELECT ii.*, s.ichi_code AS service_code, s.procedure_name AS service_name FROM invoice_items ii LEFT JOIN service_catalog s ON s.id=ii.service_id WHERE ii.invoice_id=? ORDER BY ii.id");
        $iStmt->execute([$id]);
        $inv['items'] = $iStmt->fetchAll();
        // Attach payment_records for paid/deferred breakdown
        $prStmt = $pdo->prepare("
            SELECT pr.amount_paid, pr.payment_method, pr.payment_date, pr.receipt_number, pr.status, pr.created_by,
                   TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS received_by_name
            FROM payment_records pr
            LEFT JOIN users u ON u.id = pr.created_by
            WHERE pr.invoice_id=? ORDER BY pr.id ASC
        ");
        $prStmt->execute([$id]);
        $records = $prStmt->fetchAll();
        $inv['payment_records'] = $records;
        if (count($records)) {
            $completedRecords = array_values(array_filter($records, function($r) { return $r['status'] === 'completed'; }));
            $inv['paid_amount']   = round(array_sum(array_column($completedRecords, 'amount_paid')));
            $inv['unpaid_amount'] = round(array_sum(array_column(array_filter($records, function($r) { return $r['status'] === 'pending'; }),   'amount_paid')));
            if (empty($inv['payment_received_by_name']) && count($completedRecords) > 0) {
                $lastRec = end($completedRecords);
                if (!empty($lastRec['received_by_name'])) {
                    $inv['payment_received_by_name'] = $lastRec['received_by_name'];
                }
            }
        } else {
            $inv['paid_amount']   = round((float)$inv['paid_amount']);
            $inv['unpaid_amount'] = 0;
        }
        $inv['total']    = round((float)$inv['total']);
        $inv['subtotal'] = round((float)$inv['subtotal']);
        $inv['balance']  = round((float)$inv['balance']);
        if ($inv['status'] === 'paid' || ($inv['balance'] <= 1.05 && ($inv['paid_amount'] > 0 || ($inv['payment_type'] === 'insurance' && floatval($inv['copayment_percentage']) == 0)))) {
            $inv['balance'] = 0;
        }
        success($inv);
    }
    $visitId   = isset($_GET['visit_id'])   ? (int)$_GET['visit_id']   : 0;
    $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
    $status    = $_GET['status'] ?? '';
    $dateFrom  = $_GET['date_from'] ?? '';
    $dateTo    = $_GET['date_to']   ?? '';
    $where=[]; $params=[];
    if ($visitId)   { $where[]='i.visit_id=?';   $params[]=$visitId; }
    if ($patientId) { $where[]='i.patient_id=?';  $params[]=$patientId; }
    if ($status === 'pending') {
        $where[] = "i.status IN ('issued','partial','draft')";
    } elseif ($status && $status !== 'all') {
        $where[] = 'i.status=?';
        $params[] = $status;
    }
    // Server-side date range filter on visit_date
    if ($dateFrom) { $where[] = 'v.visit_date >= ?'; $params[] = $dateFrom; }
    if ($dateTo)   { $where[] = 'v.visit_date <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $stmt = $pdo->prepare("
        SELECT i.id,i.invoice_no,i.visit_id,i.patient_id,i.payment_type,i.subtotal,i.total,i.paid_amount,i.balance,i.status,i.created_at,i.insurance_id,
               i.payment_method,i.created_by,i.payment_received_by,
               TRIM(CONCAT(COALESCE(uc.first_name,''),' ',COALESCE(uc.last_name,''))) AS created_by_name,
               TRIM(CONCAT(COALESCE(ur.first_name,''),' ',COALESCE(ur.last_name,''))) AS payment_received_by_name,
               p.first_name,p.last_name,p.patient_no,p.date_of_birth,p.gender,p.phone,p.national_id,
               v.visit_no,v.visit_date,v.visit_type,v.payment_type AS visit_payment_type,
               COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
               COALESCE(ip.name, ip2.name, ip3.name) AS package_name,
               COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, (SELECT copayment_percentage FROM insurance_packages WHERE company_id = COALESCE(ic.id, ic2.id, ic3.id) AND status = 'active' ORDER BY id ASC LIMIT 1), 100) AS copayment_percentage,
               COALESCE(pi.member_number, pi2.member_number, pi3.member_number) AS membership_no
        FROM invoices i
        JOIN patients p ON p.id=i.patient_id
        JOIN visits v ON v.id=i.visit_id
        LEFT JOIN users uc ON uc.id=i.created_by
        LEFT JOIN users ur ON ur.id=i.payment_received_by
        LEFT JOIN patient_insurance pi ON pi.id=i.insurance_id AND pi.patient_id=i.patient_id
        LEFT JOIN insurance_companies ic ON ic.id=pi.company_id
        LEFT JOIN insurance_packages ip ON ip.id=pi.package_id
        LEFT JOIN patient_insurance pi2 ON pi2.company_id=i.insurance_id AND pi2.patient_id=i.patient_id AND pi.id IS NULL
        LEFT JOIN insurance_companies ic2 ON ic2.id=pi2.company_id AND pi.id IS NULL
        LEFT JOIN insurance_packages ip2 ON ip2.id=pi2.package_id AND pi.id IS NULL
        LEFT JOIN patient_insurance pi3 ON pi3.patient_id=i.patient_id AND pi3.is_primary=1 AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_companies ic3 ON ic3.id=pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_packages ip3 ON ip3.id=pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
        $ws ORDER BY v.visit_date DESC, i.created_at DESC LIMIT 1000");
    $stmt->execute($params);
    $invoiceRows = $stmt->fetchAll();

    // Attach payment_records to each invoice so UI can show paid vs unpaid breakdown
    $prStmt = $pdo->prepare("
        SELECT pr.amount_paid, pr.payment_method, pr.payment_date, pr.receipt_number, pr.status, pr.created_by,
               TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS received_by_name
        FROM payment_records pr
        LEFT JOIN users u ON u.id = pr.created_by
        WHERE pr.invoice_id=? ORDER BY pr.id ASC
    ");
    foreach ($invoiceRows as &$invRow) {
        $prStmt->execute([(int)$invRow['id']]);
        $records = $prStmt->fetchAll();
        $invRow['payment_records'] = $records;
        $completedRecords = array_values(array_filter($records, function($r) { return $r['status'] === 'completed'; }));
        if (count($records) > 0) {
            // Use payment_records as source of truth
            $invRow['paid_amount']       = round(array_sum(array_column($completedRecords, 'amount_paid')));
            $invRow['unpaid_amount']     = round(array_sum(array_column(array_filter($records, function($r) { return $r['status'] === 'pending'; }),     'amount_paid')));
            $invRow['writtenoff_amount'] = round(array_sum(array_column(array_filter($records, function($r) { return $r['status'] === 'written_off'; }), 'amount_paid')));
            if (empty($invRow['payment_received_by_name']) && count($completedRecords) > 0) {
                $lastRec = end($completedRecords);
                if (!empty($lastRec['received_by_name'])) {
                    $invRow['payment_received_by_name'] = $lastRec['received_by_name'];
                }
            }
        } else {
            // Legacy invoices with no payment_records rows — use invoice.paid_amount directly
            // Treat as completed if payment_method is not 'unpaid', otherwise as deferred
            $legacyMethod = $invRow['payment_method'] ?? '';
            if ($legacyMethod === 'unpaid') {
                $invRow['unpaid_amount'] = round((float)$invRow['paid_amount']);
                $invRow['paid_amount']   = 0;
            } else {
                $invRow['unpaid_amount'] = 0;
                $invRow['paid_amount']   = round((float)$invRow['paid_amount']);
            }
        }
        $invRow['total']    = round((float)$invRow['total']);
        $invRow['subtotal'] = round((float)$invRow['subtotal']);
        $invRow['balance']  = round((float)$invRow['balance']);
        if ($invRow['status'] === 'paid' || ($invRow['balance'] <= 1.05 && ($invRow['paid_amount'] > 0 || ($invRow['payment_type'] === 'insurance' && floatval($invRow['copayment_percentage']) == 0)))) {
            $invRow['balance'] = 0;
        }
    }
    unset($invRow);

    success($invoiceRows);
}

// ===== POST action=recalculate_visit — recalculate all invoices for a visit with correct insurance prices =====
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'recalculate_visit') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    if (!$visitId || !$patientId) error('visit_id and patient_id required', 422);

    // Get visit info — strictly match patient_insurance for THIS patient
    $vStmt = $pdo->prepare("SELECT v.*,
               COALESCE(ic.id, ic2.id, ic3.id) AS company_id,
               COALESCE(ic.name, ic2.name, ic3.name) AS company_name,
               COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage) AS copayment_percentage,
               COALESCE(pi.id, pi2.id, pi3.id) AS patient_insurance_id
        FROM visits v
        LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
        LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        LEFT JOIN patient_insurance pi2 ON pi2.company_id = v.insurance_id AND pi2.patient_id = v.patient_id AND pi.id IS NULL
        LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
        LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
        LEFT JOIN patient_insurance pi3 ON pi3.patient_id = v.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
        LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
        WHERE v.id = ?");
    $vStmt->execute([$visitId]);
    $visit = $vStmt->fetch();
    if (!$visit) error('Visit not found', 404);

    $companyId = (int)($visit['company_id'] ?? 0);
    $patientInsuranceId = $visit['patient_insurance_id'] ?? null;
    $copayPct = getPatientCopayPercentage($pdo, $patientId, (int)($patientInsuranceId ?: $visit['insurance_id']));

    $isInsurance = ($visit['payment_type'] === 'insurance' || $visit['payment_type'] === 'mixed' || $companyId > 0);

    // Update the visit's insurance_id to match current patient insurance
    $correctInsId = $patientInsuranceId ?: $companyId;
    if ($isInsurance && $correctInsId && (int)$visit['insurance_id'] !== (int)$correctInsId) {
        $pdo->prepare("UPDATE visits SET insurance_id = ? WHERE id = ?")->execute([$correctInsId, $visitId]);
    }

    // Load all insurance_procedure_prices for this company
    $insPrices = [];
    if ($isInsurance && $companyId) {
        $ippStmt = $pdo->prepare("SELECT service_id, price FROM insurance_procedure_prices WHERE insurance_id = ?");
        $ippStmt->execute([$companyId]);
        foreach ($ippStmt->fetchAll() as $row) {
            $insPrices[(int)$row['service_id']] = (float)$row['price'];
        }
    }

    $updatedInvoices = 0;
    $updatedItems = 0;
    $removedItems = 0;
    $totalBefore = 0;
    $totalAfter = 0;

    $pdo->beginTransaction();
    try {
        // ===== Recalculate existing invoices (update prices, apply insurance, remove deleted items) =====
        // Fetch all existing invoices for this visit (needed for cleanup + recalc loop)
        $invStmt = $pdo->prepare("
            SELECT i.id, i.invoice_no, i.payment_type, i.paid_amount, i.balance,
                   i.insurance_id, i.total, i.discount, i.tax, i.status
            FROM invoices i
            WHERE i.visit_id = ?
        ");
        $invStmt->execute([$visitId]);
        $invoices = $invStmt->fetchAll();
        // ===== CHECK EXECUTED DELETION REQUESTS =====
        // Remove invoice items linked to executed deletion requests for THIS visit only.
        // Only remove items when we can confirm the deletion belongs to this exact visit.
        
        // 1. Procedure deletions — SKIP. The execute action in deletion-requests.php
        //    already deletes the invoice_item directly when it executes a procedure deletion.
        //    No need to double-delete here.
        
        // 2. Lab order deletions — require visit_id match from entity_data (audit log)
        $labDelStmt = $pdo->prepare("
            SELECT dr.entity_id, dr.entity_name, dal.entity_data 
            FROM deletion_requests dr 
            LEFT JOIN deletion_audit_log dal ON dal.deletion_request_id = dr.id 
            WHERE dr.entity_type = 'lab_order' AND dr.status = 'executed'
        ");
        $labDelStmt->execute();
        $deletedLabOrders = $labDelStmt->fetchAll();
        
        foreach ($deletedLabOrders as $labOrder) {
            $labData = $labOrder['entity_data'] ? json_decode($labOrder['entity_data'], true) : null;
            
            // STRICT: skip if no entity_data (can't confirm visit_id)
            if (!$labData) continue;
            
            // STRICT: skip if visit_id doesn't match this visit
            $labVisitId = $labData['visit_id'] ?? null;
            if (!$labVisitId || (int)$labVisitId !== $visitId) continue;
            
            // Get the lab order description from the audit snapshot
            $labDescription = $labData['description'] ?? $labOrder['entity_name'] ?? '';
            if (!$labDescription) continue;
            
            // Try to find the invoice_item by service_id first (most precise)
            $labEntityId = (int)$labOrder['entity_id']; // This is the medical_orders.id
            $labServiceId = $labData['service_id'] ?? null;
            
            $matchedItems = [];
            
            if ($labServiceId) {
                // Best match: by service_id + visit_id + category
                $labItemStmt = $pdo->prepare("
                    SELECT ii.id FROM invoice_items ii 
                    JOIN invoices i ON i.id = ii.invoice_id 
                    WHERE i.visit_id = ? AND ii.service_id = ? 
                    AND ii.category IN ('lab', 'radiology', 'laboratory', 'procedure')
                ");
                $labItemStmt->execute([$visitId, $labServiceId]);
                $matchedItems = $labItemStmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Fallback: exact description match (not LIKE/fuzzy)
            if (empty($matchedItems) && $labDescription) {
                $labItemStmt = $pdo->prepare("
                    SELECT ii.id FROM invoice_items ii 
                    JOIN invoices i ON i.id = ii.invoice_id 
                    WHERE i.visit_id = ? AND ii.description = ? 
                    AND ii.category IN ('lab', 'radiology', 'laboratory', 'procedure')
                ");
                $labItemStmt->execute([$visitId, $labDescription]);
                $matchedItems = $labItemStmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            foreach ($matchedItems as $mi) {
                $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$mi]);
                $removedItems++;
            }
        }
        
        // 3. Prescription deletions — require visit_id match from entity_data
        $rxDelStmt = $pdo->prepare("
            SELECT dr.entity_id, dr.entity_name, dal.entity_data 
            FROM deletion_requests dr 
            LEFT JOIN deletion_audit_log dal ON dal.deletion_request_id = dr.id 
            WHERE dr.entity_type = 'prescription' AND dr.status = 'executed'
        ");
        $rxDelStmt->execute();
        $deletedPrescriptions = $rxDelStmt->fetchAll();
        
        foreach ($deletedPrescriptions as $rxDel) {
            $rxData = $rxDel['entity_data'] ? json_decode($rxDel['entity_data'], true) : null;
            
            // STRICT: skip if no entity_data (can't confirm visit_id)
            if (!$rxData) continue;
            
            // STRICT: skip if visit_id doesn't match this visit
            $rxVisitId = $rxData['visit_id'] ?? null;
            if (!$rxVisitId || (int)$rxVisitId !== $visitId) continue;
            
            // Get item details from the audit data
            $rxItems = $rxData['items'] ?? [];
            
            if (!empty($rxItems)) {
                foreach ($rxItems as $rxItem) {
                    $itemId = $rxItem['item_id'] ?? null;
                    $drugName = $rxItem['name'] ?? $rxItem['item_name'] ?? $rxItem['drug_name'] ?? '';
                    if (!$itemId && !$drugName) continue;
                    
                    $matchedRxItems = [];
                    
                    // Best match: by item_id (service_id in invoice_items) + visit_id
                    if ($itemId) {
                        $rxInvStmt = $pdo->prepare("
                            SELECT ii.id FROM invoice_items ii 
                            JOIN invoices i ON i.id = ii.invoice_id 
                            WHERE i.visit_id = ? AND ii.service_id = ? 
                            AND ii.category IN ('medication', 'pharmacy', 'drug')
                        ");
                        $rxInvStmt->execute([$visitId, $itemId]);
                        $matchedRxItems = $rxInvStmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    
                    // Fallback: exact drug name match (not LIKE/fuzzy)
                    if (empty($matchedRxItems) && $drugName) {
                        $rxInvStmt = $pdo->prepare("
                            SELECT ii.id FROM invoice_items ii 
                            JOIN invoices i ON i.id = ii.invoice_id 
                            WHERE i.visit_id = ? AND ii.description = ? 
                            AND ii.category IN ('medication', 'pharmacy', 'drug')
                        ");
                        $rxInvStmt->execute([$visitId, $drugName]);
                        $matchedRxItems = $rxInvStmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    
                    foreach ($matchedRxItems as $rxItemId) {
                        $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$rxItemId]);
                        $removedItems++;
                    }
                }
            }
            // NOTE: If no items in audit data AND no visit_id confirmed, we skip entirely.
            // This prevents accidental deletion of unrelated invoice items.
        }
        
        // Now clean up any invoices that lost all their items + fix journal entries
        foreach ($invoices as $inv) {
            $invoiceId = (int)$inv['id'];
            $remainStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?");
            $remainStmt->execute([$invoiceId]);
            $remaining = (int)$remainStmt->fetchColumn();
            
            if ($remaining === 0 && $removedItems > 0) {
                // Delete journal entries for this invoice
                $jeStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
                $jeStmt->execute([(string)$invoiceId]);
                $journalIds = $jeStmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($journalIds as $jeId) {
                    $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$jeId]);
                    $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$jeId]);
                }
                // Delete payment records
                $pdo->prepare("DELETE FROM payment_records WHERE invoice_id = ?")->execute([$invoiceId]);
                // Delete the invoice itself
                $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invoiceId]);
            } elseif ($remaining > 0 && $removedItems > 0) {
                // Invoice still has items but some were removed — recalculate total and update journal entries
                $sumStmt2 = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = ?");
                $sumStmt2->execute([$invoiceId]);
                $newTotal = (float)$sumStmt2->fetchColumn();
                $oldTotal = (float)$inv['total'];
                $paidAmount = round((float)$inv['paid_amount']);
                // Use patient share for balance/status
                $invInsId2 = (int)($inv['insurance_id'] ?? 0);
                $invCopay2 = $isInsurance && $invInsId2 ? $copayPct : 100.0;
                $patientShare2 = ($invInsId2 && $invCopay2 < 100)
                    ? round($newTotal * $invCopay2 / 100)
                    : round($newTotal);
                $newBalance = max(0, round($patientShare2 - $paidAmount));
                if ($newBalance <= 1.05 && ($paidAmount > 0 || $patientShare2 == 0)) {
                    $newBalance = 0;
                }
                $newStatus = ($newTotal <= 0) ? 'issued' : ($newBalance <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'issued'));
                $pdo->prepare("UPDATE invoices SET subtotal=?, total=?, balance=?, status=? WHERE id=?")->execute([round($newTotal), round($newTotal), $newBalance, $newStatus, $invoiceId]);
                
                // Update journal entry amounts to match new total
                $jeStmt2 = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
                $jeStmt2->execute([(string)$invoiceId]);
                $journalIds2 = $jeStmt2->fetchAll(PDO::FETCH_COLUMN);
                foreach ($journalIds2 as $jeId) {
                    if ($oldTotal > 0) {
                        $ratio = $newTotal / $oldTotal;
                        $pdo->prepare("UPDATE journal_entry_lines SET debit = ROUND(debit * ?, 0), credit = ROUND(credit * ?, 0) WHERE journal_entry_id = ?")->execute([$ratio, $ratio, $jeId]);
                    }
                }
            }
        }
        
        // Re-fetch invoices after possible deletions
        $invStmt->execute([$visitId]);
        $invoices = $invStmt->fetchAll();

        foreach ($invoices as $inv) {
            $invoiceId = (int)$inv['id'];
            $totalBefore += (float)$inv['total'];

            // Get items for this invoice
            $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
            $itemStmt->execute([$invoiceId]);
            $items = $itemStmt->fetchAll();

            $itemsChanged = false;
            foreach ($items as $item) {
                $serviceId = (int)($item['service_id'] ?? 0);
                $description = $item['description'] ?? '';

                // If no service_id, try to find it from description matching service_catalog
                if (!$serviceId && $description) {
                    // Extract test/procedure name from description patterns like "Lab Test — NAME" or just "NAME"
                    $cleanName = $description;
                    // Remove prefix like "Lab Test — " or "Procedure — " etc (handle em-dash, en-dash, hyphen)
                    $cleanName = preg_replace('/^(Lab Test|Procedure|Consultation Fee|Consultation)\s*[\x{2014}\x{2013}\-]+\s*/u', '', $cleanName);
                    $cleanName = preg_replace('/\s*\[.*\]$/', '', $cleanName); // remove [Ins. agreed: ...] suffix
                    $cleanName = preg_replace('/\s*\([\d\-]+\)$/', '', $cleanName); // remove (code) suffix like (89365-1)
                    $cleanName = trim($cleanName);
                    if ($cleanName) {
                        $matchStmt = $pdo->prepare("SELECT id FROM service_catalog WHERE procedure_name = ? LIMIT 1");
                        $matchStmt->execute([$cleanName]);
                        $matchedId = $matchStmt->fetchColumn();
                        if (!$matchedId) {
                            // Try LIKE match
                            $matchStmt2 = $pdo->prepare("SELECT id FROM service_catalog WHERE procedure_name LIKE ? LIMIT 1");
                            $matchStmt2->execute(['%'.$cleanName.'%']);
                            $matchedId = $matchStmt2->fetchColumn();
                        }
                        if ($matchedId) {
                            $serviceId = (int)$matchedId;
                            // Update the invoice_item to set service_id for future
                            $pdo->prepare("UPDATE invoice_items SET service_id = ? WHERE id = ?")->execute([$serviceId, $item['id']]);
                        }
                    }
                }

                if (!$serviceId) continue;

                // Determine if this is a drug/medication item
                $category = strtolower($item['category'] ?? '');
                $isDrug = in_array($category, ['medication', 'pharmacy', 'drug']);

                // Look up insurance-agreed price
                $newInsurancePrice = null;
                $newUnitPrice = null;
                $newTopup = 0;
                $newApplyIns = 0;
                
                if ($isInsurance && isset($insPrices[$serviceId])) {
                    $newInsurancePrice = $insPrices[$serviceId];
                    $newApplyIns = 1;
                    
                    if ($isDrug) {
                        // For drugs: insurance_price + patient_topup
                        // Get the current selling price (unit_price is the selling price)
                        $sellingPrice = (float)$item['unit_price'];
                        $newTopup = max(0, $sellingPrice - $newInsurancePrice);
                        $newUnitPrice = $sellingPrice; // Keep original selling price
                    } else {
                        // For services: use insurance price only, no top-up
                        $newUnitPrice = $newInsurancePrice;
                        $newTopup = 0;
                    }
                }

                // Update item if price changed or insurance fields missing
                $needsUpdate = false;
                $qty = max(1, (int)$item['quantity']);
                $disc = (float)$item['discount'];
                
                if ($newUnitPrice !== null && abs($newUnitPrice - (float)$item['unit_price']) > 0.01) {
                    $needsUpdate = true;
                }
                
                // Also update if insurance fields are missing/wrong
                $currentInsPrice = (float)($item['insurance_price'] ?? 0);
                $currentTopup = (float)($item['patient_topup'] ?? 0);
                $currentApplyIns = (int)($item['apply_insurance'] ?? 0);
                
                if ($isInsurance && $newInsurancePrice !== null) {
                    if (abs($currentInsPrice - $newInsurancePrice) > 0.01 || 
                        abs($currentTopup - $newTopup) > 0.01 || 
                        $currentApplyIns != $newApplyIns) {
                        $needsUpdate = true;
                    }
                }
                
                if ($needsUpdate && $newUnitPrice !== null) {
                    $newTotal = max(0, ($qty * $newUnitPrice) - $disc);
                    $pdo->prepare("UPDATE invoice_items SET unit_price = ?, insurance_price = ?, patient_topup = ?, apply_insurance = ?, total = ? WHERE id = ?")
                        ->execute([$newUnitPrice, $newInsurancePrice ?? 0, $newTopup, $newApplyIns, $newTotal, $item['id']]);
                    $updatedItems++;
                    $itemsChanged = true;
                }
            }

            if ($itemsChanged || $isInsurance) {
                // Recalculate invoice totals
                $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = ?");
                $sumStmt->execute([$invoiceId]);
                $newSubtotal = (float)$sumStmt->fetchColumn();
                $discount = (float)$inv['discount'];
                $tax = (float)$inv['tax'];
                $newInvTotal = round(max(0, $newSubtotal - $discount + $tax));
                $paidAmount = round((float)$inv['paid_amount']);

                // Calculate patient balance based on copayment
                // copayPct=0 means insurance covers 100% → patient owes 0
                // copayPct=100 means private/self-pay → patient owes full amount
                if ($isInsurance && $copayPct < 100) {
                    $patientBalance = round($newInvTotal * $copayPct / 100) - $paidAmount;
                } else {
                    // Self-pay OR private insurance (copay=100) → patient pays full
                    $patientBalance = $newInvTotal - $paidAmount;
                }
                $patientBalance = max(0, round($patientBalance));
                if ($patientBalance <= 1.05 && ($paidAmount > 0 || ($isInsurance && $copayPct == 0))) {
                    $patientBalance = 0;
                }
                $newStatus = ($newInvTotal <= 0) ? 'issued' : ($patientBalance <= 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'issued'));

                $pdo->prepare("UPDATE invoices SET subtotal=?, total=?, balance=?, status=?, payment_type=?, insurance_id=? WHERE id=?")
                    ->execute([round($newSubtotal), $newInvTotal, $patientBalance, $newStatus, $isInsurance ? 'insurance' : $inv['payment_type'], $isInsurance ? $companyId : $inv['insurance_id'], $invoiceId]);
                $updatedInvoices++;
                $totalAfter += $newInvTotal;
            } else {
                $totalAfter += (float)$inv['total'];
            }
        }

        $pdo->commit();
        
        $message = "Recalculated: {$updatedInvoices} invoice(s), {$updatedItems} item(s) updated";
        if ($removedItems > 0) $message .= ", {$removedItems} deleted item(s) removed";
        $message .= ". Total: " . number_format($totalBefore, 0) . " → " . number_format($totalAfter, 0) . " RWF";
        
        success([
            'updated_invoices' => $updatedInvoices,
            'updated_items' => $updatedItems,
            'total_before' => $totalBefore,
            'total_after' => $totalAfter,
            'removed_items' => $removedItems,
            'company_id' => $companyId,
            'copayment_percentage' => $copayPct
        ], $message);
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Recalculation failed: ' . $e->getMessage());
    }
}

// ===== POST action=record_payment_full — payment entry (Dr Bank, Cr Receivable) =====
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'record_payment_full') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $invoiceId = (int)($input['invoice_id'] ?? 0);
    $patientAmount = round((float)($input['patient_amount'] ?? 0));
    $insAmount = round((float)($input['insurance_amount'] ?? 0));
    $bankAccountIdRaw = (int)($input['bank_account_id'] ?? 0);
    $insuranceId = (int)($input['insurance_id'] ?? 0);
    $reference = $input['reference'] ?? '';
    $paymentMethodRaw = $input['payment_method'] ?? 'cash';
    
    // Don't allow bank_account_id = 0, use default
    $bankAccountId = $bankAccountIdRaw > 0 ? $bankAccountIdRaw : 1003;

    $totalPay = $patientAmount + $insAmount;
    if (!$invoiceId || $totalPay <= 0) error('invoice_id and amount required', 422);

    $inv = $pdo->prepare("SELECT * FROM invoices WHERE id=?"); $inv->execute([$invoiceId]);
    $invoice = $inv->fetch();
    if (!$invoice) error('Invoice not found', 404);

    // Get patient receivable account (linked_client_id = patient_id under parent 7)
    $patientId = (int)$invoice['patient_id'];
    $patientAccId = fin_private_ar_acct($pdo);
    if (!$patientAccId) $patientAccId = 7; // fallback to general AR

    // Map payment method to a valid payment_records ENUM value
    $enumAllowed = ['cash','card','bank_transfer','check','insurance','mobile_money','mixed'];
    $methodMap = ['bank' => 'bank_transfer', 'mtn_momo' => 'mobile_money', 'unpaid' => 'cash'];
    $paymentMethodEnum = $methodMap[$paymentMethodRaw] ?? (in_array($paymentMethodRaw, $enumAllowed) ? $paymentMethodRaw : 'cash');

    // "unpaid" means the user is recording a deferred/owed payment — amount does NOT count as received
    $isUnpaid = ($paymentMethodRaw === 'unpaid');

    // Generate receipt number for this payment
    $lastReceipt = $pdo->query("SELECT receipt_number FROM payment_records ORDER BY id DESC LIMIT 1")->fetchColumn();
    $rSeq = 1;
    if ($lastReceipt && preg_match('/(\d+)$/', $lastReceipt, $rm)) $rSeq = (int)$rm[1] + 1;
    $receiptNo = 'RCP-' . date('Ymd') . '-' . str_pad($rSeq, 4, '0', STR_PAD_LEFT);

    $pdo->beginTransaction();
    try {
        $userId = $_SESSION['user_id'] ?? ($input['received_by'] ?? ($input['created_by'] ?? null));
        $empId = null;
        if ($userId) {
            $chk = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
            $chk->execute([$userId]);
            if ($chk->fetchColumn()) {
                $empId = (int)$userId;
            } else {
                $uStmt = $pdo->prepare("SELECT e.id FROM users u JOIN employees e ON e.employee_no = u.employee_id WHERE u.id = ?");
                $uStmt->execute([$userId]);
                $val = $uStmt->fetchColumn();
                if ($val) $empId = (int)$val;
            }
        }

        // --- Insert a payment_records row ---
        // For "unpaid" write-off: mark as 'written_off' status for audit trail
        $prStatus = $isUnpaid ? 'written_off' : 'completed';
        $pdo->prepare("
            INSERT INTO payment_records
                (invoice_id, patient_id, amount_paid, payment_method,
                 payment_date, payment_time, reference_number, receipt_number, status, created_by, created_at)
            VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, NOW())
        ")->execute([$invoiceId, $patientId, $totalPay, $paymentMethodEnum, $reference ?: null, $receiptNo, $prStatus, $empId]);

        // --- Recalculate invoice totals ---
        // patient_share = what the patient is responsible for (total × copay%)
        // Insurance pays the rest; once patient_share is covered (via payment or write-off), the invoice is PAID.
        $invTotal   = round((float)$invoice['total']);
        $insIdInv  = (int)($invoice['insurance_id'] ?? 0);
        $patIdInv  = (int)($invoice['patient_id']   ?? 0);
        $copayPctInv = 100.0;
        if ($insIdInv) {
            $cpS = $pdo->prepare("SELECT ip.copayment_percentage FROM patient_insurance pi LEFT JOIN insurance_packages ip ON ip.id=pi.package_id WHERE pi.id=?");
            $cpS->execute([$insIdInv]);
            $cpR = $cpS->fetch();
            if ($cpR && $cpR['copayment_percentage'] !== null) {
                $copayPctInv = (float)$cpR['copayment_percentage'];
            } elseif ($patIdInv) {
                $cpS2 = $pdo->prepare("SELECT ip.copayment_percentage FROM patient_insurance pi LEFT JOIN insurance_packages ip ON ip.id=pi.package_id WHERE pi.patient_id=? AND pi.company_id=? LIMIT 1");
                $cpS2->execute([$patIdInv, $insIdInv]);
                $cpR2 = $cpS2->fetch();
                if ($cpR2 && $cpR2['copayment_percentage'] !== null) {
                    $copayPctInv = (float)$cpR2['copayment_percentage'];
                }
            }
        }

        // Check if invoice items have explicit insurance override fields
        $itemStmt = $pdo->prepare("SELECT quantity, unit_price, insurance_price, patient_topup, apply_insurance FROM invoice_items WHERE invoice_id = ?");
        $itemStmt->execute([$invoiceId]);
        $invItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $hasInsuranceFields = false;
        foreach ($invItems as $item) {
            if (intval($item['apply_insurance'] ?? 0) > 0 || floatval($item['insurance_price'] ?? 0) > 0) {
                $hasInsuranceFields = true;
                break;
            }
        }

        if ($hasInsuranceFields) {
            $patientShare = 0;
            foreach ($invItems as $item) {
                $qty = intval($item['quantity'] ?: 1);
                $insPrice = floatval($item['insurance_price'] ?? 0);
                $topup = floatval($item['patient_topup'] ?? 0);
                $applyIns = intval($item['apply_insurance'] ?? 0);
                if ($applyIns && $insPrice > 0) {
                    $patientShare += round(($insPrice * ($copayPctInv / 100) * $qty) + ($topup * $qty));
                } elseif ($applyIns) {
                    $patientShare += round(floatval($item['unit_price'] ?? 0) * ($copayPctInv / 100) * $qty);
                } else {
                    $patientShare += round(floatval($item['unit_price'] ?? 0) * $qty);
                }
            }
            $patientShare = round($patientShare);
        } elseif ($insIdInv && $copayPctInv < 100) {
            $patientShare = round($invTotal * $copayPctInv / 100);
        } else {
            $patientShare = $invTotal;
        }

        $prSumCompleted = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=? AND status='completed'");
        $prSumCompleted->execute([$invoiceId]);
        $totalPaidSoFar = round((float)$prSumCompleted->fetchColumn());

        $prSumWrittenOff = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=? AND status='written_off'");
        $prSumWrittenOff->execute([$invoiceId]);
        $totalWrittenOff = round((float)$prSumWrittenOff->fetchColumn());

        $totalSettled = $totalPaidSoFar + $totalWrittenOff;

        // Balance = patient share minus what patient paid or had written off
        $newBalance = max(0, round($patientShare - $totalSettled));
        if ($newBalance <= 1.05 && ($totalSettled > 0 || $patientShare == 0)) {
            $newBalance = 0;
        }
        // Paid when patient's share is fully covered/written-off
        $newStatus  = $newBalance <= 0 ? 'paid' : ($totalSettled > 0 ? 'partial' : 'issued');

        $pdo->prepare("UPDATE invoices SET paid_amount=?, balance=?, status=?, payment_method=?, transaction_reference=?, payment_received_by=? WHERE id=?")
            ->execute([$totalPaidSoFar, $newBalance, $newStatus, $paymentMethodRaw, $reference ?: null, $userId, $invoiceId]);

        // --- Generate journal reference ---
        $lastRef = $pdo->query("SELECT reference FROM journal_entries ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 1; if ($lastRef && preg_match('/(\d+)$/', $lastRef, $m)) $seq = (int)$m[1] + 1;
        $jeRef = 'PAY-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        // WRITE-OFF ENTRY: Dr Expense Account, Cr Patient Receivable (debt forgiven/lost)
        if ($isUnpaid && $totalPay > 0) {
            $expenseAccountId = $bankAccountId; // user selected an expense account
            $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'write_off',?,1,NOW(),NOW())")
                ->execute([$jeRef, 'Write-off / Deferred - '.$invoice['invoice_no'].($reference?' ('.$reference.')':''), $invoiceId]);
            $jeId = (int)$pdo->lastInsertId();
            // Dr: Expense account (loss recognised)
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                ->execute([$jeId, $expenseAccountId, 'Write-off expense - '.$invoice['invoice_no'], $totalPay]);
            // Cr: Patient Receivable (debt cleared)
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")
                ->execute([$jeId, (int)$patientAccId, 'Write-off receivable - '.$invoice['invoice_no'], $totalPay]);
        }

        // REAL PAYMENT ENTRY: Dr Cash/Bank, Cr Patient Receivable
        if ($patientAmount > 0 && !$isUnpaid) {
            $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'payment',?,1,NOW(),NOW())")
                ->execute([$jeRef, 'Payment received - '.$invoice['invoice_no'].($reference?' ('.$reference.')':''), $invoiceId]);
            $jeId = (int)$pdo->lastInsertId();

            // Dr: Cash/Bank account (asset increases — money received)
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                ->execute([$jeId, $bankAccountId, 'Cash received - '.$invoice['invoice_no'], $patientAmount]);
            // Cr: Patient Receivable (patient debt decreases)
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")
                ->execute([$jeId, (int)$patientAccId, 'Patient payment - '.$invoice['invoice_no'], $patientAmount]);
        }

        // If insurance pays now too (Dr Bank, Cr Insurance Receivable)
        // If insurance pays — Dr: Cash/Bank (money in), Cr: Insurance Receivable (debt cleared)
        if ($insAmount > 0 && $insuranceId && !$isUnpaid) {
            $insAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE linked_insurance_id=$insuranceId AND parent_id=7 LIMIT 1")->fetchColumn();
            if (!$insAccId) $insAccId = 7;

            $jeRefIns = $jeRef . '-INS';
            $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'insurance_payment',?,1,NOW(),NOW())")
                ->execute([$jeRefIns, 'Insurance payment - '.$invoice['invoice_no'], $invoiceId]);
            $jeIdIns = (int)$pdo->lastInsertId();

            // Dr: Cash/Bank (insurance money received)
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                ->execute([$jeIdIns, $bankAccountId, 'Insurance payment received - '.$invoice['invoice_no'], $insAmount]);
            // Cr: Insurance Receivable (insurance debt cleared)
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")
                ->execute([$jeIdIns, (int)$insAccId, 'Insurance claim settled - '.$invoice['invoice_no'], $insAmount]);
        }

        $pdo->commit();
        success(['journal_ref' => $jeRef, 'receipt_no' => $receiptNo], 'Payment posted. Journal: '.$jeRef);
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed: '.$e->getMessage());
    }
}

// ===== POST action=record_payment — simple payment recording =====
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'record_payment') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $invoiceId = (int)($input['invoice_id'] ?? 0);
    $amount = round((float)($input['amount'] ?? 0));
    $method_pay = $input['payment_method'] ?? 'cash';
    $reference = $input['reference'] ?? '';
    $insuranceId = (int)($input['insurance_id'] ?? 0);

    if (!$invoiceId || !$amount) error('invoice_id and amount required', 422);

    // Get invoice
    $inv = $pdo->prepare("SELECT * FROM invoices WHERE id=?"); $inv->execute([$invoiceId]);
    $invoice = $inv->fetch();
    if (!$invoice) error('Invoice not found', 404);

    // Map payment method to a valid payment_records ENUM value
    $enumAllowed = ['cash','card','bank_transfer','check','insurance','mobile_money','mixed'];
    $methodMap = ['bank' => 'bank_transfer', 'mtn_momo' => 'mobile_money', 'unpaid' => 'cash'];
    $methodEnum = $methodMap[$method_pay] ?? (in_array($method_pay, $enumAllowed) ? $method_pay : 'cash');

    // Generate receipt number
    $lastReceipt2 = $pdo->query("SELECT receipt_number FROM payment_records ORDER BY id DESC LIMIT 1")->fetchColumn();
    $rSeq2 = 1;
    if ($lastReceipt2 && preg_match('/(\d+)$/', $lastReceipt2, $rm2)) $rSeq2 = (int)$rm2[1] + 1;
    $receiptNo2 = 'RCP-' . date('Ymd') . '-' . str_pad($rSeq2, 4, '0', STR_PAD_LEFT);

    $patientId2 = (int)$invoice['patient_id'];

    $pdo->beginTransaction();
    try {
        $userId2 = $_SESSION['user_id'] ?? ($input['received_by'] ?? ($input['created_by'] ?? null));
        $empId2 = null;
        if ($userId2) {
            $chk = $pdo->prepare("SELECT id FROM employees WHERE id = ?");
            $chk->execute([$userId2]);
            if ($chk->fetchColumn()) {
                $empId2 = (int)$userId2;
            } else {
                $uStmt = $pdo->prepare("SELECT e.id FROM users u JOIN employees e ON e.employee_no = u.employee_id WHERE u.id = ?");
                $uStmt->execute([$userId2]);
                $val = $uStmt->fetchColumn();
                if ($val) $empId2 = (int)$val;
            }
        }

        // --- Insert a new payment_records row ---
        $pdo->prepare("
            INSERT INTO payment_records
                (invoice_id, patient_id, amount_paid, payment_method,
                 payment_date, payment_time, reference_number, receipt_number, status, created_by, created_at)
            VALUES (?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, 'completed', ?, NOW())
        ")->execute([$invoiceId, $patientId2, $amount, $methodEnum, $reference ?: null, $receiptNo2, $empId2]);

        // --- Recalculate invoice totals from all payment_records rows ---
        $totalPaid2 = round((float)$pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=$invoiceId AND status='completed'")->fetchColumn());
        $totalWO2 = round((float)$pdo->query("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=$invoiceId AND status='written_off'")->fetchColumn());
        $invTotal2 = round((float)$invoice['total']);
        $insIdInv2 = (int)($invoice['insurance_id'] ?? 0);
        $patIdInv2 = (int)($invoice['patient_id'] ?? 0);
        $copayPctInv2 = 100.0;
        if ($insIdInv2 && ($invoice['payment_type'] === 'insurance' || $invoice['payment_type'] === 'mixed')) {
            $copayPctInv2 = getPatientCopayPercentage($pdo, $patIdInv2, $insIdInv2);
        }
        $patientShare2 = ($insIdInv2 && $copayPctInv2 < 100) ? round($invTotal2 * $copayPctInv2 / 100) : $invTotal2;
        $totalSettled2 = $totalPaid2 + $totalWO2;
        $newBalance = max(0, round($patientShare2 - $totalSettled2));
        if ($newBalance <= 1.05 && ($totalSettled2 > 0 || $patientShare2 == 0)) {
            $newBalance = 0;
        }
        $newStatus = $newBalance <= 0 ? 'paid' : ($totalSettled2 > 0 ? 'partial' : 'issued');
        $pdo->prepare("UPDATE invoices SET paid_amount=?, balance=?, status=?, payment_method=?, payment_received_by=? WHERE id=?")->execute([$totalPaid2, $newBalance, $newStatus, $method_pay, $userId2, $invoiceId]);

        // Create journal entry
        $lastRef = $pdo->query("SELECT reference FROM journal_entries ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 1; if ($lastRef && preg_match('/(\d+)$/', $lastRef, $m)) $seq = (int)$m[1] + 1;
        $jeRef = 'PAY-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        
        $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'payment',?,1,NOW(),NOW())")
            ->execute([$jeRef, 'Payment received - '.$invoice['invoice_no'], $invoiceId]);
        $jeId = (int)$pdo->lastInsertId();
        
        // Determine debit account (where money goes)
        $debitAccount = 3; // Default: Cash & Cash equivalents (id=3)
        if ($method_pay === 'bank') $debitAccount = 3; // Bank accounts are under 3
        if ($method_pay === 'mobile_money') $debitAccount = 3;
        
        // Determine credit account (where receivable is cleared)
        $creditAccount = fin_private_ar_acct($pdo); // Default: Receivable - Private
        if ($method_pay === 'insurance' && $insuranceId) {
            // Credit the insurance company's receivable account
            $insAcc = $pdo->query("SELECT id FROM chart_of_accounts WHERE linked_insurance_id=$insuranceId AND parent_id=7 LIMIT 1")->fetchColumn();
            if ($insAcc) $creditAccount = (int)$insAcc;
        }
        
        // Dr: Cash/Bank, Cr: Receivable/Insurance
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
            ->execute([$jeId, $debitAccount, 'Payment - '.$method_pay, $amount]);
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")
            ->execute([$jeId, $creditAccount, 'Invoice '.$invoice['invoice_no'].' settled', $amount]);
        
        $pdo->commit();
        success(['journal_ref' => $jeRef], 'Payment recorded. Journal: '.$jeRef);
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed: '.$e->getMessage());
    }
}

// ===== POST action=revert_payment — Revert a paid/partial invoice back to pending (admin only) =====
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'revert_payment') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $invoiceId = (int)($input['invoice_id'] ?? 0);
    $reason = trim($input['reason'] ?? '');

    if (!$invoiceId) {
        error('invoice_id is required', 422);
    }
    if (empty($reason)) {
        error('A reason / description for reverting this invoice is required.', 422);
    }

    // Check user admin status
    $userId = $_SESSION['user_id'] ?? null;
    if (!isUserAdmin($pdo, $userId ? (int)$userId : null)) {
        error('Unauthorized. Only administrators can revert invoice payments.', 403);
    }

    // Fetch invoice
    $invStmt = $pdo->prepare("
        SELECT i.*, 
               p.first_name, p.last_name, p.patient_no,
               coa.id AS patient_coa_id
        FROM invoices i
        JOIN patients p ON p.id = i.patient_id
        LEFT JOIN chart_of_accounts coa ON coa.linked_client_id = i.patient_id AND coa.parent_id = 7
        WHERE i.id = ?
    ");
    $invStmt->execute([$invoiceId]);
    $invoice = $invStmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        error('Invoice not found', 404);
    }

    $pdo->beginTransaction();
    try {
        // 1. Fetch all payment_records for this invoice
        $prStmt = $pdo->prepare("SELECT * FROM payment_records WHERE invoice_id = ?");
        $prStmt->execute([$invoiceId]);
        $payRecords = $prStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Fetch all journal entries associated with payments / write-offs for this invoice
        $jeStmt = $pdo->prepare("
            SELECT je.* 
            FROM journal_entries je 
            WHERE je.reference_id = ? 
              AND je.reference_type IN ('payment', 'write_off', 'insurance_payment')
            ORDER BY je.id ASC
        ");
        $jeStmt->execute([(string)$invoiceId]);
        $paymentJEs = $jeStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($paymentJEs) && !empty($invoice['invoice_no'])) {
            $jeStmt2 = $pdo->prepare("
                SELECT je.* 
                FROM journal_entries je 
                WHERE (je.description LIKE ? OR je.reference LIKE ?)
                  AND je.reference_type IN ('payment', 'write_off', 'insurance_payment')
                ORDER BY je.id ASC
            ");
            $likeInv = '%' . $invoice['invoice_no'] . '%';
            $jeStmt2->execute([$likeInv, $likeInv]);
            $paymentJEs = $jeStmt2->fetchAll(PDO::FETCH_ASSOC);
        }

        $createdReversals = [];

        // Helper to generate a REV- journal reference
        $lastRef = $pdo->query("SELECT reference FROM journal_entries ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 1; 
        if ($lastRef && preg_match('/(\d+)$/', $lastRef, $m)) {
            $seq = (int)$m[1] + 1;
        }

        if (!empty($paymentJEs)) {
            // Reverse each payment/write-off journal entry by swapping debits and credits
            foreach ($paymentJEs as $je) {
                $revRef = 'REV-' . date('Ymd') . '-' . str_pad($seq++, 4, '0', STR_PAD_LEFT);
                $revDesc = 'Reversal: ' . $je['description'] . ' | Reason: ' . $reason;

                $insJE = $pdo->prepare("
                    INSERT INTO journal_entries 
                        (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at)
                    VALUES (CURDATE(), ?, ?, 'payment_reversal', ?, 1, NOW(), NOW())
                ");
                $insJE->execute([$revRef, $revDesc, (string)$invoiceId]);
                $revJeId = (int)$pdo->lastInsertId();

                // Fetch original lines
                $linesStmt = $pdo->prepare("SELECT * FROM journal_entry_lines WHERE journal_entry_id = ?");
                $linesStmt->execute([$je['id']]);
                $lines = $linesStmt->fetchAll(PDO::FETCH_ASSOC);

                $insLine = $pdo->prepare("
                    INSERT INTO journal_entry_lines 
                        (journal_entry_id, account_id, line_description, debit, credit) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($lines as $line) {
                    // SWAP: Debit becomes Credit, Credit becomes Debit
                    $newDebit = $line['credit'];
                    $newCredit = $line['debit'];
                    $lineDesc = 'Reversal: ' . ($line['line_description'] ?: 'Payment Line') . ' (Reason: ' . $reason . ')';
                    $insLine->execute([$revJeId, $line['account_id'], $lineDesc, $newDebit, $newCredit]);
                }

                $createdReversals[] = $revRef;
            }
        } else {
            // Fallback: If no journal entry was found but paid_amount > 0, create explicit reversal
            $paidAmt = round((float)$invoice['paid_amount']);
            if ($paidAmt > 0) {
                $revRef = 'REV-' . date('Ymd') . '-' . str_pad($seq++, 4, '0', STR_PAD_LEFT);
                $revDesc = 'Payment Reversal: ' . $invoice['invoice_no'] . ' | Reason: ' . $reason;

                $pdo->prepare("
                    INSERT INTO journal_entries 
                        (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at)
                    VALUES (CURDATE(), ?, ?, 'payment_reversal', ?, 1, NOW(), NOW())
                ")->execute([$revRef, $revDesc, (string)$invoiceId]);
                $revJeId = (int)$pdo->lastInsertId();

                $patientAccId = fin_private_ar_acct($pdo);
                if (!$patientAccId) $patientAccId = 7;
                $cashAccId = 1003; // Default cash/bank

                // Dr Patient Receivable (reinstate debt)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                    ->execute([$revJeId, $patientAccId, 'Reinstated receivable — ' . $invoice['invoice_no'] . ' (Reason: ' . $reason . ')', $paidAmt]);
                // Cr Cash/Bank (reversal of received cash)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, 0, ?)")
                    ->execute([$revJeId, $cashAccId, 'Payment reversal cash — ' . $invoice['invoice_no'], $paidAmt]);

                $createdReversals[] = $revRef;
            }
        }

        // 3. Delete payment records for this invoice
        $delPrStmt = $pdo->prepare("DELETE FROM payment_records WHERE invoice_id = ?");
        $delPrStmt->execute([$invoiceId]);

        // 4. Recalculate original patient share to set the pending balance
        $invTotal = round((float)$invoice['total']);
        $insIdInv = (int)($invoice['insurance_id'] ?? 0);
        $patIdInv = (int)($invoice['patient_id'] ?? 0);
        $copayPctInv = 100.0;
        if ($insIdInv && ($invoice['payment_type'] === 'insurance' || $invoice['payment_type'] === 'mixed')) {
            $copayPctInv = getPatientCopayPercentage($pdo, $patIdInv, $insIdInv);
        }

        // Check if invoice items have explicit insurance fields
        $itemStmt = $pdo->prepare("SELECT quantity, unit_price, insurance_price, patient_topup, apply_insurance FROM invoice_items WHERE invoice_id = ?");
        $itemStmt->execute([$invoiceId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $hasInsuranceFields = false;
        foreach ($items as $item) {
            if (intval($item['apply_insurance'] ?? 0) > 0 || floatval($item['insurance_price'] ?? 0) > 0) {
                $hasInsuranceFields = true;
                break;
            }
        }

        if ($hasInsuranceFields) {
            $patShare = 0;
            foreach ($items as $item) {
                $qty = intval($item['quantity'] ?: 1);
                $insPrice = floatval($item['insurance_price'] ?? 0);
                $topup = floatval($item['patient_topup'] ?? 0);
                $applyIns = intval($item['apply_insurance'] ?? 0);
                if ($applyIns && $insPrice > 0) {
                    $patShare += round(($insPrice * ($copayPctInv / 100) * $qty) + ($topup * $qty));
                } else {
                    $patShare += round(floatval($item['unit_price'] ?? 0) * $qty);
                }
            }
            $restoredBalance = round($patShare);
        } elseif ($insIdInv && $copayPctInv < 100) {
            $restoredBalance = round($invTotal * $copayPctInv / 100);
        } else {
            $restoredBalance = $invTotal;
        }

        // 5. Reset invoice to 'issued' (pending), paid_amount = 0, balance = restoredBalance
        $resetStmt = $pdo->prepare("
            UPDATE invoices 
            SET status = 'issued',
                paid_amount = 0.00,
                balance = ?,
                payment_method = NULL,
                payment_received_by = NULL,
                transaction_reference = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $resetStmt->execute([$restoredBalance, $invoiceId]);

        // 6. Audit log in invoice_edit_logs
        $userName = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? ($_SESSION['username'] ?? 'Admin'));
        $pdo->prepare("
            INSERT INTO invoice_edit_logs 
                (invoice_id, user_id, user_name, action_type, edit_summary, old_values, new_values, edit_reason, ip_address, created_at)
            VALUES (?, ?, ?, 'revert_payment', ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $invoiceId,
            $userId,
            $userName,
            'Payment reverted back to pending (issued). Reversal JE: ' . implode(', ', $createdReversals),
            json_encode(['status' => $invoice['status'], 'paid_amount' => $invoice['paid_amount'], 'balance' => $invoice['balance'], 'payment_records' => count($payRecords)]),
            json_encode(['status' => 'issued', 'paid_amount' => 0, 'balance' => $restoredBalance, 'reversal_refs' => $createdReversals]),
            $reason,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        $pdo->commit();

        success([
            'invoice_id' => $invoiceId,
            'invoice_no' => $invoice['invoice_no'],
            'status' => 'issued',
            'restored_balance' => $restoredBalance,
            'reversal_refs' => $createdReversals,
            'deleted_payments_count' => count($payRecords)
        ], 'Invoice reverted to pending successfully. ' . count($createdReversals) . ' reversal journal entry(ies) posted.');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to revert invoice: ' . $e->getMessage(), 500);
    }
}

// ===== POST action=lab_order — create lab/radiology order with invoice =====
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'lab_order') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $orderType = $input['order_type'] ?? 'lab';
    $notes = $input['notes'] ?? '';
    $tests = $input['tests'] ?? [];
    $orderedBy = (int)($input['ordered_by'] ?? 0);
    
    if (!$visitId || !$patientId) error('visit_id and patient_id required', 422);
    if (empty($tests)) error('Select at least one test', 422);
    
    $pdo->beginTransaction();
    try {
        // Create invoice
        $lastInv = $pdo->query("SELECT invoice_no FROM invoices ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 1;
        if ($lastInv && preg_match('/(\d+)$/', $lastInv, $m)) $seq = (int)$m[1] + 1;
        $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        
        // Get visit payment info
        $vStmt = $pdo->prepare("SELECT payment_type, insurance_id FROM visits WHERE id = ?");
        $vStmt->execute([$visitId]);
        $vData = $vStmt->fetch();
        $paymentType = $vData['payment_type'] ?? 'self_pay';
        $insuranceId = $input['insurance_id'] ?? $vData['insurance_id'] ?? null;
        
        // Auto-fetch copayment percentage from insurance package
        $copayPct = 100.0;
        if (isset($input['copayment_percentage'])) {
            $copayPct = (float)$input['copayment_percentage'];
        } elseif ($insuranceId) {
            $copayPct = getPatientCopayPercentage($pdo, $patientId, (int)$insuranceId);
        }
        // Determine payment type based on copayment
        // If input explicitly says insurance, respect it (e.g., Private insurance with 100% copay)
        $inputPaymentType = $input['payment_type'] ?? null;
        if ($insuranceId && $copayPct >= 100 && $inputPaymentType !== 'insurance') {
            $paymentType = 'self_pay';
            $insuranceId = null;
        } elseif ($insuranceId) {
            $paymentType = 'insurance';
        }
        
        $subtotal = round(array_sum(array_column($tests, 'price')));
        
        // Calculate patient balance based on insurance
        $patientBalance = $subtotal;
        if ($insuranceId && $copayPct < 100) {
            $patientBalance = round($subtotal * $copayPct / 100);
        }
        
        $invStmt = $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'issued', ?, ?, NOW())");
        $invStmt->execute([$invoiceNo, $visitId, $patientId, $insuranceId ? 'insurance' : $paymentType, $insuranceId, $subtotal, $subtotal, $patientBalance, $orderType . ' order' . ($notes ? ': ' . $notes : '') . ($insuranceId && $copayPct < 100 ? ' (Ins '.(100-$copayPct).'%)' : ''), $orderedBy]);
        $invoiceId = (int)$pdo->lastInsertId();
        
        // Insert invoice items
        $iiStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, insurance_price, patient_topup, apply_insurance, discount, total) VALUES (?, ?, ?, ?, 1, ?, 0, 0, 0, 0, ?)");
        foreach ($tests as $t) {
            $iiStmt->execute([$invoiceId, (int)$t['service_id'], $t['name'], $orderType, (float)$t['price'], (float)$t['price']]);
        }
        
        // Create medical orders for tracking
        $moStmt = $pdo->prepare("INSERT INTO medical_orders (consultation_id, visit_id, patient_id, order_type, description, details, status, ordered_by) VALUES (NULL, ?, ?, ?, ?, ?, 'ordered', ?)");
        foreach ($tests as $t) {
            $moStmt->execute([$visitId, $patientId, $orderType, $t['name'], $notes, $orderedBy]);
        }
        
        $pdo->commit();
        success(['invoice_id' => $invoiceId, 'invoice_no' => $invoiceNo, 'total' => $subtotal], 'Order created with invoice');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed: ' . $e->getMessage());
    }
}

// ===== POST — create invoice with items =====
if ($method === 'POST' && (empty($action) || $action === 'create')) {
    $input = json_decode(file_get_contents('php://input'),true) ?? [];
    foreach (['visit_id','patient_id','items'] as $f) {
        if (empty($input[$f])) error("Field '$f' required",422);
    }
    if (!is_array($input['items']) || !count($input['items'])) error('At least one item required',422);

    $visitId   = (int)$input['visit_id'];
    $patientId = (int)$input['patient_id'];

    // Verify visit belongs to patient
    $chk = $pdo->prepare("SELECT id, insurance_id, payment_type FROM visits WHERE id=? AND patient_id=?");
    $chk->execute([$visitId,$patientId]);
    $visit = $chk->fetch();
    if (!$visit) error('Visit not found for this patient',404);

    // ── Backend duplicate guard: block service_ids already invoiced this visit ──
    // Applies to procedure, radiology/imaging, and lab categories (not medications).
    $guardCategories = ['procedure','radiology','imaging','lab','laboratory'];
    $incomingServiceIds = [];
    foreach ($input['items'] as $item) {
        $cat = strtolower($item['category'] ?? '');
        $sid = (int)($item['service_id'] ?? 0);
        if ($sid > 0 && in_array($cat, $guardCategories)) {
            $incomingServiceIds[] = $sid;
        }
    }
    if (!empty($incomingServiceIds)) {
        $placeholders = implode(',', array_fill(0, count($incomingServiceIds), '?'));
        $dupStmt = $pdo->prepare("
            SELECT DISTINCT ii.service_id
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE i.visit_id = ? AND ii.service_id IN ($placeholders)
            AND ii.category IN ('procedure','radiology','imaging','lab','laboratory')
        ");
        $dupStmt->execute(array_merge([$visitId], $incomingServiceIds));
        $dupIds = $dupStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($dupIds)) {
            error('Duplicate service(s) already invoiced in this visit. Remove duplicates and retry.', 409);
        }
    }
    // ──────────────────────────────────────────────────────────────────────────

    $paymentType = $input['payment_type'] ?? $visit['payment_type'] ?? 'self_pay';
    $insId       = $input['insurance_id'] ?? $visit['insurance_id'] ?? null;
    $discount    = (float)($input['discount'] ?? 0);
    $tax         = (float)($input['tax'] ?? 0);
    $notes       = $input['notes'] ?? null;

    // Auto-fetch copayment percentage from patient's insurance package
    $copaymentPct = 100.0;
    if (isset($input['copayment_percentage'])) {
        $copaymentPct = (float)$input['copayment_percentage'];
    } elseif ($insId) {
        $copaymentPct = getPatientCopayPercentage($pdo, $patientId, (int)$insId);
    }
    // Determine payment type based on copayment
    // If frontend explicitly passed payment_type=insurance, respect it (e.g., Private insurance with 100% copay still uses insurance prices)
    $inputPaymentType = $input['payment_type'] ?? null;
    if ($insId && $copaymentPct >= 100 && $inputPaymentType !== 'insurance') {
        $paymentType = 'self_pay';
        $insId = null;
    } elseif ($insId) {
        $paymentType = 'insurance';
    }

    $pdo->beginTransaction();

    $invoiceNo = generateInvoiceNo($pdo);
    $creatorUserId = $_SESSION['user_id'] ?? ($input['created_by'] ?? null);
    $stmt = $pdo->prepare("INSERT INTO invoices (invoice_no,visit_id,patient_id,payment_type,insurance_id,discount,tax,subtotal,total,balance,status,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,0,0,0,'draft',?,?,NOW())");
    $stmt->execute([$invoiceNo,$visitId,$patientId,$paymentType,$insId,$discount,$tax,$notes,$creatorUserId]);
    $invoiceId = (int)$pdo->lastInsertId();

    // Insert items
    $iStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id,service_id,description,category,quantity,unit_price,insurance_price,patient_topup,apply_insurance,discount,total) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($input['items'] as $item) {
        $qty           = max(1,(int)($item['quantity']??1));
        $price         = (float)($item['unit_price']??0);
        $itemDisc      = (float)($item['discount']??0);
        $insurancePrice= (float)($item['insurance_price']??0);
        $patientTopup  = (float)($item['patient_topup']??0);
        $applyIns      = !empty($item['apply_insurance']) ? 1 : 0;
        $total         = max(0, ($qty * $price) - $itemDisc);
        $iStmt->execute([
            $invoiceId,
            !empty($item['service_id']) ? (int)$item['service_id'] : null,
            $item['description'] ?? 'Service',
            $item['category'] ?? 'consultation',
            $qty, $price, $insurancePrice, $patientTopup, $applyIns, $itemDisc, $total
        ]);
    }

    recalcInvoice($pdo, $invoiceId);

    // ENTRY 1: Record the sale (Dr Patient Receivable + Dr Insurance Receivable, Cr Income 1007)
    $invData = $pdo->prepare("SELECT * FROM invoices WHERE id=?"); $invData->execute([$invoiceId]); $invRow = $invData->fetch();
    $totalAmount = (float)$invRow['total'];
    if ($totalAmount > 0) {
        $saleRef = 'SALE-' . date('Ymd') . '-' . str_pad($invoiceId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'invoice',?,1,NOW(),NOW())")
            ->execute([$saleRef, 'Sale - '.$invoiceNo, $invoiceId]);
        $saleJeId = (int)$pdo->lastInsertId();
        
        // Find patient's account (linked_client_id = patient_id under parent 7)
        $patientAccId = fin_private_ar_acct($pdo);
        if (!$patientAccId) $patientAccId = 7; // fallback

        // Determine split: if insurance, split between patient and insurance receivable
        $insId = (int)($invRow['insurance_id'] ?? 0);
        $copayPct = $copaymentPct; // use the auto-fetched or overridden value
        
        if ($insId && $copayPct < 100) {
            $patientPortion = round($totalAmount * $copayPct / 100);
            $insPortion = round($totalAmount - $patientPortion);
            
            // Dr: Patient Receivable (their specific account)
            if ($patientPortion > 0) {
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                    ->execute([$saleJeId, (int)$patientAccId, 'Patient portion ('.$copayPct.'%)', $patientPortion]);
            }
            // Dr: Insurance Receivable (find by linked_insurance_id)
            $insAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE linked_insurance_id=$insId AND parent_id=7 LIMIT 1")->fetchColumn();
            if (!$insAccId) $insAccId = 7;
            if ($insPortion > 0) {
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                    ->execute([$saleJeId, (int)$insAccId, 'Insurance portion ('.(100-$copayPct).'%)', $insPortion]);
            }
        } else {
            // Self-pay: Dr Patient Receivable for full amount
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                ->execute([$saleJeId, (int)$patientAccId, 'Patient receivable (full)', $totalAmount]);
        }
        
        // Cr: Income account (1007 - Consultation fee)
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,1007,?,0,?)")
            ->execute([$saleJeId, 'Revenue - '.$invoiceNo, $totalAmount]);
    }

    // If insurance applies, set balance to patient portion only (insurance portion tracked in receivable)
    if ($insId && $copaymentPct < 100 && $totalAmount > 0) {
        $patientBalance = round($totalAmount * $copaymentPct / 100);
        $pdo->prepare("UPDATE invoices SET balance=?, payment_type='insurance' WHERE id=?")->execute([$patientBalance, $invoiceId]);
    }

    // Update visit: append invoice reference to notes (do NOT overwrite department_id or doctor_name)
    $pdo->prepare("UPDATE visits SET status='in_progress', notes=CONCAT(COALESCE(notes,''), ' | Invoice: ', ?) WHERE id=?")
        ->execute([
            $invoiceNo,
            $visitId
        ]);

    $pdo->commit();

    // Return created invoice
    $finalStmt = $pdo->prepare("SELECT i.*,ii_count.cnt AS item_count FROM invoices i LEFT JOIN (SELECT invoice_id,COUNT(*) AS cnt FROM invoice_items GROUP BY invoice_id) ii_count ON ii_count.invoice_id=i.id WHERE i.id=?");
    $finalStmt->execute([$invoiceId]);
    success($finalStmt->fetch(), 'Invoice created successfully', 201);
}

// ===== POST edit_invoice_full (Admin only - Price Only) =====
if ($method === 'POST' && $action === 'edit_invoice_full') {
    ensureInvoiceEditLogsTable($pdo);
    if (!isUserAdmin($pdo)) {
        error('Access denied: only Administrators or isAdmin are authorized to edit invoices.', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $invoiceId = (int)($input['invoice_id'] ?? $id ?? 0);
    if (!$invoiceId) error('invoice_id required', 422);

    $editReason = trim($input['edit_reason'] ?? $input['reason'] ?? '');
    if (empty($editReason)) {
        error('Please provide a reason for editing this invoice', 422);
    }

    // Fetch current invoice state
    $chk = $pdo->prepare("
        SELECT i.*, p.first_name, p.last_name, p.patient_no
        FROM invoices i
        JOIN patients p ON p.id = i.patient_id
        WHERE i.id = ?
    ");
    $chk->execute([$invoiceId]);
    $oldInvoice = $chk->fetch();
    if (!$oldInvoice) error('Invoice not found', 404);

    $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id ASC");
    $itemStmt->execute([$invoiceId]);
    $oldItems = $itemStmt->fetchAll();

    if (empty($oldItems)) {
        error('Invoice has no items to edit', 422);
    }

    $items = $input['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        error('Invoice must contain at least one item', 422);
    }

    $pdo->beginTransaction();

    // 1. Update invoice adjustments if provided (discount, tax, notes)
    $discount = isset($input['discount']) ? (float)$input['discount'] : (float)($oldInvoice['discount'] ?? 0);
    $tax = isset($input['tax']) ? (float)$input['tax'] : (float)($oldInvoice['tax'] ?? 0);
    $notes = $input['notes'] ?? $oldInvoice['notes'];

    $pdo->prepare("
        UPDATE invoices 
        SET discount=?, tax=?, notes=?, updated_at=NOW()
        WHERE id=?
    ")->execute([$discount, $tax, $notes, $invoiceId]);

    // 2. Update existing items by price ONLY (preserve description, category, service_id, quantity, apply_insurance)
    $oldItemsById = [];
    foreach ($oldItems as $idx => $oi) {
        $oldItemsById[(int)$oi['id']] = $oi;
    }

    $updateItemStmt = $pdo->prepare("
        UPDATE invoice_items 
        SET unit_price = ?, insurance_price = ?, patient_topup = ?, total = ?
        WHERE id = ? AND invoice_id = ?
    ");

    $priceSummaryParts = [];
    foreach ($items as $idx => $it) {
        $itemId = null;
        if (isset($it['id']) && isset($oldItemsById[(int)$it['id']])) {
            $itemId = (int)$it['id'];
        } elseif (isset($oldItems[$idx])) {
            $itemId = (int)$oldItems[$idx]['id'];
        }

        if (!$itemId || !isset($oldItemsById[$itemId])) {
            continue; // Do not insert new items - price edits only on existing items
        }

        $orig = $oldItemsById[$itemId];
        $qty = max(1, (int)$orig['quantity']);
        $newUnitPrice = max(0, (float)($it['unit_price'] ?? $orig['unit_price']));
        $newInsPrice = max(0, (float)($it['insurance_price'] ?? $orig['insurance_price']));
        $newTopup = max(0, (float)($it['patient_topup'] ?? $orig['patient_topup']));
        $itemDisc = (float)($orig['discount'] ?? 0);
        $lineTotal = max(0, ($qty * $newUnitPrice) - $itemDisc);

        $updateItemStmt->execute([
            $newUnitPrice,
            $newInsPrice,
            $newTopup,
            $lineTotal,
            $itemId,
            $invoiceId
        ]);

        if (abs((float)$orig['unit_price'] - $newUnitPrice) > 0.01) {
            $priceSummaryParts[] = "{$orig['description']}: " . number_format($orig['unit_price']) . " → " . number_format($newUnitPrice) . " RWF";
        }
    }

    // 3. Recalculate invoice totals & balance
    recalcInvoice($pdo, $invoiceId);

    // 4. Fetch updated invoice and items for audit log
    $newInvStmt = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
    $newInvStmt->execute([$invoiceId]);
    $newInvoice = $newInvStmt->fetch();

    $newItemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id ASC");
    $newItemStmt->execute([$invoiceId]);
    $newItems = $newItemStmt->fetchAll();

    // 5. Update Journal Entry
    $insIdForJe = !empty($newInvoice['insurance_id']) ? (int)$newInvoice['insurance_id'] : null;
    recalcJournalEntry($pdo, $invoiceId, (float)$newInvoice['total'], (int)$newInvoice['patient_id'], $insIdForJe);

    // 6. Formulate summary of changes
    $oldTotal = (float)($oldInvoice['total'] ?? 0);
    $newTotal = (float)($newInvoice['total'] ?? 0);
    $summaryParts = [];

    if (abs($oldTotal - $newTotal) > 0.01) {
        $summaryParts[] = "Total: " . number_format($oldTotal) . " → " . number_format($newTotal) . " RWF";
    }
    if (!empty($priceSummaryParts)) {
        $summaryParts[] = "Price adjustments: " . implode(', ', $priceSummaryParts);
    }
    if (empty($summaryParts)) {
        $summaryParts[] = "Invoice prices updated (" . count($newItems) . " items)";
    }
    $summaryText = implode('; ', $summaryParts);

    // 7. Record in audit log
    $adminUserId = $_SESSION['user_id'] ?? null;
    $adminUserName = trim(($_SESSION['user_data']['first_name'] ?? '') . ' ' . ($_SESSION['user_data']['last_name'] ?? ''));
    if (empty($adminUserName) && $adminUserId) {
        $uStmt = $pdo->prepare("SELECT TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) FROM users WHERE id=?");
        $uStmt->execute([$adminUserId]);
        $adminUserName = $uStmt->fetchColumn() ?: ('User #'.$adminUserId);
    }
    if (empty($adminUserName)) $adminUserName = 'Administrator';

    $pdo->prepare("
        INSERT INTO invoice_edit_logs 
        (invoice_id, user_id, user_name, action_type, edit_summary, old_values, new_values, edit_reason, ip_address, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,NOW())
    ")->execute([
        $invoiceId,
        $adminUserId,
        $adminUserName,
        'edit_invoice_price',
        $summaryText,
        json_encode(['invoice' => $oldInvoice, 'items' => $oldItems]),
        json_encode(['invoice' => $newInvoice, 'items' => $newItems]),
        $editReason,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    $pdo->commit();

    success([
        'invoice' => $newInvoice,
        'items' => $newItems
    ], 'Invoice prices updated and journal entry synchronized successfully');
}

// ===== PUT — edit item price / update invoice =====
if ($method === 'PUT') {
    if (!$id) error('Invoice ID required',422);
    ensureInvoiceEditLogsTable($pdo);
    
    // Check admin authorization for modifications
    if (!isUserAdmin($pdo)) {
        error('Access denied: only Administrators or isAdmin are authorized to edit invoices.', 403);
    }

    $chk = $pdo->prepare("SELECT id, status, paid_amount, total, patient_id, insurance_id FROM invoices WHERE id=?");
    $chk->execute([$id]);
    $invoice = $chk->fetch();
    if (!$invoice) error('Invoice not found',404);

    $input = json_decode(file_get_contents('php://input'),true) ?? [];
    $action2 = $input['action'] ?? 'update';
    $editReason = trim($input['edit_reason'] ?? $input['reason'] ?? 'Invoice price modified by Admin');

    // Admin info for audit
    $adminUserId = $_SESSION['user_id'] ?? null;
    $adminUserName = trim(($_SESSION['user_data']['first_name'] ?? '') . ' ' . ($_SESSION['user_data']['last_name'] ?? ''));
    if (empty($adminUserName) && $adminUserId) {
        $uStmt = $pdo->prepare("SELECT TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) FROM users WHERE id=?");
        $uStmt->execute([$adminUserId]);
        $adminUserName = $uStmt->fetchColumn() ?: ('User #'.$adminUserId);
    }
    if (empty($adminUserName)) $adminUserName = 'Administrator';

    if ($action2 === 'add_item') {
        error('Action not permitted: Adding new items is disabled. Invoices can only be edited by price.', 403);
    }

    if ($action2 === 'remove_item') {
        error('Action not permitted: Deleting items is disabled. Invoices can only be edited by price.', 403);
    }

    if ($action2 === 'edit_item') {
        $itemId = (int)($input['item_id']??0);
        if (!$itemId) error('item_id required',422);

        $itOldStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE id=? AND invoice_id=?");
        $itOldStmt->execute([$itemId, $id]);
        $oldRow = $itOldStmt->fetch();
        if (!$oldRow) error('Invoice item not found', 404);

        $price = isset($input['unit_price']) ? (float)$input['unit_price'] : (float)$oldRow['unit_price'];
        $insPrice = isset($input['insurance_price']) ? (float)$input['insurance_price'] : (float)$oldRow['insurance_price'];
        $topup = isset($input['patient_topup']) ? (float)$input['patient_topup'] : (float)$oldRow['patient_topup'];
        $disc = isset($input['discount']) ? (float)$input['discount'] : (float)$oldRow['discount'];

        $qty = (int)$oldRow['quantity'];
        $total = max(0, ($qty * $price) - $disc);
        
        $pdo->prepare("UPDATE invoice_items SET unit_price=?, insurance_price=?, patient_topup=?, discount=?, total=? WHERE id=? AND invoice_id=?")
            ->execute([$price, $insPrice, $topup, $disc, $total, $itemId, $id]);
        recalcInvoice($pdo,$id);

        // Audit log
        $pdo->prepare("INSERT INTO invoice_edit_logs (invoice_id, user_id, user_name, action_type, edit_summary, edit_reason, ip_address, created_at) VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([$id, $adminUserId, $adminUserName, 'edit_item_price', "Updated price for '{$oldRow['description']}' to " . number_format($price) . " RWF", $editReason, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);

        success(null,'Item price updated and journal entry synchronized');
    }

    if ($action2 === 'payment') {
        $paid = (float)($input['paid_amount']??0);
        $invStmt = $pdo->prepare("SELECT total FROM invoices WHERE id=?");
        $invStmt->execute([$id]);
        $total = (float)$invStmt->fetchColumn();
        $balance = max(0,$total-$paid);
        if ($balance <= 1.05 && ($paid > 0 || $total == 0)) {
            $balance = 0;
        }
        $status  = $balance<=0 ? 'paid' : ($paid>0 ? 'partial' : 'issued');
        $pdo->prepare("UPDATE invoices SET paid_amount=?,balance=?,status=? WHERE id=?")->execute([$paid,$balance,$status,$id]);
        success(null,'Payment updated');
    }

    // General field update (discount, tax, notes)
    $allowed = ['discount','tax','notes'];
    $fields=[]; $params=[];
    foreach ($allowed as $f) {
        if (array_key_exists($f,$input)) { $fields[]="$f=?"; $params[]=$input[$f]; }
    }
    if ($fields) {
        $params[]=$id;
        $pdo->prepare("UPDATE invoices SET ".implode(',',$fields)." WHERE id=?")->execute($params);
        recalcInvoice($pdo,$id);

        // Audit log
        $pdo->prepare("INSERT INTO invoice_edit_logs (invoice_id, user_id, user_name, action_type, edit_summary, edit_reason, ip_address, created_at) VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([$id, $adminUserId, $adminUserName, 'update_fields', "Updated invoice adjustments: " . implode(', ', array_keys($fields)), $editReason, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
    }
    success(null,'Invoice updated and journal entry synchronized');
}

// ===== DELETE =====
if ($method === 'DELETE') {
    if (!$id) error('Invoice ID required',422);
    ensureInvoiceEditLogsTable($pdo);
    if (!isUserAdmin($pdo)) {
        error('Access denied: only Administrators are authorized to delete invoices.', 403);
    }
    $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]);
    success(null,'Invoice deleted');
}

error('Method not allowed',405);

} catch(PDOException $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    error('Database error: '.$e->getMessage(),500);
} catch(Exception $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    error('Server error: '.$e->getMessage(),500);
}
?>
