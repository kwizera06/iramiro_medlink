<?php
// handlers/assets/asset-ledger.php
// Asset register actions of finance/chart-of-accounts (action=assets*), ported from Michel's chart-of-accounts.php.
// Included by handlers/finance/chart-of-accounts.php for every action that starts with "assets". Registering, depreciating,
// capitalizing and revaluing now post their own journal entries on the server (the page no longer posts them).
require_once __DIR__ . '/../../includes/asset_schema.php';
am_boot($pdo);

// An account the user names for a posting must be a real cash / bank / mobile-money account
function asset_cash_account(PDO $pdo, $id): int {
    $id = (int)$id;
    foreach (fin_cash_accounts($pdo) as $a) if ((int)$a['id'] === $id) return $id;
    error('Choose a cash, bank or mobile-money account', 422);
}
function asset_account_of_type(PDO $pdo, int $id, string $type, string $label): int {
    $q = $pdo->prepare("SELECT account_type FROM chart_of_accounts WHERE id = ?"); $q->execute([$id]);
    if ($q->fetchColumn() !== $type) error("$label must be an $type account", 422);
    return $id;
}
function asset_date($v, string $label = 'Date'): string {
    $d = substr((string)($v ?: date('Y-m-d')), 0, 10);
    $t = DateTime::createFromFormat('Y-m-d', $d);
    if (!$t || $t->format('Y-m-d') !== $d) error("$label is not a valid date", 422);
    if ($d > date('Y-m-d')) error("$label cannot be in the future", 422);
    return $d;
}

$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'assets') {
        $stmt = $pdo->query("SELECT a.*, c.name AS category_name FROM assets a LEFT JOIN asset_categories c ON c.id = a.category_id ORDER BY a.asset_code, a.name");
        success($stmt->fetchAll());
    }
    if ($action === 'assets_categories') {
        $stmt = $pdo->query("SELECT * FROM asset_categories WHERE status='active' ORDER BY name");
        success($stmt->fetchAll());
    }
    if ($action === 'assets_revaluations') {
        $stmt = $pdo->query("SELECT r.*, a.name AS asset_name, a.asset_code FROM asset_revaluations r JOIN assets a ON a.id=r.asset_id ORDER BY r.revaluation_date DESC LIMIT 50");
        success($stmt->fetchAll());
    }
    // Equipment received on a GRN that is still in stock and not yet on the register (one row per delivery line, with the units left)
    if ($action === 'assets_grn_lines') {
        $rows = $pdo->query("SELECT gi.id, gi.item_id, gi.unit_cost, g.grn_number, g.received_date, g.store_id, it.name AS item_name, s.company_name AS supplier,
                    FLOOR(gi.quantity_accepted) - (SELECT COUNT(*) FROM assets x WHERE x.grn_item_id = gi.id) AS units_left,
                    COALESCE((SELECT SUM(b.quantity) FROM stock_movements m JOIN stock_batches b ON b.id = m.batch_id
                              WHERE m.reference_type = 'grn' AND m.reference_id = g.id AND m.item_id = gi.item_id), 0) AS in_stock
                FROM goods_receiving_items gi JOIN goods_receiving_notes g ON g.id = gi.grn_id JOIN items it ON it.id = gi.item_id
                JOIN item_categories ic ON ic.id = it.category_id LEFT JOIN suppliers s ON s.id = g.supplier_id
                WHERE " . (empty($_GET['all']) ? "ic.name IN ('Medical Equipment', 'IT Equipment', 'Furniture') AND " : '') . "gi.quantity_accepted >= 1 AND g.received_date >= CURDATE() - INTERVAL 2 YEAR ORDER BY g.received_date DESC, gi.id DESC LIMIT 300")->fetchAll();
        foreach ($rows as &$r) $r['units_left'] = (int)min($r['units_left'], floor((float)$r['in_stock']));
        unset($r);
        success(array_values(array_filter($rows, fn($r) => $r['units_left'] > 0)));
    }
    if ($action === 'assets_capitalizations') {
        $stmt = $pdo->query("SELECT c.*, a.name AS asset_name, a.asset_code FROM asset_capitalizations c JOIN assets a ON a.id=c.asset_id ORDER BY c.capitalization_date DESC LIMIT 50");
        success($stmt->fetchAll());
    }

    // Full tree (flat list ordered for tree display)
}

