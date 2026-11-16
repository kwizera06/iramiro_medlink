<?php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'invoices';
$isAdmin = userHasRole('admin') || userHasRole('administrator') || userHasRole('isadmin') || !empty($_SESSION['user_data']['is_admin']) || in_array(strtolower($_SESSION['user_data']['role'] ?? ''), ['admin', 'administrator', 'isadmin']);

// Month dropdown (Jan–Dec of current year) that fills the tab's date-from/date-to inputs
function invMonthSelect($prefix) {
    $y = date('Y');
    echo '<select id="' . $prefix . '-month" onchange="invPickMonth(this,\'' . $prefix . '\')" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">';
    echo '<option value="">— Month —</option>';
    for ($m = 1; $m <= 12; $m++) {
        echo '<option value="' . $y . '-' . sprintf('%02d', $m) . '">' . date('F', mktime(0, 0, 0, $m, 1)) . ' ' . $y . '</option>';
    }
    echo '</select>';
}
?>
<style>.inv-tab{padding:10px 18px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}.inv-tab.active{background:var(--primary-color);color:white;}</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div><h2 style="font-size:24px;font-weight:600;">Patient Invoicing</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Click a patient to view their invoices</p></div>
</div>
<div style="display:flex;gap:8px;margin-bottom:24px;">
  <button class="inv-tab <?php echo $subModule=='invoices'?'active':'';?>" onclick="location.href='invoicing.php?module=finance&sub=invoices&care='+invCare"><i class="fas fa-file-invoice-dollar"></i> Pending</button>
  <button class="inv-tab <?php echo $subModule=='partial'?'active':'';?>" onclick="location.href='invoicing.php?module=finance&sub=partial&care='+invCare" style="<?php echo $subModule=='partial'?'':'background:#faf5ff;color:#7c3aed;border:1px solid #e9d5ff;';?>"><i class="fas fa-adjust"></i> Partial</button>
  <button class="inv-tab <?php echo $subModule=='paid'?'active':'';?>" onclick="location.href='invoicing.php?module=finance&sub=paid&care='+invCare"><i class="fas fa-check-circle"></i> Paid</button>
</div>
<?php if (in_array($subModule, ['invoices', 'partial', 'paid'], true)): ?>
<style>
.inv-care{position:relative;display:inline-flex;background:var(--gray-100);border-radius:12px;padding:4px;margin-bottom:20px}
.inv-care button{position:relative;z-index:1;width:150px;padding:9px 0;border:none;background:none;font-size:13px;font-weight:600;color:var(--gray-600);cursor:pointer;border-radius:9px;transition:color 200ms}
.inv-care button.on{color:#fff}
.inv-care-pill{position:absolute;top:4px;left:4px;width:150px;height:calc(100% - 8px);border-radius:9px;background:var(--primary-color);box-shadow:0 2px 6px rgba(0,0,0,.18);transition:transform 220ms cubic-bezier(.2,0,0,1)}
.inv-care[data-care=hosp] .inv-care-pill{transform:translateX(150px)}
.inv-care-n{display:none;margin-left:4px;min-width:18px;padding:1px 6px;border-radius:9px;background:#ef4444;color:#fff;font-size:11px;font-weight:700}
@media (prefers-reduced-motion:reduce){.inv-care-pill,.inv-care button{transition:none}}
</style>
<div class="inv-care" id="inv-care" data-care="<?php echo (($_GET['care'] ?? '') === 'hosp') ? 'hosp' : 'opd'; ?>">
  <span class="inv-care-pill"></span>
  <button type="button" data-c="opd" onclick="invSetCare('opd')"><i class="fas fa-user"></i> OPD <span class="inv-care-n" id="inv-n-opd"></span></button>
  <button type="button" data-c="hosp" onclick="invSetCare('hosp')"><i class="fas fa-bed"></i> Hospitalization <span class="inv-care-n" id="inv-n-hosp"></span></button>
</div>
<?php endif; ?>

<script>
var invCare = <?php echo (($_GET['care'] ?? '') === 'hosp') ? "'hosp'" : "'opd'"; ?>;   // OPD | hosp: which invoices every list on this page shows
function invCareQS(){ return '&care=' + invCare; }
function invSetCare(c){
  if (c === invCare) return;
  invCare = c;
  const el = document.getElementById('inv-care'); el.dataset.care = c;
  el.querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.c === c));
  const u = new URL(location.href); u.searchParams.set('care', c); history.replaceState(null, '', u);   // survives refresh, no reload
  invMonthDots();
  ['invLoad', 'partialLoad', 'paidLoad'].forEach(f => { if (typeof window[f] === 'function' && document.getElementById({invLoad: 'inv-tbody', partialLoad: 'partial-tbody', paidLoad: 'paid-tbody'}[f])) window[f](); });
}
function invCareHdr(inv,cs){
  if(!cs.any||inv.care===cs.last) return '';
  cs.last=inv.care;
  const h=inv.care==='hosp';
  return `<div style="margin:0 0 10px;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:700;background:${h?'#fef3c7':'#e0f2fe'};color:${h?'#92400e':'#075985'};"><i class="fas fa-${h?'bed':'user'}"></i> ${h?'Hospitalization':'OPD'} invoices</div>`;
}
async function invCareBadges(){
  try {
    const r = await (await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing&action=care_counts')).json();
    const d = r.data || r;
    ['opd', 'hosp'].forEach(c => { const n = document.getElementById('inv-n-' + c); if (!n) return; n.textContent = d[c] || 0; n.style.display = d[c] > 0 ? 'inline-block' : 'none'; });
  } catch (e) {}
}
// Small red dot after months that still have unpaid invoices (current care type)
async function invMonthDots(){
  try {
    const r = await (await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing&action=care_months&care=' + invCare)).json();
    const set = new Set(r.data || []);
    document.querySelectorAll('select[id$="-month"] option[value]').forEach(o => {
      if (!o.value) return;
      o.textContent = o.textContent.replace(/ ●$/, '') + (set.has(o.value) ? ' ●' : '');
      o.style.color = set.has(o.value) ? '#dc2626' : '';
    });
  } catch (e) {}
}
function invRefreshMarks(){ invCareBadges(); invMonthDots(); }
document.addEventListener('DOMContentLoaded', () => { const el = document.getElementById('inv-care'); if (el) { el.querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.c === invCare)); } invRefreshMarks(); });
window.invIsAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
window.invGlobalIsAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
var invIsAdmin = window.invIsAdmin;
var invGlobalIsAdmin = window.invGlobalIsAdmin;
window.globalBillAPI = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing';
var globalBillAPI = window.globalBillAPI;

// Month dropdown → set date range, then fire the date input's own change handler to reload
function invPickMonth(sel, prefix){
  if(!sel.value) return;
  const [y,m]=sel.value.split('-').map(Number);
  const last=new Date(y,m,0).getDate();
  document.getElementById(prefix+'-date-from').value=sel.value+'-01';
  const to=document.getElementById(prefix+'-date-to');
  to.value=sel.value+'-'+String(last).padStart(2,'0');
  to.dispatchEvent(new Event('change'));
}
// Default for every From/To filter on all tabs: today, local date (toISOString would give the UTC day)
function invToday(f,t){const d=new Date().toLocaleDateString('en-CA');document.getElementById(f).value=d;document.getElementById(t).value=d;}

// ---- ? button: where the money of a line comes from (insurance or not, which insurer, who pays what) ----
const invSrcStore=[];
function invCtx(inv){return {no:inv.invoice_no, ptype:inv.payment_type, insId:inv.insurance_id, visitId:inv.visit_id, insurer:inv.company_name, copay:parseFloat(inv.copayment_percentage ?? 100)};}
function invSrcBtn(it, inv){
  invSrcStore.push([{...it, _ctx:invCtx(inv)}]);
  return ' <button type="button" title="Where does this money come from?" aria-label="Where does this money come from?" onclick="event.stopPropagation();invSrcOpen('+(invSrcStore.length-1)+')" style="background:none;border:none;cursor:pointer;color:#2563eb;padding:0 2px;font-size:13px;"><i class="fa-regular fa-circle-question"></i></button>';
}
function invSrcOpen(idx){
  const fm=n=>Math.floor((parseFloat(n)||0)+0.5+1e-9).toLocaleString();
  const lines=invSrcStore[idx]||[];
  const rows=lines.map(it=>{
    const c=it._ctx||{}, total=parseFloat(it.total)||0, ins=c.ptype==='insurance'||c.ptype==='mixed';
    const m=String(it.description||'').match(/Ins\. pays:\s*([\d,]+)\s*\|\s*Pt\. pays:\s*([\d,]+)/);
    const cp=isNaN(c.copay)?100:c.copay;
    const pat=!ins?total:m?parseFloat(m[2].replace(/,/g,'')):Math.floor(total*cp/100+0.5+1e-9);
    return '<tr style="border-bottom:1px solid #f3f4f6;"><td style="padding:8px 10px;font-family:monospace;">'+iEsc(c.no||'—')+'</td><td style="padding:8px 10px;">'+
      (ins?'<span style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;padding:2px 8px;font-weight:600;"><i class="fas fa-shield-alt"></i> Insurance — '+iEsc(c.insurer||'unknown insurer')+'</span>'
          :'<span style="background:#f3f4f6;color:#374151;border-radius:6px;padding:2px 8px;font-weight:600;"><i class="fas fa-user"></i> Non-insurance (patient pays)</span>')+
      '</td><td style="padding:8px 10px;text-align:right;">'+fm(total)+'</td><td style="padding:8px 10px;text-align:right;color:#166534;">'+(ins?fm(total-pat):'—')+'</td><td style="padding:8px 10px;text-align:right;color:#92400e;">'+fm(pat)+'</td></tr>';
  }).join('');
  const old=document.getElementById('inv-src-modal'); if(old) old.remove();
  const m=document.createElement('div');
  m.id='inv-src-modal';
  m.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:10000;display:flex;align-items:center;justify-content:center;padding:16px;';
  m.innerHTML='<div style="background:white;border-radius:12px;max-width:680px;width:100%;max-height:80vh;overflow:auto;box-shadow:0 10px 30px rgba(0,0,0,.25);">'+
    '<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #e5e7eb;"><div><div style="font-weight:700;font-size:14px;">Where this money comes from</div><div style="font-size:12px;color:#6b7280;">'+iEsc((lines[0]||{}).description||'')+'</div></div>'+
    '<button type="button" aria-label="Close" style="background:none;border:none;font-size:18px;cursor:pointer;color:#6b7280;"><i class="fas fa-times"></i></button></div>'+
    '<table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:#f9fafb;text-align:left;"><th style="padding:8px 10px;">Invoice</th><th style="padding:8px 10px;">Billed to</th><th style="padding:8px 10px;text-align:right;">Line total</th><th style="padding:8px 10px;text-align:right;">Insurer pays</th><th style="padding:8px 10px;text-align:right;">Patient pays</th></tr></thead><tbody>'+rows+'</tbody></table>'+
    '<div style="padding:12px 18px 16px;border-top:1px solid #e5e7eb;"><div style="font-weight:700;font-size:13px;margin-bottom:8px;"><i class="fas fa-tags"></i> System tariff</div><div id="inv-src-tariff" style="font-size:12px;color:#6b7280;">Loading…</div></div></div>';
  const close=()=>{m.remove();document.removeEventListener('keydown',onKey);};
  const onKey=e=>{if(e.key==='Escape')close();};
  m.addEventListener('mousedown',e=>{if(e.target===m)close();});
  m.querySelector('button').addEventListener('click',close);
  document.addEventListener('keydown',onKey);
  document.body.appendChild(m);
  Promise.all(lines.map(it=>{
    const c=it._ctx||{}, drug=/^(medication|pharmacy|drug)/i.test(it.category||'');
    const q=(!drug&&it.service_id)?'service_id='+it.service_id:it.item_id?'item_id='+it.item_id:'';
    if(!q) return Promise.resolve({it,none:true});
    return fetch(globalBillAPI.replace('patients/billing','service-catalog')+'&action=tariff_info&'+q+'&insurance_id='+(c.insId||0)+'&visit_id='+(c.visitId||0))
      .then(r=>r.json()).then(j=>({it,j})).catch(()=>({it,none:true}));
  })).then(res=>{
    const box=document.getElementById('inv-src-tariff'); if(!box) return;
    box.innerHTML=res.map(x=>{
      const c=x.it._ctx||{};
      if(x.none||!x.j.success) return '<div style="margin-bottom:8px;">'+iEsc(c.no||'')+': no catalog link for this line, so no tariff to show (price was typed in manually).</div>';
      const d=x.j.data;
      return '<div style="margin-bottom:12px;"><div style="color:#111827;font-weight:600;">'+iEsc(c.no||'')+' — '+iEsc(d.service)+'</div>'+
        '<div style="margin:2px 0 4px;">'+(d.insurer?'Insurer: '+iEsc(d.insurer)+' ('+iEsc(d.tariff_group||'no group')+' tariff)':'Self-pay tariff')+' · patient category: '+iEsc(d.tariff_category)+'</div>'+
        '<table style="width:100%;border-collapse:collapse;"><tbody>'+d.lines.map(l=>{
          const hit=d.applied_label===l.label;
          return '<tr style="border-bottom:1px solid #f3f4f6;'+(hit?'background:#f0fdf4;font-weight:700;color:#166534;':'')+'"><td style="padding:5px 8px;">'+iEsc(l.label)+(hit?' ✓ applied':'')+'</td><td style="padding:5px 8px;text-align:right;">'+(l.price?fm(l.price)+' RWF':'— not set')+'</td></tr>';
        }).join('')+'</tbody></table>'+
        '<div style="margin-top:4px;">System price used: <b>'+fm(d.applied)+' RWF</b> · invoiced unit price: <b>'+fm(x.it.unit_price)+' RWF</b>'+(Math.abs((d.applied||0)-(parseFloat(x.it.unit_price)||0))>0.5?' <span style="color:#b91c1c;">(differs from the system tariff)</span>':'')+'</div></div>';
    }).join('');
  });
}

// Re-price one invoice with the patient's current insurance and show what it was vs what it is now
async function invRecalc(visitId, patientId, invoiceId=0){
  if(!confirm("Recalculate "+(invoiceId?"this invoice":"all unpaid invoices of this visit")+" with the patient's current insurance?\nPrices, totals and the ledger entry will be updated.")) return;
  try{
    const r=await fetch(globalBillAPI+'&action=recalculate_visit',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({visit_id:visitId,patient_id:patientId,invoice_id:invoiceId||undefined})});
    const j=await r.json();
    if(!j.success){ alert('Error: '+(j.message||'Recalculation failed')); return; }
    const list=j.data.invoices||[]; if(!list.length){ alert(j.message); return; }
    const f=n=>Math.round(n).toLocaleString()+' RWF';
    const row=(l,a,b,c)=>`<tr><td style="padding:6px 8px;color:var(--gray-500);">${l}</td><td style="padding:6px 8px;text-align:right;">${a}</td><td style="padding:6px 8px;text-align:right;font-weight:700;color:${c||'inherit'};">${b}</td></tr>`;
    const card=d=>`<div style="margin-bottom:16px;"><div style="font-weight:700;color:var(--primary-color);margin-bottom:6px;">${iEsc(d.invoice_no)}</div>
      ${d.note?`<div style="padding:8px 10px;background:#fffbeb;border-radius:6px;margin-bottom:6px;">${iEsc(d.note)}</div>`:''}
      <table style="width:100%;border-collapse:collapse;"><thead><tr style="border-bottom:1px solid var(--gray-200);"><th></th><th style="padding:6px 8px;text-align:right;">Was</th><th style="padding:6px 8px;text-align:right;">Now</th></tr></thead><tbody>
        ${row('Insurance / payer',iEsc(d.payer_before),iEsc(d.payer_after),d.payer_before===d.payer_after?'':'#1d4ed8')}
        ${row('Invoice total',f(d.total_before),f(d.total_after))}
        ${row('Patient balance',f(d.balance_before),f(d.balance_after))}
      </tbody></table>
      ${d.items.length?`<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:8px;">${d.items.map(i=>`<tr style="border-top:1px solid var(--gray-100);"><td style="padding:5px 8px;">${iEsc(i.description)} ×${i.qty}</td><td style="padding:5px 8px;text-align:right;">${Math.round(i.price_before).toLocaleString()}</td><td style="padding:5px 8px;text-align:right;font-weight:600;">${Math.round(i.price_after).toLocaleString()}</td></tr>`).join('')}</table>`:'<div style="margin-top:8px;color:var(--gray-500);">No item prices changed.</div>'}</div>`;
    const m=document.createElement('div'); m.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;padding:16px;';
    m.innerHTML=`<div style="background:white;border-radius:12px;padding:24px;width:560px;max-width:100%;max-height:90vh;overflow:auto;font-size:13px;">
      <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;"><i class="fas fa-sync-alt" style="color:var(--primary-color);"></i> Recalculated</h3>${list.map(card).join('')}
      <div style="text-align:right;"><button id="inv-rc-ok" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;">Close</button></div></div>`;
    document.body.appendChild(m);
    m.querySelector('#inv-rc-ok').onclick=()=>{ m.remove(); location.reload(); };
  }catch(e){ alert('Request failed: '+e.message); }
}
// Manual date edit → month no longer applies
document.addEventListener('input',e=>{
  const m=/^(\w+)-date-(from|to)$/.exec(e.target.id||'');
  const s=m&&document.getElementById(m[1]+'-month');
  if(s) s.value='';
});

function iEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function calcAge(dateOfBirth){if(!dateOfBirth)return '—';const dob=new Date(dateOfBirth);if(Number.isNaN(dob.getTime()))return '—';const diff=Date.now()-dob.getTime();const years=Math.floor(diff/(365.25*24*60*60*1000));return years>=0?years+' yrs':'—';}

// Rounding rule: if balance is e.g. 0.20 it stays 0 unless above 0.50
function roundBalance(val){
  const n = parseFloat(val || 0);
  if (isNaN(n) || n <= 0) return 0;
  const floor = Math.floor(n);
  const frac = Math.round((n - floor) * 1000) / 1000;
  return frac > 0.50 ? Math.ceil(n) : floor;
}

// Calculate individual item breakdown (insurance share vs patient share - whole RWF)
function calcInvoiceItemShares(it, isIns, copayPct, hasExplicitOverrides){
  const qty = parseInt(it.quantity || 1);
  const unitPrice = roundBalance(parseFloat(it.unit_price || 0));
  const lineTotal = roundBalance(parseFloat(it.total !== undefined && it.total !== null ? it.total : (unitPrice * qty)));
  const insPrice = parseFloat(it.insurance_price || 0);
  const topup = parseFloat(it.patient_topup || 0);
  const applyIns = parseInt(it.apply_insurance || 0);
  const coverPct = Math.max(0, 100 - copayPct);

  let insShare = 0;
  let patShare = lineTotal;

  if (isIns) {
    if (applyIns > 0 && insPrice > 0) {
      // Item has explicit insurance price & topup
      patShare = roundBalance(((insPrice * (copayPct / 100)) * qty) + (topup * qty));
      insShare = Math.max(0, lineTotal - patShare);
    } else if (applyIns > 0) {
      // Explicitly marked as covered with regular unit price
      patShare = roundBalance(lineTotal * (copayPct / 100));
      insShare = Math.max(0, lineTotal - patShare);
    } else if (insPrice > 0) {
      // Insurance price specified
      patShare = roundBalance(((insPrice * (copayPct / 100)) * qty) + (topup * qty));
      insShare = Math.max(0, lineTotal - patShare);
    } else if (!hasExplicitOverrides) {
      // Standard insurance invoice without item-level custom flags: standard copay applies to all items
      patShare = roundBalance(lineTotal * (copayPct / 100));
      insShare = Math.max(0, lineTotal - patShare);
    } else {
      // In an invoice with custom override items, an item with apply_insurance=0 and insurance_price=0 is patient self-pay
      insShare = 0;
      patShare = lineTotal;
    }
  } else {
    insShare = 0;
    patShare = lineTotal;
  }

  return {
    qty,
    unitPrice,
    lineTotal,
    insShare: Math.round(insShare),
    patShare: Math.round(patShare),
    topup,
    insPrice,
    applyIns
  };
}

// Display/print only: drug lines with the same drug and unit price become one line (SUM qty, discount, total). DB rows are untouched.
function invMergeLines(items){
  const out = [], seen = {};
  (items || []).forEach(it => {
    if (!['medication', 'pharmacy', 'drug'].includes(String(it.category || '').toLowerCase())) { out.push(it); return; }
    const k = [it.item_id || it.service_id || it.description, parseFloat(it.unit_price || 0), parseFloat(it.insurance_price || 0), parseFloat(it.patient_topup || 0), parseInt(it.apply_insurance || 0)].join('|');
    if (!(k in seen)) { seen[k] = out.length; out.push({...it}); return; }
    const m = out[seen[k]];
    m.quantity = parseFloat(m.quantity || 0) + parseFloat(it.quantity || 0);
    m.discount = parseFloat(m.discount || 0) + parseFloat(it.discount || 0);
    m.total = parseFloat(m.total || 0) + parseFloat(it.total || 0);
  });
  return out;
}

// Calculate patient share consistently using calcInvoiceItemShares or total
function calculatePatientShare(inv){
  if (!inv) return 0;
  const rawComp = inv.company_name || '';
  const isPrivate = (inv.payment_type === 'self_pay' || inv.visit_payment_type === 'self_pay' || !rawComp || rawComp.toLowerCase().includes('prive') || rawComp.toLowerCase().includes('private'));
  const isIns = !isPrivate && (inv.payment_type === 'insurance' || inv.payment_type === 'mixed' || rawComp !== '');
  const copayPct = parseFloat(inv.copayment_percentage ?? (isIns ? 15 : 100));
  const total = roundBalance(parseFloat(inv.total || 0));

  const items = inv._items || inv.items || [];
  if (!items.length) {
    // Server computes patient_share from the items (copay + top-ups) — prefer it over a total-based guess
    if (inv.patient_share !== undefined && inv.patient_share !== null) return roundBalance(parseFloat(inv.patient_share));
    return isIns ? roundBalance(total * (copayPct / 100)) : total;
  }

  const hasExplicit = items.some(item => 
    parseInt(item.apply_insurance || 0) > 0 || parseFloat(item.insurance_price || 0) > 0
  );

  let patSum = 0;
  items.forEach(it => {
    const shares = calcInvoiceItemShares(it, isIns, copayPct, hasExplicit);
    patSum += shares.patShare;
  });

  return roundBalance(patSum);
}

// Cash the patient paid beyond their share (e.g. a line was removed after payment) → refund due
function invSurplusRow(inv){
  const paid=(inv.payment_records||[]).filter(r=>r.status==='completed').reduce((s,r)=>s+parseFloat(r.amount_paid||0),0);
  const surplus=Math.round(paid-calculatePatientShare(inv));
  return surplus>1?`<div style="display:flex;justify-content:space-between;gap:16px;margin-top:2px;"><span style="font-weight:700;color:#7c3aed;">Surplus — refund to patient:</span><span style="font-weight:700;color:#7c3aed;">${surplus.toLocaleString()} RWF</span></div>`:'';
}

