<?php
// includes/procurement_ui.php
// Procurement screens (included by procurement.php; $subModule picks the screen): suppliers + documents, evaluation, awards, contracts,
// expected deliveries, discrepancies, proforma invoices, supplier invoices, invoice exceptions and payment status.
// Data comes from api.php?endpoint=procurement/procurement-ops (new), inventory/inventory-management (RFQs, POs, suppliers) and
// procurement/procurement-ext (RFQs, contracts, supplier bills and payments) — see handlers/procurement/procurement-ops.php.
$pcSlugs = $_SESSION['user_data']['role_slugs'] ?? [];
$pcCanBill = (bool)array_intersect($pcSlugs, ['admin', 'accountant', 'procurement_officer']); // record bills and contracts
$pcCanPay = (bool)array_intersect($pcSlugs, ['admin', 'accountant']);                          // pay suppliers (stays with Finance)
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
.fo-btn.ok{background:#16a34a;}
.fo-btn.sm{padding:4px 10px;font-size:11px;}
.fo-btn:disabled{opacity:.5;cursor:not-allowed;}
.fo-tbl{width:100%;border-collapse:collapse;font-size:13px;}
.fo-tbl th{background:var(--gray-50);padding:9px 12px;text-align:left;font-weight:600;border-bottom:2px solid var(--gray-200);white-space:nowrap;}
.fo-tbl td{padding:9px 12px;border-bottom:1px solid var(--gray-100);vertical-align:top;}
.fo-tbl .num{text-align:right;font-variant-numeric:tabular-nums;}
.fo-wrap{border:1px solid var(--gray-200);border-radius:8px;overflow:auto;}
.fo-tag{display:inline-block;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;}
.fo-tag.ok{background:#dcfce7;color:#166534;}.fo-tag.warn{background:#fef3c7;color:#92400e;}.fo-tag.bad{background:#fee2e2;color:#991b1b;}.fo-tag.gray{background:var(--gray-100);color:var(--gray-600);}.fo-tag.info{background:#dbeafe;color:#1e40af;}
.fo-empty{padding:26px;text-align:center;color:var(--gray-400);}
.fo-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px;}
.fo-note{font-size:11px;color:var(--gray-500);margin-top:8px;}
.fo-stat{flex:1;min-width:140px;border:1px solid var(--gray-200);border-radius:8px;padding:10px 14px;cursor:default;}
.fo-stat b{display:block;font-size:20px;}.fo-stat span{font-size:11px;color:var(--gray-500);}
.fo-modal{position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;}
.fo-modal>div{background:white;border-radius:12px;width:440px;max-width:92vw;max-height:88vh;overflow:auto;padding:22px;}
</style>
<script>
const pcOps = _pBase + '/api.php?endpoint=procurement/procurement-ops';
const pcFin = _pBase + '/api.php?endpoint=procurement/procurement-ext'; // medlink: contracts, bills, payments and RFQ actions live in one procurement endpoint
const pcInv = _pBase + '/api.php?endpoint=inventory/inventory-management';
const PC_CAN_BILL = <?php echo json_encode($pcCanBill); ?>, PC_CAN_PAY = <?php echo json_encode($pcCanPay); ?>;
// api = one of the endpoints above; o.params = query string, o.body = JSON POST, o.form = FormData POST
async function pcReq(api, action, o){
  o = o || {};
  const qs = new URLSearchParams(Object.assign({action: action}, o.params || {}));
  const init = o.form ? {method:'POST', body:o.form} : (o.body ? {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(o.body)} : {});
  try { const r = await fetch(api + '&' + qs.toString(), init); return await r.json(); }
  catch(e){ return {success:false, message:'Request failed: ' + e.message}; }
}
async function pcAct(btn, api, action, body, okMsg){
  if(btn) btn.disabled = true;
  const j = await pcReq(api, action, body instanceof FormData ? {form: body} : {body: body});
  if(btn) btn.disabled = false;
  if(!j.success){ alert(j.message || 'Failed'); return null; }
  if(okMsg) alert(okMsg === true ? (j.message || 'Done') : okMsg);
  return j;
}
function pcEsc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function pcMoney(v){return Number(v||0).toLocaleString(undefined,{maximumFractionDigits:2});}
function pcToday(){return new Date().toISOString().slice(0,10);}
function pcOpts(list, valKey, labelFn, selected){return list.map(x=>`<option value="${pcEsc(x[valKey])}" ${String(x[valKey])===String(selected)?'selected':''}>${pcEsc(labelFn(x))}</option>`).join('');}
function pcTag(text, kind){return `<span class="fo-tag ${kind||'gray'}">${pcEsc(text)}</span>`;}
function pcModal(html){const m=document.createElement('div');m.className='fo-modal';m.innerHTML='<div>'+html+'</div>';m.addEventListener('click',e=>{if(e.target===m)m.remove();});document.body.appendChild(m);return m;}
function pcClose(el){el.closest('.fo-modal').remove();}
const PC_SUP_KIND = {active:'ok', inactive:'gray', suspended:'warn', blacklisted:'bad'};
</script>

<?php if ($subModule == 'suppliers'): ?>
<!-- ====== SUPPLIERS: list, status (active / inactive / suspended / blacklisted) ====== -->
<div class="fo-card">
  <div class="fo-row">
    <h3 style="margin:0;"><i class="fas fa-truck"></i> Suppliers</h3>
    <select class="fo-in" style="width:auto;margin-left:auto;" id="sup-filter" onchange="supRender()"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option><option value="blacklisted">Blacklisted</option></select>
    <input class="fo-in" style="width:200px;" id="sup-q" placeholder="Search supplier…" oninput="supRender()">
    <button class="fo-btn" onclick="supNew()"><i class="fas fa-plus"></i> Add Supplier</button>
  </div>
  <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Supplier</th><th>Contact</th><th>TIN</th><th>Category</th><th>Documents</th><th>Status</th><th></th></tr></thead><tbody id="sup-tbody"><tr><td colspan="7" class="fo-empty">Loading…</td></tr></tbody></table></div>
  <p class="fo-note">Only active suppliers can be invited to bid, awarded, or sent a purchase order. A suspended or blacklisted supplier still shows when we owe them money, so they can be paid.</p>
</div>
<script>
let supRows = [];
async function supLoad(){ const j = await pcReq(pcOps, 'suppliers_all'); supRows = j.data || []; supRender(); }
function supRender(){
  const f = document.getElementById('sup-filter').value, q = document.getElementById('sup-q').value.trim().toLowerCase();
  const rows = supRows.filter(s => (!f || s.status === f) && (!q || (s.company_name + ' ' + (s.supplier_code||'') + ' ' + (s.tax_id||'')).toLowerCase().includes(q)));
  document.getElementById('sup-tbody').innerHTML = rows.length ? rows.map(s=>`<tr>
    <td style="font-weight:600;">${pcEsc(s.company_name)}<div style="font-size:11px;font-weight:400;color:var(--gray-500);">${pcEsc(s.supplier_code||'')}</div></td>
    <td style="font-size:12px;">${pcEsc(s.contact_person||'—')}<div style="color:var(--gray-500);">${pcEsc([s.phone, s.email].filter(Boolean).join(' · '))}</div></td>
    <td>${pcEsc(s.tax_id||'—')}</td><td style="font-size:12px;">${pcEsc(s.category||'—')}</td>
    <td><a href="procurement.php?module=procurement&sub=supplier-docs&supplier=${s.id}" style="font-size:12px;">${s.docs} file${s.docs==1?'':'s'}</a>${+s.docs_expired>0?' '+pcTag(s.docs_expired+' expired','bad'):''}</td>
    <td>${pcTag(s.status, PC_SUP_KIND[s.status])}${s.status_reason?`<div style="font-size:11px;color:var(--gray-500);max-width:200px;">${pcEsc(s.status_reason)}</div>`:''}</td>
    <td style="white-space:nowrap;"><button class="fo-btn alt sm" onclick="supEdit(${s.id})"><i class="fas fa-edit"></i> Edit</button> <button class="fo-btn alt sm" onclick="supStatus(${s.id})">Change status</button> <button class="fo-btn danger sm" onclick="supDelete(${s.id})"><i class="fas fa-trash"></i> Delete</button></td></tr>`).join('') : '<tr><td colspan="7" class="fo-empty">No suppliers match.</td></tr>';
}
function supStatus(id){
  const s = supRows.find(x=>+x.id===id); if(!s) return;
  const m = pcModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Supplier status</h3><div style="color:var(--gray-600);font-size:13px;">${pcEsc(s.company_name)} — now ${pcEsc(s.status)}</div>
    <label class="fo-lbl">New status</label><select class="fo-in" id="ss-status">${['active','inactive','suspended','blacklisted'].map(x=>`<option value="${x}" ${x===s.status?'selected':''}>${x[0].toUpperCase()+x.slice(1)}</option>`).join('')}</select>
    <label class="fo-lbl">Reason <span id="ss-req" style="color:#dc2626;">(required unless active)</span></label><textarea class="fo-in" id="ss-reason" rows="3" placeholder="e.g. delivered expired medicines on 3 orders">${pcEsc(s.status_reason||'')}</textarea>
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="ss-go" style="flex:1;">Save status</button><button class="fo-btn alt" onclick="pcClose(this)">Cancel</button></div>`);
  m.querySelector('#ss-go').onclick = async function(){
    if(await pcAct(this, pcOps, 'supplier_status', {id:id, status:m.querySelector('#ss-status').value, reason:m.querySelector('#ss-reason').value}, true)){ m.remove(); supLoad(); }
  };
}
function supNew(){
  const f = (id,label,ph) => `<div><label class="fo-lbl">${label}</label><input class="fo-in" id="${id}" placeholder="${ph||''}"></div>`;
  const m = pcModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Add supplier</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 12px;">${f('sn-name','Company *')}${f('sn-contact','Contact person')}${f('sn-phone','Phone')}${f('sn-email','Email')}${f('sn-tin','TIN (tax number)')}${f('sn-terms','Payment terms','Net 30')}</div>
    <label class="fo-lbl">Category</label><select class="fo-in" id="sn-cat"><option value="pharmaceutical">Pharmaceutical</option><option value="medical-equipment">Medical equipment</option><option value="laboratory">Laboratory</option><option value="other">Other</option></select>
    <label class="fo-lbl">Address</label><input class="fo-in" id="sn-addr">
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="sn-go" style="flex:1;">Save</button><button class="fo-btn alt" onclick="pcClose(this)">Cancel</button></div>
    <p class="fo-note">Documents (registration, licence, bank details) are added on the Documents &amp; Certificates tab.</p>`);
  m.querySelector('#sn-go').onclick = async function(){
    const v = id => m.querySelector('#'+id).value.trim();
    if(!v('sn-name')) return alert('Company name is required.');
    const j = await pcAct(this, pcInv, 'create_supplier', {company_name:v('sn-name'), contact_person:v('sn-contact'), phone:v('sn-phone'), email:v('sn-email'), tax_id:v('sn-tin'), payment_terms:v('sn-terms'), category:v('sn-cat'), address:v('sn-addr')}, 'Supplier added');
    if(j){ m.remove(); supLoad(); }
  };
}
// update_supplier needs PUT and delete_supplier needs DELETE (inventory-management.php); delete only sets status=inactive.
async function supCall(method, action, id, body){
  try {
    const r = await fetch(pcInv + '&action=' + action + '&id=' + id, {method:method, headers:{'Content-Type':'application/json'}, body: body ? JSON.stringify(body) : undefined});
    const j = await r.json();
    if(!j.success){ alert(j.message || 'Failed'); return null; }
    return j;
  } catch(e){ alert('Request failed: ' + e.message); return null; }
}
function supEdit(id){
  const s = supRows.find(x=>+x.id===id); if(!s) return;
  const f = (k,label) => `<div><label class="fo-lbl">${label}</label><input class="fo-in" id="se-${k}" value="${pcEsc(s[k]||'')}"></div>`;
  const cats = ['pharmaceutical','medical-equipment','laboratory','other'];
  const m = pcModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Edit supplier</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 12px;">${f('company_name','Company *')}${f('contact_person','Contact person')}${f('phone','Phone')}${f('email','Email')}${f('tax_id','TIN (tax number)')}</div>
    <label class="fo-lbl">Category</label><select class="fo-in" id="se-category">${cats.concat(s.category && !cats.includes(s.category) ? [s.category] : []).map(c=>`<option value="${pcEsc(c)}" ${c===s.category?'selected':''}>${pcEsc(c)}</option>`).join('')}</select>
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="se-go" style="flex:1;">Save changes</button><button class="fo-btn alt" onclick="pcClose(this)">Cancel</button></div>`);
  m.querySelector('#se-go').onclick = async function(){
    const v = k => m.querySelector('#se-'+k).value.trim();
    if(!v('company_name')) return alert('Company name is required.');
    this.disabled = true;
    const j = await supCall('PUT', 'update_supplier', id, {company_name:v('company_name'), contact_person:v('contact_person'), phone:v('phone'), email:v('email'), tax_id:v('tax_id'), category:v('category')});
    this.disabled = false;
    if(j){ m.remove(); supLoad(); }
  };
}
async function supDelete(id){
  const s = supRows.find(x=>+x.id===id); if(!s) return;
  if(!confirm('Delete "' + s.company_name + '"?\n\nIt is set to inactive, not erased, so past orders and bills keep their supplier.')) return;
  if(await supCall('DELETE', 'delete_supplier', id)) supLoad();
}
supLoad();
</script>

<?php elseif ($subModule == 'supplier-docs'): ?>
<!-- ====== SUPPLIER DOCUMENTS & CERTIFICATES ====== -->
<div class="fo-grid">
  <div class="fo-card">
    <h3><i class="fas fa-file-upload"></i> Add a document</h3>
    <label class="fo-lbl">Supplier</label><select class="fo-in" id="sd-sup"></select>
    <label class="fo-lbl">Type</label><select class="fo-in" id="sd-type"><option>Business registration</option><option>Tax (TIN) certificate</option><option>Licence / certificate</option><option>Bank details</option><option>Quotation</option><option>Contract</option><option>Other</option></select>
    <label class="fo-lbl">Title</label><input class="fo-in" id="sd-title" placeholder="e.g. Pharmacy licence 2026">
    <label class="fo-lbl">Expiry date <span style="font-weight:400;">(if it has one)</span></label><input class="fo-in" type="date" id="sd-exp">
    <label class="fo-lbl">File <span style="font-weight:400;">(PDF, image, Word or Excel, up to 10 MB)</span></label><input class="fo-in" type="file" id="sd-file">
    <button class="fo-btn" style="margin-top:14px;width:100%;" onclick="sdSave(this)"><i class="fas fa-check"></i> Save document</button>
  </div>
  <div class="fo-card">
    <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-folder-open"></i> Documents &amp; certificates</h3>
      <select class="fo-in" style="width:auto;margin-left:auto;" id="sd-filter" onchange="sdLoad()"><option value="">All suppliers</option></select></div>
    <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Supplier</th><th>Document</th><th>Expiry</th><th>Verified</th><th></th></tr></thead><tbody id="sd-tbody"></tbody></table></div>
    <p class="fo-note">Expired or expiring documents show on the supplier list. “Verify” records that someone has checked the original.</p>
  </div>
</div>
<script>
let sdSups = [];
async function sdInit(){
  const j = await pcReq(pcOps, 'suppliers_all'); sdSups = j.data || [];
  const want = new URLSearchParams(location.search).get('supplier') || '';
  document.getElementById('sd-sup').innerHTML = pcOpts(sdSups, 'id', s=>s.company_name + (s.status==='active'?'':' ('+s.status+')'), want);
  document.getElementById('sd-filter').innerHTML = '<option value="">All suppliers</option>' + pcOpts(sdSups, 'id', s=>s.company_name, want);
  sdLoad();
}
async function sdLoad(){
  const sid = document.getElementById('sd-filter').value;
  const j = await pcReq(pcOps, 'supplier_documents', {params:{supplier_id: sid}});
  const st = {valid:['Valid','ok'], expiring:['Expiring soon','warn'], expired:['Expired','bad'], no_expiry:['No expiry','gray']};
  document.getElementById('sd-tbody').innerHTML = (j.data||[]).length ? j.data.map(d=>`<tr>
    <td>${pcEsc(d.company_name)}</td>
    <td><b>${pcEsc(d.title)}</b><div style="font-size:11px;color:var(--gray-500);">${pcEsc(d.doc_type)} · <a href="${pcOps}&action=supplier_document_file&id=${d.id}" target="_blank" rel="noopener">${pcEsc(d.original_name)}</a></div></td>
    <td>${pcEsc(d.expiry_date||'—')} ${pcTag(st[d.state][0], st[d.state][1])}</td>
    <td>${d.verified_at ? pcTag('Verified','ok')+'<div style="font-size:11px;color:var(--gray-500);">'+pcEsc((d.verified_by_name||'')+' · '+d.verified_at.slice(0,10))+'</div>' : '<button class="fo-btn alt sm" onclick="sdVerify('+d.id+',this)">Verify</button>'}</td>
    <td><button class="fo-btn danger sm" onclick="sdDelete(${d.id},this)">Remove</button></td></tr>`).join('') : '<tr><td colspan="5" class="fo-empty">No documents yet.</td></tr>';
}
async function sdSave(btn){
  const f = document.getElementById('sd-file').files[0];
  if(!f) return alert('Choose the document file.');
  const fd = new FormData();
  fd.append('supplier_id', document.getElementById('sd-sup').value); fd.append('doc_type', document.getElementById('sd-type').value);
  fd.append('title', document.getElementById('sd-title').value); fd.append('expiry_date', document.getElementById('sd-exp').value); fd.append('file', f);
  if(await pcAct(btn, pcOps, 'supplier_document_upload', fd, 'Document saved')){ ['sd-title','sd-exp','sd-file'].forEach(id=>document.getElementById(id).value=''); sdLoad(); }
}
async function sdVerify(id, btn){ if(await pcAct(btn, pcOps, 'supplier_document_verify', {id:id}, true)) sdLoad(); }
async function sdDelete(id, btn){ if(!confirm('Remove this document?')) return; if(await pcAct(btn, pcOps, 'supplier_document_delete', {id:id}, true)) sdLoad(); }
sdInit();
</script>

<?php elseif ($subModule == 'evaluation'): ?>
<!-- ====== EVALUATION: score each bid ====== -->
<div class="fo-card">
  <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-clipboard-check"></i> Bid evaluation</h3>
    <select class="fo-in" style="width:auto;min-width:320px;margin-left:auto;" id="ev-rfq" onchange="evLoad()"></select></div>
  <div id="ev-body"><div class="fo-empty">Choose an RFQ to evaluate.</div></div>
  <p class="fo-note">Price score is worked out automatically (lowest bid = 100) and moves when a cheaper quotation arrives. Total = price 40% + technical 40% + delivery 20%. Scores lock once an award is recorded. The person who evaluates cannot make the award when segregation of duties is on.</p>
</div>
<script>
let evData = null;
async function evInit(){
  const j = await pcReq(pcFin, 'rfqs');
  const rows = (j.data||[]).filter(r=>['published','closed'].includes(r.status));
  const want = new URLSearchParams(location.search).get('rfq') || '';
  document.getElementById('ev-rfq').innerHTML = '<option value="">— choose an RFQ —</option>' + pcOpts(rows, 'id', r=>`${r.rfq_number} · ${r.title} (${r.quotation_count} bid${r.quotation_count==1?'':'s'})`, want);
  if(want) evLoad();
}
async function evLoad(){
  const id = document.getElementById('ev-rfq').value, box = document.getElementById('ev-body');
  if(!id){ box.innerHTML = '<div class="fo-empty">Choose an RFQ to evaluate.</div>'; return; }
  const j = await pcReq(pcFin, 'rfq_detail', {params:{id:id}});
  if(!j.success){ box.innerHTML = `<div class="fo-empty">${pcEsc(j.message)}</div>`; return; }
  const x = evData = j.data, locked = (x.awards||[]).length > 0;
  const qs = x.quotations || [];
  const best = Math.max(...qs.map(q=>q.total_score==null?-1:+q.total_score));
  box.innerHTML = `<div style="font-size:13px;margin-bottom:10px;"><b>${pcEsc(x.rfq_number)}</b> ${pcEsc(x.title)} · closing ${pcEsc(x.closing_date||'—')} ${pcTag(x.status, 'info')}${locked?' '+pcTag('Scores locked: award recorded','warn'):''}</div>
    <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Supplier</th><th>Quotation</th><th class="num">Amount (RWF)</th><th class="num">Days</th><th>Technical (0-100)</th><th>Delivery (0-100)</th><th class="num">Price</th><th class="num">Total</th><th>Recommendation</th><th></th></tr></thead><tbody>
    ${qs.length ? qs.map(q=>{
      const can = !locked && ['submitted','shortlisted'].includes(q.status);
      return `<tr><td><b>${pcEsc(q.company_name)}</b><div style="font-size:11px;color:var(--gray-500);">${pcEsc(q.status)}${q.valid_until?' · valid to '+pcEsc(q.valid_until):''}</div></td><td>${pcEsc(q.quotation_number)}</td><td class="num">${pcMoney(q.total_amount)}</td><td class="num">${q.delivery_days||'—'}</td>
      <td><input class="fo-in ev-t" style="width:80px;" type="number" min="0" max="100" value="${q.technical_score==null?'':+q.technical_score}" ${can?'':'disabled'}></td>
      <td><input class="fo-in ev-d" style="width:80px;" type="number" min="0" max="100" value="${q.delivery_score==null?'':+q.delivery_score}" ${can?'':'disabled'}></td>
      <td class="num">${q.price_score==null?'—':pcMoney(q.price_score)}</td>
      <td class="num" style="font-weight:700;">${q.total_score==null?'—':pcMoney(q.total_score)}${q.total_score!=null && +q.total_score===best && qs.length>1?' '+pcTag('Highest','ok'):''}</td>
      <td><input class="fo-in ev-r" style="min-width:160px;" value="${pcEsc(q.recommendation||'')}" ${can?'':'disabled'}></td>
      <td><button class="fo-btn sm" ${can?'':'disabled'} onclick="evSave(${q.id}, this)">Save</button></td></tr>`;}).join('') : '<tr><td colspan="10" class="fo-empty">No quotations recorded yet. Record them from RFQs &amp; Quotations.</td></tr>'}
    </tbody></table></div>`;
}
async function evSave(qid, btn){
  const tr = btn.closest('tr');
  const body = {quotation_id:qid, technical_score:tr.querySelector('.ev-t').value, delivery_score:tr.querySelector('.ev-d').value, recommendation:tr.querySelector('.ev-r').value};
  if(body.technical_score==='' || body.delivery_score==='') return alert('Enter both the technical and the delivery score.');
  if(await pcAct(btn, pcFin, 'evaluate_quotation', body, 'Evaluation saved')) evLoad();
}
evInit();
</script>

<?php elseif ($subModule == 'awards'): ?>
<!-- ====== AWARDS: provisional -> standstill -> final ====== -->
<div class="fo-card">
  <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-award"></i> Supplier selection &amp; award</h3><button class="fo-btn alt" style="margin-left:auto;" onclick="awInit()"><i class="fas fa-sync-alt"></i></button></div>
  <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>RFQ</th><th>Title</th><th>Closing</th><th class="num">Bids</th><th>Status</th><th>Provisional notice</th><th>Standstill ends</th><th>Awarded to</th><th></th></tr></thead><tbody id="aw-tbody"></tbody></table></div>
</div>
<div class="fo-card" id="aw-detail" style="display:none;"></div>
<script>
let awRfq = null;
async function awInit(first){
  const j = await pcReq(pcFin, 'rfqs');
  const rows = (j.data||[]).filter(r=>r.status!=='draft' && r.status!=='cancelled');
  const kind = {published:'info', closed:'warn', awarded:'ok'};
  document.getElementById('aw-tbody').innerHTML = rows.length ? rows.map(r=>`<tr><td style="font-family:monospace;">${pcEsc(r.rfq_number)}</td><td>${pcEsc(r.title)}</td><td>${pcEsc(r.closing_date||'—')}</td><td class="num">${r.quotation_count}</td><td>${pcTag(r.status, kind[r.status])}</td>
    <td>${r.provisional_notified_at?pcEsc(r.provisional_notified_at.slice(0,10)):'—'}</td><td>${pcEsc(r.standstill_end||'—')}</td><td>${pcEsc(r.awarded_supplier_name||'—')}</td>
    <td><button class="fo-btn alt sm" onclick="awOpen(${r.id})">Open</button></td></tr>`).join('') : '<tr><td colspan="9" class="fo-empty">No RFQs yet.</td></tr>';
  const want = first && new URLSearchParams(location.search).get('rfq'); if(want) awOpen(+want);
}
async function awOpen(id){
  const j = await pcReq(pcFin, 'rfq_detail', {params:{id:id}});
  if(!j.success) return alert(j.message);
  const x = awRfq = j.data, box = document.getElementById('aw-detail'), done = x.status === 'awarded';
  const prov = (x.awards||[]).filter(a=>a.award_type==='provisional').slice(-1)[0];
  const stand = x.standstill_end, standOver = stand && pcToday() > stand;
  const qs = x.quotations || [];
  box.style.display = 'block';
  box.innerHTML = `<h3><i class="fas fa-gavel"></i> ${pcEsc(x.rfq_number)} · ${pcEsc(x.title)} ${pcTag(x.status, done?'ok':'info')}</h3>
    <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th></th><th>Supplier</th><th>Quotation</th><th class="num">Amount (RWF)</th><th class="num">Total score</th><th>Status</th></tr></thead><tbody>
    ${qs.length ? qs.map(q=>{ const ok = q.total_score!=null && ['submitted','shortlisted'].includes(q.status) && !done;
      return `<tr><td><input type="radio" name="aw-q" value="${q.id}" ${ok?'':'disabled'} ${prov&&+prov.quotation_id===+q.id&&ok?'checked':''}></td><td><b>${pcEsc(q.company_name)}</b></td><td>${pcEsc(q.quotation_number)}</td><td class="num">${pcMoney(q.total_amount)}</td>
      <td class="num">${q.total_score==null?pcTag('Not evaluated','warn'):pcMoney(q.total_score)}</td><td>${pcEsc(q.status)}</td></tr>`;}).join('') : '<tr><td colspan="6" class="fo-empty">No quotations.</td></tr>'}
    </tbody></table></div>
    ${done ? '' : `<label class="fo-lbl">Decision note <span style="font-weight:400;">(required when the chosen bidder did not score highest)</span></label><textarea class="fo-in" id="aw-note" rows="2">${pcEsc(prov?prov.decision_note||'':'')}</textarea>
    <div class="fo-row" style="margin-top:12px;"><button class="fo-btn" onclick="awDo('provisional', this)">Issue provisional award</button>
      <button class="fo-btn ok" onclick="awDo('final', this)" ${prov && standOver ? '' : 'disabled'}>Issue final award</button>
      <span class="fo-note" style="margin:0;">${!prov ? 'Issue a provisional award first; the final award follows the standstill period.' : (standOver ? 'Standstill period is over: the final award can be made.' : 'Standstill (clarification / objection period) runs until '+pcEsc(stand)+'.')}</span></div>`}
    <h3 style="margin-top:18px;font-size:14px;">Award notices</h3>
    <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Type</th><th>Supplier</th><th>Date</th><th>Notice issued</th><th>Decision note</th></tr></thead><tbody>
    ${(x.awards||[]).length ? x.awards.map(a=>`<tr><td>${pcTag(a.award_type, a.award_type==='final'?'ok':'warn')}</td><td>${pcEsc(a.company_name)}</td><td>${pcEsc(a.award_date)}</td><td>${pcEsc((a.notified_at||'').slice(0,16))}</td><td>${pcEsc(a.decision_note||'—')}</td></tr>`).join('') : '<tr><td colspan="5" class="fo-empty">No award notices yet.</td></tr>'}
    </tbody></table></div>`;
  box.scrollIntoView({behavior:'smooth', block:'nearest'});
}
async function awDo(type, btn){
  const q = document.querySelector('input[name=aw-q]:checked');
  if(!q) return alert('Choose the bidder to award.');
  if(type==='final' && !confirm('Issue the final award? The other quotations will be marked rejected.')) return;
  const j = await pcAct(btn, pcFin, 'award_rfq', {rfq_id:awRfq.id, quotation_id:+q.value, award_type:type, decision_note:document.getElementById('aw-note').value}, true);
  if(j){ awInit(); awOpen(awRfq.id); }
}
awInit(true);
</script>

<?php elseif ($subModule == 'contracts'): ?>
<!-- ====== SUPPLIER CONTRACTS & COMMITMENTS (moved from Finance) ====== -->
<div class="fo-grid">
  <?php if ($pcCanBill): ?>
  <div class="fo-card">
    <h3><i class="fas fa-file-signature"></i> <span id="ct-title">Record a signed contract</span></h3>
    <input type="hidden" id="ct-id">
    <label class="fo-lbl">Supplier</label><select class="fo-in" id="ct-sup"></select>
    <label class="fo-lbl">Title</label><input class="fo-in" id="ct-name" placeholder="e.g. Laboratory reagents 2026">
    <label class="fo-lbl">Contract no.</label><input class="fo-in" id="ct-no">
    <label class="fo-lbl">Total value (RWF)</label><input class="fo-in" type="number" step="0.01" id="ct-value">
    <label class="fo-lbl">Start</label><input class="fo-in" type="date" id="ct-start">
    <label class="fo-lbl">End</label><input class="fo-in" type="date" id="ct-end">
    <label class="fo-lbl">Status</label><select class="fo-in" id="ct-status"><option value="active">Active</option><option value="closed">Closed</option><option value="cancelled">Cancelled</option></select>
    <div style="display:flex;gap:8px;margin-top:14px;"><button class="fo-btn" style="flex:1;" onclick="ctSave(this)"><i class="fas fa-check"></i> Save</button><button class="fo-btn alt" onclick="ctReset()">Clear</button></div>
    <p class="fo-note">A contract is a commitment, not a debt: nothing is posted. What is left (value less bills received inside its dates) is disclosed in the Notes to Accounts.</p>
  </div>
  <?php endif; ?>
  <div class="fo-card" style="<?php echo $pcCanBill ? '' : 'grid-column:1/-1;'; ?>">
    <h3><i class="fas fa-list"></i> Contracts</h3>
    <div id="ct-alert" style="display:none;margin-bottom:10px;padding:10px 12px;border-radius:8px;font-size:12px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Supplier</th><th>Contract</th><th>Period</th><th>Expiry</th><th class="num">Value</th><th class="num">Billed</th><th class="num">Commitment left</th><th></th></tr></thead><tbody id="ct-tbody"></tbody></table></div>
  </div>
</div>
<script>
let ctRows = [], ctSups = [];
const ctCan = PC_CAN_BILL;
function ctFillSup(keep){
  const el = document.getElementById('ct-sup'); if(!el) return;
  el.innerHTML = pcOpts(ctSups.filter(s=>s.status==='active' || String(s.id)===String(keep)), 'id', s=>s.company_name + (s.status==='active'?'':' ('+s.status+')'), keep);
}
async function ctLoad(){
  const [j, s] = await Promise.all([pcReq(pcFin, 'contracts'), pcReq(pcOps, 'suppliers_all')]);
  ctSups = s.data || []; ctFillSup(document.getElementById('ct-sup') ? document.getElementById('ct-sup').value : '');
  ctRows = j.data || [];
  const ctBadge = c => {
    if(c.expiry_state==='expired') return pcTag('Expired '+Math.abs(c.days_left)+'d ago','bad');
    if(c.expiry_state==='expiring') return pcTag(c.days_left+'d left','warn');
    if(c.expiry_state==='open') return pcTag('Open-ended','info');
    if(c.expiry_state==='inactive') return pcTag(c.status,'gray');
    return pcTag(c.days_left+'d left','ok');
  };
  const expiring = ctRows.filter(c=>c.expiry_state==='expired'||c.expiry_state==='expiring');
  const alertEl = document.getElementById('ct-alert');
  if(expiring.length){
    alertEl.style.display = 'block';
    alertEl.innerHTML = '<i class="fas fa-triangle-exclamation"></i> ' + expiring.map(c=>`${pcEsc(c.company_name)} — ${pcEsc(c.contract_no||c.title)} ${c.expiry_state==='expired'?'expired on '+pcEsc(c.end_date):'expires in '+c.days_left+' day(s)'}`).join(' · ') + '. Expired contracts block new purchase orders and final awards.';
  } else alertEl.style.display = 'none';
  document.getElementById('ct-tbody').innerHTML = ctRows.length ? ctRows.map(c=>`<tr style="${c.status!=='active'?'opacity:.55;':''}"><td>${pcEsc(c.company_name)}</td><td>${pcEsc(c.title)}<div style="font-size:11px;color:var(--gray-500);">${pcEsc(c.contract_no||'')} · ${pcEsc(c.status)}</div></td>
    <td style="font-size:12px;">${pcEsc(c.start_date)} → ${pcEsc(c.end_date||'open')}</td><td>${ctBadge(c)}</td><td class="num">${pcMoney(c.value)}</td><td class="num">${pcMoney(c.billed)}</td><td class="num" style="font-weight:600;">${pcMoney(c.remaining)}</td>
    <td>${ctCan?`<button class="fo-btn alt sm" onclick="ctEdit(${c.id})">Edit</button>`:''}</td></tr>`).join('') : '<tr><td colspan="8" class="fo-empty">No contracts yet.</td></tr>';
}
function ctEdit(id){
  const c = ctRows.find(x=>+x.id===id); if(!c) return;
  document.getElementById('ct-title').textContent = 'Edit contract';
  ctFillSup(c.supplier_id);
  [['ct-id','id'],['ct-sup','supplier_id'],['ct-name','title'],['ct-no','contract_no'],['ct-value','value'],['ct-start','start_date'],['ct-end','end_date'],['ct-status','status']].forEach(([el,k])=>document.getElementById(el).value = c[k]||'');
  window.scrollTo({top:0, behavior:'smooth'});
}
function ctReset(){
  ['ct-id','ct-name','ct-no','ct-value','ct-end'].forEach(id=>document.getElementById(id).value=''); document.getElementById('ct-status').value='active';
  document.getElementById('ct-start').value = pcToday(); document.getElementById('ct-title').textContent = 'Record a signed contract'; ctFillSup('');
}
async function ctSave(btn){
  const v = id => document.getElementById(id).value;
  if(await pcAct(btn, pcFin, 'contract_save', {id:v('ct-id'), supplier_id:v('ct-sup'), title:v('ct-name'), contract_no:v('ct-no'), value:v('ct-value'), start_date:v('ct-start'), end_date:v('ct-end'), status:v('ct-status')}, true)){ ctReset(); ctLoad(); }
}
if(ctCan) document.getElementById('ct-start').value = pcToday();
ctLoad();
</script>

<?php elseif ($subModule == 'expected-deliveries'): ?>
<!-- ====== EXPECTED DELIVERIES: approved POs still to arrive ====== -->
<div class="fo-card">
  <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-truck-loading"></i> Expected deliveries</h3><button class="fo-btn alt" style="margin-left:auto;" onclick="edLoad()"><i class="fas fa-sync-alt"></i></button></div>
  <div class="fo-row" id="ed-stats"></div>
  <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>PO</th><th>Supplier</th><th>Expected</th><th>Delivery</th><th>Received</th><th class="num">PO total</th><th></th></tr></thead><tbody id="ed-tbody"></tbody></table></div>
  <p class="fo-note">Shows approved purchase orders that are not fully received. Use Track to record the carrier, a new expected date or a delay; goods are booked in with Receive.</p>
</div>
<script>
let edRows = [], edFilter = '';
const ED_STATE = {overdue:['Overdue','bad'], due_soon:['Due within 7 days','warn'], on_track:['On track','ok'], no_date:['No date set','gray']};
async function edLoad(){
  const j = await pcReq(pcOps, 'expected_deliveries'); edRows = j.data || [];
  const cnt = k => edRows.filter(r=>r.state===k).length;
  document.getElementById('ed-stats').innerHTML = [['','All open',edRows.length],['overdue','Overdue',cnt('overdue')],['due_soon','Due within 7 days',cnt('due_soon')],['on_track','On track',cnt('on_track')],['no_date','No date set',cnt('no_date')]]
    .map(([k,l,n])=>`<div class="fo-stat" style="cursor:pointer;${edFilter===k?'border-color:var(--primary-color);':''}" onclick="edFilter='${k}';edRender()"><b>${n}</b><span>${l}</span></div>`).join('');
  edRender();
}
function edRender(){
  const rows = edRows.filter(r=>!edFilter || r.state===edFilter);
  const dTag = {pending:'gray', in_transit:'info', delayed:'bad'};
  document.getElementById('ed-tbody').innerHTML = rows.length ? rows.map(r=>{
    const pct = +r.ordered_qty > 0 ? Math.round(100*r.received_qty/r.ordered_qty) : 0;
    return `<tr><td style="font-family:monospace;">${pcEsc(r.po_number)}<div style="font-size:11px;color:var(--gray-500);">ordered ${pcEsc(r.order_date||'')}</div></td><td>${pcEsc(r.supplier_name||'—')}</td>
    <td>${pcEsc(r.expected_date||'—')}<div>${pcTag(ED_STATE[r.state][0], ED_STATE[r.state][1])}${r.state==='overdue'?' <span style="font-size:11px;color:#b91c1c;">'+Math.abs(r.days_left)+'d late</span>':''}</div></td>
    <td>${pcTag((r.delivery_status||'pending').replace('_',' '), dTag[r.delivery_status]||'gray')}${r.carrier?'<div style="font-size:11px;color:var(--gray-500);">'+pcEsc(r.carrier+(r.tracking_ref?' · '+r.tracking_ref:''))+'</div>':''}${r.delivery_note?'<div style="font-size:11px;color:var(--gray-500);">'+pcEsc(r.delivery_note)+'</div>':''}</td>
    <td>${pct}% <span style="font-size:11px;color:var(--gray-500);">(${+r.received_qty} of ${+r.ordered_qty})</span></td><td class="num">${pcMoney(r.total_amount)}</td>
    <td style="white-space:nowrap;"><button class="fo-btn alt sm" onclick="edTrack(${r.id})">Track</button> <button class="fo-btn ok sm" onclick="location.href='inventory.php?module=inventory&sub=goods-receiving&receive=${r.id}'">Receive</button></td></tr>`;}).join('') : '<tr><td colspan="7" class="fo-empty">No open deliveries here.</td></tr>';
}
function edTrack(id){
  const r = edRows.find(x=>+x.id===id); if(!r) return;
  const m = pcModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Track delivery · ${pcEsc(r.po_number)}</h3>
    <label class="fo-lbl">Delivery status</label><select class="fo-in" id="et-st"><option value="pending">Pending</option><option value="in_transit">In transit</option><option value="delayed">Delayed</option></select>
    <label class="fo-lbl">Expected delivery date</label><input class="fo-in" type="date" id="et-date" value="${pcEsc(r.expected_date||'')}">
    <label class="fo-lbl">Carrier / transporter</label><input class="fo-in" id="et-car" value="${pcEsc(r.carrier||'')}">
    <label class="fo-lbl">Tracking / waybill reference</label><input class="fo-in" id="et-ref" value="${pcEsc(r.tracking_ref||'')}">
    <label class="fo-lbl">Note <span style="font-weight:400;">(required when delayed)</span></label><input class="fo-in" id="et-note" value="${pcEsc(r.delivery_note||'')}">
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="et-go" style="flex:1;">Save</button><button class="fo-btn alt" onclick="pcClose(this)">Cancel</button></div>`);
  m.querySelector('#et-st').value = r.delivery_status || 'pending';
  m.querySelector('#et-go').onclick = async function(){
    const v = id => m.querySelector('#'+id).value;
    if(await pcAct(this, pcFin, 'update_po_delivery', {po_id:id, delivery_status:v('et-st'), expected_date:v('et-date'), carrier:v('et-car'), tracking_ref:v('et-ref'), note:v('et-note')}, true)){ m.remove(); edLoad(); }
  };
}
edLoad();
</script>

