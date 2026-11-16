<?php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'companies';
?>
<style>.ins-tab{padding:10px 18px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}.ins-tab.active{background:var(--primary-color);color:white;}</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div><h2 style="font-size:24px;font-weight:600;">Insurance Management</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Manage insurance companies and coverage rates</p></div>
</div>
<div style="display:flex;gap:8px;margin-bottom:24px;">
  <button class="ins-tab <?php echo $subModule=='companies'?'active':'';?>" onclick="location.href='insurance.php?module=insurance&sub=companies'"><i class="fas fa-building"></i> Companies</button>
  <button class="ins-tab <?php echo $subModule=='rates'?'active':'';?>" onclick="location.href='insurance.php?module=insurance&sub=rates'"><i class="fas fa-percentage"></i> Insurance Rates</button>
  <button class="ins-tab <?php echo $subModule=='insurance-prices'?'active':'';?>" onclick="location.href='insurance.php?module=insurance&sub=insurance-prices'"><i class="fas fa-shield-alt"></i> Insurance Prices</button>
  <button class="ins-tab <?php echo $subModule=='receivables'?'active':'';?>" onclick="location.href='insurance.php?module=insurance&sub=receivables'"><i class="fas fa-file-invoice-dollar"></i> Receivables</button>
</div>

<?php if ($subModule == 'companies'): ?>
<!-- ====== INSURANCE COMPANIES ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-building" style="color:var(--primary-color);"></i> Insurance Companies</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="insOpenModal()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> Add Company</button>
      <button onclick="insLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">Name</th><th style="padding:10px;text-align:left;">Code</th><th style="padding:10px;text-align:left;">Contact</th><th style="padding:10px;text-align:left;">Phone</th><th style="padding:10px;text-align:left;">Email</th><th style="padding:10px;text-align:center;">Status</th><th style="padding:10px;text-align:center;">Actions</th></tr></thead>
      <tbody id="ins-tbody"><tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>

<!-- Company Add/Edit Modal -->
<div id="ins-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:560px;max-width:90vw;padding:24px;position:relative;">
    <button onclick="insCloseModal()" style="position:absolute;top:14px;right:14px;border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 id="ins-modal-title" style="font-size:16px;font-weight:600;margin-bottom:16px;">Add Insurance Company</h3>
    <div id="ins-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <input type="hidden" id="ic-id">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Company Name *</label><input id="ic-name" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Code *</label><input id="ic-code" placeholder="e.g. RSSB" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Contact Person *</label><input id="ic-contact" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Phone *</label><input id="ic-phone" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Email *</label><input id="ic-email" type="email" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Website</label><input id="ic-website" placeholder="https://..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div style="grid-column:1/-1;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Address *</label><input id="ic-address" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Agreement Start *</label><input id="ic-start" type="date" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Agreement End *</label><input id="ic-end" type="date" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Status</label><select id="ic-status" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Notes</label><input id="ic-notes" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button onclick="insSave()" style="flex:1;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> <span id="ins-save-lbl">Save Company</span></button>
      <button onclick="insCloseModal()" style="padding:10px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<script>
const insAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/companies';
function iEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function insLoad(){
  const r=await fetch(insAPI);const j=await r.json();
  const tbody=document.getElementById('ins-tbody');
  const companies = j.data?.companies || j.data || [];
  if(j.success && companies.length){
    tbody.innerHTML=companies.map(c=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:8px 10px;font-weight:600;">${iEsc(c.name)}</td>
      <td style="padding:8px 10px;font-family:monospace;">${iEsc(c.code||'—')}</td>
      <td style="padding:8px 10px;font-size:12px;">${iEsc(c.contact_person||'—')}</td>
      <td style="padding:8px 10px;font-size:12px;">${iEsc(c.phone||'—')}</td>
      <td style="padding:8px 10px;font-size:12px;">${iEsc(c.email||'—')}</td>
      <td style="padding:8px 10px;text-align:center;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;background:${c.status==='active'?'#d1fae5':'#fee2e2'};color:${c.status==='active'?'#065f46':'#991b1b'};">${c.status}</span></td>
      <td style="padding:8px 10px;text-align:center;">
        <button onclick="insEdit(${c.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:3px;" title="Edit"><i class="fas fa-edit"></i></button>
        <button onclick="insDel(${c.id},'${iEsc(c.name)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:3px;" title="Delete"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
  } else tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No insurance companies.</td></tr>';
}

function insOpenModal(d=null){
  document.getElementById('ic-id').value=d?d.id:'';
  document.getElementById('ic-name').value=d?d.name:'';
  document.getElementById('ic-code').value=d?d.code:'';
  document.getElementById('ic-contact').value=d?d.contact_person:'';
  document.getElementById('ic-phone').value=d?d.phone:'';
  document.getElementById('ic-email').value=d?d.email:'';
  document.getElementById('ic-website').value=d?d.website:'';
  document.getElementById('ic-address').value=d?d.address:'';
  document.getElementById('ic-start').value=d?d.agreement_start_date:'';
  document.getElementById('ic-end').value=d?d.agreement_end_date:'';
  document.getElementById('ic-status').value=d?d.status:'active';
  document.getElementById('ic-notes').value=d?d.notes:'';
  document.getElementById('ins-modal-title').textContent=d?'Edit Insurance Company':'Add Insurance Company';
  document.getElementById('ins-save-lbl').textContent=d?'Update Company':'Save Company';
  document.getElementById('ins-alert').style.display='none';
  document.getElementById('ins-modal').style.display='flex';
}
function insCloseModal(){document.getElementById('ins-modal').style.display='none';}

async function insEdit(id){
  try{const r=await fetch(insAPI+'&id='+id);const j=await r.json();if(j.success)insOpenModal(j.data);else alert('Failed to load');}catch(e){alert('Error');}
}
async function insDel(id,name){
  if(!confirm(`Delete company "${name}"? This cannot be undone.`))return;
  try{const r=await fetch(insAPI+'&id='+id,{method:'DELETE'});const j=await r.json();j.success?insLoad():alert(j.message);}catch(e){alert('Failed');}
}

async function insSave(){
  const id=document.getElementById('ic-id').value;
  const al=document.getElementById('ins-alert');al.style.display='none';
  const name=document.getElementById('ic-name').value.trim();
  if(!name){al.textContent='Company name is required.';al.style.display='block';return;}
  const body={
    name:name,
    code:document.getElementById('ic-code').value.trim(),
    contact_person:document.getElementById('ic-contact').value.trim(),
    phone:document.getElementById('ic-phone').value.trim(),
    email:document.getElementById('ic-email').value.trim(),
    website:document.getElementById('ic-website').value.trim(),
    address:document.getElementById('ic-address').value.trim(),
    agreement_start_date:document.getElementById('ic-start').value,
    agreement_end_date:document.getElementById('ic-end').value,
    status:document.getElementById('ic-status').value,
    notes:document.getElementById('ic-notes').value.trim()
  };
  try{
    const url=id?insAPI+'&id='+id:insAPI;
    const r=await fetch(url,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){insCloseModal();insLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}
  }catch(err){al.textContent='Request failed';al.style.display='block';}
}
insLoad();
</script>

<?php elseif ($subModule == 'rates'): ?>
<!-- ====== INSURANCE RATES ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-percentage" style="color:var(--primary-color);"></i> Insurance Rates</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="rateOpenModal()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> Add Rate</button>
      <button onclick="rateLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">Insurance</th><th style="padding:10px;text-align:left;">Package/Plan</th><th style="padding:10px;text-align:center;">Coverage Rate (%)</th><th style="padding:10px;text-align:right;">Coverage Limit</th><th style="padding:10px;text-align:center;">Status</th><th style="padding:10px;text-align:center;">Actions</th></tr></thead>
      <tbody id="rate-tbody"><tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>

<!-- Rate Add/Edit Modal -->
<div id="rate-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:500px;max-width:90vw;padding:24px;position:relative;">
    <button onclick="rateCloseModal()" style="position:absolute;top:14px;right:14px;border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 id="rate-modal-title" style="font-size:16px;font-weight:600;margin-bottom:16px;">Add Insurance Rate</h3>
    <div id="rate-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <input type="hidden" id="rt-id">
    <input type="hidden" id="rt-code">
    <input type="hidden" id="rt-effdate">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div style="grid-column:1/-1;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Insurance Company *</label><select id="rt-company" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Select insurance...</option></select></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Plan/Package Name *</label><input id="rt-name" placeholder="e.g. CBHI Basic" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Coverage Rate (%)</label><input type="number" id="rt-rate" placeholder="e.g. 85" min="0" max="100" value="85" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Coverage Limit (RWF)</label><input type="number" id="rt-limit" placeholder="e.g. 500000" value="0" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Status</label><select id="rt-status" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button onclick="rateSave()" id="rate-save-btn" style="flex:1;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> <span id="rate-save-lbl">Save Rate</span></button>
      <button onclick="rateCloseModal()" style="padding:10px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<script>
const pkgAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/packages';
const insAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/companies';
function iEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function rateLoad(){
  const r=await fetch(pkgAPI);const j=await r.json();
  const tbody=document.getElementById('rate-tbody');
  const packages = j.data?.packages || j.data || [];
  if(j.success && packages.length){
    tbody.innerHTML=packages.map(p=>{
      const coverageRate = 100 - parseFloat(p.copayment_percentage||0);
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;font-weight:500;">${iEsc(p.company_name||'—')}</td>
        <td style="padding:8px 10px;">${iEsc(p.name)}</td>
        <td style="padding:8px 10px;text-align:center;font-size:16px;font-weight:700;color:var(--primary-color);">${coverageRate}%</td>
        <td style="padding:8px 10px;text-align:right;font-weight:500;">${parseFloat(p.coverage_limit||0).toLocaleString()} RWF</td>
        <td style="padding:8px 10px;text-align:center;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;background:${p.status==='active'?'#d1fae5':'#fee2e2'};color:${p.status==='active'?'#065f46':'#991b1b'};">${p.status}</span></td>
        <td style="padding:8px 10px;text-align:center;">
          <button onclick="rateEdit(${p.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:14px;padding:3px;" title="Edit"><i class="fas fa-edit"></i></button>
          <button onclick="rateDel(${p.id},'${iEsc(p.name)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:3px;" title="Delete"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join('');
  } else tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);">No insurance rates configured.</td></tr>';
}

async function loadCompanies(selectedId){
  try{
    const r=await fetch(insAPI);const j=await r.json();
    const companies=j.data?.companies||j.data||[];
    document.getElementById('rt-company').innerHTML='<option value="">Select insurance...</option>'+companies.map(c=>`<option value="${c.id}" ${c.id==selectedId?'selected':''}>${iEsc(c.name)}</option>`).join('');
  }catch(e){}
}

function rateOpenModal(d=null){
  document.getElementById('rt-id').value=d?d.id:'';
  document.getElementById('rt-code').value=d?d.code:'';
  document.getElementById('rt-effdate').value=d?d.effective_date:new Date().toISOString().slice(0,10);
  document.getElementById('rt-name').value=d?d.name:'';
  const coverageRate=d?(100-parseFloat(d.copayment_percentage||0)):85;
  document.getElementById('rt-rate').value=coverageRate;
  document.getElementById('rt-limit').value=d?d.coverage_limit:0;
  document.getElementById('rt-status').value=d?d.status:'active';
  document.getElementById('rate-modal-title').textContent=d?'Edit Insurance Rate':'Add Insurance Rate';
  document.getElementById('rate-save-lbl').textContent=d?'Update Rate':'Save Rate';
  document.getElementById('rate-alert').style.display='none';
  loadCompanies(d?d.company_id:'');
  document.getElementById('rate-modal').style.display='flex';
}
function rateCloseModal(){document.getElementById('rate-modal').style.display='none';}

async function rateEdit(id){
  try{const r=await fetch(pkgAPI+'&id='+id);const j=await r.json();if(j.success){rateOpenModal(j.data);}else alert('Failed to load');}catch(e){alert('Error loading rate');}
}

async function rateDel(id,name){
  if(!confirm(`Delete rate "${name}"? This cannot be undone.`))return;
  try{const r=await fetch(pkgAPI+'&id='+id,{method:'DELETE'});const j=await r.json();j.success?rateLoad():alert(j.message);}catch(e){alert('Delete failed');}
}

async function rateSave(){
  const id=document.getElementById('rt-id').value;
  const company=document.getElementById('rt-company').value;
  const name=document.getElementById('rt-name').value.trim();
  const rate=parseFloat(document.getElementById('rt-rate').value)||0;
  const al=document.getElementById('rate-alert');
  al.style.display='none';
  if(!company||!name){al.textContent='Select insurance and enter plan name.';al.style.display='block';return;}
  const copay=100-rate;
  const body={company_id:parseInt(company),name:name,copayment_percentage:copay,coverage_limit:parseFloat(document.getElementById('rt-limit').value)||0,status:document.getElementById('rt-status').value};
  // For PUT, we need code and effective_date
  if(id){
    body.code=document.getElementById('rt-code')?.value||name.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,6)+'-'+id;
    body.effective_date=document.getElementById('rt-effdate')?.value||new Date().toISOString().slice(0,10);
  }
  try{
    const url=id?pkgAPI+'&id='+id:pkgAPI;
    const r=await fetch(url,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){rateCloseModal();rateLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}
  }catch(err){al.textContent='Request failed';al.style.display='block';}
}

rateLoad();
</script>
<?php elseif ($subModule == 'receivables'): ?>
<!-- ====== INSURANCE RECEIVABLES ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-invoice-dollar" style="color:var(--primary-color);"></i> Insurance Receivables</h3>
    <button onclick="recvLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i> Refresh</button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px;text-align:left;">Insurance Company</th>
        <th style="padding:10px;text-align:left;">Account</th>
        <th style="padding:10px;text-align:right;">Balance (RWF)</th>
        <th style="padding:10px;text-align:center;">Action</th>
      </tr></thead>
      <tbody id="recv-tbody"><tr><td colspan="4" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>

<!-- ====== NON-INSURED UNPAID MODALS (months, then patients of a month) ====== -->
<div id="ni-modal" onclick="if(event.target===this)this.style.display='none'" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;padding:24px;margin:auto;position:relative;">
    <button onclick="document.getElementById('ni-modal').style.display='none'" style="position:absolute;top:14px;right:14px;border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 style="font-size:16px;font-weight:600;margin:0 0 12px 0;"><i class="fas fa-calendar-alt" style="color:#d97706;"></i> Non-insured unpaid by month <span style="font-size:12px;color:var(--gray-500);">(closed visits)</span></h3>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:var(--gray-50);"><th style="padding:9px 12px;text-align:left;">Month</th><th style="padding:9px 12px;text-align:right;">Patients</th><th style="padding:9px 12px;text-align:right;">Unpaid (RWF)</th><th style="padding:9px 12px;"></th></tr></thead>
        <tbody id="ni-tbody"></tbody>
      </table>
    </div>
  </div>
</div>
<div id="ni2-modal" onclick="if(event.target===this)this.style.display='none'" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.5);align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px;">
  <div style="background:white;border-radius:12px;width:900px;max-width:95vw;padding:24px;margin:auto;position:relative;">
    <button onclick="document.getElementById('ni2-modal').style.display='none'" style="position:absolute;top:14px;right:14px;border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 style="font-size:16px;font-weight:600;margin:0 0 12px 0;"><i class="fas fa-users" style="color:#0891b2;"></i> Non-insured unpaid — <span id="ni2-month"></span></h3>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:var(--gray-50);">
          <th style="padding:9px 12px;text-align:left;">Patient</th><th style="padding:9px 12px;text-align:left;">Patient No.</th><th style="padding:9px 12px;text-align:left;">Phone</th>
          <th style="padding:9px 12px;text-align:right;">Visits</th><th style="padding:9px 12px;text-align:right;">Billed (RWF)</th><th style="padding:9px 12px;text-align:right;">Paid (RWF)</th><th style="padding:9px 12px;text-align:right;">Unpaid (RWF)</th>
        </tr></thead>
        <tbody id="ni2-tbody"></tbody>
        <tfoot id="ni2-tfoot"></tfoot>
      </table>
    </div>
  </div>
