<?php
require_once __DIR__ . '/includes/header.php';
global $pdo;

// Role comes from the matching user account (users.employee_id = employees.employee_no);
// shifts come from the roster (all shift types the employee has been assigned)
$employees = $pdo->query("
    SELECT e.id, e.employee_no, e.first_name, e.last_name, e.position, e.status, e.date_joined, d.name AS department_name,
           r.id AS role_id, r.name AS role_name,
           (SELECT GROUP_CONCAT(DISTINCT rsa.shift_type_id) FROM roster_shift_assignments rsa
             WHERE rsa.employee_id = e.id AND rsa.status <> 'cancelled') AS shift_ids
    FROM employees e
    LEFT JOIN departments d ON d.id = e.department_id
    LEFT JOIN users u ON u.employee_id = e.employee_no
    LEFT JOIN roles r ON r.id = u.role_id
    ORDER BY e.first_name, e.last_name
")->fetchAll();
$roles  = $pdo->query("SELECT id, name FROM roles ORDER BY name")->fetchAll();
$shifts = $pdo->query("SELECT id, name, start_time, end_time FROM shift_types WHERE is_active = 1 ORDER BY name, id")->fetchAll();
$sel = 'padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;';
?>
<div style="margin-bottom:20px;">
  <h2 style="font-size:24px;font-weight:600;"><i class="fas fa-user-clock" style="color:var(--primary-color);"></i> Employee History</h2>
  <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">All employees — open the calendar to see which days each one worked</p>
</div>

<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
    <input id="eh-search" oninput="ehFilter()" placeholder="Search name, employee no, department, position..." style="flex:1;min-width:240px;max-width:420px;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    <select id="eh-role" onchange="ehFilter()" style="<?= $sel ?>">
      <option value="">All roles</option>
      <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option><?php endforeach; ?>
    </select>
    <select id="eh-shift" onchange="ehFilter()" style="<?= $sel ?>">
      <option value="">All shifts</option>
      <?php foreach ($shifts as $sh): ?><option value="<?= (int)$sh['id'] ?>"><?= htmlspecialchars($sh['name']) ?> (<?= substr($sh['start_time'], 0, 5) ?>–<?= substr($sh['end_time'], 0, 5) ?>)</option><?php endforeach; ?>
    </select>
    <button onclick="ehReset()" style="padding:9px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Clear filters"><i class="fas fa-times"></i></button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px 12px;text-align:left;">#</th>
        <th style="padding:10px 12px;text-align:left;">Employee</th>
        <th style="padding:10px 12px;text-align:left;">Employee No.</th>
        <th style="padding:10px 12px;text-align:left;">Department</th>
        <th style="padding:10px 12px;text-align:left;">Role</th>
        <th style="padding:10px 12px;text-align:left;">Position</th>
        <th style="padding:10px 12px;text-align:left;">Joined</th>
        <th style="padding:10px 12px;text-align:center;">Status</th>
        <th style="padding:10px 12px;text-align:center;">Action</th>
      </tr></thead>
      <tbody id="eh-tbody">
      <?php foreach ($employees as $i => $e): $name = trim($e['first_name'] . ' ' . $e['last_name']); ?>
        <tr class="eh-row" data-role="<?= (int)$e['role_id'] ?>" data-shifts=",<?= htmlspecialchars($e['shift_ids'] ?? '') ?>," style="border-top:1px solid var(--gray-100);">
          <td class="eh-no" style="padding:10px 12px;font-weight:700;"><?= $i + 1 ?></td>
          <td style="padding:10px 12px;font-weight:600;"><?= htmlspecialchars($name) ?></td>
          <td style="padding:10px 12px;font-family:monospace;color:var(--primary-color);"><?= htmlspecialchars($e['employee_no']) ?></td>
          <td style="padding:10px 12px;"><?= htmlspecialchars($e['department_name'] ?? '—') ?></td>
          <td style="padding:10px 12px;"><?= htmlspecialchars($e['role_name'] ?? '—') ?></td>
          <td style="padding:10px 12px;"><?= htmlspecialchars($e['position'] ?? '—') ?></td>
          <td style="padding:10px 12px;"><?= htmlspecialchars($e['date_joined'] ?? '—') ?></td>
          <td style="padding:10px 12px;text-align:center;"><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:<?= $e['status'] === 'active' ? '#d1fae5' : '#f3f4f6' ?>;color:<?= $e['status'] === 'active' ? '#065f46' : '#4b5563' ?>;"><?= htmlspecialchars($e['status'] ?? '') ?></span></td>
          <td style="padding:10px 12px;text-align:center;">
            <button onclick='ehOpen(<?= (int)$e['id'] ?>, <?= json_encode($name) ?>, <?= json_encode($e['date_joined']) ?>)' style="padding:6px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-calendar-alt"></i> Calendar</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$employees): ?><tr><td colspan="9" style="padding:30px;text-align:center;color:var(--gray-400);">No employees found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Calendar modal -->
