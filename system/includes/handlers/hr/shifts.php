<?php
// handlers/hr/shifts.php — Shift Types, Duty Rosters, Assignments
require_once __DIR__ . '/../../includes/database.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];
$id=isset($_GET['id'])?(int)$_GET['id']:null;
$entity=$_GET['entity']??'shift-types'; // shift-types, rosters, assignments

try {

// ============================================================
// SHIFT TYPES
// ============================================================
if($entity==='shift-types'){

    if($method==='GET'){
        if($id){
            $s=$pdo->prepare("SELECT * FROM shift_types WHERE id=?");$s->execute([$id]);$r=$s->fetch();
            if(!$r) err('Not found',404); ok($r);
        }
        $active=$_GET['active']??'';
        $w=[];$p=[];
        if($active!==''){$w[]="is_active=?";$p[]=(int)$active;}
        $ws=$w?'WHERE '.implode(' AND ',$w):'';
        $s=$pdo->prepare("SELECT * FROM shift_types $ws ORDER BY start_time ASC");$s->execute($p);
        $stats=$pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN category='night' THEN 1 ELSE 0 END) AS night_shifts FROM shift_types")->fetch();
        ok(['shift_types'=>$s->fetchAll(),'stats'=>$stats]);
    }
    if($method==='POST'){
        foreach(['name','code','start_time','end_time','hours'] as $f){if(empty($input[$f])) err("$f required",422);}
        $s=$pdo->prepare("INSERT INTO shift_types (name,code,start_time,end_time,hours,category,allowance_rate,is_active) VALUES (?,?,?,?,?,?,?,?)");
        $s->execute([trim($input['name']),strtoupper(trim($input['code'])),$input['start_time'],$input['end_time'],(float)$input['hours'],$input['category']??'regular',(float)($input['allowance_rate']??0),(int)($input['is_active']??1)]);
        ok(['id'=>(int)$pdo->lastInsertId()],'Shift type created',201);
    }
    if($method==='PUT'){
        if(!$id) err('ID required',422);
        $chk=$pdo->prepare("SELECT id FROM shift_types WHERE id=?");$chk->execute([$id]);if(!$chk->fetch()) err('Not found',404);
        $s=$pdo->prepare("UPDATE shift_types SET name=?,code=?,start_time=?,end_time=?,hours=?,category=?,allowance_rate=?,is_active=? WHERE id=?");
        $s->execute([trim($input['name']??''),strtoupper(trim($input['code']??'')),$input['start_time']??'00:00',$input['end_time']??'00:00',(float)($input['hours']??0),$input['category']??'regular',(float)($input['allowance_rate']??0),(int)($input['is_active']??1),$id]);
        ok(null,'Updated');
    }
    if($method==='DELETE'){
        if(!$id) err('ID required',422);
        $chk=$pdo->prepare("SELECT id FROM shift_types WHERE id=?");$chk->execute([$id]);if(!$chk->fetch()) err('Not found',404);
        $pdo->prepare("DELETE FROM shift_types WHERE id=?")->execute([$id]);ok(null,'Deleted');
    }
}

