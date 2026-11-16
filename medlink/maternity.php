<?php
// public/maternity.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'antenatal';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Page title mapping
$subModuleTitles = [
    'antenatal' => 'Antenatal Care',
    'labor' => 'Labor Monitoring',
    'delivery' => 'Delivery Records',
    'postnatal' => 'Postnatal Care',
    'newborn' => 'Newborn Registration'
];

$currentTitle = isset($subModuleTitles[$subModule]) ? $subModuleTitles[$subModule] : 'Maternity';
?>

<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h2 style="font-size: 24px; font-weight: 600; color: var(--gray-900);"><?php echo $currentTitle; ?></h2>
        <p style="color: var(--gray-500); font-size: 14px; margin-top: 4px;">
            <?php 
            $descriptions = [
                'antenatal' => 'Manage antenatal care and pregnancy monitoring',
                'labor' => 'Monitor labor progress and delivery preparation',
                'delivery' => 'Record delivery details and outcomes',
                'postnatal' => 'Manage postnatal care and recovery',
                'newborn' => 'Register newborn babies and initial assessments'
            ];
            echo isset($descriptions[$subModule]) ? $descriptions[$subModule] : 'Maternity Management';
            ?>
        </p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($action == 'list' && in_array($subModule, ['antenatal', 'labor', 'delivery', 'postnatal', 'newborn'])): ?>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=<?php echo $subModule; ?>&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Add New
        </button>
        <?php endif; ?>
        <?php if ($action != 'list'): ?>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=<?php echo $subModule; ?>'" style="padding: 10px 20px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================
ANTENATAL CARE
============================================ -->
<?php if ($subModule == 'antenatal'): ?>

<?php if ($action == 'add'): ?>
<!-- ANTENATAL CARE FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-fetus" style="color: var(--primary-color);"></i> Antenatal Care Record
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
                    ANC ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., ANC-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Gravida <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., G3" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Para <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., P2" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    LMP (Last Menstrual Period) <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    EDD (Expected Date of Delivery) <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="date" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Gestational Age (weeks) <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter weeks" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Blood Group <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Blood Group</option>
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
                    HIV Status
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="negative">Negative</option>
                    <option value="positive">Positive</option>
                    <option value="unknown">Unknown</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Hepatitis B Status
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="negative">Negative</option>
                    <option value="positive">Positive</option>
                    <option value="unknown">Unknown</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Syphilis Screening
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="negative">Negative</option>
                    <option value="positive">Positive</option>
                    <option value="unknown">Unknown</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="transfered">Transferred</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Obstetric History
                </label>
                <textarea rows="2" placeholder="Enter obstetric history" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Medical History
                </label>
                <textarea rows="2" placeholder="Enter medical history" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    ANC Notes
                </label>
                <textarea rows="2" placeholder="Additional ANC notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save ANC Record
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- ANTENATAL CARE LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <!-- ANC Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--primary-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Total ANC Patients</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--success-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Active</div>
        </div>
        <div style="padding: 16px; background: var(--gray-50); border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: 700; color: var(--warning-color);">0</div>
            <div style="font-size: 13px; color: var(--gray-500);">Due for Delivery</div>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Antenatal Care Records</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No ANC records found. Create a record to get started.</p>
        </div>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=antenatal&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New ANC Record
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-fetus"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No ANC Records</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Register antenatal patients for pregnancy care.</p>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=antenatal&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First ANC Record
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
LABOR MONITORING
============================================ -->
<?php elseif ($subModule == 'labor'): ?>

<?php if ($action == 'add'): ?>
<!-- LABOR MONITORING FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-procedures" style="color: var(--warning-color);"></i> Labor Monitoring
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
                    Labor ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., LAB-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Admission Time <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="datetime-local" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Cervical Dilatation (cm) <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 4 cm" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Contractions (mins) <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., Every 3 mins" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Fetal Heart Rate <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 140 bpm" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Maternal Heart Rate
                </label>
                <input type="text" placeholder="e.g., 80 bpm" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Blood Pressure <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 120/80" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Attending Midwife <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Midwife</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Labor Stage <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Stage</option>
                    <option value="latent">Latent Phase</option>
                    <option value="active">Active Phase</option>
                    <option value="transition">Transition Phase</option>
                    <option value="second">Second Stage (Pushing)</option>
                    <option value="third">Third Stage (Placenta)</option>
                    <option value="fourth">Fourth Stage (Recovery)</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="in-progress">In Progress</option>
                    <option value="delivered">Delivered</option>
                    <option value="transferred">Transferred</option>
                    <option value="c-section">C-Section</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Labor Progress Notes
                </label>
                <textarea rows="2" placeholder="Enter labor progress notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save Labor Record
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- LABOR MONITORING LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Labor Monitoring Records</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No labor records found. Create a record to get started.</p>
        </div>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=labor&action=add'" style="padding: 10px 20px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Labor Record
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-procedures"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Labor Records</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Monitor labor progress for maternity patients.</p>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=labor&action=add'" style="padding: 12px 24px; background: var(--warning-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Labor Record
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
DELIVERY RECORDS
============================================ -->
<?php elseif ($subModule == 'delivery'): ?>

