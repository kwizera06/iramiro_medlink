<?php
require_once __DIR__ . '/includes/header.php';
$userId = $_SESSION['user_id'] ?? 0;
// Medical Records & Archive tabs: Patient Files / Archived Records / Access Log have their own view
$docSub = in_array($_GET['sub'] ?? '', ['all', 'patient', 'archived', 'access-log'], true) ? $_GET['sub'] : 'all';
if ($docSub !== 'all') {
    require __DIR__ . '/includes/doc-archive.php';
    require_once __DIR__ . '/includes/footer.php';
    return;
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;">Document Management</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Upload, organize, and manage organizational documents</p>
  </div>
  <div style="display:flex;gap:8px;">
    <button onclick="docShowCreateFolder()" style="padding:10px 16px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;">
      <i class="fas fa-folder-plus"></i> New Folder
    </button>
    <button onclick="docShowUpload()" style="padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-upload"></i> Upload Document
    </button>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;margin-bottom:20px;">
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--primary-color);">
    <div id="doc-stat-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Total</div>
  </div>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid #16a34a;">
    <div id="doc-stat-financial" style="font-size:22px;font-weight:700;color:#16a34a;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Financial</div>
  </div>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid #2563eb;">
    <div id="doc-stat-letter" style="font-size:22px;font-weight:700;color:#2563eb;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Letters</div>
  </div>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid #d97706;">
    <div id="doc-stat-invoice" style="font-size:22px;font-weight:700;color:#d97706;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Invoices</div>
  </div>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid #7c3aed;">
    <div id="doc-stat-contract" style="font-size:22px;font-weight:700;color:#7c3aed;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Contracts</div>
  </div>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--gray-400);">
    <div id="doc-stat-other" style="font-size:22px;font-weight:700;color:var(--gray-400);">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Other</div>
  </div>
</div>

<!-- Breadcrumb & Filters -->
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:16px;">
  <div id="doc-breadcrumb" style="margin-bottom:10px;font-size:13px;display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
    <span onclick="docNavigate(null)" style="cursor:pointer;color:var(--primary-color);font-weight:600;"><i class="fas fa-home"></i> Root</span>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <input id="doc-search" type="text" placeholder="Search documents..." oninput="docDebounce()" style="flex:1;min-width:180px;padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
    <select id="doc-type-filter" onchange="docPage=1;docLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
      <option value="">All Types</option>
      <option value="financial">Financial</option>
      <option value="letter">Letter</option>
      <option value="invoice">Invoice</option>
      <option value="contract">Contract</option>
      <option value="policy">Policy</option>
      <option value="report">Report</option>
      <option value="other">Other</option>
    </select>
    <button onclick="docLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i></button>
  </div>
</div>

<!-- Folders -->
<div id="doc-folders-area" style="margin-bottom:16px;"></div>

<!-- Documents Table -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:12px 14px;text-align:left;font-weight:600;">#</th>
      <th style="padding:12px 14px;text-align:left;font-weight:600;">Document</th>
      <th style="padding:12px 14px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:12px 14px;text-align:left;font-weight:600;">File</th>
      <th style="padding:12px 14px;text-align:center;font-weight:600;">Version</th>
      <th style="padding:12px 14px;text-align:left;font-weight:600;">Uploaded By</th>
      <th style="padding:12px 14px;text-align:left;font-weight:600;">Date</th>
      <th style="padding:12px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="doc-tbody">
      <tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i></td></tr>
    </tbody>
  </table>
  <div id="doc-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--gray-200);"></div>
</div>

