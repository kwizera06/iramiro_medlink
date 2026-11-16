<?php
// public/ward.php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'admissions';
?>

<style>
.ward-tab{padding:10px 20px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}
.ward-tab.active{background:var(--primary-color);color:white;}
.stat-card{padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;}
.stat-card .num{font-size:24px;font-weight:700;}
.stat-card .label{font-size:12px;color:var(--gray-500);margin-top:2px;}
</style>

<!-- Page Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">Ward Management</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Manage admissions, beds, and patient discharges</p>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:8px;margin-bottom:24px;">
  <button class="ward-tab <?php echo $subModule=='admissions'?'active':'';?>" onclick="location.href='ward.php?module=ward&sub=admissions'">
    <i class="fas fa-user-plus"></i> Admissions
  </button>
  <button class="ward-tab <?php echo $subModule=='admitted'?'active':'';?>" onclick="location.href='ward.php?module=ward&sub=admitted'">
    <i class="fas fa-procedures"></i> Admitted Patients
  </button>
  <button class="ward-tab <?php echo $subModule=='rooms'?'active':'';?>" onclick="location.href='ward.php?module=ward&sub=rooms'">
    <i class="fas fa-bed"></i> Wards & Rooms
  </button>
</div>

<?php if ($subModule == 'admissions'): ?>
<!-- ============================== ADMISSIONS TAB ============================== -->

<!-- Date filter for pending admissions (default: today) -->
<div style="display:flex;justify-content:flex-end;align-items:center;gap:8px;margin-bottom:12px;font-size:12px;">
  <label>From <input type="date" id="ward-from" value="<?php echo date('Y-m-d'); ?>" onchange="wardLoadPending()" style="padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></label>
  <label>To <input type="date" id="ward-to" value="<?php echo date('Y-m-d'); ?>" onchange="wardLoadPending()" style="padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></label>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:24px;">
  <div class="stat-card"><div class="num" style="color:#d97706;" id="ws-pending">0</div><div class="label">Pending Admissions</div></div>
  <div class="stat-card"><div class="num" style="color:var(--primary-color);" id="ws-admitted">0</div><div class="label">Currently Admitted</div></div>
  <div class="stat-card"><div class="num" style="color:#16a34a;" id="ws-available">0</div><div class="label">Available Beds</div></div>
  <div class="stat-card"><div class="num" style="color:var(--gray-600);" id="ws-total">0</div><div class="label">Total Beds</div></div>
</div>

<!-- Pending Admission Requests -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-clock" style="color:#d97706;"></i> Pending Admission Requests</h3>
    <button onclick="wardLoadPending()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i> Refresh</button>
  </div>
  <div id="ward-pending-list">
    <div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
  </div>
</div>

<script>
const wAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=ward/ward-management';
function wEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function wardLoadPending(){
  try{
    const [pRes, aRes, bRes] = await Promise.all([
      fetch(wAPI+'&action=pending_admissions&date_from='+document.getElementById('ward-from').value+'&date_to='+document.getElementById('ward-to').value),
      fetch(wAPI+'&action=active_admissions'),
      fetch(wAPI+'&action=available_beds')
    ]);
    const [pj, aj, bj] = await Promise.all([pRes.json(), aRes.json(), bRes.json()]);
    
    // Stats
    const pending = pj.success ? pj.data : [];
    const admitted = aj.success ? aj.data : [];
    const beds = bj.success ? bj.data : [];
    document.getElementById('ws-pending').textContent = pending.length;
    document.getElementById('ws-admitted').textContent = admitted.length;
    document.getElementById('ws-available').textContent = beds.length;
    document.getElementById('ws-total').textContent = beds.length + admitted.length;
    
    // Render pending list
    const container = document.getElementById('ward-pending-list');
    if(!pending.length){
      container.innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:var(--success-color);"></i>No pending admission requests.</div>';
      return;
    }
    
    container.innerHTML = pending.map(req => `
      <div style="border:1px solid var(--gray-200);border-radius:8px;padding:14px 16px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div style="flex:1;min-width:200px;">
          <div style="font-size:15px;font-weight:600;color:var(--gray-900);">${wEsc(req.patient_name)}</div>
          <div style="font-size:12px;color:var(--gray-500);">${wEsc(req.patient_no||'')} | ${wEsc(req.visit_no||'')}</div>
          <div style="font-size:12px;color:var(--gray-700);margin-top:4px;"><strong>Reason:</strong> ${wEsc(req.diagnosis)}</div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:2px;">Type: ${wEsc(req.admission_type==='elective'?'Normal Admission':req.admission_type)} | By: ${wEsc(req.requested_by_name||'—')} | Pref: ${wEsc(req.ward_preference||'Any')} | ${new Date(req.created_at).toLocaleString()}</div>
        </div>
        <button onclick="wardAssignBed(${req.id}, ${req.patient_id}, ${req.visit_id}, '${wEsc(req.patient_name).replace(/'/g,"\\'")}')" style="padding:8px 16px;background:#7c3aed;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
          <i class="fas fa-bed"></i> Assign Bed
        </button>
      </div>
    `).join('');
  } catch(e){
    document.getElementById('ward-pending-list').innerHTML = '<div style="padding:20px;text-align:center;color:var(--danger-color);">Error: '+e.message+'</div>';
  }
}

