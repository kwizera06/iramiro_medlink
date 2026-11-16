<?php
// handlers/pharmacy/pharmacy.php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (!isset($pdo)) {
    require_once __DIR__ . '/../../includes/database.php';
}
global $pdo;
require_once __DIR__ . '/../../includes/billing-calc.php';
$sessionUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : (isset($_SESSION['logged_user_id']) ? (int)$_SESSION['logged_user_id'] : null));

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$endpoint = $_GET['endpoint'] ?? ($endpoint ?? '');
// Determine which sub-entity to handle from the endpoint
$entity = str_replace('pharmacy/', '', $endpoint);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$input = json_decode(file_get_contents('php://input'), true) ?? [];
// The logged-in user is who did it; the browser-sent id is only a fallback for sessionless callers
$userId = $sessionUserId ?: (!empty($input['user_id']) ? (int)$input['user_id'] : (!empty($input['created_by']) ? (int)$input['created_by'] : null));
$pharmacyStoreId = 1005; // Clinic Store - the store the pharmacy dispenses and sells from (also its COA account id)

if (!function_exists('success')) {
    function success($data = null, $msg = 'Success', $status = 200) {
        http_response_code($status);
        echo json_encode(['success' => true, 'message' => $msg, 'data' => $data]);
        exit();
    }
}
if (!function_exists('error')) {
    function error($msg, $status = 400) {
        http_response_code($status);
        echo json_encode(['success' => false, 'message' => $msg]);
        exit();
    }
}

// ====================================================
// 1. ITEMS (medication items from items table)
// ====================================================
if ($entity === 'items') {
    if ($method === 'GET') {
        $isMed = isset($_GET['is_medication']) ? (int)$_GET['is_medication'] : null;
        $search = $_GET['search'] ?? '';
        $limit = min(500, max(1, (int)($_GET['limit'] ?? 100)));
        
        $where = ['is_active = 1']; $params = [];
        if ($isMed !== null) { $where[] = 'is_medication = ?'; $params[] = $isMed; }
        if ($search) { $where[] = '(name LIKE ? OR generic_name LIKE ? OR item_code LIKE ?)'; $s = "%$search%"; $params[] = $s; $params[] = $s; $params[] = $s; }
        $ws = 'WHERE ' . implode(' AND ', $where);
        
        $st = $pdo->prepare("SELECT id, item_code, name, generic_name, is_medication, is_controlled, selling_price, unit_price FROM items $ws ORDER BY name ASC LIMIT $limit");
        $st->execute($params);
        success($st->fetchAll());
    }
}

// ====================================================
// 2. STOCK REQUESTS (pharmacy requests items from central store)
// ====================================================
if ($entity === 'stock-request') {
    // GET - list stock requests
    if ($method === 'GET') {
        if ($id > 0) {
            // Single request with items
            $req = $pdo->prepare("SELECT * FROM pharmacy_stock_requests WHERE id = ?");
            $req->execute([$id]);
            $r = $req->fetch();
            if (!$r) error('Request not found', 404);
            $items = $pdo->prepare("
                SELECT ri.*, i.name, i.item_code, i.generic_name,
                       COALESCE(sb.selling_price, 0) AS selling_price
                FROM pharmacy_stock_request_items ri
                JOIN items i ON i.id = ri.item_id
                LEFT JOIN stock_batches sb ON sb.item_id = ri.item_id AND sb.store_id = ? AND sb.is_active = 1
                WHERE ri.request_id = ?
                GROUP BY ri.id
            ");
            $items->execute([$pharmacyStoreId, $id]);
            $r['items'] = $items->fetchAll();
            $r['requested_by_name'] = get_user_name($pdo, $r['requested_by']);
            $r['approved_by_name'] = $r['approved_by'] ? get_user_name($pdo, $r['approved_by']) : null;
            success($r);
        }
        // List requests
        $status = $_GET['status'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page-1)*$limit;
        
        $where = []; $params = [];
        if ($status) { $where[] = "sr.status=?"; $params[] = $status; }
        $ws = $where ? 'WHERE '.implode(' AND ', $where) : '';
        
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_stock_requests sr $ws");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        
        $st = $pdo->prepare("
            SELECT sr.*, u.first_name, u.last_name,
                   (SELECT COUNT(*) FROM pharmacy_stock_request_items WHERE request_id = sr.id) AS item_count
            FROM pharmacy_stock_requests sr
            LEFT JOIN users u ON u.id = sr.requested_by
            $ws
            ORDER BY sr.created_at DESC LIMIT $limit OFFSET $offset
        ");
        $st->execute($params);
        $rows = $st->fetchAll();
        
        success([
            'requests' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int)ceil($total/max($limit,1))
        ]);
    }
    
    // POST - create new stock request
    if ($method === 'POST') {
        $reqNo = 'SRQ-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
        $notes = trim($input['notes'] ?? '');
        $priority = $input['priority'] ?? 'routine';
        $userId = (int)($input['user_id'] ?? 0);
        
        $st = $pdo->prepare("INSERT INTO pharmacy_stock_requests (request_number, requested_by, request_date, priority, notes, status) VALUES (?, ?, CURDATE(), ?, ?, 'pending')");
        $st->execute([$reqNo, $userId, $priority, $notes]);
        $reqId = $pdo->lastInsertId();
        
        // Insert items
        $items = $input['items'] ?? [];
        if (empty($items)) error('At least one item is required');
        
        $itemSt = $pdo->prepare("INSERT INTO pharmacy_stock_request_items (request_id, item_id, quantity_requested, notes) VALUES (?, ?, ?, ?)");
        foreach ($items as $it) {
            $iid = (int)($it['item_id'] ?? 0);
            $qty = (float)($it['quantity'] ?? 0);
            $note = trim($it['notes'] ?? '');
            if ($iid > 0 && $qty > 0) $itemSt->execute([$reqId, $iid, $qty, $note]);
        }
        
        success(['id' => $reqId, 'request_number' => $reqNo], 'Stock request created');
    }
    
    // PUT - update status (approve/cancel)
    if ($method === 'PUT') {
        if (!$id) error('Request ID required');
        $status = $input['status'] ?? '';
        $userId = (int)($input['user_id'] ?? 0);
        if (!in_array($status, ['approved', 'cancelled', 'fulfilled'])) error('Invalid status');
        
        if ($status === 'approved') {
            $st = $pdo->prepare("UPDATE pharmacy_stock_requests SET status=?, approved_by=?, approved_at=NOW() WHERE id=?");
            $st->execute([$status, $userId, $id]);
            $pdo->prepare("UPDATE pharmacy_stock_request_items SET quantity_approved = quantity_requested WHERE request_id = ?")->execute([$id]);
        } else {
            $st = $pdo->prepare("UPDATE pharmacy_stock_requests SET status=? WHERE id=?");
            $st->execute([$status, $id]);
        }
        success(['id' => $id, 'status' => $status], 'Request updated');
    }
}

function get_user_name($pdo, $userId) {
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];
    $st = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id=?");
    $st->execute([$userId]);
    $r = $st->fetch();
    $cache[$userId] = $r ? $r['first_name'].' '.$r['last_name'] : 'Unknown';
    return $cache[$userId];
}

