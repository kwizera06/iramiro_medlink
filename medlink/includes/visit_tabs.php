<?php
// includes/visit_tabs.php — Open Visit / Visit Closure switch
require_once __DIR__ . '/auth.php';
function visit_tabs(PDO $pdo, string $active): void {
    $tabs = ['open' => ['Open Visit', 'fa-door-open', 'patients.php?module=receptionist&sub=open-visit', 'patients.php?module=receptionist&sub=open-visit'],
             'closure' => ['Visit Closure', 'fa-clipboard-check', 'workflow.php?module=workflow&sub=closure', 'workflow.php?module=workflow&sub=closure']];
    $tabs = array_filter($tabs, fn($t) => canUsePage($pdo, $t[3]));
    if (count($tabs) < 2) return;
    echo "<style>#visit-mode{display:inline-flex;background:var(--gray-100);border-radius:8px;padding:3px;gap:2px;flex-wrap:wrap;margin-bottom:16px;}"
       . "#visit-mode button{padding:7px 14px;border:none;background:transparent;border-radius:6px;font-size:13px;font-weight:600;color:var(--gray-600);cursor:pointer;}"
       . "#visit-mode button.on{background:#16a34a;color:#fff;}</style><div id=\"visit-mode\">";
    foreach ($tabs as $key => [$label, $icon, $url]) {
        echo '<button type="button" class="' . ($key === $active ? 'on' : '') . '" onclick="location.href=\'' . $url . '\'"><i class="fas ' . $icon . '"></i> ' . htmlspecialchars($label) . '</button>';
    }
    echo '</div>';
}
