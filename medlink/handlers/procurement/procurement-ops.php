<?php
// handlers/procurement/procurement-ops.php
// Procurement screens the older inventory/finance handlers do not cover: supplier status + documents, proforma invoices,
// expected deliveries, delivery discrepancies (with follow-up) and supplier-invoice exceptions.
// Contracts, supplier bills and payments keep using finance/finance-ops (see PC_FINANCE_ACTIONS in includes/procurement_schema.php).

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/procurement_rules.php';
require_once __DIR__ . '/../../includes/procurement_schema.php';
require_once __DIR__ . '/../../includes/controls.php';
require_once __DIR__ . '/../../includes/upload_safe.php';
pc_migrate($pdo);

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

const SUPPLIER_STATUSES = ['active', 'inactive', 'suspended', 'blacklisted'];
const UNBILLED_DAYS = 30; // goods received but still not billed after this long are an exception

function dateOrNull($v, string $label): ?string {
    $v = trim((string)$v);
    if ($v === '') return null;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) error("$label is not a valid date", 422);
    return $v;
}

// Store an uploaded file under uploads/suppliers and return [original, stored, mime, size]; null when no file was sent.
function storeUpload(string $field = 'file'): ?array {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) error('The file could not be uploaded', 422);
    if ($f['size'] > 10 * 1024 * 1024) error('Files must be 10 MB or smaller', 422);
    $ext = upload_ext_from_mime($f['tmp_name']);
    if (!$ext) error('That file type is not allowed (use PDF, image, Word or Excel)', 422);
    $dir = __DIR__ . '/../../uploads/suppliers';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) error('Could not create the document folder', 500);
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$stored")) error('Could not store the file', 500);
    return [substr(basename($f['name']), 0, 255), $stored, (string)(new finfo(FILEINFO_MIME_TYPE))->file("$dir/$stored"), (int)$f['size']];
}

