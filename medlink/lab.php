<?php
// public/lab.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'test-requests';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Page title mapping
$subModuleTitles = [
    'test-requests' => 'Test Requests',
    'sample-collection' => 'Sample Collection',
    'results' => 'Results Entry',
    'consumption' => 'Confirm Item Consumption & Deduct Stock',
    'pathology' => 'Pathology',
    'microbiology' => 'Microbiology',
    'blood-bank' => 'Blood Bank',
    'statistics' => 'Laboratory Statistics',
    'test-settings' => 'Laboratory Test Settings'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'Laboratory';
?>

<!-- Page Header -->
<style>
.notif-bell-wrap{position:relative;display:inline-block;}
.notif-bell-btn{position:relative;padding:10px 14px;background:white;border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:16px;color:var(--gray-600);transition:all .2s;}
.notif-bell-btn:hover{background:var(--gray-50);color:var(--primary-color);}
.notif-bell-btn .notif-count{position:absolute;top:-4px;right:-4px;background:#dc2626;color:white;font-size:10px;font-weight:700;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;}
.notif-dropdown{display:none;position:absolute;top:calc(100% + 8px);right:0;width:380px;max-height:420px;background:white;border:1px solid var(--gray-200);border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,0.15);z-index:9999;overflow:hidden;}
.notif-dropdown.show{display:block;}
.notif-dropdown-header{padding:14px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;}
.notif-dropdown-header h4{font-size:14px;font-weight:700;margin:0;}
.notif-dropdown-body{max-height:340px;overflow-y:auto;}
.notif-item{padding:12px 16px;border-bottom:1px solid var(--gray-100);cursor:pointer;transition:background .15s;}
.notif-item:hover{background:var(--gray-50);}
.notif-item.unread{background:#eff6ff;border-left:3px solid #2563eb;}
.notif-item .notif-title{font-size:13px;font-weight:600;color:var(--gray-900);}
.notif-item .notif-msg{font-size:12px;color:var(--gray-600);margin-top:2px;}
.notif-item .notif-time{font-size:10px;color:var(--gray-400);margin-top:4px;}
.notif-item .notif-mark-btn{margin-top:6px;padding:4px 10px;background:#d1fae5;color:#065f46;border:none;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;}
.notif-item .notif-mark-btn:hover{background:#a7f3d0;}
.notif-empty{padding:40px;text-align:center;color:var(--gray-400);font-size:13px;}
.notif-mute-btn{position:relative;padding:10px 14px;background:white;border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:16px;color:var(--gray-600);transition:all .2s;}
.notif-mute-btn:hover{background:var(--gray-50);color:var(--primary-color);}
.notif-mute-btn.muted{color:#dc2626;border-color:#fecaca;background:#fef2f2;}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'test-requests' => 'Manage laboratory test requests from doctors',
                'sample-collection' => 'Collect and label patient samples',
                'results' => 'Enter and manage test results',
                'consumption' => 'Confirm consumable inventory items used for tests and deduct quantities from laboratory stock',
                'pathology' => 'Pathology testing and analysis',
                'microbiology' => 'Microbiology testing and culture',
                'blood-bank' => 'Manage blood donation and inventory',
                'test-settings' => 'Configure laboratory tests and settings'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Laboratory Management';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <!-- Notification Bell -->
        <div class="notif-bell-wrap" id="notif-bell-wrap">
          <button class="notif-bell-btn" onclick="notifToggleDropdown()" title="Notifications">
            <i class="fas fa-bell"></i>
            <span class="notif-count" id="notif-count" style="display:none;">0</span>
          </button>
          <div class="notif-dropdown" id="notif-dropdown">
            <div class="notif-dropdown-header">
              <h4><i class="fas fa-bell" style="color:var(--primary-color);margin-right:6px;"></i> Notifications</h4>
              <button onclick="notifMarkAllRead()" style="padding:4px 10px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:4px;font-size:11px;cursor:pointer;font-weight:500;">Mark all read</button>
            </div>
            <div class="notif-dropdown-body" id="notif-list">
              <div class="notif-empty"><i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:var(--gray-300);"></i>No notifications</div>
            </div>
          </div>
        </div>
        <!-- Sound Mute Toggle -->
        <button class="notif-mute-btn" id="notif-mute-btn" onclick="notifToggleMute()" title="Mute/Unmute notification sounds">
          <i class="fas fa-volume-up"></i>
        </button>
        <?php if ($action == 'list' && in_array($subModule, ['test-requests', 'sample-collection', 'results', 'pathology', 'microbiology', 'blood-bank'])): ?>
        <button onclick="window.location.href='lab.php?module=lab&sub=<?php echo $subModule; ?>&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Add New
        </button>
        <?php endif; ?>
        <?php if ($action != 'list'): ?>
        <button onclick="window.location.href='lab.php?module=lab&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Laboratory Sub-Module Navigation Tabs -->
<div style="display: flex; gap: 6px; margin-bottom: 22px; background: white; padding: 6px; border-radius: 10px; box-shadow: var(--shadow); overflow-x: auto; width: fit-content; max-width: 100%; flex-wrap: wrap;">
    <button onclick="location.href='lab.php?module=lab&sub=test-requests'" style="padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; <?php echo $subModule=='test-requests'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-list"></i> Test Requests</button>
    <button onclick="location.href='lab.php?module=lab&sub=sample-collection'" style="padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; <?php echo $subModule=='sample-collection'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-vial"></i> Sample Collection</button>
    <button onclick="location.href='lab.php?module=lab&sub=results'" style="padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; <?php echo $subModule=='results'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-poll"></i> Results Entry</button>
    <button onclick="location.href='lab.php?module=lab&sub=consumption'" style="padding: 8px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; <?php echo in_array($subModule,['consumption','item-consumption'])?'background:#059669;color:white;':'background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;';?>"><i class="fas fa-boxes-stacked"></i> Confirm Item Consumption</button>
    <button onclick="location.href='lab.php?module=lab&sub=pathology'" style="padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; <?php echo $subModule=='pathology'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-microscope"></i> Pathology</button>
    <button onclick="location.href='lab.php?module=lab&sub=microbiology'" style="padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; <?php echo $subModule=='microbiology'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-bacterium"></i> Microbiology</button>
    <button onclick="location.href='lab.php?module=lab&sub=blood-bank'" style="padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; <?php echo $subModule=='blood-bank'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-tint"></i> Blood Bank</button>
    <button onclick="location.href='lab.php?module=lab&sub=statistics'" style="padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; <?php echo $subModule=='statistics'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-chart-bar"></i> Statistics</button>
    <button onclick="location.href='lab.php?module=lab&sub=test-settings'" style="padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; white-space: nowrap; <?php echo $subModule=='test-settings'?'background:var(--primary-color);color:white;font-weight:600;':'background:transparent;color:var(--gray-600);';?>"><i class="fas fa-cogs"></i> Settings</button>
</div>

<script>
window.labBasePath = (typeof _apiBase !== 'undefined' ? _apiBase.replace(/\/api\.php.*$/, '') : '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>');
var labBasePath = window.labBasePath;
if(typeof esc !== 'function'){
  function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
}

// Universal Excel / CSV export helper for Laboratory
function labDownloadExcel(filename, sheetName, headers, rows) {
  if (!rows || !rows.length) { alert('No data to export in ' + (sheetName || 'sheet') + '.'); return; }
  if (typeof XLSX !== 'undefined') {
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
    ws['!cols'] = headers.map(h => ({ wch: Math.max(14, Math.min(42, String(h || '').length + 6)) }));
    XLSX.utils.book_append_sheet(wb, ws, sheetName || 'Data');
    XLSX.writeFile(wb, filename + '.xlsx');
  } else {
    const q = v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
    const csv = '﻿' + [headers, ...rows].map(r => r.map(q).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8;'}));
    a.download = filename + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
  }
}

// Escape for HTML text and attributes (pages redefine esc() without quote escaping)
function labEsc(s){return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}

// Saved lab result: structured results are a JSON array of {test_name, value, unit, normal_min, normal_max}
function labParseResult(result){
  if(!result || String(result).trim()[0] !== '[') return null;
  try { const r = JSON.parse(result); return Array.isArray(r) ? r : null; } catch(e){ return null; }
}

// Saved result → HTML: table with Low/High flags for structured results, plain text otherwise
function labResultHtml(result){
  const rows = labParseResult(result);
  if(!rows) return `<div style="font-size:12px;white-space:pre-wrap;font-family:monospace;color:var(--gray-800);">${labEsc(result||'')}</div>`;
  return `<table style="width:100%;border-collapse:collapse;font-size:12px;">
    <thead><tr style="border-bottom:1px solid var(--gray-200);">
      <th style="padding:4px 8px;text-align:left;">Parameter</th>
      <th style="padding:4px 8px;text-align:center;">Result</th>
      <th style="padding:4px 8px;text-align:center;">Unit</th>
      <th style="padding:4px 8px;text-align:center;">Normal Range</th>
      <th style="padding:4px 8px;text-align:center;">Status</th>
    </tr></thead><tbody>
    ${rows.map(r=>{
      if(r.param_type === 'note') return `<tr><td colspan="5" style="padding:6px 8px;font-size:12px;color:var(--gray-700);"><strong>Note:</strong> ${labEsc(r.value)}</td></tr>`;
      const val=parseFloat(r.value);
      const min=r.normal_min!=null&&r.normal_min!==''?parseFloat(r.normal_min):null;
      const max=r.normal_max!=null&&r.normal_max!==''?parseFloat(r.normal_max):null;
      let flag='';
      const choice = r.param_type === 'pos_neg' || (r.normal_value && r.param_type !== 'numeric');
      if(choice){
        flag = r.value ? (String(r.value).trim().toLowerCase() === String(r.normal_value||'Negative').trim().toLowerCase()
          ? '<span style="color:#059669;font-weight:600;">Normal</span>'
          : '<span style="color:#dc2626;font-weight:700;">Abnormal</span>') : '';
      } else if(!isNaN(val)&&min!=null&&max!=null){
        if(val<min) flag='<span style="color:#dc2626;font-weight:700;">↓ Low</span>';
        else if(val>max) flag='<span style="color:#dc2626;font-weight:700;">↑ High</span>';
        else flag='<span style="color:#059669;font-weight:600;">Normal</span>';
      }
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:4px 8px;font-weight:500;">${labEsc(r.test_name)}</td>
        <td style="padding:4px 8px;text-align:center;font-weight:700;">${labEsc(r.value||'—')}</td>
        <td style="padding:4px 8px;text-align:center;color:var(--gray-500);">${labEsc(r.unit||'')}</td>
        <td style="padding:4px 8px;text-align:center;color:var(--gray-500);">${choice?labEsc(r.normal_value||'Negative'):(min!=null&&max!=null?min+' - '+max:'—')}</td>
        <td style="padding:4px 8px;text-align:center;">${flag}</td>
      </tr>`;
    }).join('')}
  </tbody></table>`;
}

// Multi-select result chip: toggles the option in the hidden input (comma-separated)
function labToggleMultiOption(btn, orderId){
  const wrap = btn.closest('.lab-multiselect-wrap-'+orderId);
  const hidden = wrap && wrap.querySelector('.lab-param-input-'+orderId);
  if(!hidden) return;
  const opt = btn.dataset.opt;
  const cur = hidden.value ? hidden.value.split(',').map(x=>x.trim()).filter(Boolean) : [];
  const i = cur.indexOf(opt);
  if(i > -1) cur.splice(i, 1); else cur.push(opt);
  hidden.value = cur.join(', ');
  const on = i === -1;
  btn.style.background = on ? '#eff6ff' : 'white';
  btn.style.borderColor = on ? 'var(--primary-color)' : 'var(--gray-300)';
  btn.style.color = on ? 'var(--primary-color)' : 'var(--gray-700)';
  btn.textContent = (on ? '✓ ' : '+ ') + opt;
}

// Optional free-text note stored as a trailing {param_type:'note'} entry of the structured result ([] when empty)
function labNoteEntry(orderId){
  const el = document.getElementById('lab-note-'+orderId);
  const v = el ? el.value.trim() : '';
  return v ? [{test_name:'Note', value:v, param_type:'note', unit:'', normal_min:null, normal_max:null, normal_value:''}] : [];
}

// Structured result from an order's parameter inputs (null when the test has no parameters)
function labCollectParams(orderId){
  const inputs = document.querySelectorAll('.lab-param-input-'+orderId);
  if(!inputs.length) return null;
  return [...inputs].map(inp => ({
    test_name: inp.dataset.name,
    value: inp.value.trim(),
    param_type: inp.dataset.type || 'numeric',
    unit: inp.dataset.unit || '',
    normal_min: inp.dataset.min || null,
    normal_max: inp.dataset.max || null,
    normal_value: inp.dataset.normal || ''
  }));
}

// Load parameters for a specific test order and replace textarea with param fields (values from a saved draft are kept)
async function labLoadParamsForOrder(orderId, testName, savedResult){
  // Find the service_id by matching description to service_catalog
  try{
    const r=await fetch(labBasePath+'/api.php?endpoint=service-catalog&search='+encodeURIComponent(testName));
    const j=await r.json();
    if(!j.success||!j.data||!j.data.length) return; // No match, keep textarea

    // Find exact match
    const match = j.data.find(s=>(s.procedure_name||'').toLowerCase()===testName.toLowerCase()) || j.data[0];
    if(!match) return;

    // Load parameters for this service
    const pr=await fetch(labBasePath+'/api.php?endpoint=patients/billing&action=lab_params&service_id='+match.id);
    const pj=await pr.json();
    if(!pj.success||!pj.data||!pj.data.length) return; // No parameters, keep textarea

    const params=pj.data;
    const area=document.getElementById('lab-result-area-'+orderId);
    if(!area) return;
    const textarea=document.getElementById('lab-result-'+orderId);
    const saved={};
    const savedRows = labParseResult(savedResult ?? (textarea ? textarea.value : '')) || [];
    savedRows.forEach(x=>{ if(x.param_type !== 'note') saved[x.test_name]=x.value; });
    const savedNote = (savedRows.find(x=>x.param_type === 'note') || {}).value || '';

    // Replace textarea with parameter input fields
    area.innerHTML=`
      <label style="font-size:11px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:6px;">Test Parameters</label>
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead><tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
          <th style="padding:6px 8px;text-align:left;">Parameter</th>
          <th style="padding:6px 8px;text-align:center;">Result</th>
          <th style="padding:6px 8px;text-align:center;">Unit</th>
          <th style="padding:6px 8px;text-align:center;">Normal Range</th>
        </tr></thead><tbody>
        ${params.map(p=>{
          const prev = saved[p.test_name] ?? '';
          const base = `class="lab-param-input-${orderId}" data-name="${labEsc(p.test_name||'')}"`;
          const opts = (p.options||'').split(',').map(x=>x.trim()).filter(Boolean);
          let input, ref;
          if(p.param_type === 'pos_neg'){
            input = `<select ${base} data-type="pos_neg" data-unit="" data-min="" data-max="" data-normal="Negative" style="padding:4px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;background:white;width:100%;font-weight:600;"><option value="">Select result</option><option value="Negative" ${prev==='Negative'?'selected':''}>Negative</option><option value="Positive" ${prev==='Positive'?'selected':''}>Positive</option></select>`;
            ref = 'Negative';
          } else if(p.param_type === 'select'){
            input = `<select ${base} data-type="select" data-unit="" data-min="" data-max="" data-normal="${labEsc(p.normal_value||'')}" style="padding:4px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;background:white;width:100%;"><option value="">Select result</option>${opts.map(o=>`<option value="${labEsc(o)}" ${prev===o?'selected':''}>${labEsc(o)}</option>`).join('')}</select>`;
            ref = p.normal_value || '—';
          } else if(p.param_type === 'multi_select'){
            const sel = prev ? String(prev).split(',').map(x=>x.trim()) : [];
            input = `<div class="lab-multiselect-wrap-${orderId}"><input type="hidden" ${base} data-type="multi_select" data-unit="" data-min="" data-max="" data-normal="${labEsc(p.normal_value||'')}" value="${labEsc(prev)}"><div style="display:flex;flex-wrap:wrap;gap:4px;max-width:320px;">${opts.map(o=>{
              const on = sel.includes(o);
              return `<button type="button" data-opt="${labEsc(o)}" onclick="labToggleMultiOption(this, ${orderId})" style="padding:3px 8px;border-radius:12px;font-size:11px;cursor:pointer;border:1px solid ${on?'var(--primary-color)':'var(--gray-300)'};background:${on?'#eff6ff':'white'};color:${on?'var(--primary-color)':'var(--gray-700)'};">${on?'✓ ':'+ '}${labEsc(o)}</button>`;
            }).join('')}</div></div>`;
            ref = p.normal_value || 'Multi-select';
          } else if(p.param_type === 'text'){
            input = `<input type="text" ${base} data-type="text" data-unit="" data-min="" data-max="" data-normal="" value="${labEsc(prev)}" placeholder="Findings / observation..." style="width:100%;padding:4px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">`;
            ref = 'Observation';
          } else {
            input = `<input type="text" ${base} data-type="numeric" data-unit="${labEsc(p.unit||'')}" data-min="${p.normal_min!=null?p.normal_min:''}" data-max="${p.normal_max!=null?p.normal_max:''}" data-normal="" value="${labEsc(prev)}" style="width:80px;padding:4px 6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;text-align:center;" placeholder="—">`;
            ref = p.normal_min!=null&&p.normal_max!=null ? p.normal_min+' - '+p.normal_max : '—';
          }
          return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:6px 8px;font-weight:500;">${labEsc(p.test_name)}</td>
          <td style="padding:6px 8px;text-align:center;">${input}</td>
          <td style="padding:6px 8px;text-align:center;color:var(--gray-500);">${labEsc(p.unit||'')}</td>
          <td style="padding:6px 8px;text-align:center;color:var(--gray-500);">${labEsc(ref)}</td>
        </tr>`;
        }).join('')}
      </tbody></table>
      <label style="font-size:11px;font-weight:600;color:var(--gray-600);display:block;margin:10px 0 4px;">Additional Note (optional)</label>
      <textarea id="lab-note-${orderId}" rows="2" placeholder="Additional note..." style="width:100%;padding:6px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;resize:vertical;">${labEsc(savedNote)}</textarea>`;
  }catch(e){ /* Keep textarea as fallback */ }
}
</script>

<!-- ============================================
TEST REQUESTS
============================================ -->
<?php if ($subModule == 'test-requests'): ?>

<?php if ($action == 'add'): ?>
<!-- NEW TEST REQUEST FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-vial" style="color: var(--primary-color);"></i> New Test Request
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Request ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., LAB-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Patient <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Patient</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Test Type <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Test</option>
                    <option value="blood-count">Complete Blood Count</option>
                    <option value="urine-analysis">Urine Analysis</option>
                    <option value="liver-function">Liver Function Test</option>
                    <option value="kidney-function">Kidney Function Test</option>
                    <option value="thyroid">Thyroid Profile</option>
                    <option value="lipid-profile">Lipid Profile</option>
                    <option value="blood-culture">Blood Culture</option>
                    <option value="urine-culture">Urine Culture</option>
                    <option value="biopsy">Biopsy</option>
                    <option value="hiv">HIV Test</option>
                    <option value="covid">COVID-19 Test</option>
                    <option value="malaria">Malaria Test</option>
                    <option value="pregnancy">Pregnancy Test</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Requesting Doctor <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Doctor</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Department <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Department</option>
                    <option value="opd">General OPD</option>
                    <option value="emergency">Emergency</option>
                    <option value="icu">ICU</option>
                    <option value="pediatrics">Pediatrics</option>
                    <option value="cardiology">Cardiology</option>
                    <option value="neurology">Neurology</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Priority <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="stat">STAT (Immediate)</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Request Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="pending">Pending</option>
                    <option value="sample-collected">Sample Collected</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Clinical Information
                </label>
                <textarea rows="2" placeholder="Enter clinical notes and relevant information" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Submit Request
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- LAB WORKFLOW: Patient List → Click → Full Page Orders -->

<!-- Patient List View -->
<div id="lab-list-view" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
  <div style="padding:14px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);"><i class="fas fa-users" style="color:var(--primary-color);"></i> Patients with Lab Orders</h3>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <input type="date" id="lab-date-from" onchange="labLoadPatients()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="From date">
      <span style="font-size:12px;color:var(--gray-400);">to</span>
      <input type="date" id="lab-date-to" onchange="labLoadPatients()" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="To date">
      <select onchange="labSetDatePreset(this.value);this.value='';" style="padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="Date presets"><option value="">Quick range</option><option value="today">Today</option><option value="yesterday">Yesterday</option><option value="week">Last 7 days</option><option value="month">This month</option><option value="all">All dates</option></select>
      <input type="text" id="lab-patient-search" placeholder="Search patient..." oninput="labFilterPatients()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:200px;">
      <button onclick="labLoadPatients()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
      <button onclick="labExportPatientsList()" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;" title="Export to Excel"><i class="fas fa-file-excel"></i> Excel</button>
    </div>
  </div>
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:12px 16px;text-align:left;font-weight:600;">#</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;">Patient</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;">Patient No.</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;">Age / Gender</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;">Insurance</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;">Pending</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;">Completed</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;">Total</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;">Action</th>
      </tr></thead>
      <tbody id="lab-patient-tbody">
        <tr><td colspan="9" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i></td></tr>
      </tbody>
    </table>
  </div>
  <div id="lab-pagination" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-top:1px solid var(--gray-200);">
    <span id="lab-page-info" style="font-size:12px;color:var(--gray-500);"></span>
    <div id="lab-page-btns" style="display:flex;gap:4px;"></div>
  </div>
</div>

<!-- Full Page: Patient Orders View -->
<div id="lab-orders-view" style="display:none;">
  <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
    <button onclick="labBackToList()" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;"><i class="fas fa-arrow-left"></i> Back to Patient List</button>
    <button onclick="labExportSelectedPatient()" style="padding:8px 16px;background:#16a34a;color:white;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;margin-left:auto;margin-right:8px;"><i class="fas fa-file-excel"></i> Excel</button>
    <button onclick="labPrintOrders()" style="padding:8px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;"><i class="fas fa-print"></i> Print</button>
  </div>
  <!-- Patient Header -->
  <div style="background:linear-gradient(135deg,#2563eb,#0ea5e9);border-radius:10px;padding:18px 24px;color:white;margin-bottom:20px;display:flex;align-items:center;gap:16px;">
    <div style="width:46px;height:46px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;" id="lab-pt-avatar">?</div>
    <div style="flex:1;">
      <div style="font-size:17px;font-weight:700;" id="lab-pt-name">—</div>
      <div style="font-size:12px;opacity:0.9;" id="lab-pt-no">—</div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:11px;opacity:0.8;">Orders</div>
      <div style="font-size:22px;font-weight:700;" id="lab-pt-count">0</div>
    </div>
  </div>
  <!-- Orders -->
  <div id="lab-orders-panel" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;">
    <div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div>
  </div>
</div>

<script>
var labBasePath = window.labBasePath || '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]),"/"); ?>';
const labAPI = labBasePath+'/api.php?endpoint=consultation/consult&action=lab_orders';
let labPatients = [];
let labCurrentPage = 1;
const labPageSize = 15;

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

async function labLoadPatients(){
  try{
    const from = document.getElementById('lab-date-from').value;
    const to   = document.getElementById('lab-date-to').value;
    let url = labAPI+'&type=all';
    if(from) url += '&date_from='+encodeURIComponent(from);
    if(to)   url += '&date_to='+encodeURIComponent(to);
    const r = await fetch(url);
    const j = await r.json();
    if(j.success && j.data){
      const visits = {};
      j.data.forEach(o => {
        const key = o.visit_id;
        if(!visits[key]){
          visits[key] = { visit_id:o.visit_id, patient_id:o.patient_id, patient_name:o.patient_name, patient_no:o.patient_no, gender:o.gender, date_of_birth:o.date_of_birth, insurance_company:o.insurance_company, visit_status:o.visit_status||'open', visit_date:o.visit_date||'', orders:[], pending:0, completed:0 };
        }
        visits[key].orders.push(o);
        // Visit badge: the least-paid lab bill (never blocks the test)
        const rank={unpaid:4,partial:3,approved:2,covered:1,paid:1};
        if((rank[o.pay_status]||0) > (rank[visits[key].pay_status]||0)) visits[key].pay_status=o.pay_status;
        if(o.status==='ordered'||o.status==='in_progress') visits[key].pending++;
        else visits[key].completed++;
      });
      labPatients = Object.values(visits);
      labPatients.sort((a, b) => {
        if(a.pending > 0 && b.pending === 0) return -1;
        if(a.pending === 0 && b.pending > 0) return 1;
        return b.pending - a.pending;
      });
      // Apply search filter only (dates already filtered by API)
      labFilterPatients();
    }
  }catch(e){
    document.getElementById('lab-patient-tbody').innerHTML='<tr><td colspan="9" style="padding:30px;text-align:center;color:#dc2626;">Error loading</td></tr>';
  }
}

function labRenderPatients(list){
  const tbody=document.getElementById('lab-patient-tbody');
  const pageInfo=document.getElementById('lab-page-info');
  const pageBtns=document.getElementById('lab-page-btns');
  if(!list||!list.length){
    tbody.innerHTML='<tr><td colspan="9" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-flask" style="font-size:24px;display:block;margin-bottom:8px;"></i>No pending lab orders.</td></tr>';
    pageInfo.textContent='';
    pageBtns.innerHTML='';
    return;
  }
  // Pagination
  const totalPages = Math.ceil(list.length / labPageSize);
  if(labCurrentPage > totalPages) labCurrentPage = totalPages;
  if(labCurrentPage < 1) labCurrentPage = 1;
  const start = (labCurrentPage - 1) * labPageSize;
  const end = start + labPageSize;
  const pageItems = list.slice(start, end);

  tbody.innerHTML=pageItems.map((v,idx)=>{
    const hasPending = v.pending > 0;
    const rowBg = hasPending ? 'background:#fff7ed;' : '';
    const age = v.date_of_birth ? Math.floor((Date.now()-new Date(v.date_of_birth).getTime())/(365.25*24*60*60*1000))+'y' : '—';
    const gender = v.gender ? v.gender.charAt(0).toUpperCase()+v.gender.slice(1) : '—';
    return `
    <tr style="border-bottom:1px solid var(--gray-100);cursor:pointer;${rowBg}" onclick="labSelectVisit(${v.visit_id})" onmouseover="this.style.background='${hasPending?'#fed7aa':'#f8fafc'}'" onmouseout="this.style.background='${hasPending?'#fff7ed':''}'">
      <td style="padding:12px 16px;font-size:12px;color:var(--gray-500);font-weight:500;">${start+idx+1}</td>
      <td style="padding:12px 16px;font-weight:600;color:var(--gray-900);">${esc(v.patient_name)}<div style="margin-top:3px;font-weight:400;">${payBadge(v.pay_status, 0)}</div></td>
      <td style="padding:12px 16px;font-family:monospace;font-size:12px;color:var(--primary-color);">${esc(v.patient_no||'')}</td>
      <td style="padding:12px 16px;font-size:12px;">${age} / ${gender}</td>
      <td style="padding:12px 16px;font-size:12px;">${v.insurance_company?'<span style="color:var(--success-color);font-weight:600;"><i class="fas fa-shield-alt"></i> '+esc(v.insurance_company)+'</span>':'<span style="color:var(--gray-400);">None</span>'}</td>
      <td style="padding:12px 16px;text-align:center;"><span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">${v.pending}</span></td>
      <td style="padding:12px 16px;text-align:center;"><span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">${v.completed}</span></td>
      <td style="padding:12px 16px;text-align:center;">${v.orders.length}</td>
      <td style="padding:12px 16px;text-align:center;"><button style="padding:5px 12px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:500;"><i class="fas fa-flask"></i> Open</button></td>
    </tr>`;
  }).join('');

  // Page info
  pageInfo.textContent = `Showing ${start+1}–${Math.min(end, list.length)} of ${list.length} patients`;

  // Page buttons
  if(totalPages <= 1){
    pageBtns.innerHTML='';
  } else {
    let btns = '';
    btns += `<button onclick="labGoPage(${labCurrentPage-1})" ${labCurrentPage===1?'disabled':''} style="padding:6px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;font-size:12px;${labCurrentPage===1?'opacity:0.4;cursor:default;':''}"><i class="fas fa-chevron-left"></i></button>`;
    // Show max 7 page buttons
    let startPage = Math.max(1, labCurrentPage - 3);
    let endPage = Math.min(totalPages, startPage + 6);
    if(endPage - startPage < 6) startPage = Math.max(1, endPage - 6);
    for(let i=startPage; i<=endPage; i++){
      const isActive = i === labCurrentPage;
      btns += `<button onclick="labGoPage(${i})" style="padding:6px 10px;border:1px solid ${isActive?'var(--primary-color)':'var(--gray-200)'};background:${isActive?'var(--primary-color)':'white'};color:${isActive?'white':'var(--gray-700)'};border-radius:4px;cursor:pointer;font-size:12px;font-weight:${isActive?'600':'400'};">${i}</button>`;
    }
    btns += `<button onclick="labGoPage(${labCurrentPage+1})" ${labCurrentPage===totalPages?'disabled':''} style="padding:6px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;font-size:12px;${labCurrentPage===totalPages?'opacity:0.4;cursor:default;':''}"><i class="fas fa-chevron-right"></i></button>`;
    pageBtns.innerHTML = btns;
  }
}

function labGoPage(page){
  const list = labGetCurrentFilteredList();
  const totalPages = Math.ceil(list.length / labPageSize);
  if(page < 1 || page > totalPages) return;
  labCurrentPage = page;
  labRenderPatients(list);
}

function labGetCurrentFilteredList(){
  const q=document.getElementById('lab-patient-search').value.toLowerCase();
  let filtered = labGetDateFiltered();
  if(q){
    filtered = filtered.filter(v=>(v.patient_name||'').toLowerCase().includes(q)||(v.patient_no||'').toLowerCase().includes(q));
  }
  return filtered;
}

function labFilterPatients(){
  labCurrentPage = 1;
  const q=document.getElementById('lab-patient-search').value.toLowerCase();
  if(!q){labRenderPatients(labPatients);return;}
  labRenderPatients(labPatients.filter(v=>(v.patient_name||'').toLowerCase().includes(q)||(v.patient_no||'').toLowerCase().includes(q)));
}

function labGetCurrentFilteredList(){
  const q=document.getElementById('lab-patient-search').value.toLowerCase();
  if(!q) return labPatients;
  return labPatients.filter(v=>(v.patient_name||'').toLowerCase().includes(q)||(v.patient_no||'').toLowerCase().includes(q));
}

function labFilterByDate(){
  // Now handled server-side — just reload
  labLoadPatients();
}

// Print functionality using hospital paper settings
let labPaperSettings = null;
async function labLoadPaperSettings(){
  try{
    const r = await fetch(labBasePath+'/api.php?endpoint=settings/hospital');
    const j = await r.json();
    if(j.success) labPaperSettings = j.data || {};
  }catch(e){ labPaperSettings = {}; }
}
labLoadPaperSettings();

function labPrintOrders(){
  const ptName = document.getElementById('lab-pt-name').textContent;
  const ptNo = document.getElementById('lab-pt-no').textContent;
  const ordersPanel = document.getElementById('lab-orders-panel');
  const s = labPaperSettings || {};

  // Paper settings
  const headerLeft = s.header_left || s.hospital_name || 'Hospital';
  const headerRight = s.header_right || ('Date: ' + new Date().toISOString().split('T')[0]);
  const headerLineColor = s.header_line_color || '#059669';
  const headerLineSize = s.header_line_size || 2;
  const headerTextColor = s.header_text_color || '#1f2937';
  const headerFontSize = s.header_font_size || 12;
  const footerLeft = s.footer_left || '';
  const footerRight = s.footer_right || '';
  const footerLineColor = s.footer_line_color || '#059669';
  const footerLineSize = s.footer_line_size || 2;
  const footerTextColor = s.footer_text_color || '#1f2937';
  const footerFontSize = s.footer_font_size || 10;
  const wmEnabled = s.watermark_enabled === '1' || s.watermark_enabled === 1;
  const wmText = s.watermark_text || '';
  const wmColor = s.watermark_color || '#d1d5db';
  const wmSize = s.watermark_size || 60;
  const wmOpacity = (parseInt(s.watermark_opacity)||15) / 100;
  const wmRotation = s.watermark_rotation || -30;
  const docLogo = s.doc_logo || s.logo || '';

  const printWin = window.open('', '_blank', 'width=900,height=700');
  printWin.document.write(`<!DOCTYPE html><html><head><title>Lab Orders - ${ptName}</title>
    <style>
      * { margin:0; padding:0; box-sizing:border-box; }
      body { font-family: Arial, sans-serif; padding:20px; position:relative; }
      .watermark { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) rotate(${wmRotation}deg); font-size:${wmSize}px; color:${wmColor}; opacity:${wmOpacity}; font-weight:700; white-space:nowrap; pointer-events:none; z-index:0; }
      .header { display:flex; justify-content:flex-end; margin:4px 0 20px; }
      .header-left { display:flex; align-items:center; gap:10px; font-size:${headerFontSize}px; font-weight:600; color:${headerTextColor}; }
      .header-right { font-size:${headerFontSize}px; color:${headerTextColor}; }
      .header-logo { height:40px; width:auto; }
      .footer { display:flex; justify-content:space-between; align-items:center; padding-top:8px; border-top:${footerLineSize}px solid ${footerLineColor}; margin-top:30px; }
      .footer-left { font-size:${footerFontSize}px; color:${footerTextColor}; }
      .footer-right { font-size:${footerFontSize}px; color:${footerTextColor}; }
      .patient-info { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; margin-bottom:20px; }
      .patient-info h2 { font-size:16px; color:#1e293b; margin-bottom:4px; }
      .patient-info p { font-size:12px; color:#64748b; }
      .order-card { border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:12px; page-break-inside:avoid; }
      .order-card.pending { border-left:4px solid #d97706; }
      .order-card.completed { border-left:4px solid #16a34a; }
      .order-card.in-progress { border-left:4px solid #2563eb; }
      .order-title { font-size:14px; font-weight:600; color:#1e293b; }
      .order-status { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; }
      .status-pending { background:#fef3c7; color:#92400e; }
      .status-completed { background:#d1fae5; color:#065f46; }
      .status-in-progress { background:#dbeafe; color:#1e40af; }
      .order-meta { font-size:11px; color:#6b7280; margin-top:4px; }
      .result-box { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px; margin-top:8px; }
      .result-box h4 { font-size:11px; font-weight:600; color:#065f46; margin-bottom:4px; }
      .result-table { width:100%; border-collapse:collapse; font-size:12px; }
      .result-table th { padding:4px 8px; text-align:left; border-bottom:1px solid #e2e8f0; font-weight:600; }
      .result-table td { padding:4px 8px; border-bottom:1px solid #f1f5f9; }
      .print-title { text-align:center; font-size:14px; font-weight:600; color:#374151; margin-bottom:16px; }
      @media print {
        body { padding:10px; }
        .watermark { position:fixed; }
        button { display:none !important; }
      }
    </style>
  </head><body>
    ${wmEnabled && wmText ? '<div class="watermark">'+wmText+'</div>' : ''}
    <img src="${new URL('assets/letterhead.png', location.href).href}" style="display:block;width:100%;height:auto;">
    <div class="header">
      <div class="header-right">${headerRight.replace('{date}', new Date().toISOString().split('T')[0]).replace('{ref}', 'LAB-'+Date.now().toString(36).toUpperCase())}</div>
    </div>
    <div class="print-title">Laboratory Test Orders</div>
    <div class="patient-info">
      <h2>${ptName}</h2>
      <p>Patient No: ${ptNo} &nbsp; | &nbsp; Print Date: ${new Date().toLocaleString()}</p>
    </div>
    <div id="print-orders"></div>
    <div class="footer">
      <div class="footer-left">${footerLeft}</div>
      <div class="footer-right">${footerRight}</div>
    </div>
  </body></html>`);

  // Now render orders content
  const currentVisitId = labPatients.find(v => v.patient_name === ptName || v.patient_no === ptNo);
  if(currentVisitId){
    let ordersHtml = '';
    currentVisitId.orders.forEach(o => {
      const statusClass = o.status === 'completed' ? 'completed' : o.status === 'in_progress' ? 'in-progress' : 'pending';
      const statusLabel = o.status === 'completed' ? 'Completed' : o.status === 'in_progress' ? 'In Progress' : 'Pending';
      const statusBadgeClass = o.status === 'completed' ? 'status-completed' : o.status === 'in_progress' ? 'status-in-progress' : 'status-pending';

      ordersHtml += '<div class="order-card '+statusClass+'">';
      ordersHtml += '<div style="display:flex;justify-content:space-between;align-items:center;">';
      ordersHtml += '<span class="order-title">'+(o.description||'—')+'</span>';
      ordersHtml += '<span class="order-status '+statusBadgeClass+'">'+statusLabel+'</span>';
      ordersHtml += '</div>';
      ordersHtml += '<div class="order-meta">Ordered: '+(o.ordered_at ? new Date(o.ordered_at).toLocaleString() : '—')+'</div>';

      if(o.status === 'completed' && o.result){
        let structured = null;
        try { structured = JSON.parse(o.result); } catch(e){}
        if(structured && Array.isArray(structured)){
          ordersHtml += '<div class="result-box"><h4>RESULTS:</h4>';
          ordersHtml += '<table class="result-table"><thead><tr><th>Parameter</th><th>Result</th><th>Unit</th><th>Normal Range</th><th>Status</th></tr></thead><tbody>';
          structured.forEach(r => {
            const val=parseFloat(r.value);
            const min=r.normal_min!=null?parseFloat(r.normal_min):null;
            const max=r.normal_max!=null?parseFloat(r.normal_max):null;
            let flag='';
            if(!isNaN(val)&&min!=null&&max!=null){
              if(val<min) flag='↓ Low';
              else if(val>max) flag='↑ High';
              else flag='Normal';
            }
            ordersHtml += '<tr><td>'+r.test_name+'</td><td><strong>'+(r.value||'—')+'</strong></td><td>'+(r.unit||'')+'</td><td>'+(min!=null&&max!=null?min+' - '+max:'—')+'</td><td>'+flag+'</td></tr>';
          });
          ordersHtml += '</tbody></table>';
          ordersHtml += '<div style="font-size:10px;color:#6b7280;margin-top:6px;">Completed: '+(o.completed_at||'—')+'</div></div>';
        } else {
          ordersHtml += '<div class="result-box"><h4>RESULT:</h4><div style="font-size:12px;white-space:pre-wrap;font-family:monospace;">'+(o.result||'')+'</div>';
          ordersHtml += '<div style="font-size:10px;color:#6b7280;margin-top:6px;">Completed: '+(o.completed_at||'—')+'</div></div>';
        }
      }
      ordersHtml += '</div>';
    });
    printWin.document.getElementById('print-orders').innerHTML = ordersHtml;
  }
  printWin.document.close();
  setTimeout(function(){ printWin.print(); }, 500);
}

let labCurrentSelectedVisitId = null;

function labSetDatePreset(preset){
  const fmt = d => d.toISOString().split('T')[0];
  const from = new Date(), to = new Date();
  if(preset === 'all'){
    document.getElementById('lab-date-from').value = '';
    document.getElementById('lab-date-to').value = '';
  } else {
    if(preset === 'yesterday'){ from.setDate(from.getDate()-1); to.setDate(to.getDate()-1); }
    else if(preset === 'week'){ from.setDate(from.getDate()-6); }
    else if(preset === 'month'){ from.setDate(1); }
    else if(preset !== 'today') return;
    document.getElementById('lab-date-from').value = fmt(from);
    document.getElementById('lab-date-to').value = fmt(to);
  }
  labCurrentPage = 1;
  labLoadPatients();
}

function labExportPatientsList(){
  const list = labGetCurrentFilteredList();
  if(!list || !list.length){ alert('No patients to export with the current filters.'); return; }
  const headers = ['#','Patient Name','Patient No','Visit No','Age','Gender','Insurance','Pending Tests','Completed Tests','Total Tests','Latest Order Date','Tests Ordered'];
  const rows = list.map((v, i) => [
    i + 1, v.patient_name || '', v.patient_no || '', v.visit_no || '',
    v.date_of_birth ? Math.floor((Date.now()-new Date(v.date_of_birth).getTime())/(365.25*24*60*60*1000))+'y' : '—',
    v.gender ? v.gender.charAt(0).toUpperCase()+v.gender.slice(1) : '—',
    v.insurance_company || 'Self-Pay', v.pending || 0, v.completed || 0, (v.orders || []).length,
    v.latest_order_date || v.visit_date || '',
    (v.orders||[]).map(o => o.description).filter(Boolean).join(', ')
  ]);
  labDownloadExcel('Lab_Test_Requests_' + new Date().toISOString().substring(0, 10), 'Test Requests', headers, rows);
}

function labExportSelectedPatient(){
  const visit = labPatients.find(v => v.visit_id === labCurrentSelectedVisitId);
  if(!visit){ alert('Please select a patient to export.'); return; }
  const orders = (visit.orders || []).filter(o => !o.order_type || o.order_type === 'lab');
  if(!orders.length){ alert('No lab orders found for this patient.'); return; }
  const headers = ['#','Patient Name','Patient No','Visit No','Test / Examination','Order Type','Status','Ordered At','Ordered By','Parameter / Exam','Result Value','Unit','Reference Range','Flag','Completed At','Completed By'];
  const rows = [];
  orders.forEach(o => {
    const done = o.status === 'completed';
    let params = null;
    if(done && o.result){ try { params = JSON.parse(o.result); } catch(e){} }
    const head = () => [rows.length + 1, visit.patient_name || '', visit.patient_no || '', visit.visit_no || '', o.description || '', o.order_type || 'lab', o.status || '', o.ordered_at || '', o.ordered_by_name || ''];
    const tail = [o.completed_at || '', o.completed_by_name || ''];
    if(Array.isArray(params) && params.length){
      params.forEach(p => {
        let flag = 'Normal', ref = '—';
        const valStr = String(p.value || '').trim();
        if(p.param_type === 'pos_neg' || p.normal_value){
          ref = p.normal_value || 'Negative';
          flag = valStr ? (valStr.toLowerCase() === ref.trim().toLowerCase() ? 'Normal' : 'Abnormal') : '—';
        } else if(p.param_type === 'text'){
          flag = '—';
        } else {
          const val = parseFloat(p.value);
          const min = p.normal_min != null && p.normal_min !== '' ? parseFloat(p.normal_min) : null;
          const max = p.normal_max != null && p.normal_max !== '' ? parseFloat(p.normal_max) : null;
          if(min != null && max != null) ref = min + ' - ' + max;
          else if(min != null) ref = '≥ ' + min;
          else if(max != null) ref = '≤ ' + max;
          if(!isNaN(val)){
            if(min != null && val < min) flag = 'Low';
            else if(max != null && val > max) flag = 'High';
          }
        }
        rows.push([...head(), p.test_name || '', p.value || '', p.unit || '', ref, flag, ...tail]);
      });
    } else {
      rows.push([...head(), 'Result', o.result || (done ? 'Completed' : 'Pending'), '', '', done ? 'Completed' : 'Pending', ...tail]);
    }
  });
  const safeName = (visit.patient_name || 'Patient').replace(/[^a-zA-Z0-9_-]/g, '_');
  labDownloadExcel(`Lab_Orders_${safeName}_${visit.patient_no || ''}_${new Date().toISOString().substring(0, 10)}`, 'Lab Orders', headers, rows);
}

function labBackToList(){
  document.getElementById('lab-orders-view').style.display='none';
  document.getElementById('lab-list-view').style.display='block';
}

function labSelectVisit(visitId){
  const visit = labPatients.find(v => v.visit_id === visitId);
  if(!visit) return;

  labCurrentSelectedVisitId = visitId;
  const visitCompleted = visit.visit_status === 'completed';

  // Show full page
  document.getElementById('lab-list-view').style.display='none';
  document.getElementById('lab-orders-view').style.display='block';
  document.getElementById('lab-pt-avatar').textContent=(visit.patient_name||'?').charAt(0).toUpperCase();
  document.getElementById('lab-pt-name').textContent=visit.patient_name||'—';
  document.getElementById('lab-pt-no').textContent=visit.patient_no||'—';
  document.getElementById('lab-pt-count').textContent=visit.orders.length;

  const panel = document.getElementById('lab-orders-panel');
  // Show orders
  let html = '';

  visit.orders.forEach(o => {
    const isCompleted = o.status === 'completed';
    const isInProgress = o.status === 'in_progress';
    const statusBadge = isCompleted
      ? '<span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#d1fae5;color:#065f46;">Completed</span>'
      : isInProgress
        ? '<span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#dbeafe;color:#1e40af;">In Progress</span>'
        : '<span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;">Pending</span>';

    html += `<div style="border:1px solid var(--gray-200);border-radius:10px;padding:16px;margin-bottom:12px;${isCompleted?'border-left:4px solid #16a34a;':'border-left:4px solid #d97706;'}">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <div style="font-size:14px;font-weight:600;color:var(--gray-900);">${isCompleted?'':`<input type="checkbox" class="lab-sel" data-id="${o.id}" onchange="labSelCount()" style="margin-right:8px;">`}${esc(o.description)} ${payBadge(o.pay_status, o.pay_balance)}</div>
        ${statusBadge}
      </div>
      <div style="font-size:11px;color:var(--gray-500);margin-bottom:10px;display:flex;flex-wrap:wrap;gap:12px;">
        <span><i class="fas fa-user-md" style="color:var(--primary-color);"></i> Ordered by: <strong>${esc(o.ordered_by_name||'Doctor')}</strong> (${o.ordered_at ? new Date(o.ordered_at).toLocaleString() : '—'})</span>
        ${isCompleted ? `<span><i class="fas fa-microscope" style="color:#16a34a;"></i> Tested by: <strong>${esc(o.completed_by_name||o.performed_by_name||'Lab Technician')}</strong></span>` : ''}
      </div>`;

    if(isCompleted && o.result){
      html += `<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px;margin-bottom:8px;">
          <div style="font-size:11px;font-weight:600;color:#065f46;margin-bottom:6px;">RESULTS:</div>
          ${labResultHtml(o.result)}
          <div style="display:flex;justify-content:space-between;align-items:center;font-size:10px;color:var(--gray-500);margin-top:6px;">
            <span>Completed: ${o.completed_at||'—'}</span>
            <button onclick="labShowAuditModal(${o.id},'${esc(o.description).replace(/'/g,"\\'")}')" style="padding:2px 8px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:4px;font-size:10px;cursor:pointer;color:#4b5563;"><i class="fas fa-history"></i> Audit Logs</button>
          </div>
        </div>`;

      // Edit button — always available for completed orders
      {
        html += `<div id="lab-edit-area-${o.id}" style="display:none;margin-top:8px;">
          <div id="lab-result-area-${o.id}" style="margin-top:8px;">
            <label style="font-size:11px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Edit Result</label>
            <textarea id="lab-result-${o.id}" rows="3" placeholder="Edit result for ${esc(o.description)}..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;font-family:monospace;resize:vertical;">${esc(o.result||'')}</textarea>
          </div>
          <div style="display:flex;gap:8px;margin-top:8px;">
            <button onclick="labSaveResult(${o.id})" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
              <i class="fas fa-check"></i> Save Changes
            </button>
            <button onclick="document.getElementById('lab-edit-area-${o.id}').style.display='none'" style="padding:8px 14px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;">
              Cancel
            </button>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap;">
          <button onclick="labShowEditArea(${o.id},'${esc(o.description).replace(/'/g,"\\'")}')" style="padding:6px 12px;background:#f59e0b;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
            <i class="fas fa-edit"></i> Edit Result
          </button>
          <a href="lab.php?module=lab&sub=consumption&search=${encodeURIComponent(o.order_number||o.id)}" style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;background:#ede9fe;color:#6d28d9;border:1px solid #c4b5fd;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
            <i class="fas fa-boxes-stacked"></i> Consumables & Stock
          </a>
        </div>`;
      }
    }

    if(!isCompleted){
      html += `<div style="display:flex;gap:8px;margin-bottom:8px;">
        <div style="flex:1;">
          <label style="font-size:11px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Sample Collected</label>
          <select id="lab-sample-${o.id}" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
            <option value="yes" ${isInProgress?'selected':''}>Yes - Sample Received</option>
            <option value="no" ${!isInProgress?'selected':''}>No - Awaiting Sample</option>
          </select>
        </div>
      </div>
      <div id="lab-result-area-${o.id}" style="margin-top:8px;">
        <label style="font-size:11px;font-weight:600;color:var(--gray-600);display:block;margin-bottom:4px;">Test Result</label>
        <textarea id="lab-result-${o.id}" rows="3" placeholder="Enter result for ${esc(o.description)}..." style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;font-family:monospace;resize:vertical;">${esc(o.result||'')}</textarea>
      </div>
      <div style="display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap;">
        <button onclick="labSaveResult(${o.id})" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
          <i class="fas fa-check"></i> Save Result
        </button>
        <a href="lab.php?module=lab&sub=consumption&search=${encodeURIComponent(o.order_number||o.id)}" style="display:inline-flex;align-items:center;gap:5px;padding:8px 14px;background:#ede9fe;color:#6d28d9;border:1px solid #c4b5fd;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">
          <i class="fas fa-boxes-stacked"></i> Deduct Consumables
        </a>
      </div>`;
    }

    html += `</div>`;
  });

  if(visit.orders.some(o=>o.status!=='completed')){
    html = `<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
      <label style="font-size:12px;font-weight:600;cursor:pointer;"><input type="checkbox" id="lab-sel-all" onchange="labSelAll(this.checked)"> Select all</label>
      <button id="lab-submit-all" onclick="labSubmitAll()" disabled style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;opacity:.5;">Submit All (0)</button>
    </div>` + html;
  }
  panel.innerHTML = html;

  // After rendering, load parameters for incomplete orders
  visit.orders.filter(o=>o.status!=='completed').forEach(o=>{
    setTimeout(function(){ labLoadParamsForOrder(o.id, o.description); }, 150);
  });
}

async function labShowEditArea(orderId, description){
  const area = document.getElementById('lab-edit-area-'+orderId);
  if(!area) return;
  area.style.display = 'block';
  // Load parameters for this order (replaces textarea with structured inputs)
  await labLoadParamsForOrder(orderId, description);
}

function labSelAll(on){ document.querySelectorAll('.lab-sel').forEach(c=>c.checked=on); labSelCount(); }
function labSelCount(){
  const n = document.querySelectorAll('.lab-sel:checked').length, btn = document.getElementById('lab-submit-all');
  if(!btn) return;
  btn.textContent = 'Submit All ('+n+')'; btn.disabled = !n; btn.style.opacity = n ? 1 : .5;
}
// Same result building as labSaveResult; '' = nothing filled in (the server then reports that order as failed)
function labCollectResult(orderId){
  const inputs = document.querySelectorAll('.lab-param-input-'+orderId);
  if(inputs.length){
    const p = [...inputs].map(inp=>({test_name:inp.dataset.name, value:inp.value.trim(), param_type:inp.dataset.type||'numeric', unit:inp.dataset.unit||'', normal_min:inp.dataset.min||null, normal_max:inp.dataset.max||null, normal_value:inp.dataset.normal||''}));
    return p.some(x=>x.value) ? JSON.stringify(p.concat(labNoteEntry(orderId))) : '';
  }
  const el = document.getElementById('lab-result-'+orderId);
  return el ? el.value.trim() : '';
}
async function labSubmitAll(){
  const ids = [...document.querySelectorAll('.lab-sel:checked')].map(c=>parseInt(c.dataset.id));
  if(!ids.length) return;
  if(!confirm('Submit results for '+ids.length+' test(s)?')) return;
  try{
    const r = await fetch(labBasePath+'/api.php?endpoint=lab/lab&action=submit_all', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({orders: ids.map(id=>({order_id:id, result:labCollectResult(id)}))})});
    const j = await r.json();
    if(!j.success) return alert('Error: '+j.message);
    const failed = (j.data||[]).filter(x=>!x.success);
    const names = id => { const o = labPatients.flatMap(v=>v.orders).find(o=>o.id===id); return o ? o.description : '#'+id; };
    alert(j.message + (failed.length ? '\n\nNot submitted (left for correction):\n' + failed.map(x=>'- '+names(x.order_id)+': '+x.message).join('\n') : ''));
    const visitId = labCurrentSelectedVisitId;
    await labLoadPatients();
    if(visitId) labSelectVisit(visitId);
  }catch(e){ alert('Failed: '+e.message); }
}

async function labSaveResult(orderId){
  const resultEl = document.getElementById('lab-result-'+orderId);
  const sampleEl = document.getElementById('lab-sample-'+orderId);
  
  // Check if we have parameter inputs
  const paramInputs = document.querySelectorAll('.lab-param-input-'+orderId);
  let result = '';
  
  if(paramInputs.length > 0){
    // Structured result from parameters
    const paramResults = [];
    paramInputs.forEach(inp => {
      paramResults.push({
        test_name: inp.dataset.name,
        value: inp.value.trim(),
        param_type: inp.dataset.type || 'numeric',
        unit: inp.dataset.unit || '',
        normal_min: inp.dataset.min || null,
        normal_max: inp.dataset.max || null,
        normal_value: inp.dataset.normal || ''
      });
    });
    // Check at least one has a value
    if(!paramResults.some(p=>p.value)){
      return alert('Please enter at least one result value.');
    }
    result = JSON.stringify(paramResults.concat(labNoteEntry(orderId)));
  } else {
    result = resultEl ? resultEl.value.trim() : '';
  }
  
  const sampleCollected = sampleEl ? sampleEl.value : 'yes';
  
  if(!result && sampleCollected === 'yes') return alert('Please enter the test result.');
  
  const status = result ? 'completed' : 'in_progress';
  
  try{
    const r = await fetch(labBasePath+'/api.php?endpoint=consultation/consult&action=lab_result', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({order_id: orderId, result: result || 'Sample collected - awaiting processing', status: status})
    });
    const j = await r.json();
    if(j.success){
      alert('✓ ' + (status === 'completed' ? 'Result saved!' : 'Sample status updated!'));
      await labLoadPatients();
      const visitId = labPatients.find(v => v.orders.some(o => o.id === orderId))?.visit_id;
      if(visitId) labSelectVisit(visitId);
    } else {
      alert('Error: '+j.message);
    }
  } catch(e){ alert('Failed: '+e.message); }
}

// Initialize date filters to today
(function(){
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('lab-date-from').value = today;
  document.getElementById('lab-date-to').value = today;
})();

labLoadPatients();
setInterval(labLoadPatients, 30000);
</script>
<?php endif; ?>

<!-- ============================================
SAMPLE COLLECTION
============================================ -->
<?php elseif ($subModule == 'sample-collection'): ?>

<!-- Samples awaiting collection (status = ordered means sample not yet collected) -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Sample Collection</h3>
            <p style="font-size: 13px; color: var(--gray-500);">Tests awaiting sample collection from patients</p>
        </div>
        <button onclick="scLoad()" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <button id="sc-collect-sel" disabled onclick="scCollectSelected()" style="padding: 10px 20px; background: #16a34a; opacity:.5; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;"><i class="fas fa-syringe"></i> Collect Selected (0)</button>
        <button onclick="scExportList()" style="padding: 10px 20px; background: #16a34a; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;"><i class="fas fa-file-excel"></i> Excel</button>
    </div>

    <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
            <th style="padding:10px 12px;width:32px;"><input type="checkbox" id="sc-all" onchange="scToggleAll(this.checked)" title="Select all"></th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;">Test</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;">Ordered</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;">Status</th>
            <th style="padding:10px 12px;text-align:center;font-weight:600;">Action</th>
          </tr>
        </thead>
        <tbody id="sc-tbody">
          <tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
        </tbody>
      </table>
    </div>
</div>

<script>
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

let scData = [];
function scExportList(){
  const headers = ['#','Patient Name','Patient No','Test','Ordered By','Ordered At','Status'];
  const rows = scData.map((o, i) => [i + 1, o.patient_name || '', o.patient_no || '', o.description || '', o.ordered_by_name || '', o.ordered_at || '', 'Awaiting Sample']);
  labDownloadExcel('Lab_Sample_Collection_' + new Date().toISOString().substring(0, 10), 'Sample Collection', headers, rows);
}

const scSel = new Set();
async function scLoad(){
  try{
    const r = await fetch(labBasePath+'/api.php?endpoint=consultation/consult&action=lab_orders&status=ordered&type=all');
    const j = await r.json();
    scData = (j.success && j.data) || [];
    scRender();
  } catch(e){
    document.getElementById('sc-tbody').innerHTML = '<tr><td colspan="5" style="padding:40px;text-align:center;color:var(--danger-color);">Error: '+e.message+'</td></tr>';
  }
}
function scKey(o){ return o.patient_no || o.patient_name || ''; }
function scRender(){
  const tbody = document.getElementById('sc-tbody');
  const ids = new Set(scData.map(o => +o.id));
  [...scSel].forEach(id => { if(!ids.has(id)) scSel.delete(id); });
  if(!scData.length){
    tbody.innerHTML = '<tr><td colspan="5" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:var(--success-color);"></i>All samples collected! No pending collections.</td></tr>';
    scBar(); return;
  }
  const groups = {};
  scData.forEach(o => (groups[scKey(o)] = groups[scKey(o)] || []).push(o));
  tbody.innerHTML = Object.keys(groups).map(k => {
    const list = groups[k], p = list[0], gids = list.map(o => +o.id);
    const allSel = gids.every(id => scSel.has(id));
    return `<tr style="background:var(--gray-50);border-top:2px solid var(--gray-200);">
        <td style="padding:10px 12px;width:32px;"><input type="checkbox" ${allSel?'checked':''} onchange="scTogglePatient([${gids}], this.checked)" title="Select all tests for this patient"></td>
        <td colspan="3" style="padding:10px 12px;">
          <span style="font-weight:700;">${esc(p.patient_name||'—')}</span>
          <span style="font-size:11px;color:var(--gray-500);margin-left:6px;">${esc(p.patient_no||'')}</span>
          <span style="font-size:10px;color:var(--gray-500);margin-left:10px;"><i class="fas fa-user-md" style="color:var(--primary-color);"></i> ${esc(p.ordered_by_name||'Doctor')}</span>
        </td>
        <td style="padding:10px 12px;text-align:center;"><button onclick="scCollectIds([${gids}])" style="padding:5px 12px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;"><i class="fas fa-syringe"></i> Collect all (${list.length})</button></td>
      </tr>` + list.map(o => `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:10px 12px;"><input type="checkbox" ${scSel.has(+o.id)?'checked':''} onchange="scToggle(${o.id}, this.checked)"></td>
        <td style="padding:10px 12px;font-weight:500;">${esc(o.description)}<div style="margin-top:3px;font-weight:400;">${payBadge(o.pay_status, o.pay_balance)}</div></td>
        <td style="padding:10px 12px;font-size:12px;">${o.ordered_at ? new Date(o.ordered_at).toLocaleString() : '—'}</td>
        <td style="padding:10px 12px;"><span style="padding:2px 8px;border-radius:12px;font-size:11px;background:#fef3c7;color:#92400e;">Awaiting Sample</span></td>
        <td style="padding:10px 12px;text-align:center;">
          <button onclick="scCollectIds([${o.id}])" style="padding:6px 14px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;"><i class="fas fa-syringe"></i> Collect</button>
        </td>
      </tr>`).join('');
  }).join('');
  scBar();
}
function scBar(){
  const all = document.getElementById('sc-all'), btn = document.getElementById('sc-collect-sel');
  if(all) all.checked = scData.length > 0 && scSel.size === scData.length;
  if(btn){ btn.disabled = !scSel.size; btn.style.opacity = scSel.size ? 1 : .5; btn.innerHTML = '<i class="fas fa-syringe"></i> Collect Selected (' + scSel.size + ')'; }
}
function scToggle(id, on){ on ? scSel.add(+id) : scSel.delete(+id); scRender(); }
function scTogglePatient(ids, on){ ids.forEach(id => on ? scSel.add(id) : scSel.delete(id)); scRender(); }
function scToggleAll(on){ scData.forEach(o => on ? scSel.add(+o.id) : scSel.delete(+o.id)); scRender(); }
function scCollectSelected(){ scCollectIds([...scSel]); }
function scCollectIds(ids){
  const os = ids.map(id => scData.find(o => +o.id === +id)).filter(Boolean);
  if(!os.length) return;
  const names = [...new Set(os.map(o => o.patient_name || ''))].join(', ');
  scCollect(ids, os.map(o => o.description).join(' \u2022 '), names);
}

function scCollect(orderIds, testName, patientName){
  const orderId = orderIds[0];
  const modal = document.createElement('div');
  modal.id = 'sc-modal';
  modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
  modal.innerHTML = `<div style="background:white;border-radius:12px;width:520px;max-width:92vw;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-syringe" style="color:#16a34a;"></i> Collect Sample</h3>
      <button onclick="document.getElementById('sc-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:16px;">
        <div style="font-size:14px;font-weight:700;color:var(--gray-900);">${esc(testName)}</div>
        <div style="font-size:12px;color:#1e40af;margin-top:2px;">Patient: ${esc(patientName)}</div>
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Sample Type</label>
        <select id="sc-sample-type" onchange="scConsPreset()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          <option value="blood">Blood</option>
          <option value="urine">Urine</option>
          <option value="stool">Stool</option>
          <option value="swab">Swab</option>
        </select>
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Collection Notes</label>
        <input type="text" id="sc-notes" placeholder="e.g. EDTA tube, fasting sample" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>
      <div style="margin-bottom:14px;background:#f8fafc;border:1px solid var(--gray-200);border-radius:8px;padding:12px;">
        <div style="font-size:13px;font-weight:600;color:var(--gray-800);margin-bottom:2px;"><i class="fas fa-boxes-stacked" style="color:#059669;"></i> Consumables used</div>
        <div style="font-size:11px;color:var(--gray-500);margin-bottom:8px;">Deducted from <strong id="sc-cons-store">the Laboratory store</strong> when you confirm${orderIds.length>1?' (once, for the whole collection)':''}.</div>
        <div id="sc-cons-list" style="margin-bottom:8px;"></div>
        <div style="position:relative;">
          <input type="text" id="sc-cons-search" placeholder="Search consumable (tube, syringe, swab...)" oninput="scConsSearchDebounce()" autocomplete="off" style="width:100%;padding:8px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:12px;box-sizing:border-box;">
          <div id="sc-cons-dd" style="display:none;position:absolute;top:100%;left:0;right:0;max-height:190px;overflow-y:auto;background:white;border:1px solid var(--gray-300);border-radius:6px;box-shadow:0 8px 20px rgba(0,0,0,.12);z-index:5;"></div>
        </div>
      </div>
      <button onclick="scSave([${orderIds}])" style="width:100%;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
        <i class="fas fa-check-circle"></i> Confirm Collection
      </button>
    </div>
  </div>`;
  document.body.appendChild(modal);
  scConsInit(orderId);
}

// ---- Consumables used for the sample (deducted from the Laboratory store on Confirm) ----
let scCons = [], scConsStore = {id: 0, name: 'the Laboratory store'}, scConsTimer;
async function scConsInit(orderId){
  scCons = [];
  scConsRender();
  try{
    const sj = await (await fetch(labBasePath+'/api.php?endpoint=consultation/consult&action=lab_stores')).json();
    if(sj.success && sj.data && sj.data.length) scConsStore = {id: +sj.data[0].id, name: sj.data[0].name};
    document.getElementById('sc-cons-store').textContent = scConsStore.name;
    // consumables already linked to this test in the Service Catalog come pre-filled (editable)
    const j = await (await fetch(labBasePath+'/api.php?endpoint=consultation/consult&action=lab_order_consumables&order_id='+orderId+'&store_id='+scConsStore.id)).json();
    if(j.success && j.data && !j.data.is_consumed){
      (j.data.linked_items || []).forEach(li => scConsAdd({id: li.item_id, name: li.item_name, item_code: li.item_code, uom_abbr: li.uom_abbr, store_stock: li.store_stock}, parseFloat(li.default_quantity) || 1));
    }
  }catch(e){}
  scConsPreset();
}
// Standard consumables per sample type (item ids from Inventory), added when the type is picked; editable
const SC_PRESETS = {
  blood: [75, 143, 111, 199, 201, 113],   // gloves, alcohol pad, vacutainer, needle G21 (adult), needle G23 (children), EDTA tube
  urine: [116, 112],                      // urine container, envelope
  swab:  [124, 112],                      // vaginal swab, envelope
  stool: [115, 112]                       // stool container, envelope
};
async function scConsPreset(){
  scCons = scCons.filter(c => !c.auto);
  const ids = SC_PRESETS[document.getElementById('sc-sample-type').value] || [];
  try{
    const j = await (await fetch(labBasePath+'/api.php?endpoint=service-catalog&action=inventory_items&ids='+ids+'&store_id='+scConsStore.id)).json();
    const byId = {}; (j.data || []).forEach(it => byId[it.id] = it);
    ids.forEach(id => { const it = byId[id];
      if(it && !scCons.some(c => c.item_id == id)){ scConsAdd(it, 1); scCons[scCons.length-1].auto = true; } });
  }catch(e){}
  scConsRender();
}
function scConsAdd(it, qty){
  const ex = scCons.find(c => c.item_id == it.id);
  if(ex){ ex.quantity += (qty || 1); }
  else scCons.push({item_id: +it.id, name: it.name, code: it.item_code || '', uom: it.uom_abbr || '', stock: parseFloat(it.store_stock || 0), quantity: qty || 1});
  scConsRender();
}
function scConsRender(){
  const box = document.getElementById('sc-cons-list'); if(!box) return;
  box.innerHTML = scCons.length ? scCons.map((c, i) => `<div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid var(--gray-100);font-size:12px;">
      <div style="flex:1;min-width:0;"><div style="font-weight:600;color:var(--gray-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(c.name)}</div>
      <div style="font-size:10px;color:${c.quantity > c.stock ? '#dc2626' : 'var(--gray-500)'};">${esc(c.code)} · in store: ${c.stock}${c.quantity > c.stock ? ' — not enough' : ''}</div></div>
      <input type="number" min="0.01" step="any" value="${c.quantity}" onchange="scCons[${i}].quantity=parseFloat(this.value)||0;scConsRender()" style="width:70px;padding:5px;border:1px solid var(--gray-300);border-radius:5px;font-size:12px;">
      <span style="font-size:11px;color:var(--gray-500);width:28px;">${esc(c.uom)}</span>
      <button type="button" onclick="scCons.splice(${i},1);scConsRender()" style="border:none;background:none;color:#dc2626;cursor:pointer;font-size:16px;" title="Remove">&times;</button></div>`).join('')
    : '<div style="font-size:11px;color:var(--gray-400);padding:2px 0;">None added. Search below to add what was used.</div>';
}
function scConsSearchDebounce(){ clearTimeout(scConsTimer); scConsTimer = setTimeout(scConsSearch, 250); }
async function scConsSearch(){
  const q = document.getElementById('sc-cons-search').value.trim(), dd = document.getElementById('sc-cons-dd');
  if(!q){ dd.style.display = 'none'; return; }
  dd.style.display = 'block'; dd.innerHTML = '<div style="padding:8px;color:var(--gray-400);font-size:11px;">Searching...</div>';
  try{
    const j = await (await fetch(labBasePath+'/api.php?endpoint=service-catalog&action=inventory_items&q='+encodeURIComponent(q)+'&store_id='+scConsStore.id)).json();
    const items = (j.success && j.data) ? j.data : [];
    window.scConsResults = items;
    dd.innerHTML = items.length ? items.map((it, i) => { const st = parseFloat(it.store_stock || 0);
      return `<div onclick="scConsPick(${i})" style="padding:6px 10px;border-bottom:1px solid var(--gray-100);cursor:pointer;font-size:11px;display:flex;justify-content:space-between;gap:8px;">
        <div><div style="font-weight:600;color:var(--gray-900);">${esc(it.name)}</div><div style="font-size:9px;color:var(--gray-500);font-family:monospace;">${esc(it.item_code||'')}</div></div>
        <div style="font-weight:600;white-space:nowrap;color:${st>0?'#059669':'#dc2626'};">In store: ${st}</div></div>`; }).join('')
      : '<div style="padding:8px;color:var(--gray-400);font-size:11px;">No matching items.</div>';
  }catch(e){ dd.innerHTML = '<div style="padding:8px;color:#dc2626;font-size:11px;">Search error</div>'; }
}
function scConsPick(i){
  scConsAdd(window.scConsResults[i], 1);
  document.getElementById('sc-cons-search').value = '';
  document.getElementById('sc-cons-dd').style.display = 'none';
}

async function scSave(orderIds){
  const sampleType = document.getElementById('sc-sample-type').value;
  const notes = document.getElementById('sc-notes').value;
  const consumables = scCons.filter(c => c.quantity > 0).map(c => ({item_id: c.item_id, quantity: c.quantity}));
  const short = scCons.find(c => c.quantity > c.stock);
  if(short){ alert('Not enough ' + short.name + ' in ' + scConsStore.name + ' (in store: ' + short.stock + ', needed: ' + short.quantity + '). Lower the quantity or remove it.'); return; }

  const failed = [];
  for(let i = 0; i < orderIds.length; i++){
    try{
      const r = await fetch(labBasePath+'/api.php?endpoint=consultation/consult&action=lab_result', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          order_id: orderIds[i],
          result: 'Sample collected: ' + sampleType + (notes ? ' | ' + notes : ''),
          status: 'in_progress',
          // consumables are deducted once, on the first test of the batch
          consumables: i === 0 ? consumables : [],
          store_id: i === 0 ? (scConsStore.id || undefined) : undefined
        })
      });
      const j = await r.json();
      if(j.success) scSel.delete(+orderIds[i]); else failed.push(j.message || 'error');
    } catch(e){ failed.push(e.message); }
  }
  const ok = orderIds.length - failed.length;
  alert(failed.length ? ('Collected ' + ok + ' of ' + orderIds.length + '. Error: ' + failed[0]) : ('\u2713 ' + ok + ' sample(s) collected! Moved to processing.'));
  if(!failed.length) document.getElementById('sc-modal').remove();
  scLoad();
}