</div>


<!-- ====== MONTHS MODAL ====== -->
<div id="months-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:660px;max-width:95vw;max-height:90vh;overflow-y:auto;padding:24px;position:relative;">
    <button onclick="closeMonthsModal()" style="position:absolute;top:14px;right:14px;border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 style="font-size:16px;font-weight:600;margin:0 0 2px 0;"><i class="fas fa-calendar-alt" style="color:var(--primary-color);"></i> Monthly Invoice Status</h3>
    <p style="font-size:13px;color:var(--gray-500);margin:0 0 16px 0;" id="months-modal-company"></p>

    <!-- Year filter + summary bar -->
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:10px 14px;background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-200);">
      <label style="font-size:12px;font-weight:600;white-space:nowrap;">Filter by Year:</label>
      <select id="months-year-filter" onchange="renderMonthsList()" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></select>
      <div style="margin-left:auto;display:flex;gap:16px;font-size:12px;">
        <span><i class="fas fa-hourglass-half" style="color:#f59e0b;"></i> <span id="months-count-pending" style="font-weight:600;">0</span> pending</span>
        <span><i class="fas fa-check-circle" style="color:#059669;"></i> <span id="months-count-paid" style="font-weight:600;">0</span> paid</span>
      </div>
    </div>

    <!-- Months list -->
    <div id="months-list-container" style="display:flex;flex-direction:column;gap:6px;">
      <div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
    </div>
  </div>
</div>

<!-- Settlement Modal (hidden) -->
<div id="settle-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-handshake" style="color:var(--primary-color);"></i> Settle Insurance Payment</h3>
      <button onclick="closeSettle()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>

    <div style="background:var(--gray-50);padding:12px;border-radius:8px;margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;">
        <span style="font-weight:600;" id="stl-company-name">—</span>
        <span style="font-weight:700;font-size:16px;color:var(--primary-color);" id="stl-balance">0 RWF</span>
      </div>
      <small style="color:var(--gray-500);">Remaining balance for selected month</small>
    </div>

    <!-- Month/Year Selection -->
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:12px;background:#eff6ff;">
      <h4 style="font-size:13px;font-weight:600;margin-bottom:8px;color:#1e40af;"><i class="fas fa-calendar-alt"></i> Settlement Month</h4>
      <p style="font-size:11px;color:var(--gray-500);margin-bottom:8px;">Select the month you are settling. Amount will be based on that month's invoice.</p>
      <div style="display:flex;gap:12px;">
        <select id="stl-month" onchange="stlFetchMonthly()" style="flex:1;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
          <option value="1">January</option><option value="2">February</option><option value="3">March</option>
          <option value="4">April</option><option value="5">May</option><option value="6">June</option>
          <option value="7">July</option><option value="8">August</option><option value="9">September</option>
          <option value="10">October</option><option value="11">November</option><option value="12">December</option>
        </select>
        <select id="stl-year" onchange="stlFetchMonthly()" style="width:100px;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></select>
      </div>
    </div>

    <!-- Payment amount (supports partial payments) -->
    <div style="border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:12px;background:#f0fdf4;">
      <h4 style="font-size:13px;font-weight:600;margin-bottom:6px;color:#065f46;"><i class="fas fa-coins"></i> Amount This Payment Covers</h4>
      <p style="font-size:11px;color:var(--gray-500);margin-bottom:8px;">Less than the remaining balance = partial payment. The rest stays open and this payment is saved as proof.</p>
      <div style="display:flex;gap:8px;align-items:center;">
        <input type="number" id="stl-pay" min="1" step="1" style="flex:1;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" oninput="recalcSettle()">
        <button type="button" onclick="stlPayFull()" style="padding:8px 12px;background:#059669;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;white-space:nowrap;">Full remaining</button>
      </div>
      <small id="stl-progress" style="display:block;margin-top:6px;color:var(--gray-600);"></small>
    </div>

    <!-- Step 1: Write-off Amount -->
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:12px;">
      <h4 style="font-size:13px;font-weight:600;margin-bottom:8px;color:#b91c1c;"><i class="fas fa-exclamation-triangle"></i> 1. Write-off (Loss)</h4>
      <p style="font-size:11px;color:var(--gray-500);margin-bottom:8px;">Amount the insurance refuses to pay (over-billing dispute). Only on the final payment. Debit: Loss Account (1024), Credit: Insurance Receivable</p>
      <div style="display:flex;gap:12px;align-items:center;">
        <label style="font-size:12px;font-weight:500;white-space:nowrap;">Write-off Amount:</label>
        <input type="number" id="stl-writeoff" value="0" min="0" step="1" style="flex:1;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" oninput="recalcSettle()">
      </div>
    </div>

    <!-- Step 2: Withholding Tax (3%) -->
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h4 style="font-size:13px;font-weight:600;color:#d97706;margin:0;"><i class="fas fa-percentage"></i> 2. Withholding Tax (3%)</h4>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--gray-700);cursor:pointer;">
          <input type="checkbox" id="stl-wht-enabled" checked onchange="recalcSettle()" style="width:16px;height:16px;cursor:pointer;">
          Include WHT
        </label>
      </div>
      <p style="font-size:11px;color:var(--gray-500);margin-bottom:8px;">3% withholding tax deducted by insurance from the payable amount. Uncheck if this insurance does not apply WHT.</p>
      <div style="display:flex;gap:12px;align-items:center;">
        <label style="font-size:12px;font-weight:500;white-space:nowrap;">Tax Amount:</label>
        <input type="text" id="stl-wht" readonly style="flex:1;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:var(--gray-50);font-weight:600;">
      </div>
    </div>

    <!-- Step 3: Bank Payment -->
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:12px;">
      <h4 style="font-size:13px;font-weight:600;margin-bottom:8px;color:#059669;"><i class="fas fa-university"></i> 3. Bank Payment Received</h4>
      <p style="font-size:11px;color:var(--gray-500);margin-bottom:8px;">Net amount received from insurance into bank. Debit: Bank, Credit: Insurance Receivable</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="font-size:12px;font-weight:500;display:block;margin-bottom:4px;">Bank Account:</label>
          <select id="stl-bank" onchange="recalcSettle()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <option value="">Loading banks...</option>
          </select>
        </div>
        <div>
          <label style="font-size:12px;font-weight:500;display:block;margin-bottom:4px;">Net Amount (auto):</label>
          <input type="text" id="stl-net" readonly style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:var(--gray-50);font-weight:600;">
        </div>
      </div>
    </div>

    <!-- Summary & Entry Preview -->
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:16px;">
      <h4 style="font-size:12px;font-weight:600;margin-bottom:6px;color:#065f46;">Settlement Summary</h4>
      <table style="width:100%;font-size:12px;">
        <tr><td>Original Balance:</td><td style="text-align:right;font-weight:600;" id="stl-sum-balance">0</td></tr>
        <tr><td style="color:#b91c1c;">− Write-off (Loss):</td><td style="text-align:right;font-weight:600;color:#b91c1c;" id="stl-sum-writeoff">0</td></tr>
        <tr><td style="color:#d97706;">− Withholding Tax (3%):</td><td style="text-align:right;font-weight:600;color:#d97706;" id="stl-sum-wht">0</td></tr>
        <tr style="border-top:1px solid #bbf7d0;"><td style="font-weight:700;padding-top:4px;">= Bank Payment:</td><td style="text-align:right;font-weight:700;padding-top:4px;color:#059669;" id="stl-sum-net">0</td></tr>
      </table>
    </div>

    <!-- Journal Entry Preview -->
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:16px;">
      <h4 style="font-size:12px;font-weight:600;margin-bottom:8px;color:#1e40af;"><i class="fas fa-book"></i> Journal Entry Preview</h4>
      <div style="border:1px solid #bfdbfe;border-radius:6px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:11px;">
          <thead><tr style="background:#dbeafe;">
            <th style="padding:6px 8px;text-align:left;font-weight:600;">#</th>
            <th style="padding:6px 8px;text-align:left;font-weight:600;">Account</th>
            <th style="padding:6px 8px;text-align:right;font-weight:600;">Debit (RWF)</th>
            <th style="padding:6px 8px;text-align:right;font-weight:600;">Credit (RWF)</th>
          </tr></thead>
          <tbody id="stl-entry-preview">
          </tbody>
          <tfoot>
            <tr style="background:#dbeafe;font-weight:700;">
              <td colspan="2" style="padding:6px 8px;">TOTAL</td>
              <td style="padding:6px 8px;text-align:right;" id="stl-prev-total-dr">0</td>
              <td style="padding:6px 8px;text-align:right;" id="stl-prev-total-cr">0</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Reference & Date -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
      <div>
        <label style="font-size:12px;font-weight:500;display:block;margin-bottom:4px;">Entry Date:</label>
        <input type="date" id="stl-date" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="font-size:12px;font-weight:500;display:block;margin-bottom:4px;">Reference/Note:</label>
        <input type="text" id="stl-ref" placeholder="e.g. Insurance settlement Q2" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>

    <button onclick="submitSettle()" id="stl-submit-btn" style="width:100%;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check-circle"></i> Post Settlement Journals</button>
  </div>