async function wardAssignBed(requestId, patientId, visitId, patientName){
  // Load wards/rooms/beds for selection
  const wRes = await fetch(wAPI+'&action=wards');
  const wj = await wRes.json();
  const wards = wj.success ? wj.data : [];
  
  const modal = document.createElement('div');
  modal.id = 'assign-bed-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;width:500px;max-width:90vw;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-bed" style="color:#7c3aed;"></i> Assign Bed</h3>
      <button onclick="document.getElementById('assign-bed-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="background:var(--gray-50);border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;">${wEsc(patientName)}</div>
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Ward</label>
        <select id="ab-ward" onchange="abLoadRooms()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="">Select Ward...</option>
          ${wards.map(w=>'<option value="'+w.id+'">'+wEsc(w.name)+' ('+w.ward_type+')</option>').join('')}
        </select>
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Room</label>
        <select id="ab-room" onchange="abLoadBeds()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="">Select Room...</option>
        </select>
      </div>
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Bed</label>
        <select id="ab-bed" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="">Select Bed...</option>
        </select>
      </div>
      <button onclick="abConfirm(${requestId},${patientId},${visitId})" style="width:100%;padding:10px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
        <i class="fas fa-check"></i> Assign & Admit
      </button>
    </div>
  </div>`;
  document.body.appendChild(modal);
}

async function abLoadRooms(){
  const wardId = document.getElementById('ab-ward').value;
  const sel = document.getElementById('ab-room');
  sel.innerHTML = '<option value="">Select Room...</option>';
  document.getElementById('ab-bed').innerHTML = '<option value="">Select Bed...</option>';
  if(!wardId) return;
  const r = await fetch(wAPI+'&action=rooms&ward_id='+wardId);
  const j = await r.json();
  if(j.success) j.data.forEach(rm => { sel.innerHTML += `<option value="${rm.id}">${wEsc(rm.room_number)} (${rm.room_type}) - ${rm.available_beds||0} free</option>`; });
}

async function abLoadBeds(){
  const roomId = document.getElementById('ab-room').value;
  const sel = document.getElementById('ab-bed');
  sel.innerHTML = '<option value="">Select Bed...</option>';
  if(!roomId) return;
  const r = await fetch(wAPI+'&action=beds&room_id='+roomId);
  const j = await r.json();
  if(j.success) j.data.filter(b=>b.status==='available').forEach(b => { sel.innerHTML += `<option value="${b.id}">${wEsc(b.bed_number)}</option>`; });
}

async function abConfirm(requestId, patientId, visitId){
  const wardId = document.getElementById('ab-ward').value;
  const roomId = document.getElementById('ab-room').value;
  const bedId = document.getElementById('ab-bed').value;
  if(!wardId||!roomId||!bedId) return alert('Please select ward, room, and bed.');
  
  try{
    const r = await fetch(wAPI+'&action=assign_bed', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({patient_id:patientId, visit_id:visitId, bed_id:parseInt(bedId), room_id:parseInt(roomId), ward_id:parseInt(wardId), assignment_type:'admission'})
    });
    const j = await r.json();
    if(j.success){
      // Mark admission request as assigned
      await fetch(wAPI+'&action=update_request', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({request_id:requestId, status:'assigned', bed_id:parseInt(bedId)})});
      alert('✓ Bed assigned! Patient admitted.');
      document.getElementById('assign-bed-modal').remove();
      wardLoadPending();
    } else { alert('Error: '+j.message); }
  } catch(e){ alert('Failed: '+e.message); }
}

wardLoadPending();
setInterval(wardLoadPending, 30000);
</script>

<?php elseif ($subModule == 'admitted'): ?>
<!-- ============================== ADMITTED PATIENTS TAB ============================== -->

<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-procedures" style="color:#7c3aed;"></i> Currently Admitted</h3>
    <button onclick="admLoadList()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
          <th style="padding:10px 12px;text-align:left;font-weight:600;">Patient</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;">Ward / Room / Bed</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;">Admitted</th>
          <th style="padding:10px 12px;text-align:center;font-weight:600;">Days</th>
          <th style="padding:10px 12px;text-align:right;font-weight:600;">Rate/Day</th>
          <th style="padding:10px 12px;text-align:right;font-weight:600;">Total</th>
          <th style="padding:10px 12px;text-align:center;font-weight:600;">Action</th>
        </tr>
      </thead>
      <tbody id="adm-tbody">
        <tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div style="margin-top:20px;background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
  <div style="padding:14px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;gap:12px;">
    <h3 style="font-size:15px;font-weight:600;"><i class="fas fa-sign-out-alt" style="color:#64748b;"></i> All Discharged Patients</h3>
    <div style="display:flex;gap:8px;align-items:center;">
      <button type="button" onclick="ndExportExcel()" style="padding:7px 12px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-file-excel"></i> Excel</button>
      <input type="text" id="nd-search" placeholder="Search..." oninput="ndFilter()" style="width:240px;padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
    </div>
  </div>
  <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead><tr style="background:#f8fafc;color:var(--gray-600);text-align:left;border-bottom:1px solid var(--gray-200);">
      <th style="padding:8px 12px;">Patient</th><th style="padding:8px 12px;">Ward / Room / Bed</th><th style="padding:8px 12px;">Admitted</th><th style="padding:8px 12px;">Discharged</th><th style="padding:8px 12px;">Days</th><th style="padding:8px 12px;">Outcome</th><th style="padding:8px 12px;">Discharged By (Dr)</th><th style="padding:8px 12px;"></th>
    </tr></thead>
    <tbody id="nd-body"><tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
  </table></div>
</div>

<script>
const wAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=ward/ward-management';
function wEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function admLoadList(){
  const r = await fetch(wAPI+'&action=active_admissions');
  const j = await r.json();
  const tbody = document.getElementById('adm-tbody');
  if(j.success && j.data && j.data.length){
    tbody.innerHTML = j.data.map(a => {
      const days = Math.max(1, Math.ceil((new Date() - new Date(a.assigned_at||a.created_at))/(1000*60*60*24)));
      const rate = parseFloat(a.price_per_day||0);
      const total = days * rate;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 12px;"><div style="font-weight:600;">${wEsc(a.patient_name)}</div><div style="font-size:11px;color:var(--gray-500);">${wEsc(a.patient_no||'')}</div></td>
        <td style="padding:10px 12px;font-size:12px;">${wEsc(a.ward_name)} / ${wEsc(a.room_number)} / ${wEsc(a.bed_number)}</td>
        <td style="padding:10px 12px;font-size:12px;">${new Date(a.assigned_at||a.created_at).toLocaleDateString()}</td>
        <td style="padding:10px 12px;text-align:center;font-weight:700;">${days}</td>
        <td style="padding:10px 12px;text-align:right;font-size:12px;">${rate.toLocaleString()}</td>
        <td style="padding:10px 12px;text-align:right;font-weight:700;color:#16a34a;">${total.toLocaleString()}</td>
        <td style="padding:10px 12px;text-align:center;">
          <button onclick="admView(${a.visit_id},'${wEsc(a.patient_name).replace(/'/g,"\\'")}')" style="padding:5px 12px;background:white;color:var(--primary-color);border:1px solid var(--gray-200);border-radius:4px;font-size:11px;cursor:pointer;font-weight:500;margin-right:4px;"><i class="fas fa-eye"></i> View</button>
          <button onclick="admCheckout(${a.id},${a.patient_id},${a.visit_id},'${wEsc(a.patient_name).replace(/'/g,"\\'")}',${days},${rate})" style="padding:5px 12px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;font-weight:500;">
            <i class="fas fa-sign-out-alt"></i> Checkout
          </button>
        </td>
      </tr>`;
    }).join('');
  } else {
    tbody.innerHTML = '<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No admitted patients.</td></tr>';
  }
}

