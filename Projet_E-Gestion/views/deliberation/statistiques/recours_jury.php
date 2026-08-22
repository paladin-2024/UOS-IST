<?php
include_once "./views/include/header.php";

$conn = Connexion::getInstance()->getPDO();

// Déterminer l'année académique en cours
$query_annee_encours = 'SELECT idannee_acad, designation FROM annee_acad ORDER BY "dateCreation" DESC LIMIT 1';
$stmt_annee = $conn->prepare($query_annee_encours);
$stmt_annee->execute();
$annee_encours = $stmt_annee->fetch(PDO::FETCH_ASSOC);
$id_annee_encours = $annee_encours['idannee_acad'];

// Vérifier si l'utilisateur est admin ou président de jury
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] == 'Administrateur';
$userId = $_SESSION['id'];


// Récupérer les sessions
$query_sessions = 'SELECT idsession, "designSession", description FROM session ORDER BY idsession';
$stmt_sessions = $conn->prepare($query_sessions);
$stmt_sessions->execute();
$sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la session sélectionnée s'il y en a une
$selectedSession = isset($_GET['session']) ? intval($_GET['session']) : 0;


// Récupérer les bureaux de jury dont l'utilisateur est président
$userJurys = [];
if (!$isAdmin) {
    $query_user_jurys = "SELECT bjd.idbureau, bjd.designation 
                         FROM bureau_jury_deliberation bjd 
                         WHERE bjd.president_id = :userId 
                         AND bjd.annee_acad_idannee_acad = :anneeId
                         AND bjd.est_actif = 1";
    $stmt_user_jurys = $conn->prepare($query_user_jurys);
    $stmt_user_jurys->bindParam(':userId', $userId);
    $stmt_user_jurys->bindParam(':anneeId', $id_annee_encours);
    $stmt_user_jurys->execute();
    $userJurys = $stmt_user_jurys->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer tous les jurys pour l'admin
$allJurys = [];
if ($isAdmin) {
    $query_all_jurys = "SELECT bjd.idbureau, bjd.designation 
                        FROM bureau_jury_deliberation bjd 
                        WHERE bjd.annee_acad_idannee_acad = :anneeId
                        AND bjd.est_actif = 1
                        ORDER BY bjd.designation";
    $stmt_all_jurys = $conn->prepare($query_all_jurys);
    $stmt_all_jurys->bindParam(':anneeId', $id_annee_encours);
    $stmt_all_jurys->execute();
    $allJurys = $stmt_all_jurys->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer le jury sélectionné s'il y en a un
$selectedJury = isset($_GET['jury']) ? intval($_GET['jury']) : 0;

// Si l'utilisateur n'est pas admin, vérifier que le jury sélectionné est bien l'un des siens
if (!$isAdmin && $selectedJury > 0) {
    $hasAccess = false;
    foreach ($userJurys as $jury) {
        if ($jury['idbureau'] == $selectedJury) {
            $hasAccess = true;
            break;
        }
    }
    if (!$hasAccess) {
        // Rediriger ou sélectionner le premier jury de l'utilisateur
        $selectedJury = !empty($userJurys) ? $userJurys[0]['idbureau'] : 0;
    }
}

// Récupérer les statistiques de recours pour le jury sélectionné ou tous les jurys
$stats = [];
$statsQuery = "";

if ($isAdmin && $selectedJury == 0) {
    // Statistiques globales pour l'admin
    $statsQuery = "SELECT 
                  bjd.idbureau,
                  bjd.designation as jury_name,
                  COUNT(DISTINCT r.id_recours) as total_recours,
                  SUM(CASE WHEN r.statut = 'En attente' THEN 1 ELSE 0 END) as en_attente,
                  SUM(CASE WHEN r.statut = 'En traitement' THEN 1 ELSE 0 END) as en_traitement,
                  SUM(CASE WHEN r.statut = 'Approuvé' THEN 1 ELSE 0 END) as approuve,
                  SUM(CASE WHEN r.statut = 'Rejeté' THEN 1 ELSE 0 END) as rejete,
                  SUM(CASE WHEN r.est_paye = 1 THEN 1 ELSE 0 END) as payes
              FROM bureau_jury_deliberation bjd
              LEFT JOIN bureau_jury_promotion bjp ON bjd.idbureau = bjp.idbureau
              LEFT JOIN promotion p ON bjp.idpromotion = p.idpromotion
              LEFT JOIN semestre s ON p.idpromotion = s.promotion_idpromotion
              LEFT JOIN ue u ON s.idsemestre = u.semestre_idsemestre
              LEFT JOIN ecue e ON u.\"idUE\" = e.\"UE_idUE\"
              LEFT JOIN recours r ON r.id_ecue = e.\"idECUE\" AND r.id_annee_acad = :anneeId
              WHERE bjd.annee_acad_idannee_acad = :anneeId
              AND bjd.est_actif = 1";
    
    // Ajouter le filtre de session si une session est sélectionnée
    if ($selectedSession > 0) {
        $statsQuery .= " AND r.id_session = :sessionId";
    }
    
    $statsQuery .= " GROUP BY bjd.idbureau, bjd.designation
                   ORDER BY bjd.designation";
    
    $stmt_stats = $conn->prepare($statsQuery);
    $stmt_stats->bindParam(':anneeId', $id_annee_encours);
    
    // Bind le paramètre de session si nécessaire
    if ($selectedSession > 0) {
        $stmt_stats->bindParam(':sessionId', $selectedSession);
    }
    
    $stmt_stats->execute();
    $stats = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);
} else {
     // Statistiques pour un jury spécifique
     $juryId = $selectedJury > 0 ? $selectedJury : ($isAdmin ? (!empty($allJurys) ? $allJurys[0]['idbureau'] : 0) : (!empty($userJurys) ? $userJurys[0]['idbureau'] : 0));
        
    $statsQuery = "SELECT 
                    bjd.idbureau,
                    bjd.designation as jury_name,
                    COUNT(DISTINCT r.id_recours) as total_recours,
                    SUM(CASE WHEN r.statut = 'En attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN r.statut = 'En traitement' THEN 1 ELSE 0 END) as en_traitement,
                    SUM(CASE WHEN r.statut = 'Approuvé' THEN 1 ELSE 0 END) as approuve,
                    SUM(CASE WHEN r.statut = 'Rejeté' THEN 1 ELSE 0 END) as rejete,
                    SUM(CASE WHEN r.est_paye = 1 THEN 1 ELSE 0 END) as payes
                FROM bureau_jury_deliberation bjd
                LEFT JOIN bureau_jury_promotion bjp ON bjd.idbureau = bjp.idbureau
                LEFT JOIN promotion p ON bjp.idpromotion = p.idpromotion
                LEFT JOIN semestre s ON p.idpromotion = s.promotion_idpromotion
                LEFT JOIN ue u ON s.idsemestre = u.semestre_idsemestre
                LEFT JOIN ecue e ON u.\"idUE\" = e.\"UE_idUE\"
                LEFT JOIN recours r ON r.id_ecue = e.\"idECUE\" AND r.id_annee_acad = :anneeId
                WHERE bjd.idbureau = :juryId
                AND bjd.annee_acad_idannee_acad = :anneeId
                AND bjd.est_actif = 1";

    // Ajouter le filtre de session si une session est sélectionnée
    if ($selectedSession > 0) {
        $statsQuery .= " AND r.id_session = :sessionId";
    }

    $statsQuery .= " GROUP BY bjd.idbureau, bjd.designation";
        
    $stmt_stats = $conn->prepare($statsQuery);
    $stmt_stats->bindParam(':juryId', $juryId);
    $stmt_stats->bindParam(':anneeId', $id_annee_encours);

    // Bind le paramètre de session si nécessaire
    if ($selectedSession > 0) {
        $stmt_stats->bindParam(':sessionId', $selectedSession);
    }

    $stmt_stats->execute();
    $stats = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);
        
    // Récupérer les détails des ECUEs avec recours pour ce jury
    $ecueStatsQuery = "SELECT
                        e.\"idECUE\",
                        e.\"designationECUE\",
                        u.\"designationUE\",
                        COUNT(DISTINCT r.id_recours) as total_recours,
                        SUM(CASE WHEN r.statut = 'En attente' THEN 1 ELSE 0 END) as en_attente,
                        SUM(CASE WHEN r.statut = 'En traitement' THEN 1 ELSE 0 END) as en_traitement,
                        SUM(CASE WHEN r.statut = 'Approuvé' THEN 1 ELSE 0 END) as approuve,
                        SUM(CASE WHEN r.statut = 'Rejeté' THEN 1 ELSE 0 END) as rejete
                    FROM ecue e
                    JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                    JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                    JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                    JOIN bureau_jury_promotion bjp ON p.idpromotion = bjp.idpromotion
                    JOIN bureau_jury_deliberation bjd ON bjp.idbureau = bjd.idbureau
                    LEFT JOIN recours r ON r.id_ecue = e.\"idECUE\" AND r.id_annee_acad = :anneeId";

    // J'ai également corrigé ici une erreur dans la requête originale (manquait JOIN bureau_jury_promotion)

    // Ajouter le filtre de session si une session est sélectionnée
    if ($selectedSession > 0) {
        $ecueStatsQuery .= " AND r.id_session = :sessionId";
    }

    $ecueStatsQuery .= " WHERE bjd.idbureau = :juryId 
                    AND bjd.annee_acad_idannee_acad = :anneeId
                    AND bjd.est_actif = 1
                    GROUP BY e.\"idECUE\", e.\"designationECUE\", u.\"designationUE\"
                    HAVING COUNT(r.id_recours) > 0
                    ORDER BY u.\"designationUE\", e.\"designationECUE\"";
        
    $stmt_ecue_stats = $conn->prepare($ecueStatsQuery);
    $stmt_ecue_stats->bindParam(':juryId', $juryId);
    $stmt_ecue_stats->bindParam(':anneeId', $id_annee_encours);

    // Bind le paramètre de session si nécessaire
    if ($selectedSession > 0) {
        $stmt_ecue_stats->bindParam(':sessionId', $selectedSession);
    }

    $stmt_ecue_stats->execute();
    $ecueStats = $stmt_ecue_stats->fetchAll(PDO::FETCH_ASSOC);

}
?>


<main id="main" class="main">
    <div class="pagetitle">
        <h1>Tableau de Bord des Recours par Jury</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item"><a href="deliberation/recours">Recours</a></li>
                <li class="breadcrumb-item active">Statistiques par Jury</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
        <div class="col-12">
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart-line me-2"></i>Statistiques des Recours
                    <span class="text-secondary fw-normal">| Année académique <?= htmlspecialchars($annee_encours['designation']) ?></span>
                </h5>
                
                <a href="index" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Retour
                </a>
            </div>
            
            <div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-3">
                <h6 class="card-subtitle mb-3 text-muted">
                    <i class="bi bi-funnel-fill me-2"></i>Filtres d'analyse
                </h6>
                
                <form method="GET" action="" id="filter-form" class="row g-3">
                    <!-- Filtre Jury -->
                    <div class="col-md-6">
                        <label for="jury" class="form-label fw-bold">
                            <i class="bi bi-people me-1 text-primary"></i> Jury
                        </label>
                        <select class="form-select form-select-sm" id="jury" name="jury" onchange="this.form.submit()">
                            <?php if ($isAdmin): ?>
                                <option value="0" <?= $selectedJury == 0 ? 'selected' : '' ?>>Tous les jurys</option>
                                <?php foreach ($allJurys as $jury): ?>
                                    <option value="<?= $jury['idbureau'] ?>"
                                            <?= $selectedJury == $jury['idbureau'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($jury['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($userJurys as $jury): ?>
                                    <option value="<?= $jury['idbureau'] ?>"
                                            <?= $selectedJury == $jury['idbureau'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($jury['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <!-- Filtre Session -->
                    <div class="col-md-6">
                        <label for="session" class="form-label fw-bold">
                            <i class="bi bi-calendar-event me-1 text-primary"></i> Session
                        </label>
                        <select class="form-select form-select-sm" id="session" name="session" onchange="this.form.submit()">
                            <option value="0" <?= $selectedSession == 0 ? 'selected' : '' ?>>Toutes les sessions</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?= $session['idsession'] ?>"
                                        <?= $selectedSession == $session['idsession'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($session['designSession']) ?>
                                    <?= !empty($session['description']) ? '- '.htmlspecialchars($session['description']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                
                <!-- Afficher les filtres actifs sous forme de badges -->
                <?php if ($selectedJury > 0 || $selectedSession > 0): ?>
                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted">Filtres actifs:</small>
                    <div class="mt-1">
                        <?php if ($selectedJury > 0): 
                            $juryName = '';
                            foreach (($isAdmin ? $allJurys : $userJurys) as $j) {
                                if ($j['idbureau'] == $selectedJury) {
                                    $juryName = $j['designation'];
                                    break;
                                }
                            }
                        ?>
                            <span class="badge bg-primary me-2">
                                <i class="bi bi-people me-1"></i> 
                                <?= htmlspecialchars($juryName) ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($selectedSession > 0): 
                            $sessionName = '';
                            foreach ($sessions as $s) {
                                if ($s['idsession'] == $selectedSession) {
                                    $sessionName = $s['designSession'];
                                    if (!empty($s['description'])) {
                                        $sessionName .= ' - ' . $s['description'];
                                    }
                                    break;
                                }
                            }
                        ?>
                            <span class="badge bg-info text-dark me-2">
                                <i class="bi bi-calendar-event me-1"></i> 
                                <?= htmlspecialchars($sessionName) ?>
                            </span>
                        <?php endif; ?>
                        
                        <a href="deliberation/statistiques/recours_jury" class="badge bg-secondary text-decoration-none">
                            <i class="bi bi-x-circle me-1"></i>Réinitialiser
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
</div>


        </div>
    </div>
</div>


            
            <?php if ($isAdmin && $selectedJury == 0): ?>
            <!-- Vue globale pour admin (tous les jurys) -->
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                    <h5 class="card-title">
                        Statistiques Globales par Jury
                        <?php if ($selectedSession > 0): ?>
                            <span class="text-muted fw-normal fs-6">
                                (Session: <?= htmlspecialchars($sessionName) ?>)
                            </span>
                        <?php endif; ?>
                    </h5>

                        
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr class="table-primary">
                                        <th>Jury</th>
                                        <th class="text-center">Total Recours</th>
                                        <th class="text-center">En Attente</th>
                                        <th class="text-center">En Traitement</th>
                                        <th class="text-center">Approuvés</th>
                                        <th class="text-center">Rejetés</th>
                                        <th class="text-center">Taux de Traitement</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($stats) || (count($stats) == 1 && $stats[0]['total_recours'] == 0)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">Aucune donnée disponible</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $totalRecours = 0;
                                        $totalEnAttente = 0;
                                        $totalEnTraitement = 0;
                                        $totalApprouves = 0;
                                        $totalRejetes = 0;
                                        
                                        foreach ($stats as $stat): 
                                            $totalRecours += $stat['total_recours'];
                                            $totalEnAttente += $stat['en_attente'];
                                            $totalEnTraitement += $stat['en_traitement'];
                                            $totalApprouves += $stat['approuve'];
                                            $totalRejetes += $stat['rejete'];
                                            
                                            // Calcul du taux de traitement (recours traités / total)
                                            $traites = $stat['approuve'] + $stat['rejete'];
                                            $tauxTraitement = $stat['total_recours'] > 0 ? round(($traites / $stat['total_recours']) * 100) : 0;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($stat['jury_name']) ?></td>
                                                <td class="text-center"><?= $stat['total_recours'] ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-warning"><?= $stat['en_attente'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-info"><?= $stat['en_traitement'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-success"><?= $stat['approuve'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-danger"><?= $stat['rejete'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?= $tauxTraitement ?>%;" 
                                                             aria-valuenow="<?= $tauxTraitement ?>" 
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            <?= $tauxTraitement ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <!-- Ligne de totaux -->
                                        <?php 
                                        $tauxTraitementGlobal = $totalRecours > 0 ? 
                                            round((($totalApprouves + $totalRejetes) / $totalRecours) * 100) : 0; 
                                        ?>
                                        <tr class="table-active fw-bold">
                                            <td>TOTAL</td>
                                            <td class="text-center"><?= $totalRecours ?></td>
                                            <td class="text-center"><?= $totalEnAttente ?></td>
                                            <td class="text-center"><?= $totalEnTraitement ?></td>
                                            <td class="text-center"><?= $totalApprouves ?></td>
                                            <td class="text-center"><?= $totalRejetes ?></td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?= $tauxTraitementGlobal ?>%;" 
                                                         aria-valuenow="<?= $tauxTraitementGlobal ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?= $tauxTraitementGlobal ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Graphiques pour tous les jurys (admin) -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Répartition des recours par jury</h5>
                        <div>
                            <canvas id="recoursByJury"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Statut des recours par jury</h5>
                        <div>
                            <canvas id="recoursByStatus"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Vue détaillée pour un jury spécifique -->
            <?php if (!empty($stats) && $stats[0]['total_recours'] > 0): ?>
                <?php $stat = $stats[0]; ?>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Résumé des recours pour <?= htmlspecialchars($stat['jury_name']) ?></h5>
                            
                            <div class="row">
                                <div class="col-md-3 mb-4">
                                    <div class="card info-card total-card">
                                        <div class="card-body">
                                            <h5 class="card-title">Total des recours</h5>
                                            <div class="d-flex align-items-center">
                                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-clipboard-data"></i>
                                                </div>
                                                <div class="ps-3">
                                                    <h6><?= $stat['total_recours'] ?></h6>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3 mb-4">
                                    <div class="card info-card warning-card">
                                        <div class="card-body">
                                            <h5 class="card-title">En attente</h5>
                                            <div class="d-flex align-items-center">
                                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-hourglass-split"></i>
                                                </div>
                                                <div class="ps-3">
                                                    <h6><?= $stat['en_attente'] ?></h6>
                                                    <span class="text-muted small pt-2 ps-1">
                                                        <?= round(($stat['en_attente'] / $stat['total_recours']) * 100) ?>% du total
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3 mb-4">
                                    <div class="card info-card info-card">
                                        <div class="card-body">
                                            <h5 class="card-title">En traitement</h5>
                                            <div class="d-flex align-items-center">
                                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-gear"></i>
                                                </div>
                                                <div class="ps-3">
                                                    <h6><?= $stat['en_traitement'] ?></h6>
                                                    <span class="text-muted small pt-2 ps-1">
                                                        <?= round(($stat['en_traitement'] / $stat['total_recours']) * 100) ?>% du total
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-3 mb-4">
                                    <div class="card info-card success-card">
                                        <div class="card-body">
                                            <h5 class="card-title">Approuvés/Rejetés</h5>
                                            <div class="d-flex align-items-center">
                                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-check-circle"></i>
                                                </div>
                                                <div class="ps-3">
                                                    <h6><?= $stat['approuve'] ?> / <?= $stat['rejete'] ?></h6>
                                                    <span class="text-muted small pt-2 ps-1">
                                                        <?= round((($stat['approuve'] + $stat['rejete']) / $stat['total_recours']) * 100) ?>% traités
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Graphique de statut pour ce jury -->
                            <div class="row mt-3">
                                <div class="col-lg-6">
                                    <canvas id="statusChart"></canvas>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Progression du traitement</h5>
                                            <?php 
                                            $traites = $stat['approuve'] + $stat['rejete'];
                                            $tauxTraitement = round(($traites / $stat['total_recours']) * 100);
                                            ?>
                                            <div class="progress mt-3" style="height: 30px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?= $tauxTraitement ?>%;" 
                                                     aria-valuenow="<?= $tauxTraitement ?>" 
                                                     aria-valuemin="0" aria-valuemax="100">
                                                    <?= $tauxTraitement ?>% traités
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4">
                                                <h6>Détails:</h6>
                                                <ul>
                                                    <li><strong>Recours payés:</strong> <?= $stat['payes'] ?> (<?= round(($stat['payes'] / $stat['total_recours']) * 100) ?>%)</li>
                                                    <li><strong>Recours approuvés:</strong> <?= $stat['approuve'] ?> (<?= round(($stat['approuve'] / $stat['total_recours']) * 100) ?>%)</li>
                                                    <li><strong>Recours rejetés:</strong> <?= $stat['rejete'] ?> (<?= round(($stat['rejete'] / $stat['total_recours']) * 100) ?>%)</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Détails des recours par ECUE pour ce jury -->
                <div class="col-lg-12 mt-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Détails par ECUE</h5>
                            
                            <?php if (isset($ecueStats) && !empty($ecueStats)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr class="table-primary">
                                                <th>UE</th>
                                                <th>ECUE</th>
                                                <th class="text-center">Total Recours</th>
                                                <th class="text-center">En Attente</th>
                                                <th class="text-center">En Traitement</th>
                                                <th class="text-center">Approuvés</th>
                                                <th class="text-center">Rejetés</th>
                                                <th class="text-center">Taux de Traitement</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($ecueStats as $ecueStat): ?>
                                                <?php 
                                                $ecueTraites = $ecueStat['approuve'] + $ecueStat['rejete'];
                                                $tauxTraitementEcue = $ecueStat['total_recours'] > 0 ? 
                                                    round(($ecueTraites / $ecueStat['total_recours']) * 100) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($ecueStat['designationUE']) ?></td>
                                                    <td><?= htmlspecialchars($ecueStat['designationECUE']) ?></td>
                                                    <td class="text-center"><?= $ecueStat['total_recours'] ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-warning"><?= $ecueStat['en_attente'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info"><?= $ecueStat['en_traitement'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success"><?= $ecueStat['approuve'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                    <span class="badge bg-danger"><?= $ecueStat['rejete'] ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?= $tauxTraitementEcue ?>%;" 
                                                             aria-valuenow="<?= $tauxTraitementEcue ?>" 
                                                             aria-valuemin="0" aria-valuemax="100">
                                                            <?= $tauxTraitementEcue ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucun recours trouvé pour les ECUEs de ce jury.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-lg-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-1"></i>
                    Aucun recours n'a été enregistré pour ce jury.
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        
    </div>
</section>
</main>

<!-- Scripts pour les graphiques -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
<?php if ($isAdmin && $selectedJury == 0 && !empty($stats)): ?>
// Graphiques pour la vue admin (tous les jurys)

// Préparation des données pour le graphique de répartition des recours par jury
const juryLabels = <?= json_encode(array_column($stats, 'jury_name')) ?>;
const juryData = <?= json_encode(array_column($stats, 'total_recours')) ?>;

// Graphique de répartition des recours par jury (Pie chart)
new Chart(document.getElementById('recoursByJury'), {
    type: 'pie',
    data: {
        labels: juryLabels,
        datasets: [{
            data: juryData,
            backgroundColor: [
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 99, 132, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)',
                'rgba(255, 159, 64, 0.7)',
                'rgba(199, 199, 199, 0.7)',
                'rgba(83, 102, 255, 0.7)',
                'rgba(40, 159, 64, 0.7)',
                'rgba(210, 199, 199, 0.7)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right',
            },
            title: {
                display: true,
                text: 'Répartition des recours par jury'
            }
        }
    }
});

// Préparation des données pour le graphique de statut des recours (Bar chart empilé)
const statusData = {
    labels: juryLabels,
    datasets: [
        {
            label: 'En attente',
            data: <?= json_encode(array_column($stats, 'en_attente')) ?>,
            backgroundColor: 'rgba(255, 206, 86, 0.7)',
            borderColor: 'rgba(255, 206, 86, 1)',
            borderWidth: 1
        },
        {
            label: 'En traitement',
            data: <?= json_encode(array_column($stats, 'en_traitement')) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        },
        {
            label: 'Approuvés',
            data: <?= json_encode(array_column($stats, 'approuve')) ?>,
            backgroundColor: 'rgba(75, 192, 192, 0.7)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        },
        {
            label: 'Rejetés',
            data: <?= json_encode(array_column($stats, 'rejete')) ?>,
            backgroundColor: 'rgba(255, 99, 132, 0.7)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1
        }
    ]
};

new Chart(document.getElementById('recoursByStatus'), {
    type: 'bar',
    data: statusData,
    options: {
        plugins: {
            title: {
                display: true,
                text: 'Répartition des recours par jury' + 
                      '<?= $selectedSession > 0 ? " - Session: " . addslashes($sessionName) : "" ?>'
            },
        },
        responsive: true,
        scales: {
            x: {
                stacked: true,
            },
            y: {
                stacked: true
            }
        }
    }
});
<?php endif; ?>

<?php if (!$isAdmin || $selectedJury > 0): ?>
<?php if (!empty($stats) && $stats[0]['total_recours'] > 0): ?>
// Graphique pour la vue détaillée d'un jury
const statusChart = new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['En attente', 'En traitement', 'Approuvés', 'Rejetés'],
        datasets: [{
            data: [
                <?= $stats[0]['en_attente'] ?>,
                <?= $stats[0]['en_traitement'] ?>,
                <?= $stats[0]['approuve'] ?>,
                <?= $stats[0]['rejete'] ?>
            ],
            backgroundColor: [
                'rgba(255, 206, 86, 0.7)',  // Jaune pour En attente
                'rgba(54, 162, 235, 0.7)',  // Bleu pour En traitement
                'rgba(75, 192, 192, 0.7)',  // Vert pour Approuvés
                'rgba(255, 99, 132, 0.7)'   // Rouge pour Rejetés
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: true,
                text: 'Répartition des recours par statut'
            }
        }
    }
});
<?php endif; ?>
<?php endif; ?>
});
</script>

<?php include_once "./views/include/footer_file.php"; ?>
