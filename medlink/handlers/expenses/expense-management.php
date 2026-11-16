<?php
// Expense Management: operational workflow in front of the Finance ledger.
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/expense_schema.php';
ex_boot($pdo);

function em_json($data = null, $message = 'Success', $status = 200) { http_response_code($status); echo json_encode(['success' => true, 'message' => $message, 'data' => $data]); exit; }
function em_error($message, $status = 400) { http_response_code($status); echo json_encode(['success' => false, 'message' => $message]); exit; }
function em_uid() { return (int)($_SESSION['user_id'] ?? 0); }
function em_roles() { return $_SESSION['user_data']['role_slugs'] ?? []; }
function em_department_id() {
    static $dept = null; if ($dept !== null) return $dept;
    $uid = em_uid(); if (!$uid) return $dept = 0;
    $dept = (int)($_SESSION['user_data']['department_id'] ?? 0);
    if (!$dept) { global $pdo; $q = $pdo->prepare('SELECT department_id FROM users WHERE id=?'); $q->execute([$uid]); $dept = (int)$q->fetchColumn(); }
    return $dept;
}
function em_is_global() { return (bool)array_intersect(em_roles(), ['admin','accountant','manager']); }
function em_scope_department() { return em_is_global() ? 0 : (em_department_id() ?: -1); }
function em_allowed_tabs() {
    $rules = ['admin'=>['dashboard','add','expenses','approvals','payments','categories','suppliers','reports'],'accountant'=>['dashboard','add','expenses','approvals','payments','categories','suppliers','reports'],'manager'=>['dashboard','add','expenses','approvals','payments','reports'],'department_head'=>['dashboard','add','expenses','approvals'],'housekeeping_officer'=>['dashboard','add','expenses'],'maintenance_officer'=>['dashboard','add','expenses']];
    $out = []; foreach (em_roles() as $role) $out = array_merge($out, $rules[$role] ?? []); return array_values(array_unique($out));
}
function em_require_tab($tab) { if (!in_array($tab, em_allowed_tabs(), true)) em_error('You do not have access to this expense section', 403); }
function em_can_approve() { return (bool)array_intersect(em_roles(), ['admin', 'accountant', 'manager', 'department_head']); }
function em_date($v, $required = true) { $d = substr((string)$v, 0, 10); if (!$d && !$required) return null; if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) em_error('Invalid date', 422); return $d; }
function em_amount($v, $label) { $n = round((float)$v, 2); if ($n < 0) em_error("$label cannot be negative", 422); return $n; }
function em_account(PDO $pdo, $id, $type = null) {
    $q = $pdo->prepare('SELECT id, account_type FROM chart_of_accounts WHERE id = ? AND status = "active" AND has_sub = 0'); $q->execute([(int)$id]); $r = $q->fetch();
    if (!$r || ($type && $r['account_type'] !== $type)) em_error('Invalid account selection', 422); return (int)$r['id'];
}
function em_cash_account(PDO $pdo, $id) {
    $q = $pdo->prepare('SELECT id FROM chart_of_accounts WHERE id=? AND parent_id=3 AND account_type="asset" AND status="active" AND has_sub=0'); $q->execute([(int)$id]);
    if (!$q->fetchColumn()) em_error('Choose a cash, bank or mobile-money account', 422); return (int)$id;
}
function em_audit(PDO $pdo, $id, $action, $from, $to, $comment = null) { $pdo->prepare('INSERT INTO expense_request_audit (expense_request_id, action, from_status, to_status, comment, user_id) VALUES (?,?,?,?,?,?)')->execute([$id, $action, $from, $to, $comment, em_uid()]); }
function em_no(PDO $pdo) { return 'EXP-' . date('Ym') . '-' . str_pad((string)((int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM expense_requests')->fetchColumn()), 5, '0', STR_PAD_LEFT); }
function em_row(PDO $pdo, $id) { $where='e.id=?'; $args=[(int)$id]; if(em_scope_department() !== 0){ $where.=' AND e.department_id=?'; $args[]=em_scope_department(); } $q = $pdo->prepare('SELECT e.*, c.name AS category_name, a.account_code, a.account_name, d.name AS department_name, s.company_name AS supplier_name FROM expense_requests e LEFT JOIN expense_categories c ON c.id=e.category_id LEFT JOIN chart_of_accounts a ON a.id=e.expense_account_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN suppliers s ON s.id=e.supplier_id WHERE '.$where); $q->execute($args); return $q->fetch(); }

$method = $_SERVER['REQUEST_METHOD']; $action = $_GET['action'] ?? ''; $input = json_decode(file_get_contents('php://input'), true) ?: []; $uid = em_uid();
try {
    if ($method === 'GET') {
        if ($action === 'categories') { em_require_tab('categories'); em_json($pdo->query('SELECT c.*, a.account_code, a.account_name FROM expense_categories c LEFT JOIN chart_of_accounts a ON a.id=c.account_id WHERE c.is_active=1 ORDER BY c.name')->fetchAll()); }
        if ($action === 'suppliers') { em_require_tab('suppliers'); em_json($pdo->query("SELECT id,company_name,contact_person,email,phone,address,tax_id,status,is_active FROM suppliers WHERE is_active=1 ORDER BY company_name")->fetchAll()); }
        if ($action === 'accounts') { em_require_tab('add'); em_json($pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE account_type='expense' AND status='active' AND has_sub=0 ORDER BY account_code")->fetchAll()); }
        if ($action === 'cash_accounts') { em_require_tab('payments'); em_json(fin_cash_accounts($pdo)); }
        if ($action === 'departments') { em_require_tab('add'); if(em_scope_department() !== 0){ $q=$pdo->prepare('SELECT id,name FROM departments WHERE id=? AND is_active=1'); $q->execute([em_scope_department()]); em_json($q->fetchAll()); } em_json($pdo->query('SELECT id,name FROM departments WHERE is_active=1 ORDER BY name')->fetchAll()); }
        if ($action === 'list') { em_require_tab('expenses');
            $where = []; $args = []; if(em_scope_department() !== 0){ $where[]='e.department_id=?'; $args[]=em_scope_department(); }
            if (!empty($_GET['status'])) { $where[] = 'e.status=?'; $args[] = $_GET['status']; }
            if (!empty($_GET['from'])) { $where[] = 'e.expense_date>=?'; $args[] = em_date($_GET['from']); }
            if (!empty($_GET['to'])) { $where[] = 'e.expense_date<=?'; $args[] = em_date($_GET['to']); }
            $sql = 'SELECT e.*, c.name category_name, a.account_name, d.name department_name, s.company_name supplier_name FROM expense_requests e LEFT JOIN expense_categories c ON c.id=e.category_id LEFT JOIN chart_of_accounts a ON a.id=e.expense_account_id LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN suppliers s ON s.id=e.supplier_id' . ($where ? ' WHERE '.implode(' AND ', $where) : '') . ' ORDER BY e.id DESC LIMIT 500';
            $q=$pdo->prepare($sql); $q->execute($args); em_json($q->fetchAll());
        }
        if ($action === 'view') { em_require_tab('expenses'); $r=em_row($pdo, (int)($_GET['id']??0)); if(!$r) em_error('Expense not found',404); $p=$pdo->prepare('SELECT * FROM expense_request_payments WHERE expense_request_id=? ORDER BY id'); $p->execute([$r['id']]); $h=$pdo->prepare('SELECT x.*, CONCAT(COALESCE(u.first_name,"")," ",COALESCE(u.last_name,"")) user_name FROM expense_request_audit x LEFT JOIN users u ON u.id=x.user_id WHERE x.expense_request_id=? ORDER BY x.id'); $h->execute([$r['id']]); $d=$pdo->prepare('SELECT id,original_name,mime_type,file_size,created_at FROM expense_request_documents WHERE expense_request_id=? ORDER BY id'); $d->execute([$r['id']]); em_json(['expense'=>$r,'payments'=>$p->fetchAll(),'history'=>$h->fetchAll(),'documents'=>$d->fetchAll()]); }
        em_error('Unknown action',404);
    }

    if ($action === 'category') { em_require_tab('categories');
        if (!em_can_approve()) em_error('Only finance administrators can manage categories',403);
        $name=trim($input['name']??''); if($name==='') em_error('Category name is required',422); $acc=!empty($input['account_id'])?em_account($pdo,$input['account_id'],'expense'):null;
        $pdo->prepare('INSERT INTO expense_categories (name,account_id,created_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE account_id=VALUES(account_id),is_active=1')->execute([$name,$acc,$uid]); em_json(null,'Category saved',201);
    }
    if ($action === 'category_update') { em_require_tab('categories');
        if (!em_can_approve()) em_error('Only authorized users can edit categories',403);
        $id=(int)($input['id']??0); $name=trim($input['name']??''); if(!$id||$name==='') em_error('Category and name are required',422); $acc=!empty($input['account_id'])?em_account($pdo,$input['account_id'],'expense'):null;
        $pdo->prepare('UPDATE expense_categories SET name=?,account_id=? WHERE id=?')->execute([$name,$acc,$id]); em_json(null,'Category updated');
    }
    if ($action === 'category_deactivate') { em_require_tab('categories');
        if (!em_can_approve()) em_error('Only authorized users can deactivate categories',403);
        $id=(int)($input['id']??0); if(!$id) em_error('Category is required',422); $pdo->prepare('UPDATE expense_categories SET is_active=0 WHERE id=?')->execute([$id]); em_json(null,'Category deactivated');
    }
    if ($action === 'supplier') { em_require_tab('suppliers');
        $name=trim($input['company_name']??''); if($name==='') em_error('Supplier name is required',422);
        $pdo->prepare('INSERT INTO suppliers (company_name,contact_person,email,phone,address,tax_id,status,is_active,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,"active",1,?,NOW(),NOW())')->execute([$name,trim($input['contact_person']??''),trim($input['email']??''),trim($input['phone']??''),trim($input['address']??''),trim($input['tax_id']??''),$uid]); em_json(['id'=>(int)$pdo->lastInsertId()],'Supplier created',201);
    }
    if ($action === 'upload') { em_require_tab('add');
        $eid=(int)($_POST['expense_id']??0); $r=em_row($pdo,$eid); if(!$r) em_error('Expense not found',404); if(empty($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK) em_error('Choose a document to upload',422);
        $f=$_FILES['file']; if($f['size']>10*1024*1024) em_error('Documents must be 10 MB or smaller',422); $allowed=['application/pdf','image/jpeg','image/png','image/webp']; $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); if(!in_array($mime,$allowed,true)) em_error('Only PDF, JPG, PNG and WEBP documents are allowed',422);
        $dir=__DIR__.'/../../uploads/expenses'; if(!is_dir($dir)&&!mkdir($dir,0750,true)) em_error('Could not create document storage',500); $stored=bin2hex(random_bytes(16)).'.'.strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)); if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$stored)) em_error('Could not store document',500);
        $pdo->prepare('INSERT INTO expense_request_documents (expense_request_id,original_name,stored_name,mime_type,file_size,uploaded_by) VALUES (?,?,?,?,?,?)')->execute([$eid,substr(basename($f['name']),0,255),$stored,$mime,(int)$f['size'],$uid]); em_audit($pdo,$eid,'attachment',$r['status'],$r['status'],basename($f['name'])); em_json(['id'=>(int)$pdo->lastInsertId()],'Document uploaded',201);
    }
    if ($action === 'save') { em_require_tab('add');
        $id=(int)($input['id']??0); $old=$id?em_row($pdo,$id):null; if($id && (!$old || $old['status']!=='draft')) em_error('Only draft expenses can be edited',409);
        $desc=trim($input['description']??''); if($desc==='') em_error('Description is required',422); $date=em_date($input['expense_date']??null); $due=em_date($input['due_date']??null,false);
        $qty=em_amount($input['quantity']??1,'Quantity'); $qty=$qty?:1; $unit=trim($input['unit']??''); $unitPrice=em_amount($input['unit_price']??$input['amount']??0,'Unit price'); $tax=em_amount($input['tax']??0,'Tax'); $discount=em_amount($input['discount']??0,'Discount');
        $rawItems=is_array($input['items']??null)?$input['items']:[]; $itemSubtotal=0;
        foreach($rawItems as &$item){$iq=em_amount($item['quantity']??1,'Item quantity');$ip=em_amount($item['unit_price']??$item['amount']??0,'Item unit price');$item['quantity']=$iq?:1;$item['unit_price']=$ip;$item['amount']=round($item['quantity']*$ip,2);$itemSubtotal+=$item['amount'];} unset($item);
        if($rawItems){$qty=1;$unit='multiple';$unitPrice=$itemSubtotal;$item_service='Multiple items';} else {$item_service=trim($input['item_service']??'');}
        $total=round(($qty*$unitPrice)+$tax-$discount,2); if($total<=0) em_error('Total must be greater than zero',422);
        $cat=!empty($input['category_id'])?(int)$input['category_id']:null; $acc=!empty($input['expense_account_id'])?em_account($pdo,$input['expense_account_id'],'expense'):null;
        if(!$acc && $cat){$q=$pdo->prepare('SELECT account_id FROM expense_categories WHERE id=? AND is_active=1');$q->execute([$cat]);$acc=(int)$q->fetchColumn()?:null;} if(!$acc) em_error('Choose an expense account',422);
        $supplierId=!empty($input['supplier_id'])?(int)$input['supplier_id']:null; if($supplierId){$q=$pdo->prepare('SELECT company_name,phone,email,address,tax_id FROM suppliers WHERE id=? AND is_active=1');$q->execute([$supplierId]);$supplier=$q->fetch();if(!$supplier)em_error('Supplier not found',404);} else {$supplier=null;}
        $items=json_encode($rawItems,JSON_UNESCAPED_UNICODE); $departmentId=em_scope_department()>0 ? em_scope_department() : (!empty($input['department_id'])?(int)$input['department_id']:null); $fields=[$date,$due,$departmentId,$supplierId,$cat,$acc,$desc,$item_service,$items,$qty,$unit,$unitPrice,$tax,$discount,$total,trim($input['payee']??($supplier['company_name']??'')),trim($input['payee_type']??''),trim($input['telephone']??($supplier['phone']??'')),trim($input['email']??($supplier['email']??'')),trim($input['address']??($supplier['address']??'')),trim($input['tin']??($supplier['tax_id']??'')),trim($input['invoice_reference']??''),trim($input['payment_method']??''),trim($input['comments']??'')];
        $pdo->beginTransaction();
        if($old){$pdo->prepare('UPDATE expense_requests SET expense_date=?,due_date=?,department_id=?,supplier_id=?,category_id=?,expense_account_id=?,description=?,item_service=?,items_json=?,quantity=?,unit=?,unit_price=?,tax=?,discount=?,total=?,payee=?,payee_type=?,telephone=?,email=?,address=?,tin=?,invoice_reference=?,payment_method=?,comments=? WHERE id=?')->execute(array_merge($fields,[$id])); $eid=$id;} else {$pdo->prepare('INSERT INTO expense_requests (expense_no,expense_date,due_date,department_id,supplier_id,category_id,expense_account_id,description,item_service,items_json,quantity,unit,unit_price,tax,discount,total,payee,payee_type,telephone,email,address,tin,invoice_reference,payment_method,comments,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute(array_merge([em_no($pdo)],$fields,[$uid]));$eid=(int)$pdo->lastInsertId();}
        $pdo->commit(); em_audit($pdo,$eid,$old?'edit':'create',$old['status']??null,'draft'); em_json(['id'=>$eid,'expense_no'=>em_row($pdo,$eid)['expense_no']],'Draft saved',201);
    }
    if ($action === 'submit') { em_require_tab('add'); $r=em_row($pdo,(int)($input['id']??0)); if(!$r||$r['status']!=='draft') em_error('Only drafts can be submitted',409); $pdo->prepare('UPDATE expense_requests SET status="submitted",submitted_by=? WHERE id=?')->execute([$uid,$r['id']]); em_audit($pdo,$r['id'],'submit','draft','submitted'); em_json(null,'Expense submitted for approval'); }
    if ($action === 'reject') { em_require_tab('approvals'); if(!em_can_approve()) em_error('You cannot reject expenses',403); $r=em_row($pdo,(int)($input['id']??0)); if(!$r||$r['status']!=='submitted') em_error('Only submitted expenses can be rejected',409); $reason=trim($input['reason']??''); $pdo->prepare('UPDATE expense_requests SET status="rejected",rejected_reason=? WHERE id=?')->execute([$reason?:null,$r['id']]); em_audit($pdo,$r['id'],'reject','submitted','rejected',$reason); em_json(null,'Expense rejected'); }
    if ($action === 'approve') { em_require_tab('approvals');
        if(!em_can_approve()) em_error('You cannot approve expenses',403); $r=em_row($pdo,(int)($input['id']??0)); if(!$r||$r['status']!=='submitted') em_error('Only submitted expenses can be approved',409); if(!in_array('admin', em_roles(), true) && (int)$r['created_by']===$uid) em_error('Self-approval is not allowed',403);
        $pdo->beginTransaction(); fin_post($pdo,['date'=>$r['expense_date'],'type'=>'expense_management','ref_id'=>$r['id'],'dept'=>(int)$r['department_id']?:null,'description'=>'Expense '.$r['expense_no'].': '.$r['description'],'lines'=>[['account_id'=>$r['expense_account_id'],'debit'=>$r['total'],'desc'=>$r['description']],['account_id'=>fin_acct($pdo,'ap'),'credit'=>$r['total'],'desc'=>'Payable '.$r['expense_no']]]]); $pdo->prepare('UPDATE expense_requests SET status="posted",approved_by=?,approved_at=NOW(),posted_at=NOW() WHERE id=?')->execute([$uid,$r['id']]); $pdo->commit(); em_audit($pdo,$r['id'],'approve','submitted','posted'); if (function_exists('log_activity')) log_activity($pdo,'APPROVE','expenses','Expense '.$r['expense_no'].' approved and posted',$r['id']); em_json(null,'Expense approved and posted');
    }
    if ($action === 'pay') { em_require_tab('payments');
        if(!em_can_approve()) em_error('Only authorized finance users can record payment',403); $r=em_row($pdo,(int)($input['id']??0)); if(!$r||!in_array($r['status'],['posted','partially_paid'],true)) em_error('Expense must be approved before payment',409); $amount=em_amount($input['amount']??0,'Payment amount'); $remaining=round($r['total']-$r['amount_paid'],2); if($amount<=0||$amount>$remaining+0.01) em_error('Payment exceeds the balance due',422); $date=em_date($input['payment_date']??null);
        $pay=em_cash_account($pdo,$input['pay_account_id']??0);
        $pdo->beginTransaction(); $pdo->prepare('INSERT INTO expense_request_payments (expense_request_id,payment_date,amount,pay_account_id,method,reference,created_by) VALUES (?,?,?,?,?,?,?)')->execute([$r['id'],$date,$amount,$pay,trim($input['method']??$r['payment_method']??''),trim($input['reference']??''),$uid]); $pid=(int)$pdo->lastInsertId(); fin_post($pdo,['date'=>$date,'type'=>'expense_payment','ref_id'=>$pid,'dept'=>(int)$r['department_id']?:null,'description'=>'Payment for '.$r['expense_no'],'lines'=>[['account_id'=>fin_acct($pdo,'ap'),'debit'=>$amount,'desc'=>'Clear payable '.$r['expense_no']],['account_id'=>$pay,'credit'=>$amount,'desc'=>'Payment '.$r['expense_no']]]]); $new=round($r['amount_paid']+$amount,2); $st=$new+0.01>=$r['total']?'paid':'partially_paid'; $pdo->prepare('UPDATE expense_requests SET amount_paid=?,last_payment_date=?,last_payment_reference=?,status=? WHERE id=?')->execute([$new,$date,trim($input['reference']??'')?:null,$st,$r['id']]); $pdo->commit(); em_audit($pdo,$r['id'],'payment',$r['status'],$st); em_json(['balance'=>round($r['total']-$new,2)],'Payment recorded');
    }
    if ($action === 'cancel') { em_require_tab('expenses');
        if(!em_can_approve()) em_error('Only authorized users can cancel expenses',403); $r=em_row($pdo,(int)($input['id']??0)); if(!$r||in_array($r['status'],['cancelled','rejected'],true)) em_error('Expense cannot be cancelled',409); if(in_array($r['status'],['paid','partially_paid','posted'],true)) { $p=$pdo->prepare('SELECT id FROM expense_request_payments WHERE expense_request_id=?');$p->execute([$r['id']]);$pdo->beginTransaction();foreach($p->fetchAll(PDO::FETCH_COLUMN) as $pid) fin_unpost($pdo,'expense_payment',$pid,'Expense cancelled');fin_unpost($pdo,'expense_management',$r['id'],'Expense cancelled');$pdo->prepare('UPDATE expense_requests SET status="cancelled" WHERE id=?')->execute([$r['id']]);$pdo->commit(); } else {$pdo->prepare('UPDATE expense_requests SET status="cancelled" WHERE id=?')->execute([$r['id']]);} em_audit($pdo,$r['id'],'cancel',$r['status'],'cancelled'); em_json(null,'Expense cancelled with audit trail');
    }
    em_error('Unknown action',404);
} catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); error_log($e->getMessage()); em_error($e->getMessage(),422); }
