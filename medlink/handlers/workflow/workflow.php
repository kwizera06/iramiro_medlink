<?php
// handlers/workflow/workflow.php
// Visit closure (checks, then close) and patient transfers (internal bed moves, external referrals). A transfer is requested here,
// approved by an admin here (transfer_decide), then prepared, sent and completed here.
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/billing-calc.php';
require_once __DIR__ . '/../../includes/transfer_schema.php';

function jsonResponse($d, $s = 200) { http_response_code($s); echo json_encode($d); exit(); }
function success($d = null, $m = 'Success', $s = 200) { jsonResponse(['success' => true, 'message' => $m, 'data' => $d], $s); }
function error($m, $s = 400) { jsonResponse(['success' => false, 'message' => $m], $s); }
function wf_log(PDO $pdo, $a, $mod, $d, $id = null, $ref = null) { if (function_exists('log_activity')) log_activity($pdo, $a, $mod, $d, $id, $ref); }

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$uid = (int)($_SESSION['user_id'] ?? 0);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
tr_ensure_schema($pdo); // DDL, so before any transaction

// Access follows the sidebar: a role may use a step only if that step's menu item is granted to it
function wf_need(PDO $pdo, int $uid, string $sub): void {
    if (!canUsePage($pdo, "workflow.php?module=workflow&sub=$sub")) error('Your role does not have access to this step.', 403);
}

// Simple invoice for the transfer charges (ambulance km + extra cost lines), priced at the visit's tariff
function wf_invoice(PDO $pdo, int $patientId, int $visitId, array $lines, int $uid, string $note): int {
    $v = $pdo->prepare("SELECT payment_type, insurance_id FROM visits WHERE id = ?");
    $v->execute([$visitId]);
    $v = $v->fetch(PDO::FETCH_ASSOC) ?: ['payment_type' => 'self_pay', 'insurance_id' => null];
    $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_by, created_at) VALUES (?,?,?,?,?,0,0,0,'issued',?,?,NOW())")
        ->execute([generateInvoiceNo($pdo), $visitId, $patientId, $v['payment_type'], $v['insurance_id'], $note, $uid ?: null]);
    $invId = (int)$pdo->lastInsertId();
    foreach ($lines as $l) {
        $sid = $l['service_id'] ?? null;
        $price = (float)($l['unit_price'] ?? 0);
        if ($sid) { $cat = tariffServicePrice($pdo, (int)$sid, visitForeignTariff($pdo, $visitId) ?? 'rwanda'); $price = $cat ?: (float)($l['fallback_price'] ?? 0); }
        $qty = max(1, (int)$l['quantity']);
        $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, total) VALUES (?,?,?,?,?,?,?)")
            ->execute([$invId, $sid, $l['description'], $l['category'], $qty, $price, $price * $qty]);
    }
    recalcInvoice($pdo, $invId);
    return $invId;
}

// What stops (or should slow down) closing a visit. Blockers cannot be skipped; warnings need a confirmation.
function wf_visit_checks(PDO $pdo, int $visitId): array {
    $bed = $pdo->prepare("SELECT COUNT(*) FROM bed_assignments WHERE visit_id = ? AND status = 'active'");
    $bed->execute([$visitId]);
    $inv = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status IN ('issued','partial') THEN balance ELSE 0 END), 0) AS unpaid FROM invoices WHERE visit_id = ? AND status <> 'cancelled'");
    $inv->execute([$visitId]);
    $inv = $inv->fetch();
    $ord = $pdo->prepare("SELECT COUNT(*) FROM medical_orders WHERE visit_id = ? AND status IN ('ordered','in_progress')");
    $ord->execute([$visitId]);
    return ['admitted' => (int)$bed->fetchColumn(), 'invoices' => (int)$inv['n'], 'unpaid' => round((float)$inv['unpaid'], 2), 'pending_orders' => (int)$ord->fetchColumn()];
}

