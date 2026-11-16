<?php
/**
 * Clinical dashboard body for doctor, nurse and admin (admin adds revenue + payer donut; dashboard.php then appends dash/admin_extra.php).
 * Data: api.php?endpoint=consultation/consult&action=dashboard_data
 */
$d2Uid = (int)($_SESSION['user_id'] ?? 0);
$d2DeptId = 0;
$d2DeptName = '';
if ($d2Uid && isset($pdo)) {
    try {
        $d2q = $pdo->prepare("SELECT u.department_id, d.name FROM users u LEFT JOIN departments d ON d.id = u.department_id WHERE u.id = ?");
        $d2q->execute([$d2Uid]);
        if ($d2r = $d2q->fetch()) {
            $d2DeptId = (int)($d2r['department_id'] ?? 0);
            $d2DeptName = (string)($d2r['name'] ?? '');
        }
    } catch (Throwable $e) {}
}

$d2Role = $isAdm ? 'admin' : ($isDoc ? 'doctor' : 'nurse');
// Nurses open on the nursing department by default; doctors on their own. Both can switch to any department.
if ($isAdm) { $d2DeptId = 0; $d2DeptName = ''; }   // admin sees the whole clinic by default
if (!$isDoc && !$isAdm && isset($pdo)) {
    try {
        $d2n = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 AND name LIKE '%nurs%' ORDER BY id LIMIT 1")->fetch();
        if ($d2n) { $d2DeptId = (int)$d2n['id']; $d2DeptName = (string)$d2n['name']; }
    } catch (Throwable $e) {}
}
$d2FirstName = trim((string)$firstName) !== '' ? $firstName : 'there';

if ($isAdm) {
    // Everything the doctor and nurse see, plus money
    $d2Cards = [
        'patients_today'  => ['label' => 'Total patients',             'icon' => 'fa-user-check',     'color' => '#2563eb', 'bg' => '#dbeafe', 'foot' => 'Visits in range'],
        'in_queue'        => ['label' => 'Patient in queue',           'icon' => 'fa-people-line',    'color' => '#0891b2', 'bg' => '#cffafe', 'foot' => 'Waiting or called'],
        'lab_pending'     => ['label' => 'Lab test pending',           'icon' => 'fa-hourglass-half', 'color' => '#d97706', 'bg' => '#fef3c7', 'foot' => 'Ordered or in progress'],
        'completed'       => ['label' => 'Patient completed',          'icon' => 'fa-circle-check',   'color' => '#059669', 'bg' => '#d1fae5', 'foot' => 'Visits closed in range'],
        'unclosed'        => ['label' => 'Unclosed visit',             'icon' => 'fa-clock',          'color' => '#dc2626', 'bg' => '#fee2e2', 'foot' => 'Open or in progress'],
        'emergency'       => ['label' => 'Patient in emergency',       'icon' => 'fa-truck-medical',  'color' => '#ea580c', 'bg' => '#ffedd5', 'foot' => 'Emergency cases in range'],
        'hospitalization' => ['label' => 'Patient in hospitalization', 'icon' => 'fa-bed',            'color' => '#dc2626', 'bg' => '#fee2e2', 'foot' => 'Currently admitted'],
        'revenue'         => ['label' => 'Revenue',                    'icon' => 'fa-coins',          'color' => '#b45309', 'bg' => '#fef3c7', 'foot' => 'Billed in range', 'money' => true],
    ];
} elseif ($isDoc) {
    $d2Cards = [
        'patients_today' => ['label' => 'Total patients today',      'icon' => 'fa-user-check',     'color' => '#2563eb', 'bg' => '#dbeafe', 'foot' => 'Visits in range'],
        'in_queue'       => ['label' => 'Patient in queue',          'icon' => 'fa-people-line',    'color' => '#0891b2', 'bg' => '#cffafe', 'foot' => 'Waiting or called'],
        'lab_pending'    => ['label' => 'Lab test pending',          'icon' => 'fa-hourglass-half', 'color' => '#d97706', 'bg' => '#fef3c7', 'foot' => 'Ordered or in progress'],
        'completed'      => ['label' => 'Total patient completed',   'icon' => 'fa-circle-check',   'color' => '#059669', 'bg' => '#d1fae5', 'foot' => 'Visits closed in range'],
        'unclosed'       => ['label' => 'Unclosed visit',            'icon' => 'fa-clock',          'color' => '#dc2626', 'bg' => '#fee2e2', 'foot' => 'Open or in progress'],
    ];
} else {
    $d2Cards = [
        'in_queue'        => ['label' => 'Patient in queue',            'icon' => 'fa-people-line',    'color' => '#0891b2', 'bg' => '#cffafe', 'foot' => 'Waiting or called'],
        'completed'       => ['label' => 'Patient completed',           'icon' => 'fa-circle-check',   'color' => '#059669', 'bg' => '#d1fae5', 'foot' => 'Visits closed in range'],
        'emergency'       => ['label' => 'Patient in emergency',        'icon' => 'fa-truck-medical',  'color' => '#ea580c', 'bg' => '#ffedd5', 'foot' => 'Emergency cases in range'],
        'hospitalization' => ['label' => 'Patient in hospitalization',  'icon' => 'fa-bed',            'color' => '#dc2626', 'bg' => '#fee2e2', 'foot' => 'Currently admitted'],
    ];
}
?>

