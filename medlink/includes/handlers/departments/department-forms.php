<?php
// handlers/departments/department-forms.php
// API for: managing department form templates AND submitting/fetching filled forms
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($data, $status = 200) { http_response_code($status); echo json_encode($data); exit(); }
function success($data = null, $msg = 'Success', $status = 200) { jsonResponse(['success'=>true,'message'=>$msg,'data'=>$data], $status); }
function error($msg, $status = 400) { jsonResponse(['success'=>false,'message'=>$msg], $status); }

$method   = $_SERVER['REQUEST_METHOD'];
$action   = $_GET['action'] ?? '';
$id       = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {

// ============================================================
// TEMPLATE MANAGEMENT (admin: CRUD on form templates)
// ============================================================

// GET /department-forms?action=templates & department_id=X
// GET /department-forms?action=templates & id=X
if ($method === 'GET' && $action === 'templates') {
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM department_form_templates WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch();
        if (!$template) error('Template not found', 404);
        // Fetch fields
        $fStmt = $pdo->prepare("SELECT * FROM department_form_fields WHERE template_id = ? ORDER BY sort_order ASC");
        $fStmt->execute([$id]);
        $template['fields'] = $fStmt->fetchAll();
        success($template);
    }
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    $status = $_GET['status'] ?? '';
    $where  = []; $params = [];
    if ($deptId) { $where[] = 't.department_id = ?'; $params[] = $deptId; }
    if ($status) { $where[] = 't.status = ?'; $params[] = $status; }
    $ws = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare("
        SELECT t.*, d.name AS department_name
        FROM department_form_templates t
        JOIN departments d ON d.id = t.department_id
        $ws ORDER BY d.name, t.sort_order ASC
    ");
    $stmt->execute($params);
    $templates = $stmt->fetchAll();
    success($templates);
}

// POST /department-forms?action=templates — Create template
if ($method === 'POST' && $action === 'templates') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['department_id']) || empty($input['name']) || empty($input['slug'])) {
        error('department_id, name, and slug are required', 422);
    }
    $deptId = (int)$input['department_id'];
    // Check slug uniqueness within department
    $chk = $pdo->prepare("SELECT id FROM department_form_templates WHERE department_id = ? AND slug = ?");
    $chk->execute([$deptId, trim($input['slug'])]);
    if ($chk->fetch()) error('A template with this slug already exists in this department', 409);

    $stmt = $pdo->prepare("
        INSERT INTO department_form_templates (department_id, name, slug, description, status, is_required, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $deptId,
        trim($input['name']),
        trim($input['slug']),
        $input['description'] ?? '',
        $input['status'] ?? 'active',
        isset($input['is_required']) ? (int)$input['is_required'] : 1,
        (int)($input['sort_order'] ?? 0)
    ]);
    $newId = (int)$pdo->lastInsertId();
    success(['id' => $newId], 'Form template created', 201);
}

