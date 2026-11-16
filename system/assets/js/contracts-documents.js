// contracts-documents.js — Employee Contracts & Documents Module
const _cdBasePath = (typeof _apiBase !== 'undefined' && _apiBase)
  ? _apiBase.replace('/api.php', '')
  : (window.location.origin + (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || ''));
const cdAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=hr/contracts' : _cdBasePath + '/api.php?endpoint=hr/contracts';
const cdEmpAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=hr/employees' : _cdBasePath + '/api.php?endpoint=hr/employees';
const cdDeptAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=hr/departments' : _cdBasePath + '/api.php?endpoint=hr/departments';
let ctPage=1, dcPage=1;
function xEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function xFmt(n){return Number(n||0).toLocaleString();}

// Tab switching
function cdTab(t){
  ['contracts','documents'].forEach(x=>{
    document.getElementById('cd-panel-'+x).style.display=x===t?'block':'none';
    const btn=document.getElementById('cd-tab-'+x);
    btn.style.background=x===t?'var(--primary-color)':'transparent';
    btn.style.color=x===t?'white':'var(--gray-600)';
  });
  if(t==='contracts') ctLoad();
  if(t==='documents') dcLoad();
}

// Load employees + departments for dropdowns
async function cdInitDropdowns(){
  try{
    const r=await fetch(cdEmpAPI+'&limit=500');const j=await r.json();
    if(j.success){
      const opts='<option value="">Select Employee</option>'+j.data.employees.map(e=>`<option value="${e.id}">${xEsc(e.first_name+' '+e.last_name)} (${xEsc(e.employee_no)})</option>`).join('');
      document.getElementById('ct-emp').innerHTML=opts;
      document.getElementById('dc-emp').innerHTML=opts;
    }
  }catch(e){}
  try{
    const r=await fetch(cdDeptAPI);const j=await r.json();
    if(j.success){
      document.getElementById('ct-dept').innerHTML='<option value="">Select</option>'+j.data.map(d=>`<option value="${d.id}">${xEsc(d.name)}</option>`).join('');
    }
  }catch(e){}
}

