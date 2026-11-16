<?php
// department_file.php — list of departments (from the database) with add / edit / delete
require_once __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">Departments</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Add, edit and delete the hospital departments used across the system</p>
  </div>
  <button onclick="dpOpen()" style="padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-plus"></i> Add Department</button>
</div>

<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;">
  <input id="dp-search" type="text" placeholder="Search name, code, location…" style="width:100%;max-width:360px;padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <select id="dp-status" onchange="dpLoad()" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;margin-left:8px;"><option value="active">Active</option><option value="inactive">Inactive</option><option value="all">All</option></select>
</div>

<div style="background:white;border-radius:var(--radius);padding:0 0 8px;box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
      <?php foreach (['Code','Department','Location','Phone','Email','Staff'] as $h): ?>
      <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);"><?php echo $h; ?></th>
      <?php endforeach; ?>
      <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
    </tr></thead>
    <tbody id="dp-tbody" data-no-page><tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
  </table>
</div>

<!-- Add / Edit modal -->
<div id="dp-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:520px;position:relative;">
    <button onclick="dpClose()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 id="dp-title" style="font-size:18px;font-weight:600;margin-bottom:20px;">Add Department</h3>
    <div id="dp-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <form onsubmit="dpSave(event)">
      <input type="hidden" id="dp-id">
      <style>#dp-modal label{display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;}
             #dp-modal input,#dp-modal textarea{width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;margin-bottom:14px;}</style>
      <label>Department name <span style="color:var(--danger-color)">*</span></label><input id="dp-name" type="text" required>
      <label>Code <small style="color:var(--gray-400)">(auto from name if empty)</small></label><input id="dp-code" type="text" maxlength="20">
      <label>Location</label><input id="dp-location" type="text">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div><label>Phone</label><input id="dp-phone" type="text"></div>
        <div><label>Email</label><input id="dp-email" type="email"></div>
      </div>
      <label>Description</label><textarea id="dp-desc" rows="2"></textarea>
      <div style="display:flex;gap:12px;">
        <button type="submit" id="dp-btn" style="flex:1;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save</button>
        <button type="button" onclick="dpClose()" style="padding:12px 24px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
var _hrBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const dpAPI = _hrBase + '/api.php?endpoint=hr/departments';
const dpEsc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
let dpRows = [];
const $dp = id => document.getElementById(id);

async function dpLoad(){
  try {
    const j = await (await fetch(dpAPI + '&all=1&status=' + $dp('dp-status').value)).json();
    if (!j.success) { $dp('dp-tbody').innerHTML = `<tr><td colspan="7" style="padding:40px;text-align:center;">${dpEsc(j.message)}</td></tr>`; return; }
    dpRows = j.data; dpRender();
  } catch (e) { $dp('dp-tbody').innerHTML = '<tr><td colspan="7" style="padding:40px;text-align:center;">Failed to load</td></tr>'; }
}
function dpRender(){
  const q = $dp('dp-search').value.toLowerCase();
  const rows = dpRows.filter(r => [r.name, r.code, r.location].join(' ').toLowerCase().includes(q));
  $dp('dp-tbody').innerHTML = rows.length ? rows.map(r => `<tr style="border-bottom:1px solid var(--gray-100);">
    <td style="padding:12px 16px;">${dpEsc(r.code)}</td>
    <td style="padding:12px 16px;font-weight:500;">${dpEsc(r.name)}${r.is_active==1?'':' <span style="font-size:11px;background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;">Inactive</span>'}</td>
    <td style="padding:12px 16px;">${dpEsc(r.location)}</td>
    <td style="padding:12px 16px;">${dpEsc(r.phone)}</td>
    <td style="padding:12px 16px;">${dpEsc(r.email)}</td>
    <td style="padding:12px 16px;">${r.staff}</td>
    <td style="padding:12px 16px;text-align:center;white-space:nowrap;">
      <button onclick="dpOpen(${r.id})" title="Edit" style="padding:5px 10px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;color:var(--primary-color);"><i class="fas fa-edit"></i></button>
      <button onclick="dpToggle(${r.id})" title="${r.is_active==1?'Deactivate':'Activate'}" style="padding:5px 10px;background:${r.is_active==1?'#fef3c7':'#d1fae5'};border:none;border-radius:6px;cursor:pointer;color:${r.is_active==1?'#b45309':'#047857'};"><i class="fas ${r.is_active==1?'fa-toggle-off':'fa-toggle-on'}"></i> ${r.is_active==1?'Deactivate':'Activate'}</button>
      <button onclick="dpDel(${r.id})" title="Delete" style="padding:5px 10px;background:#fee2e2;border:none;border-radius:6px;cursor:pointer;color:#dc2626;"><i class="fas fa-trash"></i></button>
    </td></tr>`).join('') : '<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">No departments</td></tr>';
}
function dpOpen(id){
  const r = dpRows.find(x => x.id == id) || {};
  $dp('dp-id').value = r.id || '';
  $dp('dp-name').value = r.name || ''; $dp('dp-code').value = r.code || ''; $dp('dp-location').value = r.location || '';
  $dp('dp-phone').value = r.phone || ''; $dp('dp-email').value = r.email || ''; $dp('dp-desc').value = r.description || '';
  $dp('dp-title').textContent = r.id ? 'Edit Department' : 'Add Department';
  $dp('dp-alert').style.display = 'none'; $dp('dp-modal').style.display = 'flex'; $dp('dp-name').focus();
}
function dpClose(){ $dp('dp-modal').style.display = 'none'; }
async function dpSave(e){
  e.preventDefault();
  const id = $dp('dp-id').value, btn = $dp('dp-btn'), al = $dp('dp-alert');
  al.style.display = 'none'; btn.disabled = true;
  const body = {name:$dp('dp-name').value, code:$dp('dp-code').value, location:$dp('dp-location').value,
                phone:$dp('dp-phone').value, email:$dp('dp-email').value, description:$dp('dp-desc').value};
  try {
    const j = await (await fetch(id ? dpAPI + '&id=' + id : dpAPI, {method:id ? 'PUT' : 'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)})).json();
    if (j.success) { dpClose(); dpLoad(); } else { al.textContent = j.message || 'Save failed'; al.style.display = 'block'; }
  } catch (err) { al.textContent = 'Request failed: ' + err.message; al.style.display = 'block'; }
  btn.disabled = false;
}
async function dpToggle(id){
  const r = dpRows.find(x => x.id == id);
  if (r.is_active == 1 && !confirm(`Deactivate "${r.name}"? It will be hidden from lists; records are kept.`)) return;
  try {
    const j = await (await fetch(dpAPI + '&action=set_active&id=' + id, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({is_active: r.is_active == 1 ? 0 : 1})})).json();
    j.success ? dpLoad() : alert(j.message);
  } catch (e) { alert('Request failed'); }
}
async function dpDel(id){
  const r = dpRows.find(x => x.id == id);
  if (!confirm(`Permanently delete "${r.name}"? This cannot be undone. To just hide it, use Deactivate.`)) return;
  try {
    const j = await (await fetch(dpAPI + '&id=' + id, {method:'DELETE'})).json();
    j.success ? dpLoad() : alert(j.message);
  } catch (e) { alert('Delete failed'); }
}
$dp('dp-search').addEventListener('input', dpRender);
dpLoad();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
