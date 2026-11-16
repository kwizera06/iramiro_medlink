<?php
/**
 * includes/components/activity_log_widget.php
 *
 * Reusable Activity Log widget.
 * Usage:
 *   $alwModule = 'patients';  // required — filters by module
 *   $alwTitle  = 'Patient Activity';  // optional custom title
 *   require __DIR__ . '/includes/components/activity_log_widget.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce Administrator role: only admin can see activity logs across all pages
$isAlwAdmin = false;
if (isset($_SESSION['user_data']['role']) && $_SESSION['user_data']['role'] === 'admin') {
    $isAlwAdmin = true;
} elseif (isset($_SESSION['user_data']['role_slugs']) && in_array('admin', $_SESSION['user_data']['role_slugs'])) {
    $isAlwAdmin = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $isAlwAdmin = true;
} elseif (function_exists('userHasRole') && userHasRole('admin')) {
    $isAlwAdmin = true;
} elseif (!empty($_SESSION['user_id'])) {
    global $pdo;
    if (!isset($pdo)) {
        require_once __DIR__ . '/../database.php';
    }
    if (isset($pdo)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND r.slug = 'admin'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $isAlwAdmin = true;
        } else {
            $uStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $uStmt->execute([$_SESSION['user_id']]);
            if ($uStmt->fetchColumn() === 'admin') {
                $isAlwAdmin = true;
            }
        }
    }
}

// If user is not an administrator, do not render activity log
if (!$isAlwAdmin) {
    return;
}

$alwModule = $alwModule ?? 'system';
$alwTitle  = $alwTitle  ?? 'Recent Activity';
$alwId     = 'alw_' . preg_replace('/[^a-z0-9]/', '_', strtolower($alwModule));
?>
<div id="<?= $alwId ?>_wrap" style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-top:28px;">
  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:15px;font-weight:600;margin:0;">
      <i class="fas fa-history" style="color:var(--primary-color);margin-right:6px;"></i>
      <?= htmlspecialchars($alwTitle) ?>
    </h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="date" id="<?= $alwId ?>_from" onchange="<?= $alwId ?>_load(1)" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <span style="font-size:11px;color:var(--gray-400);">to</span>
      <input type="date" id="<?= $alwId ?>_to" onchange="<?= $alwId ?>_load(1)" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <button onclick="<?= $alwId ?>_load(1)" style="padding:6px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;"><i class="fas fa-search"></i></button>
      <button onclick="<?= $alwId ?>_load(1)" style="padding:6px 10px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>

  <!-- Table -->
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
          <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--gray-600);white-space:nowrap;">Date &amp; Time</th>
          <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Performed By</th>
          <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Action</th>
          <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Description</th>
          <th style="padding:8px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Reference</th>
        </tr>
      </thead>
      <tbody id="<?= $alwId ?>_tbody" data-no-page>
        <tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div id="<?= $alwId ?>_paging" style="display:flex;justify-content:space-between;align-items:center;padding:10px 0 0;margin-top:6px;border-top:1px solid var(--gray-100);"></div>
</div>

<script>
(function(){
  const MOD   = <?= json_encode($alwModule) ?>;
  const WID   = <?= json_encode($alwId) ?>;
  const _base = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
  const API   = window.location.origin + _base + '/api.php?endpoint=activity-log';
  let page    = 1;

  const ACTION_COLORS = {
    CREATE:            'background:#dcfce7;color:#166534',
    UPDATE:            'background:#dbeafe;color:#1e40af',
    DELETE:            'background:#fee2e2;color:#991b1b',
    PAYMENT:           'background:#f3e8ff;color:#6b21a8',
    PAYMENT_INIT:      'background:#fef3c7;color:#92400e',
    PAYMENT_CONFIRMED: 'background:#dcfce7;color:#166534',
    DISPENSE:          'background:#ffedd5;color:#9a3412',
  };

  function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function fmtDate(dt){
    if(!dt) return '—';
    const d = new Date(dt.replace(' ','T'));
    return d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})
      + '<br><span style="font-size:10px;color:var(--gray-400);">'
      + d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit',second:'2-digit'})
      + '</span>';
  }

  async function load(pg){
    page = pg || 1;
    const from  = document.getElementById(WID+'_from').value;
    const to    = document.getElementById(WID+'_to').value;
    const tbody = document.getElementById(WID+'_tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="padding:24px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';

    try {
      const params = new URLSearchParams({ module: MOD, from, to, page, limit: pgSize(WID, 25) });
      const r = await fetch(API + '&' + params);
      const j = await r.json();

      if(!j.success){
        tbody.innerHTML = '<tr><td colspan="5" style="padding:24px;text-align:center;color:#dc2626;">' + esc(j.message || 'Could not load activity log') + '</td></tr>';
        return;
      }

      const { logs, total, total_pages } = j.data;
      if(!logs || !logs.length){
        tbody.innerHTML = '<tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-clipboard-list" style="font-size:20px;display:block;margin-bottom:8px;"></i>No activity recorded for this period.</td></tr>';
        document.getElementById(WID+'_paging').innerHTML = '';
        return;
      }

      tbody.innerHTML = logs.map((row) => {
        const acStyle = ACTION_COLORS[row.action] || 'background:#f3f4f6;color:#374151';
        const act = esc((row.action||'').replace(/_/g,' '));
        return `<tr style="border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
          <td style="padding:8px 10px;font-size:11px;white-space:nowrap;">${fmtDate(row.created_at)}</td>
          <td style="padding:8px 10px;">
            <div style="font-weight:600;font-size:12px;color:var(--gray-800);">${esc(row.user_name||'System')}</div>
            ${row.ip_address ? '<div style="font-size:10px;color:var(--gray-400);">'+esc(row.ip_address)+'</div>' : ''}
          </td>
          <td style="padding:8px 10px;">
            <span style="padding:2px 8px;border-radius:6px;font-size:10px;font-weight:700;${acStyle};">${act}</span>
          </td>
          <td style="padding:8px 10px;color:var(--gray-700);max-width:220px;word-break:break-word;">${esc(row.description||'—')}</td>
          <td style="padding:8px 10px;">
            ${row.record_ref
              ? '<code style="font-size:10px;background:var(--gray-50);padding:2px 6px;border-radius:4px;color:var(--primary-color);border:1px solid var(--gray-200);">'+esc(row.record_ref)+'</code>'
              : '<span style="color:var(--gray-300);">—</span>'}
          </td>
        </tr>`;
      }).join('');

      // Paging
      const pg = document.getElementById(WID+'_paging');
      if(total_pages <= 1){
        pg.innerHTML = `<div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect(WID,25,WID+'_load(1)',[10,25,50,100])}<span style="font-size:11px;color:var(--gray-500);">${total} record${total!==1?'s':''}</span></div><div></div>`;
        return;
      }
      const pd = page===1, nd = page===total_pages;
      let btns = `<button onclick="${WID}_load(${page-1})" ${pd?'disabled':''} style="padding:4px 8px;border:1px solid var(--gray-200);background:${pd?'var(--gray-100)':'white'};border-radius:5px;cursor:${pd?'not-allowed':'pointer'};font-size:11px;"><i class="fas fa-chevron-left"></i></button>`;
      let sp = Math.max(1, page-2), ep = Math.min(total_pages, sp+4);
      if(ep-sp < 4) sp = Math.max(1, ep-4);
      for(let p=sp; p<=ep; p++){
        const act = p === page;
        btns += `<button onclick="${WID}_load(${p})" style="padding:4px 8px;border:1px solid ${act?'var(--primary-color)':'var(--gray-200)'};background:${act?'var(--primary-color)':'white'};color:${act?'white':'var(--gray-700)'};border-radius:5px;cursor:pointer;font-size:11px;font-weight:${act?'600':'400'};">${p}</button>`;
      }
      btns += `<button onclick="${WID}_load(${page+1})" ${nd?'disabled':''} style="padding:4px 8px;border:1px solid var(--gray-200);background:${nd?'var(--gray-100)':'white'};border-radius:5px;cursor:${nd?'not-allowed':'pointer'};font-size:11px;"><i class="fas fa-chevron-right"></i></button>`;

      pg.innerHTML = `
        <div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect(WID,25,WID+'_load(1)',[10,25,50,100])}<span style="font-size:11px;color:var(--gray-500);">Page ${page} of ${total_pages} (${total} records)</span></div>
        <div style="display:flex;gap:4px;">${btns}</div>`;
    } catch(e){
      document.getElementById(WID+'_tbody').innerHTML = '<tr><td colspan="5" style="padding:24px;text-align:center;color:#dc2626;">Error: '+esc(e.message)+'</td></tr>';
    }
  }

  // expose to global scope for button onclick
  window[WID+'_load'] = load;

  // init date defaults to last month
  dfRange(WID+'_from', WID+'_to');

  // initial load
  load(1);
})();
</script>
