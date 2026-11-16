<?php
// includes/stock.php — one place to ask "is this drug in stock?". Used by prescribe/dispense checks.

/** Units on hand for an item: active, non-expired batches, optionally limited to one store. */
function stock_available(PDO $pdo, int $itemId, ?int $storeId = null): float {
    $sql = "SELECT COALESCE(SUM(quantity),0) FROM stock_batches
            WHERE item_id=? AND is_active=1 AND quantity>0 AND (expiry_date IS NULL OR expiry_date>=CURDATE())";
    $params = [$itemId];
    if ($storeId !== null) { $sql .= " AND store_id=?"; $params[] = $storeId; }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (float)$st->fetchColumn();
}

/** Warning text when qty is more than what is on hand, else null. Warning only: callers never block (client decision). */
function stock_warning(PDO $pdo, int $itemId, float $qty, string $name = '', ?int $storeId = null): ?string {
    $have = stock_available($pdo, $itemId, $storeId);
    if ($have >= $qty) return null;
    $label = $name !== '' ? $name : "item #$itemId";
    return $have <= 0 ? "Out of stock: $label" : "Low stock: $label (available $have, needed $qty)";
}

/** FIFO-deduct $qty of an item from $storeId, one stock_movements row per batch touched. Caller owns the transaction.
 *  Never blocks: a shortfall goes negative on the newest batch (or a DEFAULT batch). Returns total cost consumed. */
function stock_consume(PDO $pdo, int $itemId, float $qty, int $storeId, string $refType, ?int $refId, string $note, $userId): float {
    $mv = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
                         VALUES (?, ?, ?, NULL, 'consumption', ?, ?, ?, ?, ?, ?, NOW())");
    $q = $pdo->prepare("SELECT id, quantity, unit_cost FROM stock_batches WHERE item_id = ? AND store_id = ? AND is_active = 1 AND quantity > 0 ORDER BY (expiry_date IS NULL), expiry_date ASC, received_date ASC, id ASC");
    $q->execute([$itemId, $storeId]);
    $remaining = $qty; $cost = 0.0;
    foreach ($q->fetchAll() as $b) {
        if ($remaining <= 0) break;
        $d = min((float)$b['quantity'], $remaining);
        $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ? WHERE id = ?")->execute([$d, $b['id']]);
        $mv->execute([$itemId, $b['id'], $storeId, $d, (float)$b['unit_cost'], $refType, $refId, $note, $userId]);
        $cost += $d * (float)$b['unit_cost']; $remaining -= $d;
    }
    if ($remaining > 0.0001) {
        $a = $pdo->prepare("SELECT id, unit_cost FROM stock_batches WHERE item_id = ? AND store_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $a->execute([$itemId, $storeId]);
        if ($row = $a->fetch()) {
            $batchId = (int)$row['id']; $uc = (float)$row['unit_cost'];
            $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ? WHERE id = ?")->execute([$remaining, $batchId]);
        } else {
            $uc = 0.0;
            $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, is_active, created_by, created_at) VALUES (?, ?, 'DEFAULT', ?, 0, 1, ?, NOW())")
                ->execute([$itemId, $storeId, -$remaining, $userId]);
            $batchId = (int)$pdo->lastInsertId();
        }
        $mv->execute([$itemId, $batchId, $storeId, $remaining, $uc, $refType, $refId, $note, $userId]);
        $cost += $remaining * $uc;
    }
    return $cost;
}
