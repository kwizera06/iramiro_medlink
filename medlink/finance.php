<?php
require_once __DIR__ . '/includes/header.php';
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'chart-of-accounts';
?>
<style>
.fn-tab{padding:10px 18px;border:none;background:var(--gray-50);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-600);}
.fn-tab.active{background:var(--primary-color);color:white;}
.coa-row{cursor:pointer;transition:background 0.15s;} .coa-row:hover{background:var(--gray-50);}
.coa-children{display:none;} .coa-children.open{display:table-row-group;}
.coa-toggle{width:20px;display:inline-block;text-align:center;color:var(--gray-400);}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div><h2 style="font-size:24px;font-weight:600;">Finance & Accounting</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Financial statements and chart of accounts</p></div>
</div>

<div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
  <button class="fn-tab <?php echo $subModule=='chart-of-accounts'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=chart-of-accounts'"><i class="fas fa-sitemap"></i> Chart of Accounts</button>
  <button class="fn-tab <?php echo $subModule=='journal-entry'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=journal-entry'"><i class="fas fa-book"></i> Journal Entries</button>
  <button class="fn-tab <?php echo $subModule=='general-ledger'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=general-ledger'"><i class="fas fa-file-alt"></i> General Ledger</button>
  <button class="fn-tab <?php echo $subModule=='balance-sheet'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=balance-sheet'"><i class="fas fa-balance-scale"></i> Balance Sheet</button>
  <button class="fn-tab <?php echo $subModule=='income-statement'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=income-statement'"><i class="fas fa-chart-line"></i> Income Statement</button>
  <button class="fn-tab <?php echo $subModule=='trial-balance'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=trial-balance'"><i class="fas fa-list-ol"></i> Trial Balance</button>
  <button class="fn-tab <?php echo $subModule=='cash-flow'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=cash-flow'"><i class="fas fa-money-bill-wave"></i> Cash Flow</button>
  <button class="fn-tab <?php echo $subModule=='cash-equivalent'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=cash-equivalent'"><i class="fas fa-university"></i> Cash & Banks</button>
  <button class="fn-tab <?php echo $subModule=='bank-reconciliation'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=bank-reconciliation'"><i class="fas fa-check-double"></i> Reconciliation</button>
  <button class="fn-tab <?php echo $subModule=='fs-notes'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=fs-notes'"><i class="fas fa-sticky-note"></i> Notes to Accounts</button>
  <button class="fn-tab <?php echo $subModule=='debts'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=debts'"><i class="fas fa-hand-holding-usd"></i> Outstanding Debts</button>
  <button class="fn-tab <?php echo $subModule=='payroll-finance'?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=payroll-finance'" style="background:<?php echo $subModule=='payroll-finance'?'var(--primary-color)':'#7c3aed'; ?>;color:white;border-color:#7c3aed;"><i class="fas fa-users"></i> Payroll Finance</button>
</div>
<div style="display:flex;gap:8px;margin:-12px 0 24px;flex-wrap:wrap;">
<?php foreach ([['expenses','fa-receipt','Expenses & Payables'], ['equity-statement','fa-layer-group','Changes in Equity'], ['year-end','fa-calendar-check','Year-End Close'], ['receivables','fa-hand-holding-usd','Bad Debts & Write-offs'], ['claims','fa-file-medical','Insurance Claims']] as [$fnSub, $fnIcon, $fnLabel]): ?>
  <button class="fn-tab <?php echo $subModule==$fnSub?'active':'';?>" onclick="location.href='finance.php?module=finance&sub=<?php echo $fnSub; ?>'"><i class="fas <?php echo $fnIcon; ?>"></i> <?php echo $fnLabel; ?></button>
<?php endforeach; ?>
</div>
<script>
// shared by the Expenses/Equity/Year-End/Receivables screens (ported from the St Michel system)
var _fnBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
if (!window.pgSize) {
  window.pgSize = (key, def) => { const v = localStorage.getItem('pg:' + key); return v === null ? def : +v; };
  window.pgSizeSelect = (key, def, onchange, sizes = [10, 25, 50, 100, 0]) => {
    const cur = pgSize(key, def);
    return `<label style="font-size:12px;color:var(--gray-500);display:inline-flex;align-items:center;gap:6px;margin-right:auto;">Rows <select onchange="localStorage.setItem('pg:${key}',this.value);${onchange || ''}" style="padding:5px 8px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;background:white;color:var(--gray-700);cursor:pointer;">`
      + sizes.map(n => `<option value="${n}"${n === cur ? ' selected' : ''}>${n || 'All'}</option>`).join('') + '</select></label>';
  };
}
</script>

<?php if ($subModule == 'chart-of-accounts'): ?>
<!-- ====== CHART OF ACCOUNTS - Collapsible Tree ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-sitemap" style="color:var(--primary-color);"></i> Chart of Accounts</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="coaNewAccount()" style="padding:6px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:11px;font-weight:600;"><i class="fas fa-plus"></i> Add Account</button>
      <button onclick="coaExpandAll()" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:11px;">Expand All</button>
      <button onclick="coaCollapseAll()" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:11px;">Collapse All</button>
      <button onclick="coaLoad()" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:11px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div id="coa-tree" style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
  </div>
</div>

<script>
const fnAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts';
function fEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

let coaAccounts = [];

async function coaLoad(){
  try{
    const r = await fetch(fnAPI);
    const j = await r.json();
    if(j.success && j.data){
      coaAccounts = j.data.accounts || j.data;
      coaRender();
    }
  } catch(e){ document.getElementById('coa-tree').innerHTML='<p style="padding:20px;color:red;">Error loading</p>'; }
}

function coaRender(){
  const typeColors = {asset:'#2563eb',liability:'#dc2626',equity:'#7c3aed',income:'#16a34a',expense:'#d97706'};
  
  // Build tree structure
  const roots = coaAccounts.filter(a => !a.parent_id);
  
  function getChildren(parentId){
    return coaAccounts.filter(a => a.parent_id == parentId);
  }
  
  function renderAccount(account, level){
    const children = getChildren(account.id);
    const hasChildren = children.length > 0;
    const indent = level * 20;
    const color = typeColors[account.account_type] || '#666';
    const bold = level < 2 ? 'font-weight:700;' : '';
    const bg = level === 0 ? 'background:var(--gray-50);' : '';
    const canDelete = !account.is_default && !hasChildren;
    
    let html = `<div class="coa-row" style="display:flex;align-items:center;padding:8px 12px;border-bottom:1px solid var(--gray-100);${bg}" data-id="${account.id}" data-level="${level}">
      <span style="min-width:${indent}px;"></span>
      <span class="coa-toggle" onclick="coaToggle(this.parentElement)" style="cursor:pointer;">${hasChildren ? '<i class="fas fa-chevron-right"></i>' : '<i class="fas fa-minus" style="font-size:8px;"></i>'}</span>
      <span style="min-width:80px;font-family:monospace;color:${color};font-size:12px;margin-left:8px;">${fEsc(account.account_code)}</span>
      <span style="flex:1;font-size:13px;${bold}margin-left:8px;" onclick="coaToggle(this.parentElement)" style="cursor:pointer;">${fEsc(account.account_name)}</span>
      <span style="padding:2px 8px;border-radius:10px;font-size:10px;background:${color}15;color:${color};text-transform:capitalize;margin-right:8px;">${account.account_type}</span>
      ${!account.is_default ? `<button onclick="event.stopPropagation();coaEdit(${account.id})" style="border:none;background:none;color:var(--primary-color);cursor:pointer;font-size:12px;padding:2px 6px;margin-right:4px;" title="Edit"><i class="fas fa-edit"></i></button>` : ''}
      ${canDelete ? `<button onclick="event.stopPropagation();coaDelete(${account.id},'${fEsc(account.account_name).replace(/'/g,"\\'")}')" style="border:none;background:none;color:#dc2626;cursor:pointer;font-size:12px;padding:2px 6px;" title="Delete"><i class="fas fa-trash"></i></button>` : ''}
    </div>`;
    
    if(hasChildren){
      html += `<div class="coa-children" data-parent="${account.id}">`;
      children.forEach(child => { html += renderAccount(child, level + 1); });
      html += `</div>`;
    }
    return html;
  }
  
  let html = '';
  roots.forEach(root => { html += renderAccount(root, 0); });
  document.getElementById('coa-tree').innerHTML = html || '<div style="padding:30px;text-align:center;color:var(--gray-400);">No accounts found.</div>';
}

function coaToggle(el){
  const id = el.getAttribute('data-id');
  const childDiv = document.querySelector('.coa-children[data-parent="'+id+'"]');
  if(!childDiv) return;
  const isOpen = childDiv.classList.contains('open');
  childDiv.classList.toggle('open');
  const icon = el.querySelector('.coa-toggle i');
  if(icon){ icon.className = isOpen ? 'fas fa-chevron-right' : 'fas fa-chevron-down'; }
}

function coaExpandAll(){ document.querySelectorAll('.coa-children').forEach(el=>el.classList.add('open')); document.querySelectorAll('.coa-toggle i.fa-chevron-right').forEach(i=>i.className='fas fa-chevron-down'); }
function coaCollapseAll(){ document.querySelectorAll('.coa-children').forEach(el=>el.classList.remove('open')); document.querySelectorAll('.coa-toggle i.fa-chevron-down').forEach(i=>i.className='fas fa-chevron-right'); }

async function coaDelete(id, name){
  if(!confirm('Delete account "'+name+'"?\n\nThis cannot be undone.')) return;
  try{
    const r = await fetch(fnAPI+'&id='+id, {method:'DELETE'});
    const j = await r.json();
    if(j.success){ alert('✓ Account deleted.'); coaLoad(); }
    else alert('Error: '+j.message);
  } catch(e){ alert('Failed: '+e.message); }
}

function coaEdit(id){
  const account = coaAccounts.find(a => a.id == id);
  if(!account) return alert('Account not found');
  
  // Build parent account options (exclude self and its descendants to prevent circular reference)
  let parentOpts = '<option value="">— No Parent (Root Account) —</option>';
  
  // Get all descendant IDs to exclude them
  const getDescendants = (parentId) => {
    const children = coaAccounts.filter(a => a.parent_id == parentId);
    let descendants = children.map(c => c.id);
    children.forEach(c => {
      descendants = descendants.concat(getDescendants(c.id));
    });
    return descendants;
  };
  
  const excludeIds = [id, ...getDescendants(id)];
  
  coaAccounts.forEach(a => {
    if(!excludeIds.includes(a.id)){
      const selected = a.id == account.parent_id ? 'selected' : '';
      parentOpts += `<option value="${a.id}" ${selected}>${fEsc(a.account_code)} - ${fEsc(a.account_name)} (${a.account_type})</option>`;
    }
  });

  const modal = document.createElement('div');
  modal.id = 'coa-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;width:500px;max-width:90vw;max-height:80vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Edit Account</h3>
      <button onclick="document.getElementById('coa-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
      <div style="grid-column:1/-1;">
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Parent Account</label>
        <select id="coa-parent" onchange="coaAutoType()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${parentOpts}</select>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Account Code *</label>
        <input id="coa-code" value="${fEsc(account.account_code)}" placeholder="e.g. 1111" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Account Type *</label>
        <select id="coa-type" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="asset" ${account.account_type=='asset'?'selected':''}>Asset</option>
          <option value="liability" ${account.account_type=='liability'?'selected':''}>Liability</option>
          <option value="equity" ${account.account_type=='equity'?'selected':''}>Equity</option>
          <option value="income" ${account.account_type=='income'?'selected':''}>Income</option>
          <option value="expense" ${account.account_type=='expense'?'selected':''}>Expense</option>
        </select>
      </div>
      <div style="grid-column:1/-1;">
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Account Name *</label>
        <input id="coa-name" value="${fEsc(account.account_name)}" placeholder="e.g. Petty Cash" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div style="grid-column:1/-1;">
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Description</label>
        <textarea id="coa-desc" rows="2" placeholder="Optional description..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;">${fEsc(account.description||'')}</textarea>
      </div>
    </div>
    <button onclick="coaUpdateAccount(${id})" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Update Account</button>
  </div>`;
  document.body.appendChild(modal);
}

function coaNewAccount(){
  // Build parent account options from loaded data
  let parentOpts = '<option value="">— No Parent (Root Account) —</option>';
  coaAccounts.forEach(a => {
    parentOpts += `<option value="${a.id}">${fEsc(a.account_code)} - ${fEsc(a.account_name)} (${a.account_type})</option>`;
  });

  const modal = document.createElement('div');
  modal.id = 'coa-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;width:500px;max-width:90vw;max-height:80vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;">Add New Account</h3>
      <button onclick="document.getElementById('coa-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
      <div style="grid-column:1/-1;">
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Parent Account</label>
        <select id="coa-parent" onchange="coaAutoType()" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${parentOpts}</select>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Account Code *</label>
        <input id="coa-code" placeholder="e.g. 1111" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Account Type *</label>
        <select id="coa-type" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="asset">Asset</option>
          <option value="liability">Liability</option>
          <option value="equity">Equity</option>
          <option value="income">Income</option>
          <option value="expense">Expense</option>
        </select>
      </div>
      <div style="grid-column:1/-1;">
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Account Name *</label>
        <input id="coa-name" placeholder="e.g. Petty Cash" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div style="grid-column:1/-1;">
        <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Description</label>
        <textarea id="coa-desc" rows="2" placeholder="Optional description..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
      </div>
    </div>
    <button onclick="coaSaveAccount()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Create Account</button>
  </div>`;
  document.body.appendChild(modal);
}

function coaAutoType(){
  const parentId = document.getElementById('coa-parent').value;
  if(parentId){
    const parent = coaAccounts.find(a => a.id == parentId);
    if(parent) document.getElementById('coa-type').value = parent.account_type;
  }
}

async function coaSaveAccount(){
  const code = document.getElementById('coa-code').value.trim();
  const name = document.getElementById('coa-name').value.trim();
  const type = document.getElementById('coa-type').value;
  const parentId = document.getElementById('coa-parent').value || null;
  const desc = document.getElementById('coa-desc').value.trim();
  
  if(!code || !name) return alert('Account code and name are required.');
  
  try{
    const r = await fetch(fnAPI, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({account_code: code, account_name: name, account_type: type, parent_id: parentId ? parseInt(parentId) : null, description: desc})
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Account created!');
      document.getElementById('coa-modal').remove();
      coaLoad();
    } else alert('Error: ' + j.message);
  } catch(e){ alert('Failed: ' + e.message); }
}

async function coaUpdateAccount(id){
  const code = document.getElementById('coa-code').value.trim();
  const name = document.getElementById('coa-name').value.trim();
  const type = document.getElementById('coa-type').value;
  const parentId = document.getElementById('coa-parent').value || null;
  const desc = document.getElementById('coa-desc').value.trim();
  
  if(!code || !name) return alert('Account code and name are required.');
  
  try{
    const r = await fetch(fnAPI+'&id='+id, {
      method: 'PUT',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({account_code: code, account_name: name, account_type: type, parent_id: parentId ? parseInt(parentId) : null, description: desc})
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Account updated!');
      document.getElementById('coa-modal').remove();
      coaLoad();
    } else alert('Error: ' + j.message);
  } catch(e){ alert('Failed: ' + e.message); }
}

coaLoad();
</script>

<?php elseif ($subModule == 'journal-entry'): ?>
<!-- ====== JOURNAL ENTRIES ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-book" style="color:var(--primary-color);"></i> Journal Entries</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="date" id="je-date-from" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="From date">
      <span style="font-size:12px;color:var(--gray-400);">to</span>
      <input type="date" id="je-date-to" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="To date">
      <button onclick="jePage=1;jeLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Filter"><i class="fas fa-filter"></i></button>
      <button onclick="jeNewEntry()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> New Entry</button>
      <button onclick="jeClearFilter()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Clear filter & Refresh"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px;text-align:left;">Date</th><th style="padding:10px;text-align:left;">Account</th><th style="padding:10px;text-align:left;">Reference</th><th style="padding:10px;text-align:left;">Description</th><th style="padding:10px;text-align:right;">Debit</th><th style="padding:10px;text-align:right;">Credit</th><th style="padding:10px;text-align:center;">Actions</th>
      </tr></thead>
      <tbody id="je-tbody"><tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
  <div id="je-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;"></div>
</div>
<script>
function fEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

let jeAccounts = [];
async function jeLoadAccounts(){
  if(jeAccounts.length) return;
  const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts');
  const j = await r.json();
  if(j.success) jeAccounts = j.data.accounts || j.data;
}

async function jeNewEntry(){
  await jeLoadAccounts();
  let accOpts = '<option value="">Select account...</option>';
  jeAccounts.forEach(a => { accOpts += `<option value="${a.id}">${fEsc(a.account_code)} - ${fEsc(a.account_name)}</option>`; });

  const modal = document.createElement('div');
  modal.id = 'je-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;width:700px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-book" style="color:var(--primary-color);"></i> New Journal Entry</h3>
      <button onclick="document.getElementById('je-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Date *</label><input type="date" id="je-date" value="${new Date().toISOString().split('T')[0]}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Reference</label><input id="je-ref" placeholder="Auto-generated if empty" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Description *</label><input id="je-desc" placeholder="Transaction description" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    </div>

    <!-- Lines -->
    <div style="margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <label style="font-size:13px;font-weight:600;color:var(--gray-700);">Entry Lines</label>
        <button onclick="jeAddLine()" type="button" style="padding:4px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-plus"></i> Add Line</button>
      </div>
      <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
          <thead><tr style="background:var(--gray-50);">
            <th style="padding:8px;text-align:left;">Account</th>
            <th style="padding:8px;text-align:left;">Description</th>
            <th style="padding:8px;text-align:right;width:100px;">Debit</th>
            <th style="padding:8px;text-align:right;width:100px;">Credit</th>
            <th style="padding:8px;width:30px;"></th>
          </tr></thead>
          <tbody id="je-lines-tbody"></tbody>
          <tfoot><tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);">
            <td colspan="2" style="padding:8px;font-weight:700;">TOTALS</td>
            <td style="padding:8px;text-align:right;font-weight:700;" id="je-total-debit">0</td>
            <td style="padding:8px;text-align:right;font-weight:700;" id="je-total-credit">0</td>
            <td></td>
          </tr></tfoot>
        </table>
      </div>
      <div id="je-balance-msg" style="margin-top:6px;font-size:12px;font-weight:600;color:#065f46;display:none;"><i class="fas fa-check-circle"></i> Balanced</div>
    </div>

    <button onclick="jeSaveEntry()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Post Journal Entry</button>
  </div>`;
  document.body.appendChild(modal);
  window._jeAccOpts = accOpts;
  jeAddLine(); // Debit line
  jeAddLine(); // Credit line
}

