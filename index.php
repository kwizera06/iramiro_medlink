<?php
// Public landing page. All text/images come from medlink/includes/site-content.php (edited in the admin "Front End" page).
require_once __DIR__ . '/medlink/includes/site-content.php';
$c = site_load();
$icons = site_icons();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function tel($p) { $d = preg_replace('/\D/', '', $p); return '+' . (strpos($d, '0') === 0 ? '250' . substr($d, 1) : $d); }
function initials($n) { preg_match_all('/\p{L}/u', implode('', array_map(fn($w) => mb_substr($w, 0, 1), preg_split('/[\s\/]+/', trim($n)))), $m); return mb_strtoupper(implode('', array_slice($m[0], 0, 2))); }
$check = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>';
$mailIc = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="M4 4l8 8 8-8"/></svg>';
$phoneIc = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.34 1.78.66 2.61a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.47-1.27a2 2 0 0 1 2.11-.45c.83.32 1.71.54 2.61.66A2 2 0 0 1 22 16.92z"/></svg>';
$pinIc = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';
$groups = array_values(array_filter($c['partners']['groups'], fn($g) => !empty($g['items'])));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($c['meta']['title']) ?></title>
<link rel="icon" href="assets/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css">
</head>
<body>

<header>
  <nav class="nav">
    <a href="#home" class="brand">
      <img src="assets/logo.png" alt="">
      <span class="brand-name"><strong>Iramiro Clinic </strong></span>
      
    </a>
    <ul class="nav-links" id="navLinks">
      <li><a href="#home"><?= e($c['nav']['home']) ?></a></li>
      <li><a href="#about"><?= e($c['nav']['about']) ?></a></li>
      <li><a href="#services"><?= e($c['nav']['services']) ?></a></li>
      <li><a href="doctors-schedule.php"><?= e($c['nav']['team']) ?></a></li>
      <?php if ($groups): ?><li><a href="#partner"><?= e($c['nav']['partner']) ?></a></li><?php endif; ?>
      <li><a href="#contact"><?= e($c['nav']['contact']) ?></a></li>
    </ul>
    <div class="nav-actions">
      <button class="btn btn-primary" id="openLogin"><?= e($c['nav']['login']) ?></button>
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu"><span></span><span></span><span></span></button>
    </div>
  </nav>
</header>

<section class="hero" id="home" style="background-image:url('<?= e($c['hero']['image']) ?>')">
  <div class="wrap">
    <div class="hero-text">
      <div class="hero-eyebrow"><?= e($c['hero']['eyebrow']) ?></div>
      <h1><?= e($c['hero']['title']) ?> <em><?= e($c['hero']['title_highlight']) ?></em></h1>
      <p><?= e($c['hero']['text']) ?></p>
      <div class="hero-cta">
        <a href="#contact" class="btn btn-primary"><?= e($c['hero']['button_primary']) ?></a>
        <a href="#services" class="btn-outline"><?= e($c['hero']['button_secondary']) ?></a>
      </div>
    </div>
  </div>
</section>

<section class="quick">
  <div class="wrap">
    <div class="quick-card">
      <h3><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg> Find a Doctor</h3>
      <p>See our specialists and their consultation days and hours.</p>
      <a href="doctors-schedule.php" class="btn btn-primary">View Schedule</a>
    </div>
    <div class="quick-card">
      <h3><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg> Our Services</h3>
      <p>Explore our full range of medical care under one roof.</p>
      <a href="#services" class="btn btn-primary">View Services</a>
    </div>
    <div class="quick-card">
      <h3><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-6.3-7-11a7 7 0 0 1 14 0c0 4.7-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg> Visit Us</h3>
      <p>Open Monday to Sunday, 24 hours. Find us in Kabuga, Kigali.</p>
      <a href="#contact" class="btn btn-primary">Get in Touch</a>
    </div>
  </div>
</section>

