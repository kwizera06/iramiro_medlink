// report-general.js
const _rptBase = (typeof _apiBase !== 'undefined' ? _apiBase : (typeof rptBasePath !== 'undefined' ? rptBasePath + '/api.php' : 'api.php'));
const rptAPI = _rptBase + '?endpoint=reports/general';
const rptInsAPI = _rptBase + '?endpoint=insurance/companies';
const rptPaymentMethodsAPI = _rptBase + '?endpoint=reports/general&action=payment_methods';
let rptPage=1;
let rptLastParams=null;
function rFmt(n){return Number(n||0).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0});}
function rEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

const rptSpecialityAPI = _rptBase + '?endpoint=reports/general&action=specialities';

// Load insurance dropdown + payment methods + speciality dropdown
async function rptInitFilters(){
  try{
    const r=await fetch(rptInsAPI);const j=await r.json();
    const allCompanies=j.data?.companies||j.data||[];
    const insuranceCompanies=allCompanies.filter(c=>{
      const n=(c.name||'').toLowerCase();
      const code=(c.code||'').toLowerCase();
      return n!=='private' && n!=='privé' && n!=='prive' && code!=='private' && c.id!=13;
    });
    document.getElementById('rpt-ins').innerHTML='<option value="">All</option><option value="private">Private (Self-Pay)</option>'+insuranceCompanies.map(c=>`<option value="${c.id}">${rEsc(c.name)}</option>`).join('');
  }catch(e){}
  // Payment methods from invoices and payment records
  try{
    const r=await fetch(rptPaymentMethodsAPI);const j=await r.json();
    const methods=j.data||[];
    const map = {
      'cash': 'Cash', 'mobile_money': 'Mobile Money', 'mtn_momo': 'MTN MoMo',
      'airtel_money': 'Airtel Money', 'bank': 'Bank Transfer', 'bank_transfer': 'Bank Transfer',
      'card': 'Card / POS', 'insurance': 'Insurance', 'unpaid': 'Unpaid'
    };
    const methodSelect=document.getElementById('rpt-method');
    if(methodSelect){
      methodSelect.innerHTML='<option value="">All Methods</option>'+methods.map(m=>`<option value="${rEsc(m)}">${rEsc(map[m]||m.replace(/_/g,' '))}</option>`).join('');
    }
  }catch(e){}
  // Load specialities into dropdown
  try{
    const r=await fetch(rptSpecialityAPI);const j=await r.json();
    const specs=j.data||[];
    const grp=document.getElementById('rpt-cat-speciality-group');
    if(grp&&specs.length){
      grp.innerHTML=specs.map(s=>`<option value="${rEsc(s)}">${rEsc(s)}</option>`).join('');
    }
  }catch(e){}
}

