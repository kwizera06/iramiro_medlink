<?php
// department_portal.php - Department Portal: Dashboard, Queue, Forms, Reports
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header with Tab Navigation -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:16px;">
    <div>
        <h2 style="font-size:24px;font-weight:600;color:var(--gray-900);">
            <i class="fas fa-chart-line" style="color:var(--primary-color);"></i>
            <span id="portal-title">Department Portal</span>
        </h2>
        <p id="portal-subtitle" style="color:var(--gray-500);font-size:14px;margin-top:4px;">
            Dashboard & Management
        </p>
    </div>
    <div style="display:flex;gap:8px;">
        <button onclick="exportReport()" style="padding:10px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:8px;font-weight:500;cursor:pointer;">
            <i class="fas fa-download"></i> Export
        </button>
        <button onclick="refreshData()" style="padding:10px 16px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:500;cursor:pointer;">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- TAB NAVIGATION -->
<div style="display:flex;gap:12px;border-bottom:2px solid var(--gray-200);margin-bottom:24px;overflow-x:auto;">
    <button onclick="switchTab('dashboard')" class="portal-tab active" style="padding:12px 20px;border:none;background:none;font-weight:600;color:var(--primary-color);cursor:pointer;border-bottom:3px solid var(--primary-color);margin-bottom:-2px;">
        <i class="fas fa-gauge-high"></i> Dashboard
    </button>
    <button onclick="switchTab('queue')" class="portal-tab" style="padding:12px 20px;border:none;background:none;font-weight:600;color:var(--gray-500);cursor:pointer;">
        <i class="fas fa-list-ol"></i> Queue
    </button>
    <button onclick="switchTab('forms')" class="portal-tab" style="padding:12px 20px;border:none;background:none;font-weight:600;color:var(--gray-500);cursor:pointer;">
        <i class="fas fa-clipboard"></i> Forms
    </button>
    <button onclick="switchTab('reports')" class="portal-tab" style="padding:12px 20px;border:none;background:none;font-weight:600;color:var(--gray-500);cursor:pointer;">
        <i class="fas fa-chart-bar"></i> Reports
    </button>
</div>

<!-- ============================================
     DASHBOARD TAB
     ============================================ -->
<div id="tab-dashboard" class="portal-section" style="display:block;">
    
    <!-- Stats Cards Row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
        
        <!-- Waiting Patients -->
        <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border-left:4px solid var(--warning-color);">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <p style="color:var(--gray-500);font-size:13px;font-weight:500;margin-bottom:8px;">Waiting</p>
                    <p id="stat-waiting" style="font-size:32px;font-weight:700;color:var(--gray-900);">—</p>
                    <p style="color:var(--warning-color);font-size:12px;margin-top:6px;">
                        <i class="fas fa-arrow-up"></i> <span id="stat-waiting-trend">0</span>% change
                    </p>
                </div>
                <div style="width:48px;height:48px;background:#fef3c7;border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--warning-color);font-size:24px;">
                    <i class="fas fa-hourglass-end"></i>
                </div>
            </div>
        </div>

        <!-- In Consultation -->
        <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border-left:4px solid var(--primary-color);">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <p style="color:var(--gray-500);font-size:13px;font-weight:500;margin-bottom:8px;">In Consultation</p>
                    <p id="stat-consulting" style="font-size:32px;font-weight:700;color:var(--gray-900);">—</p>
                    <p style="color:var(--primary-color);font-size:12px;margin-top:6px;">
                        <span id="stat-consulting-time">—</span> min avg
                    </p>
                </div>
                <div style="width:48px;height:48px;background:#dbeafe;border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--primary-color);font-size:24px;">
                    <i class="fas fa-stethoscope"></i>
                </div>
            </div>
        </div>

        <!-- Completed Today -->
        <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border-left:4px solid var(--success-color);">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <p style="color:var(--gray-500);font-size:13px;font-weight:500;margin-bottom:8px;">Completed</p>
                    <p id="stat-done" style="font-size:32px;font-weight:700;color:var(--gray-900);">—</p>
                    <p style="color:var(--success-color);font-size:12px;margin-top:6px;">
                        <i class="fas fa-check-circle"></i> Today
                    </p>
                </div>
                <div style="width:48px;height:48px;background:#d1fae5;border-radius:12px;display:flex;align-items:center;justify-content:center;color:var(--success-color);font-size:24px;">
                    <i class="fas fa-check"></i>
                </div>
            </div>
        </div>

        <!-- Revenue Today -->
        <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);border-left:4px solid var(--primary-color);">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <p style="color:var(--gray-500);font-size:13px;font-weight:500;margin-bottom:8px;">Revenue Today</p>
                    <p id="stat-revenue" style="font-size:32px;font-weight:700;color:var(--gray-900);">$—</p>
                    <p style="color:var(--gray-500);font-size:12px;margin-top:6px;">
                        <span id="stat-revenue-paid" style="color:var(--success-color);">$0</span> paid
                    </p>
                </div>
                <div style="width:48px;height:48px;background:#e0f2fe;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#0284c7;font-size:24px;">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;">
        
        <!-- Queue Trend Chart -->
        <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
            <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);margin-bottom:16px;">
                <i class="fas fa-chart-line"></i> Daily Consultation Trend
            </h3>
            <canvas id="chartConsultationTrend" style="max-height:300px;"></canvas>
        </div>

        <!-- Status Breakdown -->
        <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
            <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);margin-bottom:16px;">
                <i class="fas fa-chart-pie"></i> Queue Status
            </h3>
            <canvas id="chartQueueStatus" style="max-height:300px;"></canvas>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div style="background:white;border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);">
        <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);margin-bottom:16px;">
            <i class="fas fa-tachometer-alt"></i> This Week Performance
        </h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:20px;">
            <div style="padding:16px;background:var(--gray-50);border-radius:8px;">
                <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Total Consultations</p>
                <p id="perf-consultations" style="font-size:24px;font-weight:700;color:var(--gray-900);">—</p>
            </div>
            <div style="padding:16px;background:var(--gray-50);border-radius:8px;">
                <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Revenue</p>
                <p id="perf-revenue" style="font-size:24px;font-weight:700;color:var(--gray-900);">$—</p>
            </div>
            <div style="padding:16px;background:var(--gray-50);border-radius:8px;">
                <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Avg Time/Patient</p>
                <p id="perf-avg-time" style="font-size:24px;font-weight:700;color:var(--gray-900);">—</p>
            </div>
            <div style="padding:16px;background:var(--gray-50);border-radius:8px;">
                <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Completion Rate</p>
                <p id="perf-completion" style="font-size:24px;font-weight:700;color:var(--success-color);">—%</p>
            </div>
        </div>
    </div>
