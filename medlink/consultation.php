<?php
// consultation.php - Step-based consultation workflow
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/consult_schema.php';
consult_ensure_schema($pdo); // self-heals: diagnosis_recurrence_flags, dental X-ray services under Imaging

// Check if user is an administrator (can see prices and restricted sections)
$isAdminUser = false;
if (isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['user_data']['is_admin'])) {
        $isAdminUser = true;
    } elseif (isset($_SESSION['user_data']['role']) && in_array(strtolower($_SESSION['user_data']['role']), ['admin', 'administrator'])) {
        $isAdminUser = true;
    } elseif (isset($_SESSION['user_data']['role_slugs']) && (in_array('admin', $_SESSION['user_data']['role_slugs']) || in_array('administrator', $_SESSION['user_data']['role_slugs']))) {
        $isAdminUser = true;
    } elseif (isset($_SESSION['user_role']) && in_array(strtolower($_SESSION['user_role']), ['admin', 'administrator'])) {
        $isAdminUser = true;
    } elseif (function_exists('userHasRole') && (userHasRole('admin') || userHasRole('administrator'))) {
        $isAdminUser = true;
    } else {
        require_once __DIR__ . '/includes/database.php';
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND (r.slug IN ('admin', 'administrator') OR LOWER(r.name) IN ('admin', 'administrator'))
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $isAdminUser = (int)$stmt->fetchColumn() > 0;
    }
}

$visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$visitId) {
    echo '<div style="padding:60px;text-align:center;color:var(--gray-500);"><h2>No visit selected</h2><p>Please select a patient from the department queue.</p><a href="department.php" style="color:var(--primary-color);">← Back to Department</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Doctor from another department/doctor's patient: no consultation form, read-only summary modal instead
require_once __DIR__ . '/includes/auth.php';
if (!canAccessVisit($pdo, $visitId)) {
    $h = fn($v) => htmlspecialchars((string)($v ?? '') !== '' ? (string)$v : '—');
    $s = $pdo->prepare("SELECT p.*, v.id AS vid FROM visits v JOIN patients p ON p.id = v.patient_id WHERE v.id = ?");
    $s->execute([$visitId]);
    $pt = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    $s = $pdo->prepare("SELECT id, visit_no, visit_date, visit_type, status, chief_complaint, notes FROM visits WHERE patient_id = ? ORDER BY visit_date DESC, id DESC");
    $s->execute([(int)($pt['id'] ?? 0)]);
    $visits = $s->fetchAll(PDO::FETCH_ASSOC);
    $qd = $pdo->prepare("SELECT d.name AS dept, CONCAT(u.first_name,' ',u.last_name) AS doc, h.status
        FROM visit_department_history h LEFT JOIN departments d ON d.id = h.department_id LEFT JOIN users u ON u.id = h.assigned_to
        WHERE h.visit_id = ? AND h.status <> 'cancelled' ORDER BY h.id DESC");
    $qc = $pdo->prepare("SELECT c.*, d.name AS dept FROM consultations c LEFT JOIN departments d ON d.id = c.department_id WHERE c.visit_id = ? ORDER BY c.id DESC");
    $row = fn($l, $v) => '<div style="display:flex;gap:8px;padding:5px 0;border-bottom:1px solid #f1f5f9;font-size:13px;"><span style="width:150px;flex:none;color:#64748b;">' . $l . '</span><span style="flex:1;white-space:pre-wrap;">' . $h($v) . '</span></div>';
    $sec = fn($t) => '<h4 style="margin:18px 0 6px;font-size:14px;font-weight:700;color:#1e293b;">' . $t . '</h4>';
    ?>
<style>.xv-card{flex:none;min-width:160px;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;cursor:pointer;text-align:left;font:inherit;font-size:12px;line-height:1.5;color:#475569;white-space:nowrap;transition:border-color .15s,background .15s}.xv-card:hover{border-color:#93c5fd}.xv-card b{display:block;color:#1e293b;font-size:13px;margin-bottom:2px}.xv-card i{font-style:normal;display:inline-block;margin-top:4px;padding:1px 8px;border-radius:10px;background:#f1f5f9;font-size:11px;font-weight:600;text-transform:capitalize}.xv-card.active{border-color:#1769e0;background:#eff6ff;box-shadow:0 0 0 1px #1769e0 inset}</style>
<div style="position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:12px;width:100%;max-width:760px;max-height:90vh;display:flex;flex-direction:column;">
    <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <div><div style="font-size:17px;font-weight:700;"><?= $h(trim(($pt['first_name'] ?? '') . ' ' . ($pt['last_name'] ?? ''))) ?></div>
      <div style="font-size:12px;color:#b91c1c;margin-top:2px;"><i class="fas fa-lock"></i> Read-only: this patient is assigned to another doctor/department.</div></div>
      <a href="department.php" style="padding:6px 14px;border:1px solid #e2e8f0;border-radius:8px;color:#334155;text-decoration:none;font-size:13px;">Close</a>
    </div>
    <div style="flex:none;padding:14px 22px 10px;display:flex;gap:10px;overflow-x:auto;border-bottom:1px solid #e2e8f0;" id="xv-cards">
      <?php foreach ($visits as $v): ?>
      <button type="button" class="xv-card<?= (int)$v['id'] === $visitId ? ' active' : '' ?>" data-v="<?= (int)$v['id'] ?>"><b><?= $h($v['visit_no']) ?></b><?= $h($v['visit_date']) ?> · <?= $h($v['visit_type']) ?><br><i><?= $h($v['status']) ?></i></button>
      <?php endforeach; ?>
    </div>
    <div style="padding:6px 22px 20px;overflow:auto;">
      <?= $sec('Patient') ?>
      <?= $row('Patient no', $pt['patient_no'] ?? '') . $row('Gender', $pt['gender'] ?? '') . $row('Date of birth', $pt['date_of_birth'] ?? '') . $row('Phone', $pt['phone'] ?? '') . $row('Blood group', $pt['blood_group'] ?? '') . $row('Address', $pt['address'] ?? '') ?>
      <?= $row('Allergies', $pt['allergies'] ?? '') . $row('Chronic diseases', $pt['chronic_diseases'] ?? '') . $row('Current medications', $pt['current_medications'] ?? '') . $row('Medical alerts', $pt['medical_alerts'] ?? '') ?>
      <?php foreach ($visits as $v):
        $qd->execute([$v['id']]); $qc->execute([$v['id']]); ?>
      <div class="xv-pane" data-v="<?= (int)$v['id'] ?>" style="<?= (int)$v['id'] === $visitId ? '' : 'display:none' ?>">
        <?= $sec('Visit') ?>
        <?= $row('Visit no', $v['visit_no']) . $row('Date', $v['visit_date']) . $row('Type', $v['visit_type']) . $row('Status', $v['status']) . $row('Chief complaint', $v['chief_complaint']) . $row('Notes', $v['notes']) ?>
        <?php foreach ($qd->fetchAll(PDO::FETCH_ASSOC) as $d) echo $row('Dept: ' . $h($d['dept']), trim(($d['doc'] ?? '') . ' (' . $d['status'] . ')')); ?>
        <?php foreach ($qc->fetchAll(PDO::FETCH_ASSOC) as $c): ?>
          <?= $sec('Consultation — ' . $h($c['dept']) . ' · ' . $h($c['doctor_name']) . ' · ' . $h($c['created_at'])) ?>
          <?= $row('Vitals', 'BP ' . ($c['bp_systolic'] ?? '—') . '/' . ($c['bp_diastolic'] ?? '—') . ', Pulse ' . ($c['pulse'] ?? '—') . ', Temp ' . ($c['temperature'] ?? '—') . ', SpO₂ ' . ($c['oxygen_sat'] ?? '—') . ', Wt ' . ($c['weight'] ?? '—') . ', Ht ' . ($c['height'] ?? '—')) ?>
          <?= $row('Complaint', $c['chief_complaint']) . $row('History', $c['history']) . $row('Examination', $c['examination']) . $row('Diagnosis', trim(($c['diagnosis'] ?? '') . ' ' . ($c['icd_code'] ?? ''))) . $row('Treatment plan', $c['treatment_plan']) . $row('Outcome', $c['outcome']) . $row('Notes', $c['notes']) ?>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.xv-card').forEach(b => b.onclick = () => {
  document.querySelectorAll('.xv-card').forEach(x => x.classList.toggle('active', x === b));
  document.querySelectorAll('.xv-pane').forEach(p => p.style.display = p.dataset.v === b.dataset.v ? '' : 'none');
});
</script>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<!-- Back Button -->
<div style="margin-bottom:16px;">
  <a href="department.php" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;text-decoration:none;font-size:13px;">
    <i class="fas fa-arrow-left"></i> Back to Queue
  </a>
</div>

<!-- Patient Info Header -->
<div style="background:white;border-radius:var(--radius);padding:18px 24px;box-shadow:var(--shadow);margin-bottom:20px;">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
    <div style="width:50px;height:50px;background:var(--primary-color);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:20px;font-weight:700;" id="c-avatar">?</div>
    <div style="flex:1;min-width:200px;">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;"><h2 id="c-name" style="font-size:18px;font-weight:700;color:var(--gray-900);">Loading...</h2><span id="c-allergy-pill" style="display:none;padding:3px 10px;border-radius:20px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;font-size:12px;font-weight:700;"></span></div>
      <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:4px;font-size:12px;color:var(--gray-500);">
        <span><i class="fas fa-id-card"></i> <span id="c-uhid">—</span></span>
        <span><i class="fas fa-venus-mars"></i> <span id="c-gender">—</span></span>
        <span><i class="fas fa-birthday-cake"></i> <span id="c-age">—</span></span>
        <span><i class="fas fa-phone"></i> <span id="c-phone">—</span></span>
        <span><i class="fas fa-shield-alt" style="color:var(--primary-color);"></i> <span id="c-ins-inline">—</span></span>
      </div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:11px;color:var(--gray-500);">Visit</div>
      <div id="c-visit-no" style="font-size:15px;font-weight:700;color:var(--primary-color);font-family:monospace;">—</div>
      <div id="c-insurance-badge" style="display:none;margin-top:6px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;"></div>
    </div>
  </div>
</div>

<style>
.ct-tabs{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1px solid #e7ebf1;margin-bottom:20px}
.ct-tabs a{padding:10px 14px;color:#6b7890;text-decoration:none;font-size:13px;font-weight:700;border-bottom:2px solid transparent;cursor:pointer}
.ct-tabs a.active{color:#1769e0;border-color:#1769e0}
.ct-panel{background:white;border-radius:var(--radius);padding:18px 22px;box-shadow:var(--shadow);margin-bottom:20px}
.ct-panel table{width:100%;border-collapse:collapse;font-size:13px}
.ct-panel th{text-align:left;padding:8px;background:var(--gray-50);border-bottom:2px solid var(--gray-200);font-size:12px}
.ct-panel td{padding:8px;border-bottom:1px solid var(--gray-100);vertical-align:top}
.ct-panel h4{font-size:14px;font-weight:700;margin:0 0 10px}
.ct-empty{padding:24px;text-align:center;color:var(--gray-400)}
.ct-sub{display:flex;gap:6px;margin-bottom:14px}
.ct-sub a{padding:6px 16px;border-radius:16px;border:1px solid var(--gray-200);color:var(--gray-600);font-size:12px;font-weight:700;cursor:pointer}
.ct-sub a.active{background:var(--primary-color);border-color:var(--primary-color);color:#fff}
.ct-sub .ct-badge{background:#fff;color:#dc2626}
.ct-sub a:not(.active) .ct-badge{background:#dc2626;color:#fff}
#sec-final-dx[inert],#sec-final-rx[inert]{opacity:.6}
.ct-badge{display:inline-block;min-width:18px;padding:1px 6px;margin-left:6px;border-radius:9px;background:#dc2626;color:#fff;font-size:11px;font-weight:700;line-height:16px;text-align:center}
</style>
<nav class="ct-tabs" id="ct-tabs">
  <a class="active" data-tab="consult">Consultation</a>
  <a data-tab="results">Results<span class="ct-badge" id="ctb-results" style="display:none"></span></a>
  <a data-tab="meds">Medication<span class="ct-badge" id="ctb-meds" style="display:none"></span></a>
  <a data-tab="invoicing">Invoicing<span class="ct-badge" id="ctb-invoicing" style="display:none"></span></a>
  <a data-tab="visits">Previous Visits</a>
</nav>
<div id="ct-consult">
<style>
.content-area{overflow:visible !important;} /* lets the sticky Billing List stick to the window scroll */
.c-layout{display:flex;gap:0;align-items:stretch;}
.c-main{flex:0 0 80%;max-width:80%;min-width:0;box-sizing:border-box;padding-right:20px;border-right:1px solid #94a3b8;}
.c-side{flex:1 1 0;min-width:0;box-sizing:border-box;padding-left:20px;}
.c-side-in{position:sticky;top:84px;max-height:calc(100vh - 100px);overflow-y:auto;}
@media (max-width:1024px){.c-layout{flex-direction:column;}.c-main{flex:1 1 auto;max-width:100%;width:100%;padding-right:0;border-right:0;}.c-side{width:100%;padding-left:0;}.c-side-in{position:static;max-height:none;}}
</style>
<div class="c-layout"><div class="c-main">

<!-- Vital Signs Row -->
<div style="background:white;border-radius:var(--radius);padding:16px 24px;box-shadow:var(--shadow);margin-bottom:20px;">
  <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:12px;"><i class="fas fa-heartbeat" style="color:var(--danger-color);"></i> Vital Signs <button onclick="cEditAllVitals()" style="margin-left:8px;padding:3px 10px;background:#2563eb;color:white;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;"><i class="fas fa-pen"></i> Edit all vitals</button></h4>
  <div id="c-vitals" style="display:flex;gap:12px;flex-wrap:wrap;">
    <span style="color:var(--gray-400);font-size:13px;">Loading...</span>
  </div>
  <div id="c-vrec" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--gray-200);">
    <div style="font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">Vital Signs Entries <span id="sv-c-vrec" style="font-size:11px;font-weight:500;margin-left:6px;"></span></div>
    <div id="c-vrec-form" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:10px;">
      <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">BP</label><input type="text" id="c-v-bp" placeholder="120/80" style="width:80px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;display:block;"></div>
      <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Temp</label><input type="text" id="c-v-temp" placeholder="36.5" style="width:70px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;display:block;"></div>
      <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Pulse</label><input type="text" id="c-v-pulse" placeholder="72" style="width:70px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;display:block;"></div>
      <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">SpO2</label><input type="text" id="c-v-spo2" placeholder="98%" style="width:70px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;display:block;"></div>
      <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">RR</label><input type="text" id="c-v-rr" placeholder="16" style="width:70px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;display:block;"></div>
      <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Weight</label><input type="text" id="c-v-wt" placeholder="70kg" style="width:80px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;display:block;"></div>
      <button type="button" id="btn-save-vrec" onclick="saveVitalRecord()" style="padding:7px 14px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save</button>
    </div>
    <div id="c-vrec-list"></div>
  </div>
</div>

<!-- Medical Profile -->
<div style="background:white;border-radius:var(--radius);padding:16px 24px;box-shadow:var(--shadow);margin-bottom:20px;">
  <div style="margin:0;padding:16px;background:#fafafa;border:1px solid var(--gray-200);border-radius:10px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
      <i class="fas fa-heartbeat" style="color:#dc2626;"></i>
      <span style="font-size:14px;font-weight:600;color:var(--gray-800);">Medical Profile <span style="font-size:11px;font-weight:400;color:var(--gray-500);">(saved to patient record)</span></span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:5px;"><i class="fas fa-tint" style="color:#dc2626;"></i> Blood Group</label>
        <select id="f-blood-group" style="width:100%;padding:10px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
          <option value="">Unknown</option>
          <option value="A+">A+</option>
          <option value="A-">A−</option>
          <option value="B+">B+</option>
          <option value="B-">B−</option>
          <option value="AB+">AB+</option>
          <option value="AB-">AB−</option>
          <option value="O+">O+</option>
          <option value="O-">O−</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:5px;"><i class="fas fa-exclamation-triangle" style="color:#d97706;"></i> Allergies</label>
        <input type="text" id="f-allergies" placeholder="e.g. Penicillin, Sulfa drugs, Pollen..." style="width:100%;padding:10px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
      </div>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:5px;"><i class="fas fa-notes-medical" style="color:#7c3aed;"></i> Chronic Diseases</label>
      <input type="text" id="f-chronic-diseases" placeholder="e.g. Diabetes, Hypertension, Asthma..." style="width:100%;padding:10px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
    </div>
    <button type="button" onclick="saveMedicalProfile()" style="margin-top:10px;padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save</button>
    <div id="mp-save-status" style="display:none;margin-top:8px;font-size:12px;color:#059669;"></div>
  </div>
</div>

<!-- Audit Trail -->
<div id="c-audit-trail" style="display:none;background:white;border-radius:var(--radius);padding:14px 20px;box-shadow:var(--shadow);margin-bottom:20px;">
  <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:10px;">
    <i class="fas fa-history" style="color:#7c3aed;"></i> Consultation Audit Trail
  </h4>
  <div id="c-audit-trail-rows"></div>
</div>

<!-- ============================================
     STEP 1: CONSULTATION NOTES
============================================ -->
<div id="step-1" style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <div id="c-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;"></div>

  <!-- Chief Complaints -->
  <div style="margin-bottom:18px;">
    <label style="display:block;font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:6px;">1. Chief Complaints <span id="sv-f-complaints" style="font-size:11px;font-weight:500;color:#059669;margin-left:6px;"></span></label>
    <div style="display:flex;gap:8px;"><textarea id="f-complaints" rows="3" placeholder="Enter the patient's main complaints..." style="flex:1;min-width:0;padding:12px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
  </div>

  <!-- Past medical history -->
  <div style="margin-bottom:18px;">
    <label style="display:block;font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:6px;">2. Past medical history <span id="sv-f-past-history" style="font-size:11px;font-weight:500;color:#059669;margin-left:6px;"></span></label>
    <div style="display:flex;gap:8px;"><textarea id="f-past-history" rows="3" placeholder="Previous medical history, surgeries, allergies..." style="flex:1;min-width:0;padding:12px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
  </div>

  <!-- Physical Examination -->
  <div style="margin-bottom:18px;">
    <label style="display:block;font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:6px;">3. Physical Examination <span id="sv-f-physical-exam" style="font-size:11px;font-weight:500;color:#059669;margin-left:6px;"></span></label>
    <div style="display:flex;gap:8px;"><textarea id="f-physical-exam" rows="3" placeholder="Findings from physical examination..." style="flex:1;min-width:0;padding:12px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
  </div>

  <!-- Differential Diagnosis -->
  <div style="margin-bottom:18px;">
    <label style="display:block;font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:6px;">4. Differential Diagnosis <span id="sv-f-diff-diagnosis" style="font-size:11px;font-weight:500;color:#059669;margin-left:6px;"></span></label>
    <div style="display:flex;gap:8px;"><textarea id="f-diff-diagnosis" rows="3" placeholder="Possible diagnoses..." style="flex:1;min-width:0;padding:12px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
  </div>

</div>

<!-- ============================================
     STEP 2: ELECTRONIC ORDERS
============================================ -->
<div id="step-2" style="margin-bottom:8px;">

  <!-- data holder only (feeds Billing List + duplicate guard); visible table removed -->

<div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <label style="display:block;font-size:15px;font-weight:700;color:var(--gray-800);margin:0 0 8px;">5. Lab Test</label>
  <!-- Lab Test Panel -->
  <div id="ord-panel-lab" style="margin-bottom:4px;">
    <div id="ord-box-lab" onclick="ordToggle('lab')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;">
      <span style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;"><span id="ord-chips-lab" style="display:contents;"></span><span id="ord-ph-lab" style="color:var(--gray-400);font-size:14px;">Select lab test...</span></span>
      <i class="fas fa-caret-down" style="color:var(--gray-500);"></i>
    </div>
    <div id="ord-dropdown-lab" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
      <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="ord-search-lab" placeholder="Search lab test..." autocomplete="off" oninput="ordSearchDebounce('lab')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;"></div>
      <div id="ord-list-lab" style="max-height:240px;overflow-y:auto;"></div>
    </div>
    <div id="ord-selected-lab" style="margin-top:12px;"></div>
    <div>
      <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Clinical Notes</label>
      <textarea id="ord-notes-lab" rows="2" placeholder="Reason for investigation..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
    </div>
    <button onclick="submitCombinedOrder('lab')" id="btn-send-order-lab" style="margin-top:12px;padding:10px 20px;background:#2563eb;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-paper-plane"></i> Add to Billing List</button>

    <!-- Insurance Toggle for Lab -->
    <div id="lab-ins-toggle" style="display:none;margin-top:12px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:var(--gray-700);cursor:pointer;">
        <input type="checkbox" id="lab-apply-insurance" checked> Apply Insurance Coverage
      </label>
      <div id="lab-ins-info" style="margin-top:6px;font-size:12px;color:#1e40af;"></div>
    </div>
  </div>


</div>
<div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <label style="display:block;font-size:15px;font-weight:700;color:var(--gray-800);margin:0 0 8px;">6. Medication</label>
  <div id="ord-panel-med" style="margin-bottom:4px;">
    <div id="ord-box-med" onclick="ordToggle('med')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;">
      <span style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;"><span id="ord-chips-med" style="display:contents;"></span><span id="ord-ph-med" style="color:var(--gray-400);font-size:14px;">Select medication...</span></span>
      <i class="fas fa-caret-down" style="color:var(--gray-500);"></i>
    </div>
    <div id="ord-dropdown-med" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
      <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="ord-search-med" placeholder="Search medication..." autocomplete="off" oninput="ordSearchDebounce('med')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;"></div>
      <div id="ord-list-med" style="max-height:240px;overflow-y:auto;"></div>
    </div>
    <div id="ord-selected-med" style="margin-top:12px;"></div>
    <div>
      <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Notes</label>
      <textarea id="ord-notes-med" rows="2" placeholder="Special instructions, indications..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
    </div>
    <button onclick="submitCombinedOrder('med')" id="btn-send-order-med" style="margin-top:12px;padding:10px 20px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-prescription"></i> Add to Billing List</button>

    <!-- Insurance Toggle for Pharmacy -->
    <div id="rx-ins-toggle" style="display:none;margin-top:12px;padding:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:var(--gray-700);cursor:pointer;">
        <input type="checkbox" id="rx-apply-insurance" checked> Apply Insurance Coverage
      </label>
      <div id="rx-ins-info" style="margin-top:6px;font-size:12px;color:#1e40af;"></div>
    </div>
  </div>
</div>

<div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <label style="display:block;font-size:15px;font-weight:700;color:var(--gray-800);margin:0 0 8px;">7. Procedure</label>
  <div id="ord-panel-proc" style="margin-bottom:4px;">
    <div id="ord-box-proc" onclick="ordToggle('proc')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;">
      <span style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;"><span id="ord-chips-proc" style="display:contents;"></span><span id="ord-ph-proc" style="color:var(--gray-400);font-size:14px;">Select procedure...</span></span>
      <i class="fas fa-caret-down" style="color:var(--gray-500);"></i>
    </div>
    <div id="ord-dropdown-proc" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
      <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="ord-search-proc" placeholder="Search procedure..." autocomplete="off" oninput="ordSearchDebounce('proc')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;"></div>
      <div id="ord-list-proc" style="max-height:240px;overflow-y:auto;"></div>
    </div>
    <div id="ord-selected-proc" style="margin-top:12px;"></div>
    <div>
      <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Notes</label>
      <textarea id="ord-notes-proc" rows="2" placeholder="Special instructions, indications..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
    </div>
    <button onclick="submitCombinedOrder('proc')" id="btn-send-order-proc" style="margin-top:12px;padding:10px 20px;background:#9d174d;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-syringe"></i> Add to Billing List</button>
  </div>
</div>

<div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <label style="display:block;font-size:15px;font-weight:700;color:var(--gray-800);margin:0 0 8px;">8. Consumables</label>
  <div id="ord-panel-cons" style="margin-bottom:4px;">
    <div id="ord-box-cons" onclick="ordToggle('cons')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;">
      <span style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;"><span id="ord-chips-cons" style="display:contents;"></span><span id="ord-ph-cons" style="color:var(--gray-400);font-size:14px;">Select consumable...</span></span>
      <i class="fas fa-caret-down" style="color:var(--gray-500);"></i>
    </div>
    <div id="ord-dropdown-cons" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
      <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="ord-search-cons" placeholder="Search consumable (gloves, syringe, gauze...)" autocomplete="off" oninput="ordSearchDebounce('cons')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;"></div>
      <div id="ord-list-cons" style="max-height:240px;overflow-y:auto;"></div>
    </div>
    <div id="ord-selected-cons" style="margin-top:12px;"></div>
    <div>
      <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Notes</label>
      <textarea id="ord-notes-cons" rows="2" placeholder="Where / why used..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
    </div>
    <button onclick="submitCombinedOrder('cons')" id="btn-send-order-cons" style="margin-top:12px;padding:10px 20px;background:#b45309;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-box-open"></i> Add to Billing List</button>
  </div>
</div>

<div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <label style="display:block;font-size:15px;font-weight:700;color:var(--gray-800);margin:0 0 8px;">9. Imaging</label>
  <div id="ord-panel-img" style="margin-bottom:4px;">
    <div id="ord-box-img" onclick="ordToggle('img')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;">
      <span style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;"><span id="ord-chips-img" style="display:contents;"></span><span id="ord-ph-img" style="color:var(--gray-400);font-size:14px;">Select imaging...</span></span>
      <i class="fas fa-caret-down" style="color:var(--gray-500);"></i>
    </div>
    <div id="ord-dropdown-img" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
      <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="ord-search-img" placeholder="Search imaging..." autocomplete="off" oninput="ordSearchDebounce('img')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;"></div>
      <div id="ord-list-img" style="max-height:240px;overflow-y:auto;"></div>
    </div>
    <div id="ord-selected-img" style="margin-top:12px;"></div>
    <div>
      <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Notes</label>
      <textarea id="ord-notes-img" rows="2" placeholder="Special instructions, indications..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
    </div>
    <button onclick="submitCombinedOrder('img')" id="btn-send-order-img" style="margin-top:12px;padding:10px 20px;background:#ea580c;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-x-ray"></i> Add to Billing List</button>
  </div>

</div>
<!-- 10. Dental: shown only on Dental Clinic visits (loadPatient). Same billing path as Procedure. -->
<div id="sec-dental" style="display:none;background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <label style="display:block;font-size:15px;font-weight:700;color:var(--gray-800);margin:0 0 8px;">10. Dental</label>
  <div id="ord-panel-dent" style="margin-bottom:4px;">
    <div id="ord-box-dent" onclick="ordToggle('dent')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;">
      <span style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;"><span id="ord-chips-dent" style="display:contents;"></span><span id="ord-ph-dent" style="color:var(--gray-400);font-size:14px;">Select dental service...</span></span>
      <i class="fas fa-caret-down" style="color:var(--gray-500);"></i>
    </div>
    <div id="ord-dropdown-dent" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
      <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="ord-search-dent" placeholder="Search dental service..." autocomplete="off" oninput="ordSearchDebounce('dent')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;"></div>
      <div id="ord-list-dent" style="max-height:240px;overflow-y:auto;"></div>
    </div>
    <div id="ord-selected-dent" style="margin-top:12px;"></div>
    <div>
      <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Notes</label>
      <textarea id="ord-notes-dent" rows="2" placeholder="Clinical notes for the billing list..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
    </div>
    <button onclick="submitCombinedOrder('dent')" id="btn-send-order-dent" style="margin-top:12px;padding:10px 20px;background:#0d9488;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-tooth"></i> Add to Billing List</button>
  </div>

  <!-- Tooth chart (simple table) -->
  <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);">
    <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin:0 0 10px;"><i class="fas fa-teeth" style="color:#0d9488;"></i> Tooth chart <span style="font-weight:400;font-size:12px;color:var(--gray-500);">(this patient, all visits)</span></h4>
    <div style="display:grid;grid-template-columns:90px 90px 150px 1fr auto;gap:8px;align-items:end;">
      <div><label style="font-size:12px;color:var(--gray-600);">Tooth</label><select id="dent-tooth" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></select></div>
      <div><label style="font-size:12px;color:var(--gray-600);">Surface</label><select id="dent-surface" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">—</option><option>M</option><option>O</option><option>D</option><option>B</option><option>L</option><option>MO</option><option>DO</option><option>MOD</option><option>FULL</option></select></div>
      <div><label style="font-size:12px;color:var(--gray-600);">Condition</label><select id="dent-cond" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></select></div>
      <div><label style="font-size:12px;color:var(--gray-600);">Notes</label><input id="dent-tnotes" type="text" maxlength="255" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <button onclick="dentSaveTooth()" style="padding:9px 16px;background:#0d9488;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Save</button>
    </div>
    <div id="dent-chart" style="margin-top:12px;"></div>
  </div>

  <!-- Treatment plans -->
  <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);">
    <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin:0 0 10px;"><i class="fas fa-clipboard-list" style="color:#0d9488;"></i> Treatment plans <button onclick="dentLoad()" title="Reload (e.g. after the cashier took payment)" style="margin-left:8px;padding:2px 9px;background:white;color:#0d9488;border:1px solid #0d9488;border-radius:5px;font-size:11px;cursor:pointer;"><i class="fas fa-sync"></i> Refresh</button></h4>
    <div id="dent-plans"></div>
    <details style="margin-top:10px;"><summary style="cursor:pointer;font-weight:600;font-size:13px;color:#0d9488;">+ New plan</summary>
      <div style="margin-top:10px;">
        <input id="dent-plan-title" type="text" maxlength="200" placeholder="Plan title / diagnosis" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;margin-bottom:8px;">
        <div style="display:grid;grid-template-columns:1fr 110px 70px auto;gap:8px;align-items:end;">
          <select id="dent-plan-svc" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></select>
          <input id="dent-plan-tooth" type="text" placeholder="Tooth / arch" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <input id="dent-plan-stage" type="number" min="1" value="1" title="Stage (visit order)" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <button onclick="dentDraftAdd()" style="padding:9px 14px;background:white;color:#0d9488;border:1px solid #0d9488;border-radius:6px;font-weight:600;cursor:pointer;">Add</button>
        </div>
        <div id="dent-draft" style="margin-top:8px;"></div>
        <button id="dent-plan-save" onclick="dentPlanSave()" style="margin-top:8px;padding:9px 18px;background:#0d9488;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Save plan</button>
      </div>
    </details>
  </div>
</div>
</div>

<!-- ============================================
     STEP 3: INVESTIGATION RESULTS, FINAL DIAGNOSIS & PRESCRIPTION
============================================ -->
<div id="step-3" style="margin-bottom:8px;">

  <!-- Who added this consultation -->
  <div id="c-consultation-author" style="display:none;margin-bottom:14px;padding:8px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:12px;color:#1e40af;">
    <i class="fas fa-user-md" style="margin-right:6px;"></i><span id="c-consultation-author-text"></span>
  </div>

  <div id="ct-lock-banner" style="display:none;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:8px;padding:10px 16px;margin-bottom:12px;font-size:13px;font-weight:600;"><i class="fas fa-lock"></i> <span id="ct-lock-text"></span></div>
  <!-- Final Diagnosis -->
  <div id="sec-final-dx" style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
    <label style="display:block;font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:6px;">11. Final Diagnosis <span style="color:#dc2626;">*</span> <span style="font-size:12px;color:var(--gray-400);font-weight:400;">(Required to complete consultation)</span></label>
    <div id="diag-box" onclick="diagToggle()" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;">
      <span style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1;min-width:0;"><span id="diag-chips" style="display:contents;"></span><span id="diag-ph" style="color:var(--gray-400);font-size:14px;">Select diagnosis...</span></span>
      <i class="fas fa-caret-down" style="color:var(--gray-500);"></i>
    </div>
    <div id="diagnosis-dropdown" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
      <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="f-final-diagnosis" placeholder="Search diagnosis, or type a new one and press Enter..." autocomplete="off" oninput="searchDiagnoses()" onkeydown="if(event.key==='Enter'){event.preventDefault();addNewDiagnosis();}" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;outline:none;"></div>
      <div id="diag-list" style="max-height:240px;overflow-y:auto;"></div>
    </div>
    <div id="f-recurrence-alerts"></div>
  </div>

  <!-- Final Prescription -->
  <div id="sec-final-rx" style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
    <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;"><i class="fas fa-prescription" style="color:#16a34a;"></i> 12. Medication <span style="font-size:12px;font-weight:400;color:var(--gray-500);">(prescribed at discharge, take-home)</span></h4>
    <div id="final-rx-items">
      <div class="final-rx-row" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 70px 100px auto;gap:10px;margin-bottom:4px;align-items:end;">
        <div>
          <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Medication</label>
          <input type="text" class="final-rx-med" list="final-rx-med-list" placeholder="Type medication name..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Dosage</label>
          <input type="text" class="final-rx-dose" placeholder="e.g. 500mg" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Frequency</label>
          <input type="text" class="final-rx-freq" placeholder="e.g. 3x/day" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Duration</label>
          <input type="text" class="final-rx-dur" placeholder="e.g. 7 days" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Qty</label>
          <input type="number" min="1" class="final-rx-qty" placeholder="Qty" style="width:100%;padding:9px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Route</label>
          <select class="final-rx-route" style="width:100%;padding:9px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"><option>Oral</option><option>IV</option><option>IM</option><option>SC</option><option>Topical</option><option>Inhalation</option><option>Rectal</option><option>Other</option></select>
        </div>
        <button onclick="this.closest('.final-rx-row').remove()" style="padding:9px 12px;background:var(--danger-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-times"></i></button>
      </div>
    </div>
    <datalist id="final-rx-med-list"></datalist>
    <button onclick="addFinalRxRow()" style="padding:8px 14px;background:var(--gray-50);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;"><i class="fas fa-plus"></i> Add Medication</button>
  </div>

  <!-- Prescription -->
  <div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
    <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;"><i class="fas fa-print" style="color:#16a34a;"></i> 13. Prescription</h4>
    <button onclick="printDischargeRx()" style="padding:8px 14px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;margin-left:6px;"><i class="fas fa-print"></i> Print discharge prescription</button>
  </div>

<!-- Management and Follow Up (treatment_plan) -->
  <div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
    <label style="display:block;font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:6px;"><i class="fas fa-clipboard-list" style="color:var(--primary-color);margin-right:6px;"></i>14. Management and Follow Up</label>
    <textarea id="f-treatment-plan" rows="4" placeholder="Treatment plan, management notes, follow-up instructions, next appointment..." style="width:100%;padding:12px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea>
  </div>

  <!-- Follow-up appointment (rendez-vous): booking it does not open a visit; reception does that when the patient returns -->
  <div id="fu-card" style="margin-bottom:16px;border:1px solid #bfdbfe;background:#f8fbff;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
      <div>
        <div style="font-size:14px;font-weight:600;color:var(--gray-800);"><i class="fas fa-calendar-plus" style="color:var(--primary-color);margin-right:6px;"></i>15. Follow-up Appointment (Rendez-vous)</div>
        <div style="font-size:12px;color:var(--gray-500);margin-top:2px;">Does the patient need to come back? Book it here, you will be reminded on your dashboard.</div>
      </div>
      <button type="button" onclick="fuOpen()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;"><i class="fas fa-plus"></i> Schedule follow-up</button>
    </div>
    <div id="fu-list" style="margin-top:10px;font-size:13px;"></div>
  </div>

<div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <label style="display:block;font-size:15px;font-weight:700;color:var(--gray-800);margin:0 0 8px;">16. Admit Patient</label>
  <!-- Admit Panel (hidden) -->
  <div id="ord-panel-admit" style="">
    <h4 style="font-size:15px;font-weight:600;color:#5b21b6;margin-bottom:14px;"><i class="fas fa-procedures"></i> Request Patient Admission</h4>

    <!-- Already admitted banner (hidden by default) -->
    <div id="admit-already-sent" style="display:none;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px 16px;margin-bottom:14px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <i class="fas fa-check-circle" style="color:#16a34a;font-size:20px;"></i>
        <div>
          <div style="font-weight:700;color:#166534;font-size:14px;">Admission Request Already Sent</div>
          <div style="font-size:12px;color:#15803d;margin-top:2px;" id="admit-already-detail">An admission request has been submitted and is pending ward assignment.</div>
        </div>
      </div>
    </div>

    <!-- Form (hidden once submitted) -->
    <div id="admit-form-body">
      <p style="font-size:12px;color:var(--gray-500);margin-bottom:14px;">Submit an admission request. Ward staff will assign a bed.</p>
      <div style="margin-bottom:12px;">
        <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Reason for Admission <span style="color:var(--danger-color);">*</span></label>
        <textarea id="admit-reason" rows="3" placeholder="Diagnosis / reason for admission..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div>
          <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Admission Type</label>
          <select id="admit-type" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
            <option value="elective">Normal Admission</option>
            <option value="emergency">Emergency</option>
            <option value="transfer">Transfer</option>
          </select>
        </div>
        <div>
          <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Preferred Ward</label>
          <select id="admit-ward-pref" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
            <option value="">Any available</option>
            <option value="general">General</option>
            <option value="vip">VIP</option>
            <option value="icu">ICU</option>
            <option value="maternity">Maternity</option>
            <option value="pediatric">Pediatric</option>
            <option value="isolation">Isolation</option>
          </select>
        </div>
      </div>
      <button onclick="submitAdmitOrder()" id="btn-admit" style="padding:10px 20px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-paper-plane"></i> Send Admission Request</button>
    </div>
  </div>
</div>

    <!-- Complete -->
  <div style="display:flex;justify-content:flex-end;gap:10px;padding-top:16px;border-top:1px solid var(--gray-200);">
    <button onclick="saveAndComplete()" id="btn-complete" style="padding:12px 24px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-check-double"></i> Save & Complete Consultation
    </button>
  </div>
</div>

</div><!-- /c-main -->
<aside class="c-side"><div class="c-side-in" style="background:white;border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);">
  <h4 style="font-size:14px;font-weight:700;color:var(--gray-800);margin-bottom:8px;"><i class="fas fa-file-invoice" style="color:var(--primary-color);"></i> Billing List</h4>
  <div id="c-bill-pending"></div>
  <button id="btn-bill-send" onclick="billSendAll()" disabled style="width:100%;margin-top:12px;padding:10px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-paper-plane"></i> Send to Billing (0)</button>
  <div id="c-bill-sent"></div>
</div></aside></div><!-- /c-layout -->
</div><!-- /ct-consult -->
<script src="js/hosp-invoicing.js?v=<?= filemtime(__DIR__ . '/js/hosp-invoicing.js') ?>"></script>
<div id="ct-results" class="ct-panel" style="display:none">
<!-- Investigation Results (from Lab) -->
  <div style="margin-bottom:20px;">
    <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;"><i class="fas fa-flask" style="color:#2563eb;"></i> Lab Investigation Results</h4>
    <div id="c-lab-results" style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;min-height:60px;">
      <p style="color:var(--gray-400);font-size:13px;text-align:center;">Loading lab results...</p>
    </div>
  </div>

<!-- Investigation Results (from Imaging & Radiology) -->
  <div style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <h4 style="font-size:14px;font-weight:600;color:#9a3412;margin:0;"><i class="fas fa-x-ray" style="color:#ea580c;"></i> Imaging & Radiology Results</h4>
      <button onclick="cOpenImagingResultModal(-1)" style="padding:5px 12px;background:#ea580c;color:white;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;"><i class="fas fa-plus"></i> Add Imaging Result</button>
    </div>
    <div id="c-imaging-results" style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;min-height:60px;">
      <p style="color:var(--gray-400);font-size:13px;text-align:center;">Loading imaging results...</p>
    </div>
  </div>

<div id="ct-results-list"></div><div id="c-orders-summary" style="display:none;margin-top:22px;"><div id="c-orders-summary-content"></div></div></div>
<div id="ct-meds" class="ct-panel" style="display:none">
    <!-- Existing Prescriptions for Step 2 -->
    <div id="c-existing-rx-step2" style="display:none;margin-bottom:18px;">
      <h5 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;"><i class="fas fa-history" style="color:#16a34a;"></i> Prescriptions Already Ordered for this Visit</h5>
      <div id="c-existing-rx-list-step2" style="border:1px solid #bbf7d0;border-radius:8px;padding:12px;background:#f0fdf4;"></div>
    </div>

<div id="ct-meds-list"></div></div>
<div id="ct-invoicing" class="ct-panel" style="display:none"></div>
<div id="ct-visits" class="ct-panel" style="display:none"></div>
<script>
// Consultation tabs: switched client-side so the open consultation form is never reloaded or lost
document.addEventListener('DOMContentLoaded', function(){
  const loaded = {}, pid = () => patientId || (window._currentVisit && window._currentVisit.patient_id) || 0;
  const hospAPI = consultAPI.replace('consultation/consult', 'consultation/hosp-invoicing');
  const get = a => fetch(consultAPI + '&action=' + a + '&patient_id=' + pid() + '&visit_id=' + visitId).then(r => r.json());
  const fd = d => d ? cEsc(String(d).substring(0, 16)) : '—';
  const empty = m => '<div class="ct-empty">' + m + '</div>';
  const pill = (t, bg, c) => '<span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:' + bg + ';color:' + c + ';text-transform:capitalize;">' + cEsc(t) + '</span>';
  function resultHtml(o) {
    if (o.status === 'no_sample') return '<b style="color:#b91c1c;">NOT DONE — sample not taken</b>';
    if (!o.result) return '<span style="color:var(--gray-400);">Pending…</span>';
    let p = null; try { p = JSON.parse(o.result); } catch (e) {}
    if (Array.isArray(p)) return p.map(r => cEsc(r.test_name) + ': <b>' + cEsc(r.value || '—') + '</b>' + (r.unit ? ' ' + cEsc(r.unit) : '')).join('<br>');
    if (p && typeof p === 'object') return Object.entries(p).filter(([k, v]) => v && typeof v !== 'object').map(([k, v]) => cEsc(k.replace(/_/g, ' ')) + ': <b>' + cEsc(v) + '</b>').join('<br>') || '—';
    return cEsc(o.result);
  }
  const badge = (t, n) => { const b = document.getElementById('ctb-' + t); if (b) { b.textContent = n; b.style.display = n > 0 ? '' : 'none'; } };
  // Procedures / medication / consumables / samples recorded by nurses (doctor optional)
  const nurseSection = (links, kinds, title, icon) => {
    const l = links.filter(x => kinds.includes(x.kind));
    return '<h4 style="margin-top:22px;"><i class="fas ' + icon + '"></i> ' + title + '</h4>' + (!l.length ? empty('Nothing recorded by nurses.') :
      '<table><thead><tr><th>Date</th><th>Visit</th><th>Type</th><th>Details</th><th>Doctor</th><th>Recorded by</th></tr></thead><tbody>' +
      l.map(x => '<tr' + (x.mine ? ' style="background:#f0fdf4;"' : '') + '><td>' + fd(x.created_at) + '</td><td>' + cEsc(x.visit_no) + '</td><td>' + cEsc(x.kind) + '</td><td><b>' + cEsc(x.summary) + '</b></td><td>' +
        cEsc(x.doctor_name || '—') + (x.mine ? ' ' + pill('you', '#d1fae5', '#065f46') : '') + '</td><td>' + cEsc(x.nurse_name || '—') + '</td></tr>').join('') + '</tbody></table>');
  };
  const loaders = {
    async results(el) {
      const [rj, nj] = await Promise.all([get('patient_results'), get('patient_nurse_links')]);
      const d = rj.data || [], links = nj.data || [];
      badge('results', d.filter(o => !o.result && ['ordered', 'in_progress'].includes(o.status)).length); // lab / imaging still waiting for a result
      el.innerHTML = '<h4><i class="fas fa-folder-open"></i> All Results — this patient, every visit</h4>' + (!d.length ? empty('No lab or imaging orders for this patient.') :
        '<table><thead><tr><th>Date</th><th>Visit</th><th>Type</th><th>Test</th><th>Status</th><th>Result</th></tr></thead><tbody>' +
        d.map(o => '<tr><td>' + fd(o.ordered_at) + '</td><td>' + cEsc(o.visit_no) + '</td><td>' + (o.order_type === 'radiology' ? 'Imaging' : 'Lab') + '</td><td><b>' + cEsc(o.description) + '</b></td><td>' +
          pill(o.status, o.status === 'completed' ? '#d1fae5' : '#fef3c7', o.status === 'completed' ? '#065f46' : '#92400e') + '</td><td>' + resultHtml(o) + '</td></tr>').join('') + '</tbody></table>') +
        nurseSection(links, ['procedure', 'sample'], 'Nurse Procedures &amp; Samples', 'fa-syringe');
    },
    async meds(el) {
      const [mj, nj] = await Promise.all([get('patient_meds'), get('patient_nurse_links')]);
      const d = mj.data || {given: [], take_home: [], pending: 0}, links = nj.data || [];
      badge('meds', Number(d.pending) || 0); // prescribed, not yet dispensed
      el.innerHTML = '<h4><i class="fas fa-pills"></i> Medication Given</h4>' + (!d.given.length ? empty('No medication given in the clinic.') :
        '<table><thead><tr><th>Date</th><th>Medication</th><th>Dose</th><th>Given by</th></tr></thead><tbody>' +
        d.given.map(m => '<tr><td>' + fd(m.given_at) + '</td><td><b>' + cEsc(m.medication) + '</b></td><td>' + cEsc(m.dosage || '—') + '</td><td>' + cEsc(m.given_by || '—') + '</td></tr>').join('') + '</tbody></table>') +
        '<h4 style="margin-top:22px;"><i class="fas fa-house-medical"></i> Take-Home Medication</h4>' + (!d.take_home.length ? empty('No take-home medication.') :
        '<table><thead><tr><th>Date</th><th>Medication</th><th>Dosage</th><th>Qty</th></tr></thead><tbody>' +
        d.take_home.map(m => '<tr><td>' + fd(m.created_at) + '</td><td><b>' + cEsc(m.medication) + '</b></td><td>' + cEsc(m.dosage || '—') + '</td><td>' + cEsc(m.quantity || '—') + '</td></tr>').join('') + '</tbody></table>') +
        nurseSection(links, ['prescription', 'consumable'], 'Nurse Medication &amp; Consumables', 'fa-prescription');
    },
    async visits(el) {
      const d = (await get('patient_visits')).data || [];
      window._pvTab = d;
      el.innerHTML = '<h4><i class="fas fa-clock-rotate-left"></i> Previous Visits</h4>' + (!d.length ? empty('No previous visits for this patient.') :
        '<table><thead><tr><th>Visit No</th><th>Name</th><th>Phone</th><th>Insurance</th><th>Diagnosis</th><th>Doctor Notes</th><th>Doctor / Department</th><th>Action</th></tr></thead><tbody>' +
        d.map((v, i) => '<tr><td><b style="font-family:monospace;color:var(--primary-color);">' + cEsc(v.visit_no) + '</b><div style="font-size:11px;color:var(--gray-500);">' + cEsc(String(v.visit_date || '').substring(0, 10)) + '</div></td><td>' + cEsc(v.patient_name) + '</td><td>' + cEsc(v.phone || '—') +
          '</td><td>' + cEsc(v.insurance || 'Private') + '</td><td>' + cEsc(v.diagnosis || '—') + '</td><td style="max-width:240px;">' + cEsc(String(v.doctor_notes || '—').substring(0, 120)) + '</td><td>' +
          ((v.doctors || []).map(x => '<div><b>' + cEsc(x.doctor) + '</b> <span style="color:var(--gray-500);">' + cEsc(x.department || '') + '</span></div>').join('') || '—') +
          '</td><td><button type="button" onclick="pvView(' + i + ')" style="padding:5px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-eye"></i> View</button></td></tr>').join('') + '</tbody></table>');
    },
    async invoicing(el) {
      if (!el.querySelector('.ct-sub')) {   // built once, so background refreshes only swap the table contents (no flicker, sub-tab kept)
        el.innerHTML = '<div class="ct-sub"><a data-sub="opd" class="active">OPD<span class="ct-badge" id="ctb-opd" style="display:none"></span></a>' +
          '<a data-sub="hosp">Hospitalization<span class="ct-badge" id="ctb-hosp" style="display:none"></span></a></div><div id="ct-inv-opd"></div><div id="ct-inv-hosp" style="display:none"></div>';
        el.querySelector('.ct-sub').addEventListener('click', e => {
          const a = e.target.closest('a[data-sub]'); if (!a) return;
          el.querySelectorAll('.ct-sub a').forEach(x => x.classList.toggle('active', x === a));
          document.getElementById('ct-inv-opd').style.display = a.dataset.sub === 'opd' ? '' : 'none';
          document.getElementById('ct-inv-hosp').style.display = a.dataset.sub === 'hosp' ? '' : 'none';
        });
      }
      const [o, h] = await Promise.all([get('patient_invoicing'), fetch(hospAPI + '&patient_id=' + pid()).then(r => r.json())]);
      const d = o.data || [], hr = h.data || [];
      const col = {paid: ['#d1fae5', '#065f46'], covered: ['#d1fae5', '#065f46'], approved: ['#dbeafe', '#1e40af'], partial: ['#fef3c7', '#92400e'], unpaid: ['#fee2e2', '#b91c1c']};
      const nOpd = d.filter(i => !['paid', 'covered'].includes(i.pay_status)).length;
      document.getElementById('ct-inv-opd').innerHTML = '<h4><i class="fas fa-file-invoice"></i> My Billing for this Patient (OPD)</h4>' + (!d.length ? empty('No OPD bills raised by you for this patient.') :
        '<table><thead><tr><th>Date</th><th>Invoice</th><th>Visit</th><th>Items</th>' + (window._isAdminUser ? '<th>Total</th><th>Balance</th>' : '') + '<th>Status</th></tr></thead><tbody>' +
        d.map(i => '<tr><td>' + fd(i.created_at) + '</td><td><b>' + cEsc(i.invoice_no) + '</b></td><td>' + cEsc(i.visit_no) + (i.visit_type === 'follow_up' ? ' ' + pill('follow-up', '#ede9fe', '#5b21b6') : '') + '</td><td>' +
          i.items.map(x => cEsc(x.description) + (x.quantity > 1 ? ' ×' + cEsc(x.quantity) : '')).join('<br>') + '</td>' +
          (window._isAdminUser ? '<td>' + Number(i.total).toLocaleString() + '</td><td>' + Number(i.balance).toLocaleString() + '</td>' : '') +
          '<td>' + pill(i.pay_status === 'covered' ? 'paid' : i.pay_status, ...(col[i.pay_status] || ['#f3f4f6', '#555'])) + '</td></tr>').join('') + '</tbody></table>');
      const nHosp = hospInvoicing.render(document.getElementById('ct-inv-hosp'), hr, {admin: !!window._isAdminUser, esc: cEsc});
      badge('opd', nOpd); badge('hosp', nHosp);
      badge('invoicing', nOpd + nHosp); // bills not fully paid, OPD + hospitalization
    }
  };
  // Refresh in the background (no spinner). Badges need invoicing / results / medication at once; Previous Visits only after it was opened.
  const hostId = {results: 'ct-results-list', meds: 'ct-meds-list', invoicing: 'ct-invoicing', visits: 'ct-visits'};
  async function ctGate() {
    try {
      const j = await fetch(consultAPI + '&action=visit_pay_gate&visit_id=' + visitId).then(r => r.json());
      if (!j.success) return;
      const lock = j.data.unpaid > 0 && !j.data.admin;
      ['sec-final-dx', 'sec-final-rx'].forEach(id => { const x = document.getElementById(id); if (x) x.inert = lock; });
      document.getElementById('ct-lock-text').textContent = 'Final Diagnosis and Medication are read-only until all invoices of this visit are paid (' + j.data.unpaid + ' unpaid).';
      document.getElementById('ct-lock-banner').style.display = lock ? '' : 'none';
    } catch (e) {}
  }
  window.ctRefresh = () => {
    ctGate();
    ['invoicing', 'results', 'meds', 'visits'].forEach(t => {
      if (t === 'visits' && !loaded.visits) return;
      loaders[t](document.getElementById(hostId[t])).then(() => loaded[t] = true).catch(() => {});
    });
    if (typeof loadLabResults === 'function') loadLabResults();
    if (typeof loadImagingResults === 'function') loadImagingResults();
    if (typeof loadExistingPrescriptions === 'function') loadExistingPrescriptions();
  };
  window.addEventListener('load', () => window.ctRefresh());
  // Poll one tiny "did anything change?" call; reload tabs only when its hash moves. Skips hidden tabs and overlapping calls, backs off on errors.
  let sig = null, busy = false, fails = 0, timer = null;
  async function ctPoll() {
    timer = null;
    if (!document.hidden && !busy) {
      busy = true;
      try {
        const j = await get('patient_tabs_sig');
        if (!j.success) throw 0;
        fails = 0;
        if (sig !== null && j.data.sig !== sig) window.ctRefresh();
        sig = j.data.sig;
      } catch (e) { fails = Math.min(fails + 1, 5); }
      busy = false;
    }
    timer = setTimeout(ctPoll, 45000 * Math.pow(2, fails)); // 45s, doubling to ~24 min after repeated failures
  }
  window.addEventListener('load', () => { timer = setTimeout(ctPoll, 1000); });
  document.addEventListener('visibilitychange', () => { if (!document.hidden && !busy) { clearTimeout(timer); ctPoll(); } }); // catch up as soon as the tab is shown again
  document.getElementById('ct-tabs').addEventListener('click', async e => {
    const a = e.target.closest('a[data-tab]'); if (!a) return;
    const t = a.dataset.tab;
    document.querySelectorAll('#ct-tabs a').forEach(x => x.classList.toggle('active', x === a));
    ['consult', 'results', 'meds', 'invoicing', 'visits'].forEach(k => document.getElementById('ct-' + k).style.display = k === t ? '' : 'none');
    const el = document.getElementById({results: "ct-results-list", meds: "ct-meds-list"}[t] || "ct-" + t);
    if (loaders[t] && loaded[t] && t !== 'visits') loaders[t](el).catch(() => {}); // already shown from cache; refresh quietly on open
    if (loaders[t] && !loaded[t]) {
      el.innerHTML = empty('Loading…');
      try { await loaders[t](el); loaded[t] = true; } catch (err) { el.innerHTML = empty('Failed to load.'); }
    }
  });
});
</script>

<!-- Previous visits modal -->
<div id="pv-modal" onclick="if(event.target===this)pvClose()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:100%;max-width:1100px;height:90vh;display:flex;flex-direction:column;position:relative;">
    <div style="padding:16px 24px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:17px;font-weight:700;margin:0;"><i class="fas fa-clock-rotate-left" style="color:var(--primary-color);"></i> Previous Visits</h3>
      <button onclick="pvClose()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    </div>
    <div id="pv-cards" style="padding:14px 24px;display:flex;gap:10px;overflow-x:auto;border-bottom:1px solid var(--gray-200);flex-shrink:0;"></div>
    <div id="pv-detail" style="padding:20px 24px;overflow-y:auto;flex:1;"></div>
  </div>
</div>

<!-- Schedule follow-up modal -->
<div id="fu-modal" onclick="if(event.target===this)fuClose()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;align-items:center;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:24px;width:100%;max-width:560px;margin:auto;position:relative;">
    <button onclick="fuClose()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:4px;">Schedule follow-up appointment</h3>
    <p id="fu-patient" style="font-size:13px;color:var(--gray-500);margin-bottom:14px;"></p>
    <div id="fu-err" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div style="grid-column:1/-1;"><label style="font-size:12px;font-weight:600;color:var(--gray-600);">Doctor</label>
        <select id="fu-doctor" onchange="fuBooked()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></select></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--gray-600);">Date <span style="color:#dc2626;">*</span></label>
        <input id="fu-date" type="date" min="<?php echo date('Y-m-d'); ?>" onchange="fuBooked()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--gray-600);">Time <span style="color:#dc2626;">*</span></label>
        <input id="fu-time" type="time" step="300" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div style="grid-column:1/-1;display:flex;gap:6px;flex-wrap:wrap;" id="fu-quick"></div>
      <div id="fu-booked" style="grid-column:1/-1;font-size:12px;color:var(--gray-500);"></div>
      <div><label style="font-size:12px;font-weight:600;color:var(--gray-600);">Duration</label>
        <select id="fu-duration" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"><option>10</option><option selected>15</option><option>20</option><option>30</option><option>45</option><option>60</option></select></div>
      <div></div>
      <div style="grid-column:1/-1;"><label style="font-size:12px;font-weight:600;color:var(--gray-600);">Reason for follow-up <span style="color:#dc2626;">*</span></label>
        <input id="fu-reason" type="text" placeholder="e.g. Review lab results, check wound healing" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div style="grid-column:1/-1;"><label style="font-size:12px;font-weight:600;color:var(--gray-600);">Medical instructions / what to bring</label>
        <textarea id="fu-instructions" rows="3" placeholder="e.g. Come fasting, bring previous results, continue medication until review" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button id="fu-save" onclick="fuSave()" style="flex:1;padding:10px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-calendar-check"></i> Book appointment</button>
      <button onclick="fuClose()" style="padding:10px 18px;background:white;border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Close</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/components/imaging_report_modal.php'; ?>
<script src="js/approved-edit.js?v=<?= filemtime(__DIR__ . '/js/approved-edit.js') ?>"></script>

<script>
const visitId = <?php echo $visitId; ?>;
const patientId = <?php echo $patientId; ?>;
window._isAdminUser = <?php echo $isAdminUser ? 'true' : 'false'; ?>;
const _cBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const consultAPI = window.location.origin + _cBase + '/api.php?endpoint=consultation/consult';
const visitsAPI = window.location.origin + _cBase + '/api.php?endpoint=patients/visits';
const patientsAPI = window.location.origin + _cBase + '/api.php?endpoint=patients/patients';
const vitalsAPI = window.location.origin + _cBase + '/api.php?endpoint=settings/vital-signs';
const billingAPI = window.location.origin + _cBase + '/api.php?endpoint=patients/billing';
const inventoryAPI = window.location.origin + _cBase + '/api.php?endpoint=inventory/inventory-management';

function cEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

let labTestOptions = ''; // cached <option> html for lab tests
let medicationOptions = ''; // cached <option> html for medications

// ── Duplicate order guard & Visit Items Store ───────────────────────────────
window._currentVisitOrders = null;
window._existingVisitItems = {
  pharmacyIds: new Set(),
  pharmacyNames: new Set(),
  labIds: new Set(),
  labNames: new Set(),
  procIds: new Set(),
  procNames: new Set(),
  imgNames: new Set()
};

const _submittedItems = new Set();
function _dupKey(desc){ return String(desc||'').toLowerCase().trim(); }
function _checkDups(descs){
  const dupes = descs.filter(d => _submittedItems.has(_dupKey(d)));
  if(dupes.length){
    alert('Duplicate item(s) already added for this visit:\n\n• ' + dupes.join('\n• ') + '\n\nPlease edit the quantity of existing items in the table below instead of adding duplicates.');
    return false;
  }
  return true;
}
function _markSubmitted(descs){ descs.forEach(d => _submittedItems.add(_dupKey(d))); }
// ── End duplicate guard ───────────────────────────────────────────────────

// Load lab tests from service catalog
let labCatalogServices=null;
let labFacilityCategory='primary';

async function loadLabTests(){
  // Load facility category
  try{
    const hr=await fetch(window.location.origin + _cBase + '/api.php?endpoint=settings/hospital');
    const hj=await hr.json();
    if(hj.success && hj.data) labFacilityCategory=hj.data.facility_category||'primary';
  }catch(e){}
  // Load catalog
  try{
    const r=await fetch(window.location.origin + _cBase + '/api.php?endpoint=service-catalog&category=lab&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data) labCatalogServices=j.data;
  }catch(e){}
}

function labGetPrice(svc){
  if(labFacilityCategory==='secondary') return parseFloat(svc.secondary_health_facility)||0;
  if(labFacilityCategory==='tertiary') return parseFloat(svc.tertiary_health_facility)||0;
  return parseFloat(svc.primary_health_facility)||0;
}

const labSpecialtiesList = ['bacteriology','biochemistry','hematology','immuno-serology','parasitology','molecular','anatomical pathology','blood transfusion'];

let labSearchTimer;
function labSearchTests(input){
  clearTimeout(labSearchTimer);
  labSearchTimer=setTimeout(()=>{
    const q=input.value.trim().toLowerCase();
    const dropdown=input.closest('.lab-row').querySelector('.lab-dropdown');
    if(!labCatalogServices){dropdown.style.display='none';return;}
    
    let filtered=labCatalogServices.filter(s=>{
      const spec = (s.speciality||'').toLowerCase();
      return labSpecialtiesList.some(ls => spec.includes(ls));
    });
    if(q.length>=1){
      filtered=filtered.filter(s=>{
        return (s.procedure_name||'').toLowerCase().includes(q)||(s.speciality||'').toLowerCase().includes(q)||(s.ichi_code||'').toLowerCase().includes(q);
      });
    }
    filtered=filtered.slice(0,15);
    
    if(!filtered.length){
      dropdown.innerHTML='<div style="padding:10px;text-align:center;color:var(--gray-400);font-size:12px;">No tests found</div>';
      dropdown.style.display='block';return;
    }
    const catLabel=labFacilityCategory==='secondary'?'Secondary':labFacilityCategory==='tertiary'?'Tertiary':'Primary';
    const hasIns=cInsuranceId&&(cPaymentType==='insurance'||cPaymentType==='mixed');
    dropdown.innerHTML=filtered.map(s=>{
      const price=labGetPrice(s);
      const safeName=(s.procedure_name||'').replace(/'/g,"\\'").replace(/"/g,'&quot;');
      const insHint=hasIns?` <span style="color:#d97706;">(insurance price applies)</span>`:'';
      const priceDisplay = false ? ` — <strong style="color:#2563eb;">${price.toLocaleString()} RWF</strong> <span style="color:var(--gray-400);">(${catLabel})</span>${insHint}` : '';
      return `<div onclick="labSelectTest(this,${s.id},'${safeName}',${price})" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--gray-100);font-size:12px;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''">
        <div style="font-weight:500;">${cEsc(s.procedure_name||'—')}</div>
        <div style="font-size:10px;color:var(--gray-500);margin-top:1px;">${cEsc(s.speciality||'')} ${s.ichi_code?'| '+s.ichi_code:''}${priceDisplay}</div>
      </div>`;
    }).join('');
    dropdown.style.display='block';
  },200);
}

function labSelectTest(el,id,name,price){
  const testKey = name.toLowerCase().trim();
  if(window._existingVisitItems && (window._existingVisitItems.labNames.has(testKey) || window._existingVisitItems.labIds.has(String(id)))){
    alert(`Lab test "${name}" is already ordered for this visit.\n\nDuplicate orders for the same lab test are not allowed.`);
    el.closest('.lab-dropdown').style.display='none';
    return;
  }
  let countOther = 0;
  document.querySelectorAll('#lab-items .lab-test-id').forEach(inp => {
    if(inp.value && String(inp.value) === String(id)) countOther++;
  });
  if(countOther > 0){
    alert(`Lab test "${name}" is already selected in this order form.`);
    el.closest('.lab-dropdown').style.display='none';
    return;
  }

  const row=el.closest('.lab-row');
  row.querySelector('.lab-test-search').value=name;
  row.querySelector('.lab-test-id').value=id;
  row.querySelector('.lab-test-price').value=price;
  row.querySelector('.lab-dropdown').style.display='none';
}

// Close dropdowns on outside click
document.addEventListener('click',function(e){
  if(!e.target.classList.contains('lab-test-search')){
    document.querySelectorAll('.lab-dropdown').forEach(d=>d.style.display='none');
  }
});

// Load medications from items table
async function loadMedications(){
  if(medicationOptions) return;
  let items = [];
  try{
    // Load medications (is_medication=1)
    const r=await fetch(inventoryAPI+'&action=items&medication=1&limit=500');
    const j=await r.json();
    if(j.success){
      items=j.data.items||j.data||[];
      window._medList=items; window._medById={}; items.forEach(i=>{window._medById[i.id]=i;});
      medicationOptions=items.map(i=>`<option value="${i.id}" data-price="${i.selling_price||0}" data-cost="${i.unit_price||0}">${cEsc(i.name)}${i.generic_name?' ('+cEsc(i.generic_name)+')':''}${false ? ' - ' + parseFloat(i.selling_price||0).toLocaleString() + ' RWF' : ''}</option>`).join('');
    }
  }catch(e){}
  // Load insurance pricing — must run after cCompanyId is set by loadPatient
  if(cCompanyId){
    try{
      const r=await fetch(inventoryAPI+'&action=insurance_prices&insurance_id='+cCompanyId);
      const j=await r.json();
      if(j.success) window._drugInsPrices=j.data||[];
    }catch(e){ window._drugInsPrices=[]; }
  }
  // Populate existing selects
  document.querySelectorAll('select.final-rx-med').forEach(sel=>{
    if(sel.options && sel.options.length<=1) sel.innerHTML='<option value="">Select Medication...</option>'+medicationOptions;
  });
  // Populate datalist for final rx free-text inputs
  if(items && items.length){
    let dl = document.getElementById('final-rx-med-list');
    if(!dl){
      dl = document.createElement('datalist');
      dl.id = 'final-rx-med-list';
      document.body.appendChild(dl);
    }
    dl.innerHTML = items.map(i=>`<option value="${cEsc(i.name)}">${i.generic_name?cEsc(i.generic_name):''}</option>`).join('');
    document.querySelectorAll('.final-rx-med').forEach(inp=>{
      if(!inp.getAttribute('list')) inp.setAttribute('list','final-rx-med-list');
    });
  }
}

// Calculate drug price based on insurance rules
function getDrugPrice(itemId){
  const sellingPrice=parseFloat((window._medById||{})[itemId]?.selling_price)||0;

  if(!cInsuranceId || !window._drugInsPrices || !(cPaymentType==='insurance'||cPaymentType==='mixed')){
    return {total:sellingPrice, patientPays:sellingPrice, insurancePays:0, topup:0, insPrice:0, isExceptional:false, note:'Self-pay'};
  }

  const insRow=window._drugInsPrices.find(p=>p.item_id==itemId);
  if(!insRow){
    return {total:sellingPrice, patientPays:sellingPrice, insurancePays:0, topup:0, insPrice:0, isExceptional:false, note:'Not in formulary — patient pays full price'};
  }

  const price        = parseFloat(insRow.price)||0;
  const patientTopup = 0; // top-ups removed
  const isExceptional= parseInt(insRow.is_exceptional)||0;
  const copayRate    = cCopayPct/100;
  const coverRate    = 1 - copayRate;

  const insurancePays = Math.round(price * coverRate);
  const patientCopay  = price - insurancePays;          // = price * copayRate (rounded)

  if(isExceptional){
    // Patient pays their copay share PLUS the patient_topup
    const patientPays = patientCopay + patientTopup;
    return {
      total: price + patientTopup,
      patientPays,
      insurancePays,
      topup: patientTopup,
      insPrice: price,
      isExceptional: true,
      note: 'Exceptional'
    };
  } else {
    // Standard: just split price at copay rate, no topup
    return {
      total: price,
      patientPays: patientCopay,
      insurancePays,
      topup: 0,
      insPrice: price,
      isExceptional: false,
      note: 'Covered'
    };
  }
}

// ============ VALIDATION ============
// Fields 1-4 are mandatory to complete the consultation
function validateStep1(){
  const req=[['f-complaints','Chief Complaints'],['f-past-history','Past medical history'],['f-physical-exam','Physical Examination'],['f-diff-diagnosis','Differential Diagnosis']];
  const al=document.getElementById('c-alert');
  const missing=req.filter(([id])=>!document.getElementById(id).value.trim());
  req.forEach(([id])=>{ document.getElementById(id).style.borderColor=missing.some(m=>m[0]===id)?'#dc2626':''; });
  if(!missing.length){ al.style.display='none'; return true; }
  al.style.background='#fef2f2';al.style.color='#991b1b';
  al.textContent='Required: '+missing.map(m=>m[1]).join(', ');al.style.display='block';
  document.getElementById(missing[0][0]).focus();
  return false;
}
// ============ LOAD PATIENT ============
let cDeptId = 0; // department_id from visit
let cInsuranceId = null; // insurance_id from visit (patient_insurance.id)
let cInsuranceName = '';
let cPackageName = '';
let cCompanyId = null;  // insurance company id for drug price lookups
let cPaymentType = null; // payment_type from visit
let cCopayPct = 100; // copayment percentage (100 = no insurance)

async function loadPatient(){
  try{
    const vr=await fetch(visitsAPI+'&id='+visitId);
    const vj=await vr.json();
    if(vj.success){
      const v=vj.data;
      window._currentVisit = v;
      if(v.status==='completed'){ const vf=document.getElementById('c-vrec-form'); if(vf) vf.style.display='none'; }
      cDeptId = v.department_id || 0;
      const sd=document.getElementById('sec-dental'); if(sd) sd.style.display=/dent/i.test(v.department_name||'')?'':'none'; if(/dent/i.test(v.department_name||'')) setTimeout(dentInit,0); // after payment/insurance globals below are set
      cPaymentType = v.payment_type || 'cash';
      cInsuranceId = v.insurance_id || null;
      cInsuranceName = v.company_name || '';
      cPackageName = v.package_name || '';
      cCompanyId = v.company_id || null;
      
      const isIns = (cPaymentType === 'insurance' || cPaymentType === 'mixed' || (cInsuranceId && cInsuranceId != 13)) && cInsuranceName;
      cCopayPct = isIns ? (v.copayment_percentage != null && v.copayment_percentage !== '' ? parseFloat(v.copayment_percentage) : 15) : 100;
      const coverage = 100 - cCopayPct;

      // Load medications NOW — after cCompanyId is set so insurance prices load correctly
      medicationOptions = ''; // reset so loadMedications re-runs with correct company
      loadMedications();
      
      document.getElementById('c-visit-no').textContent=v.visit_number||v.visit_no||v.id;
      
      // Show insurance badge and summary
      const badge = document.getElementById('c-insurance-badge');
      const insInline = document.getElementById('c-ins-inline');
      if(isIns){
        const pkgStr = cPackageName ? ` · ${cPackageName}` : '';
        badge.innerHTML = `<i class="fas fa-shield-alt"></i> <span>${cEsc(cInsuranceName)}${cEsc(pkgStr)}</span> <span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;font-weight:700;margin-left:4px;">${cCopayPct}% Copay</span> <span style="font-size:10px;color:#047857;margin-left:2px;">(${coverage}% Covered)</span>`;
        badge.style.display = 'inline-flex';
        badge.style.alignItems = 'center';
        badge.style.gap = '4px';
        badge.style.background = '#f0fdf4';
        badge.style.color = '#166534';
        badge.style.border = '1px solid #bbf7d0';

        if(insInline) {
          insInline.innerHTML = `<strong>${cEsc(cInsuranceName)}</strong> (${cCopayPct}% Copay)`;
        }

        // Show insurance toggles on order panels
        const insLabel = `<strong>${cEsc(cInsuranceName)}</strong>`;
        document.getElementById('lab-ins-toggle').style.display = 'block';
        document.getElementById('lab-ins-info').innerHTML = `${insLabel} covers <strong>${coverage}%</strong> — Patient pays <strong>${cCopayPct}%</strong>`;
        document.getElementById('rx-ins-toggle').style.display = 'block';
        document.getElementById('rx-ins-info').innerHTML = `${insLabel} covers <strong>${coverage}%</strong> — Patient pays <strong>${cCopayPct}%</strong>`;
        // Show per-item insurance checkboxes
        document.querySelectorAll('.lab-ins-row').forEach(el=>el.style.display='block');
        document.querySelectorAll('.final-rx-ins-row').forEach(el=>el.style.display='block');
      } else {
        badge.innerHTML = `<i class="fas fa-user"></i> <span>Self-Pay / Cash</span> <span style="background:#f3f4f6;color:#4b5563;padding:2px 8px;border-radius:10px;font-weight:600;margin-left:4px;">100% Patient</span>`;
        badge.style.display = 'inline-flex';
        badge.style.alignItems = 'center';
        badge.style.gap = '4px';
        badge.style.background = '#f9fafb';
        badge.style.color = '#4b5563';
        badge.style.border = '1px solid #e5e7eb';

        if(insInline) {
          insInline.innerHTML = `Self-Pay (100% Copay)`;
        }

        document.getElementById('lab-ins-toggle').style.display = 'none';
        document.getElementById('rx-ins-toggle').style.display = 'none';
        document.querySelectorAll('.lab-ins-row').forEach(el=>el.style.display='none');
        document.querySelectorAll('.final-rx-ins-row').forEach(el=>el.style.display='none');
      }
      const pid=v.patient_id||patientId;
      const pr=await fetch(patientsAPI+'&id='+pid);
      const pj=await pr.json();
      if(pj.success){
        const p=pj.data;
        window._currentPatient = p;
        const name=(p.first_name||'')+' '+(p.last_name||'');
        document.getElementById('c-name').textContent=name;
        const al=(p.allergies||'').trim(), alPill=document.getElementById('c-allergy-pill');
        if(al && !/^(none|no|nil|n\/a|-+|none known|nkda)\b/i.test(al)){ alPill.innerHTML='<i class="fas fa-exclamation-triangle"></i> Allergies: '+cEsc(al); alPill.style.display='inline-block'; }
        document.getElementById('c-avatar').textContent=(p.first_name||'?')[0].toUpperCase();
        document.getElementById('c-uhid').textContent=p.patient_no||p.id;
        document.getElementById('c-gender').textContent=p.gender||'—';
        document.getElementById('c-phone').textContent=p.phone||'—';
        if(p.date_of_birth){
          const age=Math.floor((Date.now()-new Date(p.date_of_birth).getTime())/(365.25*24*60*60*1000));
          document.getElementById('c-age').textContent=age+' yrs';
        }
        // Pre-populate medical profile fields (Step 1)
        if(p.blood_group) document.getElementById('f-blood-group').value=p.blood_group;
        document.getElementById('f-allergies').value=p.allergies||'';
        document.getElementById('f-chronic-diseases').value=p.chronic_diseases||'';
      }
      loadOrdersSummary();
    }
  }catch(e){console.error(e);}
}

// ============ CORRECT A VITAL SIGN (admin-approved, logged) ============
function cEditVital(id){
  const v=window._cVitals[id]; if(!v) return;
  approvedEdit('consult_vital', id, v.sign_name+' — visit #'+visitId, [{key:'value',label:v.sign_name+(v.unit?' ('+v.unit+')':''),value:v.value}], loadVitals);
}

// All active vital signs, including ones the nurse left empty. Empty = doctor fills directly;
// recorded = read-only here, corrected through the approval pencil.
const cEditAllVitals=()=>editAllVitals(visitId,patientId,()=>{loadVitals();loadConsultEvents();},cEditVital);

// ============ DOCTOR/NURSE VITAL ENTRIES (nursing_records, one new row per entry) ============
async function loadVitalRecords(){
  const box=document.getElementById('c-vrec-list'); if(!box) return;
  try{
    const j=await (await fetch(consultAPI+'&action=nursing_records&visit_id='+visitId+'&limit=200')).json();
    const rows=(j.data||[]).filter(r=>r.record_type==='vitals');
    const role=r=>/doctor|physician|clinician/i.test(r||'')?'Doctor':/nurse/i.test(r||'')?'Nurse':(r||'');
    box.innerHTML=rows.length?rows.map(r=>`<div style="font-size:12px;color:var(--gray-700);padding:5px 0;border-bottom:1px solid var(--gray-100);">
        <strong>${cEsc(String(r.description||'').replace(/^Vitals:\s*/,''))}</strong>
        <span style="color:var(--gray-500);"> · ${cEsc(r.nurse_name||'—')}${r.entered_by_role?' ('+cEsc(role(r.entered_by_role))+')':''} · ${cEsc(r.created_at||'')}</span>
      </div>`).join(''):'<div style="font-size:12px;color:var(--gray-400);">No vital entries yet.</div>';
  }catch(e){}
}
async function saveVitalRecord(){
  if(window._currentVisit?.status==='completed') return;
  const g=k=>document.getElementById('c-v-'+k).value.trim();
  if(!['bp','temp','pulse','spo2','rr','wt'].some(g)) return alert('Enter at least one vital sign.');
  const btn=document.getElementById('btn-save-vrec'), st=document.getElementById('sv-c-vrec'), old=btn.innerHTML;
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
  try{
    const j=await (await fetch(consultAPI+'&action=nursing_record',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      patient_id:patientId||window._currentVisit?.patient_id, visit_id:visitId, bed_assignment_id:0, record_type:'vitals',
      item_id:null, service_id:null, dosage:'', quantity:1, unit_price:0, notes:'', administered_by:cUserId,
      description:'Vitals: BP:'+g('bp')+' T:'+g('temp')+' P:'+g('pulse')+' SpO2:'+g('spo2')+' RR:'+g('rr')+' Wt:'+g('wt')
    })})).json();
    st.style.color=j.success?'#059669':'#dc2626';
    st.textContent=j.success?'✓ Saved':(j.message||'Save failed');
    if(j.success){ ['bp','temp','pulse','spo2','rr','wt'].forEach(k=>{document.getElementById('c-v-'+k).value='';}); loadVitalRecords(); setTimeout(()=>{st.textContent='';},3000); }
  }catch(e){ st.style.color='#dc2626'; st.textContent='Error: '+e.message; }
  finally{ btn.disabled=false; btn.innerHTML=old; }
}

// ============ LOAD VITALS ============
async function loadVitals(){
  try{
    // every active sign is shown; ones the nurse left empty show "—"
    const [sj,j]=await Promise.all([fetch(vitalsAPI).then(r=>r.json()),fetch(vitalsAPI+'&action=records&visit_id='+visitId).then(r=>r.json())]);
    const el=document.getElementById('c-vitals');
    window._cVitals={}; const have={};
    (j.data||[]).forEach(v=>{window._cVitals[v.id]=v; if(!have[v.vital_sign_id]) have[v.vital_sign_id]=v;});
    el.innerHTML=(sj.data||[]).map(s=>{const v=have[s.id];
      return `<div style="background:var(--gray-50);border-radius:6px;padding:8px 14px;text-align:center;min-width:90px;${v?'':'opacity:.7;'}">
          <div style="font-size:10px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">${cEsc(s.name)}${v?` <i class="fas fa-pen" title="Correct (needs admin approval)" style="cursor:pointer;color:#2563eb;margin-left:4px;" onclick="cEditVital(${v.id})"></i>`:''}</div>
          <div style="font-size:16px;font-weight:700;color:${v?'var(--gray-900)':'var(--gray-400)'};margin:2px 0;">${v?cEsc(v.value):'—'}</div>
          <div style="font-size:10px;color:var(--gray-400);">${cEsc(s.unit||'')}</div>
        </div>`;}).join('')||'<span style="color:var(--gray-400);font-size:13px;">No vital signs configured</span>';
  }catch(e){console.error(e);}
}

// ============ FULL TIMELINE: every doctor who worked on this file ============
async function loadConsultEvents(){
  try{
    const j=await (await fetch(consultAPI+'&action=events&visit_id='+visitId)).json();
    if(!j.success||!j.data.length) return;
    const auditEl=document.getElementById('c-audit-trail'); auditEl.style.display='block';
    let box=document.getElementById('c-audit-events');
    if(!box){ box=document.createElement('div'); box.id='c-audit-events'; box.style.cssText='margin-top:10px;border-top:2px solid #e5e7eb;padding-top:8px;'; auditEl.appendChild(box); }
    // same user doing the same thing back-to-back = one row: latest time + repeat count
    const evs=[]; j.data.forEach(e=>{ const p=evs[evs.length-1]; if(p&&p.user_name===e.user_name&&p.event===e.event&&(p.detail||'')===(e.detail||'')&&!parseInt(e.is_handover)&&!parseInt(p.is_handover)){ p.n++; if(e.created_at>p.created_at) p.created_at=e.created_at; } else evs.push({...e,n:1}); });
    box.innerHTML='<div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:4px;">Full activity (all users)</div>'+evs.map(e=>`
      <div style="font-size:11px;color:#4b5563;padding:3px 0;border-bottom:1px solid #f3f4f6;">
        ${new Date(e.created_at.replace(' ','T')).toLocaleString([],{dateStyle:'short',timeStyle:'short'})} · <strong>${cEsc(e.user_name||'—')}</strong> — ${cEsc(e.event)}${e.n>1?' <span style="color:#6b7280;">(repeated '+e.n+' times)</span>':''}${e.detail?' <em>('+cEsc(e.detail)+')</em>':''}
        ${parseInt(e.is_handover)?'<span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:8px;font-weight:600;margin-left:4px;">handover</span>':''}
      </div>`).join('');
  }catch(e){}
}

// ============ LOAD EXISTING CONSULTATION ============
async function loadExisting(){
  try{
    const r=await fetch(consultAPI+'&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data){
      const c=j.data;

      // ── Audit Trail ─────────────────────────────────────────────────
      const auditEl = document.getElementById('c-audit-trail');
      if(auditEl){
        const fmt = dt => dt ? new Date(dt).toLocaleString([],{dateStyle:'short',timeStyle:'short'}) : null;
        let rows = [];

        // Who started the consultation (clicked Consult)
        if(c.doctor_name){
          rows.push({icon:'fa-user-md',color:'#2563eb',label:'Consultation Started',who:c.doctor_name,when:fmt(c.created_at)});
        }
        // Who saved Step 1
        const s1name = c.step1_by_name || c.step1_name;
        if(s1name){
          rows.push({icon:'fa-notes-medical',color:'#7c3aed',label:'Step 1 Saved (Notes & History)',who:s1name,when:fmt(c.step1_at)});
        }
        // Who saved Step 3 / Diagnosis
        const s3name = c.step3_by_name || c.step3_name;
        if(s3name){
          rows.push({icon:'fa-stethoscope',color:'#0891b2',label:'Step 3 Saved (Diagnosis & Prescription)',who:s3name,when:fmt(c.step3_at)});
        }
        // Who completed
        const compName = c.completed_by_name || c.completed_name;
        if(compName){
          rows.push({icon:'fa-check-double',color:'#16a34a',label:'Consultation Completed',who:compName,when:fmt(c.completed_at)});
        }

        if(rows.length){
          auditEl.style.display='block';
          document.getElementById('c-audit-trail-rows').innerHTML = rows.map(r=>`
            <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #e5e7eb;">
              <div style="width:28px;height:28px;border-radius:50%;background:${r.color}22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas ${r.icon}" style="font-size:12px;color:${r.color};"></i>
              </div>
              <div style="flex:1;min-width:0;">
                <div style="font-size:12px;font-weight:600;color:#374151;">${r.label}</div>
                <div style="font-size:11px;color:#6b7280;">by <strong>${cEsc(r.who)}</strong>${r.when?' &nbsp;·&nbsp; '+r.when:''}</div>
              </div>
            </div>`).join('');
        }
      }
      loadConsultEvents();
      // ── End Audit Trail ──────────────────────────────────────────────

      if(c.chief_complaint) document.getElementById('f-complaints').value=c.chief_complaint;
      if(c.history) document.getElementById('f-past-history').value=c.history;
      if(c.examination) document.getElementById('f-physical-exam').value=c.examination;
      if(c.differential_diagnosis) document.getElementById('f-diff-diagnosis').value=c.differential_diagnosis;
      if(c.treatment_plan) document.getElementById('f-treatment-plan').value=c.treatment_plan;
      if(c.diagnosis){
        selectedDiagnoses=c.diagnosis.split(',').map(d=>d.trim()).filter(Boolean);
        renderDiagnoses();
      }
      // Load saved prescriptions back into form
      if(c.prescription){
        let rxList=[];
        try{ rxList=JSON.parse(c.prescription); }catch(e){}
        if(rxList.length){
          const container=document.getElementById('final-rx-items');
          // Clear default empty row
          container.innerHTML='';
          rxList.forEach(rx=>{
            container.insertAdjacentHTML('beforeend', finalRxRowHtml(rx));
          });
        }
      }
    }
  }catch(e){}
}

// ============ LOAD LAB RESULTS (Step 3) ============
async function loadLabResults(){
  try{
    const r=await fetch(consultAPI+'&action=lab_results&visit_id='+visitId);
    const j=await r.json();
    const el=document.getElementById('c-lab-results');
    if(j.success&&j.data&&j.data.length){
      let tableHtml='';
      j.data.forEach((t,i)=>{
        let resultHtml='<span style="color:var(--gray-400);">Pending...</span>';
        if(t.status==='no_sample') resultHtml='<span style="font-weight:700;color:#b91c1c;"><i class="fas fa-ban"></i> NOT DONE — sample not taken</span>';
        else if(t.result){
          let parsed=null;
          try{parsed=JSON.parse(t.result);}catch(e){}
          if(parsed&&Array.isArray(parsed)&&parsed.length){
            resultHtml=`<table style="width:100%;border-collapse:collapse;font-size:11px;margin:4px 0;">
              ${parsed.map(r=>{
                const val=parseFloat(r.value);
                const min=r.normal_min!=null?parseFloat(r.normal_min):null;
                const max=r.normal_max!=null?parseFloat(r.normal_max):null;
                let flag='';
                if(!isNaN(val)&&min!=null&&max!=null){
                  if(val<min) flag='<span style="color:#dc2626;font-weight:700;">↓</span>';
                  else if(val>max) flag='<span style="color:#dc2626;font-weight:700;">↑</span>';
                  else flag='<span style="color:#059669;">✓</span>';
                }
                return `<tr><td style="padding:2px 4px;font-weight:500;">${cEsc(r.test_name)}</td><td style="padding:2px 4px;font-weight:700;">${cEsc(r.value||'—')}</td><td style="padding:2px 4px;color:var(--gray-500);">${cEsc(r.unit||'')}</td><td style="padding:2px 4px;color:var(--gray-400);">${min!=null&&max!=null?min+'-'+max:''}</td><td style="padding:2px 4px;">${flag}</td></tr>`;
              }).join('')}
            </table>`;
          } else {
            resultHtml=`<span style="font-weight:600;color:#065f46;">${cEsc(t.result)}</span>`;
          }
        }
        tableHtml+=`<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:10px 8px;color:var(--gray-500);">${i+1}</td>
          <td style="padding:10px 8px;font-weight:600;">${cEsc(t.test_name||t.description||'—')}</td>
          <td style="padding:10px 8px;"><span style="padding:2px 6px;border-radius:8px;font-size:10px;background:${t.order_type==='radiology'?'#fef3c7':'#dbeafe'};color:${t.order_type==='radiology'?'#92400e':'#1e40af'};">${cEsc(t.order_type||'lab')}</span></td>
          <td style="padding:10px 8px;">${resultHtml}</td>
          <td style="padding:10px 8px;font-size:12px;">
            <table style="width:100%;border-collapse:collapse;">
              <tr>
                <td style="padding:2px 6px;background:#eff6ff;border-radius:4px 4px 0 0;border-bottom:1px solid #bfdbfe;">
                  <span style="font-size:10px;font-weight:700;color:#1e40af;text-transform:uppercase;letter-spacing:0.5px;"><i class="fas fa-user-md" style="margin-right:3px;"></i>Ordered By</span>
                </td>
              </tr>
              <tr>
                <td style="padding:3px 6px;">
                  <span style="font-weight:600;color:var(--gray-800);">${cEsc(t.ordered_by_name||'—')}</span><br>
                  <span style="font-size:10px;color:var(--gray-400);">${t.ordered_at?new Date(t.ordered_at).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—'}</span>
                </td>
              </tr>
              ${t.completed_by_name?`
              <tr>
                <td style="padding:2px 6px;background:#f0fdf4;border-radius:0;border-top:1px solid #bbf7d0;border-bottom:1px solid #bbf7d0;">
                  <span style="font-size:10px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:0.5px;"><i class="fas fa-check-circle" style="margin-right:3px;"></i>Results By</span>
                </td>
              </tr>
              <tr>
                <td style="padding:3px 6px;">
                  <span style="font-weight:600;color:#065f46;">${cEsc(t.completed_by_name)}</span><br>
                  <span style="font-size:10px;color:var(--gray-400);">${t.completed_at?new Date(t.completed_at).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—'}</span>
                </td>
              </tr>`:'<tr><td style="padding:3px 6px;font-size:10px;color:var(--gray-400);">Results pending...</td></tr>'}
            </table>
          </td>
          <td style="padding:10px 8px;text-align:center;"><span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:${t.status==='no_sample'?'#fee2e2':t.status==='completed'?'#d1fae5':t.status==='ordered'?'#fef3c7':'#dbeafe'};color:${t.status==='no_sample'?'#991b1b':t.status==='completed'?'#065f46':t.status==='ordered'?'#92400e':'#1e40af'};">${cEsc(t.status||'ordered')}</span></td>
        </tr>`;
      });
      el.innerHTML=`<table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="border-bottom:2px solid var(--gray-200);background:var(--gray-50);">
          <th style="padding:10px 8px;text-align:left;font-weight:600;">#</th>
          <th style="padding:10px 8px;text-align:left;font-weight:600;">Test Name</th>
          <th style="padding:10px 8px;text-align:left;font-weight:600;">Type</th>
          <th style="padding:10px 8px;text-align:left;font-weight:600;">Result</th>
          <th style="padding:10px 8px;text-align:left;font-weight:600;">Ordered By / Results By</th>
          <th style="padding:10px 8px;text-align:center;font-weight:600;">Status</th>
        </tr></thead><tbody>${tableHtml}</tbody></table>`;
    } else {
      el.innerHTML='<p style="color:var(--gray-400);font-size:13px;text-align:center;"><i class="fas fa-flask" style="font-size:20px;display:block;margin-bottom:6px;"></i>No lab tests ordered for this visit.</p>';
    }
  }catch(e){
    const labEl = document.getElementById('c-lab-results');
    if(labEl) labEl.innerHTML='<p style="color:var(--gray-400);font-size:13px;text-align:center;">Could not load lab results.</p>';
  }
}

// ============ LOAD EXISTING PRESCRIPTIONS (from Step 2) ============
async function loadExistingPrescriptions(){
  try{
    const r=await fetch(window.location.origin + _cBase + '/api.php?endpoint=consultation/consult&action=prescriptions&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      const html=j.data.map(rx=>{
        const stColors={active:'#16a34a',dispensed_full:'#2563eb',dispensed_partial:'#d97706',cancelled:'#dc2626'};
        const stLabel=rx.status.replace(/_/g,' ');
        let itemsHtml='';
        if(rx.items&&rx.items.length){
          itemsHtml='<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:8px;"><thead><tr style="border-bottom:1px solid #bbf7d0;"><th style="padding:4px 8px;text-align:left;">Medication</th><th style="padding:4px 8px;">Dosage</th><th style="padding:4px 8px;text-align:center;">Qty</th><th style="padding:4px 8px;text-align:center;">Status</th></tr></thead><tbody>'+
          rx.items.map(it=>`<tr style="border-bottom:1px solid #ecfdf5;"><td style="padding:4px 8px;font-weight:500;">${cEsc(it.item_name||it.medication||'—')}</td><td style="padding:4px 8px;">${cEsc(it.dosage)}</td><td style="padding:4px 8px;text-align:center;font-weight:600;">${it.quantity}</td><td style="padding:4px 8px;text-align:center;"><span style="font-size:10px;padding:2px 6px;border-radius:8px;background:${stColors[it.status]||'#ccc'}20;color:${stColors[it.status]||'#555'};">${it.status}</span></td></tr>`).join('')+
          '</tbody></table>';
        }
        return `<div style="margin-bottom:10px;padding:10px 12px;background:white;border-radius:6px;border:1px solid #bbf7d0;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div><strong style="font-size:13px;color:var(--primary-color);font-family:monospace;">${cEsc(rx.prescription_no)}</strong> <span style="font-size:11px;color:var(--gray-500);margin-left:8px;">${rx.prescription_date||''}</span>
            ${rx.created_by_name||rx.prescribed_by_name?'<span style="font-size:11px;color:var(--gray-600);margin-left:8px;"><i class="fas fa-user-nurse" style="color:var(--gray-400);margin-right:3px;"></i>'+cEsc(rx.created_by_name||rx.prescribed_by_name)+'</span>':''}</div>
            <span style="padding:3px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${stColors[rx.status]||'#ccc'}20;color:${stColors[rx.status]||'#555'};">${stLabel}</span>
          </div>
          ${rx.notes?'<div style="font-size:11px;color:var(--gray-600);margin-top:4px;"><i class="fas fa-sticky-note"></i> '+cEsc(rx.notes)+'</div>':''}
          ${itemsHtml}
        </div>`;
      }).join('');
      // Show on step 3
      const elRx3 = document.getElementById('c-existing-rx');
      const elRxList3 = document.getElementById('c-existing-rx-list');
      if(elRx3) elRx3.style.display = 'block';
      if(elRxList3) elRxList3.innerHTML = html;

      // Show on step 2
      const elRx2 = document.getElementById('c-existing-rx-step2');
      const elRxList2 = document.getElementById('c-existing-rx-list-step2');
      if(elRx2) elRx2.style.display = 'block';
      if(elRxList2) elRxList2.innerHTML = html;
    } else {
      const elRx3 = document.getElementById('c-existing-rx');
      const elRx2 = document.getElementById('c-existing-rx-step2');
      if(elRx3) elRx3.style.display = 'none';
      if(elRx2) elRx2.style.display = 'none';
    }
  }catch(e){
    const elRx3 = document.getElementById('c-existing-rx');
    const elRx2 = document.getElementById('c-existing-rx-step2');
    if(elRx3) elRx3.style.display = 'none';
    if(elRx2) elRx2.style.display = 'none';
  }
}

// ============ LOAD DIAGNOSIS LIST ============
const diagnosesAPI = window.location.origin + _cBase + '/api.php?endpoint=consultation/diagnoses';
let diagnosisTimer;

async function searchDiagnoses(){
  clearTimeout(diagnosisTimer);
  diagnosisTimer=setTimeout(async()=>{
    const q=document.getElementById('f-final-diagnosis').value.trim();
    const list=document.getElementById('diag-list');
    try{
      const j=await (await fetch(diagnosesAPI+'&search='+encodeURIComponent(q))).json();
      if(document.getElementById('f-final-diagnosis').value.trim()!==q) return; // stale
      const rows=((j.success&&j.data)||[]).filter(d=>!selectedDiagnoses.includes(d.name));
      const row='padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--gray-100);';
      let h=rows.map(d=>'<div onclick="selectDiagnosis(\''+cEsc(d.name).replace(/'/g,"\\'")+'\','+d.id+')" style="'+row+'" onmouseover="this.style.background=\'var(--gray-50)\'" onmouseout="this.style.background=\'\'">'+cEsc(d.name)+'</div>').join('');
      const exact=((j.data)||[]).some(d=>d.name.toLowerCase()===q.toLowerCase())||selectedDiagnoses.some(d=>d.toLowerCase()===q.toLowerCase());
      if(q&&!exact) h+='<div onclick="addNewDiagnosis()" style="'+row+'color:var(--primary-color);font-weight:600;"><i class="fas fa-plus"></i> Add "'+cEsc(q)+'" as new diagnosis</div>';
      list.innerHTML=h||'<div style="padding:8px 12px;font-size:12px;color:var(--gray-400);">No results</div>';
    }catch(e){ list.innerHTML=''; }
  },250);
}

function diagToggle(){
  const dd=document.getElementById('diagnosis-dropdown'), open=dd.style.display!=='none';
  dd.style.display=open?'none':'block';
  if(!open){ const i=document.getElementById('f-final-diagnosis'); i.value=''; searchDiagnoses(); i.focus(); }
}
document.addEventListener('click',e=>{ if(!e.target.isConnected) return; const dd=document.getElementById('diagnosis-dropdown'); if(dd && !e.target.closest('#diag-box') && !e.target.closest('#diagnosis-dropdown')) dd.style.display='none'; });

function selectDiagnosis(name,id){
  if(!selectedDiagnoses.includes(name)){
    selectedDiagnoses.push(name);
    renderDiagnoses();
    checkRecurrence(name);
  }
  const i=document.getElementById('f-final-diagnosis'); i.value=''; i.focus(); // stay open for the next one
  searchDiagnoses();
}

async function addNewDiagnosis(){
  const inp=document.getElementById('f-final-diagnosis');
  const val=inp.value.trim();
  if(!val){alert('Type a diagnosis name first.');return;}
  // Save to DB
  try{
    const r=await fetch(diagnosesAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:val})});
    const j=await r.json();
    if(j.success){
      selectDiagnosis(val,j.data.id);
    }else{alert(j.message);}
  }catch(e){}
}

// ============ SAVE ONE FIELD (1-4) ============
const _sfState={};
async function saveField(id,key,btn){
  const st=document.getElementById('sv-'+id), el=document.getElementById(id), old=btn?btn.innerHTML:'';
  const S=_sfState[id]||(_sfState[id]={saved:null,busy:false,dirty:false});
  if(S.busy){ S.dirty=true; return; }
  S.busy=true; S.dirty=false;
  const val=el.value.trim();
  if(btn){ btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>'; }
  try{
    const r=await fetch(consultAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      visit_id:visitId, patient_id:patientId||window._currentVisit?.patient_id, department_id:cDeptId||window._currentVisit?.department_id, created_by:cUserId,
      doctor_id:cUserId, doctor_name:cUserName,
      [key]:val
    })});
    const j=await r.json();
    st.style.color=j.success?'#059669':'#dc2626';
    st.textContent=j.success?'✓ Saved':(j.message||'Save failed');
    if(j.success){ S.saved=val; setTimeout(()=>{st.textContent='';},3000); }
  }catch(e){ st.style.color='#dc2626'; st.textContent='Error: '+e.message; }
  finally{ if(btn){ btn.disabled=false; btn.innerHTML=old; } S.busy=false; if(S.dirty&&el.value.trim()!==S.saved) saveField(id,key); }
}
// Autosave: debounce on input, immediate on blur; skipped when visit completed
[['f-complaints','chief_complaint'],['f-past-history','history'],['f-physical-exam','examination'],['f-diff-diagnosis','differential_diagnosis']].forEach(([id,key])=>{
  const el=document.getElementById(id); if(!el) return;
  let t; const S=_sfState[id]=_sfState[id]||{saved:null,busy:false,dirty:false};
  const locked=()=>window._currentVisitOrders?.is_completed||window._currentVisit?.status==='completed';
  const go=()=>{ clearTimeout(t); if(locked()) return; if(el.value.trim()!==S.saved) saveField(id,key); };
  el.addEventListener('focus',()=>{ S.saved=el.value.trim(); });
  el.addEventListener('input',()=>{ clearTimeout(t); t=setTimeout(go,1000); });
  el.addEventListener('blur',go);
});

// ============ SAVE MEDICAL PROFILE ============
async function saveMedicalProfile(){
  const blood   = document.getElementById('f-blood-group').value;
  const allerg  = document.getElementById('f-allergies').value.trim();
  const chronic = document.getElementById('f-chronic-diseases').value.trim();
  const status  = document.getElementById('mp-save-status');
  const pid = patientId || window._currentPatient?.id;
  if(!pid) return;
  try{
    const r = await fetch(patientsAPI+'&id='+pid, {
      method:'PATCH',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ blood_group: blood, allergies: allerg, chronic_diseases: chronic })
    });
    const j = await r.json();
    if(j.success){
      status.textContent='✓ Medical profile saved';
      status.style.color='#059669';
      status.style.display='block';
      setTimeout(()=>{ status.style.display='none'; }, 3000);
    }
  }catch(e){ /* non-blocking — consultation save still proceeds */ }
}

