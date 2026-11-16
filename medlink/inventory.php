<?php
// public/inventory.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'central-store';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Page title mapping
$subModuleTitles = [
    'central-store' => 'Central Store',
    'items' => 'Items Master',
    'categories' => 'Item Categories',
    'opening' => 'Opening Stock',
    'stock-report' => 'Stock Report',
    'purchase-request' => 'Purchase Requests',
    'goods-receiving' => 'Goods Receiving',
    'transfers' => 'Store Transfers',
    'stock-issues' => 'Drug Issues & Movements',
    'drug-performance' => 'Drug Performance',
    'expired' => 'Expired Items'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'Inventory';
?>

<script>
// API Base URL - use relative path to work correctly
const invAPI = 'api.php?endpoint=inventory/inventory-management&action=';
const loggedUserId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'null'; ?>;
const invIsAdmin = <?php echo ((function_exists('userHasRole') && userHasRole('admin')) || !empty($_SESSION['user_data']['is_admin']) || !empty($_SESSION['is_admin'])) ? 'true' : 'false'; ?>;

// ========== UTILITY FUNCTIONS ==========
function escHtml(str) { const d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }

function showToast(msg, type) {
    let t = document.getElementById('toast-container');
    if (!t) { t = document.createElement('div'); t.id = 'toast-container'; t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;'; document.body.appendChild(t); }
    const d = document.createElement('div'); d.style.cssText = 'padding:12px 20px;border-radius:8px;color:white;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:slideIn 0.3s ease;';
    d.style.background = type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#10b981';
    d.textContent = msg; t.appendChild(d);
    setTimeout(() => { d.style.opacity = '0'; d.style.transition = 'opacity 0.3s'; setTimeout(() => d.remove(), 300); }, 3000);
}

function showModal(title, bodyHtml, wide) {
    let m = document.getElementById('gen-modal'); if (!m) { m = document.createElement('div'); m.id = 'gen-modal'; document.body.appendChild(m); }
    let width = '500px';
    if (wide === true) width = '90%';
    else if (wide === 'medium') width = '650px';
    else if (typeof wide === 'string') width = wide;
    m.innerHTML = '<div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;" onclick="if(event.target===this)document.getElementById(\'gen-modal\').innerHTML=\'\'"><div style="background:white;border-radius:12px;width:'+width+';max-width:90vw;max-height:90vh;overflow:auto;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.3);"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;"><h3 style="font-size:18px;font-weight:600;">'+escHtml(title)+'</h3><button onclick="this.closest(\'#gen-modal\').innerHTML=\'\'" style="background:none;border:none;font-size:24px;cursor:pointer;color:var(--gray-400);">&times;</button></div>'+bodyHtml+'</div></div>';
}

// ========== GLOBAL MOVEMENT HISTORY MODAL ==========
let srStoresList = [];
let srCurrentModalItem = null;
let srModalAllMovements = [];

function srEnsureMovementModalDOM(){
  const inlineHost = document.getElementById('sr-inline-host');
  if(inlineHost ? inlineHost.firstElementChild : document.getElementById('sr-movement-modal')) return;
  const m = document.createElement('div');
  m.id = 'sr-movement-modal';
  m.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:10000;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px);';
  m.onclick = function(event){ if(event.target===this) srCloseMovementModal(); };
  m.innerHTML = `
  <div style="background:white;border-radius:12px;width:1000px;max-width:98vw;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:hidden;animation:srModalIn 0.2s ease-out;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;background:white;">
      <div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
          <h3 id="sr-modal-title" style="font-size:16px;font-weight:700;margin:0;color:var(--gray-800);display:flex;align-items:center;gap:8px;">
            <i class="fas fa-history" style="color:var(--primary-color);"></i>
            <span>Movement History</span>
          </h3>
          <span id="sr-modal-code" style="font-size:11px;background:var(--gray-100);color:var(--primary-color);padding:2px 8px;border-radius:4px;font-family:monospace;font-weight:600;"></span>
        </div>
        <div id="sr-modal-subtitle" style="font-size:12px;color:var(--gray-500);margin-top:3px;">Detailed stock transaction log with full dates and timestamps</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <button onclick="srExportModalCSV()" style="padding:6px 10px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:11px;cursor:pointer;color:var(--gray-700);font-weight:500;"><i class="fas fa-file-csv" style="color:#16a34a;margin-right:4px;"></i> Export</button>
        <button onclick="srPrintModalHistory()" style="padding:6px 10px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:11px;cursor:pointer;color:var(--gray-700);font-weight:500;"><i class="fas fa-print" style="color:var(--primary-color);margin-right:4px;"></i> Print</button>
        <button onclick="srCloseMovementModal()" style="border:none;background:none;font-size:22px;color:var(--gray-400);cursor:pointer;line-height:1;padding:4px 8px;border-radius:4px;" title="Close">&times;</button>
      </div>
    </div>
    <div style="padding:16px 20px;overflow-y:auto;flex:1;background:var(--gray-50);">
      <div id="sr-modal-stats" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(160px, 1fr));gap:10px;margin-bottom:14px;"></div>
      <div style="background:white;padding:12px;border-radius:8px;border:1px solid var(--gray-200);margin-bottom:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="min-width:140px;">
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--gray-600);">Store</label>
          <select id="sr-modal-store" onchange="srReloadModalMovements()" style="width:100%;padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
            <option value="">All Stores</option>
          </select>
        </div>
        <div style="min-width:120px;">
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--gray-600);">Movement Type</label>
          <select id="sr-modal-type" onchange="srReloadModalMovements()" style="width:100%;padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
            <option value="">All Types</option>
            <option value="purchase_receipt">Purchase Receipt / Inflow</option>
            <option value="transfer_in">Transfer In</option>
            <option value="transfer_out">Transfer Out</option>
            <option value="dispensing">Dispensing / Sales</option>
            <option value="consumption">Consumption</option>
            <option value="adjustment">Adjustment</option>
            <option value="return">Return</option>
            <option value="damage">Damage</option>
            <option value="expiry">Expiry / Write-off</option>
          </select>
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--gray-600);">From Date</label>
          <input type="date" id="sr-modal-from" onchange="srReloadModalMovements()" style="padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--gray-600);">To Date</label>
          <input type="date" id="sr-modal-to" onchange="srReloadModalMovements()" style="padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        </div>
        <div style="flex:1;min-width:160px;">
          <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--gray-600);">Search in History</label>
          <input type="text" id="sr-modal-search" placeholder="Filter notes, ref#, batch, user..." oninput="srFilterModalTable()" style="width:100%;padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        </div>
        <div style="align-self:flex-end;">
          <button onclick="srReloadModalMovements()" style="padding:6px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;" title="Reload"><i class="fas fa-sync-alt"></i></button>
        </div>
      </div>
      <div id="sr-modal-content" style="background:white;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading movement history...</div>
      </div>
    </div>
    <div style="padding:10px 20px;background:white;border-top:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;font-size:12px;color:var(--gray-500);">
      <span id="sr-modal-count-footer"></span>
      <button onclick="srCloseMovementModal()" style="padding:6px 16px;background:var(--gray-200);color:var(--gray-700);border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;">Close</button>
    </div>
  </div>`;
  if(inlineHost){
    // Movement Stock tab: same card, rendered inline (no overlay, no close buttons)
    const card = m.firstElementChild;
    card.style.cssText = 'background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;';
    card.querySelectorAll('[onclick^="srCloseMovementModal"]').forEach(b => b.remove());
    card.querySelector('#sr-modal-content').innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);">Select an item above to view its movement history.</div>';
    inlineHost.appendChild(card);
    return;
  }
  document.body.appendChild(m);
}

function srOpenMovementModal(itemId, itemName, itemCode, storeId, storeName, batchId, batchNumber){
  srEnsureMovementModalDOM();
  if(document.getElementById('sr-inline-host')){
    const gm = document.getElementById('gen-modal'); if(gm) gm.innerHTML = '';
    itemsTab('movement');
    const pk = document.getElementById('sr-pick'); if(pk) pk.value = (itemCode ? itemCode + ' - ' : '') + (itemName || '');
  }
  srCurrentModalItem = {
    itemId: itemId,
    itemName: itemName || 'Item #' + itemId,
    itemCode: itemCode || '',
    storeId: storeId || '',
    storeName: storeName || '',
    batchId: batchId || '',
    batchNumber: batchNumber || ''
  };

  const modalStoreSel = document.getElementById('sr-modal-store');
  if(modalStoreSel){
    if(srStoresList && srStoresList.length){
      const opts = srStoresList.map(s => `<option value="${s.id}" ${storeId && String(storeId) === String(s.id) ? 'selected' : ''}>${escHtml(s.name)}</option>`).join('');
      modalStoreSel.innerHTML = '<option value="">All Stores</option>' + opts;
    } else {
      fetch(invAPI+'stores').then(r=>r.json()).then(j=>{
        if(j.success && j.data){
          srStoresList = j.data;
          const opts = j.data.map(s => `<option value="${s.id}" ${storeId && String(storeId) === String(s.id) ? 'selected' : ''}>${escHtml(s.name)}</option>`).join('');
          modalStoreSel.innerHTML = '<option value="">All Stores</option>' + opts;
          if(storeId) modalStoreSel.value = storeId;
        }
      }).catch(e=>{});
    }
    if(storeId) modalStoreSel.value = storeId;
    else modalStoreSel.value = '';
  }

  document.getElementById('sr-modal-from').value = document.getElementById('sr-from')?.value || '';
  document.getElementById('sr-modal-to').value = document.getElementById('sr-to')?.value || new Date().toISOString().split('T')[0];
  document.getElementById('sr-modal-type').value = '';
  document.getElementById('sr-modal-search').value = '';

  document.getElementById('sr-modal-title').innerHTML = '<i class="fas fa-history" style="color:var(--primary-color);"></i> <span>Movement History: ' + escHtml(srCurrentModalItem.itemName) + '</span>';
  document.getElementById('sr-modal-code').textContent = srCurrentModalItem.itemCode ? ('Code: ' + srCurrentModalItem.itemCode) : '';
  document.getElementById('sr-modal-code').style.display = srCurrentModalItem.itemCode ? 'inline-block' : 'none';
  
  let subText = 'Showing all stock transactions and movements with full dates and timestamps.';
  if (storeName) subText += ' (Store: ' + escHtml(storeName) + ')';
  if (batchNumber) subText += ' (Batch: ' + escHtml(batchNumber) + ')';
  document.getElementById('sr-modal-subtitle').textContent = subText;

  const modal = document.getElementById('sr-movement-modal');
  if(modal){
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }

  srReloadModalMovements();
}

function srCloseMovementModal(){
  const modal = document.getElementById('sr-movement-modal');
  if(modal) modal.style.display = 'none';
  document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e){
  if(e.key === 'Escape'){
    const m = document.getElementById('sr-movement-modal');
    if(m && m.style.display === 'flex') srCloseMovementModal();
  }
});

async function srReloadModalMovements(){
  if(!srCurrentModalItem) return;
  const contentDiv = document.getElementById('sr-modal-content');
  const statsDiv = document.getElementById('sr-modal-stats');
  const footerCount = document.getElementById('sr-modal-count-footer');

  contentDiv.innerHTML = '<div style="padding:36px;text-align:center;color:var(--gray-500);"><i class="fas fa-spinner fa-spin" style="font-size:22px;color:var(--primary-color);margin-bottom:8px;display:block;"></i> Fetching full movement history...</div>';
  if (statsDiv) statsDiv.innerHTML = '';
  if (footerCount) footerCount.textContent = '';

  const storeId = document.getElementById('sr-modal-store')?.value || '';
  const type = document.getElementById('sr-modal-type')?.value || '';
  const from = document.getElementById('sr-modal-from')?.value || '';
  const to = document.getElementById('sr-modal-to')?.value || '';

  let url = invAPI + 'movements&item_id=' + encodeURIComponent(srCurrentModalItem.itemId) + '&limit=1000';
  if (storeId) url += '&store_id=' + encodeURIComponent(storeId);
  if (type) url += '&type=' + encodeURIComponent(type);
  if (from) url += '&from=' + encodeURIComponent(from);
  if (to) url += '&to=' + encodeURIComponent(to);

  try {
    const r = await fetch(url);
    const j = await r.json();
    if(j.success && j.data){
      srModalAllMovements = j.data;
      srFilterModalTable();
    } else {
      srModalAllMovements = [];
      contentDiv.innerHTML = '<div style="padding:36px;text-align:center;color:var(--gray-400);"><i class="fas fa-exchange-alt" style="font-size:24px;margin-bottom:8px;display:block;"></i> No movement history records found for this item with selected filters.</div>';
    }
  } catch(e) {
    contentDiv.innerHTML = '<div style="padding:30px;text-align:center;color:#dc2626;"><i class="fas fa-exclamation-circle" style="font-size:20px;margin-bottom:6px;display:block;"></i> Error loading movement history: ' + escHtml(e.message) + '</div>';
  }
}

function srFilterModalTable(){
  if(!srModalAllMovements) return;
  const q = (document.getElementById('sr-modal-search')?.value || '').trim().toLowerCase();
  
  let filtered = srModalAllMovements;
  if(q){
    filtered = srModalAllMovements.filter(m => {
      const type = (m.movement_type || '').toLowerCase();
      const refType = (m.reference_type || '').toLowerCase();
      const refId = String(m.reference_id || '');
      const notes = (m.notes || '').toLowerCase();
      const batch = (m.batch_number || '').toLowerCase();
      const fromSt = (m.from_store_name || '').toLowerCase();
      const toSt = (m.to_store_name || '').toLowerCase();
      const user = (m.created_by_name || '').toLowerCase();
      const date = (m.movement_date || m.created_at || '').toLowerCase();
      return type.includes(q) || refType.includes(q) || refId.includes(q) || notes.includes(q) || batch.includes(q) || fromSt.includes(q) || toSt.includes(q) || user.includes(q) || date.includes(q);
    });
  }

  srRenderModalStats(filtered);
  srRenderModalTable(filtered);
}

function srRenderModalStats(movements){
  const statsDiv = document.getElementById('sr-modal-stats');
  if(!statsDiv) return;

  let totalIn = 0;
  let totalOut = 0;

  movements.forEach(m => {
    const qty = parseFloat(m.quantity || 0);
    const t = m.movement_type;
    if(t === 'purchase_receipt' || t === 'transfer_in' || t === 'return'){
      totalIn += qty;
    } else if(t === 'transfer_out' || t === 'dispensing' || t === 'consumption' || t === 'damage' || t === 'expiry'){
      totalOut += qty;
    } else {
      if(m.to_store_id && !m.from_store_id) totalIn += qty;
      else if(m.from_store_id && !m.to_store_id) totalOut += qty;
      else totalIn += qty;
    }
  });

  const net = totalIn - totalOut;

  statsDiv.innerHTML = `
    <div style="background:white;padding:10px 14px;border-radius:8px;border:1px solid var(--gray-200);box-shadow:0 1px 2px rgba(0,0,0,0.04);">
      <div style="font-size:11px;color:var(--gray-500);font-weight:600;text-transform:uppercase;">Total Movements</div>
      <div style="font-size:18px;font-weight:700;color:var(--gray-800);margin-top:2px;">${movements.length} <span style="font-size:12px;font-weight:400;color:var(--gray-400);">entries</span></div>
    </div>
    <div style="background:#f0fdf4;padding:10px 14px;border-radius:8px;border:1px solid #bbf7d0;box-shadow:0 1px 2px rgba(0,0,0,0.04);">
      <div style="font-size:11px;color:#166534;font-weight:600;text-transform:uppercase;"><i class="fas fa-arrow-down"></i> Total Inflow</div>
      <div style="font-size:18px;font-weight:700;color:#16a34a;margin-top:2px;">+${totalIn.toLocaleString(undefined, {maximumFractionDigits:2})}</div>
    </div>
    <div style="background:#fef2f2;padding:10px 14px;border-radius:8px;border:1px solid #fecaca;box-shadow:0 1px 2px rgba(0,0,0,0.04);">
      <div style="font-size:11px;color:#991b1b;font-weight:600;text-transform:uppercase;"><i class="fas fa-arrow-up"></i> Total Outflow</div>
      <div style="font-size:18px;font-weight:700;color:#dc2626;margin-top:2px;">-${totalOut.toLocaleString(undefined, {maximumFractionDigits:2})}</div>
    </div>
    <div style="background:white;padding:10px 14px;border-radius:8px;border:1px solid var(--gray-200);box-shadow:0 1px 2px rgba(0,0,0,0.04);">
      <div style="font-size:11px;color:var(--gray-500);font-weight:600;text-transform:uppercase;">Net Movement</div>
      <div style="font-size:18px;font-weight:700;color:${net >= 0 ? '#16a34a' : '#dc2626'};margin-top:2px;">${net >= 0 ? '+' : ''}${net.toLocaleString(undefined, {maximumFractionDigits:2})}</div>
    </div>
  `;
}

function srGetMovementBadge(type, m){
  if(m && (m.movement_type === 'return' || m.movement_type === 'adjustment') && m.reference_type === 'consumption_reversal'){
    return `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#dcfce7;color:#166534;white-space:nowrap;">
      <i class="fas fa-undo" style="font-size:10px;"></i> Consump. Reversal
    </span>`;
  }
  const badges = {
    'purchase_receipt': { label: 'Purchase Receipt', bg: '#d1fae5', color: '#065f46', icon: 'fa-arrow-down' },
    'transfer_in':      { label: 'Transfer In',      bg: '#dcfce7', color: '#166534', icon: 'fa-sign-in-alt' },
    'transfer_out':     { label: 'Transfer Out',     bg: '#ffedd5', color: '#9a3412', icon: 'fa-sign-out-alt' },
    'dispensing':       { label: 'Dispensing / Sale', bg: '#f3e8ff', color: '#6b21a8', icon: 'fa-prescription' },
    'consumption':      { label: 'Consumption',      bg: '#e0e7ff', color: '#3730a3', icon: 'fa-box-open' },
    'adjustment':       { label: 'Adjustment',       bg: '#dbeafe', color: '#1e40af', icon: 'fa-sliders-h' },
    'return':           { label: 'Return',           bg: '#cffafe', color: '#155e75', icon: 'fa-undo' },
    'damage':           { label: 'Damage',           bg: '#fee2e2', color: '#991b1b', icon: 'fa-exclamation-triangle' },
    'expiry':           { label: 'Expiry / Write-off', bg: '#fee2e2', color: '#991b1b', icon: 'fa-calendar-times' }
  };
  const b = badges[type] || { label: (type || 'Movement').replace(/_/g, ' '), bg: '#f3f4f6', color: '#374151', icon: 'fa-exchange-alt' };
  let badgeHtml = `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:12px;font-size:11px;font-weight:600;background:${b.bg};color:${b.color};white-space:nowrap;text-transform:capitalize;">
    <i class="fas ${b.icon}" style="font-size:10px;"></i> ${b.label}
  </span>`;
  if(m && (m.is_reverted > 0 || (m.notes || '').indexOf('[REVERTED') !== -1)){
    badgeHtml += ` <span style="display:inline-flex;align-items:center;gap:3px;padding:2px 6px;border-radius:6px;font-size:10px;font-weight:700;background:#fee2e2;color:#991b1b;margin-left:4px;" title="This transaction has been reverted"><i class="fas fa-ban"></i> REVERTED</span>`;
  }
  return badgeHtml;
}

function srFormatFullDateTime(dateStr){
  if(!dateStr) return '<span style="color:var(--gray-400);">—</span>';
  try {
    const parts = dateStr.split(' ');
    const datePart = parts[0];
    const timePart = parts[1] || '';
    const d = new Date(dateStr.replace(/-/g, '/'));
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    let formattedDate = datePart;
    if(!isNaN(d.getTime())){
      formattedDate = String(d.getDate()).padStart(2,'0') + ' ' + monthNames[d.getMonth()] + ' ' + d.getFullYear();
    }
    return `<div style="display:flex;flex-direction:column;gap:2px;">
      <span style="font-weight:600;color:var(--gray-800);font-size:12px;"><i class="far fa-calendar-alt" style="color:var(--primary-color);margin-right:4px;font-size:11px;"></i>${formattedDate}</span>
      ${timePart ? `<span style="font-size:11px;color:var(--gray-500);font-family:monospace;"><i class="far fa-clock" style="margin-right:3px;font-size:10px;"></i>${timePart}</span>` : ''}
    </div>`;
  } catch(e) {
    return `<span style="font-family:monospace;font-size:11px;">${escHtml(dateStr)}</span>`;
  }
}

function srRenderModalTable(movements){
  const contentDiv = document.getElementById('sr-modal-content');
  const footerCount = document.getElementById('sr-modal-count-footer');

  if(!movements || !movements.length){
    contentDiv.innerHTML = '<div style="padding:36px;text-align:center;color:var(--gray-400);"><i class="fas fa-search" style="font-size:22px;margin-bottom:8px;display:block;"></i>No matching movement records.</div>';
    if(footerCount) footerCount.textContent = '0 records';
    return;
  }

  if(footerCount) footerCount.textContent = 'Showing ' + movements.length + ' movement record' + (movements.length !== 1 ? 's' : '');

  let html = '<div style="max-height:55vh;overflow-y:auto;overflow-x:auto;">';
  html += '<table style="width:100%;border-collapse:collapse;font-size:12px;min-width:920px;"><thead><tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);position:sticky;top:0;z-index:2;">';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Date &amp; Time</th>';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Type</th>';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Store / Location</th>';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Batch &amp; Expiry</th>';
  html += '<th style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-700);">Qty</th>';
  html += '<th style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-700);">Unit Cost</th>';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Reference / Notes</th>';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">User</th>';
  html += '<th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Action</th>';
  html += '</tr></thead><tbody>';

  movements.forEach(m => {
    const isOut = (m.movement_type === 'transfer_out' || m.movement_type === 'dispensing' || m.movement_type === 'consumption' || m.movement_type === 'damage' || m.movement_type === 'expiry');
    const isIn = (m.movement_type === 'purchase_receipt' || m.movement_type === 'transfer_in' || m.movement_type === 'return');
    const qtyVal = parseFloat(m.quantity || 0);
    const qtySign = isIn ? '+' : (isOut ? '-' : '');
    const qtyColor = isIn ? '#16a34a' : (isOut ? '#dc2626' : 'var(--gray-800)');

    let storeRoute = '';
    if(m.movement_type === 'transfer_out' || m.movement_type === 'transfer_in'){
      storeRoute = `<div style="display:flex;align-items:center;gap:4px;font-size:11px;">
        <span style="color:var(--gray-600);">${escHtml(m.from_store_name || 'Store')}</span>
        <i class="fas fa-arrow-right" style="color:var(--primary-color);font-size:9px;"></i>
        <strong style="color:var(--gray-900);">${escHtml(m.to_store_name || 'Store')}</strong>
      </div>`;
    } else if(m.to_store_name && !m.from_store_name){
      let origin = m.reference_type === 'opening_stock' || m.reference_type === 'opening_balance' ? 'Opening Stock' : (m.reference_type === 'other_stock' ? 'Other Stock' : 'Supplier / PO');
      storeRoute = `<div style="font-size:11px;"><span style="color:var(--gray-400);">${origin} &rarr;</span> <strong style="color:var(--primary-color);">${escHtml(m.to_store_name)}</strong></div>`;
    } else if(m.from_store_name && !m.to_store_name){
      let dest = m.movement_type === 'dispensing' ? 'Patient / Dispensed' : (m.movement_type === 'consumption' ? 'Department / Consumed' : (m.movement_type === 'expiry' ? 'Write-off (Expired)' : 'Outflow'));
      storeRoute = `<div style="font-size:11px;"><strong style="color:var(--gray-800);">${escHtml(m.from_store_name)}</strong> <span style="color:var(--gray-400);">&rarr; ${dest}</span></div>`;
    } else if(m.from_store_name && m.to_store_name){
      storeRoute = `<div style="font-size:11px;">${escHtml(m.from_store_name)} &rarr; ${escHtml(m.to_store_name)}</div>`;
    } else {
      storeRoute = '<span style="color:var(--gray-400);">—</span>';
    }

    let refBadge = '';
    if(m.reference_type){
      const refLabel = m.reference_type.replace(/_/g, ' ').toUpperCase() + (m.reference_id ? ' #' + m.reference_id : '');
      refBadge = `<span style="background:var(--gray-100);color:var(--gray-700);padding:1px 5px;border-radius:3px;font-size:10px;font-family:monospace;font-weight:600;">${escHtml(refLabel)}</span>`;
    }
    const notesText = m.notes ? `<div style="color:var(--gray-600);font-size:11px;margin-top:2px;">${escHtml(m.notes)}</div>` : '';

    const isReverted = (m.is_reverted > 0) || ((m.notes || '').indexOf('[REVERTED') !== -1);
    const isConsumption = (m.movement_type === 'consumption') || (m.reference_type === 'consumption');

    let actionCell = '';
    if (isConsumption) {
      if (isReverted) {
        actionCell = '<span style="display:inline-flex;align-items:center;gap:3px;padding:3px 8px;background:#f3f4f6;color:#6b7280;border-radius:4px;font-size:11px;font-weight:600;" title="This consumption has already been reverted"><i class="fas fa-check"></i> Reverted</span>';
      } else if (invIsAdmin) {
        actionCell = `<button onclick="srOpenRevertConsumptionModal(${m.id})" style="padding:4px 10px;background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;cursor:pointer;font-size:11px;font-weight:600;color:#991b1b;display:inline-flex;align-items:center;gap:4px;transition:all 0.15s ease;" title="Revert this consumption: restores quantity back to batch and creates general ledger journal reversal">
          <i class="fas fa-undo"></i> Revert
        </button>`;
      } else {
        actionCell = '<span style="color:var(--gray-400);font-size:11px;" title="Admin only">Restricted</span>';
      }
    } else {
      actionCell = '<span style="color:var(--gray-300);">—</span>';
    }

    html += `<tr class="sr-row-hover" style="border-bottom:1px solid var(--gray-100);transition:background 0.1s ease;">
      <td style="padding:9px 12px;white-space:nowrap;">
        ${srFormatFullDateTime(m.movement_date || m.created_at)}
      </td>
      <td style="padding:9px 12px;white-space:nowrap;">
        ${srGetMovementBadge(m.movement_type, m)}
      </td>
      <td style="padding:9px 12px;">
        ${storeRoute}
      </td>
      <td style="padding:9px 12px;">
        <div style="font-family:monospace;font-size:11px;color:var(--primary-color);font-weight:600;">
          ${m.batch_number ? escHtml(m.batch_number) : '<span style="color:var(--gray-400);font-weight:normal;">—</span>'}
        </div>
        ${m.expiry_date ? `<div style="font-size:10px;color:var(--gray-500);"><i class="far fa-calendar" style="margin-right:2px;"></i>Exp: ${m.expiry_date}</div>` : ''}
      </td>
      <td style="padding:9px 12px;text-align:right;font-family:monospace;font-weight:700;font-size:12px;color:${qtyColor};white-space:nowrap;">
        ${qtySign}${qtyVal.toLocaleString(undefined, {maximumFractionDigits:2})}
        ${m.uom_abbr ? `<span style="font-size:10px;color:var(--gray-400);margin-left:3px;font-weight:normal;">${escHtml(m.uom_abbr)}</span>` : ''}
      </td>
      <td style="padding:9px 12px;text-align:right;font-size:11px;color:var(--gray-700);white-space:nowrap;">
        ${m.unit_cost ? parseFloat(m.unit_cost).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '<span style="color:var(--gray-400);">—</span>'}
      </td>
      <td style="padding:9px 12px;">
        ${refBadge}
        ${notesText}
      </td>
      <td style="padding:9px 12px;font-size:11px;color:var(--gray-600);white-space:nowrap;">
        ${m.created_by_name ? `<i class="fas fa-user-circle" style="color:var(--gray-400);margin-right:4px;"></i>${escHtml(m.created_by_name)}` : '<span style="color:var(--gray-400);">System</span>'}
      </td>
      <td style="padding:9px 12px;text-align:center;white-space:nowrap;">
        ${actionCell}
      </td>
    </tr>`;
  });

  html += '</tbody></table></div>';
  contentDiv.innerHTML = html;
}