// PUT /department-forms?action=templates&id=X — Update template
if ($method === 'PUT' && $action === 'templates') {
    if (!$id) error('Template ID required', 422);
    $chk = $pdo->prepare("SELECT id FROM department_form_templates WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('Template not found', 404);

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $fields = []; $params = [];
    $allowed = ['name','description','status','is_required','sort_order'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) { $fields[] = "$f = ?"; $params[] = $input[$f]; }
    }
    if ($fields) {
        $params[] = $id;
        $pdo->prepare("UPDATE department_form_templates SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    }
    success(null, 'Template updated');
}

// DELETE /department-forms?action=templates&id=X
if ($method === 'DELETE' && $action === 'templates') {
    if (!$id) error('Template ID required', 422);
    $pdo->prepare("DELETE FROM department_form_templates WHERE id = ?")->execute([$id]);
    success(null, 'Template deleted');
}

// ============================================================
// FIELD MANAGEMENT (CRUD on fields within a template)
// ============================================================

// GET /department-forms?action=fields&template_id=X
if ($method === 'GET' && $action === 'fields') {
    $tid = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;
    if (!$tid) error('template_id required', 422);
    $stmt = $pdo->prepare("SELECT * FROM department_form_fields WHERE template_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$tid]);
    success($stmt->fetchAll());
}

// POST /department-forms?action=fields — Add field to template
if ($method === 'POST' && $action === 'fields') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($input['template_id']) || empty($input['field_label']) || empty($input['field_key'])) {
        error('template_id, field_label, and field_key are required', 422);
    }
    $tid = (int)$input['template_id'];
    // Check key uniqueness
    $chk = $pdo->prepare("SELECT id FROM department_form_fields WHERE template_id = ? AND field_key = ?");
    $chk->execute([$tid, trim($input['field_key'])]);
    if ($chk->fetch()) error('A field with this key already exists in this template', 409);

    $stmt = $pdo->prepare("
        INSERT INTO department_form_fields (template_id, field_label, field_key, field_type, field_options, placeholder, is_required, sort_order, validation_rules, default_value, help_text)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tid,
        trim($input['field_label']),
        trim($input['field_key']),
        $input['field_type'] ?? 'text',
        isset($input['field_options']) ? json_encode($input['field_options']) : null,
        $input['placeholder'] ?? null,
        isset($input['is_required']) ? (int)$input['is_required'] : 0,
        (int)($input['sort_order'] ?? 0),
        isset($input['validation_rules']) ? json_encode($input['validation_rules']) : null,
        $input['default_value'] ?? null,
        $input['help_text'] ?? null
    ]);
    success(['id' => (int)$pdo->lastInsertId()], 'Field added', 201);
}

