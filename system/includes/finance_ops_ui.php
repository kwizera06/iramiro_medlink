<?php
// includes/finance_ops_ui.php
// Finance screens that sit on top of the ledger (included by finance.php; $subModule picks the screen).
// Data comes from api.php?endpoint=finance/finance-ops — see handlers/finance/finance-ops.php.
?>
<style>
.fo-card{background:white;border-radius:var(--radius);padding:20px 24px;box-shadow:var(--shadow);margin-bottom:20px;}
.fo-card h3{font-size:15px;font-weight:600;margin-bottom:14px;}
.fo-card h3 i{color:var(--primary-color);margin-right:6px;}
.fo-grid{display:grid;grid-template-columns:minmax(300px,380px) 1fr;gap:20px;align-items:start;}
@media(max-width:1000px){.fo-grid{grid-template-columns:1fr;}}
.fo-lbl{display:block;font-size:12px;font-weight:600;color:var(--gray-600);margin:10px 0 4px;}
.fo-in{width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;box-sizing:border-box;}
.fo-btn{padding:8px 16px;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;background:var(--primary-color);color:white;}
.fo-btn.alt{background:var(--gray-50);color:var(--gray-700);border:1px solid var(--gray-200);}
.fo-btn.danger{background:#fee2e2;color:#991b1b;}
.fo-btn.sm{padding:4px 10px;font-size:11px;}
.fo-btn:disabled{opacity:.5;cursor:not-allowed;}
.fo-tbl{width:100%;border-collapse:collapse;font-size:13px;}
.fo-tbl th{background:var(--gray-50);padding:9px 12px;text-align:left;font-weight:600;border-bottom:2px solid var(--gray-200);white-space:nowrap;}
.fo-tbl td{padding:9px 12px;border-bottom:1px solid var(--gray-100);vertical-align:top;}
.fo-tbl .num{text-align:right;font-variant-numeric:tabular-nums;}
.fo-wrap{border:1px solid var(--gray-200);border-radius:8px;overflow:auto;}
.fo-tag{display:inline-block;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:600;}
.fo-tag.ok{background:#dcfce7;color:#166534;}.fo-tag.warn{background:#fef3c7;color:#92400e;}.fo-tag.bad{background:#fee2e2;color:#991b1b;}.fo-tag.gray{background:var(--gray-100);color:var(--gray-600);}
.fo-empty{padding:26px;text-align:center;color:var(--gray-400);}
.fo-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px;}
.fo-modal{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;}
.fo-modal>div{background:white;border-radius:12px;width:440px;max-width:92vw;max-height:88vh;overflow:auto;padding:22px;}
</style>
<script>
const _foApi = _fnBase + '/api.php?endpoint=finance/finance-ops';
async function fnOps(action, opts){
  opts = opts || {};
  const qs = new URLSearchParams(Object.assign({action: action}, opts.params || {}));
  const init = opts.body ? {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(opts.body)} : {};
  try { const r = await fetch(_foApi + '&' + qs.toString(), init); return await r.json(); }
  catch(e){ return {success:false, message:'Request failed: ' + e.message}; }
}
function fnEsc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fnMoney(v){return Number(v||0).toLocaleString(undefined,{maximumFractionDigits:2});}
function fnToday(){return new Date().toISOString().slice(0,10);}
function fnOpts(list, valKey, labelFn, selected){return list.map(x=>`<option value="${fnEsc(x[valKey])}" ${String(x[valKey])===String(selected)?'selected':''}>${fnEsc(labelFn(x))}</option>`).join('');}
function fnModal(html){const m=document.createElement('div');m.className='fo-modal';m.innerHTML='<div>'+html+'</div>';m.addEventListener('click',e=>{if(e.target===m)m.remove();});document.body.appendChild(m);return m;}
async function fnAct(btn, action, body, okMsg, after){
  if(btn) btn.disabled = true;
  const j = await fnOps(action, {body: body});
  if(btn) btn.disabled = false;
  if(!j.success){ alert(j.message || 'Failed'); return null; }
  if(okMsg) alert(okMsg === true ? (j.message || 'Done') : okMsg);
  if(after) after(j);
  return j;
}
</script>

<?php if ($subModule == 'expenses'): ?>
<!-- ====== EXPENSES & SUPPLIER PAYABLES ====== -->
<div class="fo-grid">
  <div class="fo-card">
    <h3><i class="fas fa-receipt"></i> Record expense</h3>
    <label class="fo-lbl">Date</label><input class="fo-in" type="date" id="ex-date">
    <label class="fo-lbl">Expense account</label><select class="fo-in" id="ex-acc"></select>
    <label class="fo-lbl">Amount (RWF)</label><input class="fo-in" type="number" min="0" step="0.01" id="ex-amount">
    <label class="fo-lbl">Paid from</label><select class="fo-in" id="ex-pay" onchange="exPayChange()"></select>
    <div id="ex-credit-box" style="display:none;">
      <label class="fo-lbl">Supplier (optional)</label><select class="fo-in" id="ex-supplier"></select>
      <label class="fo-lbl">Payee / who is owed</label><input class="fo-in" id="ex-payee" placeholder="e.g. landlord, utility company">
    </div>
    <label class="fo-lbl">Department (cost centre)</label><select class="fo-in" id="ex-dept"><option value="">— none —</option></select>
    <label class="fo-lbl">Reference / invoice no.</label><input class="fo-in" id="ex-ref">
    <label class="fo-lbl">Description</label><input class="fo-in" id="ex-desc">
    <button class="fo-btn" style="margin-top:14px;width:100%;" onclick="exSave(this)"><i class="fas fa-check"></i> Post expense</button>
    <p style="font-size:11px;color:var(--gray-500);margin-top:8px;">Paid: Dr expense, Cr the cash / bank account. Not paid yet: Dr expense, Cr supplier payable.</p>
  </div>
  <div>
    <div class="fo-card">
      <h3><i class="fas fa-list"></i> Expenses</h3>
      <div class="fo-row">
        <input class="fo-in" style="width:auto;" type="date" id="ex-from"><input class="fo-in" style="width:auto;" type="date" id="ex-to">
        <button class="fo-btn alt" onclick="exLoad()"><i class="fas fa-search"></i> Filter</button>
        <span id="ex-total" style="margin-left:auto;font-weight:700;"></span>
      </div>
      <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Date</th><th>Account</th><th>Details</th><th>Paid from</th><th class="num">Amount</th><th>Status</th><th></th></tr></thead><tbody id="ex-tbody"></tbody></table></div>
    </div>
    <div class="fo-card">
      <h3><i class="fas fa-landmark"></i> Deductions and taxes to pay over <span style="font-size:11px;font-weight:400;color:var(--gray-500);">PAYE and tax withheld from suppliers to RRA, pension to RSSB</span></h3>
      <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Deduction</th><th class="num">We owe</th><th></th></tr></thead><tbody id="rm-tbody"></tbody></table></div>
      <h3 style="margin-top:20px;">Recent remittances</h3>
      <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Date</th><th>Deduction</th><th>Period</th><th>Paid from</th><th>Reference</th><th class="num">Amount</th><th></th></tr></thead><tbody id="rm-hist"></tbody></table></div>
    </div>
  </div>
</div>
<script>
let exCash = [], exSuppliers = [];
function exPayChange(){ document.getElementById('ex-credit-box').style.display = document.getElementById('ex-pay').value ? 'none' : 'block'; }
async function exInit(){
  document.getElementById('ex-date').value = fnToday();
  dfRange('ex-from', 'ex-to');
  const [a, c, s, dp] = await Promise.all([fnOps('expense_accounts'), fnOps('cash_accounts'), fnOps('suppliers_payable'), fnOps('departments')]);
  const dSel = document.getElementById('ex-dept'), dKeep = dSel.value;
  dSel.innerHTML = '<option value="">— none —</option>' + fnOpts(dp.data||[], 'id', x=>x.name, dKeep);
  document.getElementById('ex-acc').innerHTML = '<option value="">Choose…</option>' + fnOpts(a.data||[], 'id', x=>x.account_code+' '+x.account_name);
  exCash = c.data || [];
  document.getElementById('ex-pay').innerHTML = '<option value="">Not paid yet (payable)</option>' + fnOpts(exCash, 'id', x=>x.account_name+' ('+fnMoney(x.balance)+')');
  exSuppliers = (s.data && s.data.suppliers) || [];
  document.getElementById('ex-supplier').innerHTML = '<option value="">— none —</option>' + fnOpts(exSuppliers, 'id', x=>x.company_name);
  exPayChange(); exLoad(); rmLoad();
}
async function exLoad(){
  const j = await fnOps('expenses', {params:{from: document.getElementById('ex-from').value, to: document.getElementById('ex-to').value}});
  const tb = document.getElementById('ex-tbody');
  if(!j.success){ tb.innerHTML = `<tr><td colspan="7" class="fo-empty">${fnEsc(j.message)}</td></tr>`; return; }
  document.getElementById('ex-total').textContent = 'Total: ' + fnMoney(j.data.total) + ' RWF';
  tb.innerHTML = j.data.expenses.length ? j.data.expenses.map(e=>`<tr style="${e.status==='voided'?'opacity:.5;text-decoration:line-through;':''}">
    <td>${fnEsc(e.expense_date)}</td><td>${fnEsc(e.expense_account||'')}</td>
    <td>${fnEsc(e.description||'')}<div style="font-size:11px;color:var(--gray-500);">${fnEsc([e.payee, e.supplier, e.reference].filter(Boolean).join(' · '))}</div></td>
    <td>${fnEsc(e.pay_account||'—')}</td><td class="num">${fnMoney(e.amount)}</td>
    <td><span class="fo-tag ${e.status==='paid'?'ok':(e.status==='payable'?'warn':'gray')}">${fnEsc(e.status)}</span></td>
    <td>${e.status!=='voided'?`<button class="fo-btn danger sm" onclick="exVoid(${e.id},this)">Void</button>`:''}</td></tr>`).join('')
    : '<tr><td colspan="7" class="fo-empty">No expenses in this range.</td></tr>';
}
async function exSave(btn){
  const body = {expense_date: document.getElementById('ex-date').value, expense_account_id: document.getElementById('ex-acc').value, amount: document.getElementById('ex-amount').value,
    pay_account_id: document.getElementById('ex-pay').value, supplier_id: document.getElementById('ex-supplier').value, payee: document.getElementById('ex-payee').value, department_id: document.getElementById('ex-dept').value,
    reference: document.getElementById('ex-ref').value, description: document.getElementById('ex-desc').value};
  const j = await fnAct(btn, 'expense', body, 'Expense posted to the ledger');
  if(j){ ['ex-amount','ex-ref','ex-desc','ex-payee'].forEach(id=>document.getElementById(id).value=''); exInit(); }
}
async function exVoid(id, btn){ if(!confirm('Void this expense? Its ledger entry is removed (or reversed if the month is closed).')) return; if(await fnAct(btn, 'expense_void', {id:id}, true)) exInit(); }
async function rmLoad(){
  const j = await fnOps('statutory_payables'); const d = j.data || {payables:[], remittances:[]};
  document.getElementById('rm-tbody').innerHTML = d.payables.map(p=>`<tr><td>${fnEsc(p.label)}</td><td class="num" style="font-weight:600;color:${p.owed>0?'#dc2626':'inherit'};">${fnMoney(p.owed)}</td>
    <td>${p.owed>0?`<button class="fo-btn sm" onclick="rmPay('${p.key}', '${fnEsc(p.label)}', ${p.owed})">Pay</button>`:''}</td></tr>`).join('');
  document.getElementById('rm-hist').innerHTML = d.remittances.length ? d.remittances.map(r=>`<tr style="${r.status==='voided'?'opacity:.5;text-decoration:line-through;':''}"><td>${fnEsc(r.remit_date)}</td><td>${fnEsc(r.label)}</td><td>${fnEsc(r.period||'')}</td><td>${fnEsc(r.pay_account)}</td><td>${fnEsc(r.reference||'')}</td><td class="num">${fnMoney(r.amount)}</td>
    <td>${r.status!=='voided'?`<button class="fo-btn danger sm" onclick="rmVoid(${r.id},this)">Void</button>`:''}</td></tr>`).join('') : '<tr><td colspan="7" class="fo-empty">No remittances yet.</td></tr>';
}
function rmPay(key, label, owed){
  const m = fnModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Pay over: ${label}</h3><div style="color:var(--gray-600);font-size:13px;">Owed ${fnMoney(owed)} RWF</div>
    <label class="fo-lbl">Date</label><input class="fo-in" type="date" id="rm-date" value="${fnToday()}">
    <label class="fo-lbl">Payroll month</label><input class="fo-in" type="month" id="rm-period">
    <label class="fo-lbl">Amount</label><input class="fo-in" type="number" step="0.01" id="rm-amount" value="${owed}">
    <label class="fo-lbl">Paid from</label><select class="fo-in" id="rm-from">${fnOpts(exCash,'id',x=>x.account_name+' ('+fnMoney(x.balance)+')')}</select>
    <label class="fo-lbl">Reference (RRA / RSSB receipt)</label><input class="fo-in" id="rm-ref">
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="rm-go" style="flex:1;">Record payment</button><button class="fo-btn alt" onclick="this.closest('.fo-modal').remove()">Cancel</button></div>`);
  m.querySelector('#rm-go').onclick = async function(){
    const j = await fnAct(this, 'remittance', {liability_key:key, remit_date:m.querySelector('#rm-date').value, period:m.querySelector('#rm-period').value, amount:m.querySelector('#rm-amount').value,
      pay_account_id:m.querySelector('#rm-from').value, reference:m.querySelector('#rm-ref').value}, true);
    if(j){ m.remove(); exInit(); }
  };
}
async function rmVoid(id, btn){ if(!confirm('Void this remittance? The amount becomes owed again.')) return; if(await fnAct(btn, 'remittance_void', {id:id}, true)) exInit(); }
exInit();
</script>

<?php elseif ($subModule == 'bank-reconciliation'): ?>
<!-- ====== BANK RECONCILIATION (saved; matched on the server) ====== -->
<div class="fo-grid">
  <div>
    <div class="fo-card">
      <h3><i class="fas fa-file-upload"></i> New bank statement</h3>
      <label class="fo-lbl">Bank account</label><select class="fo-in" id="br-account"></select>
      <label class="fo-lbl">Statement CSV <a href="#" onclick="brSample();return false;" style="font-weight:400;">sample</a></label><input class="fo-in" type="file" id="br-file" accept=".csv" onchange="brParse()">
      <p style="font-size:11px;color:var(--gray-500);margin-top:4px;">Columns: Date, Description, Reference, Money in, Money out, Balance (header row first)</p>
      <label class="fo-lbl">Statement date</label><input class="fo-in" type="date" id="br-date">
      <label class="fo-lbl">Closing balance on the statement</label><input class="fo-in" type="number" step="0.01" id="br-bal">
      <div id="br-parsed" style="font-size:12px;color:var(--gray-600);margin-top:6px;"></div>
      <button class="fo-btn" style="margin-top:14px;width:100%;" onclick="brSave(this)"><i class="fas fa-check-double"></i> Save &amp; match</button>
    </div>
    <div class="fo-card">
      <h3><i class="fas fa-history"></i> Reconciliations</h3>
      <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Date</th><th>Account</th><th class="num">Statement</th><th>Status</th></tr></thead><tbody id="br-hist"></tbody></table></div>
    </div>
  </div>
  <div class="fo-card" id="br-view"><div class="fo-empty">Upload a statement, or open a reconciliation from the list.</div></div>
</div>
<script>
let brLines = [], brCur = null;
async function brInit(){
  const c = await fnOps('cash_accounts');
  document.getElementById('br-account').innerHTML = fnOpts(c.data||[], 'id', x=>x.account_name+' ('+fnMoney(x.balance)+')');
  document.getElementById('br-date').value = fnToday();
  brHist();
}
async function brHist(){
  const j = await fnOps('bank_recs');
  document.getElementById('br-hist').innerHTML = (j.data||[]).length ? j.data.map(r=>`<tr style="cursor:pointer;" onclick="brOpen(${r.id})"><td>${fnEsc(r.reconciliation_date)}</td><td>${fnEsc(r.account_name)}</td><td class="num">${fnMoney(r.bank_statement_balance)}</td>
    <td><span class="fo-tag ${r.status==='completed'?'ok':'warn'}">${fnEsc(r.status)}</span></td></tr>`).join('') : '<tr><td colspan="4" class="fo-empty">None yet.</td></tr>';
}
function brParse(){
  const f = document.getElementById('br-file').files[0]; if(!f) return;
  const rd = new FileReader();
  rd.onload = e => {
    const rows = e.target.result.split(/\r?\n/).filter(l=>l.trim()).slice(1).map(l=>l.split(',').map(c=>c.trim().replace(/^"|"$/g,'')));
    brLines = rows.filter(c=>c.length>=5).map(c=>({date:c[0], description:c[1], reference:c[2], money_in:parseFloat(c[3])||0, money_out:parseFloat(c[4])||0, balance:parseFloat(c[5])||0}));
    const last = brLines[brLines.length-1];
    if(last){ document.getElementById('br-date').value = last.date; if(last.balance) document.getElementById('br-bal').value = last.balance; }
    document.getElementById('br-parsed').textContent = brLines.length + ' statement lines read';
  };
  rd.readAsText(f);
}
async function brSave(btn){
  if(!brLines.length) return alert('Choose a statement CSV first');
  const j = await fnAct(btn, 'bank_rec_save', {account_id:document.getElementById('br-account').value, statement_date:document.getElementById('br-date').value,
    statement_balance:document.getElementById('br-bal').value, lines:brLines}, true);
  if(j){ brRender(j.data); brHist(); }
}
async function brOpen(id){ const j = await fnOps('bank_rec', {params:{id:id}}); if(j.success) brRender(j.data); else alert(j.message); }
function brRender(d){
  brCur = d; const t = d.totals, draft = d.rec.status === 'draft';
  const row = (l, v, b) => `<tr><td style="${b?'font-weight:700;':''}">${l}</td><td class="num" style="${b?'font-weight:700;':''}">${v}</td></tr>`;
  const missing = d.lines.filter(x=>!x.journal_line_id);
  document.getElementById('br-view').innerHTML = `<h3><i class="fas fa-check-double"></i> ${fnEsc(d.rec.account_name)} — statement of ${fnEsc(d.rec.reconciliation_date)} <span class="fo-tag ${draft?'warn':'ok'}">${fnEsc(d.rec.status)}</span></h3>
    <div class="fo-wrap" style="margin-bottom:14px;"><table class="fo-tbl">
      ${row('Balance on bank statement', fnMoney(t.statement_balance))}${row('Less: payments in our books, not yet through the bank', '('+fnMoney(t.outstanding_payments)+')')}
      ${row('Add: receipts in our books, not yet through the bank', fnMoney(t.deposits_in_transit))}${row('Adjusted bank balance', fnMoney(t.adjusted_bank), 1)}
      ${row('Balance in our books', fnMoney(t.book_balance))}${row('Add / less: bank items not yet in our books', fnMoney(t.bank_only_in - t.bank_only_out))}
      ${row('Adjusted book balance', fnMoney(t.adjusted_book), 1)}
      <tr><td style="font-weight:700;">Difference</td><td class="num" style="font-weight:700;color:${Math.abs(t.difference)<0.01?'#166534':'#991b1b'};">${fnMoney(t.difference)}</td></tr></table></div>
    <h3 style="font-size:13px;">Statement lines (${d.lines.length - missing.length} matched, ${missing.length} missing from our books)</h3>
    <div class="fo-wrap" style="max-height:260px;margin-bottom:14px;"><table class="fo-tbl"><thead><tr><th>Date</th><th>Description</th><th class="num">In</th><th class="num">Out</th><th></th></tr></thead><tbody>
      ${d.lines.map(x=>`<tr><td>${fnEsc(x.transaction_date)}</td><td>${fnEsc(x.description)}<div style="font-size:11px;color:var(--gray-500);">${fnEsc(x.reference||'')}</div></td><td class="num">${+x.debit?fnMoney(x.debit):''}</td><td class="num">${+x.credit?fnMoney(x.credit):''}</td>
        <td>${x.journal_line_id?`<span class="fo-tag ok">matched</span>${draft?` <button class="fo-btn alt sm" onclick="brUnmatch(${x.id},this)">Undo</button>`:''}`:(draft?`<button class="fo-btn sm" onclick="brFix(${x.id})">Post / match</button>`:'')}</td></tr>`).join('')}
    </tbody></table></div>
    <h3 style="font-size:13px;">In our books, not on this statement (${d.open_ledger.length})</h3>
    <div class="fo-wrap" style="max-height:200px;"><table class="fo-tbl"><thead><tr><th>Date</th><th>Entry</th><th class="num">In</th><th class="num">Out</th></tr></thead><tbody>
      ${d.open_ledger.length ? d.open_ledger.map(o=>`<tr><td>${fnEsc(o.entry_date)}</td><td>${fnEsc(o.reference||'')} ${fnEsc(o.description||'')}</td><td class="num">${+o.debit?fnMoney(o.debit):''}</td><td class="num">${+o.credit?fnMoney(o.credit):''}</td></tr>`).join('') : '<tr><td colspan="4" class="fo-empty">Nothing outstanding.</td></tr>'}
    </tbody></table></div>
    ${draft?`<div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" style="flex:1;" onclick="brComplete(this)"><i class="fas fa-lock"></i> Complete reconciliation</button><button class="fo-btn danger" onclick="brDelete(this)">Delete draft</button></div>`:''}`;
}
async function brFix(lineId){
  const x = brCur.lines.find(l=>+l.id===lineId), isIn = +x.debit > 0, amt = isIn ? +x.debit : +x.credit;
  const same = brCur.open_ledger.filter(o=>Math.abs((isIn ? +o.debit : +o.credit) - amt) < 0.01 && !(isIn ? +o.credit : +o.debit));
  const acc = await fnOps('expense_accounts', {params:{type: isIn ? 'income' : 'expense'}});
  const m = fnModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">${fnEsc(x.description||'Statement line')} — ${fnMoney(amt)} ${isIn?'in':'out'}</h3>
    ${same.length?`<label class="fo-lbl">Match to an entry already in our books</label><select class="fo-in" id="bf-jl">${fnOpts(same,'id',o=>o.entry_date+' '+(o.reference||'')+' '+(o.description||''))}</select>
      <button class="fo-btn alt" style="margin-top:8px;width:100%;" id="bf-match">Match</button><div style="text-align:center;color:var(--gray-400);margin:10px 0;">or</div>`:''}
    <label class="fo-lbl">Post it to the books against (e.g. bank charges, interest)</label><select class="fo-in" id="bf-acc">${fnOpts(acc.data||[],'id',a=>a.account_code+' '+a.account_name)}</select>
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="bf-post" style="flex:1;">Post</button><button class="fo-btn alt" onclick="this.closest('.fo-modal').remove()">Cancel</button></div>`);
  if(same.length) m.querySelector('#bf-match').onclick = async function(){ const j = await fnAct(this, 'bank_rec_match', {line_id:lineId, journal_line_id:m.querySelector('#bf-jl').value}); if(j){ m.remove(); brRender(j.data); } };
  m.querySelector('#bf-post').onclick = async function(){ const j = await fnAct(this, 'bank_rec_post_line', {line_id:lineId, account_id:m.querySelector('#bf-acc').value}); if(j){ m.remove(); brRender(j.data); } };
}
async function brUnmatch(lineId, btn){ const j = await fnAct(btn, 'bank_rec_match', {line_id:lineId, journal_line_id:null}); if(j) brRender(j.data); }
async function brComplete(btn){ if(!confirm('Complete this reconciliation? It is locked afterwards.')) return; if(await fnAct(btn, 'bank_rec_complete', {id:brCur.rec.id}, true)){ brOpen(brCur.rec.id); brHist(); } }
async function brDelete(btn){ if(!confirm('Delete this draft? Entries already posted from it stay in the books.')) return; if(await fnAct(btn, 'bank_rec_delete', {id:brCur.rec.id}, true)){ document.getElementById('br-view').innerHTML='<div class="fo-empty">Draft deleted.</div>'; brHist(); } }
function brSample(){
  const csv = 'Date,Description,Reference,Money in,Money out,Balance\n2026-10-01,Patient payments,,250000,0,1250000\n2026-10-03,Supplier payment,SUP-20261003-00012,0,180000,1070000\n2026-10-31,Bank charges,FEE,0,1500,1068500';
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv],{type:'text/csv'})); a.download = 'bank-statement-sample.csv'; a.click();
}
brInit();
</script>

