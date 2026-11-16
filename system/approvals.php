<?php
// approvals.php — Reception approves services that are not paid yet (pay later). Paid bills need no approval.
// Every portal's payment badge shows the result: Paid / Approved, pay later / Awaiting approval.
require_once __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
  <div>
    <h2 style="font-size:24px;font-weight:700;color:var(--gray-900);margin:0;"><i class="fas fa-user-check" style="color:var(--primary-color);margin-right:8px;"></i>Approvals</h2>
    <p style="color:var(--gray-500);font-size:13px;margin:3px 0 0 0;">Services waiting for payment. Collect the payment, or approve the service so the patient pays later.</p>
  </div>
  <div style="display:flex;gap:8px;">
    <button id="ap-tab-pending" onclick="apShow('pending')" style="padding:9px 16px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Awaiting approval <span id="ap-count"></span></button>
    <button id="ap-tab-approved" onclick="apShow('approved')" style="padding:9px 16px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Approved today</button>
  </div>
</div>

<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="background:var(--gray-50);text-align:left;color:var(--gray-600);font-size:12px;">
        <th style="padding:12px 16px;">Patient</th>
        <th style="padding:12px 16px;">Service</th>
        <th style="padding:12px 16px;">Bill</th>
        <th style="padding:12px 16px;text-align:right;">To pay</th>
        <th style="padding:12px 16px;" id="ap-col-action">Action</th>
      </tr>
    </thead>
    <tbody id="ap-tbody"><tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading…</td></tr></tbody>
  </table>
</div>

<script>
const apAPI = window._apiBase + '?endpoint=patients/billing';
const AP_PHASES = {consultation:'Consultation', lab:'Lab', laboratory:'Lab', radiology:'Imaging', imaging:'Imaging', procedure:'Procedure',
  pharmacy:'Pharmacy', medication:'Pharmacy', drug:'Pharmacy', emergency:'Emergency', nursing:'Nursing', accommodation:'Ward', tariff_adjustment:'Tariff change'};
const AP_TARIFFS = {east_africa:'Foreigner - East Africa', africa:'Foreigner - Africa', world:'Foreigner - Rest of the World'};
let apMode = 'pending', apRows = {};
const apEsc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const apMoney = n => Math.round(+n || 0).toLocaleString() + ' RWF';

function apShow(mode) {
  apMode = mode;
  ['pending', 'approved'].forEach(m => {
    const b = document.getElementById('ap-tab-' + m);
    b.style.background = m === mode ? 'var(--primary-color)' : 'white';
    b.style.color = m === mode ? 'white' : 'var(--gray-700)';
  });
  document.getElementById('ap-col-action').textContent = mode === 'pending' ? 'Action' : 'Status';
  apLoad();
}

