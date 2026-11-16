<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/ui_prompt.php';
require_once __DIR__ . '/includes/procurement_rules.php';
require_once __DIR__ . '/includes/auth.php';   // canUsePage()
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'approved-requests';
?>
<style>.pc-tab{padding:10px 18px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}.pc-tab.active{background:var(--primary-color);color:white;}</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div><h2 style="font-size:24px;font-weight:600;">Procurement</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Source, evaluate and award suppliers before issuing purchase orders</p></div>
</div>
<?php
// One sidebar item per page (PC_PAGES); the tabs below belong to the page the current ?sub= is in. Pages are in workflow order:
// supplier -> sourcing -> contract -> order + receipt -> invoice + payment.
require_once __DIR__ . '/includes/procurement_schema.php';
pc_migrate($pdo);
$pcLabels = ['suppliers'=>'All Suppliers','supplier-docs'=>'Documents & Certificates','performance'=>'Performance',
  'approved-requests'=>'Approved Requests','sourcing'=>'RFQs & Quotations','evaluation'=>'Evaluation','awards'=>'Awards','contracts'=>'Contracts',
  'purchase-orders'=>'Purchase Orders','expected-deliveries'=>'Expected Deliveries','goods-received'=>'Goods Received','discrepancies'=>'Discrepancies',
  'proformas'=>'Proforma Invoices','supplier-invoices'=>'Supplier Invoices','invoice-exceptions'=>'Exceptions','payment-status'=>'Payment Status'];
$pcUid = (int)($_SESSION['user_id'] ?? 0);
$pcNav = [];
foreach (PC_PAGES as [$title, $icon, $subs]) if (pc_has_access($pdo, $pcUid, $subs)) $pcNav[] = [$title, $icon, $subs];
$pcActive = null;
foreach ($pcNav as $i => [, , $subs]) if (in_array($subModule, $subs, true)) $pcActive = $i;
?>
<div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
<?php foreach ($pcNav as $i => [$title, $icon, $subs]): ?>
  <button class="pc-tab <?php echo $i === $pcActive ? 'active' : ''; ?>" onclick="location.href='procurement.php?module=procurement&sub=<?php echo $subs[0]; ?>'"><span style="opacity:.6;margin-right:4px;"><?php echo $i + 1; ?>.</span><i class="<?php echo $icon; ?>"></i> <?php echo $title; ?></button>
<?php endforeach; ?>
</div>
<?php if ($pcActive !== null): ?>
<div style="display:flex;gap:6px;margin-bottom:24px;overflow-x:auto;border-bottom:1px solid var(--gray-200);" id="pc-subtabs">
<?php if ($pcActive === 1 && canUsePage($pdo, 'inventory.php?module=inventory&sub=purchase-request')): ?>
  <a href="inventory.php?module=inventory&sub=purchase-request" style="padding:8px 14px;font-size:13px;text-decoration:none;white-space:nowrap;border-bottom:2px solid transparent;color:var(--gray-600);font-weight:500;">Purchase Requests</a>
<?php endif; ?>
<?php foreach ($pcNav[$pcActive][2] as $t): $on = $t === $subModule; ?>
  <a href="procurement.php?module=procurement&sub=<?php echo $t; ?>" data-tab="<?php echo $t; ?>" style="padding:8px 14px;font-size:13px;text-decoration:none;white-space:nowrap;border-bottom:2px solid <?php echo $on ? 'var(--primary-color)' : 'transparent'; ?>;color:<?php echo $on ? 'var(--primary-color)' : 'var(--gray-600)'; ?>;font-weight:<?php echo $on ? '600' : '500'; ?>;"><?php echo $pcLabels[$t]; ?></a>
<?php endforeach; ?>
</div>
<?php else: ?><div style="margin-bottom:12px;"></div><?php endif; ?>

<script>
const _pBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const pExt = _pBase + '/api.php?endpoint=procurement/procurement-ext&action=';
</script>

<?php if ($subModule == 'sourcing'): ?>
<!-- ====== PRE-PO SOURCING: RFQ, QUOTATIONS, EVALUATION, AWARD ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap;">
    <div><h3 style="font-size:16px;font-weight:600;"><i class="fas fa-balance-scale" style="color:var(--primary-color);"></i> Pre-PO Sourcing</h3><p style="font-size:12px;color:var(--gray-500);margin-top:4px;">Create RFQs, record supplier quotations, score bids and issue provisional or final awards.</p></div>
    <div style="display:flex;gap:8px;"><button onclick="rfqNew()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> New RFQ</button><button onclick="rfqLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button></div>
  </div>
  <div id="rfq-list"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
