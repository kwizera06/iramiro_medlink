<?php
// handlers/settings/menus.php — Dynamic Menu & Role Access Management
require_once __DIR__ . '/../../includes/database.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success',$s=200){jr(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];
$id=isset($_GET['id'])?(int)$_GET['id']:null;
$entity=$_GET['entity']??'menus'; // menus, items, access

try {

// ============================================================
// MENUS (parent menu groups)
// ============================================================
if($entity==='menus'){
    if($method==='GET'){
        if($id){$s=$pdo->prepare("SELECT * FROM nav_menus WHERE id=?");$s->execute([$id]);$r=$s->fetch();if(!$r)err('Not found',404);ok($r);}
        $s=$pdo->query("SELECT m.*, (SELECT COUNT(*) FROM nav_menu_items i WHERE i.menu_id=m.id) AS item_count FROM nav_menus m ORDER BY m.menu_order ASC");
        ok(['menus'=>$s->fetchAll()]);
    }
    if($method==='POST'){
        if(empty($input['title'])) err('title required',422);
        $s=$pdo->prepare("INSERT INTO nav_menus (title,icon,section_label,menu_order,is_active) VALUES (?,?,?,?,?)");
        $order=(int)$pdo->query("SELECT COALESCE(MAX(menu_order),0)+1 FROM nav_menus")->fetchColumn();
        $s->execute([trim($input['title']),trim($input['icon']??'fas fa-circle'),trim($input['section_label']??''),$input['menu_order']??$order,(int)($input['is_active']??1)]);
        ok(['id'=>(int)$pdo->lastInsertId()],'Menu created',201);
    }
    if($method==='PUT'){
        if(!$id) err('ID required',422);
        $s=$pdo->prepare("UPDATE nav_menus SET title=?,icon=?,section_label=?,menu_order=?,is_active=? WHERE id=?");
        $s->execute([trim($input['title']??''),trim($input['icon']??'fas fa-circle'),trim($input['section_label']??''),(int)($input['menu_order']??0),(int)($input['is_active']??1),$id]);
        ok(null,'Updated');
    }
    if($method==='DELETE'){
        if(!$id) err('ID required',422);
        $pdo->prepare("DELETE FROM nav_menus WHERE id=?")->execute([$id]);ok(null,'Deleted');
    }
}

// ============================================================
// MENU ITEMS (sub-links under a parent menu)
// ============================================================
elseif($entity==='items'){
    if($method==='GET'){
        if($id){$s=$pdo->prepare("SELECT i.*,m.title AS menu_title FROM nav_menu_items i JOIN nav_menus m ON m.id=i.menu_id WHERE i.id=?");$s->execute([$id]);$r=$s->fetch();if(!$r)err('Not found',404);ok($r);}
        $menuId=isset($_GET['menu_id'])?(int)$_GET['menu_id']:0;
        $w=[];$p=[];
        if($menuId>0){$w[]="i.menu_id=?";$p[]=$menuId;}
        $ws=$w?'WHERE '.implode(' AND ',$w):'';
        $s=$pdo->prepare("SELECT i.*,m.title AS menu_title FROM nav_menu_items i JOIN nav_menus m ON m.id=i.menu_id $ws ORDER BY m.menu_order, i.item_order ASC");
        $s->execute($p);
        ok(['items'=>$s->fetchAll()]);
    }
    if($method==='POST'){
        foreach(['menu_id','title','url'] as $f){if(empty($input[$f])) err("$f required",422);}
        $order=(int)$pdo->prepare("SELECT COALESCE(MAX(item_order),0)+1 FROM nav_menu_items WHERE menu_id=?")->execute([(int)$input['menu_id']]);
        $order=(int)$pdo->query("SELECT COALESCE(MAX(item_order),0)+1 FROM nav_menu_items WHERE menu_id=".(int)$input['menu_id'])->fetchColumn();
        $s=$pdo->prepare("INSERT INTO nav_menu_items (menu_id,title,url,icon,item_order,is_active) VALUES (?,?,?,?,?,?)");
        $s->execute([(int)$input['menu_id'],trim($input['title']),trim($input['url']),trim($input['icon']??'fas fa-circle'),$input['item_order']??$order,(int)($input['is_active']??1)]);
        $newItemId=(int)$pdo->lastInsertId();
        // Auto-assign to admin role (id=1)
        $pdo->prepare("INSERT IGNORE INTO role_menu_access (role_id, menu_item_id) VALUES (1, ?)")->execute([$newItemId]);
        ok(['id'=>$newItemId],'Item created',201);
    }
    if($method==='PUT'){
        if(!$id) err('ID required',422);
        $s=$pdo->prepare("UPDATE nav_menu_items SET menu_id=?,title=?,url=?,icon=?,item_order=?,is_active=? WHERE id=?");
        $s->execute([(int)($input['menu_id']??0),trim($input['title']??''),trim($input['url']??''),trim($input['icon']??'fas fa-circle'),(int)($input['item_order']??0),(int)($input['is_active']??1),$id]);
        ok(null,'Updated');
    }
    if($method==='DELETE'){
        if(!$id) err('ID required',422);
        $pdo->prepare("DELETE FROM nav_menu_items WHERE id=?")->execute([$id]);ok(null,'Deleted');
    }
}

// ============================================================
// ROLE ACCESS — Assign/revoke menu items per role
// ============================================================
elseif($entity==='access'){
    if($method==='GET'){
        $roleId=isset($_GET['role_id'])?(int)$_GET['role_id']:0;
        if(!$roleId) err('role_id required',422);
        // Get all menu items with access flag for this role
        $s=$pdo->prepare("
            SELECT i.id, i.title, i.url, i.icon, i.menu_id, m.title AS menu_title, m.section_label,
                   CASE WHEN rma.id IS NOT NULL THEN 1 ELSE 0 END AS has_access
            FROM nav_menu_items i
            JOIN nav_menus m ON m.id=i.menu_id
            LEFT JOIN role_menu_access rma ON rma.menu_item_id=i.id AND rma.role_id=?
            WHERE i.is_active=1 AND m.is_active=1
            ORDER BY m.menu_order, i.item_order
        ");
        $s->execute([$roleId]);
        ok(['role_id'=>$roleId,'items'=>$s->fetchAll()]);
    }
    if($method==='POST'){
        // Bulk update: receive role_id and array of menu_item_ids
        $roleId=(int)($input['role_id']??0);
        if(!$roleId) err('role_id required',422);
        $itemIds=$input['menu_item_ids']??[];
        if(!is_array($itemIds)) err('menu_item_ids must be an array',422);
        // Clear existing access for this role
        $pdo->prepare("DELETE FROM role_menu_access WHERE role_id=?")->execute([$roleId]);
        // Insert new access
        if(count($itemIds)>0){
            $vals=[];$params=[];
            foreach($itemIds as $itemId){
                $vals[]="(?,?)";
                $params[]=$roleId;
                $params[]=(int)$itemId;
            }
            $pdo->prepare("INSERT INTO role_menu_access (role_id,menu_item_id) VALUES ".implode(',',$vals))->execute($params);
        }
        ok(['role_id'=>$roleId,'assigned'=>count($itemIds)],'Access updated');
    }
}

// ============================================================
// USER ACCESS OVERRIDES — Custom page access per specific user
// ============================================================
elseif($entity==='user_access'){
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_menu_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        menu_item_id INT NOT NULL,
        access_type VARCHAR(20) DEFAULT 'grant',
        granted_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_menu (user_id, menu_item_id),
        KEY idx_user_id (user_id),
        KEY idx_menu_item_id (menu_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if($method==='GET'){
        $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
        if(!$targetUserId) err('user_id required', 422);

        // Fetch user info
        $uStmt = $pdo->prepare("
            SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.status
            FROM users u WHERE u.id = ?
        ");
        $uStmt->execute([$targetUserId]);
        $targetUser = $uStmt->fetch();
        if(!$targetUser) err('User not found', 404);

        // Fetch user's assigned roles
        $rStmt = $pdo->prepare("
            SELECT r.id, r.name, r.slug
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = ?
            ORDER BY r.name
        ");
        $rStmt->execute([$targetUserId]);
        $targetRoles = $rStmt->fetchAll();
        $targetRoleIds = array_map(function($r) { return (int)$r['id']; }, $targetRoles);

        $isTargetAdmin = false;
        foreach ($targetRoles as $r) {
            if ($r['slug'] === 'admin') $isTargetAdmin = true;
        }

        // Get all menu items with role access flag and user override flag
        $rolePlaceholders = !empty($targetRoleIds) ? implode(',', array_fill(0, count($targetRoleIds), '?')) : '0';
        $params = !empty($targetRoleIds) ? $targetRoleIds : [0];
        $params[] = $targetUserId;

        $s = $pdo->prepare("
            SELECT i.id, i.title, i.url, i.icon, i.menu_id, m.title AS menu_title, m.section_label,
                   MAX(CASE WHEN rma.id IS NOT NULL THEN 1 ELSE 0 END) AS has_role_access,
                   MAX(CASE WHEN uma.id IS NOT NULL THEN 1 ELSE 0 END) AS has_user_override,
                   MAX(CASE WHEN (rma.id IS NOT NULL OR uma.id IS NOT NULL) THEN 1 ELSE 0 END) AS has_access
            FROM nav_menu_items i
            JOIN nav_menus m ON m.id = i.menu_id
            LEFT JOIN role_menu_access rma ON rma.menu_item_id = i.id AND rma.role_id IN ($rolePlaceholders)
            LEFT JOIN user_menu_access uma ON uma.menu_item_id = i.id AND uma.user_id = ?
            WHERE i.is_active = 1 AND m.is_active = 1
            GROUP BY i.id, i.title, i.url, i.icon, i.menu_id, m.title, m.section_label, m.menu_order, i.item_order
            ORDER BY m.menu_order, i.item_order
        ");
        $s->execute($params);
        $items = $s->fetchAll();

        ok([
            'user' => $targetUser,
            'roles' => $targetRoles,
            'is_admin' => $isTargetAdmin,
            'items' => $items
        ]);
    }
    if($method==='POST'){
        $targetUserId = (int)($input['user_id'] ?? 0);
        if(!$targetUserId) err('user_id required', 422);

        // menu_item_ids: Array of menu_item_ids that are explicitly granted to this user as overrides
        $itemIds = $input['menu_item_ids'] ?? [];
        if(!is_array($itemIds)) err('menu_item_ids must be an array', 422);

        $assignedBy = $_SESSION['user_id'] ?? null;

        // Clear existing custom overrides for this user
        $pdo->prepare("DELETE FROM user_menu_access WHERE user_id = ?")->execute([$targetUserId]);

        // Insert new custom overrides
        if(count($itemIds) > 0){
            $vals = []; $params = [];
            foreach($itemIds as $itemId){
                $vals[] = "(?, ?, 'grant', ?)";
                $params[] = $targetUserId;
                $params[] = (int)$itemId;
                $params[] = $assignedBy;
            }
            $pdo->prepare("INSERT INTO user_menu_access (user_id, menu_item_id, access_type, granted_by) VALUES " . implode(',', $vals))->execute($params);
        }

        ok(['user_id' => $targetUserId, 'override_count' => count($itemIds)], 'User access overrides updated successfully');
    }
}

// ============================================================
// RESET USER ACCESS (Delete all overrides for a user)
// ============================================================
elseif($entity==='user_access_reset'){
    if($method==='POST'){
        $targetUserId = (int)($input['user_id'] ?? 0);
        if(!$targetUserId) err('user_id required', 422);

        $pdo->exec("CREATE TABLE IF NOT EXISTS user_menu_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            menu_item_id INT NOT NULL,
            access_type VARCHAR(20) DEFAULT 'grant',
            granted_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_menu (user_id, menu_item_id),
            KEY idx_user_id (user_id),
            KEY idx_menu_item_id (menu_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->prepare("DELETE FROM user_menu_access WHERE user_id = ?")->execute([$targetUserId]);
        ok(['user_id' => $targetUserId], 'User access reset to role defaults');
    }
}

// ============================================================
// LIST USERS WITH ACTIVE OVERRIDES
// ============================================================
elseif($entity==='users_with_overrides'){
    if($method==='GET'){
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_menu_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            menu_item_id INT NOT NULL,
            access_type VARCHAR(20) DEFAULT 'grant',
            granted_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_menu (user_id, menu_item_id),
            KEY idx_user_id (user_id),
            KEY idx_menu_item_id (menu_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $s = $pdo->query("
            SELECT u.id, u.username, u.first_name, u.last_name, u.email,
                   COUNT(uma.id) AS override_count,
                   GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') AS role_names,
                   GROUP_CONCAT(DISTINCT i.title SEPARATOR ', ') AS override_pages
            FROM users u
            JOIN user_menu_access uma ON uma.user_id = u.id
            JOIN nav_menu_items i ON i.id = uma.menu_item_id
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            GROUP BY u.id, u.username, u.first_name, u.last_name, u.email
            ORDER BY u.first_name, u.last_name
        ");
        ok(['users' => $s->fetchAll()]);
    }
}

// ============================================================
// FULL MENU TREE (for header rendering)
// ============================================================
elseif($entity==='tree'){
    if($method==='GET'){
        $roleId=isset($_GET['role_id'])?(int)$_GET['role_id']:0;
        // Get full menu tree filtered by role access
        $query="SELECT m.id AS menu_id, m.title AS menu_title, m.icon AS menu_icon, m.section_label,
                       i.id AS item_id, i.title AS item_title, i.url, i.icon AS item_icon
                FROM nav_menus m
                JOIN nav_menu_items i ON i.menu_id=m.id AND i.is_active=1
                LEFT JOIN role_menu_access rma ON rma.menu_item_id=i.id AND rma.role_id=?
                WHERE m.is_active=1 ".($roleId?"AND rma.id IS NOT NULL":"")."
                ORDER BY m.menu_order, i.item_order";
        $s=$pdo->prepare($query);
        $s->execute([$roleId]);
        $rows=$s->fetchAll();
        // Group by menu
        $tree=[];
        foreach($rows as $row){
            $mid=$row['menu_id'];
            if(!isset($tree[$mid])){
                $tree[$mid]=['id'=>$mid,'title'=>$row['menu_title'],'icon'=>$row['menu_icon'],'section_label'=>$row['section_label'],'items'=>[]];
            }
            $tree[$mid]['items'][]=['id'=>$row['item_id'],'title'=>$row['item_title'],'url'=>$row['url'],'icon'=>$row['item_icon']];
        }
        ok(['tree'=>array_values($tree)]);
    }
}
else{err('Invalid entity',400);}

err('Method not allowed',405);
} catch(PDOException $e){err('DB: '.$e->getMessage(),500);}
  catch(Exception $e){err('Server: '.$e->getMessage(),500);}
?>
