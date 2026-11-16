<?php
// handlers/consultation/consult.php
// Phase 2: Enhanced consultation system with forms and prescriptions
require_once __DIR__ . '/../../includes/database.php';
function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

/**
 * After inserting an invoice, immediately mark it paid+balance=0 if insurance
 * covers 100% (copayPct = 0).  Also sets status='issued' for partial coverage
 * so the normal payment flow still applies.
 */
function autoCloseIfInsuranceFull(PDO $pdo, int $invoiceId, float $copayPct, bool $isInsurance): void {
    if ($isInsurance && $copayPct == 0) {
        // Insurance covers everything — patient owes nothing
        $pdo->prepare("UPDATE invoices SET balance=0, status='paid' WHERE id=?")
            ->execute([$invoiceId]);
    }
}

// Function to ensure tables exist
if (!function_exists('ensureServiceCatalogItemsTable')) {
    function ensureServiceCatalogItemsTable(PDO $pdo): void {
        static $done = false;
        if ($done) return;
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS service_catalog_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                service_id INT NOT NULL,
                item_id INT NOT NULL,
                default_quantity DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
                store_id INT NULL DEFAULT NULL,
                notes VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_service_item (service_id, item_id),
                INDEX idx_service (service_id),
                INDEX idx_item (item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS lab_item_consumption (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NULL DEFAULT NULL,
                visit_id INT NULL DEFAULT NULL,
                patient_id INT NULL DEFAULT NULL,
                service_id INT NULL DEFAULT NULL,
                item_id INT NOT NULL,
                store_id INT NOT NULL,
                batch_id INT NULL DEFAULT NULL,
                quantity DECIMAL(12,4) NOT NULL,
                unit_cost DECIMAL(12,2) DEFAULT 0.00,
                status ENUM('confirmed','reversed') NOT NULL DEFAULT 'confirmed',
                stock_movement_id INT NULL DEFAULT NULL,
                consumed_by INT NULL DEFAULT NULL,
                consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order (order_id),
                INDEX idx_visit (visit_id),
                INDEX idx_patient (patient_id),
                INDEX idx_service (service_id),
                INDEX idx_item (item_id),
                INDEX idx_store (store_id),
                INDEX idx_consumed_at (consumed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $done = true;
        } catch (Exception $e) {}
    }
}
ensureServiceCatalogItemsTable($pdo);

/**
 * Resolve copayment_percentage for a visit.
 * Returns float: 0 = insurance pays all, 100 = patient pays all.
 */
function resolveCopay(PDO $pdo, int $visitId): array {
    $payType = 'self_pay'; $insId = null; $copayPct = 100.0; $companyId = null; $patientInsId = null;
    $vs = $pdo->prepare("
        SELECT v.payment_type, v.insurance_id,
               COALESCE(pi.id, pi2.id, pi3.id) AS patient_insurance_id,
               COALESCE(ic.id, ic2.id, ic3.id) AS company_id,
               COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage
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
        WHERE v.id = ?
    ");
    $vs->execute([$visitId]);
    $vd = $vs->fetch();
    if ($vd) {
        $payType        = $vd['payment_type'] ?? 'self_pay';
        $insId          = $vd['insurance_id'];
        $patientInsId   = $vd['patient_insurance_id'] ?? null;
        $companyId      = $vd['company_id'] ?? null;
        $copayPct       = ($vd['copayment_percentage'] !== null) ? (float)$vd['copayment_percentage'] : 100.0;
    }
    $isInsurance = ($companyId || $patientInsId) && ($payType === 'insurance' || $payType === 'mixed');
    return compact('payType', 'insId', 'patientInsId', 'copayPct', 'companyId', 'isInsurance');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// For POST requests, also check action from JSON body
if ($method === 'POST' && !$action) {
    $rawBody = file_get_contents('php://input');
    $bodyData = json_decode($rawBody, true) ?? [];
    if (!empty($bodyData['action'])) {
        $action = $bodyData['action'];
    }
    // Store for later use so we don't read php://input twice
    $GLOBALS['_parsed_input'] = $bodyData;
}
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ============================================================
// PHASE 2 ACTIONS: Enhanced Consultation System
// ============================================================

// GET REQUIRED FORMS FOR A DEPARTMENT
// GET /consultation/consult?action=get_forms&department_id=X&visit_id=X
if ($method === 'GET' && $action === 'get_forms') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
    
    if (!$deptId) error('department_id required', 422);
    
    // Get all active form templates for this department
    $stmt = $pdo->prepare("
        SELECT t.* FROM department_form_templates t
        WHERE t.department_id = ? AND t.status = 'active'
        ORDER BY t.sort_order ASC
    ");
    $stmt->execute([$deptId]);
    $templates = $stmt->fetchAll();
    
    // For each template, get fields and check if already submitted
    foreach ($templates as &$t) {
        $fStmt = $pdo->prepare("
            SELECT * FROM department_form_fields
            WHERE template_id = ?
            ORDER BY sort_order ASC
        ");
        $fStmt->execute([$t['id']]);
        $t['fields'] = $fStmt->fetchAll();
        
        // Decode field options
        foreach ($t['fields'] as &$f) {
            if ($f['field_options']) {
                $f['field_options'] = json_decode($f['field_options'], true);
            }
            if ($f['validation_rules']) {
                $f['validation_rules'] = json_decode($f['validation_rules'], true);
            }
        }
        
        // Check if already submitted
        if ($visitId) {
            $sStmt = $pdo->prepare("
                SELECT id, form_data, status FROM visit_department_forms
                WHERE visit_id = ? AND template_id = ?
            ");
            $sStmt->execute([$visitId, $t['id']]);
            $submitted = $sStmt->fetch();
            if ($submitted) {
                $t['is_submitted'] = true;
                $t['submission_id'] = $submitted['id'];
                $t['submission_status'] = $submitted['status'];
                $t['form_data'] = json_decode($submitted['form_data'], true);
            } else {
                $t['is_submitted'] = false;
                $t['submission_id'] = null;
                $t['form_data'] = [];
            }
        }
    }
    
    success($templates);
}

// SAVE CONSULTATION WITH FORMS AND PRESCRIPTIONS (ATOMIC)
// POST /consultation/consult?action=save_with_forms
if ($method === 'POST' && $action === 'save_with_forms') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    foreach (['visit_id', 'patient_id', 'department_id'] as $f) {
        if (empty($input[$f])) error("Field '$f' required", 422);
    }
    
    try {
        $pdo->beginTransaction();
        
        $visitId = (int)$input['visit_id'];
        $patientId = (int)$input['patient_id'];
        $deptId = (int)$input['department_id'];
        
        // 1. Save or update consultation
        $consultFields = [
            'visit_id', 'patient_id', 'department_id', 'doctor_id', 'doctor_name',
            'bp_systolic', 'bp_diastolic', 'pulse', 'temperature', 'weight', 'height', 
            'oxygen_sat', 'respiratory_rate', 'chief_complaint', 'history', 'examination', 
            'differential_diagnosis', 'diagnosis', 'icd_code', 'treatment_plan', 'notes', 'outcome', 'referred_to_dept', 
            'admit_ward', 'follow_up_date', 'status', 'created_by'
        ];
        
        // Check if consultation exists
        $chk = $pdo->prepare("SELECT id FROM consultations WHERE visit_id = ? AND department_id = ?");
        $chk->execute([$visitId, $deptId]);
        $existingId = $chk->fetchColumn();
        
        $consultId = $existingId;
        
        if ($existingId) {
            // Update existing
            $sets = [];
            $params = [];
            foreach ($consultFields as $f) {
                if (array_key_exists($f, $input) && !in_array($f, ['visit_id', 'patient_id', 'department_id', 'created_by'])) {
                    $sets[] = "$f = ?";
                    $params[] = $input[$f] === '' ? null : $input[$f];
                }
            }
            if ($sets) {
                $params[] = $existingId;
                $pdo->prepare("UPDATE consultations SET " . implode(', ', $sets) . " WHERE id = ?")
                    ->execute($params);
            }
        } else {
            // Create new
            $cols = [];
            $vals = [];
            $params = [];
            foreach ($consultFields as $f) {
                if (array_key_exists($f, $input)) {
                    $cols[] = $f;
                    $vals[] = '?';
                    $params[] = $input[$f] === '' ? null : $input[$f];
                }
            }
            $pdo->prepare("INSERT INTO consultations (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")")
                ->execute($params);
            $consultId = (int)$pdo->lastInsertId();
        }
        
        // 2. Submit forms (if provided)
        if (!empty($input['forms'])) {
            foreach ($input['forms'] as $form) {
                $templateId = (int)$form['template_id'];
                $formData = $form['form_data'] ?? [];
                
                // Check if form already submitted
                $existing = $pdo->prepare("SELECT id FROM visit_department_forms WHERE visit_id = ? AND template_id = ?");
                $existing->execute([$visitId, $templateId]);
                $existingFormId = $existing->fetchColumn();
                
                if ($existingFormId) {
                    // Update
                    $pdo->prepare("
                        UPDATE visit_department_forms
                        SET form_data = ?, status = ?, filled_by = ?, updated_at = NOW()
                        WHERE id = ?
                    ")->execute([
                        json_encode($formData),
                        $form['status'] ?? 'completed',
                        $input['created_by'] ?? null,
                        $existingFormId
                    ]);
                } else {
                    // Insert new
                    $pdo->prepare("
                        INSERT INTO visit_department_forms (visit_id, patient_id, template_id, department_id, form_data, status, filled_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $visitId,
                        $patientId,
                        $templateId,
                        $deptId,
                        json_encode($formData),
                        $form['status'] ?? 'completed',
                        $input['created_by'] ?? null
                    ]);
                    
                    $formId = (int)$pdo->lastInsertId();
                    
                    // Link form to consultation
                    $pdo->prepare("
                        INSERT INTO consultation_form_references (consultation_id, form_id)
                        VALUES (?, ?)
                    ")->execute([$consultId, $formId]);
                }
            }
        }
        
        // 3. Save prescription items (if provided)
        $prescriptionBatchId = null;
        if (!empty($input['prescriptions']) && is_array($input['prescriptions'])) {
            // Check if prescription batch exists
            $pBatch = $pdo->prepare("SELECT id FROM prescription_batches WHERE consultation_id = ?");
            $pBatch->execute([$consultId]);
            $existingBatchId = $pBatch->fetchColumn();
            
            if (!$existingBatchId) {
                // Create new prescription batch
                $prescriptionNo = 'RX-' . date('YmdHis') . '-' . $consultId;
                $pdo->prepare("
                    INSERT INTO prescription_batches (consultation_id, visit_id, patient_id, doctor_id, prescription_no, status)
                    VALUES (?, ?, ?, ?, ?, 'draft')
                ")->execute([$consultId, $visitId, $patientId, $input['doctor_id'] ?? null, $prescriptionNo]);
                $prescriptionBatchId = (int)$pdo->lastInsertId();
            } else {
                $prescriptionBatchId = $existingBatchId;
                // Delete existing items for this batch
                $pdo->prepare("DELETE FROM prescription_items WHERE batch_id = ?")->execute([$prescriptionBatchId]);
            }
            
            // Add prescription items
            foreach ($input['prescriptions'] as $presc) {
                $pdo->prepare("
                    INSERT INTO prescription_items (batch_id, item_id, dosage, frequency, duration, quantity, unit, route, instructions, is_controlled, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $prescriptionBatchId,
                    (int)$presc['item_id'],
                    $presc['dosage'] ?? '',
                    $presc['frequency'] ?? '',
                    $presc['duration'] ?? '',
                    $presc['quantity'] ?? 0,
                    $presc['unit'] ?? 'tablet(s)',
                    $presc['route'] ?? 'oral',
                    $presc['instructions'] ?? '',
                    $presc['is_controlled'] ?? 0,
                    'pending'
                ]);
            }
        }
        
        // 4. Update consultation batch references
        if ($prescriptionBatchId) {
            $pdo->prepare("UPDATE consultations SET prescription_batch_id = ? WHERE id = ?")
                ->execute([$prescriptionBatchId, $consultId]);
        }
        
        // 5. Handle consultation completion
        if (($input['status'] ?? '') === 'completed') {
            $outcome = $input['outcome'] ?? null;
            $vStatus = 'in_progress';
            switch ($outcome) {
                case 'admitted':
                case 'referred':
                    $vStatus = 'in_progress';
                    break;
                case 'discharged':
                case 'treated':
                case 'follow_up':
                    $vStatus = 'completed';
                    break;
                default:
                    $vStatus = 'in_progress';
                    break;
            }
            $pdo->prepare("UPDATE visits SET status = ?, consultation_status = 'done' WHERE id = ?")
                ->execute([$vStatus, $visitId]);
            
            $pdo->prepare("UPDATE patient_queue SET status = 'done', done_at = NOW() WHERE visit_id = ? AND department_id = ?")
                ->execute([$visitId, $deptId]);
            
            // Update department history
            $pdo->prepare("UPDATE visit_department_history SET status = 'completed', completed_at = NOW() WHERE visit_id = ? AND department_id = ? AND status = 'in_progress'")
                ->execute([$visitId, $deptId]);
        }
        
        // 6. Fetch complete consultation with all data
        $fetchStmt = $pdo->prepare("
            SELECT c.*, d.name AS dept_name
            FROM consultations c
            LEFT JOIN departments d ON d.id = c.department_id
            WHERE c.id = ?
        ");
        $fetchStmt->execute([$consultId]);
        $result = $fetchStmt->fetch();
        
        // Get prescriptions
        if ($prescriptionBatchId) {
            $pStmt = $pdo->prepare("
                SELECT pi.*, i.name AS item_name
                FROM prescription_items pi
                LEFT JOIN items i ON i.id = pi.item_id
                WHERE pi.batch_id = ?
            ");
            $pStmt->execute([$prescriptionBatchId]);
            $result['prescriptions'] = $pStmt->fetchAll();
        }
        
        // Get orders
        $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id = ? ORDER BY ordered_at DESC");
        $oStmt->execute([$consultId]);
        $result['orders'] = $oStmt->fetchAll();
        
        $pdo->commit();
        
        success($result, $existingId ? 'Consultation updated with forms and prescriptions' : 'Consultation created with forms and prescriptions', 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ============================================================
// LEGACY CONSULTATION OPERATIONS
// ============================================================

// GET — load consultation + orders + patient history
if ($method === 'GET') {

    // Get prescriptions for a visit (emergency drug prescriptions)
    if ($action === 'prescriptions') {
        $vId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        if (!$vId) error('visit_id required', 422);
        $stmt = $pdo->prepare("
            SELECT pp.*, CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, CONCAT(u.first_name, ' ', u.last_name) AS created_by_name
            FROM pharmacy_prescriptions pp
            LEFT JOIN users u ON u.id = pp.doctor_id
            WHERE pp.visit_id = ?
            ORDER BY pp.created_at DESC
        ");
        $stmt->execute([$vId]);
        $prescriptions = $stmt->fetchAll();
        foreach ($prescriptions as &$rx) {
            $iStmt = $pdo->prepare("SELECT pi.*, i.name AS item_name, i.item_code FROM pharmacy_prescription_items pi LEFT JOIN items i ON i.id = pi.item_id WHERE pi.prescription_id = ?");
            $iStmt->execute([$rx['id']]);
            $rx['items'] = $iStmt->fetchAll();
        }
        unset($rx);
        success($prescriptions);
    }

    // Get completed consultations for a department
    if ($action === 'completed_list') {
        $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
        $date = $_GET['date'] ?? '';
        if (!$deptId) error('department_id required', 422);
        $where = ["c.department_id = ?", "c.status = 'completed'"];
        $params = [$deptId];
        if ($date) {
            $where[] = "DATE(c.created_at) = ?";
            $params[] = $date;
        } else {
            // Default to today
            $where[] = "DATE(c.created_at) = CURDATE()";
        }
        $ws = 'WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare("
            SELECT c.id, c.visit_id, c.patient_id, c.diagnosis, c.created_at, c.status,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no,
                   v.visit_no
            FROM consultations c
            JOIN patients p ON p.id = c.patient_id
            JOIN visits v ON v.id = c.visit_id
            $ws
            ORDER BY c.created_at DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // Get referrals made by a specific doctor
    if ($action === 'my_referrals') {
        $doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
        if (!$doctorId) error('doctor_id required', 422);
        $stmt = $pdo->prepare("
            SELECT c.id, c.visit_id, c.patient_id, c.diagnosis, c.created_at, c.outcome,
                   c.referred_to_dept,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no,
                   v.visit_no,
                   d.name AS referred_dept_name,
                   pq.status AS queue_status
            FROM consultations c
            JOIN patients p ON p.id = c.patient_id
            JOIN visits v ON v.id = c.visit_id
            LEFT JOIN departments d ON d.id = c.referred_to_dept
            LEFT JOIN patient_queue pq ON pq.visit_id = c.visit_id AND pq.department_id = c.referred_to_dept
            WHERE c.doctor_id = ? AND c.outcome = 'referred'
            ORDER BY c.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$doctorId]);
        $referrals = $stmt->fetchAll();
        $prevVisitStmt = $pdo->prepare("
            SELECT v_prev.id, v_prev.visit_no, v_prev.visit_date, v_prev.created_at,
                   d.name AS department_name,
                   DATEDIFF(COALESCE(?, CURDATE()), v_prev.visit_date) AS days_ago
            FROM visits v_prev
            LEFT JOIN departments d ON d.id = v_prev.department_id
            WHERE v_prev.patient_id = ?
              AND v_prev.id != ?
              AND (v_prev.visit_date < ? OR (v_prev.visit_date = ? AND v_prev.id < ?))
            ORDER BY v_prev.visit_date DESC, v_prev.id DESC
            LIMIT 1
        ");
        foreach ($referrals as &$ref) {
            $vDate = substr($ref['created_at'] ?? '', 0, 10) ?: date('Y-m-d');
            $prevVisitStmt->execute([
                $vDate,
                $ref['patient_id'],
                $ref['visit_id'],
                $vDate,
                $vDate,
                $ref['visit_id']
            ]);
            $prev = $prevVisitStmt->fetch(PDO::FETCH_ASSOC);
            if ($prev) {
                $prev['days_ago'] = (int)$prev['days_ago'];
                $ref['previous_visit'] = $prev;
            } else {
                $ref['previous_visit'] = null;
            }
        }
        unset($ref);
        success($referrals);
    }

    // Get consultation record for a visit
    if ($action === 'consultation') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        if (!$visitId) error('visit_id required', 422);
        $stmt = $pdo->prepare("SELECT c.*, d.name AS department_name FROM consultations c LEFT JOIN departments d ON d.id=c.department_id WHERE c.visit_id = ? ORDER BY c.id DESC");
        $stmt->execute([$visitId]);
        $results = $stmt->fetchAll();
        success($results);
    }

    // Get lab results for a visit (from medical_orders)
    if ($action === 'lab_results') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        if (!$visitId) error('visit_id required', 422);
        $stmt = $pdo->prepare("
            SELECT mo.id, mo.description AS test_name, mo.order_type, mo.status, mo.result, mo.completed_at, mo.ordered_at,
                   TRIM(CONCAT(COALESCE(uo.first_name,''),' ',COALESCE(uo.last_name,''))) AS ordered_by_name,
                   (SELECT TRIM(CONCAT(COALESCE(u2.first_name,''),' ',COALESCE(u2.last_name,''))) 
                    FROM edit_audit_log eal 
                    JOIN users u2 ON u2.id = eal.edited_by 
                    WHERE eal.entity_type = 'lab_result' AND eal.entity_id = mo.id 
                    ORDER BY eal.id DESC LIMIT 1) AS completed_by_name,
                   (SELECT TRIM(CONCAT(COALESCE(u2.first_name,''),' ',COALESCE(u2.last_name,''))) 
                    FROM edit_audit_log eal 
                    JOIN users u2 ON u2.id = eal.edited_by 
                    WHERE eal.entity_type = 'lab_result' AND eal.entity_id = mo.id 
                    ORDER BY eal.id DESC LIMIT 1) AS performed_by_name
            FROM medical_orders mo
            LEFT JOIN users uo ON uo.id = mo.ordered_by
            WHERE mo.visit_id = ? AND mo.order_type IN ('lab','radiology')
            ORDER BY mo.id ASC
        ");
        $stmt->execute([$visitId]);
        success($stmt->fetchAll());
    }

    // Get medical orders for a visit or single order
    if ($action === 'orders') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        if ($orderId) {
            $stmt = $pdo->prepare("SELECT mo.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM medical_orders mo LEFT JOIN patients p ON p.id = mo.patient_id WHERE mo.id = ?");
            $stmt->execute([$orderId]);
            success($stmt->fetchAll());
        }
        if (!$visitId) error('visit_id required', 422);
        $stmt = $pdo->prepare("SELECT mo.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM medical_orders mo LEFT JOIN patients p ON p.id = mo.patient_id WHERE mo.visit_id = ? ORDER BY mo.id DESC");
        $stmt->execute([$visitId]);
        success($stmt->fetchAll());
    }

    // Get all lab/radiology orders for lab.php
    if ($action === 'lab_orders') {
        $status = $_GET['status'] ?? '';
        $type = $_GET['type'] ?? 'lab';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';
        $where = ["mo.order_type IN ('lab','radiology')"];
        $params = [];
        if ($status) { $where[] = "mo.status = ?"; $params[] = $status; }
        if ($type && $type !== 'all') { $where[] = "mo.order_type = ?"; $params[] = $type; }
        if ($dateFrom) { $where[] = "v.visit_date >= ?"; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = "v.visit_date <= ?"; $params[] = $dateTo; }
        $ws = 'WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare("
            SELECT mo.*, 
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                   p.patient_no, p.gender, p.date_of_birth,
                   ic.name AS insurance_company,
                   TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS ordered_by_name,
                   (SELECT TRIM(CONCAT(COALESCE(u2.first_name,''),' ',COALESCE(u2.last_name,''))) 
                    FROM edit_audit_log eal 
                    JOIN users u2 ON u2.id = eal.edited_by 
                    WHERE eal.entity_type = 'lab_result' AND eal.entity_id = mo.id 
                    ORDER BY eal.id DESC LIMIT 1) AS completed_by_name,
                   (SELECT TRIM(CONCAT(COALESCE(u2.first_name,''),' ',COALESCE(u2.last_name,''))) 
                    FROM edit_audit_log eal 
                    JOIN users u2 ON u2.id = eal.edited_by 
                    WHERE eal.entity_type = 'lab_result' AND eal.entity_id = mo.id 
                    ORDER BY eal.id DESC LIMIT 1) AS performed_by_name,
                   v.status AS visit_status,
                   v.visit_date
            FROM medical_orders mo
            LEFT JOIN patients p ON p.id = mo.patient_id
            LEFT JOIN visits v ON v.id = mo.visit_id
            LEFT JOIN patient_insurance pi ON pi.patient_id = p.id AND pi.is_primary = 1
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN users u ON u.id = mo.ordered_by
            $ws
            ORDER BY v.visit_date DESC, mo.ordered_at DESC
            LIMIT 500
        ");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // ===== LAB CONSUMPTION ENDPOINTS =====
    // Get lab orders with mapped consumables and consumption status
    if ($action === 'lab_consumption_orders') {
        $statusFilter = $_GET['consumption_status'] ?? 'all'; // all, pending, consumed
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';
        $search   = trim($_GET['search'] ?? '');
        $storeId  = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 1040; // default to clinic lab store

        $where = ["mo.order_type IN ('lab','radiology')"];
        $params = [];

        if ($dateFrom) { $where[] = "(v.visit_date >= ? OR DATE(mo.ordered_at) >= ?)"; $params[] = $dateFrom; $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = "(v.visit_date <= ? OR DATE(mo.ordered_at) <= ?)"; $params[] = $dateTo;   $params[] = $dateTo; }
        if ($search) {
            $like = "%$search%";
            $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_no LIKE ? OR mo.description LIKE ? OR v.visit_no LIKE ? OR CAST(mo.id AS CHAR) LIKE ?)";
            $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
        }

        $ws = 'WHERE ' . implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT mo.*,
                   CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                   p.patient_no, p.gender, p.date_of_birth,
                   ic.name AS insurance_company,
                   v.visit_no, v.visit_date, v.status AS visit_status,
                   TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS ordered_by_name
            FROM medical_orders mo
            LEFT JOIN patients p ON p.id = mo.patient_id
            LEFT JOIN visits v ON v.id = mo.visit_id
            LEFT JOIN patient_insurance pi ON pi.patient_id = p.id AND pi.is_primary = 1
            LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
            LEFT JOIN users u ON u.id = mo.ordered_by
            $ws
            ORDER BY mo.ordered_at DESC, mo.id DESC
            LIMIT 300
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Load consumption records and mapped consumables for these orders
        $orderIds = array_map(function($o) { return (int)$o['id']; }, $orders);
        $consumptionsMap = [];
        if (!empty($orderIds)) {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $cStmt = $pdo->prepare("
                SELECT lic.*, i.name AS item_name, i.item_code, iu.abbreviation AS uom_abbr,
                       s.name AS store_name,
                       TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS consumed_by_name
                FROM lab_item_consumption lic
                JOIN items i ON i.id = lic.item_id
                LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                LEFT JOIN stores s ON s.id = lic.store_id
                LEFT JOIN users u ON u.id = lic.consumed_by
                WHERE lic.order_id IN ($placeholders) AND lic.status = 'confirmed'
                ORDER BY lic.consumed_at ASC
            ");
            $cStmt->execute($orderIds);
            while ($row = $cStmt->fetch()) {
                $consumptionsMap[$row['order_id']][] = $row;
            }
        }

        // Cache service catalog item mappings
        $serviceMappings = [];
        $scAll = $pdo->query("
            SELECT sci.service_id, sci.item_id, sci.default_quantity, sci.notes,
                   sc.procedure_name, sc.speciality,
                   i.name AS item_name, i.item_code, ic.name AS category_name, iu.abbreviation AS uom_abbr,
                   COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND is_active = 1 AND quantity > 0), 0) AS total_stock,
                   COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND store_id = $storeId AND is_active = 1 AND quantity > 0), 0) AS store_stock
            FROM service_catalog_items sci
            JOIN service_catalog sc ON sc.id = sci.service_id
            JOIN items i ON i.id = sci.item_id
            LEFT JOIN item_categories ic ON ic.id = i.category_id
            LEFT JOIN item_uoms iu ON iu.id = i.uom_id
            ORDER BY sc.id, i.name
        ")->fetchAll();

        foreach ($scAll as $row) {
            $key = strtolower(trim($row['procedure_name']));
            $serviceMappings[$key][] = $row;
            $serviceMappings['sid_' . $row['service_id']][] = $row;
        }

        $resultOrders = [];
        foreach ($orders as $ord) {
            $consumedItems = $consumptionsMap[$ord['id']] ?? [];
            $isConsumed = !empty($consumedItems);

            // Find linked consumables by service_id or test description match
            $linkedConsumables = [];
            if (!empty($ord['service_id']) && !empty($serviceMappings['sid_' . $ord['service_id']])) {
                $linkedConsumables = $serviceMappings['sid_' . $ord['service_id']];
            } else {
                $testKey = strtolower(trim($ord['description'] ?? ''));
                $linkedConsumables = $serviceMappings[$testKey] ?? [];

                // If no exact match, try partial match
                if (empty($linkedConsumables)) {
                    foreach ($serviceMappings as $nameKey => $itemsList) {
                        if (strpos($nameKey, 'sid_') === 0) continue;
                        if (strpos($nameKey, $testKey) !== false || strpos($testKey, $nameKey) !== false) {
                            $linkedConsumables = $itemsList;
                            break;
                        }
                    }
                }
            }

            $ord['is_consumed'] = $isConsumed ? 1 : 0;
            $ord['consumed_items'] = $consumedItems;
            $ord['consumed_items_count'] = count($consumedItems);
            $ord['consumed_at'] = $isConsumed ? $consumedItems[0]['consumed_at'] : null;
            $ord['consumed_by_name'] = $isConsumed ? $consumedItems[0]['consumed_by_name'] : null;
            $ord['linked_consumables'] = $linkedConsumables;
            $ord['linked_consumables_count'] = count($linkedConsumables);

            // Filter by consumption status
            if ($statusFilter === 'pending' && $isConsumed) continue;
            if ($statusFilter === 'consumed' && !$isConsumed) continue;

            $resultOrders[] = $ord;
        }

        success($resultOrders);
    }

    // Get consumables details for a specific lab order or test
    if ($action === 'lab_order_consumables') {
        $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
        $serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 1040;
        $testName = trim($_GET['test_name'] ?? '');

        $order = null;
        if ($orderId > 0) {
            $oStmt = $pdo->prepare("
                SELECT mo.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no,
                       v.visit_no, v.visit_date
                FROM medical_orders mo
                LEFT JOIN patients p ON p.id = mo.patient_id
                LEFT JOIN visits v ON v.id = mo.visit_id
                WHERE mo.id = ?
            ");
            $oStmt->execute([$orderId]);
            $order = $oStmt->fetch();
            if ($order && !$testName) $testName = $order['description'];
        }

        // Find service catalog entry if not explicitly provided
        $service = null;
        if ($serviceId > 0) {
            $sStmt = $pdo->prepare("SELECT * FROM service_catalog WHERE id = ?");
            $sStmt->execute([$serviceId]);
            $service = $sStmt->fetch();
        } elseif ($testName) {
            $sStmt = $pdo->prepare("SELECT * FROM service_catalog WHERE procedure_name = ? OR procedure_name LIKE ? ORDER BY LENGTH(procedure_name) ASC LIMIT 1");
            $sStmt->execute([$testName, "%$testName%"]);
            $service = $sStmt->fetch();
            if ($service) $serviceId = (int)$service['id'];
        }

        // Get linked items from service_catalog_items
        $linkedItems = [];
        if ($serviceId > 0) {
            $liStmt = $pdo->prepare("
                SELECT sci.*, 
                       i.name AS item_name, i.item_code, i.generic_name, i.selling_price, i.unit_price,
                       ic.name AS category_name, iu.name AS uom_name, iu.abbreviation AS uom_abbr,
                       COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND is_active = 1 AND quantity > 0), 0) AS total_stock,
                       COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND store_id = ? AND is_active = 1 AND quantity > 0), 0) AS store_stock
                FROM service_catalog_items sci
                JOIN items i ON i.id = sci.item_id
                LEFT JOIN item_categories ic ON ic.id = i.category_id
                LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                WHERE sci.service_id = ?
                ORDER BY i.name ASC
            ");
            $liStmt->execute([$storeId, $serviceId]);
            $linkedItems = $liStmt->fetchAll();
        }

        // Check if already consumed for this order
        $consumedRecords = [];
        if ($orderId > 0) {
            $crStmt = $pdo->prepare("
                SELECT lic.*, i.name AS item_name, i.item_code, iu.abbreviation AS uom_abbr,
                       s.name AS store_name,
                       TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS consumed_by_name
                FROM lab_item_consumption lic
                JOIN items i ON i.id = lic.item_id
                LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                LEFT JOIN stores s ON s.id = lic.store_id
                LEFT JOIN users u ON u.id = lic.consumed_by
                WHERE lic.order_id = ? AND lic.status = 'confirmed'
                ORDER BY lic.consumed_at ASC
            ");
            $crStmt->execute([$orderId]);
            $consumedRecords = $crStmt->fetchAll();
        }

        success([
            'order' => $order,
            'service' => $service,
            'linked_items' => $linkedItems,
            'is_consumed' => !empty($consumedRecords),
            'consumed_records' => $consumedRecords,
            'store_id' => $storeId
        ]);
    }

    // Get consumption history log
    if ($action === 'lab_consumption_history') {
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $stmt = $pdo->query("
            SELECT lic.*, 
                   i.name AS item_name, i.item_code, iu.abbreviation AS uom_abbr,
                   s.name AS store_name, s.code AS store_code,
                   mo.description AS test_name,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_no,
                   v.visit_no,
                   TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) AS consumed_by_name
            FROM lab_item_consumption lic
            JOIN items i ON i.id = lic.item_id
            LEFT JOIN item_uoms iu ON iu.id = i.uom_id
            LEFT JOIN stores s ON s.id = lic.store_id
            LEFT JOIN medical_orders mo ON mo.id = lic.order_id
            LEFT JOIN patients p ON p.id = lic.patient_id
            LEFT JOIN visits v ON v.id = lic.visit_id
            LEFT JOIN users u ON u.id = lic.consumed_by
            ORDER BY lic.consumed_at DESC, lic.id DESC
            LIMIT $limit
        ");
        success($stmt->fetchAll());
    }

    // Get stores list with lab stores prioritized
    if ($action === 'lab_stores') {
        $stmt = $pdo->query("
            SELECT s.*, 
                   (SELECT COUNT(*) FROM stock_batches sb WHERE sb.store_id = s.id AND sb.is_active = 1 AND sb.quantity > 0) AS active_batches_count
            FROM stores s 
            WHERE s.is_active = 1 
            ORDER BY CASE WHEN s.id = 1040 THEN 0 WHEN s.type = 'laboratory' OR s.code LIKE '%LAB%' OR s.name LIKE '%lab%' THEN 1 ELSE 2 END, s.name ASC
        ");
        success($stmt->fetchAll());
    }

    // --- EMERGENCY CASES ---
    if ($action === 'emergency') {
        $status = $_GET['status'] ?? '';
        $subAction = $_GET['sub_action'] ?? '';
        
        // Get treatments for a case
        if ($subAction === 'treatments') {
            $caseId = (int)($_GET['case_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM emergency_treatments WHERE case_id=? ORDER BY administered_at DESC");
            $stmt->execute([$caseId]);
            success($stmt->fetchAll());
        }
        
        $where = []; $params = [];
        if ($status === 'active') { $where[] = "e.status IN ('triage','treatment','observation')"; }
        else if ($status) { $where[] = "e.status=?"; $params[] = $status; }
        $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $stmt = $pdo->prepare("SELECT e.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no FROM emergency_cases e LEFT JOIN patients p ON p.id=e.patient_id $ws ORDER BY FIELD(e.triage_level,'red','orange','yellow','green','blue'), e.created_at DESC LIMIT 50");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // --- SURGERIES ---
    if ($action === 'surgery') {
        $status = $_GET['status'] ?? '';
        $where = []; $params = [];
        if ($status) { $where[] = "s.status=?"; $params[] = $status; }
        $ws = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $stmt = $pdo->prepare("SELECT s.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no FROM surgeries s LEFT JOIN patients p ON p.id=s.patient_id $ws ORDER BY s.scheduled_date DESC, s.scheduled_time DESC LIMIT 50");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // --- NURSING RECORDS ---
    if ($action === 'nursing_records') {
        $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        $where = [];
        $params = [];
        if ($visitId) { $where[] = "nr.visit_id = ?"; $params[] = $visitId; }
        if ($dateFrom) { $where[] = "DATE(nr.created_at) >= ?"; $params[] = $dateFrom; }
        if ($dateTo) { $where[] = "DATE(nr.created_at) <= ?"; $params[] = $dateTo; }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $pdo->prepare("SELECT nr.*, 
            CONCAT(p.first_name,' ',p.last_name) AS patient_name, 
            p.patient_no,
            v.visit_no,
            CONCAT(u.first_name,' ',u.last_name) AS nurse_name,
            CONCAT(u.first_name,' ',u.last_name) AS created_by_name
            FROM nursing_records nr 
            LEFT JOIN patients p ON p.id=nr.patient_id 
            LEFT JOIN visits v ON v.id=nr.visit_id
            LEFT JOIN users u ON u.id=nr.administered_by
            $whereSQL
            ORDER BY nr.created_at DESC LIMIT ?");
        $params[] = $limit;
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // Patient full history (past consultations)
    if ($action === 'history') {
        $pid = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
        if (!$pid) error('patient_id required', 422);
        $stmt = $pdo->prepare("
            SELECT c.id, c.created_at, c.diagnosis, c.treatment_plan, c.notes, c.outcome, c.status,
                   d.name AS department_name, c.doctor_name,
                   v.visit_no, v.visit_date
            FROM consultations c
            JOIN visits v ON v.id=c.visit_id
            LEFT JOIN departments d ON d.id=c.department_id
            WHERE c.patient_id=? AND c.status='completed'
            ORDER BY c.created_at DESC LIMIT 20
        ");
        $stmt->execute([$pid]);
        success($stmt->fetchAll());
    }

    // Single consultation with orders
    if ($id) {
        $stmt = $pdo->prepare("SELECT c.*, d.name AS dept_name FROM consultations c LEFT JOIN departments d ON d.id=c.department_id WHERE c.id=?");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) error('Consultation not found', 404);
        $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id=? ORDER BY ordered_at DESC");
        $oStmt->execute([$id]);
        $c['orders'] = $oStmt->fetchAll();
        success($c);
    }

    // By visit_id
    $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
    $deptId  = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    if ($visitId) {
        $where = 'WHERE c.visit_id=?'; $params = [$visitId];
        if ($deptId) { $where .= ' AND c.department_id=?'; $params[] = $deptId; }
        $stmt = $pdo->prepare("SELECT c.*, d.name AS dept_name FROM consultations c LEFT JOIN departments d ON d.id=c.department_id $where ORDER BY c.created_at DESC LIMIT 1");
        $stmt->execute($params);
        $c = $stmt->fetch();
        if ($c) {
            $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id=? ORDER BY ordered_at");
            $oStmt->execute([$c['id']]);
            $c['orders'] = $oStmt->fetchAll();
        }
        success($c ?: null);
    }

    error('Missing parameters', 422);
}

// POST — save lab result
// POST — emergency case or treatment
if ($method === 'POST' && $action === 'emergency') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $subAction = $_GET['sub_action'] ?? '';
    
    // Add treatment to a case
    if ($subAction === 'add_treatment') {
        $caseId = (int)($input['case_id'] ?? 0);
        if (!$caseId) error('case_id required', 422);
        $stmt = $pdo->prepare("INSERT INTO emergency_treatments (case_id, treatment_type, item_id, service_id, description, dosage, quantity, unit_price, notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $caseId, $input['treatment_type']??'other',
            !empty($input['item_id'])?(int)$input['item_id']:null,
            !empty($input['service_id'])?(int)$input['service_id']:null,
            $input['description']??'Treatment', $input['dosage']??'',
            (float)($input['quantity']??1), (float)($input['unit_price']??0), $input['notes']??''
        ]);
        // Update case status to treatment
        $pdo->prepare("UPDATE emergency_cases SET status='treatment' WHERE id=? AND status='triage'")->execute([$caseId]);
        success(['id'=>(int)$pdo->lastInsertId()], 'Treatment added');
    }
    
    // New emergency case
    $stmt = $pdo->prepare("INSERT INTO emergency_cases (patient_id, triage_level, chief_complaint, arrival_mode, consciousness, vitals_bp, vitals_pulse, vitals_temp, vitals_spo2, vitals_rr) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        !empty($input['patient_id'])?(int)$input['patient_id']:null,
        $input['triage_level']??'yellow', $input['chief_complaint']??'',
        $input['arrival_mode']??'walk_in', $input['consciousness']??'alert',
        $input['vitals_bp']??null, $input['vitals_pulse']??null,
        $input['vitals_temp']??null, $input['vitals_spo2']??null, $input['vitals_rr']??null
    ]);
    success(['id'=>(int)$pdo->lastInsertId()], 'Emergency case registered');
}

// PUT — update emergency case (discharge)
if ($method === 'PUT' && $action === 'emergency') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) error('Case ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $status = $input['status'] ?? 'discharged';
    
    $pdo->prepare("UPDATE emergency_cases SET status=?, discharged_at=NOW() WHERE id=?")->execute([$status, $id]);
    
    // Create invoice for all treatments
    if (($input['create_invoice']??false)) {
        $treatments = $pdo->prepare("SELECT * FROM emergency_treatments WHERE case_id=? AND unit_price > 0"); $treatments->execute([$id]);
        $items = $treatments->fetchAll();
        if (!empty($items)) {
            $case = $pdo->prepare("SELECT * FROM emergency_cases WHERE id=?"); $case->execute([$id]); $caseData = $case->fetch();
            $total = 0;
            foreach ($items as $it) { $total += (float)$it['quantity'] * (float)$it['unit_price']; }

            $lastInv = $pdo->query("SELECT invoice_no FROM invoices ORDER BY id DESC LIMIT 1")->fetchColumn();
            $seq = 1; if ($lastInv && preg_match('/(\d+)$/', $lastInv, $m)) $seq = (int)$m[1] + 1;
            $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            // Resolve insurance for this emergency case
            $emVisitId = $caseData['visit_id'] ?? null;
            $emCopay   = resolveCopay($pdo, (int)$emVisitId);
            $emIsIns   = $emCopay['isInsurance'];
            $emCopayPct= $emCopay['copayPct'];
            $emCompany = $emCopay['companyId'];
            $patientBalance = $emIsIns ? round($total * $emCopayPct / 100, 2) : $total;

            $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_at) VALUES (?,?,?,?,?,?,?,?,'issued',?,NOW())")
                ->execute([$invoiceNo, $emVisitId, $caseData['patient_id'], $emIsIns ? 'insurance' : 'self_pay', $emIsIns ? $emCompany : null, $total, $total, $patientBalance, 'Emergency treatments']);
            $invId = (int)$pdo->lastInsertId();

            foreach ($items as $it) {
                $lineTotal = (float)$it['quantity'] * (float)$it['unit_price'];
                $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, total) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$invId, $it['service_id']??$it['item_id'], $it['description'], 'emergency', (float)$it['quantity'], (float)$it['unit_price'], $lineTotal]);
            }

            // Auto-close if insurance covers 100%
            autoCloseIfInsuranceFull($pdo, $invId, $emCopayPct, $emIsIns);
        }
    }
    success(null, 'Case updated');
}

// POST — schedule surgery
if ($method === 'POST' && $action === 'surgery') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['patient_id']) || empty($input['procedure_name'])) error('patient_id and procedure_name required', 422);
    $stmt = $pdo->prepare("INSERT INTO surgeries (patient_id, visit_id, procedure_name, service_id, total_cost, surgeon_name, anesthetist_name, theatre_room, scheduled_date, scheduled_time, priority, pre_op_notes, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'scheduled')");
    $stmt->execute([
        (int)$input['patient_id'], $input['visit_id']??null, $input['procedure_name'],
        $input['service_id']??null, (float)($input['total_cost']??0),
        $input['surgeon_name']??null, $input['anesthetist_name']??null, $input['theatre_room']??null,
        $input['scheduled_date']??date('Y-m-d'), $input['scheduled_time']??null,
        $input['priority']??'elective', $input['pre_op_notes']??null
    ]);
    $surgeryId = (int)$pdo->lastInsertId();
    
    // Save additional items if any
    $items = $input['items'] ?? [];
    if (!empty($items)) {
        $itemTotal = 0;
        foreach ($items as $it) {
            $itemTotal += (float)($it['unit_price']??0) * (int)($it['quantity']??1);
        }
        // Store items as JSON in pre_op_notes (append)
        $itemsJson = json_encode($items);
        $pdo->prepare("UPDATE surgeries SET pre_op_notes = CONCAT(COALESCE(pre_op_notes,''), '\n[ITEMS]', ?) , total_cost = total_cost + ? WHERE id = ?")->execute([$itemsJson, $itemTotal, $surgeryId]);
    }
    
    success(['id'=>$surgeryId], 'Surgery scheduled');
}

// PUT — update surgery status
if ($method === 'PUT' && $action === 'surgery') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) error('Surgery ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $fields = []; $params = [];
    $allowed = ['status','actual_start','actual_end','post_op_notes','complications','duration_minutes'];
    foreach ($allowed as $f) { if (array_key_exists($f,$input)) { $fields[]="$f=?"; $params[]=$input[$f]; } }
    if ($fields) { $params[]=$id; $pdo->prepare("UPDATE surgeries SET ".implode(',',$fields)." WHERE id=?")->execute($params); }
    
    // Create invoice on completion (procedure + items)
    if (($input['status']??'') === 'completed' && ($input['create_invoice']??false)) {
        $sg = $pdo->prepare("SELECT * FROM surgeries WHERE id=?"); $sg->execute([$id]); $surgery = $sg->fetch();
        if ($surgery && $surgery['total_cost'] > 0) {
            $lastInv = $pdo->query("SELECT invoice_no FROM invoices ORDER BY id DESC LIMIT 1")->fetchColumn();
            $seq = 1; if ($lastInv && preg_match('/(\d+)$/', $lastInv, $m)) $seq = (int)$m[1] + 1;
            $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            // Resolve insurance for this surgery's visit
            $sgVisitId = $surgery['visit_id'] ?? null;
            $sgCopay   = $sgVisitId ? resolveCopay($pdo, (int)$sgVisitId) : ['isInsurance'=>false,'copayPct'=>100,'companyId'=>null,'payType'=>'self_pay'];
            $sgIsIns   = $sgCopay['isInsurance'];
            $sgCopayPct= $sgCopay['copayPct'];
            $sgCompany = $sgCopay['companyId'];
            $sgTotal   = (float)$surgery['total_cost'];
            $patientBalance = $sgIsIns ? round($sgTotal * $sgCopayPct / 100, 2) : $sgTotal;

            $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_at) VALUES (?,?,?,?,?,?,?,?,'issued',?,NOW())")
                ->execute([$invoiceNo, $sgVisitId, $surgery['patient_id'], $sgIsIns ? 'insurance' : 'self_pay', $sgIsIns ? $sgCompany : null, $sgTotal, $sgTotal, $patientBalance, 'Surgery: '.$surgery['procedure_name']]);
            $invId = (int)$pdo->lastInsertId();

            // Add procedure as first item
            $procCost = $sgTotal;
            $itemsData = [];
            if (strpos($surgery['pre_op_notes']??'', '[ITEMS]') !== false) {
                $parts = explode('[ITEMS]', $surgery['pre_op_notes']);
                if (isset($parts[1])) { try { $itemsData = json_decode($parts[1], true) ?? []; } catch(Exception $e){} }
                $itemsTotal = 0;
                foreach ($itemsData as $it) { $itemsTotal += (float)($it['unit_price']??0) * (int)($it['quantity']??1); }
                $procCost = $sgTotal - $itemsTotal;
            }

            if ($procCost > 0) {
                $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, total) VALUES (?,?,?,?,1,?,?)")
                    ->execute([$invId, $surgery['service_id'], $surgery['procedure_name'], 'procedure', $procCost, $procCost]);
            }

            foreach ($itemsData as $it) {
                $qty = (int)($it['quantity']??1);
                $price = (float)($it['unit_price']??0);
                $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, total) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$invId, $it['item_id']??null, $it['name']??'Item', 'pharmacy', $qty, $price, $qty*$price]);
            }

            $pdo->prepare("UPDATE surgeries SET invoice_id=? WHERE id=?")->execute([$invId, $id]);

            // Auto-close if insurance covers 100%
            autoCloseIfInsuranceFull($pdo, $invId, $sgCopayPct, $sgIsIns);
        }
    }
    success(null, 'Surgery updated');
}

