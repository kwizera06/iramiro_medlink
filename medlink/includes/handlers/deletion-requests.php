<?php
// handlers/deletion-requests.php
require_once __DIR__ . '/../includes/database.php';

function jsonResp($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success'){jsonResp(['success'=>true,'message'=>$m,'data'=>$d]);}
function err($m,$s=400){jsonResp(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = $_SESSION['user_id'] ?? 0;

// ===== GET =====
if ($method === 'GET') {

    // List all deletion requests with filters
    if (!$action || $action === 'list') {
        $status = $_GET['status'] ?? '';
        $entityType = $_GET['entity_type'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];
        if ($status) { $where[] = "dr.status = ?"; $params[] = $status; }
        if ($entityType) { $where[] = "dr.entity_type = ?"; $params[] = $entityType; }
        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM deletion_requests dr $whereSQL");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $totalPages = ceil($total / $limit);

        // Fetch
        $sql = "SELECT dr.*, 
                    CONCAT(u1.first_name, ' ', u1.last_name) AS requested_by_name,
                    CONCAT(u2.first_name, ' ', u2.last_name) AS reviewed_by_name
                FROM deletion_requests dr
                LEFT JOIN users u1 ON u1.id = dr.requested_by
                LEFT JOIN users u2 ON u2.id = dr.reviewed_by
                $whereSQL
                ORDER BY dr.requested_at DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        // Stats
        $statsStmt = $pdo->query("SELECT status, COUNT(*) as cnt FROM deletion_requests GROUP BY status");
        $statsRaw = $statsStmt->fetchAll();
        $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'executed' => 0, 'total' => $total];
        foreach ($statsRaw as $s) { $stats[$s['status']] = (int)$s['cnt']; }

        ok([
            'requests' => $requests,
            'stats' => $stats,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => $totalPages]
        ]);
    }

    // Single request detail
    if ($action === 'detail' && $id) {
        $stmt = $pdo->prepare("SELECT dr.*, 
                    CONCAT(u1.first_name, ' ', u1.last_name) AS requested_by_name,
                    CONCAT(u2.first_name, ' ', u2.last_name) AS reviewed_by_name,
                    CONCAT(u3.first_name, ' ', u3.last_name) AS executed_by_name
                FROM deletion_requests dr
                LEFT JOIN users u1 ON u1.id = dr.requested_by
                LEFT JOIN users u2 ON u2.id = dr.reviewed_by
                LEFT JOIN users u3 ON u3.id = dr.executed_by
                WHERE dr.id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) err('Request not found', 404);

        // Attach entity details (patient info, visits, etc.)
        $req['entity_details'] = null;

        // For executed requests — load snapshot from audit log + patient/visit from live DB
        if ($req['status'] === 'executed') {
            $auditStmt = $pdo->prepare("SELECT entity_data FROM deletion_audit_log WHERE deletion_request_id = ? ORDER BY id DESC LIMIT 1");
            $auditStmt->execute([$id]);
            $auditRow = $auditStmt->fetch();
            if ($auditRow && !empty($auditRow['entity_data'])) {
                $snapshot = json_decode($auditRow['entity_data'], true) ?: [];
                $snapshot['_from_audit_log'] = true;

                // Enrich with live patient info if we have patient_id
                $livePid = (int)($req['patient_id'] ?? $snapshot['patient_id'] ?? 0);
                if ($livePid) {
                    $patStmt2 = $pdo->prepare("SELECT CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no, p.phone, p.gender, p.date_of_birth, ic.name AS insurance_company, ip.name AS package_name FROM patients p LEFT JOIN patient_insurance pi ON pi.patient_id=p.id LEFT JOIN insurance_companies ic ON ic.id=pi.company_id LEFT JOIN insurance_packages ip ON ip.id=pi.package_id WHERE p.id=?");
                    $patStmt2->execute([$livePid]);
                    $livePatient = $patStmt2->fetch();
                    if ($livePatient) {
                        $snapshot['patient_name']      = $livePatient['patient_name'];
                        $snapshot['patient_no']        = $livePatient['patient_no'];
                        $snapshot['phone']             = $livePatient['phone'];
                        $snapshot['gender']            = $livePatient['gender'];
                        $snapshot['date_of_birth']     = $livePatient['date_of_birth'];
                        $snapshot['insurance_company'] = $livePatient['insurance_company'];
                        $snapshot['package_name']      = $livePatient['package_name'];
                    }
                }
                // Enrich with live visit info if we have visit_id
                $liveVid = (int)($req['visit_id'] ?? $snapshot['visit_id'] ?? 0);
                if ($liveVid) {
                    $visStmt2 = $pdo->prepare("SELECT v.visit_no, v.visit_date, v.visit_type, v.payment_type, d.name AS department_name FROM visits v LEFT JOIN departments d ON d.id=v.department_id WHERE v.id=?");
                    $visStmt2->execute([$liveVid]);
                    $liveVisit = $visStmt2->fetch();
                    if ($liveVisit) {
                        $snapshot['visit_no']        = $liveVisit['visit_no'];
                        $snapshot['visit_date']      = $liveVisit['visit_date'];
                        $snapshot['visit_type']      = $liveVisit['visit_type'];
                        $snapshot['payment_type']    = $liveVisit['payment_type'];
                        $snapshot['department_name'] = $liveVisit['department_name'];
                    }
                }
                $req['entity_details'] = $snapshot;
            }
        }

        if (!$req['entity_details'] && $req['entity_type'] === 'patient' && $req['status'] !== 'executed') {
            $entityId = (int)$req['entity_id'];
            $patStmt = $pdo->prepare("SELECT p.*, ic.name AS insurance_company, ip.name AS package_name
                FROM patients p
                LEFT JOIN patient_insurance pi ON pi.patient_id = p.id
                LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
                LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                WHERE p.id = ?");
            $patStmt->execute([$entityId]);
            $patient = $patStmt->fetch();
            if ($patient) {
                // Get visits for this patient
                $visStmt = $pdo->prepare("SELECT v.id, v.visit_no, v.visit_date, v.visit_type, v.payment_type, v.status, d.name AS department_name
                    FROM visits v
                    LEFT JOIN departments d ON d.id = v.department_id
                    WHERE v.patient_id = ?
                    ORDER BY v.visit_date DESC LIMIT 10");
                $visStmt->execute([$entityId]);
                $patient['visits'] = $visStmt->fetchAll();

                // Get invoice totals
                $invStmt = $pdo->prepare("SELECT COUNT(*) as invoice_count, COALESCE(SUM(total),0) as total_billed, COALESCE(SUM(paid_amount),0) as total_paid, COALESCE(SUM(balance),0) as total_balance
                    FROM invoices WHERE patient_id = ?");
                $invStmt->execute([$entityId]);
                $patient['billing_summary'] = $invStmt->fetch();

                $req['entity_details'] = $patient;
            }
        }

        // Procedure entity details
        if (!$req['entity_details'] && $req['entity_type'] === 'procedure' && $req['status'] !== 'executed') {
            $entityId = (int)$req['entity_id'];
            $procStmt = $pdo->prepare("
                SELECT ii.*, i.invoice_no, i.visit_id, i.patient_id, i.payment_type, i.insurance_id, i.status AS invoice_status,
                       v.visit_no, v.visit_date, v.visit_type,
                       CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no, p.phone, p.gender, p.date_of_birth,
                       ic.name AS insurance_company, ip.name AS package_name,
                       sc.procedure_name AS service_name, sc.speciality
                FROM invoice_items ii
                JOIN invoices i ON i.id = ii.invoice_id
                JOIN visits v ON v.id = i.visit_id
                JOIN patients p ON p.id = i.patient_id
                LEFT JOIN patient_insurance pi ON pi.patient_id = p.id
                LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
                LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                LEFT JOIN service_catalog sc ON sc.id = ii.service_id
                WHERE ii.id = ?");
            $procStmt->execute([$entityId]);
            $proc = $procStmt->fetch();
            if ($proc) {
                $req['entity_details'] = $proc;
            }
        }

        // Lab order entity details
        if (!$req['entity_details'] && $req['entity_type'] === 'lab_order' && $req['status'] !== 'executed') {
            $entityId = (int)$req['entity_id'];
            $labStmt = $pdo->prepare("
                SELECT mo.*, v.visit_no, v.visit_date, v.visit_type, v.payment_type,
                       CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no, p.phone, p.gender, p.date_of_birth,
                       ic.name AS insurance_company, ip.name AS package_name,
                       CONCAT(u.first_name,' ',u.last_name) AS ordered_by_name
                FROM medical_orders mo
                JOIN visits v ON v.id = mo.visit_id
                JOIN patients p ON p.id = mo.patient_id
                LEFT JOIN patient_insurance pi ON pi.patient_id = p.id
                LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
                LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                LEFT JOIN users u ON u.id = mo.ordered_by
                WHERE mo.id = ?");
            $labStmt->execute([$entityId]);
            $lab = $labStmt->fetch();
            if ($lab) {
                $req['entity_details'] = $lab;
            }
        }

        // Prescription entity details
        if (!$req['entity_details'] && $req['entity_type'] === 'prescription' && $req['status'] !== 'executed') {
            $entityId = (int)$req['entity_id'];
            $rxStmt = $pdo->prepare("
                SELECT pr.*, v.visit_no, v.visit_date, v.visit_type, v.payment_type,
                       CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no, p.phone, p.gender, p.date_of_birth,
                       ic.name AS insurance_company, ip.name AS package_name,
                       CONCAT(u.first_name,' ',u.last_name) AS doctor_name
                FROM pharmacy_prescriptions pr
                JOIN patients p ON p.id = pr.patient_id
                LEFT JOIN visits v ON v.id = pr.visit_id
                LEFT JOIN users u ON u.id = pr.doctor_id
                LEFT JOIN patient_insurance pi ON pi.patient_id = p.id
                LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
                LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                WHERE pr.id = ?");
            $rxStmt->execute([$entityId]);
            $rx = $rxStmt->fetch();
            if ($rx) {
                // Get prescription items with item names
                $itemsStmt = $pdo->prepare("SELECT ppi.*, it.name AS item_name FROM pharmacy_prescription_items ppi LEFT JOIN items it ON it.id = ppi.item_id WHERE ppi.prescription_id = ?");
                $itemsStmt->execute([$entityId]);
                $rx['items'] = $itemsStmt->fetchAll();
                $req['entity_details'] = $rx;
            }
        }

        ok($req);
    }

    // Check if entity already has a pending request
    if ($action === 'check') {
        $entityType = $_GET['entity_type'] ?? '';
        $entityId = (int)($_GET['entity_id'] ?? 0);
        if (!$entityType || !$entityId) err('entity_type and entity_id required');
        $stmt = $pdo->prepare("SELECT id, status FROM deletion_requests WHERE entity_type=? AND entity_id=? AND status IN ('pending','approved') ORDER BY requested_at DESC LIMIT 1");
        $stmt->execute([$entityType, $entityId]);
        $existing = $stmt->fetch();
        ok($existing ?: null);
    }
}

// ===== POST =====
if ($method === 'POST') {

    // Create a new deletion request
    if (!$action || $action === 'create') {
        // Support multipart form data (for file upload)
        $entityType = $_POST['entity_type'] ?? '';
        $entityId = (int)($_POST['entity_id'] ?? 0);
        $entityName = $_POST['entity_name'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $requestedBy = (int)($_POST['requested_by'] ?? $userId);
        $visitId   = isset($_POST['visit_id'])   ? (int)$_POST['visit_id']   : null;
        $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;

        if (!$entityType) err('entity_type is required');
        if (!$entityId) err('entity_id is required');
        if (!$remarks || strlen($remarks) < 10) err('Detailed remarks are required (at least 10 characters)');
        if (!$requestedBy) err('User must be logged in');

        // Check for existing pending request
        $existing = $pdo->prepare("SELECT id FROM deletion_requests WHERE entity_type=? AND entity_id=? AND status IN ('pending','approved')");
        $existing->execute([$entityType, $entityId]);
        if ($existing->fetch()) err('A deletion request already exists for this record');

        // Handle file upload
        $proofPath = null;
        if (isset($_FILES['proof_document']) && $_FILES['proof_document']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/deletion-proofs/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['proof_document']['name'], PATHINFO_EXTENSION);
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
            if (!in_array(strtolower($ext), $allowed)) err('Invalid file type. Allowed: pdf, jpg, png, doc, docx');
            $filename = 'proof_' . time() . '_' . $entityType . '_' . $entityId . '.' . $ext;
            $dest = $uploadDir . $filename;
            if (!move_uploaded_file($_FILES['proof_document']['tmp_name'], $dest)) err('Failed to upload proof document');
            $proofPath = 'uploads/deletion-proofs/' . $filename;
        }

        $stmt = $pdo->prepare("INSERT INTO deletion_requests (entity_type, entity_id, entity_name, visit_id, patient_id, remarks, proof_document, requested_by, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$entityType, $entityId, $entityName, $visitId ?: null, $patientId ?: null, $remarks, $proofPath, $requestedBy]);
        ok(['id' => $pdo->lastInsertId()], 'Deletion request submitted successfully');
    }

    // Approve or reject
    if ($action === 'review') {
        $input = json_decode(file_get_contents('php://input'), true);
        $reqId = (int)($input['id'] ?? 0);
        $status = $input['status'] ?? ''; // approved or rejected
        $reviewNotes = $input['review_notes'] ?? '';
        $reviewedBy = (int)($input['reviewed_by'] ?? $userId);

        if (!$reqId) err('Request ID required');
        if (!in_array($status, ['approved', 'rejected'])) err('Status must be approved or rejected');
        if (!$reviewedBy) err('Reviewer must be logged in');

        // Check current status
        $stmt = $pdo->prepare("SELECT * FROM deletion_requests WHERE id = ?");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) err('Request not found', 404);
        if ($req['status'] !== 'pending') err('Can only review pending requests');

        $stmt = $pdo->prepare("UPDATE deletion_requests SET status=?, reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE id=?");
        $stmt->execute([$status, $reviewedBy, $reviewNotes, $reqId]);
        ok(null, 'Request ' . $status);
    }

    // Execute deletion (only if approved)
    if ($action === 'execute') {
        $input = json_decode(file_get_contents('php://input'), true);
        $reqId = (int)($input['id'] ?? 0);
        $executedBy = (int)($input['executed_by'] ?? $userId);

        if (!$reqId) err('Request ID required');
        if (!$executedBy) err('User must be logged in');

        // Fetch request
        $stmt = $pdo->prepare("SELECT * FROM deletion_requests WHERE id = ?");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) err('Request not found', 404);
        if ($req['status'] !== 'approved') err('Can only execute approved requests');

        // Only the requester can execute
        if ((int)$req['requested_by'] !== $executedBy) {
            err('Only the user who requested deletion can execute it');
        }

        $entityType = $req['entity_type'];
        $entityId = (int)$req['entity_id'];
        $reqVisitId   = isset($req['visit_id'])   ? (int)$req['visit_id']   : 0;
        $reqPatientId = isset($req['patient_id']) ? (int)$req['patient_id'] : 0;

        // Snapshot the entity data before deletion
        $entityData = null;
        try {
            if ($entityType === 'patient') {
                $snap = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
                $snap->execute([$entityId]);
                $entityData = $snap->fetch();
            } elseif ($entityType === 'lab_order') {
                $snap = $pdo->prepare("SELECT * FROM medical_orders WHERE id = ?");
                $snap->execute([$entityId]);
                $entityData = $snap->fetch();
            } elseif ($entityType === 'prescription') {
                $snap = $pdo->prepare("SELECT * FROM pharmacy_prescriptions WHERE id = ?");
                $snap->execute([$entityId]);
                $entityData = $snap->fetch();
                // Also snapshot items
                $items = $pdo->prepare("SELECT * FROM pharmacy_prescription_items WHERE prescription_id = ?");
                $items->execute([$entityId]);
                $entityData['items'] = $items->fetchAll();
            } elseif ($entityType === 'procedure') {
                $snap = $pdo->prepare("SELECT ii.*, i.invoice_no FROM invoice_items ii LEFT JOIN invoices i ON i.id=ii.invoice_id WHERE ii.id = ?");
                $snap->execute([$entityId]);
                $entityData = $snap->fetch();
            } elseif ($entityType === 'journal_entry') {
                $snap = $pdo->prepare("SELECT * FROM journal_entries WHERE id = ?");
                $snap->execute([$entityId]);
                $entityData = $snap->fetch();
                $lines = $pdo->prepare("SELECT jl.*, c.account_name, c.account_code FROM journal_entry_lines jl LEFT JOIN chart_of_accounts c ON c.id=jl.account_id WHERE jl.journal_entry_id = ?");
                $lines->execute([$entityId]);
                $entityData['lines'] = $lines->fetchAll();
            } elseif ($entityType === 'prescription_item') {
                $snap = $pdo->prepare("SELECT ppi.*, i.name AS item_name, pp.prescription_no, pp.visit_id FROM pharmacy_prescription_items ppi LEFT JOIN items i ON i.id=ppi.item_id LEFT JOIN pharmacy_prescriptions pp ON pp.id=ppi.prescription_id WHERE ppi.id = ?");
                $snap->execute([$entityId]);
                $entityData = $snap->fetch();
            }
        } catch (Exception $e) {}

        // Perform the actual deletion
        try {
            $pdo->beginTransaction();

            if ($entityType === 'patient') {
                // Check if patient has any records (visits, prescriptions, invoices, etc.)
                $hasRecords = false;
                $recordTypes = [];
                $chkVisits = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE patient_id = ?"); $chkVisits->execute([$entityId]);
                if ((int)$chkVisits->fetchColumn() > 0) { $hasRecords = true; $recordTypes[] = 'visits'; }
                $chkInvoices = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE patient_id = ?"); $chkInvoices->execute([$entityId]);
                if ((int)$chkInvoices->fetchColumn() > 0) { $hasRecords = true; $recordTypes[] = 'invoices'; }
                $chkRx = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_prescriptions WHERE patient_id = ?"); $chkRx->execute([$entityId]);
                if ((int)$chkRx->fetchColumn() > 0) { $hasRecords = true; $recordTypes[] = 'prescriptions'; }
                $chkOrders = $pdo->prepare("SELECT COUNT(*) FROM medical_orders WHERE patient_id = ?"); $chkOrders->execute([$entityId]);
                if ((int)$chkOrders->fetchColumn() > 0) { $hasRecords = true; $recordTypes[] = 'lab orders'; }

                if ($hasRecords) {
                    $pdo->rollBack();
                    // Mark request as rejected with reason
                    $pdo->prepare("UPDATE deletion_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE id=?")
                        ->execute([$executedBy, 'Cannot delete: patient has existing records (' . implode(', ', $recordTypes) . '). Patient data must be preserved for medical/legal compliance.', $reqId]);
                    err('This patient has existing records (' . implode(', ', $recordTypes) . ') and cannot be deleted. The request has been automatically rejected.');
                }

                // Only patients with no records can be deleted
                $pdo->prepare("DELETE FROM patient_insurance WHERE patient_id = ?")->execute([$entityId]);
                $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$entityId]);
            }

            if ($entityType === 'lab_order') {
                // Delete lab order from medical_orders
                // Snapshot BEFORE delete so we still have the data
                $labSnap = $pdo->prepare("SELECT id, description, visit_id FROM medical_orders WHERE id = ?");
                $labSnap->execute([$entityId]);
                $labRow = $labSnap->fetch();
                $labVisitId = $reqVisitId ?: (int)($labRow['visit_id'] ?? 0);

                $pdo->prepare("DELETE FROM medical_orders WHERE id = ?")->execute([$entityId]);

                // Also remove the matching invoice item for this lab order (scoped to visit)
                // Match by description + visit_id since medical_orders has no service_id
                if ($labVisitId && !empty($labRow['description'])) {
                    $invItemQ = $pdo->prepare("
                        SELECT ii.id, ii.invoice_id FROM invoice_items ii
                        JOIN invoices i ON i.id = ii.invoice_id
                        WHERE i.visit_id = ? AND ii.description LIKE ?
                          AND ii.category IN ('lab','radiology','lab_order')
                          AND i.status != 'paid'
                        LIMIT 1
                    ");
                    $invItemQ->execute([$labVisitId, '%' . $labRow['description'] . '%']);
                    $labInvItem = $invItemQ->fetch();
                    if ($labInvItem) {
                        $labInvoiceId = (int)$labInvItem['invoice_id'];
                        $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$labInvItem['id']]);
                        $remStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?");
                        $remStmt->execute([$labInvoiceId]);
                        $remCount = (int)$remStmt->fetchColumn();
                        if ($remCount === 0) {
                            $jeIds = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type='invoice' AND reference_id=?");
                            $jeIds->execute([$labInvoiceId]);
                            foreach ($jeIds->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
                                $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id=?")->execute([$jeId]);
                                $pdo->prepare("DELETE FROM journal_entries WHERE id=?")->execute([$jeId]);
                            }
                            $pdo->prepare("DELETE FROM payment_records WHERE invoice_id=?")->execute([$labInvoiceId]);
                            $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$labInvoiceId]);
                        } else {
                            // Recalculate invoice total
                            $newLabTotal = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = $labInvoiceId")->fetchColumn();
                            $pdo->prepare("UPDATE invoices SET total=?, subtotal=?, balance=?-paid_amount WHERE id=?")->execute([$newLabTotal, $newLabTotal, $newLabTotal, $labInvoiceId]);
                            // Update journal entry lines to exact new total
                            $jeLabStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type='invoice' AND reference_id=?");
                            $jeLabStmt->execute([$labInvoiceId]);
                            foreach ($jeLabStmt->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
                                $pdo->prepare("UPDATE journal_entry_lines SET debit = CASE WHEN debit > 0 THEN ? ELSE 0 END, credit = CASE WHEN credit > 0 THEN ? ELSE 0 END WHERE journal_entry_id = ?")->execute([$newLabTotal, $newLabTotal, $jeId]);
                            }
                        }
                    }
                }
            }

            if ($entityType === 'prescription') {
                // Snapshot visit_id from the prescription itself if not stored on request
                $rxSnap = $pdo->prepare("SELECT visit_id, patient_id FROM pharmacy_prescriptions WHERE id = ?");
                $rxSnap->execute([$entityId]);
                $rxRow = $rxSnap->fetch();
                $rxVisitId   = $reqVisitId   ?: (int)($rxRow['visit_id']   ?? 0);
                $rxPatientId = $reqPatientId ?: (int)($rxRow['patient_id'] ?? 0);

                // Delete prescription items then the prescription
                $pdo->prepare("DELETE FROM pharmacy_prescription_items WHERE prescription_id = ?")->execute([$entityId]);
                $pdo->prepare("DELETE FROM pharmacy_prescriptions WHERE id = ?")->execute([$entityId]);

                // Remove all pharmacy invoice items for this visit (unpaid) that belong to this prescription
                // We match by visit_id + category pharmacy/medication
                if ($rxVisitId) {
                    // Find all unpaid invoices for this visit with pharmacy items
                    $pharmInvStmt = $pdo->prepare("
                        SELECT DISTINCT i.id AS invoice_id FROM invoices i
                        JOIN invoice_items ii ON ii.invoice_id = i.id
                        WHERE i.visit_id = ? AND ii.category IN ('pharmacy','medication','drug')
                          AND i.status != 'paid'
                    ");
                    $pharmInvStmt->execute([$rxVisitId]);
                    $pharmInvoices = $pharmInvStmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($pharmInvoices as $pharmInvId) {
                        // Delete pharmacy items from this invoice
                        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ? AND category IN ('pharmacy','medication','drug')")->execute([$pharmInvId]);
                        // Check remaining items
                        $remStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?");
                        $remStmt->execute([$pharmInvId]);
                        $remCount = (int)$remStmt->fetchColumn();
                        if ($remCount === 0) {
                            $jeIds = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type='invoice' AND reference_id=?");
                            $jeIds->execute([$pharmInvId]);
                            foreach ($jeIds->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
                                $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id=?")->execute([$jeId]);
                                $pdo->prepare("DELETE FROM journal_entries WHERE id=?")->execute([$jeId]);
                            }
                            $pdo->prepare("DELETE FROM payment_records WHERE invoice_id=?")->execute([$pharmInvId]);
                            $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$pharmInvId]);
                        } else {
                            $newPharmTotal = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = $pharmInvId")->fetchColumn();
                            $pdo->prepare("UPDATE invoices SET total=?, subtotal=?, balance=?-paid_amount WHERE id=?")->execute([$newPharmTotal, $newPharmTotal, $newPharmTotal, $pharmInvId]);
                            // Update journal entry lines to exact new total
                            $jePhStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type='invoice' AND reference_id=?");
                            $jePhStmt->execute([$pharmInvId]);
                            foreach ($jePhStmt->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
                                $pdo->prepare("UPDATE journal_entry_lines SET debit = CASE WHEN debit > 0 THEN ? ELSE 0 END, credit = CASE WHEN credit > 0 THEN ? ELSE 0 END WHERE journal_entry_id = ?")->execute([$newPharmTotal, $newPharmTotal, $jeId]);
                            }
                        }
                    }
                }
            }

            if ($entityType === 'procedure') {
                // Procedure is an invoice_item — find its invoice, delete the item
                // Use visit_id/patient_id to confirm we're deleting the right record
                $itemQuery = "SELECT ii.*, i.status AS invoice_status, i.visit_id AS inv_visit_id, i.patient_id AS inv_patient_id
                              FROM invoice_items ii
                              JOIN invoices i ON i.id = ii.invoice_id
                              WHERE ii.id = ?";
                // Scope to visit if we have it, for extra safety
                if ($reqVisitId) {
                    $itemQuery .= " AND i.visit_id = " . (int)$reqVisitId;
                }
                $itemStmt = $pdo->prepare($itemQuery);
                $itemStmt->execute([$entityId]);
                $item = $itemStmt->fetch();
                if ($item) {
                    $invoiceId = $item['invoice_id'];
                    if ($item['invoice_status'] === 'paid') {
                        // Invoice already paid — keep item for audit trail, just mark request executed
                    } else {
                        // Delete the procedure invoice item
                        $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$entityId]);
                        // Check remaining items on this invoice
                        $remainStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?");
                        $remainStmt->execute([$invoiceId]);
                        $remaining = (int)$remainStmt->fetchColumn();
                        if ($remaining === 0) {
                            // No items left — delete journal entries, payment records, and invoice
                            $jeStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
                            $jeStmt->execute([(string)$invoiceId]);
                            $journalIds = $jeStmt->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($journalIds as $jeId) {
                                $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$jeId]);
                                $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$jeId]);
                            }
                            $pdo->prepare("DELETE FROM payment_records WHERE invoice_id = ?")->execute([$invoiceId]);
                            $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invoiceId]);
                        } else {
                            // Recalculate invoice total & balance
                            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = ?");
                            $sumStmt->execute([$invoiceId]);
                            $newTotal = (float)$sumStmt->fetchColumn();
                            $invStmt = $pdo->prepare("SELECT paid_amount, insurance_id, patient_id FROM invoices WHERE id = ?");
                            $invStmt->execute([$invoiceId]);
                            $inv = $invStmt->fetch();
                            $paidAmount = (float)($inv['paid_amount'] ?? 0);
                            $newBalance = max(0, $newTotal - $paidAmount);
                            $newStatus = $newBalance <= 0 && $newTotal > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'issued');
                            // Adjust for copayment if insurance
                            $insId = $inv['insurance_id'] ?? null;
                            if ($insId) {
                                $cpStmt = $pdo->prepare("SELECT ip.copayment_percentage FROM patient_insurance pi LEFT JOIN insurance_packages ip ON ip.id=pi.package_id WHERE pi.patient_id=? AND pi.company_id=? LIMIT 1");
                                $cpStmt->execute([$inv['patient_id'], $insId]);
                                $cpRow = $cpStmt->fetch();
                                if ($cpRow && $cpRow['copayment_percentage'] !== null) {
                                    $copayPct = (float)$cpRow['copayment_percentage'];
                                    if ($copayPct < 100) {
                                        $newBalance = max(0, round($newTotal * $copayPct / 100, 2) - $paidAmount);
                                        $newStatus = $newBalance <= 0 && $newTotal > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'issued');
                                    }
                                }
                            }
                            $pdo->prepare("UPDATE invoices SET subtotal=?, total=?, balance=?, status=? WHERE id=?")->execute([$newTotal, $newTotal, $newBalance, $newStatus, $invoiceId]);
                            // Update journal entry lines to exact new total
                            $jeStmt2 = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
                            $jeStmt2->execute([$invoiceId]);
                            foreach ($jeStmt2->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
                                // Set debit lines (receivable) to newTotal, credit lines (revenue) to newTotal
                                $pdo->prepare("UPDATE journal_entry_lines SET debit = CASE WHEN debit > 0 THEN ? ELSE 0 END, credit = CASE WHEN credit > 0 THEN ? ELSE 0 END WHERE journal_entry_id = ?")->execute([$newTotal, $newTotal, $jeId]);
                            }
                        }
                    }
                }
            }

            if ($entityType === 'invoice') {
                $invoiceStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
                $invoiceStmt->execute([$entityId]);
                $invoice = $invoiceStmt->fetch();
                if ($invoice) {
                    $entityData = $invoice;
                    $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
                    $itemsStmt->execute([$entityId]);
                    $entityData['items'] = $itemsStmt->fetchAll();

                    $jeStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
                    $jeStmt->execute([$entityId]);
                    $journalIds = $jeStmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($journalIds as $jeId) {
                        $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$jeId]);
                        $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$jeId]);
                    }

                    $pdo->prepare("DELETE FROM payment_records WHERE invoice_id = ?")->execute([$entityId]);
                    $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$entityId]);
                    $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$entityId]);
                }
            }

            if ($entityType === 'journal_entry') {
                // Delete journal entry and its lines
                $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$entityId]);
                $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$entityId]);
            }

            if ($entityType === 'prescription_item') {
                // Delete single item from a prescription
                $itemStmt = $pdo->prepare("SELECT ppi.*, pp.visit_id, pp.patient_id FROM pharmacy_prescription_items ppi LEFT JOIN pharmacy_prescriptions pp ON pp.id=ppi.prescription_id WHERE ppi.id = ?");
                $itemStmt->execute([$entityId]);
                $itemRow = $itemStmt->fetch();
                if ($itemRow) {
                    $prescriptionId = (int)$itemRow['prescription_id'];
                    $rxVisitId = (int)($itemRow['visit_id'] ?? 0);
                    $rxItemId = (int)($itemRow['item_id'] ?? 0);
                    
                    // Delete the single prescription item
                    $pdo->prepare("DELETE FROM pharmacy_prescription_items WHERE id = ?")->execute([$entityId]);
                    
                    // Check if prescription has remaining items
                    $remainStmt = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_prescription_items WHERE prescription_id = ?");
                    $remainStmt->execute([$prescriptionId]);
                    $remaining = (int)$remainStmt->fetchColumn();
                    if ($remaining === 0) {
                        // No items left — delete the prescription itself
                        $pdo->prepare("DELETE FROM pharmacy_prescriptions WHERE id = ?")->execute([$prescriptionId]);
                    }
                    
                    // Remove related invoice item (match by item_id/service_id + visit_id + category)
                    // BUT skip if invoice is already paid
                    if ($rxVisitId && $rxItemId) {
                        $invItemStmt = $pdo->prepare("
                            SELECT ii.id, ii.invoice_id, ii.total 
                            FROM invoice_items ii 
                            JOIN invoices i ON i.id = ii.invoice_id 
                            WHERE i.visit_id = ? AND ii.service_id = ? 
                            AND ii.category IN ('medication','pharmacy','drug')
                            AND i.status != 'paid'
                            LIMIT 1
                        ");
                        $invItemStmt->execute([$rxVisitId, $rxItemId]);
                        $invItem = $invItemStmt->fetch();
                        
                        if ($invItem) {
                            $invoiceId = (int)$invItem['invoice_id'];
                            
                            // Delete the invoice item
                            $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$invItem['id']]);
                            
                            // Check if invoice has remaining items
                            $invRemainStmt = $pdo->prepare("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?");
                            $invRemainStmt->execute([$invoiceId]);
                            $invRemaining = (int)$invRemainStmt->fetchColumn();
                            
                            if ($invRemaining === 0) {
                                // No items left — delete journal entries + invoice
                                $jeStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
                                $jeStmt->execute([$invoiceId]);
                                $journalIds = $jeStmt->fetchAll(PDO::FETCH_COLUMN);
                                foreach ($journalIds as $jeId) {
                                    $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?")->execute([$jeId]);
                                    $pdo->prepare("DELETE FROM journal_entries WHERE id = ?")->execute([$jeId]);
                                }
                                $pdo->prepare("DELETE FROM payment_records WHERE invoice_id = ?")->execute([$invoiceId]);
                                $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$invoiceId]);
                            } else {
                                // Recalculate invoice totals
                                $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id = ?");
                                $sumStmt->execute([$invoiceId]);
                                $newTotal = (float)$sumStmt->fetchColumn();
                                
                                $invStmt = $pdo->prepare("SELECT paid_amount, insurance_id, patient_id FROM invoices WHERE id = ?");
                                $invStmt->execute([$invoiceId]);
                                $inv = $invStmt->fetch();
                                $paidAmount = (float)($inv['paid_amount'] ?? 0);
                                $newBalance = max(0, $newTotal - $paidAmount);
                                $newStatus = $newBalance <= 0 && $newTotal > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'issued');
                                
                                // If insurance, apply copayment
                                $insId = $inv['insurance_id'] ?? null;
                                if ($insId) {
                                    $cpStmt = $pdo->prepare("SELECT ip.copayment_percentage FROM patient_insurance pi LEFT JOIN insurance_packages ip ON ip.id=pi.package_id WHERE pi.patient_id=? AND pi.company_id=? LIMIT 1");
                                    $cpStmt->execute([$inv['patient_id'], $insId]);
                                    $cpRow = $cpStmt->fetch();
                                    if ($cpRow && $cpRow['copayment_percentage'] !== null) {
                                        $copayPct = (float)$cpRow['copayment_percentage'];
                                        if ($copayPct < 100) {
                                            $newBalance = max(0, round($newTotal * $copayPct / 100, 2) - $paidAmount);
                                            $newStatus = $newBalance <= 0 && $newTotal > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'issued');
                                        }
                                    }
                                }
                                
                                $pdo->prepare("UPDATE invoices SET subtotal=?, total=?, balance=?, status=? WHERE id=?")->execute([$newTotal, $newTotal, $newBalance, $newStatus, $invoiceId]);
                                
                                // Update journal entry lines to exact new total
                                $jeStmt2 = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
                                $jeStmt2->execute([$invoiceId]);
                                foreach ($jeStmt2->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
                                    $pdo->prepare("UPDATE journal_entry_lines SET debit = CASE WHEN debit > 0 THEN ? ELSE 0 END, credit = CASE WHEN credit > 0 THEN ? ELSE 0 END WHERE journal_entry_id = ?")->execute([$newTotal, $newTotal, $jeId]);
                                }
                            }
                        }
                    }
                }
            }

            // Log the deletion
            $logStmt = $pdo->prepare("INSERT INTO deletion_audit_log (deletion_request_id, entity_type, entity_id, entity_data, deleted_by, deleted_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $logStmt->execute([$reqId, $entityType, $entityId, json_encode($entityData), $executedBy]);

            // Mark request as executed
            $pdo->prepare("UPDATE deletion_requests SET status='executed', executed_by=?, executed_at=NOW() WHERE id=?")->execute([$executedBy, $reqId]);

            $pdo->commit();
            ok(null, 'Record deleted successfully. Audit log created.');
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Deletion failed: ' . $e->getMessage());
        }
    }
}

// ===== DELETE =====
if ($method === 'DELETE') {
    // Cancel a pending request
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM deletion_requests WHERE id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) err('Request not found', 404);
        if ($req['status'] !== 'pending') err('Can only cancel pending requests');
        $pdo->prepare("DELETE FROM deletion_requests WHERE id = ?")->execute([$id]);
        ok(null, 'Request cancelled');
    }
}

err('Invalid request', 400);
