<?php
// public/pharmacy.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'prescription';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Page title mapping
$subModuleTitles = [
    'prescription' => 'Prescriptions',
    'dispensing' => 'Dispensing',
    'pos' => 'Pharmacy POS',
    'stock' => 'Pharmacy Stock',
    'interaction' => 'Drug Interaction Check',
    'controlled-drugs' => 'Controlled Drugs',
    'stock-request' => 'Stock Requests',
    'stock-receive' => 'Receive Stock',
    'stock-movements' => 'Stock Movements'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'Pharmacy';
?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'prescription' => 'Manage and process patient prescriptions',
                'dispensing' => 'Dispense medications to patients',
                'pos' => 'Pharmacy point of sale and billing',
                'stock' => 'Manage pharmacy inventory and stock levels',
                'interaction' => 'Check for drug interactions and allergies',
                'controlled-drugs' => 'Manage controlled substances and narcotics',
                'stock-request' => 'Request stock items from central store',
                'stock-receive' => 'Receive and verify incoming stock deliveries',
                'stock-movements' => 'Track all pharmacy stock movements in/out'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Pharmacy Management';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($action == 'list'): ?>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=<?php echo $subModule; ?>&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Add New
        </button>
        <?php endif; ?>
        <?php if ($action != 'list'): ?>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
PRESCRIPTIONS
============================================ -->
<?php if ($subModule == 'prescription'): ?>

<?php if ($action == 'add'): ?>
<!-- CREATE PRESCRIPTION FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-file-prescription" style="color: var(--primary-color);"></i> Create Prescription
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Prescription ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., RX-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Patient <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Patient</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Doctor <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Doctor</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Date Prescribed <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Drug Name <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter drug name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Strength <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 500mg" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Dosage Form <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Form</option>
                    <option value="tablet">Tablet</option>
                    <option value="capsule">Capsule</option>
                    <option value="syrup">Syrup</option>
                    <option value="injection">Injection</option>
                    <option value="ointment">Ointment</option>
                    <option value="cream">Cream</option>
                    <option value="drops">Drops</option>
                    <option value="inhaler">Inhaler</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Quantity <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter quantity" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Dosage Instructions <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 1 tablet twice daily" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Duration <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 7 days" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Special Instructions
                </label>
                <textarea rows="2" placeholder="Special instructions or notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Create Prescription
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- PRESCRIPTIONS LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Summary Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 20px;">
        <div onclick="rxQuickFilterStatus('all')" style="padding: 14px 16px; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='var(--gray-200)'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 12px; font-weight: 600; color: var(--gray-600); text-transform: uppercase;">Total</span>
                <i class="fas fa-file-prescription" style="color: var(--primary-color); opacity: 0.8;"></i>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: var(--gray-900); margin-top: 4px;" data-rx-stat="total">0</div>
        </div>
        <div onclick="rxQuickFilterStatus('active')" style="padding: 14px 16px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#d97706'" onmouseout="this.style.borderColor='#fde68a'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 12px; font-weight: 600; color: #92400e; text-transform: uppercase;">Pending</span>
                <i class="fas fa-clock" style="color: #d97706;"></i>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: #b45309; margin-top: 4px;" data-rx-stat="pending">0</div>
        </div>
        <div onclick="rxQuickFilterStatus('dispensed_partial')" style="padding: 14px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb'" onmouseout="this.style.borderColor='#bfdbfe'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 12px; font-weight: 600; color: #1e40af; text-transform: uppercase;">Partial</span>
                <i class="fas fa-hourglass-half" style="color: #3b82f6;"></i>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: #1d4ed8; margin-top: 4px;" data-rx-stat="partial">0</div>
        </div>
        <div onclick="rxQuickFilterStatus('dispensed_full')" style="padding: 14px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#16a34a'" onmouseout="this.style.borderColor='#bbf7d0'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 12px; font-weight: 600; color: #166534; text-transform: uppercase;">Dispensed</span>
                <i class="fas fa-check-circle" style="color: #16a34a;"></i>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: #15803d; margin-top: 4px;" data-rx-stat="dispensed">0</div>
        </div>
        <div onclick="rxQuickFilterStatus('cancelled')" style="padding: 14px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#dc2626'" onmouseout="this.style.borderColor='#fecaca'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 12px; font-weight: 600; color: #991b1b; text-transform: uppercase;">Cancelled</span>
                <i class="fas fa-times-circle" style="color: #dc2626;"></i>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: #b91c1c; margin-top: 4px;" data-rx-stat="cancelled">0</div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; padding: 16px; margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
            <!-- Search Input -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-search" style="color: var(--primary-color);"></i> Search Prescriptions
                </label>
                <div style="position: relative;">
                    <input type="text" id="rx-filter-search" placeholder="Search by Patient name/No, Rx #, Doctor, Drug, Phone..." 
                           oninput="rxDebounceSearch()" onkeydown="if(event.key==='Enter'){rxCurrentPage=1;rxPharmLoad();}"
                           style="width: 100%; padding: 9px 32px 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
                    <button id="rx-search-clear-btn" onclick="rxClearSearch()" style="display:none; position:absolute; right:8px; top:50%; transform:translateY(-50%); border:none; background:none; color:var(--gray-400); cursor:pointer; font-size:14px;">&times;</button>
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-filter" style="color: var(--gray-500);"></i> Status
                </label>
                <select id="rx-filter-status" onchange="rxApplyFilter()" style="width: 100%; padding: 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
                    <option value="all" selected>All Statuses</option>
                    <option value="active">Pending (Active)</option>
                    <option value="dispensed_partial">Partially Dispensed</option>
                    <option value="dispensed_full">Fully Dispensed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <!-- Destination Filter -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-hospital" style="color: var(--gray-500);"></i> Destination
                </label>
                <select id="rx-filter-dest" onchange="rxApplyFilter()" style="width: 100%; padding: 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
                    <option value="all">All Destinations</option>
                    <option value="hospital">Hospital Pharmacy</option>
                    <option value="external">External Pharmacy</option>
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-calendar-alt" style="color: var(--gray-500);"></i> From Date
                </label>
                <input type="date" id="rx-filter-from" onchange="rxApplyFilter()" oninput="rxApplyFilter()" style="width: 100%; padding: 8px 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
            </div>

            <!-- Date To -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-calendar-check" style="color: var(--gray-500);"></i> To Date
                </label>
                <input type="date" id="rx-filter-to" onchange="rxApplyFilter()" oninput="rxApplyFilter()" style="width: 100%; padding: 8px 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 8px;">
                <button onclick="rxResetFilters()" title="Reset all filters" style="padding: 9px 14px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button onclick="rxPharmLoad()" title="Refresh List" style="padding: 9px 14px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap;">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <!-- Date Quick Presets & Limit Selector -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--gray-200); font-size: 12px; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                <span style="font-weight: 600; color: var(--gray-500); margin-right: 4px;">Quick Date Range:</span>
                <button type="button" onclick="rxSetDateRange('today')" class="rx-date-btn" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px;">Today</button>
                <button type="button" onclick="rxSetDateRange('yesterday')" class="rx-date-btn" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px;">Yesterday</button>
                <button type="button" onclick="rxSetDateRange('week')" class="rx-date-btn" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px;">This Week</button>
                <button type="button" onclick="rxSetDateRange('month')" class="rx-date-btn" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px;">This Month</button>
                <button type="button" onclick="rxSetDateRange('all')" class="rx-date-btn" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px; font-weight:600;">All Dates</button>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span style="color: var(--gray-600); font-weight: 500;">Rows per page:</span>
                <select id="rx-filter-limit" onchange="rxApplyLimit()" style="padding: 4px 8px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 12px; background: white;">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Active Filter Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <div id="rx-result-summary" style="font-size: 13px; color: var(--gray-600);">
            Loading prescriptions...
        </div>
    </div>

    <!-- Prescriptions Table -->
    <div style="border: 1px solid var(--gray-200); border-radius: 8px; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
      <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 960px;">
        <thead>
          <tr style="background: var(--gray-50); border-bottom: 2px solid var(--gray-200);">
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Rx #</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Patient</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Doctor / Visit</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Date Prescribed</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Prescribed Drugs</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Destination</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Status</th>
            <th style="padding: 12px 14px; text-align: center; font-weight: 600; color: var(--gray-700);">Action</th>
          </tr>
        </thead>
        <tbody id="rx-pharm-tbody">
          <tr><td colspan="8" style="padding: 40px; text-align: center; color: var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i><div style="margin-top: 8px;">Loading prescriptions...</div></td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <div id="rx-pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; flex-wrap: wrap; gap: 12px;">
        <div id="rx-pagination-info" style="font-size: 13px; color: var(--gray-600);">
            Showing 0 to 0 of 0 entries
        </div>
        <div id="rx-pagination-buttons" style="display: flex; gap: 4px; align-items: center;">
            <!-- Buttons dynamically generated -->
        </div>
    </div>
</div>

<script>
const rxAPI = 'api.php?endpoint=pharmacy/prescriptions';
let rxCurrentPage = 1;
let rxCurrentLimit = 25;
let rxSearchTimer = null;
let rxAllPrescriptionsCache = [];

function rxDebounceSearch(){
  const searchVal = document.getElementById('rx-filter-search')?.value.trim() || '';
  const clearBtn = document.getElementById('rx-search-clear-btn');
  if(clearBtn) clearBtn.style.display = searchVal ? 'block' : 'none';
  
  clearTimeout(rxSearchTimer);
  rxSearchTimer = setTimeout(() => {
    rxCurrentPage = 1;
    rxPharmLoad();
  }, 250);
}

function rxClearSearch(){
  const inp = document.getElementById('rx-filter-search');
  if(inp) inp.value = '';
  const clearBtn = document.getElementById('rx-search-clear-btn');
  if(clearBtn) clearBtn.style.display = 'none';
  rxCurrentPage = 1;
  rxPharmLoad();
}

function rxApplyFilter(){
  rxCurrentPage = 1;
  rxPharmLoad();
}

function rxApplyLimit(){
  const lim = parseInt(document.getElementById('rx-filter-limit')?.value || 25);
  rxCurrentLimit = lim;
  rxCurrentPage = 1;
  rxPharmLoad();
}

function rxQuickFilterStatus(status){
  const sel = document.getElementById('rx-filter-status');
  if(sel) sel.value = status;
  rxCurrentPage = 1;
  rxPharmLoad();
}

function rxSetDateRange(type){
  const now = new Date();
  const fromEl = document.getElementById('rx-filter-from');
  const toEl = document.getElementById('rx-filter-to');
  
  const formatDate = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  if(type === 'today'){
    const todayStr = formatDate(now);
    if(fromEl) fromEl.value = todayStr;
    if(toEl) toEl.value = todayStr;
  } else if(type === 'yesterday'){
    const yest = new Date(now);
    yest.setDate(yest.getDate() - 1);
    const yestStr = formatDate(yest);
    if(fromEl) fromEl.value = yestStr;
    if(toEl) toEl.value = yestStr;
  } else if(type === 'week'){
    const firstDay = new Date(now);
    firstDay.setDate(now.getDate() - now.getDay());
    if(fromEl) fromEl.value = formatDate(firstDay);
    if(toEl) toEl.value = formatDate(now);
  } else if(type === 'month'){
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    if(fromEl) fromEl.value = formatDate(firstDay);
    if(toEl) toEl.value = formatDate(now);
  } else if(type === 'all'){
    if(fromEl) fromEl.value = '';
    if(toEl) toEl.value = '';
  }
  rxCurrentPage = 1;
  rxPharmLoad();
}

function rxResetFilters(){
  const searchEl = document.getElementById('rx-filter-search');
  const statusEl = document.getElementById('rx-filter-status');
  const destEl = document.getElementById('rx-filter-dest');
  const fromEl = document.getElementById('rx-filter-from');
  const toEl = document.getElementById('rx-filter-to');
  const clearBtn = document.getElementById('rx-search-clear-btn');
  
  if(searchEl) searchEl.value = '';
  if(statusEl) statusEl.value = 'all';
  if(destEl) destEl.value = 'all';
  if(fromEl) fromEl.value = '';
  if(toEl) toEl.value = '';
  if(clearBtn) clearBtn.style.display = 'none';
  
  rxCurrentPage = 1;
  rxPharmLoad();
}

function rxGoToPage(page){
  rxCurrentPage = Math.max(1, page);
  rxPharmLoad();
}

async function rxPharmLoad(){
  const tbody = document.getElementById('rx-pharm-tbody');
  if(tbody) {
    tbody.innerHTML = '<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i><div style="margin-top:8px;">Loading prescriptions...</div></td></tr>';
  }

  const searchVal = document.getElementById('rx-filter-search')?.value.trim() || '';
  const statusVal = document.getElementById('rx-filter-status')?.value || 'all';
  const destVal = document.getElementById('rx-filter-dest')?.value || 'all';
  const fromVal = document.getElementById('rx-filter-from')?.value || '';
  const toVal = document.getElementById('rx-filter-to')?.value || '';
  
  let url = `${rxAPI}&page=${rxCurrentPage}&limit=${rxCurrentLimit}`;
  if(searchVal) url += `&search=${encodeURIComponent(searchVal)}`;
  if(statusVal && statusVal !== 'all') url += `&status=${encodeURIComponent(statusVal)}`;
  if(destVal && destVal !== 'all') url += `&destination=${encodeURIComponent(destVal)}`;
  if(fromVal) url += `&from=${encodeURIComponent(fromVal)}`;
  if(toVal) url += `&to=${encodeURIComponent(toVal)}`;

  try{
    const r = await fetch(url);
    const j = await r.json();
    if(j.success && j.data){
      const prescriptions = j.data.prescriptions || [];
      const stats = j.data.stats || {};
      const pagination = j.data.pagination || {total: prescriptions.length, page: rxCurrentPage, limit: rxCurrentLimit, total_pages: 1};
      
      rxAllPrescriptionsCache = prescriptions;

      // Update stats cards
      document.querySelectorAll('[data-rx-stat]').forEach(el => {
        const key = el.getAttribute('data-rx-stat');
        if(stats[key] !== undefined) {
          el.textContent = Number(stats[key]).toLocaleString();
        }
      });

      // Update result summary text
      const summaryEl = document.getElementById('rx-result-summary');
      if(summaryEl) {
        if(pagination.total > 0){
          summaryEl.innerHTML = `Found <strong>${pagination.total.toLocaleString()}</strong> prescription(s) ${statusVal !== 'all' ? `(Status: <em>${statusVal}</em>)` : ''} ${searchVal ? `matching "<em>${dqEsc(searchVal)}</em>"` : ''}`;
        } else {
          summaryEl.innerHTML = 'No prescriptions matching current criteria';
        }
      }

      if(prescriptions.length > 0){
        tbody.innerHTML = prescriptions.map(rx => {
          const patientName = ((rx.pat_first||'')+' '+(rx.pat_last||'')).trim() || '—';
          const doctorName = ((rx.doc_first||'')+' '+(rx.doc_last||'')).trim() || (rx.doctor_name||'—');
          
          const destBadge = rx.destination === 'external' 
            ? '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-external-link-alt" style="font-size:9px;"></i> External</span>'
            : '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-hospital-alt" style="font-size:9px;"></i> Hospital</span>';
          
          const statusMap = {
            'active': '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-clock" style="font-size:9px;"></i> Pending</span>',
            'dispensed_partial': '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#eff6ff;color:#1d4ed8;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-hourglass-half" style="font-size:9px;"></i> Partial</span>',
            'dispensed_full': '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-check" style="font-size:9px;"></i> Dispensed</span>',
            'cancelled': '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#fee2e2;color:#991b1b;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-times" style="font-size:9px;"></i> Cancelled</span>',
            'expired': '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#f3f4f6;color:#6b7280;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-ban" style="font-size:9px;"></i> Expired</span>'
          };

          const itemsText = rx.items_summary ? dqEsc(rx.items_summary) : `${rx.item_count || 0} medication(s)`;

          return `<tr style="border-bottom:1px solid var(--gray-100); transition:background 0.15s;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
            <td style="padding:12px 14px;font-family:monospace;color:var(--primary-color);font-weight:700;">
              ${dqEsc(rx.prescription_no || '—')}
              ${rx.is_controlled == 1 ? '<span style="margin-left:4px;padding:1px 5px;background:#fee2e2;color:#991b1b;border-radius:4px;font-size:10px;font-weight:700;">CTRL</span>' : ''}
            </td>
            <td style="padding:12px 14px;">
              <div style="font-weight:600;color:var(--gray-900);">${dqEsc(patientName)}</div>
              <div style="font-size:11px;color:var(--gray-500);margin-top:2px;">
                ${rx.patient_no ? `<span style="font-family:monospace;">${dqEsc(rx.patient_no)}</span>` : ''} 
                ${rx.pat_phone ? `• <i class="fas fa-phone" style="font-size:9px;"></i> ${dqEsc(rx.pat_phone)}` : ''}
              </div>
            </td>
            <td style="padding:12px 14px;">
              <div style="font-weight:500;color:var(--gray-900);">${dqEsc(doctorName)}</div>
              ${rx.visit_no ? `<div style="font-size:11px;color:var(--gray-500);font-family:monospace;">${dqEsc(rx.visit_no)}</div>` : ''}
            </td>
            <td style="padding:12px 14px;">
              <div style="font-weight:500;color:var(--gray-800);">${rx.prescription_date || '—'}</div>
              ${rx.created_at ? `<div style="font-size:11px;color:var(--gray-400);">${rx.created_at.substring(11, 16)}</div>` : ''}
            </td>
            <td style="padding:12px 14px;max-width:280px;">
              <div style="font-size:12px;color:var(--gray-800);line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${itemsText}">
                ${itemsText}
              </div>
              ${rx.diagnosis ? `<div style="font-size:11px;color:var(--gray-500);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${dqEsc(rx.diagnosis)}">Dx: ${dqEsc(rx.diagnosis)}</div>` : ''}
            </td>
            <td style="padding:12px 14px;">${destBadge}${rx.destination === 'external' ? '' : `<div style="margin-top:4px;" title="Pharmacy bill">${payBadge(rx.pay_status, rx.pay_balance)}</div>`}</td>
            <td style="padding:12px 14px;">${statusMap[rx.status] || rx.status}</td>
            <td style="padding:12px 14px;text-align:center;white-space:nowrap;">
              <button onclick="rxPharmView(${rx.id})" style="padding:6px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:5px;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                <i class="fas fa-pills"></i> ${rx.status === 'active' ? 'Dispense' : 'View'}
              </button>
              <button onclick="rxPrintSlip(${rx.id})" title="Print Slip" style="padding:6px 10px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;margin-left:4px;">
                <i class="fas fa-print"></i>
              </button>
            </td>
          </tr>`;
        }).join('');
      } else {
        tbody.innerHTML = `<tr><td colspan="8" style="padding:48px 20px;text-align:center;color:var(--gray-400);">
          <div style="font-size:32px;margin-bottom:10px;color:var(--gray-300);"><i class="fas fa-prescription-bottle-alt"></i></div>
          <div style="font-size:14px;font-weight:600;color:var(--gray-600);">No prescriptions found</div>
          <div style="font-size:12px;color:var(--gray-400);margin-top:4px;">Try adjusting your filters or date range</div>
          <button onclick="rxResetFilters()" style="margin-top:12px;padding:6px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;">Reset Filters</button>
        </td></tr>`;
      }

      // Render pagination
      rxRenderPagination(pagination);

    } else {
      tbody.innerHTML = `<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--danger-color);">${j.message || 'Failed to load prescriptions.'}</td></tr>`;
    }
  } catch(e){
    if(tbody) tbody.innerHTML = `<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--danger-color);">Error loading prescriptions: ${dqEsc(e.message)}</td></tr>`;
  }
}

function rxRenderPagination(p){
  const infoEl = document.getElementById('rx-pagination-info');
  const btnsEl = document.getElementById('rx-pagination-buttons');
  if(!infoEl || !btnsEl) return;

  const total = p.total || 0;
  const page = p.page || 1;
  const totalPages = p.total_pages || 1;
  const from = p.from || 0;
  const to = p.to || 0;

  infoEl.innerHTML = total > 0 
    ? `Showing <strong>${from}</strong> to <strong>${to}</strong> of <strong>${total.toLocaleString()}</strong> prescriptions`
    : `No entries to show`;

  if(totalPages <= 1){
    btnsEl.innerHTML = '';
    return;
  }

  let html = '';
  
  // Previous button
  const prevDisabled = page <= 1 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : `onclick="rxGoToPage(${page - 1})"`;
  html += `<button ${prevDisabled} style="padding:6px 10px;background:white;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;color:var(--gray-700);"><i class="fas fa-chevron-left"></i></button>`;

  // Page Numbers
  let pages = [];
  if(totalPages <= 7){
    for(let i=1; i<=totalPages; i++) pages.push(i);
  } else {
    pages.push(1);
    if(page > 3) pages.push('...');
    const start = Math.max(2, page - 1);
    const end = Math.min(totalPages - 1, page + 1);
    for(let i=start; i<=end; i++) pages.push(i);
    if(page < totalPages - 2) pages.push('...');
    pages.push(totalPages);
  }

  pages.forEach(pg => {
    if(pg === '...'){
      html += `<span style="padding:6px 8px;font-size:12px;color:var(--gray-400);">...</span>`;
    } else if(pg === page){
      html += `<button style="padding:6px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:default;">${pg}</button>`;
    } else {
      html += `<button onclick="rxGoToPage(${pg})" style="padding:6px 12px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;">${pg}</button>`;
    }
  });

  // Next button
  const nextDisabled = page >= totalPages ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : `onclick="rxGoToPage(${page + 1})"`;
  html += `<button ${nextDisabled} style="padding:6px 10px;background:white;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;color:var(--gray-700);"><i class="fas fa-chevron-right"></i></button>`;

  btnsEl.innerHTML = html;
}

async function rxPharmView(id){
  try{
    const r = await fetch(rxAPI+'&id='+id);
    const j = await r.json();
    if(j.success && j.data){
      const rx = j.data;
      const items = rx.items || [];
      const patientName = ((rx.pat_first||'')+' '+(rx.pat_last||'')).trim() || '—';
      const doctorName = ((rx.doc_first||'')+' '+(rx.doc_last||'')).trim() || (rx.doctor_name||'—');
      const isActive = rx.status === 'active';
      const isPartial = rx.status === 'dispensed_partial';
      const canDispense = isActive || isPartial;

      let itemsHtml = items.map((it, i) => {
        const drugName = it.name || it.instructions || 'Drug #'+(i+1);
        const isPending = it.status === 'pending';
        const pendingQty = Math.max(0, (parseFloat(it.quantity) || 0) - (parseFloat(it.quantity_dispensed) || 0));
        const clinicStock = parseFloat(it.clinic_stock) || 0;
        const hasEnoughStock = clinicStock >= pendingQty;
        
        const isOutside = it.destination === 'external'; // patient buys it outside: nothing to dispense or bill
        const statusBadge = isOutside
          ? '<span style="padding:3px 8px;border-radius:8px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;"><i class="fas fa-external-link-alt" style="font-size:9px;"></i> Outside</span>'
          : it.status === 'dispensed'
          ? '<span style="padding:3px 8px;border-radius:8px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;">Dispensed</span>'
          : '<span style="padding:3px 8px;border-radius:8px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;">Pending</span>';

        const stockBadge = isPending && canDispense && !isOutside
          ? (clinicStock <= 0 
              ? '<span style="font-size:11px;color:#dc2626;font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Out of stock (0 in Clinic Store)</span>'
              : (hasEnoughStock 
                  ? `<span style="font-size:11px;color:#16a34a;font-weight:500;">✓ ${clinicStock} avail in Clinic Store</span>`
                  : `<span style="font-size:11px;color:#d97706;font-weight:600;"><i class="fas fa-exclamation-circle"></i> Low: only ${clinicStock} avail in Clinic Store</span>`))
          : '';

        let dispInputHtml = '';
        if (isOutside) {
          dispInputHtml = '<span style="font-size:11px;color:#92400e;">Patient buys outside</span>';
        } else if (isPending && canDispense) {
          if (clinicStock <= 0) {
            dispInputHtml = `
              <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                <input type="number" class="rx-disp-qty" data-item-id="${it.id}" data-drug-name="${dqEsc(drugName)}" data-clinic-stock="0"
                       value="0" min="0" max="0" disabled
                       style="width:70px;padding:6px 8px;border:1px solid #fca5a5;background:#fee2e2;color:#991b1b;border-radius:6px;font-size:13px;font-weight:600;text-align:center;cursor:not-allowed;">
                <span style="font-size:10px;color:#dc2626;font-weight:700;">Out of stock</span>
              </div>
            `;
          } else {
            const defaultQty = Math.min(pendingQty, clinicStock);
            dispInputHtml = `
              <div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
                <input type="number" class="rx-disp-qty" data-item-id="${it.id}" data-drug-name="${dqEsc(drugName)}" data-clinic-stock="${clinicStock}"
                       value="${defaultQty}" min="0" max="${clinicStock}" 
                       oninput="if(parseFloat(this.value) > ${clinicStock}){ this.style.borderColor='#dc2626'; this.style.backgroundColor='#fee2e2'; } else { this.style.borderColor='var(--gray-300)'; this.style.backgroundColor='white'; }"
                       style="width:70px;padding:6px 8px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;font-weight:600;text-align:center;">
                ${clinicStock < pendingQty ? `<span style="font-size:10px;color:#d97706;font-weight:600;">Max: ${clinicStock}</span>` : ''}
              </div>
            `;
          }
        } else {
          dispInputHtml = `<span style="font-weight:600;color:var(--gray-700);">${Number(it.quantity_dispensed||0)}</span>`;
        }

        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:12px 10px;">
            <div style="font-weight:600;color:var(--gray-900);">${dqEsc(drugName)}</div>
            ${it.item_code ? `<div style="font-size:11px;color:var(--gray-400);font-family:monospace;">${dqEsc(it.item_code)}</div>` : ''}
            ${stockBadge ? `<div style="margin-top:2px;">${stockBadge}</div>` : ''}
          </td>
          <td style="padding:12px 10px;color:var(--gray-700);">${dqEsc(it.dosage||'—')}</td>
          <td style="padding:12px 10px;color:var(--gray-700);">${dqEsc(it.frequency||'—')}</td>
          <td style="padding:12px 10px;color:var(--gray-700);">${dqEsc(it.duration||'—')}</td>
          <td style="padding:12px 10px;font-weight:700;color:var(--gray-900);">${Number(it.quantity||0)}</td>
          <td style="padding:12px 10px;text-align:center;">
            ${dispInputHtml}
          </td>
          <td style="padding:12px 10px;text-align:center;">${statusBadge}</td>
        </tr>`;
      }).join('');

      const modal = document.createElement('div');
      modal.id = 'rx-dispense-modal';
      modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;';
      modal.innerHTML = `<div style="background:white;border-radius:14px;width:820px;max-width:95vw;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;">
        <!-- Modal Header -->
        <div style="padding:18px 24px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;background:white;">
          <div>
            <div style="display:flex;align-items:center;gap:10px;">
              <h3 style="font-size:17px;font-weight:700;color:var(--gray-900);margin:0;"><i class="fas fa-pills" style="color:#16a34a;"></i> ${dqEsc(rx.prescription_no)}</h3>
              <span style="padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;background:${rx.destination==='external'?'#fef3c7':'#dbeafe'};color:${rx.destination==='external'?'#92400e':'#1e40af'};">${rx.destination==='external'?'External Pharmacy':'Hospital Pharmacy'}</span>
            </div>
            <p style="font-size:12px;color:var(--gray-500);margin:4px 0 0 0;">Prescribed on ${rx.prescription_date||'—'} by Dr. ${dqEsc(doctorName)}</p>
          </div>
          <button onclick="document.getElementById('rx-dispense-modal').remove()" style="border:none;background:none;font-size:24px;cursor:pointer;color:var(--gray-400);line-height:1;">&times;</button>
        </div>

        <!-- Modal Body -->
        <div style="padding:20px 24px;overflow-y:auto;flex:1;">
          <!-- Patient & Visit Details Card -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
            <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:12px 16px;">
              <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;">Patient Info</div>
              <div style="font-size:15px;font-weight:700;color:var(--gray-900);margin-top:2px;">${dqEsc(patientName)}</div>
              <div style="font-size:12px;color:var(--gray-600);margin-top:2px;">
                ${rx.patient_no ? `<span style="font-family:monospace;font-weight:600;">${dqEsc(rx.patient_no)}</span>` : ''}
                ${rx.pat_phone ? `• <i class="fas fa-phone" style="font-size:10px;"></i> ${dqEsc(rx.pat_phone)}` : ''}
              </div>
            </div>
            <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:12px 16px;">
              <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:0.5px;">Diagnosis & Clinical Notes</div>
              <div style="font-size:14px;font-weight:600;color:var(--gray-900);margin-top:2px;">${dqEsc(rx.diagnosis || 'None specified')}</div>
              <div style="font-size:12px;color:var(--gray-600);margin-top:2px;">
                ${rx.visit_no ? `Visit: <span style="font-family:monospace;">${dqEsc(rx.visit_no)}</span>` : ''}
              </div>
            </div>
          </div>

          <!-- Prescribed Medications Table -->
          <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:18px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Medication</th>
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Dosage</th>
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Frequency</th>
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Duration</th>
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Prescribed</th>
                  <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">${canDispense ? 'Dispense Qty' : 'Dispensed'}</th>
                  <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Status</th>
                </tr>
              </thead>
              <tbody>${itemsHtml}</tbody>
            </table>
          </div>

          ${rx.notes ? `<div style="margin-bottom:16px;padding:10px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;font-size:12px;color:#0369a1;"><i class="fas fa-sticky-note"></i> <strong>Doctor\'s Notes:</strong> ${dqEsc(rx.notes)}</div>` : ''}

          ${canDispense ? `
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;font-size:12px;color:#1e40af;">
              <i class="fas fa-info-circle"></i> <strong>Inventory Notice:</strong> Confirming dispensing will automatically deduct item quantities from <strong>Clinic Store (Store #1005)</strong> using FIFO and post the journal entry (Dr COGS / Cr Clinic Store).
            </div>
          ` : ''}
        </div>

        <!-- Modal Footer -->
        <div style="padding:16px 24px;border-top:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;background:var(--gray-50);">
          <button onclick="rxPrintSlip(${rx.id})" style="padding:9px 16px;background:white;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:500;color:var(--gray-700);cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-print"></i> Print Slip
          </button>
          
          <div style="display:flex;gap:10px;">
            <button onclick="document.getElementById('rx-dispense-modal').remove()" style="padding:9px 18px;background:white;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:500;color:var(--gray-700);cursor:pointer;">
              Close
            </button>
            ${canDispense ? `
              <button onclick="rxDispense(${rx.id})" style="padding:9px 22px;background:#16a34a;color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                <i class="fas fa-check-circle"></i> Confirm Dispense
              </button>
            ` : ''}
          </div>
        </div>
      </div>`;
      document.body.appendChild(modal);
    }
  } catch(e){ alert('Error loading details: '+e.message); }
}

function dqEsc(s){
  return String(s||'')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}

async function rxDispense(prescriptionId){
  const qtyInputs = document.querySelectorAll('.rx-disp-qty');
  const items = [];
  const errors = [];

  qtyInputs.forEach((inp) => {
    if (inp.disabled) return;
    const itemId = inp.getAttribute('data-item-id');
    const drugName = inp.getAttribute('data-drug-name') || 'Medication';
    const clinicStock = parseFloat(inp.getAttribute('data-clinic-stock')) || 0;
    const qty = parseFloat(inp.value) || 0;

    if (qty > 0) {
      if (clinicStock <= 0) {
        errors.push(`"${drugName}" is out of stock in Clinic Store (0 available).`);
      } else if (qty > clinicStock) {
        errors.push(`"${drugName}": Dispense quantity (${qty}) exceeds available Clinic Store stock (${clinicStock}).`);
      } else {
        items.push({item_id: parseInt(itemId), quantity_dispensed: qty});
      }
    }
  });
  
  if (errors.length > 0) {
    return alert('⚠️ Warning - Cannot Dispense:\n\n' + errors.join('\n') + '\n\nPlease adjust quantities to match available stock in Clinic Store.');
  }

  if(!items.length) {
    return alert('⚠️ Warning: No valid items to dispense. Please make sure quantities are greater than 0 and stock is available in Clinic Store.');
  }
  
  if(!confirm('Confirm dispensing ' + items.length + ' item(s)?\n\nStock will be deducted from Clinic Store and a journal entry will be posted.')) return;
  
  try{
    const r = await fetch(rxAPI+'&action=dispense', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({prescription_id: prescriptionId, items: items})
    });
    const j = await r.json();
    if(j.success){
      alert('✓ ' + (j.message || 'Dispensed successfully.'));
      document.getElementById('rx-dispense-modal')?.remove();
      rxPharmLoad();
    } else {
      alert('⚠️ ' + j.message);
    }
  } catch(e){
    alert('Failed: ' + e.message);
  }
}

async function rxPrintSlip(id){
  try{
    const r = await fetch(rxAPI+'&id='+id);
    const j = await r.json();
    if(!j.success || !j.data) return alert('Failed to load prescription for print');
    const rx = j.data;
    const items = rx.items || [];
    const patientName = ((rx.pat_first||'')+' '+(rx.pat_last||'')).trim() || '—';
    const doctorName = ((rx.doc_first||'')+' '+(rx.doc_last||'')).trim() || (rx.doctor_name||'—');

    const printWin = window.open('', '_blank', 'width=700,height=800');
    if(!printWin) return alert('Please allow popups to print');

    const itemsRows = items.map((it, i) => `
      <tr>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;">${i+1}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;font-weight:600;">${dqEsc(it.name||it.instructions||'Drug')}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;">${dqEsc(it.dosage||'—')}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;">${dqEsc(it.frequency||'—')}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;">${dqEsc(it.duration||'—')}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;text-align:right;font-weight:600;">${Number(it.quantity||0)}</td>
      </tr>
    `).join('');

    printWin.document.write(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>Prescription - ${dqEsc(rx.prescription_no)}</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 24px; color: #111; font-size: 13px; }
          .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 16px; }
          .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
          table { width: 100%; border-collapse: collapse; margin-top: 12px; }
          th { background: #f3f4f6; text-align: left; padding: 8px; border-bottom: 2px solid #ddd; font-size: 12px; }
          @media print { button { display: none; } }
        </style>
      </head>
      <body>
        <div class="header">
          <h2 style="margin:0;color:#2563eb;">IRAMIRO CLINIC</h2>
          <div style="font-size:12px;color:#666;margin-top:2px;">PRESCRIPTION ORDER SLIP</div>
        </div>
        <div class="grid">
          <div>
            <strong>Rx #:</strong> <span style="font-family:monospace;font-weight:700;">${dqEsc(rx.prescription_no)}</span><br>
            <strong>Date:</strong> ${rx.prescription_date||''}<br>
            <strong>Prescriber:</strong> Dr. ${dqEsc(doctorName)}
          </div>
          <div>
            <strong>Patient:</strong> ${dqEsc(patientName)}<br>
            <strong>Patient #:</strong> ${dqEsc(rx.patient_no||'—')}<br>
            <strong>Diagnosis:</strong> ${dqEsc(rx.diagnosis||'—')}
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th style="width:30px;">#</th>
              <th>Medication</th>
              <th>Dosage</th>
              <th>Frequency</th>
              <th>Duration</th>
              <th style="text-align:right;">Qty</th>
            </tr>
          </thead>
          <tbody>${itemsRows}</tbody>
        </table>
        ${rx.notes ? `<div style="margin-top:16px;padding:8px 12px;background:#f9fafb;border-radius:4px;"><strong>Notes:</strong> ${dqEsc(rx.notes)}</div>` : ''}
        <div style="margin-top:32px;display:flex;justify-content:space-between;padding-top:20px;border-top:1px dashed #ccc;">
          <div>Pharmacist Signature: __________________</div>
          <div>Date Dispensed: __________________</div>
        </div>
        <script>
          window.onload = function(){ window.print(); };
        <\/script>
      </body>
      </html>
    `);
    printWin.document.close();
  } catch(e){ alert('Error printing: '+e.message); }
}

// Initial load
document.addEventListener('DOMContentLoaded', () => {
  rxPharmLoad();
});
// If DOM already loaded
if(document.readyState === 'complete' || document.readyState === 'interactive'){
  rxPharmLoad();
}
</script>
<?php endif; ?>

<!-- ============================================
DISPENSING
============================================ -->
<?php elseif ($subModule == 'dispensing'): ?>

<!-- DISPENSED PRESCRIPTIONS LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Summary Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 20px;">
        <div onclick="rxDispQuickFilterStatus('dispensed')" style="padding: 14px 16px; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary-color)'" onmouseout="this.style.borderColor='var(--gray-200)'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 12px; font-weight: 600; color: var(--gray-600); text-transform: uppercase;">Total Dispensed</span>
                <i class="fas fa-receipt" style="color: var(--primary-color); opacity: 0.8;"></i>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: var(--gray-900); margin-top: 4px;" data-rx-disp-stat="total">0</div>
        </div>
        <div onclick="rxDispQuickFilterStatus('dispensed_full')" style="padding: 14px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#16a34a'" onmouseout="this.style.borderColor='#bbf7d0'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 12px; font-weight: 600; color: #166534; text-transform: uppercase;">Fully Dispensed</span>
                <i class="fas fa-check-circle" style="color: #16a34a;"></i>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: #15803d; margin-top: 4px;" data-rx-disp-stat="full">0</div>
        </div>
        <div onclick="rxDispQuickFilterStatus('dispensed_partial')" style="padding: 14px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb'" onmouseout="this.style.borderColor='#bfdbfe'">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size: 12px; font-weight: 600; color: #1e40af; text-transform: uppercase;">Partially Dispensed</span>
                <i class="fas fa-hourglass-half" style="color: #3b82f6;"></i>
            </div>
            <div style="font-size: 22px; font-weight: 700; color: #1d4ed8; margin-top: 4px;" data-rx-disp-stat="partial">0</div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div style="background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; padding: 16px; margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
            <!-- Search Input -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-search" style="color: var(--primary-color);"></i> Search Dispensed Orders
                </label>
                <div style="position: relative;">
                    <input type="text" id="rx-disp-search" placeholder="Search by Patient name/No, Rx #, Doctor, Drug..." 
                           oninput="rxDispDebounceSearch()" onkeydown="if(event.key==='Enter'){rxDispCurrentPage=1;rxDispensedLoad();}"
                           style="width: 100%; padding: 9px 32px 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
                    <button id="rx-disp-clear-btn" onclick="rxDispClearSearch()" style="display:none; position:absolute; right:8px; top:50%; transform:translateY(-50%); border:none; background:none; color:var(--gray-400); cursor:pointer; font-size:14px;">&times;</button>
                </div>
            </div>

            <!-- Status Filter -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-filter" style="color: var(--gray-500);"></i> Dispense Status
                </label>
                <select id="rx-disp-status" onchange="rxDispApplyFilter()" style="width: 100%; padding: 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
                    <option value="dispensed" selected>All Dispensed</option>
                    <option value="dispensed_full">Fully Dispensed</option>
                    <option value="dispensed_partial">Partially Dispensed</option>
                </select>
            </div>

            <!-- Destination Filter -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-hospital" style="color: var(--gray-500);"></i> Destination
                </label>
                <select id="rx-disp-dest" onchange="rxDispApplyFilter()" style="width: 100%; padding: 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
                    <option value="all">All Destinations</option>
                    <option value="hospital">Hospital Pharmacy</option>
                    <option value="external">External Pharmacy</option>
                </select>
            </div>

            <!-- Date From -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-calendar-alt" style="color: var(--gray-500);"></i> From Date
                </label>
                <input type="date" id="rx-disp-from" onchange="rxDispApplyFilter()" oninput="rxDispApplyFilter()" style="width: 100%; padding: 8px 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
            </div>

            <!-- Date To -->
            <div>
                <label style="display: block; font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">
                    <i class="fas fa-calendar-check" style="color: var(--gray-500);"></i> To Date
                </label>
                <input type="date" id="rx-disp-to" onchange="rxDispApplyFilter()" oninput="rxDispApplyFilter()" style="width: 100%; padding: 8px 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 8px;">
                <button onclick="rxDispResetFilters()" title="Reset all filters" style="padding: 9px 14px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap;">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button onclick="rxDispensedLoad()" title="Refresh List" style="padding: 9px 14px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap;">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <!-- Date Quick Presets & Limit Selector -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--gray-200); font-size: 12px; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                <span style="font-weight: 600; color: var(--gray-500); margin-right: 4px;">Quick Date Range:</span>
                <button type="button" onclick="rxDispSetDateRange('today')" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px;">Today</button>
                <button type="button" onclick="rxDispSetDateRange('yesterday')" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px;">Yesterday</button>
                <button type="button" onclick="rxDispSetDateRange('week')" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px;">This Week</button>
                <button type="button" onclick="rxDispSetDateRange('month')" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px;">This Month</button>
                <button type="button" onclick="rxDispSetDateRange('all')" style="padding: 4px 10px; background: white; border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer; font-size: 12px; font-weight:600;">All Dates</button>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <span style="color: var(--gray-600); font-weight: 500;">Rows per page:</span>
                <select id="rx-disp-limit" onchange="rxDispApplyLimit()" style="padding: 4px 8px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 12px; background: white;">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Active Filter Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <div id="rx-disp-result-summary" style="font-size: 13px; color: var(--gray-600);">
            Loading dispensed orders...
        </div>
    </div>

    <!-- Dispensed Table -->
    <div style="border: 1px solid var(--gray-200); border-radius: 8px; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
      <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 960px;">
        <thead>
          <tr style="background: var(--gray-50); border-bottom: 2px solid var(--gray-200);">
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Rx #</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Patient</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Doctor / Visit</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Date</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Dispensed Drugs</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Destination</th>
            <th style="padding: 12px 14px; text-align: left; font-weight: 600; color: var(--gray-700);">Status</th>
            <th style="padding: 12px 14px; text-align: center; font-weight: 600; color: var(--gray-700);">Action</th>
          </tr>
        </thead>
        <tbody id="rx-dispensed-tbody">
          <tr><td colspan="8" style="padding: 40px; text-align: center; color: var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i><div style="margin-top: 8px;">Loading dispensed records...</div></td></tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination Controls -->
    <div id="rx-disp-pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; flex-wrap: wrap; gap: 12px;">
        <div id="rx-disp-pagination-info" style="font-size: 13px; color: var(--gray-600);">
            Showing 0 to 0 of 0 entries
        </div>
        <div id="rx-disp-pagination-buttons" style="display: flex; gap: 4px; align-items: center;">
            <!-- Buttons dynamically generated -->
        </div>
    </div>
</div>

<script>
const rxDispAPI = 'api.php?endpoint=pharmacy/prescriptions';
function dqEsc(s){
  return String(s||'')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}
let rxDispCurrentPage = 1;
let rxDispCurrentLimit = 25;
let rxDispSearchTimer = null;

function rxDispDebounceSearch(){
  const searchVal = document.getElementById('rx-disp-search')?.value.trim() || '';
  const clearBtn = document.getElementById('rx-disp-clear-btn');
  if(clearBtn) clearBtn.style.display = searchVal ? 'block' : 'none';
  
  clearTimeout(rxDispSearchTimer);
  rxDispSearchTimer = setTimeout(() => {
    rxDispCurrentPage = 1;
    rxDispensedLoad();
  }, 250);
}

function rxDispClearSearch(){
  const inp = document.getElementById('rx-disp-search');
  if(inp) inp.value = '';
  const clearBtn = document.getElementById('rx-disp-clear-btn');
  if(clearBtn) clearBtn.style.display = 'none';
  rxDispCurrentPage = 1;
  rxDispensedLoad();
}

function rxDispApplyFilter(){
  rxDispCurrentPage = 1;
  rxDispensedLoad();
}

function rxDispApplyLimit(){
  const lim = parseInt(document.getElementById('rx-disp-limit')?.value || 25);
  rxDispCurrentLimit = lim;
  rxDispCurrentPage = 1;
  rxDispensedLoad();
}

function rxDispQuickFilterStatus(status){
  const sel = document.getElementById('rx-disp-status');
  if(sel) sel.value = status;
  rxDispCurrentPage = 1;
  rxDispensedLoad();
}

function rxDispSetDateRange(type){
  const now = new Date();
  const fromEl = document.getElementById('rx-disp-from');
  const toEl = document.getElementById('rx-disp-to');
  
  const formatDate = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  if(type === 'today'){
    const todayStr = formatDate(now);
    if(fromEl) fromEl.value = todayStr;
    if(toEl) toEl.value = todayStr;
  } else if(type === 'yesterday'){
    const yest = new Date(now);
    yest.setDate(yest.getDate() - 1);
    const yestStr = formatDate(yest);
    if(fromEl) fromEl.value = yestStr;
    if(toEl) toEl.value = yestStr;
  } else if(type === 'week'){
    const firstDay = new Date(now);
    firstDay.setDate(now.getDate() - now.getDay());
    if(fromEl) fromEl.value = formatDate(firstDay);
    if(toEl) toEl.value = formatDate(now);
  } else if(type === 'month'){
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    if(fromEl) fromEl.value = formatDate(firstDay);
    if(toEl) toEl.value = formatDate(now);
  } else if(type === 'all'){
    if(fromEl) fromEl.value = '';
    if(toEl) toEl.value = '';
  }
  rxDispCurrentPage = 1;
  rxDispensedLoad();
}

function rxDispResetFilters(){
  const searchEl = document.getElementById('rx-disp-search');
  const statusEl = document.getElementById('rx-disp-status');
  const destEl = document.getElementById('rx-disp-dest');
  const fromEl = document.getElementById('rx-disp-from');
  const toEl = document.getElementById('rx-disp-to');
  const clearBtn = document.getElementById('rx-disp-clear-btn');
  
  if(searchEl) searchEl.value = '';
  if(statusEl) statusEl.value = 'dispensed';
  if(destEl) destEl.value = 'all';
  if(fromEl) fromEl.value = '';
  if(toEl) toEl.value = '';
  if(clearBtn) clearBtn.style.display = 'none';
  
  rxDispCurrentPage = 1;
  rxDispensedLoad();
}

function rxDispGoToPage(page){
  rxDispCurrentPage = Math.max(1, page);
  rxDispensedLoad();
}

async function rxDispensedLoad(){
  const tbody = document.getElementById('rx-dispensed-tbody');
  if(tbody) {
    tbody.innerHTML = '<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i><div style="margin-top:8px;">Loading dispensed records...</div></td></tr>';
  }

  const searchVal = document.getElementById('rx-disp-search')?.value.trim() || '';
  const statusVal = document.getElementById('rx-disp-status')?.value || 'dispensed';
  const destVal = document.getElementById('rx-disp-dest')?.value || 'all';
  const fromVal = document.getElementById('rx-disp-from')?.value || '';
  const toVal = document.getElementById('rx-disp-to')?.value || '';
  
  let url = `${rxDispAPI}&page=${rxDispCurrentPage}&limit=${rxDispCurrentLimit}&status=${encodeURIComponent(statusVal)}`;
  if(searchVal) url += `&search=${encodeURIComponent(searchVal)}`;
  if(destVal && destVal !== 'all') url += `&destination=${encodeURIComponent(destVal)}`;
  if(fromVal) url += `&from=${encodeURIComponent(fromVal)}`;
  if(toVal) url += `&to=${encodeURIComponent(toVal)}`;

  try{
    const r = await fetch(url);
    const j = await r.json();
    if(j.success && j.data){
      const prescriptions = j.data.prescriptions || [];
      const stats = j.data.stats || {};
      const pagination = j.data.pagination || {total: prescriptions.length, page: rxDispCurrentPage, limit: rxDispCurrentLimit, total_pages: 1};
      
      // Update stats cards
      const dispTotal = (stats.dispensed || 0) + (stats.partial || 0);
      const statTotalEl = document.querySelector('[data-rx-disp-stat="total"]');
      const statFullEl = document.querySelector('[data-rx-disp-stat="full"]');
      const statPartEl = document.querySelector('[data-rx-disp-stat="partial"]');
      
      if(statTotalEl) statTotalEl.textContent = Number(dispTotal).toLocaleString();
      if(statFullEl) statFullEl.textContent = Number(stats.dispensed || 0).toLocaleString();
      if(statPartEl) statPartEl.textContent = Number(stats.partial || 0).toLocaleString();

      // Summary text
      const summaryEl = document.getElementById('rx-disp-result-summary');
      if(summaryEl) {
        if(pagination.total > 0){
          summaryEl.innerHTML = `Found <strong>${pagination.total.toLocaleString()}</strong> dispensed prescription(s) ${searchVal ? `matching "<em>${dqEsc(searchVal)}</em>"` : ''}`;
        } else {
          summaryEl.innerHTML = 'No dispensed prescriptions matching current filters';
        }
      }

      if(prescriptions.length > 0){
        tbody.innerHTML = prescriptions.map(rx => {
          const patientName = ((rx.pat_first||'')+' '+(rx.pat_last||'')).trim() || '—';
          const doctorName = ((rx.doc_first||'')+' '+(rx.doc_last||'')).trim() || (rx.doctor_name||'—');
          
          const destBadge = rx.destination === 'external' 
            ? '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-external-link-alt" style="font-size:9px;"></i> External</span>'
            : '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-hospital-alt" style="font-size:9px;"></i> Hospital</span>';
          
          const statusBadge = rx.status === 'dispensed_full'
            ? '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-check-circle" style="font-size:9px;"></i> Fully Dispensed</span>'
            : '<span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:#eff6ff;color:#1d4ed8;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-hourglass-half" style="font-size:9px;"></i> Partially Dispensed</span>';

          const itemsText = rx.items_summary ? dqEsc(rx.items_summary) : `${rx.item_count || 0} drugs`;

          return `<tr style="border-bottom:1px solid var(--gray-100); transition:background 0.15s;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
            <td style="padding:12px 14px;font-family:monospace;color:var(--primary-color);font-weight:700;">
              ${dqEsc(rx.prescription_no || '—')}
              ${rx.is_controlled == 1 ? '<span style="margin-left:4px;padding:1px 5px;background:#fee2e2;color:#991b1b;border-radius:4px;font-size:10px;font-weight:700;">CTRL</span>' : ''}
            </td>
            <td style="padding:12px 14px;">
              <div style="font-weight:600;color:var(--gray-900);">${dqEsc(patientName)}</div>
              <div style="font-size:11px;color:var(--gray-500);margin-top:2px;">
                ${rx.patient_no ? `<span style="font-family:monospace;">${dqEsc(rx.patient_no)}</span>` : ''} 
                ${rx.pat_phone ? `• <i class="fas fa-phone" style="font-size:9px;"></i> ${dqEsc(rx.pat_phone)}` : ''}
              </div>
            </td>
            <td style="padding:12px 14px;">
              <div style="font-weight:500;color:var(--gray-900);">${dqEsc(doctorName)}</div>
              ${rx.visit_no ? `<div style="font-size:11px;color:var(--gray-500);font-family:monospace;">${dqEsc(rx.visit_no)}</div>` : ''}
            </td>
            <td style="padding:12px 14px;">
              <div style="font-weight:500;color:var(--gray-800);">${rx.prescription_date || '—'}</div>
              ${rx.created_at ? `<div style="font-size:11px;color:var(--gray-400);">${rx.created_at.substring(11, 16)}</div>` : ''}
            </td>
            <td style="padding:12px 14px;max-width:280px;">
              <div style="font-size:12px;color:var(--gray-800);line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${itemsText}">
                ${itemsText}
              </div>
              ${rx.diagnosis ? `<div style="font-size:11px;color:var(--gray-500);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${dqEsc(rx.diagnosis)}">Dx: ${dqEsc(rx.diagnosis)}</div>` : ''}
            </td>
            <td style="padding:12px 14px;">${destBadge}${rx.destination === 'external' ? '' : `<div style="margin-top:4px;" title="Pharmacy bill">${payBadge(rx.pay_status, rx.pay_balance)}</div>`}</td>
            <td style="padding:12px 14px;">${statusBadge}</td>
            <td style="padding:12px 14px;text-align:center;white-space:nowrap;">
              <button onclick="rxDispensedView(${rx.id})" style="padding:6px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:5px;">
                <i class="fas fa-eye"></i> Details
              </button>
              <button onclick="rxPrintDispensedSlip(${rx.id})" title="Print Receipt" style="padding:6px 10px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;margin-left:4px;">
                <i class="fas fa-print"></i>
              </button>
            </td>
          </tr>`;
        }).join('');
      } else {
        tbody.innerHTML = `<tr><td colspan="8" style="padding:48px 20px;text-align:center;color:var(--gray-400);">
          <div style="font-size:32px;margin-bottom:10px;color:var(--gray-300);"><i class="fas fa-check-circle"></i></div>
          <div style="font-size:14px;font-weight:600;color:var(--gray-600);">No dispensed prescriptions found</div>
          <div style="font-size:12px;color:var(--gray-400);margin-top:4px;">Prescriptions will appear here once dispensed</div>
          <button onclick="rxDispResetFilters()" style="margin-top:12px;padding:6px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;">Reset Filters</button>
        </td></tr>`;
      }

      // Render pagination
      rxDispRenderPagination(pagination);

    } else {
      tbody.innerHTML = `<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--danger-color);">${j.message || 'Failed to load dispensed prescriptions.'}</td></tr>`;
    }
  } catch(e){
    if(tbody) tbody.innerHTML = `<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--danger-color);">Error loading: ${dqEsc(e.message)}</td></tr>`;
  }
}

function rxDispRenderPagination(p){
  const infoEl = document.getElementById('rx-disp-pagination-info');
  const btnsEl = document.getElementById('rx-disp-pagination-buttons');
  if(!infoEl || !btnsEl) return;

  const total = p.total || 0;
  const page = p.page || 1;
  const totalPages = p.total_pages || 1;
  const from = p.from || 0;
  const to = p.to || 0;

  infoEl.innerHTML = total > 0 
    ? `Showing <strong>${from}</strong> to <strong>${to}</strong> of <strong>${total.toLocaleString()}</strong> dispensed orders`
    : `No entries to show`;

  if(totalPages <= 1){
    btnsEl.innerHTML = '';
    return;
  }

  let html = '';
  const prevDisabled = page <= 1 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : `onclick="rxDispGoToPage(${page - 1})"`;
  html += `<button ${prevDisabled} style="padding:6px 10px;background:white;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;color:var(--gray-700);"><i class="fas fa-chevron-left"></i></button>`;

  let pages = [];
  if(totalPages <= 7){
    for(let i=1; i<=totalPages; i++) pages.push(i);
  } else {
    pages.push(1);
    if(page > 3) pages.push('...');
    const start = Math.max(2, page - 1);
    const end = Math.min(totalPages - 1, page + 1);
    for(let i=start; i<=end; i++) pages.push(i);
    if(page < totalPages - 2) pages.push('...');
    pages.push(totalPages);
  }

  pages.forEach(pg => {
    if(pg === '...'){
      html += `<span style="padding:6px 8px;font-size:12px;color:var(--gray-400);">...</span>`;
    } else if(pg === page){
      html += `<button style="padding:6px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:default;">${pg}</button>`;
    } else {
      html += `<button onclick="rxDispGoToPage(${pg})" style="padding:6px 12px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;">${pg}</button>`;
    }
  });

  const nextDisabled = page >= totalPages ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : `onclick="rxDispGoToPage(${page + 1})"`;
  html += `<button ${nextDisabled} style="padding:6px 10px;background:white;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;color:var(--gray-700);"><i class="fas fa-chevron-right"></i></button>`;

  btnsEl.innerHTML = html;
}

async function rxDispensedView(id){
  try{
    const r = await fetch(rxDispAPI+'&id='+id);
    const j = await r.json();
    if(j.success && j.data){
      const rx = j.data;
      const items = rx.items || [];
      const patientName = ((rx.pat_first||'')+' '+(rx.pat_last||'')).trim() || '—';
      const doctorName = ((rx.doc_first||'')+' '+(rx.doc_last||'')).trim() || (rx.doctor_name||'—');
      
      let itemsHtml = items.map((it, i) => {
        const drugName = it.name || it.instructions || 'Drug #'+(i+1);
        const qtyPrescribed = parseFloat(it.quantity || 0);
        const qtyDispensed = parseFloat(it.quantity_dispensed || 0);
        const unitPrice = parseFloat(it.selling_price || it.unit_price || 0);
        const itemTotal = qtyDispensed * unitPrice;

        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:10px 12px;font-weight:600;color:var(--gray-900);">${dqEsc(drugName)}</td>
          <td style="padding:10px 12px;color:var(--gray-700);">${dqEsc(it.dosage||'—')}</td>
          <td style="padding:10px 12px;color:var(--gray-700);">${dqEsc(it.frequency||'—')}</td>
          <td style="padding:10px 12px;color:var(--gray-700);">${dqEsc(it.duration||'—')}</td>
          <td style="padding:10px 12px;font-weight:600;">${qtyPrescribed}</td>
          <td style="padding:10px 12px;font-weight:700;color:#065f46;background:#f0fdf4;text-align:center;">${qtyDispensed}</td>
          <td style="padding:10px 12px;text-align:right;">${unitPrice.toLocaleString()} RWF</td>
          <td style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-900);">${itemTotal.toLocaleString()} RWF</td>
        </tr>`;
      }).join('');
      
      const total = items.reduce((sum, it) => sum + (parseFloat(it.quantity_dispensed||0) * parseFloat(it.selling_price || it.unit_price || 0)), 0);
      
      const modal = document.createElement('div');
      modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;';
      modal.innerHTML = `<div style="background:white;border-radius:14px;width:820px;max-width:95vw;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;">
        <!-- Modal Header -->
        <div style="padding:18px 24px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;background:white;">
          <div>
            <div style="display:flex;align-items:center;gap:10px;">
              <h3 style="font-size:17px;font-weight:700;color:var(--gray-900);margin:0;"><i class="fas fa-receipt" style="color:#16a34a;"></i> Dispensed Order: ${dqEsc(rx.prescription_no)}</h3>
              <span style="padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;">${rx.status === 'dispensed_full' ? 'Fully Dispensed' : 'Partially Dispensed'}</span>
            </div>
            <p style="font-size:12px;color:var(--gray-500);margin:4px 0 0 0;">${dqEsc(patientName)} | Dr. ${dqEsc(doctorName)} | Date: ${rx.prescription_date||'—'}</p>
          </div>
          <button onclick="this.closest('[style*=position]').remove()" style="border:none;background:none;font-size:24px;cursor:pointer;color:var(--gray-400);line-height:1;">&times;</button>
        </div>

        <!-- Modal Body -->
        <div style="padding:20px 24px;overflow-y:auto;flex:1;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
            <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:12px 16px;">
              <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;">Patient</div>
              <div style="font-size:15px;font-weight:700;color:var(--gray-900);margin-top:2px;">${dqEsc(patientName)}</div>
              <div style="font-size:12px;color:var(--gray-600);margin-top:2px;">
                ${rx.patient_no ? `<span style="font-family:monospace;font-weight:600;">${dqEsc(rx.patient_no)}</span>` : ''}
                ${rx.pat_phone ? `• <i class="fas fa-phone" style="font-size:10px;"></i> ${dqEsc(rx.pat_phone)}` : ''}
              </div>
            </div>
            <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:12px 16px;">
              <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;">Prescriber & Diagnosis</div>
              <div style="font-size:14px;font-weight:600;color:var(--gray-900);margin-top:2px;">Dr. ${dqEsc(doctorName)}</div>
              <div style="font-size:12px;color:var(--gray-600);margin-top:2px;">${dqEsc(rx.diagnosis || 'Diagnosis: —')} ${rx.visit_no ? `(Visit: ${dqEsc(rx.visit_no)})` : ''}</div>
            </div>
          </div>
          
          <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:16px;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
              <thead>
                <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Medication</th>
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Dosage</th>
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Freq</th>
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Duration</th>
                  <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Prescribed</th>
                  <th style="padding:10px 12px;text-align:center;font-weight:600;color:#065f46;background:#f0fdf4;">Dispensed</th>
                  <th style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-700);">Unit Price</th>
                  <th style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-700);">Total</th>
                </tr>
              </thead>
              <tbody>${itemsHtml}</tbody>
              <tfoot>
                <tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);">
                  <td colspan="7" style="padding:12px 14px;font-weight:700;text-align:right;color:var(--gray-700);">Total Value:</td>
                  <td style="padding:12px 14px;font-weight:800;font-size:15px;color:#065f46;text-align:right;">${total.toLocaleString()} RWF</td>
                </tr>
              </tfoot>
            </table>
          </div>
          
          ${rx.notes ? `<div style="padding:10px 14px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;font-size:12px;color:#0369a1;margin-bottom:12px;"><i class="fas fa-sticky-note"></i> <strong>Notes:</strong> ${dqEsc(rx.notes)}</div>` : ''}
        </div>

        <!-- Modal Footer -->
        <div style="padding:16px 24px;border-top:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;background:var(--gray-50);">
          <button onclick="rxPrintDispensedSlip(${rx.id})" style="padding:9px 16px;background:white;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:500;color:var(--gray-700);cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-print"></i> Print Slip
          </button>
          <button onclick="this.closest('[style*=position]').remove()" style="padding:9px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
            Close
          </button>
        </div>
      </div>`;
      document.body.appendChild(modal);
    }
  } catch(e){ alert('Error: '+e.message); }
}

async function rxPrintDispensedSlip(id){
  try{
    const r = await fetch(rxDispAPI+'&id='+id);
    const j = await r.json();
    if(!j.success || !j.data) return alert('Failed to load dispensed prescription');
    const rx = j.data;
    const items = rx.items || [];
    const patientName = ((rx.pat_first||'')+' '+(rx.pat_last||'')).trim() || '—';
    const doctorName = ((rx.doc_first||'')+' '+(rx.doc_last||'')).trim() || (rx.doctor_name||'—');

    const printWin = window.open('', '_blank', 'width=700,height=800');
    if(!printWin) return alert('Please allow popups to print');

    const itemsRows = items.map((it, i) => {
      const qty = parseFloat(it.quantity_dispensed || it.quantity || 0);
      const price = parseFloat(it.selling_price || it.unit_price || 0);
      return `<tr>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;">${i+1}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;font-weight:600;">${dqEsc(it.name||it.instructions||'Drug')}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;">${dqEsc(it.dosage||'—')}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;text-align:center;">${qty}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;text-align:right;">${price.toLocaleString()}</td>
        <td style="padding:6px 8px;border-bottom:1px solid #ddd;text-align:right;font-weight:600;">${(qty * price).toLocaleString()}</td>
      </tr>`;
    }).join('');

    const total = items.reduce((sum, it) => sum + (parseFloat(it.quantity_dispensed||it.quantity||0) * parseFloat(it.selling_price||it.unit_price||0)), 0);

    printWin.document.write(`
      <!DOCTYPE html>
      <html>
      <head>
        <title>Dispensed Receipt - ${dqEsc(rx.prescription_no)}</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 24px; color: #111; font-size: 13px; }
          .header { text-align: center; border-bottom: 2px solid #16a34a; padding-bottom: 12px; margin-bottom: 16px; }
          .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
          table { width: 100%; border-collapse: collapse; margin-top: 12px; }
          th { background: #f3f4f6; text-align: left; padding: 8px; border-bottom: 2px solid #ddd; font-size: 12px; }
          @media print { button { display: none; } }
        </style>
      </head>
      <body>
        <div class="header">
          <h2 style="margin:0;color:#16a34a;">IRAMIRO CLINIC</h2>
          <div style="font-size:12px;color:#666;margin-top:2px;">PHARMACY DISPENSING RECEIPT</div>
        </div>
        <div class="grid">
          <div>
            <strong>Rx #:</strong> <span style="font-family:monospace;font-weight:700;">${dqEsc(rx.prescription_no)}</span><br>
            <strong>Date:</strong> ${rx.prescription_date||''}<br>
            <strong>Prescriber:</strong> Dr. ${dqEsc(doctorName)}
          </div>
          <div>
            <strong>Patient:</strong> ${dqEsc(patientName)}<br>
            <strong>Patient #:</strong> ${dqEsc(rx.patient_no||'—')}<br>
            <strong>Status:</strong> ${rx.status === 'dispensed_full' ? 'Fully Dispensed' : 'Partially Dispensed'}
          </div>
        </div>
        <table>
          <thead>
            <tr>
              <th style="width:30px;">#</th>
              <th>Medication</th>
              <th>Dosage</th>
              <th style="text-align:center;">Qty</th>
              <th style="text-align:right;">Unit Price</th>
              <th style="text-align:right;">Total (RWF)</th>
            </tr>
          </thead>
          <tbody>${itemsRows}</tbody>
          <tfoot>
            <tr>
              <td colspan="5" style="padding:10px 8px;text-align:right;font-weight:700;border-top:2px solid #ddd;">Total:</td>
              <td style="padding:10px 8px;text-align:right;font-weight:800;border-top:2px solid #ddd;color:#16a34a;">${total.toLocaleString()} RWF</td>
            </tr>
          </tfoot>
        </table>
        <div style="margin-top:32px;display:flex;justify-content:space-between;padding-top:20px;border-top:1px dashed #ccc;">
          <div>Pharmacist Signature: __________________</div>
          <div>Received By: __________________</div>
        </div>
        <script>
          window.onload = function(){ window.print(); };
        <\/script>
      </body>
      </html>
    `);
    printWin.document.close();
  } catch(e){ alert('Error printing: '+e.message); }
}

// Initial load for dispensing
document.addEventListener('DOMContentLoaded', () => {
  if('<?php echo $subModule; ?>' === 'dispensing') rxDispensedLoad();
});
if(document.readyState === 'complete' || document.readyState === 'interactive'){
  if('<?php echo $subModule; ?>' === 'dispensing') rxDispensedLoad();
}
</script>

<!-- ============================================
PHARMACY POS
============================================ -->
<?php elseif ($subModule == 'pos'): ?>

<?php if ($action == 'add'): ?>
<!-- POS - SELL DRUGS -->
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;">
  <!-- Left: Cart -->
  <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-cart-plus" style="color:var(--primary-color);"></i> Drug Sale</h3>
      <span style="font-size:12px;color:var(--gray-500);" id="pos-date"><?php echo date('d M Y H:i'); ?></span>
    </div>
    <div style="padding:16px 20px;">
      <!-- Search Drug -->
      <div style="margin-bottom:16px;display:flex;gap:8px;">
        <input type="text" id="pos-drug-search" placeholder="Search drug by name..." oninput="posSearchDrug()" style="flex:1;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
      </div>
      <!-- Search Results -->
      <div id="pos-search-results" style="display:none;background:white;border:1px solid var(--gray-200);border-radius:8px;max-height:200px;overflow-y:auto;margin-bottom:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
      
      <!-- Cart Table -->
      <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="background:var(--gray-50);">
              <th style="padding:10px;text-align:left;font-weight:600;">Drug</th>
              <th style="padding:10px;text-align:center;font-weight:600;width:80px;">Qty</th>
              <th style="padding:10px;text-align:right;font-weight:600;width:100px;">Price</th>
              <th style="padding:10px;text-align:right;font-weight:600;width:100px;">Total</th>
              <th style="padding:10px;width:40px;"></th>
            </tr>
          </thead>
          <tbody id="pos-cart-tbody">
            <tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);font-size:12px;">Search and add drugs to cart</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right: Payment -->
  <div>
    <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);">
        <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-receipt" style="color:#16a34a;"></i> Payment</h3>
      </div>
      <div style="padding:16px 20px;">
        <!-- Customer -->
        <div style="margin-bottom:12px;">
          <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:4px;">Customer Name</label>
          <input type="text" id="pos-customer" placeholder="Walk-in customer (optional)" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>

        <!-- Totals -->
        <div style="background:var(--gray-50);border-radius:8px;padding:14px;margin-bottom:12px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
            <span style="color:var(--gray-600);">Items:</span>
            <span style="font-weight:600;" id="pos-item-count">0</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;">
            <span style="color:var(--gray-600);">Subtotal:</span>
            <span style="font-weight:600;" id="pos-subtotal">0 RWF</span>
          </div>
          <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:2px solid var(--gray-200);margin-top:8px;">
            <span style="font-size:14px;font-weight:700;color:var(--gray-900);">TOTAL:</span>
            <span style="font-size:18px;font-weight:700;color:#16a34a;" id="pos-total">0 RWF</span>
          </div>
        </div>

        <!-- Payment Method -->
        <div style="margin-bottom:12px;">
          <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:4px;">Payment Method</label>
          <select id="pos-payment-method" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <option value="cash">Cash</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="card">Card</option>
            <option value="bank_transfer">Bank Transfer</option>
          </select>
        </div>

        <!-- Paid Amount -->
        <div style="margin-bottom:12px;">
          <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin-bottom:4px;">Amount Paid</label>
          <input type="number" id="pos-paid" placeholder="0" oninput="posCalcChange()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>

        <!-- Change -->
        <div style="background:#d1fae5;border-radius:6px;padding:10px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:12px;font-weight:600;color:#065f46;">Change:</span>
          <span style="font-size:16px;font-weight:700;color:#065f46;" id="pos-change">0 RWF</span>
        </div>

        <!-- Submit -->
        <button onclick="posCompleteSale()" style="width:100%;padding:12px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;">
          <i class="fas fa-check-circle"></i> Complete Sale
        </button>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=pos'" style="width:100%;padding:10px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;font-size:13px;color:var(--gray-600);margin-top:8px;">
          Cancel
        </button>
      </div>
    </div>
  </div>
</div>

<script>
let posCart = [];
let posDrugs = [];

async function posLoadDrugs(){
  try{
    const r = await fetch('api.php?endpoint=pharmacy/items&is_medication=1&limit=500');
    const j = await r.json();
    if(j.success && j.data) posDrugs = j.data;
  } catch(e){}
}

function posSearchDrug(){
  const term = document.getElementById('pos-drug-search').value.toLowerCase().trim();
  const resultsDiv = document.getElementById('pos-search-results');
  if(term.length < 2){ resultsDiv.style.display = 'none'; return; }
  
  const matches = posDrugs.filter(d => d.name.toLowerCase().includes(term) || (d.generic_name && d.generic_name.toLowerCase().includes(term)));
  if(!matches.length){ resultsDiv.innerHTML = '<div style="padding:12px;font-size:12px;color:var(--gray-400);">No drugs found</div>'; resultsDiv.style.display = 'block'; return; }
  
  resultsDiv.innerHTML = matches.slice(0,10).map(d => `
    <div onclick="posAddToCart(${d.id}, '${d.name.replace(/'/g,"\\'")}', ${parseFloat(d.selling_price||0)})" style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--gray-100);display:flex;justify-content:space-between;align-items:center;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
      <div>
        <div style="font-size:13px;font-weight:500;color:var(--gray-900);">${d.name}</div>
        <div style="font-size:11px;color:var(--gray-500);">${d.generic_name||''} | ${d.item_code||''}</div>
      </div>
      <span style="font-size:13px;font-weight:600;color:#16a34a;">${parseFloat(d.selling_price||0).toLocaleString()} RWF</span>
    </div>
  `).join('');
  resultsDiv.style.display = 'block';
}

function posAddToCart(itemId, name, price){
  document.getElementById('pos-search-results').style.display = 'none';
  document.getElementById('pos-drug-search').value = '';
  
  const existing = posCart.find(c => c.item_id === itemId);
  if(existing){ existing.quantity++; } 
  else { posCart.push({item_id: itemId, name: name, unit_price: price, quantity: 1}); }
  posRenderCart();
}

function posRenderCart(){
  const tbody = document.getElementById('pos-cart-tbody');
  if(!posCart.length){
    tbody.innerHTML = '<tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);font-size:12px;">Search and add drugs to cart</td></tr>';
    posUpdateTotals();
    return;
  }
  tbody.innerHTML = posCart.map((item, i) => `
    <tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:8px 10px;font-weight:500;">${item.name}</td>
      <td style="padding:8px;text-align:center;">
        <input type="number" value="${item.quantity}" min="1" onchange="posUpdateQty(${i}, this.value)" style="width:55px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;text-align:center;font-size:12px;">
      </td>
      <td style="padding:8px 10px;text-align:right;font-size:12px;">${item.unit_price.toLocaleString()}</td>
      <td style="padding:8px 10px;text-align:right;font-weight:600;">${(item.quantity * item.unit_price).toLocaleString()}</td>
      <td style="padding:8px;text-align:center;">
        <button onclick="posRemoveItem(${i})" style="border:none;background:none;color:var(--danger-color);cursor:pointer;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>
  `).join('');
  posUpdateTotals();
}

function posUpdateQty(index, val){ posCart[index].quantity = Math.max(1, parseInt(val)||1); posRenderCart(); }
function posRemoveItem(index){ posCart.splice(index, 1); posRenderCart(); }

function posUpdateTotals(){
  const total = posCart.reduce((s, i) => s + (i.quantity * i.unit_price), 0);
  document.getElementById('pos-item-count').textContent = posCart.length;
  document.getElementById('pos-subtotal').textContent = total.toLocaleString() + ' RWF';
  document.getElementById('pos-total').textContent = total.toLocaleString() + ' RWF';
  posCalcChange();
}

function posCalcChange(){
  const total = posCart.reduce((s, i) => s + (i.quantity * i.unit_price), 0);
  const paid = parseFloat(document.getElementById('pos-paid').value) || 0;
  const change = Math.max(0, paid - total);
  document.getElementById('pos-change').textContent = change.toLocaleString() + ' RWF';
}

async function posCompleteSale(){
  if(!posCart.length) return alert('Add drugs to cart first.');
  const total = posCart.reduce((s, i) => s + (i.quantity * i.unit_price), 0);
  const paid = parseFloat(document.getElementById('pos-paid').value) || 0;
  if(paid < total) return alert('Paid amount is less than total.');
  
  if(!confirm('Complete this sale for ' + total.toLocaleString() + ' RWF?')) return;
  
  try{
    const r = await fetch('api.php?endpoint=pharmacy/pos', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        patient_id: 0,
        payment_method: document.getElementById('pos-payment-method').value,
        paid_amount: paid,
        notes: document.getElementById('pos-customer').value || 'Walk-in',
        items: posCart.map(c => ({item_id: c.item_id, quantity: c.quantity, unit_price: c.unit_price}))
      })
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Sale completed! Receipt: ' + (j.data?.receipt_no||''));
      posCart = [];
      posRenderCart();
      document.getElementById('pos-paid').value = '';
      document.getElementById('pos-customer').value = '';
    } else {
      alert('Error: ' + j.message);
    }
  } catch(e){ alert('Failed: ' + e.message); }
}

posLoadDrugs();
</script>
<?php else: ?>
<!-- POS LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Pharmacy POS Transactions</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No POS transactions found. Create a transaction to get started.</p>
        </div>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=pos&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Sale
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-cash-register"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No POS Transactions</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Start processing pharmacy sales transactions.</p>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=pos&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Sale
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
PHARMACY STOCK
============================================ -->
<?php elseif ($subModule == 'stock'): ?>

<?php if ($action == 'add'): ?>
<!-- ADD PHARMACY STOCK FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-box" style="color: var(--primary-color);"></i> Add Pharmacy Stock
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Drug Name <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter drug name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Generic Name
                </label>
                <input type="text" placeholder="Enter generic name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Category <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Category</option>
                    <option value="antibiotic">Antibiotic</option>
                    <option value="analgesic">Analgesic</option>
                    <option value="antihypertensive">Antihypertensive</option>
                    <option value="antidiabetic">Antidiabetic</option>
                    <option value="antiviral">Antiviral</option>
                    <option value="antifungal">Antifungal</option>
                    <option value="vitamin">Vitamin</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Strength <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 500mg" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Quantity <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter quantity" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Reorder Level <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter reorder level" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Batch Number
                </label>
                <input type="text" placeholder="Enter batch number" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Expiry Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Unit Cost <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter unit cost" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Selling Price <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter selling price" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Storage Instructions
                </label>
                <textarea rows="2" placeholder="Storage instructions" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Add Stock
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- PHARMACY STOCK LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Total Items</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">In Stock</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Low Stock</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--danger-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Expired</div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Pharmacy Stock</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No stock items found. Add stock to get started.</p>
        </div>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=stock&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Add Stock
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-box"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Pharmacy Stock</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Add medications to your pharmacy inventory.</p>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=stock&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Add First Stock
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
DRUG INTERACTION
============================================ -->
<?php elseif ($subModule == 'interaction'): ?>

<?php if ($action == 'add'): ?>
<!-- DRUG INTERACTION CHECK FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-exclamation-triangle" style="color: var(--warning-color);"></i> Drug Interaction Check
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Patient <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Patient</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Drug 1 <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter drug name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Drug 2 <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter drug name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Check Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Interaction Result <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Result</option>
                    <option value="no-interaction">No Interaction</option>
                    <option value="minor">Minor Interaction</option>
                    <option value="moderate">Moderate Interaction</option>
                    <option value="major">Major Interaction - Contraindicated</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Clinical Notes / Recommendations
                </label>
                <textarea rows="3" placeholder="Enter clinical notes and recommendations" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-check"></i> Check Interaction
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- DRUG INTERACTION LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Drug Interactions</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No drug interaction checks found. Perform a check to get started.</p>
        </div>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=interaction&action=add'" style="padding: 10px 20px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Check
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Drug Interactions Checked</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Check for drug interactions to ensure patient safety.</p>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=interaction&action=add'" style="padding: 12px 24px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Check
        </button>
    </div>

    <!-- Interaction Alert -->
    <div style="margin-top: 24px; padding: 16px; background: #fee2e2; border-radius: 8px; border-left: 4px solid var(--danger-color);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-exclamation-circle" style="color: var(--danger-color); font-size: 20px;"></i>
            <div>
                <div style="font-weight: 600; color: var(--gray-900);">No Interactions Detected</div>
                <div style="font-size: 13px; color: var(--gray-600);">All checked interactions are safe with no contraindications.</div>
            </div>
            <button style="margin-left: auto; padding: 6px 16px; background: var(--success-color); color: white; border: none; border-radius: 6px; cursor: pointer;">
                Safe
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
CONTROLLED DRUGS
============================================ -->
<?php elseif ($subModule == 'controlled-drugs'): ?>

<?php if ($action == 'add'): ?>
<!-- CONTROLLED DRUG REGISTRY FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-shield-alt" style="color: var(--danger-color);"></i> Controlled Drug Registry
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Drug Name <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter drug name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Schedule <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Schedule</option>
                    <option value="schedule1">Schedule I</option>
                    <option value="schedule2">Schedule II</option>
                    <option value="schedule3">Schedule III</option>
                    <option value="schedule4">Schedule IV</option>
                    <option value="schedule5">Schedule V</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Batch Number <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter batch number" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Quantity Received <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter quantity" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Quantity Dispensed <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter quantity dispensed" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Current Balance <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter current balance" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Received From
                </label>
                <input type="text" placeholder="Enter supplier name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Received Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Dispensed To
                </label>
                <input type="text" placeholder="Enter patient/ward name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Dispensed By <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Pharmacist</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Prescription Reference
                </label>
                <input type="text" placeholder="Enter prescription ID" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Notes
                </label>
                <textarea rows="2" placeholder="Additional notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--danger-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Register Drug
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- CONTROLLED DRUGS LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Controlled Drug Registry</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No controlled drugs registered. Add a controlled drug to get started.</p>
        </div>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=controlled-drugs&action=add'" style="padding: 10px 20px; background: var(--danger-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Register Drug
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Controlled Drugs Registered</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Register controlled substances to maintain compliance.</p>
        <button onclick="window.location.href='pharmacy.php?module=pharmacy&sub=controlled-drugs&action=add'" style="padding: 12px 24px; background: var(--danger-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Register First Drug
        </button>
    </div>

    <!-- Audit Trail Note -->
    <div style="margin-top: 24px; padding: 16px; background: #dbeafe; border-radius: 8px; border-left: 4px solid var(--primary-color);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-history" style="color: var(--primary-color); font-size: 20px;"></i>
            <div>
                <div style="font-weight: 600; color: var(--gray-900);">Audit Trail</div>
                <div style="font-size: 13px; color: var(--gray-600);">All controlled drug transactions are automatically logged for compliance.</div>
            </div>
            <button style="margin-left: auto; padding: 6px 16px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer;">
                View Logs
            </button>
        </div>
    </div>

</div>
<?php endif; ?>

<?php elseif ($subModule == 'stock-request'): ?>

<!-- ========================================== -->
<!-- Stock Requests: Pharmacy requests items from central store -->
<!-- ========================================== -->
<div class="module-container" style="padding: 24px;">
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #fef3c7; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-hourglass-half" style="color: #f59e0b; font-size: 18px;"></i>
                </div>
                <div><div style="font-size: 13px; color: var(--gray-500);">Pending</div><div style="font-size: 20px; font-weight: 700;" id="srq-pending-count">0</div></div>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #dbeafe; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-check-circle" style="color: #3b82f6; font-size: 18px;"></i>
                </div>
                <div><div style="font-size: 13px; color: var(--gray-500);">Approved</div><div style="font-size: 20px; font-weight: 700;" id="srq-approved-count">0</div></div>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #d1fae5; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-check-double" style="color: #10b981; font-size: 18px;"></i>
                </div>
                <div><div style="font-size: 13px; color: var(--gray-500);">Fulfilled</div><div style="font-size: 20px; font-weight: 700;" id="srq-fulfilled-count">0</div></div>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #fee2e2; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-times-circle" style="color: #ef4444; font-size: 18px;"></i>
                </div>
                <div><div style="font-size: 13px; color: var(--gray-500);">Cancelled</div><div style="font-size: 20px; font-weight: 700;" id="srq-cancelled-count">0</div></div>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap;">
        <div style="flex:1;min-width:200px;">
            <input id="srq-search" placeholder="Search by request number..." style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div style="width:160px;">
            <select id="srq-status-filter" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="fulfilled">Fulfilled</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <button onclick="srqOpenModal()" style="padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-plus"></i> New Request</button>
    </div>

    <div style="background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead><tr style="background:var(--gray-50);color:var(--gray-500);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">
                <th style="padding:12px 16px;text-align:left;">Request #</th>
                <th style="padding:12px 16px;text-align:left;">Date</th>
                <th style="padding:12px 16px;text-align:left;">Priority</th>
                <th style="padding:12px 16px;text-align:left;">Items</th>
                <th style="padding:12px 16px;text-align:left;">Requested By</th>
                <th style="padding:12px 16px;text-align:center;">Status</th>
                <th style="padding:12px 16px;text-align:center;">Actions</th>
            </tr></thead>
            <tbody id="srq-tbody"><tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<?php elseif ($subModule == 'stock-receive'): ?>

<!-- ========================================== -->
<!-- Receive Stock: Accept goods from central store / transfers -->
<!-- ========================================== -->
<div class="module-container" style="padding: 24px;">
    <div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
        <button onclick="rcvShowTab('pending')" id="rcv-tab-pending" style="padding:10px 20px;border-radius:8px;font-weight:500;border:1px solid var(--gray-200);background:var(--primary-color);color:white;cursor:pointer;">Pending Transfers</button>
        <button onclick="rcvShowTab('receipts')" id="rcv-tab-receipts" style="padding:10px 20px;border-radius:8px;font-weight:500;border:1px solid var(--gray-200);background:white;color:var(--gray-700);cursor:pointer;">Receipt History</button>
        <button onclick="rcvOpenReceiveModal()" style="margin-left:auto;padding:10px 20px;background:#10b981;color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-truck-loading"></i> Receive Stock</button>
    </div>

    <div id="rcv-pending-section">
        <div style="background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead><tr style="background:var(--gray-50);color:var(--gray-500);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">
                    <th style="padding:12px 16px;text-align:left;">Transfer #</th>
                    <th style="padding:12px 16px;text-align:left;">From Store</th>
                    <th style="padding:12px 16px;text-align:left;">Date</th>
                    <th style="padding:12px 16px;text-align:left;">Items</th>
                    <th style="padding:12px 16px;text-align:center;">Status</th>
                    <th style="padding:12px 16px;text-align:center;">Action</th>
                </tr></thead>
                <tbody id="rcv-pending-tbody"><tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div id="rcv-receipts-section" style="display:none;">
        <div style="background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead><tr style="background:var(--gray-50);color:var(--gray-500);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">
                    <th style="padding:12px 16px;text-align:left;">Receipt #</th>
                    <th style="padding:12px 16px;text-align:left;">Date</th>
                    <th style="padding:12px 16px;text-align:left;">Source</th>
                    <th style="padding:12px 16px;text-align:left;">Received By</th>
                    <th style="padding:12px 16px;text-align:left;">Notes</th>
                    <th style="padding:12px 16px;text-align:center;">Action</th>
                </tr></thead>
                <tbody id="rcv-receipts-tbody"><tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($subModule == 'stock-movements'): ?>

<!-- ========================================== -->
<!-- Stock Movements: Track all pharmacy stock in/out -->
<!-- ========================================== -->
<div class="module-container" style="padding: 24px;">
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #d1fae5; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-arrow-down" style="color: #10b981; font-size: 18px;"></i>
                </div>
                <div><div style="font-size: 13px; color: var(--gray-500);">Total In</div><div style="font-size: 20px; font-weight: 700;" id="mov-total-in">0</div></div>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #fee2e2; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-arrow-up" style="color: #ef4444; font-size: 18px;"></i>
                </div>
                <div><div style="font-size: 13px; color: var(--gray-500);">Total Out</div><div style="font-size: 20px; font-weight: 700;" id="mov-total-out">0</div></div>
            </div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #dbeafe; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-exchange-alt" style="color: #3b82f6; font-size: 18px;"></i>
                </div>
                <div><div style="font-size: 13px; color: var(--gray-500);">Total Movements</div><div style="font-size: 20px; font-weight: 700;" id="mov-total-count">0</div></div>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap;">
        <div style="width:160px;">
            <select id="mov-type-filter" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;" onchange="movLoad()">
                <option value="">All Movements</option>
                <option value="in">Stock In</option>
                <option value="out">Stock Out</option>
            </select>
        </div>
        <div style="flex:1;text-align:right;color:var(--gray-400);font-size:13px;padding:10px;">Real-time stock movement tracking</div>
    </div>

    <div style="background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead><tr style="background:var(--gray-50);color:var(--gray-500);font-size:12px;text-transform:uppercase;letter-spacing:0.5px;">
                <th style="padding:12px 16px;text-align:left;">Date/Time</th>
                <th style="padding:12px 16px;text-align:left;">Item</th>
                <th style="padding:12px 16px;text-align:left;">Type</th>
                <th style="padding:12px 16px;text-align:right;">Qty</th>
                <th style="padding:12px 16px;text-align:right;">Unit Cost</th>
                <th style="padding:12px 16px;text-align:left;">Reference</th>
                <th style="padding:12px 16px;text-align:left;">User</th>
            </tr></thead>
            <tbody id="mov-tbody"><tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);">Loading...</td></tr></tbody>
        </table>
    </div>
</div>

<?php endif; ?>


<script>
// ==========================================
// PHARMACY STOCK REQUESTS (SRQ)
// ==========================================
async function srqLoad() {
    const status = document.getElementById('srq-status-filter')?.value || '';
    try {
        const r = await fetch(`api.php?endpoint=pharmacy/stock-request&status=${status}`);
        const j = await r.json();
        if (!j.success) return;
        
        const rows = j.data?.requests || [];
        const tbody = document.getElementById('srq-tbody');
        if (!tbody) return;
        
        // Update stats
        const stats = {pending:0, approved:0, fulfilled:0, cancelled:0};
        rows.forEach(r => { if (stats[r.status] !== undefined) stats[r.status]++; });
        Object.keys(stats).forEach(k => {
            const el = document.getElementById(`srq-${k}-count`);
            if (el) el.textContent = stats[k];
        });
        
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);">No stock requests found</td></tr>';
            return;
        }
        
        tbody.innerHTML = rows.map(r => {
            const statusColors = {pending:'#f59e0b', approved:'#3b82f6', fulfilled:'#10b981', cancelled:'#ef4444'};
            const sc = statusColors[r.status] || '#6b7280';
            const priorityBadge = r.priority === 'urgent' ? '🔴 Urgent' : r.priority === 'high' ? '🟠 High' : '⚪ Routine';
            return `<tr>
                <td style="padding:12px 16px;font-weight:500;">${r.request_number}</td>
                <td style="padding:12px 16px;">${r.request_date}</td>
                <td style="padding:12px 16px;">${priorityBadge}</td>
                <td style="padding:12px 16px;">${r.item_count} items</td>
                <td style="padding:12px 16px;">${r.first_name || ''} ${r.last_name || ''}</td>
                <td style="padding:12px 16px;text-align:center;">
                    <span style="padding:4px 12px;border-radius:12px;font-size:12px;font-weight:500;background:${sc}20;color:${sc};">${r.status}</span>
                </td>
                <td style="padding:12px 16px;text-align:center;">
                    <button onclick="srqApprove(${r.id})" style="padding:4px 10px;border-radius:6px;border:1px solid #3b82f6;background:#dbeafe;color:#3b82f6;font-size:12px;cursor:pointer;margin:2px;" title="Approve">✓</button>
                    <button onclick="srqCancel(${r.id})" style="padding:4px 10px;border-radius:6px;border:1px solid #ef4444;background:#fee2e2;color:#ef4444;font-size:12px;cursor:pointer;margin:2px;" title="Cancel">✕</button>
                </td>
            </tr>`;
        }).join('');
    } catch(e) { console.error('srqLoad error:', e); }
}

async function srqApprove(id) {
    if (!confirm('Approve this stock request?')) return;
    try {
        const r = await fetch('api.php?endpoint=pharmacy/stock-request/'+id, {
            method: 'PUT', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({status:'approved', user_id: <?php echo $_SESSION['user_id'] ?? 0; ?>})
        });
        const j = await r.json();
        if (j.success) { showToast('Request approved'); srqLoad(); }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Error approving request', 'error'); }
}

async function srqCancel(id) {
    if (!confirm('Cancel this stock request?')) return;
    try {
        const r = await fetch('api.php?endpoint=pharmacy/stock-request/'+id, {
            method: 'PUT', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({status:'cancelled', user_id: <?php echo $_SESSION['user_id'] ?? 0; ?>})
        });
        const j = await r.json();
        if (j.success) { showToast('Request cancelled'); srqLoad(); }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Error cancelling request', 'error'); }
}

function srqOpenModal() {
    if (!confirm('Create new stock request? Use the central store "Transfer Stock" feature to create requests.')) {
        window.location.href = 'inventory.php?module=inventory&sub=transfers&action=new';
    }
}

// ==========================================
// STOCK RECEIVE (RCV)
// ==========================================
function rcvShowTab(tab) {
    ['pending','receipts'].forEach(t => {
        const sec = document.getElementById(`rcv-${t}-section`);
        const btn = document.getElementById(`rcv-tab-${t}`);
        if (sec) sec.style.display = t === tab ? 'block' : 'none';
        if (btn) {
            btn.style.background = t === tab ? 'var(--primary-color)' : 'white';
            btn.style.color = t === tab ? 'white' : 'var(--gray-700)';
        }
    });
}

async function rcvLoad() {
    try {
        const r = await fetch('api.php?endpoint=pharmacy/stock-receive');
        const j = await r.json();
        if (!j.success) return;
        
        const data = j.data || {};
        
        // Pending transfers
        const ptBody = document.getElementById('rcv-pending-tbody');
        const transfers = data.pending_transfers || [];
        if (ptBody) {
            if (transfers.length === 0) {
                ptBody.innerHTML = '<tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);">No pending transfers</td></tr>';
            } else {
                ptBody.innerHTML = transfers.map(t => {
                    const fromName = t.from_store_name || 'Central Store';
                    return `<tr>
                        <td style="padding:12px 16px;font-weight:500;">${t.transfer_number}</td>
                        <td style="padding:12px 16px;">${fromName}</td>
                        <td style="padding:12px 16px;">${t.created_at ? t.created_at.split(' ')[0] : '-'}</td>
                        <td style="padding:12px 16px;">${t.item_count} items</td>
                        <td style="padding:12px 16px;text-align:center;"><span style="padding:4px 10px;border-radius:12px;font-size:12px;background:#fef3c7;color:#f59e0b;font-weight:500;">${t.status}</span></td>
                        <td style="padding:12px 16px;text-align:center;">
                            <button onclick="rcvReceiveTransfer(${t.id})" style="padding:6px 14px;border-radius:6px;border:none;background:#10b981;color:white;font-size:12px;cursor:pointer;">Receive</button>
                        </td>
                    </tr>`;
                }).join('');
            }
        }
        
        // Receipts history
        const rcBody = document.getElementById('rcv-receipts-tbody');
        const receipts = data.receipts || [];
        if (rcBody) {
            if (receipts.length === 0) {
                rcBody.innerHTML = '<tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);">No receipts yet</td></tr>';
            } else {
                rcBody.innerHTML = receipts.map(r => `<tr>
                    <td style="padding:12px 16px;font-weight:500;">${r.receipt_number}</td>
                    <td style="padding:12px 16px;">${r.receipt_date || r.created_at?.split(' ')[0]}</td>
                    <td style="padding:12px 16px;">${r.source || '-'}</td>
                    <td style="padding:12px 16px;">${r.first_name || ''} ${r.last_name || ''}</td>
                    <td style="padding:12px 16px;">${r.notes || '-'}</td>
                    <td style="padding:12px 16px;text-align:center;">
                        <button onclick="showToast('Receipt #${r.receipt_number}')" style="padding:4px 10px;border-radius:6px;border:1px solid var(--gray-200);background:white;cursor:pointer;">View</button>
                    </td>
                </tr>`).join('');
            }
        }
    } catch(e) { console.error('rcvLoad error:', e); }
}

function rcvOpenReceiveModal() {
    if (confirm('Receive stock manually? This will open the central stock receive form.')) {
        window.location.href = 'inventory.php?module=inventory&sub=stock&action=receive';
    }
}

function rcvReceiveTransfer(id) {
    if (confirm('Receive this transfer now?')) {
        window.location.href = 'inventory.php?module=inventory&sub=stock&action=receive&transfer_id=' + id;
    }
}

// ==========================================
// STOCK MOVEMENTS (MOV)
// ==========================================
async function movLoad() {
    const type = document.getElementById('mov-type-filter')?.value || '';
    try {
        const r = await fetch(`api.php?endpoint=pharmacy/stock-movements&type=${type}`);
        const j = await r.json();
        if (!j.success) return;
        
        const data = j.data || {};
        
        // Update summary
        const summary = data.summary || {};
        ['total-in','total-out','total-count'].forEach(k => {
            const el = document.getElementById(`mov-${k}`);
            if (el) el.textContent = summary[k.replace('-','_')] ?? 0;
        });
        document.getElementById('mov-total-count').textContent = summary.total_movements ?? data.total ?? 0;
        
        const rows = data.movements || [];
        const tbody = document.getElementById('mov-tbody');
        if (!tbody) return;
        
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);">No movements found</td></tr>';
            return;
        }
        
        tbody.innerHTML = rows.map(m => {
            const isIn = m.to_store_id == 2;
            const badgeColor = isIn ? '#10b981' : '#ef4444';
            const badgeBg = isIn ? '#d1fae5' : '#fee2e2';
            const label = isIn ? 'IN' : 'OUT';
            const icon = isIn ? '⬇' : '⬆';
            return `<tr>
                <td style="padding:12px 16px;">${m.created_at || '-'}</td>
                <td style="padding:12px 16px;font-weight:500;">${m.item_name || '-'}<br><span style="font-size:12px;color:var(--gray-400);">${m.item_code || ''}</span></td>
                <td style="padding:12px 16px;">
                    <span style="padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;background:${badgeBg};color:${badgeColor};">${icon} ${label}</span>
                </td>
                <td style="padding:12px 16px;text-align:right;font-weight:600;">${m.quantity}</td>
                <td style="padding:12px 16px;text-align:right;">${m.unit_cost ? m.unit_cost.toFixed(2) : '-'}</td>
                <td style="padding:12px 16px;">${m.reference_type || m.movement_type || '-'}</td>
                <td style="padding:12px 16px;">${m.user_name || '-'}</td>
            </tr>`;
        }).join('');
    } catch(e) { console.error('movLoad error:', e); }
}

// Auto-load when page loads
document.addEventListener('DOMContentLoaded', function() {
    const sub = '<?php echo $subModule; ?>';
    if (sub === 'stock-request') srqLoad();
    else if (sub === 'stock-receive') { rcvLoad(); }
    else if (sub === 'stock-movements') movLoad();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';

?>