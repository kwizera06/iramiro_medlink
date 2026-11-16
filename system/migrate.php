<?php
// migrate.php: sidebar order. "Users, Roles & Permissions" moves to the end of the menu (position 100), after Reports & HMIS.
// Idempotent. Run: open in browser, or `php migrate.php`.
if (PHP_SAPI !== 'cli') header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/includes/database.php';

$st = $pdo->prepare("UPDATE nav_menus SET menu_order = 100 WHERE title = 'Users, Roles & Permissions' AND menu_order <> 100");
$st->execute();
echo $st->rowCount() ? "menu 'Users, Roles & Permissions' moved to position 100\n" : "menu 'Users, Roles & Permissions' already at position 100 (or not present)\n";
echo "Done\n";
