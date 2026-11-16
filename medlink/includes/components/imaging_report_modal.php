<?php
/**
 * Iramiro Clinic
 * Dedicated Imaging & Ultrasound Report Forms & Print Engine Component
 */
$hospSettings = [];
try {
    if (isset($pdo)) {
        $hStmt = $pdo->query("SELECT setting_key, setting_value FROM hospital_settings");
        $hospSettings = $hStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
} catch (Exception $e) {}

$cHospitalName = $hospSettings['hospital_name'] ?? 'Iramiro Clinic';
$cHospitalAddress = $hospSettings['hospital_address'] ?? '';
$cHospitalPhone = $hospSettings['hospital_phone'] ?? '';
$cHospitalEmail = $hospSettings['hospital_email'] ?? '';

$cCurrentUserName = '';
if (isset($_SESSION['user_data'])) {
    $cCurrentUserName = trim(($_SESSION['user_data']['first_name'] ?? '') . ' ' . ($_SESSION['user_data']['last_name'] ?? ''));
}
if (!$cCurrentUserName && isset($_SESSION['username'])) {
    $cCurrentUserName = $_SESSION['username'];
}
?>

<!-- ========================================================================= -->
<!-- IMAGING & ULTRASOUND REPORT MODAL -->
<!-- ========================================================================= -->
<style>
#c-imaging-modal-overlay {
  display: none;
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15, 23, 42, 0.7);
  backdrop-filter: blur(4px);
  z-index: 99999;
  align-items: center;
  justify-content: center;
  padding: 16px;
  box-sizing: border-box;
}
#c-imaging-modal-dialog {
  background: #ffffff;
  width: 100%;
  max-width: 1150px;
  max-height: 94vh;
  border-radius: 12px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  animation: cModalFadeIn 0.2s ease-out;
}
@keyframes cModalFadeIn {
  from { opacity: 0; transform: scale(0.98) translateY(8px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
.c-img-header {
  background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
  color: white;
  padding: 14px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.c-img-tabs-bar {
  display: flex;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  overflow-x: auto;
  padding: 6px 12px 0 12px;
  gap: 4px;
  flex-shrink: 0;
}
.c-img-tab-btn {
  padding: 8px 14px;
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  border: 1px solid transparent;
  border-bottom: none;
  border-radius: 6px 6px 0 0;
  background: transparent;
  cursor: pointer;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s;
}
.c-img-tab-btn:hover {
  color: #0f766e;
  background: #f1f5f9;
}
.c-img-tab-btn.active {
  color: #0f766e;
  background: #ffffff;
  border-color: #cbd5e1;
  border-bottom-color: #ffffff;
  margin-bottom: -1px;
  box-shadow: 0 -2px 4px rgba(0,0,0,0.03);
}
.c-img-body {
  padding: 20px;
  overflow-y: auto;
  flex: 1;
  background: #ffffff;
}
.c-img-footer {
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
  padding: 12px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  gap: 12px;
}
.c-sec-title {
  font-size: 14px;
  font-weight: 700;
  color: #0f766e;
  border-bottom: 2px solid #ccfbf1;
  padding-bottom: 6px;
  margin: 16px 0 12px 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.c-sec-title:first-child {
  margin-top: 0;
}
.c-form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}
.c-fgroup {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.c-fgroup label {
  font-size: 11px;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.c-fgroup input[type="text"],
.c-fgroup input[type="number"],
.c-fgroup input[type="date"],
.c-fgroup select,
.c-fgroup textarea {
  width: 100%;
  padding: 7px 10px;
  font-size: 13px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  outline: none;
  background: #ffffff;
  box-sizing: border-box;
  color: #1e293b;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.c-fgroup input:focus,
.c-fgroup select:focus,
.c-fgroup textarea:focus {
  border-color: #0d9488;
  box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
}
.c-check-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 8px;
  background: #f8fafc;
  padding: 12px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}
.c-check-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 500;
  color: #334155;
  cursor: pointer;
}
.c-organ-box {
  background: #fcfdfd;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 12px;
}
.c-organ-title {
  font-size: 12px;
  font-weight: 700;
  color: #0f766e;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.c-preset-btn {
  padding: 3px 8px;
  font-size: 11px;
  background: #f0fdf4;
  color: #15803d;
  border: 1px solid #bbf7d0;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
}
.c-preset-btn:hover {
  background: #dcfce7;
}
</style>

<div id="c-imaging-modal-overlay">
  <div id="c-imaging-modal-dialog">
    <!-- Header -->
    <div class="c-img-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <div style="width:36px;height:36px;border-radius:8px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-file-medical-alt"></i>
        </div>
        <div>
          <div style="font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px;">
            <span id="c-modal-img-title">Ultrasound Examination & Report</span>
            <span id="c-modal-img-status-badge" style="font-size:10px;padding:2px 8px;border-radius:10px;background:rgba(255,255,255,0.25);font-weight:600;">NEW</span>
          </div>
          <div style="font-size:12px;opacity:0.9;" id="c-modal-patient-summary">Loading patient...</div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <!-- Form Type Switcher -->
        <div style="display:flex;align-items:center;background:rgba(0,0,0,0.15);padding:3px 6px;border-radius:6px;gap:6px;">
          <label style="font-size:11px;font-weight:600;margin:0;color:#f0fdfa;">REPORT FORM:</label>
          <select id="c-imaging-form-type" onchange="cSwitchImagingFormType(this.value)" style="padding:4px 8px;font-size:12px;font-weight:600;border:none;border-radius:4px;background:#ffffff;color:#0f766e;outline:none;cursor:pointer;">
            <option value="us_breast">Breast Ultrasound Examination & Report</option>
            <option value="us_obstetric">Obstetric Ultrasound Examination & Report</option>
            <option value="us_pelvic">Pelvic (Gynaecological) Ultrasound Report</option>
            <option value="us_abdomen">Abdominal Ultrasound Examination & Report</option>
            <option value="us_renal">Renal & Urinary Tract Ultrasound Report</option>
            <option value="us_prostate">Prostate Ultrasound Examination & Report</option>
            <option value="us_thyroid">Thyroid Ultrasound Examination & Report</option>
            <option value="us_scrotal">Scrotal / Testicular Ultrasound Report</option>
            <option value="us_soft_tissue">Soft Tissue / Musculoskeletal Ultrasound</option>
            <option value="ultrasound">Comprehensive Ultrasound Report (All 15 Sections)</option>
            <option value="general">General Radiology / X-Ray / CT / MRI Form</option>
          </select>
        </div>
        <!-- Order Selector (if multiple) -->
        <select id="c-imaging-order-select" onchange="cOnOrderSelectChange(this.value)" style="max-width:200px;padding:4px 8px;font-size:12px;border-radius:4px;border:none;background:#ffffff;color:#334155;outline:none;display:none;"></select>
        <button type="button" onclick="cCloseImagingModal()" style="background:transparent;border:none;color:white;font-size:18px;cursor:pointer;padding:4px 8px;" title="Close (Esc)">✕</button>
      </div>
    </div>

    <!-- ULTRASOUND TAB BAR (Visible ONLY in comprehensive mode) -->
    <div id="c-us-tabs-container" class="c-img-tabs-bar" style="display:none;">
      <button type="button" class="c-img-tab-btn active" onclick="cSwitchUsTab('tab-pt-clin')"><i class="fas fa-user-injured"></i> 1-3. Patient & Request</button>
      <button type="button" class="c-img-tab-btn" onclick="cSwitchUsTab('tab-breast')"><i class="fas fa-circle-dot"></i> Breast</button>
      <button type="button" class="c-img-tab-btn" onclick="cSwitchUsTab('tab-obstetric')"><i class="fas fa-baby"></i> Obstetric</button>
      <button type="button" class="c-img-tab-btn" onclick="cSwitchUsTab('tab-pelvis')"><i class="fas fa-venus"></i> Pelvis (Gyn)</button>
      <button type="button" class="c-img-tab-btn" onclick="cSwitchUsTab('tab-abdomen')"><i class="fas fa-lungs"></i> Abdomen</button>
      <button type="button" class="c-img-tab-btn" onclick="cSwitchUsTab('tab-renal-prostate')"><i class="fas fa-stethoscope"></i> Renal & Prostate</button>
      <button type="button" class="c-img-tab-btn" onclick="cSwitchUsTab('tab-thyroid')"><i class="fas fa-ribbon"></i> Thyroid</button>
      <button type="button" class="c-img-tab-btn" onclick="cSwitchUsTab('tab-scrotal-soft')"><i class="fas fa-dna"></i> Scrotal & Soft Tissue</button>
      <button type="button" class="c-img-tab-btn" onclick="cSwitchUsTab('tab-impression')"><i class="fas fa-file-signature"></i> Impression & Sign</button>
    </div>

    <!-- Body / Forms -->
    <div class="c-img-body">
      <!-- Hidden IDs -->
      <input type="hidden" id="c-img-order-id" value="0">
      <input type="hidden" id="c-img-order-name" value="">
      <input type="hidden" id="c-img-visit-id" value="">
      <input type="hidden" id="c-img-patient-id" value="">

      <!-- ============================================================= -->
      <!-- A. ULTRASOUND FORM -->
      <!-- ============================================================= -->
      <div id="c-panel-ultrasound">

        <!-- COMMON SECTION 1-3: PATIENT & CLINICAL INFO -->
        <div id="c-ustab-tab-pt-clin" class="c-ustab-content">
          <div class="c-sec-title">
            <span><i class="fas fa-user-circle"></i> 1. PATIENT INFORMATION</span>
            <span style="font-size:11px;font-weight:normal;color:#64748b;">Auto-populated from patient file (read only)</span>
          </div>
          <div class="c-form-grid">
            <div class="c-fgroup">
              <label>Patient Name</label>
              <input type="text" id="us-patient-name" placeholder="Full Name" readonly style="background:#f3f4f6;color:#4b5563;cursor:not-allowed;">
            </div>
            <div class="c-fgroup">
              <label>Patient ID / File No.</label>
              <input type="text" id="us-patient-no" placeholder="UHID / File #" readonly style="background:#f3f4f6;color:#4b5563;cursor:not-allowed;">
            </div>
            <div class="c-fgroup">
              <label>Date of Birth</label>
              <input type="date" id="us-dob" readonly style="background:#f3f4f6;color:#4b5563;cursor:not-allowed;">
            </div>
            <div class="c-fgroup">
              <label>Age (years)</label>
              <input type="text" id="us-age" placeholder="Age" readonly style="background:#f3f4f6;color:#4b5563;cursor:not-allowed;">
            </div>
            <div class="c-fgroup">
              <label>Sex</label>
              <select id="us-sex" disabled style="background:#f3f4f6;color:#4b5563;cursor:not-allowed;">
                <option value="">-- Select --</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="c-fgroup">
              <label>Telephone</label>
              <input type="text" id="us-phone" placeholder="Phone" readonly style="background:#f3f4f6;color:#4b5563;cursor:not-allowed;">
            </div>
            <div class="c-fgroup" style="grid-column: span 2;">
              <label>Address</label>
              <input type="text" id="us-address" placeholder="Residence / Sector / District" readonly style="background:#f3f4f6;color:#4b5563;cursor:not-allowed;">
            </div>
            <div class="c-fgroup">
              <label>Date of Examination</label>
              <input type="date" id="us-exam-date">
            </div>
            <div class="c-fgroup">
              <label>Time</label>
              <input type="text" id="us-exam-time" placeholder="HH:MM">
            </div>
            <div class="c-fgroup">
              <label>Referring Clinician</label>
              <input type="text" id="us-referring-clinician" placeholder="Dr. Name">
            </div>
            <div class="c-fgroup">
              <label>Department / Service</label>
              <input type="text" id="us-department" placeholder="OPD / Inpatient / Gyn / etc.">
            </div>
          </div>

          <div class="c-sec-title">
            <span><i class="fas fa-notes-medical"></i> 2. CLINICAL INFORMATION</span>
          </div>
          <div class="c-fgroup" style="margin-bottom:10px;">
            <label>Clinical Indication / Reason for Examination</label>
            <input type="text" id="us-clinical-indication" placeholder="e.g. Palpable breast lump, mastalgia, screening, pelvic pain, routine antenatal...">
          </div>
          <div class="c-fgroup" style="margin-bottom:10px;">
            <label>Relevant Medical / Surgical History</label>
            <input type="text" id="us-medical-history" placeholder="e.g. Previous surgeries, known lumps, biopsy history, hypertension...">
          </div>
          <div class="c-fgroup">
            <label>Relevant Laboratory / Previous Imaging Results</label>
            <input type="text" id="us-lab-results" placeholder="e.g. Previous mammogram findings, biopsy results, blood work...">
          </div>

          <div id="c-sec-requested-exams-block">
            <div class="c-sec-title">
              <span><i class="fas fa-check-square"></i> 3. ULTRASOUND EXAMINATION REQUESTED</span>
            </div>
            <div class="c-check-grid" id="us-requested-exams-grid">
              <label class="c-check-item"><input type="checkbox" id="us-req-breast" value="Breast Ultrasound"> Breast Ultrasound</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-obstetric" value="Obstetric Ultrasound"> Obstetric Ultrasound</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-pelvic" value="Pelvic Ultrasound"> Pelvic Ultrasound</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-abdominal" value="Abdominal Ultrasound"> Abdominal Ultrasound</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-renal" value="Renal / Urinary Tract Ultrasound"> Renal / Urinary Tract</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-prostate" value="Prostate Ultrasound"> Prostate Ultrasound</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-thyroid" value="Thyroid Ultrasound"> Thyroid Ultrasound</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-scrotal" value="Scrotal Ultrasound"> Scrotal Ultrasound</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-soft-tissue" value="Soft Tissue Ultrasound"> Soft Tissue Ultrasound</label>
              <label class="c-check-item"><input type="checkbox" id="us-req-hepatobiliary" value="Hepatobiliary Ultrasound"> Hepatobiliary Ultrasound</label>
              <label class="c-check-item" style="grid-column:span 2;"><input type="checkbox" id="us-req-other-chk"> Other: <input type="text" id="us-req-other-txt" style="border:1px solid #cbd5e1;padding:2px 6px;border-radius:4px;font-size:12px;margin-left:4px;" placeholder="Specify other scan"></label>
            </div>
          </div>
        </div>

        <!-- BREAST ULTRASOUND FINDINGS -->
        <div id="c-ustab-tab-breast" class="c-ustab-content">
          <div class="c-sec-title">
            <span><i class="fas fa-circle-dot"></i> BREAST ULTRASOUND FINDINGS</span>
            <button type="button" class="c-preset-btn" onclick="cSetUltrasoundBaseline('breast')"><i class="fas fa-magic"></i> Optional: Fill Normal Baseline</button>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Bilateral Breast Assessment</div>
            <div class="c-form-grid">
              <div class="c-fgroup" style="grid-column: span 2;">
                <label>Right Breast Findings</label>
                <textarea id="us-breast-right" rows="4" placeholder="Describe fibroglandular tissue pattern, skin, nipple-areola complex, ductal architecture, focal masses, cysts, calcifications (clock-face location, distance from nipple, dimensions in mm)..."></textarea>
              </div>
              <div class="c-fgroup" style="grid-column: span 2;">
                <label>Left Breast Findings</label>
                <textarea id="us-breast-left" rows="4" placeholder="Describe fibroglandular tissue pattern, skin, nipple-areola complex, ductal architecture, focal masses, cysts, calcifications (clock-face location, distance from nipple, dimensions in mm)..."></textarea>
              </div>
              <div class="c-fgroup">
                <label>Axillary Lymph Nodes</label>
                <input type="text" id="us-breast-axilla" placeholder="Right & Left axillary lymph node status, morphology, fatty hilum...">
              </div>
              <div class="c-fgroup">
                <label>Overall BI-RADS Category</label>
                <select id="us-breast-birads">
                  <option value="">-- Select BI-RADS Category --</option>
                  <option value="BI-RADS 0: Incomplete (Need additional imaging)">BI-RADS 0: Incomplete (Need additional imaging)</option>
                  <option value="BI-RADS 1: Negative (Normal examination)">BI-RADS 1: Negative (Normal examination)</option>
                  <option value="BI-RADS 2: Benign finding(s)">BI-RADS 2: Benign finding(s)</option>
                  <option value="BI-RADS 3: Probably benign (Short-interval follow-up recommended)">BI-RADS 3: Probably benign (Short-interval follow-up)</option>
                  <option value="BI-RADS 4: Suspicious abnormality (Biopsy considered)">BI-RADS 4: Suspicious abnormality (Biopsy considered)</option>
                  <option value="BI-RADS 5: Highly suggestive of malignancy">BI-RADS 5: Highly suggestive of malignancy</option>
                  <option value="BI-RADS 6: Known biopsy-proven malignancy">BI-RADS 6: Known biopsy-proven malignancy</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 4: ABDOMINAL ULTRASOUND -->
        <div id="c-ustab-tab-abdomen" class="c-ustab-content" style="display:none;">
          <div class="c-sec-title">
            <span><i class="fas fa-lungs"></i> ABDOMINAL ULTRASOUND FINDINGS</span>
            <button type="button" class="c-preset-btn" onclick="cSetUltrasoundBaseline('abdomen')"><i class="fas fa-magic"></i> Optional: Fill Normal Baseline</button>
          </div>
          <!-- Liver -->
          <div class="c-organ-box">
            <div class="c-organ-title">Liver</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Size</label>
                <select id="us-abd-liver-size">
                  <option value="">-- Select --</option>
                  <option value="Normal">Normal</option>
                  <option value="Hepatomegaly">Hepatomegaly</option>
                  <option value="Shrunken">Shrunken</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Echogenicity</label>
                <select id="us-abd-liver-echo">
                  <option value="">-- Select --</option>
                  <option value="Normal">Normal</option>
                  <option value="Increased (Fatty liver: Mild)">Increased (Fatty liver: Mild)</option>
                  <option value="Increased (Fatty liver: Moderate)">Increased (Fatty liver: Moderate)</option>
                  <option value="Increased (Fatty liver: Severe)">Increased (Fatty liver: Severe)</option>
                  <option value="Decreased">Decreased</option>
                  <option value="Heterogeneous">Heterogeneous</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Surface</label>
                <select id="us-abd-liver-surface">
                  <option value="">-- Select --</option>
                  <option value="Smooth">Smooth</option>
                  <option value="Nodular">Nodular</option>
                  <option value="Irregular">Irregular</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Focal Lesions</label>
                <input type="text" id="us-abd-liver-lesions" placeholder="None detected / Cysts / Hemangioma / Mass...">
              </div>
              <div class="c-fgroup">
                <label>Intrahepatic Biliary Ducts</label>
                <select id="us-abd-liver-ihbd">
                  <option value="">-- Select --</option>
                  <option value="Not dilated">Not dilated</option>
                  <option value="Dilated">Dilated</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Portal Vein</label>
                <input type="text" id="us-abd-liver-pv" placeholder="Portal vein caliber, flow, patency...">
              </div>
            </div>
          </div>

          <!-- Gallbladder & CBD -->
          <div class="c-organ-box">
            <div class="c-organ-title">Gallbladder & Common Bile Duct (CBD)</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Gallbladder Wall</label>
                <select id="us-abd-gb-wall">
                  <option value="">-- Select --</option>
                  <option value="Normal (<3mm)">Normal (<3mm)</option>
                  <option value="Thickened (>3mm)">Thickened (>3mm)</option>
                  <option value="Edematous / Double contour">Edematous / Double contour</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Lumen</label>
                <select id="us-abd-gb-lumen">
                  <option value="">-- Select --</option>
                  <option value="Anechoic, clear">Anechoic, clear</option>
                  <option value="Calculus / Cholelithiasis present">Calculus / Cholelithiasis present</option>
                  <option value="Biliary sludge present">Biliary sludge present</option>
                  <option value="Polyp(s) present">Polyp(s) present</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Pericholecystic Fluid</label>
                <select id="us-abd-gb-fluid">
                  <option value="">-- Select --</option>
                  <option value="Absent">Absent</option>
                  <option value="Present">Present</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Sonographic Murphy’s Sign</label>
                <select id="us-abd-gb-murphy">
                  <option value="">-- Select --</option>
                  <option value="Negative">Negative</option>
                  <option value="Positive">Positive</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>CBD Diameter</label>
                <input type="text" id="us-abd-cbd-diam" placeholder="CBD caliber, e.g. Normal (<6mm) or 4.2 mm">
              </div>
              <div class="c-fgroup">
                <label>CBD Luminal Contents</label>
                <input type="text" id="us-abd-cbd-lumen" placeholder="Clear / Calculus / Sludge...">
              </div>
            </div>
          </div>

          <!-- Pancreas & Spleen -->
          <div class="c-organ-box">
            <div class="c-organ-title">Pancreas & Spleen</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Pancreas Visualisation</label>
                <select id="us-abd-panc-vis">
                  <option value="">-- Select --</option>
                  <option value="Well visualised">Well visualised</option>
                  <option value="Partially obscured by bowel gas">Partially obscured by bowel gas</option>
                  <option value="Obscured">Obscured</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Pancreas Size & Echogenicity</label>
                <input type="text" id="us-abd-panc-echo" placeholder="Normal size, homogeneous, bulky, heterogeneous...">
              </div>
              <div class="c-fgroup">
                <label>Pancreatic Duct</label>
                <input type="text" id="us-abd-panc-duct" placeholder="Caliber, e.g. Not dilated (<2mm) / Dilated">
              </div>
              <div class="c-fgroup">
                <label>Spleen Size (Length)</label>
                <input type="text" id="us-abd-spleen-size" placeholder="e.g. Normal (<12cm) or 10.5 cm">
              </div>
              <div class="c-fgroup">
                <label>Spleen Echotexture & Lesions</label>
                <input type="text" id="us-abd-spleen-echo" placeholder="Homogeneous / Splenomegaly / Focal lesions...">
              </div>
            </div>
          </div>

          <!-- Kidneys (Abdominal view) -->
          <div class="c-organ-box">
            <div class="c-organ-title">Kidneys (Abdominal Assessment)</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Right Kidney</label>
                <input type="text" id="us-abd-rk-desc" placeholder="Size, cortex, CMD, hydronephrosis, calculi...">
              </div>
              <div class="c-fgroup">
                <label>Left Kidney</label>
                <input type="text" id="us-abd-lk-desc" placeholder="Size, cortex, CMD, hydronephrosis, calculi...">
              </div>
            </div>
          </div>

          <!-- Bladder & Abdominal Cavity -->
          <div class="c-organ-box">
            <div class="c-organ-title">Urinary Bladder & Abdominal Cavity</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Urinary Bladder</label>
                <input type="text" id="us-abd-bladder" placeholder="Distension, wall thickness, lumen, calculus...">
              </div>
              <div class="c-fgroup">
                <label>Free Fluid / Ascites</label>
                <select id="us-abd-ascites">
                  <option value="">-- Select --</option>
                  <option value="None">None</option>
                  <option value="Minimal (Pelvis)">Minimal (Pelvis)</option>
                  <option value="Minimal (Morison's pouch)">Minimal (Morison's pouch)</option>
                  <option value="Moderate">Moderate</option>
                  <option value="Gross / Marked">Gross / Marked</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Abdominal Aorta & Nodes</label>
                <input type="text" id="us-abd-aorta" placeholder="Caliber, aneurysm, para-aortic lymphadenopathy...">
              </div>
              <div class="c-fgroup">
                <label>Bowel & Peritoneal observations</label>
                <input type="text" id="us-abd-bowel" placeholder="Peristalsis, thickened loops, fluid collections...">
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 5: PELVIC ULTRASOUND -->
        <div id="c-ustab-tab-pelvis" class="c-ustab-content" style="display:none;">
          <div class="c-sec-title">
            <span><i class="fas fa-venus"></i> PELVIC ULTRASOUND FINDINGS (GYNAECOLOGICAL)</span>
            <button type="button" class="c-preset-btn" onclick="cSetUltrasoundBaseline('pelvis')"><i class="fas fa-magic"></i> Optional: Fill Normal Baseline</button>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Uterus & Endometrium</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Position</label>
                <select id="us-pel-ut-pos">
                  <option value="">-- Select --</option>
                  <option value="Anteverted, anteflexed (AVAF)">Anteverted, anteflexed (AVAF)</option>
                  <option value="Retroverted (RV)">Retroverted (RV)</option>
                  <option value="Axial / Midline">Axial / Midline</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Contour</label>
                <select id="us-pel-ut-contour">
                  <option value="">-- Select --</option>
                  <option value="Smooth, regular">Smooth, regular</option>
                  <option value="Lobulated (Fibroid uterus)">Lobulated (Fibroid uterus)</option>
                  <option value="Irregular">Irregular</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Dimensions (Length x AP x Transverse in mm)</label>
                <input type="text" id="us-pel-ut-dims" placeholder="e.g. 75 x 40 x 48 mm">
              </div>
              <div class="c-fgroup">
                <label>Myometrium & Fibroids</label>
                <input type="text" id="us-pel-ut-myo" placeholder="Homogeneous / Fibroids (size, type, location)...">
              </div>
              <div class="c-fgroup">
                <label>Endometrial Thickness (Double layer)</label>
                <input type="text" id="us-pel-endo-thick" placeholder="e.g. 7.5 mm">
              </div>
              <div class="c-fgroup">
                <label>Endometrium Appearance</label>
                <input type="text" id="us-pel-endo-desc" placeholder="Trilaminar, homogeneous, thickened, fluid in cavity...">
              </div>
            </div>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Ovaries, Adnexa & Cul-de-Sac</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Right Ovary Visualisation & Dims</label>
                <input type="text" id="us-pel-ro-dims" placeholder="Dimensions (mm), volume, follicular activity, cyst...">
              </div>
              <div class="c-fgroup">
                <label>Left Ovary Visualisation & Dims</label>
                <input type="text" id="us-pel-lo-dims" placeholder="Dimensions (mm), volume, follicular activity, cyst...">
              </div>
              <div class="c-fgroup">
                <label>Adnexal Regions / Fallopian Tubes</label>
                <input type="text" id="us-pel-adnexa" placeholder="Clear / Adnexal mass / Hydrosalpinx...">
              </div>
              <div class="c-fgroup">
                <label>Pouch of Douglas (Cul-de-sac)</label>
                <select id="us-pel-pouch">
                  <option value="">-- Select --</option>
                  <option value="No free fluid in the cul-de-sac">No free fluid in the cul-de-sac</option>
                  <option value="Minimal free physiological fluid">Minimal free physiological fluid</option>
                  <option value="Moderate free fluid collection">Moderate free fluid collection</option>
                  <option value="Significant pelvic free fluid">Significant pelvic free fluid</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 6: OBSTETRIC ULTRASOUND -->
        <div id="c-ustab-tab-obstetric" class="c-ustab-content" style="display:none;">
          <div class="c-sec-title">
            <span><i class="fas fa-baby"></i> OBSTETRIC ULTRASOUND FINDINGS</span>
            <button type="button" class="c-preset-btn" onclick="cSetUltrasoundBaseline('obstetric')"><i class="fas fa-magic"></i> Optional: Fill Normal Baseline</button>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Gestational Baseline</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>LMP (Last Menstrual Period)</label>
                <input type="date" id="us-ob-lmp" onchange="cCalcObstetricFromLMP()">
              </div>
              <div class="c-fgroup">
                <label>Clinical Gestational Age</label>
                <input type="text" id="us-ob-clin-ga" placeholder="e.g. 24 weeks 2 days">
              </div>
              <div class="c-fgroup">
                <label>Number of Fetuses</label>
                <select id="us-ob-fetuses">
                  <option value="">-- Select --</option>
                  <option value="Single intrauterine pregnancy">Single intrauterine pregnancy</option>
                  <option value="Twin pregnancy (Monochorionic Diamniotic)">Twin (MCDA)</option>
                  <option value="Twin pregnancy (Dichorionic Diamniotic)">Twin (DCDA)</option>
                  <option value="Multiple pregnancy">Multiple pregnancy</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Presentation</label>
                <select id="us-ob-presentation">
                  <option value="">-- Select --</option>
                  <option value="Cephalic">Cephalic</option>
                  <option value="Breech">Breech</option>
                  <option value="Transverse lie">Transverse lie</option>
                  <option value="Variable / Unstable">Variable / Unstable</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Fetal Cardiac Activity / FHR</label>
                <input type="text" id="us-ob-fhr" placeholder="e.g. Present, regular, 142 bpm">
              </div>
              <div class="c-fgroup">
                <label>Fetal Movements</label>
                <select id="us-ob-movements">
                  <option value="">-- Select --</option>
                  <option value="Active fetal body and limb movements observed">Active fetal body and limb movements</option>
                  <option value="Reduced fetal movements">Reduced fetal movements</option>
                  <option value="Absent fetal movement">Absent fetal movement</option>
                </select>
              </div>
            </div>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Placenta, Liquor & Cervix</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Placenta Location</label>
                <select id="us-ob-plac-loc">
                  <option value="">-- Select --</option>
                  <option value="Anterior, high">Anterior, high</option>
                  <option value="Posterior, high">Posterior, high</option>
                  <option value="Fundal">Fundal</option>
                  <option value="Right lateral">Right lateral</option>
                  <option value="Left lateral">Left lateral</option>
                  <option value="Low-lying (Not covering os)">Low-lying (Not covering os)</option>
                  <option value="Placenta previa (Marginal)">Placenta previa (Marginal)</option>
                  <option value="Placenta previa (Complete/Total)">Placenta previa (Complete/Total)</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Placenta Maturity Grade</label>
                <select id="us-ob-plac-grade">
                  <option value="">-- Select --</option>
                  <option value="Grade 0">Grade 0</option>
                  <option value="Grade I">Grade I</option>
                  <option value="Grade II">Grade II</option>
                  <option value="Grade III">Grade III</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Amniotic Fluid Volume</label>
                <select id="us-ob-amniotic">
                  <option value="">-- Select --</option>
                  <option value="Adequate / Normal (AFI normal)">Adequate / Normal</option>
                  <option value="Adequate (Single deepest vertical pocket normal)">Adequate (SDVP normal)</option>
                  <option value="Oligohydramnios (AFI < 5 cm)">Oligohydramnios</option>
                  <option value="Polyhydramnios (AFI > 24 cm)">Polyhydramnios</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Cervix & Internal Os</label>
                <input type="text" id="us-ob-cervix" placeholder="e.g. Closed, competent, cervical length: 36 mm">
              </div>
            </div>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">
              <span>Fetal Biometry</span>
              <button type="button" class="c-preset-btn" onclick="cCalcEFW()"><i class="fas fa-calculator"></i> Calculate EFW & GA</button>
            </div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>BPD (mm)</label>
                <input type="number" step="0.1" id="us-ob-bpd" placeholder="mm" onchange="cCalcEFW()">
              </div>
              <div class="c-fgroup">
                <label>HC (mm)</label>
                <input type="number" step="0.1" id="us-ob-hc" placeholder="mm" onchange="cCalcEFW()">
              </div>
              <div class="c-fgroup">
                <label>AC (mm)</label>
                <input type="number" step="0.1" id="us-ob-ac" placeholder="mm" onchange="cCalcEFW()">
              </div>
              <div class="c-fgroup">
                <label>FL (mm)</label>
                <input type="number" step="0.1" id="us-ob-fl" placeholder="mm" onchange="cCalcEFW()">
              </div>
              <div class="c-fgroup">
                <label>Estimated Fetal Weight (g)</label>
                <input type="text" id="us-ob-efw" placeholder="grams">
              </div>
              <div class="c-fgroup">
                <label>Ultrasound GA</label>
                <input type="text" id="us-ob-us-ga" placeholder="e.g. 24 weeks 0 days ± 1 wk">
              </div>
              <div class="c-fgroup">
                <label>Estimated Due Date (EDD by US)</label>
                <input type="date" id="us-ob-edd-us">
              </div>
            </div>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Fetal Anatomy Survey</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Cranium & Brain</label>
                <input type="text" id="us-ob-anat-head" placeholder="Cranial contour, midline falx, ventricles...">
              </div>
              <div class="c-fgroup">
                <label>Spine</label>
                <input type="text" id="us-ob-anat-spine" placeholder="Cervical, thoracic, lumbar, sacral spine, overlying skin...">
              </div>
              <div class="c-fgroup">
                <label>Thorax & 4-Chamber Heart</label>
                <input type="text" id="us-ob-anat-heart" placeholder="Rhythm, 4-chamber cardiac view, diaphragm...">
              </div>
              <div class="c-fgroup">
                <label>Abdomen & Stomach Bubble</label>
                <input type="text" id="us-ob-anat-abdo" placeholder="Stomach bubble, anterior abdominal wall, cord insertion...">
              </div>
              <div class="c-fgroup">
                <label>Renal & Bladder</label>
                <input type="text" id="us-ob-anat-urinary" placeholder="Kidneys in renal fossae, urinary bladder filling...">
              </div>
              <div class="c-fgroup">
                <label>Extremities & Cord</label>
                <input type="text" id="us-ob-anat-limbs" placeholder="Four limbs visualised, 3-vessel umbilical cord...">
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 7 & 8: RENAL & PROSTATE -->
        <div id="c-ustab-tab-renal-prostate" class="c-ustab-content" style="display:none;">
          <div class="c-sec-title">
            <span><i class="fas fa-stethoscope"></i> RENAL & PROSTATE ULTRASOUND FINDINGS</span>
            <button type="button" class="c-preset-btn" onclick="cSetUltrasoundBaseline('renal')"><i class="fas fa-magic"></i> Optional: Fill Normal Baseline</button>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Kidneys & Ureters</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Right Kidney (Dims & Cortex)</label>
                <input type="text" id="us-ren-rk-dims" placeholder="e.g. 105 x 44 mm, Cortex: 15 mm">
              </div>
              <div class="c-fgroup">
                <label>Right Kidney Findings</label>
                <input type="text" id="us-ren-rk-desc" placeholder="Cortex, CMD, hydronephrosis, calculi...">
              </div>
              <div class="c-fgroup">
                <label>Left Kidney (Dims & Cortex)</label>
                <input type="text" id="us-ren-lk-dims" placeholder="e.g. 108 x 46 mm, Cortex: 16 mm">
              </div>
              <div class="c-fgroup">
                <label>Left Kidney Findings</label>
                <input type="text" id="us-ren-lk-desc" placeholder="Cortex, CMD, hydronephrosis, calculi...">
              </div>
              <div class="c-fgroup" style="grid-column:span 2;">
                <label>Urinary Bladder Assessment</label>
                <input type="text" id="us-ren-bladder" placeholder="Distension, wall thickness, lumen, mucosal surface, calculi...">
              </div>
            </div>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">
              <span>Prostate Ultrasound</span>
              <button type="button" class="c-preset-btn" onclick="cCalcProstateVol()"><i class="fas fa-calculator"></i> Calculate Volume</button>
            </div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Transverse Diameter (mm)</label>
                <input type="number" step="0.1" id="us-pros-tx" placeholder="mm" onchange="cCalcProstateVol()">
              </div>
              <div class="c-fgroup">
                <label>Anteroposterior (AP) Diameter (mm)</label>
                <input type="number" step="0.1" id="us-pros-ap" placeholder="mm" onchange="cCalcProstateVol()">
              </div>
              <div class="c-fgroup">
                <label>Cranio-caudal (Length) (mm)</label>
                <input type="number" step="0.1" id="us-pros-cc" placeholder="mm" onchange="cCalcProstateVol()">
              </div>
              <div class="c-fgroup">
                <label>Estimated Prostate Volume</label>
                <input type="text" id="us-pros-vol" placeholder="Calculated volume (ml / cc)">
              </div>
              <div class="c-fgroup">
                <label>Parenchymal Echotexture</label>
                <select id="us-pros-echo">
                  <option value="">-- Select --</option>
                  <option value="Homogeneous, normal echogenicity">Homogeneous, normal echogenicity</option>
                  <option value="Heterogeneous, benign prostatic hyperplasia (BPH)">Heterogeneous (BPH changes)</option>
                  <option value="Calcifications noted (prostatoliths)">Calcifications noted</option>
                  <option value="Focal hypo-echoic nodule present">Focal hypo-echoic nodule present</option>
                </select>
              </div>
              <div class="c-fgroup">
                <label>Median Lobe Protrusion (IPP)</label>
                <input type="text" id="us-pros-ipp" placeholder="e.g. None / IPP Grade 1 (<5mm)">
              </div>
              <div class="c-fgroup">
                <label>Prostatic Capsule & Seminal Vesicles</label>
                <input type="text" id="us-pros-capsule" placeholder="Capsule intact, seminal vesicles...">
              </div>
              <div class="c-fgroup">
                <label>Post-Void Residual Urine (PVR, ml)</label>
                <input type="text" id="us-pros-pvr" placeholder="Pre-void, post-void residual...">
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 10: THYROID ULTRASOUND -->
        <div id="c-ustab-tab-thyroid" class="c-ustab-content" style="display:none;">
          <div class="c-sec-title">
            <span><i class="fas fa-ribbon"></i> THYROID ULTRASOUND FINDINGS</span>
            <button type="button" class="c-preset-btn" onclick="cSetUltrasoundBaseline('thyroid')"><i class="fas fa-magic"></i> Optional: Fill Normal Baseline</button>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">
              <span>Thyroid Lobes & Isthmus</span>
              <button type="button" class="c-preset-btn" onclick="cCalcThyroidVol()"><i class="fas fa-calculator"></i> Calculate Volume</button>
            </div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Right Lobe (L x W x AP mm)</label>
                <input type="text" id="us-thy-rl-dims" placeholder="e.g. 44 x 16 x 15 mm">
              </div>
              <div class="c-fgroup">
                <label>Right Lobe Volume (ml)</label>
                <input type="text" id="us-thy-rl-vol" placeholder="e.g. 5.5 ml">
              </div>
              <div class="c-fgroup">
                <label>Left Lobe (L x W x AP mm)</label>
                <input type="text" id="us-thy-ll-dims" placeholder="e.g. 42 x 15 x 14 mm">
              </div>
              <div class="c-fgroup">
                <label>Left Lobe Volume (ml)</label>
                <input type="text" id="us-thy-ll-vol" placeholder="e.g. 4.6 ml">
              </div>
              <div class="c-fgroup">
                <label>Isthmus Thickness (mm)</label>
                <input type="text" id="us-thy-isthmus" placeholder="e.g. 2.8 mm">
              </div>
              <div class="c-fgroup">
                <label>Parenchymal Echogenicity</label>
                <select id="us-thy-echo">
                  <option value="">-- Select --</option>
                  <option value="Normal, homogeneous echotexture">Normal, homogeneous echotexture</option>
                  <option value="Diffusely coarse / heterogeneous (Thyroiditis)">Diffusely coarse / heterogeneous (Thyroiditis)</option>
                  <option value="Hypoechoic diffuse enlargement (Graves/Hashimoto)">Hypoechoic diffuse enlargement</option>
                </select>
              </div>
              <div class="c-fgroup" style="grid-column: span 2;">
                <label>Focal Nodules / Lesions (TI-RADS if applicable)</label>
                <input type="text" id="us-thy-nodules" placeholder="None / Location, size, composition, TI-RADS score...">
              </div>
              <div class="c-fgroup" style="grid-column: span 2;">
                <label>Cervical Lymph Nodes</label>
                <input type="text" id="us-thy-nodes" placeholder="Cervical lymphadenopathy status...">
              </div>
            </div>
          </div>
        </div>

        <!-- SECTION 11 & 12: SCROTAL & SOFT TISSUE -->
        <div id="c-ustab-tab-scrotal-soft" class="c-ustab-content" style="display:none;">
          <div class="c-sec-title">
            <span><i class="fas fa-dna"></i> SCROTAL & SOFT TISSUE ULTRASOUND FINDINGS</span>
            <button type="button" class="c-preset-btn" onclick="cSetUltrasoundBaseline('scrotal')"><i class="fas fa-magic"></i> Optional: Fill Normal Baseline</button>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Scrotal / Testicular Assessment</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Right Testis</label>
                <input type="text" id="us-scrot-rt" placeholder="Dimensions (mm), echotexture, color Doppler flow...">
              </div>
              <div class="c-fgroup">
                <label>Left Testis</label>
                <input type="text" id="us-scrot-lt" placeholder="Dimensions (mm), echotexture, color Doppler flow...">
              </div>
              <div class="c-fgroup">
                <label>Epididymides</label>
                <input type="text" id="us-scrot-epi" placeholder="Bilateral epididymides, spermatocele, epididymitis...">
              </div>
              <div class="c-fgroup">
                <label>Hydrocele / Fluid</label>
                <select id="us-scrot-hydro">
                  <option value="">-- Select --</option>
                  <option value="No hydrocele detected bilaterally">No hydrocele detected bilaterally</option>
                  <option value="Minimal physiological fluid">Minimal physiological fluid</option>
                  <option value="Right hydrocele present">Right hydrocele present</option>
                  <option value="Left hydrocele present">Left hydrocele present</option>
                  <option value="Bilateral hydrocele present">Bilateral hydrocele present</option>
                </select>
              </div>
              <div class="c-fgroup" style="grid-column: span 2;">
                <label>Pampiniform Plexus / Varicocele</label>
                <input type="text" id="us-scrot-varico" placeholder="Pampiniform venous plexus, varicocele at rest/Valsalva...">
              </div>
            </div>
          </div>
          <div class="c-organ-box">
            <div class="c-organ-title">Soft Tissue / Musculoskeletal Assessment</div>
            <div class="c-form-grid">
              <div class="c-fgroup">
                <label>Anatomical Region / Site</label>
                <input type="text" id="us-soft-site" placeholder="e.g. Right thigh, anterior abdominal wall, neck swelling...">
              </div>
              <div class="c-fgroup">
                <label>Depth & Dimensions</label>
                <input type="text" id="us-soft-dims" placeholder="e.g. Subcutaneous, 25 x 18 x 12 mm">
              </div>
              <div class="c-fgroup" style="grid-column: span 2;">
                <label>Sonographic Characteristics</label>
                <input type="text" id="us-soft-desc" placeholder="Echogenicity (cystic, solid, complex), margins, acoustic transmission, color Doppler vascularity...">
              </div>
            </div>
          </div>
        </div>

        <!-- COMMON SECTION 13-15: COMMENTS, IMPRESSION & SIGNATURES -->
        <div id="c-ustab-tab-impression" class="c-ustab-content">
          <div class="c-sec-title">
            <span><i class="fas fa-comment-medical"></i> COMMENTS / ADDITIONAL FINDINGS</span>
          </div>
          <div class="c-fgroup" style="margin-bottom:14px;">
            <textarea id="us-general-comments" rows="3" placeholder="Additional observations, notes or technical limitations (optional)..."></textarea>
          </div>

          <div class="c-sec-title">
            <span><i class="fas fa-file-signature"></i> IMPRESSION / CONCLUSION</span>
          </div>
          <div class="c-form-grid" style="margin-bottom:12px;">
            <div class="c-fgroup">
              <label>1. Primary Finding / Impression</label>
              <input type="text" id="us-imp-1" placeholder="Main sonographic diagnosis">
            </div>
            <div class="c-fgroup">
              <label>2. Secondary Finding</label>
              <input type="text" id="us-imp-2" placeholder="Secondary finding (if any)">
            </div>
            <div class="c-fgroup">
              <label>3. Additional Finding</label>
              <input type="text" id="us-imp-3" placeholder="Additional finding (if any)">
            </div>
          </div>
          <div class="c-fgroup" style="margin-bottom:12px;">
            <label>Final Conclusion / Diagnostic Summary</label>
            <textarea id="us-final-conclusion" rows="2" placeholder="Clear summary statement of the examination conclusion..."></textarea>
          </div>
          <div class="c-fgroup" style="margin-bottom:16px;">
            <label>Recommendations / Advised Follow-up</label>
            <textarea id="us-recommendations" rows="2" placeholder="e.g. Clinical correlation advised; follow-up ultrasound in 6 weeks; biopsy / mammography correlation..."></textarea>
          </div>

          <div class="c-sec-title">
            <span><i class="fas fa-stamp"></i> REPORTING INFORMATION & SIGNATURES</span>
          </div>
          <div class="c-form-grid">
            <div class="c-fgroup">
              <label>Performed By (Sonographer / Clinician)</label>
              <input type="text" id="us-performed-by" placeholder="Doctor / Sonographer Name">
            </div>
            <div class="c-fgroup">
              <label>Qualification / Role</label>
              <input type="text" id="us-performed-qual" placeholder="e.g. Medical Officer / Sonographer">
            </div>
            <div class="c-fgroup">
              <label>Registration / License No.</label>
              <input type="text" id="us-performed-reg" placeholder="Medical Council Reg #">
            </div>
            <div class="c-fgroup">
              <label>Reported / Verified By</label>
              <input type="text" id="us-verified-by" placeholder="Consultant Radiologist / Reviewer">
            </div>
            <div class="c-fgroup">
              <label>Qualification / Role (Verifier)</label>
              <input type="text" id="us-verified-qual" placeholder="e.g. Consultant Radiologist / Medical Officer">
            </div>
            <div class="c-fgroup">
              <label>Reporting Date & Time</label>
              <input type="text" id="us-report-datetime">
            </div>
          </div>

          <div style="margin-top:16px;padding:10px 14px;background:#f0fdfa;border-left:4px solid #0d9488;border-radius:4px;font-size:11px;color:#0f766e;">
            <strong>IMPORTANT NOTICE:</strong> This report should be interpreted together with the patient's clinical history, physical examination, and relevant laboratory investigations. Ultrasound findings should be correlated clinically where appropriate.
          </div>
        </div>

      </div><!-- /c-panel-ultrasound -->

      <!-- ============================================================= -->
      <!-- B. GENERAL RADIOLOGY / X-RAY / CT / MRI FORM -->
      <!-- ============================================================= -->
      <div id="c-panel-general" style="display:none;">
        <div class="c-sec-title">
          <span><i class="fas fa-x-ray"></i> GENERAL RADIOLOGY & IMAGING REPORT</span>
          <span style="font-size:11px;font-weight:normal;color:#64748b;">X-Ray, CT Scan, MRI & Fluoroscopy</span>
        </div>

        <div class="c-form-grid" style="margin-bottom:12px;">
          <div class="c-fgroup">
            <label>Modality</label>
            <select id="gen-modality">
              <option value="">-- Select Modality --</option>
              <option value="Digital Radiography (X-Ray)">Digital Radiography (X-Ray)</option>
              <option value="Computed Tomography (CT)">Computed Tomography (CT)</option>
              <option value="Magnetic Resonance Imaging (MRI)">Magnetic Resonance Imaging (MRI)</option>
              <option value="Mammography">Mammography</option>
              <option value="Fluoroscopy">Fluoroscopy</option>
            </select>
          </div>
          <div class="c-fgroup" style="grid-column:span 2;">
            <label>Examination / Procedure Name</label>
            <input type="text" id="gen-exam-name" placeholder="e.g. Chest X-Ray PA View, Lumbar Spine AP/Lat, Non-contrast Head CT">
          </div>
          <div class="c-fgroup">
            <label>Examination Date</label>
            <input type="date" id="gen-exam-date">
          </div>
        </div>

        <div class="c-fgroup" style="margin-bottom:12px;">
          <label>Clinical Indication / History</label>
          <input type="text" id="gen-clinical-info" placeholder="Reason for examination, symptoms, trauma, suspected pathology...">
        </div>

        <div class="c-fgroup" style="margin-bottom:12px;">
          <label>Technique & Projections</label>
          <input type="text" id="gen-technique" placeholder="e.g. Standard PA erect view of the chest without contrast">
        </div>

        <div class="c-fgroup" style="margin-bottom:12px;">
          <label>Findings (Detailed Narrative Report)</label>
          <textarea id="gen-findings" rows="6" placeholder="Describe findings systematically (e.g. Heart size, lung fields, mediastinum, bony thorax, soft tissues)..."></textarea>
        </div>

        <div class="c-fgroup" style="margin-bottom:12px;">
          <label>Impression / Conclusion</label>
          <textarea id="gen-impression" rows="3" placeholder="Key diagnostic impression / conclusion..."></textarea>
        </div>

        <div class="c-fgroup" style="margin-bottom:16px;">
          <label>Recommendations / Notes</label>
          <textarea id="gen-recommendations" rows="2" placeholder="Further evaluation, follow-up, or clinical correlation..."></textarea>
        </div>

        <div class="c-sec-title">
          <span><i class="fas fa-user-md"></i> Reporting Radiologist / Medical Officer</span>
        </div>
        <div class="c-form-grid">
          <div class="c-fgroup">
            <label>Reported By</label>
            <input type="text" id="gen-reported-by">
          </div>
          <div class="c-fgroup">
            <label>Qualification / Role</label>
            <input type="text" id="gen-role" placeholder="e.g. Medical Officer / Radiologist">
          </div>
          <div class="c-fgroup">
            <label>Date & Time</label>
            <input type="text" id="gen-datetime">
          </div>
        </div>
      </div><!-- /c-panel-general -->

      <!-- Imaging scans and diagnostic attachments -->
      <div class="c-attachments-section" style="margin-top:20px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
        <div class="c-sec-title" style="margin-top:0;margin-bottom:6px;">
          <span><i class="fas fa-paperclip"></i> Imaging Scans &amp; Diagnostic Attachments</span>
          <span id="c-img-att-count" style="font-size:11px;font-weight:600;background:#0f766e;color:white;padding:2px 8px;border-radius:10px;">0 attached</span>
        </div>
        <p style="font-size:12px;color:#64748b;margin:0 0 12px 0;">
          Upload X-ray films, ultrasound snapshots, scan printouts, or diagnostic reports. Maximum 30MB per file.
        </p>
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
          <div id="c-img-dropzone" class="c-att-zone" style="flex:1;min-width:240px;cursor:pointer;" onclick="document.getElementById('c-img-file-input').click()">
            <i class="fas fa-cloud-upload-alt" style="font-size:24px;color:#0d9488;margin-bottom:4px;"></i>
            <div style="font-size:12.5px;font-weight:600;color:#1e293b;">Click to upload or drag and drop files here</div>
            <div style="font-size:11px;color:#64748b;">JPG, PNG, WEBP, GIF, PDF, DOC, DOCX, DICOM</div>
            <input type="file" id="c-img-file-input" style="display:none" multiple accept="image/*,.pdf,.dcm,.dicom,.doc,.docx" onchange="cHandleAttachmentUpload(this.files)">
          </div>
          <div id="c-img-upload-spinner" style="display:none;font-size:11px;color:#0d9488;font-weight:600;align-items:center;gap:6px;">
            <i class="fas fa-spinner fa-spin"></i> Uploading file...
          </div>
        </div>
        <div id="c-img-attachments-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;"></div>
      </div>

    </div><!-- /c-img-body -->

    <!-- Footer Action Bar -->
    <div class="c-img-footer">
      <div style="display:flex;align-items:center;gap:8px;">
        <button type="button" class="btn btn-secondary" onclick="cCloseImagingModal()" style="padding:8px 16px;border-radius:6px;font-size:13px;">Cancel</button>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <button type="button" class="btn btn-outline-primary" onclick="cPrintImagingReportFromModal()" style="padding:8px 16px;border-radius:6px;font-size:13px;display:flex;align-items:center;gap:6px;font-weight:600;">
          <i class="fas fa-print"></i> Print Official Report
        </button>
        <button type="button" class="btn btn-primary" onclick="cSaveImagingResult('completed')" style="padding:8px 20px;border-radius:6px;font-size:13px;background:#0d9488;border-color:#0f766e;color:white;display:flex;align-items:center;gap:6px;font-weight:600;">
          <i class="fas fa-check-circle"></i> Save & Finalize Report
        </button>
      </div>
    </div>

  </div><!-- /c-imaging-modal-dialog -->
</div><!-- /c-imaging-modal-overlay -->

<!-- Hidden container for print rendering -->
<div id="c-imaging-print-area" style="display:none;"></div>

<!-- ========================================================================= -->
<!-- JAVASCRIPT ENGINE -->
<!-- ========================================================================= -->
<script>
if(typeof window.cEsc !== 'function') {
  window._imSignature = <?php
    require_once __DIR__ . '/../signature.php';
    $imSig = get_user_signature($pdo, (int)($_SESSION['user_id'] ?? 0));
    $imStamp = get_hospital_stamp($pdo);
    echo json_encode($imSig['data_uri'] ?? null);
  ?>;
  window._imStamp = <?php echo json_encode($imStamp ?? null); ?>;
  window.cSignature = function(){ return window._imSignature ? '<img src="'+window._imSignature+'" style="max-height:42px;max-width:150px;display:block;margin:0 auto 2px;">Signature' : 'Signature'; };
  window.cStamp = function(){ return window._imStamp ? '<img src="'+window._imStamp+'" alt="Official stamp" style="max-height:70px;max-width:110px;object-fit:contain;">' : ''+cEsc(_cHosp.name)+'<br>OFFICIAL STAMP'; };
  window.cEsc = function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');};
}
// Global state for imaging module
window._currentImagingResults = [];
window._activeImagingOrder = null;
window._currentAttachments = [];
const _cHosp = <?php echo json_encode(['name'=>$cHospitalName,'address'=>$cHospitalAddress,'phone'=>$cHospitalPhone,'email'=>$cHospitalEmail], JSON_HEX_TAG|JSON_HEX_AMP); ?>;
const _cCurrentDoctorDefault = <?php echo json_encode($cCurrentUserName ?: ''); ?>;

// Reset all form fields to empty strings (NO hardcoded text values)
function cResetImagingForm() {
  const inputs = document.querySelectorAll('#c-imaging-modal-dialog input:not([type="hidden"]):not([type="checkbox"])');
  inputs.forEach(el => el.value = '');

  const textareas = document.querySelectorAll('#c-imaging-modal-dialog textarea');
  textareas.forEach(el => el.value = '');

  const selects = document.querySelectorAll('#c-imaging-modal-dialog select');
  selects.forEach(el => {
    if(el.id !== 'c-imaging-form-type' && el.id !== 'c-imaging-order-select') {
      el.value = '';
    }
  });

  const checkboxes = document.querySelectorAll('#c-imaging-modal-dialog input[type="checkbox"]');
  checkboxes.forEach(el => el.checked = false);

  window._currentAttachments = [];
  cRenderAttachmentsList();
}

// ============ IMAGING ATTACHMENTS ==========
function cRenderAttachmentsList() {
  const listEl = document.getElementById('c-img-attachments-list');
  const countEl = document.getElementById('c-img-att-count');
  if (!listEl) return;
  const list = window._currentAttachments || [];
  if (countEl) countEl.textContent = list.length + ' attached';
  if (!list.length) {
    listEl.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:12px;font-size:12px;color:#94a3b8;font-style:italic;">No files attached yet.</div>';
    return;
  }
  listEl.innerHTML = list.map((att, idx) => {
    const q = v => cEsc(v).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    const name = q(att.name || 'Attachment');
    // only our own upload folder is linkable (blocks javascript: and external URLs)
    const path = /^uploads\/imaging\/[\w.\-]+$/.test(att.path || '') ? q(att.path) : '';
    const type = String(att.type || '').toLowerCase();
    const isImage = type.indexOf('image/') === 0 || /\.(jpg|jpeg|png|webp|gif|bmp)$/i.test(att.name || '');
    const isPdf = type.indexOf('pdf') !== -1 || /\.pdf$/i.test(att.name || '');
    const size = att.size ? (att.size > 1048576 ? (att.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(att.size / 1024)) + ' KB') : '';
    const visual = isImage
      ? `<a href="${path}" target="_blank" rel="noopener"><img src="${path}" alt="Imaging attachment" style="width:58px;height:58px;object-fit:cover;border-radius:6px;border:1px solid #cbd5e1"></a>`
      : `<div style="width:58px;height:58px;display:flex;align-items:center;justify-content:center;border-radius:6px;background:#fff;border:1px solid #cbd5e1;color:${isPdf ? '#dc2626' : '#0284c7'};font-size:24px"><i class="fas ${isPdf ? 'fa-file-pdf' : 'fa-file-medical'}"></i></div>`;
    return `<div style="display:flex;align-items:center;gap:9px;padding:8px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;min-width:0">
      ${visual}<div style="min-width:0;flex:1"><div title="${name}" style="font-size:11px;font-weight:600;color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${name}</div>
      <div style="font-size:10px;color:#64748b;margin-top:4px">${size} <a href="${path}" target="_blank" style="color:#0d9488;font-weight:600;margin-left:6px">View</a></div></div>
      <button type="button" onclick="cDeleteAttachment(${idx})" title="Remove attachment" style="border:0;background:none;color:#dc2626;cursor:pointer;padding:4px"><i class="fas fa-trash-alt"></i></button></div>`;
  }).join('');
}

window.cDeleteAttachment = function(idx) {
  if (window._currentAttachments && window._currentAttachments[idx]) {
    window._currentAttachments.splice(idx, 1);
    cRenderAttachmentsList();
  }
};

window.cHandleAttachmentUpload = async function(files) {
  if (!files || !files.length) return;
  const spinner = document.getElementById('c-img-upload-spinner');
  if (spinner) spinner.style.display = 'inline-flex';
  const api = typeof consultAPI !== 'undefined' ? consultAPI : (window.location.origin + (window.appBasePath || window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'))) + '/api.php?endpoint=consultation/consult');
  try {
    for (const file of files) {
      const fd = new FormData();
      fd.append('file', file);
      const response = await fetch(api + '&action=upload_imaging_attachment', { method: 'POST', body: fd });
      const json = await response.json();
      if (!response.ok || !json.success) throw new Error(json.message || 'Upload failed');
      window._currentAttachments.push(json.data);
    }
  } catch (e) {
    alert('Imaging attachment upload failed: ' + e.message);
  } finally {
    if (spinner) spinner.style.display = 'none';
    const input = document.getElementById('c-img-file-input');
    if (input) input.value = '';
    cRenderAttachmentsList();
  }
};

document.addEventListener('DOMContentLoaded', function() {
  const zone = document.getElementById('c-img-dropzone');
  if (!zone) return;
  ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.style.background = '#ecfdf5'; }));
  ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.style.background = ''; }));
  zone.addEventListener('drop', e => cHandleAttachmentUpload(e.dataTransfer.files));
});

// Form titles dictionary
const _cFormTitles = {
  'us_breast': 'Breast Ultrasound Examination & Report',
  'us_obstetric': 'Obstetric Ultrasound Examination & Report',
  'us_pelvic': 'Pelvic (Gynaecological) Ultrasound Report',
  'us_abdomen': 'Abdominal Ultrasound Examination & Report',
  'us_renal': 'Renal & Urinary Tract Ultrasound Report',
  'us_prostate': 'Prostate Ultrasound Examination & Report',
  'us_thyroid': 'Thyroid Ultrasound Examination & Report',
  'us_scrotal': 'Scrotal / Testicular Ultrasound Report',
  'us_soft_tissue': 'Soft Tissue / Musculoskeletal Ultrasound Report',
  'ultrasound': 'Comprehensive Ultrasound Examination & Report',
  'general': 'General Radiology & Imaging Report'
};

// Switch between dedicated forms
window.cSwitchImagingFormType = function(type) {
  const pUS = document.getElementById('c-panel-ultrasound');
  const pGen = document.getElementById('c-panel-general');
  const tabContainer = document.getElementById('c-us-tabs-container');
  const reqExamsBlock = document.getElementById('c-sec-requested-exams-block');

  // Update Modal Header Title
  const customTitle = _cFormTitles[type] || 'Ultrasound Examination & Report';
  document.getElementById('c-modal-img-title').textContent = customTitle;

  if(type === 'general') {
    pUS.style.display = 'none';
    pGen.style.display = 'block';
    tabContainer.style.display = 'none';
    return;
  }

  pUS.style.display = 'block';
  pGen.style.display = 'none';

  // If comprehensive mode, show tabs bar and hide individual forms except active tab
  if(type === 'ultrasound') {
    tabContainer.style.display = 'flex';
    if(reqExamsBlock) reqExamsBlock.style.display = 'block';
    cSwitchUsTab('tab-pt-clin');
    return;
  }

  // Focused Single-Examination Forms:
  tabContainer.style.display = 'none';
  if(reqExamsBlock) reqExamsBlock.style.display = 'none'; // in single form, title says it all

  // Hide all organ sections first
  const allSections = [
    'tab-abdomen', 'tab-pelvis', 'tab-obstetric',
    'tab-renal-prostate', 'tab-breast', 'tab-thyroid',
    'tab-scrotal-soft'
  ];
  allSections.forEach(s => {
    const el = document.getElementById('c-ustab-' + s);
    if(el) el.style.display = 'none';
  });

  // Always show Patient & Clinical info and Comments & Impression
  document.getElementById('c-ustab-tab-pt-clin').style.display = 'block';
  document.getElementById('c-ustab-tab-impression').style.display = 'block';

  // Uncheck all requested exams and check the specific one
  document.querySelectorAll('#us-requested-exams-grid input[type="checkbox"]').forEach(c => c.checked = false);

  if(type === 'us_breast') {
    document.getElementById('c-ustab-tab-breast').style.display = 'block';
    document.getElementById('us-req-breast').checked = true;
  } else if(type === 'us_obstetric') {
    document.getElementById('c-ustab-tab-obstetric').style.display = 'block';
    document.getElementById('us-req-obstetric').checked = true;
  } else if(type === 'us_pelvic') {
    document.getElementById('c-ustab-tab-pelvis').style.display = 'block';
    document.getElementById('us-req-pelvic').checked = true;
  } else if(type === 'us_abdomen') {
    document.getElementById('c-ustab-tab-abdomen').style.display = 'block';
    document.getElementById('us-req-abdominal').checked = true;
  } else if(type === 'us_renal' || type === 'us_prostate') {
    document.getElementById('c-ustab-tab-renal-prostate').style.display = 'block';
    if(type === 'us_renal') document.getElementById('us-req-renal').checked = true;
    if(type === 'us_prostate') document.getElementById('us-req-prostate').checked = true;
  } else if(type === 'us_thyroid') {
    document.getElementById('c-ustab-tab-thyroid').style.display = 'block';
    document.getElementById('us-req-thyroid').checked = true;
  } else if(type === 'us_scrotal' || type === 'us_soft_tissue') {
    document.getElementById('c-ustab-tab-scrotal-soft').style.display = 'block';
    if(type === 'us_scrotal') document.getElementById('us-req-scrotal').checked = true;
    if(type === 'us_soft_tissue') document.getElementById('us-req-soft-tissue').checked = true;
  }
};

// Switch Ultrasound Tabs (In comprehensive mode)
window.cSwitchUsTab = function(tabId) {
  document.querySelectorAll('.c-ustab-content').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.c-img-tab-btn').forEach(btn => btn.classList.remove('active'));

  const target = document.getElementById('c-ustab-' + tabId);
  if(target) target.style.display = 'block';

  const clickedBtn = Array.from(document.querySelectorAll('.c-img-tab-btn')).find(b => b.getAttribute('onclick')?.includes(tabId));
  if(clickedBtn) clickedBtn.classList.add('active');
};

// ============ LOAD IMAGING RESULTS (For Step 3) ============
async function loadImagingResults() {
  const container = document.getElementById('c-imaging-results');
  if(!container) return;
  const vId = typeof visitId !== 'undefined' ? visitId : (window._currentVisit?.id || 0);
  if(!vId) {
    container.innerHTML = '<p style="color:var(--gray-400);font-size:13px;text-align:center;">No visit selected.</p>';
    return;
  }

  try {
    const api = typeof consultAPI !== 'undefined' ? consultAPI : (window.location.origin + ((typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '')) + '/api.php?endpoint=consultation/consult');
    const r = await fetch(api + '&action=imaging_results&visit_id=' + vId);
    const j = await r.json();
    if(j.success && j.data && j.data.length) {
      window._currentImagingResults = j.data;
      let rowsHtml = '';
      j.data.forEach((item, idx) => {
        const isDone = item.status === 'completed' || (item.result && item.result.length > 5);
        const statusBadge = isDone
          ? '<span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;"><i class="fas fa-check-circle"></i> Completed</span>'
          : '<span style="background:#fff7ed;color:#9a3412;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;"><i class="fas fa-clock"></i> Ordered</span>';

        let previewText = '<span style="color:var(--gray-400);font-style:italic;">Results pending...</span>';
        let formBadge = '';
        if(item.result) {
          try {
            const parsed = JSON.parse(item.result);
            const fType = parsed.form_type || 'ultrasound';
            const fTitle = _cFormTitles[fType] || 'Ultrasound';
            formBadge = `<span style="font-size:10px;background:#f0fdfa;color:#0f766e;border:1px solid #ccfbf1;padding:1px 6px;border-radius:4px;font-weight:600;margin-bottom:2px;display:inline-block;">${cEsc(fTitle)}</span><br>`;
            if (Array.isArray(parsed.attachments) && parsed.attachments.length) {
              formBadge += `<span style="font-size:10px;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;padding:1px 6px;border-radius:4px;font-weight:600;display:inline-block;margin-bottom:3px;"><i class="fas fa-paperclip"></i> ${parsed.attachments.length} attachment${parsed.attachments.length === 1 ? '' : 's'}</span><br>`;
            }

            if(fType.startsWith('us_') || fType === 'ultrasound') {
              const conc = parsed.conclusion?.final || parsed.conclusion?.imp_1 || parsed.impression?.[0] || '';
              if(conc) {
                previewText = `${formBadge}<div style="font-size:12px;font-weight:600;color:#0f766e;">${cEsc(conc)}</div>`;
              } else {
                previewText = `${formBadge}<span style="font-size:12px;color:#0f766e;font-weight:600;">Report recorded</span>`;
              }
              if(parsed.conclusion?.recommendations) {
                previewText += `<div style="font-size:11px;color:#64748b;">Rec: ${cEsc(parsed.conclusion.recommendations)}</div>`;
              }
            } else {
              const imp = parsed.impression || parsed.findings || 'Radiology report recorded';
              previewText = `${formBadge}<div style="font-size:12px;font-weight:600;color:#1e293b;">${cEsc(imp.substring(0, 120))}${imp.length > 120 ? '...' : ''}</div>`;
            }
          } catch(e) {
            previewText = `<div style="font-size:12px;color:#334155;">${cEsc(String(item.result).substring(0, 100))}</div>`;
          }
        }

        const actionBtns = `
          <div style="display:flex;align-items:center;justify-content:center;gap:6px;">
            <button type="button" onclick="cOpenImagingResultModalFromList(${item.id})" style="padding:4px 10px;background:${isDone ? '#e0e7ff' : '#059669'};color:${isDone ? '#3730a3' : '#ffffff'};border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;">
              <i class="fas fa-${isDone ? 'edit' : 'plus'}"></i> ${isDone ? 'Edit Result' : 'Add Result'}
            </button>
            ${isDone ? `
            <button type="button" onclick="cPrintImagingReportById(${item.id})" style="padding:4px 10px;background:#4338ca;color:white;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;">
              <i class="fas fa-print"></i> Print
            </button>` : ''}
          </div>
        `;

        rowsHtml += `
          <tr style="border-bottom:1px solid var(--gray-100);">
            <td style="padding:10px 8px;color:var(--gray-500);font-weight:600;">${idx+1}</td>
            <td style="padding:10px 8px;font-weight:600;color:var(--gray-800);">${cEsc(item.imaging_name || item.description || 'Imaging Study')}</td>
            <td style="padding:10px 8px;">
              <span style="padding:2px 8px;border-radius:8px;font-size:10px;font-weight:600;background:#fef3c7;color:#92400e;text-transform:uppercase;">
                ${cEsc(item.order_type || 'Radiology')}
              </span>
            </td>
            <td style="padding:10px 8px;">${previewText}</td>
            <td style="padding:10px 8px;font-size:11px;">
              <div style="color:var(--gray-700);font-weight:600;">By: ${cEsc(item.completed_by_name || item.ordered_by_name || '—')}</div>
              <div style="color:var(--gray-400);font-size:10px;">${item.completed_at ? new Date(item.completed_at).toLocaleString() : (item.ordered_at ? new Date(item.ordered_at).toLocaleString() : '—')}</div>
            </td>
            <td style="padding:10px 8px;text-align:center;">${statusBadge}</td>
            <td style="padding:10px 8px;text-align:center;white-space:nowrap;">${actionBtns}</td>
          </tr>
        `;
      });

      container.innerHTML = `
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="border-bottom:2px solid var(--gray-200);background:var(--gray-50);text-align:left;">
              <th style="padding:10px 8px;font-weight:600;">#</th>
              <th style="padding:10px 8px;font-weight:600;">Imaging Examination</th>
              <th style="padding:10px 8px;font-weight:600;">Type</th>
              <th style="padding:10px 8px;font-weight:600;">Result / Impression Summary</th>
              <th style="padding:10px 8px;font-weight:600;">Provider / Date</th>
              <th style="padding:10px 8px;text-align:center;font-weight:600;">Status</th>
              <th style="padding:10px 8px;text-align:center;font-weight:600;">Actions</th>
            </tr>
          </thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      `;
    } else {
      container.innerHTML = '<p style="color:var(--gray-400);font-size:13px;text-align:center;padding:12px;"><i class="fas fa-x-ray" style="font-size:20px;display:block;margin-bottom:6px;"></i>No imaging orders recorded for this visit.</p>';
    }
  } catch(e) {
    container.innerHTML = '<p style="color:var(--gray-400);font-size:13px;text-align:center;">Could not load imaging results.</p>';
  }
}
window.loadImagingResults = loadImagingResults;

// ============ OPEN MODAL ============
window.cOpenImagingResultModal = function(idx) {
  let order = null;
  const items = window._currentImagingItems || [];
  if(idx >= 0 && items[idx]) {
    order = items[idx];
  } else if(items.length > 0) {
    order = items[0];
  }
  cInitImagingModalWithOrder(order);
};

window.cOpenImagingResultModalFromList = function(orderId) {
  let order = (window._currentImagingResults || []).find(it => String(it.id) === String(orderId));
  if(!order) order = (window._currentImagingItems || []).find(it => String(it.id || it.order_id) === String(orderId));
  cInitImagingModalWithOrder(order);
};

function cInitImagingModalWithOrder(order) {
  cResetImagingForm();
  window._activeImagingOrder = order;

  const v = window._currentVisit || {};
  const p = window._currentPatient || {};

  const orderId = order ? (order.order_id || order.id || 0) : 0;
  const orderName = order ? (order.imaging_name || order.description || 'Ultrasound Examination') : 'Ultrasound Examination';

  document.getElementById('c-img-order-id').value = orderId;
  document.getElementById('c-img-order-name').value = orderName;
  document.getElementById('c-img-visit-id').value = v.id || (typeof visitId !== 'undefined' ? visitId : 0);
  document.getElementById('c-img-patient-id').value = p.id || (typeof patientId !== 'undefined' ? patientId : 0);

  // Header Title
  document.getElementById('c-modal-img-title').textContent = orderName;
  const isDone = order && (order.status === 'completed' || (order.result && order.result.length > 5));
  const badge = document.getElementById('c-modal-img-status-badge');
  badge.textContent = isDone ? 'COMPLETED' : 'NEW / DRAFT';
  badge.style.background = isDone ? '#059669' : 'rgba(255,255,255,0.25)';

  // Patient summary in modal header
  const pName = ((p.first_name||'') + ' ' + (p.last_name||'')).trim() || '';
  const pNo = p.patient_no || p.id || '—';
  const pAge = p.date_of_birth ? (Math.floor((Date.now() - new Date(p.date_of_birth).getTime()) / (365.25 * 24 * 3600 * 1000)) + ' yrs') : '—';
  const pGen = p.gender || '—';
  document.getElementById('c-modal-patient-summary').textContent = `${pName || 'Patient'} | ID: ${pNo} | ${pAge} | ${pGen}`;

  // Populate Section 1 fields only with real patient data
  document.getElementById('us-patient-name').value = pName;
  document.getElementById('us-patient-no').value = p.patient_no || p.id || '';
  document.getElementById('us-dob').value = p.date_of_birth || '';
  document.getElementById('us-age').value = pAge.replace(' yrs', '').replace('—', '');
  if(p.gender) {
    const gLower = p.gender.toLowerCase();
    if(gLower.startsWith('f')) document.getElementById('us-sex').value = 'Female';
    else if(gLower.startsWith('m')) document.getElementById('us-sex').value = 'Male';
    else document.getElementById('us-sex').value = 'Other';
  }
  document.getElementById('us-phone').value = p.phone || '';
  document.getElementById('us-address').value = p.address || '';
  document.getElementById('us-exam-date').value = new Date().toISOString().slice(0, 10);
  document.getElementById('us-exam-time').value = new Date().toTimeString().slice(0, 5);
  document.getElementById('us-referring-clinician').value = v.doctor_name || v.attending_doctor || '';
  document.getElementById('us-department').value = v.department_name || v.department || 'Outpatient Department (OPD)';

  // Section 2: Clinical info - only populate if real complaint exists, otherwise empty
  document.getElementById('us-clinical-indication').value = v.chief_complaint || v.presenting_complaints || '';
  document.getElementById('us-medical-history').value = p.chronic_diseases || p.medical_history || '';
  document.getElementById('us-lab-results').value = '';

  // General Radiology fields
  document.getElementById('gen-exam-name').value = orderName;
  document.getElementById('gen-exam-date').value = new Date().toISOString().slice(0, 10);
  document.getElementById('gen-clinical-info').value = v.chief_complaint || v.presenting_complaints || '';
  document.getElementById('gen-reported-by').value = _cCurrentDoctorDefault;
  document.getElementById('gen-datetime').value = new Date().toISOString().slice(0, 10) + ' ' + new Date().toTimeString().slice(0, 5);

  // Section 15 Signatures: default to current doctor
  document.getElementById('us-performed-by').value = _cCurrentDoctorDefault;
  document.getElementById('us-verified-by').value = _cCurrentDoctorDefault;
  document.getElementById('us-report-datetime').value = new Date().toISOString().slice(0, 10) + ' ' + new Date().toTimeString().slice(0, 5);

  // Auto-detect examination form type
  const lowerName = orderName.toLowerCase();
  let defaultType = 'us_breast'; // default to breast if matching or fallback
  if(lowerName.includes('breast') || lowerName.includes('mammo') || lowerName.includes('sein')) {
    defaultType = 'us_breast';
  } else if(lowerName.includes('obstet') || lowerName.includes('pregnan') || lowerName.includes('fetal') || lowerName.includes('grossesse')) {
    defaultType = 'us_obstetric';
  } else if(lowerName.includes('pelvi') || lowerName.includes('gyn')) {
    defaultType = 'us_pelvic';
  } else if(lowerName.includes('abdo') || lowerName.includes('hepato') || lowerName.includes('liver') || lowerName.includes('gall')) {
    defaultType = 'us_abdomen';
  } else if(lowerName.includes('prostat')) {
    defaultType = 'us_prostate';
  } else if(lowerName.includes('renal') || lowerName.includes('kidney') || lowerName.includes('urinary')) {
    defaultType = 'us_renal';
  } else if(lowerName.includes('thyroid') || lowerName.includes('neck')) {
    defaultType = 'us_thyroid';
  } else if(lowerName.includes('scrot') || lowerName.includes('testi')) {
    defaultType = 'us_scrotal';
  } else if(lowerName.includes('soft') || lowerName.includes('musculo')) {
    defaultType = 'us_soft_tissue';
  } else if(lowerName.includes('x-ray') || lowerName.includes('xray') || lowerName.includes('ct scan') || lowerName.includes('mri') || lowerName.includes('radiography')) {
    defaultType = 'general';
  } else {
    defaultType = 'us_breast';
  }

  // If order already has saved result, restore its exact saved form type
  if(order && order.result) {
    try {
      const data = typeof order.result === 'string' ? JSON.parse(order.result) : order.result;
      if(data.form_type) defaultType = data.form_type;
    } catch(e) {}
  }

  document.getElementById('c-imaging-form-type').value = defaultType;
  cSwitchImagingFormType(defaultType);

  // Load existing parsed result if already saved by the doctor
  if(order && order.result) {
    try {
      const data = typeof order.result === 'string' ? JSON.parse(order.result) : order.result;
      cPopulateFormFromResult(data);
    } catch(e) {
      if(defaultType === 'general') {
        document.getElementById('gen-findings').value = order.result;
      } else {
        document.getElementById('us-general-comments').value = order.result;
      }
    }
  }

  // Setup Order select dropdown if multiple orders exist
  const select = document.getElementById('c-imaging-order-select');
  const items = window._currentImagingItems || window._currentImagingResults || [];
  if(items.length > 1) {
    select.innerHTML = items.map((it, i) => `<option value="${it.order_id || it.id || i}" ${(it.order_id || it.id) == orderId ? 'selected' : ''}>${cEsc(it.imaging_name || it.description || 'Order #'+(i+1))}</option>`).join('');
    select.style.display = 'inline-block';
  } else {
    select.style.display = 'none';
  }

  const modal = document.getElementById('c-imaging-modal-overlay');
  if(modal) modal.style.display = 'flex';
}

function cOnOrderSelectChange(val) {
  const items = window._currentImagingItems || window._currentImagingResults || [];
  const found = items.find((it, i) => String(it.order_id || it.id || i) === String(val));
  if(found) {
    cInitImagingModalWithOrder(found);
  }
}

function cCloseImagingModal() {
  const modal = document.getElementById('c-imaging-modal-overlay');
  if(modal) modal.style.display = 'none';
}

// Close on Escape key
document.addEventListener('keydown', (e) => {
  if(e.key === 'Escape') {
    const modal = document.getElementById('c-imaging-modal-overlay');
    if(modal && modal.style.display === 'flex') {
      cCloseImagingModal();
    }
  }
});

// ============ OPTIONAL BASELINE PRESETS ============
window.cSetUltrasoundBaseline = function(type) {
  if(type === 'breast') {
    document.getElementById('us-breast-right').value = 'Normal fibroglandular pattern. Skin and nipple-areola complex normal. No discrete solid mass, suspicious calcification cluster or cyst detected.';
    document.getElementById('us-breast-left').value = 'Normal fibroglandular pattern. Skin and nipple-areola complex normal. No discrete solid mass, suspicious calcification cluster or cyst detected.';
    document.getElementById('us-breast-axilla').value = 'Bilateral axillae normal; no pathological lymphadenopathy observed.';
    document.getElementById('us-breast-birads').value = 'BI-RADS 1: Negative (Normal examination)';
    if(!document.getElementById('us-imp-1').value) document.getElementById('us-imp-1').value = 'Normal bilateral breast ultrasound (BI-RADS 1).';
    if(!document.getElementById('us-final-conclusion').value) document.getElementById('us-final-conclusion').value = 'No sonographic evidence of discrete benign or malignant lesion in either breast.';
    if(!document.getElementById('us-recommendations').value) document.getElementById('us-recommendations').value = 'Routine annual screening / clinical breast examination.';
  } else if(type === 'abdomen') {
    document.getElementById('us-abd-liver-size').value = 'Normal';
    document.getElementById('us-abd-liver-echo').value = 'Normal';
    document.getElementById('us-abd-liver-surface').value = 'Smooth';
    document.getElementById('us-abd-liver-lesions').value = 'None detected';
    document.getElementById('us-abd-liver-ihbd').value = 'Not dilated';
    document.getElementById('us-abd-liver-pv').value = 'Normal caliber (<13mm), patent';
    document.getElementById('us-abd-gb-wall').value = 'Normal (<3mm)';
    document.getElementById('us-abd-gb-lumen').value = 'Anechoic, clear';
    document.getElementById('us-abd-gb-fluid').value = 'Absent';
    document.getElementById('us-abd-gb-murphy').value = 'Negative';
    document.getElementById('us-abd-cbd-diam').value = 'Normal (<6mm)';
    document.getElementById('us-abd-cbd-lumen').value = 'Clear, no calculus seen';
    document.getElementById('us-abd-panc-vis').value = 'Well visualised';
    document.getElementById('us-abd-panc-echo').value = 'Normal size, homogeneous echotexture';
    document.getElementById('us-abd-panc-duct').value = 'Not dilated (<2mm)';
    document.getElementById('us-abd-spleen-size').value = 'Normal (<12cm)';
    document.getElementById('us-abd-spleen-echo').value = 'Homogeneous, no focal lesion';
    document.getElementById('us-abd-rk-desc').value = 'Normal size, cortical thickness & CMD preserved. No hydronephrosis or calculus.';
    document.getElementById('us-abd-lk-desc').value = 'Normal size, cortical thickness & CMD preserved. No hydronephrosis or calculus.';
    document.getElementById('us-abd-bladder').value = 'Adequately distended, smooth wall, anechoic lumen, no calculus or mass seen';
    document.getElementById('us-abd-ascites').value = 'None';
    document.getElementById('us-abd-aorta').value = 'Normal caliber, no aneurysm';
    document.getElementById('us-abd-bowel').value = 'Normal peristalsis, no localized mass or collection';

    if(!document.getElementById('us-imp-1').value) document.getElementById('us-imp-1').value = 'Normal sonographic examination of the abdomen.';
    if(!document.getElementById('us-final-conclusion').value) document.getElementById('us-final-conclusion').value = 'No sonographic evidence of organomegaly, focal mass lesion, calculus or free peritoneal fluid.';
    if(!document.getElementById('us-recommendations').value) document.getElementById('us-recommendations').value = 'Clinical correlation advised.';
  } else if(type === 'pelvis') {
    document.getElementById('us-pel-ut-pos').value = 'Anteverted, anteflexed (AVAF)';
    document.getElementById('us-pel-ut-contour').value = 'Smooth, regular';
    document.getElementById('us-pel-ut-dims').value = '75 x 40 x 48 mm';
    document.getElementById('us-pel-ut-myo').value = 'Homogeneous echotexture, no leiomyoma seen';
    document.getElementById('us-pel-endo-thick').value = '7.5 mm';
    document.getElementById('us-pel-endo-desc').value = 'Regular, trilaminar appearance';
    document.getElementById('us-pel-ro-dims').value = 'Visualised, normal size with physiological follicular activity';
    document.getElementById('us-pel-lo-dims').value = 'Visualised, normal size with physiological follicular activity';
    document.getElementById('us-pel-adnexa').value = 'Bilateral adnexa clear, no mass or hydrosalpinx';
    document.getElementById('us-pel-pouch').value = 'No free fluid in the cul-de-sac';

    if(!document.getElementById('us-imp-1').value) document.getElementById('us-imp-1').value = 'Normal pelvic ultrasound examination.';
    if(!document.getElementById('us-final-conclusion').value) document.getElementById('us-final-conclusion').value = 'Normal anteverted uterus with regular endometrium. Bilateral normal ovaries. No adnexal mass.';
    if(!document.getElementById('us-recommendations').value) document.getElementById('us-recommendations').value = 'Clinical correlation advised.';
  } else if(type === 'obstetric') {
    document.getElementById('us-ob-fetuses').value = 'Single intrauterine pregnancy';
    document.getElementById('us-ob-presentation').value = 'Cephalic';
    document.getElementById('us-ob-fhr').value = 'Present, regular';
    document.getElementById('us-ob-movements').value = 'Active fetal body and limb movements observed';
    document.getElementById('us-ob-plac-loc').value = 'Anterior, high';
    document.getElementById('us-ob-plac-grade').value = 'Grade I';
    document.getElementById('us-ob-amniotic').value = 'Adequate / Normal (AFI normal)';
    document.getElementById('us-ob-cervix').value = 'Closed, competent';

    document.getElementById('us-ob-anat-head').value = 'Normal cranial contour, intact midline falx, symmetrical ventricles';
    document.getElementById('us-ob-anat-spine').value = 'Intact cervical, thoracic, lumbar and sacral segments';
    document.getElementById('us-ob-anat-heart').value = 'Regular rhythm, 4-chamber view normal';
    document.getElementById('us-ob-anat-abdo').value = 'Stomach bubble visualised, intact abdominal wall';
    document.getElementById('us-ob-anat-urinary').value = 'Both kidneys in renal fossae, bladder filling';
    document.getElementById('us-ob-anat-limbs').value = 'Four limbs visualised, 3-vessel umbilical cord';

    if(!document.getElementById('us-imp-1').value) document.getElementById('us-imp-1').value = 'Single viable intrauterine pregnancy.';
    if(!document.getElementById('us-final-conclusion').value) document.getElementById('us-final-conclusion').value = 'Single viable fetus with active cardiac activity. Gross fetal anatomy survey unremarkable.';
    if(!document.getElementById('us-recommendations').value) document.getElementById('us-recommendations').value = 'Routine antenatal care.';
  } else if(type === 'renal') {
    document.getElementById('us-ren-rk-dims').value = 'Normal size, cortex preserved';
    document.getElementById('us-ren-rk-desc').value = 'Normal echogenicity, no hydronephrosis or calculus';
    document.getElementById('us-ren-lk-dims').value = 'Normal size, cortex preserved';
    document.getElementById('us-ren-lk-desc').value = 'Normal echogenicity, no hydronephrosis or calculus';
    document.getElementById('us-ren-bladder').value = 'Well distended, smooth wall, no calculus or mass';
    document.getElementById('us-pros-echo').value = 'Homogeneous, normal echogenicity';
    document.getElementById('us-pros-ipp').value = 'None';
    document.getElementById('us-pros-capsule').value = 'Intact';
    document.getElementById('us-pros-pvr').value = 'Insignificant residual volume';

    if(!document.getElementById('us-imp-1').value) document.getElementById('us-imp-1').value = 'Normal renal and urinary tract ultrasound.';
    if(!document.getElementById('us-final-conclusion').value) document.getElementById('us-final-conclusion').value = 'No sonographic evidence of hydronephrosis, calculus or focal renal lesion.';
  } else if(type === 'thyroid') {
    document.getElementById('us-thy-echo').value = 'Normal, homogeneous echotexture';
    document.getElementById('us-thy-nodules').value = 'No solid or cystic thyroid nodule detected';
    document.getElementById('us-thy-nodes').value = 'No suspicious cervical lymphadenopathy';
    if(!document.getElementById('us-imp-1').value) document.getElementById('us-imp-1').value = 'Normal thyroid ultrasound.';
  } else if(type === 'scrotal') {
    document.getElementById('us-scrot-rt').value = 'Normal size and echotexture, normal vascularity';
    document.getElementById('us-scrot-lt').value = 'Normal size and echotexture, normal vascularity';
    document.getElementById('us-scrot-epi').value = 'Bilateral epididymides normal';
    document.getElementById('us-scrot-hydro').value = 'No hydrocele detected bilaterally';
    document.getElementById('us-scrot-varico').value = 'No varicocele detected';
    if(!document.getElementById('us-imp-1').value) document.getElementById('us-imp-1').value = 'Normal scrotal ultrasound.';
  }
};

// ============ CALCULATORS ============
window.cCalcProstateVol = function() {
  const tx = parseFloat(document.getElementById('us-pros-tx').value) || 0;
  const ap = parseFloat(document.getElementById('us-pros-ap').value) || 0;
  const cc = parseFloat(document.getElementById('us-pros-cc').value) || 0;
  if(tx > 0 && ap > 0 && cc > 0) {
    const vol = ((tx * ap * cc * 0.523) / 1000).toFixed(1);
    document.getElementById('us-pros-vol').value = vol + ' ml / cc' + (vol > 25 ? ' (Enlarged)' : ' (Normal)');
  }
};

window.cCalcThyroidVol = function() {
  const parseDims = (str) => {
    const parts = (str || '').split(/[x*]/).map(p => parseFloat(p.trim())).filter(p => !isNaN(p));
    return parts.length === 3 ? parts : null;
  };
  const rd = parseDims(document.getElementById('us-thy-rl-dims').value);
  if(rd) {
    const rvol = ((rd[0] * rd[1] * rd[2] * 0.479) / 1000).toFixed(1);
    document.getElementById('us-thy-rl-vol').value = rvol + ' ml';
  }
  const ld = parseDims(document.getElementById('us-thy-ll-dims').value);
  if(ld) {
    const lvol = ((ld[0] * ld[1] * ld[2] * 0.479) / 1000).toFixed(1);
    document.getElementById('us-thy-ll-vol').value = lvol + ' ml';
  }
};

window.cCalcObstetricFromLMP = function() {
  const lmpVal = document.getElementById('us-ob-lmp').value;
  if(!lmpVal) return;
  const lmp = new Date(lmpVal);
  if(isNaN(lmp.getTime())) return;

  const today = new Date();
  const diffDays = Math.floor((today - lmp) / (1000 * 60 * 60 * 24));
  if(diffDays > 0) {
    const w = Math.floor(diffDays / 7);
    const d = diffDays % 7;
    document.getElementById('us-ob-clin-ga').value = `${w} weeks ${d} days`;

    const edd = new Date(lmp.getTime() + (280 * 24 * 3600 * 1000));
    document.getElementById('us-ob-edd-us').value = edd.toISOString().slice(0, 10);
  }
};

window.cCalcEFW = function() {
  const bpd = parseFloat(document.getElementById('us-ob-bpd').value) || 0;
  const ac = parseFloat(document.getElementById('us-ob-ac').value) || 0;
  const fl = parseFloat(document.getElementById('us-ob-fl').value) || 0;

  if(bpd > 10 && ac > 10 && fl > 10) {
    const bpd_cm = bpd / 10;
    const ac_cm = ac / 10;
    const fl_cm = fl / 10;
    const logBW = 1.335 - (0.0034 * ac_cm * fl_cm) + (0.0316 * bpd_cm) + (0.0457 * ac_cm) + (0.1623 * fl_cm);
    const efw = Math.round(Math.pow(10, logBW));
    if(efw > 50 && efw < 6000) {
      document.getElementById('us-ob-efw').value = efw + ' g';
    }
  }

  if(fl > 10 && !document.getElementById('us-ob-us-ga').value) {
    const estWeeks = Math.round((fl / 3) + 2);
    if(estWeeks >= 10 && estWeeks <= 42) {
      document.getElementById('us-ob-us-ga').value = `${estWeeks} weeks 0 days ± 1 wk`;
    }
  }
};

// ============ COLLECT FORM DATA ============
function cCollectReportData() {
  const formType = document.getElementById('c-imaging-form-type').value;

  if(formType === 'general') {
    const p = window._currentPatient || {};
    const pName = ((p.first_name||'') + ' ' + (p.last_name||'')).trim() || p.name || '';
    return {
      form_type: 'general',
      patient: {
        name: pName,
        patient_no: p.patient_no || p.id || '',
        dob: p.date_of_birth || p.dob || '',
        age: p.age || (p.date_of_birth ? Math.floor((Date.now() - new Date(p.date_of_birth).getTime()) / (365.25 * 24 * 3600 * 1000)) : ''),
        gender: p.gender || p.sex || '',
        phone: p.phone || '',
        address: p.address || ''
      },
      modality: document.getElementById('gen-modality').value,
      exam_name: document.getElementById('gen-exam-name').value,
      exam_date: document.getElementById('gen-exam-date').value,
      clinical_info: document.getElementById('gen-clinical-info').value,
      technique: document.getElementById('gen-technique').value,
      findings: document.getElementById('gen-findings').value,
      impression: document.getElementById('gen-impression').value,
      recommendations: document.getElementById('gen-recommendations').value,
      reported_by: document.getElementById('gen-reported-by').value,
      role: document.getElementById('gen-role').value,
      datetime: document.getElementById('gen-datetime').value
    };
  }

  const reqExams = [];
  document.querySelectorAll('#us-requested-exams-grid input[type="checkbox"]').forEach(cb => {
    if(cb.checked && cb.id !== 'us-req-other-chk') reqExams.push(cb.value);
  });
  if(document.getElementById('us-req-other-chk').checked) {
    const otherVal = document.getElementById('us-req-other-txt').value.trim();
    if(otherVal) reqExams.push('Other: ' + otherVal);
  }

  return {
    form_type: formType,
    patient: {
      name: document.getElementById('us-patient-name').value,
      patient_no: document.getElementById('us-patient-no').value,
      dob: document.getElementById('us-dob').value,
      age: document.getElementById('us-age').value,
      sex: document.getElementById('us-sex').value,
      phone: document.getElementById('us-phone').value,
      address: document.getElementById('us-address').value,
      exam_date: document.getElementById('us-exam-date').value,
      exam_time: document.getElementById('us-exam-time').value,
      referring_clinician: document.getElementById('us-referring-clinician').value,
      department: document.getElementById('us-department').value
    },
    clinical: {
      indication: document.getElementById('us-clinical-indication').value,
      medical_history: document.getElementById('us-medical-history').value,
      lab_results: document.getElementById('us-lab-results').value
    },
    requested_exams: reqExams,
    findings: {
      breast: {
        right: document.getElementById('us-breast-right').value,
        left: document.getElementById('us-breast-left').value,
        axilla: document.getElementById('us-breast-axilla').value,
        birads: document.getElementById('us-breast-birads').value
      },
      abdomen: {
        liver_size: document.getElementById('us-abd-liver-size').value,
        liver_echo: document.getElementById('us-abd-liver-echo').value,
        liver_surface: document.getElementById('us-abd-liver-surface').value,
        liver_lesions: document.getElementById('us-abd-liver-lesions').value,
        liver_ihbd: document.getElementById('us-abd-liver-ihbd').value,
        liver_pv: document.getElementById('us-abd-liver-pv').value,
        gb_wall: document.getElementById('us-abd-gb-wall').value,
        gb_lumen: document.getElementById('us-abd-gb-lumen').value,
        gb_fluid: document.getElementById('us-abd-gb-fluid').value,
        gb_murphy: document.getElementById('us-abd-gb-murphy').value,
        cbd_diam: document.getElementById('us-abd-cbd-diam').value,
        cbd_lumen: document.getElementById('us-abd-cbd-lumen').value,
        panc_vis: document.getElementById('us-abd-panc-vis').value,
        panc_echo: document.getElementById('us-abd-panc-echo').value,
        panc_duct: document.getElementById('us-abd-panc-duct').value,
        spleen_size: document.getElementById('us-abd-spleen-size').value,
        spleen_echo: document.getElementById('us-abd-spleen-echo').value,
        rk_desc: document.getElementById('us-abd-rk-desc').value,
        lk_desc: document.getElementById('us-abd-lk-desc').value,
        bladder: document.getElementById('us-abd-bladder').value,
        ascites: document.getElementById('us-abd-ascites').value,
        aorta: document.getElementById('us-abd-aorta').value,
        bowel: document.getElementById('us-abd-bowel').value
      },
      pelvis: {
        ut_pos: document.getElementById('us-pel-ut-pos').value,
        ut_contour: document.getElementById('us-pel-ut-contour').value,
        ut_dims: document.getElementById('us-pel-ut-dims').value,
        ut_myo: document.getElementById('us-pel-ut-myo').value,
        endo_thick: document.getElementById('us-pel-endo-thick').value,
        endo_desc: document.getElementById('us-pel-endo-desc').value,
        ro_dims: document.getElementById('us-pel-ro-dims').value,
        lo_dims: document.getElementById('us-pel-lo-dims').value,
        adnexa: document.getElementById('us-pel-adnexa').value,
        pouch: document.getElementById('us-pel-pouch').value
      },
      obstetric: {
        lmp: document.getElementById('us-ob-lmp').value,
        clin_ga: document.getElementById('us-ob-clin-ga').value,
        fetuses: document.getElementById('us-ob-fetuses').value,
        presentation: document.getElementById('us-ob-presentation').value,
        fhr: document.getElementById('us-ob-fhr').value,
        movements: document.getElementById('us-ob-movements').value,
        plac_loc: document.getElementById('us-ob-plac-loc').value,
        plac_grade: document.getElementById('us-ob-plac-grade').value,
        amniotic: document.getElementById('us-ob-amniotic').value,
        cervix: document.getElementById('us-ob-cervix').value,
        bpd: document.getElementById('us-ob-bpd').value,
        hc: document.getElementById('us-ob-hc').value,
        ac: document.getElementById('us-ob-ac').value,
        fl: document.getElementById('us-ob-fl').value,
        efw: document.getElementById('us-ob-efw').value,
        us_ga: document.getElementById('us-ob-us-ga').value,
        edd_us: document.getElementById('us-ob-edd-us').value,
        head: document.getElementById('us-ob-anat-head').value,
        spine: document.getElementById('us-ob-anat-spine').value,
        heart: document.getElementById('us-ob-anat-heart').value,
        abdo: document.getElementById('us-ob-anat-abdo').value,
        urinary: document.getElementById('us-ob-anat-urinary').value,
        limbs: document.getElementById('us-ob-anat-limbs').value
      },
      renal: {
        rk_dims: document.getElementById('us-ren-rk-dims').value,
        rk_desc: document.getElementById('us-ren-rk-desc').value,
        lk_dims: document.getElementById('us-ren-lk-dims').value,
        lk_desc: document.getElementById('us-ren-lk-desc').value,
        bladder: document.getElementById('us-ren-bladder').value
      },
      prostate: {
        tx: document.getElementById('us-pros-tx').value,
        ap: document.getElementById('us-pros-ap').value,
        cc: document.getElementById('us-pros-cc').value,
        vol: document.getElementById('us-pros-vol').value,
        echo: document.getElementById('us-pros-echo').value,
        ipp: document.getElementById('us-pros-ipp').value,
        capsule: document.getElementById('us-pros-capsule').value,
        pvr: document.getElementById('us-pros-pvr').value
      },
      thyroid: {
        rl_dims: document.getElementById('us-thy-rl-dims').value,
        rl_vol: document.getElementById('us-thy-rl-vol').value,
        ll_dims: document.getElementById('us-thy-ll-dims').value,
        ll_vol: document.getElementById('us-thy-ll-vol').value,
        isthmus: document.getElementById('us-thy-isthmus').value,
        echo: document.getElementById('us-thy-echo').value,
        nodules: document.getElementById('us-thy-nodules').value,
        nodes: document.getElementById('us-thy-nodes').value
      },
      scrotal: {
        rt: document.getElementById('us-scrot-rt').value,
        lt: document.getElementById('us-scrot-lt').value,
        epi: document.getElementById('us-scrot-epi').value,
        hydro: document.getElementById('us-scrot-hydro').value,
        varico: document.getElementById('us-scrot-varico').value
      },
      soft_tissue: {
        site: document.getElementById('us-soft-site').value,
        dims: document.getElementById('us-soft-dims').value,
        desc: document.getElementById('us-soft-desc').value
      }
    },
    general_comments: document.getElementById('us-general-comments').value,
    conclusion: {
      imp_1: document.getElementById('us-imp-1').value,
      imp_2: document.getElementById('us-imp-2').value,
      imp_3: document.getElementById('us-imp-3').value,
      final: document.getElementById('us-final-conclusion').value,
      recommendations: document.getElementById('us-recommendations').value
    },
    reporting: {
      performed_by: document.getElementById('us-performed-by').value,
      performed_qual: document.getElementById('us-performed-qual').value,
      performed_reg: document.getElementById('us-performed-reg').value,
      verified_by: document.getElementById('us-verified-by').value,
      verified_qual: document.getElementById('us-verified-qual').value,
      datetime: document.getElementById('us-report-datetime').value
    }
  };
}

// Populate form when opening existing result saved by doctor
function cPopulateFormFromResult(data) {
  if(!data) return;
  const fType = data.form_type || 'ultrasound';
  document.getElementById('c-imaging-form-type').value = fType;
  cSwitchImagingFormType(fType);

  if(fType === 'general') {
    if(data.modality) document.getElementById('gen-modality').value = data.modality;
    if(data.exam_name) document.getElementById('gen-exam-name').value = data.exam_name;
    if(data.exam_date) document.getElementById('gen-exam-date').value = data.exam_date;
    if(data.clinical_info) document.getElementById('gen-clinical-info').value = data.clinical_info;
    if(data.technique) document.getElementById('gen-technique').value = data.technique;
    if(data.findings) document.getElementById('gen-findings').value = data.findings;
    if(data.impression) document.getElementById('gen-impression').value = data.impression;
    if(data.recommendations) document.getElementById('gen-recommendations').value = data.recommendations;
    if(data.reported_by) document.getElementById('gen-reported-by').value = data.reported_by;
    if(data.role) document.getElementById('gen-role').value = data.role;
    if(data.datetime) document.getElementById('gen-datetime').value = data.datetime;
    return;
  }

  if(data.patient) {
    const pt = data.patient;
    if(pt.name) document.getElementById('us-patient-name').value = pt.name;
    if(pt.patient_no) document.getElementById('us-patient-no').value = pt.patient_no;
    if(pt.dob) document.getElementById('us-dob').value = pt.dob;
    if(pt.age) document.getElementById('us-age').value = pt.age;
    if(pt.sex) document.getElementById('us-sex').value = pt.sex;
    if(pt.phone) document.getElementById('us-phone').value = pt.phone;
    if(pt.address) document.getElementById('us-address').value = pt.address;
    if(pt.exam_date) document.getElementById('us-exam-date').value = pt.exam_date;
    if(pt.exam_time) document.getElementById('us-exam-time').value = pt.exam_time;
    if(pt.referring_clinician) document.getElementById('us-referring-clinician').value = pt.referring_clinician;
    if(pt.department) document.getElementById('us-department').value = pt.department;
  }

  if(data.clinical) {
    if(data.clinical.indication) document.getElementById('us-clinical-indication').value = data.clinical.indication;
    if(data.clinical.medical_history) document.getElementById('us-medical-history').value = data.clinical.medical_history;
    if(data.clinical.lab_results) document.getElementById('us-lab-results').value = data.clinical.lab_results;
  }

  if(data.requested_exams && Array.isArray(data.requested_exams)) {
    document.querySelectorAll('#us-requested-exams-grid input[type="checkbox"]').forEach(cb => cb.checked = false);
    data.requested_exams.forEach(ex => {
      const match = Array.from(document.querySelectorAll('#us-requested-exams-grid input[type="checkbox"]')).find(cb => cb.value === ex);
      if(match) match.checked = true;
      else if(ex.startsWith('Other:')) {
        document.getElementById('us-req-other-chk').checked = true;
        document.getElementById('us-req-other-txt').value = ex.replace('Other:', '').trim();
      }
    });
  }

  const f = data.findings || {};
  if(f.breast) {
    const b = f.breast;
    if(b.right) document.getElementById('us-breast-right').value = b.right;
    if(b.left) document.getElementById('us-breast-left').value = b.left;
    if(b.axilla) document.getElementById('us-breast-axilla').value = b.axilla;
    if(b.birads) document.getElementById('us-breast-birads').value = b.birads;
  }

  if(f.abdomen) {
    const a = f.abdomen;
    if(a.liver_size) document.getElementById('us-abd-liver-size').value = a.liver_size;
    if(a.liver_echo) document.getElementById('us-abd-liver-echo').value = a.liver_echo;
    if(a.liver_surface) document.getElementById('us-abd-liver-surface').value = a.liver_surface;
    if(a.liver_lesions) document.getElementById('us-abd-liver-lesions').value = a.liver_lesions;
    if(a.liver_ihbd) document.getElementById('us-abd-liver-ihbd').value = a.liver_ihbd;
    if(a.liver_pv) document.getElementById('us-abd-liver-pv').value = a.liver_pv;
    if(a.gb_wall) document.getElementById('us-abd-gb-wall').value = a.gb_wall;
    if(a.gb_lumen) document.getElementById('us-abd-gb-lumen').value = a.gb_lumen;
    if(a.gb_fluid) document.getElementById('us-abd-gb-fluid').value = a.gb_fluid;
    if(a.gb_murphy) document.getElementById('us-abd-gb-murphy').value = a.gb_murphy;
    if(a.cbd_diam) document.getElementById('us-abd-cbd-diam').value = a.cbd_diam;
    if(a.cbd_lumen) document.getElementById('us-abd-cbd-lumen').value = a.cbd_lumen;
    if(a.panc_vis) document.getElementById('us-abd-panc-vis').value = a.panc_vis;
    if(a.panc_echo) document.getElementById('us-abd-panc-echo').value = a.panc_echo;
    if(a.panc_duct) document.getElementById('us-abd-panc-duct').value = a.panc_duct;
    if(a.spleen_size) document.getElementById('us-abd-spleen-size').value = a.spleen_size;
    if(a.spleen_echo) document.getElementById('us-abd-spleen-echo').value = a.spleen_echo;
    if(a.rk_desc) document.getElementById('us-abd-rk-desc').value = a.rk_desc;
    if(a.lk_desc) document.getElementById('us-abd-lk-desc').value = a.lk_desc;
    if(a.bladder) document.getElementById('us-abd-bladder').value = a.bladder;
    if(a.ascites) document.getElementById('us-abd-ascites').value = a.ascites;
    if(a.aorta) document.getElementById('us-abd-aorta').value = a.aorta;
    if(a.bowel) document.getElementById('us-abd-bowel').value = a.bowel;
  }

  if(f.pelvis) {
    const p = f.pelvis;
    if(p.ut_pos) document.getElementById('us-pel-ut-pos').value = p.ut_pos;
    if(p.ut_contour) document.getElementById('us-pel-ut-contour').value = p.ut_contour;
    if(p.ut_dims) document.getElementById('us-pel-ut-dims').value = p.ut_dims;
    if(p.ut_myo) document.getElementById('us-pel-ut-myo').value = p.ut_myo;
    if(p.endo_thick) document.getElementById('us-pel-endo-thick').value = p.endo_thick;
    if(p.endo_desc) document.getElementById('us-pel-endo-desc').value = p.endo_desc;
    if(p.ro_dims) document.getElementById('us-pel-ro-dims').value = p.ro_dims;
    if(p.lo_dims) document.getElementById('us-pel-lo-dims').value = p.lo_dims;
    if(p.adnexa) document.getElementById('us-pel-adnexa').value = p.adnexa;
    if(p.pouch) document.getElementById('us-pel-pouch').value = p.pouch;
  }

  if(f.obstetric) {
    const o = f.obstetric;
    if(o.lmp) document.getElementById('us-ob-lmp').value = o.lmp;
    if(o.clin_ga) document.getElementById('us-ob-clin-ga').value = o.clin_ga;
    if(o.fetuses) document.getElementById('us-ob-fetuses').value = o.fetuses;
    if(o.presentation) document.getElementById('us-ob-presentation').value = o.presentation;
    if(o.fhr) document.getElementById('us-ob-fhr').value = o.fhr;
    if(o.movements) document.getElementById('us-ob-movements').value = o.movements;
    if(o.plac_loc) document.getElementById('us-ob-plac-loc').value = o.plac_loc;
    if(o.plac_grade) document.getElementById('us-ob-plac-grade').value = o.plac_grade;
    if(o.amniotic) document.getElementById('us-ob-amniotic').value = o.amniotic;
    if(o.cervix) document.getElementById('us-ob-cervix').value = o.cervix;
    if(o.bpd) document.getElementById('us-ob-bpd').value = o.bpd;
    if(o.hc) document.getElementById('us-ob-hc').value = o.hc;
    if(o.ac) document.getElementById('us-ob-ac').value = o.ac;
    if(o.fl) document.getElementById('us-ob-fl').value = o.fl;
    if(o.efw) document.getElementById('us-ob-efw').value = o.efw;
    if(o.us_ga) document.getElementById('us-ob-us-ga').value = o.us_ga;
    if(o.edd_us) document.getElementById('us-ob-edd-us').value = o.edd_us;
    if(o.head) document.getElementById('us-ob-anat-head').value = o.head;
    if(o.spine) document.getElementById('us-ob-anat-spine').value = o.spine;
    if(o.heart) document.getElementById('us-ob-anat-heart').value = o.heart;
    if(o.abdo) document.getElementById('us-ob-anat-abdo').value = o.abdo;
    if(o.urinary) document.getElementById('us-ob-anat-urinary').value = o.urinary;
    if(o.limbs) document.getElementById('us-ob-anat-limbs').value = o.limbs;
  }

  if(f.renal) {
    const r = f.renal;
    if(r.rk_dims) document.getElementById('us-ren-rk-dims').value = r.rk_dims;
    if(r.rk_desc) document.getElementById('us-ren-rk-desc').value = r.rk_desc;
    if(r.lk_dims) document.getElementById('us-ren-lk-dims').value = r.lk_dims;
    if(r.lk_desc) document.getElementById('us-ren-lk-desc').value = r.lk_desc;
    if(r.bladder) document.getElementById('us-ren-bladder').value = r.bladder;
  }

  if(f.prostate) {
    const pr = f.prostate;
    if(pr.tx) document.getElementById('us-pros-tx').value = pr.tx;
    if(pr.ap) document.getElementById('us-pros-ap').value = pr.ap;
    if(pr.cc) document.getElementById('us-pros-cc').value = pr.cc;
    if(pr.vol) document.getElementById('us-pros-vol').value = pr.vol;
    if(pr.echo) document.getElementById('us-pros-echo').value = pr.echo;
    if(pr.ipp) document.getElementById('us-pros-ipp').value = pr.ipp;
    if(pr.capsule) document.getElementById('us-pros-capsule').value = pr.capsule;
    if(pr.pvr) document.getElementById('us-pros-pvr').value = pr.pvr;
  }

  if(f.thyroid) {
    const t = f.thyroid;
    if(t.rl_dims) document.getElementById('us-thy-rl-dims').value = t.rl_dims;
    if(t.rl_vol) document.getElementById('us-thy-rl-vol').value = t.rl_vol;
    if(t.ll_dims) document.getElementById('us-thy-ll-dims').value = t.ll_dims;
    if(t.ll_vol) document.getElementById('us-thy-ll-vol').value = t.ll_vol;
    if(t.isthmus) document.getElementById('us-thy-isthmus').value = t.isthmus;
    if(t.echo) document.getElementById('us-thy-echo').value = t.echo;
    if(t.nodules) document.getElementById('us-thy-nodules').value = t.nodules;
    if(t.nodes) document.getElementById('us-thy-nodes').value = t.nodes;
  }

  if(f.scrotal) {
    const sc = f.scrotal;
    if(sc.rt) document.getElementById('us-scrot-rt').value = sc.rt;
    if(sc.lt) document.getElementById('us-scrot-lt').value = sc.lt;
    if(sc.epi) document.getElementById('us-scrot-epi').value = sc.epi;
    if(sc.hydro) document.getElementById('us-scrot-hydro').value = sc.hydro;
    if(sc.varico) document.getElementById('us-scrot-varico').value = sc.varico;
  }

  if(f.soft_tissue) {
    const st = f.soft_tissue;
    if(st.site) document.getElementById('us-soft-site').value = st.site;
    if(st.dims) document.getElementById('us-soft-dims').value = st.dims;
    if(st.desc) document.getElementById('us-soft-desc').value = st.desc;
  }

  if(data.general_comments) document.getElementById('us-general-comments').value = data.general_comments;

  if(data.conclusion) {
    const c = data.conclusion;
    if(c.imp_1) document.getElementById('us-imp-1').value = c.imp_1;
    if(c.imp_2) document.getElementById('us-imp-2').value = c.imp_2;
    if(c.imp_3) document.getElementById('us-imp-3').value = c.imp_3;
    if(c.final) document.getElementById('us-final-conclusion').value = c.final;
    if(c.recommendations) document.getElementById('us-recommendations').value = c.recommendations;
  }

  if(data.reporting) {
    const rep = data.reporting;
    if(rep.performed_by) document.getElementById('us-performed-by').value = rep.performed_by;
    if(rep.performed_qual) document.getElementById('us-performed-qual').value = rep.performed_qual;
    if(rep.performed_reg) document.getElementById('us-performed-reg').value = rep.performed_reg;
    if(rep.verified_by) document.getElementById('us-verified-by').value = rep.verified_by;
    if(rep.verified_qual) document.getElementById('us-verified-qual').value = rep.verified_qual;
    if(rep.datetime) document.getElementById('us-report-datetime').value = rep.datetime;
  }
  window._currentAttachments = Array.isArray(data.attachments) ? data.attachments : [];
  cRenderAttachmentsList();
}

// ============ SAVE IMAGING RESULT ============
window.cSaveImagingResult = async function(status = 'completed') {
  if (window._cImgSaving) return; // ignore repeat clicks while a save is in flight
  const orderId = document.getElementById('c-img-order-id').value;
  const visitIdVal = document.getElementById('c-img-visit-id').value;
  const patientIdVal = document.getElementById('c-img-patient-id').value;
  const imagingNameVal = document.getElementById('c-img-order-name').value;

  const reportData = cCollectReportData();

  // An X-ray report must carry the image (the server checks the same)
  if (status === 'completed' && /x-?ray|radiograph/i.test(imagingNameVal) && !(window._currentAttachments || []).length) {
    alert('An X-ray image must be attached before the report can be completed.');
    return;
  }

  window._cImgSaving = true;
  try {
    const api = typeof consultAPI !== 'undefined' ? consultAPI : (window.location.origin + ((typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '')) + '/api.php?endpoint=consultation/consult');
    const r = await fetch(api + '&action=imaging_result', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: orderId,
        visit_id: visitIdVal,
        patient_id: patientIdVal,
        imaging_name: imagingNameVal,
        status: status,
        result: Object.assign({}, reportData, { attachments: window._currentAttachments || [] })
      })
    });
    const j = await r.json();
    if(j.success) {
      alert('Imaging report saved and finalized successfully!');
      cCloseImagingModal();
      if(typeof loadOrdersSummary === 'function') await loadOrdersSummary();
      await loadImagingResults();
    } else {
      alert('Error saving report: ' + (j.message || 'Unknown error'));
    }
  } catch(e) {
    alert('Failed to save report: ' + e.message);
  } finally { window._cImgSaving = false; }
};

