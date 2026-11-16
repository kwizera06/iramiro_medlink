<?php
// public/hr.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'employees';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Permissions are part of the Leave & Permissions page (same workflow); keep old links working.
$hrInitialTab = 'requests';
if ($subModule === 'permissions') { $subModule = 'leave'; $hrInitialTab = 'permissions'; }

// Page title mapping
$subModuleTitles = [
    'employees'       => 'Employee Management',
    'payroll'         => 'Payroll Management',
    'attendance'      => 'Attendance Management',
    'permissions'     => 'Permission Requests',
    'leave'           => 'Leave Management',
    'performance'     => 'Performance Management',
    'recruitment'     => 'Recruitment & Vacancies',
    'license-tracking'=> 'License & Certification Tracking',
    'contracts'       => 'Employee Contracts & Documents',
    'training'        => 'Training & Professional Development',
    'shifts'          => 'Shift & Duty Roster',
    'disciplinary'    => 'Disciplinary Management',
    'communications'  => 'Communication Management',
    'goals-tasks'     => 'Goals & Tasks',
    'interns'         => 'Intern Management',
    'casual-workers'  => 'Casual Workers',
    'intern-casual-payroll' => 'Intern & Casual Payroll'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'HR Management';
?>
<script>
const _apiBase = window.location.origin + '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/api.php';
const _hrInitialTab = '<?php echo $hrInitialTab; ?>';
</script>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'employees' => 'Manage all hospital employees and their information',
                'payroll' => 'Process employee salaries, taxes, and benefits',
                'attendance' => 'Track employee attendance and working hours',
                'leave' => 'Manage employee leave requests and balances',
                'performance' => 'Evaluate employee performance and KPI tracking',
                'recruitment' => 'Manage job vacancies and recruitment process',
                'license-tracking' => 'Track employee licenses, certifications, and renewals'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Human Resources Management';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($action == 'list'): ?>
        <button onclick="window.location.href='hr.php?module=hr&sub=<?php echo $subModule; ?>&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Add New
        </button>
        <?php endif; ?>
        <?php if ($action != 'list'): ?>
        <button onclick="window.location.href='hr.php?module=hr&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
EMPLOYEES MODULE
============================================ -->
<?php if ($subModule == 'employees'): ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:24px;">
  <div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;">
    <div id="emp-stat-total" style="font-size:24px;font-weight:700;color:var(--primary-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Total</div>
  </div>
  <div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;">
    <div id="emp-stat-active" style="font-size:24px;font-weight:700;color:var(--success-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Active</div>
  </div>
  <div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;">
    <div id="emp-stat-leave" style="font-size:24px;font-weight:700;color:var(--warning-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">On Leave</div>
  </div>
  <div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;">
    <div id="emp-stat-term" style="font-size:24px;font-weight:700;color:var(--danger-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Terminated</div>
  </div>
</div>

<!-- Toolbar -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="emp-search" type="text" placeholder="Search name, ID, email…"
    style="flex:1;min-width:180px;padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <select id="emp-filter-dept" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:160px;">
    <option value="">All Departments</option>
  </select>
  <select id="emp-filter-status" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Status</option>
    <option value="active">Active</option>
    <option value="on-leave">On Leave</option>
    <option value="suspended">Suspended</option>
    <option value="terminated">Terminated</option>
  </select>
  <button onclick="empOpenModal()" style="padding:9px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;white-space:nowrap;">
    <i class="fas fa-user-plus"></i> Add Employee
  </button>
</div>

<!-- Table -->
<div style="background:white;border-radius:var(--radius);padding:0 0 8px;box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
      <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Employee</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">ID</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Department</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Position</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Gross Salary</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
      </tr>
    </thead>
    <tbody id="emp-tbody">
      <tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
    </tbody>
  </table>
  <div id="emp-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:12px 16px;"></div>
</div>

<!-- Add/Edit Employee Modal -->
<div id="emp-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:760px;margin:auto;position:relative;">
    <button onclick="empCloseModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="emp-modal-title" style="font-size:18px;font-weight:600;margin-bottom:20px;">Add Employee</h3>
    <div id="emp-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form id="emp-form" onsubmit="empSubmit(event)" enctype="multipart/form-data">
      <input type="hidden" id="emp-id">
      <input type="hidden" name="address" value="">
      <input type="hidden" name="notes" value="">

      <!-- Personal -->
      <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Personal Info</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">First Name <span style="color:var(--danger-color)">*</span></label>
          <input id="emp-first_name" name="first_name" type="text" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Last Name <span style="color:var(--danger-color)">*</span></label>
          <input id="emp-last_name" name="last_name" type="text" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Email <span style="color:var(--danger-color)">*</span></label>
          <input id="emp-email" name="email" type="email" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Phone <span style="color:var(--danger-color)">*</span></label>
          <input id="emp-phone" name="phone" type="text" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Gender <span style="color:var(--danger-color)">*</span></label>
          <select id="emp-gender" name="gender" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="">Select</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
          </select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Date of Birth</label>
          <input id="emp-dob" name="date_of_birth" type="date" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">National ID</label>
          <input id="emp-nid" name="national_id" type="text" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Nationality</label>
          <input id="emp-nationality" name="nationality" type="text" value="Rwandan" maxlength="60" placeholder="e.g. Rwandan, Kenyan" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      </div>

      <!-- Employment -->
      <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Employment</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Department</label>
          <select id="emp-dept" name="department_id" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="">Select Department</option>
          </select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Position <span style="color:var(--danger-color)">*</span></label>
          <input id="emp-position" name="position" type="text" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Employment Type</label>
          <select id="emp-type" name="employment_type" onchange="empTypeFields()" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="full-time">Full Time</option><option value="part-time">Part Time</option>
            <option value="contract">Contract</option><option value="intern">Intern</option>
            <option value="casual-worker">Casual Worker</option>
          </select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Date Joined <span style="color:var(--danger-color)">*</span></label>
          <input id="emp-joined" name="date_joined" type="date" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div id="emp-salary-wrap"><label id="emp-salary-label" style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Gross Salary (RWF) <span style="color:var(--danger-color)">*</span></label>
          <input id="emp-salary" name="gross_salary" type="number" min="0" step="1" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Status</label>
          <select id="emp-status" name="status" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="active">Active</option><option value="on-leave">On Leave</option>
            <option value="suspended">Suspended</option><option value="terminated">Terminated</option>
          </select></div>
      </div>

      <!-- Internship placement (interns only) -->
      <div id="emp-intern-section" style="display:none;">
        <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Internship Placement</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px;">
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Start</label>
            <input id="emp-intern_start_date" name="intern_start_date" type="date" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">End</label>
            <input id="emp-intern_end_date" name="intern_end_date" type="date" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Supervisor</label>
            <select id="emp-intern_supervisor" name="intern_supervisor" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">—</option></select></div>
        </div>
      </div>

      <!-- Casual work terms (casual workers only) -->
      <div id="emp-casual-section" style="display:none;">
        <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Casual Work Terms</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Paid per</label>
            <select id="emp-payment_frequency" name="payment_frequency" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="daily">Day worked</option><option value="hourly">Hour worked</option><option value="weekly">Week worked</option><option value="monthly">Month (fixed)</option>
            </select></div>
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Rate (RWF per day / hour / week / month)</label>
            <input id="emp-casual_hourly_rate" name="casual_hourly_rate" type="number" min="0" step="1" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Max hours per week</label>
            <input id="emp-casual_max_hours_per_week" name="casual_max_hours_per_week" type="number" min="0" step="1" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Engagement ends</label>
            <input id="emp-casual_contract_end_date" name="casual_contract_end_date" type="date" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        </div>
      </div>

      <!-- Bank Details -->
      <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Bank Details</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Bank Name</label>
          <input id="emp-bank_name" name="bank_name" type="text" placeholder="e.g. Bank of Kigali" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Account Number</label>
          <input id="emp-bank_account_number" name="bank_account_number" type="text" placeholder="Account number" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Account Name</label>
          <input id="emp-bank_account_name" name="bank_account_name" type="text" placeholder="Name on account" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      </div>

      <!-- Fixed Deductions -->
      <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Monthly Fixed Deductions (RWF)</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px;">
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Mutuelle</label>
          <input id="emp-mutuelle" name="mutuelle_amount" type="number" min="0" step="1" value="0" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      </div>

      <!-- Documents (new only) -->
      <div id="emp-docs-section">
        <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Upload Documents</div>
        <div id="emp-doc-rows">
          <div class="doc-row" style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;margin-bottom:8px;">
            <select name="document_types[]" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
              <option value="Contract">Contract</option><option value="Certificate">Certificate</option>
              <option value="ID Copy">ID Copy</option><option value="Degree">Degree</option>
              <option value="License">License</option><option value="Other">Other</option>
            </select>
            <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="padding:8px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
            <button type="button" onclick="empAddDocRow()" style="padding:9px 12px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;" title="Add more"><i class="fas fa-plus"></i></button>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="emp-submit-btn" style="flex:1;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
          <i class="fas fa-save"></i> <span id="emp-submit-label">Save Employee</span>
        </button>
        <button type="button" onclick="empCloseModal()" style="padding:12px 24px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const empAPI  = _apiBase + '?endpoint=hr/employees';
const deptAPI = _apiBase + '?endpoint=hr/departments';
let empPage=1, empSearch='', empFilterDept='', empFilterStatus='';
function eEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function eFmt(n){return Number(n||0).toLocaleString();}

async function empLoadDepts(){
  try{
    const r=await fetch(deptAPI);
    const j=await r.json();
    if(!j.success)return;
    const opts=j.data.map(d=>`<option value="${d.id}">${eEsc(d.name)}</option>`).join('');
    document.getElementById('emp-filter-dept').innerHTML='<option value="">All Departments</option>'+opts;
    document.getElementById('emp-dept').innerHTML='<option value="">Select Department</option>'+opts;
  }catch(e){}
}

async function empLoad(){
  const p=new URLSearchParams({page:empPage,limit:20,search:empSearch,department_id:empFilterDept,status:empFilterStatus});
  try{
    const r=await fetch(empAPI+'&'+p); const j=await r.json();
    if(!j.success){empMsg('Failed: '+j.message);return;}
    const {employees,stats,pagination}=j.data;
    document.getElementById('emp-stat-total').textContent  = stats.total||0;
    document.getElementById('emp-stat-active').textContent = stats.active||0;
    document.getElementById('emp-stat-leave').textContent  = stats.on_leave||0;
    document.getElementById('emp-stat-term').textContent   = stats.emp_terminated||0;
    const sc={active:'var(--success-color)','on-leave':'var(--warning-color)',suspended:'#f97316',terminated:'var(--danger-color)'};
    const tb=document.getElementById('emp-tbody');
    if(!employees.length){
      tb.innerHTML='<tr><td colspan="7" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-users" style="font-size:36px;display:block;margin-bottom:8px;"></i>No employees found.</td></tr>';
    }else{
      tb.innerHTML=employees.map(e=>`
        <tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:12px 16px;"><div style="font-weight:600;">${eEsc(e.first_name)} ${eEsc(e.last_name)}</div><div style="font-size:12px;color:var(--gray-500);">${eEsc(e.email)}</div></td>
          <td style="padding:12px 16px;font-family:monospace;font-size:13px;color:var(--primary-color);">${eEsc(e.employee_no)}</td>
          <td style="padding:12px 16px;font-size:13px;">${eEsc(e.department_name||'—')}</td>
          <td style="padding:12px 16px;font-size:13px;">${eEsc(e.position)}</td>
          <td style="padding:12px 16px;font-size:13px;">RWF ${eFmt(e.gross_salary)}</td>
          <td style="padding:12px 16px;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${sc[e.status]||'#ccc'}20;color:${sc[e.status]||'#555'};">${e.status}</span></td>
          <td style="padding:12px 16px;text-align:center;">
            <button onclick="empEdit(${e.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;padding:4px;" title="Edit"><i class="fas fa-edit"></i></button>
            <button onclick="empDel(${e.id},'${eEsc(e.first_name+' '+e.last_name)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;padding:4px;" title="Delete"><i class="fas fa-trash"></i></button>
          </td>
        </tr>`).join('');
    }
    const pg=document.getElementById('emp-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="empPage=${i+1};empLoad()" style="padding:6px 12px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;">${i+1}</button>`).join(''):'';
  }catch(e){empMsg('Error: '+e.message);}
}
function empMsg(m){document.getElementById('emp-tbody').innerHTML=`<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}

function empOpenModal(d=null){
  const isEdit=!!d;
  document.getElementById('emp-id').value            = d?d.id:'';
  document.getElementById('emp-first_name').value    = d?d.first_name:'';
  document.getElementById('emp-last_name').value     = d?d.last_name:'';
  document.getElementById('emp-email').value         = d?d.email:'';
  document.getElementById('emp-phone').value         = d?d.phone:'';
  document.getElementById('emp-gender').value        = d?d.gender:'';
  document.getElementById('emp-dob').value           = d?d.date_of_birth:'';
  document.getElementById('emp-nid').value           = d?d.national_id:'';
  document.getElementById('emp-nationality').value   = d?(d.nationality||'Rwandan'):'Rwandan';
  document.getElementById('emp-dept').value          = d?d.department_id:'';
  document.getElementById('emp-position').value      = d?d.position:'';
  document.getElementById('emp-type').value          = d?d.employment_type:'full-time';
  document.getElementById('emp-joined').value        = d?d.date_joined:'';
  document.getElementById('emp-salary').value        = d?d.gross_salary:'';
  document.getElementById('emp-status').value        = d?d.status:'active';
  document.getElementById('emp-bank_name').value     = d?d.bank_name:'';
  document.getElementById('emp-bank_account_number').value = d?d.bank_account_number:'';
  document.getElementById('emp-bank_account_name').value   = d?d.bank_account_name:'';
  document.getElementById('emp-mutuelle').value      = d?d.mutuelle_amount:0;
  for(const k of EMP_TYPE_FIELDS) document.getElementById('emp-'+k).value = d&&d[k]!=null?d[k]:'';
  if(!d||!d.payment_frequency) document.getElementById('emp-payment_frequency').value='daily';
  empTypeFields();
  document.getElementById('emp-modal-title').textContent  = isEdit?'Edit Employee':'Add Employee';
  document.getElementById('emp-submit-label').textContent = isEdit?'Update Employee':'Save Employee';
  document.getElementById('emp-docs-section').style.display = isEdit?'none':'block';
  document.getElementById('emp-alert').style.display='none';
  document.getElementById('emp-modal').style.display='flex';
}
function empCloseModal(){document.getElementById('emp-modal').style.display='none';}
const EMP_TYPE_FIELDS=['intern_start_date','intern_end_date','intern_supervisor','payment_frequency','casual_hourly_rate','casual_max_hours_per_week','casual_contract_end_date'];
// Interns get a placement and a stipend; casual workers a rate instead of a salary
function empTypeFields(){
  const t=document.getElementById('emp-type').value;
  document.getElementById('emp-intern-section').style.display=t==='intern'?'':'none';
  document.getElementById('emp-casual-section').style.display=t==='casual-worker'?'':'none';
  document.getElementById('emp-salary-wrap').style.display=t==='casual-worker'?'none':'';
  document.getElementById('emp-salary-label').innerHTML=(t==='intern'?'Monthly stipend (RWF)':'Gross Salary (RWF)')+' <span style="color:var(--danger-color)">*</span>';
  if(t==='casual-worker'&&!document.getElementById('emp-salary').value) document.getElementById('emp-salary').value=0;
}

function empAddDocRow(){
  const row=document.createElement('div');
  row.className='doc-row';
  row.style='display:grid;grid-template-columns:1fr 1fr auto;gap:10px;margin-bottom:8px;';
  row.innerHTML=`<select name="document_types[]" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
    <option value="Contract">Contract</option><option value="Certificate">Certificate</option>
    <option value="ID Copy">ID Copy</option><option value="Degree">Degree</option>
    <option value="License">License</option><option value="Other">Other</option>
  </select>
  <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="padding:8px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
  <button type="button" onclick="this.closest('.doc-row').remove()" style="padding:9px 12px;background:#fee2e2;border:1px solid #fecaca;border-radius:8px;cursor:pointer;color:#dc2626;"><i class="fas fa-times"></i></button>`;
  document.getElementById('emp-doc-rows').appendChild(row);
}

async function empEdit(id){
  try{const r=await fetch(empAPI+'&id='+id);const j=await r.json();if(j.success)empOpenModal(j.data);}
  catch(e){alert('Could not load employee');}
}
async function empDel(id,name){
  if(!confirm(`Delete "${name}"? This cannot be undone.`))return;
  try{const r=await fetch(empAPI+'&id='+id,{method:'DELETE'});const j=await r.json();j.success?empLoad():alert(j.message);}
  catch(e){alert('Delete failed');}
}

async function empSubmit(e){
  e.preventDefault();
  const id=document.getElementById('emp-id').value;
  const btn=document.getElementById('emp-submit-btn');
  const al=document.getElementById('emp-alert');
  al.style.display='none'; btn.disabled=true;

  try{
    let r, j;
    if(id){
      // PUT with JSON
      const body={
        first_name:document.getElementById('emp-first_name').value.trim(),
        last_name:document.getElementById('emp-last_name').value.trim(),
        email:document.getElementById('emp-email').value.trim(),
        phone:document.getElementById('emp-phone').value.trim(),
        gender:document.getElementById('emp-gender').value,
        date_of_birth:document.getElementById('emp-dob').value,
        national_id:document.getElementById('emp-nid').value.trim(),
        nationality:document.getElementById('emp-nationality').value.trim()||'Rwandan',
        department_id:document.getElementById('emp-dept').value,
        position:document.getElementById('emp-position').value.trim(),
        employment_type:document.getElementById('emp-type').value,
        date_joined:document.getElementById('emp-joined').value,
        gross_salary:document.getElementById('emp-salary').value,
        status:document.getElementById('emp-status').value,
        bank_name:document.getElementById('emp-bank_name').value.trim(),
        bank_account_number:document.getElementById('emp-bank_account_number').value.trim(),
        bank_account_name:document.getElementById('emp-bank_account_name').value.trim(),
        mutuelle_amount:document.getElementById('emp-mutuelle').value,
        notes:''
      };
      for(const k of EMP_TYPE_FIELDS) body[k]=document.getElementById('emp-'+k).value;
      r=await fetch(empAPI+'&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    }else{
      // POST with FormData for file uploads
      const fd=new FormData(document.getElementById('emp-form'));
      // Debug: log what we're sending
      console.log('FormData entries:');
      for (let pair of fd.entries()) {
        console.log(pair[0] + ': ' + pair[1]);
      }
      r=await fetch(empAPI,{method:'POST',body:fd});
    }
    j=await r.json();
    if(j.success){empCloseModal();empLoad();}
    else{al.textContent=j.message||'Save failed';al.style.display='block';}
  }catch(err){al.textContent='Request failed: '+err.message;al.style.display='block';}
  finally{btn.disabled=false;}
}

let empTimer;
document.getElementById('emp-search').addEventListener('input',function(){clearTimeout(empTimer);empTimer=setTimeout(()=>{empSearch=this.value;empPage=1;empLoad();},350);});
document.getElementById('emp-filter-dept').addEventListener('change',function(){empFilterDept=this.value;empPage=1;empLoad();});
document.getElementById('emp-filter-status').addEventListener('change',function(){empFilterStatus=this.value;empPage=1;empLoad();});

const _initEmpSearch = new URLSearchParams(window.location.search).get('search');
if (_initEmpSearch) {
  const empInp = document.getElementById('emp-search');
  if (empInp) empInp.value = _initEmpSearch;
  empSearch = _initEmpSearch;
}

empLoadDepts();
empLoad();
// Supervisors for interns, then deep links from the Interns / Casual Workers pages (?new=intern, ?edit=ID)
(async function(){
  try{const j=await (await fetch(empAPI+'&limit=100&status=active')).json();
    document.getElementById('emp-intern_supervisor').innerHTML='<option value="">—</option>'+(j.data.employees||[]).map(e=>`<option value="${e.id}">${eEsc(e.first_name+' '+e.last_name)}</option>`).join('');}catch(e){}
  const q=new URLSearchParams(location.search);
  if(q.get('edit')) empEdit(q.get('edit'));
  else if(q.get('new')){empOpenModal();document.getElementById('emp-type').value=q.get('new');empTypeFields();}
})();
</script>

<!-- ============================================
PAYROLL MODULE
============================================ -->
<?php elseif ($subModule == 'payroll'): ?>

<!-- Payroll Toolbar -->
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="pay-period" type="month" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <select id="pay-filter-status" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Status</option>
    <option value="draft">Draft</option>
    <option value="processed">Processed</option>
    <option value="approved">Approved</option>
    <option value="paid">Paid</option>
  </select>
  <button onclick="payLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
    <i class="fas fa-search"></i> Filter
  </button>
  <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
    <button onclick="payOpenApproveAll()" id="pay-approve-all-btn" style="display:none;padding:9px 16px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
      <i class="fas fa-check-double"></i> Approve All
    </button>
    <button onclick="payOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
      <i class="fas fa-cogs"></i> Process Payroll
    </button>
  </div>
</div>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px 16px;background:white;border-radius:8px;box-shadow:var(--shadow);">
    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.4px;">Employees</div>
    <div id="pay-sum-count" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div>
  </div>
  <div style="padding:14px 16px;background:white;border-radius:8px;box-shadow:var(--shadow);">
    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.4px;">Total Gross</div>
    <div id="pay-sum-gross" style="font-size:16px;font-weight:700;color:var(--gray-800);">—</div>
  </div>
  <div style="padding:14px 16px;background:white;border-radius:8px;box-shadow:var(--shadow);">
    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.4px;">Total PAYE (TPR)</div>
    <div id="pay-sum-tpr" style="font-size:16px;font-weight:700;color:var(--danger-color);">—</div>
  </div>
  <div style="padding:14px 16px;background:white;border-radius:8px;box-shadow:var(--shadow);">
    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.4px;">Total Emp RSSB</div>
    <div id="pay-sum-emprssb" style="font-size:16px;font-weight:700;color:#f97316;">—</div>
  </div>
  <div style="padding:14px 16px;background:white;border-radius:8px;box-shadow:var(--shadow);">
    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.4px;">Total Net Pay</div>
    <div id="pay-sum-net" style="font-size:16px;font-weight:700;color:var(--success-color);">—</div>
  </div>
  <div style="padding:14px 16px;background:white;border-radius:8px;box-shadow:var(--shadow);">
    <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.4px;">Total Employer Cost</div>
    <div id="pay-sum-ecost" style="font-size:16px;font-weight:700;color:#6366f1;">—</div>
  </div>
</div>

<!-- PAYE Formula Reference Card -->
<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px 16px;margin-bottom:14px;font-size:13px;color:#1e40af;">
  <strong><i class="fas fa-info-circle"></i> Rwanda PAYE (TPR) Brackets:</strong>
  &nbsp;0–60k = 0% &nbsp;|&nbsp; 60k–100k = 10% &nbsp;|&nbsp; 100k–200k = 20% &nbsp;|&nbsp; Above 200k = 30%
  &nbsp;&nbsp;<strong>RSSB:</strong> Employee 6% &nbsp;|&nbsp; Employer Pension 6% + OHI 2%
  &nbsp;&nbsp;<strong>Mutuelle de Santé:</strong> 0.5% of Gross
</div>

<!-- Payroll Table -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:1400px;">
    <thead>
      <!-- Group headers -->
      <tr style="background:#f1f5f9;border-bottom:1px solid var(--gray-200);">
        <th colspan="2" style="padding:8px 10px;text-align:left;font-weight:600;color:var(--gray-600);border-right:2px solid var(--gray-300);">Employee</th>
        <th colspan="7" style="padding:8px 10px;text-align:center;font-weight:600;color:#16a34a;border-right:2px solid var(--gray-300);">Gross Salary Components</th>
        <th colspan="5" style="padding:8px 10px;text-align:center;font-weight:600;color:#dc2626;border-right:2px solid var(--gray-300);">Employee Deductions</th>
        <th colspan="3" style="padding:8px 10px;text-align:center;font-weight:600;color:#7c3aed;border-right:2px solid var(--gray-300);">Employer Contributions</th>
        <th colspan="2" style="padding:8px 10px;text-align:center;font-weight:600;color:var(--gray-600);">Status / Actions</th>
      </tr>
      <!-- Column headers -->
      <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:9px 10px;text-align:left;font-weight:600;color:var(--gray-700);white-space:nowrap;">Name</th>
        <th style="padding:9px 10px;text-align:left;font-weight:600;color:var(--gray-700);border-right:2px solid var(--gray-300);">Dept</th>
        <!-- Gross components -->
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#15803d;">Basic</th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#15803d;">Housing</th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#15803d;">Transport</th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#15803d;">Comms</th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#15803d;">OT</th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#15803d;">Bonus</th>
        <th style="padding:9px 10px;text-align:right;font-weight:700;color:#15803d;border-right:2px solid var(--gray-300);">GROSS</th>
        <!-- Deductions -->
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#dc2626;">Emp RSSB<br><span style="font-weight:400;font-size:10px;">(6%)</span></th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#dc2626;">PAYE<br><span style="font-weight:400;font-size:10px;">(TPR)</span></th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#dc2626;">Mutuelle<br><span style="font-weight:400;font-size:10px;">(0.5%)</span></th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#dc2626;">Loans</th>
        <th style="padding:9px 10px;text-align:right;font-weight:700;color:#dc2626;border-right:2px solid var(--gray-300);">Total Ded.</th>
        <!-- Employer -->
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#7c3aed;">Pension<br><span style="font-weight:400;font-size:10px;">(6%)</span></th>
        <th style="padding:9px 10px;text-align:right;font-weight:600;color:#7c3aed;">OHI<br><span style="font-weight:400;font-size:10px;">(2%)</span></th>
        <th style="padding:9px 10px;text-align:right;font-weight:700;color:#7c3aed;border-right:2px solid var(--gray-300);">Emp. Cost</th>
        <!-- Net + status -->
        <th style="padding:9px 10px;text-align:right;font-weight:700;color:var(--success-color);">Net Pay</th>
        <th style="padding:9px 10px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
      </tr>
    </thead>
    <tbody id="pay-tbody">
      <tr><td colspan="19" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
    </tbody>
  </table>
  <div id="pay-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:12px 16px;"></div>
</div>

<!-- Process Payroll Modal -->
<div id="pay-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:660px;margin:auto;position:relative;">
    <button onclick="payCloseModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:18px;font-weight:600;margin-bottom:4px;"><i class="fas fa-cogs" style="color:var(--success-color);margin-right:8px;"></i>Process Payroll</h3>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:18px;">Fill in the details, click <strong>Preview</strong> to review calculations, then <strong>Confirm &amp; Save</strong> to record.</p>

    <!-- Step indicators -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:13px;">
      <span id="pay-step1-ind" style="display:flex;align-items:center;gap:5px;font-weight:600;color:var(--primary-color);">
        <span style="width:22px;height:22px;border-radius:50%;background:var(--primary-color);color:white;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">1</span> Input
      </span>
      <span style="flex:1;height:2px;background:var(--gray-200);border-radius:2px;"></span>
      <span id="pay-step2-ind" style="display:flex;align-items:center;gap:5px;font-weight:600;color:var(--gray-400);">
        <span id="pay-step2-num" style="width:22px;height:22px;border-radius:50%;background:var(--gray-300);color:white;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">2</span> Preview &amp; Confirm
      </span>
    </div>

    <div id="pay-alert" style="display:none;margin-bottom:14px;padding:11px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>

    <!-- ── STEP 1: Input form ── -->
    <div id="pay-step1">
      <form id="pay-form" onsubmit="payDoPreview(event)">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Pay Period <span style="color:var(--danger-color)">*</span></label>
            <input id="pay-form-period" type="month" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Employee <span style="font-weight:400;color:var(--gray-400);">(blank = all active)</span></label>
            <select id="pay-form-emp" onchange="payOnEmpChange()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">— All Active Employees —</option>
            </select>
          </div>
        </div>

        <div style="font-size:12px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #dcfce7;">Earnings (leave 0 to use employee defaults)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;">
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Basic Pay</label>
            <input id="pay-form-basic" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Housing Allow.</label>
            <input id="pay-form-housing" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Transport Allow.</label>
            <input id="pay-form-transport" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Comms Allow.</label>
            <input id="pay-form-comms" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Overtime Pay</label>
            <input id="pay-form-ot" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Bonus</label>
            <input id="pay-form-bonus" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        </div>

        <div style="font-size:12px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #fee2e2;">Extra Deductions</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;">
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Loans (RWF)</label>
            <input id="pay-form-loan" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Salary Advance</label>
            <input id="pay-form-advance" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
          <div><label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Other Deductions</label>
            <input id="pay-form-otherded" type="number" min="0" step="1" value="0" oninput="payLiveCalc()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        </div>

        <!-- Inline live calc (single employee only) -->
        <div id="pay-preview-box" style="display:none;background:#f8fafc;border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px;">
          <div style="font-weight:600;color:var(--gray-700);margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:.4px;"><i class="fas fa-calculator" style="color:var(--primary-color);"></i> Live Preview</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <div style="display:flex;justify-content:space-between;padding:4px 8px;background:white;border-radius:4px;font-size:12px;"><span style="color:var(--gray-600);">Gross</span><strong id="pvw-gross" style="color:#15803d;">—</strong></div>
            <div style="display:flex;justify-content:space-between;padding:4px 8px;background:white;border-radius:4px;font-size:12px;"><span style="color:var(--gray-600);">Emp RSSB (6%)</span><strong id="pvw-rssb" style="color:#dc2626;">—</strong></div>
            <div style="display:flex;justify-content:space-between;padding:4px 8px;background:white;border-radius:4px;font-size:12px;"><span style="color:var(--gray-600);">PAYE/TPR</span><strong id="pvw-paye" style="color:#dc2626;">—</strong></div>
            <div style="display:flex;justify-content:space-between;padding:4px 8px;background:white;border-radius:4px;font-size:12px;"><span style="color:var(--gray-600);">Mutuelle (0.5%)</span><strong id="pvw-mutuelle" style="color:#dc2626;">—</strong></div>
            <div style="display:flex;justify-content:space-between;padding:4px 8px;background:#dcfce7;border-radius:4px;font-size:12px;"><span style="color:#15803d;font-weight:600;">Net Pay</span><strong id="pvw-net" style="color:#15803d;">—</strong></div>
            <div style="display:flex;justify-content:space-between;padding:4px 8px;background:#ede9fe;border-radius:4px;font-size:12px;"><span style="color:#7c3aed;font-weight:600;">Employer Cost</span><strong id="pvw-ecost" style="color:#7c3aed;">—</strong></div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Notes (optional)</label>
            <input id="pay-form-notes" type="text" placeholder="e.g. August 2026 payroll" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:3px;">Initial Status</label>
            <select id="pay-form-status" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="draft">Draft</option>
              <option value="processed">Processed</option>
            </select>
          </div>
        </div>

        <div style="display:flex;gap:10px;padding-top:14px;border-top:1px solid var(--gray-200);">
          <button type="submit" id="pay-preview-btn" style="flex:1;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
            <i class="fas fa-eye"></i> Preview Calculation
          </button>
          <button type="button" onclick="payCloseModal()" style="padding:11px 22px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
        </div>
      </form>
    </div>

    <!-- ── STEP 2: Preview results + Confirm ── -->
    <div id="pay-step2" style="display:none;">
      <div id="pay-preview-result" style="max-height:360px;overflow-y:auto;margin-bottom:16px;"></div>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:13px;color:#92400e;margin-bottom:16px;">
        <i class="fas fa-exclamation-triangle"></i> <strong>Nothing has been saved yet.</strong> Review the figures above, then click <strong>Confirm &amp; Save</strong> to record this payroll.
      </div>
      <div style="display:flex;gap:10px;">
        <button onclick="payBackToStep1()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">
          <i class="fas fa-arrow-left"></i> Back &amp; Edit
        </button>
        <button onclick="payConfirmSave()" id="pay-confirm-btn" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:700;cursor:pointer;">
          <i class="fas fa-save"></i> Confirm &amp; Save
        </button>
      </div>
    </div>

  </div>
</div>

<!-- Approve Modal -->
<div id="pay-approve-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:460px;position:relative;">
    <button onclick="document.getElementById('pay-approve-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;color:#7c3aed;"><i class="fas fa-check-double" style="margin-right:8px;"></i><span id="pay-approve-title">Approve Payroll</span></h3>
    <div id="pay-approve-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <p id="pay-approve-desc" style="font-size:13px;color:var(--gray-600);margin-bottom:14px;"></p>
    <div style="margin-bottom:14px;">
      <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Approval Notes (optional)</label>
      <textarea id="pay-approve-notes" rows="3" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;resize:vertical;" placeholder="Add any notes…"></textarea>
    </div>
    <div style="display:flex;gap:10px;">
      <button onclick="paySubmitApprove()" id="pay-approve-btn" style="flex:1;padding:11px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-check"></i> Approve</button>
      <button onclick="document.getElementById('pay-approve-modal').style.display='none'" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<!-- Record Detail Modal -->
<div id="pay-detail-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:680px;margin:auto;position:relative;">
    <button onclick="document.getElementById('pay-detail-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <div id="pay-detail-content">Loading…</div>
  </div>
</div>

<script>
const payAPI     = _apiBase + '?endpoint=hr/payroll';
const empListAPI = _apiBase + '?endpoint=hr/employees';
let payPage=1, payPeriodFilter='', payStatusFilter='';
let payApproveRecordId=null, payApprovePeriod='';

function pEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function pFmt(n){return Number(n||0).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0});}
function rwf(n){return 'RWF '+pFmt(n);}

// Set default period to current month
(function(){
  const d=new Date();
  const ym=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');
  document.getElementById('pay-period').value=ym;
  document.getElementById('pay-form-period').value=ym;
})();

async function payLoadEmps(){
  try{
    const r=await fetch(empListAPI+'&limit=500&status=active');
    const j=await r.json();
    if(!j.success)return;
    document.getElementById('pay-form-emp').innerHTML='<option value="">— All Active Employees —</option>'+
      j.data.employees.map(e=>`<option value="${e.id}" data-basic="${e.gross_salary||0}" data-housing="${e.housing_allowance||0}" data-transport="${e.transport_allowance||0}" data-comms="${e.communication_allowance||0}">${pEsc(e.first_name+' '+e.last_name)} (${pEsc(e.employee_no)})</option>`).join('');
  }catch(e){}
}

// Prefill form fields from selected employee defaults
function payOnEmpChange(){
  const sel=document.getElementById('pay-form-emp');
  const opt=sel.options[sel.selectedIndex];
  if(opt&&opt.value){
    const basic=parseFloat(opt.dataset.basic)||0;
    const housing=parseFloat(opt.dataset.housing)||0;
    const transport=parseFloat(opt.dataset.transport)||0;
    const comms=parseFloat(opt.dataset.comms)||0;
    document.getElementById('pay-form-basic').value    = basic    >0?basic:0;
    document.getElementById('pay-form-housing').value  = housing  >0?housing:0;
    document.getElementById('pay-form-transport').value= transport>0?transport:0;
    document.getElementById('pay-form-comms').value    = comms    >0?comms:0;
  } else {
    ['pay-form-basic','pay-form-housing','pay-form-transport','pay-form-comms'].forEach(id=>{
      document.getElementById(id).value=0;
    });
  }
  payLiveCalc();
}

async function payLoad(){
  payPeriodFilter = document.getElementById('pay-period').value;
  payStatusFilter = document.getElementById('pay-filter-status').value;
  const p=new URLSearchParams({page:payPage,limit:50,period:payPeriodFilter,status:payStatusFilter});
  try{
    const r=await fetch(payAPI+'&'+p); const j=await r.json();
    if(!j.success){payMsg('Failed: '+j.message);return;}
    const {records,summary,pagination}=j.data;

    // Summary cards
    document.getElementById('pay-sum-count').textContent   = summary.total_employees||0;
    document.getElementById('pay-sum-gross').textContent   = rwf(summary.total_gross);
    document.getElementById('pay-sum-tpr').textContent     = rwf(summary.total_tpr);
    document.getElementById('pay-sum-emprssb').textContent = rwf(summary.total_emp_rssb);
    document.getElementById('pay-sum-net').textContent     = rwf(summary.total_net);
    document.getElementById('pay-sum-ecost').textContent   = rwf(summary.total_employer_cost);

    // Show "Approve All" button if any draft/processed records
    const hasPending = records.some(r=>r.status==='draft'||r.status==='processed');
    document.getElementById('pay-approve-all-btn').style.display = hasPending&&payPeriodFilter?'':'none';

    // Status badge colors
    const sc={draft:'#f59e0b',processed:'var(--primary-color)',approved:'#7c3aed',paid:'var(--success-color)'};

    const tb=document.getElementById('pay-tbody');
    if(!records.length){
      tb.innerHTML='<tr><td colspan="19" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-file-invoice-dollar" style="font-size:36px;display:block;margin-bottom:8px;"></i>No payroll records for this period. Click "Process Payroll" to generate.</td></tr>';
    } else {
      tb.innerHTML=records.map(rec=>{
        const canEdit   = rec.status!=='approved'&&rec.status!=='paid';
        const canApprove= rec.status==='draft'||rec.status==='processed';
        const canDel    = rec.status==='draft'||rec.status==='processed';
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 10px;white-space:nowrap;">
            <div style="font-weight:600;font-size:12px;">${pEsc(rec.first_name)} ${pEsc(rec.last_name)}</div>
            <div style="font-size:11px;color:var(--primary-color);font-family:monospace;">${pEsc(rec.employee_no)}</div>
          </td>
          <td style="padding:8px 10px;font-size:11px;color:var(--gray-500);border-right:2px solid var(--gray-200);">${pEsc(rec.department_name||'—')}</td>
          <!-- Gross components -->
          <td style="padding:8px 10px;text-align:right;font-size:12px;">${pFmt(rec.basic_pay)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;">${pFmt(rec.housing_allowance)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;">${pFmt(rec.transport_allowance)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;">${pFmt(rec.communication_allowance)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;">${pFmt(rec.overtime_pay)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;">${pFmt(rec.bonus)}</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;font-size:12px;color:#15803d;border-right:2px solid var(--gray-200);">${pFmt(rec.gross_salary)}</td>
          <!-- Deductions -->
          <td style="padding:8px 10px;text-align:right;font-size:12px;color:#dc2626;">${pFmt(rec.employee_rssb)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;color:#dc2626;">${pFmt(rec.tpr_tax)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;">${pFmt(rec.mutuelle_deduction)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;">${pFmt(rec.loan_deduction)}</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;font-size:12px;color:#dc2626;border-right:2px solid var(--gray-200);">${pFmt(rec.total_deductions)}</td>
          <!-- Employer -->
          <td style="padding:8px 10px;text-align:right;font-size:12px;color:#7c3aed;">${pFmt(rec.employer_rssb_pension)}</td>
          <td style="padding:8px 10px;text-align:right;font-size:12px;color:#7c3aed;">${pFmt(rec.employer_ohi)}</td>
          <td style="padding:8px 10px;text-align:right;font-weight:700;font-size:12px;color:#7c3aed;border-right:2px solid var(--gray-200);">${pFmt(rec.employer_cost)}</td>
          <!-- Net + actions -->
          <td style="padding:8px 10px;text-align:right;font-weight:700;font-size:13px;color:var(--success-color);">${pFmt(rec.net_pay)}</td>
          <td style="padding:8px 10px;text-align:center;white-space:nowrap;">
            <span style="display:inline-block;padding:2px 7px;border-radius:10px;font-size:10px;font-weight:700;background:${sc[rec.status]||'#ccc'}22;color:${sc[rec.status]||'#555'};margin-bottom:3px;">${rec.status.toUpperCase()}</span><br>
            <button onclick="payViewDetail(${rec.id})" title="View Details" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:13px;padding:2px 3px;"><i class="fas fa-eye"></i></button>
            ${canApprove?`<button onclick="payOpenApprove(${rec.id},'${pEsc(rec.first_name+' '+rec.last_name)}')" title="Approve" style="background:none;border:none;color:#7c3aed;cursor:pointer;font-size:13px;padding:2px 3px;"><i class="fas fa-check-circle"></i></button>`:''}
            ${canDel?`<button onclick="payDel(${rec.id})" title="Delete" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:13px;padding:2px 3px;"><i class="fas fa-trash"></i></button>`:''}
          </td>
        </tr>`;
      }).join('');
    }

    const pg=document.getElementById('pay-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`
      <button onclick="payPage=${i+1};payLoad()" style="padding:5px 10px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:12px;">${i+1}</button>`).join(''):'';
  }catch(e){payMsg('Error: '+e.message);}
}
function payMsg(m){document.getElementById('pay-tbody').innerHTML=`<tr><td colspan="21" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}

function payOpenModal(){
  // Reset to step 1
  document.getElementById('pay-alert').style.display='none';
  document.getElementById('pay-step1').style.display='block';
  document.getElementById('pay-step2').style.display='none';
  document.getElementById('pay-step1-ind').style.color='var(--primary-color)';
  document.getElementById('pay-step2-ind').style.color='var(--gray-400)';
  document.getElementById('pay-step2-num').style.background='var(--gray-300)';
  document.getElementById('pay-modal').style.display='flex';
}
function payCloseModal(){document.getElementById('pay-modal').style.display='none';}
function payBackToStep1(){
  document.getElementById('pay-step1').style.display='block';
  document.getElementById('pay-step2').style.display='none';
  document.getElementById('pay-step1-ind').style.color='var(--primary-color)';
  document.getElementById('pay-step2-ind').style.color='var(--gray-400)';
  document.getElementById('pay-step2-num').style.background='var(--gray-300)';
}

// Wire employee select
document.getElementById('pay-form-emp').addEventListener('change', payOnEmpChange);

// Client-side PAYE (mirrors server calcPAYE)
function clientPAYE(gross){
  if(gross<=60000) return 0;
  let tax=0;
  tax+=(Math.min(gross,100000)-60000)*0.10;
  if(gross>100000) tax+=(Math.min(gross,200000)-100000)*0.20;
  if(gross>200000) tax+=(gross-200000)*0.30;
  return Math.round(tax);
}

// Live mini-preview in the input form (single employee only)
function payLiveCalc(){
  if(!document.getElementById('pay-form-emp').value){
    document.getElementById('pay-preview-box').style.display='none'; return;
  }
  const v=id=>parseFloat(document.getElementById(id).value)||0;
  const gross=v('pay-form-basic')+v('pay-form-housing')+v('pay-form-transport')+
               v('pay-form-comms')+v('pay-form-ot')+v('pay-form-bonus');
  if(!gross){document.getElementById('pay-preview-box').style.display='none';return;}
  const empRssb  = Math.round(gross*0.06);
  const paye     = clientPAYE(gross);
  const mutuelle = Math.round(gross*0.005);
  const loans    = v('pay-form-loan'), advance=v('pay-form-advance'), otherDed=v('pay-form-otherded');
  const totalDed = empRssb+paye+mutuelle+loans+advance+otherDed;
  const net      = gross-totalDed;
  const empCost  = gross+Math.round(gross*0.06)+Math.round(gross*0.02);
  document.getElementById('pay-preview-box').style.display='block';
  document.getElementById('pvw-gross').textContent    = rwf(gross);
  document.getElementById('pvw-rssb').textContent     = rwf(empRssb);
  document.getElementById('pvw-paye').textContent     = rwf(paye);
  document.getElementById('pvw-mutuelle').textContent = rwf(mutuelle);
  document.getElementById('pvw-net').textContent      = rwf(net);
  document.getElementById('pvw-ecost').textContent    = rwf(empCost);
}

// Stored preview data from server for confirmation
let payPreviewData = null;

// Step 1 → Preview: call server, show results, NO save yet
async function payDoPreview(e){
  e.preventDefault();
  const period=document.getElementById('pay-form-period').value;
  if(!period){alert('Please select a pay period.');return;}
  const al=document.getElementById('pay-alert');
  al.style.display='none';
  const btn=document.getElementById('pay-preview-btn');
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Calculating…';
  const empId=document.getElementById('pay-form-emp').value;
  const v=id=>parseFloat(document.getElementById(id).value)||0;
  const params=new URLSearchParams({
    action:'preview', employee_id:empId||'',
    basic_pay               : v('pay-form-basic'),
    housing_allowance       : v('pay-form-housing'),
    transport_allowance     : v('pay-form-transport'),
    communication_allowance : v('pay-form-comms'),
    overtime_pay            : v('pay-form-ot'),
    bonus                   : v('pay-form-bonus'),
    loan_deduction          : v('pay-form-loan'),
    advance_deduction       : v('pay-form-advance'),
    other_deductions        : v('pay-form-otherded'),
  });
  try{
    let previewRows=[];
    if(empId){
      // Single employee preview from server
      const r=await fetch(payAPI+'&'+params);
      const j=await r.json();
      if(!j.success) throw new Error(j.message);
      // Get employee name
      const opt=document.getElementById('pay-form-emp').selectedOptions[0];
      previewRows=[{name:opt.text, ...j.data}];
    } else {
      // All active employees: fetch list then calc client-side
      const er=await fetch(empListAPI+'&limit=500&status=active');
      const ej=await er.json();
      if(!ej.success) throw new Error(ej.message);
      previewRows=ej.data.employees.map(emp=>{
        const basic=v('pay-form-basic')||parseFloat(emp.gross_salary)||0;
        const housing=v('pay-form-housing')||parseFloat(emp.housing_allowance)||0;
        const transport=v('pay-form-transport')||parseFloat(emp.transport_allowance)||0;
        const comms=v('pay-form-comms')||parseFloat(emp.communication_allowance)||0;
        const ot=v('pay-form-ot'), bonus=v('pay-form-bonus');
        const gross=basic+housing+transport+comms+ot+bonus;
        const empRssb=Math.round(gross*0.06);
        const paye=clientPAYE(gross);
        const mutuelle=Math.round(gross*0.005);
        const loans=v('pay-form-loan'), advance=v('pay-form-advance'), otherDed=v('pay-form-otherded');
        const totalDed=empRssb+paye+mutuelle+loans+advance+otherDed;
        return {
          employee_id:emp.id, name:emp.first_name+' '+emp.last_name, employee_no:emp.employee_no,
          department_name:emp.department_name||'—',
          basic_pay:basic, housing_allowance:housing, transport_allowance:transport,
          communication_allowance:comms, overtime_pay:ot, bonus:bonus, gross_salary:gross,
          employee_rssb:empRssb, tpr_tax:paye, mutuelle_deduction:mutuelle,
          loan_deduction:loans, advance_deduction:advance, other_deductions:otherDed,
          total_deductions:totalDed, net_pay:gross-totalDed,
          employer_rssb_pension:Math.round(gross*0.06),
          employer_ohi:Math.round(gross*0.02),
          employer_cost:gross+Math.round(gross*0.06)+Math.round(gross*0.02)
        };
      }).filter(r=>r.gross_salary>0);
    }
    payPreviewData=previewRows;
    payShowPreviewStep2(previewRows, period);
  }catch(err){al.textContent='Preview failed: '+err.message;al.style.display='block';}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-eye"></i> Preview Calculation';}
}

function payShowPreviewStep2(rows, period){
  // Advance step indicator
  document.getElementById('pay-step1-ind').style.color='var(--gray-400)';
  document.getElementById('pay-step2-ind').style.color='#7c3aed';
  document.getElementById('pay-step2-num').style.background='#7c3aed';
  document.getElementById('pay-step1').style.display='none';
  document.getElementById('pay-step2').style.display='block';
  // Build preview table
  const totGross=rows.reduce((s,r)=>s+r.gross_salary,0);
  const totNet  =rows.reduce((s,r)=>s+r.net_pay,0);
  const totCost =rows.reduce((s,r)=>s+(r.employer_cost||0),0);
  let html=`
    <div style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:10px;">
      ${rows.length} employee${rows.length!==1?'s':''} &bull; Period: <strong>${pEsc(period)}</strong>
      &nbsp;&nbsp;Total Gross: <span style="color:#15803d;">${rwf(totGross)}</span>
      &nbsp;&nbsp;Total Net: <span style="color:var(--success-color);">${rwf(totNet)}</span>
      &nbsp;&nbsp;Employer Cost: <span style="color:#7c3aed;">${rwf(totCost)}</span>
    </div>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:11px;">
      <thead>
        <tr style="background:#f1f5f9;">
          <th style="padding:6px 8px;text-align:left;border-bottom:2px solid var(--gray-200);">Employee</th>
          <th style="padding:6px 8px;text-align:right;color:#15803d;border-bottom:2px solid var(--gray-200);">Gross</th>
          <th style="padding:6px 8px;text-align:right;color:#dc2626;border-bottom:2px solid var(--gray-200);">RSSB 6%</th>
          <th style="padding:6px 8px;text-align:right;color:#dc2626;border-bottom:2px solid var(--gray-200);">PAYE</th>
          <th style="padding:6px 8px;text-align:right;color:#dc2626;border-bottom:2px solid var(--gray-200);">Mutuelle 0.5%</th>
          <th style="padding:6px 8px;text-align:right;color:#dc2626;border-bottom:2px solid var(--gray-200);">Total Ded.</th>
          <th style="padding:6px 8px;text-align:right;color:var(--success-color);border-bottom:2px solid var(--gray-200);">Net Pay</th>
          <th style="padding:6px 8px;text-align:right;color:#7c3aed;border-bottom:2px solid var(--gray-200);">Emp. Cost</th>
        </tr>
      </thead><tbody>`;
  rows.forEach(r=>{
    html+=`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:5px 8px;"><strong>${pEsc(r.name)}</strong><br><span style="color:var(--gray-500);font-size:10px;">${pEsc(r.employee_no||'')}</span></td>
      <td style="padding:5px 8px;text-align:right;font-weight:600;color:#15803d;">${pFmt(r.gross_salary)}</td>
      <td style="padding:5px 8px;text-align:right;color:#dc2626;">${pFmt(r.employee_rssb)}</td>
      <td style="padding:5px 8px;text-align:right;color:#dc2626;">${pFmt(r.tpr_tax)}</td>
      <td style="padding:5px 8px;text-align:right;color:#dc2626;">${pFmt(r.mutuelle_deduction)}</td>
      <td style="padding:5px 8px;text-align:right;font-weight:600;color:#dc2626;">${pFmt(r.total_deductions)}</td>
      <td style="padding:5px 8px;text-align:right;font-weight:700;color:var(--success-color);">${pFmt(r.net_pay)}</td>
      <td style="padding:5px 8px;text-align:right;color:#7c3aed;">${pFmt(r.employer_cost)}</td>
    </tr>`;
  });
  html+='</tbody></table></div>';
  document.getElementById('pay-preview-result').innerHTML=html;
}

// Step 2 → Confirm: NOW we save to DB
async function payConfirmSave(){
  const btn=document.getElementById('pay-confirm-btn');
  const al=document.getElementById('pay-alert');
  al.style.display='none'; btn.disabled=true;
  btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';
  const period=document.getElementById('pay-form-period').value;
  const empId=document.getElementById('pay-form-emp').value;
  const v=id=>parseFloat(document.getElementById(id).value)||0;
  const body={
    pay_period              : period,
    status                  : document.getElementById('pay-form-status').value,
    notes                   : document.getElementById('pay-form-notes').value.trim(),
    basic_pay               : v('pay-form-basic'),
    housing_allowance       : v('pay-form-housing'),
    transport_allowance     : v('pay-form-transport'),
    communication_allowance : v('pay-form-comms'),
    overtime_pay            : v('pay-form-ot'),
    bonus                   : v('pay-form-bonus'),
    loan_deduction          : v('pay-form-loan'),
    advance_deduction       : v('pay-form-advance'),
    other_deductions        : v('pay-form-otherded'),
  };
  if(empId) body.employee_id=empId;
  try{
    const r=await fetch(payAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){
      payCloseModal();
      document.getElementById('pay-period').value=period;
      payLoad();
    }else{al.textContent=j.message||'Failed to save';al.style.display='block';payBackToStep1();}
  }catch(err){al.textContent='Save failed: '+err.message;al.style.display='block';payBackToStep1();}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> Confirm &amp; Save';}
}

// Approve single record
function payOpenApprove(recordId, empName){
  payApproveRecordId = recordId;
  payApprovePeriod   = '';
  document.getElementById('pay-approve-title').textContent = 'Approve: '+empName;
  document.getElementById('pay-approve-desc').textContent  = 'Approving this individual payroll record will lock it from further edits.';
  document.getElementById('pay-approve-notes').value='';
  document.getElementById('pay-approve-alert').style.display='none';
  document.getElementById('pay-approve-modal').style.display='flex';
}

// Approve all for current period
function payOpenApproveAll(){
  payApproveRecordId = null;
  payApprovePeriod   = payPeriodFilter;
  document.getElementById('pay-approve-title').textContent = 'Approve All – '+payPeriodFilter;
  document.getElementById('pay-approve-desc').textContent  = 'This will approve ALL draft/processed records for the period '+payPeriodFilter+'. This action cannot be undone.';
  document.getElementById('pay-approve-notes').value='';
  document.getElementById('pay-approve-alert').style.display='none';
  document.getElementById('pay-approve-modal').style.display='flex';
}

async function paySubmitApprove(){
  const btn=document.getElementById('pay-approve-btn');
  const al=document.getElementById('pay-approve-alert');
  al.style.display='none'; btn.disabled=true; btn.textContent='Approving…';
  const body={notes:document.getElementById('pay-approve-notes').value.trim()};
  if(payApproveRecordId) body.record_id=payApproveRecordId;
  else body.pay_period=payApprovePeriod;
  try{
    const r=await fetch(payAPI+'&action=approve',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){
      document.getElementById('pay-approve-modal').style.display='none';
      payLoad();
    }else{al.textContent=j.message||'Failed';al.style.display='block';}
  }catch(err){al.textContent='Request failed: '+err.message;al.style.display='block';}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> Approve';}
}

async function payDel(id){
  if(!confirm('Delete this payroll record?'))return;
  try{
    const r=await fetch(payAPI+'&id='+id,{method:'DELETE'});
    const j=await r.json();
    j.success?payLoad():alert(j.message);
  }catch(e){alert('Failed to delete');}
}

async function payViewDetail(id){
  document.getElementById('pay-detail-content').innerHTML='<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
  document.getElementById('pay-detail-modal').style.display='flex';
  try{
    const r=await fetch(payAPI+'&id='+id);
    const j=await r.json();
    if(!j.success){document.getElementById('pay-detail-content').innerHTML='<p style="color:red;">'+pEsc(j.message)+'</p>';return;}
    const d=j.data;
    const sc={draft:'#f59e0b',processed:'var(--primary-color)',approved:'#7c3aed',paid:'var(--success-color)'};
    document.getElementById('pay-detail-content').innerHTML=`
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
        <div>
          <h3 style="font-size:17px;font-weight:700;margin:0;">${pEsc(d.first_name)} ${pEsc(d.last_name)}</h3>
          <div style="font-size:12px;color:var(--gray-500);">${pEsc(d.employee_no)} &bull; ${pEsc(d.department_name||'—')} &bull; Period: ${pEsc(d.pay_period)}</div>
        </div>
        <span style="padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;background:${sc[d.status]||'#ccc'}22;color:${sc[d.status]||'#555'};">${d.status.toUpperCase()}</span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
        <div style="background:#f0fdf4;border-radius:8px;padding:12px;">
          <div style="font-size:11px;font-weight:700;color:#15803d;text-transform:uppercase;margin-bottom:8px;">Earnings</div>
          ${row('Basic Pay',       d.basic_pay)}
          ${row('Housing Allow.',  d.housing_allowance)}
          ${row('Transport Allow.',d.transport_allowance)}
          ${row('Comms Allow.',    d.communication_allowance)}
          ${row('Overtime',        d.overtime_pay)}
          ${row('Bonus',           d.bonus)}
          ${row('Other Benefits',  d.other_benefits)}
          <div style="border-top:1px solid #bbf7d0;margin-top:6px;padding-top:6px;display:flex;justify-content:space-between;font-weight:700;color:#15803d;">
            <span>Gross Salary</span><span>RWF ${pFmt(d.gross_salary)}</span>
          </div>
        </div>
        <div style="background:#fff5f5;border-radius:8px;padding:12px;">
          <div style="font-size:11px;font-weight:700;color:#dc2626;text-transform:uppercase;margin-bottom:8px;">Deductions</div>
          ${row('Employee RSSB (6%)', d.employee_rssb, '#dc2626')}
          ${row('PAYE / TPR',         d.tpr_tax,        '#dc2626')}
          ${row('Mutuelle',           d.mutuelle_deduction)}
          ${row('Loans',              d.loan_deduction)}
          ${row('Advance',            d.advance_deduction)}
          ${row('Other Deductions',   d.other_deductions)}
          <div style="border-top:1px solid #fecaca;margin-top:6px;padding-top:6px;display:flex;justify-content:space-between;font-weight:700;color:#dc2626;">
            <span>Total Deductions</span><span>RWF ${pFmt(d.total_deductions)}</span>
          </div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;">
        <div style="background:#dcfce7;border-radius:8px;padding:12px;text-align:center;">
          <div style="font-size:11px;color:#15803d;font-weight:600;">NET PAY</div>
          <div style="font-size:20px;font-weight:800;color:#15803d;">RWF ${pFmt(d.net_pay)}</div>
        </div>
        <div style="background:#ede9fe;border-radius:8px;padding:12px;">
          <div style="font-size:11px;font-weight:700;color:#7c3aed;text-transform:uppercase;margin-bottom:6px;">Employer Contributions</div>
          ${row('Pension (6%)', d.employer_rssb_pension, '#7c3aed')}
          ${row('OHI (2%)',     d.employer_ohi,           '#7c3aed')}
          <div style="border-top:1px solid #c4b5fd;margin-top:6px;padding-top:6px;display:flex;justify-content:space-between;font-weight:700;color:#7c3aed;">
            <span>Employer Cost</span><span>RWF ${pFmt(d.employer_cost)}</span>
          </div>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:12px;">
          <div style="font-size:11px;font-weight:700;color:var(--gray-600);text-transform:uppercase;margin-bottom:6px;">Approval Info</div>
          <div style="font-size:12px;color:var(--gray-700);">
            ${d.approved_at?`<div><i class="fas fa-check-circle" style="color:#7c3aed;"></i> Approved by ${pEsc(d.approved_by_name||'—')}</div><div style="color:var(--gray-500);font-size:11px;">${d.approved_at}</div>`:'<span style="color:var(--gray-400);">Not yet approved</span>'}
            ${d.finance_recorded?`<div style="margin-top:6px;"><i class="fas fa-coins" style="color:var(--success-color);"></i> Finance recorded</div><div style="color:var(--gray-500);font-size:11px;">Ref: ${pEsc(d.finance_reference||'—')}</div>`:''}
          </div>
        </div>
      </div>
      ${(d.status==='draft'||d.status==='processed')?`
      <div style="display:flex;gap:10px;padding-top:12px;border-top:1px solid var(--gray-200);">
        <button onclick="document.getElementById('pay-detail-modal').style.display='none';payOpenApprove(${d.id},'${pEsc(d.first_name+' '+d.last_name)}')"
          style="padding:9px 20px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
          <i class="fas fa-check-circle"></i> Approve
        </button>
        <button onclick="document.getElementById('pay-detail-modal').style.display='none';payDel(${d.id})"
          style="padding:9px 20px;background:white;color:var(--danger-color);border:1px solid var(--danger-color);border-radius:8px;font-weight:600;cursor:pointer;">
          <i class="fas fa-trash"></i> Delete
        </button>
      </div>`:''}
    `;
  }catch(e){document.getElementById('pay-detail-content').innerHTML='<p style="color:red;">Error loading details.</p>';}
}

function row(label, val, color='var(--gray-800)'){
  if(!val||parseFloat(val)===0) return '';
  return `<div style="display:flex;justify-content:space-between;font-size:12px;padding:2px 0;"><span style="color:var(--gray-600);">${label}</span><span style="font-weight:600;color:${color};">RWF ${pFmt(val)}</span></div>`;
}

payLoadEmps();
payLoad();
</script>

<!-- ============================================
ATTENDANCE MODULE
============================================ -->
<?php elseif ($subModule == 'attendance'): ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:20px;">
  <div onclick="document.getElementById('at-filter-status').value='pending';atPage=1;atLoad()" title="Show check-ins waiting for approval" style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;cursor:pointer;"><div id="at-pending" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Pending Approval</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="at-present" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Present</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="at-absent" style="font-size:22px;font-weight:700;color:var(--danger-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Absent</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="at-late" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Late</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="at-leave" style="font-size:22px;font-weight:700;color:#8b5cf6;">—</div><div style="font-size:12px;color:var(--gray-500);">On Leave</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="at-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total Records</div></div>
</div>

<!-- Toolbar -->
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="at-month" type="month" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <input id="at-date" type="date" placeholder="Specific date" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <select id="at-filter-status" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Status</option>
    <option value="pending">Pending Approval</option>
    <option value="present">Present</option><option value="absent">Absent</option>
    <option value="late">Late</option><option value="half-day">Half Day</option>
    <option value="on-leave">On Leave</option><option value="holiday">Holiday</option>
  </select>
  <button onclick="atLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="atOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> Mark Attendance</button>
</div>

<!-- Table -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Date</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Check In</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Check Out</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;color:var(--gray-700);">Late (min)</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;color:var(--gray-700);">OT (hrs)</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
    </tr></thead>
    <tbody id="at-tbody" data-no-page><tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="at-pagination" style="display:flex;justify-content:flex-end;align-items:center;gap:6px;padding:10px 14px;"></div>
</div>

<!-- Attendance Modal -->
<div id="at-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:540px;position:relative;">
    <button onclick="atCloseModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="at-modal-title" style="font-size:17px;font-weight:600;margin-bottom:18px;">Mark Attendance</h3>
    <div id="at-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form id="at-form" onsubmit="atSubmit(event)">
      <input type="hidden" id="at-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Employee <span style="color:var(--danger-color)">*</span></label>
          <select id="at-emp" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Select Employee</option></select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Date <span style="color:var(--danger-color)">*</span></label>
          <input id="at-work-date" type="date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Status <span style="color:var(--danger-color)">*</span></label>
          <select id="at-status" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="present">Present</option><option value="absent">Absent</option>
            <option value="late">Late</option><option value="half-day">Half Day</option>
            <option value="on-leave">On Leave</option><option value="holiday">Holiday</option>
          </select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Check In</label>
          <input id="at-checkin" type="time" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Check Out</label>
          <input id="at-checkout" type="time" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Late (minutes)</label>
          <input id="at-late" type="number" min="0" value="0" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Overtime (hours)</label>
          <input id="at-ot" type="number" min="0" step="0.5" value="0" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Notes</label>
          <textarea id="at-notes" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="at-submit-btn" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> <span id="at-submit-lbl">Save</span></button>
        <button type="button" onclick="atCloseModal()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
var _hrBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const atAPI= _hrBase + '/api.php?endpoint=hr/attendance';
const atEmpAPI= _hrBase + '/api.php?endpoint=hr/employees';
let atPage=1;
function aEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
(function(){const d=new Date();document.getElementById('at-month').value=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');document.getElementById('at-work-date').value=d.toISOString().slice(0,10);})();
async function atLoadEmps(){try{const r=await fetch(atEmpAPI+'&limit=500');const j=await r.json();if(!j.success)return;document.getElementById('at-emp').innerHTML='<option value="">Select Employee</option>'+j.data.employees.map(e=>`<option value="${e.id}">${aEsc(e.first_name+' '+e.last_name)} (${aEsc(e.employee_no)})</option>`).join('');}catch(e){}}
async function atLoad(){
  const month=document.getElementById('at-month').value;
  const date=document.getElementById('at-date').value;
  const status=document.getElementById('at-filter-status').value;
  const p=new URLSearchParams({page:atPage,limit:pgSize('at',50),month,date,status});
  try{
    const r=await fetch(atAPI+'&'+p);const j=await r.json();if(!j.success){atMsg('Failed: '+j.message);return;}
    const{records,stats,pagination}=j.data;
    document.getElementById('at-pending').textContent=stats.pending||0;
    document.getElementById('at-present').textContent=stats.present||0;
    document.getElementById('at-absent').textContent=stats.absent||0;
    document.getElementById('at-late').textContent=stats.late||0;
    document.getElementById('at-leave').textContent=stats.on_leave||0;
    document.getElementById('at-total').textContent=stats.total||0;
    const sc={present:'var(--success-color)',absent:'var(--danger-color)',late:'var(--warning-color)','half-day':'#f97316','on-leave':'#8b5cf6',holiday:'var(--primary-color)'};
    const tb=document.getElementById('at-tbody');
    if(!records.length){tb.innerHTML='<tr><td colspan="8" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-calendar-check" style="font-size:36px;display:block;margin-bottom:8px;"></i>No attendance records found.</td></tr>';return;}
    tb.innerHTML=records.map(r=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:11px 14px;"><div style="font-weight:600;">${aEsc(r.first_name)} ${aEsc(r.last_name)}</div><div style="font-size:12px;color:var(--primary-color);font-family:monospace;">${aEsc(r.employee_no)}</div></td>
      <td style="padding:11px 14px;">${r.work_date}</td>
      <td style="padding:11px 14px;">${r.check_in||'—'}</td>
      <td style="padding:11px 14px;">${r.check_out||'—'}</td>
      <td style="padding:11px 14px;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${sc[r.status]||'#ccc'}20;color:${sc[r.status]||'#555'};">${r.status}</span>${r.approval==='pending'?' <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#fef3c7;color:#92400e;">awaiting approval</span>':''}</td>
      <td style="padding:11px 14px;text-align:right;">${r.late_minutes||0}</td>
      <td style="padding:11px 14px;text-align:right;">${r.overtime_hours||0}</td>
      <td style="padding:11px 14px;text-align:center;white-space:nowrap;">
        ${r.approval==='pending'?`<button onclick="atApprove(${r.id})" title="Approve check-in" style="background:none;border:none;color:var(--success-color);cursor:pointer;font-size:15px;padding:3px;"><i class="fas fa-check-circle"></i></button>`:''}
        <button onclick="atEdit(${r.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;padding:3px;"><i class="fas fa-edit"></i></button>
        <button onclick="atDel(${r.id})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;padding:3px;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
    const pg=document.getElementById('at-pagination');
    pg.innerHTML=pgSizeSelect('at',50,'atPage=1;atLoad()',[10,25,50,100])+(pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="atPage=${i+1};atLoad()" style="padding:5px 10px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;">${i+1}</button>`).join(''):'');
  }catch(e){atMsg('Error: '+e.message);}
}
function atMsg(m){document.getElementById('at-tbody').innerHTML=`<tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}
function atOpenModal(d=null){
  document.getElementById('at-id').value=d?d.id:'';
  document.getElementById('at-emp').value=d?d.employee_id:'';
  document.getElementById('at-work-date').value=d?d.work_date:new Date().toISOString().slice(0,10);
  document.getElementById('at-status').value=d?d.status:'present';
  document.getElementById('at-checkin').value=d?d.check_in:'';
  document.getElementById('at-checkout').value=d?d.check_out:'';
  document.getElementById('at-late').value=d?d.late_minutes:0;
  document.getElementById('at-ot').value=d?d.overtime_hours:0;
  document.getElementById('at-notes').value=d?d.notes:'';
  document.getElementById('at-modal-title').textContent=d?'Edit Attendance':'Mark Attendance';
  document.getElementById('at-submit-lbl').textContent=d?'Update':'Save';
  document.getElementById('at-alert').style.display='none';
  document.getElementById('at-modal').style.display='flex';
}
function atCloseModal(){document.getElementById('at-modal').style.display='none';}
async function atEdit(id){try{const r=await fetch(atAPI+'&id='+id);const j=await r.json();if(j.success)atOpenModal(j.data);}catch(e){alert('Load failed');}}
async function atApprove(id){try{const r=await fetch(atAPI+'&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'approve'})});const j=await r.json();j.success?atLoad():alert(j.message);}catch(e){alert('Failed');}}
async function atDel(id){if(!confirm('Delete this record?'))return;try{const r=await fetch(atAPI+'&id='+id,{method:'DELETE'});const j=await r.json();j.success?atLoad():alert(j.message);}catch(e){alert('Failed');}}
async function atSubmit(e){
  e.preventDefault();const id=document.getElementById('at-id').value;
  const btn=document.getElementById('at-submit-btn');const al=document.getElementById('at-alert');
  al.style.display='none';btn.disabled=true;
  const body={employee_id:document.getElementById('at-emp').value,work_date:document.getElementById('at-work-date').value,status:document.getElementById('at-status').value,check_in:document.getElementById('at-checkin').value||null,check_out:document.getElementById('at-checkout').value||null,late_minutes:document.getElementById('at-late').value,overtime_hours:document.getElementById('at-ot').value,notes:document.getElementById('at-notes').value.trim()};
  try{const r=await fetch(id?atAPI+'&id='+id:atAPI,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){atCloseModal();atLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}}
  catch(err){al.textContent='Request failed';al.style.display='block';}
  finally{btn.disabled=false;}
}
atLoadEmps();atLoad();
</script>

<?php elseif ($subModule == 'leave'): ?>
<?php
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/leave_workflow.php';
$lvMe = lw_me($pdo);
?>
<style>
.lv-btn{padding:9px 16px;border:none;border-radius:8px;font-weight:500;font-size:14px;cursor:pointer;transition:transform 140ms ease-out,background-color 150ms ease;}
.lv-btn:active{transform:scale(0.97);}
.lv-btn:disabled{opacity:.6;cursor:default;transform:none;}
.lv-tab{padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:transparent;color:var(--gray-600);}
.lv-tab.on{background:var(--primary-color);color:white;}
.lv-card{padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;}
.lv-card b{display:block;font-size:22px;font-weight:700;}
.lv-card span{font-size:12px;color:var(--gray-500);}
.lv-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
.lv-in{width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;}
.lv-lbl{display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;}
.lv-steps{display:flex;gap:6px;flex-wrap:wrap;align-items:center;font-size:12px;color:var(--gray-600);margin-bottom:16px;}
.lv-steps i.fa-arrow-right{color:var(--gray-300);font-size:10px;}
.lv-steps span{background:white;border:1px solid var(--gray-200);border-radius:20px;padding:4px 10px;}
.lv-bal{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin-bottom:16px;}
.lv-bal div.c{background:white;border-radius:8px;box-shadow:var(--shadow);padding:12px 14px;}
.lv-bar{height:6px;background:var(--gray-100);border-radius:3px;margin-top:8px;overflow:hidden;}
.lv-bar i{display:block;height:100%;background:var(--success-color);border-radius:3px;}
.lv-row-act td:first-child{box-shadow:inset 3px 0 0 var(--warning-color);}
.lv-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1100;align-items:center;justify-content:center;padding:16px;overflow-y:auto;}
.lv-overlay.show{display:flex;}
.lv-dialog{background:white;border-radius:12px;padding:26px;width:100%;position:relative;margin:auto;transition:transform 200ms cubic-bezier(0.23,1,0.32,1),opacity 200ms cubic-bezier(0.23,1,0.32,1);}
@starting-style{.lv-overlay.show .lv-dialog{transform:scale(0.96);opacity:0;}}
@media (prefers-reduced-motion:reduce){.lv-dialog{transition:opacity 150ms ease;}@starting-style{.lv-overlay.show .lv-dialog{transform:none;}}}
.lv-x{position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);}
.lv-err{display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;}
.lv-tl{list-style:none;margin:0 0 16px;padding:0;}
.lv-tl li{position:relative;padding:0 0 14px 28px;font-size:13px;}
.lv-tl li:before{content:'';position:absolute;left:8px;top:18px;bottom:0;width:2px;background:var(--gray-200);}
.lv-tl li:last-child:before{display:none;}
.lv-tl li .dot{position:absolute;left:0;top:1px;width:18px;height:18px;border-radius:50%;background:var(--gray-200);color:white;font-size:9px;display:flex;align-items:center;justify-content:center;}
.lv-tl li.done .dot{background:var(--success-color);}
.lv-tl li.now .dot{background:var(--warning-color);}
.lv-tl li.bad .dot{background:var(--danger-color);}
.lv-tl li .t{font-weight:600;color:var(--gray-800);}
.lv-tl li .s{color:var(--gray-500);font-size:12px;margin-top:2px;}
.lv-tl li .n{margin-top:4px;padding:6px 8px;background:var(--gray-50);border-radius:6px;color:var(--gray-700);}
</style>

<!-- How a request moves -->
<div class="lv-steps">
  <span><i class="fas fa-user"></i> 1. Employee submits</span><i class="fas fa-arrow-right"></i>
  <span><i class="fas fa-clipboard-list"></i> 2. HR reviews reason, dates, documents &amp; balance</span><i class="fas fa-arrow-right"></i>
  <span><i class="fas fa-user-check"></i> 3. Manager approves or rejects</span><i class="fas fa-arrow-right"></i>
  <span><i class="fas fa-bell"></i> 4. Employee is notified</span>
</div>

<!-- Leave Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:white;padding:6px;border-radius:10px;box-shadow:var(--shadow);width:fit-content;">
  <button id="lv-tab-requests" class="lv-tab on" onclick="lvTab('requests')">Leave Requests</button>
  <button id="lv-tab-permissions" class="lv-tab" onclick="lvTab('permissions')">Permission Requests</button>
  <?php if ($lvMe['is_hr']): ?><button id="lv-tab-types" class="lv-tab" onclick="lvTab('types')">Leave Types</button><?php endif; ?>
</div>

<?php if (!$lvMe['emp_id']): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;">
  <i class="fas fa-info-circle"></i> Your user account is not linked to an employee record, so you cannot request leave for yourself<?php echo $lvMe['is_hr'] ? ' (you can still file requests for employees)' : ''; ?>. HR links it by setting the same Employee No. on the user and the employee.
</div>
<?php endif; ?>

<!-- ===== LEAVE REQUESTS PANEL ===== -->
<div id="lv-panel-requests">
<?php if ($lvMe['emp_id']): ?>
<div style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">My leave balance <span id="lv-bal-year" style="font-weight:400;color:var(--gray-500);"></span></div>
<div id="lv-bal" class="lv-bal"></div>
<?php endif; ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:18px;">
  <div class="lv-card"><b id="lv-total" style="color:var(--primary-color);">—</b><span>Total</span></div>
  <div class="lv-card"><b id="lv-pending" style="color:#b45309;">—</b><span>Awaiting HR</span></div>
  <div class="lv-card"><b id="lv-hr" style="color:#1d4ed8;">—</b><span>Awaiting Manager</span></div>
  <div class="lv-card"><b id="lv-approved" style="color:var(--success-color);">—</b><span>Approved</span></div>
  <div class="lv-card"><b id="lv-rejected" style="color:var(--danger-color);">—</b><span>Rejected</span></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <?php if ($lvMe['is_hr']): ?>
  <select id="lv-scope" class="lv-in" style="width:auto;" onchange="lvPage=1;lvLoad()"><option value="all">All staff</option><?php if ($lvMe['emp_id']): ?><option value="mine">Only my requests</option><?php endif; ?></select>
  <?php endif; ?>
  <select id="lv-filter-status" class="lv-in" style="width:auto;" onchange="lvPage=1;lvLoad()">
    <option value="">All Status</option><option value="pending">Awaiting HR</option><option value="hr_reviewed">Awaiting Manager</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option>
  </select>
  <select id="lv-filter-type" class="lv-in" style="width:auto;min-width:160px;" onchange="lvPage=1;lvLoad()"><option value="">All Leave Types</option></select>
  <?php if ($lvMe['emp_id'] || $lvMe['is_hr']): ?>
  <button class="lv-btn" onclick="lvOpenModal()" style="background:var(--success-color);color:white;margin-left:auto;"><i class="fas fa-plus"></i> New Leave Request</button>
  <?php endif; ?>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Leave Type</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">From</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">To</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Days</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);"></th>
    </tr></thead>
    <tbody id="lv-tbody" data-no-page><tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="lv-pagination" style="display:flex;justify-content:flex-end;align-items:center;gap:6px;padding:10px 14px;"></div>
</div>
</div>

<!-- ===== PERMISSIONS PANEL ===== -->
<div id="lv-panel-permissions" style="display:none;">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:18px;">
  <div class="lv-card"><b id="pm-total" style="color:var(--primary-color);">—</b><span>Total</span></div>
  <div class="lv-card"><b id="pm-pending" style="color:#b45309;">—</b><span>Awaiting HR</span></div>
  <div class="lv-card"><b id="pm-hr" style="color:#1d4ed8;">—</b><span>Awaiting Manager</span></div>
  <div class="lv-card"><b id="pm-approved" style="color:var(--success-color);">—</b><span>Approved</span></div>
  <div class="lv-card"><b id="pm-rejected" style="color:var(--danger-color);">—</b><span>Rejected</span></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <?php if ($lvMe['is_hr']): ?>
  <select id="pm-scope" class="lv-in" style="width:auto;" onchange="pmPage=1;pmLoad()"><option value="all">All staff</option><?php if ($lvMe['emp_id']): ?><option value="mine">Only my requests</option><?php endif; ?></select>
  <?php endif; ?>
  <select id="pm-filter-status" class="lv-in" style="width:auto;" onchange="pmPage=1;pmLoad()">
    <option value="">All Status</option><option value="pending">Awaiting HR</option><option value="hr_reviewed">Awaiting Manager</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="cancelled">Cancelled</option>
  </select>
  <?php if ($lvMe['emp_id'] || $lvMe['is_hr']): ?>
  <button class="lv-btn" onclick="pmOpenModal()" style="background:var(--success-color);color:white;margin-left:auto;"><i class="fas fa-plus"></i> New Permission</button>
  <?php endif; ?>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Date</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Time</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Reason</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);"></th>
    </tr></thead>
    <tbody id="pm-tbody" data-no-page><tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="pm-pagination" style="display:flex;justify-content:flex-end;align-items:center;gap:6px;padding:10px 14px;"></div>
</div>
</div>

<?php if ($lvMe['is_hr']): ?>
<!-- ===== LEAVE TYPES PANEL ===== -->
<div id="lv-panel-types" style="display:none;">
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;">
  <span style="font-weight:600;color:var(--gray-700);">Leave Types Configuration <span style="font-weight:400;font-size:12px;color:var(--gray-500);">(0 days = no limit)</span></span>
  <button class="lv-btn" onclick="ltOpenModal()" style="background:var(--primary-color);color:white;"><i class="fas fa-plus"></i> Add Type</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Name</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Code</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Days / year</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Paid</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Carry Forward</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
    </tr></thead>
    <tbody id="lt-tbody"><tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
  </table>
</div>
</div>
<?php endif; ?>

<!-- Leave Request Modal -->
<div id="lv-modal" class="lv-overlay">
  <div class="lv-dialog" style="max-width:520px;">
    <button class="lv-x" onclick="lvCloseModal()"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:4px;">New Leave Request</h3>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">Goes to HR for review, then to the manager for the final decision.</p>
    <div id="lv-alert" class="lv-err"></div>
    <form id="lv-form" onsubmit="lvSubmit(event)">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <?php if ($lvMe['is_hr']): ?>
        <div style="grid-column:1/-1;"><label class="lv-lbl">Employee <span style="font-weight:400;color:var(--gray-400);"><?php echo $lvMe['emp_id'] ? '(leave empty for yourself)' : ''; ?></span></label>
          <select id="lv-emp" class="lv-in" onchange="lvLoadFormBalance()"><option value=""><?php echo $lvMe['emp_id'] ? 'Myself' : 'Select Employee'; ?></option></select></div>
        <?php endif; ?>
        <div style="grid-column:1/-1;"><label class="lv-lbl">Leave Type <span style="color:var(--danger-color)">*</span></label>
          <select id="lv-type" class="lv-in" required onchange="lvSummary()"><option value="">Select Type</option></select></div>
        <div><label class="lv-lbl">Start Date <span style="color:var(--danger-color)">*</span></label>
          <input id="lv-start" type="date" class="lv-in" required onchange="lvSummary()"></div>
        <div><label class="lv-lbl">End Date <span style="color:var(--danger-color)">*</span></label>
          <input id="lv-end" type="date" class="lv-in" required onchange="lvSummary()"></div>
        <div id="lv-summary" style="grid-column:1/-1;display:none;padding:10px 12px;border-radius:8px;font-size:13px;"></div>
        <div style="grid-column:1/-1;"><label class="lv-lbl">Reason</label>
          <textarea id="lv-reason" rows="2" class="lv-in" style="resize:vertical;"></textarea></div>
        <div style="grid-column:1/-1;"><label class="lv-lbl">Supporting Document <span style="color:var(--gray-400);font-weight:400;">(e.g. medical certificate — PDF, image, DOC)</span></label>
          <input id="lv-proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="lv-in"></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="lv-submit-btn" class="lv-btn" style="flex:1;background:var(--success-color);color:white;font-weight:600;"><i class="fas fa-paper-plane"></i> Send to HR</button>
        <button type="button" class="lv-btn" onclick="lvCloseModal()" style="background:white;color:var(--gray-700);border:1px solid var(--gray-200);">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Permission Modal -->
<div id="pm-modal" class="lv-overlay">
  <div class="lv-dialog" style="max-width:520px;">
    <button class="lv-x" onclick="pmCloseModal()"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:4px;">New Permission Request</h3>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">For a few hours away during a work day. Goes to HR, then to the manager.</p>
    <div id="pm-alert" class="lv-err"></div>
    <form id="pm-form" onsubmit="pmSubmit(event)">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <?php if ($lvMe['is_hr']): ?>
        <div style="grid-column:1/-1;"><label class="lv-lbl">Employee <span style="font-weight:400;color:var(--gray-400);"><?php echo $lvMe['emp_id'] ? '(leave empty for yourself)' : ''; ?></span></label>
          <select id="pm-emp" class="lv-in"><option value=""><?php echo $lvMe['emp_id'] ? 'Myself' : 'Select Employee'; ?></option></select></div>
        <?php endif; ?>
        <div style="grid-column:1/-1;"><label class="lv-lbl">Date <span style="color:var(--danger-color)">*</span></label>
          <input id="pm-date" type="date" class="lv-in" required></div>
        <div><label class="lv-lbl">From Time <span style="color:var(--danger-color)">*</span></label>
          <input id="pm-start" type="time" class="lv-in" required></div>
        <div><label class="lv-lbl">To Time <span style="color:var(--danger-color)">*</span></label>
          <input id="pm-end" type="time" class="lv-in" required></div>
        <div style="grid-column:1/-1;"><label class="lv-lbl">Reason <span style="color:var(--danger-color)">*</span></label>
          <textarea id="pm-reason" rows="2" class="lv-in" style="resize:vertical;" required></textarea></div>
        <div style="grid-column:1/-1;"><label class="lv-lbl">Supporting Document <span style="color:var(--gray-400);font-weight:400;">(PDF, image, DOC)</span></label>
          <input id="pm-proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="lv-in"></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="pm-submit-btn" class="lv-btn" style="flex:1;background:var(--success-color);color:white;font-weight:600;"><i class="fas fa-paper-plane"></i> Send to HR</button>
        <button type="button" class="lv-btn" onclick="pmCloseModal()" style="background:white;color:var(--gray-700);border:1px solid var(--gray-200);">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Review modal (leave + permission) -->
<div id="wf-modal" class="lv-overlay">
  <div class="lv-dialog" style="max-width:620px;">
    <button class="lv-x" onclick="wfClose()"><i class="fas fa-times"></i></button>
    <h3 id="wf-title" style="font-size:18px;font-weight:600;margin-bottom:16px;">Request</h3>
    <div id="wf-body"></div>
    <div id="wf-alert" class="lv-err"></div>
    <div id="wf-notes-wrap" style="display:none;margin-bottom:12px;"><label class="lv-lbl" id="wf-notes-lbl">Comment</label><textarea id="wf-notes" rows="2" class="lv-in" style="resize:vertical;"></textarea></div>
    <div id="wf-actions" style="display:flex;gap:10px;flex-wrap:wrap;padding-top:14px;border-top:1px solid var(--gray-200);"></div>
  </div>
</div>

<?php if ($lvMe['is_hr']): ?>
<!-- Leave Type Modal -->
<div id="lt-modal" class="lv-overlay">
  <div class="lv-dialog" style="max-width:460px;">
    <button class="lv-x" onclick="ltCloseModal()"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;">Add Leave Type</h3>
    <div id="lt-alert" class="lv-err"></div>
    <form id="lt-form" onsubmit="ltSubmit(event)">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div><label class="lv-lbl">Name <span style="color:var(--danger-color)">*</span></label><input id="lt-name" type="text" class="lv-in"></div>
        <div><label class="lv-lbl">Code <span style="color:var(--danger-color)">*</span></label><input id="lt-code" type="text" class="lv-in"></div>
        <div><label class="lv-lbl">Days per year <span style="font-weight:400;color:var(--gray-400);">(0 = no limit)</span></label><input id="lt-days" type="number" min="0" value="0" class="lv-in"></div>
        <div><label class="lv-lbl">Paid</label><select id="lt-paid" class="lv-in"><option value="1">Yes</option><option value="0">No</option></select></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button type="submit" class="lv-btn" style="flex:1;background:var(--primary-color);color:white;font-weight:600;"><i class="fas fa-save"></i> Save</button>
        <button type="button" class="lv-btn" onclick="ltCloseModal()" style="background:white;color:var(--gray-700);border:1px solid var(--gray-200);">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
var _hrBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const lvAPI= _hrBase + '/api.php?endpoint=hr/leave';
const pmAPI= _hrBase + '/api.php?endpoint=hr/permissions';
const lvEmpAPI= _hrBase + '/api.php?endpoint=hr/employees';
const ME = <?php echo json_encode(['uid' => $lvMe['uid'], 'emp_id' => $lvMe['emp_id'], 'is_hr' => $lvMe['is_hr'], 'is_admin' => $lvMe['is_admin']]); ?>;
let lvPage=1,pmPage=1,lvTypes=[],lvMyBal={},lvFormBal={};
function lEsc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
const ST={pending:['Awaiting HR','#b45309','#fef3c7'],hr_reviewed:['Awaiting Manager','#1d4ed8','#dbeafe'],approved:['Approved','#047857','#d1fae5'],rejected:['Rejected','#b91c1c','#fee2e2'],cancelled:['Cancelled','#4b5563','#f3f4f6']};
function stPill(s){const x=ST[s]||[s,'#555','#eee'];return `<span class="lv-pill" style="background:${x[2]};color:${x[1]};">${x[0]}</span>`;}
function needsMe(r){return (ME.is_hr&&r.status==='pending')||(ME.is_admin&&r.status==='hr_reviewed');}
function fmtD(d){return d?new Date(d.replace(' ','T')).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}):'—';}
function fmtDT(d){return d?new Date(d.replace(' ','T')).toLocaleString('en-GB',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}):'';}
function pager(id,prefix,pg,cb){document.getElementById(id).innerHTML=pgSizeSelect(prefix,25,cb.replace('X','1'),[10,25,50,100])+(pg.total_pages>1?Array.from({length:pg.total_pages},(_,i)=>`<button onclick="${cb.replace('X',i+1)}" style="padding:5px 10px;border:1px solid ${i+1===pg.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pg.page?'var(--primary-color)':'white'};color:${i+1===pg.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;">${i+1}</button>`).join(''):'');}
function actCell(kind,r){return needsMe(r)?`<button class="lv-btn" onclick="event.stopPropagation();wfOpen('${kind}',${r.id})" style="padding:5px 12px;font-size:12px;background:var(--primary-color);color:white;">Review</button>`:`<button class="lv-btn" onclick="event.stopPropagation();wfOpen('${kind}',${r.id})" style="padding:5px 12px;font-size:12px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);">View</button>`;}

function lvTab(t){
  ['requests','permissions','types'].forEach(x=>{
    const p=document.getElementById('lv-panel-'+x); if(!p)return;
    p.style.display=x===t?'block':'none';
    document.getElementById('lv-tab-'+x).classList.toggle('on',x===t);
  });
  if(t==='requests')lvLoad();
  if(t==='permissions')pmLoad();
  if(t==='types')ltLoad();
}

async function lvBalance(empId){
  const r=await fetch(lvAPI+'&type=balance'+(empId?'&employee_id='+empId:''));const j=await r.json();
  const m={}; if(j.success) j.data.forEach(b=>m[b.id]=b); return m;
}
function balText(b){
  if(!b)return '';
  if(b.remaining===null)return 'no limit';
  const free=b.remaining-b.pending;
  return `${free} of ${b.days_allowed} day(s) left`+(b.pending?` · ${b.pending} in review`:'');
}
async function lvRenderMyBalance(){
  const box=document.getElementById('lv-bal'); if(!box)return;
  lvMyBal=await lvBalance(0);
  const list=Object.values(lvMyBal).filter(b=>b.days_allowed>0);
  if(list.length) document.getElementById('lv-bal-year').textContent='('+list[0].year+')';
  box.innerHTML=list.map(b=>{const free=Math.max(0,b.remaining-b.pending),pct=Math.max(0,Math.min(100,free/b.days_allowed*100));return `<div class="c">
    <div style="font-size:12px;color:var(--gray-500);">${lEsc(b.name)}</div>
    <div style="font-size:18px;font-weight:700;color:var(--gray-800);margin-top:2px;">${free}<span style="font-size:12px;font-weight:400;color:var(--gray-500);"> / ${b.days_allowed} days left</span></div>
    <div class="lv-bar"><i style="width:${pct}%;${pct<25?'background:var(--danger-color);':''}"></i></div>
    <div style="font-size:11px;color:var(--gray-500);margin-top:6px;">${b.used} used${b.pending?' · '+b.pending+' in review':''}</div></div>`;}).join('')||'<div style="font-size:13px;color:var(--gray-400);">No leave types configured.</div>';
}

async function lvInit(){
  if(ME.is_hr){
    try{
      const r=await fetch(lvEmpAPI+'&limit=500');const j=await r.json();
      if(j.success){
        const opts=j.data.employees.filter(e=>+e.id!==ME.emp_id).map(e=>`<option value="${e.id}">${lEsc(e.first_name+' '+e.last_name)} (${lEsc(e.employee_no)})</option>`).join('');
        document.getElementById('lv-emp').insertAdjacentHTML('beforeend',opts);
        document.getElementById('pm-emp').insertAdjacentHTML('beforeend',opts);
      }
    }catch(e){}
  }
  try{
    const r=await fetch(lvAPI+'&type=types');const j=await r.json();
    if(j.success){
      lvTypes=j.data.filter(t=>+t.is_active);
      document.getElementById('lv-filter-type').innerHTML='<option value="">All Leave Types</option>'+j.data.map(t=>`<option value="${t.id}">${lEsc(t.name)}</option>`).join('');
    }
  }catch(e){}
  lvRenderMyBalance();
  lvLoad();
}

async function lvLoad(){
  const scope=document.getElementById('lv-scope');
  const p=new URLSearchParams({page:lvPage,limit:pgSize('lv',25),status:document.getElementById('lv-filter-status').value,leave_type_id:document.getElementById('lv-filter-type').value});
  if(scope&&scope.value==='mine')p.set('employee_id',ME.emp_id);
  try{
    const r=await fetch(lvAPI+'&'+p);const j=await r.json();if(!j.success){lvMsg(lEsc(j.message||'Failed'));return;}
    const{requests,stats,pagination}=j.data;
    [['lv-total','total'],['lv-pending','pending'],['lv-hr','hr_reviewed'],['lv-approved','approved'],['lv-rejected','rejected']].forEach(([el,k])=>document.getElementById(el).textContent=stats[k]||0);
    const tb=document.getElementById('lv-tbody');
    if(!requests.length){tb.innerHTML='<tr><td colspan="7" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-umbrella-beach" style="font-size:36px;display:block;margin-bottom:8px;"></i>No leave requests found.</td></tr>';pager('lv-pagination','lv',pagination,'lvPage=X;lvLoad()');return;}
    tb.innerHTML=requests.map(r=>`<tr class="${needsMe(r)?'lv-row-act':''}" style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onclick="wfOpen('leave',${r.id})">
      <td style="padding:11px 14px;"><div style="font-weight:600;">${lEsc(r.first_name)} ${lEsc(r.last_name)}</div><div style="font-size:12px;color:var(--primary-color);font-family:monospace;">${lEsc(r.employee_no)}</div></td>
      <td style="padding:11px 14px;">${lEsc(r.leave_type_name)} ${+r.is_paid?'<span style="font-size:11px;background:#d1fae5;color:#065f46;padding:1px 6px;border-radius:8px;">Paid</span>':'<span style="font-size:11px;background:#fee2e2;color:#991b1b;padding:1px 6px;border-radius:8px;">Unpaid</span>'}</td>
      <td style="padding:11px 14px;white-space:nowrap;">${fmtD(r.start_date)}</td>
      <td style="padding:11px 14px;white-space:nowrap;">${fmtD(r.end_date)}</td>
      <td style="padding:11px 14px;text-align:center;font-weight:700;">${r.days_requested}</td>
      <td style="padding:11px 14px;">${stPill(r.status)}</td>
      <td style="padding:11px 14px;text-align:center;">${actCell('leave',r)}</td>
    </tr>`).join('');
    pager('lv-pagination','lv',pagination,'lvPage=X;lvLoad()');
  }catch(e){lvMsg('Error: '+lEsc(e.message));}
}
function lvMsg(m){document.getElementById('lv-tbody').innerHTML=`<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}

// --- New leave request ---
async function lvLoadFormBalance(){
  const sel=document.getElementById('lv-emp'); const emp=sel?sel.value:'';
  lvFormBal=emp?await lvBalance(emp):(ME.emp_id?lvMyBal:{});
  const cur=document.getElementById('lv-type').value;
  document.getElementById('lv-type').innerHTML='<option value="">Select Type</option>'+lvTypes.map(t=>{const b=lvFormBal[t.id];return `<option value="${t.id}">${lEsc(t.name)}${b?' — '+balText(b):''}</option>`;}).join('');
  document.getElementById('lv-type').value=cur;
  lvSummary();
}
function lvSummary(){
  const box=document.getElementById('lv-summary'),s=document.getElementById('lv-start').value,e=document.getElementById('lv-end').value,b=lvFormBal[document.getElementById('lv-type').value];
  if(!s||!e){box.style.display='none';return;}
  const days=Math.round((new Date(e)-new Date(s))/864e5)+1;
  let html,bad=false;
  if(days<1){html='<i class="fas fa-exclamation-circle"></i> End date is before the start date.';bad=true;}
  else{
    html=`<strong>${days}</strong> calendar day(s)`;
    if(b&&b.remaining!==null){const left=b.remaining-b.pending-days;bad=left<0;html+=bad?` — only ${Math.max(0,b.remaining-b.pending)} day(s) of ${lEsc(b.name)} left`:` · <strong>${left}</strong> day(s) of ${lEsc(b.name)} left after this`;}
  }
  box.style.display='block';box.style.background=bad?'#fef2f2':'#f0fdf4';box.style.color=bad?'#991b1b':'#166534';box.innerHTML=html;
}
async function lvOpenModal(){
  document.getElementById('lv-form').reset();document.getElementById('lv-alert').style.display='none';document.getElementById('lv-summary').style.display='none';
  document.getElementById('lv-modal').classList.add('show');
  await lvLoadFormBalance();
}
function lvCloseModal(){document.getElementById('lv-modal').classList.remove('show');}
async function lvSubmit(e){
  e.preventDefault();const btn=document.getElementById('lv-submit-btn');const al=document.getElementById('lv-alert');al.style.display='none';btn.disabled=true;
  const fd=new FormData();
  const emp=document.getElementById('lv-emp'); if(emp&&emp.value)fd.append('employee_id',emp.value);
  fd.append('leave_type_id',document.getElementById('lv-type').value);
  fd.append('start_date',document.getElementById('lv-start').value);
  fd.append('end_date',document.getElementById('lv-end').value);
  fd.append('reason',document.getElementById('lv-reason').value.trim());
  const proofFile=document.getElementById('lv-proof').files[0];
  if(proofFile){fd.append('proof_document',proofFile);}
  try{const r=await fetch(lvAPI,{method:'POST',body:fd});const j=await r.json();if(j.success){lvCloseModal();lvLoad();lvRenderMyBalance();}else{al.textContent=j.message||'Failed';al.style.display='block';}}
  catch(err){al.textContent='Request failed';al.style.display='block';}finally{btn.disabled=false;}
}

// --- Permissions ---
async function pmLoad(){
  const scope=document.getElementById('pm-scope');
  const p=new URLSearchParams({page:pmPage,limit:pgSize('pm',25),status:document.getElementById('pm-filter-status').value});
  if(scope&&scope.value==='mine')p.set('employee_id',ME.emp_id);
  try{
    const r=await fetch(pmAPI+'&'+p);const j=await r.json();if(!j.success){pmMsg(lEsc(j.message||'Failed'));return;}
    const{requests,stats,pagination}=j.data;
    [['pm-total','total'],['pm-pending','pending'],['pm-hr','hr_reviewed'],['pm-approved','approved'],['pm-rejected','rejected']].forEach(([el,k])=>document.getElementById(el).textContent=stats[k]||0);
    const tb=document.getElementById('pm-tbody');
    if(!requests.length){tb.innerHTML='<tr><td colspan="6" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-clock" style="font-size:36px;display:block;margin-bottom:8px;"></i>No permission requests found.</td></tr>';pager('pm-pagination','pm',pagination,'pmPage=X;pmLoad()');return;}
    tb.innerHTML=requests.map(r=>`<tr class="${needsMe(r)?'lv-row-act':''}" style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onclick="wfOpen('permission',${r.id})">
      <td style="padding:11px 14px;"><div style="font-weight:600;">${lEsc(r.first_name)} ${lEsc(r.last_name)}</div><div style="font-size:12px;color:var(--primary-color);font-family:monospace;">${lEsc(r.employee_no)}</div></td>
      <td style="padding:11px 14px;white-space:nowrap;">${fmtD(r.request_date)}</td>
      <td style="padding:11px 14px;white-space:nowrap;">${lEsc(String(r.start_time).slice(0,5))} — ${lEsc(String(r.end_time).slice(0,5))}</td>
      <td style="padding:11px 14px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${lEsc(r.reason)}</td>
      <td style="padding:11px 14px;">${stPill(r.status)}</td>
      <td style="padding:11px 14px;text-align:center;">${actCell('permission',r)}</td>
    </tr>`).join('');
    pager('pm-pagination','pm',pagination,'pmPage=X;pmLoad()');
  }catch(e){pmMsg('Error: '+lEsc(e.message));}
}
function pmMsg(m){document.getElementById('pm-tbody').innerHTML=`<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}
function pmOpenModal(){document.getElementById('pm-form').reset();document.getElementById('pm-date').value=new Date().toISOString().slice(0,10);document.getElementById('pm-alert').style.display='none';document.getElementById('pm-modal').classList.add('show');}
function pmCloseModal(){document.getElementById('pm-modal').classList.remove('show');}
async function pmSubmit(e){
  e.preventDefault();const btn=document.getElementById('pm-submit-btn');const al=document.getElementById('pm-alert');al.style.display='none';btn.disabled=true;
  const fd=new FormData();
  const emp=document.getElementById('pm-emp'); if(emp&&emp.value)fd.append('employee_id',emp.value);
  fd.append('request_date',document.getElementById('pm-date').value);
  fd.append('start_time',document.getElementById('pm-start').value);
  fd.append('end_time',document.getElementById('pm-end').value);
  fd.append('reason',document.getElementById('pm-reason').value.trim());
  const proofFile=document.getElementById('pm-proof').files[0];
  if(proofFile){fd.append('proof_document',proofFile);}
  try{const r=await fetch(pmAPI,{method:'POST',body:fd});const j=await r.json();if(j.success){pmCloseModal();pmLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}}
  catch(err){al.textContent='Request failed';al.style.display='block';}finally{btn.disabled=false;}
}

// ===== REVIEW MODAL (both kinds) =====
let wfCur=null;
async function wfOpen(kind,id){
  try{
    const r=await fetch((kind==='leave'?lvAPI:pmAPI)+'&id='+id);const j=await r.json();
    if(!j.success){alert(j.message||'Failed to load');return;}
    wfCur={kind,d:j.data};wfRender();
    document.getElementById('wf-modal').classList.add('show');
  }catch(e){alert('Failed to load');}
}
function wfClose(){document.getElementById('wf-modal').classList.remove('show');}
function wfRender(){
  const {kind,d}=wfCur, api=kind==='leave'?lvAPI:pmAPI;
  document.getElementById('wf-title').innerHTML=(kind==='leave'?'Leave Request':'Permission Request')+' &nbsp;'+stPill(d.status);
  const row=(k,v)=>`<div><div style="color:var(--gray-500);font-size:12px;">${k}</div><div style="font-weight:600;margin-top:2px;">${v}</div></div>`;
  let grid=row('Employee',lEsc(d.first_name+' '+d.last_name)+` <span style="font-weight:400;color:var(--gray-500);">(${lEsc(d.employee_no)})</span>`);
  if(kind==='leave') grid+=row('Leave Type',lEsc(d.leave_type_name))+row('From',fmtD(d.start_date))+row('To',fmtD(d.end_date))+row('Days',d.days_requested)+row('Department',lEsc(d.dept_name||'—'));
  else grid+=row('Date',fmtD(d.request_date))+row('Time',lEsc(String(d.start_time).slice(0,5))+' — '+lEsc(String(d.end_time).slice(0,5)))+row('Department',lEsc(d.department_name||'—'));
  let html=`<div style="background:var(--gray-50);border-radius:8px;padding:14px;margin-bottom:14px;"><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:14px;">${grid}</div>
    <div style="margin-top:12px;font-size:12px;color:var(--gray-500);">Reason</div><div style="margin-top:2px;padding:8px;background:white;border-radius:6px;border-left:3px solid var(--primary-color);font-size:14px;white-space:pre-wrap;">${lEsc(d.reason||'—')}</div>
    ${d.proof_document_name?`<div style="margin-top:10px;font-size:13px;"><a href="${api}&id=${d.id}&download=1" style="color:var(--primary-color);text-decoration:none;"><i class="fas fa-paperclip"></i> ${lEsc(d.proof_document_name)}</a></div>`:'<div style="margin-top:10px;font-size:12px;color:var(--gray-400);">No supporting document</div>'}</div>`;
  // Balance check for HR / manager
  if(d.balance&&d.status!=='approved'){
    const b=d.balance;
    if(b.remaining===null) html+=`<div style="margin-bottom:14px;padding:10px 12px;border-radius:8px;background:#f0fdf4;color:#166534;font-size:13px;"><i class="fas fa-scale-balanced"></i> ${lEsc(b.name)} has no day limit.</div>`;
    else{const over=+d.days_requested>b.remaining;html+=`<div style="margin-bottom:14px;padding:10px 12px;border-radius:8px;background:${over?'#fef2f2':'#f0fdf4'};color:${over?'#991b1b':'#166534'};font-size:13px;"><i class="fas fa-scale-balanced"></i> ${lEsc(b.name)} ${b.year}: ${b.days_allowed} allowed · ${b.used} used · <strong>${b.remaining} left</strong>${over?' — this request is more than what is left':' · '+(b.remaining-d.days_requested)+' left after approval'}</div>`;}
  }
  // Timeline
  const hrRejected=d.status==='rejected'&&d.hr_reviewed_at&&d.hr_reviewed_at===d.reviewed_at;
  const final=['approved','rejected'].includes(d.status);
  const step=(cls,icon,t,s,n)=>`<li class="${cls}"><span class="dot"><i class="fas fa-${icon}"></i></span><div class="t">${t}</div>${s?`<div class="s">${s}</div>`:''}${n?`<div class="n">${lEsc(n)}</div>`:''}</li>`;
  let tl=step('done','check','Submitted',fmtDT(d.created_at));
  if(d.status==='cancelled') tl+=step('bad','times','Cancelled','');
  else{
    tl+= d.hr_reviewed_at ? step(hrRejected?'bad':'done',hrRejected?'times':'check',hrRejected?'Rejected by HR':'Reviewed by HR',lEsc(d.hr_reviewer_name||'')+' · '+fmtDT(d.hr_reviewed_at),d.hr_notes)
         : step(d.status==='pending'?'now':'done',d.status==='pending'?'hourglass-half':'check','HR review',d.status==='pending'?'Waiting for HR':'');
    if(!hrRejected) tl+= final ? step(d.status==='approved'?'done':'bad',d.status==='approved'?'check':'times',d.status==='approved'?'Approved by manager':'Rejected by manager',lEsc(d.reviewer_name||'')+(d.reviewed_at?' · '+fmtDT(d.reviewed_at):''),d.review_notes)
         : step(d.status==='hr_reviewed'?'now':'','user-check','Manager decision',d.status==='hr_reviewed'?'Waiting for the manager':'');
    tl+=step(final?'done':'','bell','Employee notified',final?'Decision sent to the employee':'');
  }
  html+=`<ul class="lv-tl">${tl}</ul>`;
  document.getElementById('wf-body').innerHTML=html;

  // Actions for this user at this stage
  const own=+d.employee_id===ME.emp_id, btn=(a,label,bg,fg,icon,brd)=>`<button class="lv-btn" onclick="wfAct('${a}')" style="flex:1;background:${bg};color:${fg};font-weight:600;${brd?'border:1px solid var(--gray-200);':''}"><i class="fas fa-${icon}"></i> ${label}</button>`;
  let acts='',notes=false,lbl='Comment';
  if(ME.is_hr&&d.status==='pending'){acts+=btn('forward','Forward to Manager','var(--primary-color)','white','share')+btn('reject','Reject','var(--danger-color)','white','times');notes=true;lbl='HR comment (required when rejecting)';}
  else if(ME.is_admin&&d.status==='hr_reviewed'){acts+=btn('approve','Approve','var(--success-color)','white','check')+btn('reject','Reject','var(--danger-color)','white','times');notes=true;lbl='Decision note (required when rejecting)';}
  if((own||ME.is_hr)&&['pending','hr_reviewed'].includes(d.status)) acts+=btn('cancel','Cancel request','white','var(--gray-700)','ban',true);
  if(ME.is_hr&&d.status!=='approved') acts+=`<button class="lv-btn" onclick="wfDelete()" title="Delete" style="background:white;color:var(--danger-color);border:1px solid var(--gray-200);"><i class="fas fa-trash"></i></button>`;
  acts+=`<button class="lv-btn" onclick="wfClose()" style="background:white;color:var(--gray-700);border:1px solid var(--gray-200);">Close</button>`;
  document.getElementById('wf-actions').innerHTML=acts;
  document.getElementById('wf-notes-wrap').style.display=notes?'block':'none';
  document.getElementById('wf-notes-lbl').textContent=lbl;
  document.getElementById('wf-notes').value='';
  document.getElementById('wf-alert').style.display='none';
}
function wfErr(m){const a=document.getElementById('wf-alert');a.textContent=m;a.style.display='block';}
async function wfAct(action){
  const notes=document.getElementById('wf-notes').value.trim();
  if(action==='reject'&&!notes){wfErr('Write the reason for rejecting — the employee will see it.');document.getElementById('wf-notes').focus();return;}
  if(action==='cancel'&&!confirm('Cancel this request?'))return;
  const {kind,d}=wfCur;
  document.querySelectorAll('#wf-actions button').forEach(b=>b.disabled=true);
  try{
    const r=await fetch((kind==='leave'?lvAPI:pmAPI)+'&id='+d.id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,notes})});
    const j=await r.json();
    if(!j.success){wfErr(j.message||'Failed');return;}
    wfClose(); kind==='leave'?(lvLoad(),lvRenderMyBalance()):pmLoad();
  }catch(e){wfErr('Request failed');}
  finally{document.querySelectorAll('#wf-actions button').forEach(b=>b.disabled=false);}
}
async function wfDelete(){
  if(!confirm('Delete this request permanently?'))return;
  const {kind,d}=wfCur;
  try{const r=await fetch((kind==='leave'?lvAPI:pmAPI)+'&id='+d.id,{method:'DELETE'});const j=await r.json();if(!j.success){wfErr(j.message);return;}wfClose();kind==='leave'?lvLoad():pmLoad();}catch(e){wfErr('Failed');}
}
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.lv-overlay.show').forEach(o=>o.classList.remove('show'));});

<?php if ($lvMe['is_hr']): ?>
// Leave Types
async function ltLoad(){
  try{const r=await fetch(lvAPI+'&type=types');const j=await r.json();if(!j.success)return;
    const tb=document.getElementById('lt-tbody');
    if(!j.data.length){tb.innerHTML='<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);">No leave types found.</td></tr>';return;}
    tb.innerHTML=j.data.map(t=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:11px 14px;font-weight:600;">${lEsc(t.name)}</td>
      <td style="padding:11px 14px;font-family:monospace;font-size:13px;">${lEsc(t.code)}</td>
      <td style="padding:11px 14px;text-align:center;font-weight:700;">${+t.days_allowed||'No limit'}</td>
      <td style="padding:11px 14px;text-align:center;">${+t.is_paid?'<span style="color:var(--success-color);font-weight:600;">Yes</span>':'<span style="color:var(--danger-color);">No</span>'}</td>
      <td style="padding:11px 14px;text-align:center;">${+t.carry_forward?'<span style="color:var(--success-color);">Yes</span>':'No'}</td>
      <td style="padding:11px 14px;text-align:center;"><button onclick="ltDel(${t.id},'${lEsc(t.name).replace(/'/g,'&#39;')}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;"><i class="fas fa-trash"></i></button></td>
    </tr>`).join('');
  }catch(e){}
}
function ltOpenModal(){document.getElementById('lt-form').reset();document.getElementById('lt-alert').style.display='none';document.getElementById('lt-modal').classList.add('show');}
function ltCloseModal(){document.getElementById('lt-modal').classList.remove('show');}
async function ltDel(id,name){if(!confirm(`Delete leave type "${name}"?`))return;try{const r=await fetch(lvAPI+'&type=types&id='+id,{method:'DELETE'});const j=await r.json();j.success?ltLoad():alert(j.message);}catch(e){alert('Failed');}}
async function ltSubmit(e){
  e.preventDefault();const al=document.getElementById('lt-alert');al.style.display='none';
  const body={name:document.getElementById('lt-name').value.trim(),code:document.getElementById('lt-code').value.trim(),days_allowed:document.getElementById('lt-days').value,is_paid:document.getElementById('lt-paid').value};
  try{const r=await fetch(lvAPI+'&type=types',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){ltCloseModal();ltLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}}catch(err){al.textContent='Failed';al.style.display='block';}
}
<?php endif; ?>

lvInit();
if (_hrInitialTab === 'permissions') lvTab('permissions');
</script>


<?php elseif ($subModule == 'performance'): ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:18px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="pf-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total Reviews</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="pf-avg" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Avg Score</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="pf-excellent" style="font-size:22px;font-weight:700;color:#f59e0b;">—</div><div style="font-size:12px;color:var(--gray-500);">Excellent</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="pf-needs" style="font-size:22px;font-weight:700;color:var(--danger-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Needs Improvement</div></div>
</div>

<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="pf-period" type="text" placeholder="Period e.g. 2026-Q1" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;min-width:160px;">
  <select id="pf-filter-status" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Status</option><option value="draft">Draft</option><option value="submitted">Submitted</option><option value="acknowledged">Acknowledged</option>
  </select>
  <button onclick="pfLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="pfOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> New Review</button>
</div>

<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Period</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Reviewer</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Punctuality</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Quality</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Teamwork</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Comm.</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Initiative</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Overall</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Rating</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
    </tr></thead>
    <tbody id="pf-tbody"><tr><td colspan="11" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="pf-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 14px;"></div>
</div>

<!-- Performance Modal -->
<div id="pf-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:680px;margin:auto;position:relative;">
    <button onclick="pfCloseModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="pf-modal-title" style="font-size:17px;font-weight:600;margin-bottom:16px;">New Performance Review</h3>
    <div id="pf-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form id="pf-form" onsubmit="pfSubmit(event)">
      <input type="hidden" id="pf-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Employee <span style="color:var(--danger-color)">*</span></label>
          <select id="pf-emp" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Select Employee</option></select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Review Period <span style="color:var(--danger-color)">*</span></label>
          <input id="pf-period-inp" type="text" placeholder="e.g. 2026-Q1" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Reviewer Name</label>
          <input id="pf-reviewer" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      </div>
      <div style="background:var(--gray-50);border-radius:8px;padding:14px;margin-bottom:14px;">
        <div style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:12px;">Score each area 1 (Poor) → 5 (Excellent)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Punctuality</label>
            <select id="pf-s1" onchange="pfCalc()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">—</option><option value="1">1 - Poor</option><option value="2">2 - Below Average</option><option value="3">3 - Average</option><option value="4">4 - Good</option><option value="5">5 - Excellent</option>
            </select></div>
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Work Quality</label>
            <select id="pf-s2" onchange="pfCalc()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">—</option><option value="1">1 - Poor</option><option value="2">2 - Below Average</option><option value="3">3 - Average</option><option value="4">4 - Good</option><option value="5">5 - Excellent</option>
            </select></div>
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Teamwork</label>
            <select id="pf-s3" onchange="pfCalc()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">—</option><option value="1">1 - Poor</option><option value="2">2 - Below Average</option><option value="3">3 - Average</option><option value="4">4 - Good</option><option value="5">5 - Excellent</option>
            </select></div>
          <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Communication</label>
            <select id="pf-s4" onchange="pfCalc()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">—</option><option value="1">1 - Poor</option><option value="2">2 - Below Average</option><option value="3">3 - Average</option><option value="4">4 - Good</option><option value="5">5 - Excellent</option>
            </select></div>
          <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Initiative</label>
            <select id="pf-s5" onchange="pfCalc()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">—</option><option value="1">1 - Poor</option><option value="2">2 - Below Average</option><option value="3">3 - Average</option><option value="4">4 - Good</option><option value="5">5 - Excellent</option>
            </select></div>
        </div>
        <div id="pf-calc-result" style="margin-top:12px;padding:10px;background:white;border-radius:8px;text-align:center;font-size:14px;font-weight:600;color:var(--gray-700);">Overall score will appear here</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Strengths</label>
          <textarea id="pf-strengths" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Areas to Improve</label>
          <textarea id="pf-improvements" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Goals for Next Period</label>
          <textarea id="pf-goals" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Status</label>
          <select id="pf-status" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="draft">Draft</option><option value="submitted">Submitted</option><option value="acknowledged">Acknowledged</option>
          </select></div>
      </div>
      <div style="display:flex;gap:10px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="pf-submit-btn" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> <span id="pf-submit-lbl">Save Review</span></button>
        <button type="button" onclick="pfCloseModal()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const pfAPI=_apiBase+'?endpoint=hr/performance';
const pfEmpAPI=_apiBase+'?endpoint=hr/employees';
let pfPage=1;
function pEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
const pfRating={excellent:'#f59e0b',good:'var(--success-color)',satisfactory:'var(--primary-color)','needs-improvement':'var(--warning-color)',unsatisfactory:'var(--danger-color)'};
const pfStars=n=>n?'★'.repeat(n)+'☆'.repeat(5-n):'—';

async function pfInit(){
  try{const r=await fetch(pfEmpAPI+'&limit=500');const j=await r.json();if(!j.success)return;
    document.getElementById('pf-emp').innerHTML='<option value="">Select Employee</option>'+j.data.employees.map(e=>`<option value="${e.id}">${pEsc(e.first_name+' '+e.last_name)} (${pEsc(e.employee_no)})</option>`).join('');
  }catch(e){}pfLoad();
}
function pfCalc(){
  const vals=[1,2,3,4,5].map(i=>parseInt(document.getElementById('pf-s'+i).value)||0).filter(v=>v>0);
  if(!vals.length){document.getElementById('pf-calc-result').textContent='Overall score will appear here';return;}
  const avg=(vals.reduce((a,b)=>a+b,0)/vals.length).toFixed(1);
  const labels={'5':'Excellent','4':'Good','3':'Satisfactory','2':'Needs Improvement','1':'Unsatisfactory'};
  const r=avg>=4.5?'Excellent':avg>=3.5?'Good':avg>=2.5?'Satisfactory':avg>=1.5?'Needs Improvement':'Unsatisfactory';
  document.getElementById('pf-calc-result').innerHTML=`Overall: <span style="font-size:20px;color:${pfRating[r.toLowerCase().replace(' ','-')]||'#555'}">${avg}</span> / 5.0 — <strong>${r}</strong>`;
}
async function pfLoad(){
  const p=new URLSearchParams({page:pfPage,limit:20,period:document.getElementById('pf-period').value,status:document.getElementById('pf-filter-status').value});
  try{
    const r=await fetch(pfAPI+'&'+p);const j=await r.json();if(!j.success){pfMsg('Failed');return;}
    const{reviews,stats,pagination}=j.data;
    document.getElementById('pf-total').textContent=stats.total||0;
    document.getElementById('pf-avg').textContent=stats.avg_score||'—';
    document.getElementById('pf-excellent').textContent=stats.excellent||0;
    document.getElementById('pf-needs').textContent=stats.needs_improvement||0;
    const tb=document.getElementById('pf-tbody');
    if(!reviews.length){tb.innerHTML='<tr><td colspan="11" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-chart-bar" style="font-size:36px;display:block;margin-bottom:8px;"></i>No performance reviews found.</td></tr>';return;}
    tb.innerHTML=reviews.map(r=>`<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 14px;"><div style="font-weight:600;">${pEsc(r.first_name)} ${pEsc(r.last_name)}</div><div style="font-size:12px;color:var(--primary-color);font-family:monospace;">${pEsc(r.employee_no)}</div></td>
      <td style="padding:10px 14px;">${pEsc(r.review_period)}</td>
      <td style="padding:10px 14px;font-size:13px;">${pEsc(r.reviewer_name||'—')}</td>
      <td style="padding:10px 14px;text-align:center;color:#f59e0b;">${pfStars(r.punctuality_score)}</td>
      <td style="padding:10px 14px;text-align:center;color:#f59e0b;">${pfStars(r.quality_score)}</td>
      <td style="padding:10px 14px;text-align:center;color:#f59e0b;">${pfStars(r.teamwork_score)}</td>
      <td style="padding:10px 14px;text-align:center;color:#f59e0b;">${pfStars(r.communication_score)}</td>
      <td style="padding:10px 14px;text-align:center;color:#f59e0b;">${pfStars(r.initiative_score)}</td>
      <td style="padding:10px 14px;text-align:center;font-weight:700;font-size:16px;">${r.overall_score||'—'}</td>
      <td style="padding:10px 14px;"><span style="padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;background:${pfRating[r.rating]||'#ccc'}20;color:${pfRating[r.rating]||'#555'};">${r.rating||'—'}</span></td>
      <td style="padding:10px 14px;text-align:center;">
        <button onclick="pfEdit(${r.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;padding:3px;"><i class="fas fa-edit"></i></button>
        <button onclick="pfDel(${r.id})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;padding:3px;"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('');
    const pg=document.getElementById('pf-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="pfPage=${i+1};pfLoad()" style="padding:5px 10px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;">${i+1}</button>`).join(''):'';
  }catch(e){pfMsg('Error: '+e.message);}
}
function pfMsg(m){document.getElementById('pf-tbody').innerHTML=`<tr><td colspan="11" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}
function pfOpenModal(d=null){
  document.getElementById('pf-id').value=d?d.id:'';
  document.getElementById('pf-emp').value=d?d.employee_id:'';
  document.getElementById('pf-period-inp').value=d?d.review_period:'';
  document.getElementById('pf-reviewer').value=d?d.reviewer_name:'';
  [1,2,3,4,5].forEach((i,idx)=>{const keys=['punctuality_score','quality_score','teamwork_score','communication_score','initiative_score'];document.getElementById('pf-s'+i).value=d?d[keys[idx]]:''});
  document.getElementById('pf-strengths').value=d?d.strengths:'';
  document.getElementById('pf-improvements').value=d?d.improvements:'';
  document.getElementById('pf-goals').value=d?d.goals:'';
  document.getElementById('pf-status').value=d?d.status:'draft';
  document.getElementById('pf-modal-title').textContent=d?'Edit Performance Review':'New Performance Review';
  document.getElementById('pf-submit-lbl').textContent=d?'Update Review':'Save Review';
  document.getElementById('pf-alert').style.display='none';
  pfCalc();
  document.getElementById('pf-modal').style.display='flex';
}
function pfCloseModal(){document.getElementById('pf-modal').style.display='none';}
async function pfEdit(id){try{const r=await fetch(pfAPI+'&id='+id);const j=await r.json();if(j.success)pfOpenModal(j.data);}catch(e){alert('Load failed');}}
async function pfDel(id){if(!confirm('Delete this review?'))return;try{const r=await fetch(pfAPI+'&id='+id,{method:'DELETE'});const j=await r.json();j.success?pfLoad():alert(j.message);}catch(e){alert('Failed');}}
async function pfSubmit(e){
  e.preventDefault();const id=document.getElementById('pf-id').value;const btn=document.getElementById('pf-submit-btn');const al=document.getElementById('pf-alert');al.style.display='none';btn.disabled=true;
  const body={employee_id:document.getElementById('pf-emp').value,review_period:document.getElementById('pf-period-inp').value.trim(),reviewer_name:document.getElementById('pf-reviewer').value.trim(),punctuality_score:document.getElementById('pf-s1').value,quality_score:document.getElementById('pf-s2').value,teamwork_score:document.getElementById('pf-s3').value,communication_score:document.getElementById('pf-s4').value,initiative_score:document.getElementById('pf-s5').value,strengths:document.getElementById('pf-strengths').value.trim(),improvements:document.getElementById('pf-improvements').value.trim(),goals:document.getElementById('pf-goals').value.trim(),status:document.getElementById('pf-status').value};
  try{const r=await fetch(id?pfAPI+'&id='+id:pfAPI,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const j=await r.json();if(j.success){pfCloseModal();pfLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}}
  catch(err){al.textContent='Request failed';al.style.display='block';}finally{btn.disabled=false;}
}
pfInit();
</script>

<?php elseif ($subModule == 'recruitment'): ?>

<?php if ($action == 'add'): ?>
<!-- ADD JOB VACANCY FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-briefcase" style="color: var(--success-color);"></i> Create Job Vacancy
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Job Title <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., Senior Cardiologist" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Department <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Department</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Employment Type <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Type</option>
                    <option value="full-time">Full-Time</option>
                    <option value="part-time">Part-Time</option>
                    <option value="contract">Contract</option>
                    <option value="temporary">Temporary</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Positions Available <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Number of positions" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Experience Required
                </label>
                <input type="text" placeholder="e.g., 5+ years" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Salary Range
                </label>
                <input type="text" placeholder="e.g., $50,000 - $70,000" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Job Description <span style="color: var(--danger-color);">*</span>
                </label>
                <textarea rows="4" placeholder="Enter detailed job description" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Requirements
                </label>
                <textarea rows="3" placeholder="Enter job requirements" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-briefcase"></i> Post Vacancy
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- RECRUITMENT LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Job Vacancies</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No job vacancies posted. Create a vacancy to start recruitment.</p>
        </div>
        <button onclick="window.location.href='hr.php?module=hr&sub=recruitment&action=add'" style="padding: 10px 20px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Post Vacancy
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-briefcase"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Job Vacancies</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Post a job vacancy to start the recruitment process.</p>
        <button onclick="window.location.href='hr.php?module=hr&sub=recruitment&action=add'" style="padding: 12px 24px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Post First Vacancy
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
LICENSE TRACKING MODULE
============================================ -->
<?php elseif ($subModule == 'license-tracking'): ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:18px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="lc-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="lc-active" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Active</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="lc-expired" style="font-size:22px;font-weight:700;color:var(--danger-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Expired</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="lc-soon" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Expiring ≤30d</div></div>
</div>

<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="lc-filter-status" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Status</option>
    <option value="active">Active</option>
    <option value="expired">Expired</option>
    <option value="pending-renewal">Pending Renewal</option>
    <option value="revoked">Revoked</option>
  </select>
  <label style="display:flex;align-items:center;gap:6px;font-size:14px;color:var(--gray-700);cursor:pointer;">
    <input type="checkbox" id="lc-expiring-only" onchange="lcLoad()" style="width:16px;height:16px;cursor:pointer;">
    Expiring in 30 days
  </label>
  <button onclick="lcLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="lcOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> Add License</button>
</div>

<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">License / Cert</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Type</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Issuing Body</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Issue Date</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Expiry Date</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Days Left</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Doc</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
    </tr></thead>
    <tbody id="lc-tbody"><tr><td colspan="10" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="lc-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 14px;"></div>
</div>

<!-- License Modal -->
<div id="lc-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;position:relative;">
    <button onclick="lcCloseModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="lc-modal-title" style="font-size:17px;font-weight:600;margin-bottom:16px;">Add License / Certification</h3>
    <div id="lc-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form id="lc-form" onsubmit="lcSubmit(event)">
      <input type="hidden" id="lc-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Employee <span style="color:var(--danger-color)">*</span></label>
          <select id="lc-emp" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Select Employee</option></select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">License Type <span style="color:var(--danger-color)">*</span></label>
          <select id="lc-type" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="Medical License">Medical License</option>
            <option value="Nursing License">Nursing License</option>
            <option value="Pharmacy License">Pharmacy License</option>
            <option value="Lab Technician">Lab Technician</option>
            <option value="Driver License">Driver License</option>
            <option value="Certificate">Certificate</option>
            <option value="CPD Certificate">CPD Certificate</option>
            <option value="Other">Other</option>
          </select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">License/Cert Name <span style="color:var(--danger-color)">*</span></label>
          <input id="lc-name" type="text" placeholder="Full name of the license" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">License Number</label>
          <input id="lc-number" type="text" placeholder="Reference / registration no." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Issuing Body</label>
          <input id="lc-issuer" type="text" placeholder="e.g. Rwanda Medical Council" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Issue Date</label>
          <input id="lc-issued" type="date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Expiry Date</label>
          <input id="lc-expiry" type="date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Status</label>
          <select id="lc-status" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="active">Active</option>
            <option value="expired">Expired</option>
            <option value="pending-renewal">Pending Renewal</option>
            <option value="revoked">Revoked</option>
          </select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Remind Before (days)</label>
          <input id="lc-reminder" type="number" min="1" value="30" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Notes</label>
          <textarea id="lc-notes" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">License Document <span style="color:var(--gray-400);font-weight:400;">(PDF, JPG, PNG — max 5MB)</span></label>
          <input id="lc-file" type="file" accept=".pdf,.jpg,.jpeg,.png" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
          <div id="lc-current-file" style="display:none;margin-top:6px;font-size:12px;color:var(--primary-color);"><i class="fas fa-file-pdf"></i> <span id="lc-current-file-name"></span> <a id="lc-current-file-link" href="#" target="_blank" style="margin-left:8px;color:var(--success-color);text-decoration:none;"><i class="fas fa-external-link-alt"></i> View</a></div>
        </div>
        <div id="lc-change-reason-wrap" style="grid-column:1/-1;display:none;"><label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Change Reason <span style="color:var(--gray-400);font-weight:400;">(for version history)</span></label>
          <input id="lc-change-reason" type="text" placeholder="e.g. Renewed, Updated number, Corrected dates..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="lc-submit-btn" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> <span id="lc-submit-lbl">Save License</span></button>
        <button type="button" onclick="lcCloseModal()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Version History Modal -->
<div id="lc-ver-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1001;align-items:center;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:900px;max-height:85vh;overflow-y:auto;position:relative;margin:auto;">
    <button onclick="lcCloseVerModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="lc-ver-title" style="font-size:17px;font-weight:600;margin-bottom:16px;"><i class="fas fa-history" style="color:#8b5cf6;margin-right:8px;"></i>Version History</h3>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:14px;">Each update to this license is tracked as a new version. The most recent version is shown first.</p>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
          <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);white-space:nowrap;">Version</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Type</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Number</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Issuer</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Issued</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Expiry</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Change Reason</th>
        </tr></thead>
        <tbody id="lc-ver-tbody"><tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);">Loading...</td></tr></tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button type="button" onclick="lcCloseVerModal()" style="padding:10px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Close</button>
    </div>
  </div>
</div>

<script>
const lcAPI=_apiBase+'?endpoint=hr/licenses';
const lcEmpAPI=_apiBase+'?endpoint=hr/employees';
let lcPage=1;
function cEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
const lcSC={active:'var(--success-color)',expired:'var(--danger-color)','pending-renewal':'var(--warning-color)',revoked:'var(--gray-400)'};

async function lcInit(){
  try{const r=await fetch(lcEmpAPI+'&limit=500');const j=await r.json();if(!j.success)return;
    document.getElementById('lc-emp').innerHTML='<option value="">Select Employee</option>'+j.data.employees.map(e=>`<option value="${e.id}">${cEsc(e.first_name+' '+e.last_name)} (${cEsc(e.employee_no)})</option>`).join('');
  }catch(e){}lcLoad();
}
async function lcLoad(){
  const expiring=document.getElementById('lc-expiring-only').checked?30:'';
  const p=new URLSearchParams({page:lcPage,limit:20,status:document.getElementById('lc-filter-status').value,expiring});
  try{
    const r=await fetch(lcAPI+'&'+p);const j=await r.json();if(!j.success){lcMsg('Failed');return;}
    const{licenses,stats,pagination}=j.data;
    document.getElementById('lc-total').textContent=stats.total||0;
    document.getElementById('lc-active').textContent=stats.active||0;
    document.getElementById('lc-expired').textContent=stats.expired||0;
    document.getElementById('lc-soon').textContent=stats.expiring_soon||0;
    const tb=document.getElementById('lc-tbody');
    if(!licenses.length){tb.innerHTML='<tr><td colspan="10" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-id-badge" style="font-size:36px;display:block;margin-bottom:8px;"></i>No licenses found.</td></tr>';return;}
    tb.innerHTML=licenses.map(l=>{
      const days=parseInt(l.days_to_expiry);
      const daysLabel=isNaN(days)||!l.expiry_date?'—':days<0?`<span style="color:var(--danger-color);font-weight:700;">Expired ${Math.abs(days)}d ago</span>`:days<=30?`<span style="color:var(--warning-color);font-weight:700;">${days}d</span>`:`<span style="color:var(--success-color);">${days}d</span>`;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 14px;"><div style="font-weight:600;">${cEsc(l.first_name)} ${cEsc(l.last_name)}</div><div style="font-size:12px;color:var(--primary-color);font-family:monospace;">${cEsc(l.employee_no)}</div></td>
        <td style="padding:10px 14px;"><div style="font-weight:600;">${cEsc(l.license_name)}</div><div style="font-size:12px;color:var(--gray-500);">${cEsc(l.license_number||'—')}</div></td>
        <td style="padding:10px 14px;font-size:13px;">${cEsc(l.license_type)}</td>
        <td style="padding:10px 14px;font-size:13px;">${cEsc(l.issuing_body||'—')}</td>
        <td style="padding:10px 14px;font-size:13px;">${l.issue_date||'—'}</td>
        <td style="padding:10px 14px;font-size:13px;">${l.expiry_date||'—'}</td>
        <td style="padding:10px 14px;font-size:13px;">${daysLabel}</td>
        <td style="padding:10px 14px;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${lcSC[l.status]||'#ccc'}20;color:${lcSC[l.status]||'#555'};">${l.status}</span></td>
        <td style="padding:10px 14px;text-align:center;font-size:13px;">${l.document_path?`<a href="${cEsc(l.document_path)}" target="_blank" style="color:var(--primary-color);text-decoration:none;" title="View Document"><i class="fas fa-file-pdf" style="font-size:16px;"></i></a>`:'<span style="color:var(--gray-300);">—</span>'}</td>
        <td style="padding:10px 14px;text-align:center;">
          <button onclick="lcVersions(${l.id},'${cEsc(l.license_name)}')" style="background:none;border:none;color:#8b5cf6;cursor:pointer;font-size:15px;padding:3px;" title="Version History"><i class="fas fa-history"></i></button>
          <button onclick="lcEdit(${l.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;padding:3px;" title="Edit"><i class="fas fa-edit"></i></button>
          <button onclick="lcDel(${l.id})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;padding:3px;" title="Delete"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join('');
    const pg=document.getElementById('lc-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="lcPage=${i+1};lcLoad()" style="padding:5px 10px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;">${i+1}</button>`).join(''):'';
  }catch(e){lcMsg('Error: '+e.message);}
}
function lcMsg(m){document.getElementById('lc-tbody').innerHTML=`<tr><td colspan="10" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}
function lcOpenModal(d=null){
  document.getElementById('lc-id').value=d?d.id:'';
  document.getElementById('lc-emp').value=d?d.employee_id:'';
  document.getElementById('lc-type').value=d?d.license_type:'Medical License';
  document.getElementById('lc-name').value=d?d.license_name:'';
  document.getElementById('lc-number').value=d?d.license_number:'';
  document.getElementById('lc-issuer').value=d?d.issuing_body:'';
  document.getElementById('lc-issued').value=d?d.issue_date:'';
  document.getElementById('lc-expiry').value=d?d.expiry_date:'';
  document.getElementById('lc-status').value=d?d.status:'active';
  document.getElementById('lc-reminder').value=d?d.reminder_days:30;
  document.getElementById('lc-notes').value=d?d.notes:'';
  document.getElementById('lc-change-reason').value='';
  document.getElementById('lc-change-reason-wrap').style.display=d?'block':'none';
  document.getElementById('lc-file').value='';
  // Show current file if exists
  if(d && d.document_path){
    document.getElementById('lc-current-file').style.display='block';
    document.getElementById('lc-current-file-name').textContent=d.document_path.split('/').pop();
    document.getElementById('lc-current-file-link').href=d.document_path;
  }else{
    document.getElementById('lc-current-file').style.display='none';
  }
  document.getElementById('lc-modal-title').textContent=d?'Edit License':'Add License / Certification';
  document.getElementById('lc-submit-lbl').textContent=d?'Update':'Save License';
  document.getElementById('lc-alert').style.display='none';
  document.getElementById('lc-modal').style.display='flex';
}
function lcCloseModal(){document.getElementById('lc-modal').style.display='none';}
async function lcEdit(id){try{const r=await fetch(lcAPI+'&id='+id);const j=await r.json();if(j.success)lcOpenModal(j.data);}catch(e){alert('Load failed');}}
async function lcDel(id){if(!confirm('Delete this license and all its version history?'))return;try{const r=await fetch(lcAPI+'&id='+id,{method:'DELETE'});const j=await r.json();j.success?lcLoad():alert(j.message);}catch(e){alert('Failed');}}
async function lcSubmit(e){
  e.preventDefault();const id=document.getElementById('lc-id').value;
  const btn=document.getElementById('lc-submit-btn');const al=document.getElementById('lc-alert');al.style.display='none';btn.disabled=true;
  const fd=new FormData();
  fd.append('employee_id',document.getElementById('lc-emp').value);
  fd.append('license_type',document.getElementById('lc-type').value);
  fd.append('license_name',document.getElementById('lc-name').value.trim());
  fd.append('license_number',document.getElementById('lc-number').value.trim());
  fd.append('issuing_body',document.getElementById('lc-issuer').value.trim());
  fd.append('issue_date',document.getElementById('lc-issued').value||'');
  fd.append('expiry_date',document.getElementById('lc-expiry').value||'');
  fd.append('status',document.getElementById('lc-status').value);
  fd.append('reminder_days',document.getElementById('lc-reminder').value);
  fd.append('notes',document.getElementById('lc-notes').value.trim());
  fd.append('change_reason',document.getElementById('lc-change-reason').value.trim());
  const fileInput=document.getElementById('lc-file');
  if(fileInput.files[0]) fd.append('license_document',fileInput.files[0]);
  if(id) fd.append('_method','PUT');
  try{const r=await fetch(id?lcAPI+'&id='+id:lcAPI,{method:'POST',body:fd});const j=await r.json();if(j.success){lcCloseModal();lcLoad();}else{al.textContent=j.message||'Failed';al.style.display='block';}}
  catch(err){al.textContent='Request failed';al.style.display='block';}finally{btn.disabled=false;}
}

// Version History
async function lcVersions(id, name){
  document.getElementById('lc-ver-title').textContent='Version History: '+name;
  document.getElementById('lc-ver-tbody').innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
  document.getElementById('lc-ver-modal').style.display='flex';
  try{
    const r=await fetch(lcAPI+'&id='+id+'&action=versions');
    const j=await r.json();
    if(!j.success){document.getElementById('lc-ver-tbody').innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:var(--danger-color);">Failed to load versions</td></tr>';return;}
    const versions=j.data.versions;
    if(!versions.length){document.getElementById('lc-ver-tbody').innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);">No version history available</td></tr>';return;}
    document.getElementById('lc-ver-tbody').innerHTML=versions.map((v,idx)=>{
      const isCurrent=idx===0;
      return `<tr style="border-bottom:1px solid var(--gray-100);${isCurrent?'background:#f0fdf4;':''}">
        <td style="padding:9px 12px;font-weight:700;text-align:center;">${isCurrent?'<span style="background:var(--success-color);color:white;padding:2px 8px;border-radius:10px;font-size:11px;">v'+v.version_number+'</span>':'v'+v.version_number}</td>
        <td style="padding:9px 12px;font-size:13px;">${cEsc(v.license_type)}</td>
        <td style="padding:9px 12px;font-size:13px;">${cEsc(v.license_number||'—')}</td>
        <td style="padding:9px 12px;font-size:13px;">${cEsc(v.issuing_body||'—')}</td>
        <td style="padding:9px 12px;font-size:13px;">${v.issue_date||'—'}</td>
        <td style="padding:9px 12px;font-size:13px;">${v.expiry_date||'—'}</td>
        <td style="padding:9px 12px;"><span style="padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;background:${lcSC[v.status]||'#ccc'}20;color:${lcSC[v.status]||'#555'};">${v.status}</span></td>
        <td style="padding:9px 12px;font-size:12px;color:var(--gray-600);"><div>${cEsc(v.change_reason||'—')}</div><div style="color:var(--gray-400);margin-top:2px;font-size:11px;">${v.created_at||''}</div></td>
      </tr>`;
    }).join('');
  }catch(e){document.getElementById('lc-ver-tbody').innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:var(--danger-color);">Error: '+e.message+'</td></tr>';}
}
function lcCloseVerModal(){document.getElementById('lc-ver-modal').style.display='none';}

lcInit();
</script>

<!-- ============================================
CONTRACTS MODULE
============================================ -->
<?php elseif ($subModule == 'contracts'): ?>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:white;padding:6px;border-radius:10px;box-shadow:var(--shadow);width:fit-content;">
  <button id="cd-tab-contracts" onclick="cdTab('contracts')" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:var(--primary-color);color:white;">Contracts</button>
  <button id="cd-tab-documents" onclick="cdTab('documents')" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:transparent;color:var(--gray-600);">Documents</button>
</div>

<!-- ===== CONTRACTS PANEL ===== -->
<div id="cd-panel-contracts">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:18px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="ct-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="ct-active" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Active</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="ct-inactive" style="font-size:22px;font-weight:700;color:var(--danger-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Inactive</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="ct-expiring" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Expiring ≤30d</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="ct-type-filter" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;">
    <option value="">All Types</option><option value="permanent">Permanent</option><option value="fixed-term">Fixed Term</option><option value="temporary">Temporary</option><option value="internship">Internship</option><option value="volunteer">Volunteer</option>
  </select>
  <select id="ct-status-filter" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;">
    <option value="">All Status</option><option value="1">Active</option><option value="0">Inactive</option>
  </select>
  <button onclick="ctLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="ctOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> New Contract</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Contract #</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Job Title</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Start</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">End</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">Salary</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Doc</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="ct-tbody"><tr><td colspan="10" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="ct-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 14px;"></div>
</div>
</div>

<!-- ===== DOCUMENTS PANEL ===== -->
<div id="cd-panel-documents" style="display:none;">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:18px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="dc-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="dc-diplomas" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Diplomas</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="dc-certs" style="font-size:22px;font-weight:700;color:#8b5cf6;">—</div><div style="font-size:12px;color:var(--gray-500);">Certificates</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="dc-other" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Other</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="dc-type-filter" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;">
    <option value="">All Types</option><option value="Diploma">Diploma</option><option value="Certificate">Certificate</option><option value="ID Copy">ID Copy</option><option value="CV/Resume">CV/Resume</option><option value="Reference Letter">Reference Letter</option><option value="Other">Other</option>
  </select>
  <button onclick="dcLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="dcOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> Upload Document</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Document Name</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Size</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Uploaded</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Ver</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">File</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="dc-tbody"><tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="dc-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 14px;"></div>
</div>
</div>

<!-- Contract Modal -->
<div id="ct-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:680px;margin:auto;position:relative;">
    <button onclick="ctCloseModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="ct-modal-title" style="font-size:17px;font-weight:600;margin-bottom:16px;">New Contract</h3>
    <div id="ct-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form id="ct-form" onsubmit="ctSubmit(event)">
      <input type="hidden" id="ct-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Employee *</label>
          <select id="ct-emp" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Select Employee</option></select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Contract Type *</label>
          <select id="ct-type" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="permanent">Permanent</option><option value="fixed-term">Fixed Term</option><option value="temporary">Temporary</option><option value="internship">Internship</option><option value="volunteer">Volunteer</option></select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Job Title *</label>
          <input id="ct-job" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Start Date *</label>
          <input id="ct-start" type="date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">End Date</label>
          <input id="ct-end" type="date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Department</label>
          <select id="ct-dept" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Select</option></select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Gross Salary (RWF)</label>
          <input id="ct-salary" type="number" min="0" value="0" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Work Location</label>
          <input id="ct-location" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Status</label>
          <select id="ct-active" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="1">Active</option><option value="0">Inactive</option></select></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Contract PDF <span style="color:var(--gray-400);font-weight:400;">(PDF, DOC — max 10MB)</span></label>
          <input id="ct-file" type="file" accept=".pdf,.doc,.docx" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
          <div id="ct-current-file" style="display:none;margin-top:4px;font-size:12px;color:var(--primary-color);"><i class="fas fa-file-pdf"></i> <span id="ct-current-file-name"></span></div></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Notes</label>
          <textarea id="ct-notes" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
        <div id="ct-reason-wrap" style="grid-column:1/-1;display:none;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Change Reason</label>
          <input id="ct-reason" type="text" placeholder="e.g. Renewed, Amended salary..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="ct-submit-btn" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> <span id="ct-submit-lbl">Save Contract</span></button>
        <button type="button" onclick="ctCloseModal()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Document Upload Modal -->
<div id="dc-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:520px;position:relative;">
    <button onclick="dcCloseModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="dc-modal-title" style="font-size:17px;font-weight:600;margin-bottom:16px;">Upload Document</h3>
    <div id="dc-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form id="dc-form" onsubmit="dcSubmit(event)">
      <input type="hidden" id="dc-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Employee *</label>
          <select id="dc-emp" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;"><option value="">Select</option></select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Document Type *</label>
          <select id="dc-type" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="Diploma">Diploma</option><option value="Certificate">Certificate</option><option value="ID Copy">ID Copy</option><option value="CV/Resume">CV/Resume</option><option value="Reference Letter">Reference Letter</option><option value="Other">Other</option></select></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Document Name *</label>
          <input id="dc-name" type="text" placeholder="e.g. Bachelor's Degree in Nursing" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">File * <span style="color:var(--gray-400);font-weight:400;">(PDF, JPG, PNG, DOC — max 10MB)</span></label>
          <input id="dc-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
          <div id="dc-current-file" style="display:none;margin-top:4px;font-size:12px;color:var(--primary-color);"><i class="fas fa-file"></i> <span id="dc-current-file-name"></span></div></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Notes</label>
          <textarea id="dc-notes" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
        <div id="dc-reason-wrap" style="grid-column:1/-1;display:none;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Change Reason</label>
          <input id="dc-reason" type="text" placeholder="e.g. Updated copy, Re-scanned..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="dc-submit-btn" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-upload"></i> <span id="dc-submit-lbl">Upload</span></button>
        <button type="button" onclick="dcCloseModal()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Version History Modal (shared) -->
<div id="cd-ver-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1001;align-items:center;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:26px;width:100%;max-width:800px;max-height:85vh;overflow-y:auto;position:relative;margin:auto;">
    <button onclick="document.getElementById('cd-ver-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="cd-ver-title" style="font-size:17px;font-weight:600;margin-bottom:14px;"><i class="fas fa-history" style="color:#8b5cf6;margin-right:8px;"></i>Version History</h3>
    <div id="cd-ver-content" style="overflow-x:auto;">Loading...</div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px;padding-top:12px;border-top:1px solid var(--gray-200);">
      <button onclick="document.getElementById('cd-ver-modal').style.display='none'" style="padding:10px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Close</button>
    </div>
  </div>
</div>

<script src="assets/js/contracts-documents.js"></script>

<!-- TRAINING MODULE -->
<?php elseif ($subModule == 'training'): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="tr-programs" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Programs</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="tr-enrolled" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Enrolled</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="tr-ongoing" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Ongoing</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="tr-completed" style="font-size:22px;font-weight:700;color:var(--gray-500);">—</div><div style="font-size:12px;color:var(--gray-500);">Completed</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="tr-view" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;">
    <option value="programs">Training Programs</option>
    <option value="enrollments">Enrollments</option>
  </select>
  <button onclick="trLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-sync"></i> Refresh</button>
  <button onclick="trOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> New Program</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Title</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Provider</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Dates</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Hours</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="tr-tbody"><tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<!-- SHIFT & DUTY ROSTER MODULE -->
<?php elseif ($subModule == 'shifts'): ?>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;background:white;padding:6px;border-radius:10px;box-shadow:var(--shadow);width:fit-content;">
  <button id="sh-tab-types" onclick="shTab('types')" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:var(--primary-color);color:white;">Shift Types</button>
  <button id="sh-tab-rosters" onclick="shTab('rosters')" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:transparent;color:var(--gray-600);">Duty Rosters</button>
  <button id="sh-tab-assignments" onclick="shTab('assignments')" style="padding:8px 20px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:transparent;color:var(--gray-600);">Assignments</button>
</div>

<!-- ===== SHIFT TYPES PANEL ===== -->
<div id="sh-panel-types">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:18px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="st-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total Shifts</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="st-active" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Active</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="st-night" style="font-size:22px;font-weight:700;color:#8b5cf6;">—</div><div style="font-size:12px;color:var(--gray-500);">Night Shifts</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;justify-content:flex-end;">
  <button onclick="stOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-plus"></i> Add Shift Type</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Name</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Code</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Time</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Hours</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Category</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">Allowance</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="st-tbody"><tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>
</div>

<!-- ===== ROSTERS PANEL ===== -->
<div id="sh-panel-rosters" style="display:none;">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:18px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rt-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rt-active" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Active</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rt-draft" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Draft</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="rt-completed" style="font-size:22px;font-weight:700;color:var(--gray-500);">—</div><div style="font-size:12px;color:var(--gray-500);">Completed</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="rt-filter-status" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Status</option><option value="draft">Draft</option><option value="published">Published</option><option value="active">Active</option><option value="completed">Completed</option>
  </select>
  <button onclick="rtLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="rtOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> New Roster</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Roster Name</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Department</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Period</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="rt-tbody"><tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="rt-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 14px;"></div>
</div>
</div>

<!-- ===== ASSIGNMENTS PANEL ===== -->
<div id="sh-panel-assignments" style="display:none;">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;margin-bottom:18px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="as-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="as-scheduled" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Scheduled</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="as-completed" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Completed</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="as-absent" style="font-size:22px;font-weight:700;color:var(--danger-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Absent</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="as-filter-roster" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:160px;"><option value="">All Rosters</option></select>
  <select id="as-filter-status" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Status</option><option value="scheduled">Scheduled</option><option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="absent">Absent</option><option value="cancelled">Cancelled</option>
  </select>
  <input id="as-filter-date" type="date" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <button onclick="asLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="asOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> Assign Shift</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Roster</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Shift</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Date</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Time</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">OT</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="as-tbody"><tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
  <div id="as-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:10px 14px;"></div>
</div>
</div>

<script src="assets/js/shifts.js"></script>

<!-- DISCIPLINARY MODULE -->
<?php elseif ($subModule == 'disciplinary'): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="dc-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Total Cases</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="dc-investigating" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Investigating</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="dc-resolved" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Resolved</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="dc-grave" style="font-size:22px;font-weight:700;color:var(--danger-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Grave Cases</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="dc-filter" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;">
    <option value="">All Status</option>
    <option value="reported">Reported</option>
    <option value="investigating">Investigating</option>
    <option value="hearing-scheduled">Hearing Scheduled</option>
    <option value="resolved">Resolved</option>
    <option value="closed">Closed</option>
  </select>
  <button onclick="dcLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="dcOpenModal()" style="padding:9px 16px;background:var(--danger-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> New Case</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Case #</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Date</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Severity</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="dc-tbody"><tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<!-- COMMUNICATIONS MODULE -->
<?php elseif ($subModule == 'communications'): ?>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="cm-view" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;">
    <option value="announcements">Announcements</option>
    <option value="messages">Messages</option>
  </select>
  <button onclick="cmLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-sync"></i> Refresh</button>
  <button onclick="cmOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> New Post</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Title</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Priority</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Posted</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Valid</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="cm-tbody"><tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<!-- GOALS & TASKS MODULE -->
<?php elseif ($subModule == 'goals-tasks'): ?>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="gt-view" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;">
    <option value="goals">Goals & KPIs</option>
    <option value="tasks">Tasks</option>
    <option value="pip">Performance Improvement Plans</option>
  </select>
  <button onclick="gtLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-sync"></i> Refresh</button>
  <button onclick="gtOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> Create</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Title</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Employee</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Type</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Target Date</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="gt-tbody"><tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<!-- INTERNS -->
<?php elseif ($subModule == 'interns'): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="in-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Interns</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="in-active" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Active</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="in-expiring" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Ending in 30 days</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="in-completed" style="font-size:22px;font-weight:700;color:var(--gray-500);">—</div><div style="font-size:12px;color:var(--gray-500);">Placement ended</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="in-search" type="search" placeholder="Search name or number…" oninput="inLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;min-width:220px;">
  <select id="in-dept" onchange="inLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;background:white;"><option value="">All Departments</option></select>
  <button onclick="inOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> Add Intern</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Intern</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Department</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Start</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">End</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Duration</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Supervisor</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="in-tbody"><tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<!-- CASUAL WORKERS -->
<?php elseif ($subModule == 'casual-workers'): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="cw-total" style="font-size:22px;font-weight:700;color:var(--primary-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Casual workers</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="cw-active" style="font-size:22px;font-weight:700;color:var(--success-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Active</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="cw-daily" style="font-size:22px;font-weight:700;color:var(--warning-color);">—</div><div style="font-size:12px;color:var(--gray-500);">Paid per day</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;"><div id="cw-hourly" style="font-size:22px;font-weight:700;color:#7c3aed;">—</div><div style="font-size:12px;color:var(--gray-500);">Paid per hour</div></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="cw-search" type="search" placeholder="Search name…" oninput="cwLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;min-width:220px;">
  <select id="cw-filter" onchange="cwLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;background:white;">
    <option value="">Any pay basis</option><option value="daily">Per day</option><option value="hourly">Per hour</option><option value="weekly">Per week</option><option value="monthly">Per month</option>
  </select>
  <button onclick="cwOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-plus"></i> Add Casual Worker</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Worker</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Department</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Paid per</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">Rate (RWF)</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">Max h / week</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Engagement ends</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="cw-tbody"><tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<!-- INTERN & CASUAL PAYROLL -->
<?php elseif ($subModule == 'intern-casual-payroll'): ?>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="ic-period" type="month" onchange="icLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;">
  <select id="ic-type" onchange="icLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;background:white;">
    <option value="">Interns and casual workers</option><option value="intern">Interns</option><option value="casual-worker">Casual workers</option>
  </select>
  <button onclick="icExport()" style="padding:9px 16px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;margin-left:auto;"><i class="fas fa-file-csv"></i> Export</button>
  <button onclick="icProcessPayroll()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-cogs"></i> Process Payroll</button>
</div>
<div style="padding:10px 14px;margin-bottom:14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;font-size:13px;color:#1d4ed8;">
  <i class="fas fa-info-circle"></i> Interns receive their stipend with no deductions. Casual workers are paid their rate for the days or hours of <b>approved attendance</b> in the month, less the flat casual PAYE rate. Approve and pay these payslips on the <a href="hr.php?module=hr&sub=payroll" style="color:inherit;font-weight:600;">Payroll</a> page.
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:16px;">
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);"><div style="font-size:12px;color:var(--gray-500);">Payslips</div><div id="ic-employees" style="font-size:20px;font-weight:700;color:var(--primary-color);">—</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);"><div style="font-size:12px;color:var(--gray-500);">Total gross</div><div id="ic-gross" style="font-size:18px;font-weight:700;">—</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);"><div style="font-size:12px;color:var(--gray-500);">PAYE withheld</div><div id="ic-tax" style="font-size:18px;font-weight:700;color:var(--danger-color);">—</div></div>
  <div style="padding:14px;background:white;border-radius:8px;box-shadow:var(--shadow);"><div style="font-size:12px;color:var(--gray-500);">Total net pay</div><div id="ic-net" style="font-size:18px;font-weight:700;color:var(--success-color);">—</div></div>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Employee</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Paid per</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">Rate</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">Worked</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">Gross</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">PAYE</th>
      <th style="padding:11px 14px;text-align:right;font-weight:600;">Net pay</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Status</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="ic-tbody"><tr><td colspan="9" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr></tbody>
  </table>
</div>

<?php endif; ?>


<script>
// ==============================================
// Shared utilities
// ==============================================
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function badge(status,labels){var m={active:'success',expired:'danger',pending:'warning',draft:'secondary',published:'success','true':'success','false':'secondary',completed:'info',cancelled:'secondary',approved:'success',rejected:'danger','in-progress':'warning',enrolled:'info',reported:'warning',investigating:'warning',resolved:'success',closed:'secondary',scheduled:'info',held:'primary',planned:'info',open:'success',ongoing:'warning',yes:'success',no:'secondary',1:'success',0:'secondary'};var c=m[status]||'secondary';return'<span style="padding:3px 10px;border-radius:12px;font-size:12px;font-weight:500;background:var(--'+c+'-bg,var(--gray-100));color:var(--'+c+'-color,var(--gray-700));">'+(labels&&labels[status]?labels[status]:status)+'</span>';}
if (typeof empAPI === 'undefined') { window.empAPI = _apiBase + '?endpoint=hr/employees'; }

// ==============================================
// Training Module
// ==============================================
const trAPI = _apiBase + '?endpoint=hr/hr-enhanced&entity=training-programs';
async function trLoad(){
  try{
    var r=await fetch(trAPI);var j=await r.json();
    if(!j.success){document.getElementById('tr-tbody').innerHTML='<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--danger-color);">Failed</td></tr>';return;}
    var d=j.data||[];
    if(d.length===0){document.getElementById('tr-tbody').innerHTML='<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);">No programs</td></tr>';return;}
    var h='';
    d.forEach(function(r){
      h+='<tr><td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.title)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.training_type)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.provider_name||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.start_date||'')+' - '+esc(r.end_date||'')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+(r.duration_hours||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.status)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="alert(\'View ID: \'+r.id+\'\')" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-eye"></i></button></td></tr>';
    });
    document.getElementById('tr-tbody').innerHTML=h;
    document.getElementById('tr-programs').textContent=d.length;
  }catch(e){document.getElementById('tr-tbody').innerHTML='<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--danger-color);">Error</td></tr>';}
}

// ==============================================
// Disciplinary Module
// ==============================================
const dcAPI = _apiBase + '?endpoint=hr/hr-enhanced&entity=disciplinary-cases';
async function dcLoad(){
  try{
    var r=await fetch(dcAPI);var j=await r.json();
    if(!j.success){document.getElementById('dc-tbody').innerHTML='<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--danger-color);">Failed</td></tr>';return;}
    var d=j.data||[];
    if(d.length===0){document.getElementById('dc-tbody').innerHTML='<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);">No cases</td></tr>';return;}
    var h='';
    d.forEach(function(r){
      h+='<tr><td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);font-weight:600;">'+esc(r.case_number)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.employee_id||'')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.incident_type)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.incident_date||'')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.severity)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.status)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="alert(\'Case ID: \'+r.id+\'\')" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-eye"></i></button></td></tr>';
    });
    document.getElementById('dc-tbody').innerHTML=h;
    // Stats
    var total=d.length;var inv=d.filter(function(x){return x.status==='investigating';}).length;
    var res=d.filter(function(x){return x.status==='resolved'||x.status==='closed';}).length;
    var gr=d.filter(function(x){return x.severity==='grave';}).length;
    document.getElementById('dc-total').textContent=total;
    document.getElementById('dc-investigating').textContent=inv;
    document.getElementById('dc-resolved').textContent=res;
    document.getElementById('dc-grave').textContent=gr;
  }catch(e){document.getElementById('dc-tbody').innerHTML='<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--danger-color);">Error</td></tr>';}
}

// ==============================================
// Communications Module
// ==============================================
const cmAPI = _apiBase + '?endpoint=hr/hr-enhanced&entity=announcements';
async function cmLoad(){
  try{
    var r=await fetch(cmAPI);var j=await r.json();
    if(!j.success){document.getElementById('cm-tbody').innerHTML='<tr><td colspan="6" style="padding:48px;text-align:center;color:var(--danger-color);">Failed</td></tr>';return;}
    var d=j.data||[];
    if(d.length===0){document.getElementById('cm-tbody').innerHTML='<tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);">No announcements</td></tr>';return;}
    var h='';
    d.forEach(function(r){
      h+='<tr><td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);font-weight:600;">'+esc(r.title)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.announcement_type)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.priority)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.created_at||'')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.start_date||'')+' to '+esc(r.end_date||'')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="alert(\'ID: \'+r.id+\'\')" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-eye"></i></button></td></tr>';
    });
    document.getElementById('cm-tbody').innerHTML=h;
  }catch(e){document.getElementById('cm-tbody').innerHTML='<tr><td colspan="6" style="padding:48px;text-align:center;color:var(--danger-color);">Error</td></tr>';}
}

// ==============================================
// Goals & Tasks Module
// ==============================================
const gtAPI = _apiBase + '?endpoint=hr/hr-enhanced&entity=goals';
async function gtLoad(){
  try{
    var r=await fetch(gtAPI);var j=await r.json();
    if(!j.success){document.getElementById('gt-tbody').innerHTML='<tr><td colspan="6" style="padding:48px;text-align:center;color:var(--danger-color);">Failed</td></tr>';return;}
    var d=j.data||[];
    if(d.length===0){document.getElementById('gt-tbody').innerHTML='<tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);">No goals/tasks</td></tr>';return;}
    var h='';
    d.forEach(function(r){
      h+='<tr><td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.goal_title||r.task_name||'')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+(r.employee_id||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.goal_type||'task')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.target_date||r.due_date||'')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+badge(r.status)+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="alert(\'ID: \'+r.id+\'\')" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-eye"></i></button></td></tr>';
    });
    document.getElementById('gt-tbody').innerHTML=h;
  }catch(e){document.getElementById('gt-tbody').innerHTML='<tr><td colspan="6" style="padding:48px;text-align:center;color:var(--danger-color);">Error</td></tr>';}
}


// ==============================================
// Interns Module
// ==============================================
const inAPI = _apiBase + '?endpoint=hr/employees';
async function inLoad(){
  try{
    var r=await fetch(inAPI+'&limit=500&employment_type=intern');
    var j=await r.json();
    if(!j.success||!j.data||!j.data.employees){document.getElementById('in-tbody').innerHTML='<tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);">No interns found</td></tr>';return;}
    var d=j.data.employees;
    var search=(document.getElementById('in-search').value||'').toLowerCase();
    var dept=(document.getElementById('in-dept').value||'');
    if(search) d=d.filter(function(x){return(x.first_name+' '+x.last_name).toLowerCase().indexOf(search)>-1||(x.employee_no||'').toLowerCase().indexOf(search)>-1;});
    if(dept) d=d.filter(function(x){return String(x.department_id)===dept;});
    if(d.length===0){document.getElementById('in-tbody').innerHTML='<tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);">No matches</td></tr>';return;}
    var h='';var active=0,expiring=0,completed=0;
    d.forEach(function(r){
      if(r.status==='active') active++;
      var end=r.intern_end_date||'';
      if(end){var d1=new Date(end),d2=new Date();var days=Math.ceil((d1-d2)/(1000*60*60*24));if(days<=30&&days>0)expiring++;if(days<0)completed++;}
      var stb=r.status==='active'?'<span style="padding:3px 10px;border-radius:12px;font-size:12px;font-weight:500;background:var(--success-bg,var(--gray-100));color:var(--success-color);">Active</span>':'<span style="padding:3px 10px;border-radius:12px;font-size:12px;font-weight:500;background:var(--gray-100);color:var(--gray-500);">'+esc(r.status)+'</span>';
      h+='<tr><td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);font-weight:600;">'+esc(r.first_name+' '+r.last_name)+'<br><small style="color:var(--gray-400);font-size:11px;">'+esc(r.employee_no)+'</small></td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+(r.department_name||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.intern_start_date||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.intern_end_date||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+(r.intern_duration_days?r.intern_duration_days+' days':'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+(r.intern_supervisor||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;">'+stb+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="location.href=\'hr.php?module=hr&sub=employees&edit='+r.id+'\'" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-edit"></i></button></td></tr>';
    });
    document.getElementById('in-tbody').innerHTML=h;
    document.getElementById('in-total').textContent=d.length;
    document.getElementById('in-active').textContent=active;
    document.getElementById('in-expiring').textContent=expiring;
    document.getElementById('in-completed').textContent=completed;
    // Load department filter
    var dr=await fetch(_apiBase+'?endpoint=hr/departments');
    var dj=await dr.json();
    if(dj.success&&dj.data){var opts='<option value="">All Departments</option>';dj.data.forEach(function(x){opts+='<option value="'+x.id+'">'+esc(x.name)+'</option>';});document.getElementById('in-dept').innerHTML=opts;}
  }catch(e){document.getElementById('in-tbody').innerHTML='<tr><td colspan="8" style="padding:48px;text-align:center;color:var(--danger-color);">Error: '+e.message+'</td></tr>';}
}
function inOpenModal(){location.href='new_employee.php?type=intern';}

// ==============================================
// Casual Workers Module
// ==============================================
async function cwLoad(){
  try{
    var r=await fetch(inAPI+'&limit=500&employment_type=casual-worker');
    var j=await r.json();
    if(!j.success||!j.data||!j.data.employees){document.getElementById('cw-tbody').innerHTML='<tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);">No casual workers</td></tr>';return;}
    var d=j.data.employees;
    var filter=document.getElementById('cw-filter').value;
    if(filter) d=d.filter(function(x){return x.payment_frequency===filter;});
    var search=(document.getElementById('cw-search').value||'').toLowerCase();
    if(search) d=d.filter(function(x){return(x.first_name+' '+x.last_name).toLowerCase().indexOf(search)>-1;});
    if(d.length===0){document.getElementById('cw-tbody').innerHTML='<tr><td colspan="8" style="padding:48px;text-align:center;color:var(--gray-400);">No matches</td></tr>';return;}
    var h='';var active=0,hourly=0,daily=0;
    d.forEach(function(r){
      if(r.status==='active')active++;
      if(r.payment_frequency==='hourly')hourly++;
      if(r.payment_frequency==='daily')daily++;
      var stb=r.status==='active'?'<span style="padding:3px 10px;border-radius:12px;font-size:12px;background:var(--success-bg,var(--gray-100));color:var(--success-color);">Active</span>':'<span style="padding:3px 10px;border-radius:12px;font-size:12px;background:var(--gray-100);color:var(--gray-500);">'+esc(r.status)+'</span>';
      h+='<tr><td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);font-weight:600;">'+esc(r.first_name+' '+r.last_name)+'<br><small style="color:var(--gray-400);font-size:11px;">'+esc(r.employee_no)+'</small></td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.department_name||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.payment_frequency||'monthly')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:right;">'+(r.casual_hourly_rate?parseFloat(r.casual_hourly_rate).toLocaleString():'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:right;">'+(r.casual_max_hours_per_week||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);">'+esc(r.casual_contract_end_date||'-')+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;">'+stb+'</td>'+
        '<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="location.href=\'hr.php?module=hr&sub=employees&edit='+r.id+'\'" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-edit"></i></button></td></tr>';
    });
    document.getElementById('cw-tbody').innerHTML=h;
    document.getElementById('cw-total').textContent=d.length;
    document.getElementById('cw-active').textContent=active;
    document.getElementById('cw-hourly').textContent=hourly;
    document.getElementById('cw-daily').textContent=daily;
  }catch(e){document.getElementById('cw-tbody').innerHTML='<tr><td colspan="8" style="padding:48px;text-align:center;color:var(--danger-color);">Error</td></tr>';}
}
function cwOpenModal(){location.href='new_employee.php?type=casual-worker';}

// ==============================================
// Intern & Casual Payroll Module
// ==============================================
if (typeof payAPI === 'undefined') { window.payAPI = _apiBase + '?endpoint=hr/payroll'; }
var icRecords=[];
function icTypes(){var t=document.getElementById('ic-type').value;return t?[t]:['intern','casual-worker'];}
async function icLoad(){
  var per=document.getElementById('ic-period');
  if(!per.value){var n=new Date();per.value=n.getFullYear()+'-'+String(n.getMonth()+1).padStart(2,'0');}
  try{
    var lists=await Promise.all(icTypes().map(function(t){return fetch(payAPI+'&limit=100&period='+encodeURIComponent(per.value)+'&employment_type='+encodeURIComponent(t)).then(function(r){return r.json();});}));
    var bad=lists.find(function(j){return !j.success;}); if(bad){document.getElementById('ic-tbody').innerHTML='<tr><td colspan="9" style="padding:48px;text-align:center;color:var(--danger-color);">'+esc(bad.message||'Failed')+'</td></tr>';return;}
    icRecords=[].concat.apply([],lists.map(function(j){return j.data.records||[];}));
    var sum=function(k){return icRecords.reduce(function(a,r){return a+Number(r[k]||0);},0).toLocaleString()+' RWF';};
    document.getElementById('ic-employees').textContent=icRecords.length;
    document.getElementById('ic-gross').textContent=sum('gross_salary');
    document.getElementById('ic-tax').textContent=sum('tpr_tax');
    document.getElementById('ic-net').textContent=sum('net_pay');
    if(!icRecords.length){document.getElementById('ic-tbody').innerHTML='<tr><td colspan="9" style="padding:48px;text-align:center;color:var(--gray-400);">No payslips for this month. Click Process Payroll.</td></tr>';return;}
    var lbl={draft:'draft',processed:'submitted',approved:'approved',paid:'paid'};
    document.getElementById('ic-tbody').innerHTML=icRecords.map(function(r){
      var rate=r.hourly_rate||r.daily_rate, worked=r.payment_frequency==='hourly'?(r.hours_worked||0)+' h':(r.days_worked!=null?r.days_worked+' days':'-');
      var st=r.status==='paid'?'<span style="padding:3px 10px;border-radius:12px;font-size:12px;background:var(--success-bg,var(--gray-100));color:var(--success-color);">paid</span>':'<span style="padding:3px 10px;border-radius:12px;font-size:12px;background:var(--warning-bg,var(--gray-100));color:var(--warning-color);">'+esc(lbl[r.status]||r.status||'pending')+'</span>';
      return '<tr><td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);font-weight:600;">'+esc(r.first_name+' '+r.last_name)+'<br><small style="color:var(--gray-400);font-size:11px;">'+esc(r.employee_no)+'</small></td>'
        +'<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;">'+esc(r.payment_frequency||'monthly')+'</td>'
        +'<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:right;">'+(rate?Number(rate).toLocaleString():'-')+'</td>'
        +'<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:right;">'+worked+'</td>'
        +'<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:right;font-weight:600;">'+Number(r.gross_salary||0).toLocaleString()+' RWF</td>'
        +'<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:right;">'+Number(r.tpr_tax||0).toLocaleString()+' RWF</td>'
        +'<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:right;font-weight:600;color:var(--success-color);">'+Number(r.net_pay||0).toLocaleString()+' RWF</td>'
        +'<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;">'+st+'</td>'
        +'<td style="padding:10px 14px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="alert(\'Record ID: '+r.id+'\')" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-eye"></i></button></td></tr>';
    }).join('');
  }catch(e){document.getElementById('ic-tbody').innerHTML='<tr><td colspan="9" style="padding:48px;text-align:center;color:var(--danger-color);">Error: '+e.message+'</td></tr>';}
}
async function icProcessPayroll(){
  var p=document.getElementById('ic-period').value, types=icTypes();
  if(!p){alert('Choose the month first.');return;}
  if(!confirm('Prepare '+p+' payslips for '+(types.length>1?'interns and casual workers':types[0]==='intern'?'interns':'casual workers')+'?'))return;
  var msgs=[];
  for(var i=0;i<types.length;i++){
    try{var j=await (await fetch(payAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pay_period:p,employment_type:types[i]})})).json();
      msgs.push((types[i]==='intern'?'Interns: ':'Casual workers: ')+(j.message||''));}
    catch(e){msgs.push('Error: '+e.message);}
  }
  alert(msgs.join('\n'));
  icLoad();
}
function icExport(){
  if(!icRecords.length){alert('Nothing to export');return;}
  var cols=['employee_no','first_name','last_name','pay_period','payment_frequency','days_worked','hours_worked','gross_salary','tpr_tax','advance_deduction','net_pay','status'];
  var csv=[cols.join(',')].concat(icRecords.map(function(r){return cols.map(function(c){return '"'+String(r[c]==null?'':r[c]).replace(/"/g,'""')+'"';}).join(',');})).join('\n');
  var a=document.createElement('a');
  a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));
  a.download='intern-casual-payroll-'+document.getElementById('ic-period').value+'.csv';
  a.click();
}


// Auto-load on page load
document.addEventListener('DOMContentLoaded', function(){
  var sub = new URLSearchParams(window.location.search).get('sub');
  if(sub==='training') trLoad();
  else if(sub==='disciplinary') dcLoad();
  else if(sub==='communications') cmLoad();
  else if(sub==='goals-tasks') gtLoad();
  else if(sub==='interns') inLoad();
  else if(sub==='casual-workers') cwLoad();
  else if(sub==='intern-casual-payroll') icLoad();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>