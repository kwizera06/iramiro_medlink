<?php
if (($_GET['sub'] ?? '') === 'insurance-prices') { header('Location: insurance.php?module=insurance&sub=insurance-prices'); exit; } // moved to Insurance
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'catalog';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div><h2 style="font-size:24px;font-weight:600;">Service Catalog</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Manage hospital services, procedures, and pricing</p></div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:white;padding:6px;border-radius:10px;box-shadow:var(--shadow);width:fit-content;flex-wrap:wrap;">
  <button onclick="location.href='service-catalog.php?module=admin&sub=catalog'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='catalog'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-list"></i> Service Catalog</button>
  <button onclick="location.href='service-catalog.php?module=admin&sub=inventory-items'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo in_array($subModule,['inventory-items','inventory-mapping','catalog-inventory'])?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-boxes"></i> Connect Inventory Items</button>
</div>

<script>
function esc(s){
  if(s === null || s === undefined) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
</script>

<?php if ($subModule == 'catalog'): ?>

<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:8px;align-items:center;">
      <input id="sc-search" type="text" placeholder="Search services..." oninput="scDebounce()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:220px;">
      <select id="sc-status" onchange="scLoad()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option></select>
      <select id="sc-spec" onchange="scPage=1;scRender()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;max-width:220px;"><option value="">All specialities</option></select>
    </div>
    <div style="display:flex;gap:8px;">
      <button onclick="scOpenImportModal()" style="padding:8px 16px;background:#059669;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-file-csv"></i> Import CSV</button>
      <button onclick="scOpenModal()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> Add Service</button>
      <button onclick="scLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>

  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:16px;" id="sc-stats"></div>

  <!-- Table -->
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:900px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px;text-align:left;">RHIC</th>
        <th style="padding:10px;text-align:left;">Speciality</th>
        <th style="padding:10px;text-align:left;">ICHI CODE</th>
        <th style="padding:10px;text-align:left;">Procedure Name</th>
        <th style="padding:10px;text-align:right;">Primary Health Facility</th>
        <th style="padding:10px;text-align:right;">Secondary Health Facility</th>
        <th style="padding:10px;text-align:right;">Tertiary Health Facility</th>
        <th style="padding:10px;text-align:center;">Connected Items</th>
        <th style="padding:10px;text-align:center;">Actions</th>
      </tr></thead>
      <tbody id="sc-tbody"><tr><td colspan="9" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div id="sc-pagination" style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;font-size:12px;color:var(--gray-500);"></div>
</div>

<!-- Add/Edit Modal -->
<div id="sc-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 id="sc-modal-title" style="font-size:16px;font-weight:600;">Add Service</h3>
      <button onclick="scCloseModal()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <input type="hidden" id="sc-edit-id">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">RHIC</label>
        <input id="sc-rhic" type="text" placeholder="e.g. 1.1" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Speciality *</label>
        <input id="sc-speciality" type="text" placeholder="e.g. General Surgery" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">ICHI CODE</label>
        <input id="sc-ichi" type="text" placeholder="e.g. PZX.AH.AC" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Procedure Name *</label>
        <input id="sc-procedure" type="text" placeholder="e.g. Appendectomy" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Primary Health Facility (RWF)</label>
        <input id="sc-primary" type="number" min="0" step="0.01" placeholder="0.00" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Secondary Health Facility (RWF)</label>
        <input id="sc-secondary" type="number" min="0" step="0.01" placeholder="0.00" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Tertiary Health Facility (RWF)</label>
        <input id="sc-tertiary" type="number" min="0" step="0.01" placeholder="0.00" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>
    <button onclick="scSave()" id="sc-save-btn" style="width:100%;margin-top:16px;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-save"></i> Save Service</button>
  </div>
</div>

<!-- CSV Import Modal -->
<div id="sc-import-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-csv" style="color:#059669;"></i> Import CSV</h3>
      <button onclick="scCloseImportModal()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:16px;">
      <p style="font-size:12px;font-weight:600;color:#065f46;margin-bottom:6px;">CSV Format Requirements:</p>
      <p style="font-size:11px;color:#047857;margin-bottom:4px;">Columns: <strong>RHIC, Speciality, ICHI CODE, Procedure name, Primary Health Facility, Secondary Health Facility, Tertiary Health Facility</strong></p>
      <p style="font-size:11px;color:#047857;">First row should be the header row. Price columns should contain numeric values.</p>
    </div>
    <div style="margin-bottom:16px;">
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">Select CSV File</label>
      <input type="file" id="sc-csv-file" accept=".csv" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div id="sc-import-preview" style="display:none;margin-bottom:16px;max-height:200px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:6px;"></div>
    <div id="sc-import-status" style="display:none;margin-bottom:12px;padding:10px;border-radius:6px;font-size:12px;"></div>
    <div style="display:flex;gap:8px;">
      <button onclick="scPreviewCSV()" id="sc-preview-btn" style="flex:1;padding:10px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-eye"></i> Preview</button>
      <button onclick="scImportCSV()" id="sc-import-btn" style="flex:1;padding:10px;background:#059669;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-upload"></i> Import</button>
    </div>
  </div>
</div>

<script>
const scAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=service-catalog';
let scTimer;
let scAllData=[];
let scPage=1;
const scPageSize=25;

function scGoPage(p){scPage=p;scRender();}

function scDebounce(){clearTimeout(scTimer);scTimer=setTimeout(scLoad,350);}

async function scLoad(){
  const search=document.getElementById('sc-search').value.trim();
  const tbody=document.getElementById('sc-tbody');
  tbody.innerHTML='<tr><td colspan="9" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    let url=scAPI+'&status='+document.getElementById('sc-status').value;
    if(search) url+='&search='+encodeURIComponent(search);
    const r=await fetch(url);
    const j=await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="9" style="padding:20px;text-align:center;color:#dc2626;">'+j.message+'</td></tr>';return;}
    const services=j.data||[];
    scAllData=services;
    const spSel=document.getElementById('sc-spec'), spCur=spSel.value;
    spSel.innerHTML='<option value="">All specialities</option>'+[...new Set(services.map(x=>x.speciality).filter(Boolean))].sort().map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');
    spSel.value=spCur;
    scPage=1;

    // Stats
    const total=services.length;
    document.getElementById('sc-stats').innerHTML=`
      <div style="background:var(--gray-50);border-radius:6px;padding:10px;text-align:center;"><div style="font-size:18px;font-weight:700;color:var(--primary-color);">${total}</div><div style="font-size:10px;color:var(--gray-500);">Total Services</div></div>`;

    scRender();
  }catch(e){
    tbody.innerHTML='<tr><td colspan="9" style="padding:20px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}

function scRender(){
  const tbody=document.getElementById('sc-tbody');
  const pag=document.getElementById('sc-pagination');
  const sp=document.getElementById('sc-spec').value, services=sp?scAllData.filter(x=>x.speciality===sp):scAllData;
    if(!services.length){
      tbody.innerHTML='<tr><td colspan="9" style="padding:30px;text-align:center;color:var(--gray-400);">No services found.</td></tr>';
      pag.innerHTML='';return;
    }

    const totalPages=Math.ceil(services.length/scPageSize);
    if(scPage>totalPages) scPage=totalPages;
    if(scPage<1) scPage=1;
    const start=(scPage-1)*scPageSize;
    const end=start+scPageSize;

    tbody.innerHTML=services.slice(start,end).map(s=>{
      const linkedCount=parseInt(s.linked_items_count||0);
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;font-family:monospace;font-size:12px;">${s.rhic||'—'}</td>
        <td style="padding:8px 10px;">${s.speciality||'—'}</td>
        <td style="padding:8px 10px;font-family:monospace;font-size:12px;color:var(--primary-color);">${s.ichi_code||'—'}</td>
        <td style="padding:8px 10px;font-weight:500;">${s.procedure_name||'—'}${s.is_active==1?'':' <span style="font-size:10px;background:#f3f4f6;color:#6b7280;padding:2px 6px;border-radius:10px;">Inactive</span>'}</td>
        <td style="padding:8px 10px;text-align:right;font-weight:600;">${parseFloat(s.primary_health_facility||0).toLocaleString()}</td>
        <td style="padding:8px 10px;text-align:right;font-weight:600;">${parseFloat(s.secondary_health_facility||0).toLocaleString()}</td>
        <td style="padding:8px 10px;text-align:right;font-weight:600;">${parseFloat(s.tertiary_health_facility||0).toLocaleString()}</td>
        <td style="padding:8px 10px;text-align:center;">
          <a href="service-catalog.php?module=admin&sub=inventory-items&search=${encodeURIComponent(s.procedure_name||'')}" style="text-decoration:none;display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:${linkedCount>0?'#d1fae5':'#f3f4f6'};color:${linkedCount>0?'#065f46':'#6b7280'};border:1px solid ${linkedCount>0?'#a7f3d0':'#e5e7eb'};" title="View & Connect Inventory Items">
            <i class="fas fa-boxes"></i> ${linkedCount} ${linkedCount===1?'item':'items'}
          </a>
        </td>
        <td style="padding:8px 10px;text-align:center;">
          <button onclick="location.href='service-catalog.php?module=admin&sub=inventory-items&search='+encodeURIComponent('${(s.procedure_name||'').replace(/'/g,"\\'")}')" style="background:none;border:none;color:#059669;cursor:pointer;font-size:14px;padding:4px;" title="Connect Inventory Items"><i class="fas fa-link"></i></button>
          <button onclick='scEdit(${JSON.stringify(s).replace(/'/g,"&#39;")})' style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:4px;" title="Edit"><i class="fas fa-edit"></i></button>
          <button onclick="scToggle(${s.id},${s.is_active==1?0:1})" style="background:none;border:none;color:${s.is_active==1?'#b45309':'#047857'};cursor:pointer;font-size:14px;padding:4px;" title="${s.is_active==1?'Deactivate':'Activate'}"><i class="fas ${s.is_active==1?'fa-toggle-on':'fa-toggle-off'}"></i></button>
          <button onclick="scDelete(${s.id})" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;padding:4px;" title="Delete"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join('');

    if(totalPages>1){
      const b='padding:5px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;';
      pag.innerHTML=`<div>Showing ${start+1}–${Math.min(end,services.length)} of ${services.length}</div><div style="display:flex;align-items:center;gap:4px;">
        <button onclick="scGoPage(${scPage-1})" ${scPage===1?'disabled':''} style="${b}${scPage===1?'opacity:0.4;':''}"><i class="fas fa-chevron-left"></i></button>
        <span style="padding:0 8px;font-weight:500;">Page ${scPage} of ${totalPages}</span>
        <button onclick="scGoPage(${scPage+1})" ${scPage===totalPages?'disabled':''} style="${b}${scPage===totalPages?'opacity:0.4;':''}"><i class="fas fa-chevron-right"></i></button></div>`;
    } else {
      pag.innerHTML=`<div>Showing ${services.length} services</div><div></div>`;
    }
}

