<?php
// includes/tariff.php — foreign-patient price lists (patients/visits.tariff_category -> service_catalog.price_*)

// Foreign price column of service_catalog for a tariff category (null for rwanda / unknown)
function foreignPriceCol(?string $tariff): ?string {
    return ['east_africa' => 'price_east_africa', 'africa' => 'price_africa', 'world' => 'price_world'][$tariff ?? ''] ?? null;
}

// Tariff category of a visit, falling back to the patient's, 'rwanda' when neither is known
function visitTariff(PDO $pdo, int $visitId, int $patientId = 0): string {
    if ($visitId) {
        $q = $pdo->prepare("SELECT tariff_category FROM visits WHERE id = ?");
        $q->execute([$visitId]);
        if ($t = $q->fetchColumn()) return $t;
    }
    if ($patientId) {
        $q = $pdo->prepare("SELECT tariff_category FROM patients WHERE id = ?");
        $q->execute([$patientId]);
        if ($t = $q->fetchColumn()) return $t;
    }
    return 'rwanda';
}

// Regional price of a catalog service for a foreign tariff, null when rwanda or no regional price is set
function foreignServicePrice(PDO $pdo, ?int $serviceId, string $tariff): ?float {
    $col = foreignPriceCol($tariff);
    if (!$col || !$serviceId) return null;
    $q = $pdo->prepare("SELECT NULLIF($col,0) FROM service_catalog WHERE id = ?");
    $q->execute([$serviceId]);
    $v = $q->fetchColumn();
    return ($v === false || $v === null) ? null : (float)$v;
}

// Facility-category price column of service_catalog (hospital_settings.facility_category: primary|secondary|tertiary)
function facilityPriceCol(PDO $pdo): string {
    $q = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='facility_category' LIMIT 1");
    return ['secondary' => 'secondary_health_facility', 'tertiary' => 'tertiary_health_facility'][(string)$q->fetchColumn()] ?? 'primary_health_facility';
}

/**
 * THE price resolver (#41). Order:
 *   1. insurer's own insurance_procedure_prices row (> 0)
 *   2. foreign-patient price ($tariffCategory east_africa|africa|world, when set on the service)
 *   3. group price: insurer tariff_group public -> price_public, private -> price_private; self-pay ($insuranceCompanyId null/0) -> price_non_insured
 *   4. facility column (facility_category setting)
 * Call: servicePrice($pdo, $serviceId, $insuranceCompanyId, $tariff, $zone) - company id = insurance_companies.id (null for self-pay), $tariff = visitTariff(), $zone = visits.residence_zone.
 * $zone is accepted for the callers but changes nothing yet: out-of-zone pricing is an open client question
 * (hospital_settings.service_zone_out_surcharge_pct exists, default 0, applied by nobody).
 */
function servicePrice(PDO $pdo, int $serviceId, ?int $insuranceCompanyId = null, ?string $tariffCategory = null, ?string $zone = null): float {
    if ($insuranceCompanyId == 13) $insuranceCompanyId = null; // 'Private' insurer = self-pay (FIN_PRIVATE_INSURER)
    if ($insuranceCompanyId) {
        $q = $pdo->prepare("SELECT price FROM insurance_procedure_prices WHERE service_id = ? AND insurance_id = ? LIMIT 1");
        $q->execute([$serviceId, $insuranceCompanyId]);
        $p = (float)$q->fetchColumn();
        if ($p > 0) return $p;
    }
    if ($f = foreignServicePrice($pdo, $serviceId, $tariffCategory ?? 'rwanda')) return $f;
    require_once __DIR__ . '/tariff_schema.php';
    tariff_ensure_schema($pdo);
    $col = 'price_non_insured';
    if ($insuranceCompanyId) {
        $q = $pdo->prepare("SELECT tariff_group FROM insurance_companies WHERE id = ?");
        $q->execute([$insuranceCompanyId]);
        $col = $q->fetchColumn() === 'public' ? 'price_public' : 'price_private';
    }
    $fac = facilityPriceCol($pdo);
    $q = $pdo->prepare("SELECT COALESCE(NULLIF($col,0), $fac, 0) FROM service_catalog WHERE id = ?");
    $q->execute([$serviceId]);
    return (float)$q->fetchColumn();
}
