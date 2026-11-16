<?php
// department.php - Front-end page (like patients.php pattern)
require_once __DIR__ . '/includes/signature.php';
require_once __DIR__ . '/includes/header.php';
$departmentSignature = get_user_signature($pdo, (int)($_SESSION['user_id'] ?? 0));
$departmentStamp = get_hospital_stamp($pdo);

// Check if user is an administrator (can see all departments and restricted sections)
$isAdminUser = false;
if (isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['user_data']['is_admin'])) {
        $isAdminUser = true;
    } elseif (isset($_SESSION['user_data']['role']) && in_array(strtolower($_SESSION['user_data']['role']), ['admin', 'administrator'])) {
        $isAdminUser = true;
    } elseif (isset($_SESSION['user_data']['role_slugs']) && (in_array('admin', $_SESSION['user_data']['role_slugs']) || in_array('administrator', $_SESSION['user_data']['role_slugs']))) {
        $isAdminUser = true;
    } elseif (isset($_SESSION['user_role']) && in_array(strtolower($_SESSION['user_role']), ['admin', 'administrator'])) {
        $isAdminUser = true;
    } elseif (function_exists('userHasRole') && (userHasRole('admin') || userHasRole('administrator'))) {
        $isAdminUser = true;
    } else {
        require_once __DIR__ . '/includes/database.php';
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND (r.slug IN ('admin', 'administrator') OR LOWER(r.name) IN ('admin', 'administrator'))
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $isAdminUser = (int)$stmt->fetchColumn() > 0;
    }
}
?>

<!-- Page Header -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;">
    <div>
        <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">
            <i class="fas fa-building" style="color:var(--primary-color);"></i>
            <span id="dept-page-title">My Department</span>
        </h2>
        <p id="dept-page-subtitle" style="color:var(--gray-500);font-size:14px;margin-top:4px;">
            Loading...
        </p>
    </div>
</div>

<!-- QUEUE STATS -->
<div id="dq-main-content">
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;">
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="dq-waiting" style="font-size:28px;font-weight:700;color:var(--warning-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Waiting</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="dq-consulting" style="font-size:28px;font-weight:700;color:var(--primary-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">In Consultation</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;">
    <div id="dq-done" style="font-size:28px;font-weight:700;color:var(--success-color);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Completed Today</div>
  </div>
  <div style="padding:16px;background:white;border-radius:8px;box-shadow:var(--shadow);text-align:center;border-left:4px solid var(--primary-color);">
    <div id="dq-current" style="font-size:28px;font-weight:700;color:var(--gray-900);">—</div>
    <div style="font-size:13px;color:var(--gray-500);">Now Serving</div>
  </div>
</div>

<!-- MAIN TWO-COLUMN LAYOUT -->
<div style="display:grid;grid-template-columns:1fr;gap:24px;">

  <!-- ===== LEFT COLUMN: Queue + Actions ===== -->
  <div>
    <!-- Tabs -->
    <div style="display:flex;gap:0;margin-bottom:0;">
      <button id="dq-tab-waiting" onclick="dqSwitchTab('waiting')" style="flex:1;padding:14px 20px;background:var(--primary-color);color:white;border:none;border-radius:12px 12px 0 0;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fas fa-clock"></i> Waiting <span id="dq-tab-waiting-count" style="background:rgba(255,255,255,.3);padding:2px 8px;border-radius:12px;font-size:12px;">0</span>
      </button>
      <button id="dq-tab-consulting" onclick="dqSwitchTab('consulting')" style="flex:1;padding:14px 20px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:12px 12px 0 0;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fas fa-stethoscope"></i> In Consultation <span id="dq-tab-consult-count" style="background:var(--gray-200);padding:2px 8px;border-radius:12px;font-size:12px;">0</span>
      </button>
      <button id="dq-tab-labpending" onclick="dqSwitchTab('labpending')" style="flex:1;padding:14px 20px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:12px 12px 0 0;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fas fa-hourglass-half"></i> Result Pending <span id="dq-tab-labpending-count" style="background:#d97706;color:white;padding:2px 8px;border-radius:12px;font-size:12px;">0</span>
      </button>
      <button id="dq-tab-completed" onclick="dqSwitchTab('completed')" style="flex:1;padding:14px 20px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:12px 12px 0 0;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fas fa-check-circle"></i> Completed <span id="dq-tab-completed-count" style="background:var(--gray-200);padding:2px 8px;border-radius:12px;font-size:12px;">0</span>
      </button>
      <button id="dq-tab-referrals" onclick="dqSwitchTab('referrals')" style="flex:1;padding:14px 20px;background:var(--gray-100);color:var(--gray-600);border:none;border-radius:12px 12px 0 0;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fas fa-share"></i> My Referrals <span id="dq-tab-referrals-count" style="background:var(--gray-200);padding:2px 8px;border-radius:12px;font-size:12px;">0</span>
      </button>
    </div>

    <!-- Waiting Tab Content -->
    <div id="dq-panel-waiting" style="background:white;border-radius:0 0 var(--radius) var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
          <i class="fas fa-list-ol" style="color:var(--warning-color);"></i> Waiting Queue
        </h3>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="date" id="dq-waiting-date-from" onchange="dqLoad()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="From date">
          <span style="font-size:12px;color:var(--gray-400);">to</span>
          <input type="date" id="dq-waiting-date-to" onchange="dqLoad()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="To date">
          <select title="Department" id="dq-dept-filter" onchange="dqFilterTab('waiting')" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;max-width:180px;"><option value="">All</option></select>
          <button onclick="dqExcel('waiting')" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-file-excel"></i> Excel</button>
          <input type="text" id="dq-search" placeholder="Search patient, visit, insurance..." oninput="dqFilterTab('waiting')" style="padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:200px;">
          <button onclick="dqCallNext()" id="dqCallNextBtn" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
            <i class="fas fa-forward"></i> Call Next
          </button>
          <button onclick="document.getElementById('dq-waiting-date-from').value='';document.getElementById('dq-waiting-date-to').value='';document.getElementById('dq-search').value='';dqLoad()" style="padding:8px 14px;background:var(--gray-50);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;" title="Clear filter & Refresh">
            <i class="fas fa-sync-alt"></i>
          </button>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
          <thead>
            <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">#</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Patient</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Visit</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Department</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
              <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Action</th>
            </tr>
          </thead>
          <tbody id="dq-tbody">
            <tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- In Consultation Tab Content -->
    <div id="dq-panel-consulting" style="display:none;background:white;border-radius:0 0 var(--radius) var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
          <i class="fas fa-stethoscope" style="color:var(--primary-color);"></i> Currently In Consultation
        </h3>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="date" id="dq-consult-date-from" onchange="dqLoad()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="From date">
          <span style="font-size:12px;color:var(--gray-400);">to</span>
          <input type="date" id="dq-consult-date-to" onchange="dqLoad()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="To date">
          <select title="Department" id="dq-consult-dept-filter" onchange="dqFilterTab('consulting')" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;max-width:180px;"><option value="">All</option></select>
          <button onclick="dqExcel('consulting')" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-file-excel"></i> Excel</button>
          <input type="text" id="dq-consult-search" placeholder="Search patient, doctor, visit..." oninput="dqFilterTab('consulting')" style="padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:200px;">
          <button onclick="document.getElementById('dq-consult-date-from').value='';document.getElementById('dq-consult-date-to').value='';document.getElementById('dq-consult-search').value='';dqLoad()" style="padding:8px 14px;background:var(--gray-50);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;" title="Clear filter & Refresh">
            <i class="fas fa-sync-alt"></i>
          </button>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
          <thead>
            <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">#</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Patient</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Visit</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Doctor</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Department</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Started</th>
              <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Action</th>
            </tr>
          </thead>
          <tbody id="dq-consult-tbody">
            <tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Result Pending Tab Content -->
    <div id="dq-panel-labpending" style="display:none;background:white;border-radius:0 0 var(--radius) var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
          <i class="fas fa-hourglass-half" style="color:#d97706;"></i> Patients With Lab Results Pending
        </h3>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="text" id="dq-labpending-search" placeholder="Search patient, visit, test..." oninput="dqFilterTab('labpending')" style="padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:220px;">
          <button onclick="dqLoadLabPending()" style="padding:8px 14px;background:var(--gray-50);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:8px;cursor:pointer;" title="Refresh"><i class="fas fa-sync-alt"></i></button>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
          <thead>
            <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">#</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Patient</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Visit</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Pending Tests</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Department</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--gray-700);">Ordered</th>
              <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--gray-700);">Action</th>
            </tr>
          </thead>
          <tbody id="dq-labpending-tbody"></tbody>
        </table>
      </div>
    </div>

    <!-- Completed Tab Content -->
    <div id="dq-panel-completed" style="display:none;background:white;border-radius:0 0 var(--radius) var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
          <i class="fas fa-check-circle" style="color:var(--success-color);"></i> Completed Consultations
        </h3>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="date" id="dq-completed-date-from" onchange="dqLoadCompleted()" style="padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" title="From date">
          <span style="font-size:12px;color:var(--gray-400);">to</span>
          <input type="date" id="dq-completed-date-to" onchange="dqLoadCompleted()" style="padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;" title="To date">
          <select title="Department" id="dq-completed-dept-filter" onchange="dqFilterTab('completed')" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;max-width:180px;"><option value="">All</option></select>
          <button onclick="dqExcel('completed')" style="padding:8px 14px;background:#16a34a;color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-file-excel"></i> Excel</button>
          <input type="text" id="dq-completed-search" placeholder="Search patient, doctor, diagnosis..." oninput="dqFilterTab('completed')" style="padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:220px;">
          <button onclick="document.getElementById('dq-completed-date-from').value='';document.getElementById('dq-completed-date-to').value='';document.getElementById('dq-completed-search').value='';dqLoadCompleted()" style="padding:7px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;" title="Clear filter">All</button>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
          <thead>
            <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
              <th style="padding:10px 12px;text-align:left;font-weight:600;">#</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Patient</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Visit No.</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Doctor</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Diagnosis</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Department</th>
              <th style="padding:10px 12px;text-align:center;font-weight:600;">Action</th>
            </tr>
          </thead>
          <tbody id="dq-completed-tbody">
            <tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Referrals Tab Content -->
    <div id="dq-panel-referrals" style="display:none;background:white;border-radius:0 0 var(--radius) var(--radius);box-shadow:var(--shadow);overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
          <i class="fas fa-share" style="color:#7c3aed;"></i> Patients I Referred
        </h3>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="date" id="dq-referrals-date-from" onchange="dqLoadReferrals()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="From date">
          <span style="font-size:12px;color:var(--gray-400);">to</span>
          <input type="date" id="dq-referrals-date-to" onchange="dqLoadReferrals()" style="padding:7px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;" title="To date">
          <input type="text" id="dq-referrals-search" placeholder="Search patient, dept, reason..." oninput="dqFilterTab('referrals')" style="padding:7px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;width:210px;">
          <button onclick="document.getElementById('dq-referrals-date-from').value='';document.getElementById('dq-referrals-date-to').value='';document.getElementById('dq-referrals-search').value='';dqLoadReferrals()" style="padding:7px 12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;" title="Clear filter & Refresh"><i class="fas fa-sync-alt"></i></button>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
          <thead>
            <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
              <th style="padding:10px 12px;text-align:left;font-weight:600;">#</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Patient</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Visit No.</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Referred To</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Diagnosis</th>
              <th style="padding:10px 12px;text-align:left;font-weight:600;">Date</th>
              <th style="padding:10px 12px;text-align:center;font-weight:600;">Status</th>
            </tr>
          </thead>
          <tbody id="dq-referrals-tbody">
            <tr><td colspan="6" style="padding:48px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Quick Actions (only when patient selected) -->
    <div id="dq-actions" style="display:none;margin-top:16px;">
      <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
        <div style="padding:14px 20px;border-bottom:1px solid var(--gray-200);">
          <h3 style="font-size:15px;font-weight:600;color:var(--gray-900);">
            <i class="fas fa-clipboard-check" style="color:var(--primary-color);"></i> Orders & Notes
          </h3>
          <p id="dq-action-patient" style="font-size:13px;color:var(--gray-500);margin-top:2px;">No patient selected</p>
        </div>
        <div style="padding:16px 20px;">
          <div id="dq-order-list" style="font-size:13px;color:var(--gray-500);">Select a patient to see orders.</div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ============================================
MODALS
============================================ -->

<!-- Order Menu Modal -->
<div id="modal-orderMenu" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:400px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);"><i class="fas fa-file-medical" style="color:var(--primary-color);"></i> Electronic Orders</h3>
      <button onclick="hideModal('orderMenu')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:16px 20px;">
      <p style="font-size:12px;color:var(--gray-500);margin-bottom:12px;" id="om-patient-label">Select an order type</p>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <button onclick="hideModal('orderMenu');rxOpenModal();" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-prescription" style="font-size:18px;color:#16a34a;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#166534;">Drug Prescription</div><div style="font-size:11px;color:var(--gray-500);">Prescribe medications</div></div>
        </button>
        <button onclick="hideModal('orderMenu');showLabModal('lab');" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-flask" style="font-size:18px;color:#2563eb;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#1e40af;">Lab Test</div><div style="font-size:11px;color:var(--gray-500);">Order laboratory investigation</div></div>
        </button>
        <button onclick="hideModal('orderMenu');showLabModal('radiology');" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-x-ray" style="font-size:18px;color:#d97706;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#92400e;">Radiology / Imaging</div><div style="font-size:11px;color:var(--gray-500);">X-Ray, Ultrasound, CT, MRI</div></div>
        </button>
        <button onclick="hideModal('orderMenu');showModal('referral');" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-share" style="font-size:18px;color:#dc2626;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#991b1b;">Referral</div><div style="font-size:11px;color:var(--gray-500);">Refer to another department</div></div>
        </button>
        <button onclick="hideModal('orderMenu');document.getElementById('admit-patient-info').textContent=dqCurrentPatient?dqCurrentPatient.patientName+' ('+dqCurrentPatient.visitNo+')':'';showModal('admission');" style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:8px;cursor:pointer;text-align:left;width:100%;">
          <i class="fas fa-procedures" style="font-size:18px;color:#7c3aed;width:24px;text-align:center;"></i>
          <div><div style="font-size:13px;font-weight:600;color:#5b21b6;">Admit Patient</div><div style="font-size:11px;color:var(--gray-500);">Admit to ward / inpatient</div></div>
        </button>
      </div>
    </div>
  </div>
</div>

</div><!-- end dq-main-content -->

<!-- Patient Details Section -->
<div id="modal-patientDetails" style="display:none;">
  <div style="background:white;border-radius:var(--radius);padding:24px;box-shadow:var(--shadow);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid var(--gray-200);">
      <h3 style="font-size:18px;font-weight:600;color:var(--gray-900);"><i class="fas fa-user-circle" style="color:var(--primary-color);"></i> Patient Details</h3>
      <div style="display:flex;gap:8px;">
        <button onclick="printExternalRx()" id="btn-print-ext-rx" style="display:none;padding:8px 16px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-print"></i> Print External Prescription</button>
        <button onclick="hideModal('patientDetails')" style="padding:8px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;font-weight:600;"><i class="fas fa-arrow-left"></i> Back</button>
      </div>
    </div>
    <div id="pd-content">
      <div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></div>
    </div>
  </div>
</div>

<!-- Department Form Modal -->
<div id="modal-departmentForm" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:90vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <!-- Header with patient info -->
    <div style="padding:20px;border-bottom:1px solid var(--gray-200);background:var(--gray-50);">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
        <div>
          <h2 style="font-size:18px;font-weight:700;color:var(--gray-900);margin:0;">Department Form</h2>
          <p style="font-size:12px;color:var(--gray-500);margin:4px 0 0 0;">Fill out the department-specific form for this consultation</p>
        </div>
        <button onclick="hideFormModal()" style="border:none;background:none;font-size:28px;cursor:pointer;color:var(--gray-400);">&times;</button>
      </div>
      
      <!-- Patient Info Card -->
      <div style="background:white;border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-top:12px;">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Patient Name</div>
            <div style="font-size:14px;font-weight:600;color:var(--gray-900);margin-top:2px;" id="mf-patient-name">—</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Patient ID</div>
            <div style="font-size:14px;font-weight:600;color:var(--primary-color);margin-top:2px;font-family:monospace;" id="mf-patient-id">—</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:600;color:var(--gray-500);text-transform:uppercase;">Queue #</div>
            <div style="font-size:14px;font-weight:600;color:var(--gray-900);margin-top:2px;" id="mf-queue-no">—</div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Form Content -->
    <div style="padding:20px;">
      <!-- Form selector -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">
          <i class="fas fa-clipboard-list"></i> Select Form
        </label>
        <select id="mf-form-select" onchange="mfLoadFormFields()" style="width:100%;padding:10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;">
          <option value="">— Select Form —</option>
        </select>
      </div>

      <!-- Form fields container -->
      <div id="mf-form-fields" style="display:none;background:var(--gray-50);border-radius:6px;padding:16px;border:1px solid var(--gray-200);max-height:350px;overflow-y:auto;margin-bottom:16px;"></div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:8px;">
        <button onclick="hideFormModal()" style="flex:1;padding:11px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;color:var(--gray-700);">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button onclick="mfSaveForm()" style="flex:1;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;">
          <i class="fas fa-save"></i> Save Form & Serve Patient
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Lab / Radiology Order Modal -->
<div id="modal-labOrder" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:600px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;" id="modal-labOrder-title"><i class="fas fa-flask" style="color:#2563eb;"></i> Lab Test Order</h3>
      <button onclick="hideModal('labOrder')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <!-- Patient info -->
      <div style="background:var(--gray-50);border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;color:var(--gray-700);" id="lab-patient-info">No patient selected</div>

      <!-- Order Type -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Order Type</label>
        <select id="mo-lab-type" onchange="labLoadTests()" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;">
          <option value="lab">Lab Test</option>
          <option value="radiology">Radiology / Imaging</option>
        </select>
      </div>

      <!-- Available Tests -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">Select Tests</label>
        <div id="lab-tests-list" style="border:1px solid var(--gray-200);border-radius:8px;max-height:200px;overflow-y:auto;padding:4px;">
          <div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">Loading tests...</div>
        </div>
      </div>

      <!-- Selected Tests Summary -->
      <div id="lab-selected-summary" style="display:none;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:12px;">
        <div style="font-size:12px;font-weight:600;color:#1e40af;margin-bottom:6px;">Selected Tests:</div>
        <div id="lab-selected-list" style="font-size:12px;color:#1e40af;"></div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;padding-top:8px;border-top:1px solid #bfdbfe;">
          <span style="font-size:13px;font-weight:700;color:#1e40af;">Total:</span>
          <span style="font-size:13px;font-weight:700;color:#1e40af;" id="lab-total-price">0 RWF</span>
        </div>
      </div>

      <!-- Notes -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Clinical Notes</label>
        <textarea id="mo-lab-details" rows="2" placeholder="Any specific instructions..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;resize:vertical;font-size:13px;"></textarea>
      </div>

      <!-- Insurance toggle -->
      <div style="margin-bottom:12px;" id="lab-ins-toggle">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:500;color:var(--gray-700);cursor:pointer;">
          <input type="checkbox" id="lab-apply-insurance" onchange="labUpdateSummary()"> Apply Insurance Coverage
        </label>
        <div id="lab-ins-info" style="display:none;margin-top:6px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:8px 10px;font-size:12px;color:#1e40af;">
          <span id="lab-ins-detail"></span>
        </div>
      </div>

      <button onclick="labSubmitOrder()" id="btn-mo-lab" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
        <i class="fas fa-paper-plane"></i> Send Order & Create Invoice
      </button>
    </div>
  </div>