// POST — save nursing record with invoice
if ($method === 'POST' && $action === 'nursing_record') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $patientId = (int)($input['patient_id'] ?? 0);
    $visitId = (int)($input['visit_id'] ?? 0);
    $assignId = (int)($input['bed_assignment_id'] ?? 0);
    $recordType = $input['record_type'] ?? 'note';
    $itemId = !empty($input['item_id']) ? (int)$input['item_id'] : null;
    $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
    $description = trim($input['description'] ?? '');
    $dosage = $input['dosage'] ?? '';
    $qty = (float)($input['quantity'] ?? 1);
    $unitPrice = (float)($input['unit_price'] ?? 0);
    $notes = $input['notes'] ?? '';
    
    if (!$patientId) error('patient_id required', 422);
    if (!$description) error('Description required', 422);
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO nursing_records (patient_id, visit_id, bed_assignment_id, record_type, item_id, service_id, description, dosage, quantity, unit_price, notes, administered_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $adminBy = $_SESSION['user_id'] ?? ($input['administered_by'] ?? null);
        $stmt->execute([$patientId, $visitId, $assignId, $recordType, $itemId, $serviceId, $description, $dosage, $qty, $unitPrice, $notes, $adminBy]);
        $recordId = (int)$pdo->lastInsertId();
        
        $invoiceNo = null;
        $total = $qty * $unitPrice;
        if ($total > 0) {
            $lastInv = $pdo->query("SELECT invoice_no FROM invoices ORDER BY id DESC LIMIT 1")->fetchColumn();
            $seq = 1;
            if ($lastInv && preg_match('/(\d+)$/', $lastInv, $m)) $seq = (int)$m[1] + 1;
            $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
            
            $payType = 'self_pay'; $insId = null; $copayPct = 100; $companyId = null;
            if ($visitId) {
                $vs = $pdo->prepare("
                    SELECT v.payment_type, v.insurance_id,
                           COALESCE(ic.id, ic2.id, ic3.id) AS company_id,
                           COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, 100) AS copayment_percentage
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
                    WHERE v.id=?
                ");
                $vs->execute([$visitId]);
                $vd = $vs->fetch();
                if ($vd) {
                    $payType   = $vd['payment_type'] ?? 'self_pay';
                    $insId     = $vd['insurance_id'];
                    $companyId = $vd['company_id'] ?? null;
                    $copayPct  = ($vd['copayment_percentage'] !== null) ? (float)$vd['copayment_percentage'] : 100.0;
                }
            }
            
            $isInsurance = $companyId && ($payType === 'insurance' || $payType === 'mixed');
            
            // Look up insurance-agreed price if applicable
            if ($isInsurance && ($serviceId || $itemId)) {
                $lookupId = $serviceId ?: $itemId;
                $insPriceStmt = $pdo->prepare("SELECT price FROM insurance_procedure_prices WHERE insurance_id = ? AND service_id = ?");
                $insPriceStmt->execute([$companyId, $lookupId]);
                $insPrice = $insPriceStmt->fetchColumn();
                if ($insPrice !== false && (float)$insPrice > 0) {
                    $unitPrice = (float)$insPrice;
                    $total = $qty * $unitPrice;
                }
            }
            
            // Calculate patient balance based on copayment
            $patientBalance = $total;
            if ($isInsurance && $copayPct < 100) {
                $patientBalance = round($total * $copayPct / 100, 2);
            }
            
            $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_at) VALUES (?,?,?,?,?,?,?,?,'issued',?,NOW())")
                ->execute([$invoiceNo, $visitId, $patientId, $isInsurance ? 'insurance' : 'self_pay', $isInsurance ? $companyId : null, $total, $total, $patientBalance, "Nursing: $description"]);
            $invoiceId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, total) VALUES (?,?,?,?,?,?,?)")
                ->execute([$invoiceId, $serviceId ?? $itemId, $description, $recordType==='service'?'nursing':'pharmacy', $qty, $unitPrice, $total]);
            $pdo->prepare("UPDATE nursing_records SET invoice_id=? WHERE id=?")->execute([$invoiceId, $recordId]);
            // Auto-close if insurance covers 100%
            autoCloseIfInsuranceFull($pdo, $invoiceId, $copayPct, $isInsurance);
        }
        $pdo->commit();
        success(['id' => $recordId, 'invoice_no' => $invoiceNo], 'Nursing record saved');
    } catch (Exception $e) { $pdo->rollBack(); error('Failed: ' . $e->getMessage()); }
}