scLoad();
setInterval(scLoad, 30000);
</script>

<!-- ============================================
RESULTS ENTRY
============================================ -->
<?php elseif ($subModule == 'results'): ?>

<!-- Tests with samples collected, ready for results entry -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Results Entry</h3>
            <p style="font-size: 13px; color: var(--gray-500);">Tests with samples collected — enter results below</p>
        </div>
        <div style="display:flex;gap:8px;">
          <select id="res-filter" onchange="resLoad()" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
            <option value="in_progress">Awaiting Results</option>
            <option value="completed">Completed</option>
            <option value="">All</option>
          </select>
          <button onclick="resLoad()" style="padding: 8px 16px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;font-size:12px;">
              <i class="fas fa-sync-alt"></i>
          </button>
          <button onclick="resExportList()" style="padding: 8px 16px; background: #16a34a; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;font-size:12px;"><i class="fas fa-file-excel"></i> Excel</button>
        </div>
    </div>

    <div id="res-list" style="display:flex;flex-direction:column;gap:12px;">
      <div style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
    </div>
</div>

<script>
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

let resData = [];
function resExportList(){
  const headers = ['#','Patient Name','Patient No','Test','Status','Ordered At','Result','Completed At','Completed By'];
  const flat = r => { try { const p = JSON.parse(r); if(Array.isArray(p)) return p.map(x => (x.test_name||'') + ': ' + (x.value||'') + (x.unit ? ' ' + x.unit : '')).join('; '); } catch(e){} return r || ''; };
  const rows = resData.map((o, i) => [i + 1, o.patient_name || '', o.patient_no || '', o.description || '', o.status || '', o.ordered_at || '', o.status === 'completed' ? flat(o.result) : '', o.completed_at || '', o.completed_by_name || '']);
  labDownloadExcel('Lab_Results_' + new Date().toISOString().substring(0, 10), 'Results', headers, rows);
}

