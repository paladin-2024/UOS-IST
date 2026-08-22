<!DOCTYPE html>
<html lang="fr">
<?php
require_once dirname(__DIR__) . '/config/config.php';
$universite     = new Universite();
$configUniversite = $universite->getConfigurationUniversite();
$appName  = htmlspecialchars($configUniversite['nom_application'] ?? 'E-GESTION');
$logoPath = !empty($configUniversite['logo']) ? './' . htmlspecialchars($configUniversite['logo']) : '';
?>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $appName ?> — Connexion</title>
<meta name="description" content="<?= $appName ?> — Connexion sécurisée">

<?php if ($logoPath): ?>
<link rel="icon" href="<?= $logoPath ?>">
<link rel="apple-touch-icon" href="<?= $logoPath ?>">
<?php endif; ?>

<!-- CSS vendor nécessaires -->
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/icon_bootstrap/bootstrap-icons.css" rel="stylesheet">
<link href="assets/vendor/spinners/ladda-themeless.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- Config JS -->
<script>const APP_CONFIG = <?php echo json_encode(AppConfig::getJsConfig()); ?>;</script>
<script src="assets/js/DataLoader.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --blue-deep  : #0b1e3d;
    --blue-main  : #1e54d0;
    --blue-light : #3b6ff5;
    --blue-soft  : #e8efff;
    --rdc-blue   : #007FFF;
    --rdc-red    : #CE1126;
    --rdc-yellow : #F7D618;
    --radius-sm  : 10px;
    --radius-md  : 16px;
    --radius-lg  : 24px;
    --shadow-lg  : 0 20px 60px rgba(0,0,0,.35);
}

html, body { height: 100%; }

body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: #fff;
    min-height: 100vh;
    display: flex; flex-direction: column;
    -webkit-font-smoothing: antialiased;
    overflow-x: hidden;
}

/* ── Barre RDC ── */
.rdc-bar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 200;
    height: 4px; display: flex;
}
.rdc-bar span { flex: 1; display: block; }
.rdc-bar .b { background: var(--rdc-blue); }
.rdc-bar .r { background: var(--rdc-red); }
.rdc-bar .y { background: var(--rdc-yellow); }

/* ── Grille décorative (côté blanc) ── */
.bg-grid {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image:
        linear-gradient(rgba(30,84,208,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(30,84,208,.04) 1px, transparent 1px);
    background-size: 44px 44px;
}
.bg-glow {
    position: fixed; z-index: 0; pointer-events: none;
    width: 600px; height: 600px; border-radius: 50%;
    background: radial-gradient(circle, rgba(30,84,208,.06) 0%, transparent 70%);
    top: -150px; right: -100px;
}
.bg-glow-2 {
    position: fixed; z-index: 0; pointer-events: none;
    width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(0,127,255,.05) 0%, transparent 70%);
    bottom: -100px; left: -50px;
}

/* ── Layout principal ── */
.login-wrapper {
    position: relative; z-index: 1;
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    min-height: 100vh;
    padding-top: 4px;
}

/* ── Panneau gauche ── */
.login-left {
    display: flex; flex-direction: column;
    justify-content: center; align-items: flex-start;
    padding: 3rem 3.5rem;
    position: relative;
    background: linear-gradient(145deg, #0b1e3d 0%, #0f2a4a 55%, #163266 100%);
}
.login-left::after {
    content: '';
    position: absolute; right: 0; top: 0; bottom: 0;
    width: 1px;
    background: rgba(255,255,255,.08);
}

.left-brand {
    display: flex; align-items: center; gap: .9rem;
    margin-bottom: 3rem;
}
.left-brand-icon {
    width: 48px; height: 48px; border-radius: 14px;
    background: linear-gradient(135deg, var(--blue-main), var(--blue-light));
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    box-shadow: 0 6px 20px rgba(30,84,208,.45);
}
.left-brand-name {
    font-size: 1.3rem; font-weight: 800; color: #fff;
    letter-spacing: -.03em; line-height: 1;
}
.left-brand-name span { color: var(--rdc-yellow); }

.left-logo {
    margin-bottom: 2rem;
}
.left-logo img {
    max-width: 90px; max-height: 90px;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,.35));
}

