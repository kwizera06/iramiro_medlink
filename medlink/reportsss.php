<?php
// report.php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'general';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;">Reports</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Financial and operational reports</p>
  </div>
</div>

<!-- Report Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:white;padding:6px;border-radius:10px;box-shadow:var(--shadow);width:fit-content;">
  <button class="ins-tab <?php echo $subModule=='general'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=general'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='general'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-chart-bar"></i> General Report</button>
  <button class="ins-tab <?php echo $subModule=='visitors'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=visitors'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='visitors'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-users"></i> Visitors</button>
  <button class="ins-tab <?php echo $subModule=='consultations'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=consultations'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='consultations'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-user-md"></i> Doctor Sales</button>
  <button class="ins-tab <?php echo $subModule=='lab-sales'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=lab-sales'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='lab-sales'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-flask"></i> Lab Sales</button>
  <button class="ins-tab <?php echo $subModule=='procedure-sales'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=procedure-sales'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='procedure-sales'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-syringe"></i> Procedure Sales</button>
  <button class="ins-tab <?php echo $subModule=='drug-sales'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=drug-sales'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='drug-sales'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-pills"></i> Drug Sales</button>
  <button class="ins-tab <?php echo $subModule=='diagnosis'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=diagnosis'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='diagnosis'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-diagnoses"></i> Diagnosis</button>
  <button class="ins-tab <?php echo $subModule=='prescriptions'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=prescriptions'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='prescriptions'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-prescription"></i> Prescriptions</button>
  <button class="ins-tab <?php echo $subModule=='lab-analytics'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=lab-analytics'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='lab-analytics'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-microscope"></i> Lab Analytics</button>
  <button class="ins-tab <?php echo $subModule=='insurance-monthly'?'active':'';?>" onclick="location.href='report.php?module=reports&sub=insurance-monthly'" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;<?php echo $subModule=='insurance-monthly'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-shield-alt"></i> Insurance Monthly</button>
</div>

<?php if ($subModule == 'general'): ?>
<!-- ===== GENERAL REPORT ===== -->

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label>
    <input id="rpt-from" type="date" value="<?php echo date('Y-m-01'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label>
    <input id="rpt-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Insurance</label>
    <select id="rpt-ins" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;min-width:140px;">
      <option value="">All</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Category</label>
    <select id="rpt-cat" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All Categories</option>
      <option value="consultation">Consultation</option>
      <option value="pharmacy">Pharmacy</option>
      <option value="lab">Lab</option>
      <option value="procedure">Procedure</option>
      <option value="radiology">Radiology</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Payment</label>
    <select id="rpt-pay" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All</option>
      <option value="paid">Paid</option>
      <option value="unpaid">Unpaid</option>
      <option value="partial">Partial</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Method</label>
    <select id="rpt-method" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All Methods</option>
      <option value="cash">Cash</option>
      <option value="bank">Bank Transfer</option>
      <option value="mobile_money">Mobile Money</option>
      <option value="mtn_momo">MTN MoMo</option>
    </select>
  </div>
  <div style="margin-top:14px;">
    <button onclick="rptLoad(true)" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
  </div>
  <div style="margin-top:14px;margin-left:auto;">
    <button onclick="rptPrint()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;" title="Print"><i class="fas fa-print"></i></button>
  </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:16px;">
  <button id="rpt-tab-summary" onclick="rptSwitchTab('summary')" style="padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px 8px 0 0;font-size:13px;font-weight:600;cursor:pointer;"><i class="fas fa-chart-pie"></i> Summary</button>
  <button id="rpt-tab-details" onclick="rptSwitchTab('details')" style="padding:10px 20px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-bottom:none;border-radius:8px 8px 0 0;font-size:13px;font-weight:500;cursor:pointer;"><i class="fas fa-list"></i> Details</button>
</div>

<!-- SUMMARY TAB -->
<div id="rpt-summary-section">

<!-- Stats Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rpt-s-invoices" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Invoices</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rpt-s-total" style="font-size:18px;font-weight:700;color:var(--gray-800);">—</div><div style="font-size:11px;color:var(--gray-500);">Total Amount</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rpt-s-paid" style="font-size:18px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Paid by Patient</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rpt-s-balance" style="font-size:18px;font-weight:700;color:var(--danger-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Patient Balance</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rpt-s-insowes" style="font-size:18px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Insurance Owes</div></div>
</div>

<!-- Payment Status -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
  <span id="rpt-s-paidcount" style="padding:6px 14px;background:#d1fae5;color:#065f46;border-radius:20px;font-size:12px;font-weight:600;">— Paid</span>
  <span id="rpt-s-unpaidcount" style="padding:6px 14px;background:#fee2e2;color:#991b1b;border-radius:20px;font-size:12px;font-weight:600;">— Unpaid</span>
  <span id="rpt-s-partialcount" style="padding:6px 14px;background:#fef3c7;color:#92400e;border-radius:20px;font-size:12px;font-weight:600;">— Partial</span>
  <span id="rpt-s-inscount" style="padding:6px 14px;background:#dbeafe;color:#1e40af;border-radius:20px;font-size:12px;font-weight:600;">— Insurance</span>
  <span id="rpt-s-selfcount" style="padding:6px 14px;background:#f3e8ff;color:#6b21a8;border-radius:20px;font-size:12px;font-weight:600;">— Self-Pay</span>
</div>

<!-- Payment Methods Breakdown -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);padding:18px 20px;margin-bottom:16px;">
  <h4 style="font-size:14px;font-weight:600;color:var(--gray-700);margin-bottom:12px;"><i class="fas fa-money-bill-wave" style="color:var(--primary-color);margin-right:6px;"></i>Payment Methods</h4>
  <div id="rpt-pm-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;"></div>
</div>

<!-- Services Breakdown -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);padding:18px 20px;margin-bottom:16px;">
  <h4 style="font-size:14px;font-weight:600;color:var(--gray-700);margin-bottom:12px;"><i class="fas fa-th-list" style="color:#7c3aed;margin-right:6px;"></i>Services Breakdown</h4>
  <div id="rpt-svc-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;"></div>
</div>

</div><!-- end rpt-summary-section -->

<!-- DETAILS TAB -->
<div id="rpt-details-section" style="display:none;">

<!-- Report Table -->
<div id="rpt-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Visit No.</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Date</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Patient</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Insurance</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Method</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Total</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Paid</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Balance</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Ins. Owes</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Invoices</th>
    </tr></thead>
    <tbody id="rpt-tbody"><tr><td colspan="12" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
    <tfoot id="rpt-tfoot" style="display:none;">
      <tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);font-weight:700;">
        <td colspan="6" style="padding:10px 12px;">TOTALS</td>
        <td id="rpt-f-total" style="padding:10px 12px;text-align:right;">—</td>
        <td id="rpt-f-paid" style="padding:10px 12px;text-align:right;color:var(--success-color);">—</td>
        <td id="rpt-f-balance" style="padding:10px 12px;text-align:right;color:var(--danger-color);">—</td>
        <td id="rpt-f-insowes" style="padding:10px 12px;text-align:right;color:var(--warning-color);">—</td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
  <div id="rpt-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 14px;"></div>
</div>
</div><!-- end rpt-details-section -->

<script src="assets/js/report-general.js?v=3"></script>
<?php endif; ?>

<?php if ($subModule == 'visitors'): ?>
<!-- ===== VISITORS REPORT ===== -->

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label>
    <input id="vis-from" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label>
    <input id="vis-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Status</label>
    <select id="vis-status" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All</option>
      <option value="open">Open</option>
      <option value="in_progress">In Progress</option>
      <option value="completed">Completed</option>
      <option value="cancelled">Cancelled</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Payment</label>
    <select id="vis-payment" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All</option>
      <option value="insurance">Insurance</option>
      <option value="self_pay">Private</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Search</label>
    <input id="vis-search" type="text" placeholder="Patient name or No..." style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:160px;">
  </div>
  <div style="margin-top:14px;">
    <button onclick="visLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
  </div>
  <div style="margin-top:14px;margin-left:auto;">
    <button onclick="visPrint()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;" title="Print"><i class="fas fa-print"></i></button>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="vis-s-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Total Visitors</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="vis-s-insurance" style="font-size:22px;font-weight:700;color:#2563eb;">—</div><div style="font-size:11px;color:var(--gray-500);">Insurance</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="vis-s-private" style="font-size:22px;font-weight:700;color:#7c3aed;">—</div><div style="font-size:11px;color:var(--gray-500);">Private</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="vis-s-completed" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Completed</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="vis-s-cancelled" style="font-size:22px;font-weight:700;color:var(--danger-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Cancelled</div></div>
