<?php
// includes/vital_rules.php — sanity limits for vital sign values. The limits in vital_signs_settings (min_value/max_value)
// win when set; otherwise these defaults (values no living patient can have are rejected, abnormal values are still allowed).
const VITAL_DEFAULTS = [
    'hr' => [20, 300], 'resp' => [3, 90], 'spo2' => [30, 100], 'temp' => [25, 45], 'weight' => [0.3, 500], 'size' => [20, 260],
    'pain' => [0, 10], 'glucose' => [10, 1500], 'bmi' => [5, 100], 'muac' => [3, 60],
];

// Returns an error message, or null when the value is acceptable.
function vital_check(string $slug, string $name, string $value, $min = null, $max = null): ?string {
    $value = trim($value);
    if ($slug === 'disease_type') {
        return in_array($value, ['Natural disease', 'Occupation disease', 'Other', 'Road traffic accident', 'Work related accident'], true)
            ? null : "$name must be one of the listed options";
    }
    if ($slug === 'bp') {
        if (!preg_match('~^(\d{2,3})\s*/\s*(\d{2,3})$~', $value, $m)) return "$name must look like 120/80";
        [$sys, $dia] = [(int)$m[1], (int)$m[2]];
        if ($sys < 40 || $sys > 300 || $dia < 20 || $dia > 200 || $dia >= $sys) return "$name $value is not a possible blood pressure";
        return null;
    }
    if (!is_numeric($value)) return "$name must be a number (got \"$value\")";
    $lo = $min !== null ? (float)$min : (VITAL_DEFAULTS[$slug][0] ?? null);
    $hi = $max !== null ? (float)$max : (VITAL_DEFAULTS[$slug][1] ?? null);
    if (($lo !== null && (float)$value < $lo) || ($hi !== null && (float)$value > $hi)) return "$name $value is outside the possible range ($lo to $hi). Check the reading.";
    return null;
}

// Validates [{vital_sign_id, value}] against the configured signs; ends the request with 422 on the first bad value.
function vital_check_readings(PDO $pdo, array $readings): void {
    $q = $pdo->prepare("SELECT name, slug, min_value, max_value FROM vital_signs_settings WHERE id = ?");
    foreach ($readings as $r) {
        $val = trim((string)($r['value'] ?? ''));
        if ($val === '') continue;
        $q->execute([(int)($r['vital_sign_id'] ?? 0)]);
        if (!($s = $q->fetch())) continue;
        if ($err = vital_check((string)$s['slug'], (string)$s['name'], $val, $s['min_value'], $s['max_value'])) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => $err]);
            exit();
        }
    }
}
