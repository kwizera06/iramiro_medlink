<?php
require_once __DIR__ . '/includes/asset_schema.php';

$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'register';

// Michel-only tabs are hidden: a direct request goes back to the Asset Register.
// Checked before header.php so the redirect still happens after the page has started output.
if (in_array($subModule, AM_HIDDEN_SUBS, true)) {
    require_once __DIR__ . '/includes/session_check.php';   // not logged in -> login page
    header('Location: assets.php?module=assets&sub=register');
    exit;
}

// "New Asset" is its own Lendsmart menu item but renders the Register page and opens the Add Asset modal.
$assetAutoNew = false;
if ($subModule === 'new_asset') { $subModule = 'register'; $assetAutoNew = true; }

require_once __DIR__ . '/includes/header.php';
?>
<style>.as-tab{padding:10px 18px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}.as-tab.active{background:var(--primary-color);color:white;}</style>
<script>const _asBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
async function asFillCash(selectId, blankLabel){
  const sel=document.getElementById(selectId); if(!sel) return;
  try{
    const r=await fetch(_asBase + '/api.php?endpoint=assets/asset-management&action=cash_accounts'); const j=await r.json();
    sel.innerHTML=(blankLabel?'<option value="">'+blankLabel+'</option>':'')+(j.data||[]).map(a=>'<option value="'+a.id+'">'+a.account_name+'</option>').join('');
  }catch(e){}
}</script>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div><h2 style="font-size:24px;font-weight:600;">Assets Management</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Track, depreciate, and manage hospital assets</p></div>
</div>
<?php
// Lendsmart-style sidebar: no tab strip, except Capitalization + Revaluation share one menu item.
$tabLabels = ['register'=>['Register','fa-clipboard-list'],'summary'=>['Summary','fa-table'],'categories'=>['Categories','fa-tags'],'opening'=>['Opening Balance','fa-book-open'],'qr'=>['QR Labels & Verification','fa-qrcode'],
  'capitalization'=>['Capitalization','fa-hammer'],'depreciation'=>['Depreciation','fa-chart-line'],'revaluation'=>['Revaluation','fa-arrow-up'],
  'assignments'=>['Assignments & Handover','fa-user-tag'],'clearance'=>['Staff Exit Clearance','fa-user-check'],
  'transfers'=>['Transfers','fa-exchange-alt'],'movements'=>['Movement History','fa-route'],
  'm_schedule'=>['Schedule','fa-calendar-alt'],'m_faults'=>['Fault Requests','fa-exclamation-triangle'],'m_orders'=>['Work Orders','fa-clipboard-check'],'m_verify'=>['Pending Verification','fa-user-shield'],'m_history'=>['History','fa-history'],
  'mon_status'=>['Equipment Status','fa-heartbeat'],'mon_alerts'=>['Alerts','fa-bell'],'mon_critical'=>['Critical Equipment','fa-life-ring'],'disposal'=>['Disposal','fa-trash-alt']];
$pageTabs = am_tab_group($subModule);
?>
<?php if (count($pageTabs) > 1): ?>
<style>.as-tabs{display:flex;gap:8px;margin-bottom:24px;overflow-x:auto;scroll-snap-type:x proximity;-webkit-overflow-scrolling:touch;padding-bottom:4px;}.as-tabs .as-tab{flex:0 0 auto;scroll-snap-align:start;white-space:nowrap;}</style>
<div class="as-tabs" id="as-tabs">
<?php foreach ($pageTabs as $t): [$tl, $ti] = $tabLabels[$t] ?? [$t, 'fa-circle']; ?>
  <button class="as-tab <?php echo $subModule==$t?'active':'';?>" data-tab="<?php echo $t;?>" onclick="location.href='assets.php?module=assets&sub=<?php echo $t;?>'"><i class="fas <?php echo $ti;?>"></i> <?php echo $tl;?></button>
<?php endforeach; ?>
</div>
<script>document.querySelector('#as-tabs .active')?.scrollIntoView({inline:'center',block:'nearest'});</script>
<?php endif; ?>

<script>
// shared by the transfers, assignments and disposal tabs
const amAPI = _asBase + '/api.php?endpoint=assets/asset-management&action=';
function amEsc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
async function amGet(action){try{const r=await fetch(amAPI+action);return await r.json();}catch(e){return {success:false,message:'Network error'};}}
async function amPost(action,body){try{const r=await fetch(amAPI+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});return await r.json();}catch(e){return {success:false,message:'Network error'};}}
function amBadge(s){const c={pending:['#fef3c7','#92400e'],approved:['#dbeafe','#1e40af'],completed:['#d1fae5','#065f46'],rejected:['#fee2e2','#991b1b'],assigned:['#dbeafe','#1e40af'],returned:['#d1fae5','#065f46'],lost_damaged:['#fee2e2','#991b1b']}[s]||['#f3f4f6','#374151'];return `<span style="padding:2px 8px;border-radius:10px;font-size:10px;background:${c[0]};color:${c[1]};white-space:nowrap;">${amEsc(String(s).replace('_',' / '))}</span>`;}
function amModal(id,title,body){
  const old=document.getElementById(id); if(old) old.remove();
  const m=document.createElement('div'); m.id=id; m.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  m.innerHTML=`<div style="background:white;border-radius:12px;width:520px;max-width:92vw;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);"><div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;"><h3 style="font-size:16px;font-weight:600;">${amEsc(title)}</h3><button type="button" onclick="document.getElementById('${id}').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div><div style="padding:20px;">${body}</div></div>`;
  document.body.appendChild(m);
}
function amField(label,inner){return `<div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">${label}</label>${inner}</div>`;}
const amInput='style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"';
// printable document in a new window (handover forms, disposal certificates, QR labels)
function amPrint(title,body){
  const w=window.open('','_blank'); if(!w) return alert('Allow pop-ups to print.');
  w.document.write('<!doctype html><title>'+amEsc(title)+'</title><style>body{font-family:Arial,sans-serif;margin:32px;color:#111}h1{font-size:20px;margin:0 0 4px}table{border-collapse:collapse;width:100%;margin:12px 0}td,th{border:1px solid #999;padding:6px 8px;font-size:13px;text-align:left}.sig{display:flex;gap:40px;margin-top:48px}.sig div{flex:1;border-top:1px solid #111;padding-top:4px;font-size:12px}.lbl{display:inline-block;width:150px;margin:6px;text-align:center;font-size:11px;page-break-inside:avoid}</style>'+body+'<script>window.onload=function(){window.print()}<\/script>');
  w.document.close();
}
</script>

<?php if ($subModule == 'summary'): ?>
<!-- ====== ASSET MANAGEMENT SUMMARY ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-table" style="color:var(--primary-color);"></i> Asset Management Summary</h3>
    <div style="display:flex;gap:8px;align-items:center;">
      <label style="font-size:12px;font-weight:500;">Period:</label>
      <input type="date" id="ams-from" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <span style="font-size:12px;">to</span>
      <input type="date" id="ams-to" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <button onclick="amsLoad()" style="padding:6px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-filter"></i> Apply</button>
    </div>
  </div>
  <div style="overflow-x:auto;">
    <table id="ams-table" style="width:100%;border-collapse:collapse;font-size:12px;white-space:nowrap;">
      <thead id="ams-thead"></thead>
      <tbody id="ams-tbody"><tr><td colspan="2" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>
<script>
const amsAssetAPI=_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets';
const amsCatAPI=_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets_categories';

async function amsInit(){
  // Default period: last month through today
  dfRange('ams-from','ams-to');
  amsLoad();
}

