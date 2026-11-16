<?php
// handlers/hr/performance.php
require_once __DIR__ . '/../../includes/database.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];
$id=isset($_GET['id'])?(int)$_GET['id']:null;

function calcRating(float $score):string{
    if($score>=4.5) return 'excellent';
    if($score>=3.5) return 'good';
    if($score>=2.5) return 'satisfactory';
    if($score>=1.5) return 'needs-improvement';
    return 'unsatisfactory';
}
function fetchRev(PDO $pdo,int $id):?array{
    $s=$pdo->prepare("SELECT pr.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name FROM performance_reviews pr JOIN employees e ON e.id=pr.employee_id LEFT JOIN departments d ON d.id=e.department_id WHERE pr.id=?");
    $s->execute([$id]); $r=$s->fetch(); return is_array($r)?$r:null;
}

try {
if($method==='GET'){
    if($id){ $r=fetchRev($pdo,$id); if(!$r) err('Not found',404); ok($r); }
    $empId=isset($_GET['employee_id'])?(int)$_GET['employee_id']:0;
    $period=$_GET['period']??''; $status=$_GET['status']??'';
    $page=max(1,(int)($_GET['page']??1)); $limit=min(100,max(1,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
    $w=[]; $p=[];
    if($empId>0){$w[]="pr.employee_id=?";$p[]=$empId;}
    if($period!==''){$w[]="pr.review_period=?";$p[]=$period;}
    if($status!==''){$w[]="pr.status=?";$p[]=$status;}
    $ws=$w?'WHERE '.implode(' AND ',$w):'';
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM performance_reviews pr $ws"); $cnt->execute($p); $total=(int)$cnt->fetchColumn();
    $s=$pdo->prepare("SELECT pr.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name FROM performance_reviews pr JOIN employees e ON e.id=pr.employee_id LEFT JOIN departments d ON d.id=e.department_id $ws ORDER BY pr.created_at DESC LIMIT $limit OFFSET $offset");
    $s->execute($p); $rows=$s->fetchAll();
    $ss=$pdo->prepare("SELECT COUNT(*) AS total, ROUND(AVG(pr.overall_score),2) AS avg_score, SUM(CASE WHEN pr.rating='excellent' THEN 1 ELSE 0 END) AS excellent, SUM(CASE WHEN pr.rating='good' THEN 1 ELSE 0 END) AS good, SUM(CASE WHEN pr.rating='needs-improvement' THEN 1 ELSE 0 END) AS needs_improvement FROM performance_reviews pr $ws");
    $ss->execute($p); $stats=$ss->fetch();
    ok(['reviews'=>$rows,'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

if($method==='POST'){
    foreach(['employee_id','review_period'] as $f){ if(empty($input[$f])) err("$f required",422); }
    $scores=array_filter([(int)($input['punctuality_score']??0),(int)($input['quality_score']??0),(int)($input['teamwork_score']??0),(int)($input['communication_score']??0),(int)($input['initiative_score']??0)]);
    $overall=count($scores)?round(array_sum($scores)/count($scores),1):null;
    $rating=$overall?calcRating($overall):null;
    $s=$pdo->prepare("INSERT INTO performance_reviews (employee_id,review_period,reviewer_name,punctuality_score,quality_score,teamwork_score,communication_score,initiative_score,overall_score,rating,strengths,improvements,goals,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->execute([(int)$input['employee_id'],trim($input['review_period']),trim($input['reviewer_name']??''),(int)($input['punctuality_score']??0),(int)($input['quality_score']??0),(int)($input['teamwork_score']??0),(int)($input['communication_score']??0),(int)($input['initiative_score']??0),$overall,$rating,trim($input['strengths']??''),trim($input['improvements']??''),trim($input['goals']??''),$input['status']??'draft']);
    $newId=(int)$pdo->lastInsertId(); ok(fetchRev($pdo,$newId),'Review saved',201);
}

if($method==='PUT'){
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id FROM performance_reviews WHERE id=?"); $chk->execute([$id]);
    if(!$chk->fetch()) err('Not found',404);
    $scores=array_filter([(int)($input['punctuality_score']??0),(int)($input['quality_score']??0),(int)($input['teamwork_score']??0),(int)($input['communication_score']??0),(int)($input['initiative_score']??0)]);
    $overall=count($scores)?round(array_sum($scores)/count($scores),1):null;
    $rating=$overall?calcRating($overall):null;
    $s=$pdo->prepare("UPDATE performance_reviews SET reviewer_name=?,punctuality_score=?,quality_score=?,teamwork_score=?,communication_score=?,initiative_score=?,overall_score=?,rating=?,strengths=?,improvements=?,goals=?,status=? WHERE id=?");
    $s->execute([trim($input['reviewer_name']??''),(int)($input['punctuality_score']??0),(int)($input['quality_score']??0),(int)($input['teamwork_score']??0),(int)($input['communication_score']??0),(int)($input['initiative_score']??0),$overall,$rating,trim($input['strengths']??''),trim($input['improvements']??''),trim($input['goals']??''),$input['status']??'draft',$id]);
    ok(fetchRev($pdo,$id),'Updated');
}

if($method==='DELETE'){
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id FROM performance_reviews WHERE id=?"); $chk->execute([$id]);
    if(!$chk->fetch()) err('Not found',404);
    $pdo->prepare("DELETE FROM performance_reviews WHERE id=?")->execute([$id]); ok(null,'Deleted');
}
err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
