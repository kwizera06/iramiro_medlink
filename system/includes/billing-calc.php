<?php
// includes/billing-calc.php — single source of truth for patient / insurance shares of an invoice

/**
 * Patient's share of an invoice. Top-ups are always paid by the patient, never by insurance.
 * $copayPct must already be 100 for self-pay invoices.
 * Items: quantity, unit_price, insurance_price, patient_topup, apply_insurance.
 */
function itemsPatientShare(array $items, float $copayPct, float $invoiceTotal): float {
    $hasInsuranceFields = false;
    foreach ($items as $item) {
        if (intval($item['apply_insurance'] ?? 0) > 0 || floatval($item['insurance_price'] ?? 0) > 0) {
            $hasInsuranceFields = true;
            break;
        }
    }
    // Legacy invoices without per-item insurance fields: copay on the whole total
    if (!$hasInsuranceFields) {
        return $copayPct < 100 ? round($invoiceTotal * $copayPct / 100) : round($invoiceTotal);
    }
    $patientShare = 0;
    foreach ($items as $item) {
        $qty = intval($item['quantity'] ?: 1);
        $insPrice = floatval($item['insurance_price'] ?? 0);
        $topup = 0; // top-ups removed
        $applyIns = intval($item['apply_insurance'] ?? 0);
        if ($applyIns && $insPrice > 0) {
            $patientShare += round(($insPrice * ($copayPct / 100) * $qty) + ($topup * $qty));
        } elseif ($applyIns) {
            $patientShare += round(floatval($item['unit_price'] ?? 0) * ($copayPct / 100) * $qty);
        } else {
            $patientShare += round(floatval($item['unit_price'] ?? 0) * $qty);
        }
    }
    return round($patientShare);
}

// Visit tariff categories (visits.tariff_category) billed at a foreign price => service_catalog column
const FOREIGN_TARIFFS = ['east_africa' => 'price_east_africa', 'africa' => 'price_africa', 'world' => 'price_world'];
const TARIFF_LABELS   = ['rwanda' => 'Rwanda', 'east_africa' => 'Foreigner - East Africa', 'africa' => 'Foreigner - Africa', 'world' => 'Foreigner - Rest of the World'];
const PRIVE_INSURANCE_ID = 14; // private patients' price list (insurance_procedure_prices)
const DRUG_CATEGORIES =['medication', 'pharmacy', 'drug', 'consumable'];

/** service_catalog price column for the hospital's facility level (Rwanda tariff). */
function facilityPriceColumn(PDO $pdo): string {
    $fc = $pdo->query("SELECT setting_value FROM hospital_settings WHERE setting_key='facility_category' LIMIT 1")->fetchColumn();
    return ['secondary' => 'secondary_health_facility', 'tertiary' => 'tertiary_health_facility'][$fc] ?? 'primary_health_facility';
}

/** Foreign tariff of the visit ('east_africa' / 'africa' / 'world'), null when billed on the Rwanda tariff. */
function visitForeignTariff(PDO $pdo, ?int $visitId): ?string {
    if (!$visitId) return null;
    $s = $pdo->prepare("SELECT tariff_category FROM visits WHERE id = ?");
    $s->execute([$visitId]);
    $t = $s->fetchColumn();
    return isset(FOREIGN_TARIFFS[$t]) ? $t : null;
}

/**
 * SQL price of service_catalog row $a on a tariff. Foreign tariff without its own price => the private price:
 * the higher of the PRIVE agreed price and the self-pay facility price. Rwanda => self-pay facility price.
 */
function tariffPriceSql(PDO $pdo, string $tariff, string $a = 'sc'): string {
    $selfPay = "$a." . facilityPriceColumn($pdo);
    if (!isset(FOREIGN_TARIFFS[$tariff])) return $selfPay;
    $prive = "(SELECT MAX(price) FROM insurance_procedure_prices WHERE service_id = $a.id AND insurance_id = " . PRIVE_INSURANCE_ID . ")";
    return "COALESCE(NULLIF($a." . FOREIGN_TARIFFS[$tariff] . ", 0), GREATEST(COALESCE($prive, 0), $selfPay))";
}

/** Price of a catalog service on a tariff (see tariffPriceSql), null when the service is unknown. */
function tariffServicePrice(PDO $pdo, int $serviceId, string $tariff): ?float {
    $s = $pdo->prepare("SELECT " . tariffPriceSql($pdo, $tariff) . " FROM service_catalog sc WHERE sc.id = ?");
    $s->execute([$serviceId]);
    $p = $s->fetchColumn();
    return $p === false ? null : (float)$p;
}

/**
 * Unit price to bill for an invoice line. Foreign-tariff visits: catalog services are always priced here on the server
 * (the browser's price is ignored); drugs and non-catalog lines keep $price. Rwanda tariff: $price unchanged.
 */
