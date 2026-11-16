<?php
// includes/tariff_schema.php — #41 tariffs: insurer tariff group, per-group catalog prices, residence zone.
// Idempotent; call outside a transaction (DDL commits). Prices load only into empty values.
// Settings (hospital_settings): service_zone_sectors (comma separated sectors that are "in zone"),
// service_zone_out_surcharge_pct (nullable, default 0; nothing applies it automatically, pricing for out-of-zone is an open client question).

function tariff_ensure_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $add = function (string $t, string $c, string $def) use ($pdo): bool {
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $q->execute([$t, $c]);
        if ($q->fetchColumn()) return false;
        $pdo->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
        return true;
    };
    if ($add('insurance_companies', 'tariff_group', "ENUM('public','private') NOT NULL DEFAULT 'private'")) {
        // public = RSSB/RAMA and MMI/Military; everything else stays private (editable in Insurance > Companies)
        $pdo->exec("UPDATE insurance_companies SET tariff_group='public'
            WHERE LOWER(code) IN ('rssb','rama','mmi') OR LOWER(name) LIKE '%rssb%' OR LOWER(name) LIKE '%rama%' OR LOWER(name) LIKE '%mmi%' OR LOWER(name) LIKE '%military%' OR LOWER(name) LIKE '%mutuelle%'");
    }
    foreach (['price_public', 'price_private', 'price_non_insured'] as $c) $add('service_catalog', $c, 'DECIMAL(12,2) NULL');
    $add('service_catalog', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
    $add('patients', 'residence_zone', "ENUM('in_zone','out_zone') NOT NULL DEFAULT 'in_zone'");
    $add('visits', 'residence_zone', "ENUM('in_zone','out_zone') NOT NULL DEFAULT 'in_zone'");
}

// Load rama/private_insurance/non_insured from the MoH file into empty price columns only (matched by rhic). Returns services touched.
function tariff_load_group_prices(PDO $pdo): int {
    static $done = false;
    if ($done) return 0;
    $done = true;
    tariff_ensure_schema($pdo);
    $file = __DIR__ . '/../migrations/tariff_private_feb2026.json';
    $rows = is_file($file) ? json_decode(file_get_contents($file), true) : null;
    if (!$rows) return 0;
    // one cheap check so normal page loads skip the 373 updates
    if (!(int)$pdo->query("SELECT COUNT(*) FROM service_catalog WHERE rhic LIKE 'RHIC-%' AND price_public IS NULL AND price_private IS NULL AND price_non_insured IS NULL")->fetchColumn()) return 0;
    $up = $pdo->prepare("UPDATE service_catalog SET
            price_public = COALESCE(price_public, NULLIF(?,0)), price_private = COALESCE(price_private, NULLIF(?,0)), price_non_insured = COALESCE(price_non_insured, NULLIF(?,0))
        WHERE rhic = ?");
    $n = 0;
    foreach ($rows as $r) {
        $up->execute([$r['rama'] ?? 0, $r['private_insurance'] ?? 0, $r['non_insured'] ?? 0, $r['rhic']]);
        $n += $up->rowCount();
    }
    return $n;
}

function tariff_setting(PDO $pdo, string $key): ?string {
    try {
        $q = $pdo->prepare("SELECT setting_value FROM hospital_settings WHERE setting_key=? LIMIT 1");
        $q->execute([$key]);
        $v = $q->fetchColumn();
        return $v === false ? null : (string)$v;
    } catch (Throwable $e) { return null; }
}

// Zone for a sector name: 'in_zone' when the sector is listed in service_zone_sectors (or no list is set), else 'out_zone'.
// Patient form (reception) should default patients.residence_zone / visits.residence_zone from this.
function tariff_zone_for_sector(PDO $pdo, ?string $sector): string {
    $list = array_filter(array_map(fn($s) => mb_strtolower(trim($s)), explode(',', (string)tariff_setting($pdo, 'service_zone_sectors'))));
    $sector = mb_strtolower(trim((string)$sector));
    return (!$list || $sector === '' || in_array($sector, $list, true)) ? 'in_zone' : 'out_zone';
}