<!-- Upload Modal -->
<div id="doc-upload-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:550px;max-width:95vw;padding:24px;position:relative;">
    <button onclick="document.getElementById('doc-upload-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;"><i class="fas fa-upload" style="color:var(--primary-color);margin-right:8px;"></i>Upload Document</h3>
    <div id="doc-upload-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
    <form id="doc-upload-form" onsubmit="docSubmitUpload(event)">
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Title *</label>
        <input id="doc-title" type="text" required placeholder="Document title" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Document Type *</label>
          <select id="doc-type" required style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
            <option value="financial">Financial Document</option>
            <option value="letter">Letter</option>
            <option value="invoice">Invoice</option>
            <option value="contract">Contract</option>
            <option value="policy">Policy</option>
            <option value="report">Report</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Folder</label>
          <select id="doc-folder" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
            <option value="">Root (No Folder)</option>
          </select>
        </div>
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Tags</label>
        <input id="doc-tags" type="text" placeholder="e.g. 2026, quarterly, budget" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Description</label>
        <textarea id="doc-desc" rows="2" placeholder="Brief description..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
      </div>
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">File * <span style="font-weight:400;color:var(--gray-400);">(Max 20MB)</span></label>
        <input id="doc-file" type="file" required style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <button type="submit" id="doc-upload-btn" style="width:100%;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-upload"></i> Upload</button>
    </form>
  </div>
</div>

<!-- Create Folder Modal -->
<div id="doc-folder-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:420px;max-width:95vw;padding:24px;position:relative;">
    <button onclick="document.getElementById('doc-folder-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;"><i class="fas fa-folder-plus" style="color:#d97706;margin-right:8px;"></i>New Folder</h3>
    <div style="margin-bottom:14px;">
      <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Folder Name *</label>
      <input id="doc-folder-name" type="text" required placeholder="e.g. 2026 Financial Reports" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div style="margin-bottom:14px;">
      <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Description</label>
      <input id="doc-folder-desc" type="text" placeholder="Optional description" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div style="margin-bottom:16px;">
      <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Color</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap;" id="doc-folder-colors">
        <span onclick="docSelectColor('#6b7280')" data-color="#6b7280" style="width:28px;height:28px;border-radius:50%;background:#6b7280;cursor:pointer;border:3px solid #6b7280;"></span>
        <span onclick="docSelectColor('#2563eb')" data-color="#2563eb" style="width:28px;height:28px;border-radius:50%;background:#2563eb;cursor:pointer;border:3px solid transparent;"></span>
        <span onclick="docSelectColor('#16a34a')" data-color="#16a34a" style="width:28px;height:28px;border-radius:50%;background:#16a34a;cursor:pointer;border:3px solid transparent;"></span>
        <span onclick="docSelectColor('#d97706')" data-color="#d97706" style="width:28px;height:28px;border-radius:50%;background:#d97706;cursor:pointer;border:3px solid transparent;"></span>
        <span onclick="docSelectColor('#dc2626')" data-color="#dc2626" style="width:28px;height:28px;border-radius:50%;background:#dc2626;cursor:pointer;border:3px solid transparent;"></span>
        <span onclick="docSelectColor('#7c3aed')" data-color="#7c3aed" style="width:28px;height:28px;border-radius:50%;background:#7c3aed;cursor:pointer;border:3px solid transparent;"></span>
      </div>
    </div>
    <button onclick="docCreateFolder()" style="width:100%;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-folder-plus"></i> Create Folder</button>
  </div>
</div>

<!-- Version Modal -->
<div id="doc-version-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;position:relative;">
    <button onclick="document.getElementById('doc-version-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <div id="doc-version-content">Loading...</div>
  </div>
</div>


<script>
const docAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=documents';
const docUserId=<?php echo (int)$userId; ?>;
let docPage=1,docTimer,docCurrentFolder=null,docSelectedColor='#6b7280';

function docEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function docFmtSize(b){if(!b)return '—';if(b<1024)return b+'B';if(b<1048576)return (b/1024).toFixed(1)+'KB';return (b/1048576).toFixed(1)+'MB';}
async function docJson(r){const t=await r.text();try{return JSON.parse(t);}catch(_){throw new Error(r.status===413?'File too large for the server':'Server error ('+r.status+')');}}
function docDebounce(){clearTimeout(docTimer);docTimer=setTimeout(()=>{docPage=1;docLoad();},350);}

