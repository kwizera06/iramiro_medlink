<?php
// handlers/reports/general.php — General Report (invoices + patients)
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/billing-calc.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Return distinct specialities from service_catalog
if($action === 'specialities'){
    $stmt=$pdo->query("SELECT DISTINCT speciality FROM service_catalog WHERE speciality IS NOT NULL AND speciality != '' ORDER BY speciality");
    ok(array_column($stmt->fetchAll(),'speciality'));
}

// Return distinct payment methods from invoices and payment_records
if($action === 'payment_methods'){
    $methods1 = $pdo->query("SELECT DISTINCT payment_method FROM invoices WHERE payment_method IS NOT NULL AND payment_method != ''")->fetchAll(PDO::FETCH_COLUMN);
    $methods2 = $pdo->query("SELECT DISTINCT payment_method FROM payment_records WHERE payment_method IS NOT NULL AND payment_method != '' AND status = 'completed'")->fetchAll(PDO::FETCH_COLUMN);
    $allMethods = array_values(array_unique(array_filter(array_merge($methods1, $methods2))));
    sort($allMethods);
    ok($allMethods);
}

// Allow POST for insurance_procedure_prices
if($method==='POST' && $action==='insurance_procedure_prices'){
    $input=json_decode(file_get_contents('php://input'),true)??[];
    $serviceId=(int)($input['service_id']??0);
    $insuranceId=(int)($input['insurance_id']??0);
    $price=(float)($input['price']??0);
    if(!$serviceId||!$insuranceId) err('service_id and insurance_id required',422);
    $pdo->prepare("INSERT INTO insurance_procedure_prices (service_id,insurance_id,price) VALUES (?,?,?) ON DUPLICATE KEY UPDATE price=?")->execute([$serviceId,$insuranceId,$price,$price]);
    jr(['success'=>true,'message'=>'Price saved']);
}

if($method!=='GET') err('Method not allowed',405);