function scOpenModal(){
  document.getElementById('sc-edit-id').value='';
  document.getElementById('sc-rhic').value='';
  document.getElementById('sc-speciality').value='';
  document.getElementById('sc-ichi').value='';
  document.getElementById('sc-procedure').value='';
  document.getElementById('sc-primary').value='';
  document.getElementById('sc-secondary').value='';
  document.getElementById('sc-tertiary').value='';
  document.getElementById('sc-modal-title').textContent='Add Service';
  document.getElementById('sc-save-btn').innerHTML='<i class="fas fa-save"></i> Save Service';
  document.getElementById('sc-modal').style.display='flex';
}

function scEdit(s){
  document.getElementById('sc-edit-id').value=s.id;
  document.getElementById('sc-rhic').value=s.rhic||'';
  document.getElementById('sc-speciality').value=s.speciality||'';
  document.getElementById('sc-ichi').value=s.ichi_code||'';
  document.getElementById('sc-procedure').value=s.procedure_name||'';
  document.getElementById('sc-primary').value=s.primary_health_facility||'';
  document.getElementById('sc-secondary').value=s.secondary_health_facility||'';
  document.getElementById('sc-tertiary').value=s.tertiary_health_facility||'';
  document.getElementById('sc-modal-title').textContent='Edit Service';
  document.getElementById('sc-save-btn').innerHTML='<i class="fas fa-save"></i> Update Service';
  document.getElementById('sc-modal').style.display='flex';
}

