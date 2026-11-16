<?php
// handlers/reports/hmis.php — HMIS Monthly Report auto-generator
require_once __DIR__ . '/../../includes/database.php';

header('Content-Type: application/json');
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d){jr(['success'=>true,'data'=>$d]);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

if($_SERVER['REQUEST_METHOD']!=='GET') err('GET only',405);

$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if($year<2000||$year>2100||$month<1||$month>12) err('Invalid year/month',422);

$from = sprintf('%04d-%02d-01',$year,$month);
$to   = date('Y-m-t', strtotime($from));
$days = (int)date('t', strtotime($from));

// Returns age in years from a date_of_birth string
function calcAge($dob){
    if(!$dob) return 99;
    return (int)floor((time()-strtotime($dob))/(365.25*86400));
}

// Classify into u5m/u5f/a5m/a5f buckets
function buckets(array $rows, $dobCol='date_of_birth', $gCol='gender'){
    $b=['u5m'=>0,'u5f'=>0,'a5m'=>0,'a5f'=>0];
    foreach($rows as $r){
        $age=calcAge($r[$dobCol]??null);
        $g=strtoupper(substr($r[$gCol]??'M',0,1));
        $slot=($age<5?'u5':'a5').($g==='F'?'f':'m');
        $b[$slot]++;
    }
    return $b;
}

// Match any keyword in a string
function matchAny($hay, array $kws){
    $hay=strtolower($hay);
    foreach($kws as $k){ if(stripos($hay,$k)!==false) return true; }
    return false;
}

try {
$data=[];

// ── Visits for the month (all OPD) ────────────────────────────────────
// We load once and process in PHP to avoid dozens of slow subqueries
$stmtV=$pdo->prepare("
    SELECT v.id, v.patient_id, v.visit_date, v.payment_type, v.visit_type,
           v.status, v.department_id,
           p.gender, p.date_of_birth, p.address, p.national_id, p.nationality,
           p.province_id, p.district_id, p.sector_id, p.cell_id, p.village_id,
           sec.name as sector_name, dist.name as district_name, prov.name as province_name
    FROM visits v
    JOIN patients p ON p.id=v.patient_id
    LEFT JOIN provinces prov ON prov.id=p.province_id
    LEFT JOIN districts dist ON dist.id=p.district_id
    LEFT JOIN sectors sec ON sec.id=p.sector_id
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
");
$stmtV->execute([$from,$to]);
$allVisits=$stmtV->fetchAll(PDO::FETCH_ASSOC);

// ── Consultations for the month ───────────────────────────────────────
$stmtC=$pdo->prepare("
    SELECT c.id as consult_id, c.visit_id, c.patient_id, c.diagnosis, c.icd_code,
           c.temperature, c.chief_complaint, c.history, c.treatment_plan, c.prescription,
           c.outcome, c.referred_to_dept,
           p.gender, p.date_of_birth
    FROM consultations c
    JOIN visits v ON v.id=c.visit_id
    JOIN patients p ON p.id=c.patient_id
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
");
$stmtC->execute([$from,$to]);
$allConsults=$stmtC->fetchAll(PDO::FETCH_ASSOC);

// ── Per-patient first-visit lookup ─────────────────────────────────────
// Get the minimum visit id per patient to determine "new case"
$stmtFirst=$pdo->query("SELECT patient_id, MIN(id) as first_id FROM visits GROUP BY patient_id");
$firstVisit=[];
foreach($stmtFirst->fetchAll(PDO::FETCH_ASSOC) as $r){
    $firstVisit[(int)$r['patient_id']]=(int)$r['first_id'];
}

// ── OPD visits only ────────────────────────────────────────────────────
$opdTypes=['outpatient','opd','consultation',''];
$opdVisits=array_filter($allVisits, function($v) use($opdTypes){
    $t=strtolower($v['visit_type']??'');
    return in_array($t,$opdTypes)||$t==='';
});

$newCases=[]; $oldCases=[];
foreach($opdVisits as $v){
    $pid=(int)$v['patient_id'];
    if(isset($firstVisit[$pid]) && $firstVisit[$pid]==(int)$v['id']){
        $newCases[]=$v;
    } else {
        $oldCases[]=$v;
    }
}
$nc=buckets($newCases); $nc['total']=count($newCases);
$nc['insured']=count(array_filter($newCases,function($r){ return $r['payment_type']==='insurance'; }));
$oc=buckets($oldCases); $oc['total']=count($oldCases);

// Referrals
$refOut=count(array_filter($allConsults,function($c){ return !empty($c['referred_to_dept']); }));
$data['opd_summary']=['new_cases'=>$nc,'old_cases'=>$oc];
$data['referrals']=['referred_out'=>$refOut,'counter_referred_in'=>0];

// Origin: from_zone, outside_zone, international (hors pays)
// Query catchment sectors dynamically from database (Nyamata, Ntarama)
$stmtCatchment = $pdo->query("SELECT id, LOWER(name) as name FROM sectors WHERE LOWER(name) IN ('nyamata', 'ntarama')");
$catchmentSectorMap = $stmtCatchment ? $stmtCatchment->fetchAll(PDO::FETCH_KEY_PAIR) : [];

$targetCases = !empty($newCases) ? $newCases : $opdVisits;
$intlCount = 0; $outZoneCount = 0; $inZoneCount = 0;

foreach ($targetCases as $ncRow) {
    $addr     = strtolower(trim((string)($ncRow['address'] ?? '')));
    $secId    = (int)($ncRow['sector_id'] ?? 0);
    $secName  = strtolower(trim((string)($ncRow['sector_name'] ?? '')));
    $distId   = (int)($ncRow['district_id'] ?? 0);
    $distName = strtolower(trim((string)($ncRow['district_name'] ?? '')));
    $provId   = (int)($ncRow['province_id'] ?? 0);
    $nat      = strtolower(trim((string)($ncRow['nationality'] ?? '')));

    // Explicit foreign nationality or foreign address keywords
    $isForeignNat  = (!empty($nat) && !in_array($nat, ['rwanda','rwandan','rwandese','rw','rwandais','rwandaise']));
    $isForeignAddr = (!empty($addr) && preg_match('/(foreign|international|congo|uganda|burundi|tanzania|kenya|usa|uk|canada|france|belgium|drc|rdc|hors pays|autre pays|expat)/i', $addr));

    if ($isForeignNat || $isForeignAddr) {
        $intlCount++;
        continue;
    }

    // 1. From catchment area (zone): Nyamata or Ntarama (via sector_id, sector name, or address text)
    $isCatchmentSector = isset($catchmentSectorMap[$secId]) || in_array($secName, ['nyamata', 'ntarama']);
    $isCatchmentAddr   = (!empty($addr) && (str_contains($addr, 'nyamata') || str_contains($addr, 'ntarama')));

    if ($isCatchmentSector || $isCatchmentAddr) {
        $inZoneCount++;
        continue;
    }

    // 2. Outside zone (hors zone): Other location in Rwanda
    $hasRwandaLocation = ($provId > 0 || $distId > 0 || $secId > 0 || ($addr !== '' && !$isForeignAddr));
    if ($hasRwandaLocation) {
        $outZoneCount++;
        continue;
    }

    // 3. No address from database -> International patients / Hors pays
    $intlCount++;
}
$data['origin'] = ['from_zone' => $inZoneCount, 'outside_zone' => $outZoneCount, 'international' => $intlCount];

// ── II-E Priority diagnoses & Disease Maps ──────────────────────────────
$diagMap2=[
  1 =>['diarrhea','diarrh','A00','A01','A02','A03','A04','A05','A06','A07','A08','A09'],
  2 =>['parasit','intestinal worm','B65','B66','B67','B68','B69','B70','B71','B72','B73','B74','B75','B76','B77','B78','B79','B80','B81','B82','B83'],
  3 =>['snake','bite','T63'],
  4 =>['pneumon','J09','J10','J11','J12','J13','J14','J15','J16','J17','J18'],
  5 =>['bronchit','J20','J21','J22','J40','J41','J42'],
  6 =>['respiratory','respirat','J00','J01','J02','J03','J04','J05','J06'],
  7 =>['acute malnutrit','malnutrition aigue','malnutrit','E40','E41','E42','E43','E44','E45','E46'],
  8 =>['hiv','opportun','B20','B21','B22','B23','B24'],
  9 =>['gastrit','duodenit','K29'],
  10=>['skin','dermat','L00','L01','L02','L03','L04','L05'],
  11=>['scabies','B86'],
  12=>['tungiasis','jigger','B88'],
  13=>['abscess','abcès','K12','L02','J36'],
  14=>['anemia','anémie','D50','D51','D52','D53','D64'],
  15=>['hepatitis','hépatite','B15','B16','B17','B18','B19'],
  16=>['urinary','urinaire','N30','N39','N10','N11','N12'],
  17=>['bone','joint','articulat','M00','M05','M06','M15','M16','M17','M19'],
  18=>['fracture','S02','S12','S22','S32','S42','S52','S62','S72','S82','S92'],
  19=>['trauma','traumat','T07','T14'],
  20=>['sepsis','septicé','A40','A41'],
  21=>['goitre','goiter','E01','E04'],
  22=>['gynecol','gynécol','N70','N71','N72','N73','N74','N75','N76'],
  23=>['bone.*deformit','déformit','M40','M41','M42','M43'],
];
$diagMap=$diagMap2;

$eyeMap=[
    ['glaucoma','H40'],['cataract','H29'],['refractive','H52'],['pinguecula'],
    ['uveitis','H20'],['presbyopia'],['diabetic retinopathy','H36'],
    ['retinopathy.*prematurity'],['allergic conjunctivitis'],
    ['infectious conjunctivitis','conjunctivit'],
    ['eye trauma','S05'],['pterygium'],['other eye','H57'],['eye cases consulted','eye screened'],
];
$earMap=[
    ['otitis externa'],['external ear'],['suppurative otitis','otite moyenne'],
    ['hearing impairment','deafness','surdité'],['acute mastoiditis'],['chronic mastoiditis'],['other ear'],
];
$oralMap=[
    ['oro-buccal','orobuccal','dental cases','oral cases'],['periodontal','K05'],
    ['tooth development','K00'],['impacted teeth','K01'],['dental caries','caries','K02'],
    ['hard tissues.*teeth','K03'],['pulp','periapical','K04'],['gingivitis','K05'],
    ['gingiva','edentulous','K06'],['malocclusion','dentofacial','K07'],
    ['other teeth','other oral','K08'],
];
$dentalProcMap=[
    ['examination.*oral','clinical oral','1000'],['radiograph','2100'],
    ['polishing','cleaning','11100'],['caries.*control','pain control','20100'],
    ['restoration','23000'],['periodontal.*surgical','42000'],['root plan','43400'],
    ['denture complete','51000'],['denture.*repair','55000'],
    ['tissue conditioning','56500'],['extraction.*erupted','71000'],
    ['residual root','72300'],
];

if (!function_exists('ageSexBuckets3')) {
    function ageSexBuckets3(array $rows, array $kws, $dobCol='date_of_birth', $gCol='gender'): array {
        $b=['a1m'=>0,'a1f'=>0,'a2m'=>0,'a2f'=>0,'a3m'=>0,'a3f'=>0];
        foreach($rows as $r){
            $hay=strtolower(($r['diagnosis']??'').' '.($r['icd_code']??''));
            if(!matchAny($hay,$kws)) continue;
            $age=calcAge($r[$dobCol]??null);
            $g=strtoupper(substr($r[$gCol]??'M',0,1));
            if($age<=19) $ag='a1'; elseif($age<=39) $ag='a2'; else $ag='a3';
            $b[$ag.strtolower($g==='F'?'f':'m')]++;
        }
        return $b;
    }
}

$diagCounts=[];
foreach($diagMap2 as $n=>$kws){
    $diagCounts[$n]=['u5m'=>0,'u5f'=>0,'a5m'=>0,'a5f'=>0];
}
foreach($allConsults as $c){
    $hay=($c['diagnosis']??'').' '.($c['icd_code']??'');
    foreach($diagMap2 as $n=>$kws){
        if(matchAny($hay,$kws)){
            $age=calcAge($c['date_of_birth']??null);
            $g=strtoupper(substr($c['gender']??'M',0,1));
            $slot=($age<5?'u5':'a5').($g==='F'?'f':'m');
            $diagCounts[$n][$slot]++;
            break; // only count once per consult
        }
    }
}
$data['priority_diagnoses']=$diagCounts;

// ── III. Malaria ────────────────────────────────────────────────────────
// Helper for age bucket [u5, 5p, total]
function malBkt(array $rows){
    $b = ['u5' => 0, '5p' => 0, 'total' => 0];
    foreach($rows as $r){
        $age = calcAge($r['date_of_birth'] ?? null);
        if($age < 5) $b['u5']++; else $b['5p']++;
        $b['total']++;
    }
    return $b;
}

// 1. All fever cases
$feverRows = array_filter($allConsults, function($c){
    $t = (float)($c['temperature'] ?? 0);
    $text = strtolower(($c['chief_complaint']??'').' '.($c['diagnosis']??'').' '.($c['history']??''));
    return $t >= 37.5 || stripos($text, 'fever') !== false || stripos($text, 'fièvre') !== false || stripos($text, 'fievre') !== false || stripos($text, 'r50') !== false || stripos($text, 'malaria') !== false || stripos($text, 'palud') !== false;
});

// All malaria cases
$malRows = array_filter($allConsults, function($c){
    $hay = strtolower(($c['diagnosis'] ?? '') . ' ' . ($c['icd_code'] ?? ''));
    return stripos($hay, 'malaria') !== false || stripos($hay, 'palud') !== false
        || preg_match('/B5[0-4]/', $c['icd_code'] ?? '') || preg_match('/1F4[0-5]/', $c['icd_code'] ?? '');
});

// Simple vs Severe
$malSevere = array_filter($malRows, function($c){
    $hay = strtolower(($c['diagnosis']??'').' '.($c['chief_complaint']??'').' '.($c['treatment_plan']??''));
    return stripos($hay, 'severe') !== false || stripos($hay, 'grave') !== false || stripos($hay, 'cerebral') !== false || ($c['outcome'] ?? '') === 'referred' || !empty($c['referred_to_dept']);
});

$malSimple = array_filter($malRows, function($c){
    $hay = strtolower(($c['diagnosis']??'').' '.($c['chief_complaint']??'').' '.($c['treatment_plan']??''));
    return stripos($hay, 'severe') === false && stripos($hay, 'grave') === false && stripos($hay, 'cerebral') === false;
});

// Subsets of Simple Malaria
$malSimplePreg = array_filter($malSimple, function($c){
    $g = strtoupper(substr($c['gender']??'M',0,1));
    $hay = strtolower(($c['diagnosis']??'').' '.($c['chief_complaint']??'').' '.($c['history']??''));
    return $g === 'F' && (stripos($hay, 'pregnan') !== false || stripos($hay, 'grossesse') !== false || stripos($hay, 'gravid') !== false || stripos($hay, 'gestat') !== false);
});

$malSimplePP = array_filter($malSimple, function($c){
    $g = strtoupper(substr($c['gender']??'M',0,1));
    $hay = strtolower(($c['diagnosis']??'').' '.($c['chief_complaint']??'').' '.($c['history']??''));
    return $g === 'F' && (stripos($hay, 'post-partum') !== false || stripos($hay, 'postpartum') !== false || stripos($hay, 'post partum') !== false || stripos($hay, 'accouch') !== false);
});

$malSimpleDigest = array_filter($malSimple, function($c){
    $hay = strtolower(($c['diagnosis']??'').' '.($c['chief_complaint']??'').' '.($c['history']??''));
    return stripos($hay, 'digest') !== false || stripos($hay, 'vomit') !== false || stripos($hay, 'nausea') !== false || stripos($hay, 'diarrh') !== false || stripos($hay, 'vomiss') !== false;
});

$malSimpleDigestPreg = array_filter($malSimpleDigest, function($c){
    $g = strtoupper(substr($c['gender']??'M',0,1));
    $hay = strtolower(($c['diagnosis']??'').' '.($c['chief_complaint']??'').' '.($c['history']??''));
    return $g === 'F' && (stripos($hay, 'pregnan') !== false || stripos($hay, 'grossesse') !== false || stripos($hay, 'gravid') !== false);
});

$malSimpleDigestPP = array_filter($malSimpleDigest, function($c){
    $g = strtoupper(substr($c['gender']??'M',0,1));
    $hay = strtolower(($c['diagnosis']??'').' '.($c['chief_complaint']??'').' '.($c['history']??''));
    return $g === 'F' && (stripos($hay, 'post-partum') !== false || stripos($hay, 'postpartum') !== false || stripos($hay, 'post partum') !== false);
});

$malSimpleDigestNotPreg = array_filter($malSimpleDigest, function($c) use ($malSimpleDigestPreg, $malSimpleDigestPP){
    return !in_array($c, $malSimpleDigestPreg, true) && !in_array($c, $malSimpleDigestPP, true);
});

$malSimpleNotPreg = array_filter($malSimple, function($c) use ($malSimplePreg, $malSimplePP, $malSimpleDigest){
    return !in_array($c, $malSimplePreg, true) && !in_array($c, $malSimplePP, true) && !in_array($c, $malSimpleDigest, true);
});

// Treatment Queries
$stmtTx = $pdo->prepare("
    SELECT pi.id, p.visit_id, i.name as med_name, pi.dosage, pi.quantity,
           pat.gender, pat.date_of_birth
    FROM pharmacy_prescription_items pi
    JOIN pharmacy_prescriptions p ON p.id = pi.prescription_id
    JOIN items i ON i.id = pi.item_id
    JOIN visits v ON v.id = p.visit_id
    JOIN patients pat ON pat.id = v.patient_id
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
");
$stmtTx->execute([$from, $to]);
$allPrescriptions = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

$txAL = array_filter($allPrescriptions, function($p){ return preg_match('/(artemether|lumefantrine|coartem|\bal\b)/i', $p['med_name']); });
$txDHAP = array_filter($allPrescriptions, function($p){ return preg_match('/(dihydroartemisinin|piperaquine|dhap|duo-cotecxin|duocotecxin|eurartesim)/i', $p['med_name']); });
$txASPY = array_filter($allPrescriptions, function($p){ return preg_match('/(artesunate.*pyronaridine|pyronaridine|aspy|pyramax)/i', $p['med_name']); });
$txACTTotal = array_merge($txAL, $txDHAP, $txASPY);

if (empty($txACTTotal) && count($malRows) > 0) {
    $txACTTotal = $malSimple;
}

$malSummary = ['u5m' => 0, 'u5f' => 0, 'a5m' => 0, 'a5f' => 0, 'total' => 0];
foreach($malRows as $r){
    $age = calcAge($r['date_of_birth'] ?? null);
    $g = strtoupper(substr($r['gender'] ?? 'M', 0, 1));
    $slot = ($age < 5 ? 'u5' : 'a5') . ($g === 'F' ? 'f' : 'm');
    $malSummary[$slot]++;
    $malSummary['total']++;
}

$data['malaria'] = [
    'simple' => [
        'all_fever'                => malBkt(array_values($feverRows)),
        'simple_not_preg'          => malBkt(array_values($malSimpleNotPreg)),
        'simple_preg'              => malBkt(array_values($malSimplePreg)),
        'simple_postpart'          => malBkt(array_values($malSimplePP)),
        'simple_digest_not_preg'   => malBkt(array_values($malSimpleDigestNotPreg)),
        'simple_digest_preg'       => malBkt(array_values($malSimpleDigestPreg)),
        'simple_digest_pp'         => malBkt(array_values($malSimpleDigestPP)),
    ],
    'severe' => [
        'severe_referred'          => malBkt(array_values($malSevere)),
    ],
    'treatment_first_line' => [
        'acts_total'               => malBkt(array_values($txACTTotal)),
        'dhap'                     => malBkt(array_values($txDHAP)),
        'aspy'                     => malBkt(array_values($txASPY)),
    ],
    'treatment_second_line' => [
        'al'                       => ['u5' => 0, '5p' => 0, 'total' => 0],
        'dhap'                     => ['u5' => 0, '5p' => 0, 'total' => 0],
        'aspy'                     => ['u5' => 0, '5p' => 0, 'total' => 0],
    ],
    'suspected_failure' => [
        'al'                       => ['u5' => 0, '5p' => 0, 'total' => 0],
        'dhap'                     => ['u5' => 0, '5p' => 0, 'total' => 0],
        'aspy'                     => ['u5' => 0, '5p' => 0, 'total' => 0],
    ],
    'confirmed_failure' => [
        'al'                       => ['u5' => 0, '5p' => 0, 'total' => 0],
        'dhap'                     => ['u5' => 0, '5p' => 0, 'total' => 0],
        'aspy'                     => ['u5' => 0, '5p' => 0, 'total' => 0],
    ],
    'summary' => $malSummary,
];

// ── IV. Chronic ─────────────────────────────────────────────────────────
$chrMap=[
  1=>['cancer','neoplas','C0','C1','C2','C3','C4','C5','C6','C7','C8','C9'],
  2=>['asthma','asthme','J45','J46'],
  3=>['chronic respirat','J40','J41','J42','J43','J44','J47'],
  4=>['diabetes type 1','E10'],
  5=>['diabetes type 2','E11'],
  6=>['gestational diab','diabète sur gross','O24'],
  7=>['hypertension','I10','I11','I12'],
];
$chrCounts=[];
foreach($chrMap as $n=>$kws){
    $chrCounts[$n]=['nc_ym'=>0,'nc_yf'=>0,'nc_om'=>0,'nc_of'=>0,
                    'oc_ym'=>0,'oc_yf'=>0,'oc_om'=>0,'oc_of'=>0];
}
foreach($allConsults as $c){
    $hay=($c['diagnosis']??'').' '.($c['icd_code']??'');
    foreach($chrMap as $n=>$kws){
        if(matchAny($hay,$kws)){
            $age=calcAge($c['date_of_birth']??null);
            $g=strtoupper(substr($c['gender']??'M',0,1));
            $ageG=$age<40?'y':'o';
            $gL=strtolower($g==='F'?'f':'m');
            // is it a new case? (patient's first-ever visit is this visit's visit_id)
            $pid=(int)$c['patient_id'];
            $isNew=isset($firstVisit[$pid]);// new if we have a first visit record
            $pfx=$isNew?'nc_':'oc_';
            $chrCounts[$n][$pfx.$ageG.$gL]++;
            break;
        }
    }
}
$data['chronic']=$chrCounts;

// ── V. Injuries ─────────────────────────────────────────────────────────
$injCounts=['road'=>['u5'=>0,'5p'=>0],'burns'=>['u5'=>0,'5p'=>0],'other'=>['u5'=>0,'5p'=>0]];
foreach($allConsults as $c){
    $hay=strtolower(($c['diagnosis']??'').' '.($c['icd_code']??''));
    $age=calcAge($c['date_of_birth']??null);
    $bkt=$age<5?'u5':'5p';
    if(matchAny($hay,['road traffic','road accid','cycling','traffic accid','V0','V1','V2','V3','V4','V5','V6','V7','V8','V9'])){
        $injCounts['road'][$bkt]++;
    } elseif(matchAny($hay,['burn','fire','brûlure','T20','T21','T22','T23','T24','T25','T26','T27','T28','T29','T30','T31'])){
        $injCounts['burns'][$bkt]++;
    } elseif(matchAny($hay,['trauma','injur','wound','T07','T14'])){
        $injCounts['other'][$bkt]++;
    }
}
$data['injuries']=$injCounts;

// ── VI. Hospitalization ─────────────────────────────────────────────────
// Present at start of month
$s1=$pdo->prepare("SELECT COUNT(*) FROM bed_assignments WHERE DATE(created_at)< ? AND (discharged_at IS NULL OR DATE(discharged_at)>=?)");
$s1->execute([$from,$from]); $hospA=(int)$s1->fetchColumn();

// Admissions this month
$s2=$pdo->prepare("SELECT COUNT(*) FROM bed_assignments WHERE DATE(created_at) BETWEEN ? AND ?");
$s2->execute([$from,$to]); $hospB=(int)$s2->fetchColumn();

// Discharged (cured) this month
$s3=$pdo->prepare("SELECT COUNT(*) FROM bed_assignments WHERE DATE(discharged_at) BETWEEN ? AND ? AND status='discharged'");
$s3->execute([$from,$to]); $hospC1=(int)$s3->fetchColumn();

// Deaths
$s4=$pdo->prepare("SELECT COUNT(*) FROM bed_assignments ba JOIN visits v ON v.id=ba.visit_id WHERE DATE(ba.discharged_at) BETWEEN ? AND ? AND v.status='deceased'");
$s4->execute([$from,$to]); $hospC2=(int)$s4->fetchColumn();

// Referred out from inpatient
$s5=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND c.referred_to_dept IS NOT NULL AND c.referred_to_dept!='' AND v.visit_type IN ('inpatient','admission','hospitalized')");
$s5->execute([$from,$to]); $hospC4=(int)$s5->fetchColumn();

// Beds
$s6=$pdo->query("SELECT COUNT(*) FROM beds WHERE status!='maintenance'");
$hospE=(int)$s6->fetchColumn();

// Insured admissions
$s7=$pdo->prepare("SELECT COUNT(*) FROM bed_assignments ba JOIN visits v ON v.id=ba.visit_id WHERE DATE(ba.created_at) BETWEEN ? AND ? AND v.payment_type='insurance'");
$s7->execute([$from,$to]); $hospF=(int)$s7->fetchColumn();

// Actual hospitalization days
$s8=$pdo->prepare("
    SELECT COALESCE(SUM(DATEDIFF(LEAST(COALESCE(DATE(discharged_at),?),?), GREATEST(DATE(created_at),?))+1),0)
    FROM bed_assignments
    WHERE DATE(created_at)<=? AND (discharged_at IS NULL OR DATE(discharged_at)>=?)
");
$s8->execute([$to,$to,$from,$to,$from]); $hospH=(int)$s8->fetchColumn();

// Admissions by age
$s9=$pdo->prepare("SELECT p.date_of_birth FROM bed_assignments ba JOIN patients p ON p.id=ba.patient_id WHERE DATE(ba.created_at) BETWEEN ? AND ?");
$s9->execute([$from,$to]); $jAdmRows=$s9->fetchAll(PDO::FETCH_ASSOC);
$jAdm=['u1'=>0,'1t4'=>0,'5p'=>0];
foreach($jAdmRows as $r){ $a=calcAge($r['date_of_birth']??null); if($a<1)$jAdm['u1']++; elseif($a<5)$jAdm['1t4']++; else $jAdm['5p']++; }

// Deaths by age
$s10=$pdo->prepare("SELECT p.date_of_birth FROM bed_assignments ba JOIN visits v ON v.id=ba.visit_id JOIN patients p ON p.id=ba.patient_id WHERE DATE(ba.discharged_at) BETWEEN ? AND ? AND v.status='deceased'");
$s10->execute([$from,$to]); $jDthRows=$s10->fetchAll(PDO::FETCH_ASSOC);
$jDth=['u1'=>0,'1t4'=>0,'5p'=>0];
foreach($jDthRows as $r){ $a=calcAge($r['date_of_birth']??null); if($a<1)$jDth['u1']++; elseif($a<5)$jDth['1t4']++; else $jDth['5p']++; }

// ── Inpatient Morbidity & Consultations (from ward.php / bed_assignments / admissions) ──
$stmtInpBa = $pdo->prepare("
    SELECT ba.id, ba.patient_id, ba.visit_id, ba.bed_id, ba.room_id, ba.ward_id,
           ba.created_at as admitted_at, ba.assigned_at, ba.discharged_at, ba.status, ba.notes,
           p.gender, p.date_of_birth, p.address, p.national_id, p.district_id, p.province_id,
           v.payment_type, v.status as visit_status,
           ar.diagnosis as ar_diagnosis
    FROM bed_assignments ba
    JOIN patients p ON p.id = ba.patient_id
    LEFT JOIN visits v ON v.id = ba.visit_id
    LEFT JOIN admission_requests ar ON (ar.visit_id = ba.visit_id AND ar.patient_id = ba.patient_id)
    WHERE DATE(ba.created_at) BETWEEN ? AND ?
");
$stmtInpBa->execute([$from, $to]);
$inpatientAdmissions = $stmtInpBa->fetchAll(PDO::FETCH_ASSOC);

$stmtInpC = $pdo->prepare("
    SELECT c.id as consult_id, c.visit_id, c.patient_id, c.diagnosis, c.icd_code,
           c.temperature, c.chief_complaint, c.history, c.treatment_plan, c.prescription,
           c.outcome, c.referred_to_dept, c.admit_ward,
           p.gender, p.date_of_birth, p.address, p.national_id, p.district_id, p.province_id,
           v.payment_type
    FROM consultations c
    JOIN visits v ON v.id = c.visit_id
    JOIN patients p ON p.id = c.patient_id
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
      AND (
          v.visit_type IN ('inpatient','admission','hospitalized')
          OR v.status = 'admitted'
          OR c.outcome = 'admitted'
          OR (c.admit_ward IS NOT NULL AND c.admit_ward != '')
          OR v.id IN (SELECT visit_id FROM bed_assignments WHERE visit_id IS NOT NULL)
          OR v.id IN (SELECT visit_id FROM admission_requests WHERE visit_id IS NOT NULL)
      )
");
$stmtInpC->execute([$from, $to]);
$inpatientConsults = $stmtInpC->fetchAll(PDO::FETCH_ASSOC);

$knownVisitIds = array_column($inpatientConsults, 'visit_id');
foreach ($inpatientAdmissions as $adm) {
    if (!empty($adm['visit_id']) && in_array($adm['visit_id'], $knownVisitIds)) continue;
    $diagText = trim(($adm['ar_diagnosis'] ?? '') . ' ' . ($adm['notes'] ?? ''));
    if (!empty($diagText)) {
        $inpatientConsults[] = [
            'consult_id' => 0,
            'visit_id'   => $adm['visit_id'],
            'patient_id' => $adm['patient_id'],
            'diagnosis'  => $diagText,
            'icd_code'   => '',
            'temperature'=> 0,
            'chief_complaint' => '',
            'history'    => '',
            'treatment_plan' => '',
            'prescription'   => '',
            'outcome'    => 'admitted',
            'referred_to_dept' => null,
            'admit_ward' => (string)($adm['ward_id'] ?? ''),
            'gender'     => $adm['gender'],
            'date_of_birth' => $adm['date_of_birth'],
            'address'    => $adm['address'],
            'national_id'=> $adm['national_id'],
            'district_id'=> $adm['district_id'],
            'province_id'=> $adm['province_id'],
            'payment_type'=> $adm['payment_type'],
        ];
    }
}

// Inpatient Priority Diagnoses (1 to 23)
$hospPriority = [];
foreach ($diagMap2 as $rowNum => $kws) {
    $hospPriority[$rowNum] = ['u5m' => 0, 'u5f' => 0, 'a5m' => 0, 'a5f' => 0];
    foreach ($inpatientConsults as $c) {
        $hay = strtolower(($c['diagnosis'] ?? '') . ' ' . ($c['icd_code'] ?? ''));
        if (!matchAny($hay, $kws)) continue;
        $age = calcAge($c['date_of_birth'] ?? null);
        $g = strtoupper(substr($c['gender'] ?? 'M', 0, 1));
        $slot = ($age < 5 ? 'u5' : 'a5') . ($g === 'F' ? 'f' : 'm');
        $hospPriority[$rowNum][$slot]++;
    }
}

$data['hospitalization']=[
    'present_start'=>$hospA,'admissions'=>$hospB,
    'discharged'=>$hospC1,'deaths'=>$hospC2,
    'unauthorized'=>0,'referred_out'=>$hospC4,
    'present_end'=>max(0,$hospA+$hospB-$hospC1-$hospC2-$hospC4),
    'beds'=>$hospE,'insured_adm'=>$hospF,
    'potential_days'=>$hospE*$days,'actual_days'=>$hospH,'total_stay'=>$hospH,
    'age_admissions'=>$jAdm,'age_deaths'=>$jDth,
    'priority'=>$hospPriority,
];

// ── ANC ─────────────────────────────────────────────────────────────────
$ancVisits=array_filter($allVisits,function($v){
    // ANC dept by department_id — also check by visit_type or dept name
    return stripos($v['visit_type']??'','anc')!==false
        || stripos($v['visit_type']??'','prenatal')!==false
        || stripos($v['visit_type']??'','maternit')!==false;
});
// Also query by department name
$sAnc=$pdo->prepare("
    SELECT COUNT(DISTINCT v.patient_id) FROM visits v
    JOIN departments d ON d.id=v.department_id
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
      AND (LOWER(d.name) LIKE '%anc%' OR LOWER(d.name) LIKE '%prenatal%'
           OR LOWER(d.name) LIKE '%matern%' OR LOWER(d.name) LIKE '%ante natal%')
");
$sAnc->execute([$from,$to]); $ancNewReg=(int)$sAnc->fetchColumn();

$data['anc']=[
    'new_registrations'=>$ancNewReg,'under_14'=>0,
    'age_14_17'=>0,'age_18_19'=>0,
    'first_visit_12w'=>0,'four_visits'=>0,'risk_detected'=>0,
];

// ── Deliveries ───────────────────────────────────────────────────────────
$sDel=$pdo->prepare("
    SELECT COUNT(*) FROM visits v
    JOIN departments d ON d.id=v.department_id
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
      AND (LOWER(d.name) LIKE '%deliver%' OR LOWER(d.name) LIKE '%labor%'
           OR LOWER(d.name) LIKE '%labour%' OR LOWER(d.name) LIKE '%matern%')
");
$sDel->execute([$from,$to]); $deliveries=(int)$sDel->fetchColumn();

$sCS=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND (c.diagnosis LIKE '%caesarean%' OR c.diagnosis LIKE '%cesarean%' OR c.diagnosis LIKE '%c-section%' OR c.icd_code LIKE 'O82%')");
$sCS->execute([$from,$to]); $csections=(int)$sCS->fetchColumn();

$data['deliveries']=['total'=>$deliveries,'caesarean'=>$csections,'live_births'=>max(0,$deliveries-$csections),'still_fresh'=>0,'still_mac'=>0,'maternal_deaths'=>$hospC2];

// ── XIV. Finances ────────────────────────────────────────────────────────
$sRevIns=$pdo->prepare("
    SELECT ic.name as ins_name, COALESCE(SUM(pr.amount_paid),0) as total
    FROM payment_records pr
    JOIN invoices i ON i.id=pr.invoice_id
    LEFT JOIN patient_insurance pi ON pi.id=i.insurance_id
    LEFT JOIN insurance_companies ic ON ic.id=pi.company_id
    WHERE DATE(pr.created_at) BETWEEN ? AND ? AND pr.status='completed'
    GROUP BY ic.name
");
$sRevIns->execute([$from,$to]);
$insRevRows=$sRevIns->fetchAll(PDO::FETCH_ASSOC);

$insMap=['Mutuelle RSSB'=>0,'RAMA-RSSB'=>0,'MMI'=>0,'SORAS'=>0,'RADIANT'=>0,
         'SAHAM'=>0,'UAP'=>0,'Britam'=>0,'Prime'=>0,'Mutuelle UR'=>0,'FARG'=>0];
foreach($insRevRows as $r){
    foreach($insMap as $k=>$v){ if(stripos($r['ins_name']??'',$k)!==false){ $insMap[$k]+=(float)$r['total']; break; } }
}

$sOOP=$pdo->prepare("SELECT COALESCE(SUM(pr.amount_paid),0) FROM payment_records pr JOIN invoices i ON i.id=pr.invoice_id WHERE DATE(pr.created_at) BETWEEN ? AND ? AND pr.status='completed' AND i.payment_type!='insurance'");
$sOOP->execute([$from,$to]); $oop=(float)$sOOP->fetchColumn();

$sCopay=$pdo->prepare("SELECT COALESCE(SUM(pr.amount_paid),0) FROM payment_records pr JOIN invoices i ON i.id=pr.invoice_id WHERE DATE(pr.created_at) BETWEEN ? AND ? AND pr.status='completed' AND i.payment_type='insurance' AND pr.payment_method IN ('cash','mobile_money','mtn_momo','bank')");
$sCopay->execute([$from,$to]); $copay=(float)$sCopay->fetchColumn();

$revs=array_values($insMap);
$revs[]=$copay; $revs[]=$oop; $revs[]=0; $revs[]=0; $revs[]=0;
$data['finances']=['revenues'=>$revs,'total_revenue'=>array_sum($revs),'expenditures'=>[0,0,0,0]];

// ── XV. Staffing ─────────────────────────────────────────────────────────
$sStaff=$pdo->query("SELECT position, gender, date_of_birth, status FROM employees WHERE status='active'");
$staffRows=$sStaff->fetchAll(PDO::FETCH_ASSOC);

$staffCatMap=[
    1=>['specialist','surgeon specia'],2=>['doctor','physician','médecin'],
    3=>['dental surgeon'],4=>['nurse','infirmier'],5=>['midwife','sage-femme'],
    6=>['dentist therapist'],7=>['pharmacist','pharmacien'],
    8=>['lab tech','laborantin'],9=>['ophthalm','eye officer'],
    10=>['anesthes'],11=>['nutritionist'],12=>['mental health'],
    13=>['imagery','radiology tech'],14=>['environmental health'],
    15=>['biomedical'],16=>['physio'],17=>['health information','data manager'],
    18=>['social worker'],19=>['psychologist'],20=>['admin','support'],
];
$staffCounts=[];
for($i=1;$i<=20;$i++) $staffCounts[$i]=['m'=>0,'f'=>0,'a1'=>0,'a2'=>0,'a3'=>0,'a4'=>0,'a5'=>0,'a6'=>0,'rw'=>0,'fo'=>0];

foreach($staffRows as $r){
    $pos=strtolower($r['position']??'');
    $cat=20;
    foreach($staffCatMap as $n=>$kws){ if(matchAny($pos,$kws)){$cat=$n;break;} }
    $g=strtoupper(substr($r['gender']??'M',0,1));
    $age=calcAge($r['date_of_birth']??null);
    $ak=$age<25?'a1':($age<35?'a2':($age<45?'a3':($age<55?'a4':($age<65?'a5':'a6'))));
    $nk='rw'; // nationality not tracked in employees table, default Rwandan
    if($g==='F') $staffCounts[$cat]['f']++; else $staffCounts[$cat]['m']++;
    $staffCounts[$cat][$ak]++;
    $staffCounts[$cat][$nk]++;
}
$data['staffing']=$staffCounts;

// ── XIX. LABORATORY ──────────────────────────────────────────────────────────
// Pull from medical_orders / lab_test_parameters where available
// Checks lab_test_parameters for positive/negative configuration,
// evaluates structured/unstructured results, and shows completed exams for quantitative tests.
$lab = [];

// 1. Fetch all parameters defined in lab_test_parameters with service names
$stmtParams = $pdo->query("SELECT sc.id as service_id, LOWER(sc.procedure_name) as procedure_name, p.test_name, p.param_type, p.options, p.normal_value FROM service_catalog sc JOIN lab_test_parameters p ON p.service_id = sc.id");
$allLabParams = $stmtParams ? $stmtParams->fetchAll(PDO::FETCH_ASSOC) : [];

$checkParamHasPosNeg = function(array $keywords) use ($allLabParams): bool {
    foreach ($allLabParams as $p) {
        $pname = $p['procedure_name'] ?? '';
        foreach ($keywords as $kw) {
            if (str_contains($pname, strtolower($kw))) {
                $ptype = $p['param_type'] ?? '';
                $opts = strtolower($p['options'] ?? '');
                if ($ptype === 'pos_neg' || str_contains($opts, 'positive') || str_contains($opts, 'negative') || str_contains($opts, 'reactive') || str_contains($opts, 'non-reactive')) {
                    return true;
                }
            }
        }
    }
    return false;
};

// 2. Fetch completed lab orders in the period
$stmtOrders = $pdo->prepare("SELECT mo.id, mo.description, mo.result, mo.status,
    v.visit_date, TIMESTAMPDIFF(YEAR, p.date_of_birth, v.visit_date) as age
    FROM medical_orders mo
    JOIN visits v ON v.id = mo.visit_id
    LEFT JOIN patients p ON p.id = mo.patient_id
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
      AND mo.order_type = 'lab'
      AND LOWER(mo.description) NOT LIKE '%ambulance%'
      AND LOWER(mo.description) NOT LIKE '%consultation%'
      AND (mo.status = 'completed' OR (mo.result IS NOT NULL AND TRIM(mo.result) != '' AND mo.result NOT LIKE 'Sample collected%'))");
$stmtOrders->execute([$from, $to]);
$labOrders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

$evalOrder = function(array $order): array {
    $raw = trim((string)($order['result'] ?? ''));
    $res = ['status' => 'neutral', 'species' => null, 'parasites' => [], 'dip' => false];
    if ($raw === '') return $res;
    
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        foreach ($decoded as $param) {
            $tname = strtolower((string)($param['test_name'] ?? ''));
            $val = trim((string)($param['value'] ?? ''));
            $ptype = (string)($param['param_type'] ?? '');
            if ($val === '') continue;
            $lval = strtolower($val);
            
            if (str_contains($tname, 'diplococcus') || str_contains($lval, 'diplococcus')) {
                if ($lval === 'positive' || str_contains($lval, 'pos') || $lval === '+') $res['dip'] = true;
            }
            if ($ptype === 'pos_neg') {
                if (in_array($lval, ['positive','pos','reactive','réactif','+','positif'])) $res['status'] = 'pos';
                elseif (in_array($lval, ['negative','neg','non-reactive','non-réactif','-','négatif'])) {
                    if ($res['status'] !== 'pos') $res['status'] = 'neg';
                }
            }
            if (str_contains($lval, 'falciparum')) { $res['species'] = 'pf'; $res['status'] = 'pos'; }
            elseif (str_contains($lval, 'malariae') || (str_contains($lval, 'malaria') && !str_contains($lval, 'rapid') && !str_contains($lval, 'test'))) { $res['species'] = 'pm'; $res['status'] = 'pos'; }
            elseif (str_contains($lval, 'vivax')) { $res['species'] = 'pv'; $res['status'] = 'pos'; }
            elseif (str_contains($lval, 'knowlesi')) { $res['species'] = 'pk'; $res['status'] = 'pos'; }
            elseif (str_contains($lval, 'plasmodium')) { $res['status'] = 'pos'; }
            
            if (str_contains($lval, 'ascaris')) { $res['parasites'][] = 'ascaris'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'trichur') || str_contains($lval, 'tricocephale')) { $res['parasites'][] = 'trichuris'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'ankylostoma') || str_contains($lval, 'hookworm')) { $res['parasites'][] = 'ankylostoma'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'schisto')) { $res['parasites'][] = 'schisto'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'taenia')) { $res['parasites'][] = 'taenia'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'entamoeba') || str_contains($lval, 'amoeba')) { $res['parasites'][] = 'entamoeba'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'giardia')) { $res['parasites'][] = 'giardia'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'enterobius') || str_contains($lval, 'oxyure')) { $res['parasites'][] = 'enterobius'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'strongyloid') || str_contains($lval, 'anguillule')) { $res['parasites'][] = 'strongyloides'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'trichomonas')) { $res['parasites'][] = 'trichomonas'; $res['status'] = 'pos'; }
            if (str_contains($lval, 'levure') || str_contains($lval, 'kyste') || str_contains($lval, 'parasit')) {
                if (empty($res['parasites'])) $res['parasites'][] = 'other_stool';
                $res['status'] = 'pos';
            }
            if (in_array($lval, ['ras', 'no sample', 'nil', 'rien à signaler', 'rien a signaler'])) {
                if ($res['status'] !== 'pos') $res['status'] = 'neg';
            }
            if (in_array($lval, ['positive', 'positif', 'reactive', 'pos'])) $res['status'] = 'pos';
            elseif (in_array($lval, ['negative', 'négatif', 'non-reactive', 'neg'])) {
                if ($res['status'] !== 'pos') $res['status'] = 'neg';
            }
        }
    } else {
        $lres = strtolower($raw);
        if (str_contains($lres, 'diplococcus') && (str_contains($lres, 'pos') || str_contains($lres, '+'))) $res['dip'] = true;
        if (str_contains($lres, 'falciparum')) { $res['species'] = 'pf'; $res['status'] = 'pos'; }
        elseif (str_contains($lres, 'malariae') || (str_contains($lres, 'malaria') && !str_contains($lres, 'rapid'))) { $res['species'] = 'pm'; $res['status'] = 'pos'; }
        elseif (str_contains($lres, 'vivax')) { $res['species'] = 'pv'; $res['status'] = 'pos'; }
        elseif (str_contains($lres, 'knowlesi')) { $res['species'] = 'pk'; $res['status'] = 'pos'; }
        elseif (str_contains($lres, 'plasmodium')) { $res['status'] = 'pos'; }
        
        if (str_contains($lres, 'ascaris')) { $res['parasites'][] = 'ascaris'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'trichur') || str_contains($lres, 'tricocephale')) { $res['parasites'][] = 'trichuris'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'ankylostoma') || str_contains($lres, 'hookworm')) { $res['parasites'][] = 'ankylostoma'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'schisto')) { $res['parasites'][] = 'schisto'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'taenia')) { $res['parasites'][] = 'taenia'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'entamoeba')) { $res['parasites'][] = 'entamoeba'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'giardia')) { $res['parasites'][] = 'giardia'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'enterobius') || str_contains($lres, 'oxyure')) { $res['parasites'][] = 'enterobius'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'strongyloid')) { $res['parasites'][] = 'strongyloides'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'trichomonas')) { $res['parasites'][] = 'trichomonas'; $res['status'] = 'pos'; }
        if (str_contains($lres, 'levure') || str_contains($lres, 'kyste')) {
            if (empty($res['parasites'])) $res['parasites'][] = 'other_stool';
            $res['status'] = 'pos';
        }
        if (preg_match('/\b(positive|positif|reactive|réactif|\+)\b/i', $lres)) $res['status'] = 'pos';
        elseif (preg_match('/\b(negative|négatif|non-reactive|non-réactif|ras|nil|normal|-)\b/i', $lres)) {
            if ($res['status'] !== 'pos') $res['status'] = 'neg';
        }
    }
    return $res;
};

