<?php
require_once __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div><h2 style="font-size:24px;font-weight:600;">Inventory Opening Balance</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Upload Excel to record opening stock with insurance prices</p></div>
</div>

<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:20px;">
  <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;"><i class="fas fa-file-excel" style="color:#059669;"></i> Upload Excel File</h3>
  
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px;">
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Store *</label>
      <select id="ob-store" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="">Loading stores...</option>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Inventory Account (for journal) *</label>
      <select id="ob-account" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="">Loading accounts...</option>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Opening Date</label>
      <input type="date" id="ob-date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
  </div>

  <div style="border:2px dashed var(--gray-200);border-radius:8px;padding:30px;text-align:center;margin-bottom:16px;">
    <i class="fas fa-file-excel" style="font-size:36px;color:#059669;display:block;margin-bottom:8px;"></i>
    <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Upload Excel or CSV file with columns:<br><strong>NO, Name, QTY, Cost, MMI, RSSB, SANLAM, OLDMUTUAL, EDN CARE, PRIME, RADIANT, PRIVE, Batch Number, Measure, Expiration Date</strong></p>
    <input type="file" id="ob-file" accept=".xlsx,.xls,.csv" onchange="obParseFile()" style="font-size:13px;">
  </div>
  
  <div id="ob-status" style="display:none;padding:10px;border-radius:6px;margin-bottom:12px;font-size:13px;"></div>
</div>

<!-- Insurance Column Mapping -->
<div id="ob-mapping-section" style="display:none;background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:20px;">
  <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;"><i class="fas fa-link" style="color:#7c3aed;"></i> Insurance Column Mapping</h3>
  <p style="font-size:12px;color:var(--gray-500);margin-bottom:12px;">Map each Excel price column to the correct insurance company in the system. The price columns show what each insurance pays for the item.</p>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px;text-align:left;">Excel Column</th>
        <th style="padding:10px;text-align:left;">Mapped Insurance</th>
        <th style="padding:10px;text-align:center;">Status</th>
      </tr></thead>
      <tbody id="ob-mapping-tbody"></tbody>
    </table>
  </div>
</div>

<!-- Preview Table -->
<div id="ob-preview-section" style="display:none;background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-bottom:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-eye" style="color:var(--primary-color);"></i> Preview (<span id="ob-count">0</span> items)</h3>
    <button onclick="obSubmit()" id="ob-submit-btn" style="padding:10px 24px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-check-circle"></i> Confirm & Save Opening Stock</button>
  </div>
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:11px;white-space:nowrap;" id="ob-table">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:6px;text-align:left;">#</th>
        <th style="padding:6px;text-align:left;">Name</th>
        <th style="padding:6px;text-align:center;">Qty</th>
        <th style="padding:6px;text-align:right;">Cost</th>
        <th style="padding:6px;text-align:right;">MMI</th>
        <th style="padding:6px;text-align:right;">RSSB</th>
        <th style="padding:6px;text-align:right;">SANLAM</th>
        <th style="padding:6px;text-align:right;">OLDMUTUAL</th>
        <th style="padding:6px;text-align:right;">EDN CARE</th>
        <th style="padding:6px;text-align:right;">PRIME</th>
        <th style="padding:6px;text-align:right;">RADIANT</th>
        <th style="padding:6px;text-align:right;">PRIVE</th>
        <th style="padding:6px;text-align:left;">Batch</th>
        <th style="padding:6px;text-align:left;">Measure</th>
        <th style="padding:6px;text-align:left;">Expiry</th>
        <th style="padding:6px;text-align:center;">Action</th>
      </tr></thead>
      <tbody id="ob-tbody"></tbody>
      <tfoot><tr style="background:var(--gray-50);font-weight:700;">
        <td colspan="2" style="padding:8px;">TOTAL</td>
        <td style="padding:8px;text-align:center;" id="ob-total-qty">0</td>
        <td style="padding:8px;text-align:right;" id="ob-total-cost">0</td>
        <td colspan="13"></td>
      </tr></tfoot>
    </table>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