function tariffLinePrice(PDO $pdo, ?int $visitId, ?int $serviceId, float $price, string $category = ''): float {
    $tariff = visitForeignTariff($pdo, $visitId);
    if (!$tariff || !$serviceId || in_array(strtolower($category), DRUG_CATEGORIES, true)) return $price;
    return tariffServicePrice($pdo, $serviceId, $tariff) ?? $price;
}

/**
 * Moves a visit to another tariff and re-prices its catalog-service lines. Unpaid invoices are re-priced in place;
 * on invoices that already received money the lines stay as charged and one adjustment line carries the difference.
 * A foreign tariff drops Rwandan insurance from the visit and its invoices. Call inside a transaction.
 * Returns the total amount change.
 */
function changeVisitTariff(PDO $pdo, int $visitId, string $tariff, string $reason, ?int $userId): float {
    $old = $pdo->query("SELECT tariff_category FROM visits WHERE id = $visitId FOR UPDATE")->fetchColumn();
    if ($old === false) throw new RuntimeException('Visit not found');
    if ($old === $tariff) return 0.0;
    $foreign = isset(FOREIGN_TARIFFS[$tariff]);
    if ($foreign) {
        $pdo->prepare("UPDATE visits SET tariff_category = ?, payment_type = 'self_pay', insurance_id = NULL, insurance_status = NULL, insurance_note = ? WHERE id = ?")
            ->execute([$tariff, TARIFF_LABELS[$tariff] . ' tariff', $visitId]);
    } else {
        $pdo->prepare("UPDATE visits SET tariff_category = ? WHERE id = ?")->execute([$tariff, $visitId]);
    }

    $drugs = "'" . implode("','", DRUG_CATEGORIES) . "'";
    $invoices = $pdo->query("SELECT i.id, (i.paid_amount > 0 OR EXISTS (SELECT 1 FROM payment_records pr WHERE pr.invoice_id = i.id AND pr.status IN ('completed','written_off'))) AS has_payment
                             FROM invoices i WHERE i.visit_id = $visitId AND i.status <> 'cancelled'")->fetchAll(PDO::FETCH_ASSOC);
    $lines = $pdo->prepare("SELECT id, service_id, quantity, unit_price, discount FROM invoice_items
                            WHERE invoice_id = ? AND service_id IS NOT NULL AND LOWER(category) NOT IN ($drugs, 'tariff_adjustment')");
    $reprice = $pdo->prepare("UPDATE invoice_items SET unit_price = ?, insurance_price = 0, patient_topup = 0, apply_insurance = 0, total = GREATEST(0, quantity * ? - discount) WHERE id = ?");
    $adjust = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, category, quantity, unit_price, total) VALUES (?, ?, 'tariff_adjustment', 1, ?, ?)");
    $label = 'Tariff change: ' . TARIFF_LABELS[$old] . ' → ' . TARIFF_LABELS[$tariff];
    $change = 0.0;
    foreach ($invoices as $inv) {
        $invId = (int)$inv['id'];
        if ($foreign) {
            $pdo->exec("UPDATE invoices SET payment_type = 'self_pay', insurance_id = NULL WHERE id = $invId");
            $pdo->exec("UPDATE invoice_items SET insurance_price = 0, patient_topup = 0, apply_insurance = 0 WHERE invoice_id = $invId");
        }
        $diff = 0.0;
        $lines->execute([$invId]);
        foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $new = tariffServicePrice($pdo, (int)$l['service_id'], $tariff);
            if ($new === null || abs($new - (float)$l['unit_price']) < 0.5) continue;
            $diff += ($new - (float)$l['unit_price']) * (float)$l['quantity'];
            if (!$inv['has_payment']) $reprice->execute([$new, $new, $l['id']]);
        }
        if ($inv['has_payment'] && abs($diff) >= 0.5) $adjust->execute([$invId, $label, round($diff), round($diff)]);
        $change += $diff;
        recalcInvoice($pdo, $invId);
    }
    $pdo->prepare("INSERT INTO visit_tariff_changes (visit_id, old_category, new_category, amount_change, reason, changed_by) VALUES (?,?,?,?,?,?)")
        ->execute([$visitId, $old, $tariff, round($change), $reason, $userId]);
    return round($change);
}

/** medical_orders.invoice_item_id = the invoice line that bills the order. DDL, so call it outside a transaction. */
function ensureOrderInvoiceLink(PDO $pdo): bool {
    static $ok = null;
    if ($ok) return true;
    if (!$pdo->query("SHOW COLUMNS FROM medical_orders LIKE 'invoice_item_id'")->fetch()) {
        if ($pdo->inTransaction()) return false;
        $pdo->exec("ALTER TABLE medical_orders ADD COLUMN invoice_item_id INT NULL, ADD INDEX idx_mo_invoice_item (invoice_item_id)");
    }
    return $ok = true;
}