if ($method === 'POST' && $action === 'lab_result') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $orderId = (int)($input['order_id'] ?? 0);
    $result = trim($input['result'] ?? '');
    $status = $input['status'] ?? 'completed';
    $performedBy = !empty($input['performed_by']) ? (int)$input['performed_by'] : ($_SESSION['user_id'] ?? null);
    
    if (!$orderId) error('order_id required', 422);
    if (!$result) error('Result text required', 422);

    $oldOrderStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE id = ?");
    $oldOrderStmt->execute([$orderId]);
    $order = $oldOrderStmt->fetch();

    $stmt = $pdo->prepare("UPDATE medical_orders SET result = ?, status = ?, completed_at = NOW() WHERE id = ?");
    $stmt->execute([$result, $status, $orderId]);
    
    // Audit log
    try {
        $logStmt = $pdo->prepare("INSERT INTO edit_audit_log (entity_type, entity_id, entity_name, data_before, data_after, changed_fields, edited_by, reason) VALUES ('lab_result', ?, ?, ?, ?, ?, ?, ?)");
        $logStmt->execute([
            $orderId,
            $order['description'] ?? ('Lab Order #' . $orderId),
            json_encode(['status' => $order['status'] ?? '', 'result' => $order['result'] ?? '']),
            json_encode(['status' => $status, 'result' => $result]),
            'Result recorded / updated',
            $performedBy,
            $input['reason'] ?? 'Lab result recorded'
        ]);
    } catch (Exception $e) {}
        
        if ($order && $order['visit_id']) {
            $visitId = (int)$order['visit_id'];
            $patientId = (int)$order['patient_id'];
            
            // Count total lab/radiology orders for this visit
            $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM medical_orders WHERE visit_id = ? AND order_type IN ('lab','radiology')");
            $totalStmt->execute([$visitId]);
            $totalOrders = (int)$totalStmt->fetchColumn();
            
            // Count completed lab/radiology orders for this visit
            $completedStmt = $pdo->prepare("SELECT COUNT(*) FROM medical_orders WHERE visit_id = ? AND order_type IN ('lab','radiology') AND status = 'completed'");
            $completedStmt->execute([$visitId]);
            $completedOrders = (int)$completedStmt->fetchColumn();
            
            // If all orders are completed, create a notification
            if ($totalOrders > 0 && $completedOrders >= $totalOrders) {
                // Check if a notification already exists for this visit (avoid duplicates)
                $existsStmt = $pdo->prepare("SELECT id FROM notifications WHERE visit_id = ? AND type = 'lab_ready' AND is_read = 0");
                $existsStmt->execute([$visitId]);
                
                if (!$existsStmt->fetch()) {
                    // Get patient name
                    $ptStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM patients WHERE id = ?");
                    $ptStmt->execute([$patientId]);
                    $ptName = $ptStmt->fetchColumn() ?: 'Patient';
                    
                    $notifTitle = "Lab Results Ready";
                    $notifMessage = "All $totalOrders lab test(s) for $ptName are completed and ready for review.";
                    
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role) VALUES ('lab_ready', ?, ?, ?, ?, 'nurse')");
                    $notifStmt->execute([$notifTitle, $notifMessage, $visitId, $patientId]);
                }
            }
        }
    
    success(['order_id' => $orderId], 'Result saved');
}

