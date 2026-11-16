<?php
if (!defined('MEDLINK_API') && PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// handlers/settings/user-sessions.php
// Returns staff shift activity from user_sessions joined with users + roles
require_once __DIR__ . '/../../includes/database.php';
function jr($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function ok($d=null,$m='Success'){jr(['success'=>true,'message'=>$m,'data'=>$d]);}
function err($m,$s=400){jr(['success'=>false,'message'=>$m],$s);}

if($_SERVER['REQUEST_METHOD'] !== 'GET') err('Method not allowed',405);

try {
    $dateFrom  = $_GET['date_from']  ?? date('Y-m-d');
    $dateTo    = $_GET['date_to']    ?? date('Y-m-d');
    $timeFrom  = $_GET['time_from']  ?? ''; // e.g. "08:00"
    $timeTo    = $_GET['time_to']    ?? ''; // e.g. "17:00"

    // Build WHERE
    $w = ["us.login_at >= ? AND us.login_at < DATE_ADD(?, INTERVAL 1 DAY)"];
    $p = [$dateFrom, $dateTo];

    if ($timeFrom !== '' && $timeTo !== '') {
        if ($timeTo > $timeFrom) {
            // Day shift e.g. 08:00–17:00
            $w[] = "TIME(us.login_at) BETWEEN ? AND ?";
            $p[] = $timeFrom;
            $p[] = $timeTo;
        } else {
            // Night shift e.g. 17:00–08:00
            $w[] = "(TIME(us.login_at) >= ? OR TIME(us.login_at) <= ?)";
            $p[] = $timeFrom;
            $p[] = $timeTo;
        }
    }

    $ws = 'WHERE ' . implode(' AND ', $w);

    // Get all sessions in range with user name and roles
    $stmt = $pdo->prepare("
        SELECT
            us.id,
            us.user_id,
            CONCAT(u.first_name, ' ', u.last_name) AS full_name,
            u.username,
            d.name AS department_name,
            us.login_at,
            us.logout_at,
            us.logout_reason,
            us.ip_address,
            TIMESTAMPDIFF(MINUTE, us.login_at, COALESCE(us.logout_at, NOW())) AS duration_minutes,
            GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles
        FROM user_sessions us
        JOIN users u ON u.id = us.user_id
        LEFT JOIN departments d ON d.id = u.department_id
        LEFT JOIN user_roles ur ON ur.user_id = u.id
        LEFT JOIN roles r ON r.id = ur.role_id
        $ws
        AND us.login_at IS NOT NULL
        GROUP BY us.id
        ORDER BY us.login_at DESC
    ");
    $stmt->execute($p);
    $sessions = $stmt->fetchAll();

    // Summary: group by user
    $users = [];
    foreach ($sessions as $s) {
        $uid = $s['user_id'];
        if (!isset($users[$uid])) {
            $users[$uid] = [
                'user_id'         => $uid,
                'full_name'       => $s['full_name'],
                'username'        => $s['username'],
                'department_name' => $s['department_name'],
                'roles'           => $s['roles'],
                'session_count'   => 0,
                'total_minutes'   => 0,
                'first_login'     => $s['login_at'],
                'last_logout'     => $s['logout_at'],
                'sessions'        => [],
            ];
        }
        $users[$uid]['session_count']++;
        $users[$uid]['total_minutes'] += max(0, (int)$s['duration_minutes']);
        if ($s['login_at'] < $users[$uid]['first_login']) {
            $users[$uid]['first_login'] = $s['login_at'];
        }
        if (!empty($s['logout_at']) && ($users[$uid]['last_logout'] === null || $s['logout_at'] > $users[$uid]['last_logout'])) {
            $users[$uid]['last_logout'] = $s['logout_at'];
        }
        $users[$uid]['sessions'][] = $s;
    }

    // Sort by total_minutes desc
    usort($users, fn($a,$b) => $b['total_minutes'] - $a['total_minutes']);

    ok([
        'users'    => array_values($users),
        'sessions' => $sessions,
        'total'    => count($sessions),
        'filters'  => ['date_from'=>$dateFrom,'date_to'=>$dateTo,'time_from'=>$timeFrom,'time_to'=>$timeTo],
    ]);

} catch (PDOException $e) { err('DB: '.$e->getMessage(), 500); }
  catch (Exception $e)    { err('Server: '.$e->getMessage(), 500); }
?>
