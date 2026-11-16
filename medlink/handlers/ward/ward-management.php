<?php
// handlers/ward/ward-management.php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$wardId = isset($_GET['ward_id']) ? (int)$_GET['ward_id'] : null;
$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;

// Structural ward/room/pricing changes are an administrator (or Ward page) task.
// Bed workflow actions (assign_bed, transfer, discharge, bed status) stay available
// to clinical staff.
$wardStructuralActions = ['create_ward', 'create_room', 'set_pricing', 'update_room', 'delete_ward', 'delete_room'];
if ($method !== 'GET' && in_array($action, $wardStructuralActions, true)) {
    $canManageWards = isUserAdmin($pdo);
    if (!$canManageWards) {
        $roleStmt = $pdo->prepare("SELECT r.slug FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?");
        $roleStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
        $slugs = array_map('strtolower', $roleStmt->fetchAll(PDO::FETCH_COLUMN));
        $canManageWards = (bool)array_intersect(['clinical_director', 'managing_director'], $slugs);
    }
    if (!$canManageWards) error('You do not have permission to manage wards and rooms.', 403);
}

try {

// ============================================
// GET
// ============================================
if ($method === 'GET') {

    // --- WARDS ---
    if ($action === 'wards') {
        $stmt = $pdo->query("SELECT * FROM wards WHERE is_active=1 ORDER BY name");
        success($stmt->fetchAll());
    }

    // --- SINGLE WARD with stats ---
    if ($action === 'ward_detail' && $id) {
        $w = $pdo->prepare("SELECT * FROM wards WHERE id=?");
        $w->execute([$id]);
        $ward = $w->fetch();
        if (!$ward) error('Ward not found', 404);

        // Rooms in this ward with bed counts
        $rStmt = $pdo->prepare("
            SELECT r.*, 
                (SELECT COUNT(*) FROM beds b WHERE b.room_id=r.id) as total_beds,
                (SELECT COUNT(*) FROM beds b WHERE b.room_id=r.id AND b.status='occupied') as occupied_beds,
                (SELECT COUNT(*) FROM beds b WHERE b.room_id=r.id AND b.status='available') as available_beds,
                (SELECT COUNT(*) FROM beds b WHERE b.room_id=r.id AND b.status='cleaning') as cleaning_beds,
                (SELECT COUNT(*) FROM beds b WHERE b.room_id=r.id AND b.status='maintenance') as maintenance_beds
            FROM rooms r WHERE r.ward_id=? AND r.is_active=1 ORDER BY r.room_number
        ");
        $rStmt->execute([$id]);
        $rooms = $rStmt->fetchAll();

        // Occupancy stats
        $totalBeds = 0; $occupied = 0; $available = 0; $cleaning = 0; $maintenance = 0;
        foreach ($rooms as $r) {
            $totalBeds += $r['total_beds'];
            $occupied += $r['occupied_beds'];
            $available += $r['available_beds'];
            $cleaning += $r['cleaning_beds'];
            $maintenance += $r['maintenance_beds'];
        }

        $ward['rooms'] = $rooms;
        $ward['stats'] = [
            'total_beds' => $totalBeds,
            'occupied' => $occupied,
            'available' => $available,
            'cleaning' => $cleaning,
            'maintenance' => $maintenance
        ];
        success($ward);
    }

    // --- ALL ROOMS (optionally filtered by ward) ---
    if ($action === 'rooms') {
        $sql = "SELECT r.*, w.name as ward_name, w.ward_type as ward_type_name,
                (SELECT COUNT(*) FROM beds b WHERE b.room_id=r.id) as total_beds,
                (SELECT COUNT(*) FROM beds b WHERE b.room_id=r.id AND b.status='available') as available_beds
                FROM rooms r JOIN wards w ON w.id=r.ward_id WHERE r.is_active=1";
        $params = [];
        if ($wardId) { $sql .= " AND r.ward_id=?"; $params[] = $wardId; }
        $sql .= " ORDER BY w.name, r.room_number";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // --- SINGLE ROOM with beds ---
    if ($action === 'room_detail' && $id) {
        $rStmt = $pdo->prepare("SELECT r.*, w.name as ward_name, w.ward_type FROM rooms r JOIN wards w ON w.id=r.ward_id WHERE r.id=?");
        $rStmt->execute([$id]);
        $room = $rStmt->fetch();
        if (!$room) error('Room not found', 404);

        $bStmt = $pdo->prepare("SELECT b.*, 
            (SELECT CONCAT(p.first_name,' ',p.last_name) FROM bed_assignments ba JOIN patients p ON p.id=ba.patient_id WHERE ba.bed_id=b.id AND ba.status='active' LIMIT 1) as current_patient,
            (SELECT ba.patient_id FROM bed_assignments ba WHERE ba.bed_id=b.id AND ba.status='active' LIMIT 1) as current_patient_id
            FROM beds b WHERE b.room_id=? AND b.is_active=1 ORDER BY b.bed_number");
        $bStmt->execute([$id]);
        $room['beds'] = $bStmt->fetchAll();

        $pStmt = $pdo->prepare("SELECT * FROM bed_pricing WHERE room_type=? AND (ward_type=? OR ward_type='general') AND is_active=1 AND (effective_to IS NULL OR effective_to >= CURDATE()) ORDER BY ward_type='general' ASC LIMIT 1");
        $pStmt->execute([$room['room_type'], $room['ward_type']]);
        $room['pricing'] = $pStmt->fetch();

        success($room);
    }

    // --- BEDS ---
    if ($action === 'beds') {
        if ($roomId) {
            $stmt = $pdo->prepare("SELECT b.*, 
                (SELECT CONCAT(p.first_name,' ',p.last_name) FROM bed_assignments ba JOIN patients p ON p.id=ba.patient_id WHERE ba.bed_id=b.id AND ba.status='active' LIMIT 1) as current_patient
                FROM beds b WHERE b.room_id=? AND b.is_active=1 ORDER BY b.bed_number");
            $stmt->execute([$roomId]);
        } else {
            $stmt = $pdo->query("SELECT b.*, r.room_number, w.name as ward_name 
                FROM beds b JOIN rooms r ON r.id=b.room_id JOIN wards w ON w.id=r.ward_id 
                WHERE b.is_active=1 ORDER BY w.name, r.room_number, b.bed_number");
        }
        success($stmt->fetchAll());
    }

    // --- AVAILABLE BEDS (for admission assignment) ---
    if ($action === 'available_beds') {
        $sql = "SELECT b.id, b.bed_number, b.bed_label, r.room_number, r.room_type, 
                w.name as ward_name, w.id as ward_id, r.id as room_id,
                (SELECT price_per_day FROM bed_pricing WHERE room_type=r.room_type AND (ward_type=w.ward_type OR ward_type='general') AND is_active=1 AND (effective_to IS NULL OR effective_to >= CURDATE()) ORDER BY ward_type=w.ward_type DESC LIMIT 1) as price_per_day
                FROM beds b 
                JOIN rooms r ON r.id=b.room_id 
                JOIN wards w ON w.id=r.ward_id 
                WHERE b.status='available' AND b.is_active=1 AND r.is_active=1 AND w.is_active=1";
        $params = [];
        if ($wardId) { $sql .= " AND w.id=?"; $params[] = $wardId; }
        if ($roomId) { $sql .= " AND r.id=?"; $params[] = $roomId; }
        $sql .= " ORDER BY w.name, r.room_number, b.bed_number";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // --- BED PRICING ---
    if ($action === 'pricing') {
        $stmt = $pdo->query("SELECT * FROM bed_pricing WHERE is_active=1 AND (effective_to IS NULL OR effective_to >= CURDATE()) ORDER BY room_type, ward_type");
        success($stmt->fetchAll());
    }

    // --- OCCUPANCY STATS ---
    if ($action === 'occupancy_stats') {
        $stmt = $pdo->query("
            SELECT 
                w.id, w.name, w.code, w.ward_type, w.floor,
                (SELECT COUNT(*) FROM rooms r JOIN beds b ON b.room_id=r.id WHERE r.ward_id=w.id) as total_beds,
                (SELECT COUNT(*) FROM rooms r JOIN beds b ON b.room_id=r.id WHERE r.ward_id=w.id AND b.status='occupied') as occupied,
                (SELECT COUNT(*) FROM rooms r JOIN beds b ON b.room_id=r.id WHERE r.ward_id=w.id AND b.status='available') as available,
                (SELECT COUNT(*) FROM rooms r JOIN beds b ON b.room_id=r.id WHERE r.ward_id=w.id AND b.status='cleaning') as cleaning,
                (SELECT COUNT(*) FROM rooms r JOIN beds b ON b.room_id=r.id WHERE r.ward_id=w.id AND b.status='maintenance') as maintenance
            FROM wards w WHERE w.is_active=1 ORDER BY w.name
        ");
        $wards = $stmt->fetchAll();
        $totals = ['total_beds'=>0,'occupied'=>0,'available'=>0,'cleaning'=>0,'maintenance'=>0];
        foreach ($wards as &$w) {
            foreach (['total_beds','occupied','available','cleaning','maintenance'] as $k) {
                $w[$k] = (int)$w[$k];
                $totals[$k] += $w[$k];
            }
        }
        success(['wards' => $wards, 'totals' => $totals]);
    }

    // --- ACTIVE ADMISSIONS (currently assigned beds) ---
    // --- PENDING ADMISSION REQUESTS ---
    if ($action === 'pending_admissions') {
        $stmt = $pdo->query("
            SELECT ar.*, 
                CONCAT(p.first_name,' ',p.last_name) as patient_name,
                p.patient_no,
                v.visit_no,
                CONCAT(u.first_name,' ',u.last_name) as requested_by_name
            FROM admission_requests ar
            JOIN patients p ON p.id=ar.patient_id
            LEFT JOIN visits v ON v.id=ar.visit_id
            LEFT JOIN users u ON u.id=ar.requested_by
            WHERE ar.status='pending'
            ORDER BY ar.created_at DESC
        ");
        success($stmt->fetchAll());
    }

    // --- ACTIVE ADMISSIONS (patients currently in beds) ---
    if ($action === 'active_admissions') {
        $stmt = $pdo->query("
            SELECT ba.*, 
                CONCAT(p.first_name,' ',p.last_name) as patient_name, 
                p.patient_no, 
                b.bed_number, r.room_number, r.room_type, w.name as ward_name, w.code as ward_code, w.ward_type,
                TIMESTAMPDIFF(DAY, ba.assigned_at, COALESCE(ba.discharged_at, NOW())) as days_admitted,
                bp.price_per_day
            FROM bed_assignments ba
            JOIN patients p ON p.id=ba.patient_id
            JOIN beds b ON b.id=ba.bed_id
            JOIN rooms r ON r.id=ba.room_id
            JOIN wards w ON w.id=ba.ward_id
            LEFT JOIN bed_pricing bp ON bp.room_type=r.room_type AND bp.ward_type=w.ward_type AND bp.is_active=1
            WHERE ba.status='active'
            ORDER BY w.name, r.room_number, b.bed_number
        ");
        success($stmt->fetchAll());
    }

    // Every discharged stay, with who admitted / discharged
    if ($action === 'discharged_admissions') {
        $stmt = $pdo->query("
            SELECT ba.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no,
                b.bed_number, r.room_number, w.name AS ward_name,
                TIMESTAMPDIFF(DAY, ba.assigned_at, ba.discharged_at) AS days_admitted,
                COALESCE(CONCAT(ua.first_name,' ',ua.last_name), ua.username) AS admitted_by_name,
                COALESCE(CONCAT(ud.first_name,' ',ud.last_name), ud.username) AS discharged_by_name
            FROM bed_assignments ba
            JOIN patients p ON p.id=ba.patient_id
            JOIN beds b ON b.id=ba.bed_id
            JOIN rooms r ON r.id=ba.room_id
            JOIN wards w ON w.id=ba.ward_id
            LEFT JOIN users ua ON ua.id=ba.created_by
            LEFT JOIN users ud ON ud.id=ba.discharged_by
            WHERE ba.status='discharged'
            ORDER BY ba.discharged_at DESC LIMIT 500
        ");
        success($stmt->fetchAll());
    }

    error('Unknown action', 404);
}

// ============================================
// POST
// ============================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // --- REQUEST ADMISSION (from department) ---
    if ($action === 'request_admission') {
        $patientId = (int)($input['patient_id'] ?? 0);
        $visitId = (int)($input['visit_id'] ?? 0);
        $diagnosis = trim($input['diagnosis'] ?? '');
        if (!$patientId) error('patient_id required', 422);
        if (!$diagnosis) error('Diagnosis required', 422);
        if ($visitId) {
            $dup = $pdo->prepare("SELECT id FROM admission_requests WHERE visit_id = ? AND status = 'pending' LIMIT 1");
            $dup->execute([$visitId]);
            if ($dup->fetch()) error('An admission request for this visit is already pending ward assignment.', 409);
        }
        
        $stmt = $pdo->prepare("INSERT INTO admission_requests (patient_id, visit_id, diagnosis, admission_type, ward_preference, requested_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $patientId, $visitId, $diagnosis,
            $input['admission_type'] ?? 'elective',
            $input['ward_preference'] ?? null,
            ($_SESSION['user_id'] ?? null) ?: ($input['requested_by'] ?? null)
        ]);
        
        // Update visit status to in_progress (keep consultation_status unchanged so patient stays visible in department)
        if ($visitId) {
            $pdo->prepare("UPDATE visits SET status='in_progress' WHERE id=? AND status='open'")->execute([$visitId]);
        }
        
        success(['id' => (int)$pdo->lastInsertId()], 'Admission request created');
    }

    // --- UPDATE ADMISSION REQUEST STATUS ---
    if ($action === 'update_request') {
        $requestId = (int)($input['request_id'] ?? 0);
        $status = $input['status'] ?? '';
        $bedId = $input['bed_id'] ?? null;
        if (!$requestId) error('request_id required', 422);
        
        $stmt = $pdo->prepare("UPDATE admission_requests SET status=?, assigned_bed_id=?, assigned_at=NOW() WHERE id=?");
        $stmt->execute([$status, $bedId, $requestId]);
        success(null, 'Request updated');
    }

    // --- CREATE WARD ---
    if ($action === 'create_ward') {
        foreach (['name','code'] as $f) {
            if (empty($input[$f])) error("Field '$f' required", 422);
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO wards (name, code, description, ward_type, floor, building) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                trim($input['name']), strtoupper(trim($input['code'])),
                $input['description'] ?? null, $input['ward_type'] ?? 'general',
                $input['floor'] ?? null, $input['building'] ?? null
            ]);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') error('A ward with this code already exists', 409);
            throw $e;
        }
        success(['id' => (int)$pdo->lastInsertId()], 'Ward created', 201);
    }

    // --- CREATE ROOM ---
    if ($action === 'create_room') {
        foreach (['ward_id','room_number','bed_count'] as $f) {
            if (empty($input[$f])) error("Field '$f' required", 422);
        }
        $bedCount = (int)$input['bed_count'];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO rooms (ward_id, room_number, room_type, bed_count, has_attached_bathroom, has_tv, has_ac, has_refrigerator, has_oxygen, has_suction, has_call_bell, size_sqft, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                (int)$input['ward_id'], trim($input['room_number']),
                $input['room_type'] ?? 'single', $bedCount,
                isset($input['has_attached_bathroom']) ? 1 : 0,
                isset($input['has_tv']) ? 1 : 0,
                isset($input['has_ac']) ? 1 : 0,
                isset($input['has_refrigerator']) ? 1 : 0,
                isset($input['has_oxygen']) ? 1 : 0,
                isset($input['has_suction']) ? 1 : 0,
                isset($input['has_call_bell']) ? 1 : 1,
                $input['size_sqft'] ?? null,
                $input['notes'] ?? null
            ]);
            $roomId = (int)$pdo->lastInsertId();

            // Auto-create beds
            for ($i = 1; $i <= $bedCount; $i++) {
                $label = $bedCount === 1 ? 'Bed' : 'Bed ' . chr(64 + $i); // A, B, C...
                $pdo->prepare("INSERT INTO beds (room_id, bed_number, bed_label) VALUES (?,?,?)")
                    ->execute([$roomId, $input['room_number'].'-'.sprintf('%02d',$i), $label]);
            }
            $pdo->commit();
            success(['id' => $roomId], 'Room created with '.$bedCount.' bed(s)', 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Error: '.$e->getMessage(), 500);
        }
    }

    // --- ASSIGN BED (admit patient to a bed) ---
    if ($action === 'assign_bed') {
        foreach (['patient_id','bed_id','room_id','ward_id'] as $f) {
            if (empty($input[$f])) error("Field '$f' required", 422);
        }
        $visitId = $input['visit_id'] ?? null;
        $createdBy = $input['created_by'] ?? null;

        // Check bed is available
        $chk = $pdo->prepare("SELECT status, is_active FROM beds WHERE id=?");
        $chk->execute([(int)$input['bed_id']]);
        $bed = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$bed || !$bed['is_active']) error('Bed not found', 404);
        if (trim($bed['status']) !== 'available') error('Bed is not available (current: '.$bed['status'].')', 409);

        // Check patient not already assigned to an active bed
        $chk2 = $pdo->prepare("SELECT id FROM bed_assignments WHERE patient_id=? AND status='active' LIMIT 1");
        $chk2->execute([(int)$input['patient_id']]);
        if ($chk2->fetch()) error('Patient already assigned to a bed. Transfer or discharge first.', 409);

        $pdo->beginTransaction();
        try {
            // Create assignment
            $stmt = $pdo->prepare("INSERT INTO bed_assignments (patient_id, visit_id, bed_id, room_id, ward_id, assignment_type, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                (int)$input['patient_id'], 
                $visitId ? (int)$visitId : null,
                (int)$input['bed_id'], 
                (int)$input['room_id'], 
                (int)$input['ward_id'],
                $input['assignment_type'] ?? 'admission', 
                'active',
                $input['notes'] ?? null, 
                $createdBy ? (int)$createdBy : null
            ]);

            // Mark bed as occupied
            $pdo->prepare("UPDATE beds SET status='occupied' WHERE id=?")->execute([(int)$input['bed_id']]);

            // Update visit status if provided
            if ($visitId) {
                $pdo->prepare("UPDATE visits SET status='in_progress', consultation_status='done' WHERE id=?")->execute([$visitId]);
            }

            $pdo->commit();
            success(['id' => (int)$pdo->lastInsertId()], 'Patient assigned to bed successfully', 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Assignment failed: '.$e->getMessage(), 500);
        }
    }

    // --- SET BED PRICING ---
    if ($action === 'set_pricing') {
        foreach (['room_type','price_per_day','effective_from'] as $f) {
            if (empty($input[$f])) error("Field '$f' required", 422);
        }
        $stmt = $pdo->prepare("INSERT INTO bed_pricing (room_type, ward_type, price_per_day, currency, effective_from, effective_to) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $input['room_type'], $input['ward_type'] ?? 'general',
            (float)$input['price_per_day'], $input['currency'] ?? 'RWF',
            $input['effective_from'], $input['effective_to'] ?? null
        ]);
        success(['id' => (int)$pdo->lastInsertId()], 'Pricing set', 201);
    }

    error('Unknown action', 404);
}

// ============================================
// PUT
// ============================================
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // --- DISCHARGE patient from bed ---
    if ($action === 'discharge') {
        $assignmentId = $input['assignment_id'] ?? $id ?? null;
        $patientId = $input['patient_id'] ?? null;
        $visitId = $input['visit_id'] ?? null;
        $days = (int)($input['days'] ?? 0);
        $ratePerDay = (float)($input['rate_per_day'] ?? 0);
        $createInvoice = $input['create_invoice'] ?? false;

        if (!$assignmentId && $patientId) {
            $stmt = $pdo->prepare("SELECT id, bed_id, visit_id FROM bed_assignments WHERE patient_id=? AND status='active' LIMIT 1");
            $stmt->execute([$patientId]);
            $ass = $stmt->fetch();
            if (!$ass) error('No active bed assignment for this patient', 404);
            $assignmentId = $ass['id'];
            $bedId = $ass['bed_id'];
            if (!$visitId) $visitId = $ass['visit_id'];
        } else if ($assignmentId) {
            $stmt = $pdo->prepare("SELECT bed_id, patient_id, visit_id FROM bed_assignments WHERE id=?");
            $stmt->execute([$assignmentId]);
            $ass = $stmt->fetch();
            if (!$ass) error('Assignment not found', 404);
            $bedId = $ass['bed_id'];
            if (!$patientId) $patientId = $ass['patient_id'];
            if (!$visitId) $visitId = $ass['visit_id'];
        } else {
            error('Assignment ID or patient_id required', 422);
        }

        $pdo->beginTransaction();
        try {
            // Discharge from bed
            $pdo->prepare("UPDATE bed_assignments SET status='discharged', discharged_at=NOW() WHERE id=?")->execute([$assignmentId]);
            $pdo->prepare("UPDATE beds SET status='cleaning' WHERE id=?")->execute([$bedId]);
            
            // Update visit status
            if ($visitId) {
                $pdo->prepare("UPDATE visits SET status='completed', consultation_status='done' WHERE id=?")->execute([$visitId]);
            }
            
            // Create invoice for bed charges
            $invoiceNo = null;
            $invoiceId = null;
            if ($createInvoice && $days > 0 && $ratePerDay > 0) {
                $total = $days * $ratePerDay;
                
                $lastInv = $pdo->query("SELECT invoice_no FROM invoices ORDER BY id DESC LIMIT 1")->fetchColumn();
                $seq = 1;
                if ($lastInv && preg_match('/(\d+)$/', $lastInv, $m)) $seq = (int)$m[1] + 1;
                $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
                
                // Get payment type from visit
                $payType = 'self_pay';
                $insId = null;
                if ($visitId) {
                    $vs = $pdo->prepare("SELECT payment_type, insurance_id FROM visits WHERE id=?");
                    $vs->execute([$visitId]);
                    $vd = $vs->fetch();
                    if ($vd) { $payType = $vd['payment_type']; $insId = $vd['insurance_id']; }
                }
                
                $invStmt = $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'issued', ?, NOW())");
                $invStmt->execute([$invoiceNo, $visitId, $patientId, $payType, $insId, $total, $total, $total, "Ward accommodation - $days day(s) @ " . number_format($ratePerDay) . "/day"]);
                $invoiceId = (int)$pdo->lastInsertId();
                
                $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, category, quantity, unit_price, total) VALUES (?, ?, 'accommodation', ?, ?, ?)")
                    ->execute([$invoiceId, "Ward bed charges ($days days)", $days, $ratePerDay, $total]);
            }
            
            $pdo->commit();
            success(['invoice_id' => $invoiceId, 'invoice_no' => $invoiceNo], 'Patient discharged' . ($invoiceNo ? '. Invoice: ' . $invoiceNo : ''));
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Discharge failed: '.$e->getMessage(), 500);
        }
    }

    // --- TRANSFER patient to another bed ---
    if ($action === 'transfer') {
        foreach (['assignment_id','new_bed_id','new_room_id','new_ward_id'] as $f) {
            if (empty($input[$f])) error("Field '$f' required", 422);
        }
        $pdo->beginTransaction();
        try {
            // Get old bed
            $stmt = $pdo->prepare("SELECT bed_id, patient_id, visit_id FROM bed_assignments WHERE id=? AND status='active'");
            $stmt->execute([(int)$input['assignment_id']]);
            $old = $stmt->fetch();
            if (!$old) error('Active assignment not found', 404);

            // Free old bed
            $pdo->prepare("UPDATE beds SET status='available' WHERE id=?")->execute([$old['bed_id']]);

            // Mark old assignment as transferred
            $pdo->prepare("UPDATE bed_assignments SET status='transferred', discharged_at=NOW() WHERE id=?")->execute([(int)$input['assignment_id']]);

            // Create new assignment
            $stmt2 = $pdo->prepare("INSERT INTO bed_assignments (patient_id, visit_id, bed_id, room_id, ward_id, assignment_type, status, notes, created_by) VALUES (?,?,?,?,?,'transfer','active',?,?)");
            $stmt2->execute([
                $old['patient_id'], $old['visit_id'],
                (int)$input['new_bed_id'], (int)$input['new_room_id'], (int)$input['new_ward_id'],
                $input['notes'] ?? null, $input['created_by'] ?? null
            ]);

            // Mark new bed as occupied
            $pdo->prepare("UPDATE beds SET status='occupied' WHERE id=?")->execute([(int)$input['new_bed_id']]);

            $pdo->commit();
            success(['id' => (int)$pdo->lastInsertId()], 'Patient transferred successfully');
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Transfer failed: '.$e->getMessage(), 500);
        }
    }

    // --- UPDATE BED STATUS (cleaning, maintenance, etc) ---
    if ($action === 'update_bed_status') {
        if (!$id || empty($input['status'])) error('Bed ID and status required', 422);
        $allowed = ['available','cleaning','maintenance','out_of_order'];
        if (!in_array($input['status'], $allowed)) error('Invalid status. Allowed: '.implode(', ',$allowed), 422);
        $pdo->prepare("UPDATE beds SET status=? WHERE id=?")->execute([$input['status'], $id]);
        success(null, 'Bed status updated');
    }

    // --- UPDATE ROOM ---
    if ($action === 'update_room') {
        if (!$id) error('Room ID required', 422);
        $allowed = ['room_type','has_attached_bathroom','has_tv','has_ac','has_refrigerator','has_oxygen','has_suction','has_call_bell','size_sqft','notes','is_active'];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $sets[] = "$f=?";
                $params[] = $input[$f] === '' ? null : $input[$f];
            }
        }
        if (!$sets) error('No fields to update', 422);
        $params[] = $id;
        $pdo->prepare("UPDATE rooms SET ".implode(',',$sets)." WHERE id=?")->execute($params);
        success(null, 'Room updated');
    }

    error('Unknown action', 404);
}

// ============================================
// DELETE
// ============================================
if ($method === 'DELETE') {
    if ($action === 'delete_ward' && $id) {
        $pdo->prepare("UPDATE wards SET is_active=0 WHERE id=?")->execute([$id]);
        success(null, 'Ward deactivated');
    }
    if ($action === 'delete_room' && $id) {
        $pdo->prepare("UPDATE rooms SET is_active=0 WHERE id=?")->execute([$id]);
        success(null, 'Room deactivated');
    }
    error('Unknown action', 404);
}

error('Method not allowed', 405);
} catch (PDOException $e) {
    error('DB error: '.$e->getMessage(), 500);
}