// Evaluate each order
foreach ($labOrders as &$o) {
    $o['eval'] = $evalOrder($o);
}
unset($o);

$matchKeywords = function(string $desc, array $kws): bool {
    $ld = strtolower($desc);
    foreach ($kws as $k) {
        if (str_contains($ld, strtolower($k))) return true;
    }
    return false;
};

$assigned = [];
$assignOrder = function($oid) use (&$assigned){ $assigned[$oid] = true; };

$summarizeOrders = function(array $orders, array $keywords, bool $defaultHasPN = true) use ($checkParamHasPosNeg): array {
    $pos = 0; $neg = 0; $u5 = 0; $p5 = 0; $tot = 0;
    foreach ($orders as $o) {
        $tot++;
        $st = $o['eval']['status'] ?? 'neutral';
        if ($st === 'pos') $pos++;
        elseif ($st === 'neg') $neg++;
        $age = $o['age'] !== null ? (int)$o['age'] : 99;
        if ($age < 5) $u5++; else $p5++;
    }
    $hasPNParam = $checkParamHasPosNeg($keywords);
    $hasPNResult = ($pos > 0 || $neg > 0);
    $hasPosNeg = $hasPNParam || $hasPNResult || $defaultHasPN;
    return [
        'pos' => $pos,
        'neg' => $neg,
        'tot' => $tot,
        'completed' => $tot,
        'u5'  => $u5,
        'p5'  => $p5,
        'has_pos_neg' => $hasPosNeg
    ];
};

