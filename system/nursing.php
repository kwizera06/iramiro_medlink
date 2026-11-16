<?php
// public/nursing.php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'patients';
// Prices are visible only to admins and cashiers/finance
$rs = $pdo->prepare("SELECT GROUP_CONCAT(r.slug) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?");
$rs->execute([$_SESSION['user_id'] ?? 0]);
if (!array_intersect(explode(',', (string)$rs->fetchColumn()), ['admin','administrator','accountant','cashier','finance']) && empty($_SESSION['user_data']['is_admin'])) echo '<style>.ns-price{display:none !important;}</style>';
?>

<style>
.ns-tab{padding:10px 20px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}
.ns-tab.active{background:var(--primary-color);color:white;}
</style>

<!-- Page Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">Admitted Care</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Record medications, injections, vitals and services for admitted patients</p>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:8px;margin-bottom:24px;">
  <button class="ns-tab <?php echo $subModule=='patients'?'active':'';?>" onclick="location.href='nursing.php?module=nursing&sub=patients'">
    <i class="fas fa-procedures"></i> Admitted Patients
  </button>
  <button class="ns-tab <?php echo $subModule=='schedule'?'active':'';?>" onclick="location.href='nursing.php?module=nursing&sub=schedule'">
    <i class="fas fa-calendar-alt"></i> Schedule / Timetable
  </button>
  <button class="ns-tab <?php echo $subModule=='records'?'active':'';?>" onclick="location.href='nursing.php?module=nursing&sub=records'">
    <i class="fas fa-history"></i> All Records
  </button>
</div>

<?php require __DIR__ . '/includes/ns_view.php'; ?>

<?php if ($subModule == 'patients'): ?>
<!-- ====== ADMITTED PATIENTS - Select patient to add records ====== -->
<div style="display:grid;grid-template-columns:1fr 1.3fr;gap:20px;">
  <!-- Left: Patient list -->
  <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--gray-200);">
      <h3 style="font-size:15px;font-weight:600;"><i class="fas fa-bed" style="color:#7c3aed;"></i> Admitted Patients</h3>
      <input type="text" id="ns-search" placeholder="Search..." oninput="nsFilter()" style="width:100%;padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;margin-top:8px;">
    </div>
    <div id="ns-patient-list" style="max-height:500px;overflow-y:auto;padding:10px;">
      <div style="padding:30px;text-align:center;color:var(--gray-400);font-size:12px;"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
  </div>

  <!-- Right: Add record for selected patient -->
  <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--gray-200);">
      <h3 style="font-size:15px;font-weight:600;"><i class="fas fa-plus-circle" style="color:#16a34a;"></i> Add Record</h3>
      <p style="font-size:12px;color:var(--gray-500);margin-top:2px;" id="ns-selected-info">Select a patient from the list</p>
    </div>
    <div id="ns-record-panel" style="padding:16px 20px;">
      <div style="text-align:center;padding:40px;color:var(--gray-300);">
        <i class="fas fa-hand-pointer" style="font-size:36px;display:block;margin-bottom:8px;"></i>
        <p style="font-size:13px;color:var(--gray-400);">Select a patient to add nursing records</p>
      </div>
    </div>
  </div>
</div>

<script>
const nsAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=ward/ward-management';
const nsRecAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=nursing';
const nsUserId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; ?>;
let nsCurrentPatient = null;

async function nsLoadPatients(){
  try{
    const r = await fetch(nsAPI+'&action=active_admissions');
    const j = await r.json();
    const container = document.getElementById('ns-patient-list');
    if(j.success && j.data && j.data.length){
      container.innerHTML = j.data.map(a => `
        <div class="ns-pt-row" onclick="nsSelectPatient(${a.patient_id},${a.visit_id},${a.id},'${nEsc(a.patient_name).replace(/'/g,"\\'")}','${nEsc(a.ward_name||'')}','${nEsc(a.room_number||'')}','${nEsc(a.bed_number||'')}')" style="padding:10px 12px;border:1px solid var(--gray-200);border-radius:6px;margin-bottom:6px;cursor:pointer;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='var(--gray-200)'">
          <div style="font-weight:600;font-size:13px;">${nEsc(a.patient_name)}</div>
          <div style="font-size:11px;color:var(--gray-500);">${nEsc(a.ward_name)} / ${nEsc(a.room_number)} / Bed ${nEsc(a.bed_number)}</div>
          <a href="procedures.php?module=nurse&sub=procedures&visit_id=${a.visit_id}&patient_id=${a.patient_id}&name=${encodeURIComponent(a.patient_name)}" onclick="event.stopPropagation()" style="display:inline-block;margin-top:6px;font-size:11px;color:var(--primary-color);"><i class="fas fa-pills"></i> Meds / Consumables / Procedures</a>
          <button type="button" onclick="event.stopPropagation();nsViewVisit(${a.visit_id},'${nEsc(a.patient_name).replace(/'/g,"\'")}','${nEsc(a.ward_name||'')} / ${nEsc(a.room_number||'')} / Bed ${nEsc(a.bed_number||'')}')" style="margin:6px 0 0 8px;padding:2px 8px;font-size:11px;border:1px solid var(--gray-200);border-radius:4px;background:white;cursor:pointer;"><i class="fas fa-eye"></i> View</button>
        </div>`).join('');
    } else {
      container.innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);font-size:12px;">No admitted patients.</div>';
    }
  } catch(e){}
}

