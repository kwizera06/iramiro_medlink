<?php
// includes/finance_close_ui.php
// Year-End Close and Statement of Changes in Equity screens (included by finance.php; $subModule picks the screen).
// Data comes from api.php?endpoint=finance/finance-close — see handlers/finance/finance-close.php.
require __DIR__ . '/finance_ops_ui.php'; // shared styles and helpers (fnEsc, fnMoney, fnModal); draws nothing for these screens
?>
<style>
.fc-btn-press{transition:transform .12s ease-out,background-color .12s ease-out;}
.fc-btn-press:active:not(:disabled){transform:scale(.97);}
.fc-num-neg{color:#991b1b;}
.fc-total td{font-weight:700;border-top:2px solid var(--gray-200);background:var(--gray-50);}
.fc-note{font-size:12px;color:var(--gray-500);margin-top:10px;line-height:1.5;}
.fc-check{margin-top:12px;padding:10px 12px;border-radius:6px;font-size:12px;font-weight:600;}
.fc-check.ok{background:#dcfce7;color:#166534;}.fc-check.bad{background:#fee2e2;color:#991b1b;}
</style>
<script>
const _fcApi = _fnBase + '/api.php?endpoint=finance/finance-close';
async function fcCall(action, opts){
  opts = opts || {};
  const qs = new URLSearchParams(Object.assign({action: action}, opts.params || {}));
  const init = opts.body ? {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(opts.body)} : {};
  try { const r = await fetch(_fcApi + '&' + qs.toString(), init); return await r.json(); }
  catch(e){ return {success:false, message:'Request failed: ' + e.message}; }
}
function fcAmt(v){ v = Number(v||0); return `<span class="${v<0?'fc-num-neg':''}">${v<0?'('+fnMoney(-v)+')':fnMoney(v)}</span>`; }
</script>

<?php if ($subModule == 'equity-statement'): ?>
<!-- ====== STATEMENT OF CHANGES IN EQUITY ====== -->
<div class="fo-card">
  <h3><i class="fas fa-layer-group"></i> Statement of Changes in Equity</h3>
  <div class="fo-row">
    <label class="fo-lbl" style="margin:0;">From</label><input type="date" id="eq-from" class="fo-in" style="width:auto;">
    <label class="fo-lbl" style="margin:0;">To</label><input type="date" id="eq-to" class="fo-in" style="width:auto;">
    <button class="fo-btn fc-btn-press" id="eq-go" onclick="eqLoad()">Show</button>
    <button class="fo-btn alt fc-btn-press" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  </div>
  <div class="fo-wrap"><table class="fo-tbl">
    <thead><tr><th>Equity</th><th class="num">Opening balance</th><th class="num">Surplus / (deficit) for the period</th><th class="num">Other movements</th><th class="num">Closing balance</th></tr></thead>
    <tbody id="eq-body"><tr><td colspan="5" class="fo-empty">Loading...</td></tr></tbody>
  </table></div>
  <div id="eq-check"></div>
  <p class="fc-note">Surplus is income minus expenses for the period. Other movements are capital introduced, opening balances, revaluation surplus and any manual equity journals. A year-end close moves the surplus to retained earnings without changing these totals.</p>
</div>
<script>
document.getElementById('eq-to').value = fnToday();
document.getElementById('eq-from').value = fnToday().slice(0,4) + '-01-01';
async function eqLoad(){
  const btn = document.getElementById('eq-go'), body = document.getElementById('eq-body');
  btn.disabled = true;
  const j = await fcCall('equity_statement', {params:{from: document.getElementById('eq-from').value, to: document.getElementById('eq-to').value}});
  btn.disabled = false;
  if(!j.success){ body.innerHTML = `<tr><td colspan="5" class="fo-empty" style="color:#991b1b;">${fnEsc(j.message || 'Failed')}</td></tr>`; document.getElementById('eq-check').innerHTML=''; return; }
  const d = j.data;
  body.innerHTML = d.rows.map(r => `<tr><td>${fnEsc(r.account_code)} ${fnEsc(r.account_name)}</td><td class="num">${fcAmt(r.opening)}</td><td class="num">${fcAmt(r.surplus)}</td><td class="num">${fcAmt(r.other)}</td><td class="num">${fcAmt(r.closing)}</td></tr>`).join('')
    + `<tr class="fc-total"><td>Total equity</td><td class="num">${fcAmt(d.totals.opening)}</td><td class="num">${fcAmt(d.totals.surplus)}</td><td class="num">${fcAmt(d.totals.other)}</td><td class="num">${fcAmt(d.totals.closing)}</td></tr>`;
  const ok = Math.abs(d.difference) < 0.01;
  document.getElementById('eq-check').innerHTML = ok
    ? `<div class="fc-check ok"><i class="fas fa-check-circle"></i> Closing equity agrees with the Balance Sheet at ${fnEsc(d.to)} (${fnMoney(d.balance_sheet_equity)} RWF).</div>`
    : `<div class="fc-check bad"><i class="fas fa-exclamation-triangle"></i> Closing equity differs from the Balance Sheet by ${fnMoney(d.difference)} RWF. Investigate before issuing the statements.</div>`;
}
eqLoad();
</script>

<?php elseif ($subModule == 'year-end'): ?>
<!-- ====== YEAR-END CLOSE ====== -->
<div class="fo-card">
  <h3><i class="fas fa-calendar-check"></i> Year-end close</h3>
  <p class="fc-note" style="margin:0 0 14px;">Closing a finished year posts one journal entry dated 31 December that moves the year's surplus or deficit into Retained Earnings, then locks all twelve months. Years must be closed in order. A closed year can be reopened only while no later year is closed.</p>
  <div class="fo-wrap"><table class="fo-tbl">
    <thead><tr><th>Year</th><th class="num">Surplus / (deficit)</th><th>Status</th><th></th></tr></thead>
    <tbody id="ye-body"><tr><td colspan="4" class="fo-empty">Loading...</td></tr></tbody>
  </table></div>
</div>
<script>
async function yeLoad(){
  const body = document.getElementById('ye-body');
  const j = await fcCall('year_end_status');
  if(!j.success){ body.innerHTML = `<tr><td colspan="4" class="fo-empty" style="color:#991b1b;">${fnEsc(j.message || 'Failed')}</td></tr>`; return; }
  if(!j.data.years.length){ body.innerHTML = '<tr><td colspan="4" class="fo-empty">No income or expense has been posted yet.</td></tr>'; return; }
  body.innerHTML = j.data.years.map(y => {
    const tag = y.closed ? '<span class="fo-tag ok">Closed</span>' : (y.finished ? '<span class="fo-tag warn">Ready to close</span>' : '<span class="fo-tag gray">In progress</span>');
    const act = y.closed ? (y.can_reopen ? `<button class="fo-btn danger sm fc-btn-press" onclick="yeAct(this,'year_end_reopen',${y.year})">Reopen</button>` : '')
              : (y.can_close ? `<button class="fo-btn sm fc-btn-press" onclick="yeAct(this,'year_end_close',${y.year})">Close year</button>` : '');
    return `<tr><td>${y.year}</td><td class="num">${fcAmt(y.surplus)}</td><td>${tag}</td><td class="num">${act}</td></tr>`;
  }).join('');
}
async function yeAct(btn, action, year){
  const msg = action === 'year_end_close'
    ? `Close ${year}? This locks every month of ${year} and moves its surplus to Retained Earnings.`
    : `Reopen ${year}? The closing entry is removed and the months of ${year} become open again.`;
  if(!confirm(msg)) return;
  btn.disabled = true;
  const j = await fcCall(action, {body:{year: year}});
  btn.disabled = false;
  alert(j.success ? (j.message || 'Done') : (j.message || 'Failed'));
  yeLoad();
}
yeLoad();
</script>

<?php endif; ?>
