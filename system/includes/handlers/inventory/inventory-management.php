<?php
// handlers/inventory/inventory-management.php
require_once __DIR__ . "/../../includes/database.php";

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$sessionUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : (isset($_SESSION['logged_user_id']) ? (int)$_SESSION['logged_user_id'] : null));

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = "Success", $s = 200) { jsonResponse(["success" => true, "message" => $m, "data" => $d], $s); }
function error($m, $s = 400) { jsonResponse(["success" => false, "message" => $m], $s); }

/**
 * Normalize raw expiry date input to YYYY-MM-DD or null.
 * Handles ISO dates (YYYY-MM-DD), Year/Month (YYYY-MM -> end of month),
 * Month/Year (MM/YYYY -> end of month), Day/Month/Year (DD/MM/YYYY, DD-MM-YYYY),
 * ISO with time, and strtotime strings.
 */
function normalizeExpiryDate($raw) {
    if ($raw === null || $raw === '') return null;
    $raw = trim((string)$raw);
    if ($raw === '' || strtolower($raw) === 'null' || strtolower($raw) === 'undefined' || $raw === '0000-00-00') {
        return null;
    }
    
    // YYYY-MM-DD (with optional time or T) or YYYY/MM/DD
    if (preg_match('/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})(?:[T\s].*)?$/', $raw, $m)) {
        $y = (int)$m[1];
        $mon = (int)$m[2];
        $d = (int)$m[3];
        if (checkdate($mon, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mon, $d);
        }
    }
    
    // YYYY-MM or YYYY/MM -> default to last day of that month
    if (preg_match('/^(\d{4})[-\/](\d{1,2})$/', $raw, $m)) {
        $y = (int)$m[1];
        $mon = (int)$m[2];
        $d = (int)$m[3];
        if ($mon >= 1 && $mon <= 12) {
            return date('Y-m-t', mktime(0, 0, 0, $mon, 1, $y));
        }
    }
    
    // MM/YYYY or MM-YYYY -> default to last day of that month
    if (preg_match('/^(\d{1,2})[-\/](\d{4})$/', $raw, $m)) {
        $mon = (int)$m[1];
        $y = (int)$m[2];
        if ($mon >= 1 && $mon <= 12) {
            return date('Y-m-t', mktime(0, 0, 0, $mon, 1, $y));
        }
    }
    
    // DD/MM/YYYY or DD-MM-YYYY or DD.MM.YYYY
    if (preg_match('/^(\d{1,2})[-\/\.](\d{1,2})[-\/\.](\d{4})$/', $raw, $m)) {
        $d = (int)$m[1];
        $mon = (int)$m[2];
        $y = (int)$m[3];
        if (checkdate($mon, $d, $y)) {
            return sprintf('%04d-%02d-%02d', $y, $mon, $d);
        }
    }
    
    // strtotime fallback
    $ts = strtotime($raw);
    if ($ts !== false && $ts > 0) {
        $formatted = date('Y-m-d', $ts);
        if ($formatted !== '1970-01-01') {
            return $formatted;
        }
    }
    
    return null;
}

/**
 * Ensure stock_transfer_items table has batch_number and expiry_date columns for full transfer tracking.
 */
function ensureTransferSchema($pdo) {
    static $done = false;
    if ($done) return;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM stock_transfer_items")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('batch_number', $cols)) {
            $pdo->exec("ALTER TABLE stock_transfer_items ADD COLUMN batch_number VARCHAR(100) NULL AFTER batch_id");
        }
        if (!in_array('expiry_date', $cols)) {
            $pdo->exec("ALTER TABLE stock_transfer_items ADD COLUMN expiry_date DATE NULL AFTER batch_number");
        }
        $done = true;
    } catch (Exception $e) {}
}

/**
 * Process stock quantity adjustment with complete journal entries and stock movements.
 */
