<?php
$_univConfig = (new Universite())->getConfigurationUniversite();
$_logoPath   = !empty($_univConfig['logo']) ? htmlspecialchars($_univConfig['logo']) : 'assets/img/favicon.png';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Landela-Cours — E-Suivi des Cours</title>
<link rel="icon" href="<?= $_logoPath ?>">
<link rel="apple-touch-icon" href="<?= $_logoPath ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/lucide@0.468.0/dist/umd/lucide.min.js"
        onerror="this.onerror=null;this.src='assets/vendor/lucide.min.js'"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --blue-deep  : #0f2a4a;
    --blue-main  : #1e54d0;
    --blue-light : #3b6ff5;
    --blue-soft  : #e8efff;
    --green      : #12b76a;
    --amber      : #f79009;
    --gray-50    : #f9fafb;
    --gray-100   : #f3f4f6;
    --gray-200   : #e5e7eb;
    --gray-400   : #9ca3af;
    --gray-600   : #4b5563;
    --gray-800   : #1f2937;
    --radius-sm  : 8px;
    --radius-md  : 14px;
    --radius-lg  : 22px;
    --shadow-sm  : 0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
    --shadow-md  : 0 4px 16px rgba(0,0,0,.1);
    --shadow-lg  : 0 12px 40px rgba(0,0,0,.14);
}

html { scroll-behavior: smooth; }

/* ════════════════════════════════════════
   BARRE RDC
════════════════════════════════════════ */
.rdc-bar {
    position: fixed; top: 0; left: 0; right: 0;
    z-index: 200;
    height: 5px;
    display: flex;
}
.rdc-bar span {
    flex: 1;
    display: block;
}
.rdc-bar .rdc-blue  { background: #007FFF; }
.rdc-bar .rdc-red   { background: #CE1126; }
.rdc-bar .rdc-yellow{ background: #F7D618; }

body {
    font-family: 'Inter', system-ui, sans-serif;
    color: var(--gray-800);
    background: #fff;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
}

/* ════════════════════════════════════════
   NAVBAR
════════════════════════════════════════ */
.navbar {
    position: fixed; top: 5px; left: 0; right: 0;
    z-index: 100;
    padding: 0 2rem;
    height: 68px;
    display: flex; align-items: center; justify-content: space-between;
    background: rgba(255,255,255,.88);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid rgba(0,0,0,.07);
    transition: box-shadow .2s;
}
.navbar.scrolled { box-shadow: var(--shadow-md); }

.navbar-brand {
    display: flex; align-items: center; gap: .6rem;
    text-decoration: none;
}
.brand-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--blue-main), var(--blue-light));
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
}
.brand-name {
    font-size: 1.2rem; font-weight: 800; color: var(--blue-deep);
    letter-spacing: -.02em;
}
.brand-name span { color: var(--blue-main); }

.navbar-actions { display: flex; align-items: center; gap: .75rem; }
.btn-ghost {
    padding: .45rem 1rem; border-radius: var(--radius-sm);
    font-size: .85rem; font-weight: 500; color: var(--gray-600);
    text-decoration: none; border: none; background: none; cursor: pointer;
    transition: color .15s, background .15s;
}
.btn-ghost:hover { color: var(--blue-main); background: var(--blue-soft); }

.btn-primary {
    padding: .48rem 1.2rem; border-radius: var(--radius-sm);
    font-size: .85rem; font-weight: 600; color: #fff;
    background: linear-gradient(135deg, var(--blue-main), var(--blue-light));
    text-decoration: none; border: none; cursor: pointer;
    box-shadow: 0 2px 8px rgba(30,84,208,.35);
    transition: transform .13s, box-shadow .13s, filter .13s;
    display: inline-flex; align-items: center; gap: .4rem;
}
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(30,84,208,.4); filter: brightness(1.05); }
.btn-primary:active { transform: translateY(0); }

/* ════════════════════════════════════════
   HERO
════════════════════════════════════════ */
.hero {
    min-height: 100vh;
    padding-top: 68px;
    display: flex; align-items: center;
    background:
        radial-gradient(ellipse 80% 60% at 60% 40%, rgba(59,111,245,.12) 0%, transparent 70%),
        radial-gradient(ellipse 50% 40% at 20% 80%, rgba(18,183,106,.07) 0%, transparent 60%),
        #fff;
    overflow: hidden;
    position: relative;
}

/* Grille décorative */
.hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(30,84,208,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(30,84,208,.04) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}

.hero-inner {
    max-width: 1200px; margin: 0 auto; padding: 5rem 2rem;
    display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center;
}

.hero-badge {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .3rem .85rem;
    border-radius: 20px;
    background: var(--blue-soft);
    border: 1px solid rgba(30,84,208,.2);
    color: var(--blue-main);
    font-size: .75rem; font-weight: 600; letter-spacing: .04em;
    text-transform: uppercase;
    margin-bottom: 1.25rem;
}