// ============ PRINT ENGINE ============
window.cPrintImagingReportFromModal = function() {
  const reportData = cCollectReportData();
  const orderName = document.getElementById('c-img-order-name').value;
  cExecutePrintReport(reportData, { imaging_name: orderName });
};

window.cPrintImagingReport = function(idx) {
  const item = (window._currentImagingItems || [])[idx];
  if(!item) return;
  cPrintImagingReportItem(item);
};

window.cPrintImagingReportById = function(orderId) {
  let item = (window._currentImagingResults || []).find(it => String(it.id) === String(orderId));
  if(!item && window._currentImagingItems) {
    item = window._currentImagingItems.find(it => String(it.order_id) === String(orderId) || String(it.invoice_item_id) === String(orderId));
  }
  if(item) {
    cPrintImagingReportItem(item);
  } else {
    alert('Imaging record not found.');
  }
};

function cPrintImagingReportItem(item) {
  let reportData = null;
  if(item.result) {
    try {
      reportData = typeof item.result === 'string' ? JSON.parse(item.result) : item.result;
    } catch(e) {
      reportData = { form_type: 'general', findings: String(item.result), impression: '' };
    }
  } else {
    // If not saved yet, infer form type from item description
    const ln = (item.imaging_name || item.description || '').toLowerCase();
    let fType = 'us_breast';
    if(ln.includes('breast') || ln.includes('mammo') || ln.includes('sein')) fType = 'us_breast';
    else if(ln.includes('obstet') || ln.includes('pregnan') || ln.includes('grossesse')) fType = 'us_obstetric';
    else if(ln.includes('pelvi') || ln.includes('gyn')) fType = 'us_pelvic';
    else if(ln.includes('abdo') || ln.includes('hepato')) fType = 'us_abdomen';
    else if(ln.includes('prostat')) fType = 'us_prostate';
    else if(ln.includes('renal') || ln.includes('kidney')) fType = 'us_renal';
    else if(ln.includes('thyroid')) fType = 'us_thyroid';
    else if(ln.includes('scrot') || ln.includes('testi')) fType = 'us_scrotal';
    else if(ln.includes('x-ray') || ln.includes('ct scan') || ln.includes('mri')) fType = 'general';
    reportData = { form_type: fType };
  }
  cExecutePrintReport(reportData, item);
}