// POST — Assets
if ($method === 'POST' && $action === 'assets') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '') error('Name required', 422);
    $cost = round((float)($input['purchase_cost'] ?? 0), 2);
    if ($cost < 0) error('Purchase cost cannot be negative', 422);
    $date = asset_date($input['purchase_date'] ?? null, 'Purchase date');
    $catId = (int)($input['category_id'] ?? 0);
    $cat = $pdo->prepare("SELECT COALESCE(c.account_id, (SELECT x.id FROM chart_of_accounts x WHERE x.account_code = CASE c.name WHEN 'Land' THEN '1211' WHEN 'Buildings' THEN '1212' WHEN 'Furniture & Fixtures' THEN '1213' WHEN 'Electronic Equipment' THEN '1217' WHEN 'Computers & IT' THEN '1217' WHEN 'Intangible Assets' THEN '1218' WHEN 'Plant & Machinery' THEN '1218' WHEN 'Motor Vehicles' THEN '1218' WHEN 'Other Equipment' THEN '1218' END)) AS account_id FROM asset_categories c WHERE c.id = ? AND c.status = 'active'"); $cat->execute([$catId]);
    $catRow = $cat->fetch();
    if (!$catRow) error('Choose a valid asset category', 422);
    $accountId = (int)($input['account_id'] ?? 0) ?: (int)$catRow['account_id'];
    if ($cost > 0 && !$accountId) error('Choose the asset account for this category', 422);
    if ($accountId) asset_account_of_type($pdo, $accountId, 'asset', 'Asset account');
    $credit = 0;
    $grnItemId = (int)($input['grn_item_id'] ?? 0);
    $grnUnit = null;
    if ($grnItemId) {
        // Equipment that came in on a goods receipt is already in inventory and on the supplier's bill: registering it moves one unit out of
        // stock into the asset account (Dr asset, Cr the store) instead of buying it a second time against payables.
        $pdo->beginTransaction();
        $g = $pdo->prepare("SELECT gi.id, gi.item_id, gi.unit_cost, gi.quantity_accepted, g.id AS grn_id, g.grn_number, g.store_id FROM goods_receiving_items gi
                            JOIN goods_receiving_notes g ON g.id = gi.grn_id WHERE gi.id = ? FOR UPDATE");
        $g->execute([$grnItemId]);
        $grnUnit = $g->fetch();
        if (!$grnUnit) { $pdo->rollBack(); error('That delivery line was not found', 404); }
        if (!$accountId) { $pdo->rollBack(); error('Choose the asset account for this category', 422); }
        $input['opening'] = false;
        $reg = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE grn_item_id = ?"); $reg->execute([$grnItemId]);
        if ((int)$reg->fetchColumn() >= floor((float)$grnUnit['quantity_accepted'])) { $pdo->rollBack(); error('Every unit on this delivery line is already on the register', 409); }
        $b = $pdo->prepare("SELECT b.id, b.store_id FROM stock_movements m JOIN stock_batches b ON b.id = m.batch_id
                            WHERE m.reference_type = 'grn' AND m.reference_id = ? AND m.item_id = ? AND b.quantity >= 1 ORDER BY b.id LIMIT 1 FOR UPDATE");
        $b->execute([$grnUnit['grn_id'], $grnUnit['item_id']]);
        $batch = $b->fetch();
        if (!$batch) { $pdo->rollBack(); error('No unit from this delivery is left in stock (returned or issued already)', 409); }
        $cost = round((float)$grnUnit['unit_cost'], 2);   // the cost is what the delivery was booked at, not what is typed
        $credit = am_store_acct($pdo, (int)$batch['store_id']);
    } elseif ($cost > 0) {
        // Bought directly, but a delivery of the same item at the same price is still waiting on the shelf: that is almost certainly this asset
        // being counted twice (once in stock, once on the register). The user must pick the delivery, or say it is a different purchase.
        if (empty($input['opening']) && empty($input['not_delivery'])) {
            $dup = $pdo->prepare("SELECT g.grn_number, gi.id FROM goods_receiving_items gi JOIN goods_receiving_notes g ON g.id = gi.grn_id JOIN items it ON it.id = gi.item_id
                                  WHERE LOWER(it.name) = LOWER(?) AND ABS(gi.unit_cost - ?) < 0.005 AND gi.quantity_accepted >= 1 AND g.received_date >= CURDATE() - INTERVAL 1 YEAR
                                    AND FLOOR(gi.quantity_accepted) > (SELECT COUNT(*) FROM assets x WHERE x.grn_item_id = gi.id) LIMIT 1");
            $dup->execute([$name, $cost]);
            if ($d = $dup->fetch()) error("This looks like the delivery {$d['grn_number']} that is still in stock: choose it under \"Received on a delivery\" so it is not counted twice. If it really is a different purchase, confirm and save again.", 409);
        }
        if (!empty($input['opening'])) $credit = fin_acct($pdo, '3200');
        elseif (!empty($input['paid_from_account_id'])) $credit = asset_cash_account($pdo, $input['paid_from_account_id']);
        else $credit = fin_acct($pdo, 'ap');
    }
    if (!$grnUnit) $pdo->beginTransaction();
    if (trim((string)($input['serial_number'] ?? '')) !== '') {
        $sd = $pdo->prepare("SELECT asset_code FROM assets WHERE serial_number = ?");
        $sd->execute([trim((string)$input['serial_number'])]);
        if ($dupCode = $sd->fetchColumn()) { if ($pdo->inTransaction()) $pdo->rollBack(); error("Serial number {$input['serial_number']} is already registered on asset $dupCode.", 409); }
    }
    $code = next_doc_no($pdo, 'assets', 'asset_code', 'AST-');
    $stmt = $pdo->prepare("INSERT INTO assets (asset_code, name, category_id, account_id, purchase_date, purchase_cost, current_value, location, serial_number, supplier, warranty_expiry, grn_item_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$code, $name, $catId, $accountId ?: null, $date, $cost, $cost, $input['location']??null, (trim((string)($input['serial_number']??'')) ?: null), $input['supplier']??null, $input['warranty_expiry']??null, $grnItemId ?: null, (int)($_SESSION['user_id'] ?? 0) ?: null]);
    $assetId = (int)$pdo->lastInsertId();
    if ($grnUnit) {
        $pdo->prepare("UPDATE stock_batches SET quantity = quantity - 1 WHERE id = ?")->execute([$batch['id']]);
        $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes) VALUES (?,?,?,'transfer_out','asset',?,-1,?,?)")
            ->execute([$grnUnit['item_id'], $batch['id'], $batch['store_id'], $assetId, $cost, "Registered as fixed asset $code from {$grnUnit['grn_number']}"]);
    }
    // Ledger: Dr asset account; Cr opening equity (opening balance), the account it was paid from, or payables
    if ($cost > 0) {
        am_post($pdo, ['date' => $date, 'type' => !empty($input['opening']) ? 'asset_open' : 'asset_capitalize', 'ref_id' => 'new-'.$assetId,
            'description' => (!empty($input['opening']) ? 'Opening balance - ' : 'Asset purchase - ').$name,
            'lines' => [['account_id' => $accountId, 'debit' => $cost, 'desc' => $code.' '.$name], ['account_id' => $credit, 'credit' => $cost, 'desc' => $code.' '.$name]]]);
    }
    $pdo->commit();
    success(['id'=>$assetId, 'asset_code'=>$code], 'Asset created');
}

if ($method === 'POST' && $action === 'assets_depreciate') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $month = (int)($input['month'] ?? date('n'));
    $year = (int)($input['year'] ?? date('Y'));
    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) error('Choose a valid month and year', 422);
    if (sprintf('%04d-%02d-01', $year, $month) > date('Y-m-d')) error('That month has not started yet', 422);
    $periodDate = sprintf('%04d-%02d-%02d', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));
    // one run at a time: a double click or two users must not depreciate the same month twice
    $pdo->query("SELECT GET_LOCK('asset_depreciation', 10)")->fetchColumn();

    $pdo->beginTransaction();
    $assets = $pdo->prepare("SELECT a.*, c.depreciation_rate, c.depreciation_method FROM assets a JOIN asset_categories c ON c.id=a.category_id
                             WHERE a.status IN ('active','under_maintenance') AND c.depreciation_rate > 0 AND a.purchase_date <= ?
                               AND NOT EXISTS (SELECT 1 FROM asset_depreciation_log l WHERE l.asset_id = a.id AND l.period_date = ?)
                             FOR UPDATE");
    $assets->execute([$periodDate, $periodDate]);
    $count = 0;
    foreach ($assets->fetchAll() as $a) {
        $cost = (float)$a['purchase_cost'];
        $rate = (float)$a['depreciation_rate'];
        $accDep = (float)$a['accumulated_depreciation'];
        $bv = $cost - $accDep;
        if ($bv <= 0) continue;
        // Monthly depreciation = annual rate / 12
        $annualDep = $a['depreciation_method'] === 'reducing_balance' ? $bv * $rate / 100 : $cost * $rate / 100;
        $monthlyDep = round($annualDep / 12, 2);
        $monthlyDep = min($monthlyDep, $bv); // Can't depreciate below 0
        if ($monthlyDep <= 0) continue;
        $newAcc = $accDep + $monthlyDep;
        $newBV = $cost - $newAcc;
        $pdo->prepare("UPDATE assets SET accumulated_depreciation=?, current_value=? WHERE id=?")->execute([$newAcc, $newBV, $a['id']]);
        $pdo->prepare("INSERT INTO asset_depreciation_log (asset_id, period_date, depreciation_amount, accumulated, book_value, method) VALUES (?,?,?,?,?,?)")->execute([$a['id'], $periodDate, $monthlyDep, $newAcc, $newBV, $a['depreciation_method']]);
        $count++;
    }
    // Ledger: one entry per month for everything depreciated in it (re-running a month only adds what was missing)
    $tot = $pdo->prepare("SELECT COALESCE(SUM(depreciation_amount),0) FROM asset_depreciation_log WHERE period_date = ?");
    $tot->execute([$periodDate]);
    $total = round((float)$tot->fetchColumn(), 2);
    if ($count > 0 && $total > 0) {
        am_post($pdo, ['date' => $periodDate, 'type' => 'asset_depreciation', 'ref_id' => substr($periodDate, 0, 7), 'replace' => true,
            'description' => 'Monthly depreciation - '.substr($periodDate, 0, 7),
            'lines' => [['account_id' => fin_acct($pdo, '5350'), 'debit' => $total, 'desc' => 'Depreciation expense '.substr($periodDate, 0, 7)],
                        ['account_id' => fin_acct($pdo, '1300'), 'credit' => $total, 'desc' => 'Accumulated depreciation '.substr($periodDate, 0, 7)]]]);
    }
    $pdo->commit();
    success(['assets' => $count, 'total' => $total], "$count assets depreciated for month $month/$year");
}

