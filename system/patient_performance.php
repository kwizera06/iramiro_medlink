<?php
// patient_performance.php — patients grouped by total number of visits, with donut chart
require_once __DIR__ . '/includes/session_check.php';
require_once __DIR__ . '/includes/database.php';

// Visit-count buckets for the donut: key => [label, min, max (null = open)]
$PP_BUCKETS = [
    ['1',  'First time (1)', 1, 1],
    ['2',  'Second time (2)', 2, 2],
    ['3_5', '3 - 5 visits', 3, 5],
    ['6_15', '6 - 15 visits', 6, 15],
    ['16', 'More than 15', 16, null],
];
// Select option => min/max visit count
$PP_FILTERS = ['1' => [1, 1], '2' => [2, 2], 'gt5' => [6, null], 'gt15' => [16, null]];

if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $from = $_GET['date_from'] ?? '';
    $to   = $_GET['date_to'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $f = $PP_FILTERS[$_GET['visit_count'] ?? ''] ?? null;

    // Total visits per patient (lifetime). Date range, if given, keeps patients who visited inside it.
    $having = []; $params = [];
    if ($from !== '' && $to !== '') { $having[] = "SUM(v.visit_date BETWEEN ? AND ?) > 0"; array_push($params, $from, $to); }
    elseif ($from !== '')           { $having[] = "SUM(v.visit_date >= ?) > 0"; $params[] = $from; }
    elseif ($to !== '')             { $having[] = "SUM(v.visit_date <= ?) > 0"; $params[] = $to; }
    $hv = $having ? 'HAVING ' . implode(' AND ', $having) : '';
    $base = "SELECT p.id, p.patient_no, p.first_name, p.last_name, p.phone, COUNT(v.id) AS cnt,
                    MIN(v.visit_date) AS first_visit, MAX(v.visit_date) AS last_visit
             FROM patients p JOIN visits v ON v.patient_id = p.id GROUP BY p.id $hv";

    // Donut data: all matching patients bucketed by visit count
    $sel = ''; $bp = [];
    foreach ($PP_BUCKETS as $i => [$k, $label, $min, $max]) {
        $sel .= ($i ? ',' : '') . "COALESCE(SUM(t.cnt >= $min" . ($max ? " AND t.cnt <= $max" : '') . "),0) AS b$i";
    }
    $row = $pdo->prepare("SELECT $sel FROM ($base) t");
    $row->execute($params);
    $r = $row->fetch(PDO::FETCH_ASSOC);
    $buckets = [];
    foreach ($PP_BUCKETS as $i => [$k, $label, $min, $max]) {
        $buckets[] = ['key' => $k, 'label' => $label, 'min' => $min, 'max' => $max, 'count' => (int)$r["b$i"]];
    }

    // List
    $w = ''; $lp = $params;
    if ($f) { $w = 'WHERE t.cnt >= ?' . ($f[1] ? ' AND t.cnt <= ?' : ''); $lp[] = $f[0]; if ($f[1]) $lp[] = $f[1]; }
    $c = $pdo->prepare("SELECT COUNT(*) FROM ($base) t $w");
    $c->execute($lp);
    $total = (int)$c->fetchColumn();
    $off = ($page - 1) * $limit;
    $l = $pdo->prepare("SELECT t.* FROM ($base) t $w ORDER BY t.cnt DESC, t.last_visit DESC LIMIT $limit OFFSET $off");
    $l->execute($lp);

    echo json_encode(['success' => true, 'buckets' => $buckets, 'filter' => $f, 'patients' => $l->fetchAll(PDO::FETCH_ASSOC),
        'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $limit)]);
    exit;
}

require_once __DIR__ . '/includes/header.php';
$subModule = 'patient-performance';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;"><div><h2 style="font-size:24px;font-weight:600;">Reports</h2><p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Financial and operational reports</p></div></div>
<?php require __DIR__ . '/includes/report_tabs.php'; ?>
<?php
$inp = 'padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:13px;background:white;';
$lbl = 'font-size:11px;font-weight:600;color:var(--gray-500);display:block;margin-bottom:2px;';
?>
<p style="color:var(--gray-500);font-size:14px;margin-bottom:12px;">Patients by total number of visits in the system</p>

<div style="background:white;border-radius:var(--radius);padding:16px 20px;box-shadow:var(--shadow);margin-bottom:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
  <div><label style="<?php echo $lbl; ?>">From</label><input id="pp-from" type="date" style="<?php echo $inp; ?>"></div>
  <div><label style="<?php echo $lbl; ?>">To</label><input id="pp-to" type="date" style="<?php echo $inp; ?>"></div>
  <div>
    <label style="<?php echo $lbl; ?>">Total visits</label>
    <select id="pp-count" style="<?php echo $inp; ?>">
      <option value="">All</option>
      <option value="1">First time (1 visit)</option>
      <option value="2">Second time (2 visits)</option>
      <option value="gt5">More than 5 times</option>
      <option value="gt15">More than 15 times</option>
    </select>
  </div>
  <button onclick="ppPage=1;ppLoad()" style="padding:8px 18px;background:var(--primary-color);color:white;border:none;border-radius:6px;font-weight:500;cursor:pointer;font-size:13px;"><i class="fas fa-search"></i> Filter</button>
  <button onclick="ppReset()" style="padding:8px 14px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;cursor:pointer;font-size:13px;"><i class="fas fa-undo"></i> Reset</button>
</div>

<div style="display:grid;grid-template-columns:minmax(280px,360px) 1fr;gap:16px;align-items:start;">
  <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);padding:16px;text-align:center;">
    <div style="position:relative;max-width:300px;margin:0 auto;">
      <canvas id="pp-chart"></canvas>
      <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
        <div id="pp-center" style="font-size:26px;font-weight:700;color:var(--primary-color);">—</div>
        <div id="pp-center-l" style="font-size:11px;color:var(--gray-500);">Patients</div>
      </div>
    </div>
    <div id="pp-legend" style="margin-top:12px;font-size:12px;text-align:left;"></div>
  </div>

  <div style="background:white;border-radius:var(--radius);box-shadow:var(--shadow);overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
      <thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
        <th style="padding:10px 12px;text-align:left;">#</th>
        <th style="padding:10px 12px;text-align:left;">Patient</th>
        <th style="padding:10px 12px;text-align:left;">Patient No.</th>
        <th style="padding:10px 12px;text-align:left;">Phone</th>
        <th style="padding:10px 12px;text-align:center;">Total visits</th>
        <th style="padding:10px 12px;text-align:left;">First visit</th>
        <th style="padding:10px 12px;text-align:left;">Last visit</th>
      </tr></thead>
      <tbody id="pp-tbody"></tbody>
    </table>
    <div id="pp-pager" style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;font-size:13px;color:var(--gray-600);"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ppURL = location.pathname + '?ajax=1';
const ppColors = ['#2563eb', '#7c3aed', '#059669', '#d97706', '#dc2626'];
let ppPage = 1, ppChart = null;
const ppEsc = s => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

function ppReset(){
  ['pp-from','pp-to','pp-count'].forEach(id => document.getElementById(id).value = '');
  ppPage = 1; ppLoad();
}

async function ppLoad(){
  const p = new URLSearchParams({
    date_from: document.getElementById('pp-from').value, date_to: document.getElementById('pp-to').value,
    visit_count: document.getElementById('pp-count').value, page: ppPage
  });
  const tb = document.getElementById('pp-tbody');
  tb.innerHTML = '<tr><td colspan="7" style="padding:30px;text-align:center;"><i class="fas fa-spinner fa-spin"></i></td></tr>';
  try {
    const j = await (await fetch(ppURL + '&' + p)).json();
    if (!j.success) throw new Error(j.message || 'Failed');
    ppDraw(j);
  } catch(e) {
    tb.innerHTML = '<tr><td colspan="7" style="padding:30px;text-align:center;color:#dc2626;">Error: ' + ppEsc(e.message) + '</td></tr>';
  }
}

function ppDraw(j){
  // A bucket is "selected" when it falls inside the chosen visit-count range; no filter = all selected
  const f = j.filter;
  const sel = j.buckets.map(b => !f || (b.min >= f[0] && (f[1] === null || (b.max !== null && b.max <= f[1]))));
  const total = j.buckets.reduce((a, b) => a + b.count, 0);
  const hit = j.buckets.reduce((a, b, i) => a + (sel[i] ? b.count : 0), 0);

  document.getElementById('pp-center').textContent = hit;
  document.getElementById('pp-center-l').textContent = f ? 'of ' + total + ' patients' : 'Patients';

  const bg = j.buckets.map((b, i) => sel[i] ? ppColors[i] : ppColors[i] + '30');
  const off = sel.map(s => f && s ? 14 : 0);   // pop selected slices out
  if (ppChart) ppChart.destroy();
  ppChart = new Chart(document.getElementById('pp-chart'), {
    type: 'doughnut',
    data: { labels: j.buckets.map(b => b.label), datasets: [{ data: j.buckets.map(b => b.count), backgroundColor: bg, offset: off, borderWidth: 2 }] },
    options: { cutout: '62%', layout: { padding: 14 }, plugins: { legend: { display: false } } }
  });

  document.getElementById('pp-legend').innerHTML = j.buckets.map((b, i) =>
    `<div style="display:flex;align-items:center;gap:8px;padding:3px 0;opacity:${sel[i] ? 1 : .4};font-weight:${f && sel[i] ? 700 : 400};">
       <span style="width:10px;height:10px;border-radius:50%;background:${ppColors[i]};"></span>
       <span style="flex:1;">${ppEsc(b.label)}</span><span>${b.count}</span></div>`).join('');

  const tb = document.getElementById('pp-tbody');
  tb.innerHTML = j.patients.length ? j.patients.map((r, i) => `<tr style="border-bottom:1px solid var(--gray-100);">
      <td style="padding:9px 12px;color:var(--gray-500);">${(j.page - 1) * 50 + i + 1}</td>
      <td style="padding:9px 12px;font-weight:600;">${ppEsc(r.first_name + ' ' + r.last_name)}</td>
      <td style="padding:9px 12px;font-family:monospace;font-size:12px;color:var(--primary-color);">${ppEsc(r.patient_no)}</td>
      <td style="padding:9px 12px;font-size:12px;">${ppEsc(r.phone)}</td>
      <td style="padding:9px 12px;text-align:center;font-weight:700;">${r.cnt}</td>
      <td style="padding:9px 12px;font-size:12px;">${ppEsc(r.first_visit)}</td>
      <td style="padding:9px 12px;font-size:12px;">${ppEsc(r.last_visit)}</td></tr>`).join('')
    : '<tr><td colspan="7" style="padding:50px;text-align:center;color:var(--gray-400);">No patients found.</td></tr>';

  document.getElementById('pp-pager').innerHTML = `<span>${j.total} patients</span>` + (j.pages > 1 ? `<span>
      <button ${j.page <= 1 ? 'disabled' : ''} onclick="ppPage--;ppLoad()" style="padding:5px 12px;border:1px solid var(--gray-200);background:white;border-radius:6px;cursor:pointer;">Prev</button>
      Page ${j.page} / ${j.pages}
      <button ${j.page >= j.pages ? 'disabled' : ''} onclick="ppPage++;ppLoad()" style="padding:5px 12px;border:1px solid var(--gray-200);background:white;border-radius:6px;cursor:pointer;">Next</button></span>` : '');
}

ppLoad();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