async function resLoad(){
  const status = document.getElementById('res-filter').value;
  const url = labBasePath+'/api.php?endpoint=consultation/consult&action=lab_orders&type=all' + (status ? '&status='+status : '');
  try{
    const r = await fetch(url);
    const j = await r.json();
    resData = (j.success && j.data) || [];
    const container = document.getElementById('res-list');
    
    if(j.success && j.data && j.data.length > 0){
      container.innerHTML = j.data.map(o => {
        const isCompleted = o.status === 'completed';
        const borderColor = isCompleted ? '#bbf7d0' : '#bfdbfe';
        const bgColor = isCompleted ? '#f0fdf4' : 'white';
        
        let resultHtml = '';
        if(isCompleted){
          resultHtml = `<div style="background:#d1fae5;border:1px solid #bbf7d0;border-radius:6px;padding:10px 12px;margin-top:10px;">
            <div style="font-size:11px;font-weight:600;color:#065f46;margin-bottom:4px;">RESULT:</div>
            ${labResultHtml(o.result)}
            <div style="font-size:10px;color:var(--gray-400);margin-top:6px;">Completed: ${o.completed_at||'—'}</div>
            <div id="res-edit-${o.id}"></div>
            <button id="res-edit-btn-${o.id}" onclick="resEdit(${o.id})" style="margin-top:8px;padding:6px 12px;background:#f59e0b;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-edit"></i> Edit Result</button>
          </div>`;
        } else {
          resultHtml = `<div style="margin-top:10px;">
            ${/^Sample collected/i.test(o.result||'') ? `<div style="font-size:11px;color:var(--gray-500);margin-bottom:6px;"><i class="fas fa-vial"></i> ${esc(o.result)}</div>` : ''}
            <div id="lab-result-area-${o.id}">
              <textarea id="lab-result-${o.id}" rows="3" placeholder="Enter result for ${esc(o.description)}..." style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;font-family:monospace;resize:vertical;">${/^Sample collected/i.test(o.result||'') ? '' : esc(o.result||'')}</textarea>
            </div>
            <div style="display:flex;gap:8px;margin-top:8px;">
              <button onclick="resSave(${o.id},'completed')" style="padding:7px 14px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                <i class="fas fa-check"></i> Save as Completed
              </button>
              <button onclick="resSave(${o.id},'in_progress')" style="padding:7px 14px;background:#d97706;color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;">
                <i class="fas fa-clock"></i> Save Draft
              </button>
              <button onclick="resCancel(${o.id},'${esc(o.description).replace(/'/g,"\\'")}')" style="padding:7px 14px;background:white;color:#dc2626;border:1px solid #fecaca;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;margin-left:auto;">
                <i class="fas fa-undo"></i> Cancel &amp; Back to Sample Collection
              </button>
            </div>
          </div>`;
        }
        
        return `<div style="border:1px solid ${borderColor};border-radius:8px;padding:14px 16px;background:${bgColor};">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <span style="font-size:15px;font-weight:700;color:var(--gray-900);">${esc(o.description)}</span>
              <span style="font-size:12px;color:var(--gray-500);margin-left:8px;">${o.order_type||'lab'}</span>
              ${payBadge(o.pay_status, o.pay_balance)}
            </div>
            <span style="padding:3px 8px;border-radius:12px;font-size:10px;font-weight:600;background:${isCompleted?'#d1fae5':'#dbeafe'};color:${isCompleted?'#065f46':'#1e40af'};">${isCompleted?'Completed':'Awaiting Result'}</span>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:16px;font-size:12px;color:var(--gray-500);margin-top:6px;">
            <span><i class="fas fa-user"></i> ${esc(o.patient_name||'—')}</span>
            <span><i class="fas fa-id-card"></i> ${esc(o.patient_no||'')}</span>
            <span><i class="fas fa-clock"></i> ${o.ordered_at ? new Date(o.ordered_at).toLocaleString() : '—'}</span>
            <span><i class="fas fa-user-md" style="color:var(--primary-color);"></i> Ordered by: <strong>${esc(o.ordered_by_name||'Doctor')}</strong></span>
            ${isCompleted ? `<span><i class="fas fa-microscope" style="color:#16a34a;"></i> Tested by: <strong>${esc(o.completed_by_name||o.performed_by_name||'Lab Technician')}</strong></span>` : ''}
          </div>
          ${resultHtml}
        </div>`;
      }).join('');
      // Tests with parameters get one input per parameter (draft values kept) instead of the text box
      j.data.filter(o => o.status !== 'completed').forEach(o => labLoadParamsForOrder(o.id, o.description, o.result));
    } else {
      container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:32px;display:block;margin-bottom:8px;color:var(--success-color);"></i>No tests matching this filter.</div>';
    }
  } catch(e){
    document.getElementById('res-list').innerHTML = '<div style="padding:20px;text-align:center;color:var(--danger-color);">Error: '+e.message+'</div>';
  }
}