// Generate printable HTML for Proforma Invoice
function generateProformaInvoiceHTML(visit, items, grandTotal, grandPatientShare, grandInsShare, grandPaid, grandPatientDue, hospital, isSingleInvoice = false) {
  const hs = hospital || {};
  const patientName = iEsc(visit.patient_name || (visit.first_name ? (visit.first_name + ' ' + (visit.last_name||'')) : '—'));
  const today = new Date().toISOString().split('T')[0];
  const hasIns = (visit.insurance && visit.insurance.toLowerCase() !== 'self-pay') || grandInsShare > 0;
  
  let copayPct = 100;
  if (visit.copayment_percentage !== undefined && visit.copayment_percentage !== null) {
    copayPct = parseFloat(visit.copayment_percentage);
  } else if (visit.invoices && visit.invoices[0] && visit.invoices[0].copayment_percentage !== undefined && visit.invoices[0].copayment_percentage !== null) {
    copayPct = parseFloat(visit.invoices[0].copayment_percentage);
  } else if (hasIns) {
    copayPct = 15;
  }
  const coverPct = Math.max(0, 100 - copayPct);

  // Document settings from hospital config
  const docLogo = hs.doc_logo || hs.logo || '';
  const hLineColor = hs.header_line_color || '#0284c7';
  const hLineSize = hs.header_line_size || '2';
  const hTextColor = hs.header_text_color || '#1f2937';
  const hFontSize = hs.header_font_size || '11';
  const hLeftText = hs.header_left || hs.hospital_name || 'Hospital';
  const hRightText = (hs.header_right || 'Date: {date}').replace('{date}', visit.date || today).replace('{ref}', visit.visit_no || '');
  const fLineColor = hs.footer_line_color || '#0284c7';
  const fLineSize = hs.footer_line_size || '2';
  const fTextColor = hs.footer_text_color || '#1f2937';
  const fFontSize = hs.footer_font_size || '9';
  const fLeftText = hs.footer_left || (hs.address || 'Kigali, Rwanda');
  const fRightText = hs.footer_right || ((hs.phone ? 'Tel: ' + hs.phone + ' | ' : '') + 'Email: ' + (hs.email || ''));

  // Watermark
  let wmHtml = `<div style="position:absolute;top:48%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);opacity:0.06;pointer-events:none;z-index:0;font-size:68px;color:#0284c7;font-weight:900;letter-spacing:8px;white-space:nowrap;">PROFORMA INVOICE</div>`;
  if(hs.watermark_enabled === '1'){
    const wmOpacity = (parseInt(hs.watermark_opacity)||15)/100;
    const wmRotation = hs.watermark_rotation || '-30';
    if(hs.watermark_type === 'image' && hs.watermark_image){
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;"><img src="${hs.watermark_image}" style="width:150px;height:auto;"></div>`;
    }
  }

  const hasExplicitOverrides = items && items.some(it => parseInt(it.apply_insurance || 0) > 0 || parseFloat(it.insurance_price || 0) > 0);

  const itemRows = (items && items.length > 0) ? invMergeLines(items).map((it, i) => {
    const shares = calcInvoiceItemShares(it, hasIns, copayPct, hasExplicitOverrides);
    const invNoBadge = it._invoice_no ? `<span style="font-size:9px;color:#64748b;font-family:monospace;margin-left:4px;">[${iEsc(it._invoice_no)}]</span>` : '';
    if (hasIns) {
      return `<tr style="page-break-inside:avoid;">
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:center;">${i+1}</td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;word-wrap:break-word;max-width:200px;">
          <div style="font-weight:600;color:#1e293b;">${iEsc(it.description)} ${invNoBadge}</div>
          ${it.service_code ? `<span style="font-size:10px;color:#64748b;font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
        </td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:center;"><span style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:10px;color:#475569;">${iEsc(it.category || '—')}</span></td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:center;font-weight:600;">${shares.qty}</td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:right;white-space:nowrap;">${shares.unitPrice.toLocaleString()}</td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:right;white-space:nowrap;font-weight:600;">${shares.lineTotal.toLocaleString()}</td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:right;white-space:nowrap;color:#059669;background:#f0fdf4;font-weight:600;">${shares.insShare.toLocaleString()}</td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:right;white-space:nowrap;color:#2563eb;background:#eff6ff;font-weight:600;">${Math.round(shares.patShare).toLocaleString()}</td>
      </tr>`;
    } else {
      return `<tr style="page-break-inside:avoid;">
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:center;">${i+1}</td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;word-wrap:break-word;max-width:260px;">
          <div style="font-weight:600;color:#1e293b;">${iEsc(it.description)} ${invNoBadge}</div>
          ${it.service_code ? `<span style="font-size:10px;color:#64748b;font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
        </td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:center;"><span style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:10px;color:#475569;">${iEsc(it.category || '—')}</span></td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:center;font-weight:600;">${shares.qty}</td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:right;white-space:nowrap;">${shares.unitPrice.toLocaleString()}</td>
        <td style="padding:6px 8px;border:1px solid #e2e8f0;text-align:right;white-space:nowrap;font-weight:600;">${shares.lineTotal.toLocaleString()}</td>
      </tr>`;
    }
  }).join('') : `<tr><td colspan="${hasIns ? 8 : 6}" style="padding:16px;text-align:center;color:#94a3b8;">No billable items found.</td></tr>`;

  return `<div style="font-family:Arial,sans-serif;font-size:12px;padding:24px;color:#333;width:100%;box-sizing:border-box;position:relative;overflow:hidden;min-height:1060px;background:white;">
    ${wmHtml}
    <div style="position:relative;z-index:1;min-height:1020px;display:flex;flex-direction:column;">
      <!-- Header -->
      <img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
      <div style="display:flex;justify-content:flex-end;margin:4px 0 14px;">
        <div style="font-size:${hFontSize}px;color:${hTextColor};text-align:right;">
          <div style="font-weight:700;color:#0284c7;font-size:13px;">OFFICIAL PROFORMA</div>
          <div style="font-size:11px;color:#64748b;margin-top:2px;">${iEsc(hRightText)}</div>
        </div>
      </div>

      <!-- Title Banner -->
      <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <div>
          <div style="font-size:18px;font-weight:900;color:#0369a1;letter-spacing:0.5px;">PROFORMA INVOICE</div>
          <div style="font-size:11px;color:#0284c7;margin-top:2px;">Pre-Billing Service Quotation &amp; Due Statement</div>
        </div>
        <div style="text-align:right;">
          <span style="padding:4px 12px;border-radius:12px;font-size:11px;font-weight:800;background:#fef3c7;color:#92400e;border:1px solid #fde68a;">
            <i class="fas fa-clock"></i> PENDING PAYMENT
          </span>
        </div>
      </div>

      <!-- Patient & Visit Metadata -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;">
        <div>
          <div style="margin-bottom:4px;"><strong>Patient Name:</strong> <span style="font-weight:700;color:#0f172a;font-size:13px;">${patientName}</span></div>
          <div style="margin-bottom:4px;"><strong>Patient No:</strong> <span style="font-family:monospace;font-weight:600;color:#0369a1;">${iEsc(visit.patient_no || '—')}</span></div>
          <div style="margin-bottom:4px;"><strong>Age:</strong> ${iEsc(visit.age || '—')}</div>
          <div><strong>Created By:</strong> ${iEsc(visit.created_by_name || visit.created_by || '—')}</div>
        </div>
        <div style="text-align:right;">
          <div style="margin-bottom:4px;"><strong>Visit No:</strong> <span style="font-family:monospace;font-weight:700;color:#0f172a;font-size:13px;">${iEsc(visit.visit_no || '—')}</span></div>
          <div style="margin-bottom:4px;"><strong>Visit Date:</strong> ${iEsc(visit.date || today)}</div>
          <div style="margin-bottom:4px;"><strong>Insurance / Scheme:</strong> <span style="font-weight:700;color:${hasIns ? '#1d4ed8' : '#334155'};">${iEsc(visit.insurance || 'Self-pay')}</span></div>
          <div><strong>Invoices Included:</strong> ${visit.invoices ? visit.invoices.length : 1}</div>
        </div>
      </div>

      <!-- Itemized Services Table -->
      <table style="width:100%;border-collapse:collapse;margin-bottom:14px;table-layout:fixed;font-size:11px;">
        <thead>
          <tr style="background:#f1f5f9;border-bottom:2px solid #cbd5e1;">
            <th style="padding:7px 8px;border:1px solid #cbd5e1;text-align:center;width:25px;">#</th>
            <th style="padding:7px 8px;border:1px solid #cbd5e1;text-align:left;">Service / Item Description</th>
            <th style="padding:7px 8px;border:1px solid #cbd5e1;text-align:center;width:75px;">Category</th>
            <th style="padding:7px 8px;border:1px solid #cbd5e1;text-align:center;width:35px;">Qty</th>
            <th style="padding:7px 8px;border:1px solid #cbd5e1;text-align:right;width:75px;">Unit Price</th>
            <th style="padding:7px 8px;border:1px solid #cbd5e1;text-align:right;width:80px;">Line Total</th>
            ${hasIns ? `<th style="padding:7px 8px;border:1px solid #cbd5e1;text-align:right;width:85px;color:#059669;background:#f0fdf4;">Ins. (${coverPct}%)</th><th style="padding:7px 8px;border:1px solid #cbd5e1;text-align:right;width:85px;color:#2563eb;background:#eff6ff;">Patient (${copayPct}%)</th>` : ''}
          </tr>
        </thead>
        <tbody>${itemRows}</tbody>
      </table>

      <!-- Totals & Balance Due Box -->
      <div style="margin-top:4px;text-align:right;page-break-inside:avoid;">
        <table style="margin-left:auto;border-collapse:collapse;font-size:12px;">
          <tr>
            <td style="padding:4px 16px;text-align:right;color:#475569;"><strong>Grand Subtotal:</strong></td>
            <td style="padding:4px 16px;text-align:right;font-weight:700;font-size:13px;">${grandTotal.toLocaleString()} RWF</td>
          </tr>
          ${hasIns ? `
          <tr style="color:#059669;">
            <td style="padding:4px 16px;text-align:right;"><strong>Insurance Covers (${coverPct}%):</strong></td>
            <td style="padding:4px 16px;text-align:right;font-weight:700;">${grandInsShare.toLocaleString()} RWF</td>
          </tr>
          <tr style="color:#2563eb;">
            <td style="padding:4px 16px;text-align:right;"><strong>Patient Share (${copayPct}%):</strong></td>
            <td style="padding:4px 16px;text-align:right;font-weight:700;">${grandPatientShare.toLocaleString()} RWF</td>
          </tr>` : ''}
          ${grandPaid > 0 ? `
          <tr style="color:#16a34a;">
            <td style="padding:4px 16px;text-align:right;"><strong>Paid So Far:</strong></td>
            <td style="padding:4px 16px;text-align:right;font-weight:700;">${grandPaid.toLocaleString()} RWF</td>
          </tr>` : ''}
          <tr style="border-top:2px solid #0284c7;background:#f0f9ff;font-size:15px;">
            <td style="padding:8px 16px;text-align:right;color:#0369a1;"><strong>Total Amount Due:</strong></td>
            <td style="padding:8px 16px;text-align:right;font-weight:900;color:#0369a1;">${grandPatientDue.toLocaleString()} RWF</td>
          </tr>
        </table>
      </div>

      <!-- Informational Disclaimer -->
      <div style="margin-top:16px;padding:8px 14px;background:#fffbeb;border:1px solid #fef3c7;border-radius:6px;font-size:10px;color:#92400e;line-height:1.4;">
        <i class="fas fa-info-circle"></i> <strong>Proforma Notice:</strong> This is a formal proforma invoice / quotation for medical services rendered or prescribed. Please present this document at the cashier / billing counter to complete payment.
      </div>

      <!-- Signatures & Stamp -->
      <div style="margin-top:40px;page-break-inside:avoid;">
        <div style="display:flex;justify-content:space-between;gap:30px;">
          <div style="flex:1;text-align:center;">
            <div style="border-bottom:1px solid #475569;height:45px;margin-bottom:6px;"></div>
            <div style="font-size:11px;font-weight:600;color:#475569;">Prepared By (Cashier / Billing)</div>
          </div>
          <div style="flex:1;text-align:center;">
            <div style="border:1px dashed #94a3b8;height:45px;margin-bottom:6px;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:10px;">Official Stamp</div>
            <div style="font-size:11px;font-weight:600;color:#475569;">Hospital Stamp</div>
          </div>
          <div style="flex:1;text-align:center;">
            <div style="border-bottom:1px solid #475569;height:45px;margin-bottom:6px;"></div>
            <div style="font-size:11px;font-weight:600;color:#475569;">Patient / Client Signature</div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div style="margin-top:auto;padding-top:10px;border-top:${fLineSize}px solid ${fLineColor};display:flex;justify-content:space-between;align-items:center;font-size:${fFontSize}px;color:${fTextColor};">
        <span>${iEsc(fLeftText)}</span>
        <span>${iEsc(fRightText)}</span>
      </div>
    </div>
  </div>`;
}

// Show proforma preview modal
function invShowPrintPreviewModal(html, title, docTitle) {
  let modal = document.getElementById('inv-proforma-preview-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'inv-proforma-preview-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.65);z-index:99999;overflow-y:auto;padding:30px 20px;display:flex;align-items:flex-start;justify-content:center;';
    modal.innerHTML = `
      <div style="width:100%;max-width:880px;margin:0 auto;position:relative;">
        <div style="background:#0f172a;color:white;padding:12px 20px;border-radius:10px 10px 0 0;display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;" id="inv-proforma-preview-title">
            <i class="fas fa-file-invoice" style="color:#38bdf8;"></i> Proforma Invoice Preview
          </span>
          <div style="display:flex;gap:8px;">
            <button id="inv-proforma-print-btn" style="padding:8px 18px;background:#0284c7;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;"><i class="fas fa-print"></i> Print / Save PDF</button>
            <button onclick="document.getElementById('inv-proforma-preview-modal').style.display='none'" style="padding:8px 14px;background:#475569;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;">✕ Close</button>
          </div>
        </div>
        <div id="inv-proforma-preview-body" style="background:white;border-radius:0 0 10px 10px;box-shadow:0 10px 40px rgba(0,0,0,.3);overflow:hidden;"></div>
      </div>
    `;
    document.body.appendChild(modal);
  }

  document.getElementById('inv-proforma-preview-title').innerHTML = `<i class="fas fa-file-invoice" style="color:#38bdf8;"></i> ${iEsc(title || 'Proforma Invoice Preview')}`;
  document.getElementById('inv-proforma-preview-body').innerHTML = html;

  const printBtn = document.getElementById('inv-proforma-print-btn');
  printBtn.onclick = function() {
    const printContent = document.getElementById('inv-proforma-preview-body').innerHTML;
    const printWin = window.open('', '_blank', 'width=900,height=750');
    printWin.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>${iEsc(docTitle || 'Proforma Invoice')}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #333; background: white; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 6px 8px; border: 1px solid #ddd; }
    thead tr { background: #f0f0f0; }
    @media print {
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      @page { margin: 10mm; size: A4; }
    }
  </style>
</head>
<body>${printContent}</body>
</html>`);
    printWin.document.close();
    printWin.focus();
    setTimeout(() => {
      printWin.print();
      printWin.close();
    }, 500);
  };

  modal.style.display = 'flex';
}

const invGlobalBillAPI = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing';
invGlobalIsAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

let _editInvCurrentData = null;
let _editInvCompaniesList = [];
let _editInvServicesList = [];

async function invOpenEditModal(invoiceId) {
  if (!invGlobalIsAdmin) {
    alert('Access Denied: Only Administrators are authorized to edit invoices.');
    return;
  }

  let modal = document.getElementById('inv-edit-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'inv-edit-modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:99999;overflow-y:auto;padding:24px 16px;display:flex;align-items:flex-start;justify-content:center;';
    document.body.appendChild(modal);
  }

  modal.innerHTML = `
    <div style="background:white;border-radius:12px;width:100%;max-width:960px;margin:auto;box-shadow:0 20px 50px rgba(0,0,0,0.3);overflow:hidden;">
      <div style="padding:30px;text-align:center;color:var(--gray-500);font-size:14px;"><i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i> Loading invoice details and pricing catalogs...</div>
    </div>
  `;
  modal.style.display = 'flex';

  try {
    const rootPath = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>';
    const [rInv, rComp, rServ] = await Promise.all([
      fetch(invGlobalBillAPI + '&id=' + invoiceId).then(r => r.json()),
      fetch(rootPath + '/api.php?endpoint=insurance/companies').then(r => r.json()).catch(() => ({ data: [] })),
      fetch(rootPath + '/api.php?endpoint=settings/service-catalog&limit=500').then(r => r.json()).catch(() => ({ data: [] }))
    ]);

    if (!rInv.success || !rInv.data) {
      alert('Failed to load invoice: ' + (rInv.message || 'Not found'));
      modal.style.display = 'none';
      return;
    }

    _editInvCurrentData = rInv.data;
    const compData = rComp.data || [];
    _editInvCompaniesList = Array.isArray(compData) ? compData : (compData.companies || []);
    const servData = rServ.data || [];
    _editInvServicesList = Array.isArray(servData) ? servData : (servData.services || []);

    invRenderEditModalContent();
  } catch(e) {
    alert('Error loading invoice: ' + e.message);
    modal.style.display = 'none';
  }
}

function invRenderEditModalContent() {
  const inv = _editInvCurrentData;
  const modal = document.getElementById('inv-edit-modal');
  if (!inv || !modal) return;

  const isIns = (inv.payment_type || '').toLowerCase() === 'insurance' || (inv.payment_type || '').toLowerCase() === 'mixed';
  const copayPct = parseFloat(inv.copayment_percentage ?? (isIns ? 15 : 100));
  const patientName = iEsc((inv.first_name || '') + ' ' + (inv.last_name || ''));

  let compOpts = '<option value="">-- Select Insurance Provider --</option>';
  if (Array.isArray(_editInvCompaniesList)) {
    _editInvCompaniesList.forEach(c => {
      const sel = (String(c.id) === String(inv.insurance_id || '')) ? 'selected' : '';
      compOpts += `<option value="${c.id}" ${sel}>${iEsc(c.name)}</option>`;
    });
  }

  const categories = ['consultation', 'laboratory', 'pharmacy', 'nursing', 'radiology', 'procedure', 'ambulance', 'other'];

  const items = inv.items || [];
  let itemsRowsHtml = '';
  items.forEach((it, idx) => {
    itemsRowsHtml += invBuildEditItemRowHtml(it, idx, categories);
  });

  modal.innerHTML = `
    <div style="background:white;border-radius:12px;width:100%;max-width:980px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,0.35);overflow:hidden;">
      <!-- Header -->
      <div style="background:linear-gradient(135deg, #1e293b, #0f172a);color:white;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;">
        <div>
          <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:17px;font-weight:700;"><i class="fas fa-tag" style="color:#f59e0b;"></i> Edit Invoice Pricing — ${iEsc(inv.invoice_no)}</span>
            <span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;"><i class="fas fa-user-shield"></i> Administrator / isAdmin Only</span>
          </div>
          <div style="font-size:12px;color:#94a3b8;margin-top:4px;display:flex;gap:14px;flex-wrap:wrap;">
            <span>Patient: <strong style="color:white;">${patientName}</strong> (${iEsc(inv.patient_no || '—')})</span>
            <span>Visit: <strong style="color:white;">${iEsc(inv.visit_no || '—')}</strong></span>
            <span>Status: <strong style="color:#38bdf8;">${(inv.status||'').toUpperCase()}</strong></span>
            <span><i class="fas fa-user-edit" style="color:#f59e0b;"></i> Created by: <strong style="color:#fef3c7;">${iEsc(inv.created_by_name || 'Staff / System')}</strong>${inv.created_at ? ' (' + iEsc(inv.created_at.substring(0,16)) + ')' : ''}</span>
          </div>
        </div>
        <button onclick="document.getElementById('inv-edit-modal').style.display='none'" style="background:none;border:none;color:#94a3b8;font-size:24px;cursor:pointer;line-height:1;" title="Close">&times;</button>
      </div>

      <!-- Body -->
      <div style="padding:20px 24px;max-height:78vh;overflow-y:auto;background:#f8fafc;">
        <!-- Price Only Notice Banner -->
        <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-bottom:16px;color:#92400e;font-size:12px;display:flex;align-items:center;gap:10px;">
          <i class="fas fa-lock" style="font-size:16px;color:#d97706;flex-shrink:0;"></i>
          <div>
            <strong>Price-Only Edit Mode:</strong> Only item unit prices, insurance prices, and patient topups can be edited by an Administrator. Item names, quantities, and billing schemes are locked.
          </div>
        </div>

        <!-- Items Table -->
        <div style="background:white;padding:16px;border-radius:8px;border:1px solid var(--gray-200);margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <div style="font-size:13px;font-weight:700;color:var(--gray-800);display:flex;align-items:center;gap:6px;">
              <i class="fas fa-list" style="color:var(--primary-color);"></i> Invoice Items &amp; Unit Pricing (Editable by Price)
            </div>
            <span style="font-size:11px;color:var(--gray-500);font-style:italic;">
              <i class="fas fa-info-circle"></i> Item quantities &amp; descriptions are fixed
            </span>
          </div>

          <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
              <thead>
                <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                  <th style="padding:8px 8px;text-align:left;min-width:180px;">Item / Service Description</th>
                  <th style="padding:8px 8px;text-align:center;width:100px;">Category</th>
                  <th style="padding:8px 8px;text-align:center;width:60px;">Qty</th>
                  <th style="padding:8px 8px;text-align:right;width:120px;color:#0284c7;">Unit Price (RWF) *</th>
                  ${isIns ? `<th style="padding:8px 8px;text-align:center;width:80px;">Insurance</th>` : ''}
                  ${isIns ? `<th style="padding:8px 8px;text-align:right;width:110px;">Ins Price (RWF)</th>` : ''}
                  ${isIns ? `<th style="padding:8px 8px;text-align:right;width:100px;">Topup (RWF)</th>` : ''}
                  <th style="padding:8px 8px;text-align:right;width:110px;">Line Total</th>
                  <th style="padding:8px 8px;text-align:center;width:30px;"></th>
                </tr>
              </thead>
              <tbody id="edit-inv-items-tbody">
                ${itemsRowsHtml}
              </tbody>
            </table>
          </div>
        </div>

        <!-- Financial Breakdown Card -->
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px 18px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div>
            <div style="font-size:11px;color:#1e40af;font-weight:700;text-transform:uppercase;">Live Calculated Breakdown</div>
            <div style="font-size:12px;color:#3b82f6;margin-top:2px;">Dynamic sync of insurance vs patient shares &amp; journal entry</div>
          </div>
          <div style="display:flex;gap:20px;flex-wrap:wrap;text-align:right;">
            <div>
              <div style="font-size:10px;color:#64748b;">Subtotal</div>
              <div style="font-size:14px;font-weight:700;color:#1e293b;" id="edit-inv-calc-subtotal">0 RWF</div>
            </div>
            ${isIns ? `
            <div>
              <div style="font-size:10px;color:#059669;">Insurance Share</div>
              <div style="font-size:14px;font-weight:700;color:#059669;" id="edit-inv-calc-insshare">0 RWF</div>
            </div>
            <div>
              <div style="font-size:10px;color:#2563eb;">Patient Share</div>
              <div style="font-size:14px;font-weight:700;color:#2563eb;" id="edit-inv-calc-patshare">0 RWF</div>
            </div>
            ` : ''}
            <div>
              <div style="font-size:10px;color:#dc2626;">Net Grand Total</div>
              <div style="font-size:16px;font-weight:800;color:#dc2626;" id="edit-inv-calc-grandtotal">0 RWF</div>
            </div>
          </div>
        </div>

        <!-- Mandatory Audit Reason -->
        <div style="background:#fffbeb;border:1px solid #fef3c7;border-radius:8px;padding:14px 18px;">
          <label style="font-size:12px;font-weight:800;color:#92400e;display:flex;align-items:center;gap:6px;margin-bottom:6px;">
            <i class="fas fa-shield-alt"></i> Reason for Price Modification (Mandatory Audit Trail) <span style="color:#dc2626;">*</span>
          </label>
          <textarea id="edit-inv-reason" rows="2" placeholder="State specifically why prices are being adjusted (e.g. Approved fee adjustment, price correction per hospital policy)..." style="width:100%;padding:8px 12px;border:1px solid #d97706;border-radius:6px;font-size:12px;background:white;" required></textarea>
        </div>
      </div>

      <!-- Footer Action Buttons -->
      <div style="background:white;padding:14px 24px;border-top:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
        <button onclick="document.getElementById('inv-edit-modal').style.display='none'" style="padding:9px 18px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300);border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
          Cancel
        </button>
        <button onclick="invSaveEditedInvoice('${inv.id}')" id="edit-inv-save-btn" style="padding:10px 24px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-save"></i> Save Price Changes &amp; Update Journal Entry
        </button>
      </div>
    </div>
  `;

  invRecalcEditModalTotals();
}

function invBuildEditItemRowHtml(it, idx, categories) {
  const qty = parseInt(it.quantity || 1);
  const price = parseFloat(it.unit_price || 0);
  const disc = parseFloat(it.discount || 0);
  const insPrice = parseFloat(it.insurance_price || 0);
  const topup = parseFloat(it.patient_topup || 0);
  const applyIns = parseInt(it.apply_insurance || 0) > 0;
  const lineTotal = Math.max(0, (qty * price) - disc);
  const isIns = (_editInvCurrentData?.payment_type || '').toLowerCase() === 'insurance' || (_editInvCurrentData?.payment_type || '').toLowerCase() === 'mixed';

  return `
    <tr class="edit-inv-row" style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:8px 8px;">
        <div style="font-weight:600;color:var(--gray-900);">${iEsc(it.description || 'Service')}</div>
        ${it.service_code ? `<span style="font-size:10px;color:var(--gray-400);font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
        <input type="hidden" class="edit-it-id" value="${it.id || ''}">
        <input type="hidden" class="edit-it-desc" value="${iEsc(it.description || '')}">
        <input type="hidden" class="edit-it-service-id" value="${it.service_id || ''}">
      </td>
      <td style="padding:8px 8px;text-align:center;">
        <span style="padding:2px 8px;border-radius:6px;font-size:11px;background:#f3e8ff;color:#6b21a8;font-weight:600;text-transform:capitalize;">${iEsc(it.category || 'service')}</span>
        <input type="hidden" class="edit-it-cat" value="${iEsc(it.category || 'consultation')}">
      </td>
      <td style="padding:8px 8px;text-align:center;">
        <span style="font-weight:700;color:var(--gray-800);font-size:13px;">${qty}</span>
        <input type="hidden" class="edit-it-qty" value="${qty}">
      </td>
      <td style="padding:8px 8px;text-align:right;">
        <input type="number" class="edit-it-price" min="0" step="any" value="${price}" oninput="invRecalcEditModalTotals()" style="width:100%;padding:6px 8px;border:1.5px solid #0284c7;border-radius:6px;font-size:12px;font-weight:700;text-align:right;background:#f0f9ff;color:#0369a1;" required>
      </td>
      ${isIns ? `
      <td style="padding:8px 8px;text-align:center;">
        ${applyIns ? '<span style="color:#059669;font-weight:700;font-size:11px;"><i class="fas fa-check-circle"></i> Covered</span>' : '<span style="color:var(--gray-400);font-size:11px;">No</span>'}
        <input type="hidden" class="edit-it-apply-ins" value="${applyIns ? 1 : 0}">
      </td>
      <td style="padding:8px 8px;text-align:right;">
        <input type="number" class="edit-it-ins-price" min="0" step="any" value="${insPrice}" oninput="invRecalcEditModalTotals()" style="width:100%;padding:6px 6px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;text-align:right;" ${!applyIns ? 'disabled' : ''}>
      </td>
      <td style="padding:8px 8px;text-align:right;">
        <input type="number" class="edit-it-topup" min="0" step="any" value="${topup}" oninput="invRecalcEditModalTotals()" style="width:100%;padding:6px 6px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;text-align:right;" ${!applyIns ? 'disabled' : ''}>
      </td>
      ` : ''}
      <td style="padding:8px 8px;text-align:right;font-weight:700;color:var(--gray-900);">
        <span class="edit-it-linetotal">${lineTotal.toLocaleString()}</span>
      </td>
      <td style="padding:8px 8px;text-align:center;">
        <span style="color:var(--gray-400);font-size:11px;" title="Item is locked: price only editable"><i class="fas fa-lock"></i></span>
      </td>
    </tr>
  `;
}

function invRecalcEditModalTotals() {
  const rows = document.querySelectorAll('.edit-inv-row');
  const payType = (_editInvCurrentData?.payment_type || 'self_pay').toLowerCase();
  const isIns = payType === 'insurance' || payType === 'mixed';
  const copayPct = parseFloat(_editInvCurrentData?.copayment_percentage ?? (isIns ? 15 : 100));
  const discount = parseFloat(_editInvCurrentData?.discount || 0);
  const tax = parseFloat(_editInvCurrentData?.tax || 0);

  let subtotal = 0;
  let totalInsShare = 0;
  let totalPatShare = 0;

  rows.forEach(row => {
    const qty = Math.max(1, parseInt(row.querySelector('.edit-it-qty')?.value || 1));
    const price = Math.max(0, parseFloat(row.querySelector('.edit-it-price')?.value || 0));
    const applyIns = parseInt(row.querySelector('.edit-it-apply-ins')?.value || 0) > 0;
    const insPrice = Math.max(0, parseFloat(row.querySelector('.edit-it-ins-price')?.value || 0));
    const topup = Math.max(0, parseFloat(row.querySelector('.edit-it-topup')?.value || 0));
    const lineTotal = qty * price;

    const lineTotalEl = row.querySelector('.edit-it-linetotal');
    if (lineTotalEl) lineTotalEl.textContent = lineTotal.toLocaleString();

    subtotal += lineTotal;

    const shares = calcInvoiceItemShares({
      quantity: qty,
      unit_price: price,
      apply_insurance: applyIns ? 1 : 0,
      insurance_price: insPrice,
      patient_topup: topup,
      total: lineTotal
    }, isIns, copayPct, false);

    totalInsShare += shares.insShare;
    totalPatShare += shares.patShare;
  });

  const grandTotal = Math.max(0, subtotal - discount + tax);

  const subEl = document.getElementById('edit-inv-calc-subtotal');
  const insEl = document.getElementById('edit-inv-calc-insshare');
  const patEl = document.getElementById('edit-inv-calc-patshare');
  const totEl = document.getElementById('edit-inv-calc-grandtotal');

  if (subEl) subEl.textContent = subtotal.toLocaleString() + ' RWF';
  if (insEl) insEl.textContent = Math.round(totalInsShare).toLocaleString() + ' RWF';
  if (patEl) patEl.textContent = Math.round(totalPatShare).toLocaleString() + ' RWF';
  if (totEl) totEl.textContent = grandTotal.toLocaleString() + ' RWF';
}

async function invSaveEditedInvoice(invoiceId) {
  if (!invGlobalIsAdmin) {
    alert('Access Denied: Only Administrators or isAdmin are authorized to edit invoices.');
    return;
  }

  const reason = (document.getElementById('edit-inv-reason')?.value || '').trim();
  if (!reason) {
    alert('Please enter a mandatory Reason for this price adjustment (required for audit trail).');
    document.getElementById('edit-inv-reason')?.focus();
    return;
  }

  const rows = document.querySelectorAll('.edit-inv-row');
  if (!rows.length) {
    alert('Invoice must contain at least one item.');
    return;
  }

  const items = [];
  rows.forEach(row => {
    const itemId = row.querySelector('.edit-it-id')?.value || null;
    const desc = (row.querySelector('.edit-it-desc')?.value || '').trim();
    const serviceId = row.querySelector('.edit-it-service-id')?.value || null;
    const cat = row.querySelector('.edit-it-cat')?.value || 'consultation';
    const qty = parseInt(row.querySelector('.edit-it-qty')?.value || 1);
    const unitPrice = parseFloat(row.querySelector('.edit-it-price')?.value || 0);
    const applyIns = parseInt(row.querySelector('.edit-it-apply-ins')?.value || 0);
    const insPrice = parseFloat(row.querySelector('.edit-it-ins-price')?.value || 0);
    const topup = parseFloat(row.querySelector('.edit-it-topup')?.value || 0);

    items.push({
      id: itemId ? parseInt(itemId) : null,
      service_id: serviceId ? parseInt(serviceId) : null,
      description: desc,
      category: cat,
      quantity: qty,
      unit_price: unitPrice,
      apply_insurance: applyIns,
      insurance_price: insPrice,
      patient_topup: topup,
      discount: 0
    });
  });

  const saveBtn = document.getElementById('edit-inv-save-btn');
  if (saveBtn) {
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating Prices &amp; Journal Entry...';
  }

  try {
    const r = await fetch(invGlobalBillAPI + '&action=edit_invoice_full', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'edit_invoice_full',
        invoice_id: parseInt(invoiceId),
        visit_id: _editInvCurrentData?.visit_id ? parseInt(_editInvCurrentData.visit_id) : null,
        patient_id: _editInvCurrentData?.patient_id ? parseInt(_editInvCurrentData.patient_id) : null,
        edit_reason: reason,
        items: items
      })
    });

    const j = await r.json();
    if (j.success) {
      alert('✓ Invoice pricing updated successfully! Related accounting journal entry has been synchronized.');
      document.getElementById('inv-edit-modal').style.display = 'none';

      // Reload current view
      if (typeof invLoad === 'function' && document.getElementById('inv-visit-view')?.style.display !== 'none') {
        if (typeof invShowVisit === 'function' && _editInvCurrentData?.visit_id) {
          invShowVisit(_editInvCurrentData.visit_id);
        } else {
          invLoad();
        }
      } else if (typeof partialLoad === 'function' && document.getElementById('partial-visit-view')?.style.display !== 'none') {
        if (typeof partialShowVisit === 'function' && _editInvCurrentData?.visit_id) {
          partialShowVisit(_editInvCurrentData.visit_id);
        } else {
          partialLoad();
        }
      } else if (typeof paidLoad === 'function') {
        paidLoad();
      }
    } else {
      alert('Error updating invoice: ' + (j.message || 'Unknown error'));
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Price Changes &amp; Update Journal Entry';
      }
    }
  } catch(e) {
    alert('Failed to save invoice: ' + e.message);
    if (saveBtn) {
      saveBtn.disabled = false;
      saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Price Changes &amp; Update Journal Entry';
    }
  }
}