const WF_VISIT_SELECT = "SELECT v.id, v.visit_no, v.visit_date, v.visit_type, v.status, v.payment_type, v.consultation_status, v.completed_at,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_no,
        v.doctor_name AS doctor_name, d.name AS department,
        (SELECT COUNT(*) FROM bed_assignments b WHERE b.visit_id = v.id AND b.status = 'active') AS admitted,
        (SELECT COUNT(*) FROM invoices i WHERE i.visit_id = v.id AND i.status <> 'cancelled') AS invoices,
        (SELECT COALESCE(SUM(i.balance), 0) FROM invoices i WHERE i.visit_id = v.id AND i.status IN ('issued','partial')) AS unpaid,
        (SELECT COUNT(*) FROM medical_orders m WHERE m.visit_id = v.id AND m.status IN ('ordered','in_progress')) AS pending_orders,
        CONCAT(cb.first_name, ' ', cb.last_name) AS closed_by_name
    FROM visits v JOIN patients p ON p.id = v.patient_id
    LEFT JOIN departments d ON d.id = v.department_id LEFT JOIN users cb ON cb.id = v.completed_by";

try {
    if ($method === 'GET' && $action === 'closure_list') {
        wf_need($pdo, $uid, 'closure');
        $closed = ($_GET['view'] ?? 'open') === 'closed';
        $w = [$closed ? "v.status = 'completed' AND v.completed_at >= CURDATE() - INTERVAL 30 DAY" : "v.status IN ('open','in_progress')"];
        $p = [];
        if (($q = trim($_GET['search'] ?? '')) !== '') {
            $w[] = "(CONCAT(p.first_name,' ',p.last_name) LIKE ? OR CONCAT(p.last_name,' ',p.first_name) LIKE ? OR p.patient_no LIKE ? OR v.visit_no LIKE ?)";
            array_push($p, ...array_fill(0, 4, "%$q%"));
        }
        $s = $pdo->prepare(WF_VISIT_SELECT . ' WHERE ' . implode(' AND ', $w) . ($closed ? ' ORDER BY v.completed_at DESC LIMIT 200' : ' ORDER BY v.visit_date, v.id LIMIT 500'));
        $s->execute($p);
        success($s->fetchAll());
    }

    if ($method === 'POST' && $action === 'closure_close') {
        wf_need($pdo, $uid, 'closure');
        $visitId = (int)($input['visit_id'] ?? 0);
        if (!$visitId) error('visit_id required', 422);
        $pdo->beginTransaction();
        $v = $pdo->prepare("SELECT v.id, v.visit_no, v.status, CONCAT(p.first_name, ' ', p.last_name) AS patient_name FROM visits v JOIN patients p ON p.id = v.patient_id WHERE v.id = ? FOR UPDATE");
        $v->execute([$visitId]);
        $v = $v->fetch();
        if (!$v) { $pdo->rollBack(); error('Visit not found', 404); }
        if (!in_array($v['status'], ['open', 'in_progress'], true)) { $pdo->rollBack(); error("This visit is already {$v['status']}.", 409); }
        $c = wf_visit_checks($pdo, $visitId);
        if ($c['admitted'] > 0) { $pdo->rollBack(); error('The patient is still admitted to a bed. Discharge the patient first.', 409); }
        if ($c['unpaid'] > 0) { $pdo->rollBack(); error('The visit still has unpaid invoices (' . number_format($c['unpaid']) . ' RWF). Collect the payment or cancel the invoice first.', 409); }
        if ($c['pending_orders'] > 0 && empty($input['confirm_pending'])) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'code' => 'confirm_pending', 'message' => "{$c['pending_orders']} lab / imaging / procedure order(s) are not finished yet. Close the visit anyway?"], 409);
        }
        $pdo->prepare("UPDATE visits SET status = 'completed', consultation_status = 'done', closed_at = NOW(), completed_by = ?, completed_at = NOW() WHERE id = ?")->execute([$uid, $visitId]);
        $note = trim((string)($input['note'] ?? ''));
        wf_log($pdo, 'UPDATE', 'visits', "Visit closed: {$v['visit_no']} for {$v['patient_name']}" . ($c['pending_orders'] ? " (closed with {$c['pending_orders']} unfinished order(s))" : '') . ($note !== '' ? " | $note" : ''), $visitId, $v['visit_no']);
        $pdo->commit();
        success(null, "Visit {$v['visit_no']} closed");
    }

    if ($method === 'GET' && $action === 'transfer_history') {
        wf_need($pdo, $uid, 'transfers');
        $s = $pdo->query("SELECT n.id, n.assigned_at, n.notes, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_no,
                wo.name AS from_ward, ro.room_number AS from_room, bo.bed_number AS from_bed,
                wn.name AS to_ward, rn.room_number AS to_room, bn.bed_number AS to_bed, CONCAT(u.first_name, ' ', u.last_name) AS by_name
            FROM bed_assignments n
            JOIN patients p ON p.id = n.patient_id
            JOIN beds bn ON bn.id = n.bed_id JOIN rooms rn ON rn.id = n.room_id JOIN wards wn ON wn.id = n.ward_id
            LEFT JOIN bed_assignments o ON o.id = (SELECT o2.id FROM bed_assignments o2 WHERE o2.patient_id = n.patient_id AND o2.visit_id <=> n.visit_id AND o2.id < n.id ORDER BY o2.id DESC LIMIT 1)
            LEFT JOIN beds bo ON bo.id = o.bed_id LEFT JOIN rooms ro ON ro.id = o.room_id LEFT JOIN wards wo ON wo.id = o.ward_id
            LEFT JOIN users u ON u.id = n.created_by
            WHERE n.assignment_type = 'transfer' ORDER BY n.id DESC LIMIT 200");
        success($s->fetchAll());
    }

    // ---- Patient transfers: requested -> approved (approvals page) -> prepared -> in transit -> completed ----
    if ($method === 'GET' && $action === 'transfer_list') {
        wf_need($pdo, $uid, 'transfers');
        $type = $_GET['type'] ?? '';
        $typed = in_array($type, ['internal', 'external'], true);
        $s = $pdo->prepare(AP_TRANSFER_SELECT . ($typed ? ' WHERE t.transfer_type = ?' : '') . ' ORDER BY t.id DESC LIMIT 300');
        $s->execute($typed ? [$type] : []);
        success($s->fetchAll());
    }

    if ($method === 'GET' && $action === 'transfer_costs') {
        wf_need($pdo, $uid, 'transfers');
        $s = $pdo->prepare("SELECT c.service_id, c.description, c.quantity, c.unit_price FROM patient_transfer_costs c WHERE c.transfer_id = ? ORDER BY c.id");
        $s->execute([(int)($_GET['id'] ?? 0)]);
        success($s->fetchAll());
    }

    if ($method === 'GET' && $action === 'cost_services') { // catalog lines for the cost editor
        wf_need($pdo, $uid, 'transfers');
        $q = '%' . trim($_GET['q'] ?? '') . '%';
        $s = $pdo->prepare("SELECT id, procedure_name FROM service_catalog WHERE procedure_name LIKE ? OR rhic LIKE ? ORDER BY procedure_name LIMIT 20");
        $s->execute([$q, $q]);
        success($s->fetchAll());
    }

    if ($method === 'POST' && $action === 'transfer_create') {
        wf_need($pdo, $uid, 'transfers');
        $type = $input['type'] ?? '';
        $reason = trim((string)($input['reason'] ?? ''));
        if (!in_array($type, ['internal', 'external'], true)) error('Choose internal or external transfer', 422);
        if ($reason === '') error('The reason for the transfer is required', 422);
        $urgency = in_array($input['urgency'] ?? '', ['routine', 'urgent', 'emergency'], true) ? $input['urgency'] : 'routine';
        $scope = $type === 'external' && ($input['scope'] ?? '') === 'partial' ? 'partial' : 'complete'; // partial: the patient goes out for a service and comes back
        $a = $pdo->prepare("SELECT id, patient_id, visit_id, bed_id FROM bed_assignments WHERE id = ? AND status = 'active'");
        $a->execute([(int)($input['assignment_id'] ?? 0)]);
        $a = $a->fetch();
        if (!$a) error('This patient is no longer admitted', 404);
        $open = $pdo->prepare("SELECT transfer_no FROM patient_transfers WHERE patient_id = ? AND status IN ('requested','approved','prepared','in_transit') LIMIT 1");
        $open->execute([$a['patient_id']]);
        if ($no = $open->fetchColumn()) error("This patient already has an open transfer ($no).", 409);

        $toBed = $toRoom = $toWard = null;
        $facility = trim((string)($input['to_facility'] ?? ''));
        if ($type === 'internal') {
            $b = $pdo->prepare("SELECT b.id, b.room_id, r.ward_id FROM beds b JOIN rooms r ON r.id = b.room_id WHERE b.id = ? AND b.status = 'available' AND b.is_active = 1");
            $b->execute([(int)($input['to_bed_id'] ?? 0)]);
            $b = $b->fetch();
            if (!$b) error('Choose a free destination bed', 422);
            if ((int)$b['id'] === (int)$a['bed_id']) error('The patient is already in that bed', 422);
            [$toBed, $toRoom, $toWard] = [(int)$b['id'], (int)$b['room_id'], (int)$b['ward_id']];
            $facility = '';
        } elseif ($facility === '') {
            error('The receiving facility is required', 422);
        }
        // Ambulance charge: the tariff line "Ambulance per 1km" x the distance (external transfers only)
        $km = $type === 'external' ? max(0, (int)($input['km'] ?? 0)) : 0;
        $svc = null;
        if ($km > 0) {
            $c = $pdo->prepare("SELECT id FROM service_catalog WHERE rhic = ? LIMIT 1");
            $c->execute([TR_AMBULANCE_RHIC]);
            if (!($svc = $c->fetchColumn())) error('The tariff line ' . TR_AMBULANCE_RHIC . ' (Ambulance per 1km) is missing from the service catalog.', 422);
        }
        $txt = fn($k) => ($v = trim((string)($input[$k] ?? ''))) === '' ? null : $v;
        if ($type === 'external' && $txt('consent_by') === null) error('Record who gave consent for the transfer (the patient or the guardian).', 422);
        $pdo->beginTransaction();
        $no = next_doc_no($pdo, 'patient_transfers', 'transfer_no', 'TRF-' . date('Ym') . '-');
        $pdo->prepare("INSERT INTO patient_transfers (transfer_no, transfer_type, patient_id, visit_id, assignment_id, urgency, reason, clinical_condition, to_ward_id, to_room_id, to_bed_id, to_facility, transport, accompanied_by, service_id, charge_qty, requested_by, scope)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$no, $type, $a['patient_id'], $a['visit_id'], $a['id'], $urgency, $reason, $txt('clinical_condition'), $toWard, $toRoom, $toBed, $facility ?: null,
                       $txt('transport'), $txt('accompanied_by'), $svc, max(1, $km), $uid, $scope]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE patient_transfers SET oxygen_monitoring = ?, consent_by = ?, referral_documents = ? WHERE id = ?")->execute([$txt('oxygen_monitoring'), $txt('consent_by'), $txt('referral_documents'), $newId]);
        wf_log($pdo, 'CREATE', 'ward', "Transfer requested: $no ($type, $urgency)", $newId, $no);
        $pdo->commit();
        success(['id' => $newId, 'transfer_no' => $no], "Transfer $no requested. It waits for admin approval.");
    }

    if ($method === 'POST' && $action === 'transfer_decide') {
        if (!isUserAdmin($pdo)) error('Only an admin can decide patient transfers.', 403);
        $decision = $input['decision'] ?? '';
        if (!in_array($decision, ['approve', 'reject'], true)) error('Unknown decision', 422);
        $note = trim((string)($input['note'] ?? ''));
        if ($decision === 'reject' && $note === '') error('Give a reason for rejecting', 422);
        $pdo->beginTransaction();
        $t = $pdo->prepare("SELECT id, transfer_no, status FROM patient_transfers WHERE id = ? FOR UPDATE");
        $t->execute([(int)($input['id'] ?? 0)]);
        $t = $t->fetch();
        if (!$t) { $pdo->rollBack(); error('Transfer not found', 404); }
        if ($t['status'] !== 'requested') { $pdo->rollBack(); error("This transfer is already {$t['status']}.", 409); }
        $new = $decision === 'approve' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE patient_transfers SET status = ?, decided_by = ?, decided_at = NOW(), decision_note = ? WHERE id = ?")->execute([$new, $uid, $note ?: null, $t['id']]);
        wf_log($pdo, 'UPDATE', 'ward', "Transfer {$t['transfer_no']} $new", (int)$t['id'], $t['transfer_no']);
        $pdo->commit();
        success(null, "Transfer {$t['transfer_no']} $new");
    }
    if ($method === 'POST' && ($action === 'transfer_step' || $action === 'transfer_cancel')) {
        wf_need($pdo, $uid, 'transfers');
        $pdo->beginTransaction();
        $t = $pdo->prepare("SELECT * FROM patient_transfers WHERE id = ? FOR UPDATE");
        $t->execute([(int)($input['id'] ?? 0)]);
        $t = $t->fetch();
        if (!$t) { $pdo->rollBack(); error('Transfer not found', 404); }
        $st = $t['status'];

        if ($action === 'transfer_cancel') {
            if (!in_array($st, ['requested', 'approved', 'prepared'], true)) { $pdo->rollBack(); error("A transfer that is $st cannot be cancelled.", 409); }
            $pdo->prepare("UPDATE patient_transfers SET status = 'cancelled' WHERE id = ?")->execute([$t['id']]);
            wf_log($pdo, 'UPDATE', 'ward', "Transfer cancelled: {$t['transfer_no']}", (int)$t['id'], $t['transfer_no']);
            $pdo->commit();
            success(null, 'Transfer cancelled');
        }

        $step = $input['step'] ?? '';
        $need = ['prepare' => 'approved', 'depart' => 'prepared', 'complete' => 'in_transit', 'return' => 'completed'][$step] ?? null;
        if (!$need) { $pdo->rollBack(); error('Unknown step', 422); }
        if ($st !== $need) { $pdo->rollBack(); error("This transfer is $st; it must be $need for this step.", 409); }
        $note = trim((string)($input['handover_note'] ?? ''));
        $invoiceMsg = '';
        if ($step === 'return' && $t['scope'] !== 'partial') { $pdo->rollBack(); error('Only a partial transfer has a return step.', 422); }
        // Extra costs (oxygen, escort, ...) are edited at the Prepare and Complete steps: the list sent replaces the saved one
        if (in_array($step, ['prepare', 'complete'], true) && isset($input['costs']) && is_array($input['costs'])) {
            $pdo->prepare("DELETE FROM patient_transfer_costs WHERE transfer_id = ?")->execute([$t['id']]);
            foreach ($input['costs'] as $c) {
                $svcId = (int)($c['service_id'] ?? 0) ?: null;
                $desc = trim((string)($c['description'] ?? ''));
                if ($desc === '') continue;
                $pdo->prepare("INSERT INTO patient_transfer_costs (transfer_id, service_id, description, quantity, unit_price, created_by) VALUES (?,?,?,?,?,?)")
                    ->execute([$t['id'], $svcId, $desc, max(1, (int)($c['quantity'] ?? 1)), max(0, (float)($c['unit_price'] ?? 0)), $uid ?: null]);
            }
        }

        if ($step === 'prepare') {
            $pdo->prepare("UPDATE patient_transfers SET status = 'prepared', prepared_at = NOW(), handover_note = COALESCE(NULLIF(?, ''), handover_note), facility_accepted = ?,
                    transport = COALESCE(NULLIF(?, ''), transport), accompanied_by = COALESCE(NULLIF(?, ''), accompanied_by) WHERE id = ?")
                ->execute([$note, !empty($input['facility_accepted']) ? 1 : 0, trim((string)($input['transport'] ?? '')), trim((string)($input['accompanied_by'] ?? '')), $t['id']]);
        } elseif ($step === 'depart') {
            $pdo->prepare("UPDATE patient_transfers SET status = 'in_transit', departed_at = NOW() WHERE id = ?")->execute([$t['id']]);
        } elseif ($step === 'return') {
            $pdo->prepare("UPDATE patient_transfers SET status = 'returned', returned_at = NOW(), handover_note = COALESCE(NULLIF(?, ''), handover_note) WHERE id = ?")->execute([$note, $t['id']]);
        } else {
            $a = $pdo->prepare("SELECT id, bed_id, patient_id, visit_id FROM bed_assignments WHERE id = ? AND status = 'active' FOR UPDATE");
            $a->execute([$t['assignment_id']]);
            $a = $a->fetch();
            if (!$a) { $pdo->rollBack(); error('The patient is no longer in that bed.', 409); }
            if ($t['transfer_type'] === 'internal') {
                $claim = $pdo->prepare("UPDATE beds SET status = 'occupied' WHERE id = ? AND status = 'available' AND is_active = 1");
                $claim->execute([$t['to_bed_id']]);
                if ($claim->rowCount() !== 1) { $pdo->rollBack(); error('The destination bed is no longer available. Cancel and request again with another bed.', 409); }
                $pdo->prepare("UPDATE beds SET status = 'available' WHERE id = ?")->execute([$a['bed_id']]);
                $pdo->prepare("UPDATE bed_assignments SET status = 'transferred', discharged_at = NOW() WHERE id = ?")->execute([$a['id']]);
                $pdo->prepare("INSERT INTO bed_assignments (patient_id, visit_id, bed_id, room_id, ward_id, assignment_type, status, notes, created_by) VALUES (?,?,?,?,?,'transfer','active',?,?)")
                    ->execute([$a['patient_id'], $a['visit_id'], $t['to_bed_id'], $t['to_room_id'], $t['to_ward_id'], "{$t['transfer_no']}: {$t['reason']}", $uid]);
                $pdo->prepare("UPDATE admission_requests SET assigned_bed_id = ? WHERE patient_id = ? AND assigned_bed_id = ?")->execute([$t['to_bed_id'], $a['patient_id'], $a['bed_id']]);
            } elseif ($t['scope'] !== 'partial') {
                // Leaves this hospital for good: same effect as a discharge with outcome "transferred"
                $pdo->prepare("UPDATE bed_assignments SET status = 'discharged', discharged_at = NOW(), discharged_by = ?, discharge_status = 'transferred', transfer_facility = ?,
                        discharge_notes = COALESCE(discharge_notes, ?), notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ?")
                    ->execute([$uid ?: null, $t['to_facility'], "Transfer {$t['transfer_no']}", " [Discharge Status: transfer] {$t['transfer_no']} to {$t['to_facility']}", $a['id']]);
                $pdo->prepare("UPDATE beds SET status = 'cleaning' WHERE id = ?")->execute([$a['bed_id']]);
                if ($a['visit_id']) $pdo->prepare("UPDATE visits SET status = 'completed', consultation_status = 'done' WHERE id = ?")->execute([$a['visit_id']]);
            } // partial transfer: the bed and the visit stay open until the patient returns
            $pdo->prepare("UPDATE patient_transfers SET status = 'completed', arrived_at = NOW(), completed_at = NOW(), completed_by = ?, handover_note = COALESCE(NULLIF(?, ''), handover_note) WHERE id = ?")
                ->execute([$uid, $note, $t['id']]);
        }
        wf_log($pdo, 'UPDATE', 'ward', "Transfer {$t['transfer_no']}: $step", (int)$t['id'], $t['transfer_no']);
        $pdo->commit();

        // The ambulance km and the extra cost lines go on one invoice once the patient has really moved (never for a transfer that did not happen).
        // Every line is priced at the patient's tariff (insurer / foreign / facility) by createWardOrderInvoice.
        if ($step === 'complete' && $t['visit_id']) {
            $lines = [];
            if ($t['service_id']) {
                $sc = $pdo->prepare("SELECT procedure_name, GREATEST(COALESCE(primary_health_facility,0), COALESCE(secondary_health_facility,0), COALESCE(tertiary_health_facility,0)) AS price FROM service_catalog WHERE id = ?");
                $sc->execute([$t['service_id']]);
                $sc = $sc->fetch();
                if ($sc && (float)$sc['price'] > 0) {
                    $lines[] = ['service_id' => (int)$t['service_id'], 'description' => $sc['procedure_name'] . ' (' . (int)$t['charge_qty'] . ' km)', 'quantity' => (int)$t['charge_qty'], 'category' => 'emergency'];
                } else {
                    $invoiceMsg = ' No price is set for the ambulance charge in the tariff, so it was not invoiced.';
                }
            }
            $cs = $pdo->prepare("SELECT service_id, description, quantity, unit_price FROM patient_transfer_costs WHERE transfer_id = ? ORDER BY id");
            $cs->execute([$t['id']]);
            foreach ($cs->fetchAll() as $c) {
                $line = ['description' => $c['description'], 'quantity' => (int)$c['quantity'], 'category' => 'emergency'];
                if ($c['service_id']) { $line['service_id'] = (int)$c['service_id']; $line['unit_price'] = 0; $line['fallback_price'] = (float)$c['unit_price']; }
                else { $line['unit_price'] = (float)$c['unit_price']; }
                $lines[] = $line;
            }
            if ($lines) {
                try {
                    $invId = wf_invoice($pdo, (int)$t['patient_id'], (int)$t['visit_id'], $lines, $uid, "Patient transfer {$t['transfer_no']}");
                    $pdo->prepare("UPDATE patient_transfers SET invoice_id = ? WHERE id = ?")->execute([$invId, $t['id']]);
                    $invoiceMsg = ' Charges added to a new invoice.';
                } catch (Throwable $e) {
                    error_log('transfer invoice: ' . $e->getMessage());
                    $invoiceMsg = ' The charges could not be invoiced; add them by hand.';
                }
            }
        }
        success(null, 'Transfer ' . ['prepare' => 'prepared', 'depart' => 'in transit', 'complete' => 'completed', 'return' => 'marked as returned'][$step] . '.' . $invoiceMsg);
    }

    error('Unknown action', 404);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error('DB: ' . $e->getMessage(), 500);
}
