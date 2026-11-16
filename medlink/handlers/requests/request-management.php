<?php
// Standalone Requests & Requisitions workflow. Procurement is connected only at the
// controlled handoff point; this module owns internal requests and stock fulfillment.
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/controls.php';
require_once __DIR__ . '/../../includes/finance_posting.php';
require_once __DIR__ . '/../../includes/request_schema.php';
rm_schema($pdo);
function rm_json($d=null,$m='Success',$s=200){http_response_code($s);echo json_encode(['success'=>true,'message'=>$m,'data'=>$d]);exit;}
function rm_error($m,$s=400){http_response_code($s);echo json_encode(['success'=>false,'message'=>$m]);exit;}
function rm_uid(){return (int)($_SESSION['user_id']??0);}
function rm_roles(){return $_SESSION['user_data']['role_slugs']??[];}
function rm_can_approve(){return (bool)array_intersect(rm_roles(),rm_approver_roles());}
function rm_is_nurse(){return in_array('nurse',rm_roles(),true)&&!rm_can_approve();}
// Doctors of a department: the people a nurse can pick as supervisor.
function rm_supervisors(PDO $pdo,int $dept){$q=$pdo->prepare("SELECT DISTINCT u.id,TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id AND r.slug='doctor' WHERE u.is_active=1 AND u.department_id=? ORDER BY name");$q->execute([$dept]);return $q->fetchAll();}
function rm_can_fulfill(){return (bool)array_intersect(rm_roles(),['admin','inventory_manager','storekeeper','storekeper','manager']);}
function rm_is_global(){return (bool)array_intersect(rm_roles(),['admin','manager','inventory_manager','storekeeper','storekeper','procurement_officer']);}
// null = hospital-wide role, integer = the only department this user may work with.
function rm_scope_dept(){static $d=false;if($d!==false)return $d;if(rm_is_global())return $d=null;$v=(int)($_SESSION['user_data']['department_id']??0);if(!$v){global $pdo;$q=$pdo->prepare('SELECT department_id FROM users WHERE id=?');$q->execute([rm_uid()]);$v=(int)$q->fetchColumn();}return $d=$v;}
function rm_row(PDO $pdo,int $id){$q=$pdo->prepare('SELECT r.*,CONCAT(COALESCE(u.first_name,"")," ",COALESCE(u.last_name,"")) requester_name,d.name department_name FROM request_management r LEFT JOIN users u ON u.id=r.requested_by LEFT JOIN departments d ON d.id=r.department_id WHERE r.id=?');$q->execute([$id]);$r=$q->fetch();if(!$r)return null;$i=$pdo->prepare('SELECT * FROM request_management_items WHERE request_id=? ORDER BY id');$i->execute([$id]);$r['items']=$i->fetchAll();$a=$pdo->prepare('SELECT x.*,CONCAT(COALESCE(u.first_name,"")," ",COALESCE(u.last_name,"")) user_name FROM request_management_audit x LEFT JOIN users u ON u.id=x.user_id WHERE x.request_id=? ORDER BY x.id');$a->execute([$id]);$r['history']=$a->fetchAll();return $r;}
function rm_audit(PDO $pdo,$id,$action,$from,$to,$comment=''){ $pdo->prepare('INSERT INTO request_management_audit(request_id,action,from_status,to_status,comment,user_id) VALUES(?,?,?,?,?,?)')->execute([$id,$action,$from,$to,$comment,rm_uid()]); }
function rm_no(PDO $pdo){return 'REQM-'.date('Ymd').'-'.str_pad((string)((int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM request_management')->fetchColumn()),4,'0',STR_PAD_LEFT);}
function rm_stock(PDO $pdo,$itemId){if(!$itemId)return 0;$q=$pdo->prepare("SELECT COALESCE(SUM(b.quantity),0) FROM stock_batches b WHERE b.store_id=? AND b.item_id=? AND b.is_active=1 AND b.quantity>0 AND (b.expiry_date IS NULL OR b.expiry_date>=CURDATE())");$q->execute([clinic_store_id($pdo),$itemId]);return (float)$q->fetchColumn();}
// Issue stock for a departmental request: FIFO from the Clinic Store only (Main Store restocks it by transfer), stock movement per batch,
// and one ledger entry per store (Dr departmental consumables / Cr store). Returns issued value.
function rm_issue_item(PDO $pdo,int $itemId,float $qty,int $requestId,int $deptId,string $reqNo): float {
    if($itemId<=0||$qty<=0)return 0;
    $q=$pdo->prepare("SELECT b.id,b.quantity,b.unit_cost,b.store_id FROM stock_batches b WHERE b.store_id=? AND b.item_id=? AND b.is_active=1 AND b.quantity>0 AND (b.expiry_date IS NULL OR b.expiry_date>=CURDATE()) ORDER BY (b.expiry_date IS NULL),b.expiry_date,b.created_at FOR UPDATE");
    $q->execute([clinic_store_id($pdo),$itemId]);
    $left=$qty;$value=0.0;$byStore=[];
    $dest=dept_store_id($pdo,$deptId);   // 0 when the request has no department: old behaviour (plain issue)
    foreach($q->fetchAll() as $b){
        if($left<=0)break;
        $take=min((float)$b['quantity'],$left);
        if($take<=0)continue;
        $pdo->prepare('UPDATE stock_batches SET quantity=quantity-? WHERE id=?')->execute([$take,$b['id']]);
        $v=$take*(float)$b['unit_cost'];$value+=$v;
        $sid=(int)$b['store_id'];$byStore[$sid]=($byStore[$sid]??0)+$v;
        $mv=$pdo->prepare("INSERT INTO stock_movements(item_id,batch_id,from_store_id,to_store_id,movement_type,reference_type,reference_id,quantity,unit_cost,notes,created_by) VALUES(?,?,?,?,?,'request_management',?,?,?,?,?)");
        if($dest){
            // Transfer into the department's own store: same batch number/expiry/cost, so FEFO and cost reports stay true
            $bi=$pdo->prepare('SELECT batch_number,expiry_date,unit_cost,selling_price,received_date FROM stock_batches WHERE id=?');$bi->execute([$b['id']]);$src=$bi->fetch();
            $f=$pdo->prepare('SELECT id FROM stock_batches WHERE item_id=? AND store_id=? AND batch_number <=> ? AND expiry_date <=> ? AND unit_cost=? AND is_active=1 LIMIT 1 FOR UPDATE');
            $f->execute([$itemId,$dest,$src['batch_number'],$src['expiry_date'],$src['unit_cost']]);
            if($did=(int)$f->fetchColumn())$pdo->prepare('UPDATE stock_batches SET quantity=quantity+? WHERE id=?')->execute([$take,$did]);
            else{$pdo->prepare('INSERT INTO stock_batches(item_id,store_id,batch_number,quantity,unit_cost,selling_price,expiry_date,received_date,created_by) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$itemId,$dest,$src['batch_number'],$take,$src['unit_cost'],$src['selling_price'],$src['expiry_date'],$src['received_date'],rm_uid()?:null]);$did=(int)$pdo->lastInsertId();}
            $mv->execute([$itemId,$b['id'],$sid,$dest,'transfer_out',$requestId,$take,(float)$b['unit_cost'],"Transferred on $reqNo",rm_uid()?:null]);
            $mv->execute([$itemId,$did,$sid,$dest,'transfer_in',$requestId,$take,(float)$b['unit_cost'],"Received on $reqNo",rm_uid()?:null]);
        }else $mv->execute([$itemId,$b['id'],$sid,null,'dispensing',$requestId,$take,(float)$b['unit_cost'],"Issued on $reqNo",rm_uid()?:null]);
        $left-=$take;
    }
    foreach($byStore as $store=>$v){
        fin_post_stock($pdo,'request_issue',$requestId.'-'.$itemId.'-'.$store,(int)$store,-$v,fin_acct($pdo,'dept_consumables'),"Stock issued on $reqNo",null,$deptId);
    }
    return $value;
}
$method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??'';$input=json_decode(file_get_contents('php://input'),true)?:[];$uid=rm_uid();
try{
 if($method==='GET'){
  if($action==='departments'){$scope=rm_scope_dept();if($scope!==null){$q=$pdo->prepare('SELECT id,name FROM departments WHERE is_active=1 AND id=?');$q->execute([$scope]);rm_json($q->fetchAll());}rm_json($pdo->query('SELECT id,name FROM departments WHERE is_active=1 ORDER BY name')->fetchAll());}
  if($action==='supervisors'){$d=(int)($_GET['department_id']??0);if($d<=0)rm_json([]);rm_json(rm_supervisors($pdo,$d));}
  if($action==='items')rm_json($pdo->query('SELECT i.id,i.item_code,i.name,i.category_id,ic.name category_name,iu.abbreviation uom_abbr,(SELECT COALESCE(SUM(b.quantity),0) FROM stock_batches b WHERE b.item_id=i.id AND b.is_active=1 AND b.quantity>0 AND b.store_id='.(int)clinic_store_id($pdo).') in_stock FROM items i LEFT JOIN item_categories ic ON ic.id=i.category_id LEFT JOIN item_uoms iu ON iu.id=i.uom_id WHERE i.is_active=1 ORDER BY i.name LIMIT 1000')->fetchAll());
  if($action==='view'){ $r=rm_row($pdo,(int)($_GET['id']??0));if(!$r)rm_error('Request not found',404);$scope=rm_scope_dept();if($scope!==null && (int)$r['department_id']!==$scope && (int)$r['requested_by']!==$uid)rm_error('Request not found',404);rm_json($r); }
  if($action==='dashboard'||$action==='list'){
   $where=[];$args=[];
   if($action==='list'&&!empty($_GET['status'])){$sts=array_values(array_filter(array_map('trim',explode(',',(string)$_GET['status']))));if($sts){$where[]='r.status IN ('.implode(',',array_fill(0,count($sts),'?')).')';foreach($sts as $s)$args[]=$s;}}
   if($action==='list'&&!empty($_GET['department_id'])){$where[]='r.department_id=?';$args[]=(int)$_GET['department_id'];}
   // Visibility: admins/managers and stock roles see the whole hospital; a department head
   // sees their own department; everyone else only sees the requests they raised.
   $roles=rm_roles();
   $broad=(bool)array_intersect($roles,['admin','manager','inventory_manager','storekeeper','storekeper','clinical_director','managing_director']);
   if($action==='list'&&!empty($_GET['mine'])){ $where[]='r.requested_by=?';$args[]=$uid; }
   elseif(!$broad){
     if(in_array('department_head',$roles,true)){
       $dq=$pdo->prepare('SELECT department_id FROM users WHERE id=?');$dq->execute([$uid]);$myDept=(int)$dq->fetchColumn();
       if($myDept){$where[]='r.department_id=?';$args[]=$myDept;}
       else {$where[]='r.requested_by=?';$args[]=$uid;}
     } else {
       $where[]='r.requested_by=?';$args[]=$uid;
     }
   }
   $sql='SELECT r.*,CONCAT(COALESCE(u.first_name,"")," ",COALESCE(u.last_name,"")) requester_name,d.name department_name,(SELECT COUNT(*) FROM request_management_items i WHERE i.request_id=r.id) item_count,(SELECT a.comment FROM request_management_audit a WHERE a.request_id=r.id AND a.action="reject" ORDER BY a.id DESC LIMIT 1) rejection_reason,IF(r.status IN ("submitted","pending_supervisor"),DATEDIFF(NOW(),COALESCE(r.supervisor_approved_at,r.created_at)),NULL) days_pending,TRIM(CONCAT(COALESCE(sv.first_name,"")," ",COALESCE(sv.last_name,""))) supervisor_name FROM request_management r LEFT JOIN users u ON u.id=r.requested_by LEFT JOIN users sv ON sv.id=r.supervisor_id LEFT JOIN departments d ON d.id=r.department_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY r.id DESC LIMIT 500';$q=$pdo->prepare($sql);$q->execute($args);$rows=$q->fetchAll();$sq=$pdo->prepare("SELECT COUNT(*) total,SUM(status IN ('submitted','pending_supervisor')) pending,SUM(status='approved') approved,SUM(status='partially_fulfilled') partial,SUM(status='fulfilled') fulfilled,SUM(status='rejected') rejected,SUM(status='procurement') procurement FROM request_management r".($where?" WHERE ".implode(" AND ",$where):""));$sq->execute($args);$stats=$sq->fetch();rm_json(['requests'=>$rows,'stats'=>$stats,'reminder_days'=>rm_reminder_days($pdo)]);
  }
  if($action==='procurement'){$scope=rm_scope_dept();if($scope!==null){$q=$pdo->prepare("SELECT r.*,COUNT(i.id) item_count FROM request_management r LEFT JOIN request_management_items i ON i.request_id=r.id WHERE r.status='procurement' AND r.department_id=? GROUP BY r.id ORDER BY r.id DESC");$q->execute([$scope]);rm_json($q->fetchAll());}$q=$pdo->query("SELECT r.*,COUNT(i.id) item_count FROM request_management r LEFT JOIN request_management_items i ON i.request_id=r.id WHERE r.status='procurement' GROUP BY r.id ORDER BY r.id DESC");rm_json($q->fetchAll());}
  rm_error('Unknown action',404);
 }
 if($method==='POST'){
  if($action==='create'){$items=$input['items']??[];if(!$items||!is_array($items))rm_error('At least one item is required',422);$scope=rm_scope_dept();if($scope===0)rm_error('Your account is not assigned to a department',403);$dept=$scope!==null?$scope:(!empty($input['department_id'])?(int)$input['department_id']:null);
   $submit=!empty($input['submit']);$status=$submit?'submitted':'draft';$sup=null;
   // Nurse requests need a doctor of the department to approve first (status pending_supervisor, then submitted).
   if(rm_is_nurse()){$sup=(int)($input['supervisor_id']??0)?:null;if($submit||$sup){if(!$dept||!$sup||!array_filter(rm_supervisors($pdo,$dept),fn($d)=>(int)$d['id']===$sup))rm_error('Pick a doctor of your department to approve this request',422);}if($submit)$status='pending_supervisor';}
   $pdo->beginTransaction();$pdo->prepare('INSERT INTO request_management(request_no,requested_by,department_id,purpose,required_date,comments,priority,status,supervisor_id) VALUES(?,?,?,?,?,?,?,?,?)')->execute([rm_no($pdo),$uid,$dept,trim($input['purpose']??''),$input['required_date']??null,trim($input['comments']??''),$input['priority']??'normal',$status,$sup]);$id=(int)$pdo->lastInsertId();$ins=$pdo->prepare('INSERT INTO request_management_items(request_id,item_id,item_name,item_category,quantity_requested,notes) VALUES(?,?,?,?,?,?)');foreach($items as $x){$name=trim($x['item_name']??'');if(!$name)continue;if((float)($x['quantity']??0)<=0)rm_error('Quantity for "'.$name.'" must be greater than zero',422);$ins->execute([$id,!empty($x['item_id'])?(int)$x['item_id']:null,$name,trim($x['item_category']??''),(float)($x['quantity']??0),trim($x['notes']??'')]);}$pdo->commit();rm_audit($pdo,$id,'create',null,$status,$sup?'Supervisor #'.$sup:'');rm_json(rm_row($pdo,$id),'Request created',201);}
  if($action==='submit'){$r=rm_row($pdo,(int)($input['id']??0));if(!$r||$r['status']!=='draft')rm_error('Only drafts can be submitted',409);$to='submitted';if(rm_is_nurse()){$sup=(int)($input['supervisor_id']??$r['supervisor_id']);if(!$sup||!array_filter(rm_supervisors($pdo,(int)$r['department_id']),fn($d)=>(int)$d['id']===$sup))rm_error('Pick a doctor of your department to approve this request',422);$pdo->prepare('UPDATE request_management SET supervisor_id=? WHERE id=?')->execute([$sup,$r['id']]);$to='pending_supervisor';}$pdo->prepare('UPDATE request_management SET status=? WHERE id=?')->execute([$to,$r['id']]);rm_audit($pdo,$r['id'],'submit','draft',$to);rm_json(null,'Request submitted');}
  if($action==='supervisor_approve'){$r=rm_row($pdo,(int)($input['id']??0));if(!$r||$r['status']!=='pending_supervisor')rm_error('This request is not waiting for a supervisor',409);if((int)$r['supervisor_id']!==$uid)rm_error('Only the doctor chosen as supervisor can approve this stage',403);$pdo->prepare("UPDATE request_management SET status='submitted',supervisor_approved_at=NOW() WHERE id=?")->execute([$r['id']]);rm_audit($pdo,$r['id'],'supervisor_approve','pending_supervisor','submitted',trim($input['comment']??''));rm_json(null,'Approved as supervisor; sent to final approval');}
  if($action==='remind'){if(!array_intersect(rm_roles(),['admin','storekeeper','storekeper','inventory_manager']))rm_error('Only the stock officer can send reminders',403);$r=rm_row($pdo,(int)($input['id']??0));if(!$r||!in_array($r['status'],['pending_supervisor','submitted'],true))rm_error('Only pending requests can be reminded',409);if($r['last_reminded_at']&&strtotime($r['last_reminded_at'])>time()-86400)rm_error('A reminder was already sent in the last 24 hours ('.$r['last_reminded_at'].')',429);$stage=$r['status']==='pending_supervisor'?'supervisor':'final approver';$title='Request '.$r['request_no'].' is waiting for approval';$msg=$r['request_no'].' ('.($r['department_name']?:'no department').') has been pending since '.($r['supervisor_approved_at']?:$r['created_at']).'. Reminder to the '.$stage.'.';$n=$pdo->prepare("INSERT INTO notifications(type,title,message,target_user_id,target_role) VALUES('request_reminder',?,?,?,?)");if($stage==='supervisor')$n->execute([$title,$msg,(int)$r['supervisor_id']?:null,null]);else foreach(rm_approver_roles() as $role)$n->execute([$title,$msg,null,$role]);$pdo->prepare('UPDATE request_management SET last_reminded_at=NOW() WHERE id=?')->execute([$r['id']]);rm_audit($pdo,$r['id'],'remind',$r['status'],$r['status'],'Reminder sent to '.$stage);rm_json(null,'Reminder sent to the '.$stage);}
  if($action==='approve'){if(!rm_can_approve())rm_error('You are not authorized to approve requests',403);$r=rm_row($pdo,(int)($input['id']??0));if(!$r||$r['status']!=='submitted')rm_error('Only submitted requests can be approved',409);$self=(int)$r['requested_by']===$uid;$selfAllowed=(bool)array_intersect(rm_roles(),['admin']);if($self&&!$selfAllowed)rm_error('Self-approval is not allowed',403);if(in_array('department_head',rm_roles(),true)&&!array_intersect(rm_roles(),['admin','manager'])){$dq=$pdo->prepare('SELECT department_id FROM users WHERE id=?');$dq->execute([$uid]);if((int)$dq->fetchColumn()!==(int)$r['department_id'])rm_error('You can only approve requests from your own department',403);}$comment=trim($input['comment']??'');if($self)$comment=trim('Self-approved by requester. '.$comment);$pdo->prepare("UPDATE request_management SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?")->execute([$uid,$r['id']]);rm_audit($pdo,$r['id'],'approve','submitted','approved',$comment);rm_json(null,'Request approved'.($self?' (self-approved)':''));}
  if($action==='reject'){$r=rm_row($pdo,(int)($input['id']??0));$bySup=$r&&$r['status']==='pending_supervisor'&&(int)$r['supervisor_id']===$uid;if(!rm_can_approve()&&!$bySup)rm_error('You are not authorized to reject requests',403);if(!$r||($r['status']!=='submitted'&&!$bySup))rm_error('Only submitted requests can be rejected',409);if((int)$r['requested_by']===$uid)rm_error('You cannot reject your own request; cancel it instead',403);if(in_array('department_head',rm_roles(),true)&&!array_intersect(rm_roles(),['admin','manager'])){$dq=$pdo->prepare('SELECT department_id FROM users WHERE id=?');$dq->execute([$uid]);if((int)$dq->fetchColumn()!==(int)$r['department_id'])rm_error('You can only reject requests from your own department',403);}$comment=trim($input['comment']??'');if(!$comment)rm_error('Rejection reason is required',422);$pdo->prepare("UPDATE request_management SET status='rejected' WHERE id=?")->execute([$r['id']]);rm_audit($pdo,$r['id'],'reject',$r['status'],'rejected',$comment);rm_json(null,'Request rejected');}
  if($action==='fulfill'){
    if(!rm_can_fulfill())rm_error('You are not authorized to fulfill requests',403);
    $r=rm_row($pdo,(int)($input['id']??0));$deptStore=!empty($r['department_id']);
    if(!$r||!in_array($r['status'],['approved','partially_fulfilled'],true))rm_error('Request is not ready for fulfillment',409);
    $pdo->beginTransaction();
    // Lock the request and re-read it: two people clicking Transfer at once must not move the stock twice
    $lk=$pdo->prepare('SELECT status FROM request_management WHERE id=? FOR UPDATE');$lk->execute([$r['id']]);
    $r=rm_row($pdo,(int)$r['id']);
    if(!in_array($r['status'],['approved','partially_fulfilled'],true)){$pdo->rollBack();rm_error('Request is not ready for fulfillment',409);}
    $issued=0;$remaining=0;$value=0;
    foreach($r['items'] as $x){
      $itemId=(int)$x['item_id'];$want=(float)$x['quantity_requested'];$already=(float)($x['quantity_issued']??0);
      $outstanding=max(0,$want-$already);
      $available=$itemId?rm_stock($pdo,$itemId):0;
      $take=$itemId?min($available,$outstanding):0;
      if(isset($input['items'][$x['id']]))$take=min($take,max(0,(float)$input['items'][$x['id']]));
      if($take>0)$value+=rm_issue_item($pdo,$itemId,$take,(int)$r['id'],(int)($r['department_id']??0),$r['request_no']);
      $totalIssued=$already+$take;
      $short=max(0,$want-$totalIssued);
      $pdo->prepare('UPDATE request_management_items SET quantity_available=?,quantity_issued=?,shortage=? WHERE id=?')->execute([$available,$totalIssued,$short,$x['id']]);
      $issued+=$take;$remaining+=$short;
    }
    $status=$remaining>0?'partially_fulfilled':'fulfilled';
    $pdo->prepare('UPDATE request_management SET status=?,fulfilled_by=?,fulfilled_at=NOW(),stock_checked_by=?,stock_checked_at=NOW() WHERE id=?')->execute([$status,$uid,$uid,$r['id']]);
    $pdo->commit();
    rm_audit($pdo,$r['id'],'fulfill',$r['status'],$status,'Issued '.$issued.' / shortage '.$remaining.' / value '.number_format($value));
    rm_json(['issued'=>$issued,'shortage'=>$remaining,'value'=>$value],($deptStore?'Transferred ':'Issued ').$issued.' unit(s)'.($deptStore?' to the department store':'').'; shortage '.$remaining.'. Value '.number_format($value).' RWF');
  }
  if($action==='create_requisition'){if(!rm_can_fulfill())rm_error('Only inventory/procurement users can create requisitions',403);$r=rm_row($pdo,(int)($input['id']??0));if(!$r||!in_array($r['status'],['approved','partially_fulfilled'],true))rm_error('Request is not ready for procurement',409);$items=array_values(array_filter($r['items'],fn($x)=>(float)$x['shortage']>0));if(!$items)rm_error('No shortage requires procurement',422);$dupIds=[];foreach($items as $x){if(!empty($x['item_id']))$dupIds[(int)$x['item_id']]=1;}if($dupIds){$ph=implode(',',array_fill(0,count($dupIds),'?'));$dq=$pdo->prepare("SELECT pr.pr_number FROM purchase_requests pr JOIN purchase_request_items pri ON pri.pr_id=pr.id WHERE pr.department_id <=> ? AND pr.status IN ('pending','approved','ordered','partially_received') AND pr.request_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND pri.item_id IN ($ph) LIMIT 1");$dq->execute(array_merge([(int)$r['department_id']],array_keys($dupIds)));if($dup=$dq->fetchColumn())rm_error("Duplicate purchase request: $dup already covers this item and is still open (raised in the last 7 days).",409);}$pr='PRM-'.date('Ymd').'-'.str_pad((string)((int)$pdo->query('SELECT COALESCE(MAX(id),0)+1 FROM purchase_requests')->fetchColumn()),4,'0',STR_PAD_LEFT);$pdo->beginTransaction();$pdo->prepare('INSERT INTO purchase_requests(pr_number,department_id,requested_by,request_date,required_by,priority,justification,status,total_estimated,notes) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$pr,$r['department_id'],$r['requested_by'],date('Y-m-d'),$r['required_date'],$r['priority'],$r['purpose'],'pending',0,'From '.$r['request_no']]);$pid=(int)$pdo->lastInsertId();$ins=$pdo->prepare('INSERT INTO purchase_request_items(pr_id,item_id,item_description,quantity,uom_id,estimated_unit_price,estimated_total,notes) VALUES(?,?,?,?,?,?,?,?)');
 $total=0;foreach($items as $x){$itemId=(int)$x['item_id'];$price=0;if($itemId){$s=$pdo->prepare('SELECT unit_price FROM items WHERE id=?');$s->execute([$itemId]);$price=(float)$s->fetchColumn();}else{$s=$pdo->prepare('SELECT id,unit_price FROM items WHERE name=? AND is_active=1 LIMIT 1');$s->execute([$x['item_name']]);if($row=$s->fetch()){$itemId=(int)$row['id'];$price=(float)$row['unit_price'];}}$qty=(float)$x['shortage'];$line=$qty*$price;$total+=$line;$ins->execute([$pid,$itemId?:null,$x['item_name'],$qty,null,$price,$line,$x['notes']]);}
 $pdo->prepare('UPDATE purchase_requests SET total_estimated=? WHERE id=?')->execute([$total,$pid]);$pdo->prepare('UPDATE request_management SET status="procurement",requisition_id=? WHERE id=?')->execute([$pid,$r['id']]);$pdo->commit();rm_audit($pdo,$r['id'],'procurement',$r['status'],'procurement','Handed to Procurement as '.$pr);rm_json(['id'=>$pid,'requisition_no'=>$pr],'Purchase request '.$pr.' created in Procurement',201);}
  if($action==='cancel'){ $r=rm_row($pdo,(int)($input['id']??0));if(!$r||in_array($r['status'],['fulfilled','cancelled'],true))rm_error('Request cannot be cancelled',409);$scope=rm_scope_dept();$canCancel=(int)$r['requested_by']===$uid||in_array('admin',rm_roles(),true)||($scope!==null&&(int)$r['department_id']===$scope&&in_array('department_head',rm_roles(),true));if(!$canCancel)rm_error('You cannot cancel this request',403);$pdo->prepare("UPDATE request_management SET status='cancelled' WHERE id=?")->execute([$r['id']]);rm_audit($pdo,$r['id'],'cancel',$r['status'],'cancelled');rm_json(null,'Request cancelled');}
  rm_error('Unknown action',404);
 }
 rm_error('Method not allowed',405);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());rm_error($e->getMessage(),422);}
