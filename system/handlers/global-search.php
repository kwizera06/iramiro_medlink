<?php
// handlers/global-search.php — Full System Global Search API
header('Content-Type: application/json; charset=utf-8');

@ini_set('pcre.jit', 0);
require_once __DIR__ . '/../includes/database.php';

function jsonResp($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function ok($data = null, $message = 'Success') {
    jsonResp(['success' => true, 'message' => $message, 'data' => $data]);
}

function err($message, $statusCode = 400) {
    jsonResp(['success' => false, 'message' => $message], $statusCode);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isSearchAdminUser() {
    $adminRoles = ['admin', 'administrator', 'isadmin'];

    // Check is_admin boolean / truthy flag
    if (!empty($_SESSION['user_data']['is_admin']) || !empty($_SESSION['is_admin'])) {
        return true;
    }

    // Check role slugs in session
    if (isset($_SESSION['user_data']['role_slugs']) && is_array($_SESSION['user_data']['role_slugs'])) {
        foreach ($_SESSION['user_data']['role_slugs'] as $slug) {
            if (in_array(strtolower(trim((string)$slug)), $adminRoles, true)) {
                return true;
            }
        }
    }

    // Check role names in session
    if (isset($_SESSION['user_data']['role_names']) && is_array($_SESSION['user_data']['role_names'])) {
        foreach ($_SESSION['user_data']['role_names'] as $name) {
            if (in_array(strtolower(trim((string)$name)), $adminRoles, true)) {
                return true;
            }
        }
    }

    // Check single role in session
    if (isset($_SESSION['user_data']['role']) && in_array(strtolower(trim((string)$_SESSION['user_data']['role'])), $adminRoles, true)) {
        return true;
    }
    if (isset($_SESSION['role_name']) && in_array(strtolower(trim((string)$_SESSION['role_name'])), $adminRoles, true)) {
        return true;
    }
    if (isset($_SESSION['role']) && in_array(strtolower(trim((string)$_SESSION['role'])), $adminRoles, true)) {
        return true;
    }

    $userId = $_SESSION['user_id'] ?? ($_SESSION['user_data']['id'] ?? null);
    if (!$userId) {
        return false;
    }

    global $pdo;
    if (!isset($pdo) && isset($GLOBALS['pdo'])) {
        $pdo = $GLOBALS['pdo'];
    }
    if (!isset($pdo)) {
        require_once __DIR__ . '/../includes/database.php';
        if (!isset($pdo) && isset($GLOBALS['pdo'])) {
            $pdo = $GLOBALS['pdo'];
        }
    }
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
    } catch (Exception $e) {
        // ignore
    }

    return false;
}

if (!isSearchAdminUser()) {
    err('Access denied: System search is restricted to administrators.', 403);
}

if (!isset($pdo)) {
    err('Database connection unavailable', 500);
}

$query = trim($_GET['q'] ?? '');
$category = trim(strtolower($_GET['category'] ?? 'all'));
$limit = max(1, min((int)($_GET['limit'] ?? ($category === 'all' ? 6 : 40)), 100));

if ($query === '') {
    jsonResp([
        'success' => true,
        'query' => '',
        'category' => $category,
        'total_count' => 0,
        'counts' => [
            'patients' => 0,
            'staff' => 0,
            'pharmacy' => 0,
            'invoices' => 0,
            'visits' => 0,
            'departments' => 0,
            'documents' => 0,
            'services' => 0,
            'emergency' => 0
        ],
        'results' => [
            'patients' => [],
            'staff' => [],
            'pharmacy' => [],
            'invoices' => [],
            'visits' => [],
            'departments' => [],
            'documents' => [],
            'services' => [],
            'emergency' => []
        ]
    ]);
}

$searchLike = '%' . $query . '%';
$results = [
    'patients' => [],
    'staff' => [],
    'pharmacy' => [],
    'invoices' => [],
    'visits' => [],
    'departments' => [],
    'documents' => [],
    'services' => [],
    'emergency' => []
];
$counts = [
    'patients' => 0,
    'staff' => 0,
    'pharmacy' => 0,
    'invoices' => 0,
    'visits' => 0,
    'departments' => 0,
    'documents' => 0,
    'services' => 0,
    'emergency' => 0
];

// ============================================
// 1. PATIENTS
// ============================================
if ($category === 'all' || $category === 'patients') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.patient_no, 
                p.first_name, 
                p.last_name, 
                p.phone, 
                p.national_id, 
                p.email, 
                p.gender, 
                p.date_of_birth, 
                p.status,
                (SELECT ic.name FROM patient_insurance pi JOIN insurance_companies ic ON ic.id = pi.company_id WHERE pi.patient_id = p.id AND pi.status = 'active' LIMIT 1) AS insurance_company
            FROM patients p
            WHERE 
                p.first_name LIKE ? OR 
                p.last_name LIKE ? OR 
                CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR 
                p.patient_no LIKE ? OR 
                p.phone LIKE ? OR 
                p.national_id LIKE ? OR 
                p.email LIKE ?
            ORDER BY 
                CASE WHEN p.patient_no = ? THEN 1
                     WHEN CONCAT(p.first_name, ' ', p.last_name) LIKE ? THEN 2
                     ELSE 3 END,
                p.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(5, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(6, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(7, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(8, $query, PDO::PARAM_STR);
        $stmt->bindValue(9, $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(10, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $results['patients'][] = [
                'id' => (int)$row['id'],
                'type' => 'patient',
                'title' => trim($row['first_name'] . ' ' . $row['last_name']),
                'subtitle' => 'UHID: ' . ($row['patient_no'] ?: 'N/A') . ($row['phone'] ? ' | Phone: ' . $row['phone'] : '') . ($row['national_id'] ? ' | ID: ' . $row['national_id'] : ''),
                'badge' => ucfirst($row['status'] ?: 'Active'),
                'badge_class' => ($row['status'] === 'active' ? 'badge-success' : 'badge-warning'),
                'insurance' => $row['insurance_company'] ?: 'Cash / None',
                'url' => 'patients.php?module=receptionist&sub=manage&search=' . urlencode($row['patient_no'] ?: $row['first_name']),
                'action_label' => 'View Patient'
            ];
        }
        $counts['patients'] = count($results['patients']);
    } catch (Exception $e) {
    }
}

// ============================================
// 2. STAFF & DOCTORS (EMPLOYEES & USERS)
// ============================================
if ($category === 'all' || $category === 'staff' || $category === 'doctors' || $category === 'employees') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                e.id, 
                e.employee_no, 
                e.first_name, 
                e.last_name, 
                e.position, 
                e.phone, 
                e.email, 
                e.employment_type, 
                e.status, 
                d.name AS department_name
            FROM employees e
            LEFT JOIN departments d ON d.id = e.department_id
            WHERE 
                e.first_name LIKE ? OR 
                e.last_name LIKE ? OR 
                CONCAT(e.first_name, ' ', e.last_name) LIKE ? OR 
                e.employee_no LIKE ? OR 
                e.position LIKE ? OR 
                e.phone LIKE ? OR 
                e.email LIKE ? OR
                d.name LIKE ?
            ORDER BY 
                CASE WHEN e.employee_no = ? THEN 1
                     WHEN CONCAT(e.first_name, ' ', e.last_name) LIKE ? THEN 2
                     ELSE 3 END,
                e.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(5, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(6, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(7, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(8, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(9, $query, PDO::PARAM_STR);
        $stmt->bindValue(10, $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(11, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $results['staff'][] = [
                'id' => (int)$row['id'],
                'type' => 'staff',
                'title' => trim($row['first_name'] . ' ' . $row['last_name']),
                'subtitle' => ($row['position'] ?: 'Staff') . ($row['department_name'] ? ' — ' . $row['department_name'] : '') . ($row['employee_no'] ? ' (' . $row['employee_no'] . ')' : ''),
                'badge' => $row['position'] ?: 'Staff',
                'badge_class' => 'badge-info',
                'phone' => $row['phone'] ?: '',
                'email' => $row['email'] ?: '',
                'url' => 'hr.php?module=hr&sub=employees&search=' . urlencode($row['employee_no'] ?: $row['first_name']),
                'action_label' => 'View Staff Profile'
            ];
        }
        $counts['staff'] = count($results['staff']);
    } catch (Exception $e) {
    }
}

// ============================================
// 3. PHARMACY & INVENTORY ITEMS (DRUGS, SUPPLIES)
// ============================================
if ($category === 'all' || $category === 'pharmacy' || $category === 'inventory' || $category === 'medications') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                i.id, 
                i.item_code, 
                i.name, 
                i.generic_name, 
                i.unit_price, 
                i.selling_price, 
                i.is_medication, 
                i.is_controlled,
                i.is_active,
                c.name AS category_name
            FROM items i
            LEFT JOIN item_categories c ON c.id = i.category_id
            WHERE 
                i.name LIKE ? OR 
                i.generic_name LIKE ? OR 
                i.item_code LIKE ? OR 
                i.description LIKE ? OR
                c.name LIKE ?
            ORDER BY 
                CASE WHEN i.item_code = ? THEN 1
                     WHEN i.name LIKE ? THEN 2
                     ELSE 3 END,
                i.is_medication DESC,
                i.name ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(5, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(6, $query, PDO::PARAM_STR);
        $stmt->bindValue(7, $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(8, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $isMed = (bool)$row['is_medication'];
            $priceText = number_format((float)$row['selling_price'], 0) . ' RWF';
            $results['pharmacy'][] = [
                'id' => (int)$row['id'],
                'type' => 'pharmacy',
                'title' => $row['name'],
                'subtitle' => ($row['generic_name'] ? 'Generic: ' . $row['generic_name'] . ' | ' : '') . 'Code: ' . ($row['item_code'] ?: 'N/A') . ' | Price: ' . $priceText . ($row['category_name'] ? ' (' . $row['category_name'] . ')' : ''),
                'badge' => $isMed ? 'Medication' : ($row['category_name'] ?: 'Item'),
                'badge_class' => $isMed ? 'badge-primary' : 'badge-secondary',
                'is_medication' => $isMed,
                'price' => (float)$row['selling_price'],
                'url' => $isMed ? 'pharmacy.php?module=pharmacy&sub=prescription' : 'inventory.php?module=inventory&sub=items&search=' . urlencode($row['item_code'] ?: $row['name']),
                'action_label' => $isMed ? 'View in Pharmacy' : 'View Item Catalog'
            ];
        }
        $counts['pharmacy'] = count($results['pharmacy']);
    } catch (Exception $e) {
    }
}

// ============================================
// 4. INVOICES & BILLING
// ============================================
if ($category === 'all' || $category === 'invoices' || $category === 'billing' || $category === 'finance') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                inv.id, 
                inv.invoice_no, 
                inv.visit_id, 
                inv.patient_id, 
                inv.payment_type, 
                inv.total, 
                inv.paid_amount, 
                inv.balance, 
                inv.status, 
                inv.created_at,
                inv.transaction_reference,
                p.first_name, 
                p.last_name, 
                p.patient_no
            FROM invoices inv
            LEFT JOIN patients p ON p.id = inv.patient_id
            WHERE 
                inv.invoice_no LIKE ? OR 
                inv.transaction_reference LIKE ? OR 
                p.first_name LIKE ? OR 
                p.last_name LIKE ? OR 
                CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR
                p.patient_no LIKE ?
            ORDER BY 
                CASE WHEN inv.invoice_no = ? THEN 1 ELSE 2 END,
                inv.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(5, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(6, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(7, $query, PDO::PARAM_STR);
        $stmt->bindValue(8, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $patientName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $tot = number_format((float)$row['total'], 0) . ' RWF';
            $bal = number_format((float)$row['balance'], 0) . ' RWF';
            $status = ucfirst($row['status'] ?: 'Pending');
            $badgeClass = ($row['status'] === 'paid') ? 'badge-success' : (($row['status'] === 'partial') ? 'badge-warning' : 'badge-danger');

            $results['invoices'][] = [
                'id' => (int)$row['id'],
                'type' => 'invoice',
                'title' => 'Invoice #' . ($row['invoice_no'] ?: $row['id']),
                'subtitle' => ($patientName ? 'Patient: ' . $patientName . ' | ' : '') . 'Total: ' . $tot . ' | Balance: ' . $bal . ' | Type: ' . ucfirst($row['payment_type'] ?: 'Cash'),
                'badge' => $status,
                'badge_class' => $badgeClass,
                'total' => (float)$row['total'],
                'date' => substr($row['created_at'] ?? '', 0, 10),
                'url' => 'invoicing.php?module=finance&sub=invoices&search=' . urlencode($row['invoice_no'] ?: $row['id']),
                'action_label' => 'Open Invoice'
            ];
        }
        $counts['invoices'] = count($results['invoices']);
    } catch (Exception $e) {
    }
}

// ============================================
// 5. VISITS & CONSULTATIONS
// ============================================
if ($category === 'all' || $category === 'visits' || $category === 'consultations') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                v.id, 
                v.visit_no, 
                v.visit_date, 
                v.visit_time, 
                v.payment_type, 
                v.chief_complaint, 
                v.status, 
                v.consultation_status,
                p.first_name, 
                p.last_name, 
                p.patient_no, 
                d.name AS department_name, 
                c.diagnosis, 
                c.doctor_name
            FROM visits v
            LEFT JOIN patients p ON p.id = v.patient_id
            LEFT JOIN departments d ON d.id = v.department_id
            LEFT JOIN consultations c ON c.visit_id = v.id
            WHERE 
                v.visit_no LIKE ? OR 
                v.chief_complaint LIKE ? OR 
                p.first_name LIKE ? OR 
                p.last_name LIKE ? OR 
                CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR 
                p.patient_no LIKE ? OR 
                c.diagnosis LIKE ? OR 
                c.doctor_name LIKE ? OR
                v.doctor_name LIKE ?
            ORDER BY 
                CASE WHEN v.visit_no = ? THEN 1 ELSE 2 END,
                v.id DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(5, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(6, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(7, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(8, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(9, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(10, $query, PDO::PARAM_STR);
        $stmt->bindValue(11, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $patientName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $docName = $row['doctor_name'] ?: 'Doctor';
            $diagnosis = $row['diagnosis'] ?: $row['chief_complaint'] ?: 'General Consultation';

            $results['visits'][] = [
                'id' => (int)$row['id'],
                'type' => 'visit',
                'title' => 'Visit #' . ($row['visit_no'] ?: $row['id']) . ' — ' . $patientName,
                'subtitle' => ($row['department_name'] ? $row['department_name'] . ' | ' : '') . 'Doctor: ' . $docName . ' | Reason/Dx: ' . $diagnosis . ' | Date: ' . ($row['visit_date'] ?: 'N/A'),
                'badge' => ucfirst($row['consultation_status'] ?: $row['status'] ?: 'Active'),
                'badge_class' => ($row['consultation_status'] === 'completed' ? 'badge-success' : 'badge-info'),
                'url' => 'patients.php?module=nurse&sub=patients-visit&search=' . urlencode($row['visit_no'] ?: $patientName),
                'action_label' => 'View Visit Details'
            ];
        }
        $counts['visits'] = count($results['visits']);
    } catch (Exception $e) {
    }
}

// ============================================
// 6. DEPARTMENTS
// ============================================
if ($category === 'all' || $category === 'departments') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, code, name, description, head_of_department, location, phone
            FROM departments
            WHERE 
                name LIKE ? OR 
                code LIKE ? OR 
                description LIKE ? OR 
                head_of_department LIKE ? OR
                location LIKE ?
            ORDER BY 
                CASE WHEN code = ? THEN 1
                     WHEN name LIKE ? THEN 2
                     ELSE 3 END,
                name ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(5, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(6, $query, PDO::PARAM_STR);
        $stmt->bindValue(7, $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(8, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $results['departments'][] = [
                'id' => (int)$row['id'],
                'type' => 'department',
                'title' => $row['name'] . ($row['code'] ? ' (' . $row['code'] . ')' : ''),
                'subtitle' => ($row['head_of_department'] ? 'HOD: ' . $row['head_of_department'] . ' | ' : '') . ($row['location'] ? 'Location: ' . $row['location'] : 'Hospital Department'),
                'badge' => 'Department',
                'badge_class' => 'badge-primary',
                'url' => 'departments.php',
                'action_label' => 'Open Department'
            ];
        }
        $counts['departments'] = count($results['departments']);
    } catch (Exception $e) {
    }
}

// ============================================
// 7. DOCUMENTS & MEDICAL RECORDS
// ============================================
if ($category === 'all' || $category === 'documents' || $category === 'medical_documents') {
    try {
        // General documents
        $stmt = $pdo->prepare("
            SELECT id, title, document_type, file_name, file_extension, status, created_at
            FROM documents
            WHERE title LIKE ? OR file_name LIKE ? OR document_type LIKE ? OR tags LIKE ?
            LIMIT ?
        ");
        $stmt->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $docRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($docRows as $row) {
            $results['documents'][] = [
                'id' => (int)$row['id'],
                'type' => 'document',
                'title' => $row['title'] ?: $row['file_name'],
                'subtitle' => 'Doc Type: ' . ucfirst($row['document_type'] ?: 'File') . ' | Ext: ' . strtoupper($row['file_extension'] ?: 'DOC') . ' | Created: ' . substr($row['created_at'] ?? '', 0, 10),
                'badge' => ucfirst($row['document_type'] ?: 'Document'),
                'badge_class' => 'badge-info',
                'url' => 'documents.php',
                'action_label' => 'View Documents'
            ];
        }

        // Medical certificates / documents
        $stmtMed = $pdo->prepare("
            SELECT id, reference_no, document_type, patient_name, physician_name, created_at
            FROM medical_documents
            WHERE reference_no LIKE ? OR patient_name LIKE ? OR physician_name LIKE ? OR document_type LIKE ?
            LIMIT ?
        ");
        $stmtMed->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmtMed->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmtMed->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmtMed->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmtMed->bindValue(5, $limit, PDO::PARAM_INT);
        $stmtMed->execute();
        $medRows = $stmtMed->fetchAll(PDO::FETCH_ASSOC);

        foreach ($medRows as $row) {
            $results['documents'][] = [
                'id' => (int)$row['id'],
                'type' => 'medical_document',
                'title' => ($row['reference_no'] ? '[' . $row['reference_no'] . '] ' : '') . ucfirst(str_replace('-', ' ', $row['document_type'] ?: 'Medical Cert')),
                'subtitle' => 'Patient: ' . ($row['patient_name'] ?: 'N/A') . ' | Physician: ' . ($row['physician_name'] ?: 'Doctor') . ' | Date: ' . substr($row['created_at'] ?? '', 0, 10),
                'badge' => 'Medical Certificate',
                'badge_class' => 'badge-success',
                'url' => 'medical-documents.php?module=documents&sub=generated',
                'action_label' => 'View Certificate'
            ];
        }
        $counts['documents'] = count($results['documents']);
    } catch (Exception $e) {
    }
}

// ============================================
// 8. SERVICE CATALOG & PROCEDURES
// ============================================
if ($category === 'all' || $category === 'services' || $category === 'procedures' || $category === 'catalog') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, rhic, speciality, ichi_code, procedure_name, primary_health_facility, secondary_health_facility, tertiary_health_facility
            FROM service_catalog
            WHERE is_active = 1 AND (
                procedure_name LIKE ? OR 
                ichi_code LIKE ? OR 
                speciality LIKE ? OR 
                rhic LIKE ?)
            ORDER BY 
                CASE WHEN procedure_name LIKE ? THEN 1 ELSE 2 END,
                procedure_name ASC
            LIMIT ?
        ");
        $stmt->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmt->bindValue(5, $query . '%', PDO::PARAM_STR);
        $stmt->bindValue(6, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $results['services'][] = [
                'id' => (int)$row['id'],
                'type' => 'service',
                'title' => $row['procedure_name'],
                'subtitle' => ($row['speciality'] ? 'Speciality: ' . $row['speciality'] . ' | ' : '') . 'ICHI: ' . ($row['ichi_code'] ?: 'N/A') . ' | RHIC: ' . ($row['rhic'] ?: 'N/A') . ' | Tariff: ' . number_format((float)$row['primary_health_facility'], 0) . ' RWF',
                'badge' => $row['speciality'] ?: 'Procedure',
                'badge_class' => 'badge-secondary',
                'url' => 'service-catalog.php?module=admin&sub=catalog&search=' . urlencode($row['procedure_name']),
                'action_label' => 'View Tariff Catalog'
            ];
        }
        $counts['services'] = count($results['services']);
    } catch (Exception $e) {
    }
}

// ============================================
// 9. EMERGENCY & WARDS
// ============================================
if ($category === 'all' || $category === 'emergency' || $category === 'wards') {
    try {
        // Wards
        $stmtWard = $pdo->prepare("
            SELECT id, name, code, ward_type, floor, building, is_active
            FROM wards
            WHERE name LIKE ? OR code LIKE ? OR ward_type LIKE ? OR building LIKE ?
            LIMIT ?
        ");
        $stmtWard->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmtWard->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmtWard->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmtWard->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmtWard->bindValue(5, $limit, PDO::PARAM_INT);
        $stmtWard->execute();
        $wardRows = $stmtWard->fetchAll(PDO::FETCH_ASSOC);

        foreach ($wardRows as $row) {
            $results['emergency'][] = [
                'id' => (int)$row['id'],
                'type' => 'ward',
                'title' => 'Ward: ' . $row['name'] . ($row['code'] ? ' (' . $row['code'] . ')' : ''),
                'subtitle' => 'Type: ' . ucfirst($row['ward_type'] ?: 'General') . ($row['building'] ? ' | Bldg: ' . $row['building'] : '') . ($row['floor'] ? ' Fl: ' . $row['floor'] : ''),
                'badge' => 'Ward',
                'badge_class' => 'badge-info',
                'url' => 'ward.php',
                'action_label' => 'Open Ward'
            ];
        }

        // Emergency cases
        $stmtEm = $pdo->prepare("
            SELECT em.id, em.chief_complaint, em.triage_level, em.status, em.admitted_at, p.first_name, p.last_name, p.patient_no
            FROM emergency_cases em
            LEFT JOIN patients p ON p.id = em.patient_id
            WHERE em.chief_complaint LIKE ? OR em.triage_level LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
            LIMIT ?
        ");
        $stmtEm->bindValue(1, $searchLike, PDO::PARAM_STR);
        $stmtEm->bindValue(2, $searchLike, PDO::PARAM_STR);
        $stmtEm->bindValue(3, $searchLike, PDO::PARAM_STR);
        $stmtEm->bindValue(4, $searchLike, PDO::PARAM_STR);
        $stmtEm->bindValue(5, $limit, PDO::PARAM_INT);
        $stmtEm->execute();
        $emRows = $stmtEm->fetchAll(PDO::FETCH_ASSOC);

        foreach ($emRows as $row) {
            $patientName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $results['emergency'][] = [
                'id' => (int)$row['id'],
                'type' => 'emergency',
                'title' => 'Emergency: ' . ($patientName ?: 'Patient #' . $row['id']),
                'subtitle' => 'Complaint: ' . ($row['chief_complaint'] ?: 'Emergency Care') . ' | Triage: ' . ucfirst($row['triage_level'] ?: 'Standard'),
                'badge' => 'Triage: ' . ucfirst($row['triage_level'] ?: 'Urgent'),
                'badge_class' => 'badge-danger',
                'url' => 'emergency.php',
                'action_label' => 'View Emergency Unit'
            ];
        }
        $counts['emergency'] = count($results['emergency']);
    } catch (Exception $e) {
    }
}

$totalCount = array_sum($counts);

jsonResp([
    'success' => true,
    'query' => $query,
    'category' => $category,
    'total_count' => $totalCount,
    'counts' => $counts,
    'results' => $results
]);