</div>

<!-- Table -->
<div id="vis-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:10px 12px;text-align:left;font-weight:600;">#</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Patient</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Patient No.</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Visit No.</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Date</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Type</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Payment</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Insurance</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Copay %</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Department</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Status</th>
    </tr></thead>
    <tbody id="vis-tbody"><tr><td colspan="11" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="vis-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;"></div>
</div>

<script>
const visAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=patients/visits';
let visPage=1;
function vEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function isPrivateVisit(v){
  if(!v) return true;
  if(v.is_private !== undefined) return v.is_private;
  if(v.payment_type==='self_pay'||!v.payment_type) return true;
  if(v.company_id==14) return true;
  const compName=(v.company_name||'').toLowerCase();
  if(compName.includes('prive')||compName.includes('private')) return true;
  return false;
}

async function visLoad(){
  const from=document.getElementById('vis-from').value;
  const to=document.getElementById('vis-to').value;
  const status=document.getElementById('vis-status').value;
  const payment=document.getElementById('vis-payment').value;
  const search=document.getElementById('vis-search').value.trim();

  const p=new URLSearchParams({date_from:from,date_to:to,page:visPage,limit:25});
  if(status) p.append('status',status);
  if(payment) p.append('payment_type',payment);
  if(search) p.append('search',search);

  const tb=document.getElementById('vis-tbody');
  tb.innerHTML='<tr><td colspan="11" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';

  try{
    const r=await fetch(visAPI+'&'+p);
    const j=await r.json();
    if(!j.success){tb.innerHTML='<tr><td colspan="11" style="padding:30px;text-align:center;color:#dc2626;">'+vEsc(j.message)+'</td></tr>';return;}
    const visits=j.data.visits||j.data||[];
    const pagination=j.data.pagination||{};
    const stats=j.data.stats||{};

    // Stats
    document.getElementById('vis-s-total').textContent=pagination.total||visits.length||0;
    const insCount=visits.filter(v=>!isPrivateVisit(v)).length;
    const privCount=visits.filter(v=>isPrivateVisit(v)).length;
    const completedCount=visits.filter(v=>v.status==='completed').length;
    const cancelledCount=visits.filter(v=>v.status==='cancelled').length;
    document.getElementById('vis-s-insurance').textContent=stats.insurance_visits!==undefined?stats.insurance_visits:insCount;
    document.getElementById('vis-s-private').textContent=stats.self_pay_visits!==undefined?stats.self_pay_visits:privCount;
    document.getElementById('vis-s-completed').textContent=stats.completed_visits!==undefined?stats.completed_visits:completedCount;
    document.getElementById('vis-s-cancelled').textContent=stats.cancelled_visits!==undefined?stats.cancelled_visits:cancelledCount;

    if(!visits.length){
      tb.innerHTML='<tr><td colspan="11" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-users" style="font-size:36px;display:block;margin-bottom:8px;"></i>No visitors found for this period.</td></tr>';
      return;
    }

    const stColors={open:'#2563eb',in_progress:'#d97706',completed:'#059669',cancelled:'#dc2626'};
    const stBg={open:'#dbeafe',in_progress:'#fef3c7',completed:'#d1fae5',cancelled:'#fee2e2'};
    tb.innerHTML=visits.map((v,i)=>{
      const name=v.patient_name||(v.first_name+' '+v.last_name);
      const priv=isPrivateVisit(v);
      const paymentBadge=priv
        ? `<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:#f3e8ff;color:#6b21a8;">Private</span>`
        : `<span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:#dbeafe;color:#1e40af;">Insurance</span>`;

      const insHtml=priv
        ? `<span style="color:var(--gray-400);">Private</span>`
        : `<div><span style="font-weight:600;color:var(--primary-color);font-size:12px;"><i class="fas fa-shield-alt" style="margin-right:4px;"></i>${vEsc(v.company_name||'—')}</span>${v.package_name?`<div style="font-size:11px;color:var(--gray-500);">${vEsc(v.package_name)}</div>`:''}</div>`;

      const copayHtml=priv
        ? `<span style="color:var(--gray-400);font-size:11px;">100%</span>`
        : `<span style="padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;background:#ecfdf5;color:#047857;">${parseFloat(v.copayment_percentage||0)}%</span>`;

      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:9px 12px;color:var(--gray-500);font-weight:600;">${(visPage-1)*25+i+1}</td>
        <td style="padding:9px 12px;font-weight:600;">${vEsc(name)}</td>
        <td style="padding:9px 12px;font-family:monospace;font-size:12px;color:var(--primary-color);">${vEsc(v.patient_no||'')}</td>
        <td style="padding:9px 12px;font-family:monospace;font-size:12px;">${vEsc(v.visit_no||'')}</td>
        <td style="padding:9px 12px;font-size:12px;">${vEsc(v.visit_date||'')}</td>
        <td style="padding:9px 12px;text-align:center;font-size:12px;">${vEsc(v.visit_type||'outpatient')}</td>
        <td style="padding:9px 12px;text-align:center;">${paymentBadge}</td>
        <td style="padding:9px 12px;font-size:12px;">${insHtml}</td>
        <td style="padding:9px 12px;text-align:center;">${copayHtml}</td>
        <td style="padding:9px 12px;font-size:12px;">${vEsc(v.department_name||'—')}</td>
        <td style="padding:9px 12px;text-align:center;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${stBg[v.status]||'#f3f4f6'};color:${stColors[v.status]||'#666'};">${v.status||'open'}</span></td>
      </tr>`;
    }).join('');

    // Pagination
    const pg=document.getElementById('vis-pagination');
    if(pagination.total_pages>1){
      let btns = '';
      let sp = Math.max(1, visPage - 2), ep = Math.min(pagination.total_pages, sp + 4);
      if (ep - sp < 4) sp = Math.max(1, ep - 4);
      for (let i = sp; i <= ep; i++) {
        const active = i === visPage;
        btns += `<button onclick="visPage=${i};visLoad()" style="padding:6px 12px;border:1px solid ${active?'var(--primary-color)':'var(--gray-200)'};background:${active?'var(--primary-color)':'white'};color:${active?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;font-weight:${active?'700':'400'};">${i}</button>`;
      }
      const startIdx = (visPage - 1) * (pagination.limit || 25) + 1;
      const endIdx = Math.min(visPage * (pagination.limit || 25), pagination.total);
      pg.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;flex-wrap:wrap;gap:8px;">
          <span style="font-size:13px;color:var(--gray-600);">
            Showing <strong>${startIdx}–${endIdx}</strong> of <strong>${pagination.total}</strong> visitors
          </span>
          <div style="display:flex;gap:4px;align-items:center;">
            <button onclick="visPage--;visLoad()" ${visPage<=1?'disabled':''} style="padding:6px 14px;border:1px solid var(--gray-200);background:${visPage<=1?'var(--gray-100)':'white'};color:${visPage<=1?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${visPage<=1?'not-allowed':'pointer'};font-size:13px;"><i class="fas fa-chevron-left"></i> Previous</button>
            ${btns}
            <button onclick="visPage++;visLoad()" ${visPage>=pagination.total_pages?'disabled':''} style="padding:6px 14px;border:1px solid var(--gray-200);background:${visPage>=pagination.total_pages?'var(--gray-100)':'white'};color:${visPage>=pagination.total_pages?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${visPage>=pagination.total_pages?'not-allowed':'pointer'};font-size:13px;">Next <i class="fas fa-chevron-right"></i></button>
          </div>
        </div>`;
    } else if (pagination && pagination.total > 0) {
      pg.innerHTML = `<div style="font-size:13px;color:var(--gray-600);width:100%;text-align:right;">Showing all <strong>${pagination.total}</strong> visitors</div>`;
    } else {
      pg.innerHTML = '';
    }

  }catch(e){tb.innerHTML='<tr><td colspan="11" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

function visPrint(){
  const el=document.getElementById('vis-printarea');
  const win=window.open('','','width=1000,height=700');
  win.document.write('<html><head><title>Visitors Report</title><style>body{font-family:Arial,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{padding:6px 8px;border:1px solid #ddd;text-align:left;}th{background:#f0f0f0;font-weight:600;}@media print{button{display:none;}}</style></head><body>');
  win.document.write('<h2>Visitors Report ('+document.getElementById('vis-from').value+' to '+document.getElementById('vis-to').value+')</h2>');
  win.document.write(el.innerHTML);
  win.document.write('</body></html>');
  win.document.close();
  win.print();
}

visLoad();
</script>
<?php endif; ?>

<?php if ($subModule == 'consultations'): ?>
<!-- ===== CONSULTATIONS BY DOCTOR REPORT ===== -->

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label>
    <input id="doc-from" type="date" value="<?php echo date('Y-m-01'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label>
    <input id="doc-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div style="margin-top:14px;">
    <button onclick="docLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
  </div>
</div>

<!-- Chart -->
<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:600;color:var(--gray-800);margin-bottom:16px;"><i class="fas fa-chart-pie" style="color:var(--primary-color);"></i> Revenue by Doctor</h3>
  <div style="max-width:400px;margin:0 auto;"><canvas id="doc-chart"></canvas></div>
</div>

<!-- Table -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:10px 12px;text-align:left;font-weight:600;">#</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Doctor</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Consultations</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Total Revenue</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Paid</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Pending</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Avg/Consult</th>
    </tr></thead>
    <tbody id="doc-tbody"><tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
    <tfoot id="doc-tfoot" style="display:none;">
      <tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);font-weight:700;">
        <td colspan="2" style="padding:10px 12px;">TOTALS</td>
        <td id="doc-f-consults" style="padding:10px 12px;text-align:center;">—</td>
        <td id="doc-f-revenue" style="padding:10px 12px;text-align:right;">—</td>
        <td id="doc-f-paid" style="padding:10px 12px;text-align:right;color:var(--success-color);">—</td>
        <td id="doc-f-pending" style="padding:10px 12px;text-align:right;color:var(--danger-color);">—</td>
        <td style="padding:10px 12px;"></td>
      </tr>
    </tfoot>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let docChart=null;

async function docLoad(){
  const from=document.getElementById('doc-from').value;
  const to=document.getElementById('doc-to').value;
  const tb=document.getElementById('doc-tbody');
  tb.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';

  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=reports/general&action=by_doctor&date_from='+from+'&date_to='+to);
    const j=await r.json();
    if(!j.success){tb.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:#dc2626;">'+j.message+'</td></tr>';return;}
    const data=j.data||[];
    if(!data.length){
      tb.innerHTML='<tr><td colspan="7" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-user-md" style="font-size:36px;display:block;margin-bottom:8px;"></i>No consultation data found.</td></tr>';
      document.getElementById('doc-tfoot').style.display='none';
      return;
    }

    let totalConsults=0, totalRevenue=0, totalPaid=0, totalPending=0;
    tb.innerHTML=data.map((d,i)=>{
      const revenue=parseFloat(d.total_revenue||0);
      const paid=parseFloat(d.total_paid||0);
      const pending=revenue-paid;
      const avg=d.consultation_count>0?Math.round(revenue/d.consultation_count):0;
      totalConsults+=parseInt(d.consultation_count||0);
      totalRevenue+=revenue;
      totalPaid+=paid;
      totalPending+=pending;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 12px;color:var(--gray-500);">${i+1}</td>
        <td style="padding:10px 12px;font-weight:600;">${d.doctor_name||'Unknown'}</td>
        <td style="padding:10px 12px;text-align:center;"><span style="background:var(--primary-light);color:var(--primary-color);padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">${d.consultation_count}</span></td>
        <td style="padding:10px 12px;text-align:right;font-weight:700;">${Number(revenue).toLocaleString()} RWF</td>
        <td style="padding:10px 12px;text-align:right;color:var(--success-color);">${Number(paid).toLocaleString()}</td>
        <td style="padding:10px 12px;text-align:right;color:${pending>0?'var(--danger-color)':'var(--gray-400)'};">${pending>0?Number(pending).toLocaleString():'—'}</td>
        <td style="padding:10px 12px;text-align:center;font-size:12px;color:var(--gray-600);">${Number(avg).toLocaleString()} RWF</td>
      </tr>`;
    }).join('');

    // Footer
    document.getElementById('doc-tfoot').style.display='';
    document.getElementById('doc-f-consults').textContent=totalConsults;
    document.getElementById('doc-f-revenue').textContent=Number(totalRevenue).toLocaleString()+' RWF';
    document.getElementById('doc-f-paid').textContent=Number(totalPaid).toLocaleString()+' RWF';
    document.getElementById('doc-f-pending').textContent=Number(totalPending).toLocaleString()+' RWF';

    // Chart
    renderDocChart(data);

  }catch(e){tb.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

function renderDocChart(data){
  const ctx=document.getElementById('doc-chart').getContext('2d');
  if(docChart) docChart.destroy();

  const labels=data.map(d=>d.doctor_name||'Unknown');
  const revenues=data.map(d=>parseFloat(d.total_revenue||0));
  const colors=['#2563eb','#059669','#d97706','#dc2626','#7c3aed','#0891b2','#ea580c','#4f46e5','#16a34a','#be185d'];

  docChart=new Chart(ctx,{
    type:'pie',
    data:{
      labels:labels,
      datasets:[{
        data:revenues,
        backgroundColor:colors.slice(0,data.length),
        borderWidth:2,
        borderColor:'#fff'
      }]
    },
    options:{
      responsive:true,
      plugins:{
        legend:{position:'bottom',labels:{font:{size:12}}},
        tooltip:{callbacks:{label:function(ctx){return ctx.label+': '+Number(ctx.raw).toLocaleString()+' RWF';}}}
      }
    }
  });
}

docLoad();
</script>
<?php endif; ?>

<?php if ($subModule == 'lab-sales' || $subModule == 'procedure-sales' || $subModule == 'drug-sales'): ?>
<!-- ===== CATEGORY SALES REPORT (Lab / Procedure / Drugs) ===== -->
<?php
$catMap = ['lab-sales'=>'lab','procedure-sales'=>'procedure','drug-sales'=>'pharmacy'];
$catTitles = ['lab-sales'=>'Lab Sales','procedure-sales'=>'Procedure Sales','drug-sales'=>'Drug Sales'];
$catIcons = ['lab-sales'=>'fa-flask','procedure-sales'=>'fa-syringe','drug-sales'=>'fa-pills'];
$catColors = ['lab-sales'=>'#2563eb','procedure-sales'=>'#db2777','drug-sales'=>'#16a34a'];
$currentCat = $catMap[$subModule] ?? 'lab';
$currentCatTitle = $catTitles[$subModule] ?? 'Sales';
$currentCatIcon = $catIcons[$subModule] ?? 'fa-chart-bar';
$currentCatColor = $catColors[$subModule] ?? '#2563eb';
?>

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label>
    <input id="cat-from" type="date" value="<?php echo date('Y-m-01'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label>
    <input id="cat-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div style="margin-top:14px;">
    <button onclick="catLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="cat-s-items" style="font-size:22px;font-weight:700;color:<?php echo $currentCatColor; ?>;">—</div><div style="font-size:11px;color:var(--gray-500);">Total Items</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="cat-s-revenue" style="font-size:18px;font-weight:700;color:var(--gray-800);">—</div><div style="font-size:11px;color:var(--gray-500);">Total Revenue</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="cat-s-qty" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Total Quantity</div></div>
</div>

<!-- Chart -->
<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:600;color:var(--gray-800);margin-bottom:16px;"><i class="fas <?php echo $currentCatIcon; ?>" style="color:<?php echo $currentCatColor; ?>;"></i> <?php echo $currentCatTitle; ?> — Top Items</h3>
  <div style="max-width:400px;margin:0 auto;"><canvas id="cat-chart"></canvas></div>
</div>

<!-- Table -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:10px 12px;text-align:left;font-weight:600;">#</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Item / Service</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Quantity</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Total Revenue</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Avg Price</th>
    </tr></thead>
    <tbody id="cat-tbody"><tr><td colspan="5" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
    <tfoot id="cat-tfoot" style="display:none;">
      <tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);font-weight:700;">
        <td colspan="2" style="padding:10px 12px;">TOTALS</td>
        <td id="cat-f-qty" style="padding:10px 12px;text-align:center;">—</td>
        <td id="cat-f-revenue" style="padding:10px 12px;text-align:right;">—</td>
        <td style="padding:10px 12px;"></td>
      </tr>
    </tfoot>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const catCategory='<?php echo $currentCat; ?>';
let catChart=null;

async function catLoad(){
  const from=document.getElementById('cat-from').value;
  const to=document.getElementById('cat-to').value;
  const tb=document.getElementById('cat-tbody');
  tb.innerHTML='<tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';

  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=reports/general&action=by_category&category='+catCategory+'&date_from='+from+'&date_to='+to);
    const j=await r.json();
    if(!j.success){tb.innerHTML='<tr><td colspan="5" style="padding:30px;text-align:center;color:#dc2626;">'+j.message+'</td></tr>';return;}
    const data=j.data||[];
    if(!data.length){
      tb.innerHTML='<tr><td colspan="5" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-chart-pie" style="font-size:36px;display:block;margin-bottom:8px;"></i>No data found for this period.</td></tr>';
      document.getElementById('cat-tfoot').style.display='none';
      return;
    }

    let totalQty=0,totalRevenue=0;
    tb.innerHTML=data.map((d,i)=>{
      const revenue=parseFloat(d.total_revenue||0);
      const qty=parseInt(d.total_qty||0);
      const avg=qty>0?Math.round(revenue/qty):0;
      totalQty+=qty;
      totalRevenue+=revenue;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 12px;color:var(--gray-500);">${i+1}</td>
        <td style="padding:10px 12px;font-weight:600;">${d.description||'—'}</td>
        <td style="padding:10px 12px;text-align:center;">${qty}</td>
        <td style="padding:10px 12px;text-align:right;font-weight:700;">${Number(revenue).toLocaleString()} RWF</td>
        <td style="padding:10px 12px;text-align:right;color:var(--gray-600);">${Number(avg).toLocaleString()} RWF</td>
      </tr>`;
    }).join('');

    document.getElementById('cat-s-items').textContent=data.length;
    document.getElementById('cat-s-revenue').textContent=Number(totalRevenue).toLocaleString()+' RWF';
    document.getElementById('cat-s-qty').textContent=totalQty;
    document.getElementById('cat-tfoot').style.display='';
    document.getElementById('cat-f-qty').textContent=totalQty;
    document.getElementById('cat-f-revenue').textContent=Number(totalRevenue).toLocaleString()+' RWF';

    // Pie chart top 10
    renderCatChart(data.slice(0,10));
  }catch(e){tb.innerHTML='<tr><td colspan="5" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

function renderCatChart(data){
  const ctx=document.getElementById('cat-chart').getContext('2d');
  if(catChart) catChart.destroy();
  const labels=data.map(d=>(d.description||'').substring(0,25));
  const values=data.map(d=>parseFloat(d.total_revenue||0));
  const colors=['#2563eb','#059669','#d97706','#dc2626','#7c3aed','#0891b2','#ea580c','#4f46e5','#16a34a','#be185d'];
  catChart=new Chart(ctx,{
    type:'pie',
    data:{labels,datasets:[{data:values,backgroundColor:colors.slice(0,data.length),borderWidth:2,borderColor:'#fff'}]},
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11}}},tooltip:{callbacks:{label:function(ctx){return ctx.label+': '+Number(ctx.raw).toLocaleString()+' RWF';}}}}}
  });
}

