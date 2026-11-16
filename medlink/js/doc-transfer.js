// External Patient Transfer Form. Shared by the form page (#et-form) and the Generated Documents viewer (etBody).
const _etBase = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
const ET_INSURERS = ['RSSB', 'MMI', 'UAP', 'RADIANT', 'PRIME', 'SANLAM', 'BRITAM', 'EDEN CARE', 'RBA', 'PRIVATE'];
// [key, label, type, columns of 6]
const ET_SECTIONS = [
  ['1. Patient Information', [
    ['name', "Patient's Full Name", 'text', 6], ['age', 'Age', 'text', 2], ['dob', 'Date of Birth', 'date', 2], ['sex', 'Sex', 'sex', 2],
    ['parent', 'Parent/Caregiver', 'text', 4], ['parent_tel', 'Tel', 'text', 2],
    ['district', 'District', 'text', 2], ['sector', 'Sector', 'text', 2], ['cell', 'Cell', 'text', 1], ['village', 'Village', 'text', 1]]],
  ['2. Transfer Details', [
    ['admission', 'Date and Time of Admission', 'datetime-local', 3], ['decision', 'Date and Time of Transfer Decision', 'datetime-local', 3],
    ['facility', 'Receiving Health Facility', 'text', 3], ['department', 'Receiving Department/Service', 'text', 3],
    ['contact_time', 'Time of Initial Contact', 'time', 2], ['contact_staff', 'Staff Contacted at Receiving Facility', 'text', 2], ['contact_tel', 'Telephone Number', 'text', 2],
    ['transfer_type', 'Type of Transfer', 'select:Emergency|Non-Emergency', 3], ['reason', 'Reason for Transfer', 'area', 6]]],
  ['3. Clinical Assessment', [
    ['bp', 'Blood Pressure (BP)', 'text', 2], ['temp', 'Temperature (°C)', 'text', 2], ['hr', 'Heart Rate (HR)', 'text', 2],
    ['rr', 'Respiratory Rate (RR)', 'text', 2], ['spo2', 'Oxygen Saturation (SpO₂)', 'text', 2], ['weight', 'Weight (kg)', 'number', 2],
    ['height', 'Height (cm)', 'number', 2], ['bmi', 'BMI (kg/m²)', 'text', 2],
    ['presentation', 'Clinical Presentation', 'area', 6], ['condition', "Patient's Immediate Condition Before Transfer", 'area', 6]]],
  ['4–6. Investigations, Diagnosis, Treatment', [
    ['labs', 'Laboratory Investigations and Results', 'area', 6], ['diagnosis', 'Diagnosis', 'area', 6], ['treatment', 'Treatment and Management Provided', 'area', 6]]],
  ['7–8. Insurance & Referring Provider', [
    ['insurance', 'Health Insurance', 'select:|' + ET_INSURERS.join('|'), 2],
    ['ref_name', 'Referring Provider Full Name', 'text', 4], ['ref_qual', 'Professional Qualification', 'text', 2],
    ['ref_date', 'Date', 'date', 2], ['ref_tel', 'Telephone Number', 'text', 2]]]
];
const ET_KEYS = ET_SECTIONS.flatMap(s => s[1].map(f => f[0]));

let etHosp = {}, etPatient = null, etRef = null, etTimer = null;
const etEl = k => document.getElementById('et-' + k);
const etEsc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const etDots = v => (v !== undefined && v !== null && String(v).trim() !== '') ? etEsc(String(v).trim()) : '....................';
const etMulti = v => (v && String(v).trim()) ? etEsc(v).replace(/\n/g, '<br>') : '.............................................................................................<br>.............................................................................................';
const etDT = v => v ? etEsc(String(v).replace('T', ' ')) : etDots('');
const etDate = v => { if (!v) return etDots(''); const d = new Date(v); return isNaN(d) ? etEsc(v) : String(d.getDate()).padStart(2, '0') + ' / ' + String(d.getMonth() + 1).padStart(2, '0') + ' / ' + d.getFullYear(); };
const etBox = on => on ? '☑' : '☐';