// POST — confirm lab item consumption (deduct quantity from stock)
if ($method === 'POST' && $action === 'confirm_lab_consumption') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $orderId = !empty($input['order_id']) ? (int)$input['order_id'] : null;
    $visitId = !empty($input['visit_id']) ? (int)$input['visit_id'] : null;
    $patientId = !empty($input['patient_id']) ? (int)$input['patient_id'] : null;
    $serviceId = !empty($input['service_id']) ? (int)$input['service_id'] : null;
    $storeId = !empty($input['store_id']) ? (int)$input['store_id'] : 1040;
    $items = $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    if (empty($items) || !is_array($items)) {
        error('At least one item is required for consumption', 422);
    }

    if ($orderId && (!$patientId || !$visitId)) {
        $ordStmt = $pdo->prepare("SELECT patient_id, visit_id, description FROM medical_orders WHERE id = ?");
        $ordStmt->execute([$orderId]);
        if ($oRow = $ordStmt->fetch()) {
            if (!$patientId) $patientId = (int)$oRow['patient_id'];
            if (!$visitId) $visitId = (int)$oRow['visit_id'];
        }
    }

    $pdo->beginTransaction();
    try {
        $deductedItems = [];

        foreach ($items as $it) {
            $itemId = (int)($it['item_id'] ?? 0);
            $qty = (float)($it['quantity'] ?? 0);
            $itemNotes = trim($it['notes'] ?? '');
            if (!$itemId || $qty <= 0) continue;

            // FIFO deduction from stock_batches in selected store
            $batchesStmt = $pdo->prepare("
                SELECT id, quantity, unit_cost, batch_number 
                FROM stock_batches 
                WHERE item_id = ? AND store_id = ? AND is_active = 1 AND quantity > 0 
                ORDER BY received_date ASC, created_at ASC, id ASC
            ");
            $batchesStmt->execute([$itemId, $storeId]);
            $batches = $batchesStmt->fetchAll();

            $remaining = $qty;
            $totalCost = 0;
            $lastSmId = null;
            $lastBatchId = null;

            if (!empty($batches)) {
                foreach ($batches as $b) {
                    if ($remaining <= 0) break;
                    $deduct = min((float)$b['quantity'], $remaining);
                    $newQty = (float)$b['quantity'] - $deduct;
                    $pdo->prepare("UPDATE stock_batches SET quantity = ? WHERE id = ?")->execute([$newQty, $b['id']]);

                    // Record stock movement for this batch
                    $movementNote = 'Lab consumption' . ($orderId ? " [Order #$orderId]" : '') . ($itemNotes ? ": $itemNotes" : '');
                    $pdo->prepare("
                        INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
                        VALUES (?, ?, ?, NULL, 'consumption', ?, ?, 'lab_consumption', ?, ?, ?, NOW())
                    ")->execute([$itemId, $b['id'], $storeId, $deduct, (float)$b['unit_cost'], $orderId, $movementNote, $userId]);
                    $lastSmId = (int)$pdo->lastInsertId();
                    $lastBatchId = (int)$b['id'];

                    $totalCost += $deduct * (float)$b['unit_cost'];
                    $remaining -= $deduct;
                }
            }

            // If remaining quantity not covered by positive batches
            if ($remaining > 0) {
                // Find any batch in store or create default batch
                $anyBatch = $pdo->prepare("SELECT id, unit_cost FROM stock_batches WHERE item_id = ? AND store_id = ? ORDER BY id DESC LIMIT 1");
                $anyBatch->execute([$itemId, $storeId]);
                $bRow = $anyBatch->fetch();

                if ($bRow) {
                    $batchId = (int)$bRow['id'];
                    $unitCost = (float)$bRow['unit_cost'];
                    $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ? WHERE id = ?")->execute([$remaining, $batchId]);
                } else {
                    $unitCost = 0.0;
                    $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, is_active, created_by, created_at) VALUES (?, ?, 'DEFAULT', ?, ?, 1, ?, NOW())")
                        ->execute([$itemId, $storeId, -$remaining, $unitCost, $userId]);
                    $batchId = (int)$pdo->lastInsertId();
                }

                $movementNote = 'Lab consumption' . ($orderId ? " [Order #$orderId]" : '') . ($itemNotes ? ": $itemNotes" : '');
                $pdo->prepare("
                    INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
                    VALUES (?, ?, ?, NULL, 'consumption', ?, ?, 'lab_consumption', ?, ?, ?, NOW())
                ")->execute([$itemId, $batchId, $storeId, $remaining, $unitCost, $orderId, $movementNote, $userId]);
                $lastSmId = (int)$pdo->lastInsertId();
                $lastBatchId = $batchId;

                $totalCost += $remaining * $unitCost;
            }

            $unitCostAvg = $qty > 0 ? ($totalCost / $qty) : 0.0;

            // Record in lab_item_consumption
            $licStmt = $pdo->prepare("
                INSERT INTO lab_item_consumption (order_id, visit_id, patient_id, service_id, item_id, store_id, batch_id, quantity, unit_cost, status, stock_movement_id, consumed_by, consumed_at, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, ?, NOW(), ?)
            ");
            $licStmt->execute([$orderId, $visitId, $patientId, $serviceId, $itemId, $storeId, $lastBatchId, $qty, $unitCostAvg, $lastSmId, $userId, $notes ?: $itemNotes]);
            $licId = (int)$pdo->lastInsertId();

            $deductedItems[] = [
                'consumption_id' => $licId,
                'item_id' => $itemId,
                'quantity' => $qty,
                'unit_cost' => $unitCostAvg,
                'store_id' => $storeId
            ];
        }

        $pdo->commit();
        success([
            'order_id' => $orderId,
            'deducted_items' => $deductedItems,
            'count' => count($deductedItems)
        ], 'Stock quantity deducted and consumption confirmed successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to deduct stock: ' . $e->getMessage(), 500);
    }
}

// POST — reverse lab item consumption
if ($method === 'POST' && $action === 'reverse_lab_consumption') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $consumptionId = (int)($input['consumption_id'] ?? 0);
    $reason = trim($input['reason'] ?? 'Lab consumption reversal');
    $userId = $_SESSION['user_id'] ?? null;

    if (!$consumptionId) error('consumption_id required', 422);

    $stmt = $pdo->prepare("SELECT * FROM lab_item_consumption WHERE id = ?");
    $stmt->execute([$consumptionId]);
    $lic = $stmt->fetch();
    if (!$lic) error('Consumption record not found', 404);
    if ($lic['status'] === 'reversed') error('Consumption record already reversed', 400);

    $pdo->beginTransaction();
    try {
        // Restore stock to stock_batches
        if (!empty($lic['batch_id'])) {
            $pdo->prepare("UPDATE stock_batches SET quantity = quantity + ? WHERE id = ?")
                ->execute([(float)$lic['quantity'], (int)$lic['batch_id']]);
        } else {
            $pdo->prepare("UPDATE stock_batches SET quantity = quantity + ? WHERE item_id = ? AND store_id = ? ORDER BY id DESC LIMIT 1")
                ->execute([(float)$lic['quantity'], (int)$lic['item_id'], (int)$lic['store_id']]);
        }

        // Record reversing stock movement
        $pdo->prepare("
            INSERT INTO stock_movements (item_id, batch_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
            VALUES (?, ?, ?, 'adjustment', ?, ?, 'lab_consumption_reversal', ?, ?, ?, NOW())
        ")->execute([(int)$lic['item_id'], $lic['batch_id'] ?: null, (int)$lic['store_id'], (float)$lic['quantity'], (float)$lic['unit_cost'], $consumptionId, "Reversal: $reason", $userId]);

        // Mark as reversed
        $pdo->prepare("UPDATE lab_item_consumption SET status = 'reversed', notes = CONCAT(COALESCE(notes,''), ' [REVERSED: ', ?, ']') WHERE id = ?")
            ->execute([$reason, $consumptionId]);

        $pdo->commit();
        success(null, 'Consumption reversed and stock restored successfully');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to reverse consumption: ' . $e->getMessage(), 500);
    }
}

// POST — reopen a completed consultation
if ($method === 'POST' && $action === 'reopen') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    if (!$visitId) error('visit_id required', 422);
    
    // Re-open the consultation record
    $pdo->prepare("UPDATE consultations SET status='draft' WHERE visit_id=? AND status='completed' ORDER BY id DESC LIMIT 1")
        ->execute([$visitId]);
    
    // Re-open the visit - update queued_at to now so it shows in today's queue
    $pdo->prepare("UPDATE visits SET status='in_progress', consultation_status='waiting', queued_at=NOW() WHERE id=?")
        ->execute([$visitId]);
    
    success(null, 'Consultation re-opened');
}

// POST — pharmacy order (create prescription + items)
if ($method === 'POST' && $action === 'pharmacy_order') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $deptId = (int)($input['department_id'] ?? 0);
    $items = $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');

    if (!$visitId || !$patientId) error('visit_id and patient_id required', 422);
    if (empty($items)) error('At least one medication required', 422);

    // ── Backend duplicate guard: block item_ids already prescribed this visit ──
    $incomingItemIds = array_filter(array_map(function($it) { return (int)($it['item_id'] ?? 0); }, $items));
    if (!empty($incomingItemIds)) {
        $placeholders = implode(',', array_fill(0, count($incomingItemIds), '?'));
        $dupStmt = $pdo->prepare("
            SELECT DISTINCT ppi.item_id
            FROM pharmacy_prescription_items ppi
            JOIN pharmacy_prescriptions pp ON pp.id = ppi.prescription_id
            WHERE pp.visit_id = ? AND ppi.item_id IN ($placeholders)
        ");
        $dupStmt->execute(array_merge([$visitId], array_values($incomingItemIds)));
        $dupIds = $dupStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($dupIds)) {
            error('Duplicate medication(s) already prescribed in this visit. Remove duplicates and retry.', 409);
        }
    }
    // ──────────────────────────────────────────────────────────────────────────

    $pdo->beginTransaction();
    try {
        // Generate prescription number
        $lastRx = $pdo->query("SELECT prescription_no FROM pharmacy_prescriptions ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 1;
        if ($lastRx && preg_match('/(\d+)$/', $lastRx, $m)) $seq = (int)$m[1] + 1;
        $rxNo = 'RX-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        // Get consultation_id if exists
        $cStmt = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? LIMIT 1");
        $cStmt->execute([$visitId]);
        $consultId = $cStmt->fetchColumn() ?: null;

        $pdo->prepare("INSERT INTO pharmacy_prescriptions (prescription_no, visit_id, patient_id, doctor_id, consultation_id, diagnosis, notes, status, prescription_date, created_at) VALUES (?,?,?,?,?,?,?,'active',CURDATE(),NOW())")
            ->execute([$rxNo, $visitId, $patientId, $input['doctor_id'] ?? 0, $consultId, '', $notes]);
        $rxId = (int)$pdo->lastInsertId();

        // Insert items
        $itemStmt = $pdo->prepare("INSERT INTO pharmacy_prescription_items (prescription_id, item_id, dosage, frequency, duration, quantity, instructions, status, destination) VALUES (?,?,?,?,?,?,?,'pending',?)");
        foreach ($items as $item) {
            $itemStmt->execute([
                $rxId,
                (int)($item['item_id'] ?? 0),
                $item['dosage'] ?? '',
                $item['frequency'] ?? '',
                $item['duration'] ?? '',
                (int)($item['quantity'] ?? 1),
                $item['medication'] ?? '',
                $item['destination'] ?? 'hospital'
            ]);
        }

        $pdo->commit();
        success(['prescription_id' => $rxId, 'prescription_no' => $rxNo], 'Prescription created: ' . $rxNo);
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed: ' . $e->getMessage());
    }
}

// POST — update prescription item quantity (syncs prescription, invoice item, invoice totals, and journal entries)
if ($method === 'POST' && ($action === 'update_rx_item_quantity' || $action === 'update_prescription_quantity' || $action === 'update_prescription_item_qty')) {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $itemId = (int)($input['prescription_item_id'] ?? $input['item_id'] ?? $_GET['id'] ?? 0);
    $newQty = (int)($input['quantity'] ?? $_GET['quantity'] ?? 0);

    if (!$itemId || $newQty < 1) {
        error('Valid prescription item ID and quantity (minimum 1) are required.', 422);
    }

    // 1. Fetch prescription item details
    $piStmt = $pdo->prepare("
        SELECT ppi.*, pp.visit_id, pp.patient_id, pp.prescription_no, i.name AS item_name, i.item_code
        FROM pharmacy_prescription_items ppi
        JOIN pharmacy_prescriptions pp ON pp.id = ppi.prescription_id
        LEFT JOIN items i ON i.id = ppi.item_id
        WHERE ppi.id = ?
    ");
    $piStmt->execute([$itemId]);
    $pi = $piStmt->fetch(PDO::FETCH_ASSOC);

    if (!$pi) {
        error('Prescription item not found.', 404);
    }

    $visitId = (int)$pi['visit_id'];
    $oldQty = (int)$pi['quantity'];
    $medItemId = (int)($pi['item_id'] ?? 0);
    $medName = $pi['item_name'] ?? $pi['instructions'] ?? '';

    $pdo->beginTransaction();
    try {
        // 2. Update pharmacy_prescription_items
        $pdo->prepare("UPDATE pharmacy_prescription_items SET quantity = ? WHERE id = ?")
            ->execute([$newQty, $itemId]);

        // 3. Find corresponding invoice item
        $invItemStmt = $pdo->prepare("
            SELECT ii.*, i.id AS inv_id
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE i.visit_id = ?
              AND ii.category IN ('medication', 'pharmacy', 'drug')
              AND (
                  ii.service_id = ?
                  OR (ii.description LIKE ? AND ii.description != '')
                  OR (ii.description LIKE ? AND ? != '')
              )
            ORDER BY ii.id DESC
            LIMIT 1
        ");
        $invItemStmt->execute([
            $visitId,
            $medItemId,
            "%{$medName}%",
            "%{$pi['item_code']}%",
            $pi['item_code'] ?? ''
        ]);
        $invItem = $invItemStmt->fetch(PDO::FETCH_ASSOC);

        $invoiceUpdated = false;
        $invoiceId = null;

        if ($invItem) {
            $invoiceId = (int)$invItem['inv_id'];
            $unitPrice = (float)$invItem['unit_price'];
            $discount = (float)($invItem['discount'] ?? 0);
            $newItemTotal = max(0, ($unitPrice * $newQty) - $discount);

            // Update invoice item
            $pdo->prepare("UPDATE invoice_items SET quantity = ?, total = ? WHERE id = ?")
                ->execute([$newQty, $newItemTotal, $invItem['id']]);

            // Recalculate invoice totals and update journal entries
            if (!function_exists('syncConsultationInvoiceAndJournal')) {
                function syncConsultationInvoiceAndJournal(PDO $pdo, int $invoiceId): void {
                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM invoice_items WHERE invoice_id = ?");
                    $stmt->execute([$invoiceId]);
                    $subtotal = (float)$stmt->fetchColumn();

                    $invStmt = $pdo->prepare("SELECT discount, tax, paid_amount, insurance_id, patient_id, payment_type FROM invoices WHERE id = ?");
                    $invStmt->execute([$invoiceId]);
                    $inv = $invStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$inv) return;

                    $discount   = (float)($inv['discount'] ?? 0);
                    $tax        = (float)($inv['tax'] ?? 0);
                    $paidAmount = (float)($inv['paid_amount'] ?? 0);
                    $total      = max(0, $subtotal - $discount + $tax);
                    $insId      = $inv['insurance_id'] ? (int)$inv['insurance_id'] : null;
                    $patId      = $inv['patient_id'] ? (int)$inv['patient_id'] : null;

                    $copayPct = 100.0;
                    if ($insId && ($inv['payment_type'] === 'insurance' || $inv['payment_type'] === 'mixed')) {
                        // 1. Try matching patient_insurance by id AND patient_id
                        $pStmt = $pdo->prepare("
                            SELECT ip.copayment_percentage
                            FROM patient_insurance pi
                            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                            WHERE (pi.id = ? OR pi.company_id = ?) AND pi.patient_id = ?
                            ORDER BY (pi.id = ?) DESC, pi.is_primary DESC, pi.id DESC
                            LIMIT 1
                        ");
                        $pStmt->execute([$insId, $insId, $patId, $insId]);
                        $pRow = $pStmt->fetch(PDO::FETCH_ASSOC);
                        if ($pRow && $pRow['copayment_percentage'] !== null) {
                            $copayPct = (float)$pRow['copayment_percentage'];
                        } else {
                            // Fallback to company default
                            $cStmt = $pdo->prepare("SELECT default_copay_percentage FROM insurance_companies WHERE id = ? LIMIT 1");
                            $cStmt->execute([$insId]);
                            $cVal = $cStmt->fetchColumn();
                            if ($cVal !== false && $cVal !== null) {
                                $copayPct = (float)$cVal;
                            } else {
                                $copayPct = 15.0;
                            }
                        }
                    }

                    if ($insId && $copayPct < 100) {
                        $patientShare = round($total * $copayPct / 100, 2);
                    } else {
                        $patientShare = $total;
                    }

                    $balance = max(0, $patientShare - $paidAmount);
                    $status = ($total <= 0) ? 'issued' : (($balance <= 0) ? 'paid' : (($paidAmount > 0) ? 'partial' : 'issued'));

                    $pdo->prepare("UPDATE invoices SET subtotal = ?, total = ?, balance = ?, status = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$subtotal, $total, $balance, $status, $invoiceId]);

                    $jeStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
                    $jeStmt->execute([$invoiceId]);
                    $jeId = $jeStmt->fetchColumn();
                    if (!$jeId) return;

                    $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$jeId]);
                    if ($total <= 0) return;

                    $patientAccId = 7;
                    if ($patId) {
                        $accId = fin_private_ar_acct($pdo);
                        if ($accId) $patientAccId = (int)$accId;
                    }

                    if ($insId && $copayPct < 100) {
                        $patientPortion = round($total * $copayPct / 100, 2);
                        $insPortion = round($total - $patientPortion, 2);

                        if ($patientPortion > 0) {
                            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                                ->execute([$jeId, $patientAccId, 'Patient portion (' . $copayPct . '%)', $patientPortion]);
                        }
                        $insAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE linked_insurance_id = $insId AND parent_id = 7 LIMIT 1")->fetchColumn();
                        if (!$insAccId) $insAccId = 7;
                        if ($insPortion > 0) {
                            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                                ->execute([$jeId, (int)$insAccId, 'Insurance portion (' . (100 - $copayPct) . '%)', $insPortion]);
                        }
                    } else {
                        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                            ->execute([$jeId, $patientAccId, 'Patient receivable (full)', $total]);
                    }

                    $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, 1007, ?, 0, ?)")
                        ->execute([$jeId, 'Revenue (updated)', $total]);
                }
            }

            syncConsultationInvoiceAndJournal($pdo, $invoiceId);
            $invoiceUpdated = true;
        }

        $pdo->commit();
        success([
            'prescription_item_id' => $itemId,
            'old_quantity' => $oldQty,
            'new_quantity' => $newQty,
            'invoice_updated' => $invoiceUpdated,
            'invoice_id' => $invoiceId
        ], "Quantity updated from $oldQty to $newQty.");
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to update quantity: ' . $e->getMessage());
    }
}

// POST — lab order (create medical_orders)
if ($method === 'POST' && $action === 'lab_order') {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];
    $visitId = (int)($input['visit_id'] ?? 0);
    $patientId = (int)($input['patient_id'] ?? 0);
    $deptId = (int)($input['department_id'] ?? 0);
    $tests = $input['tests'] ?? $input['items'] ?? [];
    $notes = trim($input['notes'] ?? '');

    if (!$visitId || !$patientId) error('visit_id and patient_id required', 422);
    if (empty($tests)) error('At least one test required', 422);

    // ── Backend duplicate guard: block service_ids already ordered this visit ──
    $incomingServiceIds = array_values(array_filter(array_map(function($t) { return (int)($t['service_id'] ?? 0); }, $tests)));
    if (!empty($incomingServiceIds)) {
        $placeholders = implode(',', array_fill(0, count($incomingServiceIds), '?'));
        $dupStmt = $pdo->prepare("
            SELECT DISTINCT ii.service_id
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE i.visit_id = ? AND ii.service_id IN ($placeholders)
            AND ii.category IN ('lab','laboratory','radiology','imaging')
        ");
        $dupStmt->execute(array_merge([$visitId], $incomingServiceIds));
        $dupIds = $dupStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($dupIds)) {
            error('Duplicate lab test(s) already ordered in this visit. Remove duplicates and retry.', 409);
        }
    }
    // ──────────────────────────────────────────────────────────────────────────

    $pdo->beginTransaction();
    try {
        // Get consultation_id if exists
        $cStmt = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? LIMIT 1");
        $cStmt->execute([$visitId]);
        $consultId = $cStmt->fetchColumn() ?: null;

        $orderedBy = !empty($input['ordered_by']) ? (int)$input['ordered_by'] : ($_SESSION['user_id'] ?? null);
        $orderStmt = $pdo->prepare("INSERT INTO medical_orders (consultation_id, visit_id, patient_id, order_type, description, details, status, ordered_by, ordered_at) VALUES (?,?,?,?,?,?,'ordered',?,NOW())");

        foreach ($tests as $test) {
            $orderStmt->execute([
                $consultId,
                $visitId,
                $patientId,
                'lab',
                $test['test_name'] ?? '',
                $notes . ($test['priority'] && $test['priority'] !== 'normal' ? ' [Priority: ' . $test['priority'] . ']' : ''),
                $orderedBy
            ]);
        }

        $pdo->commit();

        // Create notification for lab department
        try {
            $ptStmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) AS name FROM patients WHERE id = ?");
            $ptStmt->execute([$patientId]);
            $ptName = $ptStmt->fetchColumn() ?: 'Patient';

            $testCount = count($tests);
            $testNames = array_map(function($t){ return $t['test_name'] ?? ''; }, $tests);
            $testList = implode(', ', array_slice($testNames, 0, 3));
            if($testCount > 3) $testList .= '...';

            $notifTitle = "New Lab Request";
            $notifMessage = "$testCount test(s) ordered for $ptName: $testList";

            $notifStmt = $pdo->prepare("INSERT INTO notifications (type, title, message, visit_id, patient_id, target_role) VALUES ('lab_request', ?, ?, ?, ?, 'lab')");
            $notifStmt->execute([$notifTitle, $notifMessage, $visitId, $patientId]);
        } catch (Exception $e) {
            // Non-critical — don't fail the order if notification fails
        }

        success(null, count($tests) . ' lab test(s) ordered');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed: ' . $e->getMessage());
    }
}