</div>

<!-- Prescription Modal (Multi-Drug) -->
<div id="modal-prescription" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:700px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-prescription" style="color:#16a34a;"></i> Drug Prescription</h3>
      <button onclick="hideModal('prescription')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <!-- Patient Info -->
      <div style="background:var(--gray-50);border-radius:6px;padding:10px 14px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:13px;font-weight:600;color:var(--gray-700);" id="rx-patient-info">No patient selected</span>
        <span style="font-size:12px;color:var(--gray-500);" id="rx-visit-info"></span>
      </div>

      <!-- Diagnosis -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Diagnosis / Indication</label>
        <input type="text" id="rx-diagnosis" placeholder="e.g. Upper Respiratory Infection" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      </div>

      <!-- Drug Items Table -->
      <div style="margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <label style="font-size:13px;font-weight:600;color:var(--gray-700);">Medications</label>
          <button onclick="rxAddRow()" type="button" style="padding:6px 12px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;">
            <i class="fas fa-plus"></i> Add Drug
          </button>
        </div>
        <div style="border:1px solid var(--gray-200);border-radius:8px;overflow:hidden;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;" id="rx-drugs-table">
            <thead>
              <tr style="background:var(--gray-50);">
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);width:30%;">Drug</th>
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);">Dosage</th>
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);">Frequency</th>
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);">Duration</th>
                <th style="padding:8px;text-align:left;font-weight:600;color:var(--gray-600);">Qty</th>
                <th style="padding:8px;text-align:center;font-weight:600;color:var(--gray-600);width:30px;"></th>
              </tr>
            </thead>
            <tbody id="rx-drugs-tbody">
            </tbody>
          </table>
        </div>
      </div>

      <!-- Notes -->
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Notes</label>
        <textarea id="rx-notes" rows="2" placeholder="Additional notes..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea>
      </div>

      <!-- Destination -->
      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;">Send To</label>
        <div style="display:flex;gap:12px;">
          <label style="flex:1;display:flex;align-items:center;gap:8px;padding:12px;border:2px solid var(--primary-color);border-radius:8px;cursor:pointer;background:var(--primary-light);">
            <input type="radio" name="rx-destination" value="hospital" checked style="accent-color:var(--primary-color);">
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--gray-900);">Hospital Pharmacy</div>
              <div style="font-size:11px;color:var(--gray-500);">Send to dispensing queue</div>
            </div>
          </label>
          <label style="flex:1;display:flex;align-items:center;gap:8px;padding:12px;border:2px solid var(--gray-200);border-radius:8px;cursor:pointer;" id="rx-external-label">
            <input type="radio" name="rx-destination" value="external" style="accent-color:var(--primary-color);">
            <div>
              <div style="font-size:13px;font-weight:600;color:var(--gray-900);">External Pharmacy</div>
              <div style="font-size:11px;color:var(--gray-500);">Print for patient</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Action Buttons -->
      <div style="display:flex;gap:8px;">
        <button onclick="hideModal('prescription')" style="padding:10px 16px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;color:var(--gray-700);">
          Cancel
        </button>
        <button onclick="rxSubmitPrescription()" style="flex:1;padding:10px;background:#16a34a;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
          <i class="fas fa-check"></i> Submit Prescription
        </button>
        <button onclick="rxSubmitAndPrint()" style="padding:10px 16px;background:#1e40af;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;" id="rx-print-btn" title="Submit & Print">
          <i class="fas fa-print"></i> Print
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Referral Modal -->
<div id="modal-referral" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:500px;max-width:90vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;">Refer to Department</h3>
      <button onclick="hideModal('referral')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Referred To <span style="color:var(--danger-color)">*</span></label>
        <select id="mo-ref-dept" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;">
        <option value="">Select department...</option>
        </select>
      </div>
      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Referral Notes / Reason</label>
        <textarea id="mo-ref-notes" rows="3" placeholder="Reason for referral, findings, suspected diagnosis..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;resize:vertical;"></textarea>
      </div>
      <button onclick="submitReferral()" id="btn-mo-ref" style="width:100%;padding:10px;background:#dc2626;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
        <i class="fas fa-share"></i> Send Referral
      </button>
    </div>
  </div>
</div>

<!-- Admission Modal -->
<!-- Admission Request Modal (Doctor just sends request) -->
<div id="modal-admission" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
  <div style="background:white;border-radius:12px;width:450px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;">
      <h3 style="font-size:16px;font-weight:600;"><i class="fas fa-procedures" style="color:#7c3aed;"></i> Request Admission</h3>
      <button onclick="hideModal('admission')" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
    </div>
    <div style="padding:20px;">
      <div style="background:var(--gray-50);border-radius:6px;padding:10px 14px;margin-bottom:16px;font-size:13px;font-weight:600;color:var(--gray-700);" id="admit-patient-info">No patient selected</div>

      <div style="margin-bottom:12px;">
        <label style="display:block;font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:4px;">Admission Reason <span style="color:var(--danger-color);">*</span></label>
        <textarea id="admit-diagnosis" rows="3" placeholder="Reason for admission / diagnosis..." style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;resize:vertical;font-size:13px;"></textarea>
      </div>

      <div style="margin-bottom:16px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Type</label>
          <select id="admit-type" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <option value="elective">Normal Admission</option>
            <option value="emergency">Emergency</option>
            <option value="transfer">Transfer</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:13px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">Preferred Ward</label>
          <select id="admit-ward-pref" style="width:100%;padding:9px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <option value="">Any available</option>
            <option value="general">General</option>
            <option value="vip">VIP</option>
            <option value="icu">ICU</option>
            <option value="maternity">Maternity</option>
            <option value="pediatric">Pediatric</option>
            <option value="isolation">Isolation</option>
          </select>
        </div>
      </div>

      <button onclick="admitSubmit()" style="width:100%;padding:10px;background:#7c3aed;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;">
        <i class="fas fa-paper-plane"></i> Send Admission Request
      </button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="js/approved-edit.js?v=<?= filemtime(__DIR__ . '/js/approved-edit.js') ?>"></script>
<script>
// Session data injected from PHP
window._sessionUserId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0; ?>;
window._sessionUserData = <?php echo json_encode($_SESSION['user_data'] ?? []); ?>;
window._isAdminUser = <?php echo $isAdminUser ? 'true' : 'false'; ?>;
</script>
<script>
// ============================================================
// Configuration — ALL data fetched via API (like patients.php)
// ============================================================
const _deptBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const dqAPI = window.location.origin + _deptBase + '/api.php?endpoint=departments/department-forms';
const consultAPI = window.location.origin + _deptBase + '/api.php?endpoint=consultation/consult';
const visitsAPI = window.location.origin + _deptBase + '/api.php?endpoint=patients/visits';
const deptAPI = window.location.origin + _deptBase + '/api.php?endpoint=hr/departments';
const usersAPI = window.location.origin + _deptBase + '/api.php?endpoint=settings/users';
window._departmentSignature = <?php echo json_encode($departmentSignature['data_uri'] ?? null); ?>;
window._departmentStamp = <?php echo json_encode($departmentStamp); ?>;

let loggedUserId = 0;
let deptId = 0;
let deptAll = true;            // queue spans every department for everyone (doctors/nurses included)
let ownDeptName = '', dqDeptDefaulted = false, dqAllDepts = [];
let ownDeptId = 0;             // the signed-in user's own department, used by Call Next
const dqVisitDept = {};        // visit id -> its department, used by row actions in the All view
let loggedUserName = '';
let dqCurrentPatient = null;
let dqCurrentVisitId = null;
let dqCurrentConsultId = null;

// ============================================================
// Bootstrap — get department from users table via API
// ============================================================
async function bootDepartment() {
  try {
    // Get user data from PHP session (injected below)
    loggedUserId = window._sessionUserId || 0;

    if (!loggedUserId) {
      document.getElementById('dept-page-title').textContent = 'Not Logged In';
      document.getElementById('dept-page-subtitle').textContent = 'Please log in to view department queue.';
      return;
    }

    // Load full user details including department_id and department_name from API
    const r = await fetch(usersAPI + '&id=' + loggedUserId);
    const j = await r.json();
    if (j.success && j.data) {
      const u = j.data;
      loggedUserName = (u.first_name || '') + ' ' + (u.last_name || '');
      loggedUserName = loggedUserName.trim() || 'User';
      
      // Get department_id from users table
      if (u.department_id) {
        deptId = parseInt(u.department_id);
        ownDeptId = deptId;
        ownDeptName = u.department_name || '';
      }

      // Department name comes from the JOIN in settings/users API
      let deptName = u.department_name || 'My Department';
      if (!deptId && !window._isAdminUser) deptName = 'No Department Assigned';

      // Queue spans all departments; the table's Department filter defaults to the user's own
      deptAll = true;
      document.getElementById('dept-page-title').textContent = deptName;
      document.getElementById('dept-page-subtitle').textContent = 'Showing consultations from all departments';

      try { dqAllDepts = ((await (await fetch(deptAPI + '&consult=1&limit=200')).json()).data || []).map(d => d.name).filter(Boolean); } catch(e) {}

      // Load departments for referral dropdown
      loadReferralDepartments();

      // Load medications for prescription
      rxLoadMedications();

      // Load lab/radiology services
      labLoadServices();

      // Default date filters: today
      ['waiting','consult','completed','referrals'].forEach(t => dfRange('dq-'+t+'-date-from','dq-'+t+'-date-to',0));
      // Start queue polling
      dqLoad();
      setInterval(dqLoad, 15000);
      // Load referrals count badge
      dqLoadReferralsCount();
    } else {
      document.getElementById('dept-page-subtitle').textContent = 'Could not load user data';
    }
  } catch(e) {
    document.getElementById('dept-page-subtitle').textContent = 'Error loading: ' + e.message;
  }
}

// Load all departments for referral modal
async function loadReferralDepartments() {
  const sel = document.getElementById('mo-ref-dept');
  sel.innerHTML = '<option value="">Select department...</option>';
  try {
    const r = await fetch(deptAPI + '&limit=200');
    const j = await r.json();
    if (j.success && j.data) {
      j.data.forEach(d => {
        if (parseInt(d.id) === deptId) return; // skip own department
        const o = document.createElement('option');
        o.value = d.id;
        o.textContent = d.name;
        sel.appendChild(o);
      });
    }
  } catch(e) {}
}

// ============================================================
// Utilities
// ============================================================
function dqEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// Render a structured (imaging) report object as readable label/value rows instead of raw JSON
function dqFmtReport(o){
  const label = k => k.replace(/_/g,' ').replace(/\b\w/g, c => c.toUpperCase());
  const rows = (obj) => Object.entries(obj).map(([k,v]) => {
    if(k === 'attachments' || v === null || v === '' || (Array.isArray(v) && !v.length)) return '';
    if(Array.isArray(v)) v = v.join(', ');
    if(typeof v === 'object'){
      const inner = rows(v);
      return inner ? `<div style="margin-top:6px;"><div style="font-weight:700;color:#065f46;">${dqEsc(label(k))}</div><div style="padding-left:10px;">${inner}</div></div>` : '';
    }
    return `<div><span style="color:var(--gray-500);">${dqEsc(label(k))}:</span> <strong>${dqEsc(v)}</strong></div>`;
  }).join('');
  return rows(o);
}
// Payment state of a visit's invoices: paid (with invoice no.), not paid, or no invoice yet
function dqRenderPaidBadge(c){
  const n = parseInt(c.invoice_count) || 0, open = parseInt(c.unpaid_count) || 0;
  const b = (bg, fg, icon, txt) => `<div style="margin-top:4px;"><span style="background:${bg};color:${fg};padding:2px 7px;border-radius:6px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;"><i class="fas ${icon}"></i> ${txt}</span></div>`;
  if(!n) return b('#f3f4f6', '#6b7280', 'fa-file-invoice', 'No invoice');
  if(open) return b('#fef3c7', '#92400e', 'fa-clock', 'Not paid');
  return b('#d1fae5', '#065f46', 'fa-check-circle', 'Paid · ' + dqEsc(c.paid_invoice_nos || ''));
}
// Excel export of the lists last loaded for each tab (respects the date filters, not the search box)
const dqExportData = {waiting:[], consulting:[], completed:[]};
function dqExcel(tab){
  const list = dqExportData[tab] || [];
  if(!list.length) return alert('Nothing to export.');
  const pay = v => (v.payment_type === 'insurance' || v.payment_type === 'mixed') && v.company_name ? v.company_name + ' (' + (v.copayment_percentage ?? 15) + '% Copay)' : 'Self-Pay';
  const time = t => t ? new Date(t).toLocaleString() : '';
  let head, row;
  if(tab === 'waiting'){
    head = ['#','Patient','Patient No','Visit No','Payment','Status','Department','Vitals'];
    row = (v,i) => [i+1, v.patient_name, v.patient_no, v.visit_no, pay(v), 'Waiting', v.department_name, parseInt(v.vitals_count) ? 'Recorded' : 'None'];
  } else if(tab === 'consulting'){
    head = ['#','Patient','Patient No','Visit No','Payment','Doctor','Department','Started','Vitals'];
    row = (v,i) => [i+1, v.patient_name, v.patient_no, v.visit_no, pay(v), v.doctor_name, v.department_name, time(v.consultation_started_at || v.queued_at), parseInt(v.vitals_count) ? 'Recorded' : 'None'];
  } else {
    head = ['#','Patient','Patient No','Visit No','Payment','Invoice Status','Invoice No','Doctor','Diagnosis','Department'];
    row = (v,i) => {
      const n = parseInt(v.invoice_count) || 0, open = parseInt(v.unpaid_count) || 0;
      return [i+1, v.patient_name, v.patient_no, v.visit_no, pay(v), !n ? 'No invoice' : open ? 'Not paid' : 'Paid', v.paid_invoice_nos || '', v.doctor_name, v.diagnosis || v.chief_complaint, v.department_name];
    };
  }
  const ws = XLSX.utils.aoa_to_sheet([head].concat(list.map((v,i) => row(v,i).map(x => x ?? ''))));
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, tab.charAt(0).toUpperCase() + tab.slice(1));
  XLSX.writeFile(wb, 'department_' + tab + '_' + new Date().toISOString().slice(0,10) + '.xlsx');
}
function dqRenderInsuranceBadge(v) {
  if (!v) return '';
  const isIns = (v.payment_type === 'insurance' || v.payment_type === 'mixed' || (v.insurance_id && v.insurance_id != 13)) && v.company_name;
  if (isIns) {
    const copay = (v.copayment_percentage !== undefined && v.copayment_percentage !== null) ? parseFloat(v.copayment_percentage) : 15;
    const pkg = v.package_name ? ` · ${dqEsc(v.package_name)}` : '';
    return `<div style="margin-top:4px;"><span style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:2px 7px;border-radius:6px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;" title="Insurance Coverage: ${100-copay}%"><i class="fas fa-shield-alt"></i> ${dqEsc(v.company_name)}${pkg} <span style="background:#dbeafe;color:#1e40af;padding:1px 5px;border-radius:4px;font-weight:700;">${copay}% Copay</span></span></div>`;
  } else {
    return `<div style="margin-top:4px;"><span style="background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb;padding:2px 7px;border-radius:6px;font-size:11px;font-weight:500;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;"><i class="fas fa-user"></i> Self-Pay (100% Copay)</span></div>`;
  }
}
function showModal(id){
  if(id==='patientDetails'){
    document.getElementById('modal-patientDetails').style.display='block';
    // Hide the main queue content
    document.getElementById('dq-main-content').style.display='none';
  } else {
    document.getElementById('modal-'+id).style.display='flex';
  }
}
function hideModal(id){
  document.getElementById('modal-'+id).style.display='none';
  if(id==='patientDetails'){
    document.getElementById('dq-main-content').style.display='block';
  }
}
function getAPIPath(p){ return p; }

