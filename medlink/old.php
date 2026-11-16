<?php
// department.php - Front-end page (like patients.php pattern)
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;">
    <div>
        <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">
            <i class="fas fa-building" style="color:var(--primary-color);"></i>
            <span id="dept-page-title">My Department</span>
        </h2>
        <p id="dept-page-subtitle" style="color:var(--gray-500);font-size:14px;margin-top:4px;">
            Loading...
        </p>
    </div>
</div>

<!-- QUEUE STATS -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="dq-waiting" style="font-size:28px;font-weight:700;color:var(--warning-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Waiting</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="dq-consulting" style="font-size:28px;font-weight:700;color:var(--primary-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">In Consultation</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="dq-done" style="font-size:28px;font-weight:700;color:var(--success-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Completed Today</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--primary-color);">
    <div id="dq-current" style="font-size:28px;font-weight:700;color:var(--gray-900);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Now Serving</div>
  </div>
</div>

<!-- MAIN TWO-COLUMN LAYOUT -->
<div style="display:grid;grid-template-columns:1fr;gap:24px;">

  <!-- ===== LEFT COLUMN: Queue + Actions ===== -->
  <div>
    <!-- Queue Table -->
    <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
          <i class="fas fa-list-ol" style="color:var(--primary-color);"></i> Patient Queue
        </h3>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="text" id="dq-search" placeholder="Search patient..." oninput="dqFilterQueue()" style="padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:180px;">
          <button onclick="dqCallNext()" id="dqCallNextBtn" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
            <i class="fas fa-forward"></i> Call Next
          </button>
          <button onclick="dqLoad()" style="padding:8px 14px;background:var(--gray-50);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;" title="Refresh">
            <i class="fas fa-sync-alt"></i>
          </button>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
          <thead>
            <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">#</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Patient</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Visit</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Queued</th>
              <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Action</th>
            </tr>
          </thead>
          <tbody id="dq-tbody">
            <tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Quick Actions (only when patient selected) -->
    <div id="dq-actions" style="display:none;margin-top:16px;">
      <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid var(--gray-200);">
          <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);">
            <i class="fas fa-clipboard-check" style="color:var(--primary-color);"></i> Orders & Notes
          </h3>
          <p id="dq-action-patient" style="font-size:13px;color:var(--gray-500);margin-top:2px;">No patient selected</p>
        </div>
        <div style="padding:16px 20px;">
          <div id="dq-order-list" style="font-size:13px;color:var(--gray-500);">Select a patient to see orders.</div>
        </div>
      </div>
    </div>

    <!-- Previously seen today -->
    <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);margin-top:16px;overflow:hidden;">
      <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);">
        <h4 style="font-size:14px;font-weight:600;color:var(--gray-700);">
          <i class="fas fa-history" style="color:var(--gray-400);"></i> Previously Seen Today
        </h4>
      </div>
      <div style="overflow-x:auto;max-height:200px;overflow-y:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
              <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Queue</th>
              <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Patient</th>
              <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Status</th>
              <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Action</th>
            </tr>
          </thead>
          <tbody id="dq-done-tbody">
            <tr><td colspan="4" style="padding:24px;text-align:center;color:var(--gray-400);">No patients seen yet today.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ============================================
MODALS
============================================ -->

<!-- Order Menu Modal -->
<div id="modal-orderMenu" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:400px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);"><i class="fas fa-file-medical" style="color:var(--primary-color);"></i> Electronic Orders</h3>
      <button onclick="hideModal('orderMenu')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:16px 20px;">
      <p style="font-size:12px;color:var(--gray-500);margin-bottom:12px;" id="om-patient-label">Select an order type</p>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <button onclick="hideModal('orderMenu');rxOpenModal();" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-prescription" style="font-size:18px;color:#16a34a;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#166534;">Drug Prescription</div><div style="font-size:11px;color:var(--gray-500);">Prescribe medications</div></div>
        </button>
        <button onclick="hideModal('orderMenu');showLabModal('lab');" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-flask" style="font-size:18px;color:#2563eb;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#1e40af;">Lab Test</div><div style="font-size:11px;color:var(--gray-500);">Order laboratory investigation</div></div>
        </button>
        <button onclick="hideModal('orderMenu');showLabModal('radiology');" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-x-ray" style="font-size:18px;color:#d97706;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#92400e;">Radiology / Imaging</div><div style="font-size:11px;color:var(--gray-500);">X-Ray, Ultrasound, CT, MRI</div></div>
        </button>
        <button onclick="hideModal('orderMenu');showModal('referral');" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-share" style="font-size:18px;color:#dc2626;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#991b1b;">Referral</div><div style="font-size:11px;color:var(--gray-500);">Refer to another department</div></div>
        </button>
        <button onclick="hideModal('orderMenu');document.getElementById('admit-patient-info').textContent=dqCurrentPatient?dqCurrentPatient.patientName+' ('+dqCurrentPatient.visitNo+')':'';showModal('admission');" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-procedures" style="font-size:18px;color:#7c3aed;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#5b21b6;">Admit Patient</div><div style="font-size:11px;color:var(--gray-500);">Admit to ward / inpatient</div></div>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Patient Details Modal -->
<div id="modal-patientDetails" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:700px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:white;z-index:1;">
      <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);"><i class="fas fa-user-circle" style="color:var(--primary-color);"></i> Patient Details</h3>
      <button onclick="hideModal('patientDetails')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div id="pd-content" style="padding:20px;">
      <div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></div>
    </div>
  </div>
</div>

<!-- Department Form Modal -->
<div id="modal-departmentForm" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:90vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <!-- Header with patient info -->
    <div style="padding:20px;border-bottom:1px solid var(--gray-200);background:var(--gray-50);">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
        <div>
          <h2 style="font-size:18px;font-weight:700;color:var(--gray-900);margin:0;">Department Form</h2>
          <p style="font-size:12px;color:var(--gray-500);margin:4px 0 0 0;">Fill out the department-specific form for this consultation</p>
        </div>
        <button onclick="hideFormModal()" style="border:none;background:none;font-size:28px;cursor:pointer;color:var(--gray-400);">&times;</button>
      </div>
      
      <!-- Patient Info Card -->
      <div style="background:white;border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-top:12px;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Patient Name</div>
            <div style="font-size:14px;font-weight:600;color:var(--gray-900);margin-top:2px;" id="mf-patient-name">—</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Patient ID</div>
            <div style="font-size:14px;font-weight:600;color:var(--primary-color);margin-top:2px;font-family:monospace;" id="mf-patient-id">—</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Queue #</div>
            <div style="font-size:14px;font-weight:600;color:var(--gray-900);margin-top:2px;" id="mf-queue-no">—</div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Form Content -->
    <div style="padding:20px;">
      <!-- Form selector -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">
          <i class="fas fa-clipboard-list"></i> Select Form
        </label>
        <select id="mf-form-select" onchange="mfLoadFormFields()" style="width:100%;padding:10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
          <option value="">— Select Form —</option>
        </select>
      </div>

      <!-- Form fields container -->
      <div id="mf-form-fields" style="display:none;background:var(--gray-50);border-radius:6px;padding:16px;border:1px solid var(--gray-200);max-height:350px;overflow-y:auto;margin-bottom:16px;"></div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:8px;">
        <button onclick="hideFormModal()" style="flex:1;padding:11px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;color:var(--gray-700);">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button onclick="mfSaveForm()" style="flex:1;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;">
          <i class="fas fa-save"></i> Save Form & Serve Patient
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Lab / Radiology Order Modal -->
<div id="modal-labOrder" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;" id="modal-labOrder-title"><i class="fas fa-flask" style="color:#2563eb;"></i> Lab Test Order</h3>
      <button onclick="hideModal('labOrder')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <!-- Patient info -->
      <div style="background:var(--gray-50);border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;color:var(--gray-700);" id="lab-patient-info">No patient selected</div>

      <!-- Order Type -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Order Type</label>
        <select id="mo-lab-type" onchange="labLoadTests()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;">
          <option value="lab">Lab Test</option>
          <option value="radiology">Radiology / Imaging</option>
        </select>
      </div>

      <!-- Available Tests -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">Select Tests</label>
        <div id="lab-tests-list" style="border:1px solid var(--gray-200);border-radius:8px;max-height:200px;overflow-y:auto;padding:4px;">
          <div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">Loading tests...</div>
        </div>
      </div>

      <!-- Selected Tests Summary -->
      <div id="lab-selected-summary" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:600;color:#1e40af;margin-bottom:6px;">Selected Tests:</div>
        <div id="lab-selected-list" style="font-size:12px;color:#1e40af;"></div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;padding-top:8px;border-top:1px solid #bfdbfe;">
          <span style="font-size:13px;font-weight:700;color:#1e40af;">Total:</span>
          <span style="font-size:13px;font-weight:700;color:#1e40af;" id="lab-total-price">0 RWF</span>
        </div>
      </div>

      <!-- Notes -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Clinical Notes</label>
        <textarea id="mo-lab-details" rows="2" placeholder="Any specific instructions..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;resize:vertical;font-size:13px;"></textarea>
      </div>

      <!-- Insurance toggle -->
      <div style="margin-bottom:12px;" id="lab-ins-toggle">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:var(--gray-700);cursor:pointer;">
          <input type="checkbox" id="lab-apply-insurance" onchange="labUpdateSummary()"> Apply Insurance Coverage
        </label>
        <div id="lab-ins-info" style="display:none;margin-top:6px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:8px 10px;font-size:12px;color:#1e40af;">
          <span id="lab-ins-detail"></span>
        </div>
      </div>

      <button onclick="labSubmitOrder()" id="btn-mo-lab" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
        <i class="fas fa-paper-plane"></i> Send Order & Create Invoice
      </button>
    </div>
  </div>
