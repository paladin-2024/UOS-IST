<?php
// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php?view=admin/login');
    exit;
}

// Récupérer le nombre de messages non lus pour le badge
$db = Connexion::getInstance()->getPDO();
$stmt = $db->query("SELECT COUNT(*) as total FROM contact_submissions WHERE is_read = 0");
$unreadMessages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Définir le titre par défaut et le contenu
$pageTitle = $pageTitle ?? 'Administration';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISTM Beni - <?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <?php if (isset($extraCss)): echo $extraCss; endif; ?>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="index.php?view=admin/dashboard">ISTM Beni Admin</a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="w-100"></div>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap d-flex align-items-center">
                <span class="nav-link px-3 text-white">Bienvenue, <?php echo $_SESSION['admin_fullname']; ?></span>
                <a class="nav-link px-3" href="index.php?view=admin/logout">Déconnexion</a>
            </div>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($_GET['view'] === 'admin/dashboard') ? 'active' : ''; ?>" href="index.php?view=admin/dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Tableau de bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/pages') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/pages">
                                <i class="fas fa-file-alt me-2"></i>
                                Pages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/news') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/news">
                                <i class="fas fa-newspaper me-2"></i>
                                Actualités
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/formations') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/formations">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Formations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/staff') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/staff">
                                <i class="fas fa-users me-2"></i>
                                Personnel
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/events') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/events">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Événements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/departments') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/departments">
                                <i class="fas fa-building me-2"></i>
                                Départements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/gallery') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/gallery">
                                <i class="fas fa-images me-2"></i>
                                Galerie
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/contact') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/contact">
                                <i class="fas fa-envelope me-2"></i>
                                Messages
                                <?php if ($unreadMessages > 0): ?>
                                    <span class="badge bg-danger"><?php echo $unreadMessages; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>

                    <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Configuration</span>
                    </h6>
                    <ul class="nav flex-column mb-2">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($_GET['view'] === 'admin/settings') ? 'active' : ''; ?>" href="index.php?view=admin/settings">
                                <i class="fas fa-cog me-2"></i>
                                Paramètres
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_GET['view'], 'admin/users') === 0) ? 'active' : ''; ?>" href="index.php?view=admin/users">
                                <i class="fas fa-user-shield me-2"></i>
                                Utilisateurs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($_GET['view'] === 'admin/menus') ? 'active' : ''; ?>" href="index.php?view=admin/menus">
                                <i class="fas fa-bars me-2"></i>
                                Menus
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php" target="_blank">
                                <i class="fas fa-external-link-alt me-2"></i>
                                Voir le site
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if (isset($content)): ?>
                    <?php echo $content; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script>
        // Initialiser les icônes Feather
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
        });
    </script>
    <?php if (isset($extraJs)): echo $extraJs; endif; ?>
</body>
</html>