function scCloseModal(){document.getElementById('sc-modal').style.display='none';}

async function scSave(){
  const id=document.getElementById('sc-edit-id').value;
  const rhic=document.getElementById('sc-rhic').value.trim();
  const speciality=document.getElementById('sc-speciality').value.trim();
  const ichi_code=document.getElementById('sc-ichi').value.trim();
  const procedure_name=document.getElementById('sc-procedure').value.trim();
  const primary_health_facility=document.getElementById('sc-primary').value;
  const secondary_health_facility=document.getElementById('sc-secondary').value;
  const tertiary_health_facility=document.getElementById('sc-tertiary').value;

  if(!procedure_name)return alert('Procedure name is required.');

  const body={rhic,speciality,ichi_code,procedure_name,
    primary_health_facility:parseFloat(primary_health_facility)||0,
    secondary_health_facility:parseFloat(secondary_health_facility)||0,
    tertiary_health_facility:parseFloat(tertiary_health_facility)||0};

  const btn=document.getElementById('sc-save-btn');
  btn.disabled=true;

  try{
    const url=id?scAPI+'&id='+id:scAPI;
    const r=await fetch(url,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){
      scCloseModal();
      scLoad();
    }else alert('Error: '+j.message);
  }catch(e){alert('Failed: '+e.message);}
  finally{btn.disabled=false;}
}

async function scToggle(id,on){
  if(!on&&!confirm('Deactivate this service? It will no longer be offered for billing or search.'))return;
  try{
    const r=await fetch(scAPI+'&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({is_active:on})});
    const j=await r.json();
    if(j.success) scLoad(); else alert('Error: '+j.message);
  }catch(e){alert('Failed: '+e.message);}
}
async function scDelete(id){
  if(!confirm('Delete this service? This cannot be undone.'))return;
  try{
    const r=await fetch(scAPI+'&id='+id,{method:'DELETE'});
    const j=await r.json();
    if(j.success) scLoad();
    else alert('Error: '+j.message);
  }catch(e){alert('Failed: '+e.message);}
}

// CSV Import Functions
function scOpenImportModal(){
  document.getElementById('sc-csv-file').value='';
  document.getElementById('sc-import-preview').style.display='none';
  document.getElementById('sc-import-preview').innerHTML='';
  document.getElementById('sc-import-status').style.display='none';
  document.getElementById('sc-import-modal').style.display='flex';
}

function scCloseImportModal(){document.getElementById('sc-import-modal').style.display='none';}

function scParseCSV(text){
  const lines=text.split(/\r?\n/).filter(l=>l.trim());
  if(lines.length<2)return[];
  const rows=[];
  for(let i=1;i<lines.length;i++){
    const cols=lines[i].split(',').map(c=>c.trim().replace(/^"|"$/g,''));
    if(cols.length>=7){
      rows.push({
        rhic:cols[0]||'',
        speciality:cols[1]||'',
        ichi_code:cols[2]||'',
        procedure_name:cols[3]||'',
        primary_health_facility:parseFloat(cols[4])||0,
        secondary_health_facility:parseFloat(cols[5])||0,
        tertiary_health_facility:parseFloat(cols[6])||0
      });
    }
  }
  return rows;
}

function scPreviewCSV(){
  const file=document.getElementById('sc-csv-file').files[0];
  if(!file)return alert('Please select a CSV file first.');
  const reader=new FileReader();
  reader.onload=function(e){
    const rows=scParseCSV(e.target.result);
    if(!rows.length){
      alert('No valid data rows found. Make sure your CSV has at least 7 columns.');return;
    }
    const preview=document.getElementById('sc-import-preview');
    let html='<table style="width:100%;font-size:11px;border-collapse:collapse;">';
    html+='<thead><tr style="background:var(--gray-50);"><th style="padding:6px;text-align:left;">RHIC</th><th style="padding:6px;text-align:left;">Speciality</th><th style="padding:6px;text-align:left;">ICHI CODE</th><th style="padding:6px;text-align:left;">Procedure</th><th style="padding:6px;text-align:right;">Primary</th><th style="padding:6px;text-align:right;">Secondary</th><th style="padding:6px;text-align:right;">Tertiary</th></tr></thead><tbody>';
    const showRows=rows.slice(0,10);
    showRows.forEach(r=>{
      html+=`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:4px 6px;">${r.rhic}</td><td style="padding:4px 6px;">${r.speciality}</td><td style="padding:4px 6px;">${r.ichi_code}</td><td style="padding:4px 6px;">${r.procedure_name}</td><td style="padding:4px 6px;text-align:right;">${r.primary_health_facility.toLocaleString()}</td><td style="padding:4px 6px;text-align:right;">${r.secondary_health_facility.toLocaleString()}</td><td style="padding:4px 6px;text-align:right;">${r.tertiary_health_facility.toLocaleString()}</td></tr>`;
    });
    html+='</tbody></table>';
    if(rows.length>10) html+=`<p style="padding:6px;font-size:11px;color:var(--gray-500);">Showing 10 of ${rows.length} rows...</p>`;
    else html+=`<p style="padding:6px;font-size:11px;color:var(--gray-500);">${rows.length} row(s) found.</p>`;
    preview.innerHTML=html;
    preview.style.display='block';
  };
  reader.readAsText(file);
}

async function scImportCSV(){
  const file=document.getElementById('sc-csv-file').files[0];
  if(!file)return alert('Please select a CSV file first.');
  const btn=document.getElementById('sc-import-btn');
  btn.disabled=true;
  btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Importing...';
  const status=document.getElementById('sc-import-status');

  const reader=new FileReader();
  reader.onload=async function(e){
    const rows=scParseCSV(e.target.result);
    if(!rows.length){
      status.style.display='block';
      status.style.background='#fef2f2';
      status.style.color='#991b1b';
      status.textContent='No valid data rows found.';
      btn.disabled=false;
      btn.innerHTML='<i class="fas fa-upload"></i> Import';
      return;
    }
    try{
      const r=await fetch(scAPI+'&action=import',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({rows:rows})
      });
      const j=await r.json();
      status.style.display='block';
      if(j.success){
        status.style.background='#f0fdf4';
        status.style.color='#065f46';
        status.textContent=j.message||('Successfully imported '+rows.length+' services.');
        scLoad();
      }else{
        status.style.background='#fef2f2';
        status.style.color='#991b1b';
        status.textContent='Error: '+j.message;
      }
    }catch(err){
      status.style.display='block';
      status.style.background='#fef2f2';
      status.style.color='#991b1b';
      status.textContent='Failed: '+err.message;
    }
    btn.disabled=false;
    btn.innerHTML='<i class="fas fa-upload"></i> Import';
  };
  reader.readAsText(file);
}