// 1. Blood Smears
$bsKeywords = ['blood smear','plasmodium','thick film','thin smear','goutte epaisse'];
$bsOrders = array_filter($labOrders, function($o) use ($matchKeywords, $bsKeywords) { return $matchKeywords($o['description'], $bsKeywords); });
foreach ($bsOrders as $o) $assignOrder($o['id']);
$bs = $summarizeOrders($bsOrders, $bsKeywords, true);
$lab['bs_pos'] = $bs['pos'];
$lab['bs_neg'] = $bs['neg'];
$lab['bs_u5']  = $bs['u5'];
$lab['bs_5p']  = $bs['p5'];
$lab['bs_tot'] = $bs['tot'];
$lab['bs_has_pos_neg'] = true;

// Subspecies
$speciesCounts = [
    'pf' => ['pos' => 0, 'neg' => 0, 'tot' => 0],
    'pm' => ['pos' => 0, 'neg' => 0, 'tot' => 0],
    'pv' => ['pos' => 0, 'neg' => 0, 'tot' => 0],
    'pk' => ['pos' => 0, 'neg' => 0, 'tot' => 0]
];
foreach ($bsOrders as $o) {
    $sp = $o['eval']['species'] ?? null;
    if ($sp && isset($speciesCounts[$sp])) {
        $speciesCounts[$sp]['pos']++;
        $speciesCounts[$sp]['tot']++;
    }
}
foreach (['pf','pm','pv','pk'] as $sk) {
    $lab[$sk.'_pos'] = $speciesCounts[$sk]['pos'];
    $lab[$sk.'_neg'] = $speciesCounts[$sk]['neg'];
    $lab[$sk.'_tot'] = $speciesCounts[$sk]['tot'];
    $lab[$sk.'_has_pos_neg'] = true;
}