// Re-open a completed result for correction (same inputs as entry; Save Changes overwrites it until verified)
async function resEdit(orderId){
  const o = resData.find(x => x.id === orderId);
  if(!o) return;
  document.getElementById('res-edit-btn-'+orderId).style.display = 'none';
  document.getElementById('res-edit-'+orderId).innerHTML = `<div id="lab-result-area-${orderId}" style="margin-top:8px;"><textarea id="lab-result-${orderId}" rows="3" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;font-family:monospace;resize:vertical;">${esc(o.result||'')}</textarea></div>
    <div style="display:flex;gap:8px;margin-top:8px;">
      <button onclick="resSave(${orderId},'completed')" style="padding:7px 14px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-check"></i> Save Changes</button>
      <button onclick="resLoad()" style="padding:7px 14px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;">Cancel</button>
    </div>`;
  await labLoadParamsForOrder(orderId, o.description, o.result);
}

async function resSave(orderId, status){
  const params = labCollectParams(orderId);
  const textEl = document.getElementById('lab-result-'+orderId);
  let result;
  if(params){
    if(!params.some(p => p.value)) return alert('Please enter at least one result value.');
    result = JSON.stringify(params.concat(labNoteEntry(orderId)));
  } else {
    result = textEl ? textEl.value.trim() : '';
  }
  if(!result) return alert('Please enter the result.');
  
  try{
    const r = await fetch(labBasePath+'/api.php?endpoint=consultation/consult&action=lab_result', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({order_id: orderId, result: result, status: status})
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Result saved!');
      resLoad();
    } else { alert('Error: '+j.message); }
  } catch(e){ alert('Failed: '+e.message); }
}

// Undo the sample collection: the test returns to Sample Collection to be collected again (another sample type / notes)
async function resCancel(orderId, testName){
  if(!confirm('Cancel "'+testName+'" here and send it back to Sample Collection?\n\nAny result typed or saved as draft will be cleared.')) return;
  try{
    const r = await fetch(labBasePath+'/api.php?endpoint=consultation/consult&action=lab_result', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({order_id: orderId, status: 'ordered'})
    });
    const j = await r.json();
    if(!j.success) return alert('Error: '+j.message);
    if(confirm('✓ Sent back to Sample Collection.\n\nOpen Sample Collection now?')) location.href = 'lab.php?module=lab&sub=sample-collection';
    else resLoad();
  } catch(e){ alert('Failed: '+e.message); }
}

resLoad();
</script>