// Document body for sections 1-8. d = saved/live data object.
function etBody(d) {
  const h = t => `<h3 style="font-size:13px;font-weight:700;margin:12px 0 5px;color:#0f172a;">${t}</h3>`;
  const row = (pairs) => `<tr>${pairs.map(([l, v]) => `<td style="padding:3px 6px 3px 0;font-weight:600;white-space:nowrap;">${l}:</td><td style="padding:3px 12px 3px 0;">${v}</td>`).join('')}</tr>`;
  const tbl = rows => `<table style="width:100%;font-size:12px;border-collapse:collapse;">${rows.join('')}</table>`;
  const sex = (d.sex || '').toLowerCase();
  const vitals = [['Blood Pressure (BP)', d.bp, ''], ['Temperature', d.temp, '°C'], ['Heart Rate (HR)', d.hr, 'bpm'], ['Respiratory Rate (RR)', d.rr, '/min'],
    ['Oxygen Saturation (SpO₂)', d.spo2, '%'], ['Weight', d.weight, 'kg'], ['Height', d.height, 'cm'], ['BMI', d.bmi, 'kg/m²']];
  const box = v => `<div style="font-size:12px;line-height:1.5;border:1px solid #cbd5e1;border-radius:4px;padding:6px 10px;min-height:34px;">${etMulti(v)}</div>`;
  return `<div style="font-size:12px;color:#0f172a;">
    ${h('1. PATIENT INFORMATION')}
    ${tbl([
      row([["Patient's Full Name", `<strong>${etDots(d.name)}</strong>`]]),
      row([['Age', etDots(d.age)], ['Date of Birth', etDate(d.dob)], ['Sex', `${etBox(sex === 'male')} Male &nbsp; ${etBox(sex === 'female')} Female`]]),
      row([['Parent/Caregiver', etDots(d.parent)], ['Tel', etDots(d.parent_tel)]]),
      row([['District', etDots(d.district)], ['Sector', etDots(d.sector)], ['Cell', etDots(d.cell)], ['Village', etDots(d.village)]])])}
    ${h('2. TRANSFER DETAILS')}
    ${tbl([
      row([['Date and Time of Admission', etDT(d.admission)]]),
      row([['Date and Time of Transfer Decision', etDT(d.decision)]]),
      row([['Receiving Health Facility', etDots(d.facility)], ['Receiving Department/Service', etDots(d.department)]]),
      row([['Time of Initial Contact', etDots(d.contact_time)], ['Staff Contacted', etDots(d.contact_staff)], ['Tel', etDots(d.contact_tel)]]),
      row([['Type of Transfer', `${etBox(d.transfer_type === 'Emergency')} Emergency &nbsp; ${etBox(d.transfer_type === 'Non-Emergency')} Non-Emergency`]])])}
    <div style="margin-top:6px;font-weight:600;">Reason for Transfer:</div>${box(d.reason)}
    ${h('3. CLINICAL ASSESSMENT')}
    <div style="font-weight:600;margin-bottom:3px;">Vital Signs:</div>
    <table style="width:100%;font-size:12px;border-collapse:collapse;border:1px solid #cbd5e1;"><tr>${vitals.map(([l, v, u]) => `<td style="padding:4px 6px;border:1px solid #cbd5e1;"><span style="font-size:10px;color:#475569;">${l}</span><br><strong>${v ? etEsc(v) + ' ' + u : '........'}</strong></td>`).join('')}</tr></table>
    <div style="margin-top:6px;font-weight:600;">Clinical Presentation:</div>${box(d.presentation)}
    <div style="margin-top:6px;font-weight:600;">Patient's Immediate Condition Before Transfer:</div>${box(d.condition)}
    ${h('4. LABORATORY INVESTIGATIONS AND RESULTS')}${box(d.labs)}
    ${h('5. DIAGNOSIS')}${box(d.diagnosis)}
    ${h('6. TREATMENT AND MANAGEMENT PROVIDED')}${box(d.treatment)}
    ${h('7. HEALTH INSURANCE INFORMATION')}
    <div style="line-height:1.8;">${ET_INSURERS.map(i => `${etBox(d.insurance === i)} ${i}`).join(' &nbsp; ')}</div>
    ${h('8. REFERRING HEALTHCARE PROVIDER')}
    ${tbl([
      row([['Full Name', etDots(d.ref_name)], ['Professional Qualification', etDots(d.ref_qual)]]),
      row([['Date', etDate(d.ref_date)], ['Telephone Number', etDots(d.ref_tel)]])])}
    <div style="margin-top:6px;font-weight:600;">Signature and Official Stamp:</div>
  </div>`;
}

function etData() {
  const d = { document_type: 'external_transfer' };
  ET_KEYS.forEach(k => { d[k] = (etEl(k).value || '').trim(); });
  d.patient_id = etEl('patient-id').value || '';
  d.visit_id = etEl('visit').value || '';
  return d;
}

