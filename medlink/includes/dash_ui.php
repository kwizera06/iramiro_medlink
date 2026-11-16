<?php
// includes/dash_ui.php — shared parts for the role dashboards (includes/dash/*.php).
// Everything is rendered on the server: no JS libraries, no extra requests, so a dashboard is one page load.
// Every function RETURNS html; the dashboard file echoes it. Cells and labels are escaped here, callers pass raw text.
//
//   [$from, $to] = dx_range('month');                       // ?from=&to= (validated), default today | week | month
//   echo dx_head('Title', 'Subtitle', [$from, $to]);        // heading + date filter (pass null for no filter)
//   echo dx_attention([['5 invoices unpaid', 'finance.php', 5], ...]);   // only rows with count > 0 are shown
//   echo dx_cards([['Label', '1,200 RWF', 'fa-coins', '#b45309', 'footnote', 'page.php'], ...]);
//   echo dx_grid([dx_table(...), dx_cols(...)], 2);         // panels side by side (1-3 columns)
//   dx_table($title, $headers, $rows, $empty, $href)  dx_bars($title, [label=>value], $fmt, $color)
//   dx_cols($title, $labels, [series=>[values]], ['type'=>'bar'|'line', 'fmt'=>callable])   dx_donut($title, [label=>value])
//   dx_rows($pdo, $sql, $params) / dx_val(...)  — query helpers: a failing query logs and returns [] / 0, never a fatal page.

