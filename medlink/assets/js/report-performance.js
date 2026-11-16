// Doctor performance, nurse performance and repeat diagnoses report tabs (report.php).
// PERF = {kind:'doctor'|'nurse'|'repeat'} is set by report.php before this file loads.
(function () {
  var kind = PERF.kind;
  var api = rptBasePath + '/api.php?endpoint=reports/general&action=';
  var $ = function (id) { return document.getElementById(id); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var money = function (n) { return Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 0 }); };
  var TD = 'padding:9px 10px;border-bottom:1px solid var(--gray-100);';
  var data = null, seq = 0, filled = false;

  var COLS = {
    doctor: [['patients', 'Patients seen'], ['consultations', 'Consultations'], ['children', 'Children &lt;15'], ['under5', 'Under 5'], ['adults', 'Adults 15+'], ['women', 'Women'], ['men', 'Men'],
      ['self_pay', 'Self-pay'], ['insured', 'Insured'], ['rx_only', 'Rx only (no lab/imaging)'], ['lab', 'To Lab'], ['imaging', 'To Imaging'], ['procedures', 'Procedures'], ['admitted', 'Admitted'], ['referred_out', 'Referred out'],
      ['$fee', 'Consultation fees'], ['$shared', 'Invoiced revenue (shared)'], ['$paid', 'Paid revenue (shared)']],
    nurse: [['received', 'Received / triaged'], ['referrals', 'Referrals to depts'], ['meds', 'Meds / injections'], ['procedures', 'Procedures done'], ['completed', 'Visits completed'],
      ['nursing_notes', 'Notes / vitals / services'], ['$activity', 'All system activities'], ['patients', 'Patients (distinct)'], ['children', 'Children &lt;15'], ['adults', 'Adults 15+'], ['women', 'Women'], ['men', 'Men'], ['self_pay', 'Self-pay'], ['insured', 'Insured']]
  };

  function params() {
    var p = new URLSearchParams({ date_from: $('perf-from').value, date_to: $('perf-to').value });
    ['perf-doctor:doctor_id', 'perf-dept:department_id', 'perf-ins:insurance_id'].forEach(function (x) {
      var a = x.split(':'), el = $(a[0]);
      if (el && el.value) p.append(a[1], el.value);
    });
    return p;
  }
  function fillSelect(id, list, label) {
    var el = $(id); if (!el || filled) return;
    el.innerHTML = '<option value="">' + label + '</option>' + list.map(function (o) { return '<option value="' + o.id + '">' + esc(o.name) + '</option>'; }).join('');
  }
  function num(sid, key, n) {
    if (!n) return '<span style="color:var(--gray-300);">0</span>';
    return '<a href="#" class="perf-n" data-s="' + sid + '" data-m="' + esc(key) + '">' + n + '</a>';
  }

  function render() {
    var cols = COLS[kind], staff = data.staff;
    var head = '<tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);"><th style="padding:9px 10px;text-align:left;">' + (kind === 'doctor' ? 'Doctor' : 'Nurse') + '</th>' +
      cols.map(function (c) { return '<th style="padding:9px 10px;text-align:center;font-weight:600;font-size:12px;">' + c[1] + '</th>'; }).join('') + '</tr>';
    var body = staff.map(function (s) {
      var tds = cols.map(function (c) {
        var k = c[0], v;
        if (k === '$fee' || k === '$shared' || k === '$paid') v = '<a href="#" class="perf-n" data-s="' + s.id + '" data-m="consultations">' + money(k === '$fee' ? s.counts.fee : k === '$paid' ? s.counts.paid_rev : s.counts.shared_rev) + '</a>';
        else if (k === '$activity') v = money(s.counts.activity);
        else v = num(s.id, k, s.counts[k] || 0);
        return '<td style="' + TD + 'text-align:center;">' + v + '</td>';
      }).join('');
      var nm = s.id === 0 ? '<strong>' + esc(s.name) + '</strong>' : esc(s.name);
      return '<tr><td style="' + TD + 'font-weight:500;white-space:nowrap;">' + nm + '</td>' + tds + '</tr>';
    }).join('');
    $('perf-table').innerHTML = '<thead>' + head + '</thead><tbody>' + (body || '<tr><td colspan="' + (cols.length + 1) + '" style="padding:40px;text-align:center;color:var(--gray-400);">No activity found for this period.</td></tr>') + '</tbody>';
    $('perf-note').textContent = data.note || '';
  }

  window.perfLoad = function () {
    var my = ++seq;
    $('perf-table').innerHTML = '<tbody><tr><td style="padding:40px;text-align:center;color:var(--gray-400);"><i class="fas fa-spinner fa-spin"></i></td></tr></tbody>';
    fetch(api + kind + '_performance&' + params(), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (my !== seq) return;
      if (!j.success) { $('perf-table').innerHTML = '<tbody><tr><td style="padding:30px;color:#dc2626;">' + esc(j.message || 'Failed to load') + '</td></tr></tbody>'; return; }
      data = j.data;
      if (kind === 'doctor') { fillSelect('perf-doctor', data.doctors || [], 'All doctors'); fillSelect('perf-dept', data.departments, 'All departments');
        fillSelect('perf-ins', [{ id: 'self', name: 'Self-pay' }].concat(data.insurers), 'All payers'); if ($('perf-doctor') && !(data.doctors || []).length) $('perf-doctor').style.display = 'none'; }
      filled = true;
      render();
    }).catch(function () { if (my === seq) $('perf-table').innerHTML = '<tbody><tr><td style="padding:30px;color:#dc2626;">Failed to load</td></tr></tbody>'; });
  };

  // Drill-down modal
  function drill(sid, m) {
    var s = data.staff.filter(function (x) { return x.id === sid; })[0];
    $('perf-drill-title').textContent = (s ? s.name : '') + ' — ' + (m.indexOf(':') > 0 ? m.replace(/^(ins|ref):/, '') : m.replace(/_/g, ' '));
    $('perf-drill-body').innerHTML = '<tr><td style="padding:30px;text-align:center;"><i class="fas fa-spinner fa-spin"></i></td></tr>';
    $('perf-modal').classList.add('show');
    var p = params(); p.append('staff_id', sid); p.append('detail', m);
    fetch(api + kind + '_performance&' + p, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      var rows = (j.data && j.data.rows) || [];
      $('perf-drill-body').innerHTML = rows.length ? rows.map(function (r, i) {
        return '<tr><td style="' + TD + '">' + (i + 1) + '</td><td style="' + TD + '">' + esc(r.date) + '</td><td style="' + TD + '">' + esc(r.visit_no) + '</td><td style="' + TD + '">' + esc(r.patient_no) + '</td><td style="' + TD + '">' + esc(r.patient) +
          '</td><td style="' + TD + '">' + esc(r.gender) + ' ' + esc(r.age) + '</td><td style="' + TD + '">' + esc(r.payer) + '</td><td style="' + TD + '">' + esc(r.info) + '</td><td style="' + TD + 'text-align:right;">' + (r.fee == null ? '' : money(r.fee)) +
          '</td><td style="' + TD + 'text-align:right;">' + (r.visit_rev == null ? '' : money(r.visit_rev)) + '</td></tr>';
      }).join('') : '<tr><td colspan="10" style="padding:30px;text-align:center;color:var(--gray-400);">Nothing to show.</td></tr>';
    });
  }
  window.perfCloseDrill = function () { $('perf-modal').classList.remove('show'); };

  function init() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('.perf-n');
      if (a) { e.preventDefault(); drill(parseInt(a.dataset.s, 10), a.dataset.m); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') perfCloseDrill(); });
    perfLoad();
  }

  // Repeat diagnoses: simple list
  if (kind === 'repeat') {
    window.perfLoad = function () {
      var p = new URLSearchParams({ date_from: $('perf-from').value, date_to: $('perf-to').value });
      $('perf-body').innerHTML = '<tr><td colspan="9" style="padding:40px;text-align:center;"><i class="fas fa-spinner fa-spin"></i></td></tr>';
      fetch(api + 'repeat_diagnoses&' + p, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        var rows = (j.data) || [];
        $('perf-body').innerHTML = rows.length ? rows.map(function (r, i) {
          return '<tr><td style="' + TD + '">' + (i + 1) + '</td><td style="' + TD + '">' + esc(String(r.created_at).slice(0, 16)) + '</td><td style="' + TD + '">' + esc(r.patient_no) + '</td><td style="' + TD + '">' + esc(r.patient_name) + '</td><td style="' + TD + '">' + esc(r.visit_no) +
            '</td><td style="' + TD + '">' + esc(r.diagnosis) + '</td><td style="' + TD + 'text-align:center;">' + r.occurrences + ' / ' + r.window_days + ' days</td><td style="' + TD + '">' + esc(r.action) + '</td><td style="' + TD + '">' + esc(r.justification) + '</td><td style="' + TD + '">' + esc(r.user_name) + '</td></tr>';
        }).join('') : '<tr><td colspan="10" style="padding:40px;text-align:center;color:var(--gray-400);">No repeat-diagnosis flags in this period.</td></tr>';
      });
    };
    perfLoad();
  } else init();
})();
