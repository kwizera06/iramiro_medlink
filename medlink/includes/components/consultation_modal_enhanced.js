/**
 * Phase 2: Enhanced Consultation Modal
 * Supports:
 * - Dynamic department forms
 * - Multi-item prescriptions
 * - Form submission during consultation
 */

class EnhancedConsultationModal {
    constructor(config = {}) {
        this.config = {
            apiBase: config.apiBase || '/api.php',
            onSave: config.onSave || null,
            ...config
        };
        this.currentConsultation = null;
        this.currentForms = [];
        this.prescriptions = [];
    }

    /**
     * Open consultation modal and load forms for department
     */
    async open(visitId, patientId, departmentId, doctorId) {
        console.log('Opening consultation modal for visit:', visitId, 'dept:', departmentId);
        
        try {
            // Load forms required for this department
            const formsResp = await fetch(`${this.config.apiBase}?endpoint=consultation/consult&action=get_forms&department_id=${departmentId}&visit_id=${visitId}`);
            if (!formsResp.ok) throw new Error('Failed to load forms');
            
            const formsData = await formsResp.json();
            if (!formsData.success) throw new Error(formsData.message || 'Failed to load forms');
            
            this.currentForms = formsData.data || [];
            this.visitId = visitId;
            this.patientId = patientId;
            this.departmentId = departmentId;
            this.doctorId = doctorId;
            this.prescriptions = [];
            
            // Render modal
            this._renderModal();
            
            // Show modal
            document.getElementById('consultation-modal').style.display = 'block';
            
        } catch (err) {
            console.error('Error opening consultation modal:', err);
            alert('Error loading consultation: ' + err.message);
        }
    }

    /**
     * Render the consultation modal UI
     */
    _renderModal() {
        const html = `
        <div id="consultation-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1000;overflow:auto;">
            <div style="background:white;margin:20px auto;border-radius:12px;max-width:1000px;box-shadow:0 20px 60px rgba(0,0,0,0.3);max-height:90vh;overflow:auto;">
                
                <!-- Header -->
                <div style="padding:24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
                    <h2 style="margin:0;font-size:20px;font-weight:600;">Consultation</h2>
                    <button onclick="document.getElementById('consultation-modal').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;color:#9ca3af;">&times;</button>
                </div>
                
                <!-- Body -->
                <div style="padding:24px;">
                    
                    <!-- Tabs -->
                    <div style="display:flex;border-bottom:2px solid #e5e7eb;margin-bottom:24px;">
                        <button class="consult-tab active" data-tab="vitals" style="padding:12px 24px;border:none;background:none;font-weight:500;border-bottom:3px solid #2563eb;color:#2563eb;cursor:pointer;margin-right:16px;">
                            Vitals & Diagnosis
                        </button>
                        <button class="consult-tab" data-tab="forms" style="padding:12px 24px;border:none;background:none;font-weight:500;color:#6b7280;cursor:pointer;margin-right:16px;">
                            Forms (${this.currentForms.length})
                        </button>
                        <button class="consult-tab" data-tab="prescriptions" style="padding:12px 24px;border:none;background:none;font-weight:500;color:#6b7280;cursor:pointer;">
                            Prescriptions (${this.prescriptions.length})
                        </button>
                    </div>
                    
                    <!-- Tab Content -->
                    
                    <!-- Vitals & Diagnosis Tab -->
                    <div class="consult-tab-content" data-content="vitals" style="display:block;">
                        ${this._renderVitalsForm()}
                    </div>
                    
                    <!-- Forms Tab -->
                    <div class="consult-tab-content" data-content="forms" style="display:none;">
                        ${this._renderFormsSection()}
                    </div>
                    
                    <!-- Prescriptions Tab -->
                    <div class="consult-tab-content" data-content="prescriptions" style="display:none;">
                        ${this._renderPrescriptionsSection()}
                    </div>
                    
                </div>
                
                <!-- Footer -->
                <div style="padding:24px;border-top:1px solid #e5e7eb;display:flex;gap:12px;justify-content:flex-end;">
                    <button onclick="document.getElementById('consultation-modal').style.display='none'" style="padding:10px 24px;background:white;border:1px solid #d1d5db;border-radius:8px;cursor:pointer;font-weight:500;">
                        Cancel
                    </button>
                    <button id="consult-save-btn" onclick="consultationModal.save()" style="padding:10px 24px;background:#2563eb;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:500;">
                        Save Consultation
                    </button>
                </div>
                
            </div>
        </div>
        `;
        
        // Check if modal exists, if not create it
        let modal = document.getElementById('consultation-modal');
        if (!modal) {
            const container = document.createElement('div');
            container.innerHTML = html;
            document.body.appendChild(container.firstElementChild);
            modal = document.getElementById('consultation-modal');
        } else {
            modal.parentElement.replaceChild(
                document.createRange().createContextualFragment(html).firstElementChild,
                modal
            );
        }
        
        // Attach tab listeners
        document.querySelectorAll('.consult-tab').forEach(btn => {
            btn.addEventListener('click', (e) => this._switchTab(e.target.dataset.tab));
        });
    }