<style>
  .d2{
    --d2-ink:#111827; --d2-ink2:#374151; --d2-muted:#6b7280; --d2-muted2:#9aa2af;
    --d2-line:#e9ecf1; --d2-line2:#dfe3ea; --d2-brand:#2563eb; --d2-green:#15803d;
    --d2-amber:#b45309; --d2-red:#b91c1c; --d2-violet:#6d28d9;
    font-size:13px; color:var(--d2-ink);
  }
  .d2 *{box-sizing:border-box}
  .d2-title{margin:0;font-size:26px;line-height:1.15;font-weight:650;letter-spacing:-.03em;color:var(--d2-ink)}
  .d2-eyebrow{font-size:10px;font-weight:650;letter-spacing:.13em;text-transform:uppercase;color:var(--d2-muted2);margin-bottom:5px}
  .d2-head{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:18px}
  .d2-head-main{min-width:0}
  .d2-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:8px 0 0;color:var(--d2-muted);font-size:11.5px}
  .d2-dot{width:3px;height:3px;border-radius:50%;background:#c7ccd5}
  .d2-chip{display:inline-flex;align-items:center;gap:6px;padding:3px 9px;border-radius:999px;background:#eef2f7;border:1px solid #e3e8ef;color:var(--d2-ink2);font-size:10.5px;font-weight:600}
  .d2-chip i{width:5px;height:5px;border-radius:50%;background:var(--d2-brand);display:block}

  .d2-range{display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;flex:none}
  .d2-date{display:flex;flex-direction:column;gap:3px}
  .d2-date span{font-size:9px;font-weight:650;letter-spacing:.07em;text-transform:uppercase;color:var(--d2-muted2)}
  .d2-date input{padding:6px 8px;border:1px solid var(--d2-line2);border-radius:7px;background:#fff;font-size:11.5px;color:var(--d2-ink2);outline:none}
  .d2-date input:focus{border-color:#bfd3fb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
  .d2-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;border-radius:7px;border:1px solid var(--d2-line2);background:#fff;font-size:11px;font-weight:600;color:var(--d2-ink2);cursor:pointer;transition:all .15s ease}
  .d2-btn:hover{background:#f8fafc}
  .d2-btn--primary{background:var(--d2-brand);border-color:var(--d2-brand);color:#fff}
  .d2-btn--primary:hover{background:#1d4ed8}
  .d2-btn:disabled{opacity:.65;cursor:wait}
  .d2-presets{display:flex;gap:2px;padding:2px;border:1px solid var(--d2-line);border-radius:7px;background:#f8fafc}
  .d2-presets button{border:0;background:transparent;padding:4px 8px;border-radius:5px;font-size:10px;font-weight:600;color:var(--d2-muted);cursor:pointer}
  .d2-presets button:hover{background:#fff;color:var(--d2-ink2);box-shadow:0 1px 2px rgba(15,23,42,.08)}

  .d2-error{display:flex;align-items:center;gap:8px;margin-bottom:14px;padding:10px 12px;border:1px solid #fbdada;border-radius:10px;background:#fef2f2;color:var(--d2-red);font-size:11.5px}
  .d2-error[hidden]{display:none}
  .d2-error button{margin-left:auto;border:1px solid #f3b9b9;background:#fff;color:var(--d2-red);border-radius:6px;padding:3px 9px;font-size:10.5px;font-weight:600;cursor:pointer}

  .d2-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:16px}
  .d2-stat{background:#fff;border:1px solid var(--d2-line);border-radius:14px;padding:14px 15px 13px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px -8px rgba(15,23,42,.10);min-width:0;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;animation:d2FadeUp .45s cubic-bezier(.22,.61,.36,1) both}
  .d2-stat:hover{transform:translateY(-2px);box-shadow:0 2px 4px rgba(15,23,42,.04),0 12px 24px -14px rgba(15,23,42,.18);border-color:var(--d2-line2)}
  .d2-stat:nth-child(1){animation-delay:0ms}.d2-stat:nth-child(2){animation-delay:40ms}.d2-stat:nth-child(3){animation-delay:80ms}.d2-stat:nth-child(4){animation-delay:120ms}.d2-stat:nth-child(5){animation-delay:160ms}
  .d2-stat-top{display:flex;align-items:center;gap:8px;min-width:0}
  .d2-stat-icon{width:26px;height:26px;border-radius:8px;flex:none;display:grid;place-items:center;font-size:13px}
  .d2-stat-label{flex:1;min-width:0;font-size:9.5px;font-weight:650;letter-spacing:.075em;text-transform:uppercase;color:var(--d2-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .d2-kebab{flex:none;color:#b3bac6;font-size:15px;line-height:1;width:16px;text-align:center;user-select:none}
  .d2-stat-value{font-size:31px;font-weight:650;letter-spacing:-.035em;line-height:1.1;margin:9px 0 4px;color:var(--d2-ink);font-variant-numeric:tabular-nums}
  .d2-stat-value--money{font-size:21px;margin:13px 0 7px}
  .d2-stat-foot{font-size:10.5px;color:var(--d2-muted);min-height:16px}

  .d2-layout{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:16px;align-items:start}
  .d2-side{display:flex;flex-direction:column;gap:16px;min-width:0}
  .d2-card{background:#fff;border:1px solid var(--d2-line);border-radius:14px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px -8px rgba(15,23,42,.10);min-width:0;overflow:hidden}
  .d2-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:15px 18px 13px}
  .d2-card-head--bordered{border-bottom:1px solid var(--d2-line)}
  .d2-card-eyebrow{font-size:9.5px;font-weight:650;letter-spacing:.11em;text-transform:uppercase;color:var(--d2-muted2);margin-bottom:3px}
  .d2-card-title{margin:0;font-size:13.5px;font-weight:650;letter-spacing:-.01em;color:var(--d2-ink)}
  .d2-card-sub{margin:3px 0 0;font-size:10.5px;color:var(--d2-muted)}
  .d2-count{display:inline-grid;place-items:center;min-width:20px;height:18px;padding:0 6px;margin-left:5px;border-radius:999px;vertical-align:1px;background:#eef2f7;color:var(--d2-ink2);font-size:10px;font-weight:650;font-variant-numeric:tabular-nums}

  .d2-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
  .d2-search{display:flex;align-items:center;gap:7px;padding:6px 10px;border:1px solid var(--d2-line);border-radius:8px;background:#fbfcfd;transition:border-color .15s ease,background .15s ease,box-shadow .15s ease}
  .d2-search:focus-within{border-color:#bfd3fb;background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
  .d2-search i{font-size:12px;color:var(--d2-muted2);flex:none}
  .d2-search input{border:0;background:transparent;outline:none;width:196px;font-size:11.5px;color:var(--d2-ink)}
  .d2-search input::placeholder{color:var(--d2-muted2)}
  .d2-select{appearance:none;padding:6px 28px 6px 10px;border:1px solid var(--d2-line);border-radius:8px;background:#fbfcfd url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 9px center;font-size:11.5px;color:var(--d2-ink2);cursor:pointer;max-width:220px}
  .d2-select:focus{outline:none;border-color:#bfd3fb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}

  .d2-table-wrap{overflow-x:auto}
  .d2-table{width:100%;min-width:850px;border-collapse:separate;border-spacing:0;margin:0}
  .d2-table thead th{background:#fbfcfd;padding:9px 14px;text-align:left;font-size:9.5px;font-weight:650;letter-spacing:.075em;text-transform:uppercase;color:var(--d2-muted2);white-space:nowrap;border-bottom:1px solid var(--d2-line)}
  .d2-table tbody td{padding:10px 14px;border-bottom:1px solid #f1f3f7;vertical-align:middle}
  .d2-table tbody tr{transition:background .14s ease;animation:d2FadeUp .32s cubic-bezier(.22,.61,.36,1) both}
  .d2-table tbody tr:hover{background:#fafbff}
  .d2-table tbody tr:last-child td{border-bottom:0}
  .d2-patient{display:flex;align-items:center;gap:10px;min-width:0}
  .d2-avatar{--a:#e0e7ff;--b:#3730a3;width:34px;height:34px;border-radius:50%;flex:none;display:grid;place-items:center;background:var(--a);color:var(--b);font-size:10.5px;font-weight:700;letter-spacing:.02em;border:1px solid rgba(15,23,42,.04)}
  .d2-avatar--sm{width:30px;height:30px;font-size:9.5px}
  .d2-patient-meta{display:flex;flex-direction:column;min-width:0;line-height:1.3}
  .d2-patient-name{font-size:12.5px;font-weight:600;letter-spacing:-.005em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--d2-ink)}
  .d2-patient-sub{font-size:10px;color:var(--d2-muted2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-variant-numeric:tabular-nums}
  .d2-visit{font-size:11.5px;font-weight:600;color:var(--d2-ink2);font-variant-numeric:tabular-nums;letter-spacing:.01em}
  .d2-type{display:flex;flex-direction:column;line-height:1.3}
  .d2-type span:first-child{font-size:11.5px;font-weight:500;color:var(--d2-ink2)}
  .d2-time{font-size:10px;color:var(--d2-muted2);font-variant-numeric:tabular-nums}
  .d2-ins{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:600;letter-spacing:.01em;white-space:nowrap;border:1px solid transparent;max-width:170px;overflow:hidden;text-overflow:ellipsis}
  .d2-ins i{width:5px;height:5px;border-radius:50%;background:currentColor;display:block;flex:none}
  .d2-ins--rssb{background:#eff4ff;color:#1e5ac8;border-color:#dbe8ff}
  .d2-ins--mutuelle{background:#ecfdf3;color:#137a43;border-color:#d6f5e3}
  .d2-ins--private{background:#f5f3ff;color:#6b35c9;border-color:#e9e2fd}
  .d2-ins--cash{background:#f1f5f9;color:#526071;border-color:#e5eaf1}
  .d2-status{display:flex;flex-direction:column;gap:5px;align-items:flex-start}
  .d2-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:600;white-space:nowrap;border:1px solid transparent}
  .d2-pill i{width:5px;height:5px;border-radius:50%;background:currentColor;display:block;flex:none}
  .d2-pill--completed{background:#ecfdf3;color:var(--d2-green);border-color:#d6f5e3}
  .d2-pill--consult{background:#eff4ff;color:#1e5ac8;border-color:#dbe8ff}
  .d2-pill--waiting{background:#fffaeb;color:var(--d2-amber);border-color:#fbeccb}
  .d2-pill--lab{background:#f5f3ff;color:var(--d2-violet);border-color:#e9e2fd}
  .d2-pill--scheduled{background:#f1f5f9;color:#526071;border-color:#e5eaf1}
  .d2-progress{width:44px;height:3px;border-radius:999px;background:#edf0f4;overflow:hidden}
  .d2-progress span{display:block;height:100%;border-radius:inherit;background:currentColor}
  .d2-progress--completed{color:#22c55e}.d2-progress--consult{color:#3b82f6}.d2-progress--waiting{color:#f59e0b}.d2-progress--lab{color:#8b5cf6}.d2-progress--scheduled{color:#94a3b8}
  .d2-td-action{text-align:right;white-space:nowrap}
  .d2-open{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:7px;border:1px solid var(--d2-line2);background:#fff;font-size:10.5px;font-weight:600;color:var(--d2-ink2);text-decoration:none;transition:all .16s ease}
  .d2-open i{font-size:10px;transition:transform .16s ease}
  .d2-open:hover{background:var(--d2-brand);border-color:var(--d2-brand);color:#fff;box-shadow:0 4px 10px -5px rgba(37,99,235,.55)}
  .d2-open:hover i{transform:translate(1px,-1px)}
  .d2-empty,.d2-loading{padding:38px 20px !important;text-align:center;color:var(--d2-muted);font-size:12px}
  .d2-empty i,.d2-loading i{margin-right:6px;color:#c3c9d3}
  .d2-table-foot{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:11px 16px;border-top:1px solid var(--d2-line)}
  .d2-range-label{font-size:10.5px;color:var(--d2-muted);font-variant-numeric:tabular-nums}
  .d2-pager{display:flex;align-items:center;gap:4px}
  .d2-page{min-width:28px;height:28px;padding:0 7px;display:grid;place-items:center;border:1px solid transparent;border-radius:7px;background:transparent;font-size:11px;font-weight:600;color:var(--d2-muted);transition:all .15s ease;font-variant-numeric:tabular-nums;cursor:pointer}
  .d2-page:hover:not(:disabled):not(.is-active){background:#f2f4f8;color:var(--d2-ink2)}
  .d2-page.is-active{background:var(--d2-brand);color:#fff;box-shadow:0 3px 8px -4px rgba(37,99,235,.7)}
  .d2-page:disabled{opacity:.35;cursor:not-allowed}

  .d2-donut-wrap{position:relative;width:160px;height:160px;margin:2px auto 0}
  .d2-donut-wrap svg{width:100%;height:100%;overflow:visible}
  .d2-donut-center{position:absolute;inset:0;display:grid;place-content:center;text-align:center;pointer-events:none}
  .d2-donut-center strong{font-size:25px;font-weight:650;letter-spacing:-.03em;line-height:1.1;font-variant-numeric:tabular-nums;color:var(--d2-ink)}
  .d2-donut-center span{font-size:9px;font-weight:600;letter-spacing:.09em;text-transform:uppercase;color:var(--d2-muted2);margin-top:2px}
  .d2-gender-legend{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;padding:14px 14px 16px}
  .d2-glegend{display:flex;flex-direction:column;align-items:center;gap:3px;min-width:0}
  .d2-glegend-top{display:flex;align-items:center;gap:5px;min-width:0}
  .d2-gdot{width:7px;height:7px;border-radius:50%;flex:none}
  .d2-glabel{font-size:10.5px;font-weight:600;color:var(--d2-ink2);white-space:nowrap}
  .d2-gpct{font-size:11px;font-weight:500;color:var(--d2-muted);font-variant-numeric:tabular-nums}
  .d2-dlegend{padding:2px 18px 14px;display:flex;flex-direction:column}
  .d2-dlegend-row{display:grid;grid-template-columns:8px 1fr auto 34px;align-items:center;gap:8px;padding:7px 0;border-top:1px solid #f2f4f7}
  .d2-dlegend-row:first-child{border-top:0}
  .d2-ddot{width:7px;height:7px;border-radius:50%}
  .d2-dname{font-size:11px;font-weight:500;color:var(--d2-ink2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .d2-dval{font-size:10.5px;color:var(--d2-muted);font-variant-numeric:tabular-nums}
  .d2-dpct{font-size:10.5px;font-weight:600;color:var(--d2-ink2);text-align:right;font-variant-numeric:tabular-nums}
  .d2-nodata{padding:6px 0 4px;font-size:10.5px;color:var(--d2-muted2);text-align:center}
  .d2-nodata--pad{padding:24px 16px}

  .d2-esc-list{display:flex;flex-direction:column;padding:2px 8px 8px}
  .d2-esc-row{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:9px;text-decoration:none;transition:background .15s ease}
  .d2-esc-row:hover{background:#f8fafc}
  .d2-esc-meta{display:flex;flex-direction:column;min-width:0;flex:1;line-height:1.3}
  .d2-esc-name{font-size:11.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--d2-ink)}
  .d2-esc-sub{font-size:10px;color:var(--d2-muted2);font-variant-numeric:tabular-nums;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .d2-esc-badge{display:inline-flex;align-items:center;gap:4px;flex:none;padding:3px 7px;border-radius:999px;font-size:9px;font-weight:650;letter-spacing:.03em;text-transform:uppercase;border:1px solid transparent}
  .d2-esc-badge i{width:4px;height:4px;border-radius:50%;background:currentColor;display:block}
  .d2-esc-badge--er{background:#fef2f2;color:var(--d2-red);border-color:#fbdada}
  .d2-esc-badge--admit{background:#eff4ff;color:#1e5ac8;border-color:#dbe8ff}
  .d2-esc-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 18px 13px;border-top:1px solid var(--d2-line);font-size:10.5px;color:var(--d2-muted)}
  .d2-esc-links{display:flex;gap:10px}
  .d2-esc-links a{color:var(--d2-brand);font-weight:600;text-decoration:none}
  .d2-esc-links a:hover{color:#1d4ed8}

  .d2-busy .d2-table-wrap,.d2-busy .d2-side{opacity:.55;pointer-events:none;transition:opacity .15s ease}
  @keyframes d2FadeUp{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}

  @media (max-width:1240px){
    .d2-stats{grid-template-columns:repeat(3,minmax(0,1fr))}
    .d2-layout{grid-template-columns:minmax(0,1fr)}
    .d2-side{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:start}
    .d2-side .d2-card:last-child{grid-column:1 / -1}
  }
  @media (max-width:900px){
    .d2-head{align-items:flex-start;flex-direction:column;gap:12px}
    .d2-range{width:100%}
    .d2-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .d2-tools{width:100%;justify-content:flex-start}
    .d2-search{flex:1}
    .d2-search input{width:100%}
    .d2-select{flex:1;max-width:none}
    .d2-side{grid-template-columns:minmax(0,1fr)}
    .d2-side .d2-card:last-child{grid-column:auto}
  }
  @media (max-width:520px){
    .d2-title{font-size:23px}
    .d2-stats{grid-template-columns:minmax(0,1fr)}
    .d2-stat-value{font-size:27px}
    .d2-card-head{flex-wrap:wrap}
  }
  @media (prefers-reduced-motion:reduce){
    .d2 *,.d2 *::before,.d2 *::after{animation-duration:.001ms !important;animation-iteration-count:1 !important;transition-duration:.001ms !important}
  }
</style>

<div class="d2" id="d2" data-role="<?= htmlspecialchars($d2Role) ?>">
  <div class="d2-head">
    <div class="d2-head-main">
      <div class="d2-eyebrow"><?= htmlspecialchars($greeting) ?></div>
      <h1 class="d2-title"><?= htmlspecialchars($d2FirstName) ?>, here is your day</h1>
      <div class="d2-meta">
        <span id="d2-booked">— patients booked</span>
        <span class="d2-dot"></span>
        <span id="d2-first">First patient at —</span>
        <span class="d2-chip" id="d2-dept-chip"><i></i><?= htmlspecialchars($d2DeptName !== '' ? $d2DeptName : 'All departments') ?></span>
      </div>
    </div>

    <div class="d2-range">
      <label class="d2-date"><span>From</span><input type="date" id="d2-from" value="<?= date('Y-m-d') ?>"></label>
      <label class="d2-date"><span>To</span><input type="date" id="d2-to" value="<?= date('Y-m-d') ?>"></label>
      <button type="button" class="d2-btn d2-btn--primary" id="d2-apply" onclick="d2Load()"><i class="fas fa-search"></i> Apply</button>
      <div class="d2-presets" aria-label="Quick date ranges">
        <button type="button" data-d2-range="today">Today</button>
        <button type="button" data-d2-range="week">Week</button>
        <button type="button" data-d2-range="month">Month</button>
      </div>
    </div>
  </div>

  <div class="d2-error" id="d2-error" hidden></div>

  <div class="d2-stats" aria-label="Today at a glance">
    <?php foreach ($d2Cards as $d2Key => $d2Card): ?>
      <article class="d2-stat">
        <div class="d2-stat-top">
          <span class="d2-stat-icon" style="background:<?= $d2Card['bg'] ?>;color:<?= $d2Card['color'] ?>"><i class="fas <?= $d2Card['icon'] ?>"></i></span>
          <span class="d2-stat-label" title="<?= htmlspecialchars($d2Card['label']) ?>"><?= htmlspecialchars($d2Card['label']) ?></span>
          <span class="d2-kebab" aria-hidden="true">⋯</span>
        </div>
        <div class="d2-stat-value<?= !empty($d2Card['money']) ? ' d2-stat-value--money' : '' ?>" data-d2-value="<?= htmlspecialchars($d2Key) ?>">—</div>
        <div class="d2-stat-foot" id="d2-foot-<?= htmlspecialchars($d2Key) ?>"><?= htmlspecialchars($d2Card['foot']) ?></div>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="d2-layout">
    <div class="d2-card">
      <div class="d2-card-head d2-card-head--bordered">
        <div>
          <div class="d2-card-eyebrow">Department patients</div>
          <h2 class="d2-card-title">All patients in department <span class="d2-count" id="d2-table-count">—</span></h2>
        </div>
        <div class="d2-tools">
          <label class="d2-search">
            <i class="fas fa-search"></i>
            <input id="d2-search" type="search" placeholder="Search patient, visit, insurance…" aria-label="Search patients">
          </label>
          <select id="d2-dept" class="d2-select" aria-label="Filter by department" onchange="d2Load()">
            <option value="0">All departments</option>
            <?php if ($d2DeptId && $d2DeptName !== ''): ?>
              <option value="<?= (int)$d2DeptId ?>" selected><?= htmlspecialchars($d2DeptName) ?></option>
            <?php endif; ?>
          </select>
        </div>
      </div>

      <div class="d2-table-wrap">
        <table class="d2-table">
          <colgroup>
            <col style="width:31%"><col style="width:11%"><col style="width:15%"><col style="width:11%"><col style="width:17%"><col style="width:15%">
          </colgroup>
          <thead>
            <tr>
              <th scope="col">Patient</th>
              <th scope="col">Visit number</th>
              <th scope="col">Type</th>
              <th scope="col">Insurance</th>
              <th scope="col">Status</th>
              <th scope="col" style="text-align:right">Action</th>
            </tr>
          </thead>
          <tbody id="d2-tbody">
            <tr><td colspan="6" class="d2-loading"><i class="fas fa-spinner fa-spin"></i> Loading patients…</td></tr>
          </tbody>
        </table>
      </div>

      <div class="d2-table-foot">
        <span class="d2-range-label" id="d2-range-label">Loading…</span>
        <div class="d2-pager" id="d2-pager" aria-label="Pagination"></div>
      </div>
    </div>

    <aside class="d2-side">
      <div class="d2-card">
        <div class="d2-card-head">
          <div>
            <div class="d2-card-eyebrow">Patient activity</div>
            <h2 class="d2-card-title">Today by gender &amp; age</h2>
          </div>
          <span class="d2-kebab" aria-hidden="true">⋯</span>
        </div>
        <div class="d2-donut-wrap">
          <svg id="d2-gender-donut" viewBox="0 0 120 120" role="img" aria-label="Patient activity by gender and age"></svg>
          <div class="d2-donut-center"><strong id="d2-gender-total">—</strong><span>patients</span></div>
        </div>
        <div class="d2-gender-legend" id="d2-gender-legend"></div>
      </div>

      <div class="d2-card">
        <div class="d2-card-head">
          <div>
            <div class="d2-card-eyebrow"><?= $isAdm ? 'Payers' : ($isDoc ? 'Clinical mix' : 'Medication') ?></div>
            <h2 class="d2-card-title"><?= $isAdm ? 'Patients by insurance' : ($isDoc ? 'Diagnoses today' : 'Drugs administered') ?></h2>
            <p class="d2-card-sub"><?= $isAdm ? 'Visits by payer in the selected range' : ($isDoc ? 'Top diagnoses across visits in range' : 'Doses given by the nursing station today') ?></p>
          </div>
          <span class="d2-kebab" aria-hidden="true">⋯</span>
        </div>
        <div class="d2-donut-wrap" style="width:148px;height:148px">
          <svg id="d2-clinical-donut" viewBox="0 0 120 120" role="img" aria-label="Clinical mix"></svg>
          <div class="d2-donut-center"><strong id="d2-clinical-total">—</strong><span><?= $isAdm ? 'visits' : ($isDoc ? 'diagnosed' : 'doses') ?></span></div>
        </div>
        <div class="d2-dlegend" id="d2-clinical-legend"></div>
      </div>

      <div class="d2-card">
        <div class="d2-card-head">
          <div>
            <div class="d2-card-eyebrow">Escalated today</div>
            <h2 class="d2-card-title">Emergency &amp; hospitalization <span class="d2-count" id="d2-esc-count">—</span></h2>
            <p class="d2-card-sub"><?= $isAdm ? 'Latest emergency cases and admissions' : 'Patients you sent out of the department' ?></p>
          </div>
        </div>
        <div class="d2-esc-list" id="d2-esc-list">
          <div class="d2-nodata d2-nodata--pad">Loading…</div>
        </div>
        <div class="d2-esc-foot">
          <span id="d2-esc-summary">—</span>
          <span class="d2-esc-links"><a href="emergency.php">Emergency</a><a href="ward.php">Ward</a></span>
        </div>
      </div>
    </aside>
  </div>
</div>

<?php if ($isAdm): ?>
<!-- Patient Type details modal -->
<div id="pt-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:white;border-radius:12px;max-width:1000px;width:100%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 25px -5px rgba(0,0,0,0.2);padding:24px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;border-bottom:1px solid var(--gray-200);padding-bottom:12px;">
      <div>
        <h3 id="pt-modal-title" style="font-size:16px;font-weight:700;margin:0;"></h3>
        <p id="pt-modal-subtitle" style="font-size:12px;color:var(--gray-500);margin:4px 0 0 0;"></p>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <button onclick="d2PayerExport()" style="padding:6px 12px;background:#16a34a;color:white;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;"><i class="fas fa-file-excel"></i> Excel</button>
        <button onclick="document.getElementById('pt-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--gray-400);">&times;</button>
      </div>
    </div>
    <div id="pt-modal-content" style="overflow-x:auto;"></div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<?php endif; ?>

<script>
(function () {
  'use strict';

  var D2_API = (window._apiBase || (window.location.origin + '/medlink/api.php')) + '?endpoint=';
  var D2_ROLE = <?= json_encode($d2Role) ?>;
  var PAGE_SIZE = 20;
  var state = { data: null, patients: [], filtered: [], page: 1, dept: <?= (int)$d2DeptId ?> };
  var avatarPalette = [
    ['#e0e7ff', '#3730a3'], ['#dcfce7', '#166534'], ['#fef3c7', '#92400e'], ['#fce7f3', '#9d174d'],
    ['#e0f2fe', '#075985'], ['#ede9fe', '#5b21b6'], ['#fee2e2', '#991b1b'], ['#ccfbf1', '#115e59']
  ];

  function el(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function pad2(n) { return n < 10 ? '0' + n : String(n); }
  function initials(name) {
    return String(name || '').split(/[\s-]+/).filter(Boolean).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();
  }
  function fmtDate(d) {
    return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
  }
  function insClass(name) {
    var n = String(name || '').toLowerCase();
    if (n.indexOf('rssb') !== -1) return 'rssb';
    if (n.indexOf('mutuelle') !== -1 || n.indexOf('mmi') !== -1) return 'mutuelle';
    if (n.indexOf('cash') !== -1 || n.indexOf('self') !== -1) return 'cash';
    return 'private';
  }
  function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }

  function setLoading(on) {
    var d2 = el('d2');
    if (d2) d2.classList.toggle('d2-busy', !!on);
    var btn = el('d2-apply');
    if (btn) {
      btn.disabled = !!on;
      btn.innerHTML = on ? '<i class="fas fa-spinner fa-spin"></i> Loading' : '<i class="fas fa-search"></i> Apply';
    }
  }
  function showError(msg) {
    var box = el('d2-error');
    if (!box) return;
    box.hidden = false;
    box.innerHTML = '<i class="fas fa-triangle-exclamation"></i><span>' + esc(msg) + '</span><button type="button" onclick="d2Load()">Retry</button>';
  }
  function hideError() { var box = el('d2-error'); if (box) box.hidden = true; }

  function drawDonut(svg, segments, total, emptyColor) {
    if (!svg) return;
    var r = 50, cx = 60, cy = 60, C = 2 * Math.PI * r;
    svg.innerHTML = '';
    if (!total) {
      var ring = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      ring.setAttribute('cx', cx); ring.setAttribute('cy', cy); ring.setAttribute('r', r);
      ring.setAttribute('fill', 'none'); ring.setAttribute('stroke', emptyColor || '#eef1f5');
      ring.setAttribute('stroke-width', '15');
      svg.appendChild(ring);
      return;
    }
    var offset = 0;
    segments.forEach(function (seg, i) {
      if (!seg.value) return;
      var len = (seg.value / total) * C;
      var gap = Math.min(4, len * 0.25);
      var circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      circle.setAttribute('cx', cx); circle.setAttribute('cy', cy); circle.setAttribute('r', r);
      circle.setAttribute('fill', 'none'); circle.setAttribute('stroke', seg.color);
      circle.setAttribute('stroke-width', '15');
      circle.setAttribute('stroke-dasharray', '0 ' + C);
      circle.setAttribute('stroke-dashoffset', String(-offset));
      circle.setAttribute('transform', 'rotate(-90 ' + cx + ' ' + cy + ')');
      circle.style.transition = 'stroke-dasharray .7s cubic-bezier(.4,0,.2,1) ' + (i * 70) + 'ms';
      svg.appendChild(circle);
      offset += len;
      (function (c, dash) {
        requestAnimationFrame(function () {
          requestAnimationFrame(function () { c.setAttribute('stroke-dasharray', dash + ' ' + C); });
        });
      })(circle, Math.max(len - gap, 0.5));
    });
  }

  function renderCards() {
    var c = (state.data && state.data.counts) || {};
    document.querySelectorAll('[data-d2-value]').forEach(function (node) {
      var key = node.getAttribute('data-d2-value');
      var v = Number(c[key] || 0);
      node.textContent = v;
    });
    var m = state.money || {};
    ['revenue', 'collected'].forEach(function (k) {
      var n = document.querySelector('[data-d2-value="' + k + '"]');
      if (n) n.textContent = m[k] == null ? '—' : Number(m[k]).toLocaleString() + ' RWF';
    });
    var booked = Number(c.patients_today || 0);
    var done = Number(c.completed || 0);
    var foot = el('d2-foot-completed');
    if (foot) foot.textContent = booked ? Math.round((done / booked) * 100) + '% of visits in range' : 'Visits closed in range';
  }

  function renderMeta() {
    var c = (state.data && state.data.counts) || {};
    var booked = Number(c.patients_today || 0);
    var bookedEl = el('d2-booked');
    if (bookedEl) bookedEl.textContent = booked + (booked === 1 ? ' patient booked' : ' patients booked');
    var firstEl = el('d2-first');
    if (firstEl) firstEl.textContent = (state.data && state.data.first_time) ? 'First patient at ' + state.data.first_time : 'No patients in range';
    var chip = el('d2-dept-chip');
    if (chip) {
      var name = 'All departments';
      if (state.dept) {
        var list = (state.data && state.data.departments) || [];
        for (var i = 0; i < list.length; i++) { if (Number(list[i].id) === Number(state.dept)) { name = list[i].name; break; } }
        if (name === 'All departments' && state.data && state.data.my_department) name = state.data.my_department.name || name;
      }
      chip.innerHTML = '<i></i>' + esc(name);
    }
  }

  function renderDeptOptions() {
    var sel = el('d2-dept');
    if (!sel) return;
    var current = String(state.dept || 0);
    var html = '<option value="0">All departments</option>';
    ((state.data && state.data.departments) || []).forEach(function (d) {
      html += '<option value="' + Number(d.id) + '">' + esc(d.name) + '</option>';
    });
    sel.innerHTML = html;
    sel.value = current;
    if (sel.value !== current) { sel.value = '0'; state.dept = 0; }
  }

  function rowHTML(p, index) {
    var pal = avatarPalette[Math.abs(Number(p.patient_id) || 0) % avatarPalette.length];
    var pct = Math.max(0, Math.min(100, (Number(p.step) || 1) / 4 * 100));
    return '<tr style="animation-delay:' + Math.min(index * 14, 140) + 'ms">' +
      '<td><div class="d2-patient">' +
        '<span class="d2-avatar" style="--a:' + pal[0] + ';--b:' + pal[1] + '">' + esc(initials(p.name)) + '</span>' +
        '<span class="d2-patient-meta">' +
          '<span class="d2-patient-name">' + esc(p.name) + '</span>' +
          '<span class="d2-patient-sub">' + Number(p.age || 0) + ' yrs · ' + esc(cap(p.gender)) + '</span>' +
        '</span>' +
      '</div></td>' +
      '<td><span class="d2-visit">' + esc(p.visit_no) + '</span></td>' +
      '<td><span class="d2-type"><span>' + esc(p.visit_type) + '</span><span class="d2-time">' + esc(p.time) + '</span></span></td>' +
      '<td><span class="d2-ins d2-ins--' + insClass(p.insurance) + '"><i></i>' + esc(p.insurance) + (p.cover != null ? ' · ' + Number(p.cover) + '%' : '') + '</span></td>' +
      '<td><span class="d2-status">' +
        '<span class="d2-pill d2-pill--' + esc(p.progress) + '"><i></i>' + esc(p.status_label) + '</span>' +
        '<span class="d2-progress d2-progress--' + esc(p.progress) + '" title="Step ' + Number(p.step || 1) + ' of 4"><span style="width:' + pct + '%"></span></span>' +
      '</span></td>' +
      '<td class="d2-td-action"><a class="d2-open" href="consultation.php?visit_id=' + Number(p.id) + '&amp;patient_id=' + Number(p.patient_id) + '" title="Open consultation for ' + esc(p.name) + '">Open <i class="fas fa-arrow-up-right-from-square"></i></a></td>' +
    '</tr>';
  }

  function emptyRow() {
    return '<tr><td colspan="6" class="d2-empty"><i class="fas fa-user-slash"></i>No patients match this view.</td></tr>';
  }

  function renderPager(pages) {
    var wrap = el('d2-pager');
    if (!wrap) return;
    var html = '<button type="button" class="d2-page" ' + (state.page === 1 ? 'disabled' : '') + ' aria-label="Previous page" onclick="d2Page(' + (state.page - 1) + ')"><i class="fas fa-chevron-left"></i></button>';
    var start = Math.max(1, Math.min(state.page - 2, pages - 4));
    var end = Math.min(pages, start + 4);
    for (var i = start; i <= end; i++) {
      html += '<button type="button" class="d2-page' + (i === state.page ? ' is-active' : '') + '" aria-label="Page ' + i + '" ' + (i === state.page ? 'aria-current="page"' : '') + ' onclick="d2Page(' + i + ')">' + i + '</button>';
    }
    html += '<button type="button" class="d2-page" ' + (state.page === pages ? 'disabled' : '') + ' aria-label="Next page" onclick="d2Page(' + (state.page + 1) + ')"><i class="fas fa-chevron-right"></i></button>';
    wrap.innerHTML = html;
  }

  function renderTable() {
    var q = (el('d2-search').value || '').trim().toLowerCase();
    state.filtered = state.patients.filter(function (p) {
      if (!q) return true;
      return (p.name + ' ' + p.patient_no + ' ' + p.visit_no + ' ' + p.insurance + ' ' + p.status_label + ' ' + p.department_name).toLowerCase().indexOf(q) !== -1;
    });
    var total = state.filtered.length;
    var pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if (state.page > pages) state.page = pages;
    var start = (state.page - 1) * PAGE_SIZE;
    var rows = state.filtered.slice(start, start + PAGE_SIZE);
    var tbody = el('d2-tbody');
    tbody.innerHTML = rows.length ? rows.map(rowHTML).join('') : emptyRow();
    var count = el('d2-table-count');
    if (count) count.textContent = total;
    var range = el('d2-range-label');
    if (range) range.textContent = total ? 'Showing ' + (start + 1) + '–' + Math.min(start + PAGE_SIZE, total) + ' of ' + total + ' patients' : 'No patients';
    renderPager(pages);
  }

  function renderGender() {
    var g = (state.data && state.data.gender) || { male: 0, female: 0, child: 0 };
    var total = Number(g.male || 0) + Number(g.female || 0) + Number(g.child || 0);
    var segments = [
      { label: 'Male', value: Number(g.male || 0), color: '#f59e0b' },
      { label: 'Female', value: Number(g.female || 0), color: '#8b5cf6' },
      { label: 'Child (<5)', value: Number(g.child || 0), color: '#38bdf8' }
    ];
    var totalEl = el('d2-gender-total');
    if (totalEl) totalEl.textContent = total;
    drawDonut(el('d2-gender-donut'), segments, total, '#eef1f5');
    var legend = el('d2-gender-legend');
    if (!legend) return;
    if (!total) {
      legend.innerHTML = '<div class="d2-nodata" style="grid-column:1/-1">No patients in this range.</div>';
      return;
    }
    legend.innerHTML = segments.map(function (s) {
      var pct = Math.round((s.value / total) * 100);
      return '<div class="d2-glegend">' +
        '<span class="d2-glegend-top"><span class="d2-gdot" style="background:' + s.color + '"></span><span class="d2-glabel">' + s.label + '</span></span>' +
        '<span class="d2-gpct">' + pct + '%</span>' +
      '</div>';
    }).join('');
  }

  function renderClinical() {
    var isDoc = D2_ROLE === 'doctor', isAdm = D2_ROLE === 'admin';
    var items, total;
    if (isAdm) {
      items = (state.payers || []).slice();
      total = items.reduce(function (a, d) { return a + Number(d.count || 0); }, 0);
    } else {
      items = ((state.data && (isDoc ? state.data.diagnoses : state.data.drugs)) || []).slice();
      total = Number((state.data && (isDoc ? state.data.diagnoses_total : state.data.drugs_total)) || 0);
    }
    var palette = isAdm ? ['#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#4f46e5', '#be185d'] : ['#0ea5e9', '#8b5cf6', '#f59e0b', '#22c55e'];
    var totalEl = el('d2-clinical-total');
    if (totalEl) totalEl.textContent = total;
    var segments = items.map(function (d, i) { return { value: Number(d.count || 0), color: palette[i % palette.length] }; });
    drawDonut(el('d2-clinical-donut'), segments, total, '#eef1f5');
    var legend = el('d2-clinical-legend');
    if (!legend) return;
    if (!items.length) {
      legend.innerHTML = '<div class="d2-nodata">' + (isAdm ? 'No visits in this range.' : isDoc ? 'No diagnoses recorded in this range.' : 'No drugs administered in this range.') + '</div>';
      return;
    }
    legend.innerHTML = items.map(function (d, i) {
      var pct = total ? Math.round((Number(d.count || 0) / total) * 100) : 0;
      return '<div class="d2-dlegend-row"' + (isAdm ? ' onclick="d2Payer(' + i + ')" style="cursor:pointer"' : '') + ' title="' + esc(d.full || d.label) + (isAdm ? ' - click for details' : '') + '">' +
        '<span class="d2-ddot" style="background:' + palette[i % palette.length] + '"></span>' +
        '<span class="d2-dname">' + esc(d.label) + '</span>' +
        '<span class="d2-dval">' + Number(d.count || 0) + '</span>' +
        '<span class="d2-dpct">' + pct + '%</span>' +
      '</div>';
    }).join('');
  }

  function renderEscalations() {
    var list = (state.data && state.data.escalations) || [];
    var counts = (state.data && state.data.counts) || {};
    var er = Number(counts.emergency || 0);
    var ad = Number(counts.hospitalization || 0);
    var countEl = el('d2-esc-count');
    if (countEl) countEl.textContent = er + ad;
    var wrap = el('d2-esc-list');
    if (!wrap) return;
    if (!list.length) {
      wrap.innerHTML = '<div class="d2-nodata d2-nodata--pad">No emergency cases or admissions in this range.</div>';
    } else {
      wrap.innerHTML = list.map(function (e) {
        var pal = avatarPalette[Math.abs(Number(e.patient_id) || 0) % avatarPalette.length];
        var badge = e.kind === 'emergency'
          ? '<span class="d2-esc-badge d2-esc-badge--er"><i></i>Emergency</span>'
          : '<span class="d2-esc-badge d2-esc-badge--admit"><i></i>Admitted</span>';
        var meta = [e.visit_no, e.time, e.kind === 'admitted' ? e.ward : ''].filter(Boolean).join(' · ');
        var href = e.visit_id ? 'consultation.php?visit_id=' + Number(e.visit_id) + '&amp;patient_id=' + Number(e.patient_id) : '#';
        return '<a class="d2-esc-row" href="' + href + '">' +
          '<span class="d2-avatar d2-avatar--sm" style="--a:' + pal[0] + ';--b:' + pal[1] + '">' + esc(initials(e.name)) + '</span>' +
          '<span class="d2-esc-meta"><span class="d2-esc-name">' + esc(e.name) + '</span><span class="d2-esc-sub">' + esc(meta) + '</span></span>' +
          badge +
        '</a>';
      }).join('');
    }
    var summary = el('d2-esc-summary');
    if (summary) summary.textContent = er + ' emergency · ' + ad + ' admission' + (ad === 1 ? '' : 's');
  }

  function renderAll() {
    renderCards();
    renderMeta();
    renderDeptOptions();
    renderTable();
    renderGender();
    renderClinical();
    renderEscalations();
  }

  window.d2Load = function () {
    var from = el('d2-from').value || fmtDate(new Date());
    var to = el('d2-to').value || from;
    if (to < from) { var tmp = from; from = to; to = tmp; el('d2-from').value = from; el('d2-to').value = to; }
    var sel = el('d2-dept');
    state.dept = sel ? (Number(sel.value) || 0) : state.dept;
    hideError();
    setLoading(true);
    var q = '&date_from=' + encodeURIComponent(from) + '&date_to=' + encodeURIComponent(to);
    var get = function (u) { return fetch(D2_API + u, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }); };
    // Admin extras (revenue, payer split) must not break the clinical data if they fail
    var extras = D2_ROLE !== 'admin' ? Promise.resolve([null, null]) : Promise.all([
      get('patients/billing&action=today_summary' + q).catch(function () { return null; }),
      get('reports/general' + q + '&limit=10000').catch(function () { return null; })
    ]);
    Promise.all([get('consultation/consult&action=dashboard_data' + q + '&department_id=' + state.dept), extras])
      .then(function (res) {
        var j = res[0], bill = res[1][0], rep = res[1][1];
        if (!j || !j.success) throw new Error((j && j.message) || 'Could not load the dashboard.');
        var bd = bill && bill.success && bill.data;
        state.money = bd ? { revenue: parseFloat(bd.total_revenue || bd.total_amount || 0), collected: parseFloat(bd.total_paid || 0) } : null;
        var by = (rep && rep.success && rep.data && rep.data.stats && rep.data.stats.by_payer) || [];
        var priv = 0;
        state.payers = [];
        by.forEach(function (p) {
          if (p.private) priv += Number(p.count || 0);
          else if (Number(p.count) > 0) state.payers.push({ label: p.name, full: p.name, count: Number(p.count) });
        });
        state.payers.sort(function (a, b) { return b.count - a.count; });
        // visits per payer for the click-through modal (same grouping as by_payer in the handler)
        state.payerRows = {};
        ((rep && rep.success && rep.data && rep.data.invoices) || []).forEach(function (inv) {
          var n = (inv.is_private || !inv.insurance_name) ? 'Private (Self-Pay)' : inv.insurance_name;
          (state.payerRows[n] = state.payerRows[n] || []).push(inv);
        });
        if (priv > 0) state.payers.unshift({ label: 'Private (Self-Pay)', full: 'Private (Self-Pay)', count: priv });
        state.data = j.data;
        state.patients = j.data.patients || [];
        state.page = 1;
        renderAll();
      })
      .catch(function (e) { showError(e.message || 'Could not load the dashboard.'); })
      .then(function () { setLoading(false); });
  };

  // Admin: click a payer in the legend to list its visits (Excel export in the modal)
  window.d2Payer = function (i) {
    var p = (state.payers || [])[i]; if (!p) return;
    var rows = (state.payerRows || {})[p.full] || [];
    var num = function (v) { return Number(Math.round(parseFloat(v || 0))).toLocaleString(); };
    var sum = function (k) { return rows.reduce(function (a, r) { return a + parseFloat(r[k] || 0); }, 0); };
    var th = function (t, a) { return '<th style="padding:8px 10px;text-align:' + (a || 'left') + ';">' + t + '</th>'; };
    var td = function (t, a, x) { return '<td style="padding:8px 10px;text-align:' + (a || 'left') + ';' + (x || '') + '">' + t + '</td>'; };
    el('pt-modal-title').textContent = 'Patient Type: ' + p.full;
    el('pt-modal-subtitle').textContent = rows.length + ' visit' + (rows.length !== 1 ? 's' : '') + ' · Billed ' + num(sum('total')) + ' · Paid ' + num(sum('paid_amount')) + ' · Balance ' + num(sum('balance')) + ' RWF';
    el('pt-modal-content').innerHTML = '<table id="pt-modal-table" style="width:100%;border-collapse:collapse;font-size:12px;"><thead><tr style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">' +
      th('#') + th('Date') + th('Patient') + th('Patient No') + th('Visit No') + th('Package') + th('Method') + th('Billed (RWF)', 'right') + th('Paid (RWF)', 'right') + th('Balance (RWF)', 'right') + '</tr></thead><tbody>' +
      rows.map(function (r, k) {
        return '<tr style="border-bottom:1px solid var(--gray-100);">' + td(k + 1) + td(esc(String(r.visit_date || '').slice(0, 10)), 'left', 'white-space:nowrap;') +
          td(esc(((r.patient_first || '') + ' ' + (r.patient_last || '')).trim() || '—'), 'left', 'font-weight:600;') + td(esc(r.patient_no || '')) + td(esc(r.visit_no || '')) + td(esc(r.package_name || '')) +
          td(esc(String(r.payment_method || '').replace(/_/g, ' '))) + td(num(r.total), 'right') + td(num(r.paid_amount), 'right', 'color:#065f46;') + td(num(r.balance), 'right', 'color:#b91c1c;') + '</tr>';
      }).join('') + '</tbody></table>';
    el('pt-modal').style.display = 'flex';
  };
  window.d2PayerExport = function () {
    var table = el('pt-modal-table'); if (!table) return;
    var rows = [].slice.call(table.querySelectorAll('tr')).map(function (tr) { return [].slice.call(tr.children).map(function (c) { return c.innerText.trim(); }); });
    var wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(rows), 'Patient Type');
    XLSX.writeFile(wb, 'PatientType_' + el('pt-modal-title').textContent.replace(/^Patient Type:\s*/, '').replace(/[^a-z0-9]/gi, '_') + '.xlsx');
  };

  window.d2Page = function (n) {
    state.page = Math.max(1, Number(n) || 1);
    renderTable();
  };

  document.querySelectorAll('[data-d2-range]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var range = btn.getAttribute('data-d2-range');
      var now = new Date();
      var from = fmtDate(now), to = fmtDate(now);
      if (range === 'week') {
        var d = new Date(now);
        var day = (d.getDay() + 6) % 7; // Monday first
        d.setDate(d.getDate() - day);
        from = fmtDate(d);
      } else if (range === 'month') {
        from = now.getFullYear() + '-' + pad2(now.getMonth() + 1) + '-01';
      }
      el('d2-from').value = from;
      el('d2-to').value = to;
      window.d2Load();
    });
  });

  var search = el('d2-search');
  if (search) {
    search.addEventListener('input', function () {
      state.page = 1;
      renderTable();
    });
  }

  window.d2Load();
})();
</script>