function srExportModalCSV(){
  if(!srModalAllMovements || !srModalAllMovements.length) return alert('No movements to export.');
  let csv = 'Date Time,Movement Type,From Store,To Store,Batch Number,Expiry Date,Quantity,Unit Cost,Reference Type,Reference ID,Notes,User\n';
  srModalAllMovements.forEach(m => {
    const row = [
      '"' + (m.movement_date || m.created_at || '') + '"',
      '"' + (m.movement_type || '') + '"',
      '"' + (m.from_store_name || '').replace(/"/g, '""') + '"',
      '"' + (m.to_store_name || '').replace(/"/g, '""') + '"',
      '"' + (m.batch_number || '').replace(/"/g, '""') + '"',
      '"' + (m.expiry_date || '') + '"',
      m.quantity || 0,
      m.unit_cost || 0,
      '"' + (m.reference_type || '') + '"',
      '"' + (m.reference_id || '') + '"',
      '"' + (m.notes || '').replace(/"/g, '""') + '"',
      '"' + (m.created_by_name || '').replace(/"/g, '""') + '"'
    ];
    csv += row.join(',') + '\n';
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  const safeName = (srCurrentModalItem?.itemName || 'item').replace(/[^a-z0-9]/gi, '_').toLowerCase();
  a.download = 'movements_' + safeName + '.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

function srPrintModalHistory(){
  const div = document.getElementById('sr-modal-content');
  if(!div) return;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <html>
      <head>
        <title>Movement History - ${escHtml(srCurrentModalItem?.itemName || '')}</title>
        <style>
          body { font-family: sans-serif; padding: 20px; font-size: 12px; color: #1e293b; }
          h2 { margin-bottom: 2px; }
          h4 { margin-top: 2px; color: #475569; font-weight: 500; }
          table { width: 100%; border-collapse: collapse; margin-top: 15px; }
          th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; font-size: 11px; }
          th { background: #f1f5f9; font-weight: 600; }
          td.right { text-align: right; }
        </style>
      </head>
      <body>
        <h2>Movement History: ${escHtml(srCurrentModalItem?.itemName || '')} ${srCurrentModalItem?.itemCode ? '(' + escHtml(srCurrentModalItem.itemCode) + ')' : ''}</h2>
        <h4>Generated: ${new Date().toLocaleString()}</h4>
        ${div.innerHTML}
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => { printWindow.print(); }, 500);
}

// ========== REVERT ITEM CONSUMPTION (WITH JOURNAL REVERSAL) ==========
async function srOpenRevertConsumptionModal(movementId) {
  if (!invIsAdmin) {
    showToast('Permission denied: Only administrators can revert consumption movements.', 'error');
    return;
  }
  try {
    showToast('Loading movement details...', 'warning');
    const r = await fetch(invAPI + 'preview_revert_consumption&movement_id=' + encodeURIComponent(movementId) + '&_v=' + Date.now());
    const j = await r.json();
    if (!j.success || !j.data) {
      showToast(j.message || 'Error fetching movement details', 'error');
      return;
    }
    const { movement, is_reverted, cost_amount, original_journal, original_lines } = j.data;
    if (is_reverted) {
      showToast('This consumption has already been reverted', 'warning');
      return;
    }

    const qty = parseFloat(movement.quantity || 0);
    const unitCost = parseFloat(movement.unit_cost || 0);
    const totalVal = cost_amount || (qty * unitCost);
    const safeItemName = escHtml(movement.item_name || 'Item');
    const storeName = escHtml(movement.from_store_name || 'Store Location');
    const batchNo = movement.batch_number ? escHtml(movement.batch_number) : 'Default Batch';
    const origRef = original_journal ? escHtml(original_journal.reference) : (movement.reference_id ? 'CON #' + movement.reference_id : 'N/A');

    let linesPreviewHtml = '';
    if (original_lines && original_lines.length > 0) {
      linesPreviewHtml = original_lines.map(l => {
        const origDebit = parseFloat(l.debit || 0);
        const origCredit = parseFloat(l.credit || 0);
        // Swapped for reversal
        const revDebit = origCredit;
        const revCredit = origDebit;
        const isRevDr = revDebit > 0;
        const amt = isRevDr ? revDebit : revCredit;
        return `<div style="background:white;padding:7px 10px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;border-left:4px solid ${isRevDr ? '#16a34a' : '#4f46e5'};">
          <div>
            <span style="font-weight:700;color:${isRevDr ? '#16a34a' : '#4f46e5'};margin-right:6px;">${isRevDr ? 'DEBIT (Dr)' : 'CREDIT (Cr)'}:</span>
            <strong>${escHtml(l.account_name || 'Account #' + l.account_id)}</strong>
            ${l.account_code ? `<span style="font-size:11px;color:var(--gray-500);margin-left:4px;">[${escHtml(l.account_code)}]</span>` : ''}
          </div>
          <strong style="color:${isRevDr ? '#16a34a' : '#4f46e5'};font-size:12px;">${amt.toLocaleString()} RWF</strong>
        </div>`;
      }).join('');
    } else {
      linesPreviewHtml = `
        <div style="background:white;padding:7px 10px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;border-left:4px solid #16a34a;">
          <div>
            <span style="font-weight:700;color:#16a34a;margin-right:6px;">DEBIT (Dr):</span>
            <strong>${storeName}</strong>
            <span style="font-size:11px;color:var(--gray-500);margin-left:4px;">(Store Inventory Asset &bull; Inventory Restored)</span>
          </div>
          <strong style="color:#16a34a;font-size:12px;">${totalVal.toLocaleString()} RWF</strong>
        </div>
        <div style="background:white;padding:7px 10px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;border-left:4px solid #4f46e5;">
          <div>
            <span style="font-weight:700;color:#4f46e5;margin-right:6px;">CREDIT (Cr):</span>
            <strong>Medical Expenses / Drugs COGS</strong>
            <span style="font-size:11px;color:var(--gray-500);margin-left:4px;">(Expense / Cost Reversal)</span>
          </div>
          <strong style="color:#4f46e5;font-size:12px;">${totalVal.toLocaleString()} RWF</strong>
        </div>
      `;
    }

    const html = `
    <div style="font-size:13px;color:var(--gray-800);">
      <!-- Summary Box -->
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 14px;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:8px;font-weight:700;font-size:14px;color:#c2410c;margin-bottom:8px;">
          <i class="fas fa-undo"></i>
          <span>Revert Item Consumption</span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px;">
          <div><span style="color:var(--gray-500);">Item:</span> <strong>${safeItemName}</strong></div>
          <div><span style="color:var(--gray-500);">Store:</span> <strong>${storeName}</strong></div>
          <div><span style="color:var(--gray-500);">Batch:</span> <code style="background:#fef3c7;padding:1px 4px;border-radius:3px;">${batchNo}</code></div>
          <div><span style="color:var(--gray-500);">Movement ID:</span> <strong>#${movement.id}</strong> (${movement.movement_date || movement.created_at})</div>
          <div><span style="color:var(--gray-500);">Original Reference:</span> <strong>${origRef}</strong></div>
          <div><span style="color:var(--gray-500);">Quantity to Restore:</span> <strong style="color:#16a34a;font-size:13px;">+${qty.toLocaleString()} units</strong></div>
          <div style="grid-column:1/-1;"><span style="color:var(--gray-500);">Restored Inventory Value:</span> <strong style="color:#16a34a;font-size:14px;">${totalVal.toLocaleString()} RWF</strong> (${unitCost.toLocaleString()} RWF/unit)</div>
        </div>
      </div>

      <!-- Financial Transaction Impact Preview -->
      <div style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:12px 14px;margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-size:12px;font-weight:700;color:var(--gray-800);display:flex;align-items:center;gap:6px;">
            <i class="fas fa-book" style="color:var(--primary-color);"></i>
            <span>General Ledger Reversal Impact</span>
          </span>
          <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:#dcfce7;color:#166534;">Auto-Balanced Contra Entry</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;font-size:12px;">
          ${linesPreviewHtml}
        </div>
        <div style="font-size:11px;color:var(--gray-500);margin-top:8px;">
          <i class="fas fa-info-circle"></i> This transaction will post a reversing contra entry to the General Ledger and return stock to the store inventory batch.
        </div>
      </div>

      <!-- Reason for Reversal -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">
          Reason for Reversal / Return Note <span style="color:var(--danger-color);">*</span>
        </label>
        <input type="text" id="rev-consume-reason" value="Item returned unused / consumption cancelled" placeholder="Enter audit reason..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;" required>
      </div>

      <!-- Actions -->
      <div style="display:flex;justify-content:flex-end;gap:10px;align-items:center;">
        <button type="button" onclick="document.getElementById('gen-modal').innerHTML=''" style="padding:9px 16px;background:var(--gray-100);border:1px solid var(--gray-300);border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-700);">
          Cancel
        </button>
        <button type="button" id="rev-consume-submit-btn" onclick="srSubmitRevertConsumption(${movement.id})" style="padding:9px 20px;background:#dc2626;color:white;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 6px rgba(220,38,38,0.25);">
          <i class="fas fa-undo"></i> <span>Confirm &amp; Reverse Consumption</span>
        </button>
      </div>
    </div>
    `;

    showModal('Revert Consumption & Post Financial Reversal', html, '640px');
  } catch(e) {
    showToast('Error: ' + e.message, 'error');
    console.error('srOpenRevertConsumptionModal:', e);
  }
}

async function srSubmitRevertConsumption(movementId) {
  if (!invIsAdmin) {
    showToast('Permission denied: Only administrators can revert consumption movements.', 'error');
    return;
  }
  const reasonInput = document.getElementById('rev-consume-reason');
  const reason = reasonInput ? reasonInput.value.trim() : '';
  const btn = document.getElementById('rev-consume-submit-btn');

  if (!reason) {
    showToast('Please provide a reason for the reversal', 'error');
    if (reasonInput) reasonInput.focus();
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reversing...';
  }

  try {
    const r = await fetch(invAPI + 'revert_consumption', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        movement_id: movementId,
        reason: reason,
        user_id: loggedUserId
      })
    });
    const j = await r.json();

    if (j.success) {
      showToast('✓ ' + (j.message || 'Consumption reverted successfully!'));
      
      // Close modal
      const m = document.getElementById('gen-modal');
      if (m) m.innerHTML = '';

      // Reload movement history table
      srReloadModalMovements();

      // Reload items master data so stock quantity refreshes
      if (typeof loadItemsMaster === 'function') {
        loadItemsMaster();
      }

      // If item stock modal is open, refresh it
      if (typeof curItemStockData !== 'undefined' && curItemStockData && curItemStockData.itemId) {
        viewItemStock(curItemStockData.itemId, curItemStockData.itemName, 0);
      }
    } else {
      showToast(j.message || 'Failed to revert consumption', 'error');
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-undo"></i> <span>Confirm &amp; Reverse Consumption</span>';
      }
    }
  } catch(e) {
    showToast('Network error: ' + e.message, 'error');
    console.error('srSubmitRevertConsumption:', e);
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-undo"></i> <span>Confirm &amp; Reverse Consumption</span>';
    }
  }
}

// ========== LOADING DROPDOWNS ==========
async function loadCategories(selectId, selectedId) {
    try {
        const r = await fetch(invAPI + 'categories');
        const j = await r.json();
        if (!j.success) return;
        const sel = document.getElementById(selectId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Select Category...</option>';
        (j.data || []).forEach(c => {
            sel.innerHTML += '<option value="' + c.id + '"' + (selectedId && c.id == selectedId ? ' selected' : '') + '>' + escHtml(c.name) + '</option>';
        });
    } catch(e) { console.error('loadCategories:', e); }
}

async function loadUoms(selectId, selectedId) {
    try {
        const r = await fetch(invAPI + 'uoms');
        const j = await r.json();
        if (!j.success) return;
        const sel = document.getElementById(selectId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Select Unit...</option>';
        (j.data || []).forEach(u => {
            sel.innerHTML += '<option value="' + u.id + '"' + (selectedId && u.id == selectedId ? ' selected' : '') + '>' + escHtml(u.name) + ' (' + escHtml(u.abbreviation) + ')</option>';
        });
        sel.innerHTML += '<option value="__new__">+ Add new unit...</option>';
        sel.onchange = () => { if (sel.value === '__new__') addUom(sel); };
    } catch(e) { console.error('loadUoms:', e); }
}

async function addUom(sel) {
    const name = (prompt('New unit name (e.g. Tablet):') || '').trim();
    if (!name) { sel.value = ''; return; }
    const abbr = (prompt('Abbreviation (e.g. tab):', name) || '').trim() || name;
    try {
        const r = await fetch(invAPI + 'create_uom', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({name: name, abbreviation: abbr})
        });
        const j = await r.json();
        if (!j.success) { alert(j.message || 'Failed to add unit'); sel.value = ''; return; }
        if (!sel.querySelector('option[value="' + j.data.id + '"]')) {
            sel.add(new Option(name + ' (' + abbr + ')', j.data.id), sel.querySelector('option[value="__new__"]'));
        }
        sel.value = j.data.id;
    } catch(e) { alert('Failed to add unit'); sel.value = ''; }
}

async function loadStores(selectId, selectedId) {
    try {
        const r = await fetch(invAPI + 'stores');
        const j = await r.json();
        if (!j.success) return;
        const sel = document.getElementById(selectId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Select Store...</option>';
        (j.data || []).forEach(s => {
            sel.innerHTML += '<option value="' + s.id + '"' + (selectedId && s.id == selectedId ? ' selected' : '') + '>' + escHtml(s.name) + ' (' + escHtml(s.code) + ')</option>';
        });
    } catch(e) { console.error('loadStores:', e); }
}

async function loadSuppliers(selectId, selectedId) {
    try {
        const r = await fetch(invAPI + 'suppliers');
        const j = await r.json();
        if (!j.success) return;
        const sel = document.getElementById(selectId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Select Supplier...</option>';
        (j.data || []).forEach(s => {
            sel.innerHTML += '<option value="' + s.id + '"' + (selectedId && s.id == selectedId ? ' selected' : '') + '>' + escHtml(s.company_name) + '</option>';
        });
    } catch(e) { console.error('loadSuppliers:', e); }
}

async function loadItems(selectId, selectedId, filterCategory) {
    try {
        let url = invAPI + 'items&active=true';
        if (filterCategory) url += '&category_id=' + filterCategory;
        const r = await fetch(url);
        const j = await r.json();
        if (!j.success) return;
        const sel = document.getElementById(selectId);
        if (!sel) return;
        sel.innerHTML = '<option value="">Select Item...</option>';
        (j.data || []).forEach(i => {
            sel.innerHTML += '<option value="' + i.id + '"' + (selectedId && i.id == selectedId ? ' selected' : '') + '>' + escHtml(i.name) + ' (' + escHtml(i.item_code) + ')</option>';
        });
    } catch(e) { console.error('loadItems:', e); }
}
// ========== DASHBOARD ==========
async function loadDashboard() {
    const sEl = document.getElementById('inv-stats');
    if (!sEl) return;
    sEl.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    try {
        const r = await fetch(invAPI + 'dashboard_stats' + (window._storeFilter ? '&store_id=' + window._storeFilter : ''));
        const j = await r.json();
        if (!j.success || !j.data) { sEl.innerHTML = ''; return; }
        const d = j.data;
        sEl.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;">' +
            '<div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--primary-color);">' + d.total_items + '</div><div style="font-size:13px;color:var(--gray-500);">Total Items</div></div>' +
            '<div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--success-color);">' + d.total_stores + '</div><div style="font-size:13px;color:var(--gray-500);">Stores</div></div>' +
            '<div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--warning-color);">' + d.low_stock_items + '</div><div style="font-size:13px;color:var(--gray-500);">Low Stock</div></div>' +
            '<div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--danger-color);">' + d.expiring_items + '</div><div style="font-size:13px;color:var(--gray-500);">Expiring (30d)</div></div>' +
            '<div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--gray-900);">' + d.total_suppliers + '</div><div style="font-size:13px;color:var(--gray-500);">Suppliers</div></div>' +
            '<div style="padding:16px;background:var(--gray-50);border-radius:8px;text-align:center;"><div style="font-size:24px;font-weight:700;color:var(--primary-color);">RWF ' + (d.stock_value ? d.stock_value.toLocaleString() : '0') + '</div><div style="font-size:13px;color:var(--gray-500);">Stock Value</div></div>' +
        '</div>';
    } catch(e) { console.error('loadDashboard:', e); sEl.innerHTML = ''; }
}

// ========== CENTRAL STORE / STOCK TABLE ==========
async function loadStock() {
    const tbody = document.getElementById('stock-tbody');
    const empty = document.getElementById('stock-empty');
    const header = document.getElementById('stock-header');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    try {
        const r = await fetch(invAPI + 'stock' + (window._storeFilter ? '&store_id=' + window._storeFilter : ''));
        const j = await r.json();
        if (!j.success || !j.data || !j.data.length) {
            if (empty) empty.style.display = 'block';
            if (header) header.style.display = 'none';
            tbody.innerHTML = '';
            return;
        }
        if (empty) empty.style.display = 'none';
        if (header) header.style.display = 'block';
        tbody.innerHTML = j.data.map(sb => {
            const isLow = sb.quantity <= sb.reorder_level && sb.reorder_level > 0;
            const isExpired = sb.expiry_date && sb.expiry_date < new Date().toISOString().split('T')[0];
            return '<tr>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);font-family:monospace;font-size:12px;color:var(--primary-color);">' + escHtml(sb.item_code) + '</td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);font-weight:500;">' + escHtml(sb.item_name) + '</td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);font-size:12px;">' + escHtml(sb.store_name) + '</td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);font-size:12px;">' + escHtml(sb.batch_number) + '</td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);text-align:center;font-weight:600;">' + sb.quantity + '</td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);text-align:right;">' + (sb.unit_cost ? Number(sb.unit_cost).toLocaleString() : '0') + '</td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);text-align:right;font-weight:600;">' + (sb.selling_price ? Number(sb.selling_price).toLocaleString() : '0') + '</td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);font-size:12px;">' + (sb.expiry_date||'-') + '</td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);text-align:center;"><span style="padding:2px 6px;border-radius:4px;font-size:11px;font-weight:500;background:' + (isExpired ? '#fee2e2' : isLow ? '#fef3c7' : '#d1fae5') + ';color:' + (isExpired ? '#991b1b' : isLow ? '#92400e' : '#065f46') + ';">' + (isExpired ? 'Expired' : isLow ? 'Low' : 'OK') + '</span></td>' +
                '<td style="padding:8px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="csViewPrices('+sb.item_id+')" style="padding:3px 8px;background:#7c3aed;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;"><i class="fas fa-tags"></i></button></td>' +
                '</tr>';
        }).join('');
        // Update empty description
        if (document.getElementById('stock-count')) {
            document.getElementById('stock-count').textContent = j.data.length + ' records found';
        }
    } catch(e) { console.error('loadStock:', e); tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:40px;color:var(--danger-color);">Error loading stock</td></tr>'; }
}

function adjustStock(id, qty) {
    if (!invIsAdmin) {
        showToast('Permission denied: Only administrators can adjust stock.', 'error');
        return;
    }
    const newQty = prompt('Enter new quantity for batch #' + id + ' (current: ' + qty + '):', qty);
    if (newQty === null) return;
    fetch(invAPI + 'adjust_stock&id=' + id, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({new_quantity: parseFloat(newQty)})
    }).then(r => r.json()).then(j => {
        if (j.success) { showToast('Stock adjusted!'); loadStock(); }
        else showToast(j.message, 'error');
    }).catch(e => showToast('Error adjusting stock', 'error'));
}

let csSearchTimer;
function csSearchDebounce(){clearTimeout(csSearchTimer);csSearchTimer=setTimeout(loadStock,350);}

async function csViewPrices(itemId){
  try{
    const r=await fetch(invAPI+'item_insurance_prices&item_id='+itemId);
    const j=await r.json();
    if(!j.success||!j.data||!j.data.length){alert('No insurance prices set for this item.');return;}
    let html='<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">Insurance</th><th style="padding:8px;text-align:right;">Price</th><th style="padding:8px;text-align:center;">Type</th><th style="padding:8px;text-align:right;">Add. Sell Price</th><th style="padding:8px;text-align:right;">Patient Topup</th></tr></thead><tbody>';
    j.data.forEach(p=>{
      const isExc=p.is_exceptional==1;
      html+='<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 8px;">'+escHtml(p.insurance_name||'ID:'+p.insurance_id)+'</td><td style="padding:6px 8px;text-align:right;font-weight:600;">'+Number(p.price||0).toLocaleString()+'</td><td style="padding:6px 8px;text-align:center;"><span style="padding:2px 6px;border-radius:8px;font-size:10px;background:'+(isExc?'#fef3c7':'#d1fae5')+';color:'+(isExc?'#92400e':'#065f46')+';">'+(isExc?'Exceptional':'Normal')+'</span></td><td style="padding:6px 8px;text-align:right;">'+(isExc?Number(p.additional_insurance_selling_price||0).toLocaleString():'—')+'</td><td style="padding:6px 8px;text-align:right;color:#dc2626;">'+(isExc?Number(p.patient_topup||0).toLocaleString()+' RWF':'—')+'</td></tr>';
    });
    html+='</tbody></table>';
    const modal=document.createElement('div');modal.id='cs-prices-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
    modal.innerHTML='<div style="background:white;border-radius:12px;width:550px;max-width:95vw;padding:24px;max-height:80vh;overflow-y:auto;"><div style="display:flex;justify-content:space-between;margin-bottom:12px;"><h3 style="font-size:15px;font-weight:600;"><i class="fas fa-tags" style="color:#7c3aed;"></i> Insurance Prices</h3><button onclick="document.getElementById(\'cs-prices-modal\').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>'+html+'</div>';
    document.body.appendChild(modal);
  }catch(e){alert('Error loading prices');}
}

// ========== ITEMS MASTER ==========
let itemsMasterData = [];
let itemsFilterTimer;
let itemsCurrentPage = 1;
const itemsPageSize = 20;
let itemsFilteredData = [];

function itemsFilterDebounce(){clearTimeout(itemsFilterTimer);itemsFilterTimer=setTimeout(()=>{itemsCurrentPage=1;itemsApplyFilter();},300);}

function itemsApplyFilter(){
  itemsCurrentPage = 1;
  const q = (document.getElementById('items-search')?.value||'').toLowerCase();
  const cat = document.getElementById('items-filter-category')?.value||'';
  const type = document.getElementById('items-filter-type')?.value||'';
  const status = document.getElementById('items-filter-status')?.value||'';
  let filtered = itemsMasterData;
  if(itemsLowOnly) filtered = filtered.filter(i => (parseFloat(i.total_stock)||0) < 10);
  if(q){
    filtered = filtered.filter(i => {
      return (i.name||'').toLowerCase().includes(q) ||
             (i.item_code||'').toLowerCase().includes(q) ||
             (i.generic_name||'').toLowerCase().includes(q);
    });
  }
  if(cat) filtered = filtered.filter(i => String(i.category_id) === cat);
  if(type === 'medication') filtered = filtered.filter(i => i.is_medication == 1);
  if(type === 'general') filtered = filtered.filter(i => !i.is_medication || i.is_medication == 0);
  if(status === 'active') filtered = filtered.filter(i => i.is_active == 1);
  if(status === 'inactive') filtered = filtered.filter(i => !i.is_active || i.is_active == 0);
  itemsFilteredData = filtered;
  itemsRenderTable();
}

function itemsGoPage(page){
  const totalPages = Math.ceil(itemsFilteredData.length / itemsPageSize);
  if(page < 1 || page > totalPages) return;
  itemsCurrentPage = page;
  itemsRenderTable();
}

function itemsRenderTable(){
  const data = itemsFilteredData;
  const tbody = document.getElementById('items-tbody');
  const empty = document.getElementById('items-empty');
  const countEl = document.getElementById('items-count');
  const pgEl = document.getElementById('items-pagination');
  if(!tbody) return;
  if(!data||!data.length){
    if(itemsMasterData.length === 0 && empty) empty.style.display = 'block';
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--gray-400);"><i class="fas fa-search" style="font-size:20px;display:block;margin-bottom:8px;"></i>No items match your filters.</td></tr>';
    if(countEl) countEl.textContent = '0 items';
    if(pgEl) pgEl.innerHTML = '';
    return;
  }
  if(empty) empty.style.display = 'none';

  // Pagination calc
  const totalPages = Math.ceil(data.length / itemsPageSize);
  if(itemsCurrentPage > totalPages) itemsCurrentPage = totalPages;
  if(itemsCurrentPage < 1) itemsCurrentPage = 1;
  const start = (itemsCurrentPage - 1) * itemsPageSize;
  const end = start + itemsPageSize;
  const pageItems = data.slice(start, end);

  if(countEl) countEl.textContent = 'Showing ' + (start+1) + '–' + Math.min(end, data.length) + ' of ' + data.length + ' item' + (data.length!==1?'s':'');

  tbody.innerHTML = pageItems.map(i => {
    const totalStock = parseFloat(i.total_stock) || 0;
    const formattedStock = Number.isInteger(totalStock) ? totalStock.toLocaleString() : totalStock.toFixed(2);
    const safeItemName = (i.name || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    const stockBtn = totalStock > 0 ? 
      `<button onclick="viewItemStock(${i.id},'${safeItemName}',${totalStock})" style="padding:3px 8px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;cursor:pointer;font-size:12px;color:#1e40af;font-weight:600;display:inline-flex;align-items:center;gap:4px;" title="View stock batches and locations"><i class="fas fa-boxes"></i> ${formattedStock}</button>` :
      `<button onclick="viewItemStock(${i.id},'${safeItemName}',0)" style="padding:3px 8px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;font-size:11px;color:#9ca3af;font-weight:500;" title="View stock details">0</button>`;
    
    return '<tr>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(i.item_code) + '</td>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);font-weight:500;">' + escHtml(i.name) + '</td>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(i.generic_name||'-') + '</td>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(i.category_name) + '</td>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(i.uom_abbr||'-') + '</td>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);text-align:center;">' + stockBtn + '</td>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + (i.is_medication ? '<span style="color:var(--primary-color);"><i class="fas fa-prescription"></i> Medication</span>' : '<span style="color:var(--gray-400);">General</span>') + '</td>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);"><span style="padding:4px 8px;border-radius:4px;font-size:12px;font-weight:500;background:' + (i.is_active ? '#d1fae5' : '#fee2e2') + ';color:' + (i.is_active ? '#065f46' : '#991b1b') + ';">' + (i.is_active ? 'Active' : 'Inactive') + '</span></td>' +
    '<td style="padding:12px;border-bottom:1px solid var(--gray-100);text-align:center;"><button onclick="editItem('+i.id+')" style="padding:4px 10px;background:var(--primary-color);color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;margin-right:4px;" title="Edit Item"><i class="fas fa-edit"></i></button>' +
    '<button onclick="deleteItem(' + i.id + ',\'' + safeItemName + '\')" style="padding:4px 10px;background:#fee2e2;border:none;border-radius:4px;cursor:pointer;font-size:12px;color:#991b1b;" title="Delete Item"><i class="fas fa-trash"></i></button></td>' +
    '</tr>';
  }).join('');

  // Render pagination
  if(pgEl){
    if(totalPages <= 1){ pgEl.innerHTML = ''; return; }
    let btns = '';
    btns += `<button onclick="itemsGoPage(${itemsCurrentPage-1})" ${itemsCurrentPage===1?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${itemsCurrentPage===1?'var(--gray-100)':'white'};color:${itemsCurrentPage===1?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${itemsCurrentPage===1?'not-allowed':'pointer'};font-size:12px;font-weight:500;"><i class="fas fa-chevron-left"></i> Prev</button>`;
    let startP = Math.max(1, itemsCurrentPage - 3);
    let endP = Math.min(totalPages, startP + 6);
    if(endP - startP < 6) startP = Math.max(1, endP - 6);
    for(let p=startP;p<=endP;p++){
      const active = p===itemsCurrentPage;
      btns += `<button onclick="itemsGoPage(${p})" style="padding:6px 10px;border:1px solid ${active?'var(--primary-color)':'var(--gray-200)'};background:${active?'var(--primary-color)':'white'};color:${active?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:12px;font-weight:${active?'600':'400'};">${p}</button>`;
    }
    btns += `<button onclick="itemsGoPage(${itemsCurrentPage+1})" ${itemsCurrentPage===totalPages?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${itemsCurrentPage===totalPages?'var(--gray-100)':'white'};color:${itemsCurrentPage===totalPages?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${itemsCurrentPage===totalPages?'not-allowed':'pointer'};font-size:12px;font-weight:500;">Next <i class="fas fa-chevron-right"></i></button>`;
    pgEl.innerHTML = `<span style="font-size:12px;color:var(--gray-500);">Page ${itemsCurrentPage} of ${totalPages}</span><div style="display:flex;gap:4px;">${btns}</div>`;
  }
}

function itemsPopulateCategoryFilter(){
  const sel = document.getElementById('items-filter-category');
  if(!sel) return;
  const cats = {};
  itemsMasterData.forEach(i => { if(i.category_id && i.category_name) cats[i.category_id] = i.category_name; });
  const sorted = Object.entries(cats).sort((a,b) => a[1].localeCompare(b[1]));
  sel.innerHTML = '<option value="">All Categories</option>' + sorted.map(([id,name]) => '<option value="'+id+'">'+escHtml(name)+'</option>').join('');
}

async function loadItemsMaster() {
    const tbody = document.getElementById('items-tbody');
    const empty = document.getElementById('items-empty');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    try {
        const r = await fetch(invAPI + 'items&_v=' + Date.now());
        const j = await r.json();
        if (!j.success || !j.data || !j.data.length) {
            if (empty) empty.style.display = 'block';
            tbody.innerHTML = '';
            itemsMasterData = [];
            return;
        }
        if (empty) empty.style.display = 'none';
        itemsMasterData = j.data;
        itemsPopulateCategoryFilter();
        const _urlSearch = new URLSearchParams(window.location.search).get('search');
        if (_urlSearch && document.getElementById('items-search')) {
            document.getElementById('items-search').value = _urlSearch;
        }
        itemsApplyFilter();
        zfRender();
    } catch(e) { console.error('loadItemsMaster:', e); tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--danger-color);">Error loading items</td></tr>'; }
}

function deleteItem(id, name) {
    if (!confirm('Delete item "' + name + '"? (soft delete)')) return;
    fetch(invAPI + 'delete_item&id=' + id, {method: 'DELETE'})
        .then(r => r.json()).then(j => {
            if (j.success) { showToast('Item deactivated'); loadItemsMaster(); }
            else showToast(j.message, 'error');
        }).catch(e => showToast('Error', 'error'));
}

let curItemStockData = null;

async function viewItemStock(itemId, itemName, totalQty) {
    try {
        const itemObj = (typeof itemsMasterData !== 'undefined' && itemsMasterData.length) ? itemsMasterData.find(x => x.id == itemId) : null;
        const itemCode = itemObj ? itemObj.item_code : '';
        
        const r = await fetch(invAPI + 'item_stock_details&item_id=' + itemId + '&_v=' + Date.now());
        const j = await r.json();
        if (!j.success) { showToast(j.message || 'Error fetching stock data', 'error'); return; }
        
        const stocks = j.data || [];
        const stores = j.stores || [];
        curItemStockData = { itemId, itemName, itemCode, stocks, stores };
        
        renderItemStockModal();
    } catch(e) { 
        showToast('Error loading stock', 'error'); 
        console.error('viewItemStock:', e); 
    }
}

function renderItemStockModal() {
    if (!curItemStockData) return;
    const { itemId, itemName, itemCode, stocks, stores } = curItemStockData;
    
    const activeBatches = stocks.filter(s => parseFloat(s.quantity) > 0);
    const depletedBatches = stocks.filter(s => !s.quantity || parseFloat(s.quantity) <= 0);
    const liveTotal = activeBatches.reduce((sum, s) => sum + (parseFloat(s.quantity) || 0), 0);
    const formattedTotal = Number.isInteger(liveTotal) ? liveTotal.toLocaleString() : liveTotal.toFixed(2);
    const safeItemName = (itemName || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    const safeItemCode = (itemCode || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');

    // Per-store breakdown badges
    let storePills = '';
    if (stores && stores.length) {
        storePills = stores.map(st => {
            const stStock = parseFloat(st.current_stock) || 0;
            const hasStock = stStock > 0;
            const bg = hasStock ? '#f0fdf4' : '#f8fafc';
            const border = hasStock ? '#bbf7d0' : 'var(--gray-200)';
            const color = hasStock ? '#166534' : 'var(--gray-500)';
            const stFmt = Number.isInteger(stStock) ? stStock.toLocaleString() : stStock.toFixed(2);
            return `<div style="padding:6px 12px;background:${bg};border:1px solid ${border};border-radius:6px;display:flex;align-items:center;gap:6px;font-size:12px;">
                <i class="fas fa-warehouse" style="color:${hasStock ? '#16a34a' : 'var(--gray-400)'};"></i>
                <span style="font-weight:600;color:var(--gray-800);">${escHtml(st.store_name)}:</span>
                <strong style="color:${color};font-size:13px;">${stFmt}</strong>
                ${!hasStock && st.total_batches_count > 0 ? `<span style="font-size:10px;color:var(--gray-400);">(${st.total_batches_count} depleted)</span>` : ''}
            </div>`;
        }).join('');
    }

    let html = `
    <div style="margin-bottom:16px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
            <div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <h4 style="font-size:17px;font-weight:700;color:var(--gray-800);margin:0;">${escHtml(itemName)}</h4>
                    ${itemCode ? `<span style="font-size:11px;background:var(--gray-100);color:var(--primary-color);padding:2px 8px;border-radius:4px;font-family:monospace;font-weight:600;">${escHtml(itemCode)}</span>` : ''}
                </div>
                <div style="font-size:13px;color:var(--gray-600);display:flex;align-items:center;gap:12px;margin-top:6px;flex-wrap:wrap;">
                    <span>Total Stock: <strong style="color:var(--primary-color);font-size:16px;">${formattedTotal}</strong></span>
                    <span>•</span>
                    <span>Active Batches: <strong style="color:#16a34a;">${activeBatches.length}</strong></span>
                    ${depletedBatches.length > 0 ? `<span>•</span><span>Depleted Batches: <strong style="color:var(--gray-500);">${depletedBatches.length}</strong></span>` : ''}
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                ${invIsAdmin ? `
                <button type="button" onclick="openAdjustStockForItem(${itemId}, '${safeItemName}')" 
                    style="padding:7px 14px;background:#4f46e5;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    <i class="fas fa-balance-scale"></i> Adjust Quantity
                </button>` : ''}
                <button type="button" onclick="srOpenMovementModal(${itemId}, '${safeItemName}', '${safeItemCode}')" 
                    style="padding:7px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                    <i class="fas fa-history"></i> Full Movement History
                </button>
            </div>
        </div>

        <!-- Store Stock Breakdown -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;margin-bottom:14px;">
            ${storePills}
        </div>

        <!-- Filter bar inside modal -->
        <div style="background:var(--gray-50);padding:10px 12px;border-radius:8px;border:1px solid var(--gray-200);display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px;">
            <div style="min-width:140px;">
                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--gray-600);">Filter by Store</label>
                <select id="modal-stock-store-filter" onchange="filterItemStockModal()" style="width:100%;padding:6px 8px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;background:white;">
                    <option value="">All Stores</option>
                    ${(stores||[]).map(s => `<option value="${s.store_id}">${escHtml(s.store_name)}</option>`).join('')}
                </select>
            </div>
            <div style="min-width:150px;">
                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--gray-600);">Batch Status</label>
                <select id="modal-stock-status-filter" onchange="filterItemStockModal()" style="width:100%;padding:6px 8px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;background:white;">
                    <option value="active">Active Batches (Qty > 0)</option>
                    <option value="all">All Batches (Active & Depleted)</option>
                    <option value="depleted">Depleted Batches (0 Qty)</option>
                </select>
            </div>
            <div style="flex:1;min-width:160px;">
                <label style="font-size:11px;font-weight:600;display:block;margin-bottom:2px;color:var(--gray-600);">Search Batches</label>
                <input type="text" id="modal-stock-search" placeholder="Search batch #, store..." oninput="filterItemStockModal()" style="width:100%;padding:6px 8px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;background:white;">
            </div>
        </div>
    </div>

    <div id="modal-stock-table-container"></div>
    `;

    showModal('Stock Details & Locations', html, '850px');
    filterItemStockModal();
}

function filterItemStockModal() {
    if (!curItemStockData) return;
    const { itemId, itemName, itemCode, stocks } = curItemStockData;
    const container = document.getElementById('modal-stock-table-container');
    if (!container) return;

    const storeFilter = document.getElementById('modal-stock-store-filter')?.value || '';
    const statusFilter = document.getElementById('modal-stock-status-filter')?.value || 'active';
    const searchQ = (document.getElementById('modal-stock-search')?.value || '').trim().toLowerCase();

    let filtered = stocks;
    if (storeFilter) {
        filtered = filtered.filter(s => String(s.store_id) === String(storeFilter));
    }
    if (statusFilter === 'active') {
        filtered = filtered.filter(s => parseFloat(s.quantity) > 0);
    } else if (statusFilter === 'depleted') {
        filtered = filtered.filter(s => !s.quantity || parseFloat(s.quantity) <= 0);
    }
    if (searchQ) {
        filtered = filtered.filter(s => {
            const b = (s.batch_number || '').toLowerCase();
            const st = (s.store_name || '').toLowerCase();
            return b.includes(searchQ) || st.includes(searchQ);
        });
    }

    if (filtered.length === 0) {
        container.innerHTML = `
            <div style="text-align:center;padding:36px 16px;background:var(--gray-50);border-radius:8px;border:1px dashed var(--gray-200);color:var(--gray-500);margin-top:6px;">
                <i class="fas fa-box-open" style="font-size:28px;display:block;margin-bottom:8px;color:var(--gray-400);"></i>
                No batches match the selected filter.
                ${statusFilter === 'active' && stocks.length > 0 ? `<div style="margin-top:6px;"><button onclick="document.getElementById('modal-stock-status-filter').value='all';filterItemStockModal();" style="padding:4px 10px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;">View all batches (including depleted)</button></div>` : ''}
            </div>
        `;
        return;
    }

    let html = `
    <div style="overflow-x:auto;max-height:55vh;overflow-y:auto;border:1px solid var(--gray-200);border-radius:8px;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--gray-50);position:sticky;top:0;z-index:2;border-bottom:2px solid var(--gray-200);">
                    <th style="padding:10px 12px;text-align:left;color:var(--gray-700);font-weight:600;">Store</th>
                    <th style="padding:10px 12px;text-align:left;color:var(--gray-700);font-weight:600;">Batch #</th>
                    <th style="padding:10px 12px;text-align:right;color:var(--gray-700);font-weight:600;">Qty</th>
                    <th style="padding:10px 12px;text-align:right;color:var(--gray-700);font-weight:600;">Unit Cost</th>
                    <th style="padding:10px 12px;text-align:right;color:var(--gray-700);font-weight:600;">Sell Price</th>
                    <th style="padding:10px 12px;text-align:left;color:var(--gray-700);font-weight:600;">Expiry Date</th>
                    <th style="padding:10px 12px;text-align:center;color:var(--gray-700);font-weight:600;">Status</th>
                    <th style="padding:10px 12px;text-align:center;color:var(--gray-700);font-weight:600;">Action</th>
                </tr>
            </thead>
            <tbody>
    `;

    filtered.forEach(s => {
        const qtyVal = parseFloat(s.quantity) || 0;
        const isDepleted = qtyVal <= 0;
        const expiryDate = s.expiry_date || '';
        const isExpired = expiryDate && new Date(expiryDate) < new Date();
        const expiryColor = isExpired ? '#dc2626' : (expiryDate ? '#059669' : 'var(--gray-500)');
        const expiryText = expiryDate || 'No expiry';
        const formattedQty = Number.isInteger(qtyVal) ? qtyVal.toLocaleString() : qtyVal.toFixed(2);
        const costVal = parseFloat(s.unit_cost) || 0;
        const priceVal = parseFloat(s.selling_price) || 0;
        const safeBatch = (s.batch_number || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const safeStoreName = (s.store_name || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const safeItemName = (itemName || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const safeItemCode = (itemCode || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');

        let statusBadge = '';
        if (isDepleted) {
            statusBadge = '<span style="padding:2px 8px;border-radius:12px;font-size:10px;font-weight:600;background:#f3f4f6;color:#6b7280;">Depleted (0 Qty)</span>';
        } else if (isExpired) {
            statusBadge = '<span style="padding:2px 8px;border-radius:12px;font-size:10px;font-weight:600;background:#fee2e2;color:#991b1b;">Expired</span>';
        } else {
            statusBadge = '<span style="padding:2px 8px;border-radius:12px;font-size:10px;font-weight:600;background:#d1fae5;color:#065f46;">Active</span>';
        }

        html += `
            <tr style="border-bottom:1px solid var(--gray-100);background:${isDepleted ? '#fafafa' : 'white'};">
                <td style="padding:9px 12px;font-weight:600;color:${isDepleted ? 'var(--gray-600)' : 'var(--gray-900)'};">
                    <i class="fas fa-warehouse" style="color:var(--gray-400);margin-right:4px;font-size:10px;"></i>${escHtml(s.store_name || ('Store #' + s.store_id))}
                </td>
                <td style="padding:9px 12px;font-family:monospace;font-size:12px;color:var(--primary-color);">
                    ${s.batch_number ? escHtml(s.batch_number) : '<span style="color:var(--gray-400);">—</span>'}
                </td>
                <td style="padding:9px 12px;text-align:right;font-weight:700;font-family:monospace;font-size:12px;color:${isDepleted ? 'var(--gray-400)' : 'var(--gray-900)'};">
                    ${formattedQty}
                </td>
                <td style="padding:9px 12px;text-align:right;color:var(--gray-600);font-size:11px;">
                    ${costVal > 0 ? costVal.toLocaleString() + ' RWF' : '—'}
                </td>
                <td style="padding:9px 12px;text-align:right;color:var(--gray-600);font-size:11px;">
                    ${priceVal > 0 ? priceVal.toLocaleString() + ' RWF' : '—'}
                </td>
                <td style="padding:9px 12px;color:${expiryColor};font-size:11px;">
                    ${expiryText}
                </td>
                <td style="padding:9px 12px;text-align:center;">
                    ${statusBadge}
                </td>
                <td style="padding:9px 12px;text-align:center;white-space:nowrap;">
                    ${invIsAdmin ? `
                    <button onclick="openAdjustStockForBatch(${s.id}, ${itemId}, '${safeItemName}', '${safeItemCode}', ${s.store_id}, '${safeStoreName}', '${safeBatch}', ${qtyVal}, ${costVal})" style="padding:3px 8px;background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;border-radius:4px;cursor:pointer;font-size:11px;font-weight:600;margin-right:4px;" title="Adjust Quantity & Post Journal Entry">
                        <i class="fas fa-balance-scale"></i> Adjust Qty
                    </button>
                    <button onclick="editStockBatch(${s.id}, '${safeBatch}', '${expiryDate}', ${qtyVal}, ${costVal}, ${itemId}, '${safeItemName}')" style="padding:3px 8px;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;border-radius:4px;cursor:pointer;font-size:11px;font-weight:500;margin-right:4px;" title="Edit Batch Details">
                        <i class="fas fa-edit"></i> Edit
                    </button>` : ''}
                    <button onclick="srOpenMovementModal(${itemId}, '${safeItemName}', '${safeItemCode}', ${s.store_id}, '${safeStoreName}', ${s.id}, '${safeBatch}')" style="padding:3px 8px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:4px;cursor:pointer;font-size:11px;font-weight:500;" title="View Movement History for this batch">
                        <i class="fas fa-history"></i> History
                    </button>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// ========== STOCK ADJUSTMENT (WITH JOURNAL ENTRIES) ==========
let curAdjData = null;

async function openAdjustStockForItem(itemId, itemName) {
    if (!invIsAdmin) {
        showToast('Permission denied: Only administrators can adjust stock.', 'error');
        return;
    }
    try {
        const itemObj = ((typeof itemsMasterData !== 'undefined' && itemsMasterData.length) ? itemsMasterData.find(x => x.id == itemId) : null)
            || ((typeof invTabItems !== 'undefined' && invTabItems) ? invTabItems.find(x => x.id == itemId) : null);
        const name = itemName || (itemObj ? itemObj.name : 'Item #' + itemId);
        const code = itemObj ? (itemObj.item_code || '') : '';
        const defaultCost = itemObj ? (parseFloat(itemObj.unit_price) || 0) : 0;

        showToast('Loading stock details...', 'warning');

        // Fetch item stock batches
        const r = await fetch(invAPI + 'item_stock_details&item_id=' + itemId + '&_v=' + Date.now());
        const j = await r.json();
        const batches = (j.success && j.data) ? j.data : [];

        // Fetch stores
        let stores = [];
        try {
            const sr = await fetch(invAPI + 'stores');
            const sj = await sr.json();
            if (sj.success) stores = sj.data || [];
        } catch(e) {}
        if (!stores.length && j.stores) {
            stores = j.stores.map(s => ({ id: s.store_id, name: s.store_name, code: s.store_code }));
        }

        // Fetch candidate chart of accounts for adjustments
        let adjAccounts = [];
        try {
            const ar = await fetch(invAPI + 'adjustment_accounts');
            const aj = await ar.json();
            if (aj.success) adjAccounts = aj.data || [];
        } catch(e) {}

        const initialBatchId = batches.length > 0 ? batches[0].id : 'new';
        const initialStoreId = batches.length > 0 ? batches[0].store_id : (stores.length > 0 ? stores[0].id : '');

        curAdjData = {
            itemId: itemId,
            itemName: name,
            itemCode: code,
            defaultCost: defaultCost,
            batches: batches,
            stores: stores,
            adjAccounts: adjAccounts,
            selectedBatchId: initialBatchId,
            selectedStoreId: initialStoreId
        };

        renderAdjustStockModal();
    } catch(err) {
        console.error('openAdjustStockForItem:', err);
        showToast('Error loading adjustment data: ' + err.message, 'error');
    }
}

async function openAdjustStockForBatch(batchId, itemId, itemName, itemCode, storeId, storeName, batchNumber, currentQty, unitCost) {
    if (!invIsAdmin) {
        showToast('Permission denied: Only administrators can adjust stock.', 'error');
        return;
    }
    try {
        let stores = [];
        try {
            const sr = await fetch(invAPI + 'stores');
            const sj = await sr.json();
            if (sj.success) stores = sj.data || [];
        } catch(e) {}

        let batches = [];
        try {
            const r = await fetch(invAPI + 'item_stock_details&item_id=' + itemId + '&_v=' + Date.now());
            const j = await r.json();
            if (j.success) batches = j.data || [];
        } catch(e) {}

        let adjAccounts = [];
        try {
            const ar = await fetch(invAPI + 'adjustment_accounts');
            const aj = await ar.json();
            if (aj.success) adjAccounts = aj.data || [];
        } catch(e) {}

        curAdjData = {
            itemId: itemId,
            itemName: itemName || '',
            itemCode: itemCode || '',
            defaultCost: unitCost || 0,
            batches: batches,
            stores: stores,
            adjAccounts: adjAccounts,
            selectedBatchId: batchId,
            selectedStoreId: storeId
        };

        renderAdjustStockModal();
    } catch(err) {
        console.error('openAdjustStockForBatch:', err);
        showToast('Error loading batch data: ' + err.message, 'error');
    }
}

function renderAdjustStockModal() {
    if (!curAdjData) return;
    const { itemId, itemName, itemCode, defaultCost, batches, stores, selectedBatchId, selectedStoreId, adjAccounts } = curAdjData;

    // Batch options
    let batchOpts = '';
    if (batches && batches.length > 0) {
        batches.forEach(b => {
            const bQty = parseFloat(b.quantity) || 0;
            const bCost = parseFloat(b.unit_cost) || defaultCost;
            const bFmtQty = Number.isInteger(bQty) ? bQty.toLocaleString() : bQty.toFixed(2);
            const isSel = String(b.id) === String(selectedBatchId);
            batchOpts += `<option value="${b.id}" ${isSel ? 'selected' : ''}>${escHtml(b.store_name)} — Batch #${escHtml(b.batch_number||'N/A')} (Current: ${bFmtQty}, Cost: ${bCost.toLocaleString()} RWF)</option>`;
        });
        batchOpts += `<option value="new" ${selectedBatchId === 'new' ? 'selected' : ''}>+ Create New Batch / Add Stock to Store</option>`;
    } else {
        batchOpts = `<option value="new" selected>+ Initial Stock / New Batch (No batches yet)</option>`;
    }

    // Store options
    const storeOpts = (stores || []).map(s => {
        const isSel = String(s.id) === String(selectedStoreId);
        return `<option value="${s.id}" ${isSel ? 'selected' : ''}>${escHtml(s.name)} (${escHtml(s.code||'')})</option>`;
    }).join('');

    const html = `
    <div style="font-size:13px;color:var(--gray-800);">
        <!-- Item Overview Header -->
        <div style="background:#f8fafc;border:1px solid var(--gray-200);border-radius:8px;padding:12px 14px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div>
                <div style="font-size:15px;font-weight:700;color:var(--gray-900);display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-boxes" style="color:var(--primary-color);"></i>
                    <span>${escHtml(itemName)}</span>
                    ${itemCode ? `<span style="font-size:11px;background:var(--gray-200);color:var(--gray-700);padding:2px 6px;border-radius:4px;font-family:monospace;">${escHtml(itemCode)}</span>` : ''}
                </div>
                <div style="font-size:12px;color:var(--gray-500);margin-top:2px;">
                    Item ID #${itemId} &bull; Adjusting inventory balance with automated journal entry posting
                </div>
            </div>
            <div style="font-size:11px;background:#eef2ff;color:#3730a3;border:1px solid #c7d2fe;padding:4px 8px;border-radius:6px;font-weight:600;">
                <i class="fas fa-book"></i> General Ledger Linked
            </div>
        </div>

        <form id="stock-adjustment-form" onsubmit="event.preventDefault(); submitStockAdjustment();">
            <!-- Batch Selection -->
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">
                    Select Stock Batch <span style="color:var(--danger-color);">*</span>
                </label>
                <select id="adj-batch-select" onchange="adjBatchChanged()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
                    ${batchOpts}
                </select>
            </div>

            <!-- Store Selection -->
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">
                    Store / Inventory Location <span style="color:var(--danger-color);">*</span>
                </label>
                <select id="adj-store-select" onchange="recalcAdjustStock()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
                    ${storeOpts}
                </select>
                <div id="adj-store-note" style="font-size:11px;color:var(--gray-500);margin-top:3px;">Each store is linked to an asset account in the Chart of Accounts.</div>
            </div>

            <!-- New Batch Fields (shown only if 'new' is selected) -->
            <div id="adj-new-batch-fields" style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:14px;">
                <div style="font-size:12px;font-weight:700;color:#166534;margin-bottom:8px;"><i class="fas fa-plus-circle"></i> New Batch Details</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;color:#166534;">Batch Number</label>
                        <input type="text" id="adj-new-batch-no" placeholder="e.g. BATCH-${new Date().toISOString().slice(0,10).replace(/-/g,'')}" style="width:100%;padding:8px 10px;border:1px solid #86efac;border-radius:6px;font-size:12px;font-family:monospace;background:white;">
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;color:#166534;">Expiry Date</label>
                        <input type="date" id="adj-new-expiry" style="width:100%;padding:8px 10px;border:1px solid #86efac;border-radius:6px;font-size:12px;background:white;">
                    </div>
                </div>
            </div>

            <!-- Quantity & Cost Grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">Current Quantity</label>
                    <div id="adj-current-qty-display" style="padding:9px 12px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:6px;font-weight:700;font-size:14px;color:var(--gray-800);text-align:right;">0</div>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">
                        New Quantity <span style="color:var(--danger-color);">*</span>
                    </label>
                    <input type="number" id="adj-new-qty" step="any" min="0" oninput="recalcAdjustStock()" style="width:100%;padding:9px 12px;border:2px solid var(--primary-color);border-radius:6px;font-size:14px;font-weight:700;text-align:right;background:white;" placeholder="0" required>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">
                        Unit Cost (RWF) <span style="color:var(--danger-color);">*</span>
                    </label>
                    <input type="number" id="adj-cost" step="any" min="0" oninput="recalcAdjustStock()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:14px;font-weight:600;text-align:right;background:white;" placeholder="0" required>
                </div>
            </div>

            <!-- Calculated Variance Indicator -->
            <div id="adj-diff-banner" style="padding:10px 14px;border-radius:8px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;background:#f3f4f6;border:1px solid var(--gray-200);">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-weight:600;font-size:12px;color:var(--gray-700);">Quantity Difference:</span>
                    <span id="adj-diff-text" style="font-weight:700;font-size:13px;padding:2px 8px;border-radius:4px;background:white;">0 units</span>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:11px;color:var(--gray-500);margin-right:4px;">Adjustment Value:</span>
                    <strong id="adj-value-text" style="font-size:14px;color:var(--gray-900);">0 RWF</strong>
                </div>
            </div>

            <!-- Adjustment Reason and Date -->
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">
                        Adjustment Reason <span style="color:var(--danger-color);">*</span>
                    </label>
                    <select id="adj-reason-select" onchange="adjReasonChanged()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
                        <option value="Physical count reconciliation">Physical count reconciliation (Inventory Audit)</option>
                        <option value="Damaged / Spoiled stock">Damaged / Spoiled stock (Reduction)</option>
                        <option value="Expired stock removal">Expired stock removal (Write-off)</option>
                        <option value="Lost / Missing inventory">Lost / Missing inventory (Shrinkage)</option>
                        <option value="Stock found / Surplus">Stock found / Surplus (Addition)</option>
                        <option value="Supplier bonus / Sample received">Supplier bonus / Sample received (Addition)</option>
                        <option value="Initial / Opening stock correction">Initial / Opening stock correction</option>
                        <option value="Other adjustment">Other / Custom reason</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">
                        Entry Date <span style="color:var(--danger-color);">*</span>
                    </label>
                    <input type="date" id="adj-entry-date" value="${new Date().toISOString().slice(0,10)}" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;" required>
                </div>
            </div>

            <!-- Notes -->
            <div style="margin-bottom:14px;">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">Audit Notes / Explanation</label>
                <input type="text" id="adj-notes" placeholder="Optional details, authorization note, or batch references..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;">
            </div>

            <!-- Accounting & Journal Entries Impact Preview Box -->
            <div id="adj-journal-preview-box" style="background:#f8fafc;border:1px solid #cbd5e1;border-radius:8px;padding:14px;margin-bottom:18px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <span style="font-size:12px;font-weight:700;color:var(--gray-800);display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-invoice-dollar" style="color:var(--primary-color);"></i>
                        <span>Double-Entry Accounting Impact</span>
                    </span>
                    <span id="adj-journal-status-badge" style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;background:#e2e8f0;color:#475569;">Balanced Entry</span>
                </div>

                <div id="adj-journal-lines" style="font-size:12px;display:flex;flex-direction:column;gap:8px;">
                    <!-- Dynamically populated by recalcAdjustStock() -->
                </div>

                <!-- Account override selector -->
                <div style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--gray-200);">
                    <label style="display:block;font-size:11px;font-weight:600;color:var(--gray-600);margin-bottom:3px;">
                        Offsetting Account (General Ledger)
                    </label>
                    <select id="adj-offset-account-select" onchange="recalcAdjustStock()" style="width:100%;padding:7px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;background:white;">
                        <option value="">Auto-resolve from adjustment reason & type (Recommended)</option>
                    </select>
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div style="display:flex;gap:10px;justify-content:flex-end;align-items:center;">
                <button type="button" onclick="adjClose()" style="padding:10px 18px;background:var(--gray-100);border:1px solid var(--gray-300);border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;color:var(--gray-700);">
                    Cancel
                </button>
                <button type="submit" id="adj-submit-btn" style="padding:10px 22px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 2px 6px rgba(0,0,0,0.15);">
                    <i class="fas fa-check-circle"></i> <span>Confirm & Post Adjustment</span>
                </button>
            </div>
        </form>
    </div>
    `;

    const adjPanel = document.getElementById('adj-panel');
    if (adjPanel) {
        // Adjust Stock tab: render inline instead of a modal
        const gm = document.getElementById('gen-modal'); if (gm) gm.innerHTML = '';
        itemsTab('adjust');
        const pk = document.getElementById('adj-pick'); if (pk) pk.value = (itemCode ? itemCode + ' - ' : '') + itemName;
        adjPanel.innerHTML = html;
    } else {
        showModal('Adjust Stock Quantity & Post Journal Entry', html, '680px');
    }
    
    // Trigger initial batch selection setup and populate account dropdown
    adjBatchChanged();
    populateAdjAccountsDropdown();
}

// Closes the adjust modal, or clears the inline panel in the Adjust Stock tab
function adjClose() {
    const m = document.getElementById('gen-modal');
    if (m) m.innerHTML = '';
    const panel = document.getElementById('adj-panel');
    if (panel) panel.innerHTML = '';
    const pk = document.getElementById('adj-pick');
    if (pk) pk.value = '';
}

function populateAdjAccountsDropdown() {
    const sel = document.getElementById('adj-offset-account-select');
    if (!sel || !curAdjData || !curAdjData.adjAccounts) return;
    
    const accs = curAdjData.adjAccounts;
    let opts = '<option value="">Auto-resolve from adjustment reason & type (Recommended)</option>';
    
    const groups = { 'expense': 'Expense / Loss Accounts', 'income': 'Income / Revenue Accounts', 'equity': 'Equity Accounts' };
    
    Object.keys(groups).forEach(type => {
        const list = accs.filter(a => a.account_type === type);
        if (list.length > 0) {
            opts += `<optgroup label="${groups[type]}">`;
            list.forEach(a => {
                opts += `<option value="${a.id}">[${escHtml(a.account_code)}] ${escHtml(a.account_name)}</option>`;
            });
            opts += `</optgroup>`;
        }
    });
    
    sel.innerHTML = opts;
}

function adjBatchChanged() {
    if (!curAdjData) return;
    const batchSel = document.getElementById('adj-batch-select');
    const storeSel = document.getElementById('adj-store-select');
    const newBatchFields = document.getElementById('adj-new-batch-fields');
    const curQtyEl = document.getElementById('adj-current-qty-display');
    const newQtyInput = document.getElementById('adj-new-qty');
    const costInput = document.getElementById('adj-cost');
    if (!batchSel || !storeSel) return;

    const val = batchSel.value;
    if (val === 'new') {
        storeSel.disabled = false;
        if (newBatchFields) newBatchFields.style.display = 'block';
        if (curQtyEl) { curQtyEl.textContent = '0'; curQtyEl.dataset.qty = '0'; }
        if (costInput) costInput.value = curAdjData.defaultCost || '';
        if (newQtyInput && !newQtyInput.value) newQtyInput.value = '';
    } else {
        const batch = curAdjData.batches.find(b => String(b.id) === String(val));
        if (batch) {
            storeSel.value = batch.store_id;
            storeSel.disabled = true;
            if (newBatchFields) newBatchFields.style.display = 'none';
            const bQty = parseFloat(batch.quantity) || 0;
            const bCost = parseFloat(batch.unit_cost) || curAdjData.defaultCost || 0;
            if (curQtyEl) {
                curQtyEl.textContent = Number.isInteger(bQty) ? bQty.toLocaleString() : bQty.toFixed(2);
                curQtyEl.dataset.qty = String(bQty);
            }
            if (costInput) costInput.value = bCost;
            if (newQtyInput) newQtyInput.value = bQty;
        }
    }
    recalcAdjustStock();
}

function adjReasonChanged() {
    recalcAdjustStock();
}

function recalcAdjustStock() {
    const curQtyEl = document.getElementById('adj-current-qty-display');
    const newQtyInput = document.getElementById('adj-new-qty');
    const costInput = document.getElementById('adj-cost');
    const diffText = document.getElementById('adj-diff-text');
    const valText = document.getElementById('adj-value-text');
    const banner = document.getElementById('adj-diff-banner');
    const linesContainer = document.getElementById('adj-journal-lines');
    const submitBtn = document.getElementById('adj-submit-btn');
    const storeSel = document.getElementById('adj-store-select');
    const reasonSel = document.getElementById('adj-reason-select');
    const offsetAccSel = document.getElementById('adj-offset-account-select');

    if (!curQtyEl || !newQtyInput || !costInput) return;

    const oldQty = parseFloat(curQtyEl.dataset.qty || 0);
    const newQtyStr = newQtyInput.value;
    const newQty = newQtyStr === '' ? 0 : parseFloat(newQtyStr);
    const cost = parseFloat(costInput.value) || 0;
    const storeName = storeSel ? storeSel.options[storeSel.selectedIndex]?.text || 'Store Account' : 'Store Account';
    const reason = reasonSel ? reasonSel.value : '';

    if (isNaN(newQty) || newQty < 0) {
        if (banner) {
            banner.style.background = '#fef2f2';
            banner.style.borderColor = '#fca5a5';
        }
        if (diffText) {
            diffText.textContent = 'Invalid quantity (cannot be negative)';
            diffText.style.color = '#dc2626';
        }
        if (submitBtn) submitBtn.disabled = true;
        return;
    }

    if (submitBtn) submitBtn.disabled = false;

    const diff = newQty - oldQty;
    const moveQty = Math.abs(diff);
    const adjValue = Math.round(moveQty * cost * 100) / 100;
    const diffFormatted = Number.isInteger(diff) ? diff.toLocaleString() : diff.toFixed(2);
    const valFormatted = adjValue.toLocaleString() + ' RWF';

    if (valText) valText.textContent = valFormatted;

    if (diff > 0) {
        // Stock Addition / Surplus
        if (banner) {
            banner.style.background = '#f0fdf4';
            banner.style.borderColor = '#bbf7d0';
        }
        if (diffText) {
            diffText.textContent = '+' + diffFormatted + ' units (Stock Addition / Surplus)';
            diffText.style.color = '#15803d';
        }
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Post Stock Addition (' + valFormatted + ')';
            submitBtn.style.background = '#16a34a';
        }

        let offsetName = 'Other Income / Inventory Gain';
        if (offsetAccSel && offsetAccSel.value) {
            offsetName = offsetAccSel.options[offsetAccSel.selectedIndex]?.text || offsetName;
        } else if (reason.toLowerCase().includes('opening') || reason.toLowerCase().includes('initial')) {
            offsetName = 'Opening Equity Balance (Account #77)';
        } else {
            offsetName = 'Other Income (Account #44)';
        }

        if (linesContainer) {
            linesContainer.innerHTML = `
                <div style="background:white;padding:8px 10px;border-radius:6px;border-left:4px solid #16a34a;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-weight:700;color:#16a34a;margin-right:6px;">DEBIT (Dr):</span>
                        <strong>${escHtml(storeName)}</strong>
                        <span style="font-size:11px;color:var(--gray-500);margin-left:4px;">(Store Inventory Asset &bull; Stock Increases)</span>
                    </div>
                    <strong style="color:#16a34a;font-size:13px;">${valFormatted}</strong>
                </div>
                <div style="background:white;padding:8px 10px;border-radius:6px;border-left:4px solid #4f46e5;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-weight:700;color:#4f46e5;margin-right:6px;">CREDIT (Cr):</span>
                        <strong>${escHtml(offsetName)}</strong>
                        <span style="font-size:11px;color:var(--gray-500);margin-left:4px;">(Surplus / Income / Equity)</span>
                    </div>
                    <strong style="color:#4f46e5;font-size:13px;">${valFormatted}</strong>
                </div>
            `;
        }
    } else if (diff < 0) {
        // Stock Reduction / Shrinkage
        if (banner) {
            banner.style.background = '#fef2f2';
            banner.style.borderColor = '#fca5a5';
        }
        if (diffText) {
            diffText.textContent = diffFormatted + ' units (Stock Reduction / Shrinkage)';
            diffText.style.color = '#dc2626';
        }
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-minus-circle"></i> Post Stock Reduction (' + valFormatted + ')';
            submitBtn.style.background = '#dc2626';
        }

        let offsetName = 'Drug / Medical Operation Loss';
        if (offsetAccSel && offsetAccSel.value) {
            offsetName = offsetAccSel.options[offsetAccSel.selectedIndex]?.text || offsetName;
        } else if (reason.toLowerCase().includes('expir')) {
            offsetName = 'Drug Expiration Expenses (Account #1021)';
        } else if (reason.toLowerCase().includes('damage') || reason.toLowerCase().includes('loss')) {
            offsetName = 'Drug Additional Loss (Account #1385)';
        } else {
            offsetName = 'Drug Additional Loss (Account #1385)';
        }

        if (linesContainer) {
            linesContainer.innerHTML = `
                <div style="background:white;padding:8px 10px;border-radius:6px;border-left:4px solid #dc2626;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-weight:700;color:#dc2626;margin-right:6px;">DEBIT (Dr):</span>
                        <strong>${escHtml(offsetName)}</strong>
                        <span style="font-size:11px;color:var(--gray-500);margin-left:4px;">(Inventory Loss / Expense)</span>
                    </div>
                    <strong style="color:#dc2626;font-size:13px;">${valFormatted}</strong>
                </div>
                <div style="background:white;padding:8px 10px;border-radius:6px;border-left:4px solid #4f46e5;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <span style="font-weight:700;color:#4f46e5;margin-right:6px;">CREDIT (Cr):</span>
                        <strong>${escHtml(storeName)}</strong>
                        <span style="font-size:11px;color:var(--gray-500);margin-left:4px;">(Store Inventory Asset &bull; Stock Decreases)</span>
                    </div>
                    <strong style="color:#4f46e5;font-size:13px;">${valFormatted}</strong>
                </div>
            `;
        }
    } else {
        // No difference
        if (banner) {
            banner.style.background = '#f3f4f6';
            banner.style.borderColor = '#e5e7eb';
        }
        if (diffText) {
            diffText.textContent = '0 units (No Change)';
            diffText.style.color = 'var(--gray-600)';
        }
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Save (No Qty Change)';
            submitBtn.style.background = 'var(--primary-color)';
        }
        if (linesContainer) {
            linesContainer.innerHTML = `
                <div style="text-align:center;padding:10px;color:var(--gray-500);font-size:12px;background:white;border-radius:6px;">
                    <i class="fas fa-info-circle" style="margin-right:4px;"></i> Quantity is unchanged. No financial journal entry will be recorded.
                </div>
            `;
        }
    }
}

async function submitStockAdjustment() {
    if (!invIsAdmin) {
        showToast('Permission denied: Only administrators can adjust stock.', 'error');
        return;
    }
    if (!curAdjData) return;
    const batchSel = document.getElementById('adj-batch-select');
    const storeSel = document.getElementById('adj-store-select');
    const curQtyEl = document.getElementById('adj-current-qty-display');
    const newQtyInput = document.getElementById('adj-new-qty');
    const costInput = document.getElementById('adj-cost');
    const reasonSel = document.getElementById('adj-reason-select');
    const dateInput = document.getElementById('adj-entry-date');
    const notesInput = document.getElementById('adj-notes');
    const offsetAccSel = document.getElementById('adj-offset-account-select');
    const newBatchNoInput = document.getElementById('adj-new-batch-no');
    const newExpiryInput = document.getElementById('adj-new-expiry');
    const submitBtn = document.getElementById('adj-submit-btn');

    const batchVal = batchSel ? batchSel.value : null;
    const isNew = batchVal === 'new';
    const storeId = storeSel ? parseInt(storeSel.value) : null;
    const oldQty = parseFloat(curQtyEl ? curQtyEl.dataset.qty : 0) || 0;
    const newQty = parseFloat(newQtyInput ? newQtyInput.value : 0);
    const unitCost = parseFloat(costInput ? costInput.value : 0) || 0;
    const reason = reasonSel ? reasonSel.value : 'Physical count reconciliation';
    const entryDate = dateInput ? dateInput.value : '';
    const notes = notesInput ? notesInput.value.trim() : '';
    const offsetAccId = (offsetAccSel && offsetAccSel.value) ? parseInt(offsetAccSel.value) : null;

    if (!storeId) {
        showToast('Please select a store location', 'error');
        return;
    }
    if (isNaN(newQty) || newQty < 0) {
        showToast('Please enter a valid non-negative quantity', 'error');
        return;
    }

    const diff = newQty - oldQty;
    const val = Math.abs(diff) * unitCost;

    const confirmMsg = diff === 0
        ? `No quantity change (${oldQty} -> ${newQty}). Save anyway?`
        : `Confirm Stock Adjustment for "${curAdjData.itemName}"?\n\n` +
          `• Old Quantity: ${oldQty}\n` +
          `• New Quantity: ${newQty} (${diff > 0 ? '+' : ''}${diff} units)\n` +
          `• Unit Cost: ${unitCost.toLocaleString()} RWF\n` +
          `• Adjustment Value: ${val.toLocaleString()} RWF\n` +
          `• Reason: ${reason}\n\n` +
          `A balanced journal entry will be created and posted to the General Ledger. Proceed?`;

    if (!confirm(confirmMsg)) return;

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
    }

    const payload = {
        item_id: curAdjData.itemId,
        batch_id: isNew ? null : parseInt(batchVal),
        store_id: storeId,
        new_quantity: newQty,
        unit_cost: unitCost,
        reason: reason,
        notes: notes,
        entry_date: entryDate,
        adjustment_account_id: offsetAccId,
        create_new_batch: isNew ? 1 : 0,
        batch_number: isNew && newBatchNoInput ? newBatchNoInput.value.trim() : null,
        expiry_date: isNew && newExpiryInput ? newExpiryInput.value : null,
        user_id: loggedUserId
    };

    try {
        const r = await fetch(invAPI + 'adjust_stock', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const j = await r.json();

        if (j.success) {
            const jRef = j.data?.journal_reference ? ` (Ref: ${j.data.journal_reference})` : '';
            showToast('✓ ' + (j.message || ('Stock adjusted!' + jRef)));
            
            // Close modal / clear inline panel
            adjClose();

            // Reload items master table
            loadItemsMaster();

            // If stock details modal was open for this item, refresh it
            if (typeof curItemStockData !== 'undefined' && curItemStockData && curItemStockData.itemId == curAdjData.itemId) {
                viewItemStock(curAdjData.itemId, curAdjData.itemName, 0);
            }
        } else {
            showToast(j.message || 'Adjustment failed', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                recalcAdjustStock();
            }
        }
    } catch(err) {
        console.error(err);
        showToast('Network error: ' + err.message, 'error');
        if (submitBtn) {
            submitBtn.disabled = false;
            recalcAdjustStock();
        }
    }
}

async function editStockBatch(batchId, currentBatch, currentExpiry, currentQty, currentCost, itemId, itemName) {
    if (!invIsAdmin) {
        showToast('Permission denied: Only administrators can edit batch details.', 'error');
        return;
    }
    const qtyText = (typeof currentQty !== 'undefined' && currentQty !== null) ? currentQty : '—';
    let cleanExpiry = '';
    if (currentExpiry && currentExpiry !== 'null' && currentExpiry !== 'undefined') {
        const m = String(currentExpiry).trim().match(/^(\d{4}-\d{2}-\d{2})/);
        cleanExpiry = m ? m[1] : String(currentExpiry).trim();
    }
    const targetItemId = itemId || (curItemStockData ? curItemStockData.itemId : 0);
    const targetItemName = itemName || (curItemStockData ? curItemStockData.itemName : '');
    const safeItemName = (targetItemName || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    const safeBatch = (currentBatch || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');

    const cancelAction = targetItemId ? `viewItemStock(${targetItemId}, '${safeItemName}')` : `document.getElementById('gen-modal').innerHTML=''`;

    const html = `
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div>
                <span style="font-size:12px;color:#1e40af;font-weight:600;">Current Quantity:</span>
                <strong style="font-size:14px;color:#1e3a8a;margin-left:4px;">${qtyText}</strong>
            </div>
            ${(targetItemId && invIsAdmin) ? `
                <button type="button" onclick="document.getElementById('gen-modal').innerHTML='';openAdjustStockForBatch(${batchId}, ${targetItemId}, '${safeItemName}', '', 0, '', '${safeBatch}', ${currentQty||0}, ${currentCost||0})" 
                    style="padding:6px 12px;background:#4f46e5;color:white;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                    <i class="fas fa-balance-scale"></i> Adjust Quantity (Journal Entry)
                </button>
            ` : ''}
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Batch Number</label>
            <input type="text" id="edit-batch-number" value="${escHtml(currentBatch || '')}" style="width:100%;padding:10px;border:1px solid var(--gray-200);border-radius:6px;font-family:monospace;">
        </div>
        <div style="margin-bottom:16px;">
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Expiry Date</label>
            <input type="date" id="edit-expiry-date" value="${cleanExpiry}" style="width:100%;padding:10px;border:1px solid var(--gray-200);border-radius:6px;">
            <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Leave empty if there is no expiration date.</div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;">
            <button onclick="${cancelAction}" style="padding:10px 20px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-weight:500;">Cancel</button>
            <button id="btn-save-batch-edit" onclick="saveStockBatchEdit(${batchId}, ${targetItemId}, '${safeItemName}')" style="padding:10px 20px;background:var(--primary-color);color:white;border:none;border-radius:6px;cursor:pointer;font-weight:500;"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    `;
    showModal('Edit Stock Batch', html, false);
}

async function saveStockBatchEdit(batchId, itemId, itemName) {
    if (!invIsAdmin) {
        showToast('Permission denied: Only administrators can edit batch details.', 'error');
        return;
    }
    const batchInput = document.getElementById('edit-batch-number');
    const expiryInput = document.getElementById('edit-expiry-date');
    const batchNumber = batchInput ? batchInput.value.trim() : '';
    const expiryDate = expiryInput ? expiryInput.value.trim() : '';
    
    if (!batchNumber) { showToast('Batch number is required', 'error'); return; }
    
    const saveBtn = document.getElementById('btn-save-batch-edit');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    }

    try {
        const r = await fetch(invAPI + 'update_stock_batch', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                batch_id: batchId,
                batch_number: batchNumber,
                expiry_date: expiryDate ? expiryDate : null
            })
        });
        const j = await r.json();
        if (j.success) {
            showToast('✓ Batch updated successfully');
            const fallbackItemId = itemId || (curItemStockData ? curItemStockData.itemId : 0);
            const fallbackItemName = itemName || (curItemStockData ? curItemStockData.itemName : '');
            if (fallbackItemId) {
                viewItemStock(fallbackItemId, fallbackItemName);
            } else {
                document.getElementById('gen-modal').innerHTML = '';
            }
            if (typeof loadItemsMaster === 'function') loadItemsMaster();
        } else {
            showToast(j.message || 'Update failed', 'error');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }
        }
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
        console.error(e);
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    }
}

async function editItem(id) {
    try {
        const r = await fetch(invAPI + 'item_detail&id=' + id);
        const j = await r.json();
        if (!j.success || !j.data) return alert('Item not found');
        const item = j.data;
        
        // Load categories
        let catOpts = '<option value="">Select...</option>';
        try { const cr = await fetch(invAPI+'categories'); const cj = await cr.json(); if(cj.success) cj.data.forEach(c=>{ catOpts+=`<option value="${c.id}" ${c.id==item.category_id?'selected':''}>${escHtml(c.name)}</option>`; }); } catch(e){}
        
        // Load insurance companies
        let insCompanies=[];
        try{ const ir=await fetch((typeof _apiBase !== 'undefined' ? _apiBase : 'api.php')+'?endpoint=insurance/companies&status=active'); const ij=await ir.json(); if(ij.success) insCompanies=ij.data?.companies||ij.data||[]; }catch(e){console.error('ins load err',e);}
        
        // Load existing insurance prices for this item
        let insPrices=[];
        try{ const pr=await fetch(invAPI+'item_insurance_prices&item_id='+id); const pj=await pr.json(); if(pj.success) insPrices=pj.data||[]; }catch(e){console.error('ins prices err',e);}
        
        // Build insurance rows
        let insRows='';
        if(insCompanies.length){
          insRows=insCompanies.map(c=>{
          const existing=insPrices.find(p=>p.insurance_id==c.id)||{};
          const isExc=existing.is_exceptional==1;
          return `<tr style="border-bottom:1px solid var(--gray-100);" id="ei-ins-row-${c.id}">
            <td style="padding:6px 8px;font-size:12px;font-weight:500;">${escHtml(c.name)}</td>
            <td style="padding:6px 8px;"><input type="number" id="ei-ins-price-${c.id}" value="${existing.price||0}" style="width:70px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:right;"></td>
            <td style="padding:6px 8px;text-align:center;"><select id="ei-ins-type-${c.id}" onchange="eiToggleExc(${c.id})" style="padding:4px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"><option value="0" ${!isExc?'selected':''}>Normal</option><option value="1" ${isExc?'selected':''}>Exceptional</option></select></td>
            <td style="padding:6px 8px;" id="ei-ins-exc-${c.id}">${isExc?'<input type="number" id="ei-ins-addprice-'+c.id+'" value="'+(existing.additional_insurance_selling_price||0)+'" style="width:70px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:right;" placeholder="Sell price">':''}</td>
            <td style="display:none;" id="ei-ins-topup-${c.id}">${isExc?((existing.additional_insurance_selling_price||0)-(existing.price||0)).toLocaleString()+' RWF':''}</td>
          </tr>`;
        }).join('');
        }
        
        const modal = document.createElement('div');
        modal.id = 'edit-item-modal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
        modal.innerHTML = `<div style="background:white;border-radius:12px;width:700px;max-width:95vw;max-height:90vh;overflow-y:auto;padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <h3 style="font-size:16px;font-weight:600;margin:0;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Edit Item</h3>
                    ${invIsAdmin ? `<button type="button" onclick="const m=document.getElementById('edit-item-modal'); if(m) m.remove(); openAdjustStockForItem(${item.id},'${(item.name||'').replace(/\\/g, '\\\\').replace(/'/g, "\\'")}');" style="padding:5px 12px;background:#e0e7ff;border:1px solid #c7d2fe;border-radius:6px;font-size:12px;color:#3730a3;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;" title="Adjust stock quantity with automatic journal entries"><i class="fas fa-balance-scale"></i> Adjust Stock Quantity</button>` : ''}
                </div>
                <button onclick="document.getElementById('edit-item-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Item Name *</label><input id="ei-name" value="${escHtml(item.name||'')}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
                <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Item Code</label><input id="ei-code" value="${escHtml(item.item_code||'')}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
                <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Generic Name</label><input id="ei-generic" value="${escHtml(item.generic_name||'')}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
                <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Category</label><select id="ei-category" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">${catOpts}</select></div>
                <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Unit Price (Cost)</label><input type="number" id="ei-cost" value="${item.unit_price||0}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
                <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Selling Price (Private)</label><input type="number" id="ei-sell" value="${item.selling_price||0}" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
                <div style="display:flex;align-items:center;gap:16px;padding-top:8px;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" id="ei-medication" ${item.is_medication?'checked':''}> Medication</label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" id="ei-controlled" ${item.is_controlled?'checked':''}> Controlled</label>
                </div>
                <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Status</label><select id="ei-status" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="1" ${item.is_active?'selected':''}>Active</option><option value="0" ${!item.is_active?'selected':''}>Inactive</option></select></div>
                <div style="grid-column:1/-1;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Description</label><textarea id="ei-desc" rows="2" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;">${escHtml(item.description||'')}</textarea></div>
            </div>
            
            <!-- Insurance Pricing -->
            <div style="border:1px solid var(--gray-200);border-radius:8px;padding:14px;margin-bottom:16px;">
              <h4 style="font-size:13px;font-weight:600;margin-bottom:10px;color:#7c3aed;"><i class="fas fa-shield-alt"></i> Insurance Pricing</h4>
              <p style="font-size:11px;color:var(--gray-500);margin-bottom:8px;">Set price per insurance. If <strong>Exceptional</strong>, set the additional selling price — patient tops up the difference.</p>
              <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:var(--gray-50);">
                  <th style="padding:6px 8px;text-align:left;">Insurance</th>
                  <th style="padding:6px 8px;text-align:right;">Price</th>
                  <th style="padding:6px 8px;text-align:center;">Type</th>
                  <th style="padding:6px 8px;text-align:right;">Add. Selling Price</th>
                  <th style="display:none;">Patient Top-up</th>
                </tr></thead>
                <tbody>${insRows}</tbody>
              </table>
            </div>
            
            <button onclick="saveEditItem(${item.id})" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save Changes</button>
        </div>`;
        document.body.appendChild(modal);
    } catch(e) { alert('Error loading item'); }
}

function eiToggleExc(insId){
  const type=document.getElementById('ei-ins-type-'+insId).value;
  const excTd=document.getElementById('ei-ins-exc-'+insId);
  const topupTd=document.getElementById('ei-ins-topup-'+insId);
  if(type==='1'){
    excTd.innerHTML='<input type="number" id="ei-ins-addprice-'+insId+'" value="0" oninput="eiCalcTopup('+insId+')" style="width:70px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:right;" placeholder="Sell price">';
    topupTd.innerHTML='0 RWF';
  }else{
    excTd.innerHTML='';
    topupTd.innerHTML='';
  }
}

function eiCalcTopup(insId){
  const price=parseFloat(document.getElementById('ei-ins-price-'+insId).value)||0;
  const addPrice=parseFloat(document.getElementById('ei-ins-addprice-'+insId).value)||0;
  const topup=addPrice-price;
  document.getElementById('ei-ins-topup-'+insId).innerHTML=(topup>0?topup.toLocaleString():'0')+' RWF';
}

async function saveEditItem(id) {
    const data = {
        name: document.getElementById('ei-name').value,
        item_code: document.getElementById('ei-code').value,
        generic_name: document.getElementById('ei-generic').value,
        category_id: parseInt(document.getElementById('ei-category').value) || null,
        unit_price: parseFloat(document.getElementById('ei-cost').value) || 0,
        selling_price: parseFloat(document.getElementById('ei-sell').value) || 0,
        is_medication: document.getElementById('ei-medication').checked ? 1 : 0,
        is_controlled: document.getElementById('ei-controlled').checked ? 1 : 0,
        is_active: parseInt(document.getElementById('ei-status').value),
        description: document.getElementById('ei-desc').value
    };
    if (!data.name) return alert('Item name required.');
    
    // Collect insurance pricing
    const insPricing=[];
    document.querySelectorAll('[id^="ei-ins-price-"]').forEach(el=>{
      const insId=el.id.replace('ei-ins-price-','');
      const price=parseFloat(el.value)||0;
      const typeEl=document.getElementById('ei-ins-type-'+insId);
      const isExc=typeEl?typeEl.value==='1':false;
      const addPriceEl=document.getElementById('ei-ins-addprice-'+insId);
      const addPrice=addPriceEl?parseFloat(addPriceEl.value)||0:0;
      if(price>0||addPrice>0){
        insPricing.push({insurance_id:parseInt(insId),price,is_exceptional:isExc?1:0,additional_insurance_selling_price:isExc?addPrice:0,patient_topup:0});
      }
    });
    data.insurance_prices=insPricing;
    
    try {
        const r = await fetch(invAPI + 'update_item&id=' + id, {method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(data)});
        const j = await r.json();
        if (j.success) { alert('✓ Item updated!'); document.getElementById('edit-item-modal').remove(); loadItemsMaster(); }
        else alert('Error: ' + j.message);
    } catch(e) { alert('Failed'); }
}
// ========== CREATE ITEM ==========
async function saveItem() {
    const data = {
        name: document.getElementById('fi-name')?.value,
        item_code: document.getElementById('fi-code')?.value,
        generic_name: document.getElementById('fi-generic')?.value,
        category_id: parseInt(document.getElementById('fi-category')?.value),
        uom_id: parseInt(document.getElementById('fi-uom')?.value) || null,
        is_medication: document.getElementById('fi-medication')?.checked ? 1 : 0,
        is_controlled: document.getElementById('fi-controlled')?.checked ? 1 : 0,
        description: document.getElementById('fi-desc')?.value
    };
    if (!data.name || !data.category_id) return showToast('Name and Category required', 'error');
    try {
        const r = await fetch(invAPI + 'create_item', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const j = await r.json();
        if (j.success) { showToast('Item created!'); window.location.href = 'inventory.php?module=inventory&sub=items'; }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Failed to create item', 'error'); }
}

// ========== RECEIVE STOCK ==========
async function saveReceiveStock() {
    const data = {
        item_id: parseInt(document.getElementById('rs-item')?.value),
        store_id: parseInt(document.getElementById('rs-store')?.value),
        quantity: parseFloat(document.getElementById('rs-qty')?.value),
        unit_cost: parseFloat(document.getElementById('rs-cost')?.value) || 0,
        selling_price: parseFloat(document.getElementById('rs-price')?.value) || null,
        batch_number: document.getElementById('rs-batch')?.value,
        expiry_date: document.getElementById('rs-expiry')?.value || null,
        received_date: document.getElementById('rs-date')?.value || null,
        reorder_level: parseFloat(document.getElementById('rs-reorder')?.value) || 0,
        min_stock_level: parseFloat(document.getElementById('rs-min')?.value) || 0,
        notes: document.getElementById('rs-notes')?.value
    };
    if (!data.item_id || !data.store_id || !data.quantity) return showToast('Item, Store and Quantity required', 'error');
    try {
        const r = await fetch(invAPI + 'receive_stock', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const j = await r.json();
        if (j.success) { showToast('Stock received!'); window.location.href = 'inventory.php?module=inventory&sub=stock'; }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Failed to receive stock', 'error'); }
}

// ========== ISSUE STOCK ==========
async function saveIssueStock() {
    const data = {
        item_id: parseInt(document.getElementById('is-item')?.value),
        store_id: parseInt(document.getElementById('is-store')?.value),
        quantity: parseFloat(document.getElementById('is-qty')?.value),
        notes: document.getElementById('is-notes')?.value
    };
    if (!data.item_id || !data.store_id || !data.quantity) return showToast('Item, Store and Quantity required', 'error');
    try {
        const r = await fetch(invAPI + 'issue_stock', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const j = await r.json();
        if (j.success) { showToast('Stock issued!'); window.location.href = 'inventory.php?module=inventory&sub=stock'; }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Failed to issue stock', 'error'); }
}

// ========== TRANSFER STOCK ==========
async function saveTransfer() {
    const data = {
        item_id: parseInt(document.getElementById('tr-item')?.value),
        from_store_id: parseInt(document.getElementById('tr-from')?.value),
        to_store_id: parseInt(document.getElementById('tr-to')?.value),
        quantity: parseFloat(document.getElementById('tr-qty')?.value),
        notes: document.getElementById('tr-notes')?.value
    };
    if (!data.item_id || !data.from_store_id || !data.to_store_id || !data.quantity) return showToast('All fields required', 'error');
    try {
        const r = await fetch(invAPI + 'transfer_stock', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const j = await r.json();
        if (j.success) { showToast('Transfer completed!'); loadTransfers(); }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Transfer failed', 'error'); }
}

// ========== LOAD TRANSFERS ==========
async function loadTransfers() {
    const tbody = document.getElementById('transfers-tbody');
    const empty = document.getElementById('transfers-empty');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    try {
        const r = await fetch(invAPI + 'transfers');
        const j = await r.json();
        if (!j.success || !j.data || !j.data.length) {
            if (empty) empty.style.display = 'block';
            tbody.innerHTML = '';
            return;
        }
        if (empty) empty.style.display = 'none';
        tbody.innerHTML = j.data.map(t =>
            '<tr>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);font-weight:500;">' + escHtml(t.transfer_number) + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(t.from_store_name) + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(t.to_store_name) + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + (t.transfer_date || '-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);"><span style="padding:4px 8px;border-radius:4px;font-size:12px;background:' + (t.status === 'received' ? '#d1fae5' : t.status === 'cancelled' ? '#fee2e2' : t.status === 'in_transit' ? '#dbeafe' : '#fef3c7') + ';color:' + (t.status === 'received' ? '#065f46' : t.status === 'cancelled' ? '#991b1b' : t.status === 'in_transit' ? '#1e40af' : '#92400e') + ';">' + escHtml(t.status) + '</span></td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(t.reason||'-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);"><button onclick="viewTransferDetail(' + t.id + ')" style="padding:4px 10px;background:var(--gray-100);border:none;border-radius:4px;cursor:pointer;"><i class="fas fa-eye"></i></button></td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);"><button onclick="cancelTransfer(' + t.id + ')" style="padding:4px 10px;background:#fee2e2;border:none;border-radius:4px;cursor:pointer;color:#991b1b;"><i class="fas fa-times"></i></button></td>' +
            '</tr>'
        ).join('');
    } catch(e) { console.error('loadTransfers:', e); tbody.innerHTML = ''; }
}

async function viewTransferDetail(id) {
    try {
        const r = await fetch(invAPI + 'transfer_detail&id=' + id);
        const j = await r.json();
        if (!j.success || !j.data) return showToast(j.message || 'Could not load transfer details', 'error');
        const t = j.data;
        
        const sBg = {draft: '#f3f4f6', pending: '#fef3c7', approved: '#d1fae5', cancelled: '#fee2e2', received: '#dbeafe'};
        const sColor = {draft: '#374151', pending: '#92400e', approved: '#065f46', cancelled: '#991b1b', received: '#1e40af'};
        const statusBg = sBg[t.status] || '#f3f4f6';
        const statusColor = sColor[t.status] || '#374151';

        let html = '<div style="font-family:inherit;">';
        
        // Summary Header Card
        html += '<div style="background:#f9fafb;border:1px solid var(--gray-200);border-radius:8px;padding:16px;margin-bottom:20px;">' +
            '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:14px;font-size:13px;">' +
            '<div><span style="color:var(--gray-500);display:block;font-size:11px;font-weight:600;text-transform:uppercase;">Transfer #</span><strong style="font-family:monospace;font-size:14px;color:var(--primary-color);">' + escHtml(t.transfer_number || '-') + '</strong></div>' +
            '<div><span style="color:var(--gray-500);display:block;font-size:11px;font-weight:600;text-transform:uppercase;">Status</span><span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;text-transform:capitalize;background:' + statusBg + ';color:' + statusColor + ';">' + escHtml(t.status || '-') + '</span></div>' +
            '<div><span style="color:var(--gray-500);display:block;font-size:11px;font-weight:600;text-transform:uppercase;">From Store</span><strong style="color:var(--gray-800);">' + escHtml(t.from_store_name || '#' + t.from_store_id) + (t.from_store_code ? ' (' + escHtml(t.from_store_code) + ')' : '') + '</strong></div>' +
            '<div><span style="color:var(--gray-500);display:block;font-size:11px;font-weight:600;text-transform:uppercase;">To Store</span><strong style="color:var(--gray-800);">' + escHtml(t.to_store_name || '#' + t.to_store_id) + (t.to_store_code ? ' (' + escHtml(t.to_store_code) + ')' : '') + '</strong></div>' +
            '<div><span style="color:var(--gray-500);display:block;font-size:11px;font-weight:600;text-transform:uppercase;">Transfer Date</span><span style="color:var(--gray-700);">' + (t.transfer_date || '-') + '</span></div>' +
            '<div><span style="color:var(--gray-500);display:block;font-size:11px;font-weight:600;text-transform:uppercase;">Reason / Notes</span><span style="color:var(--gray-700);">' + escHtml(t.reason || (t.notes || '—')) + '</span></div>' +
            '</div></div>';

        // Transferred Items Table
        html += '<h4 style="font-size:14px;font-weight:600;margin-bottom:10px;color:var(--gray-800);display:flex;align-items:center;gap:6px;"><i class="fas fa-boxes" style="color:var(--primary-color);"></i> Transferred Items & Batches</h4>';
        
        if (t.items && t.items.length) {
            html += '<div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;margin-bottom:16px;">';
            html += '<table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:#f8fafc;border-bottom:1px solid var(--gray-200);">';
            html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);width:40px;">#</th>';
            html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Item Details</th>';
            html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Batch Number</th>';
            html += '<th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Expiration Date</th>';
            html += '<th style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-700);">Transferred Qty</th>';
            html += '<th style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-700);">Unit Cost</th>';
            html += '<th style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-700);">Total Value</th>';
            html += '</tr></thead><tbody>';
            
            let totalQty = 0;
            let totalVal = 0;
            
            t.items.forEach((it, idx) => {
                const qty = parseFloat(it.quantity || 0);
                const cost = parseFloat(it.unit_cost || 0);
                const lineVal = qty * cost;
                totalQty += qty;
                totalVal += lineVal;

                // Expiry formatting
                let expBadge = '<span style="color:var(--gray-400);">—</span>';
                if (it.expiry_date) {
                    const isExp = new Date(it.expiry_date) < new Date();
                    if (isExp) {
                        expBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;background:#fee2e2;color:#991b1b;"><i class="fas fa-exclamation-triangle"></i> ' + it.expiry_date + ' (Expired)</span>';
                    } else {
                        expBadge = '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500;background:#ecfdf5;color:#065f46;">' + it.expiry_date + '</span>';
                    }
                }

                // Batch Number formatting
                const batchHtml = it.batch_number ? 
                    '<span style="font-family:monospace;font-weight:600;padding:2px 8px;background:#eff6ff;color:#1e40af;border-radius:4px;border:1px solid #dbeafe;">' + escHtml(it.batch_number) + '</span>' : 
                    '<span style="color:var(--gray-400);">—</span>';

                html += '<tr style="border-bottom:1px solid var(--gray-100);">' +
                    '<td style="padding:10px 12px;color:var(--gray-400);">' + (idx + 1) + '</td>' +
                    '<td style="padding:10px 12px;"><div style="font-weight:600;color:var(--gray-900);">' + escHtml(it.item_name || 'Item #' + it.item_id) + '</div>' +
                    (it.item_code ? '<div style="font-size:11px;color:var(--gray-500);font-family:monospace;">' + escHtml(it.item_code) + (it.category_name ? ' • ' + escHtml(it.category_name) : '') + '</div>' : '') + '</td>' +
                    '<td style="padding:10px 12px;">' + batchHtml + '</td>' +
                    '<td style="padding:10px 12px;text-align:center;">' + expBadge + '</td>' +
                    '<td style="padding:10px 12px;text-align:right;font-weight:700;font-size:13px;color:var(--gray-900);">' + Number(qty).toLocaleString() + (it.uom_abbr ? ' ' + escHtml(it.uom_abbr) : '') + '</td>' +
                    '<td style="padding:10px 12px;text-align:right;color:var(--gray-600);">' + (cost > 0 ? Number(cost).toLocaleString() + ' RWF' : '—') + '</td>' +
                    '<td style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-900);">' + (lineVal > 0 ? Number(lineVal).toLocaleString() + ' RWF' : '—') + '</td>' +
                    '</tr>';
            });
            
            html += '</tbody><tfoot><tr style="background:#f8fafc;font-weight:700;border-top:2px solid var(--gray-200);">' +
                '<td colspan="4" style="padding:10px 12px;text-align:right;color:var(--gray-700);">Total:</td>' +
                '<td style="padding:10px 12px;text-align:right;color:var(--gray-900);font-size:13px;">' + Number(totalQty).toLocaleString() + '</td>' +
                '<td style="padding:10px 12px;"></td>' +
                '<td style="padding:10px 12px;text-align:right;color:var(--primary-color);font-size:13px;">' + (totalVal > 0 ? Number(totalVal).toLocaleString() + ' RWF' : '—') + '</td>' +
                '</tr></tfoot></table></div>';
        } else {
            html += '<div style="padding:24px;text-align:center;color:var(--gray-400);border:1px dashed var(--gray-200);border-radius:8px;margin-bottom:16px;">No items found in this transfer record.</div>';
        }

        // Action Buttons inside Modal
        html += '<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid var(--gray-100);">';
        if (t.status === 'pending') {
            if (invIsAdmin) {
                html += '<button onclick="tfApprove(' + t.id + ')" style="padding:8px 16px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-check"></i> Approve Transfer</button>';
                html += '<button onclick="tfReject(' + t.id + ')" style="padding:8px 16px;background:#dc2626;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-times"></i> Reject</button>';
            }
        } else if (t.status === 'draft') {
            html += '<button onclick="tfSubmit(' + t.id + ')" style="padding:8px 16px;background:#d97706;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-paper-plane"></i> Submit for Approval</button>';
        }
        html += '<button onclick="document.getElementById(\'gen-modal\').innerHTML=\'\'" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">Close</button>';
        html += '</div></div>';

        showModal('Transfer Details — ' + (t.transfer_number || '#' + id), html, '850px');
    } catch(e) { console.error('viewTransferDetail error:', e); showToast('Error loading transfer details', 'error'); }
}

function cancelTransfer(id) {
    if (!invIsAdmin) {
        showToast('Permission denied: Only administrators can cancel transfers.', 'error');
        return;
    }
    if (!confirm('Cancel this transfer?')) return;
    fetch(invAPI + 'update_transfer_status&id=' + id, {
        method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({status: 'cancelled'})
    }).then(r => r.json()).then(j => {
        if (j.success) { showToast('Transfer cancelled'); loadTransfers(); }
        else showToast(j.message, 'error');
    });
}

// ========== SUPPLIERS ==========
async function loadSuppliersList() {
    const tbody = document.getElementById('suppliers-tbody');
    const empty = document.getElementById('suppliers-empty');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    try {
        const r = await fetch(invAPI + 'suppliers');
        const j = await r.json();
        if (!j.success || !j.data || !j.data.length) {
            if (empty) empty.style.display = 'block';
            tbody.innerHTML = '';
            return;
        }
        if (empty) empty.style.display = 'none';
        tbody.innerHTML = j.data.map(s =>
            '<tr>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(s.supplier_code) + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);font-weight:500;">' + escHtml(s.company_name) + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(s.contact_person||'-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(s.phone||'-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(s.email||'-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(s.category||'-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);"><button onclick="deleteSupplier(' + s.id + ',\'' + s.company_name.replace(/'/g,"\\'") + '\')" style="padding:4px 10px;background:#fee2e2;border:none;border-radius:4px;cursor:pointer;color:#991b1b;"><i class="fas fa-trash"></i></button></td>' +
            '</tr>'
        ).join('');
    } catch(e) { console.error('loadSuppliersList:', e); tbody.innerHTML = ''; }
}

function deleteSupplier(id, name) {
    if (!confirm('Deactivate supplier "' + name + '"?')) return;
    fetch(invAPI + 'delete_supplier&id=' + id, {method: 'DELETE'})
        .then(r => r.json()).then(j => {
            if (j.success) { showToast('Supplier deactivated'); loadSuppliersList(); }
            else showToast(j.message, 'error');
        });
}

async function saveSupplier() {
    const data = {
        supplier_code: document.getElementById('fs-code')?.value,
        company_name: document.getElementById('fs-name')?.value,
        contact_person: document.getElementById('fs-contact')?.value,
        phone: document.getElementById('fs-phone')?.value,
        email: document.getElementById('fs-email')?.value,
        address: document.getElementById('fs-address')?.value,
        category: document.getElementById('fs-category')?.value,
        payment_terms: document.getElementById('fs-terms')?.value,
        tax_id: document.getElementById('fs-tax')?.value,
        notes: document.getElementById('fs-notes')?.value
    };
    if (!data.company_name) return showToast('Company name required', 'error');
    try {
        const r = await fetch(invAPI + 'create_supplier', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const j = await r.json();
        if (j.success) { showToast('Supplier created!'); window.location.href = 'inventory.php?module=inventory&sub=suppliers'; }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Failed to create supplier', 'error'); }
}

// ========== CATEGORIES ==========
async function loadCategoriesTable() {
    const tbody = document.getElementById('cat-tbody');
    const empty = document.getElementById('cat-empty');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    try {
        const r = await fetch(invAPI + 'categories');
        const j = await r.json();
        if (!j.success || !j.data || !j.data.length) {
            if (empty) empty.style.display = 'block';
            tbody.innerHTML = '';
            return;
        }
        if (empty) empty.style.display = 'none';
        invCatRows = j.data;
        tbody.innerHTML = j.data.map(c =>
            '<tr>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);font-weight:500;">' + escHtml(c.name) + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(c.code) + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + escHtml(c.description||'-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + (c.parent_id ? 'Subcategory' : 'Parent') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);white-space:nowrap;"><button onclick="editCategory(' + c.id + ')" title="Edit" style="padding:4px 10px;background:var(--gray-100);border:none;border-radius:4px;cursor:pointer;color:var(--primary-color);margin-right:4px;"><i class="fas fa-edit"></i></button><button onclick="deleteCategory(' + c.id + ',\'' + c.name.replace(/'/g,"\\'") + '\')" style="padding:4px 10px;background:#fee2e2;border:none;border-radius:4px;cursor:pointer;color:#991b1b;"><i class="fas fa-trash"></i></button></td>' +
            '</tr>'
        ).join('');
    } catch(e) { console.error('loadCategoriesTable:', e); tbody.innerHTML = ''; }
}

let invCatRows = [];
function editCategory(id) {
    const c = invCatRows.find(x => x.id == id); if (!c) return;
    document.getElementById('cat-edit-modal')?.remove();
    const m = document.createElement('div');
    m.id = 'cat-edit-modal';
    m.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;padding:16px;';
    const f = 'width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;margin-bottom:12px;';
    const l = 'display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;';
    m.innerHTML = '<div style="background:white;border-radius:12px;padding:24px;width:100%;max-width:480px;">' +
        '<h3 style="font-size:17px;font-weight:600;margin-bottom:16px;">Edit Category</h3>' +
        '<label style="' + l + '">Category Name *</label><input id="ce-name" style="' + f + '" value="' + escHtml(c.name).replace(/"/g, '&quot;') + '">' +
        '<label style="' + l + '">Code</label><input id="ce-code" style="' + f + '" value="' + escHtml(c.code).replace(/"/g, '&quot;') + '">' +
        '<label style="' + l + '">Description</label><textarea id="ce-desc" rows="2" style="' + f + '">' + escHtml(c.description) + '</textarea>' +
        '<div style="display:flex;gap:10px;"><button onclick="saveCategoryEdit(' + c.id + ')" style="flex:1;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save</button>' +
        '<button onclick="document.getElementById(\'cat-edit-modal\').remove()" style="padding:11px 22px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button></div></div>';
    document.body.appendChild(m);
}
async function saveCategoryEdit(id) {
    const name = document.getElementById('ce-name').value.trim();
    if (!name) return showToast('Category name required', 'error');
    try {
        const r = await fetch(invAPI + 'update_category&id=' + id, {method: 'PUT', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({name, code: document.getElementById('ce-code').value.trim(), description: document.getElementById('ce-desc').value.trim()})});
        const j = await r.json();
        if (j.success) { document.getElementById('cat-edit-modal').remove(); showToast('Category updated'); loadCategoriesTable(); }
        else showToast(j.message, 'error');
    } catch (e) { showToast('Failed to update category', 'error'); }
}

function deleteCategory(id, name) {
    if (!confirm('Deactivate category "' + name + '"?')) return;
    fetch(invAPI + 'delete_category&id=' + id, {method: 'DELETE'})
        .then(r => r.json()).then(j => {
            if (j.success) { showToast('Category deactivated'); loadCategoriesTable(); }
            else showToast(j.message, 'error');
        });
}

async function saveCategory() {
    const data = {
        name: document.getElementById('fc-name')?.value,
        code: document.getElementById('fc-code')?.value,
        description: document.getElementById('fc-desc')?.value
    };
    if (!data.name) return showToast('Category name required', 'error');
    try {
        const r = await fetch(invAPI + 'create_category', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const j = await r.json();
        if (j.success) { showToast('Category created!'); window.location.href = 'inventory.php?module=inventory&sub=categories'; }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Failed to create category', 'error'); }
}

// ========== PURCHASE REQUESTS ==========
async function loadPurchaseRequests() {
    const tbody = document.getElementById('pr-tbody');
    const empty = document.getElementById('pr-empty');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    try {
        const r = await fetch(invAPI + 'purchase_requests');
        const j = await r.json();
        if (!j.success || !j.data || !j.data.length) {
            if (empty) empty.style.display = 'block';
            tbody.innerHTML = '';
            return;
        }
        if (empty) empty.style.display = 'none';
        tbody.innerHTML = j.data.map(pr =>
            '<tr>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);font-weight:500;">' + escHtml(pr.pr_number) + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + (pr.request_date || '-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + (pr.item_count||0) + ' items</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + (pr.total_estimated ? 'RWF ' + Number(pr.total_estimated).toLocaleString() : '-') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);"><span style="padding:2px 6px;border-radius:3px;font-size:12px;background:' + (pr.status === 'approved' ? '#d1fae5' : pr.status === 'rejected' || pr.status === 'cancelled' ? '#fee2e2' : '#fef3c7') + ';color:' + (pr.status === 'approved' ? '#065f46' : pr.status === 'rejected' || pr.status === 'cancelled' ? '#991b1b' : '#92400e') + ';">' + escHtml(pr.status) + '</span></td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);">' + (pr.priority || 'medium') + '</td>' +
            '<td style="padding:12px;border-bottom:1px solid var(--gray-100);"><button onclick="window.location.href=\'inventory.php?module=inventory&sub=procurement&action=create_po&pr_id=' + pr.id + '\'" style="padding:4px 10px;background:var(--primary-color);color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;">Create PO</button></td>' +
            '</tr>'
        ).join('');
    } catch(e) { console.error('loadPurchaseRequests:', e); tbody.innerHTML = ''; }
}

// ========== INIT ==========
document.addEventListener('DOMContentLoaded', function() {
    // Load categories dropdowns
    loadCategories('fi-category');
    loadCategories('fc-category');
    loadUoms('fi-uom');
    loadStores('rs-store');
    loadStores('is-store');
    loadStores('tr-from');
    loadStores('tr-to');
    loadItems('rs-item');
    loadItems('is-item');
    loadItems('tr-item');
    loadSuppliers('fs-supplier');

    // Load tables based on sub-module
    const sub = '<?php echo $subModule; ?>';
    const act = '<?php echo $action; ?>';
    if (act === 'list') {
        if (sub === 'central-store' || sub === 'stock') loadStock();
        else if (sub === 'items') loadItemsMaster();
        else if (sub === 'transfers') loadTransfers();
        else if (sub === 'suppliers') loadSuppliersList();
        else if (sub === 'categories') loadCategoriesTable();
        else if (sub === 'procurement') loadPurchaseRequests();
        // Load dashboard stats on central-store
        if (sub === 'central-store') loadDashboard();
    }
    // Load dashboard even if empty for stats
    if (sub === 'central-store' && document.getElementById('inv-stats')) loadDashboard();
});
</script>
<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'central-store' => 'Manage central store inventory and stock levels',
                'stock' => 'Track and manage all stock items across departments',
                'transfers' => 'Transfer stock between departments and stores',
                'audit' => 'Conduct stock audits and reconcile inventory',
                'items' => 'Manage item master data, categories and units',
                'categories' => 'Manage item categories and classification',
                'suppliers' => 'Manage vendor and supplier information',
                'procurement' => 'Create and manage purchase requests and orders'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Inventory Management';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($action == 'list'): ?>
        <?php 
        $addLinks = [
            'central-store' => 'inventory.php?module=inventory&sub=central-store&action=add',
            'stock' => 'inventory.php?module=inventory&sub=stock&action=receive',
            'transfers' => 'inventory.php?module=inventory&sub=transfers&action=new',
            'audit' => 'inventory.php?module=inventory&sub=audit&action=add',
            'items' => 'inventory.php?module=inventory&sub=items&action=add',
            'categories' => 'inventory.php?module=inventory&sub=categories&action=add',
            'suppliers' => 'inventory.php?module=inventory&sub=suppliers&action=add',
            'procurement' => 'inventory.php?module=inventory&sub=procurement&action=create_pr',
        ];
        $addLabels = [
            'central-store' => 'Add Item',
            'stock' => 'Receive Stock',
            'transfers' => 'New Transfer',
            'audit' => 'New Audit',
            'items' => 'New Item',
            'categories' => 'New Category',
            'suppliers' => 'New Supplier',
            'procurement' => 'New PR',
        ];
        $addIcons = [
            'central-store' => 'fa-plus',
            'stock' => 'fa-arrow-down',
            'transfers' => 'fa-exchange-alt',
            'audit' => 'fa-clipboard-check',
            'items' => 'fa-plus',
            'categories' => 'fa-plus',
            'suppliers' => 'fa-plus',
            'procurement' => 'fa-file-invoice',
        ];
        $colors = [
            'central-store' => 'var(--primary-color)',
            'stock' => 'var(--success-color)',
            'transfers' => 'var(--warning-color)',
            'audit' => 'var(--primary-color)',
            'items' => 'var(--primary-color)',
            'categories' => 'var(--primary-color)',
            'suppliers' => 'var(--primary-color)',
            'procurement' => 'var(--primary-color)',
        ];
        if (isset($addLinks[$subModule])): ?>
        <button onclick="window.location.href='<?php echo $addLinks[$subModule]; ?>'" style="padding: 10px 20px; background: <?php echo $colors[$subModule]; ?>; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas <?php echo $addIcons[$subModule]; ?>"></i> <?php echo $addLabels[$subModule]; ?>
        </button>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($action != 'list' && $action != ''): ?>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
    </div>
</div>
<!-- ============================================
CENTRAL STORE
============================================ -->
<?php if ($subModule == 'central-store'): ?>

<?php if ($action == 'add'): ?>
<!-- ADD ITEM FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-store" style="color: var(--primary-color);"></i> Add Item to Central Store
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Item Code <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" id="fi-code" placeholder="e.g., ITM-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Item Name <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" id="fi-name" placeholder="Enter item name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Category <span style="color: var(--danger-color);">*</span>
            </label>
            <select id="fi-category" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Unit of Measure
            </label>
            <select id="fi-uom" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Generic Name
            </label>
            <input type="text" id="fi-generic" placeholder="Generic name (for meds)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div style="display: flex; align-items: center; gap: 20px; padding-top: 8px;">
            <label style="display: flex; align-items: center; gap: 6px; font-size: 14px; cursor: pointer;">
                <input type="checkbox" id="fi-medication"> Is Medication
            </label>
            <label style="display: flex; align-items: center; gap: 6px; font-size: 14px; cursor: pointer;">
                <input type="checkbox" id="fi-controlled"> Controlled Drug
            </label>
        </div>
        <div style="grid-column: 1 / -1;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Description
            </label>
            <textarea id="fi-desc" rows="2" placeholder="Item description" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>
    <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <button onclick="saveItem()" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-save"></i> Save Item
        </button>
        <button type="reset" onclick="window.location.href='inventory.php?module=inventory&sub=central-store'" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
            Cancel
        </button>
    </div>
</div>

<?php else: ?>
<!-- CENTRAL STORE DASHBOARD -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-warehouse" style="color:var(--primary-color);"></i> Stores (Linked to Chart of Accounts)</h3>
      <button onclick="storeNew()" style="padding:8px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> New Store</button>
    </div>
    <div id="stores-list" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;"></div>
    <h4 style="font-size:14px;font-weight:600;margin:18px 0 10px;color:var(--gray-700);">Department stores</h4>
    <div id="dept-stores" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;"></div>
</div>

<script>
async function storesLoad(){
  try{
    const r=await fetch(invAPI+'stores_with_accounts');const j=await r.json();
    const div=document.getElementById('stores-list');
    if(j.success&&j.data&&j.data.length){
      window._stores=Object.fromEntries(j.data.map(s=>[s.id,s]));
      const on=window._storeFilter;
      div.innerHTML=`<div onclick="storePick(0)" style="cursor:pointer;border:2px solid ${!on?'var(--primary-color)':'var(--gray-200)'};background:${!on?'#eff6ff':'#fff'};border-radius:8px;padding:14px;"><div style="font-size:14px;font-weight:600;">All stores</div><div style="font-size:12px;color:var(--gray-500);margin-top:6px;">${j.data.reduce((a,s)=>a+ +s.item_count,0)} items · RWF ${Math.round(j.data.reduce((a,s)=>a+ +s.value,0)).toLocaleString()}</div></div>`+
      j.data.map(s=>`<div onclick="storePick(${s.id})" style="cursor:pointer;border:2px solid ${on==s.id?'var(--primary-color)':'var(--gray-200)'};background:${on==s.id?'#eff6ff':'#fff'};border-radius:8px;padding:14px;transition:border-color .15s ease-out,background .15s ease-out;">
        <div style="display:flex;justify-content:space-between;gap:6px;"><div style="font-size:14px;font-weight:600;color:var(--gray-900);">${escHtml(s.name)}</div>
          <div style="white-space:nowrap"><button title="Edit" onclick="event.stopPropagation();storeEdit(${s.id})" style="border:0;background:none;cursor:pointer;color:var(--gray-500)"><i class="fas fa-pen"></i></button><button title="Delete" onclick="event.stopPropagation();storeDelete(${s.id})" style="border:0;background:none;cursor:pointer;color:var(--danger-color)"><i class="fas fa-trash"></i></button></div></div>
        <div style="font-size:11px;color:var(--gray-500);margin-top:2px;">Code: ${escHtml(s.code)} | Account: ${escHtml(s.account_code||'—')}</div>
        <div style="font-size:11px;color:var(--primary-color);margin-top:2px;">COA: ${escHtml(s.account_name||'Not linked')}</div>
        <div style="font-size:13px;font-weight:600;margin-top:8px;">${s.item_count} items · ${Number(s.qty).toLocaleString()} units</div>
        <div style="font-size:12px;color:var(--gray-500);">Worth RWF ${Math.round(s.value).toLocaleString()}</div>
      </div>`).join('');
    } else div.innerHTML='<p style="color:var(--gray-400);font-size:13px;">No stores configured.</p>';
  }catch(e){}
}

function storePick(id){window._storeFilter=id||0;storesLoad();deptLoad();loadStock();loadDashboard();if(id)document.getElementById('stock-tbody')?.scrollIntoView({behavior:'smooth',block:'start'});}
async function deptLoad(){
  const div=document.getElementById('dept-stores'); if(!div) return;
  try{
    const j=await (await fetch(invAPI+'stores_with_accounts&dept=1')).json(), on=window._storeFilter;
    div.innerHTML=(j.data||[]).map(s=>`<div onclick="storePick(${s.id})" style="cursor:pointer;border:2px solid ${on==s.id?'var(--primary-color)':'var(--gray-200)'};background:${on==s.id?'#eff6ff':'#fff'};border-radius:8px;padding:10px;">
      <div style="font-size:12px;font-weight:600;color:var(--gray-900);">${escHtml(s.name)}</div>
      <div style="font-size:12px;margin-top:4px;">${s.item_count} items</div>
      <div style="font-size:11px;color:var(--gray-500);">Worth RWF ${Math.round(s.value).toLocaleString()}</div></div>`).join('')||'<p style="color:var(--gray-400);font-size:13px;">No department stores.</p>';
  }catch(e){}
}
function storeEdit(id){
  const s=window._stores[id]; if(!s) return;
  const name=prompt('Store name:',s.name); if(name===null) return;
  const code=prompt('Store code:',s.code); if(code===null) return;
  const acc=prompt('Chart of Accounts code:',s.account_code||''); if(acc===null) return;
  fetch(invAPI+'update_store&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:name,code:code,account_code:acc})})
    .then(r=>r.json()).then(j=>{if(j.success){showToast('Store updated');storesLoad();loadStock();}else alert(j.message);});
}
function storeDelete(id){
  const s=window._stores[id]; if(!s||!confirm('Delete store "'+s.name+'"? It is deactivated and its history is kept. Only an empty store can be deleted.')) return;
  fetch(invAPI+'delete_store&id='+id,{method:'DELETE'}).then(r=>r.json()).then(j=>{if(j.success){showToast('Store deleted');if(window._storeFilter==id)window._storeFilter=0;storesLoad();loadStock();}else alert(j.message);});
}
function storeNew(){
  const name=prompt('Store Name (e.g. Main Medicine Store):');
  if(!name) return;
  const code=prompt('Store Code (e.g. MAIN):');
  if(!code) return;
  const accCode=prompt('Account Code for Chart of Accounts (under 1130 Inventory, e.g. 1133):');
  if(!accCode) return;
  fetch(invAPI+'create_store_with_account',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:name,code:code,account_code:accCode})})
    .then(r=>r.json()).then(j=>{if(j.success){alert('✓ Store created & linked to COA!');storesLoad();}else alert(j.message);});
}
storesLoad();deptLoad();
</script>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Stats -->
    <div id="inv-stats"><div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Loading dashboard...</div></div>

    <!-- Stock Table -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Current Stock</h3>
            <p id="stock-count" style="font-size: 13px; color: var(--gray-500);">Loading...</p>
        </div>
    </div>

    <div id="stock-empty" style="display:none; text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;"><i class="fas fa-store"></i></div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Stock Items</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Receive stock to start managing inventory.</p>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=stock&action=receive'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Receive Stock
        </button>
    </div>

    <div id="stock-header" style="display:none;">
        <!-- Search -->
        <div style="display:flex;gap:8px;margin-bottom:12px;">
          <input id="cs-search" type="text" placeholder="Search items..." oninput="csSearchDebounce()" style="flex:1;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
        <div style="overflow-x:auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;white-space:nowrap;">
            <thead>
                <tr style="background: var(--gray-50);">
                    <th style="padding: 8px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Code</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Item</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Store</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Batch</th>
                    <th style="padding: 8px; text-align: center; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Qty</th>
                    <th style="padding: 8px; text-align: right; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Cost</th>
                    <th style="padding: 8px; text-align: right; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Private</th>
                    <th style="padding: 8px; text-align: left; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Expiry</th>
                    <th style="padding: 8px; text-align: center; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Status</th>
                    <th style="padding: 8px; text-align: center; font-weight: 600; border-bottom: 2px solid var(--gray-200);">Prices</th>
                </tr>
            </thead>
            <tbody id="stock-tbody"></tbody>
        </table>
        </div>
        <div id="cs-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:12px 0;"></div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
STOCK MANAGEMENT
============================================ -->
<?php elseif ($subModule == 'stock'): ?>

<?php if ($action == 'receive'): ?>
<!-- RECEIVE STOCK FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-arrow-down" style="color: var(--success-color);"></i> Receive Stock
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Item <span style="color: var(--danger-color);">*</span>
            </label>
            <select id="rs-item" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Store <span style="color: var(--danger-color);">*</span>
            </label>
            <select id="rs-store" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Quantity <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="number" id="rs-qty" step="0.01" min="0" placeholder="Enter quantity" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Batch Number
            </label>
            <input type="text" id="rs-batch" placeholder="Auto-generated if empty" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Unit Cost (RWF)
            </label>
            <input type="number" id="rs-cost" step="0.01" min="0" placeholder="Unit cost" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Selling Price (RWF)
            </label>
            <input type="number" id="rs-price" step="0.01" min="0" placeholder="Selling price" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Expiry Date
            </label>
            <input type="date" id="rs-expiry" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Received Date
            </label>
            <input type="date" id="rs-date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Reorder Level
            </label>
            <input type="number" id="rs-reorder" step="0.01" min="0" placeholder="Min stock alert level" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Min Stock Level
            </label>
            <input type="number" id="rs-min" step="0.01" min="0" placeholder="Minimum stock" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div style="grid-column: 1 / -1;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Notes
            </label>
            <textarea id="rs-notes" rows="2" placeholder="Additional notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>
    <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <button onclick="saveReceiveStock()" style="flex: 1; padding: 12px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-save"></i> Receive Stock
        </button>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=stock'" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
            Cancel
        </button>
    </div>
</div>

<?php elseif ($action == 'issue'): ?>
<!-- ISSUE STOCK FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-arrow-up" style="color: var(--danger-color);"></i> Issue / Dispense Stock
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Item <span style="color: var(--danger-color);">*</span>
            </label>
            <select id="is-item" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Store <span style="color: var(--danger-color);">*</span>
            </label>
            <select id="is-store" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Quantity <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="number" id="is-qty" step="0.01" min="0.01" placeholder="Enter quantity to issue" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div style="grid-column: 1 / -1;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Notes / Reason
            </label>
            <textarea id="is-notes" rows="2" placeholder="Reason for issuing stock" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>
    <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <button onclick="saveIssueStock()" style="flex: 1; padding: 12px; background: var(--danger-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-arrow-up"></i> Issue Stock
        </button>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=stock'" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
            Cancel
        </button>
    </div>
</div>

<?php else: ?>
<!-- STOCK TABLE VIEW (shared with central-store) -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">All Stock Items</h3>
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.location.href='inventory.php?module=inventory&sub=stock&action=receive'" style="padding: 8px 16px; background: var(--success-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">
                <i class="fas fa-arrow-down"></i> Receive
            </button>
            <button onclick="window.location.href='inventory.php?module=inventory&sub=stock&action=issue'" style="padding: 8px 16px; background: var(--danger-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px;">
                <i class="fas fa-arrow-up"></i> Issue
            </button>
        </div>
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead>
            <tr style="background: var(--gray-50);">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Code</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Item</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Category</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Store</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Batch</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Qty</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Expiry</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Cost</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Action</th>
            </tr>
        </thead>
        <tbody id="stock-tbody"><tr><td colspan="10" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody>
    </table>
</div>
<?php endif; ?>
<!-- ============================================
ITEMS MASTER
============================================ -->
<?php elseif ($subModule == 'items'): ?>

<?php if ($action == 'add'): ?>
<!-- CREATE ITEM FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-box" style="color: var(--primary-color);"></i> Create New Item
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Item Name <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" id="fi-name" placeholder="Enter item name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Item Code
            </label>
            <input type="text" id="fi-code" placeholder="Auto-generated if empty" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Category <span style="color: var(--danger-color);">*</span>
            </label>
            <select id="fi-category" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Unit of Measure
            </label>
            <select id="fi-uom" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Loading...</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Generic Name
            </label>
            <input type="text" id="fi-generic" placeholder="Generic name if medication" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div style="display: flex; align-items: center; gap: 20px; padding-top: 8px;">
            <label style="display: flex; align-items: center; gap: 6px; font-size: 14px; cursor: pointer;">
                <input type="checkbox" id="fi-medication"> Is Medication
            </label>
            <label style="display: flex; align-items: center; gap: 6px; font-size: 14px; cursor: pointer;">
                <input type="checkbox" id="fi-controlled"> Controlled Substance
            </label>
        </div>
        <div style="grid-column: 1 / -1;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Description
            </label>
            <textarea id="fi-desc" rows="2" placeholder="Item description" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>
    <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <button onclick="saveItem()" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-save"></i> Save Item
        </button>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=items'" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
            Cancel
        </button>
    </div>
</div>

<?php else: ?>
<div style="display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid #e7ebf1;margin-bottom:20px;"><button id="tab-items" onclick="itemsTab('items')" style="padding:12px 20px;background:none;border:0;border-bottom:2px solid transparent;color:#6b7890;font-size:15px;font-weight:700;cursor:pointer;">Items</button><button id="tab-icd" onclick="itemsTab('icd')" style="padding:12px 20px;background:none;border:0;border-bottom:2px solid transparent;color:#6b7890;font-size:15px;font-weight:700;cursor:pointer;">ICD-11 Diagnoses</button><button data-admin id="tab-adjust" onclick="itemsTab('adjust')" style="padding:12px 20px;background:none;border:0;border-bottom:2px solid transparent;color:#6b7890;font-size:15px;font-weight:700;cursor:pointer;">Adjust Stock</button><button id="tab-movement" onclick="itemsTab('movement')" style="display:none;"></button><button id="tab-low" onclick="itemsTab('low')" style="padding:12px 20px;background:none;border:0;border-bottom:2px solid transparent;color:#6b7890;font-size:15px;font-weight:700;cursor:pointer;">Low Stock</button><button id="tab-consume" onclick="itemsTab('consume')" style="padding:12px 20px;background:none;border:0;border-bottom:2px solid transparent;color:#6b7890;font-size:15px;font-weight:700;cursor:pointer;">Stock Consume</button><button id="tab-zero" onclick="itemsTab('zero')" style="padding:12px 20px;background:none;border:0;border-bottom:2px solid transparent;color:#6b7890;font-size:15px;font-weight:700;cursor:pointer;">Zero Fees <span id="zero-n" style="display:none;margin-left:4px;padding:1px 6px;border-radius:9px;background:#ef4444;color:#fff;font-size:11px;"></span></button></div>
<!-- ITEMS TABLE -->
<div id="items-card" style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
      <h3 style="font-size:16px;font-weight:600;margin:0;"><i class="fas fa-boxes" style="color:var(--primary-color);margin-right:6px;"></i>Items Catalog</h3>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="text" id="items-search" placeholder="Search name, code, generic..." oninput="itemsFilterDebounce()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:220px;">
        <select id="items-filter-category" onchange="itemsApplyFilter()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;color:var(--gray-700);">
          <option value="">All Categories</option>
        </select>
        <select id="items-filter-type" onchange="itemsApplyFilter()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;color:var(--gray-700);">
          <option value="">All Types</option>
          <option value="medication">Medication</option>
          <option value="general">General</option>
        </select>
        <select id="items-filter-status" onchange="itemsApplyFilter()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;color:var(--gray-700);">
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
        <button onclick="loadItemsMaster()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
      </div>
    </div>
    <div id="items-count" style="font-size:12px;color:var(--gray-500);margin-bottom:12px;"></div>
    <div id="items-empty" style="display:none; text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;"><i class="fas fa-boxes"></i></div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Items</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create items to start building your inventory catalog.</p>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=items&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Item
        </button>
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead>
            <tr style="background: var(--gray-50);">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Code</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Name</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Generic</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Category</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">UOM</th>
                <th style="padding: 12px; text-align: center; border-bottom: 2px solid var(--gray-200);">Stock</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Type</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Status</th>
                <th style="padding: 12px; text-align: center; border-bottom: 2px solid var(--gray-200);">Action</th>
            </tr>
        </thead>
        <tbody id="items-tbody"><tr><td colspan="9" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody>
    </table>
    <div id="items-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-top:1px solid var(--gray-200);margin-top:8px;"></div>
</div>
<!-- ICD-11 DIAGNOSES -->
<div id="icd-card" style="display:none;background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px;">
    <h3 style="font-size:16px;font-weight:600;margin:0;"><i class="fas fa-stethoscope" style="color:var(--primary-color);margin-right:6px;"></i>ICD-11 Diagnoses</h3>
    <input type="text" id="icd-search" placeholder="Search diagnosis, ICD code, category..." oninput="icdSearchDebounce()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:280px;">
  </div>
  <div id="icd-add" style="display:none;margin-bottom:12px;padding:12px;background:var(--gray-50);border-radius:8px;font-size:13px;">
    Not in the list. Add <strong id="icd-add-name"></strong>:
    <input id="icd-add-code" placeholder="ICD-11 code (e.g. 1F40)" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;width:160px;margin:0 6px;">
    <input id="icd-add-cat" placeholder="Category" style="padding:6px 10px;border:1px solid var(--gray-200);border-radius:6px;width:160px;margin-right:6px;">
    <button onclick="icdAdd()" style="padding:6px 14px;background:var(--primary-color);color:#fff;border:none;border-radius:6px;cursor:pointer;">Add</button>
  </div>
  <div id="icd-count" style="font-size:12px;color:var(--gray-500);margin-bottom:8px;"></div>
  <table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;width:140px;">ICD-11 code</th><th style="padding:10px;text-align:left;">Diagnosis</th><th style="padding:10px;text-align:left;">Category</th></tr></thead><tbody id="icd-tbody"></tbody></table>
</div>
<!-- ADJUST STOCK TAB -->
<div id="adjust-card" data-admin style="display:none;background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <h3 style="font-size:16px;font-weight:600;margin:0 0 4px;"><i class="fas fa-balance-scale" style="color:var(--primary-color);margin-right:6px;"></i>Adjust Stock Quantity &amp; Post Journal Entry</h3>
  <p style="font-size:12px;color:var(--gray-500);margin:0 0 14px;">Pick an item, then set the new quantity. A balanced journal entry is posted automatically.</p>
  <div style="max-width:680px;">
    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">Item <span style="color:var(--danger-color);">*</span></label>
    <input id="adj-pick" list="inv-items-dl" placeholder="Search item by name or code..." oninput="invPickChanged(this, openAdjustFromPick)" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;">
    <div id="adj-panel" style="margin-top:16px;"></div>
  </div>
</div>
<!-- STOCK MOVEMENT TAB -->
<div id="movement-card" style="display:none;">
  <div style="background:white;border-radius:var(--radius);padding:16px 24px;box-shadow:var(--shadow);margin-bottom:16px;">
    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:var(--gray-700);">Item <span style="color:var(--danger-color);">*</span></label>
    <input id="sr-pick" list="inv-items-dl" placeholder="Search item by name or code..." oninput="invPickChanged(this, srOpenMovementFromPick)" style="width:100%;max-width:680px;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;">
  </div>
  <div id="sr-inline-host"></div>
</div>
<!-- STOCK TRANSFER TAB -->
<div id="transfer-card" data-admin style="display:none;background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div id="tf-inline" style="margin-bottom:20px;"></div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
    <h3 style="font-size:14px;font-weight:600;margin:0;">Recent Transfers</h3>
    <button onclick="tfLoad()" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh list"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
        <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Transfer #</th>
        <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">From &rarr; To Store</th>
        <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Date</th>
        <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Transferred Items</th>
        <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
        <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
      </tr></thead>
      <tbody id="tf-tbody"><tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading transfers...</td></tr></tbody>
    </table>
  </div>
</div>
<script src="js/inv-transfers.js?v=<?= filemtime(__DIR__ . '/js/inv-transfers.js') ?>"></script>
<script>
// ========== ICD-11 DIAGNOSES (tab on Items page) ==========
const diagAPI = 'api.php?endpoint=consultation/diagnoses';
const itemsTabs = ['items', 'low', 'icd', 'adjust', 'movement', 'transfer', 'consume', 'zero'];
let itemsLowOnly = false;
function itemsTab(t) {
    if (!itemsTabs.includes(t) || !document.getElementById('tab-' + t)) t = 'items';
    if (itemsLowOnly !== (t === 'low')) { itemsLowOnly = t === 'low'; if (typeof itemsApplyFilter === 'function' && itemsMasterData.length) itemsApplyFilter(); }
    itemsTabs.forEach(k => {
        const card = document.getElementById((k === 'low' ? 'items' : k) + '-card'), b = document.getElementById('tab-' + k);
        if (card && k !== 'low') card.style.display = (k === t || (k === 'items' && t === 'low')) ? '' : 'none';
        if (b) { const on = k === t; b.style.color = on ? '#1769e0' : '#6b7890'; b.style.borderBottomColor = on ? '#1769e0' : 'transparent'; }
    });
    if (t === 'icd') icdLoad();
    if (t === 'transfer') { if (!document.getElementById('tf-inline').firstElementChild) tfNew(); tfLoad(); }
    if (t === 'movement') srEnsureMovementModalDOM();
    if (t === 'adjust' || t === 'movement' || t === 'consume') invTabLoadItems();
    if (t === 'zero') zfRender();
}
let _icdT;
function icdSearchDebounce() { clearTimeout(_icdT); _icdT = setTimeout(icdLoad, 250); }
async function icdLoad() {
    const q = document.getElementById('icd-search').value.trim();
    const j = await (await fetch(diagAPI + '&search=' + encodeURIComponent(q))).json();
    const rows = j.data || [];
    document.getElementById('icd-tbody').innerHTML = rows.map(d => '<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:8px 10px;font-family:monospace;color:var(--primary-color);">' + escHtml(d.code || '—') + '</td><td style="padding:8px 10px;">' + escHtml(d.name) + '</td><td style="padding:8px 10px;color:var(--gray-500);">' + escHtml(d.category || '') + '</td></tr>').join('') || '<tr><td colspan="3" style="padding:24px;text-align:center;color:var(--gray-400);">No diagnosis found</td></tr>';
    document.getElementById('icd-count').textContent = rows.length + (rows.length === 100 ? '+ shown (type to narrow)' : ' shown');
    const add = document.getElementById('icd-add');
    add.style.display = q.length >= 3 && !rows.some(d => d.name.toLowerCase() === q.toLowerCase()) ? '' : 'none';
    document.getElementById('icd-add-name').textContent = q;
}
async function icdAdd() {
    const name = document.getElementById('icd-search').value.trim();
    const j = await (await fetch(diagAPI, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({name, code: document.getElementById('icd-add-code').value, category: document.getElementById('icd-add-cat').value})})).json();
    showToast(j.success ? 'Diagnosis added' : j.message);
    if (j.success) icdLoad();
}
</script>

<!-- ZERO FEES TAB -->
<div id="zero-card" style="display:none;background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <h3 style="font-size:16px;font-weight:600;margin:0 0 4px;"><i class="fas fa-tag" style="color:#ef4444;margin-right:8px;"></i>Items with no selling price</h3>
  <p style="font-size:12px;color:var(--gray-500);margin:0 0 14px;">Click Set Price to enter the selling price and any insurance prices.</p>
  <table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);text-align:left;"><th style="padding:8px 10px;">Item</th><th style="padding:8px 10px;">Code</th><th style="padding:8px 10px;">Category</th><th style="padding:8px 10px;"></th></tr></thead><tbody id="zero-tbody"></tbody></table>
</div>
<script>
function zfRender(){
  const list = (typeof itemsMasterData !== 'undefined' ? itemsMasterData : []).filter(i => !(parseFloat(i.selling_price) > 0));
  const n = document.getElementById('zero-n'); if (n) { n.textContent = list.length; n.style.display = list.length ? 'inline-block' : 'none'; }
  const tb = document.getElementById('zero-tbody'); if (!tb) return;
  tb.innerHTML = list.length ? list.map(i => '<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:8px 10px;font-weight:600;">' + escHtml(i.name) + '</td><td style="padding:8px 10px;font-family:monospace;">' + escHtml(i.item_code || '') + '</td><td style="padding:8px 10px;">' + escHtml(i.category_name || '—') + '</td><td style="padding:8px 10px;"><button onclick="editItem(' + i.id + ')" style="padding:5px 10px;background:#7c3aed;color:#fff;border:0;border-radius:6px;cursor:pointer;"><i class="fas fa-tag"></i> Set Price</button></td></tr>').join('') : '<tr><td colspan="4" style="padding:30px;text-align:center;color:var(--gray-400);">Every item has a selling price.</td></tr>';
}
</script>

<!-- STOCK CONSUME TAB -->
<div id="consume-card" style="display:none;background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <h3 style="font-size:16px;font-weight:600;margin:0 0 14px;"><i class="fas fa-minus-circle" style="color:#d97706;margin-right:8px;"></i>Consume Item</h3>
  <div style="max-width:560px;">
    <div style="margin-bottom:14px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Item <span style="color:var(--danger-color);">*</span></label>
      <input id="consume-pick" list="inv-items-dl" placeholder="Search item by name or code..." oninput="invPickChanged(this, consumePick)" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div id="consume-alert" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>
    <div style="background:var(--gray-50);border-radius:8px;padding:12px 14px;margin-bottom:16px;">
      <div style="font-weight:600;font-size:14px;" id="consume-item-name">—</div>
      <div style="font-size:12px;color:var(--gray-500);margin-top:4px;" id="consume-item-info">—</div>
    </div>
    <input type="hidden" id="consume-item-id">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Store <span style="color:var(--danger-color);">*</span></label>
        <select id="consume-store" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="">Select store...</option>
        </select>
      </div>
      <div>
        <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Quantity <span style="color:var(--danger-color);">*</span></label>
        <input type="number" id="consume-qty" min="1" value="1" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
    </div>
    <div style="margin-bottom:14px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Entry Type <span style="color:var(--danger-color);">*</span></label>
      <select id="consume-entry-type" onchange="consumeEntryTypeChanged()" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="income">Used for / sold to a patient (Cost of Goods Sold)</option>
        <option value="expense">Expense (Medical Expenses)</option>
      </select>
      <div id="consume-entry-note" style="font-size:11px;color:var(--gray-500);margin-top:4px;"></div>
    </div>
    <div id="consume-cash-account-row" style="display:none;margin-bottom:14px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Cash / Bank Account <span style="color:var(--danger-color);">*</span></label>
      <select id="consume-cash-account" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="3">Cash & Cash equivalents</option>
        <option value="1000">BANK OF KIGALI (000460777333283)</option>
        <option value="1001">BK Code 11528872</option>
        <option value="1002">MTN CODE (528872)</option>
        <option value="1003">Petty cash</option>
      </select>
      <div style="font-size:11px;color:var(--gray-500);margin-top:4px;">Select which account received the payment</div>
    </div>
    <div style="margin-bottom:14px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Reason / Notes</label>
      <input type="text" id="consume-reason" placeholder="e.g., Used in ward, patient consumption, expired..." style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
    <div id="consume-summary" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;"></div>
    <button id="consume-submit-btn" onclick="submitConsume()" style="width:100%;padding:12px;background:#d97706;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;">
      <i class="fas fa-check"></i> Confirm Consumption
    </button>
  </div>
</div>

<script>
let consumeItemData = {};

function consumePick(it){
  const itemId=it.id, itemName=it.name, isMedication=!!parseInt(it.is_medication), unitCost=parseFloat(it.unit_price)||0, sellingPrice=parseFloat(it.selling_price)||0;
  openConsumeModal(itemId, itemName, isMedication, unitCost, sellingPrice);
}

async function openConsumeModal(itemId, itemName, isMedication, unitCost, sellingPrice){
  consumeItemData = {id:itemId, name:itemName, is_medication:isMedication, unit_price:unitCost, selling_price:sellingPrice};
  document.getElementById('consume-item-id').value = itemId;
  document.getElementById('consume-item-name').textContent = itemName;
  document.getElementById('consume-item-info').textContent = (isMedication?'Medication':'General Item') + ' | Cost: '+Number(unitCost).toLocaleString()+' RWF | Sell: '+Number(sellingPrice).toLocaleString()+' RWF';
  document.getElementById('consume-alert').style.display='none';
  document.getElementById('consume-qty').value=1;
  document.getElementById('consume-reason').value='';
  
  // Set default entry type based on item type
  const entryType = document.getElementById('consume-entry-type');
  if(isMedication) entryType.value='income';
  else entryType.value='expense';
  consumeEntryTypeChanged();
  
  // Load stores
  const storeSel = document.getElementById('consume-store');
  storeSel.innerHTML='<option value="">Loading...</option>';
  try{
    const r=await fetch(invAPI+'stores');
    const j=await r.json();
    if(j.success && j.data){
      storeSel.innerHTML='<option value="">Select store...</option>'+j.data.map(s=>`<option value="${s.id}">${escHtml(s.name)}</option>`).join('');
    }
  }catch(e){storeSel.innerHTML='<option value="">Error loading stores</option>';}
  
}

function resetConsumeForm(){
  consumeItemData={};
  document.getElementById('consume-pick').value='';
  document.getElementById('consume-item-id').value='';
  document.getElementById('consume-item-name').textContent='—';
  document.getElementById('consume-item-info').textContent='—';
  document.getElementById('consume-qty').value=1;
  document.getElementById('consume-reason').value='';
  document.getElementById('consume-store').innerHTML='<option value="">Select store...</option>';
  document.getElementById('consume-summary').style.display='none';
}

function consumeEntryTypeChanged(){
  const type=document.getElementById('consume-entry-type').value;
  const note=document.getElementById('consume-entry-note');
  const summary=document.getElementById('consume-summary');
  const cashRow=document.getElementById('consume-cash-account-row');
  const qty=parseInt(document.getElementById('consume-qty').value)||1;
  const cost=consumeItemData.unit_price||0;
  const sell=consumeItemData.selling_price||0;
  
  // Revenue is booked when the patient is billed, so no cash account is asked for here
  cashRow.style.display = 'none';
  
  if(type==='income'){
    note.textContent='Records: Dr Cost of Goods Sold, Cr Inventory — at cost. The sale itself is recorded when the patient is billed (invoice or pharmacy POS).';
    summary.style.display='block';
    summary.innerHTML='<strong>Journal:</strong> Dr Cost of Goods Sold '+Number(qty*cost).toLocaleString()+' RWF | Cr Inventory (Store) '+Number(qty*cost).toLocaleString()+' RWF';
  } else if(type==='expense'){
    note.textContent='Records: Dr Medical Expenses, Cr Inventory — at cost price.';
    summary.style.display='block';
    summary.innerHTML='<strong>Journal:</strong> Dr Medical Expenses '+Number(qty*cost).toLocaleString()+' RWF | Cr Inventory (Store) '+Number(qty*cost).toLocaleString()+' RWF';
  }
}

// Update summary when quantity changes
document.getElementById('consume-qty')?.addEventListener('input', consumeEntryTypeChanged);

async function submitConsume(){
  const btn=document.getElementById('consume-submit-btn');
  const al=document.getElementById('consume-alert');
  al.style.display='none';
  
  const itemId=parseInt(document.getElementById('consume-item-id').value);
  if(!itemId){al.textContent='Please select an item.';al.style.cssText='display:block;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';return;}
  const storeId=parseInt(document.getElementById('consume-store').value);
  const qty=parseInt(document.getElementById('consume-qty').value);
  const entryType=document.getElementById('consume-entry-type').value;
  const reason=document.getElementById('consume-reason').value.trim();
  
  if(!storeId){al.textContent='Please select a store.';al.style.cssText='display:block;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';return;}
  if(!qty||qty<1){al.textContent='Quantity must be at least 1.';al.style.cssText='display:block;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';return;}
  
  
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Processing...';
  
  try{
    const r=await fetch(invAPI+'consume_item',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({item_id:itemId, store_id:storeId, quantity:qty, entry_type:entryType, reason:reason})
    });
    const j=await r.json();
    if(j.success){
      al.innerHTML='<i class="fas fa-check-circle"></i> '+j.message;
      al.style.cssText='display:block;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;';
      setTimeout(()=>{resetConsumeForm();al.style.display='none';loadItemsMaster();},1500);
    } else {
      al.textContent=j.message||'Failed to consume item.';
      al.style.cssText='display:block;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';
    }
  }catch(e){
    al.textContent='Error: '+e.message;
    al.style.cssText='display:block;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;';
  }finally{
    btn.disabled=false;btn.innerHTML='<i class="fas fa-check"></i> Confirm Consumption';
  }
}

// ========== SHARED ITEM PICKER + TAB DEEP LINK ==========
let invTabItems=null;
async function invTabLoadItems(){
  if(invTabItems) return invTabItems;
  try{
    const j=await (await fetch(invAPI+'items&active=true&limit=500')).json();
    invTabItems=j.success?(j.data||[]):[];
  }catch(e){invTabItems=[];}
  const dl=document.createElement('datalist');dl.id='inv-items-dl';
  dl.innerHTML=invTabItems.map(i=>'<option value="'+escHtml((i.item_code?i.item_code+' - ':'')+i.name)+'"></option>').join('');
  document.body.appendChild(dl);
  return invTabItems;
}
function invPickChanged(inp, cb){
  const it=(invTabItems||[]).find(i=>((i.item_code?i.item_code+' - ':'')+i.name)===inp.value);
  if(it) cb(it);
}
function openAdjustFromPick(it){ openAdjustStockForItem(it.id, it.name); }
function srOpenMovementFromPick(it){ srOpenMovementModal(it.id, it.name, it.item_code||''); }

if(!invIsAdmin) document.querySelectorAll('[data-admin]').forEach(e=>e.remove());
(async function(){
  const q=new URLSearchParams(window.location.search), tab=q.get('tab');
  if(!tab||tab==='items') return;
  itemsTab(tab);
  const id=q.get('item_id'), it=id?(await invTabLoadItems()).find(i=>i.id==id):null;
  if(!it) return;
  const cur=['adjust','movement','consume'].includes(tab)?tab:'';
  if(cur==='adjust'&&invIsAdmin) openAdjustFromPick(it);
  else if(cur==='movement') srOpenMovementFromPick(it);
  else if(cur==='consume'){document.getElementById('consume-pick').value=(it.item_code?it.item_code+' - ':'')+it.name;consumePick(it);}
})();
</script>

<?php endif; ?>
<!-- ============================================
CATEGORIES
============================================ -->
<?php elseif ($subModule == 'categories'): ?>

<?php if ($action == 'add'): ?>
<!-- CREATE CATEGORY FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-tags" style="color: var(--primary-color);"></i> Create Category
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Category Name <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" id="fc-name" placeholder="e.g., Medications" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Code
            </label>
            <input type="text" id="fc-code" placeholder="e.g., MED" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div style="grid-column: 1 / -1;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Description
            </label>
            <textarea id="fc-desc" rows="2" placeholder="Category description" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>
    <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <button onclick="saveCategory()" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-save"></i> Save Category
        </button>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=categories'" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
            Cancel
        </button>
    </div>
</div>

<?php else: ?>
<!-- CATEGORIES TABLE -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div id="cat-empty" style="display:none; text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;"><i class="fas fa-tags"></i></div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Categories</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create categories to classify your items.</p>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=categories&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Category
        </button>
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead>
            <tr style="background: var(--gray-50);">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Name</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Code</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Description</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Level</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Action</th>
            </tr>
        </thead>
        <tbody id="cat-tbody"><tr><td colspan="5" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============================================
SUPPLIERS
============================================ -->
<?php elseif ($subModule == 'suppliers'): ?>

<?php if ($action == 'add'): ?>
<!-- CREATE SUPPLIER FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-truck" style="color: var(--primary-color);"></i> Create Supplier
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Company Name <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" id="fs-name" placeholder="Company name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Supplier Code
            </label>
            <input type="text" id="fs-code" placeholder="Auto-generated if empty" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Contact Person
            </label>
            <input type="text" id="fs-contact" placeholder="Contact person name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Phone
            </label>
            <input type="text" id="fs-phone" placeholder="Phone number" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Email
            </label>
            <input type="email" id="fs-email" placeholder="Email address" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Category
            </label>
            <select id="fs-category" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select...</option>
                <option value="pharmaceutical">Pharmaceutical</option>
                <option value="medical-equipment">Medical Equipment</option>
                <option value="laboratory">Laboratory</option>
                <option value="office">Office Supplies</option>
                <option value="maintenance">Maintenance</option>
                <option value="food">Food & Nutrition</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Payment Terms
            </label>
            <input type="text" id="fs-terms" placeholder="e.g., Net 30" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Tax ID
            </label>
            <input type="text" id="fs-tax" placeholder="Tax identification number" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Address
            </label>
            <input type="text" id="fs-address" placeholder="Physical address" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div style="grid-column: 1 / -1;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Notes
            </label>
            <textarea id="fs-notes" rows="2" placeholder="Additional notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>
    <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <button onclick="saveSupplier()" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-save"></i> Save Supplier
        </button>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=suppliers'" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
            Cancel
        </button>
    </div>
</div>

<?php else: ?>
<!-- SUPPLIERS TABLE -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div id="suppliers-empty" style="display:none; text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;"><i class="fas fa-truck"></i></div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Suppliers</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Add suppliers for procurement.</p>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=suppliers&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Supplier
        </button>
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead>
            <tr style="background: var(--gray-50);">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Code</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Company</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Contact</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Phone</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Email</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Category</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Action</th>
            </tr>
        </thead>
        <tbody id="suppliers-tbody"><tr><td colspan="7" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody>
    </table>
</div>
<?php endif; ?>
<!-- ============================================
PROCUREMENT
============================================ -->
<?php elseif ($subModule == 'procurement'): ?>

<?php if ($action == 'create_pr'): ?>
<!-- CREATE PURCHASE REQUEST FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-file-invoice" style="color: var(--primary-color);"></i> Create Purchase Request
    </h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Priority
            </label>
            <select id="pr-priority" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="medium">Medium</option>
                <option value="low">Low</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Required By
            </label>
            <input type="date" id="pr-required" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div style="grid-column: 1 / -1;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Justification
            </label>
            <textarea id="pr-just" rows="2" placeholder="Reason for this purchase request" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>

    <h4 style="font-size: 15px; font-weight: 600; color: var(--gray-700); margin-bottom: 12px;">Request Items</h4>
    <div id="pr-items-container">
        <div class="pr-item-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 40px; gap: 8px; margin-bottom: 8px; align-items: end;">
            <div>
                <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">Item</label>
                <select class="pr-item-select" style="width: 100%; padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 13px; background: white;">
                    <option value="">Select...</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">Quantity</label>
                <input type="number" class="pr-qty" step="0.01" min="0" placeholder="Qty" style="width: 100%; padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 13px;">
            </div>
            <div>
                <label style="display: block; font-size: 12px; color: var(--gray-500); margin-bottom: 4px;">Est. Price</label>
                <input type="number" class="pr-price" step="0.01" min="0" placeholder="Unit price" style="width: 100%; padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 13px;">
            </div>
            <div style="display: flex; align-items: end; padding-bottom: 4px;">
                <button onclick="this.closest('.pr-item-row').remove()" style="background: #fee2e2; border: none; border-radius: 4px; width: 32px; height: 32px; cursor: pointer; color: #991b1b;"><i class="fas fa-times"></i></button>
            </div>
        </div>
    </div>
    <button onclick="addPRItemRow()" style="padding: 8px 16px; background: var(--gray-100); border: 1px dashed var(--gray-300); border-radius: 6px; cursor: pointer; font-size: 13px; color: var(--gray-600); width: 100%;">
        <i class="fas fa-plus"></i> Add Item Row
    </button>

    <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
        <button onclick="savePurchaseRequest()" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-save"></i> Submit Purchase Request
        </button>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=procurement'" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
            Cancel
        </button>
    </div>
</div>

<script>
async function addPRItemRow() {
    const container = document.getElementById('pr-items-container');
    const row = document.createElement('div');
    row.className = 'pr-item-row';
    row.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 40px; gap: 8px; margin-bottom: 8px; align-items: end;';
    row.innerHTML = '<div><label style="display:block;font-size:12px;color:var(--gray-500);margin-bottom:4px;">Item</label><select class="pr-item-select" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"><option value="">Select...</option></select></div>' +
        '<div><label style="display:block;font-size:12px;color:var(--gray-500);margin-bottom:4px;">Quantity</label><input type="number" class="pr-qty" step="0.01" min="0" placeholder="Qty" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>' +
        '<div><label style="display:block;font-size:12px;color:var(--gray-500);margin-bottom:4px;">Est. Price</label><input type="number" class="pr-price" step="0.01" min="0" placeholder="Unit price" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>' +
        '<div style="display:flex;align-items:end;padding-bottom:4px;"><button onclick="this.closest(\'.pr-item-row\').remove()" style="background:#fee2e2;border:none;border-radius:4px;width:32px;height:32px;cursor:pointer;color:#991b1b;"><i class="fas fa-times"></i></button></div>';
    container.appendChild(row);
    // Load items into this select
    try {
        const r = await fetch(invAPI + 'items&active=true');
        const j = await r.json();
        if (j.success && j.data) {
            const sel = row.querySelector('.pr-item-select');
            j.data.forEach(i => {
                sel.innerHTML += '<option value="' + i.id + '">' + escHtml(i.name) + ' (' + escHtml(i.item_code) + ')</option>';
            });
        }
    } catch(e) {}
}

async function savePurchaseRequest() {
    const items = [];
    document.querySelectorAll('.pr-item-row').forEach(row => {
        const itemId = parseInt(row.querySelector('.pr-item-select')?.value);
        const qty = parseFloat(row.querySelector('.pr-qty')?.value);
        const price = parseFloat(row.querySelector('.pr-price')?.value) || 0;
        if (itemId && qty > 0) {
            items.push({item_id: itemId, quantity: qty, estimated_unit_price: price});
        }
    });
    if (!items.length) return showToast('At least one item with quantity required', 'error');
    const data = {
        items: items,
        priority: document.getElementById('pr-priority')?.value || 'medium',
        required_by: document.getElementById('pr-required')?.value || null,
        justification: document.getElementById('pr-just')?.value || null
    };
    try {
        const r = await fetch(invAPI + 'create_purchase_request', {
            method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
        });
        const j = await r.json();
        if (j.success) { showToast('Purchase request created: ' + (j.data?.pr_number || '')); window.location.href = 'inventory.php?module=inventory&sub=procurement'; }
        else showToast(j.message, 'error');
    } catch(e) { showToast('Failed', 'error'); }
}
</script>

<?php else: ?>
<!-- PROCUREMENT TABLE -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div id="pr-empty" style="display:none; text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;"><i class="fas fa-file-invoice"></i></div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Purchase Requests</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create purchase requests to initiate procurement.</p>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=procurement&action=create_pr'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Create PR
        </button>
    </div>
    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead>
            <tr style="background: var(--gray-50);">
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">PR #</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Date</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Items</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Total Est.</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Status</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Priority</th>
                <th style="padding: 12px; text-align: left; border-bottom: 2px solid var(--gray-200);">Action</th>
            </tr>
        </thead>
        <tbody id="pr-tbody"><tr><td colspan="7" style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody>
    </table>
</div>
<?php endif; ?>

<!-- ============================================
STOCK AUDIT (static for now)
============================================ -->
<?php elseif ($subModule == 'audit'): ?>
<?php if ($action == 'add'): ?>
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-clipboard-check" style="color: var(--primary-color);"></i> Create Stock Audit
    </h3>
    <p style="color: var(--gray-500); margin-bottom: 16px;">Stock audits use the stock adjustments module. <a href="?module=inventory&sub=stock" style="color: var(--primary-color);">Go to stock management</a> to adjust quantities.</p>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Item
            </label>
            <select id="ad-item" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select Item</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Store
            </label>
            <select id="ad-store" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select Store</option>
            </select>
        </div>
    </div>
</div>
<?php else: ?>
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;"><i class="fas fa-clipboard-check"></i></div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">Stock Audits</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">To conduct a stock audit, go to <strong>Stock Management</strong> and adjust batch quantities as needed.</p>
        <button onclick="window.location.href='inventory.php?module=inventory&sub=stock'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-cubes"></i> Go to Stock
        </button>
    </div>
</div>
<?php endif; ?>

<?php elseif ($subModule == 'opening'): ?>
<!-- ====== OPENING STOCK ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-box-open" style="color:var(--primary-color);"></i> Record Opening Stock</h3>
  </div>
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">Record opening balances for items. This debits the store account and credits Opening Equity Balance (3200).</p>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Store *</label>
      <select id="os-store" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="">Loading stores...</option>
      </select>
    </div>
    <div>
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Date</label>
      <input type="date" id="os-date" value="<?php echo date('Y-m-d');?>" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
    </div>
  </div>

  <!-- Items to add -->
  <div style="margin-bottom:16px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <label style="font-size:13px;font-weight:600;">Items</label>
      <button onclick="osAddRow()" type="button" style="padding:5px 12px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-plus"></i> Add Item</button>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead><tr style="background:var(--gray-50);">
          <th style="padding:8px;text-align:left;">Item</th>
          <th style="padding:8px;text-align:center;width:70px;">Qty</th>
          <th style="padding:8px;text-align:center;width:90px;">Unit Cost</th>
          <th style="padding:8px;text-align:center;width:90px;">Sell Price</th>
          <th style="padding:8px;text-align:left;width:90px;">Batch #</th>
          <th style="padding:8px;text-align:center;width:100px;">Expiry</th>
          <th style="padding:8px;width:30px;"></th>
        </tr></thead>
        <tbody id="os-tbody"></tbody>
      </table>
    </div>
  </div>

  <button onclick="osSubmit()" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
    <i class="fas fa-save"></i> Record Opening Stock & Create Journal Entry
  </button>
</div>

<script>
let osItems=[];
async function osInit(){
  // Load stores for all selects on this page
  try{
    const sr=await fetch(invAPI+'stores');const sj=await sr.json();
    if(sj.success&&sj.data){
      const opts=sj.data.map(s=>'<option value="'+s.id+'">'+s.name+'</option>').join('');
      const osStore=document.getElementById('os-store');
      if(osStore) osStore.innerHTML='<option value="">Select Store...</option>'+opts;
      const othStore=document.getElementById('oth-store');
      if(othStore) othStore.innerHTML='<option value="">Select Store...</option>'+opts;
      const srStore=document.getElementById('sr-store');
      if(srStore) srStore.innerHTML='<option value="">All Stores</option>'+opts;
    }
  }catch(e){}
  try{
    const r=await fetch(invAPI+'items&active=true&limit=500');const j=await r.json();
    if(j.success) osItems=j.data;
  }catch(e){}
  osAddRow();
}

function osAddRow(){
  const tbody=document.getElementById('os-tbody');
  // Create datalist once
  if(!document.getElementById('os-items-dl')){
    const dl=document.createElement('datalist');dl.id='os-items-dl';
    osItems.forEach(i=>{const o=document.createElement('option');o.value=(i.item_code?i.item_code+' - ':'')+i.name;o.setAttribute('data-id',i.id);o.setAttribute('data-cost',i.unit_price||0);o.setAttribute('data-sell',i.selling_price||0);dl.appendChild(o);});
    document.body.appendChild(dl);
  }
  const row=document.createElement('tr');row.className='os-row';row.style.borderBottom='1px solid var(--gray-100)';
  row.innerHTML=`<td style="padding:4px;"><input type="text" class="os-item-search" list="os-items-dl" placeholder="Search item..." oninput="osMatchItem(this)" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"><input type="hidden" class="os-item"></td>
    <td style="padding:4px;"><input type="number" class="os-qty" value="1" min="1" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:center;"></td>
    <td style="padding:4px;"><input type="number" class="os-cost" placeholder="0" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:center;"></td>
    <td style="padding:4px;"><input type="number" class="os-sell" placeholder="0" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:center;"></td>
    <td style="padding:4px;"><input class="os-batch" placeholder="Batch" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
    <td style="padding:4px;"><input type="date" class="os-expiry" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
    <td style="padding:4px;"><button onclick="this.closest('tr').remove()" style="border:none;background:none;color:#dc2626;cursor:pointer;"><i class="fas fa-trash"></i></button></td>`;
  tbody.appendChild(row);
}

function osMatchItem(input){
  const val=input.value;
  const hidden=input.nextElementSibling;
  const row=input.closest('tr');
  const match=osItems.find(i=>((i.item_code?i.item_code+' - ':'')+i.name)===val);
  if(match){
    hidden.value=match.id;
    input.style.borderColor='#16a34a';
    row.querySelector('.os-cost').value=match.unit_price||'';
    row.querySelector('.os-sell').value=match.selling_price||'';
  } else {
    hidden.value='';
    input.style.borderColor=val.length>2?'#d97706':'var(--gray-200)';
  }
}

function osFillPrice(sel){
  const opt=sel.selectedOptions[0];
  const row=sel.closest('tr');
  if(opt){
    row.querySelector('.os-cost').value=opt.getAttribute('data-cost')||'';
    row.querySelector('.os-sell').value=opt.getAttribute('data-sell')||'';
  }
}

async function osSubmit(){
  if (!invIsAdmin) {
    alert('Permission denied: Only administrators can record opening stock.');
    return;
  }
  const storeId=document.getElementById('os-store').value;
  const date=document.getElementById('os-date').value;
  const rows=document.querySelectorAll('.os-row');
  const items=[];
  rows.forEach(row=>{
    const itemId=row.querySelector('.os-item').value;
    const qty=parseFloat(row.querySelector('.os-qty').value)||0;
    const cost=parseFloat(row.querySelector('.os-cost').value)||0;
    const sell=parseFloat(row.querySelector('.os-sell').value)||0;
    const batch=row.querySelector('.os-batch').value;
    const expiry=row.querySelector('.os-expiry').value||null;
    if(itemId&&qty>0) items.push({item_id:parseInt(itemId),quantity:qty,unit_cost:cost,selling_price:sell,batch_number:batch,expiry_date:expiry});
  });
  if(!items.length) return alert('Add at least one item.');
  if(!confirm('Record opening stock for '+items.length+' item(s)? This will create stock batches and a journal entry.')) return;

  try{
    const r=await fetch(invAPI+'opening_stock',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({store_id:parseInt(storeId),date:date,items:items})});
    const j=await r.json();
    if(j.success){alert('✓ Opening stock recorded!\n'+j.message);location.reload();}
    else alert('Error: '+j.message);
  }catch(e){alert('Failed: '+e.message);}
}
osInit();
</script>

<!-- ====== OTHER STOCK CATEGORY ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);margin-top:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-boxes" style="color:#7c3aed;"></i> Other Stock (Non-Medical / Supplies)</h3>
  </div>
  <p style="font-size:13px;color:var(--gray-500);margin-bottom:16px;">Record other stock items (cleaning supplies, office materials, etc.) that do not require insurance. This creates a journal entry debiting the supplies account.</p>

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
      <button onclick="othAddRow()" type="button" style="padding:5px 12px;background:#7c3aed;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-plus"></i> Add Item</button>
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
      Total: <span id="oth-total" style="margin-left:6px;color:var(--primary-color);">0 RWF</span>
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
function othAddRow(){
  const tbody=document.getElementById('oth-tbody');
  const row=document.createElement('tr');row.className='oth-row';row.style.borderBottom='1px solid var(--gray-100)';
  row.innerHTML=`<td style="padding:4px;"><input type="text" class="oth-name" placeholder="Item name" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
    <td style="padding:4px;"><input type="number" class="oth-qty" value="1" min="1" oninput="othCalcTotal()" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:center;"></td>
    <td style="padding:4px;"><select class="oth-measure" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;">
      <option value="pcs">Pieces</option>
      <option value="box">Box</option>
      <option value="pack">Pack</option>
      <option value="kg">Kg</option>
      <option value="ltr">Litre</option>
      <option value="roll">Roll</option>
      <option value="bottle">Bottle</option>
      <option value="set">Set</option>
      <option value="pair">Pair</option>
      <option value="other">Other</option>
    </select></td>
    <td style="padding:4px;"><input type="number" class="oth-cost" placeholder="0" min="0" oninput="othCalcTotal()" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;text-align:center;"></td>
    <td style="padding:4px;"><input type="text" class="oth-batch" placeholder="Batch #" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
    <td style="padding:4px;"><button onclick="this.closest('tr').remove();othCalcTotal()" style="border:none;background:none;color:#dc2626;cursor:pointer;"><i class="fas fa-trash"></i></button></td>`;
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
    const expiry=row.querySelector('.oth-expiry')?row.querySelector('.oth-expiry').value:null;
    if(name&&qty>0) items.push({name,quantity:qty,measure,unit_cost:cost,batch_number:batch,expiry_date:expiry});
  });
  if(!items.length) return alert('Add at least one item with a name and quantity.');
  if(!confirm('Record '+items.length+' other stock item(s)? This will create a journal entry.')) return;

  try{
    const r=await fetch(invAPI+'other_stock',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({store_id:parseInt(storeId),date:date,items:items})});
    const j=await r.json();
    if(j.success){alert('✓ Other stock recorded!\n'+(j.message||''));othLoadHistory();
      // Clear form
      document.getElementById('oth-tbody').innerHTML='';othAddRow();othCalcTotal();
    }
    else alert('Error: '+j.message);
  }catch(e){alert('Failed: '+e.message);}
}

async function othLoadHistory(){
  const tbody=document.getElementById('oth-hist-tbody');
  tbody.innerHTML='<tr><td colspan="7" style="padding:20px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const r=await fetch(invAPI+'other_stock');
    const j=await r.json();
    if(j.success && j.data && j.data.length){
      tbody.innerHTML=j.data.map(s=>`<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px;font-size:11px;">${s.date||s.created_at||'—'}</td>
        <td style="padding:8px;font-weight:500;">${s.name||'—'}</td>
        <td style="padding:8px;text-align:center;">${s.quantity||0}</td>
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

othAddRow();
othLoadHistory();
</script>

<?php elseif ($subModule == 'stock-report'): ?>
<!-- ====== STOCK REPORT: Opening, In, Out, Closing ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-chart-bar" style="color:var(--primary-color);"></i> Stock Report (Opening / In / Out / Closing)</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <button onclick="srExportCSV()" id="sr-btn-csv" style="display:none;padding:7px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;font-weight:500;color:var(--gray-700);"><i class="fas fa-file-csv" style="color:#16a34a;margin-right:4px;"></i> Export CSV</button>
      <button onclick="srPrintReport()" id="sr-btn-print" style="display:none;padding:7px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;font-weight:500;color:var(--gray-700);"><i class="fas fa-print" style="color:var(--primary-color);margin-right:4px;"></i> Print</button>
    </div>
  </div>

  <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap;background:var(--gray-50);padding:14px;border-radius:8px;border:1px solid var(--gray-200);">
    <div style="min-width:160px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">Store</label>
      <select id="sr-store" onchange="srLoad()" style="width:100%;padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
        <option value="">Loading stores...</option>
      </select>
    </div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">From</label><input type="date" id="sr-from" onchange="srLoad()" style="padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;"></div>
    <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">To</label><input type="date" id="sr-to" value="<?php echo date('Y-m-d');?>" onchange="srLoad()" style="padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;"></div>
    
    <div style="flex:1;min-width:220px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">Search Report</label>
      <div style="position:relative;">
        <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--gray-400);font-size:12px;"></i>
        <input type="text" id="sr-search" placeholder="Search item, code, batch, store..." oninput="srFilter()" style="width:100%;padding:8px 28px 8px 30px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
        <button type="button" onclick="document.getElementById('sr-search').value='';srFilter();" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:12px;" title="Clear search">&times;</button>
      </div>
    </div>

    <div>
      <button onclick="srLoad()" style="padding:8px 20px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
        <i class="fas fa-sync-alt"></i> Generate
      </button>
    </div>
  </div>

  <div id="sr-summary-bar" style="display:none;margin-bottom:14px;gap:12px;flex-wrap:wrap;"></div>

  <div id="sr-content"><div style="padding:40px 20px;text-align:center;color:var(--gray-400);font-size:13px;"><i class="fas fa-info-circle" style="font-size:20px;margin-bottom:8px;display:block;color:var(--gray-300);"></i>Select date range and click <strong>Generate</strong> to view stock movements.</div></div>
</div>

<style>
@keyframes srModalIn {
  from { opacity: 0; transform: scale(0.97); }
  to { opacity: 1; transform: scale(1); }
}
.sr-clickable-item {
  color: var(--primary-color);
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.15s ease;
  background: none;
  border: none;
  padding: 0;
  font-family: inherit;
  font-size: inherit;
  text-align: left;
}
.sr-clickable-item:hover {
  text-decoration: underline;
  color: #1d4ed8;
}
.sr-clickable-item:hover i {
  transform: scale(1.15);
  color: #1d4ed8;
}
.sr-row-hover:hover {
  background-color: #f8fafc !important;
}
.sr-hist-btn {
  padding: 4px 8px;
  background: #f1f5f9;
  border: 1px solid var(--gray-200);
  border-radius: 4px;
  color: var(--primary-color);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.15s ease;
}
.sr-hist-btn:hover {
  background: var(--primary-color);
  color: white;
  border-color: var(--primary-color);
}
</style>

<script>
let srAllData = [];
let srPage = 1, srPerPage = 25, srLast = {data: [], q: ''};

// Initialize dates to 1st of month to today
document.getElementById('sr-from').value = new Date(new Date().setDate(1)).toISOString().split('T')[0];

// Load stores dynamically and then auto-load report
fetch(invAPI+'stores').then(r=>r.json()).then(j=>{
  if(j.success && j.data){
    srStoresList = j.data;
    const opts = j.data.map(s=>'<option value="'+s.id+'">'+escHtml(s.name)+'</option>').join('');
    document.getElementById('sr-store').innerHTML='<option value="">All Stores</option>'+opts;
    const modalStoreSel = document.getElementById('sr-modal-store');
    if (modalStoreSel) modalStoreSel.innerHTML='<option value="">All Stores</option>'+opts;
  }
  srLoad();
}).catch(e=>{ console.error('Error loading stores:', e); srLoad(); });

// Generate / Fetch Stock Report
async function srLoad(){
  const storeId = document.getElementById('sr-store').value;
  const from = document.getElementById('sr-from').value;
  const to = document.getElementById('sr-to').value;
  if(!from || !to) return alert('Select date range.');

  const div = document.getElementById('sr-content');
  div.innerHTML = '<div style="padding:40px;text-align:center;color:var(--gray-500);"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--primary-color);margin-bottom:10px;display:block;"></i> Generating stock report...</div>';
  document.getElementById('sr-summary-bar').style.display = 'none';
  document.getElementById('sr-btn-csv').style.display = 'none';
  document.getElementById('sr-btn-print').style.display = 'none';

  try{
    const r = await fetch(invAPI + 'stock_report&store_id=' + encodeURIComponent(storeId) + '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to));
    const j = await r.json();
    if(j.success && j.data && j.data.length){
      srAllData = j.data;
      document.getElementById('sr-btn-csv').style.display = 'inline-flex';
      document.getElementById('sr-btn-print').style.display = 'inline-flex';
      srFilter();
    } else {
      srAllData = [];
      div.innerHTML = '<div style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-box-open" style="font-size:28px;margin-bottom:10px;display:block;"></i> No stock movements found for the selected period and store.</div>';
    }
  }catch(e){
    document.getElementById('sr-content').innerHTML = '<div style="padding:30px;text-align:center;color:#dc2626;"><i class="fas fa-exclamation-circle" style="font-size:24px;margin-bottom:8px;display:block;"></i> Error loading report: ' + escHtml(e.message) + '</div>';
  }
}

// Client-side instant filter by search keyword
function srFilter(keepPage){
  if(!srAllData || !srAllData.length) return;
  if(keepPage !== true) srPage = 1;
  const q = (document.getElementById('sr-search')?.value || '').trim().toLowerCase();
  
  let filtered = srAllData;
  if(q){
    filtered = srAllData.filter(item => {
      const name = (item.item_name || '').toLowerCase();
      const code = (item.item_code || '').toLowerCase();
      const batch = (item.batch_number || '').toLowerCase();
      const store = (item.store_name || '').toLowerCase();
      return name.includes(q) || code.includes(q) || batch.includes(q) || store.includes(q);
    });
  }

  srRenderSummary(filtered, srAllData.length);
  srRenderTable(filtered, q);
}

// Summary KPI cards
function srRenderSummary(data, totalCount){
  const sumBar = document.getElementById('sr-summary-bar');
  if(!sumBar) return;

  let totOpening = 0, totIn = 0, totOut = 0, totClosing = 0;
  data.forEach(d => {
    totOpening += parseFloat(d.opening || 0);
    totIn += parseFloat(d.stock_in || 0);
    totOut += parseFloat(d.stock_out || 0);
    totClosing += parseFloat(d.closing || 0);
  });

  sumBar.style.display = 'flex';
  sumBar.innerHTML = `
    <div style="background:white;border:1px solid var(--gray-200);border-radius:8px;padding:8px 14px;display:flex;align-items:center;gap:8px;font-size:12px;">
      <i class="fas fa-boxes" style="color:var(--primary-color);"></i>
      <span><strong>${data.length}</strong> ${data.length === totalCount ? 'records' : 'of ' + totalCount + ' records'}</span>
    </div>
    <div style="background:white;border:1px solid var(--gray-200);border-radius:8px;padding:8px 14px;display:flex;align-items:center;gap:8px;font-size:12px;">
      <span style="color:var(--gray-500);">Opening:</span>
      <strong>${totOpening.toLocaleString(undefined, {maximumFractionDigits:2})}</strong>
    </div>
    <div style="background:white;border:1px solid #bbf7d0;border-radius:8px;padding:8px 14px;display:flex;align-items:center;gap:8px;font-size:12px;background:#f0fdf4;">
      <span style="color:#166534;"><i class="fas fa-arrow-down" style="font-size:10px;"></i> Total In:</span>
      <strong style="color:#16a34a;">+${totIn.toLocaleString(undefined, {maximumFractionDigits:2})}</strong>
    </div>
    <div style="background:white;border:1px solid #fecaca;border-radius:8px;padding:8px 14px;display:flex;align-items:center;gap:8px;font-size:12px;background:#fef2f2;">
      <span style="color:#991b1b;"><i class="fas fa-arrow-up" style="font-size:10px;"></i> Total Out:</span>
      <strong style="color:#dc2626;">-${totOut.toLocaleString(undefined, {maximumFractionDigits:2})}</strong>
    </div>
    <div style="background:white;border:1px solid var(--gray-200);border-radius:8px;padding:8px 14px;display:flex;align-items:center;gap:8px;font-size:12px;background:#f8fafc;">
      <span style="color:var(--gray-700);">Closing:</span>
      <strong style="color:var(--gray-900);font-size:13px;">${totClosing.toLocaleString(undefined, {maximumFractionDigits:2})}</strong>
    </div>
  `;
}

// Render main Stock Report table
function srRenderTable(allRows, query){
  srLast = {data: allRows, q: query};
  const total = allRows ? allRows.length : 0;
  const pages = Math.max(1, Math.ceil(total / srPerPage));
  if(srPage > pages) srPage = pages;
  const data = (allRows || []).slice((srPage - 1) * srPerPage, srPage * srPerPage);
  const div = document.getElementById('sr-content');
  if(!data || !data.length){
    div.innerHTML = '<div style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-search" style="font-size:24px;margin-bottom:8px;display:block;"></i>No items matching "<strong>'+escHtml(query)+'</strong>". <button onclick="document.getElementById(\'sr-search\').value=\'\';srFilter();" style="margin-left:8px;padding:4px 10px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;">Clear Search</button></div>';
    return;
  }

  const today = new Date().toISOString().split('T')[0];
  const soon = new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0];

  let html = '<div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;">';
  html += '<table style="width:100%;border-collapse:collapse;font-size:12px;min-width:760px;" id="sr-data-table"><thead><tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Store</th>';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Item (Click for Movement History)</th>';
  html += '<th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Batch #</th>';
  html += '<th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Expiry Date</th>';
  html += '<th style="padding:10px 12px;text-align:right;font-weight:600;color:var(--gray-700);">Opening</th>';
  html += '<th style="padding:10px 12px;text-align:right;color:#16a34a;font-weight:600;">In</th>';
  html += '<th style="padding:10px 12px;text-align:right;color:#dc2626;font-weight:600;">Out</th>';
  html += '<th style="padding:10px 12px;text-align:right;font-weight:700;color:var(--gray-800);">Closing</th>';
  html += '<th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Action</th>';
  html += '</tr></thead><tbody>';

  data.forEach(item => {
    const expired  = item.expiry_date && item.expiry_date < today;
    const expiring = item.expiry_date && !expired && item.expiry_date <= soon;
    const expiryColor = expired ? '#dc2626' : expiring ? '#d97706' : '';
    const expiryBadge = expired
      ? ' <span style="font-size:9px;font-weight:700;padding:1px 4px;background:#dc2626;color:white;border-radius:3px;">EXP</span>'
      : expiring
      ? ' <span style="font-size:9px;font-weight:700;padding:1px 4px;background:#d97706;color:white;border-radius:3px;">SOON</span>'
      : '';

    const safeItemName = (item.item_name || 'Item #' + item.item_id).replace(/"/g, '&quot;');
    const safeItemCode = (item.item_code || '').replace(/"/g, '&quot;');
    const safeStoreName = (item.store_name || '').replace(/"/g, '&quot;');
    const safeBatchNum = (item.batch_number || '').replace(/"/g, '&quot;');

    html += `<tr class="sr-row-hover" style="border-bottom:1px solid var(--gray-100);transition:background 0.1s ease;">
      <td style="padding:9px 12px;font-size:11px;color:var(--gray-700);font-weight:600;">
        <i class="fas fa-warehouse" style="color:var(--gray-400);margin-right:4px;font-size:10px;"></i>${escHtml(item.store_name || '—')}
      </td>
      <td style="padding:9px 12px;">
        <button type="button" class="sr-clickable-item" 
          onclick="srOpenMovementModal(${item.item_id}, '${safeItemName}', '${safeItemCode}', ${item.store_id || 'null'}, '${safeStoreName}', ${item.batch_id || 'null'}, '${safeBatchNum}')" 
          title="Click to view full movement history for ${safeItemName}">
          <span style="font-weight:600;">${escHtml(item.item_name || 'Item #' + item.item_id)}</span>
          <i class="fas fa-history" style="font-size:10px;opacity:0.75;" title="View Movement History"></i>
        </button>
        ${item.item_code ? `<span style="font-size:10px;color:var(--gray-500);background:var(--gray-100);padding:1px 5px;border-radius:3px;margin-left:6px;font-family:monospace;">${escHtml(item.item_code)}</span>` : ''}
      </td>
      <td style="padding:9px 12px;font-family:monospace;font-size:11px;color:var(--primary-color);">
        ${item.batch_number ? `<span style="background:var(--gray-50);border:1px solid var(--gray-200);padding:2px 6px;border-radius:4px;">${escHtml(item.batch_number)}</span>` : '<span style="color:var(--gray-400);">—</span>'}
      </td>
      <td style="padding:9px 12px;text-align:center;color:${expiryColor || 'inherit'};font-weight:${expired || expiring ? '600' : '400'};">
        ${item.expiry_date ? (item.expiry_date + expiryBadge) : '<span style="color:var(--gray-400);">—</span>'}
      </td>
      <td style="padding:9px 12px;text-align:right;font-family:monospace;font-size:12px;">${parseFloat(item.opening || 0).toLocaleString(undefined, {maximumFractionDigits:2})}</td>
      <td style="padding:9px 12px;text-align:right;color:#16a34a;font-weight:600;font-family:monospace;font-size:12px;">+${parseFloat(item.stock_in || 0).toLocaleString(undefined, {maximumFractionDigits:2})}</td>
      <td style="padding:9px 12px;text-align:right;color:#dc2626;font-weight:600;font-family:monospace;font-size:12px;">-${parseFloat(item.stock_out || 0).toLocaleString(undefined, {maximumFractionDigits:2})}</td>
      <td style="padding:9px 12px;text-align:right;font-weight:700;font-family:monospace;font-size:12px;color:var(--gray-900);">${parseFloat(item.closing || 0).toLocaleString(undefined, {maximumFractionDigits:2})}</td>
      <td style="padding:9px 12px;text-align:center;">
        <button type="button" class="sr-hist-btn" 
          onclick="srOpenMovementModal(${item.item_id}, '${safeItemName}', '${safeItemCode}', ${item.store_id || 'null'}, '${safeStoreName}', ${item.batch_id || 'null'}, '${safeBatchNum}')" 
          title="View Movement History">
          <i class="fas fa-history"></i> History
        </button>
      </td>
    </tr>`;
  });

  html += '</tbody></table></div>';
  const bs = 'padding:5px 12px;border:1px solid var(--gray-200);border-radius:6px;background:white;font-size:12px;cursor:pointer;';
  html += '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px;font-size:12px;color:var(--gray-600);">'
    + '<span>Showing ' + ((srPage - 1) * srPerPage + 1) + '–' + Math.min(srPage * srPerPage, total) + ' of ' + total + '</span>'
    + '<span style="display:flex;align-items:center;gap:8px;">'
    + '<select onchange="srPerPage=+this.value;srPage=1;srFilter(true)" style="padding:4px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">'
    + [25,50,100,200].map(n => '<option value="' + n + '"' + (n === srPerPage ? ' selected' : '') + '>' + n + ' / page</option>').join('') + '</select>'
    + '<button style="' + bs + '" ' + (srPage <= 1 ? 'disabled' : '') + ' onclick="srPage--;srFilter(true)">Prev</button>'
    + '<span>Page ' + srPage + ' / ' + pages + '</span>'
    + '<button style="' + bs + '" ' + (srPage >= pages ? 'disabled' : '') + ' onclick="srPage++;srFilter(true)">Next</button>'
    + '</span></div>';
  div.innerHTML = html;
}

// Export CSV for Main Stock Report
function srExportCSV(){
  if(!srAllData || !srAllData.length) return alert('No data to export.');
  const q = (document.getElementById('sr-search')?.value || '').trim().toLowerCase();
  let data = srAllData;
  if(q){
    data = srAllData.filter(item => {
      return (item.item_name || '').toLowerCase().includes(q) ||
             (item.item_code || '').toLowerCase().includes(q) ||
             (item.batch_number || '').toLowerCase().includes(q) ||
             (item.store_name || '').toLowerCase().includes(q);
    });
  }

  let csv = 'Store,Item Code,Item Name,Batch Number,Expiry Date,Opening,In,Out,Closing\n';
  data.forEach(item => {
    const row = [
      '"' + (item.store_name || '').replace(/"/g, '""') + '"',
      '"' + (item.item_code || '').replace(/"/g, '""') + '"',
      '"' + (item.item_name || '').replace(/"/g, '""') + '"',
      '"' + (item.batch_number || '').replace(/"/g, '""') + '"',
      '"' + (item.expiry_date || '') + '"',
      item.opening || 0,
      item.stock_in || 0,
      item.stock_out || 0,
      item.closing || 0
    ];
    csv += row.join(',') + '\n';
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'stock_report_' + (document.getElementById('sr-from')?.value || 'from') + '_to_' + (document.getElementById('sr-to')?.value || 'to') + '.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

// Print Main Stock Report
function srPrintReport(){
  const div = document.getElementById('sr-content');
  if(!div) return;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`
    <html>
      <head>
        <title>Stock Report</title>
        <style>
          body { font-family: sans-serif; padding: 20px; font-size: 12px; color: #1e293b; }
          h2 { margin-bottom: 4px; }
          p { margin-top: 0; color: #64748b; font-size: 11px; }
          table { width: 100%; border-collapse: collapse; margin-top: 15px; }
          th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
          th { background: #f1f5f9; font-weight: 600; }
          td.right { text-align: right; }
          .btn, button, .sr-hist-btn, .sr-clickable-item i { display: none !important; }
        </style>
      </head>
      <body>
        <h2>Stock Report (Opening / In / Out / Closing)</h2>
        <p>Period: ${document.getElementById('sr-from')?.value || ''} to ${document.getElementById('sr-to')?.value || ''} | Generated: ${new Date().toLocaleString()}</p>
        ${div.innerHTML}
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => { printWindow.print(); }, 500);
}
</script>

<?php elseif ($subModule == 'purchase-request'): ?>
<!-- ====== PURCHASE REQUESTS ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-file-alt" style="color:var(--primary-color);"></i> Purchase Requests</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="date" id="pr-date-from" onchange="prCurrentPage=1;prApplyFilter()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <span style="font-size:12px;color:var(--gray-400);">to</span>
      <input type="date" id="pr-date-to" onchange="prCurrentPage=1;prApplyFilter()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      <select id="pr-filter-status" onchange="prCurrentPage=1;prApplyFilter()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
        <option value="">All Status</option>
        <option value="draft">Draft</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="ordered">Ordered</option>
      </select>
      <button onclick="prNew()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-plus"></i> New Request</button>
      <button onclick="prLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);"><th style="padding:10px;text-align:left;">PR #</th><th style="padding:10px;text-align:left;">Date</th><th style="padding:10px;text-align:left;">Priority</th><th style="padding:10px;text-align:center;">Items</th><th style="padding:10px;text-align:right;">Est. Total</th><th style="padding:10px;text-align:left;">Status</th><th style="padding:10px;text-align:center;">Action</th></tr></thead>
      <tbody id="pr-tbody"><tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
  <div id="pr-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-top:1px solid var(--gray-200);margin-top:8px;"></div>
</div>
<script>
let prAllData = [];
let prCurrentPage = 1;
const prPageSize = 15;

// Initialize date filters to current month
(function(){
  const today = new Date().toISOString().split('T')[0];
  const firstOfMonth = today.substring(0,8) + '01';
  document.getElementById('pr-date-from').value = firstOfMonth;
  document.getElementById('pr-date-to').value = today;
})();

function prApplyFilter(){
  const from = document.getElementById('pr-date-from').value;
  const to = document.getElementById('pr-date-to').value;
  const status = document.getElementById('pr-filter-status').value;
  let filtered = prAllData;
  if(from) filtered = filtered.filter(pr => (pr.request_date||'') >= from);
  if(to) filtered = filtered.filter(pr => (pr.request_date||'') <= to);
  if(status) filtered = filtered.filter(pr => pr.status === status);
  prRenderTable(filtered);
}

function prGoPage(page){
  const from = document.getElementById('pr-date-from').value;
  const to = document.getElementById('pr-date-to').value;
  const status = document.getElementById('pr-filter-status').value;
  let filtered = prAllData;
  if(from) filtered = filtered.filter(pr => (pr.request_date||'') >= from);
  if(to) filtered = filtered.filter(pr => (pr.request_date||'') <= to);
  if(status) filtered = filtered.filter(pr => pr.status === status);
  const totalPages = Math.ceil(filtered.length / prPageSize);
  if(page < 1 || page > totalPages) return;
  prCurrentPage = page;
  prRenderTable(filtered);
}

function prRenderTable(data){
  const tbody = document.getElementById('pr-tbody');
  const pgEl = document.getElementById('pr-pagination');
  if(!data||!data.length){
    tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No purchase requests found.</td></tr>';
    if(pgEl) pgEl.innerHTML='';
    return;
  }
  const totalPages = Math.ceil(data.length / prPageSize);
  if(prCurrentPage > totalPages) prCurrentPage = totalPages;
  if(prCurrentPage < 1) prCurrentPage = 1;
  const start = (prCurrentPage - 1) * prPageSize;
  const end = start + prPageSize;
  const pageItems = data.slice(start, end);

  const sBg={draft:'#f3f4f6',pending:'#fef3c7',approved:'#d1fae5',rejected:'#fee2e2',ordered:'#dbeafe'};
  const sColor={draft:'#374151',pending:'#92400e',approved:'#065f46',rejected:'#991b1b',ordered:'#1e40af'};
  tbody.innerHTML=pageItems.map(pr=>{
    let actions=`<button onclick="prView(${pr.id})" style="padding:3px 8px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:4px;"><i class="fas fa-eye"></i></button>`;
    if(pr.status==='draft') actions+=`<button onclick="prSubmit(${pr.id})" style="padding:3px 8px;background:#d97706;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;">Submit</button>`;
    else if(pr.status==='pending' && invIsAdmin) actions+=`<button onclick="prApprove(${pr.id})" style="padding:3px 8px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;margin-right:4px;">Approve</button><button onclick="prReject(${pr.id})" style="padding:3px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;">Reject</button>`;
    return `<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:8px 10px;font-family:monospace;color:var(--primary-color);">${escHtml(pr.pr_number||'')}</td><td style="padding:8px 10px;font-size:12px;">${pr.request_date||'—'}</td><td style="padding:8px 10px;font-size:12px;text-transform:uppercase;font-weight:600;">${pr.priority||'medium'}</td><td style="padding:8px 10px;text-align:center;">${pr.item_count||0}</td><td style="padding:8px 10px;text-align:right;">${parseFloat(pr.total_estimated||0).toLocaleString()}</td><td style="padding:8px 10px;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;background:${sBg[pr.status]||'#f3f4f6'};color:${sColor[pr.status]||'#374151'};">${pr.status}</span></td><td style="padding:8px 10px;text-align:center;">${actions}</td></tr>`;
  }).join('');

  // Pagination
  if(pgEl){
    if(totalPages <= 1){ pgEl.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">${data.length} request${data.length!==1?'s':''}</span><div></div>`; return; }
    let btns='';
    btns+=`<button onclick="prGoPage(${prCurrentPage-1})" ${prCurrentPage===1?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${prCurrentPage===1?'var(--gray-100)':'white'};color:${prCurrentPage===1?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${prCurrentPage===1?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-left"></i> Prev</button>`;
    let startP=Math.max(1,prCurrentPage-3),endP=Math.min(totalPages,startP+6);
    if(endP-startP<6) startP=Math.max(1,endP-6);
    for(let p=startP;p<=endP;p++){
      const active=p===prCurrentPage;
      btns+=`<button onclick="prGoPage(${p})" style="padding:6px 10px;border:1px solid ${active?'var(--primary-color)':'var(--gray-200)'};background:${active?'var(--primary-color)':'white'};color:${active?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:12px;font-weight:${active?'600':'400'};">${p}</button>`;
    }
    btns+=`<button onclick="prGoPage(${prCurrentPage+1})" ${prCurrentPage===totalPages?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${prCurrentPage===totalPages?'var(--gray-100)':'white'};color:${prCurrentPage===totalPages?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${prCurrentPage===totalPages?'not-allowed':'pointer'};font-size:12px;">Next <i class="fas fa-chevron-right"></i></button>`;
    pgEl.innerHTML=`<span style="font-size:12px;color:var(--gray-500);">Page ${prCurrentPage} of ${totalPages} (${data.length} requests)</span><div style="display:flex;gap:4px;">${btns}</div>`;
  }
}

async function prLoad(){
  try{
    const r=await fetch(invAPI+'purchase_requests');const j=await r.json();
    const tbody=document.getElementById('pr-tbody');
    if(j.success&&j.data&&j.data.length){
      prAllData = j.data;
      prApplyFilter();
    } else { prAllData=[]; tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:var(--gray-400);">No purchase requests.</td></tr>'; document.getElementById('pr-pagination').innerHTML=''; }
  }catch(e){}
}

let _prItemsCache = [];
async function prNew(){
  // Load items for search
  if(!_prItemsCache.length){
    try{const r=await fetch(invAPI+'items&active=true&limit=500');const j=await r.json();if(j.success) _prItemsCache=j.data||[];}catch(e){}
  }
  
  const modal=document.createElement('div');modal.id='pr-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:650px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;"><h3 style="font-size:16px;font-weight:600;">New Purchase Request</h3><button onclick="document.getElementById('pr-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Priority</label><select id="pr-priority" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
      <div><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Required By</label><input type="date" id="pr-required" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
      <div style="grid-column:1/-1;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Justification</label><textarea id="pr-just" rows="2" placeholder="Reason for this request..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea></div>
    </div>
    <div style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:8px;"><label style="font-size:13px;font-weight:600;">Items</label><button onclick="prAddRow()" type="button" style="padding:4px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;"><i class="fas fa-plus"></i> Add</button></div>
      <div id="pr-items-container"></div>
    </div>
    <div style="display:flex;gap:8px;">
      <button onclick="prSave('draft')" style="flex:1;padding:10px;background:var(--gray-600);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save Draft</button>
      <button onclick="prSave('pending')" style="flex:1;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Submit for Approval</button>
    </div>
  </div>`;
  document.body.appendChild(modal);
  prAddRow();
}

function prAddRow(){
  const c=document.getElementById('pr-items-container');
  const rowId='pr-row-'+Date.now();
  const row=document.createElement('div');row.className='pr-item-row';row.id=rowId;row.style.cssText='display:flex;gap:6px;margin-bottom:6px;align-items:center;';
  row.innerHTML=`<div style="flex:2;position:relative;">
      <input type="text" class="pr-item-search" placeholder="Search item..." oninput="prSearchItem(this)" onfocus="prSearchItem(this)" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
      <input type="hidden" class="pr-item-id">
      <div class="pr-item-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:160px;overflow-y:auto;background:white;border:1px solid var(--gray-200);border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:100;"></div>
    </div>
    <input type="number" class="pr-item-qty" placeholder="Qty" min="1" value="1" style="width:60px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
    <input type="number" class="pr-item-price" placeholder="Est.Price" style="width:80px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
    <button onclick="this.parentElement.remove()" style="border:none;background:none;color:#dc2626;cursor:pointer;font-size:14px;"><i class="fas fa-trash"></i></button>`;
  c.appendChild(row);

  // Close dropdown on outside click
  document.addEventListener('click', function closeDD(e){
    if(!row.contains(e.target)){
      row.querySelector('.pr-item-dropdown').style.display='none';
      document.removeEventListener('click', closeDD);
    }
  });
}

function prSearchItem(input){
  const q = (input.value||'').toLowerCase();
  const row = input.closest('.pr-item-row');
  const dropdown = row.querySelector('.pr-item-dropdown');
  const filtered = _prItemsCache.filter(i => {
    return (i.name||'').toLowerCase().includes(q) || (i.item_code||'').toLowerCase().includes(q) || (i.generic_name||'').toLowerCase().includes(q);
  }).slice(0, 20);
  if(!filtered.length){
    dropdown.innerHTML='<div style="padding:8px 10px;font-size:11px;color:var(--gray-400);">No items found</div>';
    dropdown.style.display='block';
    return;
  }
  dropdown.innerHTML = filtered.map(i =>
    `<div onclick="prSelectItem(this,${i.id},'${escHtml((i.item_code?i.item_code+' - ':'')+i.name).replace(/'/g,"&#39;")}',${i.unit_price||0})" style="padding:6px 10px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--gray-50);transition:background .1s;" onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background=''">
      <div style="font-weight:500;">${escHtml(i.name)}</div>
      <div style="font-size:10px;color:var(--gray-500);">${escHtml(i.item_code||'')} ${i.generic_name?'· '+escHtml(i.generic_name):''}</div>
    </div>`
  ).join('');
  dropdown.style.display='block';
}

function prSelectItem(el, id, name, price){
  const row = el.closest('.pr-item-row');
  row.querySelector('.pr-item-search').value = name;
  row.querySelector('.pr-item-id').value = id;
  row.querySelector('.pr-item-price').value = price || '';
  row.querySelector('.pr-item-dropdown').style.display = 'none';
}

async function prSave(status){
  const rows=document.querySelectorAll('.pr-item-row');
  const items=[];
  rows.forEach(row=>{
    const id=row.querySelector('.pr-item-id').value;
    const qty=row.querySelector('.pr-item-qty').value;
    const price=row.querySelector('.pr-item-price').value;
    if(id) items.push({item_id:parseInt(id),quantity:parseFloat(qty)||1,estimated_unit_price:parseFloat(price)||0});
  });
  if(!items.length) return alert('Add at least one item.');
  try{
    const r=await fetch(invAPI+'create_purchase_request',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({priority:document.getElementById('pr-priority').value,required_by:document.getElementById('pr-required').value,justification:document.getElementById('pr-just').value,status:status,items:items})});
    const j=await r.json();
    if(j.success){alert('✓ '+(status==='draft'?'Draft saved!':'Request submitted!'));document.getElementById('pr-modal').remove();prLoad();}else alert(j.message);
  }catch(e){alert('Failed');}
}

async function prSubmit(id){
  if(!confirm('Submit this request for approval?')) return;
  await fetch(invAPI+'update_pr_status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pr_id:id,status:'pending'})});
  prLoad();
}
async function prApprove(id){
  if (!invIsAdmin) {
    alert('Permission denied: Only administrators can approve purchase requests.');
    return;
  }
  const note=prompt('Approval note (optional):','');
  if(note===null) return;
  await fetch(invAPI+'update_pr_status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pr_id:id,status:'approved',note:note})});
  alert('✓ Approved!'); prLoad();
}
async function prReject(id){
  if (!invIsAdmin) {
    alert('Permission denied: Only administrators can reject purchase requests.');
    return;
  }
  const note=prompt('Reason for rejection:','');
  if(!note) return alert('Please provide a reason for rejection.');
  await fetch(invAPI+'update_pr_status',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pr_id:id,status:'rejected',note:note})});
  alert('Request rejected.'); prLoad();
}

async function prView(id){
  try{
    const r=await fetch(invAPI+'purchase_request_detail&id='+id);
    const j=await r.json();
    if(!j.success||!j.data) return alert('Not found');
    const pr=j.data;
    const items=pr.items||[];
    
    const sBg={draft:'#f3f4f6',pending:'#fef3c7',approved:'#d1fae5',rejected:'#fee2e2'};
    const sColor={draft:'#374151',pending:'#92400e',approved:'#065f46',rejected:'#991b1b'};
    
    const modal=document.createElement('div');modal.id='pr-view-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
    modal.innerHTML=`<div style="background:white;border-radius:12px;width:600px;max-width:90vw;max-height:80vh;overflow-y:auto;padding:24px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
        <h3 style="font-size:16px;font-weight:600;">Purchase Request: ${escHtml(pr.pr_number)}</h3>
        <button onclick="document.getElementById('pr-view-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;font-size:13px;">
        <div><strong>Date:</strong> ${pr.request_date||'—'}</div>
        <div><strong>Priority:</strong> <span style="text-transform:uppercase;font-weight:600;">${pr.priority||'medium'}</span></div>
        <div><strong>Status:</strong> <span style="padding:2px 8px;border-radius:10px;font-size:11px;background:${sBg[pr.status]||'#f3f4f6'};color:${sColor[pr.status]||'#374151'};">${pr.status}</span></div>
        <div><strong>Required By:</strong> ${pr.required_by||'—'}</div>
        <div><strong>Est. Total:</strong> ${parseFloat(pr.total_estimated||0).toLocaleString()} RWF</div>
        <div><strong>Approved:</strong> ${pr.approved_at||'—'}</div>
      </div>
      ${pr.justification?'<div style="margin-bottom:12px;padding:10px;background:var(--gray-50);border-radius:6px;font-size:12px;"><strong>Justification:</strong> '+escHtml(pr.justification)+'</div>':''}
      ${pr.notes?'<div style="margin-bottom:12px;padding:10px;background:#fffbeb;border-radius:6px;font-size:12px;border-left:3px solid #d97706;"><strong>Note:</strong> '+escHtml(pr.notes)+'</div>':''}
      <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
          <thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">Item</th><th style="padding:8px;text-align:center;">Qty</th><th style="padding:8px;text-align:right;">Unit Price</th><th style="padding:8px;text-align:right;">Total</th></tr></thead>
          <tbody>${items.map(it=>`<tr style="border-top:1px solid var(--gray-100);">
            <td style="padding:6px 8px;">${escHtml(it.item_name||it.item_description||'Item #'+it.item_id)}</td>
            <td style="padding:6px 8px;text-align:center;">${it.quantity||0}</td>
            <td style="padding:6px 8px;text-align:right;">${parseFloat(it.estimated_unit_price||0).toLocaleString()}</td>
            <td style="padding:6px 8px;text-align:right;font-weight:600;">${parseFloat(it.estimated_total||0).toLocaleString()}</td>
          </tr>`).join('')}</tbody>
        </table>
      </div>
    </div>`;
    document.body.appendChild(modal);
  }catch(e){alert('Error loading details');}
}
prLoad();
</script>

<?php elseif ($subModule == 'goods-receiving'): ?>
<!-- ====== GOODS RECEIVING ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-boxes" style="color:#16a34a;"></i> Receive Goods (from Confirmed POs)</h3>
    <button onclick="grLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
  </div>
  <div id="gr-list"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
async function grLoad(){
  try{
    const r=await fetch(invAPI+'purchase_orders&status=confirmed');const j=await r.json();
    const div=document.getElementById('gr-list');
    if(j.success&&j.data&&j.data.length){
      div.innerHTML=j.data.map(po=>`<div style="border:1px solid var(--gray-200);border-radius:8px;padding:14px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
        <div><div style="font-weight:600;">${escHtml(po.po_number)} — ${escHtml(po.supplier_name||'')}</div><div style="font-size:12px;color:var(--gray-500);">Items: ${po.item_count||0} | Total: ${parseFloat(po.total_amount||0).toLocaleString()} RWF | Date: ${po.order_date||'—'}</div></div>
        <button onclick="grReceive(${po.id})" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-download"></i> Receive</button>
      </div>`).join('');
    } else div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);">No confirmed POs awaiting receipt.</div>';
  }catch(e){}
}

async function grReceive(poId){
  const r=await fetch(invAPI+'purchase_order_detail&id='+poId);const j=await r.json();
  if(!j.success) return alert('Error');
  const po=j.data;const items=po.items||[];
  
  const modal=document.createElement('div');modal.id='gr-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:650px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;"><h3 style="font-size:16px;font-weight:600;">Receive: ${escHtml(po.po_number)}</h3><button onclick="document.getElementById('gr-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button></div>
    <div style="background:var(--gray-50);border-radius:6px;padding:10px 12px;margin-bottom:16px;font-size:12px;"><strong>Supplier:</strong> ${escHtml(po.supplier_name||'—')} | <strong>Store:</strong> Main Store (1004)</div>
    <div style="margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Receive to Store</label>
      <select id="gr-store" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        <option value="1004">Main Store</option>
        <option value="1005">Clinic Store</option>
      </select>
    </div>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:16px;">
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead><tr style="background:var(--gray-50);"><th style="padding:8px;text-align:left;">Item</th><th style="padding:8px;text-align:center;">Ordered</th><th style="padding:8px;text-align:center;">Receive Qty</th><th style="padding:8px;text-align:center;">Batch #</th><th style="padding:8px;text-align:center;">Expiry</th></tr></thead>
        <tbody>${items.map(it=>`<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:6px 8px;">${escHtml(it.item_name||it.item_description||'Item')}</td>
          <td style="padding:6px 8px;text-align:center;">${it.quantity_ordered}</td>
          <td style="padding:6px 8px;text-align:center;"><input type="number" class="gr-qty" data-item-id="${it.item_id}" data-price="${it.unit_price||0}" value="${parseFloat(it.quantity_ordered)-parseFloat(it.quantity_received||0)}" min="0" style="width:60px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;text-align:center;font-size:12px;"></td>
          <td style="padding:6px 8px;text-align:center;"><input class="gr-batch" placeholder="Batch" style="width:70px;padding:4px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
          <td style="padding:6px 8px;text-align:center;"><input type="date" class="gr-expiry" style="padding:4px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;"></td>
        </tr>`).join('')}</tbody>
      </table>
    </div>
    <button onclick="grSubmit(${poId},${po.supplier_id})" style="width:100%;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Confirm Receipt & Create Journal Entry</button>
  </div>`;
  document.body.appendChild(modal);
}

async function grSubmit(poId, supplierId){
  const modal=document.getElementById('gr-modal');
  const storeId=parseInt(document.getElementById('gr-store').value);
  const rows=modal.querySelectorAll('tbody tr');
  const items=[];
  let missingBatch=false;
  rows.forEach(row=>{
    const qtyInp=row.querySelector('.gr-qty');
    const batchInp=row.querySelector('.gr-batch');
    const expiryInp=row.querySelector('.gr-expiry');
    if(!qtyInp) return;
    const qty=parseFloat(qtyInp.value)||0;
    if(qty>0){
      const batchVal=(batchInp?.value||'').trim();
      const expiryVal=(expiryInp?.value||'').trim();
      if(!batchVal) missingBatch=true;
      items.push({
        item_id:parseInt(qtyInp.getAttribute('data-item-id')),
        quantity:qty,
        unit_price:parseFloat(qtyInp.getAttribute('data-price'))||0,
        batch_number:batchVal||null,
        expiry_date:expiryVal||null
      });
    }
  });
  if(!items.length) return alert('Enter quantities.');
  if(missingBatch && !confirm('Some items are missing a batch number. Continue without batch numbers?')) return;
  if(!confirm('Receive '+items.length+' item(s) into stock?\nJournal: Dr Store / Cr Supplier')) return;
  
  try{
    const r=await fetch(invAPI+'receive_goods',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({po_id:poId,store_id:storeId,supplier_id:supplierId,items:items})});
    const j=await r.json();
    if(j.success){alert('✓ Goods received! '+j.message);document.getElementById('gr-modal').remove();grLoad();}
    else alert('Error: '+j.message);
  }catch(e){alert('Failed');}
}
grLoad();
</script>

<?php elseif ($subModule == 'transfers'): ?>
<!-- ====== STORE TRANSFERS ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <div>
      <h3 style="font-size:16px;font-weight:600;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-exchange-alt" style="color:var(--primary-color);"></i> Store Transfers
      </h3>
      <p style="font-size:12px;color:var(--gray-500);margin-top:2px;">Transfer stock between stores with batch and expiry tracking</p>
    </div>
    <div style="display:flex;gap:8px;">
      <button onclick="tfNew()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-plus"></i> New Transfer
      </button>
      <button onclick="tfLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh list">
        <i class="fas fa-sync-alt"></i>
      </button>
    </div>
  </div>
  <div style="border:1px solid var(--gray-200);border-radius:8px;overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Transfer #</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">From → To Store</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Date</th>
          <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Transferred Items</th>
          <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
          <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
        </tr>
      </thead>
      <tbody id="tf-tbody"><tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading transfers...</td></tr></tbody>
    </table>
  </div>
</div>
<script src="js/inv-transfers.js?v=<?= filemtime(__DIR__ . '/js/inv-transfers.js') ?>"></script>
<script>
// Initializer
tfInit(); 
tfLoad();

// Auto-open new transfer modal if action=new in URL
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('sub') === 'transfers' && urlParams.get('action') === 'new') {
  setTimeout(() => tfNew(), 200);
}
</script>

<?php elseif ($subModule == 'stock-issues'): ?>
<!-- ====== DRUG ISSUES & MOVEMENTS (#24) ====== -->
<style>.rp-tbl{width:100%;border-collapse:collapse;font-size:12px}.rp-tbl th{padding:9px 10px;text-align:left;background:var(--gray-50);font-size:11px;text-transform:uppercase;color:var(--gray-500)}.rp-tbl td{padding:7px 10px;border-top:1px solid var(--gray-100)}.rp-tbl .num{text-align:right}.rp-in{padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px}.rp-lbl{font-size:12px;font-weight:600;display:block;margin-bottom:4px}.rp-btn{padding:8px 14px;border:1px solid var(--gray-200);background:white;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;transition:transform .15s ease-out}.rp-btn:active{transform:scale(.97)}.rp-btn.pri{background:var(--primary-color);color:white;border-color:var(--primary-color)}.rp-card{border:1px solid var(--gray-200);border-radius:8px;overflow:auto;margin-bottom:16px}.rp-card h4{font-size:13px;padding:10px;margin:0;background:var(--gray-50)}@media(prefers-reduced-motion:reduce){.rp-btn{transition:none}.rp-btn:active{transform:none}}</style>
<script src="assets/js/report-export.js"></script>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;"><i class="fas fa-people-carry-box" style="color:var(--primary-color);"></i> Drug Issues &amp; Movements</h3>
  <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap;">
    <div><label class="rp-lbl">Store</label><select id="si-store" class="rp-in"></select></div>
    <div><label class="rp-lbl">From</label><input type="date" id="si-from" class="rp-in"></div>
    <div><label class="rp-lbl">To</label><input type="date" id="si-to" value="<?php echo date('Y-m-d');?>" class="rp-in"></div>
    <div><label class="rp-lbl">Category</label><select id="si-cat" class="rp-in"><option value="">All</option></select></div>
    <div><label class="rp-lbl">Item</label><select id="si-item" class="rp-in" style="max-width:200px"><option value="">All</option></select></div>
    <div><label class="rp-lbl">Department</label><select id="si-dept" class="rp-in"><option value="">All</option></select></div>
    <div><label class="rp-lbl">Requested by</label><select id="si-req" class="rp-in" style="max-width:180px"><option value="">All</option></select></div>
    <button class="rp-btn pri" onclick="siLoad()">Generate</button>
    <button class="rp-btn" onclick="rptPrintPreview('si-report','Drug Issues & Movements')"><i class="fas fa-print"></i> Print</button>
    <button class="rp-btn" onclick="rptExportPdf('si-report','Drug Issues & Movements','drug-issues')"><i class="fas fa-file-pdf"></i> PDF</button>
    <button class="rp-btn" onclick="rptExportXlsx('si-table','drug-issues')"><i class="fas fa-file-excel"></i> Excel</button>
  </div>
  <div id="si-report"><div style="padding:30px;text-align:center;color:var(--gray-400);">Choose filters and click Generate.</div></div>
</div>
<script>
dfRange('si-from','si-to');
const siFmt = n => (parseFloat(n)||0).toLocaleString(undefined,{maximumFractionDigits:2});
(async function(){
  const [st, ca, it, fl] = await Promise.all(['stores','categories','items&active=true','report_filters'].map(a => fetch(invAPI+a).then(r=>r.json()).catch(()=>({}))));
  const clinic = fl.data ? fl.data.clinic_store_id : 0;
  document.getElementById('si-store').innerHTML = '<option value="0">All stores</option>' + (st.data||[]).map(s=>'<option value="'+s.id+'"'+(s.id==clinic?' selected':'')+'>'+escHtml(s.name)+'</option>').join('');
  const fill = (id, rows, label) => document.getElementById(id).insertAdjacentHTML('beforeend', (rows||[]).map(x=>'<option value="'+x.id+'">'+escHtml(label(x))+'</option>').join(''));
  fill('si-cat', ca.data, x=>x.name); fill('si-item', it.data, x=>x.name); fill('si-dept', fl.data&&fl.data.departments, x=>x.name); fill('si-req', fl.data&&fl.data.users, x=>x.name);
})();
function siTotals(title, rows){
  return '<div class="rp-card"><h4>'+title+'</h4><table class="rp-tbl"><thead><tr><th>'+title.replace('Totals per ','')+'</th><th class="num">Quantity</th><th class="num">Value (RWF)</th></tr></thead><tbody>'
    + rows.map(r=>'<tr><td>'+escHtml(r.label)+'</td><td class="num">'+siFmt(r.qty)+'</td><td class="num">'+siFmt(r.value)+'</td></tr>').join('') + '</tbody></table></div>';
}
async function siLoad(){
  const g = id => document.getElementById(id).value;
  const q = new URLSearchParams({store_id:g('si-store'), from:g('si-from'), to:g('si-to'), category_id:g('si-cat'), item_id:g('si-item'), department_id:g('si-dept'), requester_id:g('si-req')});
  const div = document.getElementById('si-report');
  if(!g('si-from')||!g('si-to')) return alert('Select date range.');
  div.innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);">Loading...</div>';
  try{
    const j = await (await fetch(invAPI+'stock_issues&'+q)).json();
    if(!j.success) throw new Error(j.message||'Failed');
    const rows = j.data.rows, t = j.data.totals;
    if(!rows.length){ div.innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);">No issues for these filters.</div>'; return; }
    const kind = {request_management:'Dept request', requisition:'Requisition', prescription:'Prescription', pharmacy_sale:'POS sale'};
    div.innerHTML = '<p style="font-size:12px;color:var(--gray-500);margin-bottom:8px;">'+rows.length+' issue(s) from '+g('si-from')+' to '+g('si-to')+(j.data.truncated?' (first 3000 shown)':'')+'</p>'
      + '<div class="rp-card"><table class="rp-tbl" id="si-table"><thead><tr><th>Date</th><th>Item</th><th>Batch</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Value</th><th>Department / patient</th><th>Source</th><th>Requested by</th><th>Approved by</th><th>Issued by</th></tr></thead><tbody>'
      + rows.map(r=>'<tr><td>'+escHtml(String(r.created_at).slice(0,16))+'</td><td>'+escHtml(r.item_name)+'</td><td>'+escHtml(r.batch_number||'—')+'</td><td class="num">'+siFmt(r.qty)+'</td><td class="num">'+siFmt(r.unit_cost)+'</td><td class="num">'+siFmt(r.value)+'</td><td>'+escHtml(r.target||'—')+'</td><td>'+escHtml(kind[r.reference_type]||r.reference_type)+'</td><td>'+escHtml(r.requested_by||'—')+'</td><td>'+escHtml(r.approved_by||'—')+'</td><td>'+escHtml(r.issued_by||'—')+'</td></tr>').join('')
      + '<tr style="font-weight:700;background:var(--gray-50)"><td colspan="3">Total</td><td class="num">'+siFmt(t.qty)+'</td><td></td><td class="num">'+siFmt(t.value)+'</td><td colspan="5"></td></tr></tbody></table></div>'
      + siTotals('Totals per item', t.by_item) + siTotals('Totals per department / patient', t.by_department) + siTotals('Totals per requester', t.by_requester);
  }catch(e){ div.innerHTML = '<p style="color:#dc2626;">Error: '+escHtml(e.message)+'</p>'; }
}
</script>

<?php elseif ($subModule == 'drug-performance'): ?>
<!-- ====== DRUG PERFORMANCE (#24) ====== -->
<style>.rp-tbl{width:100%;border-collapse:collapse;font-size:12px}.rp-tbl th{padding:9px 10px;text-align:left;background:var(--gray-50);font-size:11px;text-transform:uppercase;color:var(--gray-500)}.rp-tbl td{padding:7px 10px;border-top:1px solid var(--gray-100)}.rp-tbl .num{text-align:right}.rp-in{padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px}.rp-lbl{font-size:12px;font-weight:600;display:block;margin-bottom:4px}.rp-btn{padding:8px 14px;border:1px solid var(--gray-200);background:white;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;transition:transform .15s ease-out}.rp-btn:active{transform:scale(.97)}.rp-btn.pri{background:var(--primary-color);color:white;border-color:var(--primary-color)}.rp-card{border:1px solid var(--gray-200);border-radius:8px;overflow:auto;margin-bottom:16px}.rp-card h4{font-size:13px;padding:10px;margin:0;background:var(--gray-50)}@media(prefers-reduced-motion:reduce){.rp-btn{transition:none}.rp-btn:active{transform:none}}</style>
<script src="assets/js/report-export.js"></script>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;"><i class="fas fa-chart-line" style="color:var(--primary-color);"></i> Drug Performance</h3>
  <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap;">
    <div><label class="rp-lbl">Store</label><select id="dp-store" class="rp-in"></select></div>
    <div><label class="rp-lbl">From</label><input type="date" id="dp-from" class="rp-in"></div>
    <div><label class="rp-lbl">To</label><input type="date" id="dp-to" value="<?php echo date('Y-m-d');?>" class="rp-in"></div>
    <button class="rp-btn pri" onclick="dpLoad()">Generate</button>
    <button class="rp-btn" onclick="rptPrintPreview('dp-report','Drug Performance')"><i class="fas fa-print"></i> Print</button>
    <button class="rp-btn" onclick="rptExportPdf('dp-report','Drug Performance','drug-performance')"><i class="fas fa-file-pdf"></i> PDF</button>
    <button class="rp-btn" onclick="rptExportXlsx('dp-top-value','drug-performance-top-drugs')"><i class="fas fa-file-excel"></i> Excel (top drugs)</button>
  </div>
  <div id="dp-report"><div style="padding:30px;text-align:center;color:var(--gray-400);">Choose the period and click Generate.</div></div>
</div>
<script>
dfRange('dp-from','dp-to');
const dpFmt = n => (parseFloat(n)||0).toLocaleString(undefined,{maximumFractionDigits:2});
fetch(invAPI+'stores').then(r=>r.json()).then(async j=>{
  const fl = await fetch(invAPI+'report_filters').then(r=>r.json()).catch(()=>({}));
  const clinic = fl.data ? fl.data.clinic_store_id : 0;
  document.getElementById('dp-store').innerHTML = '<option value="0">All stores</option>' + (j.data||[]).map(s=>'<option value="'+s.id+'"'+(s.id==clinic?' selected':'')+'>'+escHtml(s.name)+'</option>').join('');
}).catch(()=>{});
function dpTable(id, title, head, rows, cells){
  return '<div class="rp-card"><h4>'+title+'</h4><table class="rp-tbl" id="'+id+'"><thead><tr>'+head.map((h,i)=>'<th'+(i>0&&cells.num.includes(i)?' class="num"':'')+'>'+h+'</th>').join('')+'</tr></thead><tbody>'
    + (rows.length ? rows.map(r=>'<tr>'+cells.fn(r).map((c,i)=>'<td'+(cells.num.includes(i)?' class="num"':'')+'>'+c+'</td>').join('')+'</tr>').join('') : '<tr><td colspan="'+head.length+'" style="color:var(--gray-400);text-align:center;">No data</td></tr>')
    + '</tbody></table></div>';
}
async function dpLoad(){
  const g = id => document.getElementById(id).value;
  if(!g('dp-from')||!g('dp-to')) return alert('Select date range.');
  const div = document.getElementById('dp-report');
  div.innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);">Loading...</div>';
  try{
    const j = await (await fetch(invAPI+'drug_performance&'+new URLSearchParams({store_id:g('dp-store'), from:g('dp-from'), to:g('dp-to')}))).json();
    if(!j.success) throw new Error(j.message||'Failed');
    const d = j.data, byQty = [...d.top].sort((a,b)=>b.qty-a.qty).slice(0,10), byVal = d.top.slice(0,10);
    const lab = x => escHtml(x.label||'—');
    div.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:16px;">'
      + dpTable('dp-top-qty','Top drugs by quantity',['Item','Quantity','Value (RWF)'],byQty,{num:[1,2],fn:x=>[lab(x),dpFmt(x.qty),dpFmt(x.value)]})
      + dpTable('dp-top-value','Top drugs by value',['Item','Quantity','Value (RWF)'],byVal,{num:[1,2],fn:x=>[lab(x),dpFmt(x.qty),dpFmt(x.value)]})
      + dpTable('dp-dept','Use per department',['Department','Quantity','Value (RWF)','Issues'],d.by_department,{num:[1,2,3],fn:x=>[lab(x),dpFmt(x.qty),dpFmt(x.value),x.events]})
      + dpTable('dp-doc','Use per prescriber',['Prescriber','Quantity','Value (RWF)','Prescriptions'],d.by_prescriber,{num:[1,2,3],fn:x=>[lab(x),dpFmt(x.qty),dpFmt(x.value),x.events]})
      + '</div>'
      + dpTable('dp-slow','Slow / non-moving (stock on hand, nothing issued in this period)',['Item','On hand','Value on hand (RWF)','Last issue'],d.slow,{num:[1,2],fn:x=>[escHtml(x.item_name),dpFmt(x.on_hand),dpFmt(x.on_hand_value),escHtml(x.last_issue||'never')]})
      + dpTable('dp-exp','Expiry risk (expired or within 90 days)',['Item','Batch','Quantity','Expiry','Days left','Value (RWF)'],d.expiry,{num:[2,4,5],fn:x=>[escHtml(x.item_name),escHtml(x.batch_number||'—'),dpFmt(x.quantity),escHtml(x.expiry_date),(x.days_left<0?'<b style="color:#dc2626">expired</b>':x.days_left),dpFmt(x.value)]});
  }catch(e){ div.innerHTML = '<p style="color:#dc2626;">Error: '+escHtml(e.message)+'</p>'; }
}
</script>


<?php elseif ($subModule == 'expired'): ?>
<!-- ====== EXPIRED ITEMS ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> Expired Items</h3>
    <div style="display:flex;gap:8px;">
      <button onclick="expWriteOff()" style="padding:8px 14px;background:#dc2626;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-ban"></i> Write Off All Expired</button>
      <button onclick="expLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div id="exp-list"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
async function expLoad(){
  try{
    const r=await fetch(invAPI+'expired_items');const j=await r.json();
    const div=document.getElementById('exp-list');
    if(j.success&&j.data&&j.data.length){
      let html='<div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;"><table style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:#fee2e2;"><th style="padding:8px;text-align:left;">Item</th><th style="padding:8px;text-align:left;">Store</th><th style="padding:8px;text-align:left;">Batch</th><th style="padding:8px;text-align:center;">Qty</th><th style="padding:8px;text-align:right;">Value</th><th style="padding:8px;text-align:left;">Expired On</th></tr></thead><tbody>';
      let total=0;
      j.data.forEach(b=>{
        const val=(parseFloat(b.quantity)||0)*(parseFloat(b.unit_cost)||0);total+=val;
        html+=`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 8px;font-weight:500;">${b.item_name||'Item #'+b.item_id}</td><td style="padding:6px 8px;">${b.store_name||'Store #'+b.store_id}</td><td style="padding:6px 8px;font-family:monospace;font-size:11px;">${b.batch_number||'—'}</td><td style="padding:6px 8px;text-align:center;">${b.quantity}</td><td style="padding:6px 8px;text-align:right;">${val.toLocaleString()}</td><td style="padding:6px 8px;color:#dc2626;">${b.expiry_date}</td></tr>`;
      });
      html+=`</tbody><tfoot><tr style="background:#fee2e2;border-top:2px solid #dc2626;"><td colspan="4" style="padding:8px;font-weight:700;">Total Expired Value</td><td style="padding:8px;text-align:right;font-weight:700;">${total.toLocaleString()} RWF</td><td></td></tr></tfoot></table></div>`;
      div.innerHTML=html;
    } else div.innerHTML='<div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="color:#16a34a;font-size:24px;display:block;margin-bottom:8px;"></i>No expired items in stock.</div>';
  }catch(e){}
}
async function expWriteOff(){
  if(!confirm('Write off ALL expired items?\n\nThis will:\n- Remove expired stock\n- Create expense journal entry (Dr Expiry Expense / Cr Store)')) return;
  try{
    const r=await fetch(invAPI+'write_off_expired',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({})});
    const j=await r.json();
    if(j.success){alert('✓ '+j.message);expLoad();}else alert('Error: '+j.message);
  }catch(e){alert('Failed');}
}
expLoad();
</script>

<?php elseif ($subModule == 'alerts'): ?>
<!-- ====== STOCK ALERTS ====== -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-bell" style="color:#d97706;"></i> Stock Alerts</h3>
    <div style="display:flex;gap:8px;align-items:center;">
      <label style="font-size:12px;color:var(--gray-500);">Expiring within</label>
      <select id="al-days" onchange="alLoad()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;background:white;">
        <option value="30">30 days</option><option value="60">60 days</option><option value="90" selected>90 days</option><option value="180">180 days</option>
      </select>
      <button onclick="alLoad()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <div id="al-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px;"></div>
  <div id="al-body"><div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div></div>
</div>
<script>
function alTable(title,color,head,rows){
  if(!rows.length)return '';
  return `<h4 style="font-size:13px;font-weight:700;margin:18px 0 8px;color:${color};">${title} (${rows.length})</h4>
    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;"><table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead><tr style="background:var(--gray-50);">${head.map(h=>`<th style="padding:8px;text-align:left;">${h}</th>`).join('')}</tr></thead>
    <tbody>${rows.join('')}</tbody></table></div>`;
}
async function alLoad(){
  const body=document.getElementById('al-body');
  try{
    const r=await fetch(invAPI+'stock_alerts&days='+document.getElementById('al-days').value);const j=await r.json();
    if(!j.success){body.innerHTML='<div style="padding:30px;text-align:center;color:#dc2626;">'+escHtml(j.message||'Error')+'</div>';return;}
    const d=j.data;
    const card=(n,l,c)=>`<div style="padding:14px;border-radius:10px;border-left:4px solid ${c};background:var(--gray-50);text-align:center;"><div style="font-size:22px;font-weight:700;color:${c};">${n}</div><div style="font-size:11px;color:var(--gray-500);">${l}</div></div>`;
    document.getElementById('al-cards').innerHTML=card(d.low_stock.length,'Low stock','#d97706')+card(d.expiring_soon.length,'Expiring soon','#ea580c')+card(d.expired.length,'Expired','#dc2626');
    const td='padding:6px 8px;';
    const low=d.low_stock.map(x=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="${td}font-weight:500;">${escHtml(x.name)}<div style="font-size:10px;color:var(--gray-400);">${escHtml(x.item_code)}</div></td><td style="${td}">${escHtml(x.store_name||'')}</td><td style="${td}text-align:right;font-weight:700;color:#d97706;">${parseFloat(x.quantity)}</td><td style="${td}text-align:right;">${parseFloat(x.reorder_level)}</td></tr>`);
    const exp=(rows)=>rows.map(x=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="${td}font-weight:500;">${escHtml(x.name)}<div style="font-size:10px;color:var(--gray-400);">${escHtml(x.item_code)}</div></td><td style="${td}">${escHtml(x.store_name||'')}</td><td style="${td}font-family:monospace;">${escHtml(x.batch_number||'—')}</td><td style="${td}text-align:right;">${parseFloat(x.quantity)}</td><td style="${td}">${escHtml(x.expiry_date)}</td><td style="${td}text-align:right;font-weight:700;">${x.days_left<0?Math.abs(x.days_left)+' d ago':x.days_left+' d'}</td></tr>`);
    const expHead=['Item','Store','Batch','Qty','Expiry','Left'];
    const html=alTable('Expired — write off from Expired Items','#dc2626',expHead,exp(d.expired))
      +alTable('Expiring soon','#ea580c',expHead,exp(d.expiring_soon))
      +alTable('Low stock — at or under reorder level','#d97706',['Item','Store','In stock','Reorder level'],low);
    body.innerHTML=html||'<div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="color:#16a34a;font-size:24px;display:block;margin-bottom:8px;"></i>Nothing needs attention.</div>';
  }catch(e){body.innerHTML='<div style="padding:30px;text-align:center;color:#dc2626;">Failed</div>';}
}
alLoad();
</script>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>