// Everything done to the patient during the admission (billed items + nursing records), by date
async function admView(visitId, name){
  let m = document.getElementById('adm-view-modal');
  if(!m){ m = document.createElement('div'); m.id='adm-view-modal';
    m.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;';
    m.onclick = e => { if(e.target===m) m.style.display='none'; }; document.body.appendChild(m); }
  m.style.display='flex';
  m.innerHTML='<div style="background:white;border-radius:12px;width:900px;max-width:94vw;max-height:88vh;overflow-y:auto;padding:20px;text-align:center;color:var(--gray-400);">Loading...</div>';
  const base = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult';
  try{
    const [a,b] = await Promise.all([
      fetch(base+'&action=visit_orders_summary&visit_id='+visitId).then(r=>r.json()),
      fetch(base+'&action=nursing_records&visit_id='+visitId+'&limit=1000').then(r=>r.json())]);
    if(!a.success) throw new Error(a.message||'Could not load visit');
    const rows = [
      ...(a.data.billed_items||[]).map(i=>({t:i.added_at, type:i.category||'billed', what:i.description, qty:i.quantity||1, amt:i.total, by:i.added_by_name})),
      ...(b.success?b.data||[]:[]).map(r=>({t:r.administered_at||r.created_at, type:r.record_type, what:r.description, qty:'', amt:'', by:r.nurse_name}))
    ].sort((x,y)=>new Date(x.t)-new Date(y.t));
    const td = 'padding:7px 10px;border-bottom:1px solid var(--gray-100);';
    m.firstChild.style.textAlign='left';
    m.firstChild.innerHTML = `<div style="display:flex;justify-content:space-between;margin-bottom:12px;"><h3 style="font-size:16px;font-weight:600;"><i class="fas fa-eye" style="color:var(--primary-color);"></i> ${wEsc(name)} · admission activity</h3>
      <button onclick="document.getElementById('adm-view-modal').style.display='none'" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);text-align:left;">
      <th style="padding:8px 10px;">Date</th><th style="padding:8px 10px;">Type</th><th style="padding:8px 10px;">Details</th><th style="padding:8px 10px;">Qty</th><th style="padding:8px 10px;text-align:right;">Amount</th><th style="padding:8px 10px;">By</th></tr></thead><tbody>`
      + (rows.length ? rows.map(r=>`<tr><td style="${td}white-space:nowrap;">${r.t?new Date(r.t).toLocaleString():''}</td><td style="${td}">${wEsc(r.type)}</td><td style="${td}">${wEsc(r.what)}</td><td style="${td}">${r.qty}</td><td style="${td}text-align:right;">${r.amt?(+r.amt).toLocaleString():''}</td><td style="${td}">${wEsc(r.by||'—')}</td></tr>`).join('')
        : '<tr><td colspan="6" style="padding:20px;text-align:center;color:var(--gray-400);">Nothing recorded yet.</td></tr>') + '</tbody></table>';
  }catch(e){ m.firstChild.innerHTML='<div style="color:#dc2626;">Error: '+wEsc(e.message)+'</div>'; }
}