.hero-title {
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 900;
    line-height: 1.1;
    letter-spacing: -.03em;
    color: var(--blue-deep);
    margin-bottom: 1.25rem;
}
.hero-title .highlight {
    background: linear-gradient(135deg, var(--blue-main), var(--blue-light));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-sub {
    font-size: 1.05rem; color: var(--gray-600); line-height: 1.7;
    margin-bottom: 2rem; max-width: 480px;
}

.hero-cta { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.btn-primary-lg {
    padding: .8rem 1.8rem; border-radius: var(--radius-sm);
    font-size: .95rem; font-weight: 700; color: #fff;
    background: linear-gradient(135deg, var(--blue-main), var(--blue-light));
    text-decoration: none; border: none; cursor: pointer;
    box-shadow: 0 4px 18px rgba(30,84,208,.4);
    transition: transform .13s, box-shadow .13s;
    display: inline-flex; align-items: center; gap: .5rem;
}
.btn-primary-lg:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(30,84,208,.45); }

.hero-note {
    font-size: .8rem; color: var(--gray-400);
    display: flex; align-items: center; gap: .3rem;
}

/* Hero screenshot */
.hero-visual {
    position: relative;
    display: flex; justify-content: center; align-items: center;
}
.hero-screenshot {
    width: 100%; max-width: 780px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg), 0 0 0 1px rgba(0,0,0,.07);
    overflow: hidden;
    animation: floatY 5s ease-in-out infinite;
}
@keyframes floatY {
    0%, 100% { transform: translateY(0); }
    50%       { transform: translateY(-10px); }
}
.hero-img {
    width: 100%; height: auto;
    display: block;
}
.chip {
    padding: .15em .6em; border-radius: 20px; font-size: .65rem; font-weight: 600;
}
.chip-green  { background: #d1fae5; color: #065f46; }
.chip-amber  { background: #fef3c7; color: #92400e; }
.chip-gray   { background: var(--gray-200); color: var(--gray-600); }

/* ════════════════════════════════════════
   STATS STRIP
════════════════════════════════════════ */
.stats-strip {
    background: linear-gradient(135deg, var(--blue-deep), var(--blue-main));
    padding: 2.5rem 2rem;
    position: relative;
}
.stats-strip::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #007FFF 33.3%, #CE1126 33.3% 66.6%, #F7D618 66.6%);
}
.stats-inner {
    max-width: 900px; margin: 0 auto;
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 2rem; text-align: center;
}
.stat-val {
    font-size: 2.2rem; font-weight: 900; color: #fff; letter-spacing: -.03em;
    line-height: 1;
}
.stat-lbl { font-size: .82rem; color: rgba(255,255,255,.65); margin-top: .35rem; font-weight: 500; }

/* ════════════════════════════════════════
   SECTION GÉNÉRIQUE
════════════════════════════════════════ */
section { padding: 5rem 2rem; }
.section-inner { max-width: 1100px; margin: 0 auto; }
.section-tag {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    color: var(--blue-main); margin-bottom: .75rem;
}
.section-title {
    font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 800;
    letter-spacing: -.025em; color: var(--blue-deep);
    margin-bottom: .75rem; line-height: 1.2;
    position: relative; display: inline-block;
}
/* Soulignement tricolore RDC sous les titres de section */
.text-center .section-title::after {
    content: '';
    display: block;
    margin: .55rem auto 0;
    width: 54px; height: 3px;
    border-radius: 2px;
    background: linear-gradient(90deg, #007FFF 33.3%, #CE1126 33.3% 66.6%, #F7D618 66.6%);
}
.section-sub {
    font-size: 1rem; color: var(--gray-600); max-width: 560px; line-height: 1.7;
}
.text-center { text-align: center; }
.text-center .section-sub { margin: 0 auto; }

/* ════════════════════════════════════════
   COMMENT ÇA MARCHE — STEPS
════════════════════════════════════════ */
.steps-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;
    margin-top: 3.5rem; position: relative;
}
.steps-grid::before {
    content: '';
    position: absolute;
    top: 38px; left: calc(16.6% + 1.5rem); right: calc(16.6% + 1.5rem);
    height: 2px;
    background: linear-gradient(90deg, var(--blue-soft) 0%, var(--blue-main) 50%, var(--blue-soft) 100%);
    z-index: 0;
}
.step-card {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    padding: 1.75rem 1.5rem;
    position: relative; z-index: 1;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s, border-color .2s;
}
/* Accent tricolore RDC en haut de chaque step-card */
.step-card::after {
    content: '';
    position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #007FFF 33.3%, #CE1126 33.3% 66.6%, #F7D618 66.6%);
}
.step-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-md);
    border-color: rgba(30,84,208,.18);
}
.step-num {
    width: 48px; height: 48px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: .85rem; font-weight: 800;
    margin-bottom: 1.25rem;
}
/* Numéros aux couleurs du drapeau RDC */
.step-card:nth-child(1) .step-num { background: linear-gradient(135deg, #0066CC, #007FFF); box-shadow: 0 4px 12px rgba(0,127,255,.35); }
.step-card:nth-child(2) .step-num { background: linear-gradient(135deg, #a80d1e, #CE1126); box-shadow: 0 4px 12px rgba(206,17,38,.35); }
.step-card:nth-child(3) .step-num { background: linear-gradient(135deg, #d4a800, #F7D618); box-shadow: 0 4px 12px rgba(247,214,24,.4); color: #3d2e00; }
.step-title { font-size: 1rem; font-weight: 700; color: var(--blue-deep); margin-bottom: .5rem; }
.step-text  { font-size: .85rem; color: var(--gray-600); line-height: 1.65; }

/* ════════════════════════════════════════
   FEATURES
════════════════════════════════════════ */
.features-bg { background: var(--gray-50); }
.features-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem; margin-top: 3rem;
}
.feature-card {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    transition: transform .2s, box-shadow .2s, border-color .2s;
}
.feature-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    border-color: rgba(30,84,208,.2);
}
.feature-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 1rem;
    color: #fff;
}
.fi-blue   { background: linear-gradient(135deg, var(--blue-main), var(--blue-light)); }
.fi-green  { background: linear-gradient(135deg, #059669, #10b981); }
.fi-amber  { background: linear-gradient(135deg, #d97706, #f59e0b); }
.fi-purple { background: linear-gradient(135deg, #6d28d9, #8b5cf6); }
.fi-red    { background: linear-gradient(135deg, #dc2626, #ef4444); }
.fi-teal   { background: linear-gradient(135deg, #0891b2, #06b6d4); }
.feature-title { font-size: .92rem; font-weight: 700; color: var(--blue-deep); margin-bottom: .4rem; }
.feature-text  { font-size: .82rem; color: var(--gray-600); line-height: 1.6; }

/* ════════════════════════════════════════
   WORKFLOW DÉTAILLÉ
════════════════════════════════════════ */
.workflow-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center;
    margin-top: 3.5rem;
}
.workflow-list { display: flex; flex-direction: column; gap: 1.5rem; }
.workflow-item {
    display: flex; gap: 1rem; align-items: flex-start;
    padding-left: .85rem;
    border-left: 3px solid transparent;
    border-image: linear-gradient(180deg, #007FFF 0%, #CE1126 50%, #F7D618 100%) 1;
    transition: transform .2s;
}
.workflow-item:hover { transform: translateX(4px); }
.workflow-icon {
    width: 40px; height: 40px; min-width: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    background: linear-gradient(135deg, var(--blue-main), var(--blue-light));
}
.workflow-content h4 { font-size: .9rem; font-weight: 700; color: var(--blue-deep); margin-bottom: .25rem; }
.workflow-content p  { font-size: .82rem; color: var(--gray-600); line-height: 1.6; }

.workflow-visual {
    background: linear-gradient(145deg, var(--blue-soft), #fff);
    border: 1px solid rgba(30,84,208,.15);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow-sm);
}
.wv-title { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--gray-400); margin-bottom: 1.25rem; }
.wv-promo {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius-sm);
    margin-bottom: .75rem;
    overflow: hidden;
}
.wv-promo-header {
    padding: .6rem .9rem;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid var(--gray-100);
}
.wv-promo-name { font-size: .8rem; font-weight: 700; color: var(--blue-deep); }
.wv-promo-pct  { font-size: .72rem; font-weight: 700; color: var(--green); }
.wv-bar-wrap { padding: .5rem .9rem .7rem; }
.wv-bar { height: 6px; border-radius: 3px; background: var(--gray-200); overflow: hidden; }
.wv-bar-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, var(--green), #34d399); }
.wv-ecues { padding: 0 .9rem .7rem; display: flex; flex-direction: column; gap: .3rem; }
.wv-ecue {
    display: flex; align-items: center; justify-content: space-between;
    font-size: .72rem; color: var(--gray-600);
}

/* ════════════════════════════════════════
   CTA FINAL
════════════════════════════════════════ */
.cta-section {
    background: linear-gradient(135deg, var(--blue-deep) 0%, var(--blue-main) 60%, var(--blue-light) 100%);
    text-align: center;
    position: relative; overflow: hidden;
}
.cta-section::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        radial-gradient(circle at 20% 50%, rgba(255,255,255,.05) 0%, transparent 40%),
        radial-gradient(circle at 80% 50%, rgba(255,255,255,.05) 0%, transparent 40%);
}
.cta-inner { position: relative; z-index: 1; max-width: 650px; margin: 0 auto; }
.cta-title { font-size: clamp(1.6rem, 3vw, 2.4rem); font-weight: 900; color: #fff; margin-bottom: .85rem; letter-spacing: -.03em; }
.cta-sub   { font-size: 1rem; color: rgba(255,255,255,.75); margin-bottom: 2rem; line-height: 1.65; }
.btn-white {
    padding: .8rem 2rem; border-radius: var(--radius-sm);
    font-size: .95rem; font-weight: 700; color: var(--blue-main);
    background: #fff;
    text-decoration: none; border: none; cursor: pointer;
    box-shadow: 0 4px 18px rgba(0,0,0,.2);
    transition: transform .13s, box-shadow .13s;
    display: inline-flex; align-items: center; gap: .5rem;
}
.btn-white:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,0,0,.25); }

/* ════════════════════════════════════════
   FOOTER
════════════════════════════════════════ */
footer {
    background: var(--blue-deep);
    padding: 2rem;
    text-align: center;
    color: rgba(255,255,255,.45);
    font-size: .8rem;
    position: relative;
}
footer::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #007FFF 33.3%, #CE1126 33.3% 66.6%, #F7D618 66.6%);
}
footer a { color: rgba(255,255,255,.6); text-decoration: none; }
footer a:hover { color: #fff; }

/* ════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════ */

/* Empêcher le scroll horizontal sur tous les écrans */
body { overflow-x: hidden; }

/* ── Tablette (≤ 900px) ─────────────────────────────── */
@media (max-width: 900px) {
    .hero-inner    { grid-template-columns: 1fr; gap: 2rem; padding: 2.5rem 1.5rem; }
    .hero-visual   { order: -1; }
    .hero-sub      { max-width: 100%; }
    .steps-grid    { grid-template-columns: 1fr; gap: 1rem; }
    .steps-grid::before { display: none; }
    .workflow-grid { grid-template-columns: 1fr; gap: 2rem; }
    .stats-inner   { grid-template-columns: repeat(3, 1fr); gap: 1rem; }
    .stat-val      { font-size: 1.7rem; }
    .mockup        { max-width: 100%; }
}

/* ── Mobile (≤ 640px) ───────────────────────────────── */
@media (max-width: 640px) {
    /* Splash */
    .splash-icon      { width: 58px; height: 58px; border-radius: 16px; }
    .splash-icon svg  { width: 26px; height: 26px; }
    .splash-name      { font-size: 1.6rem; }
    .splash-tagline   { font-size: .7rem; }
    .splash-progress-wrap { width: 160px; }
    #splash::after    { width: 280px; height: 280px; }

    /* Navbar : masquer les liens texte, garder seulement le bouton */
    .navbar { padding: 0 1rem; height: 60px; }
    .navbar-actions .btn-ghost { display: none; }
    .btn-primary { font-size: .8rem; padding: .42rem .9rem; }

    /* Hero */
    section { padding: 3rem 1.1rem; }
    .hero-inner { padding: 2rem 1.1rem; }
    .hero-badge { font-size: .68rem; }
    .hero-title { font-size: 1.75rem; letter-spacing: -.02em; }
    .hero-sub   { font-size: .9rem; }
    .hero-cta   { flex-direction: column; align-items: stretch; gap: .75rem; }
    .btn-primary-lg { justify-content: center; width: 100%; padding: .75rem 1rem; font-size: .88rem; }
    .hero-note  { font-size: .73rem; }

    /* Mockup : désactiver l'animation flottante sur mobile */
    .mockup { animation: none; }
    .mockup-kpis { grid-template-columns: repeat(3, 1fr); gap: .4rem; }
    .mockup-kpi  { padding: .5rem .5rem; }
    .mockup-kpi .k-val { font-size: 1.1rem; }
    .mockup-kpi .k-lbl { font-size: .55rem; }
    .mockup-rows .mockup-row { font-size: .65rem; padding: .4rem .6rem; }

    /* Stats strip */
    .stats-inner { grid-template-columns: 1fr; gap: 1.25rem; text-align: center; }
    .stat-val    { font-size: 2rem; }

    /* Steps */
    .step-card { padding: 1.25rem 1.1rem; }

    /* Features */
    .features-grid { grid-template-columns: 1fr; }
    .feature-card  { padding: 1.25rem; }

    /* Workflow */
    .workflow-visual  { padding: 1.25rem; }
    .wv-promo-header  { flex-direction: column; align-items: flex-start; gap: .25rem; }

    /* CTA */
    .cta-title { font-size: 1.55rem; }
    .cta-sub   { font-size: .88rem; }
    .btn-white { width: 100%; justify-content: center; padding: .75rem 1rem; font-size: .88rem; }

    /* Sections */
    .section-title { font-size: 1.4rem; }
    .section-sub   { font-size: .88rem; }
}

/* ── Très petit mobile (≤ 380px) ────────────────────── */
@media (max-width: 380px) {
    /* Splash */
    .splash-icon      { width: 50px; height: 50px; border-radius: 13px; }
    .splash-icon svg  { width: 22px; height: 22px; }
    .splash-name      { font-size: 1.35rem; }
    .splash-tagline   { font-size: .65rem; letter-spacing: .08em; }
    .splash-progress-wrap { width: 130px; }

    .hero-title  { font-size: 1.5rem; }
    .brand-name  { font-size: .95rem; }
    .mockup-body { padding: .75rem; }
    .mockup-kpis { gap: .3rem; }
    .stats-strip { padding: 2rem 1.1rem; }
}

/* ════════════════════════════════════════
   SPLASH SCREEN
════════════════════════════════════════ */
#splash {
    position: fixed; inset: 0;
    z-index: 9999;
    background: linear-gradient(145deg, #0b1e3d 0%, #0f2a4a 55%, #163266 100%);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 0;
    overflow: hidden;
    transition: opacity .55s ease, visibility .55s ease;
}
#splash.hidden { opacity: 0; visibility: hidden; pointer-events: none; }

/* Grille déco derrière */
#splash::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
    background-size: 44px 44px;
    pointer-events: none;
}

/* Halo lumineux central */
#splash::after {
    content: '';
    position: absolute;
    width: 480px; height: 480px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(30,84,208,.28) 0%, transparent 70%);
    pointer-events: none;
}

.splash-inner {
    position: relative; z-index: 1;
    display: flex; flex-direction: column; align-items: center; gap: 1.6rem;
    animation: splashIn .65s cubic-bezier(.22,.68,0,1.2) both;
}
@keyframes splashIn {
    from { opacity: 0; transform: translateY(22px) scale(.96); }
    to   { opacity: 1; transform: none; }
}

/* Icône */
.splash-icon {
    width: 72px; height: 72px;
    border-radius: 20px;
    background: linear-gradient(135deg, #1e54d0, #3b6ff5);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    box-shadow: 0 8px 32px rgba(30,84,208,.5), 0 0 0 1px rgba(255,255,255,.08);
    position: relative;
}
/* Anneau pulsant autour de l'icône */
.splash-icon::before {
    content: '';
    position: absolute; inset: -8px;
    border-radius: 28px;
    border: 1.5px solid rgba(59,111,245,.4);
    animation: pulseRing 1.8s ease-in-out infinite;
}
@keyframes pulseRing {
    0%, 100% { opacity: .5; transform: scale(1); }
    50%       { opacity: 1;  transform: scale(1.06); }
}

/* Brand */
.splash-brand {
    display: flex; flex-direction: column; align-items: center; gap: .3rem;
}
.splash-name {
    font-size: 2rem; font-weight: 900;
    letter-spacing: -.04em;
    color: #fff;
    line-height: 1;
}
.splash-name em {
    font-style: normal;
    background: linear-gradient(90deg, #F7D618, #fbbf24);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.splash-tagline {
    font-size: .78rem; font-weight: 500; letter-spacing: .12em;
    text-transform: uppercase;
    color: rgba(255,255,255,.45);
}

/* Barre de progression tricolore */
.splash-progress-wrap {
    width: 200px;
    display: flex; flex-direction: column; align-items: center; gap: .55rem;
}
.splash-progress-track {
    width: 100%; height: 3px;
    background: rgba(255,255,255,.1);
    border-radius: 2px;
    overflow: hidden;
}
.splash-progress-fill {
    height: 100%; width: 0%;
    border-radius: 2px;
    background: linear-gradient(90deg, #007FFF 0%, #007FFF 33%, #CE1126 33%, #CE1126 66%, #F7D618 66%);
    background-size: 200px 100%;
    animation: splashProgress 1.8s cubic-bezier(.4,0,.2,1) forwards;
}
@keyframes splashProgress {
    0%   { width: 0%; }
    60%  { width: 75%; }
    100% { width: 100%; }
}
.splash-dots {
    display: flex; gap: 5px; align-items: center;
}
.splash-dot {
    width: 5px; height: 5px; border-radius: 50%;
    animation: dotBlink 1.2s ease-in-out infinite;
}
.splash-dot:nth-child(1) { background: #007FFF; animation-delay: 0s; }
.splash-dot:nth-child(2) { background: #CE1126; animation-delay: .2s; }
.splash-dot:nth-child(3) { background: #F7D618; animation-delay: .4s; }
@keyframes dotBlink {
    0%, 80%, 100% { opacity: .3; transform: scale(1); }
    40%            { opacity: 1;  transform: scale(1.4); }
}

/* Bande tricolore en bas du splash */
.splash-rdc-band {
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #007FFF 33.3%, #CE1126 33.3% 66.6%, #F7D618 66.6%);
}

/* ════════════════════════════════════════
   ANIMATIONS AU SCROLL
════════════════════════════════════════ */
.reveal {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity .55s ease, transform .55s ease;
}
.reveal.visible { opacity: 1; transform: none; }
.reveal-delay-1 { transition-delay: .1s; }
.reveal-delay-2 { transition-delay: .2s; }
.reveal-delay-3 { transition-delay: .3s; }
.reveal-delay-4 { transition-delay: .4s; }
.reveal-delay-5 { transition-delay: .5s; }
</style>
</head>
<body>

<!-- ════════════════ SPLASH SCREEN ════════════════ -->
<div id="splash" role="status" aria-label="Chargement de Landela-Cours">
    <div class="splash-inner">

        <div class="splash-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
            </svg>
        </div>

        <div class="splash-brand">
            <div class="splash-name">Landela<em>-Cours</em></div>
            <div class="splash-tagline">E-Suivi des Enseignements</div>
        </div>

        <div class="splash-progress-wrap">
            <div class="splash-progress-track">
                <div class="splash-progress-fill"></div>
            </div>
            <div class="splash-dots">
                <div class="splash-dot"></div>
                <div class="splash-dot"></div>
                <div class="splash-dot"></div>
            </div>
        </div>

    </div>
    <div class="splash-rdc-band"></div>
</div>

<!-- Barre tricolore RDC -->
<div class="rdc-bar" aria-hidden="true">
    <span class="rdc-blue"></span>
    <span class="rdc-red"></span>
    <span class="rdc-yellow"></span>
</div>

<!-- ════════════════ NAVBAR ════════════════ -->
<nav class="navbar" id="navbar">
    <a href="#" class="navbar-brand">
        <div class="brand-icon">
            <i data-lucide="activity" style="width:18px;height:18px;stroke-width:2.5"></i>
        </div>
        <span class="brand-name">Landela<span>-Cours</span></span>
    </a>
    <div class="navbar-actions">
        <a href="#comment" class="btn-ghost">Comment ça marche</a>
        <a href="#fonctionnalites" class="btn-ghost">Fonctionnalités</a>
        <a href="accueil" class="btn-primary">
            <i data-lucide="log-in" style="width:15px;height:15px;stroke-width:2.5"></i>
            Se connecter
        </a>
    </div>
</nav>

<!-- ════════════════ HERO ════════════════ -->
<section class="hero" id="hero">
    <div class="hero-inner">
        <div>
            <div class="hero-badge">
                <i data-lucide="zap" style="width:12px;height:12px;stroke-width:2.5"></i>
                E-Suivi des Cours — Nouvelle génération
            </div>
            <h1 class="hero-title">
                Le pouls de vos<br>
                <span class="highlight">enseignements,</span><br>
                en temps réel.
            </h1>
            <p class="hero-sub">
                Landela-Cours permet aux chefs de section de suivre l'avancement
                de chaque cours, par promotion et par UE, et d'agir immédiatement
                en cas de retard.
            </p>
            <div class="hero-cta">
                <a href="accueil" class="btn-primary-lg">
                    <i data-lucide="arrow-right" style="width:18px;height:18px;stroke-width:2.5"></i>
                    Accéder à la plateforme
                </a>
                <span class="hero-note">
                    <i data-lucide="shield-check" style="width:14px;height:14px;stroke-width:2"></i>
                    Accès sécurisé — Identifiants fournis par l'administration
                </span>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-screenshot">
                <img src="uploads/landela_cours.png"
                     alt="Aperçu de Landela-Cours"
                     class="hero-img">
            </div>
        </div>
    </div>
</section>

<!-- ════════════════ STATS ════════════════ -->
<div class="stats-strip">
    <div class="stats-inner">
        <div class="reveal">
            <div class="stat-val">100%</div>
            <div class="stat-lbl">Suivi en temps réel, sans attente</div>
        </div>
        <div class="reveal reveal-delay-2">
            <div class="stat-val">3 clics</div>
            <div class="stat-lbl">Pour mettre à jour le statut d'un cours</div>
        </div>
        <div class="reveal reveal-delay-4">
            <div class="stat-val">0 papier</div>
            <div class="stat-lbl">Tout est numérique et traçable</div>
        </div>
    </div>
</div>

<!-- ════════════════ COMMENT ÇA MARCHE ════════════════ -->
<section id="comment">
    <div class="section-inner">
        <div class="text-center reveal">
            <div class="section-tag">
                <i data-lucide="map" style="width:13px;height:13px"></i>
                Procédure de fonctionnement
            </div>
            <h2 class="section-title">Comment ça marche ?</h2>
            <p class="section-sub">
                En trois étapes simples, le chef de section configure ses cours
                et assure un suivi précis tout au long de l'année académique.
            </p>
        </div>

        <div class="steps-grid">
            <div class="step-card reveal reveal-delay-1">
                <div class="step-num">1</div>
                <h3 class="step-title">Configurer les cours</h3>
                <p class="step-text">
                    Connectez-vous à Landela-Cours et accédez à votre section.
                    Via le <strong>wizard guidé</strong>, créez les promotions,
                    définissez les UE par semestre, puis ajoutez chaque ECUE
                    avec son volume horaire (CM, TD, TP).
                    Cette structure est réutilisable d'une année à l'autre.
                </p>
            </div>
            <div class="step-card reveal reveal-delay-2">
                <div class="step-num">2</div>
                <h3 class="step-title">Mettre à jour les statuts</h3>
                <p class="step-text">
                    Au fil des semaines, actualisez le statut de chaque cours
                    d'un seul clic : <strong>Non commencé → En cours → Terminé</strong>.
                    Chaque modification est horodatée et attribuée à votre compte,
                    garantissant une traçabilité complète.
                </p>
            </div>
            <div class="step-card reveal reveal-delay-3">
                <div class="step-num">3</div>
                <h3 class="step-title">Piloter avec le tableau de bord</h3>
                <p class="step-text">
                    Le tableau de bord agrège instantanément les données :
                    taux d'avancement global, graphiques par section et par promotion,
                    liste détaillée filtrée. La <strong>hiérarchie</strong> dispose
                    d'une vue consolidée de toutes les sections en un seul écran.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════ FONCTIONNALITÉS ════════════════ -->
<section class="features-bg" id="fonctionnalites">
    <div class="section-inner">
        <div class="text-center reveal">
            <div class="section-tag">
                <i data-lucide="sparkles" style="width:13px;height:13px"></i>
                Ce que vous obtenez
            </div>
            <h2 class="section-title">Toutes les fonctionnalités dont vous avez besoin</h2>
            <p class="section-sub">
                Landela-Cours est conçu pour les réalités des universités africaines :
                simple, rapide, fiable.
            </p>
        </div>
        <div class="features-grid">
            <div class="feature-card reveal reveal-delay-1">
                <div class="feature-icon fi-blue">
                    <i data-lucide="layout-dashboard" style="width:20px;height:20px;stroke-width:2"></i>
                </div>
                <h4 class="feature-title">Tableau de bord consolidé</h4>
                <p class="feature-text">
                    Vue globale de toutes les sections avec indicateurs clés, graphiques
                    donut et barres, filtrage par année et par section.
                </p>
            </div>
            <div class="feature-card reveal reveal-delay-2">
                <div class="feature-icon fi-green">
                    <i data-lucide="check-circle-2" style="width:20px;height:20px;stroke-width:2"></i>
                </div>
                <h4 class="feature-title">Mise à jour en 1 clic</h4>
                <p class="feature-text">
                    Trois boutons par cours — Non commencé, En cours, Terminé — mis à jour
                    instantanément sans rechargement de page.
                </p>
            </div>
            <div class="feature-card reveal reveal-delay-3">
                <div class="feature-icon fi-purple">
                    <i data-lucide="bar-chart-3" style="width:20px;height:20px;stroke-width:2"></i>
                </div>
                <h4 class="feature-title">Graphiques interactifs</h4>
                <p class="feature-text">
                    Visualisations ApexCharts animées : répartition globale en donut
                    et avancement par section ou promotion en barres empilées.
                </p>
            </div>
            <div class="feature-card reveal reveal-delay-4">
                <div class="feature-icon fi-amber">
                    <i data-lucide="layers" style="width:20px;height:20px;stroke-width:2"></i>
                </div>
                <h4 class="feature-title">Hiérarchie structurée</h4>
                <p class="feature-text">
                    Organisation claire : Section → Orientation → Promotion →
                    Semestre → UE → ECUE. Chaque niveau est consultable
                    et modifiable indépendamment.
                </p>
            </div>
            <div class="feature-card reveal reveal-delay-5">
                <div class="feature-icon fi-teal">
                    <i data-lucide="search" style="width:20px;height:20px;stroke-width:2"></i>
                </div>
                <h4 class="feature-title">Recherche en temps réel</h4>
                <p class="feature-text">
                    Filtrez instantanément les cours par nom, UE ou promotion
                    sans recharger la page — idéal pour les sections avec de nombreux cours.
                </p>
            </div>
            <div class="feature-card reveal reveal-delay-1">
                <div class="feature-icon fi-red">
                    <i data-lucide="shield-check" style="width:20px;height:20px;stroke-width:2"></i>
                </div>
                <h4 class="feature-title">Traçabilité & Sécurité</h4>
                <p class="feature-text">
                    Chaque modification est horodatée et liée à l'utilisateur.
                    Accès protégé par session, rôles et permissions granulaires.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════ WORKFLOW DÉTAILLÉ ════════════════ -->
<section>
    <div class="section-inner">
        <div class="workflow-grid">
            <div>
                <div class="reveal">
                    <div class="section-tag">
                        <i data-lucide="git-branch" style="width:13px;height:13px"></i>
                        Guide du chef de section
                    </div>
                    <h2 class="section-title">Votre routine hebdomadaire simplifiée</h2>
                    <p class="section-sub" style="margin-bottom:2rem">
                        Consacrez 5 minutes par semaine pour maintenir un suivi
                        parfaitement à jour et accessible à toute la hiérarchie.
                    </p>
                </div>
                <div class="workflow-list">
                    <div class="workflow-item reveal reveal-delay-1">
                        <div class="workflow-icon">
                            <i data-lucide="log-in" style="width:18px;height:18px;stroke-width:2"></i>
                        </div>
                        <div class="workflow-content">
                            <h4>1 — Connexion sécurisée</h4>
                            <p>Accédez à Landela-Cours avec vos identifiants fournis par l'administration. Votre section est chargée automatiquement.</p>
                        </div>
                    </div>
                    <div class="workflow-item reveal reveal-delay-2">
                        <div class="workflow-icon">
                            <i data-lucide="list-checks" style="width:18px;height:18px;stroke-width:2"></i>
                        </div>
                        <div class="workflow-content">
                            <h4>2 — Parcourir les promotions</h4>
                            <p>Dépliez chaque promotion pour voir ses cours par semestre et UE. Identifiez d'un coup d'œil ce qui n'a pas encore démarré.</p>
                        </div>
                    </div>
                    <div class="workflow-item reveal reveal-delay-3">
                        <div class="workflow-icon">
                            <i data-lucide="mouse-pointer-click" style="width:18px;height:18px;stroke-width:2"></i>
                        </div>
                        <div class="workflow-content">
                            <h4>3 — Mettre à jour les statuts</h4>
                            <p>Pour chaque cours commencé ou terminé depuis votre dernière visite, cliquez sur le bouton correspondant. Sauvegarde instantanée.</p>
                        </div>
                    </div>
                    <div class="workflow-item reveal reveal-delay-4">
                        <div class="workflow-icon">
                            <i data-lucide="trending-up" style="width:18px;height:18px;stroke-width:2"></i>
                        </div>
                        <div class="workflow-content">
                            <h4>4 — Consulter l'avancement</h4>
                            <p>Vérifiez le tableau de bord pour visualiser le taux de complétion de votre section et le comparer aux autres sections.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal reveal-delay-2">
                <div class="workflow-visual">
                    <div class="wv-title">Aperçu — Suivi par promotion</div>

                    <div class="wv-promo">
                        <div class="wv-promo-header">
                            <span class="wv-promo-name">L3 Informatique</span>
                            <span class="wv-promo-pct">78% terminé</span>
                        </div>
                        <div class="wv-bar-wrap">
                            <div class="wv-bar"><div class="wv-bar-fill" style="width:78%"></div></div>
                        </div>
                        <div class="wv-ecues">
                            <div class="wv-ecue"><span>Syst. d'exploitation</span><span class="chip chip-green">Terminé</span></div>
                            <div class="wv-ecue"><span>Réseaux informatiques</span><span class="chip chip-amber">En cours</span></div>
                            <div class="wv-ecue"><span>Compilation</span><span class="chip chip-green">Terminé</span></div>
                        </div>
                    </div>

                    <div class="wv-promo">
                        <div class="wv-promo-header">
                            <span class="wv-promo-name">L2 Mathématiques</span>
                            <span class="wv-promo-pct" style="color:var(--amber)">45% terminé</span>
                        </div>
                        <div class="wv-bar-wrap">
                            <div class="wv-bar"><div class="wv-bar-fill" style="width:45%;background:linear-gradient(90deg,var(--amber),#fbbf24)"></div></div>
                        </div>
                        <div class="wv-ecues">
                            <div class="wv-ecue"><span>Analyse complexe</span><span class="chip chip-green">Terminé</span></div>
                            <div class="wv-ecue"><span>Topologie</span><span class="chip chip-gray">Non commencé</span></div>
                            <div class="wv-ecue"><span>Probabilités II</span><span class="chip chip-amber">En cours</span></div>
                        </div>
                    </div>

                    <div style="margin-top:1rem;padding:.75rem;background:#fff;border-radius:var(--radius-sm);border:1px solid var(--gray-200)">
                        <div style="font-size:.7rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem">Mise à jour rapide</div>
                        <div style="display:flex;gap:.4rem;flex-wrap:wrap">
                            <span style="padding:.25rem .7rem;border-radius:6px;font-size:.7rem;font-weight:600;background:var(--gray-200);color:var(--gray-600);cursor:pointer">⚪ Non commencé</span>
                            <span style="padding:.25rem .7rem;border-radius:6px;font-size:.7rem;font-weight:600;background:#fef3c7;color:#92400e;cursor:pointer">▶ En cours</span>
                            <span style="padding:.25rem .7rem;border-radius:6px;font-size:.7rem;font-weight:600;background:#d1fae5;color:#065f46;cursor:pointer">✓ Terminé</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ════════════════ CTA FINAL ════════════════ -->
<section class="cta-section">
    <div class="cta-inner reveal">
        <h2 class="cta-title">Prêt à démarrer le suivi ?</h2>
        <p class="cta-sub">
            Connectez-vous avec vos identifiants et commencez à suivre
            l'avancement de vos cours dès aujourd'hui.
            Le tableau de bord est mis à jour en temps réel.
        </p>
        <a href="accueil" class="btn-white">
            <i data-lucide="arrow-right" style="width:18px;height:18px;stroke-width:2.5;color:var(--blue-main)"></i>
            Accéder à Landela-Cours
        </a>
        <p style="margin-top:1.25rem;font-size:.78rem;color:rgba(255,255,255,.45)">
            Vous n'avez pas de compte ? Contactez votre administration.
        </p>
    </div>
</section>

<!-- ════════════════ FOOTER ════════════════ -->
<footer>
    <div style="margin-bottom:.5rem">
        <span style="color:rgba(255,255,255,.7);font-weight:700">Landela-Cours</span>
        &nbsp;·&nbsp; E-Suivi des Cours
        &nbsp;·&nbsp; Module intégré à <a href="accueil">E-Gestion</a>
    </div>
    <div>Tous droits réservés &copy; <?= date('Y') ?></div>
</footer>

<script>
// Init Lucide icons
lucide.createIcons();

// Splash screen
(function() {
    var splash = document.getElementById('splash');
    if (!splash) return;
    // Bloquer le scroll pendant le splash
    document.body.style.overflow = 'hidden';

    function hideSplash() {
        splash.classList.add('hidden');
        document.body.style.overflow = '';
        // Supprimer du DOM après la transition
        splash.addEventListener('transitionend', function() {
            splash.remove();
        }, { once: true });
    }

    // Attendre au moins 2.2s ET que la page soit chargée
    var minDone = false, pageDone = false;
    var timer = setTimeout(function() { minDone = true; if (pageDone) hideSplash(); }, 2200);

    if (document.readyState === 'complete') {
        pageDone = true;
        if (minDone) hideSplash();
    } else {
        window.addEventListener('load', function() {
            pageDone = true;
            if (minDone) hideSplash();
        });
    }
})();

// Navbar scroll shadow
window.addEventListener('scroll', function() {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 10);
});

// Reveal on scroll
const observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.12 });

document.querySelectorAll('.reveal').forEach(function(el) {
    observer.observe(el);
});

// Smooth scroll for nav links
document.querySelectorAll('a[href^="#"]').forEach(function(a) {
    a.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>
</body>
</html>
