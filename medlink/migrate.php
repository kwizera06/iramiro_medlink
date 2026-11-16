<?php
// migrate.php (2026-11-13): (a) CBC panel (service_catalog #3052) parameters; (b) sidebar: Admitted Care in Admission & Beds.
//  CBC: remove LYMP#, MXD#, GRA#, MCH, MCHC; rename MXD% -> MID%; add RDW-C%
//  1. remove LYMP#, MXD#, GRA#, MCH, MCHC
//  2. rename MXD% -> MID%
//  3. add RDW-C%
// Safe to re-run. Open /medlink/migrate.php or `php migrate.php`. Add ?preview=1 (or --preview) to only report.
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/database.php';
$preview = isset($_GET['preview']) || in_array('--preview', $argv ?? [], true);
$svc = 3052;
$drop = ['LYMP#', 'MXD#', 'GRA#', 'MCH', 'MCHC'];
$in = implode(',', array_fill(0, count($drop), '?'));
$c = $pdo->prepare("SELECT COUNT(*) FROM lab_test_parameters WHERE service_id = ? AND test_name IN ($in)");
$c->execute([$svc, ...$drop]);
$n = (int)$c->fetchColumn();
if (!$preview) $pdo->prepare("DELETE FROM lab_test_parameters WHERE service_id = ? AND test_name IN ($in)")->execute([$svc, ...$drop]);
echo "$n parameter(s) " . ($preview ? 'would be ' : '') . "removed.\n";
$c = $pdo->prepare("SELECT COUNT(*) FROM lab_test_parameters WHERE service_id = ? AND test_name = 'MXD%'");
$c->execute([$svc]);
$n = (int)$c->fetchColumn();
if (!$preview) $pdo->prepare("UPDATE lab_test_parameters SET test_name = 'MID%' WHERE service_id = ? AND test_name = 'MXD%'")->execute([$svc]);
echo "$n parameter(s) " . ($preview ? 'would be ' : '') . "renamed MXD% -> MID%.\n";
// 3. add RDW-C% (11.5 - 14.5 %) to the CBC panel
$c = $pdo->prepare("SELECT COUNT(*) FROM lab_test_parameters WHERE service_id = ? AND test_name = 'RDW-C%'");
$c->execute([$svc]);
$n = (int)$c->fetchColumn() ? 0 : 1;
if ($n && !$preview) $pdo->prepare("INSERT INTO lab_test_parameters (service_id, test_name, param_type, unit, normal_min, normal_max) VALUES (?, 'RDW-C%', 'numeric', '%', 11.5, 14.5)")->execute([$svc]);
echo "$n parameter(s) " . ($preview ? 'would be ' : '') . "added: RDW-C%.\n";

// 4. Sidebar: "Nursing Care" -> "Admitted Care", moved to Admission & Beds (after Admitted Patients), open to doctor + nurse roles
$q = $pdo->query("SELECT id, menu_id, title, group_label FROM nav_menu_items WHERE url LIKE 'nursing.php?module=nursing&sub=patients%' AND is_active = 1 ORDER BY id LIMIT 1");
$nav = $q->fetch();
$ref = $pdo->query("SELECT item_order FROM nav_menu_items WHERE url LIKE 'ward.php?module=ward&sub=admitted%' AND group_label = 'Admission & Beds' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
if ($nav && $ref !== false) {
    if (!$preview) {
        $pdo->prepare("UPDATE nav_menu_items SET item_order = item_order + 1 WHERE menu_id = ? AND item_order > ? AND id <> ?")->execute([$nav['menu_id'], $ref, $nav['id']]);
        $pdo->prepare("UPDATE nav_menu_items SET title = 'Admitted Care', group_label = 'Admission & Beds', item_order = ? WHERE id = ?")->execute([(int)$ref + 1, $nav['id']]);
        $pdo->prepare("INSERT IGNORE INTO role_menu_access (role_id, menu_item_id) SELECT id, ? FROM roles WHERE slug IN ('doctor','nurse')")->execute([$nav['id']]);
    }
    echo "Sidebar item #{$nav['id']} " . ($preview ? 'would be ' : '') . "set to Admitted Care / Admission & Beds, doctor + nurse access.\n";
} else echo "Sidebar item not found, skipped.\n";