</div>



<!-- ============================================
     QUEUE TAB
     ============================================ -->
<div id="tab-queue" class="portal-section" style="display:none;">
    
    <!-- Queue Controls -->
    <div style="background:white;border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;gap:12px;align-items:center;">
            <label style="font-size:13px;font-weight:500;color:var(--gray-700);">Date:</label>
            <input type="date" id="queue-date-filter" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <label style="font-size:13px;font-weight:500;color:var(--gray-700);margin-left:12px;">Status:</label>
            <select id="queue-status-filter" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
                <option value="">All</option>
                <option value="waiting">Waiting</option>
                <option value="called">Called</option>
                <option value="in_consultation">In Consultation</option>
                <option value="done">Done</option>
            </select>
        </div>
        <button onclick="callNextPatient()" style="padding:10px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
            <i class="fas fa-forward"></i> Call Next
        </button>
    </div>

    <!-- Queue Table -->
    <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                        <th style="padding:12px;text-align:left;font-weight:600;color:var(--gray-700);">#</th>
                        <th style="padding:12px;text-align:left;font-weight:600;color:var(--gray-700);">Patient</th>
                        <th style="padding:12px;text-align:left;font-weight:600;color:var(--gray-700);">Phone</th>
                        <th style="padding:12px;text-align:left;font-weight:600;color:var(--gray-700);">Chief Complaint</th>
                        <th style="padding:12px;text-align:left;font-weight:600;color:var(--gray-700);">Status</th>
                        <th style="padding:12px;text-align:left;font-weight:600;color:var(--gray-700);">Queued Time</th>
                        <th style="padding:12px;text-align:center;font-weight:600;color:var(--gray-700);">Actions</th>
                    </tr>
                </thead>
                <tbody id="queue-tbody">
                    <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>


<!-- ============================================
     FORMS TAB
     ============================================ -->
<div id="tab-forms" class="portal-section" style="display:none;">
    
    <!-- Forms List -->
    <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);background:var(--gray-50);">
            <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
                <i class="fas fa-clipboard"></i> Department Forms
            </h3>
            <p style="font-size:13px;color:var(--gray-500);margin-top:4px;">
                Department-specific forms for patient consultations and assessments
            </p>
        </div>
        <div style="padding:20px;">
            <div id="forms-container" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                <div style="text-align:center;padding:40px;color:var(--gray-400);">
                    <i class="fas fa-spinner fa-spin" style="font-size:24px;"></i>
                    <p style="margin-top:12px;">Loading forms...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Response Viewer -->
    <div id="form-response-viewer" style="display:none;margin-top:24px;background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);background:var(--gray-50);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
                <i class="fas fa-file-alt"></i> Form Response
            </h3>
            <button onclick="closeFormResponse()" style="border:none;background:none;font-size:22px;cursor:pointer;color:var(--gray-400);">&times;</button>
        </div>
        <div id="form-response-content" style="padding:20px;">
            <!-- Response content will be inserted here -->
        </div>
    </div>


<!-- ============================================
     REPORTS TAB
     ============================================ -->
