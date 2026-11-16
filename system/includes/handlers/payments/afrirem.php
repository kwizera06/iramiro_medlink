<?php
// handlers/payments/afrirem.php - MTN MoMo via AfriRem API
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);
require_once __DIR__ . '/../../includes/database.php';

function jsonResp($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success'){jsonResp(['success'=>true,'message'=>$m,'data'=>$d]);}
function error($m,$s=400){jsonResp(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method !== 'POST') error('POST required', 405);

$invoiceId = (int)($input['invoice_id'] ?? 0);
$mobileNumber = trim($input['mobile_number'] ?? '');
$amount = (float)($input['amount'] ?? 0);
$insuranceId = (int)($input['insurance_id'] ?? 0);

// AfriRem API Credentials
$public_key = 'pk_tfyk4r_6340fb7af45559786588b597e6dbe258b237794099f84795_86ef6f691443c86b';
$secret_key = 'sk_10bbc3f1ee73c1255f17beb819a6d021921fcb3ec9a147b4ea52718811b15b5e747e99674767b2780f01131304ac90cb386a440b7d424ca91863546c6bb800c3';

// Validate
if (!$invoiceId) error('invoice_id required');
if (empty($mobileNumber)) error('Mobile number required');
if (!preg_match('/^07[2-9][0-9]{7}$/', $mobileNumber)) error('Invalid phone format. Use: 078XXXXXXX');
if ($amount <= 0) error('Amount must be > 0');

// Get invoice
$invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
$invStmt->execute([$invoiceId]);
$invoice = $invStmt->fetch();
if (!$invoice) error('Invoice not found', 404);

$transactionRef = 'MED-' . strtoupper(substr(uniqid(), -8));

try {
    // Call AfriRem API
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.afrirem.com/api/v2/api.php?endpoint=momo-initiate',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            "phone_number" => $mobileNumber,
            "transaction_reference" => $transactionRef,
            "amount" => (string)$amount,
            "company" => "MedLink Hospital",
            "currency" => "RWF",
            "customer_email" => "billing@medlink.rw"
        ]),
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . $public_key,
            'X-API-Secret: ' . $secret_key,
            'Content-Type: application/json'
        ],
    ]);
    
    $apiResponse = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    
    if ($curlError) error('Connection error: ' . $curlError);
    
    $responseData = json_decode($apiResponse, true);
    
    if ($httpCode == 200 && isset($responseData['success']) && $responseData['success'] === true) {
        // Payment initiated - update invoice
        $pdo->beginTransaction();
        
        // Record payment (pending confirmation from MoMo callback)
        $newBalance = max(0, (float)$invoice['balance'] - $amount);
        $newPaid = (float)$invoice['paid_amount'] + $amount;
        $newStatus = $newBalance <= 0 ? 'paid' : 'partial';
        $pdo->prepare("UPDATE invoices SET paid_amount=?, balance=?, status=?, payment_method='mtn_momo', transaction_reference=?, payment_response=? WHERE id=?")->execute([$newPaid, $newBalance, $newStatus, $transactionRef, json_encode($responseData), $invoiceId]);
        
        // Create journal entry: Dr MoMo (1002), Cr Patient Receivable
        $lastRef = $pdo->query("SELECT reference FROM journal_entries ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 1; if ($lastRef && preg_match('/(\d+)$/', $lastRef, $m)) $seq = (int)$m[1] + 1;
        $jeRef = 'MOMO-' . date('Ymd') . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
        
        $pdo->prepare("INSERT INTO journal_entries (entry_date, reference, description, reference_type, reference_id, posted, posted_at, created_at) VALUES (CURDATE(),?,?,'momo_payment',?,1,NOW(),NOW())")
            ->execute([$jeRef, 'MoMo payment - '.$invoice['invoice_no'].' - '.$mobileNumber, $invoiceId]);
        $jeId = (int)$pdo->lastInsertId();
        
        // Dr: MTN MoMo account (1002)
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,1002,?,?,0)")
            ->execute([$jeId, 'MoMo payment '.$transactionRef, $amount]);
        
        // Cr: Patient Receivable
        $patientId = (int)$invoice['patient_id'];
        $patientAccId = fin_private_ar_acct($pdo) ?: 7;
        $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, line_description, debit, credit) VALUES (?,?,?,0,?)")
            ->execute([$jeId, (int)$patientAccId, 'Patient payment via MoMo', $amount]);
        
        $pdo->commit();
        
        success([
            'transaction_reference' => $transactionRef,
            'amount' => $amount,
            'mobile_number' => $mobileNumber,
            'journal_ref' => $jeRef,
            'api_response' => $responseData
        ], 'MoMo payment initiated. Ref: ' . $transactionRef);
    } else {
        $errMsg = $responseData['message'] ?? $responseData['error'] ?? 'Payment gateway error';
        error('MoMo failed: ' . $errMsg);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error('Error: ' . $e->getMessage());
}