const _ioBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const obInvAPI = _ioBase + '/api.php?endpoint=inventory/inventory-management';
const obCoaAPI = _ioBase + '/api.php?endpoint=finance/chart-of-accounts';
const obInsAPI = _ioBase + '/api.php?endpoint=insurance/companies';
let obData=[];
let obInsuranceCompanies=[];
let obColumnMapping={};

// Column names from Excel mapped to keys
const obColumns=['mmi','rssb','sanlam','oldmutual','edn_care','prime','radiant','private_price'];
const obColumnLabels=['MMI','RSSB','SANLAM','OLDMUTUAL','EDN CARE','PRIME','RADIANT','PRIVATE'];

async function obInit(){
  document.getElementById('ob-date').value=new Date().toISOString().split('T')[0];
  // Load stores
  try{
    const r=await fetch(obInvAPI+'&action=stores');
    const j=await r.json();
    if(j.success){
      const stores=j.data||[];
      document.getElementById('ob-store').innerHTML='<option value="">Select store...</option>'+stores.map(s=>'<option value="'+s.id+'">'+s.name+'</option>').join('');
      document.getElementById('ob-account').innerHTML='<option value="">Select account...</option>'+stores.map(s=>'<option value="'+s.id+'">'+s.name+'</option>').join('');
    }
  }catch(e){}
  // Load insurance companies
  try{
    const r=await fetch(obInsAPI+'&status=active');
    const j=await r.json();
    if(j.success){
      const data=j.data||{};
      obInsuranceCompanies=data.companies||data||[];
      if(Array.isArray(j.data)) obInsuranceCompanies=j.data;
    }
  }catch(e){console.error('Failed to load insurance companies:',e);}
}

function obBuildMapping(){
  const insOpts='<option value="">— Not mapped —</option><option value="private">PRIVATE (No Insurance)</option>'+obInsuranceCompanies.map(c=>'<option value="'+c.id+'">'+c.name+' ('+c.code+')</option>').join('');
  
  // Auto-match by name
  obColumnMapping={};
  const tbody=document.getElementById('ob-mapping-tbody');
  tbody.innerHTML=obColumnLabels.map((label,i)=>{
    const key=obColumns[i];
    // Try to auto-match
    let matched='';
    if(key==='private_price'){matched='private';}
    else{
      const found=obInsuranceCompanies.find(c=>c.name.toLowerCase().includes(label.toLowerCase())||c.code.toLowerCase().includes(label.toLowerCase()));
      if(found) matched=String(found.id);
    }
    obColumnMapping[key]=matched;
    return `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:8px 10px;font-weight:600;">${label}</td>
      <td style="padding:8px 10px;"><select onchange="obColumnMapping['${key}']=this.value;obUpdateMappingStatus()" style="width:100%;padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" id="ob-map-${key}">${insOpts}</select></td>
      <td style="padding:8px 10px;text-align:center;" id="ob-map-status-${key}"><i class="fas fa-circle" style="color:var(--gray-300);"></i></td>
    </tr>`;
  }).join('');
  
  // Set auto-matched values
  obColumns.forEach(key=>{
    const sel=document.getElementById('ob-map-'+key);
    if(sel && obColumnMapping[key]) sel.value=obColumnMapping[key];
  });
  obUpdateMappingStatus();
  document.getElementById('ob-mapping-section').style.display='block';
}

function obUpdateMappingStatus(){
  obColumns.forEach(key=>{
    const el=document.getElementById('ob-map-status-'+key);
    if(!el)return;
    if(obColumnMapping[key]){
      el.innerHTML='<i class="fas fa-check-circle" style="color:#059669;"></i>';
    }else{
      el.innerHTML='<i class="fas fa-minus-circle" style="color:var(--gray-300);"></i>';
    }
  });
}

async function obInit(){
  document.getElementById('ob-date').value=new Date().toISOString().split('T')[0];
  // Load stores
  try{
    const r=await fetch(obInvAPI+'&action=stores');
    const j=await r.json();
    if(j.success){
      const stores=j.data||[];
      document.getElementById('ob-store').innerHTML='<option value="">Select store...</option>'+stores.map(s=>'<option value="'+s.id+'">'+s.name+'</option>').join('');
      document.getElementById('ob-account').innerHTML='<option value="">Select account...</option>'+stores.map(s=>'<option value="'+s.id+'">'+s.name+'</option>').join('');
    }
  }catch(e){}
}