/**
 * Pairs each unlinked lab / imaging / procedure order of the visit with one unlinked invoice line (service_id, else description),
 * oldest first, so the paid / covered status follows the real line instead of a text guess. Safe to call any time; never throws.
 */
function linkVisitOrders(PDO $pdo, int $visitId): void {
    try {
        if (!$visitId || !ensureOrderInvoiceLink($pdo)) return;
        $o = $pdo->prepare("SELECT id, order_type, service_id, description FROM medical_orders
                            WHERE visit_id = ? AND invoice_item_id IS NULL AND status <> 'cancelled' AND order_type IN ('lab','radiology','procedure') ORDER BY id");
        $o->execute([$visitId]);
        $orders = $o->fetchAll(PDO::FETCH_ASSOC);
        if (!$orders) return;
        $i = $pdo->prepare("SELECT ii.id, ii.service_id, ii.description, ii.category FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
                            WHERE i.visit_id = ? AND i.status <> 'cancelled' AND ii.category IN ('lab','laboratory','radiology','imaging','procedure')
                              AND ii.id NOT IN (SELECT invoice_item_id FROM medical_orders WHERE invoice_item_id IS NOT NULL) ORDER BY ii.id");
        $i->execute([$visitId]);
        $items = $i->fetchAll(PDO::FETCH_ASSOC);
        $cats = ['lab' => ['lab','laboratory'], 'radiology' => ['radiology','imaging'], 'procedure' => ['procedure']];
        $set = $pdo->prepare("UPDATE medical_orders SET invoice_item_id = ? WHERE id = ? AND invoice_item_id IS NULL");
        foreach ($orders as $mo) {
            $d = mb_strtolower(trim($mo['description']));
            foreach ($items as $k => $it) {
                if (!in_array($it['category'], $cats[$mo['order_type']], true)) continue;
                $idesc = mb_strtolower(trim($it['description']));
                $same = ($mo['service_id'] && $it['service_id'] && $mo['service_id'] == $it['service_id'])
                     || $idesc === $d || str_starts_with($idesc, $d . ' ') || str_contains($idesc, ' — ' . $d);
                if (!$same) continue;
                $set->execute([(int)$it['id'], (int)$mo['id']]);
                unset($items[$k]);
                break;
            }
        }
    } catch (Throwable $e) { error_log('linkVisitOrders: ' . $e->getMessage()); }
}

function generateInvoiceNo(PDO $pdo): string {
    $prefix = 'INV-'.date('Ymd').'-';
    $stmt = $pdo->prepare("SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY invoice_no DESC LIMIT 1");
    $stmt->execute([$prefix.'%']);
    $last = $stmt->fetchColumn();
    $seq  = $last ? ((int)substr($last,-4)+1) : 1;
    return $prefix.str_pad($seq,4,'0',STR_PAD_LEFT);
}

/**
 * Consultation fee billed at registration (first phase of the visit). Price comes from the service catalog,
 * or the insurer's agreed price; recalcInvoice() splits it into patient copay and insurance receivable.
 * Returns the invoice row, or null when the service is unknown.
 */
function createConsultationInvoice(PDO $pdo, int $visitId, int $serviceId, ?int $userId, ?int $deptId = null): ?array {
    $v = $pdo->prepare("SELECT v.patient_id, v.payment_type, v.insurance_id, v.department_id, COALESCE(pi.company_id, v.insurance_id) AS company_id
                        FROM visits v LEFT JOIN patient_insurance pi ON pi.id = v.insurance_id AND pi.patient_id = v.patient_id
                        WHERE v.id = ?");
    $v->execute([$visitId]);
    $visit = $v->fetch(PDO::FETCH_ASSOC);

    $col = facilityPriceColumn($pdo);
    $s = $pdo->prepare("SELECT procedure_name, $col AS price FROM service_catalog WHERE id = ?");
    $s->execute([$serviceId]);
    $service = $s->fetch(PDO::FETCH_ASSOC);
    if (!$visit || !$service) return null;

    $foreign  = visitForeignTariff($pdo, $visitId);
    $insured  = !$foreign && $visit['insurance_id'] && in_array($visit['payment_type'], ['insurance', 'mixed'], true);
    $price    = $foreign ? tariffServicePrice($pdo, $serviceId, $foreign) : (float)$service['price'];
    $insPrice = 0.0;
    if ($insured) {
        $ip = $pdo->prepare("SELECT price FROM insurance_procedure_prices WHERE service_id = ? AND insurance_id = ? LIMIT 1");
        $ip->execute([$serviceId, (int)$visit['company_id']]);
        $agreed = $ip->fetchColumn();
        if ($agreed !== false) $price = $insPrice = (float)$agreed;
    }

    $invoiceNo = generateInvoiceNo($pdo);
    $pdo->prepare("INSERT INTO invoices (invoice_no, visit_id, patient_id, payment_type, insurance_id, subtotal, total, balance, status, notes, created_by, created_at, department_id)
                   VALUES (?,?,?,?,?,0,0,0,'draft','Consultation fee',?,NOW(),?)")
        ->execute([$invoiceNo, $visitId, (int)$visit['patient_id'], $insured ? $visit['payment_type'] : 'self_pay', $insured ? (int)$visit['insurance_id'] : null, $userId, $deptId ?: $visit['department_id']]);
    $invoiceId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoice_items (invoice_id, service_id, description, category, quantity, unit_price, insurance_price, patient_topup, apply_insurance, discount, total)
                   VALUES (?,?,?,'consultation',1,?,?,0,?,0,?)")
        ->execute([$invoiceId, $serviceId, 'Consultation Fee — ' . $service['procedure_name'], $price, $insPrice, $insured ? 1 : 0, $price]);
    recalcInvoice($pdo, $invoiceId);

    $r = $pdo->prepare("SELECT id, invoice_no, total, balance, status FROM invoices WHERE id = ?");
    $r->execute([$invoiceId]);
    return $r->fetch(PDO::FETCH_ASSOC);
}

/**
 * Consultation fee rule, per department, counted from the patient's last FULL consultation there (cancelled visits/invoices ignored):
 * 0-3 days = free, 4-14 days = follow-up fee, 15+ days or never = full consultation.
 */
function consultRuleForDays(?int $days): string {
    if ($days === null || $days > 14) return 'full';
    return $days <= 3 ? 'free' : 'follow_up';
}
function consultIsFollowUp(string $serviceName): bool { return (bool)preg_match('/follow.?up/i', $serviceName); }

function consultRule(PDO $pdo, int $patientId, int $deptId, int $excludeVisitId = 0): array {
    $q = $pdo->prepare("SELECT v.visit_no, v.visit_date, DATEDIFF(CURDATE(), v.visit_date) AS days
        FROM invoices i JOIN visits v ON v.id = i.visit_id
        JOIN invoice_items ii ON ii.invoice_id = i.id JOIN service_catalog sc ON sc.id = ii.service_id
        WHERE v.patient_id = ? AND v.id <> ? AND v.status <> 'cancelled' AND i.status <> 'cancelled'
          AND ii.category = 'consultation' AND COALESCE(i.department_id, v.department_id) = ?
          AND sc.procedure_name NOT REGEXP 'follow.?up|ward visit'
        ORDER BY v.visit_date DESC, v.id DESC LIMIT 1");
    $q->execute([$patientId, $excludeVisitId, $deptId]);
    $last = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    $days = $last ? (int)$last['days'] : null;
    $k = $pdo->prepare("SELECT visit_kind FROM departments WHERE id = ?");
    $k->execute([$deptId]);
    $kind = $k->fetchColumn() ?: 'consultation';
    return [
        'rule' => $kind === 'no_fee' ? 'no_fee' : consultRuleForDays($days),
        'days' => $days,
        'last_visit_no' => $last['visit_no'] ?? null,
        'last_date' => $last['visit_date'] ?? null,
    ];
}

/** Consultation services allowed for a rule in a department (GP for General Medicine / OPD, psychiatry / ophthalmology their own, else specialist). */
function consultServicesFor(PDO $pdo, int $deptId, string $rule): array {
    if (!in_array($rule, ['full', 'follow_up'], true)) return [];
    $col = facilityPriceColumn($pdo);
    $all = $pdo->query("SELECT id, procedure_name AS name, $col AS price FROM service_catalog
                        WHERE speciality = 'Consultation' AND is_active = 1 AND $col > 0 AND procedure_name NOT LIKE '%ward visit%' ORDER BY procedure_name")->fetchAll(PDO::FETCH_ASSOC);
    $all = array_values(array_filter($all, fn($s) => consultIsFollowUp($s['name']) === ($rule === 'follow_up')));
    return $all;
}

/** Throws RuntimeException when $serviceId does not fit the rule (fee required, follow-up vs full, or no fee allowed). */
function consultCheckService(PDO $pdo, string $rule, ?int $serviceId): void {
    if (in_array($rule, ['free', 'no_fee'], true)) {
        if ($serviceId) throw new RuntimeException($rule === 'free' ? 'No consultation fee: the patient had a full consultation in this department within the last 3 days.' : 'This service has no consultation fee.');
        return;
    }
    if (!$serviceId) return; // "No consultation fee" chosen on purpose (patient who pays nothing)
    $s = $pdo->prepare("SELECT procedure_name FROM service_catalog WHERE id = ? AND speciality = 'Consultation' AND is_active = 1");
    $s->execute([$serviceId]);
    $name = $s->fetchColumn();
    if ($name === false) throw new RuntimeException('Invalid consultation fee.');
    if (consultIsFollowUp($name) !== ($rule === 'follow_up'))
        throw new RuntimeException($rule === 'follow_up' ? 'Within 14 days of the last full consultation: only a follow-up fee can be billed.' : 'More than 14 days since the last full consultation (or first visit): a full consultation fee is required.');
}

/**
 * Sets the visit's consultation fee for a department: keeps it when unchanged, replaces an unpaid one, never touches a paid one.
 * Returns ['invoice_no' => ?string, 'note' => string]. Call inside a transaction.
 */
function setVisitConsultationFee(PDO $pdo, int $visitId, int $deptId, ?int $serviceId, ?int $userId): array {
    $q = $pdo->prepare("SELECT i.id, i.invoice_no, i.paid_amount, i.status, ii.service_id FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id
                        JOIN visits v ON v.id = i.visit_id
                        WHERE i.visit_id = ? AND i.status <> 'cancelled' AND ii.category = 'consultation' AND COALESCE(i.department_id, v.department_id) = ?
                        ORDER BY i.id DESC LIMIT 1");
    $q->execute([$visitId, $deptId]);
    $cur = $q->fetch(PDO::FETCH_ASSOC);
    if ($cur && (int)$cur['service_id'] === (int)$serviceId) return ['invoice_no' => $cur['invoice_no'], 'note' => 'Consultation fee unchanged'];
    if ($cur && ((float)$cur['paid_amount'] > 0 || $cur['status'] === 'paid')) return ['invoice_no' => $cur['invoice_no'], 'note' => 'Consultation fee already paid: not changed (adjust it in Invoicing)'];
    if ($cur) cancelInvoice($pdo, (int)$cur['id'], 'Consultation fee changed at triage');
    if (!$serviceId) return ['invoice_no' => null, 'note' => $cur ? 'Consultation fee removed' : 'No consultation fee'];
    $inv = createConsultationInvoice($pdo, $visitId, $serviceId, $userId, $deptId);
    if (!$inv) throw new RuntimeException('Invalid consultation fee.');
    return ['invoice_no' => $inv['invoice_no'], 'note' => $cur ? 'Consultation fee changed' : 'Consultation fee billed'];
}

/**
 * SQL columns {$alias}_status (paid / covered (insurance pays all) / approved / partial / unpaid, NULL = not billed) and {$alias}_balance (patient still owes)
 * for the visit's invoices holding items that match $itemFilter (alias ii = invoice_items).
 * approved = reception let every unpaid bill go ahead to be paid later (approvals.php).
 * Only informs staff: a service is never blocked for payment, unpaid ones stay recorded on the invoice / as a debt.
 */
function payStatusSql(string $visitCol, string $itemFilter, string $alias = 'pay'): string {
    $inv = "FROM invoices i WHERE i.visit_id = $visitCol AND i.status <> 'cancelled'
            AND EXISTS (SELECT 1 FROM invoice_items ii WHERE ii.invoice_id = i.id AND ($itemFilter))";
    return "(SELECT CASE WHEN COUNT(*) = 0 THEN NULL WHEN SUM(i.balance) <= 0 THEN IF(SUM(i.paid_amount) = 0 AND SUM(i.payment_type = 'insurance') = COUNT(*), 'covered', 'paid')
                         WHEN SUM(i.balance > 0 AND i.approved_at IS NULL) = 0 THEN 'approved'
                         WHEN SUM(i.paid_amount) > 0 THEN 'partial' ELSE 'unpaid' END $inv) AS {$alias}_status,
            (SELECT COALESCE(SUM(i.balance), 0) $inv) AS {$alias}_balance";
}

function invoicePatientShare(PDO $pdo, int $invoiceId, float $copayPct, float $invoiceTotal): float {
    $stmt = $pdo->prepare("SELECT quantity, unit_price, insurance_price, patient_topup, apply_insurance FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    return itemsPatientShare($stmt->fetchAll(PDO::FETCH_ASSOC), $copayPct, $invoiceTotal);
}

function getPatientCopayPercentage(PDO $pdo, ?int $patientId, ?int $insuranceId): float {
    if (!$patientId) return 100.0;
    
    // 1. Try matching patient_insurance by id AND patient_id
    if ($insuranceId) {
        $stmt = $pdo->prepare("
            SELECT ip.copayment_percentage
            FROM patient_insurance pi
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            WHERE pi.id = ? AND pi.patient_id = ?
        ");
        $stmt->execute([$insuranceId, $patientId]);
        $row = $stmt->fetch();
        if ($row && $row['copayment_percentage'] !== null) {
            return (float)$row['copayment_percentage'];
        }

        // 2. Try matching patient_insurance by company_id AND patient_id
        $stmt2 = $pdo->prepare("
            SELECT ip.copayment_percentage
            FROM patient_insurance pi
            LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
            WHERE pi.company_id = ? AND pi.patient_id = ?
            ORDER BY pi.is_primary DESC, pi.created_at DESC
            LIMIT 1
        ");
        $stmt2->execute([$insuranceId, $patientId]);
        $row2 = $stmt2->fetch();
        if ($row2 && $row2['copayment_percentage'] !== null) {
            return (float)$row2['copayment_percentage'];
        }
    }

    // 3. Fallback to patient's primary insurance record
    $stmt3 = $pdo->prepare("
        SELECT ip.copayment_percentage
        FROM patient_insurance pi
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        WHERE pi.patient_id = ? AND pi.is_primary = 1
        LIMIT 1
    ");
    $stmt3->execute([$patientId]);
    $row3 = $stmt3->fetch();
    if ($row3 && $row3['copayment_percentage'] !== null) {
        return (float)$row3['copayment_percentage'];
    }

    // 4. Fallback to any active insurance for this patient
    $stmt4 = $pdo->prepare("
        SELECT ip.copayment_percentage
        FROM patient_insurance pi
        LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        WHERE pi.patient_id = ? AND pi.status = 'active'
        ORDER BY pi.created_at DESC
        LIMIT 1
    ");
    $stmt4->execute([$patientId]);
    $row4 = $stmt4->fetch();
    if ($row4 && $row4['copayment_percentage'] !== null) {
        return (float)$row4['copayment_percentage'];
    }

    // 5. Fallback to active package for this company
    if ($insuranceId) {
        $stmt5 = $pdo->prepare("
            SELECT ip.copayment_percentage
            FROM insurance_packages ip
            JOIN patient_insurance pi ON (pi.company_id = ip.company_id)
            WHERE (pi.id = ? OR pi.company_id = ?) AND pi.patient_id = ? AND ip.status = 'active'
            ORDER BY ip.id ASC
            LIMIT 1
        ");
        $stmt5->execute([$insuranceId, $insuranceId, $patientId]);
        $row5 = $stmt5->fetch();
        if ($row5 && $row5['copayment_percentage'] !== null) {
            return (float)$row5['copayment_percentage'];
        }
    }

    return 100.0;
}

function recalcInvoice(PDO $pdo, int $invoiceId): void {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM invoice_items WHERE invoice_id=?");
    $stmt->execute([$invoiceId]);
    $subtotal = (float)$stmt->fetchColumn();

    $invStmt = $pdo->prepare("SELECT discount, tax, paid_amount, insurance_id, patient_id, payment_type FROM invoices WHERE id=?");
    $invStmt->execute([$invoiceId]);
    $inv = $invStmt->fetch();

    $discount   = (float)($inv['discount']    ?? 0);
    $tax        = (float)($inv['tax']         ?? 0);
    $paidAmount = round((float)($inv['paid_amount'] ?? 0));
    $total      = round(max(0, $subtotal - $discount + $tax));
    $insId      = $inv['insurance_id'] ?? null;
    $patId      = $inv['patient_id']   ?? null;

    // ── Resolve patient's copay percentage ──────────────────────────────────
    $copayPct = 100.0;
    if ($insId && ($inv['payment_type'] === 'insurance' || $inv['payment_type'] === 'mixed')) {
        $copayPct = getPatientCopayPercentage($pdo, (int)$patId, (int)$insId);
    }

    // ── Patient share = the amount the patient is responsible for ──────────
    $patientShare = invoicePatientShare($pdo, $invoiceId, $copayPct, $total);

    // ── Get completed paid and written-off from payment_records ─────────────
    $prStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=? AND status='completed'");
    $prStmt->execute([$invoiceId]);
    $prPaid = round((float)$prStmt->fetchColumn());

    $woStmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=? AND status='written_off'");
    $woStmt->execute([$invoiceId]);
    $prWrittenOff = round((float)$woStmt->fetchColumn());

    $effectivePaid = ($prPaid > 0 || $prWrittenOff > 0) ? $prPaid : $paidAmount;
    $totalSettled = $effectivePaid + $prWrittenOff;

    // ── Balance = what the patient still owes (patient share − settled amount) ──
    $balance = max(0, round($patientShare - $totalSettled));
    if ($balance <= 1.05 && ($totalSettled > 0 || $patientShare == 0)) {
        $balance = 0;
    }

    // ── Status: paid when patient's share is fully covered ─────────────────
    if ($total <= 0) {
        $status = 'issued';
    } elseif ($balance <= 0 && ($totalSettled > 0 || $patientShare == 0)) {
        $status = 'paid';          // patient paid/deferred their part → invoice is done
    } elseif ($totalSettled > 0) {
        $status = 'partial';
    } else {
        $status = 'issued';
    }

    $pdo->prepare("UPDATE invoices SET subtotal=?, total=?, paid_amount=?, balance=?, status=? WHERE id=?")
        ->execute([round($subtotal), $total, $effectivePaid, $balance, $status, $invoiceId]);

    // Update journal entry to match new totals
    recalcJournalEntry($pdo, $invoiceId, $total, $patId, $insId);
}

function recalcJournalEntry(PDO $pdo, int $invoiceId, float $newTotal, ?int $patientId, ?int $insId): void {
    // Find the journal entry for this invoice
    $jeStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type='invoice' AND reference_id=?");
    $jeStmt->execute([$invoiceId]);
    $jeId = $jeStmt->fetchColumn();

    if (!$jeId) {
        if ($newTotal <= 0) return;
        $invStmt = $pdo->prepare("SELECT invoice_no FROM invoices WHERE id=?");
        $invStmt->execute([$invoiceId]);
        $invNo = $invStmt->fetchColumn() ?: ('INV-'.$invoiceId);
        $saleRef = 'SALE-' . date('Ymd') . '-' . str_pad($invoiceId, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(), ?, ?, 'invoice', ?, 1, NOW(), NOW())")
            ->execute([$saleRef, 'Sale - ' . $invNo, $invoiceId]);
        $jeId = (int)$pdo->lastInsertId();
    } else {
        $invStmt = $pdo->prepare("SELECT invoice_no FROM invoices WHERE id=?");
        $invStmt->execute([$invoiceId]);
        $invNo = $invStmt->fetchColumn() ?: ('INV-'.$invoiceId);
        $pdo->prepare("UPDATE journal_entries SET description=?, updated_at=NOW() WHERE id=?")
            ->execute(['Sale - ' . $invNo, $jeId]);
    }

    // Get copayment percentage
    $copayPct = getPatientCopayPercentage($pdo, $patientId, $insId);

    // Delete old lines and recreate
    $pdo->prepare("DELETE FROM journal_entry_lines WHERE journal_entry_id=?")->execute([$jeId]);

    if ($newTotal <= 0) return; // Invoice is empty, no entries needed

    // Find patient's receivable account
    $patientAccId = 7; // fallback
    if ($patientId) {
        $accId = fin_private_ar_acct($pdo);
        if ($accId) $patientAccId = (int)$accId;
    }

    if ($insId && $copayPct < 100) {
        // Patient portion includes top-ups; insurance only carries its copay-adjusted share
        $patientPortion = min($newTotal, invoicePatientShare($pdo, $invoiceId, $copayPct, $newTotal));
        $insPortion = round($newTotal - $patientPortion);

        // Dr: Patient Receivable
        if ($patientPortion > 0) {
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                ->execute([$jeId, (int)$patientAccId, 'Patient portion ('.$copayPct.'%)', $patientPortion]);
        }
        // Dr: Insurance Receivable of the insurer. invoices.insurance_id is usually the patient's policy
        // (patient_insurance.id, as on the visit), older rows hold the company id.
        $co = $pdo->prepare("SELECT company_id FROM patient_insurance WHERE id = ? AND patient_id = ?");
        $co->execute([(int)$insId, (int)$patientId]);
        $companyId = (int)($co->fetchColumn() ?: $insId);
        $insAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE linked_insurance_id=$companyId AND parent_id=7 LIMIT 1")->fetchColumn();
        if (!$insAccId) $insAccId = 7;
        if ($insPortion > 0) {
            $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
                ->execute([$jeId, (int)$insAccId, 'Insurance portion ('.(100-$copayPct).'%)', $insPortion]);
        }
    } else {
        // Self-pay: Dr Patient Receivable full amount
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,0)")
            ->execute([$jeId, (int)$patientAccId, 'Patient receivable (full)', $newTotal]);
    }

    // Cr: Income account
    $incomeAccId = $pdo->query("SELECT id FROM chart_of_accounts WHERE account_type='income' AND status='active' ORDER BY account_code ASC LIMIT 1")->fetchColumn();
    if (!$incomeAccId) $incomeAccId = 1007;

    $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")
        ->execute([$jeId, (int)$incomeAccId, 'Revenue - ' . $invNo, $newTotal]);
}

/** Posts a mirror-image journal entry so the original stays on record but nets to zero. */
function reverseJournalEntry(PDO $pdo, int $jeId, string $referenceType, string $referenceId, string $reason): void {
    $lines = $pdo->prepare("SELECT account_id, line_description, debit, credit FROM journal_entry_lines WHERE journal_entry_id = ?");
    $lines->execute([$jeId]);
    $lines = $lines->fetchAll(PDO::FETCH_ASSOC);
    if (!$lines) return;
    $desc = $pdo->query("SELECT description FROM journal_entries WHERE id = " . $jeId)->fetchColumn();
    $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(), ?, ?, ?, ?, 1, NOW(), NOW())")
        ->execute(['REV-' . date('Ymd') . '-' . $jeId, 'Reversal: ' . $desc . ' | ' . $reason, $referenceType, $referenceId]);
    $revId = (int)$pdo->lastInsertId();
    $ins = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,?)");
    foreach ($lines as $l) {
        $ins->execute([$revId, $l['account_id'], 'Reversal: ' . $l['line_description'], $l['credit'], $l['debit']]);
    }
}

/**
 * Cancels an invoice without erasing it: items and payments stay on record, the sale journal is reversed.
 * Anything the patient already paid shows up as a refund due.
 */
function cancelInvoice(PDO $pdo, int $invoiceId, string $reason = 'Invoice cancelled'): void {
    $status = $pdo->query("SELECT status FROM invoices WHERE id = " . $invoiceId)->fetchColumn();
    if ($status === false || $status === 'cancelled') return;
    // Debts are never cancelled automatically — Finance has to resolve them first
    try {
        $openDebt = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM patient_debts WHERE invoice_id = $invoiceId AND status = 'outstanding'")->fetchColumn();
    } catch (PDOException $e) { $openDebt = 0; } // table not created yet
    if ($openDebt > 0) throw new RuntimeException('This invoice has an outstanding debt with Finance. Finance must clear or write it off first.');
    $je = $pdo->prepare("SELECT id FROM journal_entries WHERE reference_type = 'invoice' AND reference_id = ?");
    $je->execute([(string)$invoiceId]);
    foreach ($je->fetchAll(PDO::FETCH_COLUMN) as $jeId) {
        reverseJournalEntry($pdo, (int)$jeId, 'invoice_reversal', (string)$invoiceId, $reason);
    }
    $pdo->prepare("UPDATE invoices SET status = 'cancelled', balance = 0 WHERE id = ?")->execute([$invoiceId]);
}

/** Removes one invoice line and keeps totals, balance, status and journal in step. An emptied invoice is cancelled. */
function removeInvoiceItem(PDO $pdo, int $itemId): void {
    $invoiceId = (int)$pdo->query("SELECT invoice_id FROM invoice_items WHERE id = " . $itemId)->fetchColumn();
    if (!$invoiceId) return;
    $pdo->prepare("DELETE FROM invoice_items WHERE id = ?")->execute([$itemId]);
    $left = (int)$pdo->query("SELECT COUNT(*) FROM invoice_items WHERE invoice_id = " . $invoiceId)->fetchColumn();
    $left ? recalcInvoice($pdo, $invoiceId) : cancelInvoice($pdo, $invoiceId, 'All items removed');
}

/**
 * Visits with several insurers: visit_insurances lists them (patient_insurance ids); invoices.ins_assigned = 1 once
 * the cashier picked which insurer covers that invoice. DDL commits implicitly — never call inside a transaction.
 */
function ensureVisitInsurers(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS visit_insurances (
        visit_id INT NOT NULL,
        patient_insurance_id INT NOT NULL,
        PRIMARY KEY (visit_id, patient_insurance_id)
    )");
    if (!$pdo->query("SHOW COLUMNS FROM invoices LIKE 'ins_assigned'")->fetch()) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN ins_assigned TINYINT(1) NOT NULL DEFAULT 0");
    }
}

/** The visit's insurers when it has more than one (else empty): pi_id, company_id, company_name, package_name, copayment_percentage. */
function visitInsurers(PDO $pdo, int $visitId): array {
    ensureVisitInsurers($pdo);
    $q = $pdo->prepare("SELECT pi.id AS pi_id, pi.company_id, pi.package_id, pi.member_number, ic.name AS company_name, ip.name AS package_name, ip.copayment_percentage
        FROM visit_insurances vi JOIN patient_insurance pi ON pi.id = vi.patient_insurance_id
        JOIN insurance_companies ic ON ic.id = pi.company_id LEFT JOIN insurance_packages ip ON ip.id = pi.package_id
        WHERE vi.visit_id = ? ORDER BY vi.patient_insurance_id");
    $q->execute([$visitId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    return count($rows) > 1 ? $rows : [];
}

function ensurePatientDebtsTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    // DDL commits implicitly in MySQL — never call this inside a transaction
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_debts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        patient_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        loss_account_id INT NULL,
        status ENUM('outstanding','cleared','written_off') NOT NULL DEFAULT 'outstanding',
        note VARCHAR(255) NULL,
        transferred_by INT NULL,
        transferred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        resolved_by INT NULL,
        resolved_at DATETIME NULL,
        resolution_note TEXT NULL,
        payment_record_id INT NULL,
        INDEX idx_invoice (invoice_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Loss account picked at transfer time (Medical Operation loss, Drug additional loss…) — added after the table first shipped
    $pdo->exec("ALTER TABLE patient_debts ADD COLUMN IF NOT EXISTS loss_account_id INT NULL AFTER amount");
    $done = true;
}
