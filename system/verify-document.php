<?php
// verify-document.php
// Public official document verification portal (accessible when scanning document QR codes)
require_once __DIR__ . '/includes/database.php';

$ref = trim($_GET['ref'] ?? $_GET['reference'] ?? '');

$doc = null;
if ($ref !== '') {
    $stmt = $pdo->prepare("SELECT * FROM medical_documents WHERE reference_no = ?");
    $stmt->execute([$ref]);
    $doc = $stmt->fetch();
}

// Hospital settings
$hospSettings = [];
try {
    $st = $pdo->query("SELECT setting_key, setting_value FROM hospital_settings");
    while ($r = $st->fetch()) {
        $hospSettings[$r['setting_key']] = $r['setting_value'];
    }
} catch (Exception $e) {}

$hospName    = $hospSettings['hospital_name'] ?? 'Iramiro Clinic';
$hospLogo    = $hospSettings['doc_logo'] ?? $hospSettings['logo'] ?? '';
$hospAddress = $hospSettings['address'] ?? 'Kigali, Rwanda';
$hospPhone   = $hospSettings['phone'] ?? '';
$hospEmail   = $hospSettings['email'] ?? '';

$docTypeNames = [
    'care_sick_child'     => 'Medical Certificate for Care of a Sick Child',
    'leave_certificate'   => 'Medical Leave Certificate',
    'test_certificate'    => 'Laboratory Test Certificate',
    'dietary_restriction' => 'Dietary Restriction Certificate',
    'fitness_certificate' => 'Medical Certificate for Physical Fitness',
    'biological_certificate' => 'Medical Certificate (Biological Tests)',
    'hospitalization_certificate' => 'Certificate of Hospitalization',
    'referral_form' => 'Referral / Transfer Form',
    'echography_report' => "Rapport d'Examen Échographique",
    'followup_sheet' => 'Fiche de Suivi du Malade Hospitalisé',
    'dental_lab' => 'Dental Laboratory Work Authorization',
    'attestation_service' => 'Attestation de Service',
    'attestation_service_rendu' => 'Attestation de Service Rendu',
    'mission_order' => 'Mission Order'
];
$isHrDoc = $doc && in_array($doc['document_type'], ['attestation_service', 'attestation_service_rendu', 'mission_order'], true);

$docData = [];
if ($doc && !empty($doc['document_data'])) {
    $docData = is_array($doc['document_data']) ? $doc['document_data'] : (json_decode($doc['document_data'], true) ?: []);
}

