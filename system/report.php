<?php
// report.php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'general';
if (!$isAdmin && in_array($subModule, ['diagnosis', 'hmis'], true)) {
    $subModule = 'general';
}
?>
<script>
const _apiBase = window.location.origin + '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/api.php';
const rptBasePath = window.location.origin + '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>';
</script>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;">Reports</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Financial and operational reports</p>
  </div>
</div>

<?php require __DIR__ . '/includes/report_tabs.php'; ?>

<?php if ($subModule == 'general'): ?>
<!-- ===== GENERAL REPORT ===== -->

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Visit Date From</label>
    <input id="rpt-from" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Visit Date To</label>
    <input id="rpt-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Insurance</label>
    <select id="rpt-ins" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;min-width:140px;">
      <option value="">All</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Category / Speciality</label>
    <select id="rpt-cat" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All Orders</option>
      <option value="consultation">Consultation Orders</option>
      <option value="pharmacy">Emergency Drugs</option>
      <option value="laboratory">Laboratory Orders</option>
      <option value="imaging">Imaging Orders</option>
      <option value="procedure">Procedure Orders</option>
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
      <option value="insurance">Insurance</option>
    </select>
  </div>
  <div style="margin-top:14px;">
    <button onclick="rptLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
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
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-left:3px solid #dc2626;"><div id="rpt-s-writtenoff" style="font-size:18px;font-weight:700;color:#dc2626;">—</div><div style="font-size:11px;color:var(--gray-500);">Written Off</div></div>
</div>

<!-- Payment Status -->
<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
  <span id="rpt-s-paidcount" style="padding:6px 14px;background:#d1fae5;color:#065f46;border-radius:20px;font-size:12px;font-weight:600;">— Paid</span>
  <span id="rpt-s-unpaidcount" style="padding:6px 14px;background:#fee2e2;color:#991b1b;border-radius:20px;font-size:12px;font-weight:600;">— Unpaid</span>
  <span id="rpt-s-partialcount" style="padding:6px 14px;background:#fef3c7;color:#92400e;border-radius:20px;font-size:12px;font-weight:600;">— Partial</span>
  <span id="rpt-s-inscount" style="padding:6px 14px;background:#dbeafe;color:#1e40af;border-radius:20px;font-size:12px;font-weight:600;">— Insurance</span>
  <span id="rpt-s-selfcount" style="padding:6px 14px;background:#f3e8ff;color:#6b21a8;border-radius:20px;font-size:12px;font-weight:600;">— Self-Pay</span>
  <span id="rpt-s-writtenoff-count" style="padding:6px 14px;background:#fee2e2;color:#991b1b;border-radius:20px;font-size:12px;font-weight:600;display:none;">— Written Off</span>
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

<!-- Payment Method / Service details modal -->
<div id="rpt-detail-modal" onclick="if(event.target===this)rptCloseDetail()" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:white;border-radius:12px;max-width:1000px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 50px rgba(0,0,0,.3);">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:16px 20px;border-bottom:1px solid var(--gray-200);">
      <div>
        <h3 id="rpt-detail-title" style="font-size:16px;font-weight:700;margin:0;text-transform:capitalize;"></h3>
        <p id="rpt-detail-sub" style="font-size:12px;color:var(--gray-500);margin:4px 0 0 0;"></p>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button onclick="rptExportDetail()" style="padding:6px 12px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;"><i class="fas fa-file-excel"></i> Excel</button>
        <button onclick="rptCloseDetail()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
      </div>
    </div>
    <div id="rpt-detail-body" style="overflow:auto;padding:0 8px 8px;"></div>
  </div>
</div>

<!-- DETAILS TAB -->
<div id="rpt-details-section" style="display:none;">

<!-- Report Table -->
<div id="rpt-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:10px 12px;text-align:center;font-weight:600;width:40px;">#</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Visit No.</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Date</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Patient</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;">Insurance</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Method</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Total</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Paid</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Deferred</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Balance</th>
      <th style="padding:10px 12px;text-align:right;font-weight:600;">Ins. Owes</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;">Invoices</th>
    </tr></thead>
    <tbody id="rpt-tbody"><tr><td colspan="14" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
    <tfoot id="rpt-tfoot" style="display:none;">
      <tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);font-weight:700;">
        <td colspan="7" style="padding:10px 12px;">TOTALS</td>
        <td id="rpt-f-total" style="padding:10px 12px;text-align:right;">—</td>
        <td id="rpt-f-paid" style="padding:10px 12px;text-align:right;color:var(--success-color);">—</td>
        <td id="rpt-f-deferred" style="padding:10px 12px;text-align:right;color:#7c3aed;">—</td>
        <td id="rpt-f-balance" style="padding:10px 12px;text-align:right;color:var(--danger-color);">—</td>
        <td id="rpt-f-insowes" style="padding:10px 12px;text-align:right;color:var(--warning-color);">—</td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
  <div id="rpt-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 14px;"></div>
</div>
</div><!-- end rpt-details-section -->

<script src="assets/js/report-general.js?v=12"></script>
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
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Total visits</label>
    <select id="vis-count" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="">All</option>
      <option value="1">First time (1 visit)</option>
      <option value="2">Second time (2 visits)</option>
      <option value="gt5">More than 5 times</option>
      <option value="gt20">More than 20 times</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Show</label>
    <select id="vis-limit" onchange="visPage=1;visLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <option value="25" selected>25 / page</option>
      <option value="50">50 / page</option>
      <option value="100">100 / page</option>
      <option value="200">200 / page</option>
      <option value="500">500 / page</option>
    </select>
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Search</label>
    <input id="vis-search" type="text" placeholder="Patient name or No..." style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:160px;" onkeydown="if(event.key==='Enter'){visPage=1;visLoad();}">
  </div>
  <div style="margin-top:14px;display:flex;gap:6px;align-items:center;">
    <button onclick="visPage=1;visLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
    <button onclick="visResetFilters()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;" title="Reset filters"><i class="fas fa-undo"></i> Reset</button>
  </div>
  <div style="margin-top:14px;display:flex;gap:4px;align-items:center;">
    <button onclick="visSetQuickDate('today')" style="padding:6px 10px;background:#f3f4f6;border:1px solid var(--gray-200);border-radius:6px;font-size:11px;cursor:pointer;font-weight:500;">Today</button>
    <button onclick="visSetQuickDate('month')" style="padding:6px 10px;background:#f3f4f6;border:1px solid var(--gray-200);border-radius:6px;font-size:11px;cursor:pointer;font-weight:500;">This Month</button>
    <button onclick="visSetQuickDate('all')" style="padding:6px 10px;background:#f3f4f6;border:1px solid var(--gray-200);border-radius:6px;font-size:11px;cursor:pointer;font-weight:500;">All Time</button>
  </div>
  <div style="margin-top:14px;margin-left:auto;">
    <button onclick="visPrint()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;" title="Print"><i class="fas fa-print"></i></button>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="vis-s-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:11px;color:var(--gray-500);">Total Patients</div></div>
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
const visAPI = _apiBase + '?endpoint=patients/visits';
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

function visSetQuickDate(type){
  const today = new Date().toISOString().split('T')[0];
  const fromEl = document.getElementById('vis-from');
  const toEl = document.getElementById('vis-to');
  if(type === 'today'){
    fromEl.value = today;
    toEl.value = today;
  } else if(type === 'month'){
    fromEl.value = today.slice(0, 7) + '-01';
    toEl.value = today;
  } else if(type === 'all'){
    fromEl.value = '';
    toEl.value = '';
  }
  visPage = 1;
  visLoad();
}

function visResetFilters(){
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('vis-from').value = today;
  document.getElementById('vis-to').value = today;
  document.getElementById('vis-status').value = '';
  document.getElementById('vis-payment').value = '';
  document.getElementById('vis-count').value = '';
  document.getElementById('vis-search').value = '';
  const lim = document.getElementById('vis-limit');
  if(lim) lim.value = '25';
  visPage = 1;
  visLoad();
}

