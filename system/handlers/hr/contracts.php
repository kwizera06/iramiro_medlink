<?php
// handlers/hr/contracts.php — Employee Contracts & Documents
require_once __DIR__ . '/../../includes/database.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
// Support _method override for FormData
if($method==='POST' && isset($_POST['_method']) && strtoupper($_POST['_method'])==='PUT'){
    $method='PUT';
}
$contentType=$_SERVER['CONTENT_TYPE']??'';
if($method==='GET' || $method==='DELETE'){
    $input=[];
}else{
    $input=(strpos($contentType,'application/json')!==false)?json_decode(file_get_contents('php://input'),true)??[]:$_POST;
}

$id=isset($_GET['id'])?(int)$_GET['id']:null;
$entity=$_GET['entity']??'contracts'; // 'contracts' or 'documents'
$action=$_GET['action']??null;

$uploadDirContracts=__DIR__.'/../../uploads/contracts/';
$uploadDirDocuments=__DIR__.'/../../uploads/employee_documents/';

// ===== FILE UPLOAD HELPER =====
function handleUpload(string $fieldName, string $destDir, int $maxMB=10):?array{
    if(!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error']===UPLOAD_ERR_NO_FILE) return null;
    $file=$_FILES[$fieldName];
    if($file['error']!==UPLOAD_ERR_OK) err('File upload error: code '.$file['error'],422);
    if($file['size']>$maxMB*1024*1024) err("File too large. Maximum {$maxMB}MB.",422);
    $allowed=['application/pdf','image/jpeg','image/png','image/jpg',
              'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $finfo=finfo_open(FILEINFO_MIME_TYPE);
    $mime=finfo_file($finfo,$file['tmp_name']);
    if(!in_array($mime,$allowed)) err('Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.',422);
    if(!is_dir($destDir)) mkdir($destDir,0755,true);
    $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
    $filename=time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
    if(!move_uploaded_file($file['tmp_name'],$destDir.$filename)) err('Failed to save file.',500);
    return ['path'=>$filename,'size'=>$file['size'],'mime'=>$mime,'original'=>$file['name']];
}

// ===== CONTRACT VERSION =====
function saveContractVersion(PDO $pdo, int $contractId, string $reason=''):void{
    $r=$pdo->prepare("SELECT * FROM employee_contracts WHERE id=?");
    $r->execute([$contractId]); $d=$r->fetch(); if(!$d) return;
    $pdo->prepare("INSERT INTO employee_contract_versions (contract_id,version_number,contract_number,contract_type,start_date,end_date,job_title,gross_salary,is_active,contract_document,change_reason) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([$contractId,(int)$d['current_version'],$d['contract_number'],$d['contract_type'],$d['start_date'],$d['end_date'],$d['job_title'],$d['gross_salary'],$d['is_active'],$d['contract_document'],$reason]);
}

// ===== DOCUMENT VERSION =====
function saveDocumentVersion(PDO $pdo, int $docId, string $reason=''):void{
    $r=$pdo->prepare("SELECT * FROM employee_documents WHERE id=?");
    $r->execute([$docId]); $d=$r->fetch(); if(!$d) return;
    $pdo->prepare("INSERT INTO employee_document_versions (document_id,version_number,document_name,file_path,file_size,mime_type,change_reason) VALUES (?,?,?,?,?,?,?)")
    ->execute([$docId,(int)$d['current_version'],$d['document_name'],$d['file_path'],$d['file_size'],$d['mime_type'],$reason]);
}

try {

// ============================================================
// CONTRACTS ENTITY
// ============================================================
if($entity==='contracts'){

    if($method==='GET'){
        // Version history
        if($action==='versions' && $id){
            $s=$pdo->prepare("SELECT * FROM employee_contract_versions WHERE contract_id=? ORDER BY version_number DESC");
            $s->execute([$id]);
            ok(['versions'=>$s->fetchAll(),'contract_id'=>$id]);
        }
        // Single
        if($id){
            $s=$pdo->prepare("SELECT c.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name FROM employee_contracts c JOIN employees e ON e.id=c.employee_id LEFT JOIN departments d ON d.id=c.department_id WHERE c.id=?");
            $s->execute([$id]); $r=$s->fetch();
            if(!$r) err('Not found',404);
            ok($r);
        }
        // List
        $page=max(1,(int)($_GET['page']??1)); $limit=min(100,max(1,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
        $w=[]; $p=[];
        if(!empty($_GET['employee_id'])){$w[]="c.employee_id=?";$p[]=(int)$_GET['employee_id'];}
        if(!empty($_GET['contract_type'])){$w[]="c.contract_type=?";$p[]=$_GET['contract_type'];}
        if(isset($_GET['is_active']) && $_GET['is_active']!==''){$w[]="c.is_active=?";$p[]=(int)$_GET['is_active'];}
        $ws=$w?'WHERE '.implode(' AND ',$w):'';
        $cnt=$pdo->prepare("SELECT COUNT(*) FROM employee_contracts c $ws"); $cnt->execute($p); $total=(int)$cnt->fetchColumn();
        $s=$pdo->prepare("SELECT c.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name FROM employee_contracts c JOIN employees e ON e.id=c.employee_id LEFT JOIN departments d ON d.id=c.department_id $ws ORDER BY c.created_at DESC LIMIT $limit OFFSET $offset");
        $s->execute($p);
        $stats=$pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END) AS inactive, SUM(CASE WHEN end_date IS NOT NULL AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring_soon FROM employee_contracts")->fetch();
        ok(['contracts'=>$s->fetchAll(),'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
    }

    if($method==='POST'){
        foreach(['employee_id','contract_type','start_date','job_title'] as $f){ if(empty($input[$f])) err("$f required",422); }
        // Auto-generate contract number
        $cnt=$pdo->query("SELECT COUNT(*) FROM employee_contracts")->fetchColumn();
        $contractNo='CTR-'.str_pad($cnt+1,5,'0',STR_PAD_LEFT);
        // File upload
        $upload=handleUpload('contract_file',$uploadDirContracts);
        $docPath=$upload?'uploads/contracts/'.$upload['path']:null;

        $s=$pdo->prepare("INSERT INTO employee_contracts (employee_id,contract_number,contract_type,start_date,end_date,probation_end_date,job_title,job_grade,department_id,gross_salary,housing_allowance,transport_allowance,work_location,working_hours,is_active,contract_document,notes,current_version) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)");
        $s->execute([
            (int)$input['employee_id'], $contractNo, $input['contract_type'],
            $input['start_date'], !empty($input['end_date'])?$input['end_date']:null,
            !empty($input['probation_end_date'])?$input['probation_end_date']:null,
            trim($input['job_title']), trim($input['job_grade']??''),
            !empty($input['department_id'])?(int)$input['department_id']:null,
            (float)($input['gross_salary']??0), (float)($input['housing_allowance']??0),
            (float)($input['transport_allowance']??0), trim($input['work_location']??''),
            trim($input['working_hours']??''), (int)($input['is_active']??1),
            $docPath, trim($input['notes']??'')
        ]);
        $newId=(int)$pdo->lastInsertId();
        saveContractVersion($pdo,$newId,'Initial contract');
        $s2=$pdo->prepare("SELECT c.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name FROM employee_contracts c JOIN employees e ON e.id=c.employee_id LEFT JOIN departments d ON d.id=c.department_id WHERE c.id=?");
        $s2->execute([$newId]);
        ok($s2->fetch(),'Contract created',201);
    }

    if($method==='PUT'){
        if(!$id) err('ID required',422);
        $chk=$pdo->prepare("SELECT id,current_version,contract_document FROM employee_contracts WHERE id=?");
        $chk->execute([$id]); $ex=$chk->fetch(); if(!$ex) err('Not found',404);

        $upload=handleUpload('contract_file',$uploadDirContracts);
        $docPath=$upload?'uploads/contracts/'.$upload['path']:$ex['contract_document'];
        if($upload && $ex['contract_document']){
            $old=__DIR__.'/../../'.$ex['contract_document'];
            if(file_exists($old)) unlink($old);
        }
        $newVer=(int)$ex['current_version']+1;
        $reason=trim($input['change_reason']??'Updated');

        $s=$pdo->prepare("UPDATE employee_contracts SET contract_type=?,start_date=?,end_date=?,probation_end_date=?,job_title=?,job_grade=?,department_id=?,gross_salary=?,housing_allowance=?,transport_allowance=?,work_location=?,working_hours=?,is_active=?,contract_document=?,notes=?,current_version=? WHERE id=?");
        $s->execute([
            $input['contract_type']??'permanent',
            $input['start_date']??date('Y-m-d'),
            !empty($input['end_date'])?$input['end_date']:null,
            !empty($input['probation_end_date'])?$input['probation_end_date']:null,
            trim($input['job_title']??''), trim($input['job_grade']??''),
            !empty($input['department_id'])?(int)$input['department_id']:null,
            (float)($input['gross_salary']??0), (float)($input['housing_allowance']??0),
            (float)($input['transport_allowance']??0), trim($input['work_location']??''),
            trim($input['working_hours']??''), (int)($input['is_active']??1),
            $docPath, trim($input['notes']??''), $newVer, $id
        ]);
        saveContractVersion($pdo,$id,$reason);
        $s2=$pdo->prepare("SELECT c.*,e.first_name,e.last_name,e.employee_no,d.name AS dept_name FROM employee_contracts c JOIN employees e ON e.id=c.employee_id LEFT JOIN departments d ON d.id=c.department_id WHERE c.id=?");
        $s2->execute([$id]);
        ok($s2->fetch(),'Contract updated');
    }

    if($method==='DELETE'){
        if(!$id) err('ID required',422);
        $chk=$pdo->prepare("SELECT id,contract_document FROM employee_contracts WHERE id=?");
        $chk->execute([$id]); $ex=$chk->fetch(); if(!$ex) err('Not found',404);
        if($ex['contract_document']){
            $f=__DIR__.'/../../'.$ex['contract_document'];
            if(file_exists($f)) unlink($f);
        }
        $pdo->prepare("DELETE FROM employee_contracts WHERE id=?")->execute([$id]);
        ok(null,'Contract deleted');
    }
}

// ============================================================
// DOCUMENTS ENTITY (Diplomas, Certificates, etc.)
// ============================================================
elseif($entity==='documents'){

    if($method==='GET'){
        // Version history
        if($action==='versions' && $id){
            $s=$pdo->prepare("SELECT * FROM employee_document_versions WHERE document_id=? ORDER BY version_number DESC");
            $s->execute([$id]);
            ok(['versions'=>$s->fetchAll(),'document_id'=>$id]);
        }
        // Single
        if($id){
            $s=$pdo->prepare("SELECT d.*,e.first_name,e.last_name,e.employee_no FROM employee_documents d JOIN employees e ON e.id=d.employee_id WHERE d.id=?");
            $s->execute([$id]); $r=$s->fetch();
            if(!$r) err('Not found',404);
            ok($r);
        }
        // List
        $page=max(1,(int)($_GET['page']??1)); $limit=min(100,max(1,(int)($_GET['limit']??20))); $offset=($page-1)*$limit;
        $w=[]; $p=[];
        if(!empty($_GET['employee_id'])){$w[]="d.employee_id=?";$p[]=(int)$_GET['employee_id'];}
        if(!empty($_GET['document_type'])){$w[]="d.document_type=?";$p[]=$_GET['document_type'];}
        $ws=$w?'WHERE '.implode(' AND ',$w):'';
        $cnt=$pdo->prepare("SELECT COUNT(*) FROM employee_documents d $ws"); $cnt->execute($p); $total=(int)$cnt->fetchColumn();
        $s=$pdo->prepare("SELECT d.*,e.first_name,e.last_name,e.employee_no FROM employee_documents d JOIN employees e ON e.id=d.employee_id $ws ORDER BY d.created_at DESC LIMIT $limit OFFSET $offset");
        $s->execute($p);
        $stats=$pdo->query("SELECT COUNT(*) AS total, SUM(CASE WHEN document_type='Diploma' THEN 1 ELSE 0 END) AS diplomas, SUM(CASE WHEN document_type='Certificate' THEN 1 ELSE 0 END) AS certificates, SUM(CASE WHEN document_type NOT IN ('Diploma','Certificate') THEN 1 ELSE 0 END) AS other FROM employee_documents")->fetch();
        ok(['documents'=>$s->fetchAll(),'stats'=>$stats,'pagination'=>['total'=>$total,'page'=>$page,'limit'=>$limit,'total_pages'=>(int)ceil($total/max($limit,1))]]);
    }

    if($method==='POST'){
        foreach(['employee_id','document_type','document_name'] as $f){ if(empty($input[$f])) err("$f required",422); }
        $upload=handleUpload('document_file',$uploadDirDocuments);
        if(!$upload) err('Document file is required',422);
        $filePath='uploads/employee_documents/'.$upload['path'];

        $s=$pdo->prepare("INSERT INTO employee_documents (employee_id,document_type,document_name,file_path,file_size,mime_type,notes,current_version) VALUES (?,?,?,?,?,?,?,1)");
        $s->execute([
            (int)$input['employee_id'], trim($input['document_type']),
            trim($input['document_name']), $filePath, $upload['size'],
            $upload['mime'], trim($input['notes']??'')
        ]);
        $newId=(int)$pdo->lastInsertId();
        saveDocumentVersion($pdo,$newId,'Initial upload');
        $s2=$pdo->prepare("SELECT d.*,e.first_name,e.last_name,e.employee_no FROM employee_documents d JOIN employees e ON e.id=d.employee_id WHERE d.id=?");
        $s2->execute([$newId]);
        ok($s2->fetch(),'Document uploaded',201);
    }

    if($method==='PUT'){
        if(!$id) err('ID required',422);
        $chk=$pdo->prepare("SELECT id,current_version,file_path FROM employee_documents WHERE id=?");
        $chk->execute([$id]); $ex=$chk->fetch(); if(!$ex) err('Not found',404);

        $upload=handleUpload('document_file',$uploadDirDocuments);
        $filePath=$upload?'uploads/employee_documents/'.$upload['path']:$ex['file_path'];
        $fileSize=$upload?$upload['size']:null;
        $mime=$upload?$upload['mime']:null;
        if($upload && $ex['file_path']){
            $old=__DIR__.'/../../'.$ex['file_path'];
            if(file_exists($old)) unlink($old);
        }
        $newVer=(int)$ex['current_version']+1;
        $reason=trim($input['change_reason']??'Updated');

        if($upload){
            $s=$pdo->prepare("UPDATE employee_documents SET document_type=?,document_name=?,file_path=?,file_size=?,mime_type=?,notes=?,current_version=? WHERE id=?");
            $s->execute([trim($input['document_type']??''),trim($input['document_name']??''),$filePath,$fileSize,$mime,trim($input['notes']??''),$newVer,$id]);
        }else{
            $s=$pdo->prepare("UPDATE employee_documents SET document_type=?,document_name=?,notes=?,current_version=? WHERE id=?");
            $s->execute([trim($input['document_type']??''),trim($input['document_name']??''),trim($input['notes']??''),$newVer,$id]);
        }
        saveDocumentVersion($pdo,$id,$reason);
        $s2=$pdo->prepare("SELECT d.*,e.first_name,e.last_name,e.employee_no FROM employee_documents d JOIN employees e ON e.id=d.employee_id WHERE d.id=?");
        $s2->execute([$id]);
        ok($s2->fetch(),'Document updated');
    }

    if($method==='DELETE'){
        if(!$id) err('ID required',422);
        $chk=$pdo->prepare("SELECT id,file_path FROM employee_documents WHERE id=?");
        $chk->execute([$id]); $ex=$chk->fetch(); if(!$ex) err('Not found',404);
        if($ex['file_path']){
            $f=__DIR__.'/../../'.$ex['file_path'];
            if(file_exists($f)) unlink($f);
        }
        $pdo->prepare("DELETE FROM employee_documents WHERE id=?")->execute([$id]);
        ok(null,'Document deleted');
    }
}
else{ err('Invalid entity. Use ?entity=contracts or ?entity=documents',400); }

err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