// PUT /department-forms?action=fields&id=X — Update field
if ($method === 'PUT' && $action === 'fields') {
    if (!$id) error('Field ID required', 422);
    $chk = $pdo->prepare("SELECT id FROM department_form_fields WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetch()) error('Field not found', 404);
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $fields = []; $params = [];
    $allowed = ['field_label','field_key','field_type','field_options','placeholder','is_required','sort_order','validation_rules','default_value','help_text'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            if ($f === 'field_options' || $f === 'validation_rules') {
                $fields[] = "$f = ?";
                $params[] = is_string($input[$f]) ? $input[$f] : json_encode($input[$f]);
            } else {
                $fields[] = "$f = ?";
                $params[] = $input[$f];
            }
        }
    }
    if ($fields) { $params[] = $id; $pdo->prepare("UPDATE department_form_fields SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params); }
    success(null, 'Field updated');
}

// DELETE /department-forms?action=fields&id=X
if ($method === 'DELETE' && $action === 'fields') {
    if (!$id) error('Field ID required', 422);
    $pdo->prepare("DELETE FROM department_form_fields WHERE id = ?")->execute([$id]);
    success(null, 'Field deleted');
}

// ============================================================
// SUBMIT/FETCH FILLED FORMS (for a visit in a department)
// ============================================================

// GET /department-forms?action=submitted&visit_id=X&department_id=X
// GET /department-forms?action=submitted&visit_id=X  (all forms for visit)
if ($method === 'GET' && $action === 'submitted') {
    $visitId      = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
    $departmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    $templateId   = isset($_GET['template_id']) ? (int)$_GET['template_id'] : 0;

    if (!$visitId) error('visit_id required', 422);
    $where  = ['vdf.visit_id = ?'];
    $params = [$visitId];
    if ($departmentId) { $where[] = 'vdf.department_id = ?'; $params[] = $departmentId; }
    if ($templateId)   { $where[] = 'vdf.template_id = ?';  $params[] = $templateId; }
    $ws = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT vdf.*, u.first_name AS filled_by_first, u.last_name AS filled_by_last,
               d.name AS department_name, t.name AS template_name
        FROM visit_department_forms vdf
        JOIN department_form_templates t ON t.id = vdf.template_id
        JOIN departments d ON d.id = vdf.department_id
        LEFT JOIN users u ON u.id = vdf.filled_by
        $ws ORDER BY vdf.created_at DESC
    ");
    $stmt->execute($params);
    $forms = $stmt->fetchAll();

    // Decode JSON form_data for each
    foreach ($forms as &$f) {
        $f['form_data'] = json_decode($f['form_data'], true) ?? [];
    }

    success($forms);
}

// POST /department-forms?action=submit — Save/submit a department form for a visit
if ($method === 'POST' && $action === 'submit') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    foreach (['visit_id', 'patient_id', 'template_id', 'department_id', 'form_data'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }

    $visitId      = (int)$input['visit_id'];
    $patientId    = (int)$input['patient_id'];
    $templateId   = (int)$input['template_id'];
    $departmentId = (int)$input['department_id'];
    $filledBy     = !empty($input['filled_by']) ? (int)$input['filled_by'] : null;
    $status       = $input['status'] ?? 'completed';
    $formData     = $input['form_data']; // already an object/array

    // Check if a submission already exists for this visit+template
    $existing = $pdo->prepare("SELECT id, status FROM visit_department_forms WHERE visit_id = ? AND template_id = ?");
    $existing->execute([$visitId, $templateId]);
    $existingRow = $existing->fetch();

    if ($existingRow) {
        // Update existing submission
        $pdo->prepare("
            UPDATE visit_department_forms
            SET form_data = ?, filled_by = ?, status = ?, notes = ?
            WHERE id = ?
        ")->execute([
            json_encode($formData),
            $filledBy,
            $status,
            $input['notes'] ?? null,
            $existingRow['id']
        ]);

        // Auto-update department history if completed
        if ($status === 'completed') {
            $pdo->prepare("
                UPDATE visit_department_history
                SET status = 'completed', completed_at = NOW()
                WHERE visit_id = ? AND department_id = ? AND status = 'in_progress'
            ")->execute([$visitId, $departmentId]);
        }

        success(['id' => $existingRow['id']], 'Form updated');
    } else {
        // Insert new
        $stmt = $pdo->prepare("
            INSERT INTO visit_department_forms (visit_id, patient_id, template_id, department_id, filled_by, form_data, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $visitId, $patientId, $templateId, $departmentId,
            $filledBy, json_encode($formData), $status,
            $input['notes'] ?? null
        ]);
        $newId = (int)$pdo->lastInsertId();

        // Update department history to in_progress (if not already)
        if ($status === 'in_progress' || $status === 'draft') {
            $pdo->prepare("
                UPDATE visit_department_history
                SET status = 'in_progress', assigned_to = ?
                WHERE visit_id = ? AND department_id = ? AND status = 'pending'
            ")->execute([$filledBy, $visitId, $departmentId]);
        }

        success(['id' => $newId], 'Form submitted', 201);
    }
}

// ============================================================
// VISIT DEPARTMENT HISTORY
// ============================================================

// GET /department-forms?action=history&visit_id=X
if ($method === 'GET' && $action === 'history') {
    $visitId = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
    $deptId  = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    if (!$visitId) error('visit_id required', 422);

    $where  = ['vdh.visit_id = ?'];
    $params = [$visitId];
    if ($deptId) { $where[] = 'vdh.department_id = ?'; $params[] = $deptId; }
    $ws = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT vdh.*, d.name AS department_name,
               u1.first_name AS assigned_by_first, u1.last_name AS assigned_by_last,
               u2.first_name AS assigned_to_first, u2.last_name AS assigned_to_last
        FROM visit_department_history vdh
        JOIN departments d ON d.id = vdh.department_id
        LEFT JOIN users u1 ON u1.id = vdh.assigned_by
        LEFT JOIN users u2 ON u2.id = vdh.assigned_to
        $ws ORDER BY vdh.created_at ASC
    ");
    $stmt->execute($params);
    success($stmt->fetchAll());
}

// POST /department-forms?action=assign_department — Send visit to a department
if ($method === 'POST' && $action === 'assign_department') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach (['visit_id', 'patient_id', 'department_id'] as $f) {
        if (empty($input[$f])) error("Field '$f' is required", 422);
    }
    $visitId      = (int)$input['visit_id'];
    $patientId    = (int)$input['patient_id'];
    $departmentId = (int)$input['department_id'];
    $assignedBy   = !empty($input['assigned_by']) ? (int)$input['assigned_by'] : null;
    $assignedTo   = !empty($input['assigned_to']) ? (int)$input['assigned_to'] : null;
    $doctorNotes  = $input['doctor_notes'] ?? null;

    // Check if already assigned to this department
    $chk = $pdo->prepare("SELECT id, status FROM visit_department_history WHERE visit_id = ? AND department_id = ?");
    $chk->execute([$visitId, $departmentId]);
    if ($chk->fetch()) error('Visit already assigned to this department', 409);

    $stmt = $pdo->prepare("
        INSERT INTO visit_department_history (visit_id, patient_id, department_id, assigned_by, assigned_to, status, doctor_notes)
        VALUES (?, ?, ?, ?, ?, 'pending', ?)
    ");
    $stmt->execute([$visitId, $patientId, $departmentId, $assignedBy, $assignedTo, $doctorNotes]);

    // Also update the visit's department_id
    $pdo->prepare("UPDATE visits SET department_id = ?, status = 'in_progress' WHERE id = ?")
        ->execute([$departmentId, $visitId]);

    success(['id' => (int)$pdo->lastInsertId()], 'Patient assigned to department');
}

// ============================================================
// DASHBOARD QUERIES — for logged in doctor
// ============================================================

// GET /department-forms?action=pending_visits&department_id=X&user_id=X
if ($method === 'GET' && $action === 'pending_visits') {
    $deptId  = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    $userId  = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $isAdmin = isset($_GET['is_admin']) ? (int)$_GET['is_admin'] : 0;

    $where  = ['vdh.status = ?']; $params = ['pending'];
    if ($deptId) { $where[] = 'vdh.department_id = ?'; $params[] = $deptId; }

    // If not admin, only show assigned to them or unassigned
    if (!$isAdmin && $userId) {
        $where[] = '(vdh.assigned_to IS NULL OR vdh.assigned_to = ?)';
        $params[] = $userId;
    }

    $ws = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT vdh.*, d.name AS department_name,
               p.first_name AS patient_first, p.last_name AS patient_last,
               p.patient_no, p.phone, p.gender, p.date_of_birth,
               v.visit_no, v.visit_date, v.chief_complaint, v.payment_type,
               u1.first_name AS dr_first, u1.last_name AS dr_last
        FROM visit_department_history vdh
        JOIN departments d ON d.id = vdh.department_id
        JOIN patients p ON p.id = vdh.patient_id
        JOIN visits v ON v.id = vdh.visit_id
        LEFT JOIN users u1 ON u1.id = vdh.assigned_to
        $ws ORDER BY vdh.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $pending = $stmt->fetchAll();

    // Attach which forms need to be filled for each pending
    foreach ($pending as &$p) {
        $fStmt = $pdo->prepare("
            SELECT t.id AS template_id, t.name AS template_name, t.slug, t.is_required,
                   (SELECT vdf.id FROM visit_department_forms vdf WHERE vdf.visit_id = ? AND vdf.template_id = t.id AND vdf.status = 'completed') AS already_completed
            FROM department_form_templates t
            WHERE t.department_id = ? AND t.status = 'active'
            ORDER BY t.sort_order ASC
        ");
        $fStmt->execute([$p['visit_id'], $p['department_id']]);
        $p['required_forms'] = $fStmt->fetchAll();
        $p['all_completed'] = !array_filter($p['required_forms'], function($f) {
            return $f['is_required'] && !$f['already_completed'];
        });
    }

    success($pending);
}

// ============================================================
// DOCTOR LIST BY DEPARTMENT (reusable)
// ============================================================
if ($method === 'GET' && $action === 'doctors_by_dept') {
    $deptId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
    if (!$deptId) error('department_id required', 422);
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role,
               r.name AS role_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.department_id = ? AND u.is_active = 1 AND u.is_blocked = 0
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$deptId]);
    success($stmt->fetchAll());
}

error('Action not found or method not allowed', 404);

} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    error('Server error: ' . $e->getMessage(), 500);
}
?>