function jeAddLine(){
  const tbody = document.getElementById('je-lines-tbody');
  const idx = tbody.querySelectorAll('.je-line-row').length;
  const row = document.createElement('tr');
  row.className = 'je-line-row';
  row.style.borderBottom = '1px solid var(--gray-100)';
  row.innerHTML = `
    <td style="padding:4px;"><input type="text" class="je-line-acc-search" placeholder="Search account..." onfocus="jeAccShow(this)" oninput="jeAccShow(this,true)" onkeydown="jeAccKey(event,this)" autocomplete="off" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"><input type="hidden" class="je-line-acc"></td>
    <td style="padding:4px;"><input class="je-line-desc" placeholder="Line description" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
    <td style="padding:4px;"><input type="number" class="je-line-debit" placeholder="0" min="0" oninput="jeCalcTotals()" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
    <td style="padding:4px;"><input type="number" class="je-line-credit" placeholder="0" min="0" oninput="jeCalcTotals()" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
    <td style="padding:4px;text-align:center;"><button onclick="this.closest('tr').remove();jeCalcTotals();" style="border:none;background:none;color:#dc2626;cursor:pointer;"><i class="fas fa-trash"></i></button></td>
  `;
  tbody.appendChild(row);

}

// Searchable account dropdown shared by the add and edit journal forms: text input + hidden id input right after it, one floating list below the input.
let _jeAccIn = null, _jeAccAct = -1;
function jeAccPanel(){
  let p = document.getElementById('je-acc-panel');
  if(!p){
    p = document.createElement('div'); p.id = 'je-acc-panel';
    p.style.cssText = 'display:none;position:fixed;z-index:10000;max-height:220px;overflow-y:auto;background:#fff;border:1px solid var(--gray-200);border-radius:6px;box-shadow:var(--shadow);font-size:11px;';
    document.body.appendChild(p);
    document.addEventListener('mousedown', e => { if(e.target !== _jeAccIn && !p.contains(e.target)) p.style.display = 'none'; });
    window.addEventListener('scroll', e => { if(!p.contains(e.target)) p.style.display = 'none'; }, true);
  }
  return p;
}
function jeAccShow(input, typed){
  _jeAccIn = input;
  const hidden = input.nextElementSibling;
  if(typed){ hidden.value = ''; input.style.borderColor = input.value.length > 2 ? '#d97706' : 'var(--gray-200)'; }
  const q = hidden.value ? '' : input.value.trim().toLowerCase(); // a chosen account shows the full list again
  const parentIds = new Set(jeAccounts.filter(a=>a.parent_id).map(a=>a.parent_id));
  const hits = jeAccounts.filter(a => !parentIds.has(a.id) && (a.account_code + ' - ' + a.account_name).toLowerCase().includes(q)).slice(0, 100);
  const p = jeAccPanel(), r = input.getBoundingClientRect();
  p.style.left = r.left + 'px'; p.style.top = (r.bottom + 2) + 'px'; p.style.width = Math.max(r.width, 280) + 'px';
  p.innerHTML = hits.length ? hits.map(a => `<div class="je-acc-opt" data-id="${a.id}" style="padding:6px 8px;cursor:pointer;"><b>${fEsc(a.account_code)}</b> - ${fEsc(a.account_name)}</div>`).join('') : '<div style="padding:6px 8px;color:var(--gray-500);">No account found</div>';
  p.querySelectorAll('.je-acc-opt').forEach(o => { o.onclick = () => jeAccPick(o.dataset.id); o.onmouseenter = () => jeAccMark(+[...p.children].indexOf(o)); });
  _jeAccAct = -1; p.style.display = 'block';
}
function jeAccMark(i){
  const o = jeAccPanel().querySelectorAll('.je-acc-opt');
  if(!o.length) return;
  _jeAccAct = (i + o.length) % o.length;
  o.forEach((e, n) => { e.style.background = n === _jeAccAct ? 'var(--gray-100)' : ''; });
  o[_jeAccAct].scrollIntoView({block:'nearest'});
}
function jeAccPick(id){
  const a = jeAccounts.find(x => String(x.id) === String(id)), input = _jeAccIn;
  if(!a || !input) return;
  input.value = a.account_code + ' - ' + a.account_name;
  input.nextElementSibling.value = a.id;
  input.style.borderColor = '#16a34a';
  jeAccPanel().style.display = 'none';
}
function jeAccKey(e, input){
  const p = jeAccPanel();
  if(e.key === 'ArrowDown' || e.key === 'ArrowUp'){ e.preventDefault(); if(p.style.display === 'none') jeAccShow(input); else jeAccMark(_jeAccAct + (e.key === 'ArrowDown' ? 1 : -1)); }
  else if(e.key === 'Enter' && p.style.display !== 'none'){ e.preventDefault(); const o = p.querySelectorAll('.je-acc-opt')[_jeAccAct]; if(o) jeAccPick(o.dataset.id); }
  else if(e.key === 'Escape' || e.key === 'Tab') p.style.display = 'none';
}

function jeCalcTotals(){
  let totalDebit = 0, totalCredit = 0;
  document.querySelectorAll('.je-line-debit').forEach(i => { totalDebit += parseFloat(i.value) || 0; });
  document.querySelectorAll('.je-line-credit').forEach(i => { totalCredit += parseFloat(i.value) || 0; });
  document.getElementById('je-total-debit').textContent = totalDebit.toLocaleString();
  document.getElementById('je-total-credit').textContent = totalCredit.toLocaleString();
  const msg = document.getElementById('je-balance-msg');
  if(totalDebit > 0 && totalDebit === totalCredit){
    msg.style.display = 'block';
    msg.style.color = '#065f46';
    msg.innerHTML = '<i class="fas fa-check-circle"></i> Balanced ✓';
  } else if(totalDebit > 0 || totalCredit > 0){
    msg.style.display = 'block';
    msg.style.color = '#991b1b';
    msg.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Not balanced! Difference: ' + Math.abs(totalDebit - totalCredit).toLocaleString();
  } else {
    msg.style.display = 'none';
  }
}

async function jeSaveEntry(){
  const date = document.getElementById('je-date').value;
  const desc = document.getElementById('je-desc').value.trim();
  const ref = document.getElementById('je-ref').value.trim();
  if(!date || !desc) return alert('Date and description are required.');

  // Collect lines
  const rows = document.querySelectorAll('.je-line-row');
  const lines = [];
  rows.forEach(row => {
    const accId = row.querySelector('.je-line-acc').value;
    const lineDesc = row.querySelector('.je-line-desc').value;
    const debit = parseFloat(row.querySelector('.je-line-debit').value) || 0;
    const credit = parseFloat(row.querySelector('.je-line-credit').value) || 0;
    if(accId && (debit > 0 || credit > 0)){
      lines.push({account_id: parseInt(accId), line_description: lineDesc, debit: debit, credit: credit});
    }
  });

  if(lines.length < 2) return alert('Need at least 2 lines (debit and credit).');

  // Check balance
  const totalDebit = lines.reduce((s, l) => s + l.debit, 0);
  const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
  if(Math.abs(totalDebit - totalCredit) > 0.01) return alert('Entry must be balanced! Debit (' + totalDebit.toLocaleString() + ') ≠ Credit (' + totalCredit.toLocaleString() + ')');

  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=create_journal_entry', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({entry_date: date, description: desc, reference: ref, lines: lines, posted: 1})
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Journal entry posted! Ref: ' + (j.data?.reference || ''));
      document.getElementById('je-modal').remove();
      jeLoad();
    } else alert('Error: ' + j.message);
  } catch(e){ alert('Failed: ' + e.message); }
}