try {


    // ===== REPEAT PATIENTS (Patient Visit Analytics) =====
    // A visit = one patient, one day, one department; cancelled visits excluded. So a double registration in the
    // same department counts once, two departments the same day count twice, and an empty registration with no
    // department is ignored when the patient has a departmental visit that day.
    $evSql = "SELECT v.patient_id, v.visit_date, v.department_id FROM visits v
              WHERE v.status <> 'cancelled' AND v.visit_date BETWEEN ? AND ?
                AND (v.department_id IS NOT NULL OR NOT EXISTS (SELECT 1 FROM visits x WHERE x.patient_id = v.patient_id
                     AND x.visit_date = v.visit_date AND x.status <> 'cancelled' AND x.department_id IS NOT NULL))";
    if ($action === 'repeat_visits') {
        $from = $_GET['date_from'] ?? date('Y-m-01');
        $to   = $_GET['date_to'] ?? date('Y-m-d');
        $min  = max(1, (int)($_GET['min'] ?? 2));
        $freq = max(2, (int)($_GET['frequent'] ?? 5));
        $search = trim($_GET['search'] ?? '');
        $per = "SELECT patient_id, COUNT(DISTINCT visit_date, IFNULL(department_id, 0)) AS n FROM ($evSql) ev GROUP BY patient_id";
        $st = $pdo->prepare("SELECT COUNT(*) AS patients, COALESCE(SUM(n),0) AS visits, COALESCE(SUM(n >= 2),0) AS repeat_patients,
                                    COALESCE(SUM(n >= ?),0) AS frequent_patients FROM ($per) t");
        $st->execute([$freq, $from, $to]);
        $stats = $st->fetch();
        $raw = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE status <> 'cancelled' AND visit_date BETWEEN ? AND ?");
        $raw->execute([$from, $to]);
        $stats['duplicates_merged'] = (int)$raw->fetchColumn() - (int)$stats['visits'];

        $w = ''; $p = [$from, $to];
        if ($search !== '') {
            $w = "AND (CONCAT(p.first_name,' ',p.last_name) LIKE ? OR CONCAT(p.last_name,' ',p.first_name) LIKE ? OR p.patient_no LIKE ? OR p.phone LIKE ?)";
            $like = '%' . addcslashes($search, '%_\\') . '%'; array_push($p, $like, $like, $like, $like);
        }
        $p[] = $min;
        // dates: "YYYY-MM-DD~Department" per counted visit, newest first
        $rows = $pdo->prepare("SELECT p.id, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.patient_no, p.phone, p.gender, p.date_of_birth,
                   COUNT(DISTINCT ev.visit_date, IFNULL(ev.department_id, 0)) AS visits, MIN(ev.visit_date) AS first_visit, MAX(ev.visit_date) AS last_visit,
                   GROUP_CONCAT(DISTINCT CONCAT(ev.visit_date, '~', IFNULL(d.name, '')) ORDER BY ev.visit_date DESC SEPARATOR '|') AS dates
            FROM ($evSql) ev JOIN patients p ON p.id = ev.patient_id LEFT JOIN departments d ON d.id = ev.department_id
            WHERE 1 $w
            GROUP BY p.id HAVING visits >= ?
            ORDER BY visits DESC, last_visit DESC LIMIT 2000");
        $rows->execute($p);
        ok(['stats' => $stats, 'patients' => $rows->fetchAll(), 'from' => $from, 'to' => $to, 'min' => $min, 'frequent' => $freq]);
    }

    // ===== ONE PATIENT'S FULL VISIT HISTORY =====
    if ($action === 'patient_visits') {
        $pid = (int)($_GET['patient_id'] ?? 0);
        if (!$pid) err('patient_id required', 422);
        $pt = $pdo->prepare("SELECT id, CONCAT(first_name,' ',last_name) AS patient_name, patient_no, phone, gender, date_of_birth FROM patients WHERE id = ?");
        $pt->execute([$pid]);
        $patient = $pt->fetch();
        if (!$patient) err('Patient not found', 404);
        $st = $pdo->prepare("SELECT v.id, v.visit_no, v.visit_date, v.visit_time, v.visit_type, v.status, v.payment_type, v.chief_complaint, v.department_id,
                   d.name AS department, v.doctor_name AS doctor, ic.name AS insurance
            FROM visits v LEFT JOIN departments d ON d.id = v.department_id
            LEFT JOIN insurance_companies ic ON ic.id = v.insurance_id
            WHERE v.patient_id = ? ORDER BY v.visit_date DESC, v.visit_time DESC, v.id DESC");
        $st->execute([$pid]);
        $visits = $st->fetchAll();
        // Same rule as repeat_visits; not_counted: 'cancelled' | 'duplicate' (same day + department) | 'empty' (no department)
        $withDept = []; $seen = [];
        foreach ($visits as $v) if ($v['status'] !== 'cancelled' && $v['department_id']) $withDept[$v['visit_date']] = true;
        foreach (array_reverse(array_keys($visits)) as $i) {   // oldest first, so the first registration is the counted one
            $v = &$visits[$i];
            $key = $v['visit_date'] . '|' . (int)$v['department_id'];
            if ($v['status'] === 'cancelled') $v['not_counted'] = 'cancelled';
            elseif (!$v['department_id'] && isset($withDept[$v['visit_date']])) $v['not_counted'] = 'empty';
            elseif (isset($seen[$key])) $v['not_counted'] = 'duplicate';
            else { $v['not_counted'] = null; $seen[$key] = true; }
            unset($v);
        }
        $patient['total_visits'] = count($seen);
        ok(['patient' => $patient, 'visits' => $visits]);
    }

    // ===== EVERYTHING DONE FOR A PATIENT ON ONE DATE (all visits that day, every clinical + billing record) =====
    if ($action === 'patient_day') {
        $pid = (int)($_GET['patient_id'] ?? 0); $date = $_GET['date'] ?? '';
        if (!$pid || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) err('patient_id and date required', 422);
        $q = function ($sql, $args) use ($pdo) {   // a missing/lazy table must not hide the other sections
            try { $s = $pdo->prepare($sql); $s->execute($args); return $s->fetchAll(); } catch (Throwable $e) { return []; }
        };
        $un = "TRIM(CONCAT(u.first_name,' ',u.last_name))";
        $vs = $q("SELECT v.id, v.visit_no, v.visit_date, v.visit_time, v.visit_type, v.status, v.payment_type, v.chief_complaint, d.name AS department,
                    COALESCE(NULLIF(v.doctor_name,''), $un) AS doctor, ic.name AS insurance
                  FROM visits v LEFT JOIN departments d ON d.id = v.department_id LEFT JOIN users u ON u.id = v.doctor_id
                  LEFT JOIN insurance_companies ic ON ic.id = v.insurance_id
                  WHERE v.patient_id = ? AND v.visit_date = ? ORDER BY v.visit_time, v.id", [$pid, $date]);
        foreach ($vs as &$v) {
            $id = [(int)$v['id']];
            $v['consultations'] = $q("SELECT c.id, c.status, c.doctor_name, c.chief_complaint, c.history, c.examination, c.differential_diagnosis, c.diagnosis, c.icd_code,
                    c.treatment_plan, c.prescription, c.notes, c.outcome, c.admit_ward, c.follow_up_date, c.bp_systolic, c.bp_diastolic, c.pulse, c.temperature,
                    c.weight, c.height, c.oxygen_sat, c.respiratory_rate, c.created_at, c.completed_at, rd.name AS referred_to
                FROM consultations c LEFT JOIN departments rd ON rd.id = c.referred_to_dept WHERE c.visit_id = ? ORDER BY c.id", $id);
            $v['vitals'] = $q("SELECT s.name, s.unit, r.value, r.recorded_at FROM vital_signs_records r LEFT JOIN vital_signs_settings s ON s.id = r.vital_sign_id
                WHERE r.visit_id = ? ORDER BY r.recorded_at, s.sort_order", $id);
            $v['orders'] = $q("SELECT o.order_type, o.description, o.details, o.status, o.result, o.ordered_at, o.completed_at, $un AS ordered_by
                FROM medical_orders o LEFT JOIN users u ON u.id = o.ordered_by WHERE o.visit_id = ? ORDER BY o.order_type, o.id", $id);
            $v['prescriptions'] = $q("SELECT p.prescription_no, p.status AS rx_status, p.diagnosis, p.notes, p.prescription_date, i.name AS drug, pi.dosage, pi.frequency, pi.duration,
                    pi.quantity, pi.quantity_dispensed, pi.unit, pi.route, pi.instructions, pi.status AS item_status
                FROM pharmacy_prescriptions p LEFT JOIN pharmacy_prescription_items pi ON pi.prescription_id = p.id LEFT JOIN items i ON i.id = pi.item_id
                WHERE p.visit_id = ? ORDER BY p.id, pi.id", $id);
            $v['external_rx'] = $q("SELECT e.prescription_no, e.notes, x.medication_name, x.dosage, x.frequency, x.duration, x.quantity
                FROM external_prescriptions e LEFT JOIN external_prescription_items x ON x.prescription_id = e.id WHERE e.visit_id = ? ORDER BY e.id, x.id", $id);
            $v['billing'] = $q("SELECT inv.invoice_no, inv.status AS inv_status, ii.category, ii.description, ii.quantity, ii.unit_price, ii.total, ii.dosage, ii.added_at
                FROM invoices inv JOIN invoice_items ii ON ii.invoice_id = inv.id WHERE inv.visit_id = ? ORDER BY ii.category, ii.id", $id);
            $v['invoices'] = $q("SELECT invoice_no, status, payment_type, subtotal, discount, total, paid_amount, insurer_paid_amount, balance FROM invoices WHERE visit_id = ?", $id);
            $v['nursing'] = $q("SELECT n.record_type, n.description, n.dosage, n.quantity, n.notes, n.administered_at, $un AS by_user
                FROM nursing_records n LEFT JOIN users u ON u.id = n.administered_by WHERE n.visit_id = ? ORDER BY n.administered_at", $id);
            $v['nursing_plan'] = $q("SELECT schedule_type, description, frequency, scheduled_times, administered_times, start_date, end_date, notes FROM nursing_schedule WHERE visit_id = ?", $id);
            $v['administrations'] = $q("SELECT i.name AS drug, m.dose_value, m.dose_unit, m.route, m.is_deviation, m.deviation_reason, m.administered_at, $un AS by_user
                FROM medication_administrations m LEFT JOIN pharmacy_prescription_items pi ON pi.id = m.prescription_item_id LEFT JOIN items i ON i.id = pi.item_id
                LEFT JOIN users u ON u.id = m.administered_by WHERE m.visit_id = ? ORDER BY m.administered_at", $id);
            $v['admissions'] = $q("SELECT b.assignment_type, b.status, b.assigned_at, b.discharged_at, b.discharge_status, b.discharge_notes, b.death_cause, b.transfer_facility,
                    w.name AS ward, r.room_number, bd.bed_number
                FROM bed_assignments b LEFT JOIN wards w ON w.id = b.ward_id LEFT JOIN rooms r ON r.id = b.room_id LEFT JOIN beds bd ON bd.id = b.bed_id
                WHERE b.visit_id = ? ORDER BY b.assigned_at", $id);
            $v['admission_requests'] = $q("SELECT diagnosis, admission_type, ward_preference, status, created_at FROM admission_requests WHERE visit_id = ?", $id);
            $v['ward_notes'] = $q("SELECT noted_at, notes, physician_name FROM inpatient_progress_notes WHERE visit_id = ? ORDER BY noted_at", $id);
            $v['ward_vitals'] = $q("SELECT recorded_at, temperature, blood_pressure, o2_saturation, pulse, medication FROM inpatient_vitals WHERE visit_id = ? ORDER BY recorded_at", $id);
            $v['ward_labs'] = $q("SELECT requested_at, test_name, indication FROM inpatient_lab_requests WHERE visit_id = ? ORDER BY requested_at", $id);
            $v['surgeries'] = $q("SELECT procedure_name, surgeon_name, anesthetist_name, theatre_room, anesthesia_type, status, scheduled_date, actual_start, actual_end, pre_op_notes, post_op_notes, complications
                FROM surgeries WHERE visit_id = ?", $id);
            $v['emergency'] = $q("SELECT e.id, e.triage_level, e.chief_complaint, e.arrival_mode, e.consciousness, e.vitals_bp, e.vitals_pulse, e.vitals_temp, e.vitals_spo2, e.vitals_rr,
                    e.status, e.treatment_notes, e.admitted_at, e.discharged_at FROM emergency_cases e WHERE e.visit_id = ?", $id);
            foreach ($v['emergency'] as &$e) {
                $e['treatments'] = $q("SELECT treatment_type, description, dosage, quantity, notes, administered_at FROM emergency_treatments WHERE case_id = ? ORDER BY administered_at", [$e['id']]);
            } unset($e);
            $v['transfers'] = $q("SELECT transfer_no, transfer_type, status, reason, clinical_condition, to_facility, requested_at, completed_at FROM patient_transfers WHERE visit_id = ?", $id);
            $v['departments'] = $q("SELECT d.name AS department, h.status, h.priority, h.doctor_notes, h.critical_info, h.created_at, h.completed_at
                FROM visit_department_history h LEFT JOIN departments d ON d.id = h.department_id WHERE h.visit_id = ? ORDER BY h.created_at", $id);
            $v['forms'] = $q("SELECT t.name AS form, f.form_data, f.status, f.notes, f.created_at FROM visit_department_forms f
                LEFT JOIN department_form_templates t ON t.id = f.template_id WHERE f.visit_id = ? ORDER BY f.created_at", $id);
            $v['documents'] = $q("SELECT document_type, reference_no, created_at FROM medical_documents WHERE visit_id = ? ORDER BY created_at", $id);
            $v['comments'] = $q("SELECT c.comment, c.created_at, $un AS by_user FROM clinical_note_comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.visit_id = ? ORDER BY c.created_at", $id);
            $v['events'] = $q("SELECT event, detail, user_name, created_at FROM consultation_events WHERE visit_id = ? ORDER BY created_at", $id);
        } unset($v);
        ok(['date' => $date, 'visits' => $vs]);
    }

    // ===== INSURANCE PROCEDURE PRICES =====
    if ($action === 'insurance_procedure_prices') {
        if ($method === 'GET') {
            $insuranceId = isset($_GET['insurance_id']) ? (int)$_GET['insurance_id'] : 0;
            $all = isset($_GET['all']) && $_GET['all'] == '1';
            if ($all) {
                $stmt = $pdo->query("SELECT service_id, insurance_id, price FROM insurance_procedure_prices ORDER BY service_id");
            } else {
                if (!$insuranceId) err('insurance_id required', 422);
                $stmt = $pdo->prepare("SELECT service_id, insurance_id, price FROM insurance_procedure_prices WHERE insurance_id=?");
                $stmt->execute([$insuranceId]);
            }
            ok($stmt->fetchAll());
        }
    }


    // ===== BY PRESCRIPTION REPORT =====
    if ($action === 'by_prescription') {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $drugCounts = [];

        // 1. Primary: pharmacy_prescriptions & pharmacy_prescription_items
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(NULLIF(TRIM(i.name), ''), NULLIF(TRIM(ppi.instructions), ''), 'Unknown Medication') AS medication,
                COUNT(*) AS count
            FROM pharmacy_prescription_items ppi
            JOIN pharmacy_prescriptions pp ON pp.id = ppi.prescription_id
            LEFT JOIN visits v ON v.id = pp.visit_id
            LEFT JOIN items i ON i.id = ppi.item_id
            WHERE DATE(COALESCE(pp.prescription_date, v.visit_date, pp.created_at)) BETWEEN ? AND ?
              AND (pp.status IS NULL OR pp.status != 'cancelled')
            GROUP BY medication
            ORDER BY count DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $pharmRows = $stmt->fetchAll();

        foreach ($pharmRows as $row) {
            $med = trim($row['medication']);
            if ($med === '') continue;
            $key = strtolower($med);
            $drugCounts[$key] = [
                'medication' => $med,
                'count'      => (int)$row['count']
            ];
        }

        $cntStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT pp.id)
            FROM pharmacy_prescriptions pp
            LEFT JOIN visits v ON v.id = pp.visit_id
            WHERE DATE(COALESCE(pp.prescription_date, v.visit_date, pp.created_at)) BETWEEN ? AND ?
              AND (pp.status IS NULL OR pp.status != 'cancelled')
        ");
        $cntStmt->execute([$dateFrom, $dateTo]);
        $totalPrescriptions = (int)$cntStmt->fetchColumn();

        // 2. Also merge any external prescriptions from consultations.prescription if present
        $cStmt = $pdo->prepare("
            SELECT c.prescription FROM consultations c
            JOIN visits v ON v.id = c.visit_id
            WHERE c.prescription IS NOT NULL AND c.prescription != '' AND c.prescription != '[]'
              AND DATE(v.visit_date) BETWEEN ? AND ?
        ");
        $cStmt->execute([$dateFrom, $dateTo]);
        $cRows = $cStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($cRows as $jsonStr) {
            $items = json_decode($jsonStr, true);
            if (!is_array($items) || empty($items)) continue;
            $totalPrescriptions++;
            foreach ($items as $item) {
                $med = trim($item['medication'] ?? '');
                if ($med === '') continue;
                $key = strtolower($med);
                if (!isset($drugCounts[$key])) {
                    $drugCounts[$key] = ['medication' => $med, 'count' => 0];
                }
                $drugCounts[$key]['count']++;
            }
        }

        usort($drugCounts, function($a, $b) { return $b['count'] - $a['count']; });
        jr([
            'success'             => true,
            'message'             => 'Success',
            'data'                => array_values($drugCounts),
            'total_prescriptions' => $totalPrescriptions
        ]);
    }


    // ===== BY DIAGNOSIS REPORT =====
    if ($action === 'by_diagnosis') {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        // Split comma-separated diagnoses into individual ones and count
        $stmt = $pdo->prepare("
            SELECT c.diagnosis FROM consultations c
            JOIN visits v ON v.id = c.visit_id
            WHERE c.diagnosis IS NOT NULL AND c.diagnosis != ''
              AND DATE(v.visit_date) BETWEEN ? AND ?
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Parse: each row may have comma-separated diagnoses
        $diagCounts = [];
        foreach ($rows as $diagStr) {
            $parts = array_map('trim', explode(',', $diagStr));
            foreach ($parts as $d) {
                if ($d === '') continue;
                $key = strtolower($d);
                if (!isset($diagCounts[$key])) $diagCounts[$key] = ['diagnosis' => $d, 'case_count' => 0];
                $diagCounts[$key]['case_count']++;
            }
        }
        // Sort by count desc
        usort($diagCounts, function($a, $b) { return $b['case_count'] - $a['case_count']; });
        ok(array_values($diagCounts));
    }

    // ===== BY CATEGORY REPORT (Lab, Procedure, Pharmacy) =====
    if ($action === 'by_category') {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $category = $_GET['category'] ?? 'lab';
        
        $stmt = $pdo->prepare("
            SELECT 
                ii.description,
                SUM(ii.quantity) AS total_qty,
                SUM(ii.total) AS total_revenue
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            JOIN visits v ON v.id = i.visit_id
            WHERE ii.category = ?
              AND DATE(v.visit_date) BETWEEN ? AND ?
              AND i.status != 'cancelled'
            GROUP BY ii.description
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$category, $dateFrom, $dateTo]);
        ok($stmt->fetchAll());
    }

    // ===== BY DOCTOR REPORT =====
    if ($action === 'by_doctor') {
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(c.doctor_name, CONCAT(u.first_name, ' ', u.last_name), 'Unknown') AS doctor_name,
                c.doctor_id,
                COUNT(DISTINCT c.id) AS consultation_count,
                COALESCE(SUM(i.total), 0) AS total_revenue,
                COALESCE(SUM(i.paid_amount), 0) AS total_paid
            FROM consultations c
            JOIN visits v ON v.id = c.visit_id
            LEFT JOIN users u ON u.id = c.doctor_id
            LEFT JOIN invoices i ON i.visit_id = c.visit_id AND i.status != 'cancelled'
            WHERE DATE(v.visit_date) BETWEEN ? AND ?
            GROUP BY c.doctor_id, c.doctor_name
            ORDER BY total_revenue DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        ok($stmt->fetchAll());
    }

    // Doctor / nurse performance and repeat diagnoses live in performance.php
    if (in_array($action, ['doctor_performance', 'nurse_performance', 'repeat_diagnoses'], true)) require __DIR__ . '/performance.php';

    $dateFrom=$_GET['date_from']??date('Y-m-d');
    $dateTo=$_GET['date_to']??date('Y-m-d');
    $timeFrom=$_GET['time_from']??''; // e.g. "08:00"
    $timeTo=$_GET['time_to']??'';     // e.g. "17:00"
    $insuranceId=isset($_GET['insurance_id'])&&$_GET['insurance_id']!==''?(int)$_GET['insurance_id']:null;
    $category=$_GET['category']??'';
    $paymentStatus=$_GET['payment_status']??''; // paid, unpaid, partial
    $paymentType=$_GET['payment_type']??''; // self_pay, insurance
    $paymentMethod=$_GET['payment_method']??''; // cash, bank, mobile_money, mtn_momo, insurance
    $page=max(1,(int)($_GET['page']??1));
    $limit=min(10000,max(1,(int)($_GET['limit']??50))); // dashboard asks for 9999 to cover a whole month; old cap of 200 truncated it
    $offset=($page-1)*$limit;

    // Build WHERE conditions
    $w=["DATE(v.visit_date) BETWEEN ? AND ?"];
    $p=[$dateFrom,$dateTo];

    // Time range filter (works across date range too)
    if($timeFrom!=='' && $timeTo!==''){
        if($timeTo > $timeFrom){
            // Normal range e.g. 08:00–17:00
            $w[]="TIME(v.visit_date) BETWEEN ? AND ?";
            $p[]=$timeFrom;
            $p[]=$timeTo;
        } else {
            // Overnight range e.g. 17:00–08:00
            $w[]="(TIME(v.visit_date) >= ? OR TIME(v.visit_date) <= ?)";
            $p[]=$timeFrom;
            $p[]=$timeTo;
        }
    }

    if($insuranceId){
        // Filter by insurance company — match via patient's primary insurance (same as visits API)
        $w[]="pi.company_id=?";
        $p[]=$insuranceId;
    }
    if($paymentType==='self_pay'){
        $w[]="(i.payment_type='self_pay' OR i.insurance_id IS NULL)";
    }elseif($paymentType==='insurance'){
        $w[]="i.payment_type='insurance'";
    }
    if($paymentMethod!==''){
        $w[]="i.payment_method=?";
        $p[]=$paymentMethod;
    }
    if($paymentStatus==='paid'){
        $w[]="i.status='paid'";
    }elseif($paymentStatus==='unpaid'){
        $w[]="i.status IN ('draft','issued') AND i.paid_amount=0";
    }elseif($paymentStatus==='partial'){
        // Partial = invoices with partial status OR issued/draft but have some paid amount
        $w[]="(i.status='partial' OR (i.status IN ('issued','draft') AND i.paid_amount > 0))";
    }

    // Category filter — filter invoices that have items in this category
    $categoryJoin='';
    $categoryParam=[];
    if($category!==''){
        // Map grouped categories to their speciality patterns or invoice_items.category values
     $categoryMap = [
    'pharmacy' => [
        'pharmacy',
        'emergency'
    ],

    'laboratory' => [
        'Anatomical Pathology',
        'Bacteriology',
        'BIOCHEMISTRY',
        'Biochemistry',
        'Blood Transfusion',
        'Hematology',
        'Immuno-Serology','SEROLOGY',
        'Molecular',
        'Parasitology',
        'SEROLOGY'
    ],

    'imaging' => [
        'Imaging - MRI',
        'Imaging - CT Scan',
        'Imaging - Interventional',
        'Imaging - Ultrasound',
        'Imaging - X ray',
        'MRI',
        'X-Ray',
        'CT Scan',
        'Ultrasound',
        'Interventional'
    ],

    'procedure' => [
        'Medical Procedures',
        'Surgery',
        'Endoscopy',
        'Cardiology',
        'Dentistry',
        'Physiotherapy',
        'Nursing',
        'Oncology',
     

        // Other departments/services should also appear under procedures
        'Administration',
        'Anaesthesia',
     
        'CT Surgery',
        'Dermatology',
        'DOCUMENT MEDICAL',
        'Emergency/Critical Care',
        'ENT',
        'Gastroenterology',
        'GENERAL SUGERY',
        'General Surgery',
        'Hospitalization',
        'Maxillofacial Surgery',
        'Mental Health',
        'Minor Surgery',
        'Nephrology',
        'Neurology',
        'Neurosurgery',
        'Ob/Gyn',
        'Occupational Therapy',
        'Ophthalmology',
        'ORL',
        'Orthopedics',
        'Pediatric Surgery',
        'Plastic Surgery',
        'Pulmonology',
        'SERVICE',
        'Urology',
        'Vascular Surgery'
    ]
];
        
        
        
        
        
        $categoryJoin="JOIN invoice_items ii_filter ON ii_filter.invoice_id=i.id
            LEFT JOIN service_catalog sc_filter ON sc_filter.id=ii_filter.service_id";
        
        if ($category === 'procedure') {
            $items = $categoryMap['procedure'];
            $specialityConditions = array_fill(0, count($items), 'LOWER(sc_filter.speciality) LIKE LOWER(?)');
            $w[]="(
                (LOWER(ii_filter.category) = 'procedure' OR (ii_filter.service_id IS NOT NULL AND (".implode(' OR ', $specialityConditions).")))
                AND LOWER(COALESCE(ii_filter.category,'')) NOT IN ('consultation', 'consult')
                AND LOWER(COALESCE(ii_filter.description,'')) NOT LIKE '%consultation%'
                AND (sc_filter.procedure_name IS NULL OR LOWER(sc_filter.procedure_name) NOT LIKE '%consultation%')
                AND (sc_filter.speciality IS NULL OR LOWER(sc_filter.speciality) NOT LIKE '%consultation%')
            )";
            foreach ($items as $spec) {
                $p[] = $spec.'%';
            }
        } elseif ($category === 'consultation') {
            $w[]="(
                LOWER(COALESCE(ii_filter.category,'')) IN ('consultation', 'consult')
                OR LOWER(COALESCE(ii_filter.description,'')) LIKE '%consultation%'
                OR (sc_filter.procedure_name IS NOT NULL AND LOWER(sc_filter.procedure_name) LIKE '%consultation%')
                OR (sc_filter.speciality IS NOT NULL AND LOWER(sc_filter.speciality) LIKE '%consultation%')
            )";
        } elseif (isset($categoryMap[$category])) {
            $items = $categoryMap[$category];
            
            if ($category === 'pharmacy') {
                // Pharmacy: match invoice_items.category IN ('pharmacy', 'emergency')
                $categoryConditions = array_fill(0, count($items), 'LOWER(ii_filter.category) = LOWER(?)');
                $w[]="(ii_filter.service_id IS NULL AND (".implode(' OR ', $categoryConditions)."))";
                foreach ($items as $cat) {
                    $p[] = $cat;
                }
            } else {
                // Laboratory / Imaging: match service_catalog.speciality
                $specialityConditions = array_fill(0, count($items), 'LOWER(sc_filter.speciality) LIKE LOWER(?)');
                $w[]="(ii_filter.service_id IS NOT NULL AND (".implode(' OR ', $specialityConditions)."))";
                foreach ($items as $spec) {
                    $p[] = $spec.'%';
                }
            }
        } else {
            // Individual category
            $w[]="(
                (ii_filter.service_id IS NULL AND LOWER(ii_filter.category) = LOWER(?))
                OR (ii_filter.service_id IS NOT NULL AND LOWER(sc_filter.speciality) LIKE LOWER(?))
            )";
            $p[]=$category;
            $p[]=$category.'%';
        }
    }

    // ── Helper: get insurance info for a patient ─────────────────────────────
    $insCache = [];
    $getPatientInsurance = function(int $patientId) use ($pdo, &$insCache, $insuranceId): array {
        if (isset($insCache[$patientId])) return $insCache[$patientId];
        // If filtering by a specific company, return that company's info for this patient
        if ($insuranceId) {
            $s = $pdo->prepare("
                SELECT ic.name AS company_name, COALESCE(ip.copayment_percentage, 15) AS copayment_percentage
                FROM patient_insurance pi
                JOIN insurance_companies ic ON ic.id = pi.company_id
                LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                WHERE pi.patient_id = ? AND pi.company_id = ?
                LIMIT 1
            ");
            $s->execute([$patientId, $insuranceId]);
        } else {
            // No filter — get any insurance record for this patient
            $s = $pdo->prepare("
                SELECT ic.name AS company_name, COALESCE(ip.copayment_percentage, 15) AS copayment_percentage
                FROM patient_insurance pi
                JOIN insurance_companies ic ON ic.id = pi.company_id
                LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                WHERE pi.patient_id = ?
                LIMIT 1
            ");
            $s->execute([$patientId]);
        }
        $row = $s->fetch() ?: ['company_name' => null, 'copayment_percentage' => null];
        $insCache[$patientId] = $row;
        return $row;
    };

    // ── Step 1: Get visits in date range ────────────────────────────────────
    $visitWhere  = ["DATE(v.visit_date) BETWEEN ? AND ?"];
    $visitParams = [$dateFrom, $dateTo];
    if ($paymentType === 'self_pay')  $visitWhere[] = "v.payment_type = 'self_pay'";
    if ($paymentType === 'insurance') $visitWhere[] = "v.payment_type = 'insurance'";

    // When filtering by insurance company, pre-filter visits to only insurance ones
    // and only patients who have that company registered
    if ($insuranceId) {
        $visitWhere[] = "v.payment_type = 'insurance'";
        $visitWhere[] = "EXISTS (SELECT 1 FROM patient_insurance pi_f WHERE pi_f.patient_id = v.patient_id AND pi_f.company_id = ?)";
        $visitParams[] = $insuranceId;
    }

    $visitSql = "SELECT v.id AS visit_id, v.visit_no, v.visit_date, v.payment_type, v.insurance_id,
                        p.id AS patient_id, p.patient_no, p.first_name AS patient_first, p.last_name AS patient_last, p.phone AS patient_phone,
                        COALESCE(ic.id, ic2.id, ic3.id) AS company_id,
                        COALESCE(ic.name, ic2.name, ic3.name) AS insurance_name,
                        COALESCE(ip.name, ip2.name, ip3.name, ip_def.name, '') AS package_name,
                        COALESCE(ip.copayment_percentage, ip2.copayment_percentage, ip3.copayment_percentage, ip_def.copayment_percentage, 15) AS copayment_percentage
                 FROM visits v
                 JOIN patients p ON p.id = v.patient_id
                 LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
                 LEFT JOIN insurance_companies ic ON ic.id = pi.company_id
                 LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
                 LEFT JOIN patient_insurance pi2 ON pi2.company_id = v.insurance_id AND pi2.patient_id = v.patient_id AND pi.id IS NULL
                 LEFT JOIN insurance_companies ic2 ON ic2.id = pi2.company_id AND pi.id IS NULL
                 LEFT JOIN insurance_packages ip2 ON ip2.id = pi2.package_id AND pi.id IS NULL
                 LEFT JOIN patient_insurance pi3 ON pi3.patient_id = v.patient_id AND pi3.is_primary = 1 AND pi.id IS NULL AND pi2.id IS NULL
                 LEFT JOIN insurance_companies ic3 ON ic3.id = pi3.company_id AND pi.id IS NULL AND pi2.id IS NULL
                 LEFT JOIN insurance_packages ip3 ON ip3.id = pi3.package_id AND pi.id IS NULL AND pi2.id IS NULL
                 LEFT JOIN insurance_packages ip_def ON ip_def.id = (
                     SELECT ip_sub.id FROM insurance_packages ip_sub 
                     WHERE ip_sub.company_id = COALESCE(ic.id, ic2.id, ic3.id) AND ip_sub.status = 'active' 
                     ORDER BY ip_sub.id ASC LIMIT 1
                 )
                 WHERE " . implode(' AND ', $visitWhere) . "
                 ORDER BY v.visit_date ASC, v.id ASC";
    $vstmt = $pdo->prepare($visitSql);
    $vstmt->execute($visitParams);
    $allVisits = $vstmt->fetchAll();

    // ── Step 2: For each visit get invoices and build visit groups ───────────
    $allInvoices = [];
    $visitGroups = [];

    foreach ($allVisits as $visit) {
        $insName = $visit['insurance_name'] ?? null;
        $compId = (int)($visit['company_id'] ?? 0);
        $pkgName = $visit['package_name'] ?? '';
        $visitPayType = $visit['payment_type'] ?? 'self_pay';
        $isPrivate = ($visitPayType === 'self_pay' || $compId === 14 || stripos($insName ?? '', 'prive') !== false || stripos($insName ?? '', 'private') !== false || empty($insName));
        
        // Strict filtering by insurance / payment type
        if ($insuranceId && $compId !== $insuranceId) {
            continue;
        }
        if ($paymentType === 'self_pay' && !$isPrivate) {
            continue;
        }
        if ($paymentType === 'insurance' && $isPrivate) {
            continue;
        }

        if ($isPrivate) {
            $displayPaymentType = 'self_pay';
            $displayInsuranceName = 'Private';
            $copayPct = 100.0;
        } else {
            $displayPaymentType = 'insurance';
            $displayInsuranceName = $insName;
            $copayPct = ($visit['copayment_percentage'] !== null && $visit['copayment_percentage'] !== '') ? (float)$visit['copayment_percentage'] : 15.0;
        }

        // Get invoices for this visit (exclude cancelled invoices)
        $invWhere = ["visit_id = ?", "status != 'cancelled'"];
        $invParams = [(int)$visit['visit_id']];
        if ($paymentMethod !== '') {
            $invWhere[] = "(payment_method = ? OR EXISTS (SELECT 1 FROM payment_records pr_pm WHERE pr_pm.invoice_id = invoices.id AND pr_pm.payment_method = ?))";
            $invParams[] = $paymentMethod;
            $invParams[] = $paymentMethod;
        }
        if ($paymentStatus === 'paid') {
            $invWhere[] = "(status = 'paid' OR balance <= 1.05)";
        } elseif ($paymentStatus === 'unpaid') {
            $invWhere[] = "(status IN ('draft','issued') AND (paid_amount = 0 OR paid_amount IS NULL) AND (balance > 1.05 OR balance IS NULL))";
        } elseif ($paymentStatus === 'partial') {
            $invWhere[] = "(status != 'paid' AND (balance > 1.05 OR balance IS NULL) AND (status = 'partial' OR (status IN ('issued','draft') AND paid_amount > 0)))";
        }

        $invSql = "SELECT id, invoice_no, visit_id, payment_type, payment_method, total, paid_amount, balance, status, created_at, created_by
                   FROM invoices WHERE " . implode(' AND ', $invWhere);
        $invStmt = $pdo->prepare($invSql);
        $invStmt->execute($invParams);
        $visitInvoices = $invStmt->fetchAll();
        if (!$visitInvoices) continue;

        $validInvoicesForVisit = [];

        foreach ($visitInvoices as $inv) {
            // Get invoice items
            $itemStmt = $pdo->prepare("SELECT ii.description, ii.category, ii.quantity, ii.unit_price, ii.total, ii.service_id, sc.speciality,
                                              ii.insurance_price, ii.patient_topup, ii.apply_insurance
                                       FROM invoice_items ii
                                       LEFT JOIN service_catalog sc ON sc.id = ii.service_id
                                       WHERE ii.invoice_id = ?");
            $itemStmt->execute([(int)$inv['id']]);
            $items = $itemStmt->fetchAll();

            $origInvTotal = (float)$inv['total'];

            // Apply category filter on items
            if ($category !== '') {
                $filteredItems = array_filter($items, function($it) use ($category, $categoryMap) {
                    $itemCat  = strtolower($it['category'] ?? '');
                    $itemDesc = strtolower($it['description'] ?? '');
                    $itemSpec = strtolower($it['speciality'] ?? '');

                    if ($category === 'procedure') {
                        // Strictly exclude consultations
                        if ($itemCat === 'consultation' || $itemCat === 'consult') return false;
                        if (strpos($itemDesc, 'consultation') !== false) return false;
                        if (strpos($itemSpec, 'consultation') !== false) return false;

                        // Accept if explicit procedure category
                        if ($itemCat === 'procedure') return true;

                        // Accept if matches procedure specialities in service_catalog
                        if ($it['service_id'] !== null && isset($categoryMap['procedure'])) {
                            foreach ($categoryMap['procedure'] as $spec) {
                                if (stripos($it['speciality'] ?? '', rtrim($spec, '%')) === 0) return true;
                            }
                        }
                        return false;
                    }

                    if ($category === 'consultation') {
                        if ($itemCat === 'consultation' || $itemCat === 'consult') return true;
                        if (strpos($itemDesc, 'consultation') !== false) return true;
                        if (strpos($itemSpec, 'consultation') !== false) return true;
                        return false;
                    }

                    if (isset($categoryMap[$category])) {
                        $cats = $categoryMap[$category];
                        if ($category === 'pharmacy') {
                            return $it['service_id'] === null && in_array($itemCat, array_map('strtolower', $cats));
                        } else {
                            foreach ($cats as $spec) {
                                if (stripos($it['speciality'] ?? '', rtrim($spec,'%')) === 0) return true;
                            }
                            return false;
                        }
                    }
                    return $itemCat === strtolower($category)
                        || stripos($it['speciality'] ?? '', $category) !== false;
                });
                if (empty($filteredItems)) continue; // skip invoice if no items match category
                $items = array_values($filteredItems);
                $invTotal = (float)array_sum(array_column($items, 'total'));
            } else {
                $invTotal = $origInvTotal;
            }

            // Get payment records
            $prStmt = $pdo->prepare("SELECT amount_paid, payment_method, payment_date, receipt_number, status
                                     FROM payment_records WHERE invoice_id = ? ORDER BY id ASC");
            $prStmt->execute([(int)$inv['id']]);
            $payRecords = $prStmt->fetchAll();
            foreach ($payRecords as &$pr) {
                if (($pr['payment_method'] === 'mobile_money' || empty($pr['payment_method'])) && !empty($inv['payment_method']) && in_array($inv['payment_method'], ['mtn_momo', 'airtel_money'])) {
                    $pr['payment_method'] = $inv['payment_method'];
                }
            }
            unset($pr);

            // Filter payment records by payment method if specified
            if ($paymentMethod !== '') {
                $payRecords = array_values(array_filter($payRecords, function($pr) use ($paymentMethod) {
                    $m = $pr['payment_method'] ?? 'cash';
                    if ($paymentMethod === 'mobile_money' && in_array($m, ['mobile_money', 'mtn_momo', 'airtel_money'])) return true;
                    if ($paymentMethod === 'mtn_momo' && in_array($m, ['mobile_money', 'mtn_momo'])) return true;
                    if ($paymentMethod === 'bank' && in_array($m, ['bank', 'bank_transfer'])) return true;
                    return $m === $paymentMethod;
                }));
            }

            // Get creator name
            $createdByName = 'Reception';
            if (!empty($inv['created_by'])) {
                $uStmt = $pdo->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
                $uStmt->execute([(int)$inv['created_by']]);
                $u = $uStmt->fetch();
                if ($u) {
                    $createdByName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: ($u['username'] ?? 'Staff');
                }
            }

            // Calculate payments, write-offs, patient share, and balance
            $completedRecords = array_values(array_filter($payRecords, function($r){ return $r['status']==='completed'; }));
            $writtenOffRecords = array_values(array_filter($payRecords, function($r){ return $r['status']==='written_off'; }));
            
            $completedPaid = array_sum(array_column($completedRecords, 'amount_paid'));
            if (empty($payRecords) && (float)($inv['paid_amount'] ?? 0) > 0) {
                if ($paymentMethod !== '') {
                    $m = $inv['payment_method'] ?? 'cash';
                    $matches = ($m === $paymentMethod) 
                        || ($paymentMethod === 'mobile_money' && in_array($m, ['mobile_money', 'mtn_momo', 'airtel_money']))
                        || ($paymentMethod === 'mtn_momo' && in_array($m, ['mobile_money', 'mtn_momo']))
                        || ($paymentMethod === 'bank' && in_array($m, ['bank', 'bank_transfer']));
                    $completedPaid = $matches ? (float)$inv['paid_amount'] : 0;
                } else {
                    $completedPaid = (float)$inv['paid_amount'];
                }
            }

            // If payment method was requested and this invoice has 0 paid with that method, skip it
            if ($paymentMethod !== '' && $completedPaid == 0 && (float)($inv['paid_amount'] ?? 0) > 0) {
                continue;
            }

            $writtenOff = array_sum(array_column($writtenOffRecords, 'amount_paid'));

            // Proportionally scale paid and written-off when category is filtered
            if ($category !== '' && $origInvTotal > 0) {
                $ratio = min(1.0, max(0.0, $invTotal / $origInvTotal));
                $completedPaid = round($completedPaid * $ratio, 2);
                $writtenOff    = round($writtenOff * $ratio, 2);
            }

            if ($isPrivate || $copayPct >= 100) {
                $patientShare = $invTotal;
            } else {
                $patientShare = itemsPatientShare($items, $copayPct, $invTotal); // includes patient top-ups
            }
            $totalSettled = $completedPaid + $writtenOff;
            $remainingBalance = max(0, round($patientShare - $totalSettled, 2));

            // Respect DB status and apply rounding tolerance (<= 1.05 RWF is fully paid)
            $dbStatus = $inv['status'] ?? '';
            $dbBalance = isset($inv['balance']) ? (float)$inv['balance'] : null;
            $isDbPaid = ($dbStatus === 'paid' || ($dbBalance !== null && $dbBalance <= 1.05));
            $isCovered = ($remainingBalance <= 1.05 && ($totalSettled > 0 || $patientShare <= 1.05)) || ($totalSettled >= ($patientShare - 1.05) && $totalSettled > 0);

            if ($isDbPaid || $isCovered) {
                $invStatus = 'paid';
                $remainingBalance = 0.00;
            } elseif ($totalSettled > 0) {
                $invStatus = 'partial';
            } else {
                $invStatus = in_array($dbStatus, ['draft', 'cancelled']) ? $dbStatus : 'issued';
            }

            // Strictly filter by payment_status against computed invStatus
            if ($paymentStatus !== '') {
                if ($paymentStatus === 'paid' && $invStatus !== 'paid') continue;
                if ($paymentStatus === 'unpaid' && ($invStatus !== 'issued' && $invStatus !== 'draft' || $totalSettled > 0 || $remainingBalance <= 1.05)) continue;
                if ($paymentStatus === 'partial' && $invStatus !== 'partial') continue;
            }

            $inv['items']            = $items;
            $inv['payment_records']  = $payRecords;
            $inv['total']            = $invTotal;
            $inv['paid_amount']      = $completedPaid;
            $inv['writtenoff_amount']= $writtenOff;
            $inv['balance']          = $remainingBalance;
            $inv['patient_share']    = $patientShare;
            $inv['status']           = $invStatus;
            $inv['patient_no']       = $visit['patient_no'];
            $inv['patient_first']    = $visit['patient_first'];
            $inv['patient_last']     = $visit['patient_last'];
            $inv['patient_phone']    = $visit['patient_phone'];
            $inv['visit_no']         = $visit['visit_no'];
            $inv['visit_date']       = $visit['visit_date'];
            $inv['is_private']       = $isPrivate;
            $inv['company_id']       = $compId;
            $inv['insurance_name']   = $displayInsuranceName;
            $inv['package_name']     = $pkgName;
            $inv['copayment_percentage'] = $copayPct;
            $inv['created_by_name']  = $createdByName;
            
            $validInvoicesForVisit[] = $inv;
            $allInvoices[] = $inv;
        }

        if (!empty($validInvoicesForVisit)) {
            $lastMethod = null;
            foreach ($validInvoicesForVisit as $vi) {
                if (!empty($vi['payment_method'])) {
                    $lastMethod = $vi['payment_method'];
                }
            }
            $vBalance = array_sum(array_column($validInvoicesForVisit, 'balance'));
            $allVInvsPaid = !empty($validInvoicesForVisit) && count(array_filter($validInvoicesForVisit, function($i){ return $i['status']==='paid'; })) === count($validInvoicesForVisit);
            if ($allVInvsPaid || $vBalance <= 1.05) {
                $vBalance = 0.00;
            }
            $visitGroups[] = [
                'visit_id'             => $visit['visit_id'],
                'visit_no'             => $visit['visit_no'] ?: ('VIS-'.$visit['visit_id']),
                'patient_name'         => trim(($visit['patient_first'] ?? '') . ' ' . ($visit['patient_last'] ?? '')),
                'patient_no'           => $visit['patient_no'] ?? '',
                'payment_type'         => $displayPaymentType,
                'payment_method'       => $lastMethod,
                'is_private'           => $isPrivate,
                'company_id'           => $compId,
                'insurance_name'       => $displayInsuranceName,
                'package_name'         => $pkgName,
                'copayment_percentage' => $copayPct,
                'date'                 => substr($visit['visit_date'] ?? '', 0, 10),
                'invoices'             => $validInvoicesForVisit,
                'total'                => array_sum(array_column($validInvoicesForVisit, 'total')),
                'paid'                 => array_sum(array_column($validInvoicesForVisit, 'paid_amount')),
                'deferred'             => array_sum(array_column($validInvoicesForVisit, 'writtenoff_amount')),
                'balance'              => $vBalance,
            ];
        }
    }

    // ── Step 3: Stats from collected invoices ────────────────────────────────
    $totalInvoices = count($allInvoices);
    $totalVisits   = count($visitGroups);
    $totalAmount   = array_sum(array_column($allInvoices, 'total'));
    $totalPaid     = array_sum(array_column($allInvoices, 'paid_amount'));
    $totalBal      = array_sum(array_column($allInvoices, 'balance'));
    if ($totalBal <= 1.05) {
        $totalBal = 0.00;
    }
    $totalWO       = array_sum(array_column($allInvoices, 'writtenoff_amount'));
    $paidCount     = count(array_filter($allInvoices, function($i){ return $i['status']==='paid'; }));
    $unpaidCount   = count(array_filter($allInvoices, function($i){ return in_array($i['status'],['draft','issued'])&&$i['paid_amount']==0&&($i['writtenoff_amount']??0)==0&&$i['balance']>1.05; }));
    $partCount     = count(array_filter($allInvoices, function($i){ return $i['status']==='partial'; }));
    $insCount      = count(array_filter($allInvoices, function($i){ return $i['payment_type']==='insurance'||$i['payment_type']==='mixed'; }));
    $selfCount     = count(array_filter($allInvoices, function($i){ return $i['payment_type']==='self_pay'; }));

    // Insurance owes = total − patient share (top-ups stay with the patient)
    $insOwes = 0;
    foreach ($allInvoices as $inv) {
        $copay = (float)($inv['copayment_percentage'] ?? 100);
        if (($inv['payment_type']==='insurance'||$inv['payment_type']==='mixed') && $copay < 100) {
            $insOwes += max(0, (float)$inv['total'] - (float)$inv['patient_share']);
        }
    }

    // Payment method breakdown
    $pmBreakdown = [];
    foreach ($allInvoices as $inv) {
        $records = $inv['payment_records'] ?? [];
        $completedRecords = array_filter($records, function($r){ return $r['status']==='completed'; });
        if (!empty($completedRecords)) {
            foreach ($completedRecords as $pr) {
                $m = $pr['payment_method'] ?: ($inv['payment_method'] ?: 'cash');
                if ($paymentMethod !== '') {
                    $matches = ($m === $paymentMethod)
                        || ($paymentMethod === 'mobile_money' && in_array($m, ['mobile_money', 'mtn_momo', 'airtel_money']))
                        || ($paymentMethod === 'mtn_momo' && in_array($m, ['mobile_money', 'mtn_momo']))
                        || ($paymentMethod === 'bank' && in_array($m, ['bank', 'bank_transfer']));
                    if (!$matches) continue;
                }
                if (!isset($pmBreakdown[$m])) $pmBreakdown[$m] = ['method'=>$m,'cnt'=>0,'amount'=>0];
                $pmBreakdown[$m]['cnt']++;
                $pmBreakdown[$m]['amount'] += (float)$pr['amount_paid'];
            }
        } elseif ((float)$inv['paid_amount'] > 0) {
            $m = $inv['payment_method'] ?: 'cash';
            if ($paymentMethod !== '') {
                $matches = ($m === $paymentMethod)
                    || ($paymentMethod === 'mobile_money' && in_array($m, ['mobile_money', 'mtn_momo', 'airtel_money']))
                    || ($paymentMethod === 'mtn_momo' && in_array($m, ['mobile_money', 'mtn_momo']))
                    || ($paymentMethod === 'bank' && in_array($m, ['bank', 'bank_transfer']));
                if (!$matches) continue;
            }
            if (!isset($pmBreakdown[$m])) $pmBreakdown[$m] = ['method'=>$m,'cnt'=>0,'amount'=>0];
            $pmBreakdown[$m]['cnt']++;
            $pmBreakdown[$m]['amount'] += (float)$inv['paid_amount'];
        }
    }

    // Apply pagination to VISITS (so row numbering 1..N is continuous and uninterrupted)
    $totalPages  = (int)ceil($totalVisits / max($limit, 1));
    $pagedVisits = array_slice($visitGroups, $offset, $limit);

    // Flatten invoices for this page
    $pagedInvoices = [];
    foreach ($pagedVisits as $vg) {
        foreach ($vg['invoices'] as $inv) {
            $pagedInvoices[] = $inv;
        }
    }

    // Payer breakdown over ALL invoices in range — the invoices list in the response is paged
    $byPayer = [];
    foreach ($allInvoices as $inv) {
        $name = ($inv['is_private'] || !$inv['insurance_name']) ? 'Private (Self-Pay)' : $inv['insurance_name'];
        $byPayer[$name] ??= ['name' => $name, 'private' => $name === 'Private (Self-Pay)', 'count' => 0, 'total' => 0];
        $byPayer[$name]['count']++;
        $byPayer[$name]['total'] += (float)$inv['total'];
    }

    $stats = [
        'total_invoices'  => $totalInvoices,
        'total_visits'    => $totalVisits,
        'total_amount'    => $totalAmount,
        'total_paid'      => $totalPaid,
        'total_balance'   => $totalBal,
        'total_writtenoff'=> $totalWO,
        'paid_count'      => $paidCount,
        'unpaid_count'    => $unpaidCount,
        'partial_count'   => $partCount,
        'insurance_count' => $insCount,
        'selfpay_count'   => $selfCount,
        'insurance_owes'  => $insOwes,
        'payment_methods' => array_values($pmBreakdown),
        'by_payer'        => array_values($byPayer),
    ];

    ok([
        'visits'    => $pagedVisits,
        'invoices'  => $pagedInvoices,
        'stats'     => $stats,
        'filters'   => ['date_from'=>$dateFrom,'date_to'=>$dateTo,'insurance_id'=>$insuranceId,'category'=>$category,'payment_status'=>$paymentStatus],
        'pagination'=> ['total'=>$totalVisits,'page'=>$page,'limit'=>$limit,'total_pages'=>$totalPages]
    ]);

} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