// POST — create/update consultation
if ($method === 'POST' && (!$action || $action === 'complete')) {
    $input = $GLOBALS['_parsed_input'] ?? json_decode(file_get_contents('php://input'), true) ?? [];

    // If action is 'complete', set status to completed and map fields
    if ($action === 'complete') {
        $input['status'] = 'completed';
        if (!empty($input['final_diagnosis'])) {
            $input['diagnosis'] = $input['final_diagnosis'];
        }
        // Convert prescription array to JSON string for storage
        if (isset($input['prescription']) && is_array($input['prescription'])) {
            $input['prescription'] = json_encode($input['prescription']);
        }
    }
    foreach (['visit_id','patient_id','department_id'] as $f) {
        if (empty($input[$f])) error("Field '$f' required", 422);
    }

    // Check existing
    $chk = $pdo->prepare("SELECT id FROM consultations WHERE visit_id=? AND department_id=?");
    $chk->execute([(int)$input['visit_id'], (int)$input['department_id']]);
    $existing = $chk->fetchColumn();

    $fields = [
        'visit_id','patient_id','department_id','doctor_id','doctor_name',
        'bp_systolic','bp_diastolic','pulse','temperature','weight','height','oxygen_sat','respiratory_rate',
        'chief_complaint','history','examination','differential_diagnosis','diagnosis','icd_code','treatment_plan','prescription','notes',
        'outcome','referred_to_dept','admit_ward','follow_up_date','status','created_by'
    ];

    if ($existing) {
        // Update
        $sets = []; $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f,$input) && !in_array($f,['visit_id','patient_id','department_id','created_by'])) {
                $sets[] = "$f=?"; $params[] = $input[$f] === '' ? null : $input[$f];
            }
        }
        if ($sets) { $params[] = $existing; $pdo->prepare("UPDATE consultations SET ".implode(',',$sets)." WHERE id=?")->execute($params); }
        $cid = $existing;
    } else {
        // Insert
        $cols = []; $vals = []; $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f,$input)) { $cols[] = $f; $vals[] = '?'; $params[] = $input[$f] === '' ? null : $input[$f]; }
        }
        $pdo->prepare("INSERT INTO consultations (".implode(',',$cols).") VALUES (".implode(',',$vals).")")->execute($params);
        $cid = (int)$pdo->lastInsertId();
    }

    // If completed, update visit status
    if (($input['status'] ?? '') === 'completed') {
        $outcome = $input['outcome'] ?? null;
        $vStatus = 'in_progress';
        switch ($outcome) {
            case 'admitted':
            case 'referred':
                $vStatus = 'in_progress';
                break;
            case 'discharged':
            case 'treated':
            case 'follow_up':
                $vStatus = 'completed';
                break;
            default:
                $vStatus = 'in_progress';
                break;
        }
        $pdo->prepare("UPDATE visits SET status=?, consultation_status='done' WHERE id=?")->execute([$vStatus, (int)$input['visit_id']]);
        // Mark queue done
        $pdo->prepare("UPDATE patient_queue SET status='done', done_at=NOW() WHERE visit_id=? AND department_id=?")
            ->execute([(int)$input['visit_id'], (int)$input['department_id']]);
    }

    $fetchSt = $pdo->prepare("SELECT c.*, d.name AS dept_name FROM consultations c LEFT JOIN departments d ON d.id=c.department_id WHERE c.id=?");
    $fetchSt->execute([$cid]);
    $c = $fetchSt->fetch();
    $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id=? ORDER BY ordered_at");
    $oStmt->execute([$cid]);
    $c['orders'] = $oStmt->fetchAll();
    success($c, $existing ? 'Consultation updated' : 'Consultation created', 201);
}