const _initScSearch = new URLSearchParams(window.location.search).get('search');
if (_initScSearch && document.getElementById('sc-search')) {
  document.getElementById('sc-search').value = _initScSearch;
}
scLoad();
</script>
<?php endif; ?>

<?php if (in_array($subModule, ['inventory-items', 'inventory-mapping', 'catalog-inventory'])): ?>
<!-- ===== CONNECT INVENTORY ITEMS ===== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="font-size:18px;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:8px;">
        <i class="fas fa-boxes" style="color:var(--primary-color);"></i> Connect Catalog Items with Inventory Items
      </h3>
      <p style="font-size:13px;color:var(--gray-500);margin-top:4px;">
        Connect procedures & tests to required consumable inventory items (reagents, test kits, disposables). When performed in Laboratory (or other departments), stock is confirmed and deducted automatically.
      </p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:6px;background:var(--gray-50);border:1px solid var(--gray-200);padding:4px 10px;border-radius:8px;">
        <span style="font-size:12px;font-weight:600;color:var(--gray-700);"><i class="fas fa-warehouse" style="color:var(--primary-color);"></i> Store:</span>
        <select id="sci-filter-store" onchange="sciLoad()" style="border:none;background:transparent;font-size:12px;font-weight:500;color:var(--gray-800);outline:none;cursor:pointer;">
          <option value="1040">Clinic Lab Store</option>
        </select>
      </div>
      <button onclick="sciLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;color:var(--gray-700);" title="Refresh"><i class="fas fa-sync-alt"></i> Refresh</button>
    </div>
  </div>

  <!-- KPI Stats -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;" id="sci-stats">
    <div style="background:var(--gray-50);border-radius:10px;padding:14px;text-align:center;border:1px solid var(--gray-200);">
      <div style="font-size:22px;font-weight:800;color:var(--primary-color);" id="sci-stat-total">0</div>
      <div style="font-size:12px;color:var(--gray-600);font-weight:500;margin-top:2px;">Total Services</div>
    </div>
    <div style="background:#f0fdf4;border-radius:10px;padding:14px;text-align:center;border:1px solid #bbf7d0;">
      <div style="font-size:22px;font-weight:800;color:#059669;" id="sci-stat-linked">0</div>
      <div style="font-size:12px;color:#065f46;font-weight:500;margin-top:2px;">Connected to Items</div>
    </div>
    <div style="background:#fffbeb;border-radius:10px;padding:14px;text-align:center;border:1px solid #fef3c7;">
      <div style="font-size:22px;font-weight:800;color:#d97706;" id="sci-stat-unlinked">0</div>
      <div style="font-size:12px;color:#92400e;font-weight:500;margin-top:2px;">Not Connected</div>
    </div>
    <div style="background:#eff6ff;border-radius:10px;padding:14px;text-align:center;border:1px solid #bfdbfe;">
      <div style="font-size:22px;font-weight:800;color:#2563eb;" id="sci-stat-items">0</div>
      <div style="font-size:12px;color:#1e40af;font-weight:500;margin-top:2px;">Total Item Links</div>
    </div>
  </div>

  <!-- Filters -->
  <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;flex-wrap:wrap;background:var(--gray-50);padding:12px 16px;border-radius:10px;border:1px solid var(--gray-200);">
    <div style="flex:1;min-width:220px;position:relative;">
      <input id="sci-search" type="text" placeholder="Search service name, code, or inventory item..." oninput="sciDebounce()" style="width:100%;padding:9px 12px 9px 34px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
      <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:13px;"></i>
    </div>
    <div style="min-width:180px;">
      <select id="sci-speciality" onchange="sciFilterLocal()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
        <option value="">All Specialities</option>
      </select>
    </div>
    <div style="min-width:160px;">
      <select id="sci-link-status" onchange="sciFilterLocal()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
        <option value="all">All Services</option>
        <option value="linked">Connected Items Only</option>
        <option value="unlinked">Unconnected Only</option>
      </select>
    </div>
    <div style="font-size:12px;color:var(--gray-500);white-space:nowrap;" id="sci-count-info"></div>
  </div>

  <!-- Table -->
  <div style="border:1px solid var(--gray-200);border-radius:10px;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:980px;">
      <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:12px 14px;text-align:left;width:140px;font-weight:600;">Speciality</th>
        <th style="padding:12px 14px;text-align:left;width:110px;font-weight:600;">Code</th>
        <th style="padding:12px 14px;text-align:left;width:240px;font-weight:600;">Service / Procedure</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Connected Inventory Consumables</th>
        <th style="padding:12px 14px;text-align:center;width:150px;font-weight:600;">Action</th>
      </tr></thead>
      <tbody id="sci-tbody"><tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
    </table>
  </div>
  <div id="sci-pagination" style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--gray-500);"></div>
