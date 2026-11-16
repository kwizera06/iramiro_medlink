<?php
// requisitions.php — Internal Consumable Requisitions & Approvals
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'my-requests';
if ($subModule === 'internal-requisition') $subModule = 'my-requests';
$subTitles = [
    'my-requests' => 'My Consumable Requests',
    'verification' => 'Requisition Approvals & Expense Posting',
    'approved' => 'Approved Requisitions & Stock Issued'
];
$currentTitle = $subTitles[$subModule] ?? 'Internal Requisitions';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
  <div>
    <h2 style="font-size:24px;font-weight:700;color:var(--gray-900);margin:0;"><i class="fas fa-boxes" style="color:var(--primary-color);margin-right:8px;"></i>Internal Requisitions</h2>
    <p style="color:var(--gray-500);font-size:13px;margin:3px 0 0 0;">Request consumable and supplies items for internal department consumption</p>
  </div>
  <?php if($subModule == 'my-requests'): ?>
  <button onclick="reqOpenModal()" style="padding:10px 18px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;font-weight:700;font-size:13px;display:flex;align-items:center;gap:6px;box-shadow:0 2px 8px rgba(16,185,129,0.25);">
    <i class="fas fa-plus"></i> New Internal Requisition
  </button>
  <?php endif; ?>
</div>

<!-- Navigation Tabs -->
<div style="display:flex;gap:6px;margin-bottom:18px;background:white;padding:6px;border-radius:10px;box-shadow:var(--shadow);width:fit-content;flex-wrap:wrap;">
  <a href="requisitions.php?sub=my-requests" style="text-decoration:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;<?php echo $subModule=='my-requests'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>">
    <i class="fas fa-clipboard-list"></i> My Requests
  </a>
  <a href="requisitions.php?sub=verification" style="text-decoration:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;<?php echo $subModule=='verification'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>">
    <i class="fas fa-check-double"></i> Approvals & Verification
  </a>
  <a href="requisitions.php?sub=approved" style="text-decoration:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;<?php echo $subModule=='approved'?'background:var(--primary-color);color:white;':'background:transparent;color:var(--gray-600);';?>">
    <i class="fas fa-history"></i> Approved & Stock Issued
  </a>
</div>

<!-- Stats Overview -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:18px;">
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--primary-color);">
    <div id="req-s-total" style="font-size:22px;font-weight:700;color:var(--gray-900);">—</div>
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;margin-top:2px;">Total Requisitions</div>
  </div>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--warning-color);">
    <div id="req-s-pending" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div>
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;margin-top:2px;">Pending Approval</div>
  </div>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--success-color);">
    <div id="req-s-approved" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div>
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;margin-top:2px;">Approved & Posted</div>
  </div>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--danger-color);">
    <div id="req-s-rejected" style="font-size:22px;font-weight:700;color:var(--danger-color);">—</div>
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;margin-top:2px;">Rejected</div>
  </div>
</div>

<?php if($subModule=='verification'): ?>
<!-- Approvals Notice -->
<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:10px;">
  <i class="fas fa-info-circle" style="font-size:18px;"></i>
  <div>
    <strong>Approvals Workflow:</strong> Review submitted requisitions. When approved, stock is automatically reduced from the selected store batches and a balanced Journal Entry (Debit: Expense, Credit: Inventory) is created in Accounting.
  </div>
</div>
<?php elseif($subModule=='approved'): ?>
<!-- Approved Notice -->
<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#166534;display:flex;align-items:center;gap:10px;">
  <i class="fas fa-check-circle" style="font-size:18px;"></i>
  <div>
    <strong>Historical Log:</strong> All completed requisitions with associated batch stock deductions and Journal Entry accounting references.
  </div>
</div>
<?php endif; ?>

<!-- Filters Bar -->
<div style="background:white;border-radius:10px;padding:14px 18px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
  <div style="flex:2;min-width:220px;">
    <input type="text" id="req-filter-search" placeholder="Search item, department, req #, purpose, requester..." oninput="reqFilterSearch=this.value;reqPage=1;reqLoad();" style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;">
  </div>
  <div style="flex:1;min-width:180px;">
    <select id="req-filter-dept" onchange="reqFilterDept=this.value;reqPage=1;reqLoad();" style="width:100%;padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
      <option value="">All Departments</option>
    </select>
  </div>
  <div style="flex:1;min-width:160px;">
    <select id="req-filter-store" onchange="reqFilterStore=this.value;reqPage=1;reqLoad();" style="width:100%;padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
      <option value="">All Stores</option>
    </select>
  </div>
  <div style="min-width:140px;">
    <select id="req-filter-status" onchange="reqFilterStatus=this.value;reqPage=1;reqLoad();" style="width:100%;padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
      <option value="">All Statuses</option>
      <option value="draft">Draft</option>
      <option value="submitted">Submitted</option>
      <option value="approved">Approved & Issued</option>
      <option value="rejected">Rejected</option>
    </select>
  </div>
</div>

<!-- Table Card -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Requisition #</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Date</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Requested By / Dept</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Store</th>
        <th style="padding:12px 14px;text-align:center;font-weight:600;">Items / Qty</th>
        <th style="padding:12px 14px;text-align:right;font-weight:600;">Est. Cost</th>
        <th style="padding:12px 14px;text-align:center;font-weight:600;">Priority</th>
        <th style="padding:12px 14px;text-align:center;font-weight:600;">Status</th>
        <th style="padding:12px 14px;text-align:center;font-weight:600;">Action</th>
      </tr>
    </thead>
    <tbody id="req-tbody">
      <tr>
        <td colspan="9" style="padding:48px;text-align:center;color:var(--gray-400);">
          <i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--primary-color);display:block;margin-bottom:8px;"></i>
          Loading requisitions...
        </td>
      </tr>
    </tbody>
  </table>
  <div id="req-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:12px 16px;border-top:1px solid var(--gray-100);"></div>
</div>

<script>
const _apiBase = window.location.origin + '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/api.php';
const REQ_SUB = '<?php echo $subModule; ?>';
</script>
<script src="assets/js/requisitions.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
