let tfItemsList=[];
async function tfInit(){
  try{
    const r=await fetch(invAPI+'items&active=true&limit=500');
    const j=await r.json();
    if(j.success) tfItemsList=j.data||[];
  }catch(e){}
}

async function tfLoad(){
  try{
    const r=await fetch(invAPI+'transfers');
    const j=await r.json();
    const tbody=document.getElementById('tf-tbody');
    if(j.success&&j.data&&j.data.length){
      tbody.innerHTML=j.data.map(t=>{
        const sBg={draft:'#f3f4f6',pending:'#fef3c7',approved:'#d1fae5',cancelled:'#fee2e2',received:'#dbeafe'};
        const sColor={draft:'#374151',pending:'#92400e',approved:'#065f46',cancelled:'#991b1b',received:'#1e40af'};
        let actions=`<button onclick="viewTransferDetail(${t.id})" style="padding:4px 9px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-right:4px;" title="View Transferred Items & Details"><i class="fas fa-eye"></i> View</button>`;
        if(t.status==='draft') {
          actions+=`<button onclick="tfSubmit(${t.id})" style="padding:4px 9px;background:#d97706;color:white;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;"><i class="fas fa-paper-plane"></i> Submit</button>`;
        } else if(t.status==='pending') {
          if (invIsAdmin) {
            actions+=`<button onclick="tfApprove(${t.id})" style="padding:4px 9px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;margin-right:4px;"><i class="fas fa-check"></i> Approve</button><button onclick="tfReject(${t.id})" style="padding:4px 9px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;"><i class="fas fa-times"></i> Reject</button>`;
          }
        }
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:10px 12px;font-family:monospace;font-weight:600;color:var(--primary-color);cursor:pointer;" onclick="viewTransferDetail(${t.id})" title="Click to view transferred items"><i class="fas fa-file-invoice" style="margin-right:5px;font-size:11px;"></i>${escHtml(t.transfer_number||'')}</td>
          <td style="padding:10px 12px;font-size:12px;font-weight:500;">${escHtml(t.from_store_name||'#'+t.from_store_id)} <i class="fas fa-arrow-right" style="color:var(--gray-400);font-size:10px;margin:0 4px;"></i> ${escHtml(t.to_store_name||'#'+t.to_store_id)}</td>
          <td style="padding:10px 12px;font-size:12px;color:var(--gray-600);">${t.transfer_date||'—'}</td>
          <td style="padding:10px 12px;text-align:center;"><button onclick="viewTransferDetail(${t.id})" style="padding:3px 10px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:12px;cursor:pointer;font-size:11px;color:var(--gray-700);font-weight:600;" title="Click to view transferred items"><i class="fas fa-boxes" style="color:var(--primary-color);margin-right:4px;"></i>${t.item_count||0} items</button></td>
          <td style="padding:10px 12px;"><span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;text-transform:capitalize;background:${sBg[t.status]||'#f3f4f6'};color:${sColor[t.status]||'#374151'};">${t.status}</span></td>
          <td style="padding:10px 12px;text-align:center;">${actions}</td>
        </tr>`;
      }).join('');
    } else tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);">No transfer records found.</td></tr>';
  }catch(e){ console.error('tfLoad error:', e); }
}

// Closes the modal, or resets the inline form when rendered inside the Stock Transfer tab
function tfClose(){
  const m=document.getElementById('tf-modal');
  if(m) m.remove(); else if(document.getElementById('tf-inline')) tfNew();
}

async function tfNew(){
  tfBatches=[];
  if(!tfItemsList.length) await tfInit();
  if(!document.getElementById('tf-dl')){
    const dl=document.createElement('datalist');dl.id='tf-dl';
    tfItemsList.forEach(i=>{
      const o=document.createElement('option');
      o.value=(i.item_code?i.item_code+' - ':'')+i.name;
      o.setAttribute('data-id',i.id);
      dl.appendChild(o);
    });
    document.body.appendChild(dl);
  }
  const modal=document.createElement('div');modal.id='tf-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:820px;max-width:95vw;max-height:88vh;overflow-y:auto;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--gray-100);padding-bottom:12px;">
      <div>
        <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);display:flex;align-items:center;gap:8px;">
          <i class="fas fa-exchange-alt" style="color:var(--primary-color);"></i> New Stock Transfer
        </h3>
        <p style="font-size:12px;color:var(--gray-500);margin-top:2px;">Select source and destination stores, then pick items with batch and expiry details</p>
      </div>
      <button data-tf-x onclick="tfClose()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">From Store (Source) *</label>
        <select id="tf-from" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Loading stores...</option></select>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">To Store (Destination) *</label>
        <select id="tf-to" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="">Loading stores...</option></select>
      </div>
      <div style="grid-column:1/-1;">
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">Transfer Reason / Reference</label>
        <input id="tf-reason" placeholder="e.g. Ward replenishment, emergency transfer, department request..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>
    <div style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <label style="font-size:13px;font-weight:600;color:var(--gray-800);"><i class="fas fa-boxes" style="color:var(--primary-color);"></i> Available Batches in Source Store</label>
      </div>
      <div id="tf-items"><div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;border:1px dashed var(--gray-200);border-radius:6px;">Select a "From Store" above to load available items & batches.</div></div>
    </div>
    <div style="display:flex;gap:10px;padding-top:14px;border-top:1px solid var(--gray-100);flex-wrap:wrap;">
      <button onclick="tfClose()" style="padding:10px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:600;cursor:pointer;">Cancel</button>
      <button onclick="tfSave('draft')" style="flex:1;min-width:120px;padding:10px;background:var(--gray-600);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save Draft</button>
      <button onclick="tfSave('pending')" style="flex:1;min-width:140px;padding:10px;background:#d97706;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-paper-plane"></i> Submit for Approval</button>
      ${invIsAdmin ? `<button onclick="tfSave('approved')" style="flex:1;min-width:150px;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-check-circle"></i> Direct Transfer (Instant)</button>` : ''}
    </div>
  </div>`;
  const host=document.getElementById('tf-inline');
  if(host){
    const card=modal.firstElementChild;
    card.style.cssText='';
    card.firstElementChild.remove(); // header (title + close) not needed inline
    host.innerHTML='';
    host.appendChild(card);
  } else document.body.appendChild(modal);
  
  // Load stores dynamically
  fetch(invAPI+'stores').then(r=>r.json()).then(j=>{
    if(j.success&&j.data){
      const opts=j.data.map(s=>'<option value="'+s.id+'">'+escHtml(s.name)+(s.code?' ('+escHtml(s.code)+')':'')+'</option>').join('');
      document.getElementById('tf-from').innerHTML='<option value="">Select Source Store...</option>'+opts;
      document.getElementById('tf-to').innerHTML='<option value="">Select Destination Store...</option>'+opts;
    }
  }).catch(e=>{});
  
  // Load batches when From Store changes
  document.getElementById('tf-from').addEventListener('change',tfLoadBatches);
}

let tfBatches=[];

async function tfLoadBatches(){
  const storeId=document.getElementById('tf-from').value;
  const container=document.getElementById('tf-items');
  container.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;"><i class="fas fa-spinner fa-spin"></i> Loading store batches...</div>';
  tfBatches=[];
  if(!storeId) {
    container.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;border:1px dashed var(--gray-200);border-radius:6px;">Select a "From Store" above to load available items & batches.</div>';
    return;
  }
  try{
    const r=await fetch(invAPI+'store_batches&store_id='+storeId);
    const j=await r.json();
    if(j.success && j.data) {
      tfBatches = j.data.map(b => ({
        ...b,
        _selected: false,
        _transfer_qty: parseFloat(b.quantity || 0)
      }));
    }
  }catch(e){
    console.error('tfLoadBatches error:', e);
  }
  tfRenderBatchList();
}

function tfFilterBatches(){
  const q = ((document.getElementById('tf-search')||{}).value||'').trim().toLowerCase();
  const rows = document.querySelectorAll('.tf-batch-row');
  let matchCount = 0;
  
  rows.forEach(row => {
    const searchText = (row.getAttribute('data-search') || '').toLowerCase();
    if (!q || searchText.includes(q)) {
      row.style.display = '';
      matchCount++;
    } else {
      row.style.display = 'none';
    }
  });

  const noMatchRow = document.getElementById('tf-no-matches-row');
  if (noMatchRow) {
    noMatchRow.style.display = (matchCount === 0 && tfBatches.length > 0) ? '' : 'none';
  }
}

function tfRenderBatchList(){
  const container=document.getElementById('tf-items');
  if(!tfBatches.length){
    container.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;border:1px dashed var(--gray-200);border-radius:6px;"><i class="fas fa-info-circle"></i> No active batches with stock found in this store. Select a different store.</div>';
    return;
  }

  let html='<div style="margin-bottom:8px;"><input type="text" id="tf-search" placeholder="Search item name, item code, or batch #..." oninput="tfFilterBatches()" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"></div>';

  html+='<div style="max-height:320px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:6px;">';
  html+='<table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);position:sticky;top:0;border-bottom:1px solid var(--gray-200);z-index:2;">';
  html+='<th style="padding:8px 10px;text-align:center;width:36px;"></th>';
  html+='<th style="padding:8px 10px;text-align:left;">Item</th>';
  html+='<th style="padding:8px 10px;text-align:left;width:130px;">Batch #</th>';
  html+='<th style="padding:8px 10px;text-align:center;width:80px;">Avail Qty</th>';
  html+='<th style="padding:8px 10px;text-align:center;width:155px;">Expiry Date</th>';
  html+='<th style="padding:8px 10px;text-align:center;width:105px;">Transfer Qty</th>';
  html+='</tr></thead><tbody id="tf-batches-tbody">';

  tfBatches.forEach((b, i)=>{
    const cleanExp = (b.expiry_date && b.expiry_date !== '0000-00-00' && b.expiry_date !== '0000-00-00 00:00:00') ? b.expiry_date.substring(0, 10) : '';
    const isExp = cleanExp && new Date(cleanExp) < new Date();
    
    // Batch column: show monospace badge if present, and allow editing/entering if missing
    let batchHtml = '';
    if (b.batch_number) {
      batchHtml = `<div style="display:flex;align-items:center;gap:4px;">
        <span style="font-family:monospace;font-weight:600;padding:2px 6px;background:#eff6ff;color:#1e40af;border-radius:4px;border:1px solid #dbeafe;">${escHtml(b.batch_number)}</span>
      </div>`;
    } else {
      batchHtml = `<input type="text" class="tf-batch-num" data-idx="${i}" placeholder="Batch #" value="" style="width:105px;padding:3px 6px;border:1px solid #f59e0b;background:#fffbeb;border-radius:4px;font-size:11px;font-family:monospace;" title="Enter batch number" ${b._selected?'':'disabled'}>`;
    }

    // Expiry column: editable date picker, prefilled if present, or amber prompt to enter if missing
    const expHtml = `<div style="display:flex;flex-direction:column;align-items:center;gap:2px;">
      <input type="date" class="tf-batch-expiry" data-idx="${i}" value="${cleanExp}" 
        style="width:130px;padding:3px 6px;border:1px solid ${!cleanExp ? '#f59e0b' : (isExp ? '#ef4444' : 'var(--gray-200)')};border-radius:4px;font-size:11px;text-align:center;background:${!cleanExp ? '#fffbeb' : (isExp ? '#fef2f2' : 'white')};" 
        title="${!cleanExp ? 'Please specify expiration date for this transfer' : (isExp ? 'Expired batch' : 'Expiration date')}" 
        ${b._selected?'':'disabled'}>
      ${!cleanExp ? '<span style="font-size:10px;color:#d97706;font-weight:600;"><i class="fas fa-calendar-plus"></i> Set Expiry</span>' : (isExp ? '<span style="font-size:10px;color:#dc2626;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Expired</span>' : '')}
    </div>`;

    const searchData = escHtml(((b.item_name||'') + ' ' + (b.item_code||'') + ' ' + (b.batch_number||'')).toLowerCase());

    html+=`<tr style="border-bottom:1px solid var(--gray-100);" class="tf-batch-row" data-idx="${i}" data-batch-id="${b.id}" data-search="${searchData}">
      <td style="padding:8px 10px;text-align:center;"><input type="checkbox" class="tf-batch-check" data-idx="${i}" ${b._selected?'checked':''} style="width:15px;height:15px;cursor:pointer;"></td>
      <td style="padding:8px 10px;"><div style="font-weight:600;color:var(--gray-900);">${escHtml(b.item_name||'')}</div><div style="font-size:11px;color:var(--gray-500);font-family:monospace;">${escHtml(b.item_code||'')}</div></td>
      <td style="padding:8px 10px;">${batchHtml}</td>
      <td style="padding:8px 10px;text-align:center;font-weight:700;color:var(--gray-900);">${parseFloat(b.quantity||0)}</td>
      <td style="padding:8px 10px;text-align:center;">${expHtml}</td>
      <td style="padding:8px 10px;text-align:center;"><input type="number" class="tf-batch-qty" data-idx="${i}" min="0.01" max="${parseFloat(b.quantity||0)}" step="any" value="${b._transfer_qty}" style="width:80px;padding:4px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:center;" ${b._selected?'':'disabled'}></td>
    </tr>`;
  });

  html+=`<tr id="tf-no-matches-row" style="display:none;"><td colspan="6" style="padding:16px;text-align:center;color:var(--gray-400);font-size:12px;">No matching items or batches found.</td></tr>`;

  html+='</tbody></table></div>';
  html+='<p style="font-size:11px;color:var(--gray-500);margin-top:8px;"><i class="fas fa-info-circle" style="color:var(--primary-color);"></i> Check the items/batches you wish to transfer and specify the transfer quantity. Batch numbers and expiration dates are strictly carried over into destination store.</p>';
  container.innerHTML=html;

  // Handle checkbox changes & quantity/batch/expiry input changes
  container.querySelectorAll('.tf-batch-check').forEach(cb=>{
    cb.addEventListener('change',function(){
      const idx=parseInt(this.dataset.idx);
      const batch=tfBatches[idx];
      if(batch) batch._selected = this.checked;
      const qtyInput=container.querySelector('.tf-batch-qty[data-idx="'+idx+'"]');
      if(qtyInput){
        qtyInput.disabled=!this.checked;
        if(this.checked && (!parseFloat(qtyInput.value) || parseFloat(qtyInput.value) <= 0)){
          qtyInput.value=parseFloat(batch ? batch.quantity : 0);
          if(batch) batch._transfer_qty = parseFloat(batch.quantity || 0);
        }
      }
      const expInp=container.querySelector('.tf-batch-expiry[data-idx="'+idx+'"]');
      if(expInp) expInp.disabled=!this.checked;
      const numInp=container.querySelector('.tf-batch-num[data-idx="'+idx+'"]');
      if(numInp) numInp.disabled=!this.checked;
    });
  });

  container.querySelectorAll('.tf-batch-qty').forEach(qi=>{
    qi.addEventListener('input',function(){
      const idx=parseInt(this.dataset.idx);
      const batch=tfBatches[idx];
      if(batch) batch._transfer_qty = parseFloat(this.value) || 0;
    });
  });

  container.querySelectorAll('.tf-batch-expiry').forEach(ei=>{
    ei.addEventListener('change',function(){
      const idx=parseInt(this.dataset.idx);
      const batch=tfBatches[idx];
      if(batch) batch.expiry_date = this.value.trim();
    });
  });

  container.querySelectorAll('.tf-batch-num').forEach(ni=>{
    ni.addEventListener('input',function(){
      const idx=parseInt(this.dataset.idx);
      const batch=tfBatches[idx];
      if(batch) batch.batch_number = this.value.trim();
    });
  });
}

async function tfSave(status){
  if (status === 'approved' && !invIsAdmin) {
    alert('Permission denied: Only administrators can execute direct transfers.');
    return;
  }
  const fromStore=document.getElementById('tf-from').value;
  const toStore=document.getElementById('tf-to').value;
  if(!fromStore||!toStore) return alert('Please select both From and To stores.');
  if(fromStore===toStore) return alert('Source and Destination stores must be different.');
  
  const items=[];
  let hasOverQty = false;
  const container = document.getElementById('tf-items');

  tfBatches.forEach((batch, idx) => {
    if (batch._selected) {
      const qty = parseFloat(batch._transfer_qty || 0);
      const avail = parseFloat(batch.quantity || 0);
      if (qty > avail) hasOverQty = true;
      
      const expInp = container ? container.querySelector('.tf-batch-expiry[data-idx="' + idx + '"]') : null;
      const numInp = container ? container.querySelector('.tf-batch-num[data-idx="' + idx + '"]') : null;
      
      const chosenExpiry = expInp ? (expInp.value.trim() || null) : (batch.expiry_date || null);
      const chosenBatch = numInp ? (numInp.value.trim() || batch.batch_number || '') : (batch.batch_number || '');
      
      if (qty > 0) {
        items.push({
          item_id: batch.item_id,
          batch_id: batch.id,
          batch_number: chosenBatch,
          expiry_date: chosenExpiry,
          unit_cost: parseFloat(batch.unit_cost || 0),
          quantity: qty
        });
      }
    }
  });
  
  if(hasOverQty) return alert('Transfer quantity cannot exceed available quantity for any selected batch.');
  if(!items.length) return alert('Please select at least one batch with a valid transfer quantity.');
  
  if(status === 'approved' && !confirm('Perform direct transfer now? This will immediately move stock with batch numbers and expiration dates into destination store.')) return;
  
  try{
    const r=await fetch(invAPI+'create_transfer',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        from_store_id:parseInt(fromStore),
        to_store_id:parseInt(toStore),
        reason:document.getElementById('tf-reason').value,
        status:status,
        items:items
      })
    });
    const j=await r.json();
    if(j.success){
      alert('✓ '+(status==='draft'?'Draft transfer saved!':(status==='approved'?'Transfer completed and stock transferred successfully with batch & expiration dates!':'Transfer submitted for approval!')));
      tfClose();
      tfLoad();
    }else alert('Error: ' + j.message);
  }catch(e){ alert('Failed to create transfer: ' + e.message); }
}

async function tfSubmit(id){
  if(!confirm('Submit this draft transfer for approval?')) return;
  try{
    const r=await fetch(invAPI+'update_transfer_status',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({transfer_id:id,status:'pending'})
    });
    const j=await r.json();
    if(j.success) { showToast('Transfer submitted for approval'); tfLoad(); }
    else alert('Error: ' + j.message);
  }catch(e){ alert('Error submitting transfer'); }
}