<section class="about" id="about">
  <div class="wrap">
    <div>
      <div class="eyebrow"><?= e($c['about']['eyebrow']) ?></div>
      <h2><?= e($c['about']['heading']) ?></h2>
      <?php foreach ($c['about']['paragraphs'] as $p): if (trim($p) === '') continue; ?>
      <p><?= e($p) ?></p>
      <?php endforeach; ?>
      <div class="about-strip">
        <a href="mailto:<?= e($c['contact']['email']) ?>"><span class="dot"><?= $mailIc ?></span><?= e($c['contact']['email']) ?></a>
        <a href="tel:<?= e(tel($c['contact']['phone1'])) ?>"><span class="dot"><?= $phoneIc ?></span><?= e(trim($c['contact']['phone1'] . ' / ' . $c['contact']['phone2'], ' /')) ?></a>
      </div>
    </div>
    <div class="about-card">
      <div class="eyebrow"><?= e($c['about']['card_eyebrow']) ?></div>
      <h3 style="color:#fff;margin-top:14px;font-size:22px;"><?= e($c['about']['card_title']) ?></h3>
      <ul class="about-list">
        <?php foreach ($c['about']['card_list'] as $li): if (trim($li) === '') continue; ?>
        <li><?= $check ?> <?= e($li) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="foot"><?= e($c['about']['card_footer']) ?></div>
    </div>
  </div>
</section>

<section class="services" id="services">
  <div class="wrap">
    <div class="services-head">
      <div class="eyebrow" style="justify-content:center;"><?= e($c['services']['eyebrow']) ?></div>
      <h2><?= e($c['services']['heading']) ?></h2>
      <p><?= e($c['services']['intro']) ?></p>
    </div>
    <div class="service-grid">
      <?php foreach ($c['services']['cards'] as $card): ?>
      <div class="service-card">
        <?php if (!empty($card['image'])): ?><img class="service-photo" src="<?= e($card['image']) ?>" alt="<?= e($card['title']) ?>" loading="lazy"><?php endif; ?>
        <div class="service-icon">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $icons[$card['icon']] ?? $icons['heart'] ?></svg>
        </div>
        <h3><?= e($card['title']) ?></h3>
        <p><?= e($card['text']) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<?php if ($groups): ?>
<section class="partner" id="partner">
  <div class="wrap">
    <div class="partner-head">
      <div>
        <div class="eyebrow"><?= e($c['partners']['eyebrow']) ?></div>
        <h2><?= e($c['partners']['heading']) ?></h2>
        <p><?= e($c['partners']['text']) ?></p>
      </div>
      <a href="#contact" class="btn btn-primary"><?= e($c['partners']['button']) ?></a>
    </div>
    <?php foreach ($groups as $g): ?>
    <div class="partner-group">
      <h4><?= e($g['title']) ?></h4>
      <div class="partner-grid">
        <?php foreach ($g['items'] as $it): ?>
        <div class="partner-card">
          <div class="partner-logo"><?php if (!empty($it['logo'])): ?><img src="<?= e($it['logo']) ?>" alt="" loading="lazy"><?php else: echo e(initials($it['name'] ?? '')); endif; ?></div>
          <div><strong><?= e($it['name'] ?? '') ?></strong><span><?= e($it['label'] ?? '') ?></span></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="contact" id="contact">
  <div class="wrap">
    <div>
      <div class="eyebrow"><?= e($c['contact']['eyebrow']) ?></div>
      <h2><?= e($c['contact']['heading']) ?></h2>
      <p><?= e($c['contact']['text']) ?></p>
      <ul class="contact-info">
        <li>
          <span class="ic"><?= str_replace('width="16" height="16"', 'width="18" height="18"', $mailIc) ?></span>
          <div><div class="lbl"><?= e($c['contact']['label_email']) ?></div><div class="val"><?= e($c['contact']['email']) ?></div></div>
        </li>
        <li>
          <span class="ic"><?= str_replace('width="16" height="16"', 'width="18" height="18"', $phoneIc) ?></span>
          <div><div class="lbl"><?= e($c['contact']['label_phone']) ?></div><div class="val"><?= e(trim($c['contact']['phone1'] . ' / ' . $c['contact']['phone2'], ' /')) ?></div></div>
        </li>
        <li>
          <span class="ic"><?= str_replace('width="16" height="16"', 'width="18" height="18"', $pinIc) ?></span>
          <div><div class="lbl"><?= e($c['contact']['label_location']) ?></div><div class="val"><?= e($c['contact']['location']) ?></div></div>
        </li>
      </ul>
    </div>
    <?php $f = $c['contact']['form']; ?>
    <form class="contact-form" id="contactForm">
      <div class="form-row">
        <div class="field"><label for="fname"><?= e($f['name_label']) ?></label><input id="fname" type="text" placeholder="<?= e($f['name_placeholder']) ?>" required></div>
        <div class="field"><label for="femail"><?= e($f['email_label']) ?></label><input id="femail" type="email" placeholder="<?= e($f['email_placeholder']) ?>" required></div>
      </div>
      <div class="field"><label for="fsubject"><?= e($f['subject_label']) ?></label><input id="fsubject" type="text" placeholder="<?= e($f['subject_placeholder']) ?>"></div>
      <div class="field"><label for="fmessage"><?= e($f['message_label']) ?></label><textarea id="fmessage" placeholder="<?= e($f['message_placeholder']) ?>" required></textarea></div>
      <button type="submit" class="btn btn-primary"><?= e($f['button']) ?></button>
      <div class="form-note"><?= e($f['note']) ?></div>
    </form>
  </div>