function nsFilter(){
  const term = document.getElementById('ns-search').value.toLowerCase();
  document.querySelectorAll('.ns-pt-row').forEach(r => { r.style.display = r.textContent.toLowerCase().includes(term)?'':'none'; });
}

async function nsSelectPatient(patientId, visitId, assignId, name, ward, room, bed){
  nsCurrentPatient = {patientId, visitId, assignId, name, ward, room, bed};
  document.getElementById('ns-selected-info').innerHTML = '<strong>'+nEsc(name)+'</strong> | '+nEsc(ward)+' / '+nEsc(room)+' / Bed '+nEsc(bed);
  
  document.getElementById('ns-record-panel').innerHTML = `
    <!-- Record type tabs -->
    <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
      <button onclick="nsShowForm('medication')" class="ns-type-btn" id="ns-btn-medication" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;background:#f0fdf4;"><i class="fas fa-pills"></i> Medication</button>
      <button onclick="nsShowForm('procedure')" class="ns-type-btn" id="ns-btn-procedure" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;background:white;"><i class="fas fa-syringe"></i> Procedure</button>
      <button onclick="nsShowForm('vitals')" class="ns-type-btn" id="ns-btn-vitals" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;background:white;"><i class="fas fa-heartbeat"></i> Vital</button>
      <button onclick="nsShowForm('sample')" class="ns-type-btn" id="ns-btn-sample" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;background:white;"><i class="fas fa-vial"></i> Sample</button>
      <button onclick="nsShowForm('note')" class="ns-type-btn" id="ns-btn-note" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;background:white;"><i class="fas fa-sticky-note"></i> Notes</button>
    </div>

    <!-- Medication form -->
    <div id="ns-form-medication" style="margin-bottom:12px;">
      <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Medication</label>
      <div id="nsp-med-box" onclick="nsPTog('med')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:40px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;font-size:13px;"><span id="nsp-med-label" style="color:var(--gray-400);">Select medication...</span><i class="fas fa-caret-down" style="color:var(--gray-500);"></i></div>
      <div id="nsp-med-dd" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
        <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="nsp-med-q" placeholder="Search..." autocomplete="off" oninput="nsPSearch('med')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;outline:none;"></div>
        <div id="nsp-med-list" style="max-height:220px;overflow-y:auto;"></div>
      </div>
      <input type="hidden" id="ns-med-search" value="">
      <input type="hidden" id="ns-med-item" value="">
      <input type="hidden" id="ns-med-price" value="0">
    </div>
    <div id="ns-form-sample" style="display:none;margin-bottom:12px;">
      <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Pending lab order (sample to collect)</label>
      <div id="nsp-ord-box" onclick="nsPTog('ord')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:40px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;font-size:13px;"><span id="nsp-ord-label" style="color:var(--gray-400);">Select lab order...</span><i class="fas fa-caret-down" style="color:var(--gray-500);"></i></div>
      <div id="nsp-ord-dd" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
        <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="nsp-ord-q" placeholder="Search..." autocomplete="off" oninput="nsPSearch('ord')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;outline:none;"></div>
        <div id="nsp-ord-list" style="max-height:220px;overflow-y:auto;"></div>
      </div>
      <input type="hidden" id="ns-sample-order" value="">
      <input type="hidden" id="ns-sample-order-text" value="">
      <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Then pick the sample-collection service below: it is billed as a separate fee.</div>
    </div>
    <div id="ns-form-service" style="display:none;margin-bottom:12px;">
      <label id="ns-svc-label" style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Procedure</label>
      <div id="nsp-svc-box" onclick="nsPTog('svc')" style="display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:40px;padding:6px 12px;border:1px solid #93c5d8;border-radius:6px;background:white;cursor:pointer;font-size:13px;"><span id="nsp-svc-label" style="color:var(--gray-400);">Select procedure...</span><i class="fas fa-caret-down" style="color:var(--gray-500);"></i></div>
      <div id="nsp-svc-dd" style="display:none;border:1px solid var(--gray-200);border-radius:6px;margin-top:4px;background:white;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
        <div style="padding:8px;border-bottom:1px solid var(--gray-100);"><input type="text" id="nsp-svc-q" placeholder="Search..." autocomplete="off" oninput="nsPSearch('svc')" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;outline:none;"></div>
        <div id="nsp-svc-list" style="max-height:220px;overflow-y:auto;"></div>
      </div>
      <input type="hidden" id="ns-svc-search" value="">
      <input type="hidden" id="ns-svc-item" value="">
      <input type="hidden" id="ns-svc-price" value="0">
    </div>
    <div id="ns-form-vitals" style="display:none;margin-bottom:12px;">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">BP</label><input type="text" id="ns-v-bp" placeholder="120/80" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Temp</label><input type="text" id="ns-v-temp" placeholder="36.5" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Pulse</label><input type="text" id="ns-v-pulse" placeholder="72" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">SpO2</label><input type="text" id="ns-v-spo2" placeholder="98%" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">RR</label><input type="text" id="ns-v-rr" placeholder="16" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Weight</label><input type="text" id="ns-v-wt" placeholder="70kg" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
      </div>
    </div>

    <div id="ns-dq-row" style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
      <div><label style="font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:4px;display:block;">Dosage/Qty</label><input type="text" id="ns-dosage" placeholder="e.g. 500mg x2" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></div>
      <div><label style="font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:4px;display:block;">Quantity</label><input type="number" id="ns-qty" value="1" min="1" onchange="nsUpdatePrice()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></div>
    </div>
    <div style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:4px;display:block;">Notes</label>
      <textarea id="ns-notes" rows="2" placeholder="Additional notes..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;resize:vertical;"></textarea>
    </div>
    <div id="ns-charge-box" class="ns-price" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:8px 12px;margin-bottom:12px;display:flex;justify-content:space-between;font-size:13px;">
      <span style="color:#065f46;font-weight:600;">Charge:</span>
      <span style="font-weight:700;color:#065f46;" id="ns-charge">0 RWF</span>
    </div>
    <button onclick="nsSaveRecord()" style="width:100%;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
      <i class="fas fa-save"></i> <span id="ns-save-label">Save & Create Invoice</span>
    </button>

    <!-- Recent records for this patient -->
    <div style="margin-top:16px;border-top:1px solid var(--gray-200);padding-top:12px;">
      <h4 style="font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:8px;">Recent Records</h4>
      <div id="ns-recent" style="max-height:200px;overflow-y:auto;font-size:12px;color:var(--gray-500);">Loading...</div>
    </div>
  `;
  nsShowForm('medication');
  nsLoadRecent();
}