// Search/filter queue table on any tab
function dqFilterTab(tab){
  let inputId, tbodyId;
  if(tab === 'waiting'){ inputId = 'dq-search'; tbodyId = 'dq-tbody'; }
  else if(tab === 'consulting'){ inputId = 'dq-consult-search'; tbodyId = 'dq-consult-tbody'; }
  else if(tab === 'completed'){ inputId = 'dq-completed-search'; tbodyId = 'dq-completed-tbody'; }
  else if(tab === 'referrals'){ inputId = 'dq-referrals-search'; tbodyId = 'dq-referrals-tbody'; }
  else if(tab === 'labpending'){ inputId = 'dq-labpending-search'; tbodyId = 'dq-labpending-tbody'; }
  else return;

  const input = document.getElementById(inputId);
  const term = (input?.value || '').toLowerCase().trim();
  const rows = document.querySelectorAll('#' + tbodyId + ' tr');
  rows.forEach(row => {
    if(row.querySelector('td[colspan]')) return;
    const dd = {waiting:['dq-dept-filter',3], consulting:['dq-consult-dept-filter',4], completed:['dq-completed-dept-filter',5]}[tab];
    const dept = dd ? (document.getElementById(dd[0])?.value || '') : '';
    const okDept = !dept || row.children[dd[1]]?.textContent.trim() === dept;
    row.style.display = okDept && (!term || row.textContent.toLowerCase().includes(term)) ? '' : 'none';
  });
  // keep # as 1,2,3... over visible rows
  let n = 0;
  rows.forEach(row => {
    const c = row.querySelector('td.dq-no');
    if(c && row.style.display !== 'none') c.textContent = ++n;
  });
}
function dqFilterQueue(){ dqFilterTab('waiting'); }

// Fill Department selects of the Consultation + Completed tabs
function dqFillDeptSelects(){
  const names = [...new Set([...dqAllDepts, ...['waiting','consulting','completed'].flatMap(k => (dqExportData[k]||[]).map(v => v.department_name))].filter(Boolean))].sort();
  ['dq-consult-dept-filter','dq-completed-dept-filter'].forEach(id => {
    const s = document.getElementById(id); if(!s) return;
    const cur = s.value;
    s.innerHTML = '<option value="">All</option>' + names.map(n => `<option value="${dqEsc(n)}">${dqEsc(n)}</option>`).join('');
    s.value = cur || (ownDeptName && !window._isAdminUser ? ownDeptName : '');
    if (ownDeptId && !window._isAdminUser) s.disabled = true;
  });
}

// Start consultation — update queue status and record doctor, then redirect
async function dqStartConsult(visitId, patientId){
  try{
    await fetch(visitsAPI+'&id='+visitId, {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({consultation_status:'in_consultation', doctor_id:loggedUserId, doctor_name:loggedUserName})
    });
  }catch(e){}
  window.location.href='consultation.php?visit_id='+visitId+'&patient_id='+patientId;
}

