<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/patients/attachments.php — consent form uploads (list + upload only; no delete from the UI)
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/upload_safe.php';
require_once __DIR__ . '/../../includes/attachment_schema.php';
ensure_patient_attachments($pdo);

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

$method = $_SERVER['REQUEST_METHOD'];

// GET ?patient_id=X — this patient's attachments (newest first), visit_id shown so the modal can mark this visit's
if ($method === 'GET') {
    $pid = (int)($_GET['patient_id'] ?? 0);
    if (!$pid) error('patient_id required', 422);
    $q = $pdo->prepare("SELECT a.id, a.visit_id, a.type, a.file_path, a.original_name, a.note, a.created_at, v.visit_no,
                               COALESCE(CONCAT(u.first_name,' ',u.last_name), u.username) AS uploaded_by_name
                        FROM patient_attachments a LEFT JOIN visits v ON v.id = a.visit_id LEFT JOIN users u ON u.id = a.uploaded_by
                        WHERE a.patient_id = ? ORDER BY a.id DESC");
    $q->execute([$pid]);
    success($q->fetchAll());
}

// POST multipart: patient_id, visit_id, file, note — type is always 'consent'
if ($method === 'POST') {
    $pid = (int)($_POST['patient_id'] ?? 0);
    $vid = (int)($_POST['visit_id'] ?? 0);
    if (!$pid) error('patient_id required', 422);
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) error('File upload failed', 400);
    $file = $_FILES['file'];
    if ((int)$file['size'] > 10 * 1024 * 1024) error('File size exceeds the 10MB limit', 422);
    $ext = upload_ext_from_mime($file['tmp_name']);
    if (!in_array($ext, ['pdf', 'jpg', 'png'], true)) error('Only PDF, JPG or PNG files are allowed', 422);
    if ($vid) {
        $chk = $pdo->prepare("SELECT visit_no FROM visits WHERE id = ? AND patient_id = ?");
        $chk->execute([$vid, $pid]);
        $visitNo = $chk->fetchColumn();
        if ($visitNo === false) error('Visit does not belong to this patient', 422);
    }
    $dir = __DIR__ . '/../../uploads/consent/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) error('Unable to create the upload directory', 500);
    $name = 'consent_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) error('Failed to save the file', 500);
    $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 500);
    $pdo->prepare("INSERT INTO patient_attachments (patient_id, visit_id, type, file_path, original_name, note, uploaded_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([$pid, $vid ?: null, 'consent', 'uploads/consent/' . $name, mb_substr(basename((string)$file['name']), 0, 255), $note ?: null, $_SESSION['user_id'] ?? null]);
    $newId = (int)$pdo->lastInsertId();
    log_activity($pdo, 'CREATE', 'patients', 'Consent form uploaded for patient #' . $pid . ($vid ? ' (visit ' . $visitNo . ')' : ''), $newId, $vid ? $visitNo : null);
    success(['id' => $newId], 'Consent form uploaded');
}

error('Method not allowed', 405);
