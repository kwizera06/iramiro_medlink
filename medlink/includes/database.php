<?php
// includes/database.php

@ini_set('pcre.jit', 0);

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || (strlen($needle) <= strlen($haystack) && substr_compare($haystack, $needle, -strlen($needle)) === 0);
    }
}

/** Run one-time schema DDL once per database, not once per request. Marker lives in var/; delete var/schema_*.done after restoring/importing a DB. */
function schema_once(string $key, callable $fn): void {
    $f = __DIR__ . '/../var/schema_' . preg_replace('/\W/', '', $key) . '.done';
    if (is_file($f)) return;
    $fn();
    @file_put_contents($f, date('c'));
}

$host = 'localhost';
$dbname = 'iramiro_final';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $GLOBALS['pdo'] = $pdo;
} catch(PDOException $e) {
    try {
        $password = 'Direca123123'; // prod fallback when empty password fails
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $GLOBALS['pdo'] = $pdo;
    } catch(PDOException $e2) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $e2->getMessage()
        ]);
        exit();
    }
}
?>
