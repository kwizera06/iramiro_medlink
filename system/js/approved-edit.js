// Admin-approval gated corrections (department.php details modal, consultation.php vitals).
// Flow: pencil -> no request: ask reason & send to admin | pending: wait | approved: open form -> save replaces the value.
(function () {
  const base = (typeof window.appBasePath !== 'undefined' && window.appBasePath !== null) ? window.appBasePath
    : (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) || '');
  const erAPI = window.location.origin + base + '/api.php?endpoint=edit-requests';
  const consultAPI = window.location.origin + base + '/api.php?endpoint=consultation/consult';
  const esc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(r => r.json());

  // fields: [{key,label,value,multiline}] ; onDone runs after a saved correction (reload the view)
  window.approvedEdit = async function (entityType, entityId, entityName, fields, onDone, transform) {
    const c = await (await fetch(erAPI + '&action=check&entity_type=' + entityType + '&entity_id=' + entityId)).json();
    const req = c.data;
    if (req && req.status === 'pending') return alert('A correction request for this record is waiting for admin approval.');
    if (!req) {
      const reason = prompt('Why does this record need correcting? (min 10 characters)\nThe request goes to the administrator for approval.');
      if (reason === null) return;
      const j = await post(erAPI + '&action=create', { entity_type: entityType, entity_id: entityId, entity_name: entityName, reason: reason.trim() });
      return alert(j.success ? 'Request sent to the administrator. Come back after it is approved to make the change.' : (j.message || 'Request failed'));
    }
    openForm(req.id, entityType, entityId, entityName, fields, onDone, transform);
  };

  function openForm(reqId, entityType, entityId, entityName, fields, onDone, transform) {
    const ov = document.createElement('div');
    ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10000;display:flex;align-items:center;justify-content:center;padding:16px;';
    ov.innerHTML = `<div style="background:white;border-radius:10px;padding:20px;width:100%;max-width:520px;max-height:90vh;overflow:auto;">
      <h4 style="font-size:15px;font-weight:700;margin-bottom:4px;">Approved correction</h4>
      <div style="font-size:12px;color:#6b7280;margin-bottom:14px;">${esc(entityName)}</div>
      ${fields.map((f, i) => `<label style="font-size:12px;font-weight:600;color:#4b5563;">${esc(f.label)}</label>` + (f.multiline
        ? `<textarea data-i="${i}" rows="4" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;margin:4px 0 12px;">${esc(f.value)}</textarea>`
        : `<input data-i="${i}" value="${esc(f.value)}" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;margin:4px 0 12px;">`)).join('')}
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button data-act="cancel" style="padding:8px 14px;border:1px solid #e5e7eb;background:#f3f4f6;border-radius:6px;cursor:pointer;">Cancel</button>
        <button data-act="save" style="padding:8px 14px;border:none;background:#2563eb;color:white;border-radius:6px;cursor:pointer;font-weight:600;">Save correction</button>
      </div></div>`;
    document.body.appendChild(ov);
    ov.querySelector('[data-act=cancel]').onclick = () => ov.remove();
    ov.querySelector('[data-act=save]').onclick = async (e) => {
      const body = { entity_type: entityType, entity_id: entityId, edit_request_id: reqId };
      ov.querySelectorAll('[data-i]').forEach(el => { body[fields[el.dataset.i].key] = el.value; });
      if (transform) transform(body); // e.g. fold per-row inputs back into one JSON result
      e.target.disabled = true;
      const j = await post(consultAPI + '&action=edit_record', body);
      if (!j.success) { e.target.disabled = false; return alert(j.message || 'Save failed'); }
      ov.remove();
      if (onDone) onDone();
    };
  }

  // All active vital signs: empty ones fillable by the doctor, recorded ones read-only with an approval pencil.
  window.editAllVitals = async function (visitId, patientId, onDone, onCorrect) {
    const vitalsAPI = window.location.origin + base + '/api.php?endpoint=settings/vital-signs';
    window.__vitalCorrect = onCorrect;
    const [sj,rj]=await Promise.all([fetch(vitalsAPI).then(r=>r.json()),fetch(vitalsAPI+'&action=records&visit_id='+visitId).then(r=>r.json())]);
    const have={}; (rj.data||[]).forEach(v=>have[v.vital_sign_id]=v);
    const ov=document.createElement('div');
    ov.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10000;display:flex;align-items:center;justify-content:center;padding:16px;';
    ov.innerHTML=`<div style="background:white;border-radius:10px;padding:20px;width:100%;max-width:560px;max-height:90vh;overflow:auto;">
      <h4 style="font-size:15px;font-weight:700;margin-bottom:4px;">Vital signs</h4>
      <div style="font-size:12px;color:#6b7280;margin-bottom:12px;">Empty fields can be filled now. Values already recorded need admin approval to change (pencil).</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">`+(sj.data||[]).map(s=>{const h=have[s.id];
        return `<div><label style="font-size:11px;font-weight:600;color:#4b5563;display:flex;justify-content:space-between;">${esc(s.name)}${s.unit?' ('+esc(s.unit)+')':''}${h?`<i class="fas fa-pen" title="Correct (needs admin approval)" style="cursor:pointer;color:#2563eb;" onclick="this.closest('[style*=fixed]').remove();__vitalCorrect(${h.id})"></i>`:''}</label>
        <input data-sid="${s.id}" value="${h?esc(h.value):''}" ${h?'readonly style="background:#f3f4f6;"':'placeholder="not recorded"'} style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:13px;${h?'background:#f3f4f6;color:#4b5563;':''}"></div>`;}).join('')+`</div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px;">
        <button data-act="cancel" style="padding:8px 14px;border:1px solid #e5e7eb;background:#f3f4f6;border-radius:6px;cursor:pointer;">Cancel</button>
        <button data-act="save" style="padding:8px 14px;border:none;background:#2563eb;color:white;border-radius:6px;cursor:pointer;font-weight:600;">Save new values</button></div></div>`;
    document.body.appendChild(ov);
    ov.querySelector('[data-act=cancel]').onclick=()=>ov.remove();
    ov.querySelector('[data-act=save]').onclick=async e=>{
      const readings=[...ov.querySelectorAll('input[data-sid]:not([readonly])')].filter(i=>i.value.trim()).map(i=>({vital_sign_id:+i.dataset.sid,value:i.value.trim()}));
      if(!readings.length) return alert('Enter at least one new value.');
      e.target.disabled=true;
      const j=await fetch(consultAPI+'&action=add_vitals',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({visit_id:visitId,patient_id:patientId,readings})}).then(r=>r.json());
      if(!j.success){e.target.disabled=false;return alert(j.message||'Save failed');}
      ov.remove(); if(onDone) onDone();
    };
  };
  
})();
