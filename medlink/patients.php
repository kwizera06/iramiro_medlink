<?php
// public/patients.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'manage';

// Page title mapping
$subModuleTitles = [
    'registration'   => 'New Patient Registration',
    'manage'         => 'Manage Patients',
    'open-visit'     => 'Open Visit',
    'patients-visit' => 'Patients Visits'
];

// Override for nurse module
if ($currentModule == 'nurse') {
    $subModuleTitles['patients-visit'] = 'Nurse Queue';
}

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'Patient Registration';
?>

<!-- Global _pBase Definition -->
<script>
const TARIFF_LABELS={east_africa:'East Africa',africa:'Africa',world:'Rest of World'};
function tariffBadge(c){return TARIFF_LABELS[c]?`<span style="display:inline-block;margin-left:6px;padding:1px 7px;border-radius:10px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;">Foreigner · ${TARIFF_LABELS[c]}</span>`:'';}
const _pBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
// Only zeros (more than 3) = patient has no National ID
function ptIsMissingNid(nid){const s=String(nid||'').trim();return /^[0\s-]+$/.test(s)&&s.replace(/\D+/g,'').length>3;}
function ptEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
// Consultation fee select driven by the server's 3/14-day rule (Open Visit + both Send-to-Consultant modals).
// query = 'department_id=X&patient_id=Y' or '...&visit_id=Z'. Returns the rule ({rule, days, services, current}) or null.
async function crLoad(sel, note, query, showPrice = true){
  sel.disabled = true; sel.innerHTML = '<option value="">Loading…</option>'; note.style.display = 'none';
  let r = null;
  try{ const j = await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=patients/visits&action=consult_rule&'+query)).json(); if(j.success) r = j.data; }catch(e){}
  sel.dataset.rule = r ? r.rule : '';
  if(!r){ sel.innerHTML = '<option value="">— could not load fees —</option>'; return null; }
  const ago = r.days === null ? '' : `${r.days} day${r.days===1?'':'s'} ago${r.last_visit_no?' ('+ptEsc(r.last_visit_no)+', '+ptEsc(r.last_date)+')':''}`;
  const msg = {
    free:      ['#ecfdf5','#065f46','fa-check-circle',`Returning patient: last full consultation in this service ${ago}. <strong>No consultation fee</strong> (within 3 days).`],
    follow_up: ['#eff6ff','#1e40af','fa-redo',`Returning patient: last full consultation in this service ${ago}. <strong>Follow-up fee only</strong> (within 14 days).`],
    full:      ['#fffbeb','#92400e','fa-user-md', r.days === null ? 'First consultation in this service: <strong>full consultation fee</strong>.' : `Last full consultation ${ago}: more than 14 days, <strong>full consultation fee</strong>.`],
    no_fee:    ['#f8fafc','#475569','fa-info-circle','This service is opened without a consultation fee.']
  }[r.rule];
  note.style.cssText = `display:block;margin-top:6px;padding:7px 10px;border-radius:6px;font-size:12px;background:${msg[0]};color:${msg[1]};`;
  note.innerHTML = `<i class="fas ${msg[2]}" style="margin-right:5px;"></i>${msg[3]}`;
  if(!r.services.length){ sel.innerHTML = '<option value="">— No consultation fee —</option>'; return r; }
  sel.innerHTML = '<option value="">— Select '+(r.rule==='follow_up'?'follow-up':'consultation')+' fee —</option><option value="0">No consultation fee</option>' + r.services.map(s =>
    `<option value="${s.id}">${ptEsc(s.name)}${showPrice?' — '+Number(s.price||0).toLocaleString()+' RWF':''}</option>`).join('');
  const cur = r.current && String(r.current.service_id);
  if(cur && r.services.some(s => String(s.id) === cur)) sel.value = cur;
  else if(r.services.length === 1) sel.value = String(r.services[0].id);
  sel.disabled = !!(r.current && Number(r.current.paid));   // a paid fee is changed in Invoicing only
  if(sel.disabled) note.innerHTML += '<div style="margin-top:3px;">Fee '+ptEsc(r.current.invoice_no)+' already paid: change it in Invoicing.</div>';
  return r;
}
// '' = OK, else the message to show (fee mandatory unless the rule says no fee)
function crMissing(sel){
  const rule = sel.dataset.rule;
  if(!rule) return 'Consultation fees are not loaded yet. Select the service again.';
  if((rule === 'full' || rule === 'follow_up') && sel.value === '' && !sel.disabled) return 'Please select the '+(rule==='follow_up'?'follow-up':'consultation')+' fee.';
  return '';
}
// "Dr. First Last" for doctors, plain name for other clinicians
function ptDocName(u){ const n = ((u.first_name||'')+' '+(u.last_name||'')).trim(); return /doctor|specialist|surgeon|consultant/i.test((u.role||'')+' '+(u.role_name||'')) ? 'Dr. '+n : n; }
function ptNidLabel(nid){
  return ptIsMissingNid(nid) ? '<span style="padding:2px 7px;border-radius:10px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;">No National ID</span>' : ptEsc(nid||'—');
}
</script>

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
.ov-doc-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:8px;}
.ov-doc{display:flex;flex-direction:column;gap:4px;align-items:flex-start;text-align:left;padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;background:white;cursor:pointer;font-size:13px;transition:border-color .15s ease-out,background-color .15s ease-out,transform .15s ease-out;}
.ov-doc:active{transform:scale(.98);}
.ov-doc.off{opacity:.6;}
.ov-doc.sel{border-color:var(--primary-color);background:#eff6ff;opacity:1;}
@media (prefers-reduced-motion:reduce){.ov-doc{transition:none;}.ov-doc:active{transform:none;}}
.duty-badge{display:inline-flex;align-items:center;gap:4px;border-radius:10px;padding:1px 8px;font-size:11px;font-weight:600;white-space:nowrap;}
.duty-on{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.duty-off{background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;}
.crit-badge{display:inline-flex;align-items:center;gap:4px;background:#dc2626;color:#fff;border-radius:10px;padding:1px 8px;font-size:11px;font-weight:700;white-space:nowrap;}
.crit-badge.urgent{background:#ea580c;}
.ref-field{width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;box-sizing:border-box;font-family:inherit;}
.ref-lbl{display:block;font-size:12px;font-weight:600;color:#991b1b;margin:8px 0 3px;}
.ref-steps{display:flex;align-items:center;gap:4px;font-size:11px;white-space:nowrap;}
.ref-steps span{padding:2px 8px;border-radius:10px;background:#f3f4f6;color:#9ca3af;}
.ref-steps span.done{background:#dcfce7;color:#166534;}
.ref-steps span.cur{background:var(--primary-color);color:#fff;font-weight:700;}
.nr-btn{padding:6px 12px;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;color:#fff;background:var(--primary-color);transition:transform .15s ease-out;}
.nr-btn:active{transform:scale(.97);}
@media (prefers-reduced-motion:reduce){.nr-btn{transition:none;}.nr-btn:active{transform:none;}}
.allergy-pill{display:inline-flex;align-items:center;gap:4px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:10px;padding:1px 8px;font-size:11px;font-weight:700;white-space:nowrap;}
.med-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;}
.med-chips button{padding:3px 10px;border:1px solid var(--gray-200);border-radius:14px;background:white;font-size:12px;color:var(--gray-700);cursor:pointer;transition:background-color .15s ease-out,transform .15s ease-out;}
.med-chips button:hover{background:var(--gray-50);}
.med-chips button:active{transform:scale(.96);}
.med-chips button.on{background:#dbeafe;border-color:#93c5fd;color:#1d4ed8;font-weight:600;}
.med-chip-none{display:flex;align-items:center;gap:6px;margin-top:6px;font-size:12px;color:var(--gray-600);cursor:pointer;}
@media (prefers-reduced-motion:reduce){.med-chips button{transition:none;}.med-chips button:active{transform:none;}}
.ov-visits-scroll{max-width:100%;max-height:min(60vh,620px);overflow:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;}
.ov-visits-scroll table{min-width:1080px;}
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'registration'   => 'Register a new patient with complete details',
                'manage'         => 'Search, view, and manage all patient records',
                'open-visit'     => 'Search for a patient, verify insurance, and open a new visit',
                'patients-visit' => "Today's visits — search, filter by date range, and manage consultations"
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Manage patient information';
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
        <?php if ($subModule == 'manage'): ?>
        <button onclick="window.location.href='patients.php?module=receptionist&sub=registration'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: none; align-items: center; gap: 8px;">
            <i class="fas fa-user-plus"></i> New Patient
        </button>
        <?php endif; ?>
        <?php if ($subModule == 'registration' || $subModule == 'open-visit'): ?>
        <button onclick="window.location.href='patients.php?module=receptionist&sub=manage'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back to List
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Sub-Module Content -->
<?php if ($subModule == 'registration'): ?>

<!-- ============================================
NEW PATIENT REGISTRATION
============================================ -->
<div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">

  <!-- UHID Preview Banner -->
  <div id="uhid-preview" style="display:none;">
    <i class="fas fa-id-card" style="font-size:22px;color:#2563eb;flex-shrink:0;"></i>
    <div>
      <div style="font-size:12px;font-weight:600;color:#1e40af;text-transform:uppercase;letter-spacing:.5px;">Patient Number (UHID) — will be auto-generated on save</div>
      <div id="uhid-value" style="font-size:20px;font-weight:700;color:#1d4ed8;font-family:monospace;letter-spacing:2px;">PAT-????????</div>
      <div style="font-size:11px;color:#3b82f6;margin-top:2px;">Format: PAT-YYYYMMDD-XXXX &nbsp;|&nbsp; Unique lifetime identifier for this patient</div>
    </div>
  </div>

  <div id="reg-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;"></div>
  <form id="patientRegistrationForm" onsubmit="regSubmit(event)">

    <!-- Personal Information -->
    <div style="margin-bottom:24px;">
      <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:14px;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
        <i class="fas fa-user" style="color:var(--primary-color);"></i> Personal Information
      </h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">First Name <span style="color:var(--danger-color);">*</span></label>
          <input id="reg-first_name" type="text" placeholder="Enter first name" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Last Name <span style="color:var(--danger-color);">*</span></label>
          <input id="reg-last_name" type="text" placeholder="Enter last name" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Date of Birth <span style="color:var(--danger-color);">*</span></label>
          <input id="reg-date_of_birth" type="date" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Gender <span style="color:var(--danger-color);">*</span></label>
          <select id="reg-gender" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="">Select Gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">National ID / Passport <span style="color:var(--danger-color);">*</span></label>
          <input id="reg-national_id" type="text" placeholder="National ID or Passport No." oninput="document.getElementById('reg-national_id_hint').style.display=ptIsMissingNid(this.value)?'block':'none'" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;" required>
          <div id="reg-national_id_hint" style="display:none;margin-top:6px;font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:6px 9px;">
            <i class="fas fa-info-circle"></i> No National ID: more than 3 zeros entered. Patient will be recorded without a National ID and the duplicate check is skipped.
          </div>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Origin (sets the price list when not insured)</label>
          <select id="reg-tariff_category" onchange="regUpdateCountryField()" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="rwanda">Rwandan</option>
            <option value="east_africa">Foreigner — East Africa</option>
            <option value="africa">Foreigner — Africa</option>
            <option value="world">Foreigner — Rest of the World</option>
          </select>
        </div>
        <div id="reg-country-wrap" style="display:none;">
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Country <span style="color:var(--danger-color);">*</span></label>
          <div style="position:relative;">
            <input id="reg-country-picker" type="search" autocomplete="off" placeholder="Select or search country…" onfocus="regShowCountryOptions()" oninput="regFilterCountries()" style="width:100%;padding:10px 34px 10px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;box-sizing:border-box;">
            <i class="fas fa-chevron-down" style="position:absolute;right:12px;top:12px;color:var(--gray-400);font-size:12px;pointer-events:none;"></i>
            <div id="reg-country-options" style="display:none;position:absolute;z-index:20;top:calc(100% + 3px);left:0;right:0;max-height:220px;overflow-y:auto;background:white;border:1px solid var(--gray-200);border-radius:8px;box-shadow:0 8px 20px rgba(0,0,0,.12);"></div>
          </div>
          <input id="reg-country" type="hidden" value="">
        </div>
      </div>
    </div>

    <!-- Contact Information -->
    <div style="margin-bottom:24px;">
      <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:14px;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
        <i class="fas fa-phone" style="color:var(--success-color);"></i> Contact Information
      </h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Phone Number <span style="color:var(--danger-color);">*</span></label>
          <input id="reg-phone" type="tel" placeholder="+250 xxx xxx xxx" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Email Address</label>
          <input id="reg-email" type="email" placeholder="patient@email.com" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Next of Kin Full Name</label>
          <input id="reg-next_of_kin" type="text" placeholder="Full name" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Emergency Contact</label>
          <input id="reg-emergency_contact" type="tel" placeholder="+250 xxx xxx xxx" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Relationship</label>
          <select id="reg-kin_relationship" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="">Select Relationship</option>
            <option value="spouse">Spouse</option><option value="parent">Parent</option>
            <option value="child">Child</option><option value="sibling">Sibling</option>
            <option value="friend">Friend</option><option value="other">Other</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Location Information (Cascading Dropdowns) -->
    <div style="margin-bottom:24px;">
      <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:14px;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:1px solid var(--gray-100);">
        <i class="fas fa-map-marker-alt" style="color:#e11d48;"></i> Location / Address
      </h3>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:12px;color:var(--gray-700);cursor:pointer;">
        <input type="checkbox" id="reg-address_unknown"> Address unknown (emergency): register now, complete the location later
      </label>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Province <span style="color:var(--danger-color);">*</span></label>
          <select id="reg-province_id" onchange="regLoadDistricts(this.value)" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="">— Select Province —</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">District <span style="color:var(--danger-color);">*</span></label>
          <select id="reg-district_id" onchange="regLoadSectors(this.value)" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;" disabled>
            <option value="">— Select Province first —</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Sector <span style="color:var(--danger-color);">*</span></label>
          <select id="reg-sector_id" onchange="regLoadCells(this.value)" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;" disabled>
            <option value="">— Select District first —</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Cell</label>
          <select id="reg-cell_id" onchange="regLoadVillages(this.value)" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;" disabled>
            <option value="">— Select Sector first —</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Village</label>
          <select id="reg-village_id" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;" disabled>
            <option value="">— Select Cell first —</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Buttons -->
    <div style="display:flex;gap:12px;padding-top:16px;border-top:1px solid var(--gray-200);">
      <button type="submit" id="reg-submit-btn" style="padding:12px 32px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
        <i class="fas fa-user-plus"></i> <span id="reg-submit-label">Register Patient</span>
      </button>
      <button type="reset" onclick="regReset()" style="padding:12px 32px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">
        <i class="fas fa-undo"></i> Reset
      </button>
    </div>
  </form>
</div>

<script>
const patAPI = window.location.origin + _pBase + '/api.php?endpoint=patients/patients';
const locAPI = window.location.origin + _pBase + '/api.php?endpoint=patients/locations';

const REG_EAST_AFRICA = ['Burundi','Democratic Republic of the Congo','Kenya','Rwanda','Somalia','South Sudan','Tanzania','Uganda'];
const REG_AFRICA = `Algeria|Angola|Benin|Botswana|Burkina Faso|Burundi|Cabo Verde|Cameroon|Central African Republic|Chad|Comoros|Congo|Côte d'Ivoire|Democratic Republic of the Congo|Djibouti|Egypt|Equatorial Guinea|Eritrea|Eswatini|Ethiopia|Gabon|Gambia|Ghana|Guinea|Guinea-Bissau|Kenya|Lesotho|Liberia|Libya|Madagascar|Malawi|Mali|Mauritania|Mauritius|Morocco|Mozambique|Namibia|Niger|Nigeria|Rwanda|São Tomé and Príncipe|Senegal|Seychelles|Sierra Leone|Somalia|South Africa|South Sudan|Sudan|Tanzania|Togo|Tunisia|Uganda|Zambia|Zimbabwe`.split('|');
const REG_ALL_COUNTRIES = `Afghanistan|Albania|Algeria|Andorra|Angola|Antigua and Barbuda|Argentina|Armenia|Australia|Austria|Azerbaijan|Bahamas|Bahrain|Bangladesh|Barbados|Belarus|Belgium|Belize|Benin|Bhutan|Bolivia|Bosnia and Herzegovina|Botswana|Brazil|Brunei|Bulgaria|Burkina Faso|Burundi|Cabo Verde|Cambodia|Cameroon|Canada|Central African Republic|Chad|Chile|China|Colombia|Comoros|Congo|Costa Rica|Côte d'Ivoire|Croatia|Cuba|Cyprus|Czechia|Democratic Republic of the Congo|Denmark|Djibouti|Dominica|Dominican Republic|Ecuador|Egypt|El Salvador|Equatorial Guinea|Eritrea|Estonia|Eswatini|Ethiopia|Fiji|Finland|France|Gabon|Gambia|Georgia|Germany|Ghana|Greece|Grenada|Guatemala|Guinea|Guinea-Bissau|Guyana|Haiti|Honduras|Hungary|Iceland|India|Indonesia|Iran|Iraq|Ireland|Israel|Italy|Jamaica|Japan|Jordan|Kazakhstan|Kenya|Kiribati|Kuwait|Kyrgyzstan|Laos|Latvia|Lebanon|Lesotho|Liberia|Libya|Liechtenstein|Lithuania|Luxembourg|Madagascar|Malawi|Malaysia|Maldives|Mali|Malta|Marshall Islands|Mauritania|Mauritius|Mexico|Micronesia|Moldova|Monaco|Mongolia|Montenegro|Morocco|Mozambique|Myanmar|Namibia|Nauru|Nepal|Netherlands|New Zealand|Nicaragua|Niger|Nigeria|North Korea|North Macedonia|Norway|Oman|Pakistan|Palau|Palestine|Panama|Papua New Guinea|Paraguay|Peru|Philippines|Poland|Portugal|Qatar|Romania|Russia|Saint Kitts and Nevis|Saint Lucia|Saint Vincent and the Grenadines|Samoa|San Marino|São Tomé and Príncipe|Saudi Arabia|Senegal|Serbia|Seychelles|Sierra Leone|Singapore|Slovakia|Slovenia|Solomon Islands|Somalia|South Africa|South Korea|South Sudan|Spain|Sri Lanka|Sudan|Suriname|Sweden|Switzerland|Syria|Taiwan|Tajikistan|Tanzania|Thailand|Timor-Leste|Togo|Tonga|Trinidad and Tobago|Tunisia|Türkiye|Turkmenistan|Tuvalu|Uganda|Ukraine|United Arab Emirates|United Kingdom|United States|Uruguay|Uzbekistan|Vanuatu|Vatican City|Venezuela|Vietnam|Yemen|Zambia|Zimbabwe`.split('|');
function regCountryList(category){
  if(category==='east_africa') return REG_EAST_AFRICA;
  if(category==='africa') return REG_AFRICA.filter(c=>!REG_EAST_AFRICA.includes(c));
  if(category==='world') return REG_ALL_COUNTRIES.filter(c=>!REG_AFRICA.includes(c));
  return [];
}
function regUpdateCountryField(){
  const category=document.getElementById('reg-tariff_category').value;
  const wrap=document.getElementById('reg-country-wrap');
  const country=document.getElementById('reg-country');
  const picker=document.getElementById('reg-country-picker');
  const visible=category!=='rwanda';
  wrap.style.display=visible?'block':'none';
  country.required=visible;
  if(!visible){country.value='';picker.value='';}
  regFilterCountries();
}
function regShowCountryOptions(){
  if(document.getElementById('reg-tariff_category').value!=='rwanda'){
    document.getElementById('reg-country-options').style.display='block';
    regFilterCountries();
  }
}
function regFilterCountries(){
  const category=document.getElementById('reg-tariff_category').value;
  const picker=document.getElementById('reg-country-picker');
  const query=(picker.value||'').trim().toLowerCase();
  const selected=document.getElementById('reg-country').value;
  const list=regCountryList(category).filter(c=>c.toLowerCase().includes(query));
  const box=document.getElementById('reg-country-options');
  box.innerHTML=list.length ? list.map(c=>'<button type="button" onclick="regChooseCountry(\''+c.replace(/\\/g,'\\\\').replace(/'/g,"\\'")+'\')" style="display:block;width:100%;padding:9px 12px;text-align:left;border:0;background:'+(c===selected?'#eff6ff':'white')+';color:var(--gray-800);font-size:13px;cursor:pointer;">'+c+'</button>').join('') : '<div style="padding:10px 12px;color:var(--gray-400);font-size:13px;">No countries found</div>';
  if(document.activeElement===picker) box.style.display='block';
}
function regChooseCountry(country){
  document.getElementById('reg-country').value=country;
  document.getElementById('reg-country-picker').value=country;
  document.getElementById('reg-country-options').style.display='none';
}
document.addEventListener('click',function(e){
  const wrap=document.getElementById('reg-country-wrap');
  if(wrap && !wrap.contains(e.target)) document.getElementById('reg-country-options').style.display='none';
});

// ============================================
// CASCADING LOCATION DROPDOWNS
// ============================================
async function regLoadProvinces() {
  try {
    const r = await fetch(locAPI + '&type=provinces');
    const j = await r.json();
    if (!j.success) return;
    const sel = document.getElementById('reg-province_id');
    sel.innerHTML = '<option value="">— Select Province —</option>' +
      j.data.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
  } catch(e) { console.error('Error loading provinces:', e); }
}

async function regLoadDistricts(provinceId) {
  const distSel = document.getElementById('reg-district_id');
  const secSel = document.getElementById('reg-sector_id');
  const cellSel = document.getElementById('reg-cell_id');
  const vilSel = document.getElementById('reg-village_id');
  
  // Reset dependent dropdowns
  secSel.innerHTML = '<option value="">— Select District first —</option>';
  secSel.disabled = true;
  cellSel.innerHTML = '<option value="">— Select Sector first —</option>';
  cellSel.disabled = true;
  vilSel.innerHTML = '<option value="">— Select Cell first —</option>';
  vilSel.disabled = true;
  
  if (!provinceId) {
    distSel.innerHTML = '<option value="">— Select Province first —</option>';
    distSel.disabled = true;
    return;
  }
  distSel.innerHTML = '<option value="">Loading…</option>';
  distSel.disabled = true;
  try {
    const r = await fetch(locAPI + '&type=districts&parent_id=' + provinceId);
    const j = await r.json();
    if (!j.success || !j.data.length) {
      distSel.innerHTML = '<option value="">— No districts found —</option>';
      return;
    }
    distSel.innerHTML = '<option value="">— Select District —</option>' +
      j.data.map(d => `<option value="${d.id}">${d.name}</option>`).join('');
    distSel.disabled = false;
  } catch(e) {
    distSel.innerHTML = '<option value="">— Error loading —</option>';
  }
}

async function regLoadSectors(districtId) {
  const secSel = document.getElementById('reg-sector_id');
  const cellSel = document.getElementById('reg-cell_id');
  const vilSel = document.getElementById('reg-village_id');
  
  // Reset dependent dropdowns
  cellSel.innerHTML = '<option value="">— Select Sector first —</option>';
  cellSel.disabled = true;
  vilSel.innerHTML = '<option value="">— Select Cell first —</option>';
  vilSel.disabled = true;
  
  if (!districtId) {
    secSel.innerHTML = '<option value="">— Select District first —</option>';
    secSel.disabled = true;
    return;
  }
  secSel.innerHTML = '<option value="">Loading…</option>';
  secSel.disabled = true;
  try {
    const r = await fetch(locAPI + '&type=sectors&parent_id=' + districtId);
    const j = await r.json();
    if (!j.success || !j.data.length) {
      secSel.innerHTML = '<option value="">— No sectors found —</option>';
      return;
    }
    secSel.innerHTML = '<option value="">— Select Sector —</option>' +
      j.data.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    secSel.disabled = false;
  } catch(e) {
    secSel.innerHTML = '<option value="">— Error loading —</option>';
  }
}

async function regLoadCells(sectorId) {
  const cellSel = document.getElementById('reg-cell_id');
  const vilSel = document.getElementById('reg-village_id');
  
  // Reset dependent dropdown
  vilSel.innerHTML = '<option value="">— Select Cell first —</option>';
  vilSel.disabled = true;
  
  if (!sectorId) {
    cellSel.innerHTML = '<option value="">— Select Sector first —</option>';
    cellSel.disabled = true;
    return;
  }
  cellSel.innerHTML = '<option value="">Loading…</option>';
  cellSel.disabled = true;
  try {
    const r = await fetch(locAPI + '&type=cells&parent_id=' + sectorId);
    const j = await r.json();
    if (!j.success || !j.data.length) {
      cellSel.innerHTML = '<option value="">— No cells found —</option>';
      return;
    }
    cellSel.innerHTML = '<option value="">— Select Cell —</option>' +
      j.data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    cellSel.disabled = false;
  } catch(e) {
    cellSel.innerHTML = '<option value="">— Error loading —</option>';
  }
}

async function regLoadVillages(cellId) {
  const vilSel = document.getElementById('reg-village_id');
  
  if (!cellId) {
    vilSel.innerHTML = '<option value="">— Select Cell first —</option>';
    vilSel.disabled = true;
    return;
  }
  vilSel.innerHTML = '<option value="">Loading…</option>';
  vilSel.disabled = true;
  try {
    const r = await fetch(locAPI + '&type=villages&parent_id=' + cellId);
    const j = await r.json();
    if (!j.success || !j.data.length) {
      vilSel.innerHTML = '<option value="">— No villages found —</option>';
      return;
    }
    vilSel.innerHTML = '<option value="">— Select Village —</option>' +
      j.data.map(v => `<option value="${v.id}">${v.name}</option>`).join('');
    vilSel.disabled = false;
  } catch(e) {
    vilSel.innerHTML = '<option value="">— Error loading —</option>';
  }
}

async function regSubmit(e) {
  e.preventDefault();
  const btn = document.getElementById('reg-submit-btn');
  const al  = document.getElementById('reg-alert');
  al.style.display = 'none';

  // --- Mandatory field validation ---
  const nationalId = document.getElementById('reg-national_id').value.trim();
  const phone      = document.getElementById('reg-phone').value.trim();

  if (!nationalId) {
    al.style.display = 'block';
    al.style.background = '#fef2f2';
    al.style.color = '#991b1b';
    al.style.border = '1px solid #fecaca';
    al.innerHTML = '<i class="fas fa-exclamation-circle"></i> <strong>National ID / Passport is required.</strong>';
    document.getElementById('reg-national_id').focus();
    return;
  }
  if (!phone) {
    al.style.display = 'block';
    al.style.background = '#fef2f2';
    al.style.color = '#991b1b';
    al.style.border = '1px solid #fecaca';
    al.innerHTML = '<i class="fas fa-exclamation-circle"></i> <strong>Phone Number is required.</strong>';
    document.getElementById('reg-phone').focus();
    return;
  }
  for (const [id, name] of (document.getElementById('reg-address_unknown').checked ? [] : [['reg-province_id','Province'],['reg-district_id','District'],['reg-sector_id','Sector']])) {
    if (!document.getElementById(id).value) {
      al.style.display = 'block';
      al.style.background = '#fef2f2';
      al.style.color = '#991b1b';
      al.style.border = '1px solid #fecaca';
      al.innerHTML = `<i class="fas fa-exclamation-circle"></i> <strong>${name} is required.</strong>`;
      document.getElementById(id).focus();
      return;
    }
  }
  const tariffCategory = document.getElementById('reg-tariff_category').value;
  const selectedCountry = document.getElementById('reg-country').value;
  const typedCountry = document.getElementById('reg-country-picker').value.trim();
  const allowedCountries = regCountryList(tariffCategory);
  const validCountry = tariffCategory === 'rwanda' ||
    (selectedCountry && typedCountry === selectedCountry && allowedCountries.includes(selectedCountry));
  if (!validCountry) {
    al.style.display = 'block';
    al.style.background = '#fef2f2';
    al.style.color = '#991b1b';
    al.style.border = '1px solid #fecaca';
    al.innerHTML = '<i class="fas fa-exclamation-circle"></i> <strong>Please select a valid country from the dropdown list.</strong>';
    document.getElementById('reg-country-picker').focus();
    return;
  }

  // --- Duplicate National ID / Passport check ---
  if (!ptIsMissingNid(nationalId)) try {
    const dupCheck = await fetch(patAPI + '&action=check_national_id&national_id=' + encodeURIComponent(nationalId));
    const dupJson  = await dupCheck.json();
    if (dupJson.success && dupJson.data && dupJson.data.exists) {
      al.style.display = 'block';
      al.style.background = '#fef2f2';
      al.style.color = '#991b1b';
      al.style.border = '1px solid #fecaca';
      al.innerHTML = `<i class="fas fa-exclamation-circle"></i> <strong>Duplicate record:</strong> A patient with National ID / Passport <strong>${nationalId}</strong> is already registered (Patient No: ${dupJson.data.patient_number || '—'}).`;
      document.getElementById('reg-national_id').focus();
      return;
    }
  } catch(dupErr) {
    // If check endpoint not available, fall through — server-side will catch it
  }

  btn.disabled = true;
  document.getElementById('reg-submit-label').textContent = 'Registering…';

  const body = {
    first_name:          document.getElementById('reg-first_name').value.trim(),
    last_name:           document.getElementById('reg-last_name').value.trim(),
    date_of_birth:       document.getElementById('reg-date_of_birth').value,
    gender:              document.getElementById('reg-gender').value,
    national_id:         document.getElementById('reg-national_id').value.trim(),
    tariff_category:     document.getElementById('reg-tariff_category').value,
    country:             document.getElementById('reg-country').value,
    phone:               document.getElementById('reg-phone').value.trim(),
    email:               document.getElementById('reg-email').value.trim(),
    province_id:         document.getElementById('reg-province_id').value || null,
    district_id:         document.getElementById('reg-district_id').value || null,
    sector_id:           document.getElementById('reg-sector_id').value || null,
    cell_id:             document.getElementById('reg-cell_id').value || null,
    village_id:          document.getElementById('reg-village_id').value || null,
    emergency_contact:   document.getElementById('reg-emergency_contact').value.trim(),
    next_of_kin:         document.getElementById('reg-next_of_kin').value.trim(),
    kin_relationship:    document.getElementById('reg-kin_relationship').value,
    address_unknown:     document.getElementById('reg-address_unknown').checked,
    registered_by:       <?php echo (int)($_SESSION['user_id'] ?? 0); ?>
  };

  try {
    let r = await fetch(patAPI, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
    let j = await r.json();
    if (j.code === 'possible_duplicate' && confirm(j.message + ' Register as a NEW patient anyway?')) {
      body.confirm_duplicate = true;
      r = await fetch(patAPI, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
      j = await r.json();
    }
    if (j.success) {
      document.getElementById('patientRegistrationForm').reset();
      regResetFormFields();

      const patNo = j.data?.patient_no || '';
      const patName = (document.getElementById('reg-first_name')?.value || '') + ' ' + (document.getElementById('reg-last_name')?.value || '');
      const ptMod = '<?php echo htmlspecialchars($currentModule ?: "receptionist"); ?>';

      al.innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:14px;">
          <i class="fas fa-check-circle" style="font-size:28px;color:#16a34a;flex-shrink:0;margin-top:2px;"></i>
          <div style="flex:1;">
            <div style="font-weight:700;font-size:16px;color:#166534;margin-bottom:4px;">Patient Registered Successfully!</div>
            <div style="font-size:13px;color:#15803d;margin-bottom:14px;">
              Patient Number (UHID): <strong style="font-family:monospace;font-size:16px;letter-spacing:1px;background:#dcfce7;padding:2px 8px;border-radius:4px;color:#166534;">${patNo}</strong>
            </div>
            ${j.data?.no_national_id ? '<div style="font-size:12px;margin-bottom:10px;padding:8px 10px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;color:#92400e;"><i class="fas fa-info-circle"></i> Recorded without a National ID.</div>' : ''}
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <a href="patients.php?module=${encodeURIComponent(ptMod)}&sub=open-visit&patient_no=${encodeURIComponent(patNo)}"
                 style="padding:10px 20px;background:#16a34a;color:white;border-radius:6px;font-weight:600;text-decoration:none;font-size:13px;display:inline-flex;align-items:center;gap:6px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                <i class="fas fa-door-open"></i> Open Visit Now
              </a>
              <a href="patients.php?module=${encodeURIComponent(ptMod)}&sub=manage"
                 style="padding:10px 18px;background:white;color:#166534;border:1px solid #bbf7d0;border-radius:6px;font-weight:600;text-decoration:none;font-size:13px;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-list"></i> View in Patient List
              </a>
            </div>
          </div>
        </div>`;
      al.style.cssText = 'display:block;padding:18px 20px;border-radius:10px;font-size:14px;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;margin-bottom:20px;box-shadow:0 2px 6px rgba(22,101,52,0.08);';
      window.scrollTo({top:0,behavior:'smooth'});
    } else {
      al.textContent = j.message || 'Registration failed';
      al.style.cssText = 'display:block;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin-bottom:16px;';
    }
  } catch(err) {
    al.textContent = 'Request failed: ' + err.message;
    al.style.cssText = 'display:block;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin-bottom:16px;';
  } finally {
    btn.disabled = false;
    document.getElementById('reg-submit-label').textContent = 'Register Patient';
  }
}

function regResetFormFields() {
  document.getElementById('reg-national_id_hint').style.display = 'none';
  document.getElementById('uhid-value').textContent = 'PAT-????????';
  // Reset location dropdowns
  document.getElementById('reg-district_id').innerHTML = '<option value="">— Select Province first —</option>';
  document.getElementById('reg-district_id').disabled = true;
  document.getElementById('reg-sector_id').innerHTML = '<option value="">— Select District first —</option>';
  document.getElementById('reg-sector_id').disabled = true;
  document.getElementById('reg-cell_id').innerHTML = '<option value="">— Select Sector first —</option>';
  document.getElementById('reg-cell_id').disabled = true;
  document.getElementById('reg-village_id').innerHTML = '<option value="">— Select Cell first —</option>';
  document.getElementById('reg-village_id').disabled = true;
  document.getElementById('reg-province_id').value = '';
  document.getElementById('reg-tariff_category').value = 'rwanda';
  document.getElementById('reg-country-picker').value = '';
  document.getElementById('reg-country').value = '';
  regUpdateCountryField();
}

function regReset() {
  regResetFormFields();
  document.getElementById('reg-alert').style.display = 'none';
}

// Animate UHID preview to show today's date prefix
(function(){
  const today = new Date();
  const y = today.getFullYear();
  const m = String(today.getMonth()+1).padStart(2,'0');
  const d = String(today.getDate()).padStart(2,'0');
  const todayStr = `${y}-${m}-${d}`;
  document.getElementById('uhid-value').textContent = `PAT-${y}${m}${d}-????`;
})();

regLoadProvinces();
regUpdateCountryField();
</script>

<?php elseif ($subModule == 'manage'): ?>

<!-- ============================================
MANAGE PATIENTS
============================================ -->
<div id="pt-list-section">
<!-- Toolbar -->
<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
  <input id="pt-search" type="text" placeholder="Search name, patient no, phone, location…" value="<?php echo htmlspecialchars((string)($_GET['search'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
    style="flex:1;min-width:200px;padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
  <select id="pt-filter-status" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
    <option value="">All Statuses</option>
    <option value="active">Active</option>
    <option value="admitted">Admitted</option>
    <option value="critical">Critical</option>
    <option value="discharged">Discharged</option>
  </select>
  <select id="pt-filter-province" onchange="ptOnProvinceChange()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:145px;">
    <option value="">All Locations / Provinces</option>
    <option value="catchment">📍 Catchment Zone (Nyamata / Ntarama)</option>
    <option value="outside">📍 Outside Zone (Other Rwanda)</option>
    <option value="international">📍 International / No Address</option>
    <optgroup label="── Provinces ──" id="pt-optgroup-provinces"></optgroup>
  </select>
  <select id="pt-filter-district" onchange="ptOnDistrictChange()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:140px;">
    <option value="">All Districts</option>
  </select>
  <select id="pt-filter-sector" onchange="ptOnSectorChange()" style="padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;min-width:140px;">
    <option value="">All Sectors</option>
  </select>
  <div style="display:flex;align-items:center;gap:4px;">
    <label style="font-size:11px;color:var(--gray-500);font-weight:500;">From</label>
    <input id="pt-date-from" type="date" value="<?php echo date('Y-m-d', strtotime('-1 month')); ?>" style="padding:9px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
  </div>
  <div style="display:flex;align-items:center;gap:4px;">
    <label style="font-size:11px;color:var(--gray-500);font-weight:500;">To</label>
    <input id="pt-date-to" type="date" value="<?php echo date('Y-m-d'); ?>" style="padding:9px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;">
  </div>
  <button onclick="ptLoad()" style="padding:9px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
    <i class="fas fa-search"></i> Search
  </button>
  <button onclick="ptReset()" style="padding:9px 18px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">
    <i class="fas fa-undo"></i> Reset
  </button>
  <button onclick="ptExportExcel()" style="padding:9px 18px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
    <i class="fas fa-file-excel"></i> Export Excel
  </button>
</div>

<!-- Table -->
<div style="background:white;border-radius:var(--radius);padding:0 0 8px;box-shadow:var(--shadow);overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;font-size:14px;">
    <thead>
      <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">#</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Patient</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Patient No.</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Age / Gender</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Phone</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Location</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Insurance</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Registered</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Registered By</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
        <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
      </tr>
    </thead>
    <tbody id="pt-tbody" data-no-page>
      <tr><td colspan="11" style="padding:48px;text-align:center;color:var(--gray-400);">
        <i class="fas fa-spinner fa-spin" style="font-size:24px;"></i>
      </td></tr>
    </tbody>
  </table>
  <div id="pt-pagination" style="display:flex;justify-content:flex-end;gap:6px;padding:12px 16px;"></div>
</div>
</div><!-- end pt-list-section -->

<!-- View Patient Full Page -->
<div id="pt-view-fullpage" style="display:none;">
  <div style="margin-bottom:16px;">
    <button onclick="ptBackToList()" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;"><i class="fas fa-arrow-left"></i> Back to Patient List</button>
  </div>
  <div id="pt-view-body" style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">Loading…</div>
</div>

<!-- Edit Patient Modal -->
<div id="pt-edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1001;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:12px;padding:28px;width:100%;max-width:850px;max-height:90vh;overflow-y:auto;position:relative;">
    <button onclick="document.getElementById('pt-edit-modal').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);"><i class="fas fa-times"></i></button>
    <h3 style="font-size:18px;font-weight:600;margin-bottom:20px;"><i class="fas fa-user-edit" style="color:var(--primary-color);margin-right:8px;"></i> Edit Patient</h3>
    <div id="pt-edit-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:14px;"></div>
    <form id="pt-edit-form" onsubmit="ptEditSubmit(event)">
      <input type="hidden" id="edit-patient-id">

      <!-- Personal Information -->
      <div style="margin-bottom:20px;">
        <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--gray-100);"><i class="fas fa-user" style="color:var(--primary-color);margin-right:6px;"></i>Personal Information</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">First Name *</label>
            <input id="edit-first_name" type="text" required style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Last Name *</label>
            <input id="edit-last_name" type="text" required style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Date of Birth *</label>
            <input id="edit-date_of_birth" type="date" required style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Gender *</label>
            <select id="edit-gender" required style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">National ID</label>
            <input id="edit-national_id" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Origin</label>
            <select id="edit-tariff_category" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="rwanda">Rwandan</option>
            <option value="east_africa">Foreigner — East Africa</option>
            <option value="africa">Foreigner — Africa</option>
            <option value="world">Foreigner — Rest of the World</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Medical Information -->
      <div style="margin-bottom:20px;">
        <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--gray-100);"><i class="fas fa-notes-medical" style="color:var(--warning-color);"></i> Medical Information</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Allergies</label>
            <input id="edit-allergies" type="text" placeholder="e.g. Penicillin, Nuts" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <label class="med-chip-none"><input type="checkbox" onchange="medNoAllergies(this,'edit-allergies')"> No known allergies</label>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Non-communicable diseases (NCDs)</label>
            <input id="edit-chronic_diseases" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <div class="med-chips" data-for="edit-chronic_diseases"></div>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Current Medications</label>
            <input id="edit-current_medications" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Medical Alerts</label>
            <input id="edit-medical_alerts" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
        </div>
      </div>

      <!-- Contact Information -->
      <div style="margin-bottom:20px;">
        <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--gray-100);"><i class="fas fa-phone" style="color:var(--success-color);margin-right:6px;"></i>Contact</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Phone *</label>
            <input id="edit-phone" type="tel" required style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Email</label>
            <input id="edit-email" type="email" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Emergency Contact</label>
            <input id="edit-emergency_contact" type="tel" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Next of Kin</label>
            <input id="edit-next_of_kin" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Relationship</label>
            <select id="edit-kin_relationship" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="">—</option><option value="spouse">Spouse</option><option value="parent">Parent</option>
              <option value="child">Child</option><option value="sibling">Sibling</option>
              <option value="friend">Friend</option><option value="other">Other</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Location -->
      <div style="margin-bottom:20px;">
        <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--gray-100);"><i class="fas fa-map-marker-alt" style="color:#e11d48;margin-right:6px;"></i>Location</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;">
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Province</label>
            <select id="edit-province_id" onchange="editLoadDistricts(this.value)" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="">— Select —</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">District</label>
            <select id="edit-district_id" onchange="editLoadSectors(this.value)" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="">— Select Province —</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Sector</label>
            <select id="edit-sector_id" onchange="editLoadCells(this.value)" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="">— Select District —</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Cell</label>
            <select id="edit-cell_id" onchange="editLoadVillages(this.value)" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="">— Select Sector —</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Village</label>
            <select id="edit-village_id" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="">— Select Cell —</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Status -->
      <div style="margin-bottom:20px;">
        <h4 style="font-size:14px;font-weight:600;color:var(--gray-800);margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--gray-100);"><i class="fas fa-clipboard" style="color:var(--gray-500);margin-right:6px;"></i>Status & Notes</h4>
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:12px;">
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Status</label>
            <select id="edit-status" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
              <option value="active">Active</option><option value="admitted">Admitted</option>
              <option value="critical">Critical</option><option value="discharged">Discharged</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;">Notes</label>
            <input id="edit-notes" type="text" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
          </div>
        </div>
      </div>

      <!-- Buttons -->
      <div style="display:flex;gap:10px;padding-top:16px;border-top:1px solid var(--gray-200);">
        <button type="submit" id="pt-edit-save-btn" style="padding:10px 28px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;">
          <i class="fas fa-save"></i> Save Changes
        </button>
        <button type="button" onclick="document.getElementById('pt-edit-modal').style.display='none'" style="padding:10px 28px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;font-size:14px;">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const ptIsReception = <?php echo $currentModule === 'receptionist' ? 'true' : 'false'; ?>;
const ptIsAdmin = <?php echo (isset($_SESSION['user_data']['is_admin']) && $_SESSION['user_data']['is_admin']) ? 'true' : 'false'; ?>;
const ptCanOverrideEdit = <?php echo (isset($_SESSION['user_data']['is_admin']) && $_SESSION['user_data']['is_admin']) || (isset($_SESSION['user_data']['role_slugs']) && in_array('admin', $_SESSION['user_data']['role_slugs'])) ? 'true' : 'false'; ?>;
const _ptBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const ptAPI = window.location.origin + _ptBase + '/api.php?endpoint=patients/patients';
const ptLocAPI = window.location.origin + _ptBase + '/api.php?endpoint=patients/locations';
let ptPage = 1, ptSearch = <?php echo json_encode((string)($_GET['search'] ?? '')); ?>, ptFilterStatus = '';

function ptAge(dob){
  if(!dob)return '—';
  const d=new Date(dob), now=new Date();
  let y=now.getFullYear()-d.getFullYear();
  if(now.getMonth()<d.getMonth()||(now.getMonth()===d.getMonth()&&now.getDate()<d.getDate()))y--;
  return y+'y';
}

async function ptLoad(){
  const dateFrom=document.getElementById('pt-date-from')?.value||'';
  const dateTo=document.getElementById('pt-date-to')?.value||'';
  const provVal=document.getElementById('pt-filter-province')?.value||'';
  const distVal=document.getElementById('pt-filter-district')?.value||'';
  const secVal=document.getElementById('pt-filter-sector')?.value||'';

  const p=new URLSearchParams({page:ptPage,limit:pgSize('pt',25),search:ptSearch,status:ptFilterStatus});
  if(dateFrom) p.append('date_from',dateFrom);
  if(dateTo) p.append('date_to',dateTo);
  if(provVal){
    if(['catchment','outside','international'].includes(provVal)){
      p.append('zone',provVal);
    } else {
      p.append('province_id',provVal);
    }
  }
  if(distVal) p.append('district_id',distVal);
  if(secVal) p.append('sector_id',secVal);

  try{
    const r=await fetch(ptAPI+'&'+p);
    const j=await r.json();
    if(!j.success){document.getElementById('pt-tbody').innerHTML=`<tr><td colspan="11" style="padding:40px;text-align:center;color:var(--gray-400);">Failed to load</td></tr>`;return;}
    const {patients,stats,pagination}=j.data;
    const tb=document.getElementById('pt-tbody');
    const sc={active:'var(--success-color)',admitted:'var(--warning-color)',critical:'var(--danger-color)',discharged:'var(--gray-400)',deceased:'#7c3aed'};
    if(!patients.length){
      tb.innerHTML='<tr><td colspan="11" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-users" style="font-size:36px;display:block;margin-bottom:8px;"></i>No patients found.</td></tr>';
    }else{
      const startNum = ((ptPage-1)*pgSize('pt',25))+1;
      tb.innerHTML=patients.map((p,idx)=>{
        const regDate=p.created_at?p.created_at.substring(0,10):'—';

        // Location formatting
        let locPrimary = '';
        let locSub = '';
        if(p.district_name && p.sector_name){
          locPrimary = p.district_name + ', ' + p.sector_name;
          locSub = [p.province_name, p.cell_name, p.village_name].filter(Boolean).join(' • ');
        } else if(p.district_name){
          locPrimary = p.district_name;
          locSub = [p.province_name, p.cell_name, p.village_name].filter(Boolean).join(' • ');
        } else if(p.sector_name){
          locPrimary = p.sector_name;
          locSub = [p.province_name, p.cell_name, p.village_name].filter(Boolean).join(' • ');
        } else if(p.province_name){
          locPrimary = p.province_name;
          locSub = p.address || '';
        } else if(p.address){
          locPrimary = p.address;
          locSub = '';
        }

        const locHtml = locPrimary ? `
          <div>
            <div style="font-weight:600;color:var(--gray-800);display:flex;align-items:center;gap:5px;">
              <i class="fas fa-map-marker-alt" style="color:#ef4444;font-size:11px;"></i>
              <span>${ptEsc(locPrimary)}</span>
            </div>
            ${locSub ? `<div style="font-size:11px;color:var(--gray-500);margin-top:2px;padding-left:14px;">${ptEsc(locSub)}</div>` : ''}
          </div>` : `<span style="color:var(--gray-400);">—</span>`;

        return `
        <tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:12px 16px;font-size:13px;color:var(--gray-500);font-weight:500;">${startNum+idx}</td>
          <td style="padding:12px 16px;">
            <div style="font-weight:600;color:var(--gray-900);">${ptEsc(p.first_name)} ${ptEsc(p.last_name)}</div>
            <div style="font-size:12px;color:var(--gray-500);">${ptNidLabel(p.national_id)}</div>
          </td>
          <td style="padding:12px 16px;font-family:monospace;font-size:13px;color:var(--primary-color);">${ptEsc(p.patient_no)}</td>
          <td style="padding:12px 16px;">${ptAge(p.date_of_birth)} / ${p.gender?p.gender.charAt(0).toUpperCase()+p.gender.slice(1):'—'}</td>
          <td style="padding:12px 16px;">${ptEsc(p.phone)}</td>
          <td style="padding:12px 16px;font-size:13px;">${locHtml}</td>
          <td style="padding:12px 16px;font-size:12px;color:var(--gray-500);">${p.insurance_company?'<span style="color:var(--success-color);font-weight:600;"><i class="fas fa-check-circle"></i> '+ptEsc(p.insurance_company)+'</span>':'<span style="color:var(--gray-400);">None</span>'}</td>
          <td style="padding:12px 16px;font-size:12px;color:var(--gray-600);">${regDate}</td>
          <td style="padding:12px 16px;font-size:12px;">
            ${p.registered_by_name
              ? `<span style="font-weight:600;color:var(--gray-800);"><i class="fas fa-user" style="color:var(--primary-color);margin-right:3px;font-size:11px;"></i>${ptEsc(p.registered_by_name)}</span>`
              : `<span style="color:var(--gray-400);">—</span>`}
          </td>
          <td style="padding:12px 16px;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${sc[p.status]??'#ccc'}20;color:${sc[p.status]??'#555'};">${p.status.charAt(0).toUpperCase()+p.status.slice(1)}</span></td>
          <td style="padding:12px 16px;text-align:center;white-space:nowrap;">
            ${ptIsReception?'':`<button onclick="ptView(${p.id})" style="background:none;border:none;color:var(--primary-color);cursor:pointer;font-size:15px;padding:4px 6px;" title="View"><i class="fas fa-eye"></i></button>`}
            ${(!ptCanOverrideEdit && (+p.edits_used||0)>=3)
              ?`<button onclick="alert('Edit limit reached: this patient was already edited 3 times. Only an administrator can edit it now.')" style="background:none;border:none;color:var(--gray-400);cursor:not-allowed;font-size:15px;padding:4px 6px;" title="Edit limit reached (3/3)"><i class="fas fa-lock"></i></button>`
              :`<button onclick="ptEdit(${p.id})" style="background:none;border:none;color:var(--warning-color);cursor:pointer;font-size:15px;padding:4px 6px;" title="Edit${ptCanOverrideEdit?'':' ('+(3-(+p.edits_used||0))+' left)'}"><i class="fas fa-edit"></i></button>`}
            ${ptIsAdmin?`<button onclick="ptDelete(${p.id},'${ptEsc(p.first_name+' '+p.last_name)}')" style="background:none;border:none;color:var(--danger-color);cursor:pointer;font-size:15px;padding:4px 6px;" title="Request Deletion"><i class="fas fa-trash"></i></button>`:''}
          </td>
        </tr>`;
      }).join('');
    }
    const pg=document.getElementById('pt-pagination');
    if(pagination.total_pages>1){
      let pgHtml='<div style="display:flex;align-items:center;gap:10px;width:100%;justify-content:space-between;">';
      pgHtml+=`<button onclick="ptPage--;ptLoad()" ${pagination.page<=1?'disabled':''} style="padding:8px 16px;border:1px solid var(--gray-200);background:${pagination.page<=1?'var(--gray-100)':'white'};color:${pagination.page<=1?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${pagination.page<=1?'not-allowed':'pointer'};font-weight:500;font-size:13px;"><i class="fas fa-chevron-left"></i> Previous</button>`;
      pgHtml+=`${pgSizeSelect('pt',25,'ptPage=1;ptLoad()',[10,25,50,100])}<span style="font-size:13px;color:var(--gray-600);">Page ${pagination.page} of ${pagination.total_pages} <span style="color:var(--gray-400);">(${pagination.total} patients)</span></span>`;
      pgHtml+=`<button onclick="ptPage++;ptLoad()" ${pagination.page>=pagination.total_pages?'disabled':''} style="padding:8px 16px;border:1px solid var(--gray-200);background:${pagination.page>=pagination.total_pages?'var(--gray-100)':'white'};color:${pagination.page>=pagination.total_pages?'var(--gray-400)':'var(--gray-700)'};border-radius:6px;cursor:${pagination.page>=pagination.total_pages?'not-allowed':'pointer'};font-weight:500;font-size:13px;">Next <i class="fas fa-chevron-right"></i></button>`;
      pgHtml+='</div>';
      pg.innerHTML=pgHtml;
    } else { pg.innerHTML=`<div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect('pt',25,'ptPage=1;ptLoad()',[10,25,50,100])}<span style="font-size:13px;color:var(--gray-600);">${pagination.total} patients</span></div>`; }  }catch(e){document.getElementById('pt-tbody').innerHTML=`<tr><td colspan="11" style="padding:40px;text-align:center;color:var(--gray-400);">Error: ${e.message}</td></tr>`;}
}

function ptBackToList(){
  document.getElementById('pt-list-section').style.display='block';
  document.getElementById('pt-view-fullpage').style.display='none';
}

async function ptView(id){
  document.getElementById('pt-list-section').style.display='none';
  document.getElementById('pt-view-fullpage').style.display='block';
  document.getElementById('pt-view-body').innerHTML='<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--gray-400);"></i></div>';
  try{
    const r=await fetch(ptAPI+'&id='+id);
    const j=await r.json();
    if(!j.success){document.getElementById('pt-view-body').textContent='Error: '+j.message;return;}
    const p=j.data;

    // Location string
    const locParts = [p.province_name, p.district_name, p.sector_name, p.cell_name, p.village_name].filter(Boolean);
    const locationStr = locParts.length ? locParts.join(' → ') : '—';

    // Insurance section
    let insHtml='<div style="color:var(--gray-400);font-size:13px;">No insurance on file.</div>';
    if(p.insurance && p.insurance.length){
      insHtml = p.insurance.map(i=>`
        <div style="padding:12px 14px;background:var(--gray-50);border-radius:8px;margin-bottom:8px;border-left:3px solid var(--primary-color);">
          <div style="font-weight:600;font-size:14px;">${ptEsc(i.company_name)} <span style="font-size:12px;color:var(--gray-500);">${ptEsc(i.company_code||'')}</span></div>
          ${i.package_name?`<div style="font-size:13px;color:var(--gray-600);margin-top:2px;">Package: ${ptEsc(i.package_name)}</div>`:''}
          <div style="font-size:13px;color:var(--gray-600);margin-top:2px;">Member No: ${ptEsc(i.member_number||'—')}</div>
          <div style="font-size:13px;color:var(--gray-600);">Status:
            <span style="margin-left:8px;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:${i.status==='active'?'#dcfce7':'#fef2f2'};color:${i.status==='active'?'#166534':'#991b1b'};">${(i.status||'').toUpperCase()}</span>
          </div>
          ${i.principal_member_name?`
          <div style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--gray-200);font-size:12px;color:var(--gray-600);">
            <strong style="color:var(--gray-700);">Principal Member:</strong> ${ptEsc(i.principal_member_name)}
            ${i.principal_phone?' | Phone: '+ptEsc(i.principal_phone):''}
            ${i.principal_member_number?' | No: '+ptEsc(i.principal_member_number):''}
            ${i.employer_name?'<br><strong style="color:var(--gray-700);">Employer:</strong> '+ptEsc(i.employer_name):''}
          </div>`:''}
        </div>`).join('');
    }

    document.getElementById('pt-view-body').innerHTML=`
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:14px;">
        <div><span style="color:var(--gray-500);font-size:12px;">Patient No</span><div style="font-weight:700;color:var(--primary-color);font-family:monospace;">${ptEsc(p.patient_no)}</div></div>
        <div><span style="color:var(--gray-500);font-size:12px;">Full Name</span><div style="font-weight:600;">${ptEsc(p.first_name)} ${ptEsc(p.last_name)}${p.allergies&&!/^no known allergies$/i.test(p.allergies)?` <span class="allergy-pill" title="${ptEsc(p.allergies)}"><i class="fas fa-exclamation-triangle"></i> Allergies</span>`:''}</div></div>
        <div><span style="color:var(--gray-500);font-size:12px;">Date of Birth</span><div>${p.date_of_birth||'—'} (${ptAge(p.date_of_birth)})</div></div>
        <div><span style="color:var(--gray-500);font-size:12px;">Gender</span><div>${p.gender?p.gender.charAt(0).toUpperCase()+p.gender.slice(1):'—'}</div></div>
        <div><span style="color:var(--gray-500);font-size:12px;">Phone</span><div>${ptEsc(p.phone)}</div></div>
        <div><span style="color:var(--gray-500);font-size:12px;">Email</span><div>${ptEsc(p.email||'—')}</div></div>
        <div><span style="color:var(--gray-500);font-size:12px;">National ID</span><div>${ptNidLabel(p.national_id)}</div></div>
      </div>

      <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-100);">
        <div style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="color:#e11d48;margin-right:4px;"></i> Location</div>
        <div style="font-size:14px;color:var(--gray-800);">${ptEsc(locationStr)}</div>
      </div>

      <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-100);display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:14px;">
        <div><span style="color:var(--gray-500);font-size:12px;">Emergency Contact</span><div>${ptEsc(p.emergency_contact||'—')}</div></div>
        <div><span style="color:var(--gray-500);font-size:12px;">Next of Kin</span><div>${ptEsc(p.next_of_kin||'—')} ${p.kin_relationship?'('+ptEsc(p.kin_relationship)+')':''}</div></div>
        ${p.allergies?`<div style="grid-column:1/-1;"><span style="color:var(--gray-500);font-size:12px;">Allergies</span><div style="color:var(--danger-color);font-weight:500;">${ptEsc(p.allergies)}</div></div>`:''}
        ${p.chronic_diseases?`<div style="grid-column:1/-1;"><span style="color:var(--gray-500);font-size:12px;">Non-communicable diseases (NCDs)</span><div>${ptEsc(p.chronic_diseases)}</div></div>`:''}
        ${p.current_medications?`<div style="grid-column:1/-1;"><span style="color:var(--gray-500);font-size:12px;">Current Medications</span><div>${ptEsc(p.current_medications)}</div></div>`:''}
        ${p.medical_alerts?`<div style="grid-column:1/-1;"><span style="color:var(--gray-500);font-size:12px;">Medical Alerts</span><div style="color:var(--warning-color);font-weight:500;">${ptEsc(p.medical_alerts)}</div></div>`:''}
      </div>

      <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-100);">
        <div style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;"><i class="fas fa-shield-alt" style="color:var(--secondary-color);margin-right:4px;"></i> Insurance</div>
        ${insHtml}
      </div>

      <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-100);display:flex;gap:10px;">
        <button onclick="ptBackToList();ptEdit(${p.id})" style="padding:8px 20px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;">
          <i class="fas fa-edit"></i> Edit Patient
        </button>
      </div>

      <!-- Visits Section -->
      <div style="margin-top:24px;padding-top:20px;border-top:2px solid var(--gray-200);">
        <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;"><i class="fas fa-history" style="color:var(--primary-color);margin-right:8px;"></i>All Visits</h3>
        <div id="pt-visits-list"><div style="text-align:center;padding:20px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading visits...</div></div>
      </div>

      <!-- Visit Details Section (hidden until a visit is clicked) -->
      <div id="pt-visit-details" style="display:none;margin-top:20px;padding-top:20px;border-top:2px solid var(--primary-color);"></div>
    `;
    // Load visits for this patient
    ptLoadVisits(p.id);
  }catch(e){document.getElementById('pt-view-body').textContent='Failed to load patient details.';}
}

async function ptLoadVisits(patientId){
  const el=document.getElementById('pt-visits-list');
  try{
    const visAPI=window.location.origin + _pBase + '/api.php?endpoint=patients/visits';
    const r=await fetch(visAPI+'&patient_id='+patientId+'&limit=100');
    const j=await r.json();
    if(!j.success){el.innerHTML='<p style="color:var(--gray-400);">Could not load visits.</p>';return;}
    const rawVisits=j.data.visits||j.data||[];
    const seen=new Set();
    const visits=rawVisits.filter(v=>{
      if(!v||!v.id||seen.has(v.id)) return false;
      seen.add(v.id);
      return true;
    });
    if(!visits.length){el.innerHTML='<p style="color:var(--gray-400);text-align:center;padding:20px;"><i class="fas fa-calendar-times" style="font-size:20px;display:block;margin-bottom:8px;"></i>No visits recorded.</p>';return;}
    const sc={open:'#f59e0b',in_progress:'#2563eb',completed:'#16a34a',cancelled:'#dc2626'};
    el.innerHTML='<div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;"><table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:var(--gray-50);"><th style="padding:10px 14px;text-align:left;">Visit No</th><th style="padding:10px 14px;text-align:left;">Date</th><th style="padding:10px 14px;text-align:left;">Department</th><th style="padding:10px 14px;text-align:left;">Type</th><th style="padding:10px 14px;text-align:left;">Status</th><th style="padding:10px 14px;text-align:center;">Action</th></tr></thead><tbody>'+visits.map(v=>{
      const color=sc[v.status]||'var(--gray-500)';
      return `<tr style="border-bottom:1px solid var(--gray-100);cursor:pointer;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
        <td style="padding:10px 14px;font-family:monospace;color:var(--primary-color);font-weight:600;">${ptEsc(v.visit_no||v.id)}</td>
        <td style="padding:10px 14px;">${ptEsc(v.visit_date||'—')}</td>
        <td style="padding:10px 14px;">${ptEsc(v.department_name||'—')}</td>
        <td style="padding:10px 14px;"><span style="padding:2px 8px;border-radius:10px;font-size:11px;background:#dbeafe;color:#1d4ed8;">${ptEsc(v.visit_type||'OPD')}</span></td>
        <td style="padding:10px 14px;"><span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:${color}20;color:${color};">${ptEsc(v.status||'—')}</span></td>
        <td style="padding:10px 14px;text-align:center;"><button onclick="ptViewVisitDetails(${v.id},${v.patient_id},'${ptEsc(v.visit_no||'')}','${ptEsc(v.patient_name||(v.first_name+' '+v.last_name)||'')}')" style="padding:5px 12px;background:#6b21a8;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;font-weight:500;"><i class="fas fa-eye"></i> Details</button></td>
      </tr>`;
    }).join('')+'</tbody></table></div>';
  }catch(e){el.innerHTML='<p style="color:#dc2626;">Error loading visits.</p>';}
}

async function ptViewVisitDetails(visitId, patientId, visitNo, patientName){
  const el=document.getElementById('pt-visit-details');
  el.style.display='block';
  el.innerHTML='<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i> Loading visit details...</div>';
  el.scrollIntoView({behavior:'smooth'});

  window._activeVisitDetailsId = visitId;
  const consultAPI=window.location.origin + _pBase + '/api.php?endpoint=consultation/consult';

  // Load visit overview
  let visitData = null;
  try {
    const vr = await fetch(window.location.origin + _pBase + '/api.php?endpoint=patients/visits&id=' + visitId);
    const vj = await vr.json();
    if(vj.success) visitData = vj.data;
  } catch(e){}

  if(window._activeVisitDetailsId !== visitId) return;

  const sc={open:'#f59e0b',in_progress:'#2563eb',completed:'#16a34a',cancelled:'#dc2626'};
  const statusColor = (visitData && sc[visitData.status]) ? sc[visitData.status] : '#6b7280';
  const statusLabel = visitData ? (visitData.status || '').toUpperCase() : '';

  let html=`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
    <h3 style="font-size:16px;font-weight:700;margin:0;color:var(--gray-900);display:flex;align-items:center;gap:8px;">
      <i class="fas fa-clipboard-list" style="color:#6b21a8;"></i>
      Visit Details: <span style="font-family:monospace;color:var(--primary-color);">${ptEsc(visitNo)}</span>
      ${statusLabel ? `<span style="font-size:11px;padding:3px 10px;border-radius:12px;font-weight:700;background:${statusColor}20;color:${statusColor};">${ptEsc(statusLabel)}</span>` : ''}
    </h3>
    <button onclick="document.getElementById('pt-visit-details').style.display='none'" style="padding:6px 14px;background:var(--gray-100);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;font-weight:500;"><i class="fas fa-times"></i> Close</button>
  </div>`;

  // Visit Metadata Card
  if(visitData){
    const insText = (visitData.payment_type === 'insurance' || visitData.payment_type === 'mixed')
      ? (visitData.company_name ? `${visitData.company_name} ${visitData.package_name ? '('+visitData.package_name+')' : ''} • Copay: ${visitData.copayment_percentage}%` : 'Insurance')
      : 'Self Pay';
    html += `
      <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:14px 18px;margin-bottom:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;font-size:13px;">
        <div>
          <span style="color:var(--gray-500);font-size:11px;text-transform:uppercase;font-weight:600;display:block;">Visit Date & Time</span>
          <strong style="color:var(--gray-800);">${ptEsc(visitData.visit_date || '—')} ${ptEsc(visitData.visit_time || '')}</strong>
        </div>
        <div>
          <span style="color:var(--gray-500);font-size:11px;text-transform:uppercase;font-weight:600;display:block;">Department</span>
          <strong style="color:var(--gray-800);">${ptEsc(visitData.department_name || '—')}</strong>
        </div>
        <div>
          <span style="color:var(--gray-500);font-size:11px;text-transform:uppercase;font-weight:600;display:block;">Doctor</span>
          <strong style="color:var(--gray-800);">${ptEsc(visitData.doctor_name || '—')}</strong>
        </div>
        <div>
          <span style="color:var(--gray-500);font-size:11px;text-transform:uppercase;font-weight:600;display:block;">Payment / Insurance</span>
          <strong style="color:var(--gray-800);">${ptEsc(insText)}</strong>
        </div>
        <div>
          <span style="color:var(--gray-500);font-size:11px;text-transform:uppercase;font-weight:600;display:block;">Visit Type</span>
          <strong style="color:var(--gray-800);">${ptEsc(visitData.visit_type ? visitData.visit_type.toUpperCase() : 'OPD')}</strong>
        </div>
        ${visitData.chief_complaint ? `<div style="grid-column:1/-1;"><span style="color:var(--gray-500);font-size:11px;text-transform:uppercase;font-weight:600;display:block;">Chief Complaint</span><span style="color:var(--gray-800);">${ptEsc(visitData.chief_complaint)}</span></div>` : ''}
      </div>
    `;
  }

  let hasAnyDetails = false;

  // Vital Signs
  try{
    const r=await fetch(window.location.origin + _pBase + '/api.php?endpoint=settings/vital-signs&action=records&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      const uniqueVitals = [];
      const seenSigns = new Set();
      j.data.forEach(v => {
        const key = v.vital_sign_id || v.sign_slug || v.sign_name;
        if(!seenSigns.has(key)){
          seenSigns.add(key);
          uniqueVitals.push(v);
        }
      });
      if(uniqueVitals.length){
        hasAnyDetails = true;
        html+=`<div style="margin-bottom:18px;"><h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-heartbeat" style="color:#dc2626;margin-right:6px;"></i>Vital Signs</h4><div style="display:flex;flex-wrap:wrap;gap:10px;">`;
        html+=uniqueVitals.map(v=>'<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:8px 14px;min-width:110px;"><div style="font-size:11px;color:var(--gray-500);font-weight:500;">'+ptEsc(v.sign_name||'Vital')+'</div><div style="font-size:14px;font-weight:700;color:var(--gray-900);margin-top:2px;">'+ptEsc(v.value||'—')+' <span style="font-size:11px;color:var(--gray-500);font-weight:400;">'+ptEsc(v.unit||'')+'</span></div></div>').join('');
        html+='</div></div>';
      }
    }
  }catch(e){}

  // Consultation
  try{
    const r=await fetch(consultAPI+'&action=consultation&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      const seenCons = new Set();
      const consults = j.data.filter(c => {
        if(!c || !c.id || seenCons.has(c.id)) return false;
        seenCons.add(c.id);
        return true;
      });
      if(consults.length){
        hasAnyDetails = true;
        for(const c of consults){
          html+=`<div style="margin-bottom:18px;"><h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-stethoscope" style="color:var(--primary-color);margin-right:6px;"></i>Consultation${c.department_name?' — '+ptEsc(c.department_name):''}</h4><div style="background:var(--gray-50);border-radius:6px;padding:12px 16px;border:1px solid var(--gray-200);font-size:13px;">`;
          if(c.chief_complaint) html+=`<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);">Chief Complaint:</span> ${ptEsc(c.chief_complaint)}</div>`;
          if(c.history) html+=`<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);">History:</span> ${ptEsc(c.history)}</div>`;
          if(c.examination) html+=`<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);">Physical Examination:</span> ${ptEsc(c.examination)}</div>`;
          if(c.differential_diagnosis) html+=`<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:#d97706;">Differential Diagnosis:</span> ${ptEsc(c.differential_diagnosis)}</div>`;
          if(c.diagnosis) html+=`<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:#7c3aed;">Final Diagnosis:</span> <strong>${ptEsc(c.diagnosis)}</strong></div>`;
          if(c.treatment_plan) html+=`<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:#0891b2;">Management & Follow Up:</span> ${ptEsc(c.treatment_plan)}</div>`;
          if(c.notes) html+=`<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);">Notes:</span> ${ptEsc(c.notes)}</div>`;
          if(c.prescription){
            let rxList=[];try{rxList=JSON.parse(c.prescription);}catch(e){}
            if(rxList.length){
              html+=`<div style="padding:8px 0;"><span style="font-weight:600;color:#16a34a;"><i class="fas fa-prescription" style="margin-right:4px;"></i>External Prescription</span><table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:6px;background:white;border-radius:4px;overflow:hidden;border:1px solid var(--gray-200);"><thead><tr style="background:#f0fdf4;"><th style="padding:6px 10px;text-align:left;color:#166534;">#</th><th style="padding:6px 10px;text-align:left;color:#166534;">Medication</th><th style="padding:6px 10px;text-align:left;color:#166534;">Dosage</th><th style="padding:6px 10px;text-align:left;color:#166534;">Frequency</th><th style="padding:6px 10px;text-align:left;color:#166534;">Duration</th></tr></thead><tbody>`+rxList.map((rx,i)=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 10px;">${i+1}</td><td style="padding:6px 10px;font-weight:500;">${ptEsc(rx.medication||'')}</td><td style="padding:6px 10px;">${ptEsc(rx.dosage||'—')}</td><td style="padding:6px 10px;">${ptEsc(rx.frequency||'—')}</td><td style="padding:6px 10px;">${ptEsc(rx.duration||'—')}</td></tr>`).join('')+`</tbody></table></div>`;
            }
          }
          html+=`<div style="font-size:11px;color:var(--gray-400);margin-top:8px;">Date: ${ptEsc(c.created_at||'—')} | Status: <strong>${ptEsc(c.status||'draft')}</strong></div>`;
          html+=`</div></div>`;
        }
      }
    }
  }catch(e){}

  // Prescriptions (internal pharmacy)
  try{
    const r=await fetch(window.location.origin + _pBase + '/api.php?endpoint=pharmacy/prescriptions&visit_id='+visitId+'&patient_id='+patientId);
    const j=await r.json();
    const rawRx = j.data?.prescriptions || j.data || [];
    if(j.success && Array.isArray(rawRx) && rawRx.length){
      const seenRx = new Set();
      const prescriptions = rawRx.filter(rx => {
        if(!rx || !rx.id || seenRx.has(rx.id)) return false;
        seenRx.add(rx.id);
        return true;
      });
      if(prescriptions.length){
        hasAnyDetails = true;
        html+=`<div style="margin-bottom:18px;"><h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-pills" style="color:#dc2626;margin-right:6px;"></i>Pharmacy Prescriptions</h4>`;
        prescriptions.forEach(rx=>{
          const prescriber = rx.prescribed_by_name || rx.created_by_name || '—';
          html+=`<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:12px 14px;margin-bottom:8px;font-size:12px;">`;
          html+=`<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
            <div style="font-weight:600;font-size:13px;"><span style="font-family:monospace;color:var(--primary-color);">${ptEsc(rx.prescription_no||rx.id)}</span> — <span style="text-transform:capitalize;">${ptEsc(rx.status||'')}</span></div>
            <div style="font-size:11px;color:var(--gray-500);"><i class="fas fa-user-md" style="margin-right:4px;"></i>${ptEsc(prescriber)}</div>
          </div>`;
          if(rx.items && rx.items.length){
            html+=`<table style="width:100%;border-collapse:collapse;font-size:12px;background:white;border-radius:4px;overflow:hidden;border:1px solid var(--gray-200);"><thead><tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);"><th style="padding:6px 8px;text-align:left;">Drug</th><th style="padding:6px 8px;text-align:center;">Qty</th><th style="padding:6px 8px;text-align:left;">Dosage</th><th style="padding:6px 8px;text-align:left;">Frequency</th><th style="padding:6px 8px;text-align:left;">Duration</th></tr></thead><tbody>`+rx.items.map(it=>`<tr style="border-bottom:1px solid var(--gray-100);"><td style="padding:6px 8px;font-weight:500;">${ptEsc(it.item_name||it.name||it.drug_name||'')}</td><td style="padding:6px 8px;text-align:center;">${it.quantity||''}</td><td style="padding:6px 8px;">${ptEsc(it.dosage||'—')}</td><td style="padding:6px 8px;">${ptEsc(it.frequency||'—')}</td><td style="padding:6px 8px;">${ptEsc(it.duration||'—')}</td></tr>`).join('')+`</tbody></table>`;
          }
          html+=`</div>`;
        });
        html+=`</div>`;
      }
    }
  }catch(e){}

  // Lab Orders
  try{
    const r=await fetch(consultAPI+'&action=lab_orders&visit_id='+visitId+'&patient_id='+patientId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      const seenOrders = new Set();
      const labOrders = j.data.filter(o => {
        if(!o || !o.id || seenOrders.has(o.id)) return false;
        seenOrders.add(o.id);
        return true;
      });
      if(labOrders.length){
        hasAnyDetails = true;
        html+=`<div style="margin-bottom:18px;"><h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-flask" style="color:#7c3aed;margin-right:6px;"></i>Lab Orders</h4>`;
        html+=labOrders.map(o=>{
          let resultHtml='<span style="color:var(--gray-400);">No results recorded yet</span>';
          if(o.result){
            let parsed=null;
            try{ parsed=JSON.parse(o.result); }catch(e){}
            if(Array.isArray(parsed)&&parsed.length){
              resultHtml='<table style="width:100%;border-collapse:collapse;font-size:11px;margin-top:6px;background:white;border-radius:4px;border:1px solid var(--gray-200);"><thead><tr style="background:#f5f3ff;"><th style="padding:5px 8px;text-align:left;color:#5b21b6;">Parameter</th><th style="padding:5px 8px;text-align:center;color:#5b21b6;">Value</th><th style="padding:5px 8px;text-align:left;color:#5b21b6;">Unit</th><th style="padding:5px 8px;text-align:left;color:#5b21b6;">Normal Range</th><th style="padding:5px 8px;text-align:center;color:#5b21b6;">Flag</th></tr></thead><tbody>';
              resultHtml+=parsed.map(param=>{
                const val=parseFloat(param.value);
                const min=parseFloat(param.normal_min);
                const max=parseFloat(param.normal_max);
                let flag='';let flagColor='';
                if(!isNaN(val)&&!isNaN(min)&&!isNaN(max)){
                  if(val<min){flag='L';flagColor='#2563eb';}
                  else if(val>max){flag='H';flagColor='#dc2626';}
                  else{flag='N';flagColor='#16a34a';}
                }
                return `<tr style="border-top:1px solid var(--gray-100);"><td style="padding:4px 8px;font-weight:500;">${ptEsc(param.test_name||param.name||'')}</td><td style="padding:4px 8px;text-align:center;font-weight:700;${flagColor?'color:'+flagColor+';':''}">${ptEsc(param.value||'—')}</td><td style="padding:4px 8px;color:var(--gray-500);">${ptEsc(param.unit||'')}</td><td style="padding:4px 8px;color:var(--gray-500);">${!isNaN(min)&&!isNaN(max)?min+' — '+max:'—'}</td><td style="padding:4px 8px;text-align:center;">${flag?'<span style="padding:1px 6px;border-radius:4px;font-size:10px;font-weight:700;background:'+flagColor+'15;color:'+flagColor+';">'+flag+'</span>':''}</td></tr>`;
              }).join('');
              resultHtml+='</tbody></table>';
            }else{
              resultHtml='<span style="color:var(--gray-900);font-weight:500;">'+ptEsc(o.result)+'</span>';
            }
          }
          const isDone = o.status==='completed';
          return `<div style="border:1px solid var(--gray-200);border-radius:6px;padding:12px 14px;margin-bottom:8px;background:var(--gray-50);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <span style="font-weight:600;font-size:13px;color:var(--gray-900);">${ptEsc(o.description||o.test_name||'')}</span>
              <span style="padding:2px 8px;border-radius:8px;font-size:11px;font-weight:600;background:${isDone?'#d1fae5':'#fef3c7'};color:${isDone?'#065f46':'#92400e'};">${ptEsc(o.status||'')}</span>
            </div>
            <div style="font-size:12px;">${resultHtml}</div>
          </div>`;
        }).join('');
        html+=`</div>`;
      }
    }
  }catch(e){}

  // Invoices / Billing
  try{
    const r=await fetch(window.location.origin + _pBase + '/api.php?endpoint=patients/billing&visit_id='+visitId);
    const j=await r.json();
    if(j.success&&j.data&&j.data.length){
      const seenInv = new Set();
      const invoices = j.data.filter(inv => {
        if(!inv || !inv.id || seenInv.has(inv.id)) return false;
        seenInv.add(inv.id);
        return true;
      });
      if(invoices.length){
        hasAnyDetails = true;
        html+=`<div style="margin-bottom:18px;"><h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;"><i class="fas fa-file-invoice-dollar" style="color:#d97706;margin-right:6px;"></i>Invoices & Billing</h4>`;
        invoices.forEach(inv=>{
          const totalAmt = parseFloat(inv.total || inv.total_amount || 0);
          const paidAmt = parseFloat(inv.paid_amount || 0);
          const balance = parseFloat(inv.balance || (totalAmt - paidAmt));
          const invStatus = inv.status || 'issued';
          const badgeColor = invStatus === 'paid' ? '#16a34a' : (invStatus === 'partial' ? '#2563eb' : '#d97706');
          html+=`<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:12px 14px;margin-bottom:8px;font-size:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div>
              <span style="font-weight:700;font-family:monospace;color:var(--primary-color);">${ptEsc(inv.invoice_no||'')}</span>
              <span style="margin-left:8px;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:${badgeColor}15;color:${badgeColor};">${ptEsc(invStatus.toUpperCase())}</span>
              <span style="color:var(--gray-500);margin-left:8px;">${ptEsc(inv.created_at||'')}</span>
            </div>
            <div style="text-align:right;">
              <span style="font-weight:700;font-size:14px;color:var(--gray-900);">${totalAmt.toLocaleString()} RWF</span>
              ${paidAmt > 0 ? `<div style="font-size:11px;color:#16a34a;">Paid: ${paidAmt.toLocaleString()} RWF ${balance > 0 ? '• Due: '+balance.toLocaleString()+' RWF' : ''}</div>` : ''}
            </div>
          </div>`;
        });
        html+=`</div>`;
      }
    }
  }catch(e){}

  if(!hasAnyDetails){
    html+=`<div style="padding:24px;text-align:center;color:var(--gray-400);background:var(--gray-50);border-radius:8px;border:1px dashed var(--gray-300);">
      <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
      No clinical records (vitals, consultation, prescriptions, lab, invoices) recorded yet for this visit.
    </div>`;
  }
  el.innerHTML=html;
}
// ============================================
// EDIT PATIENT
// ============================================
let editPatientData = null;

async function ptEdit(id){
  window._ptEditRequestId = null;
  ptOpenEditForm(id);
}

async function ptOpenEditForm(id){
  document.getElementById('pt-edit-alert').style.display='none';
  document.getElementById('pt-edit-modal').style.display='flex';
  try{
    const r=await fetch(ptAPI+'&id='+id);
    const j=await r.json();
    if(!j.success){alert('Error loading patient');return;}
    editPatientData = j.data;
    const p=j.data;
    document.getElementById('edit-patient-id').value=p.id;
    document.getElementById('edit-first_name').value=p.first_name||'';
    document.getElementById('edit-last_name').value=p.last_name||'';
    document.getElementById('edit-date_of_birth').value=p.date_of_birth||'';
    document.getElementById('edit-gender').value=p.gender||'male';
    document.getElementById('edit-national_id').value=p.national_id||'';
    document.getElementById('edit-tariff_category').value=p.tariff_category||'rwanda';
    ['allergies','chronic_diseases','current_medications','medical_alerts'].forEach(f=>{ document.getElementById('edit-'+f).value=p[f]||''; });
    document.querySelectorAll('#edit-allergies ~ .med-chip-none input').forEach(c=>{ c.checked=/^no known allergies$/i.test(p.allergies||''); });
    medChipsRefresh();
    document.getElementById('edit-phone').value=p.phone||'';
    document.getElementById('edit-email').value=p.email||'';
    document.getElementById('edit-emergency_contact').value=p.emergency_contact||'';
    document.getElementById('edit-next_of_kin').value=p.next_of_kin||'';
    document.getElementById('edit-kin_relationship').value=p.kin_relationship||'';
    document.getElementById('edit-status').value=p.status||'active';
    document.getElementById('edit-notes').value=p.notes||'';

    // Load location cascading
    await editLoadProvinces(p.province_id, p.district_id, p.sector_id, p.cell_id, p.village_id);
  }catch(e){alert('Failed to load patient: '+e.message);}
}

// Location cascading for edit
async function editLoadProvinces(selProv, selDist, selSec, selCell, selVil){
  const sel=document.getElementById('edit-province_id');
  try{
    const r=await fetch(ptLocAPI+'&type=provinces');
    const j=await r.json();
    if(j.success) sel.innerHTML='<option value="">— Select —</option>'+j.data.map(p=>`<option value="${p.id}">${p.name}</option>`).join('');
  }catch(e){}
  if(selProv){sel.value=selProv;await editLoadDistricts(selProv,selDist,selSec,selCell,selVil);}
}
async function editLoadDistricts(provId,selDist,selSec,selCell,selVil){
  const sel=document.getElementById('edit-district_id');
  if(!provId){sel.innerHTML='<option value="">— Select Province —</option>';return;}
  try{
    const r=await fetch(ptLocAPI+'&type=districts&parent_id='+provId);
    const j=await r.json();
    if(j.success) sel.innerHTML='<option value="">— Select —</option>'+j.data.map(d=>`<option value="${d.id}">${d.name}</option>`).join('');
  }catch(e){}
  if(selDist){sel.value=selDist;await editLoadSectors(selDist,selSec,selCell,selVil);}
}
async function editLoadSectors(distId,selSec,selCell,selVil){
  const sel=document.getElementById('edit-sector_id');
  if(!distId){sel.innerHTML='<option value="">— Select District —</option>';return;}
  try{
    const r=await fetch(ptLocAPI+'&type=sectors&parent_id='+distId);
    const j=await r.json();
    if(j.success) sel.innerHTML='<option value="">— Select —</option>'+j.data.map(s=>`<option value="${s.id}">${s.name}</option>`).join('');
  }catch(e){}
  if(selSec){sel.value=selSec;await editLoadCells(selSec,selCell,selVil);}
}
async function editLoadCells(secId,selCell,selVil){
  const sel=document.getElementById('edit-cell_id');
  if(!secId){sel.innerHTML='<option value="">— Select Sector —</option>';return;}
  try{
    const r=await fetch(ptLocAPI+'&type=cells&parent_id='+secId);
    const j=await r.json();
    if(j.success) sel.innerHTML='<option value="">— Select —</option>'+j.data.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
  }catch(e){}
  if(selCell){sel.value=selCell;await editLoadVillages(selCell,selVil);}
}
async function editLoadVillages(cellId,selVil){
  const sel=document.getElementById('edit-village_id');
  if(!cellId){sel.innerHTML='<option value="">— Select Cell —</option>';return;}
  try{
    const r=await fetch(ptLocAPI+'&type=villages&parent_id='+cellId);
    const j=await r.json();
    if(j.success) sel.innerHTML='<option value="">— Select —</option>'+j.data.map(v=>`<option value="${v.id}">${v.name}</option>`).join('');
  }catch(e){}
  if(selVil) sel.value=selVil;
}

// Submit edit form
async function ptEditSubmit(e){
  e.preventDefault();
  const id=document.getElementById('edit-patient-id').value;
  const al=document.getElementById('pt-edit-alert');
  const btn=document.getElementById('pt-edit-save-btn');
  al.style.display='none';
  btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';

  const body={
    loaded_updated_at: (editPatientData && editPatientData.updated_at) || null,
    first_name: document.getElementById('edit-first_name').value.trim(),
    last_name: document.getElementById('edit-last_name').value.trim(),
    date_of_birth: document.getElementById('edit-date_of_birth').value,
    gender: document.getElementById('edit-gender').value,
    national_id: document.getElementById('edit-national_id').value.trim(),
    tariff_category: document.getElementById('edit-tariff_category').value,
    allergies: document.getElementById('edit-allergies').value.trim(),
    chronic_diseases: document.getElementById('edit-chronic_diseases').value.trim(),
    current_medications: document.getElementById('edit-current_medications').value.trim(),
    medical_alerts: document.getElementById('edit-medical_alerts').value.trim(),
    phone: document.getElementById('edit-phone').value.trim(),
    email: document.getElementById('edit-email').value.trim(),
    province_id: document.getElementById('edit-province_id').value||null,
    district_id: document.getElementById('edit-district_id').value||null,
    sector_id: document.getElementById('edit-sector_id').value||null,
    cell_id: document.getElementById('edit-cell_id').value||null,
    village_id: document.getElementById('edit-village_id').value||null,
    emergency_contact: document.getElementById('edit-emergency_contact').value.trim(),
    next_of_kin: document.getElementById('edit-next_of_kin').value.trim(),
    kin_relationship: document.getElementById('edit-kin_relationship').value,
    status: document.getElementById('edit-status').value,
    notes: document.getElementById('edit-notes').value.trim(),
    edit_request_id: window._ptEditRequestId || null,
    edited_by: <?php echo (int)($userId ?? 0); ?>
  };

  try{
    const r=await fetch(ptAPI+'&id='+id,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){
      al.innerHTML='<i class="fas fa-check-circle"></i> Patient updated successfully!';
      al.style.cssText='display:block;padding:12px 16px;border-radius:8px;font-size:14px;background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;margin-bottom:16px;';
      ptLoad(); // Refresh list
      setTimeout(()=>{document.getElementById('pt-edit-modal').style.display='none';},1200);
    } else {
      al.innerHTML='<i class="fas fa-exclamation-circle"></i> '+(j.message||'Update failed');
      al.style.cssText='display:block;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin-bottom:16px;';
    }
  }catch(err){
    al.innerHTML='<i class="fas fa-exclamation-circle"></i> Request failed: '+err.message;
    al.style.cssText='display:block;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;margin-bottom:16px;';
  }finally{
    btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save Changes';
  }
}

async function ptDelete(id,name){
  // Check if there's already a pending request
  try{
    const chk=await fetch(window.location.origin + _pBase + '/api.php?endpoint=deletion-requests&action=check&entity_type=patient&entity_id='+id);
    const cj=await chk.json();
    if(cj.success && cj.data){
      if(cj.data.status==='pending') return alert('A deletion request is already pending for this patient.');
      if(cj.data.status==='approved'){
        if(confirm('This deletion request has been APPROVED. Do you want to execute the deletion now?\n\nPatient: '+name+'\n\nThis action cannot be undone.')){
          ptExecuteDelete(cj.data.id);
        }
        return;
      }
    }
  }catch(e){}

  // Show request deletion modal
  const modal=document.createElement('div');modal.id='pt-del-modal';modal.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;padding:16px;';
  modal.innerHTML=`<div style="background:white;border-radius:12px;width:550px;max-width:95vw;max-height:85vh;overflow-y:auto;padding:24px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:16px;">
      <h3 style="font-size:16px;font-weight:600;color:#dc2626;"><i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>Request Deletion</h3>
      <button onclick="document.getElementById('pt-del-modal').remove()" style="border:none;background:none;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;color:#991b1b;">
      <strong>Patient:</strong> ${ptEsc(name)} (ID: ${id})<br>
      <small>This request will be sent to an administrator for review. The record will only be deleted after approval.</small>
    </div>
    <div style="margin-bottom:14px;">
      <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">Reason / Remarks <span style="color:#dc2626;">*</span></label>
      <textarea id="del-remarks" rows="4" placeholder="Provide a detailed reason for deletion (minimum 10 characters)..." style="width:100%;padding:10px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;resize:vertical;"></textarea>
    </div>
    <div style="margin-bottom:16px;">
      <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;color:var(--gray-700);">Proof Document <span style="color:var(--gray-400);font-weight:400;">(optional - pdf, jpg, png, doc)</span></label>
      <input type="file" id="del-proof" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:8px;font-size:12px;">
    </div>
    <div id="del-alert" style="display:none;margin-bottom:12px;padding:10px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <button id="del-submit-btn" onclick="ptSubmitDeleteRequest(${id},'${ptEsc(name).replace(/'/g,"\\'")}')" style="width:100%;padding:12px;background:#dc2626;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;"><i class="fas fa-paper-plane"></i> Submit Deletion Request</button>
  </div>`;
  document.body.appendChild(modal);
}

async function ptSubmitDeleteRequest(id, name){
  const remarks=document.getElementById('del-remarks').value.trim();
  const proofFile=document.getElementById('del-proof').files[0];
  const alert_el=document.getElementById('del-alert');
  const btn=document.getElementById('del-submit-btn');
  alert_el.style.display='none';
  if(!remarks||remarks.length<10){alert_el.textContent='Please provide detailed remarks (at least 10 characters).';alert_el.style.display='block';return;}
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Submitting...';
  const fd=new FormData();
  fd.append('entity_type','patient');
  fd.append('entity_id',id);
  fd.append('entity_name',name);
  fd.append('remarks',remarks);
  fd.append('requested_by',<?php echo (int)($_SESSION['user_id'] ?? 0); ?>);
  if(proofFile) fd.append('proof_document',proofFile);
  try{
    const r=await fetch(window.location.origin + _pBase + '/api.php?endpoint=deletion-requests&action=create',{method:'POST',body:fd});
    const j=await r.json();
    if(j.success){
      document.getElementById('pt-del-modal').remove();
      alert('✓ Deletion request submitted successfully!\nAn administrator will review your request.');
    }else{alert_el.textContent=j.message;alert_el.style.display='block';}
  }catch(e){alert_el.textContent='Request failed. Try again.';alert_el.style.display='block';}
  finally{btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Submit Deletion Request';}
}

async function ptExecuteDelete(requestId){
  try{
    const r=await fetch(window.location.origin + _pBase + '/api.php?endpoint=deletion-requests&action=execute',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:requestId,executed_by:<?php echo (int)($_SESSION['user_id'] ?? 0); ?>})});
    const j=await r.json();
    if(j.success){alert('✓ '+j.message);ptLoad();}else{alert('Error: '+j.message);}
  }catch(e){alert('Deletion failed');}
}
function ptReset(){
  document.getElementById('pt-search').value='';
  document.getElementById('pt-filter-status').value='';
  document.getElementById('pt-date-from').value='';
  document.getElementById('pt-date-to').value='';
  const provSel = document.getElementById('pt-filter-province');
  if(provSel) provSel.value = '';
  const distSel = document.getElementById('pt-filter-district');
  if(distSel){ distSel.disabled = false; distSel.value = ''; }
  const secSel = document.getElementById('pt-filter-sector');
  if(secSel){ secSel.disabled = false; secSel.innerHTML = '<option value="">All Sectors</option>'; }
  ptLoadAllDistricts();
  ptSearch=''; ptFilterStatus=''; ptPage=1; ptLoad();
}

// Export patients to Excel
async function ptExportExcel(){
  const dateFrom=document.getElementById('pt-date-from')?.value||'';
  const dateTo=document.getElementById('pt-date-to')?.value||'';
  const provVal=document.getElementById('pt-filter-province')?.value||'';
  const distVal=document.getElementById('pt-filter-district')?.value||'';
  const secVal=document.getElementById('pt-filter-sector')?.value||'';

  const p=new URLSearchParams({limit:99999,search:ptSearch,status:ptFilterStatus});
  if(dateFrom) p.append('date_from',dateFrom);
  if(dateTo) p.append('date_to',dateTo);
  if(provVal){
    if(['catchment','outside','international'].includes(provVal)){
      p.append('zone',provVal);
    } else {
      p.append('province_id',provVal);
    }
  }
  if(distVal) p.append('district_id',distVal);
  if(secVal) p.append('sector_id',secVal);

  try{
    const r=await fetch(ptAPI+'&'+p);
    const j=await r.json();
    if(!j.success){alert('Failed to load patient data');return;}
    const patients=j.data.patients||[];
    if(!patients.length){alert('No patients to export');return;}

    function esc(v){return '"'+(String(v||'').replace(/"/g,'""'))+'"';}
    // Force text format for fields Excel may interpret as numbers (IDs, phone, policy numbers)
    function escNum(v){return '="'+(String(v||'').replace(/"/g,'""'))+'"';}

    const headers=[
      'Patient No','First Name','Last Name','Date of Birth','Gender',
      'National ID / Passport','Phone','Email',
      'Province','District','Sector','Cell','Village',
      'Emergency Contact','Next of Kin','Kin Relationship',
      'Insurance Company','Insurance Package','Member Number',
      'Status','Notes','Registration Date'
    ];

    let csv=headers.join(',')+'\n';
    patients.forEach(pt=>{
      const row=[
        escNum(pt.patient_no),
        esc(pt.first_name),
        esc(pt.last_name),
        esc(pt.date_of_birth),
        esc(pt.gender),
        escNum(pt.national_id),
        escNum(pt.phone),
        esc(pt.email),
        esc(pt.province_name||''),
        esc(pt.district_name||''),
        esc(pt.sector_name||''),
        esc(pt.cell_name||''),
        esc(pt.village_name||''),
        escNum(pt.emergency_contact),
        esc(pt.next_of_kin),
        esc(pt.kin_relationship),
        esc(pt.insurance_company),
        esc(pt.insurance_package),
        escNum(pt.member_number),
        esc(pt.status),
        esc(pt.notes),
        esc((pt.created_at||'').substring(0,10))
      ];
      csv+=row.join(',')+'\n';
    });

    const blob=new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8;'});
    const url=URL.createObjectURL(blob);
    const link=document.createElement('a');
    link.href=url;
    link.download='patients_export_'+new Date().toISOString().split('T')[0]+'.csv';
    link.click();
    URL.revokeObjectURL(url);
  } catch(e){
    alert('Export failed: '+e.message);
  }
}

async function ptInitLocationFilters(){
  const provGroup = document.getElementById('pt-optgroup-provinces');
  try {
    const rProv = await fetch(ptLocAPI + '&type=provinces');
    const jProv = await rProv.json();
    if (jProv.success && provGroup) {
      provGroup.innerHTML = jProv.data.map(p => `<option value="${p.id}">${ptEsc(p.name)}</option>`).join('');
    }
  } catch(e) {}
  await ptLoadAllDistricts();
}

async function ptLoadAllDistricts(){
  const distSel = document.getElementById('pt-filter-district');
  if (!distSel) return;
  try {
    const rDist = await fetch(ptLocAPI + '&type=districts');
    const jDist = await rDist.json();
    if (jDist.success) {
      distSel.innerHTML = '<option value="">All Districts</option>' +
        jDist.data.map(d => `<option value="${d.id}">${ptEsc(d.name)}</option>`).join('');
    }
  } catch(e) {}
}

async function ptOnProvinceChange(){
  const provSel = document.getElementById('pt-filter-province');
  const distSel = document.getElementById('pt-filter-district');
  const secSel  = document.getElementById('pt-filter-sector');
  const val     = provSel?.value || '';

  if (val === 'catchment' || val === 'outside' || val === 'international') {
    if (distSel) { distSel.innerHTML = '<option value="">— N/A for Zone —</option>'; distSel.disabled = true; }
    if (secSel)  { secSel.innerHTML  = '<option value="">— N/A for Zone —</option>'; secSel.disabled  = true; }
  } else {
    if (distSel) distSel.disabled = false;
    if (secSel)  { secSel.disabled  = false; secSel.innerHTML = '<option value="">All Sectors</option>'; }
    if (val) {
      if (distSel) distSel.innerHTML = '<option value="">Loading…</option>';
      try {
        const r = await fetch(ptLocAPI + '&type=districts&parent_id=' + val);
        const j = await r.json();
        if (j.success && distSel) {
          distSel.innerHTML = '<option value="">All Districts in Province</option>' +
            j.data.map(d => `<option value="${d.id}">${ptEsc(d.name)}</option>`).join('');
        }
      } catch(e) {
        if (distSel) distSel.innerHTML = '<option value="">All Districts</option>';
      }
    } else {
      await ptLoadAllDistricts();
    }
  }
  ptPage = 1;
  ptLoad();
}

async function ptOnDistrictChange(){
  const distSel = document.getElementById('pt-filter-district');
  const secSel  = document.getElementById('pt-filter-sector');
  const distId  = distSel?.value || '';

  if (secSel) secSel.innerHTML = '<option value="">All Sectors</option>';
  if (distId) {
    if (secSel) secSel.innerHTML = '<option value="">Loading…</option>';
    try {
      const r = await fetch(ptLocAPI + '&type=sectors&parent_id=' + distId);
      const j = await r.json();
      if (j.success && j.data.length && secSel) {
        secSel.innerHTML = '<option value="">All Sectors in District</option>' +
          j.data.map(s => `<option value="${s.id}">${ptEsc(s.name)}</option>`).join('');
      } else if (secSel) {
        secSel.innerHTML = '<option value="">All Sectors</option>';
      }
    } catch(e) {
      if (secSel) secSel.innerHTML = '<option value="">All Sectors</option>';
    }
  }
  ptPage = 1;
  ptLoad();
}

function ptOnSectorChange(){
  ptPage = 1;
  ptLoad();
}

let ptTimer;
document.getElementById('pt-search').addEventListener('input',function(){clearTimeout(ptTimer);ptTimer=setTimeout(()=>{ptSearch=this.value;ptPage=1;ptLoad();},350);});
document.getElementById('pt-filter-status').addEventListener('change',function(){ptFilterStatus=this.value;ptPage=1;ptLoad();});
ptInitLocationFilters();
ptLoad();
</script>

<?php elseif ($subModule == 'open-visit'): ?>
<?php require_once __DIR__ . '/includes/visit_tabs.php'; visit_tabs($pdo, 'open'); ?>

<!-- ============================================
OPEN VISIT — Step 1: Search Patient
============================================ -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:flex-start;" id="ov-layout">

  <!-- LEFT: Search Panel -->
  <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
    <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:14px;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-search" style="color:var(--primary-color);"></i> Search Patient
    </h3>
    <div style="display:flex;gap:8px;margin-bottom:12px;">
      <input id="ov-search-input" type="text" placeholder="Name, UHID, phone…"
        style="flex:1;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"
        onkeydown="if(event.key==='Enter')ovSearch()">
      <button onclick="ovSearch()" style="padding:10px 14px;background:var(--primary-color);color:white;border:none;border-radius:8px;cursor:pointer;">
        <i class="fas fa-search"></i>
      </button>
    </div>
    <div id="ov-search-results" style="max-height:420px;overflow-y:auto;"></div>
    <div id="ov-search-hint" style="text-align:center;padding:30px 0;color:var(--gray-400);font-size:13px;">
      <i class="fas fa-user-search" style="font-size:28px;display:block;margin-bottom:8px;"></i>
      Type to search patients
    </div>
  </div>

  <!-- RIGHT: Patient Card + Insurance Check + Confirm -->
  <div id="ov-right-panel">

    <!-- Placeholder when no patient selected -->
    <div id="ov-placeholder" style="background:white;border-radius:var(--radius);padding:48px;box-shadow:var(--shadow);text-align:center;color:var(--gray-400);">
      <i class="fas fa-door-open" style="font-size:48px;display:block;margin-bottom:12px;color:var(--gray-200);"></i>
      <div style="font-size:16px;font-weight:600;margin-bottom:6px;">Select a Patient</div>
      <div style="font-size:13px;">Search and select a patient on the left to open a new visit</div>
    </div>

    <!-- Patient Card + Visit Form (hidden until patient selected) -->
    <div id="ov-patient-card" style="display:none;">

      <!-- Patient Info -->
      <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
          <div style="width:52px;height:52px;border-radius:50%;background:var(--primary-color);color:white;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0;" id="ov-avatar">?</div>
          <div style="flex:1;">
            <div style="font-size:18px;font-weight:700;color:var(--gray-900);" id="ov-pt-name">—</div>
            <div style="font-size:13px;color:var(--primary-color);font-family:monospace;font-weight:600;" id="ov-pt-no">—</div>
          </div>
          <button onclick="ovClearPatient()" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:18px;" title="Clear"><i class="fas fa-times"></i></button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;font-size:13px;">
          <div><span style="color:var(--gray-500);">Gender:</span> <strong id="ov-pt-gender">—</strong></div>
          <div><span style="color:var(--gray-500);">Age:</span> <strong id="ov-pt-age">—</strong></div>
          <div><span style="color:var(--gray-500);">Phone:</span> <strong id="ov-pt-phone">—</strong></div>
          <div><span style="color:var(--gray-500);">Status:</span> <strong id="ov-pt-status">—</strong></div>
          <div><span style="color:var(--gray-500);">National ID:</span> <strong id="ov-pt-nid">—</strong></div>
          <div style="grid-column:1/-1;padding-top:8px;border-top:1px dashed var(--gray-200);display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <div id="ov-pt-insurance-wrapper" style="display:none;">
              <span style="color:var(--gray-600);font-weight:600;"><i class="fas fa-shield-alt" style="color:#2563eb;margin-right:4px;"></i>Insurance:</span>
              <span id="ov-pt-insurance-badge" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;margin-left:4px;">—</span>
            </div>
            <div>
              <span style="color:var(--gray-600);font-weight:600;"><i class="fas fa-id-card" style="color:var(--secondary-color);margin-right:4px;"></i>Member Number / Card No:</span>
              <strong id="ov-pt-member-no" style="font-family:monospace;font-size:13px;color:var(--primary-color);background:#eff6ff;padding:3px 10px;border-radius:6px;border:1px solid #bfdbfe;margin-left:4px;">—</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- Insurance Status Banner -->
      <div id="ov-limit-box" style="display:none;border-radius:var(--radius);padding:14px 18px;margin-bottom:16px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;"></div>
      <div id="ov-ins-banner" style="border-radius:var(--radius);padding:16px 18px;margin-bottom:16px;display:none;">
        <div style="display:flex;align-items:center;gap:10px;">
          <i id="ov-ins-icon" style="font-size:20px;flex-shrink:0;"></i>
          <div style="flex:1;">
            <div style="font-weight:700;font-size:14px;" id="ov-ins-title">—</div>
            <div style="font-size:13px;margin-top:2px;" id="ov-ins-note">—</div>
            <div id="ov-ins-card-info" style="display:none;font-size:12px;margin-top:5px;">
              <strong>Member Number / Card No:</strong> <span id="ov-ins-card-no" style="font-family:monospace;font-weight:700;background:rgba(255,255,255,0.7);padding:2px 8px;border-radius:4px;border:1px solid currentColor;">—</span>
            </div>
          </div>
          <span id="ov-ins-badge" style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;"></span>
        </div>
      </div>

      <!-- Visit Form -->
      <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
        <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
          <i class="fas fa-clipboard-list" style="color:var(--success-color);"></i> Visit Details
        </h3>
        <div id="ov-appt-confirm" style="display:none;margin-bottom:14px;padding:14px 16px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;font-size:13px;"></div>
        <div id="ov-form-alert" style="display:none;margin-bottom:14px;padding:12px 16px;border-radius:8px;font-size:14px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
        <div id="ov-tariff-note" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px;"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Payment Type <span style="color:var(--danger-color)">*</span></label>
            <select id="ov-payment-type" onchange="ovUpdatePayment()" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="self_pay">Self Pay</option>
              <option value="insurance">Insurance</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Select Service <span style="color:var(--danger-color)">*</span></label>
            <select id="ov-department" onchange="ovLoadDoctors()" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">— Select Service —</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Consultation Fee <span style="color:var(--danger-color)">*</span> <span style="color:var(--gray-400);font-weight:400;">(invoiced automatically)</span></label>
            <select id="ov-consult-fee" disabled style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">— Select a service first —</option>
            </select>
            <div id="ov-consult-rule" class="cr-note" style="display:none;"></div>
          </div>
          <div style="grid-column:1/-1;display:none;">
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Chief Complaint</label>
            <input id="ov-complaint" type="text" placeholder="e.g. Headache, Chest pain, Routine checkup…"
              style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Referred from <span style="color:var(--gray-400);font-weight:400;">(optional, for the HMIS report)</span></label>
            <select id="ov-referred-from" onchange="document.getElementById('ov-referred-from-name').style.display=this.value?'block':'none'" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">Not referred</option>
              <option value="facility">Another health facility (counter-referral)</option>
              <option value="school_counselor">School counselor</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Referring facility / counselor name</label>
            <input id="ov-referred-from-name" type="text" maxlength="150" placeholder="e.g. Nyamata District Hospital" style="display:none;width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;">
          </div>
        </div>
        <!-- Self-pay warning when insurance invalid -->
        <div id="ov-selfpay-warning" style="display:none;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:#9a3412;">
          <i class="fas fa-exclamation-triangle"></i> <strong>Insurance not valid</strong> — visit will be recorded as <strong>Self Pay</strong>.
          <label style="margin-left:10px;cursor:pointer;">
            <input type="checkbox" id="ov-force-insurance"> Force insurance anyway
          </label>
        </div>
        <!-- Insurance Selection (shown when insurance or mixed selected) -->
        <div id="ov-ins-selection" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:14px;margin-bottom:14px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="display:block;font-size:12px;font-weight:600;color:#1e40af;margin-bottom:4px;">Insurance Company <span style="color:var(--danger-color);">*</span></label>
              <select id="ov-insurance-company" onchange="ovLoadPlans()" required style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
                <option value="">Select insurance...</option>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:12px;font-weight:600;color:#1e40af;margin-bottom:4px;">Plan / Package <span style="color:var(--danger-color);">*</span></label>
              <select id="ov-insurance-plan" required style="width:100%;padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
                <option value="">Select plan...</option>
              </select>
            </div>
          </div>
          <div style="margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:flex-end;">
            <div>
              <label style="display:block;font-size:12px;font-weight:600;color:#1e40af;margin-bottom:4px;">Member Number / Card No. <span style="color:var(--danger-color);">*</span></label>
              <input id="ov-insurance-member-no" type="text" required placeholder="Member Number / Card No." style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
            </div>
            <div id="ov-ins-rate-info" style="display:none;font-size:12px;color:#1e40af;font-weight:500;padding-bottom:8px;"></div>
          </div>
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid #bfdbfe;">
            <div style="font-size:12px;font-weight:700;color:#1e40af;margin-bottom:8px;"><i class="fas fa-user-friends"></i> Principal Member Details <span style="font-weight:400;color:#64748b;">(if patient is a dependant)</span></div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;">
              <div><label style="display:block;font-size:12px;color:var(--gray-600);margin-bottom:4px;">Principal Member Name</label><input id="ov-principal-member-name" type="text" placeholder="Full name of principal member" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;box-sizing:border-box;"></div>
              <div><label style="display:block;font-size:12px;color:var(--gray-600);margin-bottom:4px;">Principal Phone Number</label><input id="ov-principal-phone" type="tel" placeholder="+250 xxx xxx xxx" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;box-sizing:border-box;"></div>
              <div><label style="display:block;font-size:12px;color:var(--gray-600);margin-bottom:4px;">Principal Member Number / Card No.</label><input id="ov-principal-member-number" type="text" placeholder="Principal's member/card number" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;box-sizing:border-box;"></div>
              <div><label style="display:block;font-size:12px;color:var(--gray-600);margin-bottom:4px;">Employer Name</label><input id="ov-employer-name" type="text" placeholder="Employer name" style="width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;box-sizing:border-box;"></div>
            </div>
          </div>
          <div id="ov-extra-ins"></div>
          <div style="font-size:11px;color:#64748b;margin-top:4px;">With several insurances, each invoice is assigned to one of them at invoicing.</div>
        </div>
        <!-- Additional (moved here from registration) -->
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:14px;margin-bottom:16px;">
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Patient Status</label>
            <select id="ov-patient-status" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="active">Active (Outpatient)</option>
              <option value="admitted">Admitted</option>
              <option value="critical">Critical</option>
            </select>
          </div>
          <div>
            <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Notes</label>
            <textarea id="ov-notes" rows="1" placeholder="Any additional notes" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;resize:vertical;box-sizing:border-box;"></textarea>
          </div>
        </div>
        <button id="ov-open-btn" onclick="ovOpenVisit()" style="width:100%;padding:13px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
          <i class="fas fa-door-open"></i> <span id="ov-open-label">Open Visit</span>
        </button>
      </div>

    </div><!-- /ov-patient-card -->
  </div><!-- /ov-right-panel -->
</div><!-- /ov-layout -->

<!-- Visit Opened Success Card -->
<div id="ov-success-card" style="display:none;background:white;border-radius:var(--radius);padding:32px;box-shadow:var(--shadow);text-align:center;max-width:560px;margin:0 auto;">
  <div style="width:64px;height:64px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
    <i class="fas fa-check" style="font-size:28px;color:var(--success-color);"></i>
  </div>
  <h3 style="font-size:20px;font-weight:700;color:var(--gray-900);margin-bottom:6px;">Visit Opened!</h3>
  <div style="font-size:13px;color:var(--gray-500);margin-bottom:16px;" id="ov-success-patient">—</div>
  <div style="background:var(--gray-50);border-radius:10px;padding:16px;margin-bottom:20px;">
    <div style="font-size:12px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Visit Number</div>
    <div style="font-size:28px;font-weight:800;color:var(--primary-color);font-family:monospace;letter-spacing:3px;" id="ov-success-visit-no">—</div>
    <div style="font-size:13px;color:var(--gray-500);margin-top:4px;" id="ov-success-payment">—</div>
  </div>
  <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
    <button onclick="ovNewVisit()" style="padding:10px 24px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
      <i class="fas fa-plus"></i> Open Another Visit
    </button>
    <a href="patients.php?module=receptionist&sub=manage" style="padding:10px 24px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;text-decoration:none;">
      Back to Patients
    </a>
  </div>
</div>

<?php endif; ?>

<?php if ($subModule == 'open-visit'): ?>
<!-- Today's Visits -->
<div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);margin-top:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
    <h3 style="font-size:15px;font-weight:600;"><i class="fas fa-list" style="color:var(--primary-color);"></i> Visits</h3>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <button onclick="ovExportExcel()" style="padding:6px 12px;background:#1d6f42;color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;display:flex;align-items:center;gap:5px;" title="Export current results to Excel">
        <i class="fas fa-file-excel"></i> Export Excel
      </button>
      <button onclick="ovLoadTodayVisits()" style="padding:6px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-sync-alt"></i></button>
    </div>
  </div>
  <!-- Filter bar: search + date range -->
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px;padding:10px 14px;background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-200);">
    <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:180px;background:white;border:1px solid var(--gray-200);border-radius:6px;padding:5px 10px;">
      <i class="fas fa-search" style="color:var(--gray-400);font-size:12px;"></i>
      <input id="ov-search-filter" type="text" placeholder="Search name, phone, insurance, card #…"
        style="border:none;outline:none;font-size:13px;width:100%;background:transparent;"
        oninput="clearTimeout(ovFilterTimer);ovFilterTimer=setTimeout(ovLoadTodayVisits,350)">
    </div>
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
      <label style="font-size:12px;color:var(--gray-600);font-weight:500;white-space:nowrap;">From</label>
      <input type="date" id="ov-date-from" onchange="ovLoadTodayVisits()"
        style="padding:5px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;background:white;">
      <label style="font-size:12px;color:var(--gray-600);font-weight:500;white-space:nowrap;">To</label>
      <input type="date" id="ov-date-to" onchange="ovLoadTodayVisits()"
        style="padding:5px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;background:white;">
      <label style="font-size:12px;color:var(--gray-600);font-weight:500;white-space:nowrap;">Status</label>
      <select id="ov-status-filter" onchange="ovLoadTodayVisits()"
        style="padding:5px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;background:white;">
        <option value="">All</option><option value="open">Open</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option>
      </select>
      <button onclick="ovClearFilters()" style="padding:5px 10px;background:white;border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:12px;color:var(--gray-600);" title="Clear filters">
        <i class="fas fa-times"></i> Clear
      </button>
    </div>
  </div>
  <div class="ov-visits-scroll" style="border:1px solid var(--gray-200);border-radius:8px;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);">
        <th style="padding:10px;text-align:left;">Date / Time</th>
        <th style="padding:10px;text-align:left;">Patient</th>
        <th style="padding:10px;text-align:left;">Visit No.</th>
        <th style="padding:10px;text-align:left;">Insurance & Member / Card No.</th>
        <th style="padding:10px;text-align:center;">Type</th>
        <th style="padding:10px;text-align:center;">Status</th>
        <th style="padding:10px;text-align:left;">Tracking</th>
        <th style="padding:10px;text-align:center;">Action</th>
      </tr></thead>
      <tbody id="ov-today-tbody"><tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>
    </table>
  </div>
</div>

<!-- External Prescription Modal -->
<div id="ov-rx-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:16px;overflow-y:auto;">
  <div style="background:white;border-radius:12px;width:100%;max-width:820px;max-height:92vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;margin:auto;">
    <!-- Modal Header -->
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;background:var(--gray-50);border-radius:12px 12px 0 0;flex-wrap:wrap;gap:10px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">
          <i class="fas fa-file-prescription"></i>
        </div>
        <div>
          <h3 style="font-size:16px;font-weight:700;color:var(--gray-900);margin:0;" id="ov-rx-modal-title">External Prescription</h3>
          <div style="font-size:12px;color:var(--gray-500);" id="ov-rx-modal-subtitle">Medications prescribed for external pharmacy</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button onclick="ovDownloadRxPDF()" id="ov-rx-btn-download" style="padding:8px 16px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
          <i class="fas fa-file-pdf"></i> Download PDF
        </button>
        <button onclick="ovPrintRxBrowser()" style="padding:8px 14px;background:#2563eb;color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
          <i class="fas fa-print"></i> Print
        </button>
        <button onclick="ovCloseRxModal()" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:18px;padding:4px 8px;" title="Close">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
    <!-- Modal Body / Printable Document Container -->
    <div style="padding:20px;overflow-y:auto;background:#f1f5f9;display:flex;justify-content:center;">
      <div id="ov-rx-printable-wrap" style="width:100%;max-width:780px;">
        <div id="ov-rx-printable">
          <div style="text-align:center;padding:50px;color:var(--gray-400);font-family:sans-serif;background:white;border-radius:8px;border:1px solid #e2e8f0;"><i class="fas fa-spinner fa-spin" style="font-size:28px;color:#16a34a;"></i><div style="margin-top:10px;font-size:13px;font-weight:500;">Loading prescription...</div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const ovPatAPI = window.location.origin + _pBase + '/api.php?endpoint=patients/patients';
const ovVisAPI = window.location.origin + _pBase + '/api.php?endpoint=patients/visits';
let ovSelectedPatient = null;
let ovInsuranceData   = null;
const ovIsAdmin = <?php echo (isset($_SESSION['user_data']['is_admin']) && $_SESSION['user_data']['is_admin']) || (isset($_SESSION['user_data']['role_slugs']) && in_array('admin', $_SESSION['user_data']['role_slugs'])) ? 'true' : 'false'; ?>;

function ovEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function ovAge(dob){
  if(!dob) return '—';
  const d=new Date(dob),n=new Date();
  let y=n.getFullYear()-d.getFullYear();
  if(n.getMonth()<d.getMonth()||(n.getMonth()===d.getMonth()&&n.getDate()<d.getDate()))y--;
  return y+'y';
}

let ovAppointment = null;   // follow-up appointment whose arrival this visit confirms
(function(){
  const params=new URLSearchParams(window.location.search);
  const pno=params.get('patient_no');
  if(pno){ document.getElementById('ov-search-input').value=pno; ovSearch(); }
  const apId=params.get('appointment_id');
  if(apId) ovLoadAppointment(apId);
})();

async function ovLoadAppointment(apId){
  const box=document.createElement('div');
  box.id='ov-appt-banner';
  box.style.cssText='margin-bottom:16px;padding:14px 18px;border-radius:10px;font-size:13px;';
  document.getElementById('ov-layout').before(box);
  try{
    const j=await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=appointments&id='+encodeURIComponent(apId))).json();
    if(!j.success) throw new Error(j.message);
    const a=j.data;
    if(a.status!=='scheduled'){
      box.style.background='#fef2f2';box.style.border='1px solid #fecaca';box.style.color='#991b1b';
      box.innerHTML=`<i class="fas fa-exclamation-triangle"></i> This appointment is already <strong>${ovEsc(a.status)}</strong>${a.visit_no?' (visit '+ovEsc(a.visit_no)+')':''}. Opening a visit here will not change it.`;
      return;
    }
    ovAppointment=a;
    const d=new Date(a.appointment_date+'T'+a.appointment_time), today=new Date().toDateString()===d.toDateString();
    box.style.background='#eff6ff';box.style.border='1px solid #bfdbfe';box.style.color='#1e3a8a';
    box.innerHTML=`<div style="font-weight:700;font-size:14px;margin-bottom:4px;"><i class="fas fa-calendar-check"></i> Confirming arrival for a follow-up appointment</div>
      <div><strong>${ovEsc(a.patient_name)}</strong> (${ovEsc(a.patient_no)}) · ${d.toLocaleDateString('en-GB',{weekday:'short',day:'2-digit',month:'short'})} at ${a.appointment_time.slice(0,5)} with <strong>${ovEsc(a.doctor_name||'')}</strong>${a.department?' · '+ovEsc(a.department):''}</div>
      <div style="margin-top:2px;">Reason: ${ovEsc(a.reason)}</div>
      ${today?'':`<div style="margin-top:6px;color:#b45309;"><i class="fas fa-info-circle"></i> The appointment was for ${d.toLocaleDateString('en-GB')}, not today.</div>`}
      <div style="margin-top:6px;color:#475569;">Check the payment details and click <strong>Open Visit</strong>: the visit is registered and linked to this appointment.</div>`;
    await ovSelectPatient(a.patient_id);
    document.getElementById('ov-complaint').value='Follow-up: '+a.reason;
  }catch(e){
    box.style.background='#fef2f2';box.style.color='#991b1b';
    box.innerHTML='<i class="fas fa-exclamation-triangle"></i> Could not load the appointment: '+ovEsc(e.message);
  }
}

async function ovSearch(){
  const q=document.getElementById('ov-search-input').value.trim();
  const results=document.getElementById('ov-search-results');
  const hint=document.getElementById('ov-search-hint');
  if(!q){ hint.style.display='block'; results.innerHTML=''; return; }
  hint.style.display='none';
  results.innerHTML='<div style="text-align:center;padding:20px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></div>';
  try{
    const r=await fetch(ovPatAPI+'&search='+encodeURIComponent(q)+'&limit=15');
    const j=await r.json();
    if(!j.success||!j.data.patients.length){
      results.innerHTML='<div style="text-align:center;padding:20px;color:var(--gray-400);font-size:13px;"><i class="fas fa-user-slash" style="display:block;font-size:24px;margin-bottom:6px;"></i>No patients found</div>';
      return;
    }
    const sc={active:'var(--success-color)',admitted:'var(--warning-color)',critical:'var(--danger-color)',discharged:'var(--gray-400)'};
    results.innerHTML=j.data.patients.map(p=>`
      <div onclick="ovSelectPatient(${p.id})"
        style="padding:12px;border:1px solid var(--gray-100);border-radius:8px;cursor:pointer;margin-bottom:6px;transition:.15s;"
        onmouseover="this.style.borderColor='var(--primary-color)';this.style.background='#eff6ff';"
        onmouseout="this.style.borderColor='var(--gray-100)';this.style.background='white';">
        <div style="font-weight:600;color:var(--gray-900);">${ovEsc(p.first_name)} ${ovEsc(p.last_name)}</div>
        <div style="font-size:12px;color:var(--primary-color);font-family:monospace;">${ovEsc(p.patient_no)}</div>
        <div style="font-size:12px;color:var(--gray-500);margin-top:2px;">${ovEsc(p.phone||'')} &nbsp;|&nbsp; ${ovAge(p.date_of_birth)}${p.insurance_company ? ' &nbsp;|&nbsp; <span style="color:#1d4ed8;font-weight:600;"><i class="fas fa-shield-alt"></i> '+ovEsc(p.insurance_company)+'</span>' : ''}${p.member_number ? ' &nbsp;|&nbsp; <span style="color:#2563eb;font-weight:600;"><i class="fas fa-id-card"></i> '+ovEsc(p.member_number)+'</span>' : ''}
          <span style="float:right;padding:2px 8px;border-radius:10px;font-weight:600;background:${sc[p.status]||'#ccc'}20;color:${sc[p.status]||'#555'}">${ovEsc(p.status)}</span>
        </div>
      </div>`).join('');
  }catch(e){
    results.innerHTML='<div style="text-align:center;padding:20px;color:var(--danger-color);font-size:13px;">Error loading results</div>';
  }
}

async function ovSelectPatient(id){
  try{
    const r=await fetch(ovPatAPI+'&id='+id);
    const j=await r.json();
    if(!j.success){ alert('Could not load patient'); return; }
    ovSelectedPatient=j.data;
  }catch(e){ alert('Error loading patient'); return; }
  const p=ovSelectedPatient;
  document.getElementById('ov-avatar').textContent=(p.first_name[0]||'?').toUpperCase();
  document.getElementById('ov-pt-name').textContent =p.first_name+' '+p.last_name;
  document.getElementById('ov-pt-no').textContent   =p.patient_no;
  document.getElementById('ov-pt-gender').textContent=p.gender||'—';
  document.getElementById('ov-pt-age').textContent  =ovAge(p.date_of_birth);
  document.getElementById('ov-pt-phone').textContent=p.phone||'—';
  document.getElementById('ov-pt-status').textContent=p.status||'—';
  document.getElementById('ov-pt-nid').innerHTML=ptNidLabel(p.national_id);
  const memNo = (p.insurance?.[0]?.member_number || p.member_number || '').trim();
  const ptMemEl = document.getElementById('ov-pt-member-no');
  if(ptMemEl) ptMemEl.textContent = memNo || '—';
  const inputMemEl = document.getElementById('ov-insurance-member-no');
  if(inputMemEl) inputMemEl.value = memNo;
  const primaryInsurance = p.insurance?.[0] || {};
  document.getElementById('ov-principal-member-name').value = primaryInsurance.principal_member_name || '';
  document.getElementById('ov-principal-phone').value = primaryInsurance.principal_phone || '';
  document.getElementById('ov-principal-member-number').value = primaryInsurance.principal_member_number || '';
  document.getElementById('ov-employer-name').value = primaryInsurance.employer_name || '';
  document.getElementById('ov-patient-status').value = ['active','admitted','critical'].includes(p.status) ? p.status : 'active';
  document.getElementById('ov-notes').value = '';
  ovFilterFees(); // same service, new patient: fee rule changes

  const compName = p.insurance_company || p.insurance?.[0]?.company_name || '';
  const pkgName  = p.insurance_package || p.insurance?.[0]?.package_name || '';
  const insWrap  = document.getElementById('ov-pt-insurance-wrapper');
  const insBadge = document.getElementById('ov-pt-insurance-badge');
  if(insWrap && insBadge){
    if(compName){
      insBadge.innerHTML = '<i class="fas fa-shield-alt"></i> ' + ovEsc(compName) + (pkgName ? ' <span style="opacity:0.85;font-weight:500;">· ' + ovEsc(pkgName) + '</span>' : '');
      insWrap.style.display = 'inline-block';
    } else {
      insWrap.style.display = 'none';
    }
  }

  const tn=document.getElementById('ov-tariff-note');
  if(TARIFF_LABELS[p.tariff_category]){
    tn.innerHTML=`<i class="fas fa-globe-africa"></i> <strong>Foreign patient (${TARIFF_LABELS[p.tariff_category]}).</strong> If insured, the insurer's price applies. If not insured, the ${TARIFF_LABELS[p.tariff_category]} price list is used.`;
    tn.style.display='block';
  } else tn.style.display='none';
  document.getElementById('ov-placeholder').style.display ='none';
  document.getElementById('ov-patient-card').style.display='block';
  ovCheckAppointment(p.id);
  await ovCheckInsurance(p.id);
}

// Show the patient's scheduled follow-up appointment in Visit Details; the receptionist confirms yes/no.
let ovPendingAppt = null;
async function ovCheckAppointment(patientId){
  const box=document.getElementById('ov-appt-confirm');
  box.style.display='none'; ovPendingAppt=null;
  if(ovAppointment && ovAppointment.patient_id==patientId) return;   // already confirmed via the appointment link
  ovAppointment=null;
  try{
    const from=new Date(Date.now()-30*864e5).toLocaleDateString('en-CA');
    const j=await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=appointments&status=scheduled&patient_id='+patientId+'&date_from='+from)).json();
    const list=j.data||[];
    if(!j.success || !list.length) return;
    const now=Date.now(), at=a=>new Date(a.appointment_date+'T'+a.appointment_time).getTime();
    ovPendingAppt=list.reduce((b,a)=>Math.abs(at(a)-now)<Math.abs(at(b)-now)?a:b);
  }catch(e){ return; }
  const a=ovPendingAppt, d=new Date(a.appointment_date+'T'+a.appointment_time);
  box.innerHTML=`<div style="font-weight:700;font-size:14px;margin-bottom:4px;"><i class="fas fa-calendar-check"></i> This patient has a scheduled follow-up appointment</div>
    <div>${d.toLocaleDateString('en-GB',{weekday:'short',day:'2-digit',month:'short',year:'numeric'})} at ${ovEsc(a.appointment_time.slice(0,5))}${a.doctor_name?' with <strong>'+ovEsc(a.doctor_name)+'</strong>':''}${a.department?' · '+ovEsc(a.department):''}</div>
    <div style="margin-top:2px;">Reason: ${ovEsc(a.reason||'—')}</div>
    <div style="margin-top:10px;font-weight:600;">Has the patient come for this appointment?</div>
    <div style="margin-top:8px;display:flex;gap:8px;">
      <button type="button" onclick="ovConfirmAppt(true)" style="padding:7px 18px;background:#16a34a;color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;">Yes</button>
      <button type="button" onclick="ovConfirmAppt(false)" style="padding:7px 18px;background:white;color:#991b1b;border:1px solid #fecaca;border-radius:6px;font-weight:600;cursor:pointer;">No</button>
    </div>`;
  box.style.display='block';
}

function ovConfirmAppt(yes){
  const box=document.getElementById('ov-appt-confirm');
  if(yes){
    ovAppointment=ovPendingAppt;
    box.innerHTML='<i class="fas fa-check-circle" style="color:#16a34a;"></i> <strong>Arrival confirmed.</strong> Fill in the form below; this visit will be linked to the appointment.';
    document.getElementById('ov-complaint').value='Follow-up: '+(ovPendingAppt.reason||'');
  } else {
    ovAppointment=null;
    box.style.display='none';
  }
}

async function ovCheckInsurance(patientId){
  document.getElementById('ov-ins-banner').style.display='none';
  ovInsuranceData=null;
  try{
    const r=await fetch(ovVisAPI+'&check_insurance=1&patient_id='+patientId);
    const j=await r.json();
    if(!j.success) return;
    ovInsuranceData=j.data;
    ovRenderInsuranceBanner(j.data);
  }catch(e){}
}

function ovRenderInsuranceBanner(ins){
  ovRenderLimit(ins);
  const banner =document.getElementById('ov-ins-banner');
  const icon   =document.getElementById('ov-ins-icon');
  const title  =document.getElementById('ov-ins-title');
  const note   =document.getElementById('ov-ins-note');
  const badge  =document.getElementById('ov-ins-badge');
  const warning=document.getElementById('ov-selfpay-warning');
  const payType=document.getElementById('ov-payment-type');

  const memNo = (ins?.insurance_data?.member_number || ovSelectedPatient?.insurance?.[0]?.member_number || ovSelectedPatient?.member_number || '').trim();
  const ptMemEl = document.getElementById('ov-pt-member-no');
  if(ptMemEl && memNo) ptMemEl.textContent = memNo;
  const inputMemEl = document.getElementById('ov-insurance-member-no');
  if(inputMemEl && !inputMemEl.value && memNo) inputMemEl.value = memNo;

  const compName = ins?.insurance_data?.company_name || ovSelectedPatient?.insurance_company || ovSelectedPatient?.insurance?.[0]?.company_name || '';
  const pkgName  = ins?.insurance_data?.package_name || ovSelectedPatient?.insurance_package || ovSelectedPatient?.insurance?.[0]?.package_name || '';
  const insWrap  = document.getElementById('ov-pt-insurance-wrapper');
  const insBadge = document.getElementById('ov-pt-insurance-badge');
  if(insWrap && insBadge){
    if(compName){
      insBadge.innerHTML = '<i class="fas fa-shield-alt"></i> ' + ovEsc(compName) + (pkgName ? ' <span style="opacity:0.85;font-weight:500;">· ' + ovEsc(pkgName) + '</span>' : '');
      insWrap.style.display = 'inline-block';
    } else {
      insWrap.style.display = 'none';
    }
  }

  const cardInfo = document.getElementById('ov-ins-card-info');
  const cardNo = document.getElementById('ov-ins-card-no');
  if(cardInfo && cardNo){
    if(memNo){
      cardNo.textContent = memNo;
      cardInfo.style.display = 'block';
    } else {
      cardInfo.style.display = 'none';
    }
  }

  if(!ins || !ins.has_insurance){
    banner.style.cssText='border-radius:var(--radius);padding:16px 18px;margin-bottom:16px;display:flex;background:#fff7ed;border:1px solid #fed7aa;';
    icon.className='fas fa-id-card-alt'; icon.style.color='#ea580c';
    title.textContent='No Insurance on File';
    note.textContent='This patient has no insurance record — visit will proceed as Self Pay.';
    badge.textContent='Self Pay';
    badge.style.cssText='padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:#fff7ed;color:#ea580c;border:1px solid #fed7aa;';
    payType.value='self_pay'; warning.style.display='none';
    document.getElementById('ov-ins-selection').style.display='none';
    return;
  }
  const styles={
    valid:      {bg:'#f0fdf4',border:'#bbf7d0',iconClass:'fas fa-shield-alt',iconColor:'#16a34a',badgeBg:'#dcfce7',badgeColor:'#166534'},
    expired:    {bg:'#fef2f2',border:'#fecaca',iconClass:'fas fa-times-circle',iconColor:'#dc2626',badgeBg:'#fef2f2',badgeColor:'#991b1b'},
    pending:    {bg:'#fff7ed',border:'#fed7aa',iconClass:'fas fa-clock',       iconColor:'#ea580c',badgeBg:'#fff7ed',badgeColor:'#9a3412'},
    not_covered:{bg:'#fef2f2',border:'#fecaca',iconClass:'fas fa-ban',          iconColor:'#dc2626',badgeBg:'#fef2f2',badgeColor:'#991b1b'},
  };
  const st=styles[ins.insurance_status]||styles.not_covered;
  banner.style.cssText=`border-radius:var(--radius);padding:16px 18px;margin-bottom:16px;display:flex;background:${st.bg};border:1px solid ${st.border};`;
  icon.className=st.iconClass; icon.style.color=st.iconColor;
  title.textContent=ins.insurance_status==='valid'?'Insurance Valid':
                    ins.insurance_status==='expired'?'Insurance Expired':
                    ins.insurance_status==='pending'?'Insurance Pending':'Not Covered';
  note.textContent=ins.insurance_note||'';
  badge.textContent=(ins.insurance_status||'unknown').replace('_',' ').toUpperCase();
  badge.style.cssText=`padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:${st.badgeBg};color:${st.badgeColor};border:1px solid ${st.border};`;
  
  // Set payment type to insurance and show insurance selection
  payType.value='insurance';
  warning.style.display = ins.insurance_status==='valid' ? 'none' : 'block';
  document.getElementById('ov-ins-selection').style.display='block';
  ovLoadInsCompanies();
}

function ovUpdatePayment(){
  const val=document.getElementById('ov-payment-type').value;
  const warning=document.getElementById('ov-selfpay-warning');
  const insSection=document.getElementById('ov-ins-selection');
  warning.style.display='none';
  // Show/hide insurance selection
  if(val==='insurance'){
    insSection.style.display='block';
    ovLoadInsCompanies();
  } else {
    insSection.style.display='none';
  }
}

async function ovLoadDepartments(){
  const sel=document.getElementById('ov-department');
  try{
    const j=await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=patients/visits&action=departments')).json();
    (j.data||[]).forEach(d=>{sel.innerHTML+=`<option value="${d.id}">${ovEsc(d.name)}</option>`;});
  }catch(e){}
}
ovLoadDepartments();
// Consultation fees follow the service and the 3/14-day rule (server): free / follow-up / full
function ovFilterFees(){
  const sel=document.getElementById('ov-consult-fee'), note=document.getElementById('ov-consult-rule'), dept=document.getElementById('ov-department').value;
  if(!dept || !ovSelectedPatient){ sel.disabled=true; sel.dataset.rule=''; sel.innerHTML='<option value="">— Select a service first —</option>'; note.style.display='none'; return; }
  crLoad(sel, note, 'department_id='+encodeURIComponent(dept)+'&patient_id='+ovSelectedPatient.id);
}

// Doctor assignment removed from Open Visit: any doctor of the department can treat the patient
let ovDoctor = null;
function ovLoadDoctors(){ ovFilterFees(); }

// Insurance coverage limit (#52): warn, then self-pay or attach an authorization letter
let ovLimitPending = false;
function ovRenderLimit(ins){
  const box = document.getElementById('ov-limit-box');
  const c = ins && ins.coverage;
  ovLimitPending = !!(c && c.reached);
  if(!ovLimitPending){ box.style.display = 'none'; return; }
  const fmt = n => Math.round(n).toLocaleString();
  box.innerHTML = `<div style="font-weight:700;margin-bottom:4px;"><i class="fas fa-exclamation-triangle"></i> Coverage limit reached</div>
    <div>Insurer share used <strong>${fmt(c.used)}</strong> of <strong>${fmt(c.effective)}</strong> RWF this policy year${c.authorized>0?' (includes '+fmt(c.authorized)+' authorized by letter)':''}. Choose how to continue:</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">
      <button type="button" onclick="ovLimitSelfPay()" style="padding:7px 14px;background:white;border:1px solid #fdba74;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;color:#9a3412;">Continue as self-pay</button>
      <button type="button" onclick="ovAuthModal()" style="padding:7px 14px;background:#ea580c;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;color:white;"><i class="fas fa-paperclip"></i> Attach authorization letter</button>
    </div>`;
  box.style.display = 'block';
}
function ovLimitSelfPay(){
  document.getElementById('ov-payment-type').value = 'self_pay';
  ovUpdatePayment();
  const box = document.getElementById('ov-limit-box');
  box.innerHTML = '<i class="fas fa-check-circle"></i> Limit reached: this visit will be recorded as <strong>Self Pay</strong>.';
}
function ovAuthModal(){
  document.getElementById('ov-auth-modal')?.remove();
  const m = document.createElement('div');
  m.id = 'ov-auth-modal';
  m.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;padding:16px;';
  const inp = 'width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;box-sizing:border-box;';
  const lbl = 'display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin:10px 0 3px;';
  m.innerHTML = `<div style="background:white;border-radius:12px;padding:24px;width:100%;max-width:440px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:4px;"><i class="fas fa-paperclip" style="color:var(--primary-color);"></i> Authorization letter</h3>
    <div style="font-size:12px;color:var(--gray-500);">The authorized amount raises the coverage limit for this patient.</div>
    <div id="ovau-alert" style="display:none;margin-top:10px;padding:10px 12px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <label style="${lbl}">Authorization No.</label><input id="ovau-no" type="text" style="${inp}">
    <label style="${lbl}">Authorized amount (RWF) *</label><input id="ovau-amt" type="number" min="1" step="1" style="${inp}">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div><label style="${lbl}">Valid from</label><input id="ovau-from" type="date" style="${inp}"></div>
      <div><label style="${lbl}">Valid to</label><input id="ovau-to" type="date" style="${inp}"></div>
    </div>
    <label style="${lbl}">Letter (PDF or image) *</label><input id="ovau-file" type="file" accept=".pdf,image/*" style="${inp}">
    <div style="display:flex;gap:10px;margin-top:18px;">
      <button id="ovau-save" onclick="ovAuthSave()" style="padding:9px 22px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save</button>
      <button onclick="document.getElementById('ov-auth-modal').remove()" style="padding:9px 22px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div></div>`;
  document.body.appendChild(m);
}
async function ovAuthSave(){
  const al = document.getElementById('ovau-alert'), btn = document.getElementById('ovau-save');
  const amt = parseFloat(document.getElementById('ovau-amt').value), file = document.getElementById('ovau-file').files[0];
  const no = document.getElementById('ovau-no').value.trim();
  al.style.display = 'none';
  if(!(amt > 0) || !file){ al.textContent = 'Enter the authorized amount and attach the letter.'; al.style.display = 'block'; return; }
  btn.disabled = true;
  try{
    const fd = new FormData();
    fd.append('title', 'Insurance authorization' + (no ? ' ' + no : '') + ' - ' + (ovSelectedPatient.patient_no||''));
    fd.append('document_type', 'letter'); fd.append('category', 'insurance'); fd.append('patient_id', ovSelectedPatient.id); fd.append('file', file);
    const u = await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=documents', {method:'POST', body:fd})).json();
    if(!u.success) throw new Error(u.message || 'Upload failed');
    const a = await (await fetch(ovVisAPI+'&action=add_authorization', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({
      patient_insurance_id: ovInsuranceData.insurance_id, auth_no: no, authorized_amount: amt,
      valid_from: document.getElementById('ovau-from').value || null, valid_to: document.getElementById('ovau-to').value || null, document_id: u.data.id })})).json();
    if(!a.success) throw new Error(a.message || 'Could not save authorization');
    document.getElementById('ov-auth-modal').remove();
    await ovCheckInsurance(ovSelectedPatient.id);
  }catch(e){ al.textContent = e.message; al.style.display = 'block'; btn.disabled = false; }
}

async function ovLoadInsCompanies(){
  const sel=document.getElementById('ov-insurance-company');
  if(sel.options.length<=1){
    try{
      const r=await fetch(window.location.origin + _pBase + '/api.php?endpoint=insurance/companies');
      const j=await r.json();
      const companies=j.data?.companies||j.data||[];
      sel.innerHTML='<option value="">Select insurance...</option>';
      companies.forEach(c=>{sel.innerHTML+=`<option value="${c.id}">${ovEsc(c.name)}</option>`;});
    }catch(e){}
  }
  // Pre-select patient's insurance if available
  await ovPreselectInsurance();
}

async function ovPreselectInsurance(){
  let ins = null;
  if(ovInsuranceData && ovInsuranceData.has_insurance && ovInsuranceData.insurance_data){
    ins = ovInsuranceData.insurance_data;
  } else if(ovSelectedPatient && ovSelectedPatient.insurance && ovSelectedPatient.insurance.length){
    ins = ovSelectedPatient.insurance[0];
  }
  if(!ins) return;

  const companySel=document.getElementById('ov-insurance-company');
  const targetCompId = ins.company_id ? String(ins.company_id) : null;
  const targetCompName = (ins.company_name || '').trim().toLowerCase();

  let matchedCompany = false;
  if(targetCompId){
    for(let i=0;i<companySel.options.length;i++){
      if(String(companySel.options[i].value) === targetCompId){
        companySel.value=companySel.options[i].value;
        matchedCompany = true;
        break;
      }
    }
  }
  if(!matchedCompany && targetCompName){
    for(let i=0;i<companySel.options.length;i++){
      if(companySel.options[i].textContent.trim().toLowerCase()===targetCompName){
        companySel.value=companySel.options[i].value;
        matchedCompany = true;
        break;
      }
    }
  }

  // Load plans for this company and preselect
  if(companySel.value){
    await ovLoadPlansAndPreselect(ins.package_id, ins.package_name);
  }
}

async function ovLoadPlansAndPreselect(targetPkgId, targetPkgName){
  const companyId=document.getElementById('ov-insurance-company').value;
  const sel=document.getElementById('ov-insurance-plan');
  sel.innerHTML='<option value="">Select plan...</option>';
  document.getElementById('ov-ins-rate-info').style.display='none';
  if(!companyId) return;
  try{
    const r=await fetch(window.location.origin + _pBase + '/api.php?endpoint=insurance/packages&company_id='+companyId);
    const j=await r.json();
    const packages=j.data?.packages||j.data||[];
    packages.forEach(p=>{
      const rate=100-parseFloat(p.copayment_percentage||0);
      sel.innerHTML+=`<option value="${p.id}" data-rate="${rate}" data-copay="${p.copayment_percentage||0}">${ovEsc(p.name)} (${rate}% coverage)</option>`;
    });

    // Try preselecting by package_id first, then by package_name
    let matchedPkg = false;
    if(targetPkgId){
      const targetStr = String(targetPkgId);
      for(let i=0;i<sel.options.length;i++){
        if(String(sel.options[i].value) === targetStr){
          sel.value=sel.options[i].value;
          matchedPkg = true;
          break;
        }
      }
    }
    if(!matchedPkg && targetPkgName){
      const pkgLower = targetPkgName.trim().toLowerCase();
      for(let i=0;i<sel.options.length;i++){
        if(sel.options[i].textContent.toLowerCase().includes(pkgLower)){
          sel.value=sel.options[i].value;
          matchedPkg = true;
          break;
        }
      }
    }
    // If only one package available and nothing matched, auto-select it
    if(!sel.value && packages.length === 1){
      sel.value = packages[0].id;
    }

    if(sel.value){
      const opt=sel.selectedOptions[0];
      if(opt&&opt.value){
        document.getElementById('ov-ins-rate-info').style.display='block';
        document.getElementById('ov-ins-rate-info').innerHTML='<i class="fas fa-info-circle"></i> Coverage: <strong>'+opt.getAttribute('data-rate')+'%</strong> | Patient pays: <strong>'+opt.getAttribute('data-copay')+'%</strong>';
      }
    }

    sel.onchange=function(){
      const opt=this.selectedOptions[0];
      if(opt&&opt.value){
        document.getElementById('ov-ins-rate-info').style.display='block';
        document.getElementById('ov-ins-rate-info').innerHTML='<i class="fas fa-info-circle"></i> Coverage: <strong>'+opt.getAttribute('data-rate')+'%</strong> | Patient pays: <strong>'+opt.getAttribute('data-copay')+'%</strong>';
      } else {
        document.getElementById('ov-ins-rate-info').style.display='none';
      }
    };
  }catch(e){}
}

// Wrapper for company dropdown onchange
async function ovLoadPlans(){
  await ovLoadPlansAndPreselect(null, null);
}

async function ovAddExtraIns(target, srcId, pre){
  target=target||'ov-extra-ins'; srcId=srcId||'ov-insurance-company';
  const row=document.createElement('div');
  row.className='ov-extra-row';
  row.style.cssText='margin-top:10px;padding:10px;background:white;border:1px solid #bfdbfe;border-radius:8px;display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:center;';
  const st='padding:8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;width:100%;box-sizing:border-box;';
  row.innerHTML='<select class="ovx-company" style="'+st+'">'+document.getElementById(srcId).innerHTML+'</select>'
    +'<select class="ovx-plan" style="'+st+'"><option value="">Select plan...</option></select>'
    +'<input class="ovx-member" type="text" placeholder="Member Number / Card No." style="'+st+'">'
    +'<button type="button" onclick="this.parentNode.remove()" style="background:none;border:none;color:var(--gray-400);cursor:pointer;font-size:16px;" title="Remove"><i class="fas fa-times"></i></button>';
  const co=row.querySelector('.ovx-company');
  co.value='';
  co.onchange=async function(){
    const pl=row.querySelector('.ovx-plan');
    pl.innerHTML='<option value="">Select plan...</option>';
    if(!co.value) return;
    try{
      const j=await (await fetch(window.location.origin + _pBase + '/api.php?endpoint=insurance/packages&company_id='+co.value)).json();
      (j.data?.packages||j.data||[]).forEach(p=>{ pl.innerHTML+='<option value="'+p.id+'">'+ovEsc(p.name)+' ('+(100-parseFloat(p.copayment_percentage||0))+'% coverage)</option>'; });
    }catch(e){}
  };
  document.getElementById(target).appendChild(row);
  if(pre){ co.value=String(pre.company_id); await co.onchange(); row.querySelector('.ovx-plan').value=String(pre.package_id||''); row.querySelector('.ovx-member').value=pre.member_number||''; }
}
// Reads the extra-insurance rows of a container; null (after alerting) when a row is half filled
function ovCollectExtras(sel){
  const out=[];
  for(const row of document.querySelectorAll(sel+' .ov-extra-row')){
    const c=row.querySelector('.ovx-company').value, pl=row.querySelector('.ovx-plan').value;
    if(!c && !pl) continue;
    if(!c || !pl) return null;
    out.push({insurance_id:parseInt(c), insurance_plan_id:parseInt(pl), member_number:row.querySelector('.ovx-member').value.trim()});
  }
  return out;
}

function ovClearPatient(){
  document.getElementById('ov-extra-ins').innerHTML='';
  ovLimitPending = false; ovDoctor = null;
  { const lb = document.getElementById('ov-limit-box'); if(lb) lb.style.display = 'none'; document.querySelectorAll('#ov-doctor-cards .ov-doc').forEach(x=>{ x.classList.remove('sel'); x.setAttribute('aria-pressed','false'); }); }
  ovSelectedPatient=null; ovInsuranceData=null;
  document.getElementById('ov-placeholder').style.display ='block';
  document.getElementById('ov-patient-card').style.display='none';
  document.getElementById('ov-ins-banner').style.display  ='none';
  document.getElementById('ov-insurance-company').value   ='';
  document.getElementById('ov-insurance-plan').innerHTML  ='<option value="">Select plan...</option>';
  document.getElementById('ov-ins-rate-info').style.display='none';
  document.getElementById('ov-payment-type').value        ='self_pay';
  document.getElementById('ov-ins-selection').style.display='none';
  document.getElementById('ov-form-alert').style.display   ='none';
  if(document.getElementById('ov-pt-member-no')) document.getElementById('ov-pt-member-no').textContent = '—';
  if(document.getElementById('ov-insurance-member-no')) document.getElementById('ov-insurance-member-no').value = '';
  if(document.getElementById('ov-principal-member-name')) document.getElementById('ov-principal-member-name').value = '';
  if(document.getElementById('ov-principal-phone')) document.getElementById('ov-principal-phone').value = '';
  if(document.getElementById('ov-principal-member-number')) document.getElementById('ov-principal-member-number').value = '';
  if(document.getElementById('ov-employer-name')) document.getElementById('ov-employer-name').value = '';
  if(document.getElementById('ov-ins-card-info')) document.getElementById('ov-ins-card-info').style.display = 'none';
  if(document.getElementById('ov-pt-insurance-wrapper')) document.getElementById('ov-pt-insurance-wrapper').style.display = 'none';
  document.getElementById('ov-notes').value = '';
  ovFilterFees(); // no patient: fee select reset
}

async function ovOpenVisit(){
  if(!ovSelectedPatient){ alert('Select a patient first'); return; }
  const btn  =document.getElementById('ov-open-btn');
  const label=document.getElementById('ov-open-label');
  const al   =document.getElementById('ov-form-alert');
  al.style.display='none';

  const payType    = document.getElementById('ov-payment-type').value;
  const insCompany = document.getElementById('ov-insurance-company').value;
  const insPlan    = document.getElementById('ov-insurance-plan').value;

  const deptSel = document.getElementById('ov-department');
  if(!deptSel.value){
    al.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Please <strong>Select Service</strong>.';
    al.style.display = 'block';
    deptSel.focus();
    return;
  }

  if(ovLimitPending && payType !== 'self_pay'){
    al.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Insurance coverage limit reached. Choose <strong>Continue as self-pay</strong> or <strong>Attach authorization letter</strong>.';
    al.style.display = 'block';
    return;
  }

  const feeSel = document.getElementById('ov-consult-fee');
  const feeErr = crMissing(feeSel);
  if(feeErr){
    al.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>'+feeErr;
    al.style.display = 'block'; feeSel.focus(); return;
  }

  // Make insurance mandatory when payment type is insurance
  if(payType === 'insurance'){
    const memEl = document.getElementById('ov-insurance-member-no');
    if(!memEl.value.trim()){
      al.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Please enter the <strong>Member Number / Card No.</strong> It is mandatory for insurance visits.';
      al.style.display = 'block'; memEl.focus(); return;
    }
    if(!insCompany){
      al.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Please select an <strong>Insurance Company</strong>. It is mandatory for insurance visits.';
      al.style.display = 'block';
      document.getElementById('ov-insurance-company').focus();
      return;
    }
    if(!insPlan){
      al.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Please select a <strong>Plan / Package</strong>. It is mandatory for insurance visits.';
      al.style.display = 'block';
      document.getElementById('ov-insurance-plan').focus();
      return;
    }
  }

  btn.disabled=true; label.textContent='Checking…';

  // Check if patient has an incomplete visit (open or in_progress)
  try{
    const chkR = await fetch(ovVisAPI+'&patient_id='+ovSelectedPatient.id+'&limit=10');
    const chkJ = await chkR.json();
    if(chkJ.success){
      const visits = chkJ.data.visits || chkJ.data || [];
      const incomplete = visits.find(v => v.status === 'open' || v.status === 'in_progress');
      if(incomplete){
        al.innerHTML = '<i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i><strong>Cannot open new visit.</strong> This patient has an active visit that is not yet completed.<br><span style="font-size:12px;margin-top:4px;display:inline-block;">Visit: <strong>' + (incomplete.visit_no||'') + '</strong> | Date: ' + (incomplete.visit_date||'') + ' | Status: <strong>' + incomplete.status + '</strong></span>';
        al.style.display = 'block';
        btn.disabled=false; label.textContent='Open Visit';
        return;
      }
    }
  }catch(e){}

  label.textContent='Opening…';
  const extraIns=(payType === 'insurance') ? ovCollectExtras('#ov-extra-ins') : [];
  if(extraIns===null){
    al.innerHTML = '<i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>Each extra insurance needs a <strong>company and a plan</strong> (or remove the row).';
    al.style.display = 'block'; btn.disabled=false; label.textContent='Open Visit'; return;
  }
  const forceChk=document.getElementById('ov-force-insurance');
  const memNoEl=document.getElementById('ov-insurance-member-no');
  const body={
    patient_id:        ovSelectedPatient.id,
    payment_type:      payType,
    insurance_id:      insCompany ? parseInt(insCompany) : null,
    insurance_plan_id: insPlan ? parseInt(insPlan) : null,
    member_number:     memNoEl ? memNoEl.value.trim() : '',
    principal_member_name: document.getElementById('ov-principal-member-name').value.trim(),
    principal_phone: document.getElementById('ov-principal-phone').value.trim(),
    principal_member_number: document.getElementById('ov-principal-member-number').value.trim(),
    employer_name: document.getElementById('ov-employer-name').value.trim(),
    department_id:     parseInt(deptSel.value),
    consultation_service_id: parseInt(feeSel.value) || null,
    patient_status:    document.getElementById('ov-patient-status').value,
    notes:             document.getElementById('ov-notes').value.trim(),
    doctor_id:         ovDoctor ? ovDoctor.id : null,
    doctor_name:       ovDoctor ? (ovDoctor.first_name+' '+ovDoctor.last_name) : null,
    visit_type:        ovAppointment && ovAppointment.patient_id == ovSelectedPatient.id ? 'follow_up'
                       : /emergency/i.test(deptSel.selectedOptions[0].text) ? 'emergency' : 'outpatient',
    chief_complaint:  document.getElementById('ov-complaint').value.trim(),
    referred_from:     document.getElementById('ov-referred-from').value,
    referred_from_name: document.getElementById('ov-referred-from-name').value.trim(),
    force_insurance:   forceChk ? forceChk.checked : false,
    extra_insurances:  extraIns,
    appointment_id:    ovAppointment && ovAppointment.patient_id == ovSelectedPatient.id ? ovAppointment.id : null
  };
  try{
    const r=await fetch(ovVisAPI,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    const j=await r.json();
    if(j.success){
      document.getElementById('ov-layout').style.display='none';
      document.getElementById('ov-success-card').style.display='block';
      ovLoadTodayVisits();
      document.getElementById('ov-success-visit-no').textContent=j.data.visit_no;
      document.getElementById('ov-success-patient').textContent=j.data.patient_name+' · '+j.data.patient_no;
      const payLabel={insurance:'Insurance',self_pay:'Self Pay',mixed:'Mixed'};
      document.getElementById('ov-success-payment').textContent=
        (payLabel[j.data.payment_type]||j.data.payment_type)+
        (j.data.insurance_note?' — '+j.data.insurance_note:'');
      if(j.data.appointment_id){
        document.getElementById('ov-success-payment').insertAdjacentHTML('afterend','<div id="ov-success-appt" style="margin-top:8px;font-size:13px;color:#047857;"><i class="fas fa-calendar-check"></i> Follow-up appointment marked as arrived</div>');
        ovAppointment=null; const b=document.getElementById('ov-appt-banner'); if(b) b.remove();
      }
    } else {
      al.textContent=j.message||'Failed to open visit';
      al.style.display='block';
    }
  }catch(e){
    al.textContent='Request failed: '+e.message;
    al.style.display='block';
  } finally { btn.disabled=false; label.textContent='Open Visit'; }
}

function ovNewVisit(){
  document.getElementById('ov-layout').style.display='grid';
  document.getElementById('ov-success-card').style.display='none';
  const sa=document.getElementById('ov-success-appt'); if(sa) sa.remove();
  ovClearPatient();
  document.getElementById('ov-search-input').value='';
  document.getElementById('ov-search-results').innerHTML='';
  document.getElementById('ov-search-hint').style.display='block';
  document.getElementById('ov-complaint').value='';
  { const rf=document.getElementById('ov-referred-from'); if(rf){ rf.value=''; const rn=document.getElementById('ov-referred-from-name'); rn.value=''; rn.style.display='none'; } }
}

// Today's Visits
async function ovLoadTodayVisits(){
  const tbody=document.getElementById('ov-today-tbody');
  tbody.innerHTML='<tr><td colspan="8" style="padding:20px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try{
    const searchVal = (document.getElementById('ov-search-filter')?.value||'').trim();
    const dateFrom  = document.getElementById('ov-date-from')?.value||'';
    const dateTo    = document.getElementById('ov-date-to')?.value||'';
    const statusVal = document.getElementById('ov-status-filter')?.value||'';
    let url = ovVisAPI+'&limit=100';
    if(statusVal) url+='&status='+encodeURIComponent(statusVal);
    if(searchVal) url+='&search='+encodeURIComponent(searchVal);
    if(dateFrom)  url+='&date_from='+encodeURIComponent(dateFrom);
    if(dateTo)    url+='&date_to='+encodeURIComponent(dateTo);
    // Default to today if no date range provided
    if(!dateFrom && !dateTo){
      const today=new Date().toISOString().split('T')[0];
      url+='&date='+today;
    }
    const r=await fetch(url);
    const j=await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="8" style="padding:20px;text-align:center;color:#dc2626;">'+ovEsc(j.message)+'</td></tr>';return;}
    const visits=j.data.visits||j.data||[];
    if(!visits.length){
      tbody.innerHTML='<tr><td colspan="8" style="padding:30px;text-align:center;color:var(--gray-400);">No visits today.</td></tr>';return;
    }
    const statusColors={open:'#2563eb',in_progress:'#d97706',completed:'#059669',cancelled:'#dc2626'};
    const statusBg={open:'#dbeafe',in_progress:'#fef3c7',completed:'#d1fae5',cancelled:'#fee2e2'};
    window._ovVisits={}; visits.forEach(v=>{window._ovVisits[v.id]=v;});
    tbody.innerHTML=visits.map(v=>{
      const name=v.patient_name||(v.first_name+' '+v.last_name);
      const st=v.status||'open';
      const canAct=st==='open'||st==='in_progress';
      const pendingAmt=parseFloat(v.pending_amount||0);
      const paidAmt=parseFloat(v.paid_amount||0);
      const invoiceCount=parseInt(v.invoice_count||0);
      const canCancel=canAct && invoiceCount===0;
      const canComplete=canAct && pendingAmt===0;
      const hasPrescription = parseInt(v.has_prescription || 0) > 0;

      const isInsVisit = (v.company_name || v.payment_type === 'insurance' || v.payment_type === 'mixed') && Boolean(v.company_name);
      let insColHtml = '';
      if (isInsVisit) {
        const comp = ovEsc(v.company_name);
        const pkg = v.package_name ? ' · ' + ovEsc(v.package_name) : '';
        const cardBadge = v.member_number
          ? `<div style="margin-top:3px;"><span style="font-family:monospace;font-weight:600;color:#1e40af;background:#f0f9ff;padding:1px 6px;border-radius:4px;border:1px solid #bae6fd;font-size:11px;display:inline-flex;align-items:center;gap:3px;"><i class="fas fa-id-card" style="font-size:10px;"></i>${ovEsc(v.member_number)}</span></div>`
          : `<div style="margin-top:3px;font-size:11px;color:var(--gray-400);font-style:italic;"><i class="fas fa-id-card" style="font-size:10px;margin-right:2px;"></i>No card #</div>`;
        insColHtml = `<div>
          <span style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:2px 7px;border-radius:6px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;">
            <i class="fas fa-shield-alt"></i> ${comp}${pkg}
          </span>
          ${cardBadge}
        </div>`;
      } else if (v.member_number) {
        insColHtml = `<div>
          <span style="background:#f3f4f6;color:#4b5563;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:500;">Self-Pay</span>
          <div style="margin-top:3px;"><span style="font-family:monospace;font-weight:600;color:#4b5563;background:#f3f4f6;padding:1px 6px;border-radius:4px;font-size:11px;display:inline-flex;align-items:center;gap:3px;"><i class="fas fa-id-card" style="font-size:10px;"></i>${ovEsc(v.member_number)}</span></div>
        </div>`;
      } else {
        insColHtml = `<span style="background:#f3f4f6;color:#6b7280;padding:2px 7px;border-radius:6px;font-size:11px;font-weight:500;">Self-Pay</span>`;
      }

      const visitDT=ovEsc(v.visit_date||'')+(v.visit_time?'<div style="font-size:11px;color:var(--gray-500);">'+ovEsc(String(v.visit_time).slice(0,5))+'</div>':'');
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td style="padding:8px 10px;font-size:12px;white-space:nowrap;">${visitDT}</td>
        <td style="padding:8px 10px;"><div style="font-weight:600;">${ovEsc(name)}${tariffBadge(v.tariff_category)}</div><div style="font-size:11px;color:var(--gray-500);">${ovEsc(v.patient_no||'')}</div></td>
        <td style="padding:8px 10px;font-family:monospace;font-size:12px;color:var(--primary-color);">${ovEsc(v.visit_no||'')}</td>
        <td style="padding:8px 10px;font-size:12px;">${insColHtml}</td>
        <td style="padding:8px 10px;text-align:center;font-size:12px;">${ovEsc(v.visit_type||'outpatient')}</td>
        <td style="padding:8px 10px;text-align:center;">
          <span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${statusBg[st]||'#f3f4f6'};color:${statusColors[st]||'#666'};">${st}</span>
          ${hasPrescription ? `<div style="margin-top:3px;"><span style="background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:600;display:inline-flex;align-items:center;gap:3px;" title="External prescription available"><i class="fas fa-file-prescription"></i> Has Rx</span></div>` : ''}
        </td>
        <td style="padding:8px 10px;"><span style="font-size:11px;color:var(--gray-600);">${v.opened_by_name ? '<strong>📂 Opened:</strong> ' + ovEsc(v.opened_by_name) : '—'}${v.cancelled_by_name ? '<br><strong style="color:#dc2626;">❌ Cancelled:</strong> ' + ovEsc(v.cancelled_by_name) + '<br><span style="font-size:10px;color:var(--gray-400);">' + (v.cancelled_at || '') + '</span>' : ''}${v.completed_by_name ? '<br><strong style="color:#16a34a;">✓ Completed:</strong> ' + ovEsc(v.completed_by_name) + '<br><span style="font-size:10px;color:var(--gray-400);">' + (v.completed_at || '') + '</span>' : ''}</span></td>
        <td style="padding:8px 10px;text-align:center;white-space:nowrap;">
          ${canAct?`<button onclick="ovEditInsurance(${v.id})" style="padding:4px 8px;background:#2563eb;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;font-weight:600;margin-right:4px;" title="Change insurance"><i class="fas fa-edit"></i> Edit</button>`:''}${canComplete?`<button onclick="ovChangeStatus(${v.id},'completed')" style="padding:4px 8px;background:#059669;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;font-weight:600;margin-right:4px;" title="Mark Complete"><i class="fas fa-check"></i> Complete</button>`:''}${canCancel?`<button onclick="ovChangeStatus(${v.id},'cancelled')" style="padding:4px 8px;background:#dc2626;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;font-weight:600;margin-right:4px;" title="Cancel"><i class="fas fa-times"></i> Cancel</button>`:''}${!canComplete&&!canCancel&&canAct?'<span style="font-size:10px;color:var(--gray-400);margin-right:4px;">Pay first</span>':''}${invoiceCount>0&&canAct?`<button onclick="ovRecalcVisit(${v.id},${v.patient_id})" style="padding:4px 8px;background:#7c3aed;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;font-weight:600;margin-right:4px;" title="Recalculate insurance prices"><i class="fas fa-calculator"></i> Recalc</button>`:''}${`<button onclick="ovConsent(${v.id},${v.patient_id})" style="padding:4px 8px;background:#0d9488;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;font-weight:600;margin-right:4px;" title="Consent form"><i class="fas fa-file-signature"></i> Consent Form</button>`}${hasPrescription?`<button onclick="ovPrintExternalRx(${v.id},'${ovEsc(name).replace(/'/g,"\\'")}','${ovEsc(v.visit_no||'').replace(/'/g,"\\'")}')" style="padding:4px 8px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:10px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:3px;" title="Print External Prescription"><i class="fas fa-file-prescription"></i> Print Rx</button>`:''}${!canAct&&!hasPrescription?'<span style="font-size:11px;color:var(--gray-400);">—</span>':''}
        </td>
      </tr>`;
    }).join('');
  }catch(e){
    tbody.innerHTML='<tr><td colspan="8" style="padding:20px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}

// Edit the insurance of a visit that is still open / in progress (wrong insurance entered)
async function ovEditInsurance(visitId){
  const v=(window._ovVisits||{})[visitId]; if(!v) return;
  document.getElementById('ovei-modal')?.remove();
  const m=document.createElement('div');
  m.id='ovei-modal';
  m.style.cssText='position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;padding:16px;';
  const inp='width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;';
  const lbl='display:block;font-size:12px;font-weight:500;color:var(--gray-600);margin-bottom:3px;';
  m.innerHTML=`<div style="background:white;border-radius:12px;padding:24px;width:100%;max-width:640px;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:4px;"><i class="fas fa-edit" style="color:var(--primary-color);"></i> Change Insurance</h3>
    <div style="font-size:12px;color:var(--gray-500);margin-bottom:14px;">${ovEsc(v.patient_name||'')} · ${ovEsc(v.visit_no||'')}</div>
    <div id="ovei-alert" style="display:none;margin-bottom:12px;padding:10px 12px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
    <div style="margin-bottom:12px;"><label style="${lbl}">Payment Type</label>
      <select id="ovei-pay" onchange="document.getElementById('ovei-ins').style.display=this.value==='self_pay'?'none':'block'" style="${inp}">
        <option value="self_pay">Self Pay</option><option value="insurance">Insurance</option>
      </select></div>
    <div id="ovei-ins" style="display:none;">
      <div style="margin-bottom:12px;"><label style="${lbl}">Insurance Company</label><select id="ovei-company" onchange="ovEiLoadPlans()" style="${inp}"><option value="">Select insurance...</option></select></div>
      <div style="margin-bottom:12px;"><label style="${lbl}">Plan / Package</label><select id="ovei-plan" style="${inp}"><option value="">Select plan...</option></select></div>
      <div style="margin-bottom:12px;"><label style="${lbl}">Member Number / Card No. <span style="color:var(--danger-color);">*</span></label><input id="ovei-member" type="text" value="${ovEsc(v.member_number||'')}" style="${inp}"></div>
      <div id="ovei-extra"></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px;">
      <button id="ovei-save" onclick="ovEiSave(${visitId})" style="padding:9px 22px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Save</button>
      <button onclick="document.getElementById('ovei-modal').remove()" style="padding:9px 22px;background:white;color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;">Cancel</button>
    </div></div>`;
  document.body.appendChild(m);
  document.getElementById('ovei-pay').value=v.payment_type==='mixed'?'insurance':(v.payment_type||'self_pay'); // old "mixed" visits: shown as insurance
  document.getElementById('ovei-ins').style.display=(v.payment_type==='insurance'||v.payment_type==='mixed')?'block':'none';
  try{
    const j=await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=insurance/companies')).json();
    const sel=document.getElementById('ovei-company');
    (j.data?.companies||j.data||[]).forEach(c=>{sel.innerHTML+=`<option value="${c.id}">${ovEsc(c.name)}</option>`;});
    if(v.insurance_id){ sel.value=v.insurance_id; await ovEiLoadPlans(); }
  }catch(e){}
}

async function ovEiLoadPlans(){
  const sel=document.getElementById('ovei-plan');
  sel.innerHTML='<option value="">Select plan...</option>';
  const cid=document.getElementById('ovei-company').value;
  if(!cid) return;
  try{
    const j=await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=insurance/packages&company_id='+cid)).json();
    (j.data?.packages||j.data||[]).forEach(p=>{sel.innerHTML+=`<option value="${p.id}">${ovEsc(p.name)} (${100-parseFloat(p.copayment_percentage||0)}% coverage)</option>`;});
  }catch(e){}
}

async function ovEiSave(visitId){
  const v=window._ovVisits[visitId], al=document.getElementById('ovei-alert'), btn=document.getElementById('ovei-save');
  const pay=document.getElementById('ovei-pay').value, company=document.getElementById('ovei-company').value, plan=document.getElementById('ovei-plan').value;
  al.style.display='none';
  if(pay!=='self_pay' && (!company||!plan)){ al.textContent='Select the insurance company and the plan / package.'; al.style.display='block'; return; }
  if(pay!=='self_pay' && !document.getElementById('ovei-member').value.trim()){ al.textContent='Member Number / Card No. is required for insurance visits.'; al.style.display='block'; return; }
  const extras=pay==='self_pay'?[]:ovCollectExtras('#ovei-extra');
  if(extras===null){ al.textContent='Each extra insurance needs a company and a plan (or remove the row).'; al.style.display='block'; return; }
  btn.disabled=true;
  try{
    const r=await fetch(ovVisAPI+'&id='+visitId,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      action:'change_insurance', payment_type:pay,
      insurance_id:company?parseInt(company):null, insurance_plan_id:plan?parseInt(plan):null,
      member_number:document.getElementById('ovei-member').value.trim(), extra_insurances:extras
    })});
    const j=await r.json();
    if(!j.success){ al.textContent=j.message||'Failed'; al.style.display='block'; btn.disabled=false; return; }
    document.getElementById('ovei-modal').remove();
    await ovLoadTodayVisits();
    if(parseInt(v.invoice_count||0)>0) ovRecalcVisit(visitId, v.patient_id);
  }catch(e){ al.textContent='Request failed: '+e.message; al.style.display='block'; btn.disabled=false; }
}

// Consent Form modal: upload (type fixed to consent) + list of this patient's attachments; no delete offered
async function ovConsent(visitId, patientId){
  const api=window.location.origin+_pBase+'/api.php?endpoint=patients/attachments';
  const old=document.getElementById('ovc-modal'); if(old) old.remove();
  const m=document.createElement('div');
  m.id='ovc-modal';
  m.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10000;display:flex;align-items:center;justify-content:center;';
  m.innerHTML=`<div style="background:white;border-radius:12px;padding:20px;width:480px;max-width:94vw;max-height:88vh;overflow:auto;">
    <h3 style="font-size:16px;font-weight:600;margin-bottom:12px;"><i class="fas fa-file-signature" style="color:#0d9488;"></i> Consent Form</h3>
    <div style="display:grid;gap:8px;">
      <input type="file" id="ovc-file" accept=".pdf,.jpg,.jpeg,.png" style="font-size:13px;">
      <input type="text" id="ovc-note" maxlength="500" placeholder="Note (optional)" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      <div id="ovc-err" style="display:none;color:#dc2626;font-size:12px;"></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button type="button" onclick="document.getElementById('ovc-modal').remove()" style="padding:7px 14px;border:1px solid var(--gray-200);background:white;border-radius:6px;cursor:pointer;">Close</button>
        <button type="button" id="ovc-up" style="padding:7px 14px;border:none;background:#0d9488;color:white;border-radius:6px;cursor:pointer;font-weight:600;">Upload</button>
      </div>
    </div>
    <h4 style="font-size:13px;font-weight:600;margin:14px 0 6px;">Attachments</h4>
    <div id="ovc-list" style="font-size:12px;"></div></div>`;
  document.body.appendChild(m);
  const load=async()=>{
    const j=await (await fetch(api+'&patient_id='+patientId)).json();
    const rows=j.data||[];
    document.getElementById('ovc-list').innerHTML=rows.length?rows.map(a=>`<div style="padding:6px 0;border-bottom:1px solid var(--gray-100);"><a href="${ovEsc(window.location.origin+_pBase+'/'+a.file_path)}" target="_blank" rel="noopener">${ovEsc(a.original_name)}</a>${String(a.visit_id)===String(visitId)?' <b style="color:#0d9488;">(this visit)</b>':(a.visit_no?' ('+ovEsc(a.visit_no)+')':'')}<div style="color:var(--gray-500);">${ovEsc(a.created_at||'')} - ${ovEsc(a.uploaded_by_name||'')}${a.note?' - '+ovEsc(a.note):''}</div></div>`).join(''):'<div style="color:var(--gray-400);">None yet</div>';
  };
  document.getElementById('ovc-up').onclick=async()=>{
    const err=document.getElementById('ovc-err'); err.style.display='none';
    const f=document.getElementById('ovc-file').files[0];
    if(!f){ err.textContent='Choose a file'; err.style.display='block'; return; }
    const fd=new FormData(); fd.append('file',f); fd.append('patient_id',patientId); fd.append('visit_id',visitId); fd.append('note',document.getElementById('ovc-note').value);
    const j=await (await fetch(api,{method:'POST',body:fd})).json();
    if(!j.success){ err.textContent=j.message||'Upload failed'; err.style.display='block'; return; }
    document.getElementById('ovc-file').value=''; document.getElementById('ovc-note').value='';
    load();
  };
  load();
}

async function ovRecalcVisit(visitId, patientId){
  if(!confirm('Recalculate all invoices for this visit?\n\nThis will:\n- Look up insurance-agreed prices from insurance_procedure_prices\n- Apply the correct copayment percentage\n- Update invoice totals and balances\n\nProceed?')) return;

  try{
    const r = await fetch(window.location.origin + _pBase + '/api.php?endpoint=patients/billing&action=recalculate_visit', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({visit_id: visitId, patient_id: patientId})
    });
    const j = await r.json();
    if(j.success){
      alert('✓ '+j.message);
      ovLoadTodayVisits();
    } else {
      alert('Error: '+(j.message||'Recalculation failed'));
    }
  }catch(e){alert('Failed: '+e.message);}
}

async function ovChangeStatus(visitId, newStatus){
  const label=newStatus==='completed'?'complete':'cancel';
  if(!confirm('Are you sure you want to '+label+' this visit?'))return;
  try{
    const r=await fetch(ovVisAPI+'&id='+visitId,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({status:newStatus})});
    const j=await r.json();
    if(j.success){ovLoadTodayVisits();}
    else{alert('Failed: '+j.message);}
  }catch(e){alert('Error: '+e.message);}
}

// Load today's visits on page load
dfRange('ov-date-from','ov-date-to',0);
let ovFilterTimer;
function ovClearFilters(){
  const el=document.getElementById('ov-search-filter'); if(el) el.value='';
  const sf=document.getElementById('ov-status-filter'); if(sf) sf.value='';
  dfRange('ov-date-from','ov-date-to',0);
  ovLoadTodayVisits();
}
ovLoadTodayVisits();

let ovTimer;
document.getElementById('ov-search-input').addEventListener('input',function(){
  clearTimeout(ovTimer); ovTimer=setTimeout(ovSearch,350);
});

// Export visits to Excel (client-side from the current rendered table)
function ovExportExcel(){
  const searchVal = (document.getElementById('ov-search-filter')?.value||'').trim();
  const dateFrom  = document.getElementById('ov-date-from')?.value||'';
  const dateTo    = document.getElementById('ov-date-to')?.value||'';

  // Collect rows from the rendered table
  const tbody = document.getElementById('ov-today-tbody');
  const rows  = tbody ? tbody.querySelectorAll('tr') : [];
  if(!rows.length || (rows.length===1 && rows[0].querySelector('[colspan]'))){
    alert('No visits to export.'); return;
  }

  const headers = ['Date / Time','Patient','Patient No.','Visit No.','Insurance & Member / Card No.','Type','Status'];
  const data    = [headers];

  rows.forEach(tr=>{
    const cells = tr.querySelectorAll('td');
    if(!cells.length) return;
    // col 0: date/time, col 1: patient name + no (two divs), col 2: visit no, col 3: member / card no + insurance,
    // col 4: type, col 5: status badge, col 6: tracking, col 7: actions (skip)
    const dateTime    = cells[0]?.innerText.replace(/\s+/g,' ').trim()||'';
    const patientName = cells[1]?.querySelector('div:first-child')?.textContent.trim()||'';
    const patientNo   = cells[1]?.querySelector('div:last-child')?.textContent.trim()||'';
    const visitNo     = cells[2]?.textContent.trim()||'';
    const memberNo    = cells[3]?.textContent.replace(/\s+/g,' ').replace('—','').trim()||'';
    const type        = cells[4]?.textContent.trim()||'';
    const status      = cells[5]?.textContent.trim()||'';
    data.push([dateTime, patientName, patientNo, visitNo, memberNo, type, status]);
  });

  // Build CSV (tab-separated for Excel compatibility)
  const csv = data.map(row=>row.map(cell=>{
    const s = String(cell).replace(/"/g,'""');
    return '"'+s+'"';
  }).join('\t')).join('\r\n');

  const bom  = '\uFEFF'; // UTF-8 BOM for Excel
  const blob = new Blob([bom+csv], {type:'text/csv;charset=utf-8;'});
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  const label = 'visits'+(dateFrom?'_'+dateFrom:'')+(dateTo&&dateTo!==dateFrom?'_to_'+dateTo:'')+(searchVal?'_'+searchVal:'')+'.csv';
  a.href=url; a.download=label; document.body.appendChild(a); a.click();
  document.body.removeChild(a); URL.revokeObjectURL(url);
}

// ============================================================
// External Prescription Modal & PDF/Print Handling
// ============================================================
window._ovCurrentRx = null;
window._ovCurrentRxPatient = '';
window._ovCurrentRxVisitNo = '';

async function ovPrintExternalRx(visitId, patientName, visitNo){
  const modal = document.getElementById('ov-rx-modal');
  if(!modal) return;
  modal.style.display = 'flex';

  const titleEl = document.getElementById('ov-rx-modal-title');
  const subEl = document.getElementById('ov-rx-modal-subtitle');
  if(titleEl) titleEl.textContent = 'External Prescription';
  if(subEl) subEl.textContent = (patientName || 'Patient') + (visitNo ? ' · ' + visitNo : '');

  const printArea = document.getElementById('ov-rx-printable');
  if(printArea){
    printArea.innerHTML = '<div style="text-align:center;padding:50px;color:var(--gray-400);font-family:sans-serif;"><i class="fas fa-spinner fa-spin" style="font-size:28px;color:#16a34a;"></i><div style="margin-top:10px;font-size:13px;font-weight:500;">Loading prescription details...</div></div>';
  }

  try {
    // 1. Fetch prescription details
    const rxUrl = window.location.origin + _pBase + '/api.php?endpoint=patients/visits&action=external_prescription&visit_id=' + encodeURIComponent(visitId);
    const rxRes = await fetch(rxUrl);
    const rxJson = await rxRes.json();

    if(!rxJson.success || !rxJson.data){
      if(printArea) printArea.innerHTML = '<div style="text-align:center;padding:35px;color:#dc2626;font-family:sans-serif;"><i class="fas fa-exclamation-circle" style="font-size:26px;margin-bottom:8px;"></i><br>' + ovEsc(rxJson.message || 'Prescription not found.') + '</div>';
      return;
    }

    const rxData = rxJson.data;
    const items = rxData.items || [];
    if(!items.length){
      if(printArea) printArea.innerHTML = '<div style="text-align:center;padding:35px;color:var(--gray-500);font-family:sans-serif;"><i class="fas fa-info-circle" style="font-size:26px;margin-bottom:8px;color:#3b82f6;"></i><br>No prescription items recorded for this visit.</div>';
      return;
    }

    // Save for PDF export and printing
    window._ovCurrentRx = rxData;
    window._ovCurrentRxPatient = rxData.visit?.patient_name || rxData.patient_name || patientName || 'Patient';
    window._ovCurrentRxVisitNo = rxData.visit?.visit_no || rxData.visit_no || visitNo || '';

    // 2. Fetch hospital settings for branding
    let hs = {};
    try {
      const hr = await fetch(window.location.origin + _pBase + '/api.php?endpoint=settings/hospital');
      const hj = await hr.json();
      if(hj.success) hs = hj.data || {};
    } catch(e){}

    const hospName   = hs.hospital_name || 'Iramiro Clinic';
    const docLogo    = hs.doc_logo || hs.logo || '';
    const hLineColor = hs.header_line_color || '#059669';
    const hLineSize  = parseInt(hs.header_line_size) || 2;
    const hTextColor = hs.header_text_color || '#1e293b';
    const hFontSize  = parseInt(hs.header_font_size) || 13;
    const hLeftText  = hs.header_left || hospName;
    const rawDate    = rxData.prescription_date || rxData.visit?.visit_date || '';
    const today      = rawDate ? new Date(rawDate).toLocaleDateString() : new Date().toLocaleDateString();
    const hRightText = (hs.header_right || 'Date: {date}').replace('{date}', today).replace('{ref}', window._ovCurrentRxVisitNo || 'RX');
    const fLineColor = hs.footer_line_color || '#059669';
    const fLineSize  = parseInt(hs.footer_line_size) || 2;
    const fTextColor = hs.footer_text_color || '#475569';
    const fFontSize  = parseInt(hs.footer_font_size) || 10;
    const fLeftText  = hs.footer_left || (hs.address || 'Kigali, Rwanda');
    const fRightText = hs.footer_right || ((hs.phone ? 'Tel: ' + hs.phone + ' | ' : '') + 'Email: ' + (hs.email || ''));

    // Watermark
    let wmHtml = '';
    if(hs.watermark_enabled === '1'){
      const wmOpacity = (parseInt(hs.watermark_opacity) || 12) / 100;
      const wmRotation = hs.watermark_rotation || '-25';
      if(hs.watermark_type === 'image' && hs.watermark_image){
        wmHtml = `<div style="position:absolute;top:45%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;text-align:center;"><img src="${hs.watermark_image}" style="width:240px;max-width:80%;display:block;margin:0 auto;"></div>`;
      } else {
        const wmText = hs.watermark_text || hospName;
        wmHtml = `<div style="position:absolute;top:45%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;font-size:${hs.watermark_size||'48'}px;color:${hs.watermark_color||'#94a3b8'};font-weight:700;white-space:nowrap;z-index:0;text-transform:uppercase;letter-spacing:2px;">${ovEsc(wmText)}</div>`;
      }
    }

    // Patient info preparation
    const vInfo = rxData.visit || {};
    const patName = vInfo.patient_name || rxData.patient_name || patientName || 'Patient';
    const patNo = (vInfo.patient_no || rxData.patient_no) ? ` <span style="color:#64748b;font-size:12px;font-weight:normal;">(${ovEsc(vInfo.patient_no || rxData.patient_no)})</span>` : '';
    let doctorStr = (rxData.doctor_name || '').trim();
    if(doctorStr.toLowerCase().startsWith('dr.') || doctorStr.toLowerCase().startsWith('dr ')){
      doctorStr = doctorStr.substring(3).trim();
    }
    const deptStr = rxData.department_name ? ovEsc(rxData.department_name) : '';
    const prescriber = (doctorStr ? 'Dr. ' + ovEsc(doctorStr) : '') + (doctorStr && deptStr ? ' &bull; ' : '') + deptStr;
    const insName = vInfo.insurance_name || rxData.insurance_name || '';
    const memberNo = vInfo.member_number || rxData.member_number || '';
    const insHtml = (insName || memberNo) 
      ? `<strong>${ovEsc(insName || 'Covered')}</strong>${memberNo ? ` (Card: <span style="font-family:monospace;">${ovEsc(memberNo)}</span>)` : ''}`
      : `<span>Self-Pay</span>`;

    // Compute age
    let ageStr = '';
    const dob = vInfo.date_of_birth || rxData.date_of_birth || vInfo.dob || rxData.dob;
    if(dob){
      const d = new Date(dob);
      if(!isNaN(d.getTime())){
        const diffYears = Math.max(0, new Date().getFullYear() - d.getFullYear());
        ageStr = diffYears + ' yrs';
      }
    }

    // Build items table rows with clean, fixed widths
    const rows = items.map((it, i) => `
      <tr style="border-bottom:1px solid #e2e8f0;background:${i % 2 === 1 ? '#f8fafc' : '#ffffff'};">
        <td style="padding:8px 8px;text-align:center;color:#64748b;border-right:1px solid #e2e8f0;font-size:12px;">${i+1}</td>
        <td style="padding:8px 12px;font-weight:700;color:#0f172a;border-right:1px solid #e2e8f0;font-size:12.5px;">${ovEsc(it.drug || '—')}</td>
        <td style="padding:8px 12px;color:#334155;border-right:1px solid #e2e8f0;font-size:12px;">${ovEsc(it.dosage || '—')}</td>
        <td style="padding:8px 12px;color:#334155;border-right:1px solid #e2e8f0;font-size:12px;">${ovEsc(it.frequency || '—')}</td>
        <td style="padding:8px 12px;color:#334155;border-right:1px solid #e2e8f0;font-size:12px;">${ovEsc(it.duration || '—')}</td>
        <td style="padding:8px 8px;text-align:center;font-weight:600;color:#0f172a;font-size:12px;">${ovEsc(it.quantity || '—')}</td>
      </tr>
    `).join('');

    if(printArea){
      printArea.innerHTML = `
        <div id="ov-rx-doc-content" style="font-family:'Times New Roman',serif;color:#0f172a;line-height:1.5;position:relative;background:#ffffff;padding:30px 36px;width:100%;max-width:750px;min-height:980px;margin:0 auto;box-sizing:border-box;overflow:hidden;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,0.05);display:flex;flex-direction:column;justify-content:space-between;">
          ${wmHtml}
          <div style="position:relative;z-index:1;display:flex;flex-direction:column;justify-content:space-between;flex:1;min-height:920px;">
            <!-- Top Section -->
            <div>
              <!-- Document Header -->
              <table style="width:100%;border-collapse:collapse;border-bottom:${hLineSize}px solid ${hLineColor};margin-bottom:16px;padding-bottom:10px;">
                <tr>
                  <td style="vertical-align:middle;text-align:left;padding-bottom:8px;">
                    <table style="border-collapse:collapse;">
                      <tr>
                        ${docLogo ? `<td style="vertical-align:middle;padding-right:14px;"><img src="${docLogo}" style="height:54px;width:auto;max-width:140px;object-fit:contain;display:block;"></td>` : ''}
                        <td style="vertical-align:middle;">
                          <div style="font-size:${hFontSize + 1}px;font-weight:800;color:${hTextColor};text-transform:uppercase;letter-spacing:0.5px;line-height:1.2;">${hLeftText}</div>
                          <div style="font-size:11.5px;color:#64748b;margin-top:3px;">Medical Clinic &amp; Healthcare Services</div>
                        </td>
                      </tr>
                    </table>
                  </td>
                  <td style="vertical-align:middle;text-align:right;padding-bottom:8px;font-size:${hFontSize}px;color:${hTextColor};line-height:1.5;">
                    <div style="font-weight:600;">${hRightText}</div>
                  </td>
                </tr>
              </table>

              <!-- Title -->
              <div style="text-align:center;margin-bottom:18px;">
                <div style="font-size:20px;font-weight:800;letter-spacing:2px;color:#0f172a;text-transform:uppercase;border-bottom:2px solid #0f172a;display:inline-block;padding-bottom:2px;margin-bottom:4px;">EXTERNAL PRESCRIPTION</div>
                <div style="font-size:11.5px;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;font-weight:600;">Dispensing Order for External Pharmacy</div>
              </div>

              <!-- Patient & Visit Meta Table -->
              <table style="width:100%;border-collapse:collapse;margin-bottom:18px;border:1px solid #cbd5e1;background:#f8fafc;font-size:12.5px;">
                <tr>
                  <td style="width:52%;padding:10px 14px;vertical-align:top;border-right:1px solid #e2e8f0;line-height:1.7;">
                    <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Patient Name:</span> &nbsp;<strong style="font-size:13.5px;color:#0f172a;">${ovEsc(patName)}</strong>${patNo}</div>
                    <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Visit No:</span> &nbsp;<strong style="font-family:monospace;color:#1e40af;font-size:12px;">${ovEsc(vInfo.visit_no || window._ovCurrentRxVisitNo || '—')}</strong></div>
                    ${(vInfo.gender || rxData.gender || ageStr) ? `<div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Demographics:</span> &nbsp;${ovEsc(vInfo.gender || rxData.gender || '—')}${ageStr ? ' &bull; ' + ageStr : ''}</div>` : ''}
                    <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Coverage:</span> &nbsp;${insHtml}</div>
                  </td>
                  <td style="width:48%;padding:10px 14px;vertical-align:top;line-height:1.7;">
                    <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Prescription Date:</span> &nbsp;<strong>${today}</strong></div>
                    ${prescriber ? `<div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Prescribed By:</span> &nbsp;<strong>${prescriber}</strong></div>` : ''}
                    ${rxData.diagnosis ? `<div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Diagnosis:</span> &nbsp;${ovEsc(rxData.diagnosis)}</div>` : ''}
                  </td>
                </tr>
              </table>

              <!-- Prescription Items Table -->
              <table style="width:100%;border-collapse:collapse;margin-bottom:18px;font-size:12.5px;border:1px solid #cbd5e1;">
                <thead>
                  <tr style="background:#f1f5f9;border-bottom:2px solid #94a3b8;">
                    <th style="padding:8px 8px;text-align:center;width:35px;font-weight:700;color:#0f172a;border-right:1px solid #cbd5e1;">#</th>
                    <th style="padding:8px 12px;text-align:left;width:36%;font-weight:700;color:#0f172a;border-right:1px solid #cbd5e1;">Medication / Drug</th>
                    <th style="padding:8px 12px;text-align:left;width:20%;font-weight:700;color:#0f172a;border-right:1px solid #cbd5e1;">Dosage / Strength</th>
                    <th style="padding:8px 12px;text-align:left;width:20%;font-weight:700;color:#0f172a;border-right:1px solid #cbd5e1;">Frequency</th>
                    <th style="padding:8px 12px;text-align:left;width:15%;font-weight:700;color:#0f172a;border-right:1px solid #cbd5e1;">Duration</th>
                    <th style="padding:8px 8px;text-align:center;width:9%;font-weight:700;color:#0f172a;">Qty</th>
                  </tr>
                </thead>
                <tbody>
                  ${rows}
                </tbody>
              </table>

              ${rxData.notes ? `
              <div style="font-size:12px;margin-bottom:18px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:4px;line-height:1.5;">
                <strong style="color:#92400e;">Instructions / Notes:</strong> <span style="color:#78350f;">${ovEsc(rxData.notes)}</span>
              </div>` : ''}
            </div>

            <!-- Bottom Section (Signatures & Footer anchored to bottom) -->
            <div style="page-break-inside:avoid;">
              <!-- Signatures Block -->
              <table style="width:100%;border-collapse:collapse;margin-top:16px;margin-bottom:20px;page-break-inside:avoid;">
                <tr>
                  <td style="width:50%;vertical-align:bottom;text-align:left;padding-right:20px;">
                    <div style="font-size:11.5px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Prescribing Physician:</div>
                    <div style="width:210px;border-bottom:1.5px dashed #475569;height:32px;margin-bottom:4px;"></div>
                    <div style="font-size:12px;font-weight:700;color:#0f172a;">${doctorStr ? 'Dr. ' + ovEsc(doctorStr) : "Doctor's Signature &amp; Stamp"}</div>
                    <div style="font-size:11px;color:#64748b;margin-top:2px;">Date: ${today}</div>
                  </td>
                  <td style="width:50%;vertical-align:bottom;text-align:right;padding-left:20px;">
                    <div style="display:inline-block;text-align:left;">
                      <div style="font-size:11.5px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Dispensing Pharmacist:</div>
                      <div style="width:210px;border-bottom:1.5px dashed #475569;height:32px;margin-bottom:4px;"></div>
                      <div style="font-size:12px;font-weight:700;color:#0f172a;">Signature &amp; Stamp</div>
                      <div style="font-size:11px;color:#64748b;margin-top:2px;">Dispensed Date: ___________________</div>
                    </div>
                  </td>
                </tr>
              </table>

              <!-- Footer -->
              <table style="width:100%;border-collapse:collapse;border-top:${fLineSize}px solid ${fLineColor};margin-top:10px;padding-top:8px;font-size:${fFontSize}px;color:${fTextColor};line-height:1.4;page-break-inside:avoid;">
                <tr>
                  <td style="text-align:left;vertical-align:middle;width:60%;padding-top:6px;">
                    ${fLeftText}
                  </td>
                  <td style="text-align:right;vertical-align:middle;width:40%;white-space:nowrap;padding-top:6px;">
                    ${fRightText}
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      `;
    }
  } catch(e) {
    console.error('Failed to load prescription:', e);
    if(printArea) printArea.innerHTML = '<div style="text-align:center;padding:35px;color:#dc2626;font-family:sans-serif;"><i class="fas fa-exclamation-triangle" style="font-size:26px;margin-bottom:8px;"></i><br>Failed to load prescription: ' + ovEsc(e.message) + '</div>';
  }
}

async function ovDownloadRxPDF(){
  const srcEl = document.getElementById('ov-rx-doc-content');
  if(!srcEl){ alert('Prescription content not ready.'); return; }

  const pName = (window._ovCurrentRxPatient || 'Patient').replace(/[^a-zA-Z0-9 ]/g, '').replace(/\s+/g, '_');
  const vNo = (window._ovCurrentRxVisitNo || '').replace(/[^a-zA-Z0-9\-]/g, '_');
  const filename = 'External_Prescription_' + pName + (vNo ? '_' + vNo : '') + '.pdf';

  const btn = document.getElementById('ov-rx-btn-download');
  const origHtml = btn ? btn.innerHTML : '';
  if(btn){ btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...'; }

  if(typeof html2pdf === 'undefined'){
    alert('PDF generator library not loaded. Please use Print button instead.');
    if(btn){ btn.disabled = false; btn.innerHTML = origHtml; }
    return;
  }

  // Clone document to prepare pristine A4 layout geometry
  const clone = srcEl.cloneNode(true);
  clone.id = 'ov-rx-pdf-target';
  clone.style.width = '750px';
  clone.style.maxWidth = '750px';
  clone.style.minWidth = '750px';
  clone.style.minHeight = '1030px';
  clone.style.padding = '32px 38px';
  clone.style.margin = '0';
  clone.style.background = '#ffffff';
  clone.style.border = 'none';
  clone.style.borderRadius = '0';
  clone.style.boxShadow = 'none';
  clone.style.boxSizing = 'border-box';
  clone.style.position = 'relative';
  clone.style.overflow = 'hidden';
  clone.style.display = 'flex';
  clone.style.flexDirection = 'column';
  clone.style.justifyContent = 'space-between';

  const wrapper = document.createElement('div');
  wrapper.style.position = 'fixed';
  wrapper.style.left = '-9999px';
  wrapper.style.top = '0';
  wrapper.style.width = '750px';
  wrapper.style.background = '#ffffff';
  wrapper.style.zIndex = '-9999';
  wrapper.appendChild(clone);
  document.body.appendChild(wrapper);

  const opt = {
    margin: [8, 10, 8, 10], // mm
    filename: filename,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: {
      scale: 2,
      useCORS: true,
      logging: false,
      width: 750,
      windowWidth: 750
    },
    jsPDF: {
      unit: 'mm',
      format: 'a4',
      orientation: 'portrait'
    },
    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
  };

  try {
    await html2pdf().set(opt).from(clone).save();
  } catch(err) {
    console.error('PDF error:', err);
    alert('Failed to generate PDF: ' + (err.message || 'Unknown error'));
  } finally {
    if(wrapper.parentNode){
      wrapper.parentNode.removeChild(wrapper);
    }
    if(btn){ btn.disabled = false; btn.innerHTML = origHtml; }
  }
}

function ovPrintRxBrowser(){
  const srcEl = document.getElementById('ov-rx-doc-content');
  if(!srcEl){ alert('Prescription content not ready.'); return; }

  const title = 'External Prescription - ' + (window._ovCurrentRxPatient || '');
  const win = window.open('', '_blank', 'width=880,height=980');
  if(!win){ alert('Pop-up blocked! Please allow pop-ups for this site.'); return; }

  win.document.write(`<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${title}</title>
  <style>
    @page {
      size: A4 portrait;
      margin: 8mm 10mm;
    }
    * {
      box-sizing: border-box;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
      color-adjust: exact !important;
    }
    html, body {
      margin: 0;
      padding: 0;
      background: #ffffff;
      font-family: 'Times New Roman', serif;
      color: #000000;
    }
    .print-sheet {
      width: 100%;
      max-width: 750px;
      min-height: 1000px;
      margin: 0 auto;
      background: #ffffff;
      padding: 0;
      position: relative;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    img { max-width: 100%; }
    @media screen {
      body {
        padding: 24px;
        background: #f1f5f9;
        display: flex;
        justify-content: center;
      }
      .print-sheet {
        background: #ffffff;
        padding: 30px 36px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        border-radius: 4px;
      }
    }
    @media print {
      body {
        padding: 0;
        margin: 0;
        background: #ffffff;
      }
      .print-sheet {
        padding: 0;
        margin: 0;
        max-width: 100%;
        box-shadow: none;
        border: none !important;
      }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>
  <div class="print-sheet">
    ${srcEl.innerHTML}
  </div>
</body>
</html>`);
  win.document.close();
  win.focus();
  setTimeout(() => {
    win.print();
  }, 400);
}

function ovCloseRxModal(){
  const m = document.getElementById('ov-rx-modal');
  if(m) m.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', ()=>{
  const m = document.getElementById('ov-rx-modal');
  if(m){
    m.addEventListener('click', (e)=>{
      if(e.target === m) ovCloseRxModal();
    });
  }
});
</script>
<?php endif; ?>

<?php if ($subModule == 'patients-visit' && $currentModule != 'nurse'): ?>

<!-- ============================================================
     PATIENTS VISITS — list, filter, send to consultation
     ============================================================ -->

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;" id="pv-stats-row">
  <div class="pv-stat-card" style="background:white;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);border-left:4px solid var(--primary-color);">
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;">Total Today</div>
    <div style="font-size:28px;font-weight:800;color:var(--gray-900);margin-top:4px;" id="pv-stat-today">—</div>
  </div>
  <div class="pv-stat-card" style="background:white;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);border-left:4px solid var(--success-color);">
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;">Open</div>
    <div style="font-size:28px;font-weight:800;color:var(--success-color);margin-top:4px;" id="pv-stat-open">—</div>
  </div>
  <div class="pv-stat-card" style="background:white;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);border-left:4px solid var(--warning-color);">
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;">In Progress</div>
    <div style="font-size:28px;font-weight:800;color:var(--warning-color);margin-top:4px;" id="pv-stat-inprogress">—</div>
  </div>
  <div class="pv-stat-card" style="background:white;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);border-left:4px solid var(--gray-400);">
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;">Completed</div>
    <div style="font-size:28px;font-weight:800;color:var(--gray-400);margin-top:4px;" id="pv-stat-completed">—</div>
  </div>
</div>

<!-- Filter Bar -->
<div style="background:white;border-radius:12px;padding:16px 20px;box-shadow:var(--shadow);margin-bottom:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
  <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:200px;">
    <i class="fas fa-search" style="color:var(--gray-400);"></i>
    <input id="pv-search" type="text" placeholder="Search name, UHID, visit no…"
      style="border:none;outline:none;font-size:14px;width:100%;background:transparent;"
      oninput="pvScheduleLoad()">
  </div>
  <div style="width:1px;height:28px;background:var(--gray-200);"></div>
  <label style="font-size:13px;color:var(--gray-600);font-weight:500;">From</label>
  <input id="pv-date-from" type="date" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;" onchange="pvLoad()">
  <label style="font-size:13px;color:var(--gray-600);font-weight:500;">To</label>
  <input id="pv-date-to" type="date" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;" onchange="pvLoad()">
  <button onclick="pvSetToday()" style="padding:7px 14px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;">Today</button>
  <button onclick="pvClearFilter()" style="padding:7px 14px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:8px;font-size:13px;cursor:pointer;">Clear</button>
  <select id="pv-status-filter" onchange="pvLoad()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;">
    <option value="">All Statuses</option>
    <option value="open">Open</option>
    <option value="in_progress">In Progress</option>
    <option value="completed">Completed</option>
    <option value="cancelled">Cancelled</option>
  </select>
</div>

<!-- Table -->
<div style="background:white;border-radius:12px;box-shadow:var(--shadow);overflow:hidden;">
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-100);">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Visit No.</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Patient</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Date / Time</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Type</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Payment</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Status</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Tracking Info</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Department</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;letter-spacing:.5px;">Actions</th>
        </tr>
      </thead>
      <tbody id="pv-tbody" data-no-page>
        <tr><td colspan="10" style="text-align:center;padding:48px;color:var(--gray-400);">
          <i class="fas fa-spinner fa-spin" style="font-size:24px;display:block;margin-bottom:8px;"></i>Loading…
        </td></tr>
      </tbody>
    </table>
  </div>
  <!-- Pagination -->
  <div id="pv-pagination" style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--gray-100);font-size:13px;color:var(--gray-500);"></div>
</div>

<!-- ============================================================
     SEND TO CONSULTATION MODAL
     ============================================================ -->
<div id="pv-consult-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px 12px;box-sizing:border-box;">
  <div style="background:white;border-radius:16px;width:100%;max-width:560px;margin:auto;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;">

    <!-- Modal Header -->
    <div style="background:var(--primary-color);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
      <div>
        <h3 style="color:white;font-size:17px;font-weight:700;margin-bottom:2px;"><i class="fas fa-user-md" style="margin-right:8px;"></i>Send to Consultant</h3>
        <div style="color:rgba(255,255,255,.75);font-size:13px;" id="pv-modal-subtitle">—</div>
      </div>
      <button onclick="pvCloseModal()" style="background:rgba(255,255,255,.2);border:none;color:white;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:16px;">×</button>
    </div>

    <!-- Modal Body -->
    <div style="padding:24px;">
      <div id="pv-modal-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>

      <!-- Department -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:6px;">Department <span style="color:var(--danger-color);">*</span></label>
        <select id="pv-dept-select" onchange="pvDeptChanged()" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
          <option value="">— Select Department —</option>
        </select>
      </div>

      <!-- Doctor -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:6px;">Doctor / Clinician <span style="color:var(--gray-400);font-weight:400;">(optional)</span></label>
        <div id="pv-doctor-wrapper" style="display:none;">
          <select id="pv-doctor-select" onchange="dutyShow('pv')"
            style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="">— Select Doctor / Clinician (optional) —</option>
          </select>
        </div>
        <div id="pv-doctor-duty" style="margin-top:6px;"></div>
        <div id="pv-doctor-empty" style="font-size:13px;color:var(--gray-400);padding:8px 0;">Select a department first</div>
      </div>
      <div class="ref-crit-mount" data-p="pv"></div>
      <div class="md-extra-mount" data-p="pv"></div>

      <!-- Consultation Service -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:6px;">Consultation / Follow-up Fee <span style="color:var(--danger-color);">*</span> <span style="color:var(--gray-400);font-weight:400;">(insurance applied from the visit)</span></label>
        <select id="pv-cfee" disabled style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
          <option value="">— Select a department first —</option>
        </select>
        <div id="pv-cfee-note" style="display:none;"></div>
      </div>

      <!-- Chief Complaint (read-only info) -->
      <div id="pv-modal-complaint-row" style="display:none;background:var(--gray-50);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:var(--gray-600);">
        <i class="fas fa-notes-medical" style="color:var(--primary-color);margin-right:6px;"></i>
        <strong>Complaint:</strong> <span id="pv-modal-complaint-text">—</span>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:10px;margin-top:8px;">
        <button onclick="pvCloseModal()" style="flex:1;padding:11px;background:var(--gray-100);color:var(--gray-700);border:none;border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
        <button id="pv-consult-submit-btn" onclick="pvSubmitConsultation()" style="flex:2;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
          <i class="fas fa-file-invoice-dollar"></i> Send to Consultant &amp; Generate Invoice
          &amp; Send to Department
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Success toast -->
<div id="pv-toast" style="display:none;position:fixed;bottom:28px;right:28px;background:#1e293b;color:white;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:500;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.3);align-items:center;gap:10px;">
  <i class="fas fa-check-circle" style="color:var(--success-color);"></i>
  <span id="pv-toast-msg">Done!</span>
</div>

<?php endif; ?>

<?php if ($subModule == 'patients-visit' && $currentModule != 'nurse'): ?>
<script>
const pvAPI  = window.location.origin + _pBase + '/api.php?endpoint=patients/visits';
const pvDAPI = window.location.origin + _pBase + '/api.php?endpoint=patients/patients';

let pvPage = 1;
let pvTimer = null;
let pvCurrentVisit = null;

// ---- helpers ----
function pvEsc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function pvStatusBadge(status){
  const map = {
    open:        {bg:'#dcfce7',color:'#166534',label:'Open'},
    in_progress: {bg:'#fef9c3',color:'#854d0e',label:'In Progress'},
    completed:   {bg:'#e0e7ff',color:'#3730a3',label:'Completed'},
    cancelled:   {bg:'#fee2e2',color:'#991b1b',label:'Cancelled'}
  };
  const s = map[status] || {bg:'#f1f5f9',color:'#475569',label:status||'—'};
  return `<span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${s.bg};color:${s.color};">${s.label}</span>`;
}

function pvPayBadge(type){
  const map = {
    self_pay:  {bg:'#f0f9ff',color:'#0369a1',label:'Self Pay'},
    insurance: {bg:'#f0fdf4',color:'#166534',label:'Insurance'},
    mixed:     {bg:'#fef9c3',color:'#854d0e',label:'Mixed'}
  };
  const s = map[type] || {bg:'#f1f5f9',color:'#475569',label:type||'—'};
  return `<span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;background:${s.bg};color:${s.color};">${s.label}</span>`;
}

function pvTypeBadge(type){
  const map = {
    outpatient: 'OPD', inpatient: 'IPD',
    emergency: 'EMG', follow_up: 'F/U'
  };
  return pvEsc(map[type] || type || '—');
}

function pvFmtDate(d, t){
  if(!d) return '—';
  const dt = new Date(d + (t ? 'T'+t : ''));
  return dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})
       + (t ? ' <span style="color:var(--gray-400);font-size:12px;">'
             + dt.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'})+'</span>' : '');
}

function pvFmtDateTime(dt){
  if(!dt) return '—';
  const d = new Date(dt);
  return d.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})
       + ' ' + d.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'});
}

function pvShowToast(msg){
  const t = document.getElementById('pv-toast');
  document.getElementById('pv-toast-msg').textContent = msg;
  t.style.display = 'flex';
  setTimeout(()=>{ t.style.display='none'; }, 3000);
}

// ---- init ----  (called at end of script after all functions defined)
function pvInit(){
  dfRange('pv-date-from','pv-date-to');
  pvLoad();
  pvLoadDepartments();
}

function pvSetToday(){
  const today = new Date().toISOString().slice(0,10);
  document.getElementById('pv-date-from').value = today;
  document.getElementById('pv-date-to').value   = today;
  pvPage = 1; pvLoad();
}
function pvClearFilter(){
  document.getElementById('pv-date-from').value = '';
  document.getElementById('pv-date-to').value   = '';
  document.getElementById('pv-search').value    = '';
  document.getElementById('pv-status-filter').value = '';
  pvPage = 1; pvLoad();
}
function pvScheduleLoad(){ clearTimeout(pvTimer); pvTimer = setTimeout(()=>{ pvPage=1; pvLoad(); }, 350); }

// ---- main load ----
async function pvLoad(){
  const tbody = document.getElementById('pv-tbody');
  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:22px;"></i></td></tr>';

  const search  = document.getElementById('pv-search').value.trim();
  const from    = document.getElementById('pv-date-from').value;
  const to      = document.getElementById('pv-date-to').value;
  const status  = document.getElementById('pv-status-filter').value;

  let url = pvAPI + '&page='+pvPage+'&limit='+pgSize('pv',25);
  if(search) url += '&search='+encodeURIComponent(search);
  if(from)   url += '&date_from='+encodeURIComponent(from);
  if(to)     url += '&date_to='+encodeURIComponent(to);
  if(status) url += '&status='+encodeURIComponent(status);

  try {
    const r = await fetch(url);
    const j = await r.json();
    if(!j.success){ tbody.innerHTML='<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--danger-color);">'+pvEsc(j.message||'Error loading visits')+'</td></tr>'; return; }

    pvRenderStats(j.data.stats);
    pvRenderTable(j.data.visits);
    pvRenderPagination(j.data.pagination);
  } catch(e){
    tbody.innerHTML='<tr><td colspan="8" style="text-align:center;padding:40px;color:var(--danger-color);">Network error: '+pvEsc(e.message)+'</td></tr>';
  }
}

function pvRenderStats(s){
  if(!s) return;
  document.getElementById('pv-stat-today').textContent     = s.today       ?? '—';
  document.getElementById('pv-stat-open').textContent      = s.open_visits ?? '—';
  document.getElementById('pv-stat-inprogress').textContent= s.in_progress ?? '—';
  document.getElementById('pv-stat-completed').textContent = s.completed   ?? '—';
}

function pvRenderTable(visits){
  const tbody = document.getElementById('pv-tbody');
  if(!visits || !visits.length){
    tbody.innerHTML='<tr><td colspan="10" style="text-align:center;padding:48px;color:var(--gray-400);"><i class="fas fa-calendar-times" style="font-size:32px;display:block;margin-bottom:10px;color:var(--gray-200);"></i>No visits found for this period</td></tr>';
    return;
  }
  tbody.innerHTML = visits.map(v => `
    <tr style="border-bottom:1px solid var(--gray-100);transition:.15s;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
      <td style="padding:13px 16px;font-family:monospace;font-weight:600;color:var(--primary-color);font-size:13px;">${pvEsc(v.visit_no)}</td>
      <td style="padding:13px 16px;">
        <div style="font-weight:600;color:var(--gray-900);">${pvEsc(v.patient_name||((v.first_name||'')+' '+(v.last_name||'')))}${tariffBadge(v.tariff_category)}</div>
        <div style="font-size:12px;color:var(--primary-color);font-family:monospace;">${pvEsc(v.patient_no||'')}</div>
        ${v.phone ? '<div style="font-size:12px;color:var(--gray-400);">'+pvEsc(v.phone)+'</div>' : ''}
      </td>
      <td style="padding:13px 16px;color:var(--gray-700);white-space:nowrap;">${pvFmtDate(v.visit_date, v.visit_time)}</td>
      <td style="padding:13px 16px;">${pvTypeBadge(v.visit_type)}</td>
      <td style="padding:13px 16px;">${pvPayBadge(v.payment_type)}</td>
      <td style="padding:13px 16px;">${pvStatusBadge(v.status)}</td>
      <td style="padding:13px 16px;color:var(--gray-600);font-size:13px;">
        <div style="font-size:12px;">
          ${v.opened_by_name ? '<strong>' + pvEsc(v.opened_by_name) + '</strong>' : '—'}
        </div>
        ${v.cancelled_by_name ? '<div style="color:#dc2626;margin-top:4px;"><i class="fas fa-ban" style="margin-right:3px;"></i><strong>Cancelled</strong><br><span style="font-size:11px;">' + pvEsc(v.cancelled_by_name) + '</span><br><span style="font-size:10px;color:var(--gray-400);">' + pvFmtDateTime(v.cancelled_at) + '</span></div>' : ''}
        ${v.completed_by_name ? '<div style="color:#16a34a;margin-top:4px;"><i class="fas fa-check-circle" style="margin-right:3px;"></i><strong>Completed</strong><br><span style="font-size:11px;">' + pvEsc(v.completed_by_name) + '</span><br><span style="font-size:10px;color:var(--gray-400);">' + pvFmtDateTime(v.completed_at) + '</span></div>' : ''}
      </td>
      <td style="padding:13px 16px;color:var(--gray-600);font-size:13px;">${pvEsc(v.department_name||'—')}</td>
      <td style="padding:13px 16px;text-align:center;">
        ${v.status === 'open'
          ? `<button onclick='pvOpenConsultModal(${JSON.stringify(v)})'
               style="padding:7px 14px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">
               <i class="fas fa-user-md"></i> Send to Consultant
             </button>`
          : v.status === 'in_progress'
            ? `<span style="padding:6px 12px;background:#fef9c3;color:#854d0e;border-radius:8px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                 <i class="fas fa-lock"></i> Sent to Consultant
               </span>`
            : `<span style="padding:6px 12px;background:var(--gray-100);color:var(--gray-400);border-radius:8px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                 <i class="fas fa-ban"></i> ${pvEsc(v.status)}
               </span>`
        }
      </td>
    </tr>`).join('');
}

function pvRenderPagination(p){
  const el = document.getElementById('pv-pagination');
  if(!p){ el.innerHTML=''; return; }
  if(p.total_pages <= 1){ el.innerHTML=`<div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect('pv',25,'pvPage=1;pvLoad()',[10,25,50,100])}<span>${p.total} visits</span></div><div></div>`; return; }
  const from = ((p.page-1)*p.limit)+1;
  const to   = Math.min(p.page*p.limit, p.total);
  let btns = '';
  for(let i=1;i<=p.total_pages;i++){
    const active = i===p.page;
    btns += `<button onclick="pvPage=${i};pvLoad()" style="padding:6px 12px;border:1px solid ${active?'var(--primary-color)':'var(--gray-200)'};background:${active?'var(--primary-color)':'white'};color:${active?'white':'var(--gray-600)'};border-radius:6px;font-size:13px;cursor:pointer;">${i}</button>`;
  }
  el.innerHTML = `<div style="display:flex;align-items:center;gap:12px;">${pgSizeSelect('pv',25,'pvPage=1;pvLoad()',[10,25,50,100])}<span>Showing ${from}–${to} of ${p.total}</span></div><div style="display:flex;gap:4px;">${btns}</div>`;
}

// ---- Departments loader ----
async function pvLoadDepartments(){
  const sel = document.getElementById('pv-dept-select');
  try {
    const r = await fetch(window.location.origin + _pBase + '/api.php?endpoint=patients/visits&action=departments');
    const j = await r.json();
    if(j.success && j.data && j.data.length){
      j.data.forEach(d=>{
        const o = document.createElement('option');
        o.value = d.id; o.textContent = d.name;
        sel.appendChild(o);
      });
      // default to General Medicine
      const gm = Array.from(sel.options).find(o=>o.textContent==='General Medicine');
      if(gm){ sel.value = gm.value; pvDeptChanged(); }
      return;
    }
  } catch(e){}
  // hardcoded fallback if API fails
  [{id:11,name:'General Medicine'},{id:3,name:'Outpatient Department (OPD)'},
   {id:5,name:'Emergency Department'},{id:14,name:'Cardiology'},
   {id:15,name:'Neurology'},{id:13,name:'Orthopedics'},
   {id:31,name:'Obstetrics & Gynecology'},{id:35,name:'Pediatrics'},
   {id:12,name:'General Surgery'},{id:27,name:'Psychiatry'}
  ].forEach(d=>{
    const o = document.createElement('option');
    o.value = d.id; o.textContent = d.name;
    sel.appendChild(o);
  });
  sel.value = 11; pvDeptChanged();
}

// ---- Load doctors/users for selected department ----
async function pvDeptChanged(){
  const deptId  = document.getElementById('pv-dept-select').value;
  const wrapper = document.getElementById('pv-doctor-wrapper');
  const empty   = document.getElementById('pv-doctor-empty');
  const select  = document.getElementById('pv-doctor-select');
  const fee = document.getElementById('pv-cfee'), feeNote = document.getElementById('pv-cfee-note');
  if(!deptId){ wrapper.style.display='none'; empty.style.display='block'; fee.disabled=true; fee.dataset.rule=''; fee.innerHTML='<option value="">— Select a department first —</option>'; feeNote.style.display='none'; return; }
  if(pvCurrentVisit) crLoad(fee, feeNote, 'department_id='+encodeURIComponent(deptId)+'&visit_id='+pvCurrentVisit.id);

  wrapper.style.display = 'block';
  empty.style.display   = 'none';
  select.innerHTML = '<option value="">Loading…</option>';

  try {
    const r = await fetch(window.location.origin + _pBase + '/api.php?endpoint=patients/visits&action=users_by_dept&department_id='+encodeURIComponent(deptId));
    const j = await r.json();
    select.innerHTML = '<option value="">— Select Doctor / Clinician (optional) —</option>';
    if(j.success && j.data && j.data.length){
      j.data.forEach(u=>{
        const o = document.createElement('option');
        o.value = u.first_name+' '+u.last_name;
        o.dataset.id = u.id;
        o.dataset.duty = u.on_duty ? '1' : '0';
        o.textContent = ptDocName(u)+(u.role_name?' ('+u.role_name+')':'')+' ('+(parseInt(u.queue_count)||0)+' queue) - '+(u.on_duty?'On duty':'Off duty');
        if(!u.on_duty) o.style.color = '#9ca3af';
        select.appendChild(o);
      });
    } else {
      select.innerHTML += '<option disabled>No users in this department</option>';
    }
    dutyShow('pv');
  } catch(e){
    select.innerHTML = '<option value="">— could not load users —</option>';
  }
}

// ---- Open consultation modal ----
function pvOpenConsultModal(visit){
  if(visit.status !== 'open'){
    pvShowToast('This visit has already been sent to consultation'); return;
  }
  pvCurrentVisit = visit;
  document.getElementById('pv-modal-subtitle').textContent =
    (visit.patient_name || visit.first_name+' '+visit.last_name) + ' · ' + visit.visit_no;
  document.getElementById('pv-modal-alert').style.display = 'none';

  const complaintRow = document.getElementById('pv-modal-complaint-row');
  if(visit.chief_complaint){
    document.getElementById('pv-modal-complaint-text').textContent = visit.chief_complaint;
    complaintRow.style.display = 'block';
  } else { complaintRow.style.display = 'none'; }

  // Pre-select dept if already set, then load doctors
  const deptSel = document.getElementById('pv-dept-select');
  if(visit.department_id) deptSel.value = visit.department_id;
  pvDeptChanged().then(()=>{
    // After doctors load, pre-select if doctor_name is set
    if(visit.doctor_name || visit.doctor_id){
      const sel = document.getElementById('pv-doctor-select');
      const match = Array.from(sel.options).find(o=>(visit.doctor_id && o.dataset.id==visit.doctor_id) || o.value===visit.doctor_name);
      if(match) sel.value = match.value;
      dutyShow('pv');
    }
  });
  refCritReset('pv'); refCritCtx.pv = visit; mdReset('pv');

  document.getElementById('pv-consult-modal').style.display = 'flex';
}

function pvCloseModal(){
  document.getElementById('pv-consult-modal').style.display = 'none';
  pvCurrentVisit = null;
}

// close on backdrop click
document.getElementById('pv-consult-modal').addEventListener('click', function(e){
  if(e.target === this) pvCloseModal();
});

// ---- Submit consultation + create invoice ----
async function pvSubmitConsultation(){
  const btn   = document.getElementById('pv-consult-submit-btn');
  const alert = document.getElementById('pv-modal-alert');
  alert.style.display = 'none';

  const deptId = document.getElementById('pv-dept-select').value;
  const doctor = document.getElementById('pv-doctor-select').value.trim();
  const doctorId = document.getElementById('pv-doctor-select').selectedOptions[0]?.dataset.id || '';
  const feeSel = document.getElementById('pv-cfee');

  if(!deptId){ alert.textContent='Please select a department.'; alert.style.display='block'; return; }
  const feeErr = crMissing(feeSel);
  if(feeErr){ alert.textContent=feeErr; alert.style.display='block'; return; }
  const ref = refCritCollect('pv');
  if(ref.error){ alert.textContent=ref.error; alert.style.display='block'; return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

  try {
    const v = pvCurrentVisit;
    const extras = mdCollect('pv', deptId); // validates the extra rows before anything is saved

    // 0. Main department fee first (server checks the 3/14-day rule; nothing is sent when it is refused)
    if(!feeSel.disabled){
      const fj = await crSave(v.id, parseInt(deptId), parseInt(feeSel.value)||null);
      if(!fj.success) throw new Error(fj.message || 'Consultation fee not saved');
      v._feeMsg = fj.data.invoice_no ? fj.data.note+' ('+fj.data.invoice_no+')' : fj.data.note;
    }

    // 1. Extra departments (each: own assignment + own fee), so the main department is assigned last and stays the visit's department
    const extraDone = await mdSend('pv', extras, ref);

    // 2. Assign dept/doctor on visit
    const aj = await crPost(pvAPI+'&id='+v.id, 'PUT', {
      action: 'assign_consultation',
      department_id: parseInt(deptId),
      doctor_id: doctorId ? parseInt(doctorId) : null,
      doctor_name: doctor || null,
      priority: ref.priority,
      critical_info: ref.critical_info || null
    });
    if(!aj.success) throw new Error(aj.message || 'Could not send the patient');

    const deptName = document.getElementById('pv-dept-select').selectedOptions[0].text;
    pvCloseModal();
    pvShowToast('✓ Patient sent to '+deptName+(v._feeMsg?' — '+v._feeMsg:'')+(extraDone.length?' + '+extraDone.join(', '):''));
    pvLoad(); // refresh table

  } catch(e){
    alert.textContent = e.message || 'Something went wrong';
    alert.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-file-invoice-dollar"></i> Send to Consultant & Generate Invoice';
  }
}

// ---- boot ----
pvInit();
</script>
<?php endif; ?>

<!-- ============================================================
     NURSE QUEUE VIEW — patients.php?module=nurse&sub=patients-visit
     Shows queue as received from reception with Call Next button
============================================================ -->
<?php if ($currentModule == 'nurse' && $subModule == 'patients-visit'): ?>
<?php
// Prices visible only to admins and cashiers/finance (same rule as nursing.php)
$nqRs = $pdo->prepare("SELECT GROUP_CONCAT(r.slug) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?");
$nqRs->execute([$_SESSION['user_id'] ?? 0]);
$nqShowPrices = array_intersect(explode(',', (string)$nqRs->fetchColumn()), ['admin','administrator','accountant','cashier','finance']) || !empty($_SESSION['user_data']['is_admin']);
if (!$nqShowPrices) echo '<style>.ns-price{display:none !important;}</style>';
echo '<script>window.nqShowPrices = ' . ($nqShowPrices ? 'true' : 'false') . ';</script>';
?>
<?php require_once __DIR__ . '/includes/nurse_tabs.php'; nurse_tabs($pdo, 'portal'); ?>
<!-- Page Header Override -->
<script>document.querySelector('h2').textContent='Nurse Queue';document.querySelector('h2+p').textContent="Patient queue — call next patient, view pending and sent to consultation";</script>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px;">
  <div style="background:white;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);border-left:4px solid var(--warning-color);">
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Waiting / Pending</div>
    <div style="font-size:28px;font-weight:800;color:var(--warning-color);margin-top:4px;" id="nq-stat-pending">—</div>
  </div>
  <div style="background:white;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);border-left:4px solid var(--primary-color);">
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Sent to Consultation</div>
    <div style="font-size:28px;font-weight:800;color:var(--primary-color);margin-top:4px;" id="nq-stat-consultation">—</div>
  </div>
  <div style="background:white;border-radius:12px;padding:18px 20px;box-shadow:var(--shadow);border-left:4px solid var(--success-color);">
    <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Completed Today</div>
    <div style="font-size:28px;font-weight:800;color:var(--success-color);margin-top:4px;" id="nq-stat-completed">—</div>
  </div>
</div>

<!-- Call Next Button -->
<div style="background:white;border-radius:12px;padding:20px;box-shadow:var(--shadow);margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <div>
    <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);margin-bottom:4px;"><i class="fas fa-bullhorn" style="color:var(--primary-color);margin-right:8px;"></i>Queue Control</h3>
    <p style="font-size:13px;color:var(--gray-500);">Call the next patient in line for consultation</p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <input id="nq-search" type="text" placeholder="Search name or visit no..." oninput="nqSearchFilter()" style="padding:9px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;width:220px;">
    <button onclick="nqCallNext()" id="nq-call-btn" style="padding:14px 28px;background:var(--primary-color);color:white;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(37,99,235,0.3);transition:all .2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
      <i class="fas fa-forward"></i> Call Next Patient
    </button>
  </div>
</div>

<!-- Pending Queue Table -->
<div style="margin-bottom:24px;">
  <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:12px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-clock" style="color:var(--warning-color);"></i> Waiting Queue
    <span id="nq-pending-count" style="background:var(--warning-color);color:white;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;">0</span>
  </h3>
  <div style="background:white;border-radius:12px;box-shadow:var(--shadow);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-100);">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">#</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Patient</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Arrived At</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Department</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Actions</th>
        </tr>
      </thead>
      <tbody id="nq-pending-tbody">
        <tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Sent to Consultation Table -->
<div>
  <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:12px;display:flex;align-items:center;gap:8px;">
    <i class="fas fa-stethoscope" style="color:var(--primary-color);"></i> Sent to Consultation
    <span id="nq-consult-count" style="background:var(--primary-color);color:white;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;">0</span>
  </h3>
  <div style="background:white;border-radius:12px;box-shadow:var(--shadow);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-100);">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Patient</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Sent At</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Department</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Status</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Actions</th>
        </tr>
      </thead>
      <tbody id="nq-consult-tbody">
        <tr><td colspan="5"style="text-align:center;padding:40px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:20px;"></i></td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- My Referrals: patients this nurse sent, with progress -->
<div style="margin-top:24px;">
  <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);margin-bottom:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <i class="fas fa-share-square" style="color:var(--success-color);"></i> My Referrals
    <span id="nr-count" style="background:var(--success-color);color:white;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;">0</span>
    <span style="margin-left:auto;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--gray-600);">
      From <input type="date" id="nr-from" onchange="nrLoad()" style="padding:5px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
      To <input type="date" id="nr-to" onchange="nrLoad()" style="padding:5px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;">
    </span>
  </h3>
  <div style="background:white;border-radius:12px;box-shadow:var(--shadow);overflow:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
      <thead>
        <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-100);">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Patient</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Referred At</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Department / Doctor</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Progress</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Doctor Notes</th>
          <th style="padding:12px 16px;text-align:center;font-weight:600;color:var(--gray-600);font-size:12px;text-transform:uppercase;">Admit</th>
        </tr>
      </thead>
      <tbody id="nr-tbody"><tr><td colspan="6" style="text-align:center;padding:30px;color:var(--gray-400);">No referrals in this period</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Admit patient (same request the doctor sends from the consultation) -->
<div id="nr-admit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:16px;width:100%;max-width:520px;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;">
    <div style="background:#7c3aed;padding:16px 22px;color:white;display:flex;justify-content:space-between;align-items:center;">
      <div><div style="font-size:16px;font-weight:700;"><i class="fas fa-procedures"></i> Admit Patient</div><div id="nr-admit-sub" style="font-size:12px;opacity:.85;"></div></div>
      <button onclick="nrAdmitClose()" style="background:none;border:none;color:white;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:20px 22px;">
      <label style="font-size:12px;font-weight:500;color:var(--gray-600);">Reason for Admission <span style="color:#dc2626;">*</span></label>
      <textarea id="nr-admit-reason" rows="3" placeholder="Diagnosis / reason for admission..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;margin-bottom:12px;"></textarea>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
        <div><label style="font-size:12px;font-weight:500;color:var(--gray-600);">Admission Type</label>
          <select id="nr-admit-type" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"><option value="elective">Normal Admission</option><option value="emergency">Emergency</option><option value="transfer">Transfer</option></select></div>
        <div><label style="font-size:12px;font-weight:500;color:var(--gray-600);">Preferred Ward</label>
          <select id="nr-admit-ward" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;"><option value="">Any available</option><option value="general">General</option><option value="vip">VIP</option><option value="icu">ICU</option><option value="maternity">Maternity</option><option value="pediatric">Pediatric</option><option value="isolation">Isolation</option></select></div>
      </div>
      <button id="nr-admit-btn" onclick="nrAdmitSubmit()" style="padding:10px 20px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-paper-plane"></i> Send Admission Request</button>
    </div>
  </div>
</div>

<!-- Doctor notes (read-only) + comments -->
<div id="nr-notes-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:white;border-radius:16px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.25);">
    <div style="background:var(--primary-color);padding:16px 22px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;">
      <div>
        <div style="color:white;font-size:16px;font-weight:700;"><i class="fas fa-notes-medical" style="margin-right:8px;"></i>Doctor Notes (read-only)</div>
        <div style="color:rgba(255,255,255,.75);font-size:12px;" id="nr-notes-sub">—</div>
      </div>
      <button onclick="nrCloseNotes()" style="background:rgba(255,255,255,.2);border:none;color:white;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:16px;">×</button>
    </div>
    <div style="padding:20px 22px;">
      <div id="nr-notes-body" style="font-size:13px;color:var(--gray-800);"></div>
      <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--gray-100);">
        <div style="font-size:13px;font-weight:700;margin-bottom:8px;"><i class="fas fa-comments" style="color:var(--primary-color);"></i> Nurse / doctor comments</div>
        <div id="nr-comments" style="margin-bottom:10px;"></div>
        <textarea id="nr-comment-text" rows="2" class="ref-field" placeholder="Add a comment (the doctor's notes are not changed)"></textarea>
        <div style="text-align:right;margin-top:6px;"><button class="nr-btn" id="nr-comment-btn" onclick="nrAddComment()"><i class="fas fa-paper-plane"></i> Add comment</button></div>
      </div>
    </div>
  </div>
</div>

<!-- Record Vital Signs Modal -->
<div id="nq-vitals-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:flex-start;justify-content:center;overflow-y:auto;">
  <div style="background:white;border-radius:16px;width:100%;max-width:900px;margin:40px auto;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;">
    <!-- Modal Header -->
    <div style="background:var(--success-color);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
      <div>
        <h3 style="color:white;font-size:17px;font-weight:700;margin-bottom:2px;"><i class="fas fa-heartbeat" style="margin-right:8px;"></i>Record Vital Signs</h3>
        <div style="color:rgba(255,255,255,.75);font-size:13px;" id="nq-vitals-subtitle">—</div>
        <div style="color:white;font-size:14px;font-weight:600;margin-top:6px;" id="nq-vitals-patient">—</div>
      </div>
      <button onclick="nqCloseVitalsModal()" style="background:rgba(255,255,255,.2);border:none;color:white;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:16px;">×</button>
    </div>
    <!-- Modal Body -->
    <div style="padding:24px;">
      <div id="nq-vitals-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
      
      <!-- Vital Signs Form (dynamically loaded) -->
      <div id="nq-vitals-form" style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;">
        <p style="grid-column:1/-1;text-align:center;color:var(--gray-400);padding:20px;">Loading vital signs...</p>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);">
        <button onclick="nqCloseVitalsModal()" style="flex:1;padding:11px;background:var(--gray-100);color:var(--gray-700);border:none;border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
        <button id="nq-vitals-submit-btn" onclick="nqSubmitVitals()" style="flex:2;padding:11px;background:var(--success-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
          <i class="fas fa-check"></i> Save Vital Signs
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Send to Consultation Modal (same as patients-visit) -->
<div id="nq-consult-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9000;align-items:flex-start;justify-content:center;overflow-y:auto;padding:20px 12px;box-sizing:border-box;">
  <div style="background:white;border-radius:16px;width:100%;max-width:560px;margin:auto;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;">

    <!-- Modal Header -->
    <div style="background:var(--primary-color);padding:20px 24px;display:flex;align-items:center;justify-content:space-between;">
      <div>
        <h3 style="color:white;font-size:17px;font-weight:700;margin-bottom:2px;"><i class="fas fa-user-md" style="margin-right:8px;"></i>Send to Consultant</h3>
        <div style="color:rgba(255,255,255,.75);font-size:13px;" id="nq-modal-subtitle">—</div>
      </div>
      <button onclick="nqCloseModal()" style="background:rgba(255,255,255,.2);border:none;color:white;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:16px;">×</button>
    </div>

    <!-- Modal Body -->
    <div style="padding:24px;">
      <div id="nq-modal-alert" style="display:none;margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>

      <!-- Department -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:6px;">Department <span style="color:var(--danger-color);">*</span></label>
        <div style="position:relative;" id="nq-dept-wrapper">
          <div style="display:flex;align-items:center;border:1px solid var(--gray-200);border-radius:8px;background:white;overflow:hidden;">
            <i class="fas fa-search" style="padding:0 10px;color:var(--gray-400);font-size:13px;flex-shrink:0;"></i>
            <input id="nq-dept-search" type="text" placeholder="Search department…" autocomplete="off"
              style="flex:1;padding:10px 4px 10px 0;border:none;outline:none;font-size:14px;background:transparent;"
              oninput="nqFilterDepts()"
              onfocus="nqShowDeptDropdown()"
              onkeydown="nqDeptKeydown(event)">
            <span id="nq-dept-clear" onclick="nqClearDept()" style="display:none;padding:0 10px;cursor:pointer;color:var(--gray-400);font-size:14px;" title="Clear">×</span>
          </div>
          <input type="hidden" id="nq-dept-select" value="">
          <div id="nq-dept-dropdown" style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:200;background:white;border:1px solid var(--gray-200);border-radius:8px;max-height:200px;overflow-y:auto;box-shadow:0 4px 16px rgba(0,0,0,.12);"></div>
        </div>
      </div>

      <!-- Doctor -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:6px;">Doctor / Clinician <span style="color:var(--danger-color);">*</span></label>
        <div id="nq-doctor-wrapper" style="display:none;">
          <select id="nq-doctor-select" onchange="dutyShow('nq')" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
            <option value="">— Select Doctor / Clinician —</option>
          </select>
        </div>
        <div id="nq-doctor-duty" style="margin-top:6px;"></div>
        <div id="nq-doctor-empty" style="font-size:13px;color:var(--gray-400);padding:8px 0;">Select a department first</div>
      </div>
      <div class="ref-crit-mount" data-p="nq"></div>
      <div class="md-extra-mount" data-p="nq"></div>

      <!-- Consultation / follow-up fee: billed at reception, the nurse can change it (3/14-day rule on the server) -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:6px;">Consultation / Follow-up Fee <span style="color:var(--danger-color);">*</span></label>
        <select id="nq-cfee" disabled style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
          <option value="">— Select a department first —</option>
        </select>
        <div id="nq-cfee-note" style="display:none;"></div>
      </div>

      <!-- Chief Complaint -->
      <div id="nq-modal-complaint-row" style="display:none;background:var(--gray-50);border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:var(--gray-600);">
        <i class="fas fa-notes-medical" style="color:var(--primary-color);margin-right:6px;"></i>
        <strong>Complaint:</strong> <span id="nq-modal-complaint-text">—</span>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:10px;margin-top:8px;">
        <button onclick="nqCloseModal()" style="flex:1;padding:11px;background:var(--gray-100);color:var(--gray-700);border:none;border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
        <button id="nq-send-btn" onclick="nqSubmitConsultation()" style="flex:2;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
          <i class="fas fa-paper-plane"></i> Send to Consultant
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Success toast -->
<div id="nq-toast" style="display:none;position:fixed;bottom:28px;right:28px;background:#1e293b;color:white;padding:14px 20px;border-radius:10px;font-size:14px;font-weight:500;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.3);align-items:center;gap:10px;">
  <i class="fas fa-check-circle" style="color:var(--success-color);"></i>
  <span id="nq-toast-msg">Done!</span>
</div>

<script>
const nqAPI = window.location.origin + _pBase + '/api.php?endpoint=patients/visits';
const nqDeptAPI = window.location.origin + _pBase + '/api.php?endpoint=patients/visits&action=departments';
const nqUserAPI = window.location.origin + _pBase + '/api.php?endpoint=patients/visits&action=users_by_dept';
const nqVitalsAPI = window.location.origin + _pBase + '/api.php?endpoint=settings/vital-signs';
function nqEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

let nqCurrentVisit = null;
let nqVitalSigns = []; // cached vital sign settings
let nqAllVisits = []; // cached visits for filtering

// Client-side search filter by name or visit number
function nqSearchFilter(){
  const q = (document.getElementById('nq-search').value || '').trim().toLowerCase();
  if(!q){ nqRenderTables(nqAllVisits); return; }
  const filtered = nqAllVisits.filter(v => {
    const name = (v.patient_name || ((v.first_name||'') + ' ' + (v.last_name||''))).toLowerCase();
    const visitNo = (v.visit_no || '').toLowerCase();
    const patientNo = (v.patient_no || '').toLowerCase();
    return name.includes(q) || visitNo.includes(q) || patientNo.includes(q);
  });
  nqRenderTables(filtered);
}

// Load vital sign settings once
async function nqLoadVitalSettings(){
  if(nqVitalSigns.length) return;
  try{
    const r = await fetch(nqVitalsAPI);
    const j = await r.json();
    if(j.success) nqVitalSigns = j.data;
  }catch(e){}
}

// Open vitals modal
async function nqOpenVitalsModal(visit){
  nqCurrentVisit = visit;
  document.getElementById('nq-vitals-subtitle').textContent = visit.visit_number || visit.visit_no || visit.id;
  let age = '—';
  if(visit.date_of_birth){
    const b = new Date(visit.date_of_birth), n = new Date();
    const months = (n.getFullYear() - b.getFullYear()) * 12 + n.getMonth() - b.getMonth() - (n.getDate() < b.getDate() ? 1 : 0);
    age = months < 12 ? Math.max(months, 0) + ' month' + (months === 1 ? '' : 's') : Math.floor(months / 12) + ' yrs';
  }
  const gender = visit.gender ? visit.gender.charAt(0).toUpperCase() + visit.gender.slice(1) : '—';
  document.getElementById('nq-vitals-patient').textContent =
    (visit.patient_name || visit.first_name+' '+visit.last_name) + ' · Age: ' + age + ' · ' + gender + ' · ' + (visit.phone || '—');
  document.getElementById('nq-vitals-alert').style.display = 'none';

  await nqLoadVitalSettings();
  
  // Render form fields
  const form = document.getElementById('nq-vitals-form');
  if(!nqVitalSigns.length){
    form.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--gray-400);">No vital signs configured. Go to Settings to add them.</p>';
  } else {
    form.innerHTML = nqVitalSigns.map(vs => {
      const nameL = vs.name.toLowerCase();
      const isWeight = nameL.includes('weight') && !nameL.includes('bmi');
      const isHeight = nameL.includes('height');
      const isBMI = nameL.includes('bmi');
      const onInput = (isWeight||isHeight) ? 'oninput="nqCalcBMI()"' : '';
      const readonly = isBMI ? 'readonly style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:var(--gray-50);font-weight:600;color:var(--primary-color);"' : 'style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;"';
      return `<div>
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">
          ${nqEsc(vs.name)} ${vs.unit ? '<span style="color:var(--gray-400);font-weight:400;">(' + nqEsc(vs.unit) + ')</span>' : ''}
          ${isBMI ? '<span style="color:var(--primary-color);font-size:10px;"> (auto-calculated)</span>' : ''}
        </label>
        ${(vs.slug === 'disease_type' || /disease\s*type|desease\s*type/i.test(vs.name))
          ? `<select id="nq-vital-${vs.id}" data-id="${vs.id}" data-name="${nqEsc(vs.name)}" class="nq-vital-input" style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:8px;font-size:14px;background:white;">
              <option value="">Select...</option>
              ${['Natural disease','Occupation disease','Other','Road traffic accident','Work related accident'].map(o => `<option value="${o}">${o}</option>`).join('')}
            </select>`
          : `<input type="text" id="nq-vital-${vs.id}" data-id="${vs.id}" data-name="${nqEsc(vs.name)}" class="nq-vital-input"
          placeholder="${nqEsc(vs.name)}${vs.unit ? ' (' + nqEsc(vs.unit) + ')' : ''}"
          ${onInput} ${readonly}>`}
      </div>`;
    }).join('');
    // Pre-fill saved readings so the nurse can edit them
    try{
      const rr = await fetch(nqVitalsAPI + '&action=records&visit_id=' + visit.id);
      const rj = await rr.json();
      if(rj.success) rj.data.forEach(rec => {
        const el = document.getElementById('nq-vital-' + rec.vital_sign_id);
        if(el) el.value = rec.value;
      });
    }catch(e){}
  }

  document.getElementById('nq-vitals-modal').style.display = 'flex';
}

function nqCloseVitalsModal(){
  document.getElementById('nq-vitals-modal').style.display = 'none';
}

// Auto-calculate BMI from weight and height
function nqCalcBMI(){
  const inputs = document.querySelectorAll('.nq-vital-input');
  let weight=0, height=0, bmiInput=null;
  inputs.forEach(inp=>{
    const name=(inp.dataset.name||'').toLowerCase();
    if(name.includes('weight')&&!name.includes('bmi')) weight=parseFloat(inp.value)||0;
    if(name.includes('height')) height=parseFloat(inp.value)||0;
    if(name.includes('bmi')) bmiInput=inp;
  });
  if(bmiInput&&weight>0&&height>0){
    // Height could be in cm or m — if >3, assume cm
    const heightM=height>3?height/100:height;
    const bmi=(weight/(heightM*heightM)).toFixed(1);
    bmiInput.value=bmi;
  }
}

// Submit vitals then open consultation modal
async function nqSubmitVitals(){
  const btn = document.getElementById('nq-vitals-submit-btn');
  const al = document.getElementById('nq-vitals-alert');
  al.style.display = 'none';
  
  // Collect readings
  const inputs = document.querySelectorAll('.nq-vital-input');
  const readings = [];
  inputs.forEach(inp => {
    const val = inp.value.trim();
    if(val){
      readings.push({ vital_sign_id: parseInt(inp.dataset.id), value: val });
    }
  });
  
  if(!readings.length){
    al.textContent = 'Please record at least one vital sign';
    al.style.display = 'block';
    return;
  }
  
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  
  try{
    const r = await fetch(nqVitalsAPI + '&action=record', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        visit_id: nqCurrentVisit.id,
        patient_id: nqCurrentVisit.patient_id,
        readings: readings
      })
    });
    const j = await r.json();
    if(!j.success){ al.textContent = j.message; al.style.display = 'block'; return; }
    
    nqCloseVitalsModal();
    nqShowToast('Vital signs saved');
    nqLoad();

  }catch(e){
    al.textContent = 'Error: ' + e.message;
    al.style.display = 'block';
  }finally{
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check"></i> Save Vital Signs';
  }
}

let nqDoneToday = 0;
async function nqLoad(){
  try{
    // Every department's active queue (waiting + in consultation), regardless of reception/payment status
    const r = await fetch(nqAPI + '&action=department_queue&department_id=all');
    const j = await r.json();
    if(!j.success) return;

    nqAllVisits = [].concat(j.data.waiting || [], j.data.in_consultation || []);
    nqDoneToday = j.data.stats.done_today;
    
    // Apply search filter if active
    const q = (document.getElementById('nq-search').value || '').trim().toLowerCase();
    if(q){
      nqSearchFilter();
    } else {
      nqRenderTables(nqAllVisits);
    }
  }catch(e){ console.error('Queue load error:', e); }
}

function nqPills(v){
  let h = '';
  if(v.allergies && !/^no known allergies$/i.test(v.allergies)) h += ' <span class="allergy-pill" title="'+nqEsc(v.allergies)+'"><i class="fas fa-exclamation-triangle"></i> Allergies</span>';
  if(v.referral_priority==='critical' || v.referral_priority==='urgent') h += ' <span class="crit-badge '+v.referral_priority+'"><i class="fas fa-bolt"></i> '+(v.referral_priority==='critical'?'Critical':'Urgent')+'</span>';
  return h;
}

function nqRenderTables(visits){
    const pending = visits.filter(v => !parseInt(v.vitals_count));
    const sent = visits.filter(v => parseInt(v.vitals_count));
    const inConsultation = visits.filter(v => ['in_consultation','now_serving'].includes(v.consultation_status));

    document.getElementById('nq-stat-pending').textContent = pending.length;
    const byDept = {};
    inConsultation.forEach(v => { const d = v.department_name || 'Unassigned'; byDept[d] = (byDept[d]||0) + 1; });
    document.getElementById('nq-stat-consultation').innerHTML = inConsultation.length
      ? Object.entries(byDept).map(([d,n]) => `<div style="font-size:13px;font-weight:600;line-height:1.5;">${nqEsc(d)} : ${n}</div>`).join('')
        + `<div style="font-size:15px;font-weight:800;border-top:1px solid var(--gray-200);margin-top:4px;padding-top:2px;">Total : ${inConsultation.length}</div>`
      : '0';
    document.getElementById('nq-stat-completed').textContent = nqDoneToday;
    document.getElementById('nq-pending-count').textContent = pending.length;
    document.getElementById('nq-consult-count').textContent = sent.length;
    
    // Render pending table
    const ptb = document.getElementById('nq-pending-tbody');
    if(!pending.length){
      ptb.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:24px;display:block;margin-bottom:8px;color:var(--success-color);"></i>No patients waiting</td></tr>';
    } else {
      ptb.innerHTML = pending.map((v, i) => `
        <tr style="border-bottom:1px solid var(--gray-100);${i===0?'background:#fffbeb;':''}">
          <td style="padding:12px 16px;font-weight:700;color:var(--gray-900);">${i+1}</td>
          <td style="padding:12px 16px;">
            <div style="font-weight:600;color:var(--gray-900);">${nqEsc(v.patient_name || (v.first_name+' '+v.last_name))}${nqPills(v)}</div>
          </td>
          <td style="padding:12px 16px;font-size:13px;color:var(--gray-600);">${nqEsc(v.queued_at || v.visit_date || '—')}</td>
          <td style="padding:12px 16px;font-size:13px;">${nqEsc(v.department_name || '—')}</td>
          <td style="padding:12px 16px;text-align:center;">
            <button onclick='nqOpenVitalsModal(${JSON.stringify(v).replace(/'/g,"&#39;")})' style="padding:6px 14px;background:var(--success-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
              <i class="fas fa-heartbeat"></i> Record Vital Signs
            </button>
          </td>
        </tr>
      `).join('');
    }
    
    // Render consultation table
    const ctb = document.getElementById('nq-consult-tbody');
    if(!sent.length){
      ctb.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400);"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>No patients in consultation yet</td></tr>';
    } else {
      ctb.innerHTML = sent.map(v => {
        const inC = ['in_consultation','now_serving'].includes(v.consultation_status);
        return `
        <tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:12px 16px;font-weight:600;color:var(--gray-900);">${nqEsc(v.patient_name || (v.first_name+' '+v.last_name))}${nqPills(v)}</td>
          <td style="padding:12px 16px;font-size:13px;color:var(--gray-600);">${nqEsc(v.queued_at || v.visit_date || '—')}</td>
          <td style="padding:12px 16px;font-size:13px;">${nqEsc(v.department_name || '—')}</td>
          <td style="padding:12px 16px;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:${inC?'#fef3c7':'#dbeafe'};color:${inC?'#92400e':'#1d4ed8'};">${inC?'In Consultation':'Waiting'}</span></td>
          <td style="padding:12px 16px;text-align:center;">
            <button onclick='nqOpenVitalsModal(${JSON.stringify(v).replace(/'/g,"&#39;")})' style="padding:6px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
              <i class="fas fa-edit"></i> Edit
            </button>${v.status==='open'||v.visit_status==='open' ? `
            <button onclick='nqOpenConsultModal(Object.assign(${JSON.stringify(v).replace(/'/g,"&#39;")},{status:"open"}))' class="nr-btn" style="margin-left:6px;background:var(--success-color);">
              <i class="fas fa-user-md"></i> Send to Consultant
            </button>` : ''}
          </td>
        </tr>`;
      }).join('');
    }
}

