// assets/js/requisitions.js — Internal Requisition & Approvals
const _reqBasePath = (typeof _apiBase !== 'undefined' && _apiBase)
  ? _apiBase.replace('/api.php', '')
  : (window.location.origin + (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || ''));
const reqAPI = (typeof _apiBase !== 'undefined' && _apiBase) ? _apiBase + '?endpoint=requisitions' : _reqBasePath + '/api.php?endpoint=requisitions';
let reqPage = 1;
let reqFilterStore = '';
let reqFilterDept = '';
let reqFilterStatus = '';
let reqFilterSearch = '';

function qEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function qFmt(n){return Number(n||0).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 2});}

const stColors = {
  draft: '#6b7280',
  submitted: '#f59e0b',
  approved: '#10b981',
  rejected: '#ef4444',
  issued: '#8b5cf6',
  partial: '#3b82f6',
  cancelled: '#9ca3af'
};

const prColors = {
  low: '#6b7280',
  normal: '#2563eb',
  high: '#f59e0b',
  urgent: '#dc2626'
};

let cachedStores = [];
let cachedDepts = [];
let cachedConsumableItems = [];

async function reqInitLookups() {
  try {
    const [rS, rD] = await Promise.all([
      fetch(reqAPI + '&action=stores').then(r => r.json()),
      fetch(reqAPI + '&action=departments').then(r => r.json())
    ]);
    if (rS.success) cachedStores = rS.data || [];
    if (rD.success) cachedDepts = rD.data || [];

    // Populate filter dropdowns if present
    const deptFilterEl = document.getElementById('req-filter-dept');
    if (deptFilterEl && deptFilterEl.options.length <= 1) {
      deptFilterEl.innerHTML = '<option value="">All Departments</option>' + cachedDepts.map(d => `<option value="${d.id}">${qEsc(d.name)}</option>`).join('');
    }

    const storeFilterEl = document.getElementById('req-filter-store');
    if (storeFilterEl && storeFilterEl.options.length <= 1) {
      storeFilterEl.innerHTML = '<option value="">All Stores</option>' + cachedStores.map(s => `<option value="${s.id}">${qEsc(s.name)}</option>`).join('');
    }
  } catch(e) {}
}

