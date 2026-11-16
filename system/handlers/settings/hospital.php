<?php
// handlers/settings/hospital.php
require_once __DIR__ . '/../../includes/database.php';

function jsonResponse($d,$s=200){http_response_code($s);echo json_encode($d);exit();}
function success($d=null,$m='Success',$s=200){jsonResponse(['success'=>true,'message'=>$m,'data'=>$d],$s);}
function error($m,$s=400){jsonResponse(['success'=>false,'message'=>$m],$s);}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Ensure hospital_settings table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS hospital_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Ensure column is LONGTEXT (in case table already exists with TEXT)
    $pdo->exec("ALTER TABLE hospital_settings MODIFY COLUMN setting_value LONGTEXT");

    // GET - Load all settings
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM hospital_settings");
        $rows = $stmt->fetchAll();
        $data = [];
        foreach ($rows as $row) {
            $data[$row['setting_key']] = $row['setting_value'];
        }
        success($data);
    }

    // POST - Save settings
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $section = $input['section'] ?? '';
        unset($input['section']);

        if (empty($input)) error('No data to save', 422);

        // Public website URL used in document QR codes: http(s) only, no trailing slash
        if (array_key_exists('website_url', $input)) {
            $u = rtrim(trim((string)$input['website_url']), '/');
            if ($u !== '' && (!preg_match('#^https?://#i', $u) || !filter_var($u, FILTER_VALIDATE_URL))) error('Website URL must start with http:// or https:// (for example https://yourhospital.rw/system)', 422);
            $input['website_url'] = $u;
        }

        $stmt = $pdo->prepare("INSERT INTO hospital_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

        foreach ($input as $key => $value) {
            $stmt->execute([$key, $value]);
        }

        $sectionName = $section === 'hospital_info' ? 'Hospital information' : 'Document settings';
        success(null, $sectionName . ' saved successfully');
    }

    error('Method not allowed', 405);
} catch (PDOException $e) {
    error('Database error: ' . $e->getMessage(), 500);
}
?>