// Autosave medical profile so it carries into the patient's next visits until the doctor changes it
['f-blood-group','f-allergies','f-chronic-diseases'].forEach(id=>{
  const el=document.getElementById(id); if(!el) return;
  el.addEventListener('change',saveMedicalProfile);
});

// ============ ADD ROWS ============
function removeLabRow(btn){
  const row=btn.closest('.lab-row');
  const next=row.nextElementSibling;
  if(next && next.classList.contains('lab-ins-row')) next.remove();
  row.remove();
}

function addLabRow(){
  const showIns = cInsuranceId && (cPaymentType==='insurance'||cPaymentType==='mixed');
  const insDisplay = showIns ? '' : 'display:none;';
  const html='<div class="lab-row" style="display:grid;grid-template-columns:2fr 1fr auto;gap:10px;margin-bottom:10px;align-items:end;">'
    +'<div style="position:relative;"><input type="text" class="lab-test-search" placeholder="Search lab test..." autocomplete="off" oninput="labSearchTests(this)" onfocus="labSearchTests(this)" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">'
    +'<input type="hidden" class="lab-test-id" value="">'
    +'<input type="hidden" class="lab-test-price" value="0">'
    +'<div class="lab-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-top:2px;"></div></div>'
    +'<div><select class="lab-priority" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"><option value="normal">Normal</option><option value="urgent">Urgent</option><option value="stat">STAT</option></select></div>'
    +'<button onclick="removeLabRow(this)" style="padding:9px 12px;background:var(--danger-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-times"></i></button>'
    +'</div>'
    +'<div class="lab-ins-row" style="'+insDisplay+'margin-bottom:14px;margin-top:-6px;padding-left:4px;">'
    +'<label style="display:flex;align-items:center;gap:6px;font-size:11px;color:#059669;cursor:pointer;">'
    +'<input type="checkbox" class="lab-item-insurance" checked style="width:14px;height:14px;"> Apply insurance to this test'
    +'</label></div>';
  document.getElementById('lab-items').insertAdjacentHTML('beforeend',html);
}