<?php elseif ($subModule == 'fs-notes'): ?>
<!-- ====== NOTES TO THE FINANCIAL STATEMENTS ====== -->
<div class="fo-card">
  <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-sticky-note"></i> Notes to the financial statements</h3>
    <input class="fo-in" style="width:auto;margin-left:auto;" type="date" id="nt-date" onchange="ntLoad()"><button class="fo-btn alt" onclick="window.print()"><i class="fas fa-print"></i> Print</button></div>
  <div id="nt-body"><div class="fo-empty">Loading…</div></div>
</div>
<script>
function ntTable(head, rows){ return `<div class="fo-wrap" style="margin:6px 0 16px;"><table class="fo-tbl"><thead><tr>${head.map((h,i)=>`<th class="${i&&typeof rows[0]?.[i]==='number'?'num':''}">${h}</th>`).join('')}</tr></thead><tbody>${rows.length?rows.map(r=>`<tr>${r.map(c=>`<td class="${typeof c==='number'?'num':''}">${typeof c==='number'?fnMoney(c):fnEsc(c)}</td>`).join('')}</tr>`).join(''):`<tr><td colspan="${head.length}" class="fo-empty">None.</td></tr>`}</tbody></table></div>`; }
async function ntLoad(){
  const j = await fnOps('fs_notes', {params:{as_of: document.getElementById('nt-date').value}});
  if(!j.success){ document.getElementById('nt-body').innerHTML = `<div class="fo-empty">${fnEsc(j.message)}</div>`; return; }
  const d = j.data, h = t => `<h4 style="font-size:14px;font-weight:700;margin-top:14px;">${t}</h4>`, sum = (a,k) => a.reduce((s,x)=>s+Number(x[k]||0),0);
  document.getElementById('nt-body').innerHTML =
    h('1. Accounting policies') + `<p style="font-size:13px;">Stock is valued at purchase cost and issued earliest-expiry first. Equipment is carried at cost less accumulated depreciation, by category:</p>`
      + ntTable(['Asset category','Method','Rate %','Useful life (years)'], d.policies.map(p=>[p.name, (p.depreciation_method||'').replace('_',' '), String(p.depreciation_rate), String(p.useful_life_years)]))
    + h('2. Inventories') + ntTable(['Store','Value'], d.inventory.stores.map(s=>[s.store, Number(s.value)]))
      + `<p style="font-size:13px;">Written off this year: expired stock ${fnMoney(d.inventory.expired_written_off)} RWF; stock count adjustments ${fnMoney(d.inventory.count_adjustments)} RWF.</p>`
    + h('3. Property, plant and equipment') + ntTable(['Category','Items','Cost','Accumulated depreciation','Carrying amount'], d.ppe.categories.map(c=>[c.category||'Uncategorised', String(c.count), Number(c.cost), Number(c.acc_dep), c.cost - c.acc_dep]))
      + `<p style="font-size:13px;">Depreciation charged this year: ${fnMoney(d.ppe.depreciation_this_year)} RWF. Assets disposed of this year: ${d.ppe.disposals_this_year}.</p>`
    + h('4. Receivables') + ntTable(['Owed by','Balance'], d.receivables.insurers.map(i=>[i.insurer, Number(i.balance)]).concat([['Patients (total)', Number(d.receivables.patients)]]))
    + h('5. Payables') + ntTable(['Owed to','Balance'], [['Suppliers (billed)', Number(d.payables.suppliers)], ['Goods received, bill not yet received', Number(d.payables.goods_not_invoiced)]].concat(d.payables.payroll.map(p=>[p.item, Number(p.owed)])))
    + h('6. Commitments') + `<p style="font-size:13px;">Signed contracts not yet delivered or billed. They are not recorded as liabilities.</p>`
      + ntTable(['Supplier','Contract','Ends','Commitment'], d.commitments.map(c=>[c.company_name, c.title, c.end_date||'open', Number(c.remaining)]))
      + `<p style="font-size:13px;font-weight:600;">Total commitments: ${fnMoney(sum(d.commitments,'remaining'))} RWF</p>`
    + h('7. Cash and bank') + ntTable(['Account','Balance','Last reconciled'], d.banks.map(b=>[b.account, Number(b.balance), b.last_reconciled||'never']));
}
document.getElementById('nt-date').value = fnToday();
ntLoad();
</script>
<?php endif; ?>