function cExecutePrintReport(data, orderInfo) {
  const fType = data.form_type || 'us_breast';
  let printHtml = '';
  let docTitle = _cHosp.name + ' - MEDICAL IMAGING REPORT';

  if(fType === 'us_breast') {
    docTitle = _cHosp.name + ' - BREAST ULTRASOUND EXAMINATION & REPORT';
    printHtml = cBuildBreastPrintA4(data, orderInfo);
  } else if(fType === 'general') {
    docTitle = _cHosp.name + ' - RADIOLOGY & IMAGING REPORT';
    printHtml = cBuildGeneralPrintA4(data, orderInfo);
  } else {
    // Other ultrasound examinations
    const customTitle = _cFormTitles[fType] || 'ULTRASOUND EXAMINATION & REPORT';
    docTitle = _cHosp.name + ' - ' + customTitle.toUpperCase();
    printHtml = cBuildUltrasoundPrintA4(data, orderInfo, customTitle);
  }
  printHtml += cBuildAttachmentPrintHtml(data.attachments || []);

  const win = window.open('', '_blank', 'width=900,height=800');
  if(!win) {
    alert('Please allow pop-ups to print the report.');
    return;
  }
  win.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${docTitle}</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 12mm 14mm 12mm 14mm;
    }
    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    body {
      font-family: 'Times New Roman', serif;
      color: #000000;
      background: #ffffff;
      margin: 0;
      padding: 16px;
      font-size: 13px;
      line-height: 1.4;
    }
    .a4-container {
      width: 100%;
      max-width: 820px;
      margin: 0 auto;
      background: #ffffff;
    }
    .header-table {
      width: 100%;
      border-bottom: 2px solid #000000;
      padding-bottom: 8px;
      margin-bottom: 12px;
    }
    .clinic-name {
      font-size: 19px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #000000;
    }
    .form-title {
      font-size: 15px;
      font-weight: 800;
      text-transform: uppercase;
      margin-top: 4px;
      letter-spacing: 0.5px;
      text-decoration: underline;
    }
    .clinic-meta {
      font-size: 11px;
      margin-top: 3px;
      color: #333333;
    }
    .section-header {
      font-size: 13px;
      font-weight: 800;
      text-transform: uppercase;
      background: #f0f0f0;
      border: 1px solid #000000;
      padding: 4px 8px;
      margin-top: 10px;
      margin-bottom: 4px;
      letter-spacing: 0.3px;
    }
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 12px;
      margin-bottom: 6px;
    }
    .data-table td, .data-table th {
      border: 1px solid #777777;
      padding: 4px 6px;
      vertical-align: top;
    }
    .lbl {
      font-weight: 700;
      background: #fafafa;
      width: 22%;
    }
    .val {
      width: 28%;
    }
    .check-grid {
      display: table;
      width: 100%;
      margin: 4px 0 8px 0;
      font-size: 12px;
    }
    .check-row {
      display: table-row;
    }
    .check-cell {
      display: table-cell;
      padding: 3px 6px;
      width: 33.3%;
    }
    .findings-organ {
      margin-top: 6px;
      margin-bottom: 4px;
      font-size: 12px;
      page-break-inside: avoid;
    }
    .organ-title {
      font-weight: 800;
      text-decoration: underline;
      margin-bottom: 2px;
      font-size: 12.5px;
    }
    .impression-box {
      border: 2px solid #000000;
      padding: 8px 10px;
      margin-top: 8px;
      background: #fafafa;
      page-break-inside: avoid;
    }
    .sign-table {
      width: 100%;
      margin-top: 16px;
      border-collapse: collapse;
      page-break-inside: avoid;
    }
    .sign-table td {
      vertical-align: top;
      padding: 6px 10px;
    }
    .stamp-box {
      border: 2px dashed #444444;
      height: 90px;
      width: 180px;
      text-align: center;
      padding-top: 30px;
      font-size: 11px;
      font-weight: 700;
      color: #666666;
      box-sizing: border-box;
      margin: 4px auto 0 auto;
    }
    .notice-box {
      font-size: 10.5px;
      font-style: italic;
      border-top: 1px solid #777777;
      padding-top: 6px;
      margin-top: 14px;
      text-align: justify;
    }
    .footer-note {
      font-size: 10px;
      text-align: center;
      margin-top: 6px;
      color: #555555;
    }
    @media print {
      body { padding: 0; }
      .a4-container { max-width: 100%; }
    }
  </style>
