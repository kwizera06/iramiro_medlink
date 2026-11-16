<?php
require_once __DIR__ . '/includes/header.php';
// Prices are hidden, and removals need admin approval, for everyone except admins and cashiers/finance
$isNurseOnly = false;
if (isset($_SESSION['user_id'])) {
    $rs = $pdo->prepare("SELECT GROUP_CONCAT(r.slug) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?");
    $rs->execute([$_SESSION['user_id']]);
    $isNurseOnly = !array_intersect(explode(',', (string)$rs->fetchColumn()), ['admin','administrator','accountant','cashier','finance']) && empty($_SESSION['user_data']['is_admin']);
}
?>
<?php if ($isNurseOnly): ?>
<style>
.hide-for-nurse{display:none !important;}
.proc-hide-invoice{display:none !important;}
</style>
<?php endif; ?>
<style>
.proc-badge{padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;}
.proc-ins{background:#d1fae5;color:#065f46;}
.proc-self{background:#e0e7ff;color:#3730a3;}
.notif-bell-wrap{position:relative;display:inline-block;}
.notif-bell-btn{position:relative;padding:10px 14px;background:white;border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:16px;color:var(--gray-600);transition:all .2s;}
.notif-bell-btn:hover{background:var(--gray-50);color:var(--primary-color);}
.notif-bell-btn .notif-count{position:absolute;top:-4px;right:-4px;background:#dc2626;color:white;font-size:10px;font-weight:700;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;}
.notif-dropdown{display:none;position:absolute;top:calc(100% + 8px);right:0;width:380px;max-height:420px;background:white;border:1px solid var(--gray-200);border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,0.15);z-index:9999;overflow:hidden;}
.notif-dropdown.show{display:block;}
.notif-dropdown-header{padding:14px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;}
.notif-dropdown-header h4{font-size:14px;font-weight:700;margin:0;}
.notif-dropdown-body{max-height:340px;overflow-y:auto;}
.notif-item{padding:12px 16px;border-bottom:1px solid var(--gray-100);cursor:pointer;transition:background .15s;}
.notif-item:hover{background:var(--gray-50);}
.notif-item.unread{background:#eff6ff;border-left:3px solid #2563eb;}
.notif-item .notif-title{font-size:13px;font-weight:600;color:var(--gray-900);}
.notif-item .notif-msg{font-size:12px;color:var(--gray-600);margin-top:2px;}
.notif-item .notif-time{font-size:10px;color:var(--gray-400);margin-top:4px;}
.notif-item .notif-mark-btn{margin-top:6px;padding:4px 10px;background:#d1fae5;color:#065f46;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;}
.notif-item .notif-mark-btn:hover{background:#a7f3d0;}
.notif-empty{padding:40px;text-align:center;color:var(--gray-400);font-size:13px;}
.notif-mute-btn{position:relative;padding:10px 14px;background:white;border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:16px;color:var(--gray-600);transition:all .2s;}
.notif-mute-btn:hover{background:var(--gray-50);color:var(--primary-color);}
.notif-mute-btn.muted{color:#dc2626;border-color:#fecaca;background:#fef2f2;}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;">Procedures</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Perform procedures for patients and generate invoices</p>
  </div>
  <!-- Notification Bell -->
  <div class="notif-bell-wrap" id="notif-bell-wrap">
    <button class="notif-bell-btn" onclick="notifToggleDropdown()" title="Notifications">
      <i class="fas fa-bell"></i>
      <span class="notif-count" id="notif-count" style="display:none;">0</span>
    </button>
    <div class="notif-dropdown" id="notif-dropdown">
      <div class="notif-dropdown-header">
        <h4><i class="fas fa-bell" style="color:var(--primary-color);margin-right:6px;"></i> Notifications</h4>
        <button onclick="notifMarkAllRead()" style="padding:4px 10px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:4px;font-size:11px;cursor:pointer;font-weight:500;">Mark all read</button>
      </div>
      <div class="notif-dropdown-body" id="notif-list">
        <div class="notif-empty"><i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:var(--gray-300);"></i>No notifications</div>
      </div>
    </div>
  </div>
  <!-- Sound Mute Toggle -->
  <button class="notif-mute-btn" id="notif-mute-btn" onclick="notifToggleMute()" title="Mute/Unmute notification sounds">
    <i class="fas fa-volume-up"></i>
  </button>
</div>

<?php require_once __DIR__ . '/includes/nurse_tabs.php'; nurse_tabs($pdo, 'procedures'); ?>
<!-- Patient List with Active Visits -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-users" style="color:var(--primary-color);"></i> Patients with Active Visits</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input id="proc-date-from" type="date" onchange="procPage=1;procLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;color:var(--gray-700);">
      <span style="font-size:12px;color:var(--gray-400);">to</span>
      <input id="proc-date-to" type="date" onchange="procPage=1;procLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;color:var(--gray-700);">
      <input id="proc-search" type="text" placeholder="Search patient..." oninput="procSearchDebounce()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:200px;">
      <button onclick="procLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px;text-align:left;">Patient</th>
        <th style="padding:10px;text-align:left;">Visit No.</th>
        <th style="padding:10px;text-align:left;">Date</th>
        <th style="padding:10px;text-align:left;">Opened By</th>
        <th style="padding:10px;text-align:center;">Payment Type</th>
      </tr></thead>
      <tbody id="proc-tbody" data-no-page><tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
  <div id="proc-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;"></div>
</div>

<!-- Procedure Modal -->
<div id="proc-full-page" style="display:none;">
  <div style="margin-bottom:16px;display:flex;gap:10px;align-items:center;">
    <button onclick="procBackToList()" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;"><i class="fas fa-arrow-left"></i> Back to Patient List</button>
    <button onclick="procPrintPDF()" style="padding:8px 16px;background:#dc2626;color:white;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;"><i class="fas fa-file-pdf"></i> Print PDF</button>
  </div>
  <div id="proc-full-content" style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
    <p style="text-align:center;color:var(--gray-400);padding:30px;"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></p>
  </div>
</div>

<!-- Procedure Modal -->
<div id="proc-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-syringe" style="color:var(--primary-color);"></i> Add Procedure</h3>
      <button onclick="procCloseModal()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>

    <!-- Patient Info -->
    <div style="background:var(--gray-50);padding:12px;border-radius:8px;margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;">
        <span style="font-weight:600;" id="proc-patient-name">—</span>
        <span id="proc-payment-badge"></span>
      </div>
      <small style="color:var(--gray-500);" id="proc-visit-info">—</small>
      <div id="proc-ins-info" style="display:none;margin-top:6px;padding:6px 10px;background:#d1fae5;border-radius:4px;font-size:11px;color:#065f46;"></div>
    </div>

    <!-- Apply Insurance Toggle -->
    <div id="proc-ins-toggle" style="display:none;border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:12px;">
      <div style="font-size:12px;color:var(--gray-500);">Insurance will be applied per procedure below. Uncheck individually if needed.</div>
    </div>

    <!-- Procedure Selection -->
    <div style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Search & Select Procedure *</label>
      <input id="proc-service-search" type="text" placeholder="Type to search procedures..." oninput="procSearchServices()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      <div id="proc-service-list" style="max-height:200px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:6px;margin-top:6px;display:none;"></div>
    </div>

    <!-- Selected Procedures -->
    <div style="margin-bottom:16px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Selected Procedures</label>
      <div id="proc-selected" style="min-height:40px;border:1px solid var(--gray-200);border-radius:6px;padding:8px;">
        <p style="color:var(--gray-400);font-size:12px;text-align:center;margin:0;">No procedures selected</p>
      </div>
      <div class="hide-for-nurse" style="display:flex;justify-content:space-between;margin-top:8px;font-size:13px;font-weight:600;">
        <span>Total:</span>
        <span id="proc-total">0 RWF</span>
      </div>
      <div id="proc-split-info" class="hide-for-nurse" style="display:none;font-size:12px;color:var(--gray-500);margin-top:4px;text-align:right;"></div>
    </div>

    <div id="proc-doctor-slot"></div>

    <button onclick="procSubmit()" id="proc-submit-btn" style="width:100%;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check-circle"></i> Generate Invoice & Save</button>
  </div>
</div>

<!-- Procedure History Modal -->
<div id="proc-history-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;padding:24px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-history" style="color:#7c3aed;"></i> Procedures Performed</h3>
      <button onclick="document.getElementById('proc-history-modal').style.display='none'" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="background:var(--gray-50);padding:10px;border-radius:8px;margin-bottom:12px;">
      <span style="font-weight:600;" id="proc-hist-patient">—</span>
    </div>
    <div id="proc-hist-content" style="min-height:100px;">
      <p style="text-align:center;color:var(--gray-400);padding:20px;"><i class="fas fa-spinner fa-spin"></i></p>
    </div>
  </div>
</div>

<!-- Patient Details Modal -->
<div id="proc-details-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px;">
  <div style="background:white;border-radius:12px;width:750px;max-width:95vw;padding:24px;margin:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--gray-200);">
      <h3 style="font-size:18px;font-weight:600;"><i class="fas fa-user-circle" style="color:#059669;"></i> Patient Visit Details</h3>
      <button onclick="document.getElementById('proc-details-modal').style.display='none'" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div id="proc-details-content">
      <div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></div>
    </div>
  </div>
</div>

<script>
const _procBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const procVisitAPI = window.location.origin + _procBase + '/api.php?endpoint=patients/visits';
const procBillAPI = window.location.origin + _procBase + '/api.php?endpoint=patients/billing';
const procIsNurse = <?php echo $isNurseOnly ? 'true' : 'false'; ?>;
let procTimer,procData={},procSelectedServices=[],procCopay=100;
let procInsId=null, procPaymentType='self_pay', procDrugInsPrices=[];
let procPage=1;

// ── Duplicate order guard ─────────────────────────────────────────────────
const _procSubmittedItems = new Set();
function _procDupKey(desc){ return String(desc||'').toLowerCase().trim(); }
function _procCheckDups(descs){
  const dupes = descs.filter(d => _procSubmittedItems.has(_procDupKey(d)));
  if(dupes.length){
    alert('⚠️ Duplicate item(s) already submitted this session:\n\n• ' + dupes.join('\n• ') + '\n\nRemove or deselect them before submitting.');
    return false;
  }
  return true;
}
function _procMarkSubmitted(descs){ descs.forEach(d => _procSubmittedItems.add(_procDupKey(d))); }
// ── End duplicate guard ───────────────────────────────────────────────────

// Initialize date filters to last month
dfRange('proc-date-from','proc-date-to');

function procSearchDebounce(){clearTimeout(procTimer);procTimer=setTimeout(()=>{procPage=1;procLoad();},350);}

async function procLoad(silent){
  const search=document.getElementById('proc-search').value.trim();
  const dateFrom=document.getElementById('proc-date-from').value;
  const dateTo=document.getElementById('proc-date-to').value;
  const tbody=document.getElementById('proc-tbody');
  if(silent!==true){
    tbody.innerHTML='<tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    document.getElementById('proc-pagination').innerHTML='';
  }
  try{
    const params=new URLSearchParams({search,status:'open,in_progress',limit:pgSize('proc',25),page:procPage});
    if(dateFrom) params.set('date_from', dateFrom);
    if(dateTo) params.set('date_to', dateTo);
    const r=await fetch(procVisitAPI+'&'+params);
    const j=await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:#dc2626;">'+j.message+'</td></tr>';return;}
    const visits=j.data.visits||j.data||[];
    const pagination=j.data.pagination||null;
    if(!visits.length){
      tbody.innerHTML='<tr><td colspan="4" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-clipboard-check" style="font-size:24px;display:block;margin-bottom:8px;"></i>No active visits found.</td></tr>';
      return;
    }
    tbody.innerHTML=visits.map(v=>{
      const name=v.patient_name||(v.first_name+' '+v.last_name);
      const isIns=v.payment_type==='insurance'||v.payment_type==='mixed';
      const isMixed=v.payment_type==='mixed';
      return `<tr style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onclick="procShowFullPage(${v.id},${v.patient_id},'${name.replace(/'/g,"\\'")}')" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <td style="padding:12px 14px;">
          <div style="font-weight:600;font-size:14px;">${name}</div>
          <div style="font-size:11px;color:var(--gray-500);margin-top:2px;">${v.patient_no||''}</div>
        </td>
        <td style="padding:12px 14px;font-family:monospace;font-size:12px;color:var(--primary-color);">${v.visit_no||''}</td>
        <td style="padding:12px 14px;font-size:12px;color:var(--gray-600);">${v.visit_date||''}</td>
        <td style="padding:12px 14px;font-size:12px;">
          ${v.opened_by_name
            ? `<span style="font-weight:600;color:var(--gray-800);"><i class="fas fa-user" style="color:var(--primary-color);margin-right:4px;"></i>${v.opened_by_name}</span>`
            : `<span style="color:var(--gray-400);">—</span>`}
        </td>
        <td style="padding:12px 14px;">
          <span class="proc-badge ${isIns?'proc-ins':'proc-self'}" style="display:inline-block;margin-bottom:${isIns&&v.company_name?'3px':'0'};">
            ${isMixed?'Mixed':isIns?'Insurance':'Self-pay'}
          </span>
          ${isIns&&v.company_name?`<div style="font-size:11px;color:#065f46;font-weight:500;"><i class="fas fa-shield-alt" style="margin-right:3px;opacity:.6;"></i>${v.company_name}</div>`:''}
        </td>
      </tr>`;
    }).join('');
    // Pagination
    const pg=document.getElementById('proc-pagination');
    if(pagination && pagination.total_pages>1){
      pg.innerHTML=`
        <button onclick="procPage--;procLoad()" ${pagination.page<=1?'disabled':''} style="padding:8px 16px;border:1px solid var(--gray-200);background:${pagination.page<=1?'var(--gray-100)':'white'};color:${pagination.page<=1?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${pagination.page<=1?'not-allowed':'pointer'};font-weight:500;font-size:13px;"><i class="fas fa-chevron-left"></i> Previous</button>
        <div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect('proc',25,'procPage=1;procLoad()',[10,25,50,100])}<span style="font-size:13px;color:var(--gray-600);">Page ${pagination.page} of ${pagination.total_pages} <span style="color:var(--gray-400);">(${pagination.total} visits)</span></span></div>
        <button onclick="procPage++;procLoad()" ${pagination.page>=pagination.total_pages?'disabled':''} style="padding:8px 16px;border:1px solid var(--gray-200);background:${pagination.page>=pagination.total_pages?'var(--gray-100)':'white'};color:${pagination.page>=pagination.total_pages?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${pagination.page>=pagination.total_pages?'not-allowed':'pointer'};font-weight:500;font-size:13px;">Next <i class="fas fa-chevron-right"></i></button>`;
    } else { pg.innerHTML=pagination?`<div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect('proc',25,'procPage=1;procLoad()',[10,25,50,100])}<span style="font-size:13px;color:var(--gray-600);">${pagination.total} visits</span></div>`:''; }
  }catch(e){
    tbody.innerHTML='<tr><td colspan="5" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}

async function procOpenModal(visitId){
  // Fetch full visit details including insurance
  try{
    const r=await fetch(procVisitAPI+'&id='+visitId);
    const j=await r.json();
    if(!j.success){alert('Could not load visit');return;}
    const v=j.data;
    await procLoadDoctors();
    document.getElementById('proc-doctor-slot').innerHTML=procDoctorField('proc');
    const name=(v.first_name||'')+' '+(v.last_name||'');
    const isIns=v.payment_type==='insurance'||v.payment_type==='mixed';
    
    procData={
      visitId:v.id,
      patientId:v.patient_id,
      patientName:name,
      visitNo:v.visit_no,
      paymentType:v.payment_type,
      insuranceId:v.insurance_id||null,
      companyId:v.company_id||null,
      copay:100
    };
    procSelectedServices=[];
    
    document.getElementById('proc-patient-name').textContent=name;
    document.getElementById('proc-visit-info').textContent='Visit: '+v.visit_no+' | Date: '+v.visit_date;
    document.getElementById('proc-payment-badge').innerHTML=isIns?'<span class="proc-badge proc-ins">Insurance</span>':'<span class="proc-badge proc-self">Self-pay</span>';
    
    // Show insurance info and toggle if applicable
    const insInfo=document.getElementById('proc-ins-info');
    const insToggle=document.getElementById('proc-ins-toggle');
    if(isIns && v.insurance_id){
      // Use copayment_percentage directly from visit data (already joined)
      procData.copay = parseFloat(v.copayment_percentage) || 100;
      procCopay=procData.copay;
      procInsId=v.insurance_id||null;
      procPaymentType=v.payment_type||'self_pay';
      window._procCompanyId = v.company_id||null;
      window._procInsCompanyId = v.company_id||null;
      // Load drug insurance prices if not already loaded for this company
      if(v.company_id && (!procDrugInsPrices.length || window._lastDrugInsCo!==v.company_id)){
        try{
          const ir=await fetch(window.location.origin + _procBase + '/api.php?endpoint=inventory/inventory-management&action=insurance_prices&insurance_id='+v.company_id);
          const ij=await ir.json();
          if(ij.success){ procDrugInsPrices=ij.data||[]; window._lastDrugInsCo=v.company_id; }
        }catch(e){ procDrugInsPrices=[]; }
      }
      const coverage=100-procCopay;
      const companyName=v.company_name||'Insurance';
      const packageName=v.package_name||'';
      insInfo.innerHTML=`<i class="fas fa-shield-alt"></i> <strong>${companyName}</strong>${packageName?' - '+packageName:''} | Coverage: ${coverage}% | Patient pays: ${procCopay}%`;
      insInfo.style.display='block';
      insToggle.style.display='block';
    }else{
      insInfo.style.display='none';
      insToggle.style.display='none';
      procCopay=100;
    }
    
    document.getElementById('proc-service-search').value='';
    document.getElementById('proc-service-list').style.display='none';
    procRenderSelected();
    document.getElementById('proc-modal').style.display='flex';
  }catch(e){alert('Error: '+e.message);}
}

function procCloseModal(){document.getElementById('proc-modal').style.display='none';}

async function procViewHistory(visitId, patientName){
  document.getElementById('proc-hist-patient').textContent=patientName;
  document.getElementById('proc-hist-content').innerHTML='<p style="text-align:center;color:var(--gray-400);padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
  document.getElementById('proc-history-modal').style.display='flex';
  
  try{
    // Fetch invoices for this visit
    const r=await fetch(procBillAPI+'&with_items=1&visit_id='+visitId);
    const j=await r.json();
    if(!j.success){document.getElementById('proc-hist-content').innerHTML='<p style="text-align:center;color:#dc2626;padding:20px;">'+j.message+'</p>';return;}
    
    const invoices=j.data||[];
    if(!invoices.length){
      document.getElementById('proc-hist-content').innerHTML='<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-clipboard-check" style="font-size:24px;display:block;margin-bottom:8px;"></i>No procedures performed for this visit yet.</div>';
      return;
    }
    
    // For each invoice, fetch its items
    let procedures=[];
    for(const inv of invoices){
      try{
        const ir={json:async()=>({success:true,data:inv})}; /* items came with the list (with_items=1) */
        const ij=await ir.json();
        if(ij.success && ij.data && ij.data.items){
          ij.data.items.filter(it=>it.category==='procedure').forEach(it=>{
            procedures.push({...it, invoice_no:inv.invoice_no, date:inv.created_at, status:inv.status, created_by_name:inv.created_by_name||''});
          });
        }
      }catch(e){}
    }
    
    if(!procedures.length){
      document.getElementById('proc-hist-content').innerHTML='<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-clipboard-check" style="font-size:24px;display:block;margin-bottom:8px;"></i>No procedures performed for this visit yet.</div>';
      return;
    }
    
    let total=0;
    const rows=procedures.map((p,i)=>{
      const amt=parseFloat(p.total||p.unit_price||0);
      total+=amt;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;">${i+1}</td>
        <td style="padding:8px 10px;font-weight:500;">${p.description||p.service_name||'—'}</td>
        <td style="padding:8px 10px;font-size:12px;color:var(--gray-600);"><i class="fas fa-user-nurse" style="margin-right:4px;color:var(--gray-400);"></i>${p.created_by_name||'—'}<br><span style="font-size:10px;color:var(--gray-400);">${p.date?new Date(p.date).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—'}</span></td>
        ${procIsNurse?'':'<td style="padding:8px 10px;text-align:right;font-weight:600;">'+amt.toLocaleString()+' RWF</td>'}
        ${procIsNurse?'':'<td style="padding:8px 10px;font-size:11px;font-family:monospace;">'+(p.invoice_no||'—')+'</td>'}
        <td style="padding:8px 10px;text-align:center;"><span style="padding:2px 6px;border-radius:8px;font-size:10px;font-weight:600;background:${p.status==='paid'?'#d1fae5':'#fef3c7'};color:${p.status==='paid'?'#065f46':'#92400e'};">${p.status||'issued'}</span></td>
        <td style="padding:8px 10px;text-align:center;"><button onclick="procDeleteDirect('procedure',${p.id},'${(p.description||p.service_name||'Procedure').replace(/'/g,"\\'")}',${visitId})" style="padding:3px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:10px;cursor:pointer;" title="Delete Procedure"><i class="fas fa-trash"></i></button></td>
      </tr>`;
    }).join('');
    
    document.getElementById('proc-hist-content').innerHTML=`
      <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
          <thead><tr style="background:var(--gray-50);">
            <th style="padding:8px 10px;text-align:left;">#</th>
            <th style="padding:8px 10px;text-align:left;">Procedure</th>
            <th style="padding:8px 10px;text-align:left;">Performed By</th>
            ${procIsNurse?'':'<th style="padding:8px 10px;text-align:right;">Amount</th>'}
            ${procIsNurse?'':'<th style="padding:8px 10px;text-align:left;">Invoice</th>'}
            <th style="padding:8px 10px;text-align:center;">Status</th>
            <th style="padding:8px 10px;text-align:center;">Action</th>
          </tr></thead>
          <tbody>${rows}</tbody>
          <tfoot><tr style="background:var(--gray-50);font-weight:700;">
            <td colspan="3" style="padding:8px 10px;">Total (${procedures.length} procedures)</td>
            ${procIsNurse?'':'<td style="padding:8px 10px;text-align:right;">'+total.toLocaleString()+' RWF</td>'}
            <td colspan="${procIsNurse?2:2}"></td>
          </tr></tfoot>
        </table>
      </div>`;
  }catch(e){
    document.getElementById('proc-hist-content').innerHTML='<p style="text-align:center;color:#dc2626;padding:20px;">Error: '+e.message+'</p>';
  }
}

async function procSearchServices(){
  const q=document.getElementById('proc-service-search').value.trim();
  const list=document.getElementById('proc-service-list');
  if(q.length<2){list.style.display='none';return;}

  // Load facility category if not loaded
  if(!window._procFacilityCategory){
    try{
      const hr=await fetch(window.location.origin + _procBase + '/api.php?endpoint=settings/hospital');
      const hj=await hr.json();
      if(hj.success && hj.data) window._procFacilityCategory = hj.data.facility_category || 'primary';
    }catch(e){ window._procFacilityCategory = 'primary'; }
  }

  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=service-catalog&category=procedure&visit_id='+procData.visitId+'&search='+encodeURIComponent(q));
    const j=await r.json();
    const services=(j.data||[]).filter(s => {
      const spec = (s.speciality||'').toLowerCase();
      if(spec.startsWith('imaging')) return false;
      return !procLabSpecialties.some(ls => spec.includes(ls));
    });
    if(!services.length){list.innerHTML='<div style="padding:10px;color:var(--gray-400);font-size:12px;text-align:center;">No procedures found</div>';list.style.display='block';return;}
    const cat=window._procFacilityCategory||'primary';
    const catLabel=cat==='secondary'?'Secondary':cat==='tertiary'?'Tertiary':'Primary';
    const isIns=(procData.paymentType==='insurance'||procData.paymentType==='mixed')&&window._procInsCompanyId;
    list.innerHTML=services.slice(0,20).map(s=>{
      const basePrice=cat==='secondary'?parseFloat(s.secondary_health_facility)||0:cat==='tertiary'?parseFloat(s.tertiary_health_facility)||0:parseFloat(s.primary_health_facility)||0;
      const safeName=(s.procedure_name||'').replace(/'/g,"\\'").replace(/"/g,'&quot;');
      let priceNote=procIsNurse?'':`<span style="font-weight:600;color:var(--primary-color);">${basePrice.toLocaleString()} RWF</span> <span style="color:var(--gray-400);font-size:10px;">(${catLabel})</span>`;
      if(isIns&&!procIsNurse) priceNote+=` <span style="color:#d97706;font-size:10px;">⚡ insurance price on add</span>`;
      return `<div onclick="procAddService(${s.id},'${safeName}',${basePrice})" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--gray-100);font-size:12px;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-weight:500;">${s.procedure_name||'—'}</span>
          ${priceNote}
        </div>
        <div style="font-size:10px;color:var(--gray-500);margin-top:2px;">${s.speciality||''} ${s.ichi_code?'| '+s.ichi_code:''}</div>
      </div>`;
    }).join('');
    list.style.display='block';
  }catch(e){list.style.display='none';}
}

async function procAddService(id,name,basePrice){
  if(procSelectedServices.find(s=>s.id===id))return;
  const isIns=(procData.paymentType==='insurance'||procData.paymentType==='mixed')&&(window._procInsCompanyId||procData.insuranceId||procInsId);

  // Fetch insurance_procedure_prices for this service if patient has insurance
  let insPrice=null;
  if(isIns){
    try{
      const insCompanyId = window._procInsCompanyId || window._procCompanyId || procData.insuranceId || procInsId;
      const r=await fetch(window.location.origin + _procBase + `/api.php?endpoint=service-catalog&action=insurance_price&service_id=${id}&insurance_id=${insCompanyId}`);
      const j=await r.json();
      if(j.success && j.data) insPrice=j.data;
    }catch(e){}
  }

  // Effective price: use insurance agreed price if available, else base price
  const effectivePrice = insPrice ? parseFloat(insPrice.price)||basePrice : basePrice;

  procSelectedServices.push({
    id, name, qty: 1,
    price: effectivePrice,
    basePrice: basePrice,
    insPrice: insPrice,
    insured: !!isIns
  });
  document.getElementById('proc-service-search').value='';
  document.getElementById('proc-service-list').style.display='none';
  procRenderSelected();
}

function procRemoveService(id){
  procSelectedServices=procSelectedServices.filter(s=>s.id!==id);
  procRenderSelected();
}

function procSetQty(id,v){
  const svc=procSelectedServices.find(s=>s.id===id);
  if(svc) svc.qty=Math.max(1,parseInt(v)||1);
  procRenderSelected();
}

function procToggleServiceIns(id){
  const svc=procSelectedServices.find(s=>s.id===id);
  if(svc) svc.insured=!svc.insured;
  procRenderSelected();
}

function procRenderSelected(){
  const div=document.getElementById('proc-selected');
  const isIns=(procData.paymentType==='insurance'||procData.paymentType==='mixed')&&procInsId;
  if(!procSelectedServices.length){
    div.innerHTML='<p style="color:var(--gray-400);font-size:12px;text-align:center;margin:0;">No procedures selected</p>';
    document.getElementById('proc-total').textContent='0 RWF';
    document.getElementById('proc-split-info').style.display='none';
    return;
  }

  div.innerHTML=procSelectedServices.map(s=>{
    // Build per-service insurance note
    let insNote='';
    if(isIns && s.insured && !procIsNurse){
      const agreedPrice = (s.insPrice ? parseFloat(s.insPrice.price)||s.basePrice : s.basePrice) * s.qty;
      const insPct   = Math.round(100-procCopay);
      const patPct   = procCopay;
      const insPays  = Math.round(agreedPrice * (insPct/100));
      const patPays  = agreedPrice - insPays;
      if(s.insPrice){
        insNote=`<div style="font-size:10px;margin-top:4px;padding:4px 8px;background:#f0fdf4;border-radius:4px;color:#065f46;">
          <i class="fas fa-shield-alt"></i>
          Agreed price: <strong>${agreedPrice.toLocaleString()} RWF</strong> |
          Insurance (${insPct}%): <strong>${insPays.toLocaleString()} RWF</strong> |
          Patient (${patPct}%): <strong>${patPays.toLocaleString()} RWF</strong>
        </div>`;
      } else {
        insNote=`<div style="font-size:10px;margin-top:4px;padding:4px 8px;background:#fffbeb;border-radius:4px;color:#92400e;">
          <i class="fas fa-exclamation-triangle"></i>
          No specific insurance price — using facility rate.
          Insurance (${insPct}%): <strong>${insPays.toLocaleString()} RWF</strong> |
          Patient (${patPct}%): <strong>${patPays.toLocaleString()} RWF</strong>
        </div>`;
      }
    }
    return `<div style="padding:8px;border-bottom:1px solid var(--gray-100);">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:12px;font-weight:500;">${s.name}</span>
        <div style="display:flex;align-items:center;gap:8px;">
          <input type="number" min="1" value="${s.qty}" title="Quantity" onchange="procSetQty(${s.id},this.value)" style="width:52px;padding:3px 5px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:center;">
          ${procIsNurse?'':`<span style="font-size:12px;font-weight:600;">${(s.price*s.qty).toLocaleString()} RWF</span>`}
          <button onclick="procRemoveService(${s.id})" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;"><i class="fas fa-times"></i></button>
        </div>
      </div>
      ${isIns&&!procIsNurse?`<label style="display:flex;align-items:center;gap:6px;margin-top:4px;cursor:pointer;font-size:11px;color:${s.insured?'#059669':'#6b7280'};">
        <input type="checkbox" ${s.insured?'checked':''} onchange="procToggleServiceIns(${s.id})" style="width:14px;height:14px;">
        ${s.insured?'Insurance applied':'No insurance — patient pays full'}
      </label>`:''}
      ${insNote}
    </div>`;
  }).join('');

  // Summary totals
  let patientTotal=0, insTotal=0;
  procSelectedServices.forEach(s=>{
    const agreedPrice = ((isIns && s.insured && s.insPrice) ? parseFloat(s.insPrice.price)||s.price : s.price) * s.qty;
    if(isIns && s.insured){
      const insPct  = (100-procCopay)/100;
      const patPct  = procCopay/100;
      const insPays = Math.round(agreedPrice * insPct);
      const patPays = agreedPrice - insPays;
      patientTotal += patPays;
      insTotal     += insPays;
    } else {
      patientTotal += s.price * s.qty;
    }
  });
  const grandTotal = patientTotal + insTotal;
  document.getElementById('proc-total').textContent = grandTotal.toLocaleString()+' RWF';

  const splitInfo=document.getElementById('proc-split-info');
  if(isIns){
    splitInfo.textContent=`Patient pays: ${patientTotal.toLocaleString()} RWF | Insurance pays: ${insTotal.toLocaleString()} RWF`;
    splitInfo.style.display='block';
  }else{
    splitInfo.style.display='none';
  }
}

async function procSubmit(){
  if(!procSelectedServices.length)return alert('Please select at least one procedure.');
  const procDoctorId=procGetDoctor('proc');   // optional

  // Duplicate guard
  const procDescs = procSelectedServices.map(s => s.name+(s.insPrice&&s.insured?' [Insurance price]':''));
  if(!_procCheckDups(procDescs)) return;

  const isIns=(procData.paymentType==='insurance'||procData.paymentType==='mixed')&&procInsId;
  const hasAnyInsured=procSelectedServices.some(s=>s.insured);
  
  const btn=document.getElementById('proc-submit-btn');
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
  
  try{
    const body={
      visit_id:procData.visitId,
      patient_id:procData.patientId,
      created_by:procUserId,
      items:procSelectedServices.map(s=>{
        // Use insurance agreed price if available and insured
        const agreedPrice = (isIns && s.insured && s.insPrice) ? parseFloat(s.insPrice.price)||s.price : s.price;
        return {
          service_id:s.id,
          description:s.name+(s.insPrice&&s.insured?' [Insurance price]':''),
          category:'procedure',
          quantity:s.qty,
          unit_price:agreedPrice,
          discount:0,
          total:agreedPrice*s.qty
        };
      }),
      payment_type:hasAnyInsured&&isIns?'insurance':'self_pay',
      insurance_id:hasAnyInsured&&isIns?(window._procInsCompanyId||procData.insuranceId):null,
      copayment_percentage:hasAnyInsured&&isIns?procCopay:100
    };
    const r=await fetch(procBillAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){
      _procMarkSubmitted(procDescs);
      await procLinkDoctor('procedure',procData.visitId,procData.patientId,procDoctorId,procSelectedServices.map(s=>s.name+(s.qty>1?' x'+s.qty:'')).join(', '));
      alert('✓ Invoice generated! '+j.message);
      procCloseModal();
      procLoad();
    }else{
      alert('Error: '+j.message);
    }
  }catch(e){
    alert('Failed: '+e.message);
  }finally{
    btn.disabled=false;btn.innerHTML='<i class="fas fa-check-circle"></i> Generate Invoice & Save';
  }
}

async function procShowFullPage(visitId, patientId, patientName){
  window._procCurrentVisitId = visitId;
  window._procCurrentPatientId = patientId;
  window._procCurrentPatientName = patientName;

  // Hide patient list, show full page
  document.querySelector('[style*="background:white"][style*="box-shadow"]').style.display='none';
  const fullPage=document.getElementById('proc-full-page');
  fullPage.style.display='block';
  const content=document.getElementById('proc-full-content');
  content.innerHTML='<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i> Loading...</div>';

  function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

  // Build the page content
  let html=`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid var(--gray-200);">
    <div><h3 style="font-size:18px;font-weight:700;margin:0;">${esc(patientName)}</h3><span style="font-size:13px;color:var(--gray-500);">Visit Details</span></div>
  </div>`;

  // Action Buttons
  html+=`<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px;">
    <button onclick="procOpenModal(${visitId})" style="padding:16px;background:#f0f9ff;border:2px solid #bae6fd;border-radius:12px;cursor:pointer;text-align:center;">
      <i class="fas fa-syringe" style="font-size:24px;color:var(--primary-color);display:block;margin-bottom:6px;"></i>
      <div style="font-size:13px;font-weight:600;color:var(--primary-color);">Add Procedure</div>
    </button>
    <button onclick="procOpenRx(${visitId},${patientId},'medication')" style="padding:16px;background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;cursor:pointer;text-align:center;">
      <i class="fas fa-prescription" style="font-size:24px;color:#16a34a;display:block;margin-bottom:6px;"></i>
      <div style="font-size:13px;font-weight:600;color:#166534;">Medication</div>
    </button>
    <button onclick="procOpenRx(${visitId},${patientId},'consumable')" style="padding:16px;background:#fffbeb;border:2px solid #fde68a;border-radius:12px;cursor:pointer;text-align:center;">
      <i class="fas fa-box-open" style="font-size:24px;color:#b45309;display:block;margin-bottom:6px;"></i>
      <div style="font-size:13px;font-weight:600;color:#92400e;">Consumables</div>
    </button>
    <button onclick="procOpenSample(${visitId},${patientId})" style="padding:16px;background:#eff6ff;border:2px solid #bfdbfe;border-radius:12px;cursor:pointer;text-align:center;">
      <i class="fas fa-vial" style="font-size:24px;color:#2563eb;display:block;margin-bottom:6px;"></i>
      <div style="font-size:13px;font-weight:600;color:#1e40af;">Sample</div>
    </button>
  </div>`;

  // Load visit details
  try{
    const vr=await fetch(procVisitAPI+'&id='+visitId);const vj=await vr.json();
    if(vj.success){
      const v=vj.data;
      const isIns=v.payment_type==='insurance'||v.payment_type==='mixed';
      // Store globally for Rx/Lab modals
      procInsId=v.insurance_id||null;        // patient_insurance.id — used for billing
      procPaymentType=v.payment_type||'self_pay';
      procCopay = parseFloat(v.copayment_percentage) || 100;
      window._procCompanyId = v.company_id||null; // insurance company id — used for price lookups
      if(isIns && v.company_id){
        // Load item insurance prices (medications/drugs — category_id=1)
        try{const ir2=await fetch(window.location.origin + _procBase + '/api.php?endpoint=inventory/inventory-management&action=insurance_prices&insurance_id='+v.company_id);const ij2=await ir2.json();if(ij2.success) procDrugInsPrices=ij2.data||[];}catch(e){procDrugInsPrices=[];}
        // Store insurance company id for per-service insurance_procedure_prices lookups
        window._procInsCompanyId = v.company_id;
      } else {
        window._procInsCompanyId = null;
      }
      html+=`<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
        <div style="background:var(--gray-50);border-radius:8px;padding:14px;">
          <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;margin-bottom:6px;">Visit Info</div>
          <div style="font-size:13px;"><strong>Visit #:</strong> ${esc(v.visit_no)}</div>
          <div style="font-size:13px;"><strong>Date:</strong> ${v.visit_date||'—'}</div>
          <div style="font-size:13px;"><strong>Type:</strong> ${v.visit_type||'—'}</div>
          <div style="font-size:13px;"><strong>Department:</strong> ${esc(v.department_name||'—')}</div>
          <div style="font-size:13px;"><strong>Chief Complaint:</strong> ${esc(v.chief_complaint||'—')}</div>
        </div>
        <div style="background:${isIns?'#f0fdf4':'#f0f9ff'};border-radius:8px;padding:14px;">
          <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;margin-bottom:6px;">Payment & Insurance</div>
          <div style="font-size:13px;"><strong>Payment Type:</strong> <span style="padding:2px 8px;border-radius:10px;font-size:11px;background:${isIns?'#d1fae5':'#e0e7ff'};color:${isIns?'#065f46':'#3730a3'};">${v.payment_type}</span></div>
          ${isIns?`<div style="font-size:13px;margin-top:4px;"><strong>Insurance:</strong> ${esc(v.company_name||'—')}</div><div style="font-size:13px;"><strong>Package:</strong> ${esc(v.package_name||'—')}</div><div style="font-size:13px;"><strong>Co-pay:</strong> ${v.copayment_percentage||'—'}%</div>`:''}
        </div>
      </div>`;
    }
  }catch(e){}

  // Load vitals
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=settings/vital-signs&action=records&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      html+=`<div style="margin-bottom:20px;"><h4 style="font-size:14px;font-weight:600;margin-bottom:8px;"><i class="fas fa-heartbeat" style="color:#dc2626;"></i> Vital Signs</h4><div style="display:flex;flex-wrap:wrap;gap:8px;">`;
      j.data.forEach(v=>{
        html+=`<div style="background:var(--gray-50);border-radius:6px;padding:8px 12px;font-size:12px;"><strong>${esc(v.vital_name||v.sign_name||'')}</strong><br>${esc(v.value||'')} ${esc(v.unit||'')}</div>`;
      });
      html+=`</div></div>`;
    }
  }catch(e){}

  // Load patient info
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=patients/patients&id='+patientId);
    const j=await r.json();
    if(j.success&&j.data){
      const p=j.data;
      html+=`<div style="margin-bottom:20px;"><h4 style="font-size:14px;font-weight:600;margin-bottom:8px;"><i class="fas fa-user" style="color:var(--primary-color);"></i> Patient Information</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;background:var(--gray-50);border-radius:8px;padding:12px;font-size:13px;">
        <div><strong>Full Name:</strong> ${esc(p.first_name+' '+p.last_name)}</div>
        <div><strong>Patient No:</strong> ${esc(p.patient_no)}</div>
        <div><strong>Gender:</strong> ${esc(p.gender||'—')}</div>
        <div><strong>DOB:</strong> ${p.date_of_birth||'—'}</div>
        <div><strong>Phone:</strong> ${esc(p.phone||'—')}</div>
        <div><strong>Address:</strong> ${esc(p.address||'—')}</div>
      </div></div>`;
    }
  }catch(e){}



  // Load prescriptions
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=consultation/consult&action=prescriptions&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      html+=`<div style="margin-bottom:20px;"><h4 style="font-size:14px;font-weight:600;margin-bottom:8px;"><i class="fas fa-prescription" style="color:#16a34a;"></i> Prescriptions</h4>`;
      j.data.forEach(rx=>{
        const stColors={active:'#16a34a',dispensed_full:'#2563eb',dispensed_partial:'#d97706',cancelled:'#dc2626'};
        const stBg={active:'#dcfce7',dispensed_full:'#dbeafe',dispensed_partial:'#fef3c7',cancelled:'#fee2e2'};
        const performer=rx.created_by_name||rx.prescribed_by_name||'—';
        html+=`<div style="background:white;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:10px;overflow:hidden;">
          <div style="background:#f0fdf4;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #bbf7d0;">
            <div style="display:flex;align-items:center;gap:10px;">
              <span style="font-family:monospace;font-weight:700;color:var(--primary-color);font-size:13px;">${esc(rx.prescription_no)}</span>
              <span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:${stBg[rx.status]||'#f3f4f6'};color:${stColors[rx.status]||'#555'};">${rx.status}</span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
              <span style="font-size:11px;color:var(--gray-500);"><i class="fas fa-calendar-alt" style="margin-right:3px;"></i>${rx.prescription_date||''}</span>
              <span style="font-size:11px;color:#065f46;font-weight:500;"><i class="fas fa-user-nurse" style="margin-right:3px;opacity:.7;"></i>${esc(performer)}</span>
              <button onclick="procDeleteDirect('prescription',${rx.id},'${esc(rx.prescription_no||'Prescription').replace(/'/g,"\\'")}',${visitId})" style="padding:3px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:10px;cursor:pointer;" title="Delete Prescription"><i class="fas fa-trash"></i></button>
            </div>
          </div>`;
        if(rx.items&&rx.items.length){
          html+=`<table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="background:#f9fafb;border-bottom:1px solid #e5f5e0;">
              <th style="padding:7px 14px;text-align:left;color:var(--gray-600);">Drug</th>
              <th style="padding:7px 10px;text-align:center;color:var(--gray-600);">Dosage</th>
              <th style="padding:7px 10px;text-align:center;color:var(--gray-600);">Frequency</th>
              <th style="padding:7px 10px;text-align:center;color:var(--gray-600);">Duration</th>
              <th style="padding:7px 10px;text-align:center;color:var(--gray-600);">Qty</th>
              <th style="padding:7px 10px;text-align:center;color:var(--gray-600);">Dest.</th>
            </tr></thead><tbody>`;
          rx.items.forEach(it=>{
            html+=`<tr style="border-bottom:1px solid #f0fdf4;">
              <td style="padding:7px 14px;font-weight:500;">${esc(it.item_name||'—')}</td>
              <td style="padding:7px 10px;text-align:center;">${esc(it.dosage||'—')}</td>
              <td style="padding:7px 10px;text-align:center;">${esc(it.frequency||'—')}</td>
              <td style="padding:7px 10px;text-align:center;">${esc(it.duration||'—')}</td>
              <td style="padding:7px 10px;text-align:center;font-weight:600;">${it.quantity||0}</td>
              <td style="padding:7px 10px;text-align:center;"><span style="padding:2px 6px;background:#eff6ff;color:#1e40af;border-radius:4px;font-size:10px;">${esc(it.destination||'Hospital')}</span></td>
            </tr>`;
          });
          html+=`</tbody></table>`;
        }
        html+=`</div>`;
      });
      html+=`</div>`;
    }
  }catch(e){}

  // Load lab results
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=consultation/consult&action=lab_results&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      html+=`<div style="margin-bottom:20px;"><h4 style="font-size:14px;font-weight:600;margin-bottom:8px;"><i class="fas fa-flask" style="color:#2563eb;"></i> Medical Orders & Results</h4>`;
      j.data.forEach(t=>{
        const stColor=t.status==='completed'?'#16a34a':t.status==='ordered'?'#d97706':'var(--gray-500)';
        html+=`<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:8px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong style="font-size:13px;">${esc(t.test_name||t.service_name||'Test')}</strong>
            <div style="display:flex;align-items:center;gap:8px;">
              <span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;color:${stColor};">${t.status||'ordered'}</span>
              <button onclick="procDeleteDirect('lab',${t.id},'${esc(t.test_name||t.service_name||'Lab Order').replace(/'/g,"\\'")}',${visitId})" style="padding:3px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:10px;cursor:pointer;" title="Delete Lab Order"><i class="fas fa-trash"></i></button>
            </div>
          </div>`;
        if(t.result){
          let parsed=null;
          try{parsed=JSON.parse(t.result);}catch(e){}
          if(parsed&&Array.isArray(parsed)&&parsed.length){
            html+=`<div style="margin-top:8px;background:white;border:1px solid #bbf7d0;border-radius:6px;padding:8px;">
              <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="border-bottom:1px solid var(--gray-200);">
                  <th style="padding:4px 8px;text-align:left;font-weight:600;color:#065f46;">Parameter</th>
                  <th style="padding:4px 8px;text-align:center;font-weight:600;color:#065f46;">Result</th>
                  <th style="padding:4px 8px;text-align:center;font-weight:600;color:#065f46;">Unit</th>
                  <th style="padding:4px 8px;text-align:center;font-weight:600;color:#065f46;">Normal Range</th>
                  <th style="padding:4px 8px;text-align:center;font-weight:600;color:#065f46;">Status</th>
                </tr></thead><tbody>`;
            parsed.forEach(r=>{
              const val=parseFloat(r.value);
              const min=r.normal_min!=null?parseFloat(r.normal_min):null;
              const max=r.normal_max!=null?parseFloat(r.normal_max):null;
              let flag='';
              if(!isNaN(val)&&min!=null&&max!=null){
                if(val<min) flag='<span style="color:#dc2626;font-weight:700;">↓ Low</span>';
                else if(val>max) flag='<span style="color:#dc2626;font-weight:700;">↑ High</span>';
                else flag='<span style="color:#059669;font-weight:600;">Normal</span>';
              }
              html+=`<tr style="border-bottom:1px solid var(--gray-100);">
                <td style="padding:4px 8px;font-weight:500;">${esc(r.test_name||'')}</td>
                <td style="padding:4px 8px;text-align:center;font-weight:700;">${esc(r.value||'—')}</td>
                <td style="padding:4px 8px;text-align:center;color:var(--gray-500);">${esc(r.unit||'')}</td>
                <td style="padding:4px 8px;text-align:center;color:var(--gray-500);">${min!=null&&max!=null?min+' - '+max:'—'}</td>
                <td style="padding:4px 8px;text-align:center;">${flag}</td>
              </tr>`;
            });
            html+=`</tbody></table></div>`;
          } else {
            html+=`<div style="margin-top:6px;font-size:12px;padding:6px 8px;background:white;border-radius:4px;">${esc(t.result)}</div>`;
          }
        }
        html+=`</div>`;
      });
      html+=`</div>`;
    }
  }catch(e){}

  // Load Procedures Performed (from invoices with category='procedure')
  if(!procIsNurse) try{
    const r=await fetch(procBillAPI+'&with_items=1&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      let procedures=[];
      for(const inv of j.data){
        try{
          const ir={json:async()=>({success:true,data:inv})}; /* items came with the list (with_items=1) */
          const ij=await ir.json();
          if(ij.success&&ij.data&&ij.data.items){
            ij.data.items.filter(it=>it.category==='procedure').forEach(it=>{
              procedures.push({...it, invoice_no:inv.invoice_no, date:inv.created_at, inv_status:inv.status, created_by_name:inv.created_by_name||''});
            });
          }
        }catch(e){}
      }
      if(procedures.length){
        html+=`<div style="margin-bottom:20px;"><h4 style="font-size:14px;font-weight:600;margin-bottom:8px;"><i class="fas fa-syringe" style="color:var(--primary-color);"></i> Procedures Performed</h4>
        <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="background:var(--gray-50);">
              <th style="padding:8px 10px;text-align:left;">#</th>
              <th style="padding:8px 10px;text-align:left;">Procedure</th>
              <th style="padding:8px 10px;text-align:left;">Performed By</th>
              <th class="hide-for-nurse" style="padding:8px 10px;text-align:right;">Amount</th>
              <th style="padding:8px 10px;text-align:left;">Invoice</th>
              <th style="padding:8px 10px;text-align:center;">Status</th>
              <th style="padding:8px 10px;text-align:center;">Action</th>
            </tr></thead><tbody>`;
        let total=0;
        procedures.forEach((p,i)=>{
          const amt=parseFloat(p.total||p.unit_price||0);
          total+=amt;
          html+=`<tr style="border-bottom:1px solid var(--gray-100);">
            <td style="padding:8px 10px;">${i+1}</td>
            <td style="padding:8px 10px;font-weight:500;">${esc(p.description||p.service_name||'—')}</td>
            <td style="padding:8px 10px;font-size:12px;color:var(--gray-600);"><i class="fas fa-user-nurse" style="margin-right:4px;color:var(--gray-400);"></i>${esc(p.created_by_name||'—')}<br><span style="font-size:10px;color:var(--gray-400);">${p.date?new Date(p.date).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):"—"}</span></td>
            <td class="hide-for-nurse" style="padding:8px 10px;text-align:right;font-weight:600;">${amt.toLocaleString()} RWF</td>
            <td class="hide-for-nurse" style="padding:8px 10px;font-size:11px;font-family:monospace;">${esc(p.invoice_no||'—')}</td>
            <td style="padding:8px 10px;text-align:center;"><span style="padding:2px 6px;border-radius:8px;font-size:10px;font-weight:600;background:${p.inv_status==='paid'?'#d1fae5':'#fef3c7'};color:${p.inv_status==='paid'?'#065f46':'#92400e'};">${p.inv_status||'issued'}</span></td>
            <td style="padding:8px 10px;text-align:center;"><button onclick="procDeleteDirect('procedure',${p.id},'${esc(p.description||p.service_name||'Procedure').replace(/'/g,"\\'")}',${visitId})" style="padding:3px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:10px;cursor:pointer;" title="Delete Procedure"><i class="fas fa-trash"></i></button></td>
          </tr>`;
        });
        html+=`</tbody><tfoot><tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);">
          <td colspan="3" style="padding:8px 10px;font-weight:700;">Total</td>
          <td class="hide-for-nurse" style="padding:8px 10px;text-align:right;font-weight:700;">${total.toLocaleString()} RWF</td>
          <td colspan="3"></td>
        </tr></tfoot></table></div></div>`;
      }
    }
  }catch(e){}

  // Load ALL Invoices with all items (full breakdown)
  if(!procIsNurse) try{
    const r=await fetch(procBillAPI+'&with_items=1&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      html+=`<div style="margin-bottom:20px;"><h4 style="font-size:14px;font-weight:600;margin-bottom:8px;"><i class="fas fa-file-invoice-dollar" style="color:#d97706;"></i> All Invoices</h4>`;
      let grandTotal=0;
      for(const inv of j.data){
        let items=[];
        try{const ir={json:async()=>({success:true,data:inv})}; /* items came with the list (with_items=1) */const ij=await ir.json();if(ij.success&&ij.data&&ij.data.items)items=ij.data.items;}catch(e){}
        const invTotal=parseFloat(inv.total||0);
        const invBalance=parseFloat(inv.balance||0);
        grandTotal+=invTotal;
        const stBg=inv.status==='paid'?'#d1fae5':inv.status==='partial'?'#dbeafe':'#fef3c7';
        const stColor=inv.status==='paid'?'#065f46':inv.status==='partial'?'#1e40af':'#92400e';
        html+=`<div style="border:1px solid var(--gray-200);border-radius:8px;margin-bottom:10px;overflow:hidden;">
          <div style="padding:10px 14px;background:var(--gray-50);display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--gray-200);">
            <div><span style="font-weight:600;font-family:monospace;color:var(--primary-color);">${esc(inv.invoice_no)}</span> <span style="padding:2px 6px;border-radius:8px;font-size:10px;font-weight:600;background:${stBg};color:${stColor};">${(inv.status||'issued').toUpperCase()}</span> <span style="font-size:10px;color:var(--gray-400);margin-left:6px;">${inv.payment_type||''}</span></div>
            <div style="font-weight:700;color:${invBalance>0?'#dc2626':'#059669'};">${invBalance>0?invBalance.toLocaleString()+' RWF due':invTotal.toLocaleString()+' RWF'}</div>
          </div>
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="border-bottom:1px solid var(--gray-200);">
              <th style="padding:6px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Description</th>
              <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Category</th>
              <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Qty</th>
              <th style="padding:6px 10px;text-align:right;font-weight:600;color:var(--gray-600);">Amount</th>
              <th style="padding:6px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Action</th>
            </tr></thead>
            <tbody>${items.map(it=>{
              const catIcon=it.category==='procedure'?'🏥':it.category==='lab'||it.category==='radiology'?'🧪':it.category==='pharmacy'||it.category==='medication'?'💊':it.category==='consultation'?'🩺':'📋';
              const amt=parseFloat(it.total||0);
              return `<tr style="border-bottom:1px solid var(--gray-100);">
                <td style="padding:6px 10px;font-weight:500;">${esc(it.description||'—')}</td>
                <td style="padding:6px 10px;text-align:center;"><span style="font-size:10px;">${catIcon} ${esc(it.category||'')}</span></td>
                <td style="padding:6px 10px;text-align:center;">${it.quantity||1}</td>
                <td style="padding:6px 10px;text-align:right;font-weight:600;">${amt.toLocaleString()} RWF</td>
                <td style="padding:6px 10px;text-align:center;"><button onclick="procDeleteDirect('item',${it.id},'${esc(it.description||'Item').replace(/'/g,"\\'")}',${visitId})" style="padding:3px 8px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:4px;font-size:10px;cursor:pointer;" title="Delete Item"><i class="fas fa-trash"></i></button></td>
              </tr>`;
            }).join('')}</tbody>
          </table>
        </div>`;
      }
      html+=`<div style="text-align:right;font-size:14px;font-weight:700;color:var(--gray-900);padding:8px 0;">Grand Total: ${grandTotal.toLocaleString()} RWF</div></div>`;
    }
  }catch(e){}

  content.innerHTML=html;
}

function procBackToList(){
  document.getElementById('proc-full-page').style.display='none';
  document.querySelector('[style*="background:white"][style*="box-shadow"]').style.display='block';
}

async function procShowDetails(visitId, patientId, patientName){
  const modal=document.getElementById('proc-details-modal');
  const content=document.getElementById('proc-details-content');
  content.innerHTML='<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i> Loading...</div>';
  modal.style.display='flex';

  function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

  let html=`<div style="margin-bottom:16px;background:var(--gray-50);border-radius:8px;padding:12px 16px;">
    <span style="font-size:16px;font-weight:700;color:var(--gray-900);">${esc(patientName)}</span>
  </div>`;

  // Patient Info
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=patients/patients&id='+patientId);
    const j=await r.json();
    if(j.success&&j.data){
      const p=j.data;
      html+=`<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-user" style="color:var(--primary-color);"></i> Patient Information</h4>
        <div style="background:var(--gray-50);border-radius:6px;padding:12px 14px;border:1px solid var(--gray-200);font-size:13px;display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;">
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Full Name:</span> ${esc((p.first_name||'')+' '+(p.last_name||''))}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Patient No:</span> ${esc(p.patient_no||'')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Gender:</span> ${esc(p.gender||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">DOB:</span> ${esc(p.date_of_birth||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Phone:</span> ${esc(p.phone||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Address:</span> ${esc(p.address||'—')}</div>
          ${p.allergies?'<div style="padding:4px 0;grid-column:1/-1;"><span style="font-weight:600;color:#dc2626;">Allergies:</span> '+esc(p.allergies)+'</div>':''}
          ${p.chronic_diseases?'<div style="padding:4px 0;grid-column:1/-1;"><span style="font-weight:600;color:#d97706;">Chronic Diseases:</span> '+esc(p.chronic_diseases)+'</div>':''}
        </div>
      </div>`;
    }
  }catch(e){}

  // Vital Signs
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=settings/vital-signs&action=records&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      html+=`<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-heartbeat" style="color:#dc2626;"></i> Vital Signs</h4>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
          ${j.data.map(v=>'<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:8px 12px;min-width:100px;"><div style="font-size:11px;color:var(--gray-500);">'+(v.sign_name||'Vital')+'</div><div style="font-size:14px;font-weight:700;color:var(--gray-900);">'+(v.value||'—')+' '+(v.unit||'')+'</div></div>').join('')}
        </div>
      </div>`;
    }
  }catch(e){}

  // Consultation Data
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=consultation/consult&action=consultation&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      for(const c of j.data){
        html+=`<div style="margin-bottom:16px;">
          <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-stethoscope" style="color:var(--primary-color);"></i> Consultation${c.department_name?' — '+esc(c.department_name):''}</h4>
          <div style="background:var(--gray-50);border-radius:6px;padding:12px 14px;border:1px solid var(--gray-200);font-size:13px;">
            ${c.chief_complaint?'<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);">Chief Complaint:</span> '+esc(c.chief_complaint)+'</div>':''}
            ${c.history?'<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);">History:</span> '+esc(c.history)+'</div>':''}
            ${c.examination?'<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);">Examination:</span> '+esc(c.examination)+'</div>':''}
            ${c.diagnosis?'<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:#7c3aed;">Diagnosis:</span> '+esc(c.diagnosis)+'</div>':''}
            ${c.differential_diagnosis?'<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:#d97706;">Differential Diagnosis:</span> '+esc(c.differential_diagnosis)+'</div>':''}
            ${c.treatment_plan?'<div style="padding:5px 0;"><span style="font-weight:600;color:var(--gray-600);">Treatment Plan:</span> '+esc(c.treatment_plan)+'</div>':''}
          </div>
        </div>`;
      }
    }
  }catch(e){}

  // Prescriptions
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=pharmacy/prescriptions&visit_id='+visitId);
    const j=await r.json();
    const rxList=j.data?.prescriptions||j.data||[];
    if(j.success&&rxList.length){
      html+=`<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-prescription" style="color:#16a34a;"></i> Prescriptions</h4>`;
      for(const rx of rxList){
        const prescriber = rx.created_by_name || rx.prescribed_by_name || (rx.doc_first ? rx.doc_first+' '+rx.doc_last : '—');
        html+=`<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:10px 14px;margin-bottom:8px;font-size:12px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
            <div style="font-weight:600;">Rx #${esc(rx.prescription_no||rx.id)} — <span style="color:${rx.status==='dispensed_full'?'#2563eb':rx.status==='active'?'#16a34a':'#d97706'}">${esc(rx.status||'pending')}</span></div>
            <div style="font-size:11px;color:var(--gray-600);"><i class="fas fa-user-nurse" style="margin-right:3px;color:var(--gray-400);"></i>${esc(prescriber)}</div>
          </div>`;
        const items=rx.items||[];
        if(items.length){
          html+=`<table style="width:100%;font-size:11px;border-collapse:collapse;"><thead><tr style="border-bottom:1px solid var(--gray-200);"><th style="text-align:left;padding:3px;">Medication</th><th style="padding:3px;">Dose</th><th style="padding:3px;">Freq</th><th style="padding:3px;">Duration</th></tr></thead><tbody>`;
          items.forEach(it=>{
            html+=`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:3px;">${esc(it.medication_name||it.item_name||'')}</td><td style="padding:3px;text-align:center;">${esc(it.dosage||'')}</td><td style="padding:3px;text-align:center;">${esc(it.frequency||'')}</td><td style="padding:3px;text-align:center;">${esc(it.duration||'')}</td></tr>`;
          });
          html+=`</tbody></table>`;
        }
        html+=`</div>`;
      }
      html+=`</div>`;
    }
  }catch(e){}

  // Lab Orders
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=consultation/consult&action=lab_orders&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      html+=`<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-flask" style="color:#2563eb;"></i> Lab Orders</h4>
        <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:10px 14px;font-size:12px;">`;
      j.data.forEach(lab=>{
        html+=`<div style="padding:4px 0;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;">
          <span>${esc(lab.test_name||lab.description||'Lab Test')}</span>
          <span style="font-weight:600;color:${lab.status==='completed'?'#059669':'#d97706'};">${esc(lab.status||'pending')}</span>
        </div>`;
      });
      html+=`</div></div>`;
    }
  }catch(e){}

  // Invoices
  if(!procIsNurse) try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=patients/billing&visit_id='+visitId);
    const j=await r.json();
    const invoices=j.data?.invoices||j.data||[];
    if(j.success&&invoices.length){
      html+=`<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-file-invoice-dollar" style="color:#d97706;"></i> Invoices</h4>`;
      invoices.forEach(inv=>{
        html+=`<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:10px 14px;margin-bottom:6px;font-size:12px;display:flex;justify-content:space-between;align-items:center;">
          <div><span style="font-weight:600;">${esc(inv.invoice_no||'')}</span> <span style="color:var(--gray-500);margin-left:6px;">${esc(inv.created_at||'')}</span></div>
          <div class="hide-for-nurse" style="font-weight:700;color:var(--primary-color);">${parseFloat(inv.total_amount||0).toLocaleString()} RWF</div>
        </div>`;
      });
      html+=`</div>`;
    }
  }catch(e){}

  content.innerHTML=html||'<p style="text-align:center;color:var(--gray-400);padding:20px;">No details available.</p>';
}

// ========== MEDICATION / CONSUMABLES (type-to-search, one card each) ==========
// Medication = items of category "Medications" (the is_medication flag is not reliable); consumables = nursing / medical / dental / lab supplies.
const PROC_CONSUMABLE_CATS = ['nursing supplies','medical consumables','dental supplies','laboratory supplies'];
let procItems = null;   // id -> {id, code, name, price, kind}
async function procLoadMeds(){
  if(procItems) return;
  procItems = {};
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=inventory/inventory-management&action=items&active=1&limit=500');
    const j=await r.json();
    (j.success ? j.data||[] : []).forEach(i=>{
      const cat=String(i.category_name||'').toLowerCase();
      const kind = cat==='medications' || (!cat && i.is_medication==1) ? 'medication' : PROC_CONSUMABLE_CATS.includes(cat) ? 'consumable' : null;
      if(kind) procItems[i.id]={id:i.id, code:i.item_code||'', name:i.name, price:parseFloat(i.selling_price)||0, kind};
    });
  }catch(e){}
}
const procEsc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

function procRxRowHtml(kind, first){
  const lbl = t => first ? `<label style="font-size:11px;font-weight:500;">${t}</label>` : '';
  const inp = 'width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;box-sizing:border-box;';
  const med = kind==='medication';
  return `<div class="proc-rx-row" style="display:grid;grid-template-columns:${med?'2fr 1fr 1fr 1fr auto':'3fr 1fr auto'};gap:8px;margin-bottom:8px;align-items:end;">
    <div style="position:relative;">${lbl(med?'Medication':'Consumable')}
      <input class="proc-rx-search" autocomplete="off" placeholder="Type to search ${med?'medication':'consumable'}..." oninput="procRxSearch(this)" onfocus="procRxSearch(this)" style="${inp}">
      <input type="hidden" class="proc-rx-med">
      <div class="proc-rx-dd" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:30;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:220px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
      <div class="proc-rx-price-info" style="font-size:10px;margin-top:2px;color:var(--gray-500);"></div></div>
    ${med?`<div>${lbl('Dosage')}<input class="proc-rx-dose" placeholder="e.g. 500mg" style="${inp}"></div>
    <div>${lbl('Frequency')}<input class="proc-rx-freq" placeholder="e.g. 3x/day" style="${inp}"></div>`:''}
    <div>${lbl('Qty')}<input type="number" class="proc-rx-qty" min="1" value="1" style="${inp}"></div>
    <button onclick="this.closest('.proc-rx-row').remove()" style="padding:8px;background:#fee2e2;border:1px solid #fecaca;border-radius:6px;cursor:pointer;color:#dc2626;"><i class="fas fa-times"></i></button>
  </div>`;
}

async function procOpenRx(visitId, patientId, kind='medication'){
  await Promise.all([procLoadMeds(), procLoadDoctors()]);
  const med = kind==='medication';
  const hasIns=procInsId&&(procPaymentType==='insurance'||procPaymentType==='mixed');
  const modal=document.createElement('div');modal.id='proc-rx-modal';modal.dataset.kind=kind;modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:650px;max-width:95vw;padding:24px;margin:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;"><h3 style="font-size:16px;font-weight:600;"><i class="fas ${med?'fa-prescription':'fa-box-open'}" style="color:${med?'#16a34a':'#b45309'};"></i> ${med?'Medication':'Consumables'}</h3><button onclick="document.getElementById('proc-rx-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    ${hasIns&&med&&!procIsNurse?`<div style="margin-bottom:12px;padding:10px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;"><label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;cursor:pointer;"><input type="checkbox" id="proc-rx-ins" checked> Apply Insurance Coverage (Patient pays ${procCopay}%)</label></div>`:''}
    <div id="proc-rx-items">${procRxRowHtml(kind,true)}</div>
    <button onclick="procAddRxRow()" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:11px;cursor:pointer;margin-bottom:12px;"><i class="fas fa-plus"></i> Add ${med?'Medication':'Consumable'}</button>
    ${procDoctorField('proc-rx')}
    <div><label style="font-size:11px;font-weight:500;">Notes</label><input id="proc-rx-notes" placeholder="Instructions..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;margin-bottom:12px;box-sizing:border-box;"></div>
    <button id="proc-rx-save" onclick="procSubmitRx(${visitId},${patientId})" style="width:100%;padding:10px;background:${med?'#16a34a':'#b45309'};color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-check-circle"></i> ${procIsNurse||!med?'Record & Bill':'Prescribe & Generate Invoice'}</button>
  </div>`;
  document.body.appendChild(modal);
  modal.querySelector('.proc-rx-search').focus();
}

function procAddRxRow(){
  document.getElementById('proc-rx-items').insertAdjacentHTML('beforeend',procRxRowHtml(document.getElementById('proc-rx-modal').dataset.kind,false));
  const rows=document.querySelectorAll('#proc-rx-items .proc-rx-search'); rows[rows.length-1].focus();
}

// Suggestions while typing (name or code), like the procedure search
function procRxSearch(inp){
  const row=inp.closest('.proc-rx-row'), dd=row.querySelector('.proc-rx-dd'), kind=document.getElementById('proc-rx-modal').dataset.kind;
  row.querySelector('.proc-rx-med').value=''; row.querySelector('.proc-rx-price-info').innerHTML='';
  const q=inp.value.trim().toLowerCase();
  const list=Object.values(procItems||{}).filter(i=>i.kind===kind && (!q || i.name.toLowerCase().includes(q) || i.code.toLowerCase().includes(q)))
    .sort((a,b)=>(b.name.toLowerCase().startsWith(q))-(a.name.toLowerCase().startsWith(q)) || a.name.localeCompare(b.name)).slice(0,20);
  dd.innerHTML=list.map(i=>`<div onmousedown="procRxPick(this,${i.id})" style="padding:7px 10px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''">
      <div style="display:flex;justify-content:space-between;gap:8px;"><span style="font-weight:500;">${procEsc(i.name)}</span>${procIsNurse?'':`<span style="color:var(--primary-color);font-weight:600;white-space:nowrap;">${i.price.toLocaleString()} RWF</span>`}</div>
      ${i.code?`<div style="font-size:10px;color:var(--gray-500);">${procEsc(i.code)}</div>`:''}</div>`).join('')
    || `<div style="padding:8px 10px;font-size:12px;color:var(--gray-400);">No ${kind} found</div>`;
  dd.style.display='block';
}
function procRxPick(el,id){
  const row=el.closest('.proc-rx-row'), it=procItems[id];
  row.querySelector('.proc-rx-search').value=it.name;
  row.querySelector('.proc-rx-med').value=id;
  row.querySelector('.proc-rx-dd').style.display='none';
  procRxShowPrice(row.querySelector('.proc-rx-med'));
}
document.addEventListener('click',e=>{ if(!e.target.closest('.proc-rx-search')) document.querySelectorAll('.proc-rx-dd').forEach(x=>x.style.display='none'); });

// Drug insurance pricing
// is_exceptional=1: patient pays = price * copay% + patient_topup, insurance pays = price * coverage%
// is_exceptional=0: patient pays = price * copay%, insurance pays = price * coverage%
// selling_price is NEVER used — only item_insurance_prices.price matters
function procGetDrugPrice(itemId){
  const sellingPrice=procItems?.[itemId]?.price||0;

  if(!procInsId||!procDrugInsPrices.length||!(procPaymentType==='insurance'||procPaymentType==='mixed')){
    return {total:sellingPrice,patientPays:sellingPrice,insurancePays:0,topup:0,insPrice:0,isExceptional:false,note:'Self-pay'};
  }
  const insRow=procDrugInsPrices.find(p=>p.item_id==itemId);
  if(!insRow) return {total:sellingPrice,patientPays:sellingPrice,insurancePays:0,topup:0,insPrice:0,isExceptional:false,note:'Not in formulary — patient pays full price'};

  const price        = parseFloat(insRow.price)||0;
  const patientTopup = 0; // top-ups removed
  const isExceptional= parseInt(insRow.is_exceptional)||0;
  const copayRate    = procCopay/100;
  const coverRate    = 1 - copayRate;

  const insurancePays = Math.round(price * coverRate);
  const patientCopay  = price - insurancePays;

  if(isExceptional){
    return {
      total: price + patientTopup,
      patientPays: patientCopay + patientTopup,
      insurancePays,
      topup: patientTopup,
      insPrice: price,
      isExceptional: true,
      note: 'Exceptional'
    };
  } else {
    return {
      total: price,
      patientPays: patientCopay,
      insurancePays,
      topup: 0,
      insPrice: price,
      isExceptional: false,
      note: 'Covered'
    };
  }
}

function procRxShowPrice(sel){
  const info=sel.parentElement.querySelector('.proc-rx-price-info');
  if(procIsNurse||!sel.value){info.innerHTML='';return;}
  const p=procGetDrugPrice(sel.value);
  const hasIns=procInsId&&(procPaymentType==='insurance'||procPaymentType==='mixed');
  if(!hasIns){
    info.innerHTML=`<span style="color:var(--gray-500);">Price: <strong>${p.total.toLocaleString()} RWF</strong></span>`;
  } else if(p.note.startsWith('Not in')){
    info.innerHTML=`<span style="color:#dc2626;"><i class="fas fa-times-circle"></i> Not in formulary — Patient pays: <strong>${p.patientPays.toLocaleString()} RWF</strong></span>`;
  } else if(p.isExceptional){
    info.innerHTML=`<span style="color:#7c3aed;"><i class="fas fa-star"></i> Exceptional — Ins. price: ${p.insPrice.toLocaleString()} | Insurance pays: <strong>${p.insurancePays.toLocaleString()}</strong> | Patient pays: <strong>${p.patientPays.toLocaleString()} RWF</strong> (incl. topup ${p.topup.toLocaleString()} RWF)</span>`;
  } else {
    info.innerHTML=`<span style="color:#059669;"><i class="fas fa-check-circle"></i> Ins. price: ${p.insPrice.toLocaleString()} | Insurance pays: <strong>${p.insurancePays.toLocaleString()}</strong> | Patient pays: <strong>${p.patientPays.toLocaleString()} RWF</strong></span>`;
  }
}

// "Name dose xQty" per selected row, for the doctor's file
function procRxSummary(){
  const out=[];
  document.querySelectorAll('.proc-rx-row').forEach(row=>{
    const id=row.querySelector('.proc-rx-med').value;
    if(!id) return;
    const dose=row.querySelector('.proc-rx-dose')?.value.trim();
    out.push(procItems[id].name+(dose?' '+dose:'')+' x'+(parseInt(row.querySelector('.proc-rx-qty').value)||1));
  });
  return out.join(', ');
}

function procRxBusy(on){
  const btn=document.getElementById('proc-rx-save'); if(!btn) return;
  if(on){ btn.dataset.html=btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...'; }
  else { btn.disabled=false; if(btn.dataset.html) btn.innerHTML=btn.dataset.html; }
}

async function procSubmitRx(visitId, patientId){
  const rxDoctorId=procGetDoctor('proc-rx');   // optional: the nurse's work is recorded either way
  const kind=document.getElementById('proc-rx-modal').dataset.kind;
  if([...document.querySelectorAll('.proc-rx-row')].some(r=>r.querySelector('.proc-rx-search').value.trim() && !r.querySelector('.proc-rx-med').value))
    return alert('Pick each item from the suggestions list (or clear the row).');
  if(procIsNurse || kind==='consumable') return procNurseUse(visitId,patientId,rxDoctorId,kind);
  const rows=document.querySelectorAll('.proc-rx-row');
  const items=[],invoiceItems=[];
  const applyIns=document.getElementById('proc-rx-ins')?.checked&&(procPaymentType==='insurance'||procPaymentType==='mixed');
  rows.forEach(row=>{
    const id=row.querySelector('.proc-rx-med').value;
    const qty=parseInt(row.querySelector('.proc-rx-qty').value)||1;
    if(id){
      const name=procItems[id].name, price=procItems[id].price;
      const pricing=applyIns?procGetDrugPrice(id):{total:price,patientPays:price,topup:0};
      items.push({item_id:id,medication:name,dosage:row.querySelector('.proc-rx-dose').value,frequency:row.querySelector('.proc-rx-freq').value,duration:'',quantity:qty});
      invoiceItems.push({
        description:'Medication — '+name+(pricing.topup>0?' (Topup: '+pricing.topup.toLocaleString()+' RWF)':''),
        category:'pharmacy',quantity:qty,unit_price:pricing.total,discount:0,service_id:parseInt(id)
      });
    }
  });
  if(!items.length) return alert('Add at least one medication.');
  // Duplicate guard
  const rxDescs = invoiceItems.map(it => it.description);
  if(!_procCheckDups(rxDescs)) return;
  procRxBusy(true);
  try{
    // 1. Create prescription
    await fetch(window.location.origin + _procBase + '/api.php?endpoint=consultation/consult',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'pharmacy_order',visit_id:visitId,patient_id:patientId,department_id:0,items:items,notes:document.getElementById('proc-rx-notes').value,created_by:procUserId})});
    // 2. Generate invoice with insurance split
    const invRes=await fetch(procBillAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({visit_id:visitId,patient_id:patientId,payment_type:applyIns?procPaymentType:'self_pay',insurance_id:applyIns?(window._procInsCompanyId||procInsId):null,copayment_percentage:applyIns?procCopay:100,notes:'Emergency Prescription',created_by:procUserId,items:invoiceItems})});
    const invJson=await invRes.json();
    if(invJson.success){ _procMarkSubmitted(rxDescs); await procLinkDoctor('prescription',visitId,patientId,rxDoctorId,procRxSummary()); alert('✓ Prescription sent & Invoice '+(invJson.data.invoice_no||'')+' generated!'); }
    else alert('Prescription sent but invoice failed: '+(invJson.message||''));
    document.getElementById('proc-rx-modal').remove();
  }catch(e){alert('Error: '+e.message);}
  finally{ procRxBusy(false); }
}

// Nurse medication / any consumables: server deducts stock (IPD/OPD store), bills the patient and records it in the
// patient file (who did it, optional doctor) in ONE request. No pharmacy prescription.
async function procNurseUse(visitId,patientId,doctorId,kind){
  const items=[];
  document.querySelectorAll('.proc-rx-row').forEach(row=>{
    const id=row.querySelector('.proc-rx-med').value;
    if(id) items.push({item_id:id,quantity:parseInt(row.querySelector('.proc-rx-qty').value)||1,kind:procItems[id].kind});
  });
  if(!items.length) return alert('Add at least one item.');
  procRxBusy(true);
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=procedures/procedures&action=nurse_use',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({visit_id:visitId,patient_id:patientId,items,doctor_id:doctorId||null,kind:kind==='consumable'?'consumable':'prescription',summary:procRxSummary(),notes:document.getElementById('proc-rx-notes')?.value.trim()||''})});
    const j=await r.json();
    if(!j.success) return alert('Error: '+(j.message||''));
    alert('✓ Recorded. Stock deducted, invoice '+(j.data.invoice_no||'')+' generated.');
    document.getElementById('proc-rx-modal').remove();
  }catch(e){alert('Error: '+e.message);}
  finally{ procRxBusy(false); }
}

// ========== DOCTOR PICKER (optional in procedure / medication / consumables / sample modals) ==========
const procHandlerAPI = window.location.origin + _procBase + '/api.php?endpoint=procedures/procedures';
let procDoctors = null;
async function procLoadDoctors(){
  if(procDoctors) return;
  try{ const j=await (await fetch(procHandlerAPI+'&action=doctors')).json(); procDoctors=j.success?(j.data||[]):[]; }
  catch(e){ procDoctors=[]; }
}
function procDoctorField(pfx){
  return `<div style="position:relative;margin-bottom:12px;">
    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Doctor <span style="font-weight:400;color:var(--gray-400);">(optional: it is saved in the patient file with your name either way)</span></label>
    <input id="${pfx}-doctor-search" type="text" autocomplete="off" placeholder="Type doctor name..." oninput="procDoctorSearch('${pfx}')" onfocus="procDoctorSearch('${pfx}')" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    <input type="hidden" id="${pfx}-doctor-id">
    <div id="${pfx}-doctor-dd" style="display:none;position:absolute;left:0;right:0;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:20;"></div>
  </div>`;
}
function procDoctorSearch(pfx){
  const q=document.getElementById(pfx+'-doctor-search').value.trim().toLowerCase();
  document.getElementById(pfx+'-doctor-id').value=''; // must pick from the list
  const dd=document.getElementById(pfx+'-doctor-dd');
  const list=(procDoctors||[]).filter(d=>d.name.toLowerCase().includes(q)).slice(0,10);
  dd.innerHTML=list.map(d=>`<div onmousedown="procDoctorPick('${pfx}',${d.id})" style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--gray-100);">${String(d.name).replace(/&/g,'&amp;').replace(/</g,'&lt;')}</div>`).join('')||'<div style="padding:8px 12px;font-size:12px;color:var(--gray-400);">No doctor found</div>';
  dd.style.display='block';
}
function procDoctorPick(pfx,id){
  const d=procDoctors.find(x=>x.id==id); if(!d) return;
  document.getElementById(pfx+'-doctor-search').value=d.name;
  document.getElementById(pfx+'-doctor-id').value=d.id;
  document.getElementById(pfx+'-doctor-dd').style.display='none';
}
document.addEventListener('click',e=>{ if(!e.target.closest('[id$="-doctor-search"]')) document.querySelectorAll('[id$="-doctor-dd"]').forEach(x=>x.style.display='none'); });
function procGetDoctor(pfx){ return parseInt(document.getElementById(pfx+'-doctor-id')?.value)||0; }
// Records what the nurse did in the patient file (doctor's Results / Medication tabs), with the nurse's name and the doctor if one was chosen
async function procLinkDoctor(kind,visitId,patientId,doctorId,summary){
  try{
    const r=await fetch(procHandlerAPI+'&action=link_doctor',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({kind,visit_id:visitId,patient_id:patientId,doctor_id:doctorId||null,summary})});
    const j=await r.json();
    if(!j.success) alert('Saved, but recording it in the patient file failed: '+(j.message||''));
  }catch(e){ alert('Saved, but recording it in the patient file failed: '+e.message); }
}

// ========== SAMPLE (replaces Lab Test order; nurse records a collected sample for a doctor) ==========
const procLabSpecialties = ['bacteriology','biochemistry','hematology','immuno-serology','parasitology','molecular','anatomical pathology','blood transfusion'];

async function procOpenSample(visitId, patientId){
  await procLoadDoctors();
  const modal=document.createElement('div');modal.id='proc-sample-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:520px;max-width:95vw;padding:24px;margin:auto;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;"><h3 style="font-size:16px;font-weight:600;"><i class="fas fa-vial" style="color:#2563eb;"></i> Record Sample</h3><button onclick="document.getElementById('proc-sample-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Sample type *</label>
      <select id="proc-sample-type" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Select...</option>${['Blood','Urine','Stool','Swab'].map(t=>`<option>${t}</option>`).join('')}</select></div>
    <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Collected at</label>
      <input id="proc-sample-time" type="datetime-local" value="${new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,16)}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Notes</label>
      <input id="proc-sample-notes" placeholder="e.g. fasting, tests requested..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    ${procDoctorField('proc-sample')}
    <button onclick="procSubmitSample(${visitId},${patientId})" style="width:100%;padding:10px;background:#2563eb;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-vial"></i> Save Sample</button>
  </div>`;
  document.body.appendChild(modal);
}

async function procSubmitSample(visitId, patientId){
  const type=document.getElementById('proc-sample-type').value;
  const doctorId=procGetDoctor('proc-sample');
  if(!type) return alert('Select the sample type.');
  const notes=document.getElementById('proc-sample-notes').value.trim();
  const time=document.getElementById('proc-sample-time').value.replace('T',' ');
  const btn=document.querySelector('#proc-sample-modal button[onclick^="procSubmitSample"]');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';
  await procLinkDoctor('sample',visitId,patientId,doctorId,type+' sample'+(time?' collected '+time:'')+(notes?' - '+notes:''));
  alert('Sample recorded in the patient file.');
  document.getElementById('proc-sample-modal').remove();
}

// ========== NOTIFICATIONS ==========
const notifAPI = _procBase + '/api.php?endpoint=notifications';
let notifDropdownOpen = false;
let notifLastUnread = 0;
let notifSoundPlayed = false; // tracks if initial 5s sound was played for current batch
let notifReminderInterval = null;

// --- Sound Mute Toggle (persisted in localStorage) ---
function notifIsMuted(){ return localStorage.getItem('medlink_notif_muted') === '1'; }
function notifUpdateMuteBtn(){
  const btn = document.getElementById('notif-mute-btn');
  if(!btn) return;
  const muted = notifIsMuted();
  btn.classList.toggle('muted', muted);
  btn.querySelector('i').className = muted ? 'fas fa-volume-mute' : 'fas fa-volume-up';
  btn.title = muted ? 'Unmute notification sounds' : 'Mute notification sounds';
}
window.notifToggleMute = function(){
  const muted = notifIsMuted();
  localStorage.setItem('medlink_notif_muted', muted ? '0' : '1');
  notifUpdateMuteBtn();
  if(!muted) notifStopReminder();
};
document.addEventListener('DOMContentLoaded', notifUpdateMuteBtn);

// --- Web Audio API Sound Generator ---
const notifAudioCtx = new (window.AudioContext || window.webkitAudioContext)();

function notifPlayTone(duration, frequency, type='sine', volume=0.4){
  if(notifIsMuted()) return;
  // Resume audio context (required after user gesture)
  if(notifAudioCtx.state === 'suspended') notifAudioCtx.resume();
  const osc = notifAudioCtx.createOscillator();
  const gain = notifAudioCtx.createGain();
  osc.type = type;
  osc.frequency.value = frequency;
  gain.gain.value = volume;
  osc.connect(gain);
  gain.connect(notifAudioCtx.destination);
  osc.start();
  // Fade out at end
  gain.gain.setValueAtTime(volume, notifAudioCtx.currentTime + duration - 0.1);
  gain.gain.linearRampToValueAtTime(0, notifAudioCtx.currentTime + duration);
  osc.stop(notifAudioCtx.currentTime + duration);
}

// 5-second alert sound (repeating chime pattern)
function notifPlayAlertLong(){
  const notes = [880, 1100, 880, 1100, 880, 1100, 880, 1100, 880, 1100]; // 10 chimes over 5s
  notes.forEach((freq, i) => {
    setTimeout(() => notifPlayTone(0.3, freq, 'sine', 0.35), i * 500);
  });
}

// 1-second short reminder beep
function notifPlayReminderShort(){
  notifPlayTone(0.15, 900, 'sine', 0.3);
  setTimeout(() => notifPlayTone(0.15, 1100, 'sine', 0.3), 200);
}

// Start reminder: every 60 seconds play short beep if still unread
function notifStartReminder(){
  notifStopReminder();
  notifReminderInterval = setInterval(() => {
    if(notifLastUnread > 0){
      notifPlayReminderShort();
    } else {
      notifStopReminder();
    }
  }, 60000); // every 60 seconds
}

function notifStopReminder(){
  if(notifReminderInterval){
    clearInterval(notifReminderInterval);
    notifReminderInterval = null;
  }
}

function notifToggleDropdown(){
  const dd = document.getElementById('notif-dropdown');
  notifDropdownOpen = !notifDropdownOpen;
  dd.classList.toggle('show', notifDropdownOpen);
  if(notifDropdownOpen) notifLoadList();
}

// Close dropdown on outside click
document.addEventListener('click', function(e){
  const wrap = document.getElementById('notif-bell-wrap');
  if(wrap && !wrap.contains(e.target)){
    document.getElementById('notif-dropdown').classList.remove('show');
    notifDropdownOpen = false;
  }
});

function notifTimeAgo(dateStr){
  const now = new Date();
  const d = new Date(dateStr);
  const diff = Math.floor((now - d) / 1000);
  if(diff < 60) return 'Just now';
  if(diff < 3600) return Math.floor(diff/60) + 'm ago';
  if(diff < 86400) return Math.floor(diff/3600) + 'h ago';
  return Math.floor(diff/86400) + 'd ago';
}

async function notifLoadList(){
  try{
    const r = await fetch(notifAPI + '&target_role=nurse&limit=30');
    const j = await r.json();
    if(!j.success) return;
    const notifs = j.data.notifications || [];
    const unread = j.data.unread_count || 0;
    
    // Update badge
    const badge = document.getElementById('notif-count');
    if(unread > 0){
      badge.textContent = unread > 99 ? '99+' : unread;
      badge.style.display = 'flex';
    } else {
      badge.style.display = 'none';
    }
    
    notifLastUnread = unread;
    
    // Render list
    const container = document.getElementById('notif-list');
    if(!notifs.length){
      container.innerHTML = '<div class="notif-empty"><i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:var(--gray-300);"></i>No notifications</div>';
      return;
    }
    container.innerHTML = notifs.map(n => {
      const isUnread = !n.is_read;
      return `<div class="notif-item ${isUnread ? 'unread' : ''}" id="notif-item-${n.id}">
        <div class="notif-title"><i class="fas fa-flask" style="color:#2563eb;margin-right:6px;"></i>${n.title || 'Notification'}</div>
        <div class="notif-msg">${n.message || ''}</div>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span class="notif-time">${notifTimeAgo(n.created_at)}</span>
          ${isUnread ? `<button class="notif-mark-btn" onclick="event.stopPropagation();notifMarkRead(${n.id})"><i class="fas fa-check"></i> Mark as read</button>` : '<span style="font-size:10px;color:#059669;"><i class="fas fa-check-double"></i> Read</span>'}
        </div>
        ${n.patient_name ? `<div style="font-size:11px;color:var(--gray-500);margin-top:4px;"><i class="fas fa-user"></i> ${n.patient_name} ${n.visit_no ? '('+n.visit_no+')' : ''}</div>` : ''}
      </div>`;
    }).join('');
  }catch(e){}
}

async function notifMarkRead(id){
  try{
    await fetch(notifAPI, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'mark_read', id: id})
    });
    const item = document.getElementById('notif-item-' + id);
    if(item) item.classList.remove('unread');
    notifLoadList();
  }catch(e){}
}

async function notifMarkAllRead(){
  try{
    await fetch(notifAPI, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'mark_all_read', target_role: 'nurse'})
    });
    notifLastUnread = 0;
    notifSoundPlayed = false;
    notifStopReminder();
    notifLoadList();
  }catch(e){}
}

// Nurses cannot delete: the removal is sent to the admin (Deletion Requests) and executes on approval
async function procNurseRequestDelete(entityType, entityId, entityName, visitId){
  const remarks = prompt('Remove "'+entityName+'"?\nAn administrator must approve this before it is removed.\n\nReason (at least 10 characters):');
  if(remarks === null) return;
  if(remarks.trim().length < 10) return alert('Please write a reason of at least 10 characters.');
  const fd = new FormData();
  fd.append('entity_type', entityType === 'lab' ? 'lab_order' : entityType === 'item' ? 'procedure' : entityType);
  fd.append('entity_id', entityId);
  fd.append('entity_name', entityName);
  fd.append('remarks', remarks.trim());
  if(visitId) fd.append('visit_id', visitId);
  try {
    const j = await (await fetch(window.location.origin + _procBase + '/api.php?endpoint=deletion-requests&action=create', {method:'POST', body:fd})).json();
    alert(j.success ? '✓ Removal request sent. The item stays until an administrator approves it.' : 'Error: ' + (j.message || 'Unknown error'));
  } catch(e) { alert('Failed to send request: ' + e.message); }
}

// ===== DIRECT DELETION (WITHOUT PERMISSION / APPROVAL) =====
async function procDeleteDirect(entityType, entityId, entityName, visitId){
  if(procIsNurse) return procNurseRequestDelete(entityType, entityId, entityName, visitId);
  if(!confirm(`Are you sure you want to delete "${entityName}"?\n\nThis will immediately remove the item and synchronize/recalculate all related invoices and accounting journal entries.`)){
    return;
  }
  try {
    const res = await fetch(window.location.origin + _procBase + '/api.php?endpoint=procedures/procedures&action=direct_delete', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        entity_type: entityType,
        entity_id: entityId,
        visit_id: visitId
      })
    });
    const j = await res.json();
    if(j.success){
      alert('✓ ' + (j.message || 'Item deleted successfully'));
      if(visitId) {
        const fullPage = document.getElementById('proc-full-page');
        if(fullPage && fullPage.style.display !== 'none'){
          procShowFullPage(visitId, window._procCurrentPatientId || 0, window._procCurrentPatientName || '');
        }
        const histModal = document.getElementById('proc-details-modal');
        if(histModal && histModal.style.display === 'flex'){
          procShowDetails(visitId, window._procCurrentPatientId || 0, window._procCurrentPatientName || '');
        }
      }
      procLoad();
    } else {
      alert('Error deleting item: ' + (j.message || 'Unknown error'));
    }
  } catch(e) {
    alert('Failed to delete item: ' + e.message);
  }
}

// Fallback alias for backward compatibility
const procRequestDelete = procDeleteDirect;
const procDelAPI = _procBase + '/api.php?endpoint=deletion-requests';
const procUserId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;


// Poll for notifications every 15 seconds
async function notifPoll(){
  try{
    const r = await fetch(notifAPI + '&target_role=nurse&is_read=0&limit=1');
    const j = await r.json();
    if(j.success){
      const unread = j.data.unread_count || 0;
      const badge = document.getElementById('notif-count');
      if(unread > 0){
        badge.textContent = unread > 99 ? '99+' : unread;
        badge.style.display = 'flex';
        
        // New unread detected — play 5s alert if not already played
        if(unread > notifLastUnread || (!notifSoundPlayed && unread > 0)){
          notifPlayAlertLong();
          notifSoundPlayed = true;
          // Start reminder cycle (1s beep every 60s)
          notifStartReminder();
        }
      } else {
        badge.style.display = 'none';
        notifSoundPlayed = false;
        notifStopReminder();
      }
      notifLastUnread = unread;
    }
  }catch(e){}
}

// Start polling
notifPoll();
setInterval(notifPoll, 15000);

procLoad();
// auto-refresh list so newly opened visits appear without leaving the page
setInterval(()=>{ if(!document.hidden && document.getElementById('proc-tbody')) procLoad(true); }, 15000);
(()=>{const q=new URLSearchParams(location.search);const v=q.get('visit_id'),p=q.get('patient_id');if(v&&p)procShowFullPage(+v,+p,q.get('name')||'');})();

// ========== PRINT VISIT PDF ==========
async function procPrintPDF(){
  const content=document.getElementById('proc-full-content');
  if(!content||!content.innerHTML.trim()) return alert('No visit loaded to print.');

  // Load hospital settings for paper layout
  let hs={};
  try{
    const r=await fetch(window.location.origin + _procBase + '/api.php?endpoint=settings/hospital');
    const j=await r.json();
    if(j.success) hs=j.data||{};
  }catch(e){}

  function pEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

  const docLogo=hs.doc_logo||hs.logo||'';
  const hLineColor=hs.header_line_color||'#2563eb';
  const hLineSize=hs.header_line_size||'2';
  const hTextColor=hs.header_text_color||'#1f2937';
  const hFontSize=hs.header_font_size||'11';
  const hLeftText=hs.header_left||hs.hospital_name||'Hospital';
  const today=new Date().toISOString().split('T')[0];
  const hRightText=(hs.header_right||'Date: {date}').replace('{date}',today);
  const fLineColor=hs.footer_line_color||'#2563eb';
  const fLineSize=hs.footer_line_size||'2';
  const fTextColor=hs.footer_text_color||'#1f2937';
  const fFontSize=hs.footer_font_size||'9';
  const fLeftText=hs.footer_left||(hs.address||'Kigali, Rwanda');
  const fRightText=hs.footer_right||(hs.phone?'Tel: '+hs.phone+' | ':'')+'Email: '+(hs.email||'');

  let wmHtml='';
  if(hs.watermark_enabled==='1'){
    const wmOpacity=(parseInt(hs.watermark_opacity)||15)/100;
    const wmRotation=hs.watermark_rotation||'-30';
    if(hs.watermark_type==='image'&&hs.watermark_image){
      wmHtml=`<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;"><img src="${hs.watermark_image}" style="width:150px;height:auto;"></div>`;
    }else{
      wmHtml=`<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;font-size:${hs.watermark_size||'40'}px;color:${hs.watermark_color||'#d1d5db'};font-weight:700;white-space:nowrap;">${pEsc(hs.watermark_text||hs.hospital_name||'')}</div>`;
    }
  }

  // Clone content and clean up buttons/actions
  const clone=content.cloneNode(true);
  clone.querySelectorAll('button').forEach(b=>b.remove());
  clone.querySelectorAll('[onclick]').forEach(el=>el.removeAttribute('onclick'));
  clone.querySelectorAll('[onmouseover]').forEach(el=>{el.removeAttribute('onmouseover');el.removeAttribute('onmouseout');});

  // Get patient name from the content
  const patientNameEl=clone.querySelector('h3');
  const patientName=patientNameEl?patientNameEl.textContent.trim():'Patient';

  const el=document.createElement('div');
  el.innerHTML=`<div style="font-family:Arial,sans-serif;font-size:12px;padding:20px;color:#333;width:100%;box-sizing:border-box;position:relative;overflow:hidden;">
    ${wmHtml}
    <div style="position:relative;z-index:1;">
      <!-- Header -->
      <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:6px;border-bottom:${hLineSize}px solid ${hLineColor};margin-bottom:15px;">
        <div style="display:flex;align-items:center;gap:8px;">
          ${docLogo?'<img src="'+docLogo+'" style="max-height:50px;width:auto;">':''}
          <span style="font-size:${hFontSize}px;font-weight:600;color:${hTextColor};">${pEsc(hLeftText)}</span>
        </div>
        <div style="font-size:${hFontSize}px;color:${hTextColor};text-align:right;">${pEsc(hRightText)}</div>
      </div>

      <div style="font-size:16px;font-weight:bold;text-align:center;margin:10px 0 15px;text-decoration:underline;">PATIENT VISIT REPORT</div>

      <!-- Content -->
      <div style="font-size:12px;line-height:1.5;">
        ${clone.innerHTML}
      </div>

      <!-- Footer -->
      <div style="margin-top:30px;padding-top:8px;border-top:${fLineSize}px solid ${fLineColor};display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${pEsc(fLeftText)}</span>
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${pEsc(fRightText)}</span>
      </div>
    </div>
  </div>`;

  const filename=patientName.replace(/[^a-zA-Z0-9 ]/g,'').replace(/\s+/g,'_')+'_visit_report_'+today+'.pdf';
  html2pdf().set({
    margin:[10,10,10,10],
    filename:filename,
    image:{type:'jpeg',quality:0.98},
    html2canvas:{scale:2,useCORS:true,width:750},
    jsPDF:{unit:'mm',format:'a4',orientation:'portrait'},
    pagebreak:{mode:['avoid-all','css','legacy']}
  }).from(el).save();
}
</script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>