<!-- ============================================
CONFIRM ITEM CONSUMPTION & DEDUCT STOCK
============================================ -->
<?php elseif ($subModule == 'consumption' || $subModule == 'item-consumption'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
      <h3 style="font-size: 18px; font-weight: 700; color: var(--gray-900); display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-boxes-stacked" style="color: #059669;"></i> Confirm Laboratory Item Consumption & Deduct Stock
      </h3>
      <p style="font-size: 13px; color: var(--gray-500); margin-top: 3px;">
        Review lab test orders, confirm reagents & consumables used, and deduct quantities from laboratory stock batches (FIFO).
      </p>
    </div>
    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
      <a href="service-catalog.php?module=admin&sub=inventory-items" target="_blank" style="padding: 8px 14px; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 8px; color: var(--gray-700); text-decoration: none; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
        <i class="fas fa-external-link-alt"></i> Catalog Mappings
      </a>
      <button onclick="licLoad()" style="padding: 8px 14px; background: #059669; color: white; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
        <i class="fas fa-sync-alt"></i> Refresh
      </button>
    </div>
  </div>

  <!-- KPI Cards -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px;">
    <div style="background: var(--gray-50); border-radius: 10px; padding: 14px; text-align: center; border: 1px solid var(--gray-200);">
      <div style="font-size: 22px; font-weight: 800; color: var(--gray-900);" id="lic-stat-total">0</div>
      <div style="font-size: 12px; color: var(--gray-500); font-weight: 500; margin-top: 2px;">Total Lab Orders</div>
    </div>
    <div style="background: #fffbeb; border-radius: 10px; padding: 14px; text-align: center; border: 1px solid #fef3c7;">
      <div style="font-size: 22px; font-weight: 800; color: #d97706;" id="lic-stat-pending">0</div>
      <div style="font-size: 12px; color: #92400e; font-weight: 600; margin-top: 2px;">Pending Stock Deduction</div>
    </div>
    <div style="background: #f0fdf4; border-radius: 10px; padding: 14px; text-align: center; border: 1px solid #bbf7d0;">
      <div style="font-size: 22px; font-weight: 800; color: #059669;" id="lic-stat-consumed">0</div>
      <div style="font-size: 12px; color: #065f46; font-weight: 600; margin-top: 2px;">Stock Deducted & Confirmed</div>
    </div>
    <div style="background: #eff6ff; border-radius: 10px; padding: 14px; text-align: center; border: 1px solid #bfdbfe;">
      <div style="font-size: 22px; font-weight: 800; color: #2563eb;" id="lic-stat-items-consumed">0</div>
      <div style="font-size: 12px; color: #1e40af; font-weight: 500; margin-top: 2px;">Consumables Deducted</div>
    </div>
  </div>

  <!-- Filter Controls -->
  <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; background: var(--gray-50); padding: 12px 16px; border-radius: 10px; border: 1px solid var(--gray-200);">
    <div style="flex: 1; min-width: 220px; position: relative;">
      <input id="lic-search" type="text" placeholder="Search patient, patient no, test name, order ID..." oninput="licDebounce()" style="width: 100%; padding: 9px 12px 9px 34px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
      <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 13px;"></i>
    </div>
    <div style="min-width: 160px;">
      <select id="lic-status-filter" onchange="licFilterLocal()" style="width: 100%; padding: 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;">
        <option value="pending">Pending Deduction Only</option>
        <option value="all">All Orders</option>
        <option value="consumed">Stock Deducted Only</option>
      </select>
    </div>
    <div style="min-width: 170px;">
      <select id="lic-store-filter" onchange="licLoad()" style="width: 100%; padding: 9px 12px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 13px; background: white;" title="Deduct Store">
        <option value="">Laboratory Store</option>
      </select>
    </div>
    <div style="display: flex; gap: 6px; align-items: center;">
      <input type="date" id="lic-date-from" onchange="licLoad()" style="padding: 8px 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 12px; background: white;" title="From date">
      <span style="font-size: 12px; color: var(--gray-400);">to</span>
      <input type="date" id="lic-date-to" onchange="licLoad()" style="padding: 8px 10px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 12px; background: white;" title="To date">
    </div>
    <div style="font-size: 12px; color: var(--gray-500); white-space: nowrap;" id="lic-count-info"></div>
  </div>

  <!-- Orders Table -->
  <div style="border: 1px solid var(--gray-200); border-radius: 10px; overflow-x: auto; margin-bottom: 24px;">
    <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 1000px;">
      <thead><tr style="background: var(--gray-50); border-bottom: 2px solid var(--gray-200);">
        <th style="padding: 12px 14px; text-align: left; width: 110px; font-weight: 600;">Order ID / Date</th>
        <th style="padding: 12px 14px; text-align: left; width: 220px; font-weight: 600;">Patient</th>
        <th style="padding: 12px 14px; text-align: left; width: 220px; font-weight: 600;">Test / Procedure</th>
        <th style="padding: 12px 14px; text-align: left; font-weight: 600;">Connected Consumables (from Catalog)</th>
        <th style="padding: 12px 14px; text-align: center; width: 150px; font-weight: 600;">Stock Deduction</th>
        <th style="padding: 12px 14px; text-align: center; width: 170px; font-weight: 600;">Action</th>
      </tr></thead>
      <tbody id="lic-tbody"><tr><td colspan="6" style="padding: 40px; text-align: center; color: var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size: 22px;"></i></td></tr></tbody>
    </table>
  </div>
  <div id="lic-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; font-size: 12px; color: var(--gray-500);"></div>

  <!-- Recent Consumption Log Section -->
  <div style="border-top: 2px solid var(--gray-200); padding-top: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <h4 style="font-size: 14px; font-weight: 700; color: var(--gray-800); display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-history" style="color: var(--primary-color);"></i> Recent Consumable Stock Deductions Audit Log
      </h4>
      <button onclick="licLoadHistory()" style="padding: 4px 10px; background: var(--gray-100); border: 1px solid var(--gray-200); border-radius: 6px; font-size: 11px; cursor: pointer; font-weight: 600;">
        <i class="fas fa-sync-alt"></i> Refresh Log
      </button>
    </div>
    <div style="border: 1px solid var(--gray-200); border-radius: 8px; overflow-x: auto; background: white;">
      <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
        <thead><tr style="background: var(--gray-50); border-bottom: 1px solid var(--gray-200);">
          <th style="padding: 8px 10px; text-align: left; width: 130px; font-weight: 600;">Date & Time</th>
          <th style="padding: 8px 10px; text-align: left; width: 180px; font-weight: 600;">Patient</th>
          <th style="padding: 8px 10px; text-align: left; font-weight: 600;">Test / Order</th>
          <th style="padding: 8px 10px; text-align: left; font-weight: 600;">Item Deducted</th>
          <th style="padding: 8px 10px; text-align: center; width: 90px; font-weight: 600;">Quantity</th>
          <th style="padding: 8px 10px; text-align: left; width: 130px; font-weight: 600;">Store</th>
          <th style="padding: 8px 10px; text-align: left; width: 130px; font-weight: 600;">Deducted By</th>
          <th style="padding: 8px 10px; text-align: center; width: 90px; font-weight: 600;">Status</th>
        </tr></thead>
        <tbody id="lic-history-tbody">
          <tr><td colspan="8" style="padding: 20px; text-align: center; color: var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading recent activity...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal: Confirm Item Consumption & Deduct Stock -->
<div id="lic-confirm-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
  <div style="background: white; border-radius: 14px; width: 800px; max-width: 95vw; padding: 24px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.3);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 1px solid var(--gray-200); padding-bottom: 14px;">
      <div>
        <h3 style="font-size: 17px; font-weight: 700; color: var(--gray-900); display: flex; align-items: center; gap: 8px;">
          <i class="fas fa-boxes-stacked" style="color: #059669;"></i> Confirm Consumable Deduction for Lab Order
        </h3>
        <div style="font-size: 12px; color: var(--gray-600); margin-top: 3px;" id="lic-modal-subheading"></div>
      </div>
      <button onclick="licCloseConfirmModal()" style="border: none; background: none; font-size: 24px; line-height: 1; cursor: pointer; color: var(--gray-400);">&times;</button>
    </div>

    <!-- Patient & Order Details Card -->
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; margin-bottom: 18px; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; font-size: 12px;">
      <div>
        <div style="color: var(--gray-500); font-size: 11px;">Patient</div>
        <div style="font-weight: 700; color: var(--gray-900); font-size: 13px;" id="lic-mdl-patient">—</div>
        <div style="color: var(--primary-color); font-family: monospace; font-size: 11px;" id="lic-mdl-patient-no">—</div>
      </div>
      <div>
        <div style="color: var(--gray-500); font-size: 11px;">Test Order</div>
        <div style="font-weight: 700; color: var(--gray-900); font-size: 13px;" id="lic-mdl-test">—</div>
        <div style="color: var(--gray-500); font-size: 11px;" id="lic-mdl-order-id">—</div>
      </div>
      <div>
        <div style="color: var(--gray-500); font-size: 11px;">Visit & Ordered By</div>
        <div style="font-weight: 600; color: var(--gray-800);" id="lic-mdl-doctor">—</div>
        <div style="color: var(--gray-500); font-size: 11px;" id="lic-mdl-visit">—</div>
      </div>
      <div>
        <div style="color: var(--gray-500); font-size: 11px;">Deduct from Store</div>
        <select id="lic-mdl-store" onchange="licMdlStoreChanged()" style="width: 100%; padding: 6px 8px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 12px; background: white; font-weight: 600; margin-top: 3px;">
        </select>
      </div>
    </div>

    <input type="hidden" id="lic-mdl-order-id-input">
    <input type="hidden" id="lic-mdl-service-id-input">
    <input type="hidden" id="lic-mdl-visit-id-input">
    <input type="hidden" id="lic-mdl-patient-id-input">

    <!-- Consumables List To Deduct -->
    <div style="margin-bottom: 20px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <h4 style="font-size: 13px; font-weight: 700; color: var(--gray-800); display: flex; align-items: center; gap: 6px;">
          <i class="fas fa-vials" style="color: #059669;"></i> Items To Deduct From Stock
        </h4>
        <span style="font-size: 11px; color: var(--gray-500);">Pre-populated from Service Catalog mapping</span>
      </div>

      <div id="lic-mdl-alert-wrap"></div>

      <div style="border: 1px solid var(--gray-200); border-radius: 8px; overflow-x: auto; background: white;">
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
          <thead><tr style="background: var(--gray-50); border-bottom: 1px solid var(--gray-200);">
            <th style="padding: 8px 12px; text-align: left; font-weight: 600;">Inventory Item</th>
            <th style="padding: 8px 12px; text-align: center; width: 120px; font-weight: 600;">Available Stock</th>
            <th style="padding: 8px 12px; text-align: center; width: 110px; font-weight: 600;">Qty to Deduct</th>
            <th style="padding: 8px 12px; text-align: left; font-weight: 600;">Notes / Remarks</th>
            <th style="padding: 8px 12px; text-align: center; width: 50px; font-weight: 600;"></th>
          </tr></thead>
          <tbody id="lic-mdl-items-tbody">
            <tr><td colspan="5" style="padding: 24px; text-align: center; color: var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading mapped items...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Additional Consumable Item Box -->
    <div style="background: #f8fafc; border: 1px dashed var(--gray-300); border-radius: 8px; padding: 12px 16px; margin-bottom: 18px;">
      <div style="font-size: 12px; font-weight: 600; color: var(--gray-700); margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-plus-circle" style="color: var(--primary-color);"></i> Add Extra Consumable Item (Optional)
      </div>
      <div style="display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: center; position: relative;">
        <div style="position: relative;">
          <input type="text" id="lic-extra-search" placeholder="Search item (e.g. gloves, extra syringe, swab)..." oninput="licExtraSearchDebounce()" autocomplete="off" style="width: 100%; padding: 7px 10px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 12px; background: white;">
          <input type="hidden" id="lic-extra-selected-item-id">
          <input type="hidden" id="lic-extra-selected-item-name">
          <input type="hidden" id="lic-extra-selected-item-code">
          <input type="hidden" id="lic-extra-selected-item-uom">
          <input type="hidden" id="lic-extra-selected-item-stock">
          <div id="lic-extra-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 180px; overflow-y: auto; background: white; border: 1px solid var(--gray-300); border-radius: 6px; box-shadow: 0 10px 20px rgba(0,0,0,0.15); z-index: 1000; margin-top: 2px;"></div>
        </div>
        <div>
          <input type="number" id="lic-extra-qty" value="1.0" min="0.0001" step="any" placeholder="Qty" style="width: 100%; padding: 7px 10px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 12px; background: white;">
        </div>
        <button type="button" onclick="licAddExtraRow()" style="padding: 7px 14px; background: var(--gray-100); color: var(--gray-800); border: 1px solid var(--gray-300); border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;">
          + Add
        </button>
      </div>
    </div>

    <!-- General Notes -->
    <div style="margin-bottom: 20px;">
      <label style="display: block; font-size: 11px; font-weight: 600; color: var(--gray-700); margin-bottom: 4px;">General Consumption Notes (Optional)</label>
      <input type="text" id="lic-mdl-notes" placeholder="e.g. Standard test execution, control verified" style="width: 100%; padding: 8px 12px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 12px;">
    </div>

    <div style="display: flex; justify-content: flex-end; gap: 10px;">
      <button onclick="licCloseConfirmModal()" style="padding: 10px 20px; background: var(--gray-100); color: var(--gray-700); border: 1px solid var(--gray-300); border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
        Cancel
      </button>
      <button onclick="licSubmitConfirm()" id="lic-submit-btn" style="padding: 10px 24px; background: #059669; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
        <i class="fas fa-check-double"></i> Confirm & Deduct from Stock
      </button>
    </div>
  </div>
</div>

<!-- Modal: View Consumption Details -->
<div id="lic-view-modal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
  <div style="background: white; border-radius: 12px; width: 680px; max-width: 95vw; padding: 24px; max-height: 90vh; overflow-y: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid var(--gray-200); padding-bottom: 12px;">
      <h3 style="font-size: 16px; font-weight: 700; color: var(--gray-900); display: flex; align-items: center; gap: 8px;">
        <i class="fas fa-check-circle" style="color: #059669;"></i> Consumption & Stock Deduction Record
      </h3>
      <button onclick="document.getElementById('lic-view-modal').style.display='none'" style="border: none; background: none; font-size: 22px; cursor: pointer; color: var(--gray-400);">&times;</button>
    </div>
    <div id="lic-view-modal-content"></div>
    <div style="margin-top: 18px; text-align: right;">
      <button onclick="document.getElementById('lic-view-modal').style.display='none'" style="padding: 8px 20px; background: var(--gray-100); border: 1px solid var(--gray-300); border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">Close</button>
    </div>
  </div>
</div>

<script>
const licAPI = labBasePath + '/api.php?endpoint=consultation/consult';
const licScAPI = labBasePath + '/api.php?endpoint=service-catalog';
let licAllOrders = [];
let licStores = [];
let licDebounceTimer;
let licExtraSearchTimer;
let licCurrentPage = 1;
const licPageSize = 25;
let licCurrentMdlItems = [];

function licDebounce(){ clearTimeout(licDebounceTimer); licDebounceTimer = setTimeout(licLoad, 300); }

async function licInit(){
  // Load stores list
  try{
    const r = await fetch(licAPI + '&action=lab_stores');
    const j = await r.json();
    if(j.success && j.data){
      licStores = j.data;
      const storeFilter = document.getElementById('lic-store-filter');
      const mdlStore = document.getElementById('lic-mdl-store');
      let opts = licStores.map(s => `<option value="${s.id}">${esc(s.name)} (${esc(s.code||s.type||'')})</option>`).join('');
      storeFilter.innerHTML = opts;
      mdlStore.innerHTML = opts;
    }
  }catch(e){}

  licLoad();
  licLoadHistory();
}

async function licLoad(){
  const status = document.getElementById('lic-status-filter').value;
  const storeId = document.getElementById('lic-store-filter').value || (licStores[0] ? licStores[0].id : 0);
  const from = document.getElementById('lic-date-from').value;
  const to = document.getElementById('lic-date-to').value;
  const tbody = document.getElementById('lic-tbody');

  tbody.innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr>';

  try{
    let url = licAPI + '&action=lab_consumption_orders&store_id=' + encodeURIComponent(storeId);
    if(from) url += '&date_from=' + encodeURIComponent(from);
    if(to) url += '&date_to=' + encodeURIComponent(to);

    const r = await fetch(url);
    const j = await r.json();
    if(!j.success){
      tbody.innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;color:#dc2626;">'+esc(j.message)+'</td></tr>';
      return;
    }

    licAllOrders = j.data || [];

    // Calculate Stats
    const total = licAllOrders.length;
    const consumed = licAllOrders.filter(o => o.is_consumed == 1).length;
    const pending = total - consumed;
    let itemsDeducted = 0;
    licAllOrders.forEach(o => { itemsDeducted += (o.consumed_items_count || 0); });

    document.getElementById('lic-stat-total').textContent = total.toLocaleString();
    document.getElementById('lic-stat-pending').textContent = pending.toLocaleString();
    document.getElementById('lic-stat-consumed').textContent = consumed.toLocaleString();
    document.getElementById('lic-stat-items-consumed').textContent = itemsDeducted.toLocaleString();

    licFilterLocal();
  }catch(e){
    tbody.innerHTML = '<tr><td colspan="6" style="padding:20px;text-align:center;color:#dc2626;">Error: '+esc(e.message)+'</td></tr>';
  }
}

function licFilterLocal(){
  const search = (document.getElementById('lic-search').value||'').toLowerCase().trim();
  const status = document.getElementById('lic-status-filter').value;

  let list = licAllOrders;

  if(status === 'pending'){
    list = list.filter(o => o.is_consumed != 1);
  } else if(status === 'consumed'){
    list = list.filter(o => o.is_consumed == 1);
  }

  if(search){
    list = list.filter(o => {
      const pt = (o.patient_name||'').toLowerCase();
      const ptNo = (o.patient_no||'').toLowerCase();
      const desc = (o.description||'').toLowerCase();
      const oid = String(o.id);
      return pt.includes(search) || ptNo.includes(search) || desc.includes(search) || oid.includes(search);
    });
  }

  licCurrentPage = 1;
  licRenderList(list);
}

function licRenderList(list){
  const tbody = document.getElementById('lic-tbody');
  const countInfo = document.getElementById('lic-count-info');
  const pag = document.getElementById('lic-pagination');

  countInfo.textContent = `Showing ${list.length} of ${licAllOrders.length} orders`;

  if(!list.length){
    tbody.innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-boxes-stacked" style="font-size:24px;display:block;margin-bottom:8px;"></i>No matching lab orders found.</td></tr>';
    pag.innerHTML = '';
    return;
  }

  const totalPages = Math.ceil(list.length / licPageSize);
  if(licCurrentPage > totalPages) licCurrentPage = totalPages;
  if(licCurrentPage < 1) licCurrentPage = 1;

  const start = (licCurrentPage - 1) * licPageSize;
  const end = start + licPageSize;
  const pageItems = list.slice(start, end);

  tbody.innerHTML = pageItems.map(o => {
    const isConsumed = o.is_consumed == 1;
    const dateStr = o.ordered_at ? new Date(o.ordered_at).toLocaleDateString() : (o.visit_date||'—');
    const timeStr = o.ordered_at ? new Date(o.ordered_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) : '';
    const consumables = o.linked_consumables || [];

    let consumablesHtml = '';
    if(!consumables.length){
      consumablesHtml = `<span style="font-size:11px;color:#92400e;background:#fef3c7;padding:2px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;">
        <i class="fas fa-info-circle"></i> No catalog consumables mapped
      </span>`;
    } else {
      consumablesHtml = `<div style="display:flex;flex-wrap:wrap;gap:4px;">` + consumables.map(c => {
        const stock = parseFloat(c.store_stock || 0);
        const inStock = stock > 0;
        const stockColor = inStock ? '#059669' : '#dc2626';
        return `<span style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:2px 6px;font-size:11px;display:inline-flex;align-items:center;gap:4px;">
          <span style="font-weight:600;color:var(--gray-800);">${esc(c.item_name)}</span>
          <span style="background:#dbeafe;color:#1e40af;padding:1px 4px;border-radius:3px;font-size:10px;font-weight:700;">${parseFloat(c.default_quantity)} ${esc(c.uom_abbr||'pcs')}</span>
          <span style="color:${stockColor};font-size:10px;font-weight:600;">(${stock} in store)</span>
        </span>`;
      }).join('') + `</div>`;
    }

    const statusBadge = isConsumed
      ? `<div>
          <span style="background:#d1fae5;color:#065f46;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
            <i class="fas fa-check-circle"></i> Stock Deducted
          </span>
          <div style="font-size:10px;color:var(--gray-500);margin-top:2px;">${o.consumed_at ? o.consumed_at.substring(0,16) : ''}</div>
         </div>`
      : `<span style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
          <i class="fas fa-clock"></i> Pending Deduction
        </span>`;

    const actionBtn = isConsumed
      ? `<div style="display:flex;gap:6px;justify-content:center;">
          <button onclick="licOpenViewModal(${o.id})" style="padding:6px 12px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300);border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;"><i class="fas fa-eye"></i> View</button>
          <button onclick="licReverseModal(${o.id})" style="padding:6px 10px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:6px;font-size:11px;cursor:pointer;font-weight:600;" title="Reverse Deduction"><i class="fas fa-undo"></i> Reverse</button>
        </div>`
      : `<button onclick="licOpenConfirmModal(${o.id})" style="padding:7px 14px;background:#059669;color:white;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 1px 2px rgba(0,0,0,0.05);">
          <i class="fas fa-check-double"></i> Confirm & Deduct
        </button>`;

    return `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 14px;">
        <div style="font-family:monospace;font-weight:700;color:var(--primary-color);">#${o.id}</div>
        <div style="font-size:11px;color:var(--gray-500);">${dateStr} ${timeStr}</div>
      </td>
      <td style="padding:10px 14px;">
        <div style="font-weight:700;color:var(--gray-900);">${esc(o.patient_name)}</div>
        <div style="font-size:11px;color:var(--gray-500);">${esc(o.patient_no||'')} &bull; ${esc(o.insurance_company||'Self Pay')}</div>
      </td>
      <td style="padding:10px 14px;">
        <div style="font-weight:600;color:var(--gray-900);">${esc(o.description)}</div>
        <div style="font-size:11px;color:var(--gray-500);">Ordered by: ${esc(o.ordered_by_name||'Doctor')}</div>
      </td>
      <td style="padding:10px 14px;">${consumablesHtml}</td>
      <td style="padding:10px 14px;text-align:center;">${statusBadge}</td>
      <td style="padding:10px 14px;text-align:center;">${actionBtn}</td>
    </tr>`;
  }).join('');

  // Pagination controls
  if(totalPages > 1){
    let btns = '';
    btns += `<button onclick="licGoPage(${licCurrentPage-1})" ${licCurrentPage===1?'disabled':''} style="padding:5px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;${licCurrentPage===1?'opacity:0.4;':''}"><i class="fas fa-chevron-left"></i></button>`;
    btns += ` <span style="padding:0 8px;font-weight:500;">Page ${licCurrentPage} of ${totalPages}</span> `;
    btns += `<button onclick="licGoPage(${licCurrentPage+1})" ${licCurrentPage===totalPages?'disabled':''} style="padding:5px 10px;border:1px solid var(--gray-200);background:white;border-radius:4px;cursor:pointer;${licCurrentPage===totalPages?'opacity:0.4;':''}"><i class="fas fa-chevron-right"></i></button>`;
    pag.innerHTML = `<div>Showing ${start+1}–${Math.min(end, list.length)} of ${list.length}</div><div style="display:flex;align-items:center;gap:4px;">${btns}</div>`;
  } else {
    pag.innerHTML = `<div>Showing ${list.length} orders</div><div></div>`;
  }
}

function licGoPage(p){
  licCurrentPage = p;
  licFilterLocal();
}

// Open modal to confirm consumption
async function licOpenConfirmModal(orderId){
  const storeId = document.getElementById('lic-store-filter').value || (licStores[0] ? licStores[0].id : 0);
  const modal = document.getElementById('lic-confirm-modal');
  const alertWrap = document.getElementById('lic-mdl-alert-wrap');
  const tbody = document.getElementById('lic-mdl-items-tbody');

  alertWrap.innerHTML = '';
  tbody.innerHTML = '<tr><td colspan="5" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading test order details...</td></tr>';
  modal.style.display = 'flex';

  try{
    const r = await fetch(licAPI + '&action=lab_order_consumables&order_id=' + orderId + '&store_id=' + storeId);
    const j = await r.json();
    if(!j.success){ alert('Error: ' + j.message); licCloseConfirmModal(); return; }

    const data = j.data;
    const order = data.order || {};
    const service = data.service || {};
    const linkedItems = data.linked_items || [];

    document.getElementById('lic-mdl-order-id-input').value = order.id || '';
    document.getElementById('lic-mdl-service-id-input').value = service.id || '';
    document.getElementById('lic-mdl-visit-id-input').value = order.visit_id || '';
    document.getElementById('lic-mdl-patient-id-input').value = order.patient_id || '';

    document.getElementById('lic-mdl-patient').textContent = order.patient_name || 'Patient';
    document.getElementById('lic-mdl-patient-no').textContent = order.patient_no || '—';
    document.getElementById('lic-mdl-test').textContent = order.description || 'Lab Test';
    document.getElementById('lic-mdl-order-id').textContent = 'Order #' + order.id + ' (' + (order.ordered_at||'') + ')';
    document.getElementById('lic-mdl-doctor').textContent = order.ordered_by_name || 'Doctor';
    document.getElementById('lic-mdl-visit').textContent = 'Visit: ' + (order.visit_no||'—');
    document.getElementById('lic-mdl-store').value = storeId;
    document.getElementById('lic-mdl-notes').value = '';

    document.getElementById('lic-modal-subheading').textContent = `Order #${order.id} &bull; ${order.patient_name} &bull; ${order.description}`;

    // Store items in state
    licCurrentMdlItems = linkedItems.map(it => ({
      item_id: it.item_id,
      item_name: it.item_name,
      item_code: it.item_code,
      quantity: parseFloat(it.default_quantity || 1.0),
      uom_abbr: it.uom_abbr || 'pcs',
      store_stock: parseFloat(it.store_stock || 0),
      notes: it.notes || ''
    }));

    if(!licCurrentMdlItems.length){
      alertWrap.innerHTML = `<div style="background:#fffbeb;border:1px solid #fef3c7;padding:12px 14px;border-radius:8px;font-size:12px;color:#92400e;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
        <i class="fas fa-exclamation-triangle" style="font-size:18px;"></i>
        <div>
          No default consumables are linked to <strong>${esc(order.description)}</strong> in Service Catalog yet. You can add items below or map them in <a href="service-catalog.php?module=admin&sub=inventory-items&search=${encodeURIComponent(order.description)}" target="_blank" style="color:#b45309;font-weight:700;text-decoration:underline;">Service Catalog &gt; Connect Inventory Items</a>.
        </div>
      </div>`;
    }

    licRenderMdlItems();
  }catch(e){
    alert('Failed to load order consumables: ' + e.message);
    licCloseConfirmModal();
  }
}

function licCloseConfirmModal(){
  document.getElementById('lic-confirm-modal').style.display = 'none';
  licCurrentMdlItems = [];
}

function licRenderMdlItems(){
  const tbody = document.getElementById('lic-mdl-items-tbody');
  if(!licCurrentMdlItems.length){
    tbody.innerHTML = '<tr><td colspan="5" style="padding:24px;text-align:center;color:var(--gray-400);"><i class="fas fa-box-open" style="font-size:20px;display:block;margin-bottom:6px;"></i>No items added yet. Search and add items below.</td></tr>';
    return;
  }

  tbody.innerHTML = licCurrentMdlItems.map((it, idx) => {
    const inStock = it.store_stock > 0;
    const exceeds = it.quantity > it.store_stock;
    const stockBadge = inStock
      ? `<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:12px;font-weight:700;font-size:11px;">${it.store_stock} in stock</span>`
      : `<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:12px;font-weight:700;font-size:11px;">Out of stock (0)</span>`;

    const warnHtml = exceeds
      ? `<div style="font-size:10px;color:#dc2626;font-weight:600;margin-top:2px;"><i class="fas fa-exclamation-triangle"></i> Exceeds store stock (${it.store_stock})</div>`
      : '';

    return `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:8px 12px;">
        <div style="font-weight:600;color:var(--gray-900);">${esc(it.item_name)}</div>
        <div style="font-family:monospace;font-size:10px;color:var(--gray-500);">${esc(it.item_code||'')}</div>
      </td>
      <td style="padding:8px 12px;text-align:center;">${stockBadge}</td>
      <td style="padding:8px 12px;text-align:center;">
        <input type="number" value="${it.quantity}" min="0.0001" step="any" onchange="licCurrentMdlItems[${idx}].quantity = parseFloat(this.value)||1; licRenderMdlItems();" style="width:75px;padding:5px 6px;border:1px solid var(--gray-300);border-radius:4px;font-size:12px;text-align:center;font-weight:600;">
        <span style="font-size:10px;color:var(--gray-500);display:block;margin-top:2px;">${esc(it.uom_abbr||'pcs')}</span>
        ${warnHtml}
      </td>
      <td style="padding:8px 12px;">
        <input type="text" value="${esc(it.notes||'')}" onchange="licCurrentMdlItems[${idx}].notes = this.value.trim()" placeholder="Remarks..." style="width:100%;padding:5px 8px;border:1px solid var(--gray-300);border-radius:4px;font-size:11px;">
      </td>
      <td style="padding:8px 12px;text-align:center;">
        <button type="button" onclick="licRemoveMdlItem(${idx})" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;" title="Remove row"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
}

function licRemoveMdlItem(idx){
  licCurrentMdlItems.splice(idx, 1);
  licRenderMdlItems();
}

async function licMdlStoreChanged(){
  const storeId = document.getElementById('lic-mdl-store').value;
  if(!licCurrentMdlItems.length) return;

  // Refresh stock levels for current items
  for(let it of licCurrentMdlItems){
    try{
      const r = await fetch(licScAPI + '&action=inventory_items&q=' + encodeURIComponent(it.item_code || it.item_name) + '&store_id=' + storeId);
      const j = await r.json();
      if(j.success && j.data && j.data.length){
        const match = j.data.find(x => x.id == it.item_id);
        if(match) it.store_stock = parseFloat(match.store_stock || 0);
      }
    }catch(e){}
  }
  licRenderMdlItems();
}

// Extra item search autocomplete in modal
function licExtraSearchDebounce(){
  clearTimeout(licExtraSearchTimer);
  licExtraSearchTimer = setTimeout(licRunExtraSearch, 250);
}

async function licRunExtraSearch(){
  const q = document.getElementById('lic-extra-search').value.trim();
  const dropdown = document.getElementById('lic-extra-dropdown');
  const storeId = document.getElementById('lic-mdl-store').value || (licStores[0] ? licStores[0].id : 0);

  if(q.length < 1){
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
    return;
  }

  dropdown.style.display = 'block';
  dropdown.innerHTML = '<div style="padding:8px;text-align:center;color:var(--gray-400);font-size:11px;"><i class="fas fa-spinner fa-spin"></i> Searching items...</div>';

  try{
    const r = await fetch(licScAPI + '&action=inventory_items&q=' + encodeURIComponent(q) + '&store_id=' + storeId);
    const j = await r.json();
    const items = (j.success && j.data) ? j.data : [];

    if(!items.length){
      dropdown.innerHTML = '<div style="padding:8px;text-align:center;color:var(--gray-400);font-size:11px;">No matching items found.</div>';
      return;
    }

    dropdown.innerHTML = items.map(it => {
      const stock = parseFloat(it.store_stock || 0);
      return `<div onclick='licSelectExtraItem(${JSON.stringify(it).replace(/'/g,"&#39;")})' style="padding:6px 10px;border-bottom:1px solid var(--gray-100);cursor:pointer;font-size:11px;display:flex;justify-content:space-between;align-items:center;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background=''">
        <div>
          <div style="font-weight:600;color:var(--gray-900);">${esc(it.name)}</div>
          <div style="font-size:9px;color:var(--gray-500);font-family:monospace;">${esc(it.item_code||'')}</div>
        </div>
        <div style="font-weight:600;color:${stock>0?'#059669':'#dc2626'};">Stock: ${stock}</div>
      </div>`;
    }).join('');
  }catch(e){
    dropdown.innerHTML = '<div style="padding:8px;text-align:center;color:#dc2626;font-size:11px;">Search error</div>';
  }
}

function licSelectExtraItem(it){
  document.getElementById('lic-extra-selected-item-id').value = it.id;
  document.getElementById('lic-extra-selected-item-name').value = it.name;
  document.getElementById('lic-extra-selected-item-code').value = it.item_code || '';
  document.getElementById('lic-extra-selected-item-uom').value = it.uom_abbr || 'pcs';
  document.getElementById('lic-extra-selected-item-stock').value = it.store_stock || 0;
  document.getElementById('lic-extra-search').value = it.name;
  document.getElementById('lic-extra-dropdown').style.display = 'none';
}

function licAddExtraRow(){
  const itemId = document.getElementById('lic-extra-selected-item-id').value;
  const name = document.getElementById('lic-extra-selected-item-name').value;
  const code = document.getElementById('lic-extra-selected-item-code').value;
  const uom = document.getElementById('lic-extra-selected-item-uom').value;
  const stock = parseFloat(document.getElementById('lic-extra-selected-item-stock').value) || 0;
  const qty = parseFloat(document.getElementById('lic-extra-qty').value) || 1.0;

  if(!itemId){ alert('Please search and select an inventory item first.'); return; }
  if(qty <= 0){ alert('Quantity must be greater than 0.'); return; }

  // Check if item already in table
  const existing = licCurrentMdlItems.find(x => x.item_id == itemId);
  if(existing){
    existing.quantity += qty;
  } else {
    licCurrentMdlItems.push({
      item_id: parseInt(itemId),
      item_name: name,
      item_code: code,
      quantity: qty,
      uom_abbr: uom,
      store_stock: stock,
      notes: ''
    });
  }

  // Clear inputs
  document.getElementById('lic-extra-search').value = '';
  document.getElementById('lic-extra-selected-item-id').value = '';
  document.getElementById('lic-extra-qty').value = '1.0';

  licRenderMdlItems();
}

// Close extra dropdown when clicked outside
document.addEventListener('click', function(e){
  const dd = document.getElementById('lic-extra-dropdown');
  const inp = document.getElementById('lic-extra-search');
  if(dd && inp && !dd.contains(e.target) && e.target !== inp){
    dd.style.display = 'none';
  }
});

// Submit consumption confirmation
async function licSubmitConfirm(){
  const orderId = document.getElementById('lic-mdl-order-id-input').value;
  const serviceId = document.getElementById('lic-mdl-service-id-input').value || null;
  const visitId = document.getElementById('lic-mdl-visit-id-input').value || null;
  const patientId = document.getElementById('lic-mdl-patient-id-input').value || null;
  const storeId = document.getElementById('lic-mdl-store').value || (licStores[0] ? licStores[0].id : 0);
  const generalNotes = document.getElementById('lic-mdl-notes').value.trim();
  const btn = document.getElementById('lic-submit-btn');

  if(!licCurrentMdlItems.length){
    alert('Please add at least one consumable item to deduct from stock.');
    return;
  }

  // Validate quantities
  for(let it of licCurrentMdlItems){
    if(it.quantity <= 0){
      alert(`Invalid quantity for ${it.item_name}. Must be greater than 0.`);
      return;
    }
  }

  if(!confirm('Confirm consumption and deduct stock quantities now? This will create inventory stock movements.')){
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deducting Stock...';

  try{
    const payload = {
      action: 'confirm_lab_consumption',
      order_id: parseInt(orderId),
      service_id: serviceId ? parseInt(serviceId) : null,
      visit_id: visitId ? parseInt(visitId) : null,
      patient_id: patientId ? parseInt(patientId) : null,
      store_id: parseInt(storeId),
      items: licCurrentMdlItems.map(it => ({
        item_id: it.item_id,
        quantity: it.quantity,
        notes: it.notes || ''
      })),
      notes: generalNotes
    };

    const r = await fetch(licAPI, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });
    const j = await r.json();

    if(j.success){
      alert('✓ Consumable stock quantity deducted and confirmed successfully!');
      licCloseConfirmModal();
      licLoad();
      licLoadHistory();
    } else {
      alert('Error deducting stock: ' + j.message);
    }
  }catch(e){
    alert('Failed: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check-double"></i> Confirm & Deduct from Stock';
  }
}

// View confirmed consumption modal
async function licOpenViewModal(orderId){
  const modal = document.getElementById('lic-view-modal');
  const content = document.getElementById('lic-view-modal-content');
  content.innerHTML = '<div style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading details...</div>';
  modal.style.display = 'flex';

  try{
    const r = await fetch(licAPI + '&action=lab_order_consumables&order_id=' + orderId);
    const j = await r.json();
    if(!j.success){ content.innerHTML = '<div style="color:#dc2626;padding:20px;">' + esc(j.message) + '</div>'; return; }

    const order = j.data.order || {};
    const records = j.data.consumed_records || [];

    let html = `
      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;">
        <div style="font-weight:700;font-size:14px;color:var(--gray-900);">${esc(order.description)}</div>
        <div style="color:var(--gray-600);margin-top:2px;">Patient: <strong>${esc(order.patient_name)}</strong> (${esc(order.patient_no||'')}) &bull; Order #${order.id}</div>
      </div>
      <h4 style="font-size:13px;font-weight:700;color:var(--gray-800);margin-bottom:8px;">Deducted Consumables:</h4>
      <table style="width:100%;border-collapse:collapse;font-size:12px;border:1px solid var(--gray-200);border-radius:6px;">
        <thead><tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
          <th style="padding:8px 10px;text-align:left;">Item</th>
          <th style="padding:8px 10px;text-align:center;">Qty Deducted</th>
          <th style="padding:8px 10px;text-align:left;">Store</th>
          <th style="padding:8px 10px;text-align:left;">Deducted By</th>
          <th style="padding:8px 10px;text-align:left;">Date & Time</th>
        </tr></thead><tbody>`;

    if(!records.length){
      html += '<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--gray-400);">No confirmed records.</td></tr>';
    } else {
      records.forEach(rc => {
        html += `<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:8px 10px;"><strong>${esc(rc.item_name)}</strong> <span style="font-family:monospace;font-size:10px;color:var(--gray-500);">${esc(rc.item_code||'')}</span></td>
          <td style="padding:8px 10px;text-align:center;font-weight:700;color:#059669;">${parseFloat(rc.quantity)} ${esc(rc.uom_abbr||'pcs')}</td>
          <td style="padding:8px 10px;">${esc(rc.store_name||'Store #'+rc.store_id)}</td>
          <td style="padding:8px 10px;">${esc(rc.consumed_by_name||'Technician')}</td>
          <td style="padding:8px 10px;color:var(--gray-500);">${esc(rc.consumed_at||'')}</td>
        </tr>`;
      });
    }

    html += '</tbody></table>';
    content.innerHTML = html;
  }catch(e){
    content.innerHTML = '<div style="color:#dc2626;padding:20px;">Error: ' + esc(e.message) + '</div>';
  }
}

// Reverse consumption modal/action
async function licReverseModal(orderId){
  // Fetch consumption records for this order first
  try{
    const r = await fetch(licAPI + '&action=lab_order_consumables&order_id=' + orderId);
    const j = await r.json();
    if(!j.success || !j.data.consumed_records || !j.data.consumed_records.length){
      alert('No active consumption records found for this order.');
      return;
    }

    const records = j.data.consumed_records;
    const itemsSummary = records.map(rc => `${rc.item_name} (${rc.quantity})`).join(', ');

    const reason = prompt(`Reverse stock deduction for Order #${orderId}?\n\nItems to restore: ${itemsSummary}\n\nPlease enter reason for reversal:`, 'Test cancelled / duplicate deduction');
    if(!reason) return;

    for(let rc of records){
      const revResp = await fetch(licAPI, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          action: 'reverse_lab_consumption',
          consumption_id: rc.id,
          reason: reason
        })
      });
      const revJson = await revResp.json();
      if(!revJson.success){
        alert('Failed to reverse item ' + rc.item_name + ': ' + revJson.message);
        return;
      }
    }

    alert('✓ Consumption reversed and stock restored successfully!');
    licLoad();
    licLoadHistory();
  }catch(e){
    alert('Failed to reverse consumption: ' + e.message);
  }
}

