<?php
// includes/store_split.php — one-off stock layout used by migrate.php:
//   * only two shared stores stay in use: Main Store (central purchasing) and Clinic Store (dispensing / department requests)
//   * the lab stores (MAIN-LAB, CLINIC-LAB) are merged into them and switched off
//   * 60% of every batch stays in Main / Clinic, 40% moves to the departments' own stores (DEPT-<id>)
// Both steps are safe to run again: the merge only touches lab stores that still hold stock, the split runs once
// (marked by 'dept_split2' stock movements; the first version 'dept_split' is returned and redone). Ledger: the inventory account follows the stock (finance_posting.php).

require_once __DIR__ . '/request_schema.php';      // dept_store_id()
require_once __DIR__ . '/finance_posting.php';     // fin_post_stock(), fin_store_acct()

/** Stock value of the batches that just moved, posted Dr destination / Cr source inventory account. A ledger problem is reported, never fatal. */
function store_post_value(PDO $pdo, string $type, string $ref, int $fromStore, int $toStore, float $value, string $desc, callable $say): void {
    if ($value < 0.005) return;
    try {
        fin_post_stock($pdo, $type, $ref, $toStore, $value, fin_store_acct($pdo, $fromStore), $desc);
    } catch (Throwable $e) { $say("! ledger entry '$type $ref' not posted: " . $e->getMessage()); }
}

/** Move one batch (or part of it) into $toStore. Same batch number / expiry / cost joins an existing batch, otherwise a new batch row is made. */
function store_move_batch(PDO $pdo, array $b, int $toStore, float $qty, string $refType, string $note): void {
    $f = $pdo->prepare('SELECT id FROM stock_batches WHERE item_id=? AND store_id=? AND batch_number <=> ? AND expiry_date <=> ? AND unit_cost <=> ? AND is_active=1 LIMIT 1');
    $f->execute([$b['item_id'], $toStore, $b['batch_number'], $b['expiry_date'], $b['unit_cost']]);
    if ($did = (int)$f->fetchColumn()) {
        $pdo->prepare('UPDATE stock_batches SET quantity=quantity+? WHERE id=?')->execute([$qty, $did]);
    } else {
        $pdo->prepare('INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, reorder_level, location_rack, is_active) VALUES (?,?,?,?,?,?,?,?,0,?,1)')
            ->execute([$b['item_id'], $toStore, $b['batch_number'], $qty, $b['unit_cost'], $b['selling_price'], $b['expiry_date'], $b['received_date'], $b['location_rack']]);
        $did = (int)$pdo->lastInsertId();
    }
    $mv = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, quantity, unit_cost, notes) VALUES (?,?,?,?,?,?,?,?,?)");
    $mv->execute([$b['item_id'], $b['id'], $b['store_id'], $toStore, 'transfer_out', $refType, $qty, $b['unit_cost'], $note]);
    $mv->execute([$b['item_id'], $did, $b['store_id'], $toStore, 'transfer_in', $refType, $qty, $b['unit_cost'], $note]);
}

/** MAIN-LAB -> Main Store, CLINIC-LAB -> Clinic Store; the lab stores are then switched off (their history stays). */
function store_merge_lab(PDO $pdo, bool $dry, callable $say): void {
    $id = fn(string $code) => (int)$pdo->query("SELECT id FROM stores WHERE code=" . $pdo->quote($code))->fetchColumn();
    $pairs = ['MAIN-LAB' => 'MAIN', 'CLINIC-LAB' => 'CLINIC'];
    $pdo->beginTransaction();
    try {
        foreach ($pairs as $labCode => $toCode) {
            $from = $id($labCode); $to = $id($toCode);
            if (!$from || !$to) { $say("= lab store $labCode: nothing to merge (store missing)"); continue; }
            $bs = $pdo->prepare('SELECT * FROM stock_batches WHERE store_id=? AND quantity<>0 AND is_active=1');
            $bs->execute([$from]);
            $rows = $bs->fetchAll(); $units = 0; $value = 0.0;
            foreach ($rows as $b) {
                $units += (float)$b['quantity']; $value += (float)$b['quantity'] * (float)$b['unit_cost'];
                if ($dry) continue;
                store_move_batch($pdo, $b, $to, (float)$b['quantity'], 'store_merge', "Lab store merged into $toCode");
                $pdo->prepare('UPDATE stock_batches SET quantity=0, is_active=0 WHERE id=?')->execute([$b['id']]);
            }
            if (!$dry) {
                $pdo->prepare('UPDATE stores SET is_active=0 WHERE id=?')->execute([$from]);
                store_post_value($pdo, 'store_merge', "$labCode>$toCode", $from, $to, $value, "Lab store $labCode merged into $toCode", $say);
            }
            $say(($dry ? '[dry run] would merge ' : '= merged ') . count($rows) . " batch(es), $units unit(s) from $labCode into $toCode");
        }
        $dry ? $pdo->rollBack() : $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $say('! lab store merge failed, nothing changed: ' . $e->getMessage()); }
}

