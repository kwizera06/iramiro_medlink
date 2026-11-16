<?php
// handlers/hr/licenses.php
require_once __DIR__ . '/../../includes/database.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];

// Support _method override for FormData (PUT via POST)
if($method==='POST' && isset($_POST['_method']) && strtoupper($_POST['_method'])==='PUT'){
    $method='PUT';
}

// Get input: JSON body or POST data
if($method==='GET' || $method==='DELETE'){
    $input=[];
}else{
    $contentType=$_SERVER['CONTENT_TYPE']??'';
    if(strpos($contentType,'application/json')!==false){
        $input=json_decode(file_get_contents('php://input'),true)??[];
    }else{
        $input=$_POST;
    }
}

$id=isset($_GET['id'])?(int)$_GET['id']:null;
$action=isset($_GET['action'])?$_GET['action']:null;

// Upload directory
$uploadDir=__DIR__.'/../../uploads/licenses/';

function fetchLic(PDO $pdo,int $id):?array{
    $s=$pdo->prepare("SELECT el.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name FROM employee_licenses el JOIN employees e ON e.id=el.employee_id LEFT JOIN departments d ON d.id=e.department_id WHERE el.id=?");
    $s->execute([$id]); $r=$s->fetch(); return is_array($r)?$r:null;
}

function saveVersion(PDO $pdo, int $licenseId, string $changeReason='', ?int $changedBy=null):void{
    $lic=$pdo->prepare("SELECT * FROM employee_licenses WHERE id=?");
    $lic->execute([$licenseId]); $data=$lic->fetch();
    if(!$data) return;

    $nextVersion = (int)$data['current_version'];
    
    $s=$pdo->prepare("INSERT INTO employee_license_versions (license_id, version_number, employee_id, license_type, license_name, license_number, issuing_body, issue_date, expiry_date, status, reminder_days, notes, change_reason, changed_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $s->execute([
        $licenseId,
        $nextVersion,
        $data['employee_id'],
        $data['license_type'],
        $data['license_name'],
        $data['license_number'],
        $data['issuing_body'],
        $data['issue_date'],
        $data['expiry_date'],
        $data['status'],
        $data['reminder_days'],
        $data['notes'],
        $changeReason,
        $changedBy
    ]);
}

function handleFileUpload():?string{
    global $uploadDir;
    if(!isset($_FILES['license_document']) || $_FILES['license_document']['error']===UPLOAD_ERR_NO_FILE){
        return null;
    }
    $file=$_FILES['license_document'];
    if($file['error']!==UPLOAD_ERR_OK){
        err('File upload error: code '.$file['error'],422);
    }
    // Validate size (5MB max)
    if($file['size']>5*1024*1024){
        err('File too large. Maximum 5MB allowed.',422);
    }
    // Validate type
    $allowed=['application/pdf','image/jpeg','image/png','image/jpg'];
    $finfo=finfo_open(FILEINFO_MIME_TYPE);
    $mimeType=finfo_file($finfo,$file['tmp_name']);
    if(!in_array($mimeType,$allowed)){
        err('Invalid file type. Only PDF, JPG, and PNG are allowed.',422);
    }
    // Create directory if not exists
    if(!is_dir($uploadDir)){
        mkdir($uploadDir,0755,true);
    }
    // Generate unique filename
    $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
    $filename='lic_'.time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
    $destPath=$uploadDir.$filename;
    if(!move_uploaded_file($file['tmp_name'],$destPath)){
        err('Failed to save uploaded file.',500);
    }
    return 'uploads/licenses/'.$filename;
}

