<?php
require_once __DIR__ . '/includes/header.php';

// Enforce Administrator role: only admin can view activity logs
$isAdmin = false;
if (isset($_SESSION['user_data']['role']) && $_SESSION['user_data']['role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['user_data']['role_slugs']) && in_array('admin', $_SESSION['user_data']['role_slugs'])) {
    $isAdmin = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $isAdmin = true;
} elseif (function_exists('userHasRole') && userHasRole('admin')) {
    $isAdmin = true;
} elseif (!empty($_SESSION['user_id'])) {
    if (isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND r.slug = 'admin'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $isAdmin = true;
        } else {
            $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $uStmt->execute([$_SESSION['user_id']]);
            if ($uStmt->fetchColumn() === 'admin') {
                $isAdmin = true;
            }
        }
    }
}

if (!$isAdmin) {
    echo '<div style="max-width:600px;margin:60px auto;background:#fee2e2;border:1px solid #f87171;border-radius:10px;padding:32px;text-align:center;color:#991b1b;box-shadow:0 4px 12px rgba(0,0,0,0.05);">';
    echo '<i class="fas fa-shield-alt" style="font-size:42px;margin-bottom:14px;color:#dc2626;display:block;"></i>';
    echo '<h3 style="font-size:20px;font-weight:700;margin-bottom:8px;">Access Denied</h3>';
    echo '<p style="font-size:14px;color:#7f1d1d;line-height:1.5;">The Activity Log is restricted and can only be accessed by users with the <strong>Administrator</strong> role.</p>';
    echo '</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}
?>
<?php require_once __DIR__ . '/includes/components/log_nav.php'; ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;"><i class="fas fa-history" style="color:var(--primary-color);"></i> Activity Log</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">All actions performed across the system — who did what and when</p>
  </div>
  <button onclick="alExport()" style="padding:9px 18px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-file-excel"></i> Export CSV</button>
</div>

<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:20px;">
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:4px;">DATE FROM</label>
      <input type="date" id="al-from" onchange="alLoad(1)" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:4px;">DATE TO</label>
      <input type="date" id="al-to" onchange="alLoad(1)" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:4px;">MODULE</label>
      <select id="al-module" onchange="alLoad(1)" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;min-width:140px;">
        <option value="">All Modules</option>
      </select>
    </div>
    <div>
      <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:4px;">USER</label>
      <select id="al-user" onchange="alLoad(1)" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;min-width:160px;">
        <option value="">All Users</option>
      </select>
    </div>
    <div style="flex:1;min-width:180px;">
      <label style="font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:4px;">SEARCH</label>
      <input type="text" id="al-search" placeholder="Description, ref, user..." oninput="alDebounce()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;box-sizing:border-box;">
    </div>
    <button onclick="alLoad(1)" style="padding:9px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
    <button onclick="alReset()" style="padding:9px 14px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:13px;"><i class="fas fa-times"></i> Reset</button>
  </div>
</div>

<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
    <h3 style="font-size:15px;font-weight:600;">System Activity</h3>
    <span id="al-count" style="font-size:12px;color:var(--gray-500);"></span>
  </div>
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);">#</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);white-space:nowrap;">Date &amp; Time</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Performed By</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Action</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Module</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Description</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Reference</th>
        </tr>
      </thead>
      <tbody id="al-tbody" data-no-page>
        <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i></td></tr>
      </tbody>
    </table>
  </div>
  <div id="al-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;margin-top:8px;"></div>
</div>

<script>
const _alBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const alAPI = window.location.origin + _alBase + '/api.php?endpoint=activity-log';
let alPage = 1, alTimer;
let alInitMod = <?= json_encode((string)($_GET['mod'] ?? '')) ?>;   // module chosen from the Documents / Insurance / Settings / Deletions / Logins tabs

