<?php
// Vérification de la connexion
if (!isset($_SESSION['student_id'])) {
    header('Location: login');
    exit();
}

// Inclure les fichiers de modèle nécessaires
require_once dirname(__DIR__) . '/../config/Connexion.php';
require_once dirname(__DIR__) . '/../models/Etudiant.php';
require_once dirname(__DIR__) . '/../models/Universite.php';
require_once dirname(__DIR__) . '/../models/Agent.php';
require_once dirname(__DIR__) . '/../models/Cours.php';
require_once dirname(__DIR__) . '/../models/Horaire.php';
require_once dirname(__DIR__) . '/../models/PlanTravail.php';
require_once dirname(__DIR__) . '/../models/Deliberation.php';
require_once dirname(__DIR__) . '/../models/Recours.php';
require_once dirname(__DIR__) . '/../models/Ecue.php';
require_once dirname(__DIR__) . '/../models/GrilleAncienne.php';
require_once dirname(__DIR__) . '/../models/Dette.php';

// Initialisation des modèles
$etudiantModel = new Etudiant();
$universite = new Universite();
$agentModel = new Agent();
$coursModel=new Cours();
$ecueModel = new Ecue();  // Ajoutez cette ligne
$horaireModel = new Horaire(); // Ajoutez cette ligne
$currentYear=$universite->getAnneeAcademiqueById($_SESSION['annee_acad']);

// Récupération des informations de l'étudiant
$studentId = $_SESSION['student_id'];
$studentName = $_SESSION['student_name'] ?? 'Étudiant';
$studentMatricule = $_SESSION['student_matricule'] ?? '';  // Ajoutez cette ligne
$orientationId = $_SESSION['orientation_id'] ?? null;
$cycle = $_SESSION['cycle'] ?? null;

// Récupération des données complètes de l'étudiant pour le profil
$studentData = $etudiantModel->getEtudiantById($studentId);

// Synchroniser la photo en session avec celle de la BD
if (isset($studentData['photo'])) {
    $_SESSION['photo'] = $studentData['photo'];
}