</div>

<!-- Prescription Modal (Multi-Drug) -->
<div id="modal-prescription" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:700px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-prescription" style="color:#16a34a;"></i> Drug Prescription</h3>
      <button onclick="hideModal('prescription')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <!-- Patient Info -->
      <div style="background:var(--gray-50);border-radius:6px;padding:10px 14px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:13px;font-weight:600;color:var(--gray-700);" id="rx-patient-info">No patient selected</span>
        <span style="font-size:12px;color:var(--gray-500);" id="rx-visit-info"></span>
      </div>

      <!-- Diagnosis -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Diagnosis / Indication</label>
        <input type="text" id="rx-diagnosis" placeholder="e.g. Upper Respiratory Infection" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>

      <!-- Drug Items Table -->
      <div style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <label style="font-size:13px;font-weight:600;color:var(--gray-700);">Medications</label>
          <button onclick="rxAddRow()" type="button" style="padding:6px 12px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;">
            <i class="fas fa-plus"></i> Add Drug
          </button>
        </div>
        <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;" id="rx-drugs-table">
            <thead>
              <tr style="background:var(--gray-50);">
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);width:30%;">Drug</th>
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);">Dosage</th>
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);">Frequency</th>
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);">Duration</th>
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);">Qty</th>
                <th style="padding:8px;text-align:center;font-weight:600;color:var(--gray-600);width:30px;"></th>
              </tr>
            </thead>
            <tbody id="rx-drugs-tbody">
            </tbody>
          </table>
        </div>
      </div>

      <!-- Notes -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Notes</label>
        <textarea id="rx-notes" rows="2" placeholder="Additional notes..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
      </div>

      <!-- Destination -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">Send To</label>
        <div style="display:flex;gap:12px;">
          <label style="flex:1;display:flex;align-items:center;gap:8px;padding:12px;border:2px solid var(--primary-color);border-radius:8px;cursor:pointer;background:var(--primary-light);">
            <input type="radio" name="rx-destination" value="hospital" checked style="accent-color:var(--primary-color);">
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--gray-900);">Hospital Pharmacy</div>
              <div style="font-size:11px;color:var(--gray-500);">Send to dispensing queue</div>
            </div>
          </label>
          <label style="flex:1;display:flex;align-items:center;gap:8px;padding:12px;border:2px solid var(--gray-200);border-radius:8px;cursor:pointer;" id="rx-external-label">
            <input type="radio" name="rx-destination" value="external" style="accent-color:var(--primary-color);">
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--gray-900);">External Pharmacy</div>
              <div style="font-size:11px;color:var(--gray-500);">Print for patient</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:8px;">
        <button onclick="hideModal('prescription')" style="padding:10px 16px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;color:var(--gray-700);">
          Cancel
        </button>
        <button onclick="rxSubmitPrescription()" style="flex:1;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
          <i class="fas fa-check"></i> Submit Prescription
        </button>
        <button onclick="rxSubmitAndPrint()" style="padding:10px 16px;background:#1e40af;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;" id="rx-print-btn" title="Submit & Print">
          <i class="fas fa-print"></i> Print
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Referral Modal -->
<div id="modal-referral" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:500px;max-width:90vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;">Refer to Department</h3>
      <button onclick="hideModal('referral')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Referred To <span style="color:var(--danger-color)">*</span></label>
        <select id="mo-ref-dept" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;">
        <option value="">Select department...</option>
        </select>
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Referral Notes / Reason</label>
        <textarea id="mo-ref-notes" rows="3" placeholder="Reason for referral, findings, suspected diagnosis..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;resize:vertical;"></textarea>
      </div>
      <button onclick="submitReferral()" id="btn-mo-ref" style="width:100%;padding:10px;background:#dc2626;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
        <i class="fas fa-share"></i> Send Referral
      </button>
    </div>
  </div>
</div>

<!-- Admission Modal -->
<!-- Admission Request Modal (Doctor just sends request) -->
<div id="modal-admission" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:450px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-procedures" style="color:#7c3aed;"></i> Request Admission</h3>
      <button onclick="hideModal('admission')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="background:var(--gray-50);border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;color:var(--gray-700);" id="admit-patient-info">No patient selected</div>

      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Admission Reason <span style="color:var(--danger-color);">*</span></label>
        <textarea id="admit-diagnosis" rows="3" placeholder="Reason for admission / diagnosis..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;resize:vertical;font-size:13px;"></textarea>
      </div>

      <div style="margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Type</label>
          <select id="admit-type" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <option value="elective">Elective</option>
            <option value="emergency">Emergency</option>
            <option value="transfer">Transfer</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Preferred Ward</label>
          <select id="admit-ward-pref" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
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

      <button onclick="admitSubmit()" style="width:100%;padding:10px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
        <i class="fas fa-paper-plane"></i> Send Admission Request
      </button>
    </div>
  </div>
</div>

<script>
// ============================================================
// Configuration — ALL data fetched via API (like patients.php)
// ============================================================
const dqAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=departments/department-forms';
const consultAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult';
const visitsAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=patients/visits';
const deptAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=hr/departments';
const usersAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=settings/users';

let loggedUserId = 0;
let deptId = 0;
let loggedUserName = '';
let dqCurrentPatient = null;
let dqCurrentVisitId = null;
let dqCurrentConsultId = null;

// ============================================================
// Bootstrap — get department from users table via API
// ============================================================
async function bootDepartment() {
  try {
    // Get user data stored by login page
    const userData = JSON.parse(localStorage.getItem('user_data') || '{}');
    loggedUserId = userData.id || 0;

    if (!loggedUserId) {
      document.getElementById('dept-page-title').textContent = 'Not Logged In';
      document.getElementById('dept-page-subtitle').textContent = 'Please log in to view department queue.';
      return;
    }

    // Load full user details including department_id and department_name from API
    const r = await fetch(usersAPI + '&id=' + loggedUserId);
    const j = await r.json();
    if (j.success && j.data) {
      const u = j.data;
      loggedUserName = (u.first_name || '') + ' ' + (u.last_name || '');
      loggedUserName = loggedUserName.trim() || 'User';
      
      // Get department_id from users table
      if (u.department_id) {
        deptId = parseInt(u.department_id);
      }

      // Department name comes from the JOIN in settings/users API
      let deptName = u.department_name || 'My Department';
      if (!deptId) deptName = 'No Department Assigned';

      document.getElementById('dept-page-title').textContent = deptName;
      document.getElementById('dept-page-subtitle').textContent = deptId ? 'Patient queue management & department forms' : 'Please contact administrator to assign a department.';

      // Load departments for referral dropdown
      loadReferralDepartments();

      // Load medications for prescription
      rxLoadMedications();

      // Load lab/radiology services
      labLoadServices();

      // Start queue polling
      dqLoad();
      setInterval(dqLoad, 15000);
    } else {
      document.getElementById('dept-page-subtitle').textContent = 'Could not load user data';
    }
  } catch(e) {
    document.getElementById('dept-page-subtitle').textContent = 'Error loading: ' + e.message;
  }
}

// Load all departments for referral modal
async function loadReferralDepartments() {
  const sel = document.getElementById('mo-ref-dept');
  sel.innerHTML = '<option value="">Select department...</option>';
  try {
    const r = await fetch(deptAPI + '&limit=200');
    const j = await r.json();
    if (j.success && j.data) {
      j.data.forEach(d => {
        if (parseInt(d.id) === deptId) return; // skip own department
        const o = document.createElement('option');
        o.value = d.id;
        o.textContent = d.name;
        sel.appendChild(o);
      });
    }
  } catch(e) {}
}

// ============================================================
// Utilities
// ============================================================
function dqEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function showModal(id){document.getElementById('modal-'+id).style.display='flex';}
function hideModal(id){document.getElementById('modal-'+id).style.display='none';}
function getAPIPath(p){ return window.location.origin + p; }

// Search/filter queue table
function dqFilterQueue(){
  const term = document.getElementById('dq-search').value.toLowerCase();
  const rows = document.querySelectorAll('#dq-tbody tr');
  rows.forEach(row => {
    if(!term){ row.style.display = ''; return; }
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(term) ? '' : 'none';
  });
}

