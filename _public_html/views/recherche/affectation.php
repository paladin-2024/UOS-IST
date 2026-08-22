<?php
include "./views/include/header.php";

// Initialiser la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Fonctions utilitaires pour le contrôle d'accès
function getUserSections($db, $userId, $anneeAcadId) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE idUser = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['idannee_acad'] : null;
}

// Vérification des responsabilités de l'utilisateur connecté
$currentUserId = $_SESSION['id']; 
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur
$currentAcademicYear = getCurrentAcademicYear($connexion);
$userSections = [];
$isResponsableSection = false;

if (!$hasFullAccess && $currentAcademicYear) {
    $userSections = getUserSections($connexion, $currentUserId, $currentAcademicYear);
    $isResponsableSection = !empty($userSections);
}

// Si l'utilisateur n'a pas les droits d'accès complet et n'est responsable d'aucune section
if (!$hasFullAccess && !$isResponsableSection) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Acces refuse',
            text: 'Vous n\'avez pas les droits pour acceder a cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    include "./views/include/footer.php"; 
    exit;
}

// Fonction pour vérifier si un sujet appartient aux sections de l'utilisateur
function isSubjectAccessible($db, $sujetId, $userSections, $hasFullAccess) {
    if ($hasFullAccess) {
        return true;
    }
    
    if (empty($userSections)) {
        return false;
    }
    
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $query = "SELECT COUNT(*) as count
              FROM sujets s
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.idsujets = ? AND sec.idsection IN ($sectionsParams)";
    
    $params = array_merge([$sujetId], $userSections);
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

// Récupérer les paramètres de filtrage
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_cycle = isset($_GET['filter_cycle']) ? $_GET['filter_cycle'] : '';
$filter_specialisation = isset($_GET['filter_specialisation']) ? $_GET['filter_specialisation'] : '';
$filter_statut = isset($_GET['filter_statut']) ? $_GET['filter_statut'] : '';
$filter_annee = isset($_GET['filter_annee']) ? $_GET['filter_annee'] : '';
$filter_affectation = isset($_GET['filter_affectation']) ? $_GET['filter_affectation'] : '';

// Récupérer les années académiques
$query = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmt = $connexion->prepare($query);
$stmt->execute();
$academicYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les spécialisations (filtrées selon les sections autorisées)
if ($hasFullAccess) {
    // Admin - toutes les spécialisations
    $query = "SELECT s.*, ur.designation_UR as unite_recherche 
              FROM specialisation s
              LEFT JOIN unite_recherche ur ON s.idUnite_recherche = ur.idunite_recherche
              ORDER BY s.designation";
    $stmt = $connexion->prepare($query);
    $stmt->execute();
    $specialisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement les spécialisations de ses sections
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $query = "SELECT DISTINCT s.idSpecialisation, s.designation, s.idorientation, s.idUnite_recherche,
                     ur.designation_UR as unite_recherche
              FROM specialisation s
              LEFT JOIN unite_recherche ur ON s.idUnite_recherche = ur.idunite_recherche
              LEFT JOIN orientation o ON s.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE sec.idsection IN ($sectionsParams)
              ORDER BY s.designation";
    $stmt = $connexion->prepare($query);
    $stmt->execute($userSections);
    $specialisations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Construire la requête avec les filtres
$whereConditions = [];
$queryParams = [];

// Condition de recherche par intitulé
if (!empty($search)) {
    $whereConditions[] = "s.intitule LIKE :search";
    $queryParams['search'] = '%' . $search . '%';
}

// Filtre par cycle
if (!empty($filter_cycle)) {
    $whereConditions[] = "s.cycle = :filter_cycle";
    $queryParams['filter_cycle'] = $filter_cycle;
}

// Filtre par spécialisation
if (!empty($filter_specialisation)) {
    $whereConditions[] = "s.idSpecialisation = :filter_specialisation";
    $queryParams['filter_specialisation'] = $filter_specialisation;
}

// Filtre par statut
if (!empty($filter_statut)) {
    $whereConditions[] = "s.statut_validation = :filter_statut";
    $queryParams['filter_statut'] = $filter_statut;
}

// Filtre par année académique
if (!empty($filter_annee)) {
    $whereConditions[] = "s.annee_acad_idannee_acad = :filter_annee";
    $queryParams['filter_annee'] = $filter_annee;
}

// Recuperer les sujets de recherche avec filtres (charges via AJAX)
$sujets = [];
$sujetsCount = 0;
$canLoadSubjects = true;
$pageSize = 20;

// Récupérer la liste des étudiants pour le select (filtrés selon les sections autorisées)
if ($hasFullAccess) {
    // Admin - tous les étudiants
    $query = "SELECT * FROM etudiant ORDER BY noms";
    $stmt = $connexion->prepare($query);
    $stmt->execute();
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement les étudiants de ses sections
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $query = "SELECT e.* 
              FROM etudiant e
              LEFT JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE sec.idsection IN ($sectionsParams)
              ORDER BY e.noms";
    $stmt = $connexion->prepare($query);
    $stmt->execute($userSections);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer la liste des enseignants pour les select directeur/encadreur (filtrés selon les sections autorisées)
if ($hasFullAccess) {
    // Admin - tous les enseignants
    $query = "SELECT a.*, g.designation as gradeDesignation
              FROM agent a
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              WHERE a.type_agent = 'Enseignant'
              ORDER BY a.noms";
    $stmt = $connexion->prepare($query);
    $stmt->execute();
    $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Responsable de section - seulement les enseignants de ses sections
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $query = "SELECT DISTINCT a.*, g.designation as gradeDesignation
              FROM agent a
              LEFT JOIN grade g ON a.grade_id = g.idgrade
              LEFT JOIN agent_section ag_s ON ag_s.idAgent = a.idAgent
              WHERE a.type_agent = 'Enseignant' AND ag_s.idsection IN ($sectionsParams)
              ORDER BY a.noms";
    $stmt = $connexion->prepare($query);
    $stmt->execute($userSections);
    $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>SUJETS DE RECHERCHE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Sujets de Recherche</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Informations sur les sections gérées -->
        <?php if ($isResponsableSection): ?>
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle me-2"></i>
            Vous visualisez uniquement les <strong>sujets de recherche</strong> des sections où vous avez des responsabilités.
            <?php if (count($userSections) > 0): ?>
                <?php
                // Récupérer les noms des sections
                $sectionNames = [];
                $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
                $queryNames = "SELECT designationSection FROM section WHERE idsection IN ($sectionsParams)";
                $stmtNames = $connexion->prepare($queryNames);
                $stmtNames->execute($userSections);
                $sectionsData = $stmtNames->fetchAll(PDO::FETCH_COLUMN);
                ?>
                <strong>Vos sections:</strong> <?= implode(', ', $sectionsData) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-12">
                <div class="row">
                    <div class="col-12">
                        <div class="card overflow-auto">

                            <div class="card-body">
                                <h5 class="card-title">
                                    Liste des Sujets de Recherche
                                    <div class="float-end">
                                        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                                            <i class="bi bi-file-excel"></i> Exporter
                                        </button>
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSujetModal">
                                            <i class="bi bi-plus-circle"></i> Nouveau Sujet
                                        </button>
                                    </div>
                                </h5>

                                <!-- Formulaire de recherche -->
                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="recherche/affectation">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un sujet...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <!-- Filtres avancés -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <button class="btn btn-link p-0 text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse" aria-expanded="false" aria-controls="filtersCollapse">
                                                <i class="bi bi-funnel me-2"></i>Filtres avancés
                                                <i class="bi bi-chevron-down ms-2"></i>
                                            </button>
                                        </h6>
                                    </div>
                                    <div class="collapse" id="filtersCollapse">
                                        <div class="card-body">
                                            <form method="GET" action="" id="filtersForm">
                                                <input type="hidden" name="view" value="recherche/affectation">
                                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-3">
                                                        <label for="filter_cycle" class="form-label">Cycle</label>
                                                        <select name="filter_cycle" id="filter_cycle" class="form-select">
                                                            <option value="">Tous les cycles</option>
                                                            <option value="Premier" <?= $filter_cycle == 'Premier' ? 'selected' : '' ?>>Licence</option>
                                                            <option value="Deuxieme" <?= $filter_cycle == 'Deuxieme' ? 'selected' : '' ?>>Master</option>
                                                            <option value="Troisieme" <?= $filter_cycle == 'Troisieme' ? 'selected' : '' ?>>Doctorat</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="filter_specialisation" class="form-label">Spécialisation</label>
                                                        <select name="filter_specialisation" id="filter_specialisation" class="form-select">
                                                            <option value="">Toutes les spécialisations</option>
                                                            <?php foreach ($specialisations as $spec): ?>
                                                                <option value="<?= $spec['idSpecialisation'] ?>" <?= $filter_specialisation == $spec['idSpecialisation'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($spec['designation']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="filter_statut" class="form-label">Statut</label>
                                                        <select name="filter_statut" id="filter_statut" class="form-select">
                                                            <option value="">Tous les statuts</option>
                                                            <option value="En attente" <?= $filter_statut == 'En attente' ? 'selected' : '' ?>>En attente</option>
                                                            <option value="Validé" <?= $filter_statut == 'Validé' ? 'selected' : '' ?>>Validé</option>
                                                            <option value="A reformulé" <?= $filter_statut == 'A reformulé' ? 'selected' : '' ?>>A reformulé</option>
                                                            <option value="Modifié" <?= $filter_statut == 'Modifié' ? 'selected' : '' ?>>Modifié</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label for="filter_annee" class="form-label">Année académique</label>
                                                        <select name="filter_annee" id="filter_annee" class="form-select">
                                                            <option value="">Toutes les années</option>
                                                            <?php foreach ($academicYears as $year): ?>
                                                                <option value="<?= $year['idannee_acad'] ?>" <?= $filter_annee == $year['idannee_acad'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($year['designation']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-4">
                                                        <label for="filter_affectation" class="form-label">État d'affectation</label>
                                                        <select name="filter_affectation" id="filter_affectation" class="form-select">
                                                            <option value="">Tous les sujets</option>
                                                            <option value="avec_etudiant" <?= $filter_affectation == 'avec_etudiant' ? 'selected' : '' ?>>Avec étudiant assigné</option>
                                                            <option value="sans_etudiant" <?= $filter_affectation == 'sans_etudiant' ? 'selected' : '' ?>>Sans étudiant assigné</option>
                                                            <option value="avec_directeur" <?= $filter_affectation == 'avec_directeur' ? 'selected' : '' ?>>Avec directeur assigné</option>
                                                            <option value="sans_directeur" <?= $filter_affectation == 'sans_directeur' ? 'selected' : '' ?>>Sans directeur assigné</option>
                                                            <option value="avec_encadreur" <?= $filter_affectation == 'avec_encadreur' ? 'selected' : '' ?>>Avec encadreur assigné</option>
                                                            <option value="sans_encadreur" <?= $filter_affectation == 'sans_encadreur' ? 'selected' : '' ?>>Sans encadreur assigné</option>
                                                            <option value="complet" <?= $filter_affectation == 'complet' ? 'selected' : '' ?>>Complètement affecté</option>
                                                            <option value="incomplet" <?= $filter_affectation == 'incomplet' ? 'selected' : '' ?>>Affectation incomplète</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-8 d-flex align-items-end">
                                                        <button type="submit" class="btn btn-primary me-2">
                                                            <i class="bi bi-search me-1"></i>Appliquer les filtres
                                                        </button>
                                                        <a href="?view=recherche/affectation" class="btn btn-outline-secondary">
                                                            <i class="bi bi-x-circle me-1"></i>Réinitialiser
                                                        </a>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Résumé des filtres actifs -->
                                <?php 
                                $activeFilters = [];
                                if (!empty($search)) $activeFilters[] = "Recherche: \"$search\"";
                                if (!empty($filter_cycle)) {
                                    $cycleLabel = $filter_cycle == 'Premier' ? 'Licence' : ($filter_cycle == 'Deuxieme' ? 'Master' : 'Doctorat');
                                    $activeFilters[] = "Cycle: $cycleLabel";
                                }
                                if (!empty($filter_specialisation)) {
                                    foreach ($specialisations as $spec) {
                                        if ($spec['idSpecialisation'] == $filter_specialisation) {
                                            $activeFilters[] = "Spécialisation: " . $spec['designation'];
                                            break;
                                        }
                                    }
                                }
                                if (!empty($filter_statut)) $activeFilters[] = "Statut: $filter_statut";
                                if (!empty($filter_annee)) {
                                    foreach ($academicYears as $year) {
                                        if ($year['idannee_acad'] == $filter_annee) {
                                            $activeFilters[] = "Année: " . $year['designation'];
                                            break;
                                        }
                                    }
                                }
                                if (!empty($filter_affectation)) {
                                    $affectationLabels = [
                                        'avec_etudiant' => 'Avec étudiant assigné',
                                        'sans_etudiant' => 'Sans étudiant assigné',
                                        'avec_directeur' => 'Avec directeur assigné',
                                        'sans_directeur' => 'Sans directeur assigné',
                                        'avec_encadreur' => 'Avec encadreur assigné',
                                        'sans_encadreur' => 'Sans encadreur assigné',
                                        'complet' => 'Complètement affecté',
                                        'incomplet' => 'Affectation incomplète'
                                    ];
                                    $activeFilters[] = "Affectation: " . $affectationLabels[$filter_affectation];
                                }
                                ?>
                                
                                <?php if (!empty($activeFilters)): ?>
                                <div class="alert alert-info mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-info-circle me-2"></i>
                                            <strong>Filtres actifs:</strong> <?= implode(' • ', $activeFilters) ?>
                                            <span class="badge bg-primary ms-2"><?= $sujetsCount ?> résultat(s)</span>
                                        </div>
                                        <a href="?view=recherche/affectation" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-x"></i> Effacer
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Modal pour l'exportation -->
                                <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true" data-bs-backdrop="static">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class="bi bi-file-excel text-success me-2"></i>
                                                    Exporter les Sujets de Recherche
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" action="" id="exportForm">
                                                    <!-- Options de filtrage -->
                                                    <div class="row mb-4">
                                                        <div class="col-12">
                                                            <h6 class="fw-bold text-primary mb-3">
                                                                <i class="bi bi-funnel me-2"></i>Critères de filtrage
                                                            </h6>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="annee_export" class="form-label">Année académique *</label>
                                                            <select name="annee_export" id="annee_export" class="form-select" required>
                                                                <option value="">Sélectionner une année académique</option>
                                                                <?php foreach ($academicYears as $year): ?>
                                                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="cycle_export" class="form-label">Cycle</label>
                                                            <select name="cycle_export" id="cycle_export" class="form-select">
                                                                <option value="">Tous les cycles</option>
                                                                <option value="Premier">Licence</option>
                                                                <option value="Deuxieme">Master</option>
                                                                <option value="Troisieme">Doctorat</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="section_export" class="form-label">Section</label>
                                                            <select name="section_export" id="section_export" class="form-select" disabled>
                                                                <option value="">Choisir d'abord une année académique</option>
                                                            </select>
                                                            <div id="section_export_loading" class="d-none mt-2">
                                                                <div class="d-flex align-items-center text-muted small">
                                                                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                                                    Chargement des sections...
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="specialisation_export" class="form-label">Spécialisation</label>
                                                            <select name="specialisation_export" id="specialisation_export" class="form-select" disabled>
                                                                <option value="">Choisir d'abord une section</option>
                                                            </select>
                                                            <div id="specialisation_export_loading" class="d-none mt-2">
                                                                <div class="d-flex align-items-center text-muted small">
                                                                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                                                    Chargement des spécialisations...
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-4">
                                                        <div class="col-md-6">
                                                            <label for="statut_export" class="form-label">Statut de validation</label>
                                                            <select name="statut_export" id="statut_export" class="form-select">
                                                                <option value="">Tous les statuts</option>
                                                                <option value="En attente">En attente</option>
                                                                <option value="Validé">Validé</option>
                                                                <option value="A reformulé">A reformulé</option>
                                                                <option value="Modifié">Modifié</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="affectation_export" class="form-label">État d'affectation</label>
                                                            <select name="affectation_export" id="affectation_export" class="form-select">
                                                                <option value="">Tous les sujets</option>
                                                                <option value="avec_etudiant">Avec étudiant assigné</option>
                                                                <option value="sans_etudiant">Sans étudiant assigné</option>
                                                                <option value="avec_directeur">Avec directeur assigné</option>
                                                                <option value="sans_directeur">Sans directeur assigné</option>
                                                                <option value="avec_encadreur">Avec encadreur assigné</option>
                                                                <option value="sans_encadreur">Sans encadreur assigné</option>
                                                                <option value="complet">Complètement affecté (étudiant + directeur + encadreur)</option>
                                                                <option value="incomplet">Affectation incomplète</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Options de format et contenu -->
                                                    <div class="row mb-4">
                                                        <div class="col-12">
                                                            <h6 class="fw-bold text-primary mb-3">
                                                                <i class="bi bi-gear me-2"></i>Options d'exportation
                                                            </h6>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <label for="format_export" class="form-label">Format d'exportation</label>
                                                            <select name="format_export" id="format_export" class="form-select">
                                                                <option value="excel">Excel (.xlsx)</option>
                                                                <option value="csv">CSV (.csv)</option>
                                                                <option value="pdf">PDF (.pdf)</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="groupement_export" class="form-label">Groupement des données</label>
                                                            <select name="groupement_export" id="groupement_export" class="form-select">
                                                                <option value="aucun">Aucun groupement</option>
                                                                <option value="specialisation">Par spécialisation</option>
                                                                <option value="cycle">Par cycle</option>
                                                                <option value="statut">Par statut de validation</option>
                                                                <option value="section">Par section</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Colonnes à inclure -->
                                                    <div class="row mb-4">
                                                        <div class="col-12">
                                                            <label class="form-label">Colonnes à inclure dans l'export</label>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="intitule" id="col_intitule" checked>
                                                                        <label class="form-check-label" for="col_intitule">Intitulé du sujet</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="cycle" id="col_cycle" checked>
                                                                        <label class="form-check-label" for="col_cycle">Cycle</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="specialisation" id="col_specialisation" checked>
                                                                        <label class="form-check-label" for="col_specialisation">Spécialisation</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="statut" id="col_statut" checked>
                                                                        <label class="form-check-label" for="col_statut">Statut de validation</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="annee" id="col_annee" checked>
                                                                        <label class="form-check-label" for="col_annee">Année académique</label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="etudiant" id="col_etudiant" checked>
                                                                        <label class="form-check-label" for="col_etudiant">Étudiant assigné</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="directeur" id="col_directeur" checked>
                                                                        <label class="form-check-label" for="col_directeur">Directeur</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="encadreur" id="col_encadreur" checked>
                                                                        <label class="form-check-label" for="col_encadreur">Encadreur</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="section" id="col_section">
                                                                        <label class="form-check-label" for="col_section">Section</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="unite_recherche" id="col_unite">
                                                                        <label class="form-check-label" for="col_unite">Unité de recherche</label>
                                                                    </div>
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" name="colonnes[]" value="resume" id="col_resume">
                                                                        <label class="form-check-label" for="col_resume">Introduction / Problématique</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Options avancées -->
                                                    <div class="row mb-3">
                                                        <div class="col-12">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="inclure_statistiques" id="inclure_stats" value="1">
                                                                <label class="form-check-label" for="inclure_stats">
                                                                    Inclure une feuille de statistiques (Excel uniquement)
                                                                </label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="inclure_contacts" id="inclure_contacts" value="1">
                                                                <label class="form-check-label" for="inclure_contacts">
                                                                    Inclure les informations de contact (email, téléphone)
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Informations sur les restrictions d'accès -->
                                                    <?php if ($isResponsableSection): ?>
                                                    <div class="alert alert-info mt-3">
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        <strong>Note:</strong> L'exportation sera limitée aux données des sections dont vous êtes responsable.
                                                    </div>
                                                    <?php endif; ?>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                            <i class="bi bi-x-circle me-2"></i>Annuler
                                                        </button>
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="bi bi-download me-2"></i>Exporter
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                
<div class="table-responsive" id="affectationTableContainer" data-can-load="<?= $canLoadSubjects ? '1' : '0' ?>" data-page-size="<?= $pageSize ?>">
    <div id="affectationNoResults" class="alert alert-info d-none">
        <i class="bi bi-info-circle me-2"></i>Aucun sujet trouve avec les criteres selectionnes.
    </div>
    <table class="table table-striped table-bordered mb-0">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Intitule</th>
                <th scope="col">Cycle</th>
                <th scope="col">Specialisation</th>
                <th scope="col">Etat</th>
                <th scope="col">Etudiant</th>
                <th scope="col">Directeur</th>
                <th scope="col">Encadreur</th>
                <th scope="col">Annee</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody id="affectationTableBody"></tbody>
    </table>
    <div id="affectationPager" class="text-center my-3">
        <div id="affectationSpinner" class="spinner-border text-primary d-none" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>
        <button id="affectationLoadMore" class="btn btn-outline-primary">
            <i class="bi bi-arrow-down-circle me-1"></i>Charger plus
        </button>
    </div>
</div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal Création Sujet -->
<div class="modal fade" id="createSujetModal" tabindex="-1" role="dialog" aria-labelledby="createSujetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau Sujet de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createSujetForm" method="POST" action="controller/sujet_controller.php">
                    <input type="hidden" name="action" value="create">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="intitule" class="form-label">Intitulé du sujet</label>
                            <input type="text" class="form-control" id="intitule" name="intitule" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cycle" class="form-label">Cycle</label>
                            <select class="form-select" id="cycle" name="cycle" required>
                                <option value="">Sélectionner un cycle</option>
                                <option value="Premier">Licence</option>
                                <option value="Deuxieme">Master</option>
                                <option value="Troisieme">Doctorat</option>
                            </select>
                        </div>
                                                <div class="col-md-6">
                            <label for="idSpecialisation" class="form-label">Spécialisation</label>
                            <select class="form-select" id="idSpecialisation" name="idSpecialisation" required>
                                <option value="">Sélectionner une spécialisation</option>
                                <?php foreach ($specialisations as $specialisation): ?>
                                    <option value="<?= $specialisation['idSpecialisation'] ?>">
                                        <?= $specialisation['designation'] ?> (<?= $specialisation['unite_recherche'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="annee_acad" class="form-label">Année Académique</label>
                            <select class="form-select" id="annee_acad" name="annee_acad" required>
                                <option value="">Sélectionner une année</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="etatSujet" class="form-label">État du sujet</label>
                            <select class="form-select" id="etatSujet" name="etatSujet">
                                <option value="En attente">En attente</option>
                                <option value="Validé">Validé</option>
                                <option value="A reformulé">A reformulé</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="etudiant" class="form-label">Étudiant</label>
                            <select class="form-select" id="etudiant" name="etudiant">
                                <option value="">Sélectionner un étudiant</option>
                                <?php foreach ($etudiants as $etudiant): ?>
                                    <option value="<?= $etudiant['idetudiant'] ?>">
                                        <?= $etudiant['noms'] ?> (<?= $etudiant['matricule'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="directeur" class="form-label">Directeur</label>
                            <select class="form-select" id="directeur" name="directeur">
                                <option value="">Sélectionner un directeur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>">
                                        <?= $enseignant['gradeDesignation'] ? $enseignant['gradeDesignation'] . ' ' : '' ?><?= $enseignant['noms'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="encadreur" class="form-label">Encadreur</label>
                            <select class="form-select" id="encadreur" name="encadreur">
                                <option value="">Sélectionner un encadreur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>">
                                        <?= $enseignant['gradeDesignation'] ? $enseignant['gradeDesignation'] . ' ' : '' ?><?= $enseignant['noms'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modification Sujet -->
<div class="modal fade" id="editSujetModal" tabindex="-1" role="dialog" aria-labelledby="editSujetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier le Sujet de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSujetForm" method="POST" action="controller/sujet_controller.php">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="idsujets" id="edit_idsujets">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_intitule" class="form-label">Intitulé du sujet</label>
                            <input type="text" class="form-control" id="edit_intitule" name="intitule" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_cycle" class="form-label">Cycle</label>
                            <select class="form-select" id="edit_cycle" name="cycle" required>
                                <option value="">Sélectionner un cycle</option>
                                <option value="Premier">Licence</option>
                                <option value="Deuxieme">Master</option>
                                <option value="Troisieme">Doctorat</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_idSpecialisation" class="form-label">Spécialisation</label>
                            <select class="form-select" id="edit_idSpecialisation" name="idSpecialisation" required>
                                <option value="">Sélectionner une spécialisation</option>
                                <?php foreach ($specialisations as $specialisation): ?>
                                    <option value="<?= $specialisation['idSpecialisation'] ?>">
                                        <?= $specialisation['designation'] ?> (<?= $specialisation['unite_recherche'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_annee_acad" class="form-label">Année Académique</label>
                            <select class="form-select" id="edit_annee_acad" name="annee_acad" required>
                                <option value="">Sélectionner une année</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_etatSujet" class="form-label">État du sujet</label>
                            <select class="form-select" id="edit_etatSujet" name="etatSujet">
                                <option value="En attente">En attente</option>
                                <option value="Validé">Validé</option>
                                <option value="A reformulé">A reformulé</option>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="edit_etudiant" class="form-label">Étudiant</label>
                            <select class="form-select" id="edit_etudiant" name="etudiant">
                                <option value="">Sélectionner un étudiant</option>
                                <?php foreach ($etudiants as $etudiant): ?>
                                    <option value="<?= $etudiant['idetudiant'] ?>">
                                        <?= $etudiant['noms'] ?> (<?= $etudiant['matricule'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_directeur" class="form-label">Directeur</label>
                            <select class="form-select" id="edit_directeur" name="directeur">
                                <option value="">Sélectionner un directeur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>">
                                        <?= $enseignant['gradeDesignation'] ? $enseignant['gradeDesignation'] . ' ' : '' ?><?= $enseignant['noms'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_encadreur" class="form-label">Encadreur</label>
                            <select class="form-select" id="edit_encadreur" name="encadreur">
                                <option value="">Sélectionner un encadreur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>">
                                        <?= $enseignant['gradeDesignation'] ? $enseignant['gradeDesignation'] . ' ' : '' ?><?= $enseignant['noms'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour voir les propositions de reformulation -->
<div class="modal fade" id="reformulationProposalsModal" tabindex="-1" role="dialog" aria-labelledby="reformulationProposalsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-lightbulb text-info me-2"></i>
                    Propositions de reformulation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="reformulationProposalsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p>Chargement des propositions...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function mapSujetStatusClass(status) {
    if (!status) {
        return '';
    }
    let normalized = String(status).toLowerCase();
    if (typeof normalized.normalize === 'function') {
        normalized = normalized.normalize('NFD').replace(/[̀-ͯ]/g, '');
    }
    switch (normalized) {
        case 'en attente':
            return 'text-warning';
        case 'valide':
            return 'text-success';
        case 'a reformule':
            return 'text-danger';
        case 'modifie':
            return 'text-primary';
        default:
            return '';
    }
}

function formatCycleLabel(cycle) {
    switch (cycle) {
        case 'Premier':
            return 'Licence';
        case 'Deuxieme':
            return 'Master';
        case 'Troisieme':
            return 'Doctorat';
        default:
            return cycle || '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('affectationTableContainer');
    if (!container || container.dataset.canLoad !== '1') {
        return;
    }

    const tbody = document.getElementById('affectationTableBody');
    const loadMoreButton = document.getElementById('affectationLoadMore');
    const spinner = document.getElementById('affectationSpinner');
    const noResultsMessage = document.getElementById('affectationNoResults');
    const countBadge = document.getElementById('affectationCountBadge');
    const pageSize = parseInt(container.dataset.pageSize || '20', 10);
    const defaultNoResultHtml = noResultsMessage ? noResultsMessage.innerHTML : '';

    const state = {
        page: 1,
        limit: pageSize,
        loading: false,
        hasMore: true,
    };

    let totalLoaded = 0;

    function updateCount() {
        if (!countBadge) {
            return;
        }
        countBadge.textContent = totalLoaded === 1 ? '1 resultat' : `${totalLoaded} resultats`;
    }

    function resetTable() {
        state.page = 1;
        state.hasMore = true;
        totalLoaded = 0;
        if (tbody) {
            tbody.innerHTML = '';
        }
        if (noResultsMessage) {
            noResultsMessage.classList.add('d-none');
            noResultsMessage.classList.remove('alert-danger');
            noResultsMessage.classList.add('alert-info');
            noResultsMessage.innerHTML = defaultNoResultHtml;
        }
        if (loadMoreButton) {
            loadMoreButton.classList.remove('d-none');
            loadMoreButton.disabled = false;
        }
        updateCount();
    }

    function toggleLoading(isLoading) {
        state.loading = isLoading;
        if (spinner) {
            spinner.classList.toggle('d-none', !isLoading);
        }
        if (loadMoreButton) {
            loadMoreButton.disabled = isLoading;
        }
    }

    function buildActions(subject, safeTitle) {
        if (!subject.can_edit) {
            return '<span class="text-muted"><i class="bi bi-lock"></i></span>';
        }

        const editAttrs = {
            sujetId: subject.id || '',
            intitule: safeTitle,
            cycle: escapeHtml(subject.cycle || ''),
            specialisation: subject.specialisation && subject.specialisation.id ? subject.specialisation.id : 'null',
            annee: subject.annee && subject.annee.id ? subject.annee.id : 'null',
            etudiant: subject.etudiant && subject.etudiant.id ? subject.etudiant.id : 'null',
            directeur: subject.directeur && subject.directeur.id ? subject.directeur.id : 'null',
            encadreur: subject.encadreur && subject.encadreur.id ? subject.encadreur.id : 'null',
            etat: escapeHtml(subject.statut || '')
        };

        const editButton = `<button class="btn btn-sm btn-primary" data-sujet-id="${editAttrs.sujetId}" data-intitule="${editAttrs.intitule}" data-cycle="${editAttrs.cycle}" data-specialisation="${editAttrs.specialisation}" data-annee="${editAttrs.annee}" data-etudiant="${editAttrs.etudiant}" data-directeur="${editAttrs.directeur}" data-encadreur="${editAttrs.encadreur}" data-etat="${editAttrs.etat}" onclick="openEditSujetModalFromButton(this)" title="Modifier"><i class="bi bi-pencil"></i></button>`;

        const reformButton = subject.has_reformulation_pending
            ? `<button class="btn btn-sm btn-warning ms-1" onclick="viewReformulationProposals(${subject.id})" title="Voir les propositions"><i class="bi bi-lightbulb"></i></button>`
            : '';

        const deleteButton = `<button class="btn btn-sm btn-danger ms-1" data-sujet-id="${subject.id}" onclick="confirmDeleteSujetFromButton(this)" title="Supprimer"><i class="bi bi-trash"></i></button>`;

        return editButton + reformButton + deleteButton;
    }

    function appendSubject(subject) {
        if (!tbody) {
            return;
        }
        let rowNumber = Number(subject.index);
        if (!Number.isFinite(rowNumber)) {
            rowNumber = totalLoaded + 1;
        }
        totalLoaded = Math.max(totalLoaded, rowNumber);

        const safeTitle = escapeHtml(subject.intitule || '');
        const cycleLabel = formatCycleLabel(subject.cycle || '');
        const specialisationLabel = subject.specialisation && subject.specialisation.label
            ? escapeHtml(subject.specialisation.label)
            : '<span class="text-muted">Non defini</span>';
        const statusClass = mapSujetStatusClass(subject.statut || '');
        const statusLabel = escapeHtml(subject.statut || 'Non defini');

        let etudiantHtml = '<span class="text-muted">Non assigne</span>';
        if (subject.etudiant && subject.etudiant.id) {
            const matricule = subject.etudiant.matricule ? `<br><small class="text-muted">${escapeHtml(subject.etudiant.matricule)}</small>` : '';
            etudiantHtml = `<span class="fw-semibold">${escapeHtml(subject.etudiant.nom)}</span>${matricule}`;
        }

        let directeurHtml = '<span class="text-muted">Non assigne</span>';
        if (subject.directeur && subject.directeur.id) {
            directeurHtml = escapeHtml(subject.directeur.nom);
        }

        let encadreurHtml = '<span class="text-muted">Non assigne</span>';
        if (subject.encadreur && subject.encadreur.id) {
            encadreurHtml = escapeHtml(subject.encadreur.nom);
        }

        const anneeLabel = subject.annee && subject.annee.label ? escapeHtml(subject.annee.label) : '';
        const reformBadge = subject.has_reformulation_pending ? "<span class='badge bg-info ms-2'><i class='bi bi-lightbulb'></i></span>" : '';
        const actionsHtml = buildActions(subject, safeTitle);

        const rowHtml = `
            <tr>
                <td>${rowNumber}</td>
                <td>${safeTitle} ${reformBadge}</td>
                <td>${escapeHtml(cycleLabel)}</td>
                <td>${specialisationLabel}</td>
                <td><span class="${statusClass}">${statusLabel}</span></td>
                <td>${etudiantHtml}</td>
                <td>${directeurHtml}</td>
                <td>${encadreurHtml}</td>
                <td>${anneeLabel}</td>
                <td class="text-nowrap">${actionsHtml}</td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', rowHtml);
        updateCount();
    }

    function handleEmpty(firstPage) {
        if (!noResultsMessage) {
            return;
        }
        noResultsMessage.classList.remove('d-none');
        if (!firstPage) {
            return;
        }
        noResultsMessage.classList.remove('alert-danger');
        noResultsMessage.classList.add('alert-info');
        noResultsMessage.innerHTML = defaultNoResultHtml;
    }

    function fetchSubjects(reset) {
        if (state.loading) {
            return;
        }
        if (!state.hasMore && !reset) {
            return;
        }
        if (reset) {
            resetTable();
        }
        toggleLoading(true);

        const params = new URLSearchParams(window.location.search);
        params.set('context', 'affectation');
        params.set('page', state.page.toString());
        params.set('limit', state.limit.toString());

        fetch(`controller/load_more_sujets.php?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Erreur lors du chargement des sujets.');
                }
                return response.json();
            })
            .then(function(payload) {
                if (!payload.success) {
                    throw new Error(payload.error || 'Chargement impossible.');
                }

                const subjects = Array.isArray(payload.data) ? payload.data : [];
                if (state.page === 1 && subjects.length === 0) {
                    state.hasMore = false;
                    handleEmpty(true);
                    if (loadMoreButton) {
                        loadMoreButton.classList.add('d-none');
                    }
                    return;
                }

                if (noResultsMessage) {
                    noResultsMessage.classList.add('d-none');
                }

                subjects.forEach(function(subject) {
                    appendSubject(subject);
                });

                state.page += 1;
                state.hasMore = Boolean(payload.hasMore) && subjects.length > 0;
                if (!state.hasMore && loadMoreButton) {
                    loadMoreButton.classList.add('d-none');
                } else if (state.hasMore && loadMoreButton) {
                    loadMoreButton.classList.remove('d-none');
                }
            })
            .catch(function(error) {
                console.error(error);
                if (noResultsMessage) {
                    noResultsMessage.classList.remove('d-none');
                    noResultsMessage.classList.remove('alert-info');
                    noResultsMessage.classList.add('alert-danger');
                    noResultsMessage.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(error.message || 'Une erreur est survenue.')}`;
                } else {
                    Swal.fire('Erreur', error.message || 'Une erreur est survenue lors du chargement des sujets.', 'error');
                }
                state.hasMore = false;
                if (loadMoreButton) {
                    loadMoreButton.classList.add('d-none');
                }
            })
            .finally(function() {
                toggleLoading(false);
            });
    }

    if (loadMoreButton) {
        loadMoreButton.addEventListener('click', function() {
            fetchSubjects(false);
        });
    }

    fetchSubjects(true);
});

function openEditSujetModalFromButton(button) {
    if (!button) {
        return;
    }
    openEditSujetModal(
        button.getAttribute('data-sujet-id'),
        button.getAttribute('data-intitule'),
        button.getAttribute('data-cycle'),
        button.getAttribute('data-specialisation'),
        button.getAttribute('data-annee'),
        button.getAttribute('data-etudiant'),
        button.getAttribute('data-directeur'),
        button.getAttribute('data-encadreur'),
        button.getAttribute('data-etat')
    );
}

function confirmDeleteSujetFromButton(button) {
    if (!button) {
        return;
    }
    const sujetId = button.getAttribute('data-sujet-id');
    if (sujetId) {
        confirmDeleteSujet(sujetId);
    }
}

    function openEditSujetModal(id, intitule, cycle, idSpecialisation, anneeAcad, etudiantId, directeurId, encadreurId, etatSujet) {
        // Remplir les champs du formulaire
        document.getElementById('edit_idsujets').value = id;
        document.getElementById('edit_intitule').value = intitule;
        document.getElementById('edit_cycle').value = cycle;
        document.getElementById('edit_idSpecialisation').value = idSpecialisation;
        document.getElementById('edit_annee_acad').value = anneeAcad;
        document.getElementById('edit_etatSujet').value = etatSujet;

        // Gérer les champs qui peuvent être null
        if (etudiantId && etudiantId !== 'null') {
            document.getElementById('edit_etudiant').value = etudiantId;
        } else {
            document.getElementById('edit_etudiant').value = '';
        }

        if (directeurId && directeurId !== 'null') {
            document.getElementById('edit_directeur').value = directeurId;
        } else {
            document.getElementById('edit_directeur').value = '';
        }

        if (encadreurId && encadreurId !== 'null') {
            document.getElementById('edit_encadreur').value = encadreurId;
        } else {
            document.getElementById('edit_encadreur').value = '';
        }

        // Si vous utilisez Select2, vous devez déclencher un événement change
        // pour mettre à jour l'affichage des select
        $('#edit_etudiant').trigger('change');
        $('#edit_directeur').trigger('change');
        $('#edit_encadreur').trigger('change');

        // Afficher le modal
        var editModal = new bootstrap.Modal(document.getElementById('editSujetModal'));
        editModal.show();
    }

    function confirmDeleteSujet(id) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer !',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Créer un formulaire dynamique pour envoyer l'action en POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'controller/sujet_controller.php';

                // Champ caché pour l'action
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';

                // Champ caché pour l'ID
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'idsujets';
                idInput.value = id;

                // Ajouter les champs au formulaire
                form.appendChild(actionInput);
                form.appendChild(idInput);

                // Ajouter le formulaire au document et le soumettre
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Fonction pour voir les propositions de reformulation
    function viewReformulationProposals(sujetId) {
        // Afficher le modal et son loader
        const reformulationModal = new bootstrap.Modal(document.getElementById('reformulationProposalsModal'));
        reformulationModal.show();
        
        // Récupérer les propositions via AJAX
        fetch(`controller/get_sujet_reformulations.php?sujet_id=${sujetId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Formater et afficher les données
                let html = '';
                
                if (data.reformulations && data.reformulations.length > 0) {
                    html += '<h6 class="mb-3"><i class="bi bi-lightbulb me-2"></i>Propositions de reformulation</h6>';
                    
                    data.reformulations.forEach(reformulation => {
                        const statusClass = {
                            'En attente': 'warning',
                            'Acceptée': 'success',
                            'Refusée': 'danger'
                        }[reformulation.statut_reformulation] || 'secondary';
                        
                        const dateProposition = new Date(reformulation.date_proposition).toLocaleDateString('fr-FR', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        html += `
                            <div class="card mb-3 border-${statusClass}">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Proposition du ${dateProposition}</h6>
                                    <span class="badge bg-${statusClass}">${reformulation.statut_reformulation}</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Nouvel intitulé proposé:</strong><br>
                                            <p class="text-primary">${reformulation.intitule_propose}</p>
                                        </div>
                                        <div class="col-md-6">
                                            ${reformulation.specialisation_nom ? `
                                                <strong>Spécialisation:</strong><br>
                                                <p>${reformulation.specialisation_nom}</p>
                                            ` : ''}
                                        </div>
                                    </div>
                                    
                                    ${reformulation.directeur_nom ? `
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Directeur proposé:</strong><br>
                                                <p>${reformulation.directeur_nom}</p>
                                            </div>
                                            ${reformulation.encadreur_nom ? `
                                                <div class="col-md-6">
                                                    <strong>Encadreur proposé:</strong><br>
                                                    <p>${reformulation.encadreur_nom}</p>
                                                </div>
                                            ` : ''}
                                        </div>
                                    ` : ''}
                                    
                                    <div class="mt-3">
                                        <strong>Justification de l'étudiant:</strong>
                                        <div class="p-2 bg-light rounded mt-1">
                                            ${reformulation.justification_etudiant.replace(/\n/g, '<br>')}
                                        </div>
                                    </div>
                                    
                                    ${reformulation.commentaire_reponse ? `
                                        <div class="alert alert-${statusClass === 'success' ? 'success' : 'danger'} mt-3">
                                            <strong>Réponse de l'administration:</strong><br>
                                            ${reformulation.commentaire_reponse.replace(/\n/g, '<br>')}
                                            ${reformulation.date_traitement ? `
                                                <br><small class="text-muted">Le ${new Date(reformulation.date_traitement).toLocaleDateString('fr-FR')}</small>
                                            ` : ''}
                                        </div>
                                    ` : ''}
                                    
                                    ${reformulation.statut_reformulation === 'En attente' ? `
                                        <div class="mt-3">
                                            <button class="btn btn-success btn-sm me-2" onclick="approveReformulation(${reformulation.id_reformulation})">
                                                <i class="bi bi-check-circle"></i> Approuver
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="rejectReformulation(${reformulation.id_reformulation})">
                                                <i class="bi bi-x-circle"></i> Refuser
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    });
                } else {
                    html = '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Aucune proposition de reformulation trouvée pour ce sujet.</div>';
                }
                
                document.getElementById('reformulationProposalsContent').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('reformulationProposalsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Erreur lors du chargement des propositions: ${error.message}
                    </div>
                `;
            });
    }

    // Fonction pour approuver une reformulation
    function approveReformulation(reformulationId) {
        Swal.fire({
            title: 'Approuver cette reformulation ?',
            text: "Le sujet sera mis à jour avec les nouvelles informations proposées.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, approuver',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Envoyer la requête d'approbation
                fetch('controller/traiter_reformulation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=approve&reformulation_id=${reformulationId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Approuvé !', 'La reformulation a été approuvée avec succès.', 'success')
                        .then(() => {
                            location.reload(); // Recharger la page pour voir les changements
                        });
                    } else {
                        Swal.fire('Erreur', data.message || 'Une erreur est survenue.', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Erreur', 'Une erreur est survenue lors du traitement.', 'error');
                });
            }
        });
    }

    // Fonction pour refuser une reformulation
    function rejectReformulation(reformulationId) {
        Swal.fire({
            title: 'Refuser cette reformulation',
            input: 'textarea',
            inputLabel: 'Motif du refus',
            inputPlaceholder: 'Expliquez pourquoi cette reformulation est refusée...',
            inputAttributes: {
                'aria-label': 'Motif du refus'
            },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Refuser',
            cancelButtonText: 'Annuler',
            inputValidator: (value) => {
                if (!value) {
                    return 'Vous devez fournir un motif de refus !';
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Envoyer la requête de refus
                fetch('controller/traiter_reformulation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reject&reformulation_id=${reformulationId}&commentaire=${encodeURIComponent(result.value)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Refusé !', 'La reformulation a été refusée.', 'success')
                        .then(() => {
                            location.reload(); // Recharger la page pour voir les changements
                        });
                    } else {
                        Swal.fire('Erreur', data.message || 'Une erreur est survenue.', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Erreur', 'Une erreur est survenue lors du traitement.', 'error');
                });
            }
        });
    }

    // Initialisation des select2 pour une meilleure expérience utilisateur
    $(document).ready(function() {
        $('.form-select').select2({
            theme: 'bootstrap-5',
            width: '100%'
        });

        // Fonctionnalités avancées pour le modal d'exportation
        
        // Gestion des options selon le format sélectionné
        $('#format_export').on('change', function() {
            const format = $(this).val();
            const statsOption = $('#inclure_stats').closest('.form-check');
            
            if (format === 'excel') {
                statsOption.show();
            } else {
                statsOption.hide();
                $('#inclure_stats').prop('checked', false);
            }
        });

        // Filtrage en cascade pour le modal d'exportation : année -> section -> spécialisation
        const $anneeExport = $('#annee_export');
        const $sectionExport = $('#section_export');
        const $sectionLoading = $('#section_export_loading');
        const $specialisationExport = $('#specialisation_export');
        const $specialisationLoading = $('#specialisation_export_loading');

        function setExportSelectOptions($select, placeholder, items, valueKey, labelBuilder) {
            $select.html('');

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            $select.append(placeholderOption);

            if (Array.isArray(items)) {
                items.forEach(item => {
                    const value = item[valueKey];
                    if (!value) {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = labelBuilder(item);
                    $select.append(option);
                });
            }

            $select.val('').trigger('change.select2');
        }

        function resetSectionExport(placeholder) {
            setExportSelectOptions($sectionExport, placeholder, [], 'idsection', item => item.designationSection || '');
            $sectionExport.prop('disabled', true).trigger('change.select2');
        }

        function resetSpecialisationExport(placeholder) {
            setExportSelectOptions($specialisationExport, placeholder, [], 'idSpecialisation', item => item.designation || '');
            $specialisationExport.prop('disabled', true).trigger('change.select2');
        }

        function loadSectionsForExportYear() {
            const anneeId = $anneeExport.val();

            resetSpecialisationExport('Choisir d\'abord une section');

            if (!anneeId) {
                resetSectionExport('Choisir d\'abord une année académique');
                return;
            }

            resetSectionExport('Chargement des sections...');
            $sectionLoading.removeClass('d-none');

            fetch(`controller/get_sections_by_annee.php?annee_id=${encodeURIComponent(anneeId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur de chargement des sections.');
                    }
                    return response.json();
                })
                .then(payload => {
                    if (!payload.success || !Array.isArray(payload.sections)) {
                        throw new Error(payload.message || 'Données de sections indisponibles.');
                    }

                    const seen = new Set();
                    const sections = [];

                    payload.sections.forEach(section => {
                        const sectionId = String(section.idsection || '').trim();
                        const sectionLabel = String(section.designationSection || '').trim();

                        if (!sectionId || !sectionLabel || seen.has(sectionId)) {
                            return;
                        }

                        seen.add(sectionId);
                        sections.push(section);
                    });

                    if (sections.length === 0) {
                        resetSectionExport('Aucune section trouvée');
                        return;
                    }

                    setExportSelectOptions(
                        $sectionExport,
                        <?= json_encode($hasFullAccess ? 'Toutes les sections' : 'Toutes vos sections') ?>,
                        sections,
                        'idsection',
                        item => item.designationSection || ''
                    );
                    $sectionExport.prop('disabled', false).trigger('change.select2');
                })
                .catch(error => {
                    console.error(error);
                    resetSectionExport('Erreur de chargement');
                    resetSpecialisationExport('Choisir d\'abord une section');
                    Swal.fire('Attention', error.message || 'Impossible de charger les sections.', 'warning');
                })
                .finally(() => {
                    $sectionLoading.addClass('d-none');
                });
        }

        function loadSpecialisationsForExportSection() {
            const anneeId = $anneeExport.val();
            const sectionId = $sectionExport.val();

            if (!anneeId) {
                resetSectionExport('Choisir d\'abord une année académique');
                resetSpecialisationExport('Choisir d\'abord une section');
                return;
            }

            if (!sectionId) {
                resetSpecialisationExport('Choisir d\'abord une section');
                return;
            }

            resetSpecialisationExport('Chargement des spécialisations...');
            $specialisationLoading.removeClass('d-none');

            fetch(`controller/get_specialisations_by_section.php?section_id=${encodeURIComponent(sectionId)}&annee_id=${encodeURIComponent(anneeId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur de chargement des spécialisations.');
                    }
                    return response.json();
                })
                .then(payload => {
                    if (!payload.success || !Array.isArray(payload.specialisations)) {
                        throw new Error(payload.message || 'Données de spécialisations indisponibles.');
                    }

                    const seen = new Set();
                    const specialisations = [];

                    payload.specialisations.forEach(spec => {
                        const specId = String(spec.idSpecialisation || '').trim();
                        const specLabel = String(spec.designation || '').trim();

                        if (!specId || !specLabel || seen.has(specId)) {
                            return;
                        }

                        seen.add(specId);
                        specialisations.push(spec);
                    });

                    if (specialisations.length === 0) {
                        resetSpecialisationExport('Aucune spécialisation trouvée');
                        return;
                    }

                    setExportSelectOptions(
                        $specialisationExport,
                        'Toutes les spécialisations',
                        specialisations,
                        'idSpecialisation',
                        item => {
                            const uniteRecherche = String(item.designation_UR || '').trim();
                            return uniteRecherche ? `${item.designation} (${uniteRecherche})` : item.designation;
                        }
                    );
                    $specialisationExport.prop('disabled', false).trigger('change.select2');
                })
                .catch(error => {
                    console.error(error);
                    resetSpecialisationExport('Erreur de chargement');
                    Swal.fire('Attention', error.message || 'Impossible de charger les spécialisations.', 'warning');
                })
                .finally(() => {
                    $specialisationLoading.addClass('d-none');
                });
        }

        resetSectionExport('Choisir d\'abord une année académique');
        resetSpecialisationExport('Choisir d\'abord une section');

        $anneeExport.on('change', loadSectionsForExportYear);
        $sectionExport.on('change', loadSpecialisationsForExportSection);
        $('#exportModal').on('shown.bs.modal', function() {
            if ($anneeExport.val()) {
                loadSectionsForExportYear();
            } else {
                resetSectionExport('Choisir d\'abord une année académique');
                resetSpecialisationExport('Choisir d\'abord une section');
            }
        });

        // Boutons pour sélectionner/désélectionner toutes les colonnes
        $('#exportModal .modal-body').prepend(`
            <div class="d-none" id="colonnes-controls">
                <div class="mb-3 text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary me-2" id="select-all-cols">
                        <i class="bi bi-check-all"></i> Tout sélectionner
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all-cols">
                        <i class="bi bi-x"></i> Tout désélectionner
                    </button>
                </div>
            </div>
        `);

        // Afficher les contrôles des colonnes quand on arrive à cette section
        $('#exportModal').on('shown.bs.modal', function() {
            $('#colonnes-controls').removeClass('d-none');
        });

        $('#select-all-cols').on('click', function() {
            $('input[name="colonnes[]"]').prop('checked', true);
        });

        $('#deselect-all-cols').on('click', function() {
            $('input[name="colonnes[]"]').prop('checked', false);
        });

        // Validation du formulaire
        $('#exportForm').on('submit', function(e) {
            e.preventDefault();
            
            const colonnesSelectionnees = $('input[name="colonnes[]"]:checked').length;
            
            if (colonnesSelectionnees === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Veuillez sélectionner au moins une colonne à exporter.'
                });
                return false;
            }

            // Vérifier l'année académique
            const anneeExport = $('#annee_export').val();
            if (!anneeExport) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Attention',
                    text: 'Veuillez sélectionner une année académique.'
                });
                return false;
            }

            // Déterminer l'action selon le format
            const format = $('#format_export').val();
            let action = '';
            
            switch (format) {
                case 'excel':
                    action = 'controller/export_sujets.php';
                    break;
                case 'csv':
                    action = 'controller/export_sujets_csv.php';
                    break;
                case 'pdf':
                    action = 'controller/export_sujets_pdf_simple.php';
                    break;
                default:
                    action = 'controller/export_sujets.php';
            }

            // Mettre à jour l'action du formulaire
            $(this).attr('action', action);

            // Afficher un message de chargement
            Swal.fire({
                title: 'Génération en cours...',
                text: 'Veuillez patienter pendant la génération du fichier.',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Fermer le modal
            $('#exportModal').modal('hide');
            
            // Soumettre le formulaire
            this.submit();
            
            // Fermer le message de chargement après quelques secondes
            setTimeout(() => {
                Swal.close();
            }, 4000);
        });

        // Sauvegarde des préférences d'export dans localStorage
        $('#exportForm input, #exportForm select').on('change', function() {
            const formData = {};
            $('#exportForm').serializeArray().forEach(function(item) {
                if (item.name === 'colonnes[]') {
                    if (!formData['colonnes']) formData['colonnes'] = [];
                    formData['colonnes'].push(item.value);
                } else {
                    formData[item.name] = item.value;
                }
            });
            localStorage.setItem('export_preferences', JSON.stringify(formData));
        });

        // Restauration des préférences d'export
        const savedPrefs = localStorage.getItem('export_preferences');
        if (savedPrefs) {
            try {
                const prefs = JSON.parse(savedPrefs);
                Object.keys(prefs).forEach(function(key) {
                    if (key === 'colonnes') {
                        // Décocher toutes les colonnes d'abord
                        $('input[name="colonnes[]"]').prop('checked', false);
                        // Puis cocher celles sauvegardées
                        prefs[key].forEach(function(value) {
                            $(`input[name="colonnes[]"][value="${value}"]`).prop('checked', true);
                        });
                    } else if (!['annee_export', 'section_export', 'specialisation_export'].includes(key)) {
                        $(`[name="${key}"]`).val(prefs[key]);
                    }
                });
            } catch (e) {
                console.log('Erreur lors de la restauration des préférences:', e);
            }
        }

        // Gestion des raccourcis clavier dans le modal
        $('#exportModal').on('keydown', function(e) {
            // Ctrl+A pour sélectionner toutes les colonnes
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                $('#select-all-cols').click();
            }
            // Ctrl+D pour désélectionner toutes les colonnes
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                $('#deselect-all-cols').click();
            }
        });

        // Gestion des filtres avancés
        // Ouvrir automatiquement les filtres si des filtres sont actifs
        <?php if (!empty($activeFilters)): ?>
        $('#filtersCollapse').addClass('show');
        <?php endif; ?>

        // Animation de l'icône chevron
        $('#filtersCollapse').on('show.bs.collapse', function () {
            $('[data-bs-target="#filtersCollapse"] .bi-chevron-down').removeClass('bi-chevron-down').addClass('bi-chevron-up');
        });

        $('#filtersCollapse').on('hide.bs.collapse', function () {
            $('[data-bs-target="#filtersCollapse"] .bi-chevron-up').removeClass('bi-chevron-up').addClass('bi-chevron-down');
        });

        // Soumission automatique du formulaire de filtres lors du changement
        $('#filtersForm select').on('change', function() {
            // Optionnel: soumettre automatiquement le formulaire
            // $('#filtersForm').submit();
        });

        // Raccourci clavier pour ouvrir/fermer les filtres
        $(document).on('keydown', function(e) {
            // Ctrl+F pour ouvrir/fermer les filtres
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                $('#filtersCollapse').collapse('toggle');
            }
        });

        // Amélioration de l'UX: focus sur le premier champ de filtre quand on ouvre
        $('#filtersCollapse').on('shown.bs.collapse', function () {
            $('#filter_cycle').focus();
        });
    });
</script>

<?php include "./views/include/footer_file.php"; ?>