async function visLoad(){
  const from=document.getElementById('vis-from').value;
  const to=document.getElementById('vis-to').value;
  const status=document.getElementById('vis-status').value;
  const payment=document.getElementById('vis-payment').value;
  const search=document.getElementById('vis-search').value.trim();
  const limit=document.getElementById('vis-limit')?.value || 25;

  const p=new URLSearchParams({date_from:from,date_to:to,page:visPage,limit:limit});
  if(status) p.append('status',status);
  if(payment) p.append('payment_type',payment);
  const vcount=document.getElementById('vis-count').value;
  if(vcount) p.append('visit_count',vcount);
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
      tb.innerHTML='<tr><td colspan="11" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-users" style="font-size:36px;display:block;margin-bottom:8px;"></i>No patients found for this period.</td></tr>';
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
            Showing <strong>${startIdx}–${endIdx}</strong> of <strong>${pagination.total}</strong> patients
          </span>
          <div style="display:flex;gap:4px;align-items:center;">
            <button onclick="visPage--;visLoad()" ${visPage<=1?'disabled':''} style="padding:6px 14px;border:1px solid var(--gray-200);background:${visPage<=1?'var(--gray-100)':'white'};color:${visPage<=1?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${visPage<=1?'not-allowed':'pointer'};font-size:13px;"><i class="fas fa-chevron-left"></i> Previous</button>
            ${btns}
            <button onclick="visPage++;visLoad()" ${visPage>=pagination.total_pages?'disabled':''} style="padding:6px 14px;border:1px solid var(--gray-200);background:${visPage>=pagination.total_pages?'var(--gray-100)':'white'};color:${visPage>=pagination.total_pages?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${visPage>=pagination.total_pages?'not-allowed':'pointer'};font-size:13px;">Next <i class="fas fa-chevron-right"></i></button>
          </div>
        </div>`;
    } else if (pagination && pagination.total > 0) {
      pg.innerHTML = `<div style="font-size:13px;color:var(--gray-600);width:100%;text-align:right;">Showing all <strong>${pagination.total}</strong> patients</div>`;
    } else {
      pg.innerHTML = '';
    }

  }catch(e){tb.innerHTML='<tr><td colspan="11" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

function visPrint(){
  const el=document.getElementById('vis-printarea');
  const win=window.open('','','width=1000,height=700');
  win.document.write('<html><head><title>Patients Report</title><style>body{font-family:Arial,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{padding:6px 8px;border:1px solid #ddd;text-align:left;}th{background:#f0f0f0;font-weight:600;}@media print{button{display:none;}}</style></head><body>');
  win.document.write('<h2>Patients Report ('+document.getElementById('vis-from').value+' to '+document.getElementById('vis-to').value+')</h2>');
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
    const r=await fetch(_apiBase+'?endpoint=reports/general&action=by_doctor&date_from='+from+'&date_to='+to);
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
    const r=await fetch(_apiBase+'?endpoint=reports/general&action=by_category&category='+catCategory+'&date_from='+from+'&date_to='+to);
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

<?php if ($subModule == 'diagnosis' && $isAdmin): ?>
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
    const r=await fetch(_apiBase+'?endpoint=reports/general&action=by_diagnosis&date_from='+from+'&date_to='+to);
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
    const r=await fetch(_apiBase+'?endpoint=reports/general&action=by_prescription&date_from='+from+'&date_to='+to);
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

    try {
      renderRxChart(data.slice(0,8));
    } catch(chartErr) {
      console.warn('Rx chart render error:', chartErr);
    }
  }catch(e){tb.innerHTML='<tr><td colspan="4" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';}
}

function renderRxChart(data){
  if (typeof Chart === 'undefined') return;
  const canvas = document.getElementById('rx-chart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  if(rxChart) rxChart.destroy();
  const labels=data.map(d=>(d.medication||'').substring(0,25));
  const values=data.map(d=>parseInt(d.count||0));
  const colors=['#16a34a','#2563eb','#d97706','#dc2626','#7c3aed','#0891b2','#ea580c','#be185d'];
  try {
    rxChart=new Chart(ctx,{
      type:'pie',
      data:{labels,datasets:[{data:values,backgroundColor:colors.slice(0,data.length),borderWidth:2,borderColor:'#fff'}]},
      options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11}}},tooltip:{callbacks:{label:function(ctx){return ctx.label+': '+ctx.raw+' times';}}}}}
    });
  } catch(e) {
    console.warn('Could not render rx chart:', e);
  }
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

<?php if ($subModule == 'hmis' && $isAdmin): ?>
<!-- ===== HMIS MONTHLY REPORT (auto-generated) ===== -->
<style>
.hmis-wrap{font-family:Arial,sans-serif;font-size:13px;color:#222;}
.hmis-section{background:#fff;border-radius:8px;box-shadow:var(--shadow);padding:16px 18px;margin-bottom:14px;}
.hmis-section-title{font-size:14px;font-weight:700;color:#1e40af;border-bottom:2px solid #bfdbfe;padding-bottom:6px;margin-bottom:10px;}
.hmis-sub-title{font-size:12px;font-weight:600;color:#374151;margin:10px 0 6px;}
.hmis-tbl{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:8px;}
.hmis-tbl th{background:#eff6ff;color:#1e40af;padding:5px 7px;text-align:center;border:1px solid #bfdbfe;font-size:11px;}
.hmis-tbl td{padding:4px 7px;border:1px solid #e5e7eb;vertical-align:middle;}
.hmis-tbl td.rl{background:#f9fafb;font-size:11px;color:#374151;}
.hmis-tbl td.rn{background:#f9fafb;text-align:center;font-weight:600;color:#6b7280;width:26px;}
.hmis-tbl td.val{text-align:right;font-weight:600;min-width:40px;}
.hmis-tbl td.val0{text-align:right;color:#9ca3af;}
.hmis-icd{font-size:10px;color:#9ca3af;display:block;}
.hmis-loading{padding:40px;text-align:center;color:var(--gray-400);}
@media print{
  .hmis-noprint{display:none!important;}
  .hmis-section{box-shadow:none;border:1px solid #ccc;break-inside:avoid;}
}
</style>

<!-- Filter bar -->
<div class="hmis-noprint" style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Year / Année</label>
    <input id="hmis-year" type="number" value="<?php echo date('Y'); ?>" min="2020" max="2100" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:90px;">
  </div>
  <div>
    <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Month / Mois</label>
    <select id="hmis-month" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
      <?php for($m=1;$m<=12;$m++) echo '<option value="'.$m.'"'.($m==date('n')?' selected':'').'>'.date('F',mktime(0,0,0,$m,1)).'</option>'; ?>
    </select>
  </div>
  <button onclick="hmisGenerate()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-sync-alt"></i> Generate</button>
  <button onclick="hmisPrint()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;" title="Print"><i class="fas fa-print"></i> Print</button>
  <span id="hmis-status" style="font-size:12px;color:var(--gray-500);margin-left:4px;"></span>
</div>

<!-- Tab Navigation (Ordered by Roman Numerals) -->
<div style="display:flex;gap:4px;margin-bottom:0;margin-top:14px;overflow-x:auto;flex-wrap:wrap;border-bottom:2px solid var(--gray-200);padding-bottom:4px;">
  <button id="hmis-itab-btn-opd" onclick="hmisInnerTab('opd')" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12.5px;font-weight:600;cursor:pointer;"><i class="fas fa-stethoscope"></i> II. OPD &amp; Diseases</button>
  <button id="hmis-itab-btn-malaria" onclick="hmisInnerTab('malaria')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-virus"></i> III. Malaria</button>
  <button id="hmis-itab-btn-mh" onclick="hmisInnerTab('mh')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-brain"></i> III. Mental Health</button>
  <button id="hmis-itab-btn-chronic" onclick="hmisInnerTab('chronic')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-heartbeat"></i> IV. Chronic Conditions</button>
  <button id="hmis-itab-btn-injuries" onclick="hmisInnerTab('injuries')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-band-aid"></i> V. Injuries</button>
  <button id="hmis-itab-btn-hosp" onclick="hmisInnerTab('hosp')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-bed"></i> VI. Hospitalizations</button>
  <button id="hmis-itab-btn-anc" onclick="hmisInnerTab('anc')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-baby"></i> VIII. Antenatal (ANC)</button>
  <button id="hmis-itab-btn-surgery" onclick="hmisInnerTab('surgery')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-scalpel-path"></i> IX. Surgery &amp; Anesthesia</button>
  <button id="hmis-itab-btn-obs" onclick="hmisInnerTab('obs')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-baby-carriage"></i> XII. Obstetrics</button>
  <button id="hmis-itab-btn-finances" onclick="hmisInnerTab('finances')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-money-bill-wave"></i> XIV. Finances</button>
  <button id="hmis-itab-btn-staffing" onclick="hmisInnerTab('staffing')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-users"></i> XV. Staffing</button>
  <button id="hmis-itab-btn-lab" onclick="hmisInnerTab('lab')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-flask"></i> XIX. Laboratory</button>
  <button id="hmis-itab-btn-cancer" onclick="hmisInnerTab('cancer')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-ribbon"></i> Cancer Screening</button>
  <button id="hmis-itab-btn-ins" onclick="hmisInnerTab('ins')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12.5px;font-weight:500;cursor:pointer;"><i class="fas fa-shield-alt"></i> Insurance Summary</button>
</div>

<!-- ========================================================================= -->
<!-- TAB 1: II. OPD & DISEASES                                                  -->
<!-- ========================================================================= -->
<div id="hmis-itab-opd">
<div class="hmis-wrap" id="hmis-opd-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">II. Outpatient Consultations (OPD) &amp; Diseases</div>
  <div style="font-size:12px;color:#555;margin-top:2px;">Consultations Externes et Maladies Prioritaires</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<!-- II. OPD Consultations -->
<div class="hmis-section" id="hmis-s-opd">
  <div class="hmis-section-title"><i class="fas fa-stethoscope" style="margin-right:6px;"></i>II. Outpatient Consultations / Consultations Externes</div>
  <div class="hmis-sub-title">A/B) Morbidity Summary &amp; Insurance Status</div>
  <table class="hmis-tbl">
    <thead>
      <tr><th rowspan="2" style="text-align:left;">Type</th><th colspan="2">Under 5 yrs</th><th colspan="2">&ge;5 yrs</th><th rowspan="2">Total</th><th rowspan="2">Insured</th></tr>
      <tr><th>M</th><th>F</th><th>M</th><th>F</th></tr>
    </thead>
    <tbody>
      <tr><td class="rl">New cases / Nouveaux cas</td><td class="val" id="hv-nc-u5m">—</td><td class="val" id="hv-nc-u5f">—</td><td class="val" id="hv-nc-5pm">—</td><td class="val" id="hv-nc-5pf">—</td><td class="val" id="hv-nc-tot">—</td><td class="val" id="hv-nc-ins">—</td></tr>
      <tr><td class="rl">Old cases / Anciens cas</td><td class="val" id="hv-oc-u5m">—</td><td class="val" id="hv-oc-u5f">—</td><td class="val" id="hv-oc-5pm">—</td><td class="val" id="hv-oc-5pf">—</td><td class="val" id="hv-oc-tot">—</td><td class="val0">—</td></tr>
    </tbody>
  </table>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <div style="flex:1;min-width:180px;">
      <div class="hmis-sub-title">C) Referrals</div>
      <table class="hmis-tbl"><tbody>
        <tr><td class="rl">Referred to other levels</td><td class="val" id="hv-ref-out">—</td></tr>
        <tr><td class="rl">Counter referrals received</td><td class="val" id="hv-ref-in">—</td></tr>
      </tbody></table>
    </div>
    <div style="flex:1;min-width:180px;">
      <div class="hmis-sub-title">D) Origin / Provenance</div>
      <table class="hmis-tbl"><tbody>
        <tr><td class="rl">From catchment area (zone)</td><td class="val" id="hv-orig-zone">—</td></tr>
        <tr><td class="rl">Outside zone (hors zone)</td><td class="val" id="hv-orig-out">—</td></tr>
        <tr><td class="rl">International patients / Hors pays</td><td class="val" id="hv-orig-intl">—</td></tr>
      </tbody></table>
    </div>
  </div>
</div>

<!-- E: Priority diagnoses -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-notes-medical" style="margin-right:6px;"></i>E) New Cases of Priority Health Problems in General OPD</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:26px;">#</th>
        <th style="text-align:left;min-width:260px;">Diagnosis / ICD-11</th>
        <th colspan="2">Under 5 yrs</th>
        <th colspan="2">&ge;5 yrs</th>
      </tr>
      <tr><th></th><th></th><th>M</th><th>F</th><th>M</th><th>F</th></tr>
    </thead>
    <tbody id="hmis-opde2-tbody">
      <tr><td colspan="6" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>
  <div class="hmis-noprint" id="hmis-dxreview" style="margin-top:8px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-size:12px;">
    <span id="hmis-dxreview-msg" style="color:#92400e;background:#fffbeb;border-radius:4px;padding:4px 8px;"></span>
    <button type="button" id="hmis-dxmap-open" style="display:none;border:none;background:#1e40af;color:#fff;border-radius:6px;padding:6px 10px;font-size:12px;font-weight:600;cursor:pointer;">Map diagnoses</button>
  </div>
</div>

<!-- F: Eye diseases -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-eye" style="margin-right:6px;"></i>F) Eye, Oral and Ear Diseases</div>

  <div class="hmis-sub-title">a) Eye Diseases / Maladies Oculaires</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:26px;">#</th>
        <th style="text-align:left;min-width:220px;">Condition / ICD-11</th>
        <th colspan="2">0-19 yrs</th>
        <th colspan="2">20-39 yrs</th>
        <th colspan="2">&ge;40 yrs</th>
      </tr>
      <tr><th></th><th></th><th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th></tr>
    </thead>
    <tbody id="hmis-eye-tbody">
      <tr><td colspan="8" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:10px;">b) Diseases of the Ear / Maladies de l'oreille</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:26px;">#</th>
        <th style="text-align:left;min-width:220px;">Condition</th>
        <th colspan="2">0-19 yrs</th>
        <th colspan="2">20-39 yrs</th>
        <th colspan="2">&ge;40 yrs</th>
      </tr>
      <tr><th></th><th></th><th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th></tr>
    </thead>
    <tbody id="hmis-ear-tbody">
      <tr><td colspan="8" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:10px;">c) Oral Diseases / Maladies Orales</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:26px;">#</th>
        <th style="text-align:left;min-width:220px;">Condition / ICD-11</th>
        <th colspan="2">0-19 yrs</th>
        <th colspan="2">20-39 yrs</th>
        <th colspan="2">&ge;40 yrs</th>
      </tr>
      <tr><th></th><th></th><th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th></tr>
    </thead>
    <tbody id="hmis-oral-tbody">
      <tr><td colspan="8" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:10px;">d) Dental Procedures / Actes dentaires</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:26px;">#</th>
        <th style="text-align:left;min-width:260px;">Procedure</th>
        <th style="width:80px;">Code</th>
        <th>Total</th>
      </tr>
    </thead>
    <tbody id="hmis-dental-tbody">
      <tr><td colspan="4" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>
</div>

</div><!-- end hmis-opd-printarea -->
</div><!-- end hmis-itab-opd -->

<!-- ========================================================================= -->
<!-- TAB 2: III. MALARIA                                                        -->
<!-- ========================================================================= -->
<div id="hmis-itab-malaria" style="display:none;">
<div class="hmis-wrap" id="hmis-malaria-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">III. Malaria / Paludisme</div>
  <div style="font-size:12px;color:#555;margin-top:2px;">Rapport Mensuel Paludisme / Malaria Monthly Report</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section" id="hmis-s-malaria">
  <div class="hmis-section-title"><i class="fas fa-virus" style="margin-right:6px;"></i>A) Malaria OPD / Paludisme Consultation Externe</div>
  
  <div class="hmis-sub-title">a) Malaria simple / Paludisme simple</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:30px;">#</th>
        <th style="text-align:left;min-width:320px;">Indicator / Indicateur</th>
        <th style="width:120px;">Under 5 yrs</th>
        <th style="width:120px;">5 yrs &amp; Above</th>
        <th style="width:100px;">Total</th>
      </tr>
    </thead>
    <tbody id="hmis-mal-simple-tbody">
      <tr><td colspan="5" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:14px;">b) Malaria severe / Paludisme grave</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:30px;">#</th>
        <th style="text-align:left;min-width:320px;">Indicator / Indicateur</th>
        <th style="width:120px;">Under 5 yrs</th>
        <th style="width:120px;">5 yrs &amp; Above</th>
        <th style="width:100px;">Total</th>
      </tr>
    </thead>
    <tbody id="hmis-mal-severe-tbody">
      <tr><td colspan="5" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:14px;">c) Treatment / Traitement</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:30px;">#</th>
        <th style="text-align:left;min-width:320px;">First Line Treatment / Traitement de première ligne</th>
        <th style="width:120px;">Under 5 yrs</th>
        <th style="width:120px;">5 yrs &amp; Above</th>
        <th style="width:100px;">Total</th>
      </tr>
    </thead>
    <tbody id="hmis-mal-tx1-tbody">
      <tr><td colspan="5" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:14px;">C.1) SECOND LINE TREATMENT / Traitement de deuxième ligne</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:30px;">#</th>
        <th style="text-align:left;min-width:320px;">Second Line Treatment / Traitement de deuxième ligne</th>
        <th style="width:120px;">Under 5 yrs</th>
        <th style="width:120px;">5 yrs &amp; Above</th>
        <th style="width:100px;">Total</th>
      </tr>
    </thead>
    <tbody id="hmis-mal-tx2-tbody">
      <tr><td colspan="5" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:14px;">C.2) Suspected Treatment Failure (Total) / Échec thérapeutique suspecté</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:30px;">#</th>
        <th style="text-align:left;min-width:320px;">Suspected Treatment Failure</th>
        <th style="width:120px;">Under 5 yrs</th>
        <th style="width:120px;">5 yrs &amp; Above</th>
        <th style="width:100px;">Total</th>
      </tr>
    </thead>
    <tbody id="hmis-mal-fail-susp-tbody">
      <tr><td colspan="5" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:14px;">C.3) Confirmed Treatment Failure by Microscopy (Total) / Échec thérapeutique confirmé par microscopie</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:30px;">#</th>
        <th style="text-align:left;min-width:320px;">Confirmed Treatment Failure</th>
        <th style="width:120px;">Under 5 yrs</th>
        <th style="width:120px;">5 yrs &amp; Above</th>
        <th style="width:100px;">Total</th>
      </tr>
    </thead>
    <tbody id="hmis-mal-fail-conf-tbody">
      <tr><td colspan="5" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>

  <div class="hmis-sub-title" style="margin-top:14px;">d) Malaria Cases Summary / Résumé des cas de paludisme</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th rowspan="2" style="width:30px;">#</th>
        <th rowspan="2" style="text-align:left;min-width:280px;">Indicator</th>
        <th colspan="2">Under 5 yrs</th>
        <th colspan="2">&ge;5 Yrs</th>
        <th rowspan="2" style="width:100px;">Total</th>
      </tr>
      <tr>
        <th style="width:90px;">Male</th>
        <th style="width:90px;">Female</th>
        <th style="width:90px;">Male</th>
        <th style="width:90px;">Female</th>
      </tr>
    </thead>
    <tbody id="hmis-mal-summary-tbody">
      <tr><td colspan="7" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>
</div>

</div><!-- end hmis-malaria-printarea -->
</div><!-- end hmis-itab-malaria -->

<!-- ========================================================================= -->
<!-- TAB 3: III. MENTAL HEALTH                                                  -->
<!-- ========================================================================= -->
<div id="hmis-itab-mh" style="display:none;">
<div class="hmis-wrap" id="hmis-mh-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">III. Mental Health / Santé mentale</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<!-- A. Summary -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-brain" style="margin-right:6px;"></i>A) Summary / Résumé</div>
  <table class="hmis-tbl">
    <thead><tr><th>#</th><th style="text-align:left;">Indicator</th><th>Number</th></tr></thead>
    <tbody id="hmis-mh-summary-tbody">
      <tr><td colspan="3" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
</div>

<!-- B. Diagnosis -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-notes-medical" style="margin-right:6px;"></i>B) Diagnosis / Diagnostique</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th rowspan="3" style="width:26px;">#</th>
        <th rowspan="3" style="text-align:left;min-width:240px;">Diagnosis / ICD-11</th>
        <th colspan="8">NEW Cases</th>
        <th colspan="8">OLD Cases</th>
      </tr>
      <tr>
        <th colspan="2">0-14</th><th colspan="2">15-19</th><th colspan="2">20-64</th><th colspan="2">65+</th>
        <th colspan="2">0-14</th><th colspan="2">15-19</th><th colspan="2">20-64</th><th colspan="2">65+</th>
      </tr>
      <tr>
        <th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th>
        <th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th>
      </tr>
    </thead>
    <tbody id="hmis-mh-diag-tbody">
      <tr><td colspan="18" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>
</div>

</div><!-- end hmis-mh-printarea -->
</div><!-- end hmis-itab-mh -->

<!-- ========================================================================= -->
<!-- TAB 4: IV. CHRONIC CONDITIONS                                              -->
<!-- ========================================================================= -->
<div id="hmis-itab-chronic" style="display:none;">
<div class="hmis-wrap" id="hmis-chronic-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">IV. Chronic Medical Conditions / Maladies Chroniques</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section" id="hmis-s-chronic">
  <div class="hmis-section-title"><i class="fas fa-heartbeat" style="margin-right:6px;"></i>IV. Chronic Medical Conditions</div>
  <table class="hmis-tbl">
    <thead>
      <tr><th rowspan="2">#</th><th rowspan="2" style="text-align:left;">Condition</th><th colspan="4">New Cases</th><th colspan="4">Old Cases</th></tr>
      <tr><th colspan="2">0-39 yrs</th><th colspan="2">&ge;40 yrs</th><th colspan="2">0-39 yrs</th><th colspan="2">&ge;40 yrs</th></tr>
      <tr><th></th><th></th><th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th><th>M</th><th>F</th></tr>
    </thead>
    <tbody id="hmis-chronic-tbody">
      <tr><td colspan="10" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
</div>

</div><!-- end hmis-chronic-printarea -->
</div><!-- end hmis-itab-chronic -->

<!-- ========================================================================= -->
<!-- TAB 5: V. INJURIES                                                         -->
<!-- ========================================================================= -->
<div id="hmis-itab-injuries" style="display:none;">
<div class="hmis-wrap" id="hmis-injuries-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">V. Injuries &amp; Trauma / Traumatismes</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section" id="hmis-s-injuries">
  <div class="hmis-section-title"><i class="fas fa-band-aid" style="margin-right:6px;"></i>V. Injuries</div>
  <table class="hmis-tbl">
    <thead><tr><th>#</th><th style="text-align:left;">Cause</th><th>Under 5</th><th>&ge;5 yrs</th></tr></thead>
    <tbody id="hmis-inj-tbody">
      <tr><td colspan="4" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
</div>

</div><!-- end hmis-injuries-printarea -->
</div><!-- end hmis-itab-injuries -->

<!-- ========================================================================= -->
<!-- TAB 6: VI. HOSPITALIZATIONS                                                -->
<!-- ========================================================================= -->
<!-- ========================================================================= -->
<!-- TAB 6: VI. HOSPITALIZATIONS                                                -->
<!-- ========================================================================= -->
<div id="hmis-itab-hosp" style="display:none;">
<div class="hmis-wrap" id="hmis-hosp-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">VI. Hospitalizations / Hospitalisations</div>
  <div style="font-size:12px;color:#555;margin-top:2px;">Mouvement des malades et Consultations / Pathologies des Hospitalisés (Ward)</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<!-- Section 1: Hospitalization Movement & Capacity (From ward.php) -->
<div class="hmis-section" id="hmis-s-hosp">
  <div class="hmis-section-title"><i class="fas fa-bed" style="margin-right:6px;"></i>VI-A) Hospitalization Movement &amp; Capacity / Mouvement des hospitalisés</div>
  <div style="display:flex;gap:14px;flex-wrap:wrap;">
    <div style="flex:1;min-width:220px;">
      <table class="hmis-tbl"><tbody>
        <tr><td class="rl">A) Present at beginning of month</td><td class="val" id="hv-hosp-a">—</td></tr>
        <tr><td class="rl">B) Admissions during month</td><td class="val" id="hv-hosp-b">—</td></tr>
        <tr><td class="rl" style="padding-left:14px;">C1. Cured / Guéris</td><td class="val" id="hv-hosp-c1">—</td></tr>
        <tr><td class="rl" style="padding-left:14px;">C2. Deaths / Décès</td><td class="val" id="hv-hosp-c2">—</td></tr>
        <tr><td class="rl" style="padding-left:14px;">C3. Unauthorized discharge</td><td class="val">0</td></tr>
        <tr><td class="rl" style="padding-left:14px;">C4. Referred to district hospital</td><td class="val" id="hv-hosp-c4">—</td></tr>
        <tr><td class="rl">D) Present at end of month</td><td class="val" id="hv-hosp-d">—</td></tr>
      </tbody></table>
    </div>
    <div style="flex:1;min-width:220px;">
      <table class="hmis-tbl"><tbody>
        <tr><td class="rl">E) Number of beds</td><td class="val" id="hv-hosp-e">—</td></tr>
        <tr><td class="rl">F) Insured admissions</td><td class="val" id="hv-hosp-f">—</td></tr>
        <tr><td class="rl">G) Potential hospitalization days</td><td class="val" id="hv-hosp-g">—</td></tr>
        <tr><td class="rl">H) Actual hospitalization days</td><td class="val" id="hv-hosp-h">—</td></tr>
        <tr><td class="rl">I) Total stay days (discharged)</td><td class="val" id="hv-hosp-i">—</td></tr>
      </tbody></table>
    </div>
  </div>
  <div class="hmis-sub-title">J) Summary by Age</div>
  <table class="hmis-tbl">
    <thead><tr><th style="text-align:left;">Indicator</th><th>Under 1 yr</th><th>1–4 yrs</th><th>&ge;5 yrs</th></tr></thead>
    <tbody>
      <tr><td class="rl">Hospitalized (Admissions)</td>
        <td class="val" id="hv-hosp-ju1">—</td><td class="val" id="hv-hosp-j14">—</td><td class="val" id="hv-hosp-j5p">—</td></tr>
      <tr><td class="rl">Deaths / Décès</td>
        <td class="val" id="hv-hosp-du1">—</td><td class="val" id="hv-hosp-d14">—</td><td class="val" id="hv-hosp-d5p">—</td></tr>
    </tbody>
  </table>
</div>

<!-- Section 2: Inpatient Priority Diagnoses -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-notes-medical" style="margin-right:6px;"></i>VI-C) Priority Health Problems in Hospitalization (Inpatients)</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:26px;">#</th>
        <th style="text-align:left;min-width:260px;">Diagnosis / ICD-11</th>
        <th colspan="2">Under 5 yrs</th>
        <th colspan="2">&ge;5 yrs</th>
      </tr>
      <tr><th></th><th></th><th>M</th><th>F</th><th>M</th><th>F</th></tr>
    </thead>
    <tbody id="hmis-hosp-diag-tbody">
      <tr><td colspan="6" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>
</div>

</div><!-- end hmis-hosp-printarea -->
</div><!-- end hmis-itab-hosp -->

<!-- ========================================================================= -->
<!-- TAB 7: VIII. ANTENATAL CARE (ANC)                                          -->
<!-- ========================================================================= -->
<div id="hmis-itab-anc" style="display:none;">
<div class="hmis-wrap" id="hmis-anc-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">VIII. Antenatal Consultations / Consultations Prénatales (ANC)</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section" id="hmis-s-anc">
  <div class="hmis-section-title"><i class="fas fa-baby" style="margin-right:6px;"></i>VIII. Antenatal Consultations / Consultations prénatales</div>
  <table class="hmis-tbl">
    <thead><tr><th>#</th><th style="text-align:left;">Indicator</th><th>Value</th></tr></thead>
    <tbody id="hmis-anc-tbody">
      <tr><td colspan="3" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
</div>

</div><!-- end hmis-anc-printarea -->
</div><!-- end hmis-itab-anc -->

<!-- ========================================================================= -->
<!-- TAB 8: IX. SURGERY & X. ANESTHESIA                                         -->
<!-- ========================================================================= -->
<div id="hmis-itab-surgery" style="display:none;">
<div class="hmis-wrap" id="hmis-surgery-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">IX. Surgery Services &amp; X. Anesthesia</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-cut" style="margin-right:6px;"></i>IX. Surgery Services / Services Chirurgicaux</div>
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th colspan="2" style="text-align:left;">Type of Surgical Intervention</th>
        <th>Total Interventions</th>
        <th>Post-surgical Infection</th>
      </tr>
    </thead>
    <tbody id="hmis-surgery-tbody">
      <tr><td colspan="4" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
</div>

<!-- X. Anesthesia -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-lungs" style="margin-right:6px;"></i>X. Anesthesia / Anesthésie</div>
  <table class="hmis-tbl">
    <thead><tr><th style="text-align:left;">#</th><th style="text-align:left;">Type</th><th>Total</th></tr></thead>
    <tbody>
      <tr><td class="rn">1</td><td class="rl">General Anesthesia / Anesthésie générale</td><td class="val" id="hv-anes-general">—</td></tr>
      <tr><td class="rn">2</td><td class="rl">Regional Anesthesia / Anesthésie régionale</td><td class="val" id="hv-anes-regional">—</td></tr>
      <tr><td class="rn">3</td><td class="rl">Local Anesthesia / Anesthésie locale</td><td class="val" id="hv-anes-local">—</td></tr>
    </tbody>
  </table>

  <div class="hmis-sub-title" style="margin-top:12px;">Waiting List / Liste d'attente</div>
  <table class="hmis-tbl">
    <thead>
      <tr><th style="text-align:left;">Indicator</th><th>Major</th><th>Minor</th><th>Total</th></tr>
    </thead>
    <tbody>
      <tr>
        <td class="rl">Patients on waiting list &lt; 1 month</td>
        <td class="val" id="hv-wl-u1m-major">—</td><td class="val" id="hv-wl-u1m-minor">—</td><td class="val" id="hv-wl-u1m-tot">—</td>
      </tr>
      <tr>
        <td class="rl">Patients on waiting list &gt; 1 month</td>
        <td class="val" id="hv-wl-o1m-major">—</td><td class="val" id="hv-wl-o1m-minor">—</td><td class="val" id="hv-wl-o1m-tot">—</td>
      </tr>
      <tr>
        <td class="rl">Patients who underwent surgery after waiting &gt; 3 months (this month)</td>
        <td colspan="2" class="val0" style="text-align:center;">—</td><td class="val" id="hv-wl-o3m-done">—</td>
      </tr>
    </tbody>
  </table>
</div>

</div><!-- end hmis-surgery-printarea -->
</div><!-- end hmis-itab-surgery -->

<!-- ========================================================================= -->
<!-- TAB 9: XII. OBSTETRICS                                                     -->
<!-- ========================================================================= -->
<div id="hmis-itab-obs" style="display:none;">
<div class="hmis-wrap" id="hmis-obs-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">XII. Obstetrical Complications &amp; Deliveries</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<!-- A. Cases and Deaths -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-procedures" style="margin-right:6px;"></i>A) Cases and Deaths / Cas et Décès</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="width:26px;">#</th>
        <th style="text-align:left;min-width:280px;">Condition / ICD-10</th>
        <th>OPD New Cases</th>
        <th>Hospitalized</th>
        <th>Deaths</th>
      </tr>
    </thead>
    <tbody id="hmis-obs-tbody">
      <tr><td colspan="5" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>
</div>

<!-- B. Emergency Obstetric Interventions -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-ambulance" style="margin-right:6px;"></i>B) Emergency Obstetric &amp; Neonatal Care Interventions</div>
  <table class="hmis-tbl">
    <thead><tr><th>#</th><th style="text-align:left;">Intervention</th><th>Total</th></tr></thead>
    <tbody id="hmis-eonc-tbody">
      <tr><td colspan="3" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
</div>

<!-- C. Deliveries and Births -->
<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-baby-carriage" style="margin-right:6px;"></i>C) Deliveries and Births at Health Facility</div>
  <table class="hmis-tbl">
    <thead><tr><th>#</th><th style="text-align:left;">Indicator</th><th>Total</th></tr></thead>
    <tbody id="hmis-deliv2-tbody">
      <tr><td colspan="3" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
</div>

</div><!-- end hmis-obs-printarea -->
</div><!-- end hmis-itab-obs -->

<!-- ========================================================================= -->
<!-- TAB 10: XIV. FINANCES                                                      -->
<!-- ========================================================================= -->
<div id="hmis-itab-finances" style="display:none;">
<div class="hmis-wrap" id="hmis-finances-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">XIV. Finances / Finances</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section" id="hmis-s-fin">
  <div class="hmis-section-title"><i class="fas fa-money-bill-wave" style="margin-right:6px;"></i>XIV. Finances</div>
  <div style="display:flex;gap:14px;flex-wrap:wrap;">
    <div style="flex:1;min-width:220px;">
      <div class="hmis-sub-title">A) Revenues</div>
      <table class="hmis-tbl">
        <thead><tr><th>#</th><th style="text-align:left;">Source</th><th>Amount (RWF)</th></tr></thead>
        <tbody id="hmis-rev-tbody">
          <tr><td colspan="3" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
        </tbody>
      </table>
    </div>
    <div style="flex:1;min-width:220px;">
      <div class="hmis-sub-title">B) Expenditures</div>
      <table class="hmis-tbl">
        <thead><tr><th>#</th><th style="text-align:left;">Description</th><th>Amount (RWF)</th></tr></thead>
        <tbody>
          <tr><td class="rn">1</td><td class="rl">Medicines &amp; consumables</td><td class="val0">N/A</td></tr>
          <tr><td class="rn">2</td><td class="rl">Capital expenses</td><td class="val0">N/A</td></tr>
          <tr><td class="rn">3</td><td class="rl">Operating recurrent expenses</td><td class="val0">N/A</td></tr>
          <tr><td class="rn">4</td><td class="rl">Digital health expenses</td><td class="val0">N/A</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div><!-- end hmis-finances-printarea -->
</div><!-- end hmis-itab-finances -->

<!-- ========================================================================= -->
<!-- TAB 11: XV. STAFFING                                                       -->
<!-- ========================================================================= -->
<div id="hmis-itab-staffing" style="display:none;">
<div class="hmis-wrap" id="hmis-staffing-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">XV. Staffing Information / Personnel</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section" id="hmis-s-staff">
  <div class="hmis-section-title"><i class="fas fa-users" style="margin-right:6px;"></i>XV. Staffing Information</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr><th rowspan="2">#</th><th rowspan="2" style="text-align:left;min-width:180px;">Category</th><th colspan="2">Gender</th><th colspan="6">Age Group</th><th colspan="2">Nationality</th></tr>
      <tr><th>M</th><th>F</th><th>&lt;25</th><th>25-34</th><th>35-44</th><th>45-54</th><th>55-64</th><th>&gt;65</th><th>Rwandan</th><th>Foreign</th></tr>
    </thead>
    <tbody id="hmis-staff-tbody">
      <tr><td colspan="12" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>
</div>

</div><!-- end hmis-staffing-printarea -->
</div><!-- end hmis-itab-staffing -->

<!-- ========================================================================= -->
<!-- TAB 12: XIX. LABORATORY                                                    -->
<!-- ========================================================================= -->
<div id="hmis-itab-lab" style="display:none;">
<div class="hmis-wrap" id="hmis-lab-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">XIX. Laboratory Results / Laboratoire</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-flask" style="margin-right:6px;"></i>XIX. Laboratory / Laboratoire</div>
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th colspan="2" style="text-align:left;">Exam / Examen</th>
        <th>Positives</th><th>Negatives</th><th>Total &lt;5 yrs</th><th>Total &gt;5 yrs</th><th>TOTAL</th>
      </tr>
    </thead>
    <tbody>
      <!-- 1. Blood Smears -->
      <tr style="background:#eff6ff;">
        <td class="rn">1</td><td class="rl" style="font-weight:600;">Blood Smears Plasmodium</td>
        <td class="val" id="hv-lab-bs-pos">—</td><td class="val" id="hv-lab-bs-neg">—</td>
        <td class="val" id="hv-lab-bs-u5">—</td><td class="val" id="hv-lab-bs-5p">—</td>
        <td class="val" id="hv-lab-bs-tot">—</td>
      </tr>
      <tr><td class="rn"></td><td class="rl" style="padding-left:20px;">1.1 Plasmodium Falciparum</td><td class="val" id="hv-lab-pf-pos">—</td><td class="val" id="hv-lab-pf-neg">—</td><td class="val0">—</td><td class="val0">—</td><td class="val" id="hv-lab-pf-tot">—</td></tr>
      <tr><td class="rn"></td><td class="rl" style="padding-left:20px;">1.2 Plasmodium Malaria</td><td class="val" id="hv-lab-pm-pos">—</td><td class="val" id="hv-lab-pm-neg">—</td><td class="val0">—</td><td class="val0">—</td><td class="val" id="hv-lab-pm-tot">—</td></tr>
      <tr><td class="rn"></td><td class="rl" style="padding-left:20px;">1.3 Plasmodium Vivax</td><td class="val" id="hv-lab-pv-pos">—</td><td class="val" id="hv-lab-pv-neg">—</td><td class="val0">—</td><td class="val0">—</td><td class="val" id="hv-lab-pv-tot">—</td></tr>
      <tr><td class="rn"></td><td class="rl" style="padding-left:20px;">1.4 Plasmodium Knowlesi</td><td class="val" id="hv-lab-pk-pos">—</td><td class="val" id="hv-lab-pk-neg">—</td><td class="val0">—</td><td class="val0">—</td><td class="val" id="hv-lab-pk-tot">—</td></tr>
      <!-- 2. RDT -->
      <tr style="background:#eff6ff;">
        <td class="rn">2</td><td class="rl" style="font-weight:600;">Rapid Diagnostic Tests for Malaria</td>
        <td class="val" id="hv-lab-rdt-pos">—</td><td class="val" id="hv-lab-rdt-neg">—</td>
        <td class="val" id="hv-lab-rdt-u5">—</td><td class="val" id="hv-lab-rdt-5p">—</td>
        <td class="val" id="hv-lab-rdt-tot">—</td>
      </tr>
      <!-- 3. Stools -->
      <tr style="background:#eff6ff;">
        <td class="rn">3</td><td class="rl" style="font-weight:600;">Stools Samples TOTAL</td>
        <td class="val" id="hv-lab-st-pos">—</td><td class="val" id="hv-lab-st-neg">—</td>
        <td class="val" id="hv-lab-st-u5">—</td><td class="val" id="hv-lab-st-5p">—</td>
        <td class="val" id="hv-lab-st-tot">—</td>
      </tr>
    </tbody>
  </table>

  <!-- Stool parasites sub-table -->
  <div class="hmis-sub-title" style="margin-top:8px;">Of which Positive / Age group</div>
  <table class="hmis-tbl">
    <thead>
      <tr><th style="text-align:left;">Parasite</th><th>0-4 yrs</th><th>5-15 yrs</th><th>16+ yrs</th><th>Total Positive</th></tr>
    </thead>
    <tbody id="hmis-stool-tbody">
      <tr><td colspan="5" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>

  <!-- 4. Urine -->
  <div class="hmis-sub-title" style="margin-top:10px;">4. Urine</div>
  <table class="hmis-tbl">
    <thead><tr><th style="text-align:left;">Test</th><th>Positives</th><th>Negatives</th><th>Total</th></tr></thead>
    <tbody>
      <tr><td class="rl">4.1 Sugar / Sucre</td><td class="val" id="hv-lab-u41-pos">—</td><td class="val" id="hv-lab-u41-neg">—</td><td class="val" id="hv-lab-u41-tot">—</td></tr>
      <tr><td class="rl">4.2 Albumin / Albumine</td><td class="val" id="hv-lab-u42-pos">—</td><td class="val" id="hv-lab-u42-neg">—</td><td class="val" id="hv-lab-u42-tot">—</td></tr>
      <tr><td class="rl">4.3 Pregnancy test / Test de grossesse</td><td class="val" id="hv-lab-u43-pos">—</td><td class="val" id="hv-lab-u43-neg">—</td><td class="val" id="hv-lab-u43-tot">—</td></tr>
    </tbody>
  </table>

  <!-- 5. Bacteriology -->
  <div class="hmis-sub-title" style="margin-top:10px;">5. Bacteriology / Bactériologie</div>
  <table class="hmis-tbl">
    <thead><tr><th style="text-align:left;">Test</th><th>Positives</th><th>Negatives</th><th>Of which diplococcus gram(-)</th></tr></thead>
    <tbody>
      <tr><td class="rl">5.1 Vaginal swab – Fresh</td><td class="val" id="hv-lab-b51af-pos">—</td><td class="val" id="hv-lab-b51af-neg">—</td><td class="val" id="hv-lab-b51af-dip">—</td></tr>
      <tr><td class="rl">5.1 Vaginal swab – Gram</td><td class="val" id="hv-lab-b51ag-pos">—</td><td class="val" id="hv-lab-b51ag-neg">—</td><td class="val" id="hv-lab-b51ag-dip">—</td></tr>
      <tr><td class="rl">5.2 Urethral swab – Fresh</td><td class="val" id="hv-lab-b52af-pos">—</td><td class="val" id="hv-lab-b52af-neg">—</td><td class="val" id="hv-lab-b52af-dip">—</td></tr>
      <tr><td class="rl">5.2 Urethral swab – Gram</td><td class="val" id="hv-lab-b52ag-pos">—</td><td class="val" id="hv-lab-b52ag-neg">—</td><td class="val" id="hv-lab-b52ag-dip">—</td></tr>
      <tr><td class="rl">5.3 Urine – Fresh</td><td class="val" id="hv-lab-b53af-pos">—</td><td class="val" id="hv-lab-b53af-neg">—</td><td class="val" id="hv-lab-b53af-dip">—</td></tr>
      <tr><td class="rl">5.3 Urine – Gram</td><td class="val" id="hv-lab-b53ag-pos">—</td><td class="val" id="hv-lab-b53ag-neg">—</td><td class="val" id="hv-lab-b53ag-dip">—</td></tr>
    </tbody>
  </table>

  <!-- 6. Blood -->
  <div class="hmis-sub-title" style="margin-top:10px;">6. Blood / Sang</div>
  <table class="hmis-tbl">
    <thead><tr><th style="text-align:left;">Test</th><th>Positives</th><th>Negatives</th><th>Total</th></tr></thead>
    <tbody>
      <tr><td class="rl">6.1 RPR</td><td class="val" id="hv-lab-b61-pos">—</td><td class="val" id="hv-lab-b61-neg">—</td><td class="val" id="hv-lab-b61-tot">—</td></tr>
      <tr><td class="rl">6.2 HIV final result</td><td class="val" id="hv-lab-b62-pos">—</td><td class="val" id="hv-lab-b62-neg">—</td><td class="val" id="hv-lab-b62-tot">—</td></tr>
      <tr><td class="rl">6.3 ESR / VS</td><td class="val" id="hv-lab-b63-pos">—</td><td class="val" id="hv-lab-b63-neg">—</td><td class="val" id="hv-lab-b63-tot">—</td></tr>
      <tr><td class="rl">6.4 Full Blood Count (FBC/NFS)</td><td class="val" id="hv-lab-b64-pos">—</td><td class="val" id="hv-lab-b64-neg">—</td><td class="val" id="hv-lab-b64-tot">—</td></tr>
      <tr><td class="rl">6.5 ALAT/GPT (Alanine Aminotransferase)</td><td class="val" id="hv-lab-b65-pos">—</td><td class="val" id="hv-lab-b65-neg">—</td><td class="val" id="hv-lab-b65-tot">—</td></tr>
      <tr><td class="rl">6.6 Creatinine</td><td class="val" id="hv-lab-b66-pos">—</td><td class="val" id="hv-lab-b66-neg">—</td><td class="val" id="hv-lab-b66-tot">—</td></tr>
      <tr><td class="rl">6.7 Blood glucose</td><td class="val" id="hv-lab-b67-pos">—</td><td class="val" id="hv-lab-b67-neg">—</td><td class="val" id="hv-lab-b67-tot">—</td></tr>
      <tr><td class="rl">6.8 Amylase</td><td class="val" id="hv-lab-b68-pos">—</td><td class="val" id="hv-lab-b68-neg">—</td><td class="val" id="hv-lab-b68-tot">—</td></tr>
      <tr><td class="rl">6.9 CD4</td><td class="val" id="hv-lab-b69-pos">—</td><td class="val" id="hv-lab-b69-neg">—</td><td class="val" id="hv-lab-b69-tot">—</td></tr>
      <tr><td class="rl">6.10 Pregnancy test / Test de grossesse</td><td class="val" id="hv-lab-b610-pos">—</td><td class="val" id="hv-lab-b610-neg">—</td><td class="val" id="hv-lab-b610-tot">—</td></tr>
      <tr><td class="rl">6.11 CRAG (Serum Cryptococcal Antigen)</td><td class="val" id="hv-lab-b611-pos">—</td><td class="val" id="hv-lab-b611-neg">—</td><td class="val" id="hv-lab-b611-tot">—</td></tr>
      <tr><td class="rl">6.12 HBV (Hepatitis B Virus)</td><td class="val" id="hv-lab-b612-pos">—</td><td class="val" id="hv-lab-b612-neg">—</td><td class="val" id="hv-lab-b612-tot">—</td></tr>
      <tr><td class="rl">6.13 HCV (Hepatitis C Virus)</td><td class="val" id="hv-lab-b613-pos">—</td><td class="val" id="hv-lab-b613-neg">—</td><td class="val" id="hv-lab-b613-tot">—</td></tr>
    </tbody>
  </table>

  <!-- 7 & 8 -->
  <div class="hmis-sub-title" style="margin-top:10px;">7–8. Other Tests</div>
  <table class="hmis-tbl">
    <thead><tr><th style="text-align:left;">Test</th><th>Positives</th><th>Negatives</th><th>Total</th></tr></thead>
    <tbody>
      <tr><td class="rl">7. Cerebro Spinal Fluid (CSF)</td><td class="val" id="hv-lab-csf-pos">—</td><td class="val" id="hv-lab-csf-neg">—</td><td class="val" id="hv-lab-csf-tot">—</td></tr>
      <tr><td class="rl">8. Other Lab tests / Autres tests</td><td class="val" id="hv-lab-oth-pos">—</td><td class="val" id="hv-lab-oth-neg">—</td><td class="val" id="hv-lab-oth-tot">—</td></tr>
      <tr style="background:#eff6ff;font-weight:700;">
        <td class="rl"><strong>TOTAL Tests Conducted in Laboratory</strong></td>
        <td class="val" id="hv-lab-grand-pos">—</td>
        <td class="val" id="hv-lab-grand-neg">—</td>
        <td class="val" id="hv-lab-grand-tot">—</td>
      </tr>
    </tbody>
  </table>
</div>

</div><!-- end hmis-lab-printarea -->
</div><!-- end hmis-itab-lab -->

<!-- ========================================================================= -->
<!-- TAB 13: CANCER SCREENING                                                   -->
<!-- ========================================================================= -->
<div id="hmis-itab-cancer" style="display:none;">
<div class="hmis-wrap" id="hmis-cancer-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">Cancer Screening / Dépistage du Cancer</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-ribbon" style="margin-right:6px;color:#ec4899;"></i>Cancer Screening Indicators</div>
  <table class="hmis-tbl">
    <thead>
      <tr><th>#</th><th style="text-align:left;">Indicator</th><th>Total</th></tr>
    </thead>
    <tbody id="hmis-cancer-tbody">
      <tr><td colspan="3" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
</div>

</div><!-- end hmis-cancer-printarea -->
</div><!-- end hmis-itab-cancer -->

<!-- ========================================================================= -->
<!-- TAB 14: INSURANCE SUMMARY                                                  -->
<!-- ========================================================================= -->
<div id="hmis-itab-ins" style="display:none;">
<div class="hmis-wrap" id="hmis-ins-printarea">

<div class="hmis-section" style="text-align:center;padding:12px;">
  <div style="font-size:17px;font-weight:700;">Insurance Summary / Résumé des Assurances</div>
  <div class="hmis-period-label" style="font-size:13px;font-weight:600;color:#1e40af;margin-top:4px;"></div>
</div>

<div class="hmis-section">
  <div class="hmis-section-title"><i class="fas fa-shield-alt" style="margin-right:6px;color:#2563eb;"></i>Insurance Summary by Provider</div>
  <div style="overflow-x:auto;">
  <table class="hmis-tbl">
    <thead>
      <tr>
        <th style="text-align:left;min-width:220px;">#&nbsp; Item</th>
        <th>CBHI</th><th>RAMA</th><th>MMI</th><th>UR</th>
        <th>RADIANT</th><th>Old Mutual (UAP)</th><th>SANLAM</th>
        <th>BRITAM</th><th>EDEN CARE</th><th>Other Insurance</th>
        <th>Non-Insured</th>
      </tr>
    </thead>
    <tbody id="hmis-ins-tbody">
      <tr><td colspan="12" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>
    </tbody>
  </table>
  </div>
</div>

</div><!-- end hmis-ins-printarea -->
</div><!-- end hmis-itab-ins -->

<script>
const HMIS_API = _apiBase + '?endpoint=reports/hmis';
const HMIS_DXMAP_API = _apiBase + '?endpoint=reports/hmis-dx-map';
const MONTHS = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

const diagLabels = {
  1:'Diarrheal diseases',2:'Intestinal parasites',3:'Snake bite',4:'Pneumonia',
  5:'Bronchitis',6:'Acute Respiratory diseases other',7:'Malnutrition',
  8:'HIV/AIDS opportunistic Infection',9:'Gastritis and duodenitis',10:'Skin Diseases',
  11:'Scabies',12:'Tungiasis / Jigger',13:'Abscesses',14:'Anemia',
  15:'Viral hepatitis (B,C Chronic)',16:'Urinary tract diseases',
  17:'Bone and Joint Diseases (non-fracture)',18:'Bone and Joint Fractures',
  19:'Physical traumas (non-fracture)',20:'Sepsis',21:'Goitre',22:'Gynecological problems',
  23:'Bone diseases with deformities'
};
const malLabels = [
  'All fever cases received',
  'Simple Malaria not in Pregnancy',
  'Simple Malaria in Pregnancy',
  'Simple Malaria in post-partum',
  'Simple Malaria with Minor Digestive Symptoms (not preg.)',
  'Simple Malaria with Digestive Symptoms in Pregnancy',
  'Simple Malaria with Digestive Symptoms in post-partum',
];
const malKeys = ['all_fever','simple_not_preg','simple_preg','simple_postpart','simple_digest','simple_dig_preg','simple_dig_pp'];

const chronicLabels = {
  1:'Suspected Cancer',2:'Asthma',3:'Other Chronic respiratory diseases',
  4:'Diabetes Type 1',5:'Diabetes Type 2',6:'Diabetes gestational',7:'Hypertension'
};
const ancLabels = [
  'ANC New Registrations','ANC new registrations under 14 years',
  'ANC new registrations aged 14-17 years','ANC new registrations aged 18-19 years',
  'ANC First standard visit (Within 12 Weeks)','ANC 4 standard visits',
  'ANC risk pregnancy detected'
];
const delivLabels = [
  'Deliveries total at health facility','Deliveries by Caesarean Section',
  'Live Births','Still births (fresh ≥28 wks)','Still births (macerated ≥28 wks)',
  'Maternal deaths during labor/delivery/24h'
];
const revLabels = [
  'Mutuelle RSSB','RAMA-RSSB','MMI','SORAS','RADIANT','SAHAM',
  'UAP Rwanda','Britam Insurance','Prime Insurance','Mutuelle UR',
  'FARG','Co-Payment (10%,15%,…)','Out Of Pocket – OOP (100% private)',
  'Private Employers','Medical tourism revenues','Other revenues'
];
const staffLabels = {
  1:'Doctors (Specialist)',2:'Doctors (Generalist)',3:'Dental Surgeons',
  4:'Nurses',5:'Midwives',6:'Dentist therapist',7:'Pharmacists',
  8:'Lab technicians',9:'Ophthalmology clinical Officers',
  10:'Non-Physician Anesthetists',11:'Nutritionist',12:'Mental Health Nurses',
  13:'Medical Imagery Technologists',14:'Environmental Health officers',
  15:'Biomedical technician',16:'Physiotherapist',
  17:'Health Information System Staff',18:'Social Worker',
  19:'Clinical Psychologist',20:'Administrative and Support Personnel'
};

const opdeLabels2 = [
  ['Diarrheal diseases / Maladies diarrhéiques',''],
  ['Intestinal parasites / Parasitose intestinale','B65-B83'],
  ['Snake bite / Morsure de serpent',''],
  ['Pneumonia / Pneumonie','J09-J10'],
  ['Bronchitis',''],
  ['Acute Respiratory diseases other / Maladies aigues des voies respiratoires autres',''],
  ['Acute malnutrition',''],
  ['HIV/AIDS related opportunistic Infection',''],
  ['Gastritis and duodenitis / Gastrite et Duodénite','K29.9'],
  ['Skin Diseases / Maladie de la peau',''],
  ['Scabies (Crusted or classic scabies)',''],
  ['Tungiasis / Jigger Disease',''],
  ['Abscesses / Abcès',''],
  ['Anemia / Anémie (confirmée)','D64.9'],
  ['Viral hepatitis (B, C Chronic) / Hépatite B,C Chronique','B15-B19'],
  ['Diseases of urinary tract system / Maladies du système urinaire',''],
  ['Bone and Joint Diseases',''],
  ['Bone and Joint injuries / Fracture osseuse et articulaire',''],
  ['Physical traumas, other than fractures',''],
  ['Sepsis / Septicémie','IG40, IG41'],
  ['Goitre','E04.9'],
  ['Gynecological problems / Problèmes gynécologiques',''],
  ['Bone diseases with deformities',''],
];

const eyeLabels = [
  ['Glaucoma / Glaucome','H40'],['Cataract / Cataracte','H29.9'],
  ['Refractive error / Erreurs de réfraction','H52.7'],['Pinguecula / Pinguécula',''],
  ['Uveitis / Uvéite','H20.9'],['Presbyopia / Presbytie',''],
  ['Diabetic retinopathy / Rétinopathie diabétique','H36'],
  ['Retinopathy of Prematurity',''],['Allergic Conjunctivitis / Conjonctivite Allergique',''],
  ['Infectious Conjunctivitis / Infection conjonctivale',''],
  ['Eye trauma / Traumatisme oculaire','S05.9'],['Pterygium / Ptérygion',''],
  ['Other Eye diseases / Autres maladies oculaires','H57.9'],
  ['Total eye cases consulted or screened',''],
];

const earLabels = [
  ['Otitis externa, unspecified / Otite externe, non spécifiée',''],
  ['Disorders of external ear, unspecified',''],
  ['Suppurative otitis media, unspecified / Otite moyenne suppurée',''],
  ['Hearing impairment / deafness / Déficience auditive',''],
  ['Acute mastoiditis / Mastoïdite aiguë',''],
  ['Chronic mastoiditis / Mastoïdite Chronique',''],
  ['Other ear diseases / Autres maladies de l\'Oreille',''],
];

const oralLabels = [
  ['Total cases consulted for oro-buccal cavity and problems',''],
  ['Periodontal disease / Maladies pérodontales','K05'],
  ['Disorders of tooth development and eruption (excl. embedded/impacted)','K00'],
  ['Embedded and impacted teeth / Dents encarnées et impactées','K01'],
  ['Dental caries / Caries dentaires','K02'],
  ['Other diseases of hard tissues of teeth','K03'],
  ['Diseases of pulp and periapical tissues','K04'],
  ['Acute gingivitis / Autre gencivite','K05'],
  ['Other disorders of gingiva and edentulous alveolar ridge','K06'],
  ['Dentofacial anomalies [including malocclusion]','K07'],
  ['Other Teeth and oro-buccal cavity diseases','K08.9'],
];

const dentalProcs = [
  ['Examination and Diagnosis, Clinical Oral','1000'],
  ['Radiographs','2100'],
  ['Polishing / Cleaning','11100'],
  ['Caries / Trauma / Pain Control','20100'],
  ['Restorations','23000'],
  ['Periodontal Services, Surgical — Complete','42000'],
  ['Root planing, periodontal','43400'],
  ['Denture Complete','51000'],
  ['Dentures, Repairs / Additions','55000'],
  ['Therapeutic Tissue Conditioning','56500'],
  ['Removals (extractions), Erupted Teeth','71000'],
  ['Removals (extractions), Residual Roots','72300'],
];

function ageSexRow6(r){ return [r.a1m||0,r.a1f||0,r.a2m||0,r.a2f||0,r.a3m||0,r.a3f||0]; }

function n(v){ return (v===undefined||v===null) ? 0 : parseInt(v)||0; }
function fmt(v){ const x=n(v); return x===0?'<span style="color:#9ca3af">0</span>':x.toLocaleString(); }
function fmtRwf(v){ const x=parseFloat(v)||0; return x===0?'<span style="color:#9ca3af">0</span>':x.toLocaleString()+' RWF'; }

function setTxt(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}
function setHtml(id, val) {
  const el = document.getElementById(id);
  if (el) el.innerHTML = val;
}

async function hmisGenerate(){
  const yearEl  = document.getElementById('hmis-year');
  const monthEl = document.getElementById('hmis-month');
  const year    = yearEl ? yearEl.value : new Date().getFullYear();
  const month   = monthEl ? monthEl.value : (new Date().getMonth() + 1);
  const st      = document.getElementById('hmis-status');
  if (st) st.textContent = 'Loading…';

  // Set loading placeholders
  ['hmis-mal-tbody','hmis-chronic-tbody','hmis-inj-tbody',
   'hmis-anc-tbody','hmis-rev-tbody','hmis-staff-tbody',
   'hmis-stool-tbody','hmis-surgery-tbody','hmis-obs-tbody',
   'hmis-eonc-tbody','hmis-deliv2-tbody','hmis-opde2-tbody',
   'hmis-eye-tbody','hmis-ear-tbody','hmis-oral-tbody',
   'hmis-dental-tbody','hmis-hosp-diag-tbody',
   'hmis-mh-summary-tbody','hmis-mh-diag-tbody',
   'hmis-cancer-tbody','hmis-ins-tbody'].forEach(id=>{
    setHtml(id, '<tr><td colspan="12" class="hmis-loading"><i class="fas fa-spinner fa-spin"></i></td></tr>');
  });

  try {
    const res = await fetch(HMIS_API+'&year='+year+'&month='+month);
    const j   = await res.json();
    if(!j.success){ if(st) st.textContent='Error: '+j.message; return; }
    const d = j.data;
    if(st) st.textContent = 'Generated at '+new Date().toLocaleTimeString();

    // Period labels on all tabs
    const periodText = MONTHS[parseInt(month)] + ' ' + year;
    document.querySelectorAll('.hmis-period-label').forEach(el => {
      el.textContent = periodText;
    });
    setTxt('hmis-period-label', periodText);
    setTxt('hmis-lab-period-label', periodText);
    setTxt('hmis-surgery-period-label', periodText);
    setTxt('hmis-obs-period-label', periodText);
    setTxt('hmis-opd2-period-label', periodText);
    setTxt('hmis-mh-period-label', periodText);
    setTxt('hmis-cancer-period-label', periodText);
    setTxt('hmis-ins-period-label', periodText);

    // II. OPD
    const nc=d.opd_summary?.new_cases || {}, oc=d.opd_summary?.old_cases || {};
    setHtml('hv-nc-u5m', fmt(nc.u5m));
    setHtml('hv-nc-u5f', fmt(nc.u5f));
    setHtml('hv-nc-5pm', fmt(nc.a5m));
    setHtml('hv-nc-5pf', fmt(nc.a5f));
    setHtml('hv-nc-tot', fmt(nc.total));
    setHtml('hv-nc-ins', fmt(nc.insured));
    setHtml('hv-oc-u5m', fmt(oc.u5m));
    setHtml('hv-oc-u5f', fmt(oc.u5f));
    setHtml('hv-oc-5pm', fmt(oc.a5m));
    setHtml('hv-oc-5pf', fmt(oc.a5f));
    setHtml('hv-oc-tot', fmt(oc.total));
    setHtml('hv-ref-out', fmt(d.referrals?.referred_out));
    setHtml('hv-ref-in', fmt(d.referrals?.counter_referred_in));
    const unrev = d.dx_review ? d.dx_review.unreviewed : null;
    const drm = document.getElementById('hmis-dxreview-msg'), dmb = document.getElementById('hmis-dxmap-open');
    if (drm) { drm.style.display = unrev === null ? 'none' : 'inline-block'; drm.textContent = unrev ? unrev + ' new OPD case' + (unrev === 1 ? '' : 's') + ' not counted in any row: their diagnosis is not mapped yet.' : 'Every new case with a diagnosis is mapped or recognised.'; }
    if (dmb) dmb.style.display = d.dx_review ? 'inline-block' : 'none';
    setHtml('hv-orig-zone', fmt(d.origin?.from_zone));
    setHtml('hv-orig-out', fmt(d.origin?.outside_zone));
    setHtml('hv-orig-intl', fmt(d.origin?.international));

    // II-E priority diagnoses (if present)
    const diagTbody = document.getElementById('hmis-diag-tbody');
    if (diagTbody && d.priority_diagnoses) {
      let diagHtml='';
      for(let i=1;i<=23;i++){
        const row=d.priority_diagnoses[i]||{u5m:0,u5f:0,a5m:0,a5f:0};
        diagHtml+=`<tr><td class="rn">${i}</td><td class="rl">${diagLabels[i]||''}</td>
          <td class="val">${fmt(row.u5m)}</td><td class="val">${fmt(row.u5f)}</td>
          <td class="val">${fmt(row.a5m)}</td><td class="val">${fmt(row.a5f)}</td></tr>`;
      }
      diagTbody.innerHTML=diagHtml;
    }

    // III. Malaria
    const mal = d.malaria || {};
    const malSimple = mal.simple || {};
    const malSevere = mal.severe || {};
    const malTx1 = mal.treatment_first_line || {};
    const malTx2 = mal.treatment_second_line || {};
    const malFailSusp = mal.suspected_failure || {};
    const malFailConf = mal.confirmed_failure || {};

    const simpleLabels = [
      'All fever cases received / tous les cas de fièvre reçus',
      'Simple Malaria not in Pregnancy (exclude post-partum)',
      'Simple Malaria in Pregnancy',
      'Simple Malaria in post-partum (42 days after delivery)',
      'Simple Malaria with Minor Digestive Symptoms (not in Pregnancy) (exclude post-partum)',
      'Simple Malaria with Minor Digestive Symptoms in Pregnancy',
      'Simple Malaria with Minor Digestive Symptoms in post-partum (42 days after delivery)'
    ];
    const simpleKeys = [
      'all_fever', 'simple_not_preg', 'simple_preg', 'simple_postpart',
      'simple_digest_not_preg', 'simple_digest_preg', 'simple_digest_pp'
    ];

    setHtml('hmis-mal-simple-tbody', simpleKeys.map((k, i) => {
      const bkt = malSimple[k] || {u5:0, '5p':0, total:0};
      const tot = n(bkt.total) || (n(bkt.u5) + n(bkt['5p']));
      return `<tr>
        <td class="rn">${i+1}</td>
        <td class="rl">${simpleLabels[i]}</td>
        <td class="val">${fmt(bkt.u5)}</td>
        <td class="val">${fmt(bkt['5p'])}</td>
        <td class="val" style="font-weight:600;">${fmt(tot)}</td>
      </tr>`;
    }).join(''));

    const severeLabels = [
      'Severe Malaria Referred to Higher level'
    ];
    const severeKeys = ['severe_referred'];
    setHtml('hmis-mal-severe-tbody', severeKeys.map((k, i) => {
      const bkt = malSevere[k] || {u5:0, '5p':0, total:0};
      const tot = n(bkt.total) || (n(bkt.u5) + n(bkt['5p']));
      return `<tr>
        <td class="rn">${i+8}</td>
        <td class="rl">${severeLabels[i]}</td>
        <td class="val">${fmt(bkt.u5)}</td>
        <td class="val">${fmt(bkt['5p'])}</td>
        <td class="val" style="font-weight:600;">${fmt(tot)}</td>
      </tr>`;
    }).join(''));

    const tx1Labels = [
      'Number of Persons who received first line treatment with ACTs (Total)',
      'Number of Persons who received first line treatment with Dihydroartemisinine piperaquine (DHAP)',
      'Number of Persons who received first line treatment with Artesunate Pyronaridine (ASPY)'
    ];
    const tx1Keys = ['acts_total', 'dhap', 'aspy'];
    setHtml('hmis-mal-tx1-tbody', tx1Keys.map((k, i) => {
      const bkt = malTx1[k] || {u5:0, '5p':0, total:0};
      const tot = n(bkt.total) || (n(bkt.u5) + n(bkt['5p']));
      return `<tr>
        <td class="rn">${i+1}</td>
        <td class="rl">${tx1Labels[i]}</td>
        <td class="val">${fmt(bkt.u5)}</td>
        <td class="val">${fmt(bkt['5p'])}</td>
        <td class="val" style="font-weight:600;">${fmt(tot)}</td>
      </tr>`;
    }).join(''));

    const tx2Labels = [
      'Patients treated with second-line treatment with Artemether and Lumefantrine (AL)',
      'Patients treated with second-line treatment with Dihydroartemisinin-Piperaquine (DHAP)',
      'Patients treated with second-line treatment with Artesunate Pyronaridine (ASPY)'
    ];
    const tx2Keys = ['al', 'dhap', 'aspy'];
    setHtml('hmis-mal-tx2-tbody', tx2Keys.map((k, i) => {
      const bkt = malTx2[k] || {u5:0, '5p':0, total:0};
      const tot = n(bkt.total) || (n(bkt.u5) + n(bkt['5p']));
      return `<tr>
        <td class="rn">${i+1}</td>
        <td class="rl">${tx2Labels[i]}</td>
        <td class="val">${fmt(bkt.u5)}</td>
        <td class="val">${fmt(bkt['5p'])}</td>
        <td class="val" style="font-weight:600;">${fmt(tot)}</td>
      </tr>`;
    }).join(''));

    const failSuspLabels = [
      'Suspected Treatment failure on Artemether and Lumefantrine (AL)',
      'Suspected Treatment failure on Dihydroartemisinin-Piperaquine (DHAP)',
      'Suspected Treatment failure on Artesunate Pyronaridine (ASPY)'
    ];
    setHtml('hmis-mal-fail-susp-tbody', tx2Keys.map((k, i) => {
      const bkt = malFailSusp[k] || {u5:0, '5p':0, total:0};
      const tot = n(bkt.total) || (n(bkt.u5) + n(bkt['5p']));
      return `<tr>
        <td class="rn">${i+1}</td>
        <td class="rl">${failSuspLabels[i]}</td>
        <td class="val">${fmt(bkt.u5)}</td>
        <td class="val">${fmt(bkt['5p'])}</td>
        <td class="val" style="font-weight:600;">${fmt(tot)}</td>
      </tr>`;
    }).join(''));

    const failConfLabels = [
      'Confirmed Treatment failure on Artemether and Lumefantrine (AL)',
      'Confirmed Treatment failure on Dihydroartemisinin-piperaquine (DHAP)',
      'Confirmed Treatment failure on Artesunate Pyronaridine (ASPY)'
    ];
    setHtml('hmis-mal-fail-conf-tbody', tx2Keys.map((k, i) => {
      const bkt = malFailConf[k] || {u5:0, '5p':0, total:0};
      const tot = n(bkt.total) || (n(bkt.u5) + n(bkt['5p']));
      return `<tr>
        <td class="rn">${i+1}</td>
        <td class="rl">${failConfLabels[i]}</td>
        <td class="val">${fmt(bkt.u5)}</td>
        <td class="val">${fmt(bkt['5p'])}</td>
        <td class="val" style="font-weight:600;">${fmt(tot)}</td>
      </tr>`;
    }).join(''));

    const ms = mal.summary || {};
    const msTot = n(ms.total) || (n(ms.u5m) + n(ms.u5f) + n(ms.a5m) + n(ms.a5f));
    setHtml('hmis-mal-summary-tbody', `
      <tr>
        <td class="rn">1</td>
        <td class="rl">Malaria cases received in OPD</td>
        <td class="val">${fmt(ms.u5m)}</td>
        <td class="val">${fmt(ms.u5f)}</td>
        <td class="val">${fmt(ms.a5m)}</td>
        <td class="val">${fmt(ms.a5f)}</td>
        <td class="val" style="font-weight:700;">${fmt(msTot)}</td>
      </tr>
    `);

    // IV. Chronic
    let chrHtml='';
    for(let i=1;i<=7;i++){
      const r=(d.chronic||{})[i]||{};
      chrHtml+=`<tr><td class="rn">${i}</td><td class="rl">${chronicLabels[i]}</td>
        <td class="val">${fmt(r.nc_ym)}</td><td class="val">${fmt(r.nc_yf)}</td>
        <td class="val">${fmt(r.nc_om)}</td><td class="val">${fmt(r.nc_of)}</td>
        <td class="val">${fmt(r.oc_ym)}</td><td class="val">${fmt(r.oc_yf)}</td>
        <td class="val">${fmt(r.oc_om)}</td><td class="val">${fmt(r.oc_of)}</td></tr>`;
    }
    setHtml('hmis-chronic-tbody', chrHtml);

    // V. Injuries
    const inj=d.injuries || {road:{},burns:{},other:{}};
    setHtml('hmis-inj-tbody', `
      <tr><td class="rn">1</td><td class="rl">Road traffic injuries</td><td class="val">${fmt(inj.road?.u5)}</td><td class="val">${fmt(inj.road?.['5p'])}</td></tr>
      <tr><td class="rn">2</td><td class="rl">Fire/burns</td><td class="val">${fmt(inj.burns?.u5)}</td><td class="val">${fmt(inj.burns?.['5p'])}</td></tr>
      <tr><td class="rn">3</td><td class="rl">Other injuries</td><td class="val">${fmt(inj.other?.u5)}</td><td class="val">${fmt(inj.other?.['5p'])}</td></tr>`);

    // VI. Hospitalization
    const h = d.hospitalization || {age_admissions:{}, age_deaths:{}};
    setHtml('hv-hosp-a', fmt(h.present_start));
    setHtml('hv-hosp-b', fmt(h.admissions));
    setHtml('hv-hosp-c1', fmt(h.discharged));
    setHtml('hv-hosp-c2', fmt(h.deaths));
    setHtml('hv-hosp-c4', fmt(h.referred_out));
    setHtml('hv-hosp-d', fmt(h.present_end));
    setHtml('hv-hosp-e', fmt(h.beds));
    setHtml('hv-hosp-f', fmt(h.insured_adm));
    setHtml('hv-hosp-g', fmt(h.potential_days));
    setHtml('hv-hosp-h', fmt(h.actual_days));
    setHtml('hv-hosp-i', fmt(h.total_stay));
    setHtml('hv-hosp-ju1', fmt(h.age_admissions?.u1));
    setHtml('hv-hosp-j14', fmt(h.age_admissions?.['1t4']));
    setHtml('hv-hosp-j5p', fmt(h.age_admissions?.['5p']));
    setHtml('hv-hosp-du1', fmt(h.age_deaths?.u1));
    setHtml('hv-hosp-d14', fmt(h.age_deaths?.['1t4']));
    setHtml('hv-hosp-d5p', fmt(h.age_deaths?.['5p']));

    // Inpatient Priority Diagnoses (1-23)
    const hospPriorityRows = h.priority || {};
    setHtml('hmis-hosp-diag-tbody', opdeLabels2.map((row, i) => {
      const r = hospPriorityRows[i+1] || {u5m:0, u5f:0, a5m:0, a5f:0};
      return `<tr>
        <td class="rn">${i+1}</td>
        <td class="rl">${row[0]}${row[1] ? '<span class="hmis-icd">'+row[1]+'</span>' : ''}</td>
        <td class="val">${fmt(r.u5m)}</td><td class="val">${fmt(r.u5f)}</td>
        <td class="val">${fmt(r.a5m)}</td><td class="val">${fmt(r.a5f)}</td>
      </tr>`;
    }).join(''));

    // VIII. ANC
    const anc=d.anc || {};
    const ancVals=[anc.new_registrations,anc.under_14,anc.age_14_17,anc.age_18_19,
                   anc.first_visit_12w,anc.four_visits,anc.risk_detected];
    setHtml('hmis-anc-tbody',
      ancLabels.map((l,i)=>`<tr><td class="rn">${i+1}</td><td class="rl">${l}</td><td class="val">${fmt(ancVals[i])}</td></tr>`).join(''));

    // IX. Deliveries (if present)
    const delivTbody = document.getElementById('hmis-deliv-tbody');
    if (delivTbody && d.deliveries) {
      const del=d.deliveries;
      const delVals=[del.total,del.caesarean,del.live_births,del.still_fresh,del.still_mac,del.maternal_deaths];
      delivTbody.innerHTML=
        delivLabels.map((l,i)=>`<tr><td class="rl">${l}</td><td class="val">${fmt(delVals[i])}</td></tr>`).join('');
    }

    // XIV. Finances
    const revs=(d.finances||{}).revenues || [];
    let revHtml='';
    revLabels.forEach((l,i)=>{
      const v=revs[i]||0;
      revHtml+=`<tr><td class="rn">${i+1}</td><td class="rl">${l}</td><td class="val">${fmtRwf(v)}</td></tr>`;
    });
    revHtml+=`<tr style="background:#eff6ff;font-weight:700;"><td colspan="2" style="padding:5px 7px;text-align:right;">Total Receipts</td><td class="val">${fmtRwf(d.finances?.total_revenue)}</td></tr>`;
    setHtml('hmis-rev-tbody', revHtml);

    // XV. Staffing
    let staffHtml='';
    for(let i=1;i<=20;i++){
      const r=(d.staffing||{})[i]||{};
      staffHtml+=`<tr><td class="rn">${i}</td><td class="rl">${staffLabels[i]}</td>
        <td class="val">${fmt(r.m)}</td><td class="val">${fmt(r.f)}</td>
        <td class="val">${fmt(r.a1)}</td><td class="val">${fmt(r.a2)}</td>
        <td class="val">${fmt(r.a3)}</td><td class="val">${fmt(r.a4)}</td>
        <td class="val">${fmt(r.a5)}</td><td class="val">${fmt(r.a6)}</td>
        <td class="val">${fmt(r.rw)}</td><td class="val">${fmt(r.fo)}</td></tr>`;
    }
    setHtml('hmis-staff-tbody', staffHtml);

    // ── XIX. Laboratory ─────────────────────────────────────────────
    const lab = d.lab || {};

    function lv(id,v){ setHtml(id, fmt(v)); }

    // Helper for lab rows: if test has positive/negative parameters, show Pos & Neg; otherwise show '—'
    // In all cases, Total shows the count of completed examinations
    function lvLabRow(prefix, r){
      r = r || {};
      const hasPN = r.has_pos_neg !== false;
      if(!hasPN){
        setHtml('hv-lab-' + prefix + '-pos', '<span style="color:#9ca3af">—</span>');
        setHtml('hv-lab-' + prefix + '-neg', '<span style="color:#9ca3af">—</span>');
      } else {
        lv('hv-lab-' + prefix + '-pos', r.pos);
        lv('hv-lab-' + prefix + '-neg', r.neg);
      }
      const tot = r.completed !== undefined ? r.completed : (r.tot !== undefined ? r.tot : (n(r.pos) + n(r.neg)));
      lv('hv-lab-' + prefix + '-tot', tot);
    }

    // Blood smears
    lv('hv-lab-bs-pos', lab.bs_pos); lv('hv-lab-bs-neg', lab.bs_neg);
    lv('hv-lab-bs-u5',  lab.bs_u5);  lv('hv-lab-bs-5p',  lab.bs_5p);
    lv('hv-lab-bs-tot', lab.bs_tot !== undefined ? lab.bs_tot : (n(lab.bs_pos)+n(lab.bs_neg)));
    ['pf','pm','pv','pk'].forEach(k => {
      lv('hv-lab-'+k+'-pos', lab[k+'_pos']);
      lv('hv-lab-'+k+'-neg', lab[k+'_neg']);
      lv('hv-lab-'+k+'-tot', lab[k+'_tot'] !== undefined ? lab[k+'_tot'] : (n(lab[k+'_pos'])+n(lab[k+'_neg'])));
    });
    // RDT
    lv('hv-lab-rdt-pos', lab.rdt_pos); lv('hv-lab-rdt-neg', lab.rdt_neg);
    lv('hv-lab-rdt-u5',  lab.rdt_u5);  lv('hv-lab-rdt-5p',  lab.rdt_5p);
    lv('hv-lab-rdt-tot', lab.rdt_tot !== undefined ? lab.rdt_tot : (n(lab.rdt_pos)+n(lab.rdt_neg)));
    // Stools total
    lv('hv-lab-st-pos', lab.st_pos); lv('hv-lab-st-neg', lab.st_neg);
    lv('hv-lab-st-u5',  lab.st_u5);  lv('hv-lab-st-5p',  lab.st_5p);
    lv('hv-lab-st-tot', lab.st_tot !== undefined ? lab.st_tot : (n(lab.st_pos)+n(lab.st_neg)));
    // Stool parasites
    const stoolLabels = ['Ascariasis','Trichuris (Tricocephale)','Ankylostoma (hookworms)','Schistosoma in stool','Taenia (solium and saginata)','Entamoeba histolytica','Giardia lamblia or intestinalis','Enterobius vermicularis (Oxyure)','Strongyloides stercolaris (Anguillule)','Trichomonas intestinalis','Other parasites in stools'];
    const stoolKeys  = ['ascaris','trichuris','ankylostoma','schisto','taenia','entamoeba','giardia','enterobius','strongyloides','trichomonas','other_stool'];
    const stoolRows  = lab.stool_parasites || {};
    setHtml('hmis-stool-tbody', stoolKeys.map((k,i)=>{
      const r = stoolRows[k] || {a1:0,a2:0,a3:0};
      const tot = n(r.a1)+n(r.a2)+n(r.a3);
      return `<tr><td class="rl">${stoolLabels[i]}</td><td class="val">${fmt(r.a1)}</td><td class="val">${fmt(r.a2)}</td><td class="val">${fmt(r.a3)}</td><td class="val">${fmt(tot)}</td></tr>`;
    }).join(''));
    // Urine
    ['41','42','43'].forEach(k => { lvLabRow('u'+k, lab['u'+k]); });
    // Bacteriology
    ['b51af','b51ag','b52af','b52ag','b53af','b53ag'].forEach(k=>{ const r=lab[k]||{}; lv('hv-lab-'+k+'-pos',r.pos); lv('hv-lab-'+k+'-neg',r.neg); lv('hv-lab-'+k+'-dip',r.dip); });
    // Blood
    ['b61','b62','b63','b64','b65','b66','b67','b68','b69','b610','b611','b612','b613'].forEach(k => { lvLabRow(k, lab[k]); });
    // CSF + Other
    lvLabRow('csf', lab.csf);
    lvLabRow('oth', lab.oth);
    // Grand totals
    lv('hv-lab-grand-pos', lab.grand_pos);
    lv('hv-lab-grand-neg', lab.grand_neg);
    lv('hv-lab-grand-tot', lab.grand_tot !== undefined ? lab.grand_tot : (n(lab.grand_pos)+n(lab.grand_neg)));

    // ── IX. Surgery ──────────────────────────────────────────────────
    const surg = d.surgery || {};

    const surgGroups = [
      { label:'1. General Surgery', cat:'general', items:[
        'Appendicectomy','Hernioraphy','Laparotomy','Thyroidectomy','Adenomectomy',
        'Breast surgery','Hydrocele repair','Testis torsion repair','Hemorrhoidectomy',
        'Tissue debridement','Skin grafting','Hernia repair','Surgery due to ascariasis complications'
      ]},
      { label:'2. Gyneco-Obstetrical', cat:'gyneco', items:[
        'Caesarean Section','Dilatation and Curettage','Gynecological Hysterectomy',
        'Obstetrical Hysterectomy','Laparotomy for uterine rupture','Laparotomy for ectopic pregnancy',
        'Uterine repair','Myomectomy','Vesico vaginal Fistula Repair','Recto vaginal Fistula Repair',
        'Ureteric vaginal Fistula Repair','Tubal Ligation','Vasectomy','Episiotomy',
        'Other Gyneco-Obstetrical Surgery'
      ]},
      { label:'3. Orthopedic', cat:'ortho', items:[
        'Amputations','Open fracture repair by external fixation','Clubfoot repair',
        'Closed treatment of fracture','Joint dislocation treatment',
        'Drainage of osteomyelitis / septic arthritis','Orthopedic surgery other'
      ]},
      { label:'4. Ophthalmologic', cat:'ophtha', items:[
        'Glaucoma','Trachoma','Cataract','Eye Trauma','Ophthalmological surgery other',
        'Cornea transplantation','Pterygium'
      ]},
      { label:'5. Plastic Surgery', cat:'plastic', items:[
        'Cleft palate / Cleft lip','Other plastic surgery'
      ]},
      { label:'6. Other Surgery Procedures', cat:'other', items:[
        'Neonatal abdominal defect repair','Colostomy for imperforate anus','Cystostomy',
        'Chest tube insertion','Tracheostomy / Cricothyroidotomy','Circumcision',
        'Suturing wound','Wound debridement','Incision and drainage of abscess'
      ]},
    ];

    let surgHtml = '';
    let grandTotal=0, grandInfect=0;
    surgGroups.forEach(grp => {
      surgHtml += `<tr style="background:#eff6ff;">
        <td colspan="4" style="padding:7px 10px;font-weight:700;color:#1e40af;">${grp.label}</td>
      </tr>`;
      grp.items.forEach((item, idx) => {
        const key = grp.cat+'_'+idx;
        const row = (surg[key]) || {total:0, infection:0};
        grandTotal += n(row.total); grandInfect += n(row.infection);
        surgHtml += `<tr style="border-bottom:1px solid var(--gray-100);">
          <td class="rn">${idx+1}</td>
          <td class="rl">${item}</td>
          <td class="val">${fmt(row.total)}</td>
          <td class="val">${fmt(row.infection)}</td>
        </tr>`;
      });
    });

    // Totals row
    const surgTot = surg.totals || {};
    surgHtml += `<tr style="background:#f0fdf4;font-weight:700;border-top:2px solid #bbf7d0;">
      <td colspan="2" style="padding:8px 10px;font-weight:700;">7. SURGICAL INTERVENTIONS TOTAL</td>
      <td class="val">${fmt(n(surgTot.total)||grandTotal)}</td>
      <td class="val">${fmt(n(surgTot.infection)||grandInfect)}</td>
    </tr>
    <tr style="background:#f0fdf4;">
      <td class="rn"></td><td class="rl" style="padding-left:20px;">1. Major surgery</td>
      <td class="val">${fmt(surgTot.major)}</td><td class="val0">—</td>
    </tr>
    <tr style="background:#f0fdf4;">
      <td class="rn"></td><td class="rl" style="padding-left:20px;">2. Minor surgery</td>
      <td class="val">${fmt(surgTot.minor)}</td><td class="val0">—</td>
    </tr>`;

    setHtml('hmis-surgery-tbody', surgHtml);

    // Anesthesia
    const anes = surg.anesthesia || {};
    setHtml('hv-anes-general', fmt(anes.general));
    setHtml('hv-anes-regional', fmt(anes.regional));
    setHtml('hv-anes-local', fmt(anes.local));

    // Waiting list
    const wl = surg.waiting_list || {};
    setHtml('hv-wl-u1m-major', fmt(wl.u1m_major));
    setHtml('hv-wl-u1m-minor', fmt(wl.u1m_minor));
    setHtml('hv-wl-u1m-tot', fmt(n(wl.u1m_major)+n(wl.u1m_minor)));
    setHtml('hv-wl-o1m-major', fmt(wl.o1m_major));
    setHtml('hv-wl-o1m-minor', fmt(wl.o1m_minor));
    setHtml('hv-wl-o1m-tot', fmt(n(wl.o1m_major)+n(wl.o1m_minor)));
    setHtml('hv-wl-o3m-done', fmt(wl.o3m_done));

    // ── XII. Obstetrical Complications ──────────────────────────────
    const obs = d.obstetrics || {};

    const obsLabels = [
      ['Total number of abortions',''],
      ['Spontaneous abortions',''],
      ['Complications of abortions','O04.9 & O03'],
      ['Threatened abortion / Menace d\'avortement',''],
      ['Ectopic pregnancy / Grossesse ectopique','O00.9'],
      ['Antepartum Hemorrhage (APH)','O46.9'],
      ['Post-partum hemorrhage SVD (PPH)','O72.1'],
      ['Post C/Section hemorrhage',''],
      ['Eclampsia','O15.9'],
      ['Severe Pre-Eclampsia','O14.1'],
      ['Post C/Section infection – onsite','O85'],
      ['Post C/Section infection – peritonitis',''],
      ['Other Postpartum infections','O85'],
      ['Prolonged or Obstructed labor','O66.9'],
      ['Uterine rupture','S37.6'],
      ['Amniotic embolism',''],
      ['Complications of anesthesia',''],
      ['Gestational trophoblastic neoplasia (GTN)',''],
      ['Hyperemesis gravidarum',''],
      ['Intrauterine fetal death (IUFD)',''],
      ['Deep vein thrombosis (DVT)',''],
      ['Obstetrical fistula',''],
      ['Other Direct Obstetrical Complication','O75.4'],
      ['Anemia Severe (<7gm/dl)','O99.0'],
      ['HIV/Opportunistic Infections','O98.7'],
      ['Pulmonary embolism',''],
      ['Pneumonia on pregnancy',''],
      ['Asthma',''],
      ['Diabetes on pregnancy',''],
      ['CKD (Chronic Kidney Disease)',''],
      ['Puerperal psychosis or depression',''],
      ['Epilepsia',''],
      ['Cellulitis',''],
      ['Ileus paralytic',''],
      ['Severe malnutrition',''],
      ['Urinary tract infections (UTIs)',''],
      ['Obstetrical cancers',''],
      ['Other indirect obstetrical Complication','O75.4'],
    ];
    const obsRows = obs.cases || [];
    setHtml('hmis-obs-tbody', obsLabels.map((row,i)=>{
      const r = obsRows[i] || {opd:0,hosp:0,deaths:0};
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td>
        <td class="rl">${row[0]}${row[1]?'<span class="hmis-icd">'+row[1]+'</span>':''}</td>
        <td class="val">${fmt(r.opd)}</td>
        <td class="val">${fmt(r.hosp)}</td>
        <td class="val">${fmt(r.deaths)}</td>
      </tr>`;
    }).join(''));

    const eoncLabels = [
      'IV/IM antibiotics to manage Obstetrical Infections',
      'Mother received uterotonic drugs to manage PPH',
      'Manual removal of placenta',
      'Post-Abortion Care (Manual vacuum aspiration)',
      'Delivery by Vacuum extraction',
      'Severe Pre/eclampsia cases receiving magnesium sulfate',
      'Blood transfusion for obstetrical complications',
      'Women with obstetrical complications referred for emergency care to higher levels',
    ];
    const eoncRows = obs.eonc || [];
    setHtml('hmis-eonc-tbody', eoncLabels.map((lbl,i)=>
      `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td><td class="rl">${lbl}</td>
        <td class="val">${fmt(eoncRows[i]||0)}</td>
      </tr>`).join(''));

    const delivLabels2 = [
      'Deliveries at health facility',
      'Deliveries by Caesarean Section',
      'Delivery complicated by perineal tear (2nd/3rd/4th degree)',
      'Birth trauma to newborn',
      'Cleft palate or Cleft lip',
      'Other congenital malformation',
      'Deliveries under 14 years',
      'Deliveries 14 – 17 years',
      'Deliveries 18 – 19 years',
      'Multiple Pregnancies (twins, triplets, etc.)',
      'Women consulted for risk of premature delivery 24-34 weeks',
      'Women at risk of premature delivery 24-34 weeks who received Corticosteroid',
      'Women consulted with PPROM (Preterm Premature Rupture of Membranes)',
      'Women with PPROM who received prophylactic antibiotics',
      'Women who received oxytocin immediately after birth (active management 3rd stage)',
      'Maternal deaths during labor, delivery and 24 hours after delivery',
      'Births, live / Naissances vivantes',
      'Birth weight <2500 gr (alive)',
      'Premature newborns (alive) 22-37 weeks',
      'Birth Weight ≤2 Kg (alive newborns) all',
      'Still births fresh (≥28 weeks or ≥1000 grams) all',
      'Still births macerated (≥28 weeks or ≥1000 grams)',
      'Still births fresh (≥2500 grams)',
      'Deaths at birth of live born babies (within 30 minutes)',
      'Newborns breastfed within 1 hour of delivery',
      'Live Newborns who didn\'t cry/breathe at birth',
      'Live Newborns resuscitated successfully (cry/breathe within 5 min, APGAR >5 at 5 min)',
      'Women accompanied by their partners for delivery at health facility',
      'Persons with disabilities',
    ];
    const delivRows2 = obs.deliveries || [];
    setHtml('hmis-deliv2-tbody', delivLabels2.map((lbl,i)=>
      `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td><td class="rl">${lbl}</td>
        <td class="val">${fmt(delivRows2[i]||0)}</td>
      </tr>`).join(''));

    // ── E/F OPD Priority + Eye/Ear/Oral ─────────────────────────────
    const opd2 = d.opd2 || {};

    // E) Priority diagnoses (23 rows)
    const opde2Rows = opd2.priority || {};
    setHtml('hmis-opde2-tbody', opdeLabels2.map((row,i)=>{
      const r = opde2Rows[i+1] || {u5m:0,u5f:0,a5m:0,a5f:0};
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td>
        <td class="rl">${row[0]}${row[1]?'<span class="hmis-icd">'+row[1]+'</span>':''}</td>
        <td class="val">${fmt(r.u5m)}</td><td class="val">${fmt(r.u5f)}</td>
        <td class="val">${fmt(r.a5m)}</td><td class="val">${fmt(r.a5f)}</td>
      </tr>`;
    }).join(''));

    // Fa) Eye diseases (14 rows, 3 age groups M/F)
    const eyeRows = opd2.eye || {};
    setHtml('hmis-eye-tbody', eyeLabels.map(([lbl,icd],i)=>{
      const r = eyeRows[i] || {};
      const [a1m,a1f,a2m,a2f,a3m,a3f] = ageSexRow6(r);
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td>
        <td class="rl">${lbl}${icd?'<span class="hmis-icd">'+icd+'</span>':''}</td>
        <td class="val">${fmt(a1m)}</td><td class="val">${fmt(a1f)}</td>
        <td class="val">${fmt(a2m)}</td><td class="val">${fmt(a2f)}</td>
        <td class="val">${fmt(a3m)}</td><td class="val">${fmt(a3f)}</td>
      </tr>`;
    }).join(''));

    // Fb) Ear diseases (7 rows)
    const earRows = opd2.ear || {};
    setHtml('hmis-ear-tbody', earLabels.map(([lbl,icd],i)=>{
      const r = earRows[i] || {};
      const [a1m,a1f,a2m,a2f,a3m,a3f] = ageSexRow6(r);
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td>
        <td class="rl">${lbl}${icd?'<span class="hmis-icd">'+icd+'</span>':''}</td>
        <td class="val">${fmt(a1m)}</td><td class="val">${fmt(a1f)}</td>
        <td class="val">${fmt(a2m)}</td><td class="val">${fmt(a2f)}</td>
        <td class="val">${fmt(a3m)}</td><td class="val">${fmt(a3f)}</td>
      </tr>`;
    }).join(''));

    // Fc) Oral diseases (11 rows)
    const oralRows = opd2.oral || {};
    setHtml('hmis-oral-tbody', oralLabels.map(([lbl,icd],i)=>{
      const r = oralRows[i] || {};
      const [a1m,a1f,a2m,a2f,a3m,a3f] = ageSexRow6(r);
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td>
        <td class="rl">${lbl}${icd?'<span class="hmis-icd">'+icd+'</span>':''}</td>
        <td class="val">${fmt(a1m)}</td><td class="val">${fmt(a1f)}</td>
        <td class="val">${fmt(a2m)}</td><td class="val">${fmt(a2f)}</td>
        <td class="val">${fmt(a3m)}</td><td class="val">${fmt(a3f)}</td>
      </tr>`;
    }).join(''));

    // Fd) Dental procedures (12 rows)
    const dentalRows = opd2.dental || {};
    setHtml('hmis-dental-tbody', dentalProcs.map(([lbl,code],i)=>{
      const total = dentalRows[i] || 0;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td>
        <td class="rl">${lbl}</td>
        <td style="padding:5px 7px;text-align:center;font-family:monospace;font-size:11px;color:#6b7280;">${code}</td>
        <td class="val">${fmt(total)}</td>
      </tr>`;
    }).join(''));

    // ── III. Mental Health ────────────────────────────────────────────
    const mh = d.mental_health || {};

    const mhSummaryLabels = [
      'Mental patients under follow up',
      'Mental problems hospitalized',
      'Mental patients referred to upper level',
      'Patients who are taking medication',
      'Patients who are receiving psychological interventions',
      'Patients lost to follow up',
      'Patients referred by school counselors',
    ];
    const mhSummaryData = mh.summary || [];
    setHtml('hmis-mh-summary-tbody', mhSummaryLabels.map((lbl,i)=>
      `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td><td class="rl">${lbl}</td>
        <td class="val">${fmt(mhSummaryData[i]||0)}</td>
      </tr>`).join(''));

    const mhDiagLabels = [
      ['Anxiety disorders / Les troubles anxieux','6B00'],
      ['Post-traumatic stress disorder (PTSD)','6B40'],
      ['Schizophrenia and other psychoses','6A20'],
      ['Somatoform disorders / Troubles somatiques','6C20.Z'],
      ['Disorders due to substance use or addictive behaviours','6C4E'],
      ['Disorders due to use of alcohol / Troubles dus à l\'alcool','6C91'],
      ['Depression / Dépression','6A70'],
      ['Suicide attempt / Tentative de suicide',''],
      ['Bipolar disorders / Troubles bipolaires','6A60'],
      ['Autism spectrum disorder','6A02'],
      ['Hyperactivity Deficit Disorder (ADHD)','6A05'],
      ['Personality disorders / Troubles de la personnalité','6D10'],
      ['Conduct-dissocial disorder','6C91'],
      ['Other mental problems / Autre problèmes mentaux',''],
      ['Epilepsy / Epilepsie','8A60'],
      ['Migraine','8A80'],
      ['Parkinson diseases','8A00'],
      ['Delirium','6D70'],
      ['Dementia / Démence','6C84'],
    ];
    const mhDiagData = mh.diagnoses || [];
    setHtml('hmis-mh-diag-tbody', mhDiagLabels.map(([lbl,icd],i)=>{
      const r = mhDiagData[i] || {nc:{a1m:0,a1f:0,a2m:0,a2f:0,a3m:0,a3f:0,a4m:0,a4f:0},oc:{a1m:0,a1f:0,a2m:0,a2f:0,a3m:0,a3f:0,a4m:0,a4f:0}};
      const nc=r.nc||{}, oc=r.oc||{};
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td>
        <td class="rl">${lbl}${icd?'<span class="hmis-icd">'+icd+'</span>':''}</td>
        <td class="val">${fmt(nc.a1m)}</td><td class="val">${fmt(nc.a1f)}</td>
        <td class="val">${fmt(nc.a2m)}</td><td class="val">${fmt(nc.a2f)}</td>
        <td class="val">${fmt(nc.a3m)}</td><td class="val">${fmt(nc.a3f)}</td>
        <td class="val">${fmt(nc.a4m)}</td><td class="val">${fmt(nc.a4f)}</td>
        <td class="val">${fmt(oc.a1m)}</td><td class="val">${fmt(oc.a1f)}</td>
        <td class="val">${fmt(oc.a2m)}</td><td class="val">${fmt(oc.a2f)}</td>
        <td class="val">${fmt(oc.a3m)}</td><td class="val">${fmt(oc.a3f)}</td>
        <td class="val">${fmt(oc.a4m)}</td><td class="val">${fmt(oc.a4f)}</td>
      </tr>`;
    }).join(''));

    // ── Cancer Screening ─────────────────────────────────────────────
    const cs = d.cancer_screening || [];
    const csLabels = [
      'Women screened for cervical cancer',
      'Women screened for breast cancer',
      'Biopsies collected for all types of cancer',
      'Women tested HPV positive and VIA triage negative treated with Thermoablation or Cryotherapy',
      'Screened positive women treated with Thermoablation or Cryotherapy',
      'Women screened for cervical cancer with VIA only',
      'Women tested negative with VIA only',
      'Women tested positive with VIA only',
    ];
    setHtml('hmis-cancer-tbody', csLabels.map((lbl,i)=>
      `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rn">${i+1}</td>
        <td class="rl">${lbl}</td>
        <td class="val">${fmt(cs[i]||0)}</td>
      </tr>`).join(''));

    // ── Insurance Summary ────────────────────────────────────────────
    const ins = d.insurance_summary || {};

    const insProviders = ['cbhi','rama','mmi','ur','radiant','uap','sanlam','britam','eden','other','noninsured'];
    const insRowLabels = [
      'OPD cases (New and Old)',
      'Hospitalization cases',
      'Total amount of Co-Payment (RWF)',
      'Total amount of bills submitted (RWF)',
      'Total amount of bills paid at end of month (RWF)',
      'Social cases (number of cases)',
      'Amount billed for Social Cases (RWF)',
    ];
    const insRowKeys = ['opd','hosp','copay','billed','paid','social_cases','social_billed'];

    setHtml('hmis-ins-tbody', insRowLabels.map((lbl,i)=>{
      const key = insRowKeys[i];
      const isAmount = key.includes('amount')||key.includes('billed')||key.includes('paid')||key.includes('copay');
      const cols = insProviders.map(p=>{
        const v = (ins[p]||{})[key]||0;
        return `<td class="val">${isAmount ? (v?Number(v).toLocaleString():'0') : fmt(v)}</td>`;
      }).join('');
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="rl" style="font-weight:600;">${i+1}. ${lbl}</td>${cols}
      </tr>`;
    }).join(''));

  } catch(e){
    if (st) st.textContent='Error: '+e.message;
  }
}

const ALL_HMIS_TABS = [
  'opd', 'malaria', 'mh', 'chronic', 'injuries', 'hosp',
  'anc', 'surgery', 'obs', 'finances', 'staffing', 'lab',
  'cancer', 'ins'
];

function hmisPrint(){
  let el = null;
  for (const t of ALL_HMIS_TABS) {
    const tabEl = document.getElementById('hmis-itab-' + t);
    if (tabEl && tabEl.style.display !== 'none') {
      el = tabEl.querySelector('.hmis-wrap') || tabEl;
      break;
    }
  }
  if (!el) el = document.getElementById('hmis-itab-opd');
  const win = window.open('', '', 'width=1100,height=800');
  const styles = Array.from(document.styleSheets).map(s => {
    try { return Array.from(s.cssRules).map(r => r.cssText).join('\n'); } catch (e) { return ''; }
  }).join('\n');
  win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
    <title>HMIS Monthly Report</title>
    <style>${styles} body{padding:14px;font-family:Arial,sans-serif;font-size:11px;}
    .hmis-noprint{display:none!important;}.hmis-section{break-inside:avoid;}</style>
  </head><body>${el.innerHTML}</body></html>`);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 600);
}

