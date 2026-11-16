<?php
// new_employee.php — form to add a new employee (the Employees page only views/edits)
require_once __DIR__ . '/includes/header.php';
?>
<style>
.ne-card{background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);max-width:860px;}
.ne-h{font-size:13px;font-weight:600;color:var(--gray-500);margin:0 0 10px;text-transform:uppercase;letter-spacing:.5px;}
.ne-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px;}
.ne-grid.three{grid-template-columns:1fr 1fr 1fr;}
.ne-card label{display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;}
.ne-card input,.ne-card select{width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;}
.ne-req{color:var(--danger-color);}
@media(max-width:640px){.ne-grid,.ne-grid.three{grid-template-columns:1fr;}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">Add New Employee</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Register a new employee, their employment terms, bank details and documents</p>
  </div>
  <a href="hr.php?module=hr&sub=employees" style="padding:10px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;text-decoration:none;"><i class="fas fa-users"></i> View Employees</a>
</div>

<div class="ne-card">
  <div id="ne-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
  <form id="ne-form" onsubmit="neSubmit(event)" enctype="multipart/form-data">
    <input type="hidden" name="address" value="">
    <input type="hidden" name="notes" value="">

    <div class="ne-h">Personal Info</div>
    <div class="ne-grid">
      <div><label>First Name <span class="ne-req">*</span></label><input name="first_name" type="text" required></div>
      <div><label>Last Name <span class="ne-req">*</span></label><input name="last_name" type="text" required></div>
      <div><label>Email <span class="ne-req">*</span></label><input name="email" type="email" required></div>
      <div><label>Phone <span class="ne-req">*</span></label><input name="phone" type="text" required></div>
      <div><label>Gender <span class="ne-req">*</span></label>
        <select name="gender" required><option value="">Select</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select></div>
      <div><label>Date of Birth</label><input name="date_of_birth" type="date"></div>
      <div><label>National ID</label><input name="national_id" type="text"></div>
      <div><label>Nationality</label><input name="nationality" type="text" value="Rwandan" maxlength="60"></div>
    </div>

    <div class="ne-h">Employment</div>
    <div class="ne-grid">
      <div><label>Department</label><select id="ne-dept" name="department_id"><option value="">Select Department</option></select></div>
      <div><label>Position <span class="ne-req">*</span></label><input name="position" type="text" required></div>
      <div><label>Employment Type</label>
        <select id="ne-type" name="employment_type" onchange="neType()">
          <option value="full-time">Full Time</option><option value="part-time">Part Time</option><option value="contract">Contract</option>
          <option value="intern">Intern</option><option value="casual-worker">Casual Worker</option>
        </select></div>
      <div><label>Date Joined <span class="ne-req">*</span></label><input name="date_joined" type="date" required></div>
      <div id="ne-salary-wrap"><label id="ne-salary-label">Gross Salary (RWF) <span class="ne-req">*</span></label><input id="ne-salary" name="gross_salary" type="number" min="0" step="1"></div>
      <div><label>Status</label>
        <select name="status"><option value="active">Active</option><option value="on-leave">On Leave</option><option value="suspended">Suspended</option><option value="terminated">Terminated</option></select></div>
    </div>

    <div id="ne-intern" style="display:none;">
      <div class="ne-h">Internship Placement</div>
      <div class="ne-grid three">
        <div><label>Start</label><input name="intern_start_date" type="date"></div>
        <div><label>End</label><input name="intern_end_date" type="date"></div>
        <div><label>Supervisor</label><select id="ne-supervisor" name="intern_supervisor"><option value="">—</option></select></div>
      </div>
    </div>

    <div id="ne-casual" style="display:none;">
      <div class="ne-h">Casual Work Terms</div>
      <div class="ne-grid">
        <div><label>Paid per</label>
          <select name="payment_frequency"><option value="daily">Day worked</option><option value="hourly">Hour worked</option><option value="weekly">Week worked</option><option value="monthly">Month (fixed)</option></select></div>
        <div><label>Rate (RWF per day / hour / week / month)</label><input name="casual_hourly_rate" type="number" min="0" step="1"></div>
        <div><label>Max hours per week</label><input name="casual_max_hours_per_week" type="number" min="0" step="1"></div>
        <div><label>Engagement ends</label><input name="casual_contract_end_date" type="date"></div>
      </div>
    </div>

    <div class="ne-h">Bank Details</div>
    <div class="ne-grid">
      <div><label>Bank Name</label><input name="bank_name" type="text" placeholder="e.g. Bank of Kigali"></div>
      <div><label>Account Number</label><input name="bank_account_number" type="text"></div>
      <div><label>Account Name</label><input name="bank_account_name" type="text" placeholder="Name on account"></div>
    </div>

    <div class="ne-h">Monthly Fixed Deductions (RWF)</div>
    <div class="ne-grid three">
      <div><label>Mutuelle</label><input name="mutuelle_amount" type="number" min="0" step="1" value="0"></div>
    </div>

    <div class="ne-h">Upload Documents</div>
    <div id="ne-docs"></div>

    <div style="display:flex;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);">
      <button type="submit" id="ne-btn" style="flex:1;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save Employee</button>
      <a href="hr.php?module=hr&sub=employees" style="padding:12px 24px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;text-decoration:none;">Cancel</a>
    </div>
  </form>
</div>

<script>
var _hrBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const neEmpAPI = _hrBase + '/api.php?endpoint=hr/employees';
const neEsc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

function neType(){
  const t = document.getElementById('ne-type').value;
  document.getElementById('ne-intern').style.display = t==='intern' ? '' : 'none';
  document.getElementById('ne-casual').style.display = t==='casual-worker' ? '' : 'none';
  document.getElementById('ne-salary-wrap').style.display = t==='casual-worker' ? 'none' : '';
  document.getElementById('ne-salary-label').innerHTML = (t==='intern' ? 'Monthly stipend (RWF)' : 'Gross Salary (RWF)') + ' <span class="ne-req">*</span>';
  if (t==='casual-worker' && !document.getElementById('ne-salary').value) document.getElementById('ne-salary').value = 0;
}

function neAddDoc(first){
  const row = document.createElement('div');
  row.className = 'ne-doc';
  row.style = 'display:grid;grid-template-columns:1fr 1fr auto;gap:10px;margin-bottom:8px;';
  row.innerHTML = `<select name="document_types[]"><option>Contract</option><option>Certificate</option><option>ID Copy</option><option>Degree</option><option>License</option><option>Other</option></select>
    <input type="file" name="documents[]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
    ${first ? '<button type="button" onclick="neAddDoc(false)" title="Add more" style="padding:9px 12px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;"><i class="fas fa-plus"></i></button>'
            : '<button type="button" onclick="this.closest(\'.ne-doc\').remove()" style="padding:9px 12px;background:#fee2e2;border:1px solid #fecaca;border-radius:8px;cursor:pointer;color:#dc2626;"><i class="fas fa-times"></i></button>'}`;
  document.getElementById('ne-docs').appendChild(row);
}

async function neSubmit(e){
  e.preventDefault();
  const btn = document.getElementById('ne-btn'), al = document.getElementById('ne-alert');
  al.style.display = 'none'; btn.disabled = true;
  try {
    const r = await fetch(neEmpAPI, {method:'POST', body:new FormData(document.getElementById('ne-form'))});
    const j = await r.json();
    if (j.success) { location.href = 'hr.php?module=hr&sub=employees'; return; }
    al.textContent = j.message || 'Save failed'; al.style.display = 'block'; window.scrollTo(0,0);
  } catch (err) { al.textContent = 'Request failed: ' + err.message; al.style.display = 'block'; }
  btn.disabled = false;
}

(async function(){
  var neTypeParam = new URLSearchParams(window.location.search).get('type');
  if (neTypeParam && document.querySelector('#ne-type option[value="'+neTypeParam+'"]')) document.getElementById('ne-type').value = neTypeParam;
  neAddDoc(true); neType();
  try {
    const d = await (await fetch(_hrBase + '/api.php?endpoint=hr/departments')).json();
    document.getElementById('ne-dept').innerHTML += (d.data || []).map(x => `<option value="${x.id}">${neEsc(x.name)}</option>`).join('');
  } catch (e) {}
  try {
    const j = await (await fetch(neEmpAPI + '&limit=100&status=active')).json();
    document.getElementById('ne-supervisor').innerHTML = '<option value="">—</option>' + (j.data.employees||[]).map(x => `<option value="${x.id}">${neEsc(x.first_name+' '+x.last_name)}</option>`).join('');
  } catch (e) {}
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
