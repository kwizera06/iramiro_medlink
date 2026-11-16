<?php
// includes/finance_close.php
// Year-end close and the Statement of Changes in Equity.
// Closing is a real journal entry (reference_type 'year_end_close', dated 31 Dec): income and expense accounts of the
// year are zeroed against Retained Earnings and the twelve months are locked. Profit reports (income statement,
// statement of changes in equity) leave that entry out; the balance sheet counts it, so equity is the same before and after.
require_once __DIR__ . '/finance_posting.php';

const FIN_CLOSE_TYPE = 'year_end_close';

// Net surplus (income - expenses) from the ledger. $from null = from the beginning. $withClosing false = leave the year-end entries out.
function fin_earnings(PDO $pdo, string $to, ?string $from = null, bool $withClosing = true): float {
    $sql = "SELECT COALESCE(SUM(jl.credit - jl.debit), 0) FROM journal_entry_lines jl
            JOIN journal_entries je ON je.id = jl.journal_entry_id JOIN chart_of_accounts c ON c.id = jl.account_id
            WHERE c.account_type IN ('income','expense') AND je.entry_date <= ?";
    $args = [$to];
    if ($from !== null) { $sql .= " AND je.entry_date >= ?"; $args[] = $from; }
    if (!$withClosing) $sql .= " AND (je.reference_type IS NULL OR je.reference_type <> '" . FIN_CLOSE_TYPE . "')";
    $q = $pdo->prepare($sql);
    $q->execute($args);
    return round((float)$q->fetchColumn(), 2);
}

// Years that have income / expense postings, with whether each has been closed
function fin_close_years(PDO $pdo): array {
    $rows = $pdo->query("SELECT YEAR(je.entry_date) AS y, SUM(je.reference_type = '" . FIN_CLOSE_TYPE . "') AS closed
                         FROM journal_entries je WHERE EXISTS (SELECT 1 FROM journal_entry_lines jl JOIN chart_of_accounts c ON c.id = jl.account_id
                                                               WHERE jl.journal_entry_id = je.id AND c.account_type IN ('income','expense'))
                         GROUP BY y ORDER BY y")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $y = (int)$r['y'];
        $out[$y] = ['year' => $y, 'closed' => (bool)$r['closed'], 'surplus' => fin_earnings($pdo, "$y-12-31", "$y-01-01", false)];
    }
    return $out;
}