function processStockAdjustment($pdo, $input, $id, $userId, $sessionUserId) {
    $actualUserId = !empty($userId) ? (int)$userId : (!empty($sessionUserId) ? (int)$sessionUserId : 1);
    
    $batchId = !empty($input['batch_id']) ? (int)$input['batch_id'] : ($id ?: null);
    $itemId = !empty($input['item_id']) ? (int)$input['item_id'] : null;
    $storeId = !empty($input['store_id']) ? (int)$input['store_id'] : null;
    
    if (!isset($input['new_quantity']) || $input['new_quantity'] === '') {
        error("new_quantity required", 422);
    }
    $newQty = (float)$input['new_quantity'];
    if ($newQty < 0) {
        error("Quantity cannot be negative", 422);
    }

    $reason = trim($input['reason'] ?? 'Physical count reconciliation');
    if (!$reason) $reason = 'Physical count reconciliation';
    $notes = trim($input['notes'] ?? '');
    
    $entryDate = !empty($input['entry_date']) ? trim($input['entry_date']) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
        $entryDate = date('Y-m-d');
    }
    $customAccountId = !empty($input['adjustment_account_id']) ? (int)$input['adjustment_account_id'] : null;

    $pdo->beginTransaction();
    try {
        $oldQty = 0;
        $batchNumber = '';
        $cost = 0;
        $itemName = '';
        $storeName = '';

        if ($batchId) {
            $stmt = $pdo->prepare("
                SELECT sb.*, i.name as item_name, i.item_code, i.unit_price as item_unit_price, s.name as store_name
                FROM stock_batches sb
                JOIN items i ON i.id = sb.item_id
                LEFT JOIN stores s ON s.id = sb.store_id
                WHERE sb.id = ? AND sb.is_active = 1 FOR UPDATE
            ");
            $stmt->execute([$batchId]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$batch) error("Stock batch not found", 404);

            $oldQty = (float)$batch['quantity'];
            $itemId = (int)$batch['item_id'];
            $storeId = (int)$batch['store_id'];
            $batchNumber = $batch['batch_number'] ?: ('BATCH-' . $batch['id']);
            $itemName = $batch['item_name'];
            $storeName = $batch['store_name'] ?: ('Store #' . $storeId);

            if (isset($input['unit_cost']) && (float)$input['unit_cost'] > 0) {
                $cost = (float)$input['unit_cost'];
            } elseif ((float)$batch['unit_cost'] > 0) {
                $cost = (float)$batch['unit_cost'];
            } else {
                $cost = (float)($batch['item_unit_price'] ?? 0);
            }
        } else {
            if (!$itemId || !$storeId) {
                error("item_id and store_id required when no batch_id is provided", 422);
            }

            $stmt = $pdo->prepare("SELECT i.*, s.name as store_name FROM items i, stores s WHERE i.id = ? AND s.id = ? AND i.is_active = 1");
            $stmt->execute([$itemId, $storeId]);
            $itemRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$itemRow) error("Item or store not found", 404);

            $itemName = $itemRow['name'];
            $storeName = $itemRow['store_name'] ?: ('Store #' . $storeId);

            // Check if there is an existing active batch for this item in this store
            $chkBatch = $pdo->prepare("
                SELECT sb.* FROM stock_batches sb 
                WHERE sb.item_id = ? AND sb.store_id = ? AND sb.is_active = 1 
                ORDER BY (sb.quantity > 0) DESC, sb.id DESC LIMIT 1 FOR UPDATE
            ");
            $chkBatch->execute([$itemId, $storeId]);
            $existingBatch = $chkBatch->fetch(PDO::FETCH_ASSOC);

            if ($existingBatch && empty($input['create_new_batch'])) {
                $batchId = (int)$existingBatch['id'];
                $oldQty = (float)$existingBatch['quantity'];
                $batchNumber = $existingBatch['batch_number'] ?: ('BATCH-' . $batchId);
                if (isset($input['unit_cost']) && (float)$input['unit_cost'] > 0) {
                    $cost = (float)$input['unit_cost'];
                } elseif ((float)$existingBatch['unit_cost'] > 0) {
                    $cost = (float)$existingBatch['unit_cost'];
                } else {
                    $cost = (float)($itemRow['unit_price'] ?? 0);
                }
            } else {
                $oldQty = 0;
                $batchNumber = trim($input['batch_number'] ?? '') ?: ('ADJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
                $expiryDate = normalizeExpiryDate($input['expiry_date'] ?? null);
                $cost = isset($input['unit_cost']) && (float)$input['unit_cost'] > 0 ? (float)$input['unit_cost'] : (float)($itemRow['unit_price'] ?? 0);
            }
        }

        $diff = $newQty - $oldQty;
        if ($diff == 0) {
            $pdo->commit();
            success([
                'batch_id' => $batchId,
                'new_quantity' => $newQty,
                'diff' => 0
            ], "Quantity unchanged. No adjustment required.");
        }

        $moveQty = abs($diff);
        $totalAdjValue = round($moveQty * $cost, 2);
        $type = $diff > 0 ? "addition" : "reduction";

        // 1. Update or create stock_batches
        if ($batchId) {
            $pdo->prepare("UPDATE stock_batches SET quantity = ?, unit_cost = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newQty, $cost, $batchId]);
        } else {
            $sellingPrice = (float)($itemRow['selling_price'] ?? $cost);
            $insBatch = $pdo->prepare("
                INSERT INTO stock_batches 
                (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, is_active, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            $insBatch->execute([$itemId, $storeId, $batchNumber, $newQty, $cost, $sellingPrice, $expiryDate ?? null, $entryDate, $actualUserId]);
            $batchId = (int)$pdo->lastInsertId();
        }

        // 2. Create Journal Entry
        $jeId = null;
        $ref = null;

        if ($totalAdjValue > 0) {
            $ref = 'ADJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $changeSymbol = $diff > 0 ? "+{$moveQty}" : "-{$moveQty}";
            $jeDesc = "Stock Adjustment: {$itemName} ({$storeName}) [{$changeSymbol} units @ " . number_format($cost, 0) . " RWF]. Reason: {$reason}";
            if ($notes) $jeDesc .= " | Notes: {$notes}";

            // Resolve offsetting account in Chart of Accounts
            $offsetAccountId = null;
            if ($customAccountId) {
                $chk = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE id = ? AND status = 'active'");
                $chk->execute([$customAccountId]);
                $offsetAccountId = $chk->fetchColumn();
            }

            if (!$offsetAccountId) {
                if ($diff > 0) {
                    // Addition / Surplus
                    $qAcc = $pdo->query("SELECT id FROM chart_of_accounts WHERE (account_name LIKE '%stock adjust%' OR account_name LIKE '%inventory adjust%' OR account_name LIKE '%stock gain%' OR account_name LIKE '%inventory surplus%') AND status='active' LIMIT 1");
                    $offsetAccountId = $qAcc ? $qAcc->fetchColumn() : null;
                    if (!$offsetAccountId) {
                        if (stripos($reason, 'opening') !== false || stripos($reason, 'initial') !== false) {
                            $offsetAccountId = 77; // Opening equity balance
                        } else {
                            $offsetAccountId = 44; // Other Income
                        }
                    }
                } else {
                    // Reduction / Loss
                    $qAcc = $pdo->query("SELECT id FROM chart_of_accounts WHERE (account_name LIKE '%stock adjust%' OR account_name LIKE '%inventory adjust%' OR account_name LIKE '%stock loss%' OR account_name LIKE '%inventory shrinkage%') AND status='active' LIMIT 1");
                    $offsetAccountId = $qAcc ? $qAcc->fetchColumn() : null;
                    if (!$offsetAccountId) {
                        if (stripos($reason, 'expir') !== false) {
                            $offsetAccountId = 1021; // Drug expiration expenses
                        } elseif (stripos($reason, 'damage') !== false || stripos($reason, 'loss') !== false || stripos($reason, 'shrinkage') !== false) {
                            $offsetAccountId = 1385; // Drug additional loss
                        } else {
                            $offsetAccountId = 1385; // Drug additional loss
                        }
                    }
                }
            }

            // Fallback verification
            if ($offsetAccountId) {
                $chkAcc = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE id = ?");
                $chkAcc->execute([$offsetAccountId]);
                if (!$chkAcc->fetchColumn()) {
                    $offsetAccountId = ($diff > 0) ? 44 : 1054;
                }
            } else {
                $offsetAccountId = ($diff > 0) ? 44 : 1054;
            }

            // Create Journal Entry Header
            $jeStmt = $pdo->prepare("
                INSERT INTO journal_entries 
                (entry_date, reference, description, reference_type, reference_id, created_by, posted, posted_at, created_at)
                VALUES (?, ?, ?, 'inventory_adjustment', ?, ?, 1, NOW(), NOW())
            ");
            $jeStmt->execute([$entryDate, $ref, $jeDesc, $batchId, $actualUserId]);
            $jeId = (int)$pdo->lastInsertId();

            // Store inventory account ID is $storeId (stores.id = chart_of_accounts.id)
            $storeAccountId = $storeId;
            $lineDesc = "Stock adj ({$changeSymbol} units) {$itemName}";

            if ($diff > 0) {
                // Stock gained: Debit Store Inventory Asset (+), Credit Offset Account (Gain/Income/Equity +)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                    ->execute([$jeId, $storeAccountId, $lineDesc, $totalAdjValue]);
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, 0, ?)")
                    ->execute([$jeId, $offsetAccountId, $lineDesc, $totalAdjValue]);
            } else {
                // Stock reduced: Debit Offset Account (Loss/Expense +), Credit Store Inventory Asset (-)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                    ->execute([$jeId, $offsetAccountId, $lineDesc, $totalAdjValue]);
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, 0, ?)")
                    ->execute([$jeId, $storeAccountId, $lineDesc, $totalAdjValue]);
            }
        }

        // 3. Insert Stock Movement
        $fromStore = $diff < 0 ? $storeId : null;
        $toStore   = $diff > 0 ? $storeId : null;
        $movementNotes = "Manual adjustment ({$type}): {$oldQty} -> {$newQty}. Reason: {$reason}";
        if ($ref) $movementNotes .= ". Journal Ref: {$ref}";
        if ($notes) $movementNotes .= " | {$notes}";

        $pdo->prepare("
            INSERT INTO stock_movements 
            (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, 'adjustment', ?, ?, 'inventory_adjustment', ?, ?, ?, NOW())
        ")->execute([$itemId, $batchId, $fromStore, $toStore, $moveQty, $cost, $jeId, $movementNotes, $actualUserId]);

        $pdo->commit();

        $respMsg = "Stock adjusted successfully ({$type}: " . ($diff > 0 ? "+{$moveQty}" : "-{$moveQty}") . " units).";
        if ($ref) {
            $respMsg .= " Journal entry {$ref} posted (" . number_format($totalAdjValue, 0) . " RWF).";
        }

        success([
            'batch_id' => $batchId,
            'item_id' => $itemId,
            'store_id' => $storeId,
            'old_quantity' => $oldQty,
            'new_quantity' => $newQty,
            'difference' => $diff,
            'unit_cost' => $cost,
            'total_adjustment_value' => $totalAdjValue,
            'journal_entry_id' => $jeId,
            'journal_reference' => $ref,
            'reason' => $reason
        ], $respMsg);

    } catch (Exception $e) {
        $pdo->rollBack();
        error("Adjustment failed: " . $e->getMessage(), 500);
    }
}

/**
 * Revert an item consumption action:
 * - Restores stock back into the batch/store
 * - Creates a reversing stock movement audit record (reference_type='consumption_reversal')
 * - Marks the original movement record as [REVERTED]
 * - Posts a reversing double-entry journal transaction to the General Ledger (Dr Store Inventory Asset, Cr Expense/COGS)
 */
function processRevertConsumption($pdo, $input, $userId, $sessionUserId) {
    $actualUserId = !empty($userId) ? (int)$userId : (!empty($sessionUserId) ? (int)$sessionUserId : 1);
    $movementId = !empty($input['movement_id']) ? (int)$input['movement_id'] : 0;
    $userReason = trim($input['reason'] ?? '');

    if (!$movementId) {
        error("movement_id is required", 422);
    }

    $pdo->beginTransaction();
    try {
        // 1. Lock and load the original stock movement
        $smStmt = $pdo->prepare("
            SELECT sm.*, i.name as item_name, i.item_code, sb.batch_number, sb.expiry_date
            FROM stock_movements sm
            JOIN items i ON i.id = sm.item_id
            LEFT JOIN stock_batches sb ON sb.id = sm.batch_id
            WHERE sm.id = ? FOR UPDATE
        ");
        $smStmt->execute([$movementId]);
        $sm = $smStmt->fetch();

        if (!$sm) {
            $pdo->rollBack();
            error("Stock movement #$movementId not found", 404);
        }

        // 2. Validate type: must be consumption
        if ($sm['movement_type'] !== 'consumption' && $sm['reference_type'] !== 'consumption') {
            $pdo->rollBack();
            error("Movement #$movementId is not a consumption movement (Type: {$sm['movement_type']}) and cannot be reverted with this action", 422);
        }

        // 3. Check if already reverted
        $chkStmt = $pdo->prepare("SELECT id FROM stock_movements WHERE reference_type = 'consumption_reversal' AND reference_id = ?");
        $chkStmt->execute([$movementId]);
        if ($chkStmt->fetch()) {
            $pdo->rollBack();
            error("Consumption movement #$movementId has already been reverted.", 409);
        }
        if (strpos($sm['notes'] ?? '', '[REVERTED') !== false) {
            $pdo->rollBack();
            error("Consumption movement #$movementId is already marked as reverted.", 409);
        }

        $itemId = (int)$sm['item_id'];
        $storeId = (int)$sm['from_store_id'];
        $batchId = !empty($sm['batch_id']) ? (int)$sm['batch_id'] : null;
        $qtyToRestore = (float)$sm['quantity'];
        $unitCost = (float)$sm['unit_cost'];
        $costAmount = round($qtyToRestore * $unitCost, 2);

        if ($qtyToRestore <= 0) {
            $pdo->rollBack();
            error("Invalid consumption quantity ($qtyToRestore) to revert.", 422);
        }

        // 4. Restore Stock in stock_batches
        $targetBatchId = null;
        if ($batchId) {
            $bStmt = $pdo->prepare("SELECT id, quantity, is_active FROM stock_batches WHERE id = ? FOR UPDATE");
            $bStmt->execute([$batchId]);
            $b = $bStmt->fetch();
            if ($b) {
                $newBatchQty = (float)$b['quantity'] + $qtyToRestore;
                $pdo->prepare("UPDATE stock_batches SET quantity = ?, is_active = 1 WHERE id = ?")->execute([$newBatchQty, $batchId]);
                $targetBatchId = $batchId;
            }
        }

        // If batch not found, find another active batch in this store or create one
        if (!$targetBatchId) {
            $bMatch = $pdo->prepare("SELECT id, quantity FROM stock_batches WHERE item_id = ? AND store_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $bMatch->execute([$itemId, $storeId]);
            $existingBatch = $bMatch->fetch();
            if ($existingBatch) {
                $newBatchQty = (float)$existingBatch['quantity'] + $qtyToRestore;
                $pdo->prepare("UPDATE stock_batches SET quantity = ?, is_active = 1 WHERE id = ?")->execute([$newBatchQty, $existingBatch['id']]);
                $targetBatchId = (int)$existingBatch['id'];
            } else {
                $bNum = !empty($sm['batch_number']) ? $sm['batch_number'] : ('BATCH-' . date('Ymd'));
                $expDate = !empty($sm['expiry_date']) ? $sm['expiry_date'] : null;
                $insB = $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, expiry_date, received_date, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,CURDATE(),1,?,NOW())");
                $insB->execute([$itemId, $storeId, $bNum, $qtyToRestore, $unitCost, $expDate, $actualUserId]);
                $targetBatchId = (int)$pdo->lastInsertId();
            }
        }

        // 5. Create Reversal Record in stock_movements
        $reversalNote = "Reversal of Consumption Movement #$movementId" . ($userReason ? ": $userReason" : "");
        $insMove = $pdo->prepare("
            INSERT INTO stock_movements 
            (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at) 
            VALUES (?, ?, NULL, ?, 'return', ?, ?, 'consumption_reversal', ?, ?, ?, NOW())
        ");
        $insMove->execute([$itemId, $targetBatchId, $storeId, $qtyToRestore, $unitCost, $movementId, $reversalNote, $actualUserId]);
        $revMovementId = (int)$pdo->lastInsertId();

        // 6. Mark Original Movement as Reverted
        $updatedOrigNotes = trim(($sm['notes'] ?? '') . " [REVERTED by Movement #$revMovementId on " . date('Y-m-d H:i') . "]");
        $pdo->prepare("UPDATE stock_movements SET notes = ? WHERE id = ?")->execute([$updatedOrigNotes, $movementId]);

        // 7. FINANCIAL ACCOUNTING: Reverse Journal Entries
        // Look for existing journal entry created by this consumption
        $origJe = null;
        if (!empty($sm['reference_id'])) {
            $jeStmt = $pdo->prepare("SELECT * FROM journal_entries WHERE id = ?");
            $jeStmt->execute([(int)$sm['reference_id']]);
            $origJe = $jeStmt->fetch();
        }

        // If not found by reference_id, search by reference pattern in notes e.g. CON-20260909-XXXX
        if (!$origJe && !empty($sm['notes']) && preg_match('/(CON-\d{8}-[A-Z0-9]+)/i', $sm['notes'], $mRef)) {
            $jeStmt = $pdo->prepare("SELECT * FROM journal_entries WHERE reference = ? LIMIT 1");
            $jeStmt->execute([$mRef[1]]);
            $origJe = $jeStmt->fetch();
        }

        // If still not found, search by item_id, reference_type='consumption' around movement creation time
        if (!$origJe) {
            $jeStmt = $pdo->prepare("
                SELECT * FROM journal_entries 
                WHERE reference_type = 'consumption' 
                  AND reference_id = ? 
                  AND ABS(TIMESTAMPDIFF(MINUTE, created_at, ?)) <= 120
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) ASC LIMIT 1
            ");
            $jeStmt->execute([$itemId, $sm['created_at'], $sm['created_at']]);
            $origJe = $jeStmt->fetch();
        }

        $revRef = 'REV-CON-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $revDesc = "Reversal of Consumption (Movement #$movementId): " . $sm['item_name'] . " x" . $qtyToRestore . ($userReason ? " - $userReason" : "");
        $createdJournalEntries = [];

        if ($origJe) {
            // Find lines of the original journal entry
            $jlStmt = $pdo->prepare("SELECT * FROM journal_entry_lines WHERE journal_entry_id = ?");
            $jlStmt->execute([$origJe['id']]);
            $origLines = $jlStmt->fetchAll();

            if (!empty($origLines)) {
                $targetRevRef = 'REV-' . $origJe['reference'];
                $insJe = $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, created_by, posted, posted_at, created_at) VALUES (CURDATE(), ?, ?, 'consumption_reversal', ?, ?, 1, NOW(), NOW())");
                $insJe->execute([$targetRevRef, $revDesc, $movementId, $actualUserId]);
                $newJeId = (int)$pdo->lastInsertId();
                $createdJournalEntries[] = $targetRevRef;

                $insLine = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, ?)");
                foreach ($origLines as $l) {
                    $revLineDesc = "Reversal: " . $l['line_description'];
                    // Swap Debit and Credit
                    $insLine->execute([$newJeId, $l['account_id'], $revLineDesc, (float)$l['credit'], (float)$l['debit']]);
                }

                // Check for associated Income entry e.g. REF-INC
                $incRef = $origJe['reference'] . '-INC';
                $jeIncStmt = $pdo->prepare("SELECT * FROM journal_entries WHERE reference = ? LIMIT 1");
                $jeIncStmt->execute([$incRef]);
                $incJe = $jeIncStmt->fetch();
                if ($incJe) {
                    $jlIncStmt = $pdo->prepare("SELECT * FROM journal_entry_lines WHERE journal_entry_id = ?");
                    $jlIncStmt->execute([$incJe['id']]);
                    $incLines = $jlIncStmt->fetchAll();
                    if (!empty($incLines)) {
                        $targetIncRevRef = 'REV-' . $incRef;
                        $insJe->execute([$targetIncRevRef, $revDesc . ' (Income Reversal)', $movementId, $actualUserId]);
                        $newIncJeId = (int)$pdo->lastInsertId();
                        $createdJournalEntries[] = $targetIncRevRef;
                        foreach ($incLines as $il) {
                            $revLineDesc = "Reversal: " . $il['line_description'];
                            $insLine->execute([$newIncJeId, $il['account_id'], $revLineDesc, (float)$il['credit'], (float)$il['debit']]);
                        }
                    }
                }
            }
        }

        // If no original journal entry was found or lines were empty, create standard balanced financial reversal entry:
        if (empty($createdJournalEntries) && $costAmount > 0) {
            $insJe = $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, created_by, posted, posted_at, created_at) VALUES (CURDATE(), ?, ?, 'consumption_reversal', ?, ?, 1, NOW(), NOW())");
            $insJe->execute([$revRef, $revDesc, $movementId, $actualUserId]);
            $newJeId = (int)$pdo->lastInsertId();
            $createdJournalEntries[] = $revRef;

            $lineDesc = $sm['item_name'] . " x" . $qtyToRestore . " consumption reversal";
            $expenseAccount = 1054; // Medical Expenses
            $accChk = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE id = 1054");
            $accChk->execute();
            if (!$accChk->fetch()) {
                $accChk = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE id = 1046");
                $accChk->execute();
                if ($accChk->fetch()) $expenseAccount = 1046;
                else $expenseAccount = 44;
            }

            $insLine = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?, ?, ?, ?, ?)");
            // Dr: Store Inventory Asset (Restoring inventory asset value)
            $insLine->execute([$newJeId, $storeId, $lineDesc . " (Dr Store Inventory)", $costAmount, 0]);
            // Cr: Expense / COGS (Reversing the expense)
            $insLine->execute([$newJeId, $expenseAccount, $lineDesc . " (Cr Expense Reversal)", 0, $costAmount]);
        }

        $pdo->commit();

        $refListStr = !empty($createdJournalEntries) ? implode(', ', $createdJournalEntries) : $revRef;
        success([
            'movement_id' => $movementId,
            'reversal_movement_id' => $revMovementId,
            'restored_quantity' => $qtyToRestore,
            'restored_cost' => $costAmount,
            'journal_references' => $createdJournalEntries
        ], "Consumption movement #$movementId reverted successfully. Stock of $qtyToRestore restored. Journal entry ($refListStr) posted to General Ledger.");
    } catch (Exception $e) {
        $pdo->rollBack();
        error("Failed to revert consumption: " . $e->getMessage(), 500);
    }
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$action = $_GET["action"] ?? "";
$id     = isset($_GET["id"]) ? (int)$_GET["id"] : null;

try {

// ============================================
// GET
// ============================================
if ($method === "GET") {

    // === ITEM CATEGORIES ===
    if ($action === "categories") {
        $parentId = isset($_GET["parent_id"]) ? (int)$_GET["parent_id"] : null;
        if ($parentId) {
            $stmt = $pdo->prepare("SELECT * FROM item_categories WHERE parent_id=? AND is_active=1 ORDER BY name");
            $stmt->execute([$parentId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM item_categories WHERE is_active=1 ORDER BY name");
        }
        success($stmt->fetchAll());
    }

    // === UOMS ===
    if ($action === "uoms") {
        $stmt = $pdo->query("SELECT * FROM item_uoms WHERE is_active=1 ORDER BY name");
        success($stmt->fetchAll());
    }

    // === ITEMS ===
    // Updated 2026-08-26 - Added total_stock from stock_batches
    if ($action === "items" || $action === "items_v2") {
        $categoryId = isset($_GET["category_id"]) ? (int)$_GET["category_id"] : null;
        $activeOnly = isset($_GET["active"]) ? $_GET["active"] === "true" || $_GET["active"] === "1" : false;
        $medication = isset($_GET["medication"]) ? $_GET["medication"] === "true" || $_GET["medication"] === "1" : null;
        
        $sql = "SELECT i.*, ic.name as category_name, ic.code as category_code, iu.name as uom_name, iu.abbreviation as uom_abbr,
                (SELECT COALESCE(SUM(sb.quantity), 0)
                 FROM stock_batches sb 
                 WHERE sb.item_id = i.id AND sb.is_active = 1) as total_stock
                FROM items i 
                LEFT JOIN item_categories ic ON ic.id=i.category_id 
                LEFT JOIN item_uoms iu ON iu.id=i.uom_id 
                WHERE 1=1";
        $params = [];
        if ($categoryId) { $sql .= " AND i.category_id=?"; $params[] = $categoryId; }
        if ($activeOnly) { $sql .= " AND i.is_active=1"; }
        if ($medication !== null) { $sql .= " AND i.is_medication=?"; $params[] = $medication ? 1 : 0; }
        $sql .= " ORDER BY i.name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add debug info
        $debug_info = [
            'sql_length' => strlen($sql),
            'result_count' => count($result),
            'first_item_keys' => count($result) > 0 ? array_keys($result[0]) : [],
            'has_total_stock' => count($result) > 0 && isset($result[0]['total_stock'])
        ];
        
        jsonResponse(["success" => true, "message" => "Success", "data" => $result, "debug" => $debug_info], 200);
    }

    // === ITEM STOCK DETAILS (by store, batch, expiry) ===
    if ($action === "item_stock_details") {
        $itemId = (int)($_GET['item_id'] ?? 0);
        if (!$itemId) error("item_id required", 422);
        
        $includeZero = isset($_GET['include_zero']) ? ($_GET['include_zero'] === '1' || $_GET['include_zero'] === 'true') : true;
        
        $sql = "
            SELECT 
                sb.id,
                sb.item_id,
                sb.store_id,
                sb.batch_number,
                sb.quantity,
                sb.expiry_date,
                sb.unit_cost,
                sb.selling_price,
                sb.received_date,
                sb.location_rack,
                sb.updated_at as last_updated,
                s.name as store_name,
                s.code as store_code
            FROM stock_batches sb
            LEFT JOIN stores s ON s.id = sb.store_id
            WHERE sb.item_id = ? AND sb.is_active = 1";
            
        if (!$includeZero) {
            $sql .= " AND sb.quantity > 0";
        }
        
        $sql .= " ORDER BY (sb.quantity > 0) DESC, s.name ASC, CASE WHEN sb.expiry_date IS NULL THEN 1 ELSE 0 END, sb.expiry_date ASC, sb.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$itemId]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also fetch per-store summaries for this item across all stores
        $storeSummaryStmt = $pdo->prepare("
            SELECT s.id as store_id, s.name as store_name, s.code as store_code,
                   COALESCE(SUM(sb.quantity), 0) as current_stock,
                   COUNT(CASE WHEN sb.quantity > 0 THEN 1 END) as active_batches_count,
                   COUNT(sb.id) as total_batches_count
            FROM stores s
            LEFT JOIN stock_batches sb ON sb.store_id = s.id AND sb.item_id = ? AND sb.is_active = 1
            WHERE s.is_active = 1
            GROUP BY s.id, s.name, s.code
            ORDER BY current_stock DESC, s.name ASC
        ");
        $storeSummaryStmt->execute([$itemId]);
        $storeSummaries = $storeSummaryStmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            "success" => true,
            "message" => "Success",
            "data" => $batches,
            "stores" => $storeSummaries
        ], 200);
    }

    // === SINGLE ITEM ===
    if ($action === "item_detail" && $id) {
        $stmt = $pdo->prepare("SELECT i.*, ic.name as category_name, ic.code as category_code, iu.name as uom_name, iu.abbreviation as uom_abbr FROM items i LEFT JOIN item_categories ic ON ic.id=i.category_id LEFT JOIN item_uoms iu ON iu.id=i.uom_id WHERE i.id=?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) error("Item not found", 404);
        success($item);
    }

    // === ITEM INSURANCE PRICES ===
    if ($action === "item_insurance_prices") {
        $itemId = (int)($_GET['item_id'] ?? 0);
        if (!$itemId) error("item_id required", 422);
        try {
            $stmt = $pdo->prepare("SELECT iip.*, ic.name as insurance_name FROM item_insurance_prices iip LEFT JOIN insurance_companies ic ON ic.id=iip.insurance_id WHERE iip.item_id=?");
            $stmt->execute([$itemId]);
            success($stmt->fetchAll());
        } catch(Exception $e) { success([]); }
    }

    // Bulk insurance prices by insurance company
    if ($action === "insurance_prices") {
        $insuranceId = (int)($_GET['insurance_id'] ?? 0);
        if (!$insuranceId) error("insurance_id required", 422);
        try {
            $stmt = $pdo->prepare("SELECT iip.item_id, iip.price, iip.additional_insurance_selling_price, iip.is_exceptional, iip.insurance_pays, iip.patient_topup FROM item_insurance_prices iip WHERE iip.insurance_id=?");
            $stmt->execute([$insuranceId]);
            success($stmt->fetchAll());
        } catch(Exception $e) { success([]); }
    }

    // === STORES ===
    if ($action === "stores") {
        $stmt = $pdo->query("SELECT * FROM stores WHERE is_active=1 ORDER BY name");
        success($stmt->fetchAll());
    }

    // === STOCK BY STORE ===
    if ($action === "stock") {
        $storeId = isset($_GET["store_id"]) ? (int)$_GET["store_id"] : null;
        $itemId = isset($_GET["item_id"]) ? (int)$_GET["item_id"] : null;
        $lowStockOnly = isset($_GET["low_stock"]) ? $_GET["low_stock"] === "true" || $_GET["low_stock"] === "1" : false;
        $expiringOnly = isset($_GET["expiring"]) ? (int)$_GET["expiring"] : 0;

        $sql = "SELECT sb.*, i.name as item_name, i.item_code, i.generic_name, i.is_medication, i.is_controlled,
                ic.name as category_name, iu.abbreviation as uom_abbr, s.name as store_name, s.code as store_code
                FROM stock_batches sb 
                JOIN items i ON i.id=sb.item_id 
                LEFT JOIN item_categories ic ON ic.id=i.category_id
                LEFT JOIN item_uoms iu ON iu.id=i.uom_id
                JOIN stores s ON s.id=sb.store_id
                WHERE sb.is_active=1 AND i.is_active=1";
        $params = [];
        if ($storeId) { $sql .= " AND sb.store_id=?"; $params[] = $storeId; }
        if ($itemId) { $sql .= " AND sb.item_id=?"; $params[] = $itemId; }
        if ($lowStockOnly) { $sql .= " AND sb.quantity <= sb.reorder_level AND sb.reorder_level > 0"; }
        if ($expiringOnly > 0) { $sql .= " AND sb.expiry_date IS NOT NULL AND sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) AND sb.quantity > 0"; $params[] = $expiringOnly; }
        $sql .= " ORDER BY i.name, sb.expiry_date ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }
    // === CONSOLIDATED STOCK (sum by item across stores) ===
    if ($action === "stock_summary") {
        $sql = "SELECT i.id as item_id, i.name as item_name, i.item_code, i.generic_name, i.is_medication, i.is_controlled,
                ic.name as category_name, iu.abbreviation as uom_abbr,
                COALESCE(SUM(sb.quantity),0) as total_quantity,
                MIN(sb.expiry_date) as nearest_expiry,
                MAX(sb.reorder_level) as reorder_level,
                MAX(sb.min_stock_level) as min_stock_level,
                COUNT(DISTINCT sb.store_id) as store_count
                FROM items i 
                LEFT JOIN stock_batches sb ON sb.item_id=i.id AND sb.is_active=1
                LEFT JOIN item_categories ic ON ic.id=i.category_id
                LEFT JOIN item_uoms iu ON iu.id=i.uom_id
                WHERE i.is_active=1
                GROUP BY i.id
                ORDER BY i.name";
        $stmt = $pdo->query($sql);
        success($stmt->fetchAll());
    }

    // === STOCK MOVEMENTS ===
    if ($action === "movements") {
        $itemId = isset($_GET["item_id"]) && $_GET["item_id"] !== '' ? (int)$_GET["item_id"] : null;
        $storeId = isset($_GET["store_id"]) && $_GET["store_id"] !== '' ? (int)$_GET["store_id"] : null;
        $batchId = isset($_GET["batch_id"]) && $_GET["batch_id"] !== '' ? (int)$_GET["batch_id"] : null;
        $type = $_GET["type"] ?? null;
        $from = $_GET["from"] ?? null;
        $to = $_GET["to"] ?? null;
        $q = isset($_GET["q"]) ? trim($_GET["q"]) : '';
        $limit = isset($_GET["limit"]) ? min((int)$_GET["limit"], 1000) : 250;
        
        $sql = "SELECT sm.*, i.name as item_name, i.item_code, i.generic_name,
                iu.abbreviation as uom_abbr, iu.name as uom_name,
                sb.batch_number, sb.expiry_date,
                fs.name as from_store_name, ts.name as to_store_name,
                sm.created_at as movement_date,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), u.username) as created_by_name,
                (SELECT COUNT(*) FROM stock_movements rev WHERE rev.reference_type = 'consumption_reversal' AND rev.reference_id = sm.id) as is_reverted
                FROM stock_movements sm 
                JOIN items i ON i.id=sm.item_id
                LEFT JOIN item_uoms iu ON iu.id=i.uom_id
                LEFT JOIN stock_batches sb ON sb.id=sm.batch_id
                LEFT JOIN stores fs ON fs.id=sm.from_store_id
                LEFT JOIN stores ts ON ts.id=sm.to_store_id
                LEFT JOIN users u ON u.id=sm.created_by
                WHERE 1=1";
        $params = [];
        if ($itemId) { $sql .= " AND sm.item_id=?"; $params[] = $itemId; }
        if ($batchId) { $sql .= " AND sm.batch_id=?"; $params[] = $batchId; }
        if ($storeId) { $sql .= " AND (sm.from_store_id=? OR sm.to_store_id=?)"; $params[] = $storeId; $params[] = $storeId; }
        if ($type) { $sql .= " AND sm.movement_type=?"; $params[] = $type; }
        if ($from) { $sql .= " AND DATE(sm.created_at) >= ?"; $params[] = $from; }
        if ($to) { $sql .= " AND DATE(sm.created_at) <= ?"; $params[] = $to; }
        if ($q !== '') {
            $sql .= " AND (i.name LIKE ? OR i.item_code LIKE ? OR sb.batch_number LIKE ? OR sm.notes LIKE ? OR sm.reference_type LIKE ?)";
            $wild = "%$q%";
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
            $params[] = $wild;
        }
        $sql .= " ORDER BY sm.created_at DESC, sm.id DESC LIMIT ?";
        $params[] = $limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // === PREVIEW REVERT CONSUMPTION ===
    if ($action === "preview_revert_consumption") {
        $movementId = (int)($_GET['movement_id'] ?? 0);
        if (!$movementId) error("movement_id required", 422);

        $stmt = $pdo->prepare("
            SELECT sm.*, i.name as item_name, i.item_code, sb.batch_number, sb.expiry_date,
                   fs.name as from_store_name,
                   (SELECT COUNT(*) FROM stock_movements rev WHERE rev.reference_type = 'consumption_reversal' AND rev.reference_id = sm.id) as is_reverted
            FROM stock_movements sm
            JOIN items i ON i.id = sm.item_id
            LEFT JOIN stock_batches sb ON sb.id = sm.batch_id
            LEFT JOIN stores fs ON fs.id = sm.from_store_id
            WHERE sm.id = ?
        ");
        $stmt->execute([$movementId]);
        $sm = $stmt->fetch();
        if (!$sm) error("Stock movement not found", 404);

        $isReverted = ($sm['is_reverted'] > 0) || (strpos($sm['notes'] ?? '', '[REVERTED') !== false);

        $origJe = null;
        if (!empty($sm['reference_id'])) {
            $jeStmt = $pdo->prepare("SELECT id, reference, description, entry_date FROM journal_entries WHERE id = ?");
            $jeStmt->execute([(int)$sm['reference_id']]);
            $origJe = $jeStmt->fetch();
        }
        if (!$origJe && !empty($sm['notes']) && preg_match('/(CON-\d{8}-[A-Z0-9]+)/i', $sm['notes'], $mRef)) {
            $jeStmt = $pdo->prepare("SELECT id, reference, description, entry_date FROM journal_entries WHERE reference = ? LIMIT 1");
            $jeStmt->execute([$mRef[1]]);
            $origJe = $jeStmt->fetch();
        }
        if (!$origJe) {
            $jeStmt = $pdo->prepare("
                SELECT id, reference, description, entry_date FROM journal_entries 
                WHERE reference_type = 'consumption' 
                  AND reference_id = ? 
                  AND ABS(TIMESTAMPDIFF(MINUTE, created_at, ?)) <= 120
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) ASC LIMIT 1
            ");
            $jeStmt->execute([$sm['item_id'], $sm['created_at'], $sm['created_at']]);
            $origJe = $jeStmt->fetch();
        }

        $lines = [];
        if ($origJe) {
            $jlStmt = $pdo->prepare("
                SELECT jel.*, coa.account_name, coa.account_code, coa.account_type
                FROM journal_entry_lines jel
                LEFT JOIN chart_of_accounts coa ON coa.id = jel.account_id
                WHERE jel.journal_entry_id = ?
            ");
            $jlStmt->execute([$origJe['id']]);
            $lines = $jlStmt->fetchAll();
        }

        $costAmount = round((float)$sm['quantity'] * (float)$sm['unit_cost'], 2);

        success([
            'movement' => $sm,
            'is_reverted' => $isReverted,
            'cost_amount' => $costAmount,
            'original_journal' => $origJe,
            'original_lines' => $lines
        ]);
    }

    // === SUPPLIERS ===
    if ($action === "suppliers") {
        $stmt = $pdo->query("SELECT * FROM suppliers WHERE status='active' ORDER BY company_name");
        success($stmt->fetchAll());
    }

    // === SINGLE SUPPLIER ===
    if ($action === "supplier_detail" && $id) {
        $stmt = $pdo->prepare("SELECT s.*, 
            (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=s.id) as total_orders,
            (SELECT COALESCE(SUM(total_amount),0) FROM purchase_orders WHERE supplier_id=s.id AND status IN ('confirmed','partially_received','received')) as total_spent
            FROM suppliers s WHERE s.id=?");
        $stmt->execute([$id]);
        $supplier = $stmt->fetch();
        if (!$supplier) error("Supplier not found", 404);
        success($supplier);
    }

    // === PURCHASE REQUESTS ===
    if ($action === "purchase_requests") {
        $status = $_GET["status"] ?? null;
        $sql = "SELECT pr.*, 
                (SELECT COUNT(*) FROM purchase_request_items WHERE pr_id=pr.id) as item_count
                FROM purchase_requests pr WHERE 1=1";
        $params = [];
        if ($status) { $sql .= " AND pr.status=?"; $params[] = $status; }
        $sql .= " ORDER BY pr.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // === SINGLE PURCHASE REQUEST WITH ITEMS ===
    if ($action === "purchase_request_detail" && $id) {
        $stmt = $pdo->prepare("SELECT pr.* FROM purchase_requests pr WHERE pr.id=?");
        $stmt->execute([$id]);
        $pr = $stmt->fetch();
        if (!$pr) error("Purchase request not found", 404);
        $iStmt = $pdo->prepare("SELECT pri.*, i.name as item_name, i.item_code, iu.abbreviation as uom_abbr FROM purchase_request_items pri LEFT JOIN items i ON i.id=pri.item_id LEFT JOIN item_uoms iu ON iu.id=pri.uom_id WHERE pri.pr_id=?");
        $iStmt->execute([$id]);
        $pr["items"] = $iStmt->fetchAll();
        success($pr);
    }

    // === PURCHASE ORDERS ===
    if ($action === "purchase_orders") {
        $status = $_GET["status"] ?? null;
        $supplierId = isset($_GET["supplier_id"]) ? (int)$_GET["supplier_id"] : null;
        $sql = "SELECT po.*, s.company_name as supplier_name, s.supplier_code,
                (SELECT COUNT(*) FROM purchase_order_items WHERE po_id=po.id) as item_count,
                pr.pr_number
                FROM purchase_orders po 
                LEFT JOIN suppliers s ON s.id=po.supplier_id
                LEFT JOIN purchase_requests pr ON pr.id=po.pr_id
                WHERE 1=1";
        $params = [];
        if ($status) { $sql .= " AND po.status=?"; $params[] = $status; }
        if ($supplierId) { $sql .= " AND po.supplier_id=?"; $params[] = $supplierId; }
        $sql .= " ORDER BY po.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }
    // === SINGLE PURCHASE ORDER WITH ITEMS ===
    if ($action === "purchase_order_detail" && $id) {
        $stmt = $pdo->prepare("SELECT po.*, 
                s.company_name as supplier_name, 
                s.contact_person as supplier_contact,
                s.phone as supplier_phone, 
                s.email as supplier_email,
                pr.pr_number,
                COALESCE(CONCAT(u.first_name, ' ', u.last_name), u.username, 'Unknown') as created_by_name
                FROM purchase_orders po 
                LEFT JOIN suppliers s ON s.id=po.supplier_id
                LEFT JOIN purchase_requests pr ON pr.id=po.pr_id
                LEFT JOIN users u ON u.id=po.created_by
                WHERE po.id=?");
        $stmt->execute([$id]);
        $po = $stmt->fetch();
        if (!$po) error("Purchase order not found", 404);
        $iStmt = $pdo->prepare("SELECT poi.*, i.name as item_name, i.item_code, i.generic_name, iu.abbreviation as uom_abbr
                FROM purchase_order_items poi 
                LEFT JOIN items i ON i.id=poi.item_id
                LEFT JOIN item_uoms iu ON iu.id=i.uom_id
                WHERE poi.po_id=?");
        $iStmt->execute([$id]);
        $po["items"] = $iStmt->fetchAll();
        success($po);
    }

    // === GRN (Goods Received Notes) ===
    if ($action === "grns") {
        $poId = isset($_GET["po_id"]) ? (int)$_GET["po_id"] : null;
        $sql = "SELECT grn.*, po.po_number, s.company_name as supplier_name
                FROM goods_receiving_notes grn 
                LEFT JOIN purchase_orders po ON po.id=grn.po_id
                LEFT JOIN suppliers s ON s.id=grn.supplier_id
                WHERE 1=1";
        $params = [];
        if ($poId) { $sql .= " AND grn.po_id=?"; $params[] = $poId; }
        $sql .= " ORDER BY grn.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // === SINGLE GRN WITH ITEMS ===
    if ($action === "grn_detail" && $id) {
        $stmt = $pdo->prepare("SELECT grn.*, po.po_number, s.company_name as supplier_name
                FROM goods_receiving_notes grn 
                LEFT JOIN purchase_orders po ON po.id=grn.po_id
                LEFT JOIN suppliers s ON s.id=grn.supplier_id
                WHERE grn.id=?");
        $stmt->execute([$id]);
        $grn = $stmt->fetch();
        if (!$grn) error("GRN not found", 404);
        $iStmt = $pdo->prepare("SELECT gri.*, i.name as item_name, i.item_code, iu.abbreviation as uom_abbr,
                poi.unit_price, poi.quantity_ordered
                FROM goods_receiving_items gri
                LEFT JOIN items i ON i.id=gri.item_id
                LEFT JOIN item_uoms iu ON iu.id=i.uom_id
                LEFT JOIN purchase_order_items poi ON poi.id=gri.po_item_id
                WHERE gri.grn_id=?");
        $iStmt->execute([$id]);
        $grn["items"] = $iStmt->fetchAll();
        success($grn);
    }

    // === STOCK TRANSFERS ===
    if ($action === "transfers") {
        $status = $_GET["status"] ?? null;
        $sql = "SELECT st.*, fs.name as from_store_name, ts.name as to_store_name,
                (SELECT COUNT(*) FROM stock_transfer_items WHERE transfer_id=st.id) as item_count
                FROM stock_transfers st
                LEFT JOIN stores fs ON fs.id=st.from_store_id
                LEFT JOIN stores ts ON ts.id=st.to_store_id
                WHERE 1=1";
        $params = [];
        if ($status) { $sql .= " AND st.status=?"; $params[] = $status; }
        $sql .= " ORDER BY st.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // === SINGLE TRANSFER WITH ITEMS ===
    if ($action === "transfer_detail" && $id) {
        ensureTransferSchema($pdo);
        $stmt = $pdo->prepare("SELECT st.*, fs.name as from_store_name, fs.code as from_store_code,
                ts.name as to_store_name, ts.code as to_store_code
                FROM stock_transfers st
                LEFT JOIN stores fs ON fs.id=st.from_store_id
                LEFT JOIN stores ts ON ts.id=st.to_store_id
                WHERE st.id=?");
        $stmt->execute([$id]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transfer) error("Transfer not found", 404);
        
        $iStmt = $pdo->prepare("SELECT sti.*, i.name as item_name, i.item_code, i.generic_name, 
                ic.name as category_name, iu.abbreviation as uom_abbr, iu.name as uom_name,
                COALESCE(sti.batch_number, sb.batch_number, (SELECT sb2.batch_number FROM stock_movements sm JOIN stock_batches sb2 ON sb2.id=sm.batch_id WHERE sm.reference_type='stock_transfer' AND sm.reference_id=sti.transfer_id AND sm.item_id=sti.item_id AND sb2.batch_number IS NOT NULL AND sb2.batch_number != '' ORDER BY sm.id DESC LIMIT 1)) AS batch_number,
                COALESCE(sti.expiry_date, sb.expiry_date, (SELECT sb2.expiry_date FROM stock_movements sm JOIN stock_batches sb2 ON sb2.id=sm.batch_id WHERE sm.reference_type='stock_transfer' AND sm.reference_id=sti.transfer_id AND sm.item_id=sti.item_id AND sb2.expiry_date IS NOT NULL AND sb2.expiry_date != '0000-00-00' ORDER BY sm.id DESC LIMIT 1)) AS expiry_date,
                sb.selling_price
                FROM stock_transfer_items sti
                LEFT JOIN items i ON i.id=sti.item_id
                LEFT JOIN item_categories ic ON ic.id=i.category_id
                LEFT JOIN item_uoms iu ON iu.id=i.uom_id
                LEFT JOIN stock_batches sb ON sb.id=sti.batch_id
                WHERE sti.transfer_id=?");
        $iStmt->execute([$id]);
        $transfer["items"] = $iStmt->fetchAll(PDO::FETCH_ASSOC);
        success($transfer);
    }

    // === DASHBOARD STATS ===
    // Stores with linked chart of accounts
    // Expired items in stock
    if ($action === "expired_items") {
        $stmt = $pdo->query("SELECT sb.*, i.name AS item_name, i.item_code, s.name AS store_name FROM stock_batches sb JOIN items i ON i.id=sb.item_id LEFT JOIN stores s ON s.id=sb.store_id WHERE sb.is_active=1 AND sb.quantity>0 AND sb.expiry_date IS NOT NULL AND sb.expiry_date < CURDATE() ORDER BY sb.expiry_date ASC");
        success($stmt->fetchAll());
    }

    if ($action === "stores_with_accounts") {
        $stmt = $pdo->query("SELECT s.*, c.account_code, c.account_name FROM stores s LEFT JOIN chart_of_accounts c ON c.id = s.id WHERE s.is_active=1 ORDER BY s.name");
        success($stmt->fetchAll());
    }

    // STOCK REPORT: Opening, In, Out, Closing by date range
    if ($action === "stock_report") {
        // Auto-reconcile any legacy movements with NULL batch_id in database
        try {
            $chk = $pdo->query("SELECT id, item_id, from_store_id, to_store_id FROM stock_movements WHERE batch_id IS NULL LIMIT 100");
            $unlinked = $chk ? $chk->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($unlinked)) {
                foreach ($unlinked as $m) {
                    $stId = $m['from_store_id'] ?: $m['to_store_id'];
                    if (!$stId || !$m['item_id']) continue;
                    $bStmt = $pdo->prepare("SELECT id FROM stock_batches WHERE item_id = ? AND store_id = ? ORDER BY (quantity > 0) DESC, received_date ASC, created_at ASC, id ASC LIMIT 1");
                    $bStmt->execute([$m['item_id'], $stId]);
                    $b = $bStmt->fetch();
                    if ($b) {
                        $pdo->prepare("UPDATE stock_movements SET batch_id = ? WHERE id = ?")->execute([$b['id'], $m['id']]);
                    }
                }
            }
        } catch (Exception $e) {}

        $storeId = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : 0;
        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? date('Y-m-d');

        // Aggregate per batch: one row per (store, item, batch)
        $sql = "SELECT
                    sb.id          AS batch_id,
                    sb.batch_number,
                    sb.expiry_date,
                    i.id           AS item_id,
                    i.name         AS item_name,
                    i.item_code,
                    s.id           AS store_id,
                    s.name         AS store_name,
                    COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.batch_id=sb.id AND sm.to_store_id=s.id   AND DATE(sm.created_at) <  ?), 0)
                  - COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.batch_id=sb.id AND sm.from_store_id=s.id AND DATE(sm.created_at) <  ?), 0) AS opening,
                    COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.batch_id=sb.id AND sm.to_store_id=s.id   AND DATE(sm.created_at) BETWEEN ? AND ?), 0) AS stock_in,
                    COALESCE((SELECT SUM(sm.quantity) FROM stock_movements sm WHERE sm.batch_id=sb.id AND sm.from_store_id=s.id AND DATE(sm.created_at) BETWEEN ? AND ?), 0) AS stock_out
                FROM stock_batches sb
                JOIN items  i ON i.id  = sb.item_id
                JOIN stores s ON s.id  = sb.store_id
                WHERE sb.is_active = 1 AND i.is_active = 1";
        $params = [$from, $from, $from, $to, $from, $to];

        if ($storeId) { $sql .= " AND sb.store_id = ?"; $params[] = $storeId; }
        $sql .= " ORDER BY i.name ASC, s.name ASC, sb.expiry_date ASC, sb.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $r) {
            $opening  = (float)$r['opening'];
            $stockIn  = (float)$r['stock_in'];
            $stockOut = (float)$r['stock_out'];
            $closing  = $opening + $stockIn - $stockOut;
            if ($opening != 0 || $stockIn != 0 || $stockOut != 0 || $closing != 0) {
                $result[] = [
                    'batch_id'     => $r['batch_id'],
                    'item_id'      => $r['item_id'],
                    'item_name'    => $r['item_name'],
                    'item_code'    => $r['item_code'],
                    'store_id'     => $r['store_id'],
                    'store_name'   => $r['store_name'],
                    'batch_number' => $r['batch_number'],
                    'expiry_date'  => $r['expiry_date'],
                    'opening'      => $opening,
                    'stock_in'     => $stockIn,
                    'stock_out'    => $stockOut,
                    'closing'      => $closing,
                ];
            }
        }
        success($result);
    }

    // === STOCK BATCHES REPORT (current stock per batch, with store/batch/expiry) ===
    if ($action === "stock_batches_report") {
        $storeId = isset($_GET['store_id']) && $_GET['store_id'] !== '' ? (int)$_GET['store_id'] : 0;
        $sql = "SELECT sb.id, sb.batch_number, sb.expiry_date, sb.received_date, sb.quantity, sb.unit_cost,
                       i.name AS item_name, i.item_code,
                       s.name AS store_name
                FROM stock_batches sb
                JOIN items i ON i.id = sb.item_id
                JOIN stores s ON s.id = sb.store_id
                WHERE sb.is_active = 1 AND sb.quantity > 0";
        $params = [];
        if ($storeId) { $sql .= " AND sb.store_id = ?"; $params[] = $storeId; }
        $sql .= " ORDER BY i.name ASC, s.name ASC, sb.expiry_date ASC, sb.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    if ($action === "dashboard_stats") {
        $stats = [];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM items WHERE is_active=1");
        $stats["total_items"] = (int)$stmt->fetch()["count"];
        $stmt = $pdo->query("SELECT COUNT(DISTINCT sb.item_id) as count FROM stock_batches sb JOIN items i ON i.id=sb.item_id WHERE sb.quantity <= sb.reorder_level AND sb.reorder_level > 0 AND sb.is_active=1 AND i.is_active=1");
        $stats["low_stock_items"] = (int)$stmt->fetch()["count"];
        $stmt = $pdo->query("SELECT COUNT(DISTINCT sb.item_id) as count FROM stock_batches sb JOIN items i ON i.id=sb.item_id WHERE sb.expiry_date IS NOT NULL AND sb.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND sb.quantity > 0 AND sb.is_active=1 AND i.is_active=1");
        $stats["expiring_items"] = (int)$stmt->fetch()["count"];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM stores WHERE is_active=1");
        $stats["total_stores"] = (int)$stmt->fetch()["count"];
        $stmt = $pdo->query("SELECT COALESCE(SUM(sb.quantity * COALESCE(sb.unit_cost, 0)), 0) as total_value FROM stock_batches sb WHERE sb.is_active=1 AND sb.quantity > 0");
        $stats["stock_value"] = (float)$stmt->fetch()["total_value"];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status='received'");
        $stats["total_pos"] = (int)$stmt->fetch()["count"];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM suppliers WHERE status='active'");
        $stats["total_suppliers"] = (int)$stmt->fetch()["count"];
        success($stats);
    }

    // === STORE BATCHES (GET) - for transfers FIFO ===
    if ($action === "store_batches") {
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        if (!$storeId) error('store_id required', 422);
        $stmt = $pdo->prepare("SELECT sb.id, sb.item_id, i.name AS item_name, i.item_code, sb.batch_number, sb.quantity, sb.unit_cost, sb.selling_price, 
            CASE WHEN sb.expiry_date = '0000-00-00' OR sb.expiry_date = '0000-00-00 00:00:00' THEN NULL ELSE sb.expiry_date END AS expiry_date, 
            sb.received_date
            FROM stock_batches sb
            JOIN items i ON i.id = sb.item_id
            WHERE sb.store_id = ? AND sb.quantity > 0 AND sb.is_active = 1
            ORDER BY sb.received_date ASC, CASE WHEN sb.expiry_date IS NULL OR sb.expiry_date = '0000-00-00' THEN 1 ELSE 0 END, sb.expiry_date ASC, sb.id ASC");
        $stmt->execute([$storeId]);
        success($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // === OTHER STOCK (GET) ===
    if ($action === "other_stock") {
        $stmt = $pdo->query("SELECT i.name, i.description as measure, sb.quantity, sb.unit_cost, sb.batch_number, sb.expiry_date, sb.received_date as date, sb.store_id, sm.notes, sm.created_at
            FROM stock_batches sb
            JOIN items i ON i.id = sb.item_id
            LEFT JOIN stock_movements sm ON sm.batch_id = sb.id AND sm.reference_type='other_stock'
            WHERE i.category_id = 11
            ORDER BY sb.created_at DESC LIMIT 200");
        success($stmt->fetchAll());
    }

    // === ADJUSTMENT ACCOUNTS (for stock adjustment modal) ===
    if ($action === "adjustment_accounts") {
        $type = $_GET['type'] ?? ''; // 'addition' or 'reduction'
        $where = ["status = 'active'"];
        if ($type === 'addition') {
            $where[] = "(account_type IN ('income', 'equity') OR account_name LIKE '%adjust%' OR account_name LIKE '%surplus%' OR account_name LIKE '%gain%')";
        } elseif ($type === 'reduction') {
            $where[] = "(account_type = 'expense' OR account_name LIKE '%adjust%' OR account_name LIKE '%loss%' OR account_name LIKE '%damage%')";
        } else {
            $where[] = "(account_type IN ('expense', 'income', 'equity') OR account_name LIKE '%adjust%' OR account_name LIKE '%loss%' OR account_name LIKE '%surplus%')";
        }
        $sql = "SELECT id, account_code, account_name, account_type, description 
                FROM chart_of_accounts 
                WHERE " . implode(' AND ', $where) . " 
                ORDER BY 
                  CASE 
                    WHEN id IN (1385, 1384) THEN 1
                    WHEN id IN (44, 77) THEN 2
                    WHEN account_name LIKE '%adjust%' THEN 3
                    ELSE 4
                  END, 
                  account_type ASC, account_name ASC";
        $stmt = $pdo->query($sql);
        success($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    error("Unknown action", 404);
}
// ============================================
// POST
// ============================================
if ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true) ?? [];
    $userId = !empty($input['user_id']) ? (int)$input['user_id'] : (!empty($input['created_by']) ? (int)$input['created_by'] : $sessionUserId);

    // === CREATE STORE WITH COA ACCOUNT ===
    if ($action === "create_store_with_account") {
        $name = trim($input['name'] ?? '');
        $code = strtoupper(trim($input['code'] ?? ''));
        $accCode = trim($input['account_code'] ?? '');
        if (!$name || !$code || !$accCode) error('Name, code, and account_code required', 422);
        
        $pdo->beginTransaction();
        try {
            // Create chart of accounts entry under Inventory (parent_id=9)
            $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_id, status, is_default, has_sub) VALUES (?,?,'asset',9,'active',0,0)")
                ->execute([$accCode, $name]);
            $accId = (int)$pdo->lastInsertId();
            
            // Create store with same ID as account
            $pdo->prepare("INSERT INTO stores (id, name, code, is_active) VALUES (?,?,?,1)")->execute([$accId, $name, $code]);
            
            $pdo->commit();
            success(['store_id' => $accId, 'account_id' => $accId], 'Store created & linked to COA');
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Failed: ' . $e->getMessage());
        }
    }

    // === OPENING STOCK ===
    if ($action === "opening_stock") {
        $storeId = (int)($input['store_id'] ?? 0);
        $date = $input['date'] ?? date('Y-m-d');
        $items = $input['items'] ?? [];
        if (!$storeId || empty($items)) error('store_id and items required', 422);
        
        $pdo->beginTransaction();
        try {
            $totalValue = 0;
            $batchStmt = $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,1,?,NOW())");
            $moveStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by, created_at) VALUES (?,?,NULL,?,'purchase_receipt','opening_stock',?,?,?,'Opening stock entry',?,NOW())");
            
            foreach ($items as $it) {
                $itemId = (int)$it['item_id'];
                $qty = (float)$it['quantity'];
                $cost = (float)($it['unit_cost'] ?? 0);
                $sell = (float)($it['selling_price'] ?? 0);
                $batch = $it['batch_number'] ?? 'OPEN-'.date('Ymd');
                $rawExpiry = trim($it['expiry_date'] ?? '');
                $expiry = null;
                if ($rawExpiry) {
                    $ts = strtotime($rawExpiry);
                    if (!$ts) { $parts = preg_split('/[\/\-\.]/', $rawExpiry); if (count($parts)===3) { if(strlen($parts[2])===4) $ts=mktime(0,0,0,(int)$parts[1],(int)$parts[0],(int)$parts[2]); elseif(strlen($parts[0])===4) $ts=mktime(0,0,0,(int)$parts[1],(int)$parts[2],(int)$parts[0]); }}
                    if ($ts) $expiry = date('Y-m-d', $ts);
                }
                
                $batchStmt->execute([$itemId, $storeId, $batch, $qty, $cost, $sell, $expiry, $date, $userId]);
                $batchId = (int)$pdo->lastInsertId();
                $moveStmt->execute([$itemId, $batchId, $storeId, $batchId, $qty, $cost, $userId]);
                $totalValue += $qty * $cost;
            }
            
            // Create journal entry: Debit Store Account (store_id = COA account_id), Credit Opening Equity (account 77)
            if ($totalValue > 0) {
                $ref = 'OPEN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $jeStmt = $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, created_by, posted, posted_at, created_at) VALUES (?,?,?,?,1,NOW(),NOW())");
                $jeStmt->execute([$date, $ref, 'Opening stock - Store #'.$storeId, $userId]);
                $jeId = (int)$pdo->lastInsertId();
                
                // Debit the store's own account (store_id IS the COA account_id)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")->execute([$jeId, $storeId, 'Opening stock value', $totalValue]);
                // Credit Opening Equity Balance (id=77)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,77,?,0,?)")->execute([$jeId, 'Opening stock equity', $totalValue]);
            }
            
            $pdo->commit();
            success(null, count($items).' items recorded. Total value: '.number_format($totalValue).' RWF. Journal: '.$ref);
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Failed: '.$e->getMessage());
        }
    }

    // === OTHER STOCK (Non-Medical Supplies) — uses items, stock_batches, stock_movements ===
    if ($action === "other_stock") {
        // POST - Record other stock into standard inventory tables with category_id=11
        $storeId = (int)($input['store_id'] ?? 0);
        $date = $input['date'] ?? date('Y-m-d');
        $items = $input['items'] ?? [];
        $categoryId = 11; // Other stock category (Nursing supplies / non-medical)
        if (!$storeId || empty($items)) error('store_id and items required', 422);

        $pdo->beginTransaction();
        try {
            $totalValue = 0;
            $created = 0;

            foreach ($items as $it) {
                $name = trim($it['name'] ?? '');
                $qty = (float)($it['quantity'] ?? 0);
                $measure = $it['measure'] ?? 'pcs';
                $cost = (float)($it['unit_cost'] ?? 0);
                $batch = $it['batch_number'] ?? 'OTH-'.date('Ymd');
                $rawExpiry = trim($it['expiry_date'] ?? '');
                $expiry = null;
                if ($rawExpiry) {
                    // Try to parse various date formats
                    $ts = strtotime($rawExpiry);
                    if (!$ts) {
                        // Try d/m/Y or d-m-Y format
                        $parts = preg_split('/[\/\-\.]/', $rawExpiry);
                        if (count($parts) === 3) {
                            if (strlen($parts[2]) === 4) {
                                // d/m/Y
                                $ts = mktime(0,0,0,(int)$parts[1],(int)$parts[0],(int)$parts[2]);
                            } elseif (strlen($parts[0]) === 4) {
                                // Y/m/d
                                $ts = mktime(0,0,0,(int)$parts[1],(int)$parts[2],(int)$parts[0]);
                            }
                        }
                    }
                    if ($ts) $expiry = date('Y-m-d', $ts);
                }

                if (!$name || $qty <= 0) continue;

                // 1. Find or create item in items table
                $findItem = $pdo->prepare("SELECT id FROM items WHERE name=? AND category_id=?");
                $findItem->execute([$name, $categoryId]);
                $existing = $findItem->fetch();

                if ($existing) {
                    $itemId = (int)$existing['id'];
                    // Update cost if changed
                    $pdo->prepare("UPDATE items SET unit_price=?, description=? WHERE id=?")->execute([$cost, $measure, $itemId]);
                } else {
                    // Create new item
                    $itemCode = 'OTH-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                    $createItem = $pdo->prepare("INSERT INTO items (item_code, name, category_id, unit_price, selling_price, is_medication, is_controlled, description, created_by) VALUES (?,?,?,?,?,0,0,?,?)");
                    $createItem->execute([$itemCode, $name, $categoryId, $cost, $cost, $measure, $userId]);
                    $itemId = (int)$pdo->lastInsertId();
                }

                // 2. Create stock batch
                $batchStmt = $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,1,?,NOW())");
                $batchStmt->execute([$itemId, $storeId, $batch, $qty, $cost, $cost, $expiry, $date, $userId]);
                $batchId = (int)$pdo->lastInsertId();

                // 3. Create stock movement
                $moveStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by, created_at) VALUES (?,?,NULL,?,'purchase_receipt','other_stock',?,?,?,'Other stock entry',?,NOW())");
                $moveStmt->execute([$itemId, $batchId, $storeId, $batchId, $qty, $cost, $userId]);

                $totalValue += $qty * $cost;
                $created++;
            }

            // 4. Create journal entry: Debit Store Account, Credit Opening Equity (77)
            $ref = 'OTH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            if ($totalValue > 0) {
                $jeStmt = $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, created_by, posted, posted_at, created_at) VALUES (?,?,?,?,1,NOW(),NOW())");
                $jeStmt->execute([$date, $ref, 'Other stock (supplies) - Store #'.$storeId, $userId]);
                $jeId = (int)$pdo->lastInsertId();

                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")->execute([$jeId, $storeId, 'Other stock / supplies', $totalValue]);
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,77,?,0,?)")->execute([$jeId, 'Other stock equity credit', $totalValue]);
            }

            $pdo->commit();
            success(['ref' => $ref], $created.' item(s) recorded. Total: '.number_format($totalValue).' RWF. Journal: '.$ref);
        } catch (Exception $e) {
            $pdo->rollBack();
            error('Failed: '.$e->getMessage());
        }
    }

    // === CREATE CATEGORY ===
    if ($action === "create_category") {
        if (empty($input["name"])) error("Category name required", 422);
        $code = $input["code"] ?? strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $input["name"]), 0, 3));
        $stmt = $pdo->prepare("INSERT INTO item_categories (name, code, description, parent_id) VALUES (?,?,?,?)");
        $stmt->execute([
            trim($input["name"]), $code,
            $input["description"] ?? null,
            !empty($input["parent_id"]) ? (int)$input["parent_id"] : null
        ]);
        success(["id" => (int)$pdo->lastInsertId()], "Category created", 201);
    }

    // === CREATE UOM ===
    if ($action === "create_uom") {
        if (empty($input["name"]) || empty($input["abbreviation"])) error("Name and abbreviation required", 422);
        $stmt = $pdo->prepare("INSERT INTO item_uoms (name, abbreviation) VALUES (?,?)");
        $stmt->execute([trim($input["name"]), trim($input["abbreviation"])]);
        success(["id" => (int)$pdo->lastInsertId()], "UOM created", 201);
    }

    // === CREATE ITEM ===
    if ($action === "create_item") {
        if (empty($input["name"]) || empty($input["category_id"])) error("Item name and category required", 422);
        $itemCode = $input["item_code"] ?? ("ITM-" . date("Ymd") . "-" . str_pad(mt_rand(1, 9999), 4, "0", STR_PAD_LEFT));
        $stmt = $pdo->prepare("INSERT INTO items (name, item_code, generic_name, category_id, uom_id, 
            is_medication, is_controlled, description, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            trim($input["name"]), $itemCode,
            $input["generic_name"] ?? null,
            (int)$input["category_id"],
            !empty($input["uom_id"]) ? (int)$input["uom_id"] : null,
            isset($input["is_medication"]) ? (int)$input["is_medication"] : 0,
            isset($input["is_controlled"]) ? (int)$input["is_controlled"] : 0,
            $input["description"] ?? null,
            $userId
        ]);
        success(["id" => (int)$pdo->lastInsertId()], "Item created", 201);
    }

    // === CREATE ITEM FROM OPENING BALANCE (with stock batch) ===
    if ($action === "create_item_opening") {
        if (empty($input["name"])) error("Item name required", 422);
        $storeId = (int)($input["store_id"] ?? 0);
        if (!$storeId) error("store_id required", 422);
        
        // Ensure insurance prices table exists with exceptional columns (outside transaction)
        $pdo->exec("CREATE TABLE IF NOT EXISTS item_insurance_prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            insurance_id INT NOT NULL,
            price DECIMAL(12,2) DEFAULT 0,
            is_exceptional TINYINT(1) DEFAULT 0,
            insurance_pays DECIMAL(12,2) DEFAULT 0,
            patient_topup DECIMAL(12,2) DEFAULT 0,
            UNIQUE KEY (item_id, insurance_id)
        )");
        // Add columns if table already exists without them
        try { $pdo->exec("ALTER TABLE item_insurance_prices ADD COLUMN is_exceptional TINYINT(1) DEFAULT 0 AFTER price"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE item_insurance_prices ADD COLUMN insurance_pays DECIMAL(12,2) DEFAULT 0 AFTER is_exceptional"); } catch(Exception $e){}
        try { $pdo->exec("ALTER TABLE item_insurance_prices ADD COLUMN patient_topup DECIMAL(12,2) DEFAULT 0 AFTER insurance_pays"); } catch(Exception $e){}
        
        $pdo->beginTransaction();
        try {
            // Check if item already exists by name
            $chk = $pdo->prepare("SELECT id FROM items WHERE name = ?");
            $chk->execute([trim($input["name"])]);
            $existingId = $chk->fetchColumn();
            
            // Handle UOM (measure) - create if not exists, link to item
            $uomId = null;
            $measure = trim($input["description"] ?? '');
            if ($measure) {
                $uomChk = $pdo->prepare("SELECT id FROM item_uoms WHERE name = ? OR abbreviation = ?");
                $uomChk->execute([$measure, $measure]);
                $uomId = $uomChk->fetchColumn();
                if (!$uomId) {
                    $pdo->prepare("INSERT INTO item_uoms (name, abbreviation, is_active) VALUES (?,?,1)")
                        ->execute([$measure, $measure]);
                    $uomId = (int)$pdo->lastInsertId();
                }
            }
            
            if ($existingId) {
                $itemId = (int)$existingId;
                // Update prices and UOM
                $pdo->prepare("UPDATE items SET unit_price=?, selling_price=?, uom_id=? WHERE id=?")
                    ->execute([(float)($input["unit_price"] ?? 0), (float)($input["selling_price"] ?? 0), $uomId, $itemId]);
            } else {
                // Create new item
                $itemCode = "ITM-" . str_pad(mt_rand(1, 99999), 5, "0", STR_PAD_LEFT);
                $pdo->prepare("INSERT INTO items (name, item_code, unit_price, selling_price, is_medication, uom_id, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,1,?,NOW())")
                    ->execute([
                        trim($input["name"]),
                        $itemCode,
                        (float)($input["unit_price"] ?? 0),
                        (float)($input["selling_price"] ?? 0),
                        (int)($input["is_medication"] ?? 1),
                        $uomId,
                        $userId
                    ]);
                $itemId = (int)$pdo->lastInsertId();
            }
            
            // Parse expiry date
            $expiryDate = null;
            $rawExpiry = trim($input["expiry_date"] ?? '');
            if ($rawExpiry) {
                // Try various formats
                $parsed = date_create_from_format('d/m/Y', $rawExpiry) ?: 
                          date_create_from_format('d/m/y', $rawExpiry) ?: 
                          date_create($rawExpiry);
                if ($parsed) $expiryDate = $parsed->format('Y-m-d');
            }
            
            // Create stock batch
            $qty = (float)($input["quantity"] ?? 0);
            $unitCost = (float)($input["unit_price"] ?? 0);
            $sellingPrice = (float)($input["selling_price"] ?? 0);
            $batchNumber = trim($input["batch_number"] ?? '');
            
            $batchId = 0;
            if ($qty > 0) {
                $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,?,CURDATE(),1,?,NOW())")
                    ->execute([$itemId, $storeId, $batchNumber, $qty, $unitCost, $sellingPrice, $expiryDate, $userId]);
                $batchId = (int)$pdo->lastInsertId();
            }
            
            // Store insurance prices
            $insPrices = $input["insurance_prices"] ?? [];
            if (!empty($insPrices)) {
                $insStmt = $pdo->prepare("INSERT INTO item_insurance_prices (item_id, insurance_id, price, is_exceptional, insurance_pays, patient_topup) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price), is_exceptional=VALUES(is_exceptional), insurance_pays=VALUES(insurance_pays), patient_topup=VALUES(patient_topup)");
                foreach ($insPrices as $insId => $insPrice) {
                    if ((float)$insPrice > 0 && (int)$insId > 0) {
                        $insStmt->execute([$itemId, (int)$insId, (float)$insPrice, 0, 0, 0]);
                    }
                }
            }
            
            // Process exceptional insurance pricing
            $exceptionalData = $input["exceptional_prices"] ?? [];
            if (!empty($exceptionalData)) {
                $excStmt = $pdo->prepare("INSERT INTO item_insurance_prices (item_id, insurance_id, price, is_exceptional, insurance_pays, patient_topup) VALUES (?,?,?,1,?,?) ON DUPLICATE KEY UPDATE is_exceptional=1, insurance_pays=VALUES(insurance_pays), patient_topup=VALUES(patient_topup)");
                foreach ($exceptionalData as $exc) {
                    $excInsId = $exc['insurance_id'] ?? '';
                    $excInsPays = (float)($exc['insurance_pays'] ?? 0);
                    $excPatient = (float)($exc['patient_topup'] ?? 0);
                    
                    if ($excInsId === 'all') {
                        // Apply to all insurances that have prices for this item
                        $allIns = $pdo->prepare("SELECT insurance_id FROM item_insurance_prices WHERE item_id=?");
                        $allIns->execute([$itemId]);
                        while ($row = $allIns->fetch()) {
                            $excStmt->execute([$itemId, (int)$row['insurance_id'], $excInsPays, $excInsPays, $excPatient]);
                        }
                        // Also apply to all mapped insurances from input
                        foreach ($insPrices as $insId => $insPrice) {
                            if ((int)$insId > 0) {
                                $excStmt->execute([$itemId, (int)$insId, $excInsPays, $excInsPays, $excPatient]);
                            }
                        }
                    } else if ($excInsId === 'patient') {
                        // Patient top-up applies to all - update existing records
                        $pdo->prepare("UPDATE item_insurance_prices SET patient_topup=? WHERE item_id=?")->execute([$excPatient, $itemId]);
                    } else if ((int)$excInsId > 0) {
                        $excStmt->execute([$itemId, (int)$excInsId, $excInsPays, $excInsPays, $excPatient]);
                    }
                }
            }
            
            // Record stock movement (use 'purchase_receipt' as closest type for opening)
            if ($qty > 0 && $batchId) {
                $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, to_store_id, movement_type, reference_type, quantity, unit_cost, notes, created_by, created_at) VALUES (?,?,?,'purchase_receipt','opening_balance',?,?,?,?,NOW())")
                    ->execute([$itemId, $batchId, $storeId, $qty, $unitCost, 'Opening balance', $userId]);
            }
            
            $pdo->commit();
            success(["item_id" => $itemId], "Item saved with opening stock");
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Failed: " . $e->getMessage());
        }
    }

    // === CREATE STORE ===
    if ($action === "create_store") {
        foreach (["name","code"] as $f) {
            if (empty($input[$f])) error("Field \"$f\" required", 422);
        }
        $stmt = $pdo->prepare("INSERT INTO stores (name, code, type, location) VALUES (?,?,?,?)");
        $stmt->execute([
            trim($input["name"]), strtoupper(trim($input["code"])),
            $input["type"] ?? "department",
            $input["location"] ?? null
        ]);
        success(["id" => (int)$pdo->lastInsertId()], "Store created", 201);
    }

    // === RECEIVE STOCK (add stock to store) ===
    if ($action === "receive_stock") {
        foreach (["item_id","store_id","quantity","unit_cost"] as $f) {
            if ((!isset($input[$f]) || $input[$f] === "") && $input[$f] !== 0) error("Field \"$f\" required", 422);
        }
        $itemId = (int)$input["item_id"];
        $storeId = (int)$input["store_id"];
        $quantity = (float)$input["quantity"];
        $unitCost = (float)$input["unit_cost"];

        $chk = $pdo->prepare("SELECT id FROM items WHERE id=? AND is_active=1");
        $chk->execute([$itemId]);
        if (!$chk->fetch()) error("Item not found", 404);

        $pdo->beginTransaction();
        try {
            $batchNo = $input["batch_number"] ?? ("BATCH-" . date("Ymd") . "-" . str_pad(mt_rand(1, 9999), 4, "0", STR_PAD_LEFT));
            $stmt = $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, 
                reorder_level, min_stock_level, expiry_date, received_date, notes, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([
                $itemId, $storeId, $batchNo, $quantity, $unitCost,
                $input["selling_price"] ?? null,
                $input["reorder_level"] ?? 0,
                $input["min_stock_level"] ?? 0,
                $input["expiry_date"] ?? null,
                $input["received_date"] ?? date("Y-m-d"),
                $input["notes"] ?? null,
                $userId
            ]);
            $batchId = (int)$pdo->lastInsertId();

            // Record stock movement
            $stmt = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([
                $itemId, $batchId, $storeId, "purchase_receipt", $quantity, $unitCost,
                $input["reference_type"] ?? "manual",
                $input["reference_id"] ?? null,
                $input["notes"] ?? "Manual stock receiving",
                $userId
            ]);

            $pdo->commit();
            success(["id" => $batchId], "Stock received successfully", 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Stock receiving failed: " . $e->getMessage(), 500);
        }
    }

    // === ISSUE STOCK (dispense/use) ===
    if ($action === "issue_stock") {
        foreach (["item_id","store_id","quantity"] as $f) {
            if ((!isset($input[$f]) || $input[$f] === "") && $input[$f] !== 0) error("Field \"$f\" required", 422);
        }
        $itemId = (int)$input["item_id"];
        $storeId = (int)$input["store_id"];
        $quantity = (float)$input["quantity"];
        if ($quantity <= 0) error("Quantity must be positive", 422);

        $chk = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) as total FROM stock_batches WHERE item_id=? AND store_id=? AND is_active=1 AND quantity > 0");
        $chk->execute([$itemId, $storeId]);
        $totalStock = (float)$chk->fetch()["total"];
        if ($totalStock < $quantity) error("Insufficient stock. Available: $totalStock", 409);

        $pdo->beginTransaction();
        try {
            $batches = $pdo->prepare("SELECT id, quantity, unit_cost, batch_number FROM stock_batches WHERE item_id=? AND store_id=? AND is_active=1 AND quantity > 0 ORDER BY expiry_date ASC, created_at ASC");
            $batches->execute([$itemId, $storeId]);
            $remaining = $quantity;
            $unitCost = 0;

            while ($row = $batches->fetch()) {
                if ($remaining <= 0) break;
                $deduct = min((float)$row["quantity"], $remaining);
                $newQty = (float)$row["quantity"] - $deduct;
                $pdo->prepare("UPDATE stock_batches SET quantity=? WHERE id=?")->execute([$newQty, $row["id"]]);
                
                // Record movement per batch
                $stmt = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by, created_at) VALUES (?,?,?,NULL,'dispensing',?,?,?,?,?,NOW())");
                $stmt->execute([
                    $itemId, $row["id"], $storeId, $deduct, (float)$row["unit_cost"],
                    $input["reference_type"] ?? "dispensing",
                    $input["reference_id"] ?? null,
                    $input["notes"] ?? "Stock issued/dispensed",
                    $userId
                ]);

                $remaining -= $deduct;
            }

            $pdo->commit();
            success(null, "Stock issued successfully");
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Stock issuing failed: " . $e->getMessage(), 500);
        }
    }


    // === CREATE SUPPLIER ===
    if ($action === "create_supplier") {
        if (empty($input["company_name"])) error("Company name required", 422);
        $code = $input["supplier_code"] ?? ("SUP-" . date("Ymd") . "-" . str_pad(mt_rand(1, 999), 3, "0", STR_PAD_LEFT));
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_code, company_name, contact_person, email, phone, address, category, payment_terms, tax_id, notes, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([
                $code, trim($input["company_name"]),
                $input["contact_person"] ?? null,
                $input["email"] ?? null,
                $input["phone"] ?? null,
                $input["address"] ?? null,
                $input["category"] ?? null,
                $input["payment_terms"] ?? null,
                $input["tax_id"] ?? null,
                $input["notes"] ?? null,
                $userId
            ]);
            $supplierId = (int)$pdo->lastInsertId();
            
            // Create COA account under Accounts Payable (parent_id=22) linked to this supplier
            $accCode = '2110' . str_pad($supplierId, 2, '0', STR_PAD_LEFT);
            $accName = 'Payable - ' . trim($input["company_name"]);
            $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_id, status, is_default, has_sub, linked_supplier_id) VALUES (?,?,'liability',22,'active',0,0,?)")
                ->execute([$accCode, $accName, $supplierId]);
            
            $pdo->commit();
            success(["id" => $supplierId], "Supplier created with COA account", 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Failed: " . $e->getMessage());
        }
    }

    // === CREATE PURCHASE REQUEST ===
    if ($action === "create_purchase_request") {
        if (empty($input["items"]) || !is_array($input["items"])) error("Items array required", 422);
        $pdo->beginTransaction();
        try {
            $prNumber = "PR-" . date("Ymd") . "-" . str_pad(mt_rand(1, 999), 3, "0", STR_PAD_LEFT);
            $totalEstimated = 0;
            foreach ($input["items"] as $item) {
                $totalEstimated += (float)($item["quantity"] ?? 0) * (float)($item["estimated_unit_price"] ?? 0);
            }
            $stmt = $pdo->prepare("INSERT INTO purchase_requests (pr_number, department_id, requested_by, request_date, required_by, priority, justification, status, total_estimated, notes, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $stmt->execute([
                $prNumber,
                !empty($input["department_id"]) ? (int)$input["department_id"] : null,
                !empty($input["requested_by"]) ? (int)$input["requested_by"] : $userId,
                $input["request_date"] ?? date("Y-m-d"),
                $input["required_by"] ?? null,
                $input["priority"] ?? "medium",
                $input["justification"] ?? null,
                $input["status"] ?? "pending",
                $totalEstimated,
                $input["notes"] ?? null,
                $userId
            ]);
            $prId = (int)$pdo->lastInsertId();

            foreach ($input["items"] as $item) {
                if (empty($item["item_id"]) && empty($item["item_description"])) continue;
                $qty = (float)($item["quantity"] ?? 0);
                $price = (float)($item["estimated_unit_price"] ?? 0);
                $pdo->prepare("INSERT INTO purchase_request_items (pr_id, item_id, item_description, quantity, uom_id, estimated_unit_price, estimated_total, notes) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$prId,
                        !empty($item["item_id"]) ? (int)$item["item_id"] : null,
                        $item["item_description"] ?? null,
                        $qty,
                        !empty($item["uom_id"]) ? (int)$item["uom_id"] : null,
                        $price,
                        $qty * $price,
                        $item["notes"] ?? null
                    ]);
            }

            $pdo->commit();
            success(["id" => $prId, "pr_number" => $prNumber], "Purchase request created", 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Failed to create purchase request: " . $e->getMessage(), 500);
        }
    }
    // === UPDATE PR STATUS (POST) ===
    if ($action === "update_pr_status") {
        $prId = (int)($input['pr_id'] ?? $id ?? 0);
        if (!$prId) error("PR ID required", 422);
        if (empty($input["status"])) error("Status required", 422);
        $allowed = ["draft","pending","approved","rejected","ordered","cancelled"];
        if (!in_array($input["status"], $allowed)) error("Invalid status", 422);
        $pdo->prepare("UPDATE purchase_requests SET status=? WHERE id=?")->execute([$input["status"], $prId]);
        if ($input["status"] === "approved") {
            $pdo->prepare("UPDATE purchase_requests SET approved_at=NOW(), approved_by=? WHERE id=?")->execute([$userId, $prId]);
        }
        if (!empty($input['note'])) {
            $pdo->prepare("UPDATE purchase_requests SET notes=? WHERE id=?")->execute([$input['note'], $prId]);
        }
        success(null, "Status updated to: ".$input["status"]);
    }
    // === RECEIVE GOODS (into stock + journal entry) ===
    if ($action === "receive_goods") {
        $poId = (int)($input['po_id'] ?? 0);
        $storeId = (int)($input['store_id'] ?? 1004);
        $supplierId = (int)($input['supplier_id'] ?? 0);
        $items = $input['items'] ?? [];
        if (!$poId || empty($items)) error("po_id and items required", 422);
        
        $pdo->beginTransaction();
        try {
            $totalValue = 0;
            $batchStmt = $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, is_active, created_by, created_at) VALUES (?,?,?,?,?,?,?,CURDATE(),1,?,NOW())");
            $moveStmt = $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by, created_at) VALUES (?,?,NULL,?,'purchase_receipt','purchase_order',?,?,?,'PO Receipt',?,NOW())");
            
            foreach ($items as $it) {
                $itemId = (int)$it['item_id'];
                $qty = (float)$it['quantity'];
                $price = (float)($it['unit_price'] ?? 0);
                $batch = !empty($it['batch_number']) ? trim($it['batch_number']) : null;
                $rawExpiry = trim($it['expiry_date'] ?? '');
                $expiry = null;
                if ($rawExpiry) {
                    $ts = strtotime($rawExpiry);
                    if (!$ts) { $parts = preg_split('/[\/\-\.]/', $rawExpiry); if (count($parts)===3) { if(strlen($parts[2])===4) $ts=mktime(0,0,0,(int)$parts[1],(int)$parts[0],(int)$parts[2]); elseif(strlen($parts[0])===4) $ts=mktime(0,0,0,(int)$parts[1],(int)$parts[2],(int)$parts[0]); }}
                    if ($ts) $expiry = date('Y-m-d', $ts);
                }
                
                // Get selling price from items table
                $sellPrice = (float)($pdo->prepare("SELECT selling_price FROM items WHERE id=?")->execute([$itemId]) ? $pdo->query("SELECT selling_price FROM items WHERE id=$itemId")->fetchColumn() : 0);
                
                $batchStmt->execute([$itemId, $storeId, $batch, $qty, $price, $sellPrice ?: $price * 1.3, $expiry, $userId]);
                $batchId = (int)$pdo->lastInsertId();
                $moveStmt->execute([$itemId, $batchId, $storeId, $poId, $qty, $price, $userId]);
                $totalValue += $qty * $price;
            }
            
            // Update PO status to received
            $pdo->prepare("UPDATE purchase_orders SET status='received' WHERE id=?")->execute([$poId]);
            // Update PR status to received
            $pdo->prepare("UPDATE purchase_requests SET status='received' WHERE id=(SELECT pr_id FROM purchase_orders WHERE id=?)")->execute([$poId]);
            
            // Create journal entry: Debit Store Account, Credit Supplier Account
            if ($totalValue > 0) {
                $ref = 'GRN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, created_by, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'purchase_order',?,?,1,NOW(),NOW())")
                    ->execute([$ref, 'Goods received - PO #'.$poId, $poId, $userId]);
                $jeId = (int)$pdo->lastInsertId();
                
                // Debit: Store account (store_id = COA account_id)
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                    ->execute([$jeId, $storeId, 'Stock received', $totalValue]);
                
                // Credit: Supplier's COA account (find by linked_supplier_id)
                $supAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE linked_supplier_id=$supplierId LIMIT 1")->fetchColumn();
                if (!$supAccId) $supAccId = 22; // fallback to Accounts Payable
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")
                    ->execute([$jeId, $supAccId, 'Payable to supplier', $totalValue]);
            }
            
            $pdo->commit();
            success(null, count($items)." items received. Total: ".number_format($totalValue)." RWF. Journal: $ref");
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Failed: " . $e->getMessage());
        }
    }
    // === HELPER FUNCTION: EXECUTE STOCK TRANSFER MOVEMENTS ===
    if (!function_exists('processStockTransferMovements')) {
        function processStockTransferMovements($pdo, $fromStore, $toStore, $items, $transferNumber, $transferId, $userId) {
            ensureTransferSchema($pdo);
            $totalValue = 0;
            
            foreach ($items as $it) {
                $itemId = (int)$it['item_id'];
                $requestedQty = (float)$it['quantity'];
                if ($requestedQty <= 0) continue;
                
                $specifiedBatchId = !empty($it['batch_id']) ? (int)$it['batch_id'] : 0;
                $remainingQty = $requestedQty;
                
                // Find source batches
                $sourceBatches = [];
                if ($specifiedBatchId) {
                    $bStmt = $pdo->prepare("SELECT id, item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date FROM stock_batches WHERE id = ? AND item_id = ? AND store_id = ? AND (is_active = 1 OR is_active IS NULL) AND quantity > 0");
                    $bStmt->execute([$specifiedBatchId, $itemId, $fromStore]);
                    $primaryBatch = $bStmt->fetch();
                    if ($primaryBatch) {
                        $sourceBatches[] = $primaryBatch;
                    }
                }
                
                // If no specific batch or specific batch has less quantity than requested, fetch other available batches in FIFO order
                $fetchedIds = array_map(function($b) { return (int)$b['id']; }, $sourceBatches);
                $excludeSql = !empty($fetchedIds) ? " AND id NOT IN (" . implode(',', $fetchedIds) . ")" : "";
                
                $bListStmt = $pdo->prepare("SELECT id, item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date FROM stock_batches WHERE item_id = ? AND store_id = ? AND (is_active = 1 OR is_active IS NULL) AND quantity > 0 $excludeSql ORDER BY CASE WHEN expiry_date IS NULL OR expiry_date = '0000-00-00' THEN 1 ELSE 0 END, expiry_date ASC, received_date ASC, id ASC");
                $bListStmt->execute([$itemId, $fromStore]);
                while ($otherBatch = $bListStmt->fetch()) {
                    $sourceBatches[] = $otherBatch;
                }
                
                if (empty($sourceBatches)) {
                    throw new Exception("No active stock available for item #$itemId in source store");
                }
                
                $totalAvail = array_sum(array_map(function($b) { return (float)$b['quantity']; }, $sourceBatches));
                if ($totalAvail < $requestedQty) {
                    throw new Exception("Insufficient stock for item #$itemId in source store (available: $totalAvail, requested: $requestedQty)");
                }
                
                $firstSourceBatchId = null;
                $firstCost = 0;
                $recordedBatchNumber = null;
                $recordedExpiryDate = null;
                
                foreach ($sourceBatches as $src) {
                    if ($remainingQty <= 0) break;
                    
                    $srcQty = (float)$src['quantity'];
                    $deductQty = min($srcQty, $remainingQty);
                    $newSrcQty = $srcQty - $deductQty;
                    
                    // Normalize batch attributes: input payload overrides or falls back to source batch
                    $inputBatchNumber = !empty(trim((string)($it['batch_number'] ?? ''))) ? trim((string)$it['batch_number']) : null;
                    $srcBatchNumber = !empty(trim((string)($src['batch_number'] ?? ''))) ? trim((string)$src['batch_number']) : null;
                    $origBatchNumber = $srcBatchNumber ?: $inputBatchNumber;
                    
                    $inputExpiryDate = normalizeExpiryDate($it['expiry_date'] ?? null);
                    $srcExpiryDate = normalizeExpiryDate($src['expiry_date'] ?? null);
                    $origExpiryDate = $inputExpiryDate ?: $srcExpiryDate;
                    
                    // Heal source batch in database if missing batch_number or expiry_date
                    if ($origExpiryDate && !$srcExpiryDate) {
                        $pdo->prepare("UPDATE stock_batches SET expiry_date = ?, updated_at = NOW() WHERE id = ? AND (expiry_date IS NULL OR expiry_date = '0000-00-00' OR expiry_date = '0000-00-00 00:00:00')")
                            ->execute([$origExpiryDate, $src['id']]);
                    }
                    if ($origBatchNumber && !$srcBatchNumber) {
                        $pdo->prepare("UPDATE stock_batches SET batch_number = ?, updated_at = NOW() WHERE id = ? AND (batch_number IS NULL OR batch_number = '')")
                            ->execute([$origBatchNumber, $src['id']]);
                    }
                    
                    $unitCost = (float)($src['unit_cost'] ?? 0);
                    $sellingPrice = (float)($src['selling_price'] ?? 0);
                    
                    if ($firstSourceBatchId === null) {
                        $firstSourceBatchId = (int)$src['id'];
                        $firstCost = $unitCost;
                        $recordedBatchNumber = $origBatchNumber;
                        $recordedExpiryDate = $origExpiryDate;
                    }
                    
                    // 1. Deduct from source batch
                    $pdo->prepare("UPDATE stock_batches SET quantity = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$newSrcQty, $src['id']]);
                        
                    // 2. Add to destination store with identical batch_number and expiry_date
                    $chk = $pdo->prepare("SELECT id, expiry_date FROM stock_batches WHERE item_id = ? AND store_id = ? AND (is_active = 1 OR is_active IS NULL) AND batch_number <=> ? AND expiry_date <=> ? LIMIT 1");
                    $chk->execute([$itemId, $toStore, $origBatchNumber, $origExpiryDate]);
                    $existingDest = $chk->fetch();
                    
                    // If not exact match, and batch number is known, look for existing batch with same batch number in destination to prevent duplicates
                    if (!$existingDest && $origBatchNumber !== null) {
                        $chk2 = $pdo->prepare("SELECT id, expiry_date FROM stock_batches WHERE item_id = ? AND store_id = ? AND (is_active = 1 OR is_active IS NULL) AND batch_number = ? LIMIT 1");
                        $chk2->execute([$itemId, $toStore, $origBatchNumber]);
                        $existingDest2 = $chk2->fetch();
                        if ($existingDest2) {
                            $existingDest = $existingDest2;
                            if ($origExpiryDate && (empty($existingDest2['expiry_date']) || $existingDest2['expiry_date'] === '0000-00-00')) {
                                $pdo->prepare("UPDATE stock_batches SET expiry_date = ?, updated_at = NOW() WHERE id = ?")->execute([$origExpiryDate, $existingDest2['id']]);
                            }
                        }
                    }
                    
                    if ($existingDest) {
                        $pdo->prepare("UPDATE stock_batches SET quantity = quantity + ?, unit_cost = ?, selling_price = ?, is_active = 1, updated_at = NOW() WHERE id = ?")
                            ->execute([$deductQty, $unitCost, $sellingPrice, $existingDest['id']]);
                        $destBatchId = (int)$existingDest['id'];
                    } else {
                        $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, ?, NOW())")
                            ->execute([$itemId, $toStore, $origBatchNumber, $deductQty, $unitCost, $sellingPrice, $origExpiryDate, $userId]);
                        $destBatchId = (int)$pdo->lastInsertId();
                    }
                    
                    // 3. Record stock movements
                    $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by, created_at) VALUES (?, ?, ?, ?, 'transfer_out', 'stock_transfer', ?, ?, ?, ?, ?, NOW())")
                        ->execute([$itemId, $src['id'], $fromStore, $toStore, $transferId, $deductQty, $unitCost, 'Transfer ' . $transferNumber, $userId]);
                        
                    $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by, created_at) VALUES (?, ?, ?, ?, 'transfer_in', 'stock_transfer', ?, ?, ?, ?, ?, NOW())")
                        ->execute([$itemId, $destBatchId, $fromStore, $toStore, $transferId, $deductQty, $unitCost, 'Transfer ' . $transferNumber, $userId]);
                        
                    $remainingQty -= $deductQty;
                    $totalValue += $deductQty * $unitCost;
                }
                
                // Update or insert stock_transfer_items with actual batch, batch_number, expiry_date, and unit cost
                if (!empty($it['id'])) {
                    $pdo->prepare("UPDATE stock_transfer_items SET batch_id = ?, batch_number = ?, expiry_date = ?, unit_cost = ? WHERE id = ?")
                        ->execute([$firstSourceBatchId, $recordedBatchNumber, $recordedExpiryDate, $firstCost, $it['id']]);
                } else {
                    $pdo->prepare("INSERT INTO stock_transfer_items (transfer_id, item_id, batch_id, batch_number, expiry_date, quantity, unit_cost, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$transferId, $itemId, $firstSourceBatchId, $recordedBatchNumber, $recordedExpiryDate, $requestedQty, $firstCost, $it['notes'] ?? null]);
                }
            }
            
            return $totalValue;
        }
    }

    // === CREATE TRANSFER REQUEST ===
    if ($action === "create_transfer") {
        ensureTransferSchema($pdo);
        $fromStore = (int)($input['from_store_id'] ?? 0);
        $toStore = (int)($input['to_store_id'] ?? 0);
        $items = $input['items'] ?? [];
        $status = $input['status'] ?? 'draft';
        if (!$fromStore || !$toStore || empty($items)) error("from_store_id, to_store_id, and items required", 422);
        if ($fromStore === $toStore) error("From Store and To Store must be different", 422);
        
        $pdo->beginTransaction();
        try {
            $tfNum = 'TRF-' . date('Ymd') . '-' . str_pad(mt_rand(1,999), 3, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO stock_transfers (transfer_number, from_store_id, to_store_id, transfer_date, status, reason, created_by, created_at) VALUES (?,?,?,CURDATE(),?,?,?,NOW())")
                ->execute([$tfNum, $fromStore, $toStore, $status, $input['reason'] ?? '', $userId]);
            $tfId = (int)$pdo->lastInsertId();
            
            if ($status === 'approved') {
                $totalValue = processStockTransferMovements($pdo, $fromStore, $toStore, $items, $tfNum, $tfId, $userId);
                $pdo->prepare("UPDATE stock_transfers SET approved_by = ?, updated_at = NOW() WHERE id = ?")->execute([$userId, $tfId]);
                
                if ($totalValue > 0) {
                    $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, created_by, posted, posted_at, created_at) VALUES (CURDATE(),?,?,?,1,NOW(),NOW())")
                        ->execute([$tfNum, "Stock transfer approved: Store $fromStore → Store $toStore", $userId]);
                    $jeId = (int)$pdo->lastInsertId();
                    $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")->execute([$jeId, $toStore, 'Stock received (transfer)', $totalValue]);
                    $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")->execute([$jeId, $fromStore, 'Stock sent (transfer)', $totalValue]);
                }
            } else {
                $itemStmt = $pdo->prepare("INSERT INTO stock_transfer_items (transfer_id, item_id, batch_id, batch_number, expiry_date, quantity, unit_cost, notes) VALUES (?,?,?,?,?,?,?,?)");
                foreach ($items as $it) {
                    $itemId = (int)$it['item_id'];
                    $batchId = !empty($it['batch_id']) ? (int)$it['batch_id'] : null;
                    $qty = (float)$it['quantity'];
                    $cost = 0;
                    $bNum = !empty(trim((string)($it['batch_number'] ?? ''))) ? trim((string)$it['batch_number']) : null;
                    $bExp = normalizeExpiryDate($it['expiry_date'] ?? null);
                    if ($batchId) {
                        $bStmt = $pdo->prepare("SELECT unit_cost, batch_number, expiry_date FROM stock_batches WHERE id=?");
                        $bStmt->execute([$batchId]);
                        $bRow = $bStmt->fetch();
                        if ($bRow) {
                            $cost = (float)($bRow['unit_cost'] ?: 0);
                            if (!$bNum && !empty(trim((string)($bRow['batch_number'] ?? '')))) {
                                $bNum = trim((string)$bRow['batch_number']);
                            }
                            if (!$bExp && !empty($bRow['expiry_date'])) {
                                $bExp = normalizeExpiryDate($bRow['expiry_date']);
                            }
                            // Heal source batch in stock_batches if missing
                            if ($bExp && (empty($bRow['expiry_date']) || $bRow['expiry_date'] === '0000-00-00')) {
                                $pdo->prepare("UPDATE stock_batches SET expiry_date = ? WHERE id = ?")->execute([$bExp, $batchId]);
                            }
                            if ($bNum && empty(trim((string)($bRow['batch_number'] ?? '')))) {
                                $pdo->prepare("UPDATE stock_batches SET batch_number = ? WHERE id = ?")->execute([$bNum, $batchId]);
                            }
                        }
                    }
                    $itemStmt->execute([$tfId, $itemId, $batchId, $bNum, $bExp, $qty, $cost, $it['notes'] ?? null]);
                }
            }
            
            $pdo->commit();
            success(['id'=>$tfId, 'transfer_number'=>$tfNum], $status === 'approved' ? 'Transfer completed and stock transferred successfully with batch & expiration dates' : 'Transfer created');
        } catch (Exception $e) { $pdo->rollBack(); error("Failed: ".$e->getMessage()); }
    }

    // === UPDATE TRANSFER STATUS ===
    if ($action === "update_transfer_status") {
        $tfId = (int)($input['transfer_id'] ?? 0);
        if (!$tfId) error("transfer_id required", 422);
        $status = $input['status'] ?? '';
        if (!in_array($status, ['draft','pending','approved','cancelled'])) error("Invalid status", 422);
        $pdo->prepare("UPDATE stock_transfers SET status=? WHERE id=?")->execute([$status, $tfId]);
        if (!empty($input['note'])) {
            $pdo->prepare("UPDATE stock_transfers SET notes=CONCAT(COALESCE(notes,''), CASE WHEN notes IS NULL OR notes='' THEN '' ELSE ' | ' END, ?) WHERE id=?")->execute([$input['note'], $tfId]);
        }
        success(null, "Transfer status updated");
    }

    // === APPROVE TRANSFER (moves stock + journal entry) ===
    if ($action === "approve_transfer") {
        $tfId = (int)($input['transfer_id'] ?? 0);
        if (!$tfId) error("transfer_id required", 422);
        
        $tf = $pdo->prepare("SELECT * FROM stock_transfers WHERE id=?"); $tf->execute([$tfId]); $transfer = $tf->fetch();
        if (!$transfer) error("Transfer not found", 404);
        if ($transfer['status'] !== 'pending' && $transfer['status'] !== 'draft') error("Transfer must be pending or draft to approve");
        
        $items = $pdo->prepare("SELECT * FROM stock_transfer_items WHERE transfer_id=?"); $items->execute([$tfId]);
        $tfItems = $items->fetchAll();
        if (empty($tfItems)) error("Transfer has no items", 422);
        
        $fromStore = (int)$transfer['from_store_id'];
        $toStore = (int)$transfer['to_store_id'];
        
        $pdo->beginTransaction();
        try {
            $totalValue = processStockTransferMovements($pdo, $fromStore, $toStore, $tfItems, $transfer['transfer_number'], $tfId, $userId);
            
            // Update status to approved
            $pdo->prepare("UPDATE stock_transfers SET status='approved', approved_by=?, updated_at=NOW() WHERE id=?")->execute([$userId, $tfId]);
            if (!empty($input['note'])) {
                $pdo->prepare("UPDATE stock_transfers SET notes=CONCAT(COALESCE(notes,''), CASE WHEN notes IS NULL OR notes='' THEN '' ELSE ' | ' END, ?) WHERE id=?")->execute([$input['note'], $tfId]);
            }
            
            // Journal: Dr To Store, Cr From Store
            if ($totalValue > 0) {
                $ref = $transfer['transfer_number'];
                $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, created_by, posted, posted_at, created_at) VALUES (CURDATE(),?,?,?,1,NOW(),NOW())")
                    ->execute([$ref, "Stock transfer approved: Store $fromStore → Store $toStore", $userId]);
                $jeId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")->execute([$jeId, $toStore, 'Stock received (transfer)', $totalValue]);
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")->execute([$jeId, $fromStore, 'Stock sent (transfer)', $totalValue]);
            }
            
            $pdo->commit();
            success(null, "Transfer approved. Stock successfully transferred with batch & expiration dates! Value: " . number_format($totalValue) . " RWF");
        } catch (Exception $e) { $pdo->rollBack(); error("Failed: " . $e->getMessage()); }
    }

    // === TRANSFER STOCK BETWEEN STORES (direct transfer) ===
    if ($action === "transfer_stock") {
        $fromStore = (int)($input['from_store_id'] ?? 0);
        $toStore = (int)($input['to_store_id'] ?? 0);
        $items = $input['items'] ?? [];
        if (!$fromStore || !$toStore) error("from_store_id and to_store_id required", 422);
        if ($fromStore === $toStore) error("From and To stores must be different", 422);
        
        // Support single item or items array
        if (empty($items) && !empty($input['item_id']) && !empty($input['quantity'])) {
            $items = [[
                'item_id' => (int)$input['item_id'],
                'batch_id' => !empty($input['batch_id']) ? (int)$input['batch_id'] : null,
                'batch_number' => !empty(trim((string)($input['batch_number'] ?? ''))) ? trim((string)$input['batch_number']) : null,
                'expiry_date' => normalizeExpiryDate($input['expiry_date'] ?? null),
                'quantity' => (float)$input['quantity'],
                'notes' => $input['notes'] ?? null
            ]];
        }
        if (empty($items)) error("Items required for transfer", 422);
        
        $pdo->beginTransaction();
        try {
            $tfNum = 'TRF-' . date('Ymd') . '-' . str_pad(mt_rand(1,999), 3, '0', STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO stock_transfers (transfer_number, from_store_id, to_store_id, transfer_date, status, reason, created_by, approved_by, created_at) VALUES (?,?,?,CURDATE(),'approved',?,?,?,NOW())")
                ->execute([$tfNum, $fromStore, $toStore, $input['notes'] ?? ($input['reason'] ?? 'Direct Transfer'), $userId, $userId]);
            $tfId = (int)$pdo->lastInsertId();
            
            $totalValue = processStockTransferMovements($pdo, $fromStore, $toStore, $items, $tfNum, $tfId, $userId);
            
            // Journal entry
            if ($totalValue > 0) {
                $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, created_by, posted, posted_at, created_at) VALUES (CURDATE(),?,?,?,1,NOW(),NOW())")
                    ->execute([$tfNum, "Stock transfer: Store $fromStore → Store $toStore", $userId]);
                $jeId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")->execute([$jeId, $toStore, 'Stock received (transfer)', $totalValue]);
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")->execute([$jeId, $fromStore, 'Stock sent (transfer)', $totalValue]);
            }
            
            $pdo->commit();
            success(['id' => $tfId, 'transfer_number' => $tfNum], "Stock transferred successfully with batch numbers and expiration dates! Value: " . number_format($totalValue) . " RWF");
        } catch (Exception $e) { $pdo->rollBack(); error("Failed: " . $e->getMessage()); }
    }

    // === WRITE OFF EXPIRED ITEMS ===
    if ($action === "write_off_expired") {
        $expired = $pdo->query("SELECT sb.* FROM stock_batches sb WHERE sb.is_active=1 AND sb.quantity>0 AND sb.expiry_date IS NOT NULL AND sb.expiry_date < CURDATE()")->fetchAll();
        if (empty($expired)) error("No expired items found");
        
        $pdo->beginTransaction();
        try {
            $totalValue = 0;
            $storeValues = []; // track per store for journal
            foreach ($expired as $b) {
                $val = (float)$b['quantity'] * (float)$b['unit_cost'];
                $totalValue += $val;
                $storeId = (int)$b['store_id'];
                if (!isset($storeValues[$storeId])) $storeValues[$storeId] = 0;
                $storeValues[$storeId] += $val;
                // Zero out the batch
                $pdo->prepare("UPDATE stock_batches SET quantity=0, is_active=0 WHERE id=?")->execute([$b['id']]);
                // Movement record
                $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, notes, created_by, created_at) VALUES (?,?,?,NULL,'expiry',?,?,?,NOW())")
                    ->execute([$b['item_id'], $b['id'], $storeId, $b['quantity'], $b['unit_cost'], 'Expired - written off', $userId]);
            }
            // Journal: Dr Drug Expiration Expense (1021), Cr each Store account
            if ($totalValue > 0) {
                $ref = 'EXP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, created_by, posted, posted_at, created_at) VALUES (CURDATE(),?,?,?,1,NOW(),NOW())")
                    ->execute([$ref, 'Expired stock write-off', $userId]);
                $jeId = (int)$pdo->lastInsertId();
                // Debit expense account 1021
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,1021,?,?,0)")->execute([$jeId, 'Drug expiration loss', $totalValue]);
                // Credit each store
                foreach ($storeValues as $sid => $sval) {
                    $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")->execute([$jeId, $sid, 'Expired stock removed', $sval]);
                }
            }
            $pdo->commit();
            success(null, count($expired)." batches written off. Total loss: ".number_format($totalValue)." RWF. Journal: $ref");
        } catch (Exception $e) { $pdo->rollBack(); error("Failed: ".$e->getMessage()); }
    }


    // === UPDATE PO STATUS (POST) ===
    if ($action === "update_po_status") {
        $poId = (int)($input['po_id'] ?? $id ?? 0);
        if (!$poId) error("PO ID required", 422);
        if (empty($input["status"])) error("Status required", 422);
        $allowed = ["draft","sent","confirmed","partially_received","received","cancelled"];
        if (!in_array($input["status"], $allowed)) error("Invalid status", 422);
        $pdo->prepare("UPDATE purchase_orders SET status=? WHERE id=?")->execute([$input["status"], $poId]);
        if (!empty($input['note'])) {
            $pdo->prepare("UPDATE purchase_orders SET notes=CONCAT(COALESCE(notes,''),' | ',?) WHERE id=?")->execute([$input['note'], $poId]);
        }
        success(null, "PO status updated to: ".$input["status"]);
    }
    // === CREATE PURCHASE ORDER ===
    if ($action === "create_purchase_order") {
        if (empty($input["supplier_id"]) || empty($input["items"]) || !is_array($input["items"])) 
            error("Supplier and items required", 422);
        $pdo->beginTransaction();
        try {
            $poNumber = "PO-" . date("Ymd") . "-" . str_pad(mt_rand(1, 9999), 4, "0", STR_PAD_LEFT);
            $subtotal = 0;
            foreach ($input["items"] as $item) {
                $subtotal += (float)($item["quantity_ordered"] ?? 0) * (float)($item["unit_price"] ?? 0);
            }
            $taxRate = (float)($input["tax_rate"] ?? 0);
            $taxAmount = $subtotal * ($taxRate / 100);
            $totalAmount = $subtotal + $taxAmount;

            $stmt = $pdo->prepare("INSERT INTO purchase_orders (po_number, pr_id, supplier_id, order_date, delivery_date, 
                shipping_method, tax_rate, subtotal, tax_amount, total_amount, status, delivery_address, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $poNumber,
                !empty($input["pr_id"]) ? (int)$input["pr_id"] : null,
                (int)$input["supplier_id"],
                $input["order_date"] ?? date("Y-m-d"),
                $input["delivery_date"] ?? null,
                $input["shipping_method"] ?? null,
                $taxRate, $subtotal, $taxAmount, $totalAmount,
                $input["status"] ?? "draft",
                $input["delivery_address"] ?? null,
                $input["notes"] ?? null
            ]);
            $poId = (int)$pdo->lastInsertId();

            foreach ($input["items"] as $item) {
                if (empty($item["item_id"]) && empty($item["item_description"])) continue;
                $qty = (float)($item["quantity_ordered"] ?? 0);
                $price = (float)($item["unit_price"] ?? 0);
                $pdo->prepare("INSERT INTO purchase_order_items (po_id, item_id, item_description, quantity_ordered, uom_id, unit_price, total_price, notes) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$poId,
                        !empty($item["item_id"]) ? (int)$item["item_id"] : null,
                        $item["item_description"] ?? null,
                        $qty,
                        !empty($item["uom_id"]) ? (int)$item["uom_id"] : null,
                        $price,
                        $qty * $price,
                        $item["notes"] ?? null
                    ]);
            }

            // Update PR status if linked
            if (!empty($input["pr_id"])) {
                $pdo->prepare("UPDATE purchase_requests SET status='ordered' WHERE id=?")->execute([(int)$input["pr_id"]]);
            }

            $pdo->commit();
            success(["id" => $poId, "po_number" => $poNumber], "Purchase order created", 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Failed to create purchase order: " . $e->getMessage(), 500);
        }
    }

    // === RECEIVE GOODS (create GRN) ===
    if ($action === "receive_goods") {
        if (empty($input["po_id"]) || empty($input["items"]) || !is_array($input["items"])) 
            error("PO ID and items required", 422);
        $pdo->beginTransaction();
        try {
            $grnNumber = "GRN-" . date("Ymd") . "-" . str_pad(mt_rand(1, 9999), 4, "0", STR_PAD_LEFT);
            $storeId = !empty($input["store_id"]) ? (int)$input["store_id"] : null;

            $stmt = $pdo->prepare("INSERT INTO goods_receiving_notes (grn_number, po_id, supplier_id, received_date, 
                delivery_note_number, receiving_type, quality_status, inspection_notes, store_id, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $grnNumber,
                (int)$input["po_id"],
                !empty($input["supplier_id"]) ? (int)$input["supplier_id"] : null,
                $input["received_date"] ?? date("Y-m-d"),
                $input["delivery_note_number"] ?? null,
                $input["receiving_type"] ?? "full",
                $input["quality_status"] ?? "passed",
                $input["inspection_notes"] ?? null,
                $storeId,
                $input["notes"] ?? null
            ]);
            $grnId = (int)$pdo->lastInsertId();

            foreach ($input["items"] as $item) {
                if (empty($item["po_item_id"]) && empty($item["item_id"])) continue;
                $qtyOrdered = (float)($item["quantity_ordered"] ?? 0);
                $qtyReceived = (float)($item["quantity_received"] ?? 0);
                $qtyAccepted = (float)($item["quantity_accepted"] ?? $qtyReceived);
                $qtyRejected = (float)($item["quantity_rejected"] ?? ($qtyReceived - $qtyAccepted));

                $pdo->prepare("INSERT INTO goods_receiving_items (grn_id, po_item_id, item_id, batch_number, expiry_date,
                    quantity_ordered, quantity_received, quantity_accepted, quantity_rejected, unit_cost, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([
                        $grnId,
                        !empty($item["po_item_id"]) ? (int)$item["po_item_id"] : null,
                        !empty($item["item_id"]) ? (int)$item["item_id"] : null,
                        $item["batch_number"] ?? null,
                        $item["expiry_date"] ?? null,
                        $qtyOrdered, $qtyReceived, $qtyAccepted, $qtyRejected,
                        (float)($item["unit_cost"] ?? 0),
                        $item["notes"] ?? null
                    ]);

                // Add to stock if store_id specified and items accepted
                if ($storeId && !empty($item["item_id"]) && $qtyAccepted > 0) {
                    $batchNo = $item["batch_number"] ?? ("GRN-" . date("Ymd") . "-" . str_pad(mt_rand(1, 9999), 4, "0", STR_PAD_LEFT));
                    $pdo->prepare("INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, notes) VALUES (?,?,?,?,?,?,?,?,?)")
                        ->execute([
                            (int)$item["item_id"], $storeId, $batchNo, $qtyAccepted,
                            (float)($item["unit_cost"] ?? 0),
                            $item["selling_price"] ?? null,
                            $item["expiry_date"] ?? null,
                            $input["received_date"] ?? date("Y-m-d"),
                            "Received via GRN #$grnNumber"
                        ]);
                }
            }

            // Update PO status
            $poId = (int)$input["po_id"];
            $poItems = $pdo->prepare("SELECT SUM(poi.quantity_ordered) as total_ordered, COALESCE(SUM(gri.quantity_accepted),0) as total_received 
                FROM purchase_order_items poi 
                LEFT JOIN goods_receiving_items gri ON gri.po_item_id=poi.id 
                WHERE poi.po_id=?");
            $poItems->execute([$poId]);
            $poData = $poItems->fetch();
            if ($poData["total_received"] >= $poData["total_ordered"]) {
                $pdo->prepare("UPDATE purchase_orders SET status='received' WHERE id=?")->execute([$poId]);
            } else {
                $pdo->prepare("UPDATE purchase_orders SET status='partially_received' WHERE id=?")->execute([$poId]);
            }

            $pdo->commit();
            success(["id" => $grnId, "grn_number" => $grnNumber], "Goods received", 201);
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Failed to receive goods: " . $e->getMessage(), 500);
        }
    }

    // === CONSUME ITEM (COGS / Expense / Income) ===
    if ($action === "consume_item") {
        $itemId = (int)($input['item_id'] ?? 0);
        $storeId = (int)($input['store_id'] ?? 0);
        $quantity = (float)($input['quantity'] ?? 0);
        $reason = trim($input['reason'] ?? '');
        $entryType = trim($input['entry_type'] ?? 'cogs');
        $cashAccountId = (int)($input['cash_account_id'] ?? 3); // Default to Cash & Cash equivalents (id=3)

        if (!$itemId || !$storeId || $quantity <= 0) error("item_id, store_id, and quantity (>0) required", 422);
        if (!in_array($entryType, ['cogs','expense','income'])) error("entry_type must be income or expense", 422);
        // Treat 'cogs' as 'income' (always record both COGS + Income together)
        if ($entryType === 'cogs') $entryType = 'income';
        if ($entryType === 'income' && !$cashAccountId) error("cash_account_id required for income entry", 422);

        // Validate item exists
        $itemStmt = $pdo->prepare("SELECT id, name, selling_price FROM items WHERE id=? AND is_active=1");
        $itemStmt->execute([$itemId]);
        $item = $itemStmt->fetch();
        if (!$item) error("Item not found", 404);

        // Check sufficient stock in the store
        $stockStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) as total FROM stock_batches WHERE item_id=? AND store_id=? AND is_active=1 AND quantity > 0");
        $stockStmt->execute([$itemId, $storeId]);
        $totalStock = (float)$stockStmt->fetch()['total'];
        if ($totalStock < $quantity) error("Insufficient stock. Available: $totalStock, Requested: $quantity", 409);

        $pdo->beginTransaction();
        try {
            // Deduct stock FIFO (oldest batch first)
            $batches = $pdo->prepare("SELECT id, quantity, unit_cost, batch_number FROM stock_batches WHERE item_id=? AND store_id=? AND is_active=1 AND quantity > 0 ORDER BY received_date ASC, created_at ASC, id ASC");
            $batches->execute([$itemId, $storeId]);
            $remaining = $quantity;
            $totalCost = 0;
            $weightedCost = 0;

            $insertedMovements = [];
            while ($row = $batches->fetch()) {
                if ($remaining <= 0) break;
                $deduct = min((float)$row['quantity'], $remaining);
                $newQty = (float)$row['quantity'] - $deduct;
                $pdo->prepare("UPDATE stock_batches SET quantity=? WHERE id=?")->execute([$newQty, $row['id']]);
                
                // Record stock movement for this specific batch
                $pdo->prepare("INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, quantity, unit_cost, reference_type, notes, created_by, created_at) VALUES (?,?,?,NULL,'consumption',?,?,'consumption',?,?,NOW())")
                    ->execute([$itemId, $row['id'], $storeId, $deduct, (float)$row['unit_cost'], $reason ?: 'Item consumed', $userId]);
                $insertedMovements[] = (int)$pdo->lastInsertId();

                $totalCost += $deduct * (float)$row['unit_cost'];
                $remaining -= $deduct;
            }

            // The cost amount = total cost computed from batches (FIFO weighted)
            $costAmount = $totalCost;
            $sellingPrice = (float)$item['selling_price'];
            $incomeAmount = $quantity * $sellingPrice;

            // Generate reference number
            $ref = 'CON-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
            $description = 'Consumption: ' . $item['name'] . ' x' . $quantity . ($reason ? ' - ' . $reason : '');
            $mainJeId = null;

            // Create journal entries based on entry_type
            if ($entryType === 'income') {
                // Journal 1: Dr Drugs COGS (1046), Cr Store account (store_id) — at cost
                $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, created_by, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'consumption',?,?,1,NOW(),NOW())")->execute([$ref, $description . ' (COGS)', $itemId, $userId]);
                $jeId = (int)$pdo->lastInsertId();
                $mainJeId = $jeId;
                $lineDesc = $item['name'] . ' x' . $quantity . ' COGS';
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")->execute([$jeId, 1046, $lineDesc, $costAmount]);
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")->execute([$jeId, $storeId, $lineDesc, $costAmount]);

                // Journal 2: Dr Cash/Bank (selected account), Cr Drug & Consumable Income (1006) — at selling price
                $ref2 = $ref . '-INC';
                $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, created_by, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'consumption',?,?,1,NOW(),NOW())")->execute([$ref2, $description . ' (Income)', $itemId, $userId]);
                $jeId2 = (int)$pdo->lastInsertId();
                $lineDesc2 = $item['name'] . ' x' . $quantity . ' income';
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")->execute([$jeId2, $cashAccountId, $lineDesc2, $incomeAmount]);
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")->execute([$jeId2, 1006, $lineDesc2, $incomeAmount]);

            } elseif ($entryType === 'expense') {
                // Dr Medical Expenses (1054), Cr Store account (store_id)
                $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, created_by, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'consumption',?,?,1,NOW(),NOW())")->execute([$ref, $description, $itemId, $userId]);
                $jeId = (int)$pdo->lastInsertId();
                $mainJeId = $jeId;
                $lineDesc = $item['name'] . ' x' . $quantity . ' consumed (Expense)';
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")->execute([$jeId, 1054, $lineDesc, $costAmount]);
                $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")->execute([$jeId, $storeId, $lineDesc, $costAmount]);
            }

            // Link movements to the journal entry ID and reference code
            if ($mainJeId && !empty($insertedMovements)) {
                $noteWithRef = ($reason ? $reason . ' ' : '') . "[Ref: $ref]";
                foreach ($insertedMovements as $smId) {
                    $pdo->prepare("UPDATE stock_movements SET reference_id=?, notes=? WHERE id=?")->execute([$mainJeId, $noteWithRef, $smId]);
                }
            }

            $pdo->commit();
            success(['reference' => $ref, 'journal_entry_id' => $mainJeId], "Item consumed successfully. Ref: $ref. Cost: " . number_format($costAmount) . " RWF");
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Consumption failed: " . $e->getMessage(), 500);
        }
    }

    // === MOVE STOCK BETWEEN ITEMS (for consolidating duplicates) ===
    if ($action === "move_stock_between_items") {
        $sourceItemId = (int)($input['source_item_id'] ?? 0);
        $destItemId = (int)($input['dest_item_id'] ?? 0);
        $sourceStockId = (int)($input['source_stock_id'] ?? 0);
        $qty = (float)($input['quantity'] ?? 0);
        $reason = trim($input['reason'] ?? 'Stock consolidation');
        $userId = !empty($input['user_id']) ? (int)$input['user_id'] : (!empty($input['created_by']) ? (int)$input['created_by'] : $sessionUserId);
        
        if (!$sourceItemId || !$destItemId || !$sourceStockId || $qty <= 0) {
            error('source_item_id, dest_item_id, source_stock_id, and quantity required', 422);
        }
        
        if ($sourceItemId === $destItemId) error('Source and destination must be different items', 422);
        
        $pdo->beginTransaction();
        try {
            // Get source stock details from stock_batches
            $stmt = $pdo->prepare("SELECT * FROM stock_batches WHERE id = ? AND item_id = ? AND is_active = 1 FOR UPDATE");
            $stmt->execute([$sourceStockId, $sourceItemId]);
            $srcStock = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$srcStock) error('Source stock not found', 404);
            if ($srcStock['quantity'] < $qty) error('Insufficient quantity in source batch', 422);
            
            // Reduce source quantity
            $newSrcQty = $srcStock['quantity'] - $qty;
            $pdo->prepare("UPDATE stock_batches SET quantity = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newSrcQty, $sourceStockId]);
            
            // Find or create matching batch in destination item
            $destStmt = $pdo->prepare("
                SELECT id FROM stock_batches 
                WHERE item_id = ? AND store_id = ? AND batch_number = ? AND is_active = 1
                LIMIT 1 FOR UPDATE
            ");
            $destStmt->execute([$destItemId, $srcStock['store_id'], $srcStock['batch_number']]);
            $destStock = $destStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($destStock) {
                // Update existing destination batch
                $pdo->prepare("UPDATE stock_batches SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$qty, $destStock['id']]);
                $destStockId = $destStock['id'];
            } else {
                // Create new batch in destination
                $pdo->prepare("
                    INSERT INTO stock_batches (item_id, store_id, batch_number, quantity, unit_cost, selling_price, expiry_date, received_date, is_active, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                ")->execute([
                    $destItemId,
                    $srcStock['store_id'],
                    $srcStock['batch_number'],
                    $qty,
                    $srcStock['unit_cost'],
                    $srcStock['selling_price'],
                    $srcStock['expiry_date'],
                    $srcStock['received_date'],
                    $userId
                ]);
                $destStockId = (int)$pdo->lastInsertId();
            }
            
            // Record the movement in stock_movements (outgoing from source, incoming to dest)
            $ref = 'MOVE-' . date('YmdHis');
            $notes = "Moved from item #$sourceItemId to #$destItemId: $reason";
            
            // Outgoing from source (from_store_id removes stock)
            $pdo->prepare("
                INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by)
                VALUES (?, ?, ?, NULL, 'adjustment', 'item_consolidation', ?, ?, ?, ?, ?)
            ")->execute([$sourceItemId, $sourceStockId, $srcStock['store_id'], $destItemId, $qty, $srcStock['unit_cost'], $notes, $userId]);
            
            // Incoming to destination (to_store_id adds stock)
            $pdo->prepare("
                INSERT INTO stock_movements (item_id, batch_id, from_store_id, to_store_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by)
                VALUES (?, ?, NULL, ?, 'adjustment', 'item_consolidation', ?, ?, ?, ?, ?)
            ")->execute([$destItemId, $destStockId, $srcStock['store_id'], $sourceItemId, $qty, $srcStock['unit_cost'], $notes, $userId]);
            
            $pdo->commit();
            success([
                'source_new_qty' => $newSrcQty,
                'dest_stock_id' => $destStockId,
                'reference' => $ref
            ], "Successfully moved $qty units. Ref: $ref");
        } catch (Exception $e) {
            $pdo->rollBack();
            error("Move stock failed: " . $e->getMessage(), 500);
        }
    }

    // === UPDATE STOCK BATCH (batch number, expiry date, and optional quantity adjustment) ===
    if ($action === "update_stock_batch") {
        $input = json_decode(file_get_contents("php://input"), true) ?? [];
        $batchId = (int)($input['batch_id'] ?? 0);
        $batchNumber = trim($input['batch_number'] ?? '');
        $expiryDate = normalizeExpiryDate($input['expiry_date'] ?? null);
        
        if (!$batchId) error("batch_id required", 422);
        if (!$batchNumber) error("batch_number required", 422);
        
        // If quantity is passed and different from current quantity, route through processStockAdjustment
        if (isset($input['new_quantity']) || isset($input['quantity'])) {
            $targetQty = isset($input['new_quantity']) ? (float)$input['new_quantity'] : (float)$input['quantity'];
            $chk = $pdo->prepare("SELECT quantity FROM stock_batches WHERE id = ?");
            $chk->execute([$batchId]);
            $currentQty = (float)$chk->fetchColumn();
            
            if (abs($targetQty - $currentQty) > 0.0001) {
                $pdo->prepare("UPDATE stock_batches SET batch_number = ?, expiry_date = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$batchNumber, $expiryDate, $batchId]);
                $input['batch_id'] = $batchId;
                $input['new_quantity'] = $targetQty;
                processStockAdjustment($pdo, $input, $batchId, $userId, $sessionUserId);
                return;
            }
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE stock_batches SET batch_number = ?, expiry_date = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$batchNumber, $expiryDate, $batchId]);
            
            success([
                'batch_id' => $batchId,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate
            ], "Stock batch updated successfully");
        } catch (Exception $e) {
            error("Update failed: " . $e->getMessage(), 500);
        }
    }

    // === ADJUST STOCK (creates stock batch/movement & journal entries) ===
    if ($action === "adjust_stock") {
        processStockAdjustment($pdo, $input, $id, $userId, $sessionUserId);
    }

    // === REVERT CONSUMPTION (Restores stock & reverses journal entries) ===
    if ($action === "revert_consumption") {
        processRevertConsumption($pdo, $input, $userId, $sessionUserId);
    }

    error("Unknown action", 404);
}
// ============================================
// PUT
// ============================================
if ($method === "PUT") {
    $input = json_decode(file_get_contents("php://input"), true) ?? [];
    $userId = !empty($input['user_id']) ? (int)$input['user_id'] : (!empty($input['created_by']) ? (int)$input['created_by'] : $sessionUserId);

    // === UPDATE ITEM ===
    if ($action === "update_item" && $id) {
        $allowed = ["name","item_code","generic_name","category_id","uom_id","is_medication",
            "is_controlled","description","is_active","unit_price","selling_price"];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $sets[] = "$f=?";
                $params[] = $input[$f] === "" ? null : $input[$f];
            }
        }
        if (!$sets) error("No fields to update", 422);
        $params[] = $id;
        $pdo->prepare("UPDATE items SET " . implode(",", $sets) . " WHERE id=?")->execute($params);
        
        // Save insurance prices if provided
        $insPricing = $input['insurance_prices'] ?? [];
        if (!empty($insPricing)) {
            $insStmt = $pdo->prepare("INSERT INTO item_insurance_prices (item_id, insurance_id, price, is_exceptional, additional_insurance_selling_price, insurance_pays, patient_topup) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE price=VALUES(price), is_exceptional=VALUES(is_exceptional), additional_insurance_selling_price=VALUES(additional_insurance_selling_price), insurance_pays=VALUES(insurance_pays), patient_topup=VALUES(patient_topup)");
            foreach ($insPricing as $ins) {
                $insId = (int)($ins['insurance_id'] ?? 0);
                $price = (float)($ins['price'] ?? 0);
                $isExc = (int)($ins['is_exceptional'] ?? 0);
                $addPrice = (float)($ins['additional_insurance_selling_price'] ?? 0);
                $topup = (float)($ins['patient_topup'] ?? 0);
                if ($insId > 0) {
                    $insStmt->execute([$id, $insId, $price, $isExc, $addPrice, $price, $topup]);
                }
            }
        }
        
        success(null, "Item updated");
    }

    // === UPDATE SUPPLIER ===
    if ($action === "update_supplier" && $id) {
        $allowed = ["supplier_code","company_name","contact_person","phone","email","address","category","payment_terms","tax_id","notes","status"];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $sets[] = "$f=?";
                $params[] = $input[$f] === "" ? null : $input[$f];
            }
        }
        if (!$sets) error("No fields to update", 422);
        $params[] = $id;
        $pdo->prepare("UPDATE suppliers SET " . implode(",", $sets) . " WHERE id=?")->execute($params);
        success(null, "Supplier updated");
    }

    // === UPDATE STOCK BATCH ===
    if ($action === "update_batch" && $id) {
        $allowed = ["quantity","unit_cost","selling_price","reorder_level","min_stock_level","max_stock_level",
            "expiry_date","received_date","batch_number","location_rack","notes","is_active"];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $sets[] = "$f=?";
                if ($f === 'expiry_date') {
                    $params[] = normalizeExpiryDate($input[$f]);
                } else {
                    $params[] = $input[$f] === "" ? null : $input[$f];
                }
            }
        }
        if (!$sets) error("No fields to update", 422);
        $params[] = $id;
        $pdo->prepare("UPDATE stock_batches SET " . implode(",", $sets) . " WHERE id=?")->execute($params);
        success(null, "Batch updated");
    }

    // === UPDATE PURCHASE ORDER STATUS ===
    if ($action === "update_po_status" && $id) {
        if (empty($input["status"])) error("Status required", 422);
        $allowed = ["draft","sent","confirmed","partially_received","received","cancelled"];
        if (!in_array($input["status"], $allowed)) error("Invalid status", 422);
        $pdo->prepare("UPDATE purchase_orders SET status=? WHERE id=?")->execute([$input["status"], $id]);
        success(null, "Purchase order status updated");
    }

    // === UPDATE PURCHASE REQUEST STATUS ===
    if ($action === "update_pr_status") {
        $prId = $id ?: (int)($input['pr_id'] ?? 0);
        if (!$prId) error("PR ID required", 422);
        if (empty($input["status"])) error("Status required", 422);
        $allowed = ["draft","pending","approved","rejected","ordered","partially_received","received","cancelled"];
        if (!in_array($input["status"], $allowed)) error("Invalid status", 422);
        $pdo->prepare("UPDATE purchase_requests SET status=? WHERE id=?")->execute([$input["status"], $prId]);
        if ($input["status"] === "approved") {
            $approvedBy = !empty($input["approved_by"]) ? (int)$input["approved_by"] : $userId;
            $pdo->prepare("UPDATE purchase_requests SET approved_by=?, approved_at=NOW() WHERE id=?")->execute([$approvedBy, $prId]);
        }
        success(null, "Purchase request status updated");
    }

    // === UPDATE TRANSFER STATUS ===
    if ($action === "update_transfer_status" && $id) {
        if (empty($input["status"])) error("Status required", 422);
        $allowed = ["draft","pending","approved","in_transit","received","cancelled"];
        if (!in_array($input["status"], $allowed)) error("Invalid status", 422);
        $pdo->prepare("UPDATE stock_transfers SET status=? WHERE id=?")->execute([$input["status"], $id]);
        success(null, "Transfer status updated");
    }

    // === UPDATE STORE ===
    if ($action === "update_store" && $id) {
        $allowed = ["name","code","type","location","is_active"];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $sets[] = "$f=?";
                $params[] = $input[$f] === "" ? null : $input[$f];
            }
        }
        if (!$sets) error("No fields to update", 422);
        $params[] = $id;
        $pdo->prepare("UPDATE stores SET " . implode(",", $sets) . " WHERE id=?")->execute($params);
        success(null, "Store updated");
    }

    // === UPDATE CATEGORY ===
    if ($action === "update_category" && $id) {
        $allowed = ["name","code","description","parent_id","is_active"];
        $sets = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $sets[] = "$f=?";
                $params[] = $input[$f] === "" ? null : $input[$f];
            }
        }
        if (!$sets) error("No fields to update", 422);
        $params[] = $id;
        $pdo->prepare("UPDATE item_categories SET " . implode(",", $sets) . " WHERE id=?")->execute($params);
        success(null, "Category updated");
    }

    // === ADJUST STOCK (creates stock batch/movement & journal entries) ===
    if ($action === "adjust_stock") {
        processStockAdjustment($pdo, $input, $id, $userId, $sessionUserId);
    }

    // === REVERT CONSUMPTION (Restores stock & reverses journal entries) ===
    if ($action === "revert_consumption") {
        processRevertConsumption($pdo, $input, $userId, $sessionUserId);
    }

    error("Unknown action", 404);
}
// ============================================
// DELETE
// ============================================
if ($method === "DELETE") {
    if ($action === "delete_item" && $id) {
        $pdo->prepare("UPDATE items SET is_active=0 WHERE id=?")->execute([$id]);
        success(null, "Item deactivated");
    }
    if ($action === "delete_supplier" && $id) {
        $pdo->prepare("UPDATE suppliers SET status='inactive' WHERE id=?")->execute([$id]);
        success(null, "Supplier deactivated");
    }
    if ($action === "delete_store" && $id) {
        $pdo->prepare("UPDATE stores SET is_active=0 WHERE id=?")->execute([$id]);
        success(null, "Store deactivated");
    }
    if ($action === "delete_category" && $id) {
        $pdo->prepare("UPDATE item_categories SET is_active=0 WHERE id=?")->execute([$id]);
        success(null, "Category deactivated");
    }
    if ($action === "delete_batch" && $id) {
        $pdo->prepare("UPDATE stock_batches SET is_active=0 WHERE id=?")->execute([$id]);
        success(null, "Batch deactivated");
    }
    if ($action === "delete_transfer" && $id) {
        $pdo->prepare("UPDATE stock_transfers SET status='cancelled' WHERE id=?")->execute([$id]);
        success(null, "Transfer cancelled");
    }
    error("Unknown action", 404);
}

error("Method not allowed", 405);
} catch (PDOException $e) {
    error("DB error: " . $e->getMessage(), 500);
}