/**
 * Which departments use an item (first matching rule wins, most relevant department first).
 * 3 OPD, 4 IPD, 5 ER, 32 Maternity, 33 Labour, 35 Paediatrics, 77 ANC, 46 Pharmacy, 48 Theatre, 53 Nursing,
 * 36 Laboratory, 37 Microbiology, 58 Housekeeping, 71 Infection control, 1 Administration, 63 Finance, 54 Dietary.
 */
function store_split_targets(array $b): array {
    $cat = (int)$b['cat']; $n = (string)$b['iname'];
    if ($cat == 6) return [58, 71];
    if ($cat == 5) return [1, 63];
    if (in_array($cat, [7, 8, 9, 10])) return [58, 54];
    $rules = [
        // laboratory: stains, containers, swabs go to Microbiology too; antenatal screening tests go to ANC
        ['/giemsa|gram stain|stool container|urine container|slide|ecouvillon|pipete|h\.?pylori/i', [37, 36]],
        ['/hiv|hepatite|rpr|syphilis|pregnancy|urine strips|rubella|toxo/i',                       [36, 77]],
        ['/slides|tube|needls|tips|edta|container|lancette|lyse|cleaner|diluent|alcool|distered|hypocliner|enveoppe|enveloppe|reagent|analyzer|test|bandelette|strips|aslo|crp latex|widal|tpha|abo blood|^ant-|^creat|^urea|^got|^gpt|^k\+|^na\+|calcium|^o$|vacutainer/i', [36]],
        // family planning
        ['/depo-provera|noristerat|implanon|intrauterine|diu\b|pills-72/i',                         [3, 32, 77]],
        // labour and bleeding
        ['/tranexamic|mesoprostol|misoprostol|nifedipine|dicyinone/i',                              [33, 32, 5]],
        // emergency / ward drugs
        ['/adrenaline|hydrocortisone|dexametasone|diazepan|furosemide|salbutamol/i',                [5, 46, 3, 35, 4]],
        // IV fluids
        ['/normal saline|glucose|ringer|chloride|water for injection/i',                           [5, 46, 33, 32, 35, 48, 4]],
        // injectable antibiotics
        ['/ampicil|benzathine|ceftriaxone|ciproflox|gentamy/i',                                    [46, 3, 5, 32, 35, 4]],
        // pain, nausea, stomach
        ['/diclofenac|paracetamol|tramadol|buscopan|cimetidine|omepla|metoclopramide|ondansen/i',  [46, 3, 5, 32, 4]],
        ['/vit b|ors\b|pommade/i',                                                                  [46, 3, 35, 4]],
        // catheters and bags
        ['/foley|urine bag/i',                                                                      [48, 4, 5, 53]],
        // theatre, suturing, delivery
        ['/polyglactine|surgical|sugical|bloders|lidocaine|paraffine|sterile|trousse|dental/i',     [48, 32, 33, 5]],
        ['/tongue depressor/i',                                                                     [3, 35, 5]],
    ];
    foreach ($rules as [$re, $depts]) if (preg_match($re, $n)) return $depts;
    if ($b['med']) return [46, 3, 5, 32, 35, 4];                                                // other medicine: Pharmacy first, IPD last
    return [53, 3, 5, 4, 48, 32, 33];                                                           // other supplies: Nursing first
}

/** IPD is not always occupied, so it gets half the share of every other department. */
function store_split_share(array $depts, int $total): array {
    $w = array_map(fn($d) => $d == 4 ? 1 : 2, $depts); $sum = array_sum($w);
    $out = array_map(fn($x) => intdiv($total * $x, $sum), $w);
    for ($left = $total - array_sum($out), $k = 0; $left > 0; $left--, $k++) $out[$k % count($out)]++;   // rest goes to the most relevant departments
    return $out;
}