$docTitle = $doc ? ($docTypeNames[$doc['document_type']] ?? ucwords(str_replace('_', ' ', $doc['document_type']))) : 'Document Verification';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Verification - <?= htmlspecialchars($hospName) ?></title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #059669;
      --primary-dark: #047857;
      --primary-light: #ecfdf5;
      --gray-50: #f8fafc;
      --gray-100: #f1f5f9;
      --gray-200: #e2e8f0;
      --gray-300: #cbd5e1;
      --gray-600: #475569;
      --gray-700: #334155;
      --gray-800: #1e293b;
      --gray-900: #0f172a;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f1f5f9;
      color: var(--gray-800);
      line-height: 1.5;
      padding: 24px 16px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .verify-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--gray-200);
      width: 100%;
      max-width: 680px;
      overflow: hidden;
    }
    .verify-header {
      background: white;
      padding: 20px 24px;
      border-bottom: 2px solid var(--primary);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
    }
    .verify-brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .verify-brand img {
      height: 42px;
      width: auto;
      object-fit: contain;
    }
    .verify-brand-title {
      font-size: 16px;
      font-weight: 700;
      color: var(--gray-900);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .verify-brand-sub {
      font-size: 12px;
      color: var(--gray-600);
    }
    .status-badge-verified {
      background: #ecfdf5;
      border: 1px solid #a7f3d0;
      color: #065f46;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .status-badge-verified i {
      font-size: 32px;
      color: #059669;
    }
    .status-badge-error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #991b1b;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .status-badge-error i {
      font-size: 32px;
      color: #dc2626;
    }
    .verify-body {
      padding: 24px;
    }
    .info-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      margin-top: 14px;
      margin-bottom: 20px;
    }
    .info-table th, .info-table td {
      padding: 10px 12px;
      border-bottom: 1px solid var(--gray-100);
      text-align: left;
    }
    .info-table th {
      color: var(--gray-600);
      font-weight: 600;
      width: 38%;
      background: var(--gray-50);
    }
    .info-table td {
      color: var(--gray-900);
      font-weight: 500;
    }
    .section-title {
      font-size: 13px;
      font-weight: 700;
      color: var(--primary-dark);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 20px;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .vitals-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
      gap: 10px;
      margin-bottom: 18px;
    }
    .vital-box {
      background: var(--gray-50);
      border: 1px solid var(--gray-200);
      border-radius: 6px;
      padding: 8px 10px;
    }
    .vital-box-label {
      font-size: 10px;
      color: var(--gray-600);
      font-weight: 600;
      text-transform: uppercase;
    }
    .vital-box-val {
      font-size: 14px;
      font-weight: 700;
      color: var(--gray-900);
      margin-top: 2px;
    }
    .clinical-box {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      padding: 12px 16px;
      font-size: 13px;
      line-height: 1.6;
      margin-bottom: 16px;
    }
    .verify-footer {
      background: var(--gray-50);
      border-top: 1px solid var(--gray-200);
      padding: 16px 24px;
      font-size: 12px;
      color: var(--gray-600);
      text-align: center;
    }
  </style>
</head>
<body>