try {
if($method==='GET'){
    // Get version history for a license
    if($action==='versions' && $id){
        $s=$pdo->prepare("SELECT v.*, e.first_name AS changed_by_name FROM employee_license_versions v LEFT JOIN employees e ON e.id=v.changed_by WHERE v.license_id=? ORDER BY v.version_number DESC");
        $s->execute([$id]);
        $versions=$s->fetchAll();
        ok(['versions'=>$versions,'license_id'=>$id]);
    }

    if($id){ $r=fetchLic($pdo,$id); if(!$r) err('Not found',404); ok($r); }
    $empId=isset($_GET['employee_id'])?(int)$_GET['employee_id']:0;
    $status=$_GET['status']??''; $expiring=$_GET['expiring']??'';
    $page=max(1,(int)($_GET['page']??1)); $limit=min(100,max(1,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
    $w=[]; $p=[];
    if($empId>0){$w[]="el.employee_id=?";$p[]=$empId;}
    if($status!==''){$w[]="el.status=?";$p[]=$status;}
    if($expiring!==''){$w[]="el.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)";$p[]=(int)$expiring;}
    $ws=$w?'WHERE '.implode(' AND ',$w):'';
    $cnt=$pdo->prepare("SELECT COUNT(*) FROM employee_licenses el $ws"); $cnt->execute($p); $total=(int)$cnt->fetchColumn();
    $s=$pdo->prepare("SELECT el.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name, DATEDIFF(el.expiry_date, CURDATE()) AS days_to_expiry FROM employee_licenses el JOIN employees e ON e.id=el.employee_id LEFT JOIN departments d ON d.id=e.department_id $ws ORDER BY el.expiry_date ASC LIMIT $limit OFFSET $offset");
    $s->execute($p); $rows=$s->fetchAll();
    // Stats
    $ss=$pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) AS expired, SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring_soon FROM employee_licenses");
    $stats=$ss->fetch();
    ok(['licenses'=>$rows,'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
}

if($method==='POST'){
    foreach(['employee_id','license_type','license_name'] as $f){ if(empty($input[$f])) err("$f required",422); }
    
    // Handle file upload
    $docPath=handleFileUpload();
    
    $s=$pdo->prepare("INSERT INTO employee_licenses (employee_id,license_type,license_name,license_number,issuing_body,issue_date,expiry_date,status,reminder_days,document_path,notes,current_version) VALUES (?,?,?,?,?,?,?,?,?,?,?,1)");
    $s->execute([
        (int)$input['employee_id'],
        trim($input['license_type']),
        trim($input['license_name']),
        trim($input['license_number']??''),
        trim($input['issuing_body']??''),
        !empty($input['issue_date'])?$input['issue_date']:null,
        !empty($input['expiry_date'])?$input['expiry_date']:null,
        $input['status']??'active',
        (int)($input['reminder_days']??30),
        $docPath,
        trim($input['notes']??'')
    ]);
    $newId=(int)$pdo->lastInsertId();
    
    // Save initial version
    saveVersion($pdo, $newId, 'Initial creation', null);
    
    ok(fetchLic($pdo,$newId),'License added',201);
}

if($method==='PUT'){
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id, current_version, document_path FROM employee_licenses WHERE id=?"); $chk->execute([$id]);
    $existing=$chk->fetch();
    if(!$existing) err('Not found',404);
    
    $newVersion = (int)$existing['current_version'] + 1;
    $changeReason = trim($input['change_reason'] ?? 'Updated');
    
    // Handle file upload (keep existing if no new file)
    $docPath=handleFileUpload();
    if($docPath===null){
        $docPath=$existing['document_path']; // keep existing
    }else{
        // Delete old file if replacing
        if($existing['document_path']){
            $oldFile=__DIR__.'/../../'.$existing['document_path'];
            if(file_exists($oldFile)) unlink($oldFile);
        }
    }
    
    $s=$pdo->prepare("UPDATE employee_licenses SET license_type=?,license_name=?,license_number=?,issuing_body=?,issue_date=?,expiry_date=?,status=?,reminder_days=?,document_path=?,notes=?,current_version=? WHERE id=?");
    $s->execute([
        trim($input['license_type']??''),
        trim($input['license_name']??''),
        trim($input['license_number']??''),
        trim($input['issuing_body']??''),
        !empty($input['issue_date'])?$input['issue_date']:null,
        !empty($input['expiry_date'])?$input['expiry_date']:null,
        $input['status']??'active',
        (int)($input['reminder_days']??30),
        $docPath,
        trim($input['notes']??''),
        $newVersion,
        $id
    ]);
    
    // Save this new version
    saveVersion($pdo, $id, $changeReason, null);
    
    ok(fetchLic($pdo,$id),'Updated');
}

if($method==='DELETE'){
    if(!$id) err('ID required',422);
    $chk=$pdo->prepare("SELECT id, document_path FROM employee_licenses WHERE id=?"); $chk->execute([$id]);
    $existing=$chk->fetch();
    if(!$existing) err('Not found',404);
    // Delete file
    if($existing['document_path']){
        $filePath=__DIR__.'/../../'.$existing['document_path'];
        if(file_exists($filePath)) unlink($filePath);
    }
    $pdo->prepare("DELETE FROM employee_licenses WHERE id=?")->execute([$id]); ok(null,'Deleted');
}
err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