async function invOpenAuditLogModal(invoiceId) {
  let modal = document.getElementById('inv-audit-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'inv-audit-modal';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:99999;overflow-y:auto;padding:24px 16px;display:flex;align-items:flex-start;justify-content:center;';
    document.body.appendChild(modal);
  }

  modal.innerHTML = `
    <div style="background:white;border-radius:12px;width:100%;max-width:860px;margin:auto;box-shadow:0 20px 50px rgba(0,0,0,0.3);overflow:hidden;">
      <div style="padding:30px;text-align:center;color:var(--gray-500);font-size:14px;"><i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i> Loading audit trail...</div>
    </div>
  `;
  modal.style.display = 'flex';

  try {
    const r = await fetch(invGlobalBillAPI + '&action=invoice_logs&id=' + invoiceId);
    const j = await r.json();
    const logs = (j.success && j.data) ? j.data : [];

    let rowsHtml = '';
    if (!logs.length) {
      rowsHtml = `<tr><td colspan="4" style="padding:30px;text-align:center;color:var(--gray-400);font-size:13px;"><i class="fas fa-info-circle" style="margin-right:6px;"></i> No modifications recorded for this invoice. Original invoice as created.</td></tr>`;
    } else {
      rowsHtml = logs.map(lg => {
        const dateStr = lg.created_at ? lg.created_at.substring(0, 16) : '—';
        return `
          <tr style="border-bottom:1px solid var(--gray-100);">
            <td style="padding:10px 12px;font-size:12px;color:var(--gray-600);white-space:nowrap;">${dateStr}</td>
            <td style="padding:10px 12px;font-size:12px;font-weight:600;color:var(--gray-800);"><i class="fas fa-user-shield" style="color:#d97706;margin-right:4px;"></i>${iEsc(lg.editor_name || lg.user_name || 'Admin')}</td>
            <td style="padding:10px 12px;font-size:12px;color:#1e40af;font-weight:500;">${iEsc(lg.edit_summary || 'Invoice updated')}</td>
            <td style="padding:10px 12px;font-size:12px;color:#92400e;background:#fffbeb;border-radius:4px;"><em>${iEsc(lg.edit_reason || '—')}</em></td>
          </tr>
        `;
      }).join('');
    }

    modal.innerHTML = `
      <div style="background:white;border-radius:12px;width:100%;max-width:880px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,0.35);overflow:hidden;">
        <!-- Header -->
        <div style="background:#0f172a;color:white;padding:16px 24px;display:flex;justify-content:space-between;align-items:center;">
          <div>
            <div style="font-size:16px;font-weight:700;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-history" style="color:#38bdf8;"></i> Invoice Edit History &amp; Audit Trail
            </div>
            <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Complete chronological log of administrative edits, modifications, and reasons</div>
          </div>
          <button onclick="document.getElementById('inv-audit-modal').style.display='none'" style="background:none;border:none;color:#94a3b8;font-size:24px;cursor:pointer;line-height:1;" title="Close">&times;</button>
        </div>

        <!-- Body -->
        <div style="padding:20px;max-height:70vh;overflow-y:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                <th style="padding:8px 12px;text-align:left;color:var(--gray-700);width:140px;">Timestamp</th>
                <th style="padding:8px 12px;text-align:left;color:var(--gray-700);width:150px;">Edited By</th>
                <th style="padding:8px 12px;text-align:left;color:var(--gray-700);">Change Summary</th>
                <th style="padding:8px 12px;text-align:left;color:var(--gray-700);width:220px;">Reason</th>
              </tr>
            </thead>
            <tbody>${rowsHtml}</tbody>
          </table>
        </div>

        <!-- Footer -->
        <div style="background:var(--gray-50);padding:12px 20px;border-top:1px solid var(--gray-200);text-align:right;">
          <button onclick="document.getElementById('inv-audit-modal').style.display='none'" style="padding:8px 16px;background:var(--gray-700);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">Close</button>
        </div>
      </div>
    `;
  } catch(e) {
    alert('Failed to load audit logs: ' + e.message);
    modal.style.display = 'none';
  }
}

// Open Revert to Pending Modal (Admin only)
async function invOpenRevertModal(invoiceId, invoiceNo) {
  if (!invGlobalIsAdmin) {
    alert('Access Denied: Only Administrators are authorized to revert paid invoices.');
    return;
  }

  let modal = document.getElementById('inv-revert-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'inv-revert-modal';
    modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.55);display:flex;align-items:center;justify-content:center;z-index:99999;padding:20px;';
    document.body.appendChild(modal);
  }

  modal.style.display = 'flex';
  modal.innerHTML = `
    <div style="background:white;border-radius:12px;width:100%;max-width:560px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,0.35);overflow:hidden;">
      <div style="background:linear-gradient(135deg, #7f1d1d, #991b1b);color:white;padding:16px 22px;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:10px;">
          <i class="fas fa-undo" style="font-size:18px;color:#fca5a5;"></i>
          <span style="font-size:16px;font-weight:700;">Revert Invoice to Pending</span>
        </div>
        <button onclick="document.getElementById('inv-revert-modal').style.display='none'" style="background:none;border:none;color:#fca5a5;font-size:24px;cursor:pointer;line-height:1;" title="Close">&times;</button>
      </div>

      <div style="padding:16px 22px;background:#fef2f2;border-bottom:1px solid #fee2e2;">
        <div style="display:flex;align-items:flex-start;gap:12px;">
          <i class="fas fa-exclamation-triangle" style="font-size:22px;color:#dc2626;flex-shrink:0;margin-top:2px;"></i>
          <div style="font-size:13px;color:#991b1b;line-height:1.5;">
            <strong>Administrator Action:</strong> You are about to revert invoice <strong>${iEsc(invoiceNo || ('#' + invoiceId))}</strong> back to <strong>Pending (Issued)</strong> status.
            <ul style="margin:6px 0 0 16px;padding:0;font-size:12px;color:#7f1d1d;">
              <li>All associated <strong>payment records will be deleted</strong>.</li>
              <li>A balanced <strong>Reversal Journal Entry</strong> will be posted to the General Ledger with your reason.</li>
              <li>The invoice balance will be restored to the rounded patient share.</li>
            </ul>
          </div>
        </div>
      </div>

      <div style="padding:20px 22px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:6px;">
          Reason / Description for Reversion <span style="color:#dc2626;">*</span>
        </label>
        <textarea id="inv-revert-reason" rows="3" placeholder="Enter reason for reverting (e.g., Mistaken entry, patient changed payment method, refund)..." style="width:100%;padding:10px 12px;border:1px solid var(--gray-300);border-radius:8px;font-size:13px;box-sizing:border-box;resize:vertical;font-family:inherit;"></textarea>
        <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">This reason will be recorded on the Reversal Journal Entry and audit logs.</div>
      </div>

      <div style="background:var(--gray-50);padding:14px 22px;border-top:1px solid var(--gray-200);display:flex;justify-content:flex-end;gap:10px;">
        <button onclick="document.getElementById('inv-revert-modal').style.display='none'" style="padding:8px 16px;background:white;color:var(--gray-700);border:1px solid var(--gray-300);border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
        <button id="inv-revert-confirm-btn" onclick="invSubmitRevert(${invoiceId})" style="padding:8px 18px;background:#dc2626;color:white;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
          <i class="fas fa-undo"></i> Confirm &amp; Revert to Pending
        </button>
      </div>
    </div>
  `;
}

// Submit Revert
async function invSubmitRevert(invoiceId) {
  const reason = (document.getElementById('inv-revert-reason')?.value || '').trim();
  if (!reason) {
    alert('Please provide a reason / description for reverting this invoice.');
    document.getElementById('inv-revert-reason')?.focus();
    return;
  }

  const btn = document.getElementById('inv-revert-confirm-btn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reverting...';
  }

  try {
    const targetApi = window.globalBillAPI || (typeof billAPI !== 'undefined' ? billAPI : 'api.php?endpoint=patients/billing');
    const r = await fetch(targetApi + '&action=revert_payment', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ invoice_id: invoiceId, reason: reason })
    });
    const j = await r.json();

    if (!j.success) {
      alert('Error: ' + (j.message || 'Failed to revert invoice'));
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-undo"></i> Confirm &amp; Revert to Pending';
      }
      return;
    }

    alert('✓ ' + (j.message || 'Invoice reverted to pending successfully.'));
    const modal = document.getElementById('inv-revert-modal');
    if (modal) modal.style.display = 'none';

    // Go to Pending filtered by patient — the visit is usually outside Pending's default "today" date range
    location.href = 'invoicing.php?module=finance&sub=invoices&search=' + encodeURIComponent(j.data?.patient_no || '');
  } catch (err) {
    alert('Request failed: ' + err.message);
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-undo"></i> Confirm &amp; Revert to Pending';
    }
  }
}

