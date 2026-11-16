<?php
require_once __DIR__ . '/includes/header.php';
$isAdmin = isset($_SESSION['user_data']['is_admin']) && $_SESSION['user_data']['is_admin'];
$userId = $_SESSION['user_id'] ?? 0;
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
  <div>
    <h2 style="font-size:24px;font-weight:600;">Deletion & Edit Requests</h2>
    <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Review, approve, or reject deletion and edit requests</p>
  </div>
  <div style="display:flex;gap:8px;">
    <button id="dr-tab-delete" onclick="drSwitchTab('delete')" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;"><i class="fas fa-trash"></i> Deletion Requests</button>
    <button id="dr-tab-edit" onclick="drSwitchTab('edit')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;"><i class="fas fa-edit"></i> Edit Requests</button>
  </div>
</div>

<!-- Stats -->
<div id="dr-delete-section">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:20px;">
  <div style="padding:16px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--warning-color);">
    <div id="dr-stat-pending" style="font-size:26px;font-weight:700;color:var(--warning-color);">—</div>
    <div style="font-size:12px;color:var(--gray-500);">Pending</div>
  </div>
  <div style="padding:16px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--success-color);">
    <div id="dr-stat-approved" style="font-size:26px;font-weight:700;color:var(--success-color);">—</div>
    <div style="font-size:12px;color:var(--gray-500);">Approved</div>
  </div>
  <div style="padding:16px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--danger-color);">
    <div id="dr-stat-rejected" style="font-size:26px;font-weight:700;color:var(--danger-color);">—</div>
    <div style="font-size:12px;color:var(--gray-500);">Rejected</div>
  </div>
  <div style="padding:16px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--gray-400);">
    <div id="dr-stat-executed" style="font-size:26px;font-weight:700;color:var(--gray-400);">—</div>
    <div style="font-size:12px;color:var(--gray-500);">Executed</div>
  </div>
</div>

<!-- Filters -->
<div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <select id="dr-filter-status" onchange="drPage=1;drLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
    <option value="">All Status</option>
    <option value="pending">Pending</option>
    <option value="approved">Approved</option>
    <option value="rejected">Rejected</option>
    <option value="executed">Executed</option>
  </select>
  <select id="dr-filter-type" onchange="drPage=1;drLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
    <option value="">All Types</option>
    <option value="patient">Patient</option>
  </select>
  <button onclick="drLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-sync-alt"></i> Refresh</button>
</div>

<!-- Table -->
<div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
  <table style="width:100%;border-collapse:collapse;font-size:13px;">
    <thead>
      <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:12px 14px;text-align:left;font-weight:600;">#</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Entity</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Requested By</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Date</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Remarks</th>
        <th style="padding:12px 14px;text-align:left;font-weight:600;">Status</th>
        <th style="padding:12px 14px;text-align:center;font-weight:600;">Actions</th>
      </tr>
    </thead>
    <tbody id="dr-tbody">
      <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i></td></tr>
    </tbody>
  </table>
  <div id="dr-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--gray-200);"></div>
</div>

<!-- Detail Modal -->
<div id="dr-detail-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;position:relative;">
    <button onclick="document.getElementById('dr-detail-modal').style.display='none'" style="position:absolute;top:14px;right:14px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <div id="dr-detail-content">Loading...</div>
  </div>
</div>
</div><!-- end dr-delete-section -->