</div>

<!-- Modal: Connect / Manage Items for a Service -->
<div id="sci-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:780px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 30px rgba(0,0,0,0.25);">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;border-bottom:1px solid var(--gray-200);padding-bottom:14px;">
      <div>
        <h3 id="sci-modal-title" style="font-size:17px;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:8px;">
          <i class="fas fa-link" style="color:var(--primary-color);"></i> Connect Inventory Items
        </h3>
        <div id="sci-modal-subtitle" style="font-size:12px;color:var(--gray-600);margin-top:4px;"></div>
      </div>
      <button onclick="sciCloseModal()" style="border:none;background:none;font-size:24px;line-height:1;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>

    <input type="hidden" id="sci-modal-service-id">

    <!-- Currently Connected Items Section -->
    <div style="margin-bottom:24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h4 style="font-size:13px;font-weight:700;color:var(--gray-800);display:flex;align-items:center;gap:6px;">
          <i class="fas fa-clipboard-check" style="color:#059669;"></i> Connected Consumables (<span id="sci-modal-linked-count">0</span>)
        </h4>
        <span style="font-size:11px;color:var(--gray-500);">Stock preview from selected store</span>
      </div>

      <div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;background:white;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
          <thead><tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
            <th style="padding:8px 12px;text-align:left;font-weight:600;">Inventory Item</th>
            <th style="padding:8px 12px;text-align:left;font-weight:600;">Category</th>
            <th style="padding:8px 12px;text-align:center;font-weight:600;">Store Stock</th>
            <th style="padding:8px 12px;text-align:center;font-weight:600;width:110px;">Deduct Qty</th>
            <th style="padding:8px 12px;text-align:left;font-weight:600;">Notes</th>
            <th style="padding:8px 12px;text-align:center;font-weight:600;width:90px;">Action</th>
          </tr></thead>
          <tbody id="sci-linked-tbody">
            <tr><td colspan="6" style="padding:24px;text-align:center;color:var(--gray-400);">No items connected yet.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Connect New Item Box -->
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px;">
      <h4 style="font-size:13px;font-weight:700;color:var(--gray-900);margin-bottom:12px;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-plus-circle" style="color:var(--primary-color);"></i> Connect a New Inventory Item
      </h4>

      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;margin-bottom:10px;">
        <div style="position:relative;">
          <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Search Inventory Item *</label>
          <input type="text" id="sci-item-search" placeholder="Type item name or code..." oninput="sciItemSearchDebounce()" autocomplete="off" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
          <input type="hidden" id="sci-selected-item-id">
          <div id="sci-item-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:220px;overflow-y:auto;background:white;border:1px solid var(--gray-300);border-radius:6px;box-shadow:0 10px 20px rgba(0,0,0,0.15);z-index:1000;margin-top:2px;"></div>
        </div>

        <div>
          <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Deduct Quantity *</label>
          <input type="number" id="sci-item-qty" min="0.0001" step="any" value="1.0" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
        </div>

        <div>
          <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Deduct Store</label>
          <select id="sci-item-store" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
            <option value="">Default Store</option>
          </select>
        </div>
      </div>

      <div style="display:flex;gap:12px;align-items:flex-end;">
        <div style="flex:1;">
          <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Usage Instructions / Notes (Optional)</label>
          <input type="text" id="sci-item-notes" placeholder="e.g. 1 test cassette + 1 lancet per test" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
        </div>
        <button onclick="sciSubmitLink()" id="sci-link-btn" style="padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-link"></i> Connect Item
        </button>
      </div>

      <div id="sci-selected-preview" style="display:none;margin-top:10px;padding:8px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;font-size:12px;color:#1e40af;"></div>
    </div>

    <div style="margin-top:20px;display:flex;justify-content:flex-end;">
      <button onclick="sciCloseModal()" style="padding:10px 24px;background:var(--gray-100);color:var(--gray-800);border:1px solid var(--gray-300);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
        Done
      </button>
    </div>
  </div>
</div>

<script>
if(typeof esc !== 'function'){
  function esc(s){
    if(s === null || s === undefined) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }
}
const sciAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=service-catalog';
let sciAllServices = [];
let sciStores = [];
let sciDebounceTimer;
let sciItemSearchTimer;
let sciCurrentPage = 1;
const sciPageSize = 25;

function sciDebounce(){ clearTimeout(sciDebounceTimer); sciDebounceTimer = setTimeout(sciLoad, 300); }

async function sciInit(){
  // Load stores
  try{
    const r = await fetch(sciAPI + '&action=stores');
    const j = await r.json();
    if(j.success && j.data){
      sciStores = j.data;
      const storeSelect = document.getElementById('sci-filter-store');
      const itemStoreSelect = document.getElementById('sci-item-store');
      let opts = sciStores.map(s => `<option value="${s.id}">${esc(s.name)} (${esc(s.code||s.type||'')})</option>`).join('');
      storeSelect.innerHTML = opts;
      itemStoreSelect.innerHTML = '<option value="">Default Store</option>' + opts;
      if(sciStores.find(s => s.id == 1040)) storeSelect.value = '1040';
      else if(sciStores.length) storeSelect.value = sciStores[0].id;
    }
  }catch(e){}

  // Load specialities
  try{
    const r = await fetch(sciAPI + '&action=specialities');
    const j = await r.json();
    if(j.success && j.data){
      const specSelect = document.getElementById('sci-speciality');
      specSelect.innerHTML = '<option value="">All Specialities</option>' + j.data.map(sp => `<option value="${esc(sp)}">${esc(sp)}</option>`).join('');
    }
  }catch(e){}

  const urlParams = new URLSearchParams(window.location.search);
  const searchParam = urlParams.get('search') || urlParams.get('q');
  if(searchParam){
    const sInput = document.getElementById('sci-search');
    if(sInput) sInput.value = searchParam;
  }

  sciLoad();
}