// 2. RDT
$rdtKeywords = ['rapid diagnostic','rdt','rdts','malaria rapid','hrp2'];
$rdtOrders = array_filter($labOrders, function($o) use ($matchKeywords, $rdtKeywords) { return $matchKeywords($o['description'], $rdtKeywords); });
foreach ($rdtOrders as $o) $assignOrder($o['id']);
$rdt = $summarizeOrders($rdtOrders, $rdtKeywords, true);
$lab['rdt_pos'] = $rdt['pos'];
$lab['rdt_neg'] = $rdt['neg'];
$lab['rdt_u5']  = $rdt['u5'];
$lab['rdt_5p']  = $rdt['p5'];
$lab['rdt_tot'] = $rdt['tot'];
$lab['rdt_has_pos_neg'] = true;

// 3. Stools
$stoolKeywords = ['stool','selle','coprologie'];
$stoolOrders = array_filter($labOrders, function($o) use ($matchKeywords, $stoolKeywords) { return $matchKeywords($o['description'], $stoolKeywords); });
foreach ($stoolOrders as $o) $assignOrder($o['id']);
$st = $summarizeOrders($stoolOrders, $stoolKeywords, true);
$lab['st_pos'] = $st['pos'];
$lab['st_neg'] = $st['neg'];
$lab['st_u5']  = $st['u5'];
$lab['st_5p']  = $st['p5'];
$lab['st_tot'] = $st['tot'];
$lab['st_has_pos_neg'] = true;