if (!function_exists('dx_h')) {

function dx_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dx_num($n): string { return number_format((float)$n); }
function dx_money($n): string { return number_format((float)$n) . ' RWF'; }

function dx_rows(PDO $pdo, string $sql, array $p = []): array {
    try { $q = $pdo->prepare($sql); $q->execute($p); return $q->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { error_log('dashboard query failed: ' . $e->getMessage() . ' | ' . substr($sql, 0, 120)); return []; }
}
function dx_val(PDO $pdo, string $sql, array $p = []) {
    $r = dx_rows($pdo, $sql, $p);
    if (!$r) return 0;
    $v = array_values($r[0])[0];
    return $v === null ? 0 : $v;
}

/** [from, to] as Y-m-d from ?from=&to= (invalid or missing -> the default range). $def: 'today' | 'week' | 'month'. */
function dx_range(string $def = 'today'): array {
    $ok = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
    $today = date('Y-m-d');
    $from = $def === 'month' ? date('Y-m-01') : ($def === 'week' ? date('Y-m-d', strtotime('monday this week')) : $today);
    $to = $today;
    if ($ok($_GET['from'] ?? null) && $ok($_GET['to'] ?? null) && $_GET['from'] <= $_GET['to']) { $from = $_GET['from']; $to = $_GET['to']; }
    return [$from, $to];
}
/** Every day from..to (Y-m-d), capped at 92 days (the last 92 when the range is longer). */
function dx_days(string $from, string $to): array {
    $out = [];
    for ($t = strtotime($from), $end = strtotime($to); $t <= $end; $t = strtotime('+1 day', $t)) $out[] = date('Y-m-d', $t);
    return array_slice($out, -92);
}
/** Fill a [date=>value] map over every day of the range (missing days = 0). Returns [labels(d/m), values]. */
function dx_fill_days(array $byDate, string $from, string $to): array {
    $labels = $vals = [];
    foreach (dx_days($from, $to) as $d) { $labels[] = date('d/m', strtotime($d)); $vals[] = (float)($byDate[$d] ?? 0); }
    return [$labels, $vals];
}

function dx_styles(): string {
    static $done = false; if ($done) return ''; $done = true;
    return '<style>
.dx{font-size:13px;color:#111827}.dx *{box-sizing:border-box}
.dx-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:16px}
.dx-title{margin:0;font-size:24px;font-weight:650;letter-spacing:-.02em}.dx-sub{margin:4px 0 0;color:#6b7280;font-size:12.5px}
.dx-range{display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap}.dx-range label{display:flex;flex-direction:column;gap:3px;font-size:9.5px;font-weight:650;letter-spacing:.07em;text-transform:uppercase;color:#9aa2af}
.dx-range input{padding:6px 8px;border:1px solid #dfe3ea;border-radius:7px;font-size:12px}
.dx-btn{padding:7px 13px;border-radius:7px;border:1px solid #2563eb;background:#2563eb;color:#fff;font-size:12px;font-weight:600;cursor:pointer}
.dx-pre{display:flex;gap:2px;padding:2px;border:1px solid #e9ecf1;border-radius:7px;background:#f8fafc}.dx-pre a{padding:5px 9px;border-radius:5px;font-size:11px;font-weight:600;color:#6b7280;text-decoration:none}.dx-pre a:hover{background:#fff;color:#111827}
.dx-attn{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px}.dx-attn a{display:inline-flex;gap:7px;align-items:center;padding:6px 12px;border-radius:999px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:12px;font-weight:600;text-decoration:none}
.dx-attn b{background:#ea580c;color:#fff;border-radius:999px;padding:0 7px;font-size:11px}
.dx-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:16px}
.dx-card{display:block;background:#fff;border:1px solid #e9ecf1;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 4px 14px -8px rgba(15,23,42,.10);text-decoration:none;color:inherit;border-left:3px solid var(--c)}
a.dx-card:hover{box-shadow:0 8px 20px -12px rgba(15,23,42,.3)}
.dx-card-top{display:flex;justify-content:space-between;align-items:center;gap:8px}.dx-card-label{font-size:10px;font-weight:650;letter-spacing:.07em;text-transform:uppercase;color:#6b7280}
.dx-card-ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--bg);color:var(--c);font-size:13px}
.dx-card-val{font-size:24px;font-weight:700;margin-top:6px;letter-spacing:-.02em;font-variant-numeric:tabular-nums}.dx-card-foot{font-size:11px;color:#9aa2af;margin-top:2px}
.dx-grid{display:grid;gap:14px;margin-bottom:16px}.dx-grid.c1{grid-template-columns:1fr}.dx-grid.c2{grid-template-columns:repeat(2,minmax(0,1fr))}.dx-grid.c3{grid-template-columns:repeat(3,minmax(0,1fr))}
@media(max-width:900px){.dx-grid.c2,.dx-grid.c3{grid-template-columns:1fr}}
.dx-panel{background:#fff;border:1px solid #e9ecf1;border-radius:14px;padding:14px 16px;min-width:0;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.dx-ph{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.dx-ph h3{margin:0;font-size:13.5px;font-weight:650}.dx-ph a{font-size:11.5px;color:#2563eb;text-decoration:none;font-weight:600}
.dx-tw{overflow-x:auto}.dx table{width:100%;border-collapse:collapse}.dx th{text-align:left;font-size:10px;font-weight:650;letter-spacing:.06em;text-transform:uppercase;color:#9aa2af;padding:6px 8px;border-bottom:1px solid #e9ecf1;white-space:nowrap}
.dx td{padding:7px 8px;border-bottom:1px solid #f1f3f6;font-size:12px;vertical-align:top}.dx tr:last-child td{border-bottom:0}.dx td.r,.dx th.r{text-align:right}
.dx-empty{padding:22px 8px;text-align:center;color:#9aa2af;font-size:12px}
.dx-badge{display:inline-block;padding:1px 9px;border-radius:999px;font-size:11px;font-weight:600;color:var(--c);background:var(--bg)}
.dx-bar{display:grid;grid-template-columns:minmax(80px,32%) 1fr auto;gap:8px;align-items:center;margin:7px 0;font-size:12px}.dx-bar span:first-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dx-track{height:9px;background:#f1f3f6;border-radius:5px;overflow:hidden}.dx-track i{display:block;height:100%;border-radius:5px;background:var(--c)}.dx-bar b{font-variant-numeric:tabular-nums;font-size:11.5px}
.dx-legend{display:flex;gap:12px;flex-wrap:wrap;font-size:11px;color:#6b7280;margin-bottom:4px}.dx-legend i{display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:5px}
.dx-svg{width:100%;height:auto;display:block}.dx-donut{display:flex;gap:18px;align-items:center;flex-wrap:wrap}.dx-donut svg{width:130px;height:130px;flex:none}.dx-donut ul{list-style:none;margin:0;padding:0;font-size:12px;flex:1;min-width:130px}.dx-donut li{display:flex;gap:7px;align-items:center;margin:4px 0}.dx-donut li i{width:9px;height:9px;border-radius:2px;flex:none}.dx-donut li b{margin-left:auto;font-variant-numeric:tabular-nums}
</style>';
}

function dx_palette(): array { return ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#dc2626', '#65a30d', '#475569', '#ea580c']; }

function dx_head(string $title, string $sub = '', ?array $range = null): string {
    $h = dx_styles() . '<div class="dx"><div class="dx-head"><div><h2 class="dx-title">' . dx_h($title) . '</h2>' . ($sub !== '' ? '<p class="dx-sub">' . dx_h($sub) . '</p>' : '') . '</div>';
    if ($range) {
        $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $mk = fn($f, $t) => dx_h($path . '?from=' . $f . '&to=' . $t);
        $today = date('Y-m-d');
        $h .= '<form class="dx-range" method="get"><label>From<input type="date" name="from" value="' . dx_h($range[0]) . '"></label><label>To<input type="date" name="to" value="' . dx_h($range[1]) . '"></label>'
            . '<button class="dx-btn" type="submit">Apply</button><div class="dx-pre"><a href="' . $mk($today, $today) . '">Today</a><a href="' . $mk(date('Y-m-d', strtotime('monday this week')), $today) . '">Week</a><a href="' . $mk(date('Y-m-01'), $today) . '">Month</a></div></form>';
    }
    return $h . '</div>';   // the wrapper div is closed by dx_end()
}
function dx_end(): string { return '</div>'; }

/** Items needing attention: [[text, href, count], ...]; only count > 0 is shown. */
function dx_attention(array $items): string {
    $out = '';
    foreach ($items as [$text, $href, $n]) if ((float)$n > 0) $out .= '<a href="' . dx_h($href) . '"><b>' . dx_num($n) . '</b>' . dx_h($text) . '</a>';
    return $out === '' ? '' : '<div class="dx-attn">' . $out . '</div>';
}

/** Cards: [label, value(string), icon(fa-*), color, foot, href]. */
function dx_cards(array $cards): string {
    $out = '<div class="dx-cards">';
    foreach ($cards as $c) {
        [$label, $val, $icon, $color] = $c; $foot = $c[4] ?? ''; $href = $c[5] ?? '';
        $tag = $href !== '' ? 'a href="' . dx_h($href) . '"' : 'div';
        $out .= '<' . $tag . ' class="dx-card" style="--c:' . dx_h($color) . ';--bg:' . dx_h($color) . '1a"><div class="dx-card-top"><span class="dx-card-label">' . dx_h($label) . '</span><span class="dx-card-ico"><i class="fas ' . dx_h($icon) . '"></i></span></div>'
            . '<div class="dx-card-val">' . dx_h($val) . '</div>' . ($foot !== '' ? '<div class="dx-card-foot">' . dx_h($foot) . '</div>' : '') . '</' . ($href !== '' ? 'a' : 'div') . '>';
    }
    return $out . '</div>';
}

function dx_grid(array $panels, int $cols = 2): string { return '<div class="dx-grid c' . max(1, min(3, $cols)) . '">' . implode('', $panels) . '</div>'; }
function dx_panel(string $title, string $body, ?string $href = null, string $linkText = 'View all'): string {
    return '<div class="dx-panel"><div class="dx-ph"><h3>' . dx_h($title) . '</h3>' . ($href ? '<a href="' . dx_h($href) . '">' . dx_h($linkText) . ' →</a>' : '') . '</div>' . $body . '</div>';
}
/** A coloured pill for a table cell: use as ['html' => dx_badge('Overdue', '#dc2626')]. */
function dx_badge(string $text, string $color = '#475569'): string { return '<span class="dx-badge" style="--c:' . dx_h($color) . ';--bg:' . dx_h($color) . '1a">' . dx_h($text) . '</span>'; }

/** Table. $rows: list of lists; a cell is plain text (escaped) or ['html' => '...'] (trusted). Prefix a header with '>' to right-align that column. */
function dx_table(string $title, array $headers, array $rows, string $empty = 'Nothing to show', ?string $href = null): string {
    if (!$rows) return dx_panel($title, '<div class="dx-empty">' . dx_h($empty) . '</div>', $href);
    $right = array_map(fn($h) => str_starts_with($h, '>'), $headers);
    $t = '<div class="dx-tw"><table><thead><tr>';
    foreach ($headers as $i => $h) $t .= '<th' . ($right[$i] ? ' class="r"' : '') . '>' . dx_h(ltrim($h, '>')) . '</th>';
    $t .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $t .= '<tr>';
        foreach (array_values($r) as $i => $c) $t .= '<td' . (($right[$i] ?? false) ? ' class="r"' : '') . '>' . (is_array($c) ? ($c['html'] ?? '') : dx_h($c)) . '</td>';
        $t .= '</tr>';
    }
    return dx_panel($title, $t . '</tbody></table></div>', $href);
}

/** Horizontal bars: [label => value]. $fmt formats the number at the end of each bar. */
function dx_bars(string $title, array $items, ?callable $fmt = null, string $color = '#2563eb', ?string $href = null): string {
    $items = array_filter($items, fn($v) => (float)$v > 0);
    if (!$items) return dx_panel($title, '<div class="dx-empty">No data in this period</div>', $href);
    arsort($items); $max = max($items); $fmt = $fmt ?? 'dx_num'; $b = '';
    foreach ($items as $l => $v) $b .= '<div class="dx-bar"><span title="' . dx_h($l) . '">' . dx_h($l) . '</span><div class="dx-track"><i style="width:' . max(2, round($v / $max * 100)) . '%;--c:' . dx_h($color) . '"></i></div><b>' . dx_h($fmt($v)) . '</b></div>';
    return dx_panel($title, $b, $href);
}

/** Vertical bars or lines over $labels. $series: [name => [values aligned with labels]]. opts: type bar|line, fmt, colors. */
function dx_cols(string $title, array $labels, array $series, array $opts = []): string {
    $n = count($labels);
    $all = []; foreach ($series as $v) foreach ($v as $x) $all[] = (float)$x;
    if (!$n || !$all || max($all) <= 0) return dx_panel($title, '<div class="dx-empty">No data in this period</div>', $opts['href'] ?? null);
    $type = $opts['type'] ?? 'bar'; $fmt = $opts['fmt'] ?? 'dx_num'; $pal = $opts['colors'] ?? dx_palette();
    $W = 600; $H = 190; $L = 6; $B = 22; $T = 10; $ph = $H - $B - $T; $pw = $W - $L - 2;
    $max = max($all); $step = pow(10, floor(log10($max))); $top = ceil($max / $step) * $step; if ($top < $max * 1.02) $top += $step;
    $svg = '<svg class="dx-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . dx_h($title) . '">';
    for ($i = 0; $i <= 4; $i++) { $y = $T + $ph - $ph * $i / 4; $svg .= '<line x1="' . $L . '" x2="' . $W . '" y1="' . $y . '" y2="' . $y . '" stroke="#eef0f4"/><text x="' . ($L + 2) . '" y="' . ($y - 3) . '" font-size="9" fill="#9aa2af">' . dx_h($fmt($top * $i / 4)) . '</text>'; }
    $slot = $pw / $n; $k = count($series); $every = max(1, (int)ceil($n / 12)); $si = 0;
    foreach ($series as $name => $vals) {
        $col = $pal[$si % count($pal)]; $pts = [];
        foreach (array_values($vals) as $i => $v) {
            $h = $top > 0 ? $ph * (float)$v / $top : 0;
            $x = round($L + $slot * ($i + .5), 1); $y = round($T + $ph - $h, 1);
            if ($type === 'line') {
                $pts[] = $x . ',' . $y;
                $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="2.5" fill="' . $col . '"><title>' . dx_h($labels[$i] . ' · ' . $name . ': ' . $fmt($v)) . '</title></circle>';
            } else { $bw = max(2, $slot * .7 / $k); $x = $L + $slot * $i + $slot * .15 + $bw * $si; $svg .= '<rect x="' . round($x, 1) . '" y="' . round($T + $ph - $h, 1) . '" width="' . round($bw, 1) . '" height="' . round($h, 1) . '" rx="2" fill="' . $col . '"><title>' . dx_h($labels[$i] . ' · ' . $name . ': ' . $fmt($v)) . '</title></rect>'; }
        }
        if ($type === 'line') $svg .= '<polyline fill="none" stroke="' . $col . '" stroke-width="2" stroke-linejoin="round" points="' . implode(' ', $pts) . '"/>';
        $si++;
    }
    for ($i = 0; $i < $n; $i += $every) $svg .= '<text x="' . round($L + $slot * ($i + .5), 1) . '" y="' . ($H - 6) . '" font-size="9" fill="#9aa2af" text-anchor="middle">' . dx_h($labels[$i]) . '</text>';
    $leg = ''; $si = 0; foreach ($series as $name => $_) { $leg .= '<span><i style="background:' . $pal[$si % count($pal)] . '"></i>' . dx_h($name) . '</span>'; $si++; }
    return dx_panel($title, ($k > 1 ? '<div class="dx-legend">' . $leg . '</div>' : '') . $svg . '</svg>', $opts['href'] ?? null);
}

/** Donut: [label => value]. */
function dx_donut(string $title, array $segs, ?callable $fmt = null, ?string $href = null): string {
    $segs = array_filter($segs, fn($v) => (float)$v > 0);
    $tot = array_sum($segs);
    if ($tot <= 0) return dx_panel($title, '<div class="dx-empty">No data in this period</div>', $href);
    $fmt = $fmt ?? 'dx_num'; $pal = dx_palette(); $r = 48; $c = 2 * M_PI * $r; $off = 0; $i = 0; $arcs = ''; $li = '';
    foreach ($segs as $l => $v) {
        $len = $c * $v / $tot; $col = $pal[$i % count($pal)];
        $arcs .= '<circle cx="60" cy="60" r="' . $r . '" fill="none" stroke="' . $col . '" stroke-width="16" stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '" transform="rotate(-90 60 60)"><title>' . dx_h($l . ': ' . $fmt($v)) . '</title></circle>';
        $li .= '<li><i style="background:' . $col . '"></i>' . dx_h($l) . '<b>' . dx_h($fmt($v)) . '</b></li>'; $off += $len; $i++;
    }
    return dx_panel($title, '<div class="dx-donut"><svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="' . $r . '" fill="none" stroke="#f1f3f6" stroke-width="16"/>' . $arcs . '<text x="60" y="64" text-anchor="middle" font-size="14" font-weight="700" fill="#111827">' . dx_h($fmt($tot)) . '</text></svg><ul>' . $li . '</ul></div>', $href);
}

}
