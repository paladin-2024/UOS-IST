<?php
require_once 'config/config.php';

// Dynamically detect base URL for local and production environments
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the script name and derive the base path
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // Remove filename (index.php) to get directory
    $path = dirname($scriptName);
    
    // Normalize path - remove trailing slashes except root
    if ($path !== '/' && $path !== '\\') {
        $path = rtrim($path, '/');
    }
    
    // If path is root, use empty string, otherwise use the path
    $basePath = ($path === '/' || $path === '\\') ? '' : $path;
    
    return $protocol . '://' . $host . $basePath . '/';
}

$baseUrl = getBaseUrl();

if(!isset($_SESSION['id'])) header("Location:../accueil");

$roles = new Role();

$universite = new Universite();

$activite=new SuperUser();

function getUserIpAddress() {
	return $_SERVER['REMOTE_ADDR'];
}

function getUserAgent() {
	return $_SERVER['HTTP_USER_AGENT'];
}

$configUniversitee = $universite->getConfigurationUniversite();

?>
<html>
<head>
	<meta charset="UTF-8">
	<meta content="width=device-width, initial-scale=1.0" name="viewport">

	<title><?php echo htmlspecialchars($configUniversitee['nom_application'] ?? 'E-GESTION'); ?></title>
	<base href="<?php echo htmlspecialchars($baseUrl); ?>"> <!-- Chemin de base dynamique -->
	<meta content="<?php echo htmlspecialchars($configUniversitee['nom_application'] ?? 'E-GESTION'); ?> est une solution innovante conçue pour la gestion des universités, permettant une gestion optimisée des processus administratifs, financiers et logistiques. Notre plateforme allie efficacité et simplicité pour répondre aux besoins des professionnels du secteur" name="description">
	<meta content="<?php echo htmlspecialchars($configUniversitee['nom_application'] ?? 'E-GESTION'); ?>" name="keywords">

	<?php if (!empty($configUniversitee['logo'])): ?>
        <!-- Favicons --> 
	<link href="./<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="icon">
	<link href="./<?= htmlspecialchars($configUniversitee['logo']) ?>" rel="apple-touch-icon">
    <?php endif; ?>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

	<!-- Vendor CSS Files -->
	<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<link href="assets/vendor/icon_bootstrap/bootstrap-icons.css" rel="stylesheet">
	<link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
	<link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
	<link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
	<link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
	<link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
	
	<!-- Select2 CSS - UNE SEULE VERSION -->
	<link href="assets/vendor/select2/select2.min.css" rel="stylesheet">
	<link href="assets/vendor/select2-bootstrap-theme/select2-bootstrap.min.css" rel="stylesheet">

	<!-- Initialisation de la configuration -->
	<script>
        const APP_CONFIG = <?php echo json_encode(AppConfig::getJsConfig()); ?>;
    </script>

	<script src="assets/js/DataLoader.js"></script>

	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

	<link href="assets/DataTables/datatables.min.css" rel="stylesheet">
 
	<!-- jQuery - UN SEUL CHARGEMENT -->
	<script src="assets/js/jquery.min.js"></script>
	
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
	<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

	<!-- Remplacer la ligne TinyMCE par celle-ci -->
	<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>

	<!-- Ajoutez cette ligne dans le head si elle n'existe pas déjà -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

	<!-- Template Main CSS File -->
	<link href="assets/css/style.css" rel="stylesheet">
    
	<style>
		/* Style du conteneur de préchargement */
		#preloader {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(255, 255, 255, 0.9);
			display: flex;
			align-items: center;
			justify-content: center;
			z-index: 9999;
		}

		.spinner {
			width: 50px;
			height: 50px;
			border: 5px solid #ccc;
			border-top-color: #007bff;
			border-radius: 50%;
			animation: spin 1s linear infinite;
		}

		@keyframes spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}

		#uploadProgress {
			width: 100%;
			background-color: #f3f3f3;
			border-radius: 5px;
			overflow: hidden;
			margin-top: 10px;
		}

		#uploadProgress .progress-bar {
			height: 20px;
			background-color: #4caf50; /* Green color for the progress */
			width: 0;
			transition: width 0.4s ease;
		}
		
		/* Style de base pour tous les select2 - z-index normal */
		.select2-container {
			z-index: auto;
		}

		.select2-dropdown {
			z-index: 1000;
		}

		/* Assurer que les select2 dans un modal sont au-dessus */
		.modal .select2-container {
			z-index: 2060;
		}

		.modal .select2-dropdown {
			z-index: 2061;
		}

		/* Ajustement pour Bootstrap Modal */
		.modal {
			z-index: 1055;
		}

		.modal-backdrop {
			z-index: 1050;
		}

		/* S'assurer que le select2 s'adapte correctement dans les modals */
		.select2-selection__rendered {
			line-height: calc(1.5em + 0.75rem);
		}

		.select2-container .select2-selection--single {
			height: calc(1.5em + 0.75rem + 2px);
			padding: 0.375rem 0.75rem;
			border-radius: 0.25rem;
		}

		.select2-selection__arrow {
			height: calc(1.5em + 0.75rem + 2px);
		}
	</style>