const pAPI = _pBase + '/api.php?endpoint=inventory/inventory-management&action=';
function pClose(){document.querySelectorAll('.p-modal').forEach(e=>e.remove());}
function pEsc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function rfqBadge(s){const colors={published:['#dbeafe','#1e40af'],closed:['#fef3c7','#92400e'],awarded:['#d1fae5','#065f46'],cancelled:['#fee2e2','#991b1b'],draft:['#f3f4f6','#374151']};const c=colors[s]||colors.draft;return `<span style="padding:3px 8px;border-radius:10px;font-size:10px;background:${c[0]};color:${c[1]};">${pEsc(s)}</span>`;}
async function rfqLoad(){const j=await (await fetch(pExt+'rfqs')).json();const d=document.getElementById('rfq-list');if(!j.success||!(j.data||[]).length){d.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No RFQs yet. Create an RFQ from an approved purchase request.</div>';return;}d.innerHTML='<div style="border:1px solid var(--gray-200);border-radius:8px;overflow:auto;"><table style="width:100%;border-collapse:collapse;font-size:13px;min-width:760px;"><thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">RFQ</th><th style="padding:10px;text-align:left;">Title</th><th style="padding:10px;text-align:left;">Closing</th><th style="padding:10px;text-align:center;">Quotations</th><th style="padding:10px;text-align:left;">Status</th><th style="padding:10px;text-align:left;">Awarded supplier</th><th style="padding:10px;text-align:right;">Actions</th></tr></thead><tbody>'+j.data.map(r=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:9px 10px;font-family:monospace;color:var(--primary-color);">${pEsc(r.rfq_number)}</td><td style="padding:9px 10px;font-weight:600;">${pEsc(r.title)}<div style="font-size:11px;color:var(--gray-500);">${pEsc(r.pr_number||'No PR linked')}</div></td><td style="padding:9px 10px;">${pEsc(r.closing_date||'—')}</td><td style="padding:9px 10px;text-align:center;">${r.quotation_count||0}</td><td style="padding:9px 10px;">${rfqBadge(r.status)}</td><td style="padding:9px 10px;">${pEsc(r.awarded_supplier_name||'—')}</td><td style="padding:9px 10px;text-align:right;white-space:nowrap;"><button onclick="rfqView(${r.id})" style="padding:5px 8px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:5px;font-size:11px;cursor:pointer;">Open</button></td></tr>`).join('')+'</tbody></table></div>';}
async function rfqNew(){const j=await (await fetch(pAPI+'purchase_requests&status=approved')).json();const prs=j.success?j.data:[];const m=document.createElement('div');m.className='p-modal';m.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;';m.innerHTML=`<div style="background:white;border-radius:12px;width:560px;max-width:94vw;padding:24px;"><div style="display:flex;justify-content:space-between;margin-bottom:16px;"><h3 style="font-size:16px;font-weight:600;">Create Request for Quotation</h3><button onclick="this.closest('div[style*=fixed]').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Approved purchase request *</label><select id="rfq-pr" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Select request...</option>${prs.map(x=>`<option value="${x.id}">${pEsc(x.pr_number)} — ${pEsc(x.justification||'Purchase request')}</option>`).join('')}</select><label style="font-size:12px;font-weight:600;display:block;margin:12px 0 4px;">RFQ title *</label><input id="rfq-title" placeholder="e.g. Laboratory reagents supply" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px;"><div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Closing date</label><input type="date" id="rfq-close" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div><div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Notes</label><input id="rfq-notes" placeholder="Submission instructions" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div></div><button onclick="rfqSave()" style="width:100%;margin-top:18px;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Publish RFQ</button></div>`;document.body.appendChild(m);}
async function rfqSave(){const pr=document.getElementById('rfq-pr').value,title=document.getElementById('rfq-title').value.trim();if(!pr||!title)return alert('Select an approved request and enter a title.');const j=await (await fetch(pExt+'create_rfq',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pr_id:+pr,title,closing_date:document.getElementById('rfq-close').value,notes:document.getElementById('rfq-notes').value})})).json();alert(j.message);if(j.success){pClose();rfqLoad();}}
async function rfqView(id){const [r,s]=await Promise.all([fetch(pExt+'rfq_detail&id='+id),fetch(pAPI+'suppliers')]);const j=await r.json(),sj=await s.json();if(!j.success)return alert(j.message);const x=j.data,sups=sj.success?sj.data:[];const m=document.createElement('div');m.className='p-modal';m.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;';m.innerHTML=`<div style="background:white;border-radius:12px;width:900px;max-width:96vw;max-height:90vh;overflow:auto;padding:24px;"><div style="display:flex;justify-content:space-between;align-items:start;"><div><h3 style="font-size:17px;font-weight:600;">${pEsc(x.rfq_number)} — ${pEsc(x.title)}</h3><p style="font-size:12px;color:var(--gray-500);margin-top:4px;">Closing: ${pEsc(x.closing_date||'—')} · ${rfqBadge(x.status)}${x.standstill_end?' · Standstill until '+pEsc(x.standstill_end):''}</p></div><button onclick="this.closest('div[style*=fixed]').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div><div style="margin-top:18px;"><h4 style="font-size:13px;margin-bottom:7px;">Supplier quotations</h4><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">Supplier</th><th style="padding:8px;text-align:left;">Quotation</th><th style="padding:8px;text-align:right;">Amount</th><th style="padding:8px;text-align:center;">Score</th><th style="padding:8px;text-align:left;">Status</th></tr></thead><tbody>${(x.quotations||[]).map(q=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:8px;">${pEsc(q.company_name)}</td><td style="padding:8px;">${pEsc(q.quotation_number)}</td><td style="padding:8px;text-align:right;font-weight:600;">${parseFloat(q.total_amount).toLocaleString()} RWF</td><td style="padding:8px;text-align:center;">${q.total_score==null?'—':q.total_score}</td><td style="padding:8px;">${pEsc(q.status)}</td></tr>`).join('')||'<tr><td colspan="5" style="padding:16px;text-align:center;color:var(--gray-400);">No quotations recorded.</td></tr>'}</tbody></table></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;"><div style="border:1px solid var(--gray-200);border-radius:8px;padding:14px;"><h4 style="font-size:13px;margin-bottom:10px;">Record quotation</h4><select id="q-supplier" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid var(--gray-200);border-radius:6px;"><option value="">Supplier...</option>${sups.map(s=>`<option value="${s.id}">${pEsc(s.company_name)}</option>`).join('')}</select><input id="q-number" placeholder="Quotation number" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid var(--gray-200);border-radius:6px;"><input id="q-amount" type="number" min="0" placeholder="Total amount (RWF)" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid var(--gray-200);border-radius:6px;"><input id="q-days" type="number" min="0" placeholder="Delivery days" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid var(--gray-200);border-radius:6px;">${(x.items||[]).length?`<div style="font-size:11px;font-weight:700;color:var(--gray-500);margin:2px 0 4px;">Line prices (optional — overrides total)</div><table style="width:100%;font-size:11px;margin-bottom:8px;"><tbody>${(x.items||[]).map((it,i)=>`<tr><td style="padding:3px 0;">${pEsc(it.item_description||('Item '+(i+1)))} × ${parseFloat(it.quantity)}</td><td style="padding:3px 0;text-align:right;"><input type="number" class="q-item-price" data-index="${i}" data-qty="${it.quantity}" min="0" step="0.01" placeholder="Unit price" style="width:90px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;text-align:right;" oninput="qRecalc()"></td></tr>`).join('')}</tbody></table>`:''}<button onclick="quoteSave(${id})" style="width:100%;padding:9px;background:#2563eb;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Save quotation</button></div><div style="border:1px solid var(--gray-200);border-radius:8px;padding:14px;"><h4 style="font-size:13px;margin-bottom:10px;">Next steps</h4><p style="font-size:12px;color:var(--gray-600);margin-bottom:12px;">Score the bids on the Evaluation tab, then issue the provisional and final award on the Awards tab.</p><a href="procurement.php?module=procurement&sub=evaluation&rfq=${id}" style="display:block;text-align:center;padding:9px;border-radius:6px;font-weight:600;font-size:12px;text-decoration:none;color:white;margin-bottom:8px;background:#7c3aed;"><i class="fas fa-clipboard-check"></i> Evaluate bids</a><a href="procurement.php?module=procurement&sub=awards&rfq=${id}" style="display:block;text-align:center;padding:9px;border-radius:6px;font-weight:600;font-size:12px;text-decoration:none;color:white;margin-bottom:8px;background:#d97706;"><i class="fas fa-award"></i> Awards</a></div></div></div>`;document.body.appendChild(m);}
function qRecalc(){const els=[...document.querySelectorAll('.q-item-price')];if(!els.length)return;const t=els.reduce((s,el)=>s+(+el.dataset.qty||0)*(+el.value||0),0);const a=document.getElementById('q-amount');if(a&&t>0)a.value=t.toFixed(2);}
async function quoteSave(id){const priceEls=[...document.querySelectorAll('.q-item-price')];const items=priceEls.map(el=>({quantity:+el.dataset.qty||0,unit_price:+el.value||0}));const lineTotal=items.reduce((s,x)=>s+x.quantity*x.unit_price,0);const body={rfq_id:id,supplier_id:+document.getElementById('q-supplier').value,quotation_number:document.getElementById('q-number').value,total_amount:lineTotal>0?lineTotal:+document.getElementById('q-amount').value,delivery_days:+document.getElementById('q-days').value,items};if(!body.supplier_id||!body.quotation_number||body.total_amount<=0)return alert('Supplier, quotation number and amount are required.');const j=await (await fetch(pExt+'add_quotation',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)})).json();alert(j.message);if(j.success){pClose();rfqView(id);}}
rfqLoad();
</script>