// Pay All Outstanding (Pending tab) and Pay Outstanding Balance (Partial / Deferred tabs) share this one copy.
// It works on invCurrentVisitData, which each tab fills before calling invPayAll().
async function invPayAll(){
  if(!invCurrentVisitData||invCurrentVisitData.total<=0)return;
  if(invCurrentVisitData.unassigned>0){ alert('This visit has several insurances: assign an insurance to each of the '+invCurrentVisitData.unassigned+' invoice(s) before paying.'); return; }
  let cashOpts='<option value="1003">Petty Cash</option>';
  let expOpts='';
  try{
    const br=await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=finance/chart-of-accounts&action=cash_balances');
    const bj=await br.json();
    if(bj.success&&bj.data.accounts){
      const cashAccs=bj.data.accounts.filter(a=>a.account_group==='cash');
      // Loss accounts only (Medical Operation loss, Drug additional loss…) — where an unpaid debt goes if written off
      const medicalExpenseKeywords=['loss'];
      const expAccs=bj.data.accounts.filter(a=>{
        if(a.account_group!=='expense') return false;
        const name=(a.account_name||'').toLowerCase();
        return medicalExpenseKeywords.some(kw=>name.includes(kw));
      });
      if(cashAccs.length){cashOpts=`<optgroup label="Cash &amp; Cash Equivalents">`;cashOpts+=cashAccs.map(a=>`<option value="${a.id}">${iEsc(a.account_name)}</option>`).join('');cashOpts+=`</optgroup>`;}
      if(expAccs.length){expOpts=`<optgroup label="Expense / Write-off Accounts">`;expOpts+=expAccs.map(a=>`<option value="${a.id}">${iEsc(a.account_name)}</option>`).join('');expOpts+=`</optgroup>`;}
    }
  }catch(e){}
  window._invCashOpts=cashOpts;
  window._invExpOpts=expOpts;
  const totalDue=invCurrentVisitData.total;
  const modal=document.createElement('div');modal.id='inv-pay-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:500px;max-width:95vw;max-height:90vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;"><h3 style="font-size:16px;font-weight:600;"><i class="fas fa-money-bill" style="color:#16a34a;"></i> Record Payment</h3><button onclick="document.getElementById('inv-pay-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    <div style="background:var(--gray-50);border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;">
      <div><strong>Visit:</strong> ${iEsc(invCurrentVisitData.visit_no)} | <strong>Patient:</strong> ${iEsc(invCurrentVisitData.patient_name)}</div>
      <div style="margin-top:6px;font-size:15px;font-weight:700;color:#dc2626;">Total Due: ${totalDue.toLocaleString()} RWF</div>
      <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">${invCurrentVisitData.invoices.length} invoice(s)</div>
    </div>
    <div style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Amount to Pay <span style="color:#dc2626;">*</span></label>
      <div style="display:flex;gap:8px;align-items:center;">
        <input id="inv-pay-amount" type="number" min="1" step="1" value="${totalDue}" oninput="invUpdatePayBtn(${totalDue})" style="flex:1;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:14px;font-weight:600;">
        <button onclick="document.getElementById('inv-pay-amount').value=${totalDue};invUpdatePayBtn(${totalDue})" style="padding:9px 12px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:6px;font-size:11px;cursor:pointer;white-space:nowrap;">Full Amount</button>
      </div>
      <div id="inv-pay-amount-hint" style="font-size:11px;color:var(--gray-500);margin-top:4px;">Enter a partial amount to make a partial payment.</div>
    </div>
    <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Payment Method <span style="color:#dc2626;">*</span></label>
      <select id="inv-pay-method" onchange="invToggleBankField()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" required>
        <option value="">-- Select Payment Method --</option>
        <option value="cash">Cash</option>
        <option value="bank">Bank Transfer</option>
        <option value="mobile_money">Mobile Money</option>
        <option value="mtn_momo">MTN MoMo</option>
        <option value="insurance">Insurance</option>
        <option value="unpaid">Unpaid — Transfer to Finance (Debt)</option>
      </select>
    </div>
    <div id="inv-pay-bank-wrap" style="margin-bottom:12px;">
      <label id="inv-pay-bank-label" style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Receive Into (Cash / Bank Account) <span style="color:#dc2626;">*</span></label>
      <select id="inv-pay-bank" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" required>
        <option value="">-- Select Account --</option>${cashOpts}
      </select>
      <div id="inv-pay-bank-hint" style="font-size:11px;margin-top:4px;color:var(--gray-500);display:none;"></div>
    </div>
    <div id="inv-pay-unpaid-notice" style="display:none;margin-bottom:12px;padding:10px 14px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;font-size:12px;color:#6b21a8;">
      <i class="fas fa-clock" style="margin-right:6px;"></i><strong>Transfer to Finance:</strong> This amount becomes an outstanding debt in Finance. No cash is recorded and nothing is written off yet — if Finance writes it off, it goes to the loss account chosen above.
    </div>
    <div style="margin-bottom:16px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Reference (optional)</label><input id="inv-pay-ref" placeholder="Receipt #..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    <button id="inv-pay-btn" onclick="invPayAllConfirm(${totalDue})" style="width:100%;padding:12px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-check"></i> Confirm Payment — ${totalDue.toLocaleString()} RWF</button>
  </div>`;
  document.body.appendChild(modal);
}

function invToggleBankField(){
  const method=document.getElementById('inv-pay-method').value;
  const isUnpaid=(method==='unpaid');
  const bankSel=document.getElementById('inv-pay-bank');
  const bankLabel=document.getElementById('inv-pay-bank-label');
  const bankHint=document.getElementById('inv-pay-bank-hint');
  const notice=document.getElementById('inv-pay-unpaid-notice');
  const btn=document.getElementById('inv-pay-btn');
  if(isUnpaid){
    // Loss account the debt goes to if Finance writes it off — nothing is posted now
    document.getElementById('inv-pay-bank-wrap').style.display='';
    bankLabel.innerHTML='Loss Account (Medical Operation / Drug loss…) <span style="color:#dc2626;">*</span>';
    bankSel.innerHTML='<option value="">-- Select Loss Account --</option>'+(window._invExpOpts||'');
    notice&&(notice.style.display='block');
    if(btn) btn.style.background='#7c3aed';
  } else {
    document.getElementById('inv-pay-bank-wrap').style.display='';
    bankLabel.innerHTML='Receive Into (Cash / Bank Account) <span style="color:#dc2626;">*</span>';
    bankSel.innerHTML='<option value="">-- Select Account --</option>'+(window._invCashOpts||'');
    bankHint.style.display='none';
    notice&&(notice.style.display='none');
    if(btn) btn.style.background='#16a34a';
  }
  bankSel.value='';
}

async function invPayAllConfirm(totalDue){
  const bank=document.getElementById('inv-pay-bank').value;
  const ref=document.getElementById('inv-pay-ref').value;
  const payMethod=document.getElementById('inv-pay-method').value;
  const enteredAmount=parseFloat(document.getElementById('inv-pay-amount').value)||0;
  const isUnpaid=(payMethod==='unpaid');
  if(!payMethod) return alert('Please select a Payment Method.');
  if(!bank) return alert(isUnpaid ? 'Please select the loss account (e.g. Medical Operation loss / Drug additional loss).' : 'Please select an account to receive into.');
  if(enteredAmount<=0) return alert('Please enter a valid amount greater than 0.');
  const btn=document.getElementById('inv-pay-btn');
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
  let remaining=enteredAmount;
  let success=0,failed=0;
  for(let i = 0; i < invCurrentVisitData.invoices.length; i++){
    const inv = invCurrentVisitData.invoices[i];
    if(remaining<=0) break;
    const isLast = (i === invCurrentVisitData.invoices.length - 1);
    const patientShare=calculatePatientShare(inv);
    const payRecords=inv.payment_records||[];
    const alreadyPaid=payRecords.filter(r=>r.status==='completed'||r.status==='written_off').reduce((s,r)=>s+Math.round(parseFloat(r.amount_paid||0)),0);
    let patientDue=Math.max(0,patientShare-alreadyPaid);
    if(inv.status === 'paid' || patientDue<=1.05) continue;
    let payThis=Math.min(patientDue,remaining);
    if(isLast && remaining > 0 && Math.abs(patientDue - remaining) <= 1.05){
      payThis = patientDue;
    }
    try{
      const r=await fetch(billAPI+'&action=record_payment_full',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({invoice_id:inv.id,patient_amount:payThis,insurance_amount:0,bank_account_id:parseInt(bank),insurance_id:inv.insurance_id||0,reference:ref,payment_method:payMethod})});
      const j=await r.json();
      if(j.success){success++;remaining-=payThis;}else failed++;
    }catch(e){failed++;}
  }
  document.getElementById('inv-pay-modal').remove();
  invRefreshMarks();
  if(failed===0){
    if(isUnpaid) alert('✓ '+enteredAmount.toLocaleString()+' RWF transferred to Finance as outstanding debt ('+success+' invoice(s)).');
    else alert('✓ Payment of '+(enteredAmount-remaining).toLocaleString()+' RWF recorded across '+success+' invoice(s).'
      +(remaining>0?'\n\nSURPLUS — refund to patient: '+remaining.toLocaleString()+' RWF':'')
      +(enteredAmount<totalDue?'\n\nRemaining balance (debt): '+(totalDue-enteredAmount).toLocaleString()+' RWF':''));
    invLoad();
  } else {
    alert(success+' invoice(s) paid, '+failed+' failed. Please check and retry.');invLoad();
  }
}
</script>

<?php if ($subModule == 'invoices'): ?>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <!-- List Section -->
  <div id="inv-list-section">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-invoice-dollar" style="color:#d97706;"></i> Pending Invoices by Visit</h3>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <span style="font-size:12px;font-weight:600;color:var(--gray-600);">Visit date:</span>
        <?php invMonthSelect('inv'); ?>
        <input type="date" id="inv-date-from" onchange="invCurrentPage=1;invLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <span style="font-size:12px;color:var(--gray-400);">to</span>
        <input type="date" id="inv-date-to" onchange="invCurrentPage=1;invLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <input type="text" id="inv-search" placeholder="Search visit no, name..." oninput="invSearchDebounce()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;width:200px;">
        <button onclick="invLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
      </div>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:var(--gray-50);">
          <th style="padding:10px 12px;text-align:left;">#</th>
          <th style="padding:10px 12px;text-align:left;">Date</th>
          <th style="padding:10px 12px;text-align:left;">Patient</th>
          <th style="padding:10px 12px;text-align:left;">Patient No</th>
          <th style="padding:10px 12px;text-align:left;">Visit No</th>
          <th style="padding:10px 12px;text-align:left;">Age</th>
          <th style="padding:10px 12px;text-align:left;">Insurance</th>
          <th style="padding:10px 12px;text-align:center;">Invoices</th>
          <th style="padding:10px 12px;text-align:right;">Total Due</th>
          <th style="padding:10px 12px;text-align:left;">Created By</th>
          <th style="padding:10px 12px;text-align:center;">Action</th>
        </tr></thead>
        <tbody id="inv-tbody"><tr><td colspan="11" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
      </table>
    </div>
    <div id="inv-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;"></div>
  </div>

  <!-- Visit Invoice Detail (full page, hidden until click) -->
  <div id="inv-visit-view" style="display:none;">
    <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
      <button onclick="invBackToList()" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;"><i class="fas fa-arrow-left"></i> Back to List</button>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button id="inv-print-proforma-btn" onclick="invPrintProformaGrouped()" style="padding:10px 18px;background:#0284c7;color:white;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;"><i class="fas fa-print"></i> Print Proforma Invoice</button>
        <button id="inv-pay-all-btn" onclick="invPayAll()" style="padding:10px 20px;background:#16a34a;color:white;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;display:none;"><i class="fas fa-money-bill"></i> Pay All Outstanding</button>
      </div>
    </div>
    <!-- Visit Header -->
    <div style="background:linear-gradient(135deg,var(--primary-color),var(--secondary-color));border-radius:10px;padding:18px 24px;color:white;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <div style="width:46px;height:46px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;" id="inv-v-avatar">?</div>
      <div style="flex:1;min-width:180px;">
        <div style="font-size:17px;font-weight:700;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span id="inv-v-name">—</span>
          <span id="inv-v-badge" style="display:none;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:rgba(255,255,255,0.25);color:white;"></span>
        </div>
        <div style="font-size:12px;opacity:0.9;margin-top:4px;" id="inv-v-info">—</div>
      </div>
      <div style="display:flex;gap:20px;flex-wrap:wrap;text-align:right;">
        <div>
          <div style="font-size:11px;opacity:0.8;">Grand Total</div>
          <div style="font-size:18px;font-weight:700;" id="inv-v-grand-total">—</div>
        </div>
        <div id="inv-v-ins-block" style="display:none;">
          <div style="font-size:11px;opacity:0.8;" id="inv-v-ins-label">Insurance Covers</div>
          <div style="font-size:18px;font-weight:700;color:#a7f3d0;" id="inv-v-ins-total">—</div>
        </div>
        <div id="inv-v-copay-block" style="display:none;">
          <div style="font-size:11px;opacity:0.8;" id="inv-v-copay-label">Patient Share</div>
          <div style="font-size:18px;font-weight:700;color:#bfdbfe;" id="inv-v-copay-total">—</div>
        </div>
        <div>
          <div style="font-size:11px;opacity:0.8;">Total Outstanding</div>
          <div style="font-size:22px;font-weight:700;color:#fecaca;" id="inv-v-total">0 RWF</div>
        </div>
      </div>
    </div>
    <!-- Invoices with items inline -->
    <div id="inv-visit-invoices"></div>
  </div>
</div>

<script>
const billAPI='<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing';
invIsAdmin=<?php echo $isAdmin ? 'true' : 'false'; ?>;

let invAllVisits=[];
let invCurrentPage=1;
const invPageSize=20;
let invSearchTimer;

// Init dates to today or URL search
(function(){
  const urlParams = new URLSearchParams(window.location.search);
  const qSearch = urlParams.get('search');
  if (qSearch) {
    const searchInp = document.getElementById('inv-search');
    if (searchInp) searchInp.value = qSearch;
    document.getElementById('inv-date-from').value = '';
    document.getElementById('inv-date-to').value = '';
  } else {
    invToday('inv-date-from','inv-date-to');
  }
})();

function invSearchDebounce(){clearTimeout(invSearchTimer);invSearchTimer=setTimeout(()=>{invCurrentPage=1;invLoad();},300);}

async function invLoad(){
  const tbody=document.getElementById('inv-tbody');
  tbody.innerHTML='<tr><td colspan="10" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading visits...</td></tr>';
  const from=document.getElementById('inv-date-from').value;
  const to=document.getElementById('inv-date-to').value;
  const q=(document.getElementById('inv-search').value||'').trim();
  const base='<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing&action=visits_billing&inv_status=pending'+invCareQS();
  let url=base;
  if(from) url+='&date_from='+encodeURIComponent(from);
  if(to)   url+='&date_to='+encodeURIComponent(to);
  if(q)    url+='&search='+encodeURIComponent(q);
  try{
    const r=await fetch(url);
    const j=await r.json();
    invAllVisits=j.data||[];
    invRenderTable(invAllVisits);
  }catch(e){
    tbody.innerHTML='<tr><td colspan="10" style="padding:30px;text-align:center;color:#dc2626;">Error loading visits</td></tr>';
  }
  document.getElementById('inv-visit-view').style.display='none';
  document.getElementById('inv-list-section').style.display='block';
}

function invGoPage(p){invCurrentPage=p;invRenderTable(invAllVisits);}

function invRenderTable(list){
  const tbody=document.getElementById('inv-tbody');
  const pgEl=document.getElementById('inv-pagination');
  // Keep visits with at least one invoice not yet paid at all (e.g. reverted); paid-on invoices live in Partial/Deferred
  const filtered=list.filter(v=>parseFloat(v.pending_balance||0)>0||parseFloat(v.covered_amount||0)>0);
  if(!filtered.length){tbody.innerHTML='<tr><td colspan="11" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:20px;display:block;margin-bottom:8px;color:var(--success-color);"></i>No pending invoices found.</td></tr>';pgEl.innerHTML='';return;}
  const totalPages=Math.ceil(filtered.length/invPageSize);
  if(invCurrentPage>totalPages)invCurrentPage=totalPages;
  if(invCurrentPage<1)invCurrentPage=1;
  const start=(invCurrentPage-1)*invPageSize;
  const page=filtered.slice(start,start+invPageSize);

  tbody.innerHTML=page.map((v,i)=>{
    const name=iEsc((v.first_name||'')+' '+(v.last_name||''));
    const date=iEsc((v.visit_date||'').substring(0,10));
    const balance=parseFloat(v.pending_balance||0);
    const paid=0; // Pending rows only cover unpaid invoices
    const payType=(v.visit_payment_type||'self_pay').toLowerCase();
    const isIns=payType==='insurance'||payType==='mixed';
    const copayVal=(v.copayment_percentage !== undefined && v.copayment_percentage !== null) ? parseFloat(v.copayment_percentage) : null;
    let insurance = 'Self-pay';
    if (isIns) {
      const comp = v.company_name || 'Insurance';
      insurance = copayVal !== null ? `${comp} (${copayVal}% Copay)` : comp;
    }
    const age=calcAge(v.date_of_birth);
    return `<tr style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onclick="invShowVisit('${v.visit_id}')" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
      <td style="padding:10px 12px;color:var(--gray-500);font-size:12px;">${start+i+1}</td>
      <td style="padding:10px 12px;font-size:12px;">${date}</td>
      <td style="padding:10px 12px;font-weight:600;">${name}</td>
      <td style="padding:10px 12px;font-family:monospace;font-size:12px;color:var(--gray-500);">${iEsc(v.patient_no||'')}</td>
      <td style="padding:10px 12px;font-family:monospace;font-size:12px;color:var(--primary-color);font-weight:600;">${iEsc(v.visit_no||'')}</td>
      <td style="padding:10px 12px;font-size:12px;">${iEsc(age)}</td>
      <td style="padding:10px 12px;font-size:12px;">${isIns ? `<span style="padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;"><i class="fas fa-shield-alt" style="margin-right:3px;"></i>${iEsc(insurance)}</span>` : `<span style="padding:3px 8px;border-radius:12px;font-size:11px;background:#f3f4f6;color:#4b5563;">Self-pay</span>`}</td>
      <td style="padding:10px 12px;text-align:center;"><span style="background:var(--primary-light);color:var(--primary-color);padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;">${v.invoice_count||0}</span></td>
      <td style="padding:10px 12px;text-align:right;">
        <div style="font-weight:700;color:${balance>0?'#dc2626':'#16a34a'};">${balance.toLocaleString()} RWF</div>
        ${parseFloat(v.covered_amount||0)>0?`<div style="font-size:11px;color:#1e40af;margin-top:2px;"><i class="fas fa-shield-alt"></i> Insurance covers: ${parseFloat(v.covered_amount).toLocaleString()} RWF</div>`:''}
        ${paid>0?`<div style="font-size:11px;color:#16a34a;margin-top:2px;"><i class="fas fa-check-circle"></i> Paid: ${paid.toLocaleString()} RWF</div>`:''}
      </td>
      <td style="padding:10px 12px;font-size:12px;color:var(--gray-700);">${iEsc(v.created_by_name||'—')}</td>
      <td style="padding:10px 12px;text-align:center;white-space:nowrap;">
        <button onclick="event.stopPropagation();invPrintProformaForVisit('${v.visit_id}')" style="padding:5px 10px;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;margin-right:4px;" title="Print Proforma Invoice"><i class="fas fa-print"></i> Proforma</button>
        <button onclick="event.stopPropagation();invRecalc(${v.visit_id},${v.patient_id})" style="padding:5px 10px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;margin-right:4px;" title="Re-price this visit's unpaid invoices with the patient's current insurance"><i class="fas fa-sync-alt"></i> Recalculate</button>
        <button onclick="invShowVisit('${v.visit_id}')" style="padding:5px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:500;"><i class="fas fa-eye"></i> View</button>
      </td>
    </tr>`;
  }).join('');

  if(totalPages<=1){pgEl.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">${filtered.length} visit${filtered.length!==1?'s':''}</span><div></div>`;return;}
  let btns=`<button onclick="invGoPage(${invCurrentPage-1})" ${invCurrentPage===1?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${invCurrentPage===1?'var(--gray-100)':'white'};border-radius:6px;cursor:${invCurrentPage===1?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-left"></i></button>`;
  let sp=Math.max(1,invCurrentPage-3),ep=Math.min(totalPages,sp+6);if(ep-sp<6)sp=Math.max(1,ep-6);
  for(let p=sp;p<=ep;p++){const a=p===invCurrentPage;btns+=`<button onclick="invGoPage(${p})" style="padding:6px 10px;border:1px solid ${a?'var(--primary-color)':'var(--gray-200)'};background:${a?'var(--primary-color)':'white'};color:${a?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:12px;font-weight:${a?'600':'400'};">${p}</button>`;}
  btns+=`<button onclick="invGoPage(${invCurrentPage+1})" ${invCurrentPage===totalPages?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${invCurrentPage===totalPages?'var(--gray-100)':'white'};border-radius:6px;cursor:${invCurrentPage===totalPages?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-right"></i></button>`;
  pgEl.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">Page ${invCurrentPage} of ${totalPages} (${filtered.length} visits)</span><div style="display:flex;gap:4px;">${btns}</div>`;
}

function invBackToList(){
  document.getElementById('inv-visit-view').style.display='none';
  document.getElementById('inv-list-section').style.display='block';
}

let invCurrentVisitData=null;
// Visit with several insurers: every insured invoice must be assigned one before "Pay All Outstanding" works
async function invInsAssignPanel(visitId, invoices, container){
  return; // one insurance per visit: no per-invoice insurance choice
  const old=document.getElementById('inv-assign-panel'); if(old) old.remove();
  let insurers=[];
  try{ insurers=(await (await fetch(billAPI+'&action=visit_insurers&visit_id='+visitId)).json()).data||[]; }catch(e){}
  if(!insurers.length) return;
  const todo=invoices.filter(i=>(i.payment_type==='insurance'||i.payment_type==='mixed')&&parseFloat(i.total||0)>0&&parseInt(i.ins_assigned||0)!==1&&parseFloat(i.paid_amount||0)===0);
  invCurrentVisitData.unassigned=todo.length;
  const btn=document.getElementById('inv-pay-all-btn');
  if(btn&&todo.length){ btn.style.opacity='.5'; btn.title='Assign an insurance to every invoice first'; }
  const opts=insurers.map(r=>'<option value="'+r.pi_id+'">'+iEsc(r.company_name)+(r.package_name?' — '+iEsc(r.package_name):'')+'</option>').join('');
  const rows=invoices.filter(i=>(i.payment_type==='insurance'||i.payment_type==='mixed'||parseInt(i.ins_assigned||0)===1)&&parseFloat(i.total||0)>0).map(i=>{
    const done=parseInt(i.ins_assigned||0)===1, locked=parseFloat(i.paid_amount||0)>0;
    return '<div style="display:flex;align-items:center;gap:10px;padding:6px 0;font-size:13px;"><span style="min-width:150px;font-weight:600;">'+iEsc(i.invoice_no||('#'+i.id))+'</span>'
      +'<span style="min-width:90px;">'+Math.round(parseFloat(i.total)).toLocaleString()+' RWF</span>'
      +'<select id="inv-asg-'+i.id+'" '+(locked?'disabled ':'')+'style="flex:1;padding:6px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><option value="">— Select insurance —</option>'+opts+'<option value="0">Self pay (no insurance)</option></select>'
      +'<button '+(locked?'disabled ':'')+'onclick="invAssignIns('+i.id+','+visitId+')" style="padding:6px 12px;background:#1d4ed8;color:#fff;border:none;border-radius:6px;font-size:12px;cursor:pointer;">Assign</button>'
      +(done?'<i class="fas fa-check-circle" style="color:#16a34a;" title="Assigned"></i>':'<i class="fas fa-exclamation-circle" style="color:#d97706;" title="Not assigned"></i>')+'</div>';
  }).join('');
  container.insertAdjacentHTML('beforebegin','<div id="inv-assign-panel" style="margin-bottom:14px;padding:12px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;"><div style="font-weight:700;color:#1e40af;font-size:13px;margin-bottom:6px;"><i class="fas fa-shield-alt"></i> This visit has several insurances — choose which one covers each invoice'+(todo.length?' <span style="color:#b45309;">('+todo.length+' not assigned yet; Pay All is locked)</span>':'')+'</div>'+rows+'</div>');
  // preselect what is already assigned
  invoices.forEach(i=>{ const s=document.getElementById('inv-asg-'+i.id); if(s&&parseInt(i.ins_assigned||0)===1) s.value=i.payment_type==='self_pay'?'0':String(i.insurance_id); });
}
async function invAssignIns(invoiceId, visitId){
  const piId=document.getElementById('inv-asg-'+invoiceId).value;
  if(!piId){ alert('Select an insurance first'); return; }
  try{
    const j=await (await fetch(billAPI+'&action=assign_invoice_insurance',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({invoice_id:invoiceId,pi_id:parseInt(piId)})})).json();
    if(!j.success){ alert('Error: '+(j.message||'Failed')); return; }
    await invLoad(); invShowVisit(visitId);
  }catch(e){ alert('Failed: '+e.message); }
}
// Self-pay visit with unpaid invoices: if the patient has an active insurer, show a hint + "Apply insurance" button
async function invOfferInsurance(visitRow, invoices, badgeEl){
  try{
    const j=await (await fetch(billAPI+'&action=patient_insurers&patient_id='+visitRow.patient_id)).json();
    const insurers=(j.data||[]);
    if(!insurers.length) return;
    const old=document.getElementById('inv-ins-hint'); if(old) old.remove();
    badgeEl.insertAdjacentHTML('afterend','<span id="inv-ins-hint" style="display:inline-flex;align-items:center;gap:8px;margin-left:8px;padding:4px 10px;background:#fef3c7;color:#92400e;border:1px solid #f59e0b;border-radius:6px;font-size:12px;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Patient has active insurance ('+insurers.map(i=>iEsc(i.company_name)).join(', ')+') but this visit is self-pay <button type="button" style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;" id="inv-ins-apply">Apply insurance</button></span>');
    document.getElementById('inv-ins-apply').onclick=()=>invApplyInsurance(visitRow, invoices, insurers);
  }catch(e){}
}
async function invApplyInsurance(visitRow, invoices, insurers){
  let pick=insurers[0];
  if(insurers.length>1){
    const n=prompt('Choose insurer:\n'+insurers.map((i,k)=>(k+1)+'. '+i.company_name).join('\n'),'1');
    pick=insurers[(parseInt(n)||0)-1];
    if(!pick) return;
  }
  const lines=invoices.map(i=>'  - '+(i.invoice_no||('#'+i.id))+'  '+Math.round(parseFloat(i.total||0)).toLocaleString()+' RWF').join('\n');
  if(!confirm('Apply insurance for visit '+(visitRow.visit_no||'')+'?\n\nWill change:\n- Visit payment: self-pay -> '+pick.company_name+(pick.copayment_percentage!=null?' (patient co-pay '+pick.copayment_percentage+'%)':'')+'\n- Unpaid invoices re-priced with insurer prices:\n'+lines+'\n\nPaid invoices are not changed.')) return;
  try{
    const r=await fetch(billAPI+'&action=recalculate_visit',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({visit_id:visitRow.visit_id,patient_id:visitRow.patient_id,apply_company_id:pick.company_id})});
    const j=await r.json();
    alert(j.success?('Done. '+j.message):('Error: '+(j.message||'Failed')));
    if(j.success){ await invLoad(); invShowVisit(visitRow.visit_id); }
  }catch(e){ alert('Failed: '+e.message); }
}