// POST orders via ?action=order
if ($method === 'POST' && $action === 'order') {
    // handled below — unreachable, merged into POST
}

// PUT — add medical order
if ($method === 'PUT' && $action === 'order') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach (['consultation_id','visit_id','patient_id','order_type','description'] as $f) {
        if (empty($input[$f])) error("Field '$f' required", 422);
    }
    $pdo->prepare("INSERT INTO medical_orders (consultation_id,visit_id,patient_id,order_type,description,details,ordered_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([(int)$input['consultation_id'],(int)$input['visit_id'],(int)$input['patient_id'],$input['order_type'],trim($input['description']),trim($input['details']??''),$input['ordered_by']??null]);
    success(['id'=>(int)$pdo->lastInsertId()], 'Order added');
}

// DELETE order
if ($method === 'DELETE') {
    if (!$id) error('ID required', 422);
    $action2 = $_GET['action'] ?? '';
    if ($action2 === 'order') {
        $pdo->prepare("DELETE FROM medical_orders WHERE id=?")->execute([$id]);
    } else {
        $pdo->prepare("DELETE FROM consultations WHERE id=?")->execute([$id]);
    }
    success(null, 'Deleted');
}

// ===== SCHEDULE TIMETABLE =====
if ($method === 'GET' && $action === 'schedule') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS nursing_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        visit_id INT,
        schedule_type VARCHAR(50) DEFAULT 'medication',
        description VARCHAR(255) NOT NULL,
        frequency VARCHAR(50),
        scheduled_times JSON,
        administered_times JSON,
        start_date DATE NOT NULL,
        end_date DATE,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->prepare("
        SELECT ns.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name
        FROM nursing_schedule ns
        JOIN patients p ON p.id=ns.patient_id
        WHERE ns.start_date <= ? AND (ns.end_date IS NULL OR ns.end_date >= ?)
        ORDER BY ns.patient_id, ns.schedule_type, ns.description
    ");
    $stmt->execute([$date, $date]);
    $schedules = $stmt->fetchAll();
    
    // For each schedule, get today's administered times
    foreach ($schedules as &$s) {
        $admStmt = $pdo->prepare("SELECT administered_at FROM nursing_schedule_log WHERE schedule_id=? AND DATE(administered_at)=?");
        $admStmt->execute([$s['id'], $date]);
        $admTimes = $admStmt->fetchAll(PDO::FETCH_COLUMN);
        $s['administered_times'] = json_encode(array_map(function($t){return date('H:i',strtotime($t));}, $admTimes));
    }
    success($schedules);
}