<?php elseif ($subModule == 'approved-requests'): ?>
<!-- ====== APPROVED REQUESTS → Generate PO ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-check-circle" style="color:#16a34a;"></i> Approved Purchase Requests</h3>
    <button onclick="arLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div id="ar-list"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
const pAPI = _pBase + '/api.php?endpoint=inventory/inventory-management&action=';
function pEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function arLoad(){
  const r=await fetch(pAPI+'purchase_requests&status=approved');const j=await r.json();
  const div=document.getElementById('ar-list');
  if(j.success&&j.data&&j.data.length){
    div.innerHTML=j.data.map(pr=>`<div style="border:1px solid var(--gray-200);border-radius:8px;padding:14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
      <div><div style="font-weight:600;font-size:14px;">${pEsc(pr.pr_number)}</div><div style="font-size:12px;color:var(--gray-500);">Priority: ${pr.priority} | Items: ${pr.item_count||0} | Total: ${parseFloat(pr.total_estimated||0).toLocaleString()} RWF</div></div>
      <button onclick="arGeneratePO(${pr.id})" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-file-invoice"></i> Generate PO</button>
    </div>`).join('');
  } else div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No approved requests pending PO creation.</div>';
}

async function arGeneratePO(prId){
  // Load PR details and suppliers
  const [prRes,supRes]=await Promise.all([fetch(pAPI+'purchase_request_detail&id='+prId),fetch(pAPI+'suppliers')]);
  const [prJ,supJ]=await Promise.all([prRes.json(),supRes.json()]);
  if(!prJ.success) return alert('Error loading PR');
  const pr=prJ.data; const items=pr.items||[];
  const suppliers=supJ.success?supJ.data:[];
  
  const awardedSup = pr.award ? String(pr.award.awarded_supplier_id||'') : '';
  let supOpts='<option value="">Select supplier...</option>';
  suppliers.forEach(s=>{supOpts+=`<option value="${s.id}"${String(s.id)===awardedSup?' selected':''}>${pEsc(s.company_name)}</option>`;});

  let itemsHtml=items.map((it,i)=>{
    const awardedPrice = (pr.awarded_prices||{})[String(it.item_id)];
    const unitPrice = (awardedPrice && awardedPrice>0) ? awardedPrice : (it.estimated_unit_price||0);
    return `<tr style="border-bottom:1px solid var(--gray-100);">
    <td style="padding:6px 8px;font-size:12px;">${pEsc(it.item_name||it.item_description||'Item')}</td>
    <td style="padding:6px 8px;text-align:center;"><input type="number" class="po-qty" value="${it.quantity||1}" min="1" style="width:60px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:center;" data-item-id="${it.item_id}" data-name="${pEsc(it.item_name||it.item_description||'')}"></td>
    <td style="padding:6px 8px;text-align:right;"><input type="number" class="po-price" value="${unitPrice}" style="width:80px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:right;" oninput="poCalcTotal()"></td>
    <td style="padding:6px 8px;text-align:right;font-weight:600;" class="po-line-total">${((it.quantity||1)*unitPrice).toLocaleString()}</td>
  </tr>`;}).join('');

  const modal=document.createElement('div');modal.id='po-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:650px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;"><h3 style="font-size:16px;font-weight:600;">Generate Purchase Order from ${pEsc(pr.pr_number)}</h3><button onclick="document.getElementById('po-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Supplier *</label><select id="po-supplier" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${supOpts}</select></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Delivery Date</label><input type="date" id="po-delivery" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    </div>
    <div style="margin-bottom:12px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Notes</label><input id="po-notes" placeholder="PO notes..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:12px;">
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">Item</th><th style="padding:8px;text-align:center;">Qty</th><th style="padding:8px;text-align:right;">Unit Price</th><th style="padding:8px;text-align:right;">Total</th></tr></thead>
        <tbody>${itemsHtml}</tbody>
        <tfoot><tr style="border-top:2px solid var(--gray-200);"><td colspan="3" style="padding:8px;font-weight:700;">Grand Total</td><td style="padding:8px;text-align:right;font-weight:700;font-size:14px;" id="po-grand-total">0</td></tr></tfoot>
      </table>
    </div>
    <button onclick="poCreate(${prId})" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Create Purchase Order</button>
  </div>`;
  document.body.appendChild(modal);
  poCalcTotal();
}

function poCalcTotal(){
  let total=0;
  document.querySelectorAll('.po-qty').forEach((q,i)=>{
    const price=parseFloat(document.querySelectorAll('.po-price')[i]?.value)||0;
    const qty=parseFloat(q.value)||0;
    const lineTotal=qty*price;
    total+=lineTotal;
    const lineTds=document.querySelectorAll('.po-line-total');
    if(lineTds[i]) lineTds[i].textContent=lineTotal.toLocaleString();
  });
  const gt=document.getElementById('po-grand-total');
  if(gt) gt.textContent=total.toLocaleString()+' RWF';
}

async function poCreate(prId){
  const supplier=document.getElementById('po-supplier').value;
  if(!supplier) return alert('Select a supplier.');
  const items=[];
  document.querySelectorAll('.po-qty').forEach((q,i)=>{
    const itemId=q.getAttribute('data-item-id');
    const qty=parseFloat(q.value)||0;
    const price=parseFloat(document.querySelectorAll('.po-price')[i]?.value)||0;
    if(itemId&&qty>0) items.push({item_id:parseInt(itemId),item_description:q.getAttribute('data-name')||'',quantity_ordered:qty,unit_price:price});
  });
  if(!items.length) return alert('No items.');
  try{
    const r=await fetch(pExt+'create_purchase_order',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pr_id:prId,supplier_id:parseInt(supplier),delivery_date:document.getElementById('po-delivery').value,notes:document.getElementById('po-notes').value,items:items})});
    const j=await r.json();
    if(j.success){alert('✓ PO Created: '+(j.data?.po_number||''));document.getElementById('po-modal').remove();arLoad();}else alert(j.message);
  }catch(e){alert('Failed');}
}
arLoad();
</script>

<?php elseif ($subModule == 'purchase-orders'): ?>
<!-- ====== PURCHASE ORDERS ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-invoice" style="color:var(--primary-color);"></i> Purchase Orders</h3>
    <button onclick="poListLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">PO #</th><th style="padding:10px;text-align:left;">Supplier</th><th style="padding:10px;text-align:left;">Date</th><th style="padding:10px;text-align:right;">Total</th><th style="padding:10px;text-align:left;">Status</th><th style="padding:10px;text-align:left;">Delivery</th><th style="padding:10px;text-align:center;">Action</th></tr></thead>
      <tbody id="pol-tbody"><tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>
<script>
const pAPI = _pBase + '/api.php?endpoint=inventory/inventory-management&action=';
function pEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

const poRows={};
async function poListLoad(){
  const r=await fetch(pAPI+'purchase_orders');const j=await r.json();
  const tbody=document.getElementById('pol-tbody');
  if(j.success&&j.data&&j.data.length){
    tbody.innerHTML=j.data.map(po=>{
      poRows[po.id]=po;
      const open=po.status==='confirmed'||po.status==='partially_received';
      const overdue=open&&po.delivery_date&&po.delivery_date<new Date().toISOString().slice(0,10);
      const dBg={pending:'#f3f4f6',in_transit:'#dbeafe',delayed:'#fee2e2'}[po.delivery_status]||'#f3f4f6';
      const delivery=open?`<span style="padding:2px 8px;border-radius:10px;font-size:10px;background:${dBg};">${pEsc((po.delivery_status||'pending').replace('_',' '))}</span>${po.delivery_date?' · due '+pEsc(po.delivery_date):''}${overdue?' <b style="color:#dc2626;font-size:10px;">OVERDUE</b>':''}${po.carrier?'<div style="font-size:11px;color:var(--gray-500);">'+pEsc(po.carrier)+(po.tracking_ref?' · '+pEsc(po.tracking_ref):'')+'</div>':''}${po.delivery_note?'<div style="font-size:11px;color:var(--gray-500);">'+pEsc(po.delivery_note)+'</div>':''}`:(po.status==='received'?'delivered':'—');
      const sBg={draft:'#f3f4f6',sent:'#fef3c7',confirmed:'#dbeafe',received:'#d1fae5',cancelled:'#fee2e2'};
      const sColor={draft:'#374151',sent:'#92400e',confirmed:'#1e40af',received:'#065f46',cancelled:'#991b1b'};
      let actions=`<button onclick="poPrint(${po.id})" style="padding:3px 8px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:3px;" title="View/Print"><i class="fas fa-print"></i></button>`;
      if(po.status==='draft') actions+=`<button onclick="poApprove(${po.id})" style="padding:3px 8px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:3px;">Approve</button><button onclick="poReject(${po.id})" style="padding:3px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;">Reject</button>`;
      else if(open) actions+=`<button onclick="poTrack(${po.id})" style="padding:3px 8px;background:#2563eb;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:3px;">Track</button><button onclick="location.href='inventory.php?module=inventory&sub=goods-receiving&receive='+${po.id}" style="padding:3px 8px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;">Receive</button>`;
      return `<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:8px 10px;font-family:monospace;color:var(--primary-color);font-weight:500;">${pEsc(po.po_number||'')}</td><td style="padding:8px 10px;">${pEsc(po.supplier_name||'—')}</td><td style="padding:8px 10px;font-size:12px;">${po.order_date||'—'}</td><td style="padding:8px 10px;text-align:right;font-weight:600;">${parseFloat(po.total_amount||0).toLocaleString()}</td><td style="padding:8px 10px;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;background:${sBg[po.status]||'#f3f4f6'};color:${sColor[po.status]||'#374151'};">${(po.status||'').replace('_',' ')}</span></td><td style="padding:8px 10px;font-size:12px;">${delivery}</td><td style="padding:8px 10px;text-align:center;">${actions}</td></tr>`;
    }).join('');
  } else tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No purchase orders.</td></tr>';
}

