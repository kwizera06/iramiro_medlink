<?php
// public/ot.php - Operation Theatre
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'scheduling';
?>

<style>
.ot-tab{padding:10px 20px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}
.ot-tab.active{background:var(--primary-color);color:white;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">Operation Theatre</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Schedule, manage and track surgical procedures</p>
  </div>
</div>

<div style="display:flex;gap:8px;margin-bottom:24px;">
  <button class="ot-tab <?php echo $subModule=='scheduling'?'active':'';?>" onclick="location.href='ot.php?module=ot&sub=scheduling'"><i class="fas fa-calendar-alt"></i> Schedule</button>
  <button class="ot-tab <?php echo $subModule=='active'?'active':'';?>" onclick="location.href='ot.php?module=ot&sub=active'"><i class="fas fa-procedures"></i> In Progress</button>
  <button class="ot-tab <?php echo $subModule=='completed'?'active':'';?>" onclick="location.href='ot.php?module=ot&sub=completed'"><i class="fas fa-check-circle"></i> Completed</button>
</div>

<?php if ($subModule == 'scheduling'): ?>
<!-- ====== SCHEDULING ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-calendar-plus" style="color:var(--primary-color);"></i> Scheduled Surgeries</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="otNewSurgery()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> Schedule Surgery</button>
      <button onclick="otLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div id="ot-list"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>

<script>
const otAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=surgery';
function oEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function otLoad(){
  try{
    const r = await fetch(otAPI+'&status=scheduled');
    const j = await r.json();
    const div = document.getElementById('ot-list');
    if(j.success && j.data && j.data.length){
      div.innerHTML = '<div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;"><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">Patient</th><th style="padding:10px;text-align:left;">Procedure</th><th style="padding:10px;text-align:left;">Surgeon</th><th style="padding:10px;text-align:left;">Date/Time</th><th style="padding:10px;text-align:left;">Priority</th><th style="padding:10px;text-align:center;">Action</th></tr></thead><tbody>'+j.data.map(s=>{
        const prBg = s.priority==='emergency'?'#fee2e2':s.priority==='urgent'?'#fef3c7':'#d1fae5';
        const prColor = s.priority==='emergency'?'#991b1b':s.priority==='urgent'?'#92400e':'#065f46';
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:10px;font-weight:500;">${oEsc(s.patient_name||'—')}</td>
          <td style="padding:10px;font-weight:600;">${oEsc(s.procedure_name)}</td>
          <td style="padding:10px;">${oEsc(s.surgeon_name||'TBD')}</td>
          <td style="padding:10px;font-size:12px;">${s.scheduled_date||'—'} ${s.scheduled_time||''}</td>
          <td style="padding:10px;"><span style="padding:2px 8px;border-radius:12px;font-size:11px;background:${prBg};color:${prColor};">${s.priority}</span></td>
          <td style="padding:10px;text-align:center;">
            <button onclick="otStart(${s.id})" style="padding:4px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;">Start</button>
            <button onclick="otCancel(${s.id})" style="padding:4px 10px;background:var(--gray-200);color:var(--gray-600);border:none;border-radius:4px;font-size:11px;cursor:pointer;">Cancel</button>
          </td></tr>`;
      }).join('')+'</tbody></table></div>';
    } else div.innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);">No scheduled surgeries.</div>';
  } catch(e){ document.getElementById('ot-list').innerHTML='<p style="color:red;">Error: '+e.message+'</p>'; }
}

async function otNewSurgery(){
  await otLoadMeds();
  // Load procedures from service_catalog
  let procOpts = '<option value="">Select procedure...</option>';
  try{ const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=patients/billing&action=services&category=procedure'); const j=await r.json(); if(j.success) j.data.forEach(s=>{ procOpts+=`<option value="${s.id}" data-price="${s.unit_price}">${oEsc(s.name)} (${parseFloat(s.unit_price).toLocaleString()} RWF)</option>`; }); }catch(e){}

  // Load admitted patients
  let ptOpts = '<option value="">Select patient...</option>';
  try{ const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=ward/ward-management&action=active_admissions'); const j=await r.json(); if(j.success) j.data.forEach(a=>{ ptOpts+=`<option value="${a.patient_id}" data-visit="${a.visit_id}">${oEsc(a.patient_name)} (${oEsc(a.ward_name)} / Bed ${oEsc(a.bed_number)})</option>`; }); }catch(e){}

  // Load doctors/staff from users
  let doctorOpts = '<option value="">Select surgeon...</option>';
  let anesthOpts = '<option value="">Select anesthetist...</option>';
  try{ const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=settings/users&limit=100'); const j=await r.json(); if(j.success && j.data) j.data.forEach(u=>{ const name=((u.first_name||'')+' '+(u.last_name||'')).trim()||'User #'+u.id; doctorOpts+=`<option value="${oEsc(name)}">${oEsc(name)} (${oEsc(u.role||'')})</option>`; anesthOpts+=`<option value="${oEsc(name)}">${oEsc(name)}</option>`; }); }catch(e){}

  const modal = document.createElement('div');
  modal.id='ot-modal';
  modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:550px;max-width:90vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-cut" style="color:var(--primary-color);"></i> Schedule Surgery</h3>
      <button onclick="document.getElementById('ot-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Patient <span style="color:var(--danger-color);">*</span></label><select id="ot-patient" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${ptOpts}</select></div>
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Procedure <span style="color:var(--danger-color);">*</span></label><select id="ot-procedure" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${procOpts}</select></div>
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Lead Surgeon <span style="color:var(--danger-color);">*</span></label><select id="ot-surgeon" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${doctorOpts}</select></div>
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Anesthetist</label><select id="ot-anesth" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${anesthOpts}</select></div>
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Assistant / Team</label><input type="text" id="ot-team" placeholder="e.g. Dr. Jones (assistant), Nurse Mary" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px;">
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Date</label><input type="date" id="ot-date" value="<?php echo date('Y-m-d');?>" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Time</label><input type="time" id="ot-time" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Priority</label><select id="ot-priority" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="elective">Elective</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></select></div>
      </div>
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Theatre Room</label><input type="text" id="ot-room" placeholder="e.g. OT-1" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>

      <!-- Additional Items/Medications -->
      <div style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <label style="font-size:12px;font-weight:600;color:var(--gray-700);">Additional Items (medications, supplies)</label>
          <button type="button" onclick="otAddItem()" style="padding:4px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-plus"></i> Add</button>
        </div>
        <div id="ot-items-list" style="max-height:150px;overflow-y:auto;"></div>
      </div>

      <div style="margin-bottom:16px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Pre-Op Notes</label><textarea id="ot-notes" rows="2" placeholder="Pre-operative notes..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea></div>
      <button onclick="otSave()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Schedule Surgery</button>
    </div>
  </div>`;
  document.body.appendChild(modal);
}

async function otSave(){
  const proc = document.getElementById('ot-procedure');
  const ptSel = document.getElementById('ot-patient');
  const surgeonSel = document.getElementById('ot-surgeon');
  if(!ptSel.value) return alert('Select a patient.');
  if(!proc.value) return alert('Select a procedure.');
  if(!surgeonSel.value) return alert('Select a lead surgeon.');
  const visitId = ptSel.selectedOptions[0].getAttribute('data-visit');
  const team = document.getElementById('ot-team').value;
  const surgeonName = surgeonSel.value + (team ? ' | Team: '+team : '');
  
  // Collect additional items
  const itemRows = document.querySelectorAll('.ot-item-row');
  const items = [];
  itemRows.forEach(row => {
    const sel = row.querySelector('.ot-item-sel');
    const qty = row.querySelector('.ot-item-qty');
    if(sel && sel.value){
      items.push({
        item_id: parseInt(sel.value),
        name: sel.selectedOptions[0].textContent.split(' (')[0],
        quantity: parseInt(qty.value)||1,
        unit_price: parseFloat(sel.selectedOptions[0].getAttribute('data-price'))||0
      });
    }
  });

  try{
    const r = await fetch(otAPI, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({
      patient_id: parseInt(ptSel.value),
      visit_id: visitId ? parseInt(visitId) : null,
      procedure_name: proc.selectedOptions[0].textContent.split(' (')[0],
      service_id: parseInt(proc.value),
      total_cost: parseFloat(proc.selectedOptions[0].getAttribute('data-price'))||0,
      surgeon_name: surgeonName,
      anesthetist_name: document.getElementById('ot-anesth').value||null,
      scheduled_date: document.getElementById('ot-date').value,
      scheduled_time: document.getElementById('ot-time').value,
      priority: document.getElementById('ot-priority').value,
      theatre_room: document.getElementById('ot-room').value,
      pre_op_notes: document.getElementById('ot-notes').value,
      items: items
    })});
    const j = await r.json();
    if(j.success){ alert('✓ Surgery scheduled!'); document.getElementById('ot-modal').remove(); otLoad(); }
    else alert('Error: '+j.message);
  } catch(e){ alert('Failed: '+e.message); }
}

let otMedsCache = [];
async function otLoadMeds(){
  if(otMedsCache.length) return;
  try{ const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=pharmacy/items&is_medication=1&limit=200'); const j=await r.json(); if(j.success) otMedsCache=j.data; }catch(e){}
}

function otAddItem(){
  const container = document.getElementById('ot-items-list');
  const idx = container.querySelectorAll('.ot-item-row').length;
  let opts = '<option value="">Select item...</option>';
  otMedsCache.forEach(m => { opts += `<option value="${m.id}" data-price="${m.selling_price||0}">${oEsc(m.name)} (${parseFloat(m.selling_price||0).toLocaleString()})</option>`; });
  const row = document.createElement('div');
  row.className = 'ot-item-row';
  row.style.cssText = 'display:flex;gap:6px;align-items:center;margin-bottom:6px;';
  row.innerHTML = `
    <select class="ot-item-sel" style="flex:1;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">${opts}</select>
    <input type="number" class="ot-item-qty" value="1" min="1" style="width:50px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:center;">
    <button onclick="this.parentElement.remove()" style="border:none;background:none;color:#dc2626;cursor:pointer;font-size:14px;"><i class="fas fa-trash"></i></button>
  `;
  container.appendChild(row);
}

async function otStart(id){ if(!confirm('Start this surgery now?')) return; await fetch(otAPI+'&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({status:'in_progress',actual_start:new Date().toISOString()})}); otLoad(); }
async function otCancel(id){ if(!confirm('Cancel this surgery?')) return; await fetch(otAPI+'&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({status:'cancelled'})}); otLoad(); }

otLoad();
</script>

<?php elseif ($subModule == 'active'): ?>
<!-- ====== IN PROGRESS ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-heartbeat" style="color:#dc2626;"></i> Surgeries In Progress</h3>
    <button onclick="otActiveLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div id="ot-active"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
const otAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=surgery';
function oEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function otActiveLoad(){
  const r = await fetch(otAPI+'&status=in_progress'); const j = await r.json();
  const div = document.getElementById('ot-active');
  if(j.success && j.data && j.data.length){
    div.innerHTML = j.data.map(s=>`<div style="border:1px solid #fecaca;border-radius:8px;padding:14px;margin-bottom:10px;background:#fef2f2;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div><div style="font-size:15px;font-weight:700;">${oEsc(s.procedure_name)}</div><div style="font-size:12px;color:var(--gray-600);">Patient: ${oEsc(s.patient_name||'#'+s.patient_id)} | Surgeon: ${oEsc(s.surgeon_name||'—')} | Room: ${oEsc(s.theatre_room||'—')}</div><div style="font-size:11px;color:var(--gray-500);">Started: ${s.actual_start||'—'}</div></div>
        <button onclick="otComplete(${s.id})" style="padding:8px 16px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-check"></i> Complete</button>
      </div>
    </div>`).join('');
  } else div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No surgeries in progress.</div>';
}
async function otComplete(id){
  const notes = prompt('Post-op notes (optional):','');
  await fetch(otAPI+'&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({status:'completed',actual_end:new Date().toISOString(),post_op_notes:notes||'',create_invoice:true})});
  alert('✓ Surgery completed! Invoice created.'); otActiveLoad();
}
otActiveLoad();
</script>

<?php elseif ($subModule == 'completed'): ?>
<!-- ====== COMPLETED ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-check-circle" style="color:#16a34a;"></i> Completed Surgeries</h3>
    <button onclick="otDoneLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">Patient</th><th style="padding:10px;text-align:left;">Procedure</th><th style="padding:10px;text-align:left;">Surgeon</th><th style="padding:10px;text-align:left;">Date</th><th style="padding:10px;text-align:right;">Cost</th><th style="padding:10px;text-align:left;">Payment</th></tr></thead>
      <tbody id="ot-done-tbody"><tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>
<script>
const otAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult&action=surgery';
function oEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function otDoneLoad(){
  const r = await fetch(otAPI+'&status=completed'); const j = await r.json();
  const tbody = document.getElementById('ot-done-tbody');
  if(j.success && j.data && j.data.length){
    tbody.innerHTML = j.data.map(s=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px;">${oEsc(s.patient_name||'#'+s.patient_id)}</td>
      <td style="padding:10px;font-weight:600;">${oEsc(s.procedure_name)}</td>
      <td style="padding:10px;">${oEsc(s.surgeon_name||'—')}</td>
      <td style="padding:10px;font-size:12px;">${s.scheduled_date||'—'}</td>
      <td style="padding:10px;text-align:right;font-weight:600;color:#16a34a;">${parseFloat(s.total_cost||0).toLocaleString()} RWF</td>
      <td style="padding:10px;">${payBadge(s.pay_status, s.pay_balance)}</td>
    </tr>`).join('');
  } else tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);">No completed surgeries.</td></tr>';
}
otDoneLoad();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
