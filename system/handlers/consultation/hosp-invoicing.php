<?php
// handlers/consultation/hosp-invoicing.php
// GET ?endpoint=consultation/hosp-invoicing&patient_id=N
// Hospitalization invoices of one patient: every bill on a visit that had an admission (bed assignment), past or current, any status.
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');
$pid = (int)($_GET['patient_id'] ?? 0);
if (!$pid) { http_response_code(422); echo json_encode(['success' => false, 'message' => 'patient_id required']); exit; }

$uid = (int)($_SESSION['user_id'] ?? 0);
$rq = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.slug IN ('admin','administrator') LIMIT 1");
$rq->execute([$uid]);
$isAdm = (bool)$rq->fetchColumn();

$q = $pdo->prepare("SELECT i.id, i.invoice_no, i.visit_id, v.visit_no, i.total, i.paid_amount, i.balance, i.payment_type, i.approved_at, i.created_at,
        (SELECT w.name FROM bed_assignments b LEFT JOIN wards w ON w.id = b.ward_id WHERE b.visit_id = i.visit_id ORDER BY b.id DESC LIMIT 1) AS ward,
        (SELECT MIN(b.assigned_at) FROM bed_assignments b WHERE b.visit_id = i.visit_id) AS admitted_at,
        (SELECT IF(SUM(b.status = 'active') > 0, NULL, MAX(b.discharged_at)) FROM bed_assignments b WHERE b.visit_id = i.visit_id) AS discharged_at,
        EXISTS (SELECT 1 FROM bed_assignments b WHERE b.visit_id = i.visit_id AND b.status = 'active') AS currently_admitted
    FROM invoices i JOIN visits v ON v.id = i.visit_id
    WHERE i.patient_id = ? AND i.status <> 'cancelled' AND EXISTS (SELECT 1 FROM bed_assignments b WHERE b.visit_id = i.visit_id)
    ORDER BY i.created_at DESC LIMIT 200");
$q->execute([$pid]);
$rows = $q->fetchAll(PDO::FETCH_ASSOC);

$it = $pdo->prepare("SELECT description, category, quantity, total FROM invoice_items WHERE invoice_id = ?");
foreach ($rows as &$r) {
    $bal = (float)$r['balance']; $paid = (float)$r['paid_amount'];
    $r['pay_status'] = $bal <= 0 ? ($paid == 0 && $r['payment_type'] === 'insurance' ? 'covered' : 'paid')
        : ($paid > 0 ? 'partial' : (empty($r['approved_at']) ? 'unpaid' : 'approved'));
    $it->execute([$r['id']]);
    $r['items'] = $it->fetchAll(PDO::FETCH_ASSOC);
    if (!$isAdm) {   // prices stay admin-only, like the rest of the consultation page
        foreach (['total', 'paid_amount', 'balance'] as $k) unset($r[$k]);
        foreach ($r['items'] as &$li) unset($li['total']);
        unset($li);
    }
}
unset($r);
echo json_encode(['success' => true, 'message' => 'Success', 'data' => $rows]);
