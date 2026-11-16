<?php
// handlers/hr/leave.php  — handles both leave_types and leave_requests
require_once __DIR__ . '/../../includes/database.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];
// Also check $_POST for FormData submissions
if(empty($input)&&!empty($_POST)){$input=$_POST;}
$id=isset($_GET['id'])?(int)$_GET['id']:null;
$type=$_GET['type']??'requests'; // 'types' or 'requests'

function fetchRequest(PDO $pdo,int $id):?array{
    $s=$pdo->prepare("SELECT lr.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name,lt.name AS leave_type_name,lt.is_paid FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id LEFT JOIN departments d ON d.id=e.department_id JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.id=?");
    $s->execute([$id]); $r=$s->fetch(); return is_array($r)?$r:null;
}

try {

// ===== LEAVE TYPES =====
if($type==='types'){
    if($method==='GET'){
        $s=$pdo->query("SELECT * FROM leave_types ORDER BY name"); ok($s->fetchAll());
    }
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

// ===== LEAVE REQUESTS =====
if($method==='GET'){
    if($id){ $r=fetchRequest($pdo,$id); if(!$r) err('Not found',404); ok($r); }
    $empId=isset($_GET['employee_id'])?(int)$_GET['employee_id']:0;
    $status=$_GET['status']??''; $ltId=isset($_GET['leave_type_id'])?(int)$_GET['leave_type_id']:0;
    $page=max(1,(int)($_GET['page']??1)); $limit=min(100,max(1,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
    $w=[]; $p=[];
    if($empId>0){$w[]="lr.employee_id=?";$p[]=$empId;}
    if($status!==''){$w[]="lr.status=?";$p[]=$status;}
    if($ltId>0){$w[]="lr.leave_type_id=?";$p[]=$ltId;}
    $ws=$w?'WHERE '.implode(' AND ',$w):'';
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM leave_requests lr $ws"); $cnt->execute($p); $total=(int)$cnt->fetchColumn();
    $s=$pdo->prepare("SELECT lr.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name,lt.name AS leave_type_name,lt.is_paid FROM leave_requests lr JOIN employees e ON e.id=lr.employee_id LEFT JOIN departments d ON d.id=e.department_id JOIN leave_types lt ON lt.id=lr.leave_type_id $ws ORDER BY lr.created_at DESC LIMIT $limit OFFSET $offset");
    $s->execute($p); $rows=$s->fetchAll();
    $ss=$pdo->prepare("SELECT SUM(CASE WHEN lr.status='pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN lr.status='approved' THEN 1 ELSE 0 END) AS approved, SUM(CASE WHEN lr.status='rejected' THEN 1 ELSE 0 END) AS rejected, COUNT(*) AS total FROM leave_requests lr $ws");
    $ss->execute($p); $stats=$ss->fetch();
    $types=$pdo->query("SELECT * FROM leave_types WHERE is_active=1 ORDER BY name")->fetchAll();
    ok(['requests'=>$rows,'stats'=>$stats,'leave_types'=>$types,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

if($method==='POST'){
    foreach(['employee_id','leave_type_id','start_date','end_date'] as $f){ if(empty($input[$f])) err("$f required",422); }
    $days=max(1,(int)round((strtotime($input['end_date'])-strtotime($input['start_date']))/86400)+1);
    $proofDoc=null;$proofName=null;
    if(isset($_FILES['proof_document'])&&$_FILES['proof_document']['error']===UPLOAD_ERR_OK){
        $proofDoc=file_get_contents($_FILES['proof_document']['tmp_name']);
        $proofName=$_FILES['proof_document']['name'];
    }
    $s=$pdo->prepare("INSERT INTO leave_requests (employee_id,leave_type_id,start_date,end_date,days_requested,reason,status,proof_document,proof_document_name) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->execute([(int)$input['employee_id'],(int)$input['leave_type_id'],$input['start_date'],$input['end_date'],$days,trim($input['reason']??''),$input['status']??'pending',$proofDoc,$proofName]);
    $newId=(int)$pdo->lastInsertId(); ok(fetchRequest($pdo,$newId),'Leave request submitted',201);
}

if($method==='PUT'){
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id FROM leave_requests WHERE id=?"); $chk->execute([$id]);
    if(!$chk->fetch()) err('Not found',404);
    $s=$pdo->prepare("UPDATE leave_requests SET status=?,reviewed_by=?,reviewed_at=?,review_notes=? WHERE id=?");
    $s->execute([$input['status']??'pending',$input['reviewed_by']??null,in_array($input['status']??'',['approved','rejected'])?date('Y-m-d H:i:s'):null,trim($input['review_notes']??''),$id]);
    ok(fetchRequest($pdo,$id),'Updated');
}

if($method==='DELETE'){
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
