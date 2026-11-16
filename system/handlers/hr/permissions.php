<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/hr/permissions.php — short absences (hours), same Employee → HR → Manager workflow as leave
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
$me=lw_me($pdo);

// The proof file stays out of these (binary breaks json_encode); it is downloaded with ?download=1
const PR_SELECT="SELECT p.id,p.employee_id,p.request_date,p.start_time,p.end_time,p.reason,p.status,p.proof_document_name,p.created_at,p.created_by,
    p.hr_reviewed_by,p.hr_reviewed_at,p.hr_notes,p.reviewed_by,p.reviewed_at,p.review_notes,
    e.first_name,e.last_name,e.employee_no,d.name AS department_name,
    CONCAT(hu.first_name,' ',hu.last_name) AS hr_reviewer_name, CONCAT(ru.first_name,' ',ru.last_name) AS reviewer_name
    FROM permission_requests p JOIN employees e ON e.id=p.employee_id LEFT JOIN departments d ON d.id=e.department_id
    LEFT JOIN users hu ON hu.id=p.hr_reviewed_by LEFT JOIN users ru ON ru.id=p.reviewed_by";

function fetchOne(PDO $pdo,int $id):?array{
    $s=$pdo->prepare(PR_SELECT." WHERE p.id=?");
    $s->execute([$id]); $r=$s->fetch(); return is_array($r)?$r:null;
}

try {

if ($method==='GET') {
    if($id){
        $r=fetchOne($pdo,$id); if(!$r) err('Not found',404);
        if(!$me['is_hr']&&(int)$r['employee_id']!==$me['emp_id']) err('Not your request',403);
        if(!empty($_GET['download'])){
            $f=$pdo->prepare("SELECT proof_document,proof_document_name FROM permission_requests WHERE id=?"); $f->execute([$id]); $f=$f->fetch();
            if(!$f||$f['proof_document']===null) err('No document',404);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.str_replace(['"',"\r","\n"],'',$f['proof_document_name']).'"');
            echo $f['proof_document']; exit();
        }
        ok($r);
    }
    $empId=$me['is_hr']?(int)($_GET['employee_id']??0):$me['emp_id'];
    if(!$me['is_hr']&&!$empId) ok(['requests'=>[],'stats'=>[],'pagination'=>['total'=>0,'page'=>1,'limit'=>20,'total_pages'=>0],'unlinked'=>true]);
    $status=$_GET['status']??''; $date=$_GET['date']??'';
    $page=max(1,(int)($_GET['page']??1)); $limit=min(100,max(1,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
    $w=[]; $p=[];
    if($empId>0){$w[]="p.employee_id=?";$p[]=$empId;}
    if($date!==''){$w[]="p.request_date=?";$p[]=$date;}
    $sw=$w; $sp=$p; // stats ignore the status filter
    if($status!==''){$w[]="p.status=?";$p[]=$status;}
    $ws=$w?'WHERE '.implode(' AND ',$w):''; $sws=$sw?'WHERE '.implode(' AND ',$sw):'';
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM permission_requests p $ws"); $cnt->execute($p);
    $total=(int)$cnt->fetchColumn();
    $s=$pdo->prepare(PR_SELECT." $ws ORDER BY FIELD(p.status,'pending','hr_reviewed') DESC, p.request_date DESC, p.created_at DESC LIMIT $limit OFFSET $offset");
    $s->execute($p); $rows=$s->fetchAll();
    $ss=$pdo->prepare("SELECT SUM(p.status='pending') AS pending, SUM(p.status='hr_reviewed') AS hr_reviewed, SUM(p.status='approved') AS approved, SUM(p.status='rejected') AS rejected, COUNT(*) AS total FROM permission_requests p $sws");
    $ss->execute($sp); $stats=$ss->fetch();
    ok(['requests'=>$rows,'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

if ($method==='POST') {
    $empId=$me['is_hr']&&!empty($input['employee_id'])?(int)$input['employee_id']:$me['emp_id'];
    if(!$empId) err('Your user account is not linked to an employee record. Ask HR to link it.',422);
    foreach(['request_date','start_time','end_time','reason'] as $f){ if(empty($input[$f])) err("$f required",422); }
    if($input['end_time']<=$input['start_time']) err('End time must be after the start time',422);
    $proofDoc=null;$proofName=null;
    if(isset($_FILES['proof_document'])&&$_FILES['proof_document']['error']===UPLOAD_ERR_OK){
        $proofDoc=file_get_contents($_FILES['proof_document']['tmp_name']);
        $proofName=$_FILES['proof_document']['name'];
    }
    $s=$pdo->prepare("INSERT INTO permission_requests (employee_id,request_date,start_time,end_time,reason,status,proof_document,proof_document_name,created_by) VALUES (?,?,?,?,?,'pending',?,?,?)");
    $s->execute([$empId,$input['request_date'],$input['start_time'],$input['end_time'],trim($input['reason']),$proofDoc,$proofName,$me['uid']]);
    $newId=(int)$pdo->lastInsertId();
    lw_submitted($pdo,'permission',$empId,"{$input['request_date']} {$input['start_time']}–{$input['end_time']}: ".trim($input['reason']));
    ok(fetchOne($pdo,$newId),'Permission request sent to HR for review',201);
}

if ($method==='PUT') {
    if(!$id) err('ID required',422);
    try{ $msg=lw_act($pdo,'permission',$id,(string)($input['action']??''),trim((string)($input['notes']??'')),$me); }
    catch(PDOException $e){ throw $e; }
    catch(RuntimeException $e){ err($e->getMessage(),(int)$e->getCode()?:400); }
    ok(fetchOne($pdo,$id),$msg);
}

if ($method==='DELETE') {
    if(!$me['is_hr']) err('Only HR can delete requests; cancel it instead',403);
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id,status FROM permission_requests WHERE id=?"); $chk->execute([$id]); $r=$chk->fetch();
    if(!$r) err('Not found',404);
    if($r['status']==='approved') err('Cannot delete an approved permission',409);
    $pdo->prepare("DELETE FROM permission_requests WHERE id=?")->execute([$id]);
    ok(null,'Deleted');
}
err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