// Load recent stock deduction history audit log
async function licLoadHistory(){
  const tbody = document.getElementById('lic-history-tbody');
  try{
    const r = await fetch(licAPI + '&action=lab_consumption_history&limit=25');
    const j = await r.json();
    const list = (j.success && j.data) ? j.data : [];

    if(!list.length){
      tbody.innerHTML = '<tr><td colspan="8" style="padding:24px;text-align:center;color:var(--gray-400);">No stock deductions recorded yet.</td></tr>';
      return;
    }

    tbody.innerHTML = list.map(rc => {
      const isReversed = rc.status === 'reversed';
      const statusBadge = isReversed
        ? '<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">Reversed</span>'
        : '<span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;">Deducted</span>';

      return `<tr style="border-bottom:1px solid var(--gray-100);${isReversed?'opacity:0.6;background:#fef2f2;':''}">
        <td style="padding:8px 10px;color:var(--gray-600);">${esc(rc.consumed_at ? rc.consumed_at.substring(0,16) : '—')}</td>
        <td style="padding:8px 10px;font-weight:600;">${esc(rc.patient_name||'—')} <span style="font-size:10px;color:var(--gray-500);">${esc(rc.patient_no||'')}</span></td>
        <td style="padding:8px 10px;">${esc(rc.test_name||'Order #'+rc.order_id)}</td>
        <td style="padding:8px 10px;"><strong>${esc(rc.item_name)}</strong> <span style="font-family:monospace;font-size:10px;color:var(--gray-500);">${esc(rc.item_code||'')}</span></td>
        <td style="padding:8px 10px;text-align:center;font-weight:700;color:#059669;">${parseFloat(rc.quantity)} ${esc(rc.uom_abbr||'pcs')}</td>
        <td style="padding:8px 10px;font-size:11px;color:var(--gray-600);">${esc(rc.store_name||'Store #'+rc.store_id)}</td>
        <td style="padding:8px 10px;font-size:11px;color:var(--gray-600);">${esc(rc.consumed_by_name||'Technician')}</td>
        <td style="padding:8px 10px;text-align:center;">${statusBadge}</td>
      </tr>`;
    }).join('');
  }catch(e){
    tbody.innerHTML = '<tr><td colspan="8" style="padding:20px;text-align:center;color:#dc2626;">Failed to load audit history</td></tr>';
  }
}