</div>

<!-- Invoice Generation Modal -->
<div id="inv-gen-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:480px;max-width:95vw;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-invoice" style="color:#1e40af;"></i> Generate Monthly Invoice</h3>
      <button onclick="closeInvoiceGen()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="background:var(--gray-50);padding:12px;border-radius:8px;margin-bottom:16px;">
      <span style="font-weight:600;" id="inv-company-name">—</span>
    </div>
    <p style="font-size:12px;color:var(--gray-500);margin-bottom:12px;">Select a single month to generate the invoice. Each invoice covers one month only.</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Month *</label>
        <select id="inv-month" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="1">January</option><option value="2">February</option><option value="3">March</option>
          <option value="4">April</option><option value="5">May</option><option value="6">June</option>
          <option value="7">July</option><option value="8">August</option><option value="9">September</option>
          <option value="10">October</option><option value="11">November</option><option value="12">December</option>
        </select>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Year *</label>
        <select id="inv-year" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></select>
      </div>
    </div>

    <!-- Bank Details -->
    <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px;margin-bottom:16px;">
      <p style="font-size:12px;font-weight:700;color:#1e40af;margin:0 0 10px 0;"><i class="fas fa-university"></i> Bank Details (printed on invoice)</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
        <div>
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Bank Name *</label>
          <input type="text" id="inv-bank-name" value="Bank of Kigali" placeholder="e.g. Bank of Kigali" style="width:100%;padding:7px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Account Number *</label>
          <input type="text" id="inv-bank-account" value="000460777333283" placeholder="e.g. 000460777333283" style="width:100%;padding:7px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;box-sizing:border-box;">
        </div>
      </div>
      <div>
        <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Account Name *</label>
        <input type="text" id="inv-bank-acname" placeholder="Account holder name" style="width:100%;padding:7px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;box-sizing:border-box;">
      </div>
    </div>

    <!-- Signatory -->
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:16px;">
      <p style="font-size:12px;font-weight:700;color:#065f46;margin:0 0 10px 0;"><i class="fas fa-signature"></i> Signatory (printed on invoice)</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div>
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Full Name *</label>
          <input type="text" id="inv-sig-name" value="MUKULIRA NADINE" placeholder="e.g. MUKULIRA NADINE" style="width:100%;padding:7px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;box-sizing:border-box;">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:3px;">Title *</label>
          <input type="text" id="inv-sig-title" value="CHIEF EXECUTIVE OFFICER" placeholder="e.g. CHIEF EXECUTIVE OFFICER" style="width:100%;padding:7px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;box-sizing:border-box;">
        </div>
      </div>
    </div>

    <button onclick="generateInvoice()" id="inv-gen-btn" style="width:100%;padding:12px;background:#1e40af;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-file-invoice"></i> Generate Invoice</button>
  </div>
</div>

<!-- Invoice Preview/Print Modal -->
<div id="inv-preview-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:850px;max-width:95vw;max-height:95vh;overflow-y:auto;padding:0;">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid var(--gray-200);position:sticky;top:0;background:white;z-index:1;">
      <h3 style="font-size:16px;font-weight:600;">Invoice Documents</h3>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button onclick="downloadInvoicePDF()" style="padding:8px 14px;background:#1e40af;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-file-pdf"></i> Invoice PDF</button>
        <button onclick="downloadAttachmentPDF()" style="padding:8px 14px;background:#7c3aed;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-paperclip"></i> Attachment PDF</button>
        <button onclick="downloadExcel()" style="padding:8px 14px;background:#059669;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-file-excel"></i> Excel</button>
        <button onclick="printInvoice()" style="padding:8px 14px;background:var(--gray-600);color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print All</button>
        <button onclick="closeInvoicePreview()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
      </div>
    </div>
    <!-- Tab switching between Invoice and Attachment preview -->
    <div style="padding:12px 24px 0;display:flex;gap:8px;">
      <button id="prev-tab-inv" onclick="showPreviewTab('invoice')" style="padding:6px 14px;border:none;border-radius:6px 6px 0 0;font-size:12px;font-weight:600;cursor:pointer;background:#1e40af;color:white;">Invoice Letter</button>
      <button id="prev-tab-att" onclick="showPreviewTab('attachment')" style="padding:6px 14px;border:none;border-radius:6px 6px 0 0;font-size:12px;font-weight:600;cursor:pointer;background:var(--gray-100);color:var(--gray-600);">Attachment (Items)</button>
    </div>
    <div id="inv-preview-content" style="padding:40px;"></div>
    <div id="att-preview-content" style="padding:40px;display:none;"></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
const recvAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/companies';
const coaAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts';
function iEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function fmt(n){return parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0});}

let settleData = {}; // holds current settlement context
let niRows = [];            // non-insured patients with unpaid invoices on closed visits