async function amsLoad(){
  const fromDate=document.getElementById('ams-from').value;
  const toDate=document.getElementById('ams-to').value;
  const thead=document.getElementById('ams-thead');
  const tbody=document.getElementById('ams-tbody');
  tbody.innerHTML='<tr><td colspan="2" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

  try{
    const [ar,cr]=await Promise.all([fetch(amsAssetAPI),fetch(amsCatAPI)]);
    const [aj,cj]=await Promise.all([ar.json(),cr.json()]);
    if(!aj.success||!cj.success){tbody.innerHTML='<tr><td colspan="2" style="padding:20px;color:#dc2626;">Error loading data</td></tr>';return;}
    
    const assets=aj.data||[];
    const categories=cj.data||[];
    if(!categories.length){tbody.innerHTML='<tr><td colspan="2" style="padding:20px;color:var(--gray-400);">No categories found.</td></tr>';return;}
    
    // Build header: Row Label | Category1 | Category2 | ... | Total
    const catCols=categories.map(c=>c.name+' '+parseFloat(c.depreciation_rate||0)+'%');
    thead.innerHTML=`<tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:8px 10px;text-align:left;font-weight:700;min-width:200px;">Asset Category</th>
      ${catCols.map(n=>'<th style="padding:8px 6px;text-align:right;font-weight:600;min-width:120px;">'+n+'</th>').join('')}
      <th style="padding:8px 10px;text-align:right;font-weight:700;min-width:130px;background:#f0fdf4;">Total</th>
    </tr>`;
    
    // Calculate per category
    const fmt=n=>parseFloat(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
    const data={};
    categories.forEach(c=>{data[c.id]={opening:0,purchaseAdd:0,capAdd:0,disposals:0,depStart:0,depPeriod:0,accumDep:0};});
    
    assets.forEach(a=>{
      const cid=a.category_id;
      if(!data[cid])return;
      const cost=parseFloat(a.purchase_cost||0);
      const accDep=parseFloat(a.accumulated_depreciation||0);
      const purchDate=a.purchase_date||'';
      const cat=categories.find(c=>c.id==cid);
      const rate=parseFloat(cat?.depreciation_rate||0);
      const monthlyDep=rate>0?cost*rate/100/12:0;
      
      // Determine if asset was acquired before or during the period
      if(purchDate && purchDate < fromDate){
        // Existing asset - opening balance
        data[cid].opening+=cost;
        data[cid].depStart+=accDep>monthlyDep?(accDep-monthlyDep):accDep;
      } else if(purchDate && purchDate >= fromDate && purchDate <= toDate){
        // Purchased during period
        data[cid].purchaseAdd+=cost;
      }
      
      // Capitalization additions (current_value > purchase_cost)
      const cv=parseFloat(a.current_value||0);
      if(cv>cost) data[cid].capAdd+=(cv-cost);
      
      // Depreciation this period
      data[cid].depPeriod+=monthlyDep;
      data[cid].accumDep+=accDep;
      
      // Disposals
      if(a.status==='disposed' && a.disposal_date && a.disposal_date>=fromDate && a.disposal_date<=toDate){
        data[cid].disposals+=cost;
      }
    });
    
    // Build rows
    const rows=[
      {label:'Opening Balance<br><small style="color:var(--gray-400);">(Before '+fromDate+')</small>',key:'opening'},
      {label:'Purchase Additions<br><small style="color:var(--gray-400);">(During Selected Period)</small>',key:'purchaseAdd'},
      {label:'Capitalization Additions<br><small style="color:var(--gray-400);">(During Selected Period)</small>',key:'capAdd'},
      {label:'Total Additions',key:'totalAdd',calc:d=>d.purchaseAdd+d.capAdd},
      {label:'Disposals<br><small style="color:var(--gray-400);">(During Selected Period)</small>',key:'disposals'},
      {label:'Current Cost',key:'currentCost',calc:d=>d.opening+d.purchaseAdd+d.capAdd-d.disposals},
      {label:'Depreciation at start<br><small style="color:var(--gray-400);">(Before selected period)</small>',key:'depStart'},
      {label:'Depreciation this period<br><small style="color:var(--gray-400);">For selected month</small>',key:'depPeriod'},
      {label:'Accumulated Depreciation<br><small style="color:var(--gray-400);">(At end of period)</small>',key:'accumDep'},
      {label:'Net Book Value<br><small style="color:var(--gray-400);">(At end of period)</small>',key:'nbv',calc:d=>(d.opening+d.purchaseAdd+d.capAdd-d.disposals)-d.accumDep}
    ];
    
    const rowColors={totalAdd:'#eff6ff',currentCost:'#f0fdf4',nbv:'#f0fdf4'};
    
    tbody.innerHTML=rows.map(row=>{
      const bg=rowColors[row.key]||'';
      const isBold=row.key==='totalAdd'||row.key==='currentCost'||row.key==='nbv';
      let total=0;
      const cells=categories.map(c=>{
        const d=data[c.id];
        const val=row.calc?row.calc(d):(d[row.key]||0);
        total+=val;
        return '<td style="padding:6px;text-align:right;'+(isBold?'font-weight:700;':'')+'">'+fmt(val)+'</td>';
      }).join('');
      return '<tr style="border-bottom:1px solid var(--gray-100);'+(bg?'background:'+bg+';':'')+'"><td style="padding:8px 10px;'+(isBold?'font-weight:700;':'')+'">'+row.label+'</td>'+cells+'<td style="padding:6px 10px;text-align:right;font-weight:700;background:#f0fdf4;">'+fmt(total)+'</td></tr>';
    }).join('');
    
  }catch(e){
    tbody.innerHTML='<tr><td colspan="2" style="padding:20px;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}

amsInit();
</script>
<?php elseif ($subModule == 'register'): ?>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-clipboard-list" style="color:var(--primary-color);"></i> Assets Register</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="date" id="as-from" onchange="asLoad()" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <span style="font-size:12px;">to</span>
      <input type="date" id="as-to" onchange="asLoad()" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <select id="as-catf" onchange="asLoad()" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><option value="">All categories</option></select>
      <button onclick="asNew()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> Add Asset</button>
      <button onclick="asLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:16px;" id="as-stats"></div>
  <!-- Table -->
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;white-space:nowrap;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:8px 6px;text-align:left;">Asset Type</th>
        <th style="padding:8px 6px;text-align:left;">Asset Code</th>
        <th style="padding:8px 6px;text-align:left;">Location</th>
        <th style="padding:8px 6px;text-align:left;">Category</th>
        <th style="padding:8px 6px;text-align:left;">Date of Acquisition</th>
        <th style="padding:8px 6px;text-align:center;">Depn %</th>
        <th style="padding:8px 6px;text-align:right;">Opening Cost</th>
        <th style="padding:8px 6px;text-align:right;">Purchase Additions</th>
        <th style="padding:8px 6px;text-align:right;">Capitalization Additions</th>
        <th style="padding:8px 6px;text-align:right;">Total Additions</th>
        <th style="padding:8px 6px;text-align:right;">Current Cost</th>
        <th style="padding:8px 6px;text-align:right;">Opening Depreciation</th>
        <th style="padding:8px 6px;text-align:right;">Depreciation (Month)</th>
        <th style="padding:8px 6px;text-align:right;">Accum Depreciation</th>
        <th style="padding:8px 6px;text-align:right;font-weight:700;">Net Book Value</th>
      </tr></thead>
      <tbody id="as-tbody"><tr><td colspan="15" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>
<script>
const asAPI=_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets';
function aEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
let asCategories=[];

// Set default dates
dfRange('as-from','as-to');

async function asLoad(){
  const fromDate=document.getElementById('as-from').value;
  const toDate=document.getElementById('as-to').value;
  try{
    const [ar,cr]=await Promise.all([fetch(asAPI),fetch(asAPI+'_categories')]);
    const [aj,cj]=await Promise.all([ar.json(),cr.json()]);
    if(cj.success) asCategories=cj.data;
    const catSel=document.getElementById('as-catf'), catF=catSel.value;
    catSel.innerHTML='<option value="">All categories</option>'+asCategories.map(c=>`<option value="${c.id}">${aEsc(c.name)}</option>`).join('');
    catSel.value=catF;
    const tbody=document.getElementById('as-tbody');
    if(aj.success&&aj.data&&aj.data.length){
      const assets=aj.data;
      // Filter assets acquired on or before toDate, and by category
      const filtered=assets.filter(a=>(!toDate||!a.purchase_date||a.purchase_date<=toDate)&&(!catF||a.category_id==catF));
      // Stats
      const totalCost=filtered.reduce((s,a)=>s+parseFloat(a.purchase_cost||0),0);
      const totalBV=filtered.reduce((s,a)=>s+parseFloat(a.current_value||0),0);
      const totalDep=filtered.reduce((s,a)=>s+parseFloat(a.accumulated_depreciation||0),0);
      document.getElementById('as-stats').innerHTML=`
        <div style="background:var(--gray-50);border-radius:8px;padding:12px;text-align:center;"><div style="font-size:20px;font-weight:700;color:var(--primary-color);">${filtered.length}</div><div style="font-size:11px;color:var(--gray-500);">Total Assets</div></div>
        <div style="background:var(--gray-50);border-radius:8px;padding:12px;text-align:center;"><div style="font-size:20px;font-weight:700;color:#2563eb;">${totalCost.toLocaleString()}</div><div style="font-size:11px;color:var(--gray-500);">Total Cost</div></div>
        <div style="background:var(--gray-50);border-radius:8px;padding:12px;text-align:center;"><div style="font-size:20px;font-weight:700;color:#16a34a;">${totalBV.toLocaleString()}</div><div style="font-size:11px;color:var(--gray-500);">Book Value</div></div>
        <div style="background:var(--gray-50);border-radius:8px;padding:12px;text-align:center;"><div style="font-size:20px;font-weight:700;color:#d97706;">${totalDep.toLocaleString()}</div><div style="font-size:11px;color:var(--gray-500);">Acc. Depreciation</div></div>`;
      tbody.innerHTML=filtered.map(a=>{
        const cat=asCategories.find(c=>c.id==a.category_id);
        const catName=cat?.name||'—';
        const depRate=cat?.depreciation_rate||0;
        const purchaseCost=parseFloat(a.purchase_cost||0);
        const accumDep=parseFloat(a.accumulated_depreciation||0);
        const currentValue=parseFloat(a.current_value||0);
        const nbv=purchaseCost-accumDep;
        // Monthly depreciation = annual rate * cost / 12
        const monthlyDep=depRate>0?Math.round(purchaseCost*depRate/100/12):0;
        // Opening cost = purchase_cost (original), additions would come from capitalizations
        // For now: purchase additions = 0, capitalization = currentCost - purchaseCost if positive
        const capAdditions=currentValue>purchaseCost?(currentValue-purchaseCost):0;
        const totalAdditions=capAdditions;
        const currentCost=purchaseCost+totalAdditions;
        // Opening depreciation = accum - monthly (approx)
        const openingDep=accumDep>monthlyDep?(accumDep-monthlyDep):0;
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:6px;font-weight:500;">${aEsc(a.name)}</td>
          <td style="padding:6px;font-family:monospace;color:var(--primary-color);font-size:11px;">${aEsc(a.asset_code||'')}</td>
          <td style="padding:6px;font-size:11px;">${aEsc(a.location||'—')}</td>
          <td style="padding:6px;font-size:11px;">${aEsc(catName)}</td>
          <td style="padding:6px;font-size:11px;">${a.purchase_date||'—'}</td>
          <td style="padding:6px;text-align:center;">${depRate}%</td>
          <td style="padding:6px;text-align:right;">${purchaseCost.toLocaleString()}</td>
          <td style="padding:6px;text-align:right;">0</td>
          <td style="padding:6px;text-align:right;">${capAdditions.toLocaleString()}</td>
          <td style="padding:6px;text-align:right;">${totalAdditions.toLocaleString()}</td>
          <td style="padding:6px;text-align:right;">${currentCost.toLocaleString()}</td>
          <td style="padding:6px;text-align:right;">${openingDep.toLocaleString()}</td>
          <td style="padding:6px;text-align:right;">${monthlyDep.toLocaleString()}</td>
          <td style="padding:6px;text-align:right;">${accumDep.toLocaleString()}</td>
          <td style="padding:6px;text-align:right;font-weight:700;color:#059669;">${nbv.toLocaleString()}</td>
        </tr>`;}).join('');
    } else tbody.innerHTML='<tr><td colspan="15" style="padding:30px;text-align:center;color:var(--gray-400);">No assets registered.</td></tr>';
  }catch(e){}
}

let asGrnLines=[];
async function asLoadGrn(all){try{const r=await fetch(asAPI+'_grn_lines'+(all?'&all=1':''));const j=await r.json();asGrnLines=j.success?j.data:[];}catch(e){asGrnLines=[];}}
const asGrnOptions=()=>asGrnLines.map(l=>`<option value="${l.id}">${aEsc(l.item_name)} · ${aEsc(l.grn_number)} · ${l.units_left} unit(s) left · ${parseFloat(l.unit_cost).toLocaleString()} RWF</option>`).join('');
async function asGrnAll(on){await asLoadGrn(on);document.getElementById('as-grn').innerHTML='<option value="">Not from a delivery: bought directly</option>'+asGrnOptions();asPickGrn();}
// Picking a delivery fills in what the goods receipt already knows and locks the cost to it
function asPickGrn(){
  const l=asGrnLines.find(x=>String(x.id)===document.getElementById('as-grn').value), cost=document.getElementById('as-cost');
  cost.readOnly=!!l; cost.style.background=l?'var(--gray-50)':'';
  if(!l) return;
  document.getElementById('as-name').value=l.item_name; cost.value=l.unit_cost; document.getElementById('as-date').value=l.received_date;
  document.getElementById('as-supplier').value=l.supplier||'';
}
async function asNew(){
  if(!asCategories.length){const r=await fetch(asAPI+'_categories');const j=await r.json();if(j.success)asCategories=j.data;}
  await asLoadGrn(false);
  const grnOpts=asGrnOptions();
  const catOpts=asCategories.map(c=>`<option value="${c.id}">${aEsc(c.name)} (${c.depreciation_rate}%)</option>`).join('');
  const modal=document.createElement('div');modal.id='as-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:550px;max-width:90vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;"><h3 style="font-size:16px;font-weight:600;">Add New Asset</h3><button onclick="document.getElementById('as-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    <div style="padding:20px;">
      <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Received on a delivery <span style="font-weight:400;color:var(--gray-500);">(optional)</span></label><select id="as-grn" onchange="asPickGrn()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Not from a delivery: bought directly</option>${grnOpts}</select>
        <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Equipment already received through procurement: one unit moves out of stock into this asset, so it is not counted twice. <label style="white-space:nowrap;"><input type="checkbox" id="as-grn-all" onchange="asGrnAll(this.checked)"> show every delivered item</label></div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Asset Name *</label><input id="as-name" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Category *</label><select id="as-cat" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Select...</option>${catOpts}</select></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Purchase Date</label><input type="date" id="as-date" value="<?php echo date('Y-m-d');?>" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Purchase Cost *</label><input type="number" id="as-cost" placeholder="0" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Location</label><input id="as-loc" placeholder="e.g. Building A, Room 101" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Serial Number</label><input id="as-serial" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Supplier</label><input id="as-supplier" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Warranty Expiry</label><input type="date" id="as-warranty" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      </div>
      <button onclick="asSave()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save Asset</button>
    </div></div>`;
  document.body.appendChild(modal);
}

async function asSave(notDelivery){
  const name=document.getElementById('as-name').value.trim();
  const cat=document.getElementById('as-cat').value;
  const cost=document.getElementById('as-cost').value;
  if(!name||!cat||!cost) return alert('Fill name, category, and cost.');
  try{
    const r=await fetch(asAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,category_id:parseInt(cat),purchase_date:document.getElementById('as-date').value,purchase_cost:parseFloat(cost),location:document.getElementById('as-loc').value,serial_number:document.getElementById('as-serial').value,supplier:document.getElementById('as-supplier').value,warranty_expiry:document.getElementById('as-warranty').value||null,grn_item_id:parseInt(document.getElementById('as-grn').value)||0,not_delivery:notDelivery===true})});
    const j=await r.json();
    if(j.success){alert('✓ Asset added!');document.getElementById('as-modal').remove();asLoad();}
    else if(r.status===409&&/still in stock/.test(j.message||'')){ if(confirm(j.message)){ asSave(true); } }
    else alert(j.message);
  }catch(e){alert('Failed');}
}
<?php if ($assetAutoNew): ?>asLoad().then(function(){ asNew(); });<?php else: ?>asLoad();<?php endif; ?>
</script>

<?php elseif ($subModule == 'opening'): ?>
<!-- ====== OPENING BALANCE ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-book-open" style="color:var(--primary-color);"></i> Record Opening Balance</h3>
  </div>
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">Record opening balance for asset accounts. This creates a journal entry: <strong>Debit</strong> the asset account, <strong>Credit</strong> Opening Equity Balance (77).</p>

  <!-- Form -->
  <div style="border:1px solid var(--gray-200);border-radius:8px;padding:20px;margin-bottom:20px;">
    <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:12px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">Asset Details</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Asset Name *</label>
        <input type="text" id="ob-name" placeholder="e.g. Hospital Building" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Category *</label>
        <select id="ob-category" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="">Select category...</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Location</label>
        <input type="text" id="ob-location" placeholder="e.g. Main Building, Floor 1" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Serial Number</label>
        <input type="text" id="ob-serial" placeholder="Optional" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>

    <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:12px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">Journal Entry</h4>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Asset Account (parent_id=14) *</label>
        <select id="ob-account" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="">Loading accounts...</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Amount / Value (RWF) *</label>
        <input type="number" id="ob-amount" min="1" placeholder="e.g. 5000000" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Purchase/Acquisition Date</label>
        <input type="date" id="ob-date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Description</label>
        <input type="text" id="ob-desc" placeholder="e.g. Opening balance - Building" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>
    <div style="margin-top:16px;">
      <button onclick="obSubmit()" id="ob-submit-btn" style="padding:10px 24px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-check-circle"></i> Record Opening Balance</button>
    </div>
  </div>

  <!-- Journal Entry Preview -->
  <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:20px;" id="ob-preview" style="display:none;">
    <h4 style="font-size:12px;font-weight:600;color:#1e40af;margin-bottom:6px;"><i class="fas fa-book"></i> Journal Entry Preview</h4>
    <div id="ob-preview-content" style="font-size:12px;color:var(--gray-600);">Select an account and enter amount to see preview.</div>
  </div>

  <!-- History -->
  <h4 style="font-size:14px;font-weight:600;margin-bottom:12px;"><i class="fas fa-history" style="color:var(--gray-400);"></i> Opening Balance Entries</h4>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px;text-align:left;">Asset</th>
        <th style="padding:10px;text-align:left;">Category</th>
        <th style="padding:10px;text-align:right;">Amount</th>
        <th style="padding:10px;text-align:left;">Date</th>
        <th style="padding:10px;text-align:center;">Action</th>
      </tr></thead>
      <tbody id="ob-tbody"><tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>

<!-- Edit Modal -->
<div id="ob-edit-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:500px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Edit Opening Balance</h3>
      <button onclick="document.getElementById('ob-edit-modal').style.display='none'" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <input type="hidden" id="ob-edit-id">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div style="grid-column:1/-1;">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Asset Name *</label>
        <input type="text" id="ob-edit-name" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Category *</label>
        <select id="ob-edit-category" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="">Select...</option>
        </select>
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Date *</label>
        <input type="date" id="ob-edit-date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Amount (RWF) *</label>
        <input type="number" id="ob-edit-amount" min="1" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Location</label>
        <input type="text" id="ob-edit-location" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>
    <button onclick="obSaveEdit()" id="ob-edit-btn" style="width:100%;margin-top:16px;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-save"></i> Save Changes</button>
  </div>
</div>

<script>
const obCoaAPI=_asBase + '/api.php?endpoint=finance/chart-of-accounts';
const obAssetAPI=_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets';

async function obInit(){
  document.getElementById('ob-date').value=new Date().toISOString().split('T')[0];
  // Load asset accounts (parent_id=14)
  try{
    const r=await fetch(obCoaAPI+'&parent_id=14');
    const j=await r.json();
    if(j.success){
      const accounts=j.data?.accounts||j.data||[];
      const sel=document.getElementById('ob-account');
      sel.innerHTML='<option value="">Select asset account...</option>'+accounts.map(a=>'<option value="'+a.id+'">'+a.account_code+' - '+a.account_name+'</option>').join('');
    }
  }catch(e){}
  // Load categories
  try{
    const r=await fetch(obAssetAPI+'_categories');
    const j=await r.json();
    if(j.success){
      const cats=j.data||[];
      document.getElementById('ob-category').innerHTML='<option value="">Select category...</option>'+cats.map(c=>'<option value="'+c.id+'">'+c.name+'</option>').join('');
    }
  }catch(e){}
  obLoadHistory();
  // Live preview
  document.getElementById('ob-account').addEventListener('change',obUpdatePreview);
  document.getElementById('ob-amount').addEventListener('input',obUpdatePreview);
}

function obUpdatePreview(){
  const sel=document.getElementById('ob-account');
  const accName=sel.selectedOptions[0]?sel.selectedOptions[0].textContent:'—';
  const amount=parseFloat(document.getElementById('ob-amount').value)||0;
  const div=document.getElementById('ob-preview-content');
  if(!sel.value||!amount){div.innerHTML='Select an account and enter amount to see preview.';return;}
  div.innerHTML=`<table style="width:100%;font-size:12px;">
    <tr><td style="padding:3px 0;font-weight:600;">Dr: ${accName}</td><td style="text-align:right;font-weight:600;">${amount.toLocaleString()} RWF</td><td></td></tr>
    <tr><td style="padding:3px 0;padding-left:20px;color:var(--gray-500);">Cr: Opening Balance Equity</td><td></td><td style="text-align:right;font-weight:600;">${amount.toLocaleString()} RWF</td></tr>
  </table>`;
}

async function obSubmit(){
  const name=document.getElementById('ob-name').value.trim();
  const categoryId=parseInt(document.getElementById('ob-category').value)||0;
  const location=document.getElementById('ob-location').value.trim();
  const serial=document.getElementById('ob-serial').value.trim();
  const accountId=parseInt(document.getElementById('ob-account').value);
  const amount=parseFloat(document.getElementById('ob-amount').value);
  const entryDate=document.getElementById('ob-date').value;
  const description=document.getElementById('ob-desc').value.trim()||'Opening balance - '+name;
  
  if(!name)return alert('Please enter the asset name.');
  if(!categoryId)return alert('Please select a category.');
  if(!accountId)return alert('Please select an asset account.');
  if(!amount||amount<=0)return alert('Please enter a valid amount.');
  
  const btn=document.getElementById('ob-submit-btn');
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
  
  try{
    // The server creates the asset and posts Dr asset account / Cr opening equity in one transaction
    const assetRes=await fetch(obCoaAPI+'&action=assets',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        name:name,
        category_id:categoryId,
        account_id:accountId,
        opening:true,
        purchase_date:entryDate,
        purchase_cost:amount,
        location:location,
        serial_number:serial,
        supplier:'Opening Balance'
      })
    });
    const assetJson=await assetRes.json();
    if(!assetJson.success){alert('Failed to create asset: '+assetJson.message);return;}
    alert('✓ Asset registered & journal entry posted! Asset: '+assetJson.data.asset_code);
    document.getElementById('ob-name').value='';
    document.getElementById('ob-amount').value='';
    document.getElementById('ob-desc').value='';
    document.getElementById('ob-location').value='';
    document.getElementById('ob-serial').value='';
    obUpdatePreview();
    obLoadHistory();  }catch(e){alert('Failed: '+e.message);}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-check-circle"></i> Record Opening Balance';}
}

async function obLoadHistory(){
  const tbody=document.getElementById('ob-tbody');
  try{
    // Fetch assets where supplier='Opening Balance'
    const r=await fetch(obAssetAPI);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      const openingAssets=j.data.filter(a=>a.supplier==='Opening Balance');
      if(openingAssets.length){
        tbody.innerHTML=openingAssets.map(a=>{
          const catName=a.category_name||'—';
          return `<tr style="border-bottom:1px solid var(--gray-100);">
            <td style="padding:8px 10px;"><div style="font-weight:600;">${a.name}</div><div style="font-size:11px;color:var(--gray-500);font-family:monospace;">${a.asset_code||''}</div></td>
            <td style="padding:8px 10px;font-size:12px;">${catName}</td>
            <td style="padding:8px 10px;text-align:right;font-weight:600;">${parseFloat(a.purchase_cost||0).toLocaleString()} RWF</td>
            <td style="padding:8px 10px;font-size:12px;">${a.purchase_date||'—'}</td>
            <td style="padding:8px 10px;text-align:center;">
              <button onclick="obEdit(${a.id},'${a.name.replace(/'/g,"\\'")}',${a.category_id||0},'${a.purchase_date||''}',${a.purchase_cost||0},'${(a.location||'').replace(/'/g,"\\'")}')" style="padding:4px 10px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-edit"></i> Edit</button>
            </td>
          </tr>`;
        }).join('');
      }else{
        tbody.innerHTML='<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--gray-400);">No opening balance entries yet.</td></tr>';
      }
    }else{
      tbody.innerHTML='<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--gray-400);">No opening balance entries yet.</td></tr>';
    }
  }catch(e){
    tbody.innerHTML='<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--gray-400);">No entries found.</td></tr>';
  }
}

