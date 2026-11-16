<?php
// includes/transfer_schema.php — patient transfers + visit closure. Creates only what workflow.php needs (idempotent, no data changes).
const TR_AMBULANCE_RHIC = 'RHIC-ADMN-002';   // tariff line "Ambulance per 1km": quantity = km

function tr_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_transfers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_no VARCHAR(30) NOT NULL,
        transfer_type ENUM('internal','external') NOT NULL,
        patient_id INT NOT NULL,
        visit_id INT NULL,
        assignment_id INT NULL,
        urgency ENUM('routine','urgent','emergency') NOT NULL DEFAULT 'routine',
        status ENUM('requested','approved','prepared','in_transit','completed','rejected','cancelled','returned') NOT NULL DEFAULT 'requested',
        scope ENUM('complete','partial') NOT NULL DEFAULT 'complete',
        reason TEXT NOT NULL,
        clinical_condition TEXT NULL,
        to_ward_id INT NULL,
        to_room_id INT NULL,
        to_bed_id INT NULL,
        to_facility VARCHAR(255) NULL,
        facility_accepted TINYINT(1) NOT NULL DEFAULT 0,
        transport VARCHAR(255) NULL,
        accompanied_by VARCHAR(255) NULL,
        handover_note TEXT NULL,
        oxygen_monitoring TEXT NULL,
        consent_by VARCHAR(150) NULL,
        referral_documents TEXT NULL,
        service_id INT NULL,
        charge_qty INT NOT NULL DEFAULT 1,
        invoice_id INT NULL,
        requested_by INT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        decided_by INT NULL,
        decided_at DATETIME NULL,
        decision_note VARCHAR(500) NULL,
        prepared_at DATETIME NULL,
        departed_at DATETIME NULL,
        arrived_at DATETIME NULL,
        returned_at DATETIME NULL,
        completed_by INT NULL,
        completed_at DATETIME NULL,
        UNIQUE KEY uq_transfer_no (transfer_no),
        KEY idx_status (status), KEY idx_patient (patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_transfer_costs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transfer_id INT NOT NULL,
        service_id INT NULL,
        description VARCHAR(255) NOT NULL,
        quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
        unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_transfer (transfer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Nullable columns the closure / external-discharge steps write
    foreach (['visits' => ['completed_at' => 'DATETIME NULL', 'completed_by' => 'INT NULL'],
              'bed_assignments' => ['discharged_by' => 'INT NULL', 'discharge_status' => 'VARCHAR(30) NULL', 'transfer_facility' => 'VARCHAR(255) NULL', 'discharge_notes' => 'TEXT NULL']] as $t => $cols) {
        foreach ($cols as $c => $def) $pdo->exec("ALTER TABLE $t ADD COLUMN IF NOT EXISTS $c $def");
    }
}

// One transfer with everything the list needs: patient, where from / to, charge, who asked.
const AP_TRANSFER_SELECT = "SELECT t.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_no, p.gender, p.date_of_birth, p.blood_group,
        p.allergies, p.chronic_diseases, p.current_medications, v.visit_no,
        (SELECT ar.diagnosis FROM admission_requests ar WHERE ar.patient_id = t.patient_id AND ar.visit_id <=> t.visit_id ORDER BY ar.id DESC LIMIT 1) AS diagnosis,
        fw.name AS from_ward, fr.room_number AS from_room, fb.bed_number AS from_bed,
        tw.name AS to_ward, tr.room_number AS to_room, tb.bed_number AS to_bed,
        sc.procedure_name AS service_name, GREATEST(COALESCE(sc.primary_health_facility,0), COALESCE(sc.secondary_health_facility,0), COALESCE(sc.tertiary_health_facility,0)) AS service_price,
        TRIM(CONCAT(ru.first_name, ' ', ru.last_name)) AS requested_by_name, TRIM(CONCAT(du.first_name, ' ', du.last_name)) AS decided_by_name,
        inv.invoice_no
    FROM patient_transfers t
    JOIN patients p ON p.id = t.patient_id
    LEFT JOIN visits v ON v.id = t.visit_id
    LEFT JOIN bed_assignments ba ON ba.id = t.assignment_id
    LEFT JOIN beds fb ON fb.id = ba.bed_id LEFT JOIN rooms fr ON fr.id = ba.room_id LEFT JOIN wards fw ON fw.id = ba.ward_id
    LEFT JOIN beds tb ON tb.id = t.to_bed_id LEFT JOIN rooms tr ON tr.id = t.to_room_id LEFT JOIN wards tw ON tw.id = t.to_ward_id
    LEFT JOIN service_catalog sc ON sc.id = t.service_id
    LEFT JOIN users ru ON ru.id = t.requested_by LEFT JOIN users du ON du.id = t.decided_by
    LEFT JOIN invoices inv ON inv.id = t.invoice_id";

// Next number of a document series ($table / $col are code constants, never user input)
if (!function_exists('next_doc_no')) {
function next_doc_no(PDO $pdo, string $table, string $col, string $prefix, int $pad = 4): string {
    $pdo->query("SELECT GET_LOCK('docno_$table', 5)")->fetchColumn();
    $q = $pdo->prepare("SELECT MAX(CAST(SUBSTRING($col, ?) AS UNSIGNED)) FROM $table WHERE $col LIKE ?");
    $q->execute([strlen($prefix) + 1, $prefix . '%']);
    return $prefix . str_pad((string)((int)$q->fetchColumn() + 1), $pad, '0', STR_PAD_LEFT);
}
}