const _initLicSearch = new URLSearchParams(window.location.search).get('search');
if(_initLicSearch && document.getElementById('lic-search')){
  document.getElementById('lic-search').value = _initLicSearch;
}
licInit();
</script>

<!-- ============================================
PATHOLOGY
============================================ -->
<?php elseif ($subModule == 'pathology'): ?>

<?php if ($action == 'add'): ?>
<!-- PATHOLOGY FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-microscope" style="color: var(--primary-color);"></i> Pathology Report
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Patient <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Patient</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Specimen Type <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., Tissue, Biopsy" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Gross Description
                </label>
                <textarea rows="2" placeholder="Enter gross description" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Microscopic Description <span style="color: var(--danger-color);">*</span>
                </label>
                <textarea rows="3" placeholder="Enter microscopic description" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Diagnosis <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter diagnosis" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Pathologist <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Pathologist</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Report Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Comments / Recommendations
                </label>
                <textarea rows="2" placeholder="Additional comments" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save Report
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- PATHOLOGY LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Pathology Reports</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No pathology reports found. Create a report to get started.</p>
        </div>
        <button onclick="window.location.href='lab.php?module=lab&sub=pathology&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Report
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-microscope"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Pathology Reports</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create pathology reports for tissue and biopsy analysis.</p>
        <button onclick="window.location.href='lab.php?module=lab&sub=pathology&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Report
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
MICROBIOLOGY
============================================ -->
<?php elseif ($subModule == 'microbiology'): ?>

<?php if ($action == 'add'): ?>
<!-- MICROBIOLOGY FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-bacteria" style="color: var(--warning-color);"></i> Microbiology Report
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Patient <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Patient</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Specimen Type <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., Blood, Urine, Sputum" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Organism Isolated <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., E. coli, S. aureus" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Culture Method
                </label>
                <input type="text" placeholder="e.g., Blood Agar, MacConkey" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Sensitivity Results
                </label>
                <textarea rows="2" placeholder="Enter antibiotic sensitivity results" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Report Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="negative">Negative</option>
                    <option value="positive">Positive</option>
                    <option value="pending">Pending</option>
                    <option value="contaminated">Contaminated</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Clinical Interpretation
                </label>
                <textarea rows="2" placeholder="Enter clinical interpretation" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save Report
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- MICROBIOLOGY LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Microbiology Reports</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No microbiology reports found. Create a report to get started.</p>
        </div>
        <button onclick="window.location.href='lab.php?module=lab&sub=microbiology&action=add'" style="padding: 10px 20px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Report
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-bacteria"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Microbiology Reports</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create microbiology reports for culture and sensitivity testing.</p>
        <button onclick="window.location.href='lab.php?module=lab&sub=microbiology&action=add'" style="padding: 12px 24px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Report
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
BLOOD BANK
============================================ -->
<?php elseif ($subModule == 'blood-bank'): ?>

<?php if ($action == 'add'): ?>
<!-- BLOOD BANK FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-hand-holding-heart" style="color: var(--danger-color);"></i> Blood Bank Record
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Donor / Patient <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Donor/Patient</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Blood Type <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Blood Type</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Rh Factor <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="positive">Positive (+)</option>
                    <option value="negative">Negative (-)</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Units <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter number of units" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Expiry Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="available">Available</option>
                    <option value="issued">Issued</option>
                    <option value="expired">Expired</option>
                    <option value="quarantined">Quarantined</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Storage Location
                </label>
                <input type="text" placeholder="e.g., Fridge A1" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Notes
                </label>
                <textarea rows="2" placeholder="Additional notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--danger-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save Record
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- BLOOD BANK LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Blood Bank Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--danger-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Total Units</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Available</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">A+</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">O- (Universal)</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--danger-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Expiring Soon</div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Blood Bank Inventory</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No blood bank records found. Add a record to get started.</p>
        </div>
        <button onclick="window.location.href='lab.php?module=lab&sub=blood-bank&action=add'" style="padding: 10px 20px; background: var(--danger-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Add Record
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-hand-holding-heart"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Blood Bank Records</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Add blood units to start managing the blood bank inventory.</p>
        <button onclick="window.location.href='lab.php?module=lab&sub=blood-bank&action=add'" style="padding: 12px 24px; background: var(--danger-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Record
        </button>
    </div>

    <!-- Blood Bank Alert -->
    <div style="margin-top: 24px; padding: 16px; background: #fef3c7; border-radius: 8px; border-left: 4px solid var(--warning-color);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-exclamation-triangle" style="color: var(--warning-color); font-size: 20px;"></i>
            <div>
                <div style="font-weight: 600; color: var(--gray-900);">Blood Bank Status</div>
                <div style="font-size: 13px; color: var(--gray-600);">No blood units expiring soon. All units are within valid date range.</div>
            </div>
            <button style="margin-left: auto; padding: 6px 16px; background: var(--warning-color); color: white; border: none; border-radius: 6px; cursor: pointer;">
                View Details
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($subModule == 'statistics'): ?>
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
  <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px;">
    <label style="font-size:12px;font-weight:600;color:var(--gray-600);">From<br><input type="date" id="st-from" onchange="stLoad()" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;"></label>
    <label style="font-size:12px;font-weight:600;color:var(--gray-600);">To<br><input type="date" id="st-to" onchange="stLoad()" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;"></label>
    <label style="font-size:12px;font-weight:600;color:var(--gray-600);">Month<br><input type="month" id="st-month" onchange="stMonth()" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;"></label>
    <label style="font-size:12px;font-weight:600;color:var(--gray-600);">Group by<br><select id="st-group" onchange="stLoad()" style="padding:8px;border:1px solid var(--gray-200);border-radius:6px;background:white;"><option value="test">Test</option><option value="day">Day</option><option value="month">Month</option></select></label>
    <button onclick="stExport()" style="padding:9px 16px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;"><i class="fas fa-file-excel"></i> Export Excel</button>
  </div>
  <div id="st-body" style="overflow-x:auto;"></div>
</div>
<script>
let stRows = [];
const stIso = d => { const z = new Date(d.getTime() - d.getTimezoneOffset() * 60000); return z.toISOString().substring(0,10); };
(function(){ const n = new Date(); document.getElementById('st-from').value = stIso(new Date(n.getFullYear(), n.getMonth(), 1)); document.getElementById('st-to').value = stIso(n); })();
// A month pick sets the date range to that whole month
function stMonth(){
  const m = document.getElementById('st-month').value; if(!m) return;
  const [y, mo] = m.split('-').map(Number);
  document.getElementById('st-from').value = m + '-01';
  document.getElementById('st-to').value = m + '-' + String(new Date(y, mo, 0).getDate()).padStart(2, '0');
  stLoad();
}
async function stLoad(){
  const q = new URLSearchParams({endpoint:'lab/lab', action:'statistics', date_from:document.getElementById('st-from').value, date_to:document.getElementById('st-to').value, group:document.getElementById('st-group').value});
  const body = document.getElementById('st-body');
  try{
    const j = await (await fetch(labBasePath + '/api.php?' + q)).json();
    if(!j.success) throw new Error(j.message);
    stRows = j.data || [];
    const cols = ['total','completed','in_progress','pending','cancelled'];
    const sum = k => stRows.reduce((a, r) => a + (+r[k] || 0), 0);
    const th = 'padding:8px 10px;text-align:center;border-bottom:1px solid var(--gray-200);font-size:12px;';
    const first = {test:'Test',day:'Date',month:'Month'}[document.getElementById('st-group').value];
    body.innerHTML = stRows.length ? `<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);"><th style="${th}text-align:left;">${first}</th><th style="${th}">Total</th><th style="${th}">Completed</th><th style="${th}">In Progress</th><th style="${th}">Pending</th><th style="${th}">Cancelled</th></tr></thead><tbody>`
      + stRows.map(r => `<tr><td style="padding:7px 10px;border-bottom:1px solid var(--gray-100);">${labEsc(r.label)}</td>` + cols.map(k => `<td style="padding:7px 10px;text-align:center;border-bottom:1px solid var(--gray-100);">${r[k]}</td>`).join('') + '</tr>').join('')
      + `<tr style="font-weight:700;background:var(--gray-50);"><td style="padding:8px 10px;">Total</td>` + cols.map(k => `<td style="padding:8px 10px;text-align:center;">${sum(k)}</td>`).join('') + '</tr></tbody></table>'
      : '<div style="padding:30px;text-align:center;color:var(--gray-400);">No lab tests in this period.</div>';
  }catch(e){ body.innerHTML = '<div style="padding:20px;color:var(--danger-color);">Error: ' + labEsc(e.message) + '</div>'; }
}
function stExport(){
  const first = {test:'Test',day:'Date',month:'Month'}[document.getElementById('st-group').value];
  labDownloadExcel('Lab_Statistics_' + document.getElementById('st-from').value + '_to_' + document.getElementById('st-to').value, 'Statistics',
    [first, 'Total', 'Completed', 'In Progress', 'Pending', 'Cancelled'],
    stRows.map(r => [r.label, +r.total, +r.completed, +r.in_progress, +r.pending, +r.cancelled]));
}
stLoad();
</script>

<?php elseif ($subModule == 'test-settings'): ?>
<!-- ============================================
     LABORATORY TEST SETTINGS
============================================ -->
<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:flex-start;">

  <!-- Left: Speciality List -->
  <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
    <div style="padding:14px 16px;border-bottom:1px solid var(--gray-200);font-size:14px;font-weight:600;color:var(--gray-800);">
      <i class="fas fa-folder" style="color:var(--primary-color);"></i> Test Categories
    </div>
    <div id="lts-specialities" style="max-height:600px;overflow-y:auto;">
      <div style="padding:20px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
  </div>

  <!-- Right: Tests list / Parameters -->
  <div>
    <!-- Tests in selected speciality -->
    <div id="lts-tests-panel" style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="padding:14px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
        <h3 style="font-size:14px;font-weight:600;color:var(--gray-800);" id="lts-tests-title"><i class="fas fa-flask" style="color:var(--primary-color);"></i> Select a category</h3>
        <input type="text" id="lts-test-search" placeholder="Search tests..." oninput="ltsFilterTests()" style="padding:6px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;width:180px;display:none;">
      </div>
      <div id="lts-tests-list" style="max-height:300px;overflow-y:auto;padding:8px;">
        <div style="padding:30px;text-align:center;color:var(--gray-400);font-size:13px;">Select a category from the left to see tests</div>
      </div>
    </div>

    <!-- Parameters for selected test -->
    <div id="lts-params-panel" style="display:none;background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-top:16px;">
      <div style="padding:14px 16px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
        <h3 style="font-size:14px;font-weight:600;color:var(--gray-800);" id="lts-params-title"><i class="fas fa-sliders-h" style="color:#7c3aed;"></i> Parameters</h3>
        <button onclick="ltsOpenParamModal()" style="padding:6px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:500;"><i class="fas fa-plus"></i> Add Parameter</button>
      </div>
      <div id="lts-params-list" style="padding:12px;">
        <div style="text-align:center;color:var(--gray-400);padding:20px;font-size:13px;">No parameters configured</div>
      </div>
    </div>
  </div>
</div>

<!-- Parameter Add/Edit Modal -->
<div id="lts-param-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:450px;max-width:90vw;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h3 style="font-size:16px;font-weight:600;" id="lts-modal-title"><i class="fas fa-sliders-h" style="color:#7c3aed;margin-right:8px;"></i>Add Parameter</h3>
      <button onclick="ltsCloseParamModal()" style="border:none;background:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <input type="hidden" id="lts-param-edit-id" value="">
    <div style="margin-bottom:14px;">
      <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Parameter Name <span style="color:var(--danger-color);">*</span></label>
      <input type="text" id="lts-param-name" placeholder="e.g. Hemoglobin, WBC, RBC, Glucose..." style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
    </div>
    <div style="margin-bottom:14px;">
      <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Result Input Type</label>
      <select id="lts-param-type" onchange="ltsOnTypeChange()" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
        <option value="numeric">Numeric (unit &amp; normal range)</option>
        <option value="pos_neg">Positive / Negative</option>
        <option value="select">Dropdown (single choice)</option>
        <option value="multi_select">Dropdown (multiple choice)</option>
        <option value="text">Free text / observation</option>
      </select>
    </div>
    <div id="lts-section-numeric">
    <div style="margin-bottom:14px;">
      <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Unit</label>
      <input type="text" id="lts-param-unit" placeholder="e.g. g/dL, cells/μL, mmol/L, %" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
      <div>
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Normal Range MIN</label>
        <input type="number" step="any" id="lts-param-min" placeholder="e.g. 12.0" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
      </div>
      <div>
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Normal Range MAX</label>
        <input type="number" step="any" id="lts-param-max" placeholder="e.g. 17.5" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
      </div>
    </div>
    </div>
    <div id="lts-section-select" style="display:none;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;padding:14px;margin-bottom:16px;">
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
        <button type="button" onclick="ltsApplyPreset('blood_groups')" style="padding:4px 8px;background:white;border:1px solid #c4b5fd;border-radius:4px;font-size:11px;cursor:pointer;color:#5b21b6;">Blood Groups</button>
        <button type="button" onclick="ltsApplyPreset('reactive_non')" style="padding:4px 8px;background:white;border:1px solid #c4b5fd;border-radius:4px;font-size:11px;cursor:pointer;color:#5b21b6;">Non-Reactive / Reactive</button>
        <button type="button" onclick="ltsApplyPreset('dipstick_grades')" style="padding:4px 8px;background:white;border:1px solid #c4b5fd;border-radius:4px;font-size:11px;cursor:pointer;color:#5b21b6;">Dipstick (Neg, Trace, 1+...)</button>
        <button type="button" onclick="ltsApplyPreset('clarity')" style="padding:4px 8px;background:white;border:1px solid #c4b5fd;border-radius:4px;font-size:11px;cursor:pointer;color:#5b21b6;">Clarity</button>
        <button type="button" onclick="ltsApplyPreset('color')" style="padding:4px 8px;background:white;border:1px solid #c4b5fd;border-radius:4px;font-size:11px;cursor:pointer;color:#5b21b6;">Color</button>
        <button type="button" onclick="ltsApplyPreset('quantity')" style="padding:4px 8px;background:white;border:1px solid #c4b5fd;border-radius:4px;font-size:11px;cursor:pointer;color:#5b21b6;">Quantity (Few, Mod, Many...)</button>
      </div>
      <label style="display:block;font-size:12px;font-weight:600;color:#5b21b6;margin-bottom:4px;">Options (comma-separated) *</label>
      <textarea id="lts-param-options" rows="3" placeholder="e.g. A+, A-, B+, B-, AB+, AB-, O+, O-" style="width:100%;padding:8px 12px;border:1px solid #c4b5fd;border-radius:6px;font-size:13px;margin-bottom:8px;"></textarea>
      <label style="display:block;font-size:12px;font-weight:600;color:#5b21b6;margin-bottom:4px;">Normal value (optional)</label>
      <input type="text" id="lts-param-select-normal" placeholder="e.g. Negative" style="width:100%;padding:8px 12px;border:1px solid #c4b5fd;border-radius:6px;font-size:13px;">
    </div>
    <div style="display:flex;gap:10px;">
      <button onclick="ltsSaveParamFromModal()" style="flex:1;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;">
        <i class="fas fa-save"></i> Save Parameter
      </button>
      <button onclick="ltsCloseParamModal()" style="padding:11px 20px;background:var(--gray-100);color:var(--gray-700);border:none;border-radius:8px;font-weight:500;cursor:pointer;font-size:14px;">Cancel</button>
    </div>
  </div>
</div>

<!-- Lab Order Audit Log Modal -->
<div id="lab-audit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;padding:24px;width:100%;max-width:750px;max-height:85vh;overflow-y:auto;position:relative;">
    <button onclick="document.getElementById('lab-audit-modal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
    <h3 style="font-size:16px;font-weight:600;margin-bottom:14px;color:var(--gray-900);display:flex;align-items:center;gap:8px;">
      <i class="fas fa-history" style="color:var(--primary-color);"></i>
      <span>Lab Order Audit Logs: <span id="lab-audit-title" style="color:var(--primary-color);"></span></span>
    </h3>
    <div id="lab-audit-modal-body" style="font-size:13px;">
      <div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading audit trail...</div>
    </div>
  </div>
</div>