catLoad();
</script>
<?php endif; ?>

<?php if ($subModule == 'diagnosis'): ?>
<!-- ===== DIAGNOSIS REPORT ===== -->

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label>
    <input id="diag-from" type="date" value="<?php echo date('Y-m-01'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label>
    <input id="diag-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div style="margin-top:14px;">
    <button onclick="diagLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
  </div>
  <div style="margin-top:14px;margin-left:auto;">
    <button onclick="diagPrint()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;" title="Print"><i class="fas fa-print"></i></button>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="diag-s-total" style="font-size:22px;font-weight:700;color:#7c3aed;">—</div><div style="font-size:11px;color:var(--gray-500);">Unique Diagnoses</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="diag-s-cases" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Total Cases</div></div>
</div>

<!-- Chart -->
<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:600;color:var(--gray-800);margin-bottom:16px;"><i class="fas fa-chart-pie" style="color:#7c3aed;"></i> Top Diagnoses</h3>
  <div style="max-width:450px;margin:0 auto;"><canvas id="diag-chart"></canvas></div>
</div>

<!-- Table -->
<div id="diag-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:10px 12px;text-align:left;font-weight:600;">#</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Diagnosis</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Cases</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">% of Total</th>
    </tr></thead>
    <tbody id="diag-tbody"><tr><td colspan="4" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let diagChart=null;