    /**
     * Render vitals and diagnosis form
     */
    _renderVitalsForm() {
        return `
        <form id="vitals-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Systolic BP</label>
                <input type="number" id="bp_systolic" placeholder="e.g., 120" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Diastolic BP</label>
                <input type="number" id="bp_diastolic" placeholder="e.g., 80" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Pulse (bpm)</label>
                <input type="number" id="pulse" placeholder="e.g., 72" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Temperature (°C)</label>
                <input type="number" id="temperature" step="0.1" placeholder="e.g., 37.2" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Weight (kg)</label>
                <input type="number" id="weight" step="0.1" placeholder="e.g., 70" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Height (cm)</label>
                <input type="number" id="height" placeholder="e.g., 175" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">O₂ Saturation (%)</label>
                <input type="number" id="oxygen_sat" min="0" max="100" placeholder="e.g., 98" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            </div>
            <div>
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Respiratory Rate</label>
                <input type="number" id="respiratory_rate" placeholder="e.g., 16" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
            </div>
            
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Chief Complaint</label>
                <textarea id="chief_complaint" placeholder="Patient's main complaint" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-height:80px;font-family:inherit;"></textarea>
            </div>
            
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">History</label>
                <textarea id="history" placeholder="Patient history" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-height:80px;font-family:inherit;"></textarea>
            </div>
            
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Examination Findings</label>
                <textarea id="examination" placeholder="Physical examination results" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-height:80px;font-family:inherit;"></textarea>
            </div>
            
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Diagnosis</label>
                <textarea id="diagnosis" placeholder="Diagnosis" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-height:80px;font-family:inherit;"></textarea>
            </div>
            
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Treatment Plan</label>
                <textarea id="treatment_plan" placeholder="Treatment plan and notes" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-height:80px;font-family:inherit;"></textarea>
            </div>
            
            <div style="grid-column:1 / span 2;">
                <label style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">Outcome</label>
                <select id="outcome" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                    <option value="">Select outcome</option>
                    <option value="treated">Treated</option>
                    <option value="follow_up">Follow-up Required</option>
                    <option value="referred">Referred</option>
                    <option value="admitted">Admitted</option>
                    <option value="discharged">Discharged</option>
                </select>
            </div>
        </form>
        `;
    }

    /**
     * Render forms section with dynamic form fields
     */
    _renderFormsSection() {
        if (!this.currentForms.length) {
            return '<p style="color:#6b7280;text-align:center;padding:32px;">No forms required for this department</p>';
        }
        
        return `
        <div style="display:grid;gap:32px;">
            ${this.currentForms.map((form, idx) => this._renderForm(form, idx)).join('')}
        </div>
        `;
    }

    /**
     * Render individual form with fields
     */
    _renderForm(form, idx) {
        return `
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:20px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:15px;font-weight:600;color:#1f2937;">${form.name}</h3>
                <span style="background:#dbeafe;color:#1e40af;padding:4px 12px;border-radius:4px;font-size:12px;font-weight:500;">
                    ${form.is_submitted ? '✓ Submitted' : 'Not submitted'}
                </span>
            </div>
            ${form.description ? `<p style="color:#6b7280;font-size:13px;margin-bottom:16px;">${form.description}</p>` : ''}
            
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px;" id="form_${form.id}">
                ${form.fields.map(field => this._renderFormField(field, form.id)).join('')}
            </div>
        </div>
        `;
    }