// Load completed consultations
async function dqLoadCompleted(){
  const tbody=document.getElementById('dq-completed-tbody');
  tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
  const dateFrom=document.getElementById('dq-completed-date-from').value;
  const dateTo=document.getElementById('dq-completed-date-to').value;
  try{
    // Use the department_queue endpoint which returns done visits
    let url=window.location.origin + _deptBase + '/api.php?endpoint=patients/visits&action=department_queue&department_id='+(deptAll?'all':deptId);
    if(dateFrom) url+='&date_from='+dateFrom;
    if(dateTo) url+='&date_to='+dateTo;
    const r=await fetch(url);
    const j=await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:#dc2626;">'+dqEsc(j.message)+'</td></tr>';return;}
    let list=j.data?.done||[];
    dqExportData.completed=list;
    document.getElementById('dq-tab-completed-count').textContent=list.length;
    if(!list.length){
      tbody.innerHTML='<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-clipboard-check" style="font-size:24px;display:block;margin-bottom:8px;"></i>No completed consultations found.</td></tr>';
      return;
    }
    tbody.innerHTML=list.map((c, i)=>{
      const visitId = c.visit_id || c.id;
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="dq-no" style="padding:10px 12px;font-weight:700;color:var(--gray-700);">${i+1}</td>
        <td style="padding:10px 12px;">
          <div style="font-weight:600;">${dqEsc(c.patient_name||'')}</div>
          <div style="font-size:12px;color:var(--gray-500);">${dqEsc(c.patient_no||'')}</div>
          ${dqRenderInsuranceBadge(c)}
          ${dqRenderPaidBadge(c)}
        </td>
        <td style="padding:10px 12px;font-family:monospace;font-size:13px;color:var(--primary-color);">${dqEsc(c.visit_no||'')}</td>
        <td style="padding:10px 12px;font-size:13px;color:var(--gray-700);">${dqEsc(c.doctor_name||'—')}</td>
        <td style="padding:10px 12px;font-size:13px;">${dqEsc(c.diagnosis||c.chief_complaint||'—')}</td>
        <td style="padding:10px 12px;font-size:13px;color:var(--gray-700);">${dqEsc(c.department_name||'—')}</td>
        <td style="padding:10px 12px;text-align:center;white-space:nowrap;">
          <div style="display:flex;gap:4px;justify-content:center;flex-wrap:wrap;">
            <button onclick="dqOpenDetails(${visitId},${c.patient_id},'${dqEsc(c.visit_no||'')}','${dqEsc(c.patient_name||'')}','')" style="padding:5px 10px;background:#6b21a8;color:white;border:none;border-radius:4px;font-size:11px;font-weight:500;cursor:pointer;" title="View Details"><i class="fas fa-eye"></i></button>
          </div>
        </td>
      </tr>`;
    }).join('');
    dqFillDeptSelects();
    dqFilterTab('completed');
  }catch(e){
    tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}

// ============================================================
// Re-open a completed consultation
// ============================================================
async function dqReopenConsultation(visitId, patientId, patientName){
  if(!confirm('Re-open consultation for '+patientName+'?\nThis will move them back to your queue.')) return;
  try{
    const r=await fetch(window.location.origin + _deptBase + '/api.php?endpoint=consultation/consult',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      action:'reopen', visit_id:visitId, patient_id:patientId, doctor_id:loggedUserId
    })});
    const j=await r.json();
    if(j.success){
      alert('✓ Consultation re-opened. Patient is back in your queue.');
      dqLoad();
      dqLoadCompleted();
    } else {
      alert('Failed: '+(j.message||'Error'));
    }
  }catch(e){alert('Error: '+e.message);}
}

// ============================================================
// Load My Referrals count for badge (lightweight)
// ============================================================
async function dqLoadReferralsCount(){
  try{
    const r=await fetch(window.location.origin + _deptBase + '/api.php?endpoint=consultation/consult&action=my_referrals&doctor_id='+loggedUserId);
    const j=await r.json();
    if(j.success){
      document.getElementById('dq-tab-referrals-count').textContent=(j.data||[]).length;
    }
  }catch(e){}
}

// ============================================================
// Load My Referrals — patients this doctor referred out
// ============================================================
async function dqLoadReferrals(){
  const tbody=document.getElementById('dq-referrals-tbody');
  tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
  const rDateFrom = document.getElementById('dq-referrals-date-from')?.value || '';
  const rDateTo = document.getElementById('dq-referrals-date-to')?.value || '';
  try{
    const r=await fetch(window.location.origin + _deptBase + '/api.php?endpoint=consultation/consult&action=my_referrals&doctor_id='+loggedUserId);
    const j=await r.json();
    if(!j.success){tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:#dc2626;">'+dqEsc(j.message)+'</td></tr>';return;}
    let list=j.data||[];
    // Client-side date range filtering
    if(rDateFrom || rDateTo){
      list = list.filter(c => {
        const d = (c.created_at || '').split(' ')[0].split('T')[0];
        if(rDateFrom && d < rDateFrom) return false;
        if(rDateTo && d > rDateTo) return false;
        return true;
      });
    }
    document.getElementById('dq-tab-referrals-count').textContent=list.length;
    if(!list.length){
      tbody.innerHTML='<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-share" style="font-size:24px;display:block;margin-bottom:8px;"></i>No referrals found.</td></tr>';
      return;
    }
    tbody.innerHTML=list.map((c, i)=>{
      const statusColors={waiting:'#fef3c7',in_consultation:'#dbeafe',done:'#dcfce7'};
      const statusTextColors={waiting:'#92400e',in_consultation:'#1e40af',done:'#166534'};
      const qStatus=c.queue_status||'sent';
      return `<tr style="border-bottom:1px solid var(--gray-100);">
        <td class="dq-no" style="padding:10px 12px;font-weight:700;color:var(--gray-700);">${i+1}</td>
        <td style="padding:10px 12px;">
          <div style="font-weight:600;">${dqEsc(c.patient_name||'')}</div>
          <div style="font-size:12px;color:var(--gray-500);">${dqEsc(c.patient_no||'')}</div>
          ${dqRenderInsuranceBadge(c)}
        </td>
        <td style="padding:10px 12px;font-family:monospace;font-size:13px;color:var(--primary-color);">${dqEsc(c.visit_no||'')}</td>
        <td style="padding:10px 12px;font-size:13px;font-weight:500;">${dqEsc(c.referred_dept_name||'—')}</td>
        <td style="padding:10px 12px;font-size:13px;">${dqEsc(c.diagnosis||'—')}</td>
        <td style="padding:10px 12px;font-size:12px;color:var(--gray-500);">${c.created_at?new Date(c.created_at).toLocaleDateString():''}</td>
        <td style="padding:10px 12px;text-align:center;"><span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:${statusColors[qStatus]||'#f3f4f6'};color:${statusTextColors[qStatus]||'#6b7280'};">${(qStatus||'sent').replace('_',' ')}</span></td>
      </tr>`;
    }).join('');
    dqFilterTab('referrals');
  }catch(e){
    tbody.innerHTML='<tr><td colspan="6" style="padding:30px;text-align:center;color:#dc2626;">Error: '+e.message+'</td></tr>';
  }
}

// ============================================================
// Show Lab/Radiology Modal with pre-selected type
// ============================================================
// Quick action from queue row - set patient context then open order menu
function dqOpenOrderMenu(visitId, patientId, visitNo, patientName, queueNo){
  if (deptAll && dqVisitDept[visitId]) deptId = dqVisitDept[visitId];
  dqCurrentVisitId = visitId;
  dqCurrentPatient = {visitId, patientId, visitNo, patientName, queueNo};
  document.getElementById('om-patient-label').textContent = 'Patient: ' + patientName + ' (' + visitNo + ')';
  showModal('orderMenu');
}

// Open patient details modal - show form data, prescriptions, orders
// Age in years (or months/days for infants) from a date of birth
function dqAgeFromDob(dob){
  if(!dob) return '—';
  const d = new Date(dob), now = new Date();
  if(isNaN(d)) return '—';
  let y = now.getFullYear() - d.getFullYear();
  const m = now.getMonth() - d.getMonth();
  if(m < 0 || (m === 0 && now.getDate() < d.getDate())) y--;
  if(y >= 1) return y + ' yr' + (y > 1 ? 's' : '');
  const mo = (now.getFullYear() - d.getFullYear()) * 12 + now.getMonth() - d.getMonth() - (now.getDate() < d.getDate() ? 1 : 0);
  if(mo >= 1) return mo + ' mo';
  return Math.max(0, Math.floor((now - d) / 86400000)) + ' days';
}

// ---- Add procedures to billing from the details modal (same flow as consultation Step 2) ----
const DQ_PROC_EXCLUDED = ['bacteriology','biochemistry','hematology','immuno-serology','parasitology','molecular','anatomical pathology','blood transfusion'];
let dqProcServices = [], dqProcPicked = [], dqProcMatches = [];
function dqProcCardHtml(allPaid){
  dqProcPicked = [];
  const head = `<div style="border:1px solid var(--gray-200);border-radius:10px;padding:20px;margin-bottom:16px;"><h4 style="font-size:15px;font-weight:600;color:#9d174d;margin-bottom:14px;"><i class="fas fa-syringe"></i> Add Procedure</h4>`;
  if(allPaid) return head + `<div style="padding:12px;background:#f3f4f6;border-radius:6px;font-size:12px;color:var(--gray-600);"><i class="fas fa-lock"></i> This visit is already paid — procedures can no longer be added.</div></div>`;
  return head + `<div id="dq-proc-rows"></div>
    <button onclick="dqProcAddRow()" style="padding:8px 14px;background:var(--gray-50);color:var(--gray-600);border:1px solid var(--gray-200);border-radius:6px;font-size:12px;cursor:pointer;margin-bottom:14px;"><i class="fas fa-plus"></i> Add Procedure</button>
    <div><label style="font-size:12px;font-weight:500;color:var(--gray-600);">Clinical Notes</label>
      <textarea id="dq-proc-notes" rows="2" placeholder="Procedure notes / indications..." style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;resize:vertical;"></textarea></div>
    <button id="dq-proc-save" onclick="dqProcSave()" style="margin-top:12px;padding:10px 20px;background:#db2777;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:13px;"><i class="fas fa-syringe"></i> Save Procedure & Generate Invoice</button>
  </div>`;
}
function dqProcAddRow(){
  const i = dqProcPicked.length; dqProcPicked.push({id:'', name:'', note:'', qty:1});
  const row = document.createElement('div');
  row.className = 'dq-proc-row'; row.dataset.pi = i;
  row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 70px auto;gap:10px;margin-bottom:10px;align-items:end;';
  row.innerHTML = `<div style="position:relative;"><label style="font-size:12px;font-weight:500;color:var(--gray-600);">Procedure</label>
      <input type="text" placeholder="Search procedure..." autocomplete="off" oninput="dqProcSearch(this)" onfocus="dqProcSearch(this)" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
      <div class="dq-proc-dd" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:100;background:white;border:1px solid var(--gray-200);border-radius:6px;max-height:180px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1);margin-top:2px;"></div></div>
    <div><label style="font-size:12px;font-weight:500;color:var(--gray-600);">Notes</label>
      <input type="text" placeholder="Optional notes" oninput="dqProcPicked[${i}].note=this.value" style="width:100%;padding:9px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;"></div>
    <div><label style="font-size:12px;font-weight:500;color:var(--gray-600);">Qty</label>
      <input type="number" min="1" value="1" oninput="dqProcPicked[${i}].qty=Math.max(1,parseInt(this.value)||1)" style="width:100%;padding:9px 8px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;text-align:center;"></div>
    <button onclick="dqProcRemoveRow(this)" style="padding:9px 12px;background:var(--danger-color);color:white;border:none;border-radius:6px;cursor:pointer;font-size:12px;"><i class="fas fa-times"></i></button>`;
  document.getElementById('dq-proc-rows').appendChild(row);
}
function dqProcRemoveRow(btn){
  const row = btn.closest('.dq-proc-row'); dqProcPicked[row.dataset.pi] = null; row.remove();
  if(!document.querySelector('.dq-proc-row')) dqProcAddRow();
}
async function dqProcLoad(){
  if(dqProcServices.length) return;
  try{
    const j = await (await fetch(window.location.origin + _deptBase + '/api.php?endpoint=service-catalog&category=procedure&visit_id=' + dqCurrentPatient.visitId)).json();
    dqProcServices = (j.data || []).filter(s => { const sp = (s.speciality||'').toLowerCase(); return !sp.startsWith('imaging') && !DQ_PROC_EXCLUDED.some(e => sp.includes(e)); });
  }catch(e){ dqProcServices = []; }
}
async function dqProcSearch(inp){
  await dqProcLoad();
  const row = inp.closest('.dq-proc-row'), dd = row.querySelector('.dq-proc-dd');
  dqProcPicked[row.dataset.pi].id = ''; dqProcPicked[row.dataset.pi].name = '';
  const q = inp.value.toLowerCase().trim();
  if(!q){ dd.style.display = 'none'; return; }
  dqProcMatches = dqProcServices.filter(s => (s.procedure_name||'').toLowerCase().includes(q) || (s.speciality||'').toLowerCase().includes(q) || (s.ichi_code||'').toLowerCase().includes(q)).slice(0, 15);
  dd.innerHTML = dqProcMatches.length ? dqProcMatches.map((s, k) => `<div onclick="dqProcPick(this,${k})" style="padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--gray-100);" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''"><div style="font-weight:500;">${dqEsc(s.procedure_name)}</div><div style="font-size:11px;color:var(--gray-500);">${dqEsc(s.speciality||'')}</div></div>`).join('')
    : '<div style="padding:10px;color:var(--gray-400);font-size:12px;">No procedures found</div>';
  dd.style.display = 'block';
}
function dqProcPick(el, k){
  const s = dqProcMatches[k]; if(!s) return;
  const row = el.closest('.dq-proc-row');
  if(dqProcPicked.some(p => p && String(p.id) === String(s.id))){ alert('"' + s.procedure_name + '" is already selected.'); el.closest('.dq-proc-dd').style.display = 'none'; return; }
  Object.assign(dqProcPicked[row.dataset.pi], {id:s.id, name:s.procedure_name});
  row.querySelector('input').value = s.procedure_name;
  el.closest('.dq-proc-dd').style.display = 'none';
}
async function dqProcSave(){
  const picked = dqProcPicked.filter(x => x && x.id);
  if(!picked.length) return alert('Please select at least one procedure.');
  const p = dqCurrentPatient, btn = document.getElementById('dq-proc-save');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  try{
    const v = (await (await fetch(window.location.origin + _deptBase + '/api.php?endpoint=patients/visits&id=' + p.visitId)).json()).data || {};
    const hasIns = v.insurance_id && (v.payment_type === 'insurance' || v.payment_type === 'mixed');
    const j = await (await fetch(window.location.origin + _deptBase + '/api.php?endpoint=patients/billing', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({
      visit_id: p.visitId, patient_id: p.patientId,
      payment_type: hasIns ? v.payment_type : 'self_pay', insurance_id: hasIns ? v.insurance_id : null,
      notes: document.getElementById('dq-proc-notes').value.trim(),
      items: picked.map(x => ({service_id:x.id, description:x.name + (x.note ? ' — ' + x.note : ''), category:'procedure', quantity:x.qty||1, unit_price:0, discount:0, total:0}))
    })})).json();
    if(!j.success){ alert('Failed: ' + (j.message || 'Error')); btn.disabled = false; btn.innerHTML = '<i class="fas fa-syringe"></i> Save Procedure & Generate Invoice'; return; }
    alert('✓ Procedure(s) saved & invoice ' + ((j.data && j.data.invoice_no) || '') + ' generated!');
    dqReloadDetails();
  }catch(e){ alert('Error: ' + e.message); btn.disabled = false; }
}

// ---- Approval-gated corrections from the details modal (see js/approved-edit.js) ----
function dqReloadDetails(){ const p = dqCurrentPatient; dqOpenDetails(p.visitId, p.patientId, p.visitNo, p.patientName, p.queueNo); }
function dqEditDiagnosis(){
  const c = window._dqDiag; if(!c) return;
  approvedEdit('consult_diagnosis', c.id, 'Diagnosis — ' + dqCurrentPatient.visitNo, [
    {key:'diagnosis', label:'Final Diagnosis', value:c.diagnosis||''},
    {key:'differential_diagnosis', label:'Differential Diagnosis', value:c.differential_diagnosis||'', multiline:true}
  ], dqReloadDetails);
}
function dqEditAllVitals(){ const p = dqCurrentPatient; editAllVitals(p.visitId, p.patientId, dqReloadDetails, dqEditVital); }
function dqEditVital(id){
  const r = window._dqVitals[id]; if(!r) return;
  approvedEdit('consult_vital', id, r.sign_name + ' — ' + dqCurrentPatient.visitNo, [
    {key:'value', label:r.sign_name + (r.unit ? ' (' + r.unit + ')' : ''), value:r.value}
  ], dqReloadDetails);
}
function dqEditOrder(id){
  const o = window._dqOrders[id]; if(!o) return;
  let rows = null; try{ const p = JSON.parse(o.result); if(Array.isArray(p)) rows = p; else if(p && typeof p === 'object') rows = false; }catch(e){}
  // false = structured imaging report: only the order note is editable here
  const fields = rows ? rows.map((r, i) => ({key:'v'+i, label:(r.test_name||'Result')+(r.unit?' ('+r.unit+')':''), value:r.value||''}))
    : rows === false ? [] : [{key:'result', label:'Result', value:o.result||'', multiline:true}];
  fields.push({key:'details', label:'Notes', value:o.details||'', multiline:true});
  approvedEdit('consult_order', id, (o.description||o.order_type) + ' — ' + dqCurrentPatient.visitNo, fields, dqReloadDetails, rows ? body => {
    body.result = JSON.stringify(rows.map((r, i) => Object.assign({}, r, {value: body['v'+i]})));
    rows.forEach((r, i) => delete body['v'+i]);
  } : null);
}

async function dqOpenDetails(visitId, patientId, visitNo, patientName, queueNo){
  if (deptAll && dqVisitDept[visitId]) deptId = dqVisitDept[visitId];
  dqCurrentVisitId = visitId;
  dqCurrentPatient = {visitId, patientId, visitNo, patientName, queueNo};
  
  const content = document.getElementById('pd-content');
  content.innerHTML = '<div style="text-align:center;padding:30px;color:var(--gray-400);"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i> Loading...</div>';
  showModal('patientDetails');
  window._extRxItems = [];
  window._extRxPatient = patientName;
  window._extRxVisitId = visitId;
  window._extRxVisitNo = visitNo;
  document.getElementById('btn-print-ext-rx').style.display='none';

  let html = `<div style="margin-bottom:16px;background:var(--gray-50);border-radius:8px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;">
    <div><span style="font-size:16px;font-weight:700;color:var(--gray-900);">${dqEsc(patientName)}</span>
    <span style="font-size:12px;color:var(--gray-500);margin-left:8px;">${dqEsc(visitNo)}</span></div>
  </div>`;

  // 0a. Load patient personal details & visit insurance
  try{
    const [pr, vr] = await Promise.all([
      fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=patients/patients&id='+patientId)),
      fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=patients/visits&id='+visitId))
    ]);
    const pj = await pr.json();
    const vj = await vr.json();
    const v = (vj && vj.success) ? vj.data : null;
    const isIns = v && (v.payment_type === 'insurance' || v.payment_type === 'mixed' || (v.insurance_id && v.insurance_id != 13)) && v.company_name;
    const copay = isIns ? parseFloat(v.copayment_percentage ?? 15) : 100;
    const coverage = 100 - copay;

    if(pj.success && pj.data){
      const p=pj.data;
      const alg=(p.allergies||'').trim();
      if(alg && !/^(none|no|nil|n\/a|-+|none known|nkda)\b/i.test(alg))
        html = html.replace('</div>\n  </div>', '</div>\n    <span style="padding:3px 10px;border-radius:20px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;font-size:12px;font-weight:700;"><i class="fas fa-exclamation-triangle"></i> Allergies: '+dqEsc(alg)+'</span>\n  </div>');
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-user" style="color:var(--primary-color);"></i> Patient & Insurance Information
        </h4>
        <div style="background:var(--gray-50);border-radius:6px;padding:12px 14px;border:1px solid var(--gray-200);font-size:13px;display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;">
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Full Name:</span> ${dqEsc((p.first_name||'')+' '+(p.last_name||''))}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Patient No:</span> ${dqEsc(p.patient_no||'')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Gender:</span> ${dqEsc(p.gender||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">DOB:</span> ${dqEsc(p.date_of_birth||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Age:</span> ${dqEsc(dqAgeFromDob(p.date_of_birth))}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Phone:</span> ${dqEsc(p.phone||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Address:</span> ${dqEsc(p.address||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Emergency Contact / Next of Kin:</span> ${dqEsc(p.next_of_kin||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Relationship:</span> ${dqEsc(p.kin_relationship||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Emergency Contact Phone:</span> ${dqEsc(p.emergency_contact||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:var(--gray-600);">Blood Group:</span> ${dqEsc(p.blood_group||'—')}</div>
          <div style="padding:4px 0;"><span style="font-weight:600;color:#dc2626;">Allergies:</span> ${dqEsc(p.allergies||'—')}</div>
          <div style="padding:6px 0;grid-column:1/-1;border-top:1px dashed var(--gray-200);margin-top:4px;">
            <span style="font-weight:600;color:var(--gray-700);"><i class="fas fa-shield-alt" style="color:var(--primary-color);"></i> Insurance / Payment:</span>
            ${isIns ? `
              <span style="margin-left:8px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-shield-alt"></i> <strong>${dqEsc(v.company_name)}</strong> ${v.package_name ? '· ' + dqEsc(v.package_name) : ''}
                <span style="background:#dbeafe;color:#1e40af;padding:2px 7px;border-radius:4px;font-weight:700;">${copay}% Patient Copay</span>
                <span style="color:#059669;font-size:11px;">(${coverage}% Covered)</span>
              </span>
            ` : `
              <span style="margin-left:8px;background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:500;display:inline-flex;align-items:center;gap:4px;">
                <i class="fas fa-user"></i> Self-Pay / Cash (100% Patient Copay)
              </span>
            `}
          </div>
          ${p.chronic_diseases?'<div style="padding:4px 0;grid-column:1/-1;"><span style="font-weight:600;color:#d97706;">Chronic Diseases:</span> '+dqEsc(p.chronic_diseases)+'</div>':''}
        </div>
      </div>`;
    }
  }catch(e){}

  // 0b. Load vital signs from vital_signs_records
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=settings/vital-signs&action=records&visit_id='+visitId));
    const j = await r.json();
    if(j.success){
      // newest value per vital sign
      const by = {};
      j.data.forEach(r => { const k = r.sign_slug || r.sign_name; if(!by[k] || r.recorded_at > by[k].recorded_at) by[k] = r; });
      const find = re => Object.values(by).find(r => re.test((r.sign_slug||'') + ' ' + (r.sign_name||'')));
      const fmt = r => r ? dqEsc(r.value) + (r.unit ? ' ' + dqEsc(r.unit) : '') : '—';
      // BMI is calculated from weight (kg) and height (cm); falls back to a recorded BMI
      const w = parseFloat((find(/weight/i)||{}).value), h = parseFloat((find(/size|height/i)||{}).value);
      const bmi = (w > 0 && h > 0) ? (w / Math.pow(h / 100, 2)).toFixed(1) + ' kg/m²' : (find(/bmi/i) ? fmt(find(/bmi/i)) : '—');
      const latest = Object.values(by).reduce((a, r) => (!a || r.recorded_at > a.recorded_at) ? r : a, null);
      window._dqVitals = {};
      const vr = (l, re) => { const r = find(re); if(r) window._dqVitals[r.id] = r; return [l, fmt(r), r]; };
      const rows = [
        vr('Blood Pressure', /bp|blood pressure/i),
        vr('Pulse / Heart Rate', /hr|heart|pulse/i),
        vr('Respiratory Rate', /resp/i),
        vr('Oxygen Saturation (SpO₂)', /spo2/i),
        vr('Height', /size|height/i),
        ['BMI', bmi],
        vr('Pain Score', /pain/i),
        vr('Blood Glucose', /gluc/i),
        ['Date & Time Taken', latest ? dqEsc(latest.recorded_at) : '—'],
        ['Recorded By (Nurse/Staff)', latest && latest.recorded_by_name ? dqEsc(latest.recorded_by_name) : '—']
      ];
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-heartbeat" style="color:#dc2626;"></i> Vital Signs
          <button onclick="dqEditAllVitals()" style="margin-left:8px;padding:3px 10px;background:#2563eb;color:white;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;"><i class="fas fa-pen"></i> Edit all vitals</button>
        </h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;">
          ${rows.map(([l, v, rec], ri) => '<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:8px 12px;"><div style="font-size:11px;color:var(--gray-500);display:flex;justify-content:space-between;">' + l + (rec ? '<i class="fas fa-pen" title="Correct (needs admin approval)" style="cursor:pointer;color:#2563eb;" onclick="dqEditVital(' + rec.id + ')"></i>' : (ri < 8 && l !== 'BMI' ? '<i class="fas fa-plus" title="Fill this vital sign" style="cursor:pointer;color:#059669;" onclick="dqEditAllVitals()"></i>' : '')) + '</div><div style="font-size:14px;font-weight:700;color:var(--gray-900);">' + v + '</div></div>').join('')}
        </div>
      </div>`;
    }
  }catch(e){}

  // 1. Load consultation data (step 1 form)
  try{
    const r = await fetch(getAPIPath(consultAPI+'&action=consultation&visit_id='+visitId));
    const j = await r.json();
    if(j.success && j.data && j.data.length > 0){
      for(const c of j.data){
        html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-stethoscope" style="color:var(--primary-color);"></i> Consultation${c.department_name?' — '+dqEsc(c.department_name):''}
        </h4>
        <div style="background:var(--gray-50);border-radius:6px;padding:12px 14px;border:1px solid var(--gray-200);font-size:13px;">`;
        if(c.chief_complaint) html += `<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);min-width:140px;display:inline-block;">Chief Complaint:</span> ${dqEsc(c.chief_complaint)}</div>`;
        if(c.history) html += `<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);min-width:140px;display:inline-block;">History:</span> ${dqEsc(c.history)}</div>`;
        if(c.examination) html += `<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);min-width:140px;display:inline-block;">Physical Examination:</span> ${dqEsc(c.examination)}</div>`;
        if(c.differential_diagnosis) html += `<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:#d97706;min-width:140px;display:inline-block;">Differential Diagnosis:</span> ${dqEsc(c.differential_diagnosis)}</div>`;
        if(c.diagnosis) html += `<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:#7c3aed;min-width:140px;display:inline-block;">Final Diagnosis:</span> <strong>${dqEsc(c.diagnosis)}</strong></div>`;
        if(c.treatment_plan) html += `<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:#0891b2;min-width:140px;display:inline-block;">Management & Follow Up:</span> ${dqEsc(c.treatment_plan)}</div>`;
        if(c.notes) html += `<div style="padding:5px 0;border-bottom:1px solid var(--gray-100);"><span style="font-weight:600;color:var(--gray-600);min-width:140px;display:inline-block;">Notes:</span> ${dqEsc(c.notes)}</div>`;
        // Show prescription from consultation record (JSON) - External Prescription
        if(c.prescription){
          let rxList=[];
          try{ rxList=JSON.parse(c.prescription); }catch(e){}
          if(rxList.length){
            // Store for printing
            window._extRxItems = rxList.map(rx=>({drug:rx.medication||'',dosage:rx.dosage||'',frequency:rx.frequency||'',duration:rx.duration||'',quantity:''}));
            window._extRxPatient = patientName;
            document.getElementById('btn-print-ext-rx').style.display='inline-block';

            html += `<div style="padding:8px 0;"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <span style="font-weight:600;color:#16a34a;"><i class="fas fa-prescription"></i> External Prescription</span>
              <button onclick="printExternalRx()" style="padding:4px 10px;background:#16a34a;color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;font-weight:500;"><i class="fas fa-print"></i> Print PDF</button>
            </div>
              <table style="width:100%;border-collapse:collapse;font-size:12px;background:white;border-radius:4px;">
                <thead><tr style="background:#f0fdf4;border-bottom:1px solid #bbf7d0;">
                  <th style="padding:5px 8px;text-align:left;color:#166534;">#</th>
                  <th style="padding:5px 8px;text-align:left;color:#166534;">Medication</th>
                  <th style="padding:5px 8px;text-align:left;color:#166534;">Dosage</th>
                  <th style="padding:5px 8px;text-align:left;color:#166534;">Frequency</th>
                  <th style="padding:5px 8px;text-align:left;color:#166534;">Duration</th>
                </tr></thead><tbody>
                ${rxList.map((rx,i)=>`<tr style="border-bottom:1px solid var(--gray-100);">
                  <td style="padding:4px 8px;">${i+1}</td>
                  <td style="padding:4px 8px;font-weight:500;">${dqEsc(rx.medication||'')}</td>
                  <td style="padding:4px 8px;">${dqEsc(rx.dosage||'—')}</td>
                  <td style="padding:4px 8px;">${dqEsc(rx.frequency||'—')}</td>
                  <td style="padding:4px 8px;">${dqEsc(rx.duration||'—')}</td>
                </tr>`).join('')}
              </tbody></table></div>`;
          }
        }
        html += `<div style="font-size:11px;color:var(--gray-400);margin-top:8px;">Date: ${c.created_at||'—'} | Status: <strong>${c.status||'draft'}</strong></div>`;
        html += `</div></div>`;
      }
    } else {
      html += `<div style="margin-bottom:16px;padding:12px;background:#fef3c7;border-radius:6px;font-size:12px;color:#92400e;">
        <i class="fas fa-info-circle"></i> No consultation data recorded yet.
      </div>`;
    }
  } catch(e){}

  // 1b. Final Diagnosis (from consultation data - highlighted)
  try{
    const r2 = await fetch(getAPIPath(consultAPI+'&action=consultation&visit_id='+visitId));
    const j2 = await r2.json();
    if(j2.success && j2.data && j2.data.length > 0){
      const lastC=j2.data[0];
      window._dqDiag = lastC;
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;display:flex;justify-content:space-between;">
          <span><i class="fas fa-diagnoses" style="color:#7c3aed;"></i> Final Diagnosis</span>
          ${lastC.diagnosis ? '<i class="fas fa-pen" title="Correct (needs admin approval)" style="cursor:pointer;color:#2563eb;" onclick="dqEditDiagnosis()"></i>' : ''}
        </h4>
        <div style="background:${lastC.diagnosis?'#f5f3ff':'#fef3c7'};border:1px solid ${lastC.diagnosis?'#ddd6fe':'#fde68a'};border-radius:6px;padding:12px 14px;font-size:14px;font-weight:600;color:${lastC.diagnosis?'#5b21b6':'#92400e'};">
          ${lastC.diagnosis ? dqEsc(lastC.diagnosis) : '<i class="fas fa-clock"></i> Pending — Not yet diagnosed'}
        </div>
      </div>`;
    } else {
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-diagnoses" style="color:#7c3aed;"></i> Final Diagnosis
        </h4>
        <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:6px;padding:12px 14px;font-size:14px;font-weight:600;color:#92400e;">
          <i class="fas fa-clock"></i> Pending — Consultation not started yet
        </div>
      </div>`;
    }
  }catch(e){}

  // 2. Load prescriptions with items (Emergency drugs from internal pharmacy)
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=pharmacy/prescriptions&visit_id='+visitId));
    const j = await r.json();
    if(j.success && j.data && j.data.prescriptions && j.data.prescriptions.length > 0){
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-pills" style="color:#dc2626;"></i> Emergency Drugs
        </h4>`;
      
      for(const rx of j.data.prescriptions){
        html += `<div style="background:var(--gray-50);border-radius:6px;padding:12px 14px;margin-bottom:8px;border:1px solid var(--gray-200);font-size:12px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span style="font-weight:600;color:var(--primary-color);font-family:monospace;">${dqEsc(rx.prescription_no)}</span>
            <span style="font-size:10px;color:var(--gray-500);">${rx.prescription_date||''}</span>
          </div>`;
        if(rx.diagnosis) html += `<div style="color:var(--gray-600);margin-bottom:8px;"><strong>Diagnosis:</strong> ${dqEsc(rx.diagnosis)}</div>`;
        
        // Load items for this prescription
        try{
          const ir = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=pharmacy/prescriptions&id='+rx.id));
          const ij = await ir.json();
          if(ij.success && ij.data && ij.data.items && ij.data.items.length > 0){
            html += `<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:4px;">
              <thead><tr style="background:white;border-bottom:1px solid var(--gray-200);">
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Drug</th>
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Dosage</th>
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Frequency</th>
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Duration</th>
                <th style="padding:5px 8px;text-align:left;font-weight:600;color:var(--gray-600);">Qty</th>
                <th style="padding:5px 8px;text-align:center;font-weight:600;color:var(--gray-600);">Dest.</th>
              </tr></thead><tbody>`;
            ij.data.items.forEach(item => {
              const drugName = item.name || item.instructions || item.drug_name || ('Item #'+item.item_id);
              const itemDest = item.destination || 'hospital';
              if(itemDest === 'external') window._extRxItems.push({drug:drugName,dosage:item.dosage||'',frequency:item.frequency||'',duration:item.duration||'',quantity:item.quantity||''});
              const destBadge = itemDest==='external'?'<span style="font-size:9px;padding:1px 5px;background:#fef3c7;border-radius:6px;color:#92400e;">External</span>':'<span style="font-size:9px;padding:1px 5px;background:#dbeafe;border-radius:6px;color:#1e40af;">Hospital</span>';
              html += `<tr style="border-bottom:1px solid var(--gray-100);">
                <td style="padding:5px 8px;font-weight:500;color:var(--gray-900);">${dqEsc(drugName)}</td>
                <td style="padding:5px 8px;color:var(--gray-700);">${dqEsc(item.dosage||'—')}</td>
                <td style="padding:5px 8px;color:var(--gray-700);">${dqEsc(item.frequency||'—')}</td>
                <td style="padding:5px 8px;color:var(--gray-700);">${dqEsc(item.duration||'—')}</td>
                <td style="padding:5px 8px;color:var(--gray-700);">${item.quantity||'—'}</td>
                <td style="padding:5px 8px;text-align:center;">${destBadge}</td>
              </tr>`;
            });
            html += `</tbody></table>`;
          }
        } catch(e){}
        
        html += `</div>`;
      }
      html += `</div>`;
    }
  } catch(e){}

  // 3. Load orders (lab, radiology)
  try{
    const r = await fetch(getAPIPath(consultAPI+'&action=orders&visit_id='+visitId));
    const j = await r.json();
    if(j.success && j.data && j.data.length > 0){
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-flask" style="color:#2563eb;"></i> Medical Orders & Results
        </h4>`;
      window._dqOrders = {};
      j.data.forEach(o => {
        window._dqOrders[o.id] = o;
        const typeIcon = o.order_type === 'radiology' ? 'fa-x-ray' : o.order_type === 'medication' ? 'fa-pills' : 'fa-flask';
        const typeColor = o.order_type === 'radiology' ? '#d97706' : o.order_type === 'medication' ? '#16a34a' : '#2563eb';
        const statusBg = o.status === 'completed' ? '#d1fae5' : '#dbeafe';
        const statusColor = o.status === 'completed' ? '#065f46' : '#1e40af';
        html += `<div style="background:var(--gray-50);border-radius:6px;padding:10px 12px;margin-bottom:6px;border:1px solid var(--gray-200);font-size:12px;">
          <div style="display:flex;gap:10px;align-items:center;">
            <i class="fas ${typeIcon}" style="color:${typeColor};width:16px;"></i>
            <div style="flex:1;">
              <div style="font-weight:600;color:var(--gray-900);">${dqEsc(o.description||o.order_type)}</div>
              ${o.details ? '<div style="color:var(--gray-500);font-size:11px;">'+dqEsc(o.details)+'</div>' : ''}
            </div>
            <span style="font-size:10px;padding:2px 6px;border-radius:8px;background:${statusBg};color:${statusColor};">${dqEsc(o.status||'ordered')}</span>
            ${o.result ? '<i class="fas fa-pen" title="Correct (needs admin approval)" style="cursor:pointer;color:#2563eb;" onclick="dqEditOrder(' + o.id + ')"></i>' : ''}
          </div>`;
        // Parse result - check if structured JSON
        if(o.result){
          let parsed=null;
          try{parsed=JSON.parse(o.result);}catch(e){}
          if(parsed && Array.isArray(parsed) && parsed.length){
            html += `<div style="margin-top:8px;padding:8px 10px;background:white;border:1px solid #bbf7d0;border-radius:6px;">
              <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="border-bottom:1px solid var(--gray-200);">
                  <th style="padding:4px 8px;text-align:left;font-weight:600;color:#065f46;">Parameter</th>
                  <th style="padding:4px 8px;text-align:center;font-weight:600;color:#065f46;">Result</th>
                  <th style="padding:4px 8px;text-align:center;font-weight:600;color:#065f46;">Unit</th>
                  <th style="padding:4px 8px;text-align:center;font-weight:600;color:#065f46;">Normal Range</th>
                  <th style="padding:4px 8px;text-align:center;font-weight:600;color:#065f46;">Status</th>
                </tr></thead><tbody>`;
            parsed.forEach(r=>{
              const val=parseFloat(r.value);
              const min=r.normal_min!=null?parseFloat(r.normal_min):null;
              const max=r.normal_max!=null?parseFloat(r.normal_max):null;
              let flag='';
              if(!isNaN(val)&&min!=null&&max!=null){
                if(val<min) flag='<span style="color:#dc2626;font-weight:700;">↓ Low</span>';
                else if(val>max) flag='<span style="color:#dc2626;font-weight:700;">↑ High</span>';
                else flag='<span style="color:#059669;font-weight:600;">Normal</span>';
              }
              html+=`<tr style="border-bottom:1px solid var(--gray-100);">
                <td style="padding:4px 8px;font-weight:500;">${dqEsc(r.test_name||'')}</td>
                <td style="padding:4px 8px;text-align:center;font-weight:700;">${dqEsc(r.value||'—')}</td>
                <td style="padding:4px 8px;text-align:center;color:var(--gray-500);">${dqEsc(r.unit||'')}</td>
                <td style="padding:4px 8px;text-align:center;color:var(--gray-500);">${min!=null&&max!=null?min+' - '+max:'—'}</td>
                <td style="padding:4px 8px;text-align:center;">${flag}</td>
              </tr>`;
            });
            html += `</tbody></table></div>`;
          } else if(parsed && typeof parsed === 'object' && !Array.isArray(parsed)){
            html += `<div style="margin-top:8px;padding:8px 10px;background:white;border:1px solid #bbf7d0;border-radius:4px;font-size:12px;color:var(--gray-800);">${dqFmtReport(parsed) || 'No findings recorded'}</div>`;
          } else {
            html += `<div style="margin-top:8px;padding:8px 10px;background:white;border:1px solid #bbf7d0;border-radius:4px;font-size:12px;white-space:pre-wrap;color:var(--gray-800);"><strong style="color:#065f46;">Result:</strong> ${dqEsc(o.result)}</div>`;
          }
        }
        html += `${o.completed_at ? '<div style="font-size:10px;color:var(--gray-400);margin-top:4px;">Completed: '+dqEsc(o.completed_at)+'</div>' : ''}
        </div>`;
      });
      html += `</div>`;
    }
  } catch(e){}

  // 4. Load procedures for this visit
  let dqAllPaid = false;
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=patients/billing&with_items=1&visit_id='+visitId));
    const j = await r.json();
    dqAllPaid = !!(j.success && j.data && j.data.length > 0 && j.data.every(inv => inv.status === 'paid'));
    if(j.success && j.data && j.data.length > 0){
      let procedures=[];
      for(const inv of j.data){
        try{
          const ir = {json: async()=>({success:true,data:inv})}; // items came with the list (with_items=1)
          const ij = await ir.json();
          if(ij.success && ij.data && ij.data.items){
            ij.data.items.filter(it=>it.category==='procedure').forEach(it=>{
              procedures.push({description:it.description,total:it.total,quantity:it.quantity});
            });
          }
        }catch(e){}
      }
      if(procedures.length){
        html += `<div style="margin-bottom:16px;">
          <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
            <i class="fas fa-syringe" style="color:#d97706;"></i> Procedures
          </h4>
          <div style="background:var(--gray-50);border-radius:6px;border:1px solid var(--gray-200);overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
              <thead><tr style="background:white;border-bottom:1px solid var(--gray-200);">
                <th style="padding:8px 10px;text-align:left;font-weight:600;">Procedure</th>
                <th style="padding:8px 10px;text-align:center;font-weight:600;">Qty</th>
              </tr></thead><tbody>`;
        procedures.forEach(p=>{
          html += `<tr style="border-bottom:1px solid var(--gray-100);">
            <td style="padding:6px 10px;font-weight:500;">${dqEsc(p.description||'—')}</td>
            <td style="padding:6px 10px;text-align:center;">${p.quantity||1}</td>
          </tr>`;
        });
        html += `</tbody></table></div></div>`;
      }
    }
  } catch(e){}

  html += dqProcCardHtml(dqAllPaid);

  // If nothing loaded
  if(html.indexOf('Patient Information') === -1 && html.indexOf('Consultation') === -1 && html.indexOf('Prescriptions') === -1 && html.indexOf('Medical Orders') === -1){
    html += `<div style="text-align:center;padding:20px;color:var(--gray-400);font-size:13px;">
      <i class="fas fa-folder-open" style="font-size:32px;display:block;margin-bottom:8px;"></i>
      No details recorded yet for this visit.
    </div>`;
  }

  // Show print external rx button if there are external items
  if(window._extRxItems.length > 0){
    document.getElementById('btn-print-ext-rx').style.display='inline-block';
  }

  // 5. Load past visits for this patient
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=patients/visits&patient_id='+patientId+'&limit=50'));
    const j = await r.json();
    const allVisits = j.data?.visits || j.data || [];
    const pastVisits = allVisits.filter(v => v.id != visitId);
    if(pastVisits.length){
      html += `<div style="margin-bottom:16px;">
        <h4 style="font-size:13px;font-weight:600;color:var(--gray-700);margin-bottom:8px;border-bottom:1px solid var(--gray-200);padding-bottom:6px;">
          <i class="fas fa-history" style="color:var(--gray-500);"></i> Past Visits (${pastVisits.length})
        </h4>
        <div style="background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-200);overflow:hidden;">
          <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead><tr style="background:white;border-bottom:1px solid var(--gray-200);">
              <th style="padding:8px 10px;text-align:left;">Visit No.</th>
              <th style="padding:8px 10px;text-align:left;">Date</th>
              <th style="padding:8px 10px;text-align:center;">Type</th>
              <th style="padding:8px 10px;text-align:center;">Status</th>
              <th style="padding:8px 10px;text-align:center;">Action</th>
            </tr></thead><tbody>`;
      const stColors={open:'#2563eb',in_progress:'#d97706',completed:'#059669',cancelled:'#dc2626'};
      const stBg={open:'#dbeafe',in_progress:'#fef3c7',completed:'#d1fae5',cancelled:'#fee2e2'};
      pastVisits.forEach(v=>{
        const st=v.status||'open';
        html+=`<tr style="border-bottom:1px solid var(--gray-100);">
          <td style="padding:7px 10px;font-family:monospace;color:var(--primary-color);font-weight:600;">${dqEsc(v.visit_no||'')}</td>
          <td style="padding:7px 10px;color:var(--gray-700);">${dqEsc(v.visit_date||'')}</td>
          <td style="padding:7px 10px;text-align:center;">${dqEsc(v.visit_type||'outpatient')}</td>
          <td style="padding:7px 10px;text-align:center;"><span style="padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;background:${stBg[st]||'#f3f4f6'};color:${stColors[st]||'#555'};">${st}</span></td>
          <td style="padding:7px 10px;text-align:center;">
            <button onclick="dqOpenDetails(${v.id},${patientId},'${dqEsc(v.visit_no||'')}','${dqEsc(patientName)}','')" style="padding:4px 10px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:11px;font-weight:500;cursor:pointer;"><i class="fas fa-eye"></i> View</button>
          </td>
        </tr>`;
      });
      html+=`</tbody></table></div></div>`;
    }
  }catch(e){}

  content.innerHTML = html;
  if(document.getElementById("dq-proc-rows")) dqProcAddRow();
}