function finalRxRowHtml(rx){
  rx=rx||{};
  const v=x=>String(x||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
  const inp='style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"';
  const route=['Oral','IV','IM','SC','Topical','Inhalation','Rectal','Other'].map(o=>'<option'+(o===rx.route?' selected':'')+'>'+o+'</option>').join('');
  return '<div class="final-rx-row" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 70px 100px auto;gap:10px;margin-bottom:4px;align-items:end;">'
    +'<div><input type="text" class="final-rx-med" list="final-rx-med-list" placeholder="Type medication name..." value="'+v(rx.medication)+'" '+inp+'></div>'
    +'<div><input type="text" class="final-rx-dose" placeholder="Dosage" value="'+v(rx.dosage)+'" '+inp+'></div>'
    +'<div><input type="text" class="final-rx-freq" placeholder="Frequency" value="'+v(rx.frequency)+'" '+inp+'></div>'
    +'<div><input type="text" class="final-rx-dur" placeholder="Duration" value="'+v(rx.duration)+'" '+inp+'></div>'
    +'<div><input type="number" min="1" class="final-rx-qty" placeholder="Qty" value="'+v(rx.quantity)+'" style="width:100%;padding:9px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>'
    +'<div><select class="final-rx-route" style="width:100%;padding:9px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">'+route+'</select></div>'
    +'<button onclick="this.closest(\'.final-rx-row\').remove()" style="padding:9px 12px;background:var(--danger-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-times"></i></button>'
    +'</div>';
}
function addFinalRxRow(){
  document.getElementById('final-rx-items').insertAdjacentHTML('beforeend',finalRxRowHtml());
}

// Printable take-home prescription for the patient
function printDischargeRx(){
  const rows=[...document.querySelectorAll('#final-rx-items .final-rx-row')].map(r=>({
    med:r.querySelector('.final-rx-med').value.trim(), dose:r.querySelector('.final-rx-dose').value.trim(),
    freq:r.querySelector('.final-rx-freq').value.trim(), dur:r.querySelector('.final-rx-dur').value.trim(),
    qty:r.querySelector('.final-rx-qty').value.trim(), route:r.querySelector('.final-rx-route').value
  })).filter(x=>x.med);
  if(!rows.length) return alert('Add at least one medication first.');
  const p=window._currentPatient||{};
  const w=window.open('','_blank','width=800,height=700');
  w.document.write('<!DOCTYPE html><html><head><title>Discharge Prescription</title><style>body{font-family:Arial,sans-serif;font-size:13px;padding:24px}h2{text-align:center;margin-bottom:10px}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #94a3b8;padding:6px 8px;text-align:left}th{background:#f1f5f9}.sig{margin-top:50px;display:flex;justify-content:space-between}.sig div{border-top:1px solid #64748b;padding-top:4px;width:40%;font-size:12px}</style></head><body>'
    +'<h2>Discharge Prescription (take-home medications)</h2>'
    +'<div><b>'+cEsc((p.first_name||'')+' '+(p.last_name||''))+'</b> &nbsp;|&nbsp; Patient No: '+cEsc(p.patient_no||p.id||'')+' &nbsp;|&nbsp; Gender: '+cEsc(p.gender||'')+' &nbsp;|&nbsp; Date: '+new Date().toLocaleDateString()+'</div>'
    +(selectedDiagnoses.length?'<div style="margin-top:4px;">Diagnosis: '+cEsc(selectedDiagnoses.join(', '))+'</div>':'')
    +'<table><thead><tr><th>#</th><th>Medication</th><th>Dosage</th><th>Route</th><th>Frequency</th><th>Duration</th><th>Qty</th></tr></thead><tbody>'
    +rows.map((x,i)=>'<tr><td>'+(i+1)+'</td><td>'+cEsc(x.med)+'</td><td>'+cEsc(x.dose)+'</td><td>'+cEsc(x.route)+'</td><td>'+cEsc(x.freq)+'</td><td>'+cEsc(x.dur)+'</td><td>'+cEsc(x.qty||'-')+'</td></tr>').join('')
    +'</tbody></table><div class="sig"><div>Prescriber: '+cEsc(cUserName||'')+'</div><div>Signature / stamp</div></div></body></html>');
  w.document.close();
  setTimeout(()=>w.print(),400);
}

function removeFinalRxRow(btn){
  const row=btn.closest('.final-rx-row');
  const next=row.nextElementSibling;
  if(next && next.classList.contains('final-rx-ins-row')) next.remove();
  row.remove();
}

function finalRxDestChanged(sel){
  const row=sel.closest('.final-rx-row');
  const insRow=row.nextElementSibling;
  if(!insRow||!insRow.classList.contains('final-rx-ins-row')) return;
  const showIns=cInsuranceId && (cPaymentType==='insurance'||cPaymentType==='mixed');
  if(sel.value==='hospital' && showIns){
    insRow.style.display='block';
  }else{
    insRow.style.display='none';
  }
}

// ============ BILLING LIST (sidebar): orders are staged, billed only on "Send to Billing" ============
const billQueue = []; // {kind, display[], dupKeys[], run()}  ponytail: in-memory only, lost on reload (warned via beforeunload)
const _billIcons = {consumable:'fa-box-open',pharmacy:'fa-prescription',lab:'fa-flask',procedure:'fa-syringe',imaging:'fa-x-ray'};
function billQueueAdd(kind, display, dupKeys, run, noPrice){
  _markSubmitted(dupKeys);
  billQueue.push({kind, display, dupKeys, run, noPrice:!!noPrice}); // noPrice entries are listed in red and never sent
  billQueueRender();
}
function billQueueRemove(i){
  const e = billQueue.splice(i,1)[0];
  if(e) e.dupKeys.forEach(d => _submittedItems.delete(_dupKey(d)));
  billQueueRender();
}
function _resetOrderForm(boxSel,rowSel,insClass){
  const rows=[...document.querySelectorAll(boxSel+' '+rowSel)];
  rows.slice(1).forEach(r=>{const n=r.nextElementSibling; if(n&&n.classList.contains(insClass)) n.remove(); r.remove();});
  if(rows[0]) rows[0].querySelectorAll('input:not([type=checkbox]),select').forEach(e=>{ if(e.tagName==='SELECT') e.selectedIndex=0; else e.value=e.defaultValue; });
  document.querySelectorAll(boxSel+' .rx-price-info').forEach(e=>e.innerHTML='');
}
function billQueueRender(){
  const pend=document.getElementById('c-bill-pending'), sent=document.getElementById('c-bill-sent'), btn=document.getElementById('btn-bill-send');
  if(!pend) return;
  const row=(kind,txt,x)=>`<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--gray-100);font-size:12px;"><i class="fas ${_billIcons[kind]||'fa-file'}" style="width:14px;color:var(--gray-500);"></i><span style="flex:1;min-width:0;word-break:break-word;">${cEsc(txt)}</span>${x||''}</div>`;
  const rowRed=(kind,txt,x)=>`<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #fecaca;font-size:12px;color:#dc2626;font-weight:600;"><i class="fas ${_billIcons[kind]||'fa-file'}" style="width:14px;"></i><span style="flex:1;min-width:0;word-break:break-word;">${cEsc(txt)}<br><span style="font-weight:400;font-size:11px;">No price, will not be sent</span></span>${x||''}</div>`;
  let h='';
  billQueue.forEach((e,i)=>e.display.forEach(t=>{ h+=(e.noPrice?rowRed:row)(e.kind,t,`<button onclick="billQueueRemove(${i})" title="Remove from list" style="background:none;border:none;color:#dc2626;cursor:pointer;"><i class="fas fa-times"></i></button>`); }));
  pend.innerHTML = h || '<div style="font-size:12px;color:var(--gray-400);padding:6px 0;">Nothing waiting. Add labs, imaging, drugs or procedures.</div>';
  const n = billQueue.reduce((a,e)=>a+(e.noPrice?0:e.display.length),0);
  btn.disabled = !n;
  btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send to Billing ('+n+')';
  const d = window._currentVisitOrders || {};
  const strip = t => String(t||'').replace(/\s*\[[\s\S]*?RWF[\s\S]*?\]/gi,'').replace(/\s*-\s*[\d,]+\s*RWF/gi,'');
  let sh='';
  (d.pharmacy||[]).forEach(it=>sh+=row('pharmacy',it.medication_name+' × '+(it.quantity||1)));
  (d.labs||[]).forEach(it=>sh+=row('lab',strip(it.test_name)));
  (d.procedures||[]).forEach(it=>sh+=row('procedure',strip(it.procedure_name)));
  (d.imaging||[]).forEach(it=>sh+=row('imaging',it.imaging_name));
  sent.innerHTML = sh ? '<div style="font-size:11px;font-weight:700;color:#166534;text-transform:uppercase;margin:14px 0 2px;"><i class="fas fa-check-circle"></i> Already sent to billing</div>'+sh : '';
}
async function billSendAll(){
  if(!billQueue.some(e=>!e.noPrice)) return;
  const btn=document.getElementById('btn-bill-send');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending...';
  const notes=[]; let okCount=0;
  for(const e of billQueue.filter(e=>!e.noPrice)){
    let r; try{ r=await e.run(); }catch(err){ r={ok:false,msg:err.message}; }
    if(r.ok){ billQueue.splice(billQueue.indexOf(e),1); okCount++; }
    if(r.msg) notes.push((r.ok?'⚠ ':'✗ ')+e.kind+': '+r.msg);
  }
  await loadOrdersSummary();
  billQueueRender();
  const skipped=billQueue.filter(e=>e.noPrice).reduce((a,e)=>a+e.display.length,0);
  alert((okCount?'✓ '+okCount+' order group(s) sent to billing.':'Nothing was sent.')+(skipped?'\n\n'+skipped+' item(s) without a price were not sent (shown in red).':'')+(notes.length?'\n\n'+notes.join('\n'):''));
}
window.addEventListener('beforeunload',e=>{ if(billQueue.some(q=>!q.noPrice)){ e.preventDefault(); e.returnValue=''; } });

// ============ COMBINED ORDER SEARCH (medication + procedure + imaging) ============
let _ordSel=[], _ordRes={}, _ordTimer;
const _ordTypes=['med','proc','cons','img','lab','dent'];
const _ordTag={dent:['Dental','#115e59','#ccfbf1'],lab:['Lab','#1e40af','#dbeafe'],med:['Medication','#166534','#dcfce7'],proc:['Procedure','#9d174d','#fce7f3'],cons:['Consumable','#92400e','#fef3c7'],img:['Imaging','#9a3412','#ffedd5']};
const _ordHasIns=()=>cInsuranceId&&(cPaymentType==='insurance'||cPaymentType==='mixed');
function ordSearchDebounce(t){ clearTimeout(_ordTimer); _ordTimer=setTimeout(()=>ordSearch(t),300); }
// insured patient: show the insurer's price; if the insurer has none, show the standard price flagged "Standard"
let _svcIns=null, _svcInsFor=null;
async function loadSvcInsPrices(){
  const cid=cCompanyId||cInsuranceId;
  if(!_ordHasIns()||!cid){ _svcIns={}; return; }
  if(_svcIns&&_svcInsFor===cid) return;
  try{
    const j=await (await fetch(window.location.origin + _cBase + '/api.php?endpoint=service-catalog&action=insurance_prices&insurance_id='+cid)).json();
    _svcIns={}; (j.data||[]).forEach(r=>{ _svcIns[r.service_id]=parseFloat(r.price)||0; }); _svcInsFor=cid;
  }catch(e){ _svcIns={}; }
}
function ordPrice(type,id,standard){
  if(type==='cons'||!_ordHasIns()) return {price:standard,std:false};
  if(type==='med'){ const d=getDrugPrice(id); return {price:d.total,std:!(d.insPrice>0)}; }
  const ins=(_svcIns||{})[id];
  return ins>0 ? {price:ins,std:false} : {price:standard,std:true};
}
const _ordStdTag=std=>std?'<div style="font-size:10px;color:var(--gray-400);line-height:1.1;">Standard</div>':'';
// consumables = active inventory items in the supply categories (same list the nurse screen uses); the server prices them and deducts stock
const _consCats=['nursing supplies','medical consumables','dental supplies','laboratory supplies'];
async function loadConsumables(){
  if(window._consList) return;
  window._consList=[];
  try{
    const j=await (await fetch(inventoryAPI+'&action=items&active=1&limit=500')).json();
    window._consList=(j.success?(j.data.items||j.data||[]):[]).filter(i=>_consCats.includes(String(i.category_name||'').toLowerCase()));
  }catch(e){ window._consList=null; }
}
async function ordSearch(t){
  const q=document.getElementById('ord-search-'+t).value.toLowerCase().trim();
  const dd=document.getElementById('ord-list-'+t);
  await Promise.all([loadMedications(),loadProcServices(),loadImagingServices(),loadSvcInsPrices(),loadConsumables()]);
  if(document.getElementById('ord-search-'+t).value.toLowerCase().trim()!==q) return; // stale
  const meds=(window._medList||[]).filter(i=>(i.name||'').toLowerCase().includes(q)||(i.generic_name||'').toLowerCase().includes(q)).slice(0,15)
    .map(i=>({type:'med',id:String(i.id),name:i.name+(i.generic_name?' ('+i.generic_name+')':''),std:getDrugPrice(i.id).total}));
  const procs=procServiceList.filter(s=>(s.procedure_name||'').toLowerCase().includes(q)||(s.speciality||'').toLowerCase().includes(q)||(s.ichi_code||'').toLowerCase().includes(q)).slice(0,15)
    .map(s=>({type:'proc',id:String(s.id),name:s.procedure_name,std:getServicePrice(s)}));
  const imgs=imagingServiceList.filter(s=>(s.procedure_name||'').toLowerCase().includes(q)||(s.speciality||'').toLowerCase().includes(q)).slice(0,15)
    .map(s=>({type:'img',id:String(s.id),name:s.procedure_name,std:getServicePrice(s)}));
  const conss=(window._consList||[]).filter(i=>(i.name||'').toLowerCase().includes(q)||(i.item_code||'').toLowerCase().includes(q)).slice(0,15)
    .map(i=>({type:'cons',id:String(i.id),name:i.name,std:parseFloat(i.selling_price)||0}));
  const dents=dentalServiceList.filter(s=>(s.procedure_name||'').toLowerCase().includes(q)||(s.ichi_code||'').toLowerCase().includes(q)).slice(0,15)
    .map(s=>({type:'dent',id:String(s.id),name:s.procedure_name,std:getServicePrice(s)}));
  const labs=(labCatalogServices||[]).filter(s=>{ const sp=(s.speciality||'').toLowerCase(); return labSpecialtiesList.some(ls=>sp.includes(ls)) && ((s.procedure_name||'').toLowerCase().includes(q)||sp.includes(q)||(s.ichi_code||'').toLowerCase().includes(q)); }).slice(0,15)
    .map(s=>({type:'lab',id:String(s.id),name:s.procedure_name,std:labGetPrice(s)}));
  const res=_ordRes[t]=({med:meds,proc:procs,cons:conss,img:imgs,lab:labs,dent:dents})[t].filter(r=>!_ordSel.some(x=>x.type===r.type&&x.id===r.id)).map(r=>Object.assign(r,ordPrice(r.type,r.id,r.std)));
  if(!res.length){ dd.innerHTML='<div style="padding:10px;color:var(--gray-400);font-size:12px;">Nothing found</div>'; return; }
  dd.innerHTML=res.map((r,i)=>{
    const on=_ordSel.some(x=>x.type===r.type&&x.id===r.id);
    return `<div onclick="ordPick('${t}',${i})" style="display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--gray-100);${on?'background:#f0fdf4;':''}" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='${on?'#f0fdf4':''}'">
      <span style="flex:1;min-width:0;font-weight:500;">${cEsc(r.name)}</span>
      <span style="font-size:12px;color:var(--gray-600);white-space:nowrap;text-align:right;">${r.price?Number(r.price).toLocaleString()+' RWF':'—'}${_ordStdTag(r.std)}</span>
    </div>`;
  }).join('');
}
async function ordPick(t,i){
  const r=(_ordRes[t]||[])[i]; if(!r) return;
  if(_ordSel.some(x=>x.type===r.type&&x.id===r.id)) return; // already selected, no duplicate
  const k=_dupKey(r.name), ex=window._existingVisitItems||{};
  const exists = r.type==='dent'||r.type==='cons' ? false /* same service on another tooth is valid; same tooth is caught by _checkDups */ : r.type==='lab' ? (ex.labIds&&ex.labIds.has(r.id)) || (ex.labNames&&ex.labNames.has(k))
    : r.type==='med' ? (ex.pharmacyIds&&ex.pharmacyIds.has(r.id)) || (ex.pharmacyNames&&ex.pharmacyNames.has(k))
    : r.type==='proc' ? (ex.procIds&&ex.procIds.has(r.id)) || (ex.procNames&&ex.procNames.has(k))
    : ex.imgNames&&ex.imgNames.has(k);
  if(exists){ alert('"'+r.name+'" is already added for this visit. Edit the quantity in the Orders list instead.'); return; }
  const it=Object.assign({qty:1,dose:'',freq:'',dur:'',note:'',bodyPart:'',tooth:'',priority:'normal',applyIns:true,stock:''},r);
  _ordSel.push(it);
  const si=document.getElementById('ord-search-'+t); si.value=''; si.focus(); // clear + refocus: ready to search the next one
  ordRender(); ordRefreshDropdown();
  if(it.type==='med') ordStockNote(it);
  else if(it.type!=='lab' && it.type!=='cons' && _ordHasIns()){ // insurer agreed price, same lookup the old forms used
    try{
      const j=await (await fetch(window.location.origin + _cBase + `/api.php?endpoint=service-catalog&action=insurance_price&service_id=${it.id}&insurance_id=${cCompanyId||cInsuranceId}`)).json();
      if(j.success && j.data && j.data.price){ it.price=parseFloat(j.data.price)||it.price; ordRender(); }
    }catch(e){}
  }
}
function ordToggle(t){
  const dd=document.getElementById('ord-dropdown-'+t), open=dd.style.display!=='none';
  dd.style.display=open?'none':'block';
  if(!open){ const i=document.getElementById('ord-search-'+t); i.value=''; ordSearch(t); i.focus(); }
}
function ordRefreshDropdown(){ _ordTypes.forEach(t=>{ if(document.getElementById('ord-dropdown-'+t).style.display!=='none') ordSearch(t); }); }
function ordRemove(i){ _ordSel.splice(i,1); ordRender(); ordRefreshDropdown(); }
function ordSet(i,f,v){ if(_ordSel[i]) _ordSel[i][f]=v; }
async function ordStockNote(it){
  it.stock='';
  try{
    const j=await (await fetch(consultAPI+'&action=stock_warning&item_id='+encodeURIComponent(it.id)+'&qty='+(parseInt(it.qty)||1))).json();
    if(j.success && j.data && j.data.warning) it.stock=j.data.warning;
  }catch(e){}
  const el=document.getElementById('ord-stock-'+_ordSel.indexOf(it));
  if(el) el.innerHTML=it.stock?'<i class="fas fa-exclamation-triangle"></i> '+cEsc(it.stock)+' (you can still prescribe)':'';
}
function ordQty(i,v){ const it=_ordSel[i]; if(!it) return; it.qty=v; if(it.type==='med'){ clearTimeout(it._st); it._st=setTimeout(()=>ordStockNote(it),350); } }
function ordRender(){ _ordTypes.forEach(ordRenderType); }
function ordRenderType(ty){
  document.getElementById('ord-ph-'+ty).style.display=_ordSel.some(x=>x.type===ty)?'none':'';
  const box=document.getElementById('ord-selected-'+ty);
  document.getElementById('ord-chips-'+ty).innerHTML=_ordSel.map((x,i)=>x.type!==ty?'':`<span style="display:inline-flex;align-items:center;gap:6px;padding:3px 9px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:5px;font-size:13px;font-weight:600;color:var(--gray-700);">${cEsc(x.name)}<i class="fas fa-times" onclick="event.stopPropagation();ordRemove(${i})" title="Remove" style="cursor:pointer;color:#9ca3af;font-size:11px;"></i></span>`).join('');
  if(!_ordSel.some(x=>x.type===ty)){ box.innerHTML=''; return; }
  const inp='style="width:100%;padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"';
  const hasIns=_ordHasIns();
  box.innerHTML=_ordSel.map((x,i)=>{
    if(x.type!==ty) return '';
    const t=_ordTag[x.type];
    let body='';
    if(x.type==='med'){
      body=`<div style="display:grid;grid-template-columns:1fr 70px;gap:8px;margin-top:8px;">
        <input type="text" placeholder="Dosage e.g. 500mg" value="${cEsc(x.dose)}" oninput="ordSet(${i},'dose',this.value)" ${inp}>
        <input type="number" min="1" placeholder="Qty" value="${x.qty}" oninput="ordQty(${i},this.value)" ${inp}>
      </div>
      <div id="ord-stock-${i}" style="font-size:11px;margin-top:3px;color:#b45309;">${x.stock?'<i class="fas fa-exclamation-triangle"></i> '+cEsc(x.stock)+' (you can still prescribe)':''}</div>
      ${hasIns?`<label style="display:flex;align-items:center;gap:6px;font-size:11px;color:#059669;cursor:pointer;margin-top:4px;"><input type="checkbox" ${x.applyIns?'checked':''} onchange="ordSet(${i},'applyIns',this.checked)" style="width:14px;height:14px;"> Apply insurance to this item</label>`:''}`;
    } else if(x.type==='proc'){
      body=`<div style="display:grid;grid-template-columns:1fr 70px;gap:8px;margin-top:8px;">
        <input type="text" placeholder="Optional notes" value="${cEsc(x.note)}" oninput="ordSet(${i},'note',this.value)" ${inp}>
        <input type="number" min="1" placeholder="Qty" value="${x.qty}" oninput="ordSet(${i},'qty',this.value)" ${inp}>
      </div>`;
    } else if(x.type==='cons'){
      body=`<div style="margin-top:8px;display:flex;align-items:center;gap:14px;"><input type="number" min="1" placeholder="Qty" value="${x.qty}" oninput="ordSet(${i},'qty',this.value)" style="width:90px;padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-700);cursor:pointer;"><input type="checkbox" ${x.bill===false?'':'checked'} onchange="ordSet(${i},'bill',this.checked)" style="width:14px;height:14px;"> Bill patient</label></div>`;
    } else if(x.type==='dent'){
      body=`<div style="display:grid;grid-template-columns:1fr 1fr 70px;gap:8px;margin-top:8px;">
        <input type="text" placeholder="Tooth / quadrant / arch e.g. 46, UR, upper *" value="${cEsc(x.tooth)}" oninput="ordSet(${i},'tooth',this.value)" ${inp}>
        <input type="text" placeholder="Optional notes" value="${cEsc(x.note)}" oninput="ordSet(${i},'note',this.value)" ${inp}>
        <input type="number" min="1" placeholder="Qty" value="${x.qty}" oninput="ordSet(${i},'qty',this.value)" ${inp}>
      </div>`;
    } else if(x.type==='img'){
      body=`<div style="margin-top:8px;"><input type="text" placeholder="Body part e.g. Chest (optional)" value="${cEsc(x.bodyPart)}" oninput="ordSet(${i},'bodyPart',this.value)" ${inp}></div>`;
    }
    if(x.type==='lab'){
      if(!hasIns) return '';
      body=`<label style="display:flex;align-items:center;gap:6px;font-size:11px;color:#059669;cursor:pointer;margin-top:4px;"><input type="checkbox" ${x.applyIns?'checked':''} onchange="ordSet(${i},'applyIns',this.checked)" style="width:14px;height:14px;"> Apply insurance to this test</label>`;
    }
    return `<div style="border:1px solid var(--gray-200);border-radius:8px;padding:10px 12px;margin-bottom:8px;">
      <div style="display:flex;align-items:center;gap:8px;">
        <span style="background:${t[2]};color:${t[1]};padding:1px 8px;border-radius:10px;font-size:10px;font-weight:700;">${t[0]}</span>
        <span style="flex:1;min-width:0;font-weight:600;font-size:13px;">${cEsc(x.name)}</span>
        <span style="font-size:12px;color:var(--gray-600);white-space:nowrap;text-align:right;">${x.price?Number(x.price).toLocaleString()+' RWF':'—'}${_ordStdTag(x.std)}</span>
      </div>${body}</div>`;
  }).join('');
}
document.addEventListener('click',e=>{ if(!e.target.isConnected) return; /* clicked row was re-rendered away: still inside */ _ordTypes.forEach(t=>{ const dd=document.getElementById('ord-dropdown-'+t); if(dd && !e.target.closest('#ord-box-'+t) && !e.target.closest('#ord-dropdown-'+t)) dd.style.display='none'; }); });

// ============ SUBMIT COMBINED ORDER ============
async function submitCombinedOrder(ty){
  if(ty==='lab') return submitLabOrder();
  const dents=_ordSel.filter(x=>ty==='dent'&&x.type==='dent');
  if(dents.some(d=>!String(d.tooth).trim())) return alert('Enter the tooth / quadrant / arch for each dental service.');
  // dental = a procedure order with the tooth in its description, so billing stays on the one procedure path
  const meds=_ordSel.filter(x=>ty==='med'&&x.type==='med'), procs=_ordSel.filter(x=>ty==='proc'&&x.type==='proc').concat(dents.map(d=>Object.assign({},d,{note:'Tooth '+String(d.tooth).trim()+(d.note.trim()?' — '+d.note.trim():'')}))), imgs=_ordSel.filter(x=>ty==='img'&&x.type==='img');
  const conss=_ordSel.filter(x=>ty==='cons'&&x.type==='cons');
  if(!meds.length&&!procs.length&&!imgs.length&&!conss.length) return alert('Select at least one item first.');
  const medDescs=meds.map(m=>'Medication — '+m.name.split(' (')[0].trim());
  const procDescs=procs.map(p=>p.name+(p.note?' — '+p.note:''));
  const imgDescs=imgs.map(i=>i.name+(i.bodyPart?' ('+i.bodyPart+')':''));
  if(!_checkDups(medDescs.concat(procDescs,imgDescs))) return;
  let invoiceNote = '';
  try { invoiceNote = await invoiceNoteModal('Orders — Add to Billing List'); }
  catch(e) { return; }
  const notes=document.getElementById('ord-notes-'+ty).value.trim();
  const hasIns=_ordHasIns();
  if(meds.length) _ordQueueMeds(meds,invoiceNote,notes);
  if(procs.length) _ordQueueProcs(procs,invoiceNote,notes,hasIns);
  if(imgs.length) _ordQueueImgs(imgs,invoiceNote,notes,hasIns);
  if(conss.length) _ordQueueCons(conss,notes);
  _ordSel=_ordSel.filter(x=>x.type!==ty); ordRender(); ordRefreshDropdown();
  document.getElementById('ord-notes-'+ty).value='';
}
// Consumables used in the consultation: on "Send to Billing" the server deducts stock (OPD/IPD store) and bills them (insurer price if covered)
function _ordQueueCons(conss,notes){
  const qtyOf=c=>Math.max(1,parseInt(c.qty)||1), items=conss.map(c=>({item_id:c.id,quantity:qtyOf(c),bill:c.bill!==false}));
  billQueueAdd('consumable', conss.map(c=>c.name+' × '+qtyOf(c)+(c.bill===false?' (stock only)':'')), [], async()=>{
    const j=await fetch(window.location.origin + _cBase + '/api.php?endpoint=procedures/procedures&action=nurse_use',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({visit_id:visitId,patient_id:patientId,items,kind:'consumable',summary:'Consultation consumables: '+conss.map(c=>c.name+' x'+qtyOf(c)).join(', '),notes})}).then(x=>x.json());
    return j.success ? {ok:true,msg:''} : {ok:false,msg:j.message||'Error'};
  },false);
}
function _ordQueueMeds(meds,invoiceNote,rxNotes){
  const items=[], invoiceItems=[], display=[];
  meds.forEach(m=>{
    const qty=Math.max(1,parseInt(m.qty)||1), name=m.name.split(' (')[0].trim();
    const pricing=getDrugPrice(m.id);
    const applyIns=!!_ordHasIns()&&m.applyIns;
    const unitPrice=applyIns?pricing.total:(parseFloat((window._medById||{})[m.id]?.selling_price)||0);
    items.push({item_id:m.id, medication:name, dosage:m.dose.trim(), frequency:'', duration:'', quantity:qty});
    display.push(name+' × '+qty);
    invoiceItems.push({
      description:'Medication — '+name+(pricing.topup>0?' (Topup: '+pricing.topup.toLocaleString()+' RWF)':''),
      category:'pharmacy', quantity:qty, unit_price:unitPrice, discount:0, item_id:m.id,
      apply_insurance:applyIns, insurance_price:pricing.insPrice, patient_topup:pricing.topup
    });
  });
  const rxDescs=invoiceItems.map(it=>it.description);
  const queueSub=(items,invoiceItems,display,rxDescs,np)=>{ if(!items.length) return;
  const rxApplyIns = invoiceItems.some(it=>it.apply_insurance) && cInsuranceId && (cPaymentType==='insurance'||cPaymentType==='mixed');
  billQueueAdd('pharmacy', display, rxDescs, np?null:async()=>{
    const rxBody={action:'pharmacy_order', visit_id:visitId, patient_id:patientId, department_id:cDeptId, items:items, notes:rxNotes};
    const sendRx=()=>fetch(consultAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(rxBody)}).then(x=>x.json());
    let j=await sendRx();
    if(!j.success && j.code==='allergy_conflict'){
      const why=prompt(j.message+' Type the reason to continue anyway (or Cancel to keep it in the list):','');
      if(!why||!why.trim()) return {ok:false,msg:'allergy conflict, not sent'};
      rxBody.allergy_override_reason=why.trim(); j=await sendRx();
    }
    if(!j.success) return {ok:false,msg:j.message||'Error'};
    const invJson=await fetch(billingAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      visit_id:visitId, patient_id:patientId, department_id:cDeptId,
      payment_type: rxApplyIns ? cPaymentType : 'cash',
      insurance_id: rxApplyIns ? cInsuranceId : null,
      copayment_percentage: rxApplyIns ? cCopayPct : 100,
      notes: invoiceNote || 'Prescription',
      items:invoiceItems
    })}).then(x=>x.json());
    const stockW=((j.data&&j.data.warnings)||[]).join('; ');
    if(!invJson.success) return {ok:true,msg:'prescription sent but invoice failed: '+(invJson.message||'')};
    return {ok:true,msg:stockW?'stock warning: '+stockW:''};
  },np);
  };
  const idx=invoiceItems.map((_,i)=>i), okI=idx.filter(i=>invoiceItems[i].unit_price>0), badI=idx.filter(i=>!(invoiceItems[i].unit_price>0));
  const sub=ix=>[ix.map(i=>items[i]),ix.map(i=>invoiceItems[i]),ix.map(i=>display[i]),ix.map(i=>rxDescs[i])];
  queueSub(...sub(okI),false); queueSub(...sub(badI),true);
}
function _ordQueueProcs(procs,invoiceNote,procNotes,hasIns){
  const all=procs.map(p=>({service_id:p.id,name:p.name,note:p.note.trim(),price:parseFloat(p.price)||0,qty:Math.max(1,parseInt(p.qty)||1),dent:p.type==='dent'?{tooth:String(p.tooth).trim(),planItemId:p.planItemId||0}:null}));
  const queueSub=(items,np)=>{ if(!items.length) return;
  const procDescs=items.map(i => i.name+(i.note?' — '+i.note:''));
  billQueueAdd('procedure', items.map(i=>i.name+(i.qty>1?' × '+i.qty:'')), procDescs, np?null:async()=>{
    const j=await fetch(window.location.origin + _cBase + '/api.php?endpoint=patients/billing',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      visit_id:visitId,
      patient_id:patientId,
      payment_type: hasIns ? cPaymentType : 'self_pay',
      insurance_id: hasIns ? cInsuranceId : null,
      copayment_percentage: hasIns ? cCopayPct : 100,
      items:items.map(i=>({
        service_id:i.service_id,
        description:i.name+(i.note?' — '+i.note:''),
        category:'procedure',
        quantity:i.qty,
        unit_price:i.price,
        discount:0,
        total:i.price*i.qty
      })),
      notes: invoiceNote || procNotes
    })}).then(x=>x.json());
    if(j.success) for(const i of items.filter(x=>x.dent)) await dentPost({action:'dental_order_link',service_id:i.service_id,tooth:i.dent.tooth,plan_item_id:i.dent.planItemId,price:i.price});
    if(j.success) dentLoad();
    return j.success ? {ok:true,msg:''} : {ok:false,msg:j.message||'Error'};
  },np);
  };
  queueSub(all.filter(i=>i.price>0),false); queueSub(all.filter(i=>!(i.price>0)),true);
}
function _ordQueueImgs(imgs,invoiceNote,imgNotes,hasIns){
  const all=imgs.map(i=>({service_id:i.id,name:i.name,body_part:i.bodyPart.trim(),priority:i.priority,price:parseFloat(i.price)||0}));
  const queueSub=(items,np)=>{ if(!items.length) return;
  const imgDescs=items.map(i => i.name+(i.body_part?' ('+i.body_part+')':''));
  billQueueAdd('imaging', items.map(i=>i.name), imgDescs, np?null:async()=>{
    const j=await fetch(window.location.origin + _cBase + '/api.php?endpoint=patients/billing',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      visit_id:visitId,
      patient_id:patientId,
      payment_type: hasIns ? cPaymentType : 'self_pay',
      insurance_id: hasIns ? (cCompanyId || cInsuranceId) : null,
      copayment_percentage: hasIns ? cCopayPct : 100,
      items:items.map(i=>({service_id:parseInt(i.service_id)||null,description:i.name+(i.body_part?' ('+i.body_part+')':''),category:'radiology',quantity:1,unit_price:i.price,discount:0,total:i.price})),
      notes: invoiceNote || imgNotes
    })}).then(x=>x.json());
    if(!j.success) return {ok:false,msg:j.message||'Error'};
    try {
      await fetch(consultAPI, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
        action:'imaging_order', visit_id:visitId, patient_id:patientId, department_id:cDeptId,
        items: items.map(i => ({name:i.name, body_part:i.body_part, priority:i.priority, notes: invoiceNote || imgNotes}))
      })});
    } catch(moErr){ console.error('Failed to create medical_order for imaging:', moErr); }
    return {ok:true,msg:''};
  },np);
  };
  queueSub(all.filter(i=>i.price>0),false); queueSub(all.filter(i=>!(i.price>0)),true);
}

