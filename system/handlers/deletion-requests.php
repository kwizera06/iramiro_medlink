<?php
// handlers/deletion-requests.php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/billing-calc.php';

function jsonResp($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success'){jsonResp(['success'=>true,'message'=>$m,'data'=>$d]);}
function err($m,$s=400){jsonResp(['success'=>false,'message'=>$m],$s);}

// Finds the invoice line that bills a clinical order (lab orders and drugs are linked by description, sometimes service_id)
function findInvoiceItem(PDO $pdo, int $visitId, ?int $serviceId, string $name, bool $pharmacy): int {
    if (!$visitId || $name === '') return 0;
    $cat = $pharmacy ? "ii.category IN ('pharmacy','medication','drug')" : "ii.category NOT IN ('pharmacy','medication','drug')";
    $stmt = $pdo->prepare("SELECT ii.id FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
        WHERE i.visit_id = ? AND i.status != 'cancelled' AND $cat AND ((? IS NOT NULL AND ii.service_id = ?) OR ii.description LIKE ?)
        ORDER BY (ii.service_id <=> ?) DESC, ii.id DESC LIMIT 1");
    $stmt->execute([$visitId, $serviceId, $serviceId, '%' . $name . '%', $serviceId]);
    return (int)$stmt->fetchColumn();
}

// Clinical records are removed; their billing is adjusted through billing-calc.php so invoices,
// balances and journals stay consistent. Financial records are never erased — they are reversed/cancelled.
function performDeletion(PDO $pdo, string $type, int $id): void {
    if ($type === 'lab_order') {
        $mo = $pdo->query("SELECT visit_id, service_id, description FROM medical_orders WHERE id = $id")->fetch();
        if (!$mo) return;
        $pdo->prepare("DELETE FROM medical_orders WHERE id = ?")->execute([$id]);
        if ($itemId = findInvoiceItem($pdo, (int)$mo['visit_id'], $mo['service_id'] ? (int)$mo['service_id'] : null, trim($mo['description']), false)) removeInvoiceItem($pdo, $itemId);
    } elseif ($type === 'prescription' || $type === 'prescription_item') {
        $where = $type === 'prescription' ? 'ppi.prescription_id' : 'ppi.id';
        $rows = $pdo->query("SELECT ppi.id, ppi.prescription_id, ppi.item_id, it.name, pp.visit_id FROM pharmacy_prescription_items ppi
            JOIN pharmacy_prescriptions pp ON pp.id = ppi.prescription_id LEFT JOIN items it ON it.id = ppi.item_id WHERE $where = $id")->fetchAll();
        foreach ($rows as $r) {
            $pdo->prepare("DELETE FROM pharmacy_prescription_items WHERE id = ?")->execute([$r['id']]);
            if ($itemId = findInvoiceItem($pdo, (int)$r['visit_id'], (int)$r['item_id'], trim((string)$r['name']), true)) removeInvoiceItem($pdo, $itemId);
        }
        $rxId = $type === 'prescription' ? $id : (int)($rows[0]['prescription_id'] ?? 0);
        if ($rxId && !(int)$pdo->query("SELECT COUNT(*) FROM pharmacy_prescription_items WHERE prescription_id = $rxId")->fetchColumn()) {
            $pdo->prepare("DELETE FROM pharmacy_prescriptions WHERE id = ?")->execute([$rxId]);
        }
    } elseif ($type === 'procedure') {
        removeInvoiceItem($pdo, $id); // a procedure is an invoice line
    } elseif ($type === 'invoice') {
        cancelInvoice($pdo, $id, 'Invoice deleted');
    } elseif ($type === 'journal_entry') {
        reverseJournalEntry($pdo, $id, 'reversal', (string)$id, 'Deleted via deletion request');
    }
}

// Deletes the entity behind an approved request, logs it and marks the request executed (always exits via ok/err)
function executeDeletion(PDO $pdo, array $req, int $executedBy): void {
    $reqId = (int)$req['id'];
    $entityType = $req['entity_type'];
    $entityId = (int)$req['entity_id'];
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

            if ($hasRecords && isUserAdmin($pdo, $executedBy)) {
                // Admin full delete: reverse each invoice's journal so the books net to zero, then clear the
                // tables that don't cascade; the patients FK cascades the rest (visits, invoices, payments, orders...)
                $inv = $pdo->prepare("SELECT id FROM invoices WHERE patient_id = ?");
                $inv->execute([$entityId]);
                foreach ($inv->fetchAll(PDO::FETCH_COLUMN) as $invId) cancelInvoice($pdo, (int)$invId, 'Patient deleted');
                $pdo->prepare("DELETE FROM pharmacy_dispensing_log WHERE patient_id = ?")->execute([$entityId]);
                $pdo->prepare("DELETE FROM pharmacy_prescriptions WHERE patient_id = ?")->execute([$entityId]);
            } elseif ($hasRecords) {
                $pdo->rollBack();
                // Mark request as rejected with reason
                $pdo->prepare("UPDATE deletion_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_notes=? WHERE id=?")
                    ->execute([$executedBy, 'Cannot delete: patient has existing records (' . implode(', ', $recordTypes) . '). Patient data must be preserved for medical/legal compliance.', $reqId]);
                err('This patient has existing records (' . implode(', ', $recordTypes) . ') and cannot be deleted. The request has been automatically rejected.');
            }

            // Non-admins can only delete patients with no records
            $pdo->prepare("DELETE FROM patient_insurance WHERE patient_id = ?")->execute([$entityId]);
            $pdo->prepare("DELETE FROM patients WHERE id = ?")->execute([$entityId]);
        }

        performDeletion($pdo, $entityType, $entityId);

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

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Read JSON body once (php://input can only be read once)
$_rawBody = '';
$_jsonBody = null;
if ($method === 'POST' || $method === 'PUT') {
    $_rawBody = file_get_contents('php://input');
    if ($_rawBody) {
        $_jsonBody = json_decode($_rawBody, true);
    }
}

// Also allow action in JSON body for POST requests
if ($method === 'POST' && !$action && is_array($_jsonBody) && !empty($_jsonBody['action'])) {
    $action = $_jsonBody['action'];
}

if (session_status() === PHP_SESSION_NONE) session_start();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Every write is attributed to the session user — never to an id sent by the client
if ($method !== 'GET' && !$userId) err('User must be logged in', 401);

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
        $requestedBy = $userId;
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
        $input = $_jsonBody ?? [];
        $reqId = (int)($input['id'] ?? 0);
        $status = $input['status'] ?? ''; // approved or rejected
        $reviewNotes = $input['review_notes'] ?? '';
        $reviewedBy = $userId;
        if (!isUserAdmin($pdo, $userId)) {
            $rr = $pdo->prepare("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug IN ('managing_director','clinical_director')");
            $rr->execute([$userId]);
            if (!(int)$rr->fetchColumn()) err('Only an administrator, managing director or clinical director can approve or reject deletion requests', 403);
        }

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
        // Approval performs the deletion right away; on failure the request stays approved and can be retried
        if ($status === 'approved') executeDeletion($pdo, $req, $reviewedBy);
        ok(null, 'Request ' . $status);
    }

    // Execute deletion (only if approved)
    if ($action === 'execute') {
        $input = $_jsonBody ?? [];
        $reqId = (int)($input['id'] ?? 0);

        if (!$reqId) err('Request ID required');

        // Fetch request
        $stmt = $pdo->prepare("SELECT * FROM deletion_requests WHERE id = ?");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) err('Request not found', 404);
        if ($req['status'] !== 'approved') err('Can only execute approved requests');

        // Only the requester or an administrator can execute
        if ((int)$req['requested_by'] !== $userId && !isUserAdmin($pdo, $userId)) {
            err('Only the user who requested deletion or an administrator can execute it');
        }

        executeDeletion($pdo, $req, $userId);
    }

    // ===== DIRECT DELETE (no approval needed — only for open/in_progress visits) =====
    if ($action === 'direct_delete') {
        $input = $_jsonBody ?? [];
        $entityType  = $input['entity_type']  ?? '';
        $entityId    = (int)($input['entity_id']    ?? 0);
        $visitId     = (int)($input['visit_id']     ?? 0);
        $patientId   = (int)($input['patient_id']   ?? 0);
        $entityName  = $input['entity_name']  ?? '';
        $executedBy  = $userId;

        if (!$entityType) err('entity_type is required');
        if (!$entityId)   err('entity_id is required');
        if (!$executedBy) err('User must be logged in');

        // Always resolve visit_id from the entity itself — a client-supplied visit_id could point at a different, open visit
        $resolvedVisitId = 0;
        if (!$resolvedVisitId) {
            try {
                if ($entityType === 'procedure') {
                    $s = $pdo->prepare("SELECT i.visit_id FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE ii.id=?");
                    $s->execute([$entityId]); $r = $s->fetch();
                    if ($r) $resolvedVisitId = (int)$r['visit_id'];
                } elseif ($entityType === 'lab_order') {
                    $s = $pdo->prepare("SELECT visit_id FROM medical_orders WHERE id=?");
                    $s->execute([$entityId]); $r = $s->fetch();
                    if ($r) $resolvedVisitId = (int)$r['visit_id'];
                } elseif ($entityType === 'prescription') {
                    $s = $pdo->prepare("SELECT visit_id FROM pharmacy_prescriptions WHERE id=?");
                    $s->execute([$entityId]); $r = $s->fetch();
                    if ($r) $resolvedVisitId = (int)$r['visit_id'];
                } elseif ($entityType === 'prescription_item') {
                    $s = $pdo->prepare("SELECT pp.visit_id FROM pharmacy_prescription_items ppi JOIN pharmacy_prescriptions pp ON pp.id=ppi.prescription_id WHERE ppi.id=?");
                    $s->execute([$entityId]); $r = $s->fetch();
                    if ($r) $resolvedVisitId = (int)$r['visit_id'];
                } elseif ($entityType === 'invoice') {
                    $s = $pdo->prepare("SELECT visit_id FROM invoices WHERE id=?");
                    $s->execute([$entityId]); $r = $s->fetch();
                    if ($r) $resolvedVisitId = (int)$r['visit_id'];
                }
            } catch (Exception $e) {}
        }

        // Verify the visit is open or in_progress
        if ($resolvedVisitId) {
            $vstmt = $pdo->prepare("SELECT status FROM visits WHERE id=?");
            $vstmt->execute([$resolvedVisitId]);
            $visit = $vstmt->fetch();
            if (!$visit || !in_array($visit['status'], ['open', 'in_progress'])) {
                err('Direct deletion is only allowed for open or in-progress visits. Please submit a deletion request instead.');
            }
        } else {
            err('Could not determine visit status. Please submit a deletion request instead.');
        }

        // Deleting a whole invoice that already has money posted against it needs an administrator
        if ($entityType === 'invoice' && !isUserAdmin($pdo, $userId)) {
            $paidStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_records WHERE invoice_id=? AND status IN ('completed','written_off')");
            $paidStmt->execute([$entityId]);
            if ((int)$paidStmt->fetchColumn() > 0) err('This invoice has recorded payments. Only an administrator can delete it.', 403);
        }

        // Snapshot entity before deletion (for audit)
        $entityData = null;
        try {
            if ($entityType === 'procedure') {
                $snap = $pdo->prepare("SELECT ii.*, i.invoice_no FROM invoice_items ii LEFT JOIN invoices i ON i.id=ii.invoice_id WHERE ii.id=?");
                $snap->execute([$entityId]); $entityData = $snap->fetch();
            } elseif ($entityType === 'lab_order') {
                $snap = $pdo->prepare("SELECT * FROM medical_orders WHERE id=?");
                $snap->execute([$entityId]); $entityData = $snap->fetch();
            } elseif ($entityType === 'prescription') {
                $snap = $pdo->prepare("SELECT * FROM pharmacy_prescriptions WHERE id=?");
                $snap->execute([$entityId]); $entityData = $snap->fetch();
                $items = $pdo->prepare("SELECT * FROM pharmacy_prescription_items WHERE prescription_id=?");
                $items->execute([$entityId]); $entityData['items'] = $items->fetchAll();
            } elseif ($entityType === 'prescription_item') {
                $snap = $pdo->prepare("SELECT ppi.*, i.name AS item_name, pp.prescription_no, pp.visit_id FROM pharmacy_prescription_items ppi LEFT JOIN items i ON i.id=ppi.item_id LEFT JOIN pharmacy_prescriptions pp ON pp.id=ppi.prescription_id WHERE ppi.id=?");
                $snap->execute([$entityId]); $entityData = $snap->fetch();
            } elseif ($entityType === 'invoice') {
                $snap = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
                $snap->execute([$entityId]); $entityData = $snap->fetch();
                $items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=?");
                $items->execute([$entityId]); $entityData['items'] = $items->fetchAll();
            }
        } catch (Exception $e) {}

        try {
            $pdo->beginTransaction();

            performDeletion($pdo, $entityType, $entityId);

            // Audit log (no deletion_request row needed, use request_id=0)
            $logStmt = $pdo->prepare("INSERT INTO deletion_audit_log (deletion_request_id, entity_type, entity_id, entity_data, deleted_by, deleted_at) VALUES (0, ?, ?, ?, ?, NOW())");
            $logStmt->execute([$entityType, $entityId, json_encode($entityData), $executedBy]);

            $pdo->commit();
            ok(null, 'Deleted successfully.');
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
        if ((int)$req['requested_by'] !== $userId && !isUserAdmin($pdo, $userId)) err('Only the requester or an administrator can cancel this request', 403);
        $pdo->prepare("DELETE FROM deletion_requests WHERE id = ?")->execute([$id]);
        ok(null, 'Request cancelled');
    }
}

err('Invalid request', 400);