async function printExternalRx(){
  let items = window._extRxItems || [];
  let patientName = window._extRxPatient || 'Patient';
  const visitId = window._extRxVisitId || null;
  const visitNo = window._extRxVisitNo || '';

  // 1. Fetch complete rich prescription data if visitId is available
  let rxData = null;
  if(visitId){
    try {
      const rxUrl = window.location.origin + _deptBase + '/api.php?endpoint=patients/visits&action=external_prescription&visit_id=' + encodeURIComponent(visitId);
      const rxRes = await fetch(rxUrl);
      const rxJson = await rxRes.json();
      if(rxJson.success && rxJson.data){
        rxData = rxJson.data;
        if(rxData.items && rxData.items.length) {
          items = rxData.items;
        }
      }
    } catch(e){}
  }

  if(!items.length){ alert('No external prescriptions to print.'); return; }
  
  // 2. Fetch hospital settings for document branding
  let hs = {};
  try{
    const r = await fetch(window.location.origin + _deptBase + '/api.php?endpoint=settings/hospital');
    const j = await r.json();
    if(j.success) hs = j.data || {};
  }catch(e){}
  
  const hospName   = hs.hospital_name || 'Iramiro Clinic';
  const docLogo    = hs.doc_logo || hs.logo || '';
  const hLineColor = hs.header_line_color || '#059669';
  const hLineSize  = parseInt(hs.header_line_size) || 2;
  const hTextColor = hs.header_text_color || '#1e293b';
  const hFontSize  = parseInt(hs.header_font_size) || 13;
  const hLeftText  = hs.header_left || hospName;
  const rawDate    = rxData?.prescription_date || rxData?.visit?.visit_date || '';
  const today      = rawDate ? new Date(rawDate).toLocaleDateString() : new Date().toLocaleDateString();
  const hRightText = (hs.header_right || 'Date: {date}').replace('{date}', today).replace('{ref}', rxData?.visit?.visit_no || visitNo || 'RX');
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
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;z-index:0;text-align:center;"><img src="${hs.watermark_image}" style="width:280px;max-width:85%;display:block;margin:0 auto;"></div>`;
    } else {
      const wmText = hs.watermark_text || hospName;
      wmHtml = `<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(${wmRotation}deg);opacity:${wmOpacity};pointer-events:none;font-size:${hs.watermark_size||'48'}px;color:${hs.watermark_color||'#94a3b8'};font-weight:700;white-space:nowrap;z-index:0;text-transform:uppercase;letter-spacing:2px;">${dqEsc(wmText)}</div>`;
    }
  }

  // Patient info preparation
  const vInfo = rxData?.visit || {};
  const patName = vInfo.patient_name || rxData?.patient_name || patientName || 'Patient';
  const patNo = (vInfo.patient_no || rxData?.patient_no) ? ` <span style="color:#64748b;font-size:12px;font-weight:normal;">(${dqEsc(vInfo.patient_no || rxData?.patient_no)})</span>` : '';
  let doctorStr = (rxData?.doctor_name || '').trim();
  if(doctorStr.toLowerCase().startsWith('dr.') || doctorStr.toLowerCase().startsWith('dr ')){
    doctorStr = doctorStr.substring(3).trim();
  }
  const deptStr = rxData?.department_name ? dqEsc(rxData.department_name) : '';
  const prescriber = (doctorStr ? 'Dr. ' + dqEsc(doctorStr) : '') + (doctorStr && deptStr ? ' &bull; ' : '') + deptStr;
  const insName = vInfo.insurance_name || rxData?.insurance_name || '';
  const memberNo = vInfo.member_number || rxData?.member_number || '';
  const insHtml = (insName || memberNo) 
    ? `<strong>${dqEsc(insName || 'Covered')}</strong>${memberNo ? ` (Card: <span style="font-family:monospace;">${dqEsc(memberNo)}</span>)` : ''}`
    : `<span>Self-Pay</span>`;

  let ageStr = '';
  const dob = vInfo.date_of_birth || rxData?.date_of_birth || vInfo.dob || rxData?.dob;
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
      <td style="padding:8px 12px;font-weight:700;color:#0f172a;border-right:1px solid #e2e8f0;font-size:12.5px;">${dqEsc(it.drug || '—')}</td>
      <td style="padding:8px 12px;color:#334155;border-right:1px solid #e2e8f0;font-size:12px;">${dqEsc(it.dosage || '—')}</td>
      <td style="padding:8px 12px;color:#334155;border-right:1px solid #e2e8f0;font-size:12px;">${dqEsc(it.frequency || '—')}</td>
      <td style="padding:8px 12px;color:#334155;border-right:1px solid #e2e8f0;font-size:12px;">${dqEsc(it.duration || '—')}</td>
      <td style="padding:8px 8px;text-align:center;font-weight:600;color:#0f172a;font-size:12px;">${dqEsc(it.quantity || '—')}</td>
    </tr>
  `).join('');
  
  const wrapper = document.createElement('div');
  wrapper.style.position = 'fixed';
  wrapper.style.left = '-9999px';
  wrapper.style.top = '0';
  wrapper.style.width = '750px';
  wrapper.style.background = '#ffffff';
  wrapper.style.zIndex = '-9999';
  
  wrapper.innerHTML = `
    <div id="ext-rx-printable" style="width:750px;min-width:750px;max-width:750px;min-height:1030px;padding:32px 38px;box-sizing:border-box;background:#ffffff;position:relative;overflow:hidden;font-family:'Times New Roman',serif;color:#0f172a;display:flex;flex-direction:column;justify-content:space-between;">
      ${wmHtml}
      <div style="position:relative;z-index:1;display:flex;flex-direction:column;justify-content:space-between;flex:1;min-height:960px;">
        <!-- Top Section -->
        <div>
          <!-- Header -->
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
                <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Patient Name:</span> &nbsp;<strong style="font-size:13.5px;color:#0f172a;">${dqEsc(patName)}</strong>${patNo}</div>
                <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Visit No:</span> &nbsp;<strong style="font-family:monospace;color:#1e40af;font-size:12px;">${dqEsc(vInfo.visit_no || visitNo || '—')}</strong></div>
                ${(vInfo.gender || rxData?.gender || ageStr) ? `<div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Demographics:</span> &nbsp;${dqEsc(vInfo.gender || rxData?.gender || '—')}${ageStr ? ' &bull; ' + ageStr : ''}</div>` : ''}
                <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Coverage:</span> &nbsp;${insHtml}</div>
              </td>
              <td style="width:48%;padding:10px 14px;vertical-align:top;line-height:1.7;">
                <div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Prescription Date:</span> &nbsp;<strong>${today}</strong></div>
                ${prescriber ? `<div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Prescribed By:</span> &nbsp;<strong>${prescriber}</strong></div>` : ''}
                ${rxData?.diagnosis ? `<div><span style="color:#64748b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Diagnosis:</span> &nbsp;${dqEsc(rxData.diagnosis)}</div>` : ''}
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
            <tbody>${rows}</tbody>
          </table>

          ${rxData?.notes ? `
          <div style="font-size:12px;margin-bottom:18px;padding:8px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:4px;line-height:1.5;">
            <strong style="color:#92400e;">Instructions / Notes:</strong> <span style="color:#78350f;">${dqEsc(rxData.notes)}</span>
          </div>` : ''}
        </div>

        <!-- Bottom Section (Signatures & Footer anchored to bottom) -->
        <div style="page-break-inside:avoid;">
          <!-- Signatures Block -->
          <table style="width:100%;border-collapse:collapse;margin-top:16px;margin-bottom:20px;page-break-inside:avoid;">
            <tr>
              <td style="width:50%;vertical-align:bottom;text-align:left;padding-right:20px;">
                <div style="font-size:11.5px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Prescribing Physician:</div>
                 ${window._departmentSignature ? '<img src="'+window._departmentSignature+'" style="max-height:30px;max-width:190px;display:block;margin-bottom:2px;">' : '<div style="width:210px;border-bottom:1.5px dashed #475569;height:32px;margin-bottom:4px;"></div>'}
                <div style="font-size:12px;font-weight:700;color:#0f172a;">${doctorStr ? 'Dr. ' + dqEsc(doctorStr) : "Doctor's Signature &amp; Stamp"}</div>
                <div style="font-size:11px;color:#64748b;margin-top:2px;">Date: ${today}</div>
              </td>
              <td style="width:50%;vertical-align:bottom;text-align:right;padding-left:20px;">
                <div style="display:inline-block;text-align:left;">
                  <div style="font-size:11.5px;font-weight:700;color:#334155;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">Dispensing Pharmacist:</div>
                   ${window._departmentStamp ? '<img src="'+window._departmentStamp+'" style="max-height:42px;max-width:90px;display:block;margin-bottom:2px;">' : '<div style="width:210px;border-bottom:1.5px dashed #475569;height:32px;margin-bottom:4px;"></div>'}
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
    </div>`;

  document.body.appendChild(wrapper);

  const cleanPatientName = patName.replace(/[^a-zA-Z0-9 ]/g, '').replace(/\s+/g,'_');
  const vNoStr = (vInfo.visit_no || visitNo || '').replace(/[^a-zA-Z0-9\-]/g, '_');
  const filename = 'External_Prescription_' + cleanPatientName + (vNoStr ? '_' + vNoStr : '') + '.pdf';

  const opt = {
    margin: [8, 10, 8, 10], // mm
    filename: filename,
    image: {type:'jpeg',quality:0.98},
    html2canvas: {
      scale: 2,
      useCORS: true,
      logging: false,
      width: 750,
      windowWidth: 750
    },
    jsPDF: {unit:'mm',format:'a4',orientation:'portrait'},
    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
  };

  html2pdf().set(opt).from(wrapper.firstElementChild).save().then(()=>{
    if(wrapper.parentNode) wrapper.parentNode.removeChild(wrapper);
  }).catch(err=>{
    console.error('PDF error:', err);
    if(wrapper.parentNode) wrapper.parentNode.removeChild(wrapper);
  });
}

function dqQuickAction(visitId, patientId, visitNo, patientName, queueNo, action){
  dqCurrentVisitId = visitId;
  dqCurrentPatient = {visitId, patientId, visitNo, patientName, queueNo};
  
  if(action === 'prescription'){
    rxOpenModal();
  } else if(action === 'lab'){
    showLabModal('lab');
  } else if(action === 'radiology'){
    showLabModal('radiology');
  } else if(action === 'referral'){
    showModal('referral');
  } else if(action === 'admission'){
    showModal('admission');
  }
}

// ============================================================
// LAB/RADIOLOGY ORDER SYSTEM
// ============================================================
let labServices = [];

async function labLoadServices(){
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=patients/billing&action=services&category=lab,radiology&visit_id='+dqCurrentVisitId));
    const j = await r.json();
    if(j.success && j.data) labServices = j.data;
  } catch(e){ console.error('Failed to load lab services:', e); }
}

async function showLabModal(type){
  if(!dqCurrentPatient) return alert('Select a patient first.');
  document.getElementById('mo-lab-type').value = type;
  document.getElementById('modal-labOrder-title').innerHTML = 
    type === 'lab' ? '<i class="fas fa-flask" style="color:#2563eb;"></i> Lab Test Order' : '<i class="fas fa-x-ray" style="color:#d97706;"></i> Radiology Order';
  document.getElementById('lab-patient-info').textContent = dqCurrentPatient.patientName + ' (' + dqCurrentPatient.visitNo + ')';
  
  // Get insurance info from visit
  window._labInsCopay = 100;
  window._labInsId = null;
  try{
    const vr = await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId));
    const vj = await vr.json();
    if(vj.success && vj.data && (vj.data.company_id || vj.data.insurance_id) && (vj.data.payment_type==='insurance'||vj.data.payment_type==='mixed')){
      window._labInsId = vj.data.company_id || vj.data.insurance_id;
      window._labInsCopay = parseFloat(vj.data.copayment_percentage) || 100;
      document.getElementById('lab-ins-toggle').style.display = 'block';
      document.getElementById('lab-apply-insurance').checked = true;
      // Fetch services with patient's agreed insurance prices
      try{
        const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=patients/billing&action=services&category=lab,radiology&visit_id='+dqCurrentVisitId+'&insurance_id='+window._labInsId));
        const j = await r.json();
        if(j.success && j.data) labServices = j.data;
      }catch(e){}
    } else {
      document.getElementById('lab-ins-toggle').style.display = 'none';
      document.getElementById('lab-apply-insurance').checked = false;
      await labLoadServices();
    }
  }catch(e){}
  
  labLoadTests();
  showModal('labOrder');
}

function labLoadTests(){
  const type = document.getElementById('mo-lab-type').value;
  const container = document.getElementById('lab-tests-list');
  const labSpecialities = ['bacteriology','biochemistry','hematology','immuno-serology','parasitology','molecular','anatomical pathology','blood transfusion'];
  const filtered = labServices.filter(s => {
    const cat = (s.category||'').toLowerCase();
    if(type === 'lab') return labSpecialities.some(ls => cat.includes(ls));
    if(type === 'radiology') return cat.includes('imaging');
    return cat.includes(type);
  });
  
  if(!filtered.length){
    container.innerHTML = '<div style="padding:20px;text-align:center;color:var(--gray-400);font-size:12px;">No tests available for this category.</div>';
    return;
  }
  
  container.innerHTML = filtered.map(s => `
    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--gray-50);cursor:pointer;" onmouseover="this.style.background='var(--gray-50)'" onmouseout="this.style.background='white'">
      <input type="checkbox" class="lab-test-check" value="${s.id}" data-name="${dqEsc(s.name)}" data-price="${s.unit_price||0}" onchange="labUpdateSummary()" style="accent-color:var(--primary-color);cursor:pointer;">
      <div style="flex:1;">
        <div style="font-size:13px;font-weight:500;color:var(--gray-900);">${dqEsc(s.name)}</div>
        <div style="font-size:11px;color:var(--gray-500);">${dqEsc(s.code||'')}</div>
      </div>
      <span style="font-size:13px;font-weight:600;color:#16a34a;">${parseFloat(s.unit_price||0).toLocaleString()} RWF</span>
    </label>
  `).join('');
}

function labUpdateSummary(){
  const checks = document.querySelectorAll('.lab-test-check:checked');
  const summaryDiv = document.getElementById('lab-selected-summary');
  const listDiv = document.getElementById('lab-selected-list');
  const insCheck = document.getElementById('lab-apply-insurance');
  const insInfo = document.getElementById('lab-ins-info');
  
  if(!checks.length){
    summaryDiv.style.display = 'none';
    insInfo.style.display = 'none';
    return;
  }
  
  let total = 0;
  let items = [];
  checks.forEach(cb => {
    const name = cb.getAttribute('data-name');
    const price = parseFloat(cb.getAttribute('data-price'));
    total += price;
    items.push(name + ' (' + price.toLocaleString() + ')');
  });
  
  listDiv.innerHTML = items.map(i => '• ' + i).join('<br>');
  
  if(insCheck.checked && window._labInsCopay < 100){
    const copay = window._labInsCopay || 15;
    const coverage = 100 - copay;
    const insAmount = Math.round(total * coverage / 100);
    const patientAmount = total - insAmount;
    document.getElementById('lab-total-price').textContent = total.toLocaleString() + ' RWF';
    insInfo.style.display = 'block';
    insInfo.innerHTML = 'Insurance covers '+coverage+'%: <strong>'+insAmount.toLocaleString()+'</strong> | Patient pays '+copay+'%: <strong>'+patientAmount.toLocaleString()+' RWF</strong>';
  } else {
    document.getElementById('lab-total-price').textContent = total.toLocaleString() + ' RWF';
    insInfo.style.display = 'none';
  }
  summaryDiv.style.display = 'block';
}

async function labSubmitOrder(){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  
  const checks = document.querySelectorAll('.lab-test-check:checked');
  if(!checks.length) return alert('Please select at least one test.');
  
  const type = document.getElementById('mo-lab-type').value;
  const notes = document.getElementById('mo-lab-details').value.trim();
  
  const selectedTests = [];
  checks.forEach(cb => {
    selectedTests.push({
      service_id: parseInt(cb.value),
      name: cb.getAttribute('data-name'),
      price: parseFloat(cb.getAttribute('data-price'))
    });
  });
  
  try{
    const applyIns = document.getElementById('lab-apply-insurance')?.checked && window._labInsCopay < 100;
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=patients/billing&action=lab_order'), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        visit_id: dqCurrentVisitId,
        patient_id: dqCurrentPatient.patientId,
        order_type: type,
        notes: notes,
        tests: selectedTests,
        ordered_by: loggedUserId,
        insurance_id: applyIns ? window._labInsId : null,
        copayment_percentage: applyIns ? window._labInsCopay : 100
      })
    });
    const j = await r.json();
    if(j.success){
      hideModal('labOrder');
      alert('✓ '+type.charAt(0).toUpperCase()+type.slice(1)+' order sent! Invoice: '+(j.data?.invoice_no||'Created'));
      loadOrders(dqCurrentVisitId);
      // Reset
      document.querySelectorAll('.lab-test-check:checked').forEach(cb => cb.checked = false);
      labUpdateSummary();
      document.getElementById('mo-lab-details').value = '';
    } else {
      alert('Error: '+j.message);
    }
  } catch(e){ alert('Failed: '+e.message); }
}

// ============================================================
// 1. QUEUE MANAGEMENT
// ============================================================
// Tab switching
function dqSwitchTab(tab){
  const waitingPanel = document.getElementById('dq-panel-waiting');
  const consultPanel = document.getElementById('dq-panel-consulting');
  const completedPanel = document.getElementById('dq-panel-completed');
  const referralsPanel = document.getElementById('dq-panel-referrals');
  const waitingTab = document.getElementById('dq-tab-waiting');
  const consultTab = document.getElementById('dq-tab-consulting');
  const completedTab = document.getElementById('dq-tab-completed');
  const referralsTab = document.getElementById('dq-tab-referrals');
  const labPanel = document.getElementById('dq-panel-labpending');
  const labTab = document.getElementById('dq-tab-labpending');

  // Hide all
  waitingPanel.style.display='none'; consultPanel.style.display='none'; completedPanel.style.display='none'; referralsPanel.style.display='none'; labPanel.style.display='none';
  labTab.style.background='var(--gray-100)'; labTab.style.color='var(--gray-600)';
  waitingTab.style.background='var(--gray-100)'; waitingTab.style.color='var(--gray-600)';
  consultTab.style.background='var(--gray-100)'; consultTab.style.color='var(--gray-600)';
  completedTab.style.background='var(--gray-100)'; completedTab.style.color='var(--gray-600)';
  referralsTab.style.background='var(--gray-100)'; referralsTab.style.color='var(--gray-600)';
  
  if(tab === 'waiting'){
    waitingPanel.style.display='block';
    waitingTab.style.background='var(--primary-color)'; waitingTab.style.color='white';
  } else if(tab === 'consulting'){
    consultPanel.style.display='block';
    consultTab.style.background='var(--primary-color)'; consultTab.style.color='white';
  } else if(tab === 'labpending'){
    labPanel.style.display='block';
    labTab.style.background='#d97706'; labTab.style.color='white';
    dqLoadLabPending();
  } else if(tab === 'completed'){
    completedPanel.style.display='block';
    completedTab.style.background='var(--primary-color)'; completedTab.style.color='white';
    dqLoadCompleted();
  } else if(tab === 'referrals'){
    referralsPanel.style.display='block';
    referralsTab.style.background='#7c3aed'; referralsTab.style.color='white';
    dqLoadReferrals();
  }
}

async function dqLoadLabPending(){
  const tb = document.getElementById('dq-labpending-tbody');
  if(!deptId && !deptAll) return;
  try{
    const j = await (await fetch(visitsAPI+'&action=lab_pending&department_id='+((deptAll && (window._isAdminUser || !ownDeptId))?'all':(ownDeptId||deptId)))).json();
    const list = j.success ? (j.data||[]) : [];
    document.getElementById('dq-tab-labpending-count').textContent = list.length;
    if(!list.length){
      tb.innerHTML = '<tr><td colspan="7" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:36px;display:block;margin-bottom:8px;color:var(--success-color);"></i>No pending lab results.</td></tr>';
      return;
    }
    tb.innerHTML = list.map((v,i) => `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:10px 12px;font-weight:700;color:var(--gray-700);">${i+1}</td>
      <td style="padding:10px 12px;"><div style="font-weight:600;">${dqEsc(v.patient_name||'Unknown')}</div><div style="font-size:12px;color:var(--gray-500);">${dqEsc(v.patient_no||'')}</div></td>
      <td style="padding:10px 12px;font-family:monospace;font-size:13px;color:var(--primary-color);">${dqEsc(v.visit_no)}</td>
      <td style="padding:10px 12px;font-size:13px;"><span style="padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#fef3c7;color:#92400e;">${parseInt(v.pending_count)} pending</span> ${dqEsc(v.tests||'')}</td>
      <td style="padding:10px 12px;font-size:13px;">${dqEsc(v.department_name||'—')}</td>
      <td style="padding:10px 12px;font-size:13px;color:var(--gray-500);">${v.ordered_at ? new Date(v.ordered_at.replace(' ','T')).toLocaleString([],{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}) : '—'}</td>
      <td style="padding:10px 12px;text-align:center;"><button onclick="window.location.href='consultation.php?visit_id=${v.id}&patient_id=${v.patient_id}'" style="padding:5px 10px;background:var(--success-color);color:white;border:none;border-radius:4px;font-size:11px;font-weight:500;cursor:pointer;"><i class="fas fa-clipboard-list"></i> Open</button></td>
    </tr>`).join('');
    dqFilterTab('labpending');
  }catch(e){ tb.innerHTML = '<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">Failed to load.</td></tr>'; }
}

async function dqLoad(){
  dqLoadLabPending();
  if(!deptId && !deptAll) {
    document.getElementById('dq-tbody').innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-exclamation-triangle" style="font-size:36px;display:block;margin-bottom:8px;"></i>No department assigned.</td></tr>';
    return;
  }
  try{
    // Build URL with date range params
    let queueUrl = visitsAPI+'&action=department_queue&department_id='+(deptAll?'all':deptId);
    const wDateFrom = document.getElementById('dq-waiting-date-from')?.value || '';
    const wDateTo = document.getElementById('dq-waiting-date-to')?.value || '';
    if(wDateFrom) queueUrl += '&date_from='+wDateFrom;
    if(wDateTo) queueUrl += '&date_to='+wDateTo;
    const r=await fetch(queueUrl);
    const j=await r.json();
    if(!j.success){ document.getElementById('dq-tbody').innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);">Error: '+dqEsc(j.message)+'</td></tr>'; return; }
    const {waiting, in_consultation, done, current_serving, stats} = j.data;
    [].concat(waiting||[], in_consultation||[], done||[]).forEach(v => { if(v.department_id) dqVisitDept[v.id] = parseInt(v.department_id); });

    document.getElementById('dq-waiting').textContent = stats?.waiting || 0;
    document.getElementById('dq-consulting').textContent = stats?.in_consultation || 0;
    document.getElementById('dq-done').textContent = stats?.done_today || 0;
    document.getElementById('dq-current').textContent = current_serving ? ('#'+current_serving.queue_no) : '—';

    // Update tab counts
    document.getElementById('dq-tab-waiting-count').textContent = (waiting||[]).length;
    document.getElementById('dq-tab-consult-count').textContent = (in_consultation||[]).length;
    document.getElementById('dq-tab-completed-count').textContent = (done||[]).length;

    const badge = document.getElementById('deptQueueBadge');
    if(badge) badge.textContent = stats?.waiting || 0;

    // === WAITING TAB ===
    const tb = document.getElementById('dq-tbody');
    const waitingList = waiting || [];
    dqExportData.waiting = waitingList;
    const dsel = document.getElementById('dq-dept-filter'), dcur = dsel.value;
    dsel.innerHTML = '<option value="">All</option>' + [...new Set([...dqAllDepts, ...waitingList.map(v => v.department_name)].filter(Boolean))].sort().map(n => `<option value="${dqEsc(n)}">${dqEsc(n)}</option>`).join('');
    dsel.value = dcur;
    // first render: default the table filter to the user's own department
    if (!dqDeptDefaulted && ownDeptName) { dqDeptDefaulted = true; if ([...dsel.options].some(o => o.value === ownDeptName)) dsel.value = ownDeptName; }
    if(!waitingList.length){
      tb.innerHTML = '<tr><td colspan="6" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-check-circle" style="font-size:36px;display:block;margin-bottom:8px;color:var(--success-color);"></i>No patients waiting. All clear!</td></tr>';
    } else {
      tb.innerHTML = waitingList.map((v, i) => {
        return `<tr style="border-bottom:1px solid var(--gray-100);">
          <td class="dq-no" style="padding:10px 12px;font-weight:700;font-size:16px;color:var(--gray-900);">${i+1}</td>
          <td style="padding:10px 12px;">
            <div style="font-weight:600;">${dqEsc(v.patient_name||'Unknown')}</div>
            <div style="font-size:12px;color:var(--gray-500);">${dqEsc(v.patient_no||'')}</div>
            ${dqRenderInsuranceBadge(v)}
            ${parseInt(v.vitals_count) ? '<span style="font-size:11px;color:#059669;font-weight:600;"><i class="fas fa-heartbeat"></i> Vitals recorded</span>' : '<span style="font-size:11px;color:#dc2626;font-weight:600;"><i class="fas fa-exclamation-circle"></i> No vital signs yet</span>'}
          </td>
          <td style="padding:10px 12px;font-family:monospace;font-size:13px;color:var(--primary-color);">${dqEsc(v.visit_no)}</td>
          <td style="padding:10px 12px;font-size:13px;font-weight:500;">${dqEsc(v.department_name||'—')}</td>
          <td style="padding:10px 12px;"><span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;background:#fef3c7;color:#92400e;">Waiting</span></td>
          <td style="padding:10px 12px;text-align:center;">
            <button onclick="dqStartConsult(${v.id},${v.patient_id})" style="padding:6px 14px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;">
              <i class="fas fa-user-md"></i> Consult
            </button>
          </td>
        </tr>`;
      }).join('');
    }
    dqFilterTab('waiting');

    // === IN CONSULTATION TAB ===
    const ctb = document.getElementById('dq-consult-tbody');
    const consultList = in_consultation || [];
    // active consultations always show, whatever the date
    document.getElementById('dq-tab-consult-count').textContent = consultList.length;
    dqExportData.consulting = consultList;
    if(!consultList.length){
      ctb.innerHTML = '<tr><td colspan="6" style="padding:60px;text-align:center;color:var(--gray-400);"><i class="fas fa-inbox" style="font-size:36px;display:block;margin-bottom:8px;"></i>No patients in consultation right now.</td></tr>';
    } else {
      ctb.innerHTML = consultList.map((v, i) => {
        const isCurrent = (current_serving && v.id === current_serving.id) || v.consultation_status === 'now_serving';
        const startedAt = v.consultation_started_at || v.queued_at;
        return `<tr style="border-bottom:1px solid var(--gray-100);${isCurrent?'background:#f0fdf4;':''}">
          <td class="dq-no" style="padding:10px 12px;font-weight:700;color:var(--gray-700);">${i+1}</td>
          <td style="padding:10px 12px;">
            <div style="font-weight:600;">${dqEsc(v.patient_name||'Unknown')}</div>
            <div style="font-size:12px;color:var(--gray-500);">${dqEsc(v.patient_no||'')}</div>
            ${dqRenderInsuranceBadge(v)}
            ${parseInt(v.vitals_count) ? '<span style="font-size:11px;color:#059669;font-weight:600;"><i class="fas fa-heartbeat"></i> Vitals recorded</span>' : '<span style="font-size:11px;color:#dc2626;font-weight:600;"><i class="fas fa-exclamation-circle"></i> No vital signs yet</span>'}
          </td>
          <td style="padding:10px 12px;font-family:monospace;font-size:13px;color:var(--primary-color);">${dqEsc(v.visit_no)}</td>
          <td style="padding:10px 12px;font-size:13px;font-weight:500;">${dqEsc(v.doctor_name||'—')}</td>
          <td style="padding:10px 12px;font-size:13px;">${dqEsc(v.department_name||'—')}</td>
          <td style="padding:10px 12px;font-size:13px;color:var(--gray-500);">${startedAt ? new Date(startedAt).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}) : '—'}</td>
          <td style="padding:10px 12px;text-align:center;">
            <div style="display:flex;gap:4px;align-items:center;justify-content:center;">
              <button onclick="window.location.href='consultation.php?visit_id=${v.id}&patient_id=${v.patient_id}'" style="padding:5px 10px;background:var(--success-color);color:white;border:none;border-radius:4px;font-size:11px;font-weight:500;cursor:pointer;" title="Continue Consultation">
                <i class="fas fa-clipboard-list"></i> Continue
              </button>
              <button onclick="dqOpenDetails(${v.id}, ${v.patient_id}, '${dqEsc(v.visit_no)}', '${dqEsc(v.patient_name||'')}', '${dqEsc(v.queue_no||'')}')" style="padding:5px 10px;background:#6b21a8;color:white;border:none;border-radius:4px;font-size:11px;font-weight:500;cursor:pointer;" title="View Details">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </td>
        </tr>`;
      }).join('');
    }
    dqFillDeptSelects();
    dqFilterTab('consulting');
  } catch(e){ document.getElementById('dq-tbody').innerHTML = '<tr><td colspan="6" style="padding:40px;text-align:center;color:var(--gray-400);">Error: '+e.message+'</td></tr>'; }
}

async function dqCallNext(){
  const callDept = ownDeptId;
  if(!callDept) return alert('No department assigned.');
  const btn = document.getElementById('dqCallNextBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calling...';
  try{
    const r = await fetch(getAPIPath(visitsAPI+'&action=call_next&department_id='+callDept), {method:'POST'});
    const j = await r.json();
    if(j.success && j.data){
      // Navigate to consultation page
      window.location.href = 'consultation.php?visit_id=' + j.data.id + '&patient_id=' + j.data.patient_id;
    } else { alert(j.message || 'No waiting patients.'); }
  } catch(e){ alert('Failed: '+e.message); }
  finally{ btn.disabled=false; btn.innerHTML='<i class="fas fa-forward"></i> Call Next'; }
}

// ============================================================
// 2. SELECT PATIENT — Open form modal
// ============================================================
async function dqSelectPatient(visitId, patientId, visitNo, patientName, queueNo, openModal){
  dqCurrentVisitId = visitId;
  dqCurrentPatient = {visitId, patientId, visitNo, patientName, queueNo};

  // Show actions panel
  document.getElementById('dq-actions').style.display = 'block';
  document.getElementById('dq-action-patient').textContent = patientName+' ('+visitNo+')';

  // Only open modal if explicitly requested (Consult button click)
  if(openModal){
    document.getElementById('mf-patient-name').textContent = dqEsc(patientName);
    document.getElementById('mf-patient-id').textContent = '#'+dqEsc(patientId);
    document.getElementById('mf-queue-no').textContent = '#'+dqEsc(queueNo);
    document.getElementById('modal-departmentForm').style.display = 'flex';
    await mfLoadDepartmentForms();
    await mfLoadPreviousFormData();
  }

  // Load existing orders for this visit
  loadOrders(visitId);
}

// ============================================================
// 3. CONSULTATION SAVE
// ============================================================
async function saveConsultation(status){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  const data = {
    visit_id: dqCurrentVisitId,
    patient_id: dqCurrentPatient.patientId,
    department_id: deptId,
    doctor_id: loggedUserId,
    doctor_name: loggedUserName,
    bp_systolic: document.getElementById('c-bp-sys').value || null,
    bp_diastolic: document.getElementById('c-bp-dia').value || null,
    pulse: document.getElementById('c-pulse').value || null,
    temperature: document.getElementById('c-temp').value || null,
    weight: document.getElementById('c-weight').value || null,
    oxygen_sat: document.getElementById('c-o2').value || null,
    chief_complaint: document.getElementById('c-complaint').value,
    history: document.getElementById('c-history').value,
    examination: document.getElementById('c-exam').value,
    diagnosis: document.getElementById('c-diagnosis').value,
    treatment_plan: document.getElementById('c-treatment').value,
    status: status,
    created_by: loggedUserId
  };

  try{
    const r = await fetch(getAPIPath(consultAPI), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(data)
    });
    const j = await r.json();
    if(j.success){
      dqCurrentConsultId = j.data?.id || dqCurrentConsultId;
      
      // Submit department form if one is selected
      const formPayload = dqCollectFormData();
      if(formPayload && formPayload.template_id){
        try{
          await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=departments/department-forms&action=submit'), {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({
              visit_id: dqCurrentVisitId,
              patient_id: dqCurrentPatient.patientId,
              template_id: formPayload.template_id,
              department_id: deptId,
              filled_by: loggedUserId,
              form_data: formPayload.data,
              status: status === 'completed' ? 'completed' : 'draft'
            })
          });
        } catch(fe){ console.error('Form submission failed:', fe); }
      }
      
      alert('Consultation '+(status === 'completed' ? 'completed' : 'draft saved')+'!');
      if(status === 'completed') {
        // Mark visit as done
        await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId), {
          method:'PUT',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify({consultation_status:'done', status:'completed'})
        });
        dqCurrentVisitId = null; dqCurrentPatient = null;
        document.getElementById('dq-consult-panel').innerHTML = `
          <div style="text-align:center;padding:30px 20px;">
            <div style="font-size:48px;color:var(--success-color);margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
            <h4 style="font-size:16px;font-weight:600;color:var(--gray-600);">Consultation Completed</h4>
            <p style="font-size:13px;color:var(--gray-400);margin-top:4px;">Patient has been marked as done.</p>
          </div>`;
        document.getElementById('dq-actions').style.display = 'none';
        document.getElementById('dq-consult-badge').textContent = 'No patient';
        dqLoad();
      }
    } else { alert('Error: '+j.message); }
  } catch(e){ alert('Failed: '+e.message); }
}

// ============================================================
// 4. MEDICAL ORDERS (Lab, Radiology, Prescription)
// ============================================================
// Auto-save a draft consultation if none exists (for orders to work)
async function ensureConsultation() {
  if (dqCurrentConsultId) return dqCurrentConsultId;
  if (!dqCurrentVisitId || !dqCurrentPatient || !deptId) return null;
  
  // Check again via API
  try {
    const r = await fetch(getAPIPath(consultAPI+'&visit_id='+dqCurrentVisitId+'&department_id='+deptId));
    const j = await r.json();
    if (j.success && j.data && j.data.id) {
      dqCurrentConsultId = j.data.id;
      return dqCurrentConsultId;
    }
  } catch(e) {}

  // Create a minimal draft consultation
  try {
    const data = {
      visit_id: dqCurrentVisitId,
      patient_id: dqCurrentPatient.patientId,
      department_id: deptId,
      doctor_id: loggedUserId,
      doctor_name: loggedUserName,
      status: 'draft',
      created_by: loggedUserId
    };
    const r = await fetch(getAPIPath(consultAPI), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(data)
    });
    const j = await r.json();
    if (j.success && j.data) {
      dqCurrentConsultId = j.data.id;
      return dqCurrentConsultId;
    }
  } catch(e) {}
  return null;
}

function buildOrderPayload(orderType, description, details){
  return {
    consultation_id: dqCurrentConsultId,
    visit_id: dqCurrentVisitId,
    patient_id: dqCurrentPatient.patientId,
    order_type: orderType,
    description: description,
    details: details,
    ordered_by: loggedUserId
  };
}

async function submitMedicalOrder(){
  const type = document.getElementById('mo-lab-type').value;
  const desc = document.getElementById('mo-lab-desc').value.trim();
  const details = document.getElementById('mo-lab-details').value.trim();
  if(!desc) return alert('Please enter the test name/description.');

  // Ensure consultation exists first
  const cid = await ensureConsultation();
  if (!cid) return alert('Could not create consultation. Please save a draft first.');

  const btn = document.getElementById('btn-mo-lab');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
  try{
    const r = await fetch(getAPIPath(consultAPI+'&action=order'), {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(buildOrderPayload(type, desc, details))
    });
    const j = await r.json();
    if(j.success){ alert('Order sent!'); hideModal('labOrder'); document.getElementById('mo-lab-desc').value=''; document.getElementById('mo-lab-details').value=''; loadOrders(dqCurrentVisitId); }
    else { alert('Error: '+j.message); }
  } catch(e){ alert('Failed: '+e.message); }
  finally{ btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Order'; }
}

// --- Prescription: search medications from items table ---
let rxMedications = [];

async function rxLoadMedications(){
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=pharmacy/items&is_medication=1&limit=500'));
    const j = await r.json();
    if(j.success && j.data) rxMedications = j.data;
  } catch(e){ console.error('Failed to load medications:', e); }
}

function rxOpenModal(){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('Select a patient first.');
  document.getElementById('rx-patient-info').textContent = dqCurrentPatient.patientName;
  document.getElementById('rx-visit-info').textContent = dqCurrentPatient.visitNo;
  document.getElementById('rx-diagnosis').value = '';
  document.getElementById('rx-notes').value = '';
  document.getElementById('rx-drugs-tbody').innerHTML = '';
  rxAddRow(); // Add first row
  showModal('prescription');
}

function rxAddRow(){
  const tbody = document.getElementById('rx-drugs-tbody');
  const idx = tbody.children.length;
  const row = document.createElement('tr');
  row.style.borderTop = '1px solid var(--gray-100)';
  row.innerHTML = `
    <td style="padding:6px;">
      <input type="text" id="rx-drug-${idx}" placeholder="Search drug..." list="rx-med-list" oninput="rxSearchDrug(this)" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;" data-item-id="">
    </td>
    <td style="padding:6px;">
      <input type="text" id="rx-dosage-${idx}" placeholder="e.g. 500mg" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
    </td>
    <td style="padding:6px;">
      <select id="rx-freq-${idx}" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:11px;">
        <option value="OD">OD (Once daily)</option>
        <option value="BD">BD (Twice daily)</option>
        <option value="TDS">TDS (3x daily)</option>
        <option value="QDS">QDS (4x daily)</option>
        <option value="STAT">STAT</option>
        <option value="PRN">PRN (As needed)</option>
        <option value="Nocte">Nocte (At night)</option>
      </select>
    </td>
    <td style="padding:6px;">
      <input type="text" id="rx-dur-${idx}" placeholder="7 days" style="width:100%;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
    </td>
    <td style="padding:6px;">
      <input type="number" id="rx-qty-${idx}" placeholder="Qty" min="1" style="width:60px;padding:6px;border:1px solid var(--gray-200);border-radius:4px;font-size:12px;">
    </td>
    <td style="padding:6px;text-align:center;">
      <button onclick="this.closest('tr').remove()" type="button" style="border:none;background:none;color:var(--danger-color);cursor:pointer;font-size:14px;" title="Remove">
        <i class="fas fa-trash"></i>
      </button>
    </td>
  `;
  tbody.appendChild(row);
}

function rxSearchDrug(input){
  const val = input.value.toLowerCase();
  const datalist = document.getElementById('rx-med-list');
  if(!datalist){
    const dl = document.createElement('datalist');
    dl.id = 'rx-med-list';
    document.body.appendChild(dl);
  }
  const dl = document.getElementById('rx-med-list');
  if(val.length < 2){ dl.innerHTML = ''; return; }
  const matches = rxMedications.filter(m => m.name.toLowerCase().includes(val) || (m.generic_name && m.generic_name.toLowerCase().includes(val)));
  dl.innerHTML = matches.slice(0,20).map(m => `<option value="${dqEsc(m.name)}" data-id="${m.id}">${dqEsc(m.generic_name||'')}</option>`).join('');
  // Try to match item_id
  const exact = rxMedications.find(m => m.name.toLowerCase() === val);
  if(exact) input.setAttribute('data-item-id', exact.id);
  else input.setAttribute('data-item-id', '');
}

function rxCollectDrugs(){
  const tbody = document.getElementById('rx-drugs-tbody');
  const rows = tbody.querySelectorAll('tr');
  const drugs = [];
  rows.forEach((row, idx) => {
    const drugInput = document.getElementById('rx-drug-'+idx);
    if(!drugInput) return;
    const name = drugInput.value.trim();
    if(!name) return;
    const itemId = drugInput.getAttribute('data-item-id') || null;
    drugs.push({
      item_id: itemId ? parseInt(itemId) : null,
      drug_name: name,
      dosage: document.getElementById('rx-dosage-'+idx)?.value || '',
      frequency: document.getElementById('rx-freq-'+idx)?.value || '',
      duration: document.getElementById('rx-dur-'+idx)?.value || '',
      quantity: parseFloat(document.getElementById('rx-qty-'+idx)?.value) || 0
    });
  });
  return drugs;
}

async function rxSubmitPrescription(andPrint){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  const drugs = rxCollectDrugs();
  if(!drugs.length) return alert('Please add at least one drug.');
  
  const destination = document.querySelector('input[name="rx-destination"]:checked')?.value || 'hospital';
  const diagnosis = document.getElementById('rx-diagnosis').value.trim();
  const notes = document.getElementById('rx-notes').value.trim();
  
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=pharmacy/prescriptions'), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        patient_id: dqCurrentPatient.patientId,
        visit_id: dqCurrentVisitId,
        doctor_id: loggedUserId,
        diagnosis: diagnosis,
        notes: notes,
        destination: destination,
        items: drugs
      })
    });
    const j = await r.json();
    if(j.success){
      hideModal('prescription');
      if(andPrint && j.data?.id){
        rxPrintPrescription(j.data.id);
      }
      alert('✓ Prescription submitted' + (destination === 'hospital' ? ' to Hospital Pharmacy!' : ' (External). Print ready.'));
      loadOrders(dqCurrentVisitId);
    } else {
      alert('Error: ' + j.message);
    }
  } catch(e){
    alert('Failed: ' + e.message);
  }
}

function rxSubmitAndPrint(){
  rxSubmitPrescription(true);
}

function rxPrintPrescription(prescriptionId){
  const printWin = window.open('', '_blank', 'width=800,height=600');
  printWin.document.write(`<html><head><title>Prescription</title><style>
    body{font-family:Arial,sans-serif;padding:30px;font-size:14px;}
    h2{margin:0;font-size:20px;} .hospital{text-align:center;margin-bottom:20px;border-bottom:2px solid #333;padding-bottom:10px;}
    table{width:100%;border-collapse:collapse;margin-top:15px;} th,td{border:1px solid #ddd;padding:8px;text-align:left;font-size:13px;}
    th{background:#f5f5f5;font-weight:600;} .footer{margin-top:30px;display:flex;justify-content:space-between;}
    @media print{button{display:none;}}
  </style></head><body>
    <div class="hospital"><h2>Hospital Prescription</h2><p>Date: ${new Date().toLocaleDateString()}</p></div>
    <p><strong>Patient:</strong> ${dqEsc(dqCurrentPatient.patientName)} | <strong>Visit:</strong> ${dqEsc(dqCurrentPatient.visitNo)}</p>
    <p><strong>Diagnosis:</strong> ${dqEsc(document.getElementById('rx-diagnosis').value||'—')}</p>
    <table><thead><tr><th>#</th><th>Drug</th><th>Dosage</th><th>Frequency</th><th>Duration</th><th>Qty</th></tr></thead><tbody>`);
  
  const drugs = rxCollectDrugs();
  drugs.forEach((d, i) => {
    printWin.document.write(`<tr><td>${i+1}</td><td>${dqEsc(d.drug_name)}</td><td>${dqEsc(d.dosage)}</td><td>${dqEsc(d.frequency)}</td><td>${dqEsc(d.duration)}</td><td>${d.quantity||'—'}</td></tr>`);
  });
  
  printWin.document.write(`</tbody></table>
    <p style="margin-top:15px;"><strong>Notes:</strong> ${dqEsc(document.getElementById('rx-notes').value||'—')}</p>
    <div class="footer"><div><p>___________________</p><p>Doctor Signature</p></div><div><p>___________________</p><p>Pharmacist</p></div></div>
    <br><button onclick="window.print()">Print</button>
  </body></html>`);
  printWin.document.close();
}

// ============================================================
// 5. REFERRAL
// ============================================================
async function submitReferral(){
  const dept = document.getElementById('mo-ref-dept').value;
  const notes = document.getElementById('mo-ref-notes').value.trim();
  if(!dept) return alert('Please select a department.');

  // Ensure consultation exists first
  const cid = await ensureConsultation();
  if (!cid) return alert('Could not create consultation. Please save a draft first.');

  const btn = document.getElementById('btn-mo-ref');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
  try{
    // First create a medical order of type referral
    const r1 = await fetch(getAPIPath(consultAPI+'&action=order'), {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify(buildOrderPayload('referral', 'Referred to department #'+dept, notes))
    });
    const j1 = await r1.json();

    // Then send to department via visits endpoint
    const r2 = await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId), {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({action:'send_to_department', department_id: parseInt(dept), notes: notes, user_id: loggedUserId})
    });
    const j2 = await r2.json();

    if(j2.success){
      alert('Patient referred successfully!');
      hideModal('referral');
      dqCurrentVisitId = null; dqCurrentPatient = null;
      document.getElementById('dq-consult-panel').innerHTML = `
        <div style="text-align:center;padding:30px 20px;">
          <div style="font-size:48px;color:var(--success-color);margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
          <h4 style="font-size:16px;font-weight:600;color:var(--gray-600);">Patient Referred</h4>
          <p style="font-size:13px;color:var(--gray-400);margin-top:4px;">Patient has been sent to the selected department.</p>
        </div>`;
      document.getElementById('dq-actions').style.display = 'none';
      dqLoad();
    } else { alert('Error: '+(j2.message||'Failed to send')); }
  } catch(e){ alert('Failed: '+e.message); }
  finally{ btn.disabled=false; btn.innerHTML='<i class="fas fa-share"></i> Send Referral'; }
}