// ============ SUBMIT LAB ORDER ============
async function submitLabOrder(){
  const hasInsNow=_ordHasIns();
  const rowData=_ordSel.filter(x=>x.type==='lab').map(x=>({id:x.id, name:x.name, basePrice:parseFloat(x.price)||0, applyIns:!!(hasInsNow&&x.applyIns), priority:'normal'}));
  if(!rowData.length) return alert('Add at least one test.');
  const labNames = rowData.map(t => 'Lab Test — '+t.name);
  if(!_checkDups(labNames)) return;
  let invoiceNote = '';
  try { invoiceNote = await invoiceNoteModal('Lab — Add to Billing List'); }
  catch(e) { return; }
  const labNotes=document.getElementById('ord-notes-lab').value.trim();
  const queueSub=(rowData,labNames,np)=>{ if(!rowData.length) return;
  if(np){ // zero-price tests: straight to the lab, never billed
    _markSubmitted(labNames);
    fetch(consultAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'lab_order', visit_id:visitId, patient_id:patientId, department_id:cDeptId, tests:rowData.map(t=>({service_id:t.id, test_name:t.name, priority:t.priority})), notes:labNotes})}).then(x=>x.json())
      .then(j=>{ if(j.success){ loadOrdersSummary(); } else { labNames.forEach(d=>_submittedItems.delete(_dupKey(d))); alert('Lab: '+(j.message||'Error')); } })
      .catch(e=>{ labNames.forEach(d=>_submittedItems.delete(_dupKey(d))); alert('Lab: '+e.message); });
    return;
  }
  const tests=rowData.map(t=>({service_id:t.id, test_name:t.name, priority:t.priority}));
  const labApplyIns = rowData.some(t=>t.applyIns) && cInsuranceId && (cPaymentType==='insurance'||cPaymentType==='mixed');
  billQueueAdd('lab', rowData.map(t=>t.name), labNames, np?null:async()=>{
    const insPriceMap={};
    if(labApplyIns){
      await Promise.all(rowData.filter(t=>t.applyIns).map(async t=>{
        try{
          const j=await (await fetch(window.location.origin + _cBase + `/api.php?endpoint=service-catalog&action=insurance_price&service_id=${t.id}&insurance_id=${cCompanyId||cInsuranceId}`)).json();
          if(j.success && j.data) insPriceMap[t.id]=j.data;
        }catch(e){}
      }));
    }
    const copayRate = cCopayPct/100;
    const invoiceItems=rowData.map(t=>{
      const insRow = insPriceMap[t.id]||null;
      const usePrice = (labApplyIns && t.applyIns && insRow) ? parseFloat(insRow.price)||t.basePrice : t.basePrice;
      const insPays = (labApplyIns && t.applyIns) ? Math.round(usePrice * (1-copayRate)) : 0;
      const patPays = (labApplyIns && t.applyIns) ? usePrice - insPays : usePrice;
      let desc = 'Lab Test — '+t.name;
      if(labApplyIns && t.applyIns){
        desc += insRow
          ? ` [Ins. agreed: ${usePrice.toLocaleString()} RWF | Ins. pays: ${insPays.toLocaleString()} | Pt. pays: ${patPays.toLocaleString()}]`
          : ` [No ins. price — facility rate used | Ins. pays: ${insPays.toLocaleString()} | Pt. pays: ${patPays.toLocaleString()}]`;
      }
      return {service_id:parseInt(t.id)||null, description:desc, category:'lab', quantity:1, unit_price:usePrice, discount:0, apply_insurance:t.applyIns};
    });
    const j=await fetch(consultAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      action:'lab_order', visit_id:visitId, patient_id:patientId, department_id:cDeptId, tests:tests, notes:labNotes
    })}).then(x=>x.json());
    if(!j.success) return {ok:false,msg:j.message||'Error'};
    const invJson=await fetch(billingAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      visit_id:visitId, patient_id:patientId, department_id:cDeptId,
      payment_type: labApplyIns ? cPaymentType : 'cash',
      insurance_id: labApplyIns ? cInsuranceId : null,
      copayment_percentage: labApplyIns ? cCopayPct : 100,
      notes: invoiceNote || 'Lab Tests ordered',
      items:invoiceItems
    })}).then(x=>x.json());
    return {ok:true,msg:invJson.success?'':'lab ordered but invoice failed: '+(invJson.message||'')};
  },np);
  };
  const okR=rowData.filter(t=>t.basePrice>0), badR=rowData.filter(t=>!(t.basePrice>0));
  queueSub(okR,okR.map(t=>'Lab Test — '+t.name),false); queueSub(badR,badR.map(t=>'Lab Test — '+t.name),true);
  _ordSel=_ordSel.filter(x=>x.type!=='lab'); ordRender(); ordRefreshDropdown();
  document.getElementById('ord-notes-lab').value='';
}

