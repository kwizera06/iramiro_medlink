<?php
// Doctors' Schedule page. Data = team members in medlink/includes/site-content.php (role = "Specialty · schedule"), editable in admin Front End.
require_once __DIR__ . '/medlink/includes/site-content.php';
$c = site_load();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function initials($n) { preg_match_all('/\p{L}/u', implode('', array_map(fn($w) => mb_substr($w, 0, 1), preg_split('/\s+/', trim(preg_replace('/^(Dr\.?)\s+/i', '', $n))))), $m); return mb_strtoupper(implode('', array_slice($m[0], 0, 2))); }

$docs = [];
foreach ($c['team']['members'] as $m) {
    if (trim($m['name'] ?? '') === '') continue;
    [$spec, $when] = array_pad(array_map('trim', explode('·', $m['role'] ?? '', 2)), 2, '');
    $docs[] = ['name' => $m['name'], 'spec' => $spec, 'when' => $when, 'photo' => $m['photo'] ?? ''];
}
$specs = array_values(array_unique(array_column($docs, 'spec')));
$specs = array_filter($specs);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctors' Schedule — <?= e($c['brand']['small'] . ' ' . $c['brand']['name']) ?></title>
<link rel="icon" href="assets/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css">
<style>
  .sched-hero{background:linear-gradient(115deg,var(--forest-deep),var(--forest) 60%,var(--leaf-dark));color:#fff;padding:72px 0 64px;position:relative;overflow:hidden;}
  .sched-hero::after{content:"";position:absolute;right:-100px;top:-120px;width:380px;height:380px;border-radius:50%;background:radial-gradient(circle,rgba(92,179,126,.3),transparent 70%);}
  .sched-hero .wrap{position:relative;z-index:1;}
  .sched-hero h1{color:#fff;font-size:clamp(32px,4.6vw,52px);line-height:1.1;margin-top:14px;max-width:640px;}
  .sched-hero p{color:rgba(255,255,255,.8);margin-top:14px;max-width:520px;font-size:17px;}
  .sched-hero .eyebrow{color:var(--leaf-light);}
  .sched-hero .eyebrow::before{background:var(--leaf-light);}
  .sched{padding:56px 0 100px;background:var(--cream);}
  .tools{display:flex;flex-wrap:wrap;gap:14px 20px;align-items:center;justify-content:space-between;margin-bottom:28px;}
  .chips{display:flex;flex-wrap:wrap;gap:8px;}
  .chip{padding:8px 16px;border-radius:999px;border:1.5px solid var(--line);background:#fff;font-size:13.5px;font-weight:600;color:var(--ink);transition:background .2s ease,border-color .2s ease,color .2s ease,transform .15s ease;}
  .chip:hover{border-color:var(--leaf);}
  .chip:active{transform:scale(.97);}
  .chip[aria-pressed="true"]{background:var(--forest);border-color:var(--ink);color:#fff;}
  .search{position:relative;}
  .search input{padding:11px 16px 11px 40px;border:1.5px solid var(--line);border-radius:999px;font:inherit;font-size:14.5px;background:#fff;width:260px;max-width:100%;transition:border-color .2s ease;}
  .search input:focus{border-color:var(--leaf);outline:none;}
  .search svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--ink-soft);}
  .doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;}
  .doc{background:#fff;border:1px solid var(--line);border-radius:var(--radius);padding:22px;display:flex;gap:16px;align-items:center;transition:transform .25s ease,box-shadow .25s ease;}
  .doc:hover{transform:translateY(-4px);box-shadow:var(--shadow);}
  .doc[hidden]{display:none;}
  .avatar{width:60px;height:60px;border-radius:50%;flex-shrink:0;background:var(--mint-200);color:var(--leaf-dark);display:flex;align-items:center;justify-content:center;font-family:'Inter',sans-serif;font-size:21px;font-weight:600;overflow:hidden;}
  .avatar img{width:100%;height:100%;object-fit:cover;}
  .doc h3{font-size:17.5px;line-height:1.25;}
  .badge{display:inline-block;margin-top:6px;padding:3px 10px;border-radius:999px;background:var(--mint-100);color:var(--leaf-dark);font-family:'Inter',sans-serif;font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;}
  .when{display:flex;align-items:center;gap:6px;margin-top:8px;font-size:14px;color:var(--ink-soft);}
  .when svg{color:var(--leaf);flex-shrink:0;}
  .empty{display:none;text-align:center;color:var(--ink-soft);padding:40px 0;}
  .cta-bar{margin-top:44px;padding:28px 32px;border-radius:20px;background:var(--forest);color:#fff;display:flex;flex-wrap:wrap;gap:18px;align-items:center;justify-content:space-between;}
  .cta-bar strong{font-family:'Inter',sans-serif;font-size:20px;font-weight:600;}
  .cta-bar span{display:block;color:rgba(255,255,255,.7);font-size:14.5px;margin-top:2px;}
  @media (prefers-reduced-motion:reduce){.doc,.chip{transition:none;}}
</style>
</head>
<body>

<header>
  <nav class="nav">
    <a href="index.php#home" class="brand">
      <img src="assets/logo.png" alt="">
      <span class="brand-name"><strong>Iramiro Clinic</strong></span>
    </a>
    <ul class="nav-links" id="navLinks">
      <li><a href="index.php#home"><?= e($c['nav']['home']) ?></a></li>
      <li><a href="index.php#about"><?= e($c['nav']['about']) ?></a></li>
      <li><a href="index.php#services"><?= e($c['nav']['services']) ?></a></li>
      <li><a href="doctors-schedule.php" aria-current="page" style="color:var(--leaf-dark)"><?= e($c['nav']['team']) ?></a></li>
      <li><a href="index.php#contact"><?= e($c['nav']['contact']) ?></a></li>
    </ul>
    <div class="nav-actions">
      <a class="btn btn-primary" href="medlink/login.php"><?= e($c['nav']['login']) ?></a>
      <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu"><span></span><span></span><span></span></button>
    </div>
  </nav>
</header>

<section class="sched-hero">
  <div class="wrap">
    <div class="eyebrow"><?= e($c['team']['eyebrow']) ?></div>
    <h1><?= e($c['team']['heading']) ?></h1>
    <p><?= e($c['team']['intro']) ?></p>
  </div>
</section>

<section class="sched">
  <div class="wrap">
    <div class="tools">
      <div class="chips" id="chips">
        <button class="chip" data-spec="" aria-pressed="true">All</button>
        <?php foreach ($specs as $s): ?><button class="chip" data-spec="<?= e($s) ?>" aria-pressed="false"><?= e($s) ?></button><?php endforeach; ?>
      </div>
      <label class="search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input id="q" type="search" placeholder="Search a doctor" aria-label="Search a doctor">
      </label>
    </div>

    <div class="doc-grid" id="grid">
      <?php foreach ($docs as $d): ?>
      <article class="doc" data-spec="<?= e($d['spec']) ?>" data-name="<?= e(mb_strtolower($d['name'])) ?>">
        <div class="avatar"><?php if ($d['photo']): ?><img src="<?= e($d['photo']) ?>" alt="" loading="lazy"><?php else: echo e(initials($d['name'])); endif; ?></div>
        <div>
          <h3><?= e($d['name']) ?></h3>
          <?php if ($d['spec']): ?><span class="badge"><?= e($d['spec']) ?></span><?php endif; ?>
          <?php if ($d['when']): ?><div class="when"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><?= e($d['when']) ?></div><?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <p class="empty" id="empty">No doctor matches your search.</p>

    <div class="cta-bar">
      <div><strong>Need to reach us?</strong><span>Open <?= e(preg_replace('/^Open /', '', $c['about']['card_footer'])) ?></span></div>
      <a class="btn btn-primary" href="index.php#contact"><?= e($c['nav']['contact']) ?></a>
    </div>
  </div>
</section>

<footer>
  <div class="wrap">
    <div class="footer-bottom" style="padding-top:0">
      <div><?= e($c['footer']['copyright']) ?></div>
      <div><?= e($c['footer']['tagline']) ?></div>
    </div>
  </div>
</footer>

<script>
  const navLinks = document.getElementById('navLinks');
  document.getElementById('menuToggle').addEventListener('click', () => navLinks.classList.toggle('open'));
  const chips = [...document.querySelectorAll('.chip')], cards = [...document.querySelectorAll('.doc')], q = document.getElementById('q');
  let spec = '';
  function apply() {
    const t = q.value.trim().toLowerCase();
    let n = 0;
    cards.forEach(d => { const show = (!spec || d.dataset.spec === spec) && d.dataset.name.includes(t); d.hidden = !show; n += show; });
    document.getElementById('empty').style.display = n ? 'none' : 'block';
  }
  chips.forEach(b => b.addEventListener('click', () => { spec = b.dataset.spec; chips.forEach(x => x.setAttribute('aria-pressed', x === b)); apply(); }));
  q.addEventListener('input', apply);
</script>
</body>
</html>