if ($method === 'POST' && $action === 'schedule_save') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $patientId = (int)($input['patient_id'] ?? 0);
    $visitId = !empty($input['visit_id']) ? (int)$input['visit_id'] : null;
    $type = $input['schedule_type'] ?? 'medication';
    $desc = trim($input['description'] ?? '');
    $freq = $input['frequency'] ?? 'once';
    $times = $input['scheduled_times'] ?? '["08:00"]';
    $startDate = $input['start_date'] ?? date('Y-m-d');
    $endDate = !empty($input['end_date']) ? $input['end_date'] : null;
    $notes = $input['notes'] ?? '';
    
    if (!$patientId || !$desc) error('Patient and description required', 422);
    
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS nursing_schedule (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        visit_id INT,
        schedule_type VARCHAR(50) DEFAULT 'medication',
        description VARCHAR(255) NOT NULL,
        frequency VARCHAR(50),
        scheduled_times JSON,
        administered_times JSON,
        start_date DATE NOT NULL,
        end_date DATE,
        notes TEXT,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->prepare("INSERT INTO nursing_schedule (patient_id, visit_id, schedule_type, description, frequency, scheduled_times, start_date, end_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$patientId, $visitId, $type, $desc, $freq, $times, $startDate, $endDate, $notes, $_SESSION['user_id'] ?? null]);
    success(['id' => (int)$pdo->lastInsertId()], 'Schedule created');
}

if ($method === 'POST' && $action === 'schedule_administer') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $schedId = (int)($input['schedule_id'] ?? 0);
    $timeSlot = $input['time_slot'] ?? '';
    if (!$schedId) error('schedule_id required', 422);
    
    // Ensure log table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS nursing_schedule_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        administered_at DATETIME NOT NULL,
        administered_by INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Record administration
    $adminTime = date('Y-m-d') . ' ' . ($timeSlot ?: date('H:i')) . ':00';
    $pdo->prepare("INSERT INTO nursing_schedule_log (schedule_id, administered_at, administered_by) VALUES (?,?,?)")
        ->execute([$schedId, $adminTime, $_SESSION['user_id'] ?? null]);
    success(null, 'Marked as administered');
}

error('Method not allowed', 405);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('DB error: '.$e->getMessage(), 500);
}
?>