async function tfApprove(id){
  if (!invIsAdmin) {
    alert('Permission denied: Only administrators can approve transfers.');
    return;
  }
  const note=prompt('Approval note (optional):','Approved');
  if(note===null) return;
  try{
    const r=await fetch(invAPI+'approve_transfer',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({transfer_id:id,note:note})
    });
    const j=await r.json();
    if(j.success){
      alert('✓ Approved! Stock successfully transferred with batch & expiration dates.');
      const m = document.getElementById('gen-modal');
      if (m) m.innerHTML = '';
      tfLoad();
    } else alert('Approval error: ' + j.message);
  }catch(e){ alert('Failed to approve transfer'); }
}

async function tfReject(id){
  if (!invIsAdmin) {
    alert('Permission denied: Only administrators can reject transfers.');
    return;
  }
  const note=prompt('Rejection reason:','');
  if(!note) return alert('Reason is required to reject a transfer.');
  try{
    const r=await fetch(invAPI+'update_transfer_status',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({transfer_id:id,status:'cancelled',note:note})
    });
    const j=await r.json();
    if(j.success){
      showToast('Transfer rejected');
      const m = document.getElementById('gen-modal');
      if (m) m.innerHTML = '';
      tfLoad();
    } else alert('Error: ' + j.message);
  }catch(e){ alert('Failed to reject transfer'); }
}
