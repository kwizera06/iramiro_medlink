<?php
// includes/page_perms.php — per-user action limits (add/edit/delete/download) on a page.
// No row in user_page_permissions = unrestricted (old behaviour). Admins are never restricted.

function pp_boot(PDO $pdo): void {
    schema_once('page_perms', fn() => $pdo->exec("CREATE TABLE IF NOT EXISTS user_page_permissions (
        user_id INT NOT NULL, menu_item_id INT NOT NULL,
        can_view TINYINT(1) NOT NULL DEFAULT 1, can_add TINYINT(1) NOT NULL DEFAULT 1,
        can_edit TINYINT(1) NOT NULL DEFAULT 1, can_delete TINYINT(1) NOT NULL DEFAULT 1,
        can_download TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (user_id, menu_item_id)
    )"));
}

// Menu item the browser is currently on, matched from the Referer (page + ?sub=)
function pp_current_item(PDO $pdo): ?int {
    $ref = parse_url($_SERVER['HTTP_REFERER'] ?? '');
    if (empty($ref['path'])) return null;
    parse_str($ref['query'] ?? '', $q);
    $page = basename($ref['path']);
    $st = $pdo->prepare("SELECT id, url FROM nav_menu_items WHERE TRIM(LEADING '/' FROM url) LIKE ?");
    $st->execute([$page . '%']);
    foreach ($st->fetchAll() as $row) {
        parse_str(parse_url($row['url'], PHP_URL_QUERY) ?? '', $iq);
        if (($iq['sub'] ?? '') === ($q['sub'] ?? '')) return (int)$row['id'];
    }
    return null;
}

// Block writes the user's row doesn't allow. Action is guessed from HTTP verb / action name.
// ponytail: name-based guess, an oddly named action may map to the wrong flag; download is UI-side only
function pp_enforce(PDO $pdo, string $endpoint, string $method): void {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    // hr/my-attendance: own check-in/out from the floating button, not an action on the current page
    if (!$uid || strpos($endpoint, 'auth/') === 0 || strpos($endpoint, 'settings/') === 0 || $endpoint === 'hr/my-attendance') return;
    pp_boot($pdo);
    $item = pp_current_item($pdo);
    if (!$item) return;
    $st = $pdo->prepare("SELECT * FROM user_page_permissions WHERE user_id=? AND menu_item_id=?");
    $st->execute([$uid, $item]);
    $p = $st->fetch();
    if (!$p) return;
    $adm = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug='admin' LIMIT 1");
    $adm->execute([$uid]);
    if ($adm->fetchColumn()) return;

    $body = json_decode(file_get_contents('php://input'), true);
    $act = strtolower((string)($_GET['action'] ?? (is_array($body) ? ($body['action'] ?? '') : '')));
    if ($method === 'DELETE' || preg_match('/delete|remove|cancel|void/', $act)) { $flag = 'can_delete'; $what = 'delete'; }
    elseif ($method === 'PUT' || $method === 'PATCH' || preg_match('/update|edit|change|set_|assign|transfer|discharge|complete|approve|reject/', $act)) { $flag = 'can_edit'; $what = 'edit'; }
    else { $flag = 'can_add'; $what = 'add'; }

    if (!$p[$flag]) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => "Your account is not allowed to $what on this page."]);
        exit();
    }
}

// Current user's limits for the page being rendered (null = unrestricted / admin)
function pp_page_row(PDO $pdo): ?array {
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (!$uid) return null;
    pp_boot($pdo);
    $page = basename($_SERVER['SCRIPT_NAME']);
    $st = $pdo->prepare("SELECT i.url, p.* FROM nav_menu_items i JOIN user_page_permissions p ON p.menu_item_id=i.id AND p.user_id=? WHERE TRIM(LEADING '/' FROM i.url) LIKE ?");
    $st->execute([$uid, $page . '%']);
    foreach ($st->fetchAll() as $row) {
        parse_str(parse_url($row['url'], PHP_URL_QUERY) ?? '', $iq);
        if (($iq['sub'] ?? '') !== ($_GET['sub'] ?? '')) continue;
        $adm = $pdo->prepare("SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug='admin' LIMIT 1");
        $adm->execute([$uid]);
        return $adm->fetchColumn() ? null : $row;
    }
    return null;
}