// Disposals need a second person's approval: see handlers/assets/asset-management.php (disposal_request / disposal_decide)
if ($method === 'POST' && $action === 'assets_dispose') error('Disposals need approval: raise a disposal request on the Disposal tab', 410);
if ($method === 'POST' && $action === 'assets_capitalize') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $assetId = (int)($input['asset_id'] ?? 0);
    $amount = round((float)($input['amount'] ?? 0), 2);
    if (!$assetId || $amount <= 0) error('asset_id and a positive amount are required', 422);
    $date = asset_date($input['date'] ?? null, 'Date');
    $creditAcc = !empty($input['paid_from_account_id']) ? asset_cash_account($pdo, $input['paid_from_account_id']) : fin_acct($pdo, 'ap');

    $pdo->beginTransaction();
    $aq = $pdo->prepare("SELECT a.name, a.asset_code, a.account_id, a.status, COALESCE(c.account_id, (SELECT x.id FROM chart_of_accounts x WHERE x.account_code = CASE c.name WHEN 'Land' THEN '1211' WHEN 'Buildings' THEN '1212' WHEN 'Furniture & Fixtures' THEN '1213' WHEN 'Electronic Equipment' THEN '1217' WHEN 'Computers & IT' THEN '1217' WHEN 'Intangible Assets' THEN '1218' WHEN 'Plant & Machinery' THEN '1218' WHEN 'Motor Vehicles' THEN '1218' WHEN 'Other Equipment' THEN '1218' END)) AS cat_account FROM assets a LEFT JOIN asset_categories c ON c.id = a.category_id WHERE a.id = ? FOR UPDATE"); $aq->execute([$assetId]);
    $asset = $aq->fetch();
    if (!$asset) { $pdo->rollBack(); error('Asset not found', 404); }
    if ($asset['status'] === 'disposed') { $pdo->rollBack(); error('A disposed asset cannot be capitalized', 409); }
    $assetAcc = (int)($asset['account_id'] ?: $asset['cat_account']);
    if (!$assetAcc) { $pdo->rollBack(); error('This asset has no asset account; set its category account first', 422); }
    // Increase asset value
    $pdo->prepare("UPDATE assets SET current_value = current_value + ?, purchase_cost = purchase_cost + ? WHERE id = ?")->execute([$amount, $amount, $assetId]);

    // Record capitalization
    $pdo->prepare("INSERT INTO asset_capitalizations (asset_id, capitalization_date, amount, supplier, description) VALUES (?,?,?,?,?)")
        ->execute([$assetId, $date, $amount, $input['supplier']??'', $input['description']??'']);
    $capId = (int)$pdo->lastInsertId();
    // Ledger: Dr asset account; Cr the account it was paid from, or payables
    am_post($pdo, ['date' => $date, 'type' => 'asset_capitalize', 'ref_id' => $assetId.'-'.$capId, 'description' => 'Capitalization - '.$asset['name'],
        'lines' => [['account_id' => $assetAcc, 'debit' => $amount, 'desc' => $asset['asset_code'].' capitalization'],
                    ['account_id' => $creditAcc, 'credit' => $amount, 'desc' => $input['supplier']??'Capitalized cost']]]);
    $pdo->commit();
    success(null, 'Expense capitalized');
}