async function recvLoad(){
  const tbody=document.getElementById('recv-tbody');
  tbody.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
  try{
    const r=await fetch(recvAPI);const j=await r.json();
    const companies=j.data?.companies||j.data||[];
    try{ const nj=await (await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/settlement&action=non_insured_unpaid')).json(); niRows=nj.success?nj.data:[]; }catch(e){ niRows=[]; }
    if(!companies.length){tbody.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:var(--gray-400);">No insurance companies found.</td></tr>';return;}
    
    // For each company, get their receivable balance in parallel
    const rowPromises = companies.map(async c => {
      try {
        const ar = await fetch(coaAPI+'&action=insurance_receivable&insurance_id='+c.id);
        const aj = await ar.json();
        if (aj.success && aj.data) return {company: c, account: aj.data};
      } catch(e) {}
      return null;
    });
    const results = await Promise.all(rowPromises);
    const rows = results.filter(Boolean);
    
    if(!rows.length){tbody.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:var(--gray-400);">No receivable accounts found.</td></tr>';return;}
    
    const niTotal=niRows.reduce((a,p)=>a+parseFloat(p.unpaid||0),0);
    const niRow=niRows.length?`<tr style="border-bottom:1px solid var(--gray-100);background:#fffbeb;">
      <td style="padding:10px;font-weight:600;">Non-insured (Self-pay)</td>
      <td style="padding:10px;font-size:12px;color:var(--gray-500);">Unpaid on closed visits &middot; ${new Set(niRows.map(p=>p.id)).size} patients</td>
      <td style="padding:10px;text-align:right;font-weight:700;font-size:14px;color:#d97706;">${fmt(niTotal)} RWF</td>
      <td style="padding:10px;text-align:center;"><button onclick="niOpen()" style="padding:6px 12px;background:#0891b2;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-users"></i> Patients</button></td>
    </tr>`:'';
    tbody.innerHTML=niRow+rows.map(r=>{
      const bal=parseFloat(r.account.balance||0);
      const color=bal>0?'#059669':'var(--gray-400)';
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px;font-weight:600;">${iEsc(r.company.name)}</td>
        <td style="padding:10px;font-size:12px;font-family:monospace;">${iEsc(r.account.account_code)} - ${iEsc(r.account.account_name)}</td>
        <td style="padding:10px;text-align:right;font-weight:700;font-size:14px;color:${color};">
          ${fmt(bal)} RWF
        </td>
        <td style="padding:10px;text-align:center;">
          <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">
            <button onclick="openMonthsPanel(${r.company.id},'${iEsc(r.company.name)}',${r.account.id},${bal})" style="padding:6px 12px;background:#7c3aed;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-calendar-alt"></i> Months</button>
            <button onclick="openInvoiceGen(${r.company.id},'${iEsc(r.company.name)}')" style="padding:6px 12px;background:#1e40af;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-file-invoice"></i> Invoice</button>
            ${bal>0?`<button onclick="openSettle(${r.company.id},'${iEsc(r.company.name)}',${r.account.id},${bal})" style="padding:6px 12px;background:#059669;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-handshake"></i> Settle</button>`:''}
          </div>
        </td>
      </tr>`;
    }).join('');
  }catch(e){
    tbody.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:#dc2626;">Error loading: '+e.message+'</td></tr>';
  }
}

// ============ NON-INSURED UNPAID MODAL ============
function niMonthName(ym){ const [y,m]=ym.split('-'); return new Date(y,m-1,1).toLocaleString('en-US',{month:'long'})+' '+y; }
function niOpen(){
  const by={};
  niRows.forEach(p=>{ const g=by[p.ym]=by[p.ym]||{n:0,unpaid:0}; g.n++; g.unpaid+=parseFloat(p.unpaid||0); });
  document.getElementById('ni-tbody').innerHTML=Object.keys(by).sort().reverse().map(ym=>`<tr style="border-top:1px solid var(--gray-100);">
    <td style="padding:8px 12px;font-weight:600;">${niMonthName(ym)}</td>
    <td style="padding:8px 12px;text-align:right;">${by[ym].n}</td>
    <td style="padding:8px 12px;text-align:right;font-weight:700;color:#d97706;">${fmt(by[ym].unpaid)}</td>
    <td style="padding:8px 12px;text-align:center;"><button onclick="niOpenMonth('${ym}')" style="padding:6px 12px;background:#0891b2;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-eye"></i> See all</button></td>
  </tr>`).join('');
  document.getElementById('ni-modal').style.display='flex';
}
function niOpenMonth(ym){
  const list=niRows.filter(p=>p.ym===ym);
  document.getElementById('ni2-month').textContent=niMonthName(ym)+' ('+list.length+' patients)';
  document.getElementById('ni2-tbody').innerHTML=list.map(p=>`<tr style="border-top:1px solid var(--gray-100);">
    <td style="padding:8px 12px;font-weight:600;">${iEsc((p.first_name||'')+' '+(p.last_name||''))}</td>
    <td style="padding:8px 12px;font-family:monospace;color:var(--primary-color);">${iEsc(p.patient_no)}</td>
    <td style="padding:8px 12px;">${iEsc(p.phone||'—')}</td>
    <td style="padding:8px 12px;text-align:right;">${p.visits}</td>
    <td style="padding:8px 12px;text-align:right;">${fmt(p.billed)}</td>
    <td style="padding:8px 12px;text-align:right;">${fmt(p.paid)}</td>
    <td style="padding:8px 12px;text-align:right;font-weight:700;color:#d97706;">${fmt(p.unpaid)}</td>
  </tr>`).join('');
  const t=k=>list.reduce((a,p)=>a+parseFloat(p[k]||0),0);
  document.getElementById('ni2-tfoot').innerHTML=`<tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);font-weight:700;"><td colspan="4" style="padding:10px 12px;text-align:right;">TOTAL</td><td style="padding:10px 12px;text-align:right;">${fmt(t('billed'))}</td><td style="padding:10px 12px;text-align:right;">${fmt(t('paid'))}</td><td style="padding:10px 12px;text-align:right;color:#d97706;">${fmt(t('unpaid'))}</td></tr>`;
  document.getElementById('ni2-modal').style.display='flex';
}


// ============ MONTHS MODAL ============
let monthsPanelData = {};
let monthsAllData = []; // all months from API

async function openMonthsPanel(companyId, companyName, accountId, balance){
  monthsPanelData = {companyId, companyName, accountId, balance};
  document.getElementById('months-modal-company').textContent = companyName;
  document.getElementById('months-modal').style.display = 'flex';
  document.getElementById('months-list-container').innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  await loadMonths();
}

function closeMonthsModal(){
  document.getElementById('months-modal').style.display = 'none';
}

async function loadMonths(){
  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/settlement&action=paid_pending_months&insurance_id='+monthsPanelData.companyId);
    const j = await r.json();
    if(!j.success){
      document.getElementById('months-list-container').innerHTML='<div style="padding:20px;text-align:center;color:#dc2626;"><i class="fas fa-exclamation-circle"></i> '+iEsc(j.message)+'</div>';
      return;
    }

    // Merge pending + paid into one list sorted by year desc, month desc
    monthsAllData = [...j.data.pending, ...j.data.paid].sort((a,b)=> b.year!==a.year ? b.year-a.year : b.month-a.month);

    // Build year filter options
    const years = [...new Set(monthsAllData.map(m=>m.year))].sort((a,b)=>b-a);
    const yearSel = document.getElementById('months-year-filter');
    yearSel.innerHTML = '<option value="">All Years</option>' + years.map(y=>`<option value="${y}" ${y===new Date().getFullYear()?'selected':''}>${y}</option>`).join('');

    renderMonthsList();
  }catch(e){
    document.getElementById('months-list-container').innerHTML='<div style="padding:20px;text-align:center;color:#dc2626;">Error: '+e.message+'</div>';
  }
}

function renderMonthsList(){
  const yearFilter = parseInt(document.getElementById('months-year-filter').value) || 0;
  const filtered = yearFilter ? monthsAllData.filter(m=>m.year===yearFilter) : monthsAllData;

  const pendingCount = filtered.filter(m=>m.status!=='paid').length;
  const paidCount    = filtered.filter(m=>m.status==='paid').length;
  document.getElementById('months-count-pending').textContent = pendingCount;
  document.getElementById('months-count-paid').textContent    = paidCount;

  if(!filtered.length){
    document.getElementById('months-list-container').innerHTML =
      '<div style="padding:30px;text-align:center;color:var(--gray-400);">No invoices found'+(yearFilter?' for '+yearFilter:'')+'.</div>';
    return;
  }

  document.getElementById('months-list-container').innerHTML = filtered.map(m => {
    const isPaid = m.status === 'paid';
    const amountPaid = m.total_amount - m.pending_amount;
    const histBtn = m.settled_amount>0 ? `<button onclick="showSettlePayments(${m.month},${m.year},'${iEsc(m.month_name)}')" style="padding:6px 12px;background:#7c3aed;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-receipt"></i> Payments</button>` : '';

    if(isPaid){
      return `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border:1px solid #bbf7d0;border-radius:8px;background:#f0fdf4;">
          <div style="display:flex;align-items:center;gap:12px;">
            <span style="padding:4px 10px;background:#d1fae5;color:#065f46;border-radius:12px;font-size:11px;font-weight:700;white-space:nowrap;"><i class="fas fa-check-circle"></i> PAID</span>
            <div>
              <span style="font-weight:600;font-size:14px;">${iEsc(m.month_name)} ${m.year}</span>
              <small style="display:block;color:var(--gray-500);margin-top:1px;">
                ${m.visit_count ? m.visit_count + ' visit' + (m.visit_count!==1?'s':'') + ' (' + m.invoice_count + ' invoice' + (m.invoice_count!==1?'s':'') + ')' : m.invoice_count + ' invoice' + (m.invoice_count!==1?'s':'')}
                &nbsp;·&nbsp; Total billed: <b>${fmt(m.total_amount)} RWF</b>
                &nbsp;·&nbsp; <span style="color:#059669;">Paid: <b>${fmt(amountPaid)} RWF</b></span>
              </small>
            </div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0;">${histBtn}<button onclick="closeMonthsModal();openInvoiceGenForMonth(${m.month},${m.year})" style="padding:6px 12px;background:#6b7280;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-file-invoice"></i> Invoice</button></div>
        </div>`;
    } else {
      return `
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border:1px solid #fde68a;border-radius:8px;background:#fffbeb;">
          <div style="display:flex;align-items:center;gap:12px;">
            <span style="padding:4px 10px;background:#fef3c7;color:#92400e;border-radius:12px;font-size:11px;font-weight:700;white-space:nowrap;"><i class="fas fa-hourglass-half"></i> ${m.status==='partial'?'PARTIAL':'PENDING'}</span>
            <div>
              <span style="font-weight:600;font-size:14px;">${iEsc(m.month_name)} ${m.year}</span>
              <small style="display:block;color:var(--gray-500);margin-top:1px;">
                ${m.visit_count ? m.visit_count + ' visit' + (m.visit_count!==1?'s':'') + ' (' + m.invoice_count + ' invoice' + (m.invoice_count!==1?'s':'') + ')' : m.invoice_count + ' invoice' + (m.invoice_count!==1?'s':'')}
                &nbsp;·&nbsp; Total billed: <b>${fmt(m.total_amount)} RWF</b>
                ${m.settled_amount>0?`&nbsp;·&nbsp; <span style="color:#059669;">Paid so far: <b>${fmt(m.settled_amount)} RWF</b></span>`:''}
                &nbsp;·&nbsp; <span style="color:#b45309;">Balance due: <b>${fmt(m.pending_amount)} RWF</b></span>
              </small>
            </div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0;">
            ${histBtn}
            <button onclick="closeMonthsModal();openInvoiceGenForMonth(${m.month},${m.year})" style="padding:6px 12px;background:#1e40af;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-file-invoice"></i> Invoice</button>
            <button onclick="closeMonthsModal();openSettleForMonth(${m.month},${m.year},${m.pending_amount})" style="padding:6px 12px;background:#059669;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-handshake"></i> Settle</button>
          </div>
        </div>`;
    }
  }).join('');
}

// Printable proof of every partial payment for a month
async function showSettlePayments(month, year, monthName){
  const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/settlement&action=month_payments&insurance_id='+monthsPanelData.companyId+'&month='+month+'&year='+year);
  const j = await r.json();
  if(!j.success) return alert(j.message);
  const rows = j.data;
  const sum = k => rows.reduce((a,p)=>a+parseFloat(p[k]),0);
  const w = window.open('','_blank');
  w.document.write(`<html><head><title>Payments ${iEsc(monthName)} ${year}</title><style>body{font-family:Arial;padding:24px}table{border-collapse:collapse;width:100%;font-size:13px}th,td{border:1px solid #ccc;padding:6px 8px;text-align:right}th:nth-child(-n+3),td:nth-child(-n+3){text-align:left}</style></head><body>
    <h2>${iEsc(monthsPanelData.companyName)} - Payments for ${iEsc(monthName)} ${year}</h2>
    ${rows.length?`<table><tr><th>#</th><th>Date</th><th>Bank / Ref</th><th>Bank received</th><th>WHT</th><th>Write-off</th><th>Cleared</th></tr>
    ${rows.map((p,i)=>`<tr><td>${i+1}</td><td>${p.entry_date}</td><td>${iEsc(p.bank_name||'')} ${iEsc(p.reference||'')}<br><small>${iEsc(p.journal_refs||'')}</small></td><td>${fmt(p.net_amount)}</td><td>${fmt(p.wht_amount)}</td><td>${fmt(p.writeoff_amount)}</td><td>${fmt(p.total_cleared)}</td></tr>`).join('')}
    <tr><th colspan="3">Total</th><th>${fmt(sum('net_amount'))}</th><th>${fmt(sum('wht_amount'))}</th><th>${fmt(sum('writeoff_amount'))}</th><th>${fmt(sum('total_cleared'))}</th></tr></table>`:'<p>No recorded payments (settled before payment tracking).</p>'}
    <p><button onclick="print()">Print</button></p></body></html>`);
  w.document.close();
}

function openInvoiceGenForMonth(month, year){
  openInvoiceGen(monthsPanelData.companyId, monthsPanelData.companyName);
  setTimeout(()=>{
    document.getElementById('inv-month').value = month;
    document.getElementById('inv-year').value  = year;
  }, 50);
}

function openSettleForMonth(month, year, pendingAmount){
  openSettle(monthsPanelData.companyId, monthsPanelData.companyName, monthsPanelData.accountId, monthsPanelData.balance);
  setTimeout(()=>{
    document.getElementById('stl-month').value = month;
    document.getElementById('stl-year').value  = year;
    stlFetchMonthly();
  }, 50);
}

async function loadBankAccounts(){
  try{
    const r=await fetch(coaAPI+'&action=cash_balances');
    const j=await r.json();
    const sel=document.getElementById('stl-bank');
    if(j.success && j.data?.accounts?.length){
      sel.innerHTML=j.data.accounts.map(a=>`<option value="${a.id}">${iEsc(a.account_name)} (${fmt(a.balance)} RWF)</option>`).join('');
    }else{
      sel.innerHTML='<option value="">No bank accounts found</option>';
    }
  }catch(e){
    document.getElementById('stl-bank').innerHTML='<option value="">Error loading</option>';
  }
}

function openSettle(companyId, companyName, accountId, balance){
  settleData={companyId, companyName, accountId, balance};
  document.getElementById('stl-company-name').textContent=companyName;
  document.getElementById('stl-balance').textContent=fmt(balance)+' RWF';
  document.getElementById('stl-writeoff').value=0;
  document.getElementById('stl-writeoff').max=balance;
  document.getElementById('stl-date').value=new Date().toISOString().split('T')[0];
  document.getElementById('stl-ref').value='';
  // Set month/year
  const now=new Date();
  document.getElementById('stl-month').value=now.getMonth()+1;
  const yearSel=document.getElementById('stl-year');
  yearSel.innerHTML='';
  for(let y=now.getFullYear();y>=now.getFullYear()-3;y--){
    yearSel.innerHTML+=`<option value="${y}" ${y===now.getFullYear()?'selected':''}>${y}</option>`;
  }
  document.getElementById('settle-modal').style.display='flex';
  loadBankAccounts();
  stlFetchMonthly();
}

async function stlFetchMonthly(){
  const month=parseInt(document.getElementById('stl-month').value);
  const year=parseInt(document.getElementById('stl-year').value);
  if(!month||!year) return;
  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/settlement&action=monthly_invoice&insurance_id='+settleData.companyId+'&month='+month+'&year='+year);
    const j=await r.json();
    if(j.success && j.data.total > 0){
      settleData.balance = j.data.remaining;
      settleData.month = month;
      settleData.year = year;
      document.getElementById('stl-balance').textContent = fmt(j.data.remaining)+' RWF';
      document.getElementById('stl-progress').textContent = 'Month total '+fmt(j.data.total)+' · already paid '+fmt(j.data.settled_amount||0)+' · remaining '+fmt(j.data.remaining)+' RWF';
      document.getElementById('stl-writeoff').value = 0;
      stlPayFull();
    } else {
      settleData.balance = 0;
      document.getElementById('stl-pay').value = '';
      document.getElementById('stl-progress').textContent = '';
      settleData.month = month;
      settleData.year = year;
      document.getElementById('stl-balance').textContent = '0 RWF (no invoice)';
      recalcSettle();
    }
  }catch(e){
    console.error('Failed to fetch monthly amount:', e);
  }
}

function closeSettle(){
  document.getElementById('settle-modal').style.display='none';
}

function stlPayFull(){
  document.getElementById('stl-pay').value=settleData.balance||'';
  recalcSettle();
}

// Amount this payment covers, capped at remaining; isFinal = clears the month (write-off allowed only then)
function stlPayState(){
  const remaining=settleData.balance||0;
  const pay=Math.min(parseFloat(document.getElementById('stl-pay').value)||0, remaining);
  const isFinal=remaining>0 && pay>=remaining-0.5;
  return {remaining, pay, isFinal};
}

function recalcSettle(){
  const {pay:balance,isFinal}=stlPayState();
  const woEl=document.getElementById('stl-writeoff');
  woEl.disabled=!isFinal;
  if(!isFinal) woEl.value=0;
  woEl.max=balance;
  const writeoff=parseFloat(woEl.value)||0;
  const whtEnabled=document.getElementById('stl-wht-enabled').checked;
  
  // After write-off, remaining balance
  const afterWriteoff=balance-writeoff;
  
  // WHT is 3% of the amount after write-off (only if enabled)
  const wht=whtEnabled?Math.floor(afterWriteoff*0.03):0;
  
  // Net bank payment = after write-off minus WHT (use exact value, no rounding)
  const net=afterWriteoff-wht;
  
  document.getElementById('stl-wht').value=whtEnabled?(fmt(wht)+' RWF'):'N/A (disabled)';
  document.getElementById('stl-net').value=fmt(net)+' RWF';
  
  // Summary
  document.getElementById('stl-sum-balance').textContent=fmt(balance)+' RWF'+(isFinal?' (final)':' (partial)');
  document.getElementById('stl-sum-writeoff').textContent=fmt(writeoff)+' RWF';
  document.getElementById('stl-sum-wht').textContent=whtEnabled?(fmt(wht)+' RWF'):'N/A';
  document.getElementById('stl-sum-net').textContent=fmt(net)+' RWF';
  
  // Journal Entry Preview
  const compName=settleData.companyName||'Insurance';
  const bankSel=document.getElementById('stl-bank');
  const bankName=bankSel.selectedOptions[0]?bankSel.selectedOptions[0].textContent:'Bank Account';
  let rows=[];
  let entryNum=1;
  let totalDr=0, totalCr=0;
  
  // Entry 1: Write-off
  if(writeoff>0){
    rows.push({num:entryNum, label:'<span style="color:#b91c1c;font-weight:600;">Entry '+entryNum+': Write-off</span>', account:'', dr:'', cr:'', isHeader:true});
    rows.push({num:'', account:'Loss Account (1024)', dr:fmt(writeoff), cr:'—'});
    rows.push({num:'', account:'Receivable - '+compName, dr:'—', cr:fmt(writeoff)});
    totalDr+=writeoff; totalCr+=writeoff;
    entryNum++;
  }
  
  // Entry 2: WHT
  if(wht>0){
    rows.push({num:entryNum, label:'<span style="color:#d97706;font-weight:600;">Entry '+entryNum+': Withholding Tax 3%</span>', account:'', dr:'', cr:'', isHeader:true});
    rows.push({num:'', account:'WHT Account (1025)', dr:fmt(wht), cr:'—'});
    rows.push({num:'', account:'Receivable - '+compName, dr:'—', cr:fmt(wht)});
    totalDr+=wht; totalCr+=wht;
    entryNum++;
  }
  
  // Entry 3: Bank Payment
  if(net>0){
    rows.push({num:entryNum, label:'<span style="color:#059669;font-weight:600;">Entry '+entryNum+': Bank Payment</span>', account:'', dr:'', cr:'', isHeader:true});
    rows.push({num:'', account:bankName, dr:fmt(net), cr:'—'});
    rows.push({num:'', account:'Receivable - '+compName, dr:'—', cr:fmt(net)});
    totalDr+=net; totalCr+=net;
  }
  
  const tbody=document.getElementById('stl-entry-preview');
  tbody.innerHTML=rows.map(r=>{
    if(r.isHeader){
      return `<tr style="background:#f0f9ff;"><td colspan="4" style="padding:5px 8px;">${r.label}</td></tr>`;
    }
    const isDr=r.dr!=='—';
    return `<tr style="border-bottom:1px solid #e0f2fe;">
      <td style="padding:4px 8px;"></td>
      <td style="padding:4px 8px;${isDr?'font-weight:600;':'padding-left:20px;color:var(--gray-600);'}">${isDr?r.account:'&nbsp;&nbsp;'+r.account}</td>
      <td style="padding:4px 8px;text-align:right;${isDr?'font-weight:600;color:#1e40af;':''}">${r.dr}</td>
      <td style="padding:4px 8px;text-align:right;${!isDr?'font-weight:600;color:#1e40af;':''}">${r.cr}</td>
    </tr>`;
  }).join('');
  
  document.getElementById('stl-prev-total-dr').textContent=fmt(totalDr)+' RWF';
  document.getElementById('stl-prev-total-cr').textContent=fmt(totalCr)+' RWF';
}

async function submitSettle(){
  const {remaining,pay:balance,isFinal}=stlPayState();
  if(balance<=0)return alert('Enter the amount this payment covers (remaining: '+fmt(remaining)+' RWF).');
  const writeoff=parseFloat(document.getElementById('stl-writeoff').value)||0;
  const bankId=document.getElementById('stl-bank').value;
  const entryDate=document.getElementById('stl-date').value;
  const reference=document.getElementById('stl-ref').value.trim();
  
  if(writeoff<0||writeoff>balance)return alert('Write-off must be between 0 and the balance.');
  if(!bankId)return alert('Please select a bank account.');
  if(!entryDate)return alert('Please select a date.');
  
  const afterWriteoff=balance-writeoff;
  const whtEnabled=document.getElementById('stl-wht-enabled').checked;
  const wht=whtEnabled?Math.floor(afterWriteoff*0.03):0;
  const net=afterWriteoff-wht;
  
  if(net<=0)return alert('Net payment must be greater than 0.');
  
  const msg=`Post settlement for ${settleData.companyName}?\n\n` +
    `• Write-off (Loss): ${fmt(writeoff)} RWF\n` +
    (whtEnabled?`• Withholding Tax (3%): ${fmt(wht)} RWF\n`:'• Withholding Tax: Not applied\n') +
    `• Bank Payment: ${fmt(net)} RWF\n` +
    `• Total clearing: ${fmt(balance)} RWF\n` +
    (isFinal?'• Final payment - month will be marked PAID':`• PARTIAL payment - ${fmt(remaining-balance)} RWF stays open`);
  
  if(!confirm(msg))return;
  
  const btn=document.getElementById('stl-submit-btn');
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
  
  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/settlement',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        insurance_id: settleData.companyId,
        insurance_account_id: settleData.accountId,
        bank_account_id: parseInt(bankId),
        writeoff_amount: writeoff,
        wht_amount: wht,
        net_amount: net,
        total_balance: remaining,
        entry_date: entryDate,
        reference: reference,
        month: settleData.month || parseInt(document.getElementById('stl-month').value),
        year: settleData.year || parseInt(document.getElementById('stl-year').value)
      })
    });
    const j=await r.json();
    if(j.success){
      alert('✓ '+j.message);
      closeSettle();
      recvLoad();
    }else{
      alert('Error: '+j.message);
    }
  }catch(e){
    alert('Failed: '+e.message);
  }finally{
    btn.disabled=false;btn.innerHTML='<i class="fas fa-check-circle"></i> Post Settlement Journals';
  }
}

