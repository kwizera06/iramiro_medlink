<?php
// frontend.php — admin editor for the public landing page (../index.php). Content is stored by includes/site-content.php.
require_once __DIR__ . '/includes/site-content.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/session_check.php';
    require_once __DIR__ . '/includes/database.php';
    require_once __DIR__ . '/includes/auth.php';
    header('Content-Type: application/json');
    $fail = function (string $m, int $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'message' => $m]); exit; };
    if (!isUserAdmin($pdo)) $fail('Administrators only.', 403);
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') $fail('Bad request.', 403);
    if (!is_dir(SITE_DIR) && !@mkdir(SITE_DIR, 0755, true)) $fail('Cannot create folder medlink/uploads/site — create it and make it writable by the web server.', 500);

    if (($_GET['action'] ?? '') === 'upload') {
        $f = $_FILES['image'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) $fail('No image received (max upload size may be too small).');
        if ($f['size'] > 3 * 1024 * 1024) $fail('Image too large (max 3MB).');
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])] ?? null;
        if (!$ext) $fail('Only JPG, PNG, WEBP or GIF images.');
        $name = bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], SITE_DIR . $name)) $fail('Failed to save image.', 500);
        echo json_encode(['success' => true, 'path' => 'medlink/uploads/site/' . $name]);
        exit;
    }

    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) $fail('Invalid data.');
    $clean = site_clean(site_defaults(), $in);
    if (file_put_contents(SITE_FILE, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) $fail('Cannot write medlink/uploads/site/content.json — check folder permissions.', 500);
    echo json_encode(['success' => true]);
    exit;
}

