<?php
// handlers/payments/afrirem.php - MTN MoMo via AfriRem API
// action=initiate (default): sends the MoMo prompt and records a *pending* payment — the invoice is not touched.
// action=check_status: asks AfriRem; only a successful payment is applied to the invoice and the ledger.
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/billing-calc.php';
require_once __DIR__ . '/../../includes/visit-routing.php';
require_once __DIR__ . '/../../includes/auth.php';

function jsonResp($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success'){jsonResp(['success'=>true,'message'=>$m,'data'=>$d]);}
function error($m,$s=400){jsonResp(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? 'initiate');

if ($method !== 'POST') error('POST required', 405);

// Only finance/cashier staff (or administrators) may move money.
if (!isUserAdmin($pdo) && !isFinanceUser($pdo)) {
    error('You do not have permission to initiate payments.', 403);
}

// AfriRem API Credentials
$public_key = 'pk_tfyk4r_6340fb7af45559786588b597e6dbe258b237794099f84795_86ef6f691443c86b';
$secret_key = 'sk_10bbc3f1ee73c1255f17beb819a6d021921fcb3ec9a147b4ea52718811b15b5e747e99674767b2780f01131304ac90cb386a440b7d424ca91863546c6bb800c3';

function afriremCall(string $url, ?array $body, string $pk, string $sk): array {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => $body === null ? 'GET' : 'POST',
        CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body),
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $pk, 'X-API-Secret: ' . $sk, 'Content-Type: application/json'],
    ]);
    $raw = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($curlError) error('Connection error: ' . $curlError);
    return [$httpCode, json_decode((string)$raw, true)];
}

try {

// ===== CHECK STATUS — apply the payment only when AfriRem says it succeeded =====
if ($action === 'check_status') {
    $ref = trim($input['transaction_reference'] ?? ($input['ref'] ?? ''));
    if (!$ref) error('transaction_reference required', 422);

    $pr = $pdo->prepare("SELECT * FROM payment_records WHERE reference_number = ? AND payment_method = 'mobile_money' ORDER BY id DESC LIMIT 1");
    $pr->execute([$ref]);
    $payment = $pr->fetch();
    if (!$payment) error('MoMo payment not found', 404);
    if ($payment['status'] !== 'pending') success(['payment_status' => $payment['status'], 'receipt_no' => $payment['receipt_number']], 'Payment already ' . $payment['status']);

    [$httpCode, $resp] = afriremCall('https://api.afrirem.com/api/v2/api.php?endpoint=momo-status&reference=' . urlencode($ref), null, $public_key, $secret_key);
    if (!is_array($resp)) error('Invalid response from payment gateway');
    $gatewayStatus = strtolower((string)($resp['status'] ?? ($resp['transaction_status'] ?? ($resp['data']['status'] ?? ''))));

    if (in_array($gatewayStatus, ['failed', 'cancelled', 'rejected', 'expired'], true)) {
        $pdo->prepare("UPDATE payment_records SET status = 'failed', notes = CONCAT(COALESCE(notes,''), ?) WHERE id = ? AND status = 'pending'")
            ->execute([' | MoMo ' . $gatewayStatus, $payment['id']]);
        success(['payment_status' => 'failed', 'gateway_status' => $gatewayStatus], 'MoMo payment ' . $gatewayStatus . ' — nothing was recorded on the invoice.');
    }
    if (!in_array($gatewayStatus, ['successful', 'success', 'completed', 'paid'], true)) {
        success(['payment_status' => 'pending', 'gateway_status' => $gatewayStatus], 'Payment still pending — the patient has not confirmed yet.');
    }

    $invoiceId = (int)$payment['invoice_id'];
    $amount = round((float)$payment['amount_paid']);
    $pdo->beginTransaction();
    try {
        // Flip pending → completed exactly once, even if two people check at the same time
        $flip = $pdo->prepare("UPDATE payment_records SET status = 'completed', verified_at = NOW(), notes = CONCAT(COALESCE(notes,''), ' | MoMo confirmed') WHERE id = ? AND status = 'pending'");
        $flip->execute([$payment['id']]);
        if ($flip->rowCount() !== 1) { $pdo->rollBack(); success(['payment_status' => 'completed'], 'Payment already confirmed'); }

        $inv = $pdo->prepare("SELECT * FROM invoices WHERE id = ? FOR UPDATE");
        $inv->execute([$invoiceId]);
        $invoice = $inv->fetch();

        // Same balance rule as the cashier screen: patient share minus everything paid or written off
        $insId = (int)($invoice['insurance_id'] ?? 0);
        $copay = ($insId && in_array($invoice['payment_type'], ['insurance', 'mixed'], true)) ? getPatientCopayPercentage($pdo, (int)$invoice['patient_id'], $insId) : 100.0;
        $patientShare = invoicePatientShare($pdo, $invoiceId, $copay, round((float)$invoice['total']));
        $sum = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN status='completed' THEN amount_paid END),0), COALESCE(SUM(CASE WHEN status='written_off' THEN amount_paid END),0) FROM payment_records WHERE invoice_id = ?");
        $sum->execute([$invoiceId]);
        [$paid, $writtenOff] = array_map('round', array_map('floatval', $sum->fetch(PDO::FETCH_NUM)));
        $balance = max(0, round($patientShare - $paid - $writtenOff));
        $status = $balance <= 0 ? 'paid' : 'partial';
        $pdo->prepare("UPDATE invoices SET paid_amount=?, balance=?, status=?, payment_method='mtn_momo', transaction_reference=?, payment_response=? WHERE id=?")
            ->execute([$paid, $balance, $status, $ref, json_encode($resp), $invoiceId]);

        // Dr MTN MoMo account (1002), Cr patient receivable
        $patientId = (int)$invoice['patient_id'];
        $patientAccId = fin_private_ar_acct($pdo) ?: 7;
        $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'momo_payment',?,1,NOW(),NOW())")
            ->execute(['MOMO-' . $ref, 'MoMo payment - ' . $invoice['invoice_no'], $invoiceId]);
        $jeId = (int)$pdo->lastInsertId();
        $line = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,?,?)");
        $line->execute([$jeId, 1002, 'MoMo payment ' . $ref, $amount, 0]);
        $line->execute([$jeId, (int)$patientAccId, 'Patient payment via MoMo', 0, $amount]);

        $pdo->commit();
        vrTryCloseForInvoice($pdo, $invoiceId);
        success(['payment_status' => 'completed', 'receipt_no' => $payment['receipt_number'], 'invoice_status' => $status, 'balance' => $balance], 'MoMo payment confirmed and recorded.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error('Failed to record payment: ' . $e->getMessage(), 500);
    }
}

