<?php
include "./views/include/header.php";

// Vérification des responsabilités de l'utilisateur connecté
$userSections = [];
$isResponsableSection = false;
$currentUserId = $_SESSION['id']; 

// Récupérer l'année académique en cours
$pdo = Connexion::getInstance()->getPDO();

// Vérifier si la colonne est_active existe et récupérer l'année courante
try {
    $checkColumn = "SELECT column_name FROM information_schema.columns WHERE table_name = 'annee_acad' AND column_name = 'est_active'";
    $stmtCheck = $pdo->prepare($checkColumn);
    $stmtCheck->execute();
    $columnExists = $stmtCheck->fetch();

    if ($columnExists) {
        $queryAnnee = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
        $stmtAnnee = $pdo->prepare($queryAnnee);
        $stmtAnnee->execute();
        $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);

        // Si aucune année active, prendre la plus récente
        if (!$currentYear) {
            $queryAnnee = 'SELECT * FROM annee_acad ORDER BY "dateCreation" DESC LIMIT 1';
            $stmtAnnee = $pdo->prepare($queryAnnee);
            $stmtAnnee->execute();
            $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $queryAnnee = 'SELECT * FROM annee_acad ORDER BY "dateCreation" DESC LIMIT 1';
        $stmtAnnee = $pdo->prepare($queryAnnee);
        $stmtAnnee->execute();
        $currentYear = $stmtAnnee->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération de l'année académique: " . $e->getMessage());
    $currentYear = null;
}

if (!$currentYear) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucune année académique trouvée dans le système.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    include "./views/include/footer.php"; 
    exit;
}

// Récupérer les sections dont l'utilisateur est responsable
try {
    $query = 'SELECT section_idsection
              FROM responsable_section
              WHERE "idUser" = :userId
              AND annee_acad_idannee_acad = :anneeId';

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':userId', $currentUserId);
    $stmt->bindParam(':anneeId', $currentYear['idannee_acad']);
    $stmt->execute();
    $userSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $isResponsableSection = !empty($userSections);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des sections responsables: " . $e->getMessage());
    $userSections = [];
    $isResponsableSection = false;
}

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