function sendStored(string $stored, string $name, string $mime): void {
    $path = __DIR__ . '/../../uploads/suppliers/' . basename($stored);
    if (!is_file($path)) error('The file is missing from storage', 404);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST; // multipart forms (documents, proformas)
$uid    = ctl_uid() ?: null;
$today  = date('Y-m-d');

try {

// =====================================================================
if ($method === 'GET') {

    if ($action === 'suppliers_all') {
        success($pdo->query("SELECT s.id, s.supplier_code, s.company_name, s.contact_person, s.phone, s.email, s.category, s.tax_id, s.status, s.status_reason, s.status_changed_at,
                (SELECT COUNT(*) FROM supplier_documents d WHERE d.supplier_id = s.id) AS docs,
                (SELECT COUNT(*) FROM supplier_documents d WHERE d.supplier_id = s.id AND d.expiry_date < CURDATE()) AS docs_expired
             FROM suppliers s ORDER BY s.company_name")->fetchAll());
    }

    if ($action === 'supplier_documents') {
        $q = $pdo->prepare("SELECT d.id, d.supplier_id, d.doc_type, d.title, d.original_name, d.mime_type, d.file_size, d.expiry_date, d.verified_at, d.uploaded_at, s.company_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS verified_by_name,
                    CASE WHEN d.expiry_date IS NULL THEN 'no_expiry' WHEN d.expiry_date < CURDATE() THEN 'expired' WHEN d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring' ELSE 'valid' END AS state
                FROM supplier_documents d JOIN suppliers s ON s.id = d.supplier_id LEFT JOIN users u ON u.id = d.verified_by
                WHERE (? = 0 OR d.supplier_id = ?) ORDER BY d.expiry_date IS NULL, d.expiry_date, d.id DESC");
        $sid = (int)($_GET['supplier_id'] ?? 0);
        $q->execute([$sid, $sid]);
        success($q->fetchAll());
    }

    if ($action === 'supplier_document_file') {
        $q = $pdo->prepare("SELECT original_name, stored_name, mime_type FROM supplier_documents WHERE id = ?");
        $q->execute([(int)($_GET['id'] ?? 0)]);
        $d = $q->fetch();
        if (!$d) error('Document not found', 404);
        sendStored($d['stored_name'], $d['original_name'], (string)$d['mime_type']);
    }

    if ($action === 'proformas') {
        success($pdo->query("SELECT pf.*, s.company_name, po.po_number, po.total_amount AS po_total, pr.pr_number,
                    (pf.stored_name IS NOT NULL) AS has_file,
                    (pf.status = 'pending' AND pf.valid_until IS NOT NULL AND pf.valid_until < CURDATE()) AS expired
                FROM supplier_proformas pf JOIN suppliers s ON s.id = pf.supplier_id
                LEFT JOIN purchase_orders po ON po.id = pf.po_id LEFT JOIN purchase_requests pr ON pr.id = pf.pr_id
                ORDER BY pf.id DESC LIMIT 300")->fetchAll());
    }

    if ($action === 'proforma_file') {
        $q = $pdo->prepare("SELECT original_name, stored_name, mime_type FROM supplier_proformas WHERE id = ? AND stored_name IS NOT NULL");
        $q->execute([(int)($_GET['id'] ?? 0)]);
        $d = $q->fetch();
        if (!$d) error('No file attached to this proforma', 404);
        sendStored($d['stored_name'], $d['original_name'], (string)$d['mime_type']);
    }

    // open POs (approved, not yet fully received), most overdue first
    if ($action === 'expected_deliveries') {
        $rows = $pdo->query("SELECT po.id, po.po_number, po.order_date, po.delivery_date AS expected_date, po.delivery_status, po.carrier, po.tracking_ref, po.delivery_note, po.status, po.total_amount,
                    s.company_name AS supplier_name,
                    (SELECT COALESCE(SUM(quantity_ordered), 0) FROM purchase_order_items WHERE po_id = po.id) AS ordered_qty,
                    (SELECT COALESCE(SUM(quantity_received), 0) FROM purchase_order_items WHERE po_id = po.id) AS received_qty
                FROM purchase_orders po LEFT JOIN suppliers s ON s.id = po.supplier_id
                WHERE po.status IN ('confirmed', 'partially_received')
                ORDER BY po.delivery_date IS NULL, po.delivery_date, po.id")->fetchAll();
        foreach ($rows as &$r) {
            $days = $r['expected_date'] ? (int)floor((strtotime($r['expected_date']) - strtotime($today)) / 86400) : null;
            $r['days_left'] = $days;
            $r['state'] = $days === null ? 'no_date' : ($days < 0 ? 'overdue' : ($days <= 7 ? 'due_soon' : 'on_track'));
        }
        success($rows);
    }

    // rejected / damaged goods and short deliveries, with the follow-up note
    if ($action === 'discrepancies') {
        $rows = $pdo->query("SELECT gi.id AS grn_item_id, g.id AS grn_id, g.grn_number, g.received_date, g.receiving_type, s.company_name AS supplier_name, po.po_number,
                    COALESCE(i.name, poi.item_description) AS item_name, gi.batch_number, gi.quantity_ordered, gi.quantity_received, gi.quantity_accepted, gi.quantity_rejected, gi.notes,
                    COALESCE(n.status, 'open') AS follow_status, n.note AS follow_note, n.updated_at AS follow_at
                FROM goods_receiving_items gi
                JOIN goods_receiving_notes g ON g.id = gi.grn_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN purchase_orders po ON po.id = g.po_id
                LEFT JOIN items i ON i.id = gi.item_id
                LEFT JOIN purchase_order_items poi ON poi.id = gi.po_item_id
                LEFT JOIN grn_discrepancy_notes n ON n.grn_item_id = gi.id
                WHERE gi.quantity_rejected > 0 OR gi.quantity_accepted < gi.quantity_received OR (g.receiving_type = 'full' AND gi.quantity_received < gi.quantity_ordered)
                ORDER BY COALESCE(n.status, 'open') = 'resolved', g.received_date DESC, gi.id DESC LIMIT 300")->fetchAll();
        foreach ($rows as &$r) {
            $short = (float)$r['quantity_ordered'] - (float)$r['quantity_received'];
            $bad = max((float)$r['quantity_rejected'], (float)$r['quantity_received'] - (float)$r['quantity_accepted']);
            $r['kind'] = $bad > 0 ? 'rejected' : 'short';
            $r['short_qty'] = $r['receiving_type'] === 'full' && $short > 0 ? $short : 0;
            $r['bad_qty'] = $bad;
        }
        success($rows);
    }

    // supplier-bill problems: duplicates, price differences against the PO / accepted proforma, billed without delivery, unbilled or overdue
    if ($action === 'invoice_exceptions') {
        $out = [];
        $add = function (string $type, string $severity, array $r, string $detail) use (&$out) {
            $out[] = ['type' => $type, 'severity' => $severity, 'supplier' => $r['company_name'] ?? $r['supplier_name'] ?? '', 'ref' => $r['ref'] ?? '', 'detail' => $detail,
                      'amount' => isset($r['amount']) ? (float)$r['amount'] : null, 'date' => $r['date'] ?? null];
        };
        $live = "si.status <> 'voided'";

        foreach ($pdo->query("SELECT si.id, s.company_name, si.invoice_number AS ref, si.invoice_date AS date, si.amount FROM supplier_invoices si JOIN suppliers s ON s.id = si.supplier_id
                WHERE $live AND EXISTS (SELECT 1 FROM supplier_invoices x WHERE x.id <> si.id AND x.status <> 'voided' AND x.supplier_id = si.supplier_id
                      AND (LOWER(TRIM(x.invoice_number)) = LOWER(TRIM(si.invoice_number)) OR (x.amount = si.amount AND x.invoice_date = si.invoice_date)))")->fetchAll() as $r)
            $add('duplicate', 'high', $r, 'Same supplier has another bill with this number, or the same amount on the same date');

        foreach ($pdo->query("SELECT g.grn_number AS ref, g.received_date AS date, s.company_name, po.po_number, gi.unit_cost, poi.unit_price, gi.quantity_accepted,
                    si.invoice_number, ROUND((gi.unit_cost - poi.unit_price) * gi.quantity_accepted, 2) AS amount
                FROM goods_receiving_items gi JOIN goods_receiving_notes g ON g.id = gi.grn_id
                JOIN purchase_order_items poi ON poi.id = gi.po_item_id JOIN purchase_orders po ON po.id = g.po_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id LEFT JOIN supplier_invoices si ON si.id = g.supplier_invoice_id AND si.status <> 'voided'
                WHERE ABS(gi.unit_cost - poi.unit_price) > 0.005")->fetchAll() as $r)
            $add('price_difference', 'high', $r, "PO {$r['po_number']} price " . number_format($r['unit_price'], 2) . ' but received at ' . number_format($r['unit_cost'], 2) . ($r['invoice_number'] ? " (billed on {$r['invoice_number']})" : ' (not billed yet)'));

        foreach ($pdo->query("SELECT pf.proforma_number AS ref, pf.issue_date AS date, s.company_name, po.po_number, pf.amount AS pf_amount, po.total_amount, ROUND(po.total_amount - pf.amount, 2) AS amount
                FROM supplier_proformas pf JOIN purchase_orders po ON po.id = pf.po_id JOIN suppliers s ON s.id = pf.supplier_id
                WHERE pf.status = 'accepted' AND po.status <> 'cancelled' AND ABS(po.total_amount - pf.amount) > 1")->fetchAll() as $r)
            $add('price_difference', 'medium', $r, "PO {$r['po_number']} total " . number_format($r['total_amount'], 2) . ' differs from accepted proforma ' . number_format($r['pf_amount'], 2));

        foreach ($pdo->query("SELECT si.id, s.company_name, si.invoice_number AS ref, si.invoice_date AS date, si.amount FROM supplier_invoices si JOIN suppliers s ON s.id = si.supplier_id
                WHERE $live AND NOT EXISTS (SELECT 1 FROM goods_receiving_notes g WHERE g.supplier_invoice_id = si.id)")->fetchAll() as $r)
            $add('billed_not_delivered', 'high', $r, 'Billed, but no goods received note is matched to this bill');

        $gq = $pdo->query("SELECT g.id, g.grn_number AS ref, g.received_date AS date, s.company_name FROM goods_receiving_notes g LEFT JOIN suppliers s ON s.id = g.supplier_id
                WHERE g.supplier_invoice_id IS NULL AND g.received_date <= DATE_SUB(CURDATE(), INTERVAL " . UNBILLED_DAYS . " DAY)");
        foreach ($gq->fetchAll() as $r) {
            $r['amount'] = fin_grn_open_value($pdo, (int)$r['id']);
            if ($r['amount'] > 0) $add('unbilled_delivery', 'medium', $r, 'Goods received more than ' . UNBILLED_DAYS . ' days ago and still not billed');
        }

        foreach ($pdo->query("SELECT si.id, s.company_name, si.invoice_number AS ref, si.due_date AS date, ROUND(si.amount - si.paid_amount, 2) AS amount FROM supplier_invoices si JOIN suppliers s ON s.id = si.supplier_id
                WHERE si.status IN ('posted', 'partially_paid') AND si.due_date IS NOT NULL AND si.due_date < CURDATE()")->fetchAll() as $r)
            $add('overdue', 'medium', $r, 'Payment was due on ' . $r['date']);

        foreach ($pdo->query("SELECT si.id, s.company_name, si.invoice_number AS ref, si.invoice_date AS date, ROUND(si.amount - si.paid_amount, 2) AS amount, s.status AS supplier_status FROM supplier_invoices si JOIN suppliers s ON s.id = si.supplier_id
                WHERE si.status IN ('posted', 'partially_paid') AND s.status IN ('suspended', 'blacklisted')")->fetchAll() as $r)
            $add('restricted_supplier', 'medium', $r, 'Supplier is ' . $r['supplier_status'] . ' with this bill still unpaid');

        usort($out, fn($a, $b) => [$a['severity'] === 'high' ? 0 : 1, $b['date'] ?? ''] <=> [$b['severity'] === 'high' ? 0 : 1, $a['date'] ?? '']);
        success($out);
    }

    error('Unknown action', 404);
}

// =====================================================================
if ($method === 'POST') {

    if ($action === 'supplier_status') {
        $id = (int)($input['id'] ?? 0); $st = (string)($input['status'] ?? ''); $reason = trim((string)($input['reason'] ?? ''));
        if (!in_array($st, SUPPLIER_STATUSES, true)) error('Choose active, inactive, suspended or blacklisted', 422);
        if ($st !== 'active' && $reason === '') error('Give the reason for setting this supplier ' . $st, 422);
        $q = $pdo->prepare('SELECT company_name, status FROM suppliers WHERE id = ?'); $q->execute([$id]); $s = $q->fetch();
        if (!$s) error('Supplier not found', 404);
        if ($s['status'] === $st) error('The supplier is already ' . $st, 409);
        $pdo->prepare('UPDATE suppliers SET status = ?, is_active = ?, status_reason = ?, status_changed_at = NOW(), status_changed_by = ? WHERE id = ?')
            ->execute([$st, $st === 'active' ? 1 : 0, $st === 'active' ? null : $reason, $uid, $id]);
        log_activity($pdo, 'update', 'procurement', "Supplier {$s['company_name']} set $st" . ($reason !== '' ? ": $reason" : ''), $id);
        success(null, "Supplier is now $st");
    }

    if ($action === 'supplier_document_upload') {
        $sid = (int)($input['supplier_id'] ?? 0);
        $q = $pdo->prepare('SELECT company_name FROM suppliers WHERE id = ?'); $q->execute([$sid]); $name = $q->fetchColumn();
        if (!$name) error('Supplier not found', 404);
        $type = trim((string)($input['doc_type'] ?? '')); $title = trim((string)($input['title'] ?? ''));
        if ($type === '' || $title === '') error('Document type and title are required', 422);
        $expiry = dateOrNull($input['expiry_date'] ?? '', 'Expiry date');
        $f = storeUpload();
        if (!$f) error('Choose the document file', 422);
        $pdo->prepare('INSERT INTO supplier_documents (supplier_id, doc_type, title, original_name, stored_name, mime_type, file_size, expiry_date, uploaded_by) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$sid, $type, $title, $f[0], $f[1], $f[2], $f[3], $expiry, $uid]);
        log_activity($pdo, 'create', 'procurement', "Document \"$title\" added for $name", (int)$pdo->lastInsertId());
        success(['id' => (int)$pdo->lastInsertId()], 'Document saved', 201);
    }

    if ($action === 'supplier_document_verify') {
        $id = (int)($input['id'] ?? 0);
        $n = $pdo->prepare('UPDATE supplier_documents SET verified_by = ?, verified_at = NOW() WHERE id = ?');
        $n->execute([$uid, $id]);
        if (!$n->rowCount()) error('Document not found or already verified', 404);
        success(null, 'Document marked as verified');
    }

    if ($action === 'supplier_document_delete') {
        $id = (int)($input['id'] ?? 0);
        $q = $pdo->prepare('SELECT stored_name, title FROM supplier_documents WHERE id = ?'); $q->execute([$id]); $d = $q->fetch();
        if (!$d) error('Document not found', 404);
        $pdo->prepare('DELETE FROM supplier_documents WHERE id = ?')->execute([$id]);
        @unlink(__DIR__ . '/../../uploads/suppliers/' . basename($d['stored_name']));
        log_activity($pdo, 'delete', 'procurement', "Supplier document \"{$d['title']}\" removed", $id);
        success(null, 'Document removed');
    }

    if ($action === 'proforma_save') {
        $sid = (int)($input['supplier_id'] ?? 0);
        $q = $pdo->prepare('SELECT company_name, status FROM suppliers WHERE id = ?'); $q->execute([$sid]); $s = $q->fetch();
        if (!$s) error('Supplier not found', 404);
        if ($s['status'] !== 'active') error('This supplier is ' . $s['status'] . '; proformas can only be registered for active suppliers', 409);
        $no = trim((string)($input['proforma_number'] ?? '')); if ($no === '') error('Proforma number is required', 422);
        $issue = dateOrNull($input['issue_date'] ?? '', 'Issue date') ?: $today;
        $until = dateOrNull($input['valid_until'] ?? '', 'Valid-until date');
        if ($until && $until < $issue) error('Valid-until is before the issue date', 422);
        $amount = round((float)($input['amount'] ?? 0), 2); if ($amount <= 0) error('The proforma amount must be greater than zero', 422);
        $po = !empty($input['po_id']) ? (int)$input['po_id'] : null; $pr = !empty($input['pr_id']) ? (int)$input['pr_id'] : null;
        if ($po) {
            $q = $pdo->prepare('SELECT supplier_id FROM purchase_orders WHERE id = ?'); $q->execute([$po]); $ps = $q->fetchColumn();
            if ($ps === false) error('Purchase order not found', 404);
            if ((int)$ps !== $sid) error('That purchase order belongs to a different supplier', 422);
        }
        $q = $pdo->prepare('SELECT 1 FROM supplier_proformas WHERE supplier_id = ? AND proforma_number = ?'); $q->execute([$sid, $no]);
        if ($q->fetchColumn()) error('This supplier already has a proforma with that number', 409);
        $f = storeUpload();
        $pdo->prepare('INSERT INTO supplier_proformas (proforma_number, supplier_id, pr_id, po_id, issue_date, valid_until, amount, notes, original_name, stored_name, mime_type, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$no, $sid, $pr, $po, $issue, $until, $amount, trim((string)($input['notes'] ?? '')) ?: null, $f[0] ?? null, $f[1] ?? null, $f[2] ?? null, $uid]);
        $id = (int)$pdo->lastInsertId();
        log_activity($pdo, 'create', 'procurement', "Proforma $no from {$s['company_name']} registered: " . number_format($amount), $id);
        success(['id' => $id], 'Proforma registered', 201);
    }

    if ($action === 'proforma_decide') {
        $id = (int)($input['id'] ?? 0); $dec = (string)($input['decision'] ?? ''); $note = trim((string)($input['note'] ?? ''));
        if (!in_array($dec, ['accepted', 'rejected'], true)) error('Choose accept or reject', 422);
        if ($dec === 'rejected' && $note === '') error('Give the reason for rejecting this proforma', 422);
        $q = $pdo->prepare('SELECT proforma_number, status, valid_until FROM supplier_proformas WHERE id = ?'); $q->execute([$id]); $p = $q->fetch();
        if (!$p) error('Proforma not found', 404);
        if ($p['status'] !== 'pending') error('This proforma was already ' . $p['status'], 409);
        if ($dec === 'accepted' && $p['valid_until'] && $p['valid_until'] < $today) error('This proforma expired on ' . $p['valid_until'] . ' and cannot be accepted', 409);
        $pdo->prepare('UPDATE supplier_proformas SET status = ?, decision_note = ?, decided_by = ?, decided_at = NOW() WHERE id = ?')->execute([$dec, $note ?: null, $uid, $id]);
        log_activity($pdo, 'update', 'procurement', "Proforma {$p['proforma_number']} $dec" . ($note !== '' ? ": $note" : ''), $id);
        success(null, "Proforma $dec");
    }

    if ($action === 'discrepancy_note') {
        $gi = (int)($input['grn_item_id'] ?? 0); $st = (string)($input['status'] ?? 'open'); $note = trim((string)($input['note'] ?? ''));
        if (!in_array($st, ['open', 'resolved'], true)) error('Choose open or resolved', 422);
        if ($st === 'resolved' && $note === '') error('Say how this was resolved (credit note, replacement, return...)', 422);
        $q = $pdo->prepare('SELECT 1 FROM goods_receiving_items WHERE id = ?'); $q->execute([$gi]);
        if (!$q->fetchColumn()) error('Delivery line not found', 404);
        $pdo->prepare('INSERT INTO grn_discrepancy_notes (grn_item_id, status, note, updated_by) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), updated_by = VALUES(updated_by)')
            ->execute([$gi, $st, $note ?: null, $uid]);
        log_activity($pdo, 'update', 'procurement', "Delivery discrepancy on GRN line #$gi marked $st", $gi);
        success(null, $st === 'resolved' ? 'Marked as resolved' : 'Follow-up saved');
    }

    error('Unknown action', 404);
}

error('Method not allowed', 405);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error($e->getMessage(), 500);
}