async function diagLoad(){
  const from=document.getElementById('diag-from').value;
  const to=document.getElementById('diag-to').value;
  const tb=document.getElementById('diag-tbody');
  tb.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';

  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=reports/general&action=by_diagnosis&date_from='+from+'&date_to='+to);
    const j=await r.json();
    if(!j.success){tb.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:#dc2626;">'+j.message+'</td></tr>';return;}
    const data=j.data||[];
    if(!data.length){
      tb.innerHTML='<tr><td colspan="4" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-diagnoses" style="font-size:36px;display:block;margin-bottom:8px;"></i>No diagnosis data found.</td></tr>';
      return;
    }

    const totalCases=data.reduce((s,d)=>s+parseInt(d.case_count||0),0);
    document.getElementById('diag-s-total').textContent=data.length;
    document.getElementById('diag-s-cases').textContent=totalCases;

    tb.innerHTML=data.map((d,i)=>{
      const count=parseInt(d.case_count||0);
      const pct=totalCases>0?(count/totalCases*100).toFixed(1):0;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 12px;color:var(--gray-500);">${i+1}</td>
        <td style="padding:10px 12px;font-weight:600;">${d.diagnosis}</td>
        <td style="padding:10px 12px;text-align:center;"><span style="background:#f5f3ff;color:#7c3aed;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">${count}</span></td>
        <td style="padding:10px 12px;text-align:center;">
          <div style="display:flex;align-items:center;gap:8px;justify-content:center;">
            <div style="flex:1;max-width:120px;height:6px;background:var(--gray-200);border-radius:3px;overflow:hidden;">
              <div style="height:100%;width:${pct}%;background:#7c3aed;border-radius:3px;"></div>
            </div>
            <span style="font-size:12px;color:var(--gray-600);">${pct}%</span>
          </div>
        </td>
      </tr>`;
    }).join('');

    renderDiagChart(data.slice(0,8));
  }catch(e){tb.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

function renderDiagChart(data){
  const ctx=document.getElementById('diag-chart').getContext('2d');
  if(diagChart) diagChart.destroy();
  const labels=data.map(d=>(d.diagnosis||'').substring(0,30));
  const values=data.map(d=>parseInt(d.case_count||0));
  const colors=['#7c3aed','#2563eb','#059669','#d97706','#dc2626','#0891b2','#ea580c','#be185d'];
  diagChart=new Chart(ctx,{
    type:'pie',
    data:{labels,datasets:[{data:values,backgroundColor:colors.slice(0,data.length),borderWidth:2,borderColor:'#fff'}]},
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11}}},tooltip:{callbacks:{label:function(ctx){return ctx.label+': '+ctx.raw+' cases';}}}}}
  });
}

function diagPrint(){
  const el=document.getElementById('diag-printarea');
  const win=window.open('','','width=800,height=600');
  win.document.write('<html><head><title>Diagnosis Report</title><style>body{font-family:Arial,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{padding:6px 8px;border:1px solid #ddd;text-align:left;}th{background:#f0f0f0;}</style></head><body>');
  win.document.write('<h2>Diagnosis Report ('+document.getElementById('diag-from').value+' to '+document.getElementById('diag-to').value+')</h2>');
  win.document.write(el.innerHTML);
  win.document.write('</body></html>');
  win.document.close();
  win.print();
}

diagLoad();
</script>
<?php endif; ?>

<?php if ($subModule == 'prescriptions'): ?>
<!-- ===== EXTERNAL PRESCRIPTIONS REPORT ===== -->

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label>
    <input id="rx-from" type="date" value="<?php echo date('Y-m-01'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label>
    <input id="rx-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div style="margin-top:14px;">
    <button onclick="rxLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
  </div>
  <div style="margin-top:14px;margin-left:auto;">
    <button onclick="rxPrint()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;" title="Print"><i class="fas fa-print"></i></button>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rx-s-total" style="font-size:22px;font-weight:700;color:#16a34a;">—</div><div style="font-size:11px;color:var(--gray-500);">Total Prescriptions</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rx-s-drugs" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Unique Drugs</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rx-s-items" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Total Items</div></div>
</div>

<!-- Chart -->
<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px;">
  <h3 style="font-size:15px;font-weight:600;color:var(--gray-800);margin-bottom:16px;"><i class="fas fa-chart-pie" style="color:#16a34a;"></i> Most Prescribed Drugs</h3>
  <div style="max-width:450px;margin:0 auto;"><canvas id="rx-chart"></canvas></div>
</div>

<!-- Table -->
<div id="rx-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:10px 12px;text-align:left;font-weight:600;">#</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Medication</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Times Prescribed</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">% of Total</th>
    </tr></thead>
    <tbody id="rx-tbody"><tr><td colspan="4" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let rxChart=null;

async function rxLoad(){
  const from=document.getElementById('rx-from').value;
  const to=document.getElementById('rx-to').value;
  const tb=document.getElementById('rx-tbody');
  tb.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';

  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=reports/general&action=by_prescription&date_from='+from+'&date_to='+to);
    const j=await r.json();
    if(!j.success){tb.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:#dc2626;">'+j.message+'</td></tr>';return;}
    const data=j.data||[];
    if(!data.length){
      tb.innerHTML='<tr><td colspan="4" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-prescription" style="font-size:36px;display:block;margin-bottom:8px;"></i>No prescription data found.</td></tr>';
      return;
    }

    const totalItems=data.reduce((s,d)=>s+parseInt(d.count||0),0);
    document.getElementById('rx-s-drugs').textContent=data.length;
    document.getElementById('rx-s-items').textContent=totalItems;
    document.getElementById('rx-s-total').textContent=j.total_prescriptions||totalItems;

    tb.innerHTML=data.map((d,i)=>{
      const count=parseInt(d.count||0);
      const pct=totalItems>0?(count/totalItems*100).toFixed(1):0;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 12px;color:var(--gray-500);">${i+1}</td>
        <td style="padding:10px 12px;font-weight:600;">${d.medication}</td>
        <td style="padding:10px 12px;text-align:center;"><span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">${count}</span></td>
        <td style="padding:10px 12px;text-align:center;">
          <div style="display:flex;align-items:center;gap:8px;justify-content:center;">
            <div style="flex:1;max-width:120px;height:6px;background:var(--gray-200);border-radius:3px;overflow:hidden;">
              <div style="height:100%;width:${pct}%;background:#16a34a;border-radius:3px;"></div>
            </div>
            <span style="font-size:12px;color:var(--gray-600);">${pct}%</span>
          </div>
        </td>
      </tr>`;
    }).join('');

    renderRxChart(data.slice(0,8));
  }catch(e){tb.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

function renderRxChart(data){
  const ctx=document.getElementById('rx-chart').getContext('2d');
  if(rxChart) rxChart.destroy();
  const labels=data.map(d=>(d.medication||'').substring(0,25));
  const values=data.map(d=>parseInt(d.count||0));
  const colors=['#16a34a','#2563eb','#d97706','#dc2626','#7c3aed','#0891b2','#ea580c','#be185d'];
  rxChart=new Chart(ctx,{
    type:'pie',
    data:{labels,datasets:[{data:values,backgroundColor:colors.slice(0,data.length),borderWidth:2,borderColor:'#fff'}]},
    options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11}}},tooltip:{callbacks:{label:function(ctx){return ctx.label+': '+ctx.raw+' times';}}}}}
  });
}

