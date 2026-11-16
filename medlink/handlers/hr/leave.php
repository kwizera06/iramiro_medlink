<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/hr/leave.php  — handles leave_types, leave_requests (Employee → HR → Manager workflow) and balances
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/leave_workflow.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];
// Also check $_POST for FormData submissions
if(empty($input)&&!empty($_POST)){$input=$_POST;}
$id=isset($_GET['id'])?(int)$_GET['id']:null;
$type=$_GET['type']??'requests'; // 'types', 'balance' or 'requests'
$me=lw_me($pdo);

// The proof file stays out of these (binary breaks json_encode); it is downloaded with ?download=1
const LR_COLS="lr.id,lr.employee_id,lr.leave_type_id,lr.start_date,lr.end_date,lr.days_requested,lr.reason,lr.status,lr.proof_document_name,
    lr.created_at,lr.created_by,lr.hr_reviewed_by,lr.hr_reviewed_at,lr.hr_notes,lr.reviewed_by,lr.reviewed_at,lr.review_notes,
    e.first_name,e.last_name,e.employee_no,d.name AS dept_name,lt.name AS leave_type_name,lt.is_paid,
    CONCAT(hu.first_name,' ',hu.last_name) AS hr_reviewer_name, CONCAT(ru.first_name,' ',ru.last_name) AS reviewer_name";
const LR_FROM="FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id LEFT JOIN departments d ON d.id=e.department_id
    JOIN leave_types lt ON lt.id=lr.leave_type_id LEFT JOIN users hu ON hu.id=lr.hr_reviewed_by LEFT JOIN users ru ON ru.id=lr.reviewed_by";

function fetchRequest(PDO $pdo,int $id):?array{
    $s=$pdo->prepare("SELECT ".LR_COLS." ".LR_FROM." WHERE lr.id=?");
    $s->execute([$id]); $r=$s->fetch(); return is_array($r)?$r:null;
}