// ============================================================
// DUTY ROSTERS
// ============================================================
elseif($entity==='rosters'){

    if($method==='GET'){
        if($id){
            $s=$pdo->prepare("SELECT r.*,d.name AS dept_name FROM duty_rosters r LEFT JOIN departments d ON d.id=r.department_id WHERE r.id=?");
            $s->execute([$id]);$r=$s->fetch();if(!$r) err('Not found',404); ok($r);
        }
        $page=max(1,(int)($_GET['page']??1));$limit=min(100,max(1,(int)($_GET['limit']??20)));$offset=($page-1)*$limit;
        $w=[];$p=[];
        if(!empty($_GET['status'])){$w[]="r.status=?";$p[]=$_GET['status'];}
        if(!empty($_GET['department_id'])){$w[]="r.department_id=?";$p[]=(int)$_GET['department_id'];}
        $ws=$w?'WHERE '.implode(' AND ',$w):'';
        $cnt=$pdo->prepare("SELECT COUNT(*) FROM duty_rosters r $ws");$cnt->execute($p);$total=(int)$cnt->fetchColumn();
        $s=$pdo->prepare("SELECT r.*,d.name AS dept_name FROM duty_rosters r LEFT JOIN departments d ON d.id=r.department_id $ws ORDER BY r.start_date DESC LIMIT $limit OFFSET $offset");
        $s->execute($p);
        $stats=$pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='active' OR status='published' THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) AS draft, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed FROM duty_rosters")->fetch();
        ok(['rosters'=>$s->fetchAll(),'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
    }
    if($method==='POST'){
        foreach(['roster_name','start_date','end_date'] as $f){if(empty($input[$f])) err("$f required",422);}
        $s=$pdo->prepare("INSERT INTO duty_rosters (roster_name,department_id,unit_name,start_date,end_date,roster_type,status,notes) VALUES (?,?,?,?,?,?,?,?)");
        $s->execute([trim($input['roster_name']),!empty($input['department_id'])?(int)$input['department_id']:null,trim($input['unit_name']??''),$input['start_date'],$input['end_date'],$input['roster_type']??'monthly',$input['status']??'draft',trim($input['notes']??'')]);
        ok(['id'=>(int)$pdo->lastInsertId()],'Roster created',201);
    }
    if($method==='PUT'){
        if(!$id) err('ID required',422);
        $chk=$pdo->prepare("SELECT id FROM duty_rosters WHERE id=?");$chk->execute([$id]);if(!$chk->fetch()) err('Not found',404);
        $s=$pdo->prepare("UPDATE duty_rosters SET roster_name=?,department_id=?,unit_name=?,start_date=?,end_date=?,roster_type=?,status=?,notes=? WHERE id=?");
        $s->execute([trim($input['roster_name']??''),!empty($input['department_id'])?(int)$input['department_id']:null,trim($input['unit_name']??''),$input['start_date']??date('Y-m-d'),$input['end_date']??date('Y-m-d'),$input['roster_type']??'monthly',$input['status']??'draft',trim($input['notes']??''),$id]);
        ok(null,'Updated');
    }
    if($method==='DELETE'){
        if(!$id) err('ID required',422);
        $pdo->prepare("DELETE FROM duty_rosters WHERE id=?")->execute([$id]);ok(null,'Deleted');
    }
}

// ============================================================
// ROSTER SHIFT ASSIGNMENTS
// ============================================================
elseif($entity==='assignments'){

    if($method==='GET'){
        if($id){
            $s=$pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_no,st.name AS shift_name,st.start_time AS shift_start,st.end_time AS shift_end,r.roster_name FROM roster_shift_assignments a JOIN employees e ON e.id=a.employee_id JOIN shift_types st ON st.id=a.shift_type_id JOIN duty_rosters r ON r.id=a.roster_id WHERE a.id=?");
            $s->execute([$id]);$r=$s->fetch();if(!$r) err('Not found',404);ok($r);
        }
        $page=max(1,(int)($_GET['page']??1));$limit=min(100,max(1,(int)($_GET['limit']??30)));$offset=($page-1)*$limit;
        $w=[];$p=[];
        if(!empty($_GET['roster_id'])){$w[]="a.roster_id=?";$p[]=(int)$_GET['roster_id'];}
        if(!empty($_GET['employee_id'])){$w[]="a.employee_id=?";$p[]=(int)$_GET['employee_id'];}
        if(!empty($_GET['shift_date'])){$w[]="a.shift_date=?";$p[]=$_GET['shift_date'];}
        if(!empty($_GET['status'])){$w[]="a.status=?";$p[]=$_GET['status'];}
        $ws=$w?'WHERE '.implode(' AND ',$w):'';
        $cnt=$pdo->prepare("SELECT COUNT(*) FROM roster_shift_assignments a $ws");$cnt->execute($p);$total=(int)$cnt->fetchColumn();
        $s=$pdo->prepare("SELECT a.*,e.first_name,e.last_name,e.employee_no,st.name AS shift_name,st.code AS shift_code,st.start_time AS shift_start,st.end_time AS shift_end,r.roster_name FROM roster_shift_assignments a JOIN employees e ON e.id=a.employee_id JOIN shift_types st ON st.id=a.shift_type_id JOIN duty_rosters r ON r.id=a.roster_id $ws ORDER BY a.shift_date DESC, st.start_time ASC LIMIT $limit OFFSET $offset");
        $s->execute($p);
        $stats=$pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='scheduled' THEN 1 ELSE 0 END) AS scheduled, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) AS absent FROM roster_shift_assignments")->fetch();
        ok(['assignments'=>$s->fetchAll(),'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
    }
    if($method==='POST'){
        foreach(['roster_id','employee_id','shift_type_id','shift_date'] as $f){if(empty($input[$f])) err("$f required",422);}
        $s=$pdo->prepare("INSERT INTO roster_shift_assignments (roster_id,employee_id,shift_type_id,shift_date,location,is_overtime,status,notes) VALUES (?,?,?,?,?,?,?,?)");
        $s->execute([(int)$input['roster_id'],(int)$input['employee_id'],(int)$input['shift_type_id'],$input['shift_date'],trim($input['location']??''),(int)($input['is_overtime']??0),$input['status']??'scheduled',trim($input['notes']??'')]);
        ok(['id'=>(int)$pdo->lastInsertId()],'Assignment created',201);
    }
    if($method==='PUT'){
        if(!$id) err('ID required',422);
        $chk=$pdo->prepare("SELECT id FROM roster_shift_assignments WHERE id=?");$chk->execute([$id]);if(!$chk->fetch()) err('Not found',404);
        $s=$pdo->prepare("UPDATE roster_shift_assignments SET shift_type_id=?,shift_date=?,location=?,is_overtime=?,status=?,notes=? WHERE id=?");
        $s->execute([(int)($input['shift_type_id']??0),$input['shift_date']??date('Y-m-d'),trim($input['location']??''),(int)($input['is_overtime']??0),$input['status']??'scheduled',trim($input['notes']??''),$id]);
        ok(null,'Updated');
    }
    if($method==='DELETE'){
        if(!$id) err('ID required',422);
        $pdo->prepare("DELETE FROM roster_shift_assignments WHERE id=?")->execute([$id]);ok(null,'Deleted');
    }
}
else{ err('Invalid entity. Use ?entity=shift-types, rosters, or assignments',400); }

err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