// Call Next — opens the modal for the first pending patient
async function nqCallNext(){
  const btn = document.getElementById('nq-call-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
  try{
    await nqLoad();
    const next = nqAllVisits.find(v => !parseInt(v.vitals_count));
    if(!next){ alert('No patients waiting in queue.'); return; }
    nqOpenVitalsModal(next);
  }catch(e){ alert('Error: ' + e.message); }
  finally{ btn.disabled = false; btn.innerHTML = '<i class="fas fa-forward"></i> Call Next Patient'; }
}

// ---- Open consultation modal ----
function nqOpenConsultModal(visit){
  if(visit.status !== 'open'){
    nqShowToast('This visit has already been sent to consultation'); return;
  }
  nqCurrentVisit = visit;
  refCritReset('nq'); refCritCtx.nq = visit; mdReset('nq');
  document.getElementById('nq-modal-subtitle').textContent =
    (visit.patient_name || visit.first_name+' '+visit.last_name) + ' · ' + (visit.visit_number || visit.visit_no || visit.id);
  document.getElementById('nq-modal-alert').style.display = 'none';

  const complaintRow = document.getElementById('nq-modal-complaint-row');
  if(visit.chief_complaint){
    document.getElementById('nq-modal-complaint-text').textContent = visit.chief_complaint;
    complaintRow.style.display = 'block';
  } else { complaintRow.style.display = 'none'; }

  // Load departments
  nqLoadDepts().then(()=>{
    if(visit.department_id){
      // Pre-select dept from visit
      const dept = (window._nqDepts||[]).find(d=>d.id==visit.department_id);
      if(dept) nqSelectDept(dept.id, dept.name);
      else { document.getElementById('nq-dept-select').value=visit.department_id; nqDeptChanged(); }
    } else {
      // Default to General Medicine
      const gm = (window._nqDepts||[]).find(d=>d.name==='General Medicine');
      if(gm) nqSelectDept(gm.id, gm.name);
      else nqDeptChanged();
    }
  });

  document.getElementById('nq-consult-modal').style.display = 'flex';
}

async function nqLoadDepts(){
  // Guard: already loaded
  if(window._nqDepts && window._nqDepts.length) return;
  try{
    const r = await fetch(nqDeptAPI);
    const j = await r.json();
    if(j.success){
      const depts = j.data || [];
      depts.sort((a,b)=>{
        if(a.name==='General Medicine') return -1;
        if(b.name==='General Medicine') return 1;
        return a.name.localeCompare(b.name);
      });
      window._nqDepts = depts;
    }
  }catch(e){ window._nqDepts = []; }
}

// Searchable dept widget helpers
function nqRenderDeptDropdown(filter){
  const dd   = document.getElementById('nq-dept-dropdown');
  const list = (window._nqDepts||[]).filter(d=>!filter||d.name.toLowerCase().includes(filter.toLowerCase()));
  if(!list.length){
    dd.innerHTML='<div style="padding:10px 14px;font-size:13px;color:var(--gray-400);">No departments found</div>';
    return;
  }
  const cur = document.getElementById('nq-dept-select').value;
  dd.innerHTML = list.map(d=>`
    <div data-id="${d.id}" data-name="${nqEsc(d.name)}"
      onclick="nqSelectDept('${d.id}','${nqEsc(d.name)}')"
      style="padding:9px 14px;font-size:14px;cursor:pointer;border-bottom:1px solid var(--gray-100);background:${cur==d.id?'#eff6ff':'white'};"
      onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='${cur==d.id?'#eff6ff':'white'}'">
      ${nqEsc(d.name)}
    </div>`).join('');
}

function nqShowDeptDropdown(){
  const dd=document.getElementById('nq-dept-dropdown');
  nqRenderDeptDropdown(document.getElementById('nq-dept-search').value);
  dd.style.display='block';
}

function nqFilterDepts(){
  const dd=document.getElementById('nq-dept-dropdown');
  dd.style.display='block';
  nqRenderDeptDropdown(document.getElementById('nq-dept-search').value);
}

function nqSelectDept(id, name){
  document.getElementById('nq-dept-select').value = id;
  document.getElementById('nq-dept-search').value = name;
  document.getElementById('nq-dept-clear').style.display = 'inline';
  document.getElementById('nq-dept-dropdown').style.display = 'none';
  nqDeptChanged();
}

function nqClearDept(){
  document.getElementById('nq-dept-select').value = '';
  document.getElementById('nq-dept-search').value = '';
  document.getElementById('nq-dept-clear').style.display = 'none';
  document.getElementById('nq-dept-dropdown').style.display = 'none';
  nqDeptChanged();
}

function nqDeptKeydown(e){
  if(e.key==='Escape'){ document.getElementById('nq-dept-dropdown').style.display='none'; }
  if(e.key==='Enter'){
    const first=document.getElementById('nq-dept-dropdown').querySelector('[data-id]');
    if(first) nqSelectDept(first.dataset.id, first.dataset.name);
    e.preventDefault();
  }
}

// Close dept dropdown when clicking outside
document.addEventListener('click',function(e){
  if(!document.getElementById('nq-dept-wrapper')?.contains(e.target)){
    const dd=document.getElementById('nq-dept-dropdown');
    if(dd) dd.style.display='none';
  }
});

async function nqDeptChanged(){
  const deptId = document.getElementById('nq-dept-select').value;
  const wrapper = document.getElementById('nq-doctor-wrapper');
  const empty   = document.getElementById('nq-doctor-empty');
  const select  = document.getElementById('nq-doctor-select');
  const fee = document.getElementById('nq-cfee'), feeNote = document.getElementById('nq-cfee-note');

  if(!deptId){ wrapper.style.display='none'; empty.style.display='block'; fee.disabled=true; fee.dataset.rule=''; fee.innerHTML='<option value="">— Select a department first —</option>'; feeNote.style.display='none'; return; }
  if(nqCurrentVisit) crLoad(fee, feeNote, 'department_id='+encodeURIComponent(deptId)+'&visit_id='+nqCurrentVisit.id, window.nqShowPrices);
  empty.style.display='none'; wrapper.style.display='block';
  select.innerHTML = '<option value="">— Loading… —</option>';

  try{
    const r = await fetch(nqUserAPI+'&department_id='+deptId+'&limit=100');
    const j = await r.json();
    select.innerHTML = '<option value="">— Select Doctor / Clinician —</option>';
    if(j.success){
      const users = j.data.users || j.data || [];
      users.forEach(u=>{
        const o = document.createElement('option');
        o.value = u.id;
        o.setAttribute('data-name', u.first_name+' '+u.last_name);
        o.dataset.duty = u.on_duty ? '1' : '0';
        o.textContent = ptDocName(u)+(u.role_name?' ('+u.role_name+')':'')+' ('+(parseInt(u.queue_count)||0)+' queue) - '+(u.on_duty?'On duty':'Off duty');
        if(!u.on_duty) o.style.color = '#9ca3af';
        select.appendChild(o);
      });
      // Doctor chosen at reception: pre-select when the visit is still in that department
      if(nqCurrentVisit?.doctor_id && nqCurrentVisit.department_id == deptId) select.value = nqCurrentVisit.doctor_id;
    }
    dutyShow('nq');
  }catch(e){
    select.innerHTML = '<option value="">— could not load users —</option>';
  }
}

function nqCloseModal(){
  document.getElementById('nq-consult-modal').style.display = 'none';
  // Reset dept search widget
  document.getElementById('nq-dept-search').value = '';
  document.getElementById('nq-dept-select').value = '';
  document.getElementById('nq-dept-clear').style.display = 'none';
  document.getElementById('nq-dept-dropdown').style.display = 'none';
  // Reset doctor dropdown
  document.getElementById('nq-doctor-wrapper').style.display = 'none';
  document.getElementById('nq-doctor-empty').style.display = 'block';
  document.getElementById('nq-doctor-select').innerHTML = '<option value="">— Select Doctor / Clinician —</option>';
  window._nqDepts = null; // force reload next open
  nqCurrentVisit = null;
}
document.getElementById('nq-consult-modal').addEventListener('click', function(e){
  if(e.target === this) nqCloseModal();
});

// ---- Submit consultation + create invoice ----
async function nqSubmitConsultation(){
  const btn = document.getElementById('nq-send-btn');
  const alert = document.getElementById('nq-modal-alert');
  alert.style.display = 'none';

  const deptId = document.getElementById('nq-dept-select').value;
  const doctorSel = document.getElementById('nq-doctor-select');
  const doctorId = doctorSel.value;
  const doctorName = doctorSel.selectedOptions[0]?.getAttribute('data-name') || '';
  const feeSel = document.getElementById('nq-cfee');

  if(!deptId){ alert.textContent='Please select a department.'; alert.style.display='block'; return; }
  if(!doctorId){ alert.textContent='Please select a Doctor / Clinician.'; alert.style.display='block'; return; }
  const feeErr = crMissing(feeSel);
  if(feeErr){ alert.textContent=feeErr; alert.style.display='block'; return; }
  const ref = refCritCollect('nq');
  if(ref.error){ alert.textContent=ref.error; alert.style.display='block'; return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

  try {
    const v = nqCurrentVisit;
    const extras = mdCollect('nq', deptId); // validates the extra rows before anything is saved

    // 0. Main department fee: keeps the reception fee, or replaces it when the nurse changed department / fee (server checks the 3/14-day rule)
    let feeMsg = '';
    if(!feeSel.disabled){
      const fj = await crSave(v.id, parseInt(deptId), parseInt(feeSel.value)||null);
      if(!fj.success) throw new Error(fj.message || 'Consultation fee not saved');
      feeMsg = ' — '+fj.data.note+(fj.data.invoice_no?' ('+fj.data.invoice_no+')':'');
    }

    // 1. Extra departments (each: own assignment + own fee), so the main department is assigned last and stays the visit's department
    const extraDone = await mdSend('nq', extras, ref);
    const extraTxt = extraDone.length ? ' + '+extraDone.join(', ') : '';

    // 2. Assign dept/doctor on visit
    const aj = await crPost(nqAPI+'&id='+v.id, 'PUT', {
      action: 'assign_consultation',
      department_id: parseInt(deptId),
      doctor_id: parseInt(doctorId),
      doctor_name: doctorName || null,
      priority: ref.priority,
      critical_info: ref.critical_info || null
    });
    if(!aj.success) throw new Error(aj.message || 'Could not send the patient');

    const deptName  = document.getElementById('nq-dept-search').value || '';
    nqCloseModal();
    nqShowToast('✓ Patient sent to '+deptName+feeMsg+extraTxt);
    nqLoad();

  } catch(e){
    alert.textContent = e.message || 'Something went wrong';
    alert.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send to Consultant';
  }
}

function nqShowToast(msg){
  const t=document.getElementById('nq-toast');
  document.getElementById('nq-toast-msg').textContent=msg;
  t.style.display='flex';
  setTimeout(()=>{t.style.display='none';},4000);
}

// ---- My Referrals ----
const nrStages = [['waiting','Waiting'],['received','Received'],['attended','Attended'],['admitted','Admitted']];
let nrVisit = null, nrRows = [];
(function(){ const t = new Date().toISOString().slice(0,10); document.getElementById('nr-from').value = t; document.getElementById('nr-to').value = t; })();
async function nrLoad(){
  const from = document.getElementById('nr-from').value, to = document.getElementById('nr-to').value;
  try{
    const j = await (await fetch(nqAPI+'&action=my_referrals&date_from='+encodeURIComponent(from)+'&date_to='+encodeURIComponent(to))).json();
    if(!j.success) return;
    const rows = nrRows = j.data || [];
    document.getElementById('nr-count').textContent = rows.length;
    const tb = document.getElementById('nr-tbody');
    if(!rows.length){ tb.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--gray-400);">No referrals in this period</td></tr>'; return; }
    tb.innerHTML = rows.map(r=>{
      const cur = nrStages.findIndex(x=>x[0]===r.progress);
      const steps = nrStages.map((x,i)=>'<span class="'+(i<cur?'done':i===cur?'cur':'')+'">'+x[1]+'</span>').join('<i class="fas fa-angle-right" style="color:#d1d5db;"></i>');
      const pr = (r.priority==='critical'||r.priority==='urgent') ? ' <span class="crit-badge '+r.priority+'"><i class="fas fa-bolt"></i> '+(r.priority==='critical'?'Critical':'Urgent')+'</span>' : '';
      const ci = r.critical_info && r.critical_info.condition ? '<div style="font-size:11px;color:#991b1b;margin-top:2px;">'+nqEsc(r.critical_info.condition)+'</div>' : '';
      return '<tr style="border-bottom:1px solid var(--gray-100);">'
        +'<td style="padding:12px 16px;"><div style="font-weight:600;">'+nqEsc(r.patient_name)+pr+'</div><div style="font-size:11px;color:var(--gray-500);">'+nqEsc(r.visit_no)+ci+'</div></td>'
        +'<td style="padding:12px 16px;font-size:13px;color:var(--gray-600);">'+nqEsc(r.referred_at||'')+'</td>'
        +'<td style="padding:12px 16px;font-size:13px;">'+nqEsc(r.department_name||'—')+(r.doctor_name?'<div style="font-size:11px;color:var(--gray-500);">'+nqEsc(r.doctor_name)+'</div>':'')+'</td>'
        +'<td style="padding:12px 16px;"><div class="ref-steps">'+steps+'</div></td>'
        +'<td style="padding:12px 16px;text-align:center;"><button class="nr-btn" onclick="nrOpenNotes('+r.visit_id+')"><i class="fas fa-eye"></i> View</button></td>'
        +'<td style="padding:12px 16px;text-align:center;">'+(r.progress==='admitted'?'<span style="font-size:12px;font-weight:600;color:#166534;"><i class="fas fa-check-circle"></i> Admitted</span>':'<button class="nr-btn" style="background:#7c3aed;color:white;border-color:#7c3aed;" onclick="nrAdmitOpen('+r.visit_id+')"><i class="fas fa-procedures"></i> Admit Patient</button>')+'</td></tr>';
    }).join('');
  }catch(e){}
}
let nrAdmitVisit = null;
function nrAdmitOpen(visitId){
  const row = nrRows.find(r=>r.visit_id==visitId); if(!row) return;
  nrAdmitVisit = row;
  document.getElementById('nr-admit-sub').textContent = (row.patient_name||'')+' · '+(row.visit_no||'');
  document.getElementById('nr-admit-reason').value = '';
  document.getElementById('nr-admit-modal').style.display = 'flex';
}
function nrAdmitClose(){ document.getElementById('nr-admit-modal').style.display = 'none'; nrAdmitVisit = null; }
async function nrAdmitSubmit(){
  const row = nrAdmitVisit, reason = document.getElementById('nr-admit-reason').value.trim(), btn = document.getElementById('nr-admit-btn');
  if(!row) return;
  if(!reason) return alert('Please enter a reason for admission.');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
  try{
    const j = await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=ward/ward-management&action=request_admission', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({
      patient_id:row.patient_id, visit_id:row.visit_id, diagnosis:reason,
      admission_type:document.getElementById('nr-admit-type').value, ward_preference:document.getElementById('nr-admit-ward').value
    })})).json();
    if(j.success){ nrAdmitClose(); nqShowToast('Admission request sent. Ward staff will assign a bed.'); nrLoad(); }
    else alert(j.message || 'Could not send the admission request.');
  }catch(e){ alert('Error: '+e.message); }
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Admission Request';
}
async function nrOpenNotes(visitId){
  nrVisit = visitId;
  const row = nrRows.find(r=>r.visit_id==visitId) || {};
  document.getElementById('nr-notes-sub').textContent = (row.patient_name||'')+' · '+(row.visit_no||'');
  document.getElementById('nr-notes-body').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  document.getElementById('nr-notes-modal').style.display = 'flex';
  nrLoadComments();
  try{
    const j = await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=consultation/consult&action=consultation&visit_id='+visitId)).json();
    const list = (j.success && j.data) ? j.data : [];
    const labels = [['chief_complaint','Chief complaint'],['history','Past medical history'],['examination','Examination'],['diagnosis','Diagnosis'],['differential_diagnosis','Differential diagnosis'],['treatment_plan','Treatment plan'],['notes','Notes'],['outcome','Outcome'],['follow_up_date','Follow-up date']];
    document.getElementById('nr-notes-body').innerHTML = list.length ? list.map(c=>{
      const f = labels.filter(l=>c[l[0]]).map(l=>'<div style="margin-bottom:8px;"><div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;">'+l[1]+'</div><div style="white-space:pre-wrap;">'+nqEsc(c[l[0]])+'</div></div>').join('');
      return '<div style="margin-bottom:14px;padding:12px;background:var(--gray-50);border-radius:8px;"><div style="font-size:12px;color:var(--gray-500);margin-bottom:8px;">'+nqEsc(c.department_name||'')+(c.doctor_name?' · Dr. '+nqEsc(c.doctor_name):'')+' · '+nqEsc(c.status||'')+'</div>'+(f||'<div style="color:var(--gray-400);">No notes written yet.</div>')+'</div>';
    }).join('') : '<div style="color:var(--gray-400);">The doctor has not started notes for this visit yet.</div>';
  }catch(e){ document.getElementById('nr-notes-body').textContent = 'Could not load notes.'; }
}
function nrCloseNotes(){ document.getElementById('nr-notes-modal').style.display = 'none'; nrVisit = null; }
document.getElementById('nr-notes-modal').addEventListener('click', function(e){ if(e.target === this) nrCloseNotes(); });
document.getElementById('nr-admit-modal').addEventListener('click', function(e){ if(e.target === this) nrAdmitClose(); });
async function nrLoadComments(){
  const box = document.getElementById('nr-comments');
  try{
    const j = await (await fetch(nqAPI+'&action=note_comments&visit_id='+nrVisit)).json();
    const list = j.success ? j.data : [];
    box.innerHTML = list.length ? list.map(c=>'<div style="padding:8px 10px;background:#eff6ff;border-radius:8px;margin-bottom:6px;font-size:13px;"><div style="font-size:11px;color:var(--gray-500);">'+nqEsc(c.author||'')+' · '+nqEsc(c.created_at)+'</div>'+nqEsc(c.comment)+'</div>').join('') : '<div style="font-size:12px;color:var(--gray-400);">No comments yet.</div>';
  }catch(e){}
}
async function nrAddComment(){
  const t = document.getElementById('nr-comment-text'), b = document.getElementById('nr-comment-btn');
  if(!t.value.trim()) return;
  b.disabled = true;
  try{
    const j = await (await fetch(nqAPI+'&action=add_note_comment', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({visit_id:nrVisit, comment:t.value.trim()})})).json();
    if(j.success){ t.value=''; nrLoadComments(); } else alert(j.message||'Failed');
  }catch(e){ alert('Failed: '+e.message); }
  b.disabled = false;
}

