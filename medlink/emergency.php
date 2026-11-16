<?php
// public/emergency.php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'triage';
$openRegister = $subModule == 'register'; // sidebar "Register Emergency": the Active Cases page with the new-case form open
if ($openRegister) $subModule = 'triage';
?>
<style>
.em-tab{padding:10px 20px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}
.em-tab.active{background:#dc2626;color:white;}
.triage-red{background:#fee2e2;border-color:#fecaca;} .triage-orange{background:#fff7ed;border-color:#fed7aa;}
.triage-yellow{background:#fefce8;border-color:#fef08a;} .triage-green{background:#f0fdf4;border-color:#bbf7d0;} .triage-blue{background:#eff6ff;border-color:#bfdbfe;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">Emergency Department</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Manage emergency cases, triage, and treatments</p>
  </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:24px;">
  <button class="em-tab <?php echo $subModule=='triage'?'active':'';?>" onclick="location.href='emergency.php?module=emergency&sub=triage'"><i class="fas fa-heartbeat"></i> Active Cases</button>
  <button class="em-tab <?php echo $subModule=='treatment'?'active':'';?>" onclick="location.href='emergency.php?module=emergency&sub=treatment'"><i class="fas fa-notes-medical"></i> Treatment</button>
  <button class="em-tab <?php echo $subModule=='admitted'?'active':'';?>" onclick="location.href='emergency.php?module=emergency&sub=admitted'"><i class="fas fa-bed"></i> Admitted</button>
  <button class="em-tab <?php echo $subModule=='discharged'?'active':'';?>" onclick="location.href='emergency.php?module=emergency&sub=discharged'"><i class="fas fa-check-circle"></i> Closed</button>
</div>

<?php if ($subModule == 'triage'): ?>
<!-- ====== ACTIVE CASES ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-ambulance" style="color:#dc2626;"></i> Active Emergency Cases</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="emNewCase()" style="padding:8px 16px;background:#dc2626;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> New Case</button>
      <button onclick="emLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div id="em-cases"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>

<script>
const emAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=consultation/consult&action=emergency';
function eEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function emLoad(){
  try{
    const r = await fetch(emAPI+'&status=active');
    const j = await r.json();
    const div = document.getElementById('em-cases');
    if(j.success && j.data && j.data.length){
      emCases = {}; j.data.forEach(c => emCases[c.id] = c);
      div.innerHTML = j.data.map(c => {
        const triageColors = {red:'#dc2626',orange:'#ea580c',yellow:'#ca8a04',green:'#16a34a',blue:'#2563eb'};
        const color = triageColors[c.triage_level]||'#6b7280';
        return `<div style="border:2px solid ${color}30;border-left:4px solid ${color};border-radius:8px;padding:14px 16px;margin-bottom:10px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <span style="font-size:15px;font-weight:700;color:var(--gray-900);">${eEsc(c.patient_name||'Unknown')}</span>
              <span style="padding:2px 8px;border-radius:12px;font-size:10px;font-weight:700;background:${color}20;color:${color};margin-left:8px;text-transform:uppercase;">${c.triage_level}</span>
              <span style="padding:2px 8px;border-radius:12px;font-size:10px;background:var(--gray-100);color:var(--gray-600);margin-left:4px;">${c.status}</span>
            </div>
            <div style="display:flex;gap:6px;">
              <button onclick="location.href='emergency.php?module=emergency&sub=treatment&case_id=${c.id}'" style="padding:5px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-syringe"></i> Treat</button>
              <button onclick="emClose(${c.id})" style="padding:5px 10px;background:var(--gray-600);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-sign-out-alt"></i> Close</button>
            </div>
          </div>
          <div style="font-size:12px;color:var(--gray-600);margin-top:6px;"><strong>Complaint:</strong> ${eEsc(c.chief_complaint||'—')} | <strong>Doctor:</strong> ${c.doctor_name?eEsc(c.doctor_name):'<span style="color:#dc2626;">not yet seen</span>'} | <strong>Nurse:</strong> ${c.nurse_name?eEsc(c.nurse_name):'<span style="color:#dc2626;">not yet seen</span>'}</div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:4px;">Arrival: ${c.arrival_mode} | ${c.vitals_bp?'BP:'+c.vitals_bp:''} ${c.vitals_pulse?'P:'+c.vitals_pulse:''} ${c.vitals_temp?'T:'+c.vitals_temp:''} | ${new Date(c.admitted_at||c.created_at).toLocaleString()}</div>
        </div>`;
      }).join('');
    } else div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No active emergency cases.</div>';
  } catch(e){ document.getElementById('em-cases').innerHTML='<p style="color:red;">Error: '+e.message+'</p>'; }
}

async function emNewCase(){
  // Load patients
  let ptOpts='<option value="">Walk-in (new patient)</option>';
  try{ const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=patients/patients&limit=50'); const j=await r.json(); if(j.success&&j.data) j.data.forEach(p=>{ ptOpts+=`<option value="${p.id}">${eEsc((p.first_name||'')+' '+(p.last_name||''))} (${p.patient_no||''})</option>`; }); }catch(e){}

  const modal=document.createElement('div'); modal.id='em-modal';
  modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:550px;max-width:90vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-ambulance" style="color:#dc2626;"></i> New Emergency Case</h3>
      <button onclick="document.getElementById('em-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Patient</label><select id="em-patient" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${ptOpts}</select></div>
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Chief Complaint <span style="color:red;">*</span></label><textarea id="em-complaint" rows="2" placeholder="What happened..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Triage Level</label><select id="em-triage" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="red">🔴 Red (Immediate)</option><option value="orange">🟠 Orange (Very Urgent)</option><option value="yellow" selected>🟡 Yellow (Urgent)</option><option value="green">🟢 Green (Standard)</option><option value="blue">🔵 Blue (Non-Urgent)</option></select></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Arrival Mode</label><select id="em-arrival" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="walk_in">Walk-in</option><option value="ambulance">Ambulance</option><option value="referral">Referral</option><option value="police">Police</option></select></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">BP</label><input id="em-bp" placeholder="120/80" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Pulse</label><input id="em-pulse" placeholder="88" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Temp</label><input id="em-temp" placeholder="37.5" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">SpO2</label><input id="em-spo2" placeholder="95%" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">RR</label><input id="em-rr" placeholder="20" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;"></div>
        <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);">Consciousness</label><select id="em-avpu" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"><option>alert</option><option>verbal</option><option>pain</option><option>unresponsive</option></select></div>
      </div>
      <button onclick="emSaveCase()" style="width:100%;padding:10px;background:#dc2626;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Register Emergency Case</button>
    </div>
  </div>`;
  document.body.appendChild(modal);
}

async function emSaveCase(){
  const complaint = document.getElementById('em-complaint').value.trim();
  if(!complaint) return alert('Enter chief complaint.');
  try{
    const r = await fetch(emAPI, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({
      patient_id: parseInt(document.getElementById('em-patient').value)||null,
      chief_complaint: complaint,
      triage_level: document.getElementById('em-triage').value,
      arrival_mode: document.getElementById('em-arrival').value,
      consciousness: document.getElementById('em-avpu').value,
      vitals_bp: document.getElementById('em-bp').value,
      vitals_pulse: document.getElementById('em-pulse').value,
      vitals_temp: document.getElementById('em-temp').value,
      vitals_spo2: document.getElementById('em-spo2').value,
      vitals_rr: document.getElementById('em-rr').value
    })});
    const j = await r.json();
    if(j.success){ alert('✓ Case registered!'); document.getElementById('em-modal').remove(); emLoad(); }
    else alert('Error: '+j.message);
  } catch(e){ alert('Failed: '+e.message); }
}

// Close the ER case with an outcome, signed by a doctor and a nurse. "Admitted" sends the patient to the ward's pending admissions.
let emCases = {}, emStaff = null;
async function emClose(id){
  const c = emCases[id], hasPatient = !!c.patient_id;
  if(!emStaff){ const r = await fetch(emAPI+'&sub_action=staff'); const j = await r.json(); emStaff = j.success ? j.data : {staff:[], me:0}; }
  const staffOpts = (role, current) => {
    const byRole = emStaff.staff.filter(s => s.role === role);
    const list = byRole.length ? byRole : emStaff.staff;
    const sel = current || (list.some(s => s.id == emStaff.me) ? emStaff.me : '');
    return `<option value="">Select ${role}...</option>` + list.map(s => `<option value="${s.id}" ${s.id == sel ? 'selected' : ''}>${eEsc(s.name)}</option>`).join('');
  };
  const modal=document.createElement('div'); modal.id='em-modal';
  modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:480px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-sign-out-alt" style="color:#dc2626;"></i> Close Emergency Case</h3>
      <button onclick="document.getElementById('em-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Doctor <span style="color:red;">*</span></label><select id="ec-doctor" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${staffOpts('doctor', c.doctor_id)}</select></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Nurse <span style="color:red;">*</span></label><select id="ec-nurse" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${staffOpts('nurse', c.nurse_id)}</select></div>
      </div>
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Outcome</label>
        <select id="ec-status" onchange="document.getElementById('ec-admit').style.display=this.value==='admitted'?'block':'none'" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="discharged">Discharged home</option>
          ${hasPatient?'<option value="admitted">Admit to ward</option>':''}
          <option value="transferred">Transferred / referred out</option>
          <option value="deceased">Deceased</option>
        </select>
        ${hasPatient?'':'<div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Register the patient to enable ward admission.</div>'}
      </div>
      <div id="ec-admit" style="display:none;">
        <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Admission diagnosis</label><input id="ec-diagnosis" placeholder="Defaults to chief complaint" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Ward preference</label>
          <select id="ec-ward" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Any available</option><option value="general">General</option><option value="vip">VIP</option><option value="icu">ICU</option><option value="maternity">Maternity</option><option value="pediatric">Pediatric</option><option value="isolation">Isolation</option></select></div>
      </div>
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Plan / what next</label><textarea id="ec-notes" rows="3" placeholder="Follow-up, medication, instructions, handover..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea></div>
      <button onclick="emSaveClose(${id})" style="width:100%;padding:10px;background:#dc2626;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Close Case</button>
    </div>
  </div>`;
  document.body.appendChild(modal);
}

async function emSaveClose(id){
  const status = document.getElementById('ec-status').value;
  const doctor_id = parseInt(document.getElementById('ec-doctor').value) || 0;
  const nurse_id = parseInt(document.getElementById('ec-nurse').value) || 0;
  if(!doctor_id || !nurse_id) return alert('Select the doctor and the nurse closing this case.');
  try{
    const r = await fetch(emAPI+'&id='+id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({
      status, create_invoice:true, doctor_id, nurse_id,
      notes: document.getElementById('ec-notes').value,
      diagnosis: document.getElementById('ec-diagnosis').value,
      ward_preference: document.getElementById('ec-ward').value
    })});
    const j = await r.json();
    if(!j.success) return alert('Error: '+j.message);
    alert(status==='admitted' ? '✓ Case closed. Patient sent to Ward → pending admissions.' : '✓ Case closed. Invoice created for treatments.');
    document.getElementById('em-modal').remove(); emLoad();
  } catch(e){ alert('Failed: '+e.message); }
}

emLoad(); setInterval(emLoad, 20000);
<?php if (!empty($openRegister)): ?>emNewCase();<?php endif; ?>
</script>

<?php elseif ($subModule == 'treatment'): ?>
<!-- ====== TREATMENT ====== -->
<?php $caseId = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0; ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <!-- Left: Case info + treatments -->
  <div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;"><i class="fas fa-file-medical" style="color:#dc2626;"></i> Case Treatments</h3>
    <div id="em-treat-list"><div style="padding:20px;text-align:center;color:var(--gray-400);">Select a case or <a href="emergency.php?module=emergency&sub=triage">go to active cases</a></div></div>
  </div>
  <!-- Right: Add treatment -->
  <div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;"><i class="fas fa-syringe" style="color:#16a34a;"></i> Add Treatment</h3>
    <?php if($caseId): ?>
    <div style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Type</label>
      <select id="et-type" onchange="etTypeChange()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="medication">💊 Medication</option>
        <option value="iv_fluid">💧 IV Fluid</option>
        <option value="procedure">🏥 Procedure</option>
        <option value="oxygen">🫁 Oxygen</option>
        <option value="dressing">🩹 Dressing</option>
        <option value="other">📝 Other</option>
      </select>
    </div>
    <div id="et-item-div" style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Item</label>
      <select id="et-item" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><option value="">Loading...</option></select>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
      <div><label style="font-size:12px;font-weight:500;color:var(--gray-700);display:block;margin-bottom:4px;">Dosage</label><input id="et-dosage" placeholder="e.g. 500mg IV" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></div>
      <div><label style="font-size:12px;font-weight:500;color:var(--gray-700);display:block;margin-bottom:4px;">Qty</label><input type="number" id="et-qty" value="1" min="1" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></div>
    </div>
    <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:500;color:var(--gray-700);display:block;margin-bottom:4px;">Notes</label><textarea id="et-notes" rows="2" placeholder="Notes..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;resize:vertical;"></textarea></div>
    <button onclick="etSave()" style="width:100%;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-plus"></i> Add Treatment</button>
    <?php else: ?>
    <p style="color:var(--gray-400);font-size:13px;text-align:center;padding:20px;">Go to <a href="emergency.php?module=emergency&sub=triage">Active Cases</a> and click "Treat" on a case.</p>
    <?php endif; ?>
  </div>
</div>

<script>
const emAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=consultation/consult&action=emergency';
const caseId = <?php echo $caseId ?: 'null'; ?>;
function eEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

let etMeds=[], etSvcs=[];
async function etLoadItems(){
  const base = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php');
  try{ const r=await fetch(base+'?endpoint=pharmacy/items&is_medication=1&limit=200'); const j=await r.json(); if(j.success) etMeds=j.data; }catch(e){}
  try{ const r=await fetch(base+'?endpoint=patients/billing&action=services&category=procedure,nursing,other'); const j=await r.json(); if(j.success) etSvcs=j.data; }catch(e){}
  etTypeChange();
}

function etTypeChange(){
  const type = document.getElementById('et-type').value;
  const sel = document.getElementById('et-item');
  if(type==='medication'||type==='iv_fluid'){
    sel.innerHTML = '<option value="">Select drug...</option>'+etMeds.map(m=>`<option value="${m.id}" data-price="${m.selling_price||0}">${eEsc(m.name)} (${parseFloat(m.selling_price||0).toLocaleString()})</option>`).join('');
  } else if(type==='procedure'||type==='dressing'){
    sel.innerHTML = '<option value="">Select service...</option>'+etSvcs.map(s=>`<option value="svc-${s.id}" data-price="${s.unit_price||0}">${eEsc(s.name)} (${parseFloat(s.unit_price||0).toLocaleString()})</option>`).join('');
  } else {
    sel.innerHTML = '<option value="">N/A - describe in notes</option>';
  }
}

async function etSave(){
  if(!caseId) return alert('No case selected.');
  const type = document.getElementById('et-type').value;
  const itemSel = document.getElementById('et-item');
  const selVal = itemSel.value;
  let itemId=null, serviceId=null, description='', unitPrice=0;
  
  if(selVal && selVal.startsWith('svc-')){ serviceId=parseInt(selVal.replace('svc-','')); description=itemSel.selectedOptions[0].textContent.split(' (')[0]; unitPrice=parseFloat(itemSel.selectedOptions[0].getAttribute('data-price'))||0; }
  else if(selVal){ itemId=parseInt(selVal); description=itemSel.selectedOptions[0].textContent.split(' (')[0]; unitPrice=parseFloat(itemSel.selectedOptions[0].getAttribute('data-price'))||0; }
  else { description=document.getElementById('et-notes').value||type; }

  try{
    const r = await fetch(emAPI+'&sub_action=add_treatment', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({
      case_id: caseId, treatment_type: type, item_id: itemId, service_id: serviceId,
      description: description, dosage: document.getElementById('et-dosage').value,
      quantity: parseInt(document.getElementById('et-qty').value)||1, unit_price: unitPrice,
      notes: document.getElementById('et-notes').value
    })});
    const j = await r.json();
    if(j.success){ alert('✓ Treatment added!'); document.getElementById('et-dosage').value=''; document.getElementById('et-notes').value=''; document.getElementById('et-qty').value='1'; etLoadTreatments(); }
    else alert('Error: '+j.message);
  } catch(e){ alert('Failed: '+e.message); }
}

async function etLoadTreatments(){
  if(!caseId) return;
  try{
    const r = await fetch(emAPI+'&sub_action=treatments&case_id='+caseId);
    const j = await r.json();
    const div = document.getElementById('em-treat-list');
    if(j.success && j.data && j.data.length){
      div.innerHTML = j.data.map(t=>{
        const icons={medication:'💊',iv_fluid:'💧',procedure:'🏥',oxygen:'🫁',dressing:'🩹',other:'📝'};
        return `<div style="padding:8px 10px;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;font-size:12px;">
          <div><span>${icons[t.treatment_type]||'📋'}</span> <strong>${eEsc(t.description)}</strong> ${t.dosage?'('+eEsc(t.dosage)+')':''}</div>
          <div style="color:var(--gray-500);">${t.quantity}x ${parseFloat(t.unit_price||0).toLocaleString()} | ${new Date(t.administered_at).toLocaleTimeString()}</div>
        </div>`;
      }).join('');
    } else div.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">No treatments recorded yet.</div>';
  } catch(e){}
}

if(caseId){ etLoadItems(); etLoadTreatments(); }
</script>

<?php elseif ($subModule == 'discharged' || $subModule == 'admitted'): ?>
<!-- ====== CLOSED / ADMITTED ====== -->
<?php $isAdmitted = $subModule == 'admitted'; ?>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><?php echo $isAdmitted ? '<i class="fas fa-bed" style="color:#7c3aed;"></i> Admitted from Emergency' : '<i class="fas fa-check-circle" style="color:#16a34a;"></i> Closed Cases'; ?></h3>
    <button onclick="emDischLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">Patient</th><th style="padding:10px;text-align:left;">Complaint</th><th style="padding:10px;text-align:left;">Triage</th><th style="padding:10px;text-align:left;">Doctor</th><th style="padding:10px;text-align:left;">Nurse</th><th style="padding:10px;text-align:left;">Outcome</th><th style="padding:10px;text-align:left;">Plan / next</th><th style="padding:10px;text-align:left;">Arrived</th><th style="padding:10px;text-align:left;">Closed</th></tr></thead>
      <tbody id="em-disch-tbody"><tr><td colspan="9" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>
<script>
function eEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function emDischLoad(){
  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=consultation/consult&action=emergency&status=<?php echo $isAdmitted ? 'admitted' : 'closed'; ?>');
    const j = await r.json();
    const tbody = document.getElementById('em-disch-tbody');
    if(j.success && j.data && j.data.length){
      tbody.innerHTML = j.data.map(c=>`<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;font-weight:500;">${eEsc(c.patient_name||'Unknown')}</td>
        <td style="padding:8px 10px;font-size:12px;">${eEsc((c.chief_complaint||'').substring(0,40))}</td>
        <td style="padding:8px 10px;"><span style="text-transform:uppercase;font-size:11px;font-weight:600;">${c.triage_level}</span></td>
        <td style="padding:8px 10px;font-size:12px;">${eEsc(c.doctor_name||'—')}</td>
        <td style="padding:8px 10px;font-size:12px;">${eEsc(c.nurse_name||'—')}</td>
        <td style="padding:8px 10px;font-size:12px;"><span style="text-transform:capitalize;">${eEsc(c.status)}</span>${c.status==='admitted' ? '<div style="font-size:11px;color:#7c3aed;">'+(c.ward_bed ? eEsc(c.ward_bed) : (c.awaiting_bed>0 ? 'Awaiting bed' : 'Left ward'))+'</div>' : ''}</td>
        <td style="padding:8px 10px;font-size:12px;">${eEsc(c.treatment_notes||'—')}</td>
        <td style="padding:8px 10px;font-size:12px;">${c.admitted_at?new Date(c.admitted_at).toLocaleString():'—'}</td>
        <td style="padding:8px 10px;font-size:12px;">${c.discharged_at?new Date(c.discharged_at).toLocaleString():'—'}</td>
      </tr>`).join('');
    } else tbody.innerHTML='<tr><td colspan="9" style="padding:30px;text-align:center;color:var(--gray-400);"><?php echo $isAdmitted ? 'No admitted cases.' : 'No closed cases.'; ?></td></tr>';
  } catch(e){}
}
emDischLoad();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