async function rptLoad(resetPage){
  if(resetPage) rptPage=1;
  const dateFrom=document.getElementById('rpt-from').value;
  const dateTo=document.getElementById('rpt-to').value;
  const insId=document.getElementById('rpt-ins').value;
  const cat=document.getElementById('rpt-cat').value;
  const pay=document.getElementById('rpt-pay').value;
  const method=document.getElementById('rpt-method')?.value||'';

  const p=new URLSearchParams({date_from:dateFrom,date_to:dateTo,page:rptPage,limit:100});
  if(insId && insId!=='private') p.append('insurance_id',insId);
  if(insId==='private') p.append('payment_type','self_pay');
  if(cat) p.append('category',cat);
  if(pay) p.append('payment_status',pay);
  if(method) p.append('payment_method',method);
  rptLastParams=new URLSearchParams(p);

  const tb=document.getElementById('rpt-tbody');
  tb.innerHTML='<tr><td colspan="14" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

  try{
    const r=await fetch(rptAPI+'&'+p);
    if(!r.ok){
      tb.innerHTML='<tr><td colspan="14" style="padding:30px;text-align:center;color:var(--danger-color);"><i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>Server error ('+r.status+'). Try refreshing the page.</td></tr>';
      return;
    }
    const j=await r.json();
    if(!j.success){tb.innerHTML='<tr><td colspan="14" style="padding:30px;text-align:center;color:var(--danger-color);">'+rEsc(j.message)+'</td></tr>';return;}
    const{invoices,stats,pagination}=j.data;

    // Stats
    document.getElementById('rpt-s-invoices').textContent=stats.total_invoices||0;
    document.getElementById('rpt-s-total').textContent=rFmt(stats.total_amount)+' RWF';
    document.getElementById('rpt-s-paid').textContent=rFmt(stats.total_paid)+' RWF';
    const sBalVal = parseFloat(stats.total_balance || 0);
    document.getElementById('rpt-s-balance').textContent=rFmt(sBalVal <= 1.05 ? 0 : sBalVal)+' RWF';
    document.getElementById('rpt-s-insowes').textContent=rFmt(stats.insurance_owes)+' RWF';
    document.getElementById('rpt-s-paidcount').textContent=(stats.paid_count||0)+' Paid';
    document.getElementById('rpt-s-unpaidcount').textContent=(stats.unpaid_count||0)+' Unpaid';
    document.getElementById('rpt-s-partialcount').textContent=(stats.partial_count||0)+' Partial';
    document.getElementById('rpt-s-inscount').textContent=(stats.insurance_count||0)+' Insurance';
    document.getElementById('rpt-s-selfcount').textContent=(stats.selfpay_count||0)+' Self-Pay';
    // Written-off stat (optional element)
    const woEl=document.getElementById('rpt-s-writtenoff');
    if(woEl) woEl.textContent=rFmt(stats.total_writtenoff||0)+' RWF';
    // Count invoices with written-off records from fetched data
    const woCount=invoices.filter(inv=>(inv.writtenoff_amount||0)>0).length;
    const woBadge=document.getElementById('rpt-s-writtenoff-count');
    if(woBadge){
      if(woCount>0){ woBadge.textContent=woCount+' Written Off'; woBadge.style.display='inline'; }
      else { woBadge.style.display='none'; }
    }

    // Payment methods breakdown (in summary tab)
    const pmCards=document.getElementById('rpt-pm-cards');
    if(stats.payment_methods && stats.payment_methods.length){
      const pmColors={cash:'#16a34a',bank:'#2563eb',mobile_money:'#d97706',mtn_momo:'#d97706',insurance:'#7c3aed',unpaid:'#dc2626'};
      const pmIcons={cash:'fas fa-money-bill-wave',bank:'fas fa-university',mobile_money:'fas fa-mobile-alt',mtn_momo:'fas fa-mobile-alt',insurance:'fas fa-shield-alt',unpaid:'fas fa-clock'};
      pmCards.innerHTML=stats.payment_methods.map(pm=>{
        const color=pmColors[pm.method]||'#6b7280';
        const icon=pmIcons[pm.method]||'fas fa-receipt';
        return `<div onclick="rptShowDetail('pm',this.dataset.k)" data-k="${rEsc(pm.method||'unpaid')}" title="Click for details" style="cursor:pointer;padding:14px;background:${color}08;border:1px solid ${color}30;border-radius:10px;text-align:center;">
          <i class="${icon}" style="font-size:20px;color:${color};display:block;margin-bottom:6px;"></i>
          <div style="font-size:20px;font-weight:700;color:${color};">${pm.cnt}</div>
          <div style="font-size:11px;color:var(--gray-500);text-transform:capitalize;margin-bottom:4px;">${(pm.method||'unpaid').replace(/_/g,' ')}</div>
          <div style="font-size:12px;font-weight:600;color:var(--gray-700);">${rFmt(pm.amount)} RWF</div>
        </div>`;
      }).join('');
    } else { pmCards.innerHTML='<div style="color:var(--gray-400);font-size:13px;">No payment data</div>'; }

    // Services breakdown (in summary tab)
    // NOTE: When category filter is applied, invoice_items are already filtered by backend
    // So we only count what's returned in inv.items
    const svcCards=document.getElementById('rpt-svc-cards');
    // Count by category from filtered invoices only
    const svcMap={};
    invoices.forEach(inv=>{
      if(inv.items && inv.items.length){
        inv.items.forEach(it=>{
          const cat=it.category||'other';
          if(!svcMap[cat]) svcMap[cat]={category:cat,count:0,total:0};
          svcMap[cat].count+=parseInt(it.quantity)||1;
          svcMap[cat].total+=parseFloat(it.total)||0;
        });
      }
    });
    const svcList=Object.values(svcMap).sort((a,b)=>b.total-a.total);
    const svcColors={consultation:'#2563eb',lab:'#0891b2',procedure:'#7c3aed',pharmacy:'#16a34a',radiology:'#d97706',other:'#6b7280'};
    const svcIcons={consultation:'fas fa-user-md',lab:'fas fa-flask',procedure:'fas fa-syringe',pharmacy:'fas fa-pills',radiology:'fas fa-x-ray',other:'fas fa-file-medical'};
    if(svcList.length){
      svcCards.innerHTML=svcList.map(s=>{
        const color=svcColors[s.category]||'#6b7280';
        const icon=svcIcons[s.category]||'fas fa-file';
        return `<div onclick="rptShowDetail('svc',this.dataset.k)" data-k="${rEsc(s.category)}" title="Click for details" style="cursor:pointer;padding:14px;background:${color}08;border:1px solid ${color}30;border-radius:10px;text-align:center;">
          <i class="${icon}" style="font-size:20px;color:${color};display:block;margin-bottom:6px;"></i>
          <div style="font-size:20px;font-weight:700;color:${color};">${s.count}</div>
          <div style="font-size:11px;color:var(--gray-500);text-transform:capitalize;margin-bottom:4px;">${s.category}</div>
          <div style="font-size:12px;font-weight:600;color:var(--gray-700);">${rFmt(s.total)} RWF</div>
        </div>`;
      }).join('');
    } else { svcCards.innerHTML='<div style="color:var(--gray-400);font-size:13px;">No service data</div>'; }

    if(!invoices.length){
      const activeFilters=[];
      if(cat) activeFilters.push('Category: '+cat);
      if(pay) activeFilters.push('Status: '+pay);
      if(method) activeFilters.push('Method: '+method.replace(/_/g,' '));
      if(insId==='private') activeFilters.push('Private only');
      else if(insId) activeFilters.push('Insurance ID: '+insId);
      const filterNote=activeFilters.length?'<div style="font-size:12px;margin-top:8px;color:var(--gray-500);">Active filters: '+activeFilters.join(', ')+'</div>':'';
      tb.innerHTML='<tr><td colspan="14" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-file-invoice" style="font-size:36px;display:block;margin-bottom:8px;"></i>No invoices found for '+dateFrom+' to '+dateTo+'.'+filterNote+'</td></tr>';
      document.getElementById('rpt-tfoot').style.display='none';
      return;
    }

    // Use visits from server if available (for exact continuous pagination) or group from invoices
    let visits = [];
    if (j.data.visits && Array.isArray(j.data.visits)) {
      visits = j.data.visits;
    } else {
      const visitGroups={};
      invoices.forEach(inv=>{
        const key=inv.visit_id||inv.invoice_no;
        if(!visitGroups[key]){
          visitGroups[key]={
            visit_id:inv.visit_id,
            visit_no:inv.visit_no||'',
            patient_name:(inv.patient_first||'')+' '+(inv.patient_last||''),
            patient_no:inv.patient_no||'',
            payment_type:inv.payment_type,
            payment_method:inv.payment_method||null,
            insurance_name:inv.insurance_name||'',
            copayment_percentage:inv.copayment_percentage!=null?parseFloat(inv.copayment_percentage):null,
            date:(inv.visit_date||'').slice(0,10),
            invoices:[],
            total:0,
            paid:0,
            deferred:0,
            balance:0,
            ins_owes:0
          };
        }
        if(inv.payment_method) visitGroups[key].payment_method=inv.payment_method;
        if(inv.insurance_name && !visitGroups[key].insurance_name) visitGroups[key].insurance_name=inv.insurance_name;
        if(inv.copayment_percentage!=null && visitGroups[key].copayment_percentage==null)
          visitGroups[key].copayment_percentage=parseFloat(inv.copayment_percentage);
        visitGroups[key].invoices.push(inv);
        visitGroups[key].total+=parseFloat(inv.total||0);
        visitGroups[key].paid   += parseFloat(inv.paid_amount||0);
        visitGroups[key].deferred += parseFloat(inv.writtenoff_amount||0);
        const invB = parseFloat(inv.balance||0);
        const invIsPaid = inv.status === 'paid' || invB <= 1.05;
        visitGroups[key].balance += invIsPaid ? 0 : invB;
      });
      visits = Object.values(visitGroups);
    }
    const stColors={paid:'var(--success-color)',partial:'var(--warning-color)',issued:'var(--primary-color)',draft:'var(--gray-400)',cancelled:'var(--danger-color)'};

    tb.innerHTML=visits.map((vg,idx)=>{
      const rowNum = (pagination.page - 1) * pagination.limit + idx + 1;
      const allPaid=vg.invoices.length > 0 && vg.invoices.every(i=>i.status==='paid' || parseFloat(i.balance||0) <= 1.05);
      const anyPartial=vg.invoices.some(i=>i.status==='partial' && parseFloat(i.balance||0) > 1.05);
      const anyDeferred=vg.deferred>0;
      if (allPaid || vg.balance <= 1.05) {
        vg.balance = 0;
      }
      const overallStatus=(allPaid || vg.balance <= 0) ? 'paid' : (vg.paid > 0 || vg.deferred > 0 || anyPartial) ? 'partial' : 'issued';
      const isPrivate = vg.is_private ?? (vg.payment_type === 'self_pay' || vg.company_id == 14 || (vg.insurance_name||'').toLowerCase().includes('prive') || (vg.insurance_name||'').toLowerCase().includes('private') || !vg.insurance_name);
      const isInsVisit = !isPrivate && (vg.payment_type==='insurance'||vg.payment_type==='mixed');
      const copayPct = isPrivate ? 100 : (vg.copayment_percentage != null ? parseFloat(vg.copayment_percentage) : 100);

      // insOwes = total − patient share per invoice (top-ups stay with the patient), same as server stats
      let insOwes = 0;
      if(isInsVisit && copayPct < 100){
        insOwes = Math.round(vg.invoices.reduce((s,i)=>s+Math.max(0, parseFloat(i.total||0) - parseFloat(i.patient_share||0)),0));
      }

      const pmColor={cash:'#16a34a',bank_transfer:'#2563eb',bank:'#2563eb',mobile_money:'#d97706',mtn_momo:'#d97706',airtel_money:'#dc2626',insurance:'#7c3aed',card:'#0891b2',unpaid:'#dc2626',mixed:'#6b7280'};
      const pmIcon={cash:'fa-money-bill-wave',bank_transfer:'fa-university',bank:'fa-university',mobile_money:'fa-mobile-alt',mtn_momo:'fa-mobile-alt',airtel_money:'fa-mobile-alt',insurance:'fa-shield-alt',card:'fa-credit-card',unpaid:'fa-clock',mixed:'fa-layer-group'};

      function formatPayMethod(method, fallbackMethod){
        let m = method || fallbackMethod || 'cash';
        if((m === 'mobile_money' || m === 'cash' || !m) && fallbackMethod && (fallbackMethod === 'mtn_momo' || fallbackMethod === 'airtel_money' || fallbackMethod.includes('momo'))){
          m = fallbackMethod;
        }
        const map = {
          'mtn_momo': 'MTN MoMo',
          'airtel_money': 'Airtel Money',
          'mobile_money': 'Mobile Money',
          'cash': 'Cash',
          'card': 'Card / POS',
          'bank_transfer': 'Bank Transfer',
          'bank': 'Bank Transfer',
          'insurance': 'Insurance',
          'unpaid': 'Unpaid / Credit',
          'written_off': 'Deferred / Written off'
        };
        return map[m] || m.replace(/_/g, ' ');
      }

      let detailHtml=vg.invoices.map(inv=>{
        const invPaid   = parseFloat(inv.paid_amount || 0);
        const invDeferred= parseFloat(inv.writtenoff_amount || 0);
        const rawBalance= parseFloat(inv.balance || 0);
        const isInvFullyPaid = inv.status === 'paid' || rawBalance <= 1.05;
        const invBalance= isInvFullyPaid ? 0 : rawBalance;
        const invTotal  = parseFloat(inv.total || 0);

        let itemsTable='';
        if(inv.items&&inv.items.length){
          itemsTable=`<table style="width:100%;border-collapse:collapse;font-size:11px;margin-top:6px;">
            <thead><tr style="background:#f8fafc;border-bottom:1px solid var(--gray-200);">
              <th style="padding:4px 8px;text-align:left;">Description</th>
              <th style="padding:4px 8px;text-align:center;">Category</th>
              <th style="padding:4px 8px;text-align:center;">Qty</th>
              <th style="padding:4px 8px;text-align:right;">Price</th>
              <th style="padding:4px 8px;text-align:right;">Total</th>
            </tr></thead><tbody>
            ${inv.items.map(it=>`<tr style="border-bottom:1px solid var(--gray-100);">
              <td style="padding:4px 8px;">${rEsc(it.description)}</td>
              <td style="padding:4px 8px;text-align:center;"><span style="padding:1px 6px;border-radius:8px;font-size:9px;background:#f3e8ff;color:#6b21a8;">${rEsc(it.category)}</span></td>
              <td style="padding:4px 8px;text-align:center;">${it.quantity}</td>
              <td style="padding:4px 8px;text-align:right;">${rFmt(it.unit_price)}</td>
              <td style="padding:4px 8px;text-align:right;font-weight:600;">${rFmt(it.total)}</td>
            </tr>`).join('')}
            </tbody></table>`;
        }

        // Payment records breakdown
        let paymentsHtml='';
        if(inv.payment_records&&inv.payment_records.length){
          const rows=inv.payment_records.map(pr=>{
            let m=pr.payment_method||inv.payment_method||'cash';
            if((m === 'mobile_money' || m === 'cash') && inv.payment_method && (inv.payment_method === 'mtn_momo' || inv.payment_method === 'airtel_money')){
              m = inv.payment_method;
            }
            const isDeferred=pr.status==='written_off';
            const c=isDeferred?'#dc2626':(pmColor[m]||'#6b7280');
            const ic=isDeferred?'fa-clock':(pmIcon[m]||'fa-receipt');
            const displayMethod=isDeferred?'Deferred / Written off':formatPayMethod(pr.payment_method, inv.payment_method);
            return `<div style="display:flex;align-items:center;justify-content:space-between;padding:4px 8px;border-radius:5px;background:${c}10;border:1px solid ${c}25;margin-bottom:3px;">
              <div style="display:flex;align-items:center;gap:6px;">
                <i class="fas ${ic}" style="color:${c};font-size:10px;width:12px;"></i>
                <span style="font-size:11px;font-weight:600;color:${c};">${displayMethod}</span>
                ${pr.receipt_number?`<span style="font-size:10px;color:var(--gray-400);font-family:monospace;">${rEsc(pr.receipt_number)}</span>`:''}
                ${pr.payment_date?`<span style="font-size:10px;color:var(--gray-400);">${pr.payment_date}</span>`:''}
                ${isDeferred?`<span style="font-size:9px;font-weight:700;padding:1px 5px;background:#fee2e2;color:#991b1b;border-radius:4px;margin-left:2px;">DEFERRED</span>`:''}
              </div>
              <span style="font-size:11px;font-weight:700;color:${c};">${rFmt(pr.amount_paid)} RWF</span>
            </div>`;
          }).join('');
          // Summary line
          const summaryParts=[];
          if(invPaid>0) summaryParts.push(`<span style="color:#16a34a;font-size:11px;"><i class="fas fa-check-circle"></i> Paid: <strong>${rFmt(invPaid)}</strong></span>`);
          if(invDeferred>0) summaryParts.push(`<span style="color:#dc2626;font-size:11px;"><i class="fas fa-clock"></i> Deferred: <strong>${rFmt(invDeferred)}</strong></span>`);
          if(invBalance>0) summaryParts.push(`<span style="color:#d97706;font-size:11px;"><i class="fas fa-exclamation-circle"></i> Balance: <strong>${rFmt(invBalance)}</strong></span>`);
          paymentsHtml=`<div style="margin-top:6px;padding:6px 8px;background:white;border:1px solid var(--gray-200);border-radius:6px;">
            <div style="font-size:10px;font-weight:600;color:var(--gray-500);margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em;"><i class="fas fa-receipt" style="margin-right:4px;"></i>Payments</div>
            ${rows}
            ${summaryParts.length?`<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px;padding-top:4px;border-top:1px solid var(--gray-100);">${summaryParts.join('')}</div>`:''}
          </div>`;
        } else if(invPaid>0){
          const m=inv.payment_method||'cash';
          const c=pmColor[m]||'#6b7280';
          const displayMethod=formatPayMethod(inv.payment_method);
          paymentsHtml=`<div style="margin-top:6px;padding:6px 8px;background:white;border:1px solid var(--gray-200);border-radius:6px;">
            <div style="display:flex;justify-content:space-between;padding:4px 8px;border-radius:5px;background:${c}10;border:1px solid ${c}25;font-size:11px;">
              <span style="font-weight:600;color:${c};">${displayMethod}</span>
              <span style="font-weight:700;color:${c};">${rFmt(invPaid)} RWF</span>
            </div>
          </div>`;
        }

        // Invoice status badge with deferred indicator
        const invStatusColor={paid:'#16a34a',partial:'#d97706',issued:'#2563eb',draft:'#6b7280',cancelled:'#dc2626'};
        const statusLabel=isInvFullyPaid?'PAID':(invDeferred>0?'PARTIAL / DEFERRED':(inv.status||'').toUpperCase());
        const statusColor=isInvFullyPaid?'#16a34a':(invDeferred>0?'#d97706':(invStatusColor[inv.status]||'#6b7280'));

        return `<div style="background:var(--gray-50);border:1px solid ${invDeferred>0?'#fed7aa':'var(--gray-200)'};border-left:4px solid ${invDeferred>0?'#d97706':invBalance>0?'#dc2626':'#16a34a'};border-radius:6px;padding:10px 12px;margin-bottom:8px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <span style="font-family:monospace;font-weight:600;color:var(--primary-color);font-size:12px;">${rEsc(inv.invoice_no)}</span>
            <span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${statusColor}20;color:${statusColor};">${statusLabel}</span>
          </div>
          <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--gray-600);margin-bottom:4px;">
            <span>Total: <strong>${rFmt(invTotal)}</strong> RWF</span>
            ${invPaid>0?`<span style="color:#16a34a;">Paid: <strong>${rFmt(invPaid)}</strong></span>`:''}
            ${invDeferred>0?`<span style="color:#dc2626;"><i class="fas fa-clock" style="margin-right:2px;"></i>Deferred: <strong>${rFmt(invDeferred)}</strong></span>`:''}
            ${invBalance>0?`<span style="color:#d97706;">Balance: <strong>${rFmt(invBalance)}</strong></span>`:''}
          </div>
          ${paymentsHtml}
          ${itemsTable}
        </div>`;
      }).join('');

      return `<tr style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onclick="document.getElementById('rpt-detail-${idx}').style.display=document.getElementById('rpt-detail-${idx}').style.display==='none'?'table-row':'none'" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <td style="padding:10px 12px;text-align:center;font-size:12px;color:var(--gray-500);font-weight:600;">${rowNum}</td>
        <td style="padding:10px 12px;font-family:monospace;font-weight:600;color:var(--primary-color);font-size:12px;">${rEsc(vg.visit_no||'—')}</td>
        <td style="padding:10px 12px;font-size:12px;">${rEsc(vg.date)}</td>
        <td style="padding:10px 12px;"><div style="font-weight:600;font-size:12px;">${rEsc(vg.patient_name)}</div><div style="font-size:11px;color:var(--gray-500);">${rEsc(vg.patient_no)}</div></td>
        <td style="padding:10px 12px;font-size:12px;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${!isPrivate?'#dbeafe':'#f3f4f6'};color:${!isPrivate?'#1e40af':'#6b21a8'};">${!isPrivate?'<i class="fas fa-shield-alt" style="margin-right:3px;"></i>Insurance':'Private'}</span></td>
        <td style="padding:10px 12px;font-size:12px;">${!isPrivate?`<div style="font-weight:600;color:var(--gray-900);">${rEsc(vg.insurance_name)}</div>${copayPct!=null?`<span style="padding:1px 6px;border-radius:8px;font-size:10px;background:#ede9fe;color:#7c3aed;font-weight:600;">${copayPct}% Copay</span>`:''}`:'<span style="color:var(--gray-500);">Private (100%)</span>'}</td>
        <td style="padding:10px 12px;text-align:center;font-size:11px;">${vg.payment_method?'<span style="padding:2px 8px;border-radius:10px;background:#f3f4f6;color:#374151;font-weight:600;">'+formatPayMethod(vg.payment_method)+'</span>':'<span style="color:var(--gray-400);">—</span>'}</td>
        <td style="padding:10px 12px;text-align:right;font-weight:700;">${rFmt(vg.total)}</td>
        <td style="padding:10px 12px;text-align:right;color:var(--success-color);">${rFmt(vg.paid)}</td>
        <td style="padding:10px 12px;text-align:right;color:#dc2626;font-weight:${vg.deferred>0?'600':'400'};">${vg.deferred>0?'<span style="background:#fee2e2;padding:2px 6px;border-radius:6px;font-size:11px;"><i class="fas fa-clock" style="margin-right:3px;"></i>'+rFmt(vg.deferred)+'</span>':'—'}</td>
        <td style="padding:10px 12px;text-align:right;color:var(--danger-color);font-weight:${vg.balance>0?'700':'400'};">${vg.balance>0?rFmt(vg.balance):'0'}</td>
        <td style="padding:10px 12px;text-align:right;color:var(--warning-color);font-weight:600;">${insOwes>0?rFmt(insOwes):'—'}</td>
        <td style="padding:10px 12px;text-align:center;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${stColors[overallStatus]||'#ccc'}20;color:${stColors[overallStatus]||'#555'};">${overallStatus}${anyDeferred?' <i class="fas fa-clock" title="Has deferred amount"></i>':''}</span></td>
        <td style="padding:10px 12px;text-align:center;"><span style="background:var(--primary-light);color:var(--primary-color);padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">${vg.invoices.length}</span></td>
      </tr>
      <tr id="rpt-detail-${idx}" style="display:none;">
        <td colspan="14" style="padding:12px 20px;background:#fafafa;border-bottom:2px solid var(--gray-200);">
          <div style="background:white;border:1px solid var(--gray-200);border-radius:8px;padding:10px 14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
              <span style="font-weight:700;font-size:13px;color:var(--gray-900);">${rEsc(vg.patient_name)}</span>
              <span style="font-size:11px;color:var(--gray-500);margin-left:6px;">UHID: ${rEsc(vg.patient_no)}</span>
              <span style="font-size:11px;font-family:monospace;color:var(--primary-color);margin-left:8px;font-weight:600;">${rEsc(vg.visit_no)}</span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;font-size:12px;">
              <div>
                <span style="color:var(--gray-500);">Payment:</span>
                <span style="font-weight:600;color:${isPrivate?'#6b7280':'#1e40af'};">${isPrivate?'Private (Self-Pay)':('Insurance ('+rEsc(vg.insurance_name)+(vg.package_name?' • '+rEsc(vg.package_name):'')+')')}</span>
              </div>
              <div>
                <span style="color:var(--gray-500);">Cost Split:</span>
                <span style="font-weight:700;color:#dc2626;">Patient ${copayPct}%</span>
                ${!isPrivate?`<span style="color:#059669;font-weight:700;margin-left:4px;">Ins ${Math.round(100 - copayPct)}%</span>`:''}
              </div>
            </div>
          </div>
          <div style="font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:8px;"><i class="fas fa-file-invoice-dollar" style="color:var(--primary-color);margin-right:6px;"></i>Invoices for this visit (${vg.invoices.length})</div>
          ${detailHtml}
        </td>
      </tr>`;
    }).join('');

    // Footer totals — deferred column shows written-off amounts
    const totalDeferred = stats.total_writtenoff || visits.reduce((s,vg)=>s+(vg.deferred||0),0);
    document.getElementById('rpt-tfoot').style.display='';
    document.getElementById('rpt-f-total').textContent=rFmt(stats.total_amount)+' RWF';
    document.getElementById('rpt-f-paid').textContent=rFmt(stats.total_paid)+' RWF';
    document.getElementById('rpt-f-deferred').textContent=totalDeferred>0?rFmt(totalDeferred)+' RWF':'—';
    const totBal = parseFloat(stats.total_balance || 0);
    document.getElementById('rpt-f-balance').textContent=rFmt(totBal <= 1.05 ? 0 : totBal)+' RWF';
    document.getElementById('rpt-f-insowes').textContent=rFmt(stats.insurance_owes)+' RWF';

    // Pagination
    const pg=document.getElementById('rpt-pagination');
    if(pagination&&pagination.total_pages>1){
      let btns = '';
      let sp = Math.max(1, pagination.page - 2), ep = Math.min(pagination.total_pages, sp + 4);
      if (ep - sp < 4) sp = Math.max(1, ep - 4);
      for (let i = sp; i <= ep; i++) {
        const active = i === pagination.page;
        btns += `<button onclick="rptPage=${i};rptLoad()" style="padding:6px 12px;border:1px solid ${active?'var(--primary-color)':'var(--gray-200)'};background:${active?'var(--primary-color)':'white'};color:${active?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;font-weight:${active?'700':'400'};">${i}</button>`;
      }
      const startIdx = (pagination.page - 1) * pagination.limit + 1;
      const endIdx = Math.min(pagination.page * pagination.limit, pagination.total);
      pg.innerHTML = `
        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;flex-wrap:wrap;gap:8px;">
          <span style="font-size:13px;color:var(--gray-600);">
            Showing <strong>${startIdx}–${endIdx}</strong> of <strong>${pagination.total}</strong> visits
          </span>
          <div style="display:flex;gap:4px;align-items:center;">
            <button onclick="rptPage--;rptLoad()" ${pagination.page<=1?'disabled':''} style="padding:6px 14px;border:1px solid var(--gray-200);background:${pagination.page<=1?'var(--gray-100)':'white'};color:${pagination.page<=1?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${pagination.page<=1?'not-allowed':'pointer'};font-size:13px;"><i class="fas fa-chevron-left"></i> Previous</button>
            ${btns}
            <button onclick="rptPage++;rptLoad()" ${pagination.page>=pagination.total_pages?'disabled':''} style="padding:6px 14px;border:1px solid var(--gray-200);background:${pagination.page>=pagination.total_pages?'var(--gray-100)':'white'};color:${pagination.page>=pagination.total_pages?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${pagination.page>=pagination.total_pages?'not-allowed':'pointer'};font-size:13px;">Next <i class="fas fa-chevron-right"></i></button>
          </div>
        </div>`;
    } else if (pagination && pagination.total > 0) {
      pg.innerHTML = `<div style="font-size:13px;color:var(--gray-600);width:100%;text-align:right;">Showing all <strong>${pagination.total}</strong> visits</div>`;
    } else {
      pg.innerHTML = '';
    }

  }catch(e){tb.innerHTML='<tr><td colspan="14" style="padding:30px;text-align:center;color:var(--danger-color);">Error: '+e.message+'</td></tr>';}
}

// Details modal for Payment Methods / Services cards. Re-fetches ALL invoices for the current filters
// (the page only holds one page of visits) and lists the rows that make up the clicked card.
async function rptShowDetail(kind,key){
  const modal=document.getElementById('rpt-detail-modal');
  const title=document.getElementById('rpt-detail-title');
  const sub=document.getElementById('rpt-detail-sub');
  const body=document.getElementById('rpt-detail-body');
  const label=String(key).replace(/_/g,' ');
  title.textContent=(kind==='pm'?'Payment Method: ':'Service: ')+label;
  sub.textContent='';
  body.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  modal.style.display='flex';
  try{
    const p=new URLSearchParams(rptLastParams||'');
    p.set('page',1);p.set('limit',10000);
    const j=await (await fetch(rptAPI+'&'+p)).json();
    if(!j.success) throw new Error(j.message||'Failed');
    // one entry per patient+visit; sub = the individual payments / items behind it
    const groups={};
    const invPaid=inv=>{const c=(inv.payment_records||[]).filter(r=>r.status==='completed').reduce((x,r)=>x+(parseFloat(r.amount_paid)||0),0);return c||parseFloat(inv.paid_amount)||0;};
    const add=(inv,sub)=>{
      const k=(inv.visit_id||inv.visit_no||inv.invoice_no)+'|'+(inv.patient_no||inv.patient_first||'');
      let g=groups[k];
      if(!g){
        const priv=inv.payment_type==='self_pay'||!inv.insurance_name||/priv/i.test(inv.insurance_name);
        const cp=priv||inv.copayment_percentage==null?100:parseFloat(inv.copayment_percentage);
        g=groups[k]={date:(inv.visit_date||'').slice(0,10),patient:((inv.patient_first||'')+' '+(inv.patient_last||'')).trim(),visit:inv.visit_no||'',
          ins:priv?'':inv.insurance_name,cp:cp,billed:0,paid:0,invs:{},subs:[]};
      }
      g.subs.push(sub);g.paid+=sub.paid;
      if(kind==='pm'){ if(!g.invs[inv.invoice_no]){g.invs[inv.invoice_no]=1;g.billed+=parseFloat(inv.total)||0;sub.billed=parseFloat(inv.total)||0;} }
      else g.billed+=sub.billed;
    };
    j.data.invoices.forEach(inv=>{
      if(kind==='pm'){
        // mirrors the backend breakdown: completed records, else invoice-level method, else unpaid
        const recs=(inv.payment_records||[]).filter(r=>r.status==='completed'&&parseFloat(r.amount_paid)>0);
        if(recs.length) recs.forEach(r=>{if((r.payment_method||'cash')===key) add(inv,{inv:inv.invoice_no,ref:r.receipt_number||'',paid:parseFloat(r.amount_paid)||0});});
        else if(inv.payment_method&&parseFloat(inv.paid_amount)>0){if(inv.payment_method===key) add(inv,{inv:inv.invoice_no,ref:'',paid:parseFloat(inv.paid_amount)||0});}
        else if(key==='unpaid') add(inv,{inv:inv.invoice_no,ref:'',paid:0});
      } else {
        const tot=parseFloat(inv.total)||0;
        (inv.items||[]).forEach(it=>{if((it.category||'other')===key){
          const amt=parseFloat(it.total)||0;
          // items carry no payment info: paid is the invoice's paid share, pro-rated
          add(inv,{inv:inv.invoice_no,ref:'',det:(it.description||'')+' ×'+(parseInt(it.quantity)||1),billed:amt,paid:tot?invPaid(inv)*amt/tot:0});
        }});
      }
    });
    const list=Object.values(groups);
    const nSub=list.reduce((a,g)=>a+g.subs.length,0);
    const tBilled=list.reduce((a,g)=>a+g.billed,0),tPaid=list.reduce((a,g)=>a+g.paid,0);
    sub.textContent=list.length+' patient visit'+(list.length!==1?'s':'')+' · '+nSub+(kind==='pm'?' payment':' item')+(nSub!==1?'s':'')+' · Billed '+rFmt(tBilled)+' · Paid '+rFmt(tPaid)+' RWF'+(kind==='svc'?' (paid pro-rated)':'');
    const th=(t,a)=>`<th style="padding:8px 10px;text-align:${a||'left'};">${t}</th>`;
    const td=(t,a,x,c)=>`<td${c?` colspan="${c}"`:''} style="padding:8px 10px;text-align:${a||'left'};${x||''}">${t}</td>`;
    const rowsHtml=list.map((g,i)=>{
      const insCell=g.ins?`<span style="font-weight:600;color:#1e40af;">${rEsc(g.ins)}</span>`:'<span style="color:var(--gray-500);">Private</span>';
      const cov=g.ins?`Ins ${Math.round(100-g.cp)}% · Pt ${g.cp}%`:'Pt 100%';
      return `<tr style="border-top:1px solid var(--gray-200);background:var(--gray-50);">${td(i+1)}${td(rEsc(g.date),'left','white-space:nowrap;')}${td(rEsc(g.patient||'—'),'left','font-weight:700;')}${td(rEsc(g.visit))}${td(insCell)}${td(cov,'left','white-space:nowrap;')}${kind==='svc'?td(g.subs.map((x,n)=>(n+1)+'. '+rEsc(x.det)).join('<br>-------------<br>'),'left','font-size:11px;color:var(--gray-600);line-height:1.6;'):''}${td(rFmt(g.billed),'right','font-weight:700;')}${td(rFmt(g.paid),'right','font-weight:700;color:#065f46;')}</tr>`
        +(kind==='svc'?[]:g.subs).map(s=>`<tr style="border-bottom:1px solid var(--gray-100);color:var(--gray-600);">${td('','left','',1)}${td('↳ <span style="font-family:monospace;">'+rEsc(s.inv)+'</span>'+(s.ref?' · '+rEsc(s.ref):''),'left','padding-left:24px;font-size:11px;',kind==='svc'?5:5)}${kind==='svc'?td(rEsc(s.det),'left','font-size:11px;'):''}${td(s.billed!=null?rFmt(s.billed):'','right','font-size:11px;')}${td(rFmt(s.paid),'right','font-size:11px;color:#065f46;')}</tr>`).join('');
    }).join('');
    body.innerHTML=`<table id="rpt-detail-table" style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">${th('#')}${th('Date')}${th('Patient')}${th('Visit No')}${th('Insurance')}${th('Coverage')}${kind==='svc'?th('Details (what was billed)'):''}${th('Billed (RWF)','right')}${th('Paid (RWF)','right')}</tr></thead>
      <tbody>${rowsHtml||'<tr><td colspan="9" style="padding:20px;text-align:center;color:var(--gray-400);">No records</td></tr>'}</tbody></table>`;
  }catch(e){body.innerHTML='<div style="padding:30px;text-align:center;color:var(--danger-color);">Error: '+rEsc(e.message)+'</div>';}
}
function rptCloseDetail(){document.getElementById('rpt-detail-modal').style.display='none';}
function rptExportDetail(){rptExportXlsx('rpt-detail-table',document.getElementById('rpt-detail-title').textContent);}

function rptPrint(){
  const el=document.getElementById('rpt-printarea');
  const win=window.open('','','width=1000,height=700');
  win.document.write('<html><head><title>General Report</title><style>body{font-family:Arial,sans-serif;font-size:12px;}table{width:100%;border-collapse:collapse;}th,td{padding:6px 8px;border:1px solid #ddd;text-align:left;}th{background:#f0f0f0;font-weight:600;}@media print{button{display:none;}}</style></head><body>');
  win.document.write('<h2>General Report ('+document.getElementById('rpt-from').value+' to '+document.getElementById('rpt-to').value+')</h2>');
  win.document.write(el.innerHTML);
  win.document.write('</body></html>');
  win.document.close();
  win.print();
}

function rptSwitchTab(tab){
  const summaryBtn=document.getElementById('rpt-tab-summary');
  const detailsBtn=document.getElementById('rpt-tab-details');
  const summarySection=document.getElementById('rpt-summary-section');
  const detailsSection=document.getElementById('rpt-details-section');
  if(tab==='summary'){
    summaryBtn.style.cssText='padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px 8px 0 0;font-size:13px;font-weight:600;cursor:pointer;';
    detailsBtn.style.cssText='padding:10px 20px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-bottom:none;border-radius:8px 8px 0 0;font-size:13px;font-weight:500;cursor:pointer;';
    summarySection.style.display='block';
    detailsSection.style.display='none';
  } else {
    detailsBtn.style.cssText='padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:8px 8px 0 0;font-size:13px;font-weight:600;cursor:pointer;';
    summaryBtn.style.cssText='padding:10px 20px;background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);border-bottom:none;border-radius:8px 8px 0 0;font-size:13px;font-weight:500;cursor:pointer;';
    summarySection.style.display='none';
    detailsSection.style.display='block';
  }
}

// Init
rptInitFilters();
rptLoad(false);
