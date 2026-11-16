<script>
function nEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
// --- Read-only visit view (View button on each admitted patient) ---
function nsViewTable(title, color, heads, rows){
  if(!rows.length) return '';
  return `<div style="padding:12px 0;border-bottom:1px solid var(--gray-200);">
    <div style="font-size:13px;font-weight:700;color:${color};margin-bottom:8px;">${title} (${rows.length})</div>
    <div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead><tr style="background:#f8fafc;color:var(--gray-600);text-align:left;border-bottom:1px solid var(--gray-200);">${heads.map(h=>`<th style="padding:6px 10px;">${h}</th>`).join('')}</tr></thead>
      <tbody>${rows.map(r=>`<tr style="border-bottom:1px solid var(--gray-100);">${r.map(c=>`<td style="padding:8px 10px;">${c}</td>`).join('')}</tr>`).join('')}</tbody>
    </table></div></div>`;
}
async function nsViewVisit(visitId, name, loc, extra){
  let m = document.getElementById('ns-view-modal');
  if(!m){
    m = document.createElement('div');
    m.id = 'ns-view-modal';
    m.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;';
    m.onclick = e => { if(e.target===m) m.style.display='none'; };
    document.body.appendChild(m);
  }
  m.style.display = 'flex';
  m.innerHTML = '<div style="background:white;border-radius:12px;width:900px;max-width:94vw;max-height:88vh;overflow-y:auto;padding:20px;"><div style="text-align:center;color:var(--gray-400);">Loading...</div></div>';
  const base = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=consultation/consult';
  let d = {}, recs = [];
  try{
    const [r1, r2] = await Promise.all([
      fetch(base+'&action=visit_orders_summary&visit_id='+visitId).then(r=>r.json()),
      fetch(base+'&action=nursing_records&visit_id='+visitId+'&limit=1000').then(r=>r.json())
    ]);
    if(!r1.success) throw new Error(r1.message||'Could not load visit');
    d = r1.data||{}; recs = r2.success ? (r2.data||[]) : [];
  } catch(e){
    m.firstChild.innerHTML = '<div style="color:#dc2626;">Error: '+nEsc(e.message)+'</div>';
    return;
  }
  const v = d.visit||{}, num = x => x ? parseFloat(x).toLocaleString() : '—';
  const badge = (txt, bg, fg) => `<span style="background:${bg};color:${fg};padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;">${nEsc(txt)}</span>`;
  const done = s => ['completed','dispensed'].includes(s) ? badge(s,'#dcfce7','#166534') : badge(s||'ordered','#eff6ff','#1e40af');
  const sample = s => s==='ordered' ? 'Not collected' : s==='no_sample' ? 'Not taken' : ['in_progress','completed'].includes(s) ? 'Collected' : nEsc(s||'—');
  const paid = (bal) => +bal>0 ? badge('Unpaid · '+Math.round(+bal).toLocaleString(),'#fee2e2','#991b1b') : badge('Paid','#d1fae5','#065f46');
  let html = `<div style="display:flex;justify-content:space-between;margin-bottom:12px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-eye" style="color:var(--primary-color);"></i> ${nEsc(name)}</h3>
      <button onclick="document.getElementById('ns-view-modal').style.display='none'" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="font-size:12px;color:var(--gray-600);margin-bottom:8px;">Ward / Room / Bed: <strong>${nEsc(loc)}</strong> &nbsp;|&nbsp; Visit: <strong>${nEsc(v.visit_no||visitId)}</strong> &nbsp;|&nbsp; Status: <strong>${nEsc(v.status||'—')}</strong> &nbsp;|&nbsp; Payment: <strong>${nEsc(v.payment_type||'—')}</strong>${v.company_name?' &nbsp;|&nbsp; Insurance: <strong>'+nEsc(v.company_name)+'</strong>':''}</div>${extra||''}`;
  html += nsViewTable('Medications','#166534',['Medication','Dosage & Frequency','Qty','Total (RWF)','Status','Prescribed by'],
    (d.pharmacy||[]).map(i=>[nEsc(i.medication_name),nEsc(i.dosage||'—')+(i.frequency?' · '+nEsc(i.frequency):''),i.quantity||1,num(i.invoice_total),done(i.item_status),nEsc(i.prescribed_by_name||'—')]));
  html += nsViewTable('Lab Orders','#1e40af',['Test','Price (RWF)','Order status','Sample','Ordered by'],
    (d.labs||[]).map(i=>[nEsc(i.test_name),num(i.unit_price),done(i.status),sample(i.status),nEsc(i.ordered_by_name||'—')]));
  html += nsViewTable('Imaging','#6d28d9',['Study','Price (RWF)','Status','Ordered by'],
    (d.imaging||[]).map(i=>[nEsc(i.imaging_name),num(i.unit_price),done(i.status),nEsc(i.ordered_by_name||'—')]));
  html += nsViewTable('Procedures','#9d174d',['Procedure','Qty','Total (RWF)'],
    (d.procedures||[]).map(i=>[nEsc(i.procedure_name),i.quantity||1,num(i.total)]));
  html += nsViewTable('Billed Items','#92400e',['Item','Category','Qty','Unit (RWF)','Total (RWF)','Invoice','Payment','Added by'],
    (d.billed_items||[]).map(i=>[nEsc(i.description),nEsc(i.category||''),i.quantity||1,num(i.unit_price),num(i.total),nEsc(i.invoice_no||''),paid(i.balance),nEsc(i.added_by_name||'—')]));
  html += nsViewTable('Vitals & Nursing Records','#0f766e',['When','Type','Record','By'],
    recs.map(r=>[new Date(r.administered_at||r.created_at).toLocaleString(),nEsc(r.record_type),nEsc(r.description),nEsc(r.nurse_name||'')]));
  m.firstChild.innerHTML = html;
}
</script>