</section>

<footer>
  <div class="wrap">
    <div class="footer-top">
      <div class="footer-brand">
        <img src="assets/logo.png" alt="<?= e($c['brand']['small'] . ' ' . $c['brand']['name']) ?> logo">
        <p><?= e($c['footer']['about']) ?></p>
      </div>
      <div>
        <h4><?= e($c['footer']['heading_menu']) ?></h4>
        <ul>
          <li><a href="#home"><?= e($c['nav']['home']) ?></a></li>
          <li><a href="#about"><?= e($c['nav']['about']) ?></a></li>
          <li><a href="#services"><?= e($c['nav']['services']) ?></a></li>
          <li><a href="doctors-schedule.php"><?= e($c['nav']['team']) ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?= e($c['footer']['heading_more']) ?></h4>
        <ul>
          <?php if ($groups): ?><li><a href="#partner"><?= e($c['nav']['partner']) ?></a></li><?php endif; ?>
          <li><a href="#contact"><?= e($c['nav']['contact']) ?></a></li>
          <li><a href="#" id="footerLogin"><?= e($c['nav']['login']) ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?= e($c['footer']['heading_contact']) ?></h4>
        <ul>
          <li><a href="mailto:<?= e($c['contact']['email']) ?>"><?= e($c['contact']['email']) ?></a></li>
          <li><a href="tel:<?= e(tel($c['contact']['phone1'])) ?>"><?= e($c['contact']['phone1']) ?></a></li>
          <?php if (trim($c['contact']['phone2']) !== ''): ?><li><a href="tel:<?= e(tel($c['contact']['phone2'])) ?>"><?= e($c['contact']['phone2']) ?></a></li><?php endif; ?>
          <li><a href="#"><?= e($c['footer']['address']) ?></a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div><?= e($c['footer']['copyright']) ?></div>
      <div><?= e($c['footer']['tagline']) ?></div>
    </div>
  </div>
</footer>

<script>
  const menuToggle = document.getElementById('menuToggle');
  const navLinks = document.getElementById('navLinks');
  menuToggle.addEventListener('click', () => navLinks.classList.toggle('open'));
  navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', () => navLinks.classList.remove('open')));

  [document.getElementById('openLogin'), document.getElementById('footerLogin')].forEach(btn => btn && btn.addEventListener('click', (e) => {
    e.preventDefault();
    window.location.href = "medlink/login.php";
  }));

  document.getElementById('contactForm').addEventListener('submit', (e) => {
    e.preventDefault();
    alert(<?= json_encode($f['thanks'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>);
    e.target.reset();
  });
</script>

</body>
</html>
