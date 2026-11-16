<?php
// public/radiology.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'xray';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Page title mapping
$subModuleTitles = [
    'xray' => 'X-Ray',
    'mri' => 'MRI',
    'ct' => 'CT Scan',
    'ultrasound' => 'Ultrasound',
    'reporting' => 'Reporting',
    'pacs' => 'PACS Integration'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'Radiology';
?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'xray' => 'Manage X-Ray imaging requests and reports',
                'mri' => 'Manage MRI imaging requests and reports',
                'ct' => 'Manage CT Scan imaging requests and reports',
                'ultrasound' => 'Manage Ultrasound imaging requests and reports',
                'reporting' => 'Generate and manage radiology reports',
                'pacs' => 'Picture Archiving and Communication System integration'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Radiology Management';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($action == 'list' && in_array($subModule, ['xray', 'mri', 'ct', 'ultrasound', 'reporting', 'pacs'])): ?>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=<?php echo $subModule; ?>&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Add New
        </button>
        <?php endif; ?>
        <?php if ($action != 'list'): ?>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
X-RAY
============================================ -->
<?php if ($subModule == 'xray'): ?>

<?php if ($action == 'add'): ?>
<!-- X-RAY REQUEST FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-x-ray" style="color: var(--primary-color);"></i> X-Ray Request
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Request ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., XRAY-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
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
                    Body Part <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Body Part</option>
                    <option value="chest">Chest</option>
                    <option value="skull">Skull</option>
                    <option value="spine">Spine</option>
                    <option value="pelvis">Pelvis</option>
                    <option value="shoulder">Shoulder</option>
                    <option value="elbow">Elbow</option>
                    <option value="wrist">Wrist</option>
                    <option value="hip">Hip</option>
                    <option value="knee">Knee</option>
                    <option value="ankle">Ankle</option>
                    <option value="foot">Foot</option>
                    <option value="hand">Hand</option>
                    <option value="ribs">Ribs</option>
                    <option value="abdomen">Abdomen</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    View Type <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select View</option>
                    <option value="ap">AP (Anterior-Posterior)</option>
                    <option value="pa">PA (Posterior-Anterior)</option>
                    <option value="lateral">Lateral</option>
                    <option value="oblique">Oblique</option>
                    <option value="decubitus">Decubitus</option>
                    <option value="multiple">Multiple Views</option>
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
                    Priority <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
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
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Clinical History
                </label>
                <textarea rows="2" placeholder="Enter clinical history and reason for exam" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
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
<!-- X-RAY LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">X-Ray Requests</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No X-Ray requests found. Create a request to get started.</p>
        </div>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=xray&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Request
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-x-ray"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No X-Ray Requests</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create an X-Ray request to start imaging.</p>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=xray&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Request
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
MRI
============================================ -->
<?php elseif ($subModule == 'mri'): ?>

<?php if ($action == 'add'): ?>
<!-- MRI REQUEST FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-magnet" style="color: var(--secondary-color);"></i> MRI Request
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Request ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., MRI-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
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
                    MRI Type <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Type</option>
                    <option value="brain">Brain MRI</option>
                    <option value="spine">Spine MRI</option>
                    <option value="shoulder">Shoulder MRI</option>
                    <option value="knee">Knee MRI</option>
                    <option value="hip">Hip MRI</option>
                    <option value="abdomen">Abdomen MRI</option>
                    <option value="pelvis">Pelvis MRI</option>
                    <option value="breast">Breast MRI</option>
                    <option value="cardiac">Cardiac MRI</option>
                    <option value="mra">MRA (Angiography)</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Contrast <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="without">Without Contrast</option>
                    <option value="with">With Contrast</option>
                    <option value="both">Both With & Without</option>
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
                    Priority <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
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
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Clinical History
                </label>
                <textarea rows="2" placeholder="Enter clinical history and reason for exam" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--secondary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Submit Request
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- MRI LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">MRI Requests</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No MRI requests found. Create a request to get started.</p>
        </div>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=mri&action=add'" style="padding: 10px 20px; background: var(--secondary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Request
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-magnet"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No MRI Requests</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create an MRI request to start imaging.</p>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=mri&action=add'" style="padding: 12px 24px; background: var(--secondary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Request
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
CT SCAN
============================================ -->
<?php elseif ($subModule == 'ct'): ?>

<?php if ($action == 'add'): ?>
<!-- CT SCAN REQUEST FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-microchip" style="color: var(--warning-color);"></i> CT Scan Request
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Request ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., CT-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
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
                    CT Type <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Type</option>
                    <option value="head">Head CT</option>
                    <option value="chest">Chest CT</option>
                    <option value="abdomen">Abdomen CT</option>
                    <option value="pelvis">Pelvis CT</option>
                    <option value="spine">Spine CT</option>
                    <option value="angiography">CT Angiography</option>
                    <option value="cardiac">Cardiac CT</option>
                    <option value="whole-body">Whole Body CT</option>
                    <option value="trauma">Trauma CT</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Contrast <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="without">Without Contrast</option>
                    <option value="with">With Contrast</option>
                    <option value="both">Both With & Without</option>
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
                    Priority <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
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
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Clinical History
                </label>
                <textarea rows="2" placeholder="Enter clinical history and reason for exam" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Submit Request
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- CT SCAN LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">CT Scan Requests</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No CT Scan requests found. Create a request to get started.</p>
        </div>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=ct&action=add'" style="padding: 10px 20px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Request
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-microchip"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No CT Scan Requests</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create a CT Scan request to start imaging.</p>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=ct&action=add'" style="padding: 12px 24px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Request
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
ULTRASOUND
============================================ -->
<?php elseif ($subModule == 'ultrasound'): ?>

<?php if ($action == 'add'): ?>
<!-- ULTRASOUND REQUEST FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-wave-square" style="color: var(--success-color);"></i> Ultrasound Request
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Request ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., US-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
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
                    Ultrasound Type <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Type</option>
                    <option value="obstetric">Obstetric</option>
                    <option value="pelvic">Pelvic</option>
                    <option value="abdominal">Abdominal</option>
                    <option value="renal">Renal</option>
                    <option value="breast">Breast</option>
                    <option value="thyroid">Thyroid</option>
                    <option value="vascular">Vascular</option>
                    <option value="cardiac">Cardiac (Echocardiogram)</option>
                    <option value="musculoskeletal">Musculoskeletal</option>
                    <option value="transvaginal">Transvaginal</option>
                    <option value="transrectal">Transrectal</option>
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
                    Priority <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
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
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Clinical History
                </label>
                <textarea rows="2" placeholder="Enter clinical history and reason for exam" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Submit Request
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- ULTRASOUND LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Ultrasound Requests</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No Ultrasound requests found. Create a request to get started.</p>
        </div>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=ultrasound&action=add'" style="padding: 10px 20px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Request
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-wave-square"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Ultrasound Requests</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Create an Ultrasound request to start imaging.</p>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=ultrasound&action=add'" style="padding: 12px 24px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Request
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
REPORTING
============================================ -->
<?php elseif ($subModule == 'reporting'): ?>

<?php if ($action == 'add'): ?>
<!-- RADIOLOGY REPORT FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-file-alt" style="color: var(--primary-color);"></i> Radiology Report
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Exam ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., XRAY-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
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
                    Exam Type <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="xray">X-Ray</option>
                    <option value="mri">MRI</option>
                    <option value="ct">CT Scan</option>
                    <option value="ultrasound">Ultrasound</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Report Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Radiologist <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Radiologist</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="draft">Draft</option>
                    <option value="final">Final</option>
                    <option value="review">Under Review</option>
                    <option value="amended">Amended</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Clinical History
                </label>
                <textarea rows="2" placeholder="Enter clinical history" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Findings <span style="color: var(--danger-color);">*</span>
                </label>
                <textarea rows="4" placeholder="Enter radiology findings" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Impression / Conclusion <span style="color: var(--danger-color);">*</span>
                </label>
                <textarea rows="3" placeholder="Enter impression and conclusion" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Recommendations
                </label>
                <textarea rows="2" placeholder="Enter recommendations" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
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
<!-- REPORTS LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Radiology Reports</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No radiology reports found. Create a report to get started.</p>
        </div>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=reporting&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Report
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-file-alt"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Reports</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Generate radiology reports for imaging exams.</p>
        <button onclick="window.location.href='radiology.php?module=radiology&sub=reporting&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Report
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
PACS INTEGRATION
============================================ -->
<?php elseif ($subModule == 'pacs'): ?>

<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">PACS Integration</h3>
            <p style="font-size: 13px; color: var(--gray-500);">Picture Archiving and Communication System Integration</p>
        </div>
        <button style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-sync"></i> Sync Now
        </button>
    </div>

    <!-- PACS Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Total Studies</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Synced Today</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Pending Sync</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--secondary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Failed</div>
        </div>
    </div>

    <!-- PACS Configuration -->
    <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; margin-bottom: 20px;">
        <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 12px;">PACS Configuration</h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    PACS Server URL
                </label>
                <input type="text" placeholder="https://pacs.hospital.com" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    AE Title
                </label>
                <input type="text" placeholder="HOSPITAL_AE" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Port
                </label>
                <input type="text" placeholder="104" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
        </div>
        <div style="display: flex; gap: 8px; margin-top: 12px;">
            <button style="padding: 8px 16px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-save"></i> Save Settings
            </button>
            <button style="padding: 8px 16px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 6px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-plug"></i> Test Connection
            </button>
        </div>
    </div>

    <!-- Recent PACS Activity -->
    <div style="padding: 16px; background: var(--gray-50); border-radius: 8px;">
        <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-700); margin-bottom: 12px;">Recent PACS Activity</h4>
        <div style="text-align: center; padding: 30px 20px;">
            <div style="font-size: 48px; color: var(--gray-300); margin-bottom: 8px;">
                <i class="fas fa-images"></i>
            </div>
            <p style="color: var(--gray-500);">No recent PACS activity</p>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>