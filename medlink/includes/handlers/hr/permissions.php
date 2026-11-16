<?php
// handlers/hr/permissions.php
require_once __DIR__ . '/../../includes/database.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];
// Also check $_POST for FormData submissions
if(empty($input)&&!empty($_POST)){$input=$_POST;}
$id=isset($_GET['id'])?(int)$_GET['id']:null;

function fetchOne(PDO $pdo,int $id):?array{
    $s=$pdo->prepare("SELECT p.*,e.first_name,e.last_name,e.employee_no,d.name AS department_name FROM permission_requests p JOIN employees e ON e.id=p.employee_id LEFT JOIN departments d ON d.id=e.department_id WHERE p.id=?");
    $s->execute([$id]); $r=$s->fetch(); return is_array($r)?$r:null;
}

try {

if ($method==='GET') {
    if($id){ $r=fetchOne($pdo,$id); if(!$r) err('Not found',404); ok($r); }
    $empId=isset($_GET['employee_id'])?(int)$_GET['employee_id']:0;
    $status=$_GET['status']??''; $date=$_GET['date']??'';
    $page=max(1,(int)($_GET['page']??1)); $limit=min(100,max(1,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
    $w=[]; $p=[];
    if($empId>0){$w[]="p.employee_id=?";$p[]=$empId;}
    if($status!==''){$w[]="p.status=?";$p[]=$status;}
    if($date!==''){$w[]="p.request_date=?";$p[]=$date;}
    $ws=$w?'WHERE '.implode(' AND ',$w):'';
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM permission_requests p $ws"); $cnt->execute($p);
    $total=(int)$cnt->fetchColumn();
    $s=$pdo->prepare("SELECT p.*,e.first_name,e.last_name,e.employee_no,d.name AS department_name FROM permission_requests p JOIN employees e ON e.id=p.employee_id LEFT JOIN departments d ON d.id=e.department_id $ws ORDER BY p.request_date DESC, p.created_at DESC LIMIT $limit OFFSET $offset");
    $s->execute($p); $rows=$s->fetchAll();
    $ss=$pdo->prepare("SELECT SUM(CASE WHEN p.status='pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN p.status='approved' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN p.status='rejected' THEN 1 ELSE 0 END) AS rejected, COUNT(*) AS total FROM permission_requests p $ws");
    $ss->execute($p); $stats=$ss->fetch();
    ok(['requests'=>$rows,'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

if ($method==='POST') {
    foreach(['employee_id','request_date','start_time','end_time','reason'] as $f){ if(empty($input[$f])) err("$f required",422); }
    $proofDoc=null;$proofName=null;
    if(isset($_FILES['proof_document'])&&$_FILES['proof_document']['error']===UPLOAD_ERR_OK){
        $proofDoc=file_get_contents($_FILES['proof_document']['tmp_name']);
        $proofName=$_FILES['proof_document']['name'];
    }
    $s=$pdo->prepare("INSERT INTO permission_requests (employee_id,request_date,start_time,end_time,reason,status,proof_document,proof_document_name) VALUES (?,?,?,?,?,?,?,?)");
    $s->execute([(int)$input['employee_id'],$input['request_date'],$input['start_time'],$input['end_time'],trim($input['reason']),$input['status']??'pending',$proofDoc,$proofName]);
    $newId=(int)$pdo->lastInsertId(); $r=fetchOne($pdo,$newId); ok($r,'Permission request submitted',201);
}

if ($method==='PUT') {
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id FROM permission_requests WHERE id=?"); $chk->execute([$id]);
    if(!$chk->fetch()) err('Not found',404);
    // Allow update of status (approve/reject) or full edit if still pending
    $s=$pdo->prepare("UPDATE permission_requests SET status=?,reviewed_by=?,reviewed_at=?,review_notes=? WHERE id=?");
    $s->execute([$input['status']??'pending',$input['reviewed_by']??null,!empty($input['status'])&&$input['status']!=='pending'?date('Y-m-d H:i:s'):null,trim($input['review_notes']??''),$id]);
    ok(fetchOne($pdo,$id),'Updated');
}

if ($method==='DELETE') {
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
