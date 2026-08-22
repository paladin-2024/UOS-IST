<?php
include "./views/include/header.php";

// Initialiser la connexion
$connexion = Connexion::getInstance()->getPDO();

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 
$hasFullAccess = $_SESSION['idRole'] == 1; // Supposons que le rôle 1 est administrateur

// Récupération des années académiques
$queryAnnees = "SELECT idannee_acad, designation FROM annee_acad ORDER BY dateCreation DESC";
$stmtAnnees = $connexion->prepare($queryAnnees);
$stmtAnnees->execute();
$annees = $stmtAnnees->fetchAll(PDO::FETCH_ASSOC);

// Déterminer l'année sélectionnée
$selectedYear = isset($_GET['annee_acad']) && !empty($_GET['annee_acad']) 
    ? intval($_GET['annee_acad']) 
    : (!empty($annees) ? $annees[0]['idannee_acad'] : 0);

// Récupérer les sections dont l'utilisateur est responsable
if (!$hasFullAccess) {
    $query = "SELECT section_idsection 
              FROM responsable_section 
              WHERE idUser = :userId 
              AND annee_acad_idannee_acad = :anneeId";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':userId', $currentUserId);
    $stmt->bindParam(':anneeId', $selectedYear);
    $stmt->execute();
    $userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $isResponsableSection = !empty($userSections);
}

