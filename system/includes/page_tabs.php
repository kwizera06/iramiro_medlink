<?php
// includes/page_tabs.php — a green "slide" switch between two pages that are worked together (Nurse Portal / Procedures and Billing,
// Open Visit / Visit Closure), so the user does not go back to the sidebar. Each tab is [label, icon, url, script, sub]; a tab only shows
// when the user may open that page, and nothing is drawn when there is only one tab left.
require_once __DIR__ . '/page_guard.php';

function page_tabs(PDO $pdo, string $id, array $tabs, string $active): void {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $tabs = array_filter($tabs, fn($t) => pg_has_page_access($pdo, $uid, $t[3], $t[4]));
    if (count($tabs) < 2) return;
    $id = preg_replace('/[^a-z0-9-]/i', '', $id);
    echo "<style>#$id{display:inline-flex;background:var(--gray-100);border-radius:8px;padding:3px;gap:2px;flex-wrap:wrap;margin-bottom:16px;}"
       . "#$id button{padding:7px 14px;border:none;background:transparent;border-radius:6px;font-size:13px;font-weight:600;color:var(--gray-600);cursor:pointer;transition:background-color 140ms ease-out,color 140ms ease-out;}"
       . "#$id button.on{background:#16a34a;color:#fff;box-shadow:0 1px 2px rgba(22,163,74,.35);}</style><div id=\"$id\">";
    foreach ($tabs as $key => [$label, $icon, $url]) {
        echo '<button type="button" class="' . ($key === $active ? 'on' : '') . '" onclick="location.href=\'' . $url . '\'"><i class="fas ' . $icon . '"></i> ' . htmlspecialchars($label) . '</button>';
    }
    echo '</div>';
}