let nsRecordType = 'medication';
function nsShowForm(type){
  nsRecordType = type==='procedure' ? 'service' : type; // a procedure is saved as a 'service' record
  document.querySelectorAll('.ns-type-btn').forEach(b => { b.style.background='white'; b.style.fontWeight='400'; });
  const btn = document.getElementById('ns-btn-'+type);
  if(btn){ btn.style.background='#dbeafe'; btn.style.fontWeight='600'; }
  document.getElementById('ns-form-medication').style.display = type==='medication'?'':'none';
  document.getElementById('ns-form-service').style.display = (type==='procedure'||type==='sample')?'':'none';
  document.getElementById('ns-form-sample').style.display = type==='sample'?'':'none';
  document.getElementById('ns-svc-label').textContent = type==='sample' ? 'Sample collection service' : 'Procedure';
  document.getElementById('ns-form-vitals').style.display = type==='vitals'?'':'none';
  document.getElementById('ns-dq-row').style.display = type==='vitals'||type==='sample'?'none':'grid';
  document.getElementById('ns-charge-box').style.display = type==='vitals'||type==='note'?'none':'';
  document.getElementById('ns-dq-row').style.display = type==='vitals'||type==='sample'||type==='note'?'none':'grid';
  document.getElementById('ns-notes').rows = type==='note'?6:2;
  document.getElementById('ns-notes').placeholder = type==='note'?'Write a note about the patient...':'Additional notes...';
  document.getElementById('ns-save-label').textContent = type==='note'?'Save Note':'Save & Create Invoice';
  if(type==='sample') nsLoadSampleOrders();
  nsUpdatePrice();
}

function nsUpdatePrice(){
  let price = 0;
  const qty = parseInt(document.getElementById('ns-qty')?.value)||1;
  if(nsRecordType==='medication'||nsRecordType==='injection'){
    price = parseFloat(document.getElementById('ns-med-price')?.value)||0;
  } else if(nsRecordType==='service'||nsRecordType==='sample'){
    price = parseFloat(document.getElementById('ns-svc-price')?.value)||0;
  }
  document.getElementById('ns-charge').textContent = (price*qty).toLocaleString()+' RWF';
}