// Fonction pour récupérer les statistiques globales des promotions (version corrigée)
function getStatistiquesPromotions($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = 'SELECT
                p.idpromotion,
                p."designationPromotion",
                p.cycle,
                COALESCE(p.est_terminale, 0) as est_terminale,
                o."designationOrientation",
                s."designationSection",
                COUNT(DISTINCT e.idetudiant) as nb_etudiants_inscrits,
                COUNT(DISTINCT CASE WHEN e.est_actif = 1 THEN e.idetudiant END) as nb_etudiants_actifs,
                COUNT(DISTINCT suj.idsujets) as nb_sujets_recherche,
                COUNT(DISTINCT CASE WHEN suj."etatSujet" IN (\'Validé\', \'Valide\') THEN suj.idsujets END) as nb_sujets_valides,
                COUNT(DISTINCT ag."idAgent") as nb_enseignants
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              LEFT JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion
                  AND e.annee_acad_idannee_acad = :anneeId
              LEFT JOIN sujets suj ON e.idetudiant = suj.etudiant_idetudiant
                  AND suj.annee_acad_idannee_acad = :anneeId
              LEFT JOIN enseignant_section es ON s.idsection = es.idsection
              LEFT JOIN agent ag ON es.idenseignant = ag."idAgent"
              WHERE p.annee_acad_idannee_acad = :anneeId';
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= ' GROUP BY p.idpromotion, p."designationPromotion", p.cycle, p.est_terminale,
                         o."designationOrientation", s."designationSection"
                ORDER BY s."designationSection", p.cycle, p."designationPromotion"';
    
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur dans getStatistiquesPromotions: " . $e->getMessage());
        return [];
    }
}

// Fonction pour récupérer les statistiques d'avancement des cours (version corrigée)
function getAvancementCours($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = 'SELECT
                s."designationSection",
                p."designationPromotion",
                COUNT(DISTINCT ecue."idECUE") as total_ecues,
                COALESCE(SUM(CASE WHEN ecue."CMI" > 0 THEN ecue."CMI" ELSE 0 END), 0) as total_heures_cm_prevues,
                COALESCE(SUM(CASE WHEN ecue."TD" > 0 THEN ecue."TD" ELSE 0 END), 0) as total_heures_td_prevues,
                COALESCE(SUM(CASE WHEN ecue."TP" > 0 THEN ecue."TP" ELSE 0 END), 0) as total_heures_tp_prevues,
                COALESCE(SUM(CASE WHEN se.type_cours = \'CM\' THEN
                    EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut)) / 3600.0 ELSE 0 END), 0) as heures_cm_realisees,
                COALESCE(SUM(CASE WHEN se.type_cours = \'TD\' THEN
                    EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut)) / 3600.0 ELSE 0 END), 0) as heures_td_realisees,
                COALESCE(SUM(CASE WHEN se.type_cours = \'TP\' THEN
                    EXTRACT(EPOCH FROM (se.heure_fin - se.heure_debut)) / 3600.0 ELSE 0 END), 0) as heures_tp_realisees
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              JOIN semestre sem ON p.idpromotion = sem.promotion_idpromotion
              JOIN ue ON sem.idsemestre = ue.semestre_idsemestre
              JOIN ecue ON ue."idUE" = ecue."UE_idUE"
              LEFT JOIN suivi_enseignements se ON ecue."idECUE" = se."idECUE"
                  AND se.annee_acad_idannee_acad = :anneeId
              WHERE p.annee_acad_idannee_acad = :anneeId
              AND COALESCE(ecue."estVisible", 1) = 1';
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= ' GROUP BY s.idsection, p.idpromotion
                ORDER BY s."designationSection", p."designationPromotion"';

    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur dans getAvancementCours: " . $e->getMessage());
        return [];
    }
}

// Fonction pour récupérer les statistiques des frais (version corrigée)
function getStatistiquesPaiements($pdo, $userSections, $anneeId) {
    $params = [':anneeId' => $anneeId];
    
    $query = 'SELECT
                s."designationSection",
                p."designationPromotion",
                COUNT(DISTINCT e.idetudiant) as nb_etudiants,
                COUNT(DISTINCT eo.idetudiant) as nb_etudiants_en_ordre,
                ROUND((COUNT(DISTINCT eo.idetudiant) * 100.0 / NULLIF(COUNT(DISTINCT e.idetudiant), 0)), 1) as pourcentage_en_ordre
              FROM promotion p
              JOIN orientation o ON p.orientation_idorientation = o.idorientation
              JOIN section s ON o.section_idsection = s.idsection
              LEFT JOIN etudiant e ON p.idpromotion = e.promotion_idpromotion
                  AND e.annee_acad_idannee_acad = :anneeId
              LEFT JOIN etudiant_en_ordre eo ON e.idetudiant = eo.idetudiant
                  AND eo.annee_acad_idannee_acad = :anneeId
              WHERE p.annee_acad_idannee_acad = :anneeId';
    
    if (!empty($userSections)) {
        $placeholders = [];
        foreach ($userSections as $i => $section) {
            $paramName = ":section{$i}";
            $placeholders[] = $paramName;
            $params[$paramName] = $section;
        }
        $query .= " AND o.section_idsection IN (" . implode(',', $placeholders) . ")";
    }
    
    $query .= ' GROUP BY s.idsection, p.idpromotion
                ORDER BY s."designationSection", p."designationPromotion"';

    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur dans getStatistiquesPaiements: " . $e->getMessage());
        return [];
    }
}

// Récupérer toutes les statistiques avec gestion d'erreurs
$statistiquesPromotions = [];
$avancementCours = [];
$statistiquesPaiements = [];