// ============================================================
// Show Lab/Radiology Modal with pre-selected type
// ============================================================
// Quick action from queue row - set patient context then open order menu
function dqOpenOrderMenu(visitId, patientId, visitNo, patientName, queueNo){
  dqCurrentVisitId = visitId;
  dqCurrentPatient = {visitId, patientId, visitNo, patientName, queueNo};
  document.getElementById('om-patient-label').textContent = 'Patient: ' + patientName + ' (' + visitNo + ')';
  showModal('orderMenu');
}

// Open patient details modal - show form data, prescriptions, orders
async function dqOpenDetails(visitId, patientId, visitNo, patientName, queueNo){
  dqCurrentVisitId = visitId;
  dqCurrentPatient = {visitId, patientId, visitNo, patientName, queueNo};
  
  const content = document.getElementById('pd-content');
  content.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i> Loading...</div>';
  showModal('patientDetails');

  let html = `<div style="margin-bottom:16px;background:var(--gray-50);border-radius:8px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
    <div><span style="font-size:16px;font-weight:700;color:var(--gray-900);">${dqEsc(patientName)}</span>
    <span style="font-size:12px;color:var(--gray-500);margin-left:8px;">${dqEsc(visitNo)}</span></div>
    <span style="padding:4px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;">Now Serving</span>
  </div>`;

  // 1. Load form data
  try{
    const r = await fetch(getAPIPath('api.php?endpoint=departments/department-forms&action=submitted&visit_id='+visitId));
    const j = await r.json();
    if(j.success && j.data && j.data.length > 0){
      for(const form of j.data){
        // Load template to get field labels and form name
        let fieldLabels = {};
        let formName = 'Department Form';
        try{
          const tr = await fetch(getAPIPath('api.php?endpoint=departments/department-forms&action=templates&id='+form.template_id));
          const tj = await tr.json();
          if(tj.success && tj.data){
            formName = tj.data.name || 'Department Form';
            if(tj.data.fields){
              tj.data.fields.forEach(f => { fieldLabels[f.field_key] = f.field_label; });
            }
          }
        } catch(e){}

        html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-clipboard-list" style="color:var(--primary-color);"></i> ${dqEsc(formName)}
        </h4>`;

        let formData = form.form_data;
        if(typeof formData === 'string') try{ formData = JSON.parse(formData); } catch(e){}
        
        html += `<div style="background:var(--gray-50);border-radius:6px;padding:12px 14px;margin-bottom:8px;border:1px solid var(--gray-200);">`;
        if(formData && typeof formData === 'object'){
          Object.entries(formData).forEach(([key, val]) => {
            if(val !== null && val !== ''){
              const label = fieldLabels[key] || key.replace(/_/g, ' ');
              html += `<div style="display:flex;gap:8px;font-size:13px;padding:5px 0;border-bottom:1px solid var(--gray-100);">
                <span style="font-weight:600;color:var(--gray-600);min-width:140px;">${dqEsc(label)}</span>
                <span style="color:var(--gray-900);">${dqEsc(String(val))}</span>
              </div>`;
            }
          });
        }
        html += `<div style="font-size:11px;color:var(--gray-400);margin-top:8px;">Submitted: ${form.created_at||'—'}</div></div>
        </div>`;
      }
    } else {
      html += `<div style="margin-bottom:16px;padding:12px;background:#fef3c7;border-radius:6px;font-size:12px;color:#92400e;">
        <i class="fas fa-info-circle"></i> No department form data submitted yet.
      </div>`;
    }
  } catch(e){}

  // 2. Load prescriptions with items
  try{
    const r = await fetch(getAPIPath('api.php?endpoint=pharmacy/prescriptions&visit_id='+visitId));
    const j = await r.json();
    if(j.success && j.data && j.data.prescriptions && j.data.prescriptions.length > 0){
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-prescription" style="color:#16a34a;"></i> Prescriptions
        </h4>`;
      
      for(const rx of j.data.prescriptions){
        const destBadge = rx.destination === 'external' ? '<span style="font-size:10px;padding:2px 6px;background:#fef3c7;border-radius:8px;color:#92400e;">External</span>' : '<span style="font-size:10px;padding:2px 6px;background:#dbeafe;border-radius:8px;color:#1e40af;">Hospital</span>';
        html += `<div style="background:var(--gray-50);border-radius:6px;padding:12px 14px;margin-bottom:8px;border:1px solid var(--gray-200);font-size:12px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span style="font-weight:600;color:var(--primary-color);font-family:monospace;">${dqEsc(rx.prescription_no)}</span>
            <div>${destBadge} <span style="font-size:10px;color:var(--gray-500);margin-left:4px;">${rx.prescription_date||''}</span></div>
          </div>`;
        if(rx.diagnosis) html += `<div style="color:var(--gray-600);margin-bottom:8px;"><strong>Diagnosis:</strong> ${dqEsc(rx.diagnosis)}</div>`;
        
        // Load items for this prescription
        try{
          const ir = await fetch(getAPIPath('api.php?endpoint=pharmacy/prescriptions&id='+rx.id));
          const ij = await ir.json();
          if(ij.success && ij.data && ij.data.items && ij.data.items.length > 0){
            html += `<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:4px;">
              <thead><tr style="background:white;border-bottom:1px solid var(--gray-200);">
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Drug</th>
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Dosage</th>
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Frequency</th>
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Duration</th>
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Qty</th>
              </tr></thead><tbody>`;
            ij.data.items.forEach(item => {
              const drugName = item.name || item.instructions || item.drug_name || ('Item #'+item.item_id);
              html += `<tr style="border-bottom:1px solid var(--gray-100);">
                <td style="padding:5px 8px;font-weight:500;color:var(--gray-900);">${dqEsc(drugName)}</td>
                <td style="padding:5px 8px;color:var(--gray-700);">${dqEsc(item.dosage||'—')}</td>
                <td style="padding:5px 8px;color:var(--gray-700);">${dqEsc(item.frequency||'—')}</td>
                <td style="padding:5px 8px;color:var(--gray-700);">${dqEsc(item.duration||'—')}</td>
                <td style="padding:5px 8px;color:var(--gray-700);">${item.quantity||'—'}</td>
              </tr>`;
            });
            html += `</tbody></table>`;
          }
        } catch(e){}
        
        html += `</div>`;
      }
      html += `</div>`;
    }
  } catch(e){}

  // 3. Load orders (lab, radiology)
  try{
    const r = await fetch(getAPIPath(consultAPI+'&action=orders&visit_id='+visitId));
    const j = await r.json();
    if(j.success && j.data && j.data.length > 0){
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-flask" style="color:#2563eb;"></i> Medical Orders & Results
        </h4>`;
      j.data.forEach(o => {
        const typeIcon = o.order_type === 'radiology' ? 'fa-x-ray' : o.order_type === 'medication' ? 'fa-pills' : 'fa-flask';
        const typeColor = o.order_type === 'radiology' ? '#d97706' : o.order_type === 'medication' ? '#16a34a' : '#2563eb';
        const statusBg = o.status === 'completed' ? '#d1fae5' : '#dbeafe';
        const statusColor = o.status === 'completed' ? '#065f46' : '#1e40af';
        html += `<div style="background:var(--gray-50);border-radius:6px;padding:10px 12px;margin-bottom:6px;border:1px solid var(--gray-200);font-size:12px;">
          <div style="display:flex;gap:10px;align-items:center;">
            <i class="fas ${typeIcon}" style="color:${typeColor};width:16px;"></i>
            <div style="flex:1;">
              <div style="font-weight:600;color:var(--gray-900);">${dqEsc(o.description||o.order_type)}</div>
              ${o.details ? '<div style="color:var(--gray-500);font-size:11px;">'+dqEsc(o.details)+'</div>' : ''}
            </div>
            <span style="font-size:10px;padding:2px 6px;border-radius:8px;background:${statusBg};color:${statusColor};">${dqEsc(o.status||'ordered')}</span>
          </div>
          ${o.result ? '<div style="margin-top:8px;padding:8px 10px;background:white;border:1px solid #bbf7d0;border-radius:4px;font-size:12px;white-space:pre-wrap;color:var(--gray-800);"><strong style="color:#065f46;">Result:</strong> '+dqEsc(o.result)+'</div>' : ''}
          ${o.completed_at ? '<div style="font-size:10px;color:var(--gray-400);margin-top:4px;">Completed: '+dqEsc(o.completed_at)+'</div>' : ''}
        </div>`;
      });
      html += `</div>`;
    }
  } catch(e){}

  // If nothing loaded
  if(html.indexOf('Department Form Data') === -1 && html.indexOf('Prescriptions') === -1 && html.indexOf('Medical Orders') === -1){
    html += `<div style="text-align:center;padding:20px;color:var(--gray-400);font-size:13px;">
      <i class="fas fa-folder-open" style="font-size:32px;display:block;margin-bottom:8px;"></i>
      No details recorded yet for this visit.
    </div>`;
  }

  content.innerHTML = html;
}

function dqQuickAction(visitId, patientId, visitNo, patientName, queueNo, action){
  dqCurrentVisitId = visitId;
  dqCurrentPatient = {visitId, patientId, visitNo, patientName, queueNo};
  
  if(action === 'prescription'){
    rxOpenModal();
  } else if(action === 'lab'){
    showLabModal('lab');
  } else if(action === 'radiology'){
    showLabModal('radiology');
  } else if(action === 'referral'){
    showModal('referral');
  } else if(action === 'admission'){
    showModal('admission');
  }
}

// ============================================================
// LAB/RADIOLOGY ORDER SYSTEM
// ============================================================
let labServices = [];

async function labLoadServices(){
  try{
    const r = await fetch(getAPIPath('api.php?endpoint=patients/billing&action=services&category=lab,radiology'));
    const j = await r.json();
    if(j.success && j.data) labServices = j.data;
  } catch(e){ console.error('Failed to load lab services:', e); }
}

async function showLabModal(type){
  if(!dqCurrentPatient) return alert('Select a patient first.');
  document.getElementById('mo-lab-type').value = type;
  document.getElementById('modal-labOrder-title').innerHTML = 
    type === 'lab' ? '<i class="fas fa-flask" style="color:#2563eb;"></i> Lab Test Order' : '<i class="fas fa-x-ray" style="color:#d97706;"></i> Radiology Order';
  document.getElementById('lab-patient-info').textContent = dqCurrentPatient.patientName + ' (' + dqCurrentPatient.visitNo + ')';
  
  // Get insurance info from visit
  window._labInsCopay = 100;
  window._labInsId = null;
  try{
    const vr = await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId));
    const vj = await vr.json();
    if(vj.success && vj.data && vj.data.insurance_id && (vj.data.payment_type==='insurance'||vj.data.payment_type==='mixed')){
      window._labInsId = vj.data.insurance_id;
      const ir = await fetch(getAPIPath('api.php?endpoint=insurance/packages&company_id='+vj.data.insurance_id));
      const ij = await ir.json();
      const pkgs = ij.data?.packages||ij.data||[];
      if(pkgs.length) window._labInsCopay = parseFloat(pkgs[0].copayment_percentage)||100;
      document.getElementById('lab-ins-toggle').style.display = 'block';
      document.getElementById('lab-apply-insurance').checked = true;
    } else {
      document.getElementById('lab-ins-toggle').style.display = 'none';
      document.getElementById('lab-apply-insurance').checked = false;
    }
  }catch(e){}
  
  labLoadTests();
  showModal('labOrder');
}

function labLoadTests(){
  const type = document.getElementById('mo-lab-type').value;
  const container = document.getElementById('lab-tests-list');
  const filtered = labServices.filter(s => s.category === type);
  
  if(!filtered.length){
    container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">No tests available for this category.</div>';
    return;
  }
  
  container.innerHTML = filtered.map(s => `
    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--gray-50);cursor:pointer;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
      <input type="checkbox" class="lab-test-check" value="${s.id}" data-name="${dqEsc(s.name)}" data-price="${s.unit_price}" onchange="labUpdateSummary()" style="accent-color:var(--primary-color);cursor:pointer;">
      <div style="flex:1;">
        <div style="font-size:13px;font-weight:500;color:var(--gray-900);">${dqEsc(s.name)}</div>
        <div style="font-size:11px;color:var(--gray-500);">${dqEsc(s.code||'')}</div>
      </div>
      <span style="font-size:13px;font-weight:600;color:#16a34a;">${parseFloat(s.unit_price).toLocaleString()} RWF</span>
    </label>
  `).join('');
}

function labUpdateSummary(){
  const checks = document.querySelectorAll('.lab-test-check:checked');
  const summaryDiv = document.getElementById('lab-selected-summary');
  const listDiv = document.getElementById('lab-selected-list');
  const insCheck = document.getElementById('lab-apply-insurance');
  const insInfo = document.getElementById('lab-ins-info');
  
  if(!checks.length){
    summaryDiv.style.display = 'none';
    insInfo.style.display = 'none';
    return;
  }
  
  let total = 0;
  let items = [];
  checks.forEach(cb => {
    const name = cb.getAttribute('data-name');
    const price = parseFloat(cb.getAttribute('data-price'));
    total += price;
    items.push(name + ' (' + price.toLocaleString() + ')');
  });
  
  listDiv.innerHTML = items.map(i => '• ' + i).join('<br>');
  
  if(insCheck.checked && window._labInsCopay < 100){
    const copay = window._labInsCopay || 15;
    const coverage = 100 - copay;
    const insAmount = Math.round(total * coverage / 100);
    const patientAmount = total - insAmount;
    document.getElementById('lab-total-price').textContent = total.toLocaleString() + ' RWF';
    insInfo.style.display = 'block';
    insInfo.innerHTML = 'Insurance covers '+coverage+'%: <strong>'+insAmount.toLocaleString()+'</strong> | Patient pays '+copay+'%: <strong>'+patientAmount.toLocaleString()+' RWF</strong>';
  } else {
    document.getElementById('lab-total-price').textContent = total.toLocaleString() + ' RWF';
    insInfo.style.display = 'none';
  }
  summaryDiv.style.display = 'block';
}

async function labSubmitOrder(){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  
  const checks = document.querySelectorAll('.lab-test-check:checked');
  if(!checks.length) return alert('Please select at least one test.');
  
  const type = document.getElementById('mo-lab-type').value;
  const notes = document.getElementById('mo-lab-details').value.trim();
  
  const selectedTests = [];
  checks.forEach(cb => {
    selectedTests.push({
      service_id: parseInt(cb.value),
      name: cb.getAttribute('data-name'),
      price: parseFloat(cb.getAttribute('data-price'))
    });
  });
  
  try{
    const applyIns = document.getElementById('lab-apply-insurance')?.checked && window._labInsCopay < 100;
    const r = await fetch(getAPIPath('api.php?endpoint=patients/billing&action=lab_order'), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        visit_id: dqCurrentVisitId,
        patient_id: dqCurrentPatient.patientId,
        order_type: type,
        notes: notes,
        tests: selectedTests,
        ordered_by: loggedUserId,
        insurance_id: applyIns ? window._labInsId : null,
        copayment_percentage: applyIns ? window._labInsCopay : 100
      })
    });
    const j = await r.json();
    if(j.success){
      hideModal('labOrder');
      alert('✓ '+type.charAt(0).toUpperCase()+type.slice(1)+' order sent! Invoice: '+(j.data?.invoice_no||'Created'));
      loadOrders(dqCurrentVisitId);
      // Reset
      document.querySelectorAll('.lab-test-check:checked').forEach(cb => cb.checked = false);
      labUpdateSummary();
      document.getElementById('mo-lab-details').value = '';
    } else {
      alert('Error: '+j.message);
    }
  } catch(e){ alert('Failed: '+e.message); }
}

// ============================================================
// 1. QUEUE MANAGEMENT
// ============================================================
async function dqLoad(){
  if(!deptId) {
    document.getElementById('dq-tbody').innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-exclamation-triangle" style="font-size:36px;display:block;margin-bottom:8px;"></i>No department assigned.</td></tr>';
    return;
  }
  try{
    const r=await fetch(getAPIPath(visitsAPI+'&action=department_queue&department_id='+deptId));
    const j=await r.json();
    if(!j.success){ document.getElementById('dq-tbody').innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);">Error: '+dqEsc(j.message)+'</td></tr>'; return; }
    const {waiting, in_consultation, done, current_serving, stats} = j.data;

    document.getElementById('dq-waiting').textContent = stats?.waiting || 0;
    document.getElementById('dq-consulting').textContent = stats?.in_consultation || 0;
    document.getElementById('dq-done').textContent = stats?.done_today || 0;
    document.getElementById('dq-current').textContent = current_serving ? ('#'+current_serving.queue_no) : '—';

    const badge = document.getElementById('deptQueueBadge');
    if(badge) badge.textContent = stats?.waiting || 0;

    const queueList = [...(waiting||[]), ...(in_consultation||[])];
    const tb = document.getElementById('dq-tbody');
    if(!queueList.length){
      tb.innerHTML = '<tr><td colspan="6" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:36px;display:block;margin-bottom:8px;color:var(--success-color);"></i>No patients in queue. All clear!</td></tr>';
    } else {
      tb.innerHTML = queueList.map(v => {
        const isCurrent = (current_serving && v.id === current_serving.id) || v.consultation_status === 'now_serving';
        const statusHtml = isCurrent
          ? '<span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#d1fae5;color:#065f46;"><i class="fas fa-stethoscope"></i> Now Serving</span>'
          : (v.consultation_status === 'in_consultation'
            ? '<span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#dbeafe;color:#1e40af;">In Consultation</span>'
            : '<span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#fef3c7;color:#92400e;">Waiting</span>');
        
        // Action buttons - show order button for "Now Serving" patients
        let actionHtml = '';
        if(isCurrent){
          actionHtml = `<div style="display:flex;gap:4px;align-items:center;justify-content:center;">
            <button onclick="dqSelectPatient(${v.id}, ${v.patient_id}, '${dqEsc(v.visit_no)}', '${dqEsc(v.patient_name||'')}', '${dqEsc(v.queue_no||'')}', true)" style="padding:5px 10px;background:var(--success-color);color:white;border:none;border-radius:4px;font-size:11px;font-weight:500;cursor:pointer;" title="Open Form">
              <i class="fas fa-clipboard-list"></i>
            </button>
            <button onclick="dqOpenOrderMenu(${v.id}, ${v.patient_id}, '${dqEsc(v.visit_no)}', '${dqEsc(v.patient_name||'')}', '${dqEsc(v.queue_no||'')}')" style="padding:5px 10px;background:#1e40af;color:white;border:none;border-radius:4px;font-size:11px;font-weight:500;cursor:pointer;" title="Electronic Orders">
              <i class="fas fa-file-medical"></i> Order
            </button>
            <button onclick="dqOpenDetails(${v.id}, ${v.patient_id}, '${dqEsc(v.visit_no)}', '${dqEsc(v.patient_name||'')}', '${dqEsc(v.queue_no||'')}')" style="padding:5px 10px;background:#6b21a8;color:white;border:none;border-radius:4px;font-size:11px;font-weight:500;cursor:pointer;" title="View Details">
              <i class="fas fa-eye"></i> Details
            </button>
            <button onclick="dqMarkDone()" style="padding:5px 8px;background:var(--gray-600);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;" title="Mark Done">
              <i class="fas fa-check-double"></i>
            </button>
          </div>`;
        } else {
          actionHtml = `<button onclick="dqSelectPatient(${v.id}, ${v.patient_id}, '${dqEsc(v.visit_no)}', '${dqEsc(v.patient_name||'')}', '${dqEsc(v.queue_no||'')}', true)" style="padding:6px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;">
            <i class="fas fa-user-md"></i> Consult
          </button>`;
        }

        return `<tr style="border-bottom:1px solid var(--gray-100);${isCurrent?'background:#f0fdf4;':''}">
          <td style="padding:10px 12px;font-weight:700;font-size:16px;color:var(--gray-900);">${dqEsc(v.queue_no||'—')}</td>
          <td style="padding:10px 12px;">
            <div style="font-weight:600;">${dqEsc(v.patient_name||'Unknown')}</div>
            <div style="font-size:12px;color:var(--gray-500);">${dqEsc(v.patient_no||'')}</div>
          </td>
          <td style="padding:10px 12px;font-family:monospace;font-size:13px;color:var(--primary-color);">${dqEsc(v.visit_no)}</td>
          <td style="padding:10px 12px;">${statusHtml}</td>
          <td style="padding:10px 12px;font-size:13px;color:var(--gray-500);">${v.queued_at ? new Date(v.queued_at).toLocaleTimeString() : '—'}</td>
          <td style="padding:10px 12px;text-align:center;">
            ${actionHtml}
          </td>
        </tr>`;
      }).join('');
    }

    // Done list
    const doneTbody = document.getElementById('dq-done-tbody');
    if(done && done.length){
      doneTbody.innerHTML = done.map(v => `
        <tr style="border-bottom:1px solid var(--gray-50);">
          <td style="padding:8px 12px;font-weight:600;font-size:13px;">${dqEsc(v.queue_no||'—')}</td>
          <td style="padding:8px 12px;font-size:13px;">${dqEsc(v.patient_name||'')}</td>
          <td style="padding:8px 12px;"><span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;">Done</span></td>
          <td style="padding:8px 12px;text-align:center;">
            <button onclick="dqSelectPatient(${v.id}, ${v.patient_id}, '${dqEsc(v.visit_no)}', '${dqEsc(v.patient_name||'')}', '${dqEsc(v.queue_no||'')}', true)" style="padding:4px 10px;background:var(--gray-50);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:4px;font-size:11px;cursor:pointer;">View</button>
          </td>
        </tr>`).join('');
    } else {
      doneTbody.innerHTML = '<tr><td colspan="4" style="padding:24px;text-align:center;color:var(--gray-400);">No patients seen yet today.</td></tr>';
    }
  } catch(e){ document.getElementById('dq-tbody').innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);">Error: '+e.message+'</td></tr>'; }
}

async function dqCallNext(){
  if(!deptId) return alert('No department assigned.');
  const btn = document.getElementById('dqCallNextBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calling...';
  try{
    const r = await fetch(getAPIPath(visitsAPI+'&action=call_next&department_id='+deptId), {method:'POST'});
    const j = await r.json();
    if(j.success){
      dqLoad();
      if(j.data) dqSelectPatient(j.data.id, j.data.patient_id, j.data.visit_no, j.data.patient_name, j.data.queue_no);
    } else { alert(j.message || 'No waiting patients.'); }
  } catch(e){ alert('Failed: '+e.message); }
  finally{ btn.disabled=false; btn.innerHTML='<i class="fas fa-forward"></i> Call Next'; }
}

// ============================================================
// 2. SELECT PATIENT — Open form modal
// ============================================================
async function dqSelectPatient(visitId, patientId, visitNo, patientName, queueNo, openModal){
  dqCurrentVisitId = visitId;
  dqCurrentPatient = {visitId, patientId, visitNo, patientName, queueNo};

  // Show actions panel
  document.getElementById('dq-actions').style.display = 'block';
  document.getElementById('dq-action-patient').textContent = patientName+' ('+visitNo+')';

  // Only open modal if explicitly requested (Consult button click)
  if(openModal){
    document.getElementById('mf-patient-name').textContent = dqEsc(patientName);
    document.getElementById('mf-patient-id').textContent = '#'+dqEsc(patientId);
    document.getElementById('mf-queue-no').textContent = '#'+dqEsc(queueNo);
    document.getElementById('modal-departmentForm').style.display = 'flex';
    await mfLoadDepartmentForms();
    await mfLoadPreviousFormData();
  }

  // Load existing orders for this visit
  loadOrders(visitId);
}

// ============================================================
// 3. CONSULTATION SAVE
// ============================================================
async function saveConsultation(status){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  const data = {
    visit_id: dqCurrentVisitId,
    patient_id: dqCurrentPatient.patientId,
    department_id: deptId,
    doctor_id: loggedUserId,
    doctor_name: loggedUserName,
    bp_systolic: document.getElementById('c-bp-sys').value || null,
    bp_diastolic: document.getElementById('c-bp-dia').value || null,
    pulse: document.getElementById('c-pulse').value || null,
    temperature: document.getElementById('c-temp').value || null,
    weight: document.getElementById('c-weight').value || null,
    oxygen_sat: document.getElementById('c-o2').value || null,
    chief_complaint: document.getElementById('c-complaint').value,
    history: document.getElementById('c-history').value,
    examination: document.getElementById('c-exam').value,
    diagnosis: document.getElementById('c-diagnosis').value,
    treatment_plan: document.getElementById('c-treatment').value,
    status: status,
    created_by: loggedUserId
  };

  try{
    const r = await fetch(getAPIPath(consultAPI), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(data)
    });
    const j = await r.json();
    if(j.success){
      dqCurrentConsultId = j.data?.id || dqCurrentConsultId;
      
      // Submit department form if one is selected
      const formPayload = dqCollectFormData();
      if(formPayload && formPayload.template_id){
        try{
          await fetch(getAPIPath('api.php?endpoint=departments/department-forms&action=submit'), {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
              visit_id: dqCurrentVisitId,
              patient_id: dqCurrentPatient.patientId,
              template_id: formPayload.template_id,
              department_id: deptId,
              filled_by: loggedUserId,
              form_data: formPayload.data,
              status: status === 'completed' ? 'completed' : 'draft'
            })
          });
        } catch(fe){ console.error('Form submission failed:', fe); }
      }
      
      alert('Consultation '+(status === 'completed' ? 'completed' : 'draft saved')+'!');
      if(status === 'completed') {
        // Mark visit as done
        await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId), {
          method:'PUT',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({consultation_status:'done', status:'completed'})
        });
        dqCurrentVisitId = null; dqCurrentPatient = null;
        document.getElementById('dq-consult-panel').innerHTML = `
          <div style="text-align:center;padding:30px 20px;">
            <div style="font-size:48px;color:var(--success-color);margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
            <h4 style="font-size:16px;font-weight:600;color:var(--gray-600);">Consultation Completed</h4>
            <p style="font-size:13px;color:var(--gray-400);margin-top:4px;">Patient has been marked as done.</p>
          </div>`;
        document.getElementById('dq-actions').style.display = 'none';
        document.getElementById('dq-consult-badge').textContent = 'No patient';
        dqLoad();
      }
    } else { alert('Error: '+j.message); }
  } catch(e){ alert('Failed: '+e.message); }
}

// ============================================================
// 4. MEDICAL ORDERS (Lab, Radiology, Prescription)
// ============================================================
// Auto-save a draft consultation if none exists (for orders to work)
async function ensureConsultation() {
  if (dqCurrentConsultId) return dqCurrentConsultId;
  if (!dqCurrentVisitId || !dqCurrentPatient || !deptId) return null;
  
  // Check again via API
  try {
    const r = await fetch(getAPIPath(consultAPI+'&visit_id='+dqCurrentVisitId+'&department_id='+deptId));
    const j = await r.json();
    if (j.success && j.data && j.data.id) {
      dqCurrentConsultId = j.data.id;
      return dqCurrentConsultId;
    }
  } catch(e) {}

  // Create a minimal draft consultation
  try {
    const data = {
      visit_id: dqCurrentVisitId,
      patient_id: dqCurrentPatient.patientId,
      department_id: deptId,
      doctor_id: loggedUserId,
      doctor_name: loggedUserName,
      status: 'draft',
      created_by: loggedUserId
    };
    const r = await fetch(getAPIPath(consultAPI), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(data)
    });
    const j = await r.json();
    if (j.success && j.data) {
      dqCurrentConsultId = j.data.id;
      return dqCurrentConsultId;
    }
  } catch(e) {}
  return null;
}

function buildOrderPayload(orderType, description, details){
  return {
    consultation_id: dqCurrentConsultId,
    visit_id: dqCurrentVisitId,
    patient_id: dqCurrentPatient.patientId,
    order_type: orderType,
    description: description,
    details: details,
    ordered_by: loggedUserId
  };
}

async function submitMedicalOrder(){
  const type = document.getElementById('mo-lab-type').value;
  const desc = document.getElementById('mo-lab-desc').value.trim();
  const details = document.getElementById('mo-lab-details').value.trim();
  if(!desc) return alert('Please enter the test name/description.');

  // Ensure consultation exists first
  const cid = await ensureConsultation();
  if (!cid) return alert('Could not create consultation. Please save a draft first.');

  const btn = document.getElementById('btn-mo-lab');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
  try{
    const r = await fetch(getAPIPath(consultAPI+'&action=order'), {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(buildOrderPayload(type, desc, details))
    });
    const j = await r.json();
    if(j.success){ alert('Order sent!'); hideModal('labOrder'); document.getElementById('mo-lab-desc').value=''; document.getElementById('mo-lab-details').value=''; loadOrders(dqCurrentVisitId); }
    else { alert('Error: '+j.message); }
  } catch(e){ alert('Failed: '+e.message); }
  finally{ btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Order'; }
}

// --- Prescription: search medications from items table ---
let rxMedications = [];

async function rxLoadMedications(){
  try{
    const r = await fetch(getAPIPath('api.php?endpoint=pharmacy/items&is_medication=1&limit=500'));
    const j = await r.json();
    if(j.success && j.data) rxMedications = j.data;
  } catch(e){ console.error('Failed to load medications:', e); }
}

function rxOpenModal(){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('Select a patient first.');
  document.getElementById('rx-patient-info').textContent = dqCurrentPatient.patientName;
  document.getElementById('rx-visit-info').textContent = dqCurrentPatient.visitNo;
  document.getElementById('rx-diagnosis').value = '';
  document.getElementById('rx-notes').value = '';
  document.getElementById('rx-drugs-tbody').innerHTML = '';
  rxAddRow(); // Add first row
  showModal('prescription');
}

function rxAddRow(){
  const tbody = document.getElementById('rx-drugs-tbody');
  const idx = tbody.children.length;
  const row = document.createElement('tr');
  row.style.borderTop = '1px solid var(--gray-100)';
  row.innerHTML = `
    <td style="padding:6px;">
      <input type="text" id="rx-drug-${idx}" placeholder="Search drug..." list="rx-med-list" oninput="rxSearchDrug(this)" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;" data-item-id="">
    </td>
    <td style="padding:6px;">
      <input type="text" id="rx-dosage-${idx}" placeholder="e.g. 500mg" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
    </td>
    <td style="padding:6px;">
      <select id="rx-freq-${idx}" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;">
        <option value="OD">OD (Once daily)</option>
        <option value="BD">BD (Twice daily)</option>
        <option value="TDS">TDS (3x daily)</option>
        <option value="QDS">QDS (4x daily)</option>
        <option value="STAT">STAT</option>
        <option value="PRN">PRN (As needed)</option>
        <option value="Nocte">Nocte (At night)</option>
      </select>
    </td>
    <td style="padding:6px;">
      <input type="text" id="rx-dur-${idx}" placeholder="7 days" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
    </td>
    <td style="padding:6px;">
      <input type="number" id="rx-qty-${idx}" placeholder="Qty" min="1" style="width:60px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
    </td>
    <td style="padding:6px;text-align:center;">
      <button onclick="this.closest('tr').remove()" type="button" style="border:none;background:none;color:var(--danger-color);cursor:pointer;font-size:14px;" title="Remove">
        <i class="fas fa-trash"></i>
      </button>
    </td>
  `;
  tbody.appendChild(row);
}

function rxSearchDrug(input){
  const val = input.value.toLowerCase();
  const datalist = document.getElementById('rx-med-list');
  if(!datalist){
    const dl = document.createElement('datalist');
    dl.id = 'rx-med-list';
    document.body.appendChild(dl);
  }
  const dl = document.getElementById('rx-med-list');
  if(val.length < 2){ dl.innerHTML = ''; return; }
  const matches = rxMedications.filter(m => m.name.toLowerCase().includes(val) || (m.generic_name && m.generic_name.toLowerCase().includes(val)));
  dl.innerHTML = matches.slice(0,20).map(m => `<option value="${dqEsc(m.name)}" data-id="${m.id}">${dqEsc(m.generic_name||'')}</option>`).join('');
  // Try to match item_id
  const exact = rxMedications.find(m => m.name.toLowerCase() === val);
  if(exact) input.setAttribute('data-item-id', exact.id);
  else input.setAttribute('data-item-id', '');
}

function rxCollectDrugs(){
  const tbody = document.getElementById('rx-drugs-tbody');
  const rows = tbody.querySelectorAll('tr');
  const drugs = [];
  rows.forEach((row, idx) => {
    const drugInput = document.getElementById('rx-drug-'+idx);
    if(!drugInput) return;
    const name = drugInput.value.trim();
    if(!name) return;
    const itemId = drugInput.getAttribute('data-item-id') || null;
    drugs.push({
      item_id: itemId ? parseInt(itemId) : null,
      drug_name: name,
      dosage: document.getElementById('rx-dosage-'+idx)?.value || '',
      frequency: document.getElementById('rx-freq-'+idx)?.value || '',
      duration: document.getElementById('rx-dur-'+idx)?.value || '',
      quantity: parseFloat(document.getElementById('rx-qty-'+idx)?.value) || 0
    });
  });
  return drugs;
}

async function rxSubmitPrescription(andPrint){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  const drugs = rxCollectDrugs();
  if(!drugs.length) return alert('Please add at least one drug.');
  
  const destination = document.querySelector('input[name="rx-destination"]:checked')?.value || 'hospital';
  const diagnosis = document.getElementById('rx-diagnosis').value.trim();
  const notes = document.getElementById('rx-notes').value.trim();
  
  try{
    const r = await fetch(getAPIPath('api.php?endpoint=pharmacy/prescriptions'), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        patient_id: dqCurrentPatient.patientId,
        visit_id: dqCurrentVisitId,
        doctor_id: loggedUserId,
        diagnosis: diagnosis,
        notes: notes,
        destination: destination,
        items: drugs
      })
    });
    const j = await r.json();
    if(j.success){
      hideModal('prescription');
      if(andPrint && j.data?.id){
        rxPrintPrescription(j.data.id);
      }
      alert('✓ Prescription submitted' + (destination === 'hospital' ? ' to Hospital Pharmacy!' : ' (External). Print ready.'));
      loadOrders(dqCurrentVisitId);
    } else {
      alert('Error: ' + j.message);
    }
  } catch(e){
    alert('Failed: ' + e.message);
  }
}

function rxSubmitAndPrint(){
  rxSubmitPrescription(true);
}

function rxPrintPrescription(prescriptionId){
  const printWin = window.open('', '_blank', 'width=800,height=600');
  printWin.document.write(`<html><head><title>Prescription</title><style>
    body{font-family:Arial,sans-serif;padding:30px;font-size:14px;}
    h2{margin:0;font-size:20px;} .hospital{text-align:center;margin-bottom:20px;border-bottom:2px solid #333;padding-bottom:10px;}
    table{width:100%;border-collapse:collapse;margin-top:15px;} th,td{border:1px solid #ddd;padding:8px;text-align:left;font-size:13px;}
    th{background:#f5f5f5;font-weight:600;} .footer{margin-top:30px;display:flex;justify-content:space-between;}
    @media print{button{display:none;}}
  </style></head><body>
    <div class="hospital"><h2>Hospital Prescription</h2><p>Date: ${new Date().toLocaleDateString()}</p></div>
    <p><strong>Patient:</strong> ${dqEsc(dqCurrentPatient.patientName)} | <strong>Visit:</strong> ${dqEsc(dqCurrentPatient.visitNo)}</p>
    <p><strong>Diagnosis:</strong> ${dqEsc(document.getElementById('rx-diagnosis').value||'—')}</p>
    <table><thead><tr><th>#</th><th>Drug</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Qty</th></tr></thead><tbody>`);
  
  const drugs = rxCollectDrugs();
  drugs.forEach((d, i) => {
    printWin.document.write(`<tr><td>${i+1}</td><td>${dqEsc(d.drug_name)}</td><td>${dqEsc(d.dosage)}</td><td>${dqEsc(d.frequency)}</td><td>${dqEsc(d.duration)}</td><td>${d.quantity||'—'}</td></tr>`);
  });
  
  printWin.document.write(`</tbody></table>
    <p style="margin-top:15px;"><strong>Notes:</strong> ${dqEsc(document.getElementById('rx-notes').value||'—')}</p>
    <div class="footer"><div><p>___________________</p><p>Doctor Signature</p></div><div><p>___________________</p><p>Pharmacist</p></div></div>
    <br><button onclick="window.print()">Print</button>
  </body></html>`);
  printWin.document.close();
}

// ============================================================
// 5. REFERRAL
// ============================================================
async function submitReferral(){
  const dept = document.getElementById('mo-ref-dept').value;
  const notes = document.getElementById('mo-ref-notes').value.trim();
  if(!dept) return alert('Please select a department.');

  // Ensure consultation exists first
  const cid = await ensureConsultation();
  if (!cid) return alert('Could not create consultation. Please save a draft first.');

  const btn = document.getElementById('btn-mo-ref');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
  try{
    // First create a medical order of type referral
    const r1 = await fetch(getAPIPath(consultAPI+'&action=order'), {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(buildOrderPayload('referral', 'Referred to department #'+dept, notes))
    });
    const j1 = await r1.json();

    // Then send to department via visits endpoint
    const r2 = await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId), {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'send_to_department', department_id: parseInt(dept), notes: notes, user_id: loggedUserId})
    });
    const j2 = await r2.json();

    if(j2.success){
      alert('Patient referred successfully!');
      hideModal('referral');
      dqCurrentVisitId = null; dqCurrentPatient = null;
      document.getElementById('dq-consult-panel').innerHTML = `
        <div style="text-align:center;padding:30px 20px;">
          <div style="font-size:48px;color:var(--success-color);margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
          <h4 style="font-size:16px;font-weight:600;color:var(--gray-600);">Patient Referred</h4>
          <p style="font-size:13px;color:var(--gray-400);margin-top:4px;">Patient has been sent to the selected department.</p>
        </div>`;
      document.getElementById('dq-actions').style.display = 'none';
      dqLoad();
    } else { alert('Error: '+(j2.message||'Failed to send')); }
  } catch(e){ alert('Failed: '+e.message); }
  finally{ btn.disabled=false; btn.innerHTML='<i class="fas fa-share"></i> Send Referral'; }
}

// ============================================================
// 6. ADMISSION
// ============================================================
// --- ADMISSION REQUEST (simple - no bed assignment here) ---
async function admitSubmit(){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  const diagnosis = document.getElementById('admit-diagnosis').value.trim();
  if(!diagnosis) return alert('Please enter admission reason.');
  
  const admitType = document.getElementById('admit-type').value;
  const wardPref = document.getElementById('admit-ward-pref').value;
  
  try{
    const r = await fetch(getAPIPath('api.php?endpoint=ward/ward-management&action=request_admission'), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        patient_id: dqCurrentPatient.patientId,
        visit_id: dqCurrentVisitId,
        diagnosis: diagnosis,
        admission_type: admitType,
        ward_preference: wardPref,
        requested_by: loggedUserId
      })
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Admission request sent! Ward staff will assign a bed.');
      hideModal('admission');
      dqLoad();
    } else {
      alert('Error: '+j.message);
    }
  } catch(e){ alert('Failed: '+e.message); }
}