async function poPrint(poId) {
  try {
    const r = await fetch(pAPI + 'purchase_order_detail&id=' + poId);
    const j = await r.json();
    if (!j.success || !j.data) { alert('Failed to load PO details'); return; }
    
    const po = j.data;
    const items = po.items || [];
    
    // Fetch hospital settings
    let hs = {};
    try {
      const hr = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=settings/hospital');
      const hj = await hr.json();
      if (hj.success && hj.data) hs = hj.data;
    } catch(e) { console.error('Hospital settings load error:', e); }
    
    const hospName = hs.hospital_name || 'Iramiro Clinic';
    const today = new Date().toISOString().split('T')[0];
    
    // Document settings from hospital config
    const docLogo = hs.doc_logo || hs.logo || '';
    const hLineColor = hs.header_line_color || '#059669';
    const hLineSize = hs.header_line_size || '2';
    const hTextColor = hs.header_text_color || '#1f2937';
    const hFontSize = hs.header_font_size || '11';
    const hLeftText = hs.header_left || hospName;
    const hRightText = (hs.header_right || 'Date: {date}').replace('{date}', po.order_date||today).replace('{ref}', po.po_number||'');
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
        wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;font-size:${hs.watermark_size||'40'}px;color:${hs.watermark_color||'#d1d5db'};font-weight:700;white-space:nowrap;">${pEsc(hs.watermark_text||hospName||'')}</div>`;
      }
    }
    
    // Build PO HTML
    const statusColors = {draft:'#6b7280',sent:'#d97706',confirmed:'#2563eb',received:'#059669',cancelled:'#dc2626'};
    const statusBg = {draft:'#f3f4f6',sent:'#fef3c7',confirmed:'#dbeafe',received:'#d1fae5',cancelled:'#fee2e2'};
    
    let itemsHtml = '';
    let subtotal = 0;
    items.forEach((it, idx) => {
      const qty = parseFloat(it.quantity_ordered || 0);
      const price = parseFloat(it.unit_price || 0);
      const lineTotal = qty * price;
      subtotal += lineTotal;
      itemsHtml += `<tr><td style="padding:6px 8px;border:1px solid #ddd;text-align:center;">${idx+1}</td><td style="padding:6px 8px;border:1px solid #ddd;">${pEsc(it.item_name || it.item_description || 'Item')}</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:center;">${qty.toLocaleString()}</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">${price.toLocaleString()}</td><td style="padding:6px 8px;border:1px solid #ddd;text-align:right;font-weight:600;">${lineTotal.toLocaleString()}</td></tr>`;
    });
    
    const tax = parseFloat(po.tax_amount || 0);
    const discount = parseFloat(po.discount_amount || 0);
    const total = subtotal + tax - discount;
    
    const printHtml = `
<!DOCTYPE html>
<html><head>
<meta charset="UTF-8">
<title>Purchase Order ${pEsc(po.po_number)}</title>
<style>
  @media print {
    @page { margin: 15mm; size: A4 portrait; }
    body { margin: 0; }
    .no-print { display: none !important; }
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; color: #1f2937; line-height: 1.5; }
  .container { max-width: 210mm; margin: 0 auto; padding: 20px; background: white; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 20px; border-bottom: ${hLineSize}px solid ${hLineColor}; }
  .header-left { flex: 1; }
  .logo { max-width: 120px; max-height: 60px; margin-bottom: 10px; }
  .hospital-name { font-size: 22px; font-weight: 700; color: #1e3a8a; margin-bottom: 4px; }
  .hospital-info { font-size: 11px; color: #6b7280; line-height: 1.6; }
  .header-right { text-align: right; }
  .doc-title { font-size: 28px; font-weight: 700; color: #2563eb; margin-bottom: 8px; }
  .po-number { font-size: 16px; font-weight: 600; color: #374151; margin-bottom: 4px; }
  .po-date { font-size: 12px; color: #6b7280; }
  .status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: ${statusBg[po.status] || '#f3f4f6'}; color: ${statusColors[po.status] || '#374151'}; margin-top: 6px; }
  
  .parties { display: flex; gap: 30px; margin-bottom: 30px; }
  .party-box { flex: 1; padding: 16px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; }
  .party-title { font-size: 11px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
  .party-name { font-size: 15px; font-weight: 700; color: #111827; margin-bottom: 6px; }
  .party-details { font-size: 12px; color: #4b5563; line-height: 1.7; }
  
  .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
  .items-table thead { background: #f3f4f6; }
  .items-table th { padding: 12px 10px; text-align: left; font-size: 11px; font-weight: 700; color: #374151; text-transform: uppercase; border-bottom: 2px solid #d1d5db; }
  .items-table td { padding: 10px; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
  .items-table tbody tr:hover { background: #f9fafb; }
  
  .totals { margin-left: auto; width: 300px; }
  .total-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 13px; }
  .total-row.subtotal { color: #6b7280; }
  .total-row.grand { font-size: 16px; font-weight: 700; color: #111827; padding-top: 12px; border-top: 2px solid #d1d5db; margin-top: 8px; }
  
  .footer { margin-top: 40px; padding-top: 20px; border-top: ${fLineSize}px solid ${fLineColor}; }
  .notes-section { margin-bottom: 20px; }
  .notes-title { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 6px; }
  .notes-content { font-size: 12px; color: #6b7280; padding: 10px; background: #f9fafb; border-radius: 6px; border-left: 3px solid #2563eb; }
  
  .signatures { display: flex; gap: 40px; margin-top: 40px; }
  .signature-box { flex: 1; text-align: center; }
  .signature-line { border-top: 2px solid #111827; padding-top: 6px; margin-top: 50px; font-size: 12px; font-weight: 600; color: #374151; }
  .signature-role { font-size: 11px; color: #6b7280; margin-top: 2px; }
  
  .print-btn { position: fixed; top: 20px; right: 20px; padding: 12px 24px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); z-index: 1000; }
  .print-btn:hover { background: #1e40af; }
</style>
</head><body>
<button class="print-btn no-print" onclick="window.print()"><i class="fas fa-print"></i> Print / Save as PDF</button>

<div class="container">
  ${wmHtml}
  <div style="position:relative;z-index:1;">
  <!-- Header -->
  <img src="assets/letterhead.png" style="display:block;width:100%;height:auto;">
  <div style="display:flex;justify-content:flex-end;margin:4px 0 15px;">
    <span style="font-size:${hFontSize}px;color:${hTextColor};">${pEsc(hRightText)}</span>
  </div>
  
  <!-- Title & Status -->
  <div style="text-align:center;margin-bottom:15px;">
    <h2 style="font-size:18px;font-weight:700;text-decoration:underline;margin-bottom:6px;">PURCHASE ORDER</h2>
    <div style="font-size:14px;font-weight:600;margin-bottom:4px;">PO #: ${pEsc(po.po_number || '')}</div>
    <span style="display:inline-block;padding:3px 10px;border-radius:10px;font-size:10px;font-weight:600;text-transform:uppercase;background:${statusBg[po.status]||'#f3f4f6'};color:${statusColors[po.status]||'#374151'};">${(po.status||'draft').replace('_',' ')}</span>
  </div>
  
  <!-- Parties -->
  <div style="display:flex;gap:20px;margin-bottom:15px;">
    <div style="flex:1;padding:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">
      <div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">SUPPLIER</div>
      <div style="font-size:13px;font-weight:700;margin-bottom:4px;">${pEsc(po.supplier_name || 'N/A')}</div>
      <div style="font-size:11px;color:#6b7280;line-height:1.5;">
        ${po.supplier_contact ? 'Contact: ' + pEsc(po.supplier_contact) + '<br>' : ''}
        ${po.supplier_phone ? 'Phone: ' + pEsc(po.supplier_phone) + '<br>' : ''}
        ${po.supplier_email ? 'Email: ' + pEsc(po.supplier_email) : ''}
      </div>
    </div>
    <div style="flex:1;padding:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">
      <div style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:6px;">DELIVER TO</div>
      <div style="font-size:13px;font-weight:700;margin-bottom:4px;">${pEsc(hospName)}</div>
      <div style="font-size:11px;color:#6b7280;line-height:1.5;">
        ${hs.address ? pEsc(hs.address) + '<br>' : ''}
        ${po.expected_delivery_date ? 'Expected: ' + po.expected_delivery_date : ''}
      </div>
    </div>
  </div>
  
  <!-- Items Table -->
  <table style="width:100%;border-collapse:collapse;margin-bottom:15px;">
    <thead><tr style="background:#f0f0f0;"><th style="padding:6px 8px;text-align:center;border:1px solid #ddd;width:30px;">#</th><th style="padding:6px 8px;text-align:left;border:1px solid #ddd;">Item Description</th><th style="padding:6px 8px;text-align:center;border:1px solid #ddd;width:60px;">Qty</th><th style="padding:6px 8px;text-align:right;border:1px solid #ddd;width:100px;">Unit Price</th><th style="padding:6px 8px;text-align:right;border:1px solid #ddd;width:100px;">Total</th></tr></thead>
    <tbody>${itemsHtml}</tbody>
  </table>
  
  <!-- Totals -->
  <div style="margin-left:auto;width:300px;margin-bottom:20px;">
    <table style="border:none;font-size:12px;">
      <tr><td style="border:none;padding:4px 8px;text-align:right;">Subtotal:</td><td style="border:none;padding:4px 8px;text-align:right;font-weight:600;">${subtotal.toLocaleString()} RWF</td></tr>
      ${tax > 0 ? `<tr><td style="border:none;padding:4px 8px;text-align:right;">Tax:</td><td style="border:none;padding:4px 8px;text-align:right;font-weight:600;">${tax.toLocaleString()} RWF</td></tr>` : ''}
      ${discount > 0 ? `<tr><td style="border:none;padding:4px 8px;text-align:right;">Discount:</td><td style="border:none;padding:4px 8px;text-align:right;font-weight:600;">-${discount.toLocaleString()} RWF</td></tr>` : ''}
      <tr style="border-top:2px solid #333;"><td style="border:none;padding:6px 8px;text-align:right;font-size:14px;font-weight:700;">Grand Total:</td><td style="border:none;padding:6px 8px;text-align:right;font-size:14px;font-weight:700;">${total.toLocaleString()} RWF</td></tr>
    </table>
  </div>
  
  ${po.notes ? `<div style="margin-bottom:20px;"><div style="font-size:11px;font-weight:700;margin-bottom:4px;">NOTES / TERMS:</div><div style="padding:8px;background:#f9fafb;border-left:3px solid ${hLineColor};font-size:11px;color:#6b7280;">${pEsc(po.notes)}</div></div>` : ''}
  
  <!-- Signatures -->
  <div style="display:flex;gap:30px;margin-top:40px;margin-bottom:20px;">
    <div style="flex:1;text-align:center;">
      <div style="border-bottom:1px solid #333;height:40px;margin-bottom:4px;"></div>
      <div style="font-size:10px;font-weight:600;">Prepared By</div>
      <div style="font-size:9px;color:#6b7280;">${po.created_by_name || 'Procurement'}</div>
    </div>
    <div style="flex:1;text-align:center;">
      <div style="border-bottom:1px solid #333;height:40px;margin-bottom:4px;"></div>
      <div style="font-size:10px;font-weight:600;">Approved By</div>
      <div style="font-size:9px;color:#6b7280;">Authorized Signatory</div>
    </div>
    <div style="flex:1;text-align:center;">
      <div style="border-bottom:1px solid #333;height:40px;margin-bottom:4px;"></div>
      <div style="font-size:10px;font-weight:600;">Supplier Signature</div>
      <div style="font-size:9px;color:#6b7280;">Date: ___________</div>
    </div>
  </div>
  
  <!-- Footer -->
  <div style="display:flex;justify-content:space-between;align-items:center;padding-top:6px;border-top:${fLineSize}px solid ${fLineColor};margin-top:20px;">
    <span style="font-size:${fFontSize}px;color:${fTextColor};">${pEsc(fLeftText)}</span>
    <span style="font-size:${fFontSize}px;color:${fTextColor};">${pEsc(fRightText)}</span>
  </div>
  </div>
</div>

</body></html>`;
    
    // Open in new window and trigger print
    const printWindow = window.open('', '_blank');
    printWindow.document.write(printHtml);
    printWindow.document.close();
    printWindow.focus();
    
  } catch(e) {
    console.error('Print error:', e);
    alert('Failed to generate print view: ' + e.message);
  }
}