async function sciLoad(){
  const storeId = document.getElementById('sci-filter-store').value || 1040;
  const tbody = document.getElementById('sci-tbody');
  tbody.innerHTML = '<tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr>';

  try{
    const r = await fetch(sciAPI + '&action=linked_items&store_id=' + encodeURIComponent(storeId));
    const j = await r.json();
    if(!j.success){
      tbody.innerHTML = '<tr><td colspan="5" style="padding:20px;text-align:center;color:#dc2626;">'+esc(j.message)+'</td></tr>';
      return;
    }
    sciAllServices = j.data || [];

    // Calculate KPIs
    const total = sciAllServices.length;
    const linked = sciAllServices.filter(s => (s.linked_items && s.linked_items.length > 0) || parseInt(s.linked_count) > 0).length;
    const unlinked = total - linked;
    let totalItems = 0;
    sciAllServices.forEach(s => { if(s.linked_items) totalItems += s.linked_items.length; });

    document.getElementById('sci-stat-total').textContent = total.toLocaleString();
    document.getElementById('sci-stat-linked').textContent = linked.toLocaleString();
    document.getElementById('sci-stat-unlinked').textContent = unlinked.toLocaleString();
    document.getElementById('sci-stat-items').textContent = totalItems.toLocaleString();

    sciFilterLocal();
  }catch(e){
    tbody.innerHTML = '<tr><td colspan="5" style="padding:20px;text-align:center;color:#dc2626;">Error: '+esc(e.message)+'</td></tr>';
  }
}

function sciFilterLocal(){
  const search = (document.getElementById('sci-search').value||'').toLowerCase().trim();
  const spec = document.getElementById('sci-speciality').value;
  const status = document.getElementById('sci-link-status').value;

  let list = sciAllServices;

  if(spec) list = list.filter(s => s.speciality === spec);

  if(status === 'linked'){
    list = list.filter(s => s.linked_items && s.linked_items.length > 0);
  } else if(status === 'unlinked'){
    list = list.filter(s => !s.linked_items || s.linked_items.length === 0);
  }

  if(search){
    list = list.filter(s => {
      const proc = (s.procedure_name||'').toLowerCase();
      const code = (s.ichi_code||s.rhic||'').toLowerCase();
      const speciality = (s.speciality||'').toLowerCase();
      if(proc.includes(search) || code.includes(search) || speciality.includes(search)) return true;
      if(s.linked_items && s.linked_items.some(it => (it.item_name||'').toLowerCase().includes(search) || (it.item_code||'').toLowerCase().includes(search))) return true;
      return false;
    });
  }

  sciCurrentPage = 1;
  sciRenderList(list);
}

function sciRenderList(list){
  const tbody = document.getElementById('sci-tbody');
  const countInfo = document.getElementById('sci-count-info');
  const pag = document.getElementById('sci-pagination');

  countInfo.textContent = `Showing ${list.length} of ${sciAllServices.length} services`;

  if(!list.length){
    tbody.innerHTML = '<tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-boxes" style="font-size:24px;display:block;margin-bottom:8px;"></i>No matching services found.</td></tr>';
    pag.innerHTML = '';
    return;
  }

  const totalPages = Math.ceil(list.length / sciPageSize);
  if(sciCurrentPage > totalPages) sciCurrentPage = totalPages;
  if(sciCurrentPage < 1) sciCurrentPage = 1;

  const start = (sciCurrentPage - 1) * sciPageSize;
  const end = start + sciPageSize;
  const pageItems = list.slice(start, end);

  tbody.innerHTML = pageItems.map(s => {
    const items = s.linked_items || [];
    let itemsHtml = '';

    if(!items.length){
      itemsHtml = `<span style="padding:4px 10px;background:#fffbeb;border:1px solid #fef3c7;color:#92400e;border-radius:6px;font-size:11px;font-weight:500;display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-exclamation-triangle"></i> No items connected
      </span>`;
    } else {
      itemsHtml = `<div style="display:flex;flex-wrap:wrap;gap:6px;">` + items.map(it => {
        const stock = parseFloat(it.lab_stock || 0);
        const inStock = stock > 0;
        const stockBadge = inStock
          ? `<span style="background:#d1fae5;color:#065f46;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:700;"><i class="fas fa-check"></i> ${stock} in stock</span>`
          : `<span style="background:#fee2e2;color:#b91c1c;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:700;"><i class="fas fa-times-circle"></i> Out of stock (0)</span>`;
        return `<div style="display:inline-flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid var(--gray-200);border-radius:6px;padding:4px 8px;font-size:11px;">
          <span style="font-weight:600;color:var(--gray-900);">${esc(it.item_name)}</span>
          <span style="font-family:monospace;font-size:10px;color:var(--gray-500);">${esc(it.item_code||'')}</span>
          <span style="background:#dbeafe;color:#1e40af;padding:1px 6px;border-radius:4px;font-weight:600;font-size:10px;">${parseFloat(it.default_quantity)} ${esc(it.uom_abbr||'pcs')}</span>
          ${stockBadge}
        </div>`;
      }).join('') + `</div>`;
    }

    return `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 14px;font-size:12px;color:var(--gray-600);">${esc(s.speciality||'—')}</td>
      <td style="padding:10px 14px;font-family:monospace;font-size:11px;color:var(--primary-color);">${esc(s.ichi_code||s.rhic||'—')}</td>
      <td style="padding:10px 14px;font-weight:600;color:var(--gray-900);">
        <div>${esc(s.procedure_name||'—')}</div>
        <div style="font-size:11px;color:var(--gray-500);font-weight:400;margin-top:2px;">Primary: ${parseFloat(s.primary_health_facility||0).toLocaleString()} RWF</div>
      </td>
      <td style="padding:10px 14px;">${itemsHtml}</td>
      <td style="padding:10px 14px;text-align:center;">
        <button onclick="sciOpenModal(${s.id})" style="padding:7px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
          <i class="fas fa-link"></i> Manage (${items.length})
        </button>
      </td>
    </tr>`;
  }).join('');

  // Pagination controls
  if(totalPages > 1){
    let btns = '';
    btns += `<button onclick="sciGoPage(${sciCurrentPage-1})" ${sciCurrentPage===1?'disabled':''} style="padding:5px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;${sciCurrentPage===1?'opacity:0.4;':''}"><i class="fas fa-chevron-left"></i></button>`;
    btns += ` <span style="padding:0 8px;font-weight:500;">Page ${sciCurrentPage} of ${totalPages}</span> `;
    btns += `<button onclick="sciGoPage(${sciCurrentPage+1})" ${sciCurrentPage===totalPages?'disabled':''} style="padding:5px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;${sciCurrentPage===totalPages?'opacity:0.4;':''}"><i class="fas fa-chevron-right"></i></button>`;
    pag.innerHTML = `<div>Showing ${start+1}–${Math.min(end, list.length)} of ${list.length}</div><div style="display:flex;align-items:center;gap:4px;">${btns}</div>`;
  } else {
    pag.innerHTML = `<div>Showing ${list.length} services</div><div></div>`;
  }
}