function obParseFile(){
  const file=document.getElementById('ob-file').files[0];
  if(!file)return;
  const reader=new FileReader();
  reader.onload=async function(e){
    try{
      let rows;
      const isCSV=file.name.toLowerCase().endsWith('.csv');
      if(isCSV){
        // Parse CSV
        const text=e.target.result;
        rows=text.split('\n').map(line=>{
          // Handle quoted fields
          const result=[];
          let current='',inQuotes=false;
          for(let i=0;i<line.length;i++){
            if(line[i]==='"'){inQuotes=!inQuotes;}
            else if((line[i]===','||line[i]===';')&&!inQuotes){result.push(current.trim());current='';}
            else{current+=line[i];}
          }
          result.push(current.trim());
          return result;
        });
      }else{
        // Parse Excel
        const wb=XLSX.read(e.target.result,{type:'binary'});
        const ws=wb.Sheets[wb.SheetNames[0]];
        rows=XLSX.utils.sheet_to_json(ws,{header:1,defval:''});
      }
      
      // Skip header row, parse data
      obData=[];
      for(let i=1;i<rows.length;i++){
        const r=rows[i];
        if(!r[1]||!String(r[1]).trim())continue; // skip empty name
        obData.push({
          no:r[0]||i,
          name:String(r[1]||'').trim(),
          qty:parseFloat(r[2])||0,
          cost:parseFloat(r[3])||0,
          mmi:parseFloat(r[4])||0,
          rssb:parseFloat(r[5])||0,
          sanlam:parseFloat(r[6])||0,
          oldmutual:parseFloat(r[7])||0,
          edn_care:parseFloat(r[8])||0,
          prime:parseFloat(r[9])||0,
          radiant:parseFloat(r[10])||0,
          private_price:parseFloat(r[11])||0,
          batch:String(r[12]||''),
          measure:String(r[13]||''),
          expiry:String(r[14]||'')
        });
      }
      obRenderPreview();
      // Ensure insurance companies are loaded before mapping
      if(!obInsuranceCompanies.length){
        try{
          const ir=await fetch(obInsAPI+'&status=active');
          const ij=await ir.json();
          if(ij.success){
            const idata=ij.data||{};
            obInsuranceCompanies=idata.companies||idata||[];
            if(Array.isArray(ij.data)) obInsuranceCompanies=ij.data;
          }
        }catch(e){}
      }
      obBuildMapping();
      document.getElementById('ob-status').style.display='block';
      document.getElementById('ob-status').style.background='#d1fae5';
      document.getElementById('ob-status').style.color='#065f46';
      document.getElementById('ob-status').textContent='✓ Parsed '+obData.length+' items from '+(isCSV?'CSV':'Excel');
    }catch(err){
      document.getElementById('ob-status').style.display='block';
      document.getElementById('ob-status').style.background='#fee2e2';
      document.getElementById('ob-status').style.color='#991b1b';
      document.getElementById('ob-status').textContent='Error parsing file: '+err.message;
    }
  };
  if(file.name.toLowerCase().endsWith('.csv')){
    reader.readAsText(file);
  }else{
    reader.readAsBinaryString(file);
  }
}