// ===== INITIATE — send the MoMo prompt; nothing is paid until check_status confirms it =====
$invoiceId = (int)($input['invoice_id'] ?? 0);
$mobileNumber = trim($input['mobile_number'] ?? '');
$amount = round((float)($input['amount'] ?? 0));

if (!$invoiceId) error('invoice_id required');
if (empty($mobileNumber)) error('Mobile number required');
if (!preg_match('/^07[2-9][0-9]{7}$/', $mobileNumber)) error('Invalid phone format. Use: 078XXXXXXX');
if ($amount <= 0) error('Amount must be > 0');

$invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
$invStmt->execute([$invoiceId]);
$invoice = $invStmt->fetch();
if (!$invoice) error('Invoice not found', 404);
if ($invoice['status'] === 'cancelled') error('Invoice is cancelled', 409);
$pend = $pdo->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM payment_records WHERE invoice_id=? AND status='pending'");
$pend->execute([$invoiceId]);
$open = round((float)$invoice['balance']) - round((float)$pend->fetchColumn());
if ($amount > $open) error('Amount is more than the balance still to pay (' . number_format(max(0, $open)) . ' RWF, after MoMo payments waiting for confirmation)', 422);

$transactionRef = 'MED-' . strtoupper(substr(uniqid(), -8));
[$httpCode, $resp] = afriremCall('https://api.afrirem.com/api/v2/api.php?endpoint=momo-initiate', [
    "phone_number" => $mobileNumber,
    "transaction_reference" => $transactionRef,
    "amount" => (string)$amount,
    "company" => "MedLink Hospital",
    "currency" => "RWF",
    "customer_email" => "billing@medlink.rw"
], $public_key, $secret_key);

if ($httpCode != 200 || !is_array($resp) || ($resp['success'] ?? false) !== true) {
    error('MoMo failed: ' . ($resp['message'] ?? ($resp['error'] ?? 'Payment gateway error')));
}

$lastReceipt = $pdo->query("SELECT receipt_number FROM payment_records ORDER BY id DESC LIMIT 1")->fetchColumn();
$rSeq = ($lastReceipt && preg_match('/(\d+)$/', $lastReceipt, $rm)) ? (int)$rm[1] + 1 : 1;
$receiptNo = 'RCP-' . date('Ymd') . '-' . str_pad($rSeq, 4, '0', STR_PAD_LEFT);
$pdo->prepare("INSERT INTO payment_records (invoice_id, patient_id, amount_paid, payment_method, payment_date, payment_time, reference_number, receipt_number, status, notes, created_by, created_at)
               VALUES (?, ?, ?, 'mobile_money', CURDATE(), CURTIME(), ?, ?, 'pending', ?, NULL, NOW())")
    ->execute([$invoiceId, (int)$invoice['patient_id'], $amount, $transactionRef, $receiptNo, 'MoMo prompt sent to ' . $mobileNumber]);

success([
    'transaction_reference' => $transactionRef,
    'amount' => $amount,
    'mobile_number' => $mobileNumber,
    'payment_status' => 'pending',
], 'MoMo prompt sent. The invoice is updated once the patient confirms — check the status with reference ' . $transactionRef);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error('Error: ' . $e->getMessage());
}
