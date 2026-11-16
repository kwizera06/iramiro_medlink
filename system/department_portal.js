/**
 * Department Portal JavaScript
 * Handles dashboard, queue management, forms display, and reports
 */

// Configuration
const API_BASE = 'api.php?endpoint=departments/portal';
let currentDepartmentId = null;
let currentUserId = null;
let dashboardData = {};
let reportData = {};

// Chart instances
let consultationTrendChart = null;
let queueStatusChart = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Set default dates
    document.getElementById('queue-date-filter').valueAsDate = new Date();
    document.getElementById('report-date-from').valueAsDate = new Date(new Date().setDate(new Date().getDate() - 30));
    document.getElementById('report-date-to').valueAsDate = new Date();
    
    // Load initial data
    loadDashboardData();
    setupEventListeners();
});

// ============================================
// TAB SWITCHING
// ============================================

function switchTab(tabName) {
    // Hide all sections
    document.querySelectorAll('.portal-section').forEach(el => el.style.display = 'none');
    
    // Remove active class from all tabs
    document.querySelectorAll('.portal-tab').forEach(el => {
        el.style.color = 'var(--gray-500)';
        el.style.borderBottom = 'none';
    });
    
    // Show selected section
    document.getElementById(`tab-${tabName}`).style.display = 'block';
    
    // Mark tab as active
    event.target.closest('.portal-tab').style.color = 'var(--primary-color)';
    event.target.closest('.portal-tab').style.borderBottom = '3px solid var(--primary-color)';
    event.target.closest('.portal-tab').style.marginBottom = '-2px';
    
    // Load tab-specific data
    if (tabName === 'queue') {
        loadQueueData();
    } else if (tabName === 'forms') {
        loadFormsData();
    } else if (tabName === 'reports') {
        loadReport();
    }
}

function switchReportTab(reportType) {
    // Hide all report sections
    document.querySelectorAll('.report-section').forEach(el => el.style.display = 'none');
    
    // Remove active class from all report tabs
    document.querySelectorAll('.report-tab').forEach(el => el.style.color = 'var(--gray-500)');
    
    // Show selected report
    document.getElementById(`report-${reportType}`).style.display = 'block';
    
    // Mark tab as active
    event.target.closest('.report-tab').style.color = 'var(--primary-color)';
    event.target.closest('.report-tab').style.borderBottom = '2px solid var(--primary-color)';
}

// ============================================
// DASHBOARD
// ============================================

function loadDashboardData() {
    const dept = new URLSearchParams(window.location.search).get('dept') || 1;
    currentDepartmentId = dept;
    
    fetch(`${API_BASE}&action=dashboard&department_id=${dept}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            
            dashboardData = res.data;
            updateDashboardUI();
            initCharts();
        })
        .catch(err => {
            console.error('Dashboard load error:', err);
            showNotification('Failed to load dashboard data', 'error');
        });
}

function updateDashboardUI() {
    const qs = dashboardData.queue_stats || {};
    const rs = dashboardData.revenue_stats || {};
    const pm = dashboardData.performance_metrics || {};
    
    // Update queue stats
    document.getElementById('stat-waiting').textContent = qs.waiting || 0;
    document.getElementById('stat-consulting').textContent = qs.in_consultation || 0;
    document.getElementById('stat-done').textContent = qs.done || 0;
    document.getElementById('stat-consulting-time').textContent = Math.round(pm.avg_consult_minutes || 0);
    
    // Update revenue
    document.getElementById('stat-revenue').textContent = '$' + (rs.total_revenue || 0).toFixed(2);
    document.getElementById('stat-revenue-paid').textContent = '$' + (rs.paid || 0).toFixed(2);
    
    // Update performance metrics
    document.getElementById('perf-consultations').textContent = pm.consultations_this_week || 0;
    document.getElementById('perf-revenue').textContent = '$' + (pm.revenue_this_week || 0).toFixed(2);
    document.getElementById('perf-avg-time').textContent = Math.round(pm.avg_consult_minutes || 0) + ' min';
    document.getElementById('perf-completion').textContent = '—'; // Will be calculated
}

// ============================================
// CHARTS
// ============================================

function initCharts() {
    // Load chart libraries if not present
    if (typeof Chart === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = function() {
            renderCharts();
        };
        document.head.appendChild(script);
    } else {
        renderCharts();
    }
}

function renderCharts() {
    // Queue Status Pie Chart
    const qsCtx = document.getElementById('chartQueueStatus');
    if (qsCtx) {
        const qs = dashboardData.queue_stats || {};
        consultationTrendChart = new Chart(qsCtx, {
            type: 'doughnut',
            data: {
                labels: ['Waiting', 'In Consultation', 'Completed'],
                datasets: [{
                    data: [qs.waiting || 0, qs.in_consultation || 0, qs.done || 0],
                    backgroundColor: ['#f59e0b', '#2563eb', '#22c55e'],
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 12 }, padding: 12 }
                    }
                }
            }
        });
    }
}

// ============================================
// QUEUE MANAGEMENT
// ============================================

function loadQueueData() {
    const dept = currentDepartmentId || 1;
    const date = document.getElementById('queue-date-filter').value || new Date().toISOString().split('T')[0];
    const status = document.getElementById('queue-status-filter').value || '';
    
    fetch(`${API_BASE}&action=queue_list&department_id=${dept}&date=${date}&status=${status}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            
            renderQueueTable(res.data.queue);
        })
        .catch(err => {
            console.error('Queue load error:', err);
            showNotification('Failed to load queue data', 'error');
        });
}