function obRenderPreview(){
  if(!obData.length){document.getElementById('ob-preview-section').style.display='none';return;}
  document.getElementById('ob-preview-section').style.display='block';
  document.getElementById('ob-count').textContent=obData.length;
  let totalQty=0,totalCost=0;
  const tbody=document.getElementById('ob-tbody');
  tbody.innerHTML=obData.map((d,i)=>{
    totalQty+=d.qty;
    totalCost+=(d.qty*d.cost);
    return `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:4px 6px;">${i+1}</td>
      <td style="padding:4px 6px;font-weight:500;"><input value="${d.name}" onchange="obData[${i}].name=this.value" style="width:140px;padding:3px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
      <td style="padding:4px 6px;text-align:center;"><input type="number" value="${d.qty}" onchange="obData[${i}].qty=parseFloat(this.value)||0;obUpdateTotals()" style="width:50px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:center;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.cost}" onchange="obData[${i}].cost=parseFloat(this.value)||0;obUpdateTotals()" style="width:70px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.mmi}" onchange="obData[${i}].mmi=parseFloat(this.value)||0" style="width:60px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.rssb}" onchange="obData[${i}].rssb=parseFloat(this.value)||0" style="width:60px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.sanlam}" onchange="obData[${i}].sanlam=parseFloat(this.value)||0" style="width:60px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.oldmutual}" onchange="obData[${i}].oldmutual=parseFloat(this.value)||0" style="width:60px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.edn_care}" onchange="obData[${i}].edn_care=parseFloat(this.value)||0" style="width:60px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.prime}" onchange="obData[${i}].prime=parseFloat(this.value)||0" style="width:60px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.radiant}" onchange="obData[${i}].radiant=parseFloat(this.value)||0" style="width:60px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;text-align:right;"><input type="number" value="${d.private_price}" onchange="obData[${i}].private_price=parseFloat(this.value)||0" style="width:60px;padding:3px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
      <td style="padding:4px 6px;"><input value="${d.batch}" onchange="obData[${i}].batch=this.value" style="width:80px;padding:3px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
      <td style="padding:4px 6px;"><input value="${d.measure}" onchange="obData[${i}].measure=this.value" style="width:70px;padding:3px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
      <td style="padding:4px 6px;"><input value="${d.expiry}" onchange="obData[${i}].expiry=this.value" style="width:80px;padding:3px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
      <td style="padding:4px 6px;text-align:center;"><button onclick="obData.splice(${i},1);obRenderPreview()" style="background:none;border:none;color:#dc2626;cursor:pointer;"><i class="fas fa-times"></i></button></td>
    </tr>`;
  }).join('');
  document.getElementById('ob-total-qty').textContent=totalQty;
  document.getElementById('ob-total-cost').textContent=(totalCost).toLocaleString();
}

function obUpdateTotals(){
  let totalQty=0,totalCost=0;
  obData.forEach(d=>{totalQty+=d.qty;totalCost+=(d.qty*d.cost);});
  document.getElementById('ob-total-qty').textContent=totalQty;
  document.getElementById('ob-total-cost').textContent=totalCost.toLocaleString();
}

async function obSubmit(){
  const storeId=document.getElementById('ob-store').value;
  const entryDate=document.getElementById('ob-date').value;
  const inventoryAccountId=parseInt(document.getElementById('ob-account').value);
  if(!storeId)return alert('Please select a store.');
  if(!inventoryAccountId)return alert('Please select an inventory account for the journal entry.');
  if(!obData.length)return alert('No items to save. Upload an Excel file first.');
  
  if(!confirm('Save '+obData.length+' items as opening stock?\n\nThis will:\n• Create items in inventory\n• Add stock batches\n• Create journal entry (Dr Inventory, Cr Opening Equity)'))return;
  
  const btn=document.getElementById('ob-submit-btn');
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
  
  let totalValue=0;
  let created=0,errors=[];
  
  try{
    for(const item of obData){
      try{
        // 1. Create or find item
        const insPrices={};
        obColumns.forEach(key=>{
          const mappedId=obColumnMapping[key];
          if(mappedId && mappedId!=='private' && item[key]>0){
            insPrices[mappedId]=item[key]; // key is insurance company ID
          }
        });
        
        const itemRes=await fetch(obInvAPI+'&action=create_item_opening',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
          name:item.name,
          unit_price:item.cost,
          selling_price:item.private_price||item.cost,
          is_medication:1,
          description:item.measure||'',
          store_id:parseInt(storeId),
          quantity:item.qty,
          batch_number:item.batch,
          expiry_date:item.expiry,
          insurance_prices:insPrices,
          exceptional_prices:item.exceptions||[]
        })});
        const itemJson=await itemRes.json();
        if(itemJson.success){
          created++;
          totalValue+=(item.qty*item.cost);
        }else{
          errors.push(item.name+': '+itemJson.message);
        }
      }catch(e){errors.push(item.name+': '+e.message);}
    }
    
    let msg='✓ Opening stock saved!\n\n• Items created: '+created+'/'+obData.length+'\n• Total value: '+totalValue.toLocaleString()+' RWF';
    if(errors.length) msg+='\n\nErrors ('+errors.length+'):\n'+errors.slice(0,5).join('\n');
    
    // 2. Create journal entry for total stock value: Dr Inventory (stock account), Cr Opening Equity (77)
    if(totalValue>0){
      try{
        const jnlRes=await fetch(obCoaAPI+'&action=create_journal_entry',{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({
            entry_date:entryDate,
            description:'Opening stock balance - '+created+' items',
            posted:1,
            lines:[
              {account_id:inventoryAccountId, line_description:'Opening inventory stock', debit:totalValue, credit:0},
              {account_id:77, line_description:'Opening equity - Inventory', debit:0, credit:totalValue}
            ]
          })
        });
        const jnlJson=await jnlRes.json();
        if(jnlJson.success){
          msg+='\n• Journal Entry: '+jnlJson.data.reference;
        }else{
          msg+='\n• Journal Entry FAILED: '+jnlJson.message;
        }
      }catch(je){msg+='\n• Journal Entry error: '+je.message;}
    }
    
    alert(msg);
    obData=[];
    obRenderPreview();
  }catch(e){alert('Failed: '+e.message);}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-check-circle"></i> Confirm & Save Opening Stock';}
}