const AL_ACTION_STYLES = {
  'CREATE':            'background:#dcfce7;color:#166534',
  'UPDATE':            'background:#dbeafe;color:#1e40af',
  'DELETE':            'background:#fee2e2;color:#991b1b',
  'PAYMENT':           'background:#f3e8ff;color:#6b21a8',
  'PAYMENT_INIT':      'background:#fef3c7;color:#92400e',
  'PAYMENT_CONFIRMED': 'background:#dcfce7;color:#166534',
  'DISPENSE':          'background:#ffedd5;color:#9a3412',
  'SALE':              'background:#e0f2fe;color:#075985',
  'STOCK_ADJUST':      'background:#fef9c3;color:#854d0e',
  'STOCK_TRANSFER':    'background:#ede9fe;color:#5b21b6',
  'PASSWORD_CHANGE':   'background:#fee2e2;color:#991b1b',
  'LOGIN':             'background:#dcfce7;color:#166534',
  'LOGOUT':            'background:#f3f4f6;color:#374151',
  'LOGIN_FAILED':      'background:#fee2e2;color:#991b1b',
};
// field => [old, new] as a small expandable table
function alChanges(json){
  let c; try { c = JSON.parse(json); } catch(e){ return ''; }
  const keys = Object.keys(c || {}); if(!keys.length) return '';
  const rows = keys.map(k => { const v = Array.isArray(c[k]) ? c[k] : ['', c[k]];
    return '<tr><td style="padding:2px 8px 2px 0;color:var(--gray-500);">'+alEsc(k.replace(/_/g,' '))+'</td><td style="padding:2px 8px;color:#991b1b;">'+alEsc(v[0]==null?'':v[0])+'</td><td style="padding:2px 4px;color:var(--gray-400);">&rarr;</td><td style="padding:2px 8px;color:#166534;">'+alEsc(v[1]==null?'':v[1])+'</td></tr>'; }).join('');
  return '<details style="margin-top:4px;"><summary style="cursor:pointer;font-size:11px;color:var(--primary-color);">'+keys.length+' change'+(keys.length>1?'s':'')+'</summary><table style="font-size:11px;margin-top:4px;border-collapse:collapse;">'+rows+'</table></details>';
}
const AL_MODULE_ICONS = {
  'patients':'fas fa-user-injured','visits':'fas fa-calendar-check',
  'invoices':'fas fa-file-invoice-dollar','consultations':'fas fa-stethoscope',
  'pharmacy':'fas fa-pills','hr':'fas fa-users','users':'fas fa-user-cog',
  'afrirem':'fas fa-globe','inventory':'fas fa-boxes',
};

function alEsc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function alFmtDate(dt){
  if(!dt) return '&mdash;';
  const d = new Date(dt.replace(' ','T'));
  const date = d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'});
  const time = d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  return date + '<br><span style="font-size:11px;color:var(--gray-400);">' + time + '</span>';
}

function alDebounce(){ clearTimeout(alTimer); alTimer = setTimeout(()=>alLoad(1),350); }

function alReset(){
  dfRange('al-from','al-to');
  alInitMod = '';
  document.getElementById('al-module').value = '';
  document.getElementById('al-user').value   = '';
  document.getElementById('al-search').value = '';
  alLoad(1);
}

async function alLoad(page){
  alPage = page || 1;
  const from   = document.getElementById('al-from').value;
  const to     = document.getElementById('al-to').value;
  const module = document.getElementById('al-module').value || alInitMod;
  const userId = document.getElementById('al-user').value;
  const search = document.getElementById('al-search').value;
  const tbody  = document.getElementById('al-tbody');
  tbody.innerHTML = '<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i></td></tr>';

  try {
    const params = new URLSearchParams({from, to, page:alPage, limit:pgSize('al',50)});
    if(module)  params.append('module', module);
    if(userId)  params.append('user_id', userId);
    if(search)  params.append('search', search);

    const r = await fetch(alAPI + '&' + params);
    const j = await r.json();
    if(!j.success){ tbody.innerHTML = '<tr><td colspan="7" style="padding:40px;text-align:center;color:#dc2626;">' + alEsc(j.message || 'Failed to load activity log.') + '</td></tr>'; return; }

    const { logs, total, total_pages, modules, users } = j.data;
    document.getElementById('al-count').textContent = total.toLocaleString() + ' record' + (total!==1?'s':'');

    // Populate module dropdown
    if(modules && modules.length){
      const sel = document.getElementById('al-module');
      const cur = sel.value || alInitMod;
      if(!modules.includes('deletions')) modules.push('deletions');
      sel.innerHTML = '<option value="">All Modules</option>' +
        modules.map(m=>`<option value="${alEsc(m)}"${m===cur?' selected':''}>${alEsc(m.charAt(0).toUpperCase()+m.slice(1))}</option>`).join('');
    }
    // Populate user dropdown
    if(users && users.length){
      const sel = document.getElementById('al-user');
      const cur = sel.value;
      sel.innerHTML = '<option value="">All Users</option>' +
        users.map(u=>`<option value="${alEsc(u.user_id)}"${String(u.user_id)===cur?' selected':''}>${alEsc(u.user_name||'User #'+u.user_id)}</option>`).join('');
    }

    if(!logs.length){
      tbody.innerHTML = '<tr><td colspan="7" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-clipboard-list" style="font-size:28px;display:block;margin-bottom:10px;"></i>No activity found for this period.</td></tr>';
      document.getElementById('al-pagination').innerHTML = '';
      return;
    }

    const start = (alPage-1)*pgSize('al',50);
    tbody.innerHTML = logs.map((row,i) => {
      const style  = AL_ACTION_STYLES[row.action] || 'background:#f3f4f6;color:#374151';
      const icon   = AL_MODULE_ICONS[row.module]  || 'fas fa-dot-circle';
      const action = alEsc((row.action||'').replace(/_/g,' '));
      return `<tr style="border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <td style="padding:10px 12px;color:var(--gray-400);font-size:12px;">${start+i+1}</td>
        <td style="padding:10px 12px;font-size:12px;white-space:nowrap;">${alFmtDate(row.created_at)}</td>
        <td style="padding:10px 12px;">
          <div style="font-weight:600;font-size:13px;color:var(--gray-800);">${alEsc(row.user_name||'System')}</div>
          ${row.ip_address ? '<div style="font-size:10px;color:var(--gray-400);">'+alEsc(row.ip_address)+'</div>' : ''}
        </td>
        <td style="padding:10px 12px;">
          <span style="padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700;${style};">${action}</span>
        </td>
        <td style="padding:10px 12px;font-size:12px;color:var(--gray-600);">
          <i class="${icon}" style="color:var(--primary-color);margin-right:5px;"></i>${alEsc(row.module||'')}
        </td>
        <td style="padding:10px 12px;font-size:13px;color:var(--gray-700);max-width:260px;word-break:break-word;">${alEsc(row.description||'&mdash;')}${row.changes ? alChanges(row.changes) : ''}${row.endpoint ? '<div style="font-size:10px;color:var(--gray-400);">'+alEsc(row.endpoint)+'</div>' : ''}</td>
        <td style="padding:10px 12px;">
          ${row.record_ref
            ? '<code style="font-size:11px;background:var(--gray-50);padding:2px 7px;border-radius:4px;color:var(--primary-color);border:1px solid var(--gray-200);">'+alEsc(row.record_ref)+'</code>'
            : '<span style="color:var(--gray-300);">&mdash;</span>'}
        </td>
      </tr>`;
    }).join('');

    alRenderPaging(total, total_pages);
  } catch(e){
    tbody.innerHTML = '<tr><td colspan="7" style="padding:40px;text-align:center;color:#dc2626;">Error: '+alEsc(e.message)+'</td></tr>';
  }
}

