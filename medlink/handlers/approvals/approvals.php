<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/approvals/approvals.php
// One inbox for what the signed-in user has to approve (the admin sees everything; other roles see only the kinds listed in `roles`). This file only READS the waiting items of every module and describes how to
// decide each one; the decision itself is sent by the page to the module's own endpoint, so each module keeps its own rules
// (separation of duties, ledger posting, stages). Patient transfers are the exception: they are decided here.
// To add another kind of approval, add one entry to ap_sources() (and, when the module has no endpoint of its own, a branch in `decide`).
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/approvals.php';
require_once __DIR__ . '/../../includes/asset_schema.php';   // inv_role_slugs (medlink has no inventory_perms.php)
require_once __DIR__ . '/../../includes/request_schema.php';
rm_schema($pdo);

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$uid = (int)($_SESSION['user_id'] ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$isAdmin = pg_is_admin($pdo, $uid);
$myRoles = inv_role_slugs($pdo);
if (!$isAdmin && !$myRoles) error('Not allowed.', 403);

// Waiting items per module. x = the module's main table. `from` may join more tables; requester (ru) and decider (du) names are joined for you.
//   decide.approve / decide.reject: where the page sends the decision (query string, HTTP method, JSON body); note_key = body field that carries the note.
//   roles: non-admin roles that may decide this kind (the module's own endpoint still enforces its rules). No `roles` = admin only.
//   sections: label => SQL expression, shown in the View window. lines: SQL (one text column, ? = id) listing the items of the request.
function ap_sources(): array {
    $dec = fn($endpoint, $method, $noteKey, $approve, $reject) => ['note_key' => $noteKey,
        'approve' => ['query' => $endpoint . ($approve['q'] ?? ''), 'method' => $method, 'body' => $approve['b'] ?? []],
        'reject'  => ['query' => $endpoint . ($reject['q'] ?? ''),  'method' => $method, 'body' => $reject['b'] ?? []]];
    return [
        'transfer' => [
            'label' => 'Patient transfer',
            'from' => "patient_transfers x JOIN patients p ON p.id = x.patient_id",
            'ref' => 'x.transfer_no', 'subject' => "CONCAT(p.first_name, ' ', p.last_name, ' (', p.patient_no, ')')",
            'summary' => "IF(x.transfer_type = 'internal', CONCAT('Bed transfer to ', COALESCE((SELECT CONCAT(w.name, ' · bed ', b.bed_number) FROM beds b JOIN rooms r ON r.id = b.room_id JOIN wards w ON w.id = r.ward_id WHERE b.id = x.to_bed_id), '?')), CONCAT('External transfer to ', x.to_facility))",
            'priority' => 'x.urgency', 'req_by' => 'x.requested_by', 'req_at' => 'x.requested_at', 'pending' => "x.status = 'requested'",
            'status' => 'x.status', 'dec_by' => 'x.decided_by', 'dec_at' => 'x.decided_at',
            'decide' => ['own' => true, 'note_key' => 'note'],
        ],
        'asset_transfer' => [
            'roles' => ['accountant', 'managing_director'], 'not_own' => 'x.requested_by',   // = AM_DECIDE_TRANSFER
            'label' => 'Asset transfer',
            'from' => "asset_transfers x JOIN assets a ON a.id = x.asset_id",
            'ref' => 'x.transfer_no', 'subject' => "CONCAT(a.asset_code, ' - ', a.name)",
            'summary' => "CONCAT(COALESCE(x.from_location, '—'), ' → ', COALESCE(x.to_location, '—'))",
            'priority' => 'NULL', 'req_by' => 'x.requested_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'pending'",
            'status' => 'x.status', 'dec_by' => 'x.decided_by', 'dec_at' => 'x.decided_at',
            'sections' => ['Asset' => ['Code' => 'a.asset_code', 'Name' => 'a.name', 'Purchase cost' => 'a.purchase_cost', 'Current value' => 'a.current_value', 'Condition / status' => 'a.status'],
                           'Transfer' => ['From' => 'x.from_location', 'To' => 'x.to_location', 'Transfer date' => 'x.transfer_date', 'Reason' => 'x.reason', 'Decision note' => 'x.decision_note']],
            'decide' => $dec('assets/asset-management', 'POST', 'note',
                ['q' => '&action=transfer_decide', 'b' => ['id' => '$id', 'decision' => 'approve']], ['q' => '&action=transfer_decide', 'b' => ['id' => '$id', 'decision' => 'reject']]),
        ],
        'asset_disposal' => [
            'roles' => ['managing_director'], 'not_own' => 'x.requested_by',                 // = AM_DECIDE_DISPOSAL
            'label' => 'Asset disposal',
            'from' => "asset_disposals x JOIN assets a ON a.id = x.asset_id",
            'ref' => 'x.disposal_no', 'subject' => "CONCAT(a.asset_code, ' - ', a.name)",
            'summary' => "CONCAT(x.method, ' · proceeds ', FORMAT(x.proceeds, 0), ' RWF')",
            'priority' => 'NULL', 'req_by' => 'x.requested_by', 'req_at' => 'x.requested_at', 'pending' => "x.status = 'pending'",
            'status' => 'x.status', 'dec_by' => 'x.decided_by', 'dec_at' => 'x.decided_at',
            'sections' => ['Asset' => ['Code' => 'a.asset_code', 'Name' => 'a.name', 'Purchase cost' => 'a.purchase_cost', 'Accumulated depreciation' => 'a.accumulated_depreciation', 'Current value' => 'a.current_value'],
                           'Disposal' => ['Method' => 'x.method', 'Disposal date' => 'x.disposal_date', 'Proceeds' => 'x.proceeds', 'Gain / (loss)' => 'x.gain_loss', 'Reason' => 'x.reason', 'Decision note' => 'x.decision_note']],
            'decide' => $dec('assets/asset-management', 'POST', 'note',
                ['q' => '&action=disposal_decide', 'b' => ['id' => '$id', 'decision' => 'approve']], ['q' => '&action=disposal_decide', 'b' => ['id' => '$id', 'decision' => 'reject']]),
        ],
        'expense' => [
            'roles' => ['accountant', 'department_head'], 'not_own' => 'x.created_by', 'dept' => 'x.department_id', 'global_roles' => ['accountant'],
            'label' => 'Expense',
            'from' => "expense_requests x",
            'ref' => 'x.expense_no', 'subject' => "LEFT(x.description, 80)",
            'summary' => "CONCAT(FORMAT(x.total, 0), ' RWF · ', COALESCE(x.payee, ''))",
            'priority' => 'NULL', 'req_by' => 'x.created_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'submitted'",
            'status' => 'x.status', 'dec_by' => 'x.approved_by', 'dec_at' => "COALESCE(x.approved_at, IF(x.status = 'rejected', x.updated_at, NULL))",
            'sections' => ['Expense' => ['Number' => 'x.expense_no', 'Date' => 'x.expense_date', 'Due date' => 'x.due_date', 'Description' => 'x.description', 'Item / service' => 'x.item_service',
                                         'Quantity' => 'x.quantity', 'Unit price' => 'x.unit_price', 'Tax' => 'x.tax', 'Discount' => 'x.discount', 'Total' => 'x.total'],
                           'Payee' => ['Payee' => 'x.payee', 'Telephone' => 'x.telephone', 'Invoice reference' => 'x.invoice_reference', 'Payment method' => 'x.payment_method', 'Comments' => 'x.comments', 'Rejection reason' => 'x.rejected_reason']],
            'decide' => $dec('expenses/management', 'POST', 'reason', ['q' => '&action=approve', 'b' => ['id' => '$id']], ['q' => '&action=reject', 'b' => ['id' => '$id']]),
        ],
        'partial_payment' => [
            'label' => 'Partial payment',
            'from' => "payment_records x LEFT JOIN patients p ON p.id = x.patient_id LEFT JOIN invoices i ON i.id = x.invoice_id",
            'ref' => "COALESCE(x.receipt_number, CONCAT('PAY-', x.id))", 'subject' => "COALESCE(CONCAT(p.first_name, ' ', p.last_name, ' (', p.patient_no, ')'), '—')",
            'summary' => "CONCAT(FORMAT(x.amount_paid, 0), ' RWF on invoice ', COALESCE(i.invoice_no, '?'))",
            'priority' => 'NULL', 'req_by' => 'x.created_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'awaiting_approval'",
            'status' => 'x.status', 'dec_by' => 'x.verified_by', 'dec_at' => 'x.verified_at',
            'sections' => ['Payment' => ['Amount paid' => 'x.amount_paid', 'Method' => 'x.payment_method', 'Date' => 'x.payment_date', 'Reference' => 'x.reference_number', 'Bank' => 'x.bank_name', 'Notes' => 'x.notes', 'Rejection reason' => 'x.rejection_reason'],
                           'Invoice' => ['Invoice' => 'i.invoice_no', 'Invoice total' => 'i.total', 'Balance now' => 'i.balance', 'Invoice status' => 'i.status']],
            'decide' => $dec('finance/payment-records', 'PUT', 'reason', ['q' => '&id=$id', 'b' => ['action' => 'approve']], ['q' => '&id=$id', 'b' => ['action' => 'reject']]),
        ],
        'deletion_request' => [
            'label' => 'Deletion request',
            'roles' => ['managing_director', 'clinical_director'],   // = review in deletion-requests.php
            'from' => "deletion_requests x",
            'ref' => "CONCAT('DEL-', x.id)", 'subject' => "CONCAT(x.entity_type, ': ', COALESCE(x.entity_name, x.entity_id))", 'summary' => 'x.remarks',
            'priority' => 'NULL', 'req_by' => 'x.requested_by', 'req_at' => 'x.requested_at', 'pending' => "x.status = 'pending'",
            'status' => 'x.status', 'dec_by' => 'x.reviewed_by', 'dec_at' => 'x.reviewed_at',
            'sections' => ['Request' => ['Record type' => 'x.entity_type', 'Record' => 'x.entity_name', 'Record id' => 'x.entity_id', 'Remarks' => 'x.remarks', 'Review notes' => 'x.review_notes']],
            'decide' => $dec('deletion-requests', 'POST', 'review_notes', ['q' => '&action=review', 'b' => ['id' => '$id', 'status' => 'approved']], ['q' => '&action=review', 'b' => ['id' => '$id', 'status' => 'rejected']]),
        ],
        'edit_request' => [
            'label' => 'Edit request',
            'from' => "edit_requests x",
            'ref' => "CONCAT('EDIT-', x.id)", 'subject' => "CONCAT(x.entity_type, ': ', COALESCE(x.entity_name, x.entity_id))", 'summary' => 'x.reason',
            'priority' => 'NULL', 'req_by' => 'x.requested_by', 'req_at' => 'x.requested_at', 'pending' => "x.status = 'pending'",
            'status' => 'x.status', 'dec_by' => 'x.reviewed_by', 'dec_at' => 'x.reviewed_at',
            'sections' => ['Request' => ['Record type' => 'x.entity_type', 'Record' => 'x.entity_name', 'Record id' => 'x.entity_id', 'Reason' => 'x.reason', 'Review notes' => 'x.review_notes']],
            'decide' => $dec('edit-requests', 'POST', 'review_notes', ['q' => '&action=review', 'b' => ['id' => '$id', 'status' => 'approved']], ['q' => '&action=review', 'b' => ['id' => '$id', 'status' => 'rejected']]),
        ],
        'request' => [
            'roles' => rm_approver_roles(), 'not_own' => 'x.requested_by',   // = rm_can_approve() in request-management.php
            'label' => 'Departmental request',
            'from' => "request_management x LEFT JOIN departments d ON d.id = x.department_id",
            'ref' => 'x.request_no', 'subject' => "COALESCE(d.name, 'Request')", 'summary' => 'x.purpose',
            'priority' => 'x.priority', 'req_by' => 'x.requested_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'submitted'",
            'status' => 'x.status', 'dec_by' => "COALESCE(x.approved_by, (SELECT a.user_id FROM request_management_audit a WHERE a.request_id = x.id AND a.action = 'reject' ORDER BY a.id DESC LIMIT 1))",
            'dec_at' => "COALESCE(x.approved_at, IF(x.status = 'rejected', x.updated_at, NULL))",
            'sections' => ['Request' => ['Number' => 'x.request_no', 'Department' => 'd.name', 'Purpose' => 'x.purpose', 'Required by' => 'x.required_date', 'Priority' => 'x.priority', 'Comments' => 'x.comments']],
            'lines' => "SELECT CONCAT(item_name, ' × ', quantity_requested, ' (in stock ', quantity_available, ')') FROM request_management_items WHERE request_id = ?",
            'decide' => $dec('requests/management', 'POST', 'comment', ['q' => '&action=approve', 'b' => ['id' => '$id']], ['q' => '&action=reject', 'b' => ['id' => '$id']]),
        ],
        'request_supervisor' => [   // nurse requests: the chosen doctor approves first, then the request goes to the final approvers
            'roles' => ['doctor'], 'only_user' => 'x.supervisor_id',
            'label' => 'Nurse request (supervisor)',
            'from' => "request_management x LEFT JOIN departments d ON d.id = x.department_id",
            'ref' => 'x.request_no', 'subject' => "COALESCE(d.name, 'Request')", 'summary' => 'x.purpose',
            'priority' => 'x.priority', 'req_by' => 'x.requested_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'pending_supervisor'",
            'status' => 'x.status', 'dec_by' => 'x.supervisor_id', 'dec_at' => 'x.supervisor_approved_at',
            'sections' => ['Request' => ['Number' => 'x.request_no', 'Department' => 'd.name', 'Purpose' => 'x.purpose', 'Required by' => 'x.required_date', 'Priority' => 'x.priority', 'Comments' => 'x.comments']],
            'lines' => "SELECT CONCAT(item_name, ' × ', quantity_requested, ' (in stock ', quantity_available, ')') FROM request_management_items WHERE request_id = ?",
            'decide' => $dec('requests/management', 'POST', 'comment', ['q' => '&action=supervisor_approve', 'b' => ['id' => '$id']], ['q' => '&action=reject', 'b' => ['id' => '$id']]),
        ],
        'requisition' => [
            'roles' => ['inventory_manager'], 'not_own' => 'x.requested_by',
            'label' => 'Store requisition',
            'from' => "requisitions x LEFT JOIN departments d ON d.id = x.department_id LEFT JOIN stores s ON s.id = x.store_id",
            'ref' => 'x.requisition_no', 'subject' => "CONCAT(COALESCE(d.name, 'Requisition'), ' ← ', COALESCE(s.name, '?'))", 'summary' => 'x.purpose',
            'priority' => 'x.priority', 'req_by' => 'x.requested_by', 'req_at' => 'COALESCE(x.submitted_at, x.created_at)', 'pending' => "x.status = 'submitted'",
            'status' => 'x.status', 'dec_by' => 'x.reviewed_by', 'dec_at' => 'x.reviewed_at',
            'sections' => ['Requisition' => ['Number' => 'x.requisition_no', 'Department' => 'd.name', 'Store' => 's.name', 'Purpose' => 'x.purpose', 'Priority' => 'x.priority', 'Review remarks' => 'x.review_remarks']],
            'lines' => "SELECT CONCAT(it.name, ' × ', ri.quantity_requested) FROM requisition_items ri JOIN items it ON it.id = ri.item_id WHERE ri.requisition_id = ?",
            'decide' => $dec('requisitions', 'POST', 'remarks', ['q' => '&action=approve&id=$id'], ['q' => '&action=reject&id=$id']),
        ],
        'purchase_order' => [
            'roles' => ['managing_director'], 'not_own' => 'x.created_by',
            'label' => 'Purchase order',
            'from' => "purchase_orders x LEFT JOIN suppliers s ON s.id = x.supplier_id",
            'ref' => 'x.po_number', 'subject' => "COALESCE(s.company_name, 'Supplier')", 'summary' => "CONCAT(FORMAT(x.total_amount, 0), ' RWF · delivery ', COALESCE(x.delivery_date, '—'))",
            'priority' => 'NULL', 'req_by' => 'x.created_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'draft'",
            'status' => 'x.status', 'dec_by' => 'x.approved_by', 'dec_at' => "COALESCE(x.approved_at, IF(x.status = 'cancelled', x.updated_at, NULL))",
            'sections' => ['Purchase order' => ['Number' => 'x.po_number', 'Supplier' => 's.company_name', 'Order date' => 'x.order_date', 'Delivery date' => 'x.delivery_date', 'Shipping' => 'x.shipping_method',
                                                'Subtotal' => 'x.subtotal', 'Tax' => 'x.tax_amount', 'Total' => 'x.total_amount', 'Delivery address' => 'x.delivery_address', 'Notes' => 'x.notes']],
            'lines' => "SELECT CONCAT(COALESCE(NULLIF(poi.item_description, ''), it.name, 'Item'), ' × ', poi.quantity_ordered, ' @ ', FORMAT(COALESCE(poi.unit_price, 0), 0)) FROM purchase_order_items poi LEFT JOIN items it ON it.id = poi.item_id WHERE poi.po_id = ?",
            'decide' => $dec('inventory/inventory-management', 'POST', 'note', ['q' => '&action=update_po_status', 'b' => ['po_id' => '$id', 'status' => 'confirmed']], ['q' => '&action=update_po_status', 'b' => ['po_id' => '$id', 'status' => 'cancelled']]),
        ],
        'stock_transfer' => [
            'roles' => ['inventory_manager', 'storekeeper'], 'not_own' => 'x.created_by',
            'label' => 'Stock transfer',
            'from' => "stock_transfers x LEFT JOIN stores fs ON fs.id = x.from_store_id LEFT JOIN stores ts ON ts.id = x.to_store_id",
            'ref' => 'x.transfer_number', 'subject' => "CONCAT(COALESCE(fs.name, '?'), ' → ', COALESCE(ts.name, '?'))",
            'summary' => "CONCAT((SELECT COUNT(*) FROM stock_transfer_items i WHERE i.transfer_id = x.id), ' item(s) · ', COALESCE(NULLIF(x.reason, ''), 'no reason given'))",
            'priority' => 'NULL', 'req_by' => 'x.created_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'pending'",
            'status' => 'x.status', 'dec_by' => 'x.approved_by', 'dec_at' => "IF(x.status IN ('approved','in_transit','received','cancelled'), x.updated_at, NULL)",
            'sections' => ['Transfer' => ['Number' => 'x.transfer_number', 'From store' => 'fs.name', 'To store' => 'ts.name', 'Date' => 'x.transfer_date', 'Reason' => 'x.reason', 'Notes' => 'x.notes']],
            'lines' => "SELECT CONCAT(it.name, ' × ', sti.quantity) FROM stock_transfer_items sti JOIN items it ON it.id = sti.item_id WHERE sti.transfer_id = ?",
            'decide' => $dec('inventory/inventory-management', 'POST', 'note', ['q' => '&action=approve_transfer', 'b' => ['transfer_id' => '$id']], ['q' => '&action=update_transfer_status', 'b' => ['transfer_id' => '$id', 'status' => 'cancelled']]),
        ],
        'work_order' => [
            'roles' => ['managing_director', 'maintenance_officer'], 'not_own' => 'x.work_done_by',   // = AM_VERIFY_WORK, never the person who did the work
            'label' => 'Repair verification',
            'from' => "asset_work_orders x JOIN assets a ON a.id = x.asset_id",
            'ref' => 'x.wo_no', 'subject' => "CONCAT(a.asset_code, ' - ', a.name)",
            'summary' => "CONCAT(x.kind, ' · cost ', FORMAT(x.cost, 0), ' RWF · downtime ', x.downtime_hours, ' h')",
            'priority' => 'x.priority', 'req_by' => 'x.work_done_by', 'req_at' => 'COALESCE(x.work_done_at, x.created_at)', 'pending' => "x.status = 'awaiting_verification'",
            'status' => 'x.status', 'dec_by' => 'x.verified_by', 'dec_at' => 'x.verified_at',
            'sections' => ['Work order' => ['Number' => 'x.wo_no', 'Asset' => "CONCAT(a.asset_code, ' - ', a.name)", 'Type' => 'x.kind', 'Priority' => 'x.priority', 'External provider' => 'x.external_provider'],
                           'Work recorded' => ['Findings' => 'x.findings', 'Work done' => 'x.work_done', 'Parts used' => 'x.parts_used', 'Cost (posted to Finance on approval)' => 'x.cost', 'Downtime (hours)' => 'x.downtime_hours', 'Verification note' => 'x.verify_note']],
            'decide' => $dec('assets/asset-management', 'POST', 'note', ['q' => '&action=wo_verify', 'b' => ['id' => '$id', 'decision' => 'approve']], ['q' => '&action=wo_verify', 'b' => ['id' => '$id', 'decision' => 'reject']]),
        ],
        'proforma' => [
            'roles' => ['accountant', 'managing_director'], 'not_own' => 'x.created_by',
            'label' => 'Supplier proforma',
            'from' => "supplier_proformas x JOIN suppliers s ON s.id = x.supplier_id",
            'ref' => 'x.proforma_number', 'subject' => 's.company_name',
            'summary' => "CONCAT(FORMAT(x.amount, 0), ' RWF · valid until ', COALESCE(x.valid_until, 'open'))",
            'priority' => 'NULL', 'req_by' => 'x.created_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'pending'",
            'status' => 'x.status', 'dec_by' => 'x.decided_by', 'dec_at' => 'x.decided_at',
            'sections' => ['Proforma' => ['Number' => 'x.proforma_number', 'Supplier' => 's.company_name', 'Amount' => 'x.amount', 'Issued' => 'x.issue_date', 'Valid until' => 'x.valid_until', 'Notes' => 'x.notes', 'Decision note' => 'x.decision_note']],
            'decide' => $dec('procurement/procurement-ops', 'POST', 'note', ['q' => '&action=proforma_decide', 'b' => ['id' => '$id', 'decision' => 'accepted']], ['q' => '&action=proforma_decide', 'b' => ['id' => '$id', 'decision' => 'rejected']]),
        ],
        // HR leave and permission: two steps (HR forwards, then the admin decides). The HR officer sees them at step one only.
        'leave_request' => [
            'roles' => ['hr_manager'], 'hr_step' => true,
            'label' => 'Leave request',
            'from' => "leave_requests x JOIN employees e ON e.id = x.employee_id JOIN leave_types lt ON lt.id = x.leave_type_id",
            'ref' => "CONCAT('LV-', x.id)", 'subject' => "CONCAT(e.first_name, ' ', e.last_name)",
            'summary' => "CONCAT(lt.name, ' · ', x.start_date, ' to ', x.end_date, ' (', x.days_requested, ' day(s))')",
            'priority' => 'NULL', 'req_by' => 'x.created_by', 'req_at' => 'x.created_at', 'pending' => "x.status IN ('pending', 'hr_reviewed')",
            'status' => 'x.status', 'extra' => "IF(x.status = 'pending', 'forward', 'approve')",
            'dec_by' => 'COALESCE(x.reviewed_by, x.hr_reviewed_by)', 'dec_at' => 'COALESCE(x.reviewed_at, x.hr_reviewed_at)',
            'sections' => ['Leave' => ['Employee' => "CONCAT(e.first_name, ' ', e.last_name)", 'Type' => 'lt.name', 'From' => 'x.start_date', 'To' => 'x.end_date', 'Days' => 'x.days_requested', 'Reason' => 'x.reason', 'HR notes' => 'x.hr_notes', 'Review notes' => 'x.review_notes']],
            'decide' => $dec('hr/leave', 'PUT', 'notes', ['q' => '&id=$id', 'b' => ['action' => '$extra']], ['q' => '&id=$id', 'b' => ['action' => 'reject']]),
        ],
        'permission_request' => [
            'roles' => ['hr_manager'], 'hr_step' => true,
            'label' => 'Permission request',
            'from' => "permission_requests x JOIN employees e ON e.id = x.employee_id",
            'ref' => "CONCAT('PM-', x.id)", 'subject' => "CONCAT(e.first_name, ' ', e.last_name)",
            'summary' => "CONCAT(x.request_date, ' ', LEFT(x.start_time, 5), '-', LEFT(x.end_time, 5), ' · ', LEFT(x.reason, 60))",
            'priority' => 'NULL', 'req_by' => 'x.created_by', 'req_at' => 'x.created_at', 'pending' => "x.status IN ('pending', 'hr_reviewed')",
            'status' => 'x.status', 'extra' => "IF(x.status = 'pending', 'forward', 'approve')",
            'dec_by' => 'COALESCE(x.reviewed_by, x.hr_reviewed_by)', 'dec_at' => 'COALESCE(x.reviewed_at, x.hr_reviewed_at)',
            'sections' => ['Permission' => ['Employee' => "CONCAT(e.first_name, ' ', e.last_name)", 'Date' => 'x.request_date', 'From' => 'x.start_time', 'To' => 'x.end_time', 'Reason' => 'x.reason', 'HR notes' => 'x.hr_notes', 'Review notes' => 'x.review_notes']],
            'decide' => $dec('hr/permissions', 'PUT', 'notes', ['q' => '&id=$id', 'b' => ['action' => '$extra']], ['q' => '&id=$id', 'b' => ['action' => 'reject']]),
        ],
        'purchase_request' => [
            'roles' => ['procurement_officer', 'accountant', 'managing_director'],   // each one only at its own stage, see ap_visible()
            'label' => 'Purchase request',
            'from' => "purchase_requests x LEFT JOIN departments d ON d.id = x.department_id",
            'ref' => 'x.pr_number', 'subject' => "CONCAT('Purchase request - ', COALESCE(d.name, ''))",
            'summary' => "CONCAT('Waiting for ', x.approval_stage, ' stage · est. ', FORMAT(COALESCE(x.total_estimated, 0), 0), ' RWF')",
            'priority' => 'x.priority', 'req_by' => 'x.requested_by', 'req_at' => 'x.created_at', 'pending' => "x.status = 'pending'",
            'status' => 'x.status', 'extra' => 'x.approval_stage',
            'dec_by' => "COALESCE(x.approved_by, (SELECT pa.decided_by FROM purchase_request_approvals pa WHERE pa.pr_id = x.id ORDER BY pa.id DESC LIMIT 1))",
            'dec_at' => "COALESCE(x.approved_at, IF(x.status = 'rejected', x.updated_at, NULL))",
            'sections' => ['Purchase request' => ['Number' => 'x.pr_number', 'Department' => 'd.name', 'Request date' => 'x.request_date', 'Required by' => 'x.required_by', 'Priority' => 'x.priority',
                                                  'Current stage' => 'x.approval_stage', 'Estimated total' => 'x.total_estimated', 'Justification' => 'x.justification', 'Notes' => 'x.notes']],
            'lines' => "SELECT CONCAT(item_description, ' × ', quantity, ' @ ', FORMAT(COALESCE(estimated_unit_price, 0), 0)) FROM purchase_request_items WHERE pr_id = ?",
            'decide' => $dec('inventory/inventory-management', 'POST', 'note', ['q' => '&action=pr_decision', 'b' => ['pr_id' => '$id', 'stage' => '$extra', 'decision' => 'approved']], ['q' => '&action=pr_decision', 'b' => ['pr_id' => '$id', 'stage' => '$extra', 'decision' => 'rejected']]),
        ],
    ];
}

// "$id" / "$extra" placeholders become the real values; the page adds the note
function ap_fill($v, int $id, ?string $extra) {
    if (is_array($v)) return array_map(fn($x) => ap_fill($x, $id, $extra), $v);
    return $v === '$id' ? $id : ($v === '$extra' ? $extra : (is_string($v) ? str_replace('$id', (string)$id, $v) : $v));
}

// What this user may see and decide. The admin: everything. Others: the kinds that list one of their roles, narrowed by
//   scope = who may SEE it at all (other departments' expenses / requests stay hidden), applied to Pending and Decided;
//   can   = what this user may decide right now (the stage a purchase request is at, HR's first step, never their own request).
// The module's own endpoint still enforces the same rules when the decision is sent.
function ap_visible(array $sources, bool $isAdmin, array $roles, int $uid, int $dept): array {
    if ($isAdmin) { unset($sources['request_supervisor']); return $sources; }   // only the chosen doctor decides the supervisor stage
    $out = [];
    foreach ($sources as $kind => $s) {
        if (empty($s['roles']) || !array_intersect($s['roles'], $roles)) continue;
        $scope = ['1=1']; $can = ['1=1'];
        if (!empty($s['dept']) && !array_intersect($s['global_roles'] ?? [], $roles)) $scope[] = "{$s['dept']} = " . max(0, $dept) . ($dept > 0 ? '' : ' AND 1=0');
        if (!empty($s['only_user'])) $scope[] = "{$s['only_user']} = $uid";
        if (!empty($s['not_own'])) $can[] = "({$s['not_own']} IS NULL OR {$s['not_own']} <> $uid)";
        if (!empty($s['hr_step'])) $can[] = "x.status = 'pending'";
        if ($kind === 'purchase_request') {   // same map as pr_stage_role_slugs in inventory-management.php
            $stages = array_keys(array_filter(['procurement' => ['procurement_officer'], 'finance' => ['accountant'], 'md' => ['managing_director']], fn($r) => array_intersect($r, $roles)));
            $can[] = "x.approval_stage IN ('" . implode("','", $stages) . "')";
        }
        $s['scope'] = implode(' AND ', $scope); $s['can'] = implode(' AND ', $can);
        $out[$kind] = $s;
    }
    return $out;
}

function ap_base_sql(array $s, string $cols): string {
    return "SELECT $cols FROM {$s['from']} LEFT JOIN users ru ON ru.id = {$s['req_by']} LEFT JOIN users du ON du.id = {$s['dec_by']}";
}

try {
    $dq = $pdo->prepare('SELECT department_id FROM users WHERE id = ?'); $dq->execute([$uid]);
    $sources = ap_visible(ap_sources(), $isAdmin, $myRoles, $uid, (int)$dq->fetchColumn());

    if ($method === 'GET' && $action === 'list') {
        $decided = ($_GET['view'] ?? 'pending') === 'decided';
        $rows = [];
        foreach ($sources as $kind => $s) {
            $scope = $s['scope'] ?? '1=1'; $can = $s['can'] ?? '1=1';
            $where = $decided ? "NOT ({$s['pending']}) AND {$s['dec_at']} >= CURDATE() - INTERVAL 30 DAY AND ($scope)" : "({$s['pending']}) AND ($can) AND ($scope)";
            $sql = ap_base_sql($s, "x.id, {$s['ref']} AS ref, {$s['subject']} AS subject, {$s['summary']} AS summary, {$s['priority']} AS priority,
                    TRIM(CONCAT(COALESCE(ru.first_name, ''), ' ', COALESCE(ru.last_name, ''))) AS requested_by, {$s['req_at']} AS requested_at, {$s['status']} AS status,
                    TRIM(CONCAT(COALESCE(du.first_name, ''), ' ', COALESCE(du.last_name, ''))) AS decided_by, {$s['dec_at']} AS decided_at")
                 . " WHERE $where ORDER BY " . ($decided ? "{$s['dec_at']} DESC" : "{$s['req_at']}") . ' LIMIT 200';
            try {
                foreach ($pdo->query($sql)->fetchAll() as $r) $rows[] = ['kind' => $kind, 'type' => $s['label']] + $r;
            } catch (PDOException $e) {
                error_log("approvals source $kind: " . $e->getMessage());   // a module whose table is missing must not hide the others
            }
        }
        $order = ['emergency' => 0, 'urgent' => 1, 'high' => 1];
        usort($rows, fn($a, $b) => $decided ? strcmp((string)$b['decided_at'], (string)$a['decided_at'])
            : (($order[strtolower((string)$a['priority'])] ?? 2) <=> ($order[strtolower((string)$b['priority'])] ?? 2) ?: strcmp((string)$a['requested_at'], (string)$b['requested_at'])));
        success(['rows' => $rows, 'kinds' => array_map(fn($s) => $s['label'], $sources)]);
    }

    if ($method === 'GET' && $action === 'count') {   // red number on the sidebar
        $n = 0;
        foreach ($sources as $kind => $s) {
            $can = $s['can'] ?? '1=1'; $scope = $s['scope'] ?? '1=1';
            try { $n += (int)$pdo->query("SELECT COUNT(*) FROM {$s['from']} WHERE ({$s['pending']}) AND ($can) AND ($scope)")->fetchColumn(); }
            catch (PDOException $e) { error_log("approvals count $kind: " . $e->getMessage()); }
        }
        success(['count' => $n]);
    }

    if ($method === 'GET' && $action === 'detail') {
        $kind = $_GET['kind'] ?? '';
        $id = (int)($_GET['id'] ?? 0);
        if (!isset($sources[$kind])) error('Unknown kind', 404);
        $s = $sources[$kind]; $can = $s['can'] ?? '1=1'; $scope = $s['scope'] ?? '1=1';
        $head = $pdo->prepare(ap_base_sql($s, "x.id, {$s['ref']} AS ref, {$s['subject']} AS subject, {$s['priority']} AS priority, {$s['status']} AS status, " . ($s['extra'] ?? 'NULL') . " AS extra,
                TRIM(CONCAT(COALESCE(ru.first_name, ''), ' ', COALESCE(ru.last_name, ''))) AS requested_by, {$s['req_at']} AS requested_at,
                TRIM(CONCAT(COALESCE(du.first_name, ''), ' ', COALESCE(du.last_name, ''))) AS decided_by, {$s['dec_at']} AS decided_at, (({$s['pending']}) AND ($can)) AS is_pending") . " WHERE x.id = ? AND ($scope)");
        $head->execute([$id]);
        $h = $head->fetch();
        if (!$h) error('Not found', 404);
        $out = ['kind' => $kind, 'type' => $s['label'], 'id' => $id, 'ref' => $h['ref'], 'subject' => $h['subject'], 'priority' => $h['priority'], 'status' => $h['status'],
                'requested_by' => $h['requested_by'], 'requested_at' => $h['requested_at'], 'decided_by' => $h['decided_by'], 'decided_at' => $h['decided_at'],
                'can_decide' => (bool)$h['is_pending'], 'sections' => [], 'lines' => []];

        if ($kind === 'transfer') {
            $q = $pdo->prepare(AP_TRANSFER_SELECT . ' WHERE t.id = ?');
            $q->execute([$id]);
            $t = $q->fetch();
            $bed = fn($w, $r, $b) => $w ? "$w · Room $r · Bed $b" : null;
            $ext = $t['transfer_type'] === 'external';
            $out['sections'] = [
                'Patient' => ['Name' => $t['patient_name'], 'Patient no' => $t['patient_no'], 'Visit' => $t['visit_no'], 'Gender' => $t['gender'], 'Date of birth' => $t['date_of_birth'], 'Blood group' => $t['blood_group'],
                              'Allergies' => $t['allergies'], 'Chronic diseases' => $t['chronic_diseases'], 'Current medication' => $t['current_medications']],
                'Clinical' => ['Diagnosis' => $t['diagnosis'], 'Reason for transfer' => $t['reason'], 'Patient condition' => $t['clinical_condition']],
                'Movement' => ['Type' => $ext ? 'External transfer' : 'Internal (bed) transfer', 'From' => $bed($t['from_ward'], $t['from_room'], $t['from_bed']),
                               ($ext ? 'Receiving facility' : 'To') => $ext ? $t['to_facility'] : $bed($t['to_ward'], $t['to_room'], $t['to_bed']),
                               'Transport' => $t['transport'], 'Accompanied by' => $t['accompanied_by'],
                               'Ambulance charge' => $t['service_name'] ? "{$t['service_name']} × {$t['charge_qty']} km — " . ((float)$t['service_price'] > 0 ? number_format($t['service_price'] * $t['charge_qty']) . ' RWF' : 'no price set in the tariff') : 'No charge'],
                'Decision' => ['Decision note' => $t['decision_note']],
            ];
            $out['lines'] = array_map(fn($o) => "{$o['order_type']}: {$o['description']} ({$o['status']})", ap_transfer_orders($pdo, $t['visit_id'] ? (int)$t['visit_id'] : null));
            $out['lines_title'] = 'Investigations on this visit (imaging, lab, procedures)';
        } else {
            foreach ($s['sections'] as $title => $fields) {
                $cols = [];
                foreach (array_values($fields) as $i => $expr) $cols[] = "$expr AS f$i";
                $q = $pdo->prepare(ap_base_sql($s, implode(', ', $cols)) . ' WHERE x.id = ?');
                $q->execute([$id]);
                $r = $q->fetch() ?: [];
                $out['sections'][$title] = [];
                foreach (array_keys($fields) as $i => $label) $out['sections'][$title][$label] = $r["f$i"] ?? null;
            }
            if (!empty($s['lines'])) {
                $q = $pdo->prepare($s['lines']);
                $q->execute([$id]);
                $out['lines'] = $q->fetchAll(PDO::FETCH_COLUMN);
                $out['lines_title'] = 'Items';
            }
        }
        if ($out['can_decide']) {
            $out['decide'] = $kind === 'transfer'
                ? ['note_key' => 'note', 'approve' => ['query' => 'approvals/approvals&action=decide', 'method' => 'POST', 'body' => ['kind' => 'transfer', 'id' => $id, 'decision' => 'approve']],
                                         'reject'  => ['query' => 'approvals/approvals&action=decide', 'method' => 'POST', 'body' => ['kind' => 'transfer', 'id' => $id, 'decision' => 'reject']]]
                : ap_fill($s['decide'], $id, $h['extra']);
        }
        success($out);
    }

    if ($method === 'POST' && $action === 'decide') {   // patient transfers only
        if (!$isAdmin) error('Only an admin can decide patient transfers.', 403);
        $decision = $input['decision'] ?? '';
        $note = trim((string)($input['note'] ?? ''));
        if (!in_array($decision, ['approve', 'reject'], true)) error('decision must be approve or reject', 422);
        if ($decision === 'reject' && $note === '') error('Give a reason for rejecting.', 422);
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT id, transfer_no, status FROM patient_transfers WHERE id = ? FOR UPDATE");
        $s->execute([(int)($input['id'] ?? 0)]);
        $t = $s->fetch();
        if (!$t) { $pdo->rollBack(); error('Not found', 404); }
        if ($t['status'] !== 'requested') { $pdo->rollBack(); error("This transfer is already {$t['status']}.", 409); }
        $new = $decision === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE patient_transfers SET status = ?, decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?")->execute([$new, $uid, $note ?: null, $t['id']]);
        log_activity($pdo, 'UPDATE', 'ward', "Transfer {$t['transfer_no']} $new" . ($note ? " | $note" : ''), (int)$t['id'], $t['transfer_no']);
        $pdo->commit();
        success(null, "Transfer {$t['transfer_no']} $new");
    }

    error('Unknown action', 404);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error('DB: ' . $e->getMessage(), 500);
}