    /**
     * Render individual form field
     */
    _renderFormField(field, formId) {
        const fieldId = `form_field_${formId}_${field.field_key}`;
        const value = this.currentForms.find(f => f.id === formId)?.form_data?.[field.field_key] || field.default_value || '';
        
        let input = '';
        
        switch (field.field_type) {
            case 'textarea':
                input = `<textarea id="${fieldId}" placeholder="${field.placeholder || ''}" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;min-height:100px;font-family:inherit;">${value}</textarea>`;
                break;
            case 'select':
                const options = field.field_options || [];
                input = `
                <select id="${fieldId}" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">
                    <option value="">Select ${field.field_label}</option>
                    ${options.map(opt => `<option value="${opt.value}" ${value === opt.value ? 'selected' : ''}>${opt.label}</option>`).join('')}
                </select>
                `;
                break;
            case 'checkbox':
                input = `<input type="checkbox" id="${fieldId}" ${value ? 'checked' : ''} style="width:18px;height:18px;cursor:pointer;">`;
                break;
            case 'date':
                input = `<input type="date" id="${fieldId}" value="${value}" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">`;
                break;
            case 'number':
                input = `<input type="number" id="${fieldId}" placeholder="${field.placeholder || ''}" value="${value}" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">`;
                break;
            default:
                input = `<input type="text" id="${fieldId}" placeholder="${field.placeholder || ''}" value="${value}" style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;">`;
        }
        
        const required = field.is_required ? ' <span style="color:#ef4444;">*</span>' : '';
        
        return `
        <div>
            <label for="${fieldId}" style="display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px;">${field.field_label}${required}</label>
            ${input}
            ${field.help_text ? `<p style="font-size:12px;color:#9ca3af;margin-top:4px;">${field.help_text}</p>` : ''}
        </div>
        `;
    }

    /**
     * Render prescriptions section
     */
    _renderPrescriptionsSection() {
        return `
        <div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:15px;font-weight:600;color:#1f2937;">Prescription Items</h3>
                <button id="add-presc-btn" onclick="consultationModal.addPrescriptionItem()" style="padding:8px 16px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;">
                    + Add Item
                </button>
            </div>
            
            <div id="prescriptions-list" style="display:grid;gap:16px;margin-bottom:20px;">
                ${this.prescriptions.map((presc, idx) => this._renderPrescriptionItem(presc, idx)).join('')}
            </div>
            
            ${this.prescriptions.length === 0 ? '<p style="color:#9ca3af;text-align:center;padding:32px;">No prescription items added yet</p>' : ''}
        </div>
        `;
    }

    /**
     * Render prescription item
     */
    _renderPrescriptionItem(presc, idx) {
        return `
        <div style="border:1px solid #d1d5db;border-radius:8px;padding:16px;background:white;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                <h4 style="margin:0;font-size:14px;font-weight:500;">${presc.item_name || 'Medication Item'}</h4>
                <button onclick="consultationModal.removePrescriptionItem(${idx})" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:18px;padding:0;">×</button>
            </div>
            
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
                <div>
                    <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">Dosage</label>
                    <input type="text" value="${presc.dosage || ''}" onchange="consultationModal.updatePrescriptionItem(${idx}, 'dosage', this.value)" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">Frequency</label>
                    <input type="text" value="${presc.frequency || ''}" onchange="consultationModal.updatePrescriptionItem(${idx}, 'frequency', this.value)" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">Duration</label>
                    <input type="text" value="${presc.duration || ''}" onchange="consultationModal.updatePrescriptionItem(${idx}, 'duration', this.value)" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
                <div>
                    <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">Quantity</label>
                    <input type="number" value="${presc.quantity || ''}" onchange="consultationModal.updatePrescriptionItem(${idx}, 'quantity', this.value)" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                </div>
            </div>
            
            <div style="margin-top:12px;">
                <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">Instructions</label>
                <textarea onchange="consultationModal.updatePrescriptionItem(${idx}, 'instructions', this.value)" style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;min-height:60px;font-family:inherit;">${presc.instructions || ''}</textarea>
            </div>
        </div>
        `;
    }

    /**
     * Switch tab
     */
    _switchTab(tabName) {
        document.querySelectorAll('.consult-tab').forEach(btn => {
            btn.style.color = btn.dataset.tab === tabName ? '#2563eb' : '#6b7280';
            btn.style.borderBottomColor = btn.dataset.tab === tabName ? '#2563eb' : 'transparent';
        });
        
        document.querySelectorAll('.consult-tab-content').forEach(content => {
            content.style.display = content.dataset.content === tabName ? 'block' : 'none';
        });
    }