<div id="tab-reports" class="portal-section" style="display:none;">
    
    <!-- Report Controls -->
    <div style="background:white;border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <label style="font-size:13px;font-weight:500;color:var(--gray-700);">From:</label>
            <input type="date" id="report-date-from" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
            <label style="font-size:13px;font-weight:500;color:var(--gray-700);">To:</label>
            <input type="date" id="report-date-to" style="padding:8px 12px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;">
        </div>
        <button onclick="loadReport()" style="padding:10px 18px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;">
            <i class="fas fa-refresh"></i> Generate Report
        </button>
    </div>

    <!-- Report Tabs -->
    <div style="display:flex;gap:12px;border-bottom:1px solid var(--gray-200);margin-bottom:16px;overflow-x:auto;">
        <button onclick="switchReportTab('consultation')" class="report-tab active" style="padding:10px 16px;border:none;background:none;font-weight:600;color:var(--primary-color);cursor:pointer;border-bottom:2px solid var(--primary-color);">
            <i class="fas fa-stethoscope"></i> Consultations
        </button>
        <button onclick="switchReportTab('revenue')" class="report-tab" style="padding:10px 16px;border:none;background:none;font-weight:600;color:var(--gray-500);cursor:pointer;">
            <i class="fas fa-money-bill"></i> Revenue
        </button>
        <button onclick="switchReportTab('performance')" class="report-tab" style="padding:10px 16px;border:none;background:none;font-weight:600;color:var(--gray-500);cursor:pointer;">
            <i class="fas fa-chart-bar"></i> Performance
        </button>
    </div>

    <!-- Consultation Report -->
    <div id="report-consultation" class="report-section" style="display:block;">
        <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);background:var(--gray-50);">
                <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
                    <i class="fas fa-stethoscope"></i> Consultation Volume
                </h3>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                            <th style="padding:12px;text-align:left;font-weight:600;color:var(--gray-700);">Date</th>
                            <th style="padding:12px;text-align:right;font-weight:600;color:var(--gray-700);">Total</th>
                            <th style="padding:12px;text-align:right;font-weight:600;color:var(--gray-700);">Completed</th>
                            <th style="padding:12px;text-align:right;font-weight:600;color:var(--gray-700);">No-Shows</th>
                            <th style="padding:12px;text-align:right;font-weight:600;color:var(--gray-700);">Avg Time</th>
                        </tr>
                    </thead>
                    <tbody id="report-consultation-tbody">
                        <tr><td colspan="5" style="padding:24px;text-align:center;color:var(--gray-400);">No data</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Revenue Report -->
    <div id="report-revenue" class="report-section" style="display:none;">
        <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--gray-200);background:var(--gray-50);">
                <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);">
                    <i class="fas fa-money-bill"></i> Revenue Summary
                </h3>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:var(--gray-50);border-bottom:1px solid var(--gray-200);">
                            <th style="padding:12px;text-align:left;font-weight:600;color:var(--gray-700);">Date</th>
                            <th style="padding:12px;text-align:right;font-weight:600;color:var(--gray-700);">Invoices</th>
                            <th style="padding:12px;text-align:right;font-weight:600;color:var(--gray-700);">Total</th>
                            <th style="padding:12px;text-align:right;font-weight:600;color:var(--success-color);">Paid</th>
                            <th style="padding:12px;text-align:right;font-weight:600;color:var(--warning-color);">Pending</th>
                        </tr>
                    </thead>
                    <tbody id="report-revenue-tbody">
                        <tr><td colspan="5" style="padding:24px;text-align:center;color:var(--gray-400);">No data</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Performance Report -->
    <div id="report-performance" class="report-section" style="display:none;">
        <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);padding:20px;">
            <h3 style="font-size:16px;font-weight:600;color:var(--gray-900);margin-bottom:16px;">
                <i class="fas fa-chart-bar"></i> Performance Metrics
            </h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
                <div style="padding:16px;background:var(--gray-50);border-radius:8px;border-left:4px solid var(--primary-color);">
                    <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Unique Patients</p>
                    <p id="perf-unique-patients" style="font-size:28px;font-weight:700;color:var(--gray-900);">—</p>
                </div>
                <div style="padding:16px;background:var(--gray-50);border-radius:8px;border-left:4px solid var(--success-color);">
                    <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Total Consultations</p>
                    <p id="perf-total-consultations" style="font-size:28px;font-weight:700;color:var(--gray-900);">—</p>
                </div>
                <div style="padding:16px;background:var(--gray-50);border-radius:8px;border-left:4px solid var(--warning-color);">
                    <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Avg Time Per Patient</p>
                    <p id="perf-avg-time-report" style="font-size:28px;font-weight:700;color:var(--gray-900);">—</p>
                </div>
                <div style="padding:16px;background:var(--gray-50);border-radius:8px;border-left:4px solid var(--success-color);">
                    <p style="font-size:13px;color:var(--gray-500);margin-bottom:8px;">Completion Rate</p>
                    <p id="perf-completion-rate" style="font-size:28px;font-weight:700;color:var(--success-color);">—%</p>
                </div>
            </div>
        </div>
    </div>


<!-- Modals and supporting UI will be handled in JavaScript -->
<script src="department_portal.js"></script>
</div>
</div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