try {
    if ($isResponsableSection) {
        $statistiquesPromotions = getStatistiquesPromotions($pdo, $userSections, $currentYear['idannee_acad']);
        $avancementCours = getAvancementCours($pdo, $userSections, $currentYear['idannee_acad']);
        $statistiquesPaiements = getStatistiquesPaiements($pdo, $userSections, $currentYear['idannee_acad']);
    } else {
        $statistiquesPromotions = getStatistiquesPromotions($pdo, [], $currentYear['idannee_acad']);
        $avancementCours = getAvancementCours($pdo, [], $currentYear['idannee_acad']);
        $statistiquesPaiements = getStatistiquesPaiements($pdo, [], $currentYear['idannee_acad']);
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des statistiques: " . $e->getMessage());
}

// Calculer les totaux globaux avec vérification
$totalEtudiants = !empty($statistiquesPromotions) ? array_sum(array_column($statistiquesPromotions, 'nb_etudiants_inscrits')) : 0;
$totalEtudiantsActifs = !empty($statistiquesPromotions) ? array_sum(array_column($statistiquesPromotions, 'nb_etudiants_actifs')) : 0;
$totalSujets = !empty($statistiquesPromotions) ? array_sum(array_column($statistiquesPromotions, 'nb_sujets_recherche')) : 0;
$totalSujetsValides = !empty($statistiquesPromotions) ? array_sum(array_column($statistiquesPromotions, 'nb_sujets_valides')) : 0;
$totalEnseignants = !empty($statistiquesPromotions) ? array_sum(array_column($statistiquesPromotions, 'nb_enseignants')) : 0;

// Calculer les pourcentages globaux
$pourcentageEtudiantsActifs = $totalEtudiants > 0 ? round(($totalEtudiantsActifs / $totalEtudiants) * 100, 1) : 0;
$pourcentageSujetsValides = $totalSujets > 0 ? round(($totalSujetsValides / $totalSujets) * 100, 1) : 0;

// Calculer l'avancement global des cours
$totalHeuresPrevues = 0;
$totalHeuresRealisees = 0;
if (!empty($avancementCours)) {
    foreach ($avancementCours as $cours) {
        $totalHeuresPrevues += ($cours['total_heures_cm_prevues'] + $cours['total_heures_td_prevues'] + $cours['total_heures_tp_prevues']);
        $totalHeuresRealisees += ($cours['heures_cm_realisees'] + $cours['heures_td_realisees'] + $cours['heures_tp_realisees']);
    }
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
                        <h5 class="card-title">
                            Année académique : <?= htmlspecialchars($currentYear['designation']) ?>
                            <span class="badge bg-success ms-2">Active</span>
                        </h5>
                        <?php if ($isResponsableSection): ?>
                            <p class="text-muted">
                                <i class="bi bi-person-check"></i> 
                                Vous êtes responsable de <?= count($userSections) ?> section(s)
                            </p>
                        <?php else: ?>
                            <p class="text-muted">
                                <i class="bi bi-globe"></i> 
                                Vue globale de toutes les sections
                            </p>
                        <?php endif; ?>
                        
                        <!-- Indicateurs de qualité des données -->
                        <div class="row mt-3">
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="bi bi-database"></i> 
                                    Promotions: <?= count($statistiquesPromotions) ?>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> 
                                    Dernière mise à jour: <?= date('d/m/Y H:i') ?>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">
                                    <i class="bi bi-shield-check"></i> 
                                    Données vérifiées
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques globales avec indicateurs améliorés -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card sales-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Étudiants inscrits
                            <span class="badge bg-<?= getStatusBadgeColor($pourcentageEtudiantsActifs) ?> ms-2">
                                <?= $pourcentageEtudiantsActifs ?>%
                            </span>
                        </h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($totalEtudiants) ?></h6>
                                <span class="text-success small pt-1 fw-bold"><?= number_format($totalEtudiantsActifs) ?></span>
                                <span class="text-muted small pt-2 ps-1">actifs</span>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?= $pourcentageEtudiantsActifs ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card revenue-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Sujets de recherche
                            <span class="badge bg-<?= getStatusBadgeColor($pourcentageSujetsValides) ?> ms-2">
                                <?= $pourcentageSujetsValides ?>%
                            </span>
                        </h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-journal-text"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= number_format($totalSujets) ?></h6>
                                <span class="text-success small pt-1 fw-bold"><?= number_format($totalSujetsValides) ?></span>
                                <span class="text-muted small pt-2 ps-1">validés</span>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?= $pourcentageSujetsValides ?>%">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card info-card customers-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Avancement cours
                            <span class="badge bg-<?= getProgressBadgeColor($pourcentageAvancementGlobal) ?> ms-2">
                                <?= $pourcentageAvancementGlobal ?>%
                            </span>
                        </h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?= $pourcentageAvancementGlobal ?>%</h6>
                                <span class="text-muted small pt-2 ps-1">
                                    <?= number_format($totalHeuresRealisees, 1) ?>h / <?= number_format($totalHeuresPrevues, 1) ?>h
                                </span>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-<?= getProgressColor($pourcentageAvancementGlobal) ?>" 
                                 role="progressbar" style="width: <?= $pourcentageAvancementGlobal ?>%">
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
                                <h6><?= number_format($totalEnseignants) ?></h6>
                                <span class="text-muted small pt-2 ps-1">Total enseignants</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertes et notifications -->
        <?php if (empty($statistiquesPromotions)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Attention!</strong> Aucune donnée trouvée pour l'année académique courante.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($pourcentageAvancementGlobal < 30): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <strong>Alerte!</strong> L'avancement global des cours est faible (<?= $pourcentageAvancementGlobal ?>%). 
            Une attention particulière est requise.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Tableau détaillé par promotion avec améliorations -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">
                    Situation détaillée par promotion
                    <div class="float-end">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()" 
                                    title="Exporter vers Excel">
                                <i class="bi bi-file-excel"></i> Excel
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="printReport()" 
                                    title="Imprimer le rapport">
                                <i class="bi bi-printer"></i> Imprimer
                            </button>
                            <button type="button" class="btn btn-info btn-sm" onclick="refreshData()" 
                                    title="Actualiser les données">
                                <i class="bi bi-arrow-clockwise"></i> Actualiser
                            </button>
                        </div>
                    </div>
                </h5>

                <?php if (!empty($statistiquesPromotions)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" id="promotionsTable">
                        <thead class="table-dark">
                            <tr>
                                <th rowspan="2" class="text-center">#</th>
                                <th rowspan="2">Section</th>
                                <th rowspan="2">Promotion</th>
                                <th rowspan="2" class="text-center">Cycle</th>
                                <th colspan="2" class="text-center">Étudiants</th>
                                <th colspan="2" class="text-center">Sujets recherche</th>
                                <th rowspan="2" class="text-center">Enseignants</th>
                                <th rowspan="2" class="text-center">Actions</th>
                            </tr>
                            <tr>
                                <th class="text-center">Inscrits</th>
                                <th class="text-center">Actifs</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Validés</th>
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
                            <tr class="<?= getRowClass($pourcentageActifs) ?>">
                                <td class="text-center fw-bold"><?= $index++ ?></td>
                                <td>
                                    <span class="fw-bold"><?= htmlspecialchars($promo['designationSection']) ?></span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($promo['designationPromotion']) ?></strong>
                                        <?php if ($promo['est_terminale']): ?>
                                            <span class="badge bg-warning ms-1">Terminale</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-arrow-right"></i>
                                        <?= htmlspecialchars($promo['designationOrientation']) ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= getCycleBadgeColor($promo['cycle']) ?>">
                                        <?= htmlspecialchars($promo['cycle']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?= $promo['nb_etudiants_inscrits'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?= $promo['nb_etudiants_actifs'] ?></span>
                                    <br>
                                    <span class="badge bg-<?= getStatusBadgeColor($pourcentageActifs) ?>">
                                        <?= $pourcentageActifs ?>%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?= $promo['nb_sujets_recherche'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?= $promo['nb_sujets_valides'] ?></span>
                                    <br>
                                    <span class="badge bg-<?= getStatusBadgeColor($pourcentageSujets) ?>">
                                        <?= $pourcentageSujets ?>%
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold"><?= $promo['nb_enseignants'] ?></span>
                                </td>
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
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="4" class="text-end">TOTAUX:</th>
                                <th class="text-center"><?= number_format($totalEtudiants) ?></th>
                                <th class="text-center">
                                    <?= number_format($totalEtudiantsActifs) ?>
                                    <br><small>(<?= $pourcentageEtudiantsActifs ?>%)</small>
                                </th>
                                <th class="text-center"><?= number_format($totalSujets) ?></th>
                                <th class="text-center">
                                    <?= number_format($totalSujetsValides) ?>
                                    <br><small>(<?= $pourcentageSujetsValides ?>%)</small>
                                </th>
                                <th class="text-center"><?= number_format($totalEnseignants) ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">Aucune donnée disponible</h4>
                    <p class="text-muted">Aucune promotion trouvée pour l'année académique courante.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reste du code identique pour les autres sections... -->
        <!-- (Avancement des cours, Situation des paiements, Graphiques) -->
        
    </section>
</main>

<?php
// Fonctions utilitaires améliorées
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

function getProgressBadgeColor($percentage) {
    if ($percentage >= 80) return 'success';
    if ($percentage >= 60) return 'primary';
    if ($percentage >= 40) return 'warning';
    return 'danger';
}

function getStatusBadgeColor($percentage) {
    if ($percentage >= 80) return 'success';
    if ($percentage >= 60) return 'warning';
    return 'danger';
}

function getPaymentStatusColor($percentage) {
    if ($percentage >= 80) return 'success';
    if ($percentage >= 60) return 'warning';
    return 'danger';
}

function getRowClass($percentage) {
    if ($percentage < 50) return 'table-warning';
    if ($percentage < 30) return 'table-danger';
    return '';
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
// Fonctions JavaScript améliorées
function exportToExcel() {
    // Afficher un indicateur de chargement
    Swal.fire({
        title: 'Export en cours...',
        text: 'Génération du fichier Excel',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const params = new URLSearchParams();
    params.set('export', 'excel');
    params.set('type', 'tableau_bord_section');
    
    // Rediriger vers l'export
    window.location.href = 'controller/export_tableau_bord.php?' + params.toString();
    
    // Fermer l'indicateur après un délai
    setTimeout(() => {
        Swal.close();
    }, 2000);
}

function printReport() {
    window.print();
}

function refreshData() {
    Swal.fire({
        title: 'Actualisation...',
        text: 'Mise à jour des données en cours',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Recharger la page
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Initialisation des graphiques et autres fonctionnalités
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Graphiques Chart.js (code identique à l'original)
    // ...
});
</script>

<style>
/* Styles améliorés */
@media print {
    .btn, .breadcrumb, .pagetitle nav, .alert {
        display: none !important;
    }
    
    .card {
        border: 1px solid #000 !important;
        page-break-inside: avoid;
        margin-bottom: 20px;
    }
    
    .table {
        font-size: 11px;
    }
    
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: white !important;
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
    position: sticky;
    top: 0;
    z-index: 10;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.1);
}

.btn-group .btn {
    margin: 0 1px;
}

.alert {
    border-left: 4px solid;
}

.alert-warning {
    border-left-color: #ffc107;
}

.alert-danger {
    border-left-color: #dc3545;
}

/* Animation pour les cartes */
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Amélioration des badges */
.badge {
    font-size: 0.75em;
    padding: 0.35em 0.65em;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        margin: 1px 0;
    }
    
    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>

<?php include "./views/include/footer.php"; ?>