<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/hr/attendance.php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/leave_workflow.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];
$id=isset($_GET['id'])?(int)$_GET['id']:null;

try {

// attendance_records.approval (self check-ins wait for HR) is created by fin_install, which api.php runs first

// Floating clock button (footer, every page): the signed-in employee's own record for today
if (($_GET['endpoint']??'')==='hr/my-attendance') {
    $me=lw_me($pdo);
    if(!$me['emp_id']) ok(null,'No employee profile linked to this account');
    // ponytail: today only, a night shift checking out after midnight sees a fresh day; carry yesterday's open record if needed
    $rec=function() use($pdo,$me){$s=$pdo->prepare("SELECT * FROM attendance_records WHERE employee_id=? AND work_date=CURDATE()");$s->execute([$me['emp_id']]);return $s->fetch()?:null;};
    $msg='Success';
    if ($method==='POST') {
        $act=$input['action']??''; $r=$rec();
        if ($act==='check_in') {
            if($r) err('Attendance already recorded for today',409);
            $pdo->prepare("INSERT INTO attendance_records (employee_id,work_date,check_in,status,approval,recorded_by) VALUES (?,CURDATE(),CURTIME(),'present','pending',?)")->execute([$me['emp_id'],$me['uid']]);
            $msg='Checked in, waiting to be approved';
        } elseif ($act==='check_out') {
            if(!$r||!$r['check_in']) err('Check in first',409);
            if($r['check_out']) err('Already checked out today',409);
            $pdo->prepare("UPDATE attendance_records SET check_out=CURTIME() WHERE id=?")->execute([$r['id']]);
            $msg='Checked out';
        } else err('Unknown action',422);
    } elseif ($method!=='GET') err('Method not allowed',405);
    $e=$pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name,employee_no FROM employees WHERE id=?"); $e->execute([$me['emp_id']]);
    ok(['employee'=>$e->fetch(),'record'=>$rec(),'now'=>$pdo->query("SELECT DATE_FORMAT(NOW(),'%Y-%m-%dT%H:%i:%s')")->fetchColumn()],$msg);
}

if ($method==='GET') {
    if ($id) {
        $s=$pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_no FROM attendance_records a JOIN employees e ON e.id=a.employee_id WHERE a.id=?");
        $s->execute([$id]); $r=$s->fetch(); if(!$r) err('Not found',404); ok($r);
    }
    $empId=isset($_GET['employee_id'])?(int)$_GET['employee_id']:0;
    $date=$_GET['date']??''; $month=$_GET['month']??''; $status=$_GET['status']??'';
    $page=max(1,(int)($_GET['page']??1)); $limit=min(200,max(1,(int)($_GET['limit']??50)));
    $offset=($page-1)*$limit;
    $w=[]; $p=[];
    if($empId>0){$w[]="a.employee_id=?";$p[]=$empId;}
    if($date!==''){$w[]="a.work_date=?";$p[]=$date;}
    if($month!==''){$w[]="DATE_FORMAT(a.work_date,'%Y-%m')=?";$p[]=$month;}
    if($status==='pending') $w[]="a.approval='pending'";
    elseif($status!==''){$w[]="a.status=?";$p[]=$status;}
    $ws=$w?'WHERE '.implode(' AND ',$w):'';
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM attendance_records a $ws"); $cnt->execute($p);
    $total=(int)$cnt->fetchColumn();
    $s=$pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_no,d.name AS department_name FROM attendance_records a JOIN employees e ON e.id=a.employee_id LEFT JOIN departments d ON d.id=e.department_id $ws ORDER BY a.work_date DESC,e.employee_no ASC LIMIT $limit OFFSET $offset");
    $s->execute($p); $rows=$s->fetchAll();
    // Stats for filter
    $ss=$pdo->prepare("SELECT SUM(CASE WHEN a.status='present' AND a.approval='approved' THEN 1 ELSE 0 END) AS present, SUM(CASE WHEN a.approval='pending' THEN 1 ELSE 0 END) AS pending, SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) AS absent, SUM(CASE WHEN a.status='late' THEN 1 ELSE 0 END) AS late, SUM(CASE WHEN a.status='on-leave' THEN 1 ELSE 0 END) AS on_leave, COUNT(*) AS total FROM attendance_records a $ws");
    $ss->execute($p); $stats=$ss->fetch();
    ok(['records'=>$rows,'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

if ($method==='POST') {
    if(empty($input['employee_id'])||empty($input['work_date'])||empty($input['status'])) err('employee_id, work_date, status required',422);
    if(!strtotime($input['work_date'])) err('work_date is not a valid date',422);
    if($input['work_date']>date('Y-m-d')) err('Attendance cannot be recorded for a future date',422);
    if(!empty($input['check_in']) && !empty($input['check_out']) && $input['check_out']<=$input['check_in']) err('Check-out must be after check-in',422);
    if((float)($input['overtime_hours']??0)<0 || (float)($input['overtime_hours']??0)>16) err('Overtime hours must be between 0 and 16',422);
    if((int)($input['late_minutes']??0)<0) err('Late minutes cannot be negative',422);
    $dup=$pdo->prepare("SELECT id FROM attendance_records WHERE employee_id=? AND work_date=?");
    $dup->execute([$input['employee_id'],$input['work_date']]);
    if($dup->fetch()) err('Attendance already recorded for this date',409);
    $s=$pdo->prepare("INSERT INTO attendance_records (employee_id,work_date,check_in,check_out,status,late_minutes,overtime_hours,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?)");
    $s->execute([(int)$input['employee_id'],$input['work_date'],$input['check_in']??null,$input['check_out']??null,$input['status'],(int)($input['late_minutes']??0),(float)($input['overtime_hours']??0),trim($input['notes']??''),(int)($_SESSION['user_id']??0)?:null]);
    $newId=(int)$pdo->lastInsertId();
    $f=$pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_no FROM attendance_records a JOIN employees e ON e.id=a.employee_id WHERE a.id=?");
    $f->execute([$newId]); ok($f->fetch(),'Attendance recorded',201);
}

if ($method==='PUT') {
    if(!$id) err('ID required',422);
    if (($input['action']??'')==='approve') {
        if(!lw_me($pdo)['is_hr']) err('Only HR can approve attendance',403);
        $s=$pdo->prepare("UPDATE attendance_records SET approval='approved',approved_by=?,approved_at=NOW() WHERE id=? AND approval='pending'");
        $s->execute([(int)$_SESSION['user_id'],$id]);
        if(!$s->rowCount()) err('Record not found or already approved',409);
        ok(null,'Approved');
    }
    $chk=$pdo->prepare("SELECT id FROM attendance_records WHERE id=?"); $chk->execute([$id]);
    if(!$chk->fetch()) err('Not found',404);
    $s=$pdo->prepare("UPDATE attendance_records SET check_in=?,check_out=?,status=?,late_minutes=?,overtime_hours=?,notes=? WHERE id=?");
    $s->execute([$input['check_in']??null,$input['check_out']??null,$input['status']??'present',(int)($input['late_minutes']??0),(float)($input['overtime_hours']??0),trim($input['notes']??''),$id]);
    $f=$pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_no FROM attendance_records a JOIN employees e ON e.id=a.employee_id WHERE a.id=?");
    $f->execute([$id]); ok($f->fetch(),'Updated');
}

if ($method==='DELETE') {
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id FROM attendance_records WHERE id=?"); $chk->execute([$id]);
    if(!$chk->fetch()) err('Not found',404);
    $pdo->prepare("DELETE FROM attendance_records WHERE id=?")->execute([$id]);
    ok(null,'Deleted');
}
err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
