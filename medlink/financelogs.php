<?php
require_once __DIR__ . '/includes/header.php';
?>
<div style="margin-bottom:8px;">
  <h2 style="font-size:24px;font-weight:600;"><i class="fas fa-history" style="color:var(--primary-color);"></i> Finance Activity Log</h2>
  <p style="color:var(--gray-500);font-size:14px;margin-top:4px;">Expenses, deposits, refunds, supplier payments, period closes, reversals and ledger repairs — who did what and when (administrators only)</p>
</div>
<?php
require_once __DIR__ . '/includes/components/log_nav.php';
// Widget renders nothing for non-admins, so the page is empty for them
$alwModule = 'finance';
$alwTitle  = 'Finance Activity Log';
require_once __DIR__ . '/includes/components/activity_log_widget.php';

require_once __DIR__ . '/includes/footer.php';