<div id="eh-modal" onclick="if(event.target===this)ehClose()" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px;">
  <div style="background:white;border-radius:12px;width:760px;max-width:96vw;padding:24px;margin:auto;position:relative;">
    <button onclick="ehClose()" style="position:absolute;top:14px;right:14px;border:none;background:none;font-size:24px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 style="font-size:17px;font-weight:700;margin:0 0 12px;"><i class="fas fa-calendar-alt" style="color:var(--primary-color);"></i> <span id="eh-name"></span></h3>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
      <div style="display:flex;align-items:center;gap:6px;">
        <button onclick="ehShift(-1)" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;"><i class="fas fa-chevron-left"></i></button>
        <select id="eh-month" onchange="ehLoad()" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></select>
        <select id="eh-year" onchange="ehLoad()" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"></select>
        <button onclick="ehShift(1)" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;"><i class="fas fa-chevron-right"></i></button>
      </div>
      <div id="eh-summary" style="display:flex;gap:12px;font-size:12px;font-weight:600;flex-wrap:wrap;"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;text-align:center;font-size:11px;font-weight:700;color:var(--gray-500);margin-bottom:6px;">
      <div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div>
    </div>
    <div id="eh-grid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;"></div>
    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:14px;font-size:11px;color:var(--gray-600);">
      <span><span style="display:inline-block;width:10px;height:10px;background:#d1fae5;border:1px solid #6ee7b7;border-radius:2px;"></span> Worked</span>
      <span><span style="display:inline-block;width:10px;height:10px;background:#fef3c7;border:1px solid #fcd34d;border-radius:2px;"></span> Late / half-day (worked)</span>
      <span><span style="display:inline-block;width:10px;height:10px;background:#dbeafe;border:1px solid #93c5fd;border-radius:2px;"></span> On leave / holiday</span>
    </div>
    <p style="font-size:11px;color:var(--gray-400);margin:8px 0 0;">Only days with attendance are shown; other days are left blank.</p>
  </div>
</div>