// ============================================================
// 7. LOAD ORDERS FOR CURRENT VISIT
// ============================================================
async function loadOrders(visitId){
  const container = document.getElementById('dq-order-list');
  container.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading orders...';
  try{
    // Get from consultation API
    const r = await fetch(getAPIPath(consultAPI+'&visit_id='+visitId));
    const j = await r.json();
    if(j.success && j.data && j.data.orders && j.data.orders.length){
      const typeIcons = {lab:'flask', radiology:'x-ray', medication:'prescription', referral:'share', procedure:'procedures'};
      container.innerHTML = j.data.orders.map(o => `
        <div style="padding:8px 10px;border:1px solid var(--gray-100);border-radius:6px;margin-bottom:6px;background:var(--gray-50);">
          <div style="display:flex;align-items:center;gap:6px;">
            <i class="fas fa-${typeIcons[o.order_type]||'file'}" style="color:var(--primary-color);width:16px;"></i>
            <span style="font-weight:600;font-size:13px;">${dqEsc(o.description)}</span>
            <span style="margin-left:auto;font-size:11px;padding:2px 8px;border-radius:12px;background:${o.status==='completed'?'#d1fae5':o.status==='in_progress'?'#dbeafe':'#fef3c7'};color:${o.status==='completed'?'#065f46':o.status==='in_progress'?'#1e40af':'#92400e'};">
              ${o.status||'ordered'}
            </span>
          </div>
          ${o.details ? '<div style="font-size:12px;color:var(--gray-500);margin-top:3px;padding-left:22px;">'+dqEsc(o.details)+'</div>' : ''}
        </div>
      `).join('');
    } else {
      container.innerHTML = '<p style="color:var(--gray-400);font-size:13px;">No orders yet. Use the buttons above to place orders.</p>';
    }
  } catch(e){
    container.innerHTML = '<p style="color:var(--gray-400);font-size:13px;">Could not load orders.</p>';
  }
}