// Closing lines for a year: every income / expense account with a balance goes to zero, the difference lands in Retained Earnings
function fin_year_end_plan(PDO $pdo, int $year): array {
    $q = $pdo->prepare("SELECT jl.account_id, ROUND(SUM(jl.debit - jl.credit), 2) AS n FROM journal_entry_lines jl
                        JOIN journal_entries je ON je.id = jl.journal_entry_id JOIN chart_of_accounts c ON c.id = jl.account_id
                        WHERE c.account_type IN ('income','expense') AND je.entry_date BETWEEN ? AND ?
                          AND (je.reference_type IS NULL OR je.reference_type <> '" . FIN_CLOSE_TYPE . "')
                        GROUP BY jl.account_id HAVING ABS(n) >= 0.005");
    $q->execute(["$year-01-01", "$year-12-31"]);
    $lines = []; $net = 0.0;
    foreach ($q->fetchAll() as $r) {
        $n = (float)$r['n'];
        $lines[] = ['account_id' => (int)$r['account_id'], 'debit' => $n < 0 ? -$n : 0, 'credit' => $n > 0 ? $n : 0, 'desc' => "Close $year to retained earnings"];
        $net += $n;
    }
    $net = round($net, 2); // net debit of P&L > 0 = deficit (retained is debited); surplus = -net
    if ($lines) $lines[] = ['account_id' => fin_acct($pdo, 'retained'), 'debit' => $net > 0 ? $net : 0, 'credit' => $net < 0 ? -$net : 0, 'desc' => "Surplus / (deficit) $year"];
    return ['lines' => $lines, 'surplus' => -$net];
}

function fin_year_end_close(PDO $pdo, int $year): array {
    if ($year < 2000 || $year >= (int)date('Y')) throw new RuntimeException('Only a finished year can be closed');
    $years = fin_close_years($pdo);
    if (!isset($years[$year])) throw new RuntimeException("No income or expense was posted in $year");
    if (!empty($years[$year]['closed'])) throw new RuntimeException("$year is already closed");
    foreach ($years as $y => $info) if ($y < $year && !$info['closed']) throw new RuntimeException("Close $y first");
    $plan = fin_year_end_plan($pdo, $year);
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $je = $plan['lines'] ? fin_post($pdo, ['date' => "$year-12-31", 'type' => FIN_CLOSE_TYPE, 'ref_id' => $year, 'lines' => $plan['lines'], 'description' => "Year-end close $year"]) : null;
        $uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $up = $pdo->prepare("INSERT INTO finance_periods (period_ym, status, note, changed_by, changed_at) VALUES (?, 'closed', ?, ?, NOW())
                             ON DUPLICATE KEY UPDATE status = 'closed', note = VALUES(note), changed_by = VALUES(changed_by), changed_at = NOW()");
        for ($m = 1; $m <= 12; $m++) $up->execute([sprintf('%d-%02d', $year, $m), "Year-end close $year", $uid]);
        if ($own) $pdo->commit();
        return ['year' => $year, 'journal_entry_id' => $je, 'surplus' => $plan['surplus']];
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Undo a year-end close: the year's months open again and the closing entry is removed
function fin_year_end_reopen(PDO $pdo, int $year): void {
    $years = fin_close_years($pdo);
    if (empty($years[$year]['closed'])) throw new RuntimeException("$year is not closed");
    foreach ($years as $y => $info) if ($y > $year && $info['closed']) throw new RuntimeException("Reopen $y first");
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $pdo->prepare("UPDATE finance_periods SET status = 'open', note = ?, changed_by = ?, changed_at = NOW() WHERE period_ym LIKE ?")
            ->execute(["Year-end close $year undone", $uid, "$year-%"]);
        fin_unpost($pdo, FIN_CLOSE_TYPE, $year, "Year-end close $year undone");
        if ($own) $pdo->commit();
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

// Statement of Changes in Equity for from..to.
// Columns per row: opening, surplus for the period, other movements, closing. Retained Earnings carries the surplus.
function fin_equity_statement(PDO $pdo, string $from, string $to): array {
    $retained = fin_acct($pdo, 'retained');
    $before = date('Y-m-d', strtotime("$from -1 day"));
    $q = $pdo->prepare("SELECT c.id, c.account_code, c.account_name,
            COALESCE((SELECT SUM(jl.credit - jl.debit) FROM journal_entry_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id
                      WHERE jl.account_id = c.id AND je.entry_date <= ?), 0) AS opening,
            COALESCE((SELECT SUM(jl.credit - jl.debit) FROM journal_entry_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id
                      WHERE jl.account_id = c.id AND je.entry_date BETWEEN ? AND ? AND (je.reference_type IS NULL OR je.reference_type <> '" . FIN_CLOSE_TYPE . "')), 0) AS other
            FROM chart_of_accounts c WHERE c.account_type = 'equity' AND c.status = 'active' ORDER BY c.account_code");
    $q->execute([$before, $from, $to]);
    $rows = []; $surplus = fin_earnings($pdo, $to, $from, false);
    $hasRetained = false;
    foreach ($q->fetchAll() as $r) {
        $isRet = (int)$r['id'] === $retained;
        $open = round((float)$r['opening'], 2); $other = round((float)$r['other'], 2);
        if ($isRet) { $hasRetained = true; $open = round($open + fin_earnings($pdo, $before), 2); }
        $sur = $isRet ? $surplus : 0.0;
        if (!$isRet && abs($open) < 0.005 && abs($other) < 0.005) continue;
        $rows[] = ['account_code' => $r['account_code'], 'account_name' => $isRet ? 'Retained earnings / accumulated surplus' : $r['account_name'],
                   'opening' => $open, 'surplus' => round($sur, 2), 'other' => $other, 'closing' => round($open + $sur + $other, 2)];
    }
    if (!$hasRetained) throw new RuntimeException('Retained Earnings account is not in the chart of accounts');
    $tot = ['opening' => 0.0, 'surplus' => 0.0, 'other' => 0.0, 'closing' => 0.0];
    foreach ($rows as $r) foreach ($tot as $k => $_) $tot[$k] = round($tot[$k] + $r[$k], 2);
    // Equity on the balance sheet at $to must equal the closing line
    $b = $pdo->prepare("SELECT COALESCE(SUM(jl.credit - jl.debit), 0) FROM journal_entry_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id
                        JOIN chart_of_accounts c ON c.id = jl.account_id WHERE c.account_type = 'equity' AND je.entry_date <= ?");
    $b->execute([$to]);
    $bs = round((float)$b->fetchColumn() + fin_earnings($pdo, $to), 2);
    return ['from' => $from, 'to' => $to, 'rows' => $rows, 'totals' => $tot, 'balance_sheet_equity' => $bs, 'difference' => round($tot['closing'] - $bs, 2)];
}