async function jeLoad(){
  try{
    const dateFrom = document.getElementById('je-date-from').value;
    const dateTo = document.getElementById('je-date-to').value;
    const params = new URLSearchParams({page: jePage, limit: 25});
    if(dateFrom) params.set('from', dateFrom);
    if(dateTo) params.set('to', dateTo);
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=journal_entries&'+params);
    const j = await r.json();
    const tbody = document.getElementById('je-tbody');
    if(j.success && j.data){
      const entries = j.data.entries || j.data;
      const pagination = j.data.pagination || null;
      if(!entries || !entries.length){
        tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No entries found'+(dateFrom||dateTo?' for selected date range.':'.')+'</td></tr>';
        document.getElementById('je-pagination').innerHTML='';
        return;
      }
      let html = '';
      for(const e of entries){
        const lines = [];
        if(e.debited_accounts){
          e.debited_accounts.split(', ').forEach(acc => {
            const match = acc.match(/^(.+)\s\(([0-9,.]+)\)$/);
            if(match) lines.push({account: match[1], debit: parseFloat(match[2]), credit: 0});
          });
        }
        if(e.credited_accounts){
          e.credited_accounts.split(', ').forEach(acc => {
            const match = acc.match(/^(.+)\s\(([0-9,.]+)\)$/);
            if(match) lines.push({account: match[1], debit: 0, credit: parseFloat(match[2])});
          });
        }
        lines.forEach((l, idx) => {
          const isFirst = idx === 0;
          html += `<tr style="border-bottom:1px solid var(--gray-100);${isFirst?'border-top:2px solid var(--gray-200);':''}cursor:pointer;" onclick="jeView(${e.id})">
            <td style="padding:7px 10px;font-size:12px;">${isFirst ? (e.entry_date||'—') : ''}</td>
            <td style="padding:7px 10px;font-size:12px;${l.credit>0?'padding-left:24px;color:var(--gray-600);':'font-weight:600;'}">${fEsc(l.account)}</td>
            <td style="padding:7px 10px;font-family:monospace;font-size:11px;color:var(--primary-color);">${isFirst ? fEsc(e.reference||'') : ''}</td>
            <td style="padding:7px 10px;font-size:12px;color:var(--gray-600);">${isFirst ? fEsc(e.description||'') : ''}</td>
            <td style="padding:7px 10px;text-align:right;font-weight:600;color:#2563eb;">${l.debit > 0 ? l.debit.toLocaleString() : ''}</td>
            <td style="padding:7px 10px;text-align:right;font-weight:600;color:#dc2626;">${l.credit > 0 ? l.credit.toLocaleString() : ''}</td>
            <td style="padding:7px 10px;text-align:center;white-space:nowrap;">${isFirst ? `<button onclick="event.stopPropagation();jeEditDate(${e.id},'${e.entry_date||''}')" style="padding:3px 8px;background:#dbeafe;border:1px solid #bfdbfe;border-radius:4px;cursor:pointer;font-size:11px;color:#1e40af;margin-right:4px;" title="Edit Date"><i class="fas fa-calendar-alt"></i></button><button onclick="event.stopPropagation();jeRequestEdit(${e.id},'${fEsc(e.reference||'').replace(/'/g,"\\'")}')" style="padding:3px 8px;background:#fef3c7;border:1px solid #fde68a;border-radius:4px;cursor:pointer;font-size:11px;color:#92400e;margin-right:4px;" title="Request Edit"><i class="fas fa-edit"></i></button><button onclick="event.stopPropagation();jeRequestDelete(${e.id},'${fEsc(e.reference||'').replace(/'/g,"\\'")}')" style="padding:3px 8px;background:#fee2e2;border:1px solid #fecaca;border-radius:4px;cursor:pointer;font-size:11px;color:#991b1b;" title="Request Delete"><i class="fas fa-trash"></i></button>` : ''}</td>
          </tr>`;
        });
      }
      tbody.innerHTML = html;

      // Pagination
      const pgEl = document.getElementById('je-pagination');
      if(pagination && pagination.total_pages > 1){
        let btns = '';
        btns += `<button onclick="jePage=${jePage-1};jeLoad()" ${jePage<=1?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${jePage<=1?'var(--gray-100)':'white'};color:${jePage<=1?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${jePage<=1?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-left"></i> Prev</button>`;
        let sp=Math.max(1,jePage-3),ep=Math.min(pagination.total_pages,sp+6);if(ep-sp<6)sp=Math.max(1,ep-6);
        for(let p=sp;p<=ep;p++){const a=p===jePage;btns+=`<button onclick="jePage=${p};jeLoad()" style="padding:6px 10px;border:1px solid ${a?'var(--primary-color)':'var(--gray-200)'};background:${a?'var(--primary-color)':'white'};color:${a?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:12px;font-weight:${a?'600':'400'};">${p}</button>`;}
        btns += `<button onclick="jePage=${jePage+1};jeLoad()" ${jePage>=pagination.total_pages?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${jePage>=pagination.total_pages?'var(--gray-100)':'white'};color:${jePage>=pagination.total_pages?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${jePage>=pagination.total_pages?'not-allowed':'pointer'};font-size:12px;">Next <i class="fas fa-chevron-right"></i></button>`;
        pgEl.innerHTML = `<span style="font-size:12px;color:var(--gray-500);">Page ${pagination.page} of ${pagination.total_pages} (${pagination.total} entries)</span><div style="display:flex;gap:4px;">${btns}</div>`;
      } else {
        pgEl.innerHTML = pagination ? `<span style="font-size:12px;color:var(--gray-500);">${pagination.total} entries</span><div></div>` : '';
      }
    } else { tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No entries.</td></tr>'; document.getElementById('je-pagination').innerHTML=''; }
  } catch(e){ console.error(e); }
}
async function jeView(id){
  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=journal_entry&id='+id);
    const j = await r.json();
    if(j.success && j.data){
      const e=j.data, lines=e.lines||[];
      const modal=document.createElement('div'); modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
      modal.innerHTML=`<div style="background:white;border-radius:12px;width:650px;max-width:90vw;max-height:80vh;overflow-y:auto;padding:24px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:16px;"><h3 style="font-size:16px;font-weight:700;">Journal Entry: ${fEsc(e.reference)}</h3><button onclick="this.closest('[style*=position]').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
        <div style="font-size:13px;margin-bottom:8px;color:var(--gray-600);"><strong>Date:</strong> ${e.entry_date} | <strong>Description:</strong> ${fEsc(e.description)}</div>
        <div id="je-source" style="font-size:12px;margin-bottom:14px;color:var(--gray-500);"></div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;border:1px solid var(--gray-200);border-radius:6px;overflow:hidden;">
          <thead><tr style="background:var(--gray-50);"><th style="padding:8px 10px;text-align:left;">Account</th><th style="padding:8px 10px;text-align:left;">Description</th><th style="padding:8px 10px;text-align:right;">Debit</th><th style="padding:8px 10px;text-align:right;">Credit</th></tr></thead>
          <tbody>${lines.map(l=>`<tr style="border-top:1px solid var(--gray-100);"><td style="padding:6px 10px;"><span style="color:var(--primary-color);font-family:monospace;">${fEsc(l.account_code||'')}</span> ${fEsc(l.account_name||'')}</td><td style="padding:6px 10px;">${fEsc(l.line_description||'')}</td><td style="padding:6px 10px;text-align:right;font-weight:600;">${parseFloat(l.debit||0)>0?parseFloat(l.debit).toLocaleString():''}</td><td style="padding:6px 10px;text-align:right;font-weight:600;">${parseFloat(l.credit||0)>0?parseFloat(l.credit).toLocaleString():''}</td></tr>`).join('')}</tbody>
        </table></div>`;
      document.body.appendChild(modal);
      jeLoadSource(id, modal);
    }
  } catch(e){ alert('Error'); }
}
async function jeLoadSource(id, modal){
  try{
    const r = await fetch(_fnBase + '/api.php?endpoint=finance/finance-ops&action=entry_source&id='+id); const j = await r.json();
    const box = modal.querySelector('#je-source'); if(!j.success||!box) return; const s = j.data;
    let html = '<i class="fas fa-link"></i> Source: ' + (s.link ? '<a href="'+fEsc(s.link)+'" style="color:var(--primary-color);">'+fEsc(s.label)+'</a>' : fEsc(s.label));
    if(s.reversal_of) html += ' &middot; <a href="#" onclick="event.preventDefault();this.closest(\'[style*=position]\').remove();jeView('+s.reversal_of+')" style="color:var(--primary-color);">reverses entry #'+s.reversal_of+'</a>';
    if(s.reversal_id) html += ' &middot; <span style="color:#b45309;">reversed by entry #'+s.reversal_id+'</span>';
    else if(!s.reversal_of) html += ' &middot; <button onclick="jeReverse('+id+')" style="border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:5px;padding:2px 10px;font-size:11px;cursor:pointer;"><i class="fas fa-undo"></i> Reverse</button>';
    box.innerHTML = html;
  }catch(e){}
}
async function jeReverse(id){
  const reason = prompt('Reason for reversing this entry (required):'); if(!reason) return;
  const r = await fetch(_fnBase + '/api.php?endpoint=finance/finance-ops&action=reverse_entry', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:id, reason:reason})});
  const j = await r.json(); alert(j.message || (j.success?'Reversed':'Failed'));
  if(j.success){ document.querySelectorAll('[style*=position]').forEach(m=>{ if(m.querySelector('#je-source')) m.remove(); }); jeLoad(); }
}
async function jeEditDate(id, currentDate){
  // Show a small inline modal to pick a new date — no approval needed
  const existing = document.getElementById('je-date-modal');
  if(existing) existing.remove();

  const modal = document.createElement('div');
  modal.id = 'je-date-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;padding:24px;width:320px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h4 style="font-size:14px;font-weight:700;margin:0;"><i class="fas fa-calendar-alt" style="color:#2563eb;margin-right:6px;"></i>Edit Entry Date</h4>
      <button onclick="document.getElementById('je-date-modal').remove()" style="border:none;background:none;font-size:20px;cursor:pointer;color:#64748b;">&times;</button>
    </div>
    <label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px;">New Date</label>
    <input type="date" id="je-date-input" value="${currentDate}" style="width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;box-sizing:border-box;">
    <div style="display:flex;gap:8px;margin-top:16px;">
      <button onclick="jeEditDateSave(${id})" style="flex:1;padding:9px;background:#2563eb;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;">Save Date</button>
      <button onclick="document.getElementById('je-date-modal').remove()" style="flex:1;padding:9px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;">Cancel</button>
    </div>
  </div>`;
  document.body.appendChild(modal);
}

async function jeEditDateSave(id){
  const date = document.getElementById('je-date-input').value;
  if(!date){ alert('Please select a date.'); return; }
  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=update_journal_entry_date&id='+id, {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({entry_date:date})
    });
    const j = await r.json();
    if(j.success){
      document.getElementById('je-date-modal').remove();
      jeLoad();
    } else {
      alert('Error: '+(j.message||'Failed to update date'));
    }
  }catch(e){ alert('Failed: '+e.message); }
}

async function jeRequestEdit(id, reference){
  // Check if there's already a pending/approved edit request
  try{
    const chk = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=edit-requests&action=check&entity_type=journal_entry&entity_id='+id);
    const cj = await chk.json();
    if(cj.success && cj.data && cj.data.status==='approved'){
      // Already approved — open edit modal
      window._jeEditRequestId = cj.data.id;
      jeOpenEditModal(id);
      return;
    }
    if(cj.success && cj.data && cj.data.status==='pending'){
      alert('An edit request is already pending approval for this journal entry.');
      return;
    }
  }catch(e){}

  const reason = prompt('Reason for editing this journal entry (Ref: '+reference+'):\n(Minimum 10 characters — requires admin approval)');
  if(!reason || reason.trim().length < 10){
    if(reason !== null) alert('Please provide a detailed reason (at least 10 characters).');
    return;
  }

  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=edit-requests&action=create', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({entity_type:'journal_entry', entity_id:id, entity_name:'JE: '+reference, reason:reason.trim()})
    });
    const j = await r.json();
    if(j.success) alert('✓ Edit request submitted! An admin must approve it before you can make changes.');
    else alert('Error: '+(j.message||'Failed to submit edit request'));
  }catch(e){alert('Failed to submit request.');}
}

async function jeOpenEditModal(id){
  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=journal_entry&id='+id);
    const j = await r.json();
    if(!j.success || !j.data) return alert('Could not load journal entry');
    const e = j.data, lines = e.lines || [];

    await jeLoadAccounts();

    const modal = document.createElement('div');
    modal.id = 'je-edit-modal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
    modal.innerHTML = `<div style="background:white;border-radius:12px;width:700px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
        <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-edit" style="color:#d97706;"></i> Edit Journal Entry: ${fEsc(e.reference)}</h3>
        <button onclick="document.getElementById('je-edit-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
      </div>
      <div id="je-edit-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
        <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Date *</label><input type="date" id="je-edit-date" value="${e.entry_date||''}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
        <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Reference</label><input id="je-edit-ref" value="${fEsc(e.reference||'')}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" readonly></div>
        <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Description *</label><input id="je-edit-desc" value="${fEsc(e.description||'')}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      </div>
      <div style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <label style="font-size:13px;font-weight:600;color:var(--gray-700);">Entry Lines</label>
          <button onclick="jeEditAddLine()" type="button" style="padding:4px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-plus"></i> Add Line</button>
        </div>
        <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="background:var(--gray-50);">
              <th style="padding:8px;text-align:left;">Account</th>
              <th style="padding:8px;text-align:left;">Description</th>
              <th style="padding:8px;text-align:right;width:100px;">Debit</th>
              <th style="padding:8px;text-align:right;width:100px;">Credit</th>
              <th style="padding:8px;width:30px;"></th>
            </tr></thead>
            <tbody id="je-edit-lines-tbody"></tbody>
            <tfoot><tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);">
              <td colspan="2" style="padding:8px;font-weight:700;">TOTALS</td>
              <td style="padding:8px;text-align:right;font-weight:700;" id="je-edit-total-debit">0</td>
              <td style="padding:8px;text-align:right;font-weight:700;" id="je-edit-total-credit">0</td>
              <td></td>
            </tr></tfoot>
          </table>
        </div>
        <div id="je-edit-balance-msg" style="margin-top:6px;font-size:12px;font-weight:600;display:none;"></div>
      </div>
      <button onclick="jeSaveEdit(${id})" style="width:100%;padding:10px;background:#d97706;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save Changes</button>
    </div>`;
    document.body.appendChild(modal);

    // Populate lines
    lines.forEach(l => {
      jeEditAddLine(l.account_id, l.account_code+' - '+l.account_name, l.line_description, parseFloat(l.debit)||0, parseFloat(l.credit)||0);
    });
    jeEditCalcTotals();
  }catch(e){ alert('Error loading entry: '+e.message); }
}

function jeEditAddLine(accId, accDisplay, lineDesc, debit, credit){
  const tbody = document.getElementById('je-edit-lines-tbody');
  const row = document.createElement('tr');
  row.className = 'je-edit-line-row';
  row.style.borderBottom = '1px solid var(--gray-100)';
  row.innerHTML = `
    <td style="padding:4px;"><input type="text" class="je-edit-acc-search" value="${fEsc(accDisplay||'')}" placeholder="Search account..." onfocus="jeAccShow(this)" oninput="jeAccShow(this,true)" onkeydown="jeAccKey(event,this)" autocomplete="off" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"><input type="hidden" class="je-edit-acc" value="${accId||''}"></td>
    <td style="padding:4px;"><input class="je-edit-line-desc" value="${fEsc(lineDesc||'')}" placeholder="Line description" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
    <td style="padding:4px;"><input type="number" class="je-edit-debit" value="${debit||''}" placeholder="0" min="0" oninput="jeEditCalcTotals()" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
    <td style="padding:4px;"><input type="number" class="je-edit-credit" value="${credit||''}" placeholder="0" min="0" oninput="jeEditCalcTotals()" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:right;"></td>
    <td style="padding:4px;text-align:center;"><button onclick="this.closest('tr').remove();jeEditCalcTotals();" style="border:none;background:none;color:#dc2626;cursor:pointer;"><i class="fas fa-trash"></i></button></td>
  `;
  tbody.appendChild(row);

}

function jeEditCalcTotals(){
  let totalDebit = 0, totalCredit = 0;
  document.querySelectorAll('.je-edit-debit').forEach(i => { totalDebit += parseFloat(i.value) || 0; });
  document.querySelectorAll('.je-edit-credit').forEach(i => { totalCredit += parseFloat(i.value) || 0; });
  document.getElementById('je-edit-total-debit').textContent = totalDebit.toLocaleString();
  document.getElementById('je-edit-total-credit').textContent = totalCredit.toLocaleString();
  const msg = document.getElementById('je-edit-balance-msg');
  if(totalDebit > 0 && totalDebit === totalCredit){ msg.style.display='block'; msg.style.color='#065f46'; msg.innerHTML='<i class="fas fa-check-circle"></i> Balanced ✓'; }
  else if(totalDebit > 0 || totalCredit > 0){ msg.style.display='block'; msg.style.color='#991b1b'; msg.innerHTML='<i class="fas fa-exclamation-triangle"></i> Not balanced! Diff: '+Math.abs(totalDebit-totalCredit).toLocaleString(); }
  else { msg.style.display='none'; }
}

async function jeSaveEdit(jeId){
  const date = document.getElementById('je-edit-date').value;
  const desc = document.getElementById('je-edit-desc').value.trim();
  if(!date || !desc) return alert('Date and description required.');

  const rows = document.querySelectorAll('.je-edit-line-row');
  const lines = [];
  rows.forEach(row => {
    const accId = row.querySelector('.je-edit-acc').value;
    const lineDesc = row.querySelector('.je-edit-line-desc').value;
    const debit = parseFloat(row.querySelector('.je-edit-debit').value) || 0;
    const credit = parseFloat(row.querySelector('.je-edit-credit').value) || 0;
    if(accId && (debit > 0 || credit > 0)) lines.push({account_id:parseInt(accId), line_description:lineDesc, debit, credit});
  });
  if(lines.length < 2) return alert('Need at least 2 lines.');
  const totalDebit = lines.reduce((s,l)=>s+l.debit,0);
  const totalCredit = lines.reduce((s,l)=>s+l.credit,0);
  if(Math.abs(totalDebit - totalCredit) > 0.01) return alert('Entry must be balanced!');

  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=update_journal_entry&id='+jeId, {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({entry_date:date, description:desc, lines:lines, edit_request_id: window._jeEditRequestId||null})
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Journal entry updated!');
      document.getElementById('je-edit-modal').remove();
      jeLoad();
    } else alert('Error: '+j.message);
  }catch(e){ alert('Failed: '+e.message); }
}

async function jeRequestDelete(id, reference){
  // Check if there's already a pending deletion request
  try{
    const chk = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=deletion-requests&action=check&entity_type=journal_entry&entity_id='+id);
    const cj = await chk.json();
    if(cj.success && cj.data){
      if(cj.data.status==='pending'){ alert('A deletion request is already pending for this journal entry.'); return; }
      if(cj.data.status==='approved'){
        if(confirm('Deletion APPROVED. Execute deletion now?\n\nJournal Entry: '+reference+'\n\nThis cannot be undone.')){
          jeExecuteDelete(cj.data.id);
        }
        return;
      }
    }
  }catch(e){}

  const remarks = prompt('Reason for deleting journal entry (Ref: '+reference+'):\n(Minimum 10 characters — requires admin approval)');
  if(!remarks || remarks.trim().length < 10){
    if(remarks !== null) alert('Please provide a detailed reason (at least 10 characters).');
    return;
  }

  try{
    const fd = new FormData();
    fd.append('entity_type','journal_entry');
    fd.append('entity_id', id);
    fd.append('entity_name', 'JE: '+reference);
    fd.append('remarks', remarks.trim());
    fd.append('requested_by', <?php echo (int)($_SESSION['user_id'] ?? 0); ?>);
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=deletion-requests&action=create', {method:'POST', body:fd});
    const j = await r.json();
    if(j.success) alert('✓ Deletion request submitted! An admin will review it.');
    else alert('Error: '+(j.message||'Failed'));
  }catch(e){alert('Failed to submit request.');}
}

async function jeExecuteDelete(requestId){
  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=deletion-requests&action=execute', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({id:requestId, executed_by:<?php echo (int)($_SESSION['user_id'] ?? 0); ?>})
    });
    const j = await r.json();
    if(j.success){ alert('✓ '+j.message); jeLoad(); }
    else alert('Error: '+j.message);
  }catch(e){alert('Deletion failed');}
}

function jeClearFilter(){
  document.getElementById('je-date-from').value = '';
  document.getElementById('je-date-to').value = '';
  jePage = 1;
  jeLoad();
}
let jePage = 1;
jeLoad();
</script>

<?php elseif ($subModule == 'general-ledger'): ?>
<!-- ====== GENERAL LEDGER ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-alt" style="color:var(--primary-color);"></i> General Ledger</h3>
  </div>
  <div style="display:flex;gap:8px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
    <select id="gl-account" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;min-width:250px;"><option value="">Select Account...</option></select>
    <input type="date" id="gl-from" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
    <input type="date" id="gl-to" value="<?php echo date('Y-m-d');?>" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
    <button onclick="glLoad()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Load</button>
  </div>
  <div id="gl-content"><div style="padding:30px;text-align:center;color:var(--gray-400);">Select an account and date range.</div></div>
</div>
<script>
function fEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function glInit(){
  const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts');
  const j = await r.json();
  if(j.success){
    const accounts = j.data.accounts||j.data;
    const sel=document.getElementById('gl-account');
    accounts.forEach(a=>{ sel.innerHTML+=`<option value="${a.id}">${fEsc(a.account_code)} - ${fEsc(a.account_name)}</option>`; });
  }
  document.getElementById('gl-from').value=new Date(new Date().setMonth(new Date().getMonth()-1)).toISOString().split('T')[0];
}
async function glLoad(){
  const accountId=document.getElementById('gl-account').value;
  if(!accountId) return;
  const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=ledger&account_id='+accountId+'&from='+document.getElementById('gl-from').value+'&to='+document.getElementById('gl-to').value);
  const j = await r.json();
  const div=document.getElementById('gl-content');
  if(j.success && j.data && j.data.length){
    let bal=0;
    div.innerHTML='<div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px 10px;text-align:left;">Date</th><th style="padding:8px 10px;text-align:left;">Ref</th><th style="padding:8px 10px;text-align:left;">Description</th><th style="padding:8px 10px;text-align:right;">Debit</th><th style="padding:8px 10px;text-align:right;">Credit</th><th style="padding:8px 10px;text-align:right;">Balance</th></tr></thead><tbody>'+j.data.map(l=>{
      bal+=parseFloat(l.debit||0)-parseFloat(l.credit||0);
      return `<tr style="border-top:1px solid var(--gray-100);"><td style="padding:6px 10px;">${l.entry_date||''}</td><td style="padding:6px 10px;font-family:monospace;font-size:11px;">${fEsc(l.reference||'')}</td><td style="padding:6px 10px;">${fEsc(l.line_description||l.description||'')}</td><td style="padding:6px 10px;text-align:right;">${parseFloat(l.debit||0)>0?parseFloat(l.debit).toLocaleString():''}</td><td style="padding:6px 10px;text-align:right;">${parseFloat(l.credit||0)>0?parseFloat(l.credit).toLocaleString():''}</td><td style="padding:6px 10px;text-align:right;font-weight:600;">${bal.toLocaleString()}</td></tr>`;
    }).join('')+'</tbody></table></div>';
  } else div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No entries for this period.</div>';
}
glInit();
</script>

<?php elseif ($subModule == 'balance-sheet'): ?>
<!-- ====== BALANCE SHEET ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-balance-scale" style="color:var(--primary-color);"></i> Balance Sheet</h3>
    <div style="display:flex;gap:8px;align-items:center;"><span style="font-size:12px;color:var(--gray-500);">As of:</span><input type="date" id="bs-date" value="<?php echo date('Y-m-d');?>" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><button onclick="bsLoad()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Generate</button></div>
  </div>
  <div id="bs-content"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>

<!-- Balance Sheet Note Modal — outside card so position:fixed is not clipped -->
<div id="bs-modal" onclick="if(event.target===this)bsCloseNote()" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:99999;align-items:flex-start;justify-content:center;padding-top:60px;">
  <div style="background:white;border-radius:12px;width:94%;max-width:600px;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
      <span style="font-size:14px;font-weight:700;" id="bs-modal-title"></span>
      <button onclick="bsCloseNote()" style="background:none;border:none;font-size:22px;line-height:1;cursor:pointer;color:#64748b;padding:0 4px;">&times;</button>
    </div>
    <div id="bs-modal-body" style="overflow-y:auto;flex:1;"></div>
  </div>
</div>
<script>
(function(){
function bsEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function bsFmt(n){return Number(n||0).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0});}

/* Open note modal */
window.bsOpenNote = function(title, color, childrenJson){
  const children = JSON.parse(decodeURIComponent(childrenJson));
  let rows = '';
  children.forEach(function(c){
    const cb = parseFloat(c.balance||0);
    rows += '<tr style="border-bottom:1px solid #f1f5f9;">'
      + '<td style="padding:7px 14px;font-family:monospace;font-size:12px;color:#64748b;">'+bsEsc(c.account_code)+'</td>'
      + '<td style="padding:7px 14px;font-size:13px;">'+bsEsc(c.account_name)+'</td>'
      + '<td style="padding:7px 14px;text-align:right;font-weight:600;font-size:13px;">'+bsFmt(cb)+' RWF</td>'
      + '</tr>';
  });
  const total = children.reduce(function(s,c){return s+parseFloat(c.balance||0);},0);
  document.getElementById('bs-modal-title').innerHTML = '<span style="color:'+color+';">'+bsEsc(title)+'</span>';
  document.getElementById('bs-modal-body').innerHTML =
    '<table style="width:100%;border-collapse:collapse;font-size:13px;">'
    + '<thead><tr style="background:#f8fafc;border-bottom:2px solid '+color+'30;">'
    + '<th style="padding:8px 14px;text-align:left;font-weight:600;color:#64748b;">Code</th>'
    + '<th style="padding:8px 14px;text-align:left;font-weight:600;color:#64748b;">Account Name</th>'
    + '<th style="padding:8px 14px;text-align:right;font-weight:600;color:#64748b;">Balance</th>'
    + '</tr></thead><tbody>'+rows+'</tbody>'
    + '<tfoot><tr style="background:'+color+'10;border-top:2px solid '+color+'40;">'
    + '<td colspan="2" style="padding:8px 14px;font-weight:700;color:'+color+';">Total</td>'
    + '<td style="padding:8px 14px;text-align:right;font-weight:700;color:'+color+';">'+bsFmt(total)+' RWF</td>'
    + '</tr></tfoot></table>';
  document.getElementById('bs-modal').style.display = 'flex';
};

window.bsCloseNote = function(){
  document.getElementById('bs-modal').style.display = 'none';
};

/*
  Correct balance sheet grouping:
  - Find all leaf accounts (has_sub=0) with non-zero balance → group by their parent
  - Also find has_sub=1 accounts that carry their own balance (e.g. 1300 Acc.Dep) → show under their parent
  - The parent shown on the BS row = sum of all its children's balances
  - If an account's parent is the root (parent_id=NULL) show it directly
  - Skip any row whose net balance = 0
  - "Note" button opens modal with the individual child breakdown
*/
function bsSection(title, items, color){
  const byId = {};
  items.forEach(function(a){ byId[a.id]=a; a.balance=parseFloat(a.balance||0); });

  // Collect all accounts with a non-zero balance
  const withBal = items.filter(function(a){ return a.balance!==0; });

  // For each such account, find its "display parent":
  // - if its parent has has_sub=1 AND parent's parent exists → use parent as group row
  // - if its parent is root (parent_id=null) → show the account itself as the row
  // - if it IS the root → skip
  const groupMap = {}; // parentId (or own id) → { account, children[] }

  withBal.forEach(function(a){
    const parent = byId[a.parent_id];
    if(!a.parent_id) return; // root level, skip

    // determine the group key: walk up until we find an account whose parent is root or has no parent
    let groupAccount = parent;
    if(!groupAccount){
      // orphan — show itself
      groupAccount = a;
    } else {
      // if parent's parent is also non-root, keep the parent as-is (it's already a mid-level group)
      // The rule: the group shown on BS = the direct parent of the leaf
      // unless that parent is root (parent_id=null), in which case show the account itself
      if(!groupAccount.parent_id){
        // parent is root → the account itself is the BS line
        groupAccount = a;
      }
    }

    const key = groupAccount.id;
    if(!groupMap[key]){
      groupMap[key] = { account: groupAccount, children: [] };
    }
    if(groupAccount.id !== a.id){
      groupMap[key].children.push(a);
    }
  });

  let sectionTotal = 0;
  let html = '';

  // Sort by account_code
  const groups = Object.values(groupMap).sort(function(a,b){
    return (a.account.account_code||'').localeCompare(b.account.account_code||'');
  });

  groups.forEach(function(g){
    const children = g.children;
    const bal = children.length
      ? children.reduce(function(s,c){ return s+c.balance; }, 0)
      : g.account.balance;

    if(bal === 0) return;
    sectionTotal += bal;

    const hasChildren = children.length > 0;
    const childrenJson = hasChildren ? encodeURIComponent(JSON.stringify(children)) : '';
    const label = bsEsc(g.account.account_code)+' '+bsEsc(g.account.account_name);

    html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;border-bottom:1px solid #f1f5f9;">'
      + '<span style="font-size:13px;font-weight:600;color:#1e293b;">'+label+'</span>'
      + '<span style="display:flex;align-items:center;gap:10px;">'
      + '<span style="font-weight:700;font-size:13px;'+( bal<0?'color:#dc2626;':'')+'">'+bsFmt(bal)+' RWF</span>'
      + (hasChildren
          ? '<button onclick="bsOpenNote(\''+label.replace(/\\/g,'\\\\').replace(/'/g,"\\'")+'\',' +
            '\''+color+'\',\''+childrenJson+'\')" '
            +'style="padding:2px 10px;font-size:11px;border:1px solid '+color+';color:'+color
            +';background:white;border-radius:10px;cursor:pointer;white-space:nowrap;">'
            +'Note <i class="fas fa-external-link-alt fa-xs"></i></button>'
          : '')
      + '</span>'
      + '</div>';
  });

  if(!html) return { html:'', total:0 };

  return { html:
    '<div style="margin-bottom:18px;">'
    + '<div style="padding:7px 10px;background:'+color+'12;border-left:4px solid '+color
    + ';border-radius:4px;margin-bottom:6px;font-size:13px;font-weight:700;color:'+color+';">'
    + bsEsc(title)+'</div>'
    + html
    + '</div>',
    total: sectionTotal
  };
}

async function bsLoad(){
  try{
    const bsDate = document.getElementById('bs-date').value;
    const [bsRes, isRes] = await Promise.all([
      fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=balance_sheet&date='+bsDate),
      fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=income_statement&from=2019-01-01&to='+bsDate)
    ]);
    const j = await bsRes.json();
    const isJ = await isRes.json();
    if(!j.success||!j.data){
      document.getElementById('bs-content').innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No data.</div>';
      return;
    }
    const d = j.data;
    const netIncome = d.current_earnings !== undefined ? parseFloat(d.current_earnings||0) : (isJ.success ? parseFloat(isJ.data.net_income||0) : 0); // surplus not yet closed to Retained Earnings
    const incomeTax = netIncome>0 ? Math.round(netIncome*0.28) : 0;
    const profitAfterTax = netIncome - incomeTax;

    const aR = bsSection('ASSETS',      d.assets,      '#2563eb');
    const lR = bsSection('LIABILITIES', d.liabilities, '#dc2626');
    const eR = bsSection('EQUITY',      d.equity,      '#7c3aed');

    const totalLiab   = lR.total + incomeTax;
    const totalEquity = eR.total + profitAfterTax;

    document.getElementById('bs-content').innerHTML =
      '<div style="text-align:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #f1f5f9;">'
      + '<div style="font-size:15px;font-weight:700;">BALANCE SHEET</div>'
      + '<div style="font-size:12px;color:#64748b;">As of '+bsDate+'</div>'
      + '</div>'
      + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">'
      + '<div>'
      + aR.html
      + '<div style="display:flex;justify-content:space-between;padding:10px;font-weight:700;font-size:14px;border-top:2px solid #2563eb;color:#2563eb;background:#eff6ff;border-radius:4px;">'
      + '<span>TOTAL ASSETS</span><span>'+bsFmt(aR.total)+' RWF</span></div>'
      + '</div>'
      + '<div>'
      + lR.html
      + (incomeTax>0
          ? '<div style="display:flex;justify-content:space-between;padding:6px 10px;font-size:12px;color:#dc2626;font-weight:600;background:#fef2f2;border-radius:4px;margin-bottom:6px;border:1px solid #fecaca;"><span>Taxes Payable (28%)</span><span>'+bsFmt(incomeTax)+' RWF</span></div>'
          : '')
      + '<div style="display:flex;justify-content:space-between;padding:8px 10px;font-weight:700;border-top:2px solid #dc2626;color:#dc2626;background:#fef2f2;border-radius:4px;margin-bottom:16px;font-size:13px;">'
      + '<span>Total Liabilities</span><span>'+bsFmt(totalLiab)+' RWF</span></div>'
      + eR.html
      + '<div style="display:flex;justify-content:space-between;padding:6px 10px;font-size:12px;font-weight:600;background:'+(profitAfterTax>=0?'#f0fdf4':'#fef2f2')+';border-radius:4px;margin-bottom:6px;color:'+(profitAfterTax>=0?'#059669':'#dc2626')+';">'
      + '<span>Retained Earnings (Profit After Tax)</span><span>'+bsFmt(profitAfterTax)+' RWF</span></div>'
      + '<div style="display:flex;justify-content:space-between;padding:8px 10px;font-weight:700;border-top:2px solid #7c3aed;color:#7c3aed;background:#f5f3ff;border-radius:4px;margin-bottom:12px;font-size:13px;">'
      + '<span>Total Equity</span><span>'+bsFmt(totalEquity)+' RWF</span></div>'
      + '<div style="display:flex;justify-content:space-between;padding:12px 14px;background:#1e293b;border-radius:8px;font-weight:700;font-size:14px;color:white;">'
      + '<span>LIABILITIES + EQUITY</span><span>'+bsFmt(totalLiab+totalEquity)+' RWF</span></div>'
      + (Math.abs(aR.total-(totalLiab+totalEquity))<1
          ? '<div style="margin-top:8px;padding:7px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;font-size:12px;color:#065f46;text-align:center;"><i class="fas fa-check-circle"></i> Balanced</div>'
          : '<div style="margin-top:8px;padding:7px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:12px;color:#991b1b;text-align:center;"><i class="fas fa-exclamation-triangle"></i> Difference: '+bsFmt(aR.total-(totalLiab+totalEquity))+' RWF</div>')
      + '</div></div>';
  } catch(e){ document.getElementById('bs-content').innerHTML='<p style="color:red;">Error: '+e.message+'</p>'; }
}

window.bsLoad = bsLoad;
bsLoad();
})();
</script>

<?php elseif ($subModule == 'income-statement'): ?>
<!-- ====== INCOME STATEMENT ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-chart-line" style="color:#16a34a;"></i> Income Statement (Profit & Loss)</h3>
    <div style="display:flex;gap:8px;align-items:center;"><input type="date" id="is-from" value="<?php echo date('Y-m-01');?>" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><input type="date" id="is-to" value="<?php echo date('Y-m-d');?>" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><button onclick="isLoad()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Generate</button></div>
  </div>
  <div id="is-content"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
function fEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function isLoad(){
  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=income_statement&from='+document.getElementById('is-from').value+'&to='+document.getElementById('is-to').value);
    const j=await r.json();
    if(j.success&&j.data){
      const d=j.data;
      let html=`<div style="text-align:center;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
        <h4 style="font-size:16px;font-weight:700;color:var(--gray-900);">INCOME STATEMENT</h4>
        <p style="font-size:12px;color:var(--gray-500);">For the period ${document.getElementById('is-from').value} to ${document.getElementById('is-to').value}</p>
      </div>`;

      // Revenue Section
      html+=`<div style="margin-bottom:24px;">
        <h4 style="font-size:13px;font-weight:700;color:#16a34a;margin-bottom:8px;padding:8px 12px;background:#f0fdf4;border-radius:6px;">REVENUE / INCOME</h4>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">`;
      (d.income||[]).forEach(a=>{
        const bal=parseFloat(a.balance||0);
        const isParent=!a.parent_id;
        html+=`<tr><td style="padding:5px 12px;${isParent?'font-weight:600;':'padding-left:28px;'}">${fEsc(a.account_code)} ${fEsc(a.account_name)}</td><td style="padding:5px 12px;text-align:right;${isParent?'font-weight:600;':''}${bal===0?'color:var(--gray-400);':'color:#16a34a;'}">${bal.toLocaleString()}</td></tr>`;
      });
      html+=`<tr style="border-top:2px solid #16a34a;"><td style="padding:10px 12px;font-weight:700;font-size:14px;">Total Revenue</td><td style="padding:10px 12px;text-align:right;font-weight:700;font-size:14px;color:#16a34a;">${parseFloat(d.total_income||0).toLocaleString()}</td></tr>`;
      html+=`</table></div>`;

      // Expenses Section
      html+=`<div style="margin-bottom:24px;">
        <h4 style="font-size:13px;font-weight:700;color:#d97706;margin-bottom:8px;padding:8px 12px;background:#fffbeb;border-radius:6px;">OPERATING EXPENSES</h4>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">`;
      (d.expenses||[]).forEach(a=>{
        const bal=parseFloat(a.balance||0);
        const isParent=!a.parent_id;
        html+=`<tr><td style="padding:5px 12px;${isParent?'font-weight:600;':'padding-left:28px;'}">${fEsc(a.account_code)} ${fEsc(a.account_name)}</td><td style="padding:5px 12px;text-align:right;${isParent?'font-weight:600;':''}${bal===0?'color:var(--gray-400);':'color:#d97706;'}">${bal.toLocaleString()}</td></tr>`;
      });
      html+=`<tr style="border-top:2px solid #d97706;"><td style="padding:10px 12px;font-weight:700;font-size:14px;">Total Expenses</td><td style="padding:10px 12px;text-align:right;font-weight:700;font-size:14px;color:#d97706;">${parseFloat(d.total_expenses||0).toLocaleString()}</td></tr>`;
      html+=`</table></div>`;

      // Gross Profit
      const gross = parseFloat(d.total_income||0) - parseFloat(d.total_expenses||0);
      html+=`<div style="margin-bottom:16px;padding:10px 12px;border:1px solid var(--gray-200);border-radius:6px;display:flex;justify-content:space-between;font-size:14px;"><span style="font-weight:600;">Gross Profit</span><span style="font-weight:700;color:${gross>=0?'#065f46':'#991b1b'};">${gross.toLocaleString()}</span></div>`;

      // Net Income (Profit Before Tax)
      const net=parseFloat(d.net_income||0);
      html+=`<div style="padding:10px 12px;border:1px solid var(--gray-200);border-radius:6px;display:flex;justify-content:space-between;font-size:14px;margin-bottom:8px;"><span style="font-weight:600;">Profit Before Tax</span><span style="font-weight:700;color:${net>=0?'#065f46':'#991b1b'};">${net.toLocaleString()} RWF</span></div>`;

      // Income Tax (28%)
      const taxRate=0.28;
      const incomeTax=net>0?Math.round(net*taxRate):0;
      html+=`<div style="padding:10px 12px;border:1px solid var(--gray-200);border-radius:6px;display:flex;justify-content:space-between;font-size:14px;margin-bottom:8px;"><span style="font-weight:500;color:var(--gray-600);">Income Tax (28%)</span><span style="font-weight:600;color:#dc2626;">(${incomeTax.toLocaleString()}) RWF</span></div>`;

      // Profit After Tax
      const profitAfterTax=net-incomeTax;
      html+=`<div style="background:${profitAfterTax>=0?'#d1fae5':'#fee2e2'};border-radius:8px;padding:16px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:16px;font-weight:700;">PROFIT AFTER TAX</span>
        <span style="font-size:22px;font-weight:700;color:${profitAfterTax>=0?'#065f46':'#991b1b'};">${profitAfterTax.toLocaleString()} RWF</span>
      </div>`;
      document.getElementById('is-content').innerHTML=html;
    }
  } catch(e){ document.getElementById('is-content').innerHTML='<p style="color:red;">Error</p>'; }
}
isLoad();
</script>

