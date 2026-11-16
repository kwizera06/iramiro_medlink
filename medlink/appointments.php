<?php
// public/appointments.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'scheduling';

// Page title mapping
$subModuleTitles = [
    'online-booking' => 'Online Booking',
    'scheduling' => 'Appointment Scheduling',
    'queue' => 'Queue Management',
    'reminder' => 'Appointment Reminders',
    'telemedicine' => 'Telemedicine'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'Appointments';
?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'online-booking' => 'Manage online appointment bookings from website and mobile app',
                'scheduling' => 'Schedule and manage doctor appointments',
                'queue' => 'Real-time queue management and patient flow',
                'reminder' => 'Send appointment reminders via SMS, WhatsApp, Email',
                'telemedicine' => 'Virtual consultations and online appointments'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Manage appointments';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <button onclick="window.location.href='appointments.php?module=appointment&sub=scheduling'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> New Appointment
        </button>
        <button onclick="window.location.href='appointments.php?module=appointment&sub=queue'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-list-ol"></i> View Queue
        </button>
    </div>
</div>

<!-- Sub-Module Content -->
<?php if ($subModule == 'online-booking'): ?>

<!-- ============================================
ONLINE BOOKING - EMPTY DATA
============================================ -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Total Online Bookings</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Today's Bookings</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Pending Confirmation</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--secondary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">This Week</div>
        </div>
    </div>

    <!-- Filters -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <input type="text" placeholder="Search by patient name or booking ID..." style="flex: 1; min-width: 200px; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        <select style="padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
            <option value="">All Status</option>
            <option value="confirmed">Confirmed</option>
            <option value="pending">Pending</option>
            <option value="cancelled">Cancelled</option>
            <option value="completed">Completed</option>
        </select>
        <input type="date" style="padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        <button style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-search"></i> Search
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-globe"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Online Bookings</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Online bookings will appear here once patients start booking.</p>
        <button onclick="window.location.href='appointments.php?module=appointment&sub=scheduling'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Create Appointment
        </button>
    </div>
</div>

<?php elseif ($subModule == 'scheduling'): ?>

<!-- ============================================
APPOINTMENT SCHEDULING - EMPTY DATA
============================================ -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Left Column - Schedule Form -->
    <div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
            <i class="fas fa-calendar-plus" style="color: var(--primary-color);"></i> Schedule Appointment
        </h3>
        <form id="ap-form">
            <div style="display: grid; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Patient <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input id="ap-psearch" type="text" placeholder="Type name, patient no or phone to search..." autocomplete="off" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; margin-bottom: 6px;">
                    <select id="ap-patient" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                        <option value="">Search, then select patient</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Doctor <span style="color: var(--danger-color);">*</span>
                    </label>
                    <select id="ap-doctor" required style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                        <option value="">Select Doctor</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">Department</label>
                    <select id="ap-dept" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                        <option value="">Select Department</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Date <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input id="ap-date" type="date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Time <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input id="ap-time" type="time" step="300" required value="09:00" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Reason <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input id="ap-reason" type="text" required placeholder="e.g. Review of lab results" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Notes
                    </label>
                    <textarea id="ap-notes" rows="2" placeholder="Enter any special notes or requirements" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                </div>
                <div id="ap-msg" style="display:none; font-size: 13px; padding: 8px 12px; border-radius: 8px;"></div>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-check"></i> Schedule Appointment
                    </button>
                    <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </form>
        <script>
        (function(){
          const base = (window.appBasePath ?? (location.pathname.substring(0, location.pathname.lastIndexOf('/')) || '')),
                api = ep => location.origin + base + '/api.php?endpoint=' + ep,
                $ = id => document.getElementById(id),
                opt = (v, t) => { const o = document.createElement('option'); o.value = v; o.textContent = t; return o; };
          const fill = (sel, rows, label) => rows.forEach(r => sel.appendChild(opt(r.id, label(r))));
          fetch(api('appointments&action=doctors')).then(r => r.json()).then(j => fill($('ap-doctor'), j.data || [], d => d.name));
          fetch(api('hr/departments')).then(r => r.json()).then(j => fill($('ap-dept'), j.data || [], d => d.name));
          let t;
          $('ap-psearch').addEventListener('input', e => {
            clearTimeout(t);
            t = setTimeout(async () => {
              const q = e.target.value.trim(), sel = $('ap-patient');
              sel.innerHTML = '';
              if (q.length < 2) { sel.appendChild(opt('', 'Search, then select patient')); return; }
              const j = await (await fetch(api('patients/patients&limit=20&search=' + encodeURIComponent(q)))).json();
              const rows = (j.data && j.data.patients) || [];
              sel.appendChild(opt('', rows.length ? 'Select patient (' + rows.length + ' found)' : 'No patient found'));
              fill(sel, rows, p => p.first_name + ' ' + p.last_name + ' · ' + p.patient_no + (p.phone ? ' · ' + p.phone : ''));
              if (rows.length === 1) sel.value = rows[0].id;
            }, 300);
          });
          $('ap-form').addEventListener('submit', async e => {
            e.preventDefault();
            const m = $('ap-msg'), show = (ok, txt) => { m.style.display = 'block'; m.style.background = ok ? '#d1fae5' : '#fee2e2'; m.style.color = ok ? '#047857' : '#b91c1c'; m.textContent = txt; };
            try {
              const j = await (await fetch(api('appointments'), { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({
                patient_id: $('ap-patient').value, doctor_id: $('ap-doctor').value, department_id: $('ap-dept').value,
                appointment_date: $('ap-date').value, appointment_time: $('ap-time').value,
                reason: $('ap-reason').value.trim(), instructions: $('ap-notes').value.trim() }) })).json();
              show(j.success, j.message);
              if (j.success) e.target.reset();
            } catch (x) { show(false, '
    </div>

    <!-- Right Column - Empty Schedule -->
    <div>
        <!-- Today's Schedule - Empty -->
        <div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">
                    <i class="fas fa-calendar-day" style="color: var(--success-color);"></i> Today's Schedule
                </h3>
                <span style="font-size: 14px; color: var(--gray-500);"><?php echo date('l, F j, Y'); ?></span>
            </div>
            <div style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 48px; color: var(--gray-300); margin-bottom: 12px;">
                    <i class="fas fa-calendar"></i>
                </div>
                <p style="color: var(--gray-500);">No appointments scheduled for today</p>
                <p style="font-size: 12px; color: var(--gray-400);">Schedule an appointment to get started</p>
            </div>
        </div>

        <!-- Double Booking Prevention - Empty -->
        <div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px;">
                <i class="fas fa-ban" style="color: var(--danger-color);"></i> Double Booking Prevention
            </h3>
            <div style="text-align: center; padding: 20px;">
                <p style="color: var(--gray-500); font-size: 13px;">No bookings scheduled. All time slots are available.</p>
            </div>
        </div>
    </div>
</div>

<?php elseif ($subModule == 'queue'): ?>

<!-- ============================================
QUEUE MANAGEMENT - EMPTY DATA
============================================ -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900);">Queue Management</h3>
            <p style="color: var(--gray-500); font-size: 14px;">Real-time patient queue and flow management</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <button style="padding: 10px 20px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-user-plus"></i> Call Next
            </button>
            <button style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Queue Stats - Empty -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 28px; font-weight: 700; color: var(--primary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Waiting</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 28px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">In Progress</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 28px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Missed</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 28px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Completed Today</div>
        </div>
    </div>

    <!-- Empty Queue -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-list-ol"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">Queue is Empty</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">No patients currently in the queue.</p>
        <button onclick="window.location.href='appointments.php?module=appointment&sub=scheduling'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Schedule Appointment
        </button>
    </div>
</div>

<?php elseif ($subModule == 'reminder'): ?>

<!-- ============================================
APPOINTMENT REMINDERS - EMPTY DATA
============================================ -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900);">Appointment Reminders</h3>
            <p style="color: var(--gray-500); font-size: 14px;">Send automated reminders via SMS, WhatsApp, Email, and Voice Call</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <button style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-send"></i> Send Now
            </button>
            <button style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-cog"></i> Settings
            </button>
        </div>
    </div>

    <!-- Reminder Stats - Empty -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Sent Today</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Pending</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">0%</div>
            <div style="font-size: 13px; color: var(--gray-500);">Success Rate</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--danger-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Failed</div>
        </div>
    </div>

    <!-- Reminder Channels - Empty -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; border: 2px solid var(--gray-200); border-radius: 8px; text-align: center;">
            <div style="font-size: 32px; color: #25D366;">
                <i class="fab fa-whatsapp"></i>
            </div>
            <div style="font-weight: 500; margin-top: 8px;">WhatsApp</div>
            <div style="font-size: 13px; color: var(--gray-500);">0 messages</div>
            <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px;">Active</span>
        </div>
        <div style="padding: 16px; border: 2px solid var(--gray-200); border-radius: 8px; text-align: center;">
            <div style="font-size: 32px; color: #34B7F1;">
                <i class="fas fa-sms"></i>
            </div>
            <div style="font-weight: 500; margin-top: 8px;">SMS</div>
            <div style="font-size: 13px; color: var(--gray-500);">0 messages</div>
            <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px;">Active</span>
        </div>
        <div style="padding: 16px; border: 2px solid var(--gray-200); border-radius: 8px; text-align: center;">
            <div style="font-size: 32px; color: #EA4335;">
                <i class="fas fa-envelope"></i>
            </div>
            <div style="font-weight: 500; margin-top: 8px;">Email</div>
            <div style="font-size: 13px; color: var(--gray-500);">0 emails</div>
            <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 12px;">Active</span>
        </div>
        <div style="padding: 16px; border: 2px solid var(--gray-200); border-radius: 8px; text-align: center;">
            <div style="font-size: 32px; color: #4CAF50;">
                <i class="fas fa-phone-volume"></i>
            </div>
            <div style="font-weight: 500; margin-top: 8px;">Voice Call</div>
            <div style="font-size: 13px; color: var(--gray-500);">0 calls</div>
            <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 12px;">Inactive</span>
        </div>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 40px 20px;">
        <div style="font-size: 48px; color: var(--gray-300); margin-bottom: 12px;">
            <i class="fas fa-bell"></i>
        </div>
        <p style="color: var(--gray-500);">No reminders sent yet</p>
        <p style="font-size: 12px; color: var(--gray-400);">Send appointment reminders to get started</p>
    </div>
</div>

<?php elseif ($subModule == 'telemedicine'): ?>

<!-- ============================================
TELEMEDICINE - EMPTY DATA
============================================ -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
    <!-- Left Column - Empty Telemedicine List -->
    <div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">
                <i class="fas fa-video" style="color: var(--primary-color);"></i> Virtual Consultations
            </h3>
            <button style="padding: 8px 16px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                <i class="fas fa-plus"></i> New Call
            </button>
        </div>

        <div style="text-align: center; padding: 60px 20px;">
            <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
                <i class="fas fa-video-slash"></i>
            </div>
            <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Virtual Consultations</h3>
            <p style="color: var(--gray-400); font-size: 14px;">Start a telemedicine consultation with your patients.</p>
            <button style="margin-top: 16px; padding: 10px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-plus"></i> Start New Call
            </button>
        </div>

        <!-- Virtual Waiting Room - Empty -->
        <div style="margin-top: 20px; padding: 16px; background: #ede9fe; border-radius: 8px; border: 1px solid #c4b5fd;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-users" style="color: #7c3aed; font-size: 20px;"></i>
                <div>
                    <div style="font-weight: 600; color: var(--gray-900);">Virtual Waiting Room</div>
                    <div style="font-size: 13px; color: var(--gray-600);">No patients waiting in virtual queue</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column - Empty Stats -->
    <div style="display: flex; flex-direction: column; gap: 24px;">
        <!-- Telemedicine Stats - Empty -->
        <div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px;">
                <i class="fas fa-chart-bar" style="color: var(--success-color);"></i> Telemedicine Stats
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div style="padding: 12px; background: var(--gray-50); border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">0</div>
                    <div style="font-size: 13px; color: var(--gray-500);">Total Consultations</div>
                </div>
                <div style="padding: 12px; background: var(--gray-50); border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
                    <div style="font-size: 13px; color: var(--gray-500);">Today</div>
                </div>
                <div style="padding: 12px; background: var(--gray-50); border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
                    <div style="font-size: 13px; color: var(--gray-500);">Avg Rating</div>
                </div>
                <div style="padding: 12px; background: var(--gray-50); border-radius: 8px; text-align: center;">
                    <div style="font-size: 24px; font-weight: 700; color: var(--secondary-color);">0%</div>
                    <div style="font-size: 13px; color: var(--gray-500);">Satisfaction Rate</div>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px;">
                <i class="fas fa-list" style="color: var(--primary-color);"></i> Telemedicine Features
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: var(--gray-50); border-radius: 6px;">
                    <i class="fas fa-video" style="color: var(--success-color);"></i>
                    <span style="font-size: 13px;">HD Video Calls</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: var(--gray-50); border-radius: 6px;">
                    <i class="fas fa-credit-card" style="color: var(--primary-color);"></i>
                    <span style="font-size: 13px;">Online Payment</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: var(--gray-50); border-radius: 6px;">
                    <i class="fas fa-prescription" style="color: var(--warning-color);"></i>
                    <span style="font-size: 13px;">e-Prescription</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: var(--gray-50); border-radius: 6px;">
                    <i class="fas fa-clock" style="color: var(--secondary-color);"></i>
                    <span style="font-size: 13px;">Virtual Waiting Room</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: var(--gray-50); border-radius: 6px;">
                    <i class="fas fa-shield-alt" style="color: var(--danger-color);"></i>
                    <span style="font-size: 13px;">End-to-End Encryption</span>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: var(--gray-50); border-radius: 6px;">
                    <i class="fas fa-file-medical" style="color: #7c3aed;"></i>
                    <span style="font-size: 13px;">Medical Records Access</span>
                </div>
            </div>
        </div>

        <!-- Quick Start -->
        <div style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); border-radius: var(--radius); padding: 24px; color: white;">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">
                <i class="fas fa-rocket"></i> Start Telemedicine
            </h3>
            <p style="font-size: 14px; opacity: 0.9; margin-bottom: 16px;">
                Begin a virtual consultation with your patients instantly.
            </p>
            <button style="padding: 10px 24px; background: white; color: var(--primary-color); border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-video"></i> Start New Call
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>