<style>
    .stat-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
		margin-top: 5px !important;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 8px;
        font-weight: bold;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
    
    .stat-label {
        font-size: 0.8rem;
        color: #6c757d;
    }
    
    .course-card:hover .card {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    .card {
        transition: all 0.3s ease;
    }
    
    .card-header {
        border-bottom: none;
    }
    
    .progress {
        border-radius: 10px;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .progress-bar {
        transition: width 1s ease;
    }
</style>

<style>
    /* Diminuer la taille de la police pour toute la table */
    #tableGrilleNotes {
        font-size: 0.85rem;
    }
    
    /* Augmenter la hauteur des colonnes ECUE et améliorer l'affichage vertical */
    .subject-header {
        writing-mode: vertical-lr;
        transform: rotate(180deg);
        white-space: nowrap;
        padding: 5px 2px;
        height: 200px; /* Augmenter la hauteur */
        text-align: center;
        overflow: hidden;
    }
    
    /* Styles pour les groupes de cours */
    .course-group {
        background-color: #d9d9d9;
        text-align: center;
        font-weight: bold;
        font-size: 0.9rem;
    }
    
    /* Styles pour les lignes alternées */
    #tableGrilleNotes tbody tr:nth-child(odd) {
        background-color: #f9f9f9;
    }
    
    /* Styles pour les cellules de données */
    #tableGrilleNotes td {
        padding: 4px;
        vertical-align: middle;
    }
    
    /* Styles pour l'impression */
    @media print {
        #tableGrilleNotes {
            font-size: 8pt;
        }
        
        .course-group {
            background-color: #d9d9d9 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .bg-light {
            background-color: #f8f9fa !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .bg-info {
            background-color: #17a2b8 !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        .bg-success {
            background-color: #28a745 !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<!-- Style pour les animations des toasts -->
<style>
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1100;
    }
    
    .toast-animated {
        opacity: 0;
        transform: translateX(100%);
        animation: toast-slide-in 0.3s ease forwards;
    }
    
    @keyframes toast-slide-in {
        0% {
            opacity: 0;
            transform: translateX(100%);
        }
        50% {
            opacity: 1;
            transform: translateX(-10%);
        }
        100% {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .toast-hide {
        animation: toast-slide-out 0.3s ease forwards;
    }
    
    @keyframes toast-slide-out {
        0% {
            opacity: 1;
            transform: translateX(0);
        }
        100% {
            opacity: 0;
            transform: translateX(100%);
        }
    }
</style>

<!-- Ajoutez ce style dans la section head ou juste avant la fermeture de l'en-tête -->
<style>
    /* Réduire la taille de police générale pour cette page */
    .livraison-edit-page {
        font-size: 0.85rem;
    }
    
    /* Réduire spécifiquement la taille dans le tableau des produits */
    #productTable {
        font-size: 0.8rem;
    }
    
    /* Réduire la hauteur des lignes du tableau */
    #productTable td {
        padding: 0.4rem;
    }
    
    /* Réduire la taille des inputs et selects dans le tableau */
    #productTable .form-control,
    #productTable .form-select,
    #productTable .select2-container--default .select2-selection--single {
        padding: 0.25rem 0.5rem;
        height: calc(1.5em + 0.5rem + 2px);
        font-size: 0.8rem;
    }
    
    /* Ajuster la taille des boutons dans le tableau */
    #productTable .btn-sm {
        padding: 0.15rem 0.3rem;
        font-size: 0.75rem;
    }

    

    #prescriptionsTable {
        font-size: 0.8rem;
    }
    
    /* Réduire la hauteur des lignes du tableau */
    #prescriptionsTable td {
        padding: 0.4rem;
    }
    
    /* Réduire la taille des inputs et selects dans le tableau */
    #prescriptionsTable .form-control,
    #prescriptionsTable .form-select,
    #prescriptionsTable .select2-container--default .select2-selection--single {
        padding: 0.25rem 0.5rem;
        height: calc(1.5em + 0.5rem + 2px);
        font-size: 0.8rem;
    }
    
    /* Ajuster la taille des boutons dans le tableau */
    #prescriptionsTable .btn-sm {
        padding: 0.15rem 0.3rem;
        font-size: 0.75rem;
    }
    
    /* Réduire l'espacement entre les sections */
    .card-title {
        margin-bottom: 0.5rem;
        font-size: 1rem;
    }
    
    /* Réduire la hauteur des inputs et labels */
    .form-label {
        margin-bottom: 0.25rem;
    }

    
</style>


</head>
<div id="preloader">
    <div class="spinner"></div>
</div>