.left-title {
    font-size: clamp(1.6rem, 2.5vw, 2.2rem);
    font-weight: 900; color: #fff;
    letter-spacing: -.03em; line-height: 1.15;
    margin-bottom: 1rem;
}
.left-title em {
    font-style: normal;
    background: linear-gradient(90deg, var(--rdc-yellow), #fbbf24);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}

.left-sub {
    font-size: .9rem; color: rgba(255,255,255,.5);
    line-height: 1.7; max-width: 340px;
    margin-bottom: 2.5rem;
}

.left-features {
    display: flex; flex-direction: column; gap: .85rem;
}
.left-feature {
    display: flex; align-items: center; gap: .75rem;
    font-size: .82rem; color: rgba(255,255,255,.65);
}
.left-feature-dot {
    width: 28px; height: 28px; min-width: 28px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem;
}
.lf-blue   { background: rgba(0,127,255,.2);  color: #60a5fa; }
.lf-red    { background: rgba(206,17,38,.2);  color: #f87171; }
.lf-yellow { background: rgba(247,214,24,.15); color: #fcd34d; }

/* ── Panneau droit (formulaire) ── */
.login-right {
    display: flex; align-items: center; justify-content: center;
    padding: 2.5rem 2rem;
}

.login-card {
    width: 100%; max-width: 420px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: var(--radius-lg);
    padding: 2.5rem 2.5rem 2rem;
    box-shadow: 0 8px 40px rgba(30,84,208,.1), 0 2px 8px rgba(0,0,0,.06);
    position: relative;
    overflow: hidden;
}
/* Accent tricolore en haut de la card */
.login-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--rdc-blue) 33.3%, var(--rdc-red) 33.3% 66.6%, var(--rdc-yellow) 66.6%);
}

.card-header-section {
    text-align: center;
    margin-bottom: 2rem;
}
.card-logo { margin-bottom: 1rem; }
.card-logo img {
    max-width: 64px; max-height: 64px; object-fit: contain;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,.4));
}
.card-logo-placeholder {
    width: 56px; height: 56px; border-radius: 16px; margin: 0 auto;
    background: linear-gradient(135deg, var(--blue-main), var(--blue-light));
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    box-shadow: 0 4px 16px rgba(30,84,208,.4);
}
.card-title {
    font-size: 1.25rem; font-weight: 800; color: var(--blue-deep);
    letter-spacing: -.02em; margin-bottom: .3rem;
}
.card-subtitle {
    font-size: .82rem; color: #9ca3af;
}