function etBuildForm() {
  const inp = 'width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;';
  document.getElementById('et-fields').innerHTML = ET_SECTIONS.map(([title, fields]) => `
    <h4 style="font-size:13px;font-weight:600;color:var(--primary-color);margin:16px 0 8px;border-bottom:1px solid var(--gray-200);padding-bottom:4px;">${title}</h4>
    <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;">${fields.map(([k, label, type, span]) => {
      let c;
      if (type === 'area') c = `<textarea id="et-${k}" rows="3" oninput="etPreview()" style="${inp}resize:vertical;"></textarea>`;
      else if (type === 'sex') c = `<select id="et-${k}" onchange="etPreview()" style="${inp}"><option value="">Select...</option><option>Male</option><option>Female</option></select>`;
      else if (type.startsWith('select:')) c = `<select id="et-${k}" onchange="etPreview()" style="${inp}">${type.slice(7).split('|').map(o => `<option value="${o}">${o || '—'}</option>`).join('')}</select>`;
      else c = `<input id="et-${k}" type="${type}" ${type === 'number' ? 'step="0.1" ' : ''}oninput="${k === 'weight' || k === 'height' ? 'etBMI();' : ''}etPreview()" style="${inp}">`;
      return `<div style="grid-column:span ${span};"><label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px;">${label}</label>${c}</div>`;
    }).join('')}</div>`).join('');
}

function etOpenForm() { etEl('form-modal').style.display = 'flex'; }
function etCloseForm() { etEl('form-modal').style.display = 'none'; }

function etBMI() {
  const w = parseFloat(etEl('weight').value), h = parseFloat(etEl('height').value);
  if (w > 0 && h > 0) etEl('bmi').value = (w / ((h / 100) ** 2)).toFixed(1);
}

function etPreview() {
  const hs = etHosp, d = etData();
  const hosp = hs.hospital_name || 'Hospital';
  const line = hs.header_line_color || '#059669';
  const logo = hs.doc_logo || hs.logo || '';
  const sig = typeof mdSignatureBlock === 'function' ? mdSignatureBlock('Referring Provider', d.ref_name) : '';
  etEl('preview').innerHTML = `
    <div style="position:relative;">
      <div style="text-align:center;border-bottom:2px solid ${line};padding-bottom:8px;margin-bottom:10px;">
        ${logo ? `<img src="${logo}" style="height:40px;margin-bottom:4px;">` : ''}
        <div style="font-size:16px;font-weight:700;text-transform:uppercase;">${etEsc(hosp)}</div>
        <div style="font-size:11px;color:#475569;">${[hs.tin ? 'TIN: ' + hs.tin : '', hs.phone ? 'Tel: ' + hs.phone : '', hs.email ? 'Email: ' + hs.email : ''].filter(Boolean).map(etEsc).join(' &nbsp;|&nbsp; ')}</div>
      </div>
      <div style="text-align:center;margin-bottom:6px;"><h2 style="font-size:16px;font-weight:700;text-decoration:underline;margin:0;">EXTERNAL PATIENT TRANSFER FORM</h2>
        ${etRef ? `<div style="font-size:11px;color:#64748b;margin-top:3px;">Ref: <strong>${etRef}</strong></div>` : ''}</div>
      ${etBody(d)}
      <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-top:14px;gap:12px;">
        ${sig}
        <div style="display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;padding:6px 10px;">
          <div id="et-qr-code" style="width:68px;height:68px;background:white;padding:2px;border:1px solid #e2e8f0;border-radius:4px;"></div>
          <div><div style="font-size:9px;font-weight:700;color:#059669;text-transform:uppercase;">✓ Verified Document</div>
          <div style="font-size:11px;font-weight:700;">${etRef || ''}</div></div>
        </div>
      </div>
    </div>`;
  if (typeof renderDocQRCode === 'function') renderDocQRCode('et-qr-code', etRef || '', 68);
}

async function etSearch() {
  clearTimeout(etTimer);
  const q = etEl('patient-search').value.trim(), dd = etEl('patient-dropdown');
  if (q.length < 2) { dd.style.display = 'none'; return; }
  etTimer = setTimeout(async () => {
    dd.style.display = 'block';
    dd.innerHTML = '<div style="padding:10px 12px;font-size:12px;color:#64748b;">Searching...</div>';
    try {
      const j = await (await fetch(_etBase + '/api.php?endpoint=patients/patients&search=' + encodeURIComponent(q) + '&limit=8')).json();
      const ps = (j.success && j.data && j.data.patients) || [];
      window._etPatients = ps;
      dd.innerHTML = ps.length ? ps.map((p, i) => `<div onclick="etPick(${i})" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f1f5f9;"><div style="font-weight:600;font-size:13px;">${etEsc(((p.first_name || '') + ' ' + (p.last_name || '')).trim())}</div><div style="font-size:11px;color:#64748b;">${etEsc([p.patient_no, p.phone, p.gender].filter(Boolean).join(' • '))}</div></div>`).join('')
        : '<div style="padding:10px 12px;font-size:12px;color:#94a3b8;">No matching patients found.</div>';
    } catch (e) { dd.innerHTML = '<div style="padding:10px 12px;font-size:12px;color:#ef4444;">Error searching patients.</div>'; }
  }, 250);
}