// ============================================================
// 8. MARK DONE (simple discharge without admit)
// ============================================================
async function dqMarkDone(){
  if(!dqCurrentVisitId) return;
  if(!confirm('Mark this patient as done?')) return;
  try{
    const r = await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId), {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({consultation_status:'done', status:'completed'})
    });
    const j = await r.json();
    if(j.success){
      dqCurrentVisitId = null; dqCurrentPatient = null;
      document.getElementById('dq-consult-panel').innerHTML = `
        <div style="text-align:center;padding:30px 20px;">
          <div style="font-size:48px;color:var(--success-color);margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
          <h4 style="font-size:16px;font-weight:600;color:var(--gray-600);">Patient Completed</h4>
          <p style="font-size:13px;color:var(--gray-400);">Click Call Next for the next patient.</p>
        </div>`;
      document.getElementById('dq-actions').style.display = 'none';
      document.getElementById('dq-consult-badge').textContent = 'No patient';
      dqLoad();
    } else { alert('Error: '+j.message); }
  } catch(e){ alert('Failed: '+e.message); }
}

// ============================================================
// 9. HIDE FORM MODAL
// ============================================================
function hideFormModal(){
  document.getElementById('modal-departmentForm').style.display = 'none';
  document.getElementById('mf-form-select').value = '';
  document.getElementById('mf-form-fields').innerHTML = '';
  document.getElementById('mf-form-fields').style.display = 'none';
}