// ============ INVOICE GENERATION ============
let invGenData = {};

function openInvoiceGen(companyId, companyName){
  invGenData = {companyId, companyName};
  document.getElementById('inv-company-name').textContent = companyName;
  // Set current month/year
  const now = new Date();
  document.getElementById('inv-month').value = now.getMonth() + 1;
  // Populate year dropdown
  const yearSel = document.getElementById('inv-year');
  yearSel.innerHTML = '';
  for(let y = now.getFullYear(); y >= now.getFullYear()-3; y--){
    yearSel.innerHTML += `<option value="${y}" ${y===now.getFullYear()?'selected':''}>${y}</option>`;
  }
  document.getElementById('inv-gen-modal').style.display = 'flex';
}

function closeInvoiceGen(){
  document.getElementById('inv-gen-modal').style.display = 'none';
}

async function generateInvoice(){
  const month = parseInt(document.getElementById('inv-month').value);
  const year = parseInt(document.getElementById('inv-year').value);
  const bankName    = document.getElementById('inv-bank-name').value.trim();
  const bankAccount = document.getElementById('inv-bank-account').value.trim();
  const bankAcName  = document.getElementById('inv-bank-acname').value.trim();
  const sigName     = document.getElementById('inv-sig-name').value.trim();
  const sigTitle    = document.getElementById('inv-sig-title').value.trim();

  if(!bankName || !bankAccount || !bankAcName){
    alert('Please fill in all Bank Details fields.'); return;
  }
  if(!sigName || !sigTitle){
    alert('Please fill in all Signatory fields.'); return;
  }

  const btn = document.getElementById('inv-gen-btn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
  
  try{
    // Fetch invoice data and hospital settings in parallel
    const [invRes, hsRes] = await Promise.all([
      fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/settlement&action=monthly_invoice&insurance_id='+invGenData.companyId+'&month='+month+'&year='+year),
      fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=settings/hospital')
    ]);
    const j = await invRes.json();
    const hsJson = await hsRes.json();
    if(!j.success){ alert(j.message); return; }
    
    const data = j.data;
    if(!data.total || data.total <= 0){
      alert('No charges found for '+data.month_name+' '+year+' for this insurance.');
      return;
    }
    
    const hs = hsJson.success ? hsJson.data : {};
    const bankInfo = { bankName, bankAccount, bankAcName, sigName, sigTitle };
    renderInvoicePreview(data, hs, bankInfo);
    closeInvoiceGen();
    document.getElementById('inv-preview-modal').style.display = 'flex';
  }catch(e){
    alert('Failed: '+e.message);
  }finally{
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-invoice"></i> Generate Invoice';
  }
}

function fmtDate(d){
  if(!d) return '';
  const dateOnly = String(d).split(' ')[0].split('T')[0];
  const p = dateOnly.split('-');
  return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;
}
function fmtDOB(d){
  if(!d) return '';
  const dateOnly = String(d).split(' ')[0].split('T')[0];
  const p = dateOnly.split('-');
  return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;
}
function fmtSex(g){
  if(!g) return '';
  const gl=String(g).toLowerCase();
  if(gl==='male'||gl==='m') return 'M';
  if(gl==='female'||gl==='f') return 'F';
  return g.charAt(0).toUpperCase();
}
function capitalize(s){
  return String(s||'').charAt(0).toUpperCase()+String(s||'').slice(1).toLowerCase();
}
function lastDayOfMonth(m,y){
  return new Date(y,m,0).getDate();
}

function renderInvoicePreview(data, hs, bankInfo){
  const months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
  const monthName = months[data.month] || '';
  const totalWords = numberToWords(data.total);
  
  // Bank & signatory from user input
  bankInfo = bankInfo || {};
  const invBankName    = bankInfo.bankName    || 'Bank of Kigali';
  const invBankAccount = bankInfo.bankAccount || '';
  const invBankAcName  = bankInfo.bankAcName  || '';
  const invSigName     = bankInfo.sigName     || 'MUKULIRA NADINE';
  const invSigTitle    = bankInfo.sigTitle    || 'CHIEF EXECUTIVE OFFICER';

  // Store data globally for download functions
  invGenData.invoiceData = data;
  invGenData.hospitalSettings = hs || {};
  invGenData.bankInfo = { invBankName, invBankAccount, invBankAcName, invSigName, invSigTitle };
  
  // Hospital settings defaults
  const hospName = hs.hospital_name || 'IRAMIRO CLINIC';
  const hospEmail = hs.email || 'info@radiant.rw';
  const hospPhone = hs.phone || '';
  const hospAddress = hs.address || 'KIGALI – RWANDA';
  const hospPoBox = hs.po_box || 'P.O Box: Kigali-Rwanda: 1861';
  const hospLogo = hs.doc_logo || hs.logo || '';
  
  // Header settings
  const hLineColor = hs.header_line_color || '#059669';
  const hLineSize = hs.header_line_size || '2';
  const hTextColor = hs.header_text_color || '#1f2937';
  const hFontSize = hs.header_font_size || '12';
  const hLeftText = hs.header_left || hospName;
  const hRightText = (hs.header_right || 'Date: {date} | Ref: {ref}').replace('{date}', new Date().toISOString().split('T')[0]).replace('{ref}', 'INV-'+data.month+'-'+data.year);
  
  // Footer settings
  const fLineColor = hs.footer_line_color || '#059669';
  const fLineSize = hs.footer_line_size || '2';
  const fTextColor = hs.footer_text_color || '#1f2937';
  const fFontSize = hs.footer_font_size || '10';
  const fLeftText = hs.footer_left || hospAddress + ' | ' + hospPoBox;
  const fRightText = hs.footer_right || (hospPhone ? 'Tel: '+hospPhone+' | ' : '') + 'Email: '+hospEmail;
  
  // Watermark
  const wmEnabled = hs.watermark_enabled === '1';
  const wmType = hs.watermark_type || 'text';
  const wmText = hs.watermark_text || hospName;
  const wmColor = hs.watermark_color || '#d1d5db';
  const wmSize = hs.watermark_size || '60';
  const wmOpacity = (parseInt(hs.watermark_opacity) || 15) / 100;
  const wmRotation = hs.watermark_rotation || '-30';
  const wmImage = hs.watermark_image || '';
  
  let watermarkStyle = `position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;`;
  let watermarkContent = '';
  if(wmEnabled){
    if(wmType === 'image' && wmImage){
      watermarkContent = `<img src="${wmImage}" style="width:250px;height:auto;">`;
    } else {
      watermarkStyle += `font-size:${wmSize}px;color:${wmColor};font-weight:700;white-space:nowrap;`;
      watermarkContent = iEsc(wmText);
    }
  }
  
  // Header HTML block
  const headerBlock = `<img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
  <div style="display:flex;justify-content:flex-end;margin:4px 0 20px;">
    <span style="font-size:${hFontSize}px;color:${hTextColor};">${iEsc(hRightText)}</span>
  </div>`;
  
  // Footer HTML block
  const footerBlock = `<div style="display:flex;justify-content:space-between;align-items:center;padding-top:8px;border-top:${fLineSize}px solid ${fLineColor};margin-top:30px;">
    <span style="font-size:${fFontSize}px;color:${fTextColor};">${iEsc(fLeftText)}</span>
    <span style="font-size:${fFontSize}px;color:${fTextColor};">${iEsc(fRightText)}</span>
  </div>`;

  // FILE 1: Invoice Letter
  const invoiceHtml = `
    <div id="invoice-printable" style="font-family:'Times New Roman',serif;color:#000;line-height:1.6;position:relative;overflow:hidden;">
      ${wmEnabled ? '<div style="'+watermarkStyle+'">'+watermarkContent+'</div>' : ''}
      <div style="position:relative;z-index:1;">
        ${headerBlock}
        
        <div style="text-align:center;margin-bottom:25px;">
          <h1 style="font-size:20px;font-weight:700;margin:0;text-transform:uppercase;">${iEsc(data.insurance_name)} INVOICE</h1>
          <p style="font-size:13px;margin:4px 0;">Month: <strong>${monthName}</strong>, Year: <strong>${data.year}</strong></p>
        </div>
        
        <div style="margin-bottom:20px;font-size:13px;">
          <p style="margin:2px 0;font-weight:600;">To:</p>
          <p style="margin:2px 0;font-weight:600;">${iEsc(data.insurance_name)}</p>
          ${data.insurance_address ? '<p style="margin:2px 0;">'+iEsc(data.insurance_address)+'</p>' : ''}
          ${data.insurance_phone ? '<p style="margin:2px 0;">Tel: '+iEsc(data.insurance_phone)+'</p>' : ''}
          ${data.insurance_email ? '<p style="margin:2px 0;">Email: '+iEsc(data.insurance_email)+'</p>' : ''}
        </div>
        
        <div style="margin-bottom:25px;font-size:14px;text-align:justify;">
          <p><strong>${iEsc(data.insurance_name)}</strong> has to pay to <strong>${iEsc(hospName)}</strong> the sum of <strong>${totalWords} Rwandan francs (${fmt(data.total)} RWF)</strong> for the invoice of Month <strong>${monthName}</strong> Year <strong>${data.year}</strong>. This amount reflects percentages that <strong>${iEsc(data.insurance_name)}</strong> pays to medical service given to his members. This amount will be transferred through the following bank details:</p>
        </div>

        <div style="margin-bottom:25px;font-size:13px;background:#f9f9f9;padding:12px 16px;border:1px solid #ddd;border-radius:4px;max-width:480px;">
          <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <tr><td style="padding:3px 0;color:#555;">Total Medical Services (100%):</td><td style="text-align:right;font-weight:600;">${fmt(data.gross_total||(data.totals||{}).total)} RWF</td></tr>
            <tr><td style="padding:3px 0;color:#555;">Beneficiaries (Patient Copayment):</td><td style="text-align:right;font-weight:600;color:#b91c1c;">-${fmt(data.beneficiary_total||(data.totals||{}).beneficiary)} RWF</td></tr>
            <tr style="border-top:1px solid #ccc;font-weight:700;"><td style="padding:6px 0;color:#000;">Net Amount Payable by ${iEsc(data.insurance_name)}:</td><td style="text-align:right;color:#059669;font-size:14px;">${fmt(data.total)} RWF</td></tr>
          </table>
        </div>
        
        <div style="margin-bottom:30px;font-size:14px;background:#f9f9f9;padding:16px;border:1px solid #ddd;border-radius:4px;">
          <table style="font-size:14px;">
            <tr><td style="padding:4px 12px 4px 0;font-weight:600;">A/C:</td><td>${iEsc(invBankAccount)}</td></tr>
            <tr><td style="padding:4px 12px 4px 0;font-weight:600;">Account Name:</td><td>${iEsc(invBankAcName)}</td></tr>
            <tr><td style="padding:4px 12px 4px 0;font-weight:600;">Bank name:</td><td>${iEsc(invBankName)}</td></tr>
          </table>
        </div>
        
        <div style="margin-top:80px;">
          <p style="font-size:14px;font-weight:600;margin:0;">${iEsc(invSigName)}</p>
          <p style="font-size:13px;margin:4px 0 0 0;text-transform:uppercase;letter-spacing:1px;">${iEsc(invSigTitle)}</p>
        </div>
        
        ${footerBlock}
      </div>
    </div>
  `;
  
  // FILE 2: Attachment - Items List
  const attachmentHtml = `
    <div id="attachment-printable" style="font-family:'Times New Roman',serif;color:#000;line-height:1.5;position:relative;overflow:hidden;">
      ${wmEnabled ? '<div style="'+watermarkStyle+'">'+watermarkContent+'</div>' : ''}
      <div style="position:relative;z-index:1;">
        ${headerBlock}

        <div style="text-align:center;margin-bottom:6px;">
          <p style="font-size:13px;font-weight:700;margin:0;text-transform:uppercase;">${iEsc(hospName)}</p>
          <p style="font-size:12px;margin:2px 0;">${iEsc(hospAddress)}</p>
        </div>

        <div style="text-align:center;margin-bottom:18px;">
          <p style="font-size:13px;font-weight:700;margin:4px 0;">Summary of Vouchers For ${iEsc(data.insurance_name)} - 01/${String(data.month).padStart(2,'0')}/${data.year} TO ${lastDayOfMonth(data.month,data.year)}/${String(data.month).padStart(2,'0')}/${data.year}</p>
        </div>

        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:9px;">
          <thead>
            <tr style="background:#d0d0d0;font-weight:700;">
              <th style="padding:5px 4px;border:1px solid #666;text-align:center;">NO</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:center;">DATE</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:center;">BENEFICIARY AFFILIATION NUMBER</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:center;">BENEFICIARY'S AGE</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:center;">SEX<br>(F=Female, M=Male)</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:center;">BENEFICIARY'S NAMES</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:center;">RELATIONSHIP WITH AFFILIATE</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:right;">COST FOR CONSULTATION</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:right;">Hospitalisation Cost</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:right;">COST FOR LABORATORY TESTS</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:right;">COST FOR PROCEDURES AND MATERIALS</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:right;">COST FOR MEDICINES</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:right;">TOTAL AMOUNT (100%)</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:right;">AMOUNT FOR BENEFICIARIES</th>
              <th style="padding:5px 4px;border:1px solid #666;text-align:right;">AMOUNT FOR ${iEsc(data.insurance_code||data.insurance_name)}</th>
            </tr>
          </thead>
          <tbody>
            ${(data.items||[]).map((item,i)=>`<tr style="${i%2===0?'':'background:#f7f7f7;'}">
              <td style="padding:4px;border:1px solid #ccc;text-align:center;">${i+1}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:center;">${fmtDate(item.date)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:center;">${iEsc(item.member_number||'')}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:center;">${fmtDOB(item.date_of_birth)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:center;">${fmtSex(item.gender)}</td>
              <td style="padding:4px;border:1px solid #ccc;">${iEsc(item.patient_name)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:center;">${iEsc(capitalize(item.relationship||'Self'))}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:right;">${fmt(item.consult)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:right;">${fmt(item.hosp)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:right;">${fmt(item.lab)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:right;">${fmt(item.proc)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:right;">${fmt(item.med)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:right;font-weight:600;">${fmt(item.total)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:right;">${fmt(item.beneficiary)}</td>
              <td style="padding:4px;border:1px solid #ccc;text-align:right;">${fmt(item.insurance)}</td>
            </tr>`).join('')}
          </tbody>
        </table>
        </div>

        <div style="margin-top:18px;">
          <p style="font-size:12px;font-weight:700;margin:0 0 6px 0;">TOTAL</p>
          <table style="border-collapse:collapse;font-size:9px;width:100%;">
            <thead>
              <tr style="background:#d0d0d0;font-weight:700;">
                <th style="padding:5px 4px;border:1px solid #666;text-align:right;">Consultation</th>
                <th style="padding:5px 4px;border:1px solid #666;text-align:right;">Hospitalisation</th>
                <th style="padding:5px 4px;border:1px solid #666;text-align:right;">Laboratory Tests</th>
                <th style="padding:5px 4px;border:1px solid #666;text-align:right;">Procedures &amp; Materials</th>
                <th style="padding:5px 4px;border:1px solid #666;text-align:right;">Medicines</th>
                <th style="padding:5px 4px;border:1px solid #666;text-align:right;">Total Amount</th>
                <th style="padding:5px 4px;border:1px solid #666;text-align:right;">Beneficiaries</th>
                <th style="padding:5px 4px;border:1px solid #666;text-align:right;">${iEsc(data.insurance_code||data.insurance_name)}</th>
              </tr>
            </thead>
            <tbody>
              <tr style="font-weight:700;background:#eaeaea;">
                <td style="padding:5px 4px;border:1px solid #888;text-align:right;">${fmt((data.totals||{}).consult||0)}</td>
                <td style="padding:5px 4px;border:1px solid #888;text-align:right;">${fmt((data.totals||{}).hosp||0)}</td>
                <td style="padding:5px 4px;border:1px solid #888;text-align:right;">${fmt((data.totals||{}).lab||0)}</td>
                <td style="padding:5px 4px;border:1px solid #888;text-align:right;">${fmt((data.totals||{}).proc||0)}</td>
                <td style="padding:5px 4px;border:1px solid #888;text-align:right;">${fmt((data.totals||{}).med||0)}</td>
                <td style="padding:5px 4px;border:1px solid #888;text-align:right;">${fmt((data.totals||{}).total||0)}</td>
                <td style="padding:5px 4px;border:1px solid #888;text-align:right;">${fmt((data.totals||{}).beneficiary||0)}</td>
                <td style="padding:5px 4px;border:1px solid #888;text-align:right;">${fmt((data.totals||{}).insurance||0)}</td>
              </tr>
            </tbody>
          </table>
        </div>
        
        ${footerBlock}
      </div>
    </div>
  `;
  
  document.getElementById('inv-preview-content').innerHTML = invoiceHtml;
  document.getElementById('att-preview-content').innerHTML = attachmentHtml;
}

function showPreviewTab(tab){
  const invTab = document.getElementById('prev-tab-inv');
  const attTab = document.getElementById('prev-tab-att');
  const invContent = document.getElementById('inv-preview-content');
  const attContent = document.getElementById('att-preview-content');
  if(tab==='invoice'){
    invTab.style.background='#1e40af';invTab.style.color='white';
    attTab.style.background='var(--gray-100)';attTab.style.color='var(--gray-600)';
    invContent.style.display='block';attContent.style.display='none';
  }else{
    attTab.style.background='#7c3aed';attTab.style.color='white';
    invTab.style.background='var(--gray-100)';invTab.style.color='var(--gray-600)';
    invContent.style.display='none';attContent.style.display='block';
  }
}

function numberToWords(num){
  if(num===0) return 'Zero';
  const ones=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
  const tens=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
  const scales=['','Thousand','Million','Billion'];
  
  num=Math.floor(num);
  if(num<0) return 'Negative '+numberToWords(-num);
  
  function chunk(n){
    let str='';
    if(n>=100){str+=ones[Math.floor(n/100)]+' Hundred ';n%=100;}
    if(n>=20){str+=tens[Math.floor(n/10)]+' ';n%=10;}
    if(n>0) str+=ones[n]+' ';
    return str.trim();
  }
  
  let result='';
  let scaleIdx=0;
  while(num>0){
    const part=num%1000;
    if(part>0) result=chunk(part)+' '+scales[scaleIdx]+' '+result;
    num=Math.floor(num/1000);
    scaleIdx++;
  }
  return result.trim();
}

function closeInvoicePreview(){
  document.getElementById('inv-preview-modal').style.display = 'none';
}

// FILE 1: Invoice Letter PDF
function downloadInvoicePDF(){
  const element = document.getElementById('invoice-printable');
  const data = invGenData.invoiceData||{};
  const companyName = (data.insurance_name||'Insurance').replace(/\s+/g,'_');
  const opt = {
    margin: 15,
    filename: companyName+'_Invoice_'+data.month_name+'_'+data.year+'.pdf',
    image: {type:'jpeg',quality:0.98},
    html2canvas: {scale:2},
    jsPDF: {unit:'mm',format:'a4',orientation:'portrait'}
  };
  html2pdf().set(opt).from(element).save();
}

// FILE 2: Attachment PDF (items detail list)
function downloadAttachmentPDF(){
  const element = document.getElementById('attachment-printable');
  const data = invGenData.invoiceData||{};
  const companyName = (data.insurance_name||'Insurance').replace(/\s+/g,'_');
  const opt = {
    margin: 8,
    filename: companyName+'_Attachment_'+data.month_name+'_'+data.year+'.pdf',
    image: {type:'jpeg',quality:0.98},
    html2canvas: {scale:2},
    jsPDF: {unit:'mm',format:'a4',orientation:'landscape'}
  };
  // Make sure attachment content is visible for rendering
  const attDiv = document.getElementById('att-preview-content');
  const wasHidden = attDiv.style.display === 'none';
  if(wasHidden) attDiv.style.display = 'block';
  html2pdf().set(opt).from(element).save().then(()=>{
    if(wasHidden) attDiv.style.display = 'none';
  });
}

// FILE 3: Excel Download (items spreadsheet)
function downloadExcel(){
  const data = invGenData.invoiceData||{};
  const items = data.items||[];
  const totals = data.totals||{};
  const companyName = (data.insurance_name||'Insurance').replace(/\s+/g,'_');
  const months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
  const monthName = months[data.month]||'';
  const insCode = data.insurance_code || data.insurance_name;

  const lastDay = lastDayOfMonth(data.month, data.year);
  const mm = String(data.month).padStart(2,'0');

  // Build worksheet data
  const wsData = [
    [invGenData.hospitalSettings.hospital_name||'IRAMIRO CLINIC'],
    [invGenData.hospitalSettings.address||''],
    [],
    ['Summary of Vouchers For '+data.insurance_name+' - 01/'+mm+'/'+data.year+' TO '+lastDay+'/'+mm+'/'+data.year],
    [],
    ['NO','DATE','BENEFICIARY AFFILIATION NUMBER',"BENEFICIARY'S AGE",'SEX (F=Female, M=Male)',"BENEFICIARY'S NAMES",'RELATIONSHIP WITH AFFILIATE',
     'COST FOR CONSULTATION','Hospitalisation Cost','COST FOR LABORATORY TESTS','COST FOR PROCEDURES AND MATERIALS','COST FOR MEDICINES',
     'TOTAL AMOUNT (100%)','AMOUNT FOR BENEFICIARIES','AMOUNT FOR '+insCode]
  ];

  items.forEach((item,i)=>{
    wsData.push([
      i+1,
      fmtDate(item.date),
      item.member_number||'',
      fmtDOB(item.date_of_birth),
      fmtSex(item.gender),
      item.patient_name,
      capitalize(item.relationship||'Self'),
      item.consult||0,
      item.hosp||0,
      item.lab||0,
      item.proc||0,
      item.med||0,
      item.total||0,
      item.beneficiary||0,
      item.insurance||0
    ]);
  });

  const totConsult = totals.consult !== undefined ? totals.consult : items.reduce((s, it) => s + (parseFloat(it.consult)||0), 0);
  const totHosp = totals.hosp !== undefined ? totals.hosp : items.reduce((s, it) => s + (parseFloat(it.hosp)||0), 0);
  const totLab = totals.lab !== undefined ? totals.lab : items.reduce((s, it) => s + (parseFloat(it.lab)||0), 0);
  const totProc = totals.proc !== undefined ? totals.proc : items.reduce((s, it) => s + (parseFloat(it.proc)||0), 0);
  const totMed = totals.med !== undefined ? totals.med : items.reduce((s, it) => s + (parseFloat(it.med)||0), 0);
  const totTotal = totals.total !== undefined ? totals.total : items.reduce((s, it) => s + (parseFloat(it.total)||0), 0);
  const totBeneficiary = totals.beneficiary !== undefined ? totals.beneficiary : items.reduce((s, it) => s + (parseFloat(it.beneficiary)||0), 0);
  const totInsurance = totals.insurance !== undefined ? totals.insurance : items.reduce((s, it) => s + (parseFloat(it.insurance)||0), 0);

  wsData.push([]);
  wsData.push(['TOTAL','','','','','','',
    totConsult, totHosp, totLab, totProc, totMed,
    totTotal, totBeneficiary, totInsurance
  ]);

  // Create workbook
  const wb = XLSX.utils.book_new();
  const ws = XLSX.utils.aoa_to_sheet(wsData);

  // Set column widths
  ws['!cols'] = [
    {wch:5},{wch:12},{wch:28},{wch:14},{wch:8},{wch:28},{wch:20},
    {wch:14},{wch:14},{wch:18},{wch:22},{wch:14},{wch:16},{wch:18},{wch:14}
  ];

  XLSX.utils.book_append_sheet(wb, ws, 'Voucher Summary');
  XLSX.writeFile(wb, companyName+'_Attachment_'+monthName+'_'+data.year+'.xlsx');
}

function printInvoice(){
  const invoiceContent = document.getElementById('invoice-printable').innerHTML;
  const attachContent = document.getElementById('attachment-printable').innerHTML;
  const win = window.open('','_blank','width=800,height=600');
  win.document.write(`<html><head><title>Insurance Invoice</title><style>
    body{font-family:'Times New Roman',serif;padding:40px;color:#000;}
    table{border-collapse:collapse;}
    img{max-width:100%;}
    .page-break{page-break-before:always;margin-top:40px;}
    @media print{body{padding:20px;}.page-break{page-break-before:always;}}
  </style></head><body>
    <div style="position:relative;overflow:hidden;">${invoiceContent}</div>
    <div class="page-break" style="position:relative;overflow:hidden;">${attachContent}</div>
  </body></html>`);
  win.document.close();
  win.focus();
  setTimeout(()=>{win.print();},500);
}

recvLoad();
</script>
<?php elseif ($subModule == 'insurance-prices'): ?>
<!-- ===== INSURANCE PRICES (procedures + items) ===== -->
<style>.ipp-seg{display:inline-flex;gap:4px;background:var(--gray-50);padding:3px;border-radius:8px;}.ipp-seg button{padding:7px 14px;border:none;background:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}.ipp-seg button.active{background:var(--primary-color);color:#fff;}.ipp-ctl{padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;}.ipp-pg button{padding:6px 12px;border:1px solid var(--gray-200);background:#fff;border-radius:6px;font-size:12px;cursor:pointer;}.ipp-pg button:disabled{opacity:.4;cursor:default;}</style>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-shield-alt" style="color:var(--primary-color);"></i> Insurance Prices</h3>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <div class="ipp-seg" id="ipp-type"><button data-v="procedures" class="active">Procedures</button><button data-v="items">Items</button></div>
      <div class="ipp-seg" id="ipp-group"><button data-v="" class="active">All</button><button data-v="private">Private</button><button data-v="public">Public</button></div>
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;">
    <input id="ipp-search" class="ipp-ctl" type="text" placeholder="Search name or code..." style="width:240px;">
    <select id="ipp-filter" class="ipp-ctl" style="min-width:170px;"></select>
    <select id="ipp-per" class="ipp-ctl"><option>25</option><option selected>50</option><option>100</option></select>
  </div>
  <div style="font-size:11px;color:var(--gray-500);margin-bottom:8px;" id="ipp-info">Loading...</div>
  <div style="overflow-x:auto;" id="ipp-content"></div>
  <div class="ipp-pg" style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;gap:8px;flex-wrap:wrap;">
    <span id="ipp-pageinfo" style="font-size:12px;color:var(--gray-500);"></span>
    <div style="display:flex;gap:6px;"><button id="ipp-first">«</button><button id="ipp-prev">‹ Prev</button><button id="ipp-next">Next ›</button><button id="ipp-last">»</button></div>
  </div>
</div>

<div id="ipp-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:400px;max-width:90vw;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Set Price</h3>
      <button onclick="document.getElementById('ipp-modal').style.display='none'" style="border:none;background:none;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <div style="margin-bottom:12px;padding:10px;background:var(--gray-50);border-radius:6px;font-size:13px;">
      <div style="font-weight:600;color:var(--gray-800);" id="ipp-modal-procedure">—</div>
      <div style="font-size:11px;color:var(--gray-500);margin-top:2px;" id="ipp-modal-insurance">—</div>
    </div>
    <div style="margin-bottom:16px;">
      <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Insurance Price (RWF)</label>
      <input type="number" id="ipp-modal-price" step="any" min="0" placeholder="0.00" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
    </div>
    <div style="display:flex;gap:10px;">
      <button onclick="ippSavePrice()" style="flex:1;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save</button>
      <button onclick="document.getElementById('ipp-modal').style.display='none'" style="padding:10px 20px;background:var(--gray-100);color:var(--gray-700);border:none;border-radius:8px;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<script>
const ippAPI=(typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/prices';
const ippSt={type:'procedures',group:localStorage.getItem('ipp_group')||'',page:1,filter:'',filtersFor:''};
let ippData=null, ippTimer, ippSel=null;
const ippEsc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const ippNum=v=>(v===undefined||v===null)?null:Number(v);

function ippSeg(id,val){document.querySelectorAll('#'+id+' button').forEach(b=>b.classList.toggle('active',b.dataset.v===val));}

async function ippLoad(){
  const q=new URLSearchParams({type:ippSt.type,group:ippSt.group,page:ippSt.page,per_page:document.getElementById('ipp-per').value,search:document.getElementById('ipp-search').value.trim()});
  if(ippSt.filter) q.set(ippSt.type==='items'?'category':'speciality',ippSt.filter);
  document.getElementById('ipp-info').textContent='Loading...';
  try{
    const r=await fetch(ippAPI+'&'+q); const j=await r.json();
    if(!j.success) throw new Error(j.message||'Load failed');
    ippData=j.data; ippSt.page=ippData.page;
    ippFillFilter(); ippRender();
  }catch(e){document.getElementById('ipp-content').innerHTML='<div style="padding:30px;text-align:center;color:#dc2626;">'+ippEsc(e.message)+'</div>';document.getElementById('ipp-info').textContent='';}
}

function ippFillFilter(){
  const sel=document.getElementById('ipp-filter');
  if(ippSt.filtersFor===ippSt.type) return;
  ippSt.filtersFor=ippSt.type;
  if(ippSt.type==='items'){
    sel.innerHTML='<option value="">All categories</option><option value="pharmacy">Pharmacy (Medications)</option>'+ippData.filters.map(c=>`<option value="${c.id}">${ippEsc(c.name)}</option>`).join('');
  }else{
    sel.innerHTML='<option value="">All Specialities</option>'+ippData.filters.map(s=>`<option value="${ippEsc(s)}">${ippEsc(s)}</option>`).join('');
  }
  sel.value=ippSt.filter;
}

function ippRender(){
  const d=ippData, items=ippSt.type==='items', cols=d.companies;
  const per=Number(d.per_page), from=d.total?(d.page-1)*per+1:0, to=Math.min(d.total,d.page*per);
  document.getElementById('ipp-info').textContent=`${d.total.toLocaleString()} ${items?'items':'procedures'} · ${cols.length} insurer${cols.length===1?'':'s'} shown${ippSt.group?' ('+ippSt.group+')':''}`;
  document.getElementById('ipp-pageinfo').textContent=d.total?`Showing ${from}–${to} of ${d.total.toLocaleString()} · Page ${d.page} / ${d.pages}`:'';
  document.getElementById('ipp-first').disabled=document.getElementById('ipp-prev').disabled=d.page<=1;
  document.getElementById('ipp-next').disabled=document.getElementById('ipp-last').disabled=d.page>=d.pages;
  if(!d.rows.length){document.getElementById('ipp-content').innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">Nothing found.</div>';return;}
  let h=`<table style="width:100%;border-collapse:collapse;font-size:12px;min-width:${500+cols.length*100}px;"><thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
    <th style="padding:8px 10px;text-align:left;position:sticky;left:0;background:var(--gray-50);z-index:1;">${items?'Item':'Procedure'}</th>
    <th style="padding:8px 10px;text-align:left;">${items?'Category':'Speciality'}</th>
    <th style="padding:8px 10px;text-align:right;">${items?'Selling Price':'Std Price'}</th>`;
  cols.forEach(c=>{h+=`<th title="${ippEsc(c.name)} (${c.tariff_group})" style="padding:8px 10px;text-align:right;font-size:11px;min-width:90px;">${ippEsc(c.name.length>12?c.name.slice(0,12)+'…':c.name)}<div style="font-weight:400;font-size:9px;color:${c.tariff_group==='public'?'#2563eb':'#7c3aed'};">${c.tariff_group}</div></th>`;});
  h+='</tr></thead><tbody>';
  d.rows.forEach((r,ri)=>{
    const std=Number(r.std_price||0);
    h+=`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 10px;font-weight:500;position:sticky;left:0;background:white;z-index:1;">${ippEsc(r.name)||'—'}<div style="font-size:10px;color:var(--gray-400);font-weight:400;">${ippEsc(r.code)}</div></td>
      <td style="padding:6px 10px;font-size:11px;color:var(--gray-500);">${ippEsc(r.group_name)||'—'}</td>
      <td style="padding:6px 10px;text-align:right;color:var(--gray-600);">${std?std.toLocaleString():'—'}</td>`;
    cols.forEach((c,ci)=>{
      const p=ippNum(d.prices[r.id]&&d.prices[r.id][c.id]);
      h+=`<td data-r="${ri}" data-c="${ci}" class="ipp-cell" style="padding:6px 10px;text-align:right;cursor:pointer;"><span style="font-weight:${p!==null?600:400};color:${p!==null?'#059669':'var(--gray-300)'};">${p!==null?p.toLocaleString():'—'}</span></td>`;
    });
    h+='</tr>';
  });
  document.getElementById('ipp-content').innerHTML=h+'</tbody></table>';
}

function ippReload(resetPage){if(resetPage)ippSt.page=1;ippLoad();}
document.getElementById('ipp-type').onclick=e=>{const v=e.target.dataset.v;if(!v||v===ippSt.type)return;ippSt.type=v;ippSt.filter='';document.getElementById('ipp-search').value='';ippSeg('ipp-type',v);ippReload(true);};
document.getElementById('ipp-group').onclick=e=>{const v=e.target.dataset.v;if(v===undefined||v===ippSt.group)return;ippSt.group=v;localStorage.setItem('ipp_group',v);ippSeg('ipp-group',v);ippReload(true);};
document.getElementById('ipp-filter').onchange=e=>{ippSt.filter=e.target.value;ippReload(true);};
document.getElementById('ipp-per').onchange=()=>ippReload(true);
document.getElementById('ipp-search').oninput=()=>{clearTimeout(ippTimer);ippTimer=setTimeout(()=>ippReload(true),300);};
document.getElementById('ipp-first').onclick=()=>{ippSt.page=1;ippLoad();};
document.getElementById('ipp-prev').onclick=()=>{ippSt.page--;ippLoad();};
document.getElementById('ipp-next').onclick=()=>{ippSt.page++;ippLoad();};
document.getElementById('ipp-last').onclick=()=>{ippSt.page=ippData.pages;ippLoad();};
document.getElementById('ipp-content').onclick=e=>{
  const td=e.target.closest('.ipp-cell'); if(!td) return;
  const r=ippData.rows[td.dataset.r], c=ippData.companies[td.dataset.c];
  const p=ippNum(ippData.prices[r.id]&&ippData.prices[r.id][c.id]);
  ippSel={row:r.id,ins:c.id};
  document.getElementById('ipp-modal-procedure').textContent=r.name;
  document.getElementById('ipp-modal-insurance').textContent='Insurance: '+c.name+' ('+c.tariff_group+')';
  document.getElementById('ipp-modal-price').value=p===null?'':p;
  document.getElementById('ipp-modal').style.display='flex';
};

async function ippSavePrice(){
  const price=parseFloat(document.getElementById('ipp-modal-price').value);
  if(!(price>=0)){alert('Enter a valid price');return;}
  try{
    const r=await fetch(ippAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:ippSt.type,row_id:ippSel.row,insurance_id:ippSel.ins,price})});
    const j=await r.json();
    if(!j.success){alert('Failed: '+j.message);return;}
    document.getElementById('ipp-modal').style.display='none';
    (ippData.prices[ippSel.row]=ippData.prices[ippSel.row]||{})[ippSel.ins]=price;
    ippRender();
  }catch(e){alert('Error: '+e.message);}
}

ippSeg('ipp-group',ippSt.group);
ippLoad();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
