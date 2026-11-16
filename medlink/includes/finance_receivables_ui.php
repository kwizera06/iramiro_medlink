<?php
// includes/finance_receivables_ui.php
// Bad Debts & Write-offs and Insurance Claims screens (included by finance.php; $subModule picks the screen).
// Data comes from api.php?endpoint=finance/receivables — see handlers/finance/receivables.php.
require __DIR__ . '/finance_ops_ui.php'; // shared styles and helpers (fnEsc, fnMoney, fnModal, fnToday); draws nothing for these screens
?>
<style>
.rc-btn{transition:transform .12s ease-out,background-color .12s ease-out;}
.rc-btn:active:not(:disabled){transform:scale(.97);}
.rc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:20px;}
.rc-stat{background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);}
.rc-stat b{display:block;font-size:20px;font-variant-numeric:tabular-nums;}
.rc-stat span{font-size:12px;color:var(--gray-500);}
.rc-neg{color:#991b1b;}
.rc-wide>div{width:780px !important;}
.fo-grid>.fo-card{min-width:0;}
.rc-hint{font-size:11px;color:var(--gray-500);margin-top:4px;line-height:1.4;}
.rc-drawer{justify-content:flex-end;align-items:stretch;z-index:10000;}
.rc-drawer>div{width:680px;max-width:100vw;height:100vh;max-height:100vh;border-radius:0;animation:rcSlide .2s ease-out;}
@keyframes rcSlide{from{transform:translateX(24px);opacity:0;}to{transform:none;opacity:1;}}
@media (prefers-reduced-motion:reduce){.rc-drawer>div{animation:none;}.rc-btn{transition:none;}}
.rc-line input{width:70px;padding:4px 6px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;}
.rc-lock{background:#fef3c7;color:#92400e;border-radius:8px;padding:10px 12px;font-size:12px;line-height:1.45;margin:10px 0;}
.rc-audit{font-size:11px;color:var(--gray-600);border-bottom:1px solid var(--gray-100);padding:6px 0;line-height:1.4;word-break:break-word;}
</style>
<script>
const _rcApi = _fnBase + '/api.php?endpoint=finance/receivables';
async function rcCall(action, opts){
  opts = opts || {};
  const qs = new URLSearchParams(Object.assign({action: action}, opts.params || {}));
  const init = opts.body ? {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(opts.body)} : {};
  try { const r = await fetch(_rcApi + '&' + qs.toString(), init); return await r.json(); }
  catch(e){ return {success:false, message:'Request failed: ' + e.message}; }
}
// POST, tell the user what happened, then run after()
async function rcPost(btn, action, body, after){
  if(btn) btn.disabled = true;
  const j = await rcCall(action, {body: body});
  if(btn) btn.disabled = false;
  if(!j.success){ alert(j.message || 'Failed'); return false; }
  if(after) await after(j);
  return true;
}
</script>

<?php if ($subModule == 'receivables'): ?>
<!-- ====== BAD DEBTS & WRITE-OFFS ====== -->
<div class="rc-stats" id="rb-stats"></div>
<div class="fo-card">
  <div class="fo-row" style="justify-content:space-between;">
    <h3 style="margin:0;"><i class="fas fa-user-clock"></i> Patients who owe the hospital</h3>
    <button class="fo-btn alt rc-btn" onclick="rbProvision()"><i class="fas fa-shield-alt"></i> Record provision</button>
  </div>
  <div class="fo-wrap"><table class="fo-tbl">
    <thead><tr><th>Patient</th><th class="num">Owes</th><th>Oldest open invoice</th><th></th></tr></thead>
    <tbody id="rb-debtors"><tr><td colspan="4" class="fo-empty">Loading...</td></tr></tbody>
  </table></div>
  <p class="rc-hint">Writing off removes the debt from the patient's account in the ledger. The invoice in Billing is not changed. If the patient later pays, void the write-off first, then record the payment.</p>
</div>
<div class="fo-card">
  <h3><i class="fas fa-history"></i> Provisions and write-offs</h3>
  <div class="fo-wrap"><table class="fo-tbl">
    <thead><tr><th>Date</th><th>Type</th><th>Patient</th><th>Reason</th><th class="num">Amount</th><th></th></tr></thead>
    <tbody id="rb-history"><tr><td colspan="6" class="fo-empty">Loading...</td></tr></tbody>
  </table></div>
</div>
<script>
let rbDebtors = [];
async function rbLoad(){
  const [d, h] = await Promise.all([rcCall('debtors'), rcCall('writeoffs')]);
  const body = document.getElementById('rb-debtors');
  if(!d.success){ body.innerHTML = `<tr><td colspan="4" class="fo-empty rc-neg">${fnEsc(d.message || 'Failed')}</td></tr>`; return; }
  const x = d.data; rbDebtors = x.debtors;
  document.getElementById('rb-stats').innerHTML =
    `<div class="rc-stat"><b>${fnMoney(x.patient_receivables)}</b><span>Owed by patients (RWF)</span></div>`
    + `<div class="rc-stat"><b>${fnMoney(x.allowance)}</b><span>Allowance for doubtful accounts</span></div>`
    + `<div class="rc-stat"><b>${fnMoney(x.net_receivables)}</b><span>Net receivable after allowance</span></div>`;
  body.innerHTML = x.debtors.length ? x.debtors.map(r => {
    const old = r.days_old;
    const tag = old == null ? '' : ` <span class="fo-tag ${old > 90 ? 'bad' : old > 30 ? 'warn' : 'gray'}">${old} days</span>`;
    return `<tr><td>${fnEsc(r.account_name)}</td><td class="num">${fnMoney(r.balance)}</td><td>${fnEsc(r.oldest_open_invoice || '-')}${tag}</td>
      <td class="num"><button class="fo-btn danger sm rc-btn" onclick="rbWriteOff(${r.patient_id})">Write off</button></td></tr>`;
  }).join('') : '<tr><td colspan="4" class="fo-empty">No patient owes anything.</td></tr>';
  const hist = document.getElementById('rb-history');
  const rows = (h.success && h.data) || [];
  hist.innerHTML = rows.length ? rows.map(w => `<tr style="${w.status === 'voided' ? 'opacity:.5;text-decoration:line-through;' : ''}">
      <td>${fnEsc(w.entry_date)}</td><td>${w.kind === 'provision' ? 'Provision' : 'Write-off'}</td><td>${fnEsc(w.patient || '')}</td><td>${fnEsc(w.reason || '')}</td><td class="num">${fnMoney(w.amount)}</td>
      <td class="num">${w.status === 'voided' ? '' : `<button class="fo-btn alt sm rc-btn" onclick="rbVoid(${w.id}, this)">Void</button>`}</td></tr>`).join('')
    : '<tr><td colspan="6" class="fo-empty">Nothing recorded yet.</td></tr>';
}
function rbWriteOff(patientId){
  const r = rbDebtors.find(x => +x.patient_id === patientId); if(!r) return;
  const m = fnModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Write off a patient debt</h3>
    <div style="color:var(--gray-600);font-size:13px;">${fnEsc(r.account_name)} owes ${fnMoney(r.balance)} RWF</div>
    <label class="fo-lbl">Amount</label><input class="fo-in" type="number" step="0.01" min="0" id="wo-amount" value="${r.balance}">
    <label class="fo-lbl">Date</label><input class="fo-in" type="date" id="wo-date" value="${fnToday()}" max="${fnToday()}">
    <label class="fo-lbl">Reason</label><input class="fo-in" id="wo-reason" placeholder="e.g. patient cannot be traced, approved by director">
    <div class="rc-hint">The allowance is used first; any remainder is charged to Bad debts expense.</div>
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn rc-btn" id="wo-go" style="flex:1;">Write off</button><button class="fo-btn alt rc-btn" onclick="this.closest('.fo-modal').remove()">Cancel</button></div>`);
  m.querySelector('#wo-go').onclick = async function(){
    if(await rcPost(this, 'writeoff', {patient_id: patientId, amount: m.querySelector('#wo-amount').value, entry_date: m.querySelector('#wo-date').value, reason: m.querySelector('#wo-reason').value}, rbLoad)) m.remove();
  };
}
function rbProvision(){
  const m = fnModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Provision for doubtful debts</h3>
    <div class="rc-hint">An estimate of what will not be collected. It is charged to Bad debts expense now and held in the allowance until debts are written off.</div>
    <label class="fo-lbl">Amount</label><input class="fo-in" type="number" step="0.01" min="0" id="pv-amount">
    <label class="fo-lbl">Date</label><input class="fo-in" type="date" id="pv-date" value="${fnToday()}" max="${fnToday()}">
    <label class="fo-lbl">Basis (optional)</label><input class="fo-in" id="pv-reason" placeholder="e.g. 50% of balances older than 90 days">
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn rc-btn" id="pv-go" style="flex:1;">Record provision</button><button class="fo-btn alt rc-btn" onclick="this.closest('.fo-modal').remove()">Cancel</button></div>`);
  m.querySelector('#pv-go').onclick = async function(){
    if(await rcPost(this, 'provision', {amount: m.querySelector('#pv-amount').value, entry_date: m.querySelector('#pv-date').value, reason: m.querySelector('#pv-reason').value}, rbLoad)) m.remove();
  };
}
async function rbVoid(id, btn){ if(confirm('Void this entry? Its ledger entry is removed.')) await rcPost(btn, 'writeoff_void', {id: id}, rbLoad); }
rbLoad();
</script>

<?php elseif ($subModule == 'claims'): ?>
<!-- ====== INSURANCE CLAIMS ====== -->
<div class="fo-card">
  <h3><i class="fas fa-file-medical"></i> New claim</h3>
  <div class="fo-row" style="align-items:flex-end;">
    <div><label class="fo-lbl">Insurer</label><select class="fo-in" id="cl-ins" style="min-width:220px;" onchange="clPreview()"></select></div>
    <div><label class="fo-lbl">Month</label><input class="fo-in" type="month" id="cl-month" onchange="clPreview()"></div>
    <div><label class="fo-lbl">Submitted on</label><input class="fo-in" type="date" id="cl-date" max="<?php echo date('Y-m-d'); ?>"></div>
    <div style="flex:1;min-width:160px;"><label class="fo-lbl">Notes (optional)</label><input class="fo-in" id="cl-notes"></div>
    <button class="fo-btn rc-btn" id="cl-go" onclick="clCreate(this)">Create claim</button>
  </div>
  <div class="rc-hint" id="cl-preview"></div>
  <p class="rc-hint">The amount is the insurer's share of the month's invoices, taken from the ledger. One claim per insurer per month.</p>
</div>
<div class="fo-card">
  <h3><i class="fas fa-list"></i> Claims</h3>
  <div class="fo-wrap"><table class="fo-tbl">
    <thead><tr><th>Claim</th><th>Insurer</th><th class="num">Claimed</th><th class="num">Paid</th><th class="num">Rejected, to decide</th><th class="num">Outstanding</th><th>Status</th><th></th></tr></thead>
    <tbody id="cl-body"><tr><td colspan="8" class="fo-empty">Loading...</td></tr></tbody>
  </table></div>
</div>
<script>
const CL_STATE = {awaiting_payment:['gray','Awaiting payment'], rejections_to_decide:['warn','Rejections to decide'], resubmitted:['warn','Resubmitted'], settled:['ok','Settled'], cancelled:['gray','Cancelled']};
const CL_REJ = {open:['warn','Open'], resubmitted:['gray','Resubmitted'], billed_patient:['gray','Billed to patient'], written_off:['bad','Written off'], voided:['gray','Voided']};
function clTag(map, k){ const t = map[k] || ['gray', k]; return `<span class="fo-tag ${t[0]}">${t[1]}</span>`; }
document.getElementById('cl-date').value = fnToday();
document.getElementById('cl-month').value = fnToday().slice(0, 7);
async function clInit(){
  const j = await rcCall('insurers');
  document.getElementById('cl-ins').innerHTML = '<option value="">Select...</option>' + fnOpts((j.data || []), 'id', x => x.name, '');
  clLoad();
}
async function clPreview(){
  const ins = document.getElementById('cl-ins').value, ym = document.getElementById('cl-month').value, box = document.getElementById('cl-preview');
  if(!ins || !ym){ box.textContent = 'Choose an insurer and a month to see what will be claimed.'; return; }
  const j = await rcCall('claim_preview', {params: {insurance_id: ins, period: ym}});
  box.textContent = j.success ? `${j.data.invoice_count} invoices, ${fnMoney(j.data.claimed_amount)} RWF will be claimed.` : (j.message || 'Failed');
}
async function clCreate(btn){
  await rcPost(btn, 'claim_create', {insurance_id: document.getElementById('cl-ins').value, period: document.getElementById('cl-month').value, submitted_date: document.getElementById('cl-date').value, notes: document.getElementById('cl-notes').value}, async j => { alert(j.message + ': ' + j.data.claim_no); await clLoad(); });
}
async function clLoad(){
  const body = document.getElementById('cl-body');
  const j = await rcCall('claims');
  if(!j.success){ body.innerHTML = `<tr><td colspan="8" class="fo-empty rc-neg">${fnEsc(j.message || 'Failed')}</td></tr>`; return; }
  body.innerHTML = j.data.length ? j.data.map(c => `<tr>
      <td style="white-space:nowrap;">${fnEsc(c.claim_no)}<div class="rc-hint">${fnEsc(c.period_ym)} · submitted ${fnEsc(c.submitted_date)}</div></td><td>${fnEsc(c.insurer || '')}</td>
      <td class="num">${fnMoney(c.claimed_amount)}</td><td class="num">${fnMoney(c.settled)}</td><td class="num">${fnMoney(c.rejected_open)}</td><td class="num"><b>${fnMoney(c.outstanding)}</b></td>
      <td>${clTag(CL_STATE, c.state)}</td><td class="num"><button class="fo-btn alt sm rc-btn" onclick="clOpen(${c.id})">Open</button></td></tr>`).join('')
    : '<tr><td colspan="8" class="fo-empty">No claims yet.</td></tr>';
}
async function clOpen(id){
  const j = await rcCall('claim', {params: {id: id}});
  if(!j.success){ alert(j.message || 'Failed'); return; }
  const c = j.data;
  const open = c.status !== 'cancelled';
  const rej = c.rejections.map(r => `<tr style="${r.status === 'voided' ? 'opacity:.5;text-decoration:line-through;' : ''}">
      <td>${fnEsc(r.invoice_no || r.invoice_id)}</td><td>${fnEsc(r.rejected_date)}</td><td>${fnEsc(r.reason)}</td><td class="num">${fnMoney(r.amount)}</td><td>${clTag(CL_REJ, r.status)}</td>
      <td class="num">${r.status === 'open' ? `<button class="fo-btn sm rc-btn" onclick="clDecide(${r.id}, 'resubmit', ${c.id})">Resubmit</button> <button class="fo-btn alt sm rc-btn" onclick="clDecide(${r.id}, 'bill_patient', ${c.id})">Bill patient</button> <button class="fo-btn danger sm rc-btn" onclick="clDecide(${r.id}, 'write_off', ${c.id})">Write off</button> `
        : ''}${r.status !== 'voided' ? `<button class="fo-btn alt sm rc-btn" onclick="clVoid(${r.id}, ${c.id})">Void</button>` : ''}</td></tr>`).join('');
  const m = fnModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:4px;">${fnEsc(c.claim_no)} · ${fnEsc(c.insurer || '')}</h3>
    <div class="rc-hint" style="margin:0 0 12px;">${fnEsc(c.period_ym)} · ${c.invoices.length} invoices · claimed ${fnMoney(c.claimed_amount)} · paid ${fnMoney(c.settled)} · outstanding <b>${fnMoney(c.outstanding)}</b> RWF</div>
    <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Invoice</th><th>Rejected on</th><th>Insurer's reason</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>${rej || '<tr><td colspan="6" class="fo-empty">No rejections.</td></tr>'}</tbody></table></div>
    <h4 style="font-size:13px;margin:18px 0 6px;">Invoices in this claim</h4>
    <div class="fo-wrap" style="max-height:220px;overflow:auto;"><table class="fo-tbl"><thead><tr><th>Invoice</th><th class="num">Insurer share</th><th></th></tr></thead>
      <tbody>${c.invoices.map(i => `<tr><td>${fnEsc(i.invoice_no)}</td><td class="num">${fnMoney(i.insurer_share)}</td><td class="num"><button class="fo-btn alt sm rc-btn" onclick="rvOpen(${i.id}, ${c.id})">Review</button></td></tr>`).join('') || '<tr><td colspan="3" class="fo-empty">No invoices.</td></tr>'}</tbody></table></div>
    ${open ? `<h4 style="font-size:13px;margin:18px 0 0;">Record a rejection</h4>
    <label class="fo-lbl">Invoice</label><select class="fo-in" id="rj-inv">${fnOpts(c.invoices, 'id', i => i.invoice_no + ' — insurer share ' + fnMoney(i.insurer_share), '')}</select>
    <label class="fo-lbl">Rejected amount</label><input class="fo-in" type="number" step="0.01" min="0" id="rj-amount">
    <label class="fo-lbl">Date</label><input class="fo-in" type="date" id="rj-date" value="${fnToday()}" max="${fnToday()}">
    <label class="fo-lbl">Insurer's reason</label><input class="fo-in" id="rj-reason">
    <div class="rc-hint">Nothing moves in the ledger until you decide: resubmit to the insurer, bill the patient, or write it off.</div>
    <div style="display:flex;gap:8px;margin-top:14px;"><button class="fo-btn rc-btn" id="rj-go">Record rejection</button><button class="fo-btn alt rc-btn" onclick="this.closest('.fo-modal').remove()">Close</button>
      ${!c.rejections.some(r => r.status !== 'voided') ? `<button class="fo-btn danger rc-btn" style="margin-left:auto;" onclick="clCancel(${c.id}, this)">Cancel claim</button>` : ''}</div>`
      : '<div style="margin-top:14px;"><button class="fo-btn alt rc-btn" onclick="this.closest(\'.fo-modal\').remove()">Close</button></div>'}`);
  m.classList.add('rc-wide');
  const go = m.querySelector('#rj-go');
  if(go) go.onclick = async function(){
    if(await rcPost(this, 'claim_reject', {claim_id: id, invoice_id: m.querySelector('#rj-inv').value, amount: m.querySelector('#rj-amount').value, rejected_date: m.querySelector('#rj-date').value, reason: m.querySelector('#rj-reason').value}, async () => { await clLoad(); })){ m.remove(); clOpen(id); }
  };
}
async function clDecide(rid, decision, claimId){
  const text = {resubmit: 'Mark this rejection as resubmitted to the insurer?', bill_patient: 'Bill the rejected amount to the patient? The insurer receivable moves to the patient account.', write_off: 'Write off the rejected amount as a loss?'}[decision];
  if(!confirm(text)) return;
  if(await rcPost(null, 'claim_resolve', {id: rid, decision: decision}, async () => { await clLoad(); })){ document.querySelectorAll('.fo-modal').forEach(x => x.remove()); clOpen(claimId); }
}
async function clVoid(rid, claimId){
  if(!confirm('Void this rejection? Any ledger entry it made is removed.')) return;
  if(await rcPost(null, 'claim_reject_void', {id: rid}, async () => { await clLoad(); })){ document.querySelectorAll('.fo-modal').forEach(x => x.remove()); clOpen(claimId); }
}
async function clCancel(id, btn){
  if(!confirm('Cancel this claim?')) return;
  if(await rcPost(btn, 'claim_cancel', {id: id}, async () => { await clLoad(); })) document.querySelectorAll('.fo-modal').forEach(x => x.remove());
}
// ---- Review drawer: correct an insurer invoice from inside a claim. Prices are always set by the server from the tariff. ----
const _rvBill = _fnBase + '/api.php?endpoint=patients/billing&id=';
let rvInsurers = null, rvLoaded = {}, rvClaim = 0, rvTimer;
async function rvBill(id, body){
  try {
    const r = await fetch(_rvBill + id, {method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
    const j = await r.json();
    if(!j.success){ alert(j.message || 'Failed'); return false; }
    return true;
  } catch(e){ alert('Request failed: ' + e.message); return false; }
}
function rvClose(){ document.querySelectorAll('.rc-drawer').forEach(x => x.remove()); }
async function rvOpen(id, claimId){
  const j = await rcCall('claim_invoice', {params: {id: id}});
  if(!j.success){ alert(j.message || 'Failed'); return; }
  if(!rvInsurers) rvInsurers = ((await rcCall('insurers')).data) || [];
  const v = j.data, er = v.edit_request;
  rvClaim = claimId; rvLoaded = {};
  v.items.forEach(it => { rvLoaded[it.id] = it; });
  const locked = !!v.claim && !v.is_admin;
  const dis = locked ? 'disabled' : '';
  rvClose();
  const lines = v.items.map(it => `<tr class="rc-line" data-id="${it.id}">
      <td>${fnEsc(it.description)}</td>
      <td><input type="number" min="1" value="${it.quantity}" data-f="q" ${dis}></td>
      <td><input type="number" min="0" step="0.01" value="${it.discount}" data-f="d" ${dis}></td>
      <td class="num">${fnMoney(it.unit_price)}</td><td class="num">${fnMoney(it.total)}</td>
      <td class="num" style="white-space:nowrap;"><button class="fo-btn sm rc-btn" ${dis} onclick="rvSaveLine(${v.id}, ${it.id}, this, false)">Save</button>
        <button class="fo-btn alt sm rc-btn" ${dis} onclick="rvSaveLine(${v.id}, ${it.id}, this, true)" title="Look the price up again in the tariff">Re-price</button>
        <button class="fo-btn danger sm rc-btn" ${dis} onclick="rvRemoveLine(${v.id}, ${it.id})">Remove</button></td></tr>`).join('');
  const lock = !locked ? '' : `<div class="rc-lock"><b>This invoice is in submitted claim ${fnEsc(v.claim.claim_no)}.</b> It cannot be changed directly: an administrator has to approve the change first.
      ${er ? `<div style="margin-top:6px;">Edit request ${er.status === 'approved' ? 'approved. Ask the administrator to apply the change.' : 'waiting for approval.'}</div>`
           : `<label class="fo-lbl">Why does it need to change?</label><textarea class="fo-in" id="rv-reason" rows="2" placeholder="At least 10 characters"></textarea>
              <button class="fo-btn sm rc-btn" style="margin-top:6px;" onclick="rvRequestEdit(${v.id}, '${fnEsc(v.invoice_no)}', ${claimId}, this)">Request edit</button>`}</div>`;
  const audit = v.audit.length ? v.audit.map(a => {
    let ch = '';
    try { const c = a.changes ? JSON.parse(a.changes) : null; if(c) ch = ' [' + Object.keys(c).map(k => k + ': ' + c[k][0] + ' -> ' + c[k][1]).join('; ') + ']'; } catch(e){}
    return `<div class="rc-audit"><b>${fnEsc(a.created_at)}</b> ${fnEsc(a.user_name || '')}<br>${fnEsc(a.description || '')}${fnEsc(ch)}</div>`;
  }).join('') : '<div class="rc-hint">No edits recorded for this invoice.</div>';
  const memDis = dis || (v.member ? '' : 'disabled');
  const m = fnModal(`<div style="display:flex;justify-content:space-between;align-items:flex-start;"><div>
      <h3 style="font-size:16px;font-weight:700;margin:0 0 2px;">Review ${fnEsc(v.invoice_no)}</h3>
      <div class="rc-hint" style="margin:0;">${fnEsc(v.patient || '')} &middot; ${fnEsc(String(v.created_at).slice(0, 10))} &middot; total ${fnMoney(v.total)} RWF &middot; ${fnEsc(v.status)}</div></div>
      <button class="fo-btn alt sm rc-btn" onclick="rvClose()">Close</button></div>
    ${lock}
    <h4 style="font-size:13px;margin:14px 0 4px;">Insurer and member number</h4>
    <div class="fo-row" style="align-items:flex-end;gap:8px;">
      <div style="flex:1;"><label class="fo-lbl">Insurer</label><select class="fo-in" id="rv-ins" ${dis}>${fnOpts(rvInsurers, 'id', x => x.name, v.insurance_id)}</select></div>
      <button class="fo-btn sm rc-btn" ${dis} onclick="rvSaveInsurer(${v.id}, ${claimId}, this)">Change insurer</button></div>
    <div class="fo-row" style="align-items:flex-end;gap:8px;margin-top:8px;">
      <div style="flex:1;"><label class="fo-lbl">Member number</label><input class="fo-in" id="rv-member" value="${fnEsc(v.member ? v.member.member_number : '')}" ${memDis}></div>
      <button class="fo-btn sm rc-btn" ${memDis} onclick="rvSaveMember(${v.id}, ${claimId}, this)">Save</button></div>
    <h4 style="font-size:13px;margin:16px 0 4px;">Lines</h4>
    <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Item</th><th>Qty</th><th>Discount</th><th class="num">Unit price</th><th class="num">Total</th><th></th></tr></thead>
      <tbody>${lines || '<tr><td colspan="6" class="fo-empty">No lines.</td></tr>'}</tbody></table></div>
    <div class="rc-hint">The server looks every price up in the insurer's tariff; a price cannot be typed.</div>
    <div class="fo-row" style="margin-top:8px;"><button class="fo-btn alt sm rc-btn" ${dis} onclick="rvRepriceAll(${v.id}, ${claimId}, this)">Re-price all lines</button></div>
    <h4 style="font-size:13px;margin:16px 0 4px;">Add a line</h4>
    <div class="fo-row" style="align-items:flex-end;gap:8px;">
      <div style="flex:1;"><input class="fo-in" id="rv-q" placeholder="Search the service catalog" ${dis} oninput="rvSearch(${v.insurance_id})"></div>
      <div style="width:70px;"><input class="fo-in" id="rv-qty" type="number" min="1" value="1" ${dis}></div></div>
    <select class="fo-in" id="rv-res" size="5" style="margin-top:6px;display:none;" ${dis}></select>
    <button class="fo-btn sm rc-btn" style="margin-top:6px;" ${dis} onclick="rvAddLine(${v.id}, ${claimId}, this)">Add line</button>
    <h4 style="font-size:13px;margin:16px 0 4px;">Edit history</h4>${audit}`);
  m.classList.add('rc-drawer');
}
function rvSearch(insId){
  clearTimeout(rvTimer);
  rvTimer = setTimeout(async () => {
    const q = document.getElementById('rv-q').value.trim(), sel = document.getElementById('rv-res');
    if(q.length < 2){ sel.style.display = 'none'; return; }
    try {
      const j = await (await fetch(_fnBase + '/api.php?endpoint=service-catalog&insurance_id=' + insId + '&search=' + encodeURIComponent(q))).json();
      const rows = (j.data || []).slice(0, 40);
      sel.innerHTML = rows.map(r => `<option value="${r.id}" data-name="${fnEsc(r.procedure_name)}">${fnEsc(r.procedure_name)} - ${fnMoney(r.primary_health_facility)}</option>`).join('');
      sel.style.display = rows.length ? '' : 'none';
    } catch(e){}
  }, 300);
}
async function rvAfter(id, ok){ if(ok){ await rvOpen(id, rvClaim); clLoad(); } }
async function rvAddLine(id, claimId, btn){
  const o = document.getElementById('rv-res').selectedOptions[0];
  if(!o) return alert('Search and pick a service first');
  btn.disabled = true;
  const ok = await rvBill(id, {action: 'add_item', service_id: +o.value, description: o.dataset.name, category: 'procedure', quantity: +document.getElementById('rv-qty').value || 1});
  btn.disabled = false; rvAfter(id, ok);
}
function rvLineBody(l, qty, disc){
  return {action: 'edit_item', item_id: +l.id, service_id: l.service_id || null, description: l.description, category: l.category, quantity: qty, discount: disc};
}
async function rvSaveLine(id, itemId, btn, reprice){
  const tr = btn.closest('tr'), l = rvLoaded[itemId];
  btn.disabled = true;
  const ok = await rvBill(id, reprice ? rvLineBody(l, +l.quantity, +l.discount) : rvLineBody(l, +tr.querySelector('[data-f=q]').value || 1, +tr.querySelector('[data-f=d]').value || 0));
  btn.disabled = false; rvAfter(id, ok);
}
async function rvRemoveLine(id, itemId){
  if(!confirm('Remove this line from the invoice?')) return;
  rvAfter(id, await rvBill(id, {action: 'remove_item', item_id: itemId}));
}
async function rvRepriceAll(id, claimId, btn){
  if(!confirm('Look every line up again in the tariff?')) return;
  btn.disabled = true;
  let ok = true;
  for(const k of Object.keys(rvLoaded)){ const l = rvLoaded[k]; if(!(ok = await rvBill(id, rvLineBody(l, +l.quantity, +l.discount)))) break; }
  btn.disabled = false; rvAfter(id, true);
}
async function rvSaveInsurer(id, claimId, btn){
  if(!confirm('Change the insurer on this invoice? Lines keep their current prices until you re-price them.')) return;
  btn.disabled = true;
  const ok = await rvBill(id, {insurance_id: +document.getElementById('rv-ins').value});
  btn.disabled = false; rvAfter(id, ok);
}
async function rvSaveMember(id, claimId, btn){
  await rcPost(btn, 'claim_member_number', {invoice_id: id, member_number: document.getElementById('rv-member').value}, async () => { await rvOpen(id, claimId); });
}
async function rvRequestEdit(id, no, claimId, btn){
  const reason = document.getElementById('rv-reason').value.trim();
  if(reason.length < 10) return alert('Give the reason in at least 10 characters');
  btn.disabled = true;
  try {
    const j = await (await fetch(_fnBase + '/api.php?endpoint=edit-requests', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({entity_type: 'invoice', entity_id: id, entity_name: no, reason: reason})})).json();
    if(!j.success){ alert(j.message || 'Failed'); btn.disabled = false; return; }
    alert('Edit request sent. An administrator approves it on the Approvals page.');
    await rvOpen(id, claimId);
  } catch(e){ alert('Request failed: ' + e.message); btn.disabled = false; }
}
clInit();
</script>

<?php endif; ?>