function sciGoPage(p){
  sciCurrentPage = p;
  sciFilterLocal();
}

let sciCurrentService = null;
async function sciOpenModal(serviceId){
  const storeId = document.getElementById('sci-filter-store').value || 1040;
  sciCurrentService = sciAllServices.find(s => s.id == serviceId);
  document.getElementById('sci-modal-service-id').value = serviceId;
  document.getElementById('sci-modal-title').innerHTML = `<i class="fas fa-link" style="color:var(--primary-color);"></i> Connect Inventory Items`;
  document.getElementById('sci-modal-subtitle').textContent = sciCurrentService ? `${sciCurrentService.procedure_name} (${sciCurrentService.speciality||'Service'} | Code: ${sciCurrentService.ichi_code||sciCurrentService.rhic||'—'})` : '';

  // Reset add form
  document.getElementById('sci-item-search').value = '';
  document.getElementById('sci-selected-item-id').value = '';
  document.getElementById('sci-item-qty').value = '1.0';
  document.getElementById('sci-item-notes').value = '';
  document.getElementById('sci-item-dropdown').style.display = 'none';
  document.getElementById('sci-selected-preview').style.display = 'none';

  document.getElementById('sci-modal').style.display = 'flex';
  sciReloadModalItems(serviceId);
}

function sciCloseModal(){
  document.getElementById('sci-modal').style.display = 'none';
  sciLoad(); // Reload list to update badges
}