// ── Tab switching (Ordered by Roman numerals) ─────────────────────────
function hmisInnerTab(tab){
  ALL_HMIS_TABS.forEach(t => {
    const tabEl = document.getElementById('hmis-itab-' + t);
    const btn   = document.getElementById('hmis-itab-btn-' + t);
    if (tabEl) tabEl.style.display = (t === tab ? 'block' : 'none');
    if (btn) {
      if (t === tab) {
        btn.style.background = 'var(--primary-color)';
        btn.style.color = 'white';
        btn.style.border = 'none';
        btn.style.fontWeight = '600';
      } else {
        btn.style.background = 'var(--gray-100)';
        btn.style.color = 'var(--gray-600)';
        btn.style.border = '1px solid var(--gray-200)';
        btn.style.fontWeight = '500';
      }
    }
  });
}

// ── Diagnosis mapping: assign each distinct diagnosis to an HMIS priority row ───────────────────────────
let dxmModal = null, dxmData = null;
function dxmEsc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch])); }
function dxmClose(){ if (dxmModal) { dxmModal.remove(); dxmModal = null; } }
function dxmOpen(){
  dxmClose();
  dxmModal = document.createElement('div');
  dxmModal.style.cssText = 'position:fixed;inset:0;background:rgba(17,24,39,.5);z-index:10000;display:flex;align-items:center;justify-content:center;padding:16px;';
  dxmModal.innerHTML = `<div style="background:#fff;border-radius:12px;width:100%;max-width:920px;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;" role="dialog" aria-modal="true" aria-label="Map diagnoses">
    <div style="display:flex;align-items:flex-start;gap:12px;padding:16px 20px;border-bottom:1px solid #e5e7eb;">
      <div style="flex:1;"><div style="font-size:16px;font-weight:700;">Map diagnoses to HMIS priority rows</div>
        <div style="font-size:12px;color:#6b7280;margin-top:2px;">Each diagnosis is assigned once; every future consultation with it lands in that row. Yellow = suggested from keywords, not saved yet.</div></div>
      <button type="button" id="dxm-x" aria-label="Close" style="border:none;background:none;font-size:20px;cursor:pointer;color:#9ca3af;"><i class="fas fa-times"></i></button>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;padding:12px 20px;border-bottom:1px solid #e5e7eb;">
      <input type="search" id="dxm-q" placeholder="Search diagnosis…" style="padding:7px 10px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;min-width:180px;">
      <select id="dxm-only" style="padding:7px 10px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;"><option value="unmapped">Not mapped yet</option><option value="all">All diagnoses</option></select>
      <select id="dxm-months" style="padding:7px 10px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;"><option value="3">Last 3 months</option><option value="12">Last 12 months</option><option value="24" selected>Last 24 months</option><option value="60">All (5 years)</option></select>
      <span id="dxm-n" style="font-size:12px;color:#6b7280;"></span>
      <button type="button" id="dxm-save" style="margin-left:auto;padding:7px 14px;border:none;border-radius:6px;background:#1e40af;color:#fff;font-size:12px;font-weight:600;cursor:pointer;">Save mapping</button>
    </div>
    <div style="overflow:auto;flex:1;"><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:#f9fafb;position:sticky;top:0;"><th style="padding:9px 12px;text-align:left;">Diagnosis (as typed)</th><th style="padding:9px 12px;text-align:right;width:70px;">Used</th><th style="padding:9px 12px;text-align:left;min-width:300px;">HMIS row</th></tr></thead><tbody id="dxm-body"><tr><td colspan="3" style="padding:18px;text-align:center;">Loading…</td></tr></tbody></table></div>
  </div>`;
  document.body.appendChild(dxmModal);
  document.getElementById('dxm-x').onclick = dxmClose;
  dxmModal.addEventListener('mousedown', e => { if (e.target === dxmModal) dxmClose(); });
  let t; document.getElementById('dxm-q').addEventListener('input', () => { clearTimeout(t); t = setTimeout(dxmLoad, 300); });
  document.getElementById('dxm-only').onchange = dxmLoad;
  document.getElementById('dxm-months').onchange = dxmLoad;
  document.getElementById('dxm-save').onclick = dxmSave;
  dxmLoad();
}
async function dxmLoad(){
  const body = document.getElementById('dxm-body'); if (!body) return;
  const q = new URLSearchParams({only: document.getElementById('dxm-only').value, months: document.getElementById('dxm-months').value, search: document.getElementById('dxm-q').value});
  const j = await (await fetch(HMIS_DXMAP_API + '&' + q)).json();
  if (!j.success) { body.innerHTML = '<tr><td colspan="3" style="padding:18px;color:#b91c1c;">' + dxmEsc(j.message || 'Failed') + '</td></tr>'; return; }
  dxmData = j.data;
  const labels = j.data.rows_labels;
  const opts = sel => '<option value="">— not mapped —</option><option value="none"' + (sel === 'none' ? ' selected' : '') + '>Not a priority problem (ignore)</option>' +
    Object.entries(labels).map(([k, l]) => '<option value="' + k + '"' + (String(sel) === k ? ' selected' : '') + '>' + k + '. ' + dxmEsc(l) + '</option>').join('');
  body.innerHTML = j.data.rows.map(r => {
    const cur = r.row_key || r.suggested;
    return '<tr data-k="' + dxmEsc(r.dx_key) + '" data-saved="' + dxmEsc(r.row_key) + '"><td style="padding:8px 12px;border-top:1px solid #f3f4f6;">' + dxmEsc(r.text) + '</td><td style="padding:8px 12px;border-top:1px solid #f3f4f6;text-align:right;">' + r.uses + '</td><td style="padding:8px 12px;border-top:1px solid #f3f4f6;"><select class="dxm-sel" style="width:100%;padding:6px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;' + (!r.row_key && r.suggested ? 'background:#fef3c7;' : '') + '">' + opts(cur) + '</select></td></tr>';
  }).join('') || '<tr><td colspan="3" style="padding:18px;text-align:center;color:#6b7280;">Nothing to show.</td></tr>';
  document.getElementById('dxm-n').textContent = j.data.shown + ' shown · ' + j.data.mapped + ' of ' + j.data.distinct + ' diagnoses mapped';
  body.querySelectorAll('.dxm-sel').forEach(sel => sel.addEventListener('change', () => { sel.style.background = ''; }));
}
async function dxmSave(){
  const items = [...document.querySelectorAll('#dxm-body tr[data-k]')].map(tr => ({dx_key: tr.dataset.k, row_key: tr.querySelector('.dxm-sel').value, saved: tr.dataset.saved}))
    .filter(x => x.row_key !== x.saved).map(x => ({dx_key: x.dx_key, row_key: x.row_key}));
  if (!items.length) { alert('Nothing to save.'); return; }
  const btn = document.getElementById('dxm-save'); btn.disabled = true;
  const j = await (await fetch(HMIS_DXMAP_API, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({items})})).json();
  btn.disabled = false;
  if (!j.success) { alert(j.message || 'Save failed'); return; }
  await dxmLoad();
  hmisGenerate(); // recount with the new mapping
}
document.addEventListener('click', e => { if (e.target.closest('#hmis-dxmap-open')) { dxmOpen(); } });
document.addEventListener('keydown', e => { if (e.key === 'Escape') dxmClose(); });