async function admCheckout(assignId, patientId, visitId, name, days, rate){
  const total = days * rate;
  if(!confirm(`Checkout ${name}?\n\nDays: ${days}\nRate: ${rate.toLocaleString()} RWF/day\nTotal: ${total.toLocaleString()} RWF\n\nInvoice will be created.`)) return;
  try{
    const r = await fetch(wAPI+'&action=discharge', {method:'PUT', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({assignment_id:assignId, patient_id:patientId, visit_id:visitId, days:days, rate_per_day:rate, create_invoice:true})});
    const j = await r.json();
    if(j.success){ alert('✓ Discharged! Invoice: '+(j.data?.invoice_no||'Created')); admLoadList(); }
    else alert('Error: '+j.message);
  } catch(e){ alert('Failed: '+e.message); }
}

admLoadList();
const ndAPI = wAPI;
let ndRows = [];
async function ndLoad(){
  const body = document.getElementById('nd-body');
  try{
    const j = await (await fetch(ndAPI+'&action=discharged_admissions')).json();
    ndRows = j.success ? (j.data||[]) : [];
  }catch(e){ ndRows = []; }
  body.innerHTML = ndRows.length ? ndRows.map((a,i)=>`<tr class="nd-row" style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:8px 12px;font-weight:600;">${wEsc(a.patient_name)}<div style="font-weight:400;color:var(--gray-500);font-size:11px;">${wEsc(a.patient_no||'')}</div></td>
      <td style="padding:8px 12px;">${wEsc(a.ward_name)} / ${wEsc(a.room_number)} / Bed ${wEsc(a.bed_number)}</td>
      <td style="padding:8px 12px;">${wEsc(a.assigned_at||'')}</td>
      <td style="padding:8px 12px;">${wEsc(a.discharged_at||'')}</td>
      <td style="padding:8px 12px;">${a.days_admitted??''}</td>
      <td style="padding:8px 12px;">${wEsc(a.discharge_status||'discharged')}</td>
      <td style="padding:8px 12px;">${wEsc(a.discharge_ordered_by_name||a.discharged_by_name||'—')}</td>
      <td style="padding:8px 12px;"><button type="button" onclick="ndView(${i})" style="padding:4px 10px;font-size:11px;border:1px solid var(--gray-200);border-radius:4px;background:white;cursor:pointer;"><i class="fas fa-eye"></i> View details</button></td>
    </tr>`).join('') : '<tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);">No discharged patients.</td></tr>';
}
function ndFilter(){
  const t = document.getElementById('nd-search').value.toLowerCase();
  document.querySelectorAll('.nd-row').forEach(r => { r.style.display = r.textContent.toLowerCase().includes(t)?'':'none'; });
}
// CSV that Excel opens (same approach as the discharged-patients export in michel)
function ndExportExcel(){
  if(!ndRows.length){ alert('Nothing to export.'); return; }
  const headers = ['Patient','Patient No','Ward','Room','Bed','Admitted','Discharged','Days','Outcome','Admitted By','Discharged By (Dr)'];
  const rows = ndRows.map(a => [a.patient_name||'', a.patient_no||'', a.ward_name||'', a.room_number||'', a.bed_number||'', a.assigned_at||'', a.discharged_at||'', a.days_admitted??'', a.discharge_status||'discharged', a.admitted_by_name||'', a.discharge_ordered_by_name||a.discharged_by_name||'']);
  const escv = v => { const s = String(v==null?'':v); return (s.includes(',')||s.includes('"')||s.includes('\n')) ? '"'+s.replace(/"/g,'""')+'"' : s; };
  const csv = '﻿' + [headers,...rows].map(r=>r.map(escv).join(',')).join('\r\n');
  const url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8;'}));
  const a = document.createElement('a');
  a.href = url; a.download = 'discharged_patients_'+new Date().toLocaleDateString('en-CA')+'.csv';
  document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
}
function ndView(i){
  const a = ndRows[i];
  const extra = `<div style="font-size:12px;color:var(--gray-600);margin-bottom:8px;">Admitted: <strong>${wEsc(a.assigned_at||'')}</strong> by <strong>${wEsc(a.admitted_by_name||'—')}</strong> &nbsp;|&nbsp; Discharged: <strong>${wEsc(a.discharged_at||'')}</strong> by <strong>${wEsc(a.discharged_by_name||'—')}</strong> (${wEsc(a.discharge_status||'discharged')}) &nbsp;|&nbsp; ${a.days_admitted??0} day(s)</div>`;
  nsViewVisit(a.visit_id, a.patient_name, `${a.ward_name} / ${a.room_number} / Bed ${a.bed_number}`, extra);
}
ndLoad();
</script>
<?php require __DIR__ . '/includes/ns_view.php'; ?>

<?php elseif ($subModule == 'rooms'): ?>
<!-- ============================== WARDS & ROOMS TAB ============================== -->

<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-hospital" style="color:var(--primary-color);"></i> Wards, Rooms & Beds</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="rmAddWard()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;"><i class="fas fa-plus"></i> Add Ward</button>
      <button onclick="rmAddRoom()" style="padding:8px 14px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;"><i class="fas fa-plus"></i> Add Room</button>
      <button onclick="rmLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div id="rm-content">
    <div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
  </div>
</div>

<script>
const wAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=ward/ward-management';
function wEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function rmLoad(){
  try{
    const r = await fetch(wAPI+'&action=wards');
    const j = await r.json();
    if(!j.success || !j.data.length){ document.getElementById('rm-content').innerHTML='<p style="text-align:center;color:var(--gray-400);">No wards configured.</p>'; return; }
    
    let html = '';
    for(const w of j.data){
      const rr = await fetch(wAPI+'&action=rooms&ward_id='+w.id);
      const rj = await rr.json();
      const rooms = rj.success ? rj.data : [];
      
      html += `<div style="border:1px solid var(--gray-200);border-radius:8px;margin-bottom:16px;overflow:hidden;">
        <div style="padding:12px 16px;background:var(--gray-50);border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
          <div>
            <span style="font-size:14px;font-weight:700;color:var(--gray-900);">${wEsc(w.name)}</span>
            <span style="font-size:11px;padding:2px 8px;background:var(--primary-light);color:var(--primary-color);border-radius:10px;margin-left:8px;">${w.ward_type}</span>
            <span style="font-size:11px;color:var(--gray-400);margin-left:6px;">${w.code}</span>
          </div>
          <div style="display:flex;gap:6px;align-items:center;">
            <span style="font-size:12px;color:var(--gray-500);">${rooms.length} room(s)</span>
            <button onclick="rmDeleteWard(${w.id},'${wEsc(w.name).replace(/'/g,"\\'")}',${rooms.length})" style="padding:3px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;" title="Delete Ward"><i class="fas fa-trash"></i></button>
          </div>
        </div>`;
      
      if(rooms.length){
        html += `<div style="padding:12px 16px;">`;
        for(const rm of rooms){
          const br = await fetch(wAPI+'&action=beds&room_id='+rm.id);
          const bj = await br.json();
          const beds = bj.success ? bj.data : [];
          const avail = beds.filter(b=>b.status==='available').length;
          const occ = beds.filter(b=>b.status==='occupied').length;
          
          html += `<div style="border:1px solid var(--gray-100);border-radius:6px;padding:10px 12px;margin-bottom:8px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <span style="font-weight:600;font-size:13px;">${wEsc(rm.room_number)} <span style="font-weight:400;color:var(--gray-500);">(${rm.room_type})</span></span>
              <div style="display:flex;gap:6px;align-items:center;">
                <span style="font-size:11px;color:var(--gray-500);">${avail} free / ${beds.length} total</span>
                <button onclick="rmEditRoom(${rm.id},'${wEsc(rm.room_number).replace(/'/g,"\\'")}','${rm.room_type}',${w.id})" style="padding:3px 8px;background:#7c3aed;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;" title="Edit"><i class="fas fa-edit"></i></button>
                <button onclick="rmDeleteRoom(${rm.id},'${wEsc(rm.room_number).replace(/'/g,"\\'")}',${occ})" style="padding:3px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;" title="Delete"><i class="fas fa-trash"></i></button>
              </div>
            </div>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">`;
          beds.forEach(b => {
            const color = b.status==='available'?'#16a34a':b.status==='occupied'?'#dc2626':b.status==='cleaning'?'#d97706':'var(--gray-400)';
            html += `<span style="padding:3px 8px;border-radius:4px;font-size:11px;font-weight:500;background:${color}20;color:${color};border:1px solid ${color}40;" title="${b.status}">${wEsc(b.bed_number)}</span>`;
          });
          html += `</div></div>`;
        }
        html += `</div>`;
      } else {
        html += `<div style="padding:16px;text-align:center;color:var(--gray-400);font-size:12px;">No rooms in this ward.</div>`;
      }
      html += `</div>`;
    }
    document.getElementById('rm-content').innerHTML = html;
  } catch(e){
    document.getElementById('rm-content').innerHTML = '<p style="text-align:center;color:var(--danger-color);">Error: '+e.message+'</p>';
  }
}

function rmAddWard(){
  const modal = document.createElement('div');
  modal.id = 'ward-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;width:450px;max-width:90vw;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-hospital" style="color:var(--primary-color);"></i> Add Ward</h3>
      <button onclick="document.getElementById('ward-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Ward Name <span style="color:#dc2626;">*</span></label>
      <input id="wd-name" placeholder="e.g. General Ward" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Ward Code <span style="color:#dc2626;">*</span></label>
      <input id="wd-code" placeholder="e.g. GEN" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div style="margin-bottom:16px;">
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Ward Type <span style="color:#dc2626;">*</span></label>
      <select id="wd-type" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="general">General</option>
        <option value="vip">VIP</option>
        <option value="icu">ICU</option>
        <option value="maternity">Maternity</option>
        <option value="pediatric">Pediatric</option>
        <option value="isolation">Isolation</option>
        <option value="surgical">Surgical</option>
      </select>
    </div>
    <button onclick="rmSaveWard()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
      <i class="fas fa-check"></i> Create Ward
    </button>
  </div>`;
  document.body.appendChild(modal);
}

function rmSaveWard(){
  const name = document.getElementById('wd-name').value.trim();
  const code = document.getElementById('wd-code').value.trim();
  const type = document.getElementById('wd-type').value;
  if(!name||!code) return alert('Ward name and code are required.');
  fetch(wAPI+'&action=create_ward',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,code,ward_type:type})})
    .then(r=>r.json()).then(j=>{if(j.success){alert('✓ Ward created!');document.getElementById('ward-modal').remove();rmLoad();}else alert(j.message);});
}

function rmDeleteWard(wardId, wardName, roomCount){
  if(roomCount>0) return alert('Cannot delete ward "'+wardName+'" — it has '+roomCount+' room(s). Delete all rooms first.');
  if(!confirm('Delete ward "'+wardName+'"?\nThis action cannot be undone.')) return;
  fetch(wAPI+'&action=delete_ward&id='+wardId,{method:'DELETE'})
    .then(r=>r.json()).then(j=>{if(j.success){alert('✓ Ward deleted!');rmLoad();}else alert(j.message);});
}

async function rmAddRoom(){
  // Load wards for dropdown
  const r = await fetch(wAPI+'&action=wards');
  const j = await r.json();
  const wards = j.success ? j.data : [];
  
  const modal = document.createElement('div');
  modal.id = 'room-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;width:450px;max-width:90vw;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-plus" style="color:var(--primary-color);"></i> Add Room</h3>
      <button onclick="document.getElementById('room-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Ward <span style="color:#dc2626;">*</span></label>
      <select id="rm-ward" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="">-- Select Ward --</option>
        ${wards.map(w=>'<option value="'+w.id+'">'+wEsc(w.name)+' ('+w.ward_type+')</option>').join('')}
      </select>
    </div>
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Room Number <span style="color:#dc2626;">*</span></label>
      <input id="rm-number" placeholder="e.g. R-201" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Room Type <span style="color:#dc2626;">*</span></label>
      <select id="rm-type" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="single">Single</option>
        <option value="double">Double</option>
        <option value="triple">Triple</option>
        <option value="ward">Ward</option>
        <option value="suite">Suite</option>
        <option value="isolation">Isolation</option>
        <option value="icu">ICU</option>
      </select>
    </div>
    <div style="margin-bottom:16px;">
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Number of Beds <span style="color:#dc2626;">*</span></label>
      <input id="rm-beds" type="number" min="1" value="1" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <button onclick="rmSaveRoom()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
      <i class="fas fa-check"></i> Create Room
    </button>
  </div>`;
  document.body.appendChild(modal);
}

function rmSaveRoom(){
  const wardId = document.getElementById('rm-ward').value;
  const number = document.getElementById('rm-number').value.trim();
  const type = document.getElementById('rm-type').value;
  const beds = parseInt(document.getElementById('rm-beds').value)||1;
  if(!wardId||!number) return alert('Ward and room number required.');
  fetch(wAPI+'&action=create_room',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({ward_id:parseInt(wardId),room_number:number,room_type:type,bed_count:beds})})
    .then(r=>r.json()).then(j=>{if(j.success){alert('✓ Room created with '+beds+' bed(s)!');document.getElementById('room-modal').remove();rmLoad();}else alert(j.message);});
}

async function rmEditRoom(roomId, roomNumber, roomType, wardId){
  // Load wards for dropdown
  const r = await fetch(wAPI+'&action=wards');
  const j = await r.json();
  const wards = j.success ? j.data : [];
  
  const modal = document.createElement('div');
  modal.id = 'room-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;width:450px;max-width:90vw;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:#7c3aed;"></i> Edit Room</h3>
      <button onclick="document.getElementById('room-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="margin-bottom:12px;background:var(--gray-50);border-radius:6px;padding:10px;font-size:13px;font-weight:600;">
      Room: ${wEsc(roomNumber)}
    </div>
    <div style="margin-bottom:16px;">
      <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Room Type</label>
      <select id="rm-edit-type" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="single" ${roomType==='single'?'selected':''}>Single</option>
        <option value="double" ${roomType==='double'?'selected':''}>Double</option>
        <option value="triple" ${roomType==='triple'?'selected':''}>Triple</option>
        <option value="ward" ${roomType==='ward'?'selected':''}>Ward</option>
        <option value="suite" ${roomType==='suite'?'selected':''}>Suite</option>
        <option value="isolation" ${roomType==='isolation'?'selected':''}>Isolation</option>
        <option value="icu" ${roomType==='icu'?'selected':''}>ICU</option>
      </select>
    </div>
    <button onclick="rmUpdateRoom(${roomId})" style="width:100%;padding:10px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
      <i class="fas fa-save"></i> Update Room
    </button>
  </div>`;
  document.body.appendChild(modal);
}

function rmUpdateRoom(roomId){
  const type = document.getElementById('rm-edit-type').value;
  fetch(wAPI+'&action=update_room&id='+roomId,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({room_type:type})})
    .then(r=>r.json()).then(j=>{if(j.success){alert('✓ Room updated!');document.getElementById('room-modal').remove();rmLoad();}else alert(j.message);});
}

function rmDeleteRoom(roomId, roomNumber, occupiedCount){
  if(occupiedCount>0) return alert('Cannot delete room with '+occupiedCount+' occupied bed(s). Discharge patients first.');
  if(!confirm('Delete room '+roomNumber+'?\n\nThis will deactivate the room and all its beds.')) return;
  fetch(wAPI+'&action=delete_room&id='+roomId,{method:'DELETE'})
    .then(r=>r.json()).then(j=>{if(j.success){alert('✓ Room deleted!');rmLoad();}else alert(j.message);});
}

rmLoad();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
