<?php
// handlers/settings/service-catalog.php
// $pdo is already available from api.php
require_once __DIR__ . '/../../includes/request_schema.php'; // lab_store_id()
if (!isset($pdo)) {
    require_once __DIR__ . '/../../../includes/database.php';
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
}
if (!function_exists('success')) {
    function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
}
if (!function_exists('error')) {
    function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}
}

function ensureServiceCatalogItemsTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS service_catalog_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            item_id INT NOT NULL,
            default_quantity DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
            store_id INT NULL DEFAULT NULL,
            notes VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_service_item (service_id, item_id),
            INDEX idx_service (service_id),
            INDEX idx_item (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS lab_item_consumption (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NULL DEFAULT NULL,
            visit_id INT NULL DEFAULT NULL,
            patient_id INT NULL DEFAULT NULL,
            service_id INT NULL DEFAULT NULL,
            item_id INT NOT NULL,
            store_id INT NOT NULL,
            batch_id INT NULL DEFAULT NULL,
            quantity DECIMAL(12,4) NOT NULL,
            unit_cost DECIMAL(12,2) DEFAULT 0.00,
            status ENUM('confirmed','reversed') NOT NULL DEFAULT 'confirmed',
            stock_movement_id INT NULL DEFAULT NULL,
            consumed_by INT NULL DEFAULT NULL,
            consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order (order_id),
            INDEX idx_visit (visit_id),
            INDEX idx_patient (patient_id),
            INDEX idx_service (service_id),
            INDEX idx_item (item_id),
            INDEX idx_store (store_id),
            INDEX idx_consumed_at (consumed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $done = true;
    } catch (Exception $e) {}
}

ensureServiceCatalogItemsTable($pdo);

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? [];
$action = isset($_GET['action']) ? $_GET['action'] : ($input['action'] ?? null);

try {

    // GET - list or single
    if ($method === 'GET') {

        // action=insurance_prices — every service price the insurer has agreed (one call for the order search lists)
        if ($action === 'insurance_prices') {
            $insuranceId = isset($_GET['insurance_id']) ? (int)$_GET['insurance_id'] : 0;
            if (!$insuranceId) error('insurance_id is required', 422);
            $st = $pdo->prepare("SELECT service_id, price FROM insurance_procedure_prices WHERE insurance_id = ?");
            $st->execute([$insuranceId]);
            success($st->fetchAll());
        }

        // action=insurance_price — get insurance_procedure_prices for service+insurance combo
        if ($action === 'insurance_price') {
            $serviceId   = isset($_GET['service_id'])   ? (int)$_GET['service_id']   : 0;
            $insuranceId = isset($_GET['insurance_id']) ? (int)$_GET['insurance_id'] : 0;
            if (!$serviceId || !$insuranceId) error('service_id and insurance_id are required', 422);

            $stmt = $pdo->prepare("
                SELECT ipp.*, sc.procedure_name, sc.primary_health_facility,
                       sc.secondary_health_facility, sc.tertiary_health_facility
                FROM insurance_procedure_prices ipp
                JOIN service_catalog sc ON sc.id = ipp.service_id
                WHERE ipp.service_id = ? AND ipp.insurance_id = ?
                LIMIT 1
            ");
            $stmt->execute([$serviceId, $insuranceId]);
            $row = $stmt->fetch();
            success($row ?: null);
        }

        // action=linked_items — get items linked to a service or all services with their linked items
        if ($action === 'linked_items') {
            $serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
            $storeId   = (int)($_GET['store_id'] ?? 0) ?: lab_store_id($pdo);

            if ($serviceId > 0) {
                $stmt = $pdo->prepare("
                    SELECT sci.*, 
                           i.name AS item_name, i.item_code, i.generic_name, i.selling_price, i.unit_price,
                           ic.name AS category_name, ic.code AS category_code,
                           iu.name AS uom_name, iu.abbreviation AS uom_abbr,
                           st.name AS default_store_name,
                           COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND is_active = 1 AND quantity > 0), 0) AS total_stock,
                           COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND store_id = ? AND is_active = 1 AND quantity > 0), 0) AS lab_stock
                    FROM service_catalog_items sci
                    JOIN items i ON i.id = sci.item_id
                    LEFT JOIN item_categories ic ON ic.id = i.category_id
                    LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                    LEFT JOIN stores st ON st.id = sci.store_id
                    WHERE sci.service_id = ?
                    ORDER BY i.name ASC
                ");
                $stmt->execute([$storeId, $serviceId]);
                success($stmt->fetchAll());
            }

            // Return services with their linked items
            $search = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
            $speciality = trim($_GET['speciality'] ?? '');
            $withItemsOnly = isset($_GET['linked_only']) && ($_GET['linked_only'] == '1' || $_GET['linked_only'] === 'true');

            $where = [];
            $params = [];
            if ($search) {
                $like = "%$search%";
                $where[] = "(sc.procedure_name LIKE ? OR sc.ichi_code LIKE ? OR sc.speciality LIKE ? OR sc.rhic LIKE ?)";
                $params = [$like, $like, $like, $like];
            }
            if ($speciality) {
                $where[] = "sc.speciality = ?";
                $params[] = $speciality;
            }
            $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $pdo->prepare("
                SELECT sc.*,
                       (SELECT COUNT(*) FROM service_catalog_items WHERE service_id = sc.id) AS linked_count
                FROM service_catalog sc
                $ws
                ORDER BY sc.speciality, sc.procedure_name
            ");
            $stmt->execute($params);
            $services = $stmt->fetchAll();

            if ($withItemsOnly) {
                $services = array_values(array_filter($services, function($s) { return (int)$s['linked_count'] > 0; }));
            }

            // Load linked items for the returned services
            $serviceIds = array_map(function($s) { return (int)$s['id']; }, $services);
            $linkedMap = [];
            if (!empty($serviceIds)) {
                $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
                $itemsStmt = $pdo->prepare("
                    SELECT sci.id AS link_id, sci.service_id, sci.item_id, sci.default_quantity, sci.notes,
                           i.name AS item_name, i.item_code, ic.name AS category_name, iu.abbreviation AS uom_abbr,
                           COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND is_active = 1 AND quantity > 0), 0) AS total_stock,
                           COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND store_id = ? AND is_active = 1 AND quantity > 0), 0) AS lab_stock
                    FROM service_catalog_items sci
                    JOIN items i ON i.id = sci.item_id
                    LEFT JOIN item_categories ic ON ic.id = i.category_id
                    LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                    WHERE sci.service_id IN ($placeholders)
                    ORDER BY i.name ASC
                ");
                $itemsStmt->execute(array_merge([$storeId], $serviceIds));
                while ($row = $itemsStmt->fetch()) {
                    $linkedMap[$row['service_id']][] = $row;
                }
            }

            foreach ($services as &$s) {
                $s['linked_items'] = $linkedMap[$s['id']] ?? [];
            }
            success($services);
        }

        // action=inventory_items — list items from items table for linking selector
        if ($action === 'inventory_items') {
            $q = trim($_GET['q'] ?? ($_GET['search'] ?? ''));
            $catId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
            $storeId = (int)($_GET['store_id'] ?? 0) ?: lab_store_id($pdo);
            
            $w = ["i.is_active = 1"];
            $p = [];
            if ($q) {
                $w[] = "(i.name LIKE ? OR i.item_code LIKE ? OR i.generic_name LIKE ?)";
                $like = "%$q%";
                $p = array_merge($p, [$like, $like, $like]);
            }
            if ($catId) {
                $w[] = "i.category_id = ?";
                $p[] = $catId;
            }
            if (!empty($_GET['ids'])) {
                $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
                if ($ids) $w[] = "i.id IN (" . implode(',', $ids) . ")";
            }
            $ws = implode(' AND ', $w);
            $stmt = $pdo->prepare("
                SELECT i.id, i.name, i.item_code, i.generic_name, i.category_id,
                       ic.name AS category_name, iu.name AS uom_name, iu.abbreviation AS uom_abbr,
                       COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND is_active = 1 AND quantity > 0), 0) AS total_stock,
                       COALESCE((SELECT SUM(quantity) FROM stock_batches WHERE item_id = i.id AND store_id = ? AND is_active = 1 AND quantity > 0), 0) AS store_stock
                FROM items i
                LEFT JOIN item_categories ic ON ic.id = i.category_id
                LEFT JOIN item_uoms iu ON iu.id = i.uom_id
                WHERE $ws
                ORDER BY i.name ASC
                LIMIT 200
            ");
            $stmt->execute(array_merge([$storeId], $p));
            success($stmt->fetchAll());
        }

        // action=stores — list active stores
        if ($action === 'stores') {
            $stmt = $pdo->query("
                SELECT id, name, code, type, location 
                FROM stores 
                WHERE is_active = 1 
                ORDER BY CASE WHEN type = 'laboratory' OR code LIKE '%LAB%' OR name LIKE '%lab%' THEN 0 ELSE 1 END, name ASC
            ");
            success($stmt->fetchAll());
        }

        // action=specialities — list distinct specialities in service_catalog
        if ($action === 'specialities') {
            $stmt = $pdo->query("SELECT DISTINCT speciality FROM service_catalog WHERE speciality IS NOT NULL AND speciality != '' ORDER BY speciality ASC");
            success($stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM service_catalog WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) error('Service not found', 404);
            success($row);
        }
        $search = trim($_GET['search'] ?? '');
        $where = []; $params = [];
        if ($search) {
            $like = "%$search%";
            $where[] = "(procedure_name LIKE ? OR ichi_code LIKE ? OR speciality LIKE ? OR rhic LIKE ?)";
            $params = [$like, $like, $like, $like];
        }
        $st = $_GET['status'] ?? 'active';   // active | inactive | all
        if ($st === 'inactive') $where[] = 'sc.is_active = 0'; elseif ($st !== 'all') $where[] = 'sc.is_active = 1';
        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $pdo->prepare("
            SELECT sc.*,
                   (SELECT COUNT(*) FROM service_catalog_items WHERE service_id = sc.id) AS linked_items_count
            FROM service_catalog sc 
            $ws 
            ORDER BY sc.speciality, sc.procedure_name
        ");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // POST - create, import, or link
    if ($method === 'POST') {

        // action=link_item — connect an inventory item to a service
        if ($action === 'link_item') {
            $serviceId = (int)($input['service_id'] ?? 0);
            $itemId = (int)($input['item_id'] ?? 0);
            $qty = (float)($input['default_quantity'] ?? 1.0);
            $storeId = !empty($input['store_id']) ? (int)$input['store_id'] : null;
            $notes = trim($input['notes'] ?? '');

            if (!$serviceId || !$itemId) error('service_id and item_id are required', 422);
            if ($qty <= 0) $qty = 1.0;

            $stmt = $pdo->prepare("
                INSERT INTO service_catalog_items (service_id, item_id, default_quantity, store_id, notes, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE default_quantity = VALUES(default_quantity), store_id = VALUES(store_id), notes = VALUES(notes), updated_at = NOW()
            ");
            $stmt->execute([$serviceId, $itemId, $qty, $storeId, $notes]);
            success(['service_id' => $serviceId, 'item_id' => $itemId, 'default_quantity' => $qty], 'Inventory item connected successfully');
        }

        // action=unlink_item — remove connection
        if ($action === 'unlink_item') {
            $linkId = (int)($input['id'] ?? ($input['link_id'] ?? 0));
            $serviceId = (int)($input['service_id'] ?? 0);
            $itemId = (int)($input['item_id'] ?? 0);

            if ($linkId > 0) {
                $pdo->prepare("DELETE FROM service_catalog_items WHERE id=?")->execute([$linkId]);
            } elseif ($serviceId > 0 && $itemId > 0) {
                $pdo->prepare("DELETE FROM service_catalog_items WHERE service_id=? AND item_id=?")->execute([$serviceId, $itemId]);
            } else {
                error('id or (service_id and item_id) required', 422);
            }
            success(null, 'Inventory item disconnected successfully');
        }

        // action=update_linked_item
        if ($action === 'update_linked_item') {
            $linkId = (int)($input['id'] ?? 0);
            if (!$linkId) error('id required', 422);
            $qty = (float)($input['default_quantity'] ?? 1.0);
            $storeId = !empty($input['store_id']) ? (int)$input['store_id'] : null;
            $notes = trim($input['notes'] ?? '');

            $pdo->prepare("UPDATE service_catalog_items SET default_quantity = ?, store_id = ?, notes = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$qty, $storeId, $notes, $linkId]);
            success(null, 'Linked item updated');
        }

        // CSV Import action
        if ($action === 'import') {
            if (empty($input['rows']) || !is_array($input['rows'])) {
                error('No data rows provided', 422);
            }
            $rows = $input['rows'];
            $imported = 0;
            $errors = [];

            $stmt = $pdo->prepare("INSERT INTO service_catalog (rhic, speciality, ichi_code, procedure_name, primary_health_facility, secondary_health_facility, tertiary_health_facility, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

            foreach ($rows as $i => $row) {
                $procedure = trim($row['procedure_name'] ?? '');
                if (empty($procedure)) {
                    $errors[] = "Row " . ($i+2) . ": Missing procedure name";
                    continue;
                }
                try {
                    $stmt->execute([
                        trim($row['rhic'] ?? ''),
                        trim($row['speciality'] ?? ''),
                        trim($row['ichi_code'] ?? ''),
                        $procedure,
                        (float)($row['primary_health_facility'] ?? 0),
                        (float)($row['secondary_health_facility'] ?? 0),
                        (float)($row['tertiary_health_facility'] ?? 0)
                    ]);
                    $imported++;
                } catch (PDOException $e) {
                    $errors[] = "Row " . ($i+2) . ": " . $e->getMessage();
                }
            }

            $msg = "Successfully imported $imported of " . count($rows) . " services.";
            if ($errors) $msg .= " Errors: " . count($errors);
            success(['imported' => $imported, 'errors' => $errors], $msg);
        }

        // Regular single create
        if (empty($input['procedure_name'])) error('Procedure name is required', 422);

        $stmt = $pdo->prepare("INSERT INTO service_catalog (rhic, speciality, ichi_code, procedure_name, primary_health_facility, secondary_health_facility, tertiary_health_facility, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            trim($input['rhic'] ?? ''),
            trim($input['speciality'] ?? ''),
            trim($input['ichi_code'] ?? ''),
            trim($input['procedure_name']),
            (float)($input['primary_health_facility'] ?? 0),
            (float)($input['secondary_health_facility'] ?? 0),
            (float)($input['tertiary_health_facility'] ?? 0)
        ]);
        success(['id' => (int)$pdo->lastInsertId()], 'Service created', 201);
    }

    // PUT - update
    if ($method === 'PUT') {

        if ($action === 'update_linked_item') {
            $linkId = (int)($input['id'] ?? $id);
            if (!$linkId) error('id required', 422);
            $qty = (float)($input['default_quantity'] ?? 1.0);
            $storeId = !empty($input['store_id']) ? (int)$input['store_id'] : null;
            $notes = trim($input['notes'] ?? '');

            $pdo->prepare("UPDATE service_catalog_items SET default_quantity = ?, store_id = ?, notes = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$qty, $storeId, $notes, $linkId]);
            success(null, 'Linked item updated');
        }

        if (!$id) error('Service ID required', 422);

        $chk = $pdo->prepare("SELECT id FROM service_catalog WHERE id=?");
        $chk->execute([$id]);
        if (!$chk->fetch()) error('Service not found', 404);

        $allowed = ['rhic','speciality','ichi_code','procedure_name','primary_health_facility','secondary_health_facility','tertiary_health_facility','is_active'];
        $fields = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f=?";
                $params[] = $f === 'is_active' ? (empty($input[$f]) ? 0 : 1) : $input[$f];
            }
        }
        if (!$fields) error('Nothing to update', 422);
        $params[] = $id;
        $pdo->prepare("UPDATE service_catalog SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
        success(null, 'Service updated');
    }

    // DELETE
    if ($method === 'DELETE') {
        if ($action === 'unlink_item') {
            $linkId = $id ?: (int)($_GET['link_id'] ?? 0);
            $serviceId = (int)($_GET['service_id'] ?? 0);
            $itemId = (int)($_GET['item_id'] ?? 0);

            if ($linkId > 0) {
                $pdo->prepare("DELETE FROM service_catalog_items WHERE id=?")->execute([$linkId]);
            } elseif ($serviceId > 0 && $itemId > 0) {
                $pdo->prepare("DELETE FROM service_catalog_items WHERE service_id=? AND item_id=?")->execute([$serviceId, $itemId]);
            } else {
                error('id or (service_id and item_id) required', 422);
            }
            success(null, 'Inventory item disconnected successfully');
        }

        if (!$id) error('Service ID required', 422);
        $chk = $pdo->prepare("SELECT id FROM service_catalog WHERE id=?");
        $chk->execute([$id]);
        if (!$chk->fetch()) error('Service not found', 404);
        $pdo->prepare("DELETE FROM service_catalog WHERE id=?")->execute([$id]);
        success(null, 'Service deleted');
    }

    error('Method not allowed', 405);
} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
}
?>