try {

// ===== LEAVE TYPES =====
if($type==='types'){
    if($method==='GET'){
        $s=$pdo->query("SELECT * FROM leave_types ORDER BY name"); ok($s->fetchAll());
    }
    if(!$me['is_hr']) err('Only HR can change leave types',403);
    if($method==='POST'){
        foreach(['name','code','days_allowed'] as $f){ if(empty($input[$f])&&$input[$f]!=='0') err("$f required",422); }
        $chk=$pdo->prepare("SELECT id FROM leave_types WHERE code=?"); $chk->execute([strtoupper($input['code'])]);
        if($chk->fetch()) err('Code already exists',409);
        $s=$pdo->prepare("INSERT INTO leave_types (name,code,days_allowed,is_paid,carry_forward,description) VALUES (?,?,?,?,?,?)");
        $s->execute([trim($input['name']),strtoupper(trim($input['code'])),(int)$input['days_allowed'],(int)($input['is_paid']??1),(int)($input['carry_forward']??0),trim($input['description']??'')]);
        $newId=(int)$pdo->lastInsertId(); $f=$pdo->prepare("SELECT * FROM leave_types WHERE id=?"); $f->execute([$newId]); ok($f->fetch(),'Leave type created',201);
    }
    if($method==='PUT'){
        if(!$id) err('ID required',422);
        $s=$pdo->prepare("UPDATE leave_types SET name=?,days_allowed=?,is_paid=?,carry_forward=?,description=?,is_active=? WHERE id=?");
        $s->execute([trim($input['name']??''),(int)($input['days_allowed']??0),(int)($input['is_paid']??1),(int)($input['carry_forward']??0),trim($input['description']??''),(int)($input['is_active']??1),$id]);
        $f=$pdo->prepare("SELECT * FROM leave_types WHERE id=?"); $f->execute([$id]); ok($f->fetch(),'Updated');
    }
    if($method==='DELETE'){
        if(!$id) err('ID required',422);
        $used=$pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE leave_type_id=?"); $used->execute([$id]);
        if((int)$used->fetchColumn()>0) err('Cannot delete a leave type that has been used',409);
        $pdo->prepare("DELETE FROM leave_types WHERE id=?")->execute([$id]); ok(null,'Deleted');
    }
    err('Method not allowed',405);
}

// ===== BALANCE (own, or any employee for HR) =====
if($type==='balance'){
    $empId=$me['is_hr']&&!empty($_GET['employee_id'])?(int)$_GET['employee_id']:$me['emp_id'];
    if(!$empId) err('Your user account is not linked to an employee record. Ask HR to link it.',404);
    ok(array_values(lw_balance($pdo,$empId,$_GET['date']??null)));
}

// ===== LEAVE REQUESTS =====
if($method==='GET'){
    if($id){
        $r=fetchRequest($pdo,$id); if(!$r) err('Not found',404);
        if(!$me['is_hr']&&(int)$r['employee_id']!==$me['emp_id']) err('Not your request',403);
        if(!empty($_GET['download'])){
            $f=$pdo->prepare("SELECT proof_document,proof_document_name FROM leave_requests WHERE id=?"); $f->execute([$id]); $f=$f->fetch();
            if(!$f||$f['proof_document']===null) err('No document',404);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',$f['proof_document_name']).'"');
            echo $f['proof_document']; exit();
        }
        if($me['is_hr']) $r['balance']=lw_balance($pdo,(int)$r['employee_id'],$r['start_date'])[(int)$r['leave_type_id']]??null;
        ok($r);
    }
    $empId=$me['is_hr']?(int)($_GET['employee_id']??0):$me['emp_id'];
    if(!$me['is_hr']&&!$empId) ok(['requests'=>[],'stats'=>[],'pagination'=>['total'=>0,'page'=>1,'limit'=>20,'total_pages'=>0],'unlinked'=>true]);
    $status=$_GET['status']??''; $ltId=isset($_GET['leave_type_id'])?(int)$_GET['leave_type_id']:0;
    $page=max(1,(int)($_GET['page']??1)); $limit=min(100,max(1,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
    $w=[]; $p=[];
    if($empId>0){$w[]="lr.employee_id=?";$p[]=$empId;}
    if($ltId>0){$w[]="lr.leave_type_id=?";$p[]=$ltId;}
    $sw=$w; $sp=$p; // stats ignore the status filter
    if($status!==''){$w[]="lr.status=?";$p[]=$status;}
    $ws=$w?'WHERE '.implode(' AND ',$w):''; $sws=$sw?'WHERE '.implode(' AND ',$sw):'';
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM leave_requests lr $ws"); $cnt->execute($p); $total=(int)$cnt->fetchColumn();
    $s=$pdo->prepare("SELECT ".LR_COLS." ".LR_FROM." $ws ORDER BY FIELD(lr.status,'pending','hr_reviewed') DESC, lr.created_at DESC LIMIT $limit OFFSET $offset");
    $s->execute($p); $rows=$s->fetchAll();
    $ss=$pdo->prepare("SELECT SUM(lr.status='pending') AS pending, SUM(lr.status='hr_reviewed') AS hr_reviewed, SUM(lr.status='approved') AS approved, SUM(lr.status='rejected') AS rejected, COUNT(*) AS total FROM leave_requests lr $sws");
    $ss->execute($sp); $stats=$ss->fetch();
    $types=$pdo->query("SELECT * FROM leave_types WHERE is_active=1 ORDER BY name")->fetchAll();
    ok(['requests'=>$rows,'stats'=>$stats,'leave_types'=>$types,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

if($method==='POST'){
    // Staff request their own leave; HR may file one on an employee's behalf
    $empId=$me['is_hr']&&!empty($input['employee_id'])?(int)$input['employee_id']:$me['emp_id'];
    if(!$empId) err('Your user account is not linked to an employee record. Ask HR to link it.',422);
    foreach(['leave_type_id','start_date','end_date'] as $f){ if(empty($input[$f])) err("$f required",422); }
    $start=$input['start_date']; $end=$input['end_date'];
    if(!strtotime($start)||!strtotime($end)) err('Invalid dates',422);
    if($end<$start) err('End date is before the start date',422);
    // Working days (Monday to Friday). ponytail: public holidays are not excluded until a holiday calendar exists
    $days=0; for($t=strtotime($start);$t<=strtotime($end);$t+=86400){ if((int)date('N',$t)<=5) $days++; }
    if($days<1) err('The selected dates fall only on weekend days: no leave days to request',422);
    $ltId=(int)$input['leave_type_id'];
    $bal=lw_balance($pdo,$empId,$start)[$ltId]??null;
    if(!$bal) err('Unknown or inactive leave type',422);
    if($bal['remaining']!==null&&$days>$bal['remaining']-$bal['pending'])
        err("Not enough balance: you asked for $days day(s) but only ".max(0,$bal['remaining']-$bal['pending'])." day(s) of {$bal['name']} are left for {$bal['year']}".($bal['pending']?" ({$bal['pending']} already in review)":''),422);
    $ov=$pdo->prepare("SELECT start_date,end_date FROM leave_requests WHERE employee_id=? AND status IN ('pending','hr_reviewed','approved') AND start_date<=? AND end_date>=? LIMIT 1");
    $ov->execute([$empId,$end,$start]);
    if($o=$ov->fetch()) err("Overlaps another leave request ({$o['start_date']} to {$o['end_date']})",409);
    $proofDoc=null;$proofName=null;
    if(isset($_FILES['proof_document'])&&$_FILES['proof_document']['error']===UPLOAD_ERR_OK){
        $proofDoc=file_get_contents($_FILES['proof_document']['tmp_name']);
        $proofName=$_FILES['proof_document']['name'];
    }
    $s=$pdo->prepare("INSERT INTO leave_requests (employee_id,leave_type_id,start_date,end_date,days_requested,reason,status,proof_document,proof_document_name,created_by) VALUES (?,?,?,?,?,?,'pending',?,?,?)");
    $s->execute([$empId,$ltId,$start,$end,$days,trim($input['reason']??''),$proofDoc,$proofName,$me['uid']]);
    $newId=(int)$pdo->lastInsertId();
    lw_submitted($pdo,'leave',$empId,"{$bal['name']}, $start to $end ($days day(s))");
    ok(fetchRequest($pdo,$newId),'Leave request sent to HR for review',201);
}

if($method==='PUT'){
    if(!$id) err('ID required',422);
    try{ $msg=lw_act($pdo,'leave',$id,(string)($input['action']??''),trim((string)($input['notes']??'')),$me); }
    catch(PDOException $e){ throw $e; }
    catch(RuntimeException $e){ err($e->getMessage(),(int)$e->getCode()?:400); }
    ok(fetchRequest($pdo,$id),$msg);
}

if($method==='DELETE'){
    if(!$me['is_hr']) err('Only HR can delete requests; cancel it instead',403);
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id,status FROM leave_requests WHERE id=?"); $chk->execute([$id]); $r=$chk->fetch();
    if(!$r) err('Not found',404);
    if($r['status']==='approved') err('Cannot delete approved leave',409);
    $pdo->prepare("DELETE FROM leave_requests WHERE id=?")->execute([$id]); ok(null,'Deleted');
}
err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
