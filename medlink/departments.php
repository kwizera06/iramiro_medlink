<?php
// public/departments.php
require_once __DIR__ . '/includes/header.php';

// Get sub-module parameter and action
$subModule = isset($_GET['sub']) ? $_GET['sub'] : 'opd';
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$patientId = isset($_GET['patient_id']) ? $_GET['patient_id'] : null;

// Department configurations with unique forms
$departments = [
    'opd' => [
        'name' => 'General OPD',
        'icon' => 'fa-clinic-medical',
        'color' => '#2563eb',
        'description' => 'Outpatient consultation and routine checkups',
        'forms' => [
            'opd_assessment' => 'OPD Assessment Form',
            'vital_signs' => 'Vital Signs Recording',
            'prescription' => 'Prescription Form'
        ]
    ],
    'emergency' => [
        'name' => 'Emergency Department',
        'icon' => 'fa-ambulance',
        'color' => '#ef4444',
        'description' => 'Emergency and trauma care',
        'forms' => [
            'triage_form' => 'Triage Assessment Form',
            'trauma_management' => 'Trauma Management Form',
            'emergency_discharge' => 'Emergency Discharge Summary'
        ]
    ],
    'icu' => [
        'name' => 'Intensive Care Unit (ICU)',
        'icon' => 'fa-heartbeat',
        'color' => '#dc2626',
        'description' => 'Critical care and intensive monitoring',
        'forms' => [
            'icu_assessment' => 'ICU Assessment Form',
            'ventilator_chart' => 'Ventilator Monitoring Chart',
            'daily_icu_chart' => 'Daily ICU Progress Chart'
        ]
    ],
    'nicu' => [
        'name' => 'Neonatal ICU (NICU)',
        'icon' => 'fa-baby',
        'color' => '#8b5cf6',
        'description' => 'Newborn and premature baby care',
        'forms' => [
            'nicu_assessment' => 'NICU Admission Assessment',
            'neonatal_observations' => 'Neonatal Observation Chart',
            'feeding_chart' => 'Feeding & Nutrition Chart'
        ]
    ],
    'pediatrics' => [
        'name' => 'Pediatrics',
        'icon' => 'fa-child',
        'color' => '#22c55e',
        'description' => 'Child healthcare and development',
        'forms' => [
            'pediatric_assessment' => 'Pediatric Assessment Form',
            'growth_chart' => 'Growth & Development Chart',
            'vaccination_record' => 'Vaccination Record'
        ]
    ],
    'surgery' => [
        'name' => 'Surgery Department',
        'icon' => 'fa-scalpel',
        'color' => '#7c3aed',
        'description' => 'Surgical procedures and operations',
        'forms' => [
            'pre_surgical_assessment' => 'Pre-Surgical Assessment',
            'operative_notes' => 'Operative Notes',
            'post_surgical_care' => 'Post-Surgical Care Plan'
        ]
    ],
    'orthopedics' => [
        'name' => 'Orthopedics',
        'icon' => 'fa-bone',
        'color' => '#f59e0b',
        'description' => 'Bone, joint, and musculoskeletal care',
        'forms' => [
            'orthopedic_assessment' => 'Orthopedic Assessment',
            'fracture_management' => 'Fracture Management Form',
            'cast_application' => 'Cast Application Record'
        ]
    ],
    'cardiology' => [
        'name' => 'Cardiology',
        'icon' => 'fa-heart',
        'color' => '#ef4444',
        'description' => 'Heart and cardiovascular care',
        'forms' => [
            'cardiac_assessment' => 'Cardiac Assessment Form',
            'ecg_recording' => 'ECG Recording Form',
            'cardiac_rehabilitation' => 'Cardiac Rehabilitation Plan'
        ]
    ],
    'neurology' => [
        'name' => 'Neurology',
        'icon' => 'fa-brain',
        'color' => '#8b5cf6',
        'description' => 'Brain and nervous system care',
        'forms' => [
            'neurological_assessment' => 'Neurological Assessment',
            'stroke_protocol' => 'Stroke Protocol Form',
            'seizure_management' => 'Seizure Management Form'
        ]
    ],
    'oncology' => [
        'name' => 'Oncology',
        'icon' => 'fa-ribbon',
        'color' => '#ec4899',
        'description' => 'Cancer diagnosis and treatment',
        'forms' => [
            'oncological_assessment' => 'Oncological Assessment',
            'chemotherapy_plan' => 'Chemotherapy Plan Form',
            'palliative_care' => 'Palliative Care Plan'
        ]
    ],
    'physiotherapy' => [
        'name' => 'Physiotherapy',
        'icon' => 'fa-dumbbell',
        'color' => '#14b8a6',
        'description' => 'Physical therapy and rehabilitation',
        'forms' => [
            'physiotherapy_assessment' => 'Physiotherapy Assessment',
            'exercise_plan' => 'Exercise & Treatment Plan',
            'pain_assessment' => 'Pain Assessment Form'
        ]
    ],
    'radiology' => [
        'name' => 'Radiology',
        'icon' => 'fa-x-ray',
        'color' => '#3b82f6',
        'description' => 'Diagnostic imaging services',
        'forms' => [
            'radiology_request' => 'Radiology Request Form',
            'xray_report' => 'X-Ray Report Form',
            'mri_report' => 'MRI Report Form'
        ]
    ],
    'pharmacy' => [
        'name' => 'Pharmacy',
        'icon' => 'fa-prescription',
        'color' => '#22c55e',
        'description' => 'Pharmaceutical services and dispensing',
        'forms' => [
            'prescription_dispensing' => 'Prescription Dispensing Form',
            'drug_interaction_check' => 'Drug Interaction Check',
            'controlled_drug_registry' => 'Controlled Drug Registry'
        ]
    ],
    'dental' => [
        'name' => 'Dental Clinic',
        'icon' => 'fa-tooth',
        'color' => '#f59e0b',
        'description' => 'Dental care and procedures',
        'forms' => [
            'dental_assessment' => 'Dental Assessment Form',
            'odontogram' => 'Odontogram Chart',
            'treatment_plan' => 'Dental Treatment Plan'
        ]
    ],
    'eye' => [
        'name' => 'Eye Clinic',
        'icon' => 'fa-eye',
        'color' => '#3b82f6',
        'description' => 'Vision and eye care services',
        'forms' => [
            'vision_testing' => 'Vision Testing Form',
            'glaucoma_assessment' => 'Glaucoma Assessment',
            'cataract_surgical' => 'Cataract Surgical Form'
        ]
    ],
    'maternity' => [
        'name' => 'Maternity',
        'icon' => 'fa-baby-carriage',
        'color' => '#ec4899',
        'description' => 'Pregnancy and childbirth care',
        'forms' => [
            'antenatal_assessment' => 'Antenatal Assessment Form',
            'labor_monitoring' => 'Labor Monitoring Chart',
            'delivery_record' => 'Delivery Record Form'
        ]
    ],
    'dialysis' => [
        'name' => 'Dialysis',
        'icon' => 'fa-water',
        'color' => '#14b8a6',
        'description' => 'Kidney dialysis and renal care',
        'forms' => [
            'dialysis_assessment' => 'Dialysis Assessment Form',
            'hemodialysis_record' => 'Hemodialysis Record',
            'fluid_balance_chart' => 'Fluid Balance Chart'
        ]
    ]
];