// ============================================================
// 10. LOAD DEPARTMENT FORMS FOR MODAL
// ============================================================
async function mfLoadDepartmentForms(){
  if(!deptId) return;
  try{
    const r = await fetch(getAPIPath('api.php?endpoint=departments/department-forms&action=templates&department_id='+deptId));
    const j = await r.json();
    if(j.success && j.data && j.data.length > 0){
      const sel = document.getElementById('mf-form-select');
      sel.innerHTML = '<option value="">— Select Form —</option>';
      j.data.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.id;
        opt.textContent = f.name;
        sel.appendChild(opt);
      });
    }
  } catch(e){
    console.error('Failed to load department forms:', e);
  }
}

// ============================================================
// 11. LOAD FORM FIELDS FOR MODAL
// ============================================================
async function mfLoadFormFields(){
  const templateId = document.getElementById('mf-form-select').value;
  const fieldsDiv = document.getElementById('mf-form-fields');
  
  if(!templateId){
    fieldsDiv.style.display = 'none';
    return;
  }

  try{
    const r = await fetch(getAPIPath('api.php?endpoint=departments/department-forms&action=templates&id='+templateId));
    const j = await r.json();
    
    if(j.success && j.data){
      const fields = j.data.fields || [];
      mfRenderFormFields(fields);
      fieldsDiv.style.display = 'block';
    }
  } catch(e){
    console.error('Failed to load form fields:', e);
    alert('Error loading form fields');
  }
}