<div class="verify-card">
  <!-- Header -->
  <div class="verify-header">
    <div class="verify-brand">
      <?php if ($hospLogo): ?>
        <img src="<?= htmlspecialchars($hospLogo) ?>" alt="Logo">
      <?php endif; ?>
      <div>
        <div class="verify-brand-title"><?= htmlspecialchars($hospName) ?></div>
        <div class="verify-brand-sub"><?= htmlspecialchars($hospAddress) ?></div>
      </div>
    </div>
    <div style="font-size:11px;color:var(--gray-600);text-align:right;">
      <div>Official Verification Portal</div>
      <div style="font-weight:600;color:var(--primary);"><?= date('d M Y') ?></div>
    </div>
  </div>

  <?php if ($doc): ?>
    <!-- Verified Badge -->
    <div class="status-badge-verified">
      <i class="fas fa-certificate"></i>
      <div>
        <h3 style="font-size:16px;font-weight:700;margin-bottom:2px;">OFFICIALLY VERIFIED MEDICAL DOCUMENT</h3>
        <p style="font-size:12px;opacity:0.9;">This document was authenticated against <?= htmlspecialchars($hospName) ?> electronic registry.</p>
      </div>
    </div>

    <div class="verify-body">
      <h4 style="font-size:15px;font-weight:700;color:var(--gray-900);margin-bottom:4px;"><?= htmlspecialchars($docTitle) ?></h4>
      <p style="font-size:12px;color:var(--gray-600);margin-bottom:16px;">Reference Number: <strong style="color:var(--primary);font-size:13px;"><?= htmlspecialchars($doc['reference_no']) ?></strong></p>

      <!-- Basic Info Table -->
      <table class="info-table">
        <tr>
          <th><?= $isHrDoc ? 'Issued To' : 'Patient Name' ?></th>
          <td><strong><?= htmlspecialchars($doc['patient_name']) ?></strong></td>
        </tr>
        <tr>
          <th><?= $isHrDoc ? 'Issued By' : 'Attending Physician' ?></th>
          <td><?= htmlspecialchars($doc['physician_name'] ? ($isHrDoc ? $doc['physician_name'] : 'Dr. ' . ltrim($doc['physician_name'], 'Dr. ')) : '—') ?></td>
        </tr>
        <tr>
          <th>Document Type</th>
          <td><?= htmlspecialchars($docTitle) ?></td>
        </tr>
        <tr>
          <th>Date Issued</th>
          <td><?= htmlspecialchars(!empty($docData['issued']) ? $docData['issued'] : date('Y-m-d', strtotime($doc['created_at']))) ?></td>
        </tr>
        <?php if (!empty($docData['visit_no'])): ?>
        <tr>
          <th>Associated Visit</th>
          <td><?= htmlspecialchars($docData['visit_no']) ?><?= !empty($docData['visit_date']) ? ' (' . htmlspecialchars($docData['visit_date']) . ')' : '' ?></td>
        </tr>
        <?php endif; ?>
      </table>

      <!-- Document Specific Details -->
      <?php if (!empty($docData['_summary']) && is_array($docData['_summary'])): ?>
        <!-- Documents built from docs/*.docx templates carry their own label/value summary -->
        <div class="section-title"><i class="fas fa-file-alt" style="color:var(--primary);"></i> Document Details</div>
        <table class="info-table">
          <?php foreach ($docData['_summary'] as $row): ?>
            <tr><th><?= htmlspecialchars((string)($row[0] ?? '')) ?></th><td><?= nl2br(htmlspecialchars((string)($row[1] ?? ''))) ?></td></tr>
          <?php endforeach; ?>
        </table>

      <?php elseif ($doc['document_type'] === 'fitness_certificate'): ?>
        <?php if (!empty($docData['fitness_status'])): ?>
          <div class="clinical-box" style="background:#ecfdf5;border-color:#a7f3d0;">
            <div style="font-size:11px;font-weight:700;color:#065f46;text-transform:uppercase;margin-bottom:2px;"><i class="fas fa-check-circle"></i> Medical Fitness Conclusion</div>
            <div style="font-size:14px;font-weight:700;color:#064e3b;text-transform:capitalize;"><?= htmlspecialchars($docData['fitness_status']) ?></div>
          </div>
        <?php endif; ?>

        <!-- Vitals -->
        <div class="section-title"><i class="fas fa-heartbeat" style="color:#ef4444;"></i> Vital Signs Recorded on Visit</div>
        <div class="vitals-grid">
          <div class="vital-box">
            <div class="vital-box-label">Blood Pressure</div>
            <div class="vital-box-val"><?= htmlspecialchars($docData['bp'] ?: '—') ?> <span style="font-size:10px;font-weight:normal;color:#64748b;">mmHg</span></div>
          </div>
          <div class="vital-box">
            <div class="vital-box-label">Heart Rate</div>
            <div class="vital-box-val"><?= htmlspecialchars($docData['hr'] ?: '—') ?> <span style="font-size:10px;font-weight:normal;color:#64748b;">bpm</span></div>
          </div>
          <div class="vital-box">
            <div class="vital-box-label">Resp. Rate</div>
            <div class="vital-box-val"><?= htmlspecialchars($docData['resp'] ?: '—') ?> <span style="font-size:10px;font-weight:normal;color:#64748b;">/min</span></div>
          </div>
          <div class="vital-box">
            <div class="vital-box-label">Temperature</div>
            <div class="vital-box-val"><?= htmlspecialchars($docData['temp'] ?: '—') ?> <span style="font-size:10px;font-weight:normal;color:#64748b;">°C</span></div>
          </div>
          <div class="vital-box">
            <div class="vital-box-label">SpO2</div>
            <div class="vital-box-val"><?= htmlspecialchars($docData['spo2'] ?: '—') ?> <span style="font-size:10px;font-weight:normal;color:#64748b;">%</span></div>
          </div>
          <div class="vital-box">
            <div class="vital-box-label">BMI</div>
            <div class="vital-box-val"><?= htmlspecialchars($docData['bmi'] ?: '—') ?> <span style="font-size:10px;font-weight:normal;color:#64748b;">kg/m²</span></div>
          </div>
          <div class="vital-box">
            <div class="vital-box-label">Weight</div>
            <div class="vital-box-val"><?= htmlspecialchars($docData['weight'] ?: '—') ?> <span style="font-size:10px;font-weight:normal;color:#64748b;">kg</span></div>
          </div>
          <div class="vital-box">
            <div class="vital-box-label">Height</div>
            <div class="vital-box-val"><?= htmlspecialchars($docData['height'] ?: '—') ?> <span style="font-size:10px;font-weight:normal;color:#64748b;">cm</span></div>
          </div>
        </div>

        <?php if (!empty($docData['medical_note'])): ?>
          <div class="section-title"><i class="fas fa-notes-medical" style="color:var(--primary);"></i> Medical Note / Condition</div>
          <div class="clinical-box"><?= nl2br(htmlspecialchars($docData['medical_note'])) ?></div>
        <?php endif; ?>

      <?php elseif ($doc['document_type'] === 'care_sick_child'): ?>
        <div class="section-title"><i class="fas fa-child" style="color:var(--primary);"></i> Child & Leave Details</div>
        <table class="info-table">
          <tr><th>Child Name</th><td><?= htmlspecialchars($docData['child_name'] ?? '—') ?> (Age: <?= htmlspecialchars($docData['age'] ?? '—') ?> yrs)</td></tr>
          <tr><th>Medical Condition</th><td><?= htmlspecialchars($docData['condition'] ?? '—') ?></td></tr>
          <tr><th>Leave Duration</th><td><?= htmlspecialchars($docData['days'] ?? '—') ?> (from <?= htmlspecialchars($docData['start'] ?? '—') ?> to <?= htmlspecialchars($docData['end'] ?? '—') ?>)</td></tr>
          <?php if (!empty($docData['resume'])): ?>
          <tr><th>Resume Work Date</th><td><?= htmlspecialchars($docData['resume']) ?></td></tr>
          <?php endif; ?>
        </table>

      <?php elseif ($doc['document_type'] === 'leave_certificate'): ?>
        <div class="section-title"><i class="fas fa-calendar-check" style="color:var(--primary);"></i> Medical Leave Duration</div>
        <table class="info-table">
          <tr><th>Approved Leave</th><td><strong><?= htmlspecialchars($docData['days'] ?? '—') ?> days</strong></td></tr>
          <tr><th>Leave Period</th><td>From <strong><?= htmlspecialchars($docData['start'] ?? '—') ?></strong> to <strong><?= htmlspecialchars($docData['end'] ?? '—') ?></strong></td></tr>
          <?php if (!empty($docData['clinical']) && is_array($docData['clinical'])): $lc = $docData['clinical']; $lv = $lc['vitals'] ?? []; ?>
          <tr><th>Patient</th><td>ID <?= htmlspecialchars((string)($lc['patient_no'] ?? '—')) ?> | Age <?= htmlspecialchars((string)($lc['age'] ?? '—')) ?> | Sex <?= htmlspecialchars((string)($lc['sex'] ?? '—')) ?></td></tr>
          <tr><th>Latest vitals</th><td>BP <?= htmlspecialchars((string)($lv['bp'] ?? '—')) ?> | HR <?= htmlspecialchars((string)($lv['hr'] ?? '—')) ?> | RR <?= htmlspecialchars((string)($lv['rr'] ?? '—')) ?> | Temp <?= htmlspecialchars((string)($lv['temp'] ?? '—')) ?> | SpO2 <?= htmlspecialchars((string)($lv['spo2'] ?? '—')) ?> | Weight <?= htmlspecialchars((string)($lv['weight'] ?? '—')) ?> | Height <?= htmlspecialchars((string)($lv['height'] ?? '—')) ?> | BMI <?= htmlspecialchars((string)($lv['bmi'] ?? '—')) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($docData['medical_notes'])): ?>
          <tr><th>Medical notes</th><td style="white-space:pre-wrap;"><?= htmlspecialchars((string)$docData['medical_notes']) ?></td></tr>
          <?php endif; ?>
        </table>

      <?php elseif ($doc['document_type'] === 'test_certificate'): ?>
        <div class="section-title"><i class="fas fa-flask" style="color:#7c3aed;"></i> Tests Performed & Results</div>
        <?php if (!empty($docData['tests']) && is_array($docData['tests'])): ?>
          <table class="info-table">
            <thead><tr style="background:#f1f5f9;"><th>Test Name</th><th>Result</th></tr></thead>
            <tbody>
              <?php foreach ($docData['tests'] as $t): ?>
                <tr>
                  <td><?= htmlspecialchars($t['test_name'] ?? $t['description'] ?? '—') ?></td>
                  <td><strong><?= htmlspecialchars($t['result'] ?? '—') ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="clinical-box"><?= htmlspecialchars($docData['test'] ?? '—') ?>: <strong><?= htmlspecialchars($docData['result'] ?? '—') ?></strong></div>
        <?php endif; ?>

      <?php elseif ($doc['document_type'] === 'dietary_restriction'): ?>
        <div class="section-title"><i class="fas fa-utensils" style="color:#d97706;"></i> Dietary Restrictions Advised</div>
        <div class="clinical-box">
          <?php if (!empty($docData['items'])): ?>
            <ul style="margin-left:20px;">
              <?php foreach (explode("\n", $docData['items']) as $it): if(trim($it)): ?>
                <li><?= htmlspecialchars(trim($it)) ?></li>
              <?php endif; endforeach; ?>
            </ul>
          <?php else: ?>
            <p>No specific dietary items specified.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div style="margin-top:20px;padding:12px 14px;background:#f8fafc;border:1px dashed var(--gray-300);border-radius:6px;font-size:11px;color:var(--gray-600);display:flex;align-items:center;gap:10px;">
        <i class="fas fa-shield-alt" style="font-size:20px;color:var(--primary);"></i>
        <div>
          <strong>Digital Security Verification:</strong> This document was officially generated by an authorized physician at <?= htmlspecialchars($hospName) ?>. Record ID #<?= (int)$doc['id'] ?>.
        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- Error / Not Found Badge -->
    <div class="status-badge-error">
      <i class="fas fa-times-circle"></i>
      <div>
        <h3 style="font-size:16px;font-weight:700;margin-bottom:2px;">DOCUMENT NOT FOUND</h3>
        <p style="font-size:12px;opacity:0.9;">The requested document could not be authenticated in our records.</p>
      </div>
    </div>

    <div class="verify-body" style="text-align:center;padding:40px 24px;">
      <p style="font-size:14px;color:var(--gray-700);margin-bottom:12px;">
        No medical document was found matching reference: <strong style="color:#dc2626;"><?= htmlspecialchars($ref ?: '[Empty Reference]') ?></strong>.
      </p>
      <p style="font-size:12px;color:var(--gray-600);max-width:440px;margin:0 auto 20px auto;">
        Please ensure you scanned a valid QR code on an official hospital document. If you believe this is an error, please contact <?= htmlspecialchars($hospName) ?> administration.
      </p>
      <?php if ($hospPhone || $hospEmail): ?>
        <div style="font-size:12px;color:var(--gray-600);">
          <?= $hospPhone ? 'Tel: ' . htmlspecialchars($hospPhone) : '' ?> <?= ($hospPhone && $hospEmail) ? ' • ' : '' ?> <?= $hospEmail ? 'Email: ' . htmlspecialchars($hospEmail) : '' ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="verify-footer">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($hospName) ?>. All rights reserved. Hospital Medical Documents Verification System.
  </div>
</div>

</body>
</html>