async function invShowVisit(visitId){
  // Find visit row from the already-loaded list for header info
  const visitRow=invAllVisits.find(v=>String(v.visit_id)===String(visitId));
  if(!visitRow) return;

  document.getElementById('inv-list-section').style.display='none';
  document.getElementById('inv-visit-view').style.display='block';

  const patientName=(visitRow.first_name||'')+' '+(visitRow.last_name||'');
  document.getElementById('inv-v-avatar').textContent=(patientName||'?').charAt(0).toUpperCase();
  document.getElementById('inv-v-name').textContent=patientName;
  document.getElementById('inv-v-info').textContent='Patient: '+(visitRow.patient_no||'')+'  |  Visit: '+(visitRow.visit_no||'')+'  |  Date: '+((visitRow.visit_date||'').substring(0,10));
  document.getElementById('inv-v-total').textContent=parseFloat(visitRow.total_balance||0).toLocaleString()+' RWF';
  document.getElementById('inv-pay-all-btn').style.display=parseFloat(visitRow.total_balance||0)>0?'inline-flex':'none';

  // Fetch all invoices for this visit from the API
  const container=document.getElementById('inv-visit-invoices');
  container.innerHTML='<div style="text-align:center;padding:20px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading invoices...</div>';

  let invoices=[];
  try{
    const r=await fetch(billAPI+'&visit_id='+visitId+'&status=all');
    const j=await r.json();
    invoices=j.data||[];
  }catch(e){
    container.innerHTML='<div style="text-align:center;padding:20px;color:#dc2626;">Error loading invoices</div>';
    return;
  }

  // Load items for all invoices first (needed for accurate patient share calculation)
  for(const inv of invoices){
    try{
      const r=await fetch(billAPI+'&id='+inv.id);
      const j=await r.json();
      if(j.success&&j.data&&j.data.items) inv._items=j.data.items;
    }catch(e){
      inv._items=[];
    }
  }

  // Still owed on this visit's partly-paid invoices: they live in the Partial tab, so warn the cashier here
  const partlyPaidOwed = invoices.reduce((sum, inv)=>{
    if(!['issued','partial'].includes(inv.status)) return sum;
    const paid=(inv.payment_records||[]).filter(r=>r.status==='completed'||r.status==='written_off').reduce((s,r)=>s+parseFloat(r.amount_paid||0),0);
    return paid>0 ? sum+Math.max(0, calculatePatientShare(inv)-paid) : sum;
  }, 0);

  // Filter: pending = patient owes something AND has NOT made any payment yet
  // Partial = patient has paid something but still owes more → goes to Partial tab
  invoices=invoices.filter(inv=>{
    const coveredByIns = inv.status==='paid' && inv.payment_type==='insurance' && parseFloat(inv.paid_amount||0)===0;
    if(!['issued','partial','draft'].includes(inv.status) && !coveredByIns) return false;
    const payRecords=inv.payment_records||[];
    const alreadyPaid = payRecords
      .filter(r=>r.status==='completed'||r.status==='written_off')
      .reduce((s,r)=>s+parseFloat(r.amount_paid||0), 0);
    const patientShare=calculatePatientShare(inv);
    // Must owe something AND have paid nothing yet (partial payments → Partial tab, Finance debts / unpaid → Deferred tab)
    const isDeferred=parseFloat(inv.debt_amount||0)>0 || payRecords.some(r=>r.status==='pending');
    return (patientShare > 0 || coveredByIns) && alreadyPaid === 0 && !isDeferred;
  });

  const firstInv = invoices[0] || {};
  const rawCompName = firstInv.company_name || visitRow.company_name || '';
  const isPrivate = (visitRow.visit_payment_type === 'self_pay' || visitRow.payment_type === 'self_pay' || (firstInv.payment_type === 'self_pay') || !rawCompName || rawCompName.toLowerCase().includes('prive') || rawCompName.toLowerCase().includes('private'));
  const isIns = !isPrivate && rawCompName !== '';
  const copayPct = (firstInv.copayment_percentage !== undefined && firstInv.copayment_percentage !== null)
    ? parseFloat(firstInv.copayment_percentage)
    : ((visitRow.copayment_percentage !== undefined && visitRow.copayment_percentage !== null) ? parseFloat(visitRow.copayment_percentage) : (isIns ? 15 : 100));
  const coverPct = Math.max(0, 100 - copayPct);

  // Update header badge
  const badgeEl = document.getElementById('inv-v-badge');
  if (badgeEl) {
    badgeEl.style.display = 'inline-flex';
    if (isIns) {
      badgeEl.innerHTML = `<i class="fas fa-shield-alt" style="margin-right:4px;"></i> ${iEsc(rawCompName.toUpperCase())} (${coverPct}%) COPAY(${copayPct}%)`;
    } else {
      badgeEl.innerHTML = `<i class="fas fa-user" style="margin-right:4px;"></i> SELF-PAY`;
      if (invoices.length && (visitRow.visit_payment_type || 'self_pay') === 'self_pay') invOfferInsurance(visitRow, invoices, badgeEl);
    }
  }

  let visitGrandTotal = 0;
  let visitTotalInsCovers = 0;
  let visitTotalPatientShare = 0;
  let visitTotalPatientDue = 0;

  for (const inv of invoices) {
    const total = parseFloat(inv.total || 0);
    if (total <= 0) continue;
    const items = inv._items || [];
    const invCopay = inv.copayment_percentage != null ? parseFloat(inv.copayment_percentage) : copayPct;
    const invIsIns = (inv.payment_type === 'insurance' || inv.payment_type === 'mixed') && isIns;
    const hasExplicit = items.some(it => parseInt(it.apply_insurance || 0) > 0 || parseFloat(it.insurance_price || 0) > 0);

    let invIns = 0;
    let invPat = 0;

    if (items.length) {
      items.forEach(it => {
        const shares = calcInvoiceItemShares(it, invIsIns, invCopay, hasExplicit);
        invIns += shares.insShare;
        invPat += shares.patShare;
      });
    } else {
      invPat = calculatePatientShare(inv);
      invIns = total - invPat;
    }

    const payRecords = inv.payment_records || [];
    const totalPaidByPatient = payRecords
      .filter(r => r.status === 'completed' || r.status === 'written_off')
      .reduce((s, r) => s + parseFloat(r.amount_paid || 0), 0);

    let invDue = Math.max(0, invPat - totalPaidByPatient);
    if (inv.status === 'paid' || invDue <= 1.05) {
      invDue = 0;
    }

    visitGrandTotal += total;
    visitTotalInsCovers += invIns;
    visitTotalPatientShare += invPat;
    visitTotalPatientDue += invDue;
  }

  document.getElementById('inv-v-grand-total').textContent = Math.round(visitGrandTotal).toLocaleString() + ' RWF';
  if (isIns) {
    document.getElementById('inv-v-ins-block').style.display = 'block';
    document.getElementById('inv-v-ins-label').textContent = `Insurance (${coverPct}%)`;
    document.getElementById('inv-v-ins-total').textContent = Math.round(visitTotalInsCovers).toLocaleString() + ' RWF';
    document.getElementById('inv-v-copay-block').style.display = 'block';
    document.getElementById('inv-v-copay-label').textContent = `Patient Share (${copayPct}%)`;
    document.getElementById('inv-v-copay-total').textContent = Math.round(visitTotalPatientShare).toLocaleString() + ' RWF';
  } else {
    document.getElementById('inv-v-ins-block').style.display = 'none';
    document.getElementById('inv-v-copay-block').style.display = 'none';
  }
  document.getElementById('inv-v-total').textContent = Math.round(visitTotalPatientDue).toLocaleString() + ' RWF';
  document.getElementById('inv-pay-all-btn').style.display = visitTotalPatientDue > 0 ? 'inline-flex' : 'none';

  // Store for pay-all
  invCurrentVisitData = {
    visit_id: visitId,
    visit_no: visitRow.visit_no || '',
    patient_name: patientName,
    patient_no: visitRow.patient_no || '',
    date: (visitRow.visit_date || '').substring(0, 10),
    total: visitTotalPatientDue,
    invoices: invoices
  };
  await invInsAssignPanel(visitId, invoices, container);

  if(!invoices.length){
    container.innerHTML='<div style="text-align:center;padding:30px;color:var(--gray-400);">No pending invoices for this visit.</div>';
    return;
  }

  let html='';
  const _cs={last:null,any:(invoices).some(x=>x.care==='hosp')};
  for(const inv of invoices){
    if(parseFloat(inv.total||0)<=0) continue;
    const items=inv._items||[]; // Items already loaded above
    const total=parseFloat(inv.total||0),balance=parseFloat(inv.balance||0);
    const paidAmt=parseFloat(inv.paid_amount||0);
    const unpaidAmt=parseFloat(inv.unpaid_amount||0);
    const patientShare=calculatePatientShare(inv);
    
    // Calculate total paid including write-offs (they reduce patient balance too)
    const payRecords=inv.payment_records||[];
    const totalPaidByPatient = payRecords
      .filter(r=>r.status==='completed' || r.status==='written_off')
      .reduce((sum,r)=>sum+parseFloat(r.amount_paid||0), 0);
    
    let patientDue=Math.max(0, patientShare - totalPaidByPatient);
    if (inv.status === 'paid' || patientDue <= 1.05) {
      patientDue = 0;
    }
    const isIns=inv.payment_type==='insurance'||inv.payment_type==='mixed';
    const statusColors={issued:'#fef3c7',partial:'#dbeafe',paid:'#dcfce7',draft:'#f3f4f6'};
    const statusText={issued:'#92400e',partial:'#1e40af',paid:'#166534',draft:'#6b7280'};

    // Calculate insurance coverage from items
    const copayPct=parseFloat(inv.copayment_percentage??100);
    let insurancePays = 0;
    if(inv._items && inv._items.length){
      const hasInsuranceFields = inv._items.some(item => 
        parseInt(item.apply_insurance||0) > 0 || parseFloat(item.insurance_price||0) > 0
      );
      
      if(hasInsuranceFields){
        // NEW invoices: calculate from items
        inv._items.forEach(item=>{
          const qty=parseInt(item.quantity||1);
          const insPrice=parseFloat(item.insurance_price||0);
          const applyIns=parseInt(item.apply_insurance||0);
          if(applyIns && insPrice>0){
            insurancePays += insPrice * ((100-copayPct)/100) * qty;
          }
        });
      } else {
        // OLD invoices: use total - patientShare
        insurancePays = total - patientShare;
      }
    } else {
      // Fallback
      insurancePays = total - patientShare;
    }

    let payBreakdown='';
    if(payRecords.length){
      const completed=payRecords.filter(r=>r.status==='completed');
      const writtenOff=payRecords.filter(r=>r.status==='written_off');
      const pending=payRecords.filter(r=>r.status==='pending');
      if(completed.length||writtenOff.length||pending.length){
        payBreakdown+=`<div style="margin:8px 0;padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid var(--gray-200);">`;
        payBreakdown+=`<div style="font-size:11px;font-weight:700;color:var(--gray-600);text-transform:uppercase;margin-bottom:6px;">Payment Breakdown</div>`;
        completed.forEach(r=>{payBreakdown+=`<div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;"><span><i class="fas fa-check-circle" style="color:#16a34a;margin-right:4px;"></i>${r.payment_method.replace(/_/g,' ')} ${r.payment_date?'('+r.payment_date+')':''} ${r.receipt_number?'— '+r.receipt_number:''}</span><span style="font-weight:600;color:#16a34a;">${parseFloat(r.amount_paid).toLocaleString()} RWF</span></div>`;});
        writtenOff.forEach(r=>{payBreakdown+=`<div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 6px;background:#fef2f2;border-radius:4px;margin-top:2px;"><span><i class="fas fa-times-circle" style="color:#dc2626;margin-right:4px;"></i><strong>Written off</strong> ${r.payment_date?'('+r.payment_date+')':''}</span><span style="font-weight:600;color:#dc2626;">${parseFloat(r.amount_paid).toLocaleString()} RWF</span></div>`;});
        pending.forEach(r=>{payBreakdown+=`<div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;"><span><i class="fas fa-clock" style="color:#7c3aed;margin-right:4px;"></i>Pending ${r.payment_date?'('+r.payment_date+')':''}</span><span style="font-weight:600;color:#7c3aed;">${parseFloat(r.amount_paid).toLocaleString()} RWF</span></div>`;});
        payBreakdown+=`<div style="border-top:1px solid var(--gray-200);margin-top:6px;padding-top:6px;display:flex;justify-content:space-between;font-size:12px;font-weight:700;"><span>Patient Balance Due</span><span style="color:#dc2626;">${patientDue.toLocaleString()} RWF</span></div>`;
        payBreakdown+=`</div>`;
      }
    }
    const coverPct = Math.max(0, 100 - copayPct);
    const insBadgeText = isIns 
      ? `${(inv.company_name || 'Insurance').toUpperCase()} (${coverPct}%) COPAY(${copayPct}%)` 
      : 'SELF-PAY';

    let totalInsCovers = 0;
    let totalPatientPays = 0;
    const hasExplicitOverrides = items && items.some(it => parseInt(it.apply_insurance || 0) > 0 || parseFloat(it.insurance_price || 0) > 0);

    const itemRows = invMergeLines(items).map(it => {
      const shares = calcInvoiceItemShares(it, isIns, copayPct, hasExplicitOverrides);
      totalInsCovers += shares.insShare;
      totalPatientPays += shares.patShare;

      if (isIns) {
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 12px;">
            <div style="font-weight:500;">${iEsc(it.description)}${invSrcBtn(it,inv)}</div>
            ${it.service_code ? `<span style="font-size:10px;color:var(--gray-400);font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
          </td>
          <td style="padding:8px 12px;text-align:center;"><span style="padding:1px 6px;border-radius:8px;font-size:10px;background:#f3e8ff;color:#6b21a8;">${iEsc(it.category||'—')}</span></td>
          <td style="padding:8px 12px;text-align:center;">${shares.qty}</td>
          <td style="padding:8px 12px;text-align:right;">${shares.unitPrice.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;font-weight:600;">${shares.lineTotal.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;color:#059669;font-weight:600;background:#f0fdf4;">${shares.insShare.toLocaleString()} RWF</td>
          <td style="padding:8px 12px;text-align:right;color:#2563eb;font-weight:600;background:#eff6ff;">${Math.round(shares.patShare).toLocaleString()} RWF</td>
        </tr>`;
      } else {
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 12px;">
            <div style="font-weight:500;">${iEsc(it.description)}${invSrcBtn(it,inv)}</div>
            ${it.service_code ? `<span style="font-size:10px;color:var(--gray-400);font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
          </td>
          <td style="padding:8px 12px;text-align:center;"><span style="padding:1px 6px;border-radius:8px;font-size:10px;background:#f3e8ff;color:#6b21a8;">${iEsc(it.category||'—')}</span></td>
          <td style="padding:8px 12px;text-align:center;">${shares.qty}</td>
          <td style="padding:8px 12px;text-align:right;">${shares.unitPrice.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;font-weight:600;">${shares.lineTotal.toLocaleString()}</td>
        </tr>`;
      }
    }).join('');

    html+=invCareHdr(inv,_cs)+`<div style="border:1px solid var(--gray-200);border-radius:10px;margin-bottom:16px;overflow:hidden;">
      <div style="background:var(--gray-50);padding:12px 16px;display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--gray-200);flex-wrap:wrap;gap:12px;">
        <div>
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <span style="font-weight:700;font-size:14px;color:var(--primary-color);">${iEsc(inv.invoice_no)}</span>
            <span style="padding:3px 8px;border-radius:8px;font-size:10px;font-weight:600;background:${statusColors[inv.status]||'#f3f4f6'};color:${statusText[inv.status]||'#6b7280'};">${(inv.status||'').toUpperCase()}</span>
            <span style="padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;background:${isIns?'#dbeafe':'#f3f4f6'};color:${isIns?'#1e40af':'#374151'};"><i class="${isIns?'fas fa-shield-alt':'fas fa-user'}"></i> ${iEsc(insBadgeText)}</span>
            ${inv.status!=='paid'?`<button onclick="invRecalc(${inv.visit_id},${inv.patient_id},${inv.id})" title="Re-price this invoice with the patient's current insurance" style="padding:3px 9px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;"><i class="fas fa-sync-alt"></i> Recalculate</button>`:''}
            <button onclick="invPrintSingleProforma('${inv.id}')" style="padding:3px 9px;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;" title="Print Proforma for this invoice"><i class="fas fa-print"></i> Proforma</button>
            <button onclick="invOpenAuditLogModal('${inv.id}')" style="padding:3px 9px;background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;" title="View Invoice Edit History &amp; Audit Trail"><i class="fas fa-history"></i> Logs</button>
          </div>
          <div style="font-size:11px;color:var(--gray-600);margin-top:6px;display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
            <span><i class="fas fa-user-edit" style="color:var(--primary-color);"></i> Created by: <strong style="color:var(--gray-800);">${iEsc(inv.created_by_name || 'Staff / System')}</strong></span>
            ${(inv.payment_received_by_name || inv.received_by_name) ? `<span><i class="fas fa-user-check" style="color:#16a34a;"></i> Payment Received by: <strong style="color:#166534;">${iEsc(inv.payment_received_by_name || inv.received_by_name)}</strong></span>` : ''}
            ${inv.created_at ? `<span style="color:var(--gray-400);"><i class="far fa-clock"></i> ${iEsc(inv.created_at.substring(0,16))}</span>` : ''}
          </div>
        </div>
        <div style="text-align:right;font-size:12px;min-width:200px;">
          <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:var(--gray-500);">Invoice total:</span>
            <span style="font-weight:600;">${total.toLocaleString()} RWF</span>
          </div>
          ${isIns?`<div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#059669;font-weight:500;">Insurance covers (${coverPct}%):</span>
            <span style="color:#059669;font-weight:600;">${Math.round(totalInsCovers).toLocaleString()} RWF</span>
          </div>`:''}
          <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#2563eb;font-weight:500;">Patient share (${copayPct}%):</span>
            <span style="font-weight:600;color:#2563eb;">${Math.round(totalPatientPays).toLocaleString()} RWF</span>
          </div>
          ${totalPaidByPatient>0?`<div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#16a34a;">Paid so far:</span>
            <span style="font-weight:600;color:#16a34a;">${totalPaidByPatient.toLocaleString()} RWF</span>
          </div>`:''}
          <div style="display:flex;justify-content:space-between;gap:16px;border-top:1px solid var(--gray-200);padding-top:4px;margin-top:2px;">
            <span style="font-weight:700;color:#dc2626;">Still owed:</span>
            <span style="font-weight:700;color:#dc2626;">${patientDue.toLocaleString()} RWF</span>
          </div>
          ${invSurplusRow(inv)}
        </div>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead><tr style="background:white;border-bottom:1px solid var(--gray-200);">
          <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Service / Description</th>
          <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);width:90px;">Category</th>
          <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);width:50px;">Qty</th>
          <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--gray-600);width:90px;">Unit Price</th>
          <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--gray-600);width:90px;">Total</th>
          ${isIns?`<th style="padding:8px 12px;text-align:right;font-weight:600;color:#059669;background:#f0fdf4;width:130px;">Insurance (${coverPct}%)</th><th style="padding:8px 12px;text-align:right;font-weight:600;color:#2563eb;background:#eff6ff;width:120px;">Patient (${copayPct}%)</th>`:''}
        </tr></thead>
        <tbody>${items.length?itemRows:`<tr><td colspan="${isIns?7:5}" style="padding:12px;text-align:center;color:var(--gray-400);">No items</td></tr>`}</tbody>
        <tfoot><tr style="border-top:2px solid var(--gray-200);background:var(--gray-50);font-weight:700;">
          <td colspan="4" style="padding:8px 12px;">Total</td>
          <td style="padding:8px 12px;text-align:right;">${total.toLocaleString()} RWF</td>
          ${isIns?`<td style="padding:8px 12px;text-align:right;color:#059669;background:#f0fdf4;">${Math.round(totalInsCovers).toLocaleString()} RWF</td><td style="padding:8px 12px;text-align:right;color:#2563eb;background:#eff6ff;">${Math.round(totalPatientPays).toLocaleString()} RWF</td>`:''}
        </tr></tfoot>
      </table>
      ${payBreakdown}
    </div>`;
  }
  const partlyPaidNote = Math.round(partlyPaidOwed) > 0
    ? `<div style="margin-bottom:12px;padding:10px 14px;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;font-size:13px;color:#6b21a8;"><i class="fas fa-exclamation-circle"></i> This visit also has <strong>${Math.round(partlyPaidOwed).toLocaleString()} RWF</strong> still owed on partly-paid invoices — collect it from the <a href="invoicing.php?module=finance&sub=partial&search=${encodeURIComponent(visitRow.visit_no||'')}" style="color:#6b21a8;font-weight:700;">Partial tab</a>. Total still owed for the visit: <strong>${Math.round(visitTotalPatientDue+partlyPaidOwed).toLocaleString()} RWF</strong>.</div>`
    : '';
  container.innerHTML=partlyPaidNote+(html||'<div style="text-align:center;padding:30px;color:var(--gray-400);">No invoice details found.</div>');
  window.scrollTo({top:0,behavior:'smooth'});
}


function invUpdatePayBtn(totalDue){
  const amount=parseFloat(document.getElementById('inv-pay-amount').value)||0;
  const btn=document.getElementById('inv-pay-btn');
  const hint=document.getElementById('inv-pay-amount-hint');
  if(amount<=0){btn.disabled=true;return;}
  btn.disabled=false;
  btn.innerHTML='<i class="fas fa-check"></i> Confirm Payment — '+amount.toLocaleString()+' RWF';
  if(amount<totalDue){
    hint.innerHTML='<span style="color:#d97706;font-weight:600;">⚠ Partial payment — remaining balance (debt): '+(totalDue-amount).toLocaleString()+' RWF. Invoice will be marked as <em>partial</em>.</span>';
    btn.style.background='#d97706';
  } else if(amount>totalDue){
    hint.innerHTML='<span style="color:#7c3aed;font-weight:600;">Surplus — refund to patient: '+(amount-totalDue).toLocaleString()+' RWF. Only '+totalDue.toLocaleString()+' RWF is recorded.</span>';
    btn.style.background='#16a34a';
  } else {
    hint.innerHTML='Full payment — invoice(s) will be marked as <em>paid</em>.';
    btn.style.background='#16a34a';
  }
}

async function invPrintProformaGrouped(){
  if(!invCurrentVisitData) return;
  const visit=invCurrentVisitData;
  let hospital={};
  try{
    const r=await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=settings/hospital');
    const j=await r.json();
    if(j.success) hospital=j.data||{};
  }catch(e){}

  let allItems=[];
  let grandTotal=0;
  let grandPatientShare=0;
  let grandInsShare=0;
  let grandPaid=0;
  let grandPatientDue=0;

  for(const inv of visit.invoices){
    let items=inv._items||[];
    if(!items.length){
      try{
        const r=await fetch(billAPI+'&id='+inv.id);
        const j=await r.json();
        if(j.success&&j.data&&j.data.items){
          items=j.data.items;
          inv._items=items;
        }
      }catch(e){}
    }

    const total=parseFloat(inv.total||0);
    const isIns=(inv.payment_type==='insurance'||inv.payment_type==='mixed');
    const copayPct=parseFloat(inv.copayment_percentage??(isIns?15:100));
    const hasExplicit=items.some(it=>parseInt(it.apply_insurance||0)>0||parseFloat(it.insurance_price||0)>0);

    let invIns=0;
    let invPat=0;
    if(items.length){
      items.forEach(it=>{
        const shares=calcInvoiceItemShares(it, isIns, copayPct, hasExplicit);
        invIns+=shares.insShare;
        invPat+=shares.patShare;
        allItems.push({...it, _invoice_no:inv.invoice_no});
      });
    } else {
      invPat=calculatePatientShare(inv);
      invIns=total-invPat;
    }

    const payRecords=inv.payment_records||[];
    const totalPaid=payRecords
      .filter(r=>r.status==='completed'||r.status==='written_off')
      .reduce((s,r)=>s+parseFloat(r.amount_paid||0), 0);

    let invDue=Math.max(0, invPat - totalPaid);
    if(inv.status === 'paid' || invDue <= 1.05) invDue = 0;

    grandTotal+=total;
    grandInsShare+=invIns;
    grandPatientShare+=invPat;
    grandPaid+=totalPaid;
    grandPatientDue+=invDue;
  }

  const html=generateProformaInvoiceHTML(visit, allItems, grandTotal, grandPatientShare, grandInsShare, grandPaid, grandPatientDue, hospital);
  invShowPrintPreviewModal(html, 'Proforma Invoice — '+visit.patient_name+' | '+visit.visit_no, 'Proforma_Invoice_'+visit.visit_no);
}

async function invPrintProformaForVisit(visitId){
  const visitRow=invAllVisits.find(v=>String(v.visit_id)===String(visitId));
  const patientName=visitRow ? ((visitRow.first_name||'')+' '+(visitRow.last_name||'')) : 'Patient';

  let hospital={};
  try{
    const r=await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=settings/hospital');
    const j=await r.json();
    if(j.success) hospital=j.data||{};
  }catch(e){}

  let invoices=[];
  try{
    const r=await fetch(billAPI+'&visit_id='+visitId+'&status=all');
    const j=await r.json();
    invoices=j.data||[];
  }catch(e){
    alert('Failed to load visit invoices for proforma');
    return;
  }

  invoices=invoices.filter(inv=>['issued','partial','draft'].includes(inv.status));
  if(!invoices.length){
    alert('No pending invoices found for this visit');
    return;
  }

  let allItems=[];
  let grandTotal=0;
  let grandPatientShare=0;
  let grandInsShare=0;
  let grandPaid=0;
  let grandPatientDue=0;

  for(const inv of invoices){
    let items=[];
    try{
      const r=await fetch(billAPI+'&id='+inv.id);
      const j=await r.json();
      if(j.success&&j.data&&j.data.items) items=j.data.items;
    }catch(e){}

    const total=parseFloat(inv.total||0);
    const isIns=(inv.payment_type==='insurance'||inv.payment_type==='mixed');
    const copayPct=parseFloat(inv.copayment_percentage??(isIns?15:100));
    const hasExplicit=items.some(it=>parseInt(it.apply_insurance||0)>0||parseFloat(it.insurance_price||0)>0);

    let invIns=0;
    let invPat=0;
    if(items.length){
      items.forEach(it=>{
        const shares=calcInvoiceItemShares(it, isIns, copayPct, hasExplicit);
        invIns+=shares.insShare;
        invPat+=shares.patShare;
        allItems.push({...it, _invoice_no:inv.invoice_no});
      });
    } else {
      invPat=calculatePatientShare(inv);
      invIns=total-invPat;
    }

    const payRecords=inv.payment_records||[];
    const totalPaid=payRecords
      .filter(r=>r.status==='completed'||r.status==='written_off')
      .reduce((s,r)=>s+parseFloat(r.amount_paid||0), 0);

    let invDue=Math.max(0, invPat - totalPaid);
    if(inv.status === 'paid' || invDue <= 1.05) invDue = 0;

    grandTotal+=total;
    grandInsShare+=invIns;
    grandPatientShare+=invPat;
    grandPaid+=totalPaid;
    grandPatientDue+=invDue;
  }

  const visitData={
    visit_id:visitId,
    visit_no:visitRow ? (visitRow.visit_no||'') : (invoices[0]?.visit_no||''),
    patient_name:patientName,
    patient_no:visitRow ? (visitRow.patient_no||'') : (invoices[0]?.patient_no||''),
    date:visitRow ? (visitRow.visit_date||'').substring(0,10) : (invoices[0]?.visit_date||'').substring(0,10),
    age:visitRow ? calcAge(visitRow.date_of_birth) : '',
    insurance:visitRow ? (visitRow.company_name||(visitRow.visit_payment_type==='insurance'?'Insurance':'Self-pay')) : (invoices[0]?.company_name||'Self-pay'),
    created_by_name:visitRow ? visitRow.created_by_name : (invoices[0]?.created_by_name||''),
    invoices:invoices
  };

  const html=generateProformaInvoiceHTML(visitData, allItems, grandTotal, grandPatientShare, grandInsShare, grandPaid, grandPatientDue, hospital);
  invShowPrintPreviewModal(html, 'Proforma Invoice — '+visitData.patient_name+' | '+visitData.visit_no, 'Proforma_Invoice_'+visitData.visit_no);
}

async function invPrintSingleProforma(invoiceId){
  let hospital={};
  try{
    const r=await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=settings/hospital');
    const j=await r.json();
    if(j.success) hospital=j.data||{};
  }catch(e){}

  let inv=null;
  try{
    const r=await fetch(billAPI+'&id='+invoiceId);
    const j=await r.json();
    if(j.success&&j.data) inv=j.data;
  }catch(e){
    alert('Failed to load invoice for proforma');
    return;
  }

  if(!inv){
    alert('Invoice not found');
    return;
  }

  const items=inv.items||[];
  const total=parseFloat(inv.total||0);
  const isIns=(inv.payment_type==='insurance'||inv.payment_type==='mixed');
  const copayPct=parseFloat(inv.copayment_percentage??(isIns?15:100));
  const hasExplicit=items.some(it=>parseInt(it.apply_insurance||0)>0||parseFloat(it.insurance_price||0)>0);

  let invIns=0;
  let invPat=0;
  if(items.length){
    items.forEach(it=>{
      const shares=calcInvoiceItemShares(it, isIns, copayPct, hasExplicit);
      invIns+=shares.insShare;
      invPat+=shares.patShare;
      it._invoice_no=inv.invoice_no;
    });
  } else {
    invPat=calculatePatientShare(inv);
    invIns=total-invPat;
  }

  const payRecords=inv.payment_records||[];
  const totalPaid=payRecords
    .filter(r=>r.status==='completed'||r.status==='written_off')
    .reduce((s,r)=>s+parseFloat(r.amount_paid||0), 0);

  let patientDue=Math.max(0, invPat - totalPaid);
  if(inv.status === 'paid' || patientDue <= 1.05) patientDue = 0;
  const patientName=(inv.first_name||'')+' '+(inv.last_name||'');

  const visitData={
    visit_id:inv.visit_id,
    visit_no:inv.visit_no||('INV-'+inv.invoice_no),
    patient_name:patientName,
    patient_no:inv.patient_no||'',
    date:(inv.invoice_date||inv.visit_date||'').substring(0,10),
    age:calcAge(inv.date_of_birth),
    insurance:isIns ? ((inv.company_name||'Insurance')+' ('+copayPct+'% Copay)') : 'Self-pay',
    created_by_name:inv.created_by_name||'',
    invoices:[inv]
  };

  const html=generateProformaInvoiceHTML(visitData, items, total, invPat, invIns, totalPaid, patientDue, hospital, true);
  invShowPrintPreviewModal(html, 'Proforma Invoice — '+inv.invoice_no+' ('+patientName+')', 'Proforma_Invoice_'+inv.invoice_no);
}

invLoad();
</script>

<?php elseif ($subModule == 'partial' || $subModule == 'deferred'): ?>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div id="partial-list-section">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
      <h3 style="font-size:16px;font-weight:600;"><?php echo $subModule == 'deferred'
        ? '<i class="fas fa-clock" style="color:#a21caf;"></i> Deferred — Transferred to Finance / Unpaid'
        : '<i class="fas fa-adjust" style="color:#7c3aed;"></i> Partial Payments'; ?></h3>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php invMonthSelect('partial'); ?>
        <input type="date" id="partial-date-from" onchange="partialCurrentPage=1;partialLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <span style="font-size:12px;color:var(--gray-400);">to</span>
        <input type="date" id="partial-date-to" onchange="partialCurrentPage=1;partialLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <input type="text" id="partial-search" placeholder="Search patient, visit..." oninput="partialSearchDebounce()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;width:200px;">
        <button onclick="partialLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
      </div>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:var(--gray-50);">
          <th style="padding:10px 12px;text-align:left;">#</th>
          <th style="padding:10px 12px;text-align:left;">Date</th>
          <th style="padding:10px 12px;text-align:left;">Patient</th>
          <th style="padding:10px 12px;text-align:left;">Visit No</th>
          <th style="padding:10px 12px;text-align:left;">Insurance</th>
          <th style="padding:10px 12px;text-align:right;">Invoice Total</th>
          <th style="padding:10px 12px;text-align:right;color:#16a34a;">Paid</th>
          <th style="padding:10px 12px;text-align:right;color:#7c3aed;">Deferred</th>
          <th style="padding:10px 12px;text-align:right;color:#dc2626;">Balance</th>
          <th style="padding:10px 12px;text-align:left;">Created By</th>
          <th style="padding:10px 12px;text-align:center;">Action</th>
        </tr></thead>
        <tbody id="partial-tbody"><tr><td colspan="11" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
      </table>
    </div>
    <div id="partial-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;"></div>
  </div>

  <!-- Visit detail view for partial tab -->
  <div id="partial-visit-view" style="display:none;">
    <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
      <button onclick="partialBackToList()" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;"><i class="fas fa-arrow-left"></i> Back to List</button>
      <button id="partial-pay-btn" onclick="partialPayNow()" style="padding:10px 20px;background:#16a34a;color:white;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;display:none;"><i class="fas fa-money-bill"></i> Pay Outstanding Balance</button>
    </div>
    <div style="background:linear-gradient(135deg,#7c3aed,#a855f7);border-radius:10px;padding:18px 24px;color:white;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <div style="width:46px;height:46px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;" id="partial-v-avatar">?</div>
      <div style="flex:1;min-width:180px;">
        <div style="font-size:17px;font-weight:700;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span id="partial-v-name">—</span>
          <span id="partial-v-badge" style="display:none;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:rgba(255,255,255,0.25);color:white;"></span>
        </div>
        <div style="font-size:12px;opacity:0.9;margin-top:4px;" id="partial-v-info">—</div>
      </div>
      <div style="display:flex;gap:20px;flex-wrap:wrap;text-align:right;">
        <div>
          <div style="font-size:11px;opacity:0.8;">Grand Total</div>
          <div style="font-size:18px;font-weight:700;" id="partial-v-grand-total">—</div>
        </div>
        <div id="partial-v-ins-block" style="display:none;">
          <div style="font-size:11px;opacity:0.8;" id="partial-v-ins-label">Insurance Covers</div>
          <div style="font-size:18px;font-weight:700;color:#a7f3d0;" id="partial-v-ins-total">—</div>
        </div>
        <div id="partial-v-copay-block" style="display:none;">
          <div style="font-size:11px;opacity:0.8;" id="partial-v-copay-label">Patient Share</div>
          <div style="font-size:18px;font-weight:700;color:#bfdbfe;" id="partial-v-copay-total">—</div>
        </div>
        <div>
          <div style="font-size:11px;opacity:0.8;">Paid</div>
          <div style="font-size:18px;font-weight:700;color:#bbf7d0;" id="partial-v-paid">0 RWF</div>
        </div>
        <div>
          <div style="font-size:11px;opacity:0.8;">Deferred</div>
          <div style="font-size:18px;font-weight:700;color:#ddd6fe;" id="partial-v-deferred">0 RWF</div>
        </div>
        <div>
          <div style="font-size:11px;opacity:0.8;">Balance</div>
          <div style="font-size:22px;font-weight:700;color:#fecaca;" id="partial-v-balance">0 RWF</div>
        </div>
      </div>
    </div>
    <div id="partial-visit-invoices"></div>
  </div>
</div>

<script>
const partialBillAPI='<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing';
const partialMode='<?php echo $subModule; ?>'; // 'partial' or 'deferred' — same page, different filter
let partialAllVisits=[], partialCurrentPage=1;
const partialPageSize=20;
let partialSearchTimer, partialCurrentVisitData=null;

// Shared payment modal globals (used by partialPayNow → invPayAll)
const billAPI=partialBillAPI;
invIsAdmin=<?php echo $isAdmin ? 'true' : 'false'; ?>;
let invCurrentVisitData=null;
function invLoad(){partialLoad();}


function invUpdatePayBtn(totalDue){
  const amount=parseFloat(document.getElementById('inv-pay-amount').value)||0;
  const btn=document.getElementById('inv-pay-btn');
  const hint=document.getElementById('inv-pay-amount-hint');
  if(amount<=0){btn.disabled=true;return;}
  btn.disabled=false;
  btn.innerHTML='<i class="fas fa-check"></i> Confirm Payment — '+amount.toLocaleString()+' RWF';
  if(amount<totalDue){
    hint.innerHTML='<span style="color:#d97706;font-weight:600;">⚠ Partial payment — remaining balance (debt): '+(totalDue-amount).toLocaleString()+' RWF. Invoice will be marked as <em>partial</em>.</span>';
    btn.style.background='#d97706';
  } else if(amount>totalDue){
    hint.innerHTML='<span style="color:#7c3aed;font-weight:600;">Surplus — refund to patient: '+(amount-totalDue).toLocaleString()+' RWF. Only '+totalDue.toLocaleString()+' RWF is recorded.</span>';
    btn.style.background='#16a34a';
  } else {
    hint.innerHTML='Full payment — invoice(s) will be marked as <em>paid</em>.';
    btn.style.background='#16a34a';
  }
}

(function(){
  invToday('partial-date-from','partial-date-to');
})();

function partialSearchDebounce(){clearTimeout(partialSearchTimer);partialSearchTimer=setTimeout(()=>{partialCurrentPage=1;partialApplyFilter();},300);}

async function partialLoad(){
  const tbody=document.getElementById('partial-tbody');
  tbody.innerHTML='<tr><td colspan="10" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    // Fetch all invoices with partial/issued status
    const from=document.getElementById('partial-date-from').value;
    const to=document.getElementById('partial-date-to').value;
    let partialUrl=partialBillAPI+'&status=all'+invCareQS();
    if(from) partialUrl+='&date_from='+encodeURIComponent(from);
    if(to)   partialUrl+='&date_to='+encodeURIComponent(to);
    const r=await fetch(partialUrl);
    const j=await r.json();
    const invoices=j.data||[];

    // First pass: group ALL invoices by visit
    const allGrouped={};
    invoices.forEach(inv=>{
      const key=inv.visit_id||inv.id;
      const payType=(inv.payment_type||'self_pay').toLowerCase();
      const isIns=payType==='insurance'||payType==='mixed';
      const copayVal=inv.copayment_percentage!==undefined&&inv.copayment_percentage!==null?parseFloat(inv.copayment_percentage):null;
      const insName=isIns?(inv.company_name||'Insurance')+(copayVal!==null?' ('+copayVal+'% Copay)':''):'Self-pay';
      if(!allGrouped[key]) allGrouped[key]={
        visit_id:inv.visit_id,visit_no:inv.visit_no||'—',
        patient_name:(inv.first_name||'')+' '+(inv.last_name||''),
        patient_no:inv.patient_no||'',patient_id:inv.patient_id,
        date:(inv.visit_date||'').substring(0,10),
        age:calcAge(inv.date_of_birth),
        insurance:insName,
        invoices:[],total:0,paid:0,deferred:0,balance:0,
        hasAnyPayment:false, hasDeferred:false,
        created_by_set:new Set()
      };
      const paidAmt=parseFloat(inv.paid_amount||0);
      const unpaidAmt=parseFloat(inv.unpaid_amount||0);
      if(paidAmt>0) allGrouped[key].hasAnyPayment=true;
      if(unpaidAmt>0) allGrouped[key].hasDeferred=true;
      const cre=(inv.created_by_name||'').trim();
      if(cre) allGrouped[key].created_by_set.add(cre);
      allGrouped[key].invoices.push(inv);
    });

    // Second pass: keep visits that have partial or deferred payment and still have balance,
    // including ALL non-cancelled invoices for that visit so complete financials and pending items are shown.
    const grouped={};
    Object.entries(allGrouped).forEach(([key,visit])=>{
      let visitTotal=0;
      let visitPaid=0;
      let visitDeferred=0;
      let visitBalance=0;
      let hasPartialOrDeferred=false;
      let visitDebt=0;

      visit.invoices.forEach(inv=>{
        if(inv.status==='cancelled' || inv.status==='paid') return;
        const total=Math.round(parseFloat(inv.total||0));
        const copay=parseFloat(inv.copayment_percentage??100);
        const patientShare=calculatePatientShare(inv);
        
        const payRecords=inv.payment_records||[];
        const completedPaid=payRecords
          .filter(r=>r.status==='completed')
          .reduce((s,r)=>s+Math.round(parseFloat(r.amount_paid||0)),0);
        const writtenOffPaid=payRecords
          .filter(r=>r.status==='written_off')
          .reduce((s,r)=>s+Math.round(parseFloat(r.amount_paid||0)),0);
        const totalPaidByRecords=completedPaid+writtenOffPaid;

        const paidAmt=payRecords.length ? completedPaid : Math.round(parseFloat(inv.paid_amount||0));
        const deferredAmt=payRecords.length ? writtenOffPaid : Math.round(parseFloat(inv.writtenoff_amount||0));
        const unpaidAmt=Math.round(parseFloat(inv.unpaid_amount||0));

        const debtAmt=Math.round(parseFloat(inv.debt_amount||0)); // with Finance — collected there, not here
        const effectivePaid=payRecords.length ? totalPaidByRecords : (paidAmt+deferredAmt);
        let patientDue=Math.max(0, patientShare - effectivePaid - debtAmt);
        if(patientDue <= 1.05){
          patientDue = 0;
        }

        // Deferred = transferred to Finance or left unpaid/written off; Partial = paid part, nothing deferred.
        // Untouched (e.g. reverted) invoices belong in Pending.
        const isDeferred = debtAmt > 0 || deferredAmt > 0 || unpaidAmt > 0;
        const isPartial  = !isDeferred && (paidAmt > 0 || inv.status === 'partial');
        if(partialMode === 'deferred' ? isDeferred : isPartial){
          hasPartialOrDeferred=true;
        }

        visitTotal+=total;
        visitPaid+=paidAmt;
        visitDeferred+=(unpaidAmt+deferredAmt+debtAmt);
        visitDebt+=debtAmt;
        visitBalance+=patientDue;
      });

      // A visit fully handed to Finance has nothing left to collect here but still belongs in Deferred
      if(hasPartialOrDeferred && (visitBalance > 1.05 || visitDebt > 0)){
        visit.total=visitTotal;
        visit.paid=visitPaid;
        visit.deferred=visitDeferred;
        visit.balance=visitBalance;
        visit.created_by=visit.created_by_set && visit.created_by_set.size > 0
          ? Array.from(visit.created_by_set).join(', ')
          : '—';
        grouped[key]=visit;
      }
    });

    partialAllVisits=Object.values(grouped).filter(v=>v.invoices.length>0)
      .sort((a,b)=>(b.date||'').localeCompare(a.date||''));
    partialApplyFilter();
  }catch(e){
    tbody.innerHTML='<tr><td colspan="11" style="padding:30px;text-align:center;color:#dc2626;">Error loading</td></tr>';
  }
  document.getElementById('partial-visit-view').style.display='none';
  document.getElementById('partial-list-section').style.display='block';
}

function partialApplyFilter(){
  const from=document.getElementById('partial-date-from').value;
  const to=document.getElementById('partial-date-to').value;
  const q=(document.getElementById('partial-search').value||'').toLowerCase();
  let filtered=partialAllVisits;
  if(from) filtered=filtered.filter(v=>v.date>=from);
  if(to) filtered=filtered.filter(v=>v.date<=to);
  if(q) filtered=filtered.filter(v=>v.patient_name.toLowerCase().includes(q)||v.patient_no.toLowerCase().includes(q)||v.visit_no.toLowerCase().includes(q)||(v.created_by||'').toLowerCase().includes(q));
  partialRenderTable(filtered);
}

function partialRenderTable(list){
  const tbody=document.getElementById('partial-tbody');
  const pgEl=document.getElementById('partial-pagination');
  if(!list.length){
    tbody.innerHTML='<tr><td colspan="11" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-clock" style="font-size:20px;display:block;margin-bottom:8px;color:#7c3aed;"></i>No '+(partialMode==='deferred'?'deferred':'partial')+' payments found.</td></tr>';
    pgEl.innerHTML='';return;
  }
  const totalPages=Math.ceil(list.length/partialPageSize);
  if(partialCurrentPage>totalPages) partialCurrentPage=totalPages;
  if(partialCurrentPage<1) partialCurrentPage=1;
  const start=(partialCurrentPage-1)*partialPageSize;
  const page=list.slice(start,start+partialPageSize);

  tbody.innerHTML=page.map((v,i)=>`<tr style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onclick="partialShowVisit('${v.visit_id}')" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
    <td style="padding:10px 12px;color:var(--gray-500);font-size:12px;">${start+i+1}</td>
    <td style="padding:10px 12px;font-size:12px;">${iEsc(v.date)}</td>
    <td style="padding:10px 12px;font-weight:600;">${iEsc(v.patient_name)}<div style="font-size:11px;color:var(--gray-500);">${iEsc(v.patient_no)}</div></td>
    <td style="padding:10px 12px;font-family:monospace;font-size:12px;color:var(--primary-color);font-weight:600;">${iEsc(v.visit_no)}</td>
    <td style="padding:10px 12px;font-size:12px;">${v.insurance.toLowerCase()!=='self-pay'?`<span style="padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;"><i class="fas fa-shield-alt" style="margin-right:3px;"></i>${iEsc(v.insurance)}</span>`:`<span style="padding:3px 8px;border-radius:12px;font-size:11px;background:#f3f4f6;color:#4b5563;">Self-pay</span>`}</td>
    <td style="padding:10px 12px;text-align:right;font-weight:600;">${v.total.toLocaleString()} RWF</td>
    <td style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:600;">${v.paid.toLocaleString()} RWF</td>
    <td style="padding:10px 12px;text-align:right;color:#7c3aed;font-weight:600;">${v.deferred>0?v.deferred.toLocaleString()+' RWF':'—'}</td>
    <td style="padding:10px 12px;text-align:right;color:#dc2626;font-weight:700;">${v.balance.toLocaleString()} RWF</td>
    <td style="padding:10px 12px;font-size:12px;color:var(--gray-700);">${iEsc(v.created_by||'—')}</td>
    <td style="padding:10px 12px;text-align:center;"><button style="padding:5px 12px;background:#7c3aed;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:500;"><i class="fas fa-eye"></i> View</button></td>
  </tr>`).join('');

  if(totalPages<=1){pgEl.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">${list.length} visit${list.length!==1?'s':''}</span><div></div>`;return;}
  pgEl.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">Page ${partialCurrentPage} of ${totalPages} (${list.length} visits)</span>
    <div style="display:flex;gap:4px;">
      <button onclick="partialCurrentPage--;partialApplyFilter()" ${partialCurrentPage===1?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:white;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-chevron-left"></i></button>
      <button onclick="partialCurrentPage++;partialApplyFilter()" ${partialCurrentPage===totalPages?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:white;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-chevron-right"></i></button>
    </div>`;
}

function partialBackToList(){
  document.getElementById('partial-visit-view').style.display='none';
  document.getElementById('partial-list-section').style.display='block';
}

async function partialShowVisit(visitId){
  const visit=partialAllVisits.find(v=>String(v.visit_id)===String(visitId));
  if(!visit) return;
  partialCurrentVisitData=visit;
  document.getElementById('partial-list-section').style.display='none';
  document.getElementById('partial-visit-view').style.display='block';
  document.getElementById('partial-v-avatar').textContent=(visit.patient_name||'?').charAt(0).toUpperCase();
  document.getElementById('partial-v-name').textContent=visit.patient_name;
  document.getElementById('partial-v-info').textContent='Patient: '+visit.patient_no+' | Visit: '+visit.visit_no+' | Date: '+visit.date;

  const rawCompName = visit.insurance && visit.insurance.toLowerCase() !== 'self-pay' ? visit.insurance : '';
  const isVisitIns = Boolean(rawCompName);
  const firstCopay = visit.invoices && visit.invoices[0] ? parseFloat(visit.invoices[0].copayment_percentage ?? 100) : (isVisitIns ? 15 : 100);
  const coverPct = Math.max(0, 100 - firstCopay);

  const badgeEl = document.getElementById('partial-v-badge');
  if (badgeEl) {
    badgeEl.style.display = 'inline-flex';
    if (isVisitIns) {
      badgeEl.innerHTML = `<i class="fas fa-shield-alt" style="margin-right:4px;"></i> ${iEsc(rawCompName.toUpperCase())} (${coverPct}%) COPAY(${firstCopay}%)`;
    } else {
      badgeEl.innerHTML = `<i class="fas fa-user" style="margin-right:4px;"></i> SELF-PAY`;
    }
  }

  let grandTotal = 0;
  let totalInsShare = 0;
  let totalPatShare = 0;
  for (const inv of (visit.invoices || [])) {
    const invTotal = Math.round(parseFloat(inv.total || 0));
    const invCopay = parseFloat(inv.copayment_percentage ?? firstCopay);
    const invIsIns = (inv.payment_type || '').toLowerCase() === 'insurance' || (inv.payment_type || '').toLowerCase() === 'mixed';
    grandTotal += invTotal;
    if (invIsIns) {
      const pShare = calculatePatientShare(inv);
      totalPatShare += pShare;
      totalInsShare += Math.max(0, invTotal - pShare);
    } else {
      totalPatShare += invTotal;
    }
  }

  document.getElementById('partial-v-grand-total').textContent = Math.round(grandTotal).toLocaleString() + ' RWF';
  if (isVisitIns) {
    document.getElementById('partial-v-ins-block').style.display = 'block';
    document.getElementById('partial-v-ins-label').textContent = `Insurance (${coverPct}%)`;
    document.getElementById('partial-v-ins-total').textContent = Math.round(totalInsShare).toLocaleString() + ' RWF';
    document.getElementById('partial-v-copay-block').style.display = 'block';
    document.getElementById('partial-v-copay-label').textContent = `Patient Share (${firstCopay}%)`;
    document.getElementById('partial-v-copay-total').textContent = Math.round(totalPatShare).toLocaleString() + ' RWF';
  } else {
    document.getElementById('partial-v-ins-block').style.display = 'none';
    document.getElementById('partial-v-copay-block').style.display = 'none';
  }

  document.getElementById('partial-v-paid').textContent=visit.paid.toLocaleString()+' RWF';
  document.getElementById('partial-v-deferred').textContent=visit.deferred.toLocaleString()+' RWF';
  document.getElementById('partial-v-balance').textContent=visit.balance.toLocaleString()+' RWF';
  document.getElementById('partial-pay-btn').style.display=visit.balance>0?'inline-flex':'none';

  const container=document.getElementById('partial-visit-invoices');
  container.innerHTML='<div style="text-align:center;padding:20px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  let html='';
  const _cs={last:null,any:(visit.invoices).some(x=>x.care==='hosp')};
  for(const inv of visit.invoices){
    if(inv.status==='cancelled') continue;
    const isPaid = (inv.status === 'paid');
    const _total=Math.round(parseFloat(inv.total||0));
    const _copay=parseFloat(inv.copayment_percentage??100);
    const _patientShare=calculatePatientShare(inv);
    // Use payment_records as source of truth
    const _payRecords=inv.payment_records||[];
    const _completedPaid=_payRecords.filter(r=>r.status==='completed').reduce((s,r)=>s+Math.round(parseFloat(r.amount_paid||0)),0);
    const _writtenOff=_payRecords.filter(r=>r.status==='written_off').reduce((s,r)=>s+Math.round(parseFloat(r.amount_paid||0)),0);
    const _paidByRecords=_completedPaid+_writtenOff;
    const _unpaid=Math.round(parseFloat(inv.unpaid_amount||0));
    const _paidAmt=_payRecords.length ? _completedPaid : Math.round(parseFloat(inv.paid_amount||0));
    const _deferredAmt=_payRecords.length ? _writtenOff : Math.round(parseFloat(inv.writtenoff_amount||0));
    const _debt=Math.round(parseFloat(inv.debt_amount||0));
    let _patientDue=Math.max(0,_patientShare - (_payRecords.length ? _paidByRecords : (_paidAmt+_deferredAmt)) - _debt);
    if (isPaid || _patientDue <= 1.05) {
      _patientDue = 0;
    }

    let items=inv._items||[];
    if(!items.length){
      try{const r=await fetch(partialBillAPI+'&id='+inv.id);const j=await r.json();if(j.success&&j.data&&j.data.items)items=j.data.items;}catch(e){}
    }
    const total=_total;
    const unpaidAmt=_unpaid + _deferredAmt;
    const copayPct=_copay;
    const patientShare=_patientShare;
    const totalPaidByPatient=_paidAmt;
    const patientDue=_patientDue;
    const isIns=(inv.payment_type||'').toLowerCase()==='insurance'||(inv.payment_type||'').toLowerCase()==='mixed';
    const insurancePays=isIns?Math.max(0,total-patientShare):0;
    const payRecords=_payRecords;
    const statusColors={issued:'#fef3c7',partial:'#dbeafe',paid:'#dcfce7',draft:'#f3f4f6'};
    const statusText={issued:'#92400e',partial:'#1e40af',paid:'#166534',draft:'#6b7280'};
    const computedStatus = (isPaid || (_patientDue <= 0 && total > 0)) ? 'paid' : (_paidAmt > 0 ? 'partial' : (_deferredAmt > 0 || _unpaid > 0 ? 'partial' : (inv.status || 'issued')));

    // Payment breakdown
    const completed=payRecords.filter(r=>r.status==='completed');
    const writtenOff=payRecords.filter(r=>r.status==='written_off');
    const pending=payRecords.filter(r=>r.status==='pending');
    let breakdown='';
    if(completed.length||writtenOff.length||pending.length){
      breakdown+=`<div style="margin:0 12px 12px;padding:10px 12px;background:#f8fafc;border-radius:6px;border:1px solid var(--gray-200);">
        <div style="font-size:11px;font-weight:700;color:var(--gray-600);text-transform:uppercase;margin-bottom:6px;">Payment Breakdown</div>`;
      completed.forEach(r=>{
        breakdown+=`<div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;">
          <span><i class="fas fa-check-circle" style="color:#16a34a;margin-right:4px;"></i>${(r.payment_method||'cash').replace(/_/g,' ')} ${r.payment_date?'('+r.payment_date+')':''} ${r.receipt_number?'— '+r.receipt_number:''}</span>
          <span style="font-weight:600;color:#16a34a;">${parseFloat(r.amount_paid).toLocaleString()} RWF</span></div>`;
      });
      writtenOff.forEach(r=>{
        breakdown+=`<div style="display:flex;justify-content:space-between;font-size:12px;padding:4px 6px;background:#fef2f2;border-radius:4px;margin-top:2px;">
          <span><i class="fas fa-times-circle" style="color:#dc2626;margin-right:4px;"></i><strong>Written off / Deferred</strong> ${r.payment_date?'('+r.payment_date+')':''}</span>
          <span style="font-weight:600;color:#dc2626;">${parseFloat(r.amount_paid).toLocaleString()} RWF</span></div>`;
      });
      pending.forEach(r=>{
        breakdown+=`<div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;background:#faf5ff;border-radius:4px;padding:4px 6px;margin-top:2px;">
          <span><i class="fas fa-clock" style="color:#7c3aed;margin-right:4px;"></i><strong>Pending</strong> ${r.payment_date?'('+r.payment_date+')':''}</span>
          <span style="font-weight:600;color:#7c3aed;">${parseFloat(r.amount_paid).toLocaleString()} RWF</span></div>`;
      });
      if(patientDue>0){
        breakdown+=`<div style="border-top:1px solid var(--gray-200);margin-top:6px;padding-top:6px;display:flex;justify-content:space-between;font-size:12px;font-weight:700;">
          <span>Patient Balance Due</span><span style="color:#dc2626;">${patientDue.toLocaleString()} RWF</span></div>`;
      }
      breakdown+=`</div>`;
    }

    const coverPct = Math.max(0, 100 - copayPct);
    const insBadgeText = isIns 
      ? `${(inv.company_name || 'Insurance').toUpperCase()} (${coverPct}%) COPAY(${copayPct}%)` 
      : 'SELF-PAY';

    let totalInsCovers = 0;
    let totalPatientPays = 0;
    const hasExplicitOverrides = items && items.some(it => parseInt(it.apply_insurance || 0) > 0 || parseFloat(it.insurance_price || 0) > 0);

    const itemRows = invMergeLines(items).map(it => {
      const shares = calcInvoiceItemShares(it, isIns, copayPct, hasExplicitOverrides);
      totalInsCovers += shares.insShare;
      totalPatientPays += shares.patShare;

      if (isIns) {
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 12px;">
            <div style="font-weight:500;">${iEsc(it.description)}${invSrcBtn(it,inv)}</div>
            ${it.service_code ? `<span style="font-size:10px;color:var(--gray-400);font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
          </td>
          <td style="padding:8px 12px;text-align:center;"><span style="padding:1px 6px;border-radius:8px;font-size:10px;background:#f3e8ff;color:#6b21a8;">${iEsc(it.category||'—')}</span></td>
          <td style="padding:8px 12px;text-align:center;">${shares.qty}</td>
          <td style="padding:8px 12px;text-align:right;">${shares.unitPrice.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;font-weight:600;">${shares.lineTotal.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;color:#059669;font-weight:600;background:#f0fdf4;">${shares.insShare.toLocaleString()} RWF</td>
          <td style="padding:8px 12px;text-align:right;color:#2563eb;font-weight:600;background:#eff6ff;">${Math.round(shares.patShare).toLocaleString()} RWF</td>
        </tr>`;
      } else {
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 12px;">
            <div style="font-weight:500;">${iEsc(it.description)}${invSrcBtn(it,inv)}</div>
            ${it.service_code ? `<span style="font-size:10px;color:var(--gray-400);font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
          </td>
          <td style="padding:8px 12px;text-align:center;"><span style="padding:1px 6px;border-radius:8px;font-size:10px;background:#f3e8ff;color:#6b21a8;">${iEsc(it.category||'—')}</span></td>
          <td style="padding:8px 12px;text-align:center;">${shares.qty}</td>
          <td style="padding:8px 12px;text-align:right;">${shares.unitPrice.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;font-weight:600;">${shares.lineTotal.toLocaleString()}</td>
        </tr>`;
      }
    }).join('');

    if (computedStatus === 'paid' && Math.abs(totalPatientPays - totalPaidByPatient) <= 1.05) {
      totalPatientPays = totalPaidByPatient;
    }

    html+=invCareHdr(inv,_cs)+`<div style="border:1px solid var(--gray-200);border-radius:10px;margin-bottom:16px;overflow:hidden;">
      <div style="background:var(--gray-50);padding:12px 16px;display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--gray-200);flex-wrap:wrap;gap:12px;">
        <div>
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <span style="font-weight:700;font-size:14px;color:#7c3aed;">${iEsc(inv.invoice_no)}</span>
            <span style="padding:3px 8px;border-radius:8px;font-size:10px;font-weight:600;background:${statusColors[computedStatus]||'#f3f4f6'};color:${statusText[computedStatus]||'#6b7280'};margin-left:6px;">${(computedStatus).toUpperCase()}</span>
            <span style="padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;background:${isIns?'#dbeafe':'#f3f4f6'};color:${isIns?'#1e40af':'#374151'};"><i class="${isIns?'fas fa-shield-alt':'fas fa-user'}"></i> ${iEsc(insBadgeText)}</span>
            <button onclick="invPrintSingleProforma('${inv.id}')" style="padding:3px 9px;background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;" title="Print Proforma for this invoice"><i class="fas fa-print"></i> Proforma</button>
            <button onclick="invOpenAuditLogModal('${inv.id}')" style="padding:3px 9px;background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;" title="View Invoice Edit History &amp; Audit Trail"><i class="fas fa-history"></i> Logs</button>
          </div>
          <div style="font-size:11px;color:var(--gray-600);margin-top:6px;display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
            <span><i class="fas fa-user-edit" style="color:#7c3aed;"></i> Created by: <strong style="color:var(--gray-800);">${iEsc(inv.created_by_name || 'Staff / System')}</strong></span>
            ${(inv.payment_received_by_name || inv.received_by_name) ? `<span><i class="fas fa-user-check" style="color:#16a34a;"></i> Payment Received by: <strong style="color:#166534;">${iEsc(inv.payment_received_by_name || inv.received_by_name)}</strong></span>` : ''}
            ${inv.created_at ? `<span style="color:var(--gray-400);"><i class="far fa-clock"></i> ${iEsc(inv.created_at.substring(0,16))}</span>` : ''}
          </div>
        </div>
        <div style="text-align:right;font-size:12px;min-width:200px;">
          <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:var(--gray-500);">Invoice total:</span>
            <span style="font-weight:600;">${total.toLocaleString()} RWF</span>
          </div>
          ${isIns?`<div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#059669;font-weight:500;">Insurance covers (${coverPct}%):</span>
            <span style="color:#059669;font-weight:600;">${Math.round(totalInsCovers).toLocaleString()} RWF</span>
          </div>`:''}
          <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#2563eb;font-weight:500;">Patient share (${copayPct}%):</span>
            <span style="font-weight:600;color:#2563eb;">${Math.round(totalPatientPays).toLocaleString()} RWF</span>
          </div>
          <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#16a34a;"><i class="fas fa-check-circle"></i> Paid so far:</span>
            <span style="font-weight:600;color:#16a34a;">${totalPaidByPatient.toLocaleString()} RWF</span>
          </div>
          ${unpaidAmt>0?`<div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#7c3aed;"><i class="fas fa-clock"></i> Deferred:</span>
            <span style="font-weight:600;color:#7c3aed;">${unpaidAmt.toLocaleString()} RWF</span>
          </div>`:''}
          ${_debt>0?`<div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#a21caf;"><i class="fas fa-hand-holding-usd"></i> With Finance (debt):</span>
            <span style="font-weight:600;color:#a21caf;">${_debt.toLocaleString()} RWF</span>
          </div>`:''}
          <div style="display:flex;justify-content:space-between;gap:16px;border-top:1px solid var(--gray-200);padding-top:4px;margin-top:2px;">
            <span style="font-weight:700;color:${patientDue>0?'#dc2626':'#16a34a'};">Still owed:</span>
            <span style="font-weight:700;color:${patientDue>0?'#dc2626':'#16a34a'};">${patientDue>0 ? patientDue.toLocaleString()+' RWF' : '0 RWF (Settled)'}</span>
          </div>
          ${invSurplusRow(inv)}
        </div>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead><tr style="background:white;border-bottom:1px solid var(--gray-200);">
          <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Description</th>
          <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);width:90px;">Category</th>
          <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);width:50px;">Qty</th>
          <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--gray-600);width:90px;">Unit Price</th>
          <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--gray-600);width:90px;">Total</th>
          ${isIns?`<th style="padding:8px 12px;text-align:right;font-weight:600;color:#059669;background:#f0fdf4;width:130px;">Insurance (${coverPct}%)</th><th style="padding:8px 12px;text-align:right;font-weight:600;color:#2563eb;background:#eff6ff;width:120px;">Patient (${copayPct}%)</th>`:''}
        </tr></thead>
        <tbody>${items.length?itemRows:`<tr><td colspan="${isIns?7:5}" style="padding:12px;text-align:center;color:var(--gray-400);">No items</td></tr>`}</tbody>
        <tfoot><tr style="border-top:2px solid var(--gray-200);background:var(--gray-50);font-weight:700;">
          <td colspan="4" style="padding:8px 12px;">Total</td>
          <td style="padding:8px 12px;text-align:right;">${total.toLocaleString()} RWF</td>
          ${isIns?`<td style="padding:8px 12px;text-align:right;color:#059669;background:#f0fdf4;">${Math.round(totalInsCovers).toLocaleString()} RWF</td><td style="padding:8px 12px;text-align:right;color:#2563eb;background:#eff6ff;">${Math.round(totalPatientPays).toLocaleString()} RWF</td>`:''}
        </tr></tfoot>
      </table>
      ${breakdown}
    </div>`;
  }
  container.innerHTML=html||'<p style="text-align:center;color:var(--gray-400);">No invoice details.</p>';
  window.scrollTo({top:0,behavior:'smooth'});
}

async function partialPayNow(){
  if(!partialCurrentVisitData||partialCurrentVisitData.balance<=0) return;
  // Reuse the same payment modal logic from pending tab — wire it to partialCurrentVisitData
  invCurrentVisitData = {
    visit_id: partialCurrentVisitData.visit_id,
    visit_no: partialCurrentVisitData.visit_no,
    patient_name: partialCurrentVisitData.patient_name,
    patient_no: partialCurrentVisitData.patient_no,
    total: partialCurrentVisitData.balance,
    invoices: partialCurrentVisitData.invoices.filter(i=>{
      if(i.status === 'cancelled') return false;
      const total=Math.round(parseFloat(i.total||0));
      const copay=parseFloat(i.copayment_percentage??100);
      const patientShare=calculatePatientShare(i);
      const payRecords=i.payment_records||[];
      const completedPaid=payRecords.filter(r=>r.status==='completed').reduce((s,r)=>s+Math.round(parseFloat(r.amount_paid||0)),0);
      const writtenOffPaid=payRecords.filter(r=>r.status==='written_off').reduce((s,r)=>s+Math.round(parseFloat(r.amount_paid||0)),0);
      const paidAmt=payRecords.length ? (completedPaid + writtenOffPaid) : (Math.round(parseFloat(i.paid_amount||0)) + Math.round(parseFloat(i.writtenoff_amount||0)));
      return Math.max(0, patientShare - paidAmt - Math.round(parseFloat(i.debt_amount||0))) > 0;
    })
  };
  await invPayAll();
}

partialLoad();
</script>

<?php elseif ($subModule == 'paid'): ?>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <!-- List Section -->
  <div id="paid-list-section">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-check-circle" style="color:#16a34a;"></i> Paid Invoices — Grouped by Visit</h3>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <?php invMonthSelect('paid'); ?>
        <input type="date" id="paid-date-from" onchange="paidCurrentPage=1;paidLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <span style="font-size:12px;color:var(--gray-400);">to</span>
        <input type="date" id="paid-date-to" onchange="paidCurrentPage=1;paidLoad()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <input type="text" id="paid-search" placeholder="Search visit no, patient..." oninput="paidSearchDebounce()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;width:200px;">
        <button onclick="paidLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
      </div>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead><tr style="background:var(--gray-50);">
          <th style="padding:10px 12px;text-align:left;">#</th>
          <th style="padding:10px 12px;text-align:left;">Date</th>
          <th style="padding:10px 12px;text-align:left;">Patient</th>
          <th style="padding:10px 12px;text-align:left;">Patient No</th>
          <th style="padding:10px 12px;text-align:left;">Visit No</th>
          <th style="padding:10px 12px;text-align:left;">Age</th>
          <th style="padding:10px 12px;text-align:left;">Insurance</th>
          <th style="padding:10px 12px;text-align:center;">Invoices</th>
          <th style="padding:10px 12px;text-align:right;">Patient Share</th>
          <th style="padding:10px 12px;text-align:right;">Insurance Share</th>
          <th style="padding:10px 12px;text-align:right;">Total</th>
          <th style="padding:10px 12px;text-align:left;">Received By</th>
          <th style="padding:10px 12px;text-align:center;">Action</th>
        </tr></thead>
        <tbody id="paid-tbody"><tr><td colspan="13" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
      </table>
    </div>
    <div id="paid-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;"></div>
  </div>

  <!-- Visit Detail View (hidden until click) -->
  <div id="paid-visit-view" style="display:none;">
    <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
      <button onclick="paidBackToList()" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;"><i class="fas fa-arrow-left"></i> Back to List</button>
      <button id="paid-download-btn" onclick="paidDownloadGrouped()" style="padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;"><i class="fas fa-eye"></i> Preview & Download</button>
    </div>
    <div style="background:linear-gradient(135deg,#16a34a,#059669);border-radius:10px;padding:18px 24px;color:white;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
      <div style="width:46px;height:46px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;" id="paid-v-avatar">?</div>
      <div style="flex:1;min-width:180px;">
        <div style="font-size:17px;font-weight:700;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <span id="paid-v-name">—</span>
          <span id="paid-v-badge" style="display:none;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:rgba(255,255,255,0.25);color:white;"></span>
        </div>
        <div style="font-size:12px;opacity:0.9;margin-top:4px;" id="paid-v-info">—</div>
      </div>
      <div style="display:flex;gap:20px;flex-wrap:wrap;text-align:right;">
        <div><div style="font-size:11px;opacity:0.8;">Grand Total</div><div style="font-size:18px;font-weight:700;" id="paid-v-total">—</div></div>
        <div><div style="font-size:11px;opacity:0.8;">Patient Share</div><div style="font-size:18px;font-weight:700;color:#bfdbfe;" id="paid-v-patient-share">—</div></div>
        <div id="paid-v-ins-block" style="display:none;"><div style="font-size:11px;opacity:0.8;">Insurance Share</div><div style="font-size:18px;font-weight:700;color:#ddd6fe;" id="paid-v-ins-share">—</div></div>
        <div><div style="font-size:11px;opacity:0.8;">Collected from Patient</div><div style="font-size:18px;font-weight:700;color:#bbf7d0;" id="paid-v-collected">—</div></div>
      </div>
    </div>
    <div id="paid-visit-invoices"></div>
  </div>
</div>
<script>
let paidAllVisits=[];
let paidCurrentPage=1;
const paidPageSize=20;
let paidSearchTimer;
let paidCurrentVisitData=null;

(function(){
  invToday('paid-date-from','paid-date-to');
})();

function paidSearchDebounce(){clearTimeout(paidSearchTimer);paidSearchTimer=setTimeout(()=>{paidCurrentPage=1;paidApplyFilter();},300);}

async function paidLoad(){
  const tbody=document.getElementById('paid-tbody');
  tbody.innerHTML='<tr><td colspan="13" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const from=document.getElementById('paid-date-from').value;
    const to=document.getElementById('paid-date-to').value;
    let paidUrl='<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing&status=paid'+invCareQS();
    if(from) paidUrl+='&date_from='+encodeURIComponent(from);
    if(to)   paidUrl+='&date_to='+encodeURIComponent(to);
    const r=await fetch(paidUrl);
    const j=await r.json();
    const invoices=j.data||[];
    const grouped={};
    invoices.forEach(inv=>{
      const key=inv.visit_id||inv.id;
      const payType=(inv.payment_type||inv.visit_payment_type||'self_pay').toLowerCase();
      const isIns=payType==='insurance'||payType==='mixed';
      const copayVal=inv.copayment_percentage!==undefined&&inv.copayment_percentage!==null?parseFloat(inv.copayment_percentage):null;
      const insName=isIns?(inv.company_name||'Insurance')+(copayVal!==null?' ('+copayVal+'% Copay)':''):'Self-pay';
      if(!grouped[key]) {
        grouped[key]={
          visit_id:inv.visit_id,
          visit_no:inv.visit_no||'—',
          patient_name:(inv.first_name||'')+' '+(inv.last_name||''),
          patient_no:inv.patient_no||'',
          patient_id:inv.patient_id,
          date:(inv.visit_date||'').substring(0,10),
          age:calcAge(inv.date_of_birth),
          insurance:insName,
          invoices:[],
          total:0,
          patient_paid:0,
          collected:0,
          insurance_owes:0,
          received_by_set:new Set(),
          created_by_set:new Set()
        };
      }
      grouped[key].invoices.push(inv);
      const rec = (inv.payment_received_by_name || inv.received_by_name || '').trim();
      if(rec) grouped[key].received_by_set.add(rec);
      const cre = (inv.created_by_name || '').trim();
      if(cre) grouped[key].created_by_set.add(cre);

      const invTotal=Math.round(parseFloat(inv.total||0));
      const copay=parseFloat(inv.copayment_percentage??100);
      const paidAmt=Math.round(parseFloat(inv.paid_amount||0));
      let patAmt=calculatePatientShare(inv);
      if (Math.abs(patAmt - paidAmt) <= 1.05 && paidAmt > 0) {
        patAmt = paidAmt;
      }
      const insAmt=isIns?Math.max(0, invTotal - patAmt):0;
      grouped[key].total+=invTotal;
      grouped[key].patient_paid+=patAmt;
      grouped[key].collected+=paidAmt;
      grouped[key].insurance_owes+=insAmt;
      const d=(inv.visit_date||'').substring(0,10);
      if(d&&(!grouped[key].date||d>grouped[key].date)) grouped[key].date=d;
    });

    Object.values(grouped).forEach(v => {
      v.received_by = v.received_by_set && v.received_by_set.size > 0
        ? Array.from(v.received_by_set).join(', ')
        : '—';
      v.created_by = v.created_by_set && v.created_by_set.size > 0
        ? Array.from(v.created_by_set).join(', ')
        : '—';
    });

    paidAllVisits=Object.values(grouped).sort((a,b)=>(b.date||'').localeCompare(a.date||''));
    paidApplyFilter();
  }catch(e){tbody.innerHTML='<tr><td colspan="13" style="padding:30px;text-align:center;color:#dc2626;">Error loading</td></tr>';}
  document.getElementById('paid-visit-view').style.display='none';
  document.getElementById('paid-list-section').style.display='block';
}

function paidApplyFilter(){
  const from=document.getElementById('paid-date-from').value;
  const to=document.getElementById('paid-date-to').value;
  const q=(document.getElementById('paid-search').value||'').toLowerCase();
  let filtered=paidAllVisits;
  if(from) filtered=filtered.filter(v=>v.date>=from);
  if(to) filtered=filtered.filter(v=>v.date<=to);
  if(q) filtered=filtered.filter(v=>v.patient_name.toLowerCase().includes(q)||v.patient_no.toLowerCase().includes(q)||v.visit_no.toLowerCase().includes(q)||(v.received_by||'').toLowerCase().includes(q)||(v.created_by||'').toLowerCase().includes(q));
  paidRenderTable(filtered);
}

function paidGoPage(p){paidCurrentPage=p;paidApplyFilter();}

function paidRenderTable(list){
  const tbody=document.getElementById('paid-tbody');
  const pgEl=document.getElementById('paid-pagination');
  if(!list.length){tbody.innerHTML='<tr><td colspan="13" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-receipt" style="font-size:20px;display:block;margin-bottom:8px;"></i>No paid invoices found for this period.</td></tr>';pgEl.innerHTML='';return;}
  const totalPages=Math.ceil(list.length/paidPageSize);
  if(paidCurrentPage>totalPages)paidCurrentPage=totalPages;
  if(paidCurrentPage<1)paidCurrentPage=1;
  const start=(paidCurrentPage-1)*paidPageSize;
  const page=list.slice(start,start+paidPageSize);
  tbody.innerHTML=page.map((v,i)=>`<tr style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onclick="paidShowVisit('${v.visit_id||v.invoices[0].id}')" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
    <td style="padding:10px 12px;color:var(--gray-500);font-size:12px;">${start+i+1}</td>
    <td style="padding:10px 12px;font-size:12px;">${iEsc(v.date)}</td>
    <td style="padding:10px 12px;font-weight:600;">${iEsc(v.patient_name)}</td>
    <td style="padding:10px 12px;font-family:monospace;font-size:12px;color:var(--gray-500);">${iEsc(v.patient_no)}</td>
    <td style="padding:10px 12px;font-family:monospace;font-size:12px;color:var(--primary-color);font-weight:600;">${iEsc(v.visit_no)}</td>
    <td style="padding:10px 12px;font-size:12px;">${iEsc(v.age||'—')}</td>
    <td style="padding:10px 12px;font-size:12px;">${v.insurance.toLowerCase()!=='self-pay'?`<span style="padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;"><i class="fas fa-shield-alt" style="margin-right:3px;"></i>${iEsc(v.insurance)}</span>`:`<span style="padding:3px 8px;border-radius:12px;font-size:11px;background:#f3f4f6;color:#4b5563;">Self-pay</span>`}</td>
    <td style="padding:10px 12px;text-align:center;"><span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;">${v.invoices.length}</span></td>
    <td style="padding:10px 12px;text-align:right;font-weight:600;color:#2563eb;">${v.patient_paid.toLocaleString()} RWF</td>
    <td style="padding:10px 12px;text-align:right;font-weight:600;color:${v.insurance_owes>0?'#7c3aed':'var(--gray-400)'};">${v.insurance_owes>0?v.insurance_owes.toLocaleString()+' RWF':'—'}</td>
    <td style="padding:10px 12px;text-align:right;font-weight:700;color:#065f46;">${v.total.toLocaleString()} RWF</td>
    <td style="padding:10px 12px;font-size:12px;color:#166534;font-weight:500;">
      <div><i class="fas fa-user-check" style="color:#16a34a;margin-right:4px;font-size:11px;"></i>${iEsc(v.received_by||'—')}</div>
    </td>
    <td style="padding:10px 12px;text-align:center;"><button style="padding:5px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:500;"><i class="fas fa-eye"></i> View</button></td>
  </tr>`).join('');
  if(totalPages<=1){pgEl.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">${list.length} visit${list.length!==1?'s':''}</span><div></div>`;return;}
  let btns=`<button onclick="paidGoPage(${paidCurrentPage-1})" ${paidCurrentPage===1?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${paidCurrentPage===1?'var(--gray-100)':'white'};border-radius:6px;cursor:${paidCurrentPage===1?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-left"></i></button>`;
  let sp=Math.max(1,paidCurrentPage-3),ep=Math.min(totalPages,sp+6);if(ep-sp<6)sp=Math.max(1,ep-6);
  for(let p=sp;p<=ep;p++){const a=p===paidCurrentPage;btns+=`<button onclick="paidGoPage(${p})" style="padding:6px 10px;border:1px solid ${a?'var(--primary-color)':'var(--gray-200)'};background:${a?'var(--primary-color)':'white'};color:${a?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:12px;font-weight:${a?'600':'400'};">${p}</button>`;}
  btns+=`<button onclick="paidGoPage(${paidCurrentPage+1})" ${paidCurrentPage===totalPages?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${paidCurrentPage===totalPages?'var(--gray-100)':'white'};border-radius:6px;cursor:${paidCurrentPage===totalPages?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-right"></i></button>`;
  pgEl.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">Page ${paidCurrentPage} of ${totalPages} (${list.length} visits)</span><div style="display:flex;gap:4px;">${btns}</div>`;
}

function paidBackToList(){
  document.getElementById('paid-visit-view').style.display='none';
  document.getElementById('paid-list-section').style.display='block';
}

async function paidShowVisit(visitId){
  const visit=paidAllVisits.find(v=>String(v.visit_id)===String(visitId)||v.invoices.some(i=>String(i.id)===String(visitId)));
  if(!visit)return;
  paidCurrentVisitData=visit;
  document.getElementById('paid-list-section').style.display='none';
  document.getElementById('paid-visit-view').style.display='block';
  document.getElementById('paid-v-avatar').textContent=(visit.patient_name||'?').charAt(0).toUpperCase();
  document.getElementById('paid-v-name').textContent=visit.patient_name;
  document.getElementById('paid-v-info').textContent='Patient: '+visit.patient_no+' | Visit: '+visit.visit_no+' | Date: '+visit.date+' | Created by: '+(visit.created_by||'—')+' | Received by: '+(visit.received_by||'—');
  
  const rawCompName = visit.insurance && visit.insurance.toLowerCase() !== 'self-pay' ? visit.insurance : '';
  const isVisitIns = Boolean(rawCompName);
  const firstCopay = visit.invoices && visit.invoices[0] ? parseFloat(visit.invoices[0].copayment_percentage ?? 100) : (isVisitIns ? 15 : 100);
  const coverPct = Math.max(0, 100 - firstCopay);

  const badgeEl = document.getElementById('paid-v-badge');
  if (badgeEl) {
    badgeEl.style.display = 'inline-flex';
    if (isVisitIns) {
      badgeEl.innerHTML = `<i class="fas fa-shield-alt" style="margin-right:4px;"></i> ${iEsc(rawCompName.toUpperCase())} (${coverPct}%) COPAY(${firstCopay}%)`;
    } else {
      badgeEl.innerHTML = `<i class="fas fa-user" style="margin-right:4px;"></i> SELF-PAY`;
    }
  }

  document.getElementById('paid-v-total').textContent=visit.total.toLocaleString()+' RWF';
  document.getElementById('paid-v-patient-share').textContent=visit.patient_paid.toLocaleString()+' RWF';
  document.getElementById('paid-v-collected').textContent=(visit.collected !== undefined && visit.collected > 0 ? visit.collected : visit.patient_paid).toLocaleString()+' RWF';
  const insBlock=document.getElementById('paid-v-ins-block');
  if(visit.insurance_owes>0){
    document.getElementById('paid-v-ins-share').textContent=visit.insurance_owes.toLocaleString()+' RWF';
    insBlock.style.display='block';
  } else {
    insBlock.style.display='none';
  }
  const container=document.getElementById('paid-visit-invoices');
  container.innerHTML='<div style="text-align:center;padding:20px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  let html='';
  const _cs={last:null,any:(visit.invoices).some(x=>x.care==='hosp')};
  for(const inv of visit.invoices){
    let items=[];
    try{const r=await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing&id='+inv.id);const j=await r.json();if(j.success&&j.data&&j.data.items)items=j.data.items;}catch(e){}
    const total=Math.round(parseFloat(inv.total||0)),paid=Math.round(parseFloat(inv.paid_amount||0));
    const method=(inv.payment_method||'cash').replace(/_/g,' ');
    const receivedBy = inv.payment_received_by_name || inv.received_by_name || inv.created_by_name || '—';
    const isIns = (inv.payment_type||'').toLowerCase()==='insurance' || (inv.payment_type||'').toLowerCase()==='mixed';
    const copayPct = parseFloat(inv.copayment_percentage ?? (isIns ? 10 : 100));
    const coverPct = Math.max(0, 100 - copayPct);
    const insBadgeText = isIns 
      ? `${(inv.company_name || 'Insurance').toUpperCase()} (${coverPct}%) COPAY(${copayPct}%)` 
      : 'SELF-PAY';

    let totalInsCovers = 0;
    let totalPatientPays = 0;
    const hasExplicitOverrides = items && items.some(it => parseInt(it.apply_insurance || 0) > 0 || parseFloat(it.insurance_price || 0) > 0);

    const itemRows = invMergeLines(items).map(it => {
      const shares = calcInvoiceItemShares(it, isIns, copayPct, hasExplicitOverrides);
      totalInsCovers += shares.insShare;
      totalPatientPays += shares.patShare;

      if (isIns) {
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 12px;">
            <div style="font-weight:500;">${iEsc(it.description)}</div>
            ${it.service_code ? `<span style="font-size:10px;color:var(--gray-400);font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
          </td>
          <td style="padding:8px 12px;text-align:center;"><span style="padding:1px 6px;border-radius:8px;font-size:10px;background:#f3e8ff;color:#6b21a8;">${iEsc(it.category||'—')}</span></td>
          <td style="padding:8px 12px;text-align:center;">${shares.qty}</td>
          <td style="padding:8px 12px;text-align:right;">${shares.unitPrice.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;font-weight:600;">${shares.lineTotal.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;color:#059669;font-weight:600;background:#f0fdf4;">${shares.insShare.toLocaleString()} RWF</td>
          <td style="padding:8px 12px;text-align:right;color:#2563eb;font-weight:600;background:#eff6ff;">${Math.round(shares.patShare).toLocaleString()} RWF</td>
        </tr>`;
      } else {
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 12px;">
            <div style="font-weight:500;">${iEsc(it.description)}</div>
            ${it.service_code ? `<span style="font-size:10px;color:var(--gray-400);font-family:monospace;">${iEsc(it.service_code)}</span>` : ''}
          </td>
          <td style="padding:8px 12px;text-align:center;"><span style="padding:1px 6px;border-radius:8px;font-size:10px;background:#f3e8ff;color:#6b21a8;">${iEsc(it.category||'—')}</span></td>
          <td style="padding:8px 12px;text-align:center;">${shares.qty}</td>
          <td style="padding:8px 12px;text-align:right;">${shares.unitPrice.toLocaleString()}</td>
          <td style="padding:8px 12px;text-align:right;font-weight:600;">${shares.lineTotal.toLocaleString()}</td>
        </tr>`;
      }
    }).join('');

    if (paid > 0 && Math.abs(totalPatientPays - paid) <= 1.05) {
      totalPatientPays = paid;
    }

    html+=invCareHdr(inv,_cs)+`<div style="border:1px solid var(--gray-200);border-radius:10px;margin-bottom:16px;overflow:hidden;">
      <div style="background:var(--gray-50);padding:12px 16px;display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--gray-200);flex-wrap:wrap;gap:8px;">
        <div>
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <span style="font-weight:700;font-size:14px;color:var(--primary-color);">${iEsc(inv.invoice_no)}</span> 
            <span style="padding:3px 8px;border-radius:8px;font-size:10px;font-weight:600;background:#dcfce7;color:#166534;">PAID</span> 
            <span style="padding:2px 6px;border-radius:6px;font-size:10px;background:#f3f4f6;color:#374151;">${method}</span>
            <span style="padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700;background:${isIns?'#dbeafe':'#f3f4f6'};color:${isIns?'#1e40af':'#374151'};"><i class="${isIns?'fas fa-shield-alt':'fas fa-user'}"></i> ${iEsc(insBadgeText)}</span>
            <button onclick="invOpenAuditLogModal('${inv.id}')" style="padding:3px 9px;background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;margin-left:4px;" title="View Invoice Edit History &amp; Audit Trail"><i class="fas fa-history"></i> Logs</button>
          </div>
          <div style="font-size:11px;color:var(--gray-600);margin-top:6px;display:flex;gap:14px;flex-wrap:wrap;align-items:center;">
            <span><i class="fas fa-user-edit" style="color:#16a34a;"></i> Created by: <strong style="color:var(--gray-800);">${iEsc(inv.created_by_name || 'Staff / System')}</strong></span>
            <span><i class="fas fa-user-check" style="color:#166534;"></i> Payment Received by: <strong style="color:#166534;">${iEsc(inv.payment_received_by_name || inv.received_by_name || receivedBy || 'Cashier')}</strong></span>
            ${inv.created_at ? `<span style="color:var(--gray-400);"><i class="far fa-clock"></i> ${iEsc(inv.created_at.substring(0,16))}</span>` : ''}
          </div>
        </div>
        <div style="text-align:right;font-size:12px;min-width:200px;">
          <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:var(--gray-500);">Invoice total:</span>
            <span style="font-weight:600;">${total.toLocaleString()} RWF</span>
          </div>
          ${isIns?`<div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#059669;font-weight:500;">Insurance covers (${coverPct}%):</span>
            <span style="color:#059669;font-weight:600;">${Math.round(totalInsCovers).toLocaleString()} RWF</span>
          </div>`:''}
          <div style="display:flex;justify-content:space-between;gap:16px;margin-bottom:2px;">
            <span style="color:#2563eb;font-weight:500;">Patient share (${copayPct}%):</span>
            <span style="font-weight:600;color:#2563eb;">${Math.round(totalPatientPays).toLocaleString()} RWF</span>
          </div>
          <div style="display:flex;justify-content:space-between;gap:16px;border-top:1px solid var(--gray-200);padding-top:4px;margin-top:2px;">
            <span style="font-weight:700;color:#065f46;">Paid:</span>
            <span style="font-weight:700;color:#065f46;">${paid.toLocaleString()} RWF</span>
          </div>
        </div>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead><tr style="background:white;border-bottom:1px solid var(--gray-200);">
          <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Description</th>
          <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);width:90px;">Category</th>
          <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);width:50px;">Qty</th>
          <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--gray-600);width:90px;">Unit Price</th>
          <th style="padding:8px 12px;text-align:right;font-weight:600;color:var(--gray-600);width:90px;">Total</th>
          ${isIns?`<th style="padding:8px 12px;text-align:right;font-weight:600;color:#059669;background:#f0fdf4;width:130px;">Insurance (${coverPct}%)</th><th style="padding:8px 12px;text-align:right;font-weight:600;color:#2563eb;background:#eff6ff;width:120px;">Patient (${copayPct}%)</th>`:''}
        </tr></thead>
        <tbody>${items.length?itemRows:`<tr><td colspan="${isIns?7:5}" style="padding:12px;text-align:center;color:var(--gray-400);">No items</td></tr>`}</tbody>
        <tfoot><tr style="border-top:2px solid var(--gray-200);background:var(--gray-50);font-weight:700;">
          <td colspan="4" style="padding:8px 12px;">Total</td>
          <td style="padding:8px 12px;text-align:right;">${total.toLocaleString()} RWF</td>
          ${isIns?`<td style="padding:8px 12px;text-align:right;color:#059669;background:#f0fdf4;">${Math.round(totalInsCovers).toLocaleString()} RWF</td><td style="padding:8px 12px;text-align:right;color:#2563eb;background:#eff6ff;">${Math.round(totalPatientPays).toLocaleString()} RWF</td>`:''}
        </tr></tfoot>
      </table>
    </div>`;
  }
  container.innerHTML=html;
  window.scrollTo({top:0,behavior:'smooth'});
}

paidLoad();

async function paidDownloadGrouped(){
  if(!paidCurrentVisitData) return;
  const visit=paidCurrentVisitData;
  let hospital={};
  try{const r=await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=settings/hospital');const j=await r.json();if(j.success)hospital=j.data||{};}catch(e){}
  // Load all items for all invoices
  let allItems=[];
  let grandTotal=0, grandPatientPaid=0, grandInsOwes=0;
  for(const inv of visit.invoices){
    let items=[];
    try{const r=await fetch('<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>/api.php?endpoint=patients/billing&id='+inv.id);const j=await r.json();if(j.success&&j.data&&j.data.items)items=j.data.items;}catch(e){}
    items.forEach(it=>{it._invoice_no=inv.invoice_no;allItems.push(it);});
    const invTotal=Math.round(parseFloat(inv.total||0));
    const copay=parseFloat(inv.copayment_percentage??100);
    const isIns=(inv.payment_type||inv.visit_payment_type||'').toLowerCase()==='insurance';
    grandTotal+=invTotal;
    const invPatShare=calculatePatientShare(inv);
    grandPatientPaid+=invPatShare;
    grandInsOwes+=isIns?Math.max(0, invTotal - invPatShare):0;
  }
  paidPreviewAndDownload(visit, allItems, grandTotal, grandPatientPaid, grandInsOwes, hospital);
}

function paidPreviewAndDownload(visit, items, grandTotal, grandPatientPaid, grandInsOwes, hospital){
  // Build invoice HTML
  const el = document.createElement('div');
  el.id='_pdf_preview_container';
  document.body.appendChild(el);
  paidGenerateGroupedPDF(visit, items, grandTotal, grandPatientPaid, grandInsOwes, hospital, el);
  const invoiceHtml = el.innerHTML;
  document.body.removeChild(el);

  // Show preview modal
  let modal = document.getElementById('paid-preview-modal');
  if(!modal){
    modal = document.createElement('div');
    modal.id = 'paid-preview-modal';
    modal.style.cssText = 'display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);z-index:99999;overflow-y:auto;padding:30px 20px;';
    modal.innerHTML = '<div style="max-width:860px;margin:0 auto;">'
      + '<div style="background:#1e293b;color:white;padding:12px 20px;border-radius:8px 8px 0 0;display:flex;justify-content:space-between;align-items:center;">'
      + '<span style="font-size:14px;font-weight:600;" id="paid-preview-title">Invoice Preview</span>'
      + '<div style="display:flex;gap:8px;">'
      + '<button id="paid-preview-dl-btn" style="padding:8px 18px;background:#16a34a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;"><i class="fas fa-print"></i> Print / Save PDF</button>'
      + '<button onclick="document.getElementById(\'paid-preview-modal\').style.display=\'none\'" style="padding:8px 14px;background:#6b7280;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;">✕ Close</button>'
      + '</div></div>'
      + '<div id="paid-preview-body" style="background:white;box-shadow:0 4px 20px rgba(0,0,0,.2);"></div>'
      + '</div>';
    document.body.appendChild(modal);
  }

  document.getElementById('paid-preview-title').textContent = 'Invoice — '+visit.patient_name+' | '+visit.visit_no;
  document.getElementById('paid-preview-body').innerHTML = invoiceHtml;

  // Wire up Print button — opens browser print dialog with invoice only
  const dlBtn = document.getElementById('paid-preview-dl-btn');
  dlBtn.onclick = function(){
    const printContent = document.getElementById('paid-preview-body').innerHTML;
    const printWin = window.open('', '_blank', 'width=900,height=700');
    printWin.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Invoice — ${visit.visit_no}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 6px 8px; border: 1px solid #ddd; }
    thead tr { background: #f0f0f0; }
    @media print {
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      @page { margin: 10mm; size: A4; }
    }
  </style>
</head>
<body>${printContent}</body>
</html>`);
    printWin.document.close();
    printWin.focus();
    setTimeout(()=>{ printWin.print(); printWin.close(); }, 500);
  };

  modal.style.display = 'block';
}

function paidGenerateGroupedPDF(visit, items, grandTotal, grandPatientPaid, grandInsOwes, hospital, previewContainer){
  const hs = hospital;
  const patientName=iEsc(visit.patient_name);
  const today = new Date().toISOString().split('T')[0];
  const hasIns = (visit.insurance && visit.insurance.toLowerCase() !== 'self-pay') || grandInsOwes > 0;
  const copayPct = parseFloat(visit.invoices && visit.invoices[0] && visit.invoices[0].copayment_percentage !== undefined ? visit.invoices[0].copayment_percentage : (hasIns ? 10 : 100));
  const coverPct = Math.max(0, 100 - copayPct);

  // Document settings from hospital config
  const docLogo = hs.doc_logo || hs.logo || '';
  const hLineColor = hs.header_line_color || '#059669';
  const hLineSize = hs.header_line_size || '2';
  const hTextColor = hs.header_text_color || '#1f2937';
  const hFontSize = hs.header_font_size || '11';
  const hLeftText = hs.header_left || hs.hospital_name || 'Hospital';
  const hRightText = (hs.header_right || 'Date: {date}').replace('{date}', visit.date||today).replace('{ref}', visit.visit_no||'');
  const fLineColor = hs.footer_line_color || '#059669';
  const fLineSize = hs.footer_line_size || '2';
  const fTextColor = hs.footer_text_color || '#1f2937';
  const fFontSize = hs.footer_font_size || '9';
  const fLeftText = hs.footer_left || (hs.address||'Kigali, Rwanda');
  const fRightText = hs.footer_right || (hs.phone?'Tel: '+hs.phone+' | ':'')+'Email: '+(hs.email||'');

  // Watermark
  let wmHtml = '';
  if(hs.watermark_enabled === '1'){
    const wmOpacity = (parseInt(hs.watermark_opacity)||15)/100;
    const wmRotation = hs.watermark_rotation || '-30';
    if(hs.watermark_type === 'image' && hs.watermark_image){
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;"><img src="${hs.watermark_image}" style="width:150px;height:auto;"></div>`;
    } else {
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;font-size:${hs.watermark_size||'40'}px;color:${hs.watermark_color||'#d1d5db'};font-weight:700;white-space:nowrap;">${iEsc(hs.watermark_text||hs.hospital_name||'')}</div>`;
    }
  }

  const hasExplicitOverrides = items && items.some(it => parseInt(it.apply_insurance || 0) > 0 || parseFloat(it.insurance_price || 0) > 0);

  const itemRows=invMergeLines(items).map((it,i)=>{
    const shares = calcInvoiceItemShares(it, hasIns, copayPct, hasExplicitOverrides);
    if (hasIns) {
      return `<tr style="page-break-inside:avoid;">
        <td style="padding:6px 8px;border:1px solid #ddd;">${i+1}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;word-wrap:break-word;max-width:200px;">${iEsc(it.description)}${it.service_code?' ('+iEsc(it.service_code)+')':''}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:center;">${it.category||'—'}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:center;">${shares.qty}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;white-space:nowrap;">${shares.unitPrice.toLocaleString()}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;white-space:nowrap;">${shares.lineTotal.toLocaleString()}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;white-space:nowrap;color:#059669;background:#f0fdf4;">${shares.insShare.toLocaleString()}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;white-space:nowrap;color:#2563eb;background:#eff6ff;">${Math.round(shares.patShare).toLocaleString()}</td>
      </tr>`;
    } else {
      return `<tr style="page-break-inside:avoid;">
        <td style="padding:6px 8px;border:1px solid #ddd;">${i+1}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;word-wrap:break-word;max-width:200px;">${iEsc(it.description)}${it.service_code?' ('+iEsc(it.service_code)+')':''}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:center;">${it.category||'—'}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:center;">${shares.qty}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;white-space:nowrap;">${shares.unitPrice.toLocaleString()}</td>
        <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;white-space:nowrap;">${shares.lineTotal.toLocaleString()}</td>
      </tr>`;
    }
  }).join('');

  const el=document.createElement('div');
  el.innerHTML=`<div style="font-family:Arial,sans-serif;font-size:12px;padding:20px;color:#333;width:100%;box-sizing:border-box;overflow-wrap:break-word;word-wrap:break-word;position:relative;overflow:hidden;min-height:1060px;">
    ${wmHtml}
    <div style="position:relative;z-index:1;min-height:1020px;display:flex;flex-direction:column;">
      <!-- Header -->
      <img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
      <div style="display:flex;justify-content:flex-end;margin:4px 0 15px;">
        <div style="font-size:${hFontSize}px;color:${hTextColor};text-align:right;">${iEsc(hRightText)}</div>
      </div>

      <div style="font-size:18px;font-weight:bold;text-align:center;margin:12px 0;text-decoration:underline;">INVOICE</div>
      <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
        <div><strong>Visit #:</strong> ${iEsc(visit.visit_no)}<br><strong>Date:</strong> ${iEsc(visit.date)}<br><strong>Invoices:</strong> ${visit.invoices.length}<br><strong>Insurance:</strong> ${iEsc(visit.insurance||'Self-pay')}<br><strong>Received By:</strong> ${iEsc(visit.received_by||'—')}</div>
        <div style="text-align:right;"><strong>Patient:</strong> ${patientName}<br><strong>Patient #:</strong> ${iEsc(visit.patient_no)}<br><strong>Status:</strong> <span style="color:#16a34a;font-weight:bold;">PAID</span></div>
      </div>

      <table style="width:100%;border-collapse:collapse;margin:12px 0;table-layout:fixed;">
        <thead><tr>
          <th style="background:#f0f0f0;padding:6px 8px;border:1px solid #ddd;text-align:left;width:25px;">#</th>
          <th style="background:#f0f0f0;padding:6px 8px;border:1px solid #ddd;text-align:left;">Description</th>
          <th style="background:#f0f0f0;padding:6px 8px;border:1px solid #ddd;text-align:center;width:65px;">Category</th>
          <th style="background:#f0f0f0;padding:6px 8px;border:1px solid #ddd;text-align:center;width:35px;">Qty</th>
          <th style="background:#f0f0f0;padding:6px 8px;border:1px solid #ddd;text-align:right;width:70px;">Price</th>
          <th style="background:#f0f0f0;padding:6px 8px;border:1px solid #ddd;text-align:right;width:70px;">Total</th>
          ${hasIns ? `<th style="background:#f0fdf4;padding:6px 8px;border:1px solid #ddd;text-align:right;width:75px;color:#059669;">Ins. (${coverPct}%)</th><th style="background:#eff6ff;padding:6px 8px;border:1px solid #ddd;text-align:right;width:75px;color:#2563eb;">Patient (${copayPct}%)</th>` : ''}
        </tr></thead>
        <tbody>${itemRows}</tbody>
      </table>

      <div style="margin-top:12px;text-align:right;page-break-inside:avoid;">
        <table style="margin-left:auto;border-collapse:collapse;font-size:12px;">
          <tr><td style="padding:3px 12px;text-align:right;"><strong>Grand Total:</strong></td><td style="padding:3px 12px;text-align:right;">${grandTotal.toLocaleString()} RWF</td></tr>
          <tr style="color:#2563eb;"><td style="padding:3px 12px;text-align:right;"><strong>Patient Share:</strong></td><td style="padding:3px 12px;text-align:right;">${grandPatientPaid.toLocaleString()} RWF</td></tr>
          ${grandInsOwes>0?`<tr style="color:#7c3aed;"><td style="padding:3px 12px;text-align:right;"><strong>Insurance Share:</strong></td><td style="padding:3px 12px;text-align:right;">${grandInsOwes.toLocaleString()} RWF</td></tr>`:''}
          <tr style="border-top:2px solid #16a34a;color:#16a34a;font-size:14px;"><td style="padding:6px 12px;text-align:right;"><strong>Collected from Patient:</strong></td><td style="padding:6px 12px;text-align:right;">${grandPatientPaid.toLocaleString()} RWF</td></tr>
        </table>
      </div>

      <!-- Signature & Stamp -->
      <div style="margin-top:50px;page-break-inside:avoid;">
        <div style="display:flex;justify-content:space-between;gap:40px;">
          <div style="flex:1;text-align:center;">
            <div style="border-bottom:1px solid #333;height:50px;margin-bottom:6px;"></div>
            <div style="font-size:11px;font-weight:600;color:#555;">Prepared By (Signature)</div>
          </div>
          <div style="flex:1;text-align:center;">
            <div style="border:1px dashed #999;height:50px;margin-bottom:6px;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:10px;">Stamp</div>
            <div style="font-size:11px;font-weight:600;color:#555;">Official Stamp</div>
          </div>
          <div style="flex:1;text-align:center;">
            <div style="border-bottom:1px solid #333;height:50px;margin-bottom:6px;"></div>
            <div style="font-size:11px;font-weight:600;color:#555;">Payment Received By: ${iEsc(visit.received_by||'Signature')}</div>
          </div>
        </div>
      </div>

      <!-- Footer at bottom of page -->
      <div style="margin-top:auto;padding-top:8px;border-top:${fLineSize}px solid ${fLineColor};display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${iEsc(fLeftText)}</span>
        <span style="font-size:${fFontSize}px;color:${fTextColor};">${iEsc(fRightText)}</span>
      </div>
    </div>
  </div>`;
  if(previewContainer){ previewContainer.innerHTML=el.innerHTML; return; }
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
