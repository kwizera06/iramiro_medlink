<?php
// public/reports.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Page title mapping
$subModuleTitles = [
    'dashboard' => 'Executive Dashboard',
    'clinical' => 'Clinical KPIs',
    'financial' => 'Financial KPIs',
    'operational' => 'Operational Dashboards',
    'custom' => 'Custom Report Builder',
    'predictive' => 'Predictive Analytics'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'Reports & BI';
?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'dashboard' => 'Executive overview of hospital performance metrics',
                'clinical' => 'Key clinical performance indicators and outcomes',
                'financial' => 'Financial performance metrics and revenue analytics',
                'operational' => 'Operational efficiency and process dashboards',
                'custom' => 'Build custom reports with advanced filters',
                'predictive' => 'Predictive analytics and forecasting models'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Reports & Business Intelligence';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($action == 'list' && $subModule == 'custom'): ?>
        <button onclick="window.location.href='reports.php?module=reports&sub=custom&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Create Report
        </button>
        <?php endif; ?>
        <?php if ($action != 'list'): ?>
        <button onclick="window.location.href='reports.php?module=reports&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
        <?php if (in_array($subModule, ['dashboard', 'clinical', 'financial', 'operational'])): ?>
        <button style="padding: 10px 20px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-file-pdf"></i> Export PDF
        </button>
        <button style="padding: 10px 20px; background: var(--secondary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-file-excel"></i> Export Excel
        </button>
        <button style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-print"></i> Print
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
EXECUTIVE DASHBOARD
============================================ -->
<?php if ($subModule == 'dashboard'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Date Range Filter -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <span style="font-weight: 500; color: var(--gray-700); align-self: center;">Date Range:</span>
        <input type="date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" style="padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
        <span style="color: var(--gray-500); align-self: center;">to</span>
        <input type="date" value="<?php echo date('Y-m-d'); ?>" style="padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
        <button style="padding: 8px 20px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync"></i> Update
        </button>
    </div>

    <!-- Dashboard Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 20px; background: linear-gradient(135deg, #2563eb, #60a5fa); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">Total Revenue</div>
            <div style="font-size: 28px; font-weight: 700;">$0</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                <i class="fas fa-arrow-up"></i> 0% from last period
            </div>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #22c55e, #86efac); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">Total Patients</div>
            <div style="font-size: 28px; font-weight: 700;">0</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                <i class="fas fa-arrow-up"></i> 0% from last period
            </div>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #f59e0b, #fcd34d); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">Appointments</div>
            <div style="font-size: 28px; font-weight: 700;">0</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                <i class="fas fa-arrow-down"></i> 0% from last period
            </div>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #8b5cf6, #c4b5fd); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">Bed Occupancy</div>
            <div style="font-size: 28px; font-weight: 700;">0%</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                <i class="fas fa-arrow-up"></i> 0% from last period
            </div>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #ef4444, #fca5a5); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">Average Wait Time</div>
            <div style="font-size: 28px; font-weight: 700;">0 mins</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                <i class="fas fa-arrow-down"></i> 0% improvement
            </div>
        </div>
        <div style="padding: 20px; background: linear-gradient(135deg, #0ea5e9, #7dd3fc); border-radius: 10px; color: white;">
            <div style="font-size: 13px; opacity: 0.9;">Patient Satisfaction</div>
            <div style="font-size: 28px; font-weight: 700;">0%</div>
            <div style="font-size: 12px; opacity: 0.8; margin-top: 4px;">
                <i class="fas fa-arrow-up"></i> 0% from last period
            </div>
        </div>
    </div>

    <!-- Chart Placeholders -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 300px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700);">Revenue Trend</h4>
                <span style="font-size: 12px; color: var(--gray-400);">Last 30 days</span>
            </div>
            <div style="text-align: center; padding: 80px 20px; color: var(--gray-400);">
                <div style="font-size: 48px; margin-bottom: 12px;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <p>Chart will display revenue trends</p>
                <p style="font-size: 12px;">Data will be populated when available</p>
            </div>
        </div>
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 300px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700);">Patient Distribution</h4>
                <span style="font-size: 12px; color: var(--gray-400);">By Department</span>
            </div>
            <div style="text-align: center; padding: 80px 20px; color: var(--gray-400);">
                <div style="font-size: 48px; margin-bottom: 12px;">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <p>Chart will display patient distribution</p>
                <p style="font-size: 12px;">Data will be populated when available</p>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
CLINICAL KPIs
============================================ -->
<?php elseif ($subModule == 'clinical'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Date Range Filter -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <span style="font-weight: 500; color: var(--gray-700); align-self: center;">Date Range:</span>
        <input type="date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" style="padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
        <span style="color: var(--gray-500); align-self: center;">to</span>
        <input type="date" value="<?php echo date('Y-m-d'); ?>" style="padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
        <button style="padding: 8px 20px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync"></i> Update
        </button>
    </div>

    <!-- Clinical KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--primary-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Avg Length of Stay</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0 days</div>
            <div style="font-size: 12px; color: var(--gray-400);">Target: &lt; 5 days</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--success-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Readmission Rate</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0%</div>
            <div style="font-size: 12px; color: var(--gray-400);">Target: &lt; 5%</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--danger-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Mortality Rate</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0%</div>
            <div style="font-size: 12px; color: var(--gray-400);">Target: &lt; 2%</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--warning-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Infection Rate</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0%</div>
            <div style="font-size: 12px; color: var(--gray-400);">Target: &lt; 1%</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--secondary-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Surgery Success Rate</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0%</div>
            <div style="font-size: 12px; color: var(--gray-400);">Target: &gt; 95%</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid #8b5cf6; box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Medication Error Rate</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0</div>
            <div style="font-size: 12px; color: var(--gray-400);">Target: 0</div>
        </div>
    </div>

    <!-- Clinical Charts -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Top Diagnoses</h4>
            <div style="text-align: center; padding: 60px 20px; color: var(--gray-400);">
                <div style="font-size: 36px; margin-bottom: 8px;">
                    <i class="fas fa-stethoscope"></i>
                </div>
                <p>Diagnosis data will appear here</p>
            </div>
        </div>
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Department Performance</h4>
            <div style="text-align: center; padding: 60px 20px; color: var(--gray-400);">
                <div style="font-size: 36px; margin-bottom: 8px;">
                    <i class="fas fa-building"></i>
                </div>
                <p>Department data will appear here</p>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
FINANCIAL KPIs
============================================ -->
<?php elseif ($subModule == 'financial'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Date Range Filter -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <span style="font-weight: 500; color: var(--gray-700); align-self: center;">Period:</span>
        <input type="month" value="<?php echo date('Y-m'); ?>" style="padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
        <span style="color: var(--gray-500); align-self: center;">to</span>
        <input type="month" value="<?php echo date('Y-m'); ?>" style="padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
        <button style="padding: 8px 20px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync"></i> Update
        </button>
    </div>

    <!-- Financial KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--success-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Total Revenue</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0</div>
            <div style="font-size: 12px; color: var(--gray-400);">
                <i class="fas fa-arrow-up" style="color: var(--success-color);"></i> 0%
            </div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--danger-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Total Expenses</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0</div>
            <div style="font-size: 12px; color: var(--gray-400);">
                <i class="fas fa-arrow-up" style="color: var(--danger-color);"></i> 0%
            </div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--primary-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Net Profit</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0</div>
            <div style="font-size: 12px; color: var(--gray-400);">
                <i class="fas fa-arrow-up" style="color: var(--success-color);"></i> 0%
            </div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--warning-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Accounts Receivable</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0</div>
            <div style="font-size: 12px; color: var(--gray-400);">Days Outstanding: 0</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--secondary-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Collection Rate</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0%</div>
            <div style="font-size: 12px; color: var(--gray-400);">Target: &gt; 95%</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid #8b5cf6; box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Average Claim Amount</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0</div>
            <div style="font-size: 12px; color: var(--gray-400);">Approval Rate: 0%</div>
        </div>
    </div>

    <!-- Financial Charts -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Revenue Breakdown</h4>
            <div style="text-align: center; padding: 60px 20px; color: var(--gray-400);">
                <div style="font-size: 36px; margin-bottom: 8px;">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <p>Revenue breakdown data will appear here</p>
            </div>
        </div>
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Expense Categories</h4>
            <div style="text-align: center; padding: 60px 20px; color: var(--gray-400);">
                <div style="font-size: 36px; margin-bottom: 8px;">
                    <i class="fas fa-coins"></i>
                </div>
                <p>Expense data will appear here</p>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
OPERATIONAL DASHBOARDS
============================================ -->
<?php elseif ($subModule == 'operational'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- Date Range Filter -->
    <div style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <span style="font-weight: 500; color: var(--gray-700); align-self: center;">Date Range:</span>
        <input type="date" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" style="padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
        <span style="color: var(--gray-500); align-self: center;">to</span>
        <input type="date" value="<?php echo date('Y-m-d'); ?>" style="padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 6px; font-size: 14px;">
        <button style="padding: 8px 20px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync"></i> Update
        </button>
    </div>

    <!-- Operational KPI Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--primary-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Total Appointments</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0</div>
            <div style="font-size: 12px; color: var(--gray-400);">Schedule: 0%</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--success-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Patients Served</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0</div>
            <div style="font-size: 12px; color: var(--gray-400);">Capacity: 0%</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--warning-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Bed Occupancy</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0%</div>
            <div style="font-size: 12px; color: var(--gray-400);">0 of 0 beds</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--danger-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Avg Wait Time</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0 mins</div>
            <div style="font-size: 12px; color: var(--gray-400);">Target: &lt; 15 mins</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid var(--secondary-color); box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Equipment Utilization</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0%</div>
            <div style="font-size: 12px; color: var(--gray-400);">Active: 0 of 0</div>
        </div>
        <div style="padding: 20px; background: white; border-radius: 10px; border-left: 4px solid #8b5cf6; box-shadow: var(--shadow-sm);">
            <div style="font-size: 13px; color: var(--gray-500);">Staff Availability</div>
            <div style="font-size: 28px; font-weight: 700; color: var(--gray-900);">0%</div>
            <div style="font-size: 12px; color: var(--gray-400);">0 of 0 staff</div>
        </div>
    </div>

    <!-- Operational Charts -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Daily Patient Flow</h4>
            <div style="text-align: center; padding: 60px 20px; color: var(--gray-400);">
                <div style="font-size: 36px; margin-bottom: 8px;">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <p>Patient flow data will appear here</p>
            </div>
        </div>
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Wait Times by Department</h4>
            <div style="text-align: center; padding: 60px 20px; color: var(--gray-400);">
                <div style="font-size: 36px; margin-bottom: 8px;">
                    <i class="fas fa-clock"></i>
                </div>
                <p>Wait time data will appear here</p>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
CUSTOM REPORT BUILDER
============================================ -->
<?php elseif ($subModule == 'custom'): ?>

<?php if ($action == 'add'): ?>
<!-- CREATE CUSTOM REPORT FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-tools" style="color: var(--primary-color);"></i> Build Custom Report
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Report Name <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter report name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Module <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Module</option>
                    <option value="patients">Patients</option>
                    <option value="appointments">Appointments</option>
                    <option value="pharmacy">Pharmacy</option>
                    <option value="finance">Finance</option>
                    <option value="hr">HR</option>
                    <option value="inventory">Inventory</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Date Range <span style="color: var(--danger-color);">*</span>
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <input type="date" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    <input type="date" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Report Format <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                    <option value="csv">CSV</option>
                    <option value="html">HTML</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Fields to Include <span style="color: var(--danger-color);">*</span>
                </label>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600);">
                        <input type="checkbox" checked> Patient Name
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600);">
                        <input type="checkbox" checked> Patient ID
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600);">
                        <input type="checkbox"> Age
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600);">
                        <input type="checkbox"> Gender
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600);">
                        <input type="checkbox"> Diagnosis
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600);">
                        <input type="checkbox"> Treatment Date
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600);">
                        <input type="checkbox"> Doctor
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600);">
                        <input type="checkbox"> Status
                    </label>
                </div>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Filters
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                    <input type="text" placeholder="Department" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    <input type="text" placeholder="Status" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    <input type="text" placeholder="Priority" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Description
                </label>
                <textarea rows="2" placeholder="Enter report description" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-flag"></i> Generate Report
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- CUSTOM REPORT LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Custom Reports</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No custom reports found. Create a report to get started.</p>
        </div>
        <button onclick="window.location.href='reports.php?module=reports&sub=custom&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Create Report
        </button>
    </div>

    <!-- Saved Reports -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-top: 20px;">
        <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 16px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 8px; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div>
                    <div style="font-weight: 500; font-size: 14px;">Monthly Patient Summary</div>
                    <div style="font-size: 12px; color: var(--gray-500);">Last generated: Never</div>
                </div>
                <button style="margin-left: auto; padding: 6px 12px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                    Run
                </button>
            </div>
        </div>
        <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 16px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 8px; background: var(--success-color); display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div>
                    <div style="font-weight: 500; font-size: 14px;">Revenue Report Q1</div>
                    <div style="font-size: 12px; color: var(--gray-500);">Last generated: Never</div>
                </div>
                <button style="margin-left: auto; padding: 6px 12px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                    Run
                </button>
            </div>
        </div>
        <div style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 16px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 8px; background: var(--warning-color); display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div>
                    <div style="font-weight: 500; font-size: 14px;">Clinical Outcomes Dashboard</div>
                    <div style="font-size: 12px; color: var(--gray-500);">Last generated: Never</div>
                </div>
                <button style="margin-left: auto; padding: 6px 12px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                    Run
                </button>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 40px 20px; margin-top: 20px;">
        <div style="font-size: 48px; color: var(--gray-300); margin-bottom: 12px;">
            <i class="fas fa-tools"></i>
        </div>
        <p style="color: var(--gray-400);">Saved reports will appear here</p>
        <button onclick="window.location.href='reports.php?module=reports&sub=custom&action=add'" style="margin-top: 12px; padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Create First Report
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
PREDICTIVE ANALYTICS
============================================ -->
<?php elseif ($subModule == 'predictive'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Predictive Analytics</h3>
            <p style="font-size: 13px; color: var(--gray-500);">AI-powered predictions and forecasting models</p>
        </div>
        <button style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync"></i> Run Predictions
        </button>
    </div>

    <!-- Predictive Models -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; border-left: 4px solid var(--primary-color);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="font-size: 28px; color: var(--primary-color);">
                    <i class="fas fa-hospital-user"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Patient Admission Forecast</div>
                    <div style="font-size: 12px; color: var(--gray-500);">Next 7 days</div>
                </div>
            </div>
            <div style="margin-top: 12px; display: flex; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--gray-500);">Predicted:</span>
                <span style="font-weight: 600; color: var(--gray-900);">0 patients</span>
            </div>
            <div style="margin-top: 4px; display: flex; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--gray-500);">Confidence:</span>
                <span style="font-weight: 600; color: var(--success-color);">0%</span>
            </div>
        </div>

        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; border-left: 4px solid var(--success-color);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="font-size: 28px; color: var(--success-color);">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Appointment Demand</div>
                    <div style="font-size: 12px; color: var(--gray-500);">Next 7 days</div>
                </div>
            </div>
            <div style="margin-top: 12px; display: flex; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--gray-500);">Predicted:</span>
                <span style="font-weight: 600; color: var(--gray-900);">0 appointments</span>
            </div>
            <div style="margin-top: 4px; display: flex; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--gray-500);">Confidence:</span>
                <span style="font-weight: 600; color: var(--success-color);">0%</span>
            </div>
        </div>

        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; border-left: 4px solid var(--warning-color);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="font-size: 28px; color: var(--warning-color);">
                    <i class="fas fa-pills"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Pharmacy Demand</div>
                    <div style="font-size: 12px; color: var(--gray-500);">Next 7 days</div>
                </div>
            </div>
            <div style="margin-top: 12px; display: flex; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--gray-500);">Predicted:</span>
                <span style="font-weight: 600; color: var(--gray-900);">0 units</span>
            </div>
            <div style="margin-top: 4px; display: flex; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--gray-500);">Confidence:</span>
                <span style="font-weight: 600; color: var(--success-color);">0%</span>
            </div>
        </div>

        <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; border-left: 4px solid var(--danger-color);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="font-size: 28px; color: var(--danger-color);">
                    <i class="fas fa-bed"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Bed Utilization Forecast</div>
                    <div style="font-size: 12px; color: var(--gray-500);">Next 7 days</div>
                </div>
            </div>
            <div style="margin-top: 12px; display: flex; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--gray-500);">Predicted:</span>
                <span style="font-weight: 600; color: var(--gray-900);">0% occupancy</span>
            </div>
            <div style="margin-top: 4px; display: flex; justify-content: space-between;">
                <span style="font-size: 12px; color: var(--gray-500);">Confidence:</span>
                <span style="font-weight: 600; color: var(--success-color);">0%</span>
            </div>
        </div>
    </div>

    <!-- Predictive Chart -->
    <div style="padding: 20px; background: var(--gray-50); border-radius: 8px; min-height: 250px;">
        <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 16px;">Forecast Trends</h4>
        <div style="text-align: center; padding: 60px 20px; color: var(--gray-400);">
            <div style="font-size: 48px; margin-bottom: 12px;">
                <i class="fas fa-robot"></i>
            </div>
            <p>AI predictions will appear here once data is available</p>
            <p style="font-size: 12px;">Machine learning models are training with your data</p>
        </div>
    </div>

    <!-- Model Performance -->
    <div style="margin-top: 24px; padding: 16px; background: #dbeafe; border-radius: 8px; border-left: 4px solid var(--primary-color);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-chart-line" style="color: var(--primary-color); font-size: 20px;"></i>
            <div>
                <div style="font-weight: 600; color: var(--gray-900);">Model Performance</div>
                <div style="font-size: 13px; color: var(--gray-600);">All models are training with available data. Predictions will improve over time.</div>
            </div>
            <button style="margin-left: auto; padding: 6px 16px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer;">
                View Details
            </button>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>