    /**
     * Add prescription item
     */
    addPrescriptionItem() {
        this.prescriptions.push({
            item_id: null,
            item_name: '',
            dosage: '',
            frequency: '',
            duration: '',
            quantity: 0,
            unit: 'tablet(s)',
            route: 'oral',
            instructions: '',
            is_controlled: 0
        });
        
        // Re-render prescriptions section
        const oldContent = document.querySelector('[data-content="prescriptions"]');
        if (oldContent) {
            oldContent.innerHTML = this._renderPrescriptionsSection();
        }
    }

    /**
     * Remove prescription item
     */
    removePrescriptionItem(idx) {
        this.prescriptions.splice(idx, 1);
        
        // Re-render prescriptions section
        const oldContent = document.querySelector('[data-content="prescriptions"]');
        if (oldContent) {
            oldContent.innerHTML = this._renderPrescriptionsSection();
        }
    }

    /**
     * Update prescription item field
     */
    updatePrescriptionItem(idx, field, value) {
        if (this.prescriptions[idx]) {
            this.prescriptions[idx][field] = value;
        }
    }

    /**
     * Save consultation with forms and prescriptions
     */
    async save() {
        console.log('Saving consultation...');
        
        try {
            const btn = document.getElementById('consult-save-btn');
            btn.disabled = true;
            btn.textContent = 'Saving...';
            
            // Collect vitals data
            const vitalsData = {
                bp_systolic: document.getElementById('bp_systolic')?.value || null,
                bp_diastolic: document.getElementById('bp_diastolic')?.value || null,
                pulse: document.getElementById('pulse')?.value || null,
                temperature: document.getElementById('temperature')?.value || null,
                weight: document.getElementById('weight')?.value || null,
                height: document.getElementById('height')?.value || null,
                oxygen_sat: document.getElementById('oxygen_sat')?.value || null,
                respiratory_rate: document.getElementById('respiratory_rate')?.value || null,
                chief_complaint: document.getElementById('chief_complaint')?.value || null,
                history: document.getElementById('history')?.value || null,
                examination: document.getElementById('examination')?.value || null,
                diagnosis: document.getElementById('diagnosis')?.value || null,
                treatment_plan: document.getElementById('treatment_plan')?.value || null,
                outcome: document.getElementById('outcome')?.value || null,
                status: document.getElementById('outcome')?.value === 'discharged' ? 'completed' : 'in_progress'
            };
            
            // Collect form data
            const formsData = [];
            for (const form of this.currentForms) {
                const formData = {};
                for (const field of form.fields) {
                    const fieldId = `form_field_${form.id}_${field.field_key}`;
                    const element = document.getElementById(fieldId);
                    if (element) {
                        if (field.field_type === 'checkbox') {
                            formData[field.field_key] = element.checked;
                        } else {
                            formData[field.field_key] = element.value;
                        }
                    }
                }
                
                formsData.push({
                    template_id: form.id,
                    form_data: formData,
                    status: 'completed'
                });
            }
            
            // Prepare payload
            const payload = {
                visit_id: this.visitId,
                patient_id: this.patientId,
                department_id: this.departmentId,
                doctor_id: this.doctorId,
                created_by: this.doctorId,
                ...vitalsData,
                forms: formsData,
                prescriptions: this.prescriptions.filter(p => p.item_id)
            };
            
            console.log('Payload:', payload);
            
            // Save consultation
            const resp = await fetch(`${this.config.apiBase}?endpoint=consultation/consult&action=save_with_forms`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            const result = await resp.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Failed to save consultation');
            }
            
            console.log('Consultation saved:', result.data);
            
            // Close modal
            document.getElementById('consultation-modal').style.display = 'none';
            
            // Call callback
            if (this.config.onSave) {
                this.config.onSave(result.data);
            }
            
            alert('Consultation saved successfully!');
            
        } catch (err) {
            console.error('Error saving consultation:', err);
            alert('Error saving consultation: ' + err.message);
            
            const btn = document.getElementById('consult-save-btn');
            btn.disabled = false;
            btn.textContent = 'Save Consultation';
        }
    }
}

// Global instance
let consultationModal = null;

document.addEventListener('DOMContentLoaded', () => {
    if (typeof consultationModal === 'undefined' || !consultationModal) {
        consultationModal = new EnhancedConsultationModal();
    }
});