function renderQueueTable(queue) {
    const tbody = document.getElementById('queue-tbody');
    
    if (!queue || queue.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="padding:40px;text-align:center;color:var(--gray-400);">No patients in queue</td></tr>';
        return;
    }
    
    tbody.innerHTML = queue.map((q, idx) => `
        <tr style="border-bottom:1px solid var(--gray-200);${q.status === 'done' ? 'background:var(--gray-50)' : ''}">
            <td style="padding:12px;font-weight:600;">${q.queue_no}</td>
            <td style="padding:12px;">
                <strong>${q.first_name} ${q.last_name}</strong><br>
                <span style="color:var(--gray-500);font-size:12px;">${q.patient_no}</span>
            </td>
            <td style="padding:12px;">${q.phone || '—'}</td>
            <td style="padding:12px;">${q.chief_complaint || '—'}</td>
            <td style="padding:12px;">
                <span style="padding:4px 12px;border-radius:12px;font-size:12px;font-weight:600;${
                    q.status === 'done' ? 'background:#d1fae5;color:#065f46' :
                    q.status === 'in_consultation' ? 'background:#dbeafe;color:#1e40af' :
                    q.status === 'called' ? 'background:#fef3c7;color:#92400e' :
                    'background:#fee2e2;color:#991b1b'
                }">
                    ${q.status.replace(/_/g, ' ').toUpperCase()}
                </span>
            </td>
            <td style="padding:12px;">
                <span style="color:var(--gray-500);font-size:12px;">${formatTime(q.created_at)}</span>
            </td>
            <td style="padding:12px;text-align:center;">
                <div style="display:flex;gap:6px;justify-content:center;">
                    ${q.status === 'waiting' ? `
                        <button onclick="startConsultation(${q.id})" style="padding:4px 12px;background:var(--primary-color);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;">
                            Start
                        </button>
                    ` : q.status === 'in_consultation' ? `
                        <button onclick="completeConsultation(${q.id})" style="padding:4px 12px;background:var(--success-color);color:white;border:none;border-radius:4px;font-size:11px;cursor:pointer;">
                            Complete
                        </button>
                    ` : ''}
                    <button onclick="viewPatient(${q.patient_id})" style="padding:4px 12px;background:var(--gray-200);color:var(--gray-700);border:none;border-radius:4px;font-size:11px;cursor:pointer;">
                        View
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function callNextPatient() {
    const dept = currentDepartmentId || 1;
    const date = document.getElementById('queue-date-filter').value || new Date().toISOString().split('T')[0];
    
    fetch(`${API_BASE}&action=call_next&department_id=${dept}&date=${date}`, { method: 'POST' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            
            showNotification('Patient called - Queue #' + res.data.queue_no, 'success');
            loadQueueData();
            loadDashboardData();
        })
        .catch(err => {
            console.error('Call next error:', err);
            showNotification(err.message || 'Failed to call next patient', 'error');
        });
}

function startConsultation(queueId) {
    fetch(`${API_BASE}&action=start_consultation&queue_id=${queueId}`, { method: 'POST' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            
            showNotification('Consultation started', 'success');
            loadQueueData();
            loadDashboardData();
        })
        .catch(err => {
            console.error('Start consultation error:', err);
            showNotification(err.message || 'Failed to start consultation', 'error');
        });
}

function completeConsultation(queueId) {
    if (!confirm('Mark this consultation as complete?')) return;
    
    fetch(`${API_BASE}&action=complete_consultation&queue_id=${queueId}`, { method: 'POST' })
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            
            showNotification('Consultation completed', 'success');
            loadQueueData();
            loadDashboardData();
        })
        .catch(err => {
            console.error('Complete consultation error:', err);
            showNotification(err.message || 'Failed to complete consultation', 'error');
        });
}

// ============================================
// FORMS
// ============================================

function loadFormsData() {
    const dept = currentDepartmentId || 1;
    
    fetch(`${API_BASE}&action=forms_display&department_id=${dept}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            
            renderFormsList(res.data);
        })
        .catch(err => {
            console.error('Forms load error:', err);
            showNotification('Failed to load forms', 'error');
        });
}