<?php elseif ($subModule == 'trial-balance'): ?>
<!-- ====== TRIAL BALANCE ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-list-ol" style="color:var(--primary-color);"></i> Trial Balance</h3>
    <div style="display:flex;gap:8px;align-items:center;">
      <span style="font-size:12px;color:var(--gray-500);">As of:</span>
      <input type="date" id="tb-date" value="<?php echo date('Y-m-d');?>" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <button onclick="tbLoad()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:500;"><i class="fas fa-calculator"></i> Generate</button>
      <button onclick="tbPrint()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>
  <div id="tb-content"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading trial balance...</div></div>
</div>
<script>
(function(){
function tbEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

window.tbLoad = async function(){
  var asOf = document.getElementById('tb-date').value;
  var el = document.getElementById('tb-content');
  el.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Generating...</div>';
  try{
    var r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=trial_balance&date='+asOf);
    if(!r.ok){ el.innerHTML='<div style="padding:20px;color:#dc2626;text-align:center;">API Error: HTTP '+r.status+'</div>'; return; }
    var j = await r.json();
    if(!j.success || !j.data){ el.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No data available.</div>'; return; }
    var d = j.data;
    var accounts = d.accounts || [];

    // Filter out accounts with zero balances
    var activeAccounts = accounts.filter(function(a){ return a.debit_balance > 0 || a.credit_balance > 0; });

    if(!activeAccounts.length){
      el.innerHTML='<div style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-calculator" style="font-size:28px;display:block;margin-bottom:10px;"></i>No transactions recorded as of '+tbEsc(asOf)+'.<br><span style="font-size:12px;">Create journal entries first.</span></div>';
      return;
    }

    // Group by account type
    var typeLabels = {asset:'Assets', liability:'Liabilities', equity:'Equity', income:'Income / Revenue', expense:'Expenses'};
    var typeColors = {asset:'#2563eb', liability:'#dc2626', equity:'#7c3aed', income:'#16a34a', expense:'#d97706'};
    var grouped = {};
    activeAccounts.forEach(function(a){
      if(!grouped[a.account_type]) grouped[a.account_type] = [];
      grouped[a.account_type].push(a);
    });

    var html = '<div style="margin-bottom:12px;padding:12px;background:var(--gray-50);border-radius:8px;display:flex;justify-content:space-between;font-size:13px;">';
    html += '<span><strong>Trial Balance</strong> as of <strong>'+tbEsc(asOf)+'</strong></span>';
    html += '<span>'+activeAccounts.length+' active accounts</span></div>';
    html += '<div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">';
    html += '<table style="width:100%;border-collapse:collapse;font-size:13px;" id="tb-table">';
    html += '<thead><tr style="background:var(--gray-50);">';
    html += '<th style="padding:10px 14px;text-align:left;font-weight:600;border-bottom:2px solid var(--gray-200);">Account Code</th>';
    html += '<th style="padding:10px 14px;text-align:left;font-weight:600;border-bottom:2px solid var(--gray-200);">Account Name</th>';
    html += '<th style="padding:10px 14px;text-align:left;font-weight:600;border-bottom:2px solid var(--gray-200);">Type</th>';
    html += '<th style="padding:10px 14px;text-align:right;font-weight:600;border-bottom:2px solid var(--gray-200);">Debit</th>';
    html += '<th style="padding:10px 14px;text-align:right;font-weight:600;border-bottom:2px solid var(--gray-200);">Credit</th>';
    html += '</tr></thead><tbody>';

    var typeOrder = ['asset','liability','equity','income','expense'];
    typeOrder.forEach(function(type){
      if(!grouped[type]) return;
      html += '<tr style="background:'+typeColors[type]+'11;"><td colspan="5" style="padding:8px 14px;font-weight:700;font-size:12px;color:'+typeColors[type]+';text-transform:uppercase;border-bottom:1px solid var(--gray-200);">'+(typeLabels[type]||type)+'</td></tr>';
      grouped[type].forEach(function(a){
        html += '<tr style="border-bottom:1px solid var(--gray-100);">';
        html += '<td style="padding:8px 14px;font-family:monospace;font-size:12px;color:var(--gray-600);">'+tbEsc(a.account_code)+'</td>';
        html += '<td style="padding:8px 14px;">'+tbEsc(a.account_name)+'</td>';
        html += '<td style="padding:8px 14px;font-size:11px;color:var(--gray-500);">'+tbEsc(typeLabels[a.account_type]||a.account_type)+'</td>';
        html += '<td style="padding:8px 14px;text-align:right;font-weight:500;'+(a.debit_balance>0?'color:var(--gray-900);':'color:var(--gray-300);')+'">'+(a.debit_balance>0?parseFloat(a.debit_balance).toLocaleString():'—')+'</td>';
        html += '<td style="padding:8px 14px;text-align:right;font-weight:500;'+(a.credit_balance>0?'color:var(--gray-900);':'color:var(--gray-300);')+'">'+(a.credit_balance>0?parseFloat(a.credit_balance).toLocaleString():'—')+'</td>';
        html += '</tr>';
      });
    });

    // Totals row
    var isBalanced = Math.abs(d.total_debit - d.total_credit) < 0.01;
    html += '<tr style="background:var(--gray-50);border-top:2px solid var(--gray-300);">';
    html += '<td colspan="3" style="padding:12px 14px;font-weight:700;font-size:14px;">TOTAL</td>';
    html += '<td style="padding:12px 14px;text-align:right;font-weight:700;font-size:14px;">'+parseFloat(d.total_debit).toLocaleString()+'</td>';
    html += '<td style="padding:12px 14px;text-align:right;font-weight:700;font-size:14px;">'+parseFloat(d.total_credit).toLocaleString()+'</td>';
    html += '</tr></tbody></table></div>';

    // Balance check
    if(isBalanced){
      html += '<div style="margin-top:12px;padding:12px;border-radius:8px;display:flex;align-items:center;gap:10px;font-size:13px;background:#f0fdf4;border:1px solid #bbf7d0;color:#065f46;">';
      html += '<i class="fas fa-check-circle"></i><strong>Balanced</strong> — Total Debits equal Total Credits.</div>';
    } else {
      html += '<div style="margin-top:12px;padding:12px;border-radius:8px;display:flex;align-items:center;gap:10px;font-size:13px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;">';
      html += '<i class="fas fa-exclamation-triangle"></i><strong>Out of Balance</strong> — Difference: '+(d.total_debit-d.total_credit).toLocaleString()+' RWF. Check journal entries.</div>';
    }

    el.innerHTML = html;
  }catch(e){ el.innerHTML='<div style="padding:20px;color:#dc2626;text-align:center;">Error loading trial balance: '+e.message+'</div>'; }
};

window.tbPrint = function(){
  var content = document.getElementById('tb-table');
  if(!content){ alert('Generate the trial balance first.'); return; }
  var asOf = document.getElementById('tb-date').value;
  var win = window.open('','','width=900,height=700');
  win.document.write('<html><head><title>Trial Balance - '+asOf+'<\/title><style>body{font-family:Inter,Arial,sans-serif;padding:20px;}table{width:100%;border-collapse:collapse;font-size:12px;}th,td{padding:8px 10px;border:1px solid #e2e8f0;text-align:left;}th{background:#f8fafc;font-weight:600;}h2{text-align:center;margin-bottom:4px;}.sub{text-align:center;color:#64748b;font-size:13px;margin-bottom:16px;}@media print{button{display:none;}}<\/style><\/head><body><h2>Trial Balance<\/h2><div class="sub">As of '+asOf+'<\/div>'+content.outerHTML+'<\/body><\/html>');
  win.document.close();
  setTimeout(function(){ win.print(); }, 500);
};

tbLoad();
})();
</script>

<?php elseif ($subModule == 'cash-flow'): ?>
<!-- ====== CASH FLOW ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-money-bill-wave" style="color:#16a34a;"></i> Cash Flow Statement</h3>
    <div style="display:flex;gap:8px;align-items:center;"><input type="date" id="cf-from" value="<?php echo date('Y-m-01');?>" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><input type="date" id="cf-to" value="<?php echo date('Y-m-d');?>" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;"><button onclick="cfLoad()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;">Generate</button></div>
  </div>
  <div id="cf-content"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
function fEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function cfLoad(){
  try{
    const r=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=cash_flow&from='+document.getElementById('cf-from').value+'&to='+document.getElementById('cf-to').value);
    const j=await r.json();
    if(j.success&&j.data){
      const d=j.data;
      const inflows=parseFloat(d.inflows||0), outflows=parseFloat(d.outflows||0), net=parseFloat(d.net_flow||0);
      
      let html=`<div style="text-align:center;margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
        <h4 style="font-size:16px;font-weight:700;color:var(--gray-900);">CASH FLOW STATEMENT</h4>
        <p style="font-size:12px;color:var(--gray-500);">For the period ${document.getElementById('cf-from').value} to ${document.getElementById('cf-to').value}</p>
      </div>`;

      // Summary Cards
      html+=`<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;">
        <div style="background:#d1fae5;border-radius:8px;padding:16px;text-align:center;"><div style="font-size:11px;font-weight:600;color:#065f46;">TOTAL CASH INFLOWS</div><div style="font-size:22px;font-weight:700;color:#065f46;margin-top:4px;">${inflows.toLocaleString()}</div></div>
        <div style="background:#fee2e2;border-radius:8px;padding:16px;text-align:center;"><div style="font-size:11px;font-weight:600;color:#991b1b;">TOTAL CASH OUTFLOWS</div><div style="font-size:22px;font-weight:700;color:#991b1b;margin-top:4px;">${outflows.toLocaleString()}</div></div>
        <div style="background:${net>=0?'#dbeafe':'#fef3c7'};border-radius:8px;padding:16px;text-align:center;"><div style="font-size:11px;font-weight:600;color:${net>=0?'#1e40af':'#92400e'};">NET CASH FLOW</div><div style="font-size:22px;font-weight:700;color:${net>=0?'#1e40af':'#92400e'};margin-top:4px;">${net.toLocaleString()}</div></div>
      </div>`;

      // Operating Activities
      html+=`<div style="margin-bottom:20px;">
        <h4 style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:8px;padding:8px 12px;background:var(--gray-50);border-radius:6px;">A. CASH FLOWS FROM OPERATING ACTIVITIES</h4>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">`;
      if(d.details && d.details.length){
        d.details.forEach(item=>{
          const itemNet = parseFloat(item.net||0);
          html+=`<tr style="border-bottom:1px solid var(--gray-50);">
            <td style="padding:6px 12px;padding-left:20px;">${fEsc(item.category||'Other')}</td>
            <td style="padding:6px 12px;text-align:right;color:#16a34a;">${parseFloat(item.inflow||0)>0?parseFloat(item.inflow).toLocaleString():''}</td>
            <td style="padding:6px 12px;text-align:right;color:#dc2626;">${parseFloat(item.outflow||0)>0?'('+parseFloat(item.outflow).toLocaleString()+')':''}</td>
            <td style="padding:6px 12px;text-align:right;font-weight:600;color:${itemNet>=0?'#065f46':'#991b1b'};">${itemNet.toLocaleString()}</td>
          </tr>`;
        });
      } else {
        html+=`<tr><td style="padding:6px 12px;padding-left:20px;">Cash received from operations</td><td style="padding:6px 12px;text-align:right;color:#16a34a;">${inflows.toLocaleString()}</td><td></td><td style="padding:6px 12px;text-align:right;font-weight:600;">${inflows.toLocaleString()}</td></tr>`;
        html+=`<tr><td style="padding:6px 12px;padding-left:20px;">Cash paid for operations</td><td></td><td style="padding:6px 12px;text-align:right;color:#dc2626;">(${outflows.toLocaleString()})</td><td style="padding:6px 12px;text-align:right;font-weight:600;color:#991b1b;">(${outflows.toLocaleString()})</td></tr>`;
      }
      html+=`<tr style="border-top:2px solid var(--gray-200);"><td style="padding:10px 12px;font-weight:700;">Net Cash from Operating Activities</td><td></td><td></td><td style="padding:10px 12px;text-align:right;font-weight:700;color:${net>=0?'#065f46':'#991b1b'};">${net.toLocaleString()}</td></tr>`;
      html+=`</table></div>`;

      // Investing Activities (placeholder)
      html+=`<div style="margin-bottom:20px;">
        <h4 style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:8px;padding:8px 12px;background:var(--gray-50);border-radius:6px;">B. CASH FLOWS FROM INVESTING ACTIVITIES</h4>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <tr><td style="padding:6px 12px;padding-left:20px;color:var(--gray-400);">Purchase of fixed assets</td><td></td><td></td><td style="padding:6px 12px;text-align:right;color:var(--gray-400);">0</td></tr>
          <tr><td style="padding:6px 12px;padding-left:20px;color:var(--gray-400);">Sale of fixed assets</td><td></td><td></td><td style="padding:6px 12px;text-align:right;color:var(--gray-400);">0</td></tr>
          <tr style="border-top:2px solid var(--gray-200);"><td style="padding:10px 12px;font-weight:700;">Net Cash from Investing Activities</td><td></td><td></td><td style="padding:10px 12px;text-align:right;font-weight:700;">0</td></tr>
        </table></div>`;

      // Financing Activities (placeholder)
      html+=`<div style="margin-bottom:20px;">
        <h4 style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:8px;padding:8px 12px;background:var(--gray-50);border-radius:6px;">C. CASH FLOWS FROM FINANCING ACTIVITIES</h4>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <tr><td style="padding:6px 12px;padding-left:20px;color:var(--gray-400);">Loan proceeds</td><td></td><td></td><td style="padding:6px 12px;text-align:right;color:var(--gray-400);">0</td></tr>
          <tr><td style="padding:6px 12px;padding-left:20px;color:var(--gray-400);">Loan repayments</td><td></td><td></td><td style="padding:6px 12px;text-align:right;color:var(--gray-400);">0</td></tr>
          <tr style="border-top:2px solid var(--gray-200);"><td style="padding:10px 12px;font-weight:700;">Net Cash from Financing Activities</td><td></td><td></td><td style="padding:10px 12px;text-align:right;font-weight:700;">0</td></tr>
        </table></div>`;

      // Net Change
      html+=`<div style="background:${net>=0?'#d1fae5':'#fee2e2'};border-radius:8px;padding:16px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:15px;font-weight:700;">NET INCREASE (DECREASE) IN CASH</span>
        <span style="font-size:20px;font-weight:700;color:${net>=0?'#065f46':'#991b1b'};">${net.toLocaleString()} RWF</span>
      </div>`;

      document.getElementById('cf-content').innerHTML=html;
    } else document.getElementById('cf-content').innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No cash flow data for this period.</div>';
  } catch(e){ document.getElementById('cf-content').innerHTML='<p style="color:red;">Error</p>'; }
}
cfLoad();
</script>
<?php elseif ($subModule == 'bank-reconciliation-legacy'): ?>
<!-- ====== BANK RECONCILIATION ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-check-double" style="color:var(--primary-color);"></i> Bank Reconciliation</h3>
  </div>

  <!-- Step 1: Select Bank & Upload -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
    <div>
      <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Select Bank Account</label>
      <select id="br-account" onchange="brLoadCashbook()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="">Choose bank...</option>
      </select>
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Upload Bank Statement (CSV)</label>
      <div style="display:flex;gap:8px;">
        <input type="file" id="br-file" accept=".csv" onchange="brParseCSV()" style="flex:1;padding:7px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <button onclick="brDownloadSample()" style="padding:7px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:11px;white-space:nowrap;" title="Download sample CSV"><i class="fas fa-download"></i> Sample</button>
      </div>
    </div>
  </div>
  <p style="font-size:11px;color:var(--gray-400);margin-bottom:16px;">CSV format: Date, Description, Reference, Debit, Credit, Balance (headers in first row)</p>

  <!-- Results -->
  <div id="br-results" style="display:none;">
    <!-- Summary -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:20px;">
      <div style="background:#d1fae5;border-radius:8px;padding:14px;text-align:center;">
        <div style="font-size:11px;font-weight:600;color:#065f46;">MATCHED</div>
        <div style="font-size:20px;font-weight:700;color:#065f46;" id="br-matched">0</div>
      </div>
      <div style="background:#fef3c7;border-radius:8px;padding:14px;text-align:center;">
        <div style="font-size:11px;font-weight:600;color:#92400e;">UNPRESENTED CHEQUES</div>
        <div style="font-size:20px;font-weight:700;color:#92400e;" id="br-unpresented">0</div>
      </div>
      <div style="background:#dbeafe;border-radius:8px;padding:14px;text-align:center;">
        <div style="font-size:11px;font-weight:600;color:#1e40af;">UNCREDITED DEPOSITS</div>
        <div style="font-size:20px;font-weight:700;color:#1e40af;" id="br-uncredited">0</div>
      </div>
    </div>

    <!-- Matched Items -->
    <div style="margin-bottom:20px;">
      <h4 style="font-size:13px;font-weight:700;color:#065f46;margin-bottom:8px;padding:6px 12px;background:#f0fdf4;border-radius:4px;"><i class="fas fa-check-circle"></i> Matched Transactions</h4>
      <div id="br-matched-list" style="max-height:200px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:6px;"></div>
    </div>

    <!-- Unpresented (in cashbook but NOT in bank statement) -->
    <div style="margin-bottom:20px;">
      <h4 style="font-size:13px;font-weight:700;color:#92400e;margin-bottom:8px;padding:6px 12px;background:#fffbeb;border-radius:4px;"><i class="fas fa-exclamation-triangle"></i> Unpresented Cheques (In Cashbook, Not in Bank)</h4>
      <div id="br-unpresented-list" style="max-height:200px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:6px;"></div>
    </div>

    <!-- Uncredited (in bank statement but NOT in cashbook) -->
    <div style="margin-bottom:20px;">
      <h4 style="font-size:13px;font-weight:700;color:#1e40af;margin-bottom:8px;padding:6px 12px;background:#eff6ff;border-radius:4px;"><i class="fas fa-info-circle"></i> Uncredited Deposits (In Bank, Not in Cashbook)</h4>
      <div id="br-uncredited-list" style="max-height:200px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:6px;"></div>
    </div>

    <!-- Reconciliation Summary -->
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;background:var(--gray-50);">
      <h4 style="font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:10px;">Reconciliation Summary</h4>
      <table style="width:100%;font-size:13px;">
        <tr><td style="padding:4px 0;">Balance per Bank Statement</td><td style="text-align:right;font-weight:600;" id="br-bank-bal">0</td></tr>
        <tr><td style="padding:4px 0;color:#92400e;">Less: Unpresented Cheques</td><td style="text-align:right;font-weight:600;color:#92400e;" id="br-unpres-amt">0</td></tr>
        <tr><td style="padding:4px 0;color:#1e40af;">Add: Uncredited Deposits</td><td style="text-align:right;font-weight:600;color:#1e40af;" id="br-uncred-amt">0</td></tr>
        <tr style="border-top:2px solid var(--gray-300);"><td style="padding:8px 0;font-weight:700;">Adjusted Bank Balance</td><td style="text-align:right;font-weight:700;font-size:14px;" id="br-adjusted">0</td></tr>
        <tr><td style="padding:4px 0;">Balance per Cashbook</td><td style="text-align:right;font-weight:600;" id="br-cash-bal">0</td></tr>
        <tr style="border-top:2px solid var(--gray-300);"><td style="padding:8px 0;font-weight:700;">Difference</td><td style="text-align:right;font-weight:700;font-size:14px;" id="br-diff">0</td></tr>
      </table>
    </div>
  </div>
</div>

<script>
function fEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
let brCashbook = [];
let brBankLines = [];

async function brInit(){
  const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=cash_balances');
  const j = await r.json();
  if(j.success && j.data){
    const sel = document.getElementById('br-account');
    (j.data.accounts||[]).forEach(a => { sel.innerHTML += `<option value="${a.id}" data-balance="${a.balance}">${fEsc(a.account_code)} - ${fEsc(a.account_name)}</option>`; });
  }
}

async function brLoadCashbook(){
  const accountId = document.getElementById('br-account').value;
  if(!accountId) return;
  const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=ledger&account_id='+accountId+'&from=2020-01-01&to='+new Date().toISOString().split('T')[0]);
  const j = await r.json();
  brCashbook = (j.success && j.data) ? j.data : [];
  if(brBankLines.length) brReconcile();
}

function brParseCSV(){
  const file = document.getElementById('br-file').files[0];
  if(!file) return;
  const reader = new FileReader();
  reader.onload = function(e){
    const lines = e.target.result.split('\n').filter(l=>l.trim());
    brBankLines = [];
    // Skip header row
    for(let i=1; i<lines.length; i++){
      const cols = lines[i].split(',').map(c=>c.trim().replace(/^"|"$/g,''));
      if(cols.length >= 5){
        brBankLines.push({
          date: cols[0],
          description: cols[1],
          reference: cols[2],
          debit: parseFloat(cols[3])||0,
          credit: parseFloat(cols[4])||0,
          balance: parseFloat(cols[5])||0
        });
      }
    }
    if(brCashbook.length) brReconcile();
    else if(document.getElementById('br-account').value) brLoadCashbook();
  };
  reader.readAsText(file);
}

function brReconcile(){
  document.getElementById('br-results').style.display = 'block';
  
  const matched = [];
  const unpresented = []; // in cashbook, not in bank
  const uncredited = []; // in bank, not in cashbook
  
  // Mark matches - compare by reference or amount+date
  const bankUsed = new Set();
  const cashUsed = new Set();
  
  brCashbook.forEach((cb, ci) => {
    const cbAmt = parseFloat(cb.debit||0) - parseFloat(cb.credit||0);
    for(let bi=0; bi<brBankLines.length; bi++){
      if(bankUsed.has(bi)) continue;
      const bk = brBankLines[bi];
      const bkAmt = parseFloat(bk.debit||0) - parseFloat(bk.credit||0);
      // Match by reference or by amount
      if((cb.reference && bk.reference && cb.reference.trim()===bk.reference.trim()) || (Math.abs(cbAmt - bkAmt) < 0.01 && cb.entry_date === bk.date)){
        matched.push({cashbook: cb, bank: bk});
        bankUsed.add(bi);
        cashUsed.add(ci);
        break;
      }
    }
  });
  
  // Unpresented: in cashbook but not matched
  brCashbook.forEach((cb, ci) => { if(!cashUsed.has(ci) && (parseFloat(cb.credit||0)>0)) unpresented.push(cb); });
  
  // Uncredited: in bank but not matched
  brBankLines.forEach((bk, bi) => { if(!bankUsed.has(bi) && (parseFloat(bk.debit||0)>0)) uncredited.push(bk); });
  
  // Update counts
  document.getElementById('br-matched').textContent = matched.length;
  document.getElementById('br-unpresented').textContent = unpresented.length;
  document.getElementById('br-uncredited').textContent = uncredited.length;
  
  // Render matched
  document.getElementById('br-matched-list').innerHTML = matched.length ? '<table style="width:100%;font-size:12px;border-collapse:collapse;">'+matched.map(m=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:5px 8px;">${m.cashbook.entry_date||''}</td><td style="padding:5px 8px;">${fEsc(m.cashbook.reference||m.bank.reference||'')}</td><td style="padding:5px 8px;">${fEsc(m.bank.description||m.cashbook.line_description||'')}</td><td style="padding:5px 8px;text-align:right;">${(parseFloat(m.cashbook.debit||0)-parseFloat(m.cashbook.credit||0)).toLocaleString()}</td><td style="padding:5px 8px;color:#065f46;"><i class="fas fa-check"></i></td></tr>`).join('')+'</table>' : '<p style="padding:12px;color:var(--gray-400);text-align:center;font-size:12px;">No matched items.</p>';
  
  // Render unpresented
  document.getElementById('br-unpresented-list').innerHTML = unpresented.length ? '<table style="width:100%;font-size:12px;border-collapse:collapse;">'+unpresented.map(u=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:5px 8px;">${u.entry_date||''}</td><td style="padding:5px 8px;">${fEsc(u.reference||'')}</td><td style="padding:5px 8px;">${fEsc(u.line_description||u.description||'')}</td><td style="padding:5px 8px;text-align:right;color:#92400e;">${parseFloat(u.credit||0).toLocaleString()}</td></tr>`).join('')+'</table>' : '<p style="padding:12px;color:var(--gray-400);text-align:center;font-size:12px;">None — all cheques presented.</p>';
  
  // Render uncredited
  document.getElementById('br-uncredited-list').innerHTML = uncredited.length ? '<table style="width:100%;font-size:12px;border-collapse:collapse;">'+uncredited.map(u=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:5px 8px;">${u.date||''}</td><td style="padding:5px 8px;">${fEsc(u.reference||'')}</td><td style="padding:5px 8px;">${fEsc(u.description||'')}</td><td style="padding:5px 8px;text-align:right;color:#1e40af;">${parseFloat(u.debit||0).toLocaleString()}</td></tr>`).join('')+'</table>' : '<p style="padding:12px;color:var(--gray-400);text-align:center;font-size:12px;">None — all deposits credited.</p>';
  
  // Summary
  const bankBal = brBankLines.length ? parseFloat(brBankLines[brBankLines.length-1].balance||0) : 0;
  const unpresAmt = unpresented.reduce((s,u)=>s+parseFloat(u.credit||0),0);
  const uncredAmt = uncredited.reduce((s,u)=>s+parseFloat(u.debit||0),0);
  const adjusted = bankBal - unpresAmt + uncredAmt;
  const sel = document.getElementById('br-account');
  const cashBal = parseFloat(sel.selectedOptions[0]?.getAttribute('data-balance')||0);
  
  document.getElementById('br-bank-bal').textContent = bankBal.toLocaleString();
  document.getElementById('br-unpres-amt').textContent = '('+unpresAmt.toLocaleString()+')';
  document.getElementById('br-uncred-amt').textContent = uncredAmt.toLocaleString();
  document.getElementById('br-adjusted').textContent = adjusted.toLocaleString();
  document.getElementById('br-cash-bal').textContent = cashBal.toLocaleString();
  document.getElementById('br-diff').textContent = (adjusted - cashBal).toLocaleString();
  document.getElementById('br-diff').style.color = Math.abs(adjusted-cashBal)<0.01 ? '#065f46' : '#991b1b';
}

function brDownloadSample(){
  const csv = `Date,Description,Reference,Debit,Credit,Balance
2026-07-01,Opening Balance,,,0,500000
2026-07-02,Payment from Patient - Consultation,JNL-20260708-0004,10000,0,510000
2026-07-03,Supplier Payment - Medical Supplies,CHQ-001,0,25000,485000
2026-07-04,Bank Charges,BNK-FEE,0,1500,483500
2026-07-05,Payment from Patient - Lab Tests,JNL-20260708-0003,5500,0,489000
2026-07-06,Staff Salary Transfer,SAL-JULY,0,150000,339000
2026-07-07,Insurance Payment Received,INS-PAY-001,35000,0,374000
2026-07-08,Pharmacy Drug Purchase,PO-2026-001,0,45000,329000
2026-07-08,Mobile Money Transfer In,MOMO-IN-01,10000,0,339000
2026-07-09,Utility Payment - Electricity,UTIL-ELEC,0,8000,331000`;
  const blob = new Blob([csv], {type:'text/csv'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'bank_statement_sample.csv';
  a.click();
  URL.revokeObjectURL(url);
}

brInit();
</script>

<?php elseif ($subModule == 'cash-equivalent'): ?>
<!-- ====== CASH & BANK BALANCES ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-university" style="color:var(--primary-color);"></i> Cash & Cash Equivalents</h3>
    <button onclick="ceLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <!-- Summary Cards -->
  <div id="ce-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;"></div>
  <!-- Detail Table -->
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:10px 12px;text-align:left;font-weight:600;">Account Code</th>
        <th style="padding:10px 12px;text-align:left;font-weight:600;">Account Name</th>
        <th style="padding:10px 12px;text-align:right;font-weight:600;">Balance</th>
      </tr></thead>
      <tbody id="ce-tbody"><tr><td colspan="3" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
      <tfoot id="ce-tfoot"></tfoot>
    </table>
  </div>
</div>
<script>
function fEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
async function ceLoad(){
  try{
    const r = await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=finance/chart-of-accounts&action=cash_balances');
    const j = await r.json();
    if(j.success && j.data){
      const accounts = j.data.accounts || [];
      const total = j.data.total || 0;
      
      // Cards
      const cards = document.getElementById('ce-cards');
      cards.innerHTML = accounts.map(a => {
        const bal = parseFloat(a.balance||0);
        return `<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:16px;">
          <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;margin-bottom:4px;">${fEsc(a.account_name)}</div>
          <div style="font-size:22px;font-weight:700;color:${bal>=0?'#065f46':'#991b1b'};">${bal.toLocaleString()} <span style="font-size:12px;font-weight:400;">RWF</span></div>
          <div style="font-size:11px;color:var(--gray-400);margin-top:4px;">${fEsc(a.account_code)}</div>
        </div>`;
      }).join('') + `<div style="background:#d1fae5;border:2px solid #16a34a;border-radius:8px;padding:16px;">
        <div style="font-size:11px;font-weight:600;color:#065f46;text-transform:uppercase;margin-bottom:4px;">TOTAL CASH & EQUIVALENTS</div>
        <div style="font-size:22px;font-weight:700;color:#065f46;">${parseFloat(total).toLocaleString()} <span style="font-size:12px;font-weight:400;">RWF</span></div>
      </div>`;
      
      // Table
      const tbody = document.getElementById('ce-tbody');
      tbody.innerHTML = accounts.map(a => {
        const bal = parseFloat(a.balance||0);
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:10px 12px;font-family:monospace;color:var(--primary-color);">${fEsc(a.account_code)}</td>
          <td style="padding:10px 12px;font-weight:500;">${fEsc(a.account_name)}</td>
          <td style="padding:10px 12px;text-align:right;font-weight:600;font-size:14px;color:${bal>=0?'#065f46':'#991b1b'};">${bal.toLocaleString()}</td>
        </tr>`;
      }).join('');
      
      document.getElementById('ce-tfoot').innerHTML = `<tr style="background:var(--gray-50);border-top:2px solid var(--gray-300);">
        <td colspan="2" style="padding:12px;font-weight:700;font-size:14px;">Total Cash & Cash Equivalents</td>
        <td style="padding:12px;text-align:right;font-weight:700;font-size:16px;color:#065f46;">${parseFloat(total).toLocaleString()} RWF</td>
      </tr>`;
    }
  } catch(e){ document.getElementById('ce-tbody').innerHTML='<tr><td colspan="3" style="padding:20px;color:red;">Error</td></tr>'; }
}
ceLoad();
</script>

<?php elseif ($subModule == 'payroll-finance'): ?>

<!-- Payroll Finance: Record approved payrolls as journal entries -->

<!-- Filter toolbar -->
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <label style="font-size:13px;font-weight:500;color:var(--gray-700);">Pay Period:</label>
  <input id="pf-period" type="month" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <select id="pf-status-filter" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="approved">Approved (Pending Recording)</option>
    <option value="paid">Recorded</option>
    <option value="">All</option>
  </select>
  <button onclick="pfLoad()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
    <i class="fas fa-search"></i> Load
  </button>
</div>

<!-- Summary list of payroll batches -->
<div id="pf-batches-wrap">
  <div style="padding:60px;text-align:center;color:var(--gray-400);">
    <i class="fas fa-users" style="font-size:36px;display:block;margin-bottom:8px;"></i>
    Select a period and click Load to see approved payrolls.
  </div>
</div>

<!-- Journal Entry Modal -->
<div id="pf-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:780px;margin:auto;position:relative;">
    <button onclick="pfCloseModal()" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:17px;font-weight:700;margin-bottom:4px;color:#7c3aed;"><i class="fas fa-book" style="margin-right:8px;"></i>Record Payroll Journal Entry</h3>
    <p id="pf-modal-desc" style="font-size:13px;color:var(--gray-500);margin-bottom:18px;"></p>

    <div id="pf-modal-alert" style="display:none;margin-bottom:14px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>

    <!-- Entry date + reference -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:18px;">
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Entry Date</label>
        <input id="pf-entry-date" type="date" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Reference</label>
        <input id="pf-reference" type="text" placeholder="e.g. PAY-2026-08" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
      </div>
      <div>
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Description</label>
        <input id="pf-description" type="text" placeholder="e.g. August 2026 Payroll" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
      </div>
    </div>

    <!-- Totals display -->
    <div id="pf-totals-bar" style="background:#f8fafc;border-radius:8px;padding:12px 16px;margin-bottom:18px;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;font-size:12px;"></div>

    <!-- Journal lines table -->
    <div style="font-size:12px;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Journal Lines — Select Account for Each Line</div>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;" id="pf-lines-table">
        <thead>
          <tr style="background:#f1f5f9;">
            <th style="padding:9px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Side</th>
            <th style="padding:9px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Description</th>
            <th style="padding:9px 10px;text-align:left;font-weight:600;color:var(--gray-600);">Account</th>
            <th style="padding:9px 10px;text-align:right;font-weight:600;color:#15803d;">Debit</th>
            <th style="padding:9px 10px;text-align:right;font-weight:600;color:#dc2626;">Credit</th>
          </tr>
        </thead>
        <tbody id="pf-lines-body"></tbody>
        <tfoot>
          <tr style="background:#f8fafc;font-weight:700;">
            <td colspan="3" style="padding:9px 10px;text-align:right;color:var(--gray-700);">TOTALS</td>
            <td style="padding:9px 10px;text-align:right;color:#15803d;" id="pf-total-debit">0</td>
            <td style="padding:9px 10px;text-align:right;color:#dc2626;" id="pf-total-credit">0</td>
          </tr>
          <tr id="pf-balance-row">
            <td colspan="5" style="padding:6px 10px;text-align:center;font-size:12px;font-weight:600;" id="pf-balance-msg"></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div style="display:flex;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid var(--gray-200);">
      <button onclick="pfSubmit()" id="pf-submit-btn" style="flex:1;padding:11px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:700;cursor:pointer;">
        <i class="fas fa-book"></i> Post Journal Entry
      </button>
      <button onclick="pfCloseModal()" style="padding:11px 22px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
    </div>
  </div>
</div>

<script>
const pfPayrollAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+?endpoint=hr/payroll';
const pfCoaAPI     = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+?endpoint=finance/chart-of-accounts';
let pfCoaOptions   = '';   // cached <option> html
let pfCurrentPeriod= '';
let pfCurrentLines = [];   // journal lines data

function pfFmt(n){return Number(n||0).toLocaleString('en-US',{minimumFractionDigits:0,maximumFractionDigits:0});}
function pfEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// Set default period
(function(){
  const d=new Date();
  document.getElementById('pf-period').value=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0');
})();

// Load COA once
async function pfLoadCoa(){
  if(pfCoaOptions) return;
  try{
    const r=await fetch(pfCoaAPI+'&status=active&limit=500');
    const j=await r.json();
    if(!j.success) return;
    const accounts=(j.data.accounts||j.data||[]);
    pfCoaOptions=accounts
      .filter(a=>a.account_code && a.account_name)
      .map(a=>`<option value="${a.id}">[${pfEsc(a.account_code)}] ${pfEsc(a.account_name)} (${a.account_type})</option>`)
      .join('');
  }catch(e){}
}

async function pfLoad(){
  const period=document.getElementById('pf-period').value;
  const status=document.getElementById('pf-status-filter').value;
  const wrap=document.getElementById('pf-batches-wrap');
  wrap.innerHTML='<div style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';
  await pfLoadCoa();
  try{
    const p=new URLSearchParams({period,status,limit:100});
    const r=await fetch(pfPayrollAPI+'&'+p);
    const j=await r.json();
    if(!j.success){wrap.innerHTML='<div style="padding:40px;text-align:center;color:var(--gray-400);">Failed: '+pfEsc(j.message)+'</div>';return;}
    const {records,summary}=j.data;
    if(!records.length){
      wrap.innerHTML='<div style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:36px;display:block;margin-bottom:8px;color:#7c3aed;"></i>No '+(status||'')+ ' payroll records for this period.</div>';
      return;
    }
    // Aggregate totals for the batch
    const tot={
      gross    : records.reduce((s,r)=>s+parseFloat(r.gross_salary||0),0),
      emp_rssb : records.reduce((s,r)=>s+parseFloat(r.employee_rssb||0),0),
      paye     : records.reduce((s,r)=>s+parseFloat(r.tpr_tax||0),0),
      net_pay  : records.reduce((s,r)=>s+parseFloat(r.net_pay||0),0),
      er_pension:records.reduce((s,r)=>s+parseFloat(r.employer_rssb_pension||0),0),
      er_ohi   : records.reduce((s,r)=>s+parseFloat(r.employer_ohi||0),0),
      er_cost  : records.reduce((s,r)=>s+parseFloat(r.employer_cost||0),0),
    };
    const statusColor={approved:'#7c3aed',paid:'var(--success-color)'};
    let html=`
      <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:16px;">
        <!-- Batch header -->
        <div style="padding:14px 18px;background:#f5f3ff;border-bottom:1px solid #e9d5ff;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
          <div>
            <span style="font-size:16px;font-weight:700;color:#7c3aed;">Payroll Period: ${pfEsc(period)}</span>
            <span style="margin-left:12px;font-size:12px;color:var(--gray-500);">${records.length} employee${records.length!==1?'s':''}</span>
          </div>
          ${status==='approved'?`
          <button onclick="pfOpenModal('${pfEsc(period)}',${JSON.stringify(tot).replace(/"/g,'&quot;')})"
            style="padding:9px 20px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
            <i class="fas fa-book"></i> Record Journal Entry
          </button>`:`<span style="padding:4px 12px;border-radius:12px;font-size:12px;font-weight:700;background:#dcfce7;color:#15803d;"><i class="fas fa-check"></i> Recorded</span>`}
        </div>
        <!-- Totals summary -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0;border-bottom:1px solid var(--gray-100);">
          ${pfSumCard('Gross Salary',tot.gross,'#15803d')}
          ${pfSumCard('Emp RSSB (6%)',tot.emp_rssb,'#dc2626')}
          ${pfSumCard('PAYE/TPR',tot.paye,'#dc2626')}
          ${pfSumCard('Net Salary',tot.net_pay,'var(--success-color)')}
          ${pfSumCard('Employer Pension (6%)',tot.er_pension,'#7c3aed')}
          ${pfSumCard('Employer OHI (2%)',tot.er_ohi,'#7c3aed')}
          ${pfSumCard('Total Employer Cost',tot.er_cost,'#7c3aed')}
        </div>
        <!-- Employee list -->
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="background:var(--gray-50);">
              <th style="padding:8px 12px;text-align:left;color:var(--gray-600);">Employee</th>
              <th style="padding:8px 12px;text-align:right;color:#15803d;">Gross</th>
              <th style="padding:8px 12px;text-align:right;color:#dc2626;">Emp RSSB</th>
              <th style="padding:8px 12px;text-align:right;color:#dc2626;">PAYE</th>
              <th style="padding:8px 12px;text-align:right;color:var(--success-color);">Net Pay</th>
              <th style="padding:8px 12px;text-align:right;color:#7c3aed;">Er Pension</th>
              <th style="padding:8px 12px;text-align:right;color:#7c3aed;">Er OHI</th>
              <th style="padding:8px 12px;text-align:center;">Status</th>
            </tr></thead>
            <tbody>`;
    records.forEach(rec=>{
      const sc={approved:'#7c3aed',paid:'var(--success-color)',draft:'#f59e0b',processed:'var(--primary-color)'};
      html+=`<tr style="border-top:1px solid var(--gray-100);">
        <td style="padding:8px 12px;"><strong>${pfEsc(rec.first_name)} ${pfEsc(rec.last_name)}</strong><br>
          <span style="font-size:11px;color:var(--gray-500);">${pfEsc(rec.employee_no)}</span></td>
        <td style="padding:8px 12px;text-align:right;">${pfFmt(rec.gross_salary)}</td>
        <td style="padding:8px 12px;text-align:right;color:#dc2626;">${pfFmt(rec.employee_rssb)}</td>
        <td style="padding:8px 12px;text-align:right;color:#dc2626;">${pfFmt(rec.tpr_tax)}</td>
        <td style="padding:8px 12px;text-align:right;font-weight:700;color:var(--success-color);">${pfFmt(rec.net_pay)}</td>
        <td style="padding:8px 12px;text-align:right;color:#7c3aed;">${pfFmt(rec.employer_rssb_pension)}</td>
        <td style="padding:8px 12px;text-align:right;color:#7c3aed;">${pfFmt(rec.employer_ohi)}</td>
        <td style="padding:8px 12px;text-align:center;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:${sc[rec.status]||'#ccc'}22;color:${sc[rec.status]||'#555'};">${rec.status.toUpperCase()}</span></td>
      </tr>`;
    });
    html+='</tbody></table></div></div>';
    wrap.innerHTML=html;
  }catch(e){wrap.innerHTML='<div style="padding:40px;text-align:center;color:red;">Error: '+pfEsc(e.message)+'</div>';}
}

function pfSumCard(label,val,color){
  return `<div style="padding:12px 14px;border-right:1px solid var(--gray-100);">
    <div style="font-size:11px;color:var(--gray-500);">${label}</div>
    <div style="font-size:14px;font-weight:700;color:${color};">RWF ${pfFmt(val)}</div>
  </div>`;
}

function pfOpenModal(period, tot){
  pfCurrentPeriod=period;
  document.getElementById('pf-modal-alert').style.display='none';
  document.getElementById('pf-modal-desc').textContent='Period: '+period+' — totals are aggregated across all employees in this batch.';
  // Set default reference and description
  document.getElementById('pf-reference').value='PAY-'+period;
  document.getElementById('pf-description').value='Payroll Expense – '+period;
  document.getElementById('pf-entry-date').value=new Date().toISOString().slice(0,10);

  // Build totals bar
  const totalDebit=tot.gross+tot.er_pension+tot.er_ohi;
  const totalCredit=tot.net_pay+tot.emp_rssb+tot.paye+tot.er_pension+tot.er_ohi;
  document.getElementById('pf-totals-bar').innerHTML=`
    ${pfTotCard('Gross Salary',tot.gross,'#15803d')}
    ${pfTotCard('Emp RSSB',tot.emp_rssb,'#dc2626')}
    ${pfTotCard('PAYE/TPR',tot.paye,'#dc2626')}
    ${pfTotCard('Net Salary',tot.net_pay,'var(--success-color)')}
    ${pfTotCard('Er Pension',tot.er_pension,'#7c3aed')}
    ${pfTotCard('Er OHI',tot.er_ohi,'#7c3aed')}
    ${pfTotCard('Total Debit',totalDebit,'#1d4ed8',true)}
    ${pfTotCard('Total Credit',totalCredit,'#dc2626',true)}
  `;

  // Build journal lines — preset amounts, user selects accounts
  pfCurrentLines=[
    // DEBITS
    {side:'DR', description:'Basic Salary Expense',         amount:tot.gross,      debit:tot.gross,      credit:0,            account_id:''},
    {side:'DR', description:'Employer RSSB Pension Expense',amount:tot.er_pension, debit:tot.er_pension, credit:0,            account_id:''},
    {side:'DR', description:'Employer OHI Expense',         amount:tot.er_ohi,     debit:tot.er_ohi,     credit:0,            account_id:''},
    // CREDITS
    {side:'CR', description:'Salaries Payable (Net)',        amount:tot.net_pay,    debit:0, credit:tot.net_pay,    account_id:''},
    {side:'CR', description:'RSSB Employee Pension Payable', amount:tot.emp_rssb,   debit:0, credit:tot.emp_rssb,   account_id:''},
    {side:'CR', description:'PAYE Tax Payable (TPR)',        amount:tot.paye,       debit:0, credit:tot.paye,       account_id:''},
    {side:'CR', description:'RSSB Employer Pension Payable', amount:tot.er_pension, debit:0, credit:tot.er_pension, account_id:''},
    {side:'CR', description:'RSSB OHI Payable',             amount:tot.er_ohi,     debit:0, credit:tot.er_ohi,     account_id:''},
  ].filter(l=>l.amount>0);

  pfRenderLines();
  document.getElementById('pf-modal').style.display='flex';
}

function pfTotCard(label,val,color,bold=false){
  return `<div style="background:white;border-radius:6px;padding:8px 12px;border-left:3px solid ${color};">
    <div style="font-size:10px;color:var(--gray-500);">${label}</div>
    <div style="font-size:13px;font-weight:${bold?800:600};color:${color};">RWF ${pfFmt(val)}</div>
  </div>`;
}

function pfRenderLines(){
  const sel='<select onchange="pfLineAccountChange(this)" style="width:100%;padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;background:white;"><option value="">— Select Account —</option>'+pfCoaOptions+'</select>';
  let html='';
  pfCurrentLines.forEach((l,i)=>{
    const isDr=l.side==='DR';
    html+=`<tr style="border-bottom:1px solid var(--gray-100);background:${isDr?'#f0fdf4':'#fff5f5'};">
      <td style="padding:8px 10px;">
        <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;background:${isDr?'#dcfce7':'#fee2e2'};color:${isDr?'#15803d':'#dc2626'};">${l.side}</span>
      </td>
      <td style="padding:8px 10px;font-size:12px;color:var(--gray-700);">${pfEsc(l.description)}</td>
      <td style="padding:8px 6px;min-width:260px;" data-idx="${i}">${sel}</td>
      <td style="padding:8px 10px;text-align:right;font-weight:600;color:#15803d;">${l.debit>0?'RWF '+pfFmt(l.debit):''}</td>
      <td style="padding:8px 10px;text-align:right;font-weight:600;color:#dc2626;">${l.credit>0?'RWF '+pfFmt(l.credit):''}</td>
    </tr>`;
  });
  document.getElementById('pf-lines-body').innerHTML=html;
  pfUpdateTotals();
}

function pfLineAccountChange(sel){
  const idx=parseInt(sel.closest('td').dataset.idx);
  pfCurrentLines[idx].account_id=sel.value;
}

function pfUpdateTotals(){
  const totalDr=pfCurrentLines.reduce((s,l)=>s+l.debit,0);
  const totalCr=pfCurrentLines.reduce((s,l)=>s+l.credit,0);
  document.getElementById('pf-total-debit').textContent ='RWF '+pfFmt(totalDr);
  document.getElementById('pf-total-credit').textContent='RWF '+pfFmt(totalCr);
  const diff=Math.abs(totalDr-totalCr);
  const msg=document.getElementById('pf-balance-msg');
  const row=document.getElementById('pf-balance-row');
  if(diff<1){
    msg.innerHTML='<i class="fas fa-check-circle" style="color:var(--success-color);"></i> <span style="color:var(--success-color);">Entry is balanced — Total Debit = Total Credit = RWF '+pfFmt(totalDr)+'</span>';
    row.style.background='#f0fdf4';
  } else {
    msg.innerHTML='<i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> <span style="color:#dc2626;">Out of balance by RWF '+pfFmt(diff)+'</span>';
    row.style.background='#fff5f5';
  }
}

function pfCloseModal(){document.getElementById('pf-modal').style.display='none';}

async function pfSubmit(){
  const al=document.getElementById('pf-modal-alert');
  al.style.display='none';
  // Validate all accounts selected
  const missing=pfCurrentLines.filter(l=>!l.account_id);
  if(missing.length){al.textContent='Please select an account for every line ('+missing.length+' missing).';al.style.display='block';return;}
  // Validate balance
  const totalDr=pfCurrentLines.reduce((s,l)=>s+l.debit,0);
  const totalCr=pfCurrentLines.reduce((s,l)=>s+l.credit,0);
  if(Math.abs(totalDr-totalCr)>=1){al.textContent='Entry is not balanced. Debit ('+pfFmt(totalDr)+') ≠ Credit ('+pfFmt(totalCr)+').';al.style.display='block';return;}
  const btn=document.getElementById('pf-submit-btn');
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Posting…';
  const body={
    action        : 'record-journal',
    pay_period    : pfCurrentPeriod,
    entry_date    : document.getElementById('pf-entry-date').value,
    reference     : document.getElementById('pf-reference').value.trim(),
    description   : document.getElementById('pf-description').value.trim(),
    lines         : pfCurrentLines.map(l=>({account_id:l.account_id,description:l.description,debit:l.debit,credit:l.credit}))
  };
  try{
    const r=await fetch(pfPayrollAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){
      pfCloseModal();
      pfLoad();
    } else {al.textContent=j.message||'Failed';al.style.display='block';}
  }catch(err){al.textContent='Request failed: '+err.message;al.style.display='block';}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-book"></i> Post Journal Entry';}
}

pfLoad();
</script>

<?php elseif ($subModule == 'debts'): ?>
<!-- ====== OUTSTANDING PATIENT DEBTS (balances transferred from Invoicing) ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-hand-holding-usd" style="color:var(--primary-color);"></i> Outstanding Debts</h3>
    <div style="display:flex;gap:8px;align-items:center;">
      <select id="debt-status" onchange="debtLoad()" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <option value="outstanding">Outstanding</option><option value="cleared">Cleared</option><option value="written_off">Written off</option><option value="all">All</option>
      </select>
      <button onclick="debtLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div id="debt-total" style="font-size:13px;color:var(--gray-600);margin-bottom:12px;"></div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:10px 12px;text-align:left;">Patient</th><th style="padding:10px 12px;text-align:left;">Invoice / Visit</th>
        <th style="padding:10px 12px;text-align:right;">Debt</th><th style="padding:10px 12px;text-align:right;">Invoice balance</th>
        <th style="padding:10px 12px;text-align:left;">Transferred</th><th style="padding:10px 12px;text-align:left;">Status</th><th style="padding:10px 12px;"></th>
      </tr></thead>
      <tbody id="debt-tbody"><tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>
<script>
const debtAPI = (typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=patients/billing';
let debtRows = [], debtAccounts = [];
function dEsc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
const debtStatusStyle = {outstanding:'background:#fef3c7;color:#92400e', cleared:'background:#d1fae5;color:#065f46', written_off:'background:#fee2e2;color:#991b1b'};

async function debtLoad(){
  const tbody = document.getElementById('debt-tbody');
  try{
    const j = await (await fetch(debtAPI + '&action=debts&status=' + document.getElementById('debt-status').value)).json();
    if(!j.success) throw new Error(j.message);
    debtRows = j.data.debts;
    const total = debtRows.filter(d => d.status === 'outstanding').reduce((s, d) => s + parseFloat(d.amount), 0);
    document.getElementById('debt-total').innerHTML = `Outstanding total: <strong style="color:#b45309;">${total.toLocaleString()} RWF</strong> · ${debtRows.length} record(s)`;
    tbody.innerHTML = debtRows.length ? debtRows.map(d => `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 12px;"><div style="font-weight:600;">${dEsc(d.patient_name)}</div><div style="font-size:11px;color:var(--gray-500);">${dEsc(d.patient_no)} ${dEsc(d.phone)}</div></td>
      <td style="padding:10px 12px;font-family:monospace;font-size:12px;">${dEsc(d.invoice_no)}<div style="color:var(--gray-500);">${dEsc(d.visit_no)}</div></td>
      <td style="padding:10px 12px;text-align:right;font-weight:700;">${parseFloat(d.amount).toLocaleString()}${d.loss_account_name ? `<div style="font-size:11px;font-weight:400;color:#991b1b;">${dEsc(d.loss_account_name)}</div>` : ''}</td>
      <td style="padding:10px 12px;text-align:right;color:var(--gray-600);">${d.invoice_status === 'cancelled' ? 'cancelled' : parseFloat(d.invoice_balance).toLocaleString()}</td>
      <td style="padding:10px 12px;font-size:12px;">${dEsc(String(d.transferred_at).slice(0,16))}<div style="color:var(--gray-500);">${dEsc(d.transferred_by_name)}</div></td>
      <td style="padding:10px 12px;"><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;${debtStatusStyle[d.status]||''}">${dEsc(d.status.replace('_',' '))}</span>
        ${d.resolved_at ? `<div style="font-size:11px;color:var(--gray-500);margin-top:2px;">${dEsc(d.resolved_by_name)} · ${dEsc(String(d.resolved_at).slice(0,10))}${d.resolution_note ? ' — ' + dEsc(d.resolution_note) : ''}</div>` : ''}</td>
      <td style="padding:10px 12px;text-align:right;white-space:nowrap;">${d.status === 'outstanding' && j.data.can_resolve ? `
        <button onclick="debtOpen(${d.id},'cleared')" style="padding:5px 10px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:11px;cursor:pointer;">Record payment</button>
        <button onclick="debtOpen(${d.id},'written_off')" style="padding:5px 10px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:6px;font-size:11px;cursor:pointer;">Write off</button>` : ''}</td>
    </tr>`).join('') : '<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No debts.</td></tr>';
  }catch(e){ tbody.innerHTML = `<tr><td colspan="7" style="padding:20px;color:#dc2626;">${dEsc(e.message || 'Failed to load debts')}</td></tr>`; }
}

async function debtOpen(id, resolution){
  const d = debtRows.find(x => x.id == id);
  if(!debtAccounts.length){
    try{ debtAccounts = (await (await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php') + '?endpoint=finance/chart-of-accounts&action=cash_balances')).json()).data.accounts || []; }catch(e){}
  }
  const isPay = resolution === 'cleared';
  const accs = debtAccounts.filter(a => a.account_group === (isPay ? 'cash' : 'expense'));
  const m = document.createElement('div'); m.id = 'debt-modal';
  m.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  m.innerHTML = `<div style="background:white;border-radius:12px;width:440px;max-width:95vw;padding:24px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;">${isPay ? 'Record debt payment' : 'Write off debt'}</h3>
    <div style="background:var(--gray-50);border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:14px;">${dEsc(d.patient_name)} · ${dEsc(d.invoice_no)}<div style="font-size:16px;font-weight:700;margin-top:4px;">${parseFloat(d.amount).toLocaleString()} RWF</div></div>
    ${isPay ? `<label style="font-size:12px;font-weight:600;">Payment method</label>
      <select id="debt-method" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;margin:4px 0 12px;"><option value="cash">Cash</option><option value="bank">Bank Transfer</option><option value="mobile_money">Mobile Money</option></select>` : ''}
    <label style="font-size:12px;font-weight:600;">${isPay ? 'Receive into' : 'Expense / bad-debt account'}</label>
    <select id="debt-account" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;margin:4px 0 12px;"><option value="">-- Select --</option>${accs.map(a => `<option value="${a.id}" ${!isPay && a.id == d.loss_account_id ? 'selected' : ''}>${dEsc(a.account_name)}</option>`).join('')}</select>
    ${isPay ? `<label style="font-size:12px;font-weight:600;">Reference</label><input id="debt-ref" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;margin:4px 0 12px;box-sizing:border-box;">` : ''}
    <label style="font-size:12px;font-weight:600;">${isPay ? 'Note (optional)' : 'Reason for write-off *'}</label>
    <textarea id="debt-note" rows="2" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;margin:4px 0 16px;box-sizing:border-box;"></textarea>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button onclick="document.getElementById('debt-modal').remove()" style="padding:9px 16px;background:var(--gray-100);border:none;border-radius:6px;cursor:pointer;">Cancel</button>
      <button id="debt-save" onclick="debtSave(${id},'${resolution}')" style="padding:9px 16px;background:${isPay ? '#16a34a' : '#dc2626'};color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">${isPay ? 'Record payment' : 'Write off'}</button>
    </div></div>`;
  document.body.appendChild(m);
}

async function debtSave(id, resolution){
  const v = k => (document.getElementById(k) || {}).value || '';
  if(!v('debt-account')) return alert('Select an account.');
  if(resolution === 'written_off' && v('debt-note').trim().length < 5) return alert('Enter the reason for the write-off.');
  const btn = document.getElementById('debt-save'); btn.disabled = true;
  try{
    const j = await (await fetch(debtAPI + '&action=debt_resolve', {method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({debt_id:id, resolution, account_id:parseInt(v('debt-account')), payment_method:v('debt-method') || 'cash', reference:v('debt-ref'), note:v('debt-note').trim()})})).json();
    if(!j.success) throw new Error(j.message);
    document.getElementById('debt-modal').remove();
    debtLoad();
  }catch(e){ alert(e.message || 'Failed'); btn.disabled = false; }
}
debtLoad();
</script>

<?php elseif (in_array($subModule, ['expenses','bank-reconciliation','fs-notes'], true)): ?>
<?php require __DIR__ . '/includes/finance_ops_ui.php'; ?>

<?php elseif (in_array($subModule, ['equity-statement','year-end'], true)): ?>
<?php require __DIR__ . '/includes/finance_close_ui.php'; ?>

<?php elseif (in_array($subModule, ['receivables','claims'], true)): ?>
<?php require __DIR__ . '/includes/finance_receivables_ui.php'; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