/** The old split (marker 'dept_split') ignored what each department uses. Put its stock back where it came from so it can be split again. */
function store_return_departments(PDO $pdo, bool $dry, callable $say): void {
    $done = fn(string $t) => (bool)$pdo->query("SELECT 1 FROM stock_movements WHERE reference_type='$t' LIMIT 1")->fetchColumn();
    if (!$done('dept_split') || $done('dept_split2')) return;
    $clinic = (int)$pdo->query("SELECT id FROM stores WHERE code='CLINIC'")->fetchColumn();
    $rows = $pdo->query("SELECT b.*, (SELECT m.from_store_id FROM stock_movements m WHERE m.batch_id=b.id AND m.reference_type='dept_split' AND m.movement_type='transfer_in' LIMIT 1) origin
        FROM stock_batches b JOIN stores s ON s.id=b.store_id WHERE s.code LIKE 'DEPT-%' AND b.quantity>0 AND b.is_active=1 ORDER BY b.id")->fetchAll();
    $units = 0; $perStore = [];
    $pdo->beginTransaction();
    try {
        foreach ($rows as $b) {
            $to = (int)$b['origin'] ?: $clinic; $units += (float)$b['quantity'];
            $perStore[$to] = ($perStore[$to] ?? 0) + (float)$b['quantity'] * (float)$b['unit_cost'];
            if ($dry) continue;
            store_move_batch($pdo, $b, $to, (float)$b['quantity'], 'dept_return', 'Old department split returned before the new split');
            $pdo->prepare('UPDATE stock_batches SET quantity=0 WHERE id=?')->execute([$b['id']]);
        }
        if (!$dry) foreach ($perStore as $to => $value) if ($to != $clinic) {
            store_post_value($pdo, 'dept_return', (string)$to, $clinic, $to, $value, 'Old department split returned', $say);   // departments sit in the clinic inventory account
        }
        $dry ? $pdo->rollBack() : $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $say('! returning the old department split failed, nothing changed: ' . $e->getMessage()); return; }
    $say(($dry ? '[dry run] would return ' : '= returned ') . count($rows) . " department batch(es), $units unit(s) to Main / Clinic");
}

/** Keep 60% in Main / Clinic, move 40% to the departments that use the item. Runs once ('dept_split2'). */
function store_split_departments(PDO $pdo, bool $dry, callable $say): void {
    store_return_departments($pdo, $dry, $say);
    if ($pdo->query("SELECT 1 FROM stock_movements WHERE reference_type='dept_split2' LIMIT 1")->fetchColumn()) { $say('= 40% department split already done'); return; }
    $stores = $pdo->query("SELECT id FROM stores WHERE code IN ('MAIN','CLINIC')")->fetchAll(PDO::FETCH_COLUMN);
    if (!$stores) { $say('! Main / Clinic store missing, split skipped'); return; }
    $in = implode(',', array_map('intval', $stores));
    $batches = $pdo->query("SELECT b.*, i.name iname, i.category_id cat, i.is_medication med FROM stock_batches b JOIN items i ON i.id=b.item_id
        WHERE b.is_active=1 AND b.quantity>=1 AND b.store_id IN ($in) ORDER BY b.id")->fetchAll();
    $exists = $pdo->query("SELECT id FROM departments")->fetchAll(PDO::FETCH_COLUMN);
    $moved = 0; $perStore = [];
    $pdo->beginTransaction();
    try {
        foreach ($batches as $b) {
            $depts = array_values(array_filter(store_split_targets($b), fn($d) => in_array($d, $exists)));
            $move = (int)floor($b['quantity'] * 0.4);
            if ($move < 1 || !$depts) continue;
            foreach (store_split_share($depts, $move) as $k => $q) {
                if ($q < 1) continue;
                $moved += $q;
                $perStore[$b['store_id']] = ($perStore[$b['store_id']] ?? 0) + $q * (float)$b['unit_cost'];
                if ($dry) continue;
                $to = dept_store_id($pdo, (int)$depts[$k]);
                store_move_batch($pdo, $b, $to, $q, 'dept_split2', '40% of stock split to the department store');
                $pdo->prepare('UPDATE stock_batches SET quantity=quantity-? WHERE id=?')->execute([$q, $b['id']]);
            }
        }
        if (!$dry) foreach ($perStore as $from => $value) {
            // department stores carry no inventory account of their own, so their stock stays in the clinic inventory account
            $deptAcctStore = (int)$pdo->query("SELECT id FROM stores WHERE code='CLINIC'")->fetchColumn();
            store_post_value($pdo, 'dept_split2', (string)$from, (int)$from, $deptAcctStore, $value, 'Stock split to department stores', $say);
        }
        $dry ? $pdo->rollBack() : $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $say('! department split failed, nothing changed: ' . $e->getMessage()); return; }
    $say(($dry ? '[dry run] would move ' : '= moved ') . "$moved unit(s) to department stores");
}
