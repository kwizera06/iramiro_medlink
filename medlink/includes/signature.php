<?php
// includes/signature.php — #34 digital signatures and hospital stamp.
//
// For print views (lab, imaging, prescription, certificates):
//   PHP:  get_user_signature($pdo, $userId)  -> ['path','updated_at','data_uri','name'] or null
//         get_hospital_stamp($pdo)           -> data URI string or null
//         signature_block_html($pdo, $userId, $label) -> ready-made <div> with signature image, stamp and name
//   JS:   fetch('signature-file.php?action=info&user_id=N') -> {success, data:{name, signature, stamp, signed_at}}
//         (signature / stamp are data URIs, so they also work inside window.open() print windows and html2canvas)
//   <img src="signature-file.php?action=img&user_id=N">  (login required; never a public folder)

require_once __DIR__ . '/upload_safe.php';

const SIG_MAX_BYTES = 400000;      // a drawn PNG is a few KB; this is generous
const SIG_MAX_W = 1200;
const SIG_MAX_H = 600;

function sig_dir(): string { return __DIR__ . '/../uploads/signatures'; }

// Idempotent: ALTER only when a column is missing, and never inside the caller's transaction (ALTER commits it).
function sig_schema(PDO $pdo): void {
    static $done = false;
    if ($done || $pdo->inTransaction()) return;
    $done = true;
    $need = [
        'users' => ['signature_path' => 'VARCHAR(255) NULL', 'signature_updated_at' => 'DATETIME NULL'],
        'medical_documents' => ['signed_by' => 'INT NULL', 'signed_at' => 'DATETIME NULL', 'signature_path' => 'VARCHAR(255) NULL'],
    ];
    foreach ($need as $table => $cols) {
        if (!$pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn()) continue;   // medical_documents is created by its handler
        $have = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cols as $c => $def) if (!in_array($c, $have, true)) $pdo->exec("ALTER TABLE `$table` ADD `$c` $def");
    }
}

function sig_data_uri(string $relPath): ?string {
    $full = realpath(__DIR__ . '/../' . $relPath);
    $dir = realpath(sig_dir());
    if (!$full || !$dir || strpos($full, $dir . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) return null;
    return 'data:image/png;base64,' . base64_encode(file_get_contents($full));
}

function get_user_signature(PDO $pdo, int $userId): ?array {
    sig_schema($pdo);
    $q = $pdo->prepare("SELECT signature_path, signature_updated_at, CONCAT(first_name,' ',last_name) AS name FROM users WHERE id = ?");
    $q->execute([$userId]);
    $u = $q->fetch(PDO::FETCH_ASSOC);
    if (!$u || !$u['signature_path']) return null;
    $uri = sig_data_uri($u['signature_path']);
    return $uri ? ['path' => $u['signature_path'], 'updated_at' => $u['signature_updated_at'], 'data_uri' => $uri, 'name' => trim($u['name'])] : null;
}

function get_hospital_stamp(PDO $pdo): ?string {
    try {
        $q = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key = 'doc_stamp'");
        $v = $q ? (string)$q->fetchColumn() : '';
    } catch (Throwable $e) { return null; }
    return preg_match('#^data:image/(png|jpeg);base64,#', $v) ? $v : null;
}

// Signature + stamp + printed name, for server-rendered prints. Falls back to a blank line / empty stamp box.
function signature_block_html(PDO $pdo, int $userId, string $label = 'Signature'): string {
    $sig = get_user_signature($pdo, $userId);
    $stamp = get_hospital_stamp($pdo);
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    return '<div style="display:flex;gap:30px;align-items:flex-end;margin-top:24px;">'
        . '<div style="min-width:200px;text-align:center;">'
        . ($sig ? '<img src="' . $sig['data_uri'] . '" alt="Signature" style="max-height:70px;max-width:220px;">' : '<div style="height:60px;"></div>')
        . '<div style="border-top:1px solid #000;padding-top:4px;font-size:12px;">' . $h($label) . ($sig ? ': ' . $h($sig['name']) : '') . '</div></div>'
        . ($stamp ? '<img src="' . $stamp . '" alt="Official stamp" style="max-height:90px;max-width:120px;">' : '')
        . '</div>';
}

// Validate a canvas PNG data URL and store it as uploads/signatures/user_<id>_<uniq>.png.
// Old files are kept: certificates keep pointing at the signature that was valid when they were signed.
function sig_save_data_url(PDO $pdo, int $userId, string $dataUrl): string {
    sig_schema($pdo);
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) throw new RuntimeException('Signature must be a PNG image');
    $bin = base64_decode($m[1], true);
    if ($bin === false || strlen($bin) > SIG_MAX_BYTES) throw new RuntimeException('Signature image is too large');
    $dir = sig_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new RuntimeException('Cannot create folder system/uploads/signatures');
    $tmp = tempnam(sys_get_temp_dir(), 'sig');
    file_put_contents($tmp, $bin);
    try {
        $info = @getimagesize($tmp);
        if (upload_ext_from_mime($tmp) !== 'png' || !$info || $info[2] !== IMAGETYPE_PNG || $info[0] > SIG_MAX_W || $info[1] > SIG_MAX_H || $info[0] < 20 || $info[1] < 20) {
            throw new RuntimeException('Not a valid signature image');
        }
        $name = 'user_' . $userId . '_' . bin2hex(random_bytes(6)) . '.png';
        if (!@rename($tmp, $dir . '/' . $name) && !@copy($tmp, $dir . '/' . $name)) throw new RuntimeException('Failed to save signature');
    } finally { if (is_file($tmp)) @unlink($tmp); }
    $path = 'uploads/signatures/' . $name;
    $pdo->prepare("UPDATE users SET signature_path = ?, signature_updated_at = NOW() WHERE id = ?")->execute([$path, $userId]);
    return $path;
}