// Auto-refresh every 15 seconds
nqLoad();
nrLoad();
setInterval(nqLoad, 15000);
setInterval(nrLoad, 15000);
</script>
<?php endif; ?>

<script>
// On/off duty badge under a doctor select (prefix pv|nq)
function dutyShow(p){
  const sel = document.getElementById(p+'-doctor-select'), box = document.getElementById(p+'-doctor-duty');
  if(!sel || !box) return;
  const o = sel.selectedOptions[0];
  if(!o || !o.value || o.dataset.duty === undefined){ box.innerHTML = ''; return; }
  box.innerHTML = o.dataset.duty === '1'
    ? '<span class="duty-badge duty-on"><i class="fas fa-circle" style="font-size:7px;"></i> On duty</span>'
    : '<span class="duty-badge duty-off"><i class="fas fa-circle" style="font-size:7px;"></i> Off duty</span>';
}
// Critical / urgent referral block inside the Send-to-Consultant modals (prefix pv|nq)
const refCritCtx = {};
function refCritHtml(p){
  return `<div style="margin-bottom:16px;">
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#991b1b;cursor:pointer;"><input type="checkbox" id="${p}-crit-on" onchange="refCritToggle('${p}')"> <i class="fas fa-bolt"></i> Critical / urgent referral</label>
    <div id="${p}-crit-box" style="display:none;margin-top:8px;padding:12px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;">
      <label class="ref-lbl" style="margin-top:0;">Priority</label>
      <select id="${p}-crit-priority" class="ref-field"><option value="urgent">Urgent</option><option value="critical">Critical</option></select>
      <label class="ref-lbl">Condition *</label><input id="${p}-crit-condition" class="ref-field" type="text" placeholder="e.g. Severe chest pain, suspected MI">
      <label class="ref-lbl">Vitals snapshot (auto-filled from latest vitals)</label><textarea id="${p}-crit-vitals" rows="2" class="ref-field"></textarea>
      <label class="ref-lbl">Interventions done</label><textarea id="${p}-crit-interventions" rows="2" class="ref-field" placeholder="e.g. Oxygen 4L, IV line, aspirin given"></textarea>
      <label class="ref-lbl">Onset</label><input id="${p}-crit-onset" class="ref-field" type="text" placeholder="e.g. 2 hours ago, sudden">
      <label class="ref-lbl">Reason for referral</label><input id="${p}-crit-reason" class="ref-field" type="text">
    </div></div>`;
}
document.querySelectorAll('.ref-crit-mount').forEach(m=>{ m.innerHTML = refCritHtml(m.dataset.p); });
async function refCritToggle(p){
  const on = document.getElementById(p+'-crit-on').checked;
  document.getElementById(p+'-crit-box').style.display = on ? 'block' : 'none';
  const v = refCritCtx[p], t = document.getElementById(p+'-crit-vitals');
  if(!on || !v || t.value) return;
  try{
    const j = await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=settings/vital-signs&action=records&visit_id='+v.id)).json();
    const seen = {}, parts = [];
    (j.data||[]).forEach(r=>{ if(seen[r.sign_name]) return; seen[r.sign_name] = 1; parts.push(r.sign_name+' '+r.value+(r.unit?' '+r.unit:'')); });
    if(!t.value) t.value = parts.join(', ');
  }catch(e){}
}
function refCritReset(p){
  const on = document.getElementById(p+'-crit-on'); if(!on) return;
  on.checked = false; document.getElementById(p+'-crit-box').style.display = 'none';
  ['condition','vitals','interventions','onset','reason'].forEach(f=>{ document.getElementById(p+'-crit-'+f).value = ''; });
}
function refCritCollect(p){
  const on = document.getElementById(p+'-crit-on');
  if(!on || !on.checked) return {priority:'routine'};
  const g = f => document.getElementById(p+'-crit-'+f).value.trim();
  if(!g('condition')) return {error:'Describe the patient condition for a critical / urgent referral.'};
  return {priority:document.getElementById(p+'-crit-priority').value, critical_info:{condition:g('condition'), vitals:g('vitals'), interventions:g('interventions'), onset:g('onset'), reason:g('reason')}};
}
// Extra departments in the Send-to-Consultant modals (prefix pv|nq): the visit's own department stays the main one;
// every extra gets its own department assignment AND its own consultation invoice (same assign_consultation + billing create calls as the main one)
const mdCache = {};
document.querySelectorAll('.md-extra-mount').forEach(m=>{
  const p = m.dataset.p;
  m.innerHTML = `<div style="margin-bottom:16px;"><div id="${p}-md-rows"></div>
    <button type="button" onclick="mdAddRow('${p}')" style="width:100%;padding:8px;background:var(--gray-50);border:1px dashed var(--gray-300,#d1d5db);border-radius:8px;cursor:pointer;font-size:12px;color:var(--gray-600);"><i class="fas fa-plus"></i> Also send to another department (separate invoice)</button></div>`;
});
function mdReset(p){ const r = document.getElementById(p+'-md-rows'); if(r) r.innerHTML = ''; delete mdCache[p]; }
async function mdData(p){
  if(mdCache[p]) return mdCache[p];
  const base = window.location.origin + _pBase + '/api.php?endpoint=';
  const d = {depts:[]};
  try{ const j = await (await fetch(base+'patients/visits&action=departments')).json(); if(j.success) d.depts = j.data||[]; }catch(e){}
  return mdCache[p] = d;
}
async function mdAddRow(p){
  const d = await mdData(p), row = document.createElement('div');
  row.className = 'md-row';
  row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;margin-bottom:8px;';
  const st = 'padding:9px 10px;border:1px solid var(--gray-200);border-radius:8px;font-size:12px;background:white;width:100%;';
  const esc = s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  row.innerHTML = `<select class="md-dept" onchange="mdDoctors(this.parentNode)" style="${st}"><option value="">— Department —</option>${d.depts.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join('')}</select>
    <select class="md-doc" style="${st}"><option value="">— Doctor (optional) —</option></select>
    <select class="md-svc" disabled style="${st}"><option value="">— Consultation fee —</option></select>
    <button type="button" title="Remove" onclick="this.parentNode.remove()" style="border:none;background:var(--gray-100);border-radius:8px;width:34px;cursor:pointer;color:#dc2626;"><i class="fas fa-times"></i></button>
    <div class="md-note" style="display:none;"></div>`;
  row.dataset.p = p;
  document.getElementById(p+'-md-rows').appendChild(row);
}
async function mdDoctors(row){
  const dept = row.querySelector('.md-dept').value, doc = row.querySelector('.md-doc'), v = refCritCtx[row.dataset.p];
  doc.innerHTML = '<option value="">— Doctor (optional) —</option>';
  const svc = row.querySelector('.md-svc'), note = row.querySelector('.md-note');
  if(!dept){ svc.disabled = true; svc.dataset.rule = ''; svc.innerHTML = '<option value="">— Consultation fee —</option>'; note.style.display = 'none'; return; }
  crLoad(svc, note, 'department_id='+encodeURIComponent(dept)+'&visit_id='+v.id, row.dataset.p !== 'nq' || window.nqShowPrices).then(()=>{ note.style.gridColumn = '1/-1'; });
  try{
    const j = await (await fetch(window.location.origin+_pBase+'/api.php?endpoint=patients/visits&action=users_by_dept&department_id='+encodeURIComponent(dept))).json();
    (j.data||[]).forEach(u=>{ const o = document.createElement('option'); o.value = u.id; o.dataset.name = u.first_name+' '+u.last_name; o.textContent = ptDocName(u)+(u.on_duty?'':' (off duty)'); doc.appendChild(o); });
  }catch(e){}
}
// Rows -> [{department_id, department_name, doctor_id, doctor_name, service_id, price}]; throws Error with a user message
function mdCollect(p, mainDeptId){
  const seen = new Set([String(mainDeptId)]);
  return [...document.querySelectorAll('#'+p+'-md-rows .md-row')].map(row=>{
    const dsel = row.querySelector('.md-dept'), dept = dsel.value, doc = row.querySelector('.md-doc'), svc = row.querySelector('.md-svc');
    if(!dept) throw new Error('Select a department on every extra row (or remove the empty row).');
    const name = dsel.selectedOptions[0].text;
    if(seen.has(dept)) throw new Error('The patient can only be sent once to '+name+'.');
    seen.add(dept);
    const miss = crMissing(svc);
    if(miss) throw new Error(name+': '+miss);
    return {department_id:parseInt(dept), department_name:name, doctor_id:doc.value?parseInt(doc.value):null, doctor_name:doc.selectedOptions[0]?.dataset.name||null,
            service_id:parseInt(svc.value)||null, fee_locked:svc.disabled};
  });
}
// Send the visit to each extra department and bill that department's consultation on its own invoice. Returns a summary per department.
async function mdSend(p, extras, ref){
  const v = refCritCtx[p], base = window.location.origin + _pBase + '/api.php?endpoint=patients/';
  const done = [];
  for(const x of extras){
    const fee = x.fee_locked ? null : await crSave(v.id, x.department_id, x.service_id);
    if(fee && !fee.success) throw new Error(x.department_name+': '+(fee.message||'fee not saved')+(done.length?' (already done: '+done.join(', ')+')':''));
    const a = await crPost(base+'visits&id='+v.id, 'PUT', {action:'assign_consultation', department_id:x.department_id, doctor_id:x.doctor_id, doctor_name:x.doctor_name, priority:ref.priority, critical_info:ref.critical_info||null});
    if(!a.success) throw new Error(x.department_name+': '+(a.message||'could not send')+(done.length?' (already done: '+done.join(', ')+')':''));
    done.push(x.department_name+(fee && fee.data.invoice_no ? ' '+fee.data.invoice_no : ''));
  }
  return done;
}
// Consultation fee of one department of the visit, priced and billed on the server (3/14-day rule, insurance, unpaid fee replaced)
function crPost(url, method, body){ return fetch(url, {method, headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)}).then(r=>r.json()); }
function crSave(visitId, deptId, serviceId){
  return crPost(window.location.origin+_pBase+'/api.php?endpoint=patients/visits&id='+visitId, 'PUT', {action:'set_consultation_fee', department_id:deptId, service_id:serviceId||null});
}
</script>
<script>
(function(){
  const NCD=['Hypertension','Diabetes','Asthma/COPD','Heart disease','CKD','Cancer','Sickle cell','Epilepsy','Mental health'];
  const list=v=>v.split(',').map(x=>x.trim()).filter(Boolean);
  const NONE=/^no known allergies$/i;
  window.medChipsRefresh=function(){
    document.querySelectorAll('.med-chips').forEach(box=>{
      const cur=list(document.getElementById(box.dataset.for).value).map(x=>x.toLowerCase());
      box.querySelectorAll('button').forEach(b=>b.classList.toggle('on',cur.includes(b.textContent.toLowerCase())));
    });
  };
  window.medNoAllergies=function(cb,id){
    const i=document.getElementById(id);
    if(cb.checked) i.value='No known allergies'; else if(NONE.test(i.value)) i.value='';
  };
  document.querySelectorAll('.med-chips').forEach(box=>{
    const inp=document.getElementById(box.dataset.for);
    NCD.forEach(n=>{
      const b=document.createElement('button'); b.type='button'; b.textContent=n;
      b.onclick=()=>{ const a=list(inp.value), k=a.findIndex(x=>x.toLowerCase()===n.toLowerCase()); k>=0?a.splice(k,1):a.push(n); inp.value=a.join(', '); medChipsRefresh(); };
      box.appendChild(b);
    });
    inp.addEventListener('input',medChipsRefresh);
  });
  ['reg-allergies','edit-allergies'].forEach(id=>{
    const i=document.getElementById(id); if(!i) return;
    i.addEventListener('input',()=>{ const c=i.parentNode.querySelector('.med-chip-none input'); if(c) c.checked=NONE.test(i.value.trim()); });
  });
})();
</script>
<!-- Notification System Script -->
<script>
(function(){
const notifAPI = window.location.origin + _pBase + '/api.php?endpoint=notifications';
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
  if(!muted) notifStopReminder(); // stop reminder when muting
};
document.addEventListener('DOMContentLoaded', notifUpdateMuteBtn);

const notifAudioCtx = new (window.AudioContext || window.webkitAudioContext)();

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
    const r = await fetch(notifAPI + '&target_role=nurse&limit=30');
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
      return `<div class="notif-item ${isUnread ? 'unread' : ''}" id="notif-item-${n.id}">
        <div class="notif-title"><i class="fas fa-flask" style="color:#2563eb;margin-right:6px;"></i>${n.title || 'Notification'}</div>
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
    await fetch(notifAPI, { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'mark_all_read', target_role:'nurse'}) });
    notifLastUnread = 0; notifSoundPlayed = false; notifStopReminder();
    notifLoadList();
  }catch(e){}
};

async function notifPoll(){
  try{
    const r = await fetch(notifAPI + '&target_role=nurse&is_read=0&limit=1');
    const j = await r.json();
    if(j.success){
      const unread = j.data.unread_count || 0;
      const badge = document.getElementById('notif-count');
      if(unread > 0){
        badge.textContent = unread > 99 ? '99+' : unread;
        badge.style.display = 'flex';
        if(unread > notifLastUnread || (!notifSoundPlayed && unread > 0)){
          notifPlayAlertLong();
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

<?php
require_once __DIR__ . '/includes/footer.php';
?>
