<?php
include "./views/include/header.php";

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// Récupérer l'année académique en cours
$pdo = Connexion::getInstance()->getPDO();

// Vérifier si la colonne est_active existe
$checkColumn = "SELECT column_name FROM information_schema.columns WHERE table_name = 'annee_acad' AND table_schema = 'public' AND column_name = 'est_active'";
$stmtCheck = $pdo->prepare($checkColumn);
$stmtCheck->execute();
$columnExists = $stmtCheck->fetch();

if ($columnExists) {
    $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
} else {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
}

$stmtAnnee = $pdo->prepare($queryAnnee);
$stmtAnnee->execute();
$currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

if (!$currentYear) {
    $queryAnnee = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
    $stmtAnnee = $pdo->prepare($queryAnnee);
    $stmtAnnee->execute();
    $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
}

// Récupérer les sections dont l'utilisateur est responsable
$query = "SELECT section_idsection 
          FROM responsable_section 
          WHERE \"idUser\" = :userId 
          AND annee_acad_idannee_acad = :anneeId";

$stmt = $pdo->prepare($query);
$stmt->bindParam(':userId', $currentUserId);
$stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
$stmt->execute();
$userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

$isResponsableSection = !empty($userSections);

// Récupérer les paramètres de filtrage
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : $currentYear['idannee_acad'];
$sectionFilter = isset($_GET['section']) ? intval($_GET['section']) : 0;
$cycleFilter = isset($_GET['cycle']) ? $_GET['cycle'] : '';

// Fonction pour récupérer les statistiques d'inscriptions par promotion
function getStatistiquesInscriptions($pdo, $sections = [], $anneeId = null, $cycleFilter = '') {
    $params = [];
    
    $query = "SELECT 
                p.idpromotion,
                p.\"designationPromotion\",
                p.cycle,
                o.\"designationOrientation\" as orientation,
                s.\"designationSection\" as section,
                COUNT(e.idetudiant) as total_inscrits,
                COUNT(CASE WHEN e.est_actif = 1 THEN 1 END) as inscrits_actifs,
                COUNT(CASE WHEN e.est_actif = 0 THEN 1 END) as inscrits_inactifs,
                COUNT(CASE WHEN e.dossier_complete = 1 THEN 1 END) as dossiers_complets,
                COUNT(CASE WHEN e.dossier_complete = 0 THEN 1 END) as dossiers_incomplets,
                COUNT(CASE WHEN e.sexe = 'Masculin' THEN 1 END) as hommes,
                COUNT(CASE WHEN e.sexe = 'Feminin' THEN 1 END) as femmes
              FROM promotion p
              LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
              LEFT JOIN section s ON o.section_idsection = s.idsection
              LEFT JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion 
                                   AND e.annee_acad_idannee_acad = p.annee_acad_idannee_acad
              WHERE 1=1";
    
    // Filtrer par sections si spécifié
    if (!empty($sections) && is_array($sections)) {
        $sectionParams = [];
        foreach ($sections as $i => $section) {
            if (!empty($section)) {
                $paramName = ":section{$i}";
                $sectionParams[] = $paramName;
                $params[$paramName] = $section;
            }
        }
        
        if (!empty($sectionParams)) {
            $placeholders = implode(',', $sectionParams);
            $query .= " AND o.section_idsection IN ($placeholders)";
        }
    }
    
    // Filtrer par année académique
    if (!empty($anneeId)) {
        $query .= " AND p.annee_acad_idannee_acad = :anneeId";
        $params[':anneeId'] = $anneeId;
    }
    
    // Filtrer par cycle
    if (!empty($cycleFilter)) {
        $query .= " AND p.cycle = :cycle";
        $params[':cycle'] = $cycleFilter;
    }
    
    $query .= " GROUP BY p.idpromotion, p.\"designationPromotion\", p.cycle, o.\"designationOrientation\", s.\"designationSection\"
                ORDER BY s.\"designationSection\", p.cycle, p.\"designationPromotion\"";
    
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur dans getStatistiquesInscriptions: " . $e->getMessage());
        return [];
    }
}