// ============ PREVIOUS VISITS ============
function pvClose(){ document.getElementById('pv-modal').style.display='none'; }
// View button in the Previous Visits tab: one visit, no card strip, same renderer (no billing in it)
function pvView(i){
  document.getElementById('pv-modal').style.display='flex';
  document.getElementById('pv-cards').style.display='none';
  window._pvList=window._pvTab; pvShow(i);
}
let _pvSel=-1;
async function pvShow(i){
  const c=window._pvList[i], det=document.getElementById('pv-detail');
  _pvSel=i;
  document.querySelectorAll('.pv-card').forEach(el=>el.style.borderColor=el.dataset.i==i?'var(--primary-color)':'var(--gray-200)');
  det.innerHTML='<span style="color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</span>';
  const vid=c.visit_id, get=u=>fetch(u).then(r=>r.json()).then(r=>r.data||null).catch(()=>null);
  const [cons,vit,ord,lab,img]=await Promise.all([
    get(consultAPI+'&action=consultation&visit_id='+vid), get(vitalsAPI+'&action=records&visit_id='+vid),
    get(consultAPI+'&action=visit_orders_summary&visit_id='+vid), get(consultAPI+'&action=lab_results&visit_id='+vid),
    get(consultAPI+'&action=imaging_results&visit_id='+vid)]);
  if(_pvSel!==i) return; // user switched card meanwhile
  const k=(cons&&cons[0])||{};
  const txt=t=>cEsc(t).replace(/\n/g,'<br>');
  const sec=(title,body)=>body?`<div style="margin-bottom:16px;"><div style="font-size:12px;font-weight:700;color:var(--gray-500);text-transform:uppercase;margin-bottom:4px;">${title}</div><div style="font-size:13px;color:var(--gray-800);">${body}</div></div>`:'';
  const ul=a=>a.length?'<ul style="margin:0;padding-left:18px;">'+a.map(x=>'<li>'+x+'</li>').join('')+'</ul>':'';
  const strip=t=>String(t||'').replace(/\s*\[[\s\S]*?RWF[\s\S]*?\]/gi,'').replace(/\s*-\s*[\d,]+\s*RWF/gi,'');
  let rx=[]; try{ rx=JSON.parse(k.prescription||'[]'); }catch(e){}
  det.innerHTML=
    `<div style="font-size:13px;color:var(--gray-600);margin-bottom:14px;"><strong>${cEsc(c.visit_no||'')}</strong> · ${cEsc(String(c.visit_date||c.created_at||'').substring(0,10))} · ${cEsc(c.department_name||'')} · Dr. ${cEsc(c.doctor_name||'—')}</div>`+
    sec('Chief complaints',txt(k.chief_complaint))+sec('Past medical history',txt(k.history))+sec('Physical examination',txt(k.examination))+
    sec('Vital signs',(Array.isArray(vit)?vit:[]).map(v=>`<span style="display:inline-block;margin:0 14px 4px 0;"><strong>${cEsc(v.sign_name||'')}</strong>: ${cEsc(v.value)} ${cEsc(v.unit||'')}</span>`).join(''))+
    sec('Differential diagnosis',txt(k.differential_diagnosis))+sec('Final diagnosis',txt(k.diagnosis))+
    sec('Lab tests & results',ul((Array.isArray(lab)?lab:[]).map(l=>cEsc(strip(l.test_name))+(l.result?' — <span style="white-space:pre-wrap;">'+cEsc(l.result)+'</span>':' <em style="color:var(--gray-400);">(no result)</em>'))))+
    sec('Imaging & results',ul((Array.isArray(img)?img:[]).map(l=>cEsc(l.imaging_name)+(l.result?' — <span style="white-space:pre-wrap;">'+cEsc(l.result)+'</span>':' <em style="color:var(--gray-400);">(no result)</em>'))))+
    sec('Procedures',ul(((ord&&ord.procedures)||[]).map(p=>cEsc(strip(p.procedure_name))+(p.quantity>1?' × '+p.quantity:''))))+
    sec('Medications given in visit',ul(((ord&&ord.pharmacy)||[]).map(p=>cEsc(p.medication_name)+' — '+cEsc(p.dosage||''))))+
    sec('Discharge prescription',ul((Array.isArray(rx)?rx:[]).map(p=>cEsc(p.medication)+' — '+cEsc([p.dosage,p.frequency,p.duration].filter(Boolean).join(', ')))))+
    sec('Management & follow up',txt(k.treatment_plan));
}

// ============ SUBMIT ADMIT ============
async function submitAdmitOrder(){
  const btn=document.getElementById('btn-admit');
  const reason=document.getElementById('admit-reason').value.trim();
  if(!reason) return alert('Please enter a reason for admission.');
  if(btn.dataset.submitting === '1') return;

  btn.dataset.submitting = '1';
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending...';
  try{
    const r=await fetch(window.location.origin + _cBase + '/api.php?endpoint=ward/ward-management&action=request_admission',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      patient_id:patientId, visit_id:visitId, diagnosis:reason,
      admission_type:document.getElementById('admit-type').value,
      ward_preference:document.getElementById('admit-ward-pref').value,
      requested_by: window._sessionUserId || null
    })});
    const j=await r.json();
    if(j.success){
      showAdmitAlreadySent('Admission request sent successfully. Ward staff will assign a bed shortly.');
    } else {
      if ((j.message || '').toLowerCase().includes('already') || (j.message || '').toLowerCase().includes('pending')) {
        showAdmitAlreadySent('An admission request for this patient is already pending ward assignment.');
      } else {
        alert('Failed: '+(j.message||'Error'));
        btn.dataset.submitting = '0'; btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Admission Request';
      }
    }
  }catch(e){
    alert('Error: '+e.message);
    btn.dataset.submitting = '0'; btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Admission Request';
  }
}

function showAdmitAlreadySent(msg){
  const banner = document.getElementById('admit-already-sent');
  const form   = document.getElementById('admit-form-body');
  const detail = document.getElementById('admit-already-detail');
  if(banner) { banner.style.display='block'; }
  if(form)   { form.style.display='none'; }
  if(detail && msg) { detail.textContent = msg; }
  // Also update the order button to show admitted state
  const admBtn = document.getElementById('ord-btn-admit');
  if(admBtn){
    admBtn.style.background='#f0fdf4';
    admBtn.style.borderColor='#86efac';
    admBtn.innerHTML='<i class="fas fa-check-circle" style="font-size:20px;color:#16a34a;display:block;margin-bottom:4px;"></i><div style="font-size:14px;font-weight:600;color:#166534;">Admission Sent</div><div style="font-size:12px;color:#15803d;margin-top:4px;">Request pending</div>';
  }
}

async function checkExistingAdmission(){
  if(!patientId || !visitId) return;
  try{
    const r = await fetch(window.location.origin + _cBase + '/api.php?endpoint=ward/ward-management&action=pending_admissions');
    const j = await r.json();
    if(j.success && j.data){
      const existing = j.data.find(a => a.patient_id == patientId && (a.visit_id == visitId || !a.visit_id));
      if(existing){
        showAdmitAlreadySent('Admission request already pending — Diagnosis: ' + (existing.diagnosis||'—') + '. Ward staff will assign a bed.');
      }
    }
  }catch(e){ /* non-blocking */ }
}

// ============ PROCEDURE ORDER ============
let procServiceList=[];
let hospitalFacilityCategory='primary'; // loaded from settings

async function loadHospitalCategory(){
  try{
    const r=await fetch(window.location.origin + _cBase + '/api.php?endpoint=settings/hospital');
    const j=await r.json();
    if(j.success && j.data){
      const settings=j.data;
      if(Array.isArray(settings)){
        const cat=settings.find(s=>s.setting_key==='facility_category');
        if(cat) hospitalFacilityCategory=cat.setting_value||'primary';
      } else if(settings.facility_category){
        hospitalFacilityCategory=settings.facility_category;
      }
    }
  }catch(e){}
}
function getServicePrice(service){
  if(hospitalFacilityCategory==='tertiary') return parseFloat(service.tertiary_health_facility||0);
  if(hospitalFacilityCategory==='secondary') return parseFloat(service.secondary_health_facility||0);
  return parseFloat(service.primary_health_facility||0);
}

let dentalServiceList=[];
const procExcludedSpecialties = [
  'dentist', // Dentistry services are ordered under 10. Dental (Dental Clinic visits only)
  'bacteriology', 'biochemistry', 'hematology', 'immuno-serology', 
  'parasitology', 'molecular', 'anatomical pathology', 'blood transfusion'
];