/* Champs du formulaire */
.form-group { margin-bottom: 1.1rem; }
.form-label-custom {
    display: block;
    font-size: .78rem; font-weight: 600; letter-spacing: .04em;
    text-transform: uppercase; color: #6b7280;
    margin-bottom: .45rem;
}
.input-wrap {
    position: relative;
    display: flex; align-items: center;
}
.input-icon {
    position: absolute; left: .9rem;
    color: #9ca3af;
    pointer-events: none;
    display: flex; align-items: center;
    font-size: 1rem;
}
.input-custom {
    width: 100%;
    padding: .75rem .9rem .75rem 2.6rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: var(--radius-sm);
    color: #1f2937;
    font-size: .9rem;
    transition: border-color .2s, background .2s, box-shadow .2s;
    outline: none;
    -webkit-appearance: none;
}
.input-custom::placeholder { color: #d1d5db; }
.input-custom:focus {
    border-color: var(--blue-main);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(30,84,208,.12);
}
.input-custom.is-invalid { border-color: rgba(206,17,38,.7); }
.invalid-msg {
    font-size: .75rem; color: #f87171; margin-top: .3rem;
    display: none;
}
.input-custom:invalid:not(:placeholder-shown) ~ .invalid-msg,
.was-validated .input-custom:invalid ~ .invalid-msg { display: block; }

/* Checkbox afficher mdp */
.check-row {
    display: flex; align-items: center; gap: .5rem;
    margin-bottom: 1.5rem;
}
.check-custom {
    width: 16px; height: 16px; accent-color: var(--blue-light);
    cursor: pointer;
}
.check-label {
    font-size: .8rem; color: #9ca3af; cursor: pointer;
    user-select: none;
}

/* Bouton connexion */
.btn-login {
    width: 100%;
    padding: .82rem 1.5rem;
    background: linear-gradient(135deg, var(--blue-main), var(--blue-light));
    border: none; border-radius: var(--radius-sm);
    color: #fff; font-size: .95rem; font-weight: 700;
    letter-spacing: .01em;
    cursor: pointer;
    box-shadow: 0 4px 18px rgba(30,84,208,.45);
    transition: transform .13s, box-shadow .13s, filter .13s;
    display: flex; align-items: center; justify-content: center; gap: .5rem;
    position: relative; overflow: hidden;
}
.btn-login:hover  { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(30,84,208,.5); filter: brightness(1.06); }
.btn-login:active { transform: translateY(0); }

/* Footer card */
.card-footer-note {
    text-align: center;
    margin-top: 1.5rem;
    font-size: .75rem; color: #d1d5db;
    line-height: 1.6;
}

/* ── Footer page ── */
.page-footer {
    position: relative; z-index: 1;
    text-align: center;
    padding: 1rem;
    font-size: .75rem; color: #9ca3af;
    border-top: 1px solid #f3f4f6;
}
.page-footer::after {
    content: '';
    position: absolute; bottom: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--rdc-blue) 33.3%, var(--rdc-red) 33.3% 66.6%, var(--rdc-yellow) 66.6%);
}

/* ── Responsive ── */
@media (max-width: 820px) {
    .login-wrapper { grid-template-columns: 1fr; }
    .login-left { display: none; }
    .login-right { padding: 3rem 1.25rem 2rem; align-items: flex-start; padding-top: 5rem; }
    .login-card  { max-width: 100%; padding: 2rem 1.5rem 1.5rem; }
}
@media (max-width: 420px) {
    .login-card { padding: 1.75rem 1.25rem 1.25rem; border-radius: var(--radius-md); }
    .card-title  { font-size: 1.1rem; }
}

/* ── Animation entrée ── */
.login-card {
    animation: cardIn .6s cubic-bezier(.22,.68,0,1.2) both;
    animation-delay: .1s;
    opacity: 0;
}
@keyframes cardIn {
    from { opacity: 0; transform: translateY(20px) scale(.97); }
    to   { opacity: 1; transform: none; }
}
.login-left > * {
    animation: fadeSlide .6s ease both;
}
.login-left > *:nth-child(1) { animation-delay: .15s; }
.login-left > *:nth-child(2) { animation-delay: .25s; }
.login-left > *:nth-child(3) { animation-delay: .32s; }
.login-left > *:nth-child(4) { animation-delay: .38s; }
.login-left > *:nth-child(5) { animation-delay: .44s; }
@keyframes fadeSlide {
    from { opacity: 0; transform: translateX(-16px); }
    to   { opacity: 1; transform: none; }
}
</style>
</head>
<body>

<!-- Barre RDC -->
<div class="rdc-bar" aria-hidden="true">
    <span class="b"></span><span class="r"></span><span class="y"></span>
</div>

<!-- Décorations fond -->
<div class="bg-grid"  aria-hidden="true"></div>
<div class="bg-glow"  aria-hidden="true"></div>
<div class="bg-glow-2" aria-hidden="true"></div>