const docTypeColors={financial:'#16a34a',letter:'#2563eb',invoice:'#d97706',contract:'#7c3aed',policy:'#0891b2',report:'#dc2626',other:'#6b7280'};
const docTypeIcons={financial:'fas fa-dollar-sign',letter:'fas fa-envelope',invoice:'fas fa-file-invoice',contract:'fas fa-file-contract',policy:'fas fa-shield-alt',report:'fas fa-chart-bar',other:'fas fa-file'};
const docExtIcons={pdf:'fas fa-file-pdf',doc:'fas fa-file-word',docx:'fas fa-file-word',xls:'fas fa-file-excel',xlsx:'fas fa-file-excel',csv:'fas fa-file-csv',ppt:'fas fa-file-powerpoint',pptx:'fas fa-file-powerpoint',jpg:'fas fa-file-image',jpeg:'fas fa-file-image',png:'fas fa-file-image',txt:'fas fa-file-alt'};

function docSelectColor(c){
  docSelectedColor=c;
  document.querySelectorAll('#doc-folder-colors span').forEach(s=>{s.style.border=s.getAttribute('data-color')===c?'3px solid '+c:'3px solid transparent';});
}

function docShowUpload(){
  document.getElementById('doc-upload-modal').style.display='flex';
  document.getElementById('doc-upload-alert').style.display='none';
  docLoadFolderSelect();
  if(docCurrentFolder) document.getElementById('doc-folder').value=docCurrentFolder;
}
function docShowCreateFolder(){document.getElementById('doc-folder-modal').style.display='flex';}

function docNavigate(folderId){
  docCurrentFolder=folderId;docPage=1;docLoadFolders();docLoad();
}

async function docLoadFolders(){
  const params=new URLSearchParams({action:'folders'});
  if(docCurrentFolder) params.set('parent_id',docCurrentFolder);
  try{
    const r=await fetch(docAPI+'&'+params);const j=await r.json();
    if(!j.success)return;
    const{folders,breadcrumb}=j.data;
    // Breadcrumb
    let bc='<span onclick="docNavigate(null)" style="cursor:pointer;color:var(--primary-color);font-weight:600;"><i class="fas fa-home"></i> Root</span>';
    breadcrumb.forEach(b=>{bc+=` <i class="fas fa-chevron-right" style="font-size:10px;color:var(--gray-300);margin:0 4px;"></i> <span onclick="docNavigate(${b.id})" style="cursor:pointer;color:var(--primary-color);font-weight:500;">${docEsc(b.name)}</span>`;});
    document.getElementById('doc-breadcrumb').innerHTML=bc;
    // Folders grid
    const area=document.getElementById('doc-folders-area');
    if(!folders.length){area.innerHTML='';return;}
    area.innerHTML='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;">'+folders.map(f=>`
      <div onclick="docNavigate(${f.id})" style="background:white;border-radius:10px;padding:14px;box-shadow:var(--shadow);cursor:pointer;border-left:4px solid ${f.color||'#6b7280'};transition:.15s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
          <i class="fas fa-folder" style="font-size:18px;color:${f.color||'#6b7280'};"></i>
          <span style="font-weight:600;font-size:13px;color:var(--gray-900);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${docEsc(f.name)}</span>
          <button onclick="event.stopPropagation();docDeleteFolder(${f.id},'${docEsc(f.name)}')" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:12px;padding:2px;" title="Delete folder"><i class="fas fa-times"></i></button>
        </div>
        <div style="font-size:11px;color:var(--gray-500);">${f.document_count||0} docs${f.subfolder_count?' · '+f.subfolder_count+' folders':''}</div>
      </div>`).join('')+'</div>';
  }catch(e){}
}

async function docLoadFolderSelect(){
  const sel=document.getElementById('doc-folder');
  if(sel.options.length>1)return;
  try{
    const r=await fetch(docAPI+'&action=all_folders');const j=await r.json();
    if(j.success){
      j.data.forEach(f=>{sel.innerHTML+=`<option value="${f.id}">${docEsc(f.full_path)}</option>`;});
    }
  }catch(e){}
}