// Revaluation (IAS 16): an increase first reverses an earlier revaluation loss (profit), the rest goes to the revaluation surplus;
// a decrease first uses up that asset's surplus, only the rest is a loss.
if ($method === 'POST' && $action === 'assets_revalue') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $assetId = (int)($input['asset_id'] ?? 0);
    if (!$assetId || !isset($input['new_value']) || !is_numeric($input['new_value']) || (float)$input['new_value'] < 0) error('asset_id and a new value of zero or more are required', 422);
    $newValue = round((float)$input['new_value'], 2);
    $date = asset_date($input['date'] ?? null, 'Revaluation date');

    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT a.*, COALESCE(c.account_id, (SELECT x.id FROM chart_of_accounts x WHERE x.account_code = CASE c.name WHEN 'Land' THEN '1211' WHEN 'Buildings' THEN '1212' WHEN 'Furniture & Fixtures' THEN '1213' WHEN 'Electronic Equipment' THEN '1217' WHEN 'Computers & IT' THEN '1217' WHEN 'Intangible Assets' THEN '1218' WHEN 'Plant & Machinery' THEN '1218' WHEN 'Motor Vehicles' THEN '1218' WHEN 'Other Equipment' THEN '1218' END)) AS cat_account FROM assets a LEFT JOIN asset_categories c ON c.id = a.category_id WHERE a.id = ? FOR UPDATE"); $q->execute([$assetId]);
    $a = $q->fetch();
    if (!$a) { $pdo->rollBack(); error('Asset not found', 404); }
    if ($a['status'] === 'disposed') { $pdo->rollBack(); error('A disposed asset cannot be revalued', 409); }
    $assetAcc = (int)($a['account_id'] ?: $a['cat_account']);
    if (!$assetAcc) { $pdo->rollBack(); error('This asset has no asset account; set its category account first', 422); }
    $oldValue = round((float)$a['purchase_cost'] - (float)$a['accumulated_depreciation'], 2); // carrying amount
    $diff = round($newValue - $oldValue, 2);
    if (abs($diff) < 0.005) { $pdo->rollBack(); error('The new value equals the current book value', 422); }
    $plan = asset_reval_plan($diff, (float)$a['reval_surplus'], (float)$a['reval_loss']);
    $tag = $a['asset_code'] . ' revaluation';
    if ($diff > 0) {
        $lines = [['account_id' => $assetAcc, 'debit' => $diff, 'desc' => $tag]];
        if ($plan['reverse_loss'] > 0) $lines[] = ['account_id' => fin_acct($pdo, '4310'), 'credit' => $plan['reverse_loss'], 'desc' => $tag . ' - reverses an earlier loss'];
        if ($plan['to_surplus'] > 0) $lines[] = ['account_id' => fin_acct($pdo, '3400'), 'credit' => $plan['to_surplus'], 'desc' => $tag . ' - surplus'];
    } else {
        $lines = [['account_id' => $assetAcc, 'credit' => -$diff, 'desc' => $tag]];
        if ($plan['use_surplus'] > 0) $lines[] = ['account_id' => fin_acct($pdo, '3400'), 'debit' => $plan['use_surplus'], 'desc' => $tag . ' - uses the surplus'];
        if ($plan['new_loss'] > 0) $lines[] = ['account_id' => fin_acct($pdo, '534008'), 'debit' => $plan['new_loss'], 'desc' => $tag . ' - loss'];
    }
    // the carrying value moves to the new value; cost moves by the same difference
    $pdo->prepare("UPDATE assets SET current_value = ?, purchase_cost = purchase_cost + ?, reval_surplus = ?, reval_loss = ? WHERE id = ?")->execute([$newValue, $diff, $plan['surplus'], $plan['loss_pl'], $assetId]);
    $pdo->prepare("INSERT INTO asset_revaluations (asset_id, revaluation_date, old_value, new_value, reason) VALUES (?,?,?,?,?)")
        ->execute([$assetId, $date, $oldValue, $newValue, $input['reason']??'']);
    am_post($pdo, ['date' => $date, 'type' => 'asset_revaluation', 'ref_id' => $assetId.'-'.(int)$pdo->lastInsertId(), 'description' => 'Revaluation - '.$a['name'], 'lines' => $lines]);
    $pdo->commit();
    success(['difference' => $diff], 'Asset revalued');
}

