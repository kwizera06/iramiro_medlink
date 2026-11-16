<?php
// handlers/consultation/consult_enhanced.php
// Phase 2: Enhanced Consultation System with form submission and multi-item prescriptions
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ============================================================
// GET REQUIRED FORMS FOR A DEPARTMENT
// ============================================================
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

// ============================================================
// GET CONSULTATION WITH FORMS AND PRESCRIPTIONS
// ============================================================
// GET /consultation/consult?action=consultation_with_forms&consultation_id=X
if ($method === 'GET' && $action === 'consultation_with_forms') {
    $consultId = isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0;
    
    if (!$consultId) error('consultation_id required', 422);
    
    $stmt = $pdo->prepare("
        SELECT c.*, d.name AS dept_name
        FROM consultations c
        LEFT JOIN departments d ON d.id = c.department_id
        WHERE c.id = ?
    ");
    $stmt->execute([$consultId]);
    $consultation = $stmt->fetch();
    
    if (!$consultation) error('Consultation not found', 404);
    
    // Get linked forms
    if ($consultation['form_submission_batch_id']) {
        $fStmt = $pdo->prepare("
            SELECT vdf.* FROM visit_department_forms vdf
            WHERE vdf.visit_id = ? AND vdf.id IN (
                SELECT form_id FROM consultation_form_references WHERE consultation_id = ?
            )
        ");
        $fStmt->execute([$consultation['visit_id'], $consultId]);
        $forms = $fStmt->fetchAll();
        foreach ($forms as &$f) {
            $f['form_data'] = json_decode($f['form_data'], true);
        }
        $consultation['forms'] = $forms;
    }
    
    // Get prescriptions
    if ($consultation['prescription_batch_id']) {
        $pStmt = $pdo->prepare("
            SELECT pi.*, i.name AS item_name, i.code AS item_code
            FROM prescription_items pi
            LEFT JOIN items i ON i.id = pi.item_id
            WHERE pi.batch_id = ?
            ORDER BY pi.id ASC
        ");
        $pStmt->execute([$consultation['prescription_batch_id']]);
        $consultation['prescriptions'] = $pStmt->fetchAll();
    }
    
    // Get medical orders
    $oStmt = $pdo->prepare("
        SELECT * FROM medical_orders
        WHERE consultation_id = ?
        ORDER BY ordered_at DESC
    ");
    $oStmt->execute([$consultId]);
    $consultation['orders'] = $oStmt->fetchAll();
    
    success($consultation);
}

// ============================================================
// SAVE CONSULTATION WITH FORMS AND PRESCRIPTIONS (ATOMIC)
// ============================================================
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
            'diagnosis', 'icd_code', 'treatment_plan', 'notes', 'outcome', 'referred_to_dept', 
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
        $formBatchId = null;
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
            $formBatchId = 1; // Placeholder - actual implementation could create batch records
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
        if ($formBatchId || $prescriptionBatchId) {
            $updates = [];
            $params = [];
            
            if ($formBatchId) {
                $updates[] = "form_submission_batch_id = ?";
                $params[] = $formBatchId;
            }
            if ($prescriptionBatchId) {
                $updates[] = "prescription_batch_id = ?";
                $params[] = $prescriptionBatchId;
            }
            
            if ($updates) {
                $params[] = $consultId;
                $pdo->prepare("UPDATE consultations SET " . implode(', ', $updates) . " WHERE id = ?")
                    ->execute($params);
            }
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
        
        // Get forms
        $formStmt = $pdo->prepare("
            SELECT vdf.* FROM visit_department_forms vdf
            WHERE vdf.visit_id = ? AND vdf.id IN (
                SELECT form_id FROM consultation_form_references WHERE consultation_id = ?
            )
        ");
        $formStmt->execute([$visitId, $consultId]);
        $forms = $formStmt->fetchAll();
        foreach ($forms as &$f) {
            $f['form_data'] = json_decode($f['form_data'], true);
        }
        $result['forms'] = $forms;
        
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
// ADD PRESCRIPTION ITEM
// ============================================================
// PUT /consultation/consult?action=add_prescription_item
if ($method === 'PUT' && $action === 'add_prescription_item') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    foreach (['batch_id', 'item_id', 'dosage', 'frequency'] as $f) {
        if (empty($input[$f])) error("Field '$f' required", 422);
    }
    
    $pdo->prepare("
        INSERT INTO prescription_items (batch_id, item_id, dosage, frequency, duration, quantity, unit, route, instructions, is_controlled, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        (int)$input['batch_id'],
        (int)$input['item_id'],
        $input['dosage'],
        $input['frequency'],
        $input['duration'] ?? null,
        $input['quantity'] ?? 0,
        $input['unit'] ?? 'tablet(s)',
        $input['route'] ?? 'oral',
        $input['instructions'] ?? '',
        $input['is_controlled'] ?? 0,
        'pending'
    ]);
    
    success(['id' => (int)$pdo->lastInsertId()], 'Prescription item added');
}

// ============================================================
// REMOVE PRESCRIPTION ITEM
// ============================================================
// DELETE /consultation/consult?action=prescription_item&id=X
if ($method === 'DELETE' && $action === 'prescription_item') {
    if (!$id) error('ID required', 422);
    
    // Get prescription item details before deletion
    $itemStmt = $pdo->prepare("SELECT ppi.*, pp.visit_id, pp.patient_id FROM pharmacy_prescription_items ppi JOIN pharmacy_prescriptions pp ON pp.id = ppi.prescription_id WHERE ppi.id = ?");
    $itemStmt->execute([$id]);
    $prescItem = $itemStmt->fetch();
    
    if (!$prescItem) error('Prescription item not found', 404);
    
    $pdo->beginTransaction();
    try {
        // Delete prescription item
        $pdo->prepare("DELETE FROM pharmacy_prescription_items WHERE id = ?")->execute([$id]);
        
        // Find and update related invoice
        $visitId = (int)$prescItem['visit_id'];
        if ($visitId) {
            // Find unpaid pharmacy invoices for this visit
            $invStmt = $pdo->prepare("
                SELECT DISTINCT i.id FROM invoices i
                JOIN invoice_items ii ON ii.invoice_id = i.id
                WHERE i.visit_id = ? AND ii.category IN ('pharmacy','medication','drug')
                  AND i.status != 'paid'
                LIMIT 1
            ");
            $invStmt->execute([$visitId]);
            $invoiceId = $invStmt->fetchColumn();
            
            if ($invoiceId) {
                // Delete pharmacy items for this specific item (match by item_id if available)
                $itemId = (int)$prescItem['item_id'];
                if ($itemId > 0) {
                    $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ? AND service_id = ? AND category IN ('pharmacy','medication','drug')")->execute([$invoiceId, $itemId]);
                }
                
                // Check if invoice still has items
                $remCount = (int)$pdo->query("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = $invoiceId")->fetchColumn();
                
                if ($remCount === 0) {
                    // Delete invoice and journal entries
                    $jeIds = $pdo->query("SELECT id FROM journal_entries WHERE reference_type='invoice' AND reference_id=$invoiceId");
                    foreach ($jeIds->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
                        $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id=?")->execute([$jeId]);
                        $pdo->prepare("DELETE FROM journal_entries WHERE id=?")->execute([$jeId]);
                    }
                    $pdo->prepare("DELETE FROM payment_records WHERE invoice_id=?")->execute([$invoiceId]);
                    $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$invoiceId]);
                } else {
                    // Recalculate invoice total
                    $newTotal = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = $invoiceId")->fetchColumn();
                    $pdo->prepare("UPDATE invoices SET total=?, subtotal=?, balance=?-paid_amount WHERE id=?")->execute([$newTotal, $newTotal, $newTotal, $invoiceId]);
                    
                    // Update journal entries
                    $jeStmt = $pdo->query("SELECT id FROM journal_entries WHERE reference_type='invoice' AND reference_id=$invoiceId");
                    foreach ($jeStmt->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
                        $pdo->prepare("UPDATE journal_entry_lines SET debit = CASE WHEN debit > 0 THEN ? ELSE 0 END, credit = CASE WHEN credit > 0 THEN ? ELSE 0 END WHERE journal_entry_id = ?")->execute([$newTotal, $newTotal, $jeId]);
                    }
                }
            }
        }
        
        $pdo->commit();
        success(null, 'Prescription item removed');
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Failed to remove prescription item: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// LEGACY: Standard consultation operations
// ============================================================

// GET — consultation + orders
if ($method === 'GET' && !$action) {
    if ($id) {
        $stmt = $pdo->prepare("SELECT c.*, d.name AS dept_name FROM consultations c LEFT JOIN departments d ON d.id = c.department_id WHERE c.id = ?");
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) error('Consultation not found', 404);
        
        $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id = ? ORDER BY ordered_at DESC");
        $oStmt->execute([$id]);
        $c['orders'] = $oStmt->fetchAll();
        success($c);
    }
    
    $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    if ($visitId) {
        $where = 'WHERE c.visit_id = ?';
        $params = [$visitId];
        if ($deptId) {
            $where .= ' AND c.department_id = ?';
            $params[] = $deptId;
        }
        $stmt = $pdo->prepare("SELECT c.*, d.name AS dept_name FROM consultations c LEFT JOIN departments d ON d.id = c.department_id $where ORDER BY c.created_at DESC LIMIT 1");
        $stmt->execute($params);
        $c = $stmt->fetch();
        if ($c) {
            $oStmt = $pdo->prepare("SELECT * FROM medical_orders WHERE consultation_id = ? ORDER BY ordered_at");
            $oStmt->execute([$c['id']]);
            $c['orders'] = $oStmt->fetchAll();
        }
        success($c ?: null);
    }
    
    error('Missing parameters', 422);
}

// POST — legacy order
if ($method === 'POST' && $action === 'order') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach (['consultation_id', 'visit_id', 'patient_id', 'order_type', 'description'] as $f) {
        if (empty($input[$f])) error("Field '$f' required", 422);
    }
    $pdo->prepare("INSERT INTO medical_orders (consultation_id, visit_id, patient_id, order_type, description, details, ordered_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([(int)$input['consultation_id'], (int)$input['visit_id'], (int)$input['patient_id'], $input['order_type'], trim($input['description']), trim($input['details'] ?? ''), $input['ordered_by'] ?? null]);
    success(['id' => (int)$pdo->lastInsertId()], 'Order added');
}

// DELETE — legacy
if ($method === 'DELETE' && !$action && $id) {
    $pdo->prepare("DELETE FROM consultations WHERE id = ?")->execute([$id]);
    success(null, 'Deleted');
}

error('Method not allowed or action not found', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('DB error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Server error: ' . $e->getMessage(), 500);
}
?>
