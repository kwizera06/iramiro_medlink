<?php
// inventory-workspace.php — one place for every store: pick a store or a department on the left, then Stock / Report.
require_once __DIR__ . '/includes/header.php';
?>
<style>
.iw{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;align-items:start;color:#152238}
.iw-top{background:#fff;border-radius:12px;box-shadow:var(--shadow);padding:12px 14px;margin-bottom:16px}
.iw-groups{display:flex;gap:8px;margin-bottom:10px}
.iw-gbtn{border:1px solid #d6dde8;background:#fff;border-radius:10px;padding:8px 14px;font-size:13px;font-weight:600;cursor:pointer;color:#334155;transition:transform .15s ease-out,background .15s ease-out}
.iw-gbtn:active{transform:scale(.97)}.iw-gbtn.on{background:#2563eb;color:#fff;border-color:#2563eb}
.iw-slide{display:flex;gap:6px;overflow-x:auto;padding-bottom:6px;scroll-snap-type:x proximity}
.iw-slide .iw-item{flex:none;width:auto;white-space:nowrap;border:1px solid #d6dde8;border-radius:99px;padding:5px 12px;font-size:12px;scroll-snap-align:start}
.iw-slide .iw-item.on{border-color:#2563eb}
.iw-slide .iw-empty{padding:6px}
.iw-side,.iw-main{background:#fff;border-radius:12px;box-shadow:var(--shadow)}
.iw-side{padding:12px;position:sticky;top:12px;max-height:calc(100vh - 110px);overflow:auto}
.iw-side h4{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#6b7890;margin:12px 6px 6px}
.iw-item{display:flex;justify-content:space-between;gap:8px;width:100%;text-align:left;border:0;background:none;padding:8px 10px;border-radius:8px;font-size:13px;cursor:pointer;transition:background .15s ease-out,transform .15s ease-out}
.iw-item:hover{background:#f1f5f9}.iw-item:active{transform:scale(.98)}
.iw-item.on{background:#e8f0fe;color:#1d4ed8;font-weight:600}.iw-item small{color:#6b7890;font-variant-numeric:tabular-nums}
.iw-main{padding:18px;min-width:0}
.iw-bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
.iw-bar input,.iw-bar select{border:1px solid #d6dde8;border-radius:8px;padding:8px 10px;font-size:13px;background:#fff}
.iw-btn{border:0;border-radius:8px;padding:8px 12px;font-size:13px;cursor:pointer;background:#eef1f5;color:#334155;transition:transform .15s ease-out,background .15s ease-out}
a.iw-btn{text-decoration:none;display:inline-block}.iw-btn:active{transform:scale(.97)}.iw-btn.pri{background:#2563eb;color:#fff}.iw-btn.dan{background:#fee2e2;color:#b91c1c}
.iw-tabs{display:flex;gap:4px;border-bottom:1px solid #e7ebf1;margin-bottom:14px}
.iw-tab{border:0;background:none;padding:9px 14px;font-size:14px;cursor:pointer;color:#6b7890;border-bottom:2px solid transparent}
.iw-tab.on{color:#1d4ed8;border-color:#2563eb;font-weight:600}
.iw-chip{border:1px solid #d6dde8;background:#fff;border-radius:99px;padding:5px 11px;font-size:12px;cursor:pointer}.iw-chip.on{background:#2563eb;color:#fff;border-color:#2563eb}
.iw table{width:100%;border-collapse:collapse;font-size:13px}.iw th{text-align:left;color:#6b7890;font-weight:600;padding:8px;border-bottom:1px solid #e7ebf1;white-space:nowrap}
.iw td{padding:8px;border-bottom:1px solid #f1f4f8}.iw td.n,.iw th.n{text-align:right;font-variant-numeric:tabular-nums}
.iw-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}
.iw-card{background:#f8fafc;border-radius:10px;padding:10px 12px}.iw-card small{color:#6b7890;display:block}.iw-card strong{font-size:18px;font-variant-numeric:tabular-nums}
.iw-paid{color:#047857;font-weight:600}.iw-unpaid{color:#b45309;font-weight:600}.iw-tag{font-size:11px;padding:2px 7px;border-radius:99px;background:#eef1f5}
.iw-tag.med{background:#dbeafe;color:#1d4ed8}.iw-tag.exp{background:#fee2e2;color:#b91c1c}.iw-tag.low{background:#fef3c7;color:#92400e}
.iw-empty{padding:30px;text-align:center;color:#6b7890}
#iw-modal{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9999;align-items:center;justify-content:center}
#iw-modal>div{background:#fff;border-radius:12px;padding:20px;width:min(440px,92vw)}#iw-modal label{display:block;font-size:12px;color:#6b7890;margin:10px 0 3px}
#iw-modal input,#iw-modal select{width:100%;border:1px solid #d6dde8;border-radius:8px;padding:8px}
@media(prefers-reduced-motion:reduce){.iw *{transition:none!important}}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
  <div><h2 style="font-size:24px;font-weight:600">Inventory</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px">Every store and department in one place</p></div>
  <div class="iw-bar" style="margin:0">
    <input id="iw-where" placeholder="Where is an item? e.g. paracetamol" style="width:260px" autocomplete="off">
  </div>
</div>
<div id="iw-where-out" class="iw" style="display:none;background:#fff;border-radius:10px;box-shadow:var(--shadow);padding:10px;margin-bottom:12px"></div>

<div class="iw-top">
  <div class="iw-groups"><button class="iw-gbtn on" data-g="stores">Shared stores</button><button class="iw-gbtn" data-g="depts">Departments</button></div>
  <div id="iw-stores" class="iw-slide"></div>
  <div id="iw-dwrap" style="display:none">
    <input id="iw-dsearch" placeholder="Find department" style="width:220px;border:1px solid #d6dde8;border-radius:8px;padding:6px 9px;font-size:13px;margin-bottom:8px">
    <div id="iw-depts" class="iw-slide"></div>
  </div>
</div>

<div class="iw">
  <section class="iw-main">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px">
      <h3 id="iw-title" style="font-size:18px">All shared stores</h3><span id="iw-sum" style="color:#6b7890;font-size:13px"></span>
    </div>
    <div class="iw-tabs"><button class="iw-tab on" data-t="stock">Stock</button><button class="iw-tab" data-t="report">Report</button></div>

    <div id="iw-stock">
      <div class="iw-bar">
        <input id="iw-q" placeholder="Search name, code, batch" style="min-width:220px">
        <select id="iw-kind"><option value="">Medical + non-medical</option><option value="medical">Medical</option><option value="non_medical">Non-medical</option></select>
        <select id="iw-cat"><option value="">All categories</option></select>
        <button class="iw-chip" data-f="low">Low stock</button><button class="iw-chip" data-f="expiring">Expiring ≤90d</button><button class="iw-chip" data-f="expired">Expired</button>
        <button class="iw-btn pri" id="iw-use" style="display:none;margin-left:auto">Record use</button>
      </div>
      <div id="iw-stock-out"></div>
    </div>

    <div id="iw-report" style="display:none">
      <div class="iw-bar">
        <input type="date" id="r-from"><input type="date" id="r-to">
        <select id="r-dept"><option value="">All departments</option></select>
        <select id="r-person"><option value="">All staff</option></select>
        <select id="r-item"><option value="">All items</option></select>
        <select id="r-kind"><option value="">Medical + non-medical</option><option value="medical">Medical</option><option value="non_medical">Non-medical</option></select>
        <button class="iw-btn pri" id="r-go">Show</button>
      </div>
      <div id="iw-report-out"></div>
    </div>
  </section>
</div>

<div id="iw-modal"><div id="iw-modal-box"></div></div>

<script>
(() => {
const API = window.location.origin + ((typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (location.pathname.substring(0, location.pathname.lastIndexOf('/')) || '')) + '/api.php?endpoint=inventory/inventory-management&action=';
const $ = id => document.getElementById(id);
const esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
const num = v => Number(v || 0).toLocaleString(undefined, {maximumFractionDigits: 2});
const get = async (a, q = {}) => { const r = await fetch(API + a + '&' + new URLSearchParams(Object.entries(q).filter(([, v]) => v !== '' && v != null))); return r.json(); };
const post = async (a, body) => { const r = await fetch(API + a, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)}); return r.json(); };
const toast = (m, ok = true) => { const t = document.createElement('div'); t.textContent = m; t.className = 'iw-toast'; t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;padding:10px 14px;border-radius:8px;color:#fff;background:' + (ok ? '#047857' : '#b91c1c'); document.body.appendChild(t); setTimeout(() => t.remove(), 3500); };
const debounce = (f, ms = 250) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), ms); }; };

function showGroup(g) { $('iw-stores').style.display = g === 'stores' ? '' : 'none'; $('iw-dwrap').style.display = g === 'depts' ? '' : 'none'; document.querySelectorAll('.iw-gbtn').forEach(x => x.classList.toggle('on', x.dataset.g === g)); }
let scope = {}, manage = false, depts = [], lookups = null, flag = '';

async function loadSide() {
  const j = await get('ws_stores'); if (!j.success) return;
  manage = j.data.can_manage; depts = j.data.departments;
  $('iw-stores').innerHTML = `<button class="iw-item ${!scope.store_id && !scope.department_id ? 'on' : ''}" data-s="">All shared stores</button>` +
    j.data.stores.map(s => `<button class="iw-item ${scope.store_id == s.id ? 'on' : ''}" data-s="${s.id}" data-n="${esc(s.name)}">${esc(s.name)}<small>${num(s.qty)}</small></button>`).join('');
  drawDepts();
  // Department staff have no shared stores: open their own department straight away
  if (!j.data.stores.length) showGroup('depts');
  if (!j.data.stores.length && depts.length === 1 && !scope.department_id) pick({department_id: +depts[0].id, name: depts[0].name + ' — department store'});
}
function drawDepts() {
  const f = $('iw-dsearch').value.toLowerCase();
  $('iw-depts').innerHTML = depts.filter(d => d.name.toLowerCase().includes(f)).map(d =>
    `<button class="iw-item ${scope.department_id == d.id ? 'on' : ''}" data-d="${d.id}" data-n="${esc(d.name)}">${esc(d.name)}<small>${num(d.qty)}</small></button>`).join('') || '<div class="iw-empty">No department</div>';
}
function pick(s) { scope = s; $('iw-title').textContent = s.name || 'All shared stores'; $('iw-use').style.display = s.department_id ? '' : 'none'; loadSide(); loadStock(); if (s.department_id) $('r-dept').value = s.department_id; }

async function loadStock() {
  const j = await get('ws_stock', {store_id: scope.store_id, department_id: scope.department_id, q: $('iw-q').value, kind: $('iw-kind').value, category_id: $('iw-cat').value, flag});
  if (!j.success) { $('iw-stock-out').innerHTML = `<div class="iw-empty">${esc(j.message)}</div>`; return; }
  const d = j.data; $('iw-sum').textContent = `${num(d.qty)} units · ${num(d.value)} RWF`;
  if (!d.rows.length) { $('iw-stock-out').innerHTML = `<div class="iw-empty">${scope.department_id ? 'This department has no stock yet. Stock arrives when a request is approved and transferred.' : 'No stock matches.'}</div>`; return; }
  // One row per item per store; click it to see the batches (edit / void work on a batch)
  const groups = {};
  d.rows.forEach(r => { (groups[r.item_id + '-' + r.store_id] = groups[r.item_id + '-' + r.store_id] || []).push(r); });
  const tag = r => `<span class="iw-tag ${r.kind === 'medical' ? 'med' : ''}">${r.kind === 'medical' ? 'Medical' : 'Non-medical'}</span>`;
  $('iw-stock-out').innerHTML = `<div style="overflow:auto"><table><thead><tr><th>Item</th><th>Type</th><th>Store</th><th class="n">Batches</th><th>Next expiry</th><th class="n">Qty</th><th class="n">Value</th></tr></thead><tbody>` +
    Object.entries(groups).map(([k, bs]) => {
      const r = bs[0], qty = bs.reduce((a, x) => a + +x.quantity, 0), val = bs.reduce((a, x) => a + +x.value, 0), low = bs.some(x => +x.reorder_level > 0 && +x.quantity <= +x.reorder_level);
      const exp = bs.map(x => x.expiry_date).filter(Boolean).sort()[0], dl = exp ? Math.min(...bs.filter(x => x.expiry_date).map(x => +x.days_left)) : null;
      return `<tr class="iw-grp" data-g="${k}" style="cursor:pointer"><td><b>${esc(r.item_name)}</b><br><small style="color:#6b7890">${[r.item_code, r.category].filter(Boolean).map(esc).join(' · ')}</small></td><td>${tag(r)}</td><td>${esc(r.store_name)}</td><td class="n">${bs.length}</td>
        <td>${exp ? esc(exp) + (dl < 0 ? ' <span class="iw-tag exp">expired</span>' : dl <= 90 ? ' <span class="iw-tag low">' + dl + 'd</span>' : '') : '—'}</td><td class="n">${num(qty)}${low ? ' <span class="iw-tag low">low</span>' : ''}</td><td class="n">${num(val)}</td></tr>` +
        bs.map(x => `<tr class="iw-bat" data-p="${k}" style="display:none;background:#f8fafc"><td colspan="3" style="padding-left:28px;color:#475569">Batch ${esc(x.batch_number || '—')}${x.location_rack ? ' · rack ' + esc(x.location_rack) : ''}</td><td class="n">${num(x.unit_cost)} each</td><td>${x.expiry_date ? esc(x.expiry_date) : '—'}</td><td class="n">${num(x.quantity)}</td><td class="n" style="white-space:nowrap">${manage ? `<button class="iw-btn" data-edit="${x.batch_id}">Edit</button> <button class="iw-btn dan" data-void="${x.batch_id}">Void</button>` : num(x.value)}</td></tr>`).join('');
    }).join('') + '</tbody></table></div>' + (d.truncated ? '<p class="iw-empty">Showing the first 500 batches. Narrow the filters.</p>' : '');
  window._iwRows = Object.fromEntries(d.rows.map(r => [r.batch_id, r]));
}

function modal(html) { $('iw-modal-box').innerHTML = html; $('iw-modal').style.display = 'flex'; }
const closeModal = () => { $('iw-modal').style.display = 'none'; };

async function loadLookups() {
  if (lookups) return lookups; const j = await get('ws_lookups'); lookups = j.data;
  const opt = (a, f = x => x.name) => a.map(x => `<option value="${x.id}">${esc(f(x))}</option>`).join('');
  $('iw-cat').innerHTML += opt(lookups.categories); $('r-dept').innerHTML += opt(lookups.departments); $('r-person').innerHTML += opt(lookups.people); $('r-item').innerHTML += opt(lookups.items);
  return lookups;
}

async function loadReport() {
  const j = await get('ws_report', {from: $('r-from').value, to: $('r-to').value, department_id: $('r-dept').value, person_id: $('r-person').value, item_id: $('r-item').value, kind: $('r-kind').value});
  if (!j.success) { $('iw-report-out').innerHTML = `<div class="iw-empty">${esc(j.message)}</div>`; return; }
  const {totals: t, groups: g, rows} = j.data;
  const grp = (title, list) => `<div style="flex:1;min-width:300px"><h4 style="margin:0 0 6px">${title}</h4><table><thead><tr><th></th><th class="n">Qty</th><th class="n">Amount</th><th class="n">Paid</th><th class="n">Unpaid</th></tr></thead><tbody>` +
    (list.slice(0, 15).map(x => `<tr><td>${esc(x.label)}</td><td class="n">${num(x.qty)}</td><td class="n">${num(x.amount)}</td><td class="n iw-paid">${+x.paid ? num(x.paid) : ''}</td><td class="n iw-unpaid">${+x.unpaid ? num(x.unpaid) : ''}</td></tr>`).join('') || '<tr><td colspan=5 class="iw-empty">No data</td></tr>') + '</tbody></table></div>';
  $('iw-report-out').innerHTML = `<div class="iw-cards"><div class="iw-card"><small>Quantity</small><strong>${num(t.qty)}</strong></div><div class="iw-card"><small>Total cost</small><strong>${num(t.amount)}</strong></div>
    <div class="iw-card"><small>Paid patients (cost)</small><strong class="iw-paid">${num(t.paid)}</strong></div><div class="iw-card"><small>Unpaid patients (cost)</small><strong class="iw-unpaid">${num(t.unpaid)}</strong></div><div class="iw-card"><small>Department use (internal)</small><strong>${num(t.internal)}</strong></div></div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">${grp('By department', g.department)}${grp('By staff', g.person)}${grp('By item', g.item)}</div>
    <h4 style="margin:0 0 6px">Details</h4><div style="overflow:auto"><table><thead><tr><th>Date</th><th>Item</th><th>Event</th><th>Department</th><th>Staff</th><th class="n">Qty</th><th class="n">Amount</th><th>Payment</th></tr></thead><tbody>` +
    (rows.map(r => `<tr><td>${esc(r.created_at.slice(0, 16))}</td><td>${esc(r.item_name)}</td><td>${esc(r.event)}</td><td>${esc(r.department_name || '—')}</td><td>${esc(r.person_name || '—')}</td><td class="n">${num(r.qty)}</td><td class="n">${num(r.amount)}</td>
      <td class="${r.paid_state === 'paid' ? 'iw-paid' : r.paid_state === 'unpaid' ? 'iw-unpaid' : ''}">${r.paid_state === 'internal' ? 'Internal' : r.paid_state === 'paid' ? 'Paid' : 'Unpaid'}</td></tr>`).join('') || '<tr><td colspan=8 class="iw-empty">Nothing in this period</td></tr>') + '</tbody></table></div>';
  if ($('r-person').value) showClinical($('r-person').value);
}

// One person selected: add their clinical (HMIS) performance for the same period above the stock figures.
async function showClinical(pid) {
  const base = API.replace('inventory/inventory-management', 'reports/general').replace(/action=$/, '');
  const qs = '&date_from=' + $('r-from').value + '&date_to=' + $('r-to').value;
  let s = null, kind = '';
  for (const a of ['doctor_performance', 'nurse_performance']) {
    const j = await (await fetch(base + 'action=' + a + qs)).json();
    s = ((j.data || j).staff || []).find(x => +x.id === +pid); if (s) { kind = a === 'doctor_performance' ? 'Doctor' : 'Nurse'; break; }
  }
  if (!s) return;
  const c = s.counts || {}, L = kind === 'Doctor'
    ? [['Patients', c.patients], ['Consultations', c.consultations], ['Children', c.children], ['Men', c.men], ['Women', c.women], ['Sent to lab', c.lab], ['Sent to imaging', c.imaging], ['Insured', c.insured], ['Self-pay / private', c.self_pay], ['Revenue (RWF)', c.shared_rev]]
    : [['Patients', c.patients], ['Received', c.received], ['Referrals', c.referrals], ['Women', c.women], ['Self-pay / private', c.self_pay]];
  $('iw-report-out').insertAdjacentHTML('afterbegin', `<h4 style="margin:0 0 6px">${kind} performance — ${esc(s.name)}</h4><div class="iw-cards">${L.map(([k, v]) => `<div class="iw-card"><small>${k}</small><strong>${num(v)}</strong></div>`).join('')}</div><h4 style="margin:0 0 6px">Stock used / requested</h4>`);
}

// ---- events
document.addEventListener('click', async e => {
  const g = e.target.closest('.iw-grp'); if (g && !e.target.closest('button')) { document.querySelectorAll('.iw-bat').forEach(x => { if (x.dataset.p === g.dataset.g) x.style.display = x.style.display === 'none' ? '' : 'none'; }); return; }
  const t = e.target.closest('button,a'); if (!t) return;
  if (t.classList.contains('iw-gbtn')) { showGroup(t.dataset.g); return; }
  if (t.dataset.s !== undefined && t.classList.contains('iw-item')) pick(t.dataset.s ? {store_id: +t.dataset.s, name: t.dataset.n} : {});
  else if (t.dataset.d) pick({department_id: +t.dataset.d, name: t.dataset.n + ' — department store'});
  else if (t.classList.contains('iw-tab')) { document.querySelectorAll('.iw-tab').forEach(x => x.classList.toggle('on', x === t)); $('iw-stock').style.display = t.dataset.t === 'stock' ? '' : 'none'; $('iw-report').style.display = t.dataset.t === 'report' ? '' : 'none'; if (t.dataset.t === 'report') { await loadLookups(); loadReport(); } }
  else if (t.dataset.f) { flag = flag === t.dataset.f ? '' : t.dataset.f; document.querySelectorAll('[data-f]').forEach(x => x.classList.toggle('on', x.dataset.f === flag)); loadStock(); }
  else if (t.id === 'r-go') loadReport();
  else if (t.dataset.void) { const r = window._iwRows[t.dataset.void]; modal(`<h3>Void stock record</h3><p style="margin:8px 0;color:#6b7890">${esc(r.item_name)} · batch ${esc(r.batch_number)} · ${num(r.quantity)} units leave the stock. The history stays.</p><label>Reason (required)</label><input id="m-reason"><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button class="iw-btn" data-close>Cancel</button><button class="iw-btn dan" data-dovoid="${r.batch_id}">Void</button></div>`); }
  else if (t.dataset.dovoid) { const j = await post('ws_void', {batch_id: +t.dataset.dovoid, reason: $('m-reason').value}); toast(j.message, j.success); if (j.success) { closeModal(); loadStock(); loadSide(); } }
  else if (t.dataset.edit) { const r = window._iwRows[t.dataset.edit]; modal(`<h3>Edit stock record</h3><p style="color:#6b7890">${esc(r.item_name)}</p><label>Batch number</label><input id="m-batch" value="${esc(r.batch_number)}"><label>Expiry date</label><input type="date" id="m-exp" value="${esc(r.expiry_date || '')}"><label>Reorder level</label><input type="number" id="m-reo" value="${r.reorder_level}"><label>Quantity</label><input type="number" step="0.01" id="m-qty" value="${r.quantity}"><label>Reason (required only if the quantity changes)</label><input id="m-reason"><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button class="iw-btn" data-close>Cancel</button><button class="iw-btn pri" data-dosave="${r.batch_id}">Save</button></div>`); }
  else if (t.dataset.dosave) { const j = await post('ws_edit', {batch_id: +t.dataset.dosave, batch_number: $('m-batch').value, expiry_date: $('m-exp').value, reorder_level: $('m-reo').value, quantity: $('m-qty').value, reason: $('m-reason').value}); toast(j.message, j.success); if (j.success) { closeModal(); loadStock(); loadSide(); } }
  else if (t.id === 'iw-use') { await loadLookups(); modal(`<h3>Record use — ${esc(scope.name)}</h3><label>Item</label><select id="m-item">${lookups.items.map(i => `<option value="${i.id}">${esc(i.name)}</option>`).join('')}</select><label>Quantity used</label><input type="number" step="0.01" min="0.01" id="m-uqty"><label>Note (optional)</label><input id="m-note"><div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px"><button class="iw-btn" data-close>Cancel</button><button class="iw-btn pri" data-douse>Deduct</button></div>`); }
  else if (t.dataset.douse !== undefined) { const j = await post('ws_consume', {department_id: scope.department_id, item_id: +$('m-item').value, quantity: +$('m-uqty').value, note: $('m-note').value}); toast(j.message, j.success); if (j.success) { closeModal(); loadStock(); loadSide(); } }
  else if (t.dataset.close !== undefined) closeModal();
});
$('iw-modal').addEventListener('click', e => { if (e.target.id === 'iw-modal') closeModal(); });
['iw-q', 'iw-kind', 'iw-cat'].forEach(id => $(id).addEventListener(id === 'iw-q' ? 'input' : 'change', debounce(loadStock)));
$('iw-dsearch').addEventListener('input', drawDepts);
$('iw-where').addEventListener('input', debounce(async () => {
  const q = $('iw-where').value.trim(), o = $('iw-where-out'); if (q.length < 2) { o.style.display = 'none'; return; }
  const j = await get('ws_where', {q}); o.style.display = ''; o.innerHTML = j.data.length ? '<table><tbody>' + j.data.map(x => `<tr><td><b>${esc(x.item_name)}</b></td><td>${esc(x.store_name)}</td><td class="n">${num(x.qty)}</td></tr>`).join('') + '</tbody></table>' : '<div class="iw-empty">Not found in any store</div>';
}));
const today = new Date().toISOString().slice(0, 10); $('r-from').value = today.slice(0, 8) + '01'; $('r-to').value = today;
loadLookups(); loadSide().then(loadStock);
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