// ============================================================
// 12. RENDER FORM FIELDS
// ============================================================
function mfRenderFormFields(fields){
  const fieldsDiv = document.getElementById('mf-form-fields');
  let html = '';
  
  fields.forEach((f, idx) => {
    const fieldId = 'mf-form-field-'+idx;
    const required = f.is_required ? ' <span style="color:var(--danger-color);">*</span>' : '';
    const baseStyle = 'width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:4px;font-size:13px;';
    
    if(f.field_type === 'section_header'){
      html += `<div style="font-weight:600;color:var(--gray-700);margin-top:12px;margin-bottom:8px;border-bottom:2px solid var(--primary-color);padding-bottom:4px;">${dqEsc(f.field_label)}</div>`;
    } else if(f.field_type === 'info_text'){
      html += `<div style="background:var(--blue-50);border-left:4px solid var(--primary-color);padding:8px;border-radius:4px;margin-bottom:8px;font-size:12px;color:var(--gray-600);">${dqEsc(f.field_label)}</div>`;
    } else {
      html += `<div style="margin-bottom:10px;">`;
      if(f.field_type !== 'checkbox' && f.field_type !== 'radio'){
        html += `<label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">${dqEsc(f.field_label)}${required}</label>`;
      }
      
      if(f.field_type === 'text'){
        html += `<input type="text" id="${fieldId}" placeholder="${dqEsc(f.placeholder||'')}" data-key="${dqEsc(f.field_key)}" style="${baseStyle}">`;
      } else if(f.field_type === 'textarea'){
        html += `<textarea id="${fieldId}" placeholder="${dqEsc(f.placeholder||'')}" rows="3" data-key="${dqEsc(f.field_key)}" style="${baseStyle}resize:vertical;"></textarea>`;
      } else if(f.field_type === 'number'){
        html += `<input type="number" id="${fieldId}" placeholder="${dqEsc(f.placeholder||'')}" data-key="${dqEsc(f.field_key)}" step="0.01" style="${baseStyle}">`;
      } else if(f.field_type === 'date'){
        html += `<input type="date" id="${fieldId}" data-key="${dqEsc(f.field_key)}" style="${baseStyle}">`;
      } else if(f.field_type === 'datetime'){
        html += `<input type="datetime-local" id="${fieldId}" data-key="${dqEsc(f.field_key)}" style="${baseStyle}">`;
      } else if(f.field_type === 'select'){
        let opts = '<option value="">Select...</option>';
        if(f.field_options){
          try {
            const optsArr = typeof f.field_options === 'string' ? JSON.parse(f.field_options) : f.field_options;
            optsArr.forEach(o => {
              opts += `<option value="${dqEsc(o)}">${dqEsc(o)}</option>`;
            });
          } catch(e){}
        }
        html += `<select id="${fieldId}" data-key="${dqEsc(f.field_key)}" style="${baseStyle}">${opts}</select>`;
      } else if(f.field_type === 'multi_select'){
        let opts = '';
        if(f.field_options){
          try {
            const optsArr = typeof f.field_options === 'string' ? JSON.parse(f.field_options) : f.field_options;
            optsArr.forEach(o => {
              opts += `<option value="${dqEsc(o)}">${dqEsc(o)}</option>`;
            });
          } catch(e){}
        }
        html += `<select id="${fieldId}" data-key="${dqEsc(f.field_key)}" multiple style="${baseStyle}height:80px;">${opts}</select>`;
      } else if(f.field_type === 'checkbox'){
        html += `<label style="display:flex;align-items:center;font-size:13px;color:var(--gray-700);cursor:pointer;"><input type="checkbox" id="${fieldId}" data-key="${dqEsc(f.field_key)}" style="margin-right:8px;cursor:pointer;"> ${dqEsc(f.field_label)}${required}</label>`;
      } else if(f.field_type === 'radio'){
        html += `<div style="display:flex;flex-direction:column;gap:6px;">`;
        if(f.field_options){
          try {
            const optsArr = typeof f.field_options === 'string' ? JSON.parse(f.field_options) : f.field_options;
            optsArr.forEach((o, i) => {
              const radioId = fieldId + '-' + i;
              html += `<label style="display:flex;align-items:center;font-size:13px;color:var(--gray-700);cursor:pointer;"><input type="radio" id="${radioId}" name="${fieldId}" value="${dqEsc(o)}" data-key="${dqEsc(f.field_key)}" style="margin-right:8px;cursor:pointer;"> ${dqEsc(o)}</label>`;
            });
          } catch(e){}
        }
        html += `</div>`;
      }
      
      html += `</div>`;
    }
  });
  
  fieldsDiv.innerHTML = html;
}