// ==================== CONTRACTS ====================
async function ctLoad(){
  const typeF=document.getElementById('ct-type-filter').value;
  const statusF=document.getElementById('ct-status-filter').value;
  const p=new URLSearchParams({page:ctPage,limit:20,entity:'contracts'});
  if(typeF) p.append('contract_type',typeF);
  if(statusF!=='') p.append('is_active',statusF);
  try{
    const r=await fetch(cdAPI+'&entity=contracts&'+p);const j=await r.json();
    if(!j.success){ctMsg('Failed: '+j.message);return;}
    const{contracts,stats,pagination}=j.data;
    document.getElementById('ct-total').textContent=stats.total||0;
    document.getElementById('ct-active').textContent=stats.active||0;
    document.getElementById('ct-inactive').textContent=stats.inactive||0;
    document.getElementById('ct-expiring').textContent=stats.expiring_soon||0;
    const tb=document.getElementById('ct-tbody');
    if(!contracts.length){tb.innerHTML='<tr><td colspan="10" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-file-contract" style="font-size:36px;display:block;margin-bottom:8px;"></i>No contracts found.</td></tr>';return;}
    tb.innerHTML=contracts.map(c=>{
      const stColor=c.is_active==1?'var(--success-color)':'var(--danger-color)';
      const stLabel=c.is_active==1?'Active':'Inactive';
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 14px;font-weight:600;font-family:monospace;color:var(--primary-color);">${xEsc(c.contract_number)}</td>
        <td style="padding:10px 14px;"><div style="font-weight:600;">${xEsc(c.first_name)} ${xEsc(c.last_name)}</div><div style="font-size:11px;color:var(--gray-500);">${xEsc(c.employee_no)}</div></td>
        <td style="padding:10px 14px;"><span style="padding:2px 8px;border-radius:12px;font-size:12px;background:var(--gray-100);">${xEsc(c.contract_type)}</span></td>
        <td style="padding:10px 14px;">${xEsc(c.job_title)}</td>
        <td style="padding:10px 14px;font-size:13px;">${c.start_date}</td>
        <td style="padding:10px 14px;font-size:13px;">${c.end_date||'—'}</td>
        <td style="padding:10px 14px;text-align:right;font-weight:600;">${xFmt(c.gross_salary)}</td>
        <td style="padding:10px 14px;text-align:center;">${c.contract_document?`<a href="${_cdBasePath}/${xEsc(c.contract_document)}" target="_blank" style="color:var(--primary-color);"><i class="fas fa-file-pdf" style="font-size:16px;"></i></a>`:'<span style="color:var(--gray-300);">—</span>'}</td>
        <td style="padding:10px 14px;text-align:center;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${stColor}20;color:${stColor};">${stLabel}</span></td>
        <td style="padding:10px 14px;text-align:center;">
          <button onclick="ctVersions(${c.id},'${xEsc(c.contract_number)}')" style="background:none;border:none;color:#8b5cf6;cursor:pointer;font-size:14px;padding:3px;" title="Versions"><i class="fas fa-history"></i></button>
          <button onclick="ctEdit(${c.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:3px;" title="Edit"><i class="fas fa-edit"></i></button>
          <button onclick="ctDel(${c.id})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:3px;" title="Delete"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join('');
    const pg=document.getElementById('ct-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="ctPage=${i+1};ctLoad()" style="padding:5px 10px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;">${i+1}</button>`).join(''):'';
  }catch(e){ctMsg('Error: '+e.message);}
}
function ctMsg(m){document.getElementById('ct-tbody').innerHTML=`<tr><td colspan="10" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}

function ctOpenModal(d=null){
  document.getElementById('ct-id').value=d?d.id:'';
  document.getElementById('ct-emp').value=d?d.employee_id:'';
  document.getElementById('ct-type').value=d?d.contract_type:'permanent';
  document.getElementById('ct-job').value=d?d.job_title:'';
  document.getElementById('ct-start').value=d?d.start_date:'';
  document.getElementById('ct-end').value=d?d.end_date:'';
  document.getElementById('ct-dept').value=d?d.department_id:'';
  document.getElementById('ct-salary').value=d?d.gross_salary:0;
  document.getElementById('ct-location').value=d?d.work_location:'';
  document.getElementById('ct-active').value=d?d.is_active:1;
  document.getElementById('ct-notes').value=d?d.notes:'';
  document.getElementById('ct-reason').value='';
  document.getElementById('ct-file').value='';
  document.getElementById('ct-reason-wrap').style.display=d?'block':'none';
  if(d&&d.contract_document){document.getElementById('ct-current-file').style.display='block';document.getElementById('ct-current-file-name').textContent=d.contract_document.split('/').pop();}
  else{document.getElementById('ct-current-file').style.display='none';}
  document.getElementById('ct-modal-title').textContent=d?'Edit Contract':'New Contract';
  document.getElementById('ct-submit-lbl').textContent=d?'Update':'Save Contract';
  document.getElementById('ct-alert').style.display='none';
  document.getElementById('ct-modal').style.display='flex';
}
function ctCloseModal(){document.getElementById('ct-modal').style.display='none';}

async function ctEdit(id){
  try{const r=await fetch(cdAPI+'&entity=contracts&id='+id);const j=await r.json();if(j.success)ctOpenModal(j.data);}catch(e){alert('Load failed');}
}
async function ctDel(id){
  if(!confirm('Delete this contract and its version history?'))return;
  try{const r=await fetch(cdAPI+'&entity=contracts&id='+id,{method:'DELETE'});const j=await r.json();j.success?ctLoad():alert(j.message);}catch(e){alert('Failed');}
}
async function ctSubmit(e){
  e.preventDefault();const id=document.getElementById('ct-id').value;
  const btn=document.getElementById('ct-submit-btn');const al=document.getElementById('ct-alert');
  al.style.display='none';btn.disabled=true;
  const fd=new FormData();
  if(id) fd.append('_method','PUT');
  fd.append('employee_id',document.getElementById('ct-emp').value);
  fd.append('contract_type',document.getElementById('ct-type').value);
  fd.append('job_title',document.getElementById('ct-job').value.trim());
  fd.append('start_date',document.getElementById('ct-start').value);
  fd.append('end_date',document.getElementById('ct-end').value);
  fd.append('department_id',document.getElementById('ct-dept').value);
  fd.append('gross_salary',document.getElementById('ct-salary').value);
  fd.append('work_location',document.getElementById('ct-location').value.trim());
  fd.append('is_active',document.getElementById('ct-active').value);
  fd.append('notes',document.getElementById('ct-notes').value.trim());
  fd.append('change_reason',document.getElementById('ct-reason').value.trim());
  const f=document.getElementById('ct-file').files[0];
  if(f) fd.append('contract_file',f);
  try{
    const url=id?cdAPI+'&entity=contracts&id='+id:cdAPI+'&entity=contracts';
    const r=await fetch(url,{method:'POST',body:fd});const j=await r.json();
    if(j.success){ctCloseModal();ctLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}
  }catch(err){al.textContent='Request failed';al.style.display='block';}
  finally{btn.disabled=false;}
}
async function ctVersions(id,name){
  document.getElementById('cd-ver-title').innerHTML='<i class="fas fa-history" style="color:#8b5cf6;margin-right:8px;"></i>Contract Versions: '+xEsc(name);
  document.getElementById('cd-ver-content').innerHTML='<p style="text-align:center;color:var(--gray-400);padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
  document.getElementById('cd-ver-modal').style.display='flex';
  try{
    const r=await fetch(cdAPI+'&entity=contracts&id='+id+'&action=versions');const j=await r.json();
    if(!j.success||!j.data.versions.length){document.getElementById('cd-ver-content').innerHTML='<p style="text-align:center;color:var(--gray-400);padding:20px;">No version history</p>';return;}
    document.getElementById('cd-ver-content').innerHTML='<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px 10px;">Ver</th><th style="padding:8px 10px;">Type</th><th style="padding:8px 10px;">Job Title</th><th style="padding:8px 10px;">Salary</th><th style="padding:8px 10px;">Status</th><th style="padding:8px 10px;">Reason</th><th style="padding:8px 10px;">Date</th></tr></thead><tbody>'+j.data.versions.map((v,i)=>`<tr style="border-bottom:1px solid var(--gray-100);${i===0?'background:#f0fdf4;':''}"><td style="padding:8px 10px;font-weight:700;text-align:center;">${i===0?'<span style="background:var(--success-color);color:white;padding:2px 8px;border-radius:10px;font-size:11px;">v'+v.version_number+'</span>':'v'+v.version_number}</td><td style="padding:8px 10px;">${xEsc(v.contract_type)}</td><td style="padding:8px 10px;">${xEsc(v.job_title)}</td><td style="padding:8px 10px;">${xFmt(v.gross_salary)}</td><td style="padding:8px 10px;">${v.is_active==1?'Active':'Inactive'}</td><td style="padding:8px 10px;color:var(--gray-600);">${xEsc(v.change_reason||'—')}</td><td style="padding:8px 10px;font-size:11px;color:var(--gray-500);">${v.created_at}</td></tr>`).join('')+'</tbody></table>';
  }catch(e){document.getElementById('cd-ver-content').innerHTML='<p style="color:var(--danger-color);text-align:center;">Error loading versions</p>';}
}

// ==================== DOCUMENTS ====================
async function dcLoad(){
  const typeF=document.getElementById('dc-type-filter').value;
  const p=new URLSearchParams({page:dcPage,limit:20});
  if(typeF) p.append('document_type',typeF);
  try{
    const r=await fetch(cdAPI+'&entity=documents&'+p);const j=await r.json();
    if(!j.success){dcMsg('Failed');return;}
    const{documents,stats,pagination}=j.data;
    document.getElementById('dc-total').textContent=stats.total||0;
    document.getElementById('dc-diplomas').textContent=stats.diplomas||0;
    document.getElementById('dc-certs').textContent=stats.certificates||0;
    document.getElementById('dc-other').textContent=stats.other||0;
    const tb=document.getElementById('dc-tbody');
    if(!documents.length){tb.innerHTML='<tr><td colspan="8" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-folder-open" style="font-size:36px;display:block;margin-bottom:8px;"></i>No documents found.</td></tr>';return;}
    tb.innerHTML=documents.map(d=>{
      const size=d.file_size?(d.file_size/1024).toFixed(0)+' KB':'—';
      const typeIcons={'Diploma':'fa-graduation-cap','Certificate':'fa-award','ID Copy':'fa-id-card','CV/Resume':'fa-user-tie','Reference Letter':'fa-envelope-open-text'};
      const icon=typeIcons[d.document_type]||'fa-file-alt';
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 14px;"><div style="font-weight:600;">${xEsc(d.first_name)} ${xEsc(d.last_name)}</div><div style="font-size:11px;color:var(--gray-500);">${xEsc(d.employee_no)}</div></td>
        <td style="padding:10px 14px;font-weight:600;">${xEsc(d.document_name)}</td>
        <td style="padding:10px 14px;"><i class="fas ${icon}" style="margin-right:4px;color:#8b5cf6;"></i>${xEsc(d.document_type)}</td>
        <td style="padding:10px 14px;font-size:13px;color:var(--gray-500);">${size}</td>
        <td style="padding:10px 14px;font-size:13px;">${d.created_at?d.created_at.slice(0,10):'—'}</td>
        <td style="padding:10px 14px;text-align:center;font-weight:700;color:#8b5cf6;">v${d.current_version}</td>
        <td style="padding:10px 14px;text-align:center;"><a href="${_cdBasePath}/${xEsc(d.file_path)}" target="_blank" style="color:var(--primary-color);font-size:16px;"><i class="fas fa-download"></i></a></td>
        <td style="padding:10px 14px;text-align:center;">
          <button onclick="dcVersions(${d.id},'${xEsc(d.document_name)}')" style="background:none;border:none;color:#8b5cf6;cursor:pointer;font-size:14px;padding:3px;" title="Versions"><i class="fas fa-history"></i></button>
          <button onclick="dcEdit(${d.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:3px;" title="Edit"><i class="fas fa-edit"></i></button>
          <button onclick="dcDel(${d.id})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:3px;" title="Delete"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join('');
    const pg=document.getElementById('dc-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="dcPage=${i+1};dcLoad()" style="padding:5px 10px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;">${i+1}</button>`).join(''):'';
  }catch(e){dcMsg('Error: '+e.message);}
}
function dcMsg(m){document.getElementById('dc-tbody').innerHTML=`<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}

function dcOpenModal(d=null){
  document.getElementById('dc-id').value=d?d.id:'';
  document.getElementById('dc-emp').value=d?d.employee_id:'';
  document.getElementById('dc-type').value=d?d.document_type:'Diploma';
  document.getElementById('dc-name').value=d?d.document_name:'';
  document.getElementById('dc-notes').value=d?d.notes:'';
  document.getElementById('dc-reason').value='';
  document.getElementById('dc-file').value='';
  document.getElementById('dc-reason-wrap').style.display=d?'block':'none';
  if(d&&d.file_path){document.getElementById('dc-current-file').style.display='block';document.getElementById('dc-current-file-name').textContent=d.file_path.split('/').pop();}
  else{document.getElementById('dc-current-file').style.display='none';}
  document.getElementById('dc-modal-title').textContent=d?'Update Document':'Upload Document';
  document.getElementById('dc-submit-lbl').textContent=d?'Update':'Upload';
  document.getElementById('dc-alert').style.display='none';
  document.getElementById('dc-modal').style.display='flex';
}
function dcCloseModal(){document.getElementById('dc-modal').style.display='none';}

async function dcEdit(id){
  try{const r=await fetch(cdAPI+'&entity=documents&id='+id);const j=await r.json();if(j.success)dcOpenModal(j.data);}catch(e){alert('Load failed');}
}
async function dcDel(id){
  if(!confirm('Delete this document and its version history?'))return;
  try{const r=await fetch(cdAPI+'&entity=documents&id='+id,{method:'DELETE'});const j=await r.json();j.success?dcLoad():alert(j.message);}catch(e){alert('Failed');}
}
async function dcSubmit(e){
  e.preventDefault();const id=document.getElementById('dc-id').value;
  const btn=document.getElementById('dc-submit-btn');const al=document.getElementById('dc-alert');
  al.style.display='none';btn.disabled=true;
  const fd=new FormData();
  if(id) fd.append('_method','PUT');
  fd.append('employee_id',document.getElementById('dc-emp').value);
  fd.append('document_type',document.getElementById('dc-type').value);
  fd.append('document_name',document.getElementById('dc-name').value.trim());
  fd.append('notes',document.getElementById('dc-notes').value.trim());
  fd.append('change_reason',document.getElementById('dc-reason').value.trim());
  const f=document.getElementById('dc-file').files[0];
  if(f) fd.append('document_file',f);
  try{
    const url=id?cdAPI+'&entity=documents&id='+id:cdAPI+'&entity=documents';
    const r=await fetch(url,{method:'POST',body:fd});const j=await r.json();
    if(j.success){dcCloseModal();dcLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}
  }catch(err){al.textContent='Request failed';al.style.display='block';}
  finally{btn.disabled=false;}
}
async function dcVersions(id,name){
  document.getElementById('cd-ver-title').innerHTML='<i class="fas fa-history" style="color:#8b5cf6;margin-right:8px;"></i>Document Versions: '+xEsc(name);
  document.getElementById('cd-ver-content').innerHTML='<p style="text-align:center;color:var(--gray-400);padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
  document.getElementById('cd-ver-modal').style.display='flex';
  try{
    const r=await fetch(cdAPI+'&entity=documents&id='+id+'&action=versions');const j=await r.json();
    if(!j.success||!j.data.versions.length){document.getElementById('cd-ver-content').innerHTML='<p style="text-align:center;color:var(--gray-400);padding:20px;">No version history</p>';return;}
    document.getElementById('cd-ver-content').innerHTML='<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px 10px;">Ver</th><th style="padding:8px 10px;">Name</th><th style="padding:8px 10px;">Size</th><th style="padding:8px 10px;">Reason</th><th style="padding:8px 10px;">Date</th></tr></thead><tbody>'+j.data.versions.map((v,i)=>`<tr style="border-bottom:1px solid var(--gray-100);${i===0?'background:#f0fdf4;':''}"><td style="padding:8px 10px;font-weight:700;text-align:center;">${i===0?'<span style="background:var(--success-color);color:white;padding:2px 8px;border-radius:10px;font-size:11px;">v'+v.version_number+'</span>':'v'+v.version_number}</td><td style="padding:8px 10px;">${xEsc(v.document_name)}</td><td style="padding:8px 10px;">${v.file_size?(v.file_size/1024).toFixed(0)+' KB':'—'}</td><td style="padding:8px 10px;color:var(--gray-600);">${xEsc(v.change_reason||'—')}</td><td style="padding:8px 10px;font-size:11px;color:var(--gray-500);">${v.created_at}</td></tr>`).join('')+'</tbody></table>';
  }catch(e){document.getElementById('cd-ver-content').innerHTML='<p style="color:var(--danger-color);text-align:center;">Error</p>';}
}

// Init
cdInitDropdowns();
ctLoad();
