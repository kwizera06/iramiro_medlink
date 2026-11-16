<?php
/**
 * Install HR Enhancement Schema
 * Run this once to create all new HR tables
 */
require_once __DIR__ . '/includes/database.php';

echo "Installing HR Enhancement Module...\n\n";

$sql = file_get_contents(__DIR__ . '/sql/hr_enhancement.sql');

// Split by delimiter
$statements = explode(';', $sql);
$count = 0;
$errors = [];

$pdo->beginTransaction();
try {
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        
        $pdo->exec($stmt);
        $count++;
    }
    $pdo->commit();
    echo "✅ Successfully executed $count SQL statements.\n\n";
    
    // Verify tables
    $tables = [
        'employee_emergency_contacts', 'employee_dependents', 'employee_qualifications',
        'employee_certifications', 'employee_contracts', 'employee_employment_history',
        'employee_bank_details', 'employee_professional_memberships', 'employee_career_progression',
        'vacancy_requests', 'vacancy_approvals', 'vacancy_documents',
        'shift_types', 'duty_rosters', 'roster_shift_assignments',
        'training_programs', 'training_enrollments',
        'disciplinary_cases', 'disciplinary_actions', 'disciplinary_hearings',
        'announcements', 'messages', 'message_recipients',
        'surveys', 'survey_questions', 'survey_responses',
        'employee_goals', 'employee_tasks', 'performance_improvement_plans',
        'employee_bank_details'
    ];
    
    $allOk = true;
    foreach (array_unique($tables) as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  ✅ $table\n";
        } else {
            echo "  ❌ $table - NOT CREATED\n";
            $allOk = false;
        }
    }
    
    echo "\n";
    if ($allOk) {
        echo "🎉 All HR enhancement tables installed successfully!\n";
    } else {
        echo "⚠️  Some tables are missing. Check errors above.\n";
    }
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
}