// ============================================================
// 6. ADMISSION
// ============================================================
// --- ADMISSION REQUEST (simple - no bed assignment here) ---
async function admitSubmit(){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  const diagnosis = document.getElementById('admit-diagnosis').value.trim();
  if(!diagnosis) return alert('Please enter admission reason.');
  
  const admitType = document.getElementById('admit-type').value;
  const wardPref = document.getElementById('admit-ward-pref').value;
  
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=ward/ward-management&action=request_admission'), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        patient_id: dqCurrentPatient.patientId,
        visit_id: dqCurrentVisitId,
        diagnosis: diagnosis,
        admission_type: admitType,
        ward_preference: wardPref,
        requested_by: loggedUserId
      })
    });
    const j = await r.json();
    if(j.success){
      alert('✓ Admission request sent! Ward staff will assign a bed.');
      hideModal('admission');
      dqLoad();
    } else {
      alert('Error: '+j.message);
    }
  } catch(e){ alert('Failed: '+e.message); }
}

// ============================================================
// 7. LOAD ORDERS FOR CURRENT VISIT
// ============================================================
async function loadOrders(visitId){
  const container = document.getElementById('dq-order-list');
  container.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading orders...';
  try{
    // Get from consultation API
    const r = await fetch(getAPIPath(consultAPI+'&visit_id='+visitId));
    const j = await r.json();
    if(j.success && j.data && j.data.orders && j.data.orders.length){
      const typeIcons = {lab:'flask', radiology:'x-ray', medication:'prescription', referral:'share', procedure:'procedures'};
      container.innerHTML = j.data.orders.map(o => `
        <div style="padding:8px 10px;border:1px solid var(--gray-100);border-radius:6px;margin-bottom:6px;background:var(--gray-50);">
          <div style="display:flex;align-items:center;gap:6px;">
            <i class="fas fa-${typeIcons[o.order_type]||'file'}" style="color:var(--primary-color);width:16px;"></i>
            <span style="font-weight:600;font-size:13px;">${dqEsc(o.description)}</span>
            <span style="margin-left:auto;font-size:11px;padding:2px 8px;border-radius:12px;background:${o.status==='completed'?'#d1fae5':o.status==='in_progress'?'#dbeafe':'#fef3c7'};color:${o.status==='completed'?'#065f46':o.status==='in_progress'?'#1e40af':'#92400e'};">
              ${o.status||'ordered'}
            </span>
          </div>
          ${o.details ? '<div style="font-size:12px;color:var(--gray-500);margin-top:3px;padding-left:22px;">'+dqEsc(o.details)+'</div>' : ''}
        </div>
      `).join('');
    } else {
      container.innerHTML = '<p style="color:var(--gray-400);font-size:13px;">No orders yet. Use the buttons above to place orders.</p>';
    }
  } catch(e){
    container.innerHTML = '<p style="color:var(--gray-400);font-size:13px;">Could not load orders.</p>';
  }
}

