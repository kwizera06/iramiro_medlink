<?php
// public/settings.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'users';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Page title mapping
$subModuleTitles = [
    'users' => 'User Management',
    'roles' => 'Role Management',
    'hospital-settings' => 'Hospital Settings',
    'department-forms' => 'Department Form Templates',
    'backup' => 'Backup & Restore',
    'audit-logs' => 'Audit Logs',
    'system-health' => 'System Health'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'System Administration';
?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'users' => 'Manage system users and their accounts',
                'roles' => 'Define and manage user roles',
                'hospital-settings' => 'Configure hospital details and system settings',
                'department-forms' => 'Create and manage department-specific form templates',
                'backup' => 'Backup and restore system data',
                'audit-logs' => 'View system audit trails and activity logs',
                'system-health' => 'Monitor system health and performance'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'System Administration';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($action == 'list' && in_array($subModule, ['users', 'roles'])): ?>
        <button onclick="window.location.href='settings.php?module=admin&sub=<?php echo $subModule; ?>&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Add New
        </button>
        <?php endif; ?>
        <?php if ($action != 'list'): ?>
        <button onclick="window.location.href='settings.php?module=admin&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
USER MANAGEMENT
============================================ -->
<?php if ($subModule == 'users'): ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:24px;">
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="user-stat-total" style="font-size:24px;font-weight:700;color:var(--primary-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Total Users</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="user-stat-active" style="font-size:24px;font-weight:700;color:var(--success-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Active</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="user-stat-inactive" style="font-size:24px;font-weight:700;color:var(--warning-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Inactive</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="user-stat-suspended" style="font-size:24px;font-weight:700;color:var(--danger-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Suspended</div>
  </div>
</div>

<!-- Toolbar -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="user-search" type="text" placeholder="Search users…" style="flex:1;min-width:180px;padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <select id="user-filter-role" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:120px;">
    <option value="">All Roles</option>
  </select>
  <select id="user-filter-status" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Status</option>
    <option value="active">Active</option>
    <option value="inactive">Inactive</option>
    <option value="suspended">Suspended</option>
  </select>
  <button onclick="userOpenModal()" style="padding:9px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
    <i class="fas fa-user-plus"></i> Add User
  </button>
</div>

<!-- Table -->
<div style="background:white;border-radius:var(--radius);padding:0 0 8px;box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
      <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">User</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Employee</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Role</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Department</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Last Login</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
      </tr>
    </thead>
    <tbody id="user-tbody">
      <tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
    </tbody>
  </table>
  <div id="user-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:12px 16px;"></div>
</div>

<!-- Add/Edit User Modal -->
<div id="user-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:640px;margin:auto;position:relative;">
    <button onclick="userCloseModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="user-modal-title" style="font-size:18px;font-weight:600;margin-bottom:20px;">Add User</h3>
    <div id="user-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form id="user-form" onsubmit="userSubmit(event)">
      <input type="hidden" id="user-id">
      <input type="hidden" id="user-department-id">

      <!-- Employee Selection -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Link to Employee <span style="color:var(--danger-color)">*</span></label>
        <select id="user-employee" name="employee_id" onchange="userFillFromEmployee(this)" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
          <option value="">— Select Employee —</option>
        </select>
        <div style="font-size:12px;color:var(--gray-500);margin-top:4px;">Selecting an employee will auto-fill name, email and department.</div>
      </div>

      <!-- Name -->
      <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Personal Details</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">First Name <span style="color:var(--danger-color)">*</span></label>
          <input id="user-first-name" type="text" placeholder="First name" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Last Name <span style="color:var(--danger-color)">*</span></label>
          <input id="user-last-name" type="text" placeholder="Last name" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
      </div>

      <!-- Account Details -->
      <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Account Details</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Username</label>
          <input id="user-username" name="username" type="text" placeholder="Enter username" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Email <span style="color:var(--danger-color)">*</span></label>
          <input id="user-email" name="email" type="email" placeholder="user@hospital.com" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Password <span style="color:var(--danger-color)" id="pwd-required">*</span></label>
          <input id="user-password" name="password" type="password" placeholder="Enter password" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Confirm Password <span style="color:var(--danger-color)" id="cpwd-required">*</span></label>
          <input id="user-confirm-password" type="password" placeholder="Confirm password" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
      </div>

      <!-- Role & Permissions -->
      <div style="font-size:13px;font-weight:600;color:var(--gray-500);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Roles & Status</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Roles <span style="color:var(--danger-color)">*</span></label>
          <div id="user-roles-container" style="border:1px solid var(--gray-200);border-radius:8px;padding:10px 14px;max-height:160px;overflow-y:auto;background:white;">
            <p style="color:var(--gray-400);font-size:13px;">Loading roles...</p>
          </div>
          <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Select one or more roles</div>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Status</label>
          <select id="user-status" name="status" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="user-submit-btn" style="flex:1;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
          <i class="fas fa-save"></i> <span id="user-submit-label">Create User</span>
        </button>
        <button type="button" onclick="userCloseModal()" style="padding:12px 24px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<?php if ($subModule == 'users'): ?>
<script src="assets/js/roles-access.js?v=<?php echo filemtime(__DIR__ . '/assets/js/roles-access.js'); ?>"></script>
<script>
const userAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=settings/users';
const empAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=hr/employees';
const roleAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=settings/roles';
let userPage=1, userSearch='', userFilterRole='', userFilterStatus='';
function uEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// employee_no → employee object map for auto-fill
let userEmpMap = {};

async function userLoadDeps(){
  try{
    // Load employees — value = employee_no (varchar used as users.employee_id)
    const r1=await fetch(empAPI+'&limit=500&status=active');
    const j1=await r1.json();
    if(j1.success){
      userEmpMap = {};
      const opts=j1.data.employees.map(e=>{
        userEmpMap[e.employee_no] = e;
        return `<option value="${uEsc(e.employee_no)}">${uEsc(e.first_name+' '+e.last_name)} (${uEsc(e.employee_no)})</option>`;
      }).join('');
      document.getElementById('user-employee').innerHTML='<option value="">— Select Employee —</option>'+opts;
    }
  }catch(e){}
  try{
    // Load roles for checkboxes and filter
    const r2=await fetch(roleAPI);
    const j2=await r2.json();
    if(j2.success){
      const roles = j2.data.roles || j2.data;
      window._allRoles = roles;
      // Render checkboxes in the roles container
      const container = document.getElementById('user-roles-container');
      container.innerHTML = roles.map(r => `
        <label style="display:flex;align-items:center;gap:8px;padding:4px 0;cursor:pointer;font-size:13px;color:var(--gray-700);">
          <input type="checkbox" class="user-role-cb" value="${r.id}" style="width:15px;height:15px;cursor:pointer;">
          ${uEsc(r.name)}
        </label>
      `).join('');
      // Filter dropdown
      const roleOpts=roles.map(r=>`<option value="${r.id}">${uEsc(r.name)}</option>`).join('');
      document.getElementById('user-filter-role').innerHTML='<option value="">All Roles</option>'+roleOpts;
    }
  }catch(e){}
}

function userFillFromEmployee(sel){
  const empNo = sel.value;
  const emp = userEmpMap[empNo];
  if(!emp) return;
  document.getElementById('user-first-name').value = emp.first_name || '';
  document.getElementById('user-last-name').value  = emp.last_name  || '';
  document.getElementById('user-email').value      = emp.email      || '';
  document.getElementById('user-department-id').value = emp.department_id || '';
  // auto-generate username suggestion: firstname.lastname
  if(!document.getElementById('user-id').value){
    document.getElementById('user-username').value =
      (emp.first_name+'.'+emp.last_name).toLowerCase().replace(/\s+/g,'');
  }
}

async function userLoad(){
  const p=new URLSearchParams({page:userPage,limit:20,search:userSearch,role_id:userFilterRole,status:userFilterStatus});
  try{
    const r=await fetch(userAPI+'&'+p); const j=await r.json();
    if(!j.success){userMsg('Failed: '+j.message);return;}
    const {users,stats,pagination}=j.data;
    document.getElementById('user-stat-total').textContent  = stats.total||0;
    document.getElementById('user-stat-active').textContent = stats.active||0;
    document.getElementById('user-stat-inactive').textContent = stats.inactive||0;
    document.getElementById('user-stat-suspended').textContent = stats.suspended||0;
    const sc={active:'var(--success-color)',inactive:'var(--warning-color)',suspended:'var(--danger-color)'};
    const tb=document.getElementById('user-tbody');
    if(!users.length){
      tb.innerHTML='<tr><td colspan="7" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-users-cog" style="font-size:36px;display:block;margin-bottom:8px;"></i>No users found.</td></tr>';
    }else{
      tb.innerHTML=users.map(u=>`
        <tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:12px 16px;"><div style="font-weight:600;">${uEsc(u.username)}</div><div style="font-size:12px;color:var(--gray-500);">${uEsc(u.email)}</div></td>
          <td style="padding:12px 16px;"><div style="font-weight:600;">${uEsc(u.employee_name||'—')}</div><div style="font-size:12px;color:var(--primary-color);font-family:monospace;">${uEsc(u.employee_no||'—')}</div></td>
          <td style="padding:12px 16px;font-size:13px;">${uEsc(u.role_name||'—')}</td>
          <td style="padding:12px 16px;font-size:13px;">${uEsc(u.department_name||'—')}</td>
          <td style="padding:12px 16px;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${sc[u.status]||'#ccc'}20;color:${sc[u.status]||'#555'};">${u.status}</span></td>
          <td style="padding:12px 16px;font-size:13px;">${u.last_login||'Never'}</td>
          <td style="padding:12px 16px;text-align:center;white-space:nowrap;">
            <button onclick="openUserAccessModal(${u.id},'${uEsc(u.username)}','${uEsc(u.role_name||'')}')" style="background:none;border:none;color:#8b5cf6;cursor:pointer;font-size:15px;padding:4px;margin-right:6px;" title="Custom Page Access Overrides (e.g. Give Inventory to this Nurse)"><i class="fas fa-key"></i></button>
            <button onclick="userEdit(${u.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;padding:4px;margin-right:4px;" title="Edit User"><i class="fas fa-edit"></i></button>
            <button onclick="userDel(${u.id},'${uEsc(u.username)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;padding:4px;" title="Delete"><i class="fas fa-trash"></i></button>
          </td>
        </tr>`).join('');
    }
    const pg=document.getElementById('user-pagination');
    pg.innerHTML=pagination.total_pages>1?Array.from({length:pagination.total_pages},(_,i)=>`<button onclick="userPage=${i+1};userLoad()" style="padding:6px 12px;border:1px solid ${i+1===pagination.page?'var(--primary-color)':'var(--gray-200)'};background:${i+1===pagination.page?'var(--primary-color)':'white'};color:${i+1===pagination.page?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;">${i+1}</button>`).join(''):'';
  }catch(e){userMsg('Error: '+e.message);}
}
function userMsg(m){document.getElementById('user-tbody').innerHTML=`<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}

function userOpenModal(d=null){
  const isEdit=!!d;
  document.getElementById('user-id').value               = d?d.id:'';
  document.getElementById('user-employee').value         = d?(d.employee_id||''):'';
  document.getElementById('user-first-name').value       = d?(d.first_name||''):'';
  document.getElementById('user-last-name').value        = d?(d.last_name||''):'';
  document.getElementById('user-username').value         = d?(d.username||''):'';
  document.getElementById('user-email').value            = d?(d.email||''):'';
  document.getElementById('user-password').value         = '';
  document.getElementById('user-confirm-password').value = '';
  document.getElementById('user-status').value           = d?(d.status||'active'):'active';
  document.getElementById('user-department-id').value    = d?(d.department_id||''):'';
  
  // Set role checkboxes
  const roleIds = d && d.role_ids ? d.role_ids.map(Number) : [];
  document.querySelectorAll('.user-role-cb').forEach(cb => {
    cb.checked = roleIds.includes(parseInt(cb.value));
  });
  
  document.getElementById('user-modal-title').textContent  = isEdit?'Edit User':'Add User';
  document.getElementById('user-submit-label').textContent = isEdit?'Update User':'Create User';
  document.getElementById('pwd-required').style.display = isEdit?'none':'inline';
  document.getElementById('cpwd-required').style.display = isEdit?'none':'inline';
  document.getElementById('user-password').required = !isEdit;
  document.getElementById('user-confirm-password').required = !isEdit;
  if(isEdit){
    document.getElementById('user-password').placeholder = 'Leave empty to keep current';
    document.getElementById('user-confirm-password').placeholder = 'Leave empty to keep current';
  } else {
    document.getElementById('user-password').placeholder = 'Enter password';
    document.getElementById('user-confirm-password').placeholder = 'Confirm password';
  }
  document.getElementById('user-alert').style.display='none';
  document.getElementById('user-modal').style.display='flex';
}
function userCloseModal(){document.getElementById('user-modal').style.display='none';}

async function userEdit(id){
  try{const r=await fetch(userAPI+'&id='+id);const j=await r.json();if(j.success)userOpenModal(j.data);}
  catch(e){alert('Could not load user');}
}
async function userDel(id,name){
  if(!confirm(`Delete user "${name}"? This cannot be undone.`))return;
  try{const r=await fetch(userAPI+'&id='+id,{method:'DELETE'});const j=await r.json();j.success?userLoad():alert(j.message);}
  catch(e){alert('Delete failed');}
}

async function userSubmit(e){
  e.preventDefault();
  const id=document.getElementById('user-id').value;
  const btn=document.getElementById('user-submit-btn');
  const al=document.getElementById('user-alert');
  al.style.display='none'; btn.disabled=true;

  const pwd  = document.getElementById('user-password').value;
  const cpwd = document.getElementById('user-confirm-password').value;
  if(!id && !pwd){al.textContent='Password is required';al.style.display='block';btn.disabled=false;return;}
  if(pwd && pwd !== cpwd){al.textContent='Passwords do not match';al.style.display='block';btn.disabled=false;return;}

  const firstName = document.getElementById('user-first-name').value.trim();
  const lastName  = document.getElementById('user-last-name').value.trim();
  const selectedRoles = [...document.querySelectorAll('.user-role-cb:checked')].map(cb => parseInt(cb.value));
  if(!firstName){al.textContent='First name is required';al.style.display='block';btn.disabled=false;return;}
  if(!lastName) {al.textContent='Last name is required'; al.style.display='block';btn.disabled=false;return;}
  if(!selectedRoles.length){al.textContent='At least one role must be selected';al.style.display='block';btn.disabled=false;return;}

  const body={
    employee_id   : document.getElementById('user-employee').value,
    first_name    : firstName,
    last_name     : lastName,
    username      : document.getElementById('user-username').value.trim(),
    email         : document.getElementById('user-email').value.trim(),
    role_ids      : selectedRoles,
    department_id : document.getElementById('user-department-id').value || null,
    status        : document.getElementById('user-status').value
  };
  if(pwd) body.password = pwd;

  try{
    const r=await fetch(id?userAPI+'&id='+id:userAPI,{method:id?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){userCloseModal();userLoad();}
    else{al.textContent=j.message||'Save failed';al.style.display='block';}
  }catch(err){al.textContent='Request failed: '+err.message;al.style.display='block';}
  finally{btn.disabled=false;}
}

let userTimer;
document.getElementById('user-search').addEventListener('input',function(){clearTimeout(userTimer);userTimer=setTimeout(()=>{userSearch=this.value;userPage=1;userLoad();},350);});
document.getElementById('user-filter-role').addEventListener('change',function(){userFilterRole=this.value;userPage=1;userLoad();});
document.getElementById('user-filter-status').addEventListener('change',function(){userFilterStatus=this.value;userPage=1;userLoad();});

userLoadDeps();
userLoad();
</script>
<?php endif; ?>

    </form>
 

<!-- ============================================
ROLE MANAGEMENT
============================================ -->
<?php elseif ($subModule == 'roles'): ?>

<!-- Tabs -->
<div style="display:flex;gap:6px;margin-bottom:20px;background:white;padding:6px;border-radius:10px;box-shadow:var(--shadow);width:fit-content;flex-wrap:wrap;">
  <button id="ra-tab-roles" onclick="raTab('roles')" style="padding:8px 18px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:var(--primary-color);color:white;"><i class="fas fa-user-shield" style="margin-right:6px;"></i>Roles</button>
  <button id="ra-tab-access" onclick="raTab('access')" style="padding:8px 18px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:transparent;color:var(--gray-600);"><i class="fas fa-lock" style="margin-right:6px;"></i>Page Access by Role</button>
  <button id="ra-tab-user_access" onclick="raTab('user_access')" style="padding:8px 18px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:transparent;color:var(--gray-600);"><i class="fas fa-user-lock" style="margin-right:6px;"></i>User Access Overrides</button>
  <button id="ra-tab-menus" onclick="raTab('menus')" style="padding:8px 18px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:transparent;color:var(--gray-600);"><i class="fas fa-bars" style="margin-right:6px;"></i>Menu Management</button>
  <button id="ra-tab-indiv" onclick="raTab('indiv')" style="padding:8px 18px;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;background:transparent;color:var(--gray-600);"><i class="fas fa-user-cog" style="margin-right:6px;"></i>Individual Role Management</button>
</div>

<!-- ===== INDIVIDUAL ROLE MANAGEMENT PANEL ===== -->
<div id="ra-panel-indiv" style="display:none;">
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;align-items:center;">
  <select id="indiv-role-select" onchange="indivLoad()" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:220px;">
    <option value="">— Select Role —</option>
  </select>
  <span style="font-size:13px;color:var(--gray-500);">Limit what each person in a role can do on each page.</span>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:12px 16px;text-align:center;font-weight:600;width:80px;">Action</th>
      <th style="padding:12px 16px;text-align:left;font-weight:600;">Name</th>
      <th style="padding:12px 16px;text-align:left;font-weight:600;">Role</th>
      <th style="padding:12px 16px;text-align:left;font-weight:600;">Permission</th>
    </tr></thead>
    <tbody id="indiv-tbody"><tr><td colspan="4" style="padding:40px;text-align:center;color:var(--gray-400);">Select a role above</td></tr></tbody>
  </table>
</div>
</div>

<!-- Individual permissions modal -->
<div id="indiv-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:760px;margin:auto;position:relative;">
    <button onclick="document.getElementById('indiv-modal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:18px;font-weight:600;margin-bottom:4px;">Edit Permissions: <span id="indiv-name" style="color:var(--primary-color);"></span></h3>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:14px;">Untick what this person must not do. A page with everything ticked is unrestricted.</p>
    <div id="indiv-body" style="max-height:60vh;overflow-y:auto;border:1px solid var(--gray-200);border-radius:8px;"></div>
    <div style="display:flex;gap:10px;margin-top:16px;">
      <button onclick="indivSave()" style="flex:1;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save Permissions</button>
      <button onclick="document.getElementById('indiv-modal').style.display='none'" style="padding:11px 22px;background:white;border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<!-- ===== ROLES PANEL ===== -->
<div id="ra-panel-roles">
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="role-search" type="text" placeholder="Search roles…" style="flex:1;min-width:180px;padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <button onclick="roleOpenModal()" style="padding:9px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-user-shield"></i> Add Role</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:12px 16px;text-align:left;font-weight:600;">Role Name</th>
      <th style="padding:12px 16px;text-align:left;font-weight:600;">Slug</th>
      <th style="padding:12px 16px;text-align:left;font-weight:600;">Description</th>
      <th style="padding:12px 16px;text-align:center;font-weight:600;">Users</th>
      <th style="padding:12px 16px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="role-tbody"><tr><td colspan="5" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr></tbody>
  </table>
  <div id="role-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:12px 16px;"></div>
</div>
</div>

<!-- ===== PAGE ACCESS PANEL ===== -->
<div id="ra-panel-access" style="display:none;">
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="access-role-select" onchange="accessLoadForRole()" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:200px;">
    <option value="">— Select Role —</option>
  </select>
  <button onclick="accessSave()" style="padding:9px 18px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;margin-left:auto;"><i class="fas fa-save"></i> Save Access</button>
  <div id="access-msg" style="display:none;padding:8px 14px;background:#d1fae5;color:#065f46;border-radius:8px;font-size:13px;font-weight:500;"></div>
</div>
<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:14px;">Select a role above, then check/uncheck pages that role should have access to. Click "Save Access" to apply.</p>
  <div id="access-content"><p style="text-align:center;color:var(--gray-400);padding:40px;">Select a role above to manage page access</p></div>
</div>
</div>

<!-- ===== USER ACCESS OVERRIDES PANEL ===== -->
<div id="ra-panel-user_access" style="display:none;">
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
  <select id="user-access-user-select" onchange="userAccessLoadForUser()" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:280px;flex:1;">
    <option value="">— Select User to Override Access —</option>
  </select>
  <input type="text" id="user-access-search" placeholder="Filter pages (e.g. inventory, billing)..." oninput="userAccessFilterPages()" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;min-width:220px;">
  <button onclick="userAccessReset()" style="padding:9px 16px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;font-weight:600;cursor:pointer;" title="Reset to standard role access"><i class="fas fa-undo"></i> Reset</button>
  <button onclick="userAccessSave()" style="padding:9px 20px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save Overrides</button>
  <div id="user-access-msg" style="display:none;width:100%;padding:10px 14px;background:#d1fae5;color:#065f46;border-radius:8px;font-size:13px;font-weight:600;margin-top:4px;"></div>
</div>

<!-- User Info Card -->
<div id="user-access-role-info" style="display:none;background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;"></div>

<!-- Pages Grid -->
<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:24px;">
  <div id="user-access-content"><p style="text-align:center;color:var(--gray-400);padding:40px;"><i class="fas fa-user-lock" style="font-size:36px;display:block;margin-bottom:10px;"></i>Select a user above to manage custom page access overrides</p></div>
</div>

<!-- Table: Users with Active Overrides -->
<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
    <h4 style="font-size:15px;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:8px;">
      <i class="fas fa-users-cog" style="color:var(--primary-color);"></i> Users with Active Custom Overrides
    </h4>
    <button onclick="userAccessLoadOverridesList()" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;"><i class="fas fa-sync-alt"></i> Refresh</button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
          <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-700);">User</th>
          <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Role</th>
          <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Overrides Count</th>
          <th style="padding:10px 14px;text-align:left;font-weight:600;color:var(--gray-700);">Granted Pages</th>
          <th style="padding:10px 14px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
        </tr>
      </thead>
      <tbody id="user-overrides-list-tbody">
        <tr><td colspan="5" style="padding:24px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- ===== MENU MANAGEMENT PANEL ===== -->
<div id="ra-panel-menus" style="display:none;">
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;">
  <span style="font-weight:600;color:var(--gray-700);">Parent Menus (Sections)</span>
  <button onclick="menuOpenModal()" style="padding:9px 16px;background:var(--success-color);color:white;border:none;border-radius:8px;cursor:pointer;font-size:13px;"><i class="fas fa-plus"></i> Add Menu</button>
</div>
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Title</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Section Label</th>
      <th style="padding:11px 14px;text-align:left;font-weight:600;">Icon</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Items</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Active</th>
      <th style="padding:11px 14px;text-align:center;font-weight:600;">Actions</th>
    </tr></thead>
    <tbody id="menu-tbody"><tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
  </table>
</div>
</div>

<!-- Role Add/Edit Modal -->
<div id="role-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:540px;position:relative;">
    <button onclick="roleCloseModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="role-modal-title" style="font-size:18px;font-weight:600;margin-bottom:20px;">Add Role</h3>
    <div id="role-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form id="role-form" onsubmit="roleSubmit(event)">
      <input type="hidden" id="role-id">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Role Name *</label>
          <input id="role-name" type="text" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Slug *</label>
          <input id="role-slug" type="text" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"></div>
        <div style="grid-column:1/-1;"><label style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;">Description</label>
          <textarea id="role-description" rows="3" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea></div>
      </div>
      <div style="display:flex;gap:12px;padding-top:16px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="role-submit-btn" style="flex:1;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> <span id="role-submit-label">Create Role</span></button>
        <button type="button" onclick="roleCloseModal()" style="padding:12px 24px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/roles-access.js?v=<?php echo filemtime(__DIR__ . '/assets/js/roles-access.js'); ?>"></script>

<!-- ============================================
HOSPITAL SETTINGS
============================================ -->
<?php elseif ($subModule == 'hospital-settings'): ?>

<?php include __DIR__ . '/includes/hospital-settings.php'; ?>

<!-- ============================================
DEPARTMENT FORM TEMPLATES
============================================ -->
<?php elseif ($subModule == 'department-forms'): ?>

<!-- Toolbar -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="dft-filter-dept" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:160px;">
    <option value="">All Departments</option>
  </select>
  <button onclick="dftOpenModal()" style="padding:9px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
    <i class="fas fa-plus"></i> New Form Template
  </button>
</div>

<!-- Templates List -->
<div style="background:white;border-radius:var(--radius);padding:0 0 8px;box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
      <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Template Name</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Department</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Slug</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);">Fields</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);">Required</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);">Status</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
      </tr>
    </thead>
    <tbody id="dft-tbody">
      <tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
    </tbody>
  </table>
</div>

<!-- Add/Edit Template Modal -->
<div id="dft-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:800px;margin:auto;position:relative;">
    <button onclick="dftCloseModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="dft-modal-title" style="font-size:18px;font-weight:600;margin-bottom:20px;">Form Template</h3>
    <div id="dft-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>

    <!-- Template Details -->
    <div style="margin-bottom:24px;">
      <h4 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:12px;"><i class="fas fa-cog"></i> Template Details</h4>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Department <span style="color:var(--danger-color)">*</span></label>
          <select id="dft-dept" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="">Select Department</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Slug <span style="color:var(--danger-color)">*</span></label>
          <input id="dft-slug" type="text" placeholder="e.g. opd_assessment" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div style="grid-column:1/-1;">
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Template Name <span style="color:var(--danger-color)">*</span></label>
          <input id="dft-name" type="text" placeholder="e.g. OPD Assessment Form" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div style="grid-column:1/-1;">
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Description</label>
          <textarea id="dft-desc" rows="2" placeholder="Brief description of this form" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;"></textarea>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Status</label>
          <select id="dft-status" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Required?</label>
          <select id="dft-required" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="1">Yes - Must be filled</option>
            <option value="0">No - Optional</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Fields Management -->
    <div style="margin-bottom:24px;">
      <h4 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:12px;">
        <i class="fas fa-list"></i> Form Fields
        <button onclick="dftAddField()" style="float:right;padding:4px 12px;background:var(--success-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;">
          <i class="fas fa-plus"></i> Add Field
        </button>
      </h4>
      <div id="dft-fields-container">
        <p style="color:var(--gray-400);font-size:13px;text-align:center;padding:20px;">No fields yet. Click "Add Field" to add form questions.</p>
      </div>
    </div>

    <!-- Modal Actions -->
    <div style="display:flex;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);">
      <button onclick="dftSubmit()" id="dft-submit-btn" style="flex:1;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
        <i class="fas fa-save"></i> Save Template
      </button>
      <button type="button" onclick="dftCloseModal()" style="padding:12px 24px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<script>
const dftAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+?endpoint=departments/department-forms';
const deptAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+?endpoint=hr/departments';
let dftTemplates = [];
let dftCurrentId = null;
let dftFieldCounter = 0;

function dftEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// Load departments
async function dftLoadDepts(selectId){
  try{
    const r=await fetch(deptAPI); const j=await r.json();
    if(!j.success) return;
    const opts=j.data.map(d=>`<option value="${d.id}">${dftEsc(d.name)}</option>`).join('');
    const sel=document.getElementById(selectId);
    if(sel) sel.innerHTML='<option value="">'+(selectId==='dft-filter-dept'?'All Departments':'Select Department')+'</option>'+opts;
  }catch(e){}
}

// Load templates
async function dftLoad(){
  const deptId=document.getElementById('dft-filter-dept').value;
  const url=deptId?dftAPI+'&action=templates&department_id='+deptId:dftAPI+'&action=templates';
  try{
    const r=await fetch(url); const j=await r.json();
    if(!j.success){dftMsg('Error: '+j.message);return;}
    dftTemplates=j.data||[];
    const tb=document.getElementById('dft-tbody');
    if(!dftTemplates.length){
      tb.innerHTML='<tr><td colspan="7" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-file-medical-alt" style="font-size:36px;display:block;margin-bottom:8px;"></i>No form templates found. Create one to get started.</td></tr>';
    }else{
      tb.innerHTML=dftTemplates.map(t=>`
        <tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:12px 16px;font-weight:600;">${dftEsc(t.name)}</td>
          <td style="padding:12px 16px;font-size:13px;">${dftEsc(t.department_name)}</td>
          <td style="padding:12px 16px;font-family:monospace;font-size:13px;color:var(--primary-color);">${dftEsc(t.slug)}</td>
          <td style="padding:12px 16px;text-align:center;font-weight:600;">—</td>
          <td style="padding:12px 16px;text-align:center;">${t.is_required?'<span style="color:var(--danger-color);font-weight:600;">Required</span>':'<span style="color:var(--gray-400);">Optional</span>'}</td>
          <td style="padding:12px 16px;text-align:center;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${t.status==='active'?'#d1fae5':'#fee2e2'};color:${t.status==='active'?'#065f46':'#991b1b'};">${t.status}</span></td>
          <td style="padding:12px 16px;text-align:center;">
            <button onclick="dftEdit(${t.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;padding:4px;" title="Edit"><i class="fas fa-edit"></i></button>
            <button onclick="dftDel(${t.id},'${dftEsc(t.name)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;padding:4px;" title="Delete"><i class="fas fa-trash"></i></button>
          </td>
        </tr>`).join('');
    }
  }catch(e){dftMsg('Error: '+e.message);}
}
function dftMsg(m){document.getElementById('dft-tbody').innerHTML=`<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">${m}</td></tr>`;}

// Open modal for new template
function dftOpenModal(){
  dftCurrentId=null;
  document.getElementById('dft-modal-title').textContent='New Form Template';
  document.getElementById('dft-submit-btn').innerHTML='<i class="fas fa-save"></i> Create Template';
  document.getElementById('dft-name').value='';
  document.getElementById('dft-slug').value='';
  document.getElementById('dft-desc').value='';
  document.getElementById('dft-status').value='active';
  document.getElementById('dft-required').value='1';
  document.getElementById('dft-dept').value='';
  document.getElementById('dft-fields-container').innerHTML='<p style="color:var(--gray-400);font-size:13px;text-align:center;padding:20px;">No fields yet. Click "Add Field" to add form questions.</p>';
  document.getElementById('dft-alert').style.display='none';
  document.getElementById('dft-modal').style.display='flex';
  dftLoadDepts('dft-dept');
}

// Open modal for edit
async function dftEdit(id){
  try{
    const r=await fetch(dftAPI+'&action=templates&id='+id);
    const j=await r.json();
    if(!j.success){alert('Could not load template');return;}
    const t=j.data;
    dftCurrentId=t.id;
    document.getElementById('dft-modal-title').textContent='Edit: '+t.name;
    document.getElementById('dft-submit-btn').innerHTML='<i class="fas fa-save"></i> Update Template';
    document.getElementById('dft-name').value=t.name;
    document.getElementById('dft-slug').value=t.slug;
    document.getElementById('dft-desc').value=t.description||'';
    document.getElementById('dft-status').value=t.status;
    document.getElementById('dft-required').value=t.is_required;
    await dftLoadDepts('dft-dept');
    document.getElementById('dft-dept').value=t.department_id;
    document.getElementById('dft-alert').style.display='none';
    // Load fields
    document.getElementById('dft-fields-container').innerHTML='';
    if(t.fields&&t.fields.length){
      t.fields.forEach(f=>dftRenderField(f));
    } else {
      document.getElementById('dft-fields-container').innerHTML='<p style="color:var(--gray-400);font-size:13px;text-align:center;padding:20px;">No fields yet.</p>';
    }
    document.getElementById('dft-modal').style.display='flex';
  }catch(e){alert('Error loading template');}
}

// Render a field row
function dftRenderField(f){
  const container=document.getElementById('dft-fields-container');
  // Remove empty state
  const empty=container.querySelector('p');
  if(empty&&!container.querySelector('.dft-field-row')) container.innerHTML='';
  const idx=++dftFieldCounter;
  const fId=f.id||'new_'+idx;
  const row=document.createElement('div');
  row.className='dft-field-row';
  row.id='dft-field-'+fId;
  row.style.cssText='background:var(--gray-50);border-radius:8px;padding:14px;margin-bottom:10px;border:1px solid var(--gray-200);';
  row.innerHTML=`
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <input type="hidden" class="dft-field-id" value="${fId}">
      <input type="text" class="dft-field-label" value="${dftEsc(f.field_label||'')}" placeholder="Field Label" style="flex:1;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;margin-right:8px;">
      <input type="text" class="dft-field-key" value="${dftEsc(f.field_key||'')}" placeholder="field_key" style="width:150px;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;font-family:monospace;margin-right:8px;">
      <select class="dft-field-type" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;margin-right:8px;" onchange="dftToggleOptions(this)">
        <option value="text" ${f.field_type==='text'?'selected':''}>Text</option>
        <option value="textarea" ${f.field_type==='textarea'?'selected':''}>Textarea</option>
        <option value="number" ${f.field_type==='number'?'selected':''}>Number</option>
        <option value="select" ${f.field_type==='select'?'selected':''}>Select</option>
        <option value="multi_select" ${f.field_type==='multi_select'?'selected':''}>Multi Select</option>
        <option value="date" ${f.field_type==='date'?'selected':''}>Date</option>
        <option value="datetime" ${f.field_type==='datetime'?'selected':''}>Datetime</option>
        <option value="checkbox" ${f.field_type==='checkbox'?'selected':''}>Checkbox</option>
        <option value="radio" ${f.field_type==='radio'?'selected':''}>Radio</option>
        <option value="section_header" ${f.field_type==='section_header'?'selected':''}>Section Header</option>
      </select>
      <label style="font-size:12px;white-space:nowrap;margin-right:8px;">
        <input type="checkbox" class="dft-field-required" ${f.is_required?'checked':''}> Required
      </label>
      <button onclick="this.closest('.dft-field-row').remove()" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:16px;padding:4px;" title="Remove"><i class="fas fa-times"></i></button>
    </div>
    <div class="dft-field-options" style="display:${['select','multi_select','radio'].includes(f.field_type)?'block':'none'};margin-bottom:8px;">
      <label style="font-size:12px;color:var(--gray-500);display:block;margin-bottom:2px;">Options (one per line)</label>
      <textarea class="dft-field-options-text" rows="3" placeholder="Option 1&#10;Option 2&#10;Option 3" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;">${f.field_options? (Array.isArray(f.field_options)?f.field_options.join('\n'):f.field_options) :''}</textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
      <input type="text" class="dft-field-placeholder" value="${dftEsc(f.placeholder||'')}" placeholder="Placeholder" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      <input type="text" class="dft-field-default" value="${dftEsc(f.default_value||'')}" placeholder="Default value" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
  `;
  container.appendChild(row);
}

function dftAddField(){
  dftRenderField({field_label:'',field_key:'',field_type:'text',field_options:null,placeholder:'',default_value:'',is_required:0});
}

function dftToggleOptions(sel){
  const row=sel.closest('.dft-field-row');
  const optsDiv=row.querySelector('.dft-field-options');
  optsDiv.style.display=['select','multi_select','radio'].includes(sel.value)?'block':'none';
}

// Auto-generate field key from label
document.addEventListener('input',function(e){
  if(e.target.classList.contains('dft-field-label')){
    const keyInput=e.target.closest('.dft-field-row').querySelector('.dft-field-key');
    if(!keyInput.value || keyInput.dataset.auto==='1'){
      keyInput.value=e.target.value.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
      keyInput.dataset.auto='1';
    }
  }
});

// Save template
async function dftSubmit(){
  const btn=document.getElementById('dft-submit-btn');
  const al=document.getElementById('dft-alert');
  al.style.display='none'; btn.disabled=true;
  const deptId=document.getElementById('dft-dept').value;
  const name=document.getElementById('dft-name').value.trim();
  const slug=document.getElementById('dft-slug').value.trim();
  if(!deptId||!name||!slug){al.textContent='Department, Name and Slug are required';al.style.display='block';btn.disabled=false;return;}

  // Collect fields
  const fieldRows=document.querySelectorAll('.dft-field-row');
  const fields=[];
  fieldRows.forEach(row=>{
    const label=row.querySelector('.dft-field-label').value.trim();
    const key=row.querySelector('.dft-field-key').value.trim();
    if(!label||!key) return;
    const optsText=row.querySelector('.dft-field-options-text');
    const options=optsText?optsText.value.split('\n').map(s=>s.trim()).filter(Boolean):null;
    fields.push({
      id: row.querySelector('.dft-field-id').value,
      field_label: label,
      field_key: key,
      field_type: row.querySelector('.dft-field-type').value,
      field_options: options&&options.length?options:null,
      placeholder: row.querySelector('.dft-field-placeholder').value,
      default_value: row.querySelector('.dft-field-default').value,
      is_required: row.querySelector('.dft-field-required').checked?1:0,
      sort_order: Array.from(fieldRows).indexOf(row)
    });
  });

  try{
    // Save template
    const body={department_id:deptId,name,slug,description:document.getElementById('dft-desc').value.trim(),status:document.getElementById('dft-status').value,is_required:document.getElementById('dft-required').value};
    let tId=dftCurrentId;
    if(tId){
      const r=await fetch(dftAPI+'&action=templates&id='+tId,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
      const j=await r.json();
      if(!j.success){al.textContent=j.message||'Update failed';al.style.display='block';btn.disabled=false;return;}
    }else{
      const r=await fetch(dftAPI+'&action=templates',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
      const j=await r.json();
      if(!j.success){al.textContent=j.message||'Create failed';al.style.display='block';btn.disabled=false;return;}
      tId=j.data.id;
    }

    // Save fields - delete existing then re-create
    if(dftCurrentId){
      // Get existing fields
      const fR=await fetch(dftAPI+'&action=fields&template_id='+tId);
      const fJ=await fR.json();
      if(fJ.success&&fJ.data){
        for(const f of fJ.data){
          await fetch(dftAPI+'&action=fields&id='+f.id,{method:'DELETE'});
        }
      }
    }
    for(const f of fields){
      const fBody={template_id:tId,...f};
      delete fBody.id;
      await fetch(dftAPI+'&action=fields',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(fBody)});
    }

    dftCloseModal();
    dftLoad();
  }catch(err){al.textContent='Request failed: '+err.message;al.style.display='block';}
  finally{btn.disabled=false;}
}

function dftCloseModal(){document.getElementById('dft-modal').style.display='none';}

async function dftDel(id,name){
  if(!confirm(`Delete template "${name}"? This cannot be undone.`))return;
  try{const r=await fetch(dftAPI+'&action=templates&id='+id,{method:'DELETE'});const j=await r.json();j.success?dftLoad():alert(j.message);}
  catch(e){alert('Delete failed');}
}

document.getElementById('dft-filter-dept').addEventListener('change',dftLoad);
// Auto-generate slug from name in modal
document.getElementById('dft-name').addEventListener('input',function(){
  if(!dftCurrentId){
    document.getElementById('dft-slug').value=this.value.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
  }
});

dftLoadDepts('dft-filter-dept');
dftLoad();
</script>

<!-- ============================================
BACKUP & RESTORE
============================================ -->
<?php elseif ($subModule == 'backup'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <!-- Backup Section -->
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px;">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900); margin-bottom: 12px;">
                <i class="fas fa-database" style="color: var(--primary-color);"></i> Database Backup
            </h3>
            <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 16px;">
                Create a full backup of your database including all data.
            </p>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                    <i class="fas fa-download"></i> Create Backup Now
                </button>
                <button style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                    <i class="fas fa-clock"></i> Schedule Backup
                </button>
            </div>
            <div style="margin-top: 16px; padding: 12px; background: white; border-radius: 6px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px;">
                    <span style="color: var(--gray-500);">Last Backup:</span>
                    <span style="font-weight: 500;">Never</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-top: 4px;">
                    <span style="color: var(--gray-500);">Backup Size:</span>
                    <span style="font-weight: 500;">0 MB</span>
                </div>
            </div>
        </div>

        <!-- Restore Section -->
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px;">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900); margin-bottom: 12px;">
                <i class="fas fa-upload" style="color: var(--warning-color);"></i> Restore Database
            </h3>
            <p style="font-size: 13px; color: var(--gray-500); margin-bottom: 16px;">
                Restore your database from a previous backup file.
            </p>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <input type="file" accept=".sql,.zip" style="flex: 1; padding: 8px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
                <button style="padding: 10px 20px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                    <i class="fas fa-upload"></i> Restore
                </button>
            </div>
            <div style="margin-top: 12px; padding: 12px; background: #fef3c7; border-radius: 6px; border-left: 4px solid var(--warning-color);">
                <div style="font-size: 12px; color: #92400e;">
                    <i class="fas fa-exclamation-triangle"></i> Restoring will overwrite all current data. Please create a backup first.
                </div>
            </div>
        </div>
    </div>

    <!-- Backup History -->
    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <h3 style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 12px;">Backup History</h3>
        <div style="text-align: center; padding: 30px 20px; background: var(--gray-50); border-radius: 8px;">
            <div style="font-size: 48px; color: var(--gray-300); margin-bottom: 8px;">
                <i class="fas fa-clock"></i>
            </div>
            <p style="color: var(--gray-500);">No backup history available</p>
        </div>
    </div>
</div>

<!-- ============================================
AUDIT LOGS
============================================ -->
<?php elseif ($subModule == 'audit-logs'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Total Events</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Today</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">This Week</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--secondary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">This Month</div>
        </div>
    </div>

    <!-- Filters -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <input type="date" style="padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        <span style="color: var(--gray-500); align-self: center;">to</span>
        <input type="date" style="padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        <select style="padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
            <option value="">All Users</option>
        </select>
        <select style="padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
            <option value="">All Actions</option>
            <option value="login">Login</option>
            <option value="logout">Logout</option>
            <option value="create">Create</option>
            <option value="update">Update</option>
            <option value="delete">Delete</option>
        </select>
        <button style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-search"></i> Filter
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-history"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Audit Logs</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Audit logs will appear here once system activity is recorded.</p>
    </div>
</div>

<!-- ============================================
SYSTEM HEALTH
============================================ -->
<?php elseif ($subModule == 'system-health'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- System Health Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: #d1fae5; border-radius: 8px; text-align: center; border-left: 4px solid var(--success-color);">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">
                <i class="fas fa-check-circle"></i>
            </div>
            <div style="font-size: 13px; color: var(--gray-600); margin-top: 4px;">System Status</div>
            <div style="font-weight: 600; color: #065f46;">Healthy</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Uptime (Days)</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">100%</div>
            <div style="font-size: 13px; color: var(--gray-500);">Server Performance</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Warnings</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Errors</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--secondary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Active Users</div>
        </div>
    </div>

    <!-- System Components -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 500; color: var(--gray-700);">Database</span>
                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #d1fae5; color: #065f46;">
                    <i class="fas fa-check"></i> Connected
                </span>
            </div>
            <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">Response Time: 12ms</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 500; color: var(--gray-700);">Server</span>
                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #d1fae5; color: #065f46;">
                    <i class="fas fa-check"></i> Running
                </span>
            </div>
            <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">CPU: 45% | Memory: 60%</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 500; color: var(--gray-700);">Storage</span>
                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #d1fae5; color: #065f46;">
                    <i class="fas fa-check"></i> OK
                </span>
            </div>
            <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">Used: 0 MB / 10 GB</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 500; color: var(--gray-700);">Backup</span>
                <span style="padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #fee2e2; color: #991b1b;">
                    <i class="fas fa-exclamation"></i> No Backup
                </span>
            </div>
            <div style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">Last backup: Never</div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <h3 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 12px;">
            <i class="fas fa-bolt" style="color: var(--warning-color);"></i> Recent System Events
        </h3>
        <div style="text-align: center; padding: 20px;">
            <p style="color: var(--gray-500); font-size: 13px;">No recent system events</p>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>