async function apLoad() {
  const tbody = document.getElementById('ap-tbody');
  try {
    const j = await (await fetch(apAPI + '&action=approvals&show=' + apMode)).json();
    if (!j.success) { tbody.innerHTML = `<tr><td colspan="5" style="padding:30px;text-align:center;color:#dc2626;">${apEsc(j.message)}</td></tr>`; return; }
    const rows = j.data || [];
    apRows = Object.fromEntries(rows.map(r => [r.id, r]));
    if (apMode === 'pending') document.getElementById('ap-count').textContent = rows.length ? '(' + rows.length + ')' : '';
    if (!rows.length) { tbody.innerHTML = `<tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:22px;display:block;margin-bottom:6px;"></i>${apMode === 'pending' ? 'Nothing waiting — every service is paid or approved.' : 'No approvals today.'}</td></tr>`; return; }
    tbody.innerHTML = rows.map(r => {
      const phases = [...new Set((r.categories || '').split(',').filter(Boolean).map(c => AP_PHASES[c] || c))].join(', ');
      const foreign = AP_TARIFFS[r.tariff_category] ? `<span style="padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;background:#fef3c7;color:#92400e;">${AP_TARIFFS[r.tariff_category]}</span>` : '';
      const btn = 'padding:6px 10px;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;';
      const approvedInfo = r.approved_at ? `<div style="font-size:11px;color:#1e40af;margin-top:4px;"><i class="fas fa-user-check"></i> Approved, pay later — ${apEsc(r.approved_by_name || 'Staff')}, ${apEsc(r.approved_at)}<br>${apEsc(r.approval_note)}</div>` : '';
      const action = apMode === 'pending'
        ? (r.approved_at
            // approved to pay later: stays here until the patient pays
            ? `<button onclick="apPayOpen(${r.id})" style="${btn}background:var(--success-color);"><i class="fas fa-check"></i> Paid</button>${approvedInfo}`
            : `<div style="display:flex;gap:6px;flex-wrap:wrap;">
                 <button onclick="apPayOpen(${r.id})" style="${btn}background:var(--success-color);"><i class="fas fa-money-bill-wave"></i> Collect payment</button>
                 <button onclick="apApprove(${r.id})" style="${btn}background:#1e40af;"><i class="fas fa-user-check"></i> Approve, pay later</button>
               </div>`)
        : `<div style="font-weight:600;color:#059669;"><i class="fas fa-check-circle"></i> Paid ${apMoney(r.paid_amount)}</div>${approvedInfo}`;
      return `<tr style="border-top:1px solid var(--gray-100);vertical-align:top;">
        <td style="padding:12px 16px;"><div style="font-weight:600;color:var(--gray-900);">${apEsc(r.patient_name)}</div>
          <div style="font-size:11px;color:var(--gray-500);">${apEsc(r.patient_no)} · <span style="font-family:monospace;">${apEsc(r.visit_no)}</span> ${foreign}</div></td>
        <td style="padding:12px 16px;"><div style="font-weight:600;">${apEsc(phases)}</div><div style="font-size:11px;color:var(--gray-500);max-width:340px;">${apEsc(r.items)}</div></td>
        <td style="padding:12px 16px;font-size:12px;"><div style="font-family:monospace;">${apEsc(r.invoice_no)}</div><div style="color:var(--gray-500);">${apEsc(r.created_at)}</div></td>
        <td style="padding:12px 16px;text-align:right;font-weight:700;color:${apMode === 'pending' ? '#dc2626' : '#059669'};">${apMoney(apMode === 'pending' ? r.balance : r.total)}${apMode === 'pending' && +r.paid_amount > 0 ? `<div style="font-size:11px;font-weight:400;color:#059669;">paid ${apMoney(r.paid_amount)}</div>` : ''}</td>
        <td style="padding:12px 16px;">${action}</td>
      </tr>`;
    }).join('');
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="5" style="padding:30px;text-align:center;color:#dc2626;">Error: ${apEsc(e.message)}</td></tr>`;
  }
}

async function apApprove(invoiceId) {
  const r = apRows[invoiceId];
  const note = prompt(`Approve ${r.patient_name} to receive this service and pay ${apMoney(r.balance)} later?\n\nReason (required):`);
  if (note === null) return;
  if (note.trim().length < 3) { alert('Write the reason (at least 3 characters).'); return; }
  try {
    const j = await (await fetch(apAPI + '&action=approve', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({invoice_ids: [invoiceId], note: note.trim()})})).json();
    alert(j.message);
    apLoad();
  } catch (e) { alert('Request failed: ' + e.message); }
}

// Collect payment here (same API as Invoicing): Dr cash/bank account, Cr patient receivable
let apCashOpts = null;
async function apPayOpen(invoiceId) {
  const r = apRows[invoiceId];
  if (apCashOpts === null) {
    try {
      const j = await (await fetch(window._apiBase + '?endpoint=finance/chart-of-accounts&action=cash_balances')).json();
      apCashOpts = ((j.data && j.data.accounts) || []).filter(a => a.account_group === 'cash').map(a => `<option value="${a.id}">${apEsc(a.account_name)}</option>`).join('');
    } catch (e) { apCashOpts = ''; }
  }
  const due = Math.round(r.balance);
  const m = document.createElement('div');
  m.id = 'ap-pay-modal';
  m.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  const field = 'width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;';
  m.innerHTML = `<div style="background:white;border-radius:12px;width:440px;max-width:95vw;padding:22px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:14px;"><h3 style="font-size:16px;font-weight:600;margin:0;"><i class="fas fa-money-bill-wave" style="color:#16a34a;"></i> Collect payment</h3>
      <button onclick="document.getElementById('ap-pay-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    <div style="background:var(--gray-50);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:13px;">
      <div><strong>${apEsc(r.patient_name)}</strong> · ${apEsc(r.visit_no)} · ${apEsc(r.invoice_no)}</div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:3px;">${apEsc(r.items)}</div>
      <div style="margin-top:6px;font-size:15px;font-weight:700;color:#dc2626;">To pay: ${apMoney(due)}</div></div>
    <label style="font-size:12px;font-weight:600;">Amount</label>
    <input id="ap-pay-amount" type="number" min="1" max="${due}" value="${due}" style="${field}margin:4px 0 10px;">
    <label style="font-size:12px;font-weight:600;">Payment method</label>
    <select id="ap-pay-method" style="${field}margin:4px 0 10px;"><option value="cash">Cash</option><option value="mobile_money">Mobile Money</option><option value="mtn_momo">MTN MoMo</option><option value="bank">Bank Transfer</option></select>
    <label style="font-size:12px;font-weight:600;">Receive into</label>
    <select id="ap-pay-account" style="${field}margin:4px 0 10px;">${apCashOpts || '<option value="1003">Petty Cash</option>'}</select>
    <label style="font-size:12px;font-weight:600;">Reference (optional)</label>
    <input id="ap-pay-ref" placeholder="Receipt / MoMo ref" style="${field}margin:4px 0 14px;">
    <button id="ap-pay-btn" onclick="apPayConfirm(${r.id})" style="width:100%;padding:11px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-check"></i> Confirm payment</button>
  </div>`;
  document.body.appendChild(m);
  const petty = [...document.getElementById('ap-pay-account').options].find(o => /petty/i.test(o.text));
  if (petty) petty.selected = true; // cash goes to the till by default
}

async function apPayConfirm(invoiceId) {
  const r = apRows[invoiceId];
  const amount = Math.round(+document.getElementById('ap-pay-amount').value || 0);
  if (amount <= 0 || amount > Math.round(r.balance)) { alert('Enter an amount between 1 and ' + apMoney(r.balance) + '.'); return; }
  const btn = document.getElementById('ap-pay-btn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
  try {
    const j = await (await fetch(apAPI + '&action=record_payment_full', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({
      invoice_id: r.id, patient_amount: amount, insurance_amount: 0, insurance_id: r.insurance_id || 0,
      bank_account_id: parseInt(document.getElementById('ap-pay-account').value), payment_method: document.getElementById('ap-pay-method').value,
      reference: document.getElementById('ap-pay-ref').value.trim()})})).json();
    if (!j.success) { alert(j.message || 'Payment failed'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Confirm payment'; return; }
    document.getElementById('ap-pay-modal').remove();
    alert('✓ Payment of ' + apMoney(amount) + ' recorded' + (amount < Math.round(r.balance) ? ' — still to pay: ' + apMoney(r.balance - amount) : ''));
    apLoad();
  } catch (e) { alert('Request failed: ' + e.message); btn.disabled = false; }
}

apShow('pending');
setInterval(() => { if (!document.hidden) apLoad(); }, 30000); // new orders from the doctors appear by themselves
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