obInit();
</script>

<!-- ====== OTHER STOCK CATEGORY (No Insurance) ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-top:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-boxes" style="color:#7c3aed;"></i> Other Stock (Non-Medical / Supplies)</h3>
    <button onclick="othImportToggle()" style="padding:6px 14px;background:#7c3aed;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-file-csv"></i> Import CSV</button>
  </div>
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">Record other stock items (cleaning supplies, office materials, etc.) that do not require insurance pricing. Creates a journal entry.</p>

  <!-- CSV Import section (hidden by default) -->
  <div id="oth-import-section" style="display:none;background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;padding:16px;margin-bottom:16px;">
    <h4 style="font-size:13px;font-weight:600;color:#7c3aed;margin-bottom:8px;"><i class="fas fa-file-csv"></i> Import from CSV</h4>
    <p style="font-size:11px;color:var(--gray-500);margin-bottom:8px;">CSV columns: <strong>Name, Qty, Measure, Cost, Batch Number, Expiry Date</strong></p>
    <input type="file" id="oth-csv-file" accept=".csv" style="font-size:12px;margin-bottom:8px;">
    <div style="display:flex;gap:8px;">
      <button onclick="othParseCSV()" style="padding:6px 14px;background:#7c3aed;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;font-weight:500;"><i class="fas fa-eye"></i> Preview</button>
      <button onclick="othImportToggle()" style="padding:6px 14px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:6px;font-size:11px;cursor:pointer;">Cancel</button>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Store *</label>
      <select id="oth-store" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="">Loading stores...</option>
      </select>
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Date</label>
      <input type="date" id="oth-date" value="<?php echo date('Y-m-d');?>" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
  </div>

  <!-- Items table -->
  <div style="margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <label style="font-size:13px;font-weight:600;">Items</label>
      <button onclick="othAddRow()" type="button" style="padding:5px 12px;background:#7c3aed;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-plus"></i> Add Row</button>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:600px;">
        <thead><tr style="background:var(--gray-50);">
          <th style="padding:8px;text-align:left;">Name</th>
          <th style="padding:8px;text-align:center;width:70px;">Qty</th>
          <th style="padding:8px;text-align:center;width:100px;">Measure</th>
          <th style="padding:8px;text-align:center;width:100px;">Cost (RWF)</th>
          <th style="padding:8px;text-align:left;width:110px;">Batch Number</th>
          <th style="padding:8px;width:30px;"></th>
        </tr></thead>
        <tbody id="oth-tbody"></tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:8px;font-size:13px;font-weight:600;color:var(--gray-700);">
      Total: <span id="oth-total" style="margin-left:6px;color:#7c3aed;">0 RWF</span>
    </div>
  </div>

  <button onclick="othSubmit()" style="width:100%;padding:10px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
    <i class="fas fa-save"></i> Record Other Stock & Create Journal Entry
  </button>
</div>

