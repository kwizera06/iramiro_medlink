// shifts.js — Shift & Duty Roster Module
const _shBasePath = (typeof _apiBase !== 'undefined' && _apiBase)
  ? _apiBase.replace('/api.php', '')
  : (window.location.origin + (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || ''));
const shAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=hr/shifts' : _shBasePath + '/api.php?endpoint=hr/shifts';
const shEmpAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=hr/employees' : _shBasePath + '/api.php?endpoint=hr/employees';
const shDeptAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=hr/departments' : _shBasePath + '/api.php?endpoint=hr/departments';
let rtPage=1,asPage=1;
function sEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function shTab(t){
  ['types','rosters','assignments'].forEach(x=>{
    document.getElementById('sh-panel-'+x).style.display=x===t?'block':'none';
    const btn=document.getElementById('sh-tab-'+x);
    btn.style.background=x===t?'var(--primary-color)':'transparent';
    btn.style.color=x===t?'white':'var(--gray-600)';
  });
  if(t==='types') stLoad();
  if(t==='rosters') rtLoad();
  if(t==='assignments') asLoad();
}

// ==================== SHIFT TYPES ====================
async function stLoad(){
  try{
    const r=await fetch(shAPI+'&entity=shift-types');const j=await r.json();
    if(!j.success) return;
    const{shift_types,stats}=j.data;
    document.getElementById('st-total').textContent=stats.total||0;
    document.getElementById('st-active').textContent=stats.active||0;
    document.getElementById('st-night').textContent=stats.night_shifts||0;
    const tb=document.getElementById('st-tbody');
    if(!shift_types.length){tb.innerHTML='<tr><td colspan="8" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-clock" style="font-size:36px;display:block;margin-bottom:8px;"></i>No shift types defined.</td></tr>';return;}
    const catColors={regular:'var(--primary-color)',night:'#8b5cf6',emergency:'var(--danger-color)','on-call':'var(--warning-color)',weekend:'#f97316',holiday:'#ec4899'};
    tb.innerHTML=shift_types.map(s=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 14px;font-weight:600;">${sEsc(s.name)}</td>
      <td style="padding:10px 14px;font-family:monospace;color:var(--primary-color);">${sEsc(s.code)}</td>
      <td style="padding:10px 14px;">${s.start_time} — ${s.end_time}</td>
      <td style="padding:10px 14px;text-align:center;font-weight:700;">${s.hours}h</td>
      <td style="padding:10px 14px;"><span style="padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:${catColors[s.category]||'#ccc'}20;color:${catColors[s.category]||'#555'};">${s.category}</span></td>
      <td style="padding:10px 14px;text-align:right;">${Number(s.allowance_rate).toLocaleString()} RWF</td>
      <td style="padding:10px 14px;text-align:center;"><span style="padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:${s.is_active==1?'var(--success-color)':'var(--danger-color)'}20;color:${s.is_active==1?'var(--success-color)':'var(--danger-color)'};">${s.is_active==1?'Active':'Inactive'}</span></td>
      <td style="padding:10px 14px;text-align:center;">
        <button onclick="stEdit(${s.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:3px;"><i class="fas fa-edit"></i></button>
        <button onclick="stDel(${s.id},'${sEsc(s.name)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:3px;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
  }catch(e){document.getElementById('st-tbody').innerHTML='<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--danger-color);">Error</td></tr>';}
}

function stOpenModal(d=null){
  const h=`<div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" id="st-modal">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:520px;position:relative;">
    <button onclick="document.getElementById('st-modal').remove()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;">${d?'Edit':'Add'} Shift Type</h3>
    <div id="st-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Name *</label><input id="st-name" value="${sEsc(d?.name||'')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Code *</label><input id="st-code" value="${sEsc(d?.code||'')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;" placeholder="e.g. MRN, NGT"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Start Time *</label><input id="st-start" type="time" value="${d?.start_time||'07:00'}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">End Time *</label><input id="st-end" type="time" value="${d?.end_time||'15:00'}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Hours *</label><input id="st-hours" type="number" min="0" step="0.5" value="${d?.hours||8}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Category</label><select id="st-cat" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
        <option value="regular" ${d?.category==='regular'?'selected':''}>Regular</option><option value="night" ${d?.category==='night'?'selected':''}>Night</option><option value="emergency" ${d?.category==='emergency'?'selected':''}>Emergency</option><option value="on-call" ${d?.category==='on-call'?'selected':''}>On-Call</option><option value="weekend" ${d?.category==='weekend'?'selected':''}>Weekend</option><option value="holiday" ${d?.category==='holiday'?'selected':''}>Holiday</option></select></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Allowance (RWF)</label><input id="st-allowance" type="number" min="0" value="${d?.allowance_rate||0}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Active</label><select id="st-active" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="1" ${d?.is_active!=0?'selected':''}>Yes</option><option value="0" ${d?.is_active==0?'selected':''}>No</option></select></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button onclick="stSubmit(${d?.id||'null'})" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> ${d?'Update':'Save'}</button>
      <button onclick="document.getElementById('st-modal').remove()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div>
  </div></div>`;
  document.body.insertAdjacentHTML('beforeend',h);
}
async function stEdit(id){
  try{const r=await fetch(shAPI+'&entity=shift-types&id='+id);const j=await r.json();if(j.success)stOpenModal(j.data);}catch(e){alert('Load failed');}
}
async function stDel(id,name){if(!confirm(`Delete shift type "${name}"?`))return;try{const r=await fetch(shAPI+'&entity=shift-types&id='+id,{method:'DELETE'});const j=await r.json();j.success?stLoad():alert(j.message);}catch(e){alert('Failed');}}
async function stSubmit(id){
  const body={name:document.getElementById('st-name').value.trim(),code:document.getElementById('st-code').value.trim(),start_time:document.getElementById('st-start').value,end_time:document.getElementById('st-end').value,hours:document.getElementById('st-hours').value,category:document.getElementById('st-cat').value,allowance_rate:document.getElementById('st-allowance').value,is_active:document.getElementById('st-active').value};
  try{const r=await fetch(id?shAPI+'&entity=shift-types&id='+id:shAPI+'&entity=shift-types',{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){document.getElementById('st-modal').remove();stLoad();}else{document.getElementById('st-alert').textContent=j.message;document.getElementById('st-alert').style.display='block';}}catch(e){alert('Failed');}
}

// ==================== DUTY ROSTERS ====================
async function rtLoad(){
  const statusF=document.getElementById('rt-filter-status').value;
  const p=new URLSearchParams({page:rtPage,limit:20});
  if(statusF) p.append('status',statusF);
  try{
    const r=await fetch(shAPI+'&entity=rosters&'+p);const j=await r.json();if(!j.success) return;
    const{rosters,stats,pagination}=j.data;
    document.getElementById('rt-total').textContent=stats.total||0;
    document.getElementById('rt-active').textContent=stats.active||0;
    document.getElementById('rt-draft').textContent=stats.draft||0;
    document.getElementById('rt-completed').textContent=stats.completed||0;
    const tb=document.getElementById('rt-tbody');
    const stColors={draft:'var(--warning-color)',published:'var(--primary-color)',active:'var(--success-color)',completed:'var(--gray-500)'};
    if(!rosters.length){tb.innerHTML='<tr><td colspan="6" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-calendar-alt" style="font-size:36px;display:block;margin-bottom:8px;"></i>No rosters found.</td></tr>';return;}
    tb.innerHTML=rosters.map(r=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 14px;font-weight:600;">${sEsc(r.roster_name)}</td>
      <td style="padding:10px 14px;">${sEsc(r.dept_name||'—')}</td>
      <td style="padding:10px 14px;font-size:13px;">${r.start_date} → ${r.end_date}</td>
      <td style="padding:10px 14px;"><span style="padding:2px 8px;border-radius:12px;font-size:12px;background:var(--gray-100);">${sEsc(r.roster_type)}</span></td>
      <td style="padding:10px 14px;text-align:center;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${stColors[r.status]||'#ccc'}20;color:${stColors[r.status]||'#555'};">${r.status}</span></td>
      <td style="padding:10px 14px;text-align:center;">
        <button onclick="rtEdit(${r.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:3px;"><i class="fas fa-edit"></i></button>
        <button onclick="rtDel(${r.id})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:3px;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
    const pg=document.getElementById('rt-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="rtPage=${i+1};rtLoad()" style="padding:5px 10px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;">${i+1}</button>`).join(''):'';
  }catch(e){document.getElementById('rt-tbody').innerHTML='<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--danger-color);">Error</td></tr>';}
}

function rtOpenModal(d=null){
  const h=`<div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" id="rt-modal">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:540px;position:relative;">
    <button onclick="document.getElementById('rt-modal').remove()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;">${d?'Edit':'New'} Duty Roster</h3>
    <div id="rt-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Roster Name *</label><input id="rt-name" value="${sEsc(d?.roster_name||'')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Department</label><select id="rt-dept" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Select</option></select></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Type</label><select id="rt-type" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
        <option value="weekly" ${d?.roster_type==='weekly'?'selected':''}>Weekly</option><option value="biweekly" ${d?.roster_type==='biweekly'?'selected':''}>Biweekly</option><option value="monthly" ${d?.roster_type==='monthly'||!d?'selected':''}>Monthly</option><option value="rotation" ${d?.roster_type==='rotation'?'selected':''}>Rotation</option><option value="custom" ${d?.roster_type==='custom'?'selected':''}>Custom</option></select></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Start Date *</label><input id="rt-start" type="date" value="${d?.start_date||''}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">End Date *</label><input id="rt-end" type="date" value="${d?.end_date||''}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Status</label><select id="rt-status" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
        <option value="draft" ${d?.status==='draft'||!d?'selected':''}>Draft</option><option value="published" ${d?.status==='published'?'selected':''}>Published</option><option value="active" ${d?.status==='active'?'selected':''}>Active</option><option value="completed" ${d?.status==='completed'?'selected':''}>Completed</option></select></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Unit</label><input id="rt-unit" value="${sEsc(d?.unit_name||'')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Notes</label><textarea id="rt-notes" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;">${sEsc(d?.notes||'')}</textarea></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button onclick="rtSubmit(${d?.id||'null'})" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> ${d?'Update':'Create'}</button>
      <button onclick="document.getElementById('rt-modal').remove()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div>
  </div></div>`;
  document.body.insertAdjacentHTML('beforeend',h);
  // Load depts
  fetch(shDeptAPI).then(r=>r.json()).then(j=>{if(j.success){const sel=document.getElementById('rt-dept');sel.innerHTML='<option value="">Select</option>'+j.data.map(x=>`<option value="${x.id}" ${d&&d.department_id==x.id?'selected':''}>${sEsc(x.name)}</option>`).join('');}});
}
async function rtEdit(id){try{const r=await fetch(shAPI+'&entity=rosters&id='+id);const j=await r.json();if(j.success)rtOpenModal(j.data);}catch(e){alert('Load failed');}}
async function rtDel(id){if(!confirm('Delete this roster?'))return;try{const r=await fetch(shAPI+'&entity=rosters&id='+id,{method:'DELETE'});const j=await r.json();j.success?rtLoad():alert(j.message);}catch(e){alert('Failed');}}
async function rtSubmit(id){
  const body={roster_name:document.getElementById('rt-name').value.trim(),department_id:document.getElementById('rt-dept').value,unit_name:document.getElementById('rt-unit').value.trim(),start_date:document.getElementById('rt-start').value,end_date:document.getElementById('rt-end').value,roster_type:document.getElementById('rt-type').value,status:document.getElementById('rt-status').value,notes:document.getElementById('rt-notes').value.trim()};
  try{const r=await fetch(id?shAPI+'&entity=rosters&id='+id:shAPI+'&entity=rosters',{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){document.getElementById('rt-modal').remove();rtLoad();}else{document.getElementById('rt-alert').textContent=j.message;document.getElementById('rt-alert').style.display='block';}}catch(e){alert('Failed');}
}

// ==================== ASSIGNMENTS ====================
async function asLoad(){
  const rosterF=document.getElementById('as-filter-roster').value;
  const statusF=document.getElementById('as-filter-status').value;
  const dateF=document.getElementById('as-filter-date').value;
  const p=new URLSearchParams({page:asPage,limit:30});
  if(rosterF) p.append('roster_id',rosterF);
  if(statusF) p.append('status',statusF);
  if(dateF) p.append('shift_date',dateF);
  try{
    const r=await fetch(shAPI+'&entity=assignments&'+p);const j=await r.json();if(!j.success) return;
    const{assignments,stats,pagination}=j.data;
    document.getElementById('as-total').textContent=stats.total||0;
    document.getElementById('as-scheduled').textContent=stats.scheduled||0;
    document.getElementById('as-completed').textContent=stats.completed||0;
    document.getElementById('as-absent').textContent=stats.absent||0;
    const tb=document.getElementById('as-tbody');
    const stC={scheduled:'var(--warning-color)',confirmed:'var(--primary-color)',completed:'var(--success-color)',absent:'var(--danger-color)',cancelled:'var(--gray-400)',swapped:'#8b5cf6'};
    if(!assignments.length){tb.innerHTML='<tr><td colspan="8" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-user-clock" style="font-size:36px;display:block;margin-bottom:8px;"></i>No assignments found.</td></tr>';return;}
    tb.innerHTML=assignments.map(a=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 14px;"><div style="font-weight:600;">${sEsc(a.first_name)} ${sEsc(a.last_name)}</div><div style="font-size:11px;color:var(--gray-500);">${sEsc(a.employee_no)}</div></td>
      <td style="padding:10px 14px;font-size:13px;">${sEsc(a.roster_name)}</td>
      <td style="padding:10px 14px;"><span style="font-weight:600;">${sEsc(a.shift_name)}</span> <span style="font-size:11px;color:var(--gray-500);">(${sEsc(a.shift_code)})</span></td>
      <td style="padding:10px 14px;font-size:13px;">${a.shift_date}</td>
      <td style="padding:10px 14px;font-size:13px;">${a.shift_start} — ${a.shift_end}</td>
      <td style="padding:10px 14px;text-align:center;">${a.is_overtime==1?'<span style="color:var(--warning-color);font-weight:700;">Yes</span>':'—'}</td>
      <td style="padding:10px 14px;text-align:center;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${stC[a.status]||'#ccc'}20;color:${stC[a.status]||'#555'};">${a.status}</span></td>
      <td style="padding:10px 14px;text-align:center;">
        <button onclick="asEdit(${a.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:3px;"><i class="fas fa-edit"></i></button>
        <button onclick="asDel(${a.id})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:3px;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
    const pg=document.getElementById('as-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="asPage=${i+1};asLoad()" style="padding:5px 10px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;">${i+1}</button>`).join(''):'';
  }catch(e){document.getElementById('as-tbody').innerHTML='<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--danger-color);">Error</td></tr>';}
}

function asOpenModal(d=null){
  const h=`<div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;padding:16px;" id="as-modal">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:540px;position:relative;">
    <button onclick="document.getElementById('as-modal').remove()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;">${d?'Edit':'Assign'} Shift</h3>
    <div id="as-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Employee *</label><select id="as-emp" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Loading...</option></select></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Roster *</label><select id="as-roster" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Loading...</option></select></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Shift Type *</label><select id="as-shift" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Loading...</option></select></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Date *</label><input id="as-date" type="date" value="${d?.shift_date||new Date().toISOString().slice(0,10)}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Location</label><input id="as-loc" value="${sEsc(d?.location||'')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Overtime</label><select id="as-ot" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="0" ${d?.is_overtime!=1?'selected':''}>No</option><option value="1" ${d?.is_overtime==1?'selected':''}>Yes</option></select></div>
      <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Status</label><select id="as-status" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
        <option value="scheduled" ${d?.status==='scheduled'||!d?'selected':''}>Scheduled</option><option value="confirmed" ${d?.status==='confirmed'?'selected':''}>Confirmed</option><option value="completed" ${d?.status==='completed'?'selected':''}>Completed</option><option value="absent" ${d?.status==='absent'?'selected':''}>Absent</option><option value="cancelled" ${d?.status==='cancelled'?'selected':''}>Cancelled</option></select></div>
      <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Notes</label><input id="as-notes" value="${sEsc(d?.notes||'')}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button onclick="asSubmit(${d?.id||'null'})" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> ${d?'Update':'Assign'}</button>
      <button onclick="document.getElementById('as-modal').remove()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div>
  </div></div>`;
  document.body.insertAdjacentHTML('beforeend',h);
  // Load dropdowns
  fetch(shEmpAPI+'&limit=500').then(r=>r.json()).then(j=>{if(j.success){document.getElementById('as-emp').innerHTML='<option value="">Select Employee</option>'+j.data.employees.map(e=>`<option value="${e.id}" ${d&&d.employee_id==e.id?'selected':''}>${sEsc(e.first_name+' '+e.last_name)} (${sEsc(e.employee_no)})</option>`).join('');}});
  fetch(shAPI+'&entity=rosters&limit=100').then(r=>r.json()).then(j=>{if(j.success){document.getElementById('as-roster').innerHTML='<option value="">Select Roster</option>'+j.data.rosters.map(r=>`<option value="${r.id}" ${d&&d.roster_id==r.id?'selected':''}>${sEsc(r.roster_name)}</option>`).join('');}});
  fetch(shAPI+'&entity=shift-types').then(r=>r.json()).then(j=>{if(j.success){document.getElementById('as-shift').innerHTML='<option value="">Select Shift</option>'+j.data.shift_types.map(s=>`<option value="${s.id}" ${d&&d.shift_type_id==s.id?'selected':''}>${sEsc(s.name)} (${s.start_time}-${s.end_time})</option>`).join('');}});
}
async function asEdit(id){try{const r=await fetch(shAPI+'&entity=assignments&id='+id);const j=await r.json();if(j.success)asOpenModal(j.data);}catch(e){alert('Load failed');}}
async function asDel(id){if(!confirm('Delete this assignment?'))return;try{const r=await fetch(shAPI+'&entity=assignments&id='+id,{method:'DELETE'});const j=await r.json();j.success?asLoad():alert(j.message);}catch(e){alert('Failed');}}
async function asSubmit(id){
  const body={roster_id:document.getElementById('as-roster').value,employee_id:document.getElementById('as-emp').value,shift_type_id:document.getElementById('as-shift').value,shift_date:document.getElementById('as-date').value,location:document.getElementById('as-loc').value.trim(),is_overtime:document.getElementById('as-ot').value,status:document.getElementById('as-status').value,notes:document.getElementById('as-notes').value.trim()};
  try{const r=await fetch(id?shAPI+'&entity=assignments&id='+id:shAPI+'&entity=assignments',{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){document.getElementById('as-modal').remove();asLoad();}else{document.getElementById('as-alert').textContent=j.message;document.getElementById('as-alert').style.display='block';}}catch(e){alert('Failed');}
}

// Load roster dropdown in assignment filter
async function asInitRosterFilter(){
  try{const r=await fetch(shAPI+'&entity=rosters&limit=100');const j=await r.json();if(j.success){document.getElementById('as-filter-roster').innerHTML='<option value="">All Rosters</option>'+j.data.rosters.map(r=>`<option value="${r.id}">${sEsc(r.roster_name)}</option>`).join('');}}catch(e){}
}

// Init
stLoad();
asInitRosterFilter();
