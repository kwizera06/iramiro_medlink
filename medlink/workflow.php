<?php
// workflow.php — Medical Operations steps that sit between the clinical pages: patient (bed) transfer and visit closure.
// transfers: the existing ward API moves the patient; closure: checks, then closes the visit (handlers/workflow/workflow.php).
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'transfers';
if (!in_array($subModule, ['transfers', 'closure'], true)) $subModule = 'transfers';
?>
<style>
.wf-card{padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;}
.wf-card b{display:block;font-size:22px;font-weight:700;} .wf-card span{font-size:12px;color:var(--gray-500);}
.wf-box{background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;margin-bottom:18px;}
.wf-box h3{font-size:15px;font-weight:600;padding:14px 16px 0;}
.wf-tbl{width:100%;border-collapse:collapse;font-size:13px;} .wf-tbl th{padding:10px 12px;text-align:left;background:var(--gray-50);border-bottom:2px solid var(--gray-200);} .wf-tbl td{padding:10px 12px;border-bottom:1px solid var(--gray-100);vertical-align:top;}
.wf-in{padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;}
.wf-btn{padding:7px 14px;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition:transform 140ms ease-out;white-space:nowrap;} .wf-btn:active{transform:scale(0.97);} .wf-btn:disabled{opacity:.5;cursor:not-allowed;transform:none;}
.wf-pill{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;margin:1px 2px 1px 0;white-space:nowrap;}
.wf-seg{display:inline-flex;background:var(--gray-100);border-radius:8px;padding:3px;gap:2px;} .wf-seg button{padding:7px 14px;border:none;background:transparent;border-radius:6px;font-size:13px;font-weight:500;color:var(--gray-600);cursor:pointer;} .wf-seg button.on{background:white;color:var(--gray-900);box-shadow:0 1px 2px rgba(0,0,0,.08);}
.wf-ov{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:16px;}
.wf-dlg{background:white;border-radius:12px;padding:22px;width:480px;max-width:100%;max-height:90vh;overflow-y:auto;}
.wf-err{display:none;margin:0 0 12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
</style>
<script>
const wfBase=(typeof window.appBasePath!=='undefined'&&window.appBasePath!==null)?window.appBasePath:(window.location.pathname.substring(0,window.location.pathname.lastIndexOf('/'))||'');
const WF=wfBase+'/api.php?endpoint=workflow/workflow&action=', WARD=wfBase+'/api.php?endpoint=ward/ward-management&action=';
const WF_ADMIN=<?= isUserAdmin($pdo) ? "true" : "false" ?>;
const wfEsc=s=>String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const wfGet=async u=>{try{return await (await fetch(u)).json();}catch(e){return {success:false,message:'Network error'};}};
const wfPost=async(u,b,m)=>{try{return await (await fetch(u,{method:m||'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)})).json();}catch(e){return {success:false,message:'Network error'};}};
const wfDate=d=>d?new Date(String(d).replace(' ','T')).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):'';
function wfClose(){document.querySelectorAll('.wf-ov').forEach(o=>o.remove());}
document.addEventListener('keydown',e=>{if(e.key==='Escape')wfClose();});
function wfDialog(html){wfClose();const o=document.createElement('div');o.className='wf-ov';o.onclick=e=>{if(e.target===o)wfClose();};o.innerHTML='<div class="wf-dlg">'+html+'</div>';document.body.appendChild(o);}
</script>

<?php if ($subModule === 'transfers'): ?>
<!-- ====== PATIENT TRANSFER ====== -->
<div style="margin-bottom:18px;display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;">
  <div style="margin-right:auto;">
    <h2 style="font-size:24px;font-weight:600;">Patient Transfer</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Internal: move an admitted patient to another bed or ward. External: refer the patient to another facility. An administrator approves both here.</p>
  </div>
  <button class="wf-btn" onclick="trNew()" style="background:var(--primary-color);color:white;font-weight:600;"><i class="fas fa-plus"></i> New transfer</button>
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px;">
  <div class="wf-card"><b id="tr-s-wait" style="color:#92400e;">—</b><span>Awaiting approval</span></div>
  <div class="wf-card"><b id="tr-s-open" style="color:var(--primary-color);">—</b><span>Approved / in progress</span></div>
  <div class="wf-card"><b id="tr-s-done" style="color:var(--success-color);">—</b><span>Completed today</span></div>
</div>
<div class="wf-box">
  <div style="padding:14px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <div class="wf-seg" id="tr-seg"><button class="on" data-v="internal" onclick="trTab('internal')">Internal</button><button data-v="external" onclick="trTab('external')">External</button></div>
    <input id="tr-search" class="wf-in" placeholder="Patient name or number" style="width:220px;margin-left:auto;" oninput="trRender()">
  </div>
  <table class="wf-tbl"><thead><tr><th>Reference</th><th>Patient</th><th>From</th><th id="tr-th-to">To</th><th>Urgency</th><th>Status</th><th></th></tr></thead>
  <tbody id="tr-body"><tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">Loading…</td></tr></tbody></table>
</div>
<script>
let trAll=[], trType='internal', trAdm=[], trBeds=[];
const trUrg={routine:['#e5e7eb','#374151'],urgent:['#fef3c7','#92400e'],emergency:['#fee2e2','#991b1b']};
const trSt={requested:['#fef3c7','#92400e','awaiting approval'],approved:['#d1fae5','#065f46','approved'],prepared:['#dbeafe','#1e40af','prepared'],in_transit:['#dbeafe','#1e40af','in transit'],completed:['#d1fae5','#065f46','completed'],rejected:['#fee2e2','#991b1b','rejected'],cancelled:['#e5e7eb','#374151','cancelled'],returned:['#d1fae5','#065f46','patient returned']};
const trPill=(v,m)=>`<span class="wf-pill" style="background:${m[v][0]};color:${m[v][1]};">${wfEsc(m[v][2]||v)}</span>`;
const trPlace=(w,r,b)=>w?`${wfEsc(w)} · Room ${wfEsc(r)} · Bed ${wfEsc(b)}`:'—';
const trLbl=(t,html)=>`<label style="font-size:12px;font-weight:600;display:block;margin:10px 0 4px;">${t}</label>${html}`;
function trTab(v){trType=v;document.querySelectorAll('#tr-seg button').forEach(b=>b.classList.toggle('on',b.dataset.v===v));document.getElementById('tr-th-to').textContent=v==='internal'?'To':'Receiving facility';trRender();}
function trActions(r){
  const b=(l,fn,bg,ic)=>`<button class="wf-btn" onclick="${fn}(${r.id})" style="background:${bg};color:white;margin-left:4px;"><i class="fas ${ic}"></i> ${l}</button>`;
  const cancel=['requested','approved','prepared'].includes(r.status)?`<button class="wf-btn" onclick="trCancel(${r.id})" style="background:white;border:1px solid var(--gray-200);margin-left:4px;">Cancel</button>`:'';
  const dec=r.status==="requested"&&WF_ADMIN?b("Approve","trApprove","#16a34a","fa-check")+b("Reject","trReject","#dc2626","fa-times"):"";
  const main={requested:dec,approved:b('Prepare','trPrepare','var(--primary-color)','fa-clipboard-check'),prepared:b('Send','trDepart','#7c3aed','fa-ambulance'),in_transit:b(r.transfer_type==='internal'?'Confirm receipt':'Complete','trComplete','#16a34a','fa-check'),completed:r.scope==='partial'?b('Patient returned','trReturn','#0d9488','fa-undo'):''}[r.status]||'';
  return main+cancel;
}
function trRender(){
  const q=document.getElementById('tr-search').value.trim().toLowerCase();
  const rows=trAll.filter(r=>r.transfer_type===trType&&(!q||(r.patient_name+' '+r.patient_no+' '+r.transfer_no).toLowerCase().includes(q)));
  document.getElementById('tr-body').innerHTML=rows.length?rows.map(r=>`<tr>
    <td style="font-family:monospace;">${wfEsc(r.transfer_no)}<div style="font-size:11px;color:var(--gray-500);">${wfDate(r.requested_at)}</div></td>
    <td><div style="font-weight:600;">${wfEsc(r.patient_name)}</div><div style="font-size:11px;color:var(--gray-500);">${wfEsc(r.patient_no)}</div></td>
    <td>${trPlace(r.from_ward,r.from_room,r.from_bed)}</td>
    <td>${r.transfer_type==='internal'?trPlace(r.to_ward,r.to_room,r.to_bed):wfEsc(r.to_facility||'—')+(r.facility_accepted==1?'<div style="font-size:11px;color:#065f46;">Accepted by facility</div>':'')}</td>
    <td>${trPill(r.urgency,Object.fromEntries(Object.entries(trUrg).map(([k,v])=>[k,[v[0],v[1],k]])))}</td>
    <td>${trPill(r.status,trSt)}${r.scope==='partial'?'<div style="font-size:11px;color:var(--gray-500);">Partial'+(r.status==='completed'?' - patient is out':'')+'</div>':''}${r.decision_note?`<div style="font-size:11px;color:var(--gray-500);">${wfEsc(r.decision_note)}</div>`:''}${r.invoice_no?`<div style="font-size:11px;color:var(--gray-500);">Invoice ${wfEsc(r.invoice_no)}</div>`:''}</td>
    <td style="text-align:right;white-space:nowrap;">${trActions(r)}</td></tr>`).join('')
    :`<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">No ${trType} transfers yet.</td></tr>`;
  const today=new Date().toISOString().slice(0,10);
  document.getElementById('tr-s-wait').textContent=trAll.filter(r=>r.status==='requested').length;
  document.getElementById('tr-s-open').textContent=trAll.filter(r=>['approved','prepared','in_transit'].includes(r.status)).length;
  document.getElementById('tr-s-done').textContent=trAll.filter(r=>r.status==='completed'&&String(r.completed_at).slice(0,10)===today).length;
}
async function trLoad(){
  const j=await wfGet(WF+'transfer_list');
  if(!j.success){document.getElementById('tr-body').innerHTML=`<tr><td colspan="7" style="padding:30px;text-align:center;color:#b91c1c;">${wfEsc(j.message)}</td></tr>`;return;}
  trAll=j.data; trRender();
}
async function trNew(){
  const [a,b]=await Promise.all([wfGet(WARD+'active_admissions'),wfGet(WARD+'available_beds')]);
  trAdm=a.success?a.data:[]; trBeds=b.success?b.data:[];
  if(!trAdm.length){alert('No admitted patients to transfer.');return;}
  const ext=trType==='external', groups={};
  trBeds.forEach(x=>{(groups[x.ward_name]=groups[x.ward_name]||[]).push(x);});
  const beds=Object.keys(groups).map(w=>`<optgroup label="${wfEsc(w)}">${groups[w].map(x=>`<option value="${x.id}">Room ${wfEsc(x.room_number)} (${wfEsc(x.room_type||'')}) · Bed ${wfEsc(x.bed_number)}</option>`).join('')}</optgroup>`).join('');
  wfDialog(`<h3 style="font-size:17px;font-weight:600;">New ${trType} transfer</h3><div id="tr-err" class="wf-err" style="margin-top:10px;"></div>
    ${trLbl('Patient',`<select id="tr-pt" class="wf-in" style="width:100%;">${trAdm.map(r=>`<option value="${r.id}">${wfEsc(r.patient_name)} (${wfEsc(r.patient_no)}) — ${wfEsc(r.ward_name)} · Bed ${wfEsc(r.bed_number)}</option>`).join('')}</select>`)}
    ${trLbl('Urgency','<select id="tr-urg" class="wf-in" style="width:100%;"><option value="routine">Routine</option><option value="urgent">Urgent</option><option value="emergency">Emergency</option></select>')}
    ${trLbl('Reason for transfer *','<textarea id="tr-reason" rows="2" class="wf-in" style="width:100%;resize:vertical;"></textarea>')}
    ${trLbl('Patient condition / stabilisation needed','<textarea id="tr-cond" rows="2" class="wf-in" style="width:100%;resize:vertical;"></textarea>')}
    ${ext?trLbl('Receiving facility *','<input id="tr-fac" class="wf-in" style="width:100%;" placeholder="Hospital / clinic / centre">')
         :trLbl('Destination bed *',trBeds.length?`<select id="tr-bed" class="wf-in" style="width:100%;">${beds}</select>`:'<div style="padding:10px;background:#fef3c7;color:#92400e;border-radius:6px;font-size:13px;">No free bed at the moment.</div>')}
    ${ext?trLbl('Type of transfer','<select id="tr-scope" class="wf-in" style="width:100%;"><option value="complete">Complete - the patient leaves and is discharged</option><option value="partial">Partial - the patient goes for a service (scan, dialysis...) and comes back; the bed stays reserved</option></select>'):''}
    ${trLbl('Transport',`<input id="tr-trans" class="wf-in" style="width:100%;" placeholder="${ext?'Ambulance plate / driver':'Trolley, wheelchair, bed'}">`)}
    ${ext?trLbl('Oxygen / monitoring needed during transport','<input id="tr-oxy" class="wf-in" style="width:100%;" placeholder="e.g. oxygen 4 L/min, cardiac monitor, IV line">')
         +trLbl('Consent given by (patient or guardian) *','<input id="tr-consent" class="wf-in" style="width:100%;" placeholder="Name and relationship">')
         +trLbl('Documents sent with the patient','<input id="tr-docs" class="wf-in" style="width:100%;" placeholder="Referral letter, results, imaging">'):''}
    ${trLbl('Accompanied by','<input id="tr-acc" class="wf-in" style="width:100%;" placeholder="Nurse, doctor or caregiver">')}
    ${ext?trLbl('Ambulance distance (km) — charged from the tariff "Ambulance per 1km"','<input id="tr-km" type="number" min="0" value="0" class="wf-in" style="width:120px;">'):''}
    <div style="display:flex;gap:10px;margin-top:16px;">
      <button class="wf-btn" id="tr-save" ${!ext&&!trBeds.length?'disabled':''} onclick="trSave()" style="flex:1;background:var(--primary-color);color:white;font-weight:600;">Request transfer</button>
      <button class="wf-btn" onclick="wfClose()" style="background:white;border:1px solid var(--gray-200);">Cancel</button></div>`);
}
async function trSave(){
  const v=id=>(document.getElementById(id)||{}).value||'', err=document.getElementById('tr-err'), btn=document.getElementById('tr-save');
  btn.disabled=true; err.style.display='none';
  const j=await wfPost(WF+'transfer_create',{type:trType,assignment_id:+v('tr-pt'),urgency:v('tr-urg'),reason:v('tr-reason'),clinical_condition:v('tr-cond'),to_bed_id:+v('tr-bed')||0,to_facility:v('tr-fac'),scope:v('tr-scope'),
    transport:v('tr-trans'),accompanied_by:v('tr-acc'),km:+v('tr-km')||0,oxygen_monitoring:v('tr-oxy'),consent_by:v('tr-consent'),referral_documents:v('tr-docs')});
  if(!j.success){err.textContent=j.message||'Failed';err.style.display='block';btn.disabled=false;return;}
  wfClose(); alert('✓ '+j.message); trLoad();
}
async function trStep(id,step,extra){
  const j=await wfPost(WF+'transfer_step',Object.assign({id,step},extra||{}));
  if(!j.success){alert(j.message||'Failed');return false;}
  if(step==='complete') alert('✓ '+j.message);
  wfClose(); trLoad(); return true;
}
async function trDecide(id,decision){ const note=decision==="reject"?(prompt("Reason for rejection")||""):""; if(decision==="reject"&&!note.trim()) return; const j=await wfPost(WF+"transfer_decide",{id,decision,note}); if(!j.success) alert(j.message); trLoad(); }
const trApprove=id=>trDecide(id,"approve"), trReject=id=>trDecide(id,"reject");
async function trCancel(id){ if(!confirm('Cancel this transfer?')) return; const j=await wfPost(WF+'transfer_cancel',{id}); if(!j.success) alert(j.message); trLoad(); }
async function trPrepare(id){
  const r=trAll.find(x=>+x.id===+id), ext=r.transfer_type==='external', costs=ext?await trCostsOf(id):[];
  wfDialog(`<h3 style="font-size:17px;font-weight:600;">Prepare ${wfEsc(r.transfer_no)}</h3><p style="font-size:13px;color:var(--gray-500);margin:4px 0 0;">${wfEsc(r.patient_name)}. Confirm the patient is ready and write the handover.</p>
    ${trLbl('Handover note (diagnosis, medication given, vitals, pending results)',`<textarea id="tp-note" rows="4" class="wf-in" style="width:100%;resize:vertical;">${wfEsc(r.handover_note||'')}</textarea>`)}
    ${trLbl('Transport',`<input id="tp-trans" class="wf-in" style="width:100%;" value="${wfEsc(r.transport||'')}">`)}
    ${trLbl('Accompanied by',`<input id="tp-acc" class="wf-in" style="width:100%;" value="${wfEsc(r.accompanied_by||'')}">`)}
    ${ext?'<label style="display:flex;gap:8px;align-items:center;margin-top:12px;font-size:13px;"><input type="checkbox" id="tp-acc-ok"> The receiving facility has accepted the patient</label>':''}
    ${ext?trCostBlock(costs):''}
    <div style="display:flex;gap:10px;margin-top:16px;"><button class="wf-btn" onclick="trStep(${r.id},'prepare',{handover_note:document.getElementById('tp-note').value,transport:document.getElementById('tp-trans').value,accompanied_by:document.getElementById('tp-acc').value,facility_accepted:${ext}&&document.getElementById('tp-acc-ok').checked${ext?',costs:trCostRows()':''}})" style="flex:1;background:var(--primary-color);color:white;font-weight:600;">Mark prepared</button>
    <button class="wf-btn" onclick="wfClose()" style="background:white;border:1px solid var(--gray-200);">Close</button></div>`);
}
// ---- Extra cost lines (oxygen, escort, ...) edited at Prepare and Complete. Catalog line = tariff price of the patient; free text = price typed here.
const trCostRow=c=>`<div class="tr-cost-row" style="display:grid;grid-template-columns:1fr 64px 96px auto;gap:6px;margin-bottom:6px;">
  <input class="wf-in tr-c-desc" list="tr-c-list" value="${wfEsc(c.description||'')}" data-sid="${+c.service_id||''}" placeholder="Catalog line or free text" oninput="trCostSearch(this)">
  <input class="wf-in tr-c-qty" type="number" min="1" value="${+c.quantity||1}" title="Quantity">
  <input class="wf-in tr-c-price" type="number" min="0" value="${+c.unit_price||''}" placeholder="Price" title="Unit price; leave empty for a catalog line to use the patient's tariff">
  <button class="wf-btn" type="button" onclick="this.closest('.tr-cost-row').remove()" style="background:white;border:1px solid var(--gray-200);">&times;</button></div>`;
let trCostMap={}, trCostTimer;
function trCostSearch(inp){
  inp.dataset.sid=trCostMap[inp.value]||'';
  clearTimeout(trCostTimer);
  if(inp.value.trim().length<2) return;
  trCostTimer=setTimeout(async()=>{
    const j=await wfGet(WF+'cost_services&q='+encodeURIComponent(inp.value.trim()));
    if(!j.success) return;
    j.data.forEach(x=>{trCostMap[x.procedure_name]=x.id;});
    document.getElementById('tr-c-list').innerHTML=j.data.map(x=>`<option value="${wfEsc(x.procedure_name)}">`).join('');
    inp.dataset.sid=trCostMap[inp.value]||'';
  },250);
}
function trCostBlock(rows){
  return `<label style="font-size:12px;font-weight:600;display:block;margin:12px 0 4px;">Extra costs (oxygen, escort, ...)</label>
    <datalist id="tr-c-list"></datalist><div id="tr-costs">${rows.map(trCostRow).join('')}</div>
    <button class="wf-btn" type="button" onclick="document.getElementById('tr-costs').insertAdjacentHTML('beforeend',trCostRow({}))" style="background:white;border:1px solid var(--gray-200);"><i class="fas fa-plus"></i> Add cost line</button>
    <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">A catalog line is billed at the patient's tariff (leave the price empty). A free-text line needs a price. All lines are invoiced when the transfer is completed.</div>`;
}
const trCostRows=()=>[...document.querySelectorAll('#tr-costs .tr-cost-row')].map(r=>({description:r.querySelector('.tr-c-desc').value.trim(),service_id:+r.querySelector('.tr-c-desc').dataset.sid||0,
  quantity:+r.querySelector('.tr-c-qty').value||1,unit_price:+r.querySelector('.tr-c-price').value||0})).filter(c=>c.description);
async function trCostsOf(id){const j=await wfGet(WF+'transfer_costs&id='+id);return j.success?j.data:[];}
function trReturn(id){ if(confirm('The patient is back in the ward?')) trStep(id,'return'); }
function trDepart(id){ if(confirm('The patient leaves now?')) trStep(id,'depart'); }
async function trComplete(id){
  const r=trAll.find(x=>+x.id===+id), ext=r.transfer_type==='external', costs=ext?await trCostsOf(id):[];
  wfDialog(`<h3 style="font-size:17px;font-weight:600;">${ext?'Complete':'Confirm receipt of'} ${wfEsc(r.transfer_no)}</h3>
    <p style="font-size:13px;color:var(--gray-500);margin:4px 0 0;">${ext?'The patient is handed over to '+wfEsc(r.to_facility)+'.'+(r.scope==='partial'?' This is a partial transfer: the bed and the visit stay open until the patient returns.':' The bed is released for cleaning and the visit is closed.'):'The patient has arrived in '+trPlace(r.to_ward,r.to_room,r.to_bed)+'. The old bed is freed and the new bed is taken.'}${r.service_name?' The ambulance charge ('+(+r.charge_qty)+' km) and any extra costs are added to an invoice.':''}</p>
    ${trLbl('Handover confirmation note',`<textarea id="tc-note" rows="3" class="wf-in" style="width:100%;resize:vertical;"></textarea>`)}
    ${ext?trCostBlock(costs):''}
    <div style="display:flex;gap:10px;margin-top:16px;"><button class="wf-btn" onclick="trStep(${r.id},'complete',{handover_note:document.getElementById('tc-note').value${ext?',costs:trCostRows()':''}})" style="flex:1;background:#16a34a;color:white;font-weight:600;">${ext?'Complete transfer':'Confirm receipt'}</button>
    <button class="wf-btn" onclick="wfClose()" style="background:white;border:1px solid var(--gray-200);">Close</button></div>`);
}
trLoad();
</script>

<?php else: ?>
<!-- ====== VISIT CLOSURE ====== -->
<div style="margin-bottom:18px;">
  <h2 style="font-size:24px;font-weight:600;">Visit Closure</h2>
  <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Close a visit once the patient has left. A visit cannot be closed while the patient is still in a bed or an invoice is unpaid.</p>
</div>
<?php require_once __DIR__ . '/includes/visit_tabs.php'; visit_tabs($pdo, 'closure'); ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px;">
  <div class="wf-card"><b id="cl-s-open" style="color:var(--primary-color);">—</b><span>Visits still open</span></div>
  <div class="wf-card"><b id="cl-s-ready" style="color:var(--success-color);">—</b><span>Ready to close</span></div>
  <div class="wf-card"><b id="cl-s-unpaid" style="color:var(--danger-color);">—</b><span>Waiting for payment</span></div>
  <div class="wf-card"><b id="cl-s-admit" style="color:#b45309;">—</b><span>Still in a bed</span></div>
</div>
<div class="wf-box">
  <div style="padding:14px 16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
    <div class="wf-seg" id="cl-view"><button class="on" data-v="open" onclick="clView('open')">Open visits</button><button data-v="closed" onclick="clView('closed')">Closed (last 30 days)</button></div>
    <label style="display:flex;gap:6px;align-items:center;font-size:13px;color:var(--gray-600);"><input type="checkbox" id="cl-ready" onchange="clRender()"> Only ready to close</label>
    <input id="cl-search" class="wf-in" placeholder="Patient, number or visit no." style="width:230px;margin-left:auto;" onkeydown="if(event.key==='Enter')clLoad()">
    <button class="wf-btn" onclick="clLoad()" style="background:var(--primary-color);color:white;"><i class="fas fa-search"></i> Search</button>
  </div>
  <table class="wf-tbl"><thead><tr><th>Visit</th><th>Patient</th><th>Doctor / department</th><th>Checks</th><th></th></tr></thead><tbody id="cl-body"><tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);">Loading…</td></tr></tbody></table>
</div>
<script>
let clRows=[], clMode='open';
const clBlocked=r=>+r.admitted>0||+r.unpaid>0;
const clPill=(t,bg,fg)=>`<span class="wf-pill" style="background:${bg};color:${fg};">${t}</span>`;
function clView(v){clMode=v;document.querySelectorAll('#cl-view button').forEach(b=>b.classList.toggle('on',b.dataset.v===v));clLoad();}
function clChecks(r){
  if(clMode==='closed') return clPill('Closed '+wfDate(r.completed_at),'#f3f4f6','#374151')+(r.closed_by_name?`<div style="font-size:11px;color:var(--gray-500);">by ${wfEsc(r.closed_by_name)}</div>`:'');
  let h='';
  if(+r.admitted>0) h+=clPill('Still in a bed','#fef3c7','#92400e');
  if(+r.unpaid>0) h+=clPill('Unpaid '+Number(r.unpaid).toLocaleString()+' RWF','#fee2e2','#991b1b');
  if(+r.pending_orders>0) h+=clPill(r.pending_orders+' unfinished order(s)','#dbeafe','#1e40af');
  if(!clBlocked(r)) h=clPill('Ready to close','#d1fae5','#065f46')+h;
  if(!+r.invoices) h+=clPill('No invoice','#f3f4f6','#6b7280');
  return h;
}
function clRender(){
  const onlyReady=document.getElementById('cl-ready').checked&&clMode==='open';
  const rows=clRows.filter(r=>!onlyReady||!clBlocked(r));
  document.getElementById('cl-body').innerHTML=rows.length?rows.map(r=>`<tr>
    <td style="white-space:nowrap;"><div style="font-weight:600;">${wfEsc(r.visit_no)}</div><div style="font-size:11px;color:var(--gray-500);">${wfEsc(r.visit_date)} · ${wfEsc(r.visit_type||'')}</div></td>
    <td><div style="font-weight:600;">${wfEsc(r.patient_name)}</div><div style="font-size:11px;color:var(--gray-500);">${wfEsc(r.patient_no)} · ${wfEsc(r.payment_type||'')}</div></td>
    <td>${wfEsc(r.doctor_name||'—')}<div style="font-size:11px;color:var(--gray-500);">${wfEsc(r.department||'')}</div></td>
    <td>${clChecks(r)}</td>
    <td style="text-align:right;">${clMode==='open'?`<button class="wf-btn" ${clBlocked(r)?'disabled title="Resolve the checks first"':''} style="background:var(--success-color);color:white;" onclick="clAsk(${r.id})"><i class="fas fa-clipboard-check"></i> Close visit</button>`:''}</td></tr>`).join('')
    :'<tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-400);">Nothing here.</td></tr>';
}
function clAsk(id){
  const r=clRows.find(x=>+x.id===+id);
  wfDialog(`<h3 style="font-size:17px;font-weight:600;margin-bottom:4px;">Close visit ${wfEsc(r.visit_no)}</h3>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:12px;">${wfEsc(r.patient_name)} · ${wfEsc(r.patient_no)}</p>
    <div id="cl-err" class="wf-err"></div>
    ${+r.pending_orders>0?`<div style="padding:10px;background:#dbeafe;color:#1e40af;border-radius:6px;font-size:13px;margin-bottom:12px;">${r.pending_orders} lab / imaging / procedure order(s) are not finished. They stay open after the visit is closed.</div>`:''}
    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Note (optional)</label>
    <textarea id="cl-note" rows="2" class="wf-in" style="width:100%;resize:vertical;"></textarea>
    <div style="display:flex;gap:10px;margin-top:16px;">
      <button class="wf-btn" id="cl-go" onclick="clClose(${r.id},${+r.pending_orders>0})" style="flex:1;background:var(--success-color);color:white;font-weight:600;">Close visit</button>
      <button class="wf-btn" onclick="wfClose()" style="background:white;border:1px solid var(--gray-200);">Cancel</button></div>`);
}
async function clClose(id,confirmPending){
  const btn=document.getElementById('cl-go'), err=document.getElementById('cl-err'); btn.disabled=true; err.style.display='none';
  const j=await wfPost(WF+'closure_close',{visit_id:id,confirm_pending:confirmPending,note:document.getElementById('cl-note').value.trim()});
  if(!j.success){err.textContent=j.message||'Could not close the visit';err.style.display='block';btn.disabled=false;return;}
  wfClose(); clLoad();
}
async function clLoad(){
  const j=await wfGet(WF+'closure_list&view='+clMode+'&search='+encodeURIComponent(document.getElementById('cl-search').value.trim()));
  if(!j.success){document.getElementById('cl-body').innerHTML=`<tr><td colspan="5" style="padding:30px;text-align:center;color:#b91c1c;">${wfEsc(j.message)}</td></tr>`;return;}
  clRows=j.data; clRender();
  if(clMode==='open'){
    document.getElementById('cl-s-open').textContent=clRows.length;
    document.getElementById('cl-s-ready').textContent=clRows.filter(r=>!clBlocked(r)).length;
    document.getElementById('cl-s-unpaid').textContent=clRows.filter(r=>+r.unpaid>0).length;
    document.getElementById('cl-s-admit').textContent=clRows.filter(r=>+r.admitted>0).length;
  }
}
clLoad();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