<!-- Edit Requests Section -->
<div id="dr-edit-section" style="display:none;">
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:20px;">
    <div style="padding:16px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--warning-color);">
      <div id="er-stat-pending" style="font-size:26px;font-weight:700;color:var(--warning-color);">—</div>
      <div style="font-size:12px;color:var(--gray-500);">Pending</div>
    </div>
    <div style="padding:16px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--success-color);">
      <div id="er-stat-approved" style="font-size:26px;font-weight:700;color:var(--success-color);">—</div>
      <div style="font-size:12px;color:var(--gray-500);">Approved</div>
    </div>
    <div style="padding:16px;background:white;border-radius:10px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--danger-color);">
      <div id="er-stat-rejected" style="font-size:26px;font-weight:700;color:var(--danger-color);">—</div>
      <div style="font-size:12px;color:var(--gray-500);">Rejected</div>
    </div>
  </div>
  <div style="background:white;border-radius:var(--radius);padding:14px 18px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;align-items:center;">
    <select id="er-filter-status" onchange="erPage=1;erLoad()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
      <option value="">All Status</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
    </select>
    <button onclick="erLoad()" style="padding:9px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-sync-alt"></i> Refresh</button>
  </div>
  <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
          <th style="padding:12px 14px;text-align:left;font-weight:600;">#</th>
          <th style="padding:12px 14px;text-align:left;font-weight:600;">Patient</th>
          <th style="padding:12px 14px;text-align:left;font-weight:600;">Requested By</th>
          <th style="padding:12px 14px;text-align:left;font-weight:600;">Date</th>
          <th style="padding:12px 14px;text-align:left;font-weight:600;">Reason</th>
          <th style="padding:12px 14px;text-align:left;font-weight:600;">Status</th>
          <th style="padding:12px 14px;text-align:center;font-weight:600;">Actions</th>
        </tr>
      </thead>
      <tbody id="er-tbody">
        <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i></td></tr>
      </tbody>
    </table>
  </div>
</div><!-- end dr-edit-section -->

<script>
const drAPI = (typeof _apiBase !== 'undefined' ? _apiBase : window.location.origin + '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/api.php') + '?endpoint=deletion-requests';
const drUserId = <?php echo (int)$userId; ?>;
const drIsAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
let drPage = 1;

function drEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function drLoad(){
  const status = document.getElementById('dr-filter-status').value;
  const type = document.getElementById('dr-filter-type').value;
  const params = new URLSearchParams({page: drPage, limit: 20});
  if(status) params.set('status', status);
  if(type) params.set('entity_type', type);
  const tbody = document.getElementById('dr-tbody');
  tbody.innerHTML='<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const r = await fetch(drAPI + '&action=list&' + params);
    const j = await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:#dc2626;">Error</td></tr>';return;}
    const {requests, stats, pagination} = j.data;

    // Stats
    document.getElementById('dr-stat-pending').textContent = stats.pending || 0;
    document.getElementById('dr-stat-approved').textContent = stats.approved || 0;
    document.getElementById('dr-stat-rejected').textContent = stats.rejected || 0;
    document.getElementById('dr-stat-executed').textContent = stats.executed || 0;

    if(!requests.length){
      tbody.innerHTML='<tr><td colspan="7" style="padding:50px;text-align:center;color:var(--gray-400);"><i class="fas fa-shield-alt" style="font-size:28px;display:block;margin-bottom:8px;"></i>No deletion requests found.</td></tr>';
      document.getElementById('dr-pagination').innerHTML='';
      return;
    }

    const sc = {pending:'#f59e0b', approved:'#16a34a', rejected:'#dc2626', executed:'#6b7280'};
    tbody.innerHTML = requests.map(r => {
      const remarkPreview = (r.remarks||'').length > 50 ? r.remarks.substring(0,50)+'...' : r.remarks;
      let actions = `<button onclick="drShowDetail(${r.id})" style="padding:4px 10px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;margin-right:3px;" title="View"><i class="fas fa-eye"></i></button>`;
      if(r.status==='pending' && drIsAdmin){
        actions += `<button onclick="drReview(${r.id},'approved')" style="padding:4px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;margin-right:3px;" title="Approve"><i class="fas fa-check"></i></button>`;
        actions += `<button onclick="drReview(${r.id},'rejected')" style="padding:4px 10px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;" title="Reject"><i class="fas fa-times"></i></button>`;
      }
      if(r.status==='approved' && (parseInt(r.requested_by)===drUserId || drIsAdmin)){
        actions += `<button onclick="drExecute(${r.id})" style="padding:4px 10px;background:#7c3aed;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;" title="Execute Deletion"><i class="fas fa-trash"></i> Delete</button>`;
      }
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 14px;font-size:12px;color:var(--gray-500);">${r.id}</td>
        <td style="padding:10px 14px;"><div style="font-weight:600;">${drEsc(r.entity_name||'—')}</div><div style="font-size:11px;color:var(--gray-500);">${drEsc(r.entity_type)} #${r.entity_id}</div></td>
        <td style="padding:10px 14px;font-size:12px;">${drEsc(r.requested_by_name||'User #'+r.requested_by)}</td>
        <td style="padding:10px 14px;font-size:12px;color:var(--gray-600);">${(r.requested_at||'').substring(0,16)}</td>
        <td style="padding:10px 14px;font-size:12px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${drEsc(r.remarks)}">${drEsc(remarkPreview)}</td>
        <td style="padding:10px 14px;"><span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:${sc[r.status]||'#ccc'}20;color:${sc[r.status]||'#666'};">${r.status.toUpperCase()}</span></td>
        <td style="padding:10px 14px;text-align:center;white-space:nowrap;">${actions}</td>
      </tr>`;
    }).join('');

    // Pagination
    const pgEl = document.getElementById('dr-pagination');
    if(pagination.total_pages > 1){
      let btns = `<button onclick="drPage--;drLoad()" ${drPage<=1?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${drPage<=1?'var(--gray-100)':'white'};border-radius:6px;cursor:${drPage<=1?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-left"></i></button>`;
      let sp=Math.max(1,drPage-3),ep=Math.min(pagination.total_pages,sp+6);if(ep-sp<6)sp=Math.max(1,ep-6);
      for(let p=sp;p<=ep;p++){const a=p===drPage;btns+=`<button onclick="drPage=${p};drLoad()" style="padding:6px 10px;border:1px solid ${a?'var(--primary-color)':'var(--gray-200)'};background:${a?'var(--primary-color)':'white'};color:${a?'white':'var(--gray-700)'};border-radius:6px;cursor:pointer;font-size:12px;font-weight:${a?'600':'400'};">${p}</button>`;}
      btns += `<button onclick="drPage++;drLoad()" ${drPage>=pagination.total_pages?'disabled':''} style="padding:6px 12px;border:1px solid var(--gray-200);background:${drPage>=pagination.total_pages?'var(--gray-100)':'white'};border-radius:6px;cursor:${drPage>=pagination.total_pages?'not-allowed':'pointer'};font-size:12px;"><i class="fas fa-chevron-right"></i></button>`;
      pgEl.innerHTML = `<span style="font-size:12px;color:var(--gray-500);">Page ${pagination.page} of ${pagination.total_pages} (${pagination.total} total)</span><div style="display:flex;gap:4px;">${btns}</div>`;
    } else {
      pgEl.innerHTML = `<span style="font-size:12px;color:var(--gray-500);">${pagination.total} request${pagination.total!==1?'s':''}</span><div></div>`;
    }
  }catch(e){tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:#dc2626;">Failed to load</td></tr>';}
}

async function drShowDetail(id){
  document.getElementById('dr-detail-content').innerHTML='<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin" style="font-size:20px;color:var(--gray-400);"></i></div>';
  document.getElementById('dr-detail-modal').style.display='flex';
  try{
    const r = await fetch(drAPI + '&action=detail&id=' + id);
    const j = await r.json();
    if(!j.success){document.getElementById('dr-detail-content').textContent='Error loading';return;}
    const d = j.data;
    const sc = {pending:'#f59e0b', approved:'#16a34a', rejected:'#dc2626', executed:'#6b7280'};
    let html = `<h3 style="font-size:17px;font-weight:600;margin-bottom:16px;"><i class="fas fa-file-alt" style="color:var(--primary-color);margin-right:6px;"></i>Deletion Request #${d.id}</h3>`;
    html += `<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:13px;margin-bottom:16px;">
      <div><span style="color:var(--gray-500);">Entity:</span> <strong>${drEsc(d.entity_name)}</strong></div>
      <div><span style="color:var(--gray-500);">Type:</span> ${drEsc(d.entity_type)} #${d.entity_id}</div>
      <div><span style="color:var(--gray-500);">Requested By:</span> ${drEsc(d.requested_by_name||'User #'+d.requested_by)}</div>
      <div><span style="color:var(--gray-500);">Date:</span> ${(d.requested_at||'').substring(0,16)}</div>
      <div><span style="color:var(--gray-500);">Status:</span> <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:${sc[d.status]}20;color:${sc[d.status]};">${d.status.toUpperCase()}</span></div>
      ${d.reviewed_by_name?`<div><span style="color:var(--gray-500);">Reviewed By:</span> ${drEsc(d.reviewed_by_name)} (${(d.reviewed_at||'').substring(0,16)})</div>`:''}
      ${d.executed_by_name?`<div><span style="color:var(--gray-500);">Executed By:</span> ${drEsc(d.executed_by_name)} (${(d.executed_at||'').substring(0,16)})</div>`:''}
    </div>`;
    html += `<div style="margin-bottom:16px;"><div style="font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Remarks:</div><div style="padding:12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;font-size:13px;white-space:pre-wrap;">${drEsc(d.remarks)}</div></div>`;

    // Show patient/entity details if available
    if(d.entity_details){
      const p = d.entity_details;
      const insLabel = p.insurance_company ? '<span style="color:#16a34a;font-weight:600;">'+drEsc(p.insurance_company)+'</span>' + (p.package_name ? ' ('+drEsc(p.package_name)+')' : '') : '<span style="color:var(--gray-400);">None</span>';
      let patientHtml = `<div style="margin-bottom:16px;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
        <div style="background:var(--primary-color);color:white;padding:10px 14px;font-size:13px;font-weight:600;"><i class="fas fa-user"></i> Patient Information</div>
        <div style="padding:14px;font-size:13px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">
            <div><span style="color:var(--gray-500);">Name:</span> <strong>${drEsc(p.first_name)} ${drEsc(p.last_name)}</strong></div>
            <div><span style="color:var(--gray-500);">Patient No:</span> <strong style="color:var(--primary-color);font-family:monospace;">${drEsc(p.patient_no)}</strong></div>
            <div><span style="color:var(--gray-500);">Phone:</span> ${drEsc(p.phone||'—')}</div>
            <div><span style="color:var(--gray-500);">Gender:</span> ${drEsc(p.gender||'—')}</div>
            <div><span style="color:var(--gray-500);">DOB:</span> ${drEsc(p.date_of_birth||'—')}</div>
            <div><span style="color:var(--gray-500);">Insurance:</span> ${insLabel}</div>
            <div><span style="color:var(--gray-500);">Status:</span> ${drEsc(p.status||'—')}</div>
            <div><span style="color:var(--gray-500);">Registered:</span> ${(p.created_at||'').substring(0,10)}</div>
          </div>`;

      // Billing summary
      if(p.billing_summary){
        const b = p.billing_summary;
        patientHtml += `<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 12px;margin-bottom:10px;">
          <div style="font-size:11px;font-weight:600;color:#1e40af;margin-bottom:4px;">Billing Summary</div>
          <div style="display:flex;gap:16px;font-size:12px;">
            <span>Invoices: <strong>${b.invoice_count}</strong></span>
            <span>Billed: <strong>${parseFloat(b.total_billed||0).toLocaleString()} RWF</strong></span>
            <span style="color:#16a34a;">Paid: <strong>${parseFloat(b.total_paid||0).toLocaleString()}</strong></span>
            <span style="color:#dc2626;">Balance: <strong>${parseFloat(b.total_balance||0).toLocaleString()}</strong></span>
          </div>
        </div>`;
      }

      // Visits
      if(p.visits && p.visits.length){
        patientHtml += '<div style="font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Recent Visits ('+p.visits.length+')</div>';
        patientHtml += '<table style="width:100%;border-collapse:collapse;font-size:11px;border:1px solid var(--gray-200);border-radius:6px;overflow:hidden;"><thead><tr style="background:var(--gray-50);"><th style="padding:6px 8px;text-align:left;">Visit No</th><th style="padding:6px 8px;text-align:left;">Date</th><th style="padding:6px 8px;text-align:left;">Type</th><th style="padding:6px 8px;text-align:left;">Dept</th><th style="padding:6px 8px;text-align:left;">Payment</th><th style="padding:6px 8px;text-align:left;">Status</th></tr></thead><tbody>';
        p.visits.forEach(function(v){
          const stBg = v.status==='completed'?'#d1fae5':v.status==='open'?'#dbeafe':'#fef3c7';
          const stColor = v.status==='completed'?'#065f46':v.status==='open'?'#1e40af':'#92400e';
          patientHtml += '<tr style="border-top:1px solid var(--gray-100);">';
          patientHtml += '<td style="padding:5px 8px;font-family:monospace;color:var(--primary-color);">'+drEsc(v.visit_no)+'</td>';
          patientHtml += '<td style="padding:5px 8px;">'+drEsc(v.visit_date)+'</td>';
          patientHtml += '<td style="padding:5px 8px;">'+drEsc(v.visit_type||'—')+'</td>';
          patientHtml += '<td style="padding:5px 8px;">'+drEsc(v.department_name||'—')+'</td>';
          patientHtml += '<td style="padding:5px 8px;">'+drEsc(v.payment_type||'—')+'</td>';
          patientHtml += '<td style="padding:5px 8px;"><span style="padding:1px 6px;border-radius:8px;font-size:9px;font-weight:600;background:'+stBg+';color:'+stColor+';">'+drEsc(v.status)+'</span></td>';
          patientHtml += '</tr>';
        });
        patientHtml += '</tbody></table>';
      } else {
        patientHtml += '<div style="font-size:12px;color:var(--gray-400);font-style:italic;">No visits recorded.</div>';
      }
      patientHtml += '</div></div>';
      html += patientHtml;
    }

    // Procedure entity details
    if(d.entity_details && d.entity_type==='procedure'){
      const pr = d.entity_details;
      html += '<div style="margin-bottom:16px;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">';
      html += '<div style="background:#7c3aed;color:white;padding:10px 14px;font-size:13px;font-weight:600;"><i class="fas fa-syringe"></i> Procedure Details</div>';
      html += '<div style="padding:14px;font-size:13px;">';
      html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">';
      html += '<div><span style="color:var(--gray-500);">Procedure:</span> <strong>'+drEsc(pr.service_name||pr.description)+'</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Category:</span> '+drEsc(pr.speciality||pr.category||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Unit Price:</span> <strong>'+parseFloat(pr.unit_price||0).toLocaleString()+' RWF</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Total:</span> <strong>'+parseFloat(pr.total||0).toLocaleString()+' RWF</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Invoice:</span> <span style="font-family:monospace;color:var(--primary-color);">'+drEsc(pr.invoice_no)+'</span> ('+drEsc(pr.invoice_status)+')</div>';
      html += '<div><span style="color:var(--gray-500);">Payment:</span> '+drEsc(pr.payment_type||'—')+'</div>';
      html += '</div>';
      html += '<div style="padding-top:10px;border-top:1px solid var(--gray-100);margin-top:6px;">';
      html += '<div style="font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:6px;"><i class="fas fa-user" style="margin-right:4px;"></i>Patient</div>';
      html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">';
      html += '<div><span style="color:var(--gray-500);">Name:</span> <strong>'+drEsc(pr.patient_name)+'</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Patient No:</span> <span style="font-family:monospace;color:var(--primary-color);">'+drEsc(pr.patient_no)+'</span></div>';
      html += '<div><span style="color:var(--gray-500);">Phone:</span> '+drEsc(pr.phone||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Gender:</span> '+drEsc(pr.gender||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Insurance:</span> '+(pr.insurance_company?'<span style="color:#16a34a;font-weight:600;">'+drEsc(pr.insurance_company)+'</span>'+(pr.package_name?' ('+drEsc(pr.package_name)+')':''):'<span style="color:var(--gray-400);">None</span>')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Visit:</span> '+drEsc(pr.visit_no)+' ('+drEsc(pr.visit_date)+')</div>';
      html += '</div></div>';
      html += '</div></div>';
    }

    // Lab order entity details
    if(d.entity_details && d.entity_type==='lab_order'){
      const lb = d.entity_details;
      html += '<div style="margin-bottom:16px;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">';
      html += '<div style="background:#2563eb;color:white;padding:10px 14px;font-size:13px;font-weight:600;"><i class="fas fa-flask"></i> Lab Order Details</div>';
      html += '<div style="padding:14px;font-size:13px;">';
      html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">';
      html += '<div><span style="color:var(--gray-500);">Test:</span> <strong>'+drEsc(lb.description)+'</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Order Type:</span> '+drEsc(lb.order_type||'lab')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Status:</span> <strong>'+drEsc(lb.status||'—')+'</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Ordered By:</span> '+drEsc(lb.ordered_by_name||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Date:</span> '+drEsc(lb.ordered_at||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Payment:</span> '+drEsc(lb.payment_type||'—')+'</div>';
      html += '</div>';
      html += '<div style="padding-top:10px;border-top:1px solid var(--gray-100);margin-top:6px;">';
      html += '<div style="font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:6px;"><i class="fas fa-user" style="margin-right:4px;"></i>Patient</div>';
      html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">';
      html += '<div><span style="color:var(--gray-500);">Name:</span> <strong>'+drEsc(lb.patient_name)+'</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Patient No:</span> <span style="font-family:monospace;color:var(--primary-color);">'+drEsc(lb.patient_no)+'</span></div>';
      html += '<div><span style="color:var(--gray-500);">Phone:</span> '+drEsc(lb.phone||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Gender:</span> '+drEsc(lb.gender||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Insurance:</span> '+(lb.insurance_company?'<span style="color:#16a34a;font-weight:600;">'+drEsc(lb.insurance_company)+'</span>'+(lb.package_name?' ('+drEsc(lb.package_name)+')':''):'<span style="color:var(--gray-400);">None</span>')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Visit:</span> '+drEsc(lb.visit_no)+' ('+drEsc(lb.visit_date)+')</div>';
      html += '</div></div>';
      html += '</div></div>';
    }

    // Prescription entity details
    if(d.entity_details && d.entity_type==='prescription'){
      const rx = d.entity_details;
      html += '<div style="margin-bottom:16px;border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">';
      html += '<div style="background:#16a34a;color:white;padding:10px 14px;font-size:13px;font-weight:600;"><i class="fas fa-prescription"></i> Prescription Details</div>';
      html += '<div style="padding:14px;font-size:13px;">';
      html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px;">';
      html += '<div><span style="color:var(--gray-500);">Rx No:</span> <strong>'+drEsc(rx.prescription_no)+'</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Status:</span> <strong>'+drEsc(rx.status||'—')+'</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Doctor:</span> '+drEsc(rx.doctor_name||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Date:</span> '+drEsc(rx.prescription_date||rx.created_at||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Visit:</span> '+drEsc(rx.visit_no||'—')+' ('+drEsc(rx.visit_date||'—')+')</div>';
      html += '<div><span style="color:var(--gray-500);">Payment:</span> '+drEsc(rx.payment_type||'—')+'</div>';
      html += '</div>';
      if(rx.items && rx.items.length){
        html += '<div style="font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Medications ('+rx.items.length+')</div>';
        html += '<table style="width:100%;border-collapse:collapse;font-size:11px;border:1px solid var(--gray-200);border-radius:4px;">';
        html += '<thead><tr style="background:var(--gray-50);"><th style="padding:5px 8px;text-align:left;">Medication</th><th style="padding:5px 8px;">Dosage</th><th style="padding:5px 8px;">Frequency</th><th style="padding:5px 8px;">Qty</th><th style="padding:5px 8px;">Status</th></tr></thead><tbody>';
        rx.items.forEach(function(it){
          html += '<tr style="border-top:1px solid var(--gray-100);">';
          html += '<td style="padding:4px 8px;font-weight:500;">'+drEsc(it.item_name||it.instructions||'—')+'</td>';
          html += '<td style="padding:4px 8px;">'+drEsc(it.dosage||'—')+'</td>';
          html += '<td style="padding:4px 8px;">'+drEsc(it.frequency||'—')+'</td>';
          html += '<td style="padding:4px 8px;">'+drEsc(it.quantity||'—')+'</td>';
          html += '<td style="padding:4px 8px;">'+drEsc(it.status||'—')+'</td>';
          html += '</tr>';
        });
        html += '</tbody></table>';
      }
      html += '<div style="padding-top:10px;border-top:1px solid var(--gray-100);margin-top:10px;">';
      html += '<div style="font-size:11px;font-weight:600;color:var(--gray-700);margin-bottom:6px;"><i class="fas fa-user" style="margin-right:4px;"></i>Patient</div>';
      html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">';
      html += '<div><span style="color:var(--gray-500);">Name:</span> <strong>'+drEsc(rx.patient_name)+'</strong></div>';
      html += '<div><span style="color:var(--gray-500);">Patient No:</span> <span style="font-family:monospace;color:var(--primary-color);">'+drEsc(rx.patient_no)+'</span></div>';
      html += '<div><span style="color:var(--gray-500);">Phone:</span> '+drEsc(rx.phone||'—')+'</div>';
      html += '<div><span style="color:var(--gray-500);">Insurance:</span> '+(rx.insurance_company?'<span style="color:#16a34a;font-weight:600;">'+drEsc(rx.insurance_company)+'</span>'+(rx.package_name?' ('+drEsc(rx.package_name)+')':''):'<span style="color:var(--gray-400);">None</span>')+'</div>';
      html += '</div></div>';
      html += '</div></div>';
    }
    if(d.proof_document){
      html += `<div style="margin-bottom:16px;"><div style="font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Proof Document:</div><a href="${drEsc(d.proof_document)}" target="_blank" style="padding:8px 14px;background:var(--primary-color);color:white;border-radius:6px;font-size:12px;text-decoration:none;display:inline-block;"><i class="fas fa-download"></i> View/Download Proof</a></div>`;
    }
    if(d.review_notes){
      html += `<div style="margin-bottom:16px;"><div style="font-size:12px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Review Notes:</div><div style="padding:10px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;font-size:13px;">${drEsc(d.review_notes)}</div></div>`;
    }
    // Action buttons
    if(d.status==='pending' && drIsAdmin){
      html += `<div style="display:flex;gap:10px;border-top:1px solid var(--gray-200);padding-top:16px;">
        <button onclick="drReview(${d.id},'approved')" style="flex:1;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-check"></i> Approve</button>
        <button onclick="drReview(${d.id},'rejected')" style="flex:1;padding:10px;background:#dc2626;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-times"></i> Reject</button>
      </div>`;
    }
    if(d.status==='approved' && (parseInt(d.requested_by)===drUserId || drIsAdmin)){
      html += `<div style="border-top:1px solid var(--gray-200);padding-top:16px;">
        <button onclick="drExecute(${d.id})" style="width:100%;padding:12px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-trash"></i> Execute Deletion Now</button>
      </div>`;
    }
    document.getElementById('dr-detail-content').innerHTML = html;
  }catch(e){document.getElementById('dr-detail-content').textContent='Failed to load details.';}
}

async function drReview(id, status){
  const action = status==='approved' ? 'approve' : 'reject';
  if(status==='approved' && !confirm('Approving permanently deletes this record now.\nFor a patient this also deletes ALL their visits, invoices, payments, prescriptions and lab orders.\n\nThis CANNOT be undone. Continue?')) return;
  const notes = prompt((status==='approved'?'Approval':'Rejection')+' notes (optional):','');
  if(notes===null) return;
  try{
    const r = await fetch(drAPI + '&action=review', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:id, status:status, review_notes:notes, reviewed_by:drUserId})});
    const j = await r.json();
    alert(j.success ? '✓ '+j.message : 'Error: '+j.message);
    // Approval runs the deletion server-side, which can change the status even when it fails
    document.getElementById('dr-detail-modal').style.display='none';
    drLoad();
  }catch(e){alert('Failed');}
}

async function drExecute(id){
  if(!confirm('Are you sure you want to permanently delete this record?\n\nThis action CANNOT be undone. An audit log will be kept.')) return;
  try{
    const r = await fetch(drAPI + '&action=execute', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:id, executed_by:drUserId})});
    const j = await r.json();
    if(j.success){
      alert('✓ '+j.message);
      document.getElementById('dr-detail-modal').style.display='none';
      drLoad();
    } else alert('Error: '+j.message);
  }catch(e){alert('Failed');}
}

drLoad();

// ===== EDIT REQUESTS =====
const erAPI = (typeof _apiBase !== 'undefined' ? _apiBase : window.location.origin + '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/"); ?>/api.php') + '?endpoint=edit-requests';
let erPage = 1;

function drSwitchTab(tab){
  const delBtn=document.getElementById('dr-tab-delete');
  const editBtn=document.getElementById('dr-tab-edit');
  const delSection=document.getElementById('dr-delete-section');
  const editSection=document.getElementById('dr-edit-section');
  if(tab==='edit'){
    editBtn.style.cssText='padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;';
    delBtn.style.cssText='padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;';
    delSection.style.display='none';
    editSection.style.display='block';
    erLoad();
  } else {
    delBtn.style.cssText='padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;';
    editBtn.style.cssText='padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;';
    delSection.style.display='block';
    editSection.style.display='none';
  }
}

async function erLoad(){
  const status = document.getElementById('er-filter-status').value;
  const params = new URLSearchParams({page: erPage, limit: 20});
  if(status) params.set('status', status);
  const tbody = document.getElementById('er-tbody');
  tbody.innerHTML='<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const r = await fetch(erAPI + '&action=list&' + params);
    const j = await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:#dc2626;">Error</td></tr>';return;}
    const {requests, stats} = j.data;
    document.getElementById('er-stat-pending').textContent = stats.pending || 0;
    document.getElementById('er-stat-approved').textContent = stats.approved || 0;
    document.getElementById('er-stat-rejected').textContent = stats.rejected || 0;
    if(!requests.length){
      tbody.innerHTML='<tr><td colspan="7" style="padding:50px;text-align:center;color:var(--gray-400);"><i class="fas fa-edit" style="font-size:28px;display:block;margin-bottom:8px;"></i>No edit requests found.</td></tr>';
      return;
    }
    const sc = {pending:'#f59e0b', approved:'#16a34a', rejected:'#dc2626'};
    tbody.innerHTML = requests.map(r => {
      let actions = '';
      if(r.status==='pending' && drIsAdmin){
        actions += `<button onclick="erReview(${r.id},'approved')" style="padding:4px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;margin-right:3px;" title="Approve"><i class="fas fa-check"></i></button>`;
        actions += `<button onclick="erReview(${r.id},'rejected')" style="padding:4px 10px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;" title="Reject"><i class="fas fa-times"></i></button>`;
      }
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 14px;font-size:12px;color:var(--gray-500);">${r.id}</td>
        <td style="padding:10px 14px;"><div style="font-weight:600;">${drEsc(r.entity_name||'—')}</div><div style="font-size:11px;color:var(--gray-500);">${drEsc(r.entity_type)} #${r.entity_id}</div></td>
        <td style="padding:10px 14px;font-size:12px;">${drEsc(r.requested_by_name||'User #'+r.requested_by)}</td>
        <td style="padding:10px 14px;font-size:12px;color:var(--gray-600);">${(r.requested_at||'').substring(0,16)}</td>
        <td style="padding:10px 14px;font-size:12px;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${drEsc(r.reason)}">${drEsc(r.reason)}</td>
        <td style="padding:10px 14px;"><span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:${sc[r.status]||'#ccc'}20;color:${sc[r.status]||'#666'};">${r.status.toUpperCase()}</span></td>
        <td style="padding:10px 14px;text-align:center;white-space:nowrap;">${actions}</td>
      </tr>`;
    }).join('');
  }catch(e){tbody.innerHTML='<tr><td colspan="7" style="padding:30px;text-align:center;color:#dc2626;">Failed to load</td></tr>';}
}

async function erReview(id, status){
  const notes = prompt((status==='approved'?'Approval':'Rejection')+' notes (optional):','');
  if(notes===null) return;
  try{
    const r = await fetch(erAPI + '&action=review', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({id:id, status:status, review_notes:notes, reviewed_by:drUserId})});
    const j = await r.json();
    if(j.success){ alert('✓ Edit request '+status+'!'); erLoad(); }
    else alert('Error: '+j.message);
  }catch(e){alert('Failed');}
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