// Fonction pour récupérer les statistiques globales
function getStatistiquesGlobales($pdo, $sections = [], $anneeId = null, $cycleFilter = '') {
    $params = [];
    
    $query = "SELECT 
                COUNT(DISTINCT e.idetudiant) as total_etudiants,
                COUNT(DISTINCT CASE WHEN e.est_actif = 1 THEN e.idetudiant END) as etudiants_actifs,
                COUNT(DISTINCT CASE WHEN e.dossier_complete = 1 THEN e.idetudiant END) as dossiers_complets,
                COUNT(DISTINCT CASE WHEN e.sexe = 'Masculin' THEN e.idetudiant END) as total_hommes,
                COUNT(DISTINCT CASE WHEN e.sexe = 'Feminin' THEN e.idetudiant END) as total_femmes,
                COUNT(DISTINCT p.idpromotion) as total_promotions
              FROM etudiant e
              JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                               AND e.annee_acad_idannee_acad = p.annee_acad_idannee_acad
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              WHERE 1=1";
    
    // Filtrer par sections si spécifié
    if (!empty($sections) && is_array($sections)) {
        $sectionParams = [];
        foreach ($sections as $i => $section) {
            if (!empty($section)) {
                $paramName = ":section{$i}";
                $sectionParams[] = $paramName;
                $params[$paramName] = $section;
            }
        }
        
        if (!empty($sectionParams)) {
            $placeholders = implode(',', $sectionParams);
            $query .= " AND o.section_idsection IN ($placeholders)";
        }
    }
    
    // Filtrer par année académique
    if (!empty($anneeId)) {
        $query .= " AND e.annee_acad_idannee_acad = :anneeId";
        $params[':anneeId'] = $anneeId;
    }
    
    // Filtrer par cycle
    if (!empty($cycleFilter)) {
        $query .= " AND p.cycle = :cycle";
        $params[':cycle'] = $cycleFilter;
    }
    
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur dans getStatistiquesGlobales: " . $e->getMessage());
        return [];
    }
}

