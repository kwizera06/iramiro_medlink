<?php
// includes/hmis_access.php — which HMIS report sections a role/user may see.
// Page access to the HMIS tab itself still comes from role_menu_access (Settings > Roles > Page Access).
// This file only narrows it: role_hmis_sections holds the sections ticked under the HMIS checkbox.
//
// A role with NO rows keeps every section (roles configured before this feature existed).
// A role that was saved from the UI always has the marker row '_set', so "nothing ticked" really means nothing.

const HMIS_SECTIONS = [
    'opd'      => 'II. OPD & Diseases',
    'malaria'  => 'III. Malaria',
    'mh'       => 'III. Mental Health',
    'chronic'  => 'IV. Chronic Conditions',
    'injuries' => 'V. Injuries',
    'hosp'     => 'VI. Hospitalizations',
    'anc'      => 'VIII. Antenatal (ANC)',
    'surgery'  => 'IX. Surgery & Anesthesia',
    'obs'      => 'XII. Obstetrics',
    'finances' => 'XIV. Finances',
    'staffing' => 'XV. Staffing',
    'lab'      => 'XIX. Laboratory',
    'cancer'   => 'Cancer Screening',
    'ins'      => 'Insurance Summary',
];

// $data key (hmis.php response) => section
const HMIS_DATA_SECTION = [
    'opd_summary' => 'opd', 'referrals' => 'opd', 'dx_review' => 'opd', 'origin' => 'opd', 'opd2' => 'opd', 'priority_diagnoses' => 'opd',
    'malaria' => 'malaria', 'mental_health' => 'mh', 'chronic' => 'chronic', 'injuries' => 'injuries',
    'hospitalization' => 'hosp', 'anc' => 'anc', 'surgery' => 'surgery', 'obstetrics' => 'obs', 'deliveries' => 'obs',
    'finances' => 'finances', 'staffing' => 'staffing', 'lab' => 'lab', 'cancer_screening' => 'cancer',
    'insurance_summary' => 'ins',
];

function hmis_ensure_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS role_hmis_sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_id INT NOT NULL,
        section_key VARCHAR(30) NOT NULL,
        UNIQUE KEY uq_role_section (role_id, section_key)
    )");
}

// Sections saved for one role. null = never configured (all sections).
function hmis_role_sections(PDO $pdo, int $roleId): ?array {
    hmis_ensure_table($pdo);
    $q = $pdo->prepare("SELECT section_key FROM role_hmis_sections WHERE role_id = ?");
    $q->execute([$roleId]);
    $keys = $q->fetchAll(PDO::FETCH_COLUMN);
    if (!$keys) return null;
    return array_values(array_intersect(array_keys(HMIS_SECTIONS), $keys));
}

function hmis_save_role_sections(PDO $pdo, int $roleId, array $sections): void {
    hmis_ensure_table($pdo);
    $sections = array_values(array_intersect(array_keys(HMIS_SECTIONS), $sections));
    $pdo->prepare("DELETE FROM role_hmis_sections WHERE role_id = ?")->execute([$roleId]);
    $ins = $pdo->prepare("INSERT INTO role_hmis_sections (role_id, section_key) VALUES (?, ?)");
    $ins->execute([$roleId, '_set']);
    foreach ($sections as $s) $ins->execute([$roleId, $s]);
}

// Sections one user may see: admin / no role = all; otherwise union over the roles that grant the HMIS page.
function hmis_user_sections(PDO $pdo, int $userId): array {
    $all = array_keys(HMIS_SECTIONS);
    if (!$userId) return $all;
    $rs = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $rs->execute([$userId]);
    $roleIds = array_map('intval', $rs->fetchAll(PDO::FETCH_COLUMN));
    if (!$roleIds) return $all;
    $ph = implode(',', array_fill(0, count($roleIds), '?'));
    $ac = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE id IN ($ph) AND slug = 'admin'");
    $ac->execute($roleIds);
    if ((int)$ac->fetchColumn() > 0) return $all;

    $grant = $pdo->prepare("SELECT DISTINCT rma.role_id FROM role_menu_access rma JOIN nav_menu_items i ON i.id = rma.menu_item_id
        WHERE i.is_active = 1 AND i.url LIKE '%report.php%sub=hmis%' AND rma.role_id IN ($ph)");
    $grant->execute($roleIds);
    $grantRoles = array_map('intval', $grant->fetchAll(PDO::FETCH_COLUMN));
    if (!$grantRoles) return $all; // HMIS reached through a personal override: no section limits exist for that

    $union = [];
    foreach ($grantRoles as $rid) {
        $sec = hmis_role_sections($pdo, $rid);
        if ($sec === null) return $all;
        $union = array_merge($union, $sec);
    }
    return array_values(array_intersect($all, array_unique($union)));
}
