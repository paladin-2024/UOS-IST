<?php
include "./views/include/header.php";
$pdo = Connexion::getInstance()->getPDO();

// Récupérer l'ID utilisateur connecté
$userId = $_SESSION['id'] ?? 0;

// Récupérer toutes les années académiques pour le filtre
$queryYears = "SELECT * FROM annee_acad ORDER BY designation DESC";
$stmtYears = $pdo->prepare($queryYears);
$stmtYears->execute();
$annees = $stmtYears->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'année académique active (par défaut)
$queryActiveYear = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmtActiveYear = $pdo->prepare($queryActiveYear);
$stmtActiveYear->execute();
$anneeActive = $stmtActiveYear->fetch(PDO::FETCH_ASSOC);

// Si aucune année active, prendre la plus récente
if (!$anneeActive) {
    $queryLatestYear = "SELECT * FROM annee_acad ORDER BY \"dateCreation\" DESC LIMIT 1";
    $stmtLatestYear = $pdo->prepare($queryLatestYear);
    $stmtLatestYear->execute();
    $anneeActive = $stmtLatestYear->fetch(PDO::FETCH_ASSOC);
}

// Vérifier si l'utilisateur a sélectionné une année spécifique
$selectedYear = isset($_GET['annee']) ? intval($_GET['annee']) : ($anneeActive ? $anneeActive['idannee_acad'] : 0);

// Si l'année sélectionnée existe, l'utiliser
if ($selectedYear) {
    $queryCheckYear = "SELECT * FROM annee_acad WHERE idannee_acad = ?";
    $stmtCheckYear = $pdo->prepare($queryCheckYear);
    $stmtCheckYear->execute([$selectedYear]);
    $selectedYearData = $stmtCheckYear->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedYearData) {
        $anneeActive = $selectedYearData;
    }
}

// Paramètre de recherche
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer l'ID de l'agent enseignant associé à l'utilisateur connecté
$query = "SELECT a.\"idAgent\" 
          FROM agent a 
          INNER JOIN t_users u ON a.\"idAgent\" = u.\"idAgent\" 
          WHERE u.\"idUser\" = ? AND a.type_agent = 'Enseignant'";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId]);
$idAgent = $stmt->fetchColumn();

if (!$idAgent) {
    echo "<div class='alert alert-danger'>
            <i class='bi bi-exclamation-triangle'></i> 
            Vous n'êtes pas enregistré comme enseignant dans le système.
          </div>";
    echo "<meta http-equiv='refresh' content='3;URL=index'>";
    exit();
}