// Auto-generate on load
hmisGenerate();
</script>

<?php endif; ?>

<script src="assets/js/report-export.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/report-export.js'); ?>"></script>
<?php if ($subModule == 'repeat-patients'): ?>
<!-- ===== REPEAT PATIENTS (Patient Visit Analytics) ===== -->
<style>
.rv-seg{display:inline-flex;background:var(--gray-100);border-radius:8px;padding:3px;gap:2px;}
.rv-seg button{padding:7px 14px;border:none;background:transparent;border-radius:6px;font-size:13px;font-weight:500;color:var(--gray-600);cursor:pointer;transition:background-color 150ms ease,color 150ms ease;}
.rv-seg button.on{background:white;color:var(--gray-900);box-shadow:0 1px 2px rgba(0,0,0,.08);}
.rv-in{padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;}
.rv-lbl{font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;}
.rv-btn{padding:8px 16px;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition:transform 140ms ease-out;}
.rv-btn:active{transform:scale(0.97);}
.rv-stat .k{font-size:13px;color:var(--gray-500);}
.rv-stat .v{font-size:28px;font-weight:700;color:var(--gray-900);margin:4px 0 2px;font-variant-numeric:tabular-nums;}
.rv-stat .s{font-size:12px;color:var(--gray-500);}
.rv-chip{display:inline-block;padding:2px 7px;margin:2px 3px 2px 0;border-radius:10px;background:var(--gray-100);font-size:11px;color:var(--gray-700);white-space:nowrap;}
.rv-day{cursor:pointer;transition:background-color 150ms ease;}
@media (hover:hover){.rv-day:hover{background:#c7d2fe!important;}}
.rv-sec{margin-top:16px;}.rv-sec h4{font-size:13px;font-weight:700;color:var(--gray-800);margin:0 0 6px;padding-bottom:4px;border-bottom:2px solid var(--gray-100);}
.rv-sec table{width:100%;border-collapse:collapse;font-size:12px;}.rv-sec th{background:var(--gray-50);text-align:left;padding:5px 8px;}.rv-sec td{padding:5px 8px;border-bottom:1px solid var(--gray-100);vertical-align:top;}
.rv-badge{display:inline-flex;align-items:center;justify-content:center;min-width:34px;padding:3px 8px;border-radius:14px;font-weight:700;font-size:13px;background:#dbeafe;color:#1d4ed8;}
.rv-badge.hot{background:#fef3c7;color:#b45309;}
.rv-row-hot td:first-child{box-shadow:inset 3px 0 0 #f59e0b;}
.rv-top{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px;}
.rv-top .c{background:var(--gray-50);border-radius:8px;padding:10px 12px;cursor:pointer;transition:background-color 150ms ease;}
@media (hover:hover){.rv-top .c:hover{background:#eef2ff;}}
.rv-bar{height:5px;background:var(--gray-200);border-radius:3px;margin-top:6px;overflow:hidden;}
.rv-bar i{display:block;height:100%;background:#f59e0b;border-radius:3px;}
#rv-modal,.rv-m{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;align-items:center;justify-content:center;padding:16px;overflow-y:auto;}
#rv-modal.show,.rv-m.show{display:flex;}
#rv-modal .d,.rv-m .d{background:white;border-radius:12px;padding:24px;width:100%;max-width:860px;margin:auto;position:relative;transition:transform 200ms cubic-bezier(0.23,1,0.32,1),opacity 200ms cubic-bezier(0.23,1,0.32,1);}
@starting-style{#rv-modal.show .d,.rv-m.show .d{transform:scale(0.96);opacity:0;}}
@media (prefers-reduced-motion:reduce){@starting-style{#rv-modal.show .d{transform:none;}}}
</style>

<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:16px;">
  <div style="display:flex;align-items:center;gap:14px;padding:18px 22px;border-bottom:1px solid var(--gray-100);">
    <i class="fas fa-chart-line" style="font-size:22px;color:var(--primary-color);"></i>
    <div>
      <div style="font-size:17px;font-weight:600;color:var(--gray-900);">Patient Visit Analytics</div>
      <div style="font-size:13px;color:var(--gray-500);">Reports → Patient Visit Management → Repeat Patients</div>
    </div>
  </div>
  <!-- Filters -->
  <div style="padding:14px 22px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;border-bottom:1px solid var(--gray-100);">
    <div><span class="rv-lbl">Period</span>
      <div class="rv-seg" id="rv-period">
        <button data-p="month" class="on" onclick="rvSetPeriod('month')">Month</button>
        <button data-p="3m" onclick="rvSetPeriod('3m')">Last 3 months</button>
        <button data-p="year" onclick="rvSetPeriod('year')">Year</button>
        <button data-p="custom" onclick="rvSetPeriod('custom')">Custom</button>
      </div>
    </div>
    <div id="rv-f-month"><span class="rv-lbl">Month</span><input id="rv-month" type="month" class="rv-in" value="<?php echo date('Y-m'); ?>" onchange="rvLoad()"></div>
    <div id="rv-f-year" style="display:none;"><span class="rv-lbl">Year</span>
      <select id="rv-year" class="rv-in" onchange="rvLoad()"><?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 5; $y--) echo "<option>$y</option>"; ?></select></div>
    <div id="rv-f-custom" style="display:none;gap:8px;">
      <div><span class="rv-lbl">From</span><input id="rv-from" type="date" class="rv-in" value="<?php echo date('Y-m-01'); ?>" onchange="rvLoad()"></div>
      <div><span class="rv-lbl">To</span><input id="rv-to" type="date" class="rv-in" value="<?php echo date('Y-m-d'); ?>" onchange="rvLoad()"></div>
    </div>
    <div><span class="rv-lbl">Show patients with</span>
      <select id="rv-min" class="rv-in" onchange="rvLoad()"><option value="2">2+ visits</option><option value="3">3+ visits</option><option value="5">5+ visits</option><option value="10">10+ visits</option></select></div>
    <div><span class="rv-lbl">Search</span><input id="rv-search" type="text" class="rv-in" placeholder="Name, patient No, phone" style="width:190px;" onkeydown="if(event.key==='Enter')rvLoad()"></div>
    <button class="rv-btn" onclick="rvLoad()" style="background:var(--primary-color);color:white;"><i class="fas fa-search"></i> Filter</button>
    <span id="rv-range" style="font-size:12px;color:var(--gray-500);margin-left:auto;align-self:center;"></span>
    <div id="rv-toolbar"></div><script>rptToolbar('rv-toolbar',{elementId:'rv-printarea',tableId:'rv-printarea',title:'Repeat Patients'});</script>
  </div>
  <!-- Stats -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:18px;padding:20px 22px;">
    <div class="rv-stat"><div class="k">Patients visited</div><div class="v" id="rv-s-patients">—</div><div class="s">Distinct patients</div></div>
    <div class="rv-stat"><div class="k">Repeat patients</div><div class="v" id="rv-s-repeat">—</div><div class="s">Came 2+ times</div></div>
    <div class="rv-stat"><div class="k">Total visits</div><div class="v" id="rv-s-visits">—</div><div class="s">One per patient, day &amp; department</div></div>
    <div class="rv-stat"><div class="k">Frequent patients</div><div class="v" id="rv-s-frequent" style="color:#b45309;">—</div><div class="s">Came 5+ times</div></div>
  </div>
  <div id="rv-dup" style="display:none;padding:0 22px 14px;font-size:12px;color:var(--gray-500);"></div>
</div>

<!-- Frequent visitors -->
<div id="rv-top-wrap" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);padding:16px 22px;margin-bottom:16px;display:none;">
  <div style="font-size:14px;font-weight:600;color:var(--gray-900);margin-bottom:10px;"><i class="fas fa-fire" style="color:#f59e0b;"></i> Most frequent visitors</div>
  <div id="rv-top" class="rv-top"></div>
</div>

<!-- Table -->
<div id="rv-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">#</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Patient</th>
      <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Visits</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">First visit</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Last visit</th>
      <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Visit dates</th>
      <th style="padding:10px 12px;"></th>
    </tr></thead>
    <tbody id="rv-tbody"><tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
  </table>
</div>

<!-- Visit history -->
<div id="rv-modal" onclick="if(event.target===this)rvClose()">
  <div class="d">
    <button onclick="rvClose()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <div id="rv-h-head" style="margin-bottom:14px;"></div>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
          <th style="padding:8px 10px;text-align:left;">Date</th><th style="padding:8px 10px;text-align:left;">Visit No</th>
          <th style="padding:8px 10px;text-align:left;">Department</th><th style="padding:8px 10px;text-align:left;">Doctor</th>
          <th style="padding:8px 10px;text-align:left;">Type</th><th style="padding:8px 10px;text-align:left;">Payment</th>
          <th style="padding:8px 10px;text-align:left;">Status</th><th style="padding:8px 10px;text-align:left;">Complaint</th>
        </tr></thead>
        <tbody id="rv-h-body" data-no-page></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Everything done on one date -->
<div id="rv-day" class="rv-m" onclick="if(event.target===this)rvDayClose()">
  <div class="d"><button onclick="rvDayClose()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <div id="rv-day-body"></div></div>
</div>

<script>
const RV_API=_apiBase+'?endpoint=reports/general';
let rvPeriod='month',rvRange={},rvSeq=0;
const rvEsc=s=>String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const rvD=d=>d?new Date(d+'T00:00:00').toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}):'—';
const rvShort=d=>new Date(d+'T00:00:00').toLocaleDateString('en-GB',{day:'2-digit',month:'short'});
const rvIso=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
function rvSetPeriod(p){
  rvPeriod=p;
  document.querySelectorAll('#rv-period button').forEach(b=>b.classList.toggle('on',b.dataset.p===p));
  document.getElementById('rv-f-month').style.display=p==='month'?'':'none';
  document.getElementById('rv-f-year').style.display=p==='year'?'':'none';
  document.getElementById('rv-f-custom').style.display=p==='custom'?'flex':'none';
  rvLoad();
}
function rvDates(){
  const t=new Date();
  if(rvPeriod==='month'){const [y,m]=(document.getElementById('rv-month').value||rvIso(t).slice(0,7)).split('-').map(Number);return {from:rvIso(new Date(y,m-1,1)),to:rvIso(new Date(y,m,0))};}
  if(rvPeriod==='3m'){const f=new Date(t);f.setMonth(f.getMonth()-3);f.setDate(f.getDate()+1);return {from:rvIso(f),to:rvIso(t)};}
  if(rvPeriod==='year'){const y=document.getElementById('rv-year').value;return {from:y+'-01-01',to:y+'-12-31'};}
  return {from:document.getElementById('rv-from').value,to:document.getElementById('rv-to').value};
}
async function rvLoad(){
  rvRange=rvDates();
  const tb=document.getElementById('rv-tbody'), seq=++rvSeq; // a slower earlier response must not overwrite a newer one
  if(!rvRange.from||!rvRange.to||rvRange.to<rvRange.from){tb.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:#b91c1c;">Choose a valid date range.</td></tr>';return;}
  document.getElementById('rv-range').textContent=rvD(rvRange.from)+' – '+rvD(rvRange.to);
  tb.innerHTML='<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  const q=new URLSearchParams({action:'repeat_visits',date_from:rvRange.from,date_to:rvRange.to,min:document.getElementById('rv-min').value,search:document.getElementById('rv-search').value.trim()});
  try{
    const j=await (await fetch(RV_API+'&'+q)).json();
    if(seq!==rvSeq)return;
    if(!j.success){tb.innerHTML=`<tr><td colspan="7" style="padding:30px;text-align:center;color:#b91c1c;">${rvEsc(j.message)}</td></tr>`;return;}
    const {stats,patients,frequent}=j.data, fmt=n=>(+n||0).toLocaleString();
    document.getElementById('rv-s-patients').textContent=fmt(stats.patients);
    document.getElementById('rv-s-repeat').textContent=fmt(stats.repeat_patients);
    document.getElementById('rv-s-visits').textContent=fmt(stats.visits);
    document.getElementById('rv-s-frequent').textContent=fmt(stats.frequent_patients);
    const dup=document.getElementById('rv-dup');
    dup.style.display=stats.duplicates_merged>0?'block':'none';
    dup.innerHTML=`<i class="fas fa-info-circle"></i> ${fmt(stats.duplicates_merged)} duplicate registration(s) not counted: the same patient in the same department on the same day, or an empty registration with no department. Visits to different departments on the same day count separately. Cancelled visits are not counted.`;
    // Most frequent visitors
    const top=patients.slice(0,5), max=top.length?+top[0].visits:1;
    document.getElementById('rv-top-wrap').style.display=top.length?'block':'none';
    document.getElementById('rv-top').innerHTML=top.map((p,i)=>`<div class="c" onclick="rvHistory(${p.id})">
      <div style="display:flex;justify-content:space-between;gap:8px;"><span style="font-weight:600;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${i+1}. ${rvEsc(p.patient_name)}</span><span class="rv-badge ${+p.visits>=frequent?'hot':''}">${p.visits}</span></div>
      <div style="font-size:11px;color:var(--gray-500);margin-top:2px;">${rvEsc(p.patient_no)} · last ${rvD(p.last_visit)}</div>
      <div class="rv-bar"><i style="width:${Math.round(p.visits/max*100)}%"></i></div></div>`).join('');
    if(!patients.length){tb.innerHTML='<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-user-check" style="font-size:28px;display:block;margin-bottom:8px;"></i>No patient visited that many times in this period.</td></tr>';return;}
    tb.innerHTML=patients.map((p,i)=>{
      // one chip per day; "×2" when the patient visited two departments that day
      const byDay={}; p.dates.split('|').forEach(x=>{const [d,dep]=x.split('~');(byDay[d]=byDay[d]||[]).push(dep||'No department');});
      const ds=Object.keys(byDay), hot=+p.visits>=frequent;
      const age=p.date_of_birth?Math.floor((Date.now()-new Date(p.date_of_birth))/31557600000):null;
      return `<tr class="${hot?'rv-row-hot':''}" style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onclick="rvHistory(${p.id})">
        <td style="padding:9px 12px;color:var(--gray-500);">${i+1}</td>
        <td style="padding:9px 12px;"><div style="font-weight:600;">${rvEsc(p.patient_name)} ${hot?'<span style="font-size:10px;font-weight:700;color:#b45309;background:#fef3c7;padding:1px 6px;border-radius:8px;margin-left:4px;">FREQUENT</span>':''}</div>
          <div style="font-size:11px;color:var(--gray-500);">${rvEsc(p.patient_no)}${p.gender?' · '+rvEsc(p.gender):''}${age!==null?' · '+age+' yrs':''}${p.phone?' · '+rvEsc(p.phone):''}</div></td>
        <td style="padding:9px 12px;text-align:center;"><span class="rv-badge ${hot?'hot':''}">${p.visits}</span></td>
        <td style="padding:9px 12px;white-space:nowrap;">${rvD(p.first_visit)}</td>
        <td style="padding:9px 12px;white-space:nowrap;">${rvD(p.last_visit)}</td>
        <td style="padding:9px 12px;max-width:360px;">${ds.map(d=>`<span class="rv-chip rv-day" role="button" tabindex="0" title="${rvEsc(byDay[d].join(', '))} — click for full details" onclick="event.stopPropagation();rvDay(${p.id},'${d}')" onkeydown="if(event.key==='Enter'){event.stopPropagation();rvDay(${p.id},'${d}')}" ${byDay[d].length>1?'style="background:#dbeafe;color:#1d4ed8;"':''}>${rvShort(d)}${byDay[d].length>1?' ×'+byDay[d].length:''}</span>`).join('')}</td>
        <td style="padding:9px 12px;text-align:right;"><button class="rv-btn" onclick="event.stopPropagation();rvHistory(${p.id})" style="padding:5px 12px;font-size:12px;background:white;border:1px solid var(--gray-200);color:var(--gray-700);white-space:nowrap;"><i class="fas fa-history"></i> History</button></td>
      </tr>`;}).join('');
  }catch(e){tb.innerHTML=`<tr><td colspan="7" style="padding:30px;text-align:center;color:#b91c1c;">Error: ${rvEsc(e.message)}</td></tr>`;}
}
async function rvHistory(pid){
  const m=document.getElementById('rv-modal');
  document.getElementById('rv-h-head').innerHTML='<i class="fas fa-spinner fa-spin"></i>';
  document.getElementById('rv-h-body').innerHTML='';
  m.classList.add('show');
  try{
    const j=await (await fetch(RV_API+'&action=patient_visits&patient_id='+pid)).json();
    if(!j.success){document.getElementById('rv-h-head').textContent=j.message;return;}
    const {patient:p,visits}=j.data;
    const inRange=visits.filter(v=>!v.not_counted&&v.visit_date>=rvRange.from&&v.visit_date<=rvRange.to).length;
    document.getElementById('rv-h-head').innerHTML=`<div style="font-size:18px;font-weight:600;color:var(--gray-900);">${rvEsc(p.patient_name)}</div>
      <div style="font-size:13px;color:var(--gray-500);margin-top:2px;">${rvEsc(p.patient_no)}${p.phone?' · '+rvEsc(p.phone):''}${p.gender?' · '+rvEsc(p.gender):''}</div>
      <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap;">
        <span style="padding:6px 12px;background:#dbeafe;color:#1d4ed8;border-radius:8px;font-size:13px;"><strong>${p.total_visits}</strong> visit(s) in total</span>
        <span style="padding:6px 12px;background:#fef3c7;color:#b45309;border-radius:8px;font-size:13px;"><strong>${inRange}</strong> in ${rvD(rvRange.from)} – ${rvD(rvRange.to)}</span></div>`;
    const stC={completed:'#047857',in_progress:'#1d4ed8',open:'#b45309',cancelled:'#b91c1c'};
    const why={duplicate:'same day &amp; department — counted once',empty:'empty registration (no department) — not counted'};
    document.getElementById('rv-h-body').innerHTML=visits.map(v=>{
      const muted=!!v.not_counted;
      return `<tr style="border-bottom:1px solid var(--gray-100);${muted?'color:var(--gray-400);':''}">
        <td style="padding:8px 10px;white-space:nowrap;">${rvD(v.visit_date)} <span style="font-size:11px;color:var(--gray-400);">${rvEsc(String(v.visit_time||'').slice(0,5))}</span>${why[v.not_counted]?`<div style="font-size:10px;color:var(--gray-500);">${why[v.not_counted]}</div>`:''}</td>
        <td style="padding:8px 10px;font-family:monospace;font-size:12px;white-space:nowrap;">${rvEsc(v.visit_no)}</td>
        <td style="padding:8px 10px;">${rvEsc(v.department||'—')}</td>
        <td style="padding:8px 10px;">${rvEsc(v.doctor||'—')}</td>
        <td style="padding:8px 10px;">${rvEsc(String(v.visit_type).replace('_',' '))}</td>
        <td style="padding:8px 10px;">${v.payment_type==='insurance'?rvEsc(v.insurance||'Insurance'):'Private'}</td>
        <td style="padding:8px 10px;font-weight:600;color:${stC[v.status]||'inherit'};">${rvEsc(String(v.status).replace('_',' '))}</td>
        <td style="padding:8px 10px;max-width:200px;">${rvEsc(v.chief_complaint||'—')}</td></tr>`;}).join('')||'<tr><td colspan="8" style="padding:20px;text-align:center;color:var(--gray-400);">No visits.</td></tr>';
  }catch(e){document.getElementById('rv-h-head').textContent='Error: '+e.message;}
}
const rvJ=x=>{try{return JSON.parse(x)}catch(e){return null}};
const rvT=t=>t?String(t).replace('T',' ').slice(0,16):'';
const rvE=rvEsc, rvV=x=>x===null||x===undefined||x===''?'—':rvEsc(x);
function rvTable(title,rows,cols){ // cols: [header, fn(row)->html]; empty sections are skipped
  if(!rows||!rows.length)return '';
  return `<div class="rv-sec"><h4>${title} <span style="color:var(--gray-400);font-weight:500;">(${rows.length})</span></h4><div style="overflow-x:auto;"><table><thead><tr>${cols.map(c=>`<th>${c[0]}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${cols.map(c=>`<td>${c[1](r)}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>`;
}
function rvResult(r){
  const a=rvJ(r); if(!Array.isArray(a))return r?rvE(r):'—';
  return a.map(t=>`${rvE(t.test_name||t.name||'')}: <strong>${rvE(t.value??'')}</strong> ${rvE(t.unit||'')}${t.normal_min!=null||t.normal_max!=null?` <span style="color:var(--gray-400);">(${rvE(t.normal_min??'')}–${rvE(t.normal_max??'')})</span>`:''}`).join('<br>');
}
function rvForm(d){const o=rvJ(d);if(!o||typeof o!=='object')return rvV(d);return Object.entries(o).filter(([,v])=>v!==''&&v!=null).map(([k,v])=>`<strong>${rvE(k)}</strong>: ${rvE(typeof v==='object'?JSON.stringify(v):v)}`).join('<br>')||'—';}
function rvVisitHtml(v){
  let h=`<div style="margin-top:18px;padding:12px 14px;background:var(--gray-50);border-radius:8px;">
    <div style="font-weight:700;font-size:14px;">${rvE(v.department||'No department')} <span style="font-family:monospace;font-weight:500;font-size:12px;color:var(--gray-500);">${rvE(v.visit_no)}</span></div>
    <div style="font-size:12px;color:var(--gray-600);margin-top:2px;">${rvE(String(v.visit_time||'').slice(0,5))} · ${rvE(String(v.visit_type).replace('_',' '))} · ${v.payment_type==='insurance'?rvE(v.insurance||'Insurance'):'Private'} · ${rvE(String(v.status).replace('_',' '))}${v.doctor?' · Dr '+rvE(v.doctor):''}${v.chief_complaint?' · Complaint: '+rvE(v.chief_complaint):''}</div></div>`;
  h+=(v.consultations||[]).map(c=>{
    const vit=[c.bp_systolic?`BP ${c.bp_systolic}/${c.bp_diastolic}`:'',c.pulse?`Pulse ${c.pulse}`:'',c.temperature?`Temp ${c.temperature}`:'',c.oxygen_sat?`SpO₂ ${c.oxygen_sat}%`:'',c.respiratory_rate?`RR ${c.respiratory_rate}`:'',c.weight?`Wt ${c.weight}kg`:'',c.height?`Ht ${c.height}cm`:''].filter(Boolean).join(' · ');
    const f=[['Chief complaint',c.chief_complaint],['History',c.history],['Examination',c.examination],['Vitals at consultation',vit],['Differential diagnosis',c.differential_diagnosis],['Diagnosis',(c.diagnosis||'')+(c.icd_code?' ['+c.icd_code+']':'')],['Treatment plan',c.treatment_plan],['Prescription note',c.prescription],['Notes',c.notes],['Outcome',c.outcome],['Referred to',c.referred_to],['Admit ward',c.admit_ward],['Follow-up',c.follow_up_date]].filter(x=>x[1]&&String(x[1]).trim());
    return `<div class="rv-sec"><h4>Consultation — ${rvE(c.doctor_name||'')} <span style="font-weight:500;color:var(--gray-500);">${rvE(String(c.status||'').replace('_',' '))} · ${rvT(c.created_at)}</span></h4><table><tbody>${f.map(x=>`<tr><td style="width:170px;color:var(--gray-500);">${x[0]}</td><td style="white-space:pre-wrap;">${rvE(x[1])}</td></tr>`).join('')||'<tr><td>No details recorded.</td></tr>'}</tbody></table></div>`;}).join('');
  h+=rvTable('Vital signs',v.vitals,[['Time',r=>rvT(r.recorded_at)],['Measure',r=>rvV(r.name)],['Value',r=>rvV(r.value)+' '+rvE(r.unit||'')]]);
  const lab=(v.orders||[]).filter(o=>o.order_type==='lab'), rad=(v.orders||[]).filter(o=>o.order_type!=='lab');
  h+=rvTable('Laboratory tests',lab,[['Test',r=>rvV(r.description)],['Status',r=>rvV(r.status)],['Result',r=>rvResult(r.result)],['Ordered',r=>rvT(r.ordered_at)+(r.ordered_by?' · '+rvE(r.ordered_by):'')],['Completed',r=>rvT(r.completed_at)||'—']]);
  h+=rvTable('Radiology / imaging',rad,[['Exam',r=>rvV(r.description)],['Status',r=>rvV(r.status)],['Result',r=>rvResult(r.result)],['Ordered',r=>rvT(r.ordered_at)+(r.ordered_by?' · '+rvE(r.ordered_by):'')]]);
  h+=rvTable('Prescriptions (medication)',v.prescriptions,[['Rx',r=>rvV(r.prescription_no)],['Drug',r=>rvV(r.drug)],['Dose',r=>rvV(r.dosage)],['Frequency',r=>rvV(r.frequency)],['Duration',r=>rvV(r.duration)],['Route',r=>rvV(r.route)],['Qty / dispensed',r=>rvV(r.quantity)+' / '+rvV(r.quantity_dispensed)],['Status',r=>rvV(r.item_status||r.rx_status)],['Instructions',r=>rvV(r.instructions)]]);
  h+=rvTable('External prescriptions',v.external_rx,[['Rx',r=>rvV(r.prescription_no)],['Drug',r=>rvV(r.medication_name)],['Dose',r=>rvV(r.dosage)],['Frequency',r=>rvV(r.frequency)],['Duration',r=>rvV(r.duration)],['Qty',r=>rvV(r.quantity)]]);
  h+=rvTable('Medication given (administered)',v.administrations,[['Time',r=>rvT(r.administered_at)],['Drug',r=>rvV(r.drug)],['Dose',r=>rvV(r.dose_value)+' '+rvE(r.dose_unit||'')],['Route',r=>rvV(r.route)],['By',r=>rvV(r.by_user)],['Deviation',r=>+r.is_deviation?rvE(r.deviation_reason||'Yes'):'']]);
  h+=rvTable('Nursing care',v.nursing,[['Time',r=>rvT(r.administered_at)],['Type',r=>rvV(r.record_type)],['Description',r=>rvV(r.description)],['Dose / qty',r=>rvV(r.dosage)+' / '+rvV(r.quantity)],['Notes',r=>rvV(r.notes)],['By',r=>rvV(r.by_user)]]);
  h+=rvTable('Nursing plan',v.nursing_plan,[['Type',r=>rvV(r.schedule_type)],['Description',r=>rvV(r.description)],['Frequency',r=>rvV(r.frequency)],['Start',r=>rvV(r.start_date)],['End',r=>rvV(r.end_date)],['Notes',r=>rvV(r.notes)]]);
  h+=rvTable('Procedures, services &amp; other charges',(v.billing||[]).filter(b=>!['lab','pharmacy','radiology'].includes(b.category)),[['Category',r=>rvV(r.category)],['Description',r=>rvV(r.description)],['Qty',r=>rvV(r.quantity)],['Total',r=>(+r.total||0).toLocaleString()]]);
  h+=rvTable('Surgeries',v.surgeries,[['Procedure',r=>rvV(r.procedure_name)],['Surgeon',r=>rvV(r.surgeon_name)],['Anaesthesia',r=>rvV(r.anesthesia_type)+' · '+rvV(r.anesthetist_name)],['Status',r=>rvV(r.status)],['Start–End',r=>rvT(r.actual_start)+' – '+rvT(r.actual_end)],['Pre-op',r=>rvV(r.pre_op_notes)],['Post-op',r=>rvV(r.post_op_notes)],['Complications',r=>rvV(r.complications)]]);
  h+=rvTable('Emergency',v.emergency,[['Triage',r=>rvV(r.triage_level)],['Complaint',r=>rvV(r.chief_complaint)],['Arrival',r=>rvV(r.arrival_mode)+' · '+rvV(r.consciousness)],['Vitals',r=>`BP ${rvV(r.vitals_bp)} P ${rvV(r.vitals_pulse)} T ${rvV(r.vitals_temp)} SpO₂ ${rvV(r.vitals_spo2)} RR ${rvV(r.vitals_rr)}`],['Treatment',r=>rvV(r.treatment_notes)+(r.treatments.length?'<br>'+r.treatments.map(t=>rvE(t.description||t.treatment_type)+(t.dosage?' ('+rvE(t.dosage)+')':'')).join('<br>'):'')],['Status',r=>rvV(r.status)]]);
  h+=rvTable('Admission &amp; bed stay',v.admissions,[['Ward / bed',r=>rvV(r.ward)+' · '+rvV(r.room_number)+' / '+rvV(r.bed_number)],['Type',r=>rvV(r.assignment_type)],['Admitted',r=>rvT(r.assigned_at)],['Discharged',r=>rvT(r.discharged_at)||'—'],['Outcome',r=>rvV(r.discharge_status)+(r.discharge_notes?' · '+rvE(r.discharge_notes):'')+(r.death_cause?' · '+rvE(r.death_cause):'')+(r.transfer_facility?' → '+rvE(r.transfer_facility):'')]]);
  h+=rvTable('Admission requests',v.admission_requests,[['Diagnosis',r=>rvV(r.diagnosis)],['Type',r=>rvV(r.admission_type)],['Ward',r=>rvV(r.ward_preference)],['Status',r=>rvV(r.status)],['Requested',r=>rvT(r.created_at)]]);
  h+=rvTable('Ward progress notes',v.ward_notes,[['Time',r=>rvT(r.noted_at)],['Notes',r=>rvV(r.notes)],['Physician',r=>rvV(r.physician_name)]]);
  h+=rvTable('Ward vitals',v.ward_vitals,[['Time',r=>rvT(r.recorded_at)],['Temp',r=>rvV(r.temperature)],['BP',r=>rvV(r.blood_pressure)],['SpO₂',r=>rvV(r.o2_saturation)],['Pulse',r=>rvV(r.pulse)],['Medication',r=>rvV(r.medication)]]);
  h+=rvTable('Ward lab requests',v.ward_labs,[['Time',r=>rvT(r.requested_at)],['Test',r=>rvV(r.test_name)],['Indication',r=>rvV(r.indication)]]);
  h+=rvTable('Transfers',v.transfers,[['No',r=>rvV(r.transfer_no)],['Type',r=>rvV(r.transfer_type)],['Reason',r=>rvV(r.reason)],['Condition',r=>rvV(r.clinical_condition)],['To',r=>rvV(r.to_facility)],['Status',r=>rvV(r.status)]]);
  h+=rvTable('Department movement',v.departments,[['Department',r=>rvV(r.department)],['Status',r=>rvV(r.status)],['Priority',r=>rvV(r.priority)],['Notes',r=>rvV(r.doctor_notes)+(r.critical_info?' · '+rvE(r.critical_info):'')],['From',r=>rvT(r.created_at)],['To',r=>rvT(r.completed_at)||'—']]);
  h+=rvTable('Department forms',v.forms,[['Form',r=>rvV(r.form)],['Content',r=>rvForm(r.form_data)],['Notes',r=>rvV(r.notes)],['Status',r=>rvV(r.status)]]);
  h+=rvTable('Medical documents issued',v.documents,[['Type',r=>rvV(r.document_type)],['Reference',r=>rvV(r.reference_no)],['Date',r=>rvT(r.created_at)]]);
  h+=rvTable('Clinical comments',v.comments,[['Time',r=>rvT(r.created_at)],['By',r=>rvV(r.by_user)],['Comment',r=>rvV(r.comment)]]);
  h+=rvTable('Activity timeline',v.events,[['Time',r=>rvT(r.created_at)],['Event',r=>rvV(r.event)],['Detail',r=>rvV(r.detail)],['By',r=>rvV(r.user_name)]]);
  const bill=(v.invoices||[]).map(i=>`<tr><td>${rvE(i.invoice_no)}</td><td>${rvV(i.status)}</td><td>${(+i.total||0).toLocaleString()}</td><td>${(+i.paid_amount||0).toLocaleString()}</td><td>${(+i.insurer_paid_amount||0).toLocaleString()}</td><td>${(+i.balance||0).toLocaleString()}</td></tr>`).join('');
  if(bill)h+=`<div class="rv-sec"><h4>Invoice summary</h4><table><thead><tr><th>Invoice</th><th>Status</th><th>Total</th><th>Paid</th><th>Insurer paid</th><th>Balance</th></tr></thead><tbody>${bill}</tbody></table></div>`;
  return h;
}
async function rvDay(pid,date){
  const m=document.getElementById('rv-day'), b=document.getElementById('rv-day-body');
  b.innerHTML='<i class="fas fa-spinner fa-spin"></i>'; m.classList.add('show');
  try{
    const j=await (await fetch(RV_API+'&action=patient_day&patient_id='+pid+'&date='+date)).json();
    if(!j.success){b.textContent=j.message;return;}
    const vs=j.data.visits;
    b.innerHTML=`<div style="font-size:18px;font-weight:600;">Visit details — ${rvD(date)}</div><div style="font-size:12px;color:var(--gray-500);">${vs.length} registration(s) on this date</div>`+(vs.map(rvVisitHtml).join('')||'<p>No visit found.</p>');
  }catch(e){b.textContent='Error: '+e.message;}
}
function rvDayClose(){document.getElementById('rv-day').classList.remove('show');}
function rvClose(){document.getElementById('rv-modal').classList.remove('show');}
document.addEventListener('keydown',e=>{if(e.key==='Escape'){rvClose();rvDayClose();}});
rvLoad();
</script>
<?php endif; ?>

<?php if ($subModule == 'doctor-performance'): ?>
<!-- ===== DOCTOR PERFORMANCE ===== -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label><input id="perf-from" type="date" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></div>
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label><input id="perf-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></div>
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Doctor</label><select id="perf-doctor" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></select></div>
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Department</label><select id="perf-dept" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></select></div>
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">Payer</label><select id="perf-ins" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></select></div>
  <div style="margin-top:14px;"><button onclick="perfLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button></div>
  <div id="perf-toolbar" style="margin-top:14px;margin-left:auto;"></div>
</div>
<script>rptToolbar('perf-toolbar',{elementId:'perf-printarea',tableId:'perf-table',title:'Doctor Performance',filename:'Doctor_Performance'});</script>
<div id="perf-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table id="perf-table" style="width:100%;border-collapse:collapse;font-size:13px;"></table>
  <p id="perf-note" style="padding:10px 14px;font-size:12px;color:var(--gray-500);"></p>
</div>
<div id="perf-modal" onclick="if(event.target===this)perfCloseDrill()"><div class="d">
  <button onclick="perfCloseDrill()" aria-label="Close" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
  <h3 id="perf-drill-title" style="font-size:16px;font-weight:600;margin-bottom:10px;text-transform:capitalize;"></h3>
  <div id="perf-drill-tool" style="margin-bottom:10px;"></div>
  <div id="perf-drill-area" style="overflow-x:auto;"><table id="perf-drill-table" style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead><tr style="background:var(--gray-50);"><th style="padding:7px 10px;text-align:left;">#</th><th style="padding:7px 10px;text-align:left;">Date</th><th style="padding:7px 10px;text-align:left;">Visit</th><th style="padding:7px 10px;text-align:left;">Patient No</th><th style="padding:7px 10px;text-align:left;">Patient</th><th style="padding:7px 10px;text-align:left;">Sex / Age</th><th style="padding:7px 10px;text-align:left;">Payer</th><th style="padding:7px 10px;text-align:left;">Detail</th><th style="padding:7px 10px;text-align:right;">Consult fee</th><th style="padding:7px 10px;text-align:right;">Visit revenue</th></tr></thead>
    <tbody id="perf-drill-body"></tbody></table></div>
</div></div>
<style>
#perf-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;align-items:center;justify-content:center;padding:16px;overflow-y:auto;}
#perf-modal.show{display:flex;}
#perf-modal .d{background:white;border-radius:12px;padding:24px;width:100%;max-width:960px;margin:auto;position:relative;transition:transform 200ms ease-out,opacity 200ms ease-out;}
@starting-style{#perf-modal.show .d{transform:scale(.96);opacity:0;}}
@media (prefers-reduced-motion:reduce){@starting-style{#perf-modal.show .d{transform:none;}}}
.perf-n{font-weight:600;color:var(--primary-color);text-decoration:none;}
.perf-n:hover{text-decoration:underline;}
</style>
<script>var PERF={kind:'doctor'};rptToolbar('perf-drill-tool',{elementId:'perf-drill-area',tableId:'perf-drill-table',title:'Doctor performance detail'});</script>
<script src="assets/js/report-performance.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/report-performance.js'); ?>"></script>
<?php endif; ?>

<?php if ($subModule == 'nurse-performance'): ?>
<!-- ===== NURSE PERFORMANCE ===== -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label><input id="perf-from" type="date" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></div>
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label><input id="perf-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></div>
  <div style="margin-top:14px;"><button onclick="perfLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button></div>
  <div id="perf-toolbar" style="margin-top:14px;margin-left:auto;"></div>
</div>
<script>rptToolbar('perf-toolbar',{elementId:'perf-printarea',tableId:'perf-table',title:'Nurse Performance',filename:'Nurse_Performance'});</script>
<div id="perf-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table id="perf-table" style="width:100%;border-collapse:collapse;font-size:13px;"></table>
  <p id="perf-note" style="padding:10px 14px;font-size:12px;color:var(--gray-500);"></p>
</div>
<div id="perf-modal" onclick="if(event.target===this)perfCloseDrill()"><div class="d">
  <button onclick="perfCloseDrill()" aria-label="Close" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
  <h3 id="perf-drill-title" style="font-size:16px;font-weight:600;margin-bottom:10px;text-transform:capitalize;"></h3>
  <div id="perf-drill-tool" style="margin-bottom:10px;"></div>
  <div id="perf-drill-area" style="overflow-x:auto;"><table id="perf-drill-table" style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead><tr style="background:var(--gray-50);"><th style="padding:7px 10px;text-align:left;">#</th><th style="padding:7px 10px;text-align:left;">Date</th><th style="padding:7px 10px;text-align:left;">Visit</th><th style="padding:7px 10px;text-align:left;">Patient No</th><th style="padding:7px 10px;text-align:left;">Patient</th><th style="padding:7px 10px;text-align:left;">Sex / Age</th><th style="padding:7px 10px;text-align:left;">Payer</th><th style="padding:7px 10px;text-align:left;">Detail</th><th style="padding:7px 10px;text-align:right;">Consult fee</th><th style="padding:7px 10px;text-align:right;">Visit revenue</th></tr></thead>
    <tbody id="perf-drill-body"></tbody></table></div>
</div></div>
<style>
#perf-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;align-items:center;justify-content:center;padding:16px;overflow-y:auto;}
#perf-modal.show{display:flex;}
#perf-modal .d{background:white;border-radius:12px;padding:24px;width:100%;max-width:960px;margin:auto;position:relative;transition:transform 200ms ease-out,opacity 200ms ease-out;}
@starting-style{#perf-modal.show .d{transform:scale(.96);opacity:0;}}
@media (prefers-reduced-motion:reduce){@starting-style{#perf-modal.show .d{transform:none;}}}
.perf-n{font-weight:600;color:var(--primary-color);text-decoration:none;}
.perf-n:hover{text-decoration:underline;}
</style>
<script>var PERF={kind:'nurse'};rptToolbar('perf-drill-tool',{elementId:'perf-drill-area',tableId:'perf-drill-table',title:'Nurse performance detail'});</script>
<script src="assets/js/report-performance.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/report-performance.js'); ?>"></script>
<?php endif; ?>

<?php if ($subModule == 'repeat-diagnoses'): ?>
<!-- ===== REPEAT DIAGNOSES ===== -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">From</label><input id="perf-from" type="date" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></div>
  <div><label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;">To</label><input id="perf-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></div>
  <div style="margin-top:14px;"><button onclick="perfLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button></div>
  <div id="perf-toolbar" style="margin-top:14px;margin-left:auto;"></div>
</div>
<script>rptToolbar('perf-toolbar',{elementId:'perf-printarea',tableId:'perf-printarea',title:'Repeat diagnoses',filename:'Repeat_Diagnoses'});</script>
<div id="perf-printarea" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);"><th style="padding:9px 10px;text-align:left;">#</th><th style="padding:9px 10px;text-align:left;">Flagged</th><th style="padding:9px 10px;text-align:left;">Patient No</th><th style="padding:9px 10px;text-align:left;">Patient</th><th style="padding:9px 10px;text-align:left;">Visit</th><th style="padding:9px 10px;text-align:left;">Diagnosis</th><th style="padding:9px 10px;text-align:center;">Occurrences</th><th style="padding:9px 10px;text-align:left;">Action</th><th style="padding:9px 10px;text-align:left;">Justification</th><th style="padding:9px 10px;text-align:left;">By</th></tr></thead>
    <tbody id="perf-body"></tbody>
  </table>
</div>
<script>var PERF={kind:'repeat'};</script>
<script src="assets/js/report-performance.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/report-performance.js'); ?>"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