async function sciReloadModalItems(serviceId){
  const storeId = document.getElementById('sci-filter-store').value || 1040;
  const tbody = document.getElementById('sci-linked-tbody');
  tbody.innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

  try{
    const r = await fetch(sciAPI + '&action=linked_items&service_id=' + serviceId + '&store_id=' + storeId);
    const j = await r.json();
    const items = (j.success && j.data) ? j.data : [];
    document.getElementById('sci-modal-linked-count').textContent = items.length;

    if(!items.length){
      tbody.innerHTML = '<tr><td colspan="6" style="padding:24px;text-align:center;color:var(--gray-400);"><i class="fas fa-inbox" style="font-size:20px;display:block;margin-bottom:6px;"></i>No inventory items connected to this procedure yet. Use the form below to connect items.</td></tr>';
      return;
    }

    tbody.innerHTML = items.map(it => {
      const stock = parseFloat(it.lab_stock || 0);
      const inStock = stock > 0;
      const stockBadge = inStock
        ? `<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-weight:700;font-size:11px;">${stock} in stock</span>`
        : `<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:12px;font-weight:700;font-size:11px;">0 (Out of stock)</span>`;

      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 12px;">
          <div style="font-weight:600;color:var(--gray-900);">${esc(it.item_name)}</div>
          <div style="font-family:monospace;font-size:10px;color:var(--gray-500);">${esc(it.item_code||'')}</div>
        </td>
        <td style="padding:8px 12px;font-size:11px;color:var(--gray-600);">${esc(it.category_name||'General')}</td>
        <td style="padding:8px 12px;text-align:center;">${stockBadge}</td>
        <td style="padding:8px 12px;text-align:center;">
          <input type="number" id="sci-edit-qty-${it.id}" value="${parseFloat(it.default_quantity)}" step="any" min="0.0001" style="width:75px;padding:4px 6px;border:1px solid var(--gray-300);border-radius:4px;font-size:12px;text-align:center;">
          <span style="font-size:10px;color:var(--gray-500);display:block;margin-top:2px;">${esc(it.uom_abbr||'pcs')}</span>
        </td>
        <td style="padding:8px 12px;">
          <input type="text" id="sci-edit-notes-${it.id}" value="${esc(it.notes||'')}" placeholder="Notes..." style="width:100%;padding:4px 6px;border:1px solid var(--gray-300);border-radius:4px;font-size:11px;">
        </td>
        <td style="padding:8px 12px;text-align:center;white-space:nowrap;">
          <button onclick="sciSaveRow(${it.id})" style="background:none;border:none;color:#059669;cursor:pointer;font-size:14px;padding:4px;" title="Save changes"><i class="fas fa-save"></i></button>
          <button onclick="sciUnlinkItem(${it.id})" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;padding:4px;" title="Unlink item"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join('');
  }catch(e){
    tbody.innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;color:#dc2626;">Error: '+esc(e.message)+'</td></tr>';
  }
}

// Live search items autocomplete
function sciItemSearchDebounce(){
  clearTimeout(sciItemSearchTimer);
  sciItemSearchTimer = setTimeout(sciRunItemSearch, 250);
}

async function sciRunItemSearch(){
  const q = document.getElementById('sci-item-search').value.trim();
  const dropdown = document.getElementById('sci-item-dropdown');
  const storeId = document.getElementById('sci-filter-store').value || 1040;

  if(q.length < 1){
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
    return;
  }

  dropdown.style.display = 'block';
  dropdown.innerHTML = '<div style="padding:10px;text-align:center;color:var(--gray-400);font-size:12px;"><i class="fas fa-spinner fa-spin"></i> Searching items...</div>';

  try{
    const r = await fetch(sciAPI + '&action=inventory_items&q=' + encodeURIComponent(q) + '&store_id=' + storeId);
    const j = await r.json();
    const items = (j.success && j.data) ? j.data : [];

    if(!items.length){
      dropdown.innerHTML = '<div style="padding:10px;text-align:center;color:var(--gray-400);font-size:12px;">No matching items found.</div>';
      return;
    }

    dropdown.innerHTML = items.map(it => {
      const stock = parseFloat(it.store_stock || 0);
      const stockBadge = stock > 0
        ? `<span style="color:#059669;font-weight:600;font-size:11px;">(${stock} in store)</span>`
        : `<span style="color:#dc2626;font-size:11px;">(Out of stock: ${stock})</span>`;
      return `<div class="sci-item-option" onclick='sciSelectItem(${JSON.stringify(it).replace(/'/g,"&#39;")})' style="padding:8px 12px;border-bottom:1px solid var(--gray-100);cursor:pointer;font-size:12px;display:flex;justify-content:space-between;align-items:center;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''">
        <div>
          <div style="font-weight:600;color:var(--gray-900);">${esc(it.name)}</div>
          <div style="font-size:10px;color:var(--gray-500);font-family:monospace;">${esc(it.item_code||'')} &bull; ${esc(it.category_name||'General')}</div>
        </div>
        <div>${stockBadge}</div>
      </div>`;
    }).join('');
  }catch(e){
    dropdown.innerHTML = '<div style="padding:10px;text-align:center;color:#dc2626;font-size:12px;">Search error</div>';
  }
}

function sciSelectItem(item){
  document.getElementById('sci-selected-item-id').value = item.id;
  document.getElementById('sci-item-search').value = item.name;
  document.getElementById('sci-item-dropdown').style.display = 'none';

  const preview = document.getElementById('sci-selected-preview');
  preview.style.display = 'block';
  preview.innerHTML = `<strong>Selected:</strong> ${esc(item.name)} (Code: ${esc(item.item_code||'—')}) &bull; Available in store: <strong>${parseFloat(item.store_stock||0)} ${esc(item.uom_abbr||'units')}</strong>`;
}

// Close dropdown if clicked outside
document.addEventListener('click', function(e){
  const dd = document.getElementById('sci-item-dropdown');
  const inp = document.getElementById('sci-item-search');
  if(dd && inp && !dd.contains(e.target) && e.target !== inp){
    dd.style.display = 'none';
  }
});

async function sciSubmitLink(){
  const serviceId = document.getElementById('sci-modal-service-id').value;
  const itemId = document.getElementById('sci-selected-item-id').value;
  const qty = parseFloat(document.getElementById('sci-item-qty').value) || 1.0;
  const storeId = document.getElementById('sci-item-store').value || null;
  const notes = document.getElementById('sci-item-notes').value.trim();
  const btn = document.getElementById('sci-link-btn');

  if(!serviceId){ alert('Please select a service.'); return; }
  if(!itemId){ alert('Please search and select an inventory item from the dropdown.'); return; }
  if(qty <= 0){ alert('Quantity must be greater than 0.'); return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Connecting...';

  try{
    const r = await fetch(sciAPI + '&action=link_item', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        service_id: parseInt(serviceId),
        item_id: parseInt(itemId),
        default_quantity: qty,
        store_id: storeId ? parseInt(storeId) : null,
        notes: notes
      })
    });
    const j = await r.json();
    if(j.success){
      // Reset form
      document.getElementById('sci-item-search').value = '';
      document.getElementById('sci-selected-item-id').value = '';
      document.getElementById('sci-item-qty').value = '1.0';
      document.getElementById('sci-item-notes').value = '';
      document.getElementById('sci-selected-preview').style.display = 'none';
      sciReloadModalItems(serviceId);
    } else {
      alert('Error: ' + j.message);
    }
  }catch(e){
    alert('Failed to link item: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-link"></i> Connect Item';
  }
}

async function sciSaveRow(linkId){
  const qty = parseFloat(document.getElementById('sci-edit-qty-' + linkId).value) || 1.0;
  const notes = document.getElementById('sci-edit-notes-' + linkId).value.trim();
  const serviceId = document.getElementById('sci-modal-service-id').value;

  try{
    const r = await fetch(sciAPI + '&action=update_linked_item', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        id: linkId,
        default_quantity: qty,
        notes: notes
      })
    });
    const j = await r.json();
    if(j.success){
      sciReloadModalItems(serviceId);
    } else {
      alert('Error: ' + j.message);
    }
  }catch(e){
    alert('Failed: ' + e.message);
  }
}

async function sciUnlinkItem(linkId){
  if(!confirm('Disconnect this inventory item from the procedure?')) return;
  const serviceId = document.getElementById('sci-modal-service-id').value;

  try{
    const r = await fetch(sciAPI + '&action=unlink_item', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({id: linkId})
    });
    const j = await r.json();
    if(j.success){
      sciReloadModalItems(serviceId);
    } else {
      alert('Error: ' + j.message);
    }
  }catch(e){
    alert('Failed: ' + e.message);
  }
}

const _initSciSearch = new URLSearchParams(window.location.search).get('search');
if(_initSciSearch && document.getElementById('sci-search')){
  document.getElementById('sci-search').value = _initSciSearch;
}
sciInit();
</script>
<?php endif; ?>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