function obEdit(id, name, categoryId, date, amount, location){
  document.getElementById('ob-edit-id').value=id;
  document.getElementById('ob-edit-name').value=name;
  document.getElementById('ob-edit-date').value=date;
  document.getElementById('ob-edit-amount').value=amount;
  document.getElementById('ob-edit-location').value=location;
  // Copy categories from main select
  const mainCats=document.getElementById('ob-category').innerHTML;
  document.getElementById('ob-edit-category').innerHTML=mainCats;
  document.getElementById('ob-edit-category').value=categoryId;
  document.getElementById('ob-edit-modal').style.display='flex';
}

async function obSaveEdit(){
  const id=document.getElementById('ob-edit-id').value;
  const name=document.getElementById('ob-edit-name').value.trim();
  const categoryId=parseInt(document.getElementById('ob-edit-category').value)||0;
  const date=document.getElementById('ob-edit-date').value;
  const amount=parseFloat(document.getElementById('ob-edit-amount').value)||0;
  const location=document.getElementById('ob-edit-location').value.trim();
  
  if(!name)return alert('Asset name is required.');
  if(!categoryId)return alert('Category is required.');
  if(!amount)return alert('Amount is required.');
  
  const btn=document.getElementById('ob-edit-btn');
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';
  
  try{
    // Update asset
    const r=await fetch(_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets&id='+id,{
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({name,category_id:categoryId,purchase_date:date,purchase_cost:amount,current_value:amount,location})
    });
    const j=await r.json();
    if(j.success){
      alert('✓ Asset updated successfully!');
      document.getElementById('ob-edit-modal').style.display='none';
      obLoadHistory();
    }else{
      alert('Error: '+j.message);
    }
  }catch(e){alert('Failed: '+e.message);}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> Save Changes';}
}

obInit();
</script>

<?php elseif ($subModule == 'categories'): ?>
<!-- ====== CATEGORIES ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-tags" style="color:var(--primary-color);"></i> Asset Categories & Depreciation Rates</h3>
    <button onclick="catOpen()" style="padding:9px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-plus"></i> Add Category</button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">Category</th><th style="padding:10px;text-align:center;">Rate (%)</th><th style="padding:10px;text-align:center;">Method</th><th style="padding:10px;text-align:center;">Useful life</th><th style="padding:10px;text-align:left;">Asset account</th><th style="padding:10px;text-align:center;">Assets</th><th style="padding:10px;text-align:center;">Actions</th></tr></thead>
      <tbody id="cat-tbody" data-no-page><tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>
<script>
let catRows=[], catAccounts=[];
function catAccOpts(sel){return '<option value="">— none —</option>'+catAccounts.map(a=>`<option value="${a.id}" ${a.id==sel?'selected':''}>${amEsc(a.account_code)} - ${amEsc(a.account_name)}</option>`).join('');}
async function catLoad(){
  const j=await amGet('categories');
  if(!j.success){document.getElementById('cat-tbody').innerHTML=`<tr><td colspan="7" style="padding:30px;text-align:center;">${amEsc(j.message)}</td></tr>`;return;}
  catRows=j.data;
  document.getElementById('cat-tbody').innerHTML=catRows.map(c=>`<tr style="border-bottom:1px solid var(--gray-100);">
    <td style="padding:10px;font-weight:600;">${amEsc(c.name)}</td>
    <td style="padding:10px;text-align:center;font-weight:700;color:${parseFloat(c.depreciation_rate)===0?'var(--gray-400)':'#d97706'};">${c.depreciation_rate}%</td>
    <td style="padding:10px;text-align:center;font-size:12px;">${c.depreciation_method==='none'?'No Depreciation':c.depreciation_method.replace('_',' ')}</td>
    <td style="padding:10px;text-align:center;">${c.useful_life_years>0?c.useful_life_years+' years':'∞'}</td>
    <td style="padding:10px;font-size:12px;">${amEsc(c.account_label||'—')}</td>
    <td style="padding:10px;text-align:center;">${c.assets}</td>
    <td style="padding:10px;text-align:center;white-space:nowrap;">
      <button onclick="catOpen(${c.id})" title="Edit" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;color:var(--primary-color);"><i class="fas fa-edit"></i></button>
      <button onclick="catDel(${c.id})" title="Delete" style="padding:5px 10px;background:#fee2e2;border:none;border-radius:6px;cursor:pointer;color:#dc2626;"><i class="fas fa-trash"></i></button>
    </td></tr>`).join('')||'<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No categories</td></tr>';
}
function catOpen(id){
  const c=catRows.find(x=>x.id==id)||{};
  amModal('cat-modal',c.id?'Edit category':'Add category',
    amField('Category name *',`<input id="cat-name" value="${amEsc(c.name||'')}" ${amInput}>`)+
    amField('Depreciation method',`<select id="cat-method" ${amInput}>${[['straight_line','Straight line'],['reducing_balance','Reducing balance'],['none','No depreciation']].map(m=>`<option value="${m[0]}" ${(c.depreciation_method||'straight_line')===m[0]?'selected':''}>${m[1]}</option>`).join('')}</select>`)+
    amField('Rate (% per year)',`<input id="cat-rate" type="number" min="0" max="100" step="0.01" value="${c.depreciation_rate??0}" ${amInput}>`)+
    amField('Useful life (years, 0 = unlimited)',`<input id="cat-life" type="number" min="0" step="1" value="${c.useful_life_years||0}" ${amInput}>`)+
    amField('Asset account (ledger)',`<select id="cat-acc" ${amInput}>${catAccOpts(c.account_id)}</select>`)+
    `<div id="cat-err" style="display:none;margin-bottom:10px;color:#991b1b;font-size:13px;"></div>
     <button onclick="catSave(${c.id||0})" style="width:100%;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save</button>`);
}
async function catSave(id){
  const j=await amPost('category_save',{id,name:document.getElementById('cat-name').value,depreciation_method:document.getElementById('cat-method').value,
    depreciation_rate:document.getElementById('cat-rate').value,useful_life_years:document.getElementById('cat-life').value,account_id:document.getElementById('cat-acc').value});
  if(j.success){document.getElementById('cat-modal').remove();catLoad();}
  else{const e=document.getElementById('cat-err');e.textContent=j.message||'Save failed';e.style.display='block';}
}
async function catDel(id){
  const c=catRows.find(x=>x.id==id);
  if(!confirm(`Delete "${c.name}"?`))return;
  const j=await amPost('category_delete',{id});
  j.success?catLoad():alert(j.message);
}
amGet('asset_accounts').then(j=>{catAccounts=j.data||[];});
catLoad();
</script>

<?php elseif ($subModule == 'depreciation'): ?>
<!-- ====== DEPRECIATION ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-chart-line" style="color:#d97706;"></i> Monthly Depreciation</h3>
    <div style="display:flex;gap:8px;align-items:center;">
      <label style="font-size:12px;font-weight:500;">Month:</label>
      <select id="dep-month" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <option value="1">January</option><option value="2">February</option><option value="3">March</option>
        <option value="4">April</option><option value="5">May</option><option value="6">June</option>
        <option value="7">July</option><option value="8">August</option><option value="9">September</option>
        <option value="10">October</option><option value="11">November</option><option value="12">December</option>
      </select>
      <select id="dep-year" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></select>
      <button onclick="depRun()" style="padding:8px 16px;background:#d97706;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-calculator"></i> Run Monthly Depreciation</button>
    </div>
  </div>
  <p style="font-size:12px;color:var(--gray-500);margin-bottom:16px;">Depreciation is calculated monthly (annual rate ÷ 12). Running creates journal entries: <strong>Dr</strong> Depreciation Expense, <strong>Cr</strong> Accumulated Depreciation. A month that was already run is skipped, and a month that has not started cannot be run.</p>
  <div id="dep-content"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
function aEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
const depCoaAPI=_asBase + '/api.php?endpoint=finance/chart-of-accounts';

function depInit(){
  const now=new Date();
  document.getElementById('dep-month').value=now.getMonth()+1;
  const ySel=document.getElementById('dep-year');
  for(let y=now.getFullYear();y>=2019;y--) ySel.innerHTML+=`<option value="${y}" ${y===now.getFullYear()?'selected':''}>${y}</option>`;
  depLoad();
}