async function nsSaveRecord(){
  if(!nsCurrentPatient) return alert('Select a patient first.');
  
  let description = '', itemId = null, serviceId = null, unitPrice = 0;
  const qty = parseInt(document.getElementById('ns-qty').value)||1;
  const dosage = document.getElementById('ns-dosage').value;
  const notes = document.getElementById('ns-notes').value;

  if(nsRecordType==='medication'||nsRecordType==='injection'){
    const idVal = document.getElementById('ns-med-item').value;
    if(!idVal) return alert('Select a medication.');
    itemId = parseInt(idVal);
    description = document.getElementById('ns-med-search').value;
    unitPrice = parseFloat(document.getElementById('ns-med-price').value)||0;
  } else if(nsRecordType==='service'||nsRecordType==='sample'){
    const idVal = document.getElementById('ns-svc-item').value;
    if(!idVal) return alert('Select a service.');
    serviceId = parseInt(idVal);
    description = document.getElementById('ns-svc-search').value;
    unitPrice = parseFloat(document.getElementById('ns-svc-price').value)||0;
    if(nsRecordType==='sample'){
      const ord = document.getElementById('ns-sample-order');
      if(!ord.value) return alert('Select the lab order the sample is for.');
      description += ' — '+document.getElementById('ns-sample-order-text').value;
    }
  } else if(nsRecordType==='vitals'){
    const bp=document.getElementById('ns-v-bp').value, temp=document.getElementById('ns-v-temp').value, pulse=document.getElementById('ns-v-pulse').value;
    description = 'Vitals: BP:'+bp+' T:'+temp+' P:'+pulse+' SpO2:'+document.getElementById('ns-v-spo2').value+' RR:'+document.getElementById('ns-v-rr').value+' Wt:'+document.getElementById('ns-v-wt').value;
    unitPrice = 0;
  } else {
    if(!notes.trim()) return alert('Write the note first.');
    description = notes.trim().length>250 ? notes.trim().slice(0,247)+'...' : notes.trim();
    unitPrice = 0;
  }

  // Sample: mark the lab order collected first (existing lab collect_sample), so the fee is never billed for a sample that was not recorded
  const isSample = nsRecordType==='sample';
  if(isSample){
    try{
      const c = await (await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=lab/lab&action=collect_sample', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({order_id: parseInt(document.getElementById('ns-sample-order').value)})
      })).json();
      if(!c.success) return alert('Sample not recorded, nothing billed: '+c.message);
    } catch(e){ return alert('Sample not recorded, nothing billed: '+e.message); }
  }
  const sampleNote = isSample ? '\nThe sample WAS marked collected, but the fee was not billed. Add it under Procedure.' : '';

  let doseReason = '';
  try{
   for(;;){
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=nursing_record', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        dose_reason: doseReason,
        patient_id: nsCurrentPatient.patientId,
        visit_id: nsCurrentPatient.visitId,
        bed_assignment_id: nsCurrentPatient.assignId,
        record_type: isSample ? 'service' : nsRecordType,
        item_id: itemId,
        service_id: serviceId,
        description: description,
        dosage: dosage,
        quantity: qty,
        unit_price: unitPrice,
        notes: notes,
        administered_by: nsUserId
      })
    });
    const j = await r.json();
    if(!j.success && (j.message||'').startsWith('DOSE_MISMATCH')){
      // Given dose differs from the doctor's prescription: a reason is required, the doctor is alerted
      doseReason = (prompt(j.message.replace('DOSE_MISMATCH: ','')+'\n\nReason for the different dose:')||'').trim();
      if(!doseReason) return;
      continue;
    }
    if(j.success){
      alert('Record saved.'+(j.data?.invoice_no?' Invoice: '+j.data.invoice_no:''));
      document.getElementById('ns-notes').value='';
      document.getElementById('ns-dosage').value='';
      document.getElementById('ns-qty').value='1';
      nsLoadRecent();
      if(isSample) nsLoadSampleOrders();
    } else alert('Error: '+j.message+sampleNote);
    break;
   }
  } catch(e){ alert('Failed: '+e.message+sampleNote); }
}

// Lab orders of this visit still waiting for a sample (picker source)
async function nsLoadSampleOrders(){
  document.getElementById('ns-sample-order').value=''; document.getElementById('ns-sample-order-text').value='';
  const lab=document.getElementById('nsp-ord-label'); lab.textContent='Select lab order...'; lab.style.color='var(--gray-400)';
  nsP.ord.rows=null;
  if(!nsCurrentPatient) return;
  try{
    const j = await (await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=lab_orders&type=lab&status=ordered&visit_id='+nsCurrentPatient.visitId)).json();
    nsP.ord.rows = (j.success ? (j.data||[]) : []).map(o=>({id:o.id,name:o.description}));
  } catch(e){ nsP.ord.rows=[]; }
}

async function nsLoadRecent(){
  if(!nsCurrentPatient) return;
  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=nursing_records&visit_id='+nsCurrentPatient.visitId);
    const j = await r.json();
    const div = document.getElementById('ns-recent');
    if(j.success && j.data && j.data.length){
      div.innerHTML = j.data.slice(0,15).map(rec => {
        const icon = '<i class="fas '+({medication:'fa-pills',injection:'fa-syringe',vitals:'fa-heartbeat',service:'fa-syringe',note:'fa-sticky-note'}[rec.record_type]||'fa-notes-medical')+'" style="width:14px;color:var(--gray-500);"></i>';
        return `<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;">
          <span>${icon} ${nEsc(rec.description)}${rec.nurse_name?' <em style="color:var(--gray-400);">— '+nEsc(rec.nurse_name)+'</em>':''} ${rec.invoice_id?payBadge(rec.pay_status, rec.pay_balance):''}</span>
          <span style="color:var(--gray-400);font-size:10px;">${new Date(rec.administered_at||rec.created_at).toLocaleString()}</span>
        </div>`;
      }).join('');
    } else div.innerHTML = '<div style="color:var(--gray-400);">No records yet.</div>';
  } catch(e){ document.getElementById('ns-recent').innerHTML='Error loading.'; }
}