// Récupérer toutes les sections accessibles à l'utilisateur
$sections = [];
if ($hasFullAccess) { // Si administrateur
    $querySections = "SELECT idsection, designationSection FROM section ORDER BY designationSection";
    $stmtSections = $connexion->prepare($querySections);
    $stmtSections->execute();
    $sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);
    $selectedSection = isset($_GET['section']) ? intval($_GET['section']) : 0;
} else {
    // Pour les responsables de section, récupérer leurs sections autorisées
    if ($isResponsableSection) {
        $placeholders = implode(',', array_fill(0, count($userSections), '?'));
        $querySections = "SELECT idsection, designationSection FROM section WHERE idsection IN ($placeholders) ORDER BY designationSection";
        $stmtSections = $connexion->prepare($querySections);
        $stmtSections->execute($userSections);
        $sections = $stmtSections->fetchAll(PDO::FETCH_ASSOC);
        
        // Si une section spécifique est sélectionnée, vérifier qu'elle est autorisée
        $requestedSection = isset($_GET['section']) ? intval($_GET['section']) : 0;
        if ($requestedSection > 0 && in_array($requestedSection, $userSections)) {
            $selectedSection = $requestedSection;
        } else {
            // Sélectionner la première section disponible
            $selectedSection = count($sections) > 0 ? $sections[0]['idsection'] : 0;
        }
    } else {
        // Utilisateur sans accès aux sections - rediriger
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

// Fonctions pour récupérer les statistiques avec requêtes directes

// Statistiques des mémoires
function getStatistiquesMemoires($connexion, $selectedYear, $selectedSection = 0, $userSections = []) {
    $params = [$selectedYear];
    
    $query = "SELECT 
                sec.idsection,
                sec.designationSection,
                COUNT(*) as nb_total,
                SUM(CASE WHEN QUARTER(dm.dateDepot) = 1 THEN 1 ELSE 0 END) as t1,
                SUM(CASE WHEN QUARTER(dm.dateDepot) = 2 THEN 1 ELSE 0 END) as t2,
                SUM(CASE WHEN QUARTER(dm.dateDepot) = 3 THEN 1 ELSE 0 END) as t3,
                SUM(CASE WHEN QUARTER(dm.dateDepot) = 4 THEN 1 ELSE 0 END) as t4
              FROM depot_memoire dm
              INNER JOIN sujets s ON dm.sujets_idsujets = s.idsujets
              INNER JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              INNER JOIN orientation o ON spec.idorientation = o.idorientation
              INNER JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?";
    
    // Filtrer par section si spécifiée
    if ($selectedSection > 0) {
        $query .= " AND sec.idsection = ?";
        $params[] = $selectedSection;
    } elseif (!empty($userSections)) {
        $placeholders = implode(',', array_fill(0, count($userSections), '?'));
        $query .= " AND sec.idsection IN ($placeholders)";
        $params = array_merge($params, $userSections);
    }
    
    $query .= " GROUP BY sec.idsection, sec.designationSection ORDER BY sec.designationSection";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistiques des rapports
function getStatistiquesRapports($connexion, $selectedYear, $selectedSection = 0, $userSections = []) {
    $params = [$selectedYear];
    
    $query = "SELECT 
                sec.idsection,
                sec.designationSection,
                COUNT(*) as nb_total
              FROM depot_rapport dr
              INNER JOIN etudiant e ON dr.etudiant_idetudiant = e.idetudiant
              INNER JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
              INNER JOIN orientation o ON p.orientation_idorientation = o.idorientation
              INNER JOIN section sec ON o.section_idsection = sec.idsection
              WHERE e.annee_acad_idannee_acad = ?";
    
    // Filtrer par section si spécifiée
    if ($selectedSection > 0) {
        $query .= " AND sec.idsection = ?";
        $params[] = $selectedSection;
    } elseif (!empty($userSections)) {
        $placeholders = implode(',', array_fill(0, count($userSections), '?'));
        $query .= " AND sec.idsection IN ($placeholders)";
        $params = array_merge($params, $userSections);
    }
    
    $query .= " GROUP BY sec.idsection, sec.designationSection ORDER BY sec.designationSection";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistiques des soutenances
function getStatistiquesSoutenances($connexion, $selectedYear, $selectedSection = 0, $userSections = []) {
    $params = [$selectedYear];
    
    $query = "SELECT 
                sec.idsection,
                sec.designationSection,
                COUNT(*) as nb_total,
                SUM(CASE WHEN sout.statut = 'Programmée' THEN 1 ELSE 0 END) as programmees,
                SUM(CASE WHEN sout.statut = 'Terminée' THEN 1 ELSE 0 END) as terminees,
                SUM(CASE WHEN sout.statut = 'Reportée' THEN 1 ELSE 0 END) as reportees,
                SUM(CASE WHEN sout.statut = 'Annulée' THEN 1 ELSE 0 END) as annulees
              FROM soutenance sout
              INNER JOIN sujets s ON sout.sujets_idsujets = s.idsujets
              INNER JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              INNER JOIN orientation o ON spec.idorientation = o.idorientation
              INNER JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?";
    
    // Filtrer par section si spécifiée
    if ($selectedSection > 0) {
        $query .= " AND sec.idsection = ?";
        $params[] = $selectedSection;
    } elseif (!empty($userSections)) {
        $placeholders = implode(',', array_fill(0, count($userSections), '?'));
        $query .= " AND sec.idsection IN ($placeholders)";
        $params = array_merge($params, $userSections);
    }
    
    $query .= " GROUP BY sec.idsection, sec.designationSection ORDER BY sec.designationSection";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistiques des sujets
function getStatistiquesSujets($connexion, $selectedYear, $selectedSection = 0, $userSections = []) {
    $params = [$selectedYear];
    
    $query = "SELECT 
                sec.idsection,
                sec.designationSection,
                COUNT(*) as nb_total,
                SUM(CASE WHEN s.statut_validation = 'En attente' THEN 1 ELSE 0 END) as en_attente,
                SUM(CASE WHEN s.statut_validation = 'Validé' THEN 1 ELSE 0 END) as valides,
                SUM(CASE WHEN s.statut_validation = 'Rejeté' THEN 1 ELSE 0 END) as rejetes,
                SUM(CASE WHEN s.statut_validation = 'Modifié' THEN 1 ELSE 0 END) as sujets_modifies
              FROM sujets s
              INNER JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              INNER JOIN orientation o ON spec.idorientation = o.idorientation
              INNER JOIN section sec ON o.section_idsection = sec.idsection
              WHERE s.annee_acad_idannee_acad = ?";
    
    // Filtrer par section si spécifiée
    if ($selectedSection > 0) {
        $query .= " AND sec.idsection = ?";
        $params[] = $selectedSection;
    } elseif (!empty($userSections)) {
        $placeholders = implode(',', array_fill(0, count($userSections), '?'));
        $query .= " AND sec.idsection IN ($placeholders)";
        $params = array_merge($params, $userSections);
    }
    
    $query .= " GROUP BY sec.idsection, sec.designationSection ORDER BY sec.designationSection";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistiques d'encadrement
function getStatistiquesEncadrement($connexion, $selectedYear, $selectedSection = 0, $userSections = []) {
    $params = [$selectedYear];
    
    $query = "SELECT 
                a.idAgent,
                a.noms,
                SUM(CASE WHEN s.idDirecteur = a.idAgent THEN 1 ELSE 0 END) as nb_sujets_diriges,
                SUM(CASE WHEN s.idEncadreur = a.idAgent THEN 1 ELSE 0 END) as nb_sujets_encadres,
                0 as nb_jury
              FROM agent a
              LEFT JOIN sujets s ON (s.idDirecteur = a.idAgent OR s.idEncadreur = a.idAgent) AND s.annee_acad_idannee_acad = ?
              LEFT JOIN specialisation spec ON s.idSpecialisation = spec.idSpecialisation
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              LEFT JOIN section sec ON o.section_idsection = sec.idsection
              WHERE a.type_agent = 'Enseignant'";
    
    // Filtrer par section si spécifiée
    if ($selectedSection > 0) {
        $query .= " AND (sec.idsection = ? OR sec.idsection IS NULL)";
        $params[] = $selectedSection;
    } elseif (!empty($userSections)) {
        $placeholders = implode(',', array_fill(0, count($userSections), '?'));
        $query .= " AND (sec.idsection IN ($placeholders) OR sec.idsection IS NULL)";
        $params = array_merge($params, $userSections);
    }
    
    $query .= " GROUP BY a.idAgent, a.noms 
                HAVING (nb_sujets_diriges > 0 OR nb_sujets_encadres > 0)
                ORDER BY a.noms";
    
    $stmt = $connexion->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les statistiques selon le rôle et la section sélectionnée
$sectionsFilter = $isResponsableSection ? $userSections : [];

$statMemoires = $hasFullAccess && $selectedSection == 0 
    ? getStatistiquesMemoires($connexion, $selectedYear) 
    : getStatistiquesMemoires($connexion, $selectedYear, $selectedSection, $sectionsFilter);
    
$statRapports = $hasFullAccess && $selectedSection == 0 
    ? getStatistiquesRapports($connexion, $selectedYear) 
    : getStatistiquesRapports($connexion, $selectedYear, $selectedSection, $sectionsFilter);
    
$statSoutenances = $hasFullAccess && $selectedSection == 0 
    ? getStatistiquesSoutenances($connexion, $selectedYear) 
    : getStatistiquesSoutenances($connexion, $selectedYear, $selectedSection, $sectionsFilter);
    
$statSujets = $hasFullAccess && $selectedSection == 0 
    ? getStatistiquesSujets($connexion, $selectedYear) 
    : getStatistiquesSujets($connexion, $selectedYear, $selectedSection, $sectionsFilter);

$statEncadrement = $hasFullAccess && $selectedSection == 0 
    ? getStatistiquesEncadrement($connexion, $selectedYear) 
    : getStatistiquesEncadrement($connexion, $selectedYear, $selectedSection, $sectionsFilter);

// Préparer les données pour les graphiques
$sectionsLabels = [];
$memoires = [];
$rapports = [];
$soutenances = [];

if ($hasFullAccess && $selectedSection == 0) {
    // Pour l'admin qui voit toutes les sections
    foreach ($statMemoires as $stat) {
        $sectionsLabels[] = $stat["designationSection"];
        $memoires[] = $stat["nb_total"];
    }
    
    foreach ($statRapports as $stat) {
        $rapports[] = $stat["nb_total"];
    }
    
    foreach ($statSoutenances as $stat) {
        $soutenances[] = $stat["nb_total"];
    }
} else {
    // Pour un responsable de section ou admin filtrant par section
    if (!empty($statMemoires)) {
        $sectionsLabels[] = $statMemoires[0]["designationSection"];
        $memoires[] = $statMemoires[0]["nb_total"];
    }
    
    if (!empty($statRapports)) {
        $rapports[] = $statRapports[0]["nb_total"];
    }
    
    if (!empty($statSoutenances)) {
        $soutenances[] = $statSoutenances[0]["nb_total"];
    }
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>TABLEAU DE BORD - DÉPÔTS ET SOUTENANCES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item">Recherche</li>
                <li class="breadcrumb-item active">Tableau de bord</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Informations sur les sections gérées -->
        <?php if ($isResponsableSection): ?>
        <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle me-2"></i>
            Vous visualisez uniquement les <strong>données de soutenances et de recherche</strong> des sections où vous avez des responsabilités.
            <?php if (count($userSections) > 0): ?>
                <?php
                // Récupérer les noms des sections
                $sectionNames = [];
                foreach ($sections as $section) {
                    $sectionNames[] = $section["designationSection"];
                }
                ?>
                <strong>Vos sections:</strong> <?= implode(', ', $sectionNames) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3 align-items-center mb-3">
                            <input type="hidden" name="view" value="recherche/soutenances">
                            
                            <div class="col-md-4">
                            <label for="annee_acad" class="form-label">Année académique</label>
                                <select name="annee_acad" id="annee_acad" class="form-select">
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee["idannee_acad"] ?>" <?= $selectedYear == $annee["idannee_acad"] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee["designation"]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($hasFullAccess): ?> <!-- Option Section seulement pour admin -->
                            <div class="col-md-4">
                                <label for="section" class="form-label">Section</label>
                                <select name="section" id="section" class="form-select">
                                    <option value="0">Toutes les sections</option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?= $section["idsection"] ?>" <?= $selectedSection == $section["idsection"] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($section["designationSection"]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <div class="col-md-4">
                                <label for="section" class="form-label">Section</label>
                                <select name="section" id="section" class="form-select">
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?= $section["idsection"] ?>" <?= $selectedSection == $section["idsection"] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($section["designationSection"]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                                <div class="dropdown ms-2">
                                    <button class="btn btn-success dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-file-excel"></i> Exporter
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                                        <li>
                                            <a class="dropdown-item" href="controller/export_soutenance_controller.php?type=eligible&section=<?= $selectedSection ?>&annee=<?= $selectedYear ?>">
                                                Étudiants éligibles à la soutenance
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="controller/export_soutenance_controller.php?type=litige&section=<?= $selectedSection ?>&annee=<?= $selectedYear ?>">
                                                Étudiants avec litiges de frais
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cartes résumé -->
        <div class="row">
            <!-- Carte mémoires -->
            <div class="col-xxl-4 col-md-4">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Mémoires <span>| <?= $hasFullAccess && $selectedSection == 0 ? 'Toutes sections' : 'Section sélectionnée' ?></span></h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= array_sum($memoires) ?></h6>
                                <span class="text-success small pt-1 fw-bold">Dépôts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carte rapports -->
            <div class="col-xxl-4 col-md-4">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Rapports <span>| <?= $hasFullAccess && $selectedSection == 0 ? 'Toutes sections' : 'Section sélectionnée' ?></span></h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= array_sum($rapports) ?></h6>
                                <span class="text-success small pt-1 fw-bold">Dépôts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carte soutenances -->
            <div class="col-xxl-4 col-md-4">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Soutenances <span>| <?= $hasFullAccess && $selectedSection == 0 ? 'Toutes sections' : 'Section sélectionnée' ?></span></h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= array_sum($soutenances) ?></h6>
                                <span class="text-success small pt-1 fw-bold">Programmées</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques et tableaux détaillés -->
        <div class="row">
            <!-- Graphique statistiques des mémoires par trimestre -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Dépôts de mémoires par trimestre</h5>
                        <canvas id="memoiresChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>

            <!-- Graphique statistiques des soutenances par statut -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statut des soutenances</h5>
                        <canvas id="soutenancesChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableaux de statistiques détaillées -->
        <div class="row">
            <?php if ($hasFullAccess && $selectedSection == 0): ?>
            <!-- Tableau détaillé pour l'admin (toutes les sections) -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques détaillées par section</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Section</th>
                                        <th>Mémoires</th>
                                        <th>Rapports</th>
                                        <th>Soutenances</th>
                                        <th>Soutenances terminées</th>
                                        <th>Taux d'achèvement</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($statMemoires as $index => $statMem): ?>
                                        <?php 
                                        $section = $statMem["designationSection"];
                                        $nbMemoires = $statMem["nb_total"];
                                        $nbRapports = isset($statRapports[$index]) ? $statRapports[$index]["nb_total"] : 0;
                                        $nbSoutenances = isset($statSoutenances[$index]) ? $statSoutenances[$index]["nb_total"] : 0;
                                        $nbTerminees = isset($statSoutenances[$index]) ? $statSoutenances[$index]["terminees"] : 0;
                                        $tauxAchevement = $nbSoutenances > 0 ? round(($nbTerminees / $nbSoutenances) * 100) : 0;
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($section) ?></td>
                                            <td><?= $nbMemoires ?></td>
                                            <td><?= $nbRapports ?></td>
                                            <td><?= $nbSoutenances ?></td>
                                            <td><?= $nbTerminees ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $tauxAchevement ?>%" 
                                                         aria-valuenow="<?= $tauxAchevement ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?= $tauxAchevement ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="?view=recherche/depot_soutenance&section=<?= $statMem["idsection"] ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-eye"></i> Détails
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Statistiques d'encadrement pour les responsables de section ou admin filtrant par section -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques d'encadrement</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Enseignant</th>
                                        <th>Sujets dirigés</th>
                                        <th>Sujets encadrés</th>
                                        <th>Participations au jury</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($statEncadrement as $encadrement): ?>
                                        <?php 
                                        $total = $encadrement["nb_sujets_diriges"] + $encadrement["nb_sujets_encadres"] + $encadrement["nb_jury"];
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($encadrement["noms"]) ?></td>
                                            <td><?= $encadrement["nb_sujets_diriges"] ?></td>
                                            <td><?= $encadrement["nb_sujets_encadres"] ?></td>
                                            <td><?= $encadrement["nb_jury"] ?></td>
                                            <td><strong><?= $total ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques des sujets de recherche pour les responsables de section ou admin filtrant par section -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statistiques des sujets de recherche</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <canvas id="sujetsChart" style="max-height: 300px;"></canvas>
                            </div>
                            <div class="col-md-6">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Statut</th>
                                                <th>Nombre</th>
                                                <th>Pourcentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if (!empty($statSujets)) {
                                                $stat = $statSujets[0];
                                                $total = $stat["nb_total"];
                                                $statuts = [
                                                    'En attente' => $stat["en_attente"],
                                                    'Validés' => $stat["valides"],
                                                    'Rejetés' => $stat["rejetes"],
                                                    'Modifiés' => $stat["sujets_modifies"]
                                                ];
                                                
                                                foreach ($statuts as $label => $value) {
                                                    $pourcentage = $total > 0 ? round(($value / $total) * 100) : 0;
                                                    echo '<tr>';
                                                    echo '<td>' . $label . '</td>';
                                                    echo '<td>' . $value . '</td>';
                                                    echo '<td>' . $pourcentage . '%</td>';
                                                    echo '</tr>';
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- Scripts pour les graphiques (Chart.js) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuration des graphiques
    
    // Graphique des mémoires par trimestre
    <?php if (!empty($statMemoires)): ?>
    var ctxMemoires = document.getElementById('memoiresChart').getContext('2d');
    var memoiresChart = new Chart(ctxMemoires, {
        type: 'bar',
        data: {
            labels: ['T1 (Jan-Mar)', 'T2 (Avr-Jun)', 'T3 (Jul-Sep)', 'T4 (Oct-Dec)'],
            datasets: [
                <?php if ($hasFullAccess && $selectedSection == 0): ?>
                    <?php foreach ($statMemoires as $index => $stat): ?>
                    {
                        label: '<?= str_replace("\'", "\\'", $stat["designationSection"]) ?>',
                        data: [<?= $stat["t1"] ?>, <?= $stat["t2"] ?>, <?= $stat["t3"] ?>, <?= $stat["t4"] ?>],
                        backgroundColor: 'rgba(<?= 75 + ($index * 50) % 180 ?>, <?= 192 - ($index * 30) % 150 ?>, <?= 192 + ($index * 40) % 60 ?>, 0.6)'
                    },
                    <?php endforeach; ?>
                <?php else: ?>
                    {
                        label: 'Mémoires déposés',
                        data: [
                            <?= !empty($statMemoires) ? $statMemoires[0]["t1"] : 0 ?>, 
                            <?= !empty($statMemoires) ? $statMemoires[0]["t2"] : 0 ?>, 
                            <?= !empty($statMemoires) ? $statMemoires[0]["t3"] : 0 ?>, 
                            <?= !empty($statMemoires) ? $statMemoires[0]["t4"] : 0 ?>
                        ],
                        backgroundColor: 'rgba(75, 192, 192, 0.6)'
                    }
                <?php endif; ?>
            ]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    precision: 0
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
    <?php endif; ?>
    
    // Graphique des soutenances par statut
    <?php if (!empty($statSoutenances)): ?>
    var ctxSoutenances = document.getElementById('soutenancesChart').getContext('2d');
    var soutenancesChart = new Chart(ctxSoutenances, {
        type: 'pie',
        data: {
            labels: ['Programmées', 'Terminées', 'Reportées', 'Annulées'],
            datasets: [{
                data: [
                    <?php 
                    if ($hasFullAccess && $selectedSection == 0) {
                        $programmees = $reportees = $terminees = $annulees = 0;
                        foreach ($statSoutenances as $stat) {
                            $programmees += $stat["programmees"];
                            $terminees += $stat["terminees"];
                            $reportees += $stat["reportees"];
                            $annulees += $stat["annulees"];
                        }
                        echo "$programmees, $terminees, $reportees, $annulees";
                    } else {
                        if (!empty($statSoutenances)) {
                            echo $statSoutenances[0]["programmees"] . ', ' . 
                                 $statSoutenances[0]["terminees"] . ', ' . 
                                 $statSoutenances[0]["reportees"] . ', ' . 
                                 $statSoutenances[0]["annulees"];
                        } else {
                            echo "0, 0, 0, 0";
                        }
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Graphique des sujets par statut (pour section spécifique)
    <?php if (!($hasFullAccess && $selectedSection == 0) && !empty($statSujets)): ?>
    var ctxSujets = document.getElementById('sujetsChart').getContext('2d');
    var sujetsChart = new Chart(ctxSujets, {
        type: 'doughnut',
        data: {
            labels: ['En attente', 'Validés', 'Rejetés', 'Modifiés'],
            datasets: [{
                data: [
                    <?= $statSujets[0]["en_attente"] ?>, 
                    <?= $statSujets[0]["valides"] ?>, 
                    <?= $statSujets[0]["rejetes"] ?>, 
                    <?= $statSujets[0]["sujets_modifies"] ?>
                ],
                backgroundColor: [
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    // Auto-submit du formulaire lors du changement de section ou d'année académique
    document.getElementById('annee_acad').addEventListener('change', function() {
        this.form.submit();
    });
    
    if (document.getElementById('section')) {
        document.getElementById('section').addEventListener('change', function() {
            this.form.submit();
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>