// ============================================================
// 13. COLLECT FORM DATA
// ============================================================
function mfCollectFormData(){
  const templateId = document.getElementById('mf-form-select').value;
  if(!templateId) return null;
  
  const formData = {};
  const inputs = document.getElementById('mf-form-fields').querySelectorAll('[data-key]');
  
  inputs.forEach(inp => {
    const key = inp.getAttribute('data-key');
    let value = null;
    
    if(inp.type === 'checkbox'){
      value = inp.checked;
    } else if(inp.tagName === 'SELECT' && inp.multiple){
      value = Array.from(inp.selectedOptions).map(o => o.value);
    } else if(inp.name && inp.type === 'radio'){
      const checked = document.querySelector('input[name="'+inp.name+'"]:checked');
      if(checked) value = checked.value;
    } else {
      value = inp.value || null;
    }
    
    formData[key] = value;
  });
  
  return {
    template_id: templateId,
    data: formData
  };
}

// ============================================================
// 14. SAVE FORM & MARK AS NOW SERVING
// ============================================================
async function mfSaveForm(){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  
  const formPayload = mfCollectFormData();
  if(!formPayload || !formPayload.template_id) return alert('Please select a form.');
  
  try{
    // 1. Save form data
    const r = await fetch(getAPIPath('api.php?endpoint=departments/department-forms&action=submit'), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        visit_id: dqCurrentVisitId,
        patient_id: dqCurrentPatient.patientId,
        template_id: formPayload.template_id,
        department_id: deptId,
        filled_by: loggedUserId,
        form_data: formPayload.data,
        status: 'completed'
      })
    });
    const j = await r.json();
    if(j.success){
      // 2. Update visit consultation_status to now_serving
      await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId), {
        method:'PUT',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({consultation_status:'now_serving'})
      });
      
      // 3. Close modal
      hideFormModal();
      
      alert('✓ Form saved! Patient is now being served.');
      
      // 5. Reload queue to show updated status in table
      await dqLoad();
    } else {
      alert('Error saving form: ' + j.message);
    }
  } catch(e){
    alert('Failed to save form: ' + e.message);
    console.error('Form save error:', e);
  }
}

// ============================================================
// 15. LOAD PREVIOUSLY FILLED FORM DATA
// ============================================================
async function mfLoadPreviousFormData(){
  if(!dqCurrentVisitId) return;
  
  try{
    const r = await fetch(getAPIPath('api.php?endpoint=departments/department-forms&action=submitted&visit_id='+dqCurrentVisitId));
    const j = await r.json();
    
    if(j.success && j.data && j.data.length > 0){
      // Get the first submitted form for this visit
      const submittedForm = j.data[0];
      
      // Pre-select the form
      document.getElementById('mf-form-select').value = submittedForm.template_id;
      
      // Load and render the form fields
      await mfLoadFormFields();
      
      // Populate with saved data
      setTimeout(() => {
        if(submittedForm.form_data && typeof submittedForm.form_data === 'string'){
          try {
            var formData = JSON.parse(submittedForm.form_data);
          } catch(e) {
            formData = submittedForm.form_data;
          }
        } else {
          formData = submittedForm.form_data || {};
        }
        
        // Fill in the saved values
        const inputs = document.getElementById('mf-form-fields').querySelectorAll('[data-key]');
        inputs.forEach(inp => {
          const key = inp.getAttribute('data-key');
          const value = formData[key];
          
          if(value !== undefined && value !== null){
            if(inp.type === 'checkbox'){
              inp.checked = Boolean(value);
            } else if(inp.tagName === 'SELECT' && inp.multiple){
              if(Array.isArray(value)){
                Array.from(inp.options).forEach(opt => {
                  opt.selected = value.includes(opt.value);
                });
              }
            } else if(inp.type === 'radio'){
              const radioInputs = document.querySelectorAll('input[name="'+inp.name+'"]');
              radioInputs.forEach(radio => {
                radio.checked = (radio.value === value);
              });
            } else {
              inp.value = value;
            }
          }
        });
      }, 100);
      
      // Show read-only message
      const fieldsDiv = document.getElementById('mf-form-fields');
      if(fieldsDiv && !fieldsDiv.querySelector('.form-submitted-notice')){
        const notice = document.createElement('div');
        notice.className = 'form-submitted-notice';
        notice.style.cssText = 'background:#dbeafe;border-left:4px solid #2563eb;padding:10px;border-radius:4px;margin-bottom:12px;font-size:12px;color:#1e40af;';
        notice.innerHTML = '<i class="fas fa-info-circle"></i> Form previously filled on this visit. Showing saved data.';
        fieldsDiv.parentNode.insertBefore(notice, fieldsDiv);
      }
    }
  } catch(e){
    console.error('Failed to load previous form data:', e);
  }
}

// ============================================================
// INIT — boot via API (like patients.php)
// ============================================================
bootDepartment();
</script>
<?php
require_once __DIR__ . '/includes/footer.php';
?>