// Get current department data
$currentDept = isset($departments[$subModule]) ? $departments[$subModule] : $departments['opd'];
$deptName = $currentDept['name'];
$deptColor = $currentDept['color'];
$deptIcon = $currentDept['icon'];

// Function to render department-specific form
function renderDepartmentForm($deptKey, $patientId) {
    switch($deptKey) {
        case 'opd':
            return renderOPDForm($patientId);
        case 'emergency':
            return renderEmergencyForm($patientId);
        case 'icu':
            return renderICUForm($patientId);
        case 'nicu':
            return renderNICUForm($patientId);
        case 'pediatrics':
            return renderPediatricsForm($patientId);
        case 'surgery':
            return renderSurgeryForm($patientId);
        case 'orthopedics':
            return renderOrthopedicsForm($patientId);
        case 'cardiology':
            return renderCardiologyForm($patientId);
        case 'neurology':
            return renderNeurologyForm($patientId);
        case 'oncology':
            return renderOncologyForm($patientId);
        case 'physiotherapy':
            return renderPhysiotherapyForm($patientId);
        case 'radiology':
            return renderRadiologyForm($patientId);
        case 'pharmacy':
            return renderPharmacyForm($patientId);
        case 'dental':
            return renderDentalForm($patientId);
        case 'eye':
            return renderEyeForm($patientId);
        case 'maternity':
            return renderMaternityForm($patientId);
        case 'dialysis':
            return renderDialysisForm($patientId);
        default:
            return renderOPDForm($patientId);
    }
}

