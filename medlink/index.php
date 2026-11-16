<!-- public/index.html -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MEDLINK — Multi-Hospital Management System</title>
<meta name="description" content="MEDLINK is a multi-hospital management platform — 67+ connected modules spanning patient care, AI clinical intelligence, IoT-connected devices, pharmacy, finance and national health reporting.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500;9..144,600;9..144,700;9..144,800;9..144,900&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ============================================
   TOKENS
   ============================================ */
:root{
  --ink:#152522;
  --ink-soft:#3c4f4a;
  --paper:#f6f2e9;
  --paper-alt:#eee6d4;
  --card:#fffdf8;
  --teal-900:#0c332f;
  --teal-800:#0f423c;
  --teal-700:#155e52;
  --teal-600:#1c7a68;
  --teal-500:#278f79;
  --sage-200:#c9dcc6;
  --sage-300:#a9c9ab;
  --coral-500:#dd6236;
  --coral-600:#c14e26;
  --gold-300:#d9b26a;
  --line:#ddd3bd;
  --radius:20px;
  --radius-sm:12px;
  --shadow-sm:0 2px 10px rgba(15,40,35,.06);
  --shadow-md:0 10px 30px rgba(15,40,35,.10);
  --shadow-lg:0 24px 60px rgba(15,40,35,.16);
  --ease:cubic-bezier(.4,0,.2,1);
}
*{margin:0;padding:0;box-sizing:border-box;}
html{scroll-behavior:smooth;}
body{
  font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;
  background:var(--paper);
  color:var(--ink);
  line-height:1.6;
  overflow-x:hidden;
}
a{text-decoration:none;color:inherit;}
img{max-width:100%;height:auto;display:block;}
.container{max-width:1240px;margin:0 auto;padding:0 28px;}
.eyebrow{
  font-family:'IBM Plex Mono',monospace;
  font-size:12px;
  letter-spacing:.14em;
  text-transform:uppercase;
  color:var(--coral-600);
  font-weight:600;
  display:inline-flex;
  align-items:center;
  gap:10px;
}
.eyebrow::before{
  content:'';
  width:22px;height:1px;background:var(--coral-500);
  display:inline-block;
}
h1,h2,h3,h4{font-family:'Fraunces',serif;color:var(--teal-900);font-weight:600;}
::selection{background:var(--coral-500);color:#fff;}

/* focus visibility */
a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible{
  outline:2px solid var(--coral-500); outline-offset:3px;
}

/* ============================================
   VITALS LINE SIGNATURE MOTIF
   ============================================ */
.vitals-divider{width:100%;height:34px;overflow:hidden;}
.vitals-divider svg{width:100%;height:100%;display:block;}
.vitals-path{
  fill:none;stroke-width:2;stroke:var(--coral-500);
  stroke-dasharray:900; stroke-dashoffset:900;
  animation:draw 3.6s var(--ease) forwards;
}
@keyframes draw{to{stroke-dashoffset:0;}}

/* ============================================
   NAVBAR
   ============================================ */
.navbar{
  position:fixed;top:0;left:0;right:0;z-index:1000;
  padding:20px 0;transition:all .35s var(--ease);
}
.navbar.scrolled{
  background:rgba(246,242,233,.92);
  backdrop-filter:blur(14px);
  box-shadow:var(--shadow-sm);
  padding:12px 0;
  border-bottom:1px solid var(--line);
}
.navbar .container{display:flex;align-items:center;justify-content:space-between;}
.navbar-brand{display:flex;align-items:center;gap:12px;font-family:'Fraunces',serif;font-size:22px;font-weight:700;color:#fff;}
.navbar.scrolled .navbar-brand{color:var(--teal-900);}
.navbar-brand .brand-mark{
  width:38px;height:38px;border-radius:10px;
  background:var(--coral-500);
  display:flex;align-items:center;justify-content:center;
  font-size:16px;color:#fff;flex-shrink:0;
}
.navbar-brand .brand-name em{font-style:normal;color:var(--sage-300);}
.navbar.scrolled .navbar-brand .brand-name em{color:var(--teal-600);}
.navbar-links{display:flex;align-items:center;gap:30px;list-style:none;}
.navbar-links a{
  color:rgba(255,255,255,.85);font-size:14.5px;font-weight:500;
  transition:color .25s var(--ease);position:relative;
}
.navbar.scrolled .navbar-links a{color:var(--ink-soft);}
.navbar-links a:not(.btn-nav):hover{color:#fff;}
.navbar.scrolled .navbar-links a:not(.btn-nav):hover{color:var(--teal-700);}
.btn-nav{
  padding:10px 22px;border-radius:999px;font-weight:600;font-size:14px;
  transition:all .25s var(--ease);
}
.btn-nav.ghost{border:1px solid rgba(255,255,255,.4);color:#fff !important;}
.navbar.scrolled .btn-nav.ghost{border-color:var(--teal-700);color:var(--teal-800) !important;}
.btn-nav.solid{background:var(--coral-500);color:#fff !important;}
.btn-nav.solid:hover{background:var(--coral-600);transform:translateY(-2px);}
.navbar-toggle{display:none;background:none;border:none;color:#fff;font-size:22px;cursor:pointer;}
.navbar.scrolled .navbar-toggle{color:var(--teal-900);}

/* ============================================
   HERO with image slider
   ============================================ */
.hero{
  position:relative;min-height:100vh;display:flex;align-items:center;
  overflow:hidden;padding-top:100px;color:#fff;
}
.hero .container{position:relative;z-index:2;display:grid;grid-template-columns:1.3fr 1fr;gap:56px;align-items:center;max-width:100%;padding:0 48px;}
.hero-slider{position:absolute;inset:0;z-index:0;}
.hero-slide{
  position:absolute;inset:0;background-size:cover;background-position:center;
  opacity:0;transition:opacity 1.8s ease-in-out;
}
.hero-slide.active{opacity:1;}
.hero-scrim{
  position:absolute;inset:0;
  background:linear-gradient(120deg, rgba(12,51,47,.94) 0%, rgba(12,51,47,.86) 38%, rgba(21,94,82,.55) 68%, rgba(21,94,82,.35) 100%);
  z-index:1;
}
.hero-inner{max-width:100%;}
.hero-inner .eyebrow{color:var(--gold-300);}
.hero-inner .eyebrow::before{background:var(--gold-300);}
.hero-inner h1{
  font-size:clamp(38px,5vw,60px);font-weight:600;line-height:1.06;
  margin:12px 0 14px;color:#fff;letter-spacing:-.01em;
}
.hero-inner h1 .accent{color:var(--sage-300);font-style:italic;font-weight:500;}
.hero-inner p{
  font-size:18px;color:rgba(255,255,255,.82);max-width:100%;margin-bottom:22px;line-height:1.75;
}
.hero-buttons{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:28px;}
.btn-primary{
  padding:16px 34px;background:var(--coral-500);color:#fff;border:none;border-radius:999px;
  font-size:15.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:10px;
  transition:all .3s var(--ease);
}
.btn-primary:hover{background:var(--coral-600);transform:translateY(-3px);box-shadow:0 14px 30px rgba(221,98,54,.35);}
.btn-secondary{
  padding:16px 34px;background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.35);
  border-radius:999px;font-size:15.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:10px;
  transition:all .3s var(--ease);
}
.btn-secondary:hover{background:rgba(255,255,255,.18);transform:translateY(-3px);}
.hero-stats{display:flex;gap:40px;flex-wrap:wrap;padding-top:20px;border-top:1px solid rgba(255,255,255,.18);}
.hero-stats-item .number{font-family:'Fraunces',serif;font-size:34px;font-weight:600;color:#fff;}
.hero-stats-item .label{font-size:13px;color:rgba(255,255,255,.65);font-family:'IBM Plex Mono',monospace;letter-spacing:.04em;}

/* Hero right column — key components panel */
.hero-components{
  background:rgba(12,51,47,.55);backdrop-filter:blur(18px);
  border:1px solid rgba(255,255,255,.16);border-radius:var(--radius);
  padding:30px 28px;box-shadow:0 30px 70px rgba(0,0,0,.35);
}
.hero-components .kc-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid rgba(255,255,255,.14);}
.hero-components .kc-head .kc-eyebrow{font-family:'IBM Plex Mono',monospace;font-size:11.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--gold-300);}
.hero-components .kc-head .kc-dot{width:8px;height:8px;border-radius:50%;background:#5fd6a8;box-shadow:0 0 0 4px rgba(95,214,168,.18);}

/* compact module list */
.kc-modules{max-height:500px;overflow-y:auto;padding-right:8px;}
.kc-modules::-webkit-scrollbar{width:5px;}
.kc-modules::-webkit-scrollbar-track{background:transparent;}
.kc-modules::-webkit-scrollbar-thumb{background:rgba(255,255,255,.2);border-radius:10px;}
.kc-modules-list{list-style:none;display:grid;grid-template-columns:repeat(2,1fr);gap:7px 16px;}
.kc-modules-list li{
  font-size:12px;line-height:1.45;color:rgba(255,255,255,.78);
  display:flex;align-items:flex-start;gap:8px;
}
.kc-modules-list li .mod-num{
  font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--gold-300);
  flex-shrink:0;min-width:20px;margin-top:1px;
}
.slide-dots{
  position:absolute;bottom:120px;left:48px;z-index:3;display:flex;gap:9px;
}
.slide-dots button{
  width:34px;height:4px;border-radius:2px;border:none;background:rgba(255,255,255,.35);
  cursor:pointer;transition:all .3s var(--ease);padding:0;
}
.slide-dots button.active{background:var(--coral-500);width:50px;}
.hero-caption{
  position:absolute;bottom:60px;left:48px;z-index:3;color:rgba(255,255,255,.7);
  font-family:'IBM Plex Mono',monospace;font-size:12px;letter-spacing:.06em;text-transform:uppercase;
}

/* ============================================
   SECTION HEADERS
   ============================================ */
.section{padding:110px 0;}
.section.alt{background:var(--paper-alt);}
.section-header{max-width:680px;margin-bottom:56px;}
.section-header.center{margin-left:auto;margin-right:auto;text-align:center;}
.section-header h2{font-size:clamp(30px,3.6vw,44px);font-weight:600;margin:16px 0 18px;letter-spacing:-.01em;}
.section-header p{font-size:17.5px;color:var(--ink-soft);line-height:1.8;}

/* ============================================
   ABOUT
   ============================================ */
.about-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:64px;align-items:center;}
.about-copy p{font-size:16.5px;color:var(--ink-soft);line-height:1.85;margin-bottom:18px;}
.about-values{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:32px;}
.about-value{padding:20px 0;border-top:1px solid var(--line);}
.about-value .num{font-family:'IBM Plex Mono',monospace;font-size:12px;color:var(--coral-600);letter-spacing:.08em;}
.about-value h4{font-size:16px;margin:8px 0 6px;color:var(--teal-800);}
.about-value p{font-size:14px;color:var(--ink-soft);margin:0;}
.about-visual{position:relative;border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-lg);aspect-ratio:4/5;}
.about-visual img{width:100%;height:100%;object-fit:cover;}
.about-visual .about-badge{
  position:absolute;bottom:24px;left:24px;right:24px;
  background:rgba(12,51,47,.88);backdrop-filter:blur(10px);
  border-radius:var(--radius-sm);padding:18px 20px;color:#fff;
  display:flex;align-items:center;gap:14px;
}
.about-visual .about-badge i{font-size:22px;color:var(--gold-300);}
.about-visual .about-badge .t{font-size:13px;color:rgba(255,255,255,.7);}
.about-visual .about-badge .v{font-family:'Fraunces',serif;font-size:18px;}

/* ============================================
   GALLERY STRIP (auto-scrolling)
   ============================================ */
.gallery-wrap{padding:70px 0;background:var(--teal-900);overflow:hidden;}
.gallery-wrap .gallery-head{
  display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:36px;
  color:#fff;
}
.gallery-wrap .gallery-head .eyebrow{color:var(--gold-300);}
.gallery-wrap .gallery-head .eyebrow::before{background:var(--gold-300);}
.gallery-wrap .gallery-head h3{color:#fff;font-size:26px;margin-top:10px;}
.gallery-wrap .gallery-head p{color:rgba(255,255,255,.6);font-size:14.5px;max-width:360px;text-align:right;}
.gallery-track{display:flex;gap:20px;width:max-content;animation:scroll-strip 42s linear infinite;}
.gallery-wrap:hover .gallery-track{animation-play-state:paused;}
.gallery-card{
  position:relative;width:300px;height:210px;border-radius:16px;overflow:hidden;flex-shrink:0;
}
.gallery-card img{width:100%;height:100%;object-fit:cover;transition:transform .5s var(--ease);}
.gallery-card:hover img{transform:scale(1.06);}
.gallery-card .tag{
  position:absolute;bottom:14px;left:14px;color:#fff;font-family:'IBM Plex Mono',monospace;
  font-size:11.5px;letter-spacing:.06em;text-transform:uppercase;
  background:rgba(12,51,47,.55);backdrop-filter:blur(6px);padding:6px 12px;border-radius:999px;
}
@keyframes scroll-strip{
  from{transform:translateX(0);}
  to{transform:translateX(-50%);}
}

/* ============================================
   MODULES OVERVIEW STRIP
   ============================================ */
.stats-strip{background:var(--teal-800);padding:0;}
.stats-strip .container{
  display:grid;grid-template-columns:repeat(4,1fr);
}
.stats-strip-item{
  padding:40px 28px;text-align:center;border-right:1px solid rgba(255,255,255,.1);color:#fff;
}
.stats-strip-item:last-child{border-right:none;}
.stats-strip-item .number{font-family:'Fraunces',serif;font-size:36px;color:var(--gold-300);}
.stats-strip-item .label{font-size:13px;color:rgba(255,255,255,.65);margin-top:4px;}

/* ============================================
   MODULE CATEGORY NAV (jump links)
   ============================================ */
.cat-nav{
  position:sticky;top:76px;z-index:50;background:var(--paper);
  border-bottom:1px solid var(--line);padding:16px 0;
  overflow-x:auto;
}
.cat-nav .container{display:flex;gap:10px;}
.cat-nav a{
  white-space:nowrap;padding:9px 18px;border-radius:999px;font-size:13.5px;font-weight:600;
  color:var(--ink-soft);background:var(--card);border:1px solid var(--line);
  transition:all .25s var(--ease);
}
.cat-nav a:hover, .cat-nav a.is-active{background:var(--teal-800);color:#fff;border-color:var(--teal-800);}

/* ============================================
   MODULE CATEGORY BLOCK
   ============================================ */
.mod-category{padding:90px 0;border-bottom:1px solid var(--line);scroll-margin-top:150px;}
.mod-category:last-child{border-bottom:none;}
.mod-category-head{display:grid;grid-template-columns:.9fr 1.4fr;gap:56px;margin-bottom:48px;align-items:start;}
.mod-category-head .cat-icon{
  width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;
  font-size:24px;color:#fff;margin-bottom:20px;
}
.mod-category-head h3{font-size:30px;margin-bottom:0;}
.mod-category-head .cat-blurb p{color:var(--ink-soft);font-size:16px;line-height:1.85;}

.mod-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;}
.mod-card{
  background:var(--card);border:1px solid var(--line);border-radius:var(--radius);
  padding:30px;transition:all .3s var(--ease);
}
.mod-card:hover{box-shadow:var(--shadow-md);transform:translateY(-4px);border-color:var(--teal-600);}
.mod-card-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;}
.mod-card .mini-icon{
  width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;
  font-size:18px;color:#fff;flex-shrink:0;
}
.mod-card .count{
  font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--ink-soft);
  border:1px solid var(--line);padding:3px 10px;border-radius:999px;
}
.mod-card h4{font-size:18.5px;margin-bottom:10px;color:var(--teal-900);}
.mod-card p{font-size:14.5px;color:var(--ink-soft);line-height:1.75;margin-bottom:16px;}
.mod-card ul{list-style:none;display:flex;flex-direction:column;gap:8px;}
.mod-card ul li{
  font-size:13.5px;color:var(--ink-soft);display:flex;align-items:center;gap:9px;
}
.mod-card ul li i{color:var(--teal-600);font-size:11px;}

/* icon color variants */
.ic-a{background:linear-gradient(135deg,#155e52,#278f79);}
.ic-b{background:linear-gradient(135deg,#dd6236,#e8875f);}
.ic-c{background:linear-gradient(135deg,#0c332f,#155e52);}
.ic-d{background:linear-gradient(135deg,#a9622a,#d9b26a);}
.ic-e{background:linear-gradient(135deg,#5b7a8c,#8fa9b7);}
.ic-f{background:linear-gradient(135deg,#8a4b8f,#b476ba);}
.ic-g{background:linear-gradient(135deg,#c14e26,#dd6236);}

/* ============================================
   AI & IoT SPOTLIGHT
   ============================================ */
.ai-spotlight{background:var(--teal-900);color:#fff;padding:110px 0;position:relative;overflow:hidden;}
.ai-spotlight::before{
  content:'';position:absolute;top:-30%;left:-10%;width:520px;height:520px;
  background:radial-gradient(circle, rgba(217,178,106,.14), transparent 70%);border-radius:50%;
}
.ai-spotlight::after{
  content:'';position:absolute;bottom:-30%;right:-10%;width:520px;height:520px;
  background:radial-gradient(circle, rgba(169,201,171,.12), transparent 70%);border-radius:50%;
}
.spotlight-head{position:relative;max-width:640px;margin-bottom:56px;}
.spotlight-head h3{color:#fff;font-size:clamp(26px,3.4vw,36px);margin:14px 0 14px;}
.spotlight-head p{color:rgba(255,255,255,.65);font-size:16px;line-height:1.8;}
.spotlight-grid{position:relative;display:grid;grid-template-columns:1fr 1fr;gap:28px;}
.spotlight-card{
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:var(--radius);
  padding:36px 32px;backdrop-filter:blur(6px);
}
.spotlight-icon{
  width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;
  font-size:22px;margin-bottom:20px;
}
.spotlight-icon.coral{background:rgba(221,98,54,.22);color:#ffb493;}
.spotlight-icon.sage{background:rgba(169,201,171,.18);color:var(--sage-300);}
.spotlight-card h4{color:#fff;font-size:22px;margin-bottom:14px;}
.spotlight-card>p{color:rgba(255,255,255,.68);font-size:15px;line-height:1.8;margin-bottom:20px;}
.spotlight-card ul{list-style:none;display:flex;flex-direction:column;gap:10px;margin-bottom:24px;}
.spotlight-card ul li{font-size:14px;color:rgba(255,255,255,.78);display:flex;align-items:flex-start;gap:10px;}
.spotlight-card ul li i{color:var(--gold-300);font-size:9px;margin-top:6px;}
.spotlight-insight{
  background:rgba(0,0,0,.22);border:1px solid rgba(255,255,255,.1);border-left:3px solid var(--gold-300);
  border-radius:10px;padding:16px 18px;
}
.spotlight-insight-label{
  font-family:'IBM Plex Mono',monospace;font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;
  color:var(--gold-300);display:block;margin-bottom:8px;
}
.spotlight-insight p{font-size:14px;color:rgba(255,255,255,.85);font-style:italic;margin:0;}

/* ============================================
   HOW IT WORKS
   ============================================ */
.how-steps{display:flex;flex-direction:column;}
.how-step{
  display:grid;grid-template-columns:120px 1fr 1fr;gap:40px;padding:44px 0;
  border-top:1px solid var(--line);align-items:center;
}
.how-step:last-child{border-bottom:1px solid var(--line);}
.how-step .step-num{
  font-family:'Fraunces',serif;font-size:52px;color:var(--sage-300);font-weight:600;line-height:1;
}
.how-step h4{font-size:22px;margin-bottom:12px;}
.how-step p{color:var(--ink-soft);font-size:15.5px;line-height:1.8;}
.how-step .step-visual{
  border-radius:16px;overflow:hidden;height:150px;box-shadow:var(--shadow-sm);
}
.how-step .step-visual img{width:100%;height:100%;object-fit:cover;}

/* ============================================
   FEATURES GRID (why choose us)
   ============================================ */
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:2px;background:var(--line);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;}
.feature-item{background:var(--card);padding:36px 28px;transition:background .3s var(--ease);}
.feature-item:hover{background:var(--paper-alt);}
.feature-item .icon{
  width:52px;height:52px;border-radius:14px;background:var(--teal-900);color:var(--gold-300);
  display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:18px;
}
.feature-item h4{font-size:17px;margin-bottom:8px;color:var(--teal-900);}
.feature-item p{font-size:14px;color:var(--ink-soft);line-height:1.7;}

/* ============================================
   CONTACT
   ============================================ */
.contact-wrap{display:grid;grid-template-columns:.85fr 1.15fr;gap:0;border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow-lg);}
.contact-info{background:var(--teal-900);color:#fff;padding:56px 46px;position:relative;overflow:hidden;}
.contact-info::before{
  content:'';position:absolute;top:-40%;right:-30%;width:400px;height:400px;
  background:radial-gradient(circle, rgba(217,178,106,.15), transparent 70%);border-radius:50%;
}
.contact-info h3{color:#fff;font-size:28px;margin-bottom:16px;position:relative;}
.contact-info>p{color:rgba(255,255,255,.7);font-size:15px;line-height:1.8;margin-bottom:36px;position:relative;max-width:360px;}
.contact-detail{display:flex;gap:16px;margin-bottom:26px;position:relative;}
.contact-detail i{
  width:42px;height:42px;border-radius:11px;background:rgba(255,255,255,.08);
  display:flex;align-items:center;justify-content:center;color:var(--gold-300);flex-shrink:0;
}
.contact-detail .t{font-size:12px;color:rgba(255,255,255,.55);font-family:'IBM Plex Mono',monospace;letter-spacing:.05em;text-transform:uppercase;}
.contact-detail .v{font-size:15.5px;color:#fff;margin-top:2px;}
.contact-social{display:flex;gap:12px;margin-top:36px;position:relative;}
.contact-social a{
  width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.08);
  display:flex;align-items:center;justify-content:center;color:#fff;transition:all .25s var(--ease);
}
.contact-social a:hover{background:var(--coral-500);}
.contact-form{background:var(--card);padding:56px 46px;}
.contact-form h4{font-size:20px;margin-bottom:26px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;}
.form-group{margin-bottom:18px;display:flex;flex-direction:column;gap:8px;}
.form-group label{font-size:12.5px;font-weight:600;color:var(--ink-soft);font-family:'IBM Plex Mono',monospace;letter-spacing:.03em;text-transform:uppercase;}
.form-group input, .form-group select, .form-group textarea{
  padding:13px 16px;border:1px solid var(--line);border-radius:10px;font-family:'Inter',sans-serif;
  font-size:14.5px;background:var(--paper);color:var(--ink);transition:border-color .25s var(--ease);
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus{
  border-color:var(--teal-600);outline:none;background:#fff;
}
.form-group textarea{resize:vertical;min-height:110px;}
.btn-submit{
  width:100%;padding:16px;background:var(--coral-500);color:#fff;border:none;border-radius:10px;
  font-size:15.5px;font-weight:600;cursor:pointer;transition:all .3s var(--ease);
  display:flex;align-items:center;justify-content:center;gap:10px;
}
.btn-submit:hover{background:var(--coral-600);}
.form-note{font-size:12.5px;color:var(--ink-soft);margin-top:14px;text-align:center;}
.form-success{
  display:none;text-align:center;padding:40px 20px;
}
.form-success.show{display:block;}
.form-success i{font-size:40px;color:var(--teal-600);margin-bottom:16px;}
.form-success h4{font-size:20px;margin-bottom:8px;}
.form-success p{color:var(--ink-soft);font-size:14.5px;}

/* ============================================
   CTA
   ============================================ */
.cta-section{
  padding:100px 0;background:linear-gradient(135deg,var(--teal-900),var(--teal-700));
  position:relative;overflow:hidden;color:#fff;
}
.cta-section::before{
  content:'';position:absolute;top:-50%;right:-15%;width:600px;height:600px;
  background:radial-gradient(circle, rgba(217,178,106,.18), transparent 70%);border-radius:50%;
}
.cta-content{position:relative;z-index:1;text-align:center;max-width:680px;margin:0 auto;}
.cta-content h2{color:#fff;font-size:clamp(28px,4vw,42px);margin-bottom:18px;}
.cta-content p{color:rgba(255,255,255,.72);font-size:17px;margin-bottom:36px;}
.cta-content .btn-group{display:flex;gap:16px;justify-content:center;flex-wrap:wrap;}

/* ============================================
   FOOTER
   ============================================ */
.footer{background:var(--ink);color:rgba(255,255,255,.6);padding:64px 0 30px;}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:44px;margin-bottom:44px;padding-bottom:44px;border-bottom:1px solid rgba(255,255,255,.08);}
.footer-brand{display:flex;align-items:center;gap:12px;font-family:'Fraunces',serif;font-size:22px;color:#fff;margin-bottom:14px;}
.footer-brand .brand-mark{width:34px;height:34px;border-radius:9px;background:var(--coral-500);display:flex;align-items:center;justify-content:center;font-size:14px;}
.footer-col p{font-size:14px;line-height:1.8;max-width:300px;}
.footer-links h4{font-size:13px;font-weight:600;color:#fff;margin-bottom:16px;font-family:'IBM Plex Mono',monospace;letter-spacing:.05em;text-transform:uppercase;}
.footer-links ul{list-style:none;display:flex;flex-direction:column;gap:10px;}
.footer-links ul a{font-size:14px;transition:color .25s var(--ease);}
.footer-links ul a:hover{color:#fff;}
.footer-bottom{display:flex;justify-content:space-between;align-items:center;font-size:13.5px;flex-wrap:wrap;gap:16px;}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width:1024px){
  .hero .container{grid-template-columns:1fr;}
  .hero-inner{max-width:100%;}
  .hero-components{max-width:560px;}
  .kc-modules-list{grid-template-columns:repeat(2,1fr);}
  .about-grid{grid-template-columns:1fr;}
  .about-visual{order:-1;aspect-ratio:16/9;}
  .mod-category-head{grid-template-columns:1fr;}
  .how-step{grid-template-columns:80px 1fr;}
  .how-step .step-visual{grid-column:1 / -1;height:180px;}
  .contact-wrap{grid-template-columns:1fr;}
  .footer-grid{grid-template-columns:1fr 1fr;}
  .spotlight-grid{grid-template-columns:1fr;}
  .stats-strip .container{grid-template-columns:repeat(2,1fr);}
  .stats-strip-item:nth-child(2){border-right:none;}
}
@media (max-width:768px){
  .navbar-links{
    display:none;flex-direction:column;align-items:flex-start;gap:18px;
    position:absolute;top:100%;left:0;right:0;background:var(--paper);
    padding:26px 28px;box-shadow:var(--shadow-lg);border-radius:0 0 var(--radius) var(--radius);
  }
  .navbar-links.open{display:flex;}
  .navbar.scrolled .navbar-links a{color:var(--ink-soft);}
  .navbar-toggle{display:block;}
  .hero-buttons{flex-direction:column;align-items:flex-start;}
  .hero-stats{gap:26px;}
  .slide-dots, .hero-caption{display:none;}
  .form-row{grid-template-columns:1fr;}
  .footer-grid{grid-template-columns:1fr;}
  .footer-bottom{flex-direction:column;text-align:center;}
  .section{padding:70px 0;}
  .cat-nav{top:64px;}
  .gallery-wrap .gallery-head{flex-direction:column;align-items:flex-start;gap:10px;}
  .gallery-wrap .gallery-head p{text-align:left;}
}
@media (max-width:480px){
  .hero-inner h1{font-size:32px;}
  .btn-primary, .btn-secondary{width:100%;justify-content:center;}
  .mod-category{padding:60px 0;}
}
</style>
</head>
<body>

<!-- ============================================
NAVIGATION
============================================ -->
<nav class="navbar" id="navbar">
  <div class="container">
    <a href="#home" class="navbar-brand">
      <div class="brand-mark"><i class="fas fa-diagram-project"></i></div>
      <span class="brand-name">MED<em>LINK</em></span>
    </a>
    <ul class="navbar-links" id="navbarLinks">
      <li><a href="#home">Home</a></li>
      <li><a href="#about">About Us</a></li>
      <li><a href="#modules">Modules</a></li>
      <li><a href="#how-it-works">How It Works</a></li>
      <li><a href="#contact">Contact</a></li>
      <li><a href="login.php" class="btn-nav solid">Login</a></li>
    </ul>
    <button class="navbar-toggle" id="navbarToggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>
  </div>
</nav>

<!-- ============================================
HERO — image slider
============================================ -->
<section class="hero" id="home">
  <div class="hero-slider" id="heroSlider">
    <div class="hero-slide active" style="background-image:url('https://images.pexels.com/photos/33812023/pexels-photo-33812023.jpeg?auto=compress&cs=tinysrgb&w=1920')"></div>
    <div class="hero-slide" style="background-image:url('https://images.pexels.com/photos/7335565/pexels-photo-7335565.jpeg?auto=compress&cs=tinysrgb&w=1920')"></div>
    <div class="hero-slide" style="background-image:url('https://images.pexels.com/photos/10827916/pexels-photo-10827916.jpeg?auto=compress&cs=tinysrgb&w=1920')"></div>
    <div class="hero-slide" style="background-image:url('https://images.pexels.com/photos/6074000/pexels-photo-6074000.jpeg?auto=compress&cs=tinysrgb&w=1920')"></div>
  </div>
  <div class="hero-scrim"></div>

  <div class="container">
    <div class="hero-inner">
      <span class="eyebrow">Multi-Hospital Management System</span>
      <h1>One platform. Every hospital, <span class="accent">every heartbeat.</span></h1>
      <p>
        MEDLINK connects registration, wards, pharmacy, labs, billing and dozens of other
        departments — across one hospital or an entire regional network — into a single live
        system, sharpened by AI clinical intelligence and IoT-connected devices.
      </p>
      <div class="hero-buttons">
        <a href="login.php" class="btn-primary"><i class="fas fa-right-to-bracket"></i> Login</a>
        <a href="#modules" class="btn-secondary"><i class="fas fa-grid-2"></i> Explore Modules</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stats-item"><div class="number">67+</div><div class="label">CONNECTED MODULES</div></div>
        <div class="hero-stats-item"><div class="number">9</div><div class="label">HOSPITALS ON ONE NETWORK</div></div>
        <div class="hero-stats-item"><div class="number">24/7</div><div class="label">LIVE SUPPORT</div></div>
      </div>
    </div>

    <div class="hero-components">
      <div class="kc-head">
        <span class="kc-eyebrow">67+ Modules Across Every Department</span>
        <span class="kc-dot" aria-hidden="true"></span>
      </div>
      <div class="kc-modules">
        <ol class="kc-modules-list">
          <li><span class="mod-num">1.</span>Multi-Hospital Administration</li>
          <li><span class="mod-num">2.</span>User, Role & Access Management</li>
          <li><span class="mod-num">3.</span>Patient Registration & Master Patient Index (MPI)</li>
          <li><span class="mod-num">4.</span>Appointment & Queue Management</li>
          <li><span class="mod-num">5.</span>Electronic Medical Records (EMR/EHR)</li>
          <li><span class="mod-num">6.</span>Outpatient Department (OPD) Management</li>
          <li><span class="mod-num">7.</span>Inpatient Department (IPD) Management</li>
          <li><span class="mod-num">8.</span>Emergency & Trauma Management</li>
          <li><span class="mod-num">9.</span>Nursing Management</li>
          <li><span class="mod-num">10.</span>Doctor & Clinical Management</li>
          <li><span class="mod-num">11.</span>Operating Theatre Management</li>
          <li><span class="mod-num">12.</span>ICU Management</li>
          <li><span class="mod-num">13.</span>Maternity & Reproductive Health Management</li>
          <li><span class="mod-num">14.</span>Pediatrics & Child Health Management</li>
          <li><span class="mod-num">15.</span>Immunization Management</li>
          <li><span class="mod-num">16.</span>Laboratory Information Management System (LIMS)</li>
          <li><span class="mod-num">17.</span>Radiology & Imaging (RIS/PACS)</li>
          <li><span class="mod-num">18.</span>Pharmacy Management</li>
          <li><span class="mod-num">19.</span>Blood Bank Management</li>
          <li><span class="mod-num">20.</span>Dental Management</li>
          <li><span class="mod-num">21.</span>Physiotherapy & Rehabilitation</li>
          <li><span class="mod-num">22.</span>Nutrition & Dietetics</li>
          <li><span class="mod-num">23.</span>Mental Health Management</li>
          <li><span class="mod-num">24.</span>Chronic Disease Management</li>
          <li><span class="mod-num">25.</span>Infection Prevention & Control (IPC)</li>
          <li><span class="mod-num">27.</span>Ambulance & Emergency Medical Services</li>
          <li><span class="mod-num">28.</span>Telemedicine & Virtual Care</li>
          <li><span class="mod-num">29.</span>Referral & Counter-Referral</li>
          <li><span class="mod-num">30.</span>Patient Portal & Mobile App</li>
          <li><span class="mod-num">31.</span>Clinical Decision Support System (CDSS)</li>
          <li><span class="mod-num">32.</span>Billing & Revenue Cycle Management</li>
          <li><span class="mod-num">33.</span>Insurance & Claims Management</li>
          <li><span class="mod-num">34.</span>Finance & Accounting</li>
          <li><span class="mod-num">35.</span>Procurement & Supplier Management</li>
          <li><span class="mod-num">36.</span>Inventory & Store Management</li>
          <li><span class="mod-num">37.</span>Asset & Equipment Management</li>
          <li><span class="mod-num">38.</span>Biomedical Engineering & Maintenance</li>
          <li><span class="mod-num">39.</span>Facility & Infrastructure Management</li>
          <li><span class="mod-num">40.</span>Human Resource Management</li>
          <li><span class="mod-num">41.</span>Payroll & Benefits Management</li>
          <li><span class="mod-num">42.</span>Duty Roster & Shift Scheduling</li>
          <li><span class="mod-num">43.</span>Training & CPD Management</li>
          <li><span class="mod-num">44.</span>Document & Records Management</li>
          <li><span class="mod-num">45.</span>Quality Assurance & Accreditation</li>
          <li><span class="mod-num">46.</span>Risk Management & Incident Reporting</li>
          <li><span class="mod-num">47.</span>Occupational Health & Safety</li>
          <li><span class="mod-num">48.</span>Research & Clinical Trials</li>
          <li><span class="mod-num">49.</span>Public Health & Disease Surveillance</li>
          <li><span class="mod-num">50.</span>Community Health & Outreach</li>
          <li><span class="mod-num">51.</span>Communication & Notification System</li>
          <li><span class="mod-num">52.</span>Customer Relationship & Feedback</li>
          <li><span class="mod-num">53.</span>Help Desk & Service Requests</li>
          <li><span class="mod-num">54.</span>Business Intelligence & Analytics</li>
          <li><span class="mod-num">55.</span>AI & Predictive Analytics</li>
          <li><span class="mod-num">56.</span>Workflow & Process Automation</li>
          <li><span class="mod-num">57.</span>Integration & Interoperability</li>
          <li><span class="mod-num">58.</span>Security, Privacy & Audit</li>
          <li><span class="mod-num">59.</span>Compliance & Regulatory Reporting</li>
          <li><span class="mod-num">60.</span>System Administration & Configuration</li>
          <li><span class="mod-num">61.</span>Backup, Disaster Recovery & Business Continuity</li>
          <li><span class="mod-num">62.</span>Multi-Language, Localization & Currency</li>
          <li><span class="mod-num">63.</span>Mobile Applications (All Stakeholders)</li>
          <li><span class="mod-num">64.</span>Remote Patient Monitoring (Mediwatch)</li>
          <li><span class="mod-num">65.</span>Internet of Things (building & equipment maintenance, temperature, humidity, air quality, lighting, water leakage)</li>
          <li><span class="mod-num">66.</span>Artificial Intelligence (staff attendance via facial recognition)</li>
          <li><span class="mod-num">67.</span>Patient Visit Management</li>
        </ol>
      </div>
    </div>
  </div>

  <div class="slide-dots" id="slideDots">
    <button class="active" data-slide="0" aria-label="Slide 1: Hospital corridor"></button>
    <button data-slide="1" aria-label="Slide 2: Hospital ward"></button>
    <button data-slide="2" aria-label="Slide 3: Clinical staff"></button>
    <button data-slide="3" aria-label="Slide 4: Pharmacy"></button>
  </div>
  <div class="hero-caption" id="slideCaption">01 — Hospital Corridor</div>
</section>

<!-- ============================================
STATS STRIP
============================================ -->
<div class="stats-strip">
  <div class="container">
    <div class="stats-strip-item"><div class="number">67+</div><div class="label">Modules across every department</div></div>
    <div class="stats-strip-item"><div class="number">300+</div><div class="label">Sub-features & workflows</div></div>
    <div class="stats-strip-item"><div class="number">98.9%</div><div class="label">Platform uptime</div></div>
    <div class="stats-strip-item"><div class="number">&lt;2s</div><div class="label">Average record lookup</div></div>
  </div>
</div>

<!-- ============================================
ABOUT US
============================================ -->
<section class="section" id="about">
  <div class="container">
    <div class="about-grid">
      <div class="about-copy">
        <span class="eyebrow">About Us</span>
        <h2>Built for a hospital — and a whole network of them.</h2>
        <p>
          MEDLINK started as a bed-tracking spreadsheet for a single 40-bed regional hospital. Today
          it is a multi-hospital management platform: one Super Admin can oversee an entire regional
          health system, while each hospital, branch and satellite clinic still runs its own wards,
          pharmacy and billing without waiting on head office.
        </p>
        <p>
          Underneath the day-to-day workflows sits a layer of AI clinical intelligence and IoT
          device integration — flagging the patient likely to deteriorate before the chart shows it,
          and streaming ICU monitor readings straight into the record. We are not a generic ERP with
          a medical skin — every screen, permission and report is designed around how hospitals,
          and the ministries that oversee them, actually run.
        </p>
        <div class="about-values">
          <div class="about-value">
            <div class="num">01</div>
            <h4>Clinical-first design</h4>
            <p>Every workflow is reviewed by practicing clinicians before release, not just product managers.</p>
          </div>
          <div class="about-value">
            <div class="num">02</div>
            <h4>Data that stays yours</h4>
            <p>Your hospital owns and controls its data at all times, with full export and audit access.</p>
          </div>
          <div class="about-value">
            <div class="num">03</div>
            <h4>Built to integrate</h4>
            <p>Lab analyzers, PACS, insurers and payment gateways connect without custom development.</p>
          </div>
          <div class="about-value">
            <div class="num">04</div>
            <h4>Support that answers</h4>
            <p>A dedicated implementation team stays with your hospital through go-live and beyond.</p>
          </div>
        </div>
      </div>
      <div class="about-visual">
        <img src="https://images.pexels.com/photos/7579831/pexels-photo-7579831.jpeg?auto=compress&cs=tinysrgb&w=1000" alt="Doctor consulting with a patient in a bright clinic setting">
        <div class="about-badge">
          <i class="fas fa-quote-left"></i>
          <div>
            <div class="t">Regional Medical Center, 240 beds</div>
            <div class="v">"Admissions dropped from 12 minutes to under 3."</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
GALLERY STRIP — auto-scrolling
============================================ -->
<div class="gallery-wrap">
  <div class="container">
    <div class="gallery-head">
      <div>
        <span class="eyebrow">Inside the Hospital</span>
        <h3>Where MEDLINK runs, every day.</h3>
      </div>
      <p>From the ward round to the pharmacy counter, one system follows the patient everywhere they go.</p>
    </div>
  </div>
  <div class="gallery-track">
    <div class="gallery-card"><img src="https://images.pexels.com/photos/7335565/pexels-photo-7335565.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Hospital ward with patient beds"><span class="tag">Ward Management</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/10827916/pexels-photo-10827916.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Clinical staff walking in a hospital corridor"><span class="tag">Nursing & Staff</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/6074000/pexels-photo-6074000.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Pharmacy shelves stocked with medicine"><span class="tag">Pharmacy</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/7579831/pexels-photo-7579831.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Doctor consulting a patient"><span class="tag">Patient Care</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/5049242/pexels-photo-5049242.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Empty hospital beds ready for patients"><span class="tag">Bed Management</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/33812023/pexels-photo-33812023.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Modern hospital corridor"><span class="tag">Facilities</span></div>
    <!-- duplicate set for seamless loop -->
    <div class="gallery-card"><img src="https://images.pexels.com/photos/7335565/pexels-photo-7335565.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Hospital ward with patient beds"><span class="tag">Ward Management</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/10827916/pexels-photo-10827916.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Clinical staff walking in a hospital corridor"><span class="tag">Nursing & Staff</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/6074000/pexels-photo-6074000.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Pharmacy shelves stocked with medicine"><span class="tag">Pharmacy</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/7579831/pexels-photo-7579831.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Doctor consulting a patient"><span class="tag">Patient Care</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/5049242/pexels-photo-5049242.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Empty hospital beds ready for patients"><span class="tag">Bed Management</span></div>
    <div class="gallery-card"><img src="https://images.pexels.com/photos/33812023/pexels-photo-33812023.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Modern hospital corridor"><span class="tag">Facilities</span></div>
  </div>
</div>

<!-- ============================================
MODULES — intro + category nav
============================================ -->
<section class="section" id="modules" style="padding-bottom:0;">
  <div class="container">
    <div class="section-header center">
      <span class="eyebrow">All Modules</span>
      <h2>67+ modules. One connected record.</h2>
      <p>Every department below — including AI clinical intelligence and IoT-connected devices — reads and writes to the same patient and hospital record, across as many facilities as your network runs.</p>
    </div>
  </div>
</section>

<nav class="cat-nav" id="catNav">
  <div class="container">
    <a href="#cat-intelligence">Multi-Hospital & AI</a>
    <a href="#cat-clinical">Clinical Care</a>
    <a href="#cat-inpatient">Inpatient & Critical Care</a>
    <a href="#cat-diagnostics">Diagnostics</a>
    <a href="#cat-pharmacy">Pharmacy & Supply Chain</a>
    <a href="#cat-telecare">Telemedicine & IoT</a>
    <a href="#cat-finance">Finance & Insurance</a>
    <a href="#cat-people">People & Administration</a>
    <a href="#cat-experience">Patient Experience</a>
  </div>
</nav>

<!-- ===== CATEGORY: MULTI-HOSPITAL & AI INTELLIGENCE ===== -->
<div class="mod-category" id="cat-intelligence">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-b"><i class="fas fa-diagram-project"></i></div>
        <h3>Multi-Hospital & AI Intelligence</h3>
      </div>
      <div class="cat-blurb">
        <p>
          MEDLINK is built to run one hospital or a whole regional network from the same platform.
          A Super Admin governs standardized protocols and resource sharing across every facility,
          while a layer of AI quietly reviews every chart in the background — flagging risk,
          suggesting diagnoses and predicting where the next bed shortage will happen.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-b"><i class="fas fa-sitemap"></i></div><span class="count">8 features</span></div>
        <h4>Multi-Hospital Administration</h4>
        <p>Onboard new hospitals, branches and satellite clinics under one governance layer, with standardized clinical protocols and shared resources across the network.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Hospital onboarding & configuration</li>
          <li><i class="fas fa-circle"></i>Inter-hospital staff, bed & equipment sharing</li>
          <li><i class="fas fa-circle"></i>Hospital hierarchy (main, branch, satellite)</li>
          <li><i class="fas fa-circle"></i>Central reporting across all sites</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-brain"></i></div><span class="count">4 features</span></div>
        <h4>Clinical Decision Support (AI)</h4>
        <p>Reviews the chart alongside the doctor — surfacing likely diagnoses, flagging dangerous drug interactions and recommending treatment protocols in the moment they're needed.</p>
        <ul>
          <li><i class="fas fa-circle"></i>AI-assisted diagnosis suggestions</li>
          <li><i class="fas fa-circle"></i>Real-time drug interaction alerts</li>
          <li><i class="fas fa-circle"></i>Treatment protocol recommendations</li>
          <li><i class="fas fa-circle"></i>Patient risk scoring</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-a"><i class="fas fa-chart-line"></i></div><span class="count">6 features</span></div>
        <h4>AI & Predictive Analytics</h4>
        <p>Looks ahead of the admission log — forecasting bed and drug demand, scoring readmission risk, and predicting disease outbreaks from early regional trends.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Disease prediction models</li>
          <li><i class="fas fa-circle"></i>ICU admission probability scoring</li>
          <li><i class="fas fa-circle"></i>Readmission risk detection</li>
          <li><i class="fas fa-circle"></i>Drug demand & resource forecasting</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-user-shield"></i></div><span class="count">8 features</span></div>
        <h4>User, Role & Access Management</h4>
        <p>Nine distinct user roles, from Super Admin down to Patient, each scoped to exactly the modules and records they need — nothing more.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Role-based access control (RBAC)</li>
          <li><i class="fas fa-circle"></i>Two-factor authentication (2FA)</li>
          <li><i class="fas fa-circle"></i>Delegated authority & sub-admins</li>
          <li><i class="fas fa-circle"></i>Session & login history audit logs</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-id-badge"></i></div><span class="count">4 features</span></div>
        <h4>AI Staff Attendance <span class="count" style="margin-left:6px;">Facial Recognition</span></h4>
        <p>Staff clock in and out by simply walking past a camera at the ward or department entrance — no shared badges, no buddy-punching, and no manual attendance sheets to reconcile.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Facial recognition clock-in / clock-out</li>
          <li><i class="fas fa-circle"></i>Fraud-proof attendance verification</li>
          <li><i class="fas fa-circle"></i>Real-time attendance & lateness dashboard</li>
          <li><i class="fas fa-circle"></i>Direct sync with HR payroll & rosters</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ===== CATEGORY: CLINICAL CARE ===== -->
<div class="mod-category" id="cat-clinical">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-a"><i class="fas fa-user-doctor"></i></div>
        <h3>Clinical Care</h3>
      </div>
      <div class="cat-blurb">
        <p>
          The frontline modules your registration desk, outpatient department and nursing staff
          use every hour of every day. This is where a person becomes a patient record, where
          appointments are booked and kept, and where day-to-day nursing documentation happens
          at the bedside instead of on paper that gets copied later.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-a"><i class="fas fa-users"></i></div><span class="count">7 features</span></div>
        <h4>Patient Management</h4>
        <p>The single source of truth for every patient who walks through your doors — from first registration through years of returning visits, admissions and discharges.</p>
        <ul>
          <li><i class="fas fa-circle"></i>New registration with document & ID capture</li>
          <li><i class="fas fa-circle"></i>Full electronic medical record (EMR/EHR)</li>
          <li><i class="fas fa-circle"></i>Admission (IPD) and discharge workflows</li>
          <li><i class="fas fa-circle"></i>Allergy and critical condition alerts</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-b"><i class="fas fa-calendar-check"></i></div><span class="count">5 features</span></div>
        <h4>Appointments & Telemedicine</h4>
        <p>Patients book online or at the desk, staff manage the queue in real time, and doctors can see patients remotely without leaving the same record system.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Online booking with doctor availability</li>
          <li><i class="fas fa-circle"></i>SMS & email appointment reminders</li>
          <li><i class="fas fa-circle"></i>Live queue and waiting-room display</li>
          <li><i class="fas fa-circle"></i>Video consultations with e-prescriptions</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-user-nurse"></i></div><span class="count">5 features</span></div>
        <h4>Nursing</h4>
        <p>Bedside charting that keeps every shift honest — vitals, medication rounds and handovers are logged the moment they happen, not reconstructed at end of shift.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Digital nursing notes & care plans</li>
          <li><i class="fas fa-circle"></i>Medication administration records (MAR)</li>
          <li><i class="fas fa-circle"></i>Vital signs trend charts</li>
          <li><i class="fas fa-circle"></i>Structured shift handover reports</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-truck-medical"></i></div><span class="count">4 features</span></div>
        <h4>Emergency</h4>
        <p>Built for the moments that can't wait — rapid triage, trauma protocols and ambulance dispatch are tracked from the first call to the handover at the ER doors.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Colour-coded triage scoring</li>
          <li><i class="fas fa-circle"></i>Trauma management protocols</li>
          <li><i class="fas fa-circle"></i>Ambulance dispatch & GPS tracking</li>
          <li><i class="fas fa-circle"></i>One-tap emergency treatment logging</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-e"><i class="fas fa-route"></i></div><span class="count">4 features</span></div>
        <h4>Patient Visit Management</h4>
        <p>Every reason a patient walks in — OPD consult, follow-up, IPD admission, emergency — is classified and tracked as a single visit, even when it touches five departments in one day.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Visit type classification (OPD/IPD/ER/follow-up)</li>
          <li><i class="fas fa-circle"></i>Cross-department visit routing</li>
          <li><i class="fas fa-circle"></i>Full visit history timeline per patient</li>
          <li><i class="fas fa-circle"></i>Automatic visit-based billing triggers</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ===== CATEGORY: INPATIENT & CRITICAL CARE ===== -->
<div class="mod-category alt-bg" id="cat-inpatient" style="background:var(--paper-alt);">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-c"><i class="fas fa-bed-pulse"></i></div>
        <h3>Inpatient & Critical Care</h3>
      </div>
      <div class="cat-blurb">
        <p>
          Once a patient is admitted, they move through beds, wards, theatres and sometimes
          intensive care — often within the same day. These modules keep bed occupancy, surgical
          scheduling and critical monitoring in sync so a bed is never double-booked and a
          ventilator reading is never lost between shift changes.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-a"><i class="fas fa-bed"></i></div><span class="count">5 features</span></div>
        <h4>Ward Management</h4>
        <p>A live map of every bed in the hospital — who's in it, when it was last cleaned, and where the next available bed is, without a single phone call to the ward.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Real-time bed occupancy dashboard</li>
          <li><i class="fas fa-circle"></i>Housekeeping & cleaning status</li>
          <li><i class="fas fa-circle"></i>Isolation and VIP room handling</li>
          <li><i class="fas fa-circle"></i>Inter-ward transfer requests</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-heart-pulse"></i></div><span class="count">4 features</span></div>
        <h4>ICU / CCU</h4>
        <p>Continuous, minute-by-minute charting for your most critical patients, with automatic alerts when a reading moves outside a safe range.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Ventilator & monitor integration</li>
          <li><i class="fas fa-circle"></i>Continuous critical observation charts</li>
          <li><i class="fas fa-circle"></i>ICU-specific medication protocols</li>
          <li><i class="fas fa-circle"></i>Threshold-based nurse alerts</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-b"><i class="fas fa-baby-carriage"></i></div><span class="count">5 features</span></div>
        <h4>Maternity</h4>
        <p>Follows a mother and baby from first antenatal visit through delivery and postnatal check-ups, keeping both records linked from day one.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Antenatal visit scheduling & tracking</li>
          <li><i class="fas fa-circle"></i>Live labour monitoring (partograph)</li>
          <li><i class="fas fa-circle"></i>Delivery records & complications log</li>
          <li><i class="fas fa-circle"></i>Newborn registration & vaccination</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-f"><i class="fas fa-child-reaching"></i></div><span class="count">4 features</span></div>
        <h4>Pediatrics & Child Health</h4>
        <p>Growth and vaccination charts built specifically for children, linked to school health records so a child's care history follows them, not just their chart number.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Growth monitoring charts</li>
          <li><i class="fas fa-circle"></i>Pediatric vaccination tracking</li>
          <li><i class="fas fa-circle"></i>Neonatal care records</li>
          <li><i class="fas fa-circle"></i>School health integration</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-d"><i class="fas fa-scalpel"></i></div><span class="count">6 features</span></div>
        <h4>Operation Theatre</h4>
        <p>Coordinates surgeon, anaesthetist, theatre and equipment so surgeries start on time and every implant or consumable used is captured for billing and stock.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Surgery scheduling & theatre booking</li>
          <li><i class="fas fa-circle"></i>Surgeon and team assignment</li>
          <li><i class="fas fa-circle"></i>Anaesthesia records</li>
          <li><i class="fas fa-circle"></i>Implant & consumable tracking</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ===== CATEGORY: DIAGNOSTICS ===== -->
<div class="mod-category" id="cat-diagnostics">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-e"><i class="fas fa-microscope"></i></div>
        <h3>Diagnostics</h3>
      </div>
      <div class="cat-blurb">
        <p>
          Test requests, imaging and specialist therapy sessions are where hospitals often lose the
          most time to paperwork and courier delays. These modules connect the request straight
          from the doctor's order to the lab or imaging bench, and push results back into the
          patient chart the moment they're signed off.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-flask"></i></div><span class="count">6 features</span></div>
        <h4>Laboratory</h4>
        <p>From barcode sample collection to critical value alerts, the lab module keeps pathology, microbiology and blood bank on one shared results pipeline.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Test requests & barcoded samples</li>
          <li><i class="fas fa-circle"></i>Results entry with reference ranges</li>
          <li><i class="fas fa-circle"></i>Microbiology & pathology workflows</li>
          <li><i class="fas fa-circle"></i>Blood bank inventory & cross-matching</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-x-ray"></i></div><span class="count">6 features</span></div>
        <h4>Radiology</h4>
        <p>X-ray, CT, MRI and ultrasound requests, worklists and reports live in one place, with PACS integration so images open beside the report, not in a separate viewer.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Modality worklists (X-ray, CT, MRI, US)</li>
          <li><i class="fas fa-circle"></i>Radiologist reporting templates</li>
          <li><i class="fas fa-circle"></i>PACS / DICOM image integration</li>
          <li><i class="fas fa-circle"></i>Report turnaround tracking</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-a"><i class="fas fa-water"></i></div><span class="count">4 features</span></div>
        <h4>Dialysis</h4>
        <p>Schedules machines and chairs against staff availability, and logs every session's consumables so recurring patients are never double-booked.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Machine & chair allocation</li>
          <li><i class="fas fa-circle"></i>Recurring session scheduling</li>
          <li><i class="fas fa-circle"></i>Consumables tracking per session</li>
          <li><i class="fas fa-circle"></i>Pre/post session vitals log</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-f"><i class="fas fa-dumbbell"></i></div><span class="count">3 features</span></div>
        <h4>Physiotherapy</h4>
        <p>Tracks a patient's treatment plan across multiple sessions, so progress from week one is visible to every therapist who sees them afterward.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Structured treatment plans</li>
          <li><i class="fas fa-circle"></i>Exercise & session records</li>
          <li><i class="fas fa-circle"></i>Progress tracking over time</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-b"><i class="fas fa-tooth"></i></div><span class="count">4 features</span></div>
        <h4>Dental</h4>
        <p>A dedicated odontogram and imaging workflow for dental teams, kept inside the same patient record as every other department.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Interactive odontogram charting</li>
          <li><i class="fas fa-circle"></i>Dental procedure logging</li>
          <li><i class="fas fa-circle"></i>Dental imaging storage</li>
          <li><i class="fas fa-circle"></i>Treatment plan estimates</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-droplet"></i></div><span class="count">6 features</span></div>
        <h4>Blood Bank Management</h4>
        <p>Tracks every unit of blood from donor to transfusion, with cross-matching safeguards and a fast path for emergency requests when minutes matter.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Donor registration & blood typing</li>
          <li><i class="fas fa-circle"></i>Blood inventory tracking</li>
          <li><i class="fas fa-circle"></i>Cross-matching system</li>
          <li><i class="fas fa-circle"></i>Emergency blood request routing</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-a"><i class="fas fa-syringe"></i></div><span class="count">5 features</span></div>
        <h4>Immunization Management</h4>
        <p>Keeps every child and adult vaccination on schedule, tied to vaccine stock levels so a due dose is never missed for lack of supply.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Vaccination schedules & dose tracking</li>
          <li><i class="fas fa-circle"></i>Vaccine stock integration</li>
          <li><i class="fas fa-circle"></i>National immunization reporting</li>
          <li><i class="fas fa-circle"></i>Reminder alerts for due vaccines</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ===== CATEGORY: PHARMACY & SUPPLY CHAIN ===== -->
<div class="mod-category" id="cat-pharmacy" style="background:var(--paper-alt);">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-d"><i class="fas fa-pills"></i></div>
        <h3>Pharmacy & Supply Chain</h3>
      </div>
      <div class="cat-blurb">
        <p>
          Medicine has to be in the right place, in date, and accounted for — from the moment it's
          ordered from a supplier to the moment it's handed to a patient. These modules track every
          box from procurement through central store, ward stock and the pharmacy counter, with
          batch and expiry tracking built in rather than bolted on.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-d"><i class="fas fa-prescription"></i></div><span class="count">6 features</span></div>
        <h4>Pharmacy</h4>
        <p>From e-prescription to dispensing counter, with automatic drug interaction checks and a point-of-sale that handles both walk-in and inpatient orders.</p>
        <ul>
          <li><i class="fas fa-circle"></i>E-prescription & dispensing</li>
          <li><i class="fas fa-circle"></i>Drug interaction & allergy checks</li>
          <li><i class="fas fa-circle"></i>Point-of-sale for walk-in patients</li>
          <li><i class="fas fa-circle"></i>Controlled drug register</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-warehouse"></i></div><span class="count">4 features</span></div>
        <h4>Inventory</h4>
        <p>Central store visibility across every ward and department, with batch-level tracking so nothing expires unnoticed on a back shelf.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Central store stock levels</li>
          <li><i class="fas fa-circle"></i>Inter-department stock transfers</li>
          <li><i class="fas fa-circle"></i>Scheduled stock audits</li>
          <li><i class="fas fa-circle"></i>Batch & expiry tracking</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-cart-shopping"></i></div><span class="count">5 features</span></div>
        <h4>Procurement</h4>
        <p>Turns a department's purchase request into an approved order, a supplier quote and a goods-received note — all traceable back to who asked for what.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Purchase requests & approvals</li>
          <li><i class="fas fa-circle"></i>Request for quotation (RFQ)</li>
          <li><i class="fas fa-circle"></i>Purchase order generation</li>
          <li><i class="fas fa-circle"></i>Goods receiving & supplier records</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-e"><i class="fas fa-toolbox"></i></div><span class="count">6 features</span></div>
        <h4>Asset & Biomedical Engineering</h4>
        <p>Tracks every piece of equipment from ventilators to autoclaves — its maintenance schedule, calibration due date and which department it currently belongs to.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Equipment lifecycle & depreciation tracking</li>
          <li><i class="fas fa-circle"></i>Asset allocation per department</li>
          <li><i class="fas fa-circle"></i>Maintenance schedules & repair logs</li>
          <li><i class="fas fa-circle"></i>Biomedical device calibration tracking</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ===== CATEGORY: TELEMEDICINE & CONNECTED CARE ===== -->
<div class="mod-category" id="cat-telecare" style="background:var(--paper-alt);">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-c"><i class="fas fa-satellite-dish"></i></div>
        <h3>Telemedicine & Connected Care</h3>
      </div>
      <div class="cat-blurb">
        <p>
          Care doesn't stop at the hospital wall. These modules extend MEDLINK out to patients at
          home and specialists at other facilities — through video consultations, connected
          wearables, and a referral system that closes the loop instead of losing the patient
          between two hospitals.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-video"></i></div><span class="count">4 features</span></div>
        <h4>Telemedicine & Virtual Care</h4>
        <p>Video consultations, remote prescriptions and chat-based follow-ups, all logged into the same patient record as an in-person visit.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Video consultations</li>
          <li><i class="fas fa-circle"></i>Remote prescription issuance</li>
          <li><i class="fas fa-circle"></i>Virtual follow-ups</li>
          <li><i class="fas fa-circle"></i>Chat-based consultations</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-a"><i class="fas fa-wave-square"></i></div><span class="count">4 features</span></div>
        <h4>IoT & Smart Device Integration <span class="count" style="margin-left:6px;">IoT</span></h4>
        <p>Wearables and bedside monitors stream vitals directly into the chart, so a deteriorating reading reaches a nurse before the next scheduled round.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Smart wearable integration</li>
          <li><i class="fas fa-circle"></i>ICU monitoring device connections</li>
          <li><i class="fas fa-circle"></i>Remote patient monitoring</li>
          <li><i class="fas fa-circle"></i>Real-time vital sign streaming</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-heart-circle-check"></i></div><span class="count">Mediwatch</span></div>
        <h4>Remote Patient Monitoring <span class="count" style="margin-left:6px;">Mediwatch</span></h4>
        <p>MEDLINK's own remote monitoring programme — chronic disease and post-discharge patients wear a connected device at home, and their care team watches for warning signs long before the next scheduled appointment.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Connected wearable & home monitoring kits</li>
          <li><i class="fas fa-circle"></i>Continuous vital sign transmission from home</li>
          <li><i class="fas fa-circle"></i>Automatic abnormal-reading alerts to the care team</li>
          <li><i class="fas fa-circle"></i>Chronic disease & post-discharge monitoring programmes</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-f"><i class="fas fa-building-shield"></i></div><span class="count">5 features</span></div>
        <h4>Smart Facility & Equipment Monitoring <span class="count" style="margin-left:6px;">IoT</span></h4>
        <p>Sensors placed across buildings and equipment watch the hospital itself — catching a failing chiller, a water leak or a stuffy ward before it becomes an incident report.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Temperature & humidity monitoring (cold rooms, wards, pharmacy)</li>
          <li><i class="fas fa-circle"></i>Air quality & lighting sensors</li>
          <li><i class="fas fa-circle"></i>Water leakage detection & alerts</li>
          <li><i class="fas fa-circle"></i>Predictive maintenance for buildings & equipment</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-right-left"></i></div><span class="count">4 features</span></div>
        <h4>Referral & Counter-Referral</h4>
        <p>Sends a patient to a specialist or partner hospital with their full history attached, and brings the outcome back to the referring doctor automatically.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Inter-hospital referrals</li>
          <li><i class="fas fa-circle"></i>Specialist referral routing</li>
          <li><i class="fas fa-circle"></i>Feedback loop from receiving hospital</li>
          <li><i class="fas fa-circle"></i>Referral outcome analytics</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-b"><i class="fas fa-bell"></i></div><span class="count">3 features</span></div>
        <h4>Communication & Notification System</h4>
        <p>One gateway for every SMS, email and WhatsApp message the hospital sends — appointment reminders, emergency alerts and results notifications alike.</p>
        <ul>
          <li><i class="fas fa-circle"></i>SMS / email / WhatsApp integration</li>
          <li><i class="fas fa-circle"></i>Emergency broadcast notifications</li>
          <li><i class="fas fa-circle"></i>Automated appointment reminders</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ===== CATEGORY: FINANCE & INSURANCE ===== -->
<div class="mod-category" id="cat-finance">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-b"><i class="fas fa-coins"></i></div>
        <h3>Finance & Insurance</h3>
      </div>
      <div class="cat-blurb">
        <p>
          Every clinical action in MEDLINK — a test, a bed-night, a dispensed medicine — quietly
          becomes a billable line item behind the scenes, so your finance team isn't chasing charge
          sheets from six departments at month end. Insurance claims and patient payments flow
          through the same ledger, reconciled automatically.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-b"><i class="fas fa-coins"></i></div><span class="count">8 features</span></div>
        <h4>Finance</h4>
        <p>A full chart of accounts and general ledger built around hospital billing, so clinical charges post automatically instead of being re-keyed by hand.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Chart of accounts & general ledger</li>
          <li><i class="fas fa-circle"></i>Automated patient invoicing</li>
          <li><i class="fas fa-circle"></i>Cash & bank management</li>
          <li><i class="fas fa-circle"></i>Financial statements & reports</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-a"><i class="fas fa-credit-card"></i></div><span class="count">4 features</span></div>
        <h4>ePayment</h4>
        <p>Accepts cash, card, mobile money and bank transfer against the same invoice, and reconciles every channel automatically against the ledger each night.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Live payment dashboard</li>
          <li><i class="fas fa-circle"></i>Multi-channel transaction log</li>
          <li><i class="fas fa-circle"></i>Card, mobile money & bank support</li>
          <li><i class="fas fa-circle"></i>Automated daily reconciliation</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-shield-heart"></i></div><span class="count">5 features</span></div>
        <h4>Insurance</h4>
        <p>Verifies membership at the front desk, routes pre-authorisation requests to the insurer, and tracks every claim from submission through settlement.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Insurance company & scheme setup</li>
          <li><i class="fas fa-circle"></i>Real-time membership verification</li>
          <li><i class="fas fa-circle"></i>Pre-authorisation requests</li>
          <li><i class="fas fa-circle"></i>Claims submission & tracking</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ===== CATEGORY: PEOPLE & ADMINISTRATION ===== -->
<div class="mod-category" id="cat-people" style="background:var(--paper-alt);">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-f"><i class="fas fa-user-tie"></i></div>
        <h3>People & Administration</h3>
      </div>
      <div class="cat-blurb">
        <p>
          Behind every ward is a roster, a payroll run, and a set of permissions deciding who can
          see what. These modules manage the hospital as an employer and an organisation — staff
          records, department structure, and the access controls that keep sensitive patient data
          visible only to the right people.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-f"><i class="fas fa-user-tie"></i></div><span class="count">7 features</span></div>
        <h4>Human Resources</h4>
        <p>Staff records, rosters and payroll for every role in the hospital, from consultants to housekeeping, with leave and performance tracked in the same place.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Employee records & credentialing</li>
          <li><i class="fas fa-circle"></i>Shift rosters & attendance</li>
          <li><i class="fas fa-circle"></i>Payroll processing</li>
          <li><i class="fas fa-circle"></i>Leave & performance management</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-building"></i></div><span class="count">17 units</span></div>
        <h4>Departments</h4>
        <p>Configures the real structure of your hospital — OPD, Emergency, ICU, NICU, Paediatrics, Surgery, Cardiology, Neurology and more — as connected units, not silos.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Configurable department hierarchy</li>
          <li><i class="fas fa-circle"></i>Department-level staffing & budgets</li>
          <li><i class="fas fa-circle"></i>Cross-department referral routing</li>
          <li><i class="fas fa-circle"></i>Specialty-specific workflows</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-e"><i class="fas fa-gear"></i></div><span class="count">7 features</span></div>
        <h4>System Administration</h4>
        <p>Role-based access control down to the field level, plus a full audit trail so every view, edit and export of a patient record is accountable.</p>
        <ul>
          <li><i class="fas fa-circle"></i>User & role management</li>
          <li><i class="fas fa-circle"></i>Granular permission controls</li>
          <li><i class="fas fa-circle"></i>Hospital-wide configuration settings</li>
          <li><i class="fas fa-circle"></i>Full audit logs</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-triangle-exclamation"></i></div><span class="count">6 features</span></div>
        <h4>Quality, Risk & Compliance</h4>
        <p>Tracks incidents, audits and accreditation readiness in one place, so quality reporting is a byproduct of daily work instead of a scramble before an inspection.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Incident reporting & risk classification</li>
          <li><i class="fas fa-circle"></i>Audit tracking & accreditation readiness</li>
          <li><i class="fas fa-circle"></i>Infection prevention & outbreak monitoring</li>
          <li><i class="fas fa-circle"></i>Occupational health & safety compliance</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ===== CATEGORY: PATIENT EXPERIENCE ===== -->
<div class="mod-category" id="cat-experience">
  <div class="container">
    <div class="mod-category-head">
      <div>
        <div class="cat-icon ic-a"><i class="fas fa-chart-line"></i></div>
        <h3>Patient Experience & Insight</h3>
      </div>
      <div class="cat-blurb">
        <p>
          The final layer connects the hospital back out to the people it serves, and turns
          everything happening inside into decisions your leadership team can act on — patient
          self-service on one side, executive dashboards on the other, both drawing from the same
          live data.
        </p>
      </div>
    </div>
    <div class="mod-grid">
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-a"><i class="fas fa-circle-user"></i></div><span class="count">1 portal</span></div>
        <h4>Patient Portal</h4>
        <p>Patients view their own records, book appointments, pay bills and download lab reports and prescriptions — without calling the front desk.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Personal medical record access</li>
          <li><i class="fas fa-circle"></i>Self-service appointment booking</li>
          <li><i class="fas fa-circle"></i>Online bill payment</li>
          <li><i class="fas fa-circle"></i>Lab reports & prescription downloads</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-c"><i class="fas fa-chart-bar"></i></div><span class="count">6 features</span></div>
        <h4>Reports & Business Intelligence</h4>
        <p>Executive dashboards blend clinical and financial KPIs, with predictive analytics that flag trends — like an upcoming bed shortage — before they become a crisis.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Executive summary dashboard</li>
          <li><i class="fas fa-circle"></i>Clinical & financial KPIs</li>
          <li><i class="fas fa-circle"></i>Predictive analytics & forecasting</li>
          <li><i class="fas fa-circle"></i>Custom & scheduled reports</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-g"><i class="fas fa-plug"></i></div><span class="count">Open API</span></div>
        <h4>Integrations</h4>
        <p>Connects to laboratory analyzers, PACS/DICOM imaging systems, insurance platforms and payment gateways through an open, documented API.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Laboratory analyzer connections</li>
          <li><i class="fas fa-circle"></i>PACS / DICOM imaging integration</li>
          <li><i class="fas fa-circle"></i>Insurance platform connectors</li>
          <li><i class="fas fa-circle"></i>Payment gateway integrations</li>
        </ul>
      </div>
      <div class="mod-card">
        <div class="mod-card-top"><div class="mini-icon ic-f"><i class="fas fa-earth-africa"></i></div><span class="count">5 features</span></div>
        <h4>Public Health & Disease Surveillance</h4>
        <p>Rolls patient-level data up into regional and national reporting — outbreak heat maps, vaccination coverage and notifiable disease submissions aligned to Ministry of Health formats.</p>
        <ul>
          <li><i class="fas fa-circle"></i>Disease outbreak heat maps</li>
          <li><i class="fas fa-circle"></i>Notifiable disease tracking</li>
          <li><i class="fas fa-circle"></i>Vaccination coverage by region</li>
          <li><i class="fas fa-circle"></i>Ministry of Health reporting exports</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- ============================================
AI & IoT — SPOTLIGHT SECTION
============================================ -->
<section class="ai-spotlight" id="ai-iot">
  <div class="container">
    <div class="spotlight-head">
      <span class="eyebrow" style="color:var(--gold-300);">Intelligence Layer</span>
      <h3>AI and IoT, built into the workflow — not bolted on.</h3>
      <p>Every module above feeds two things running quietly underneath: an AI layer that reads the data as it arrives, and an IoT layer that gets it there in the first place.</p>
    </div>
    <div class="spotlight-grid">
      <div class="spotlight-card">
        <div class="spotlight-icon coral"><i class="fas fa-brain"></i></div>
        <h4>AI Clinical Intelligence</h4>
        <p>
          MEDLINK's AI layer sits quietly behind every chart, comparing each new result and vital
          sign against thousands of similar cases. It doesn't replace clinical judgement — it
          narrows the search, surfaces the outlier, and gives a doctor or nurse a head start on the
          decision they were already about to make.
        </p>
        <ul>
          <li><i class="fas fa-circle"></i>Patient risk scoring & early-deterioration alerts</li>
          <li><i class="fas fa-circle"></i>Disease prediction models (diabetes, hypertension, and more)</li>
          <li><i class="fas fa-circle"></i>ICU admission probability & readmission risk detection</li>
          <li><i class="fas fa-circle"></i>Drug demand forecasting & outbreak prediction</li>
          <li><i class="fas fa-circle"></i>Facial recognition staff attendance & access control</li>
        </ul>
        <div class="spotlight-insight">
          <span class="spotlight-insight-label">AI Insight Panel</span>
          <p>"3 patients on Ward B show early signs of deterioration in the next 24–48 hours."</p>
        </div>
      </div>
      <div class="spotlight-card">
        <div class="spotlight-icon sage"><i class="fas fa-wave-square"></i></div>
        <h4>IoT & Smart Device Integration</h4>
        <p>
          Bedside monitors, ventilators and wearable sensors connect straight into MEDLINK, turning
          a reading on a screen into a data point in the patient's chart. Nothing needs to be
          copied down by hand, and nothing waits for the next round to be noticed.
        </p>
        <ul>
          <li><i class="fas fa-circle"></i>Smart wearables for remote patient monitoring</li>
          <li><i class="fas fa-circle"></i>Direct ICU monitor & ventilator integration</li>
          <li><i class="fas fa-circle"></i>Real-time vital sign streaming into the EMR</li>
          <li><i class="fas fa-circle"></i>Threshold-based alerts sent straight to nursing staff</li>
          <li><i class="fas fa-circle"></i>Mediwatch home monitoring for chronic & post-discharge patients</li>
          <li><i class="fas fa-circle"></i>Building sensors for temperature, humidity, air quality & leaks</li>
        </ul>
        <div class="spotlight-insight">
          <span class="spotlight-insight-label">Live Device Feed</span>
          <p>"ICU Bed 4 — SpO₂ 91% and falling. Nurse Uwimana notified 0.4s ago."</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
HOW IT WORKS
============================================ -->
<section class="section alt" id="how-it-works">
  <div class="container">
    <div class="section-header">
      <span class="eyebrow">How It Works</span>
      <h2>From first call to go-live in five stages.</h2>
      <p>We've done this enough times to know where hospital implementations usually go wrong — so we built the process to catch it early.</p>
    </div>
    <div class="how-steps">
      <div class="how-step">
        <div class="step-num">01</div>
        <div>
          <h4>Discovery & mapping</h4>
          <p>Our team spends time on your wards, not just in a boardroom, mapping how registration, admissions and billing actually flow today — including the workarounds.</p>
        </div>
        <div class="step-visual"><img src="https://images.pexels.com/photos/7579831/pexels-photo-7579831.jpeg?auto=compress&cs=tinysrgb&w=700" alt="Consultation and discovery session"></div>
      </div>
      <div class="how-step">
        <div class="step-num">02</div>
        <div>
          <h4>Configuration</h4>
          <p>Departments, roles, bed layouts, billing rules and insurance schemes are configured to match your hospital — not the other way around.</p>
        </div>
        <div class="step-visual"><img src="https://images.pexels.com/photos/236380/pexels-photo-236380.jpeg?auto=compress&cs=tinysrgb&w=700" alt="Hospital ward configuration"></div>
      </div>
      <div class="how-step">
        <div class="step-num">03</div>
        <div>
          <h4>Data migration</h4>
          <p>Existing patient records, stock levels and financial history are migrated and verified in a staging environment before anyone touches the live system.</p>
        </div>
        <div class="step-visual"><img src="https://images.pexels.com/photos/6074000/pexels-photo-6074000.jpeg?auto=compress&cs=tinysrgb&w=700" alt="Pharmacy inventory being catalogued"></div>
      </div>
      <div class="how-step">
        <div class="step-num">04</div>
        <div>
          <h4>Staff training</h4>
          <p>Role-specific training for reception, nursing, pharmacy and finance teams — hands-on, on your hardware, before the switch is flipped.</p>
        </div>
        <div class="step-visual"><img src="https://images.pexels.com/photos/10827916/pexels-photo-10827916.jpeg?auto=compress&cs=tinysrgb&w=700" alt="Clinical staff training session"></div>
      </div>
      <div class="how-step">
        <div class="step-num">05</div>
        <div>
          <h4>Go-live & ongoing support</h4>
          <p>An implementation specialist is on-site or on-call through your first weeks live, then hands off to our 24/7 support desk for the long run.</p>
        </div>
        <div class="step-visual"><img src="https://images.pexels.com/photos/11660582/pexels-photo-11660582.jpeg?auto=compress&cs=tinysrgb&w=700" alt="Hospital beds ready for go-live"></div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
FEATURES / WHY CHOOSE US
============================================ -->
<section class="section">
  <div class="container">
    <div class="section-header center">
      <span class="eyebrow">Why Hospitals Choose MEDLINK</span>
      <h2>The details that matter at 3am.</h2>
      <p>Everything you'd expect from an enterprise system, built around the reality of running a hospital around the clock.</p>
    </div>
    <div class="features-grid">
      <div class="feature-item">
        <div class="icon"><i class="fas fa-shield-halved"></i></div>
        <h4>Security & Compliance</h4>
        <p>Role-based access, full audit trails and encryption at rest and in transit, aligned with healthcare data protection standards.</p>
      </div>
      <div class="feature-item">
        <div class="icon"><i class="fas fa-arrows-rotate"></i></div>
        <h4>Real-Time Sync</h4>
        <p>Every module reads and writes to the same record — a bed status change in the ward shows up instantly at the front desk.</p>
      </div>
      <div class="feature-item">
        <div class="icon"><i class="fas fa-mobile-screen"></i></div>
        <h4>Works on Any Device</h4>
        <p>Fully responsive from the nursing station tablet to the finance office desktop, with no separate mobile app to maintain.</p>
      </div>
      <div class="feature-item">
        <div class="icon"><i class="fas fa-chart-line"></i></div>
        <h4>Advanced Analytics</h4>
        <p>Executive dashboards with predictive analytics and live KPIs across every clinical and financial department.</p>
      </div>
      <div class="feature-item">
        <div class="icon"><i class="fas fa-plug-circle-bolt"></i></div>
        <h4>Open Integrations</h4>
        <p>Lab analyzers, PACS, insurance platforms and payment gateways connect through documented, supported APIs.</p>
      </div>
      <div class="feature-item">
        <div class="icon"><i class="fas fa-headset"></i></div>
        <h4>24/7 Human Support</h4>
        <p>A dedicated support desk that knows hospitals don't stop at 5pm, staffed around the clock, every day of the year.</p>
      </div>
    </div>
  </div>
</section>

<div class="vitals-divider">
  <svg viewBox="0 0 1200 34" preserveAspectRatio="none">
    <path class="vitals-path" d="M0,17 L280,17 L305,4 L330,30 L355,17 L420,17 L445,10 L470,24 L495,17 L1200,17"/>
  </svg>
</div>

<!-- ============================================
CTA
============================================ -->
<section class="cta-section">
  <div class="container">
    <div class="cta-content">
      <span class="eyebrow" style="color:var(--gold-300);">Ready When You Are</span>
      <h2>Give your hospitals one system they can trust.</h2>
      <p>Join hospital networks of every size running their wards, pharmacy, finance, AI and IoT devices on MEDLINK.</p>
      <div class="btn-group">
        <a href="login.php" class="btn-primary"><i class="fas fa-right-to-bracket"></i> Login</a>
        <a href="#contact" class="btn-secondary"><i class="fas fa-calendar"></i> Schedule a Demo</a>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
CONTACT
============================================ -->
<section class="section" id="contact">
  <div class="container">
    <div class="section-header center">
      <span class="eyebrow">Contact Us</span>
      <h2>Let's talk about your hospital.</h2>
      <p>Tell us a little about your facility and one of our implementation specialists will get back to you within one business day.</p>
    </div>

    <div class="contact-wrap">
      <div class="contact-info">
        <h3>Get in touch</h3>
        <p>Whether you're running a 30-bed clinic or a 500-bed regional hospital, we'll walk you through what an implementation would look like for your team.</p>

        <div class="contact-detail">
          <i class="fas fa-phone"></i>
          <div><div class="t">Call Us</div><div class="v">+250 788 000 000</div></div>
        </div>
        <div class="contact-detail">
          <i class="fas fa-envelope"></i>
          <div><div class="t">Email</div><div class="v">hello@medlink.health</div></div>
        </div>
        <div class="contact-detail">
          <i class="fas fa-location-dot"></i>
          <div><div class="t">Office</div><div class="v">KG 7 Ave, Kigali, Rwanda</div></div>
        </div>
        <div class="contact-detail">
          <i class="fas fa-clock"></i>
          <div><div class="t">Support</div><div class="v">Available 24 hours, every day</div></div>
        </div>

        <div class="contact-social">
          <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
      </div>

      <div class="contact-form">
        <form id="contactForm">
          <h4>Send us a message</h4>
          <div class="form-row">
            <div class="form-group">
              <label for="fname">Full Name</label>
              <input type="text" id="fname" name="fname" placeholder="Jane Uwase" required>
            </div>
            <div class="form-group">
              <label for="femail">Work Email</label>
              <input type="email" id="femail" name="femail" placeholder="jane@yourhospital.com" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="fhospital">Hospital / Facility</label>
              <input type="text" id="fhospital" name="fhospital" placeholder="Kigali Regional Hospital">
            </div>
            <div class="form-group">
              <label for="fsize">Facility Size</label>
              <select id="fsize" name="fsize">
                <option>Under 50 beds</option>
                <option>50 – 150 beds</option>
                <option>150 – 400 beds</option>
                <option>400+ beds</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="fmessage">Message</label>
            <textarea id="fmessage" name="fmessage" placeholder="Tell us about your current system and what you're looking to solve..." required></textarea>
          </div>
          <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Message</button>
          <p class="form-note">We typically respond within one business day.</p>
        </form>

        <div class="form-success" id="formSuccess">
          <i class="fas fa-circle-check"></i>
          <h4>Message sent</h4>
          <p>Thanks for reaching out — a member of our team will be in touch shortly.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
FOOTER
============================================ -->
<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-col">
        <div class="footer-brand">
          <div class="brand-mark"><i class="fas fa-diagram-project"></i></div>
          MED<span style="color:var(--sage-300);">LINK</span>
        </div>
        <p>A multi-hospital management platform — connecting patient care, AI clinical intelligence, IoT devices, pharmacy and finance across one hospital or an entire network.</p>
      </div>
      <div class="footer-links">
        <h4>Modules</h4>
        <ul>
          <li><a href="#cat-clinical">Patient Management</a></li>
          <li><a href="#cat-finance">Finance & ePayment</a></li>
          <li><a href="#cat-diagnostics">Laboratory</a></li>
          <li><a href="#cat-pharmacy">Pharmacy</a></li>
        </ul>
      </div>
      <div class="footer-links">
        <h4>Company</h4>
        <ul>
          <li><a href="#about">About Us</a></li>
          <li><a href="#">Careers</a></li>
          <li><a href="#">Blog</a></li>
          <li><a href="#contact">Contact</a></li>
        </ul>
      </div>
      <div class="footer-links">
        <h4>Support</h4>
        <ul>
          <li><a href="#">Help Center</a></li>
          <li><a href="#">Documentation</a></li>
          <li><a href="#">API Reference</a></li>
          <li><a href="#">Privacy Policy</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; 2026 MEDLINK. All rights reserved.</span>
      <div class="footer-social">
        <a href="login.php" style="text-decoration:underline;">Login to your hospital</a>
      </div>
    </div>
  </div>
</footer>

<!-- ============================================
SCRIPTS
============================================ -->
<script>
  // Navbar scroll effect
  const navbar = document.getElementById('navbar');
  window.addEventListener('scroll', function(){
    navbar.classList.toggle('scrolled', window.scrollY > 40);
  });

  // Mobile menu toggle
  const toggleBtn = document.getElementById('navbarToggle');
  const navLinks = document.getElementById('navbarLinks');
  toggleBtn.addEventListener('click', () => navLinks.classList.toggle('open'));
  document.querySelectorAll('.navbar-links a').forEach(link=>{
    link.addEventListener('click', () => navLinks.classList.remove('open'));
  });

  // Hero image slider
  const slides = document.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('.slide-dots button');
  const captionEl = document.getElementById('slideCaption');
  const captions = ['01 — Hospital Corridor','02 — Hospital Ward','03 — Clinical Staff','04 — Pharmacy'];
  let current = 0;
  function goToSlide(i){
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = i;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
    if(captionEl) captionEl.textContent = captions[current];
  }
  dots.forEach((dot,i)=> dot.addEventListener('click', ()=> goToSlide(i)));
  setInterval(()=>{ goToSlide((current+1) % slides.length); }, 5500);

  // Category nav active state on scroll
  const catSections = document.querySelectorAll('.mod-category');
  const catLinks = document.querySelectorAll('.cat-nav a');
  const catObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        catLinks.forEach(l=>l.classList.remove('is-active'));
        const link = document.querySelector('.cat-nav a[href="#'+entry.target.id+'"]');
        if(link) link.classList.add('is-active');
      }
    });
  }, {rootMargin:'-40% 0px -50% 0px'});
  catSections.forEach(sec=> catObserver.observe(sec));

  // Counter animation
  function animateCounter(el, target, suffix){
    let current = 0;
    const increment = Math.max(1, Math.ceil(target/50));
    const timer = setInterval(()=>{
      current += increment;
      if(current >= target){ current = target; clearInterval(timer); }
      el.textContent = current.toLocaleString() + suffix;
    }, 25);
  }
  const statObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        entry.target.querySelectorAll('.number').forEach(num=>{
          const raw = num.textContent.trim();
          const match = raw.match(/^([\d.,]+)(.*)$/);
          if(match){
            const value = parseInt(match[1].replace(/,/g,''));
            if(!isNaN(value)){ animateCounter(num, value, match[2]); }
          }
        });
        statObserver.unobserve(entry.target);
      }
    });
  }, {threshold:0.4});
  document.querySelectorAll('.hero-stats, .stats-strip').forEach(el=> statObserver.observe(el));

  // Contact form submit (demo — no backend wired here)
  const form = document.getElementById('contactForm');
  const success = document.getElementById('formSuccess');
  form.addEventListener('submit', function(e){
    e.preventDefault();
    form.style.display = 'none';
    success.classList.add('show');
  });
</script>

</body>
</html>