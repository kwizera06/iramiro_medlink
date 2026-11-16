<?php
// handlers/settings/service-catalog.php
// $pdo is already available from api.php
if (!isset($pdo)) {
    require_once __DIR__ . '/../../../includes/database.php';
}

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

try {

    // GET - list or single
    if ($method === 'GET') {

        // action=insurance_price — get insurance_procedure_prices for service+insurance combo
        if ($action === 'insurance_price') {
            $serviceId   = isset($_GET['service_id'])   ? (int)$_GET['service_id']   : 0;
            $insuranceId = isset($_GET['insurance_id']) ? (int)$_GET['insurance_id'] : 0;
            if (!$serviceId || !$insuranceId) error('service_id and insurance_id are required', 422);

            // Get the insurance procedure price row
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
        $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $pdo->prepare("SELECT * FROM service_catalog $ws ORDER BY speciality, procedure_name");
        $stmt->execute($params);
        success($stmt->fetchAll());
    }

    // POST - create or import
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

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
        if (!$id) error('Service ID required', 422);
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $chk = $pdo->prepare("SELECT id FROM service_catalog WHERE id=?");
        $chk->execute([$id]);
        if (!$chk->fetch()) error('Service not found', 404);

        $allowed = ['rhic','speciality','ichi_code','procedure_name','primary_health_facility','secondary_health_facility','tertiary_health_facility'];
        $fields = []; $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $fields[] = "$f=?";
                $params[] = $input[$f];
            }
        }
        if (!$fields) error('Nothing to update', 422);
        $params[] = $id;
        $pdo->prepare("UPDATE service_catalog SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
        success(null, 'Service updated');
    }

    // DELETE
    if ($method === 'DELETE') {
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