// ============================================================
// 8. MARK DONE (simple discharge without admit)
// ============================================================
async function dqMarkDone(){
  if(!dqCurrentVisitId) return;
  if(!confirm('Mark this patient as done?')) return;
  try{
    const r = await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId), {
      method:'PUT',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({consultation_status:'done', status:'completed'})
    });
    const j = await r.json();
    if(j.success){
      dqCurrentVisitId = null; dqCurrentPatient = null;
      document.getElementById('dq-consult-panel').innerHTML = `
        <div style="text-align:center;padding:30px 20px;">
          <div style="font-size:48px;color:var(--success-color);margin-bottom:12px;"><i class="fas fa-check-circle"></i></div>
          <h4 style="font-size:16px;font-weight:600;color:var(--gray-600);">Patient Completed</h4>
          <p style="font-size:13px;color:var(--gray-400);">Click Call Next for the next patient.</p>
        </div>`;
      document.getElementById('dq-actions').style.display = 'none';
      document.getElementById('dq-consult-badge').textContent = 'No patient';
      dqLoad();
    } else { alert('Error: '+j.message); }
  } catch(e){ alert('Failed: '+e.message); }
}

// ============================================================
// 9. HIDE FORM MODAL
// ============================================================
function hideFormModal(){
  document.getElementById('modal-departmentForm').style.display = 'none';
  document.getElementById('mf-form-select').value = '';
  document.getElementById('mf-form-fields').innerHTML = '';
  document.getElementById('mf-form-fields').style.display = 'none';
}

