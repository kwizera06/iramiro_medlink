// js/ml-toast.js — small stacked toast (mlToast) + doctor alert feeds that use it: unacknowledged dosage alerts, repeat-illness patients.
function mlToast(html, ms) {
  let box = document.getElementById('ml-toasts');
  if (!box) {
    box = document.createElement('div');
    box.id = 'ml-toasts';
    box.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:10050;display:flex;flex-direction:column;gap:8px;max-width:380px;';
    document.body.appendChild(box);
  }
  const t = document.createElement('div');
  t.style.cssText = 'background:#1e293b;color:#fff;padding:12px 16px;border-radius:10px;font-size:13px;box-shadow:0 8px 24px rgba(0,0,0,.3);cursor:pointer;';
  t.innerHTML = html;
  t.onclick = () => t.remove();
  box.appendChild(t);
  if (ms !== 0) setTimeout(() => t.remove(), ms || 8000);
  return t;
}
function mlEsc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

// Doctors: unacknowledged dosage alerts -> toast + badge button that opens the list (Acknowledge per row)
async function mlDosageAlerts(consultAPI) {
  try {
    const j = await (await fetch(consultAPI + '&action=dosage_alerts')).json();
    const rows = j.data || [];
    const old = document.getElementById('ml-da-badge'); if (old) old.remove();
    if (!rows.length) return;
    mlToast('<b>Dosage alert</b>: ' + rows.length + ' dose(s) given differently from the prescription. Click the red badge to review.', 10000);
    const b = document.createElement('button');
    b.id = 'ml-da-badge';
    b.style.cssText = 'position:fixed;bottom:24px;left:24px;z-index:10040;background:#dc2626;color:#fff;border:none;border-radius:20px;padding:8px 14px;font-weight:600;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.3);';
    b.innerHTML = '<i class="fas fa-pills"></i> Dosage alerts (' + rows.length + ')';
    b.onclick = () => mlDosageList(consultAPI, rows);
    document.body.appendChild(b);
  } catch (e) {}
}
function mlDosageList(consultAPI, rows) {
  const m = document.createElement('div');
  m.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:10060;display:flex;align-items:center;justify-content:center;';
  m.innerHTML = '<div style="background:#fff;border-radius:12px;padding:20px;width:560px;max-width:94vw;max-height:85vh;overflow:auto;"><h3 style="font-size:16px;font-weight:600;margin-bottom:10px;">Dosage alerts</h3>'
    + rows.map(r => '<div style="padding:8px 0;border-bottom:1px solid #eee;font-size:13px;"><b>' + mlEsc(r.patient_name) + '</b> - ' + mlEsc(r.drug) + ': prescribed ' + mlEsc(r.prescribed_dose) + ', given ' + mlEsc(r.given_dose)
      + '<div style="color:#64748b;">Reason: ' + mlEsc(r.reason) + ' (' + mlEsc(r.nurse_name || '') + ', ' + mlEsc(r.created_at) + ')</div>'
      + '<button data-id="' + r.id + '" style="margin-top:4px;padding:3px 10px;border:none;background:#16a34a;color:#fff;border-radius:6px;cursor:pointer;">Acknowledge</button></div>').join('')
    + '<div style="text-align:right;margin-top:12px;"><button id="ml-da-close" style="padding:6px 14px;border:1px solid #cbd5e1;background:#fff;border-radius:6px;cursor:pointer;">Close</button></div></div>';
  document.body.appendChild(m);
  m.querySelector('#ml-da-close').onclick = () => { m.remove(); mlDosageAlerts(consultAPI); };
  m.querySelectorAll('button[data-id]').forEach(btn => btn.onclick = async () => {
    await fetch(consultAPI + '&action=dosage_alert_ack', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: +btn.dataset.id }) });
    btn.closest('div').remove();
  });
}

// Dashboard: patients who reached the repeat-visit threshold for the same diagnosis and were not referred / justified
async function mlRepeatVisitAlerts(consultAPI) {
  try {
    const j = await (await fetch(consultAPI + '&action=repeat_visit_alerts')).json();
    const rows = j.data || [];
    if (!rows.length) return;
    mlToast('<b>Repeat visits</b> (not yet referred)<br>' + rows.slice(0, 8).map(r => mlEsc(r.patient_name) + ' - ' + mlEsc(r.diagnosis) + ' (' + r.visits + 'x)').join('<br>')
      + (rows.length > 8 ? '<br>+' + (rows.length - 8) + ' more' : ''), 15000);
  } catch (e) {}
}