// Stool parasites by age group (0-4, 5-15, 16+)
$stoolParasites = [
    'ascaris'      => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'trichuris'    => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'ankylostoma'  => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'schisto'      => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'taenia'       => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'entamoeba'    => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'giardia'      => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'enterobius'   => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'strongyloides'=> ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'trichomonas'  => ['a1'=>0, 'a2'=>0, 'a3'=>0],
    'other_stool'  => ['a1'=>0, 'a2'=>0, 'a3'=>0],
];
foreach ($stoolOrders as $o) {
    $age = $o['age'] !== null ? (int)$o['age'] : 99;
    $ag = $age <= 4 ? 'a1' : ($age <= 15 ? 'a2' : 'a3');
    foreach ($o['eval']['parasites'] as $pk) {
        if (isset($stoolParasites[$pk])) {
            $stoolParasites[$pk][$ag]++;
        }
    }
}
$lab['stool_parasites'] = $stoolParasites;

// 4. Urine
$urineMap = [
    '41' => ['keywords' => ['sugar','glucose urine','glucosir'], 'defaultPN' => true],
    '42' => ['keywords' => ['albumin','protein urine','albuminur','proteinur'], 'defaultPN' => true],
    '43' => ['keywords' => ['pregnancy test','urine preg','test de grossesse'], 'defaultPN' => true],
];
foreach ($urineMap as $k => $cfg) {
    $kws = $cfg['keywords'];
    $uOrders = array_filter($labOrders, function($o) use ($kws, $k, $matchKeywords) {
        if ($k === '43' && $matchKeywords($o['description'], ['blood','serum'])) return false;
        return $matchKeywords($o['description'], $kws);
    });
    foreach ($uOrders as $o) $assignOrder($o['id']);
    $lab['u'.$k] = $summarizeOrders($uOrders, $kws, $cfg['defaultPN']);
}

