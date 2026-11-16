// js/visit-routing.js
// Shared by both "Send to Consultant" screens in patients.php (visits list + nurse queue).
// A patient can be with several departments at once (one doctor per department):
//  - VR.chips(v)            one badge per department with its status; nurse/admin get Change / Remove while the doctor has not started
//  - VR.addButton(v)        "+ Add doctor" for a visit already sent
//  - VR.addRow / VR.collect extra department rows inside the existing send modals
// Setup: VR.init({api, canEdit, requireDoctor, reload})

const VR = {
  api: '', canEdit: false, requireDoctor: false, reload: () => {},
  visits: {}, depts: null, cur: null,

  init(o){ Object.assign(this, o); },

  esc(s){ return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); },

  async departments(){
    if(!this.depts){
      try{ const j = await (await fetch(this.api + '&action=departments')).json(); this.depts = j.success ? j.data : []; }
      catch(e){ this.depts = []; }
    }
    return this.depts;
  },

  active(v){ return (v.assignments || []).filter(a => a.status === 'pending' || a.status === 'in_progress'); },

  chips(v){
    this.visits[v.id] = v;
    const rows = v.assignments || [];
    if(!rows.length) return this.esc(v.department_name || '—');
    const st = {
      pending:     ['#fef3c7', '#92400e', 'Waiting'],
      in_progress: ['#dbeafe', '#1e40af', 'In consultation'],
      completed:   ['#dcfce7', '#166534', 'Done'],
    };
    const btn = 'border:none;background:rgba(255,255,255,.7);border-radius:50%;width:18px;height:18px;cursor:pointer;font-size:10px;line-height:18px;padding:0;color:inherit;';
    return rows.map(a => {
      const [bg, color, label] = st[a.status] || st.pending;
      const doctor = (a.doctor_name || '').trim();
      const edit = this.canEdit && a.status === 'pending'
        ? `<button title="Change department / doctor" onclick="VR.open(${v.id},'edit',${a.id})" style="${btn}margin-left:4px;"><i class="fas fa-pen"></i></button>
           <button title="Remove" onclick="VR.remove(${v.id},${a.id})" style="${btn}"><i class="fas fa-times"></i></button>`
        : '';
      return `<div style="display:inline-flex;align-items:center;gap:3px;margin:2px 0;padding:3px 8px;border-radius:14px;font-size:11px;font-weight:600;background:${bg};color:${color};white-space:nowrap;">
        ${this.esc(a.department_name || '—')}${doctor ? ' – Dr. ' + this.esc(doctor) : ''} · ${label}${edit}</div>`;
    }).join('<br>');
  },

  addButton(v){
    this.visits[v.id] = v;
    return `<button onclick="VR.open(${v.id},'add')" style="padding:6px 12px;background:white;color:var(--primary-color);border:1px solid var(--primary-color);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">
      <i class="fas fa-plus"></i> Add doctor</button>`;
  },

  // ---- department + doctor rows ----
  async addRow(containerId, preset = {}){
    const depts = await this.departments();
    const row = document.createElement('div');
    row.className = 'vr-row';
    row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-bottom:8px;';
    const sel = 'padding:9px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:13px;background:white;width:100%;';
    row.innerHTML = `
      <select class="vr-dept" onchange="VR.loadDoctors(this.parentNode)" style="${sel}">
        <option value="">— Department —</option>
        ${depts.map(d => `<option value="${d.id}">${this.esc(d.name)}</option>`).join('')}
      </select>
      <select class="vr-doc" style="${sel}"><option value="">— Doctor / Clinician${this.requireDoctor ? '' : ' (optional)'} —</option></select>
      <button type="button" title="Remove row" onclick="this.parentNode.remove()" style="border:none;background:var(--gray-100);border-radius:8px;width:36px;cursor:pointer;color:#dc2626;"><i class="fas fa-times"></i></button>`;
    document.getElementById(containerId).appendChild(row);
    if(preset.department_id){
      row.querySelector('.vr-dept').value = preset.department_id;
      await this.loadDoctors(row, preset.assigned_to);
    }
    return row;
  },

  async loadDoctors(row, selected){
    const deptId = row.querySelector('.vr-dept').value;
    const doc = row.querySelector('.vr-doc');
    const first = doc.options[0].outerHTML;
    if(!deptId){ doc.innerHTML = first; return; }
    doc.innerHTML = '<option value="">Loading…</option>';
    try{
      const j = await (await fetch(this.api + '&action=users_by_dept&department_id=' + encodeURIComponent(deptId))).json();
      doc.innerHTML = first + (j.data || []).map(u =>
        `<option value="${u.id}" data-name="${this.esc(u.first_name + ' ' + u.last_name)}">${this.esc(u.first_name + ' ' + u.last_name)}${u.role_name ? ' (' + this.esc(u.role_name) + ')' : ''}</option>`).join('');
      if(selected) doc.value = selected;
    }catch(e){ doc.innerHTML = first; }
  },

  clear(containerId){ document.getElementById(containerId).innerHTML = ''; },

  // Rows → [{department_id, doctor_id, doctor_name}]. Throws Error with a message for the user.
  collect(containerId, takenDeptIds = []){
    const taken = new Set(takenDeptIds.map(String));
    const names = Object.fromEntries((this.depts || []).map(d => [String(d.id), d.name]));
    return [...document.querySelectorAll('#' + containerId + ' .vr-row')].map(row => {
      const dept = row.querySelector('.vr-dept').value;
      const doc = row.querySelector('.vr-doc');
      if(!dept) throw new Error('Please select a department on every row (or remove the empty row).');
      if(this.requireDoctor && !doc.value) throw new Error('Please select a Doctor / Clinician for ' + names[dept] + '.');
      if(taken.has(dept)) throw new Error('The patient can only be sent once to ' + names[dept] + '.');
      taken.add(dept);
      return {department_id: parseInt(dept), doctor_id: doc.value ? parseInt(doc.value) : null, doctor_name: doc.selectedOptions[0]?.dataset.name || null};
    });
  },

  // ---- Change / Add modal ----
  modal(){
    let m = document.getElementById('vr-modal');
    if(m) return m;
    m = document.createElement('div');
    m.id = 'vr-modal';
    m.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9500;align-items:center;justify-content:center;';
    m.innerHTML = `
      <div style="background:white;border-radius:16px;width:100%;max-width:620px;margin:20px;box-shadow:0 25px 60px rgba(0,0,0,.25);overflow:hidden;">
        <div style="background:var(--primary-color);padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
          <div>
            <h3 id="vr-title" style="color:white;font-size:16px;font-weight:700;margin-bottom:2px;"></h3>
            <div id="vr-subtitle" style="color:rgba(255,255,255,.8);font-size:13px;"></div>
          </div>
          <button onclick="VR.close()" style="background:rgba(255,255,255,.2);border:none;color:white;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:16px;">×</button>
        </div>
        <div style="padding:20px 24px;">
          <div id="vr-alert" style="display:none;margin-bottom:14px;padding:10px 14px;border-radius:8px;font-size:13px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;"></div>
          <div id="vr-current" style="margin-bottom:14px;font-size:12px;color:var(--gray-600);"></div>
          <div id="vr-rows"></div>
          <button id="vr-more" type="button" onclick="VR.addRow('vr-rows')" style="width:100%;padding:8px;background:var(--gray-50);border:1px dashed var(--gray-300,#d1d5db);border-radius:8px;cursor:pointer;font-size:12px;color:var(--gray-600);"><i class="fas fa-plus"></i> Add another doctor</button>
          <div style="display:flex;gap:10px;margin-top:16px;">
            <button onclick="VR.close()" style="flex:1;padding:11px;background:var(--gray-100);color:var(--gray-700);border:none;border-radius:8px;font-weight:500;cursor:pointer;">Cancel</button>
            <button id="vr-save" onclick="VR.save()" style="flex:2;padding:11px;background:var(--primary-color);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-check"></i> Save</button>
          </div>
        </div>
      </div>`;
    m.addEventListener('click', e => { if(e.target === m) VR.close(); });
    document.body.appendChild(m);
    return m;
  },

  async open(visitId, mode, historyId){
    const v = this.visits[visitId];
    const a = (v.assignments || []).find(x => x.id == historyId);
    this.cur = {v, mode, a};
    const m = this.modal();
    document.getElementById('vr-title').innerHTML = mode === 'edit'
      ? '<i class="fas fa-random" style="margin-right:8px;"></i>Change department / doctor'
      : '<i class="fas fa-user-plus" style="margin-right:8px;"></i>Send to another doctor';
    document.getElementById('vr-subtitle').textContent = (v.patient_name || ((v.first_name || '') + ' ' + (v.last_name || ''))) + ' · ' + (v.visit_no || '');
    document.getElementById('vr-alert').style.display = 'none';
    document.getElementById('vr-current').innerHTML = mode === 'edit'
      ? `Currently: <strong>${this.esc(a.department_name)}${(a.doctor_name || '').trim() ? ' – Dr. ' + this.esc(a.doctor_name) : ''}</strong>. Choose where the patient should go instead.`
      : 'Currently with: ' + this.chips({...v, assignments: this.active(v)}).replace(/<button[\s\S]*?<\/button>/g, '');
    document.getElementById('vr-more').style.display = mode === 'edit' ? 'none' : 'block';
    this.clear('vr-rows');
    m.style.display = 'flex';
    await this.addRow('vr-rows', mode === 'edit' ? a : {});
  },

  close(){ document.getElementById('vr-modal').style.display = 'none'; this.cur = null; },

  async save(){
    const {v, mode, a} = this.cur;
    const alertEl = document.getElementById('vr-alert');
    const btn = document.getElementById('vr-save');
    alertEl.style.display = 'none';
    let rows;
    try{
      // Departments that keep the patient can't be chosen again (in edit mode the one being changed is free)
      const keep = this.active(v).filter(x => mode !== 'edit' || x.id != a.id).map(x => x.department_id);
      rows = this.collect('vr-rows', keep);
      if(!rows.length) throw new Error('Please add a department.');
    }catch(e){ alertEl.textContent = e.message; alertEl.style.display = 'block'; return; }

    const body = mode === 'edit'
      ? {action:'reroute', history_id:a.id, ...rows[0]}
      : {action:'assign_consultation', assignments:rows};
    btn.disabled = true;
    try{
      const j = await (await fetch(this.api + '&id=' + v.id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)})).json();
      if(!j.success) throw new Error(j.message || 'Could not save');
      this.close();
      this.reload();
    }catch(e){ alertEl.textContent = e.message; alertEl.style.display = 'block'; }
    finally{ btn.disabled = false; }
  },

  async remove(visitId, historyId){
    const v = this.visits[visitId];
    const a = (v.assignments || []).find(x => x.id == historyId);
    const last = this.active(v).length === 1;
    if(!confirm('Remove ' + a.department_name + ' for ' + (v.patient_name || 'this patient') + '?'
      + (last ? '\n\nThis is the only department: the patient goes back to the waiting list.' : ''))) return;
    try{
      const j = await (await fetch(this.api + '&id=' + visitId, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'cancel_assignment', history_id:historyId})})).json();
      if(!j.success) throw new Error(j.message);
      this.reload();
    }catch(e){ alert(e.message || 'Could not remove'); }
  },
};
