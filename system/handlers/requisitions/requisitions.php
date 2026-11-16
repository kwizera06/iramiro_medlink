<?php
// handlers/requisitions/requisitions.php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/controls.php';
if(session_status()===PHP_SESSION_NONE) session_start();
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}
// Approving or rejecting a requisition is limited to these roles (as in Michel's system; storekeper = medlink's spelling of the storekeeper role)
function req_can_review():bool{return (bool)array_intersect($_SESSION['user_data']['role_slugs']??[],['admin','inventory_manager','managing_director','storekeeper','storekeper']);}

$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];
$id=isset($_GET['id'])?(int)$_GET['id']:null;
$action=$_GET['action']??'';
$userId=$_SESSION['user_id']??null;

// Fallback: if no session, use first active user (dev mode)
if(!$userId){
    $fallback=$pdo->query("SELECT id FROM users WHERE is_active=1 LIMIT 1")->fetch();
    $userId=$fallback?$fallback['id']:0;
}

function fetchReq(PDO $pdo, int $id):?array{
    $s=$pdo->prepare("SELECT r.*, u.first_name AS requester_first, u.last_name AS requester_last, 
           s.name AS store_name, d.name AS dept_name,
           rv.first_name AS reviewer_first, rv.last_name AS reviewer_last,
           iss.first_name AS issuer_first, iss.last_name AS issuer_last,
           (SELECT je.reference FROM journal_entries je WHERE je.reference_type='requisition' AND je.reference_id=CAST(r.id AS CHAR) ORDER BY je.id DESC LIMIT 1) AS journal_reference,
           (SELECT je.id FROM journal_entries je WHERE je.reference_type='requisition' AND je.reference_id=CAST(r.id AS CHAR) ORDER BY je.id DESC LIMIT 1) AS journal_entry_id
    FROM requisitions r
    JOIN users u ON u.id=r.requested_by
    JOIN stores s ON s.id=r.store_id
    LEFT JOIN departments d ON d.id=r.department_id
    LEFT JOIN users rv ON rv.id=r.reviewed_by
    LEFT JOIN users iss ON iss.id=r.issued_by
    WHERE r.id=?");
    $s->execute([$id]);$r=$s->fetch(PDO::FETCH_ASSOC);
    if(!$r) return null;

    // Get items with category & current stock in store
    $storeId = (int)$r['store_id'];
    $items=$pdo->prepare("
        SELECT ri.*, it.name AS item_name, it.item_code, it.category_id, ic.name AS category_name, u2.name AS uom_name,
               COALESCE(
                   NULLIF(ri.unit_price, 0),
                   (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = ri.item_id AND sb.store_id = ? AND sb.quantity > 0 AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY (sb.expiry_date IS NULL), sb.expiry_date ASC, sb.id ASC LIMIT 1),
                   (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = ri.item_id AND sb.store_id = ? AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY sb.id DESC LIMIT 1),
                   (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = ri.item_id AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY sb.id DESC LIMIT 1),
                   NULLIF(it.unit_price, 0),
                   0
               ) AS unit_price,
               COALESCE((SELECT SUM(sb.quantity) FROM stock_batches sb WHERE sb.item_id=ri.item_id AND sb.store_id=? AND sb.is_active=1), 0) AS current_stock
        FROM requisition_items ri
        JOIN items it ON it.id=ri.item_id
        LEFT JOIN item_categories ic ON ic.id=it.category_id
        LEFT JOIN item_uoms u2 ON u2.id=it.uom_id
        WHERE ri.requisition_id=?
    ");
    $items->execute([$storeId, $storeId, $storeId, $id]);
    $r['items']=$items->fetchAll(PDO::FETCH_ASSOC);
    return $r;
}

try {

// ===== GET =====
if($method==='GET'){
    // Single
    if($id){
        $r=fetchReq($pdo,$id);
        if(!$r) err('Not found',404);
        ok($r);
    }

    // Helper: Consumable Items (category_id != 1)
    if($action==='consumable_items' || $action==='items'){
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        $search = trim($_GET['search'] ?? '');
        $params = [];
        $where = ["(i.category_id != 1 OR i.category_id IS NULL)", "(i.is_medication = 0 OR i.is_medication IS NULL)", "i.is_active = 1"];
        if ($search !== '') {
            $where[] = "(i.name LIKE ? OR i.item_code LIKE ? OR i.generic_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $whereSQL = 'WHERE ' . implode(' AND ', $where);
        $storeStockSelect = $storeId > 0 
            ? "COALESCE((SELECT SUM(sb.quantity) FROM stock_batches sb WHERE sb.item_id = i.id AND sb.store_id = $storeId AND sb.is_active = 1), 0)"
            : "COALESCE((SELECT SUM(sb.quantity) FROM stock_batches sb WHERE sb.item_id = i.id AND sb.is_active = 1), 0)";

        $unitCostSelect = $storeId > 0
            ? "COALESCE(
                (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = i.id AND sb.store_id = $storeId AND sb.quantity > 0 AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY (sb.expiry_date IS NULL), sb.expiry_date ASC, sb.id ASC LIMIT 1),
                (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = i.id AND sb.store_id = $storeId AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY sb.id DESC LIMIT 1),
                (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = i.id AND sb.quantity > 0 AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY (sb.expiry_date IS NULL), sb.expiry_date ASC, sb.id ASC LIMIT 1),
                (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = i.id AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY sb.id DESC LIMIT 1),
                NULLIF(i.unit_price, 0),
                NULLIF(i.selling_price, 0),
                0
            )"
            : "COALESCE(
                (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = i.id AND sb.quantity > 0 AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY (sb.expiry_date IS NULL), sb.expiry_date ASC, sb.id ASC LIMIT 1),
                (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = i.id AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY sb.id DESC LIMIT 1),
                NULLIF(i.unit_price, 0),
                NULLIF(i.selling_price, 0),
                0
            )";

        $stmt = $pdo->prepare("
            SELECT i.id, i.item_code, i.name, i.category_id, ic.name AS category_name, i.uom_id, u.name AS uom_name,
                   $unitCostSelect AS unit_cost,
                   $unitCostSelect AS unit_price,
                   COALESCE(i.selling_price, 0) AS selling_price,
                   $storeStockSelect AS stock
            FROM items i
            LEFT JOIN item_categories ic ON ic.id = i.category_id
            LEFT JOIN item_uoms u ON u.id = i.uom_id
            $whereSQL
            ORDER BY i.name ASC
            LIMIT 1000
        ");
        $stmt->execute($params);
        ok(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // Helper: Stores
    if($action==='stores'){
        $stmt = $pdo->query("SELECT id, name, code FROM stores WHERE is_active = 1 ORDER BY name");
        ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Helper: Departments
    if($action==='departments'){
        $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
        ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // List
    $page=max(1,(int)($_GET['page']??1));$limit=min(100,max(1,(int)($_GET['limit']??20)));$offset=($page-1)*$limit;
    $w=[];$p=[];

    // Filter by view/action
    if(($action==='my-requests' || $action==='internal-requisition') && $userId){
        $w[]="r.requested_by=?";$p[]=$userId;
    }
    if($action==='verification'){
        $w[]="r.status='submitted'";
    }
    if($action==='approved'){
        $w[]="r.status IN ('approved','issued','partial')";
    }
    if(!empty($_GET['status'])){$w[]="r.status=?";$p[]=$_GET['status'];}
    if(!empty($_GET['store_id'])){$w[]="r.store_id=?";$p[]=(int)$_GET['store_id'];}
    if(!empty($_GET['department_id'])){$w[]="r.department_id=?";$p[]=(int)$_GET['department_id'];}
    if(!empty($_GET['search'])){
        $sTerm = '%'.trim($_GET['search']).'%';
        $w[]="(
            r.requisition_no LIKE ? 
            OR r.purpose LIKE ? 
            OR u.first_name LIKE ? 
            OR u.last_name LIKE ?
            OR d.name LIKE ?
            OR s.name LIKE ?
            OR EXISTS (
                SELECT 1 FROM requisition_items ri_s 
                JOIN items it_s ON it_s.id = ri_s.item_id 
                WHERE ri_s.requisition_id = r.id 
                AND (it_s.name LIKE ? OR it_s.item_code LIKE ? OR it_s.generic_name LIKE ?)
            )
        )";
        $p[] = $sTerm; $p[] = $sTerm; $p[] = $sTerm; $p[] = $sTerm;
        $p[] = $sTerm; $p[] = $sTerm; $p[] = $sTerm; $p[] = $sTerm; $p[] = $sTerm;
    }

    $ws=$w?'WHERE '.implode(' AND ',$w):'';
    $cnt=$pdo->prepare("
        SELECT COUNT(DISTINCT r.id) 
        FROM requisitions r 
        JOIN users u ON u.id=r.requested_by 
        JOIN stores s ON s.id=r.store_id 
        LEFT JOIN departments d ON d.id=r.department_id 
        $ws
    ");
    $cnt->execute($p);
    $total=(int)$cnt->fetchColumn();

    $s=$pdo->prepare("
        SELECT r.*, u.first_name AS requester_first, u.last_name AS requester_last,
               s.name AS store_name, d.name AS dept_name,
               (SELECT COUNT(*) FROM requisition_items WHERE requisition_id=r.id) AS item_count,
               COALESCE((SELECT SUM(quantity_requested) FROM requisition_items WHERE requisition_id=r.id), 0) AS total_qty,
               COALESCE((SELECT SUM(quantity_approved) FROM requisition_items WHERE requisition_id=r.id), 0) AS approved_qty,
               COALESCE((SELECT SUM(quantity_requested * COALESCE(
                   NULLIF(unit_price, 0),
                   (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = requisition_items.item_id AND sb.store_id = r.store_id AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY (sb.quantity > 0) DESC, sb.id DESC LIMIT 1),
                   (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = requisition_items.item_id AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY sb.id DESC LIMIT 1),
                   (SELECT it2.unit_price FROM items it2 WHERE it2.id = requisition_items.item_id),
                   0
               )) FROM requisition_items WHERE requisition_id=r.id), 0) AS total_estimated_cost,
               (SELECT je.reference FROM journal_entries je WHERE je.reference_type='requisition' AND je.reference_id=CAST(r.id AS CHAR) ORDER BY je.id DESC LIMIT 1) AS journal_reference
        FROM requisitions r
        JOIN users u ON u.id=r.requested_by
        JOIN stores s ON s.id=r.store_id
        LEFT JOIN departments d ON d.id=r.department_id
        $ws 
        ORDER BY r.created_at DESC 
        LIMIT $limit OFFSET $offset
    ");
    $s->execute($p);

    // Stats
    $stats=$pdo->query("SELECT COUNT(*) AS total,
        SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) AS pending_approval,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected,
        SUM(CASE WHEN status IN ('issued','partial') THEN 1 ELSE 0 END) AS issued
    FROM requisitions")->fetch(PDO::FETCH_ASSOC);

    ok(['requisitions'=>$s->fetchAll(PDO::FETCH_ASSOC),'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

// ===== POST =====
if($method==='POST'){

    // Submit for approval
    if($action==='submit'&&$id){
        $chk=$pdo->prepare("SELECT id,status FROM requisitions WHERE id=?");$chk->execute([$id]);$r=$chk->fetch(PDO::FETCH_ASSOC);
        if(!$r) err('Not found',404);
        if($r['status']!=='draft') err('Only draft requisitions can be submitted',422);
        $pdo->prepare("UPDATE requisitions SET status='submitted', submitted_at=NOW() WHERE id=?")->execute([$id]);
        ok(fetchReq($pdo,$id),'Submitted for approval');
    }

    // Approve Requisition (Reduce Stock from Batches, Log Stock Movement, Create Journal Entry for Expense)
    if($action==='approve'&&$id){
        if(!req_can_review()) err('You are not authorized to approve requisitions',403);
        $chk=$pdo->prepare("SELECT r.*, d.name AS dept_name, s.name AS store_name FROM requisitions r LEFT JOIN departments d ON d.id=r.department_id LEFT JOIN stores s ON s.id=r.store_id WHERE r.id=?");
        $chk->execute([$id]);
        $r=$chk->fetch(PDO::FETCH_ASSOC);
        if(!$r) err('Requisition not found',404);
        if($m=ctl_conflict($pdo,'approve',['requested'=>$r['requested_by']])) err($m,403);
        if($r['status']!=='submitted' && $r['status']!=='draft') err('Only submitted or draft requisitions can be approved',422);

        $storeId = (int)$r['store_id'];
        $remarks = trim($input['remarks']??'');

        // Fetch requisition items with details
        $itemsStmt = $pdo->prepare("
            SELECT ri.*, it.name AS item_name, it.category_id, COALESCE(it.unit_price, 0) AS item_unit_price 
            FROM requisition_items ri 
            JOIN items it ON it.id = ri.item_id 
            WHERE ri.requisition_id = ?
        ");
        $itemsStmt->execute([$id]);
        $reqItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($reqItems)) err('No items found in this requisition', 422);

        $approvedMap = [];
        if (!empty($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $it) {
                $approvedMap[(int)$it['id']] = max(0, (int)($it['quantity_approved'] ?? $it['quantity'] ?? 0));
            }
        }

        // Validate stock availability before approval
        foreach ($reqItems as $item) {
            $riId = (int)$item['id'];
            $approvedQty = isset($approvedMap[$riId]) ? $approvedMap[$riId] : (int)$item['quantity_requested'];
            if ($approvedQty <= 0) continue;

            $stkStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_batches WHERE item_id = ? AND store_id = ? AND is_active = 1 AND quantity > 0");
            $stkStmt->execute([(int)$item['item_id'], $storeId]);
            $availStock = (float)$stkStmt->fetchColumn();

            if ($availStock <= 0) {
                err("Warning: Cannot approve '{$item['item_name']}'. Item is completely out of stock in {$r['store_name']} (0 available).", 422);
            }
            if ($approvedQty > $availStock) {
                err("Warning: Cannot approve '{$item['item_name']}'. Approved quantity ({$approvedQty}) exceeds available stock in {$r['store_name']} ({$availStock} available).", 422);
            }
        }

        $pdo->beginTransaction();
        try {
            $totalRequisitionCost = 0.00;

            foreach ($reqItems as $item) {
                $itemId = (int)$item['item_id'];
                $riId = (int)$item['id'];
                $approvedQty = isset($approvedMap[$riId]) ? $approvedMap[$riId] : (int)$item['quantity_requested'];

                if ($approvedQty <= 0) {
                    $pdo->prepare("UPDATE requisition_items SET quantity_approved = 0, quantity_issued = 0 WHERE id = ?")
                        ->execute([$riId]);
                    continue;
                }

                // Deduct from stock_batches (FIFO)
                $batchStmt = $pdo->prepare("
                    SELECT id, quantity, unit_cost, batch_number, expiry_date
                    FROM stock_batches
                    WHERE item_id = ? AND store_id = ? AND quantity > 0 AND is_active = 1
                    ORDER BY (expiry_date IS NULL), expiry_date ASC, id ASC
                ");
                $batchStmt->execute([$itemId, $storeId]);
                $batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

                $qtyToDeduct = $approvedQty;
                $itemTotalCost = 0.00;

                if (!empty($batches)) {
                    foreach ($batches as $batch) {
                        if ($qtyToDeduct <= 0) break;
                        $batchId = (int)$batch['id'];
                        $batchQty = (float)$batch['quantity'];
                        $unitCost = (float)$batch['unit_cost'] > 0 ? (float)$batch['unit_cost'] : (float)$item['item_unit_price'];
                        $deduct = min($qtyToDeduct, $batchQty);

                        // Reduce stock in batch
                        $pdo->prepare("UPDATE stock_batches SET quantity = quantity - ?, updated_at = NOW() WHERE id = ?")
                            ->execute([$deduct, $batchId]);

                        // Record movement in stock_movements (positive quantity with from_store_id)
                        $pdo->prepare("
                            INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by, created_at)
                            VALUES (?, ?, ?, NULL, 'consumption', 'requisition', ?, ?, ?, ?, ?, NOW())
                        ")->execute([
                            $itemId,
                            $batchId,
                            $storeId,
                            $id,
                            $deduct,
                            $unitCost,
                            "Consumption Requisition {$r['requisition_no']} - {$item['item_name']}",
                            $userId
                        ]);

                        $itemTotalCost += ($deduct * $unitCost);
                        $qtyToDeduct -= $deduct;
                    }
                }

                // If remaining quantity needed, stock was insufficient
                if ($qtyToDeduct > 0) {
                    throw new Exception("Insufficient stock in {$r['store_name']} for item '{$item['item_name']}'.");
                }

                $unitPriceForRecord = $approvedQty > 0 ? round($itemTotalCost / $approvedQty, 2) : (float)$item['item_unit_price'];
                $pdo->prepare("UPDATE requisition_items SET quantity_approved = ?, quantity_issued = ?, unit_price = ? WHERE id = ?")
                    ->execute([$approvedQty, $approvedQty, $unitPriceForRecord, $riId]);

                $totalRequisitionCost += $itemTotalCost;
            }

            // CREATE JOURNAL ENTRY (Debit Expense, Credit Inventory)
            $jeReference = null;
            if ($totalRequisitionCost > 0) {
                // Find Expense Account (Default 1266 / 5010 or 5200 / 5340)
                $expAcc = $pdo->query("
                    SELECT id FROM chart_of_accounts 
                    WHERE account_type = 'expense' AND (account_code IN ('5010', '5200', '5340', '5000') OR account_name LIKE '%Consumable%' OR account_name LIKE '%Operating%') AND status = 'active'
                    ORDER BY (account_code = '5010') DESC, (account_code = '5200') DESC LIMIT 1
                ")->fetch(PDO::FETCH_ASSOC);
                $expenseAccountId = $expAcc ? (int)$expAcc['id'] : 1266;

                // Find Inventory Asset Account (Default 9 / 1130)
                $invAcc = $pdo->query("
                    SELECT id FROM chart_of_accounts 
                    WHERE account_type = 'asset' AND (account_code = '1130' OR account_name LIKE '%Inventory%') AND status = 'active'
                    LIMIT 1
                ")->fetch(PDO::FETCH_ASSOC);
                $inventoryAccountId = $invAcc ? (int)$invAcc['id'] : 9;

                $lastJe = $pdo->query("SELECT id FROM journal_entries ORDER BY id DESC LIMIT 1")->fetchColumn();
                $nextJeNum = ((int)$lastJe) + 1;
                $jeReference = 'JE-REQ-' . date('Ymd') . '-' . str_pad($nextJeNum, 4, '0', STR_PAD_LEFT);

                $deptName = !empty($r['dept_name']) ? $r['dept_name'] : 'Internal Consumption';
                $jeDesc = "Internal Requisition {$r['requisition_no']} - {$deptName} Consumables Expense";

                $pdo->prepare("
                    INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_by, posted_at, created_by, created_at)
                    VALUES (CURDATE(), ?, ?, 'requisition', ?, 1, ?, NOW(), ?, NOW())
                ")->execute([$jeReference, $jeDesc, (string)$id, $userId, $userId]);
                $jeId = (int)$pdo->lastInsertId();

                // Debit Expense
                $pdo->prepare("
                    INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit, created_at)
                    VALUES (?, ?, ?, ?, 0.00, NOW())
                ")->execute([$jeId, $expenseAccountId, "Consumables Expense for {$r['requisition_no']} ({$deptName})", $totalRequisitionCost]);

                // Credit Inventory
                $pdo->prepare("
                    INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit, created_at)
                    VALUES (?, ?, ?, 0.00, ?, NOW())
                ")->execute([$jeId, $inventoryAccountId, "Inventory stock reduction for {$r['requisition_no']}", $totalRequisitionCost]);
            }

            $pdo->prepare("
                UPDATE requisitions 
                SET status = 'approved', 
                    reviewed_by = ?, reviewed_at = NOW(), review_remarks = ?,
                    issued_by = ?, issued_at = NOW(), issue_remarks = ?
                WHERE id = ?
            ")->execute([$userId, $remarks, $userId, $remarks, $id]);

            $pdo->commit();
            ok(['requisition' => fetchReq($pdo, $id), 'journal_reference' => $jeReference, 'total_cost' => $totalRequisitionCost], 'Requisition approved, stock reduced and journal entry recorded');
        } catch (Exception $e) {
            $pdo->rollBack();
            err('Failed to approve requisition: ' . $e->getMessage(), 500);
        }
    }

    // Reject
    if($action==='reject'&&$id){
        if(!req_can_review()) err('You are not authorized to reject requisitions',403);
        $chk=$pdo->prepare("SELECT id,status FROM requisitions WHERE id=?");$chk->execute([$id]);$r=$chk->fetch(PDO::FETCH_ASSOC);
        if(!$r) err('Not found',404);
        if($r['status']!=='submitted') err('Only submitted requisitions can be rejected',422);
        $remarks=trim($input['remarks']??'');
        if(!$remarks) err('Rejection remarks required',422);
        $pdo->prepare("UPDATE requisitions SET status='rejected', reviewed_by=?, reviewed_at=NOW(), review_remarks=? WHERE id=?")->execute([$userId,$remarks,$id]);
        ok(fetchReq($pdo,$id),'Requisition rejected');
    }

    // Issue (if already approved and need secondary issue stamp)
    if($action==='issue'&&$id){
        $chk=$pdo->prepare("SELECT id,status,requested_by FROM requisitions WHERE id=?");$chk->execute([$id]);$r=$chk->fetch(PDO::FETCH_ASSOC);
        if(!$r) err('Not found',404);
        if(!in_array($r['status'],['approved','partial'])) err('Only approved requisitions can be marked as issued',422);
        if($m=ctl_conflict($pdo,'issue stock for',['requested'=>$r['requested_by']])) err($m,403);
        $remarks=trim($input['remarks']??'');
        if(!empty($input['items'])&&is_array($input['items'])){
            foreach($input['items'] as $item){
                $pdo->prepare("UPDATE requisition_items SET quantity_issued=? WHERE id=? AND requisition_id=?")
                    ->execute([(int)$item['quantity_issued'],(int)$item['id'],$id]);
            }
        }
        $pdo->prepare("UPDATE requisitions SET status='issued', issued_by=?, issued_at=NOW(), issue_remarks=? WHERE id=?")->execute([$userId,$remarks,$id]);
        ok(fetchReq($pdo,$id),'Items marked as issued');
    }

    // Create new requisition
    if(empty($action)){
        if(!$userId) err('Not authenticated',401);
        foreach(['store_id','items'] as $f){if(empty($input[$f])) err("$f required",422);}
        if(!is_array($input['items'])||count($input['items'])===0) err('At least one item required',422);

        $targetStoreId = (int)$input['store_id'];
        $stChk = $pdo->prepare("SELECT name FROM stores WHERE id = ?");
        $stChk->execute([$targetStoreId]);
        $storeName = $stChk->fetchColumn() ?: "Store #{$targetStoreId}";

        // Validate available quantity in store for all requested items
        foreach ($input['items'] as $item) {
            $itmId = (int)($item['item_id'] ?? 0);
            $qty = max(1, (int)($item['quantity'] ?? $item['quantity_requested'] ?? 1));
            if ($itmId <= 0) continue;

            $itemInfoStmt = $pdo->prepare("SELECT name FROM items WHERE id = ?");
            $itemInfoStmt->execute([$itmId]);
            $itemName = $itemInfoStmt->fetchColumn() ?: "Item #{$itmId}";

            $stkStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM stock_batches WHERE item_id = ? AND store_id = ? AND is_active = 1 AND quantity > 0");
            $stkStmt->execute([$itmId, $targetStoreId]);
            $availStock = (float)$stkStmt->fetchColumn();

            if ($availStock <= 0) {
                err("Warning: Cannot request '{$itemName}'. Item is completely out of stock in {$storeName} (0 available).", 422);
            }
            if ($qty > $availStock) {
                err("Warning: Cannot request '{$itemName}'. Requested quantity ({$qty}) exceeds available stock in {$storeName} ({$availStock} available).", 422);
            }
        }

        // Generate requisition number
        $cnt=(int)$pdo->query("SELECT COUNT(*) FROM requisitions")->fetchColumn();
        $reqNo='REQ-'.date('Ymd').'-'.str_pad($cnt+1,4,'0',STR_PAD_LEFT);

        $pdo->prepare("INSERT INTO requisitions (requisition_no,requested_by,department_id,store_id,purpose,priority,status) VALUES (?,?,?,?,?,?,'draft')")
            ->execute([$reqNo,$userId,!empty($input['department_id'])?(int)$input['department_id']:null,$targetStoreId,trim($input['purpose']??''),$input['priority']??'normal']);
        $reqId=(int)$pdo->lastInsertId();

        $itemStmt=$pdo->prepare("
            INSERT INTO requisition_items (requisition_id,item_id,quantity_requested,unit_price,notes) 
            VALUES (?,?,?,?,?)
        ");
        foreach($input['items'] as $item){
            $itmId = (int)$item['item_id'];
            $qty = max(1, (int)($item['quantity'] ?? $item['quantity_requested'] ?? 1));
            $uPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0.00;
            if ($uPrice <= 0) {
                $costStmt = $pdo->prepare("
                    SELECT COALESCE(
                        (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = ? AND sb.store_id = ? AND sb.quantity > 0 AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY (sb.expiry_date IS NULL), sb.expiry_date ASC, sb.id ASC LIMIT 1),
                        (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = ? AND sb.store_id = ? AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY sb.id DESC LIMIT 1),
                        (SELECT sb.unit_cost FROM stock_batches sb WHERE sb.item_id = ? AND sb.is_active = 1 AND sb.unit_cost > 0 ORDER BY sb.id DESC LIMIT 1),
                        NULLIF((SELECT unit_price FROM items WHERE id = ?), 0),
                        NULLIF((SELECT selling_price FROM items WHERE id = ?), 0),
                        0
                    )
                ");
                $costStmt->execute([$itmId, $targetStoreId, $itmId, $targetStoreId, $itmId, $itmId, $itmId]);
                $uPrice = (float)$costStmt->fetchColumn();
            }
            $itemStmt->execute([$reqId, $itmId, $qty, $uPrice, trim($item['notes']??'')]);
        }

        // Auto-submit if requested
        if(!empty($input['auto_submit'])){
            $pdo->prepare("UPDATE requisitions SET status='submitted', submitted_at=NOW() WHERE id=?")->execute([$reqId]);
        }

        ok(fetchReq($pdo,$reqId),'Requisition created successfully',201);
    }
}

// ===== DELETE =====
if($method==='DELETE'){
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id,status,requested_by FROM requisitions WHERE id=?");$chk->execute([$id]);$r=$chk->fetch(PDO::FETCH_ASSOC);
    if(!$r) err('Not found',404);
    if(!in_array($r['status'],['draft','rejected'])) err('Only draft or rejected requisitions can be deleted',422);
    $pdo->prepare("DELETE FROM requisition_items WHERE requisition_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM requisitions WHERE id=?")->execute([$id]);
    ok(null,'Requisition deleted');
}

err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
