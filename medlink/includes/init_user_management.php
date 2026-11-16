<?php
// Database initialization for user management
require_once 'database.php';

try {
    // Create roles table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            slug VARCHAR(50) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role_id INT NULL,
            status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            last_login DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
        )
    ");

    // Insert default roles if they don't exist
    $defaultRoles = [
        ['name' => 'Administrator', 'slug' => 'admin', 'description' => 'Full system access and administrative privileges'],
        ['name' => 'Doctor', 'slug' => 'doctor', 'description' => 'Medical practitioner with patient care access'],
        ['name' => 'Nurse', 'slug' => 'nurse', 'description' => 'Nursing staff with patient care support access'],
        ['name' => 'Receptionist', 'slug' => 'receptionist', 'description' => 'Front desk and appointment management'],
        ['name' => 'Pharmacist', 'slug' => 'pharmacist', 'description' => 'Pharmacy and medication management'],
        ['name' => 'Accountant', 'slug' => 'accountant', 'description' => 'Financial and billing operations'],
        ['name' => 'HR Manager', 'slug' => 'hr_manager', 'description' => 'Human resources and employee management'],
        ['name' => 'Lab Technician', 'slug' => 'lab_tech', 'description' => 'Laboratory operations and test management']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name, slug, description) VALUES (?, ?, ?)");
    foreach ($defaultRoles as $role) {
        $stmt->execute([$role['name'], $role['slug'], $role['description']]);
    }

    echo "Database tables created and default roles inserted successfully!\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>