function etPick(i) {
  const p = etPatient = window._etPatients[i];
  const name = ((p.first_name || '') + ' ' + (p.last_name || '')).trim();
  etEl('patient-search').value = name;
  etEl('patient-id').value = p.id || '';
  etEl('patient-dropdown').style.display = 'none';
  const set = (k, v) => { if (v !== undefined && v !== null) etEl(k).value = v; };
  set('name', name);
  set('dob', p.date_of_birth ? String(p.date_of_birth).substring(0, 10) : '');
  const age = p.date_of_birth ? (typeof mdCalcAge === 'function' ? mdCalcAge(p.date_of_birth) : '') : (p.age || '');
  set('age', age === null || (typeof age === 'number' && isNaN(age)) ? '' : age);
  const g = (p.gender || '').toLowerCase();
  set('sex', g === 'male' ? 'Male' : g === 'female' ? 'Female' : '');
  set('parent', p.next_of_kin || '');
  set('parent_tel', p.emergency_contact || '');
  set('district', p.district_name || ''); set('sector', p.sector_name || '');
  set('cell', p.cell_name || ''); set('village', p.village_name || '');
  const ins = (p.insurance_company || '').toUpperCase();
  set('insurance', ET_INSURERS.find(i => ins.includes(i)) || '');
  etLoadVisits(p.id);
  etPreview();
}

async function etLoadVisits(pid) {
  const sel = etEl('visit');
  sel.disabled = true; sel.innerHTML = '<option value="">Loading visits...</option>';
  try {
    const j = await (await fetch(_etBase + '/api.php?endpoint=patients/visits&action=patient_visits&patient_id=' + encodeURIComponent(pid))).json();
    const vs = (j.success && j.data && Array.isArray(j.data.visits)) ? j.data.visits : [];
    sel.innerHTML = '<option value="">' + (vs.length ? '— Select visit to load clinical data —' : 'No recorded visits') + '</option>' +
      vs.map(v => `<option value="${v.id}" data-adm="${etEsc(v.visit_date || '')}">${etEsc(v.visit_no || 'VIS-' + v.id)} — ${etEsc((v.visit_date || '').substring(0, 10))} [${etEsc((v.visit_type || 'general').toUpperCase())}]</option>`).join('');
    sel.disabled = false;
    if (vs.length) { sel.selectedIndex = 1; etVisit(); }
  } catch (e) { sel.innerHTML = '<option value="">Failed to load visits</option>'; sel.disabled = false; }
}

// Panel results are stored as a JSON array of {test_name,value,unit}; show them as text.
function etResult(r) {
  try { const a = JSON.parse(r); if (Array.isArray(a)) return a.map(i => `${i.test_name || ''} ${i.value ?? ''} ${i.unit || ''}`.trim()).join('; '); } catch (e) {}
  return r;
}

