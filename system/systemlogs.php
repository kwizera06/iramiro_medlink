<?php
require_once __DIR__ . '/includes/header.php';

?>
<div style="margin-bottom:16px;">
  <h2 style="font-size:24px;font-weight:600;"><i class="fas fa-history" style="color:var(--primary-color);"></i> System Logs</h2>
  <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Pick a module to see who did what and when (administrators only)</p>
</div>
<?php require_once __DIR__ . '/includes/components/log_nav.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