function poTrack(id){
  const po=poRows[id]; if(!po) return;
  const old=document.getElementById('po-track'); if(old) old.remove();
  const m=document.createElement('div'); m.id='po-track'; m.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;';
  const inp='style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;margin-bottom:10px;"';
  m.innerHTML=`<div style="background:white;border-radius:12px;width:460px;max-width:94vw;padding:22px;"><div style="display:flex;justify-content:space-between;margin-bottom:14px;"><h3 style="font-size:16px;font-weight:600;">Track delivery - ${pEsc(po.po_number)}</h3><button type="button" onclick="document.getElementById('po-track').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    <label style="font-size:12px;font-weight:600;">Delivery status</label><select id="pt-status" ${inp}><option value="pending">Pending</option><option value="in_transit">In transit</option><option value="delayed">Delayed</option></select>
    <label style="font-size:12px;font-weight:600;">Expected delivery date</label><input type="date" id="pt-date" value="${pEsc(po.delivery_date||'')}" ${inp}>
    <label style="font-size:12px;font-weight:600;">Carrier / transporter</label><input id="pt-carrier" value="${pEsc(po.carrier||'')}" ${inp}>
    <label style="font-size:12px;font-weight:600;">Tracking / waybill reference</label><input id="pt-ref" value="${pEsc(po.tracking_ref||'')}" ${inp}>
    <label style="font-size:12px;font-weight:600;">Note (required when delayed)</label><input id="pt-note" value="${pEsc(po.delivery_note||'')}" ${inp}>
    <button id="pt-save" onclick="poTrackSave(${po.id})" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save</button></div>`;
  document.body.appendChild(m);
  document.getElementById('pt-status').value=po.delivery_status||'pending';
}
async function poTrackSave(id){
  const btn=document.getElementById('pt-save'); btn.disabled=true;
  const j=await (await fetch(pExt+'update_po_delivery',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({po_id:id,delivery_status:document.getElementById('pt-status').value,expected_date:document.getElementById('pt-date').value,carrier:document.getElementById('pt-carrier').value,tracking_ref:document.getElementById('pt-ref').value,note:document.getElementById('pt-note').value})})).json();
  btn.disabled=false;
  if(j.success){document.getElementById('po-track').remove();poListLoad();}else alert(j.message);
}
async function poApprove(id){
  const note=await uiPrompt('Approval note (optional):','',{title:'Approve purchase order',placeholder:'Optional note',okLabel:'Approve'});
  if(note===null) return;
  const j=await (await fetch(pExt+'update_po_status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({po_id:id,status:'confirmed',note:note})})).json();
  alert(j.success?'✓ PO Approved!':j.message); poListLoad();
}
async function poReject(id){
  const note=await uiPrompt('Reason for rejection:','',{title:'Reject purchase order',required:true,requiredMessage:'Reason required.',placeholder:'Reason (required)',okLabel:'Reject'});
  if(note===null) return;
  const j=await (await fetch(pExt+'update_po_status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({po_id:id,status:'cancelled',note:note})})).json();
  alert(j.success?'PO Rejected.':j.message); poListLoad();
}
poListLoad();
</script>

<?php elseif (in_array($subModule, ['suppliers','supplier-docs','evaluation','awards','contracts','expected-deliveries','discrepancies','proformas','supplier-invoices','invoice-exceptions','payment-status'], true)): ?>
<?php require __DIR__ . '/includes/procurement_ui.php'; ?>
<?php elseif ($subModule == 'goods-received'): ?>
<!-- ====== GOODS RECEIVED NOTES + RETURNS TO SUPPLIER ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;"><i class="fas fa-dolly-flatbed" style="color:var(--primary-color);"></i> Goods Received Notes</h3>
  <table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);text-align:left;"><th style="padding:8px 10px;">GRN</th><th style="padding:8px 10px;">PO</th><th style="padding:8px 10px;">Supplier</th><th style="padding:8px 10px;">Received</th><th style="padding:8px 10px;">Billed</th><th></th></tr></thead><tbody id="grn-tbody"></tbody></table>
</div>
<script>
const pAPI = _pBase + '/api.php?endpoint=inventory/inventory-management&action=';
function pEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function grnLoad(){
  const j = await (await fetch(pAPI+'grns')).json();
  document.getElementById('grn-tbody').innerHTML = (j.data||[]).length ? j.data.map(g=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:8px 10px;font-family:monospace;">${pEsc(g.grn_number)}</td><td style="padding:8px 10px;">${pEsc(g.po_number||'')}</td><td style="padding:8px 10px;">${pEsc(g.supplier_name||'')}</td><td style="padding:8px 10px;">${pEsc(g.received_date)}</td><td style="padding:8px 10px;">${g.supplier_invoice_id?'Yes':'No'}</td>
    <td style="padding:8px 10px;text-align:right;"><button onclick="grnReturn(${g.id})" style="padding:3px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;">Return to supplier</button></td></tr>`).join('')
    : '<tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);">No goods received yet.</td></tr>';
}
async function grnReturn(id){
  const g = (await (await fetch(pAPI+'grn_detail&id='+id)).json()).data;
  const m = document.createElement('div');
  m.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  m.innerHTML = `<div style="background:white;border-radius:12px;width:560px;max-width:92vw;padding:24px;"><h3 style="font-size:16px;font-weight:600;margin-bottom:4px;">Return goods - ${pEsc(g.grn_number)}</h3>
    <p style="font-size:12px;color:var(--gray-500);margin-bottom:12px;">${pEsc(g.supplier_name||'')} · ${g.supplier_invoice_id?'already billed: a debit note reduces what we owe them':'not billed yet: the bill should be for the kept goods only'}</p>
    <table style="width:100%;font-size:13px;border-collapse:collapse;"><thead><tr style="text-align:left;"><th style="padding:6px;">Item</th><th style="padding:6px;">Accepted</th><th style="padding:6px;">Return qty</th></tr></thead><tbody>
    ${(g.items||[]).map(it=>`<tr><td style="padding:6px;">${pEsc(it.item_name||('#'+it.item_id))}</td><td style="padding:6px;">${it.quantity_accepted}</td><td style="padding:6px;"><input type="number" min="0" max="${it.quantity_accepted}" class="rt-qty" data-item="${it.item_id}" style="width:80px;padding:6px;border:1px solid var(--gray-200);border-radius:6px;"></td></tr>`).join('')}
    </tbody></table>
    <label style="font-size:12px;font-weight:600;display:block;margin:12px 0 4px;">Reason</label><input id="rt-reason" placeholder="e.g. damaged, wrong item, near expiry" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    <div style="display:flex;gap:8px;margin-top:16px;"><button id="rt-go" style="flex:1;padding:10px;background:#dc2626;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Return goods</button><button onclick="this.closest('div[style*=fixed]').remove()" style="padding:10px 16px;border:1px solid var(--gray-200);background:white;border-radius:8px;cursor:pointer;">Cancel</button></div></div>`;
  document.body.appendChild(m);
  m.querySelector('#rt-go').onclick = async function(){
    const items = [...m.querySelectorAll('.rt-qty')].filter(i=>parseFloat(i.value)>0).map(i=>({item_id:+i.dataset.item, quantity:parseFloat(i.value)}));
    if(!items.length) return alert('Enter a quantity to return.');
    this.disabled = true;
    const j = await (await fetch(pExt+'return_goods',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({grn_id:id, items:items, reason:m.querySelector('#rt-reason').value})})).json();
    this.disabled = false;
    alert(j.message); if(j.success){ m.remove(); grnLoad(); }
  };
}
grnLoad();
</script>
<?php elseif ($subModule == 'performance'): ?>
<!-- ====== SUPPLIER PERFORMANCE SCORECARD ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap;">
    <div><h3 style="font-size:16px;font-weight:600;"><i class="fas fa-chart-line" style="color:var(--primary-color);"></i> Supplier performance</h3>
      <p style="font-size:12px;color:var(--gray-500);margin-top:4px;">Score = 50% on-time delivery + 30% quality (accepted / received) + 20% quantity fulfilment. A ≥ 90, B ≥ 75, C ≥ 60, D below 60.</p></div>
    <button onclick="perfLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i> Refresh</button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);text-align:left;"><th style="padding:10px;">Supplier</th><th style="padding:10px;">Orders</th><th style="padding:10px;" class="num">Spend</th><th style="padding:10px;">On-time</th><th style="padding:10px;">Quality</th><th style="padding:10px;">Fulfilment</th><th style="padding:10px;">Score</th><th style="padding:10px;">Last delivery</th></tr></thead>
      <tbody id="perf-tbody"><tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>
<script>
const pAPI = _pBase + '/api.php?endpoint=inventory/inventory-management&action=';
function pEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function perfMoney(n){return Number(n||0).toLocaleString('en-US',{maximumFractionDigits:0});}
function perfRate(v){return v===null||v===undefined?'<span style="color:var(--gray-400);">—</span>':v+'%';}
async function perfLoad(){
  const j = await (await fetch(pExt+'supplier_performance')).json();
  const rows = j.data || [];
  document.getElementById('perf-tbody').innerHTML = rows.length ? rows.map(s=>{
    const grade = s.grade || '—';
    const col = grade==='A'?'#166534;background:#dcfce7':grade==='B'?'#1e40af;background:#dbeafe':grade==='C'?'#92400e;background:#fef3c7':grade==='D'?'#991b1b;background:#fee2e2':'#6b7280;background:#f3f4f6';
    return `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:9px 10px;font-weight:600;">${pEsc(s.company_name)}</td>
      <td style="padding:9px 10px;">${s.orders} <span style="font-size:11px;color:var(--gray-500);">(${s.delivered_orders} delivered)</span></td>
      <td style="padding:9px 10px;text-align:right;">${perfMoney(s.spend)}</td>
      <td style="padding:9px 10px;">${perfRate(s.on_time_rate)}${s.avg_delay_days!==null?`<div style="font-size:11px;color:var(--gray-500);">avg ${s.avg_delay_days>0?'+':''}${s.avg_delay_days}d</div>`:''}</td>
      <td style="padding:9px 10px;">${perfRate(s.quality_rate)}${s.rejected_qty>0?`<div style="font-size:11px;color:#b91c1c;">${s.rejected_qty} rejected</div>`:''}</td>
      <td style="padding:9px 10px;">${perfRate(s.fulfilment_rate)}</td>
      <td style="padding:9px 10px;">${s.score===null?'<span style="color:var(--gray-400);font-size:12px;">No deliveries yet</span>':`<span style="display:inline-block;min-width:44px;text-align:center;padding:3px 8px;border-radius:10px;font-weight:700;color:${col};">${s.score} · ${grade}</span>`}</td>
      <td style="padding:9px 10px;font-size:12px;">${pEsc(s.last_delivery||'—')}</td></tr>`;
  }).join('') : '<tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);">No suppliers.</td></tr>';
}
perfLoad();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