async function etVisit() {
  const sel = etEl('visit'), vid = sel.value;
  if (!vid) { etPreview(); return; }
  const get = u => fetch(_etBase + '/api.php?endpoint=' + u).then(r => r.json());
  const [vit, con, lab, rx] = await Promise.allSettled([
    get('settings/vital-signs&action=records&visit_id=' + vid), get('consultation/consult&action=consultation&visit_id=' + vid),
    get('consultation/consult&action=lab_results&visit_id=' + vid), get('pharmacy/prescriptions&visit_id=' + vid)]);
  const ok = r => r.status === 'fulfilled' && r.value && r.value.success ? r.value.data : null;
  const vm = {}; (Array.isArray(ok(vit)) ? ok(vit) : []).forEach(i => { if (i.sign_slug && i.value !== null && i.value !== '') vm[i.sign_slug] = String(i.value).trim(); });
  const c = Array.isArray(ok(con)) && ok(con)[0] || {};
  const num = v => String(v || '').replace(/[^0-9.]/g, '');
  const set = (k, v) => { etEl(k).value = v || ''; };
  set('bp', vm.bp || (c.bp_systolic && c.bp_diastolic ? c.bp_systolic + '/' + c.bp_diastolic : ''));
  set('hr', vm.hr || c.pulse); set('rr', vm.resp || c.respiratory_rate); set('temp', vm.temp || c.temperature);
  set('spo2', vm.spo2 || c.oxygen_sat); set('weight', num(vm.weight || c.weight)); set('height', num(vm.size || vm.height || c.height));
  set('bmi', vm.bmi || ''); if (!vm.bmi) etBMI();
  set('presentation', [c.chief_complaint ? 'Chief complaint: ' + c.chief_complaint : '', c.examination, c.notes].filter(Boolean).join('\n'));
  set('diagnosis', c.diagnosis);
  const labs = (Array.isArray(ok(lab)) ? ok(lab) : []).filter(x => x.status === 'completed' && x.result);
  set('labs', labs.map(x => (x.test_name || 'Test') + ': ' + etResult(x.result)).join('\n'));
  const rxd = ok(rx), meds = [];
  (Array.isArray(rxd) ? rxd : (rxd && rxd.prescriptions) || []).forEach(r => (r.items || []).forEach(i => meds.push([i.item_name || i.name, i.dosage, i.frequency, i.duration].filter(Boolean).join(' '))));
  set('treatment', meds.join('\n'));
  if (!etEl('admission').value) etEl('admission').value = (sel.selectedOptions[0].dataset.adm || '').replace(' ', 'T').substring(0, 16);
  etPreview();
}

async function etSave() {
  const d = etData();
  if (!d.name) { alert('Please select a patient or fill in the patient name.'); return false; }
  try {
    const j = await (await fetch(_etBase + '/api.php?endpoint=documents/medical-documents', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ document_type: 'external_transfer', patient_name: d.name, physician_name: d.ref_name, reference_no: etRef || '', patient_id: d.patient_id, visit_id: d.visit_id, document_data: { ...d, reference_no: etRef || '' } })
    })).json();
    if (!j.success) { alert(j.message || 'Could not save document.'); return false; }
  } catch (e) { alert('Could not save document: ' + e.message); return false; }
  return true;
}

async function etAfter() { etRef = await getNextDocRef('external_transfer'); etPreview(); }

async function etPrint() {
  if (!(await etSave())) return;
  mdOpenPrint('et-preview', 'External Patient Transfer Form - ' + etEl('name').value);
  etAfter();
}

async function etPDF() {
  if (!(await etSave())) return;
  await mdExportPDF('et-preview', (etRef || 'ETF') + '_' + etEl('name').value.replace(/\s+/g, '_') + '.pdf');
  etAfter();
}

async function etInit() {
  if (!document.getElementById('et-fields')) return; // viewer-only page (Generated Documents)
  etBuildForm();
  try { const j = await (await fetch(_etBase + '/api.php?endpoint=settings/hospital')).json(); if (j.success) etHosp = j.data || {}; } catch (e) {}
  etEl('ref_name').value = window._loggedUserName || '';
  etEl('ref_date').value = new Date().toISOString().split('T')[0];
  etEl('ref_tel').value = etHosp.phone || '';
  etRef = await getNextDocRef('external_transfer');
  document.addEventListener('click', e => { if (!etEl('patient-search').contains(e.target)) etEl('patient-dropdown').style.display = 'none'; });
  etPreview();
}
// Print / PDF helpers (medlink has no shared mdOpenPrint / mdExportPDF)
if (typeof window.mdOpenPrint !== 'function') window.mdOpenPrint = function (id, title) {
  const el = document.getElementById(id); if (!el) return;
  const win = window.open('', '_blank', 'width=850,height=750');
  win.document.write('<html><head><title>' + etEsc(title || 'Document') + '</title><style>@page{size:A4;margin:10mm;}body{font-family:"Times New Roman",serif;padding:10px;color:#000;line-height:1.6;font-size:14px;}img{max-width:100%;}</style></head><body>' + el.innerHTML + '</body></html>');
  win.document.close(); win.focus(); setTimeout(() => win.print(), 500);
};
if (typeof window.mdExportPDF !== 'function') window.mdExportPDF = async function (id, filename) {
  await html2pdf().set({ margin: 10, filename, image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2, useCORS: true }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } }).from(document.getElementById(id)).save();
};

etInit();