function rxPrint(){
  const el=document.getElementById('rx-printarea');
  const win=window.open('','','width=800,height=600');
  win.document.write('<html><head><title>Prescriptions Report</title><style>body{font-family:Arial,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{padding:6px 8px;border:1px solid #ddd;text-align:left;}th{background:#f0f0f0;}</style></head><body>');
  win.document.write('<h2>External Prescriptions Report ('+document.getElementById('rx-from').value+' to '+document.getElementById('rx-to').value+')</h2>');
  win.document.write(el.innerHTML);
  win.document.write('</body></html>');
  win.document.close();
  win.print();
}

rxLoad();
</script>
<?php endif; ?>

<?php if ($subModule == 'lab-analytics'): ?>
<!-- ===== LAB ANALYTICS - Advanced Lab Reports ===== -->

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label>
    <input id="la-from" type="date" value="<?php echo date('Y-m-01'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label>
    <input id="la-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Test Type</label>
    <select id="la-test" onchange="laLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;min-width:180px;">
      <option value="">All Tests</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Result Filter</label>
    <select id="la-result-filter" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All Results</option>
      <option value="normal">Normal</option>
      <option value="abnormal">Abnormal (High/Low)</option>
      <option value="positive">Positive</option>
      <option value="negative">Negative</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Gender</label>
    <select id="la-gender" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Age Range</label>
    <div style="display:flex;gap:4px;align-items:center;">
      <input id="la-age-min" type="number" placeholder="Min" min="0" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:60px;">
      <span style="color:var(--gray-400);">-</span>
      <input id="la-age-max" type="number" placeholder="Max" min="0" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:60px;">
    </div>
  </div>
  <div style="margin-top:14px;">
    <button onclick="laLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Generate</button>
  </div>
</div>

<!-- Stats Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #2563eb;">
    <div id="la-s-total" style="font-size:24px;font-weight:700;color:#2563eb;">0</div>
    <div style="font-size:11px;color:var(--gray-500);">Total Tests Done</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #16a34a;">
    <div id="la-s-completed" style="font-size:24px;font-weight:700;color:#16a34a;">0</div>
    <div style="font-size:11px;color:var(--gray-500);">Completed</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #d97706;">
    <div id="la-s-pending" style="font-size:24px;font-weight:700;color:#d97706;">0</div>
    <div style="font-size:11px;color:var(--gray-500);">Pending</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #dc2626;">
    <div id="la-s-abnormal" style="font-size:24px;font-weight:700;color:#dc2626;">0</div>
    <div style="font-size:11px;color:var(--gray-500);">Abnormal Results</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #7c3aed;">
    <div id="la-s-patients" style="font-size:24px;font-weight:700;color:#7c3aed;">0</div>
    <div style="font-size:11px;color:var(--gray-500);">Unique Patients</div>
  </div>
</div>