require_once __DIR__ . '/includes/header.php';
if (empty($isAdmin)) { echo '<p style="padding:24px;">Administrators only.</p>'; require_once __DIR__ . '/includes/footer.php'; exit; }
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
?>
<style>
  .fe-wrap{display:grid;grid-template-columns:200px 1fr;gap:20px;align-items:start;}
  .fe-tabs{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:8px;position:sticky;top:12px;}
  .fe-tabs button{display:block;width:100%;text-align:left;padding:10px 12px;border:0;background:none;border-radius:8px;font-size:14px;color:var(--gray-700);cursor:pointer;}
  .fe-tabs button:hover{background:var(--gray-100);}
  .fe-tabs button.on{background:var(--primary-color);color:#fff;}
  .fe-pane{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);padding:24px;}
  .fe-f{margin-bottom:14px;}
  .fe-f > label{display:block;font-size:12px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;}
  .fe-f input[type=text],.fe-f textarea,.fe-f select{width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font:inherit;font-size:14px;}
  .fe-f textarea{resize:vertical;min-height:70px;}
  .fe-box{border:1px solid var(--gray-200);border-radius:10px;padding:14px;margin-bottom:12px;background:var(--gray-50,#fafafa);}
  .fe-bar{display:flex;gap:6px;justify-content:flex-end;margin-bottom:8px;}
  .fe-bar button,.fe-add,.fe-up{border:1px solid var(--gray-200);background:#fff;border-radius:6px;padding:4px 9px;font-size:12px;cursor:pointer;}
  .fe-add{color:var(--primary-color);border-color:var(--primary-color);padding:6px 12px;}
  .fe-img{display:flex;gap:10px;align-items:center;}
  .fe-img img{width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--gray-200);background:#fff;}
  .fe-sub{font-size:13px;font-weight:600;color:var(--gray-800);margin:6px 0 10px;}
  @media (max-width:800px){.fe-wrap{grid-template-columns:1fr;}.fe-tabs{position:static;display:flex;flex-wrap:wrap;}.fe-tabs button{width:auto;}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">Front End</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Edit every text and picture of the public website. Changes go live when you press Save.</p>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="../index.php" target="_blank" class="fe-up" style="padding:10px 18px;font-size:14px;text-decoration:none;color:var(--gray-700);"><i class="fas fa-external-link-alt"></i> View site</a>
    <button id="fe-save" class="fe-add" style="padding:10px 22px;font-size:14px;background:var(--primary-color);color:#fff;"><i class="fas fa-save"></i> Save changes</button>
  </div>
</div>

<div class="fe-wrap">
  <div class="fe-tabs" id="fe-tabs"></div>
  <div class="fe-pane" id="fe-pane"></div>
</div>

<script>
(() => {
  const D = <?= json_encode(site_load(), $flags) ?>;
  const ICONS = <?= json_encode(array_keys(site_icons())) ?>;
  const TPL = {items: {name: '', label: '', logo: ''}, members: {name: '', role: '', photo: ''}};
  const IMG = /^(photo|logo|image)$/;
  const TITLES = {meta: 'Page title', brand: 'Brand', nav: 'Menu', hero: 'Hero', about: 'About us', services: 'Services', team: 'Team (staff)', partners: 'Partners', contact: 'Contact', footer: 'Footer'};
  const hum = k => String(k).replace(/_/g, ' ').replace(/^./, c => c.toUpperCase());
  const src = p => !p || /^(https?:)?\/\/|^\//.test(p) ? p : '../' + p;
  const el = (t, props = {}, ...kids) => { const n = Object.assign(document.createElement(t), props); n.append(...kids); return n; };
  const blank = (v, k) => k === 'icon' ? 'heart' : Array.isArray(v) ? [] : v && typeof v === 'object' ? Object.fromEntries(Object.entries(v).map(([a, b]) => [a, blank(b, a)])) : '';
  let dirty = false, tab = Object.keys(D)[0];
  const mark = () => { dirty = true; };

  function input(o, k) {
    const v = o[k], f = el('div', {className: 'fe-f'}, el('label', {textContent: typeof k === 'number' ? '' : hum(k)}));
    let c;
    if (k === 'icon') {
      c = el('select'); ICONS.forEach(i => c.append(new Option(i, i)));
      c.value = v;
    } else if (typeof v === 'string' && (v.length > 70 || v.includes('\n') || /^(text|intro|about)$/.test(k))) {
      c = el('textarea', {rows: Math.min(8, Math.ceil(v.length / 90) + 1)}); c.value = v;
    } else { c = el('input', {type: 'text'}); c.value = v; }
    c.oninput = c.onchange = () => { o[k] = c.value; mark(); if (prev) prev.src = src(c.value); };
    let prev;
    if (IMG.test(k)) {
      const file = el('input', {type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', hidden: true});
      const btn = el('button', {type: 'button', className: 'fe-up', textContent: 'Upload'});
      prev = el('img', {src: src(v), alt: ''}); prev.onerror = () => prev.style.visibility = 'hidden'; prev.onload = () => prev.style.visibility = '';
      btn.onclick = () => file.click();
      file.onchange = async () => {
        if (!file.files[0]) return;
        const fd = new FormData(); fd.append('image', file.files[0]);
        btn.textContent = 'Uploading…';
        const j = await post('upload', fd);
        btn.textContent = 'Upload';
        if (j.success) { c.value = j.path; c.oninput(); } else alert(j.message);
      };
      f.append(el('div', {className: 'fe-img'}, prev, c, btn, file));
    } else f.append(c);
    return f;
  }

  function node(o, k, draw) {
    const v = o[k];
    if (Array.isArray(v)) {
      const box = el('div', {className: 'fe-f'}, el('label', {textContent: hum(k)}));
      v.forEach((item, i) => {
        const row = el('div', {className: 'fe-box'});
        const bar = el('div', {className: 'fe-bar'});
        [['↑', -1], ['↓', 1], ['Remove', 0]].forEach(([t, d]) => {
          const b = el('button', {type: 'button', textContent: t});
          b.onclick = () => {
            if (!d) { if (!confirm('Remove this item?')) return; v.splice(i, 1); }
            else { const j = i + d; if (j < 0 || j >= v.length) return; [v[i], v[j]] = [v[j], v[i]]; }
            mark(); draw();
          };
          bar.append(b);
        });
        row.append(bar);
        if (item && typeof item === 'object') Object.keys(item).forEach(ik => row.append(node(item, ik, draw)));
        else row.append(input(v, i));
        box.append(row);
      });
      const add = el('button', {type: 'button', className: 'fe-add', textContent: '+ Add'});
      add.onclick = () => { v.push(blank(v.length ? v[0] : (TPL[k] ?? ''), k)); mark(); draw(); };
      box.append(add);
      return box;
    }
    if (v && typeof v === 'object') {
      const box = el('div', {className: 'fe-box'}, el('div', {className: 'fe-sub', textContent: hum(k)}));
      Object.keys(v).forEach(ik => box.append(node(v, ik, draw)));
      return box;
    }
    return input(o, k);
  }

  function draw() {
    const tabs = document.getElementById('fe-tabs'), pane = document.getElementById('fe-pane');
    tabs.replaceChildren(...Object.keys(D).map(k => {
      const b = el('button', {type: 'button', textContent: TITLES[k] || hum(k), className: k === tab ? 'on' : ''});
      b.onclick = () => { tab = k; draw(); };
      return b;
    }));
    pane.replaceChildren(el('h3', {textContent: TITLES[tab] || hum(tab), style: 'margin-bottom:18px;font-size:18px;'}),
      ...Object.keys(D[tab]).map(k => node(D[tab], k, draw)));
  }

  async function post(action, body) {
    const r = await fetch('frontend.php?action=' + action, {method: 'POST', headers: {'X-Requested-With': 'fetch', ...(typeof body === 'string' ? {'Content-Type': 'application/json'} : {})}, body});
    try { return await r.json(); } catch (_) { return {success: false, message: 'Server error (' + r.status + ')'}; }
  }

  document.getElementById('fe-save').onclick = async e => {
    const b = e.currentTarget; b.disabled = true;
    const j = await post('save', JSON.stringify(D));
    b.disabled = false;
    if (j.success) { dirty = false; alert('Saved. The website is updated.'); } else alert(j.message || 'Save failed.');
  };
  window.onbeforeunload = () => dirty ? 'Unsaved changes' : undefined;
  draw();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