async function loadProcServices(){
  if(procServiceList.length) return;
  await loadHospitalCategory();
  try{
    const r=await fetch(window.location.origin + _cBase + '/api.php?endpoint=service-catalog&category=procedure&visit_id='+visitId);
    const j=await r.json();
    const all=j.data||[];
    dentalServiceList=all.filter(s=>(s.speciality||'').toLowerCase().startsWith('dentist'));
    procServiceList=all.filter(s => {
      const spec = (s.speciality||'').toLowerCase();
      if(spec.startsWith('imaging')) return false;
      return !procExcludedSpecialties.some(es => spec.includes(es));
    });
  }catch(e){procServiceList=[];}
}
// ============ DENTAL: tooth chart + treatment plans ============
const _dentConds={healthy:'Healthy',cavity:'Cavity',filled:'Filled',crown:'Crown',bridge:'Bridge',implant:'Implant',missing:'Missing',root_canal:'Root canal',extraction_needed:'Extraction needed',fractured:'Fractured',impacted:'Impacted'};
const _dentCondColor={healthy:'#16a34a',cavity:'#d97706',filled:'#2563eb',crown:'#7c3aed',bridge:'#7c3aed',implant:'#0891b2',missing:'#6b7280',root_canal:'#db2777',extraction_needed:'#dc2626',fractured:'#dc2626',impacted:'#b45309'};
const _dentPid=()=>patientId||(window._currentVisit&&window._currentVisit.patient_id)||0;
let _dentData={records:[],plans:[]}, _dentDraft=[], _dentInit=false;
function dentTeeth(){
  const r=(a,b)=>{const o=[];for(let i=a;i<=b;i++)o.push(i);return o;};
  const adult=[...r(11,18).reverse(),...r(21,28),...r(31,38).reverse(),...r(41,48)];
  const baby=[...r(51,55).reverse(),...r(61,65),...r(71,75).reverse(),...r(81,85)];
  return '<optgroup label="Adult">'+adult.map(t=>`<option>${t}</option>`).join('')+'</optgroup><optgroup label="Baby">'+baby.map(t=>`<option>${t}</option>`).join('')+'</optgroup>';
}
async function dentInit(){
  if(_dentInit) return; _dentInit=true;
  document.getElementById('dent-tooth').innerHTML=dentTeeth();
  document.getElementById('dent-cond').innerHTML=Object.entries(_dentConds).map(([k,v])=>`<option value="${k}">${v}</option>`).join('');
  await Promise.all([loadProcServices(),loadSvcInsPrices()]);
  document.getElementById('dent-plan-svc').innerHTML=dentalServiceList.map(s=>`<option value="${s.id}">${cEsc(s.procedure_name)}</option>`).join('');
  dentLoad();
}
async function dentLoad(){
  try{ const j=await (await fetch(consultAPI+'&action=dental_data&patient_id='+_dentPid())).json(); if(j.success) _dentData=j.data; }catch(e){}
  dentRender();
}
async function dentPost(body){
  const j=await fetch(consultAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({visit_id:visitId,patient_id:_dentPid()},body))}).then(x=>x.json());
  if(!j.success) alert(j.message||'Error');
  return j.success?(j||true):false;
}
async function dentSaveTooth(){
  const ok=await dentPost({action:'dental_tooth_save',tooth:document.getElementById('dent-tooth').value,surface:document.getElementById('dent-surface').value,cond:document.getElementById('dent-cond').value,notes:document.getElementById('dent-tnotes').value});
  if(ok){ document.getElementById('dent-tnotes').value=''; dentLoad(); }
}
function dentSvcPrice(id){ const s=dentalServiceList.find(x=>String(x.id)===String(id)); return s?ordPrice('dent',String(id),getServicePrice(s)).price:0; }
function dentDraftAdd(){
  const sel=document.getElementById('dent-plan-svc'); if(!sel.value) return;
  _dentDraft.push({service_id:+sel.value,name:sel.options[sel.selectedIndex].text,tooth:document.getElementById('dent-plan-tooth').value.trim(),stage:Math.max(1,parseInt(document.getElementById('dent-plan-stage').value)||1),price:dentSvcPrice(sel.value)});
  dentDraftRender();
}
function dentDraftRender(){
  document.getElementById('dent-draft').innerHTML=_dentDraft.map((d,i)=>`<div style="display:flex;gap:10px;font-size:12px;padding:4px 0;border-bottom:1px solid var(--gray-100);"><span style="width:50px;">Stage ${d.stage}</span><span style="flex:1;">${cEsc(d.name)}${d.tooth?' — '+cEsc(d.tooth):''}</span><span>${Number(d.price).toLocaleString()} RWF</span><i class="fas fa-times" style="cursor:pointer;color:#dc2626;" onclick="_dentDraft.splice(${i},1);dentDraftRender()"></i></div>`).join('')
    +(_dentDraft.length?`<div style="font-size:12px;font-weight:700;text-align:right;margin-top:4px;">Total ${Number(_dentDraft.reduce((a,d)=>a+d.price,0)).toLocaleString()} RWF</div>`:'');
}
async function dentPlanSave(){
  const title=document.getElementById('dent-plan-title').value.trim();
  if(!title) return alert('Enter a plan title.'); if(!_dentDraft.length) return alert('Add at least one item.');
  if(await dentPost({action:'dental_plan_save',title,items:_dentDraft})){ _dentDraft=[]; document.getElementById('dent-plan-title').value=''; dentDraftRender(); dentLoad(); }
}
async function dentPlanSet(planId,status){ if(await dentPost({action:'dental_plan_update',plan_id:planId,status})) dentLoad(); }
async function dentItemSet(itemId,status){
  const j=await dentPost({action:'dental_plan_update',item_id:itemId,status}); if(!j) return;
  const w=(j.data&&j.data.warnings)||[]; if(w.length) alert('Done. Stock warning: '+w.join('; '));
  dentLoad();
}
function dentUse(itemId){ // planned item -> this visit's dental selection, then "Add to Billing List" as usual
  const p=_dentData.plans.flatMap(p=>p.items).find(i=>i.id==itemId); if(!p) return;
  const s=dentalServiceList.find(x=>String(x.id)===String(p.service_id)); if(!s) return alert('Service no longer in the catalog.');
  if(_ordSel.some(x=>x.type==='dent'&&x.id===String(s.id)&&x.tooth===p.tooth)) return;
  _ordSel.push(Object.assign({qty:1,note:'',tooth:p.tooth,planItemId:p.id,applyIns:true},{type:'dent',id:String(s.id),name:s.procedure_name,std:getServicePrice(s)},ordPrice('dent',String(s.id),getServicePrice(s))));
  ordRender(); document.getElementById('ord-panel-dent').scrollIntoView({behavior:'smooth',block:'center'});
}
async function dentToothDel(id){ if(!confirm('Delete this tooth record?')) return; if(await dentPost({action:'dental_tooth_delete',record_id:id})) dentLoad(); }
function dentRender(){
  const seen=new Set(), cur=[], badge=c=>`<span style="background:${_dentCondColor[c]||'#6b7280'};color:white;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;">${_dentConds[c]||c}</span>`;
  _dentData.records.forEach(r=>{ const k=r.tooth+'|'+r.surface; if(!seen.has(k)){ seen.add(k); cur.push(r); } });
  cur.sort((a,b)=>a.tooth-b.tooth);
  const th='style="text-align:left;padding:6px 8px;font-size:11px;color:var(--gray-500);"', td='style="padding:6px 8px;font-size:12px;border-top:1px solid var(--gray-100);"';
  const row=r=>`<tr><td ${td}><b>${r.tooth}</b></td><td ${td}>${cEsc(r.surface)||'—'}</td><td ${td}>${badge(r.cond)}</td><td ${td}>${cEsc(r.notes||'')}</td><td ${td}>${cEsc(String(r.recorded_at).substring(0,10))}</td><td ${td}>${cEsc(r.by_name||'')}</td><td ${td}><button title="Delete" onclick="dentToothDel(${r.id})" style="border:0;background:none;color:#dc2626;font-size:16px;line-height:1;cursor:pointer;">&times;</button></td></tr>`;
  const head=`<thead><tr><th ${th}>Tooth</th><th ${th}>Surface</th><th ${th}>Condition</th><th ${th}>Notes</th><th ${th}>Date</th><th ${th}>By</th><th ${th}></th></tr></thead>`;
  document.getElementById('dent-chart').innerHTML=!cur.length?'<div style="font-size:12px;color:var(--gray-400);">No tooth records yet.</div>'
    :`<table style="width:100%;border-collapse:collapse;">${head}<tbody>${cur.map(row).join('')}</tbody></table>`
    +(_dentData.records.length>cur.length?`<details style="margin-top:6px;"><summary style="cursor:pointer;font-size:12px;color:var(--gray-500);">History (${_dentData.records.length})</summary><table style="width:100%;border-collapse:collapse;">${head}<tbody>${_dentData.records.map(row).join('')}</tbody></table></details>`:'');
  const stBadge={proposed:'#6b7280',approved:'#2563eb',in_progress:'#d97706',completed:'#16a34a',cancelled:'#dc2626'};
  const btn=(label,fn,c)=>`<button onclick="${fn}" style="padding:3px 9px;background:white;color:${c};border:1px solid ${c};border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;">${label}</button>`;
  document.getElementById('dent-plans').innerHTML=!_dentData.plans.length?'<div style="font-size:12px;color:var(--gray-400);">No treatment plans yet.</div>':_dentData.plans.map(p=>{
    const live=p.items.filter(i=>i.status!=='cancelled'), total=live.reduce((a,i)=>a+parseFloat(i.est_price),0), done=live.filter(i=>i.status==='done').length;
    return `<div style="border:1px solid var(--gray-200);border-radius:8px;padding:10px 12px;margin-bottom:8px;">
      <div style="display:flex;align-items:center;gap:8px;"><b style="flex:1;font-size:13px;">${cEsc(p.title)}</b>
        <span style="background:${stBadge[p.status]};color:white;padding:1px 8px;border-radius:10px;font-size:11px;">${p.status.replace('_',' ')}</span>
        <span style="font-size:12px;color:var(--gray-600);">${done}/${live.length} done · ${Number(total).toLocaleString()} RWF</span>
        ${p.status==='proposed'?btn('Approve',`dentPlanSet(${p.id},'approved')`,'#2563eb')+btn('Cancel',`dentPlanSet(${p.id},'cancelled')`,'#dc2626'):''}</div>
      <table style="width:100%;border-collapse:collapse;margin-top:6px;">${p.items.map(i=>`<tr style="${i.status==='cancelled'?'text-decoration:line-through;color:#9ca3af;':''}"><td ${td} width="60">Stage ${i.stage}</td><td ${td}>${cEsc(i.procedure_name)}${i.tooth?' — '+cEsc(i.tooth):''}</td><td ${td} align="right">${Number(i.est_price).toLocaleString()}</td><td ${td} align="right">${i.status==='done'?'<span style="color:#16a34a;font-weight:600;">Done</span>':
        (['approved','in_progress'].includes(p.status)&&i.status==='planned'?(i.pay==='unbilled'?btn('Order now',`dentUse(${i.id})`,'#0d9488'):i.pay==='paid'?btn('Mark done',`dentItemSet(${i.id},'done')`,'#16a34a'):'<span style="font-size:11px;color:#d97706;font-weight:600;">Billed, awaiting payment</span>')+btn('Cancel',`dentItemSet(${i.id},'cancelled')`,'#dc2626'):i.status)}</td></tr>`).join('')}</table></div>`;
  }).join('');
}
// ============ IMAGING ORDER ============
let imagingServiceList=[];
async function loadImagingServices(){
  if(imagingServiceList.length) return;
  await loadHospitalCategory();
  try{
    const r=await fetch(window.location.origin + _cBase + '/api.php?endpoint=service-catalog&search=imaging&visit_id='+visitId);
    const j=await r.json();
    const all=j.data||[];
    imagingServiceList=all.filter(s=>(s.speciality||'').toLowerCase().startsWith('imaging'));
    if(!imagingServiceList.length) imagingServiceList=all;
  }catch(e){imagingServiceList=[];}
}
// ============ DIAGNOSIS TAGS ============
let selectedDiagnoses=[];
function addDiagnosis(){
  const inp=document.getElementById('f-final-diagnosis');
  const val=inp.value.trim();
  if(!val||selectedDiagnoses.includes(val)) return;
  addNewDiagnosis();
}
function removeDiagnosis(idx){
  recurrenceList=recurrenceList.filter(x=>x.tag!==selectedDiagnoses[idx]);
  selectedDiagnoses.splice(idx,1);
  renderDiagnoses();
  renderRecurrence();
}

// ============ REPEATED-ILLNESS ALERT (same diagnosis N times in D days) ============
let recurrenceList=[]; // {tag,count,threshold,window_days,action,justification}
async function checkRecurrence(tag){
  if(recurrenceList.some(x=>x.tag===tag)) return;
  try{
    const j=await (await fetch(consultAPI+'&action=diagnosis_recurrence&patient_id='+patientId+'&visit_id='+visitId+'&diagnosis='+encodeURIComponent(tag))).json();
    if(j.success && j.data && j.data.triggered && !recurrenceList.some(x=>x.tag===tag)){
      recurrenceList.push(Object.assign({tag,action:'',justification:''},j.data));
      renderRecurrence();
    } else if(j.success && !recurrenceList.some(x=>x.tag===tag)){
      recurrenceList.push({tag,triggered:false}); // checked, nothing to show
    }
  }catch(e){}
}
function renderRecurrence(){
  const box=document.getElementById('f-recurrence-alerts');
  box.innerHTML=recurrenceList.map((x,i)=>x.triggered===false?'':`<div class="rec-alert" style="margin-top:10px;padding:12px 14px;background:#fef2f2;border:1px solid #fca5a5;border-left:4px solid #dc2626;border-radius:8px;font-size:13px;color:#991b1b;">
    <div style="font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Repeated illness: "${cEsc(x.tag)}" treated ${x.count} times in the last ${x.window_days} days (limit ${x.threshold}).</div>
    <div style="margin:8px 0 6px;color:#7f1d1d;">Choose how to proceed before completing:</div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;">
      ${[['referred','Refer'],['transferred','Transfer'],['justified','Continue treatment here']].map(([v,l])=>`<label style="display:flex;align-items:center;gap:6px;cursor:pointer;"><input type="radio" name="rec-${i}" value="${v}" ${x.action===v?'checked':''} onchange="recurrenceList[${i}].action=this.value"> ${l}</label>`).join('')}
    </div>
    <textarea rows="2" placeholder="Justification (required to continue treatment)" oninput="recurrenceList[${i}].justification=this.value" style="width:100%;margin-top:8px;padding:8px 10px;border:1px solid #fca5a5;border-radius:6px;font-size:13px;resize:vertical;">${cEsc(x.justification)}</textarea>
  </div>`).join('');
}
// Returns true when every triggered diagnosis has a saved decision
async function resolveRecurrence(){
  for(const t of selectedDiagnoses) await checkRecurrence(t);
  const open=recurrenceList.filter(x=>x.triggered!==false);
  for(const x of open){
    if(!x.action || (x.action==='justified' && !x.justification.trim())){
      alert('"'+x.tag+'" was treated '+x.count+' times in '+x.window_days+' days.\n\nChoose Refer, Transfer, or Continue (with a written justification) before completing.');
      const el=document.getElementById('f-recurrence-alerts'); el.scrollIntoView({behavior:'smooth',block:'center'});
      return false;
    }
  }
  for(const x of open){
    const j=await (await fetch(consultAPI+'&action=diagnosis_recurrence_flag',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({visit_id:visitId,patient_id:patientId,diagnosis:x.tag,action_taken:x.action,justification:x.justification.trim()})})).json();
    if(!j.success){ alert('Could not save the decision: '+(j.message||'Error')); return false; }
  }
  return true;
}
function renderDiagnoses(){
  document.getElementById('diag-ph').style.display=selectedDiagnoses.length?'none':'';
  document.getElementById('diag-chips').innerHTML=selectedDiagnoses.map((d,i)=>`<span style="display:inline-flex;align-items:center;gap:6px;padding:3px 9px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:5px;font-size:13px;font-weight:600;color:var(--gray-700);">${cEsc(d)}<i class="fas fa-times" onclick="event.stopPropagation();removeDiagnosis(${i})" title="Remove" style="cursor:pointer;color:#9ca3af;font-size:11px;"></i></span>`).join('');
}

// ============ SAVE & COMPLETE ============
async function saveAndComplete(){
  const btn=document.getElementById('btn-complete');
  if(!validateStep1()){ document.getElementById('c-alert').scrollIntoView({behavior:'smooth',block:'center'}); return; }

  // If doctor typed a diagnosis in the input without clicking 'Add New', add it automatically
  const diagInput = document.getElementById('f-final-diagnosis');
  const typedDiag = diagInput ? diagInput.value.trim() : '';
  if(typedDiag && !selectedDiagnoses.includes(typedDiag)){
    selectedDiagnoses.push(typedDiag);
    renderDiagnoses();
    diagInput.value = '';
    const dd = document.getElementById('diagnosis-dropdown');
    if(dd) dd.style.display = 'none';
  }

  // Enforce Final Diagnosis requirement
  const finalDiagStr = selectedDiagnoses.join(', ').trim();
  if(!finalDiagStr){
    alert('Final Diagnosis is required.\n\nYou cannot save and complete the consultation without entering at least one final diagnosis.\n\nPlease search, select, or type a final diagnosis above.');
    const dBox=document.getElementById('diag-box');
    if(dBox){
      dBox.scrollIntoView({behavior:'smooth', block:'center'});
      dBox.style.borderColor = '#dc2626';
      setTimeout(() => { dBox.style.borderColor = '#93c5d8'; }, 3000);
    }
    return;
  }

  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';
  let recOk=false;
  try{ recOk=await resolveRecurrence(); }catch(e){ alert('Error: '+e.message); }
  if(!recOk){ btn.disabled=false; btn.innerHTML='<i class="fas fa-check-double"></i> Save & Complete Consultation'; return; }
  // Collect final prescription (always external - no invoice)
  const rxRows=document.querySelectorAll('#final-rx-items .final-rx-row');
  const prescription=[];
  rxRows.forEach(row=>{
    const name=row.querySelector('.final-rx-med').value.trim();
    const dosage=row.querySelector('.final-rx-dose').value.trim();
    const frequency=row.querySelector('.final-rx-freq').value.trim();
    const duration=row.querySelector('.final-rx-dur').value.trim();
    const quantity=parseInt(row.querySelector('.final-rx-qty').value)||null;
    const route=row.querySelector('.final-rx-route').value;
    if(name){
      prescription.push({medication:name, dosage, frequency, duration, quantity, route, destination:'external'});
    }
  });
  try{
    // 1. Save consultation as complete
    const r=await fetch(consultAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      action:'complete', visit_id:visitId, patient_id:patientId, department_id:cDeptId, created_by:cUserId,
      doctor_id:cUserId, doctor_name:cUserName,
      chief_complaint:document.getElementById('f-complaints').value.trim(),
      history:document.getElementById('f-past-history').value.trim(),
      examination:document.getElementById('f-physical-exam').value.trim(),
      differential_diagnosis:document.getElementById('f-diff-diagnosis').value.trim(),
      final_diagnosis:finalDiagStr,
      treatment_plan:document.getElementById('f-treatment-plan').value.trim(),
      prescription:prescription
    })});
    const j=await r.json();
    if(!j.success){ alert('Failed: '+(j.message||'Error')); return; }
    await saveMedicalProfile();

    alert('✓ Consultation completed successfully!');
    window.location.href='department.php';
  }catch(e){alert('Error: '+e.message);}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-check-double"></i> Save & Complete Consultation';}
}

// ============ INVOICE NOTE MODAL ============
// Returns a Promise that resolves with the note string (or '' if skipped),
// or rejects if the user cancels entirely.
function invoiceNoteModal(title){
  return new Promise((resolve, reject) => {
    const modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.45);display:flex;align-items:center;justify-content:center;padding:16px;';
    modal.innerHTML = `
      <div style="background:white;border-radius:12px;width:460px;max-width:95vw;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
          <div style="width:36px;height:36px;background:#fffbeb;border-radius:50%;display:flex;align-items:center;justify-content:center;">
            <i class="fas fa-sticky-note" style="color:#d97706;font-size:16px;"></i>
          </div>
          <div>
            <div style="font-size:15px;font-weight:700;color:#1f2937;">Add a Note</div>
            <div style="font-size:12px;color:#6b7280;">${title}</div>
          </div>
        </div>
        <textarea id="inv-note-input" rows="4" placeholder="Optional — type a note or comment to attach to this invoice... (press Skip to proceed without one)" style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;resize:vertical;outline:none;" autofocus></textarea>
        <div style="display:flex;gap:8px;margin-top:14px;justify-content:flex-end;">
          <button id="inv-note-cancel" style="padding:9px 18px;background:#f3f4f6;color:#374151;border:none;border-radius:7px;font-weight:600;cursor:pointer;font-size:13px;">Cancel</button>
          <button id="inv-note-skip"   style="padding:9px 18px;background:#f9fafb;color:#6b7280;border:1px solid #d1d5db;border-radius:7px;font-weight:500;cursor:pointer;font-size:13px;">Skip</button>
          <button id="inv-note-ok"     style="padding:9px 22px;background:#d97706;color:white;border:none;border-radius:7px;font-weight:700;cursor:pointer;font-size:13px;"><i class="fas fa-check"></i> Confirm</button>
        </div>
      </div>`;
    document.body.appendChild(modal);
    // Focus textarea
    setTimeout(()=>modal.querySelector('#inv-note-input').focus(), 50);
    modal.querySelector('#inv-note-cancel').onclick = () => { modal.remove(); reject('cancelled'); };
    modal.querySelector('#inv-note-skip').onclick   = () => { modal.remove(); resolve(''); };
    modal.querySelector('#inv-note-ok').onclick     = () => { const v = modal.querySelector('#inv-note-input').value.trim(); modal.remove(); resolve(v); };
    // Enter key = confirm
    modal.querySelector('#inv-note-input').addEventListener('keydown', e => {
      if(e.key === 'Enter' && (e.ctrlKey || e.metaKey)){ const v = e.target.value.trim(); modal.remove(); resolve(v); }
    });
  });
}

// ============ INIT ============
loadPatient();
loadVitals();
loadVitalRecords();
// a different doctor opening the file is recorded (audit), then the timeline is shown
fetch(consultAPI+'&action=touch',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({visit_id:visitId})}).then(r=>r.text()) /* drain the body or the request stays pending */.then(loadConsultEvents).catch(()=>{});
loadExisting();
loadLabResults();
if(typeof loadImagingResults === 'function') loadImagingResults();
loadExistingPrescriptions();
// Diagnosis list loaded on-demand via search
loadLabTests();
// loadMedications() is called inside loadPatient() after insurance info is set

// ============ ORDERS SUMMARY & DELETION REQUESTS ============
const cDelAPI = window.location.origin + _cBase + '/api.php?endpoint=deletion-requests';
const cUserId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;

// ===== Follow-up appointments (rendez-vous) =====
const fuAPI = window.location.origin + _cBase + '/api.php?endpoint=appointments';
let fuDoctors = null;
const fuEsc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const fuIso = d => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
const fuDate = (d, t) => new Date(d + 'T00:00:00').toLocaleDateString('en-GB',{weekday:'short',day:'2-digit',month:'short',year:'numeric'}) + (t ? ' at ' + t.slice(0,5) : '');
async function fuLoadList(){
  const box = document.getElementById('fu-list');
  try{
    const j = await (await fetch(fuAPI + '&patient_id=' + patientId)).json();
    if(!j.success){ box.innerHTML = ''; return; }
    const st = {scheduled:['Scheduled','#1d4ed8','#dbeafe'],arrived:['Came back','#047857','#d1fae5'],cancelled:['Cancelled','#4b5563','#f3f4f6'],missed:['Missed','#b91c1c','#fee2e2']};
    const rows = j.data.filter(a => a.status === 'scheduled' || a.source_visit_id == visitId).concat(j.data.filter(a => a.status !== 'scheduled' && a.source_visit_id != visitId).slice(-3));
    box.innerHTML = rows.length ? rows.map(a => `<div style="display:flex;justify-content:space-between;gap:10px;padding:7px 0;border-top:1px solid #dbeafe;">
        <div><strong>${fuDate(a.appointment_date, a.appointment_time)}</strong> · ${fuEsc(a.doctor_name || '')}<div style="color:var(--gray-600);font-size:12px;">${fuEsc(a.reason)}${a.instructions ? ' — <em>' + fuEsc(a.instructions) + '</em>' : ''}</div></div>
        <span style="align-self:center;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:600;white-space:nowrap;background:${st[a.status][2]};color:${st[a.status][1]};">${st[a.status][0]}</span></div>`).join('')
      : '<div style="color:var(--gray-400);font-size:12px;">No follow-up booked for this patient.</div>';
  }catch(e){ box.innerHTML = ''; }
}
// If this visit is the patient coming back for a booked follow-up, show why
async function fuShowOrigin(){
  try{
    const j = await (await fetch(fuAPI + '&visit_id=' + visitId)).json();
    const a = j.success && j.data[0]; if(!a) return;
    const div = document.createElement('div');
    div.style.cssText = 'background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;';
    div.innerHTML = `<div style="font-weight:700;margin-bottom:2px;"><i class="fas fa-calendar-check"></i> Follow-up visit</div>
      Booked by ${fuEsc(a.doctor_name || '')}${a.source_visit_no ? ' at consultation ' + fuEsc(a.source_visit_no) + ' (' + fuEsc(a.source_visit_date) + ')' : ''} for ${fuDate(a.appointment_date, a.appointment_time)}.
      <div style="margin-top:4px;"><strong>Reason:</strong> ${fuEsc(a.reason)}</div>${a.instructions ? `<div><strong>Instructions given:</strong> ${fuEsc(a.instructions)}</div>` : ''}`;
    document.getElementById('step-1').before(div);
  }catch(e){}
}
function fuSetDays(n){ const d = new Date(); d.setDate(d.getDate() + n); document.getElementById('fu-date').value = fuIso(d); fuBooked(); }
async function fuOpen(){
  if(!fuDoctors){
    const j = await (await fetch(fuAPI + '&action=doctors')).json();
    fuDoctors = j.data || [];
    document.getElementById('fu-doctor').innerHTML = fuDoctors.map(d => `<option value="${d.id}">${fuEsc(d.name)}</option>`).join('');
    document.getElementById('fu-quick').innerHTML = [[3,'In 3 days'],[7,'1 week'],[14,'2 weeks'],[30,'1 month']].map(([n,l]) =>
      `<button type="button" onclick="fuSetDays(${n})" style="padding:4px 10px;border:1px solid var(--gray-200);background:white;border-radius:14px;font-size:12px;cursor:pointer;">${l}</button>`).join('');
  }
  if(fuDoctors.some(d => +d.id === cUserId)) document.getElementById('fu-doctor').value = cUserId;
  document.getElementById('fu-patient').textContent = 'Patient: ' + document.getElementById('c-name').textContent;
  ['fu-time','fu-reason','fu-instructions'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fu-time').value = '09:00';
  document.getElementById('fu-err').style.display = 'none';
  fuSetDays(7);
  document.getElementById('fu-modal').style.display = 'flex';
}
function fuClose(){ document.getElementById('fu-modal').style.display = 'none'; }
async function fuBooked(){
  const doc = document.getElementById('fu-doctor').value, d = document.getElementById('fu-date').value, box = document.getElementById('fu-booked');
  if(!doc || !d){ box.textContent = ''; return; }
  const j = await (await fetch(fuAPI + '&action=day&doctor_id=' + doc + '&date=' + d)).json();
  const t = j.data || [];
  box.innerHTML = t.length ? '<i class="fas fa-clock"></i> Already booked that day: ' + t.map(x => `<span style="display:inline-block;background:#fef3c7;color:#92400e;border-radius:10px;padding:1px 8px;margin:2px;">${x.appointment_time.slice(0,5)} (${x.duration_min}m)</span>`).join('')
    : '<i class="fas fa-check" style="color:var(--success-color);"></i> No other appointment that day.';
}
async function fuSave(){
  const err = document.getElementById('fu-err'), btn = document.getElementById('fu-save');
  const body = { patient_id: patientId, source_visit_id: visitId, department_id: cDeptId || null,
    doctor_id: document.getElementById('fu-doctor').value, appointment_date: document.getElementById('fu-date').value,
    appointment_time: document.getElementById('fu-time').value, duration_min: document.getElementById('fu-duration').value,
    reason: document.getElementById('fu-reason').value.trim(), instructions: document.getElementById('fu-instructions').value.trim() };
  err.style.display = 'none';
  if(!body.reason){ err.textContent = 'Write the reason for the follow-up.'; err.style.display = 'block'; document.getElementById('fu-reason').focus(); return; }
  btn.disabled = true;
  try{
    const j = await (await fetch(fuAPI, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)})).json();
    if(!j.success){ err.textContent = j.message; err.style.display = 'block'; return; }
    fuClose(); fuLoadList();
  }catch(e){ err.textContent = 'Request failed'; err.style.display = 'block'; }
  finally{ btn.disabled = false; }
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') fuClose(); });
fuLoadList(); fuShowOrigin();
const cUserName = <?php
  $ud = $_SESSION['user_data'] ?? [];
  $fn = trim(($ud['first_name'] ?? '') . ' ' . ($ud['last_name'] ?? ''));
  echo json_encode($fn ?: 'Doctor');