<!-- Gender & Age Breakdown -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
  <div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:12px;"><i class="fas fa-venus-mars" style="color:#7c3aed;"></i> By Gender</h4>
    <div id="la-gender-breakdown" style="font-size:13px;">Loading...</div>
  </div>
  <div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);">
    <h4 style="font-size:14px;font-weight:600;margin-bottom:12px;"><i class="fas fa-birthday-cake" style="color:#d97706;"></i> By Age Group</h4>
    <div id="la-age-breakdown" style="font-size:13px;">Loading...</div>
  </div>
</div>

<!-- Test Results Table -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
  <div style="padding:14px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
    <h3 style="font-size:15px;font-weight:600;"><i class="fas fa-microscope" style="color:#2563eb;"></i> Lab Test Results — Patient List</h3>
    <span id="la-result-count" style="font-size:12px;color:var(--gray-500);"></span>
  </div>
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:10px;text-align:left;font-weight:600;">#</th>
        <th style="padding:10px;text-align:left;font-weight:600;">Patient</th>
        <th style="padding:10px;text-align:center;font-weight:600;">Gender</th>
        <th style="padding:10px;text-align:center;font-weight:600;">Age</th>
        <th style="padding:10px;text-align:left;font-weight:600;">Test</th>
        <th style="padding:10px;text-align:center;font-weight:600;">Result</th>
        <th style="padding:10px;text-align:center;font-weight:600;">Status</th>
        <th style="padding:10px;text-align:left;font-weight:600;">Date</th>
      </tr></thead>
      <tbody id="la-tbody"><tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i></td></tr></tbody>
    </table>
  </div>
</div>

<script>
function laEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function laAge(dob){if(!dob)return null;const d=new Date(dob),n=new Date();let y=n.getFullYear()-d.getFullYear();if(n.getMonth()<d.getMonth()||(n.getMonth()===d.getMonth()&&n.getDate()<d.getDate()))y--;return y;}

async function laLoadTestTypes(){
  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=patients/billing&action=services&category=lab,radiology');
    const j=await r.json();
    const sel=document.getElementById('la-test');
    if(j.success&&j.data) j.data.forEach(s=>{sel.innerHTML+=`<option value="${laEsc(s.name)}">${laEsc(s.name)}</option>`;});
  }catch(e){}
}