// Récupérer les données en fonction des droits de l'utilisateur
if ($isResponsableSection) {
    // Si une section spécifique est sélectionnée, vérifier que l'utilisateur y a accès
    if ($sectionFilter > 0) {
        if (in_array($sectionFilter, $userSections)) {
            $sectionsForStats = [$sectionFilter];
        } else {
            $sectionsForStats = [];
        }
    } else {
        $sectionsForStats = $userSections;
    }
    
    $statistiques = getStatistiquesInscriptions($pdo, $sectionsForStats, $anneeId, $cycleFilter);
    $statsGlobales = getStatistiquesGlobales($pdo, $sectionsForStats, $anneeId, $cycleFilter);
} else {
    // Vérifier si l'utilisateur a le droit d'accéder à toutes les données
    $hasFullAccess = $_SESSION['idRole'] == 1;

    if ($hasFullAccess) {
        if ($sectionFilter > 0) {
            $sectionsForStats = [$sectionFilter];
        } else {
            $sectionsForStats = [];
        }
        
        $statistiques = getStatistiquesInscriptions($pdo, $sectionsForStats, $anneeId, $cycleFilter);
        $statsGlobales = getStatistiquesGlobales($pdo, $sectionsForStats, $anneeId, $cycleFilter);
    } else {
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
}

// Récupérer les données nécessaires pour les filtres
// Années académiques
$queryAnnees = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtAnnees = $pdo->prepare($queryAnnees);
$stmtAnnees->execute();
$academicYears = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Sections disponibles selon les droits
if ($isResponsableSection) {
    $sections = [];
    if (!empty($userSections)) {
        $sectionPlaceholders = implode(',', array_fill(0, count($userSections), '?'));
        $querySection = "SELECT * FROM section WHERE idsection IN ($sectionPlaceholders) ORDER BY designationSection";
        $stmtSection = $pdo->prepare($querySection);
        foreach ($userSections as $i => $section) {
            $stmtSection->bindValue($i+1, $section);
        }
        $stmtSection->execute();
        $sections = $stmtSection->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    $querySection = "SELECT * FROM section ORDER BY \"designationSection\"";
    $stmtSection = $pdo->prepare($querySection);
    $stmtSection->execute();
    $sections = $stmtSection->fetchAll(PDO::FETCH_ASSOC);
}

// Organiser les statistiques par cycle
$statsByCycle = [
    'Premier' => [],
    'Deuxieme' => [],
    'Troisieme' => []
];

foreach ($statistiques as $stat) {
    $cycle = $stat['cycle'] ?? 'Premier';
    $statsByCycle[$cycle][] = $stat;
}

$cycleNames = [
    'Premier' => 'Licence (Premier cycle)',
    'Deuxieme' => 'Master (Deuxième cycle)', 
    'Troisieme' => 'Doctorat (Troisième cycle)'
];

$cycleIcons = [
    'Premier' => 'bi-mortarboard',
    'Deuxieme' => 'bi-award',
    'Troisieme' => 'bi-journal-bookmark'
];

$cycleColors = [
    'Premier' => 'primary',
    'Deuxieme' => 'success',
    'Troisieme' => 'warning'
];

?>

<!-- Début du HTML -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>TABLEAU DE BORD DES INSCRIPTIONS PAR PROMOTION</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item"><a href="#">Étudiants</a></li>
                <li class="breadcrumb-item active">Tableau de bord des inscriptions</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">

        <!-- Informations sur les sections gérées -->
        <?php if ($isResponsableSection): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Vous visualisez uniquement les inscriptions des promotions relevant de votre responsabilité.
            <?php if (count($userSections) > 0): ?>
                <strong>Sections:</strong> 
                <?php 
                $sectionNames = [];
                foreach ($sections as $section) {
                    $sectionNames[] = $section['designationSection'];
                }
                echo implode(', ', $sectionNames);
                ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtres</h5>
                        <form method="GET" action="" class="row g-3" id="filterForm">
                            <input type="hidden" name="view" value="etudiants/tableau_bord_inscriptions">
                            
                            <div class="col-md-3">
                                <label for="anneeFilter" class="form-label">Année académique</label>
                                <select name="annee" class="form-select" id="anneeFilter">
                                    <?php foreach ($academicYears as $year): ?>
                                        <option value="<?= $year['idannee_acad'] ?>" <?= $anneeId == $year['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="sectionFilter" class="form-label">Section</label>
                                <select name="section" class="form-select">
                                    <option value="">Toutes les sections</option>
                                    <?php foreach ($sections as $sec): ?>
                                        <option value="<?= $sec['idsection'] ?>" <?= $sectionFilter == $sec['idsection'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($sec['designationSection']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="cycleFilter" class="form-label">Cycle</label>
                                <select name="cycle" class="form-select">
                                    <option value="">Tous les cycles</option>
                                    <option value="Premier" <?= $cycleFilter == 'Premier' ? 'selected' : '' ?>>Licence</option>
                                    <option value="Deuxieme" <?= $cycleFilter == 'Deuxieme' ? 'selected' : '' ?>>Master</option>
                                    <option value="Troisieme" <?= $cycleFilter == 'Troisieme' ? 'selected' : '' ?>>Doctorat</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques globales -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques générales des inscriptions</h5>
                        <div class="row">
                            <div class="col-md-2">
                                <div class="card info-card sales-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Total étudiants</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-people"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsGlobales['total_etudiants'] ?? 0 ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Étudiants actifs</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-check-circle"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsGlobales['etudiants_actifs'] ?? 0 ?></h6>
                                                <span class="text-success small pt-1 fw-bold">
                                                    <?= $statsGlobales['total_etudiants'] > 0 ? round(($statsGlobales['etudiants_actifs'] / $statsGlobales['total_etudiants']) * 100) : 0 ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card info-card customers-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Dossiers complets</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-folder-check"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsGlobales['dossiers_complets'] ?? 0 ?></h6>
                                                <span class="text-info small pt-1 fw-bold">
                                                    <?= $statsGlobales['total_etudiants'] > 0 ? round(($statsGlobales['dossiers_complets'] / $statsGlobales['total_etudiants']) * 100) : 0 ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Hommes</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-person"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsGlobales['total_hommes'] ?? 0 ?></h6>
                                                <span class="text-primary small pt-1 fw-bold">
                                                    <?= $statsGlobales['total_etudiants'] > 0 ? round(($statsGlobales['total_hommes'] / $statsGlobales['total_etudiants']) * 100) : 0 ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Femmes</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-person-dress"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsGlobales['total_femmes'] ?? 0 ?></h6>
                                                <span class="text-danger small pt-1 fw-bold">
                                                    <?= $statsGlobales['total_etudiants'] > 0 ? round(($statsGlobales['total_femmes'] / $statsGlobales['total_etudiants']) * 100) : 0 ?>%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Promotions</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-collection"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $statsGlobales['total_promotions'] ?? 0 ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques détaillées par cycle -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Statistiques détaillées par promotion
                            <div class="float-end">
                                <button type="button" class="btn btn-success me-2" onclick="exportData()">
                                    <i class="bi bi-file-excel"></i> Exporter
                                </button>
                            </div>
                        </h5>

                        <?php if (empty($statistiques)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucune donnée d'inscription trouvée avec les critères spécifiés.
                            </div>
                        <?php else: ?>
                            <?php foreach ($statsByCycle as $cycleKey => $statsOfCycle): ?>
                                <?php if (empty($statsOfCycle)) continue; ?>
                                
                                <div class="mb-4">
                                    <div class="card border-<?= $cycleColors[$cycleKey] ?>">
                                        <div class="card-header bg-<?= $cycleColors[$cycleKey] ?> text-white">
                                            <h5 class="mb-0">
                                                <i class="<?= $cycleIcons[$cycleKey] ?> me-2"></i>
                                                <?= $cycleNames[$cycleKey] ?>
                                                <span class="badge bg-light text-dark ms-2"><?= count($statsOfCycle) ?> promotion(s)</span>
                                            </h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-striped table-bordered mb-0">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Promotion</th>
                                                            <th>Orientation</th>
                                                            <th>Section</th>
                                                            <th>Total inscrits</th>
                                                            <th>Actifs</th>
                                                            <th>Inactifs</th>
                                                            <th>Dossiers complets</th>
                                                            <th>Dossiers incomplets</th>
                                                            <th>Hommes</th>
                                                            <th>Femmes</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($statsOfCycle as $stat): ?>
                                                            <tr>
                                                                <td><strong><?= htmlspecialchars($stat['designationPromotion']) ?></strong></td>
                                                                <td><?= htmlspecialchars($stat['orientation']) ?></td>
                                                                <td><?= htmlspecialchars($stat['section']) ?></td>
                                                                <td>
                                                                    <span class="badge bg-primary"><?= $stat['total_inscrits'] ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-success"><?= $stat['inscrits_actifs'] ?></span>
                                                                    <?php if ($stat['total_inscrits'] > 0): ?>
                                                                        <small class="text-muted">(<?= round(($stat['inscrits_actifs'] / $stat['total_inscrits']) * 100) ?>%)</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-warning"><?= $stat['inscrits_inactifs'] ?></span>
                                                                    <?php if ($stat['total_inscrits'] > 0): ?>
                                                                        <small class="text-muted">(<?= round(($stat['inscrits_inactifs'] / $stat['total_inscrits']) * 100) ?>%)</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-info"><?= $stat['dossiers_complets'] ?></span>
                                                                    <?php if ($stat['total_inscrits'] > 0): ?>
                                                                        <small class="text-muted">(<?= round(($stat['dossiers_complets'] / $stat['total_inscrits']) * 100) ?>%)</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-secondary"><?= $stat['dossiers_incomplets'] ?></span>
                                                                    <?php if ($stat['total_inscrits'] > 0): ?>
                                                                        <small class="text-muted">(<?= round(($stat['dossiers_incomplets'] / $stat['total_inscrits']) * 100) ?>%)</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-primary"><?= $stat['hommes'] ?></span>
                                                                    <?php if ($stat['total_inscrits'] > 0): ?>
                                                                        <small class="text-muted">(<?= round(($stat['hommes'] / $stat['total_inscrits']) * 100) ?>%)</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-danger"><?= $stat['femmes'] ?></span>
                                                                    <?php if ($stat['total_inscrits'] > 0): ?>
                                                                        <small class="text-muted">(<?= round(($stat['femmes'] / $stat['total_inscrits']) * 100) ?>%)</small>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <button class="btn btn-sm btn-info" onclick="voirDetails(<?= $stat['idpromotion'] ?>)">
                                                                        <i class="bi bi-eye"></i> Détails
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
// Fonction pour exporter les données
function exportData() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'controller/export_inscriptions_stats.php?' + params.toString();
}

// Fonction pour voir les détails d'une promotion
function voirDetails(promotionId) {
    window.location.href = `?view=etudiants/liste_etudiants&promotion=${promotionId}`;
}

// Soumission automatique du formulaire lors du changement d'année
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('anneeFilter').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});
</script>

<?php include "./views/include/footer_file.php"; ?>