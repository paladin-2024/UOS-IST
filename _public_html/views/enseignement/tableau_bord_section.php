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

// Vérifier si l'utilisateur a le droit d'accéder à cette page
$hasFullAccess = $_SESSION['idRole'] == 1;

if (!$isResponsableSection && !$hasFullAccess) {
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

// Fonction utilitaire pour compter les sujets par section (même logique que choix_etudiant.php)
function countSujetsBySection($pdo, $sectionId, $anneeId, $status = null) {
    $params = [':anneeId' => $anneeId, ':sectionId' => $sectionId];
    
    $query = "SELECT COUNT(DISTINCT s.idsujets) as count
              FROM sujets s
              LEFT JOIN specialisation spec ON s.\"idSpecialisation\" = spec.\"idSpecialisation\"
              LEFT JOIN orientation o ON spec.idorientation = o.idorientation
              WHERE s.annee_acad_idannee_acad = :anneeId
              AND o.section_idsection = :sectionId";
    
    if ($status) {
        $query .= " AND s.statut_validation = :status";
        $params[':status'] = $status;
    }
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

// Fonction pour récupérer les statistiques globales des promotions
function getStatistiquesPromotions($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = "SELECT 
                p.idpromotion,
                p.\"designationPromotion\",
                p.cycle,
                p.est_terminale,
                o.\"designationOrientation\",
                s.\"designationSection\",
                s.idsection,
                COUNT(DISTINCT e.idetudiant) as nb_etudiants_inscrits,
                COUNT(DISTINCT CASE WHEN e.est_actif = 1 THEN e.idetudiant END) as nb_etudiants_actifs,
                (CASE 
                    WHEN p.est_terminale = 1 THEN 
                        (SELECT COUNT(DISTINCT suj.idsujets) 
                         FROM sujets suj 
                         JOIN specialisation spec ON suj.\"idSpecialisation\" = spec.\"idSpecialisation\" 
                         WHERE suj.annee_acad_idannee_acad = :anneeId 
                         AND spec.idorientation = o.idorientation 
                         AND suj.cycle = p.cycle)
                    ELSE 0 
                END) as nb_sujets_recherche,
                (CASE 
                    WHEN p.est_terminale = 1 THEN 
                        (SELECT COUNT(DISTINCT suj.idsujets) 
                         FROM sujets suj 
                         JOIN specialisation spec ON suj.\"idSpecialisation\" = spec.\"idSpecialisation\" 
                         WHERE suj.annee_acad_idannee_acad = :anneeId 
                         AND spec.idorientation = o.idorientation 
                         AND suj.cycle = p.cycle 
                         AND suj.statut_validation = 'Validé')
                    ELSE 0 
                END) as nb_sujets_valides,
                (SELECT COUNT(DISTINCT ag.\"idAgent\") 
                 FROM agent_section ags 
                 JOIN agent ag ON ags.\"idAgent\" = ag.\"idAgent\" 
                 WHERE ags.idsection = s.idsection 
                 AND ag.type_agent = 'Enseignant') as nb_enseignants
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              LEFT JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion 
                  AND e.annee_acad_idannee_acad = :anneeId
              WHERE p.annee_acad_idannee_acad = :anneeId";
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= " GROUP BY p.idpromotion, p.\"designationPromotion\", p.cycle, p.est_terminale, 
                         o.\"designationOrientation\", s.\"designationSection\", s.idsection
                ORDER BY s.\"designationSection\", p.cycle, p.\"designationPromotion\"";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les statistiques d'avancement des cours
function getAvancementCours($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = "SELECT 
                s.\"designationSection\",
                p.\"designationPromotion\",
                COUNT(DISTINCT ecue.\"idECUE\") as total_ecues,
                SUM(CASE WHEN ecue.\"CMI\" > 0 THEN ecue.\"CMI\" ELSE 0 END) as total_heures_cm_prevues,
                SUM(CASE WHEN ecue.\"TD\" > 0 THEN ecue.\"TD\" ELSE 0 END) as total_heures_td_prevues,
                SUM(CASE WHEN ecue.\"TP\" > 0 THEN ecue.\"TP\" ELSE 0 END) as total_heures_tp_prevues,
                COALESCE(SUM(CASE WHEN se.type_cours = 'CM' THEN EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut))/3600.0 ELSE 0 END), 0) as heures_cm_realisees,
                COALESCE(SUM(CASE WHEN se.type_cours = 'TD' THEN EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut))/3600.0 ELSE 0 END), 0) as heures_td_realisees,
                COALESCE(SUM(CASE WHEN se.type_cours = 'TP' THEN EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut))/3600.0 ELSE 0 END), 0) as heures_tp_realisees
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              JOIN semestre sem ON p.idpromotion = sem.promotion_idpromotion
              JOIN ue ON sem.idsemestre = ue.semestre_idsemestre
              JOIN ecue ON ue.\"idUE\" = ecue.\"UE_idUE\" AND ecue.\"estVisible\" = 1
              LEFT JOIN suivi_enseignements se ON ecue.\"idECUE\" = se.\"idECUE\" 
                  AND se.annee_acad_idannee_acad = :anneeId
              WHERE p.annee_acad_idannee_acad = :anneeId";
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= " GROUP BY s.idsection, p.idpromotion
                ORDER BY s.\"designationSection\", p.\"designationPromotion\"";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer les statistiques des frais
function getStatistiquesPaiements($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = "SELECT 
                s.\"designationSection\",
                p.\"designationPromotion\",
                COUNT(DISTINCT e.idetudiant) as nb_etudiants,
                COUNT(DISTINCT CASE 
                    WHEN sf.solde <= 0 OR sf.solde IS NULL THEN e.idetudiant 
                    ELSE NULL 
                END) as nb_etudiants_en_ordre,
                ROUND((COUNT(DISTINCT CASE 
                    WHEN sf.solde <= 0 OR sf.solde IS NULL THEN e.idetudiant 
                    ELSE NULL 
                END) * 100.0 / NULLIF(COUNT(DISTINCT e.idetudiant), 0)), 1) as pourcentage_en_ordre
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              LEFT JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion 
                  AND e.annee_acad_idannee_acad = :anneeId
              LEFT JOIN situation_financiere_etudiant sf ON e.idetudiant = sf.etudiant_id 
                  AND sf.annee_acad_id = :anneeId
              WHERE p.annee_acad_idannee_acad = :anneeId";
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= " GROUP BY s.idsection, p.idpromotion
                ORDER BY s.\"designationSection\", p.\"designationPromotion\"";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer toutes les statistiques
$statistiquesPromotions = [];
$avancementCours = [];
$statistiquesPaiements = [];

if ($isResponsableSection) {
    $statistiquesPromotions = getStatistiquesPromotions($pdo, $userSections, $currentYear['idannee_acad']);
    $avancementCours = getAvancementCours($pdo, $userSections, $currentYear['idannee_acad']);
    $statistiquesPaiements = getStatistiquesPaiements($pdo, $userSections, $currentYear['idannee_acad']);
} else {
    $statistiquesPromotions = getStatistiquesPromotions($pdo, [], $currentYear['idannee_acad']);
    $avancementCours = getAvancementCours($pdo, [], $currentYear['idannee_acad']);
    $statistiquesPaiements = getStatistiquesPaiements($pdo, [], $currentYear['idannee_acad']);
}

// Calculer les totaux globaux
$totalEtudiants = array_sum(array_column($statistiquesPromotions, 'nb_etudiants_inscrits'));
$totalEtudiantsActifs = array_sum(array_column($statistiquesPromotions, 'nb_etudiants_actifs'));

// Pour les sujets et enseignants, éviter les doublons
$sectionsUniques = [];
$totalSujets = 0;
$totalSujetsValides = 0;

foreach ($statistiquesPromotions as $promo) {
    // Enseignants : compter une seule fois par section
    $sectionsUniques[$promo['idsection']] = $promo['nb_enseignants'];
    
    // Sujets : additionner car maintenant ils sont comptés spécifiquement par promotion (orientation + cycle)
    $totalSujets += $promo['nb_sujets_recherche'];
    $totalSujetsValides += $promo['nb_sujets_valides'];
}

$totalEnseignants = array_sum($sectionsUniques);

// Calculer les pourcentages globaux
$pourcentageEtudiantsActifs = $totalEtudiants > 0 ? round(($totalEtudiantsActifs / $totalEtudiants) * 100, 1) : 0;
$pourcentageSujetsValides = $totalSujets > 0 ? round(($totalSujetsValides / $totalSujets) * 100, 1) : 0;

// Calculer l'avancement global des cours
$totalHeuresPrevues = 0;
$totalHeuresRealisees = 0;
foreach ($avancementCours as $cours) {
    $totalHeuresPrevues += ($cours['total_heures_cm_prevues'] + $cours['total_heures_td_prevues'] + $cours['total_heures_tp_prevues']);
    $totalHeuresRealisees += ($cours['heures_cm_realisees'] + $cours['heures_td_realisees'] + $cours['heures_tp_realisees']);
}
$pourcentageAvancementGlobal = $totalHeuresPrevues > 0 ? round(($totalHeuresRealisees / $totalHeuresPrevues) * 100, 1) : 0;

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>TABLEAU DE BORD - CHEF DE SECTION</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Tableau de bord</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Informations générales -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Année académique : <?= htmlspecialchars($currentYear['designation']) ?></h5>
                        <?php if ($isResponsableSection): ?>
                            <p class="text-muted">Vous êtes responsable de <?= count($userSections) ?> section(s)</p>
                        <?php else: ?>
                            <p class="text-muted">Vue globale de toutes les sections</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques globales -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Étudiants inscrits</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $totalEtudiants ?></h6>
                                <span class="text-success small pt-1 fw-bold"><?= $totalEtudiantsActifs ?></span>
                                <span class="text-muted small pt-2 ps-1">actifs (<?= $pourcentageEtudiantsActifs ?>%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">Sujets de recherche</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $totalSujets ?></h6>
                                <span class="text-success small pt-1 fw-bold"><?= $totalSujetsValides ?></span>
                                <span class="text-muted small pt-2 ps-1">validés (<?= $pourcentageSujetsValides ?>%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">Avancement cours</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $pourcentageAvancementGlobal ?>%</h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= $totalHeuresRealisees ?>h / <?= $totalHeuresPrevues ?>h
                                </span>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?= $pourcentageAvancementGlobal ?>%"
                                 aria-valuenow="<?= $pourcentageAvancementGlobal ?>" 
                                 aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">Enseignants</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $totalEnseignants ?></h6>
                                <span class="text-muted small pt-2 ps-1">Total enseignants</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tableau détaillé par promotion -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    Situation détaillée par promotion
                    <div class="float-end">
                        <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()">
                            <i class="bi bi-file-excel"></i> Exporter Excel
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="printReport()">
                            <i class="bi bi-printer"></i> Imprimer
                        </button>
                    </div>
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="promotionsTable">
                        <thead>
                            <tr>
                                <th rowspan="2">#</th>
                                <th rowspan="2">Section</th>
                                <th rowspan="2">Promotion</th>
                                <th rowspan="2">Cycle</th>
                                <th colspan="2" class="text-center">Étudiants</th>
                                <th colspan="2" class="text-center">Sujets recherche</th>
                                <th rowspan="2">Enseignants</th>
                                <th rowspan="2">Actions</th>
                            </tr>
                            <tr>
                                <th>Inscrits</th>
                                <th>Actifs</th>
                                <th>Total</th>
                                <th>Validés</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 1;
                            foreach ($statistiquesPromotions as $promo): 
                                $pourcentageActifs = $promo['nb_etudiants_inscrits'] > 0 ? 
                                    round(($promo['nb_etudiants_actifs'] / $promo['nb_etudiants_inscrits']) * 100, 1) : 0;
                                $pourcentageSujets = $promo['nb_sujets_recherche'] > 0 ? 
                                    round(($promo['nb_sujets_valides'] / $promo['nb_sujets_recherche']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?= $index++ ?></td>
                                <td><?= htmlspecialchars($promo['designationSection']) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($promo['designationPromotion']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($promo['designationOrientation']) ?></small>
                                    <?php if ($promo['est_terminale']): ?>
                                        <span class="badge bg-warning">Terminale</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= getCycleBadgeColor($promo['cycle']) ?>">
                                        <?= htmlspecialchars($promo['cycle']) ?>
                                    </span>
                                </td>
                                <td class="text-center"><?= $promo['nb_etudiants_inscrits'] ?></td>
                                <td class="text-center">
                                    <?= $promo['nb_etudiants_actifs'] ?>
                                    <small class="text-muted">(<?= $pourcentageActifs ?>%)</small>
                                </td>
                                <td class="text-center"><?= $promo['nb_sujets_recherche'] ?></td>
                                <td class="text-center">
                                    <?= $promo['nb_sujets_valides'] ?>
                                    <small class="text-muted">(<?= $pourcentageSujets ?>%)</small>
                                </td>
                                <td class="text-center"><?= $promo['nb_enseignants'] ?></td>
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <a href="index?view=enseignement/suivi_global_enseignements&promotion=<?= $promo['idpromotion'] ?>" 
                                           class="btn btn-sm btn-outline-primary" title="Voir détails cours">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="index?view=etudiant/liste_etudiants&promotion=<?= $promo['idpromotion'] ?>" 
                                           class="btn btn-sm btn-outline-info" title="Liste étudiants">
                                            <i class="bi bi-people"></i>
                                        </a>
                                        <a href="index?view=recherche/sujets&promotion=<?= $promo['idpromotion'] ?>" 
                                           class="btn btn-sm btn-outline-success" title="Sujets recherche">
                                            <i class="bi bi-journal-text"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Avancement des cours par promotion -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Avancement des enseignements par promotion</h5>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Promotion</th>
                                <th>Total ECUE</th>
                                <th>CM (Prévu/Réalisé)</th>
                                <th>TD (Prévu/Réalisé)</th>
                                <th>TP (Prévu/Réalisé)</th>
                                <th>Avancement global</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avancementCours as $cours): 
                                $totalPrevu = $cours['total_heures_cm_prevues'] + $cours['total_heures_td_prevues'] + $cours['total_heures_tp_prevues'];
                                $totalRealise = $cours['heures_cm_realisees'] + $cours['heures_td_realisees'] + $cours['heures_tp_realisees'];
                                $pourcentageGlobal = $totalPrevu > 0 ? round(($totalRealise / $totalPrevu) * 100, 1) : 0;
                                
                                $pourcentageCM = $cours['total_heures_cm_prevues'] > 0 ? 
                                    round(($cours['heures_cm_realisees'] / $cours['total_heures_cm_prevues']) * 100, 1) : 0;
                                $pourcentageTD = $cours['total_heures_td_prevues'] > 0 ? 
                                    round(($cours['heures_td_realisees'] / $cours['total_heures_td_prevues']) * 100, 1) : 0;
                                $pourcentageTP = $cours['total_heures_tp_prevues'] > 0 ? 
                                    round(($cours['heures_tp_realisees'] / $cours['total_heures_tp_prevues']) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($cours['designationSection']) ?></td>
                                <td><?= htmlspecialchars($cours['designationPromotion']) ?></td>
                                <td class="text-center"><?= $cours['total_ecues'] ?></td>
                                <td class="text-center">
                                    <?= $cours['total_heures_cm_prevues'] ?>h / <?= $cours['heures_cm_realisees'] ?>h
                                    <br><small class="text-muted">(<?= $pourcentageCM ?>%)</small>
                                </td>
                                <td class="text-center">
                                    <?= $cours['total_heures_td_prevues'] ?>h / <?= $cours['heures_td_realisees'] ?>h
                                    <br><small class="text-muted">(<?= $pourcentageTD ?>%)</small>
                                </td>
                                <td class="text-center">
                                    <?= $cours['total_heures_tp_prevues'] ?>h / <?= $cours['heures_tp_realisees'] ?>h
                                    <br><small class="text-muted">(<?= $pourcentageTP ?>%)</small>
                                </td>
                                <td class="text-center">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?= getProgressColor($pourcentageGlobal) ?>" 
                                             role="progressbar" 
                                             style="width: <?= $pourcentageGlobal ?>%"
                                             aria-valuenow="<?= $pourcentageGlobal ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?= $pourcentageGlobal ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Situation des paiements -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Situation des paiements par promotion</h5>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Promotion</th>
                                <th>Étudiants inscrits</th>
                                <th>Étudiants en ordre</th>
                                <th>Pourcentage en ordre</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statistiquesPaiements as $paiement): ?>
                            <tr>
                                <td><?= htmlspecialchars($paiement['designationSection']) ?></td>
                                <td><?= htmlspecialchars($paiement['designationPromotion']) ?></td>
                                <td class="text-center"><?= $paiement['nb_etudiants'] ?></td>
                                <td class="text-center"><?= $paiement['nb_etudiants_en_ordre'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= getPaymentStatusColor($paiement['pourcentage_en_ordre']) ?>">
                                        <?= $paiement['pourcentage_en_ordre'] ?>%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($paiement['pourcentage_en_ordre'] >= 80): ?>
                                        <i class="bi bi-check-circle-fill text-success" title="Excellent"></i>
                                    <?php elseif ($paiement['pourcentage_en_ordre'] >= 60): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-warning" title="Moyen"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger" title="Critique"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Graphiques -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répartition des étudiants par cycle</h5>
                        <canvas id="chartCycles" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Avancement global des enseignements</h5>
                        <canvas id="chartAvancement" style="max-height: 400px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
// Fonctions utilitaires
function getCycleBadgeColor($cycle) {
    switch ($cycle) {
        case 'Premier': return 'primary';
        case 'Deuxieme': return 'success';
        case 'Troisieme': return 'warning';
        default: return 'secondary';
    }
}

function getProgressColor($percentage) {
    if ($percentage >= 80) return 'success';
    if ($percentage >= 60) return 'primary';
    if ($percentage >= 40) return 'warning';
    return 'danger';
}

function getPaymentStatusColor($percentage) {
    if ($percentage >= 80) return 'success';
    if ($percentage >= 60) return 'warning';
    return 'danger';
}

// Préparer les données pour les graphiques
$cycleData = [];
$avancementData = [];

foreach ($statistiquesPromotions as $promo) {
    if (!isset($cycleData[$promo['cycle']])) {
        $cycleData[$promo['cycle']] = 0;
    }
    $cycleData[$promo['cycle']] += $promo['nb_etudiants_inscrits'];
}

foreach ($avancementCours as $cours) {
    $totalPrevu = $cours['total_heures_cm_prevues'] + $cours['total_heures_td_prevues'] + $cours['total_heures_tp_prevues'];
    $totalRealise = $cours['heures_cm_realisees'] + $cours['heures_td_realisees'] + $cours['heures_tp_realisees'];
    
    $avancementData[] = [
        'promotion' => $cours['designationPromotion'],
        'prevu' => $totalPrevu,
        'realise' => $totalRealise
    ];
}
?>

<script>
// Exporter vers Excel
function exportToExcel() {
    const params = new URLSearchParams();
    params.set('export', 'excel');
    params.set('type', 'tableau_bord_section');
    window.location.href = 'controller/export_tableau_bord.php?' + params.toString();
}

// Imprimer le rapport
function printReport() {
    window.print();
}

// Graphiques avec Chart.js
document.addEventListener('DOMContentLoaded', function() {
    // Graphique par cycle
    const ctx1 = document.getElementById('chartCycles').getContext('2d');
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($cycleData)) ?>,
            datasets: [{
                label: 'Nombre d\'étudiants',
                data: <?= json_encode(array_values($cycleData)) ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 206, 86, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Graphique d'avancement
    const ctx2 = document.getElementById('chartAvancement').getContext('2d');
    const avancementData = <?= json_encode($avancementData) ?>;
    
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: avancementData.map(item => item.promotion),
            datasets: [{
                label: 'Heures prévues',
                data: avancementData.map(item => item.prevu),
                backgroundColor: 'rgba(201, 203, 207, 0.5)',
                borderColor: 'rgba(201, 203, 207, 1)',
                borderWidth: 1
            }, {
                label: 'Heures réalisées',
                data: avancementData.map(item => item.realise),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Heures'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<style>
@media print {
    .btn, .breadcrumb, .pagetitle nav {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
    }
    
    .table {
        font-size: 12px;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
    }
}

.card-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.info-card.sales-card .card-icon {
    color: #4154f1;
    background: #f6f6fe;
}

.info-card.revenue-card .card-icon {
    color: #2eca6a;
    background: #e0f8e9;
}

.info-card.customers-card .card-icon {
    color: #ff771d;
    background: #ffecdf;
}

.progress {
    background-color: #e9ecef;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}
</style>

<?php include "./views/include/footer.php"; ?>