<!-- Other Stock History -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-top:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-history" style="color:#7c3aed;"></i> Other Stock History</h3>
    <button onclick="othLoadHistory()" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:11px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:8px;text-align:left;">Date</th>
        <th style="padding:8px;text-align:left;">Name</th>
        <th style="padding:8px;text-align:center;">Qty</th>
        <th style="padding:8px;text-align:center;">Measure</th>
        <th style="padding:8px;text-align:right;">Cost</th>
        <th style="padding:8px;text-align:left;">Batch #</th>
        <th style="padding:8px;text-align:center;">Expiry</th>
      </tr></thead>
      <tbody id="oth-hist-tbody"><tr><td colspan="7" style="padding:20px;text-align:center;color:var(--gray-400);">Loading...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
const othAPI = _ioBase + '/api.php?endpoint=inventory/inventory-management&action=other_stock';

function othImportToggle(){
  const sec=document.getElementById('oth-import-section');
  sec.style.display=sec.style.display==='none'?'block':'none';
}

function othAddRow(data){
  const tbody=document.getElementById('oth-tbody');
  const row=document.createElement('tr');row.className='oth-row';row.style.borderBottom='1px solid var(--gray-100)';
  const d=data||{};
  row.innerHTML=`<td style="padding:4px;"><input type="text" class="oth-name" value="${(d.name||'').replace(/"/g,'&quot;')}" placeholder="Item name" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
    <td style="padding:4px;"><input type="number" class="oth-qty" value="${d.qty||1}" min="1" oninput="othCalcTotal()" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:center;"></td>
    <td style="padding:4px;"><select class="oth-measure" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;">
      <option value="pcs" ${d.measure==='pcs'?'selected':''}>Pieces</option>
      <option value="box" ${d.measure==='box'?'selected':''}>Box</option>
      <option value="pack" ${d.measure==='pack'?'selected':''}>Pack</option>
      <option value="kg" ${d.measure==='kg'?'selected':''}>Kg</option>
      <option value="ltr" ${d.measure==='ltr'?'selected':''}>Litre</option>
      <option value="roll" ${d.measure==='roll'?'selected':''}>Roll</option>
      <option value="bottle" ${d.measure==='bottle'?'selected':''}>Bottle</option>
      <option value="set" ${d.measure==='set'?'selected':''}>Set</option>
      <option value="pair" ${d.measure==='pair'?'selected':''}>Pair</option>
      <option value="other" ${d.measure==='other'?'selected':''}>Other</option>
    </select></td>
    <td style="padding:4px;"><input type="number" class="oth-cost" value="${d.cost||''}" placeholder="0" min="0" oninput="othCalcTotal()" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:center;"></td>
    <td style="padding:4px;"><input type="text" class="oth-batch" value="${(d.batch||'').replace(/"/g,'&quot;')}" placeholder="Batch #" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
    <td style="padding:4px;"><button onclick="this.closest('tr').remove();othCalcTotal()" style="border:none;background:none;color:#dc2626;cursor:pointer;"><i class="fas fa-trash"></i></button></td>`;
  // Store expiry from CSV in a hidden field
  if(d.expiry){
    const hidden=document.createElement('input');hidden.type='hidden';hidden.className='oth-expiry';hidden.value=d.expiry;
    row.appendChild(hidden);
  }
  tbody.appendChild(row);
}

function othCalcTotal(){
  let total=0;
  document.querySelectorAll('.oth-row').forEach(row=>{
    const qty=parseFloat(row.querySelector('.oth-qty').value)||0;
    const cost=parseFloat(row.querySelector('.oth-cost').value)||0;
    total+=qty*cost;
  });
  document.getElementById('oth-total').textContent=total.toLocaleString()+' RWF';
}

function othParseCSV(){
  const file=document.getElementById('oth-csv-file').files[0];
  if(!file)return alert('Please select a CSV file.');
  const reader=new FileReader();
  reader.onload=function(e){
    const lines=e.target.result.split(/\r?\n/).filter(l=>l.trim());
    if(lines.length<2)return alert('CSV must have a header and at least one data row.');
    // Clear existing rows
    document.getElementById('oth-tbody').innerHTML='';
    for(let i=1;i<lines.length;i++){
      const cols=othParseCSVLine(lines[i]);
      if(!cols[0])continue;
      // Strip commas/spaces from numbers
      const costStr=(cols[3]||'0').replace(/[,\s]/g,'');
      const qtyStr=(cols[1]||'1').replace(/[,\s]/g,'');
      othAddRow({
        name:cols[0]||'',
        qty:parseFloat(qtyStr)||1,
        measure:(cols[2]||'pcs').toLowerCase(),
        cost:parseFloat(costStr)||0,
        batch:cols[4]||'',
        expiry:cols[5]||''
      });
    }
    othCalcTotal();
    document.getElementById('oth-import-section').style.display='none';
    alert('✓ Imported '+(lines.length-1)+' rows from CSV.');
  };
  reader.readAsText(file);
}