</head>
<body>
  <div class="a4-container">
    ${printHtml}
  </div>
</body>
</html>`);
  win.document.close();
  win.focus();
  setTimeout(() => {
    win.print();
  }, 400);
}

function cBuildAttachmentPrintHtml(attachments) {
  if (!Array.isArray(attachments) || !attachments.length) return '';
  const base = window.location.origin + (window.appBasePath || window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')));
  const escAttr = value => cEsc(String(value || '')).replace(/"/g, '&quot;');
  const cards = attachments.map(att => {
    const rawPath = String(att.path || '');
    const path = rawPath.indexOf('http') === 0 ? rawPath : base.replace(/\/$/, '') + '/' + rawPath.replace(/^\//, '');
    const name = cEsc(att.name || 'Attachment');
    const type = String(att.type || '').toLowerCase();
    const isImage = type.indexOf('image/') === 0 || /\.(jpg|jpeg|png|webp|gif|bmp)$/i.test(att.name || '');
    return isImage
      ? `<div style="display:inline-block;width:31%;margin:0 1% 10px 0;vertical-align:top;text-align:center"><img src="${escAttr(path)}" alt="${name}" style="max-width:100%;max-height:150px;border:1px solid #777"><div style="font-size:10px;margin-top:3px">${name}</div></div>`
      : `<div style="padding:6px 8px;border:1px solid #999;margin-bottom:4px;font-size:11px"><a href="${escAttr(path)}">${name}</a></div>`;
  }).join('');
  return `<div class="section-header">ATTACHED IMAGING SCANS / DIAGNOSTIC DOCUMENTS (${attachments.length})</div><div style="margin-top:6px">${cards}</div>`;
}

// ============ 1. DEDICATED BREAST ULTRASOUND A4 PRINT ============
function cBuildBreastPrintA4(data, orderInfo) {
  const p = data.patient || window._currentPatient || {};
  const c = data.clinical || {};
  const br = (data.findings && data.findings.breast) ? data.findings.breast : {};
  const conc = data.conclusion || {};
  const rep = data.reporting || {};

  const pName = ((p.first_name||'') + ' ' + (p.last_name||'')).trim() || p.name || '—';
  const pNo = p.patient_no || p.id || '—';
  const pAge = p.age || (p.date_of_birth ? Math.floor((Date.now() - new Date(p.date_of_birth).getTime()) / (365.25 * 24 * 3600 * 1000)) : '—');
  const pSex = p.sex || p.gender || 'Female';

  const impItems = [conc.imp_1, conc.imp_2, conc.imp_3].filter(it => it && it.trim() !== '');
  let impListHtml = '';
  if(impItems.length) {
    impListHtml = '<ol style="margin:4px 0 6px 20px;padding:0;font-weight:700;">' + impItems.map(it => `<li>${cEsc(it)}</li>`).join('') + '</ol>';
  }

  return `
    <!-- CLINIC HEADER -->
    <table class="header-table">
      <tr>
        <td style="width:15%;vertical-align:middle;text-align:center;">
          <div style="width:70px;height:70px;border-radius:50%;background:#0d9488;color:white;display:inline-flex;align-items:center;justify-content:center;font-size:28px;font-weight:bold;font-family:sans-serif;">
            +
          </div>
        </td>
        <td style="width:85%;text-align:center;vertical-align:middle;">
          <div class="clinic-name">${cEsc(_cHosp.name)}</div>
          <div class="form-title">BREAST ULTRASOUND EXAMINATION & REPORT FORM</div>
          <div class="clinic-meta">${cEsc(_cHosp.address)}</div>
          <div class="clinic-meta">Tel: ${cEsc(_cHosp.phone)} | Email: ${cEsc(_cHosp.email)}</div>
        </td>
      </tr>
    </table>

    <!-- 1. PATIENT INFORMATION -->
    <div class="section-header">1. PATIENT INFORMATION</div>
    <table class="data-table">
      <tr>
        <td class="lbl">Patient Name</td>
        <td class="val"><strong>${cEsc(pName)}</strong></td>
        <td class="lbl">Patient ID / File No.</td>
        <td class="val"><strong>${cEsc(pNo)}</strong></td>
      </tr>
      <tr>
        <td class="lbl">Date of Birth</td>
        <td class="val">${cEsc(p.dob || p.date_of_birth || '—')}</td>
        <td class="lbl">Age / Sex</td>
        <td class="val">${cEsc(pAge ? pAge + ' years' : '—')} &nbsp;|&nbsp; ${pSex === 'Male' ? '☑ Male ☐ Female' : (pSex === 'Female' ? '☐ Male ☑ Female' : cEsc(pSex))}</td>
      </tr>
      <tr>
        <td class="lbl">Telephone</td>
        <td class="val">${cEsc(p.phone || '—')}</td>
        <td class="lbl">Address</td>
        <td class="val">${cEsc(p.address || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Date of Examination</td>
        <td class="val">${cEsc(p.exam_date || new Date().toISOString().slice(0, 10))} &nbsp; ${cEsc(p.exam_time || '')}</td>
        <td class="lbl">Referring Clinician</td>
        <td class="val">${cEsc(p.referring_clinician || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Department / Service</td>
        <td colspan="3">${cEsc(p.department || 'Outpatient Department (OPD)')}</td>
      </tr>
    </table>

    <!-- 2. CLINICAL INFORMATION -->
    <div class="section-header">2. CLINICAL INFORMATION</div>
    <table class="data-table">
      <tr>
        <td class="lbl">Clinical Indication</td>
        <td colspan="3">${cEsc(c.indication || 'Breast Ultrasound Evaluation')}</td>
      </tr>
      <tr>
        <td class="lbl">Medical/Surgical History</td>
        <td colspan="3">${cEsc(c.medical_history || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Previous Mammography / Lab</td>
        <td colspan="3">${cEsc(c.lab_results || '—')}</td>
      </tr>
    </table>

    <!-- 3. BREAST ULTRASOUND FINDINGS -->
    <div class="section-header">3. BREAST ULTRASOUND FINDINGS</div>
    <table class="data-table">
      <thead>
        <tr style="background:#f4f4f5;font-weight:700;">
          <th style="width:50%;text-align:left;padding:6px;">RIGHT BREAST</th>
          <th style="width:50%;text-align:left;padding:6px;">LEFT BREAST</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="min-height:90px;line-height:1.5;">${cEsc(br.right || '—').replace(/\n/g, '<br>')}</td>
          <td style="min-height:90px;line-height:1.5;">${cEsc(br.left || '—').replace(/\n/g, '<br>')}</td>
        </tr>
        <tr>
          <td class="lbl">Axillary Lymph Nodes</td>
          <td colspan="3">${cEsc(br.axilla || '—')}</td>
        </tr>
        <tr>
          <td class="lbl">Overall BI-RADS Assessment</td>
          <td colspan="3"><strong>${cEsc(br.birads || '—')}</strong></td>
        </tr>
      </tbody>
    </table>

    <!-- COMMENTS (If any) -->
    ${data.general_comments && data.general_comments.trim() ? `
    <div class="section-header">ADDITIONAL OBSERVATIONS / COMMENTS</div>
    <div style="padding:6px 8px;font-size:12px;background:#fafafa;border:1px solid #999;margin-bottom:6px;">
      ${cEsc(data.general_comments).replace(/\n/g, '<br>')}
    </div>` : ''}

    <!-- 4. IMPRESSION & CONCLUSION -->
    <div class="section-header">4. IMPRESSION / CONCLUSION</div>
    <div class="impression-box">
      ${impListHtml}
      <div style="margin-top:6px;font-size:12.5px;">
        <strong>FINAL CONCLUSION:</strong> ${cEsc(conc.final || conc.imp_1 || '—')}
      </div>
      ${conc.recommendations ? `
      <div style="margin-top:4px;font-size:12px;color:#333333;">
        <strong>RECOMMENDATIONS:</strong> ${cEsc(conc.recommendations)}
      </div>` : ''}
    </div>

    <!-- 5. REPORTING INFORMATION & SIGNATURES -->
    <div class="section-header">5. REPORTING INFORMATION</div>
    <table class="sign-table">
      <tr>
        <td style="width:38%;">
          <div style="font-weight:700;font-size:12px;border-bottom:1px solid #000;padding-bottom:2px;margin-bottom:6px;">PERFORMED BY:</div>
          <div>Name: <strong>${cEsc(rep.performed_by || '—')}</strong></div>
          <div style="font-size:11px;">Qualification: ${cEsc(rep.performed_qual || 'Sonographer / Medical Officer')}</div>
          <div style="font-size:11px;">Reg. No: ${cEsc(rep.performed_reg || '—')}</div>
           <div style="margin-top:22px;border-top:1px dotted #333;width:80%;font-size:10px;text-align:center;">${cSignature()}</div>
        </td>
        <td style="width:38%;">
          <div style="font-weight:700;font-size:12px;border-bottom:1px solid #000;padding-bottom:2px;margin-bottom:6px;">REPORTED / VERIFIED BY:</div>
          <div>Name: <strong>${cEsc(rep.verified_by || rep.performed_by || '—')}</strong></div>
          <div style="font-size:11px;">Qualification: ${cEsc(rep.verified_qual || 'Consultant Radiologist / Medical Officer')}</div>
          <div style="font-size:11px;">Date & Time: ${cEsc(rep.datetime || '—')}</div>
           <div style="margin-top:22px;border-top:1px dotted #333;width:80%;font-size:10px;text-align:center;">${cSignature()}</div>
        </td>
        <td style="width:24%;text-align:center;">
          <div class="stamp-box">
             ${cStamp()}
          </div>
        </td>
      </tr>
    </table>

    <!-- NOTICE -->
    <div class="notice-box">
      <strong>IMPORTANT NOTICE:</strong> This report should be interpreted together with the patient's clinical history, physical examination, and relevant laboratory investigations. Ultrasound findings should be correlated clinically where appropriate.
    </div>
    <div class="footer-note">
      ${cEsc(_cHosp.name)} Breast Ultrasound Examination & Diagnostic Report${_cHosp.address ? ' — ' + cEsc(_cHosp.address) : ''}
    </div>
  `;
}

// ============ 2. GENERAL / OTHER ULTRASOUND A4 PRINT ============
function cBuildUltrasoundPrintA4(data, orderInfo, formCustomTitle) {
  const p = data.patient || window._currentPatient || {};
  const c = data.clinical || {};
  const f = data.findings || {};
  const conc = data.conclusion || {};
  const rep = data.reporting || {};

  const pName = ((p.first_name||'') + ' ' + (p.last_name||'')).trim() || p.name || '—';
  const pNo = p.patient_no || p.id || '—';
  const pAge = p.age || (p.date_of_birth ? Math.floor((Date.now() - new Date(p.date_of_birth).getTime()) / (365.25 * 24 * 3600 * 1000)) : '—');
  const pSex = p.sex || p.gender || '—';

  const req = data.requested_exams || [];
  const hasReq = (name) => req.some(r => r.toLowerCase().includes(name.toLowerCase()));

  const examsList = [
    'Abdominal Ultrasound', 'Pelvic Ultrasound', 'Obstetric Ultrasound',
    'Renal / Urinary Tract Ultrasound', 'Prostate Ultrasound', 'Breast Ultrasound',
    'Thyroid Ultrasound', 'Scrotal Ultrasound', 'Soft Tissue Ultrasound',
    'Hepatobiliary Ultrasound'
  ];

  let examsGridHtml = '<div class="check-grid">';
  for(let i = 0; i < examsList.length; i += 3) {
    examsGridHtml += '<div class="check-row">';
    for(let j = 0; j < 3; j++) {
      const ex = examsList[i + j];
      if(ex) {
        const isChecked = hasReq(ex);
        examsGridHtml += `<div class="check-cell">${isChecked ? '☑' : '☐'} ${ex}</div>`;
      } else {
        examsGridHtml += '<div class="check-cell"></div>';
      }
    }
    examsGridHtml += '</div>';
  }
  const otherItem = req.find(r => r.startsWith('Other:'));
  examsGridHtml += `<div class="check-row"><div class="check-cell" style="display:table-cell;" colspan="3">${otherItem ? '☑ ' + cEsc(otherItem) : '☐ Other: ___________________________'}</div></div>`;
  examsGridHtml += '</div>';

  let findingsHtml = '';

  // 1. Abdomen check
  const a = f.abdomen || {};
  const hasAbdData = hasReq('Abdominal') || hasReq('Hepatobiliary') || Object.values(a).some(v => v && v.trim() !== '');
  if(hasAbdData) {
    let liverDesc = [
      a.liver_size ? `Size: <strong>${cEsc(a.liver_size)}</strong>` : '',
      a.liver_echo ? `Echogenicity: <strong>${cEsc(a.liver_echo)}</strong>` : '',
      a.liver_surface ? `Surface: ${cEsc(a.liver_surface)}` : '',
      a.liver_lesions ? `Focal lesions: ${cEsc(a.liver_lesions)}` : '',
      a.liver_ihbd ? `IHBD: ${cEsc(a.liver_ihbd)}` : '',
      a.liver_pv ? `Portal vein: ${cEsc(a.liver_pv)}` : ''
    ].filter(Boolean).join('. ');

    let gbDesc = [
      a.gb_wall ? `GB Wall: <strong>${cEsc(a.gb_wall)}</strong>` : '',
      a.gb_lumen ? `Lumen: <strong>${cEsc(a.gb_lumen)}</strong>` : '',
      a.gb_fluid ? `Fluid: ${cEsc(a.gb_fluid)}` : '',
      a.gb_murphy ? `Murphy's sign: ${cEsc(a.gb_murphy)}` : '',
      a.cbd_diam ? `CBD: ${cEsc(a.cbd_diam)}` : '',
      a.cbd_lumen ? `CBD Lumen: ${cEsc(a.cbd_lumen)}` : ''
    ].filter(Boolean).join('. ');

    let pancDesc = [
      a.panc_vis ? `Pancreas: ${cEsc(a.panc_vis)}` : '',
      a.panc_echo ? cEsc(a.panc_echo) : '',
      a.panc_duct ? `Duct: ${cEsc(a.panc_duct)}` : '',
      a.spleen_size ? `Spleen Size: ${cEsc(a.spleen_size)}` : '',
      a.spleen_echo ? `Spleen Echotexture: ${cEsc(a.spleen_echo)}` : ''
    ].filter(Boolean).join('. ');

    let kidDesc = [
      a.rk_desc ? `Right Kidney: ${cEsc(a.rk_desc)}` : '',
      a.lk_desc ? `Left Kidney: ${cEsc(a.lk_desc)}` : ''
    ].filter(Boolean).join('. ');

    let cavDesc = [
      a.bladder ? `Bladder: ${cEsc(a.bladder)}` : '',
      a.ascites ? `Ascites / Free fluid: <strong>${cEsc(a.ascites)}</strong>` : '',
      a.aorta ? `Aorta: ${cEsc(a.aorta)}` : '',
      a.bowel ? `Bowel: ${cEsc(a.bowel)}` : ''
    ].filter(Boolean).join('. ');

    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">ABDOMINAL ULTRASOUND FINDINGS:</div>
        <table class="data-table">
          ${liverDesc ? `<tr><td class="lbl">Liver</td><td colspan="3">${liverDesc}</td></tr>` : ''}
          ${gbDesc ? `<tr><td class="lbl">Gallbladder & CBD</td><td colspan="3">${gbDesc}</td></tr>` : ''}
          ${pancDesc ? `<tr><td class="lbl">Pancreas & Spleen</td><td colspan="3">${pancDesc}</td></tr>` : ''}
          ${kidDesc ? `<tr><td class="lbl">Kidneys</td><td colspan="3">${kidDesc}</td></tr>` : ''}
          ${cavDesc ? `<tr><td class="lbl">Bladder & Cavity</td><td colspan="3">${cavDesc}</td></tr>` : ''}
        </table>
      </div>
    `;
  }

  // 2. Pelvis check
  const pel = f.pelvis || {};
  const hasPelvisData = hasReq('Pelvic') || Object.values(pel).some(v => v && v.trim() !== '');
  if(hasPelvisData) {
    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">PELVIC ULTRASOUND FINDINGS (GYNAECOLOGICAL):</div>
        <table class="data-table">
          <tr>
            <td class="lbl">Uterus</td>
            <td colspan="3">
              ${pel.ut_pos ? `Position: <strong>${cEsc(pel.ut_pos)}</strong>. ` : ''}
              ${pel.ut_contour ? `Contour: ${cEsc(pel.ut_contour)}. ` : ''}
              ${pel.ut_dims ? `Dimensions: <strong>${cEsc(pel.ut_dims)}</strong>. ` : ''}
              ${pel.ut_myo ? `Myometrium: ${cEsc(pel.ut_myo)}.` : ''}
            </td>
          </tr>
          ${pel.endo_thick || pel.endo_desc ? `
          <tr>
            <td class="lbl">Endometrium</td>
            <td colspan="3">
              ${pel.endo_thick ? `Thickness: <strong>${cEsc(pel.endo_thick)}</strong>. ` : ''}
              ${pel.endo_desc ? `Appearance: ${cEsc(pel.endo_desc)}.` : ''}
            </td>
          </tr>` : ''}
          <tr>
            <td class="lbl">Ovaries & Adnexa</td>
            <td colspan="3">
              ${pel.ro_dims ? `Right Ovary: ${cEsc(pel.ro_dims)}.<br>` : ''}
              ${pel.lo_dims ? `Left Ovary: ${cEsc(pel.lo_dims)}.<br>` : ''}
              ${pel.adnexa ? `Adnexa: ${cEsc(pel.adnexa)}. ` : ''}
              ${pel.pouch ? `Cul-de-sac: <strong>${cEsc(pel.pouch)}</strong>.` : ''}
            </td>
          </tr>
        </table>
      </div>
    `;
  }

  // 3. Obstetric check
  const ob = f.obstetric || {};
  const hasObData = hasReq('Obstetric') || Object.values(ob).some(v => v && v.trim() !== '');
  if(hasObData) {
    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">OBSTETRIC ULTRASOUND FINDINGS:</div>
        <table class="data-table">
          <tr>
            <td class="lbl">Pregnancy Baseline</td>
            <td colspan="3">
              ${ob.lmp ? `LMP: <strong>${cEsc(ob.lmp)}</strong> | ` : ''}
              ${ob.clin_ga ? `Clinical GA: <strong>${cEsc(ob.clin_ga)}</strong> | ` : ''}
              ${ob.fetuses ? `Fetuses: <strong>${cEsc(ob.fetuses)}</strong><br>` : ''}
              ${ob.presentation ? `Presentation: <strong>${cEsc(ob.presentation)}</strong> | ` : ''}
              ${ob.fhr ? `FHR: <strong>${cEsc(ob.fhr)}</strong> | ` : ''}
              ${ob.movements ? `Movements: ${cEsc(ob.movements)}` : ''}
            </td>
          </tr>
          ${ob.plac_loc || ob.amniotic || ob.cervix ? `
          <tr>
            <td class="lbl">Placenta & Liquor</td>
            <td colspan="3">
              ${ob.plac_loc ? `Placenta: <strong>${cEsc(ob.plac_loc)}</strong> (${cEsc(ob.plac_grade || 'Grade I')}) | ` : ''}
              ${ob.amniotic ? `Liquor: <strong>${cEsc(ob.amniotic)}</strong> | ` : ''}
              ${ob.cervix ? `Cervix: ${cEsc(ob.cervix)}` : ''}
            </td>
          </tr>` : ''}
          ${ob.bpd || ob.hc || ob.ac || ob.fl || ob.efw || ob.us_ga ? `
          <tr>
            <td class="lbl">Fetal Biometry</td>
            <td colspan="3">
              ${ob.bpd ? `BPD: <strong>${cEsc(ob.bpd)} mm</strong> | ` : ''}
              ${ob.hc ? `HC: <strong>${cEsc(ob.hc)} mm</strong> | ` : ''}
              ${ob.ac ? `AC: <strong>${cEsc(ob.ac)} mm</strong> | ` : ''}
              ${ob.fl ? `FL: <strong>${cEsc(ob.fl)} mm</strong><br>` : ''}
              ${ob.efw ? `EFW: <strong>${cEsc(ob.efw)}</strong> | ` : ''}
              ${ob.us_ga ? `Ultrasound GA: <strong>${cEsc(ob.us_ga)}</strong> | ` : ''}
              ${ob.edd_us ? `EDD (US): <strong>${cEsc(ob.edd_us)}</strong>` : ''}
            </td>
          </tr>` : ''}
          ${ob.head || ob.spine || ob.heart || ob.abdo || ob.urinary || ob.limbs ? `
          <tr>
            <td class="lbl">Anatomy Survey</td>
            <td colspan="3">
              ${ob.head ? `Head: ${cEsc(ob.head)} | ` : ''}
              ${ob.spine ? `Spine: ${cEsc(ob.spine)} | ` : ''}
              ${ob.heart ? `Heart: ${cEsc(ob.heart)}<br>` : ''}
              ${ob.abdo ? `Abdomen: ${cEsc(ob.abdo)} | ` : ''}
              ${ob.urinary ? `Urinary: ${cEsc(ob.urinary)} | ` : ''}
              ${ob.limbs ? `Limbs/Cord: ${cEsc(ob.limbs)}` : ''}
            </td>
          </tr>` : ''}
        </table>
      </div>
    `;
  }

  // 4. Renal & Prostate check
  const ren = f.renal || {};
  const pr = f.prostate || {};
  const hasRenalData = hasReq('Renal') || Object.values(ren).some(v => v && v.trim() !== '');
  const hasProstateData = hasReq('Prostate') || Object.values(pr).some(v => v && v.trim() !== '');

  if(hasRenalData) {
    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">RENAL & URINARY TRACT FINDINGS:</div>
        <table class="data-table">
          ${ren.rk_dims || ren.rk_desc ? `<tr><td class="lbl">Right Kidney</td><td colspan="3">${cEsc(ren.rk_dims || '')} ${cEsc(ren.rk_desc || '')}</td></tr>` : ''}
          ${ren.lk_dims || ren.lk_desc ? `<tr><td class="lbl">Left Kidney</td><td colspan="3">${cEsc(ren.lk_dims || '')} ${cEsc(ren.lk_desc || '')}</td></tr>` : ''}
          ${ren.bladder ? `<tr><td class="lbl">Bladder</td><td colspan="3">${cEsc(ren.bladder)}</td></tr>` : ''}
        </table>
      </div>
    `;
  }

  if(hasProstateData) {
    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">PROSTATE ULTRASOUND FINDINGS:</div>
        <table class="data-table">
          <tr>
            <td class="lbl">Dimensions & Volume</td>
            <td colspan="3">
              ${pr.tx ? `Transverse: ${cEsc(pr.tx)} mm x AP: ${cEsc(pr.ap || '—')} mm x CC: ${cEsc(pr.cc || '—')} mm.<br>` : ''}
              ${pr.vol ? `Estimated Prostate Volume: <strong>${cEsc(pr.vol)}</strong>.` : ''}
            </td>
          </tr>
          ${pr.echo || pr.ipp || pr.capsule || pr.pvr ? `
          <tr>
            <td class="lbl">Parenchyma & PVR</td>
            <td colspan="3">
              ${pr.echo ? `Parenchyma: ${cEsc(pr.echo)}. ` : ''}
              ${pr.ipp ? `Median lobe: ${cEsc(pr.ipp)}. ` : ''}
              ${pr.capsule ? `Capsule: ${cEsc(pr.capsule)}.<br>` : ''}
              ${pr.pvr ? `PVR: <strong>${cEsc(pr.pvr)}</strong>.` : ''}
            </td>
          </tr>` : ''}
        </table>
      </div>
    `;
  }

  // 5. Breast check (if present in comprehensive view)
  const br = f.breast || {};
  const hasBreastData = hasReq('Breast') || Object.values(br).some(v => v && v.trim() !== '');
  if(hasBreastData) {
    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">BREAST ULTRASOUND FINDINGS:</div>
        <table class="data-table">
          <tr>
            <td class="lbl">Right Breast</td>
            <td>${cEsc(br.right || '—')}</td>
            <td class="lbl">Left Breast</td>
            <td>${cEsc(br.left || '—')}</td>
          </tr>
          <tr>
            <td class="lbl">Axillary Nodes</td>
            <td>${cEsc(br.axilla || '—')}</td>
            <td class="lbl">BI-RADS Category</td>
            <td><strong>${cEsc(br.birads || '—')}</strong></td>
          </tr>
        </table>
      </div>
    `;
  }

  // 6. Thyroid check
  const th = f.thyroid || {};
  const hasThyroidData = hasReq('Thyroid') || Object.values(th).some(v => v && v.trim() !== '');
  if(hasThyroidData) {
    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">THYROID ULTRASOUND FINDINGS:</div>
        <table class="data-table">
          <tr>
            <td class="lbl">Right Lobe</td>
            <td>Dims: ${cEsc(th.rl_dims || '—')} | Vol: ${cEsc(th.rl_vol || '—')}</td>
            <td class="lbl">Left Lobe</td>
            <td>Dims: ${cEsc(th.ll_dims || '—')} | Vol: ${cEsc(th.ll_vol || '—')}</td>
          </tr>
          ${th.isthmus || th.echo || th.nodules || th.nodes ? `
          <tr>
            <td class="lbl">Isthmus & Echotexture</td>
            <td colspan="3">
              ${th.isthmus ? `Isthmus: ${cEsc(th.isthmus)}. ` : ''}
              ${th.echo ? `Echogenicity: ${cEsc(th.echo)}. ` : ''}
              ${th.nodules ? `Nodules: ${cEsc(th.nodules)}.<br>` : ''}
              ${th.nodes ? `Nodes: ${cEsc(th.nodes)}.` : ''}
            </td>
          </tr>` : ''}
        </table>
      </div>
    `;
  }

  // 7. Scrotal check
  const sc = f.scrotal || {};
  const hasScrotalData = hasReq('Scrotal') || Object.values(sc).some(v => v && v.trim() !== '');
  if(hasScrotalData) {
    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">SCROTAL & TESTICULAR FINDINGS:</div>
        <table class="data-table">
          <tr>
            <td class="lbl">Right Testis</td>
            <td>${cEsc(sc.rt || '—')}</td>
            <td class="lbl">Left Testis</td>
            <td>${cEsc(sc.lt || '—')}</td>
          </tr>
          ${sc.epi || sc.hydro || sc.varico ? `
          <tr>
            <td class="lbl">Epididymis & Fluid</td>
            <td colspan="3">
              ${sc.epi ? `Epididymides: ${cEsc(sc.epi)}. ` : ''}
              ${sc.hydro ? `Hydrocele: ${cEsc(sc.hydro)}. ` : ''}
              ${sc.varico ? `Varicocele: ${cEsc(sc.varico)}.` : ''}
            </td>
          </tr>` : ''}
        </table>
      </div>
    `;
  }

  // 8. Soft Tissue check
  const st = f.soft_tissue || {};
  const hasSoftData = hasReq('Soft Tissue') || Object.values(st).some(v => v && v.trim() !== '');
  if(hasSoftData) {
    findingsHtml += `
      <div class="findings-organ">
        <div class="organ-title">SOFT TISSUE ULTRASOUND FINDINGS:</div>
        <table class="data-table">
          <tr>
            <td class="lbl">Site</td>
            <td>${cEsc(st.site || '—')}</td>
            <td class="lbl">Dimensions</td>
            <td>${cEsc(st.dims || '—')}</td>
          </tr>
          ${st.desc ? `<tr><td class="lbl">Characteristics</td><td colspan="3">${cEsc(st.desc)}</td></tr>` : ''}
        </table>
      </div>
    `;
  }

  let commentsHtml = '';
  if(data.general_comments && data.general_comments.trim()) {
    commentsHtml = `
      <div class="section-header">ADDITIONAL FINDINGS / COMMENTS</div>
      <div style="padding:6px 8px;font-size:12px;background:#fafafa;border:1px solid #999;margin-bottom:6px;">
        ${cEsc(data.general_comments).replace(/\n/g, '<br>')}
      </div>
    `;
  }

  const impItems = [conc.imp_1, conc.imp_2, conc.imp_3].filter(it => it && it.trim() !== '');
  let impListHtml = '';
  if(impItems.length) {
    impListHtml = '<ol style="margin:4px 0 6px 20px;padding:0;font-weight:700;">' + impItems.map(it => `<li>${cEsc(it)}</li>`).join('') + '</ol>';
  }

  const headerTitle = (formCustomTitle || 'ULTRASOUND EXAMINATION & REPORT FORM').toUpperCase();

  return `
    <table class="header-table">
      <tr>
        <td style="width:15%;vertical-align:middle;text-align:center;">
          <div style="width:70px;height:70px;border-radius:50%;background:#0d9488;color:white;display:inline-flex;align-items:center;justify-content:center;font-size:28px;font-weight:bold;font-family:sans-serif;">
            +
          </div>
        </td>
        <td style="width:85%;text-align:center;vertical-align:middle;">
          <div class="clinic-name">${cEsc(_cHosp.name)}</div>
          <div class="form-title">${cEsc(headerTitle)}</div>
          <div class="clinic-meta">${cEsc(_cHosp.address)}</div>
          <div class="clinic-meta">Tel: ${cEsc(_cHosp.phone)} | Email: ${cEsc(_cHosp.email)}</div>
        </td>
      </tr>
    </table>

    <div class="section-header">1. PATIENT INFORMATION</div>
    <table class="data-table">
      <tr>
        <td class="lbl">Patient Name</td>
        <td class="val"><strong>${cEsc(pName)}</strong></td>
        <td class="lbl">Patient ID / File No.</td>
        <td class="val"><strong>${cEsc(pNo)}</strong></td>
      </tr>
      <tr>
        <td class="lbl">Date of Birth</td>
        <td class="val">${cEsc(p.dob || p.date_of_birth || '—')}</td>
        <td class="lbl">Age / Sex</td>
        <td class="val">${cEsc(pAge ? pAge + ' years' : '—')} &nbsp;|&nbsp; ${pSex === 'Male' ? '☑ Male ☐ Female' : (pSex === 'Female' ? '☐ Male ☑ Female' : cEsc(pSex))}</td>
      </tr>
      <tr>
        <td class="lbl">Telephone</td>
        <td class="val">${cEsc(p.phone || '—')}</td>
        <td class="lbl">Address</td>
        <td class="val">${cEsc(p.address || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Date of Examination</td>
        <td class="val">${cEsc(p.exam_date || new Date().toISOString().slice(0, 10))} &nbsp; ${cEsc(p.exam_time || '')}</td>
        <td class="lbl">Referring Clinician</td>
        <td class="val">${cEsc(p.referring_clinician || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Department / Service</td>
        <td colspan="3">${cEsc(p.department || 'Outpatient')}</td>
      </tr>
    </table>

    <div class="section-header">2. CLINICAL INFORMATION</div>
    <table class="data-table">
      <tr>
        <td class="lbl">Clinical Indication</td>
        <td colspan="3">${cEsc(c.indication || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Medical/Surgical History</td>
        <td colspan="3">${cEsc(c.medical_history || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Laboratory Results</td>
        <td colspan="3">${cEsc(c.lab_results || '—')}</td>
      </tr>
    </table>

    ${data.form_type === 'ultrasound' ? `
    <div class="section-header">3. ULTRASOUND EXAMINATION REQUESTED</div>
    ${examsGridHtml}` : ''}

    <div class="section-header">ULTRASOUND FINDINGS</div>
    ${findingsHtml || '<p style="font-style:italic;padding:6px;">No discrete abnormal findings recorded.</p>'}

    ${commentsHtml}

    <div class="section-header">IMPRESSION / CONCLUSION</div>
    <div class="impression-box">
      ${impListHtml}
      <div style="margin-top:6px;font-size:12.5px;">
        <strong>FINAL CONCLUSION:</strong> ${cEsc(conc.final || conc.imp_1 || '—')}
      </div>
      ${conc.recommendations ? `
      <div style="margin-top:4px;font-size:12px;color:#333333;">
        <strong>RECOMMENDATIONS:</strong> ${cEsc(conc.recommendations)}
      </div>` : ''}
    </div>

    <div class="section-header">REPORTING INFORMATION</div>
    <table class="sign-table">
      <tr>
        <td style="width:38%;">
          <div style="font-weight:700;font-size:12px;border-bottom:1px solid #000;padding-bottom:2px;margin-bottom:6px;">PERFORMED BY:</div>
          <div>Name: <strong>${cEsc(rep.performed_by || '—')}</strong></div>
          <div style="font-size:11px;">Qualification: ${cEsc(rep.performed_qual || 'Medical Officer / Sonographer')}</div>
          <div style="font-size:11px;">Reg. No: ${cEsc(rep.performed_reg || '—')}</div>
           <div style="margin-top:22px;border-top:1px dotted #333;width:80%;font-size:10px;text-align:center;">${cSignature()}</div>
        </td>
        <td style="width:38%;">
          <div style="font-weight:700;font-size:12px;border-bottom:1px solid #000;padding-bottom:2px;margin-bottom:6px;">REPORTED / VERIFIED BY:</div>
          <div>Name: <strong>${cEsc(rep.verified_by || rep.performed_by || '—')}</strong></div>
          <div style="font-size:11px;">Qualification: ${cEsc(rep.verified_qual || 'Consultant Radiologist / Medical Officer')}</div>
          <div style="font-size:11px;">Date & Time: ${cEsc(rep.datetime || '—')}</div>
           <div style="margin-top:22px;border-top:1px dotted #333;width:80%;font-size:10px;text-align:center;">${cSignature()}</div>
        </td>
        <td style="width:24%;text-align:center;">
          <div class="stamp-box">
             ${cStamp()}
          </div>
        </td>
      </tr>
    </table>

    <div class="notice-box">
      <strong>IMPORTANT NOTICE:</strong> This report should be interpreted together with the patient's clinical history, physical examination, and relevant laboratory investigations. Ultrasound findings should be correlated clinically where appropriate.
    </div>
    <div class="footer-note">
      ${cEsc(_cHosp.name)} Diagnostic Ultrasound Services${_cHosp.address ? ' — ' + cEsc(_cHosp.address) : ''}
    </div>
  `;
}

// ============ 3. BUILD GENERAL RADIOLOGY A4 HTML ============
function cBuildGeneralPrintA4(data, orderInfo) {
  const p = data.patient || window._currentPatient || {};
  const pName = ((p.first_name||'') + ' ' + (p.last_name||'')).trim() || p.name || '—';
  const pAge = p.age || (p.date_of_birth ? Math.floor((Date.now() - new Date(p.date_of_birth).getTime()) / (365.25 * 24 * 3600 * 1000)) : (p.dob ? Math.floor((Date.now() - new Date(p.dob).getTime()) / (365.25 * 24 * 3600 * 1000)) : '—'));
  const pSex = p.gender || p.sex || '—';

  return `
    <table class="header-table">
      <tr>
        <td style="width:15%;vertical-align:middle;text-align:center;">
          <div style="width:70px;height:70px;border-radius:50%;background:#0f766e;color:white;display:inline-flex;align-items:center;justify-content:center;font-size:28px;font-weight:bold;font-family:sans-serif;">
            +
          </div>
        </td>
        <td style="width:85%;text-align:center;vertical-align:middle;">
          <div class="clinic-name">${cEsc(_cHosp.name)}</div>
          <div class="form-title">RADIOLOGY & IMAGING REPORT</div>
          <div class="clinic-meta">${cEsc(_cHosp.address)}</div>
          <div class="clinic-meta">Tel: ${cEsc(_cHosp.phone)} | Email: ${cEsc(_cHosp.email)}</div>
        </td>
      </tr>
    </table>

    <div class="section-header">PATIENT & STUDY DETAILS</div>
    <table class="data-table">
      <tr>
        <td class="lbl">Patient Name</td>
        <td class="val"><strong>${cEsc(pName)}</strong></td>
        <td class="lbl">Patient ID / File No.</td>
        <td class="val"><strong>${cEsc(p.patient_no || p.id || '—')}</strong></td>
      </tr>
      <tr>
        <td class="lbl">Age / Sex</td>
        <td class="val">${cEsc(pAge ? pAge + ' years' : '—')} &nbsp;|&nbsp; ${cEsc(pSex)}</td>
        <td class="lbl">Examination Date</td>
        <td class="val">${cEsc(data.exam_date || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Modality</td>
        <td class="val"><strong>${cEsc(data.modality || '—')}</strong></td>
        <td class="lbl">Examination</td>
        <td class="val"><strong>${cEsc(data.exam_name || '—')}</strong></td>
      </tr>
      <tr>
        <td class="lbl">Clinical Indication</td>
        <td colspan="3">${cEsc(data.clinical_info || '—')}</td>
      </tr>
      <tr>
        <td class="lbl">Technique / Projections</td>
        <td colspan="3">${cEsc(data.technique || '—')}</td>
      </tr>
    </table>

    <div class="section-header">FINDINGS</div>
    <div style="padding:10px 12px;font-size:12.5px;line-height:1.5;background:#ffffff;border:1px solid #777777;min-height:140px;margin-bottom:8px;">
      ${cEsc(data.findings || 'No findings recorded.').replace(/\n/g, '<br>')}
    </div>

    <div class="section-header">IMPRESSION & RECOMMENDATIONS</div>
    <div class="impression-box">
      <div style="font-size:13px;font-weight:700;">IMPRESSION:</div>
      <div style="font-size:12.5px;margin-top:2px;">${cEsc(data.impression || '—').replace(/\n/g, '<br>')}</div>
      ${data.recommendations ? `<div style="margin-top:6px;font-size:12px;color:#333;"><strong>RECOMMENDATIONS:</strong> ${cEsc(data.recommendations)}</div>` : ''}
    </div>

    <table class="sign-table">
      <tr>
        <td style="width:60%;">
          <div style="font-weight:700;font-size:12px;border-bottom:1px solid #000;padding-bottom:2px;margin-bottom:6px;">REPORTED BY:</div>
          <div>Name: <strong>${cEsc(data.reported_by || '—')}</strong></div>
          <div style="font-size:11px;">Qualification / Role: ${cEsc(data.role || '—')}</div>
          <div style="font-size:11px;">Reported Date & Time: ${cEsc(data.datetime || '—')}</div>
           <div style="margin-top:24px;border-top:1px dotted #333;width:60%;font-size:10px;text-align:center;">${cSignature()}</div>
        </td>
        <td style="width:40%;text-align:center;">
          <div class="stamp-box">
             ${cStamp()}
          </div>
        </td>
      </tr>
    </table>

    <div class="notice-box">
      <strong>IMPORTANT NOTICE:</strong> This report should be interpreted together with the patient's clinical history, physical examination, and relevant laboratory investigations.
    </div>
    <div class="footer-note">
      ${cEsc(_cHosp.name)} Diagnostic Radiology & Imaging Services${_cHosp.address ? ' — ' + cEsc(_cHosp.address) : ''}
    </div>
  `;
}
</script>