async function laLoad(){
  const from=document.getElementById('la-from').value;
  const to=document.getElementById('la-to').value;
  const testFilter=document.getElementById('la-test').value;
  const resultFilter=document.getElementById('la-result-filter').value;
  const genderFilter=document.getElementById('la-gender').value;
  const ageMin=parseInt(document.getElementById('la-age-min').value)||0;
  const ageMax=parseInt(document.getElementById('la-age-max').value)||200;
  const tbody=document.getElementById('la-tbody');
  tbody.innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';

  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=reports/general&action=lab_analytics&date_from='+from+'&date_to='+to);
    const j=await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:#dc2626;">'+laEsc(j.message)+'</td></tr>';return;}
    let data=j.data||[];

    // Client-side filtering
    if(testFilter) data=data.filter(d=>(d.description||'').includes(testFilter));
    if(genderFilter) data=data.filter(d=>d.gender===genderFilter);
    if(ageMin||ageMax<200) data=data.filter(d=>{const age=laAge(d.date_of_birth);return age!==null&&age>=ageMin&&age<=ageMax;});
    if(resultFilter){
      data=data.filter(d=>{
        const res=(d.result||'').toLowerCase();
        if(resultFilter==='positive') return res.includes('positive');
        if(resultFilter==='negative') return res.includes('negative');
        if(resultFilter==='normal') return res.includes('normal')&&!res.includes('abnormal');
        if(resultFilter==='abnormal') return res.includes('high')||res.includes('low')||res.includes('abnormal')||res.includes('↑')||res.includes('↓');
        return true;
      });
    }

    // Stats
    const completed=data.filter(d=>d.status==='completed');
    const pending=data.filter(d=>d.status!=='completed');
    const uniquePatients=new Set(data.map(d=>d.patient_id));
    let abnormalCount=0;
    data.forEach(d=>{
      if(d.result){
        try{
          const parsed=JSON.parse(d.result);
          if(Array.isArray(parsed)){parsed.forEach(r=>{const v=parseFloat(r.value);if(!isNaN(v)&&((r.normal_min!=null&&v<r.normal_min)||(r.normal_max!=null&&v>r.normal_max)))abnormalCount++;});}
        }catch(e){if(d.result.toLowerCase().includes('positive')||d.result.toLowerCase().includes('abnormal'))abnormalCount++;}
      }
    });

    document.getElementById('la-s-total').textContent=data.length;
    document.getElementById('la-s-completed').textContent=completed.length;
    document.getElementById('la-s-pending').textContent=pending.length;
    document.getElementById('la-s-abnormal').textContent=abnormalCount;
    document.getElementById('la-s-patients').textContent=uniquePatients.size;
    document.getElementById('la-result-count').textContent=data.length+' result(s)';

    // Gender breakdown
    const genderCounts={Male:0,Female:0,Other:0};
    data.forEach(d=>{genderCounts[d.gender]=(genderCounts[d.gender]||0)+1;if(!d.gender)genderCounts['Other']++;});
    document.getElementById('la-gender-breakdown').innerHTML=`
      <div style="display:flex;gap:16px;">
        <div style="flex:1;background:#eff6ff;border-radius:6px;padding:10px;text-align:center;"><div style="font-size:20px;font-weight:700;color:#2563eb;">${genderCounts.Male}</div><div style="font-size:11px;color:var(--gray-500);">Male</div></div>
        <div style="flex:1;background:#fdf2f8;border-radius:6px;padding:10px;text-align:center;"><div style="font-size:20px;font-weight:700;color:#db2777;">${genderCounts.Female}</div><div style="font-size:11px;color:var(--gray-500);">Female</div></div>
      </div>`;

    // Age breakdown
    const ageGroups={'0-5':0,'6-17':0,'18-30':0,'31-45':0,'46-60':0,'60+':0};
    data.forEach(d=>{const a=laAge(d.date_of_birth);if(a===null)return;if(a<=5)ageGroups['0-5']++;else if(a<=17)ageGroups['6-17']++;else if(a<=30)ageGroups['18-30']++;else if(a<=45)ageGroups['31-45']++;else if(a<=60)ageGroups['46-60']++;else ageGroups['60+']++;});
    document.getElementById('la-age-breakdown').innerHTML=Object.entries(ageGroups).map(([k,v])=>`<div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--gray-100);"><span>${k} yrs</span><strong>${v}</strong></div>`).join('');

    // Render table
    if(!data.length){tbody.innerHTML='<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);">No lab results found for the selected criteria.</td></tr>';return;}
    tbody.innerHTML=data.slice(0,200).map((d,i)=>{
      const age=laAge(d.date_of_birth);
      let resultDisplay='—';
      let resultColor='var(--gray-500)';
      if(d.result){
        try{
          const parsed=JSON.parse(d.result);
          if(Array.isArray(parsed)&&parsed.length){
            const abnormals=parsed.filter(r=>{const v=parseFloat(r.value);return !isNaN(v)&&((r.normal_min!=null&&v<r.normal_min)||(r.normal_max!=null&&v>r.normal_max));});
            resultDisplay=parsed.map(r=>r.test_name+': '+r.value+(r.unit?' '+r.unit:'')).join(', ');
            if(abnormals.length){resultColor='#dc2626';resultDisplay='⚠ '+resultDisplay;}
            else resultColor='#059669';
          } else {resultDisplay=d.result.substring(0,60);}
        }catch(e){
          resultDisplay=d.result.substring(0,60);
          if(d.result.toLowerCase().includes('positive'))resultColor='#dc2626';
          else if(d.result.toLowerCase().includes('negative'))resultColor='#059669';
        }
      }
      const stColor=d.status==='completed'?'#059669':'#d97706';
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;">${i+1}</td>
        <td style="padding:8px 10px;"><div style="font-weight:600;">${laEsc(d.patient_name)}</div><div style="font-size:10px;color:var(--gray-500);">${laEsc(d.patient_no||'')}</div></td>
        <td style="padding:8px 10px;text-align:center;">${laEsc(d.gender||'—')}</td>
        <td style="padding:8px 10px;text-align:center;">${age!==null?age+'y':'—'}</td>
        <td style="padding:8px 10px;font-weight:500;">${laEsc(d.description||d.order_type||'—')}</td>
        <td style="padding:8px 10px;text-align:center;font-size:11px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:${resultColor};" title="${laEsc(resultDisplay)}">${laEsc(resultDisplay)}</td>
        <td style="padding:8px 10px;text-align:center;"><span style="padding:2px 6px;border-radius:8px;font-size:10px;font-weight:600;color:${stColor};">${d.status||'ordered'}</span></td>
        <td style="padding:8px 10px;font-size:11px;color:var(--gray-500);">${(d.created_at||'').substring(0,10)}</td>
      </tr>`;
    }).join('');
  }catch(e){tbody.innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

laLoadTestTypes();
laLoad();
</script>
<?php endif; ?>

<?php if ($subModule == 'insurance-monthly'): ?>
<!-- ===== INSURANCE MONTHLY REPORT ===== -->

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Month</label>
    <select id="im-month" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <?php
      for($m=1;$m<=12;$m++){
        $sel = $m==date('n') ? 'selected' : '';
        echo '<option value="'.str_pad($m,2,'0',STR_PAD_LEFT).'" '.$sel.'>'.date('F',mktime(0,0,0,$m,1)).'</option>';
      }
      ?>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Year</label>
    <select id="im-year" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <?php for($y=date('Y');$y>=date('Y')-4;$y--){ $sel=$y==date('Y')?'selected':''; echo "<option value='$y' $sel>$y</option>"; } ?>
    </select>
  </div>
  <div style="margin-top:14px;">
    <button onclick="imLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-sync-alt"></i> Generate</button>
  </div>
  <div style="margin-top:14px;margin-left:auto;display:flex;gap:8px;">
    <button onclick="imPrint()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;" title="Print"><i class="fas fa-print"></i> Print</button>
    <button onclick="imExportCSV()" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;" title="Export CSV"><i class="fas fa-file-csv"></i> CSV</button>
  </div>
</div>

<!-- Report Title Bar -->
<div id="im-title-bar" style="background:linear-gradient(135deg,#1e40af,#3b82f6);color:white;border-radius:var(--radius);padding:16px 24px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
  <div>
    <div style="font-size:18px;font-weight:700;"><i class="fas fa-shield-alt"></i> Insurance Monthly Report</div>
    <div id="im-period-label" style="font-size:13px;opacity:0.85;margin-top:4px;"></div>
  </div>
  <div id="im-loading-indicator" style="display:none;"><i class="fas fa-spinner fa-spin fa-2x"></i></div>
</div>

<!-- Summary Totals Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #2563eb;">
    <div id="im-tot-opd" style="font-size:22px;font-weight:700;color:#2563eb;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Total OPD Cases</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #7c3aed;">
    <div id="im-tot-inp" style="font-size:22px;font-weight:700;color:#7c3aed;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Hospitalization Cases</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #d97706;">
    <div id="im-tot-copay" style="font-size:16px;font-weight:700;color:#d97706;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Total CoPayment</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #0891b2;">
    <div id="im-tot-sub" style="font-size:16px;font-weight:700;color:#0891b2;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Bills Submitted</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #059669;">
    <div id="im-tot-paid" style="font-size:16px;font-weight:700;color:#059669;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Bills Paid</div>
  </div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-top:3px solid #dc2626;">
    <div id="im-tot-social" style="font-size:22px;font-weight:700;color:#dc2626;">—</div>
    <div style="font-size:11px;color:var(--gray-500);">Social Cases</div>
  </div>
</div>

<!-- Main Report Table -->
<div id="im-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table id="im-table" style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:900px;">
    <thead>
      <tr style="background:#1e40af;color:white;">
        <th style="padding:10px 12px;text-align:left;font-weight:600;white-space:nowrap;">Payer / Insurance</th>
        <th style="padding:10px 12px;text-align:center;font-weight:600;white-space:nowrap;">OPD Cases<br><span style="font-weight:400;font-size:10px;">(New &amp; Old)</span></th>
        <th style="padding:10px 12px;text-align:center;font-weight:600;white-space:nowrap;">Hospitalization<br><span style="font-weight:400;font-size:10px;">Cases</span></th>
        <th style="padding:10px 12px;text-align:right;font-weight:600;white-space:nowrap;">CoPayment<br><span style="font-weight:400;font-size:10px;">(Amount)</span></th>
        <th style="padding:10px 12px;text-align:right;font-weight:600;white-space:nowrap;">Bills Submitted<br><span style="font-weight:400;font-size:10px;">(Amount)</span></th>
        <th style="padding:10px 12px;text-align:right;font-weight:600;white-space:nowrap;">Bills Paid<br><span style="font-weight:400;font-size:10px;">(End of Month)</span></th>
        <th style="padding:10px 12px;text-align:center;font-weight:600;white-space:nowrap;">Social Cases<br><span style="font-weight:400;font-size:10px;">(No. of Cases)</span></th>
        <th style="padding:10px 12px;text-align:right;font-weight:600;white-space:nowrap;">Social Amount<br><span style="font-weight:400;font-size:10px;">Billed</span></th>
      </tr>
    </thead>
    <tbody id="im-tbody">
      <tr><td colspan="8" style="padding:60px;text-align:center;color:var(--gray-400);">
        <i class="fas fa-shield-alt" style="font-size:36px;display:block;margin-bottom:12px;opacity:0.3;"></i>
        Select a month and click Generate
      </td></tr>
    </tbody>
    <tfoot id="im-tfoot" style="display:none;">
      <tr style="background:#1e3a8a;color:white;font-weight:700;">
        <td style="padding:10px 12px;">GRAND TOTAL</td>
        <td id="im-f-opd" style="padding:10px 12px;text-align:center;">0</td>
        <td id="im-f-inp" style="padding:10px 12px;text-align:center;">0</td>
        <td id="im-f-copay" style="padding:10px 12px;text-align:right;">0</td>
        <td id="im-f-sub" style="padding:10px 12px;text-align:right;">0</td>
        <td id="im-f-paid" style="padding:10px 12px;text-align:right;">0</td>
        <td id="im-f-social" style="padding:10px 12px;text-align:center;">0</td>
        <td id="im-f-socamt" style="padding:10px 12px;text-align:right;">0</td>
      </tr>
    </tfoot>
  </table>
</div>

<style>
#im-table tbody tr:nth-child(odd) { background:#f8fafc; }
#im-table tbody tr:nth-child(even) { background:#ffffff; }
#im-table tbody tr:hover { background:#eff6ff; }
#im-table tbody td { border-bottom:1px solid #e5e7eb; }
.im-payer-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
@media print {
  button { display:none !important; }
  #im-title-bar { background:#1e40af !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  #im-table thead tr { background:#1e40af !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
</style>

<script>
const IM_API = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=reports/general&action=insurance_monthly';
let imData=[];

const IM_PAYER_COLORS={
  'CBHI':            {bg:'#dcfce7',color:'#15803d'},
  'RAMA':            {bg:'#dbeafe',color:'#1d4ed8'},
  'MMI':             {bg:'#fce7f3',color:'#be185d'},
  'UR':              {bg:'#f3e8ff',color:'#7e22ce'},
  'RADIANT':         {bg:'#fef3c7',color:'#b45309'},
  'OLD MUTUAL (UAP)':{bg:'#ffedd5',color:'#c2410c'},
  'SANLAM':          {bg:'#e0f2fe',color:'#0369a1'},
  'BRITAM':          {bg:'#ecfdf5',color:'#047857'},
  'EDEN CARE':       {bg:'#fdf4ff',color:'#a21caf'},
  'OTHER INSURANCE': {bg:'#f1f5f9',color:'#475569'},
  'NONINSURED':      {bg:'#fef2f2',color:'#b91c1c'},
};

function imFmt(n){ return Number(n||0).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0})+' RWF'; }
function imNum(n){ return Number(n||0).toLocaleString(); }

async function imLoad(){
  const month=document.getElementById('im-month').value;
  const year=document.getElementById('im-year').value;
  const dateFrom=year+'-'+month+'-01';
  const lastDay=new Date(parseInt(year),parseInt(month),0).getDate();
  const dateTo=year+'-'+month+'-'+String(lastDay).padStart(2,'0');

  const monthName=document.getElementById('im-month').options[document.getElementById('im-month').selectedIndex].text;
  document.getElementById('im-period-label').textContent='Period: '+monthName+' '+year+' ('+dateFrom+' to '+dateTo+')';

  const tbody=document.getElementById('im-tbody');
  const tfoot=document.getElementById('im-tfoot');
  document.getElementById('im-loading-indicator').style.display='block';
  tbody.innerHTML='<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i></td></tr>';
  tfoot.style.display='none';

  try{
    const r=await fetch(IM_API+'&date_from='+dateFrom+'&date_to='+dateTo);
    const j=await r.json();
    document.getElementById('im-loading-indicator').style.display='none';
    if(!j.success){ tbody.innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:#dc2626;">'+String(j.message||'Error')+'</td></tr>'; return; }

    imData=j.data||[];
    let totOPD=0,totInp=0,totCopay=0,totSub=0,totPaid=0,totSocial=0,totSocAmt=0;

    tbody.innerHTML=imData.map(function(row){
      const badge=IM_PAYER_COLORS[row.payer]||{bg:'#f1f5f9',color:'#334155'};
      totOPD+=row.opd_cases; totInp+=row.inpatient_cases;
      totCopay+=row.copayment; totSub+=row.bills_submitted;
      totPaid+=row.bills_paid; totSocial+=row.social_cases;
      totSocAmt+=row.social_amount;
      return '<tr>'
        +'<td style="padding:10px 12px;"><span class="im-payer-badge" style="background:'+badge.bg+';color:'+badge.color+';">'+row.payer+'</span></td>'
        +'<td style="padding:10px 12px;text-align:center;font-weight:'+(row.opd_cases>0?'700':'400')+';color:'+(row.opd_cases>0?'#1d4ed8':'#9ca3af')+';">'+(row.opd_cases>0?imNum(row.opd_cases):'—')+'</td>'
        +'<td style="padding:10px 12px;text-align:center;font-weight:'+(row.inpatient_cases>0?'700':'400')+';color:'+(row.inpatient_cases>0?'#7e22ce':'#9ca3af')+';">'+(row.inpatient_cases>0?imNum(row.inpatient_cases):'—')+'</td>'
        +'<td style="padding:10px 12px;text-align:right;color:'+(row.copayment>0?'#b45309':'#9ca3af')+';">'+(row.copayment>0?imFmt(row.copayment):'—')+'</td>'
        +'<td style="padding:10px 12px;text-align:right;font-weight:'+(row.bills_submitted>0?'600':'400')+';color:'+(row.bills_submitted>0?'#0369a1':'#9ca3af')+';">'+(row.bills_submitted>0?imFmt(row.bills_submitted):'—')+'</td>'
        +'<td style="padding:10px 12px;text-align:right;color:'+(row.bills_paid>0?'#047857':'#9ca3af')+';">'+(row.bills_paid>0?imFmt(row.bills_paid):'—')+'</td>'
        +'<td style="padding:10px 12px;text-align:center;color:'+(row.social_cases>0?'#dc2626':'#9ca3af')+';">'+(row.social_cases>0?imNum(row.social_cases):'—')+'</td>'
        +'<td style="padding:10px 12px;text-align:right;color:'+(row.social_amount>0?'#dc2626':'#9ca3af')+';">'+(row.social_amount>0?imFmt(row.social_amount):'—')+'</td>'
        +'</tr>';
    }).join('');

    // Summary cards
    document.getElementById('im-tot-opd').textContent=imNum(totOPD);
    document.getElementById('im-tot-inp').textContent=imNum(totInp);
    document.getElementById('im-tot-copay').textContent=imFmt(totCopay);
    document.getElementById('im-tot-sub').textContent=imFmt(totSub);
    document.getElementById('im-tot-paid').textContent=imFmt(totPaid);
    document.getElementById('im-tot-social').textContent=imNum(totSocial);

    // Footer totals
    tfoot.style.display='';
    document.getElementById('im-f-opd').textContent=imNum(totOPD);
    document.getElementById('im-f-inp').textContent=imNum(totInp);
    document.getElementById('im-f-copay').textContent=imFmt(totCopay);
    document.getElementById('im-f-sub').textContent=imFmt(totSub);
    document.getElementById('im-f-paid').textContent=imFmt(totPaid);
    document.getElementById('im-f-social').textContent=imNum(totSocial);
    document.getElementById('im-f-socamt').textContent=imFmt(totSocAmt);

  }catch(e){
    document.getElementById('im-loading-indicator').style.display='none';
    tbody.innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}

function imPrint(){
  const month=document.getElementById('im-month').options[document.getElementById('im-month').selectedIndex].text;
  const year=document.getElementById('im-year').value;
  const win=window.open('','_blank','width=1100,height=750');
  win.document.write('<!DOCTYPE html><html><head>'
    +'<title>Insurance Monthly Report</title>'
    +'<style>'
    +'body{font-family:Arial,sans-serif;font-size:12px;margin:20px;}'
    +'h2{text-align:center;margin-bottom:4px;}'
    +'.sub{text-align:center;color:#666;font-size:12px;margin-bottom:16px;}'
    +'table{width:100%;border-collapse:collapse;}'
    +'th{background:#1e40af;color:white;padding:8px 10px;text-align:left;font-size:11px;}'
    +'td{padding:7px 10px;border-bottom:1px solid #e5e7eb;font-size:11px;}'
    +'tr:nth-child(odd) td{background:#f8fafc;}'
    +'tfoot td{background:#1e3a8a;color:white;font-weight:700;}'
    +'</style>'
    +'</head><body>');
  win.document.write('<h2>INSURANCE MONTHLY REPORT</h2>');
  win.document.write('<p class="sub">'+document.getElementById('im-period-label').textContent+'</p>');
  win.document.write(document.getElementById('im-table').outerHTML);
  win.document.write('</body></html>');
  win.document.close();
  setTimeout(function(){ win.print(); }, 500);
}

function imExportCSV(){
  if(!imData.length){ alert('No data to export. Click Generate first.'); return; }
  const month=document.getElementById('im-month').options[document.getElementById('im-month').selectedIndex].text;
  const year=document.getElementById('im-year').value;
  const header=['Payer/Insurance','OPD Cases (New & Old)','Hospitalization Cases','CoPayment (RWF)','Bills Submitted (RWF)','Bills Paid (RWF)','Social Cases','Social Amount Billed (RWF)'];
  let totOPD=0,totInp=0,totCopay=0,totSub=0,totPaid=0,totSocial=0,totSocAmt=0;
  const rows=imData.map(function(r){
    totOPD+=r.opd_cases; totInp+=r.inpatient_cases; totCopay+=r.copayment;
    totSub+=r.bills_submitted; totPaid+=r.bills_paid; totSocial+=r.social_cases; totSocAmt+=r.social_amount;
    return ['"'+r.payer+'"',r.opd_cases,r.inpatient_cases,r.copayment,r.bills_submitted,r.bills_paid,r.social_cases,r.social_amount].join(',');
  });
  const totRow=['"GRAND TOTAL"',totOPD,totInp,totCopay.toFixed(2),totSub.toFixed(2),totPaid.toFixed(2),totSocial,totSocAmt.toFixed(2)].join(',');
  const csv='Insurance Monthly Report - '+month+' '+year+'\n\n'+header.join(',')+'\n'+rows.join('\n')+'\n'+totRow;
  const blob=new Blob([csv],{type:'text/csv'});
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob);
  a.download='insurance-monthly-'+year+'-'+document.getElementById('im-month').value+'.csv';
  a.click();
}

// Auto-load current month
imLoad();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
