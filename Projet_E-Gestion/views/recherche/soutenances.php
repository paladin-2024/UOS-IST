<?php
include "./views/include/header.php";

// Connexion à la base de données
$db = Connexion::getInstance()->getPDO();

// Fonctions utilitaires directes
function getAcademicYears($db) {
    $query = "SELECT idannee_acad, designation FROM annee_acad ORDER BY designation DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCurrentAcademicYear($db) {
    $query = "SELECT idannee_acad, designation FROM annee_acad WHERE est_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserSections($db, $userId, $anneeAcadId) {
    $query = "SELECT section_idsection FROM responsable_section 
              WHERE \"idUser\" = :userId AND annee_acad_idannee_acad = :anneeId";
    $stmt = $db->prepare($query);
    $stmt->execute(['userId' => $userId, 'anneeId' => $anneeAcadId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getAllSections($db) {
    $query = "SELECT idsection, \"designationSection\" FROM section ORDER BY \"designationSection\"";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSectionById($db, $sectionId) {
    $query = "SELECT idsection, \"designationSection\" FROM section WHERE idsection = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $sectionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fonctions statistiques directes
function getStatistiquesMemoires($db, $idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                s.idsection,
                s.\"designationSection\",
                COUNT(dm.\"idDepot\") as nb_total,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dm.\"dateDepot\") BETWEEN 1 AND 3 THEN 1 END) as t1,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dm.\"dateDepot\") BETWEEN 4 AND 6 THEN 1 END) as t2,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dm.\"dateDepot\") BETWEEN 7 AND 9 THEN 1 END) as t3,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dm.\"dateDepot\") BETWEEN 10 AND 12 THEN 1 END) as t4
            FROM 
                section s
            LEFT JOIN orientation o ON o.section_idsection = s.idsection
            LEFT JOIN specialisation sp ON sp.idorientation = o.idorientation
            LEFT JOIN sujets sj ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\" AND sj.annee_acad_idannee_acad = :anneeAcad
            LEFT JOIN depot_memoire dm ON dm.sujets_idsujets = sj.idsujets";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " WHERE s.idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= ' GROUP BY s.idsection, s."designationSection"
              ORDER BY s."designationSection"';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatistiquesRapports($db, $idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                s.idsection,
                s.\"designationSection\",
                COUNT(dr.iddepot_rapport) as nb_total,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dr.\"dateDepot\") BETWEEN 1 AND 3 THEN 1 END) as t1,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dr.\"dateDepot\") BETWEEN 4 AND 6 THEN 1 END) as t2,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dr.\"dateDepot\") BETWEEN 7 AND 9 THEN 1 END) as t3,
                COUNT(CASE WHEN EXTRACT(MONTH FROM dr.\"dateDepot\") BETWEEN 10 AND 12 THEN 1 END) as t4
            FROM 
                section s
            LEFT JOIN orientation o ON o.section_idsection = s.idsection
            LEFT JOIN promotion p ON p.orientation_idorientation = o.idorientation
            LEFT JOIN etudiant e ON e.promotion_idpromotion = p.idpromotion AND e.annee_acad_idannee_acad = :anneeAcad
            LEFT JOIN depot_rapport dr ON dr.etudiant_idetudiant = e.idetudiant";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " WHERE s.idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= ' GROUP BY s.idsection, s."designationSection"
              ORDER BY s."designationSection"';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatistiquesSoutenances($db, $idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                s.idsection,
                s.\"designationSection\",
                COUNT(st.idsoutenance) as nb_total,
                COUNT(CASE WHEN st.statut = 'Programmée' THEN 1 END) as programmees,
                COUNT(CASE WHEN st.statut = 'Terminée' THEN 1 END) as terminees,
                COUNT(CASE WHEN st.statut = 'Reportée' THEN 1 END) as reportees,
                COUNT(CASE WHEN st.statut = 'Annulée' THEN 1 END) as annulees
            FROM 
                section s
            LEFT JOIN orientation o ON o.section_idsection = s.idsection
            LEFT JOIN specialisation sp ON sp.idorientation = o.idorientation
            LEFT JOIN sujets sj ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\" AND sj.annee_acad_idannee_acad = :anneeAcad
            LEFT JOIN soutenance st ON st.sujets_idsujets = sj.idsujets";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " WHERE s.idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= ' GROUP BY s.idsection, s."designationSection"
              ORDER BY s."designationSection"';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatistiquesSujets($db, $idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                s.idsection,
                s.\"designationSection\",
                COUNT(sj.idsujets) as nb_total,
                COUNT(CASE WHEN sj.statut_validation = 'En attente' THEN 1 END) as en_attente,
                COUNT(CASE WHEN sj.statut_validation = 'Validé' THEN 1 END) as valides,
                COUNT(CASE WHEN sj.statut_validation = 'Rejeté' THEN 1 END) as rejetes,
                COUNT(CASE WHEN sj.statut_validation = 'Modifié' THEN 1 END) as sujets_modifies
            FROM 
                section s
            LEFT JOIN orientation o ON o.section_idsection = s.idsection
            LEFT JOIN specialisation sp ON sp.idorientation = o.idorientation
            LEFT JOIN sujets sj ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\" AND sj.annee_acad_idannee_acad = :anneeAcad";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " WHERE s.idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= ' GROUP BY s.idsection, s."designationSection"
              ORDER BY s."designationSection"';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatistiquesEncadrement($db, $idAnneeAcad, $idSection = null) {
    $sql = "SELECT 
                a.\"idAgent\",
                a.noms,
                COUNT(CASE WHEN sj.\"idDirecteur\" = a.\"idAgent\" THEN 1 END) as nb_sujets_diriges,
                COUNT(CASE WHEN sj.\"idEncadreur\" = a.\"idAgent\" THEN 1 END) as nb_sujets_encadres,
                COUNT(CASE WHEN j.idenseignant = a.\"idAgent\" THEN 1 END) as nb_jury
            FROM 
                agent a
            LEFT JOIN agent_section ag_s ON ag_s.\"idAgent\" = a.\"idAgent\"
            LEFT JOIN sujets sj ON (sj.\"idDirecteur\" = a.\"idAgent\" OR sj.\"idEncadreur\" = a.\"idAgent\") 
                               AND sj.annee_acad_idannee_acad = :anneeAcad
            LEFT JOIN specialisation sp ON sj.\"idSpecialisation\" = sp.\"idSpecialisation\"
            LEFT JOIN orientation o ON sp.idorientation = o.idorientation
            LEFT JOIN jury_soutenance j ON j.idenseignant = a.\"idAgent\"
            LEFT JOIN soutenance st ON st.idsoutenance = j.idsoutenance
            WHERE a.type_agent = 'Enseignant'";
    
    $params = [':anneeAcad' => $idAnneeAcad];
    
    if ($idSection) {
        $sql .= " AND ag_s.idsection = :idSection AND o.section_idsection = :idSection";
        $params[':idSection'] = $idSection;
    }
    
    $sql .= ' GROUP BY a."idAgent", a.noms
              HAVING (
                  COUNT(CASE WHEN sj."idDirecteur" = a."idAgent" THEN 1 END) > 0
                  OR COUNT(CASE WHEN sj."idEncadreur" = a."idAgent" THEN 1 END) > 0
                  OR COUNT(CASE WHEN j.idenseignant = a."idAgent" THEN 1 END) > 0
              )
              ORDER BY a.noms';
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 
$hasFullAccess = $_SESSION['idRole'] == 1; // Supposons que le rôle 1 est administrateur

// Récupération de l'année académique actuelle ou sélectionnée
$annees = getAcademicYears($db);
$selectedYear = isset($_GET['annee_acad']) && !empty($_GET['annee_acad']) 
    ? intval($_GET['annee_acad']) 
    : getCurrentAcademicYear($db)['idannee_acad'];

// Récupérer les sections dont l'utilisateur est responsable
if (!$hasFullAccess) {
    $userSections = getUserSections($db, $currentUserId, $selectedYear);
    $isResponsableSection = !empty($userSections);
}

// Récupérer toutes les sections accessibles à l'utilisateur
$sections = [];
if ($hasFullAccess) { // Si administrateur
    $sections = getAllSections($db);
    $selectedSection = isset($_GET['section']) ? intval($_GET['section']) : 0;
} else {
    // Pour les responsables de section, récupérer leurs sections autorisées
    if ($isResponsableSection) {
        foreach ($userSections as $sectionId) {
            $sectionData = getSectionById($db, $sectionId);
            if ($sectionData) {
                $sections[] = $sectionData;
            }
        }
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

// Récupérer les statistiques selon le rôle et la section sélectionnée
if ($hasFullAccess && $selectedSection == 0) {
    // Admin - toutes les sections
    $statMemoires = getStatistiquesMemoires($db, $selectedYear);
    $statRapports = getStatistiquesRapports($db, $selectedYear);
    $statSoutenances = getStatistiquesSoutenances($db, $selectedYear);
    $statSujets = getStatistiquesSujets($db, $selectedYear);
    $statEncadrement = getStatistiquesEncadrement($db, $selectedYear);
} else {
    // Responsable de section ou admin filtrant par section - filtrer par sections autorisées
    $statMemoires = [];
    $statRapports = [];
    $statSoutenances = [];
    $statSujets = [];
    $statEncadrement = [];
    
    $sectionsToQuery = $hasFullAccess ? [$selectedSection] : $userSections;
    
    foreach ($sectionsToQuery as $sectionId) {
        $memoires = getStatistiquesMemoires($db, $selectedYear, $sectionId);
        $rapports = getStatistiquesRapports($db, $selectedYear, $sectionId);
        $soutenances = getStatistiquesSoutenances($db, $selectedYear, $sectionId);
        $sujets = getStatistiquesSujets($db, $selectedYear, $sectionId);
        $encadrement = getStatistiquesEncadrement($db, $selectedYear, $sectionId);
        
        $statMemoires = array_merge($statMemoires, $memoires);
        $statRapports = array_merge($statRapports, $rapports);
        $statSoutenances = array_merge($statSoutenances, $soutenances);
        $statSujets = array_merge($statSujets, $sujets);
        $statEncadrement = array_merge($statEncadrement, $encadrement);
    }
}

// Préparer les données pour les graphiques
$sectionsLabels = [];
$memoires = [];
$rapports = [];
$soutenances = [];

if ($hasFullAccess && $selectedSection == 0) {
    // Pour l'admin qui voit toutes les sections
    foreach ($statMemoires as $stat) {
        $sectionsLabels[] = $stat['designationSection'];
        $memoires[] = $stat['nb_total'];
    }
    
    foreach ($statRapports as $stat) {
        $rapports[] = $stat['nb_total'];
    }
    
    foreach ($statSoutenances as $stat) {
        $soutenances[] = $stat['nb_total'];
    }
} else {
    // Pour un responsable de section ou admin filtrant par section
    foreach ($statMemoires as $stat) {
        $sectionsLabels[] = $stat['designationSection'];
        $memoires[] = $stat['nb_total'];
    }
    
    foreach ($statRapports as $stat) {
        $rapports[] = $stat['nb_total'];
    }
    
    foreach ($statSoutenances as $stat) {
        $soutenances[] = $stat['nb_total'];
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
                    $sectionNames[] = $section['designationSection'];
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
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= $selectedYear == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
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
                                        <option value="<?= $section['idsection'] ?>" <?= $selectedSection == $section['idsection'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($section['designationSection']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <div class="col-md-4">
                                <label for="section" class="form-label">Section</label>
                                <select name="section" id="section" class="form-select">
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?= $section['idsection'] ?>" <?= $selectedSection == $section['idsection'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($section['designationSection']) ?>
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
                                        $section = $statMem['designationSection'];
                                        $nbMemoires = $statMem['nb_total'];
                                        $nbRapports = isset($statRapports[$index]) ? $statRapports[$index]['nb_total'] : 0;
                                        $nbSoutenances = isset($statSoutenances[$index]) ? $statSoutenances[$index]['nb_total'] : 0;
                                        $nbTerminees = isset($statSoutenances[$index]) ? $statSoutenances[$index]['terminees'] : 0;
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
                                                <a href="?view=recherche/depot_soutenance&section=<?= $statMem['idsection'] ?>" class="btn btn-sm btn-primary">
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
                                        $total = $encadrement['nb_sujets_diriges'] + $encadrement['nb_sujets_encadres'] + $encadrement['nb_jury'];
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($encadrement['noms']) ?></td>
                                            <td><?= $encadrement['nb_sujets_diriges'] ?></td>
                                            <td><?= $encadrement['nb_sujets_encadres'] ?></td>
                                            <td><?= $encadrement['nb_jury'] ?></td>
                                            <td><strong><?= $total ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistiques des sujets de recherche pour une section spécifique -->
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
                                                // Agrégation des données de toutes les sections
                                                $totalSujets = 0;
                                                $totalEnAttente = 0;
                                                $totalValides = 0;
                                                $totalRejetes = 0;
                                                $totalModifies = 0;
                                                
                                                foreach ($statSujets as $stat) {
                                                    $totalSujets += $stat["nb_total"];
                                                    $totalEnAttente += $stat["en_attente"];
                                                    $totalValides += $stat["valides"];
                                                    $totalRejetes += $stat["rejetes"];
                                                    $totalModifies += $stat["sujets_modifies"];
                                                }
                                                
                                                $statuts = [
                                                    'En attente' => $totalEnAttente,
                                                    'Validés' => $totalValides,
                                                    'Rejetés' => $totalRejetes,
                                                    'Modifiés' => $totalModifies
                                                ];
                                                
                                                foreach ($statuts as $label => $value) {
                                                    $pourcentage = $totalSujets > 0 ? round(($value / $totalSujets) * 100) : 0;
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
                <?php 
                $datasets = [];
                foreach ($statMemoires as $index => $stat) {
                    $datasets[] = '{
                        label: "' . str_replace('"', '\\"', $stat["designationSection"]) . '",
                        data: [' . $stat["t1"] . ', ' . $stat["t2"] . ', ' . $stat["t3"] . ', ' . $stat["t4"] . '],
                        backgroundColor: "rgba(' . (75 + ($index * 50) % 180) . ', ' . (192 - ($index * 30) % 150) . ', ' . (192 + ($index * 40) % 60) . ', 0.6)"
                    }';
                }
                echo implode(',', $datasets);
                ?>
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
                    <?php 
                    // Calcul des totaux agrégés pour le graphique
                    $totalEnAttente = 0;
                    $totalValides = 0;
                    $totalRejetes = 0;
                    $totalModifies = 0;
                    
                    foreach ($statSujets as $stat) {
                        $totalEnAttente += $stat["en_attente"];
                        $totalValides += $stat["valides"];
                        $totalRejetes += $stat["rejetes"];
                        $totalModifies += $stat["sujets_modifies"];
                    }
                    
                    echo "$totalEnAttente, $totalValides, $totalRejetes, $totalModifies";
                    ?>
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
});</script>

<?php include "./views/include/footer.php"; ?>