// ====================================================
// 3. STOCK RECEIVE (receive goods from central store)
// ====================================================
if ($entity === 'stock-receive') {
    if ($method === 'GET') {
        if ($id > 0) {
            $rec = $pdo->prepare("SELECT * FROM pharmacy_goods_receipts WHERE id = ?");
            $rec->execute([$id]); $r = $rec->fetch();
            if (!$r) error('Receipt not found', 404);
            $items = $pdo->prepare("
                SELECT ri.*, i.name, i.item_code, i.generic_name
                FROM pharmacy_goods_receipt_items ri
                JOIN items i ON i.id = ri.item_id
                WHERE ri.receipt_id = ?
            ");
            $items->execute([$id]);
            $r['items'] = $items->fetchAll();
            $r['received_by_name'] = get_user_name($pdo, $r['received_by']);
            success($r);
        }
        // Pending stock transfers from central store that can be received
        $pendingTransfers = $pdo->prepare("
            SELECT st.*, u.first_name, u.last_name,
                   (SELECT COUNT(*) FROM stock_transfer_items WHERE transfer_id = st.id) AS item_count
            FROM stock_transfers st
            LEFT JOIN users u ON u.id = st.created_by
            WHERE st.to_store_id = ? AND st.status IN ('approved','in_transit')
            ORDER BY st.created_at DESC LIMIT 50
        ");
        $pendingTransfers->execute([$pharmacyStoreId]);
        
        // Completed receipts
        $receipts = $pdo->prepare("
            SELECT gr.*, u.first_name, u.last_name
            FROM pharmacy_goods_receipts gr
            LEFT JOIN users u ON u.id = gr.received_by
            ORDER BY gr.created_at DESC LIMIT 50
        ");
        $receipts->execute();
        
        success([
            'pending_transfers' => $pendingTransfers->fetchAll(),
            'receipts' => $receipts->fetchAll()
        ]);
    }
    
    if ($method === 'POST') {
        $receiptNo = 'GRN-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
        $transferId = (int)($input['transfer_id'] ?? 0);
        $receivedBy = (int)($input['user_id'] ?? 0);
        $source = trim($input['source'] ?? 'central_store');
        $deliveryNote = trim($input['delivery_note'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $items = $input['items'] ?? [];
        
        if (empty($items) && !$transferId) error('Items or transfer reference required');
        
        // If items are empty and transferId is provided, load items and batch/expiry from transfer
        if (empty($items) && $transferId > 0) {
            $sti = $pdo->prepare("
                SELECT sti.*, sb.batch_number, sb.expiry_date, sb.unit_cost, sb.selling_price
                FROM stock_transfer_items sti
                LEFT JOIN stock_batches sb ON sb.id = sti.batch_id
                WHERE sti.transfer_id = ?
            ");
            $sti->execute([$transferId]);
            $tItems = $sti->fetchAll();
            foreach ($tItems as $ti) {
                $items[] = [
                    'item_id' => $ti['item_id'],
                    'batch_number' => $ti['batch_number'] ?? '',
                    'expiry_date' => $ti['expiry_date'] ?? null,
                    'quantity_received' => $ti['quantity'],
                    'quantity_accepted' => $ti['quantity'],
                    'quantity_rejected' => 0,
                    'unit_cost' => $ti['unit_cost'] ?? 0,
                    'selling_price' => $ti['selling_price'] ?? 0,
                    'notes' => $ti['notes'] ?? ''
                ];
            }
        }
        
        // Create receipt record
        $st = $pdo->prepare("INSERT INTO pharmacy_goods_receipts (receipt_number, request_id, received_by, receipt_date, source, delivery_note, notes, status) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, 'completed')");
        $st->execute([$receiptNo, null, $receivedBy, $source, $deliveryNote, $notes]);
        $receiptId = $pdo->lastInsertId();
        
        $itemSt = $pdo->prepare("INSERT INTO pharmacy_goods_receipt_items (receipt_id, item_id, batch_number, expiry_date, quantity_received, quantity_accepted, quantity_rejected, unit_cost, selling_price, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $batchSt = $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
        $movementSt = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by) VALUES (?, NULL, NULL, ?, 'purchase_receipt', 'pharmacy_receipt', ?, ?, ?, ?, ?)");
        
        foreach ($items as $it) {
            $iid = (int)($it['item_id'] ?? 0);
            $batchNo = trim($it['batch_number'] ?? '');
            $expiry = $it['expiry_date'] ?? null;
            $qtyReceived = (float)($it['quantity_received'] ?? 0);
            $qtyAccepted = (float)($it['quantity_accepted'] ?? $qtyReceived);
            $qtyRejected = (float)($it['quantity_rejected'] ?? 0);
            $unitCost = (float)($it['unit_cost'] ?? 0);
            $sellPrice = (float)($it['selling_price'] ?? 0);
            $note = trim($it['notes'] ?? '');
            
            $itemSt->execute([$receiptId, $iid, $batchNo, $expiry, $qtyReceived, $qtyAccepted, $qtyRejected, $unitCost, $sellPrice, $note]);
            
            // Update pharmacy stock batches
            if ($qtyAccepted > 0) {
                $batchSt->execute([$iid, $pharmacyStoreId, $batchNo, $qtyAccepted, $unitCost, $sellPrice, $expiry, $receivedBy]);
                $batchId = $pdo->lastInsertId();
                if ($batchId === 0) {
                    // Already existed - get the id
                    $bq = $pdo->prepare("SELECT id FROM stock_batches WHERE item_id=? AND store_id=? AND batch_number=? AND is_active=1 LIMIT 1");
                    $bq->execute([$iid, $pharmacyStoreId, $batchNo]);
                    $b = $bq->fetch();
                    $batchId = $b ? $b['id'] : 0;
                }
                $movementSt->execute([$iid, $pharmacyStoreId, $receiptId, $qtyAccepted, $unitCost, "Received from $source", $receivedBy]);
            }
        }
        
        // Update transfer status if coming from a transfer
        if ($transferId > 0) {
            $pdo->prepare("UPDATE stock_transfers SET status='received', received_by=?, notes=CONCAT(COALESCE(notes,''),' | Received via: ',?) WHERE id=?")->execute([$receivedBy, $receiptNo, $transferId]);
        }
        
        success(['id' => $receiptId, 'receipt_number' => $receiptNo], 'Stock received successfully');
    }
}

// ====================================================
// 4. STOCK MOVEMENTS (pharmacy stock in/out history)
// ====================================================
if ($entity === 'stock-movements') {
    if ($method === 'GET') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page-1)*$limit;
        $typeFilter = $_GET['type'] ?? ''; // in/out
        
        $where = ["(sm.to_store_id = ? OR sm.from_store_id = ?)"];
        $params = [$pharmacyStoreId, $pharmacyStoreId];
        
        if ($typeFilter === 'in') { $where[] = "sm.to_store_id = ?"; $params[] = $pharmacyStoreId; }
        if ($typeFilter === 'out') { $where[] = "sm.from_store_id = ?"; $params[] = $pharmacyStoreId; }
        $ws = 'WHERE '.implode(' AND ', $where);
        
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm $ws");
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        
        $st = $pdo->prepare("
            SELECT sm.*, i.name AS item_name, i.item_code,
                   COALESCE(u.first_name, 'System') AS user_name
            FROM stock_movements sm
            JOIN items i ON i.id = sm.item_id
            LEFT JOIN users u ON u.id = sm.created_by
            $ws
            ORDER BY sm.created_at DESC LIMIT $limit OFFSET $offset
        ");
        $st->execute($params);
        
        // Summary
        $sum = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN sm.to_store_id = ? THEN sm.quantity ELSE 0 END) AS total_in,
                SUM(CASE WHEN sm.from_store_id = ? THEN sm.quantity ELSE 0 END) AS total_out,
                COUNT(*) AS total_movements
            FROM stock_movements sm WHERE sm.to_store_id = ? OR sm.from_store_id = ?
        ");
        $sum->execute([$pharmacyStoreId, $pharmacyStoreId, $pharmacyStoreId, $pharmacyStoreId]);
        
        success([
            'movements' => $st->fetchAll(),
            'summary' => $sum->fetch(),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    }
}

// ====================================================
// 5. PRESCRIPTIONS (view doctor prescriptions for patients)
// ====================================================
if ($entity === 'prescriptions') {
    if ($method === 'GET') {
        if ($id > 0) {
            $st = $pdo->prepare("
                SELECT p.*, pat.first_name AS pat_first, pat.last_name AS pat_last, pat.patient_no, pat.phone AS pat_phone, pat.gender AS pat_gender, pat.date_of_birth AS pat_dob,
                       doc.first_name AS doc_first, doc.last_name AS doc_last,
                       v.visit_no, v.doctor_name
                FROM pharmacy_prescriptions p
                JOIN patients pat ON pat.id = p.patient_id
                LEFT JOIN users doc ON doc.id = p.doctor_id
                LEFT JOIN visits v ON v.id = p.visit_id
                WHERE p.id = ?
            ");
            $st->execute([$id]);
            $r = $st->fetch();
            if (!$r) error('Prescription not found', 404);
            
            $items = $pdo->prepare("
                SELECT pi.*, i.name, i.item_code, i.generic_name, i.selling_price, i.unit_price,
                       COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = pi.item_id AND store_id = 1005 AND is_active = 1 AND quantity > 0), 0) AS clinic_stock
                FROM pharmacy_prescription_items pi
                LEFT JOIN items i ON i.id = pi.item_id
                WHERE pi.prescription_id = ?
                ORDER BY pi.id ASC
            ");
            $items->execute([$id]);
            $r['items'] = $items->fetchAll();
            success($r);
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;
        
        $status = trim($_GET['status'] ?? '');
        $destination = trim($_GET['destination'] ?? '');
        $search = trim($_GET['search'] ?? ($_GET['patient'] ?? ($_GET['q'] ?? '')));
        $dateFrom = trim($_GET['from'] ?? ($_GET['date_from'] ?? ''));
        $dateTo = trim($_GET['to'] ?? ($_GET['date_to'] ?? ''));
        $visitIdFilter = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;

        $where = []; 
        $params = [];
        
        if ($status === 'dispensed') {
            $where[] = "p.status IN ('dispensed_full', 'dispensed_partial')";
        } elseif ($status && $status !== 'all') { 
            $where[] = "p.status = ?"; 
            $params[] = $status; 
        }
        if ($destination && $destination !== 'all') {
            $where[] = "p.destination = ?";
            $params[] = $destination;
        }
        if ($visitIdFilter > 0) { 
            $where[] = "p.visit_id = ?"; 
            $params[] = $visitIdFilter; 
        }
        if ($dateFrom) {
            $where[] = "(CASE WHEN p.prescription_date IS NOT NULL AND p.prescription_date > '1970-01-01' THEN p.prescription_date ELSE DATE(p.created_at) END) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = "(CASE WHEN p.prescription_date IS NOT NULL AND p.prescription_date > '1970-01-01' THEN p.prescription_date ELSE DATE(p.created_at) END) <= ?";
            $params[] = $dateTo;
        }
        if ($search) {
            $words = array_filter(explode(' ', $search));
            foreach ($words as $word) {
                $s = "%$word%";
                $where[] = "(
                    p.prescription_no LIKE ? OR 
                    pat.first_name LIKE ? OR 
                    pat.last_name LIKE ? OR 
                    pat.patient_no LIKE ? OR 
                    CONCAT_WS(' ', pat.first_name, pat.last_name) LIKE ? OR
                    CONCAT_WS(' ', pat.last_name, pat.first_name) LIKE ? OR
                    pat.phone LIKE ? OR 
                    doc.first_name LIKE ? OR 
                    doc.last_name LIKE ? OR 
                    CONCAT_WS(' ', doc.first_name, doc.last_name) LIKE ? OR
                    CONCAT_WS(' ', doc.last_name, doc.first_name) LIKE ? OR
                    v.doctor_name LIKE ? OR 
                    v.visit_no LIKE ? OR 
                    p.diagnosis LIKE ? OR 
                    p.notes LIKE ? OR 
                    EXISTS (
                        SELECT 1 FROM pharmacy_prescription_items ppi 
                        LEFT JOIN items it ON it.id = ppi.item_id 
                        WHERE ppi.prescription_id = p.id AND (it.name LIKE ? OR it.generic_name LIKE ? OR ppi.instructions LIKE ?)
                    )
                )";
                for ($k = 0; $k < 18; $k++) {
                    $params[] = $s;
                }
            }
        }
        
        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Count total matching records
        $cntQuery = "
            SELECT COUNT(DISTINCT p.id) 
            FROM pharmacy_prescriptions p
            JOIN patients pat ON pat.id = p.patient_id
            LEFT JOIN users doc ON doc.id = p.doctor_id
            LEFT JOIN visits v ON v.id = p.visit_id
            $ws
        ";
        $cnt = $pdo->prepare($cntQuery);
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        
        // Query list
        $listQuery = "
            SELECT p.*, pat.first_name AS pat_first, pat.last_name AS pat_last, pat.patient_no, pat.phone AS pat_phone, pat.gender AS pat_gender,
                   doc.first_name AS doc_first, doc.last_name AS doc_last,
                   v.visit_no, v.doctor_name,
                   (SELECT COUNT(*) FROM pharmacy_prescription_items WHERE prescription_id = p.id) AS item_count,
                   (SELECT COUNT(*) FROM pharmacy_prescription_items WHERE prescription_id = p.id AND status = 'pending' AND COALESCE(destination, 'hospital') <> 'external') AS pending_count,
                   (SELECT COUNT(*) FROM pharmacy_prescription_items WHERE prescription_id = p.id AND status = 'dispensed') AS dispensed_count,
                   (
                       SELECT GROUP_CONCAT(
                           CONCAT(COALESCE(i.name, pi.instructions, 'Medication'), ' (x', CAST(pi.quantity AS UNSIGNED), ')')
                           SEPARATOR ', '
                       )
                       FROM pharmacy_prescription_items pi
                       LEFT JOIN items i ON i.id = pi.item_id
                       WHERE pi.prescription_id = p.id
                   ) AS items_summary,
                   " . payStatusSql('p.visit_id', "ii.category IN ('pharmacy','medication','drug')") . "
            FROM pharmacy_prescriptions p
            JOIN patients pat ON pat.id = p.patient_id
            LEFT JOIN users doc ON doc.id = p.doctor_id
            LEFT JOIN visits v ON v.id = p.visit_id
            $ws
            ORDER BY p.prescription_date DESC, p.id DESC 
            LIMIT $limit OFFSET $offset
        ";
        $st = $pdo->prepare($listQuery);
        $st->execute($params);
        $prescriptions = $st->fetchAll();
        
        // Dynamic stats
        $stats = [
            'total' => (int)$pdo->query("SELECT COUNT(*) FROM pharmacy_prescriptions")->fetchColumn(),
            'pending' => (int)$pdo->query("SELECT COUNT(*) FROM pharmacy_prescriptions WHERE status = 'active'")->fetchColumn(),
            'partial' => (int)$pdo->query("SELECT COUNT(*) FROM pharmacy_prescriptions WHERE status = 'dispensed_partial'")->fetchColumn(),
            'dispensed' => (int)$pdo->query("SELECT COUNT(*) FROM pharmacy_prescriptions WHERE status = 'dispensed_full'")->fetchColumn(),
            'cancelled' => (int)$pdo->query("SELECT COUNT(*) FROM pharmacy_prescriptions WHERE status = 'cancelled'")->fetchColumn(),
        ];
        
        $totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;
        
        success([
            'prescriptions' => $prescriptions,
            'stats' => $stats,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $limit, $total)
            ]
        ]);
    }
    
    // POST - create new prescription
    if ($method === 'POST' && (!isset($_GET['action']) || $_GET['action'] === '')) {
        $rxNo = 'RX-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
        $patientId = (int)($input['patient_id'] ?? 0);
        $doctorId = (int)($input['doctor_id'] ?? 0);
        $visitId = (int)($input['visit_id'] ?? 0);
        $consultId = (int)($input['consultation_id'] ?? 0);
        $diagnosis = trim($input['diagnosis'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $destination = $input['destination'] ?? 'hospital';
        $medications = $input['medications'] ?? $input['items'] ?? [];
        
        if (!$patientId) error('Patient is required');
        if (!$doctorId) error('Doctor is required');
        if (empty($medications)) error('At least one medication is required');
        
        $st = $pdo->prepare("INSERT INTO pharmacy_prescriptions (prescription_no, patient_id, visit_id, doctor_id, consultation_id, prescription_date, diagnosis, notes, status, destination) VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, 'active', ?)");
        $st->execute([$rxNo, $patientId, $visitId, $doctorId, $consultId, $diagnosis, $notes, $destination]);
        $rxId = $pdo->lastInsertId();
        
        $itemSt = $pdo->prepare("INSERT INTO pharmacy_prescription_items (prescription_id, item_id, dosage, frequency, duration, quantity, unit, route, instructions, is_controlled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($medications as $med) {
            $itemId = !empty($med['item_id']) ? (int)$med['item_id'] : null;
            $itemSt->execute([
                $rxId,
                $itemId,
                $med['dosage'] ?? '',
                $med['frequency'] ?? '',
                $med['duration'] ?? '',
                (float)($med['quantity'] ?? 0),
                $med['unit'] ?? 'tablet(s)',
                $med['route'] ?? 'oral',
                $med['drug_name'] ?? $med['instructions'] ?? '',
                (int)($med['is_controlled'] ?? 0)
            ]);
        }
        
        success(['id' => $rxId, 'prescription_no' => $rxNo], 'Prescription created');
    }

    // POST with action=dispense - dispense items from a prescription
    if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'dispense') {
        $prescriptionId = (int)($input['prescription_id'] ?? 0);
        $dispenseItems = $input['items'] ?? [];
        
        if (!$prescriptionId) error('Prescription ID is required');
        if (empty($dispenseItems)) error('No items to dispense');
        
        // Verify prescription exists and is active
        $chk = $pdo->prepare("SELECT p.id, p.status, p.patient_id, p.visit_id, p.doctor_id FROM pharmacy_prescriptions p WHERE p.id = ?");
        $chk->execute([$prescriptionId]);
        $rx = $chk->fetch();
        if (!$rx) error('Prescription not found', 404);
        if ($rx['status'] !== 'active' && $rx['status'] !== 'dispensed_partial') error('Prescription is not active');
        
        $clinicStoreId = $pharmacyStoreId;
        
        // Strictly validate available quantity in Clinic Store before processing
        $itemsToDispense = [];
        foreach ($dispenseItems as $di) {
            $itemId = (int)($di['item_id'] ?? 0);
            $qtyDispensed = (float)($di['quantity_dispensed'] ?? 0);
            if ($itemId <= 0 || $qtyDispensed <= 0) continue;

            $piStmt = $pdo->prepare("SELECT pi.*, i.name, i.unit_price, i.item_code FROM pharmacy_prescription_items pi LEFT JOIN items i ON i.id = pi.item_id WHERE pi.id = ? AND pi.prescription_id = ?");
            $piStmt->execute([$itemId, $prescriptionId]);
            $piData = $piStmt->fetch();
            if (!$piData) {
                error("Prescription item #{$itemId} not found for this prescription", 404);
            }

            $drugItemId = $piData['item_id'] ?? null;
            $drugName = $piData['name'] ?? $piData['instructions'] ?? "Item #{$itemId}";
            if (($piData['destination'] ?? '') === 'external') {
                error("'{$drugName}' is bought outside by the patient — it is not dispensed here.", 422);
            }

            if ($drugItemId) {
                $stkStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_batches WHERE store_id = ? AND item_id = ? AND is_active = 1 AND quantity > 0");
                $stkStmt->execute([$clinicStoreId, $drugItemId]);
                $availStock = (float)$stkStmt->fetchColumn();

                if ($availStock <= 0) {
                    error("Warning: Cannot dispense '{$drugName}'. Item is completely out of stock in Clinic Store (0 available).", 422);
                }
                if ($qtyDispensed > $availStock) {
                    error("Warning: Cannot dispense '{$drugName}'. Dispensing quantity ({$qtyDispensed}) exceeds available Clinic Store stock ({$availStock} available).", 422);
                }
            }

            $itemsToDispense[] = [
                'prescription_item_id' => $itemId,
                'qty' => $qtyDispensed,
                'drug_item_id' => $drugItemId,
                'pi_data' => $piData
            ];
        }

        if (empty($itemsToDispense)) {
            error("No valid quantities entered to dispense", 422);
        }

        $pdo->beginTransaction();
        try {
            $updateItem = $pdo->prepare("UPDATE pharmacy_prescription_items SET quantity_dispensed = ?, status = 'dispensed' WHERE id = ? AND prescription_id = ?");
            
            $totalCost = 0;
            $dispensedItems = [];
            
            foreach ($itemsToDispense as $itd) {
                $itemId = $itd['prescription_item_id'];
                $qtyDispensed = $itd['qty'];
                $drugItemId = $itd['drug_item_id'];
                $piData = $itd['pi_data'];
                
                $updateItem->execute([$qtyDispensed, $itemId, $prescriptionId]);
                
                $unitCost = (float)($piData['unit_price'] ?? 0);
                $lineCost = $qtyDispensed * $unitCost;
                $totalCost += $lineCost;
                
                $dispensedItems[] = [
                    'item_id' => $drugItemId,
                    'name' => $piData['name'] ?? 'Drug',
                    'qty' => $qtyDispensed,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineCost
                ];
                
                // Stock movement: deduct from clinic store (FIFO - oldest batch first)
                if ($drugItemId) {
                    $remaining = $qtyDispensed;
                    $batchStmt = $pdo->prepare("SELECT id, quantity, unit_cost FROM stock_batches WHERE store_id = ? AND item_id = ? AND is_active = 1 AND quantity > 0 ORDER BY (expiry_date IS NULL), expiry_date ASC, id ASC");
                    $batchStmt->execute([$clinicStoreId, $drugItemId]);
                    $batches = $batchStmt->fetchAll();
                    
                    foreach ($batches as $batch) {
                        if ($remaining <= 0) break;
                        $deduct = min($remaining, (float)$batch['quantity']);
                        $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ? WHERE id = ?")->execute([$deduct, $batch['id']]);
                        
                        // Record stock movement per batch
                        $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by, created_at) VALUES (?, ?, ?, 'dispensing', 'prescription', ?, ?, ?, ?, ?, NOW())")
                            ->execute([$drugItemId, $batch['id'], $clinicStoreId, $prescriptionId, $deduct, $batch['unit_cost'], 'Dispensed - Rx#'.$prescriptionId, $userId]);
                        
                        $remaining -= $deduct;
                    }

                    if ($remaining > 0) {
                        throw new Exception("Insufficient stock in Clinic Store to dispense requested quantity of '{$piData['name']}'.");
                    }
                }
            }
            
            // Check if all items are dispensed
            $pending = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_prescription_items WHERE prescription_id = ? AND status = 'pending' AND COALESCE(destination, 'hospital') <> 'external'");
            $pending->execute([$prescriptionId]);
            $pendingCount = (int)$pending->fetchColumn();
            
            $newStatus = $pendingCount === 0 ? 'dispensed_full' : 'dispensed_partial';
            $pdo->prepare("UPDATE pharmacy_prescriptions SET status = ? WHERE id = ?")->execute([$newStatus, $prescriptionId]);
            
            // Journal Entry: Debit Cost of Goods Sold (49), Credit Clinic Store (1005)
            if ($totalCost > 0) {
                // Generate journal reference
                $lastRef = $pdo->query("SELECT reference FROM journal_entries ORDER BY id DESC LIMIT 1")->fetchColumn();
                $refSeq = 1;
                if ($lastRef && preg_match('/(\d+)$/', $lastRef, $m)) $refSeq = (int)$m[1] + 1;
                $journalRef = 'JE-' . date('Ymd') . '-' . str_pad($refSeq, 4, '0', STR_PAD_LEFT);
                
                $cogsAccountId = 49; // Cost of Goods Sold (1046 was a patient receivable account)
                $clinicAccountId = 1005; // Clinic Store
                
                $itemsDesc = implode(', ', array_map(function($d){ return $d['name'].' x'.$d['qty']; }, array_slice($dispensedItems, 0, 3)));
                if (count($dispensedItems) > 3) $itemsDesc .= '...';
                
                $jeStmt = $pdo->prepare("INSERT INTO journal_entries (reference, entry_date, description, created_by, posted, created_at) VALUES (?, CURDATE(), ?, ?, 1, NOW())");
                $jeStmt->execute([$journalRef, 'Pharmacy dispensing - Rx#'.$prescriptionId.' ('.$itemsDesc.')', $userId]);
                $jeId = (int)$pdo->lastInsertId();
                
                // Debit: Cost of Goods Sold (49)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, line_description) VALUES (?, ?, ?, 0, ?)")
                    ->execute([$jeId, $cogsAccountId, $totalCost, 'COGS - Pharmacy dispensing Rx#'.$prescriptionId]);
                
                // Credit: Clinic Store (1005)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, line_description) VALUES (?, ?, 0, ?, ?)")
                    ->execute([$jeId, $clinicAccountId, $totalCost, 'Stock out - Clinic Store Rx#'.$prescriptionId]);
            }
            
            $pdo->commit();
            success([
                'prescription_id' => $prescriptionId,
                'status' => $newStatus,
                'total_cost' => $totalCost,
                'items_dispensed' => count($dispensedItems)
            ], 'Drugs dispensed. Stock deducted & journal entry posted.');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Dispense failed: ' . $e->getMessage());
        }
    }
}

// ====================================================
// 6. POINT OF SALE (Pharmacy POS)
// ====================================================
if ($entity === 'pos') {
    // GET - list sales or search
    if ($method === 'GET') {
        if ($id > 0) {
            $st = $pdo->prepare("SELECT s.*, pat.first_name AS pat_first, pat.last_name AS pat_last, pat.patient_no, u.first_name AS cashier_first, u.last_name AS cashier_last FROM pharmacy_sales s LEFT JOIN patients pat ON pat.id = s.patient_id LEFT JOIN users u ON u.id = s.sold_by WHERE s.id = ?");
            $st->execute([$id]);
            $sale = $st->fetch();
            if (!$sale) error('Sale not found', 404);
            $items = $pdo->prepare("SELECT si.*, i.name, i.item_code FROM pharmacy_sale_items si JOIN items i ON i.id = si.item_id WHERE si.sale_id = ?");
            $items->execute([$id]);
            $sale['items'] = $items->fetchAll();
            success($sale);
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page-1)*$limit;
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $st = $pdo->prepare("SELECT s.*, pat.first_name AS pat_first, pat.last_name AS pat_last, pat.patient_no, u.first_name AS cashier_first, u.last_name AS cashier_last FROM pharmacy_sales s LEFT JOIN patients pat ON pat.id = s.patient_id LEFT JOIN users u ON u.id = s.sold_by WHERE s.sale_date BETWEEN ? AND ? ORDER BY s.created_at DESC LIMIT $limit OFFSET $offset");
        $st->execute([$dateFrom, $dateTo]);
        
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_sales WHERE sale_date BETWEEN ? AND ?");
        $cnt->execute([$dateFrom, $dateTo]);
        $total = (int)$cnt->fetchColumn();
        
        // Daily summary
        $sum = $pdo->prepare("SELECT COUNT(*) AS transaction_count, COALESCE(SUM(total_amount),0) AS total_sales, COALESCE(SUM(paid_amount - total_amount),0) AS total_change, COUNT(CASE WHEN payment_method = 'cash' THEN 1 END) AS cash_count, COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END),0) AS cash_total, COUNT(CASE WHEN payment_method = 'mobile_money' THEN 1 END) AS mobile_count, COALESCE(SUM(CASE WHEN payment_method = 'mobile_money' THEN total_amount ELSE 0 END),0) AS mobile_total FROM pharmacy_sales WHERE sale_date = CURDATE() AND status = 'completed'");
        $sum->execute();
        
        success(['sales' => $st->fetchAll(), 'summary' => $sum->fetch(), 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
    
    // POST - create new sale (POS transaction)
    // Prices come from the item master and stock is taken FIFO from the pharmacy store; only item, quantity,
    // payment method and amount paid are taken from the browser.
    if ($method === 'POST') {
        $receiptNo = 'POS-'.date('Ymd').'-'.strtoupper(substr(uniqid(), -5));
        $patientId = (int)($input['patient_id'] ?? 0) ?: null;
        $prescriptionId = (int)($input['prescription_id'] ?? 0) ?: null;
        $soldBy = (int)$sessionUserId;
        $paymentMethod = $input['payment_method'] ?? 'cash';
        $paidAmount = round((float)($input['paid_amount'] ?? 0));
        $notes = trim($input['notes'] ?? '');
        $cartItems = $input['items'] ?? [];

        // Money lands in: cash → Petty cash, MoMo → MTN code, card / transfer → Bank of Kigali
        $cashAccounts = ['cash' => 1003, 'mobile_money' => 1002, 'card' => 1000, 'bank_transfer' => 1000];
        if (!isset($cashAccounts[$paymentMethod])) error('Payment method must be cash, mobile money, card or bank transfer', 422);
        if (empty($cartItems)) error('No items in cart');
        if (!$soldBy) error('Not logged in', 401);

        try {
            $pdo->beginTransaction();
            $itemQ = $pdo->prepare("SELECT id, name, selling_price FROM items WHERE id = ? AND is_active = 1");
            $batchQ = $pdo->prepare("SELECT id, quantity, unit_cost FROM stock_batches WHERE item_id = ? AND store_id = ? AND is_active = 1 AND quantity > 0 AND (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY (expiry_date IS NULL), expiry_date ASC, received_date ASC, id ASC FOR UPDATE");

            $subtotal = 0;
            $totalCost = 0;
            $lines = []; // one line per batch the quantity was taken from
            foreach ($cartItems as $ci) {
                $iid = (int)($ci['item_id'] ?? 0);
                $qty = (float)($ci['quantity'] ?? 0);
                if ($qty <= 0) throw new RuntimeException('Quantity must be more than 0');
                $itemQ->execute([$iid]);
                $item = $itemQ->fetch();
                if (!$item) throw new RuntimeException("Item #$iid not found");
                $price = (float)$item['selling_price'];
                if ($price <= 0) throw new RuntimeException("'{$item['name']}' has no selling price — set it in Inventory → Items first");

                $batchQ->execute([$iid, $pharmacyStoreId]);
                $remaining = $qty;
                foreach ($batchQ->fetchAll() as $b) {
                    if ($remaining <= 0) break;
                    $take = min($remaining, (float)$b['quantity']);
                    $lines[] = ['item_id' => $iid, 'batch_id' => (int)$b['id'], 'quantity' => $take, 'unit_price' => $price, 'unit_cost' => (float)$b['unit_cost']];
                    $remaining -= $take;
                }
                if ($remaining > 0) throw new RuntimeException("Not enough '{$item['name']}' in the pharmacy store (short by $remaining)");
                $subtotal += $qty * $price;
            }
            $totalAmount = round($subtotal);
            if ($paidAmount < $totalAmount) throw new RuntimeException('Amount paid (' . number_format($paidAmount) . ') is less than the total (' . number_format($totalAmount) . ' RWF)');
            $changeAmount = $paidAmount - $totalAmount;

            $st = $pdo->prepare("INSERT INTO pharmacy_sales (receipt_no, patient_id, prescription_id, sale_date, sale_time, subtotal, discount, tax_amount, total_amount, paid_amount, change_amount, payment_method, status, sold_by, notes) VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, 0, 0, ?, ?, ?, ?, 'completed', ?, ?)");
            $st->execute([$receiptNo, $patientId, $prescriptionId, $totalAmount, $totalAmount, $paidAmount, $changeAmount, $paymentMethod, $soldBy, $notes]);
            $saleId = (int)$pdo->lastInsertId();

            $itemSt = $pdo->prepare("INSERT INTO pharmacy_sale_items (sale_id, item_id, batch_id, quantity, unit_price, total_price, discount) VALUES (?, ?, ?, ?, ?, ?, 0)");
            $deductSt = $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ? WHERE id = ?");
            $movementSt = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by) VALUES (?, ?, ?, NULL, 'dispensing', 'pharmacy_sale', ?, ?, ?, ?, ?)");
            foreach ($lines as $li) {
                $itemSt->execute([$saleId, $li['item_id'], $li['batch_id'], $li['quantity'], $li['unit_price'], $li['quantity'] * $li['unit_price']]);
                $deductSt->execute([$li['quantity'], $li['batch_id']]);
                $movementSt->execute([$li['item_id'], $li['batch_id'], $pharmacyStoreId, $saleId, $li['quantity'], $li['unit_cost'], "POS sale $receiptNo", $soldBy]);
                $totalCost += $li['quantity'] * $li['unit_cost'];
            }

            // Update prescription if linked
            if ($prescriptionId) {
                $pdo->prepare("UPDATE pharmacy_prescriptions SET status = 'dispensed_full' WHERE id = ?")->execute([$prescriptionId]);
                $pdo->prepare("UPDATE pharmacy_prescription_items SET status = 'dispensed', quantity_dispensed = quantity WHERE prescription_id = ?")->execute([$prescriptionId]);
                $logSt = $pdo->prepare("INSERT INTO pharmacy_dispensing_log (prescription_item_id, item_id, batch_id, quantity_dispensed, unit_cost, selling_price, dispensed_by, patient_id, sale_id) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($lines as $li) {
                    $logSt->execute([$li['item_id'], $li['batch_id'], $li['quantity'], $li['unit_cost'], $li['unit_price'], $soldBy, $patientId, $saleId]);
                }
            }

            // Journal: Dr Cash/Bank, Cr Drug & consumable income (sale price); Dr Cost of Goods Sold (49), Cr pharmacy store (cost)
            $jeSt = $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, created_by, posted, posted_at, created_at) VALUES (CURDATE(), ?, ?, 'pharmacy_sale', ?, ?, 1, NOW(), NOW())");
            $lineSt = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, ?)");
            $jeSt->execute([$receiptNo, "Pharmacy POS sale $receiptNo", $saleId, $soldBy]);
            $jeId = (int)$pdo->lastInsertId();
            $lineSt->execute([$jeId, $cashAccounts[$paymentMethod], "POS sale $receiptNo ($paymentMethod)", $totalAmount, 0]);
            $lineSt->execute([$jeId, 1006, "Drug sales $receiptNo", 0, $totalAmount]);
            if ($totalCost > 0) {
                $jeSt->execute([$receiptNo . '-COGS', "Cost of drugs sold $receiptNo", $saleId, $soldBy]);
                $cogsJeId = (int)$pdo->lastInsertId();
                $lineSt->execute([$cogsJeId, 49, "COGS $receiptNo", $totalCost, 0]);
                $lineSt->execute([$cogsJeId, $pharmacyStoreId, "Stock out $receiptNo", 0, $totalCost]);
            }

            $pdo->commit();
            success(['id' => $saleId, 'receipt_no' => $receiptNo, 'change_amount' => $changeAmount, 'total_amount' => $totalAmount], 'Sale completed');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error('Sale failed: '.$e->getMessage(), $e instanceof RuntimeException ? 422 : 400);
        }
    }
}

// ====================================================
// 7. PHARMACY DASHBOARD - summary stats
// ====================================================
if ($entity === 'dashboard') {
    $today = $pdo->prepare("SELECT COUNT(*) AS sale_count, COALESCE(SUM(total_amount),0) AS total_sales FROM pharmacy_sales WHERE sale_date = CURDATE() AND status = 'completed'");
    $today->execute();
    $todayStats = $today->fetch();
    
    $rxPending = $pdo->prepare("SELECT COUNT(*) AS count FROM pharmacy_prescriptions WHERE status = 'active'");
    $rxPending->execute();
    
    $lowStock = $pdo->prepare("SELECT COUNT(*) AS count FROM stock_batches WHERE store_id = ? AND is_active = 1 AND quantity <= COALESCE(min_stock_level, 10)");
    $lowStock->execute([$pharmacyStoreId]);
    
    $pendingReq = $pdo->prepare("SELECT COUNT(*) AS count FROM pharmacy_stock_requests WHERE status = 'pending'");
    $pendingReq->execute();
    
    $expiring = $pdo->prepare("SELECT COUNT(*) AS count FROM stock_batches WHERE store_id = ? AND is_active = 1 AND quantity > 0 AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $expiring->execute([$pharmacyStoreId]);
    
    success([
        'today_sales' => $todayStats['total_sales'],
        'today_transactions' => $todayStats['sale_count'],
        'pending_prescriptions' => $rxPending->fetch()['count'],
        'low_stock_items' => $lowStock->fetch()['count'],
        'pending_requests' => $pendingReq->fetch()['count'],
        'expiring_stock' => $expiring->fetch()['count']
    ]);
}

// If no entity matched
error('Unknown endpoint: '.$entity, 404);
