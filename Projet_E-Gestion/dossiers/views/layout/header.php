<?php
$currentAction = $_GET['action'] ?? '';
$isStudent = isLoggedIn();
$isAdminUser = isAdmin();
$userName = '';
$userRole = '';
if ($isAdminUser) {
    $userName = $_SESSION['dossier_admin_name'] ?? 'Administrateur';
    $userRole = $_SESSION['dossier_admin_role'] ?? 'Administrateur';
    $userPhoto = $_SESSION['dossier_admin_photo'] ?? '';
} elseif ($isStudent) {
    $userName = $_SESSION['dossier_student_name'] ?? 'Étudiant';
    $userRole = 'Étudiant';
    $userPhoto = $_SESSION['dossier_student_photo'] ?? '';
}
$univNom = $_SESSION['dossier_universite_nom'] ?? 'Université';
$univSigle = $_SESSION['dossier_universite_sigle'] ?? '';
$univLogo = $_SESSION['dossier_universite_logo'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($univLogo)): ?>
        <!-- Favicons --> 
	<link href="https://inbtpkinshasa.info/<?= sanitize($univLogo) ?>" rel="icon">
	<link href="https://inbtpkinshasa.info/<?= sanitize($univLogo) ?>" rel="apple-touch-icon">
    <?php endif; ?>
    <title><?= sanitize($pageTitle ?? 'Espace de Scolarité') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0a1628;
            --navy-light: #132042;
            --blue: #1e3a8a;
            --blue-mid: #2563eb;
            --blue-light: #3b82f6;
            --blue-pale: #dbeafe;
            --blue-50: #eff6ff;
            --gold: #f59e0b;
            --gold-light: #fbbf24;
            --gold-pale: #fef3c7;
            --success: #059669;
            --success-light: #d1fae5;
            --warning: #d97706;
            --warning-light: #fef3c7;
            --danger: #dc2626;
            --danger-light: #fee2e2;
            --info: #0284c7;
            --info-light: #e0f2fe;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --radius: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --sidebar-width: 260px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Top Stripe ── */
        .top-stripe {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            z-index: 1100;
            display: flex;
        }
        .top-stripe span:nth-child(1) { flex: 1; background: #0a47a0; }
        .top-stripe span:nth-child(2) { flex: 1; background: #f59e0b; }
        .top-stripe span:nth-child(3) { flex: 1; background: #dc2626; }

        /* ── Sidebar ── */
        .sidebar {
            position: fixed;
            top: 4px;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0a1628 0%, #1e3a8a 100%);
            z-index: 1050;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-brand-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.05rem;
        }
        .sidebar-brand-icon {
            width: 38px;
            height: 38px;
            background: var(--gold);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 1rem;
            flex-shrink: 0;
        }
        .sidebar-brand-title span {
            color: var(--gold);
        }

        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-user-name {
            color: #fff;
            font-weight: 600;
            font-size: 0.88rem;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-user-role {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 5px;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        .sidebar-user-role.role-admin {
            background: rgba(245,158,11,0.2);
            color: var(--gold-light);
        }
        .sidebar-user-role.role-student {
            background: rgba(59,130,246,0.2);
            color: var(--blue-pale);
        }

        .sidebar-nav {
            flex: 1;
            padding: 12px 0;
        }
        .sidebar-nav-label {
            padding: 8px 20px 4px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.3);
        }
        .sidebar-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 500;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        .sidebar-nav-item:hover {
            background: rgba(255,255,255,0.08);
            color: #fff;
        }
        .sidebar-nav-item.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
            border-left-color: var(--gold);
        }
        .sidebar-nav-item i {
            width: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: var(--gold);
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
            margin-top: auto;
        }
        .sidebar-footer-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,0.45);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .sidebar-footer-link:hover {
            color: rgba(255,255,255,0.8);
        }
        .sidebar-footer-link i {
            color: var(--gold);
            width: 18px;
            text-align: center;
        }
        .sidebar-copyright {
            margin-top: 12px;
            font-size: 0.68rem;
            color: rgba(255,255,255,0.2);
        }

        /* ── Sidebar Overlay (mobile) ── */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            transition: opacity 0.3s ease;
        }
        .sidebar-overlay.show {
            display: block;
        }

        /* ── Topbar ── */
        .topbar {
            position: sticky;
            top: 4px;
            z-index: 1030;
            background: #fff;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--gray-700);
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .topbar-hamburger:hover {
            background: var(--gray-100);
        }
        .topbar-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--navy);
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .topbar-user-name {
            font-size: 0.82rem;
            color: var(--gray-600);
            font-weight: 500;
        }
        .topbar-user-name strong {
            color: var(--navy);
            font-weight: 600;
        }

        /* ── Main Wrapper ── */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: calc(100vh - 4px);
            transition: margin-left 0.3s ease;
        }

        /* ── Cards ── */
        .card {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            background: #fff;
        }
        .card-header {
            border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
            font-weight: 600;
            border-bottom: 1px solid var(--gray-200);
        }

        /* ── Badges ── */
        .badge-status {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .badge-en_attente, .badge-en_cours { background: var(--warning-light); color: var(--warning); }
        .badge-valide { background: var(--success-light); color: var(--success); }
        .badge-rejete { background: var(--danger-light); color: var(--danger); }
        .badge-soumis { background: var(--info-light); color: var(--info); }
        .badge-incomplet { background: var(--gray-100); color: var(--gray-600); }

        /* ── Progress ── */
        .progress-track {
            height: 8px;
            border-radius: 4px;
            background: var(--gray-200);
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }
        .progress-fill.green { background: var(--success); }
        .progress-fill.gold { background: var(--gold); }
        .progress-fill.red { background: var(--danger); }

        /* ── Document Cards ── */
        .doc-item {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 16px;
            background: #fff;
            transition: all 0.2s;
            position: relative;
        }
        .doc-item:hover { border-color: var(--blue-mid); box-shadow: var(--shadow); }
        .doc-item.uploaded { border-color: var(--success); background: var(--success-light); }
        .doc-item.rejected { border-color: var(--danger); background: var(--danger-light); }
        .doc-item.validated { border-color: var(--success); background: #f0fdf4; }
        .doc-item .doc-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .doc-item .doc-icon.empty { background: var(--gray-100); color: var(--gray-400); }
        .doc-item .doc-icon.pending { background: var(--warning-light); color: var(--warning); }
        .doc-item .doc-icon.valid { background: var(--success-light); color: var(--success); }
        .doc-item .doc-icon.rejected { background: var(--danger-light); color: var(--danger); }

        /* ── Upload Zone ── */
        .upload-zone {
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius-lg);
            padding: 48px 24px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            background: var(--gray-50);
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--blue-mid);
            background: var(--blue-50);
        }

        /* ── Stat Cards ── */
        .stat-box {
            background: #fff;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
        }
        .stat-box .stat-num {
            font-size: 1.75rem;
            font-weight: 800;
            line-height: 1;
        }
        .stat-box .stat-label {
            font-size: 0.78rem;
            color: var(--gray-500);
            margin-top: 4px;
            font-weight: 500;
        }

        /* ── Buttons ── */
        .btn-primary {
            background: var(--blue-mid);
            border-color: var(--blue-mid);
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-primary:hover { background: var(--blue); border-color: var(--blue); }
        .btn-gold {
            background: var(--gold);
            border: none;
            color: var(--navy);
            font-weight: 700;
            border-radius: 8px;
        }
        .btn-gold:hover { background: var(--gold-light); color: var(--navy); }
        .btn-success { border-radius: 8px; font-weight: 600; }

        /* ── Button loading spinner ── */
        .btn-loading {
            pointer-events: none;
            opacity: 0.75;
            position: relative;
        }
        .btn-loading .btn-spinner {
            display: inline-block !important;
        }
        .btn-loading .btn-label {
            visibility: hidden;
        }
        .btn-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2.5px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: btnSpinAnim 0.6s linear infinite;
            position: absolute;
            left: 50%;
            top: 50%;
            margin-left: -8px;
            margin-top: -8px;
        }
        .btn-outline-primary .btn-spinner,
        .btn-outline-danger .btn-spinner,
        .btn-outline-success .btn-spinner {
            border-color: rgba(0,0,0,0.15);
            border-top-color: currentColor;
        }
        .btn-gold .btn-spinner {
            border-color: rgba(15,23,42,0.2);
            border-top-color: #0f172a;
        }
        @keyframes btnSpinAnim { to { transform: rotate(360deg); } }

        /* ── Tables ── */
        .table th {
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gray-500);
            border-bottom: 2px solid var(--gray-200);
        }

        /* ── Forms ── */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            padding: 10px 14px;
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--blue-mid);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        /* ── Alerts ── */
        .alert { border-radius: var(--radius); border: none; font-size: 0.9rem; }

        /* ── Responsive ── */
        @media (min-width: 992px) {
            .sidebar {
                transform: translateX(0);
            }
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .topbar {
                margin-left: 0;
            }
            .topbar-hamburger {
                display: block;
            }
            .main-wrapper {
                margin-left: 0;
            }
        }

        @media (max-width: 576px) {
            .topbar {
                padding: 0 14px;
            }
            .topbar-user-name {
                display: none;
            }
        }
        /* ── Preloader ── */
        .page-preloader {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: linear-gradient(160deg, #0a1628 0%, #132042 40%, #1e3a8a 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        .page-preloader.hide {
            opacity: 0;
            visibility: hidden;
        }

        .preloader-logo {
            width: 64px;
            height: 64px;
            background: var(--gold);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            animation: preloaderPulse 2s ease-in-out infinite;
            box-shadow: 0 0 40px rgba(245, 158, 11, 0.25);
        }
        .preloader-logo i {
            font-size: 1.6rem;
            color: var(--navy);
        }

        .preloader-spinner {
            position: relative;
            width: 48px;
            height: 48px;
            margin-bottom: 24px;
        }
        .preloader-spinner .ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 3px solid transparent;
        }
        .preloader-spinner .ring-1 {
            border-top-color: var(--gold);
            animation: preloaderSpin 1s linear infinite;
        }
        .preloader-spinner .ring-2 {
            inset: 5px;
            border-right-color: var(--blue-light);
            animation: preloaderSpin 1.5s linear infinite reverse;
        }
        .preloader-spinner .ring-3 {
            inset: 10px;
            border-bottom-color: rgba(255,255,255,0.3);
            animation: preloaderSpin 2s linear infinite;
        }

        .preloader-text {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            margin-bottom: 6px;
        }
        .preloader-sub {
            color: rgba(255,255,255,0.35);
            font-size: 0.75rem;
            font-weight: 400;
        }

        .preloader-dots {
            display: inline-flex;
            gap: 4px;
            margin-left: 4px;
        }
        .preloader-dots span {
            width: 4px;
            height: 4px;
            background: var(--gold);
            border-radius: 50%;
            animation: preloaderDot 1.4s ease-in-out infinite;
        }
        .preloader-dots span:nth-child(2) { animation-delay: 0.2s; }
        .preloader-dots span:nth-child(3) { animation-delay: 0.4s; }

        .preloader-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            overflow: hidden;
        }
        .preloader-bar::after {
            content: '';
            display: block;
            height: 100%;
            width: 40%;
            background: linear-gradient(90deg, transparent, var(--gold), var(--gold-light), transparent);
            animation: preloaderSlide 1.2s ease-in-out infinite;
        }

        @keyframes preloaderPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 40px rgba(245, 158, 11, 0.25); }
            50% { transform: scale(1.05); box-shadow: 0 0 60px rgba(245, 158, 11, 0.35); }
        }
        @keyframes preloaderSpin {
            to { transform: rotate(360deg); }
        }
        @keyframes preloaderDot {
            0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
            40% { opacity: 1; transform: scale(1.2); }
        }
        @keyframes preloaderSlide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(350%); }
        }
    </style>