async function depLoad(){
  const r=await fetch(depCoaAPI+'&action=assets');
  const j=await r.json();
  if(j.success&&j.data&&j.data.length){
    const cr=await fetch(depCoaAPI+'&action=assets_categories');
    const cj=await cr.json();
    const cats=cj.success?cj.data:[];
    const activeAssets=j.data.filter(a=>a.status==='active');
    let totalMonthly=0;
    let html='<div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">Asset</th><th style="padding:8px;text-align:left;">Category</th><th style="padding:8px;text-align:right;">Cost</th><th style="padding:8px;text-align:center;">Annual Rate</th><th style="padding:8px;text-align:right;">Monthly Dep.</th><th style="padding:8px;text-align:right;">Acc. Dep.</th><th style="padding:8px;text-align:right;">Net Book Value</th></tr></thead><tbody>';
    activeAssets.forEach(a=>{
      const cat=cats.find(c=>c.id==a.category_id)||{};
      const rate=parseFloat(cat.depreciation_rate||0);
      const cost=parseFloat(a.purchase_cost||0);
      const accDep=parseFloat(a.accumulated_depreciation||0);
      const bv=cost-accDep;
      const monthlyDep=rate>0&&bv>0?Math.min(Math.round(cost*rate/100/12),bv):0;
      totalMonthly+=monthlyDep;
      html+=`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:8px;font-weight:500;">${aEsc(a.name)}</td><td style="padding:8px;font-size:11px;">${aEsc(cat.name||'')}</td><td style="padding:8px;text-align:right;">${cost.toLocaleString()}</td><td style="padding:8px;text-align:center;font-weight:600;color:#d97706;">${rate}%</td><td style="padding:8px;text-align:right;color:#d97706;font-weight:600;">${monthlyDep.toLocaleString()}</td><td style="padding:8px;text-align:right;">${accDep.toLocaleString()}</td><td style="padding:8px;text-align:right;font-weight:600;color:#16a34a;">${bv.toLocaleString()}</td></tr>`;
    });
    html+=`<tr style="background:var(--gray-50);font-weight:700;"><td colspan="4" style="padding:10px 8px;">Total Monthly Depreciation</td><td style="padding:10px 8px;text-align:right;color:#d97706;">${totalMonthly.toLocaleString()}</td><td colspan="2"></td></tr>`;
    html+='</tbody></table></div>';
    document.getElementById('dep-content').innerHTML=html;
  } else document.getElementById('dep-content').innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No active assets to depreciate.</div>';
}

