<?php
// includes/hmis_dxmap.php — which HMIS priority row a consultation diagnosis belongs to.
// Consultations carry free-text diagnoses and no ICD code, so keywords alone miss many real cases. A clinician can map each
// distinct diagnosis to a row once (table hmis_dx_map, edited from the HMIS report); keywords stay as the fallback for
// diagnoses nobody has reviewed yet.
//
// Keyword rules: a plain word matches anywhere in the text, ".*" makes a regex, "~x" is an explicit regex,
// "!x" EXCLUDES the row when x is present (e.g. a tooth fracture is not a bone fracture).

const HMIS_PRIORITY_LABELS = [
    1 => 'Diarrheal diseases', 2 => 'Intestinal parasites', 3 => 'Snake bite', 4 => 'Pneumonia', 5 => 'Bronchitis',
    6 => 'Acute respiratory diseases (other)', 7 => 'Acute malnutrition', 8 => 'HIV/AIDS opportunistic infection',
    9 => 'Gastritis and duodenitis', 10 => 'Skin diseases', 11 => 'Scabies', 12 => 'Tungiasis / jigger', 13 => 'Abscesses',
    14 => 'Anemia', 15 => 'Viral hepatitis (B, C)', 16 => 'Urinary tract diseases', 17 => 'Bone and joint diseases',
    18 => 'Bone and joint injuries / fractures', 19 => 'Physical traumas (not fractures)', 20 => 'Sepsis', 21 => 'Goitre',
    22 => 'Gynecological problems', 23 => 'Bone diseases with deformities',
];
// First matching row wins; the specific rows (scabies, tungiasis, bone deformities) are tried before the broad ones.
const HMIS_PRIORITY_ORDER = [1,2,3,4,5,6,7,8,9,11,12,10,13,14,15,16,23,17,18,19,20,21,22];

function hmis_priority_rules(): array {
    return [
  1 =>['diarrhea','diarrh','A00','A01','A02','A03','A04','A05','A06','A07','A08','A09'],
  2 =>['parasit','intestinal worm','B65','B66','B67','B68','B69','B70','B71','B72','B73','B74','B75','B76','B77','B78','B79','B80','B81','B82','B83'],
  3 =>['snake','T63'],
  4 =>['pneumon','J09','J10','J11','J12','J13','J14','J15','J16','J17','J18'],
  5 =>['bronchit','bronchiolit','J20','J21','J22','J40','J41','J42'],
  6 =>['respiratory','respirat','urti','upper respiratory','rhinit','pharyngit','nasopharyng','tonsillit','angine','laryngit','sinusit','common cold','syndrome grippal','grippe','adenoidit','J00','J01','J02','J03','J04','J05','J06'],
  7 =>['acute malnutrit','malnutrition aigue','malnutrit','E40','E41','E42','E43','E44','E45','E46'],
  8 =>['hiv','opportun','B20','B21','B22','B23','B24'],
  9 =>['gastrit','duodenit','K29'],
  10=>['skin','dermat','L00','L01','L02','L03','L04','L05'],
  11=>['scabies','B86'],
  12=>['tungiasis','jigger','B88'],
  13=>['abscess','abcès','K12','L02','J36'],
  14=>['anemia','anémie','D50','D51','D52','D53','D64'],
  15=>['hepatitis','hépatite','B15','B16','B17','B18','B19'],
  16=>['urinary','urinaire','~\buti\b','cystit','N30','N39','N10','N11','N12'],
  17=>['bone','joint','articulat','M00','M05','M06','M15','M16','M17','M19'],
  18=>['fracture','!tooth','!teeth','!dental','!filling','S02','S12','S22','S32','S42','S52','S62','S72','S82','S92'],
  19=>['trauma','traumat','T07','T14'],
  20=>['sepsis','septicé','A40','A41'],
  21=>['goitre','goiter','E01','E04'],
  22=>['gynecol','gynécol','N70','N71','N72','N73','N74','N75','N76'],
  23=>['bone.*deformit','déformit','M40','M41','M42','M43'],
];
}

if (!function_exists('matchAny')) {
    function matchAny($hay, array $kws){
        $hay = strtolower((string)$hay);
        foreach ($kws as $k) { if ($k !== '' && $k[0] === '!' && stripos($hay, substr($k, 1)) !== false) return false; }
        foreach ($kws as $k) {
            if ($k === '' || $k[0] === '!') continue;
            if ($k[0] === '~') { if (preg_match('~' . substr($k, 1) . '~iu', $hay)) return true; continue; } // explicit regex, same convention as kwSql()
            if (str_contains($k, '.*') ? preg_match('~' . $k . '~iu', $hay) : stripos($hay, $k) !== false) return true;
        }
        return false;
    }
}

// A diagnosis field may hold several diagnoses ("URTI, bacteriemie"): split on comma / semicolon / new line / pipe.
function hmis_dx_norm(string $t): string {
    $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $t)), 'UTF-8');
    return mb_substr(trim($t, " .:-	"), 0, 190);
}
function hmis_dx_tokens(?string $diagnosis): array {
    $out = [];
    foreach (preg_split('/[,;
|]+/u', (string)$diagnosis) as $raw) {
        $k = hmis_dx_norm($raw);
        if ($k !== '' && $k !== 'null') $out[$k] = trim($raw);
    }
    return $out; // key => text as typed
}
function hmis_dx_map_load(PDO $pdo): array {
    hmis_ensure_schema($pdo);
    return $pdo->query("SELECT dx_key, row_key FROM hmis_dx_map")->fetchAll(PDO::FETCH_KEY_PAIR);
}
// Row (1..23) for one consultation, or null. $unreviewed is set when a diagnosis has neither a mapping nor a keyword hit.
function hmis_priority_row(array $c, array $rules, array $map, ?bool &$unreviewed = null): ?int {
    $unreviewed = false;
    $cand = []; $rest = ''; $hasUnmapped = false;
    foreach (hmis_dx_tokens($c['diagnosis'] ?? '') as $k => $txt) {
        if (isset($map[$k])) { if ($map[$k] !== 'none') $cand[] = (int)$map[$k]; }
        else { $rest .= ' ' . $txt; $hasUnmapped = true; }
    }
    $rest = trim($rest . ' ' . ($c['icd_code'] ?? ''));
    if ($rest !== '') {
        foreach (HMIS_PRIORITY_ORDER as $n) { if (matchAny($rest, $rules[$n])) { $cand[] = $n; break; } }
    }
    // Unreviewed = a diagnosis nobody mapped and no keyword recognised, and the case is in no row
    $unreviewed = $hasUnmapped && !$cand;
    if (!$cand) return null;
    usort($cand, fn($a, $b) => array_search($a, HMIS_PRIORITY_ORDER) <=> array_search($b, HMIS_PRIORITY_ORDER));
    return $cand[0];
}
