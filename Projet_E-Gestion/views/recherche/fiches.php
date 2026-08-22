<?php
include "./views/include/header.php";
include_once "./models/PlanTravail.php";

// Initialiser la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$planTravailModel = new PlanTravail();

// Fonctions utilitaires pour le contrôle d'accès
function getUserSections($db, $userId, $anneeAcadId)
{
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE \"idUser\" = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getCurrentAcademicYear($db)
{
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
$roleLabel = $hasFullAccess ? "Administrateur" : "Responsable de section";

if (!$hasFullAccess && $currentAcademicYear) {
    $userSections = getUserSections($connexion, $currentUserId, $currentAcademicYear);
    $isResponsableSection = !empty($userSections);
}

// Si l'utilisateur n'a pas les droits d'accès complet et n'est responsable d'aucune section
if (!$hasFullAccess && !$isResponsableSection) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    include "./views/include/footer.php";
    exit;
}

// Récupérer l'année académique actuelle (active)
$anneeActuelle = getCurrentAcademicYear($connexion);

// Paramètres de recherche et filtrage
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterSection = isset($_GET['filter_section']) ? $_GET['filter_section'] : '';
$filterPromotion = isset($_GET['filter_promotion']) ? $_GET['filter_promotion'] : '';
$filterAnnee = isset($_GET['filter_annee']) ? $_GET['filter_annee'] : ($anneeActuelle ? $anneeActuelle : 0);
$filterStatutPlan = isset($_GET['filter_statut_plan']) ? $_GET['filter_statut_plan'] : '';

// Récupérer les sections accessibles pour le filtre
$sectionsAccessibles = [];
if ($hasFullAccess) {
    $querySections = "SELECT idsection, \"designationSection\" FROM section ORDER BY \"designationSection\"";
    $stmtSections = $connexion->prepare($querySections);
    $stmtSections->execute();
    $sectionsAccessibles = $stmtSections->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $querySections = "SELECT idsection, designationSection FROM section WHERE idsection IN ($sectionsParams) ORDER BY designationSection";
    $stmtSections = $connexion->prepare($querySections);
    $stmtSections->execute($userSections);
    $sectionsAccessibles = $stmtSections->fetchAll(PDO::FETCH_ASSOC);
}

// Construire la requête pour récupérer TOUS les étudiants des promotions terminales
$whereConditions = [];
$queryParams = [];

// Condition de base selon les droits d'accès
if (!$hasFullAccess) {
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $whereConditions[] = "sec.idsection IN ($sectionsParams)";
    $queryParams = array_merge($queryParams, $userSections);
}

// Filtre de recherche par nom ou matricule
if (!empty($search)) {
    $whereConditions[] = "(e.noms LIKE ? OR e.matricule LIKE ?)";
    $queryParams[] = '%' . $search . '%';
    $queryParams[] = '%' . $search . '%';
}

// Filtre par section
if (!empty($filterSection)) {
    $whereConditions[] = "sec.idsection = ?";
    $queryParams[] = $filterSection;
}

// Filtre par promotion
if (!empty($filterPromotion)) {
    $whereConditions[] = "p.idpromotion = ?";
    $queryParams[] = $filterPromotion;
}

// Filtre par année académique (optionnel)
if ($filterAnnee > 0) {
    $whereConditions[] = "aa.idannee_acad = ?";
    $queryParams[] = $filterAnnee;
}

// CONDITION: Seulement les promotions terminales
$whereConditions[] = "p.est_terminale = 1";

// Requête principale pour récupérer les étudiants avec pagination (infinite scroll)
$limit = 20; // Nombre d'étudiants par page
$whereClause = "WHERE " . implode(' AND ', $whereConditions);

$queryEtudiants = "SELECT DISTINCT e.idetudiant, e.matricule, e.noms, e.photo,
                          p.\"designationPromotion\" as promotion, p.idpromotion, p.cycle,
                          o.\"designationOrientation\" as orientation,
                          sec.\"designationSection\" as section, sec.idsection,
                          aa.designation as annee_academique,
                          s.idsujets, s.intitule as sujet_intitule, s.statut_validation as sujet_statut
                   FROM etudiant e
                   JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                   JOIN orientation o ON p.orientation_idorientation = o.idorientation
                   JOIN section sec ON o.section_idsection = sec.idsection
                   JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                   LEFT JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant AND s.statut_validation = 'Validé'
                   $whereClause
                   AND e.est_actif = 1
                   ORDER BY e.noms
                   LIMIT $limit";

$stmtEtudiants = $connexion->prepare($queryEtudiants);
$stmtEtudiants->execute($queryParams);
$etudiants = $stmtEtudiants->fetchAll(PDO::FETCH_ASSOC);

// Compter le nombre total d'étudiants pour l'infinite scroll
$queryCount = "SELECT COUNT(DISTINCT e.idetudiant) as total
               FROM etudiant e
               JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
               JOIN orientation o ON p.orientation_idorientation = o.idorientation
               JOIN section sec ON o.section_idsection = sec.idsection
               JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
               LEFT JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant AND s.statut_validation = 'Validé'
               $whereClause
               AND e.est_actif = 1";

$stmtCount = $connexion->prepare($queryCount);
$stmtCount->execute($queryParams);
$totalEtudiants = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

// Calculer les statistiques complètes (sans limite pour toutes les données filtrées)
$queryStatistiques = "SELECT 
                        COUNT(DISTINCT e.idetudiant) as total_etudiants,
                        COUNT(DISTINCT CASE WHEN s.idsujets IS NOT NULL THEN e.idetudiant END) as avec_sujet,
                        COUNT(DISTINCT CASE WHEN s.idsujets IS NULL THEN e.idetudiant END) as sans_sujet
                      FROM etudiant e
                      JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                      JOIN orientation o ON p.orientation_idorientation = o.idorientation
                      JOIN section sec ON o.section_idsection = sec.idsection
                      JOIN annee_acad aa ON e.annee_acad_idannee_acad = aa.idannee_acad
                      LEFT JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant AND s.statut_validation = 'Validé'
                      $whereClause
                      AND e.est_actif = 1";

$stmtStatistiques = $connexion->prepare($queryStatistiques);
$stmtStatistiques->execute($queryParams);
$statistiquesData = $stmtStatistiques->fetch(PDO::FETCH_ASSOC);

$statistiques = [
    'total_etudiants' => (int)$statistiquesData['total_etudiants'],
    'avec_sujet' => (int)$statistiquesData['avec_sujet'],
    'sans_sujet' => (int)$statistiquesData['sans_sujet'],
    'avec_plan_valide' => 0,
    'plan_en_attente' => 0,
    'plan_rejete' => 0,
    'progression_moyenne' => 0,
    'promotions_terminales' => 0
];

// Organiser les données par étudiant (seulement pour la première page)
$etudiantsSujets = [];

foreach ($etudiants as $etudiant) {
    $etudiantId = $etudiant['idetudiant'];

    if (!isset($etudiantsSujets[$etudiantId])) {
        $etudiantsSujets[$etudiantId] = [
            'idetudiant' => $etudiant['idetudiant'],
            'matricule' => $etudiant['matricule'],
            'noms' => $etudiant['noms'],
            'photo' => $etudiant['photo'],
            'promotion' => $etudiant['promotion'],
            'cycle' => $etudiant['cycle'],
            'orientation' => $etudiant['orientation'],
            'section' => $etudiant['section'],
            'annee_academique' => $etudiant['annee_academique'],
            'a_sujet' => !empty($etudiant['idsujets']),
            'sujets' => []
        ];
    }

    if ($etudiant['idsujets']) {
        // Récupérer les détails du sujet avec directeur et encadreur
        $queryDetails = "SELECT s.*, 
                               dir.noms as directeur_nom, gd.designation as grade_directeur,
                               enc.noms as encadreur_nom, ge.designation as grade_encadreur
                        FROM sujets s
                        LEFT JOIN agent dir ON s.\"idDirecteur\" = dir.\"idAgent\"
                        LEFT JOIN grade gd ON dir.grade_id = gd.idgrade
                        LEFT JOIN agent enc ON s.\"idEncadreur\" = enc.\"idAgent\"
                        LEFT JOIN grade ge ON enc.grade_id = ge.idgrade
                        WHERE s.idsujets = :idsujets";
        $stmtDetails = $connexion->prepare($queryDetails);
        $stmtDetails->execute(['idsujets' => $etudiant['idsujets']]);
        $sujetDetails = $stmtDetails->fetch(PDO::FETCH_ASSOC);

        if ($sujetDetails) {
            $etudiantsSujets[$etudiantId]['sujets'][] = [
                'idsujets' => $sujetDetails['idsujets'],
                'intitule' => $sujetDetails['intitule'],
                'directeur' => $sujetDetails['directeur_nom'],
                'grade_directeur' => $sujetDetails['grade_directeur'],
                'encadreur' => $sujetDetails['encadreur_nom'],
                'grade_encadreur' => $sujetDetails['grade_encadreur'],
                'statut_validation' => $sujetDetails['statut_validation']
            ];
        }
    }
}

// Les statistiques sans_sujet sont déjà calculées dans la boucle

// Calculer les statistiques de plans et progressions
$totalProgression = 0;
$countProgression = 0;

foreach ($etudiantsSujets as $etudiantId => $etudiant) {
    $progressionData = calculerProgressionEtudiant($connexion, $etudiantId, $planTravailModel);
    
    if ($progressionData['plan_valide']) {
        $statistiques['avec_plan_valide']++;
    } elseif ($progressionData['statut_plan'] === 'En attente') {
        $statistiques['plan_en_attente']++;
    } elseif ($progressionData['statut_plan'] === 'Rejeté') {
        $statistiques['plan_rejete']++;
    }
    
    $totalProgression += $progressionData['pourcentage_global'];
    $countProgression++;
}

if ($countProgression > 0) {
    $statistiques['progression_moyenne'] = round($totalProgression / $countProgression, 1);
}

// Récupérer les promotions pour le filtre (seulement celles avec sujets validés)
$queryPromotions = "SELECT DISTINCT p.idpromotion, p.\"designationPromotion\" 
                   FROM promotion p
                   JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion
                   JOIN orientation o ON p.orientation_idorientation = o.idorientation
                   JOIN section sec ON o.section_idsection = sec.idsection
                   JOIN sujets s ON e.idetudiant = s.etudiant_idetudiant
                   WHERE s.statut_validation = 'Validé'";

if (!$hasFullAccess) {
    $sectionsParams = str_repeat('?,', count($userSections) - 1) . '?';
    $queryPromotions .= " AND sec.idsection IN ($sectionsParams)";
}

$queryPromotions .= ' ORDER BY p."designationPromotion"';

$stmtPromotions = $connexion->prepare($queryPromotions);
if (!$hasFullAccess) {
    $stmtPromotions->execute($userSections);
} else {
    $stmtPromotions->execute();
}
$promotions = $stmtPromotions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les années académiques pour le filtre
$queryAnnees = "SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $connexion->prepare($queryAnnees);
$stmtAnnees->execute();
$anneesAcademiques = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour calculer le pourcentage de progression d'un étudiant basé sur le plan de travail et les validations directeur
function calculerProgressionEtudiant($connexion, $etudiantId, $planTravailModel = null)
{
    try {
        // Récupérer le ou les sujets validés de l'étudiant
        $querySubjects = "SELECT idsujets FROM sujets WHERE etudiant_idetudiant = :etudiantId AND statut_validation = 'Validé'";
        $stmtSubjects = $connexion->prepare($querySubjects);
        $stmtSubjects->execute(['etudiantId' => $etudiantId]);
        $sujets = $stmtSubjects->fetchAll(PDO::FETCH_COLUMN);

        if (empty($sujets)) {
            return [
                'pourcentage_global' => 0,
                'pourcentage_plan' => 0,
                'pourcentage_taches' => 0,
                'total_chapitres' => 0,
                'chapitres_valides' => 0,
                'total_taches' => 0,
                'taches_validees' => 0,
                'plan_valide' => false,
                'statut_plan' => 'Aucun plan'
            ];
        }

        $progressionGlobale = 0;
        $progressionPlan = 0;
        $progressionTaches = 0;
        $totalChapitres = 0;
        $chapitresValides = 0;
        $totalTaches = 0;
        $tachesValidees = 0;
        $planValide = false;
        $statutPlan = 'Aucun plan';

        foreach ($sujets as $sujetId) {
            // 1. Vérifier le plan de travail et son statut de validation
            $plan = $planTravailModel ? $planTravailModel->getPlanBySujet($sujetId) : null;

            if ($plan) {
                $statutPlan = $plan['statut_validation'];
                $planValide = ($plan['statut_validation'] === 'Validé');

                if ($planValide) {
                    // Récupérer les chapitres du plan
                    $chapitres = $planTravailModel->getChapitresByPlan($plan['idplan_travail']);
                    $totalChapitres += count($chapitres);

                    foreach ($chapitres as $chapitre) {
                        if ($chapitre['statut'] === 'Terminé') {
                            $chapitresValides++;
                        }
                    }

                    // Calculer le pourcentage du plan basé sur les chapitres validés par le directeur
                    if ($totalChapitres > 0) {
                        $progressionPlan = round(($chapitresValides / $totalChapitres) * 100);
                    }
                } else {
                    // Plan pas encore validé - progression limitée
                    $progressionPlan = 0;
                }
            }

            // 2. Calculer la progression des tâches traditionnelles (pour compatibilité)
            $queryTaches = "SELECT 
                COUNT(*) as total_taches,
                SUM(CASE WHEN validation = 'Validé' THEN 1 ELSE 0 END) as taches_validees
            FROM taches 
            WHERE sujets_idsujets = :sujetId";

            $stmtTaches = $connexion->prepare($queryTaches);
            $stmtTaches->execute(['sujetId' => $sujetId]);
            $dataTaches = $stmtTaches->fetch(PDO::FETCH_ASSOC);

            if ($dataTaches) {
                $totalTaches += $dataTaches['total_taches'] ?? 0;
                $tachesValidees += $dataTaches['taches_validees'] ?? 0;
            }
        }

        // Calculer la progression des tâches
        if ($totalTaches > 0) {
            $progressionTaches = round(($tachesValidees / $totalTaches) * 100);
        }

        // Calcul de la progression globale - prioriser le plan de travail si validé
        if ($planValide && $totalChapitres > 0) {
            // 80% basé sur les chapitres du plan validés + 20% sur les tâches
            $progressionGlobale = round(($progressionPlan * 0.8) + ($progressionTaches * 0.2));
        } else if ($totalTaches > 0) {
            // Utiliser uniquement les tâches si pas de plan validé
            $progressionGlobale = $progressionTaches;
        }

        return [
            'pourcentage_global' => max(0, min(100, $progressionGlobale)),
            'pourcentage_plan' => $progressionPlan,
            'pourcentage_taches' => $progressionTaches,
            'total_chapitres' => $totalChapitres,
            'chapitres_valides' => $chapitresValides,
            'total_taches' => $totalTaches,
            'taches_validees' => $tachesValidees,
            'plan_valide' => $planValide,
            'statut_plan' => $statutPlan
        ];
    } catch (Exception $e) {
        error_log("Erreur calcul progression étudiant: " . $e->getMessage());
        return [
            'pourcentage_global' => 0,
            'pourcentage_plan' => 0,
            'pourcentage_taches' => 0,
            'total_chapitres' => 0,
            'chapitres_valides' => 0,
            'total_taches' => 0,
            'taches_validees' => 0,
            'plan_valide' => false,
            'statut_plan' => 'Erreur'
        ];
    }
}

// Fonction pour obtenir la classe CSS du badge selon le statut du plan
function getStatutPlanBadgeClass($statut)
{
    switch ($statut) {
        case 'Validé':
            return 'bg-success';
        case 'En attente':
            return 'bg-warning text-dark';
        case 'Rejeté':
            return 'bg-danger';
        case 'Modifié':
            return 'bg-info';
        case 'Aucun plan':
            return 'bg-secondary';
        default:
            return 'bg-light text-dark';
    }
}

// Fonction pour obtenir l'icône Bootstrap selon le statut du plan
function getStatutPlanIcon($statut)
{
    switch ($statut) {
        case 'Validé':
            return 'bi-check-circle-fill';
        case 'En attente':
            return 'bi-clock-fill';
        case 'Rejeté':
            return 'bi-x-circle-fill';
        case 'Modifié':
            return 'bi-pencil-fill';
        case 'Aucun plan':
            return 'bi-file-x';
        default:
            return 'bi-question-circle';
    }
}

function getBadgeClass($validation)
{
    switch ($validation) {
        case 'Validé':
            return 'bg-success';
        case 'En cours':
            return 'bg-warning';
        case 'Rejeté':
            return 'bg-danger';
        case 'En attente':
        default:
            return 'bg-secondary';
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>
            <i class="bi bi-graph-up"></i> Tableau de Bord - Suivi des Étudiants
        </h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item active">Suivi des étudiants</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Indicateur du périmètre de visualisation -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="alert <?= $hasFullAccess ? 'alert-info' : 'alert-warning' ?> border-start border-4">
                    <div class="d-flex align-items-center">
                        <i class="bi <?= $hasFullAccess ? 'bi-shield-check' : 'bi-person-badge' ?> fs-2 me-3"></i>
                        <div>
                            <h5 class="mb-1">
                                <strong>Mode <?= $roleLabel ?></strong>
                            </h5>
                            <p class="mb-0">
                                <?php if ($hasFullAccess): ?>
                                    Vous visualisez <strong>tous les étudiants</strong> de toutes les sections de l'établissement.
                                <?php else: ?>
                                    Vous visualisez uniquement les étudiants des sections dont vous êtes responsable :
                                    <strong>
                                        <?php
                                        $sectionsNames = array_column($sectionsAccessibles, 'designationSection');
                                        echo implode(', ', $sectionsNames);
                                        ?>
                                    </strong>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques en temps réel -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card students-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Étudiants</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $statistiques['total_etudiants'] ?></h6>
                                <small class="text-muted">Promotions terminales</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card subjects-card">
                    <div class="card-body">
                        <h5 class="card-title">Avec Sujet Validé</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $statistiques['avec_sujet'] ?></h6>
                                <span class="text-success small pt-1 fw-bold">
                                    <?= $statistiques['total_etudiants'] > 0 ? round(($statistiques['avec_sujet'] / $statistiques['total_etudiants']) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card warning-card">
                    <div class="card-body">
                        <h5 class="card-title">Sans Sujet</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $statistiques['sans_sujet'] ?></h6>
                                <span class="text-danger small pt-1 fw-bold">
                                    <?= $statistiques['total_etudiants'] > 0 ? round(($statistiques['sans_sujet'] / $statistiques['total_etudiants']) * 100, 1) : 0 ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card progress-card">
                    <div class="card-body">
                        <h5 class="card-title">Progression Moyenne</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $statistiques['progression_moyenne'] ?>%</h6>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $statistiques['progression_moyenne'] ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtres et recherche améliorés -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body py-3">
                        <h6 class="card-title mb-2">
                            <i class="bi bi-funnel me-1"></i>Filtres de recherche
                        </h6>
                        
                        <form id="filtersForm" method="GET" action="" class="row g-3">
                            <input type="hidden" name="view" value="recherche/fiches">
                            
                            <div class="col-md-3">
                                <label for="filter_annee" class="form-label">
                                <i class="bi bi-calendar3 me-1"></i>Année Académique
                                </label>
                                <select id="filter_annee" name="filter_annee" class="form-select" onchange="loadSectionsByAnnee(this.value)">
                                    <option value="0">Toutes les années</option>
                                <?php foreach ($anneesAcademiques as $annee): ?>
                                    <option value="<?= $annee['idannee_acad'] ?>"
                                        <?= $filterAnnee == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($annee['designation']) ?>
                                <?php if ($anneeActuelle && $annee['idannee_acad'] == $anneeActuelle): ?>
                                        (En cours)
                                        <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_section" class="form-label">
                                    <i class="bi bi-diagram-3 me-1"></i>Section
                                </label>
                                <select class="form-select" id="filter_section" name="filter_section" onchange="loadPromotionsBySection(this.value)" <?= empty($filterAnnee) ? 'disabled' : '' ?>>
                                    <option value="">
                                        <?= empty($filterAnnee) ? 'Choisir d\'abord une année' : 'Toutes les sections' ?>
                                    </option>
                                    <?php if (!empty($filterAnnee)): ?>
                                        <?php foreach ($sectionsAccessibles as $section): ?>
                                            <option value="<?= $section['idsection'] ?>" 
                                                    <?= $filterSection == $section['idsection'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($section['designationSection']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="filter_promotion" class="form-label">
                                    <i class="bi bi-mortarboard me-1"></i>Promotion Terminale
                                </label>
                                <select class="form-select" id="filter_promotion" name="filter_promotion" <?= empty($filterSection) ? 'disabled' : '' ?>>
                                    <option value="">
                                        <?= empty($filterSection) ? 'Choisir d\'abord une section' : 'Toutes les promotions terminales' ?>
                                    </option>
                                    <?php if (!empty($filterSection)): ?>
                                        <?php foreach ($promotions as $promotion): ?>
                                            <option value="<?= $promotion['idpromotion'] ?>" 
                                                    <?= $filterPromotion == $promotion['idpromotion'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">
                                    <i class="bi bi-search me-1"></i>Recherche globale
                                </label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Nom, matricule..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-filter"></i> Appliquer filtres
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                                    <i class="bi bi-x-circle"></i> Réinitialiser
                                </button>
                                <?php if (!empty($search) || !empty($filterSection) || !empty($filterPromotion) || !empty($filterAnnee)): ?>
                                    <span class="badge bg-info ms-2">
                                        <i class="bi bi-funnel"></i> Filtres actifs
                                    </span>
                                <?php endif; ?>
                            </div>
                        </form>
                        
                        <?php if (!empty($search) || !empty($filterSection) || !empty($filterPromotion) || !empty($filterAnnee)): ?>
                            <div class="mt-3">
                                <div class="alert alert-info d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Filtres actifs :</strong>
                                        <?php
                                        $activeFilters = [];
                                        if (!empty($search)) $activeFilters[] = "Recherche: \"$search\"";
                                        if (!empty($filterAnnee)) {
                                            $anneeName = '';
                                            foreach ($anneesAcademiques as $a) {
                                                if ($a['idannee_acad'] == $filterAnnee) {
                                                    $anneeName = $a['designation'];
                                                    break;
                                                }
                                            }
                                            $activeFilters[] = "Année: $anneeName";
                                        }
                                        if (!empty($filterSection)) {
                                            $sectionName = '';
                                            foreach ($sectionsAccessibles as $s) {
                                                if ($s['idsection'] == $filterSection) {
                                                    $sectionName = $s['designationSection'];
                                                    break;
                                                }
                                            }
                                            $activeFilters[] = "Section: $sectionName";
                                        }
                                        if (!empty($filterPromotion)) {
                                            $promotionName = '';
                                            foreach ($promotions as $p) {
                                                if ($p['idpromotion'] == $filterPromotion) {
                                                    $promotionName = $p['designationPromotion'];
                                                    break;
                                                }
                                            }
                                            $activeFilters[] = "Promotion: $promotionName";
                                        }
                                        echo implode(' • ', $activeFilters);
                                        ?>
                                        <span class="badge bg-primary ms-2"><?= count($etudiantsSujets) ?> résultat(s)</span>
                                    </div>
                                    <a href="?view=recherche/fiches" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x"></i> Effacer filtres
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des étudiants -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">
                                <i class="bi bi-people-fill"></i> 
                                Liste des Étudiants (<span id="student-count"><?= count($etudiantsSujets) ?></span> / <span id="total-students"><?= $totalEtudiants ?></span>)
                            </h5>
                            <div>
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                                    <i class="bi bi-file-excel"></i> Exporter
                                </button>
                            </div>
                        </div>

                        <?php if (empty($etudiantsSujets)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun étudiant trouvé avec les critères de recherche actuels.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Étudiant</th>
                                            <th>Section / Promotion</th>
                                            <th>Sujet de Recherche</th>
                                            <th>Plan de Travail</th>
                                            <th>Progression</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="students-list">
                                    <?php 
                                    $numeroEtudiant = 1;
                                    foreach ($etudiantsSujets as $etudiantId => $etudiant):
                                            $progressionData = calculerProgressionEtudiant($connexion, $etudiantId, $planTravailModel);
                                            $progression = $progressionData['pourcentage_global'];
                                            
                                            // Déterminer la couleur de la barre de progression
                                            $progressColor = 'bg-secondary';
                                            if ($progressionData['plan_valide']) {
                                                $progressColor = 'bg-success';
                                            } elseif ($progressionData['statut_plan'] === 'En attente') {
                                                $progressColor = 'bg-warning';
                                            } elseif ($progressionData['statut_plan'] === 'Rejeté') {
                                                $progressColor = 'bg-danger';
                                            } elseif ($progression > 0) {
                                                $progressColor = 'bg-info';
                                            }
                                        ?>
                                            <tr>
                                                <td><span class="badge bg-secondary"><?= $numeroEtudiant ?></span></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($etudiant['photo'])): ?>
                                                            <img src="<?= htmlspecialchars($etudiant['photo']) ?>" 
                                                                 class="rounded-circle me-2" width="40" height="40" 
                                                                 alt="Photo">
                                                        <?php else: ?>
                                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                                 style="width: 40px; height: 40px;">
                                                                <i class="bi bi-person text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?= htmlspecialchars($etudiant['noms']) ?></strong><br>
                                                            <small class="text-muted"><?= htmlspecialchars($etudiant['matricule']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info mb-1"><?= htmlspecialchars($etudiant['section']) ?></span><br>
                                                    <small><?= htmlspecialchars($etudiant['promotion']) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($etudiant['a_sujet'] && !empty($etudiant['sujets'])): ?>
                                                        <?php foreach ($etudiant['sujets'] as $sujet): ?>
                                                            <div class="mb-2">
                                                                <strong><?= htmlspecialchars($sujet['intitule']) ?></strong><br>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-person-check"></i> 
                                                                    <?= htmlspecialchars($sujet['directeur']) ?>
                                                                    <?php if (!empty($sujet['encadreur'])): ?>
                                                                        | <i class="bi bi-person"></i> <?= htmlspecialchars($sujet['encadreur']) ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="alert alert-warning py-1 px-2 mb-0">
                                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                                            <strong>Aucun sujet assigné</strong>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?= getStatutPlanBadgeClass($progressionData['statut_plan']) ?>">
                                                        <i class="bi <?= getStatutPlanIcon($progressionData['statut_plan']) ?>"></i>
                                                        <?= $progressionData['statut_plan'] ?>
                                                    </span>
                                                    <?php if ($progressionData['plan_valide'] && $progressionData['total_chapitres'] > 0): ?>
                                                        <br><small class="text-muted">
                                                            <?= $progressionData['chapitres_valides'] ?>/<?= $progressionData['total_chapitres'] ?> chapitres
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="progress me-2" style="width: 80px; height: 8px;">
                                                            <div class="progress-bar <?= $progressColor ?>" 
                                                                 style="width: <?= $progression ?>%"></div>
                                                        </div>
                                                        <span class="small"><?= $progression ?>%</span>
                                                    </div>
                                                    <?php if ($progressionData['plan_valide']): ?>
                                                        <small class="text-success">Plan: <?= $progressionData['pourcentage_plan'] ?>%</small>
                                                    <?php endif; ?>
                                                    <?php if ($progressionData['total_taches'] > 0): ?>
                                                        <br><small class="text-info">Tâches: <?= $progressionData['pourcentage_taches'] ?>%</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="voirDetailsEtudiant(<?= $etudiantId ?>)" 
                                                                title="Voir détails complets">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                                onclick="exportFicheAvancement(<?= $etudiantId ?>)" 
                                                                title="Exporter fiche PDF">
                                                            <i class="bi bi-file-pdf"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php 
                                            $numeroEtudiant++;
                                        endforeach; 
                                        ?>
                                    </tbody>
                                    </table>
                                    </div>
                                        
                            <!-- Indicateur de chargement pour infinite scroll -->
                            <div id="loading-indicator" class="text-center py-3" style="display: none;">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <p class="mt-2">Chargement des étudiants...</p>
                            </div>
                            
                            <!-- Sentinelle pour l'infinite scroll -->
                            <div id="scroll-sentinel" style="height: 1px;"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal d'exportation -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Exporter les données</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="exportForm" action="controller/export_avancement.php" method="POST">
                        <div class="mb-3">
                            <label for="format" class="form-label">Format d'exportation</label>
                            <select class="form-select" id="format" name="format" required>
                                <option value="excel">Excel</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="include_details" name="include_details" value="1" checked>
                                <label class="form-check-label" for="include_details">
                                    Inclure les détails de progression
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" form="exportForm" class="btn btn-success">
                        <i class="bi bi-download"></i> Exporter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal détails étudiant -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de l'étudiant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Contenu chargé dynamiquement -->
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .info-card {
        box-shadow: 0px 0 30px rgba(1, 41, 112, 0.1);
        border-radius: 10px;
        overflow: hidden;
    }

    .card-icon {
        color: #012970;
        background: rgba(1, 41, 112, 0.1);
        width: 64px;
        height: 64px;
        font-size: 28px;
    }

    .students-card .card-icon {
        color: #4154f1;
        background: rgba(65, 84, 241, 0.1);
    }

    .subjects-card .card-icon {
        color: #2eca6a;
        background: rgba(46, 202, 106, 0.1);
    }

    .plans-card .card-icon {
        color: #ff771d;
        background: rgba(255, 119, 29, 0.1);
    }

    .progress-card .card-icon {
        color: #bb0852;
        background: rgba(187, 8, 82, 0.1);
    }

    .warning-card .card-icon {
        color: #dc3545;
        background: rgba(220, 53, 69, 0.1);
    }

    .table th {
        font-weight: 600;
        color: #012970;
    }

    .progress {
        border-radius: 10px;
        background-color: #e9ecef;
    }

    .progress-bar {
        border-radius: 10px;
    }

    .btn-group .btn {
        border-radius: 4px !important;
        margin-right: 2px;
    }

    .alert {
        border: none;
        border-radius: 10px;
    }

    .card {
        border-radius: 10px;
        box-shadow: 0px 0 30px rgba(1, 41, 112, 0.1);
    }
</style>

<script>
    // Fonction pour exporter la fiche d'avancement
    function exportFicheAvancement(etudiantId) {
        window.location.href = `controller/export_fiche_avancement.php?etudiant_id=${etudiantId}`;
    }



    // Fonction pour voir les détails d'un étudiant
    function voirDetailsEtudiant(etudiantId) {
        // Afficher le modal avec un loader
        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        document.getElementById('detailsContent').innerHTML = `
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des détails...</p>
            </div>
        `;
        modal.show();

        // Charger les détails via AJAX
        fetch(`controller/get_student_details.php?etudiant_id=${etudiantId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('detailsContent').innerHTML = data.html;
                } else {
                    document.getElementById('detailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            Erreur lors du chargement des détails.
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('detailsContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        Erreur de connexion.
                    </div>
                `;
            });
    }

    // Fonction pour réinitialiser les filtres
    function resetFilters() {
        window.location.href = '?view=recherche/fiches';
    }

    // Fonction pour charger les sections par année avec AJAX
    function loadSectionsByAnnee(anneeId) {
        const sectionSelect = document.getElementById('filter_section');
        const promotionSelect = document.getElementById('filter_promotion');
        
        // Réinitialiser les sections et promotions
        sectionSelect.innerHTML = '<option value="">Chargement...</option>';
        sectionSelect.disabled = true;
        promotionSelect.innerHTML = '<option value="">Choisir d\'abord une section</option>';
        promotionSelect.disabled = true;
        
        if (anneeId === '' || anneeId === '0') {
            sectionSelect.innerHTML = '<option value="">Toutes les sections</option>';
            promotionSelect.innerHTML = '<option value="">Toutes les promotions terminales</option>';
            sectionSelect.disabled = false;
            promotionSelect.disabled = false;
            return;
        }
        
        // Charger les sections via AJAX
        fetch(`controller/get_sections_by_annee.php?annee_id=${anneeId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.sections) {
                    sectionSelect.innerHTML = '<option value="">Toutes les sections</option>';
                    data.sections.forEach(section => {
                        const option = document.createElement('option');
                        option.value = section.idsection;
                        option.textContent = section.designationSection;
                        sectionSelect.appendChild(option);
                    });
                    sectionSelect.disabled = false;
                } else {
                    sectionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                sectionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            });
    }

    // Fonction pour charger les promotions par section avec AJAX
    function loadPromotionsBySection(sectionId) {
        const promotionSelect = document.getElementById('filter_promotion');
        const anneeId = document.getElementById('filter_annee').value;
        
        // Réinitialiser les promotions
        promotionSelect.innerHTML = '<option value="">Chargement...</option>';
        promotionSelect.disabled = true;
        
        if (sectionId === '' || anneeId === '') {
            promotionSelect.innerHTML = '<option value="">Choisir d\'abord une section</option>';
            return;
        }
        
        // Charger les promotions via AJAX
        fetch(`controller/get_promotions_by_section.php?section_id=${sectionId}&annee_id=${anneeId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.promotions) {
                    promotionSelect.innerHTML = '<option value="">Toutes les promotions terminales</option>';
                    data.promotions.forEach(promotion => {
                        const option = document.createElement('option');
                        option.value = promotion.idpromotion;
                        option.textContent = promotion.designationPromotion;
                        promotionSelect.appendChild(option);
                    });
                    promotionSelect.disabled = false;
                } else {
                    promotionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                promotionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initialiser les tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Animation des barres de progression
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 200);
        });

        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search').focus();
            }
        });

        // Auto-submit form on filter change (désactivé pour permettre les filtres multiples)
        // document.getElementById('filter_section').addEventListener('change', function() {
        //     this.form.submit();
        // });
        
        // document.getElementById('filter_promotion').addEventListener('change', function() {
        //     this.form.submit();
        // });
        
        // Soumission du formulaire lors de l'appui sur Entrée dans le champ de recherche
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('filtersForm').submit();
            }
        });

        // Variables pour l'infinite scroll
        let currentOffset = <?= count($etudiantsSujets) ?>;
        let isLoading = false;
        let hasMore = <?= count($etudiantsSujets) < $totalEtudiants ? 'true' : 'false' ?>;
        let currentStudentNumber = <?= count($etudiantsSujets) + 1 ?>;

        // Fonction pour charger plus d'étudiants
        function loadMoreStudents() {
            if (isLoading || !hasMore) return;
            
            isLoading = true;
            document.getElementById('loading-indicator').style.display = 'block';

            // Récupérer les filtres actuels
            const formData = new FormData();
            formData.append('offset', currentOffset);
            formData.append('recherche', document.getElementById('search').value || '');
            formData.append('section', document.getElementById('filter_section').value || '');
            formData.append('promotion', document.getElementById('filter_promotion').value || '');
            formData.append('annee', document.getElementById('filter_annee').value || '');

            fetch('controller/load_more_fiches.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.etudiants.length > 0) {
                    // Ajouter les nouveaux étudiants au tableau
                    const tbody = document.getElementById('students-list');
                    
                    data.etudiants.forEach(etudiant => {
                        const row = createStudentRow(etudiant, currentStudentNumber);
                        tbody.appendChild(row);
                        currentStudentNumber++;
                    });

                    currentOffset += data.etudiants.length;
                    hasMore = data.hasMore;
                    
                    // Mettre à jour le compteur
                    document.getElementById('student-count').textContent = currentOffset;
                } else {
                    hasMore = false;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement:', error);
                hasMore = false;
            })
            .finally(() => {
                isLoading = false;
                document.getElementById('loading-indicator').style.display = 'none';
            });
        }

        // Fonction pour créer une ligne d'étudiant
        function createStudentRow(etudiant, numero) {
            const tr = document.createElement('tr');
            
            // Déterminer la couleur de progression
            let progressColor = 'bg-secondary';
            if (etudiant.progression > 80) {
                progressColor = 'bg-success';
            } else if (etudiant.progression > 50) {
                progressColor = 'bg-info';
            } else if (etudiant.progression > 20) {
                progressColor = 'bg-warning';
            } else if (etudiant.progression > 0) {
                progressColor = 'bg-danger';
            }

            tr.innerHTML = `
                <td><span class="badge bg-secondary">${numero}</span></td>
                <td>
                    <div class="d-flex align-items-center">
                        ${etudiant.photo ? 
                            `<img src="uploads/${etudiant.photo}" alt="Photo" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` :
                            `<div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 40px; height: 40px;">
                                <i class="bi bi-person text-white"></i>
                             </div>`
                        }
                        <div>
                            <div class="fw-bold">${etudiant.noms}</div>
                            <small class="text-muted">Mat: ${etudiant.matricule || 'N/A'}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <div>
                        <span class="badge bg-primary mb-1">${etudiant.section}</span><br>
                        <small class="text-muted">${etudiant.promotion} (${etudiant.cycle} Cycle)</small>
                    </div>
                </td>
                <td>
                    ${etudiant.sujets.length > 0 ? 
                        `<div class="text-success">
                            <i class="bi bi-check-circle"></i>
                            <strong>Sujet Validé</strong><br>
                            <small>${etudiant.sujets[0].intitule}</small>
                         </div>` :
                        `<div class="text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span>Aucun sujet validé</span>
                         </div>`
                    }
                </td>
                <td>
                    ${etudiant.sujets.length > 0 ? 
                        `<span class="badge bg-success">
                            <i class="bi bi-check-circle"></i> Plan Validé
                         </span>` :
                        `<span class="badge bg-secondary">
                            <i class="bi bi-dash"></i> Aucun plan
                         </span>`
                    }
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="progress me-2" style="width: 80px; height: 20px;">
                            <div class="progress-bar ${progressColor}" role="progressbar" 
                                 style="width: ${etudiant.progression}%"></div>
                        </div>
                        <span class="small">${etudiant.progression}%</span>
                    </div>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                onclick="voirDetailsEtudiant(${etudiant.idetudiant})" 
                                title="Voir détails complets">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success" 
                                onclick="exportFicheAvancement(${etudiant.idetudiant})" 
                                title="Exporter fiche PDF">
                            <i class="bi bi-file-pdf"></i>
                        </button>
                    </div>
                </td>
            `;
            
            return tr;
        }

        // Observer pour l'infinite scroll
        const scrollSentinel = document.getElementById('scroll-sentinel');
        if (scrollSentinel && document.getElementById('students-list')) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && hasMore && !isLoading) {
                        loadMoreStudents();
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });

            observer.observe(scrollSentinel);
        }
    });
</script>

<?php include "./views/include/footer_file.php"; ?>
