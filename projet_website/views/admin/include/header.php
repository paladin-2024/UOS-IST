<?php
// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login');
    exit;
}

// Récupérer le rôle de l'utilisateur
$userRole = $_SESSION['admin_role'] ?? 'editor'; // Par défaut éditeur si non défini
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISTM Beni - Administration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="../uploads/logo.png" type="image/x-icon">
    <style>
        :root {
            --primary-color: #003366;
            --secondary-color: #ffaa00;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: var(--primary-color);
            color: white;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, .75);
            padding: .8rem 1rem;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
            border-left: 3px solid var(--secondary-color);
        }
        
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
            border-left: 3px solid var(--secondary-color);
        }
        
        .sidebar-heading {
            font-size: .75rem;
            text-transform: uppercase;
            padding: 1rem;
            color: rgba(255, 255, 255, .5);
            letter-spacing: 1px;
        }
        
        main {
            padding-top: 48px;
        }
        
        .navbar {
            background-color: var(--primary-color) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255, 255, 255, .8);
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: none;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem 1.25rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #004080;
            border-color: #004080;
        }
        
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }
        
        .list-group-item {
            border-left: none;
            border-right: none;
            padding: 1rem 1.25rem;
        }
        
        .list-group-item:first-child {
            border-top: none;
        }
        
        .list-group-item:last-child {
            border-bottom: none;
        }
        
        /* Profile image in navbar */
        .profile-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: var(--primary-color);
            color: white;
            transition: all 0.3s;
            overflow-y: auto; /* Ajoute la barre de défilement verticale */
            scrollbar-width: thin; /* Pour Firefox */
            scrollbar-color: rgba(255, 255, 255, 0.2) transparent; /* Pour Firefox */
        }
        
        /* Style pour la barre de défilement - Webkit (Chrome, Safari, Edge) */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        
        /* Amélioration du menu pour aspect plus professionnel */
        .sidebar .position-sticky {
            height: calc(100vh - 48px);
            padding-bottom: 20px;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, .75);
            padding: .7rem 1rem;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-weight: 500;
            display: flex;
            align-items: center;
            margin: 2px 10px;
            border-radius: 4px;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, .15);
            border-left: 3px solid var(--secondary-color);
        }
        
        .sidebar-heading {
            font-size: .8rem;
            text-transform: uppercase;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, .6);
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .sidebar .nav-item i {
            width: 24px;
            text-align: center;
            margin-right: 8px;
            opacity: 0.85;
        }

        
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="dashboard">
            <i class="fas fa-graduation-cap me-2"></i>ISTM Beni Admin
        </a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="w-100"></div>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap d-flex align-items-center">
                <span class="nav-link px-3 text-white">
                    <img src="../uploads/user.png" alt="Profile" class="profile-img">
                    Bienvenue, <?php echo $_SESSION['admin_fullname']; ?>
                    <?php if ($userRole === 'admin'): ?>
                        <span class="badge bg-danger ms-1">Administrateur</span>
                    <?php elseif ($userRole === 'editor'): ?>
                        <span class="badge bg-primary ms-1">Éditeur</span>
                    <?php elseif ($userRole === 'manager'): ?>
                        <span class="badge bg-success ms-1">Gestionnaire</span>
                    <?php endif; ?>
                </span>
                <a class="nav-link px-3" href="logout">
                    <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                </a>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <!-- Tableau de bord accessible à tous -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de bord
                            </a>
                        </li>
                        
                        <!-- Gestion de contenu - accessible aux éditeurs, gestionnaires et admin -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'pages' ? 'active' : ''; ?>" href="pages">
                                <i class="fas fa-file-alt me-2"></i>
                                Pages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'news' ? 'active' : ''; ?>" href="news">
                                <i class="fas fa-newspaper me-2"></i>
                                Actualités
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'formations' ? 'active' : ''; ?>" href="formations">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Formations
                            </a>
                        </li>
                        
                        <!-- Gestion du personnel - accessible aux gestionnaires et admin -->
                        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'staff' ? 'active' : ''; ?>" href="staff">
                                <i class="fas fa-users me-2"></i>
                                Personnel
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Événements et galerie - accessibles à tous -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'events' ? 'active' : ''; ?>" href="events">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Événements
                            </a>
                        </li>
                        <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'departments' ? 'active' : ''; ?>" href="departments">
                                <i class="fas fa-building me-2"></i>
                                Départements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'partners' ? 'active' : ''; ?>" href="partners">
                                <i class="fas fa-handshake me-2"></i>
                                Partenaires
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'management_committee' ? 'active' : ''; ?>" href="management_committee">
                                <i class="fas fa-users me-2"></i>
                                Comité de gestion
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'gallery' ? 'active' : ''; ?>" href="gallery">
                                <i class="fas fa-images me-2"></i>
                                Galerie
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'contact' ? 'active' : ''; ?>" href="contact">
                                <i class="fas fa-envelope me-2"></i>
                                Messages
                                <?php if (isset($unreadMessages) && $unreadMessages > 0): ?>
                                    <span class="badge bg-danger ms-2"><?php echo $unreadMessages; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>

                    <!-- Configuration - accessible uniquement aux administrateurs -->
                    <?php if ($userRole === 'admin'): ?>
                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span style="color:#f5f7fb!important">Configuration</span>
                    </h6>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="settings">
                                <i class="fas fa-cog me-2"></i>
                                Paramètres
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'statistiques' ? 'active' : ''; ?>" href="statistiques">
                                <i class="fas fa-chart-line me-2"></i>
                                Statistiques
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'users' ? 'active' : ''; ?>" href="users">
                                <i class="fas fa-user-shield me-2"></i>
                                Utilisateurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage === 'menus' ? 'active' : ''; ?>" href="menus">
                                <i class="fas fa-bars me-2"></i>
                                Menus
                            </a>
                        </li>
                    </ul>
                    <?php endif; ?>
                    
                    <!-- Lien vers le site public - accessible à tous -->
                    <ul class="nav flex-column mt-3">
                        <li class="nav-item">
                            <a class="nav-link" href="../" target="_blank">
                                <i class="fas fa-external-link-alt me-2"></i>
                                Voir le site
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">