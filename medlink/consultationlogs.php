<?php
require_once __DIR__ . '/includes/header.php';
?>
<div style="margin-bottom:8px;">
  <h2 style="font-size:24px;font-weight:600;"><i class="fas fa-history" style="color:var(--primary-color);"></i> Consultation Activity Log</h2>
  <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Actions performed on consultations — who did what and when (administrators only)</p>
</div>
<?php
require_once __DIR__ . '/includes/components/log_nav.php';
// Widget renders nothing for non-admins, so the page is empty for them
$alwModule = 'consultations';
$alwTitle  = 'Consultation Activity Log';
require_once __DIR__ . '/includes/components/activity_log_widget.php';

require_once __DIR__ . '/includes/footer.php';
