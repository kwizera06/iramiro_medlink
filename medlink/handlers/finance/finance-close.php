<?php
// handlers/finance/finance-close.php
// Year-end close and the Statement of Changes in Equity (logic in includes/finance_close.php).
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/finance_close.php';

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

function dateOf($v, string $label): string {
    $d = substr((string)$v, 0, 10);
    $t = DateTime::createFromFormat('Y-m-d', $d);
    if (!$t || $t->format('Y-m-d') !== $d) error("$label is not a valid date", 422);
    return $d;
}

try {
    if ($method === 'GET' && $action === 'equity_statement') {
        $to = dateOf($_GET['to'] ?? date('Y-m-d'), 'To date');
        $from = dateOf($_GET['from'] ?? substr($to, 0, 4) . '-01-01', 'From date');
        if ($from > $to) error('From date is after To date', 422);
        success(fin_equity_statement($pdo, $from, $to));
    }

    if ($method === 'GET' && $action === 'year_end_status') {
        $years = array_values(fin_close_years($pdo));
        foreach ($years as &$y) $y['can_close'] = false;
        unset($y);
        $firstOpen = null;
        foreach ($years as &$y) {
            if ($y['closed']) continue;
            $y['finished'] = $y['year'] < (int)date('Y');
            if ($firstOpen === null) { $firstOpen = $y['year']; $y['can_close'] = $y['finished']; }
        }
        unset($y);
        $lastClosed = null;
        foreach ($years as $y) if ($y['closed']) $lastClosed = $y['year'];
        foreach ($years as &$y) $y['can_reopen'] = $y['closed'] && $y['year'] === $lastClosed;
        success(['years' => $years, 'current_year' => (int)date('Y')]);
    }

    if ($method === 'POST' && in_array($action, ['year_end_close', 'year_end_reopen'], true)) {
        if (!array_intersect($_SESSION['user_data']['role_slugs'] ?? [], ['admin', 'accountant'])) error('Only administrators and accountants can do this', 403);
        $year = (int)($input['year'] ?? 0);
        try {
            if ($action === 'year_end_close') {
                $r = fin_year_end_close($pdo, $year);
                fin_log($pdo, 'UPDATE', 'finance', "Year-end close $year: surplus " . number_format($r['surplus'], 2), $year);
                success($r, "Year $year closed");
            }
            fin_year_end_reopen($pdo, $year);
            fin_log($pdo, 'UPDATE', 'finance', "Year-end close $year undone", $year);
            success(null, "Year $year reopened");
        } catch (RuntimeException $e) { error($e->getMessage(), 409); }
    }

    error('Unknown action', 404);
} catch (Throwable $e) {
    error_log('finance-close: ' . $e->getMessage());
    error('Server error', 500);
}
