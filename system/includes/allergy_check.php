<?php
// includes/allergy_check.php — compares medicines with the allergies written on the patient's record.
// A hit blocks prescribing/dispensing (HTTP 409, code allergy_conflict) unless the caller sends allergy_override_reason;
// the override and its reason are written to the audit log by allergy_gate().

// Drug families: an allergy to the key also covers every name listed after it.
const ALLERGY_FAMILIES = [
    'penicillin'  => ['penicillin', 'amoxicillin', 'amoxycillin', 'ampicillin', 'cloxacillin', 'flucloxacillin', 'piperacillin', 'amoxiclav', 'augmentin', 'benzathine', 'phenoxymethyl'],
    'sulfa'       => ['sulfa', 'sulpha', 'sulfamethoxazole', 'cotrimoxazole', 'co-trimoxazole', 'trimethoprim', 'bactrim', 'septrin', 'sulfadiazine'],
    'cephalosporin' => ['cephalosporin', 'cefa', 'cefi', 'cefo', 'cefp', 'cefr', 'cefu', 'ceftriaxone', 'cephalexin'],
    'nsaid'       => ['nsaid', 'ibuprofen', 'diclofenac', 'naproxen', 'ketoprofen', 'indomethacin', 'aspirin', 'piroxicam', 'meloxicam'],
    'aspirin'     => ['aspirin', 'acetylsalicylic'],
    'macrolide'   => ['macrolide', 'erythromycin', 'azithromycin', 'clarithromycin'],
    'quinolone'   => ['quinolone', 'ciprofloxacin', 'levofloxacin', 'norfloxacin', 'ofloxacin'],
];

// Returns human-readable conflicts between $allergyText and the given drug names (name + generic name).
function allergy_conflicts(string $allergyText, array $drugNames): array {
    $allergyText = strtolower(trim($allergyText));
    if ($allergyText === '' || in_array($allergyText, ['none', 'no', 'nil', 'n/a', 'none known', 'nkda'], true)) return [];
    $allergens = array_filter(array_map('trim', preg_split('/[,;\/\n]+|\band\b/i', $allergyText)), fn($a) => strlen($a) >= 3);
    $hits = [];
    foreach ($drugNames as $drug) {
        $d = strtolower((string)$drug);
        if ($d === '') continue;
        foreach ($allergens as $a) {
            $words = [$a];
            foreach (ALLERGY_FAMILIES as $fam => $members) {
                if (strpos($a, $fam) !== false) $words = array_merge($words, $members);
            }
            foreach (array_unique($words) as $w) {
                if (strlen($w) >= 3 && strpos($d, $w) !== false) { $hits[$drug] = "$drug (patient allergy: $a)"; break 2; }
            }
        }
    }
    return array_values($hits);
}

// Names to compare for a list of item ids (+ any free-text medication names).
function allergy_drug_names(PDO $pdo, array $itemIds, array $extraNames = []): array {
    $names = array_filter(array_map('strval', $extraNames));
    $itemIds = array_values(array_filter(array_map('intval', $itemIds)));
    if ($itemIds) {
        $in = implode(',', array_fill(0, count($itemIds), '?'));
        $st = $pdo->prepare("SELECT name, generic_name FROM items WHERE id IN ($in)");
        $st->execute($itemIds);
        foreach ($st->fetchAll() as $r) { $names[] = $r['name']; if (!empty($r['generic_name'])) $names[] = $r['generic_name']; }
    }
    return array_values(array_unique($names));
}

// Ends the request with 409 when the patient is allergic and no override reason was given.
function allergy_gate(PDO $pdo, int $patientId, array $drugNames, string $overrideReason, string $context): void {
    $st = $pdo->prepare("SELECT allergies FROM patients WHERE id = ?");
    $st->execute([$patientId]);
    $hits = allergy_conflicts((string)$st->fetchColumn(), $drugNames);
    if (!$hits) return;
    $overrideReason = trim($overrideReason);
    if ($overrideReason === '') {
        http_response_code(409);
        echo json_encode(['success' => false, 'code' => 'allergy_conflict', 'warnings' => $hits,
            'message' => 'ALLERGY WARNING: ' . implode('; ', $hits) . '. Choose another medicine, or continue with a written reason.']);
        exit();
    }
    if (function_exists('log_activity')) log_activity($pdo, 'ALLERGY_OVERRIDE', 'pharmacy', "$context: allergy override for " . implode('; ', $hits) . ". Reason: $overrideReason", $patientId, null);
}