?>;

async function loadOrdersSummary(){
  if(window.ctRefresh) ctRefresh(); // billing changed: keep Invoicing / Results / Medication tabs current
  const container = document.getElementById('c-orders-summary');
  const content = document.getElementById('c-orders-summary-content');
  if(!container || !content) return;
  const visitId = <?php echo (int)($_GET['visit_id'] ?? 0); ?>;
  if(!visitId){ container.style.display='none'; return; }

  content.innerHTML='<div style="padding:16px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading added items & orders...</div>';
  container.style.display='block';

  try{
    const r = await fetch(window.location.origin + _cBase + '/api.php?endpoint=consultation/consult&action=visit_orders_summary&visit_id='+visitId);
    const j = await r.json();
    if(!j.success || !j.data){
      container.style.display='none';
      return;
    }

    const d = j.data;
    window._currentVisitOrders = d;
    billQueueRender();
    const isCompleted = d.is_completed || (d.visit && d.visit.status === 'completed');

    // Reset duplicate tracking collections
    window._existingVisitItems = {
      pharmacyIds: new Set(),
      pharmacyNames: new Set(),
      labIds: new Set(),
      labNames: new Set(),
      procIds: new Set(),
      procNames: new Set(),
      imgNames: new Set()
    };

    (d.pharmacy||[]).forEach(it=>{
      if(it.inventory_item_id) window._existingVisitItems.pharmacyIds.add(String(it.inventory_item_id));
      if(it.medication_name) {
        window._existingVisitItems.pharmacyNames.add(it.medication_name.toLowerCase().trim());
        _submittedItems.add(_dupKey(it.medication_name));
        _submittedItems.add(_dupKey('Medication — ' + it.medication_name));
      }
    });

    (d.labs||[]).forEach(it=>{
      if(it.test_name){
        window._existingVisitItems.labNames.add(it.test_name.toLowerCase().trim());
        _submittedItems.add(_dupKey(it.test_name));
        _submittedItems.add(_dupKey('Lab Test — ' + it.test_name));
      }
    });

    (d.procedures||[]).forEach(it=>{
      if(it.service_id) window._existingVisitItems.procIds.add(String(it.service_id));
      if(it.procedure_name){
        window._existingVisitItems.procNames.add(it.procedure_name.toLowerCase().trim());
        _submittedItems.add(_dupKey(it.procedure_name));
      }
    });

    (d.imaging||[]).forEach(it=>{
      if(it.imaging_name){
        window._existingVisitItems.imgNames.add(it.imaging_name.toLowerCase().trim());
        _submittedItems.add(_dupKey(it.imaging_name));
      }
    });

    const totalCount = (d.pharmacy?.length||0) + (d.labs?.length||0) + (d.procedures?.length||0) + (d.imaging?.length||0);
    if(totalCount === 0){
      container.style.display='none';
      return;
    }

    const card = 'padding:14px;border-bottom:1px solid var(--gray-200);';
    const amt = v => parseFloat(v||0);
    const fmt = v => v.toLocaleString();
    const itemAmt = it => amt(it.invoice_total!=null ? it.invoice_total : (it.total!=null ? it.total : it.unit_price));
    const sum = arr => (arr||[]).reduce((a,it)=>a+itemAmt(it),0);
    const grand = sum(d.pharmacy)+sum(d.labs)+sum(d.procedures)+sum(d.imaging);
    const totTag = arr => `<span style="margin-left:auto;font-size:12px;font-weight:700;color:var(--gray-800);">Total: ${fmt(sum(arr))} RWF</span>`;
    const payBadge = it => {
      const st = it.invoice_status;
      const [t,bg,fg] = !st ? ['Not billed','#f3f4f6','#4b5563'] : st==='paid' ? ['Paid','#dcfce7','#166534'] : (st==='partial' || (amt(it.invoice_balance)>0 && amt(it.invoice_balance)<amt(it.invoice_total))) ? ['Partial','#fef3c7','#92400e'] : ['Unpaid','#fee2e2','#b91c1c'];
      return `<span style="background:${bg};color:${fg};padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;">${t}</span>`;
    };

    let html = '';

    // Notice banner
    html += `<div style="${card}padding:10px 16px;background:${isCompleted?'#f3f4f6':'#f0fdf4'};border-bottom:1px solid ${isCompleted?'#e5e7eb':'#bbf7d0'};display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
      <div style="font-size:12px;font-weight:600;color:${isCompleted?'#4b5563':'#166534'};">
        <i class="fas ${isCompleted?'fa-lock':'fa-check-circle'}"></i> ${isCompleted ? 'Visit Completed — Orders and added items are locked.' : 'Active Visit Orders — Edit quantity or delete items below (automatically syncs with billing & accounting).'}
      </div>
      <div style="font-size:11px;font-weight:600;background:white;padding:2px 10px;border-radius:12px;border:1px solid ${isCompleted?'#d1d5db':'#86efac'};color:${isCompleted?'#4b5563':'#15803d'};">
        Total Items: ${totalCount} · Total Amount: ${fmt(grand)} RWF
      </div>
    </div>`;

    // 1. PHARMACY / PRESCRIPTIONS
    if(d.pharmacy && d.pharmacy.length){
      html += `<div style="${card}">
        <div style="font-size:13px;font-weight:700;color:#166534;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-prescription"></i> Prescriptions & Medications (${d.pharmacy.length})${totTag(d.pharmacy)}
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:#f8fafc;color:var(--gray-600);text-align:left;border-bottom:1px solid var(--gray-200);">
                <th style="padding:6px 10px;">Medication</th>
                <th style="padding:6px 10px;">Dosage & Frequency</th>
                <th style="padding:6px 10px;">Duration</th>
                <th style="padding:6px 10px;text-align:center;">Qty</th>
                <th style="padding:6px 10px;text-align:right;">Total (RWF)</th>
                <th style="padding:6px 10px;text-align:center;">Payment</th>
                <th style="padding:6px 10px;text-align:center;">Status</th>
                <th style="padding:6px 10px;text-align:center;">Actions</th>
              </tr>
            </thead>
            <tbody>`;
      d.pharmacy.forEach(it=>{
        const statusBadge = it.item_status === 'dispensed' ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;">Dispensed</span>' : '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;">Awaiting pharmacy</span>';
        const safeMedName = cEsc(it.medication_name).replace(/'/g, "\\'");
        const actionHtml = !isCompleted ? `
          <button onclick="cOpenEditModal('pharmacy', ${it.item_id})" style="padding:4px 8px;background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;" title="Edit Quantity"><i class="fas fa-edit"></i> Edit Qty</button>
          <button onclick="cDirectDelete('pharmacy', ${it.item_id}, '${safeMedName}')" style="padding:4px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;" title="Delete Item"><i class="fas fa-trash"></i></button>
        ` : `<span style="font-size:11px;color:var(--gray-400);"><i class="fas fa-lock"></i></span>`;

        html += `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 10px;font-weight:600;color:var(--gray-800);">${cEsc(it.medication_name)}</td>
          <td style="padding:8px 10px;color:var(--gray-600);">${cEsc(it.dosage||'—')} ${it.frequency?'· '+cEsc(it.frequency):''}</td>
          <td style="padding:8px 10px;color:var(--gray-600);">${cEsc(it.duration||'—')}</td>
          <td style="padding:8px 10px;text-align:center;font-weight:700;color:var(--primary-color);">${it.quantity||1}</td>
          <td style="padding:8px 10px;text-align:right;font-weight:600;color:var(--gray-800);">${it.invoice_total ? parseFloat(it.invoice_total).toLocaleString() : '—'}</td>
          <td style="padding:8px 10px;text-align:center;">${payBadge(it)}</td>
          <td style="padding:8px 10px;text-align:center;">${statusBadge}</td>
          <td style="padding:8px 10px;text-align:center;white-space:nowrap;">${actionHtml}</td>
        </tr>`;
      });
      html += `</tbody></table></div></div>`;
    }

    // 2. LAB ORDERS
    if(d.labs && d.labs.length){
      html += `<div style="${card}">
        <div style="font-size:13px;font-weight:700;color:#1e40af;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-flask"></i> Lab Investigations (${d.labs.length})${totTag(d.labs)}
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:#f8fafc;color:var(--gray-600);text-align:left;border-bottom:1px solid var(--gray-200);">
                <th style="padding:6px 10px;">Test Name</th>
                <th style="padding:6px 10px;">Clinical Notes / Priority</th>
                <th style="padding:6px 10px;text-align:right;">Price (RWF)</th>
                <th style="padding:6px 10px;text-align:center;">Payment</th>
                <th style="padding:6px 10px;text-align:center;">Status</th>
                <th style="padding:6px 10px;text-align:center;">Actions</th>
              </tr>
            </thead>
            <tbody>`;
      d.labs.forEach(it=>{
        const statusBadge = it.status === 'completed' ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;">Completed</span>' : '<span style="background:#eff6ff;color:#1e40af;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;">Ordered</span>';
        const safeTestName = cEsc(it.test_name).replace(/'/g, "\\'");
        const actionHtml = !isCompleted ? `
          <button onclick="cOpenEditModal('lab', ${it.order_id})" style="padding:4px 8px;background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;" title="Edit Notes"><i class="fas fa-edit"></i> Edit</button>
          <button onclick="cDirectDelete('lab', ${it.order_id}, '${safeTestName}')" style="padding:4px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:11px;font-weight:600;cursor:margin-left:4px;" title="Delete Test"><i class="fas fa-trash"></i></button>
        ` : `<span style="font-size:11px;color:var(--gray-400);"><i class="fas fa-lock"></i></span>`;

        const testNameDisplay = window._isAdminUser ? (it.test_name||'') : (it.test_name||'').replace(/\s*\[[\s\S]*?RWF[\s\S]*?\]/gi, '').replace(/\s*-\s*[\d,]+\s*RWF/gi, '');
        html += `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 10px;font-weight:600;color:var(--gray-800);">${cEsc(testNameDisplay)}</td>
          <td style="padding:8px 10px;color:var(--gray-600);">${cEsc(it.details||'—')}</td>
          <td style="padding:8px 10px;text-align:right;font-weight:600;color:var(--gray-800);">${it.unit_price ? parseFloat(it.unit_price).toLocaleString() : '—'}</td>
          <td style="padding:8px 10px;text-align:center;">${payBadge(it)}</td>
          <td style="padding:8px 10px;text-align:center;">${statusBadge}</td>
          <td style="padding:8px 10px;text-align:center;white-space:nowrap;">${actionHtml}</td>
        </tr>`;
      });
      html += `</tbody></table></div></div>`;
    }

    // 3. PROCEDURES
    if(d.procedures && d.procedures.length){
      html += `<div style="${card}">
        <div style="font-size:13px;font-weight:700;color:#9d174d;margin-bottom:8px;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-syringe"></i> Procedures Added (${d.procedures.length})${totTag(d.procedures)}
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:#f8fafc;color:var(--gray-600);text-align:left;border-bottom:1px solid var(--gray-200);">
                <th style="padding:6px 10px;">Procedure Description</th>
                <th style="padding:6px 10px;text-align:center;">Qty</th>
                <th style="padding:6px 10px;text-align:right;">Unit Price</th><th style="padding:6px 10px;text-align:right;">Total (RWF)</th>
                <th style="padding:6px 10px;text-align:center;">Payment</th>
                <th style="padding:6px 10px;text-align:center;">Actions</th>
              </tr>
            </thead>
            <tbody>`;
      d.procedures.forEach(it=>{
        const safeProcName = cEsc(it.procedure_name).replace(/'/g, "\\'");
        const actionHtml = !isCompleted ? `
          <button onclick="cOpenEditModal('procedure', ${it.invoice_item_id})" style="padding:4px 8px;background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;" title="Edit Quantity"><i class="fas fa-edit"></i> Edit Qty</button>
          <button onclick="cDirectDelete('procedure', ${it.invoice_item_id}, '${safeProcName}')" style="padding:4px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;" title="Delete Procedure"><i class="fas fa-trash"></i></button>
        ` : `<span style="font-size:11px;color:var(--gray-400);"><i class="fas fa-lock"></i></span>`;

        const procNameDisplay = window._isAdminUser ? (it.procedure_name||'') : (it.procedure_name||'').replace(/\s*\[[\s\S]*?RWF[\s\S]*?\]/gi, '').replace(/\s*-\s*[\d,]+\s*RWF/gi, '');
        html += `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 10px;font-weight:600;color:var(--gray-800);">${cEsc(procNameDisplay)}</td>
          <td style="padding:8px 10px;text-align:center;font-weight:700;color:var(--primary-color);">${it.quantity||1}</td>
          <td style="padding:8px 10px;text-align:right;color:var(--gray-600);">${parseFloat(it.unit_price||0).toLocaleString()}</td>
          <td style="padding:8px 10px;text-align:right;font-weight:600;color:var(--gray-800);">${parseFloat(it.total||0).toLocaleString()}</td>
          <td style="padding:8px 10px;text-align:center;">${payBadge(it)}</td>
          <td style="padding:8px 10px;text-align:center;white-space:nowrap;">${actionHtml}</td>
        </tr>`;
      });
      html += `</tbody></table></div></div>`;
    }

    // 4. IMAGING
    window._currentImagingItems = d.imaging || [];
    if(d.imaging && d.imaging.length){
      html += `<div style="${card}">
        <div style="font-size:13px;font-weight:700;color:#9a3412;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;gap:6px;">
          <span><i class="fas fa-x-ray"></i> Imaging & Radiology (${d.imaging.length}) ${totTag(d.imaging).replace('margin-left:auto;','margin-left:10px;')}</span>
          <button onclick="cOpenImagingResultModal(-1)" style="padding:4px 10px;background:#ea580c;color:white;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;"><i class="fas fa-plus"></i> Add Result</button>
        </div>
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:#f8fafc;color:var(--gray-600);text-align:left;border-bottom:1px solid var(--gray-200);">
                <th style="padding:6px 10px;">Imaging Service</th>
                <th style="padding:6px 10px;">Details</th>
                <th style="padding:6px 10px;text-align:right;">Price (RWF)</th>
                <th style="padding:6px 10px;text-align:center;">Payment</th>
                <th style="padding:6px 10px;text-align:center;">Status</th>
                <th style="padding:6px 10px;text-align:center;">Actions</th>
              </tr>
            </thead>
            <tbody>`;
      d.imaging.forEach((it, idx)=>{
        const safeImgName = cEsc(it.imaging_name).replace(/'/g, "\\'");
        const imgKeyId = it.invoice_item_id || it.order_id;
        const isDone = it.status === 'completed' || (it.result && it.result.length > 5);
        const statusBadge = isDone
          ? '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;"><i class="fas fa-check-circle"></i> Completed</span>'
          : '<span style="background:#fff7ed;color:#9a3412;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;"><i class="fas fa-clock"></i> Ordered</span>';

        let resBtns = '';
        if(isDone){
          resBtns += `<button onclick="cOpenImagingResultModal(${idx})" style="padding:4px 8px;background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-right:4px;" title="View or Edit Result"><i class="fas fa-edit"></i> Edit Result</button>`;
          resBtns += `<button onclick="cPrintImagingReport(${idx})" style="padding:4px 8px;background:#4338ca;color:white;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-right:4px;" title="Print Report"><i class="fas fa-print"></i> Print</button>`;
        } else {
          resBtns += `<button onclick="cOpenImagingResultModal(${idx})" style="padding:4px 8px;background:#059669;color:white;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-right:4px;" title="Add Imaging Result"><i class="fas fa-file-medical"></i> Add Result</button>`;
        }

        const editDeleteHtml = !isCompleted ? `
          <button onclick="cOpenEditModal('imaging', ${imgKeyId})" style="padding:4px 8px;background:#f3f4f6;color:var(--gray-700);border:1px solid var(--gray-300);border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;" title="Edit Quantity"><i class="fas fa-pencil-alt"></i> Qty</button>
          <button onclick="cDirectDelete('imaging', ${imgKeyId}, '${safeImgName}')" style="padding:4px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;" title="Delete Imaging"><i class="fas fa-trash"></i></button>
        ` : `<span style="font-size:11px;color:var(--gray-400);"><i class="fas fa-lock"></i></span>`;

        html += `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 10px;font-weight:600;color:var(--gray-800);">${cEsc(it.imaging_name)}</td>
          <td style="padding:8px 10px;color:var(--gray-600);">${cEsc(it.details||'—')}</td>
          <td style="padding:8px 10px;text-align:right;font-weight:600;color:var(--gray-800);">${it.unit_price ? parseFloat(it.unit_price).toLocaleString() : '—'}</td>
          <td style="padding:8px 10px;text-align:center;">${payBadge(it)}</td>
          <td style="padding:8px 10px;text-align:center;">${statusBadge}</td>
          <td style="padding:8px 10px;text-align:center;white-space:nowrap;">${resBtns}${editDeleteHtml}</td>
        </tr>`;
      });
      html += `</tbody></table></div></div>`;
    }

    content.innerHTML = `<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:16px;overflow:hidden;">${html}</div>`;
  }catch(e){
    console.error('Error loading orders summary:', e);
    container.style.display='none';
  }
}

function cOpenEditModal(itemType, itemId){
  if(!window._currentVisitOrders) return;
  let item = null;
  if(itemType === 'pharmacy'){
    item = (window._currentVisitOrders.pharmacy||[]).find(p => p.item_id == itemId);
  } else if(itemType === 'lab'){
    item = (window._currentVisitOrders.labs||[]).find(l => l.order_id == itemId);
  } else if(itemType === 'procedure'){
    item = (window._currentVisitOrders.procedures||[]).find(p => p.invoice_item_id == itemId);
  } else if(itemType === 'imaging'){
    item = (window._currentVisitOrders.imaging||[]).find(i => (i.invoice_item_id == itemId || i.order_id == itemId));
  }
  if(!item) return alert('Item not found.');

  const existingModal = document.getElementById('c-edit-item-modal');
  if(existingModal) existingModal.remove();

  const itemName = item.medication_name || item.test_name || item.procedure_name || item.imaging_name || 'Item';
  const typeLabel = itemType.charAt(0).toUpperCase() + itemType.slice(1);

  let fieldsHtml = '';
  if(itemType === 'pharmacy'){
    fieldsHtml = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Quantity <span style="color:#dc2626;">*</span></label>
          <input type="number" id="c-edit-qty" min="1" value="${item.quantity||1}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;font-weight:600;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Dosage</label>
          <input type="text" id="c-edit-dosage" value="${cEsc(item.dosage||'')}" placeholder="e.g. 500mg" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Frequency</label>
          <input type="text" id="c-edit-freq" value="${cEsc(item.frequency||'')}" placeholder="e.g. 3x/day" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Duration</label>
          <input type="text" id="c-edit-dur" value="${cEsc(item.duration||'')}" placeholder="e.g. 7 days" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
      </div>
      <div style="margin-bottom:14px;">
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Special Instructions</label>
        <input type="text" id="c-edit-notes" value="${cEsc(item.instructions||'')}" placeholder="Instructions..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    `;
  } else if(itemType === 'lab'){
    fieldsHtml = `
      <div style="margin-bottom:14px;">
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Clinical Notes / Priority</label>
        <textarea id="c-edit-notes" rows="3" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;">${cEsc(item.details||'')}</textarea>
      </div>
    `;
  } else {
    fieldsHtml = `
      <div style="margin-bottom:14px;">
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Quantity <span style="color:#dc2626;">*</span></label>
        <input type="number" id="c-edit-qty" min="1" value="${item.quantity||1}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;font-weight:600;">
      </div>
    `;
  }

  const modal = document.createElement('div');
  modal.id = 'c-edit-item-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;padding:16px;';
  modal.innerHTML = `
    <div style="background:white;border-radius:12px;width:480px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--gray-200);">
        <h3 style="font-size:16px;font-weight:700;color:var(--gray-900);margin:0;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Edit ${typeLabel}</h3>
        <button onclick="document.getElementById('c-edit-item-modal').remove()" style="border:none;background:none;font-size:22px;color:var(--gray-400);cursor:pointer;">&times;</button>
      </div>

      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:12px;color:#1e40af;">
        <strong>${cEsc(itemName)}</strong><br>
        <span style="font-size:11px;color:#3b82f6;">Changes will automatically update the related invoice total and financial journal entry.</span>
      </div>

      ${fieldsHtml}

      <div id="c-edit-modal-alert" style="display:none;margin-bottom:12px;padding:10px;border-radius:8px;font-size:12px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-100);">
        <button onclick="document.getElementById('c-edit-item-modal').remove()" style="padding:9px 16px;background:var(--gray-100);color:var(--gray-700);border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">Cancel</button>
        <button id="c-btn-save-edit" onclick="cSubmitEditOrder('${itemType}', ${itemId})" style="padding:9px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save & Sync Invoice</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
}

async function cSubmitEditOrder(itemType, itemId){
  const visitId = <?php echo (int)($_GET['visit_id'] ?? 0); ?>;
  const btn = document.getElementById('c-btn-save-edit');
  const alertEl = document.getElementById('c-edit-modal-alert');
  if(alertEl) alertEl.style.display = 'none';

  const qtyEl = document.getElementById('c-edit-qty');
  const qty = qtyEl ? parseFloat(qtyEl.value)||1 : 1;
  const dosage = document.getElementById('c-edit-dosage')?.value||'';
  const frequency = document.getElementById('c-edit-freq')?.value||'';
  const duration = document.getElementById('c-edit-dur')?.value||'';
  const notes = document.getElementById('c-edit-notes')?.value||'';

  if(btn){
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  }

  try{
    const r = await fetch(window.location.origin + _cBase + '/api.php?endpoint=consultation/consult&action=edit_order_item', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        visit_id: visitId,
        item_type: itemType,
        item_id: itemId,
        quantity: qty,
        dosage: dosage,
        frequency: frequency,
        duration: duration,
        notes: notes
      })
    });
    const j = await r.json();
    if(j.success){
      document.getElementById('c-edit-item-modal')?.remove();
      alert('✓ Item updated! Related invoice and journal entry recalculated.');
      await loadOrdersSummary();
    } else {
      if(alertEl){
        alertEl.textContent = j.message || 'Failed to update item';
        alertEl.style.display = 'block';
      } else alert(j.message || 'Error');
    }
  }catch(e){
    if(alertEl){
      alertEl.textContent = 'Error: ' + e.message;
      alertEl.style.display = 'block';
    } else alert('Error: ' + e.message);
  }finally{
    if(btn){
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save & Sync Invoice';
    }
  }
}

async function cDirectDelete(itemType, itemId, itemName){
  const visitId = <?php echo (int)($_GET['visit_id'] ?? 0); ?>;
  if(!confirm(`Are you sure you want to delete "${itemName}"?\n\nThis will remove the item, recalculate the related invoice, and sync the financial journal entry.`)){
    return;
  }

  try{
    const r = await fetch(window.location.origin + _cBase + '/api.php?endpoint=consultation/consult&action=delete_order_item', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        visit_id: visitId,
        item_type: itemType,
        item_id: itemId
      })
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Item deleted! Related invoice and journal entry updated.');
      await loadOrdersSummary();
    } else {
      alert('Failed: ' + (j.message || 'Error'));
    }
  }catch(e){
    alert('Error: ' + e.message);
  }
}
checkExistingAdmission(); // admit section is always visible now
<?php if (userHasRole('doctor')): ?>window.addEventListener('load',()=>mlDosageAlerts(consultAPI));<?php endif; ?>
</script>
<script src="js/ml-toast.js?v=<?= filemtime(__DIR__ . '/js/ml-toast.js') ?>"></script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
