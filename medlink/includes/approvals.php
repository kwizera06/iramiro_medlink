<?php
// includes/approvals.php — shared by the approvals inbox (handlers/approvals/approvals.php) and patient transfers. Everything is approved by the admin.
require_once __DIR__ . '/page_guard.php';
require_once __DIR__ . '/transfer_schema.php';   // AP_TRANSFER_SELECT

// One transfer with everything an approver needs to see: patient, where from / to, diagnosis, charge, who asked.
// AP_TRANSFER_SELECT lives in transfer_schema.php

// Investigations on the visit, handed over with the patient (imaging included)
function ap_transfer_orders(PDO $pdo, ?int $visitId): array {
    if (!$visitId) return [];
    $q = $pdo->prepare("SELECT order_type, description, status FROM medical_orders WHERE visit_id = ? ORDER BY ordered_at DESC LIMIT 30");
    $q->execute([$visitId]);
    return $q->fetchAll();
}