// Parse CSV line handling quoted fields with commas
function othParseCSVLine(line){
  var result=[];
  var current='';
  var inQuotes=false;
  for(var i=0;i<line.length;i++){
    var ch=line.charAt(i);
    if(ch==='"'){inQuotes=!inQuotes;}
    else if(ch===','&&!inQuotes){result.push(current.trim());current='';}
    else{current+=ch;}
  }
  result.push(current.trim());
  return result;
}

async function othSubmit(){
  const storeId=document.getElementById('oth-store').value;
  const date=document.getElementById('oth-date').value;
  const rows=document.querySelectorAll('.oth-row');
  const items=[];
  rows.forEach(row=>{
    const name=row.querySelector('.oth-name').value.trim();
    const qty=parseFloat(row.querySelector('.oth-qty').value)||0;
    const measure=row.querySelector('.oth-measure').value;
    const cost=parseFloat(row.querySelector('.oth-cost').value)||0;
    const batch=row.querySelector('.oth-batch').value.trim();
    const expiryEl=row.querySelector('.oth-expiry');
    const expiry=expiryEl?expiryEl.value:null;
    if(name&&qty>0) items.push({name,quantity:qty,measure,unit_cost:cost,batch_number:batch,expiry_date:expiry||null});
  });
  if(!items.length) return alert('Add at least one item with a name and quantity.');
  if(!confirm('Record '+items.length+' other stock item(s)? This will create a journal entry.')) return;

  try{
    const r=await fetch(othAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({store_id:parseInt(storeId),date:date,items:items})});
    const j=await r.json();
    if(j.success){
      alert('✓ Other stock recorded!\n'+(j.message||''));
      othLoadHistory();
      document.getElementById('oth-tbody').innerHTML='';
      othAddRow();
      othCalcTotal();
    } else alert('Error: '+j.message);
  }catch(e){alert('Failed: '+e.message);}
}

async function othLoadHistory(){
  const tbody=document.getElementById('oth-hist-tbody');
  tbody.innerHTML='<tr><td colspan="7" style="padding:20px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const r=await fetch(othAPI);
    const j=await r.json();
    if(j.success && j.data && j.data.length){
      tbody.innerHTML=j.data.map(s=>`<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px;font-size:11px;">${s.date||'—'}</td>
        <td style="padding:8px;font-weight:500;">${s.name||'—'}</td>
        <td style="padding:8px;text-align:center;">${parseFloat(s.quantity||0)}</td>
        <td style="padding:8px;text-align:center;">${s.measure||'—'}</td>
        <td style="padding:8px;text-align:right;font-weight:600;">${parseFloat(s.unit_cost||0).toLocaleString()} RWF</td>
        <td style="padding:8px;">${s.batch_number||'—'}</td>
        <td style="padding:8px;text-align:center;font-size:11px;">${s.expiry_date||'—'}</td>
      </tr>`).join('');
    } else {
      tbody.innerHTML='<tr><td colspan="7" style="padding:20px;text-align:center;color:var(--gray-400);">No other stock records yet.</td></tr>';
    }
  }catch(e){
    tbody.innerHTML='<tr><td colspan="7" style="padding:20px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}

async function othLoadStores(){
  try{
    const r=await fetch(_ioBase + '/api.php?endpoint=inventory/inventory-management&action=stores');
    const j=await r.json();
    if(j.success && j.data){
      const sel=document.getElementById('oth-store');
      sel.innerHTML='<option value="">Select store...</option>'+j.data.map(s=>'<option value="'+s.id+'">'+s.name+'</option>').join('');
    }
  }catch(e){}
}
othLoadStores();
othAddRow();
othLoadHistory();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