async function reqLoad(){
  const actionMap = {'my-requests': 'my-requests', 'internal-requisition': 'my-requests', 'verification': 'verification', 'approved': 'approved'};
  const action = actionMap[REQ_SUB] || '';
  const p = new URLSearchParams({
    page: reqPage,
    limit: 20,
    action: action
  });
  if (reqFilterStore) p.append('store_id', reqFilterStore);
  if (reqFilterDept) p.append('department_id', reqFilterDept);
  if (reqFilterStatus) p.append('status', reqFilterStatus);
  if (reqFilterSearch) p.append('search', reqFilterSearch);

  try {
    const r = await fetch(reqAPI + '&' + p);
    const j = await r.json();
    if (!j.success) {
      document.getElementById('req-tbody').innerHTML = `<tr><td colspan="9" style="padding:30px;text-align:center;color:var(--danger-color);">${qEsc(j.message || 'Error loading')}</td></tr>`;
      return;
    }
    const {requisitions, stats, pagination} = j.data;
    
    // Update Stats counters
    if (document.getElementById('req-s-total')) document.getElementById('req-s-total').textContent = stats.total || 0;
    if (document.getElementById('req-s-pending')) document.getElementById('req-s-pending').textContent = stats.pending_approval || 0;
    if (document.getElementById('req-s-approved')) document.getElementById('req-s-approved').textContent = stats.approved || 0;
    if (document.getElementById('req-s-issued')) document.getElementById('req-s-issued').textContent = stats.issued || 0;
    if (document.getElementById('req-s-rejected')) document.getElementById('req-s-rejected').textContent = stats.rejected || 0;

    const tb = document.getElementById('req-tbody');
    if (!requisitions.length) {
      tb.innerHTML = `<tr><td colspan="9" style="padding:60px;text-align:center;color:var(--gray-400);">
        <i class="fas fa-clipboard-list" style="font-size:36px;display:block;margin-bottom:10px;color:var(--gray-300);"></i>
        <div style="font-weight:600;font-size:15px;color:var(--gray-600);">No requisitions found</div>
        <div style="font-size:13px;margin-top:4px;">Try searching for an item name, department, or create a new requisition.</div>
      </td></tr>`;
      return;
    }

    tb.innerHTML = requisitions.map(r => {
      let actions = '';
      if (REQ_SUB === 'my-requests' || REQ_SUB === 'internal-requisition') {
        if (r.status === 'draft') {
          actions += `<button onclick="reqSubmit(${r.id})" style="padding:5px 9px;background:#10b98115;color:#059669;border:1px solid #10b98130;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;margin-right:4px;" title="Submit for Approval"><i class="fas fa-paper-plane"></i> Submit</button>`;
        }
        actions += `<button onclick="reqView(${r.id})" style="padding:5px 9px;background:#3b82f615;color:#2563eb;border:1px solid #3b82f630;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;margin-right:4px;" title="View Details"><i class="fas fa-eye"></i></button>`;
        if (r.status === 'draft' || r.status === 'rejected') {
          actions += `<button onclick="reqDel(${r.id})" style="padding:5px 9px;background:#ef444415;color:#dc2626;border:1px solid #ef444430;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;" title="Delete"><i class="fas fa-trash"></i></button>`;
        }
      } else if (REQ_SUB === 'verification') {
        actions = `<button onclick="reqReview(${r.id})" style="padding:6px 14px;background:var(--warning-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;" title="Review & Approve"><i class="fas fa-clipboard-check" style="margin-right:4px;"></i> Review & Approve</button>`;
      } else if (REQ_SUB === 'approved') {
        actions = `<button onclick="reqView(${r.id})" style="padding:5px 12px;background:#3b82f615;color:#2563eb;border:1px solid #3b82f630;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;" title="View Voucher"><i class="fas fa-file-invoice" style="margin-right:4px;"></i> View Details</button>`;
      }

      const journalBadge = r.journal_reference 
        ? `<div style="margin-top:3px;"><span style="display:inline-flex;align-items:center;gap:3px;font-size:10px;font-family:monospace;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;padding:1px 6px;border-radius:4px;"><i class="fas fa-book"></i> ${qEsc(r.journal_reference)}</span></div>` 
        : '';

      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:12px 14px;font-family:monospace;font-weight:700;color:var(--primary-color);font-size:12px;">
          ${qEsc(r.requisition_no)}
          ${journalBadge}
        </td>
        <td style="padding:12px 14px;font-size:12px;color:var(--gray-600);">${r.created_at ? r.created_at.slice(0, 10) : ''}</td>
        <td style="padding:12px 14px;font-size:12px;">
          <div style="font-weight:600;color:var(--gray-800);">${qEsc(r.requester_first)} ${qEsc(r.requester_last)}</div>
          <div style="font-size:11px;color:var(--gray-500);"><i class="fas fa-building" style="margin-right:3px;"></i>${qEsc(r.dept_name || 'General')}</div>
        </td>
        <td style="padding:12px 14px;font-size:12px;font-weight:500;">
          <i class="fas fa-warehouse" style="color:var(--gray-400);margin-right:4px;"></i>${qEsc(r.store_name)}
        </td>
        <td style="padding:12px 14px;text-align:center;">
          <span style="font-weight:700;font-size:13px;">${r.item_count}</span>
          <div style="font-size:11px;color:var(--gray-500);">${qFmt(r.total_qty)} units</div>
        </td>
        <td style="padding:12px 14px;text-align:right;font-weight:600;font-size:12px;color:var(--gray-800);">
          ${qFmt(r.total_estimated_cost)} RWF
        </td>
        <td style="padding:12px 14px;text-align:center;">
          <span style="padding:3px 8px;border-radius:8px;font-size:10px;font-weight:700;text-transform:uppercase;background:${prColors[r.priority]||'#6b7280'}15;color:${prColors[r.priority]||'#6b7280'};">
            ${r.priority}
          </span>
        </td>
        <td style="padding:12px 14px;text-align:center;">
          <span style="padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:capitalize;background:${stColors[r.status]||'#6b7280'}15;color:${stColors[r.status]||'#6b7280'};">
            ${r.status === 'approved' ? '<i class="fas fa-check-circle" style="margin-right:3px;"></i>Approved & Issued' : r.status}
          </span>
        </td>
        <td style="padding:12px 14px;text-align:center;white-space:nowrap;">
          ${actions}
        </td>
      </tr>`;
    }).join('');

    // Pagination
    const pg = document.getElementById('req-pagination');
    if (pg) {
      pg.innerHTML = pagination.total_pages > 1 
        ? Array.from({length: pagination.total_pages}, (_, i) => `<button onclick="reqPage=${i+1};reqLoad()" style="padding:6px 12px;border:1px solid ${i+1 === pagination.page ? 'var(--primary-color)' : 'var(--gray-200)'};background:${i+1 === pagination.page ? 'var(--primary-color)' : 'white'};color:${i+1 === pagination.page ? 'white' : 'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;">${i+1}</button>`).join('') 
        : '';
    }
  } catch(e) {
    document.getElementById('req-tbody').innerHTML = '<tr><td colspan="9" style="padding:30px;text-align:center;color:var(--danger-color);">Failed to load requisitions.</td></tr>';
  }
}

// ===== MULTI-ITEM NEW REQUISITION MODAL =====
async function reqLoadConsumables(storeId) {
  try {
    const sId = storeId || document.getElementById('req-m-store')?.value || '';
    const r = await fetch(reqAPI + '&action=consumable_items&store_id=' + sId);
    const j = await r.json();
    cachedConsumableItems = (j.success && j.data?.items) ? j.data.items : [];
    reqEnsureItemsDatalist();
    return cachedConsumableItems;
  } catch(e) {
    return [];
  }
}

function reqEnsureItemsDatalist() {
  let dl = document.getElementById('req-items-dl');
  if (!dl) {
    dl = document.createElement('datalist');
    dl.id = 'req-items-dl';
    document.body.appendChild(dl);
  }
  dl.innerHTML = cachedConsumableItems.map(i => {
    const code = i.item_code ? i.item_code + ' — ' : '';
    const cat = i.category_name ? ` [${i.category_name}]` : '';
    const stock = ` (Stock: ${i.stock}${i.uom_name ? ' ' + i.uom_name : ''})`;
    const priceVal = parseFloat(i.unit_cost !== undefined ? i.unit_cost : i.unit_price) || 0;
    const priceStr = priceVal > 0 ? ` [Cost: ${qFmt(priceVal)} RWF]` : '';
    const fullText = `${code}${i.name}${cat}${priceStr}${stock}`;
    return `<option value="${qEsc(fullText)}">`;
  }).join('');
}

let newReqRowIndex = 0;
async function reqOpenModal() {
  await reqInitLookups();
  const defaultStoreId = reqFilterStore || '';
  let storeOpts = '<option value="">-- Select Source Store --</option>';
  cachedStores.forEach(s => {
    const sel = (defaultStoreId && String(s.id) === String(defaultStoreId)) ? ' selected' : '';
    storeOpts += `<option value="${s.id}"${sel}>${qEsc(s.name)}</option>`;
  });

  let deptOpts = '<option value="">-- Select Requesting Department --</option>';
  cachedDepts.forEach(d => {
    deptOpts += `<option value="${d.id}">${qEsc(d.name)}</option>`;
  });

  // Preload consumable items and initialize datalist for selected store
  await reqLoadConsumables(defaultStoreId);

  newReqRowIndex = 0;
  const h = `
  <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;display:flex;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;backdrop-filter:blur(2px);" id="req-modal">
    <div style="background:white;border-radius:14px;padding:26px;width:100%;max-width:850px;margin:20px auto;position:relative;box-shadow:0 20px 40px rgba(0,0,0,0.2);">
      <button onclick="document.getElementById('req-modal').remove()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);" title="Close">&times;</button>
      
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
        <div style="width:36px;height:36px;border-radius:8px;background:var(--primary-light);color:var(--primary-color);display:flex;align-items:center;justify-content:center;font-size:18px;">
          <i class="fas fa-boxes"></i>
        </div>
        <div>
          <h3 style="font-size:18px;font-weight:700;color:var(--gray-900);margin:0;">New Internal Consumable Requisition</h3>
          <p style="font-size:12px;color:var(--gray-500);margin:2px 0 0 0;">Request consumable & supplies items for internal department consumption</p>
        </div>
      </div>

      <div id="req-m-alert" style="display:none;margin:14px 0;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px;background:var(--gray-50);padding:14px;border-radius:10px;border:1px solid var(--gray-200);">
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Source Store *</label>
          <select id="req-m-store" onchange="reqOnStoreChange()" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
            ${storeOpts}
          </select>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Requesting Department *</label>
          <div>
            <input type="text" id="req-m-dept-search" placeholder="Type to filter department..." oninput="reqFilterDeptDropdown(this.value)" style="width:100%;padding:8px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;background:white;margin-bottom:4px;">
            <select id="req-m-dept" size="3" style="width:100%;padding:4px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;background:white;">
              ${deptOpts}
            </select>
          </div>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Priority</label>
          <select id="req-m-priority" style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
            <option value="normal">Normal</option>
            <option value="low">Low</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </select>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Purpose / Consumption Reason</label>
          <input id="req-m-purpose" placeholder="e.g. Ward routine replenishment, OPD procedures..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;background:white;">
        </div>
      </div>

      <!-- Items Section -->
      <div style="margin-top:18px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <div>
            <span style="font-size:14px;font-weight:700;color:var(--gray-900);">Requested Consumable Items</span>
            <span style="font-size:12px;color:var(--gray-500);margin-left:6px;">(Select non-medication consumable items)</span>
          </div>
          <button type="button" onclick="reqAddRow()" style="padding:7px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
            <i class="fas fa-plus" style="margin-right:4px;"></i> Add Item
          </button>
        </div>

        <div style="border:1px solid var(--gray-200);border-radius:8px;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:var(--gray-100);border-bottom:1px solid var(--gray-200);">
                <th style="padding:9px 10px;text-align:left;font-weight:600;width:42%;">Item / Supply (Search or Pick)</th>
                <th style="padding:9px 10px;text-align:center;font-weight:600;width:14%;">Store Stock</th>
                <th style="padding:9px 10px;text-align:center;font-weight:600;width:14%;">Req. Qty</th>
                <th style="padding:9px 10px;text-align:right;font-weight:600;width:14%;">Unit Cost</th>
                <th style="padding:9px 10px;text-align:right;font-weight:600;width:16%;">Subtotal</th>
                <th style="padding:9px 6px;text-align:center;width:35px;"></th>
              </tr>
            </thead>
            <tbody id="req-m-items-tbody"></tbody>
          </table>
        </div>
      </div>

      <!-- Total Cost Card -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;padding:12px 16px;background:#f8fafc;border-radius:8px;border:1px solid var(--gray-200);">
        <div style="font-size:12px;color:var(--gray-600);">
          <i class="fas fa-info-circle" style="color:var(--primary-color);margin-right:4px;"></i>
          Unit cost and stock will automatically update based on selected store.
        </div>
        <div style="font-size:14px;font-weight:700;color:var(--gray-900);">
          Total Estimated Expense: <span id="req-m-grand-total" style="color:var(--primary-color);font-size:16px;">0 RWF</span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid var(--gray-200);">
        <button onclick="reqSave(false)" style="flex:1;padding:11px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300);border-radius:8px;font-weight:600;cursor:pointer;">
          <i class="fas fa-save" style="margin-right:4px;"></i> Save as Draft
        </button>
        <button onclick="reqSave(true)" style="flex:1;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:700;cursor:pointer;">
          <i class="fas fa-paper-plane" style="margin-right:4px;"></i> Submit for Approval
        </button>
        <button onclick="document.getElementById('req-modal').remove()" style="padding:11px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-300);border-radius:8px;cursor:pointer;">
          Cancel
        </button>
      </div>
    </div>
  </div>`;

  document.body.insertAdjacentHTML('beforeend', h);
  reqAddRow(); // initial first row
}

function reqFilterDeptDropdown(query) {
  const q = (query || '').toLowerCase();
  const select = document.getElementById('req-m-dept');
  if (!select) return;
  const filtered = cachedDepts.filter(d => !q || d.name.toLowerCase().includes(q));
  select.innerHTML = '<option value="">-- Select Requesting Department --</option>' + 
    filtered.map(d => `<option value="${d.id}">${qEsc(d.name)}</option>`).join('');
  if (filtered.length === 1) {
    select.value = filtered[0].id;
  }
}

async function reqOnStoreChange() {
  const storeId = document.getElementById('req-m-store').value;
  await reqLoadConsumables(storeId);
  // Refresh all rows stock and prices
  document.querySelectorAll('#req-m-items-tbody tr').forEach(row => {
    const sel = row.querySelector('.req-item-select');
    if (sel && sel.value) {
      const itm = cachedConsumableItems.find(x => String(x.id) === String(sel.value));
      const qtyInput = row.querySelector('.req-item-qty');
      const stockEl = row.querySelector('.req-item-stock');
      const warningEl = row.querySelector('.req-qty-warning');

      if (itm) {
        const stock = parseFloat(itm.stock) || 0;
        const realPrice = parseFloat(itm.unit_cost !== undefined ? itm.unit_cost : itm.unit_price) || 0;
        qtyInput.setAttribute('data-stock', stock);
        qtyInput.setAttribute('data-item-name', itm.name || '');

        if (stock <= 0) {
          stockEl.innerHTML = `<span style="background:#fee2e2;color:#dc2626;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:700;"><i class="fas fa-ban"></i> 0 (Out of stock)</span>`;
          qtyInput.value = 0;
          qtyInput.min = 0;
          qtyInput.max = 0;
          qtyInput.disabled = true;
          qtyInput.style.borderColor = '#fca5a5';
          qtyInput.style.backgroundColor = '#fee2e2';
          qtyInput.style.color = '#991b1b';
          if (warningEl) {
            warningEl.textContent = 'Out of stock (0 in store)';
            warningEl.style.display = 'block';
          }
        } else {
          qtyInput.disabled = false;
          qtyInput.min = 1;
          qtyInput.max = stock;
          if (parseFloat(qtyInput.value) <= 0) qtyInput.value = 1;
          if (parseFloat(qtyInput.value) > stock) qtyInput.value = stock;
          qtyInput.style.borderColor = 'var(--gray-300)';
          qtyInput.style.backgroundColor = 'white';
          qtyInput.style.color = 'inherit';
          if (warningEl) {
            warningEl.style.display = 'none';
          }
          stockEl.textContent = itm.stock + (itm.uom_name ? ' ' + itm.uom_name : '');
          stockEl.style.color = '#166534';
        }

        row.querySelector('.req-item-price').value = realPrice;
        const metaEl = row.querySelector('.req-item-meta');
        if (metaEl) {
          metaEl.innerHTML = `
            <span style="background:#e0e7ff;color:#3730a3;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;">${qEsc(itm.category_name || 'Consumable')}</span>
            <span style="font-weight:600;font-size:11px;color:${stock > 0 ? '#16a34a' : '#dc2626'};margin-left:4px;">${stock} in store</span>
            <span style="font-weight:600;font-size:11px;color:var(--gray-600);margin-left:6px;">Real Cost: <b style="color:var(--primary-color);">${qFmt(realPrice)} RWF</b></span>
          `;
        }
        reqCalcRow(row);
      }
    }
  });
  reqCalcGrandTotal();
}

function reqAddRow() {
  newReqRowIndex++;
  const rowId = 'req-row-' + newReqRowIndex;
  const tr = document.createElement('tr');
  tr.id = rowId;
  tr.style.borderBottom = '1px solid var(--gray-100)';
  tr.innerHTML = `
    <td style="padding:8px 10px;">
      <input type="text" class="req-item-search" list="req-items-dl" placeholder="Type consumable item name or code..." autocomplete="off" 
             oninput="reqMatchItem(this)"
             style="width:100%;padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;">
      <input type="hidden" class="req-item-select" value="">
      <div class="req-item-meta" style="font-size:11px;margin-top:3px;"></div>
    </td>
    <td style="padding:8px 10px;text-align:center;font-size:12px;font-weight:600;" class="req-item-stock">—</td>
    <td style="padding:8px 10px;text-align:center;">
      <input type="number" class="req-item-qty" min="1" value="1" oninput="reqCalcRow(this.closest('tr'))" style="width:100%;max-width:75px;padding:7px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;text-align:center;">
      <div class="req-qty-warning" style="font-size:10px;color:#dc2626;font-weight:600;display:none;margin-top:2px;"></div>
    </td>
    <td style="padding:8px 10px;text-align:right;">
      <input type="number" class="req-item-price" min="0" step="0.01" value="0" oninput="reqCalcRow(this.closest('tr'))" style="width:100%;max-width:90px;padding:7px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;text-align:right;">
    </td>
    <td style="padding:8px 10px;text-align:right;font-weight:700;color:var(--gray-800);" class="req-item-subtotal">0 RWF</td>
    <td style="padding:8px 6px;text-align:center;">
      <button type="button" onclick="this.closest('tr').remove();reqCalcGrandTotal();" style="padding:6px 8px;background:#fee2e2;border:1px solid #fecaca;border-radius:6px;cursor:pointer;color:#dc2626;font-size:12px;" title="Remove row">
        <i class="fas fa-trash"></i>
      </button>
    </td>
  `;
  document.getElementById('req-m-items-tbody').appendChild(tr);
}

function reqMatchItem(input) {
  const val = input.value.trim().toLowerCase();
  const tr = input.closest('tr');
  const hidden = tr.querySelector('.req-item-select');
  const stockEl = tr.querySelector('.req-item-stock');
  const qtyInput = tr.querySelector('.req-item-qty');
  const warningEl = tr.querySelector('.req-qty-warning');
  const priceInput = tr.querySelector('.req-item-price');
  const metaEl = tr.querySelector('.req-item-meta');

  if (!val) {
    hidden.value = '';
    qtyInput.removeAttribute('data-stock');
    qtyInput.removeAttribute('data-item-name');
    qtyInput.disabled = false;
    qtyInput.min = 1;
    qtyInput.removeAttribute('max');
    qtyInput.style.borderColor = 'var(--gray-300)';
    qtyInput.style.backgroundColor = 'white';
    qtyInput.style.color = 'inherit';
    if (warningEl) warningEl.style.display = 'none';
    input.style.borderColor = 'var(--gray-300)';
    input.style.backgroundColor = 'white';
    stockEl.textContent = '—';
    stockEl.style.color = 'inherit';
    if (metaEl) metaEl.innerHTML = '';
    reqCalcRow(tr);
    return;
  }

  // 1. Exact match from datalist option, or exact item code, or exact item name
  let match = cachedConsumableItems.find(i => {
    const code = (i.item_code || '').trim().toLowerCase();
    const name = (i.name || '').trim().toLowerCase();
    const simple = ((code ? code + ' — ' : '') + name).toLowerCase();
    
    if (code && val === code) return true;
    if (name && val === name) return true;
    if (simple && val === simple) return true;
    if (code && val.startsWith(code + ' — ' + name)) return true;
    if (name && (val.startsWith(name + ' [') || val.startsWith(name + ' (stock:'))) return true;
    return false;
  });

  // 2. Substring match if user is typing
  if (!match && val.length >= 2) {
    match = cachedConsumableItems.find(i => {
      const code = (i.item_code || '').trim().toLowerCase();
      const name = (i.name || '').trim().toLowerCase();
      const simple = ((code ? code + ' — ' : '') + name).toLowerCase();
      return (code && code.includes(val)) || name.includes(val) || simple.includes(val);
    });
  }

  if (match) {
    const prevId = hidden.value;
    hidden.value = match.id;
    input.style.borderColor = '#10b981';
    input.style.backgroundColor = '#f0fdf4';
    
    const stock = parseFloat(match.stock) || 0;
    qtyInput.setAttribute('data-stock', stock);
    qtyInput.setAttribute('data-item-name', match.name || '');

    if (stock <= 0) {
      stockEl.innerHTML = `<span style="background:#fee2e2;color:#dc2626;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:700;"><i class="fas fa-ban"></i> 0 (Out of stock)</span>`;
      qtyInput.value = 0;
      qtyInput.min = 0;
      qtyInput.max = 0;
      qtyInput.disabled = true;
      qtyInput.style.borderColor = '#fca5a5';
      qtyInput.style.backgroundColor = '#fee2e2';
      qtyInput.style.color = '#991b1b';
      if (warningEl) {
        warningEl.textContent = 'Out of stock (0 in store)';
        warningEl.style.display = 'block';
      }
    } else {
      qtyInput.disabled = false;
      qtyInput.min = 1;
      qtyInput.max = stock;
      if (parseFloat(qtyInput.value) <= 0) qtyInput.value = 1;
      if (parseFloat(qtyInput.value) > stock) qtyInput.value = stock;
      qtyInput.style.borderColor = 'var(--gray-300)';
      qtyInput.style.backgroundColor = 'white';
      qtyInput.style.color = 'inherit';
      if (warningEl) {
        warningEl.style.display = 'none';
      }
      stockEl.textContent = match.stock + (match.uom_name ? ' ' + match.uom_name : '');
      stockEl.style.color = '#166534';
    }
    
    const realPrice = parseFloat(match.unit_cost !== undefined ? match.unit_cost : match.unit_price) || 0;
    
    // Auto-update price to real price if item changed or if current price is 0/empty
    if (String(prevId) !== String(match.id) || !priceInput.value || parseFloat(priceInput.value) === 0) {
      priceInput.value = realPrice;
    }

    if (metaEl) {
      metaEl.innerHTML = `
        <span style="background:#e0e7ff;color:#3730a3;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;">${qEsc(match.category_name || 'Consumable')}</span>
        <span style="font-weight:600;font-size:11px;color:${stock > 0 ? '#16a34a' : '#dc2626'};margin-left:4px;">${stock} in store</span>
        <span style="font-weight:600;font-size:11px;color:var(--gray-600);margin-left:6px;">Real Cost: <b style="color:var(--primary-color);">${qFmt(realPrice)} RWF</b></span>
      `;
    }
  } else {
    hidden.value = '';
    qtyInput.removeAttribute('data-stock');
    qtyInput.removeAttribute('data-item-name');
    qtyInput.disabled = false;
    qtyInput.min = 1;
    qtyInput.removeAttribute('max');
    qtyInput.style.borderColor = 'var(--gray-300)';
    qtyInput.style.backgroundColor = 'white';
    qtyInput.style.color = 'inherit';
    if (warningEl) warningEl.style.display = 'none';
    input.style.borderColor = val.length > 2 ? '#f59e0b' : 'var(--gray-300)';
    input.style.backgroundColor = 'white';
    stockEl.textContent = '—';
    stockEl.style.color = 'inherit';
    if (metaEl) {
      metaEl.innerHTML = val.length > 2 ? '<span style="color:#d97706;font-size:10px;"><i class="fas fa-exclamation-circle"></i> Pick from the dropdown list above</span>' : '';
    }
  }
  reqCalcRow(tr);
}

function reqCalcRow(tr) {
  const qtyInput = tr.querySelector('.req-item-qty');
  const qty = parseFloat(qtyInput?.value) || 0;
  const price = parseFloat(tr.querySelector('.req-item-price')?.value) || 0;
  const stockAttr = qtyInput?.getAttribute('data-stock');
  const warningEl = tr.querySelector('.req-qty-warning');

  if (stockAttr !== null && stockAttr !== undefined && !qtyInput.disabled) {
    const stock = parseFloat(stockAttr) || 0;
    if (stock <= 0) {
      qtyInput.style.borderColor = '#dc2626';
      qtyInput.style.backgroundColor = '#fee2e2';
      if (warningEl) {
        warningEl.textContent = 'Out of stock (0 in store)';
        warningEl.style.display = 'block';
      }
    } else if (qty > stock) {
      qtyInput.style.borderColor = '#dc2626';
      qtyInput.style.backgroundColor = '#fee2e2';
      if (warningEl) {
        warningEl.textContent = `Max: ${stock} in store`;
        warningEl.style.display = 'block';
      }
    } else {
      qtyInput.style.borderColor = 'var(--gray-300)';
      qtyInput.style.backgroundColor = 'white';
      if (warningEl) {
        warningEl.style.display = 'none';
      }
    }
  }

  const subtotal = qty * price;
  tr.querySelector('.req-item-subtotal').textContent = qFmt(subtotal) + ' RWF';
  reqCalcGrandTotal();
}

function reqCalcGrandTotal() {
  let total = 0;
  document.querySelectorAll('#req-m-items-tbody tr').forEach(tr => {
    const qty = parseFloat(tr.querySelector('.req-item-qty')?.value) || 0;
    const price = parseFloat(tr.querySelector('.req-item-price')?.value) || 0;
    total += (qty * price);
  });
  const el = document.getElementById('req-m-grand-total');
  if (el) el.textContent = qFmt(total) + ' RWF';
}

async function reqSave(autoSubmit) {
  const store = document.getElementById('req-m-store').value;
  const dept = document.getElementById('req-m-dept').value;
  const al = document.getElementById('req-m-alert');
  al.style.display = 'none';

  if (!store) {
    al.textContent = 'Please select a source store.';
    al.style.display = 'block';
    return;
  }
  if (!dept) {
    al.textContent = 'Please select the requesting department.';
    al.style.display = 'block';
    return;
  }

  const items = [];
  const errors = [];
  document.querySelectorAll('#req-m-items-tbody tr').forEach(tr => {
    const itmId = tr.querySelector('.req-item-select')?.value;
    const qtyInput = tr.querySelector('.req-item-qty');
    const qty = parseInt(qtyInput?.value) || 0;
    const price = parseFloat(tr.querySelector('.req-item-price')?.value) || 0;
    const stockAttr = qtyInput?.getAttribute('data-stock');
    const searchVal = tr.querySelector('.req-item-search')?.value.trim() || 'Item';
    const itemName = qtyInput?.getAttribute('data-item-name') || searchVal;

    if (itmId) {
      if (stockAttr !== null && stockAttr !== undefined) {
        const stock = parseFloat(stockAttr) || 0;
        if (stock <= 0) {
          errors.push(`"${itemName}" is out of stock in the selected store (0 available).`);
        } else if (qty > stock) {
          errors.push(`"${itemName}": Requested quantity (${qty}) exceeds available store stock (${stock}).`);
        } else if (qty <= 0) {
          errors.push(`"${itemName}": Requested quantity must be at least 1.`);
        } else {
          items.push({
            item_id: parseInt(itmId),
            quantity_requested: qty,
            unit_price: price
          });
        }
      } else if (qty > 0) {
        items.push({
          item_id: parseInt(itmId),
          quantity_requested: qty,
          unit_price: price
        });
      }
    }
  });

  if (errors.length > 0) {
    al.innerHTML = '<strong>⚠️ Cannot save requisition:</strong><ul style="margin:6px 0 0 16px;padding:0;">' + 
      errors.map(e => `<li>${qEsc(e)}</li>`).join('') + '</ul>';
    al.style.display = 'block';
    return;
  }

  if (!items.length) {
    al.textContent = 'Please select at least one consumable item with available stock from the suggestion list.';
    al.style.display = 'block';
    return;
  }

  const body = {
    store_id: parseInt(store),
    department_id: parseInt(dept),
    priority: document.getElementById('req-m-priority').value,
    purpose: document.getElementById('req-m-purpose').value.trim(),
    items: items,
    auto_submit: autoSubmit
  };

  try {
    const r = await fetch(reqAPI, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body)
    });
    const j = await r.json();
    if (j.success) {
      document.getElementById('req-modal').remove();
      reqLoad();
    } else {
      al.textContent = j.message || 'Failed to create requisition.';
      al.style.display = 'block';
    }
  } catch(e) {
    al.textContent = 'Network or server request failed.';
    al.style.display = 'block';
  }
}

// ===== ACTIONS =====
async function reqSubmit(id){
  if (!confirm('Are you sure you want to submit this requisition for verification and approval?')) return;
  try {
    const r = await fetch(reqAPI + '&action=submit&id=' + id, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({})
    });
    const j = await r.json();
    if (j.success) reqLoad();
    else alert(j.message || 'Failed to submit');
  } catch(e) {
    alert('Server error');
  }
}

async function reqDel(id){
  if (!confirm('Are you sure you want to delete this requisition?')) return;
  try {
    const r = await fetch(reqAPI + '&id=' + id, {method: 'DELETE'});
    const j = await r.json();
    if (j.success) reqLoad();
    else alert(j.message || 'Failed to delete');
  } catch(e) {
    alert('Server error');
  }
}

// ===== VIEW DETAILS MODAL =====
async function reqView(id){
  try {
    const r = await fetch(reqAPI + '&id=' + id);
    const j = await r.json();
    if (!j.success) { alert(j.message || 'Failed'); return; }
    const d = j.data;

    let totalRequestedVal = 0;
    let totalApprovedVal = 0;

    const itemRows = d.items.map(i => {
      const reqVal = (i.quantity_requested || 0) * (parseFloat(i.unit_price) || 0);
      const appVal = (i.quantity_approved !== null ? i.quantity_approved : 0) * (parseFloat(i.unit_price) || 0);
      totalRequestedVal += reqVal;
      totalApprovedVal += appVal;

      return `<tr style="border-top:1px solid var(--gray-100);">
        <td style="padding:10px 12px;">
          <div style="font-weight:600;color:var(--gray-800);">${qEsc(i.item_code)} — ${qEsc(i.item_name)}</div>
          <div style="font-size:11px;color:var(--gray-500);"><span style="background:#e0e7ff;color:#3730a3;padding:1px 6px;border-radius:4px;">${qEsc(i.category_name || 'Consumable')}</span></div>
        </td>
        <td style="padding:10px 12px;text-align:right;">${qFmt(i.unit_price)} RWF</td>
        <td style="padding:10px 12px;text-align:center;font-weight:600;">${i.quantity_requested}</td>
        <td style="padding:10px 12px;text-align:center;font-weight:700;color:${i.quantity_approved > 0 ? '#059669' : '#6b7280'};">${i.quantity_approved ?? '—'}</td>
        <td style="padding:10px 12px;text-align:center;font-weight:700;color:${i.quantity_issued > 0 ? '#7c3aed' : '#6b7280'};">${i.quantity_issued ?? '—'}</td>
        <td style="padding:10px 12px;text-align:right;font-weight:700;">${qFmt(i.quantity_approved !== null ? appVal : reqVal)} RWF</td>
      </tr>`;
    }).join('');

    const jeBanner = d.journal_reference 
      ? `<div style="margin-bottom:14px;padding:12px 16px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;display:flex;align-items:center;gap:10px;">
          <div style="font-size:20px;color:#166534;"><i class="fas fa-check-circle"></i></div>
          <div>
            <div style="font-weight:700;color:#166534;font-size:13px;">Stock Reduced & Journal Entry Created</div>
            <div style="font-size:12px;color:#15803d;font-family:monospace;margin-top:2px;">
              Reference: <strong>${qEsc(d.journal_reference)}</strong> | Expense debited and Inventory credited in General Ledger
            </div>
          </div>
        </div>`
      : '';

    const h = `
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1001;display:flex;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;backdrop-filter:blur(2px);" id="req-view-modal">
      <div style="background:white;border-radius:14px;padding:26px;width:100%;max-width:750px;margin:20px auto;position:relative;box-shadow:0 20px 40px rgba(0,0,0,0.2);">
        <button onclick="document.getElementById('req-view-modal').remove()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
        
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;border-bottom:1px solid var(--gray-200);padding-bottom:14px;">
          <div>
            <h3 style="font-size:18px;font-weight:700;color:var(--gray-900);margin:0 0 4px 0;">Requisition: ${qEsc(d.requisition_no)}</h3>
            <div style="font-size:12px;color:var(--gray-500);">Created on ${d.created_at || '—'}</div>
          </div>
          <div>
            <span style="padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;text-transform:capitalize;background:${stColors[d.status]||'#6b7280'}15;color:${stColors[d.status]||'#6b7280'};">
              ${d.status === 'approved' ? 'Approved & Issued' : d.status}
            </span>
          </div>
        </div>

        ${jeBanner}

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px;background:var(--gray-50);padding:14px;border-radius:10px;border:1px solid var(--gray-200);font-size:13px;">
          <div><span style="color:var(--gray-500);font-size:11px;display:block;">Requested By</span><strong>${qEsc(d.requester_first)} ${qEsc(d.requester_last)}</strong></div>
          <div><span style="color:var(--gray-500);font-size:11px;display:block;">Department</span><strong>${qEsc(d.dept_name || 'General')}</strong></div>
          <div><span style="color:var(--gray-500);font-size:11px;display:block;">Source Store</span><strong>${qEsc(d.store_name)}</strong></div>
          <div><span style="color:var(--gray-500);font-size:11px;display:block;">Priority</span><span style="padding:2px 8px;border-radius:8px;font-size:11px;font-weight:700;text-transform:uppercase;background:${prColors[d.priority]}20;color:${prColors[d.priority]};">${d.priority}</span></div>
          ${d.purpose ? `<div style="grid-column:1/-1;"><span style="color:var(--gray-500);font-size:11px;display:block;">Purpose</span><div>${qEsc(d.purpose)}</div></div>` : ''}
          ${d.reviewed_by ? `<div style="grid-column:1/-1;border-top:1px dashed var(--gray-200);padding-top:8px;margin-top:4px;"><span style="color:var(--gray-500);font-size:11px;display:block;">Reviewed / Approved By</span><strong>${qEsc(d.reviewer_first)} ${qEsc(d.reviewer_last)}</strong> on ${d.reviewed_at || '—'}${d.review_remarks ? ' — <em>' + qEsc(d.review_remarks) + '</em>' : ''}</div>` : ''}
        </div>

        <h4 style="font-size:14px;font-weight:700;margin:0 0 10px 0;">Requisition Items Breakdown</h4>
        <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:14px;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:var(--gray-100);">
                <th style="padding:8px 12px;text-align:left;">Item</th>
                <th style="padding:8px 12px;text-align:right;">Unit Cost</th>
                <th style="padding:8px 12px;text-align:center;">Requested</th>
                <th style="padding:8px 12px;text-align:center;">Approved</th>
                <th style="padding:8px 12px;text-align:center;">Issued</th>
                <th style="padding:8px 12px;text-align:right;">Subtotal</th>
              </tr>
            </thead>
            <tbody>${itemRows}</tbody>
            <tfoot>
              <tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);font-weight:700;">
                <td colspan="5" style="padding:10px 12px;text-align:right;">Total Requisition Value:</td>
                <td style="padding:10px 12px;text-align:right;color:var(--primary-color);font-size:14px;">
                  ${qFmt(d.status === 'approved' || d.status === 'issued' ? totalApprovedVal : totalRequestedVal)} RWF
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div style="display:flex;justify-content:flex-end;">
          <button onclick="document.getElementById('req-view-modal').remove()" style="padding:10px 22px;background:white;color:var(--gray-700);border:1px solid var(--gray-300);border-radius:8px;font-weight:600;cursor:pointer;">
            Close
          </button>
        </div>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', h);
  } catch(e) {
    alert('Error loading requisition details');
  }
}

// ===== REVIEW & APPROVAL MODAL (REDUCE STOCK + JOURNAL ENTRY) =====
async function reqReview(id){
  try {
    const r = await fetch(reqAPI + '&id=' + id);
    const j = await r.json();
    if (!j.success) { alert('Failed'); return; }
    const d = j.data;

    let initialTotalCost = 0;
    const itemRows = d.items.map(i => {
      const stock = parseFloat(i.current_stock || 0);
      const reqQty = parseInt(i.quantity_requested || 0);
      const price = parseFloat(i.unit_price || 0);
      const defaultApprove = stock > 0 ? Math.min(reqQty, stock) : 0;
      const subtotal = defaultApprove * price;
      initialTotalCost += subtotal;

      const stockWarning = stock <= 0 
        ? `<div style="font-size:10px;color:#dc2626;font-weight:700;"><i class="fas fa-ban"></i> Out of stock (0 in store)</div>` 
        : (stock < reqQty 
            ? `<div style="font-size:10px;color:#d97706;font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Low store stock: only ${stock} available</div>` 
            : `<div style="font-size:10px;color:#16a34a;font-weight:600;"><i class="fas fa-check"></i> ${stock} in store</div>`);

      return `<tr style="border-top:1px solid var(--gray-100);" class="rev-item-row" data-price="${price}">
        <td style="padding:10px 12px;">
          <div style="font-weight:600;color:var(--gray-800);">${qEsc(i.item_code)} — ${qEsc(i.item_name)}</div>
          <div style="font-size:11px;color:var(--gray-500);">${qEsc(i.category_name || 'Consumable')}</div>
          ${stockWarning}
        </td>
        <td style="padding:10px 12px;text-align:right;">${qFmt(price)} RWF</td>
        <td style="padding:10px 12px;text-align:center;font-weight:600;">${reqQty}</td>
        <td style="padding:10px 12px;text-align:center;">
          <input type="number" class="rev-qty" data-id="${i.id}" data-stock="${stock}" data-item-name="${qEsc(i.item_name)}" min="0" max="${stock}" value="${defaultApprove}" 
                 oninput="if(parseFloat(this.value) > ${stock}){ this.style.borderColor='#dc2626'; this.style.backgroundColor='#fee2e2'; } else { this.style.borderColor='var(--gray-300)'; this.style.backgroundColor='white'; } reqCalcRevTotal();" 
                 style="width:70px;padding:6px;border:1px solid ${stock<=0?'#fca5a5':'var(--gray-300)'};border-radius:6px;text-align:center;font-size:13px;font-weight:700;${stock<=0?'background:#fee2e2;color:#991b1b;':''}">
          ${stock < reqQty && stock > 0 ? `<div style="font-size:10px;color:#d97706;font-weight:600;margin-top:2px;">Max: ${stock}</div>` : ''}
        </td>
        <td style="padding:10px 12px;text-align:right;font-weight:700;" class="rev-item-subtotal">
          ${qFmt(subtotal)} RWF
        </td>
      </tr>`;
    }).join('');

    const h = `
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1001;display:flex;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;backdrop-filter:blur(2px);" id="req-review-modal">
      <div style="background:white;border-radius:14px;padding:26px;width:100%;max-width:800px;margin:20px auto;position:relative;box-shadow:0 20px 40px rgba(0,0,0,0.2);">
        <button onclick="document.getElementById('req-review-modal').remove()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
        
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
          <div style="width:36px;height:36px;border-radius:8px;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:18px;">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <div>
            <h3 style="font-size:18px;font-weight:700;color:var(--gray-900);margin:0;">Review Requisition: ${qEsc(d.requisition_no)}</h3>
            <p style="font-size:12px;color:var(--gray-500);margin:2px 0 0 0;">Requester: <strong>${qEsc(d.requester_first)} ${qEsc(d.requester_last)}</strong> (${qEsc(d.dept_name || 'General')}) | Store: <strong>${qEsc(d.store_name)}</strong></p>
          </div>
        </div>

        <!-- Automatic Journal & Stock Notice -->
        <div style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:12px 16px;margin-bottom:14px;display:flex;gap:10px;align-items:center;">
          <div style="font-size:20px;color:#059669;"><i class="fas fa-magic"></i></div>
          <div style="font-size:12px;color:#065f46;">
            <strong>Automated Action on Approval:</strong> Approving this requisition will automatically reduce inventory from <strong>${qEsc(d.store_name)}</strong> and create a General Ledger Journal Entry (<strong>Debit: Consumables Expense</strong>, <strong>Credit: Inventory Asset</strong>).
          </div>
        </div>

        <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;margin-bottom:14px;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
              <tr style="background:var(--gray-100);">
                <th style="padding:9px 12px;text-align:left;">Item / Supply</th>
                <th style="padding:9px 12px;text-align:right;">Unit Cost</th>
                <th style="padding:9px 12px;text-align:center;">Requested</th>
                <th style="padding:9px 12px;text-align:center;">Approve Qty</th>
                <th style="padding:9px 12px;text-align:right;">Approved Value</th>
              </tr>
            </thead>
            <tbody>${itemRows}</tbody>
            <tfoot>
              <tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);font-weight:700;">
                <td colspan="4" style="padding:10px 12px;text-align:right;">Total Approved Expense to Post:</td>
                <td style="padding:10px 12px;text-align:right;color:#059669;font-size:15px;" id="rev-total-val">
                  ${qFmt(initialTotalCost)} RWF
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div style="margin-bottom:16px;">
          <label style="font-size:12px;font-weight:600;color:var(--gray-700);display:block;margin-bottom:4px;">Approval / Review Remarks</label>
          <textarea id="rev-remarks" rows="2" placeholder="Add optional remarks or instructions..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-300);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
        </div>

        <div style="display:flex;gap:10px;">
          <button onclick="reqDoApprove(${d.id})" style="flex:2;padding:12px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:13px;">
            <i class="fas fa-check-circle" style="margin-right:6px;"></i> Approve, Reduce Stock & Post Expense
          </button>
          <button onclick="reqDoReject(${d.id})" style="flex:1;padding:12px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
            <i class="fas fa-times-circle" style="margin-right:4px;"></i> Reject
          </button>
          <button onclick="document.getElementById('req-review-modal').remove()" style="padding:12px 20px;background:white;color:var(--gray-700);border:1px solid var(--gray-300);border-radius:8px;cursor:pointer;">
            Cancel
          </button>
        </div>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', h);
  } catch(e) {
    alert('Error loading review modal');
  }
}

function reqCalcRevTotal() {
  let total = 0;
  document.querySelectorAll('#req-review-modal .rev-item-row').forEach(tr => {
    const price = parseFloat(tr.dataset.price) || 0;
    const qty = parseInt(tr.querySelector('.rev-qty').value) || 0;
    const sub = price * qty;
    tr.querySelector('.rev-item-subtotal').textContent = qFmt(sub) + ' RWF';
    total += sub;
  });
  const el = document.getElementById('rev-total-val');
  if (el) el.textContent = qFmt(total) + ' RWF';
}

async function reqDoApprove(id){
  const remarks = document.getElementById('rev-remarks').value.trim();
  const qtyInputs = document.querySelectorAll('.rev-qty');
  const errors = [];
  const items = [];

  qtyInputs.forEach(inp => {
    const qty = parseInt(inp.value) || 0;
    const stock = parseFloat(inp.getAttribute('data-stock')) || 0;
    const name = inp.getAttribute('data-item-name') || 'Item';
    if (qty > 0 && qty > stock) {
      errors.push(`"${name}": Approved quantity (${qty}) exceeds available store stock (${stock}).`);
    }
    items.push({
      id: parseInt(inp.dataset.id),
      quantity_approved: qty
    });
  });

  if (errors.length > 0) {
    return alert('⚠️ Warning - Cannot Approve:\n\n' + errors.join('\n') + '\n\nPlease adjust approved quantities to match available store stock.');
  }

  if (!confirm('Approve this requisition? This will deduct stock in batches and create a Journal Entry for recorded expenses.')) return;

  try {
    const r = await fetch(reqAPI + '&action=approve&id=' + id, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({remarks, items})
    });
    const j = await r.json();
    if (j.success) {
      document.getElementById('req-review-modal').remove();
      alert(`Requisition approved successfully!\n${j.data?.journal_reference ? 'Journal Entry: ' + j.data.journal_reference : ''}\nTotal Expense: ${qFmt(j.data?.total_cost)} RWF`);
      reqLoad();
    } else {
      alert('⚠️ ' + (j.message || 'Approval failed'));
    }
  } catch(e) {
    alert('Server error during approval');
  }
}

async function reqDoReject(id){
  const remarks = document.getElementById('rev-remarks').value.trim();
  if (!remarks) {
    alert('Rejection remarks are required.');
    document.getElementById('rev-remarks').focus();
    return;
  }
  if (!confirm('Are you sure you want to reject this requisition?')) return;
  try {
    const r = await fetch(reqAPI + '&action=reject&id=' + id, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({remarks})
    });
    const j = await r.json();
    if (j.success) {
      document.getElementById('req-review-modal').remove();
      reqLoad();
    } else {
      alert(j.message || 'Rejection failed');
    }
  } catch(e) {
    alert('Server error');
  }
}

// Initial Load
reqInitLookups().then(() => {
  reqLoad();
});
