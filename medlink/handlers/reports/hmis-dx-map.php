<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/reports/hmis-dx-map.php — map each distinct consultation diagnosis to an HMIS priority row.
//   GET  ?months=24&search=&only=unmapped|all   -> distinct diagnoses with usage, saved row and suggested row
//   POST {items:[{dx_key,row_key}]}             -> row_key "1".."23", "none" (not a priority problem) or "" (remove)
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/activity_log.php';
require_once __DIR__ . '/../../includes/hmis_schema.php';
require_once __DIR__ . '/../../includes/hmis_access.php';
require_once __DIR__ . '/../../includes/hmis_dxmap.php';

header('Content-Type: application/json');
function jr($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function ok($d = null, $m = 'Success') { jr(['success' => true, 'message' => $m, 'data' => $d]); }
function err($m, $s = 400) { jr(['success' => false, 'message' => $m], $s); }

// Same people who may see the OPD section of the HMIS report may edit its diagnosis mapping
if (!in_array('opd', hmis_user_sections($pdo, (int)($_SESSION['user_id'] ?? 0)), true)) err('Not allowed', 403);

try {
    hmis_ensure_schema($pdo);
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $months = max(1, min(60, (int)($_GET['months'] ?? 24)));
        $only = ($_GET['only'] ?? 'unmapped') === 'all' ? 'all' : 'unmapped';
        $search = mb_strtolower(trim((string)($_GET['search'] ?? '')));
        $map = hmis_dx_map_load($pdo);
        $rules = hmis_priority_rules();

        $q = $pdo->prepare("SELECT c.diagnosis FROM consultations c JOIN visits v ON v.id = c.visit_id
            WHERE v.visit_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) AND v.status <> 'cancelled' AND c.diagnosis IS NOT NULL AND c.diagnosis <> ''");
        $q->execute([$months]);
        $uses = []; $sample = [];
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $dx) {
            foreach (hmis_dx_tokens($dx) as $k => $txt) { $uses[$k] = ($uses[$k] ?? 0) + 1; if (!isset($sample[$k])) $sample[$k] = $txt; }
        }
        arsort($uses);
        $rows = []; $mapped = 0;
        foreach ($uses as $k => $n) {
            $saved = $map[$k] ?? '';
            if ($saved !== '') $mapped++;
            if ($only === 'unmapped' && $saved !== '') continue;
            if ($search !== '' && mb_strpos($k, $search) === false) continue;
            $sug = '';
            if ($saved === '') { foreach (HMIS_PRIORITY_ORDER as $r) { if (matchAny($k, $rules[$r])) { $sug = (string)$r; break; } } }
            $rows[] = ['dx_key' => $k, 'text' => $sample[$k], 'uses' => $n, 'row_key' => $saved, 'suggested' => $sug];
        }
        ok(['rows' => array_slice($rows, 0, 800), 'shown' => count($rows), 'distinct' => count($uses), 'mapped' => $mapped,
            'rows_labels' => HMIS_PRIORITY_LABELS]);
    }

    if ($method === 'POST') {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        $items = $in['items'] ?? [];
        if (!is_array($items) || !$items) err('Nothing to save', 422);
        $up = $pdo->prepare("INSERT INTO hmis_dx_map (dx_key, row_key, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE row_key=VALUES(row_key), updated_by=VALUES(updated_by)");
        $del = $pdo->prepare("DELETE FROM hmis_dx_map WHERE dx_key=?");
        $uid = (int)($_SESSION['user_id'] ?? 0) ?: null;
        $saved = 0; $removed = 0;
        foreach ($items as $it) {
            $k = hmis_dx_norm((string)($it['dx_key'] ?? ''));
            $r = (string)($it['row_key'] ?? '');
            if ($k === '') continue;
            if ($r === '') { $del->execute([$k]); $removed++; continue; }
            if ($r !== 'none' && !isset(HMIS_PRIORITY_LABELS[(int)$r])) err("Unknown HMIS row: $r", 422);
            $up->execute([$k, $r === 'none' ? 'none' : (string)(int)$r, $uid]);
            $saved++;
        }
        log_activity($pdo, 'UPDATE', 'reports', "HMIS diagnosis mapping: $saved saved, $removed removed");
        ok(['saved' => $saved, 'removed' => $removed], 'Mapping saved');
    }
    err('Method not allowed', 405);
} catch (PDOException $e) {
    err('Database error: ' . $e->getMessage(), 500);
}
