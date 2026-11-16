<?php
// Tab bar shared by systemlogs.php and every *logs page; highlights the current page.
$lnPages = [
    ['Patient',      'logdpatient',      'fa-user-injured'],
    ['Visit',        'visitlogs',        'fa-clipboard-list'],
    ['Consultation', 'consultationlogs', 'fa-stethoscope'],
    ['Department',   'departmentlogs',   'fa-hospital'],
    ['Lab',          'lablogs',          'fa-flask'],
    ['Pharmacy',     'pharmacylogs',     'fa-pills'],
    ['Procedure',    'procedurelogs',    'fa-procedures'],
    ['Ward',         'wardlogs',         'fa-bed'],
    ['Inventory',    'inventorylogs',    'fa-boxes'],
    ['Invoice',      'invoicelogs',      'fa-file-invoice-dollar'],
    ['Finance',      'financelogs',      'fa-balance-scale'],
    ['Afrirem',      'afriremlogs',      'fa-money-check-alt'],
    ['User',         'userlogs',         'fa-users-cog'],
    ['HR',           'hrlogs',           'fa-id-badge'],
];
// Filtered views of the full activity log (activity-log.php?mod=...)
$lnFilters = [
    ['Documents', 'documents', 'fa-file-medical'],
    ['Insurance', 'insurance', 'fa-shield-alt'],
    ['Settings',  'settings',  'fa-cogs'],
    ['Deletions', 'deletions', 'fa-trash-alt'],
    ['Logins',    'auth',      'fa-sign-in-alt'],
];
$lnMod = basename($_SERVER['SCRIPT_NAME'], '.php') === 'activity-log' ? ($_GET['mod'] ?? '') : null;
$lnCurrent = basename($_SERVER['SCRIPT_NAME'], '.php');
?>
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
<?php foreach ($lnPages as [$lnLabel, $lnPage, $lnIcon]): $on = $lnPage === $lnCurrent; ?>
  <a href="<?= $lnPage ?>.php?module=admin" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid <?= $on ? 'var(--primary-color)' : 'var(--gray-200)' ?>;background:<?= $on ? 'var(--primary-color)' : 'white' ?>;color:<?= $on ? 'white' : 'var(--gray-600)' ?>;">
    <i class="fas <?= $lnIcon ?>"></i> <?= $lnLabel ?>
  </a>
<?php endforeach; ?>
<?php foreach ($lnFilters as [$lnLabel, $lnKey, $lnIcon]): $on = $lnMod === $lnKey; ?>
  <a href="activity-log.php?module=admin&mod=<?= $lnKey ?>" style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid <?= $on ? 'var(--primary-color)' : 'var(--gray-200)' ?>;background:<?= $on ? 'var(--primary-color)' : 'white' ?>;color:<?= $on ? 'white' : 'var(--gray-600)' ?>;">
    <i class="fas <?= $lnIcon ?>"></i> <?= $lnLabel ?>
  </a>
<?php endforeach; ?>
</div>