// ============================================
// DEPARTMENT-SPECIFIC FORM RENDERERS
// ============================================

function renderOPDForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Reason for Visit <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter reason for visit" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Symptoms <span style="color: var(--danger-color);">*</span>
            </label>
            <textarea rows="2" placeholder="Enter patient symptoms" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Blood Pressure <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., 120/80 mmHg" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Diagnosis <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter diagnosis" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Prescription
            </label>
            <textarea rows="2" placeholder="Enter prescription details" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderEmergencyForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Triage Category <span style="color: var(--danger-color);">*</span>
            </label>
            <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select Category</option>
                <option value="red">Red - Immediate (Life Threatening)</option>
                <option value="yellow">Yellow - Urgent</option>
                <option value="green">Green - Non-Urgent</option>
                <option value="black">Black - Deceased</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Mode of Arrival <span style="color: var(--danger-color);">*</span>
            </label>
            <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select Mode</option>
                <option value="ambulance">Ambulance</option>
                <option value="walk-in">Walk-in</option>
                <option value="referral">Referral</option>
                <option value="self">Self</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Chief Complaints <span style="color: var(--danger-color);">*</span>
            </label>
            <textarea rows="2" placeholder="Enter chief complaints" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Injury Type
            </label>
            <input type="text" placeholder="Enter injury type (e.g., Blunt, Penetrating, Burn)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Emergency Treatment Given
            </label>
            <textarea rows="2" placeholder="Enter emergency treatment given" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderICUForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                ICU Admission Diagnosis <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter ICU admission diagnosis" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Ventilator Settings
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Mode" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="FIO2 (%)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Tidal Volume (ml)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="PEEP (cmH2O)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                GCS Score <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., 15/15" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Critical Observations
            </label>
            <textarea rows="2" placeholder="Enter critical observations" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                ICU Daily Progress Notes
            </label>
            <textarea rows="2" placeholder="Enter daily progress notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderNICUForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Baby\'s Name <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter baby\'s name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Gestational Age <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., 32 weeks" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Birth Weight <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., 1.8 kg" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Apgar Score <span style="color: var(--danger-color);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="1 minute" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="5 minute" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Feeding Chart
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Type" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Amount (ml)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Frequency" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
    </div>';
}

function renderPediatricsForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Child\'s Age <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., 3 years 2 months" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Height & Weight <span style="color: var(--danger-color);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Height (cm)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Weight (kg)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Vaccination Status
            </label>
            <textarea rows="2" placeholder="Enter vaccination history (with dates)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Developmental Milestones
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Gross Motor" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Fine Motor" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Language" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Social" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Pediatric Diagnosis
            </label>
            <input type="text" placeholder="Enter diagnosis" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
    </div>';
}

function renderSurgeryForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Surgical Procedure <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter surgical procedure name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Pre-Surgical Assessment <span style="color: var(--danger-color);">*</span>
            </label>
            <textarea rows="2" placeholder="Enter pre-surgical assessment" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Surgeon Name <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter surgeon name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Operative Notes
            </label>
            <textarea rows="3" placeholder="Enter detailed operative notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Post-Surgical Care Plan
            </label>
            <textarea rows="2" placeholder="Enter post-surgical care plan" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderOrthopedicsForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Affected Area <span style="color: var(--danger-color);">*</span>
            </label>
            <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select Area</option>
                <option value="shoulder">Shoulder</option>
                <option value="elbow">Elbow</option>
                <option value="wrist">Wrist</option>
                <option value="hip">Hip</option>
                <option value="knee">Knee</option>
                <option value="ankle">Ankle</option>
                <option value="spine">Spine</option>
                <option value="neck">Neck</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Fracture Type <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., Simple, Compound, Comminuted" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                X-Ray Findings
            </label>
            <textarea rows="2" placeholder="Enter X-ray findings" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Cast/Splint Applied
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Type (e.g., POP, Fiberglass)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Duration (weeks)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Orthopedic Diagnosis
            </label>
            <input type="text" placeholder="Enter diagnosis" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
    </div>';
}

function renderCardiologyForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Cardiac Symptoms <span style="color: var(--danger-color);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Chest Pain Type" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Duration" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                ECG Findings <span style="color: var(--danger-color);">*</span>
            </label>
            <textarea rows="2" placeholder="Enter ECG findings" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Ejection Fraction (%)
            </label>
            <input type="text" placeholder="e.g., 55%" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Cardiac Diagnosis
            </label>
            <input type="text" placeholder="Enter cardiac diagnosis" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Cardiac Rehabilitation Plan
            </label>
            <textarea rows="2" placeholder="Enter rehabilitation plan" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderNeurologyForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Neurological Symptoms <span style="color: var(--danger-color);">*</span>
            </label>
            <textarea rows="2" placeholder="Enter neurological symptoms" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Stroke Assessment <span style="color: var(--danger-color);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="FAST Assessment" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="NIHSS Score" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Seizure Management
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Seizure Type" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Duration" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Neurological Diagnosis
            </label>
            <input type="text" placeholder="Enter diagnosis" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Neurological Examination Findings
            </label>
            <textarea rows="2" placeholder="Enter examination findings" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderOncologyForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Cancer Type <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter cancer type" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Cancer Stage <span style="color: var(--danger-color);">*</span>
            </label>
            <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select Stage</option>
                <option value="stage0">Stage 0 (Carcinoma in situ)</option>
                <option value="stage1">Stage I</option>
                <option value="stage2">Stage II</option>
                <option value="stage3">Stage III</option>
                <option value="stage4">Stage IV (Metastatic)</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Chemotherapy Plan
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Regimen Name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Cycle Duration" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Radiotherapy Plan
            </label>
            <input type="text" placeholder="Enter radiotherapy details" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Palliative Care Plan
            </label>
            <textarea rows="2" placeholder="Enter palliative care plan" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderPhysiotherapyForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Functional Limitations <span style="color: var(--danger-color);">*</span>
            </label>
            <textarea rows="2" placeholder="Enter functional limitations" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Pain Assessment <span style="color: var(--danger-color);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Pain Scale (0-10)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Pain Type (e.g., Sharp, Dull)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Exercise Plan
            </label>
            <textarea rows="2" placeholder="Enter exercise plan" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Range of Motion (ROM)
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Active ROM" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Passive ROM" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Progress Notes
            </label>
            <textarea rows="2" placeholder="Enter progress notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderRadiologyForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Imaging Type <span style="color: var(--danger-color);">*</span>
            </label>
            <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select Imaging Type</option>
                <option value="xray">X-Ray</option>
                <option value="mri">MRI</option>
                <option value="ct">CT Scan</option>
                <option value="ultrasound">Ultrasound</option>
                <option value="mammography">Mammography</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Body Part <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter body part (e.g., Chest, Knee, Head)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Clinical History
            </label>
            <textarea rows="2" placeholder="Enter clinical history" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Findings & Impression <span style="color: var(--danger-color);">*</span>
            </label>
            <textarea rows="3" placeholder="Enter radiology findings and impression" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Recommendation
            </label>
            <input type="text" placeholder="Enter recommendation" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
    </div>';
}

function renderPharmacyForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Medication Name <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter medication name" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Dosage <span style="color: var(--danger-color);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Strength" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Frequency" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Duration (days)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Drug Interaction Check
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Check with existing meds" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Allergy Check" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Controlled Drug Registry
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Drug Schedule" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Quantity Dispensed" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Pharmacy Notes
            </label>
            <textarea rows="2" placeholder="Additional pharmacy notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderDentalForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Dental Complaint <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter dental complaint" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Tooth Number <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., 18, 32, etc." style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Odontogram Findings
            </label>
            <textarea rows="2" placeholder="Enter odontogram findings" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Dental Procedure
            </label>
            <input type="text" placeholder="e.g., Root Canal, Extraction, Filling" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Dental Treatment Plan
            </label>
            <textarea rows="2" placeholder="Enter treatment plan" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderEyeForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Visual Acuity <span style="color: var(--danger-color);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Right Eye" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Left Eye" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Intraocular Pressure
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Right Eye (mmHg)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Left Eye (mmHg)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Eye Diagnosis <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="Enter diagnosis (e.g., Glaucoma, Cataract)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Eye Examination Findings
            </label>
            <textarea rows="2" placeholder="Enter eye examination findings" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Treatment Plan
            </label>
            <textarea rows="2" placeholder="Enter treatment plan" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderMaternityForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Gravida/Para <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., G3 P2" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Gestational Age <span style="color: var(--danger-color);">*</span>
            </label>
            <input type="text" placeholder="e.g., 38 weeks" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Labor Progress
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Cervical Dilatation" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Contractions (mins)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Delivery Record
            </label>
            <textarea rows="2" placeholder="Enter delivery details" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Antenatal / Postnatal Notes
            </label>
            <textarea rows="2" placeholder="Enter antenatal/postnatal notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}

function renderDialysisForm($patientId) {
    return '
    <div style="display: grid; gap: 16px;">
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Dialysis Type <span style="color: var(--danger-color);">*</span>
            </label>
            <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                <option value="">Select Type</option>
                <option value="hemodialysis">Hemodialysis</option>
                <option value="peritoneal">Peritoneal Dialysis</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Dialysis Session Details
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Duration (hours)" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Machine Number" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Fluid Balance <span style="color: var(--danger-color);">*</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                <input type="text" placeholder="Pre-Dialysis Weight" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Post-Dialysis Weight" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                <input type="text" placeholder="Fluid Removed" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Vascular Access
            </label>
            <input type="text" placeholder="e.g., AV Fistula, Catheter" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
        </div>
        <div>
            <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                Dialysis Notes
            </label>
            <textarea rows="2" placeholder="Enter dialysis notes" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
        </div>
    </div>';
}
?>

<!-- Rest of the page (header, active patients, forms list, activities) remains the same -->
<!-- ... (previous code for header, transfer modal, active patients, and activities) ... -->

<!-- Department Forms List -->
<h3 style="font-size: 16px; font-weight: 600; color: var(--gray-900); margin-bottom: 16px;">
    <i class="fas fa-file-medical" style="color: var(--primary-color);"></i> Department Forms & Records
