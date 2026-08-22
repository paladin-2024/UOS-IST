<?php
// Charger la configuration de l'université pour la page de login
$configUniv = [];
try {
    $db = Connexion::getInstance()->getPDO();
    $stmt = $db->query("SELECT nom, sigle, logo, type_etablissement FROM configuration_universite WHERE id = 1");
    $configUniv = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {}
$loginUnivNom = $configUniv['nom'] ?? 'Université';
$loginUnivSigle = $configUniv['sigle'] ?? '';
$loginUnivLogo = $configUniv['logo'] ?? '';
$loginUnivType = $configUniv['type_etablissement'] ?? 'Université';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php if (!empty($loginUnivLogo)): ?>
        <!-- Favicons --> 
	<link href="https://inbtpkinshasa.info/<?= sanitize($loginUnivLogo) ?>" rel="icon">
	<link href="https://inbtpkinshasa.info/<?= sanitize($loginUnivLogo) ?>" rel="apple-touch-icon">
    <?php endif; ?>
    <title>Connexion — Espace de Scolarité | <?= sanitize($loginUnivSigle ?: $loginUnivNom) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            -webkit-font-smoothing: antialiased;
            background: #fff;
        }

        .top-stripe {
            position: fixed; top: 0; left: 0; right: 0; height: 5px; z-index: 1000; display: flex;
        }
        .top-stripe span:nth-child(1) { flex: 1; background: #0a47a0; }
        .top-stripe span:nth-child(2) { flex: 1; background: #f59e0b; }
        .top-stripe span:nth-child(3) { flex: 1; background: #dc2626; }

        .left-panel {
            width: 55%; min-height: 100vh;
            background: linear-gradient(160deg, #0a1628 0%, #1e3a8a 50%, #2563eb 100%);
            position: relative; overflow: hidden;
            display: flex; flex-direction: column; justify-content: center;
            padding: 60px; color: #fff;
        }
        .left-panel::before {
            content: ''; position: absolute; top: -50%; right: -30%; width: 80%; height: 200%;
            background: radial-gradient(ellipse, rgba(255,255,255,0.04) 0%, transparent 70%);
            transform: rotate(-15deg);
        }
        .left-panel::after {
            content: ''; position: absolute; bottom: -20%; left: -10%; width: 60%; height: 60%;
            background: radial-gradient(circle, rgba(245,158,11,0.08) 0%, transparent 70%);
        }
        .left-content { position: relative; z-index: 2; max-width: 520px; }

        .app-title { font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px; }
        .app-title span { color: #f59e0b; }
        .app-subtitle { font-size: 1rem; color: rgba(255,255,255,0.7); margin-bottom: 8px; }
        .app-institution { font-size: 0.85rem; color: rgba(255,255,255,0.5); margin-bottom: 40px; }
        .app-institution i { color: #f59e0b; }
        .title-bar { width: 50px; height: 4px; background: #f59e0b; border-radius: 2px; margin-bottom: 32px; }

        .feature-list { display: flex; flex-direction: column; gap: 14px; }
        .feature-item {
            display: flex; align-items: center; gap: 14px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 16px 18px; transition: background 0.2s;
        }
        .feature-item:hover { background: rgba(255,255,255,0.1); }
        .feature-item .f-icon {
            width: 42px; height: 42px; background: rgba(245,158,11,0.15); border-radius: 10px;
            display: flex; align-items: center; justify-content: center; color: #f59e0b; flex-shrink: 0;
        }
        .feature-item .f-title { font-weight: 600; font-size: 0.9rem; }
        .feature-item .f-desc { font-size: 0.78rem; color: rgba(255,255,255,0.55); margin: 0; }

        .left-footer {
            position: absolute; bottom: 30px; left: 60px; right: 60px; z-index: 2;
            border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;
        }
        .left-footer p { font-size: 0.78rem; color: rgba(255,255,255,0.4); margin: 0; }
        .left-footer i { color: #f59e0b; }

        .right-panel {
            width: 45%; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 40px; background: #fff;
        }
        .login-container { width: 100%; max-width: 400px; }
        .login-title { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .login-desc { color: #64748b; font-size: 0.9rem; margin-bottom: 30px; }

        .field-label {
            display: flex; align-items: center; gap: 8px;
            font-weight: 600; font-size: 0.85rem; color: #334155; margin-bottom: 8px;
        }
        .field-label i { color: #2563eb; font-size: 0.9rem; }

        .field-input {
            width: 100%; padding: 14px 16px;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font-size: 1rem; font-family: inherit; background: #f1f5f9;
            transition: all 0.2s; outline: none;
            -webkit-appearance: none;
        }
        .field-input:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .field-input::placeholder { color: #94a3b8; }

        .btn-submit {
            width: 100%; padding: 14px; background: #2563eb; color: #fff;
            border: none; border-radius: 10px; font-size: 1rem; font-weight: 700;
            font-family: inherit; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-submit:hover { background: #1e40af; }
        .btn-submit:active { transform: scale(0.98); }
        .btn-submit.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        .btn-submit .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2.5px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: btnSpin 0.6s linear infinite;
        }
        .btn-submit.loading .spinner { display: inline-block; }
        .btn-submit.loading .btn-label { display: none; }
        .btn-submit[style*="f59e0b"] .spinner { border-color: rgba(15,23,42,0.2); border-top-color: #0f172a; }
        @keyframes btnSpin { to { transform: rotate(360deg); } }

        .info-box {
            background: #fef3c7; border: 1px solid #fde68a; border-radius: 10px;
            padding: 16px; text-align: center; margin-top: 24px;
        }
        .info-box .info-title { font-weight: 600; font-size: 0.85rem; color: #92400e; margin-bottom: 8px; }
        .info-box .info-title i { color: #f59e0b; }
        .info-box .btn-back {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 24px; background: #f59e0b; color: #0f172a;
            border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 700;
            font-family: inherit; cursor: pointer; text-decoration: none; transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
        }
        .info-box .btn-back:hover { background: #fbbf24; color: #0f172a; }
        .info-box .btn-back:active { transform: scale(0.97); }

        .alert-custom {
            background: #fee2e2; border: 1px solid #fecaca; color: #991b1b;
            border-radius: 10px; padding: 12px 16px; font-size: 0.85rem; margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .alert-custom i { margin-top: 2px; color: #dc2626; }

        .tabs-container { display: flex; gap: 0; margin-bottom: 24px; border-bottom: 2px solid #e2e8f0; }
        .tab-btn {
            flex: 1; padding: 12px 16px; border: none; background: none;
            font-family: inherit; font-size: 0.9rem; font-weight: 600; color: #94a3b8;
            cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s;
            -webkit-tap-highlight-color: transparent;
        }
        .tab-btn:hover { color: #475569; }
        .tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
        .tab-btn.active-admin { color: #f59e0b; border-bottom-color: #f59e0b; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* ── Mobile hero banner (replaces left-panel on small screens) ── */
        .mobile-hero {
            display: none;
            background: linear-gradient(160deg, #0a1628 0%, #1e3a8a 50%, #2563eb 100%);
            color: #fff;
            text-align: center;
            padding: 40px 24px 32px;
            position: relative;
            overflow: hidden;
        }
        .mobile-hero::before {
            content: ''; position: absolute; top: -40%; right: -20%; width: 80%; height: 180%;
            background: radial-gradient(ellipse, rgba(255,255,255,0.04) 0%, transparent 70%);
            transform: rotate(-15deg);
        }
        .mobile-hero-content { position: relative; z-index: 2; }
        .mobile-hero-logo {
            width: 64px; height: 64px;
            background: rgba(245,158,11,0.15);
            border-radius: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
            box-shadow: 0 0 30px rgba(245,158,11,0.15);
        }
        .mobile-hero-logo i { font-size: 1.5rem; color: #f59e0b; }
        .mobile-hero-logo img { max-height: 40px; object-fit: contain; }
        .mobile-hero h1 { font-size: 1.35rem; font-weight: 800; margin-bottom: 2px; letter-spacing: -0.01em; }
        .mobile-hero h1 span { color: #f59e0b; }
        .mobile-hero .mh-subtitle { font-size: 0.82rem; color: rgba(255,255,255,0.65); margin-bottom: 2px; }
        .mobile-hero .mh-institution { font-size: 0.72rem; color: rgba(255,255,255,0.4); }
        .mobile-hero .mh-institution i { color: #f59e0b; }
        .mobile-hero .mh-bar { width: 36px; height: 3px; background: #f59e0b; border-radius: 2px; margin: 14px auto 0; }

        /* ── Tablet (max 992px): stack panels vertically ── */
        @media (max-width: 992px) {
            body { flex-direction: column; }
            .left-panel { width: 100%; min-height: auto; padding: 48px 32px 40px; }
            .left-footer { position: static; margin-top: 32px; padding-top: 16px; }
            .right-panel { width: 100%; min-height: auto; padding: 36px 32px 40px; }
            .login-container { max-width: 440px; }
            .feature-list { gap: 10px; }
            .feature-item { padding: 14px 16px; }
        }

        /* ── Mobile (max 576px): replace left-panel with compact hero ── */
        @media (max-width: 576px) {
            .left-panel { display: none; }
            .mobile-hero { display: block; }

            .right-panel {
                width: 100%;
                padding: 24px 20px 32px;
                min-height: auto;
                flex: 1;
                align-items: flex-start;
            }
            .login-container { max-width: 100%; }
            .login-title { font-size: 1.3rem; }
            .login-desc { font-size: 0.85rem; margin-bottom: 22px; }

            .tabs-container { margin-bottom: 20px; }
            .tab-btn { padding: 10px 8px; font-size: 0.82rem; }

            .field-label { font-size: 0.82rem; margin-bottom: 6px; }
            .field-input { padding: 12px 14px; font-size: 0.95rem; border-radius: 8px; }
            .btn-submit { padding: 13px; font-size: 0.95rem; border-radius: 8px; }

            .info-box { padding: 14px; margin-top: 20px; }
            .info-box .info-title { font-size: 0.8rem; }
            .info-box .btn-back { padding: 9px 20px; font-size: 0.82rem; }

            .alert-custom { font-size: 0.82rem; padding: 10px 14px; }
        }

        /* ── Very small phones (max 360px) ── */
        @media (max-width: 360px) {
            .mobile-hero { padding: 32px 16px 24px; }
            .mobile-hero h1 { font-size: 1.15rem; }
            .mobile-hero-logo { width: 52px; height: 52px; border-radius: 12px; }
            .mobile-hero-logo i { font-size: 1.2rem; }

            .right-panel { padding: 20px 16px 28px; }
            .login-title { font-size: 1.15rem; }
            .tab-btn { padding: 9px 6px; font-size: 0.78rem; }
            .field-input { padding: 11px 12px; font-size: 0.9rem; }
            .btn-submit { padding: 12px; font-size: 0.9rem; }
        }

        /* ── Safe area for notched phones ── */
        @supports (padding-top: env(safe-area-inset-top)) {
            .top-stripe { height: calc(5px + env(safe-area-inset-top)); padding-top: env(safe-area-inset-top); }
            .mobile-hero { padding-top: calc(40px + env(safe-area-inset-top)); }
            .right-panel { padding-bottom: calc(32px + env(safe-area-inset-bottom)); }
        }

        /* ── Landscape phones ── */
        @media (max-height: 500px) and (orientation: landscape) {
            .mobile-hero { padding: 24px 20px 16px; }
            .mobile-hero-logo { width: 44px; height: 44px; margin-bottom: 8px; }
            .mobile-hero h1 { font-size: 1.1rem; }
            .mobile-hero .mh-bar { margin-top: 8px; }
            .right-panel { padding: 16px 24px 20px; }
            .login-title { font-size: 1.15rem; }
            .login-desc { margin-bottom: 14px; }
        }
    </style>
</head>
<body>

<div class="top-stripe"><span></span><span></span><span></span></div>

<!-- Mobile hero (visible only on phones) -->
<div class="mobile-hero">
    <div class="mobile-hero-content">
        <div class="mobile-hero-logo">
            <?php if (!empty($loginUnivLogo)): ?>
                <img src="https://inbtpkinshasa.info/<?= sanitize($loginUnivLogo) ?>" alt="Logo">
            <?php else: ?>
                <i class="fas fa-graduation-cap"></i>
            <?php endif; ?>
        </div>
        <h1>Espace de <span>Scolarité</span></h1>
        <div class="mh-subtitle"><?= sanitize($loginUnivNom) ?></div>
        <div class="mh-institution"><i class="fas fa-landmark me-1"></i><?= sanitize($loginUnivType) ?></div>
        <div class="mh-bar"></div>
    </div>
</div>

<!-- Desktop left panel -->
<div class="left-panel">
    <div class="left-content">
        <h1 class="app-title">Espace de <span>Scolarité</span></h1>
        <p class="app-subtitle"><?= sanitize($loginUnivNom) ?></p>
        <?php if (!empty($loginUnivLogo)): ?>
            <div style="margin-bottom:16px;">
                <img src="https://inbtpkinshasa.info/<?= sanitize($loginUnivLogo) ?>" alt="Logo" style="max-height:100px;">
            </div>
        <?php endif; ?>
        <p class="app-institution"><i class="fas fa-landmark me-1"></i> <?= sanitize($loginUnivType) ?></p>
        <div class="title-bar"></div>
        <div class="feature-list">
            <div class="feature-item">
                <div class="f-icon"><i class="fas fa-id-card"></i></div>
                <div>
                    <div class="f-title">Accès Rapide</div>
                    <p class="f-desc">Connexion par matricule étudiant</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="f-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                <div>
                    <div class="f-title">Soumission Numérique</div>
                    <p class="f-desc">Upload de documents PDF sécurisé</p>
                </div>
            </div>
            <div class="feature-item">
                <div class="f-icon"><i class="fas fa-tasks"></i></div>
                <div>
                    <div class="f-title">Suivi en Temps Réel</div>
                    <p class="f-desc">Validation et traçabilité complètes</p>
                </div>
            </div>
        </div>
    </div>
    <div class="left-footer">
        <p><i class="fas fa-graduation-cap me-1"></i> <?= sanitize($loginUnivSigle ?: $loginUnivNom) ?> — Plateforme réservée aux étudiants finalistes</p>
    </div>
</div>

<div class="right-panel">
    <div class="login-container">
        <h2 class="login-title">Connexion</h2>
        <p class="login-desc">Accédez à votre espace de scolarité</p>

        <?php if (!empty($error)): ?>
            <div class="alert-custom">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= sanitize($error) ?></span>
            </div>
        <?php endif; ?>

        <?php $activeTab = $loginTab ?? 'student'; ?>

        <div class="tabs-container">
            <button class="tab-btn <?= $activeTab === 'student' ? 'active' : '' ?>" onclick="switchTab('student')" type="button">
                <i class="fas fa-user-graduate me-1"></i> Étudiant
            </button>
            <button class="tab-btn <?= $activeTab === 'admin' ? 'active-admin' : '' ?>" onclick="switchTab('admin')" type="button">
                <i class="fas fa-user-shield me-1"></i> Administrateur
            </button>
        </div>

        <!-- Onglet Étudiant -->
        <div id="tab-student" class="tab-content <?= $activeTab === 'student' ? 'active' : '' ?>">
            <form method="POST" action="index.php?action=authenticate" autocomplete="off">
                <div style="margin-bottom:28px;">
                    <label class="field-label">
                        <i class="fas fa-id-card"></i> Matricule
                    </label>
                    <input type="text" name="matricule" class="field-input"
                           placeholder="Entrez votre numéro de matricule" required <?= $activeTab === 'student' ? 'autofocus' : '' ?>>
                </div>
                <button type="submit" class="btn-submit">
                    <span class="spinner"></span>
                    <span class="btn-label"><i class="fas fa-sign-in-alt"></i> Se connecter</span>
                </button>
            </form>
        </div>

        <!-- Onglet Administrateur -->
        <div id="tab-admin" class="tab-content <?= $activeTab === 'admin' ? 'active' : '' ?>">
            <form method="POST" action="index.php?action=admin_authenticate" autocomplete="off">
                <div style="margin-bottom:16px;">
                    <label class="field-label">
                        <i class="fas fa-user"></i> Login
                    </label>
                    <input type="text" name="login" class="field-input"
                           placeholder="Votre identifiant" required <?= $activeTab === 'admin' ? 'autofocus' : '' ?>>
                </div>
                <div style="margin-bottom:28px;">
                    <label class="field-label">
                        <i class="fas fa-lock"></i> Mot de passe
                    </label>
                    <input type="password" name="pwd" class="field-input"
                           placeholder="Votre mot de passe" required>
                </div>
                <button type="submit" class="btn-submit" style="background:#f59e0b;color:#0f172a;">
                    <span class="spinner"></span>
                    <span class="btn-label"><i class="fas fa-sign-in-alt"></i> Connexion Admin</span>
                </button>
            </form>
        </div>

        <div class="info-box">
            <div class="info-title">
                <i class="fas fa-home me-1"></i> Application principale
            </div>
            <a href="https://inbtpkinshasa.info/" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour à E-Gestion
            </a>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(function(el) { el.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(el) { el.classList.remove('active'); el.classList.remove('active-admin'); });
    document.getElementById('tab-' + tab).classList.add('active');
    var btns = document.querySelectorAll('.tab-btn');
    if (tab === 'student') { btns[0].classList.add('active'); }
    else { btns[1].classList.add('active-admin'); }
}

document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function() {
        var btn = form.querySelector('.btn-submit');
        if (btn) btn.classList.add('loading');
    });
});
</script>

</body>
</html>