<script>
const ehBase = window.location.origin + (typeof appBasePath !== 'undefined' && appBasePath !== null ? appBasePath : window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')));
const ehMonths = ['January','February','March','April','May','June','July','August','September','October','November','December'];
let ehEmp = null;

function ehReset(){
  ['eh-search','eh-role','eh-shift'].forEach(id => document.getElementById(id).value = '');
  ehFilter();
}
function ehFilter(){
  const q = document.getElementById('eh-search').value.toLowerCase().trim();
  const role = document.getElementById('eh-role').value, shift = document.getElementById('eh-shift').value;
  let n = 0;
  document.querySelectorAll('#eh-tbody .eh-row').forEach(r => {
    const show = (!q || r.textContent.toLowerCase().includes(q))
      && (!role || r.dataset.role === role)
      && (!shift || r.dataset.shifts.includes(',' + shift + ','));
    r.style.display = show ? '' : 'none';
    if(show) r.querySelector('.eh-no').textContent = ++n;
  });
}

function ehOpen(id, name, joined){
  ehEmp = {id, name, joined: joined || ''};
  document.getElementById('eh-name').textContent = name;
  const now = new Date();
  document.getElementById('eh-month').innerHTML = ehMonths.map((m, i) => `<option value="${i}">${m}</option>`).join('');
  const years = []; for(let y = now.getFullYear() + 1; y >= now.getFullYear() - 5; y--) years.push(y);
  document.getElementById('eh-year').innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
  document.getElementById('eh-month').value = now.getMonth();
  document.getElementById('eh-year').value = now.getFullYear();
  document.getElementById('eh-modal').style.display = 'flex';
  ehLoad();
}
function ehClose(){ document.getElementById('eh-modal').style.display = 'none'; }
function ehShift(d){
  const m = document.getElementById('eh-month'), y = document.getElementById('eh-year');
  let mo = parseInt(m.value) + d, yr = parseInt(y.value);
  if(mo < 0){ mo = 11; yr--; } else if(mo > 11){ mo = 0; yr++; }
  if(![...y.options].some(o => parseInt(o.value) === yr)) return;
  m.value = mo; y.value = yr; ehLoad();
}

async function ehLoad(){
  const mo = parseInt(document.getElementById('eh-month').value), yr = parseInt(document.getElementById('eh-year').value);
  const key = yr + '-' + String(mo + 1).padStart(2, '0');
  const grid = document.getElementById('eh-grid');
  grid.innerHTML = '<div style="grid-column:1/-1;padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div>';
  let recs = {};
  try{
    const j = await (await fetch(ehBase + '/api.php?endpoint=hr/attendance&employee_id=' + ehEmp.id + '&month=' + key + '&limit=200')).json();
    if(j.success) (j.data.records || []).forEach(r => { recs[String(r.work_date).substring(0, 10)] = r; });
  }catch(e){}

  const first = new Date(yr, mo, 1), days = new Date(yr, mo + 1, 0).getDate();
  const lead = (first.getDay() + 6) % 7; // Monday first
  const sum = {worked: 0, leave: 0};
  let html = '<div></div>'.repeat(lead);
  for(let d = 1; d <= days; d++){
    const iso = key + '-' + String(d).padStart(2, '0');
    const r = recs[iso];
    let bg = 'var(--gray-50)', bd = 'var(--gray-200)', fg = 'var(--gray-400)', label = '', tip = '';
    if(r && r.status !== 'absent'){
      const st = r.status;
      if(r.approval === 'pending'){
        label = 'Pending'; [bg, bd, fg] = ['#fef3c7', '#fcd34d', '#92400e'];
      } else if(st === 'present' || st === 'late' || st === 'half-day'){
        sum.worked++; label = st === 'present' ? 'Worked' : (st === 'late' ? 'Late' : 'Half day');
        [bg, bd, fg] = st === 'present' ? ['#d1fae5', '#6ee7b7', '#065f46'] : ['#fef3c7', '#fcd34d', '#92400e'];
      } else {
        sum.leave++; label = st === 'holiday' ? 'Holiday' : 'On leave'; [bg, bd, fg] = ['#dbeafe', '#93c5fd', '#1e40af'];
      }
      tip = [r.check_in ? 'In ' + String(r.check_in).substring(0, 5) : '', r.check_out ? 'Out ' + String(r.check_out).substring(0, 5) : '', r.notes || ''].filter(Boolean).join(' · ');
    }
    html += `<div title="${tip.replace(/"/g, '&quot;')}" style="background:${bg};border:1px solid ${bd};color:${fg};border-radius:6px;padding:6px 2px;min-height:52px;text-align:center;">
      <div style="font-size:13px;font-weight:700;">${d}</div><div style="font-size:10px;font-weight:600;margin-top:2px;">${label}</div></div>`;
  }
  grid.innerHTML = html;
  document.getElementById('eh-summary').innerHTML =
    `<span style="color:#065f46;">Worked: ${sum.worked}</span><span style="color:#1e40af;">Leave/Holiday: ${sum.leave}</span>`;
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