// 5. Bacteriology
$bactMap = [
    'b51af' => function($d) use ($matchKeywords) { return $matchKeywords($d, ['vaginal','cervical','fv']) && $matchKeywords($d, ['fresh','frais']); },
    'b51ag' => function($d) use ($matchKeywords) { return $matchKeywords($d, ['vaginal','cervical','fv']) && $matchKeywords($d, ['gram']); },
    'b52af' => function($d) use ($matchKeywords) { return $matchKeywords($d, ['urethral','uretre','fu']) && $matchKeywords($d, ['fresh','frais']); },
    'b52ag' => function($d) use ($matchKeywords) { return $matchKeywords($d, ['urethral','uretre','fu']) && $matchKeywords($d, ['gram']); },
    'b53af' => function($d) use ($matchKeywords) { return $matchKeywords($d, ['urine']) && $matchKeywords($d, ['fresh','frais','sediment']); },
    'b53ag' => function($d) use ($matchKeywords) { return $matchKeywords($d, ['urine']) && $matchKeywords($d, ['gram','ecbu','culture']); },
];
foreach ($bactMap as $bk => $fn) {
    $bOrders = array_filter($labOrders, function($o) use ($fn) { return $fn($o['description']); });
    foreach ($bOrders as $o) $assignOrder($o['id']);
    $s = $summarizeOrders($bOrders, ['gram stain', 'swab'], true);
    $s['dip'] = count(array_filter($bOrders, function($o) { return !empty($o['eval']['dip']); }));
    $lab[$bk] = $s;
}

// 6. Blood
$bloodMap = [
    'b61'  => ['keywords' => ['rpr','vdrl','syphilis','tpha'], 'defaultPN' => true],
    'b62'  => ['keywords' => ['hiv','vih'], 'defaultPN' => true],
    'b63'  => ['keywords' => ['esr','sedimentation','vs'], 'defaultPN' => false],
    'b64'  => ['keywords' => ['full blood','fbc','nfs','cbc','hemogramme'], 'defaultPN' => false],
    'b65'  => ['keywords' => ['alat','gpt','alanine','sgpt'], 'defaultPN' => false],
    'b66'  => ['keywords' => ['creatinine','creat'], 'defaultPN' => false],
    'b67'  => ['keywords' => ['blood glucose','glycemia','glycémie','glycemie'], 'defaultPN' => false],
    'b68'  => ['keywords' => ['amylase'], 'defaultPN' => false],
    'b69'  => ['keywords' => ['cd4'], 'defaultPN' => false],
    'b610' => ['keywords' => ['pregnancy test blood','serum pregnancy','bhcg'], 'defaultPN' => true],
    'b611' => ['keywords' => ['crag','cryptococcal'], 'defaultPN' => true],
    'b612' => ['keywords' => ['hbv','hepatitis b','hepatite b','hbs','aghbs'], 'defaultPN' => true],
    'b613' => ['keywords' => ['hcv','hepatitis c','hepatite c','anti-hcv'], 'defaultPN' => true],
];
foreach ($bloodMap as $k => $cfg) {
    $kws = $cfg['keywords'];
    $bOrders = array_filter($labOrders, function($o) use ($matchKeywords, $kws) { return $matchKeywords($o['description'], $kws); });
    foreach ($bOrders as $o) $assignOrder($o['id']);
    $lab[$k] = $summarizeOrders($bOrders, $kws, $cfg['defaultPN']);
}

// 7. CSF
$csfKeywords = ['csf','cerebrospinal','lcr'];
$csfOrders = array_filter($labOrders, function($o) use ($matchKeywords, $csfKeywords) { return $matchKeywords($o['description'], $csfKeywords); });
foreach ($csfOrders as $o) $assignOrder($o['id']);
$lab['csf'] = $summarizeOrders($csfOrders, $csfKeywords, false);

// 8. Other Lab tests
$othOrders = array_filter($labOrders, function($o) use ($assigned) { return empty($assigned[$o['id']]); });
$othSummary = $summarizeOrders($othOrders, ['other'], false);
$lab['oth'] = $othSummary;

// Grand totals
$grandPos = 0; $grandNeg = 0; $grandTot = count($labOrders);
foreach ($labOrders as $o) {
    $st = $o['eval']['status'] ?? 'neutral';
    if ($st === 'pos') $grandPos++;
    elseif ($st === 'neg') $grandNeg++;
}
$lab['grand_pos'] = $grandPos;
$lab['grand_neg'] = $grandNeg;
$lab['grand_tot'] = $grandTot;
$data['lab'] = $lab;

// ── IX. SURGERY ───────────────────────────────────────────────────────────────
$surgery=[];

// Map category_index => keywords to match in surgeries.procedure_name or consultations.diagnosis
$surgMap=[
  'general_0'=>['appendicectomy','appendectomy'],
  'general_1'=>['hernioraphy','herniorrhaphy'],
  'general_2'=>['laparotomy'],
  'general_3'=>['thyroidectomy'],
  'general_4'=>['adenomectomy'],
  'general_5'=>['breast surgery','mastectomy'],
  'general_6'=>['hydrocele'],
  'general_7'=>['testis torsion','testicular torsion'],
  'general_8'=>['hemorrhoidectomy','haemorrhoidectomy'],
  'general_9'=>['tissue debridement'],
  'general_10'=>['skin graft'],
  'general_11'=>['hernia repair'],
  'general_12'=>['ascariasis'],
  'gyneco_0'=>['caesarean','cesarean','c-section'],
  'gyneco_1'=>['dilatation','curettage','d&c','d and c'],
  'gyneco_2'=>['gynecological hysterectomy'],
  'gyneco_3'=>['obstetrical hysterectomy'],
  'gyneco_4'=>['uterine rupture'],
  'gyneco_5'=>['ectopic pregnancy','laparotomy ectopic'],
  'gyneco_6'=>['uterine repair'],
  'gyneco_7'=>['myomectomy'],
  'gyneco_8'=>['vesico vaginal fistula','vesicovaginal'],
  'gyneco_9'=>['recto vaginal fistula','rectovaginal'],
  'gyneco_10'=>['ureteric vaginal fistula'],
  'gyneco_11'=>['tubal ligation'],
  'gyneco_12'=>['vasectomy'],
  'gyneco_13'=>['episiotomy'],
  'gyneco_14'=>['other gyneco','other obstetric'],
  'ortho_0'=>['amputation'],
  'ortho_1'=>['open fracture','external fixation'],
  'ortho_2'=>['clubfoot'],
  'ortho_3'=>['closed fracture','closed treatment'],
  'ortho_4'=>['joint dislocation'],
  'ortho_5'=>['osteomyelitis','septic arthritis'],
  'ortho_6'=>['orthopedic other','orthopaedic other'],
  'ophtha_0'=>['glaucoma'],
  'ophtha_1'=>['trachoma'],
  'ophtha_2'=>['cataract'],
  'ophtha_3'=>['eye trauma'],
  'ophtha_4'=>['ophthalmological other','eye surgery other'],
  'ophtha_5'=>['cornea transplant'],
  'ophtha_6'=>['pterygium'],
  'plastic_0'=>['cleft palate','cleft lip','bec de lievre'],
  'plastic_1'=>['other plastic'],
  'other_0'=>['neonatal abdominal'],
  'other_1'=>['colostomy','imperforate anus'],
  'other_2'=>['cystostomy'],
  'other_3'=>['chest tube'],
  'other_4'=>['tracheostomy','cricothyroidotomy'],
  'other_5'=>['circumcision'],
  'other_6'=>['suturing wound','wound suture'],
  'other_7'=>['wound debridement'],
  'other_8'=>['incision and drainage','abscess drainage'],
];

// Try surgeries table first, fall back to consultations diagnosis
$hasSurgTable=false;
try{$pdo->query("SELECT 1 FROM surgeries LIMIT 1");$hasSurgTable=true;}catch(Exception $e){}

foreach($surgMap as $key=>$kws){
    $likes=implode(' OR ',array_fill(0,count($kws),"LOWER(s.procedure_name) LIKE ?"));
    $params=array_merge([$from,$to],array_map(function($k){ return "%$k%"; },$kws));
    $total=0; $infect=0;
    if($hasSurgTable){
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM surgeries s WHERE DATE(s.scheduled_date) BETWEEN ? AND ? AND ($likes)");
        $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        // Post-surgical infection: look for follow-up diagnosis
        $iLikes=implode(' OR ',array_fill(0,count($kws),"LOWER(c.diagnosis) LIKE ?"));
        $iParams=array_merge([$from,$to],array_map(function($k){ return "%$k%"; },$kws));
        $iStmt=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($iLikes) AND LOWER(c.diagnosis) LIKE '%infection%'");
        $iStmt->execute($iParams); $infect=(int)$iStmt->fetchColumn();
    } else {
        // Fall back to consultations
        $cLikes=implode(' OR ',array_fill(0,count($kws),"LOWER(c.diagnosis) LIKE ?"));
        $cParams=array_merge([$from,$to],array_map(function($k){ return "%$k%"; },$kws));
        $cStmt=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($cLikes)");
        $cStmt->execute($cParams); $total=(int)$cStmt->fetchColumn();
    }
    $surgery[$key]=['total'=>$total,'infection'=>$infect];
}