<?php if ($action == 'add'): ?>
<!-- DELIVERY RECORD FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-baby" style="color: var(--success-color);"></i> Delivery Record
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
                    Delivery ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., DEL-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Delivery Date <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="datetime-local" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Delivery Mode <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Mode</option>
                    <option value="spontaneous-vaginal">Spontaneous Vaginal Delivery</option>
                    <option value="assisted-vaginal">Assisted Vaginal Delivery</option>
                    <option value="c-section">C-Section</option>
                    <option value="vacuum">Vacuum Extraction</option>
                    <option value="forceps">Forceps Delivery</option>
                    <option value="breech">Breech Delivery</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Delivery Outcome <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Outcome</option>
                    <option value="live-birth">Live Birth</option>
                    <option value="stillbirth">Stillbirth</option>
                    <option value="maternal-death">Maternal Death</option>
                    <option value="neonatal-death">Neonatal Death</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Number of Babies <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="e.g., 1" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Attending Doctor <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Doctor</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Attending Midwife
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Midwife</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Complications
                </label>
                <input type="text" placeholder="Enter any complications" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="completed">Completed</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="transferred">Transferred</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Delivery Notes
                </label>
                <textarea rows="3" placeholder="Enter delivery notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save Delivery Record
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- DELIVERY RECORDS LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Delivery Records</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No delivery records found. Create a record to get started.</p>
        </div>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=delivery&action=add'" style="padding: 10px 20px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New Delivery Record
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-baby"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Delivery Records</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Record deliveries for maternity patients.</p>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=delivery&action=add'" style="padding: 12px 24px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Delivery Record
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
POSTNATAL CARE
============================================ -->
<?php elseif ($subModule == 'postnatal'): ?>

<?php if ($action == 'add'): ?>
<!-- POSTNATAL CARE FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-heart" style="color: var(--primary-color);"></i> Postnatal Care Record
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
                    PNC ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., PNC-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Delivery ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., DEL-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Postnatal Day <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="number" placeholder="Enter day number" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Maternal Condition <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Condition</option>
                    <option value="stable">Stable</option>
                    <option value="recovering">Recovering</option>
                    <option value="complications">Complications</option>
                    <option value="icu">ICU Required</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Breastfeeding Status
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="exclusive">Exclusive Breastfeeding</option>
                    <option value="mixed">Mixed Feeding</option>
                    <option value="formula">Formula Feeding</option>
                    <option value="not-established">Not Established</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Uterine Involution
                </label>
                <input type="text" placeholder="Uterine fundus height" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Lochia
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select</option>
                    <option value="normal">Normal</option>
                    <option value="heavy">Heavy</option>
                    <option value="foul-smelling">Foul Smelling</option>
                    <option value="scant">Scant</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Blood Pressure <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 120/80" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Temperature
                </label>
                <input type="text" placeholder="e.g., 98.6°F" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Attending Nurse <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Nurse</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="active">Active</option>
                    <option value="discharged">Discharged</option>
                    <option value="transferred">Transferred</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Postnatal Notes
                </label>
                <textarea rows="2" placeholder="Enter postnatal notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save PNC Record
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- POSTNATAL CARE LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Postnatal Care Records</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No PNC records found. Create a record to get started.</p>
        </div>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=postnatal&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> New PNC Record
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-heart"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Postnatal Care Records</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Manage postnatal care for mothers after delivery.</p>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=postnatal&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First PNC Record
        </button>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
NEWBORN REGISTRATION
============================================ -->
<?php elseif ($subModule == 'newborn'): ?>

<?php if ($action == 'add'): ?>
<!-- NEWBORN REGISTRATION FORM -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900); margin-bottom: 20px;">
        <i class="fas fa-baby" style="color: var(--primary-color);"></i> Newborn Registration
    </h3>
    <form>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Mother <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Mother</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Newborn ID <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., NB-2024-001" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Baby's First Name <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter baby's first name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Baby's Last Name <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="Enter baby's last name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Gender <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Date of Birth <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="datetime-local" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Birth Weight (kg) <span style="color: var(--danger-color);">*</span>
                </label>
                <input type="text" placeholder="e.g., 3.2 kg" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Birth Length (cm)
                </label>
                <input type="text" placeholder="e.g., 50 cm" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Head Circumference (cm)
                </label>
                <input type="text" placeholder="e.g., 35 cm" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Apgar Score <span style="color: var(--danger-color);">*</span>
                </label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <input type="text" placeholder="1 min" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                    <input type="text" placeholder="5 min" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Blood Group
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Blood Group</option>
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
                    Rh Factor
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="positive">Positive (+)</option>
                    <option value="negative">Negative (-)</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Delivered By
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="">Select Doctor</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Status <span style="color: var(--danger-color);">*</span>
                </label>
                <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                    <option value="active">Active</option>
                    <option value="nicu">NICU</option>
                    <option value="discharged">Discharged</option>
                    <option value="transferred">Transferred</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                    Newborn Notes
                </label>
                <textarea rows="2" placeholder="Enter newborn notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
            </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Register Newborn
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php else: ?>
<!-- NEWBORN REGISTRATION LIST -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900);">Newborn Registrations</h3>
            <p style="font-size: 13px; color: var(--gray-500);">No newborns registered. Register a newborn to get started.</p>
        </div>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=newborn&action=add'" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> Register Newborn
        </button>
    </div>

    <!-- Empty State -->
    <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 64px; color: var(--gray-300); margin-bottom: 16px;">
            <i class="fas fa-baby"></i>
        </div>
        <h3 style="font-size: 20px; font-weight: 600; color: var(--gray-600); margin-bottom: 8px;">No Newborns Registered</h3>
        <p style="color: var(--gray-400); margin-bottom: 20px;">Register newborns and record their details.</p>
        <button onclick="window.location.href='maternity.php?module=maternity&sub=newborn&action=add'" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer;">
            <i class="fas fa-plus"></i> First Newborn
        </button>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>