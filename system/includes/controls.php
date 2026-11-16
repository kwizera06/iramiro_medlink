<?php
// includes/controls.php
// Segregation of duties: the person who requests, approves, receives, bills and pays must not be the same.
// Rules are switched off only by an administrator (hospital_settings.sod_enforce = '0'), e.g. a one-person site.

function ctl_uid(): int { return (int)($_SESSION['user_id'] ?? 0); }

function ctl_has_role(array $slugs): bool {
    return (bool)array_intersect($_SESSION['user_data']['role_slugs'] ?? [], $slugs);
}

function ctl_enforced(PDO $pdo): bool {
    $q = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key = 'sod_enforce' LIMIT 1");
    return ($q ? $q->fetchColumn() : false) !== '0';
}

// $earlier: what the current user may already have done to this record, e.g. ['requested' => 12, 'approved' => 7].
// Returns a message when the current user did one of them, null when the step is allowed.
function ctl_conflict(PDO $pdo, string $step, array $earlier): ?string {
    $me = ctl_uid();
    if (!$me || !ctl_enforced($pdo)) return null;
    foreach ($earlier as $did => $uid) {
        if ((int)$uid === $me) return "Segregation of duties: you $did this, so someone else must $step it";
    }
    return null;
}