// Récupérer les sujets où l'agent est directeur ou encadreur
$query = "SELECT s.*, 
          a.designation as annee, 
          e.noms as etudiant_nom, 
          e.matricule as etudiant_matricule,
          e.adressemail as etudiant_email,
          e.idetudiant as etudiant_idetudiant,
          spe.designation as specialisation,
          c.\"designationSection\" as cycle
          FROM sujets s
          LEFT JOIN annee_acad a ON s.annee_acad_idannee_acad = a.idannee_acad
          LEFT JOIN etudiant e ON s.etudiant_idetudiant = e.idetudiant
          LEFT JOIN specialisation spe ON s.\"idSpecialisation\" = spe.\"idSpecialisation\"
          LEFT JOIN orientation o ON spe.idorientation = o.idorientation
          LEFT JOIN section c ON o.section_idsection = c.idsection
          WHERE (s.\"idDirecteur\" = ? OR s.\"idEncadreur\" = ?)
          AND s.annee_acad_idannee_acad = ?";

if (!empty($search)) {
    $query .= " AND (s.intitule LIKE ? OR e.noms LIKE ?)";
}

$stmt = $pdo->prepare($query);

if (!empty($search)) {
    $searchParam = "%$search%";
    $stmt->execute([$idAgent, $idAgent, $selectedYear, $searchParam, $searchParam]);
} else {
    $stmt->execute([$idAgent, $idAgent, $selectedYear]);
}

$sujets = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Statistiques de base (sujets et tâches)
$basicStatsQuery = "SELECT 
                     COUNT(DISTINCT s.idsujets) as total_sujets,
                     COUNT(DISTINCT t.idtaches) as total_taches,
                     SUM(CASE WHEN t.validation = 'Validé' THEN 1 ELSE 0 END) as taches_validees,
                     SUM(CASE WHEN t.validation = 'Rejeté' THEN 1 ELSE 0 END) as taches_rejetees,
                     SUM(CASE WHEN t.validation = 'En attente' OR t.validation IS NULL THEN 1 ELSE 0 END) as taches_en_attente,
                     AVG(t.pourcentage_avancement) as avancement_moyen
                   FROM sujets s
                   LEFT JOIN taches t ON s.idsujets = t.sujets_idsujets
                   WHERE (s.\"idDirecteur\" = ? OR s.\"idEncadreur\" = ?)
                   AND s.annee_acad_idannee_acad = ?";
$basicStatsStmt = $pdo->prepare($basicStatsQuery);
$basicStatsStmt->execute([$idAgent, $idAgent, $selectedYear]);
$stats = $basicStatsStmt->fetch(PDO::FETCH_ASSOC);

// Statistiques des échanges
$exchangeStatsQuery = "SELECT 
                        COUNT(e.idechange) as total_echanges,
                        COUNT(DISTINCT t.idtaches) as taches_avec_echanges
                      FROM sujets s
                      JOIN taches t ON s.idsujets = t.sujets_idsujets
                      JOIN echanges_taches e ON t.idtaches = e.taches_idtaches
                      WHERE (s.\"idDirecteur\" = ? OR s.\"idEncadreur\" = ?)
                      AND s.annee_acad_idannee_acad = ?";
$exchangeStatsStmt = $pdo->prepare($exchangeStatsQuery);
$exchangeStatsStmt->execute([$idAgent, $idAgent, $selectedYear]);
$exchangeStats = $exchangeStatsStmt->fetch(PDO::FETCH_ASSOC);

// Fusionner les résultats
$stats = array_merge($stats, $exchangeStats);

// Calculer le ratio d'échanges par tâche
$stats['echanges_par_tache'] = ($stats['taches_avec_echanges'] > 0) ? 
    round($stats['total_echanges'] / $stats['taches_avec_echanges'], 1) : 0;


// Récupérer les activités récentes (5 derniers échanges)
$recentActivityQuery = "SELECT 
                          e.idechange, e.\"dateEchange\", e.commentaire, e.type_auteur,
                          t.idtaches, t.description as tache_description,
                          s.idsujets, s.intitule as sujet_intitule,
                          CASE 
                            WHEN e.type_auteur = 'Etudiant' THEN et.noms
                            WHEN e.type_auteur = 'Directeur' OR e.type_auteur = 'Encadreur' THEN a.noms
                            ELSE 'Inconnu'
                          END as nom_auteur
                        FROM echanges_taches e
                        JOIN taches t ON e.taches_idtaches = t.idtaches
                        JOIN sujets s ON t.sujets_idsujets = s.idsujets
                        LEFT JOIN etudiant et ON (e.type_auteur = 'Etudiant' AND e.\"idAuteur\" = et.idetudiant)
                        LEFT JOIN agent a ON (e.type_auteur IN ('Directeur', 'Encadreur') AND e.\"idAuteur\" = a.\"idAgent\")
                        WHERE (s.\"idDirecteur\" = ? OR s.\"idEncadreur\" = ?)
                        AND s.annee_acad_idannee_acad = ?
                        ORDER BY e.\"dateEchange\" DESC
                        LIMIT 5";
$recentActivityStmt = $pdo->prepare($recentActivityQuery);
$recentActivityStmt->execute([$idAgent, $idAgent, $selectedYear]);
$recentActivities = $recentActivityStmt->fetchAll(PDO::FETCH_ASSOC);

?>



<main id="main" class="main">
    <!-- Toast pour les messages de notification -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
        <?php if (isset($_SESSION['success']) && !empty($_SESSION['success'])): ?>
            <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success'] ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error']) && !empty($_SESSION['error'])): ?>
            <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error'] ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>
    <div class="pagetitle">
        <h1>Supervision des Travaux</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                <li class="breadcrumb-item active">Travaux à superviser</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Filtre par année académique -->
        <div class="row mb-3">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Filtrer par année académique</h5>
                        
                        <form method="GET" action="" class="row g-3 align-items-center">
                            <input type="hidden" name="view" value="recherche/projet.taches">
                            <?php if (!empty($search)): ?>
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <?php endif; ?>
                            
                            <div class="col-md-4">
                                <select name="annee" id="annee" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= ($annee['idannee_acad'] == $selectedYear) ? 'selected' : '' ?>>
                                            <?= $annee['designation'] ?> <?= (isset($annee['est_active']) && $annee['est_active'] == 1) ? '(Année en cours)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>


        <!-- Tableau de bord des statistiques -->
<div class="row mb-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Tableau de Bord - <?= $anneeActive['designation'] ?></h5>
                
                <div class="row">
                    <!-- Cartes de statistiques -->
                    <div class="col-lg-8">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Travaux supervisés</h6>
                                        <div class="d-flex align-items-center mt-3">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-primary text-white me-3">
                                                <i class="bi bi-book"></i>
                                            </div>
                                            <h2 class="mb-0"><?= $stats['total_sujets'] ?? 0 ?></h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Tâches total</h6>
                                        <div class="d-flex align-items-center mt-3">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success text-white me-3">
                                                <i class="bi bi-list-check"></i>
                                            </div>
                                            <h2 class="mb-0"><?= $stats['total_taches'] ?? 0 ?></h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Échanges/discussions</h6>
                                        <div class="d-flex align-items-center mt-3">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info text-white me-3">
                                                <i class="bi bi-chat-dots"></i>
                                            </div>
                                            <h2 class="mb-0"><?= $stats['total_echanges'] ?? 0 ?></h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Statut des tâches</h6>
                                        <div class="mt-3">
                                            <div class="progress-stacked">
                                                <?php 
                                                $totalTaches = max(1, $stats['total_taches'] ?? 1); // éviter division par zéro
                                                $pctValidees = round(($stats['taches_validees'] ?? 0) * 100 / $totalTaches);
                                                $pctRejetees = round(($stats['taches_rejetees'] ?? 0) * 100 / $totalTaches);
                                                $pctEnAttente = round(($stats['taches_en_attente'] ?? 0) * 100 / $totalTaches);
                                                ?>
                                                <div class="progress" role="progressbar" aria-label="Validées" style="width: <?= $pctValidees ?>%" aria-valuenow="<?= $pctValidees ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar bg-success"></div>
                                                </div>
                                                <div class="progress" role="progressbar" aria-label="Rejetées" style="width: <?= $pctRejetees ?>%" aria-valuenow="<?= $pctRejetees ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar bg-danger"></div>
                                                </div>
                                                <div class="progress" role="progressbar" aria-label="En attente" style="width: <?= $pctEnAttente ?>%" aria-valuenow="<?= $pctEnAttente ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <div class="progress-bar bg-warning"></div>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-2">
                                                <small><span class="badge bg-success"><?= $stats['taches_validees'] ?? 0 ?> Validées</span></small>
                                                <small><span class="badge bg-danger"><?= $stats['taches_rejetees'] ?? 0 ?> Rejetées</span></small>
                                                <small><span class="badge bg-warning"><?= $stats['taches_en_attente'] ?? 0 ?> En attente</span></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Avancement moyen</h6>
                                        <div class="mt-3">
                                            <?php 
                                            $avgProgress = round($stats['avancement_moyen'] ?? 0);
                                            $progressClass = 'bg-danger';
                                            if ($avgProgress >= 75) {
                                                $progressClass = 'bg-success';
                                            } elseif ($avgProgress >= 50) {
                                                $progressClass = 'bg-info';
                                            } elseif ($avgProgress >= 25) {
                                                $progressClass = 'bg-warning';
                                            }
                                            ?>
                                            <div class="progress">
                                                <div class="progress-bar <?= $progressClass ?>" role="progressbar" style="width: <?= $avgProgress ?>%" aria-valuenow="<?= $avgProgress ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <?= $avgProgress ?>%
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-2">
                                                <small class="text-muted">Avancement moyen des projets</small>
                                                <small class="fw-bold"><?= $avgProgress ?>%</small>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">Moyenne d'échanges par tâche: </small>
                                                <small class="fw-bold"><?= $stats['echanges_par_tache'] ?? 0 ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Activités récentes -->
                    <div class="col-lg-4">
                        <div class="card bg-light h-100">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Activités récentes</h6>
                                <div class="activity-feed mt-3">
                                    <?php if (empty($recentActivities)): ?>
                                        <div class="text-center text-muted">
                                            <i class="bi bi-info-circle"></i> Aucune activité récente.
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentActivities as $activity): 
                                            $activityClass = '';
                                            $activityIcon = '';
                                            switch ($activity['type_auteur']) {
                                                case 'Directeur':
                                                    $activityClass = 'text-primary';
                                                    $activityIcon = 'bi-person-check';
                                                    break;
                                                case 'Encadreur':
                                                    $activityClass = 'text-success';
                                                    $activityIcon = 'bi-person-check-fill';
                                                    break;
                                                case 'Etudiant':
                                                    $activityClass = 'text-info';
                                                    $activityIcon = 'bi-person';
                                                                                                        break;
                                                default:
                                                    $activityClass = 'text-secondary';
                                                    $activityIcon = 'bi-person-x';
                                            }
                                            
                                            // Formater la date
                                            $date = new DateTime($activity['dateEchange']);
                                            $formattedDate = $date->format('d/m/Y H:i');
                                        ?>
                                            <div class="activity-item mb-3">
                                                <div class="d-flex">
                                                    <div class="me-2">
                                                        <i class="bi <?= $activityIcon ?> <?= $activityClass ?>"></i>
                                                    </div>
                                                    <div class="activity-content">
                                                        <strong class="<?= $activityClass ?>"><?= htmlspecialchars($activity['nom_auteur']) ?></strong>
                                                        <span class="text-muted"> (<?= $activity['type_auteur'] ?>)</span>
                                                        <div class="small text-muted"><?= $formattedDate ?> • Sujet #<?= $activity['idsujets'] ?></div>
                                                        <div class="mt-1 text-truncate" title="<?= htmlspecialchars($activity['commentaire']) ?>">
                                                            <?= mb_substr(htmlspecialchars($activity['commentaire']), 0, 70) ?><?= (mb_strlen($activity['commentaire']) > 70) ? '...' : '' ?>
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
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Fin du tableau de bord des statistiques -->





        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Travaux à Superviser - <?= $anneeActive['designation'] ?></h5>

                        <!-- Formulaire de recherche -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <form action="" method="GET" class="d-flex">
                                    <input type="hidden" name="view" value="recherche/projet.taches">
                                    <input type="hidden" name="annee" value="<?= $selectedYear ?>">
                                    <input type="text" name="search" class="form-control me-2" 
                                           placeholder="Rechercher un sujet ou un étudiant..." 
                                           value="<?= htmlspecialchars($search) ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php if (empty($sujets)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Aucun travail à superviser trouvé pour cette année académique.
                            </div>
                        <?php else: ?>
                            <!-- Liste des sujets et leurs tâches -->
                            <div class="accordion" id="accordionSujets">
                                <?php $numeroSujet = 1; ?>
                                <?php foreach ($sujets as $index => $sujet): 
                                    $role = ($sujet['idDirecteur'] == $idAgent) ? 'Directeur' : 'Encadreur';
                                    
                                    // Récupérer les tâches du sujet
                                    $queryTaches = "SELECT * FROM taches WHERE sujets_idsujets = ? ORDER BY \"dateTache\" DESC";
                                    $stmtTaches = $pdo->prepare($queryTaches);
                                    $stmtTaches->execute([$sujet['idsujets']]);
                                    $taches = $stmtTaches->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button <?= $index !== 0 ? 'collapsed' : '' ?>" 
                                                    type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#collapse<?= $sujet['idsujets'] ?>">
                                                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                    <span class="badge bg-secondary me-2"><?= $numeroSujet ?></span>
                                                    <span>
                                                        <strong>Sujet:</strong> <?= htmlspecialchars($sujet['intitule']) ?>
                                                    </span>
                                                    <span class="badge bg-primary ms-2">
                                                        <?= count($taches) ?> tâche(s)
                                                    </span>
                                                </div>
                                            </button>
                                        </h2>

                                        <div id="collapse<?= $sujet['idsujets'] ?>" 
                                             class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>">
                                            <div class="accordion-body">
                                                <!-- Informations du sujet et de l'étudiant -->
                                                <div class="mb-4">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <h6 class="fw-bold">Informations du sujet</h6>
                                                            <p><strong>Cycle:</strong> <?= $sujet['cycle'] ?></p>
                                                            <p><strong>Spécialisation:</strong> <?= $sujet['specialisation'] ?></p>
                                                            <p><strong>Votre rôle:</strong> 
                                                                <span class="badge bg-<?= strtolower($role) === 'directeur' ? 'primary' : 'success' ?>">
                                                                    <?= $role ?>
                                                                </span>
                                                            </p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <h6 class="fw-bold">Informations de l'étudiant</h6>
                                                            <?php if ($sujet['etudiant_idetudiant']): ?>
                                                                <p><strong>Nom:</strong> <?= htmlspecialchars($sujet['etudiant_nom']) ?></p>
                                                                <p><strong>Matricule:</strong> <?= htmlspecialchars($sujet['etudiant_matricule']) ?></p>
                                                                <p><strong>Email:</strong> <?= htmlspecialchars($sujet['etudiant_email']) ?></p>
                                                            <?php else: ?>
                                                                <p class="text-muted">Informations de l'étudiant non disponibles</p>
                                                            <?php endif; ?>
                                                            <p><strong>Année académique:</strong> <?= $sujet['annee'] ?></p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Section Plan de Travail -->
                                                <?php 
                                                // Récupérer le plan de travail du sujet
                                                $queryPlan = "SELECT pt.*, 
                                                              COUNT(cp.idchapitre_plan) as nb_chapitres,
                                                              AVG(cp.pourcentage_avancement) as avancement_plan
                                                              FROM plan_travail pt
                                                              LEFT JOIN chapitre_plan cp ON pt.idplan_travail = cp.idplan_travail
                                                              WHERE pt.idsujets = ?
                                                              GROUP BY pt.idplan_travail
                                                              ORDER BY pt.version DESC LIMIT 1";
                                                $stmtPlan = $pdo->prepare($queryPlan);
                                                $stmtPlan->execute([$sujet['idsujets']]);
                                                $planTravail = $stmtPlan->fetch(PDO::FETCH_ASSOC);
                                                ?>
                                                
                                                <div class="mb-4">
                                                    <h6 class="fw-bold"><i class="bi bi-file-text me-1"></i> Plan de Travail</h6>
                                                    
                                                    <?php if (!$planTravail): ?>
                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-triangle"></i> Aucun plan de travail soumis pour le moment.
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="card border-info">
                                                            <div class="card-header bg-light">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <h6 class="mb-0"><?= htmlspecialchars($planTravail['titre_plan']) ?></h6>
                                                                    <div>
                                                                        <?php 
                                                                        $badgeClassPlan = '';
                                                                        switch($planTravail['statut_validation']) {
                                                                            case 'Validé':
                                                                                $badgeClassPlan = 'bg-success';
                                                                                break;
                                                                            case 'Rejeté':
                                                                                $badgeClassPlan = 'bg-danger';
                                                                                break;
                                                                            case 'Modifié':
                                                                                $badgeClassPlan = 'bg-info';
                                                                                break;
                                                                            default:
                                                                                $badgeClassPlan = 'bg-warning';
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?= $badgeClassPlan ?>">
                                                                            <?= $planTravail['statut_validation'] ?>
                                                                        </span>
                                                                        <span class="badge bg-secondary ms-1">
                                                                            Version <?= $planTravail['version'] ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <p><strong>Problématique:</strong></p>
                                                                        <p class="text-muted"><?= nl2br(htmlspecialchars(substr($planTravail['problematique'], 0, 200))) ?>...</p>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <p><strong>Objectifs:</strong></p>
                                                                        <p class="text-muted"><?= nl2br(htmlspecialchars(substr($planTravail['objectifs'], 0, 200))) ?>...</p>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="row mt-3">
                                                                    <div class="col-md-6">
                                                                        <p><strong>Chapitres:</strong> <?= $planTravail['nb_chapitres'] ?? 0 ?></p>
                                                                        <p><strong>Avancement:</strong> <?= round($planTravail['avancement_plan'] ?? 0) ?>%</p>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <p><strong>Date de soumission:</strong> <?= date('d/m/Y H:i', strtotime($planTravail['date_soumission'])) ?></p>
                                                                        <?php if ($planTravail['date_validation']): ?>
                                                                            <p><strong>Date de validation:</strong> <?= date('d/m/Y H:i', strtotime($planTravail['date_validation'])) ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                
                                                                <?php if ($planTravail['commentaire_directeur']): ?>
                                                                    <div class="mt-3">
                                                                        <p><strong>Commentaire du directeur:</strong></p>
                                                                        <div class="alert alert-info">
                                                                            <?= nl2br(htmlspecialchars($planTravail['commentaire_directeur'])) ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Actions de validation du plan (si directeur et plan en attente) -->
                                                                <?php if ($role === 'Directeur' && $planTravail['statut_validation'] === 'En attente'): ?>
                                                                    <div class="mt-4 border-top pt-3">
                                                                        <h6>Validation du Plan de Travail</h6>
                                                                        <form action="controller/plan_travail_controller.php" method="POST">
                                                                            <input type="hidden" name="action" value="validate_plan">
                                                                            <input type="hidden" name="plan_id" value="<?= $planTravail['idplan_travail'] ?>">
                                                                            <input type="hidden" name="annee" value="<?= $selectedYear ?>">
                                                                            <input type="hidden" name="redirect" value="recherche/projet.taches">
                                                                            
                                                                            <div class="mb-3">
                                                                                <label for="commentaire_plan<?= $planTravail['idplan_travail'] ?>" class="form-label">Commentaire de validation</label>
                                                                                <textarea class="form-control" id="commentaire_plan<?= $planTravail['idplan_travail'] ?>" 
                                                                                          name="commentaire" rows="3" placeholder="Vos observations sur le plan de travail..."></textarea>
                                                                            </div>
                                                                            
                                                                            <div class="btn-group" role="group">
                                                                                <button type="submit" name="statut" value="Validé" 
                                                                                        class="btn btn-success me-2">
                                                                                    <i class="bi bi-check-circle"></i> Valider le Plan
                                                                                </button>
                                                                                <button type="submit" name="statut" value="Modifié" 
                                                                                        class="btn btn-warning me-2">
                                                                                    <i class="bi bi-pencil-square"></i> Demander Modification
                                                                                </button>
                                                                                <button type="submit" name="statut" value="Rejeté" 
                                                                                        class="btn btn-danger">
                                                                                    <i class="bi bi-x-circle"></i> Rejeter le Plan
                                                                                </button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Bouton pour voir les détails du plan -->
                                                                <div class="mt-3">
                                                                    <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                            data-bs-toggle="modal" 
                                                                            data-bs-target="#planDetailModal<?= $planTravail['idplan_travail'] ?>">
                                                                        <i class="bi bi-eye"></i> Voir les détails du plan
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Modal pour les détails du plan -->
                                                        <div class="modal fade" id="planDetailModal<?= $planTravail['idplan_travail'] ?>" 
                                                             tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog modal-xl">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title">Détails du Plan de Travail</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="row">
                                                                            <div class="col-md-12">
                                                                                <h6>Titre:</h6>
                                                                                <p><?= htmlspecialchars($planTravail['titre_plan']) ?></p>
                                                                                
                                                                                <h6>Introduction:</h6>
                                                                                <p><?= nl2br(htmlspecialchars($planTravail['introduction'])) ?></p>
                                                                                
                                                                                <h6>Problématique:</h6>
                                                                                <p><?= nl2br(htmlspecialchars($planTravail['problematique'])) ?></p>
                                                                                
                                                                                <h6>Objectifs:</h6>
                                                                                <p><?= nl2br(htmlspecialchars($planTravail['objectifs'])) ?></p>
                                                                                
                                                                                <h6>Méthodologie:</h6>
                                                                                <p><?= nl2br(htmlspecialchars($planTravail['methodologie'])) ?></p>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <!-- Chapitres du plan -->
                                                                        <?php 
                                                                        $queryChapitres = "SELECT * FROM chapitre_plan WHERE idplan_travail = ? ORDER BY ordre_affichage, numero_chapitre";
                                                                        $stmtChapitres = $pdo->prepare($queryChapitres);
                                                                        $stmtChapitres->execute([$planTravail['idplan_travail']]);
                                                                        $chapitres = $stmtChapitres->fetchAll(PDO::FETCH_ASSOC);
                                                                        ?>
                                                                        
                                                                        <?php if (!empty($chapitres)): ?>
                                                                            <h6 class="mt-4">Structure du Plan:</h6>
                                                                            <div class="list-group">
                                                                                <?php foreach ($chapitres as $chapitre): ?>
                                                                                    <div class="list-group-item">
                                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                                            <div>
                                                                                                <h6 class="mb-1">Chapitre <?= $chapitre['numero_chapitre'] ?>: <?= htmlspecialchars($chapitre['titre_chapitre']) ?></h6>
                                                                                                <p class="mb-1 text-muted"><?= htmlspecialchars($chapitre['description']) ?></p>
                                                                                            </div>
                                                                                            <div class="text-end">
                                                                                                <span class="badge bg-<?= $chapitre['statut'] === 'Terminé' ? 'success' : ($chapitre['statut'] === 'En cours' ? 'warning' : 'secondary') ?>">
                                                                                                    <?= $chapitre['statut'] ?>
                                                                                                </span>
                                                                                                <div class="small text-muted"><?= $chapitre['pourcentage_avancement'] ?>%</div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Section Chapitres Soumis -->
                                                <?php if ($planTravail && $planTravail['statut_validation'] === 'Validé'): ?>
                                                    <?php 
                                                    // Récupérer les chapitres soumis par l'étudiant
                                                    $queryChapitresSoumis = "SELECT cp.*, 
                                                                             COUNT(ec.idechange_chapitre) as nb_echanges
                                                                             FROM chapitre_plan cp
                                                                             LEFT JOIN echange_chapitre ec ON cp.idchapitre_plan = ec.idchapitre_plan
                                                                             WHERE cp.idplan_travail = ? AND cp.date_soumission IS NOT NULL
                                                                             GROUP BY cp.idchapitre_plan
                                                                             ORDER BY cp.ordre_affichage, cp.numero_chapitre";
                                                    $stmtChapitresSoumis = $pdo->prepare($queryChapitresSoumis);
                                                    $stmtChapitresSoumis->execute([$planTravail['idplan_travail']]);
                                                    $chapitresSoumis = $stmtChapitresSoumis->fetchAll(PDO::FETCH_ASSOC);
                                                    ?>
                                                    
                                                    <?php if (!empty($chapitresSoumis)): ?>
                                                        <div class="mb-4">
                                                            <h6 class="fw-bold"><i class="bi bi-file-earmark-text me-1"></i> Chapitres Soumis</h6>
                                                            
                                                            <?php foreach ($chapitresSoumis as $chapitre): ?>
                                                                <div class="card mb-3 border-primary">
                                                                    <div class="card-header bg-light">
                                                                        <div class="d-flex justify-content-between align-items-center">
                                                                            <h6 class="mb-0">
                                                                                Chapitre <?= $chapitre['numero_chapitre'] ?>: <?= htmlspecialchars($chapitre['titre_chapitre']) ?>
                                                                            </h6>
                                                                            <div>
                                                                                <?php 
                                                                                $badgeClassChapitre = '';
                                                                                switch($chapitre['statut']) {
                                                                                    case 'Terminé':
                                                                                        $badgeClassChapitre = 'bg-success';
                                                                                        break;
                                                                                    case 'En révision':
                                                                                        $badgeClassChapitre = 'bg-warning';
                                                                                        break;
                                                                                    case 'En cours':
                                                                                        $badgeClassChapitre = 'bg-info';
                                                                                        break;
                                                                                    default:
                                                                                        $badgeClassChapitre = 'bg-secondary';
                                                                                }
                                                                                ?>
                                                                                <span class="badge <?= $badgeClassChapitre ?>">
                                                                                    <?= $chapitre['statut'] ?>
                                                                                </span>
                                                                                <span class="badge bg-info ms-1">
                                                                                    <?= $chapitre['pourcentage_avancement'] ?>%
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="card-body">
                                                                        <div class="row">
                                                                            <div class="col-md-8">
                                                                                <p><strong>Description:</strong></p>
                                                                                <p class="text-muted"><?= nl2br(htmlspecialchars($chapitre['description'])) ?></p>
                                                                                
                                                                                <?php if ($chapitre['objectifs_chapitre']): ?>
                                                                                    <p><strong>Objectifs:</strong></p>
                                                                                    <p class="text-muted"><?= nl2br(htmlspecialchars($chapitre['objectifs_chapitre'])) ?></p>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            <div class="col-md-4">
                                                                                <p><strong>Date de soumission:</strong> <?= date('d/m/Y H:i', strtotime($chapitre['date_soumission'])) ?></p>
                                                                                <?php if ($chapitre['deadline']): ?>
                                                                                    <p><strong>Deadline:</strong> <?= date('d/m/Y', strtotime($chapitre['deadline'])) ?></p>
                                                                                <?php endif; ?>
                                                                                <p><strong>Échanges:</strong> <?= $chapitre['nb_echanges'] ?></p>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <?php if ($chapitre['fichier_chapitre']): ?>
                                                                            <div class="mt-2">
                                                                                <p><strong>Fichier soumis:</strong></p>
                                                                                <a href="uploads/chapitres/<?= $chapitre['fichier_chapitre'] ?>" 
                                                                                   class="btn btn-sm btn-outline-primary" 
                                                                                   target="_blank">
                                                                                    <i class="bi bi-download"></i> Télécharger le chapitre
                                                                                </a>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        
                                                                        <?php if ($chapitre['commentaire_directeur']): ?>
                                                                            <div class="mt-3">
                                                                                <p><strong>Commentaire du directeur:</strong></p>
                                                                                <div class="alert alert-info">
                                                                                    <?= nl2br(htmlspecialchars($chapitre['commentaire_directeur'])) ?>
                                                                                </div>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        
                                                                        <!-- Échanges sur le chapitre -->
                                                                        <?php 
                                                                        $queryEchangesChapitre = "SELECT ec.*, 
                                                                                                   CASE 
                                                                                                     WHEN ec.type_auteur = 'Etudiant' THEN et.noms
                                                                                                     WHEN ec.type_auteur IN ('Directeur', 'Encadreur') THEN a.noms
                                                                                                     ELSE 'Inconnu'
                                                                                                   END as nom_auteur
                                                                                                   FROM echange_chapitre ec
                                                                                                   LEFT JOIN etudiant et ON (ec.type_auteur = 'Etudiant' AND ec.\"idAuteur\" = et.idetudiant)
                                                                                                   LEFT JOIN agent a ON (ec.type_auteur IN ('Directeur', 'Encadreur') AND ec.\"idAuteur\" = a.\"idAgent\")
                                                                                                   WHERE ec.idchapitre_plan = ?
                                                                                                   ORDER BY ec.date_echange ASC";
                                                                        $stmtEchangesChapitre = $pdo->prepare($queryEchangesChapitre);
                                                                        $stmtEchangesChapitre->execute([$chapitre['idchapitre_plan']]);
                                                                        $echangesChapitre = $stmtEchangesChapitre->fetchAll(PDO::FETCH_ASSOC);
                                                                        ?>
                                                                        
                                                                        <div class="mt-4">
                                                                            <h6><i class="bi bi-chat-text me-1"></i> Échanges sur le chapitre (<?= count($echangesChapitre) ?>)</h6>
                                                                            
                                                                            <?php if (!empty($echangesChapitre)): ?>
                                                                                <div class="comments-list mb-3">
                                                                                    <?php foreach ($echangesChapitre as $echange): ?>
                                                                                        <div class="comment-item p-3 mb-2 bg-light rounded">
                                                                                            <div class="d-flex align-items-center mb-2">
                                                                                                <div class="me-2">
                                                                                                    <i class="bi bi-person-circle"></i>
                                                                                                </div>
                                                                                                <div>
                                                                                                    <div class="fw-bold"><?= htmlspecialchars($echange['nom_auteur']) ?> (<?= $echange['type_auteur'] ?>)</div>
                                                                                                    <div class="text-muted small">
                                                                                                        <?= date('d/m/Y H:i', strtotime($echange['date_echange'])) ?>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="comment-content">
                                                                                                <p><?= nl2br(htmlspecialchars($echange['commentaire'])) ?></p>
                                                                                                
                                                                                                <?php if ($echange['fichier_joint']): ?>
                                                                                                    <div class="mt-2">
                                                                                                        <a href="uploads/echanges_chapitres/<?= $echange['fichier_joint'] ?>" 
                                                                                                           class="btn btn-sm btn-outline-secondary" 
                                                                                                           target="_blank">
                                                                                                            <i class="bi bi-paperclip"></i> Pièce jointe
                                                                                                        </a>
                                                                                                    </div>
                                                                                                <?php endif; ?>
                                                                                            </div>
                                                                                        </div>
                                                                                    <?php endforeach; ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                            
                                                                            <!-- Formulaire d'ajout de commentaire sur le chapitre -->
                                                                            <form action="controller/chapitre_controller.php" method="POST" enctype="multipart/form-data">
                                                                                <input type="hidden" name="action" value="add_comment_chapitre">
                                                                                <input type="hidden" name="chapitre_id" value="<?= $chapitre['idchapitre_plan'] ?>">
                                                                                <input type="hidden" name="type_auteur" value="<?= $role ?>">
                                                                                <input type="hidden" name="id_auteur" value="<?= $idAgent ?>">
                                                                                <input type="hidden" name="annee" value="<?= $selectedYear ?>">
                                                                                <input type="hidden" name="redirect" value="recherche/projet.taches">
                                                                                
                                                                                <div class="form-group mb-2">
                                                                                    <label for="commentaire_chapitre<?= $chapitre['idchapitre_plan'] ?>" class="form-label">Ajouter un commentaire</label>
                                                                                    <textarea class="form-control" id="commentaire_chapitre<?= $chapitre['idchapitre_plan'] ?>" 
                                                                                              name="commentaire" rows="3" required></textarea>
                                                                                </div>
                                                                                
                                                                                <div class="form-group mb-3">
                                                                                    <label for="fichier_chapitre<?= $chapitre['idchapitre_plan'] ?>" class="form-label">Joindre un fichier (facultatif)</label>
                                                                                    <input type="file" class="form-control" id="fichier_chapitre<?= $chapitre['idchapitre_plan'] ?>" name="fichier">
                                                                                </div>
                                                                                
                                                                                <button type="submit" class="btn btn-primary btn-sm">
                                                                                    <i class="bi bi-chat-dots"></i> Envoyer le commentaire
                                                                                </button>
                                                                            </form>
                                                                        </div>
                                                                        
                                                                        <!-- Validation du chapitre (si directeur ou encadreur) -->
                                                                        <?php if ($chapitre['statut'] !== 'Terminé'): ?>
                                                                            <div class="mt-4 border-top pt-3">
                                                                                <h6>Validation du Chapitre</h6>
                                                                                <form action="controller/chapitre_controller.php" method="POST">
                                                                                    <input type="hidden" name="action" value="validate_chapitre">
                                                                                    <input type="hidden" name="chapitre_id" value="<?= $chapitre['idchapitre_plan'] ?>">
                                                                                    <input type="hidden" name="annee" value="<?= $selectedYear ?>">
                                                                                    <input type="hidden" name="redirect" value="recherche/projet.taches">
                                                                                    
                                                                                    <div class="row mb-3">
                                                                                        <div class="col-md-6">
                                                                                            <label for="pourcentage_chapitre<?= $chapitre['idchapitre_plan'] ?>" class="form-label">Pourcentage d'avancement</label>
                                                                                            <input type="range" class="form-range" id="pourcentage_chapitre<?= $chapitre['idchapitre_plan'] ?>" 
                                                                                                   name="pourcentage" min="0" max="100" step="5" 
                                                                                                   value="<?= $chapitre['pourcentage_avancement'] ?>"
                                                                                                   oninput="document.getElementById('pourcentageChapitreValue<?= $chapitre['idchapitre_plan'] ?>').textContent = this.value + '%'">
                                                                                            <div class="text-center">
                                                                                                <span id="pourcentageChapitreValue<?= $chapitre['idchapitre_plan'] ?>"><?= $chapitre['pourcentage_avancement'] ?>%</span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label for="commentaire_validation_chapitre<?= $chapitre['idchapitre_plan'] ?>" class="form-label">Commentaire de validation</label>
                                                                                            <textarea class="form-control" id="commentaire_validation_chapitre<?= $chapitre['idchapitre_plan'] ?>" 
                                                                                                      name="commentaire" rows="3"></textarea>
                                                                                        </div>
                                                                                    </div>
                                                                                    
                                                                                    <div class="btn-group" role="group">
                                                                                        <button type="submit" name="statut" value="Terminé" 
                                                                                                class="btn btn-success me-2">
                                                                                            <i class="bi bi-check-circle"></i> Valider le Chapitre
                                                                                        </button>
                                                                                        <button type="submit" name="statut" value="En révision" 
                                                                                                class="btn btn-warning">
                                                                                            <i class="bi bi-pencil-square"></i> Demander Révision
                                                                                        </button>
                                                                                    </div>
                                                                                </form>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <!-- Liste des tâches -->
                                                <?php if (empty($taches)): ?>
                                                    <div class="alert alert-info">
                                                        Aucune tâche soumise pour le moment.
                                                    </div>
                                                <?php else: ?>
                                                    <?php foreach ($taches as $tache): 
                                                        // Récupérer les échanges de la tâche
                                                        $queryEchanges = "SELECT * FROM echanges_taches
                                                                          WHERE taches_idtaches = ?
                                                                          ORDER BY \"dateEchange\" ASC";
                                                        $stmtEchanges = $pdo->prepare($queryEchanges);
                                                        $stmtEchanges->execute([$tache['idtaches']]);
                                                        $echanges = $stmtEchanges->fetchAll(PDO::FETCH_ASSOC);
                                                    ?>
                                                        <div class="card mb-3">
                                                            <div class="card-body">
                                                                                                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                                    <h5 class="card-title mb-0">
                                                                        <i class="bi bi-file-earmark-text me-1"></i> 
                                                                        Tâche du <?= date('d/m/Y', strtotime($tache['dateTache'])) ?>
                                                                    </h5>
                                                                    <div>
                                                                        <?php 
                                                                        $badgeClass = '';
                                                                        switch($tache['validation']) {
                                                                            case 'Validé':
                                                                                $badgeClass = 'bg-success';
                                                                                break;
                                                                            case 'Rejeté':
                                                                                $badgeClass = 'bg-danger';
                                                                                break;
                                                                            default:
                                                                                $badgeClass = 'bg-warning';
                                                                        }
                                                                        ?>
                                                                        <span class="badge <?= $badgeClass ?>">
                                                                            <?= $tache['validation'] ?: 'En attente' ?>
                                                                        </span>
                                                                        <span class="badge bg-info ms-1">
                                                                            <?= $tache['pourcentage_avancement'] ?>% complété
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                <div class="row">
                                                                    <div class="col-md-12">
                                                                        <div class="card-text">
                                                                            <p><strong>Description:</strong></p>
                                                                            <p><?= nl2br(htmlspecialchars($tache['description'])) ?></p>
                                                                        </div>
                                                                        
                                                                        <?php if ($tache['fichierTache']): ?>
                                                                            <div class="mt-2">
                                                                                <p><strong>Fichier joint:</strong></p>
                                                                                <a href="uploads/taches/<?= $tache['fichierTache'] ?>" 
                                                                                   class="btn btn-sm btn-outline-primary" 
                                                                                   target="_blank">
                                                                                    <i class="bi bi-download"></i> Télécharger le fichier
                                                                                </a>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>

                                                                <!-- Historique des échanges -->
                                                                <div class="mt-4">
                                                                    <h6><i class="bi bi-chat-text me-1"></i> Échanges (<?= count($echanges) ?>)</h6>
                                                                    
                                                                    <div class="comments-section">
                                                                        <?php if (empty($echanges)): ?>
                                                                            <p class="text-muted">Aucun échange pour le moment.</p>
                                                                        <?php else: ?>
                                                                            <div class="comments-list">
                                                                                <?php foreach ($echanges as $echange): 
                                                                                    // Déterminer la classe CSS selon le type d'auteur
                                                                                    $commentClass = '';
                                                                                    $avatarClass = '';
                                                                                    switch ($echange['type_auteur']) {
                                                                                        case 'Directeur':
                                                                                            $commentClass = 'bg-light-primary';
                                                                                            $avatarClass = 'bg-primary';
                                                                                            break;
                                                                                        case 'Encadreur':
                                                                                            $commentClass = 'bg-light-success';
                                                                                            $avatarClass = 'bg-success';
                                                                                            break;
                                                                                        case 'Etudiant':
                                                                                            $commentClass = 'bg-light-info';
                                                                                            $avatarClass = 'bg-info';
                                                                                            break;
                                                                                    }
                                                                                ?>
                                                                                    <div class="comment-item p-3 mb-2 <?= $commentClass ?> rounded">
                                                                                        <div class="d-flex align-items-center mb-2">
                                                                                            <div class="<?= $avatarClass ?> text-white d-flex align-items-center justify-content-center rounded-circle me-2" style="width: 32px; height: 32px;">
                                                                                                <i class="bi bi-person"></i>
                                                                                            </div>
                                                                                            <div>
                                                                                                <div class="fw-bold"><?= $echange['type_auteur'] ?></div>
                                                                                                <div class="text-muted small">
                                                                                                    <?= date('d/m/Y H:i', strtotime($echange['dateEchange'])) ?>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="comment-content">
                                                                                            <p><?= nl2br(htmlspecialchars($echange['commentaire'])) ?></p>
                                                                                            
                                                                                            <?php if ($echange['fichierJoint']): ?>
                                                                                                <div class="mt-2">
                                                                                                    <a href="uploads/echanges/<?= $echange['fichierJoint'] ?>" 
                                                                                                       class="btn btn-sm btn-outline-secondary" 
                                                                                                       target="_blank">
                                                                                                        <i class="bi bi-paperclip"></i> Pièce jointe
                                                                                                    </a>
                                                                                                </div>
                                                                                            <?php endif; ?>
                                                                                        </div>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>

                                                                <!-- Formulaire d'ajout de commentaire -->
                                                                <div class="mt-3">
                                                                    <form action="controller/echange_controller.php" method="POST" enctype="multipart/form-data">
                                                                        <input type="hidden" name="action" value="add_comment">
                                                                        <input type="hidden" name="tache_id" value="<?= $tache['idtaches'] ?>">
                                                                        <input type="hidden" name="type_auteur" value="<?= $role ?>">
                                                                        <input type="hidden" name="id_auteur" value="<?= $idAgent ?>">
                                                                        <input type="hidden" name="annee" value="<?= $selectedYear ?>">
                                                                        <input type="hidden" name="redirect" value="recherche/projet.taches">
                                                                        
                                                                        <div class="form-group mb-2">
                                                                            <label for="commentaire<?= $tache['idtaches'] ?>" class="form-label">Ajouter un commentaire</label>
                                                                            <textarea class="form-control" id="commentaire<?= $tache['idtaches'] ?>" 
                                                                                      name="commentaire" rows="3" required></textarea>
                                                                        </div>
                                                                        
                                                                        <div class="form-group mb-3">
                                                                            <label for="fichier<?= $tache['idtaches'] ?>" class="form-label">Joindre un fichier (facultatif)</label>
                                                                            <input type="file" class="form-control" id="fichier<?= $tache['idtaches'] ?>" name="fichier">
                                                                        </div>
                                                                        
                                                                        <button type="submit" class="btn btn-primary">
                                                                            <i class="bi bi-chat-dots"></i> Envoyer le commentaire
                                                                        </button>
                                                                    </form>
                                                                </div>

                                                                <!-- Options de validation (si la tâche est en attente) -->
                                                                <?php if (!$tache['validation'] || $tache['validation'] == 'En attente'): ?>
                                                                    <div class="mt-4 border-top pt-3">
                                                                        <h6>Validation de la tâche</h6>
                                                                        <form action="controller/tache_controller.php" method="POST">
                                                                            <input type="hidden" name="action" value="validate_task">
                                                                            <input type="hidden" name="tache_id" value="<?= $tache['idtaches'] ?>">
                                                                            <input type="hidden" name="annee" value="<?= $selectedYear ?>">
                                                                            <input type="hidden" name="redirect" value="recherche/projet.taches">
                                                                            
                                                                            <div class="row mb-3">
                                                                                <div class="col-md-6">
                                                                                    <label for="pourcentage<?= $tache['idtaches'] ?>" class="form-label">Pourcentage d'avancement</label>
                                                                                    <input type="range" class="form-range" id="pourcentage<?= $tache['idtaches'] ?>" 
                                                                                           name="pourcentage" min="0" max="100" step="5" 
                                                                                           value="<?= $tache['pourcentage_avancement'] ?>"
                                                                                           oninput="document.getElementById('pourcentageValue<?= $tache['idtaches'] ?>').textContent = this.value + '%'">
                                                                                    <div class="text-center">
                                                                                        <span id="pourcentageValue<?= $tache['idtaches'] ?>"><?= $tache['pourcentage_avancement'] ?>%</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-md-6">
                                                                                    <label for="commentaire_validation<?= $tache['idtaches'] ?>" class="form-label">Commentaire de validation</label>
                                                                                    <textarea class="form-control" id="commentaire_validation<?= $tache['idtaches'] ?>" 
                                                                                              name="commentaire" rows="3"></textarea>
                                                                                </div>
                                                                            </div>
                                                                            
                                                                            <div class="btn-group" role="group">
                                                                                <button type="submit" name="validation" value="Validé" 
                                                                                        class="btn btn-success me-2">
                                                                                    <i class="bi bi-check-circle"></i> Valider la tâche
                                                                                </button>
                                                                                <button type="submit" name="validation" value="Rejeté" 
                                                                                        class="btn btn-danger">
                                                                                    <i class="bi bi-x-circle"></i> Rejeter la tâche
                                                                                </button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>

                                                <!-- Bouton pour ajouter une nouvelle tâche (si l'enseignant est directeur) -->
                                                <?php if ($sujet['idDirecteur'] == $idAgent): ?>
                                                    <div class="text-center mt-3">
                                                        <button type="button" class="btn btn-primary btn-add-task" 
                                                                data-sujet-id="<?= $sujet['idsujets'] ?>"
                                                                data-sujet-intitule="<?= htmlspecialchars($sujet['intitule'], ENT_QUOTES) ?>">
                                                            <i class="bi bi-plus-circle"></i> Ajouter une nouvelle tâche
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php $numeroSujet++; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal pour afficher les détails d'une tâche -->
        <div class="modal fade" id="taskDetailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Détails de la tâche</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="taskDetailContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <p>Chargement des détails...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal unique pour ajouter une tâche (en dehors de la boucle) -->
<div class="modal fade" id="addTaskModalUnique" tabindex="-1" aria-labelledby="addTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTaskModalLabel">Nouvelle tâche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addTaskForm" action="controller/tache_controller.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_task">
                    <input type="hidden" name="sujet_id" id="modal_sujet_id" value="">
                    <input type="hidden" name="annee" value="<?= $selectedYear ?>">
                    <input type="hidden" name="redirect" value="recherche/projet.taches">
                    
                    <div class="mb-3">
                        <label for="date_tache" class="form-label">Date de la tâche</label>
                        <input type="date" class="form-control" id="date_tache" name="date_tache" required value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description de la tâche</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fichier" class="form-label">Fichier joint (facultatif)</label>
                        <input type="file" class="form-control" id="fichier" name="fichier">
                        <div class="form-text">Format accepté: PDF, DOC, DOCX, XLS, XLSX (max 10MB)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="pourcentage_initial" class="form-label">
                            Pourcentage d'avancement initial: <span id="pourcentageInitialValue">0%</span>
                        </label>
                        <input type="range" class="form-range" id="pourcentage_initial" 
                               name="pourcentage" min="0" max="100" step="5" value="0"
                               oninput="document.getElementById('pourcentageInitialValue').textContent = this.value + '%'">
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer la tâche
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Attendre que le DOM soit chargé
document.addEventListener('DOMContentLoaded', function() {
    // Attacher les événements aux boutons d'ajout de tâche
    const addTaskButtons = document.querySelectorAll('.btn-add-task');
    
    addTaskButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Récupérer les données depuis les attributs data
            const sujetId = this.getAttribute('data-sujet-id');
            const sujetIntitule = this.getAttribute('data-sujet-intitule');
            
            // Mettre à jour les valeurs du modal
            document.getElementById('modal_sujet_id').value = sujetId;
            document.getElementById('addTaskModalLabel').textContent = 'Nouvelle tâche pour: ' + sujetIntitule;
            
            // Réinitialiser le formulaire
            document.getElementById('addTaskForm').reset();
            document.getElementById('modal_sujet_id').value = sujetId; // Remettre l'ID après reset
            document.getElementById('pourcentageInitialValue').textContent = '0%';
            document.getElementById('date_tache').value = new Date().toISOString().split('T')[0];
            
            // Fermer tous les modals existants
            const existingModals = document.querySelectorAll('.modal.show');
            existingModals.forEach(m => {
                const modalInstance = bootstrap.Modal.getInstance(m);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
            
            // Nettoyer les backdrops
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Réinitialiser le body
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            
            // Ouvrir le modal après un court délai
            setTimeout(() => {
                const modal = new bootstrap.Modal(document.getElementById('addTaskModalUnique'), {
                    backdrop: 'static',
                    keyboard: true
                });
                modal.show();
            }, 100);
        });
    });
    
    // Nettoyer lors de la fermeture du modal
    const addTaskModal = document.getElementById('addTaskModalUnique');
    if (addTaskModal) {
        addTaskModal.addEventListener('hidden.bs.modal', function () {
            // Supprimer les backdrops résiduels
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Réinitialiser le body
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');
        });
    }
});
    document.addEventListener('DOMContentLoaded', function() {
        // Style pour mettre en évidence l'année académique active
        const anneeSelect = document.getElementById('annee');
        if (anneeSelect) {
            Array.from(anneeSelect.options).forEach(option => {
                if (option.text.includes('(Année en cours)')) {
                    option.classList.add('fw-bold', 'text-success');
                }
            });
        }
        
        // Style pour les éléments de l'accordéon
        const accordionItems = document.querySelectorAll('.accordion-item');
        accordionItems.forEach(item => {
            const header = item.querySelector('.accordion-header');
            const collapse = item.querySelector('.accordion-collapse');
            
            // Ajouter un écouteur d'événements sur le header
            header.addEventListener('click', function() {
                // Supprimer la classe 'just-opened' de tous les éléments
                document.querySelectorAll('.accordion-collapse.just-opened').forEach(el => {
                    el.classList.remove('just-opened');
                });
                
                // Si l'élément vient d'être ouvert, ajouter la classe 'just-opened'
                if (!collapse.classList.contains('show')) {
                    collapse.classList.add('just-opened');
                    
                    // Scroll vers l'élément après un court délai
                    setTimeout(() => {
                        collapse.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 350);
                }
            });
        });
        
        // Vérification de la taille des fichiers avant soumission
        const forms = document.querySelectorAll('form[enctype="multipart/form-data"]');
        forms.forEach(form => {
            form.addEventListener('submit', function(event) {
                const fileInputs = form.querySelectorAll('input[type="file"]');
                
                fileInputs.forEach(input => {
                    if (input.files.length > 0) {
                        const file = input.files[0];
                        const maxSize = 10 * 1024 * 1024; // 10MB
                        
                        if (file.size > maxSize) {
                            event.preventDefault();
                            alert('Le fichier ' + file.name + ' est trop volumineux. La taille maximale autorisée est de 10MB.');
                        }
                        
                        // Vérification des extensions
                        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                        const extension = file.name.split('.').pop().toLowerCase();
                        const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
                        
                        if (!allowedExtensions.includes(extension) && !allowedTypes.includes(file.type)) {
                            event.preventDefault();
                            alert('Format de fichier non supporté. Formats acceptés: PDF, DOC, DOCX, XLS, XLSX');
                        }
                    }
                });
            });
        });
        
        // Si l'URL contient un paramètre de hash, ouvrir l'accordéon correspondant
        if (window.location.hash) {
            const sujetId = window.location.hash.substring(1);
            const accordionButton = document.querySelector(`button[data-bs-target="#collapse${sujetId}"]`);
            
            if (accordionButton) {
                // Simuler un clic sur le bouton de l'accordéon
                setTimeout(() => {
                    accordionButton.click();
                }, 500);
            }
        }
    });
    
    // Fonction pour voir les détails d'une tâche
    function viewTaskDetails(taskId) {
        const modal = new bootstrap.Modal(document.getElementById('taskDetailModal'));
        modal.show();
        
        // Charger les détails de la tâche par AJAX
        fetch(`controller/get_task_details.php?id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    document.getElementById('taskDetailContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> ${data.error}
                        </div>
                    `;
                    return;
                }
                
                // Construire le contenu HTML avec les détails de la tâche
                let html = `
                    <div class="row">
                        <div class="col-md-12">
                            <h5>${data.sujet_intitule}</h5>
                            <p class="text-muted">Tâche du ${data.date_tache_formatee}</p>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <h6>Description:</h6>
                            <p>${data.description}</p>
                        </div>
                    </div>
                `;
                
                if (data.fichier) {
                    html += `
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <h6>Fichier joint:</h6>
                                <a href="uploads/taches/${data.fichier}" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bi bi-download"></i> Télécharger le fichier
                                </a>
                            </div>
                        </div>
                    `;
                }
                
                // Afficher les commentaires
                if (data.echanges && data.echanges.length > 0) {
                    html += `
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h6>Commentaires:</h6>
                                <div class="comments-list">
                    `;
                    
                    data.echanges.forEach(echange => {
                        let commentClass = '';
                        switch (echange.type_auteur) {
                            case 'Directeur': commentClass = 'bg-light-primary'; break;
                            case 'Encadreur': commentClass = 'bg-light-success'; break;
                            case 'Etudiant': commentClass = 'bg-light-info'; break;
                        }
                        
                        html += `
                            <div class="comment-item p-3 mb-2 ${commentClass} rounded">
                                <div class="d-flex justify-content-between mb-1">
                                    <div><strong>${echange.type_auteur}</strong></div>
                                    <div class="text-muted small">${echange.date_echange}</div>
                                </div>
                                <p>${echange.commentaire}</p>
                                ${echange.fichier ? `
                                <a href="uploads/echanges/${echange.fichier}" class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="bi bi-paperclip"></i> Pièce jointe
                                </a>` : ''}
                            </div>
                        `;
                    });
                    
                    html += `
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('taskDetailContent').innerHTML = html;
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('taskDetailContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Une erreur est survenue lors du chargement des détails.
                    </div>
                `;
            });
    }

        // Affichage automatique des toasts
    document.addEventListener('DOMContentLoaded', function() {
        const successToast = document.getElementById('successToast');
        const errorToast = document.getElementById('errorToast');
        
        if (successToast) {
            const toast = new bootstrap.Toast(successToast, {
                autohide: true,
                delay: 5000
            });
            toast.show();
        }
        
        if (errorToast) {
            const toast = new bootstrap.Toast(errorToast, {
                autohide: true,
                delay: 7000
            });
            toast.show();
        }
    });

</script>

<style>
    /* ===== VARIABLES CSS ===== */
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        --card-hover-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        --transition-speed: 0.3s;
    }

    /* ===== CARTES AMÉLIORÉES ===== */
    .card {
        border: none;
        border-radius: 15px;
        box-shadow: var(--card-shadow);
        transition: all var(--transition-speed) ease;
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover-shadow);
    }

    .card-header {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-bottom: none;
        padding: 1.25rem;
    }

    /* ===== STATISTIQUES CARDS ===== */
    .stat-card {
        position: relative;
        overflow: hidden;
        border-radius: 15px;
        background: white;
        padding: 1.5rem;
        transition: all var(--transition-speed) ease;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--primary-gradient);
    }

    .stat-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
    }

    .stat-card .card-icon {
        width: 60px;
        height: 60px;
        font-size: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        background: var(--primary-gradient);
        color: white;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        transition: all var(--transition-speed) ease;
    }

    .stat-card:hover .card-icon {
        transform: rotate(10deg) scale(1.1);
    }

    /* ===== ACCORDÉON MODERNE ===== */
    .accordion-item {
        border: none;
        margin-bottom: 1rem;
        border-radius: 12px !important;
        overflow: hidden;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        transition: all var(--transition-speed) ease;
    }

    .accordion-item:hover {
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
    }

    .accordion-button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 500;
        padding: 1.25rem;
        border: none;
        box-shadow: none !important;
        transition: all var(--transition-speed) ease;
    }

    .accordion-button:not(.collapsed) {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        color: white;
    }

    .accordion-button::after {
        filter: brightness(0) invert(1);
    }

    .accordion-body {
        padding: 2rem;
        background: linear-gradient(135deg, #fafafa 0%, #ffffff 100%);
    }

    /* ===== BADGES AMÉLIORÉS ===== */
    .badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .badge.bg-primary {
        background: var(--primary-gradient) !important;
    }

    .badge.bg-success {
        background: var(--success-gradient) !important;
    }

    .badge.bg-warning {
        background: var(--warning-gradient) !important;
        color: white !important;
    }

    .badge.bg-info {
        background: var(--info-gradient) !important;
    }

    /* ===== BOUTONS AMÉLIORÉS ===== */
    .btn {
        border-radius: 25px;
        padding: 0.5rem 1.5rem;
        font-weight: 500;
        transition: all var(--transition-speed) ease;
        border: none;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: white;
    }

    .btn-success {
        background: var(--success-gradient);
        color: white;
    }

    .btn-warning {
        background: var(--warning-gradient);
        color: white;
    }

    .btn-group .btn {
        border-radius: 25px;
        margin: 0 0.25rem;
    }

    /* ===== PROGRESS BARS AMÉLIORÉES ===== */
    .progress {
        height: 25px;
        border-radius: 15px;
        background: #f0f0f0;
        overflow: hidden;
        box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .progress-bar {
        background: var(--primary-gradient);
        border-radius: 15px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: width 0.6s ease;
    }

    .progress-bar.bg-success {
        background: var(--success-gradient);
    }

    .progress-bar.bg-warning {
        background: var(--warning-gradient);
    }

    .progress-bar.bg-info {
        background: var(--info-gradient);
    }

    /* ===== FORMULAIRES AMÉLIORÉS ===== */
    .form-control, .form-select {
        border-radius: 10px;
        border: 2px solid #e0e0e0;
        padding: 0.75rem 1rem;
        transition: all var(--transition-speed) ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        transform: translateY(-2px);
    }

    textarea.form-control {
        border-radius: 15px;
    }

    /* ===== RANGE SLIDER PERSONNALISÉ ===== */
    input[type=range] {
        -webkit-appearance: none;
        width: 100%;
        height: 8px;
        border-radius: 5px;
        background: #e0e0e0;
        outline: none;
        transition: all var(--transition-speed) ease;
    }

    input[type=range]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        background: var(--primary-gradient);
        cursor: pointer;
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.4);
        transition: all var(--transition-speed) ease;
    }

    input[type=range]::-webkit-slider-thumb:hover {
        transform: scale(1.2);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.6);
    }

    /* ===== COMMENTAIRES AMÉLIORÉS ===== */
    .comment-item {
        background: white;
        border-radius: 15px;
        padding: 1.5rem !important;
        margin-bottom: 1rem !important;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        transition: all var(--transition-speed) ease;
        border-left: 4px solid transparent;
    }

    .comment-item:hover {
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.12);
    }

    .comment-item.bg-light-primary {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        border-left-color: #667eea;
    }

    .comment-item.bg-light-success {
        background: linear-gradient(135deg, rgba(132, 250, 176, 0.1) 0%, rgba(143, 211, 244, 0.1) 100%);
        border-left-color: #84fab0;
    }

    .comment-item.bg-light-info {
        background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
        border-left-color: #4facfe;
    }

    /* ===== ACTIVITÉS FEED ===== */
    .activity-feed {
        max-height: 400px;
        overflow-y: auto;
        padding-right: 10px;
    }

    .activity-feed::-webkit-scrollbar {
        width: 6px;
    }

    .activity-feed::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .activity-feed::-webkit-scrollbar-thumb {
        background: var(--primary-gradient);
        border-radius: 10px;
    }

    .activity-item {
        padding: 1rem;
        border-radius: 10px;
        background: white;
        margin-bottom: 0.75rem;
        transition: all var(--transition-speed) ease;
        border-left: 3px solid #667eea;
    }

    .activity-item:hover {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        transform: translateX(5px);
    }

    /* ===== ANIMATIONS ===== */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
        100% {
            transform: scale(1);
        }
    }

    .card {
        animation: fadeInUp 0.5s ease;
    }

    .stat-card {
        animation: fadeInUp 0.6s ease;
    }

    .accordion-item {
        animation: fadeInUp 0.7s ease;
    }

    /* ===== TOAST NOTIFICATIONS ===== */
    .toast {
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        animation: slideInRight 0.3s ease;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* ===== MODAL AMÉLIORÉ ===== */
    .modal-content {
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        animation: fadeInUp 0.3s ease;
    }

    .modal-header {
        background: var(--primary-gradient);
        color: white;
        border-radius: 20px 20px 0 0;
        border: none;
    }

    .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    /* ===== RESPONSIVE DESIGN ===== */
    @media (max-width: 768px) {
        .stat-card {
            margin-bottom: 1rem;
        }
        
        .accordion-button {
            padding: 1rem;
        }
        
        .card-body {
            padding: 1rem;
        }
    }

    /* ===== EFFETS HOVER SUPPLÉMENTAIRES ===== */
    .btn-outline-primary:hover {
        background: var(--primary-gradient);
        border-color: transparent;
        color: white;
    }

    .list-group-item {
        border: none;
        border-radius: 10px;
        margin-bottom: 0.5rem;
        transition: all var(--transition-speed) ease;
    }

    .list-group-item:hover {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        transform: translateX(5px);
    }

    /* ===== LOADING SPINNER ===== */
    .spinner-border {
        width: 3rem;
        height: 3rem;
        border-width: 0.3rem;
    }

    /* ===== BREADCRUMB MODERNE ===== */
    .breadcrumb {
        background: transparent;
        padding: 0;
    }

    .breadcrumb-item + .breadcrumb-item::before {
        content: "›";
        font-size: 1.2rem;
        color: #667eea;
    }

    /* ===== PAGE TITLE ===== */
    .pagetitle h1 {
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
        font-size: 2rem;
    }
</style>

<?php include "./views/include/footer_file.php"; ?>

                                                                                   
