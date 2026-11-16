<?php
// includes/doc-archive.php — Medical Records & Archive: Patient Files (sub=patient), Archived Records (sub=archived), Access Log (sub=access-log).
// Included by documents.php; expects $docSub. API: handlers/documents.php.
$docIsAdmin = isUserAdmin($pdo);
$docTitles = [
  'patient' => ['Patient Files', 'Lab reports, imaging, consents and referrals filed against a patient'],
  'archived' => ['Archived Records', 'Records moved out of the working list. They are kept, never destroyed.'],
  'access-log' => ['Access Log', 'Who uploaded, downloaded, archived or deleted a file'],
];
[$docH, $docSubH] = $docTitles[$docSub];
$docCats = ['general' => 'General', 'lab_report' => 'Lab report', 'imaging' => 'Imaging', 'consent' => 'Consent form', 'referral' => 'Referral',
  'discharge' => 'Discharge summary', 'prescription' => 'Prescription', 'insurance' => 'Insurance', 'identification' => 'ID document', 'other_clinical' => 'Other clinical'];
$inp = 'width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;';
$lbl = 'display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;"><?= htmlspecialchars($docH) ?></h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;"><?= htmlspecialchars($docSubH) ?></p>
  </div>
  <?php if ($docSub === 'patient'): ?>
  <button onclick="document.getElementById('da-modal').style.display='flex'" style="padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;"><i class="fas fa-upload"></i> Upload patient file</button>
  <?php endif; ?>
</div>

<?php if ($docSub !== 'access-log'): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
  <?php foreach (['total' => ['Active files', 'var(--primary-color)'], 'patient' => ['Patient files', '#16a34a'], 'archived' => ['Archived', '#6b7280'], 'retention_due' => ['Retention date passed', '#d97706']] as $k => [$label, $c]): ?>
  <div style="padding:14px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid <?= $c ?>;">
    <div id="da-stat-<?= $k ?>" style="font-size:22px;font-weight:700;color:<?= $c ?>;">&mdash;</div>
    <div style="font-size:11px;color:var(--gray-500);"><?= $label ?></div>
  </div>
  <?php endforeach; ?>