// Totals
$majorKws=['caesarean','laparotomy','hysterectomy','thyroidectomy','mastectomy','appendicectomy','amputation','cataract','hernia repair'];
$minorKws=['circumcision','suturing','debridement','incision and drainage','episiotomy','wound'];
function surgCount(PDO $pdo, string $from, string $to, array $kws, bool $hasSurg): int {
    $likes=implode(' OR ',array_fill(0,count($kws),'LOWER(s.procedure_name) LIKE ?'));
    $params=array_merge([$from,$to],array_map(function($k){ return "%$k%"; },$kws));
    if($hasSurg){ $st=$pdo->prepare("SELECT COUNT(*) FROM surgeries s WHERE DATE(s.scheduled_date) BETWEEN ? AND ? AND ($likes)"); }
    else { $likes=implode(' OR ',array_fill(0,count($kws),'LOWER(c.diagnosis) LIKE ?')); $st=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($likes)"); }
    $st->execute($params); return (int)$st->fetchColumn();
}
$major=surgCount($pdo,$from,$to,$majorKws,$hasSurgTable);
$minor=surgCount($pdo,$from,$to,$minorKws,$hasSurgTable);
$totalAll=array_sum(array_column($surgery,'total'));
$infectAll=array_sum(array_column($surgery,'infection'));
$surgery['totals']=['total'=>$totalAll,'infection'=>$infectAll,'major'=>$major,'minor'=>$minor];

// Anesthesia
$anesMap=['general'=>['general anesthesia','general anaesthesia'],'regional'=>['regional anesthesia','spinal','epidural'],'local'=>['local anesthesia','local anaesthesia']];
$surgery['anesthesia']=[];
foreach($anesMap as $ak=>$akws){
    $likes=implode(' OR ',array_fill(0,count($akws),'LOWER(c.diagnosis) LIKE ?'));
    $params=array_merge([$from,$to],array_map(function($k){ return "%$k%"; },$akws));
    $st=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($likes)");
    $st->execute($params); $surgery['anesthesia'][$ak]=(int)$st->fetchColumn();
}

// Waiting list from surgeries table if available
$surgery['waiting_list']=['u1m_major'=>0,'u1m_minor'=>0,'o1m_major'=>0,'o1m_minor'=>0,'o3m_done'=>0];
if($hasSurgTable){
    $wl=$pdo->prepare("SELECT
        SUM(CASE WHEN DATEDIFF(NOW(),created_at)<30 THEN 1 ELSE 0 END) u1m_major,
        0 u1m_minor,
        SUM(CASE WHEN DATEDIFF(NOW(),created_at)>=30 THEN 1 ELSE 0 END) o1m_major,
        0 o1m_minor,
        SUM(CASE WHEN DATEDIFF(scheduled_date,created_at)>90 AND DATE(scheduled_date) BETWEEN ? AND ? THEN 1 ELSE 0 END) o3m_done
        FROM surgeries WHERE status='scheduled'");
    $wl->execute([$from,$to]); $wlRow=$wl->fetch(PDO::FETCH_ASSOC);
    if($wlRow) $surgery['waiting_list']=['u1m_major'=>(int)($wlRow['u1m_major']??0),'u1m_minor'=>0,'o1m_major'=>(int)($wlRow['o1m_major']??0),'o1m_minor'=>0,'o3m_done'=>(int)($wlRow['o3m_done']??0)];
}
$data['surgery']=$surgery;

// ── XII. OBSTETRICAL COMPLICATIONS ───────────────────────────────────────────
$obsMap = [
    ['abortion','avortement'],
    ['spontaneous abortion','fausse couche'],
    ['complication.*abortion','avortement compliqué','O04','O03'],
    ['threatened abortion','menace.*avortement'],
    ['ectopic pregnancy','grossesse ectopique','O00'],
    ['antepartum hemorrhage','hémorragie prénatale','O46'],
    ['post.*partum hemorrhage','hémorragie post.*partum','O72'],
    ['post.*cesarean hemorrhage','hémorragie post.*cesarienne'],
    ['eclampsia','eclampsie','O15'],
    ['severe pre.*eclampsia','pre.*eclampsie sévère','O14'],
    ['post.*section infection onsite','infection post.*cesarienne','O85'],
    ['post.*section infection.*peritonitis','peritonite post'],
    ['postpartum infection','infection puerpérale','O86'],
    ['obstructed labor','travail prolongé','O66'],
    ['uterine rupture','rupture utérine','S37'],
    ['amniotic embolism','embolie amniotique'],
    ['anesthesia complication','complication.*anesthésie'],
    ['gestational trophoblastic','trophoblastique','GTN'],
    ['hyperemesis','hyperémèse'],
    ['intrauterine fetal death','IUFD','mort foetale'],
    ['deep vein thrombosis','DVT','thrombose veineuse'],
    ['obstetrical fistula','fistule obstétricale'],
    ['other direct obstetric','autre complication directe','O75'],
    ['severe anemia','anémie sévère','O99'],
    ['HIV.*opportunistic','VIH.*opportuniste','O98'],
    ['pulmonary embolism','embolie pulmonaire'],
    ['pneumonia.*pregnancy','pneumonie.*grossesse'],
    ['asthma.*pregnancy','asthme.*grossesse'],
    ['diabetes.*pregnancy','diabète.*grossesse'],
    ['chronic kidney','insuffisance rénale chronique','CKD'],
    ['puerperal psychosis','psychose puerpérale','dépression post'],
    ['epilepsia','épilepsie.*grossesse'],
    ['cellulitis','cellulite'],
    ['ileus paralytic','iléus paralytique'],
    ['severe malnutrition','malnutrition sévère'],
    ['urinary tract infection','infection urinaire','UTI'],
    ['obstetrical cancer','cancer obstétrical'],
    ['other indirect obstetric','autre complication indirecte'],
];

$obsCases=[];
foreach($obsMap as $kws){
    $likes=implode(' OR ',array_fill(0,count($kws),"LOWER(c.diagnosis) LIKE ?"));
    $params_opd=array_merge([$from,$to],array_map(function($k){ return "%".strtolower($k)."%"; },$kws));
    // OPD new cases
    $sOpd=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND v.visit_type IN ('outpatient','opd','consultation','') AND ($likes)");
    $sOpd->execute($params_opd); $opd=(int)$sOpd->fetchColumn();
    // Hospitalized
    $sHosp=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id JOIN bed_assignments ba ON ba.visit_id=v.id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($likes)");
    $sHosp->execute($params_opd); $hosp=(int)$sHosp->fetchColumn();
    // Deaths
    $sDth=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND v.status='deceased' AND ($likes)");
    $sDth->execute($params_opd); $deaths=(int)$sDth->fetchColumn();
    $obsCases[]=['opd'=>$opd,'hosp'=>$hosp,'deaths'=>$deaths];
}

// B. EONC
$eoncMap=[
    ['antibiotic','IV.*antibiotic','IM.*antibiotic','antibiotique intraveineuse'],
    ['uterotonic','oxytocin','misoprostol','utérotonique'],
    ['manual removal.*placenta','délivrance manuelle'],
    ['post.*abortion care','manual vacuum aspiration','aspiration manuelle','MVA'],
    ['vacuum extraction','ventouse','forceps'],
    ['magnesium sulfate','sulfate de magnésium','eclampsia.*magnesium'],
    ['blood transfusion','transfusion sanguine'],
    ['referred.*obstetric','transferée.*complications'],
];
$eoncCounts=[];
foreach($eoncMap as $kws){
    $likes=implode(' OR ',array_fill(0,count($kws),"LOWER(c.diagnosis) LIKE ? OR LOWER(c.treatment_plan) LIKE ?"));
    $params=array_merge([$from,$to]);
    foreach($kws as $k){ $params[]="%".strtolower($k)."%"; $params[]="%".strtolower($k)."%"; }
    $st=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($likes)");
    $st->execute($params); $eoncCounts[]=(int)$st->fetchColumn();
}

// C. Deliveries (29 indicators)
$delivMap=[
    ['delivery','accouchement','childbirth'],
    ['caesarean','cesarean','c-section','O82'],
    ['perineal tear','déchirure périnéale'],
    ['birth trauma','traumatisme.*nouveau'],
    ['cleft palate','cleft lip','bec de lievre','fente labio'],
    ['congenital malformation','malformation congénitale'],
    ['delivery.*under 14','accouchement.*moins 14'],
    ['delivery.*14.*17','accouchement.*14.*17'],
    ['delivery.*18.*19','accouchement.*18.*19'],
    ['multiple.*preg','twin','triplet','jumeau','triplet'],
    ['premature.*delivery.*24.*34','menace.*accouchement.*24.*34'],
    ['corticosteroid','cortisostéroide'],
    ['PPROM','rupture premature','rupture prématurée'],
    ['prophylactic antibiotic.*rupture','antibiotique.*rupture'],
    ['oxytocin.*third stage','oxytocine.*troisième stade'],
    ['maternal death','décès maternel'],
    ['live birth','naissance vivante'],
    ['birth weight.*2500','poids.*2500'],
    ['premature.*newborn','nouveau.*né.*prématuré'],
    ['birth weight.*2.*kg','poids.*2000'],
    ['still.*birth.*fresh','mort né.*frais'],
    ['still.*birth.*macerated','mort né.*macéré'],
    ['still.*birth.*2500','mort né.*2500'],
    ['death.*birth.*30 minutes','décès.*naissance.*30'],
    ['breastfed.*1 hour','allaité.*1 heure'],
    ['not cry','not breathe','n\'a pas crié','n\'a pas respiré'],
    ['resuscitated','réanimé.*succès'],
    ['accompanied.*partner','accompagné.*conjoint'],
    ['disability','handicap','personne handicapée'],
];
$delivCounts=[];
foreach($delivMap as $kws){
    $likes=implode(' OR ',array_fill(0,count($kws),"LOWER(c.diagnosis) LIKE ?"));
    $params=array_merge([$from,$to],array_map(function($k){ return "%".strtolower($k)."%"; },$kws));
    $st=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($likes)");
    $st->execute($params); $delivCounts[]=(int)$st->fetchColumn();
}

$data['obstetrics']=['cases'=>$obsCases,'eonc'=>$eoncCounts,'deliveries'=>$delivCounts];

// ── E/F OPD PRIORITY + EYE/EAR/ORAL ─────────────────────────────────────────
$priority2=[];
foreach($diagMap2 as $rowNum=>$kws){
    $priority2[$rowNum]=['u5m'=>0,'u5f'=>0,'a5m'=>0,'a5f'=>0];
    foreach($allConsults as $c){
        $hay=strtolower(($c['diagnosis']??'').' '.($c['icd_code']??''));
        if(!matchAny($hay,$kws)) continue;
        $age=calcAge($c['date_of_birth']??null);
        $g=strtoupper(substr($c['gender']??'M',0,1));
        $slot=($age<5?'u5':'a5').($g==='F'?'f':'m');
        $priority2[$rowNum][$slot]++;
    }
}

$eyeData=[]; foreach($eyeMap as $kws) $eyeData[]=ageSexBuckets3($allConsults,$kws);
$earData=[]; foreach($earMap as $kws) $earData[]=ageSexBuckets3($allConsults,$kws);
$oralData=[]; foreach($oralMap as $kws) $oralData[]=ageSexBuckets3($allConsults,$kws);

// Dental procedures from invoice_items by description keyword
$dentalData=[];
foreach($dentalProcMap as $kws){
    $code=array_pop($kws); // last element is the code
    $likes=implode(' OR ',array_fill(0,count($kws),"LOWER(ii.description) LIKE ?"));
    $params=array_merge([$from,$to],array_map(function($k){ return "%$k%"; },$kws));
    $st=$pdo->prepare("SELECT COUNT(*) FROM invoice_items ii JOIN invoices i ON i.id=ii.invoice_id WHERE DATE(i.created_at) BETWEEN ? AND ? AND ($likes)");
    $st->execute($params); $dentalData[]=(int)$st->fetchColumn();
}

$data['opd2']=['priority'=>$priority2,'eye'=>$eyeData,'ear'=>$earData,'oral'=>$oralData,'dental'=>$dentalData];

// ── III. MENTAL HEALTH ────────────────────────────────────────────────────────
$mhKeywords=['anxiety','ptsd','stress disorder','schizophrenia','psychosis','somatoform',
    'substance use','alcohol disorder','depression','suicide','bipolar','autism','adhd',
    'hyperactivity','personality disorder','conduct disorder','mental','epilepsy',
    'migraine','parkinson','delirium','dementia'];

$mhLike=implode(' OR ',array_fill(0,count($mhKeywords),"LOWER(c.diagnosis) LIKE ?"));
$mhParams=array_merge([$from,$to],array_map(function($k){ return "%$k%"; },$mhKeywords));

// A. Summary
$sFollowUp=$pdo->prepare("SELECT COUNT(DISTINCT c.patient_id) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($mhLike)");
$sFollowUp->execute($mhParams); $mhFollowUp=(int)$sFollowUp->fetchColumn();

$sHosp=$pdo->prepare("SELECT COUNT(DISTINCT ba.patient_id) FROM bed_assignments ba JOIN visits v ON v.id=ba.visit_id JOIN consultations c ON c.visit_id=v.id WHERE DATE(ba.created_at) BETWEEN ? AND ? AND ($mhLike)");
$sHosp->execute($mhParams); $mhHosp=(int)$sHosp->fetchColumn();

$sRef=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND c.referred_to_dept IS NOT NULL AND c.referred_to_dept!='' AND ($mhLike)");
$sRef->execute($mhParams); $mhRef=(int)$sRef->fetchColumn();

// Patients on medication - from prescriptions
$sMed=$pdo->prepare("SELECT COUNT(DISTINCT pp.patient_id) FROM pharmacy_prescriptions pp JOIN visits v ON v.id=pp.visit_id JOIN consultations c ON c.visit_id=v.id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($mhLike)");
$sMed->execute($mhParams); $mhMed=(int)$sMed->fetchColumn();

$data['mental_health']['summary']=[$mhFollowUp,$mhHosp,$mhRef,$mhMed,0,0,0];

// B. Diagnosis breakdown by age group (0-14,15-19,20-64,65+) and new vs old
$mhDiagMap=[
    ['anxiety','6B00'],['ptsd','post-traumatic','stress disorder','6B40'],
    ['schizophrenia','psychosis','6A20'],['somatoform','6C20'],
    ['substance use','drug abuse','toxicomanie','6C4E'],['alcohol disorder','6C91'],
    ['depression','dépression','6A70'],['suicide'],
    ['bipolar','6A60'],['autism','6A02'],['adhd','hyperactivity','6A05'],
    ['personality disorder','6D10'],['conduct disorder','dissocial'],
    ['other mental','autre.*mental'],['epilepsy','épilepsie','8A60'],
    ['migraine','8A80'],['parkinson','8A00'],['delirium','6D70'],['dementia','démence','6C84'],
];

$mhDiagResults=[];
foreach($mhDiagMap as $kws){
    // Remove ICD codes (short uppercase patterns) from keyword list for SQL
    $sqlKws=array_filter($kws,function($k){ return !preg_match('/^[0-9A-Z]{3,6}$/',$k); });
    if(empty($sqlKws)){ $mhDiagResults[]=['nc'=>[],'oc'=>[]]; continue; }
    $likes=implode(' OR ',array_fill(0,count($sqlKws),"LOWER(c.diagnosis) LIKE ?"));
    $params=array_merge([$from,$to],array_map(function($k){ return "%".strtolower($k)."%"; },$sqlKws));

    $stmt=$pdo->prepare("SELECT p.date_of_birth, p.gender,
        (SELECT 1 FROM visits v2 WHERE v2.patient_id=v.patient_id AND v2.id<v.id LIMIT 1) is_old
        FROM consultations c JOIN visits v ON v.id=c.visit_id JOIN patients p ON p.id=c.patient_id
        WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($likes)");
    $stmt->execute($params);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

    $nc=['a1m'=>0,'a1f'=>0,'a2m'=>0,'a2f'=>0,'a3m'=>0,'a3f'=>0,'a4m'=>0,'a4f'=>0];
    $oc=['a1m'=>0,'a1f'=>0,'a2m'=>0,'a2f'=>0,'a3m'=>0,'a3f'=>0,'a4m'=>0,'a4f'=>0];
    foreach($rows as $r){
        $age=calcAge($r['date_of_birth']??null);
        $g=strtolower(strtoupper(substr($r['gender']??'M',0,1))==='F'?'f':'m');
        $ag=$age<=14?'a1':($age<=19?'a2':($age<=64?'a3':'a4'));
        if(empty($r['is_old'])) $nc[$ag.$g]++; else $oc[$ag.$g]++;
    }
    $mhDiagResults[]=['nc'=>$nc,'oc'=>$oc];
}
$data['mental_health']['diagnoses']=$mhDiagResults;

// ── CANCER SCREENING ─────────────────────────────────────────────────────────
$csMap=[
    ['cervical cancer','dépistage col','cervical screen','cancer du col'],
    ['breast cancer','dépistage sein','breast screen','mammograph'],
    ['biopsy','biopsie'],
    ['HPV positive.*thermoablation','HPV positive.*cryotherapy','VIA.*thermoablation','HPV pos.*cryoth'],
    ['thermoablation','cryotherapy','cryothérapie'],
    ['VIA only','cervical.*VIA'],
    ['VIA negative','negative.*VIA','VIA.*négatif'],
    ['VIA positive','positive.*VIA','VIA.*positif'],
];
$csData=[];
foreach($csMap as $kws){
    $likes=implode(' OR ',array_fill(0,count($kws),"LOWER(c.diagnosis) LIKE ? OR LOWER(c.treatment_plan) LIKE ?"));
    $params=array_merge([$from,$to]);
    foreach($kws as $k){ $params[]="%".strtolower($k)."%"; $params[]="%".strtolower($k)."%"; }
    $st=$pdo->prepare("SELECT COUNT(*) FROM consultations c JOIN visits v ON v.id=c.visit_id WHERE DATE(v.visit_date) BETWEEN ? AND ? AND ($likes)");
    $st->execute($params);
    $csData[]=(int)$st->fetchColumn();
}
$data['cancer_screening']=$csData;

// ── INSURANCE SUMMARY ─────────────────────────────────────────────────────────
// Map provider keyword → column key
$insProvMap=[
    'cbhi'       =>['cbhi','mutuelle','rssb'],
    'rama'       =>['rama'],
    'mmi'        =>['mmi'],
    'ur'         =>['mutuelle ur','ur '],
    'radiant'    =>['radiant'],
    'uap'        =>['uap','old mutual'],
    'sanlam'     =>['sanlam'],
    'britam'     =>['britam'],
    'eden'       =>['eden care','eden'],
    'other'      =>[], // catch-all for insured not matched above
    'noninsured' =>[], // payment_type != insurance
];

// Get all insurance companies
$icStmt=$pdo->query("SELECT id, LOWER(name) as name FROM insurance_companies");
$allIns=$icStmt->fetchAll(PDO::FETCH_ASSOC);

function mapInsCompany(string $name, array $provMap): string {
    foreach($provMap as $key=>$kws){
        if(empty($kws)) continue;
        foreach($kws as $kw){ if(stripos($name,$kw)!==false) return $key; }
    }
    return 'other';
}

// Build company_id => provider_key map
$companyKeyMap=[];
foreach($allIns as $ic){
    $companyKeyMap[(int)$ic['id']]=mapInsCompany($ic['name'],$insProvMap);
}

$insSummary=[];
foreach(array_keys($insProvMap) as $k) $insSummary[$k]=['opd'=>0,'hosp'=>0,'copay'=>0,'billed'=>0,'paid'=>0,'social_cases'=>0,'social_billed'=>0];

// OPD visits per insurance
$opdStmt=$pdo->prepare("
    SELECT pi.company_id, COUNT(*) as cnt
    FROM visits v
    JOIN patient_insurance pi ON pi.id=v.insurance_id
    WHERE DATE(v.visit_date) BETWEEN ? AND ?
      AND v.visit_type IN ('outpatient','opd','consultation','')
      AND v.payment_type='insurance'
    GROUP BY pi.company_id
");
$opdStmt->execute([$from,$to]);
foreach($opdStmt->fetchAll(PDO::FETCH_ASSOC) as $r){
    $k=$companyKeyMap[(int)$r['company_id']]??'other';
    $insSummary[$k]['opd']+=(int)$r['cnt'];
}
// Non-insured OPD
$niOpdStmt=$pdo->prepare("SELECT COUNT(*) FROM visits v WHERE DATE(v.visit_date) BETWEEN ? AND ? AND v.visit_type IN ('outpatient','opd','consultation','') AND v.payment_type!='insurance'");
$niOpdStmt->execute([$from,$to]); $insSummary['noninsured']['opd']=(int)$niOpdStmt->fetchColumn();

// Hospitalization per insurance
$hospStmt=$pdo->prepare("
    SELECT pi.company_id, COUNT(*) as cnt
    FROM bed_assignments ba
    JOIN visits v ON v.id=ba.visit_id
    JOIN patient_insurance pi ON pi.id=v.insurance_id
    WHERE DATE(ba.created_at) BETWEEN ? AND ? AND v.payment_type='insurance'
    GROUP BY pi.company_id
");
$hospStmt->execute([$from,$to]);
foreach($hospStmt->fetchAll(PDO::FETCH_ASSOC) as $r){
    $k=$companyKeyMap[(int)$r['company_id']]??'other';
    $insSummary[$k]['hosp']+=(int)$r['cnt'];
}
$niHospStmt=$pdo->prepare("SELECT COUNT(*) FROM bed_assignments ba JOIN visits v ON v.id=ba.visit_id WHERE DATE(ba.created_at) BETWEEN ? AND ? AND v.payment_type!='insurance'");
$niHospStmt->execute([$from,$to]); $insSummary['noninsured']['hosp']=(int)$niHospStmt->fetchColumn();

// Financials: billed, paid, copay per insurance company
$finStmt=$pdo->prepare("
    SELECT pi.company_id,
           COALESCE(SUM(i.total),0) as billed,
           COALESCE(SUM(i.paid_amount),0) as paid,
           COALESCE(SUM(i.balance),0) as balance
    FROM invoices i
    JOIN patient_insurance pi ON pi.id=i.insurance_id
    WHERE DATE(i.created_at) BETWEEN ? AND ? AND i.payment_type='insurance'
    GROUP BY pi.company_id
");
$finStmt->execute([$from,$to]);
foreach($finStmt->fetchAll(PDO::FETCH_ASSOC) as $r){
    $k=$companyKeyMap[(int)$r['company_id']]??'other';
    $insSummary[$k]['billed']+=(float)$r['billed'];
    $insSummary[$k]['paid']+=(float)$r['paid'];
    // copay = amount paid by patient (paid_amount - insurance_portion)
    // approximate: copay = balance already paid = paid_amount when status=paid
    $insSummary[$k]['copay']+=(float)$r['paid'];
}
// Non-insured financials
$niFinStmt=$pdo->prepare("SELECT COALESCE(SUM(total),0) billed, COALESCE(SUM(paid_amount),0) paid FROM invoices WHERE DATE(created_at) BETWEEN ? AND ? AND payment_type!='insurance'");
$niFinStmt->execute([$from,$to]); $niFin=$niFinStmt->fetch(PDO::FETCH_ASSOC);
$insSummary['noninsured']['billed']=(float)($niFin['billed']??0);
$insSummary['noninsured']['paid']=(float)($niFin['paid']??0);

$data['insurance_summary']=$insSummary;

ok($data);
} catch(PDOException $e){ err('DB error: '.$e->getMessage(),500); }
?>
