<?php
// includes/nurse_tabs.php — Nurse Portal / Procedures and Billing switch (see page_tabs.php)
require_once __DIR__ . '/page_tabs.php';

function nurse_tabs(PDO $pdo, string $active): void {
    page_tabs($pdo, 'nurse-mode', [
        'portal'     => ['Nurse Portal', 'fa-user-nurse', 'patients.php?module=nurse&sub=patients-visit', 'patients.php', 'patients-visit'],
        'procedures' => ['Procedures and Billing', 'fa-hand-holding-medical', 'procedures.php?module=nurse&sub=procedures', 'procedures.php', 'procedures'],
    ], $active);
}