// ============================================================
// 10. LOAD DEPARTMENT FORMS FOR MODAL
// ============================================================
async function mfLoadDepartmentForms(){
  if(!deptId) return;
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=departments/department-forms&action=templates&department_id='+deptId));
    const j = await r.json();
    if(j.success && j.data && j.data.length > 0){
      const sel = document.getElementById('mf-form-select');
      sel.innerHTML = '<option value="">— Select Form —</option>';
      j.data.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.id;
        opt.textContent = f.name;
        sel.appendChild(opt);
      });
    }
  } catch(e){
    console.error('Failed to load department forms:', e);
  }
}

// ============================================================
// 11. LOAD FORM FIELDS FOR MODAL
// ============================================================
async function mfLoadFormFields(){
  const templateId = document.getElementById('mf-form-select').value;
  const fieldsDiv = document.getElementById('mf-form-fields');
  
  if(!templateId){
    fieldsDiv.style.display = 'none';
    return;
  }

  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=departments/department-forms&action=templates&id='+templateId));
    const j = await r.json();
    
    if(j.success && j.data){
      const fields = j.data.fields || [];
      mfRenderFormFields(fields);
      fieldsDiv.style.display = 'block';
    }
  } catch(e){
    console.error('Failed to load form fields:', e);
    alert('Error loading form fields');
  }
}

// ============================================================
// 12. RENDER FORM FIELDS
// ============================================================
function mfRenderFormFields(fields){
  const fieldsDiv = document.getElementById('mf-form-fields');
  let html = '';
  
  fields.forEach((f, idx) => {
    const fieldId = 'mf-form-field-'+idx;
    const required = f.is_required ? ' <span style="color:var(--danger-color);">*</span>' : '';
    const baseStyle = 'width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:4px;font-size:13px;';
    
    if(f.field_type === 'section_header'){
      html += `<div style="font-weight:600;color:var(--gray-700);margin-top:12px;margin-bottom:8px;border-bottom:2px solid var(--primary-color);padding-bottom:4px;">${dqEsc(f.field_label)}</div>`;
    } else if(f.field_type === 'info_text'){
      html += `<div style="background:var(--blue-50);border-left:4px solid var(--primary-color);padding:8px;border-radius:4px;margin-bottom:8px;font-size:12px;color:var(--gray-600);">${dqEsc(f.field_label)}</div>`;
    } else {
      html += `<div style="margin-bottom:10px;">`;
      if(f.field_type !== 'checkbox' && f.field_type !== 'radio'){
        html += `<label style="display:block;font-size:12px;font-weight:500;color:var(--gray-700);margin-bottom:4px;">${dqEsc(f.field_label)}${required}</label>`;
      }
      
      if(f.field_type === 'text'){
        html += `<input type="text" id="${fieldId}" placeholder="${dqEsc(f.placeholder||'')}" data-key="${dqEsc(f.field_key)}" style="${baseStyle}">`;
      } else if(f.field_type === 'textarea'){
        html += `<textarea id="${fieldId}" placeholder="${dqEsc(f.placeholder||'')}" rows="3" data-key="${dqEsc(f.field_key)}" style="${baseStyle}resize:vertical;"></textarea>`;
      } else if(f.field_type === 'number'){
        html += `<input type="number" id="${fieldId}" placeholder="${dqEsc(f.placeholder||'')}" data-key="${dqEsc(f.field_key)}" step="0.01" style="${baseStyle}">`;
      } else if(f.field_type === 'date'){
        html += `<input type="date" id="${fieldId}" data-key="${dqEsc(f.field_key)}" style="${baseStyle}">`;
      } else if(f.field_type === 'datetime'){
        html += `<input type="datetime-local" id="${fieldId}" data-key="${dqEsc(f.field_key)}" style="${baseStyle}">`;
      } else if(f.field_type === 'select'){
        let opts = '<option value="">Select...</option>';
        if(f.field_options){
          try {
            const optsArr = typeof f.field_options === 'string' ? JSON.parse(f.field_options) : f.field_options;
            optsArr.forEach(o => {
              opts += `<option value="${dqEsc(o)}">${dqEsc(o)}</option>`;
            });
          } catch(e){}
        }
        html += `<select id="${fieldId}" data-key="${dqEsc(f.field_key)}" style="${baseStyle}">${opts}</select>`;
      } else if(f.field_type === 'multi_select'){
        let opts = '';
        if(f.field_options){
          try {
            const optsArr = typeof f.field_options === 'string' ? JSON.parse(f.field_options) : f.field_options;
            optsArr.forEach(o => {
              opts += `<option value="${dqEsc(o)}">${dqEsc(o)}</option>`;
            });
          } catch(e){}
        }
        html += `<select id="${fieldId}" data-key="${dqEsc(f.field_key)}" multiple style="${baseStyle}height:80px;">${opts}</select>`;
      } else if(f.field_type === 'checkbox'){
        html += `<label style="display:flex;align-items:center;font-size:13px;color:var(--gray-700);cursor:pointer;"><input type="checkbox" id="${fieldId}" data-key="${dqEsc(f.field_key)}" style="margin-right:8px;cursor:pointer;"> ${dqEsc(f.field_label)}${required}</label>`;
      } else if(f.field_type === 'radio'){
        html += `<div style="display:flex;flex-direction:column;gap:6px;">`;
        if(f.field_options){
          try {
            const optsArr = typeof f.field_options === 'string' ? JSON.parse(f.field_options) : f.field_options;
            optsArr.forEach((o, i) => {
              const radioId = fieldId + '-' + i;
              html += `<label style="display:flex;align-items:center;font-size:13px;color:var(--gray-700);cursor:pointer;"><input type="radio" id="${radioId}" name="${fieldId}" value="${dqEsc(o)}" data-key="${dqEsc(f.field_key)}" style="margin-right:8px;cursor:pointer;"> ${dqEsc(o)}</label>`;
            });
          } catch(e){}
        }
        html += `</div>`;
      }
      
      html += `</div>`;
    }
  });
  
  fieldsDiv.innerHTML = html;
}

// ============================================================
// 13. COLLECT FORM DATA
// ============================================================
function mfCollectFormData(){
  const templateId = document.getElementById('mf-form-select').value;
  if(!templateId) return null;
  
  const formData = {};
  const inputs = document.getElementById('mf-form-fields').querySelectorAll('[data-key]');
  
  inputs.forEach(inp => {
    const key = inp.getAttribute('data-key');
    let value = null;
    
    if(inp.type === 'checkbox'){
      value = inp.checked;
    } else if(inp.tagName === 'SELECT' && inp.multiple){
      value = Array.from(inp.selectedOptions).map(o => o.value);
    } else if(inp.name && inp.type === 'radio'){
      const checked = document.querySelector('input[name="'+inp.name+'"]:checked');
      if(checked) value = checked.value;
    } else {
      value = inp.value || null;
    }
    
    formData[key] = value;
  });
  
  return {
    template_id: templateId,
    data: formData
  };
}

// ============================================================
// 14. SAVE FORM & MARK AS NOW SERVING
// ============================================================
async function mfSaveForm(){
  if(!dqCurrentVisitId || !dqCurrentPatient) return alert('No patient selected.');
  
  const formPayload = mfCollectFormData();
  if(!formPayload || !formPayload.template_id) return alert('Please select a form.');
  
  try{
    // 1. Save form data
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=departments/department-forms&action=submit'), {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        visit_id: dqCurrentVisitId,
        patient_id: dqCurrentPatient.patientId,
        template_id: formPayload.template_id,
        department_id: deptId,
        filled_by: loggedUserId,
        form_data: formPayload.data,
        status: 'completed'
      })
    });
    const j = await r.json();
    if(j.success){
      // 2. Update visit consultation_status to now_serving
      await fetch(getAPIPath(visitsAPI+'&id='+dqCurrentVisitId), {
        method:'PUT',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({consultation_status:'now_serving'})
      });
      
      // 3. Close modal
      hideFormModal();
      
      alert('✓ Form saved! Patient is now being served.');
      
      // 5. Reload queue to show updated status in table
      await dqLoad();
    } else {
      alert('Error saving form: ' + j.message);
    }
  } catch(e){
    alert('Failed to save form: ' + e.message);
    console.error('Form save error:', e);
  }
}

// ============================================================
// 15. LOAD PREVIOUSLY FILLED FORM DATA
// ============================================================
async function mfLoadPreviousFormData(){
  if(!dqCurrentVisitId) return;
  
  try{
    const r = await fetch(getAPIPath(window.location.origin + _deptBase + '/api.php?endpoint=departments/department-forms&action=submitted&visit_id='+dqCurrentVisitId));
    const j = await r.json();
    
    if(j.success && j.data && j.data.length > 0){
      // Get the first submitted form for this visit
      const submittedForm = j.data[0];
      
      // Pre-select the form
      document.getElementById('mf-form-select').value = submittedForm.template_id;
      
      // Load and render the form fields
      await mfLoadFormFields();
      
      // Populate with saved data
      setTimeout(() => {
        if(submittedForm.form_data && typeof submittedForm.form_data === 'string'){
          try {
            var formData = JSON.parse(submittedForm.form_data);
          } catch(e) {
            formData = submittedForm.form_data;
          }
        } else {
          formData = submittedForm.form_data || {};
        }
        
        // Fill in the saved values
        const inputs = document.getElementById('mf-form-fields').querySelectorAll('[data-key]');
        inputs.forEach(inp => {
          const key = inp.getAttribute('data-key');
          const value = formData[key];
          
          if(value !== undefined && value !== null){
            if(inp.type === 'checkbox'){
              inp.checked = Boolean(value);
            } else if(inp.tagName === 'SELECT' && inp.multiple){
              if(Array.isArray(value)){
                Array.from(inp.options).forEach(opt => {
                  opt.selected = value.includes(opt.value);
                });
              }
            } else if(inp.type === 'radio'){
              const radioInputs = document.querySelectorAll('input[name="'+inp.name+'"]');
              radioInputs.forEach(radio => {
                radio.checked = (radio.value === value);
              });
            } else {
              inp.value = value;
            }
          }
        });
      }, 100);
      
      // Show read-only message
      const fieldsDiv = document.getElementById('mf-form-fields');
      if(fieldsDiv && !fieldsDiv.querySelector('.form-submitted-notice')){
        const notice = document.createElement('div');
        notice.className = 'form-submitted-notice';
        notice.style.cssText = 'background:#dbeafe;border-left:4px solid #2563eb;padding:10px;border-radius:4px;margin-bottom:12px;font-size:12px;color:#1e40af;';
        notice.innerHTML = '<i class="fas fa-info-circle"></i> Form previously filled on this visit. Showing saved data.';
        fieldsDiv.parentNode.insertBefore(notice, fieldsDiv);
      }
    }
  } catch(e){
    console.error('Failed to load previous form data:', e);
  }
}

// ============================================================
// INIT — boot via API (like patients.php)
// ============================================================
bootDepartment();
</script>
<?php
require_once __DIR__ . '/includes/footer.php';
?>