// Récupération des sujets disponibles
try {
    $sujetsDisponibles = $etudiantModel->getSujetsDisponibles($orientationId, $cycle);
    if (!is_array($sujetsDisponibles)) {
        $sujetsDisponibles = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des sujets disponibles: " . $e->getMessage());
    $sujetsDisponibles = [];
}

// Récupération des tâches si un sujet est assigné
try {
    $sujetAssigne = $etudiantModel->getSujetAssigne($studentId);
    $sujetId = $sujetAssigne ? $sujetAssigne['idsujets'] : null;
    $taches = [];

    if ($sujetAssigne) {
        // Ne récupérer les tâches que si le sujet est validé
        if ($sujetAssigne['statut_validation'] === 'Validé') {
            $taches = $etudiantModel->getTaches($sujetId);
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération du sujet assigné: " . $e->getMessage());
    $sujetAssigne = null;
    $taches = [];
}

// Récupération des compteurs de notifications
try {
    $notifications = $etudiantModel->getNotificationCounters($studentId);
    if (!is_array($notifications)) {
        $notifications = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des notifications: " . $e->getMessage());
    $notifications = [];
}

// Récupérer si la promotion de l'étudiant est terminale
$estPromotionTerminale = false;
$promotionId = $_SESSION['promotion_id'] ?? 0;
if ($promotionId > 0) {
    $infoPromotion = $universite->getPromotionById($promotionId);
    $estPromotionTerminale = isset($infoPromotion['est_terminale']) && $infoPromotion['est_terminale'] == 1;
    $estFinalistePremierCycle = $estPromotionTerminale && isset($infoPromotion['cycle']) && $infoPromotion['cycle'] == 'Premier';
}


// Fonctions utilitaires
function getTypeAuteurClass($type, $isCurrentUser = false) {
    if ($isCurrentUser) {
        return 'warning';
    }
    
    return match ($type) {
        'Directeur' => 'primary',
        'Encadreur' => 'success',
        'Etudiant' => 'info',
        default => 'secondary',
    };
}

function getTypeAuteurLabel($type, $isCurrentUser = false) {
    if ($isCurrentUser) {
        return 'Vous';
    }
    
    return $type;
}

$configUniversitee = $universite->getConfigurationUniversite();

// Initialize deliberation and recours models
$deliberationModel = new Deliberation();
$recoursModel = new Recours();

// Add this after existing code that gets student information
// Check if deliberation results have been published for the student's promotion
$deliberationPubliee = false;
$recours = [];
try {
    if (isset($_SESSION['promotion_id']) && !empty($_SESSION['promotion_id'])) {
        $deliberationPubliee = $deliberationModel->isDeliberationPubliee($_SESSION['promotion_id']);
        
        // If published, get student appeals
        if ($deliberationPubliee > 0 && isset($_SESSION['student_matricule'])) {
            $deliberationPubliee = true;
            $recours = $recoursModel->getRecoursByMatricule($_SESSION['student_matricule'], $currentYear['idannee_acad']);
            if (!is_array($recours)) {
                $recours = [];
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des données de délibération/recours: " . $e->getMessage());
    $deliberationPubliee = false;
    $recours = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail Étudiant - ScienceHub Mobile</title>

    <?php if (!empty($configUniversitee['logo'])): ?>
        <!-- Favicons --> 
	<link href="../<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="icon">
	<link href="../<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="apple-touch-icon">
    <?php endif; ?>
    
    <!-- Feuilles de style -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../assets/css/notification-styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #dbeafe;
            --secondary-color: #f8fafc;
            --accent-color: #f59e0b;
            --text-color: #334155;
            --text-light: #64748b;
            --light-text: #fff;
            --danger-color: #ef4444;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --info-color: #0ea5e9;
            --border-color: #e2e8f0;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --header-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --bottom-nav-shadow: 0 -1px 3px 0 rgba(0, 0, 0, 0.1), 0 -1px 2px 0 rgba(0, 0, 0, 0.06);
            --sidebar-width: 280px;
            --header-height: 70px;
            --bottom-nav-height: 65px;
            --border-radius: 12px;
            --transition-speed: 0.3s;
        }

        body {
            background-color: var(--secondary-color);
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            padding-top: var(--header-height);
            padding-bottom: var(--bottom-nav-height);
            line-height: 1.6;
        }

        /* Header styles */
        .mobile-header {
            background-color: var(--light-text);
            padding: 15px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--header-shadow);
            height: var(--header-height);
            display: flex;
            align-items: center;
        }

        .mobile-header h1 {
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .menu-toggle {
            color: var(--primary-color);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all var(--transition-speed) ease;
        }

        .menu-toggle:hover {
            color: var(--primary-dark);
            transform: scale(1.1);
        }

        /* Bottom navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: var(--bottom-nav-shadow);
            z-index: 1000;
            height: var(--bottom-nav-height);
            display: flex;
            align-items: center;
            border-top: 1px solid var(--border-color);
        }

        .nav-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all var(--transition-speed) ease;
        }

        .nav-item i {
            font-size: 1.2rem;
            margin-bottom: 4px;
            transition: all var(--transition-speed) ease;
        }

        .nav-item.active {
            color: var(--primary-color);
            font-weight: 500;
        }

        .nav-item.active i {
            transform: translateY(-2px);
        }

        .nav-item:hover {
            color: var(--primary-color);
        }

        /* Content area */
        .content-area {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Cards */
        .subject-card, .task-card {
            background: white;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .subject-card:hover, .task-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 12px;
        }

        /* Status badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            color: white;
            display: inline-flex;
            align-items: center;
        }

        .status-badge i {
            margin-right: 4px;
        }

        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 30px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 999;
        }

        .fab i {
            font-size: 1.5rem;
        }

        .fab:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.25);
            color: white;
        }

        /* Profile menu */
        .profile-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            display: none;
            z-index: 1001;
            min-width: 260px;
            max-width: 320px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .profile-menu.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-menu-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-light) 0%, #eff6ff 100%);
            border-bottom: 1px solid var(--border-color);
        }

        .profile-menu-avatar {
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
            flex-shrink: 0;
        }

        .profile-menu-info {
            min-width: 0;
            overflow: hidden;
        }

        .profile-menu-name {
            display: block;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text-color);
        }

        .profile-menu-matricule {
            display: block;
            font-size: 0.75rem;
            color: var(--text-light);
        }

        .profile-menu-body {
            padding: 12px 16px;
        }

        .profile-menu-year {
            display: flex;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-light);
            padding: 8px 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .profile-menu-actions {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .profile-menu-btn {
            display: flex;
            align-items: center;
            padding: 9px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .profile-menu-btn-profile {
            color: var(--primary-color);
            background: transparent;
        }

        .profile-menu-btn-profile:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .profile-menu-btn-logout {
            color: var(--danger-color);
            background: transparent;
        }

        .profile-menu-btn-logout:hover {
            background: #fee2e2;
            color: #b91c1c;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            padding: 3px 6px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Timeline styles */
        .timeline {
            position: relative;
            padding: 20px 0;
            list-style: none;
            max-height: 350px;
            overflow-y: auto;
        }

        .timeline-item {
            position: relative;
            padding-left: 30px;
            margin-bottom: 25px;
        }

        .timeline-marker {
            position: absolute;
            left: 0;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 3px var(--primary-color);
            background: white;
            z-index: 1;
        }

        .timeline-content {
            background: var(--secondary-color);
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid var(--border-color);
        }

        .timeline:before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--border-color);
        }

        /* Sidebar styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background-color: white;
            box-shadow: var(--card-shadow);
            z-index: 1050;
            transform: translateX(-100%);
            transition: transform var(--transition-speed) ease-in-out;
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        /* Sidebar header */
        .sidebar-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }

        .sidebar-close-btn {
            color: var(--text-light);
            font-size: 1.1rem;
            text-decoration: none;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .sidebar-close-btn:hover {
            background-color: #fee2e2;
            color: var(--danger-color);
        }

        /* Sidebar profile */
        .sidebar-profile {
            padding: 14px 16px;
            background: linear-gradient(135deg, var(--primary-light) 0%, #eff6ff 100%);
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
            overflow: hidden;
        }

        .sidebar-profile .flex-grow-1 {
            min-width: 0;
            overflow: hidden;
        }

        .sidebar-profile .fw-semibold,
        .sidebar-profile .text-muted {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Sidebar menu */
        .sidebar-menu {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }

        .sidebar-menu-label {
            padding: 12px 18px 4px;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            color: var(--text-light);
            text-transform: uppercase;
        }

        .sidebar .nav-link {
            color: var(--text-color);
            padding: 10px 18px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            font-size: 0.88rem;
            border-radius: 0 8px 8px 0;
            margin-right: 8px;
        }

        .sidebar .nav-link i {
            width: 22px;
            margin-right: 12px;
            text-align: center;
            font-size: 1rem;
            color: var(--text-light);
            transition: color 0.2s;
        }

        .sidebar .nav-link:hover {
            background-color: var(--primary-light);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .sidebar .nav-link:hover i {
            color: var(--primary-color);
        }

        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            border-left-color: var(--primary-dark);
            font-weight: 500;
        }

        .sidebar .nav-link.active i {
            color: white;
        }

        /* Sidebar footer */
        .sidebar-footer {
            padding: 12px 16px;
            border-top: 1px solid var(--border-color);
            flex-shrink: 0;
            background: var(--secondary-color);
        }

        .sidebar-footer-year {
            font-size: 0.72rem;
            color: var(--text-light);
            text-align: center;
            margin-bottom: 8px;
        }

        /* Form styles */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        /* Tabs */
        .nav-pills .nav-link {
            border-radius: 8px;
            padding: 10px 15px;
            color: var(--text-color);
            font-weight: 500;
            margin-right: 5px;
            display: flex;
            align-items: center;
        }

        .nav-pills .nav-link i {
            margin-right: 8px;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        /* Accordion */
        .accordion-button {
            padding: 15px;
            font-weight: 500;
            background-color: white;
        }

        .accordion-button:not(.collapsed) {
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        .accordion-button:focus {
            box-shadow: none;
            border-color: var(--primary-light);
        }

        /* Modal styles */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            max-height: calc(100vh - 3.5rem);
            display: flex;
            flex-direction: column;
        }

        .modal-dialog-scrollable .modal-content form {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
            flex: 1;
        }

        .modal-header {
            border-bottom: none;
            padding: 20px 24px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .modal-header .btn-close-white {
            opacity: 0.8;
            transition: opacity 0.2s;
        }

        .modal-header .btn-close-white:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
        }

        .modal-body .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-color);
            margin-bottom: 6px;
        }

        .modal-body .form-control,
        .modal-body .form-select {
            border-radius: 10px;
            border: 1.5px solid var(--border-color);
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .modal-body .form-control:focus,
        .modal-body .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb, 13, 110, 253), 0.15);
        }

        .modal-body .form-text {
            font-size: 0.78rem;
            color: var(--text-light);
            margin-top: 4px;
        }

        .modal-body textarea.form-control {
            min-height: 100px;
        }

        .modal-footer {
            border-top: 1px solid #f0f0f0;
            padding: 16px 24px;
            background: #fafbfc;
            gap: 8px;
        }

        .modal-footer .btn {
            border-radius: 10px;
            padding: 9px 20px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .modal-content {
            max-height: calc(100vh - 3.5rem);
            display: flex;
            flex-direction: column;
        }

        /* Modal sections */
        .modal-section {
            background: #f8f9fb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #edf0f4;
        }

        .modal-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-color);
            margin-bottom: 16px;
        }

        .modal-section-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            font-size: 0.8rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .modal-section .form-control,
        .modal-section .form-select {
            background: white;
        }

        .modal-section-info {
            display: flex;
            align-items: flex-start;
            gap: 4px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #eff6ff 0%, #f0f7ff 100%);
            border-radius: 10px;
            border-left: 3px solid var(--primary-color);
            font-size: 0.83rem;
            color: #475569;
            line-height: 1.5;
        }

        .modal-section-info i {
            color: var(--primary-color);
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* Utilities */
        .required:after {
            content: " *";
            color: var(--danger-color);
            font-weight: bold;
        }

        /* Course list styles */
        .list-group-item {
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .list-group-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        .list-group-item-action {
            color: var(--text-color);
        }

        /* Table styles */
        .table {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 0 0 1px var(--border-color);
        }

        .table th {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            font-weight: 600;
            border-bottom: none;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Alert styles */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 15px;
            margin-bottom: 20px;
        }

        .alert-info {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
        }

        /* Badge styles */
        .badge {
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .bg-primary {
            background-color: var(--primary-color) !important;
        }

        .bg-success {
            background-color: var(--success-color) !important;
        }

        .bg-warning {
            background-color: var(--warning-color) !important;
        }

        .bg-danger {
            background-color: var(--danger-color) !important;
        }

        .bg-info {
            background-color: var(--info-color) !important;
        }

        .bg-secondary {
            background-color: var(--text-light) !important;
        }

        /* Schedule styles */
        .schedule-day {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 15px;
            overflow: hidden;
        }

        .schedule-day-header {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            padding: 12px 15px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .schedule-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .schedule-item:last-child {
            border-bottom: none;
        }

        .schedule-item:hover {
            background-color: var(--secondary-color);
        }

        .schedule-time {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Course details */
        .course-details h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-light);
        }

        .course-details p {
            margin-bottom: 10px;
        }

        /* Recours styles */
        .recours-item {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .recours-header {
            padding: 15px;
            background-color: var(--primary-light);
            color: var(--primary-dark);
            font-weight: 600;
        }

        .recours-body {
            padding: 15px;
        }

        .recours-footer {
            padding: 15px;
            background-color: var(--secondary-color);
            border-top: 1px solid var(--border-color);
        }

        /* ===== Scrollable tab pills (all screens) ===== */
        .nav-pills-wrapper {
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .nav-pills-wrapper::-webkit-scrollbar {
            display: none;
        }

        #mainTab.nav-pills {
            flex-wrap: nowrap;
            white-space: nowrap;
        }

        .nav-pills .nav-item {
            flex: 0 0 auto;
        }

        .nav-pills .nav-link {
            white-space: nowrap;
        }

        /* ===== MOBILE RESPONSIVE ===== */
        @media (max-width: 767.98px) {
            :root {
                --header-height: 56px;
                --bottom-nav-height: 60px;
            }

            body {
                padding-top: var(--header-height);
                padding-bottom: var(--bottom-nav-height);
            }

            /* Mobile header compact */
            .mobile-header {
                height: var(--header-height);
                padding: 0 12px;
            }

            .mobile-header h1 {
                font-size: 1rem;
            }

            /* Content area */
            .content-area {
                padding: 12px;
            }

            /* Scrollable tab pills */
            .nav-pills-wrapper {
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                margin: 0 -12px 16px;
                padding: 0 12px;
            }

            .nav-pills-wrapper::-webkit-scrollbar {
                display: none;
            }

            #mainTab.nav-pills {
                flex-wrap: nowrap;
                white-space: nowrap;
                margin-bottom: 0;
                gap: 6px;
            }

            .nav-pills .nav-item {
                flex: 0 0 auto;
            }

            .nav-pills .nav-link {
                padding: 8px 14px;
                font-size: 0.8rem;
                border-radius: 20px;
                white-space: nowrap;
            }

            .nav-pills .nav-link i {
                margin-right: 4px;
                font-size: 0.85rem;
            }

            /* Cards */
            .subject-card, .task-card {
                padding: 14px;
                border-radius: 10px;
            }

            .card {
                border-radius: 10px;
            }

            .card-body {
                padding: 14px;
            }

            /* Sidebar on mobile */
            .sidebar {
                width: 82%;
                max-width: 300px;
                box-shadow: 4px 0 25px rgba(0,0,0,0.18);
                border-right: none;
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.4);
                z-index: 1049;
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
            }

            .sidebar-overlay.show {
                display: block;
                animation: fadeIn 0.2s ease;
            }

            /* Bottom nav */
            .bottom-nav {
                height: var(--bottom-nav-height);
                padding: 4px 0;
                border-top: 1px solid var(--border-color);
                background: #fff;
            }

            .bottom-nav .d-flex {
                height: 100%;
                align-items: center;
            }

            .bottom-nav .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 4px 2px;
                min-width: 0;
                flex: 1;
                gap: 2px;
                text-decoration: none;
            }

            .bottom-nav .nav-item i {
                font-size: 1.15rem;
                margin-bottom: 1px;
            }

            .bottom-nav .nav-item small {
                font-size: 0.6rem;
                line-height: 1.1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                max-width: 56px;
                display: block;
                text-align: center;
            }

            .bottom-nav .nav-item.active {
                color: var(--primary-color);
            }

            .bottom-nav .nav-item.active i {
                transform: scale(1.1);
            }

            /* Tables responsive on mobile */
            .table-responsive {
                border-radius: 8px;
                font-size: 0.85rem;
            }

            .table th, .table td {
                padding: 8px 10px;
                font-size: 0.82rem;
            }

            /* Modal full-screen on mobile */
            .modal-dialog {
                margin: 0;
                max-width: 100%;
                min-height: 100vh;
            }

            .modal-content {
                border-radius: 0;
                min-height: 100vh;
            }

            .modal-header {
                padding: 16px;
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .modal-header .modal-title {
                font-size: 1rem;
            }

            .modal-body {
                padding: 16px;
                max-height: none;
                flex: 1;
            }

            .modal-body .form-control,
            .modal-body .form-select {
                padding: 10px 12px;
                font-size: 0.88rem;
            }

            .modal-footer {
                padding: 12px 16px;
                position: sticky;
                bottom: 0;
                z-index: 10;
            }

            .modal-footer .btn {
                flex: 1;
                padding: 10px 12px;
                font-size: 0.85rem;
            }

            .modal-section {
                padding: 14px;
                margin-bottom: 12px;
            }

            .modal-section-title {
                font-size: 0.88rem;
                margin-bottom: 12px;
            }

            .modal-section-number {
                width: 22px;
                height: 22px;
                font-size: 0.7rem;
            }

            .modal-section-info {
                font-size: 0.78rem;
                padding: 10px 12px;
            }

            /* Buttons stacking on mobile */
            .d-flex.justify-content-between {
                flex-wrap: wrap;
                gap: 8px;
            }

            /* Section titles mobile */
            .tab-pane h4 {
                font-size: 1.1rem;
            }

            .tab-pane .btn {
                font-size: 0.85rem;
                padding: 6px 12px;
            }

            /* Accordion mobile */
            .accordion-button {
                padding: 12px;
                font-size: 0.9rem;
            }

            .accordion-body {
                padding: 12px;
            }

            /* Floating action button */
            .fab {
                bottom: calc(var(--bottom-nav-height) + 12px);
                right: 12px;
                width: 50px;
                height: 50px;
                border-radius: 25px;
            }

            .fab i {
                font-size: 1.2rem;
            }

            /* Stats cards in grid */
            #statistiques-suivi .col-md-3 {
                flex: 0 0 50%;
                max-width: 50%;
                padding: 4px;
            }

            #statistiques-suivi .card-body {
                padding: 10px;
            }

            #statistiques-suivi .card-body i {
                font-size: 1.3rem !important;
                margin-bottom: 4px !important;
            }

            #statistiques-suivi .card-title {
                font-size: 1rem;
            }

            #statistiques-suivi .card-text {
                font-size: 0.7rem;
            }

            /* Badges */
            .badge {
                font-size: 0.7rem;
                padding: 4px 8px;
            }

            /* Profile menu full-width */
            .profile-menu {
                position: fixed;
                top: var(--header-height);
                right: 8px;
                left: 8px;
                min-width: auto;
                max-width: none;
                border-radius: 0 0 12px 12px;
            }

            /* Alert boxes */
            .alert {
                padding: 12px;
                font-size: 0.85rem;
            }

            .alert i.fs-4 {
                font-size: 1.1rem !important;
            }

            /* Course cards col */
            .tab-pane .col-md-6 {
                padding-left: 6px;
                padding-right: 6px;
            }

            .tab-pane .row {
                margin-left: -6px;
                margin-right: -6px;
            }
        }

        /* ===== TABLET ===== */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .content-area {
                padding: 20px;
            }

            .sidebar {
                width: 280px;
            }

            .bottom-nav .nav-item small {
                font-size: 0.7rem;
            }
        }

        /* ===== DESKTOP ===== */
        @media (min-width: 992px) {
            body {
                padding-left: var(--sidebar-width);
            }
            
            .sidebar {
                transform: translateX(0);
                box-shadow: none;
                border-right: 1px solid var(--border-color);
            }

            .sidebar-close-btn {
                display: none;
            }
            
            .menu-toggle {
                display: none;
            }

            .sidebar-overlay {
                display: none !important;
            }
            
            .content-area {
                padding: 30px;
            }

            .bottom-nav {
                display: none;
            }
        }

        /* Animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
<?php 
    function getStatusColor($status) {
        return match (strtolower($status)) {
            'en attente' => 'warning',
            'validé', 'validée', 'terminé', 'terminée' => 'success',
            'rejeté', 'rejetée', 'refusé', 'refusée' => 'danger',
            'en cours' => 'info',
            default => 'secondary'
        };
    }
    
?>