function renderFormsList(templates) {
    const container = document.getElementById('forms-container');
    
    if (!templates || templates.length === 0) {
        container.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-400);">No forms available for this department</div>';
        return;
    }
    
    container.innerHTML = templates.map(t => `
        <div style="background:white;border-radius:var(--radius);padding:16px;box-shadow:var(--shadow);border-left:4px solid var(--primary-color);">
            <h4 style="font-size:14px;font-weight:600;color:var(--gray-900);margin-bottom:8px;">
                <i class="fas fa-clipboard"></i> ${t.name}
            </h4>
            <p style="font-size:12px;color:var(--gray-500);margin-bottom:12px;">
                ${t.description || 'No description'}
            </p>
            <p style="font-size:11px;color:var(--gray-400);margin-bottom:12px;">
                <i class="fas fa-bars"></i> ${t.fields ? t.fields.length : 0} fields
            </p>
            <button onclick="editForm(${t.id}, '${t.name}')" style="width:100%;padding:10px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;">
                <i class="fas fa-pen"></i> Edit Form
            </button>
        </div>
    `).join('');
}

function editForm(templateId, templateName) {
    alert(`Edit form functionality would open a form editor for: ${templateName}`);
    // This would open a modal with the form editor
}

function closeFormResponse() {
    document.getElementById('form-response-viewer').style.display = 'none';
}

// ============================================
// REPORTS
// ============================================

function loadReport() {
    const dept = currentDepartmentId || 1;
    const dateFrom = document.getElementById('report-date-from').value;
    const dateTo = document.getElementById('report-date-to').value;
    
    fetch(`${API_BASE}&action=reports&department_id=${dept}&report_type=all&date_from=${dateFrom}&date_to=${dateTo}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) throw new Error(res.message);
            
            reportData = res.data.data;
            renderReports(reportData);
        })
        .catch(err => {
            console.error('Report load error:', err);
            showNotification('Failed to load reports', 'error');
        });
}

function renderReports(data) {
    // Render consultation volume report
    if (data.consultation_volume) {
        const tbody = document.getElementById('report-consultation-tbody');
        tbody.innerHTML = data.consultation_volume.map(row => `
            <tr style="border-bottom:1px solid var(--gray-200);">
                <td style="padding:12px;">${row.date}</td>
                <td style="padding:12px;text-align:right;font-weight:600;">${row.total_patients}</td>
                <td style="padding:12px;text-align:right;color:var(--success-color);font-weight:600;">${row.completed}</td>
                <td style="padding:12px;text-align:right;color:var(--danger-color);">${row.no_shows || 0}</td>
                <td style="padding:12px;text-align:right;">${Math.round(row.avg_consult_time_min)} min</td>
            </tr>
        `).join('');
    }
    
    // Render revenue report
    if (data.revenue) {
        const tbody = document.getElementById('report-revenue-tbody');
        tbody.innerHTML = data.revenue.map(row => `
            <tr style="border-bottom:1px solid var(--gray-200);">
                <td style="padding:12px;">${row.date}</td>
                <td style="padding:12px;text-align:right;">${row.invoice_count}</td>
                <td style="padding:12px;text-align:right;font-weight:600;">$${row.total_amount.toFixed(2)}</td>
                <td style="padding:12px;text-align:right;color:var(--success-color);font-weight:600;">$${row.paid_amount.toFixed(2)}</td>
                <td style="padding:12px;text-align:right;color:var(--warning-color);font-weight:600;">$${row.pending_amount.toFixed(2)}</td>
            </tr>
        `).join('');
    }
    
    // Render performance metrics
    if (data.performance) {
        const perf = data.performance;
        document.getElementById('perf-unique-patients').textContent = perf.unique_patients || 0;
        document.getElementById('perf-total-consultations').textContent = perf.total_consultations || 0;
        document.getElementById('perf-avg-time-report').textContent = Math.round(perf.avg_time_per_patient || 0) + ' min';
        document.getElementById('perf-completion-rate').textContent = (perf.completion_rate || 0).toFixed(1) + '%';
    }
}

// ============================================
// UTILITIES
// ============================================

function setupEventListeners() {
    // Queue filter change events
    document.getElementById('queue-date-filter').addEventListener('change', loadQueueData);
    document.getElementById('queue-status-filter').addEventListener('change', loadQueueData);
}

function refreshData() {
    loadDashboardData();
    showNotification('Data refreshed', 'success');
}

function exportReport() {
    alert('Export functionality coming soon!');
}

function formatTime(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function viewPatient(patientId) {
    window.open(`patients.php?view=${patientId}`, '_blank');
}

function showNotification(message, type = 'info') {
    // Simple notification (can be replaced with a toast library)
    console.log(`[${type.toUpperCase()}] ${message}`);
    // You could implement a toast notification here
}