</h3>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
    <?php foreach ($currentDept['forms'] as $formKey => $formName): ?>
    <div style="background: white; border-radius: var(--radius); padding: 20px; box-shadow: var(--shadow); border-left: 4px solid <?php echo $deptColor; ?>;">
        <div style="display: flex; align-items: start; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 8px; background: <?php echo $deptColor; ?>20; display: flex; align-items: center; justify-content: center; color: <?php echo $deptColor; ?>;">
                <i class="fas fa-file-alt"></i>
            </div>
            <div style="flex: 1;">
                <h4 style="font-size: 14px; font-weight: 600; color: var(--gray-900);"><?php echo $formName; ?></h4>
                <p style="font-size: 12px; color: var(--gray-500); margin-top: 4px;">
                    <?php echo $deptName; ?> assessment and documentation form
                </p>
                <div style="margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <button onclick="window.location.href='departments.php?sub=<?php echo $subModule; ?>&action=form&patient_id=P-1001'" style="padding: 4px 14px; background: <?php echo $deptColor; ?>; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                        <i class="fas fa-pen"></i> Fill Form
                    </button>
                    <button style="padding: 4px 14px; background: white; color: var(--gray-600); border: 1px solid var(--gray-200); border-radius: 6px; font-size: 12px; cursor: pointer;">
                        <i class="fas fa-history"></i> Records
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- If action is 'form' and patient is selected, show the department-specific form -->
<?php if ($action == 'form' && $patientId): ?>
<!-- Fill Form Section -->
<div style="background: white; border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); margin-bottom: 24px; border-top: 4px solid <?php echo $deptColor; ?>;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 18px; font-weight: 600; color: var(--gray-900);">
                <i class="fas fa-file-medical" style="color: var(--primary-color);"></i> <?php echo $deptName; ?> Assessment            </h3>
            <p style="color: var(--gray-500); font-size: 14px;">Patient: <?php echo $patientId; ?></p>
        </div>
        <button onclick="window.location.href='departments.php?sub=<?php echo $subModule; ?>'" style="padding: 8px 16px; background: white; color: var(--gray-600); border: 1px solid var(--gray-200); border-radius: 6px; cursor: pointer;">
            <i class="fas fa-arrow-left"></i> Back
        </button>
    </div>

    <form>
        <!-- Common Patient Information -->
        <div style="margin-bottom: 24px;">
            <h4 style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-user" style="color: var(--primary-color);"></i> Patient Information
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Patient ID
                    </label>
                    <input type="text" value="<?php echo $patientId; ?>" readonly style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: var(--gray-50);">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Full Name <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="text" placeholder="Enter patient name" value="John Doe" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Age <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="number" placeholder="Enter age" value="45" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Gender <span style="color: var(--danger-color);">*</span>
                    </label>
                    <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                        <option value="male" selected>Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Common Vital Signs -->
        <div style="margin-bottom: 24px;">
            <h4 style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-heartbeat" style="color: var(--danger-color);"></i> Vital Signs
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        BP <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="text" placeholder="120/80" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        HR <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="text" placeholder="72 bpm" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Temp <span style="color: var(--danger-color);">*</span>
                    </label>
                    <input type="text" placeholder="98.6°F" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        O2 Sat
                    </label>
                    <input type="text" placeholder="98%" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
            </div>
        </div>

        <!-- Department-Specific Form Fields -->
        <div style="margin-bottom: 24px;">
            <h4 style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-stethoscope" style="color: <?php echo $deptColor; ?>;"></i> <?php echo $deptName; ?> Assessment
            </h4>
            <?php echo renderDepartmentForm($subModule, $patientId); ?>
        </div>

        <!-- Follow-up -->
        <div style="margin-bottom: 24px;">
            <h4 style="font-size: 15px; font-weight: 600; color: var(--gray-900); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-calendar-check" style="color: var(--success-color);"></i> Follow-up
            </h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Follow-up Date
                    </label>
                    <input type="date" style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 500; color: var(--gray-700); margin-bottom: 4px;">
                        Disposition
                    </label>
                    <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: 8px; font-size: 14px; background: white;">
                        <option value="">Select Disposition</option>
                        <option value="discharge">Discharge</option>
                        <option value="admit">Admit to Ward</option>
                        <option value="transfer">Transfer to Another Department</option>
                        <option value="refer">Refer to Specialist</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div style="display: flex; gap: 12px; padding-top: 16px; border-top: 1px solid var(--gray-200);">
            <button type="submit" style="flex: 1; padding: 12px; background: <?php echo $deptColor; ?>; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> Save Assessment
            </button>
            <button type="submit" style="flex: 1; padding: 12px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                <i class="fas fa-check-circle"></i> Save & Complete
            </button>
            <button type="reset" style="padding: 12px 24px; background: white; color: var(--gray-700); border: 1px solid var(--gray-200); border-radius: 8px; font-weight: 500; cursor: pointer;">
                <i class="fas fa-undo"></i> Reset
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>