<script>
async function labShowAuditModal(orderId, orderTitle){
  document.getElementById('lab-audit-title').textContent = orderTitle || ('#'+orderId);
  const body = document.getElementById('lab-audit-modal-body');
  body.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading audit trail...</div>';
  document.getElementById('lab-audit-modal').style.display = 'flex';
  try {
    const r = await fetch(labBasePath + '/api.php?endpoint=edit-requests&action=audit_log&entity_type=lab_result&entity_id=' + orderId);
    const j = await r.json();
    if(!j.success || !j.data || !j.data.length){
      body.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-info-circle" style="font-size:24px;display:block;margin-bottom:8px;"></i>No audit log entries recorded for this test yet.</div>';
      return;
    }
    body.innerHTML = `<div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead>
          <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
            <th style="padding:8px 12px;text-align:left;">Date / Time</th>
            <th style="padding:8px 12px;text-align:left;">Action / Reason</th>
            <th style="padding:8px 12px;text-align:left;">Details</th>
            <th style="padding:8px 12px;text-align:left;">Recorded / Edited By</th>
          </tr>
        </thead>
        <tbody>
          ${j.data.map(log => `
            <tr style="border-bottom:1px solid var(--gray-100);">
              <td style="padding:8px 12px;white-space:nowrap;color:var(--gray-600);font-size:11px;">${log.edited_at || '—'}</td>
              <td style="padding:8px 12px;font-weight:500;">${esc(log.reason || 'Lab Result Entry')}</td>
              <td style="padding:8px 12px;font-family:monospace;font-size:11px;color:#166534;white-space:pre-wrap;">${esc(log.changed_fields || '—')}</td>
              <td style="padding:8px 12px;font-weight:600;color:var(--primary-color);">${esc(log.edited_by_name || 'Staff')}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>`;
  } catch(e){
    body.innerHTML = '<div style="text-align:center;padding:20px;color:#dc2626;">Failed to load audit trail: ' + esc(e.message) + '</div>';
  }
}

const ltsAPI=labBasePath+'/api.php?endpoint=service-catalog';
const ltsParamAPI=labBasePath+'/api.php?endpoint=patients/billing&action=lab_params';
let ltsAllTests=[], ltsCurrentSpeciality='', ltsCurrentServiceId=null;

// Load specialities from service catalog (lab-related)
async function ltsLoadSpecialities(){
  try{
    const r=await fetch(labBasePath+'/api.php?endpoint=patients/billing&action=services&category=lab');
    const j=await r.json();
    if(!j.success) return;
    ltsAllTests=j.data||[];
    // Group by category (speciality)
    const groups={};
    ltsAllTests.forEach(t=>{const cat=t.category||'Other';if(!groups[cat])groups[cat]=[];groups[cat].push(t);});
    const container=document.getElementById('lts-specialities');
    container.innerHTML=Object.keys(groups).sort().map(cat=>`
      <div onclick="ltsSelectSpeciality('${cat.replace(/'/g,"\\'")}')" style="padding:10px 16px;cursor:pointer;border-bottom:1px solid var(--gray-100);font-size:13px;display:flex;justify-content:space-between;align-items:center;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <span>${cat}</span>
        <span style="background:var(--gray-100);padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;color:var(--gray-600);">${groups[cat].length}</span>
      </div>
    `).join('');
  }catch(e){document.getElementById('lts-specialities').innerHTML='<div style="padding:20px;color:#dc2626;">Error loading</div>';}
}

function ltsSelectSpeciality(cat){
  ltsCurrentSpeciality=cat;
  document.getElementById('lts-tests-title').innerHTML='<i class="fas fa-flask" style="color:var(--primary-color);"></i> '+cat;
  document.getElementById('lts-test-search').style.display='inline-block';
  document.getElementById('lts-test-search').value='';
  document.getElementById('lts-params-panel').style.display='none';
  ltsRenderTests(cat);
}

function ltsRenderTests(cat){
  const tests=ltsAllTests.filter(t=>(t.category||'')==cat);
  const container=document.getElementById('lts-tests-list');
  if(!tests.length){container.innerHTML='<div style="padding:20px;text-align:center;color:var(--gray-400);">No tests in this category</div>';return;}
  container.innerHTML=tests.map(t=>`
    <div onclick="ltsSelectTest(${t.id},'${(t.name||'').replace(/'/g,"\\'")}')" style="padding:8px 12px;cursor:pointer;border-radius:6px;margin-bottom:4px;font-size:13px;border:1px solid var(--gray-100);" onmouseover="this.style.background='#eff6ff';this.style.borderColor='var(--primary-color)'" onmouseout="this.style.background='';this.style.borderColor='var(--gray-100)'">
      <div style="font-weight:500;color:var(--gray-900);">${t.name||'—'}</div>
      <div style="font-size:11px;color:var(--gray-500);">${t.code||''} | ${parseFloat(t.unit_price||0).toLocaleString()} RWF</div>
    </div>
  `).join('');
}

function ltsFilterTests(){
  const q=document.getElementById('lts-test-search').value.toLowerCase();
  const tests=ltsAllTests.filter(t=>(t.category||'')==ltsCurrentSpeciality && (t.name||'').toLowerCase().includes(q));
  const container=document.getElementById('lts-tests-list');
  container.innerHTML=tests.map(t=>`
    <div onclick="ltsSelectTest(${t.id},'${(t.name||'').replace(/'/g,"\\'")}')" style="padding:8px 12px;cursor:pointer;border-radius:6px;margin-bottom:4px;font-size:13px;border:1px solid var(--gray-100);" onmouseover="this.style.background='#eff6ff';this.style.borderColor='var(--primary-color)'" onmouseout="this.style.background='';this.style.borderColor='var(--gray-100)'">
      <div style="font-weight:500;color:var(--gray-900);">${t.name||'—'}</div>
      <div style="font-size:11px;color:var(--gray-500);">${t.code||''}</div>
    </div>
  `).join('');
}

async function ltsSelectTest(serviceId, testName){
  ltsCurrentServiceId=serviceId;
  document.getElementById('lts-params-panel').style.display='block';
  document.getElementById('lts-params-title').innerHTML='<i class="fas fa-sliders-h" style="color:#7c3aed;"></i> '+testName+' — Parameters';
  await ltsLoadParams(serviceId);
}

async function ltsLoadParams(serviceId){
  const container=document.getElementById('lts-params-list');
  container.innerHTML='<div style="text-align:center;padding:15px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div>';
  try{
    const r=await fetch(ltsParamAPI+'&service_id='+serviceId);
    const j=await r.json();
    if(!j.success){container.innerHTML='<div style="padding:15px;color:#dc2626;">Error: '+j.message+'</div>';return;}
    const params=j.data||[];
    window._ltsParams=params;
    if(!params.length){
      container.innerHTML='<div style="text-align:center;color:var(--gray-400);padding:20px;font-size:13px;"><i class="fas fa-info-circle"></i> No parameters configured. Click "Add Parameter" to create one.</div>';
      return;
    }
    container.innerHTML=`<table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
        <th style="padding:8px 10px;text-align:left;">Test Name</th>
        <th style="padding:8px 10px;text-align:left;">Type</th>
        <th style="padding:8px 10px;text-align:left;">Unit</th>
        <th style="padding:8px 10px;text-align:center;">Normal Min</th>
        <th style="padding:8px 10px;text-align:center;">Normal Max</th>
        <th style="padding:8px 10px;text-align:center;">Actions</th>
      </tr></thead><tbody>
      ${params.map(p=>`<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;font-weight:500;">${p.test_name}</td>
        <td style="padding:8px 10px;color:var(--gray-500);font-size:12px;">${({numeric:'Numeric',pos_neg:'Pos/Neg',select:'Dropdown',multi_select:'Multi-select',text:'Text'})[p.param_type]||'Numeric'}</td>
        <td style="padding:8px 10px;color:var(--gray-600);">${p.unit||'—'}</td>
        <td style="padding:8px 10px;text-align:center;">${p.normal_min!=null?p.normal_min:'—'}</td>
        <td style="padding:8px 10px;text-align:center;">${p.normal_max!=null?p.normal_max:'—'}</td>
        <td style="padding:8px 10px;text-align:center;">
          <button onclick="ltsEditParam(${p.id})" style="background:none;border:none;color:var(--warning-color);cursor:pointer;font-size:14px;padding:2px 4px;"><i class="fas fa-edit"></i></button>
          <button onclick="ltsDeleteParam(${p.id})" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:14px;padding:2px 4px;"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`).join('')}
      </tbody></table>`;
  }catch(e){container.innerHTML='<div style="padding:15px;color:#dc2626;">Error</div>';}
}

function ltsOpenParamModal(editId){
  if(!ltsCurrentServiceId){alert('Select a test first');return;}
  document.getElementById('lts-param-edit-id').value='';
  document.getElementById('lts-param-name').value='';
  document.getElementById('lts-param-unit').value='';
  document.getElementById('lts-param-min').value='';
  document.getElementById('lts-param-max').value='';
  document.getElementById('lts-param-type').value='numeric';
  document.getElementById('lts-param-options').value='';
  document.getElementById('lts-param-select-normal').value='';
  ltsOnTypeChange();
  document.getElementById('lts-modal-title').innerHTML='<i class="fas fa-sliders-h" style="color:#7c3aed;margin-right:8px;"></i>Add Parameter';
  document.getElementById('lts-param-modal').style.display='flex';
}

function ltsEditParam(id){
  const param = window._ltsParams ? window._ltsParams.find(p=>p.id===id) : null;
  if(!param){alert('Parameter not found');return;}
  document.getElementById('lts-param-edit-id').value=id;
  document.getElementById('lts-param-name').value=param.test_name||'';
  document.getElementById('lts-param-unit').value=param.unit||'';
  document.getElementById('lts-param-min').value=param.normal_min!=null?param.normal_min:'';
  document.getElementById('lts-param-max').value=param.normal_max!=null?param.normal_max:'';
  document.getElementById('lts-param-type').value=param.param_type||'numeric';
  document.getElementById('lts-param-options').value=param.options||'';
  document.getElementById('lts-param-select-normal').value=param.normal_value||'';
  ltsOnTypeChange();
  document.getElementById('lts-modal-title').innerHTML='<i class="fas fa-edit" style="color:#d97706;margin-right:8px;"></i>Edit Parameter';
  document.getElementById('lts-param-modal').style.display='flex';
}

function ltsOnTypeChange(){
  const type = document.getElementById('lts-param-type').value;
  document.getElementById('lts-section-numeric').style.display = type === 'numeric' ? 'block' : 'none';
  document.getElementById('lts-section-select').style.display = (type === 'select' || type === 'multi_select') ? 'block' : 'none';
}

function ltsApplyPreset(key){
  const presets = {
    blood_groups: { options: 'A+, A-, B+, B-, AB+, AB-, O+, O-', normal: '' },
    reactive_non: { options: 'Non-Reactive, Reactive, Indeterminate', normal: 'Non-Reactive' },
    dipstick_grades: { options: 'Negative, Trace, 1+, 2+, 3+, 4+', normal: 'Negative' },
    clarity: { options: 'Clear, Slightly Turbid, Turbid, Cloudy', normal: 'Clear' },
    color: { options: 'Straw, Light Yellow, Dark Yellow, Amber, Red, Brown', normal: 'Light Yellow' },
    quantity: { options: 'Absent, Rare, Few, Moderate, Many, Abundant', normal: 'Absent' }
  };
  if(!presets[key]) return;
  document.getElementById('lts-param-options').value = presets[key].options;
  if(presets[key].normal) document.getElementById('lts-param-select-normal').value = presets[key].normal;
}

function ltsCloseParamModal(){
  document.getElementById('lts-param-modal').style.display='none';
}

async function ltsSaveParamFromModal(){
  const name=document.getElementById('lts-param-name').value.trim();
  if(!name){alert('Please enter parameter name');return;}
  const editId=document.getElementById('lts-param-edit-id').value;
  const paramType=document.getElementById('lts-param-type').value;
  const isNum=paramType==='numeric', isSel=paramType==='select'||paramType==='multi_select';
  const options=paramType==='pos_neg'?'Negative,Positive':(isSel?document.getElementById('lts-param-options').value.trim():'');
  if(isSel&&!options){alert('Please enter dropdown options separated by commas');return;}
  const data={
    service_id:ltsCurrentServiceId,
    test_name:name,
    param_type:paramType,
    unit:isNum?document.getElementById('lts-param-unit').value.trim():'',
    normal_min:isNum?(document.getElementById('lts-param-min').value||null):null,
    normal_max:isNum?(document.getElementById('lts-param-max').value||null):null,
    options:options,
    normal_value:paramType==='pos_neg'?'Negative':(isSel?document.getElementById('lts-param-select-normal').value.trim():'')
  };
  if(editId) data.id=parseInt(editId);
  try{
    const r=await fetch(ltsParamAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    const j=await r.json();
    if(j.success){ltsCloseParamModal();ltsLoadParams(ltsCurrentServiceId);}
    else alert('Failed: '+j.message);
  }catch(e){alert('Error: '+e.message);}
}


async function ltsDeleteParam(id){
  if(!confirm('Delete this parameter?')) return;
  try{
    const r=await fetch(ltsParamAPI,{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    const j=await r.json();
    if(j.success) ltsLoadParams(ltsCurrentServiceId);
    else alert('Failed: '+j.message);
  }catch(e){alert('Error: '+e.message);}
}

ltsLoadSpecialities();
</script>

<?php endif; ?>

<!-- Notification System Script -->
<script>
(function(){
const notifAPI = labBasePath+'/api.php?endpoint=notifications';
let notifDropdownOpen = false;
let notifLastUnread = 0;
let notifSoundPlayed = false;
let notifReminderInterval = null;

// --- Sound Mute Toggle (persisted in localStorage) ---
function notifIsMuted(){ return localStorage.getItem('medlink_notif_muted') === '1'; }
function notifUpdateMuteBtn(){
  const btn = document.getElementById('notif-mute-btn');
  if(!btn) return;
  const muted = notifIsMuted();
  btn.classList.toggle('muted', muted);
  btn.querySelector('i').className = muted ? 'fas fa-volume-mute' : 'fas fa-volume-up';
  btn.title = muted ? 'Unmute notification sounds' : 'Mute notification sounds';
}
window.notifToggleMute = function(){
  const muted = notifIsMuted();
  localStorage.setItem('medlink_notif_muted', muted ? '0' : '1');
  notifUpdateMuteBtn();
  if(!muted) notifStopReminder();
};
document.addEventListener('DOMContentLoaded', notifUpdateMuteBtn);

const notifAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
// browsers keep sound blocked until a click/key: unlock on the first one and replay a waiting alert
['click','keydown','touchstart'].forEach(e=>document.addEventListener(e,()=>notifAudioCtx.resume().then(()=>{ if(notifLastUnread>0) notifPlayReminderShort(); }),{once:true}));

function notifPlayTone(duration, frequency, type='sine', volume=0.4){
  if(notifIsMuted()) return;
  if(notifAudioCtx.state === 'suspended') notifAudioCtx.resume();
  const osc = notifAudioCtx.createOscillator();
  const gain = notifAudioCtx.createGain();
  osc.type = type;
  osc.frequency.value = frequency;
  gain.gain.value = volume;
  osc.connect(gain);
  gain.connect(notifAudioCtx.destination);
  osc.start();
  gain.gain.setValueAtTime(volume, notifAudioCtx.currentTime + duration - 0.1);
  gain.gain.linearRampToValueAtTime(0, notifAudioCtx.currentTime + duration);
  osc.stop(notifAudioCtx.currentTime + duration);
}

function notifPlayAlertLong(){
  const notes = [880, 1100, 880, 1100, 880, 1100, 880, 1100, 880, 1100];
  notes.forEach((freq, i) => { setTimeout(() => notifPlayTone(0.3, freq, 'sine', 0.35), i * 500); });
}

function notifPlayReminderShort(){
  notifPlayTone(0.15, 900, 'sine', 0.3);
  setTimeout(() => notifPlayTone(0.15, 1100, 'sine', 0.3), 200);
}

function notifStartReminder(){
  notifStopReminder();
  notifReminderInterval = setInterval(() => {
    if(notifLastUnread > 0) notifPlayReminderShort();
    else notifStopReminder();
  }, 60000);
}

function notifStopReminder(){
  if(notifReminderInterval){ clearInterval(notifReminderInterval); notifReminderInterval = null; }
}

window.notifToggleDropdown = function(){
  const dd = document.getElementById('notif-dropdown');
  notifDropdownOpen = !notifDropdownOpen;
  dd.classList.toggle('show', notifDropdownOpen);
  if(notifDropdownOpen) notifLoadList();
};

document.addEventListener('click', function(e){
  const wrap = document.getElementById('notif-bell-wrap');
  if(wrap && !wrap.contains(e.target)){
    document.getElementById('notif-dropdown').classList.remove('show');
    notifDropdownOpen = false;
  }
});

function notifTimeAgo(dateStr){
  const now = new Date();
  const d = new Date(dateStr);
  const diff = Math.floor((now - d) / 1000);
  if(diff < 60) return 'Just now';
  if(diff < 3600) return Math.floor(diff/60) + 'm ago';
  if(diff < 86400) return Math.floor(diff/3600) + 'h ago';
  return Math.floor(diff/86400) + 'd ago';
}

async function notifLoadList(){
  try{
    const r = await fetch(notifAPI + '&target_role=lab&limit=30');
    const j = await r.json();
    if(!j.success) return;
    const notifs = j.data.notifications || [];
    const unread = j.data.unread_count || 0;
    const badge = document.getElementById('notif-count');
    if(unread > 0){ badge.textContent = unread > 99 ? '99+' : unread; badge.style.display = 'flex'; }
    else { badge.style.display = 'none'; }
    notifLastUnread = unread;
    const container = document.getElementById('notif-list');
    if(!notifs.length){
      container.innerHTML = '<div class="notif-empty"><i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:var(--gray-300);"></i>No notifications</div>';
      return;
    }
    container.innerHTML = notifs.map(n => {
      const isUnread = !n.is_read;
      const icon = n.type === 'lab_request' ? 'fa-vial' : 'fa-flask';
      return `<div class="notif-item ${isUnread ? 'unread' : ''}" id="notif-item-${n.id}">
        <div class="notif-title"><i class="fas ${icon}" style="color:#2563eb;margin-right:6px;"></i>${n.title || 'Notification'}</div>
        <div class="notif-msg">${n.message || ''}</div>
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span class="notif-time">${notifTimeAgo(n.created_at)}</span>
          ${isUnread ? `<button class="notif-mark-btn" onclick="event.stopPropagation();notifMarkRead(${n.id})"><i class="fas fa-check"></i> Mark as read</button>` : '<span style="font-size:10px;color:#059669;"><i class="fas fa-check-double"></i> Read</span>'}
        </div>
        ${n.patient_name ? `<div style="font-size:11px;color:var(--gray-500);margin-top:4px;"><i class="fas fa-user"></i> ${n.patient_name} ${n.visit_no ? '('+n.visit_no+')' : ''}</div>` : ''}
      </div>`;
    }).join('');
  }catch(e){}
}

window.notifMarkRead = async function(id){
  try{
    await fetch(notifAPI, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'mark_read', id:id}) });
    const item = document.getElementById('notif-item-' + id);
    if(item) item.classList.remove('unread');
    notifLoadList();
  }catch(e){}
};

window.notifMarkAllRead = async function(){
  try{
    await fetch(notifAPI, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'mark_all_read', target_role:'lab'}) });
    notifLastUnread = 0; notifSoundPlayed = false; notifStopReminder();
    notifLoadList();
  }catch(e){}
};

async function notifToastNew(){
  try{
    const j = await (await fetch(notifAPI + '&target_role=lab&is_read=0&type=lab_request&limit=5')).json();
    ((j.data && j.data.notifications) || []).forEach(n => mlToast({key:'labreq'+n.id, title:n.title || 'New lab request', msg:(n.message || '') + (n.patient_name ? ' — ' + n.patient_name : ''), icon:'fa-vial'}));
  }catch(e){}
}

async function notifPoll(){
  try{
    const r = await fetch(notifAPI + '&target_role=lab&is_read=0&limit=1');
    const j = await r.json();
    if(j.success){
      const unread = j.data.unread_count || 0;
      const badge = document.getElementById('notif-count');
      if(unread > 0){
        badge.textContent = unread > 99 ? '99+' : unread;
        badge.style.display = 'flex';
        if(unread > notifLastUnread || (!notifSoundPlayed && unread > 0)){
          notifPlayAlertLong();
          notifToastNew();
          notifSoundPlayed = true;
          notifStartReminder();
        }
      } else {
        badge.style.display = 'none';
        notifSoundPlayed = false;
        notifStopReminder();
      }
      notifLastUnread = unread;
    }
  }catch(e){}
}

notifPoll();
setInterval(notifPoll, 15000);
})();
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>