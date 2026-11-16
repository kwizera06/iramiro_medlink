<style>.doc-a4-sheet{background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.06);font-family:'Times New Roman',serif;font-size:14px;line-height:1.8;max-width:794px;margin:0 auto;position:relative;box-sizing:border-box;}</style>
<!-- ====== EXTERNAL PATIENT TRANSFER FORM ====== -->
<div style="background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:600;margin:0 0 14px;"><i class="fas fa-user-injured" style="color:var(--primary-color);"></i> Select Patient & Visit <span style="font-weight:400;font-size:12px;color:var(--gray-500);">— form is prefilled from the patient record and visit</span></h3>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div style="position:relative;">
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Patient *</label>
      <input id="et-patient-search" type="text" placeholder="Type to search patient name, ID, phone..." oninput="etSearch()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      <input id="et-patient-id" type="hidden">
      <div id="et-patient-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:190px;overflow-y:auto;z-index:100;box-shadow:0 4px 14px rgba(0,0,0,0.12);margin-top:2px;"></div>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Visit</label>
      <select id="et-visit" onchange="etVisit()" disabled style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"><option value="">— select patient first —</option></select>
    </div>
  </div>
  <div style="margin-top:12px;"><button onclick="etOpenForm()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-edit"></i> Edit / Fill Details</button></div>
</div>

<!-- Form Modal -->
<div id="et-form-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:900px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:10px;">
      <h3 style="font-size:16px;font-weight:600;margin:0;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Fill Document Details</h3>
      <button onclick="etCloseForm()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-500);">&times;</button>
    </div>
    <div id="et-fields"></div>
    <button onclick="etCloseForm()" style="width:100%;margin-top:18px;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check"></i> Done</button>
  </div>
</div>

<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-ambulance" style="color:var(--primary-color);"></i> External Patient Transfer Form</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="etPrint()" style="padding:8px 16px;background:#059669;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print</button>
      <button onclick="etPDF()" style="padding:8px 16px;background:#1e40af;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-file-pdf"></i> PDF</button>
    </div>
  </div>
  <div id="et-preview" class="doc-a4-sheet" style="padding:35px 45px;min-height:960px;"></div>
</div>