// ===== PUT — update asset =====
// Descriptive fields only. Cost and purchase date can be corrected until the asset has depreciated, been capitalized or revalued;
// the purchase / opening journal is rewritten with it. Status changes only through disposal.
if ($method === 'PUT' && $action === 'assets') {
    $assetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$assetId) error('Asset ID required', 422);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $pdo->beginTransaction();
    $q = $pdo->prepare("SELECT a.*, COALESCE(c.account_id, (SELECT x.id FROM chart_of_accounts x WHERE x.account_code = CASE c.name WHEN 'Land' THEN '1211' WHEN 'Buildings' THEN '1212' WHEN 'Furniture & Fixtures' THEN '1213' WHEN 'Electronic Equipment' THEN '1217' WHEN 'Computers & IT' THEN '1217' WHEN 'Intangible Assets' THEN '1218' WHEN 'Plant & Machinery' THEN '1218' WHEN 'Motor Vehicles' THEN '1218' WHEN 'Other Equipment' THEN '1218' END)) AS cat_account FROM assets a LEFT JOIN asset_categories c ON c.id = a.category_id WHERE a.id = ? FOR UPDATE"); $q->execute([$assetId]);
    $a = $q->fetch();
    if (!$a) { $pdo->rollBack(); error('Asset not found', 404); }
    if ($a['status'] === 'disposed') { $pdo->rollBack(); error('A disposed asset cannot be changed', 409); }

    $fields = []; $params = [];
    foreach (['name','location','serial_number','supplier','warranty_expiry','notes','description'] as $f) {
        if (array_key_exists($f, $input)) { $fields[] = "$f=?"; $params[] = $input[$f] === '' ? null : $input[$f]; }
    }
    if (isset($input['name']) && trim((string)$input['name']) === '') { $pdo->rollBack(); error('Name cannot be empty', 422); }
    if (!empty($input['category_id']) && (int)$input['category_id'] !== (int)$a['category_id']) {
        $c = $pdo->prepare("SELECT 1 FROM asset_categories WHERE id = ? AND status = 'active'"); $c->execute([(int)$input['category_id']]);
        if (!$c->fetchColumn()) { $pdo->rollBack(); error('Choose a valid asset category', 422); }
        $fields[] = 'category_id=?'; $params[] = (int)$input['category_id'];
    }
    $newCost = round((float)($input['purchase_cost'] ?? $a['purchase_cost']), 2);
    $newDate = array_key_exists('purchase_date', $input) ? asset_date($input['purchase_date'], 'Purchase date') : $a['purchase_date'];
    if ($newCost < 0) { $pdo->rollBack(); error('Purchase cost cannot be negative', 422); }
    $costChanged = abs($newCost - (float)$a['purchase_cost']) >= 0.005;
    $dateChanged = $newDate !== $a['purchase_date'];
    if (($costChanged || $dateChanged) && !empty($a['grn_item_id'])) { $pdo->rollBack(); error('Cost and purchase date come from the goods receipt for this asset and cannot be edited here', 409); }
    if ($costChanged || $dateChanged) {
        $used = $pdo->prepare("SELECT (SELECT COUNT(*) FROM asset_depreciation_log WHERE asset_id = ?) + (SELECT COUNT(*) FROM asset_capitalizations WHERE asset_id = ?) + (SELECT COUNT(*) FROM asset_revaluations WHERE asset_id = ?)");
        $used->execute([$assetId, $assetId, $assetId]);
        if ((int)$used->fetchColumn() > 0) { $pdo->rollBack(); error('Cost and purchase date can no longer be edited: depreciation, capitalization or revaluation is already recorded. Use Capitalization or Revaluation instead.', 409); }
        $origin = am_entries($pdo, ['asset_open', 'asset_capitalize'], 'new-' . $assetId);
        if (!$origin && $costChanged) { $pdo->rollBack(); error('This asset has no purchase journal to correct; use Capitalization to add cost', 409); }
        $fields[] = 'purchase_cost=?'; $params[] = $newCost;
        $fields[] = 'current_value=?';  $params[] = $newCost;
        $fields[] = 'purchase_date=?';  $params[] = $newDate;
        if ($origin) {
            $e = $origin[0];
            $cr = $pdo->prepare("SELECT account_id FROM journal_entry_lines WHERE journal_entry_id = ? AND credit > 0 LIMIT 1"); $cr->execute([$e['id']]);
            $creditAcc = (int)$cr->fetchColumn();
            $assetAcc = (int)($a['account_id'] ?: $a['cat_account']);
            if ($newCost > 0 && $creditAcc && $assetAcc) {
                am_post($pdo, ['date' => $newDate, 'type' => $e['reference_type'], 'ref_id' => 'new-' . $assetId, 'replace' => true, 'description' => $e['description'],
                    'lines' => [['account_id' => $assetAcc, 'debit' => $newCost, 'desc' => $a['asset_code'] . ' ' . $a['name']], ['account_id' => $creditAcc, 'credit' => $newCost, 'desc' => $a['asset_code'] . ' ' . $a['name']]]]);
            } elseif ($newCost <= 0) {
                fin_unpost($pdo, $e['reference_type'], 'new-' . $assetId, 'Asset cost corrected to zero');
            }
        }
    }
    if (!$fields) { $pdo->rollBack(); error('Nothing to update', 422); }
    $params[] = $assetId;
    $pdo->prepare("UPDATE assets SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
    $pdo->commit();
    success(null, 'Asset updated');
}