</head>
<body>

<!-- Preloader -->
<div class="page-preloader" id="pagePreloader">
    <div class="preloader-logo">
        <i class="fas fa-graduation-cap"></i>
    </div>
    <div class="preloader-spinner">
        <div class="ring ring-1"></div>
        <div class="ring ring-2"></div>
        <div class="ring ring-3"></div>
    </div>
    <div class="preloader-text">
        Espace Scolarité
        <div class="preloader-dots"><span></span><span></span><span></span></div>
    </div>
    <div class="preloader-sub"><?= sanitize($univSigle ?: $univNom) ?></div>
    <div class="preloader-bar"></div>
</div>

<!-- Top Stripe -->
<div class="top-stripe"><span></span><span></span><span></span></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <?php if (!empty($univLogo)): ?>
            <div class="text-center mb-2">
                <img src="https://inbtpkinshasa.info/<?= sanitize($univLogo) ?>" alt="Logo" style="max-height:40px;max-width:100%;object-fit:contain;">
            </div>
        <?php endif; ?>
        <div class="sidebar-brand-title">
            <div class="sidebar-brand-icon"><i class="fas fa-graduation-cap"></i></div>
            Espace <span>Scolarité</span>
        </div>
        <div style="font-size:0.7rem;color:rgba(255,255,255,0.4);margin-top:4px;padding-left:48px;"><?= sanitize($univSigle ?: $univNom) ?></div>
    </div>

    <div class="sidebar-user">
        <div class="d-flex align-items-center gap-2 mb-1">
            <?php if (!empty($userPhoto)): ?>
                <img src="https://inbtpkinshasa.info/uploads/<?= sanitize($userPhoto) ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.2);flex-shrink:0;">
            <?php else: ?>
                <i class="fas fa-user-circle" style="font-size:32px;color:rgba(255,255,255,0.4);flex-shrink:0;"></i>
            <?php endif; ?>
            <div class="sidebar-user-name" style="margin-bottom:0;"><?= sanitize($userName) ?></div>
        </div>
        <?php if ($isAdminUser): ?>
            <span class="sidebar-user-role role-admin" style="margin-left:40px;"><i class="fas fa-shield-alt me-1"></i><?= sanitize($userRole) ?></span>
        <?php elseif ($isStudent): ?>
            <span class="sidebar-user-role role-student" style="margin-left:40px;"><i class="fas fa-user-graduate me-1"></i>Étudiant</span>
        <?php endif; ?>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-nav-label">Navigation</div>

        <?php if ($isAdminUser): ?>
            <a href="index.php?action=admin" class="sidebar-nav-item <?= $currentAction === 'admin' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="index.php?action=admin_types_documents" class="sidebar-nav-item <?= $currentAction === 'admin_types_documents' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice"></i> Types de Documents
            </a>
            <a href="index.php?action=admin_list" class="sidebar-nav-item <?= $currentAction === 'admin_list' || $currentAction === 'admin_detail' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Liste Étudiants
            </a>
        <?php elseif ($isStudent): ?>
            <a href="index.php?action=dashboard" class="sidebar-nav-item <?= $currentAction === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="index.php?action=upload_list" class="sidebar-nav-item <?= $currentAction === 'upload' || $currentAction === 'upload_list' ? 'active' : '' ?>">
                <i class="fas fa-upload"></i> Soumettre documents
            </a>
            <a href="index.php?action=mes_documents" class="sidebar-nav-item <?= $currentAction === 'mes_documents' ? 'active' : '' ?>">
                <i class="fas fa-file-alt"></i> Mes documents
            </a>
        <?php endif; ?>

        <div class="sidebar-nav-label" style="margin-top:16px;">Compte</div>
        <a href="index.php?action=logout" class="sidebar-nav-item">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="https://inbtpkinshasa.info/" class="sidebar-footer-link">
            <i class="fas fa-arrow-left"></i> Retour à E-Gestion
        </a>
        <div class="sidebar-copyright">
            &copy; <?= date('Y') ?> — <?= sanitize($univSigle ?: $univNom) ?>
        </div>
    </div>
</aside>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Main wrapper -->
<div class="main-wrapper">
    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-left">
            <button class="topbar-hamburger" id="sidebarToggle" type="button" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="topbar-title"><?= sanitize($pageTitle ?? 'Espace de Scolarité') ?></div>
        </div>
        <div class="topbar-right">
            <div class="d-flex align-items-center gap-2">
                <div class="topbar-user-name">
                    <strong><?= sanitize($userName) ?></strong>
                    <?php if ($isStudent && !empty($_SESSION['dossier_student_matricule'])): ?>
                        <span style="color:var(--gold);margin-left:4px;"><?= sanitize($_SESSION['dossier_student_matricule']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($userPhoto)): ?>
                    <img src="http://inbtpkinshasa.info/uploads/<?= sanitize($userPhoto) ?>" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid var(--gray-200);">
                <?php else: ?>
                    <i class="fas fa-user-circle" style="font-size:32px;color:var(--blue-mid);"></i>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Page content starts here -->