async function depRun(){
  const month=document.getElementById('dep-month').value;
  const year=document.getElementById('dep-year').value;
  const months=['','January','February','March','April','May','June','July','August','September','October','November','December'];
  
  if(!confirm('Run depreciation for '+months[month]+' '+year+'?\n\nThis will:\n• Calculate monthly depreciation for each active asset\n• Update accumulated depreciation on each asset\n• Create one journal entry: Dr Depreciation Expense / Cr Accumulated Depreciation'))return;
  
  try{
    // 1. Get active assets with their categories
    const ar=await fetch(depCoaAPI+'&action=assets');
    const aj=await ar.json();
    const cr=await fetch(depCoaAPI+'&action=assets_categories');
    const cj=await cr.json();
    if(!aj.success||!cj.success){alert('Failed to load assets');return;}
    
    const cats=cj.data||[];
    const activeAssets=(aj.data||[]).filter(a=>a.status==='active');
    
    // 2. Calculate monthly depreciation for each asset
    let totalDep=0;
    const depItems=[];
    activeAssets.forEach(a=>{
      const cat=cats.find(c=>c.id==a.category_id)||{};
      const rate=parseFloat(cat.depreciation_rate||0);
      const cost=parseFloat(a.purchase_cost||0);
      const accDep=parseFloat(a.accumulated_depreciation||0);
      const bv=cost-accDep;
      if(rate>0&&bv>0){
        const monthlyDep=Math.min(Math.round(cost*rate/100/12),bv);
        if(monthlyDep>0){
          depItems.push({id:a.id,name:a.name,amount:monthlyDep});
          totalDep+=monthlyDep;
        }
      }
    });
    
    if(totalDep<=0){alert('No depreciation to process. All assets are fully depreciated or have 0% rate.');return;}
    
    // 3. Run depreciation on backend (update assets)
    const depRes=await fetch(depCoaAPI+'&action=assets_depreciate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({month:parseInt(month),year:parseInt(year)})});
    const depJson=await depRes.json();
    
    // The server posts Dr depreciation expense / Cr accumulated depreciation for what it just depreciated
    if(depJson.success){
      alert('✓ Depreciation processed for '+months[month]+' '+year+'!\n\nTotal: '+parseFloat(depJson.data.total).toLocaleString()+' RWF\nAssets: '+depJson.data.assets+(depJson.data.assets?'':'\n(this month was already depreciated)'));
    }else{
      alert('Depreciation failed: '+depJson.message);
    }
    depLoad();
  }catch(e){alert('Error: '+e.message);}
}

depInit();
</script>

<?php elseif ($subModule == 'disposal'): ?>
<!-- ====== DISPOSAL (request, then approval by an administrator or the managing director who did not raise it) ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-trash-alt" style="color:#dc2626;"></i> Asset Disposal</h3>
  </div>
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">Raise a disposal request. Nothing is posted until an administrator or the managing director, other than the person who raised it, approves. Gain or loss is calculated on the book value at approval.</p>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Asset</label><select id="disp-asset" onchange="dispCalc()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Select asset...</option></select></div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Disposal Method</label><select id="disp-method" onchange="dispCalc()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="sold">Sold</option><option value="scrapped">Scrapped</option><option value="donated">Donated</option><option value="written_off">Written Off</option></select></div>
    <div id="disp-proceeds-box"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Sale proceeds</label><input type="number" min="0" step="0.01" id="disp-value" oninput="dispCalc()" placeholder="0" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    <div id="disp-account-box"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Proceeds received in</label><select id="disp-proceeds" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></select></div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Disposal Date</label><input type="date" id="disp-date" max="<?php echo date('Y-m-d');?>" value="<?php echo date('Y-m-d');?>" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Current condition *</label><input type="text" id="disp-cond" placeholder="e.g. Irreparable, motor burnt out" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Supporting assessment</label><input type="text" id="disp-assess" placeholder="Who inspected it and what they found" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Reason *</label><input type="text" id="disp-reason" placeholder="Why is the asset being disposed of?" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
  </div>
  <div id="disp-calc" style="display:none;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:14px;margin-bottom:16px;">
    <table style="width:100%;font-size:13px;">
      <tr><td>Purchase Cost:</td><td style="text-align:right;font-weight:600;" id="dc-cost">0</td></tr>
      <tr><td>Accumulated Depreciation:</td><td style="text-align:right;" id="dc-dep">0</td></tr>
      <tr><td>Book Value (NBV):</td><td style="text-align:right;font-weight:600;" id="dc-bv">0</td></tr>
      <tr><td>Disposal Proceeds:</td><td style="text-align:right;" id="dc-proceeds">0</td></tr>
      <tr style="border-top:2px solid var(--gray-300);"><td style="padding-top:8px;font-weight:700;" id="dc-label">Gain/Loss:</td><td style="padding-top:8px;text-align:right;font-weight:700;font-size:15px;" id="dc-gl">0</td></tr>
    </table>
  </div>
  <button id="disp-submit" onclick="dispSubmit()" style="padding:10px 20px;background:#dc2626;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-paper-plane"></i> Send for approval</button>

  <div style="margin-top:24px;border-top:1px solid var(--gray-200);padding-top:16px;">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:10px;">Disposal requests</h4>
    <div id="disp-list"><div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">Loading...</div></div>
  </div>
</div>
<script>
function aEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
let dispAssets=[], dispMe={};
async function dispInit(){
  asFillCash('disp-proceeds','Cash on hand (default)');
  const [aj,me]=await Promise.all([amGet('assets'),amGet('me')]);
  if(aj.success) dispAssets=aj.data;
  if(me.success) dispMe=me.data;
  const sel=document.getElementById('disp-asset');
  dispAssets.forEach(a=>{sel.innerHTML+=`<option value="${a.id}">${amEsc(a.asset_code||'')} - ${amEsc(a.name)} (NBV: ${parseFloat(a.book_value||0).toLocaleString()})</option>`;});
  dispCalc(); dispLoad();
}
function dispCalc(){
  const sold=document.getElementById('disp-method').value==='sold';
  document.getElementById('disp-proceeds-box').style.display=sold?'':'none';
  document.getElementById('disp-account-box').style.display=sold?'':'none';
  const id=document.getElementById('disp-asset').value;
  if(!id){document.getElementById('disp-calc').style.display='none';return;}
  const asset=dispAssets.find(a=>a.id==id);
  if(!asset) return;
  const cost=parseFloat(asset.purchase_cost||0), dep=parseFloat(asset.accumulated_depreciation||0), bv=cost-dep;
  const proceeds=sold?(parseFloat(document.getElementById('disp-value').value)||0):0;
  const gl=proceeds-bv;
  document.getElementById('dc-cost').textContent=cost.toLocaleString();
  document.getElementById('dc-dep').textContent=dep.toLocaleString();
  document.getElementById('dc-bv').textContent=bv.toLocaleString();
  document.getElementById('dc-proceeds').textContent=proceeds.toLocaleString();
  document.getElementById('dc-label').textContent=gl>=0?'Gain on Disposal:':'Loss on Disposal:';
  document.getElementById('dc-gl').textContent=gl.toLocaleString();
  document.getElementById('dc-gl').style.color=gl>=0?'#065f46':'#991b1b';
  document.getElementById('disp-calc').style.display='block';
}
async function dispSubmit(){
  const id=document.getElementById('disp-asset').value;
  if(!id) return alert('Select an asset.');
  const reason=document.getElementById('disp-reason').value.trim();
  if(!reason) return alert('Give the reason for the disposal.');
  const asset_condition=document.getElementById('disp-cond').value.trim();
  if(!asset_condition) return alert('Record the current condition of the asset.');
  const btn=document.getElementById('disp-submit'); btn.disabled=true;
  const j=await amPost('disposal_request',{asset_id:parseInt(id),method:document.getElementById('disp-method').value,proceeds:parseFloat(document.getElementById('disp-value').value)||0,proceeds_account_id:parseInt(document.getElementById('disp-proceeds').value)||0,disposal_date:document.getElementById('disp-date').value,reason,asset_condition,assessment:document.getElementById('disp-assess').value});
  btn.disabled=false;
  if(j.success){alert('✓ '+j.message);location.reload();}else alert(j.message);
}
async function dispLoad(){
  const j=await amGet('disposals'); const div=document.getElementById('disp-list');
  if(!j.success){div.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">'+amEsc(j.message||'Could not load')+'</div>';return;}
  dispRows=j.data;
  if(!j.data.length){div.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">No disposal requests yet.</div>';return;}
  div.innerHTML='<div style="border:1px solid var(--gray-200);border-radius:6px;overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">No.</th><th style="padding:8px;text-align:left;">Asset</th><th style="padding:8px;text-align:left;">Method</th><th style="padding:8px;text-align:left;">Date</th><th style="padding:8px;text-align:right;">Proceeds</th><th style="padding:8px;text-align:right;">Gain / (Loss)</th><th style="padding:8px;text-align:left;">Requested by</th><th style="padding:8px;text-align:left;">Status</th><th style="padding:8px;text-align:center;">Action</th></tr></thead><tbody>'+j.data.map(d=>{
    const mine=d.requested_by==dispMe.user_id;
    let act='';
    if(d.status==='pending'){
      if(dispMe.can_decide_disposal&&!mine) act=`<button onclick="dispDecide(${d.id},'approve')" style="padding:3px 8px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:3px;">Approve</button><button onclick="dispDecide(${d.id},'reject')" style="padding:3px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;">Reject</button>`;
      else act='<span style="font-size:10px;color:var(--gray-400);">'+(mine?'awaiting another approver':'awaiting approval')+'</span>';
    }
    if(d.status==='approved') act=`<button onclick="dispCert(${d.id})" style="padding:3px 8px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:4px;font-size:10px;cursor:pointer;"><i class="fas fa-print"></i> Certificate</button>`;
    const gl=d.gain_loss==null?'—':parseFloat(d.gain_loss).toLocaleString();
    return `<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 8px;font-family:monospace;">${amEsc(d.disposal_no)}</td><td style="padding:6px 8px;">${amEsc(d.asset_code)} - ${amEsc(d.asset_name)}<div style="font-size:11px;color:var(--gray-500);">${amEsc(d.reason||'')}${d.asset_condition?' · condition: '+amEsc(d.asset_condition):''}${d.decision_note?' · '+amEsc(d.decision_note):''}</div></td><td style="padding:6px 8px;">${amEsc(String(d.method).replace('_',' '))}</td><td style="padding:6px 8px;">${amEsc(d.disposal_date)}</td><td style="padding:6px 8px;text-align:right;">${parseFloat(d.proceeds).toLocaleString()}</td><td style="padding:6px 8px;text-align:right;">${gl}</td><td style="padding:6px 8px;">${amEsc(d.requested_by_name||'—')}</td><td style="padding:6px 8px;">${amBadge(d.status)}</td><td style="padding:6px 8px;text-align:center;">${act}</td></tr>`;}).join('')+'</tbody></table></div>';
}
let dispRows=[];
function dispCert(id){
  const d=dispRows.find(x=>x.id==id); if(!d) return;
  const n=v=>parseFloat(v||0).toLocaleString();
  amPrint('Disposal certificate '+d.disposal_no,`<h1>Asset Disposal Certificate</h1><div>${amEsc(d.disposal_no)} · approved ${amEsc(d.decided_at||'')}</div>
  <table><tr><th>Asset</th><td>${amEsc(d.asset_code)} - ${amEsc(d.asset_name)}</td><th>Serial no.</th><td>${amEsc(d.serial_number||'—')}</td></tr>
  <tr><th>Method</th><td>${amEsc(String(d.method).replace('_',' '))}</td><th>Disposal date</th><td>${amEsc(d.disposal_date)}</td></tr>
  <tr><th>Cost</th><td>${n(d.purchase_cost)}</td><th>Book value at approval</th><td>${n(d.book_value)}</td></tr>
  <tr><th>Proceeds</th><td>${n(d.proceeds)}</td><th>Gain / (loss)</th><td>${n(d.gain_loss)}</td></tr>
  <tr><th>Condition</th><td colspan="3">${amEsc(d.asset_condition||'—')}</td></tr>
  <tr><th>Reason</th><td colspan="3">${amEsc(d.reason||'')}</td></tr>
  <tr><th>Assessment</th><td colspan="3">${amEsc(d.assessment||'—')}</td></tr></table>
  <div class="sig"><div>Requested by: ${amEsc(d.requested_by_name||'')}</div><div>Approved by: ${amEsc(d.decided_by_name||'')}</div><div>Internal auditor</div></div>`);
}
async function dispDecide(id,decision){
  let note='';
  if(decision==='reject'){note=prompt('Reason for rejecting:','')||''; if(!note.trim()) return alert('A reason is required.');}
  else if(!confirm('Approve this disposal? The asset is removed from the register and the journal entry is posted.')) return; else note=prompt('Approval note (optional):','')||'';
  const j=await amPost('disposal_decide',{id,decision,note});
  alert(j.success?('✓ '+j.message):j.message); if(j.success) location.reload();
}
dispInit();
</script>
<?php elseif ($subModule == 'capitalization'): ?>
<!-- ====== CAPITALIZATION ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-hammer" style="color:#d97706;"></i> Asset Capitalization</h3>
  </div>
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">Capitalize expenses that increase the value or extend the life of an existing asset (e.g. renovations, upgrades, improvements done by supplier).</p>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Select Asset *</label>
      <select id="cap-asset" onchange="capCalc()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Select asset...</option></select>
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Supplier / Vendor</label>
      <input type="text" id="cap-supplier" placeholder="e.g. ABC Construction Ltd" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Amount Paid (Cost of Work) *</label>
      <input type="number" id="cap-amount" oninput="capCalc()" placeholder="0" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Paid from</label>
      <select id="cap-paid-from" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></select>
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Date</label>
      <input type="date" id="cap-date" value="<?php echo date('Y-m-d');?>" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div style="grid-column:1/-1;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Description of Work Done *</label>
      <textarea id="cap-desc" rows="2" placeholder="e.g. Roof repair, server room AC installation, building extension..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
    </div>
  </div>

  <!-- Calculation Preview -->
  <div id="cap-preview" style="display:none;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px;margin-bottom:16px;">
    <table style="width:100%;font-size:13px;">
      <tr><td style="padding:4px 0;">Current Asset Value:</td><td style="text-align:right;font-weight:600;" id="cp-current">0</td></tr>
      <tr><td style="padding:4px 0;">Capitalized Amount (work cost):</td><td style="text-align:right;font-weight:600;color:#d97706;" id="cp-amount">+ 0</td></tr>
      <tr style="border-top:2px solid #fde68a;"><td style="padding:8px 0;font-weight:700;">New Asset Value:</td><td style="padding:8px 0;text-align:right;font-weight:700;font-size:15px;color:#065f46;" id="cp-new">0</td></tr>
    </table>
  </div>

  <button onclick="capSubmit()" style="padding:10px 20px;background:#d97706;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-check"></i> Capitalize Expense</button>

  <!-- History -->
  <div style="margin-top:24px;border-top:1px solid var(--gray-200);padding-top:16px;">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:10px;">Capitalization History</h4>
    <div id="cap-history"><div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">Loading...</div></div>
  </div>
</div>

<script>
function aEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
let capAssets=[];

async function capInit(){
  asFillCash('cap-paid-from','Not paid yet (supplier payable)');
  const r=await fetch(_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets');
  const j=await r.json();
  if(j.success) capAssets=j.data.filter(a=>a.status==='active');
  const sel=document.getElementById('cap-asset');
  capAssets.forEach(a=>{sel.innerHTML+=`<option value="${a.id}">${aEsc(a.asset_code)} - ${aEsc(a.name)} (Value: ${parseFloat(a.current_value||0).toLocaleString()})</option>`;});
  capLoadHistory();
}

function capCalc(){
  const id=document.getElementById('cap-asset').value;
  const amount=parseFloat(document.getElementById('cap-amount').value)||0;
  if(!id||!amount){document.getElementById('cap-preview').style.display='none';return;}
  const asset=capAssets.find(a=>a.id==id);
  if(!asset) return;
  const current=parseFloat(asset.current_value||0);
  document.getElementById('cp-current').textContent=current.toLocaleString();
  document.getElementById('cp-amount').textContent='+ '+amount.toLocaleString();
  document.getElementById('cp-new').textContent=(current+amount).toLocaleString();
  document.getElementById('cap-preview').style.display='block';
}

async function capSubmit(){
  const id=document.getElementById('cap-asset').value;
  const amount=parseFloat(document.getElementById('cap-amount').value)||0;
  const desc=document.getElementById('cap-desc').value.trim();
  if(!id) return alert('Select an asset.');
  if(!amount) return alert('Enter amount paid.');
  if(!desc) return alert('Enter description of work done.');
  
  const asset=capAssets.find(a=>a.id==id);
  if(!confirm(`Capitalize ${amount.toLocaleString()} RWF to "${asset.name}"?\n\nWork: ${desc}\nCurrent value: ${parseFloat(asset.current_value).toLocaleString()}\nNew value: ${(parseFloat(asset.current_value)+amount).toLocaleString()}`)) return;
  
  try{
    const r=await fetch(_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets_capitalize',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      asset_id:parseInt(id),
      amount:amount,
      supplier:document.getElementById('cap-supplier').value,
      paid_from_account_id:parseInt(document.getElementById('cap-paid-from').value)||0,
      description:desc,
      date:document.getElementById('cap-date').value
    })});
    const j=await r.json();
    if(j.success){alert('✓ Expense capitalized! Asset value increased.');location.reload();}
    else alert(j.message);
  }catch(e){alert('Failed');}
}

async function capLoadHistory(){
  try{
    const r=await fetch(_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets_capitalizations');
    const j=await r.json();
    const div=document.getElementById('cap-history');
    if(j.success&&j.data&&j.data.length){
      div.innerHTML='<div style="border:1px solid var(--gray-200);border-radius:6px;overflow:hidden;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">Date</th><th style="padding:8px;text-align:left;">Asset</th><th style="padding:8px;text-align:left;">Description</th><th style="padding:8px;text-align:left;">Supplier</th><th style="padding:8px;text-align:right;">Amount</th></tr></thead><tbody>'+j.data.map(c=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 8px;">${c.capitalization_date||''}</td><td style="padding:6px 8px;font-weight:500;">${aEsc(c.asset_name||'')}</td><td style="padding:6px 8px;">${aEsc(c.description||'')}</td><td style="padding:6px 8px;font-size:11px;">${aEsc(c.supplier||'—')}</td><td style="padding:6px 8px;text-align:right;font-weight:600;color:#d97706;">${parseFloat(c.amount).toLocaleString()}</td></tr>`).join('')+'</tbody></table></div>';
    } else div.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">No capitalizations recorded.</div>';
  }catch(e){}
}

capInit();
</script>

<?php elseif ($subModule == 'revaluation'): ?>
<!-- ====== REVALUATION ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-arrow-up" style="color:#16a34a;"></i> Asset Revaluation</h3>
  </div>
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">Revalue assets to reflect current market value. Increases are credited to Revaluation Surplus (equity), decreases are expensed.</p>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Select Asset</label>
      <select id="rev-asset" onchange="revCalc()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Select asset...</option></select>
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">New Market Value</label>
      <input type="number" id="rev-value" oninput="revCalc()" placeholder="Enter new value" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Revaluation Date</label>
      <input type="date" id="rev-date" value="<?php echo date('Y-m-d');?>" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Reason</label>
      <input type="text" id="rev-reason" placeholder="e.g. Market appraisal" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
  </div>

  <!-- Calculation -->
  <div id="rev-calc" style="display:none;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:14px;margin-bottom:16px;">
    <table style="width:100%;font-size:13px;">
      <tr><td style="padding:4px 0;">Current Book Value:</td><td style="text-align:right;font-weight:600;" id="rc-bv">0</td></tr>
      <tr><td style="padding:4px 0;">New Market Value:</td><td style="text-align:right;font-weight:600;" id="rc-new">0</td></tr>
      <tr style="border-top:2px solid var(--gray-300);"><td style="padding:8px 0;font-weight:700;" id="rc-label">Revaluation Surplus:</td><td style="padding:8px 0;text-align:right;font-weight:700;font-size:15px;" id="rc-diff">0</td></tr>
    </table>
  </div>

  <button onclick="revSubmit()" style="padding:10px 20px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-check"></i> Apply Revaluation</button>

  <!-- History -->
  <div style="margin-top:24px;border-top:1px solid var(--gray-200);padding-top:16px;">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:10px;">Revaluation History</h4>
    <div id="rev-history"><div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">Loading...</div></div>
  </div>
</div>

<script>
function aEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
let revAssets=[];

async function revInit(){
  const r=await fetch(_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets');
  const j=await r.json();
  if(j.success) revAssets=j.data.filter(a=>a.status==='active');
  const sel=document.getElementById('rev-asset');
  revAssets.forEach(a=>{sel.innerHTML+=`<option value="${a.id}">${aEsc(a.asset_code)} - ${aEsc(a.name)} (BV: ${parseFloat(a.current_value||0).toLocaleString()})</option>`;});
  revLoadHistory();
}

function revCalc(){
  const id=document.getElementById('rev-asset').value;
  const newVal=parseFloat(document.getElementById('rev-value').value)||0;
  if(!id||!newVal){document.getElementById('rev-calc').style.display='none';return;}
  const asset=revAssets.find(a=>a.id==id);
  if(!asset) return;
  const bv=parseFloat(asset.current_value||0);
  const diff=newVal-bv;
  document.getElementById('rc-bv').textContent=bv.toLocaleString();
  document.getElementById('rc-new').textContent=newVal.toLocaleString();
  document.getElementById('rc-label').textContent=diff>=0?'Revaluation Surplus (Increase):':'Impairment Loss (Decrease):';
  document.getElementById('rc-diff').textContent=diff.toLocaleString();
  document.getElementById('rc-diff').style.color=diff>=0?'#065f46':'#991b1b';
  document.getElementById('rev-calc').style.display='block';
}

async function revSubmit(){
  const id=document.getElementById('rev-asset').value;
  const newVal=parseFloat(document.getElementById('rev-value').value)||0;
  if(!id||!newVal) return alert('Select asset and enter new value.');
  const asset=revAssets.find(a=>a.id==id);
  const diff=newVal-parseFloat(asset.current_value||0);
  if(!confirm(`Revalue ${asset.name}?\n\nCurrent: ${parseFloat(asset.current_value).toLocaleString()}\nNew: ${newVal.toLocaleString()}\n${diff>=0?'Surplus':'Impairment'}: ${diff.toLocaleString()}`)) return;
  
  try{
    const r=await fetch(_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets_revalue',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({asset_id:parseInt(id),new_value:newVal,date:document.getElementById('rev-date').value,reason:document.getElementById('rev-reason').value})});
    const j=await r.json();
    if(j.success){alert('✓ Asset revalued!');location.reload();}else alert(j.message);
  }catch(e){alert('Failed');}
}

async function revLoadHistory(){
  try{
    const r=await fetch(_asBase + '/api.php?endpoint=finance/chart-of-accounts&action=assets_revaluations');
    const j=await r.json();
    const div=document.getElementById('rev-history');
    if(j.success&&j.data&&j.data.length){
      div.innerHTML='<div style="border:1px solid var(--gray-200);border-radius:6px;overflow:hidden;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">Date</th><th style="padding:8px;text-align:left;">Asset</th><th style="padding:8px;text-align:right;">Old Value</th><th style="padding:8px;text-align:right;">New Value</th><th style="padding:8px;text-align:right;">Change</th><th style="padding:8px;text-align:left;">Reason</th></tr></thead><tbody>'+j.data.map(r=>{
        const change=parseFloat(r.new_value)-parseFloat(r.old_value);
        return `<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 8px;">${r.revaluation_date||''}</td><td style="padding:6px 8px;">${aEsc(r.asset_name||'')}</td><td style="padding:6px 8px;text-align:right;">${parseFloat(r.old_value).toLocaleString()}</td><td style="padding:6px 8px;text-align:right;font-weight:600;">${parseFloat(r.new_value).toLocaleString()}</td><td style="padding:6px 8px;text-align:right;font-weight:600;color:${change>=0?'#065f46':'#991b1b'};">${change>=0?'+':''}${change.toLocaleString()}</td><td style="padding:6px 8px;font-size:11px;">${aEsc(r.reason||'')}</td></tr>`;
      }).join('')+'</tbody></table></div>';
    } else div.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">No revaluations recorded.</div>';
  }catch(e){}
}

revInit();
</script>

<?php elseif ($subModule == 'transfers'): ?>
<!-- ====== TRANSFERS ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-exchange-alt" style="color:var(--primary-color);"></i> Asset Transfers</h3>
    <div style="display:flex;gap:8px;">
      <button id="tr-new" onclick="trNew()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> Request transfer</button>
      <button onclick="trLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <p style="font-size:12px;color:var(--gray-500);margin-bottom:16px;">Move an asset to another location or department. A transfer is approved by someone other than the requester, then completed when the asset has physically moved; only then does the register change. No journal entry is needed (the value does not change).</p>
  <div id="tr-list"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
let trMe={}, trAssets=[], trDepts=[], trEmps=[];
async function trInit(){
  const [me,aj,dj,ej]=await Promise.all([amGet('me'),amGet('assets'),amGet('departments'),amGet('employees')]);
  if(ej.success) trEmps=ej.data;
  if(me.success) trMe=me.data; if(aj.success) trAssets=aj.data; if(dj.success) trDepts=dj.data;
  trLoad();
}
function trNew(){
  const assets=trAssets.map(a=>`<option value="${a.id}">${amEsc(a.asset_code)} - ${amEsc(a.name)} (${amEsc(a.location||'no location')}${a.department_name?' / '+amEsc(a.department_name):''})</option>`).join('');
  const depts=trDepts.map(d=>`<option value="${d.id}">${amEsc(d.name)}</option>`).join('');
  amModal('tr-modal','Request asset transfer',
    amField('Asset *',`<select id="tr-asset" ${amInput}><option value="">Select asset...</option>${assets}</select>`)+
    amField('To location',`<input id="tr-loc" placeholder="e.g. Ward B, Room 4" ${amInput}>`)+
    amField('To department',`<select id="tr-dept" ${amInput}><option value="">Keep current department</option>${depts}</select>`)+
    amField('Transfer date *',`<input type="date" id="tr-date" value="<?php echo date('Y-m-d');?>" ${amInput}>`)+
    amField('Reason *',`<textarea id="tr-reason" rows="2" ${amInput}></textarea>`)+
    `<button id="tr-save" onclick="trSave()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Send request</button>`);
}
async function trSave(){
  const asset=document.getElementById('tr-asset').value; if(!asset) return alert('Select an asset.');
  const btn=document.getElementById('tr-save'); btn.disabled=true;
  const j=await amPost('transfer_request',{asset_id:parseInt(asset),to_location:document.getElementById('tr-loc').value,to_department_id:parseInt(document.getElementById('tr-dept').value)||0,transfer_date:document.getElementById('tr-date').value,reason:document.getElementById('tr-reason').value});
  btn.disabled=false;
  if(j.success){document.getElementById('tr-modal').remove();alert('✓ '+j.message+' ('+j.data.transfer_no+')');trLoad();}else alert(j.message);
}
async function trLoad(){
  const j=await amGet('transfers'); const div=document.getElementById('tr-list');
  if(!j.success){div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">'+amEsc(j.message||'Could not load')+'</div>';return;}
  if(!j.data.length){div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No transfers yet.</div>';return;}
  div.innerHTML='<div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">No.</th><th style="padding:8px;text-align:left;">Asset</th><th style="padding:8px;text-align:left;">From</th><th style="padding:8px;text-align:left;">To</th><th style="padding:8px;text-align:left;">Date</th><th style="padding:8px;text-align:left;">Requested by</th><th style="padding:8px;text-align:left;">Received by</th><th style="padding:8px;text-align:left;">Status</th><th style="padding:8px;text-align:center;">Action</th></tr></thead><tbody>'+j.data.map(t=>{
    const mine=t.requested_by==trMe.user_id; let act='';
    if(t.status==='pending'){
      if(trMe.can_decide_transfer&&!mine) act=`<button onclick="trDecide(${t.id},'approve')" style="padding:3px 8px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:3px;">Approve</button><button onclick="trDecide(${t.id},'reject')" style="padding:3px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;">Reject</button>`;
      else act='<span style="font-size:10px;color:var(--gray-400);">'+(mine?'awaiting another approver':'awaiting approval')+'</span>';
    } else if(t.status==='approved') act=`<button onclick="trComplete(${t.id})" style="padding:3px 8px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;">Receive</button>`;
    return `<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 8px;font-family:monospace;">${amEsc(t.transfer_no)}</td><td style="padding:6px 8px;">${amEsc(t.asset_code)} - ${amEsc(t.asset_name)}<div style="font-size:11px;color:var(--gray-500);">${amEsc(t.reason)}${t.decision_note?' · '+amEsc(t.decision_note):''}</div></td><td style="padding:6px 8px;">${amEsc(t.from_location||'—')}<div style="font-size:11px;color:var(--gray-500);">${amEsc(t.from_department||'')}</div></td><td style="padding:6px 8px;">${amEsc(t.to_location)}<div style="font-size:11px;color:var(--gray-500);">${amEsc(t.to_department||'')}</div></td><td style="padding:6px 8px;">${amEsc(t.transfer_date)}</td><td style="padding:6px 8px;">${amEsc(t.requested_by_name||'—')}</td><td style="padding:6px 8px;">${amEsc(t.received_by_name||'—')}</td><td style="padding:6px 8px;">${amBadge(t.status)}</td><td style="padding:6px 8px;text-align:center;">${act}</td></tr>`;}).join('')+'</tbody></table></div>';
}
async function trDecide(id,decision){
  let note='';
  if(decision==='reject'){note=prompt('Reason for rejecting:','')||''; if(!note.trim()) return alert('A reason is required.');}
  const j=await amPost('transfer_decide',{id,decision,note}); alert(j.success?('✓ '+j.message):j.message); trLoad();
}
function trComplete(id){
  amModal('trc-modal','Receive asset',
    '<p style="font-size:12px;color:var(--gray-500);margin-bottom:12px;">The receiving department acknowledges it has taken possession. The register changes only now.</p>'+
    amField('Receiving officer *',`<select id="trc-emp" ${amInput}><option value="">Select staff member...</option>${trEmps.map(e=>`<option value="${e.id}">${amEsc(e.name)} (${amEsc(e.employee_no)})</option>`).join('')}</select>`)+
    `<button id="trc-save" onclick="trReceive(${id})" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Confirm receipt</button>`);
}
async function trReceive(id){
  const emp=parseInt(document.getElementById('trc-emp').value); if(!emp) return alert('Select the receiving officer.');
  const btn=document.getElementById('trc-save'); btn.disabled=true;
  const j=await amPost('transfer_complete',{id,received_by_employee_id:emp}); btn.disabled=false; alert(j.success?('✓ '+j.message):j.message);
  if(j.success){document.getElementById('trc-modal').remove();const aj=await amGet('assets'); if(aj.success) trAssets=aj.data;} trLoad();
}
trInit();
</script>

<?php elseif ($subModule == 'assignments'): ?>
<!-- ====== ASSIGNMENTS (custody) ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-user-tag" style="color:var(--primary-color);"></i> Asset Assignments</h3>
    <div style="display:flex;gap:8px;">
      <button id="as-assign" onclick="asgNew()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> Assign asset</button>
      <button onclick="asgLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <p style="font-size:12px;color:var(--gray-500);margin-bottom:16px;">Who holds which asset. An asset can only be with one person at a time; record its return, or report it lost or damaged.</p>
  <div id="asg-list"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
let asgAssets=[], asgEmps=[], asgRows=[];
async function asgInit(){
  const [aj,ej]=await Promise.all([amGet('assets'),amGet('employees')]);
  if(aj.success) asgAssets=aj.data; if(ej.success) asgEmps=ej.data;
  asgLoad();
}
function asgNew(){
  const free=asgAssets.filter(a=>!a.custodian_id&&a.status!=='disposed');
  amModal('asg-modal','Assign asset',
    amField('Asset *',`<select id="asg-asset" ${amInput}><option value="">Select asset...</option>${free.map(a=>`<option value="${a.id}">${amEsc(a.asset_code)} - ${amEsc(a.name)}</option>`).join('')}</select>`)+
    amField('Assign to *',`<select id="asg-emp" ${amInput}><option value="">Select staff member...</option>${asgEmps.map(e=>`<option value="${e.id}">${amEsc(e.name)} (${amEsc(e.employee_no)})</option>`).join('')}</select>`)+
    amField('Assigned date *',`<input type="date" id="asg-date" max="<?php echo date('Y-m-d');?>" value="<?php echo date('Y-m-d');?>" ${amInput}>`)+
    amField('Expected return',`<input type="date" id="asg-due" ${amInput}>`)+
    amField('Condition at handover',`<input id="asg-cond" value="Good working condition" ${amInput}>`)+
    amField('Notes',`<input id="asg-notes" ${amInput}>`)+
    `<button id="asg-save" onclick="asgSave()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Assign</button>`);
}
async function asgSave(){
  const asset=document.getElementById('asg-asset').value, emp=document.getElementById('asg-emp').value;
  if(!asset||!emp) return alert('Select the asset and the staff member.');
  const btn=document.getElementById('asg-save'); btn.disabled=true;
  const j=await amPost('assign',{asset_id:parseInt(asset),employee_id:parseInt(emp),assigned_date:document.getElementById('asg-date').value,expected_return_date:document.getElementById('asg-due').value,condition_out:document.getElementById('asg-cond').value,notes:document.getElementById('asg-notes').value});
  btn.disabled=false;
  if(j.success){document.getElementById('asg-modal').remove();alert('✓ '+j.message+' ('+j.data.assignment_no+')');asgInit();}else alert(j.message);
}
async function asgLoad(){
  const j=await amGet('assignments'); const div=document.getElementById('asg-list');
  if(!j.success){div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">'+amEsc(j.message||'Could not load')+'</div>';return;}
  asgRows=j.data;
  if(!j.data.length){div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No assignments yet.</div>';return;}
  div.innerHTML='<div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">No.</th><th style="padding:8px;text-align:left;">Asset</th><th style="padding:8px;text-align:left;">Held by</th><th style="padding:8px;text-align:left;">Assigned</th><th style="padding:8px;text-align:left;">Due back</th><th style="padding:8px;text-align:left;">Returned</th><th style="padding:8px;text-align:left;">Condition</th><th style="padding:8px;text-align:left;">Status</th><th style="padding:8px;text-align:center;">Action</th></tr></thead><tbody>'+j.data.map(s=>{
    const form=`<button onclick="asgForm(${s.id},'${s.status==='assigned'?'out':'in'}')" style="padding:3px 8px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:4px;font-size:10px;cursor:pointer;margin-right:3px;"><i class="fas fa-print"></i> Form</button>`;
    const act=form+(s.status==='assigned'?`<button onclick="asgClose(${s.id},false)" style="padding:3px 8px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:3px;">Return</button><button onclick="asgClose(${s.id},true)" style="padding:3px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;">Lost / damaged</button>`:'');
    return `<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 8px;font-family:monospace;">${amEsc(s.assignment_no)}</td><td style="padding:6px 8px;">${amEsc(s.asset_code)} - ${amEsc(s.asset_name)}</td><td style="padding:6px 8px;">${amEsc(s.employee_name)}</td><td style="padding:6px 8px;">${amEsc(s.assigned_date)}</td><td style="padding:6px 8px;${s.overdue==1?'color:#dc2626;font-weight:600;':''}">${amEsc(s.expected_return_date||'—')}${s.overdue==1?' (overdue)':''}</td><td style="padding:6px 8px;">${amEsc(s.returned_date||'—')}</td><td style="padding:6px 8px;font-size:11px;">${amEsc(s.condition_in||s.condition_out||'')}</td><td style="padding:6px 8px;">${amBadge(s.status)}</td><td style="padding:6px 8px;text-align:center;">${act}</td></tr>`;}).join('')+'</tbody></table></div>';
}
function asgForm(id,kind){
  const s=asgRows.find(x=>x.id==id); if(!s) return;
  const out=kind==='out';
  amPrint('Handover '+s.assignment_no,`<h1>Asset ${out?'Handover':'Return'} Form</h1><div>${amEsc(s.assignment_no)}</div>
  <table><tr><th>Asset</th><td>${amEsc(s.asset_code)} - ${amEsc(s.asset_name)}</td><th>Serial no.</th><td>${amEsc(s.serial_number||'—')}</td></tr>
  <tr><th>${out?'Issued to':'Returned by'}</th><td>${amEsc(s.employee_name)} (${amEsc(s.employee_no)})</td><th>Department</th><td>${amEsc(s.employee_department||'—')}</td></tr>
  <tr><th>Assigned</th><td>${amEsc(s.assigned_date)}</td><th>${out?'Expected return':'Returned'}</th><td>${amEsc(out?(s.expected_return_date||'—'):(s.returned_date||'—'))}</td></tr>
  <tr><th>Condition ${out?'at handover':'on return'}</th><td colspan="3">${amEsc((out?s.condition_out:s.condition_in)||'—')}</td></tr></table>
  <p style="font-size:12px">${out?'I acknowledge receipt of the asset above in the condition stated and accept responsibility for it until it is returned.':'The asset above has been returned in the condition stated.'}</p>
  <div class="sig"><div>${out?'Issued by (Asset Officer)':'Received by (Asset Officer)'}</div><div>${out?'Received by (acknowledgement)':'Returned by'}: ${amEsc(s.employee_name)}</div></div>`);
}
async function asgClose(id,lost){
  const cond=prompt(lost?'What happened to the asset?':'Condition on return:',lost?'':'Good condition'); if(!cond||!cond.trim()) return;
  const j=await amPost(lost?'assignment_lost':'assignment_return',{id,condition_in:cond}); alert(j.success?('✓ '+j.message):j.message);
  asgInit();
}
asgInit();
</script>

<?php elseif (in_array($subModule, ['qr','clearance','movements','m_schedule','m_faults','m_orders','m_verify','m_history','mon_status','mon_alerts','mon_critical'], true)): ?>
<!-- ====== QR / CLEARANCE / MOVEMENTS / MAINTENANCE / MONITORING (one script, the tab picks its loader) ====== -->
<script src="assets/js/qrcode.min.js"></script>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:10px;">
    <h3 id="nt-title" style="font-size:16px;font-weight:600;"></h3>
    <div id="nt-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;"></div>
  </div>
  <p id="nt-help" style="font-size:12px;color:var(--gray-500);margin-bottom:16px;"></p>
  <div id="nt-cards"></div>
  <div id="nt-body"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
const NT_TAB='<?php echo $subModule;?>';
const ntToday='<?php echo date('Y-m-d');?>';
let ntAssets=[], ntEmps=[], ntRows=[], ntMe={};
const ntBtnCss='padding:3px 8px;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:3px;color:white;';
const ntMainBtn='padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;';
function ntBtn(label,fn,bg){return `<button onclick="${fn}" style="${ntBtnCss}background:${bg||'var(--primary-color)'};">${label}</button>`;}
function ntPill(text,bg,fg){return `<span style="padding:2px 8px;border-radius:10px;font-size:10px;background:${bg};color:${fg};white-space:nowrap;">${amEsc(text)}</span>`;}
const NT_PRIO={low:['#f3f4f6','#374151'],medium:['#dbeafe','#1e40af'],high:['#fef3c7','#92400e'],critical:['#fee2e2','#991b1b']};
const NT_EQ={operational:['#d1fae5','#065f46','Operational'],faulty:['#fee2e2','#991b1b','Faulty'],under_maintenance:['#fef3c7','#92400e','Under maintenance'],out_of_service:['#e5e7eb','#374151','Out of service'],awaiting_inspection:['#ede9fe','#5b21b6','Awaiting inspection']};
function ntPrio(p){const c=NT_PRIO[p]||NT_PRIO.medium;return ntPill(p,c[0],c[1]);}
function ntEq(s){const c=NT_EQ[s]||['#f3f4f6','#374151',s];return ntPill(c[2],c[0],c[1]);}
function ntCrit(a){return a==1?' <span title="Critical equipment" style="color:#dc2626;"><i class="fas fa-life-ring"></i></span>':'';}
function ntTable(heads,rows,empty){
  if(!rows.length) return `<div style="padding:30px;text-align:center;color:var(--gray-400);">${empty}</div>`;
  return '<div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);">'+heads.map(h=>`<th style="padding:8px;text-align:left;white-space:nowrap;">${h}</th>`).join('')+'</tr></thead><tbody>'+rows.map(r=>'<tr style="border-bottom:1px solid var(--gray-100);">'+r.map(c=>`<td style="padding:6px 8px;vertical-align:top;">${c}</td>`).join('')+'</tr>').join('')+'</tbody></table></div>';
}
function ntSub(t){return `<div style="font-size:11px;color:var(--gray-500);">${t}</div>`;}
function ntSet(title,icon,help,actions){
  document.getElementById('nt-title').innerHTML=`<i class="fas ${icon}" style="color:var(--primary-color);"></i> ${title}`;
  document.getElementById('nt-help').textContent=help; document.getElementById('nt-actions').innerHTML=actions||'';
}
function ntBody(h){document.getElementById('nt-body').innerHTML=h;}
function ntCards(items){document.getElementById('nt-cards').innerHTML='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">'+items.map(([l,v,c])=>`<div style="flex:1 1 130px;border:1px solid var(--gray-200);border-radius:8px;padding:10px 14px;"><div style="font-size:11px;color:var(--gray-500);">${l}</div><div style="font-size:20px;font-weight:700;color:${c||'inherit'};">${v}</div></div>`).join('')+'</div>';}
function ntAssetOpts(list){return list.map(a=>`<option value="${a.id}">${amEsc(a.asset_code)} - ${amEsc(a.name)}${a.location?' ('+amEsc(a.location)+')':''}</option>`).join('');}
function ntEmpOpts(){return ntEmps.map(e=>`<option value="${e.id}">${amEsc(e.name)} (${amEsc(e.employee_no)})</option>`).join('');}
const ntSave=(id,label)=>`<button id="${id}" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">${label}</button>`;
async function ntSend(action,body,btnId,modalId,reload){
  const btn=btnId&&document.getElementById(btnId); if(btn) btn.disabled=true;
  const j=await amPost(action,body); if(btn) btn.disabled=false;
  alert(j.success?('✓ '+j.message+(j.data&&(j.data.request_no||j.data.wo_no)?' ('+(j.data.request_no||j.data.wo_no)+')':'')):j.message);
  if(j.success){ if(modalId) document.getElementById(modalId)?.remove(); (reload||ntLoad)(); }
  return j;
}
async function ntInit(){
  const need=['assets']; if(['m_schedule','m_faults','m_orders'].includes(NT_TAB)) need.push('employees');
  const res=await Promise.all(need.map(amGet)); if(res[0].success) ntAssets=res[0].data; if(res[1]&&res[1].success) ntEmps=res[1].data;
  ntLoad();
}
function ntLoad(){({qr:ntQr,clearance:ntClearance,movements:ntMovements,m_schedule:ntSchedule,m_faults:ntFaults,m_orders:()=>ntOrders('active'),m_verify:()=>ntOrders('verify'),m_history:()=>ntOrders('history'),mon_status:ntStatus,mon_alerts:ntAlerts,mon_critical:ntCritical})[NT_TAB]();}
async function ntFetch(action,err){const j=await amGet(action); if(!j.success){ntBody(`<div style="padding:30px;text-align:center;color:var(--gray-400);">${amEsc(j.message||'Could not load')}</div>`);return null;} return j.data;}

// ---- QR labels and physical verification
async function ntQr(){
  const fresh=await amGet('assets'); if(fresh.success) ntAssets=fresh.data;
  ntSet('QR Labels & Verification','fa-qrcode','Stick the label on the asset. During a stock check, scan it (or type the code) to confirm the asset is there and where the register says.',
    `<input id="qr-code" placeholder="Scan or type asset code" onkeydown="if(event.key==='Enter')ntQrFind()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><button onclick="ntQrFind()" style="${ntMainBtn}"><i class="fas fa-check"></i> Verify</button><button onclick="ntLabels()" style="${ntMainBtn}background:var(--gray-700,#374151);"><i class="fas fa-print"></i> Print all labels</button>`);
  const v=await amGet('verifications'); const ver=v.success?v.data:[];
  const stale=ntAssets.filter(a=>!a.last_verified||a.last_verified<ntToday.slice(0,4)+'-01-01').length;
  ntCards([['Assets',ntAssets.length],['Not verified this year',stale,stale?'#b45309':'#065f46'],['Mismatches found',ver.filter(x=>x.matches_register==0).length,ver.some(x=>x.matches_register==0)?'#dc2626':'inherit']]);
  ntBody(ntTable(['Code','Asset','Register location','Last verified','Action'],ntAssets.map(a=>[`<span style="font-family:monospace;">${amEsc(a.asset_code)}</span>`,amEsc(a.name),amEsc(a.location||'—'),a.last_verified?amEsc(a.last_verified.slice(0,10)):'<span style="color:#b45309;">never</span>',ntBtn('Verify',`ntVerify(${a.id})`)+ntBtn('Label',`ntLabels(${a.id})`,'#6b7280')]),'No assets.')
   +'<h4 style="font-size:14px;font-weight:600;margin:24px 0 10px;">Verification results</h4>'
   +ntTable(['When','Asset','Found at','Register says','Condition','By','Result'],ver.map(x=>[amEsc(x.verified_at.slice(0,16)),amEsc(x.asset_code+' - '+x.asset_name),amEsc(x.location_found||'—'),amEsc(x.register_location||'—'),amEsc(x.condition_found||'—'),amEsc(x.verified_by_name||'—'),x.matches_register==1?ntPill('matches','#d1fae5','#065f46'):ntPill('location differs','#fee2e2','#991b1b')]),'No physical checks recorded yet.'));
}
function ntQrFind(){
  const c=document.getElementById('qr-code').value.trim().toLowerCase(); if(!c) return;
  const a=ntAssets.find(x=>String(x.asset_code).toLowerCase()===c); if(!a) return alert('No active asset has the code "'+c+'".');
  document.getElementById('qr-code').value=''; ntVerify(a.id);
}
function ntVerify(id){
  const a=ntAssets.find(x=>x.id==id);
  amModal('qv-modal','Verify '+a.asset_code,`<p style="font-size:13px;margin-bottom:12px;"><b>${amEsc(a.name)}</b><br><span style="color:var(--gray-500);">Register location: ${amEsc(a.location||'none')}</span></p>`+
    amField('Where is it now?',`<input id="qv-loc" value="${amEsc(a.location||'')}" ${amInput}>`)+amField('Condition',`<input id="qv-cond" value="Good" ${amInput}>`)+ntSave('qv-save','Confirm found'));
  document.getElementById('qv-save').onclick=async()=>{const j=await ntSend('verify_asset',{asset_id:a.id,location_found:document.getElementById('qv-loc').value,condition_found:document.getElementById('qv-cond').value},'qv-save','qv-modal',ntQr);};
}
function ntLabels(onlyId){
  const list=onlyId?ntAssets.filter(a=>a.id==onlyId):ntAssets; const holder=document.createElement('div');
  const labels=list.map(a=>{holder.innerHTML=''; new QRCode(holder,{text:a.asset_code,width:96,height:96}); const src=holder.querySelector('canvas').toDataURL();
    return `<div class="lbl"><img src="${src}" width="96" height="96"><br><b>${amEsc(a.asset_code)}</b><br>${amEsc(a.name)}</div>`;}).join('');
  amPrint('Asset labels',`<h1>Asset QR labels</h1>${labels}`);
}

// ---- staff exit clearance
async function ntClearance(){
  ntSet('Staff Exit Clearance','fa-user-check','Staff who still hold hospital assets. Check this before someone changes department or leaves; people who have left are listed first.','');
  const d=await ntFetch('clearance'); if(!d) return;
  ntCards([['Staff holding assets',d.length],['Left but still holding',d.filter(x=>x.employee_status!=='active').length,d.some(x=>x.employee_status!=='active')?'#dc2626':'#065f46'],['Overdue items',d.reduce((n,x)=>n+Number(x.overdue),0),'#b45309']]);
  ntBody(ntTable(['Staff','Department','Status','Assets held','Overdue',''],d.map(x=>[amEsc(x.name)+ntSub(amEsc(x.employee_no)),amEsc(x.department||'—'),x.employee_status==='active'?ntPill('active','#d1fae5','#065f46'):ntPill(x.employee_status,'#fee2e2','#991b1b'),`${x.held}<div style="font-size:11px;color:var(--gray-500);">${amEsc(x.assets)}</div>`,x.overdue>0?`<span style="color:#dc2626;font-weight:600;">${x.overdue}</span>`:'0',ntBtn('Open assignments',"location.href='assets.php?module=assets&sub=assignments'",'#6b7280')]),'Nobody is holding an asset.'));
}

// ---- movement history
async function ntMovements(){
  ntSet('Movement History','fa-route','Every completed transfer: where the asset came from and went to, and who requested, approved and received it.','');
  const d=await ntFetch('movements'); if(!d) return;
  ntBody(ntTable(['Moved','Asset','From','To','Requested by','Approved by','Received by','No.'],d.map(t=>[amEsc((t.completed_at||t.transfer_date).slice(0,10)),amEsc(t.asset_code+' - '+t.asset_name)+ntSub(amEsc(t.reason||'')),amEsc(t.from_location||'—')+ntSub(amEsc(t.from_department||'')),amEsc(t.to_location)+ntSub(amEsc(t.to_department||'')),amEsc(t.requested_by_name||'—'),amEsc(t.decided_by_name||'—'),amEsc(t.received_by_name||'—'),`<span style="font-family:monospace;">${amEsc(t.transfer_no)}</span>`]),'No completed transfers yet.'));
}

// ---- preventive schedule
async function ntSchedule(){
  ntSet('Maintenance Schedule','fa-calendar-alt','Planned servicing by time, usage or the manufacturer\'s recommendation. Create a work order when a job is due; a repeat interval books the next one once the work is verified.',`<button onclick="ntSchedNew()" style="${ntMainBtn}"><i class="fas fa-plus"></i> Schedule maintenance</button>`);
  const d=await ntFetch('maintenance_schedule'); if(!d) return; ntRows=d;
  ntCards([['Scheduled',d.length],['Overdue',d.filter(x=>x.overdue==1).length,d.some(x=>x.overdue==1)?'#dc2626':'#065f46'],['Due in 14 days',d.filter(x=>x.overdue!=1&&x.due_date<=new Date(Date.now()+14*864e5).toISOString().slice(0,10)).length,'#b45309']]);
  ntBody(ntTable(['Asset','Type','Basis','Due','Repeats','Work order',''],d.map(m=>[amEsc(m.asset_code+' - '+m.asset_name)+ntCrit(m.is_critical)+(m.notes?ntSub(amEsc(m.notes)):''),amEsc(m.kind),amEsc(m.basis),m.overdue==1?`<span style="color:#dc2626;font-weight:600;">${amEsc(m.due_date)} (overdue)</span>`:amEsc(m.due_date),m.interval_days?'every '+m.interval_days+' days':'once',m.open_wo?amEsc(m.open_wo):'—',m.open_wo?'':ntBtn('Create work order',`ntWoNew(null,${m.id})`)+ntBtn('Cancel',`ntSchedCancel(${m.id})`,'#dc2626')]),'Nothing is scheduled.'));
}
function ntSchedNew(){
  amModal('ns-modal','Schedule maintenance',amField('Asset *',`<select id="ns-asset" ${amInput}><option value="">Select asset...</option>${ntAssetOpts(ntAssets)}</select>`)+
    amField('Type',`<select id="ns-kind" ${amInput}><option value="preventive">Preventive maintenance</option><option value="inspection">Inspection</option></select>`)+
    amField('Based on',`<select id="ns-basis" ${amInput}><option value="time">Time</option><option value="usage">Usage (hours / cycles)</option><option value="manufacturer">Manufacturer recommendation</option></select>`)+
    amField('Due date *',`<input type="date" id="ns-due" value="${ntToday}" ${amInput}>`)+amField('Repeat every (days)',`<input type="number" min="1" id="ns-int" placeholder="blank = one time" ${amInput}>`)+amField('Notes',`<input id="ns-notes" ${amInput}>`)+ntSave('ns-save','Schedule'));
  document.getElementById('ns-save').onclick=()=>{const a=document.getElementById('ns-asset').value; if(!a) return alert('Select an asset.');
    ntSend('schedule_add',{asset_id:+a,kind:ns_v('kind'),basis:ns_v('basis'),due_date:ns_v('due'),interval_days:+ns_v('int')||0,notes:ns_v('notes')},'ns-save','ns-modal');};
}
const ns_v=k=>document.getElementById('ns-'+k).value;
function ntSchedCancel(id){if(confirm('Cancel this scheduled maintenance?')) ntSend('schedule_cancel',{id});}

// ---- fault requests
async function ntFaults(){
  ntSet('Fault Requests','fa-exclamation-triangle','Report a fault, breakdown, defect or damage. A fault on critical equipment is always top priority. Open faults become work orders.',`<button onclick="ntFaultNew()" style="${ntMainBtn}"><i class="fas fa-plus"></i> Report fault</button>`);
  const d=await ntFetch('faults'); if(!d) return;
  const open=d.filter(f=>['open','assigned'].includes(f.status));
  ntCards([['Unresolved',open.length,open.length?'#b45309':'#065f46'],['No work order yet',d.filter(f=>f.status==='open').length],['On critical equipment',open.filter(f=>f.is_critical==1).length,open.some(f=>f.is_critical==1)?'#dc2626':'inherit']]);
  ntBody(ntTable(['No.','Asset','Fault','Priority','Reported','Status','Work order',''],d.map(f=>[`<span style="font-family:monospace;">${amEsc(f.request_no)}</span>`,amEsc(f.asset_code+' - '+f.asset_name)+ntCrit(f.is_critical)+ntSub(amEsc(f.location||'')),amEsc(f.description),ntPrio(f.priority),amEsc(f.reported_at.slice(0,10))+ntSub(amEsc(f.reported_by_name||'')+(['open','assigned'].includes(f.status)?' · '+f.age_days+' days open':'')),amBadge(f.status),amEsc(f.wo_no||'—'),f.status==='open'?ntBtn('Create work order',`ntWoNew(${f.id},null)`)+ntBtn('Close',`ntFaultClose(${f.id})`,'#6b7280'):'']),'No faults reported.'));
}
function ntFaultNew(){
  amModal('nf-modal','Report a fault',amField('Asset *',`<select id="nf-asset" ${amInput}><option value="">Select asset...</option>${ntAssetOpts(ntAssets)}</select>`)+
    amField('Priority',`<select id="nf-prio" ${amInput}><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option><option value="critical">Critical</option></select>`)+
    amField('What is wrong? *',`<textarea id="nf-desc" rows="3" ${amInput}></textarea>`)+ntSave('nf-save','Submit'));
  document.getElementById('nf-save').onclick=()=>{const a=document.getElementById('nf-asset').value; if(!a) return alert('Select an asset.');
    ntSend('fault_report',{asset_id:+a,priority:document.getElementById('nf-prio').value,description:document.getElementById('nf-desc').value},'nf-save','nf-modal');};
}
function ntFaultClose(id){const n=prompt('Reason for closing without repair:',''); if(n&&n.trim()) ntSend('fault_close',{id,note:n});}

// ---- work orders
function ntWoNew(faultId,maintId){
  amModal('nw-modal','Create work order',(faultId||maintId?'':amField('Asset *',`<select id="nw-asset" ${amInput}><option value="">Select asset...</option>${ntAssetOpts(ntAssets)}</select>`)+amField('Type',`<select id="nw-kind" ${amInput}><option value="repair">Repair</option><option value="inspection">Inspection</option><option value="preventive">Preventive</option></select>`)+amField('Priority',`<select id="nw-prio" ${amInput}><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option><option value="critical">Critical</option></select>`))+
    amField('Internal technician',`<select id="nw-emp" ${amInput}><option value="">None</option>${ntEmpOpts()}</select>`)+amField('or external service provider',`<input id="nw-ext" placeholder="Company name" ${amInput}>`)+ntSave('nw-save','Create work order'));
  document.getElementById('nw-save').onclick=()=>{const g=k=>document.getElementById('nw-'+k)?.value;
    if(!faultId&&!maintId&&!g('asset')) return alert('Select an asset.');
    ntSend('wo_create',{fault_request_id:faultId||0,maintenance_id:maintId||0,asset_id:+g('asset')||0,kind:g('kind'),priority:g('prio'),assigned_employee_id:+g('emp')||0,external_provider:g('ext')},'nw-save','nw-modal',()=>ntLoad());};
}
async function ntOrders(view){
  const T={active:['Work Orders','fa-clipboard-check','Open jobs. A technician starts the job, then records findings, work done, parts, cost and downtime. Finished work goes to Pending Verification.',`<button onclick="ntWoNew(null,null)" style="${ntMainBtn}"><i class="fas fa-plus"></i> New work order</button>`],
           verify:['Pending Verification','fa-user-shield','Repaired equipment returns to use only after someone other than the technician checks and signs it off. The cost is booked when the work is accepted.',''],
           history:['Maintenance History','fa-history','Completed and cancelled work orders, with cost and downtime.','']}[view];
  ntSet(...[T[0],T[1],T[2],T[3]]);
  if(view==='active'||view==='verify'){const me=await amGet('me'); if(me.success) ntMe=me.data;}
  const d=await ntFetch('work_orders&view='+view); if(!d) return; ntRows=d;
  if(view==='history') ntCards([['Jobs',d.length],['Total cost',d.reduce((n,w)=>n+Number(w.cost),0).toLocaleString()],['Downtime (hours)',d.reduce((n,w)=>n+Number(w.downtime_hours),0).toLocaleString()]]); else document.getElementById('nt-cards').innerHTML='';
  const who=w=>amEsc(w.technician_name||w.external_provider||'—');
  const rec=w=>`${w.findings?`<div><b>Findings:</b> ${amEsc(w.findings)}</div>`:''}${w.work_done?`<div><b>Work:</b> ${amEsc(w.work_done)}</div>`:''}${w.parts_used?`<div><b>Parts:</b> ${amEsc(w.parts_used)}</div>`:''}${w.verify_note?`<div><b>Note:</b> ${amEsc(w.verify_note)}</div>`:''}`;
  if(view==='active') ntBody(ntTable(['No.','Asset','Type','Priority','Assigned to','Status','Source',''],d.map(w=>[`<span style="font-family:monospace;">${amEsc(w.wo_no)}</span>`,amEsc(w.asset_code+' - '+w.asset_name)+ntCrit(w.is_critical),amEsc(w.kind),ntPrio(w.priority),who(w),amBadge(w.status),amEsc(w.fault_no||'schedule / manual')+(w.fault_description?ntSub(amEsc(w.fault_description.slice(0,80))):'')+(w.verify_note?ntSub('Sent back: '+amEsc(w.verify_note)):''),(w.status==='open'?ntBtn('Start',`ntWoStart(${w.id})`):'')+ntBtn('Record work',`ntWoRecord(${w.id})`,'#16a34a')+ntBtn('Cancel',`ntWoCancel(${w.id})`,'#dc2626')]),'No open work orders.'));
  else if(view==='verify') ntBody(ntTable(['No.','Asset','Work recorded','Cost','Downtime','Done by','',],d.map(w=>[`<span style="font-family:monospace;">${amEsc(w.wo_no)}</span>`,amEsc(w.asset_code+' - '+w.asset_name)+ntCrit(w.is_critical),rec(w),Number(w.cost).toLocaleString(),w.downtime_hours+' h',amEsc(w.work_done_by_name||'—'),ntMe.can_verify_work?ntBtn('Approve',`ntWoVerify(${w.id},'approve')`,'#16a34a')+ntBtn('Send back',`ntWoVerify(${w.id},'reject')`,'#dc2626'):'<span style="font-size:11px;color:var(--gray-500);">Verified by the MD or maintenance officer</span>']),'Nothing is waiting for verification.'));
  else ntBody(ntTable(['No.','Asset','Type','Work','Cost','Downtime','Status','Verified'],d.map(w=>[`<span style="font-family:monospace;">${amEsc(w.wo_no)}</span>`,amEsc(w.asset_code+' - '+w.asset_name),amEsc(w.kind),rec(w)||'—',Number(w.cost).toLocaleString(),w.downtime_hours+' h',amBadge(w.status),w.verified_at?amEsc(w.verified_at.slice(0,10))+ntSub(amEsc(w.verified_by_name||'')):'—']),'No finished work orders yet.'));
}
async function ntWoStart(id){ntSend('wo_start',{id});}
function ntWoCancel(id){const n=prompt('Reason for cancelling:',''); if(n&&n.trim()) ntSend('wo_cancel',{id,note:n});}
function ntWoVerify(id,decision){
  let note='';
  if(decision==='reject'){note=prompt('What is still wrong?','')||''; if(!note.trim()) return;} else if(!confirm('Approve? The equipment goes back into use and any cost is posted to Finance.')) return; else note=prompt('Verification note (optional):','')||'';
  ntSend('wo_verify',{id,decision,note});
}
function ntWoRecord(id){
  const w=ntRows.find(x=>x.id==id);
  amModal('nr-modal','Record work '+w.wo_no,amField('Inspection findings *',`<textarea id="nr-find" rows="2" ${amInput}></textarea>`)+amField('Work performed *',`<textarea id="nr-done" rows="2" ${amInput}></textarea>`)+amField('Spare parts used',`<input id="nr-parts" ${amInput}>`)+
    `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">`+amField('Cost',`<input type="number" min="0" step="0.01" id="nr-cost" value="0" ${amInput}>`)+amField('Downtime (hours)',`<input type="number" min="0" step="0.5" id="nr-down" value="0" ${amInput}>`)+`</div>`+
    amField('Paid from (needed when there is a cost)',`<select id="nr-pay" ${amInput}></select>`)+ntSave('nr-save','Send for verification'));
  amGet('cash_accounts').then(j=>{document.getElementById('nr-pay').innerHTML='<option value="">Select account...</option>'+(j.data||[]).map(c=>`<option value="${c.id}">${amEsc(c.account_name)}</option>`).join('');});
  document.getElementById('nr-save').onclick=()=>{const g=k=>document.getElementById('nr-'+k).value;
    ntSend('wo_record',{id,findings:g('find'),work_done:g('done'),parts_used:g('parts'),cost:+g('cost')||0,downtime_hours:+g('down')||0,pay_account_id:+g('pay')||0},'nr-save','nr-modal');};
}

// ---- monitoring
async function ntStatus(){
  ntSet('Equipment Status','fa-heartbeat','The state of every asset: Operational, Faulty, Under maintenance, Awaiting inspection or Out of service. Faults and work orders move the status automatically.','');
  const d=await ntFetch('equipment'); if(!d) return; ntRows=d;
  const cnt=s=>d.filter(x=>x.equipment_status===s).length;
  ntCards(Object.keys(NT_EQ).map(s=>[NT_EQ[s][2],cnt(s),s==='operational'?'#065f46':(cnt(s)?NT_EQ[s][1]:'inherit')]));
  ntBody(ntEquipTable(d));
}
function ntEquipTable(d){
  return ntTable(['Asset','Location','Status','Open faults','Next maintenance','Warranty','Critical',''],d.map(a=>[amEsc(a.asset_code+' - '+a.name)+ntSub(amEsc(a.department_name||'')),amEsc(a.location||'—'),ntEq(a.equipment_status),a.open_faults>0?`<b style="color:#dc2626;">${a.open_faults}</b>`:'0',amEsc(a.next_due||'—'),a.warranty_expiry?amEsc(a.warranty_expiry):'—',`<input type="checkbox" ${a.is_critical==1?'checked':''} onchange="ntCritSet(${a.id},this.checked)" title="Life support, imaging, laboratory">`,
    a.equipment_status==='out_of_service'?ntBtn('Back in service',`ntEqSet(${a.id},'operational')`,'#16a34a'):ntBtn('Out of service',`ntEqSet(${a.id},'out_of_service')`,'#6b7280')]),'No assets.');
}
async function ntEqSet(id,st){if(st==='out_of_service'&&!confirm('Mark this equipment out of service?')) return; ntSend('equipment_set',{asset_id:id,equipment_status:st});}
async function ntCritSet(id,on){const j=await amPost('equipment_set',{asset_id:id,is_critical:on?1:0}); if(!j.success){alert(j.message);} ntLoad();}
async function ntAlerts(){
  ntSet('Alerts','fa-bell','Upcoming and overdue maintenance and inspections, expiring warranties and unresolved faults, most urgent first.','');
  const d=await ntFetch('alerts'); if(!d) return;
  const C={critical:['#fee2e2','#991b1b'],high:['#fef3c7','#92400e'],warn:['#fef9c3','#854d0e'],info:['#dbeafe','#1e40af']};
  ntCards([['Critical',d.filter(x=>x.severity==='critical').length,'#dc2626'],['High',d.filter(x=>x.severity==='high').length,'#b45309'],['Other',d.filter(x=>!['critical','high'].includes(x.severity)).length]]);
  ntBody(ntTable(['Level','Alert','Asset','Details',''],d.map(x=>[ntPill(x.severity,...C[x.severity]),amEsc(x.kind),amEsc(x.asset),amEsc(x.message),ntBtn('Open',`location.href='assets.php?module=assets&sub=${x.tab}'`,'#6b7280')]),'No alerts. Everything is on schedule.'));
}
async function ntCritical(){
  ntSet('Critical Equipment','fa-life-ring','Life-support, oxygen, imaging and laboratory equipment. Downtime is highlighted and any fault is escalated to critical priority. Tick equipment as critical on the Equipment Status tab.','');
  const all=await ntFetch('equipment'); if(!all) return; const d=all.filter(a=>a.is_critical==1);
  ntCards([['Critical assets',d.length],['Not operational',d.filter(a=>a.equipment_status!=='operational').length,d.some(a=>a.equipment_status!=='operational')?'#dc2626':'#065f46'],['Total downtime (h)',d.reduce((n,a)=>n+Number(a.downtime_hours),0).toLocaleString()]]);
  ntBody(ntTable(['Asset','Location','Status','Open faults','Repairs','Downtime (h)','Maintenance cost','Next maintenance'],d.map(a=>[amEsc(a.asset_code+' - '+a.name),amEsc(a.location||'—'),ntEq(a.equipment_status),a.open_faults>0?`<b style="color:#dc2626;">${a.open_faults}</b>`:'0',a.repairs,a.downtime_hours,Number(a.maintenance_cost).toLocaleString(),amEsc(a.next_due||'—')]),'No equipment is marked critical yet.'));
}
ntInit();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