<!-- Layout -->
<div class="login-wrapper">

    <!-- ── Panneau gauche ── -->
    <div class="login-left">
        <div class="left-brand">
            <div class="left-brand-icon">
                <i class="bi bi-activity fs-5"></i>
            </div>
            <div class="left-brand-name"><?= $appName ?><span>.</span></div>
        </div>

        <h1 class="left-title">
            Gérez votre<br>
            établissement<br>
            avec <em>précision.</em>
        </h1>
        <p class="left-sub">
            Plateforme intégrée de gestion académique, financière
            et administrative pour les établissements d'enseignement supérieur.
        </p>
        <div class="left-features">
            <div class="left-feature">
                <div class="left-feature-dot lf-blue"><i class="bi bi-shield-check"></i></div>
                Accès sécurisé par rôles et permissions
            </div>
            <div class="left-feature">
                <div class="left-feature-dot lf-red"><i class="bi bi-graph-up-arrow"></i></div>
                Suivi des cours et délibérations en temps réel
            </div>
            <div class="left-feature">
                <div class="left-feature-dot lf-yellow"><i class="bi bi-people"></i></div>
                Gestion complète étudiants, personnel & finances
            </div>
        </div>
    </div>

    <!-- ── Panneau droit (formulaire) ── -->
    <div class="login-right">
        <div class="login-card">

            <div class="card-header-section">
                <?php if ($logoPath && file_exists($logoPath)): ?>
                <div class="card-logo">
                    <img src="<?= $logoPath ?>" alt="Logo">
                </div>
                <?php else: ?>
                <div class="card-logo">
                    <div class="card-logo-placeholder">
                        <i class="bi bi-mortarboard-fill fs-4"></i>
                    </div>
                </div>
                <?php endif; ?>
                <div class="card-title"><?= $appName ?></div>
                <div class="card-subtitle">Connectez-vous pour continuer</div>
            </div>

            <form method="post" action="controller/loginAdmin.php"
                  class="needs-validation ladda-form" novalidate>
                <?php require_once dirname(__DIR__) . '/utils/Security.php'; ?>
                <input type="hidden" name="csrf_token" value="<?php echo AppSecurity::csrfToken(); ?>">
                <input type="hidden" name="con" value="1">

                <!-- Login -->
                <div class="form-group">
                    <label class="form-label-custom" for="yourUsername">Login</label>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="bi bi-person"></i></span>
                        <input type="text" name="login" id="yourUsername"
                               class="input-custom" placeholder="Votre identifiant" required>
                    </div>
                    <div class="invalid-msg">Veuillez saisir votre login.</div>
                </div>

                <!-- Mot de passe -->
                <div class="form-group">
                    <label class="form-label-custom" for="yourPassword">Mot de passe</label>
                    <div class="input-wrap">
                        <span class="input-icon"><i class="bi bi-lock"></i></span>
                        <input type="password" name="pwd" id="yourPassword"
                               class="input-custom" placeholder="••••••••" required>
                    </div>
                    <div class="invalid-msg">Veuillez saisir votre mot de passe.</div>
                </div>

                <!-- Afficher mot de passe -->
                <div class="check-row">
                    <input class="check-custom" type="checkbox" onclick="changer()"
                           name="remember" id="rememberMe">
                    <label class="check-label" for="rememberMe">Afficher le mot de passe</label>
                </div>

                <!-- Bouton -->
                <button type="submit"
                        class="btn-login ladda-button" data-style="zoom-out">
                    <i class="bi bi-box-arrow-in-right"></i>
                    SE CONNECTER
                </button>
            </form>

            <div class="card-footer-note">
                Accès réservé au personnel autorisé.<br>
                Identifiants fournis par l'administration.
            </div>

        </div>
    </div>

</div>

<!-- Footer -->
<footer class="page-footer">
    &copy; <?= date('Y') ?> <?= $appName ?> — Tous droits réservés
</footer>

<!-- JS -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/spinners/spin.min.js"></script>
<script src="assets/vendor/spinners/ladda.min.js"></script>
<script src="assets/js/sweetalert.min.js"></script>

<script>
// Afficher / masquer le mot de passe
function changer() {
    var pwd = document.getElementById('yourPassword');
    pwd.type = document.getElementById('rememberMe').checked ? 'text' : 'password';
}

// Validation Bootstrap + preloader Ladda
(function() {
    var form = document.querySelector('.ladda-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        form.classList.add('was-validated');
        // Activer Ladda si disponible
        if (typeof Ladda !== 'undefined') {
            var btn = form.querySelector('.ladda-button');
            if (btn) Ladda.create(btn).start();
        }
    });
})();
</script>

</body>
</html>