async function docCreateFolder(){
  const name=document.getElementById('doc-folder-name').value.trim();
  if(!name){alert('Folder name required');return;}
  try{
    const r=await fetch(docAPI+'&action=create_folder',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,parent_id:docCurrentFolder,description:document.getElementById('doc-folder-desc').value.trim(),color:docSelectedColor,created_by:docUserId})});
    const j=await r.json();
    if(j.success){
      document.getElementById('doc-folder-modal').style.display='none';
      document.getElementById('doc-folder-name').value='';
      document.getElementById('doc-folder-desc').value='';
      docLoadFolders();
      // Reset folder select cache
      document.getElementById('doc-folder').innerHTML='<option value="">Root (No Folder)</option>';
    }else alert('Error: '+j.message);
  }catch(e){alert('Failed');}
}

async function docDeleteFolder(id,name){
  if(!confirm('Delete folder "'+name+'"?\n\nDocuments inside will be moved to root.'))return;
  try{
    const r=await fetch(docAPI+'&action=delete_folder',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    const j=await r.json();
    if(j.success){docLoadFolders();docLoad();}else alert('Error: '+j.message);
  }catch(e){alert('Failed');}
}

async function docLoad(){
  const search=document.getElementById('doc-search').value.trim();
  const type=document.getElementById('doc-type-filter').value;
  const params=new URLSearchParams({page:docPage,limit:20});
  if(search)params.set('search',search);
  if(type)params.set('type',type);
  if(docCurrentFolder)params.set('folder_id',docCurrentFolder);
  else if(!search)params.set('folder_id','null');
  const tbody=document.getElementById('doc-tbody');
  tbody.innerHTML='<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const r=await fetch(docAPI+'&'+params);const j=await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:#dc2626;">Error</td></tr>';return;}
    const{documents,stats,pagination}=j.data;
    document.getElementById('doc-stat-total').textContent=stats.total||0;
    document.getElementById('doc-stat-financial').textContent=stats.financial||0;
    document.getElementById('doc-stat-letter').textContent=stats.letter||0;
    document.getElementById('doc-stat-invoice').textContent=stats.invoice||0;
    document.getElementById('doc-stat-contract').textContent=stats.contract||0;
    document.getElementById('doc-stat-other').textContent=(stats.other||0)+(stats.policy||0)+(stats.report||0);
    if(!documents.length){
      tbody.innerHTML='<tr><td colspan="8" style="padding:50px;text-align:center;color:var(--gray-400);"><i class="fas fa-folder-open" style="font-size:28px;display:block;margin-bottom:8px;"></i>No documents in this location.</td></tr>';
      document.getElementById('doc-pagination').innerHTML='';return;
    }
    const start=(docPage-1)*20;
    tbody.innerHTML=documents.map((d,i)=>{
      const color=docTypeColors[d.document_type]||'#6b7280';
      const icon=docExtIcons[d.file_extension]||'fas fa-file';
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 14px;font-size:12px;color:var(--gray-500);">${start+i+1}</td>
        <td style="padding:10px 14px;"><div style="font-weight:600;color:var(--gray-900);">${docEsc(d.title)}</div>${d.description?'<div style="font-size:11px;color:var(--gray-500);margin-top:2px;">'+docEsc(d.description).substring(0,60)+'</div>':''}</td>
        <td style="padding:10px 14px;"><span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:${color}15;color:${color};"><i class="${docTypeIcons[d.document_type]||'fas fa-file'}" style="margin-right:4px;"></i>${d.document_type}</span></td>
        <td style="padding:10px 14px;font-size:12px;"><i class="${icon}" style="margin-right:4px;color:var(--gray-500);"></i>${docEsc(d.file_name)}<div style="font-size:10px;color:var(--gray-400);">${docFmtSize(d.file_size)}</div></td>
        <td style="padding:10px 14px;text-align:center;"><span style="padding:2px 8px;background:var(--gray-100);border-radius:10px;font-size:11px;font-weight:600;">v${d.current_version}</span></td>
        <td style="padding:10px 14px;font-size:12px;">${docEsc(d.uploaded_by_name||'—')}</td>
        <td style="padding:10px 14px;font-size:12px;color:var(--gray-600);">${(d.created_at||'').substring(0,10)}</td>
        <td style="padding:10px 14px;text-align:center;white-space:nowrap;">
          <a href="${docEsc(d.file_path)}" target="_blank" style="padding:4px 8px;background:#eff6ff;color:#2563eb;border:none;border-radius:4px;font-size:11px;cursor:pointer;text-decoration:none;margin-right:3px;" title="Download"><i class="fas fa-download"></i></a>
          <button onclick="docViewVersions(${d.id})" style="padding:4px 8px;background:#f0fdf4;color:#16a34a;border:none;border-radius:4px;font-size:11px;cursor:pointer;margin-right:3px;" title="Versions"><i class="fas fa-history"></i></button>
          <button onclick="docViewVersions(${d.id})" style="padding:4px 8px;background:#fef3c7;color:#92400e;border:none;border-radius:4px;font-size:11px;cursor:pointer;margin-right:3px;" title="New Version"><i class="fas fa-plus"></i></button>
          <button onclick="docDelete(${d.id})" style="padding:4px 8px;background:#fee2e2;color:#dc2626;border:none;border-radius:4px;font-size:11px;cursor:pointer;" title="Delete"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join('');
    const pg=document.getElementById('doc-pagination');
    if(pagination.total_pages>1){
      pg.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">Page ${pagination.page} of ${pagination.total_pages} (${pagination.total})</span><div style="display:flex;gap:4px;">
        <button onclick="docPage--;docLoad()" ${docPage<=1?'disabled':''} style="padding:6px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;font-size:12px;"><i class="fas fa-chevron-left"></i></button>
        <button onclick="docPage++;docLoad()" ${docPage>=pagination.total_pages?'disabled':''} style="padding:6px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;font-size:12px;"><i class="fas fa-chevron-right"></i></button>
      </div>`;
    } else pg.innerHTML='';
  }catch(e){tbody.innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:#dc2626;">Failed</td></tr>';}
}

async function docSubmitUpload(e){
  e.preventDefault();
  const btn=document.getElementById('doc-upload-btn');const al=document.getElementById('doc-upload-alert');
  al.style.display='none';btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Uploading...';
  const fd=new FormData();
  fd.append('title',document.getElementById('doc-title').value.trim());
  fd.append('description',document.getElementById('doc-desc').value.trim());
  fd.append('document_type',document.getElementById('doc-type').value);
  fd.append('tags',document.getElementById('doc-tags').value.trim());
  fd.append('folder_id',document.getElementById('doc-folder').value||'');
  fd.append('file',document.getElementById('doc-file').files[0]);
  fd.append('uploaded_by',docUserId);
  try{
    const r=await fetch(docAPI,{method:'POST',body:fd});const j=await docJson(r);
    if(j.success){
      al.innerHTML='<i class="fas fa-check-circle"></i> '+j.message;
      al.style.cssText='display:block;padding:10px 14px;border-radius:8px;font-size:13px;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;';
      document.getElementById('doc-upload-form').reset();
      docLoad();docLoadFolders();
      setTimeout(()=>{document.getElementById('doc-upload-modal').style.display='none';},1000);
    } else {
      al.innerHTML='<i class="fas fa-exclamation-circle"></i> '+j.message;
      al.style.cssText='display:block;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';
    }
  }catch(err){
    al.innerHTML='<i class="fas fa-exclamation-circle"></i> Upload failed: '+docEsc(err.message);
    al.style.cssText='display:block;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';
  }finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-upload"></i> Upload';}
}

async function docViewVersions(id){
  document.getElementById('doc-version-content').innerHTML='<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin" style="font-size:20px;color:var(--gray-400);"></i></div>';
  document.getElementById('doc-version-modal').style.display='flex';
  try{
    const r=await fetch(docAPI+'&id='+id);const j=await r.json();
    if(!j.success){document.getElementById('doc-version-content').textContent='Error';return;}
    const d=j.data;
    let html=`<h3 style="font-size:16px;font-weight:600;margin-bottom:4px;"><i class="fas fa-file" style="color:var(--primary-color);margin-right:6px;"></i>${docEsc(d.title)}</h3>
      <div style="font-size:12px;color:var(--gray-500);margin-bottom:16px;">Type: ${d.document_type} | Current: v${d.current_version}</div>`;
    html+=`<div style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:10px;"><i class="fas fa-history" style="margin-right:4px;"></i>Version History (${d.versions.length})</div>`;
    html+=d.versions.map(v=>`<div style="padding:12px;border:1px solid var(--gray-200);border-radius:8px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
      <div>
        <div style="font-weight:600;font-size:13px;">Version ${v.version_number} ${v.version_number==d.current_version?'<span style="padding:1px 6px;background:#dcfce7;color:#166534;border-radius:8px;font-size:10px;">Current</span>':''}</div>
        <div style="font-size:11px;color:var(--gray-500);">${docEsc(v.file_name)} · ${docFmtSize(v.file_size)} · ${(v.created_at||'').substring(0,16)}</div>
        ${v.change_notes?'<div style="font-size:11px;color:var(--gray-600);margin-top:2px;"><i class="fas fa-sticky-note" style="margin-right:3px;"></i>'+docEsc(v.change_notes)+'</div>':''}
      </div>
      <a href="${docEsc(v.file_path)}" target="_blank" style="padding:6px 12px;background:var(--primary-color);color:white;border-radius:6px;font-size:11px;text-decoration:none;font-weight:500;"><i class="fas fa-download"></i></a>
    </div>`).join('');
    html+=`<div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-200);">
      <div style="font-size:13px;font-weight:600;margin-bottom:8px;"><i class="fas fa-plus-circle" style="color:var(--primary-color);"></i> Upload New Version</div>
      <form onsubmit="docSubmitVersion(event,${d.id})">
        <input type="file" id="doc-ver-file-${d.id}" required style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;margin-bottom:8px;">
        <input type="text" id="doc-ver-notes-${d.id}" placeholder="Change notes (optional)" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;margin-bottom:8px;">
        <button type="submit" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;"><i class="fas fa-upload"></i> Upload v${d.current_version+1}</button>
      </form>
    </div>`;
    document.getElementById('doc-version-content').innerHTML=html;
  }catch(e){document.getElementById('doc-version-content').textContent='Failed to load.';}
}

async function docSubmitVersion(e,docId){
  e.preventDefault();
  const fileInput=document.getElementById('doc-ver-file-'+docId);
  const notes=document.getElementById('doc-ver-notes-'+docId).value.trim();
  if(!fileInput.files.length){alert('Select a file');return;}
  const fd=new FormData();
  fd.append('document_id',docId);
  fd.append('file',fileInput.files[0]);
  fd.append('change_notes',notes);
  fd.append('uploaded_by',docUserId);
  try{
    const r=await fetch(docAPI+'&action=new_version',{method:'POST',body:fd});const j=await docJson(r);
    if(j.success){alert('✓ '+j.message);docViewVersions(docId);docLoad();}
    else alert('Error: '+j.message);
  }catch(err){alert('Failed: '+err.message);}
}

async function docDelete(id){
  if(!confirm('Delete this document?'))return;
  try{
    const r=await fetch(docAPI+'&id='+id,{method:'DELETE'});const j=await r.json();
    if(j.success)docLoad();else alert('Error: '+j.message);
  }catch(e){alert('Failed');}
}

// Init
docLoadFolders();
docLoad();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