// --- Select with search inside (same as the consultation order boxes), one component for every picker ---
const _nsApi = () => (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php');
const nsP = {
  med: {rows:[], fetch: async q => { const j=await (await fetch(_nsApi()+'?endpoint=pharmacy/items&is_medication=1&search='+encodeURIComponent(q)+'&limit=50')).json(); return (j.success&&j.data||[]).map(m=>({id:m.id,name:m.name,price:parseFloat(m.selling_price||0)})); },
        pick: r => { nsSet('ns-med-item',r.id); nsSet('ns-med-search',r.name); nsSet('ns-med-price',r.price); } },
  svc: {rows:[], fetch: async q => { const j=await (await fetch(_nsApi()+'?endpoint=patients/billing&action=services&category=nursing,procedure,other&search='+encodeURIComponent(q))).json(); return (j.success&&j.data||[]).map(s=>({id:s.id,name:s.name,price:parseFloat(s.unit_price||0)})); },
        pick: r => { nsSet('ns-svc-item',r.id); nsSet('ns-svc-search',r.name); nsSet('ns-svc-price',r.price); } },
  ord: {rows:null, fetch: async q => (nsP.ord.rows||[]).filter(o=>o.name.toLowerCase().includes(q.toLowerCase())),
        pick: r => { nsSet('ns-sample-order',r.id); nsSet('ns-sample-order-text',r.name); } }
};
const nsSet = (id,v) => { document.getElementById(id).value = v; };
let nsPTimer;
function nsPTog(k){
  const dd=document.getElementById('nsp-'+k+'-dd'), open=dd.style.display!=='none';
  dd.style.display = open?'none':'block';
  if(!open){ const q=document.getElementById('nsp-'+k+'-q'); q.value=''; nsPRun(k); q.focus(); }
}
function nsPSearch(k){ clearTimeout(nsPTimer); nsPTimer=setTimeout(()=>nsPRun(k),250); }
async function nsPRun(k){
  const q=document.getElementById('nsp-'+k+'-q').value.trim(), list=document.getElementById('nsp-'+k+'-list');
  try{
    const rows = nsP[k].current = await nsP[k].fetch(q);
    if(document.getElementById('nsp-'+k+'-q').value.trim()!==q) return; // stale
    list.innerHTML = rows.length ? rows.map((r,i)=>`<div onclick="nsPPick('${k}',${i})" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--gray-100);font-size:12px;display:flex;justify-content:space-between;gap:8px;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''"><span>${nEsc(r.name)}</span>${r.price!=null?`<span style="color:var(--primary-color);font-weight:600;white-space:nowrap;">${r.price.toLocaleString()} RWF</span>`:''}</div>`).join('')
      : '<div style="padding:10px;color:var(--gray-400);font-size:12px;text-align:center;">'+(k==='ord'&&!(nsP.ord.rows||[]).length?'No lab orders waiting for a sample':'No results')+'</div>';
  }catch(e){ list.innerHTML=''; }
}
function nsPPick(k,i){
  const r=nsP[k].current[i]; if(!r) return;
  nsP[k].pick(r);
  const lab=document.getElementById('nsp-'+k+'-label'); lab.textContent=r.name; lab.style.color='var(--gray-800)';
  document.getElementById('nsp-'+k+'-dd').style.display='none';
  nsUpdatePrice();
}
// close a picker when clicking outside it
document.addEventListener('click',function(e){
  if(!e.target.isConnected) return;
  Object.keys(nsP).forEach(k=>{ const dd=document.getElementById('nsp-'+k+'-dd'); if(dd && !e.target.closest('#nsp-'+k+'-box') && !e.target.closest('#nsp-'+k+'-dd')) dd.style.display='none'; });
});

nsLoadPatients();
</script>

<?php elseif ($subModule == 'schedule'): ?>
<!-- ====== MEDICATION & PROCEDURE SCHEDULE TIMETABLE ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-calendar-alt" style="color:var(--primary-color);"></i> Medication & Procedure Schedule</h3>
    <div style="display:flex;gap:8px;align-items:center;">
      <input type="date" id="sched-date" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <button onclick="schedLoad()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:500;"><i class="fas fa-sync-alt"></i> Refresh</button>
      <button onclick="schedOpenAdd()" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:500;"><i class="fas fa-plus"></i> Add Schedule</button>
    </div>
  </div>

  <!-- Time slots header -->
  <div style="overflow-x:auto;">
    <table id="sched-table" style="width:100%;border-collapse:collapse;font-size:12px;min-width:900px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
          <th style="padding:10px;text-align:left;font-weight:600;position:sticky;left:0;background:var(--gray-50);min-width:160px;">Patient / Item</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">06:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">08:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">10:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">12:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">14:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">16:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">18:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">20:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">22:00</th>
          <th style="padding:10px;text-align:center;font-weight:600;width:60px;">00:00</th>
        </tr>
      </thead>
      <tbody id="sched-tbody">
        <tr><td colspan="11" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Schedule Modal -->
<div id="sched-add-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:500px;max-width:90vw;max-height:85vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-plus-circle" style="color:#16a34a;"></i> Add Schedule Entry</h3>
      <button onclick="document.getElementById('sched-add-modal').style.display='none'" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Patient</label>
      <div style="position:relative;">
        <input type="text" id="sched-patient-search" placeholder="Search admitted patient..." oninput="schedPatientSearch(this.value)" autocomplete="off" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <input type="hidden" id="sched-patient" value="">
        <input type="hidden" id="sched-patient-visit" value="">
        <div id="sched-patient-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,0.1);margin-top:2px;"></div>
      </div>
    </div>
    <div style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Type</label>
      <select id="sched-type" onchange="schedTypeChange()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="medication">Medication</option>
        <option value="procedure">Procedure</option>
        <option value="vitals">Vitals Check</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Description / Item</label>
      <input type="text" id="sched-desc" placeholder="e.g. Paracetamol 500mg, Wound dressing..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Frequency</label>
        <select id="sched-freq" onchange="schedFreqChange()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="once">Once</option>
          <option value="bd">BD (Twice daily)</option>
          <option value="tds">TDS (3x daily)</option>
          <option value="qds">QDS (4x daily)</option>
          <option value="q6h">Every 6 hours</option>
          <option value="q8h">Every 8 hours</option>
          <option value="q12h">Every 12 hours</option>
          <option value="stat">STAT (Immediately)</option>
          <option value="custom">Custom times</option>
        </select>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Start Date</label>
        <input type="date" id="sched-start" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">End Date</label>
        <input type="date" id="sched-end" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Specific Time (for once/custom)</label>
        <input type="time" id="sched-time" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>
    <div style="margin-bottom:16px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Notes</label>
      <input type="text" id="sched-notes" placeholder="Additional instructions..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <button onclick="schedSave()" style="width:100%;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-save"></i> Save Schedule</button>
  </div>
</div>

<script>
const schedAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=schedule';
const schedWardAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=ward/ward-management';
function sEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
const timeSlots=['06:00','08:00','10:00','12:00','14:00','16:00','18:00','20:00','22:00','00:00'];

(function(){document.getElementById('sched-date').value=new Date().toISOString().split('T')[0];})();

async function schedLoad(){
  const date=document.getElementById('sched-date').value;
  const tbody=document.getElementById('sched-tbody');
  tbody.innerHTML='<tr><td colspan="11" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const r=await fetch(schedAPI+'&date='+date);
    const j=await r.json();
    if(!j.success||!j.data||!j.data.length){
      tbody.innerHTML='<tr><td colspan="11" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-calendar-times" style="font-size:24px;display:block;margin-bottom:8px;"></i>No scheduled items for this date.</td></tr>';
      return;
    }
    tbody.innerHTML=j.data.map(item=>{
      const icon=item.schedule_type==='medication'?'💊':item.schedule_type==='procedure'?'🏥':item.schedule_type==='vitals'?'❤️':'📋';
      const times=item.scheduled_times?JSON.parse(item.scheduled_times):[];
      let cells='';
      timeSlots.forEach(slot=>{
        const slotH=parseInt(slot.split(':')[0]);
        const match=times.find(t=>{const h=parseInt(t.split(':')[0]);return Math.abs(h-slotH)<1;});
        const administered=item.administered_times?JSON.parse(item.administered_times):[];
        const done=administered.includes(slot)||administered.some(a=>{const h=parseInt(a.split(':')[0]);return Math.abs(h-slotH)<1;});
        if(match){
          cells+=`<td style="padding:6px;text-align:center;"><button onclick="schedAdminister(${item.id},'${slot}')" style="width:32px;height:32px;border-radius:50%;border:2px solid ${done?'#16a34a':'#d97706'};background:${done?'#dcfce7':'#fef3c7'};cursor:pointer;font-size:12px;color:${done?'#16a34a':'#d97706'};" title="${done?'Done at '+slot:'Click to mark done'}">${done?'✓':'○'}</button></td>`;
        } else {
          cells+='<td style="padding:6px;text-align:center;color:var(--gray-200);">—</td>';
        }
      });
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;position:sticky;left:0;background:white;">
          <div style="font-weight:600;font-size:12px;">${sEsc(item.patient_name)}</div>
          <div style="font-size:11px;color:var(--gray-500);">${icon} ${sEsc(item.description)}</div>
          <div style="font-size:10px;color:var(--gray-400);">${sEsc(item.frequency||'')} ${item.notes?'| '+sEsc(item.notes):''}</div>
        </td>
        ${cells}
      </tr>`;
    }).join('');
  }catch(e){tbody.innerHTML='<tr><td colspan="11" style="padding:20px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

async function schedAdminister(schedId, timeSlot){
  try{
    const r=await fetch(schedAPI+'_administer',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({schedule_id:schedId,time_slot:timeSlot})});
    const j=await r.json();
    if(j.success) schedLoad();
    else alert('Error: '+j.message);
  }catch(e){alert('Failed: '+e.message);}
}

async function schedOpenAdd(){
  document.getElementById('sched-patient-search').value='';
  document.getElementById('sched-patient').value='';
  document.getElementById('sched-patient-visit').value='';
  document.getElementById('sched-start').value=new Date().toISOString().split('T')[0];
  document.getElementById('sched-end').value='';
  document.getElementById('sched-desc').value='';
  document.getElementById('sched-notes').value='';
  document.getElementById('sched-add-modal').style.display='flex';
}

function schedFreqChange(){
  const freq=document.getElementById('sched-freq').value;
  document.getElementById('sched-time').style.display=(freq==='once'||freq==='stat'||freq==='custom')?'':'none';
}

async function schedSave(){
  const patientId=document.getElementById('sched-patient').value;
  const visitId=document.getElementById('sched-patient-visit').value||null;
  const desc=document.getElementById('sched-desc').value.trim();
  const freq=document.getElementById('sched-freq').value;
  const startDate=document.getElementById('sched-start').value;
  if(!patientId||!desc||!startDate) return alert('Patient, description, and start date are required.');
  
  // Generate time slots based on frequency
  let times=[];
  const customTime=document.getElementById('sched-time').value;
  if(freq==='once'||freq==='stat') times=[customTime||'08:00'];
  else if(freq==='bd') times=['08:00','20:00'];
  else if(freq==='tds') times=['08:00','14:00','20:00'];
  else if(freq==='qds') times=['06:00','12:00','18:00','00:00'];
  else if(freq==='q6h') times=['06:00','12:00','18:00','00:00'];
  else if(freq==='q8h') times=['06:00','14:00','22:00'];
  else if(freq==='q12h') times=['08:00','20:00'];
  else if(freq==='custom') times=customTime?[customTime]:['08:00'];

  try{
    const r=await fetch(schedAPI+'_save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      patient_id:parseInt(patientId),visit_id:visitId?parseInt(visitId):null,
      schedule_type:document.getElementById('sched-type').value,
      description:desc,frequency:freq,
      scheduled_times:JSON.stringify(times),
      start_date:startDate,
      end_date:document.getElementById('sched-end').value||null,
      notes:document.getElementById('sched-notes').value
    })});
    const j=await r.json();
    if(j.success){
      alert('✓ Schedule saved!');
      document.getElementById('sched-add-modal').style.display='none';
      schedLoad();
    } else alert('Error: '+j.message);
  }catch(e){alert('Failed: '+e.message);}
}

// --- Searchable patient dropdown for schedule ---
let schedPtTimer;
let schedPtList=[];
async function schedPatientSearch(q){
  clearTimeout(schedPtTimer);
  const dd=document.getElementById('sched-patient-dropdown');
  if(!schedPtList.length){
    try{const r=await fetch(schedWardAPI+'&action=active_admissions');const j=await r.json();if(j.success)schedPtList=j.data||[];}catch(e){}
  }
  const term=q.toLowerCase();
  const filtered=term?schedPtList.filter(a=>a.patient_name.toLowerCase().includes(term)):schedPtList;
  if(filtered.length){
    dd.innerHTML=filtered.map(a=>`<div onclick="schedPatientSelect(${a.patient_id},${a.visit_id||0},'${sEsc(a.patient_name).replace(/'/g,"\\'")}')" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--gray-100);font-size:12px;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''"><div style="font-weight:600;">${sEsc(a.patient_name)}</div><div style="font-size:10px;color:var(--gray-500);">${sEsc(a.ward_name||'')} / Bed ${sEsc(a.bed_number||'')}</div></div>`).join('');
    dd.style.display='block';
  } else {
    dd.innerHTML='<div style="padding:10px;color:var(--gray-400);font-size:11px;text-align:center;">No match</div>';
    dd.style.display='block';
  }
}
function schedPatientSelect(id,visitId,name){
  document.getElementById('sched-patient').value=id;
  document.getElementById('sched-patient-visit').value=visitId;
  document.getElementById('sched-patient-search').value=name;
  document.getElementById('sched-patient-dropdown').style.display='none';
}
document.addEventListener('click',function(e){
  if(!e.target.closest('#sched-patient-search')&&!e.target.closest('#sched-patient-dropdown')){
    const dd=document.getElementById('sched-patient-dropdown');
    if(dd) dd.style.display='none';
  }
});

schedLoad();
</script>

<?php elseif ($subModule == 'records'): ?>
<!-- ====== ALL RECORDS - Grouped by Patient/Visit ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-history" style="color:var(--primary-color);"></i> Nursing Records</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="date" id="nsr-date-from" onchange="nsrLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <span style="font-size:12px;color:var(--gray-400);">to</span>
      <input type="date" id="nsr-date-to" onchange="nsrLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <input type="text" id="nsr-search" placeholder="Search patient..." oninput="nsrFilter()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;width:160px;">
      <button onclick="nsrLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>

  <!-- Grouped list -->
  <div id="nsr-list-section">
    <div id="nsr-grouped-list">
      <div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
  </div>

  <!-- Detail view (hidden until click) -->
  <div id="nsr-detail-section" style="display:none;">
    <button onclick="nsrBackToList()" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-bottom:16px;"><i class="fas fa-arrow-left"></i> Back to List</button>
    <div id="nsr-detail-header" style="background:linear-gradient(135deg,var(--primary-color),var(--secondary-color));border-radius:10px;padding:16px 20px;color:white;margin-bottom:16px;"></div>
    <div id="nsr-detail-content"></div>
  </div>
</div>

<script>
function nEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
let nsrAllData=[];

(function(){
  const today=new Date().toISOString().split('T')[0];
  const weekAgo=new Date(Date.now()-7*86400000).toISOString().split('T')[0];
  document.getElementById('nsr-date-from').value=weekAgo;
  document.getElementById('nsr-date-to').value=today;
})();

async function nsrLoad(){
  const container=document.getElementById('nsr-grouped-list');
  container.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div>';
  const dateFrom=document.getElementById('nsr-date-from').value;
  const dateTo=document.getElementById('nsr-date-to').value;
  try{
    let url=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=nursing_records&limit=500';
    if(dateFrom) url+='&date_from='+dateFrom;
    if(dateTo) url+='&date_to='+dateTo;
    const r=await fetch(url);
    const j=await r.json();
    nsrAllData=j.success?j.data||[]:[];
    nsrFilter();
  }catch(e){container.innerHTML='<div style="padding:20px;text-align:center;color:#dc2626;">Error loading</div>';}
  document.getElementById('nsr-detail-section').style.display='none';
  document.getElementById('nsr-list-section').style.display='block';
}

function nsrFilter(){
  const q=(document.getElementById('nsr-search').value||'').toLowerCase();
  let filtered=nsrAllData;
  if(q) filtered=filtered.filter(r=>(r.patient_name||'').toLowerCase().includes(q));
  nsrRenderGrouped(filtered);
}

function nsrRenderGrouped(records){
  const container=document.getElementById('nsr-grouped-list');
  if(!records.length){
    container.innerHTML='<div style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-clipboard-check" style="font-size:24px;display:block;margin-bottom:8px;"></i>No nursing records found for this period.</div>';
    return;
  }
  // Group by patient + visit
  const grouped={};
  records.forEach(r=>{
    const key=(r.patient_id||0)+'_'+(r.visit_id||0);
    if(!grouped[key]) grouped[key]={patient_id:r.patient_id,visit_id:r.visit_id,patient_name:r.patient_name||'Unknown',visit_no:r.visit_no||'',records:[],total:0};
    grouped[key].records.push(r);
    grouped[key].total+=parseFloat(r.quantity||1)*parseFloat(r.unit_price||0);
  });
  const groups=Object.values(grouped).sort((a,b)=>b.records.length-a.records.length);

  container.innerHTML=groups.map(g=>{
    const lastDate=g.records[0]?(g.records[0].administered_at||g.records[0].created_at||'').substring(0,10):'';
    const types={};
    g.records.forEach(r=>{types[r.record_type]=(types[r.record_type]||0)+1;});
    const typeBadges=Object.entries(types).map(([t,c])=>{
      const icon=t==='medication'?'💊':t==='injection'?'💉':t==='vitals'?'❤️':t==='service'?'🏥':'📝';
      return `<span style="padding:2px 6px;border-radius:8px;font-size:10px;background:var(--gray-100);color:var(--gray-600);">${icon} ${c}</span>`;
    }).join(' ');
    return `<div onclick="nsrShowDetail('${g.patient_id}_${g.visit_id}')" style="border:1px solid var(--gray-200);border-radius:8px;padding:14px 16px;margin-bottom:8px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;" onmouseover="this.style.borderColor='var(--primary-color)';this.style.background='#f8fafc'" onmouseout="this.style.borderColor='var(--gray-200)';this.style.background=''">
      <div style="flex:1;min-width:180px;">
        <div style="font-size:14px;font-weight:600;color:var(--gray-900);">${nEsc(g.patient_name)}</div>
        <div style="font-size:12px;color:var(--gray-500);">Visit: ${nEsc(g.visit_no)} | ${g.records.length} record(s) | Last: ${lastDate}</div>
        <div style="margin-top:4px;display:flex;gap:4px;flex-wrap:wrap;">${typeBadges}</div>
      </div>
      <div class="ns-price" style="text-align:right;">
        <div style="font-size:14px;font-weight:700;color:var(--primary-color);">${g.total>0?g.total.toLocaleString()+' RWF':'—'}</div>
        <div style="font-size:11px;color:var(--gray-400);">Total charges</div>
      </div>
    </div>`;
  }).join('');
}

function nsrBackToList(){
  document.getElementById('nsr-detail-section').style.display='none';
  document.getElementById('nsr-list-section').style.display='block';
}

function nsrShowDetail(key){
  const grouped={};
  nsrAllData.forEach(r=>{
    const k=(r.patient_id||0)+'_'+(r.visit_id||0);
    if(!grouped[k]) grouped[k]={patient_id:r.patient_id,visit_id:r.visit_id,patient_name:r.patient_name||'Unknown',visit_no:r.visit_no||'',records:[]};
    grouped[k].records.push(r);
  });
  const group=grouped[key];
  if(!group) return;

  document.getElementById('nsr-list-section').style.display='none';
  document.getElementById('nsr-detail-section').style.display='block';
  document.getElementById('nsr-detail-header').innerHTML=`
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div><div style="font-size:17px;font-weight:700;">${nEsc(group.patient_name)}</div><div style="font-size:12px;opacity:0.9;">Visit: ${nEsc(group.visit_no)} | ${group.records.length} record(s)</div></div>
    </div>`;

  const content=document.getElementById('nsr-detail-content');
  content.innerHTML=`<div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:10px;text-align:left;font-weight:600;">Type</th>
        <th style="padding:10px;text-align:left;font-weight:600;">Description</th>
        <th style="padding:10px;text-align:left;font-weight:600;">Dosage</th>
        <th style="padding:10px;text-align:center;font-weight:600;">Qty</th>
        <th class="ns-price" style="padding:10px;text-align:right;font-weight:600;">Amount</th>
        <th style="padding:10px;text-align:left;font-weight:600;">Performed By</th>
        <th style="padding:10px;text-align:left;font-weight:600;">Date/Time</th>
      </tr></thead>
      <tbody>${group.records.map(rec=>{
        const icon=rec.record_type==='medication'?'💊':rec.record_type==='injection'?'💉':rec.record_type==='vitals'?'❤️':rec.record_type==='service'?'🏥':'📝';
        const amount=parseFloat(rec.quantity||1)*parseFloat(rec.unit_price||0);
        const nurse=rec.nurse_name||rec.created_by_name||'—';
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 10px;">${icon} ${nEsc(rec.record_type)}</td>
          <td style="padding:8px 10px;font-weight:500;">${nEsc(rec.description)}</td>
          <td style="padding:8px 10px;">${nEsc(rec.dosage||'—')}</td>
          <td style="padding:8px 10px;text-align:center;">${rec.quantity||1}</td>
          <td class="ns-price" style="padding:8px 10px;text-align:right;font-weight:600;">${amount>0?amount.toLocaleString()+' RWF':'—'}</td>
          <td style="padding:8px 10px;color:var(--primary-color);font-weight:500;">${nEsc(nurse)}</td>
          <td style="padding:8px 10px;font-size:11px;color:var(--gray-500);">${new Date(rec.administered_at||rec.created_at).toLocaleString()}</td>
        </tr>`;
      }).join('')}</tbody>
    </table>
  </div>`;
}

nsrLoad();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