<?php elseif ($subModule == 'discrepancies'): ?>
<!-- ====== DISCREPANCIES: rejected / damaged / short deliveries, with follow-up ====== -->
<div class="fo-card">
  <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-exclamation-triangle"></i> Delivery discrepancies</h3>
    <select class="fo-in" style="width:auto;margin-left:auto;" id="dc-filter" onchange="dcRender()"><option value="open">Open</option><option value="resolved">Resolved</option><option value="">All</option></select>
    <button class="fo-btn alt" onclick="dcLoad()"><i class="fas fa-sync-alt"></i></button></div>
  <div class="fo-row" id="dc-stats"></div>
  <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Problem</th><th>GRN</th><th>Supplier</th><th>Item</th><th class="num">Ordered</th><th class="num">Received</th><th class="num">Accepted</th><th class="num">Rejected</th><th>Follow-up</th><th></th></tr></thead><tbody id="dc-tbody"></tbody></table></div>
  <p class="fo-note">A line appears when goods were rejected or damaged at inspection, or a delivery marked “full” arrived short. Record what was agreed with the supplier (credit note, replacement, return) and mark it resolved.</p>
</div>
<script>
let dcRows = [];
async function dcLoad(){ const j = await pcReq(pcOps, 'discrepancies'); dcRows = j.data || []; dcRender(); }
function dcRender(){
  const f = document.getElementById('dc-filter').value;
  const open = dcRows.filter(r=>r.follow_status==='open').length;
  document.getElementById('dc-stats').innerHTML = `<div class="fo-stat"><b>${open}</b><span>Open</span></div><div class="fo-stat"><b>${dcRows.length-open}</b><span>Resolved</span></div><div class="fo-stat"><b>${dcRows.filter(r=>r.kind==='rejected').length}</b><span>Rejected / damaged lines</span></div><div class="fo-stat"><b>${dcRows.filter(r=>r.kind==='short').length}</b><span>Short deliveries</span></div>`;
  const rows = dcRows.filter(r=>!f || r.follow_status===f);
  document.getElementById('dc-tbody').innerHTML = rows.length ? rows.map(r=>`<tr>
    <td>${r.kind==='rejected'?pcTag('Rejected / damaged','bad'):pcTag('Short delivery','warn')}</td>
    <td style="font-family:monospace;">${pcEsc(r.grn_number)}<div style="font-size:11px;color:var(--gray-500);">${pcEsc(r.received_date)} · ${pcEsc(r.po_number||'')}</div></td><td>${pcEsc(r.supplier_name||'—')}</td>
    <td>${pcEsc(r.item_name||'—')}${r.batch_number?'<div style="font-size:11px;color:var(--gray-500);">batch '+pcEsc(r.batch_number)+'</div>':''}${r.notes?'<div style="font-size:11px;color:var(--gray-500);">'+pcEsc(r.notes)+'</div>':''}</td>
    <td class="num">${+r.quantity_ordered}</td><td class="num">${+r.quantity_received}</td><td class="num">${+r.quantity_accepted}</td><td class="num" style="color:#b91c1c;font-weight:600;">${+r.bad_qty||''}</td>
    <td>${pcTag(r.follow_status, r.follow_status==='resolved'?'ok':'warn')}${r.follow_note?'<div style="font-size:11px;color:var(--gray-500);max-width:220px;">'+pcEsc(r.follow_note)+'</div>':''}</td>
    <td><button class="fo-btn alt sm" onclick="dcFollow(${r.grn_item_id})">Follow up</button></td></tr>`).join('') : '<tr><td colspan="10" class="fo-empty">Nothing here.</td></tr>';
}
function dcFollow(id){
  const r = dcRows.find(x=>+x.grn_item_id===id); if(!r) return;
  const m = pcModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Follow-up · ${pcEsc(r.grn_number)}</h3><div style="color:var(--gray-600);font-size:13px;">${pcEsc(r.supplier_name||'')} — ${pcEsc(r.item_name||'')}</div>
    <label class="fo-lbl">Status</label><select class="fo-in" id="df-st"><option value="open">Open</option><option value="resolved">Resolved</option></select>
    <label class="fo-lbl">What was agreed / done <span style="font-weight:400;">(required to resolve)</span></label><textarea class="fo-in" id="df-note" rows="3">${pcEsc(r.follow_note||'')}</textarea>
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="df-go" style="flex:1;">Save</button><button class="fo-btn alt" onclick="pcClose(this)">Cancel</button></div>`);
  m.querySelector('#df-st').value = r.follow_status;
  m.querySelector('#df-go').onclick = async function(){
    if(await pcAct(this, pcOps, 'discrepancy_note', {grn_item_id:id, status:m.querySelector('#df-st').value, note:m.querySelector('#df-note').value}, true)){ m.remove(); dcLoad(); }
  };
}
dcLoad();
</script>

<?php elseif ($subModule == 'proformas'): ?>
<!-- ====== PROFORMA INVOICES (before purchase) ====== -->
<div class="fo-grid">
  <div class="fo-card">
    <h3><i class="fas fa-file-invoice-dollar"></i> Register a proforma invoice</h3>
    <label class="fo-lbl">Supplier</label><select class="fo-in" id="pf-sup" onchange="pfFillPo()"></select>
    <label class="fo-lbl">Proforma no.</label><input class="fo-in" id="pf-no">
    <label class="fo-lbl">Linked purchase order <span style="font-weight:400;">(optional)</span></label><select class="fo-in" id="pf-po"></select>
    <label class="fo-lbl">Linked purchase request <span style="font-weight:400;">(optional)</span></label><select class="fo-in" id="pf-pr"></select>
    <label class="fo-lbl">Amount (RWF)</label><input class="fo-in" type="number" min="0" step="0.01" id="pf-amount">
    <label class="fo-lbl">Issued</label><input class="fo-in" type="date" id="pf-issue">
    <label class="fo-lbl">Valid until</label><input class="fo-in" type="date" id="pf-until">
    <label class="fo-lbl">Attach the proforma <span style="font-weight:400;">(PDF or image)</span></label><input class="fo-in" type="file" id="pf-file">
    <label class="fo-lbl">Notes</label><input class="fo-in" id="pf-notes">
    <button class="fo-btn" style="margin-top:14px;width:100%;" onclick="pfSave(this)"><i class="fas fa-check"></i> Register</button>
  </div>
  <div class="fo-card">
    <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-list"></i> Proforma invoices</h3><button class="fo-btn alt" style="margin-left:auto;" onclick="pfLoad()"><i class="fas fa-sync-alt"></i></button></div>
    <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Proforma</th><th>Supplier</th><th>Linked to</th><th class="num">Amount</th><th>Validity</th><th>Status</th><th></th></tr></thead><tbody id="pf-tbody"></tbody></table></div>
    <p class="fo-note">A proforma is the supplier's price offer before you buy. Accepting it records the agreed price; if the purchase order total later differs from it, the difference shows under Exceptions.</p>
  </div>
</div>
<script>
let pfSups = [], pfPos = [], pfRows = [];
async function pfInit(){
  const [s, p, r] = await Promise.all([pcReq(pcInv, 'suppliers'), pcReq(pcInv, 'purchase_orders'), pcReq(pcInv, 'purchase_requests', {params:{status:'approved'}})]);
  pfSups = s.data || []; pfPos = (p.data||[]).filter(x=>x.status!=='cancelled');
  document.getElementById('pf-sup').innerHTML = pcOpts(pfSups, 'id', x=>x.company_name, '');
  document.getElementById('pf-pr').innerHTML = '<option value="">— none —</option>' + pcOpts(r.data||[], 'id', x=>x.pr_number, '');
  document.getElementById('pf-issue').value = pcToday();
  pfFillPo(); pfLoad();
}
function pfFillPo(){
  const sid = document.getElementById('pf-sup').value;
  document.getElementById('pf-po').innerHTML = '<option value="">— none —</option>' + pcOpts(pfPos.filter(x=>String(x.supplier_id)===String(sid)), 'id', x=>x.po_number+' · '+pcMoney(x.total_amount)+' RWF', '');
}
async function pfLoad(){
  const j = await pcReq(pcOps, 'proformas'); pfRows = j.data || [];
  document.getElementById('pf-tbody').innerHTML = pfRows.length ? pfRows.map(p=>{
    const exp = +p.expired === 1;
    return `<tr><td><b>${pcEsc(p.proforma_number)}</b>${+p.has_file?` <a href="${pcOps}&action=proforma_file&id=${p.id}" target="_blank" rel="noopener" title="${pcEsc(p.original_name||'')}"><i class="fas fa-paperclip"></i></a>`:''}<div style="font-size:11px;color:var(--gray-500);">${pcEsc(p.notes||'')}</div></td>
    <td>${pcEsc(p.company_name)}</td><td style="font-size:12px;">${pcEsc([p.po_number, p.pr_number].filter(Boolean).join(' · ')||'—')}</td><td class="num">${pcMoney(p.amount)}</td>
    <td style="font-size:12px;">${pcEsc(p.issue_date)} → ${pcEsc(p.valid_until||'open')}${exp?' '+pcTag('Expired','bad'):''}</td>
    <td>${pcTag(p.status, p.status==='accepted'?'ok':(p.status==='rejected'?'bad':'warn'))}${p.decision_note?'<div style="font-size:11px;color:var(--gray-500);max-width:180px;">'+pcEsc(p.decision_note)+'</div>':''}</td>
    <td style="white-space:nowrap;">${p.status==='pending'?`<button class="fo-btn ok sm" ${exp?'disabled title="Expired"':''} onclick="pfDecide(${p.id},'accepted',this)">Accept</button> <button class="fo-btn danger sm" onclick="pfDecide(${p.id},'rejected',this)">Reject</button>`:''}</td></tr>`;}).join('') : '<tr><td colspan="7" class="fo-empty">No proforma invoices yet.</td></tr>';
}
async function pfSave(btn){
  const fd = new FormData(), v = id => document.getElementById(id).value;
  fd.append('supplier_id', v('pf-sup')); fd.append('proforma_number', v('pf-no')); fd.append('po_id', v('pf-po')); fd.append('pr_id', v('pf-pr')); fd.append('amount', v('pf-amount'));
  fd.append('issue_date', v('pf-issue')); fd.append('valid_until', v('pf-until')); fd.append('notes', v('pf-notes'));
  const f = document.getElementById('pf-file').files[0]; if(f) fd.append('file', f);
  if(await pcAct(btn, pcOps, 'proforma_save', fd, 'Proforma registered')){ ['pf-no','pf-amount','pf-until','pf-notes','pf-file'].forEach(id=>document.getElementById(id).value=''); pfLoad(); }
}
async function pfDecide(id, decision, btn){
  let note = '';
  if(decision === 'rejected'){ note = prompt('Reason for rejecting this proforma:', ''); if(!note) return; }
  if(await pcAct(btn, pcOps, 'proforma_decide', {id:id, decision:decision, note:note}, true)) pfLoad();
}
pfInit();
</script>

<?php elseif ($subModule == 'supplier-invoices'): ?>
<!-- ====== SUPPLIER INVOICES: matched to goods received (PO -> GRN -> bill) ====== -->
<div class="fo-card">
  <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-file-invoice"></i> Supplier invoices <span style="font-size:11px;font-weight:400;color:var(--gray-500);">matched to goods received (PO &rarr; GRN &rarr; invoice)</span></h3>
    <?php if ($pcCanBill): ?><button class="fo-btn" style="margin-left:auto;" onclick="siStart()"><i class="fas fa-plus"></i> Enter supplier bill</button><?php endif; ?></div>
  <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Date</th><th>Supplier</th><th>Invoice no.</th><th>GRNs</th><th>Due</th><th class="num">Amount</th><th>Status</th><th class="num">Paid</th><th class="num">Balance</th><th></th></tr></thead><tbody id="si-tbody"></tbody></table></div>
  <p class="fo-note">A bill is entered against goods already received: its amount must equal their value, and a delivery can only be billed once. Verified bills are paid by Finance (Payment Status tab).</p>
</div>
<script>
let siRows = [];
const SI_KIND = {posted:['Approved','info'], partially_paid:['Partially paid','warn'], paid:['Paid','ok'], voided:['Voided','gray']};
async function siLoad(){
  const j = await pcReq(pcFin, 'supplier_invoices'); siRows = j.data || [];
  const today = pcToday();
  document.getElementById('si-tbody').innerHTML = siRows.length ? siRows.map(i=>{
    const od = ['posted','partially_paid'].includes(i.status) && i.due_date && i.due_date < today, k = SI_KIND[i.status] || [i.status,'gray'];
    return `<tr style="${i.status==='voided'?'opacity:.5;text-decoration:line-through;':''}"><td>${pcEsc(i.invoice_date)}</td><td>${pcEsc(i.company_name)}</td><td>${pcEsc(i.invoice_number)}</td><td style="font-size:11px;">${pcEsc(i.grns||'')}</td><td>${pcEsc(i.due_date||'')}</td>
    <td class="num">${pcMoney(i.amount)}</td><td>${pcTag(k[0], k[1])}${od?' '+pcTag('Overdue','bad'):''}</td><td class="num">${pcMoney(i.paid_amount||0)}</td><td class="num">${pcMoney(i.balance!=null?i.balance:i.amount)}</td>
    <td>${PC_CAN_BILL && i.status!=='voided'?`<button class="fo-btn danger sm" onclick="siVoid(${i.id},this)">Void</button>`:''}</td></tr>`;}).join('') : '<tr><td colspan="10" class="fo-empty">No supplier invoices yet.</td></tr>';
}
async function siStart(){
  const s = await pcReq(pcOps, 'suppliers_all'); const sups = s.data || [];
  const m = pcModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Enter supplier bill</h3><label class="fo-lbl">Supplier</label><select class="fo-in" id="sb-sup">${pcOpts(sups,'id',x=>x.company_name,'')}</select>
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="sb-go" style="flex:1;">Continue</button><button class="fo-btn alt" onclick="pcClose(this)">Cancel</button></div>`);
  m.querySelector('#sb-go').onclick = ()=>{ const id = m.querySelector('#sb-sup').value, name = (sups.find(x=>String(x.id)===id)||{}).company_name||''; m.remove(); siBill(+id, name); };
}
async function siBill(id, name){
  const g = await pcReq(pcFin, 'uninvoiced_grns', {params:{supplier_id:id}});
  const grns = g.data || [];
  if(!grns.length) return alert(name + ' has no goods received waiting for a bill.');
  const m = pcModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Enter supplier bill</h3><div style="color:var(--gray-600);font-size:13px;">${pcEsc(name)} — tick the deliveries this bill covers</div>
    <div class="fo-wrap" style="margin-top:10px;"><table class="fo-tbl"><thead><tr><th></th><th>GRN</th><th>PO</th><th>Received</th><th class="num">Value</th></tr></thead><tbody>
    ${grns.map(x=>`<tr><td><input type="checkbox" class="si-grn" value="${x.id}" data-v="${x.value}" checked></td><td>${pcEsc(x.grn_number)}</td><td>${pcEsc(x.po_number||'')}</td><td>${pcEsc(x.received_date)}</td><td class="num">${pcMoney(x.value)}</td></tr>`).join('')}
    </tbody></table></div>
    <div style="margin-top:8px;font-size:13px;">Goods received: <b id="si-exp"></b> RWF</div>
    <label class="fo-lbl">Supplier invoice no.</label><input class="fo-in" id="si-no">
    <label class="fo-lbl">Invoice date</label><input class="fo-in" type="date" id="si-date" value="${pcToday()}">
    <label class="fo-lbl">Due date</label><input class="fo-in" type="date" id="si-due">
    <label class="fo-lbl">Invoice amount (RWF)</label><input class="fo-in" type="number" step="0.01" id="si-amount">
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="si-go" style="flex:1;">Match &amp; post</button><button class="fo-btn alt" onclick="pcClose(this)">Cancel</button></div>
    <p class="fo-note">The amount must equal the goods received. Posts Dr goods received not invoiced, Cr ${pcEsc(name)}.</p>`);
  const sum = ()=>{ const v=[...m.querySelectorAll('.si-grn:checked')].reduce((a,c)=>a+Number(c.dataset.v),0); m.querySelector('#si-exp').textContent=pcMoney(v); return v; };
  m.querySelectorAll('.si-grn').forEach(c=>c.onchange=sum); m.querySelector('#si-amount').value = sum();
  m.querySelector('#si-go').onclick = async function(){
    const j = await pcAct(this, pcFin, 'supplier_invoice', {supplier_id:id, invoice_number:m.querySelector('#si-no').value, invoice_date:m.querySelector('#si-date').value,
      due_date:m.querySelector('#si-due').value, amount:m.querySelector('#si-amount').value, grn_ids:[...m.querySelectorAll('.si-grn:checked')].map(c=>c.value)}, true);
    if(j){ m.remove(); siLoad(); }
  };
}
async function siVoid(id, btn){ if(!confirm('Void this supplier invoice? Its GRNs become unbilled again.')) return; if(await pcAct(btn, pcFin, 'supplier_invoice_void', {id:id}, true)) siLoad(); }
siLoad();
</script>

<?php elseif ($subModule == 'invoice-exceptions'): ?>
<!-- ====== INVOICE EXCEPTIONS ====== -->
<div class="fo-card">
  <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-flag"></i> Invoice exceptions</h3><button class="fo-btn alt" style="margin-left:auto;" onclick="ixLoad()"><i class="fas fa-sync-alt"></i></button></div>
  <div class="fo-row" id="ix-stats"></div>
  <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th></th><th>Exception</th><th>Supplier</th><th>Reference</th><th>What is wrong</th><th class="num">Amount (RWF)</th><th>Date</th></tr></thead><tbody id="ix-tbody"></tbody></table></div>
  <p class="fo-note">Checks run on every visit: duplicate bills, price differences (received at a price other than the PO, or a PO total that differs from its accepted proforma), bills with no delivery, deliveries not billed after 30 days, overdue bills, and bills still owed to a suspended or blacklisted supplier.</p>
</div>
<script>
let ixRows = [], ixFilter = '';
const IX_LABEL = {duplicate:'Duplicate bill', price_difference:'Price difference', billed_not_delivered:'Billed, not delivered', unbilled_delivery:'Delivered, not billed', overdue:'Overdue payment', restricted_supplier:'Supplier restricted'};
async function ixLoad(){
  const j = await pcReq(pcOps, 'invoice_exceptions'); ixRows = j.data || [];
  const types = Object.keys(IX_LABEL);
  document.getElementById('ix-stats').innerHTML = `<div class="fo-stat" style="cursor:pointer;" onclick="ixFilter='';ixRender()"><b>${ixRows.length}</b><span>All exceptions</span></div>` +
    types.map(t=>`<div class="fo-stat" style="cursor:pointer;${ixFilter===t?'border-color:var(--primary-color);':''}" onclick="ixFilter='${t}';ixRender()"><b>${ixRows.filter(r=>r.type===t).length}</b><span>${IX_LABEL[t]}</span></div>`).join('');
  ixRender();
}
function ixRender(){
  const rows = ixRows.filter(r=>!ixFilter || r.type===ixFilter);
  document.getElementById('ix-tbody').innerHTML = rows.length ? rows.map(r=>`<tr><td>${pcTag(r.severity==='high'?'High':'Medium', r.severity==='high'?'bad':'warn')}</td><td>${pcEsc(IX_LABEL[r.type]||r.type)}</td><td>${pcEsc(r.supplier)}</td><td style="font-family:monospace;">${pcEsc(r.ref)}</td><td style="font-size:12px;">${pcEsc(r.detail)}</td><td class="num">${r.amount==null?'—':pcMoney(r.amount)}</td><td>${pcEsc(r.date||'')}</td></tr>`).join('')
    : '<tr><td colspan="7" class="fo-empty">No exceptions: every bill matches a delivery.</td></tr>';
}
ixLoad();
</script>

<?php elseif ($subModule == 'payment-status'): ?>
<!-- ====== PAYMENT STATUS: what we owe each supplier ====== -->
<div class="fo-card">
  <div class="fo-row"><h3 style="margin:0;"><i class="fas fa-truck"></i> Supplier balances</h3><button class="fo-btn alt" style="margin-left:auto;" onclick="psLoad()"><i class="fas fa-sync-alt"></i></button></div>
  <div class="fo-row" id="ps-stats"></div>
  <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Supplier</th><th>Status</th><th class="num">We owe</th><th></th></tr></thead><tbody id="sp-tbody"></tbody></table></div>
  <?php if (!$pcCanPay): ?><p class="fo-note">Payments are made by Finance. Verified supplier bills appear here as soon as they are posted.</p><?php endif; ?>
  <h3 style="margin-top:20px;">Recent supplier payments</h3>
  <div class="fo-wrap"><table class="fo-tbl"><thead><tr><th>Date</th><th>Supplier</th><th>Paid from</th><th>Reference</th><th class="num">Amount</th><th></th></tr></thead><tbody id="sp-pay-tbody"></tbody></table></div>
</div>
<script>
let psSups = [], psCash = [];
async function psLoad(){
  const reqs = [pcReq(pcFin, 'suppliers_payable'), pcReq(pcFin, 'supplier_invoices')];
  if(PC_CAN_PAY) reqs.push(pcReq(pcFin, 'cash_accounts'));
  const [s, inv, c] = await Promise.all(reqs);
  const d = s.data || {suppliers:[], payments:[]}; psSups = d.suppliers; psCash = (c && c.data) || [];
  const invs = (inv.data||[]).filter(i=>i.status!=='voided'), today = pcToday();
  const owed = psSups.reduce((a,x)=>a+Math.max(0,+x.payable),0);
  const unpaid = invs.filter(i=>['posted','partially_paid'].includes(i.status)), over = unpaid.filter(i=>i.due_date && i.due_date < today);
  document.getElementById('ps-stats').innerHTML = [[pcMoney(owed)+' RWF','Total owed to suppliers'],[unpaid.length,'Bills awaiting payment'],[invs.filter(i=>i.status==='partially_paid').length,'Partially paid'],[over.length,'Overdue bills'],[invs.filter(i=>i.status==='paid').length,'Paid bills']]
    .map(([n,l])=>`<div class="fo-stat"><b>${n}</b><span>${l}</span></div>`).join('');
  document.getElementById('sp-tbody').innerHTML = psSups.length ? psSups.map(x=>`<tr><td>${pcEsc(x.company_name)}</td><td>${pcTag(x.status, PC_SUP_KIND[x.status])}</td><td class="num" style="font-weight:600;color:${x.payable>0?'#dc2626':'inherit'};">${pcMoney(x.payable)}</td>
    <td>${PC_CAN_PAY && (+x.payable>0 || x.status==='active')?`<button class="fo-btn sm" onclick="psPay(${x.id})">${+x.payable>0?'Pay':'Advance'}</button>`:''}</td></tr>`).join('') : '<tr><td colspan="4" class="fo-empty">No suppliers yet.</td></tr>';
  document.getElementById('sp-pay-tbody').innerHTML = (d.payments||[]).length ? d.payments.map(p=>`<tr style="${p.status==='voided'?'opacity:.5;text-decoration:line-through;':''}"><td>${pcEsc(p.payment_date)}</td><td>${pcEsc(p.company_name)}</td><td>${pcEsc(p.pay_account)}</td><td>${pcEsc(p.reference||'')}</td><td class="num">${pcMoney(p.amount)}${+p.wht_amount>0?`<div style="font-size:11px;color:var(--gray-500);">tax withheld ${pcMoney(p.wht_amount)}</div>`:''}</td>
    <td>${PC_CAN_PAY && p.status!=='voided'?`<button class="fo-btn danger sm" onclick="psVoid(${p.id},this)">Void</button>`:''}</td></tr>`).join('') : '<tr><td colspan="6" class="fo-empty">No supplier payments yet.</td></tr>';
}
function psPay(id){
  const s = psSups.find(x=>+x.id===id); if(!s) return;
  const m = pcModal(`<h3 style="font-size:16px;font-weight:700;margin-bottom:6px;">Pay supplier</h3><div style="color:var(--gray-600);font-size:13px;">${pcEsc(s.company_name)} — owed ${pcMoney(s.payable)} RWF</div>
    <label class="fo-lbl">Date</label><input class="fo-in" type="date" id="sp-date" value="${pcToday()}">
    <label class="fo-lbl">Amount</label><input class="fo-in" type="number" step="0.01" id="sp-amount" value="${Math.max(0, s.payable)}">
    <label class="fo-lbl">Tax withheld (RWF)</label><input class="fo-in" type="number" step="0.01" min="0" id="sp-wht" value="0"><div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Kept back from this payment and owed to RRA. The supplier is credited the full amount.</div>
    <label class="fo-lbl">Paid from</label><select class="fo-in" id="sp-from">${pcOpts(psCash,'id',x=>x.account_name+' ('+pcMoney(x.balance)+')','')}</select>
    <label class="fo-lbl">Reference</label><input class="fo-in" id="sp-ref">
    <label style="display:flex;gap:8px;align-items:center;margin-top:12px;font-size:13px;"><input type="checkbox" id="sp-adv"> Advance payment (more than is owed now, before the bill)</label>
    <div style="display:flex;gap:8px;margin-top:16px;"><button class="fo-btn" id="sp-go" style="flex:1;">Record payment</button><button class="fo-btn alt" onclick="pcClose(this)">Cancel</button></div>`);
  m.querySelector('#sp-go').onclick = async function(){
    if(await pcAct(this, pcFin, 'supplier_payment', {supplier_id:id, payment_date:m.querySelector('#sp-date').value, amount:m.querySelector('#sp-amount').value, wht_amount:m.querySelector('#sp-wht').value, pay_account_id:m.querySelector('#sp-from').value, reference:m.querySelector('#sp-ref').value, advance:m.querySelector('#sp-adv').checked}, true)){ m.remove(); psLoad(); }
  };
}
async function psVoid(id, btn){ if(!confirm('Void this supplier payment?')) return; if(await pcAct(btn, pcFin, 'supplier_payment_void', {id:id}, true)) psLoad(); }
psLoad();
</script>
<?php endif; ?>