</div>
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;">
  <input id="da-search" placeholder="Search title, patient name or number..." oninput="daDebounce()" style="flex:1;min-width:180px;padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
  <select id="da-cat" onchange="daPage=1;daLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
    <option value="">All categories</option>
    <?php foreach ($docCats as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
  </select>
</div>
<?php endif; ?>

<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
    <?php $th = 'padding:12px 14px;font-weight:600;text-align:'; ?>
    <?php if ($docSub === 'access-log'): ?>
      <th style="<?= $th ?>left;">When</th><th style="<?= $th ?>left;">User</th><th style="<?= $th ?>left;">Action</th><th style="<?= $th ?>left;">Document</th><th style="<?= $th ?>center;">Version</th><th style="<?= $th ?>left;">IP</th>
    <?php else: ?>
      <th style="<?= $th ?>left;">Document</th><th style="<?= $th ?>left;">Patient</th><th style="<?= $th ?>left;">Category</th><th style="<?= $th ?>left;">File</th><th style="<?= $th ?>center;">Version</th><th style="<?= $th ?>left;">Uploaded</th><th style="<?= $th ?>left;">Keep until</th><th style="<?= $th ?>center;">Actions</th>
    <?php endif; ?>
    </tr></thead>
    <tbody id="da-tbody"><tr><td colspan="8" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
  </table>
  <div id="da-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--gray-200);"></div>
</div>

<?php if ($docSub === 'patient'): ?>
<div id="da-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;max-height:92vh;overflow-y:auto;padding:24px;position:relative;">
    <button onclick="document.getElementById('da-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 style="font-size:17px;font-weight:600;margin-bottom:16px;"><i class="fas fa-upload" style="color:var(--primary-color);margin-right:8px;"></i>Upload patient file</h3>
    <div id="da-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
    <form id="da-form" onsubmit="daUpload(event)">
      <div style="margin-bottom:14px;"><label style="<?= $lbl ?>">Title *</label><input id="da-title" required style="<?= $inp ?>"></div>
      <div style="margin-bottom:14px;position:relative;"><label style="<?= $lbl ?>">Patient *</label>
        <input id="da-psearch" autocomplete="off" placeholder="Type patient name, number or phone..." oninput="daSearchPatient()" style="<?= $inp ?>">
        <input id="da-pid" type="hidden">
        <div id="da-pdd" style="display:none;position:absolute;top:100%;left:0;right:0;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:190px;overflow-y:auto;z-index:100;box-shadow:0 4px 14px rgba(0,0,0,.12);"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
        <div><label style="<?= $lbl ?>">Category</label><select id="da-category" style="<?= $inp ?>background:white;"><?php foreach ($docCats as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
        <div><label style="<?= $lbl ?>">Visit</label><select id="da-visit" disabled style="<?= $inp ?>background:white;"><option value="">— select patient first —</option></select></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
        <div><label style="<?= $lbl ?>">Keep (years, 0 = no limit)</label><input id="da-retention" type="number" min="0" max="100" value="10" style="<?= $inp ?>"></div>
        <div><label style="<?= $lbl ?>">Tags</label><input id="da-tags" placeholder="e.g. x-ray, 2026" style="<?= $inp ?>"></div>
      </div>
      <div style="margin-bottom:16px;"><label style="<?= $lbl ?>">File * <span style="font-weight:400;color:var(--gray-400);">(20MB max)</span></label><input id="da-file" type="file" required style="<?= $inp ?>"></div>
      <button type="submit" id="da-btn" style="width:100%;padding:12px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-upload"></i> Upload</button>
    </form>
  </div>
</div>
<?php endif; ?>

<div id="da-ver-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;position:relative;">
    <button onclick="document.getElementById('da-ver-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <div id="da-ver-content"></div>
  </div>
</div>

<script>
const daAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=documents';
const daSub = <?= json_encode($docSub) ?>, daIsAdmin = <?= $docIsAdmin ? 'true' : 'false' ?>, daCats = <?= json_encode($docCats) ?>;
let daPage = 1, daTimer, daPTimer;
const daEsc = s => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
const daSize = b => !b ? '—' : b < 1024 ? b + 'B' : b < 1048576 ? (b/1024).toFixed(1) + 'KB' : (b/1048576).toFixed(1) + 'MB';
const daDl = (id, v) => daAPI + '&action=download&id=' + id + (v ? '&v=' + v : '');
function daDebounce(){ clearTimeout(daTimer); daTimer = setTimeout(() => { daPage = 1; daLoad(); }, 350); }
function daPager(p){
  const pg = document.getElementById('da-pagination');
  pg.innerHTML = p.total_pages > 1
    ? `<span style="font-size:12px;color:var(--gray-500);">Page ${p.page} of ${p.total_pages} (${p.total})</span><div style="display:flex;gap:4px;">
       <button onclick="daPage--;daLoad()" ${daPage<=1?'disabled':''} style="padding:6px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;"><i class="fas fa-chevron-left"></i></button>
       <button onclick="daPage++;daLoad()" ${daPage>=p.total_pages?'disabled':''} style="padding:6px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;"><i class="fas fa-chevron-right"></i></button></div>`
    : `<span style="font-size:12px;color:var(--gray-500);">${p.total} total</span>`;
}
async function daLoad(){
  const tb = document.getElementById('da-tbody');
  if (daSub === 'access-log') {
    try {
      const j = await (await fetch(daAPI + '&action=access_log&page=' + daPage + '&limit=25')).json();
      if (!j.success) { tb.innerHTML = '<tr><td colspan="6" style="padding:30px;text-align:center;color:#dc2626;">' + daEsc(j.message || 'Error') + '</td></tr>'; return; }
      tb.innerHTML = j.data.log.length ? j.data.log.map(l => `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 14px;font-size:12px;">${daEsc((l.created_at||'').substring(0,16))}</td><td style="padding:10px 14px;font-size:12px;">${daEsc(l.user_name||'—')}</td>
        <td style="padding:10px 14px;font-size:12px;font-weight:600;">${daEsc(l.action.replace('_',' '))}</td><td style="padding:10px 14px;font-size:12px;">${daEsc(l.title||('#'+l.document_id))}</td>
        <td style="padding:10px 14px;text-align:center;font-size:12px;">${l.version_number?'v'+l.version_number:'—'}</td><td style="padding:10px 14px;font-size:12px;color:var(--gray-500);">${daEsc(l.ip||'')}</td></tr>`).join('')
        : '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);">Nothing logged yet.</td></tr>';
      daPager(j.data.pagination);
    } catch (e) { tb.innerHTML = '<tr><td colspan="6" style="padding:30px;text-align:center;color:#dc2626;">Failed</td></tr>'; }
    return;
  }
  const params = new URLSearchParams({page: daPage, limit: 25, status: daSub === 'archived' ? 'archived' : 'active'});
  if (daSub === 'patient') params.set('scope', 'patient');
  const q = document.getElementById('da-search').value.trim(), cat = document.getElementById('da-cat').value;
  if (q) params.set('search', q);
  if (cat) params.set('category', cat);
  try {
    const j = await (await fetch(daAPI + '&' + params)).json();
    if (!j.success) { tb.innerHTML = '<tr><td colspan="8" style="padding:30px;text-align:center;color:#dc2626;">' + daEsc(j.message || 'Error') + '</td></tr>'; return; }
    const {documents, stats, pagination} = j.data;
    ['total','patient','archived','retention_due'].forEach(k => { const el = document.getElementById('da-stat-' + k); if (el) el.textContent = stats[k] || 0; });
    if (!documents.length) { tb.innerHTML = '<tr><td colspan="8" style="padding:50px;text-align:center;color:var(--gray-400);">No documents here.</td></tr>'; document.getElementById('da-pagination').innerHTML = ''; return; }
    const today = new Date().toISOString().substring(0, 10), btn = (bg, fg, t, ic, oc) => `<button onclick="${oc}" style="padding:4px 8px;background:${bg};color:${fg};border:none;border-radius:4px;font-size:11px;cursor:pointer;margin-right:3px;" title="${t}"><i class="fas ${ic}"></i></button>`;
    tb.innerHTML = documents.map(d => `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 14px;"><div style="font-weight:600;">${daEsc(d.title)}</div>${d.description?'<div style="font-size:11px;color:var(--gray-500);">'+daEsc(d.description).substring(0,60)+'</div>':''}</td>
      <td style="padding:10px 14px;font-size:12px;">${d.patient_name?daEsc(d.patient_name)+'<div style="font-size:10px;color:var(--gray-400);">'+daEsc(d.patient_no)+'</div>':'—'}</td>
      <td style="padding:10px 14px;"><span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#eff6ff;color:#2563eb;">${daEsc(daCats[d.category]||d.category)}</span></td>
      <td style="padding:10px 14px;font-size:12px;">${daEsc(d.file_name)}<div style="font-size:10px;color:var(--gray-400);">${daSize(d.file_size)}</div></td>
      <td style="padding:10px 14px;text-align:center;">v${d.current_version}</td>
      <td style="padding:10px 14px;font-size:12px;">${daEsc((d.created_at||'').substring(0,10))}<div style="font-size:10px;color:var(--gray-400);">${daEsc(d.uploaded_by_name||'')}</div></td>
      <td style="padding:10px 14px;font-size:12px;color:${d.retention_until&&d.retention_until<=today?'#d97706':'var(--gray-600)'};">${d.retention_until?daEsc(d.retention_until):'—'}</td>
      <td style="padding:10px 14px;text-align:center;white-space:nowrap;"><a href="${daDl(d.id)}" style="padding:4px 8px;background:#eff6ff;color:#2563eb;border-radius:4px;font-size:11px;text-decoration:none;margin-right:3px;display:inline-block;" title="Download"><i class="fas fa-download"></i></a>
        ${btn('#fef3c7','#92400e','Versions','fa-history',`daVersions(${d.id})`)}
        ${daSub==='archived'?btn('#f0fdf4','#16a34a','Restore','fa-box-open',`daState(${d.id},'restore')`):btn('#f1f5f9','#475569','Archive','fa-box-archive',`daState(${d.id},'archive')`)}
        ${daIsAdmin?btn('#fee2e2','#dc2626','Delete','fa-trash',`daDelete(${d.id})`):''}</td></tr>`).join('');
    daPager(pagination);
  } catch (e) { tb.innerHTML = '<tr><td colspan="8" style="padding:30px;text-align:center;color:#dc2626;">Failed</td></tr>'; }
}
async function daState(id, action){
  if (!confirm(action === 'archive' ? 'Archive this document? It stays on file and can be restored.' : 'Restore this document to the working list?')) return;
  const j = await (await fetch(daAPI + '&action=' + action, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id})})).json();
  if (j.success) daLoad(); else alert('Error: ' + j.message);
}
async function daDelete(id){
  if (!confirm('Delete this document? Archive it instead if you may need it later.')) return;
  const j = await (await fetch(daAPI + '&id=' + id, {method:'DELETE'})).json();
  if (j.success) daLoad(); else alert('Error: ' + j.message);
}
async function daVersions(id){
  const box = document.getElementById('da-ver-content');
  box.textContent = 'Loading...'; document.getElementById('da-ver-modal').style.display = 'flex';
  const j = await (await fetch(daAPI + '&id=' + id)).json();
  if (!j.success) { box.textContent = j.message || 'Error'; return; }
  const d = j.data;
  box.innerHTML = `<h3 style="font-size:16px;font-weight:600;margin-bottom:4px;">${daEsc(d.title)}</h3><div style="font-size:12px;color:var(--gray-500);margin-bottom:14px;">${d.patient_name?daEsc(d.patient_name)+' · ':''}${daEsc(daCats[d.category]||d.category)} | Current: v${d.current_version}</div>`
    + d.versions.map(v => `<div style="padding:12px;border:1px solid var(--gray-200);border-radius:8px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
      <div><div style="font-weight:600;font-size:13px;">Version ${v.version_number}</div><div style="font-size:11px;color:var(--gray-500);">${daEsc(v.file_name)} · ${daSize(v.file_size)} · ${daEsc((v.created_at||'').substring(0,16))}</div>${v.change_notes?'<div style="font-size:11px;">'+daEsc(v.change_notes)+'</div>':''}</div>
      <a href="${daDl(d.id, v.version_number)}" style="padding:6px 12px;background:var(--primary-color);color:white;border-radius:6px;font-size:11px;text-decoration:none;"><i class="fas fa-download"></i></a></div>`).join('');
}
function daSearchPatient(){
  document.getElementById('da-pid').value = ''; daVisits('');
  clearTimeout(daPTimer);
  const q = document.getElementById('da-psearch').value.trim(), dd = document.getElementById('da-pdd');
  if (q.length < 2) { dd.style.display = 'none'; return; }
  daPTimer = setTimeout(async () => {
    const j = await (await fetch(daAPI + '&action=patients&q=' + encodeURIComponent(q))).json();
    const rows = j.success ? j.data : [];
    dd.innerHTML = rows.length ? rows.map(p => `<div onclick="daPick(${p.id},this.dataset.n)" data-n="${daEsc(p.name+' ('+p.patient_no+')')}" style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--gray-100);">${daEsc(p.name)} <span style="color:var(--gray-500);font-size:11px;">${daEsc(p.patient_no)}</span></div>`).join('') : '<div style="padding:8px 12px;font-size:12px;color:var(--gray-500);">No patient found</div>';
    dd.style.display = 'block';
  }, 300);
}
function daPick(id, label){ document.getElementById('da-pid').value = id; document.getElementById('da-psearch').value = label; document.getElementById('da-pdd').style.display = 'none'; daVisits(id); }
async function daVisits(pid){
  const sel = document.getElementById('da-visit');
  sel.innerHTML = '<option value="">' + (pid ? 'No specific visit' : '— select patient first —') + '</option>'; sel.disabled = !pid;
  if (!pid) return;
  const j = await (await fetch(daAPI + '&action=patient_visits&patient_id=' + pid)).json();
  if (j.success) j.data.forEach(v => { sel.innerHTML += `<option value="${v.id}">${daEsc(v.visit_no || ('#' + v.id))} · ${daEsc(v.visit_date || '')}</option>`; });
}
async function daUpload(e){
  e.preventDefault();
  const btn = document.getElementById('da-btn'), al = document.getElementById('da-alert');
  const show = (ok, msg) => { al.textContent = msg; al.style.cssText = 'display:block;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;' + (ok ? 'background:#f0fdf4;color:#166534;' : 'background:#fef2f2;color:#991b1b;'); };
  const pid = document.getElementById('da-pid').value;
  if (!pid) { show(false, 'Pick a patient from the list'); return; }
  btn.disabled = true;
  const fd = new FormData();
  fd.append('title', document.getElementById('da-title').value.trim());
  fd.append('document_type', 'other');
  fd.append('category', document.getElementById('da-category').value);
  fd.append('patient_id', pid); fd.append('visit_id', document.getElementById('da-visit').value);
  fd.append('retention_years', document.getElementById('da-retention').value || 0);
  fd.append('tags', document.getElementById('da-tags').value.trim());
  fd.append('file', document.getElementById('da-file').files[0]);
  try {
    const r = await fetch(daAPI, {method:'POST', body: fd}), t = await r.text();
    let j; try { j = JSON.parse(t); } catch (_) { throw new Error('Server error (' + r.status + ')'); }
    if (j.success) { show(true, j.message); document.getElementById('da-form').reset(); daVisits(''); daLoad(); setTimeout(() => { document.getElementById('da-modal').style.display = 'none'; }, 1000); }
    else show(false, j.message);
  } catch (err) { show(false, 'Upload failed: ' + err.message); }
  finally { btn.disabled = false; }
}
daLoad();
</script>