function alRenderPaging(total, totalPages){
  const pg = document.getElementById('al-pagination');
  if(totalPages<=1){ pg.innerHTML=`<div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect('al',50,'alLoad(1)',[10,25,50,100])}<span style="font-size:12px;color:var(--gray-500);">${total.toLocaleString()} record${total!==1?'s':''}</span></div><div></div>`; return; }
  const pd=alPage===1, nd=alPage===totalPages;
  let b=`<button onclick="alLoad(${alPage-1})" ${pd?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${pd?'var(--gray-100)':'white'};border-radius:6px;cursor:${pd?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-left"></i></button>`;
  let sp=Math.max(1,alPage-3),ep=Math.min(totalPages,sp+6); if(ep-sp<6) sp=Math.max(1,ep-6);
  for(let p=sp;p<=ep;p++){
    const a=p===alPage;
    b+=`<button onclick="alLoad(${p})" style="padding:6px 10px;border:1px solid ${a?'var(--primary-color)':'var(--gray-200)'};background:${a?'var(--primary-color)':'white'};color:${a?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:12px;font-weight:${a?'600':'400'};">${p}</button>`;
  }
  b+=`<button onclick="alLoad(${alPage+1})" ${nd?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${nd?'var(--gray-100)':'white'};border-radius:6px;cursor:${nd?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-right"></i></button>`;
  pg.innerHTML=`<div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect('al',50,'alLoad(1)',[10,25,50,100])}<span style="font-size:12px;color:var(--gray-500);">Page ${alPage} of ${totalPages} (${total.toLocaleString()} records)</span></div><div style="display:flex;gap:4px;">${b}</div>`;
}

async function alExport(){
  const from=document.getElementById('al-from').value, to=document.getElementById('al-to').value;
  const module=document.getElementById('al-module').value, userId=document.getElementById('al-user').value;
  const search=document.getElementById('al-search').value;
  const params=new URLSearchParams({from,to,limit:1000,page:1});
  if(module) params.append('module',module);
  if(userId) params.append('user_id',userId);
  if(search) params.append('search',search);
  const r=await fetch(alAPI+'&'+params); const j=await r.json();
  if(!j.success||!j.data.logs.length){ alert('No data to export'); return; }
  let csv='Date & Time,Performed By,IP,Action,Module,Description,Reference\n';
  j.data.logs.forEach(row=>{
    csv+=`"${(row.created_at||'').replace(/"/g,'""')}","${(row.user_name||'System').replace(/"/g,'""')}","${(row.ip_address||'').replace(/"/g,'""')}","${(row.action||'').replace(/"/g,'""')}","${(row.module||'').replace(/"/g,'""')}","${(row.description||'').replace(/"/g,'""')}","${(row.record_ref||'').replace(/"/g,'""')}"\n`;
  });
  const url=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'}));
  const a=document.createElement('a'); a.href=url; a.download='activity_log_'+from+'_to_'+to+'.csv'; a.click(); URL.revokeObjectURL(url);
}

// Init
(function(){
  dfRange('al-from','al-to');
  alLoad(1);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
