<?php
include "./views/include/header.php";

$universite = new Universite();
$agent = new Agent();
$ecue = new Ecue();

// Vérifier si l'utilisateur est administrateur ou membre d'un jury
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isJuryMember = false;
$isJuryPresident = false;

// Récupérer les bureaux de jury où l'agent est membre (président, secrétaire ou autre)
$juryBureaux = [];
if ($agentId) {
    $juryBureaux = $universite->getJuryBureauxByAgent($agentId);
    $isJuryMember = !empty($juryBureaux);
    $isJuryPresident = $universite->isJuryPresident($agentId);
}

// Rediriger si l'utilisateur n'a pas les droits
if (!$isAdmin && !$isJuryMember) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres de sélection
$bureauId = isset($_GET['bureau']) ? intval($_GET['bureau']) : 0;
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : 0;
$sessionId = isset($_GET['session']) ? intval($_GET['session']) : 0;
$anneeId = isset($_GET['annee']) ? intval($_GET['annee']) : 0;

// Validation des accès
if ($bureauId && !$isAdmin) {
    // Vérifier si l'agent est membre de ce bureau
    $hasAccess = false;
    foreach ($juryBureaux as $jury) {
        if ($jury['idbureau'] == $bureauId) {
            $hasAccess = true;
            break;
        }
    }
    
    if (!$hasAccess) {
        echo "<script>
            Swal.fire({
                icon: 'error',
                title: 'Accès refusé',
                text: 'Vous n\'êtes pas autorisé à accéder à ce bureau de jury.'
            }).then(() => {
                window.location.href = 'deliberation/deliberation_process';
            });
        </script>";
        exit();
    }
}

// Récupérer les données pour les sélecteurs
if ($isAdmin) {
    $bureaux = $universite->getJurys('', true); // Tous les jurys actifs
} else {
    $bureaux = $juryBureaux; // Seulement les jurys où l'agent est membre
}

// Récupérer les promotions associées au bureau sélectionné
$promotions = [];
if ($bureauId) {
    $promotions = $universite->getPromotionsByJury($bureauId);
}

// Récupérer les sessions et années académiques
$sessions = $universite->getAllSessions();
$annees = $universite->getAcademicYears();

// Vérifier si une délibération existe déjà pour ces paramètres
$deliberationExistante = null;
$configDeliberation = null;
if ($bureauId && $promotionId && $sessionId && $anneeId) {
    $deliberationExistante = $universite->getDeliberationExistante($bureauId, $promotionId, $sessionId, $anneeId);
    if ($deliberationExistante) {
        $configDeliberation = $universite->getConfigurationDeliberation($bureauId, $sessionId, $anneeId);
    }
}

// Déterminer si c'est la deuxième session
$isSecondSession = false;
if ($sessionId) {
    $isSecondSession = $ecue->isDeuxiemeSession($sessionId);
}
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>Processus de Délibération</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Processus de délibération</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- 1. SÉLECTION DES PARAMÈTRES -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-gear-fill me-2"></i>
                            Sélection des paramètres de délibération
                        </h5>

                        <form method="GET" action="" class="row g-3 mb-4">
                            <input type="hidden" name="view" value="deliberation/deliberation_process">
                            
                            <div class="col-md-3">
                                <label for="bureau" class="form-label">Bureau de Jury</label>
                                <select name="bureau" id="bureau" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner un bureau</option>
                                    <?php foreach ($bureaux as $bureau): ?>
                                        <option value="<?= $bureau['idbureau'] ?>" <?= ($bureauId == $bureau['idbureau']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($bureau['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <?php if ($bureauId): ?>
                            <div class="col-md-3">
                                <label for="promotion" class="form-label">Promotion</label>
                                <select name="promotion" id="promotion" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner une promotion</option>
                                    <?php foreach ($promotions as $promotion): ?>
                                        <option value="<?= $promotion['idpromotion'] ?>" <?= ($promotionId == $promotion['idpromotion']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($promotion['designationPromotion']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="session" class="form-label">Session</label>
                                <select name="session" id="session" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner une session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?= $session['idsession'] ?>" <?= ($sessionId == $session['idsession']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($session['designSession']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="annee" class="form-label">Année Académique</label>
                                <select name="annee" id="annee" class="form-select" required onchange="this.form.submit()">
                                    <option value="">Sélectionner une année</option>
                                    <?php foreach ($annees as $annee): ?>
                                        <option value="<?= $annee['idannee_acad'] ?>" <?= ($anneeId == $annee['idannee_acad']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($annee['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($bureauId && $promotionId && $sessionId && $anneeId): ?>
                            <div class="col-12 text-center">
                                <?php if ($deliberationExistante): ?>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Une délibération existe déjà pour ces paramètres (créée le <?= date('d/m/Y H:i', strtotime($deliberationExistante['date_creation'])) ?>).
                                        Statut: <strong><?= $deliberationExistante['statut'] ?></strong>
                                    </div>
                                    <button type="button" class="btn btn-primary" onclick="continuerDeliberation(<?= $deliberationExistante['iddeliberation'] ?>)">
                                        <i class="bi bi-arrow-right-circle"></i> Continuer cette délibération
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-success" onclick="initierDeliberation()">
                                        <i class="bi bi-play-circle"></i> Initier une nouvelle délibération
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if ($bureauId && $promotionId && $sessionId && $anneeId): ?>
            
            <!-- 2. VUE DES RÉSULTATS BRUTS (PRÉ-DÉLIBÉRATION) -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-table me-2"></i>
                            Résultats bruts (pré-délibération)
                        </h5>
                        
                        <!-- Options de filtrage et tri -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="searchEtudiants" placeholder="Rechercher un étudiant...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="filterResults">
                                    <option value="all">Tous les étudiants</option>
                                    <option value="success">Réussite (≥ 10)</option>
                                    <option value="failure">Échec (< 10)</option>
                                    <option value="missing">Notes manquantes</option>
                                </select>
                            </div>
                            <div class="col-md-5 text-end">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-printer"></i> Imprimer
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="imprimerDocument('grille_points')">Grille des points par ECUE</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="imprimerDocument('recap_ue')">Récapitulatif par UE</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="imprimerDocument('recap_semestre')">Récapitulatif par semestre</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="imprimerDocument('recap_annuel')">Récapitulatif annuel</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tableau des résultats bruts -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="tableResultatsBruts">
                                <thead class="table-primary">
                                    <tr>
                                        <th rowspan="2" style="vertical-align: middle;">#</th>
                                        <th rowspan="2" style="vertical-align: middle;">Matricule</th>
                                        <th rowspan="2" style="vertical-align: middle;">Nom de l'étudiant</th>
                                        <!-- En-têtes dynamiques pour les ECUE -->
                                        <th colspan="3" class="text-center">ECUE 1</th>
                                        <th colspan="3" class="text-center">ECUE 2</th>
                                        <th colspan="3" class="text-center">ECUE 3</th>
                                        <!-- Ajouter d'autres ECUE selon les données réelles -->
                                    </tr>
                                    <tr>
                                        <!-- Sous-en-têtes pour chaque ECUE (CC, EX, MF) -->
                                        <th>CC</th>
                                        <th>EX</th>
                                        <th>MF</th>
                                        <th>CC</th>
                                        <th>EX</th>
                                        <th>MF</th>
                                        <th>CC</th>
                                        <th>EX</th>
                                        <th>MF</th>
                                        <!-- Répéter pour chaque ECUE -->
                                    </tr>
                                </thead>
                                <tbody id="tbodyResultatsBruts">
                                    <tr>
                                        <td colspan="12" class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Chargement...</span>
                                            </div>
                                            <p class="mt-2">Chargement des résultats...</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 3. LANCEMENT DE LA DÉLIBÉRATION AUTOMATIQUE -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                        <i class="bi bi-lightning-charge me-2"></i>
                            Lancement de la délibération automatique
                        </h5>
                        
                        <!-- Affichage des règles de délibération -->
                        <div class="row mb-4">
                            <div class="col-lg-12">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Règles de délibération qui seront appliquées:</h6>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <ul class="list-group">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Compensation intra-UE
                                                    <span class="badge bg-<?= ($configDeliberation && $configDeliberation['compensation_intra_ue']) ? 'success' : 'danger' ?> rounded-pill">
                                                        <?= ($configDeliberation && $configDeliberation['compensation_intra_ue']) ? 'Activée' : 'Désactivée' ?>
                                                    </span>
                                                </li>
                                                <?php if ($configDeliberation && $configDeliberation['compensation_intra_ue']): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Seuil minimum pour compensation intra-UE
                                                    <span class="badge bg-primary rounded-pill"><?= $configDeliberation['seuil_compensation_intra_ue'] ?>/20</span>
                                                </li>
                                                <?php endif; ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Compensation inter-UE
                                                    <span class="badge bg-<?= ($configDeliberation && $configDeliberation['compensation_inter_ue']) ? 'success' : 'danger' ?> rounded-pill">
                                                        <?= ($configDeliberation && $configDeliberation['compensation_inter_ue']) ? 'Activée' : 'Désactivée' ?>
                                                    </span>
                                                </li>
                                                <?php if ($configDeliberation && $configDeliberation['compensation_inter_ue']): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Seuil minimum pour compensation inter-UE
                                                    <span class="badge bg-primary rounded-pill"><?= $configDeliberation['seuil_compensation_inter_ue'] ?>/20</span>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <ul class="list-group">
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Compensation inter-semestre
                                                    <span class="badge bg-<?= ($configDeliberation && $configDeliberation['compensation_inter_semestre']) ? 'success' : 'danger' ?> rounded-pill">
                                                        <?= ($configDeliberation && $configDeliberation['compensation_inter_semestre']) ? 'Activée' : 'Désactivée' ?>
                                                    </span>
                                                </li>
                                                <?php if ($configDeliberation && $configDeliberation['compensation_inter_semestre']): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Seuil minimum pour compensation inter-semestre
                                                    <span class="badge bg-primary rounded-pill"><?= $configDeliberation['seuil_compensation_inter_semestre'] ?>/20</span>
                                                </li>
                                                <?php endif; ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Note de passage
                                                    <span class="badge bg-success rounded-pill"><?= ($configDeliberation) ? $configDeliberation['note_passage'] : '10' ?>/20</span>
                                                </li>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    Pourcentage minimum pour valider un semestre
                                                    <span class="badge bg-primary rounded-pill"><?= ($configDeliberation) ? $configDeliberation['pourcentage_passage_semestre'] : '50' ?>%</span>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Bouton de lancement et indicateur de progression -->
                        <div class="row">
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary btn-lg w-100" id="btnLancerDeliberation" onclick="lancerDeliberation()">
                                    <i class="bi bi-play-circle me-2"></i> Lancer la délibération automatique
                                </button>
                            </div>
                            <div class="col-md-8">
                                <div class="progress" style="height: 30px; display: none;" id="progressDeliberation">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" id="progressBarDeliberation">0%</div>
                                </div>
                                <div id="etapeDeliberation" class="mt-2 text-center" style="display: none;">
                                    <span class="badge bg-info">Initialisation...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 4. RÉSULTATS POST-DÉLIBÉRATION -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Résultats post-délibération
                        </h5>
                        
                        <!-- Onglets pour les différentes vues -->
                        <ul class="nav nav-tabs" id="resultsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="moyennes-ue-tab" data-bs-toggle="tab" data-bs-target="#moyennes-ue" type="button" role="tab" aria-controls="moyennes-ue" aria-selected="true">
                                    <i class="bi bi-grid me-1"></i> Moyennes par UE
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="moyennes-semestre-tab" data-bs-toggle="tab" data-bs-target="#moyennes-semestre" type="button" role="tab" aria-controls="moyennes-semestre" aria-selected="false">
                                    <i class="bi bi-calendar3 me-1"></i> Moyennes par semestre
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="resultats-finaux-tab" data-bs-toggle="tab" data-bs-target="#resultats-finaux" type="button" role="tab" aria-controls="resultats-finaux" aria-selected="false">
                                    <i class="bi bi-trophy me-1"></i> Résultats finaux
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="historique-tab" data-bs-toggle="tab" data-bs-target="#historique" type="button" role="tab" aria-controls="historique" aria-selected="false">
                                    <i class="bi bi-clock-history me-1"></i> Historique des modifications
                                </button>
                            </li>
                        </ul>
                        
                        <!-- Contenu des onglets -->
                        <div class="tab-content pt-3" id="resultsTabContent">
                            <!-- Moyennes par UE -->
                            <div class="tab-pane fade show active" id="moyennes-ue" role="tabpanel" aria-labelledby="moyennes-ue-tab">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="tableMoyennesUE">
                                        <thead class="table-primary">
                                            <tr>
                                                <th rowspan="2" style="vertical-align: middle;">#</th>
                                                <th rowspan="2" style="vertical-align: middle;">Matricule</th>
                                                <th rowspan="2" style="vertical-align: middle;">Nom de l'étudiant</th>
                                                <!-- En-têtes dynamiques pour les UE -->
                                                <th colspan="3" class="text-center">UE 1</th>
                                                <th colspan="3" class="text-center">UE 2</th>
                                                <th colspan="3" class="text-center">UE 3</th>
                                                <!-- Ajouter d'autres UE selon les données réelles -->
                                            </tr>
                                            <tr>
                                                <!-- Sous-en-têtes pour chaque UE (Moyenne, Crédits, Validation) -->
                                                <th>Moyenne</th>
                                                <th>Crédits</th>
                                                <th>Validation</th>
                                                <th>Moyenne</th>
                                                <th>Crédits</th>
                                                <th>Validation</th>
                                                <th>Moyenne</th>
                                                <th>Crédits</th>
                                                <th>Validation</th>
                                                <!-- Répéter pour chaque UE -->
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyMoyennesUE">
                                            <tr>
                                                <td colspan="12" class="text-center">
                                                    <div class="alert alert-info">
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        Les moyennes par UE seront disponibles après le lancement de la délibération.
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Moyennes par semestre -->
                            <div class="tab-pane fade" id="moyennes-semestre" role="tabpanel" aria-labelledby="moyennes-semestre-tab">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="tableMoyennesSemestre">
                                        <thead class="table-primary">
                                            <tr>
                                                <th rowspan="2" style="vertical-align: middle;">#</th>
                                                <th rowspan="2" style="vertical-align: middle;">Matricule</th>
                                                <th rowspan="2" style="vertical-align: middle;">Nom de l'étudiant</th>
                                                <!-- En-têtes dynamiques pour les semestres -->
                                                <th colspan="4" class="text-center">Semestre 1</th>
                                                <th colspan="4" class="text-center">Semestre 2</th>
                                                <th colspan="4" class="text-center">Année</th>
                                            </tr>
                                            <tr>
                                                <!-- Sous-en-têtes pour chaque semestre (Moyenne, Crédits obtenus, Crédits total, Validation) -->
                                                <th>Moyenne</th>
                                                <th>Crédits obtenus</th>
                                                <th>Crédits total</th>
                                                <th>Validation</th>
                                                <th>Moyenne</th>
                                                <th>Crédits obtenus</th>
                                                <th>Crédits total</th>
                                                <th>Validation</th>
                                                <th>Moyenne</th>
                                                <th>Crédits obtenus</th>
                                                <th>Crédits total</th>
                                                <th>Décision</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyMoyennesSemestre">
                                            <tr>
                                                <td colspan="15" class="text-center">
                                                    <div class="alert alert-info">
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        Les moyennes par semestre seront disponibles après le lancement de la délibération.
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Résultats finaux -->
                            <div class="tab-pane fade" id="resultats-finaux" role="tabpanel" aria-labelledby="resultats-finaux-tab">
                                <div class="row mb-3">
                                    <div class="col-md-12 text-end">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-file-earmark-arrow-down"></i> Exporter
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="#" onclick="exportResultatsFinaux('excel')">Excel</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="exportResultatsFinaux('pdf')">PDF</a></li>
                                                <li><a class="dropdown-item" href="#" onclick="printResultatsFinaux()">Imprimer</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="tableResultatsFinaux">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>#</th>
                                                <th>Matricule</th>
                                                <th>Nom de l'étudiant</th>
                                                <th>Moyenne Générale</th>
                                                <th>Crédits Acquis</th>
                                                <th>Crédits Total</th>
                                                <th>Mention</th>
                                                <th>Décision</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyResultatsFinaux">
                                            <tr>
                                                <td colspan="9" class="text-center">
                                                    <div class="alert alert-info">
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        Les résultats finaux seront disponibles après le lancement de la délibération.
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Historique des modifications -->
                            <div class="tab-pane fade" id="historique" role="tabpanel" aria-labelledby="historique-tab">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="tableHistorique">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>#</th>
                                                <th>Date</th>
                                                <th>Matricule</th>
                                                <th>Nom de l'étudiant</th>
                                                <th>Type d'élément</th>
                                                <th>Élément</th>
                                                <th>Note avant</th>
                                                <th>Note après</th>
                                                <th>Type de modification</th>
                                                <th>Justification</th>
                                                <th>Utilisateur</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyHistorique">
                                            <tr>
                                                <td colspan="11" class="text-center">
                                                    <div class="alert alert-info">
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        L'historique des modifications sera disponible après le lancement de la délibération.
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 5. FINALISATION ET DOCUMENTS -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-file-earmark-check me-2"></i>
                            Finalisation et documents
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card shadow-sm mb-3">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="card-title mb-0"><i class="bi bi-file-text me-2"></i>Documents officiels</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="list-group">
                                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="genererPV('semestre')">
                                                <div>
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    PV de délibération semestrielle
                                                </div>
                                                <span class="badge bg-primary rounded-pill">
                                                    <i class="bi bi-download"></i>
                                                </span>
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="genererPV('annuel')">
                                                <div>
                                                    <i class="bi bi-file-earmark-text me-2"></i>
                                                    PV de délibération annuelle
                                                </div>
                                                <span class="badge bg-primary rounded-pill">
                                                    <i class="bi bi-download"></i>
                                                </span>
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="genererPalmares('complet')">
                                                <div>
                                                    <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                                                    Palmarès
                                                </div>
                                                <span class="badge bg-primary rounded-pill">
                                                    <i class="bi bi-download"></i>
                                                </span>
                                            </button>
                                            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" onclick="genererStatistiques('global')">
                                                <div>
                                                    <i class="bi bi-bar-chart me-2"></i>
                                                    Statistiques de réussite
                                                </div>
                                                <span class="badge bg-primary rounded-pill">
                                                    <i class="bi bi-download"></i>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow-sm mb-3">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="card-title mb-0"><i class="bi bi-person-badge me-2"></i>Relevés de notes individuels</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="etudiantReleve" class="form-label">Sélectionner un étudiant</label>
                                            <select class="form-select" id="etudiantReleve">
                                                <option value="">Chargement des étudiants...</option>
                                            </select>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-outline-primary" onclick="genererReleve('individuel')">
                                                <i class="bi bi-file-earmark-person me-2"></i>
                                                Générer le relevé de notes
                                            </button>
                                            <button type="button" class="btn btn-outline-success" onclick="genererReleve('tous')">
                                                <i class="bi bi-file-earmark-zip me-2"></i>
                                                Générer tous les relevés (ZIP)
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card shadow-sm">
                                    <div class="card-header bg-success text-white">
                                        <h6 class="card-title mb-0"><i class="bi bi-check-circle me-2"></i>Validation finale</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-success" onclick="validerDeliberation()">
                                                <i class="bi bi-check-circle me-2"></i>
                                                Valider définitivement la délibération
                                            </button>
                                            <button type="button" class="btn btn-warning" onclick="recalculerResultats()">
                                                <i class="bi bi-arrow-repeat me-2"></i>
                                                Recalculer les résultats
                                            </button>
                                            <button type="button" class="btn btn-danger" onclick="annulerDeliberation()">
                                                <i class="bi bi-x-circle me-2"></i>
                                                Annuler la délibération
                                            </button>
                                        </div>
                                    </div>
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

<!-- Modal pour l'intervention manuelle du jury -->
<div class="modal fade" id="interventionModal" tabindex="-1" aria-labelledby="interventionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="interventionModalLabel">Intervention manuelle du jury</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="infoEtudiant" class="alert alert-info mb-3"></div>
                
                <form id="formIntervention">
                    <input type="hidden" id="matriculeIntervention" name="matricule">
                    <input type="hidden" id="iddeliberationIntervention" name="iddeliberation">
                    
                    <div class="mb-3">
                        <label for="typeElement" class="form-label">Type d'élément</label>
                        <select class="form-select" id="typeElement" name="type_element" required onchange="chargerElements(this.value)">
                            <option value="">Sélectionner...</option>
                            <option value="ECUE">ECUE</option>
                            <option value="UE">UE</option>
                            <option value="Semestre">Semestre</option>
                            <option value="Annuel">Annuel</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="elementContainer" style="display: none;">
                        <label for="idElement" class="form-label">Élément</label>
                        <select class="form-select" id="idElement" name="id_element" required onchange="chargerNoteOriginale()">
                            <option value="">Sélectionner...</option>
                        </select>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="noteOriginale" class="form-label">Note originale</label>
                            <input type="number" class="form-control" id="noteOriginale" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="noteModifiee" class="form-label">Note modifiée</label>
                            <input type="number" class="form-control" id="noteModifiee" name="note_modifiee" min="0" max="20" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="motif" class="form-label">Motif de l'intervention</label>
                        <select class="form-select" id="motif" name="motif" required>
                            <option value="">Sélectionner...</option>
                            <option value="Décision jury">Décision du jury</option>
                            <option value="Correction erreur">Correction d'une erreur</option>
                            <option value="Cas particulier">Cas particulier</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="justification" class="form-label">Justification détaillée</label>
                        <textarea class="form-control" id="justification" name="justification" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="sauvegarderIntervention()">Sauvegarder</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation de validation -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="confirmationModalLabel">Confirmation de validation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Attention!</strong> La validation définitive de la délibération rendra les résultats officiels et ne pourra plus être modifiée.
                </div>
                
                <p>Veuillez confirmer que:</p>
                <ul>
                    <li>Tous les membres du jury ont approuvé les résultats</li>
                    <li>Toutes les interventions manuelles ont été justifiées</li>
                    <li>Les documents officiels ont été vérifiés</li>
                </ul>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="confirmValidation" onchange="document.getElementById('btnConfirmValidation').disabled = !this.checked">
                    <label class="form-check-label" for="confirmValidation">
                        Je confirme que la délibération est complète et peut être validée définitivement
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-warning" id="btnConfirmValidation" onclick="validerDefinitivement()" disabled>
                    <i class="bi bi-check-circle me-2"></i> Valider définitivement
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let currentBureauId = <?= $bureauId ?: 'null' ?>;
let currentPromotionId = <?= $promotionId ?: 'null' ?>;
let currentSessionId = <?= $sessionId ?: 'null' ?>;
let currentAnneeId = <?= $anneeId ?: 'null' ?>;
let deliberationId = <?= $deliberationExistante ? $deliberationExistante['iddeliberation'] : 'null' ?>;
let isSecondSession = <?= $isSecondSession ? 'true' : 'false' ?>;
let interventionModal;
let confirmationModal;

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les modals
    interventionModal = new bootstrap.Modal(document.getElementById('interventionModal'));
    confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    
    // Si une délibération est déjà sélectionnée, charger les données
    if (deliberationId) {
        chargerResultatsBruts();
    }
});

// Fonction pour initier une nouvelle délibération
function initierDeliberation() {
    if (!currentBureauId || !currentPromotionId || !currentSessionId || !currentAnneeId) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Veuillez sélectionner tous les paramètres requis.'
        });
        return;
    }
    
    Swal.fire({
        title: 'Initier une nouvelle délibération',
        text: 'Êtes-vous sûr de vouloir initier une nouvelle délibération pour ces paramètres?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, initier',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Afficher un indicateur de chargement
            Swal.fire({
                title: 'Initialisation en cours...',
                text: 'Veuillez patienter pendant l\'initialisation de la délibération.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Envoyer la requête pour créer une nouvelle délibération
            fetch('controller/initier_deliberation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    bureauId: currentBureauId,
                    promotionId: currentPromotionId,
                    sessionId: currentSessionId,
                    anneeId: currentAnneeId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    deliberationId = data.deliberationId;
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Délibération initiée',
                        text: 'La délibération a été initiée avec succès.',
                        confirmButtonText: 'Continuer'
                    }).then(() => {
                        // Recharger la page avec le nouvel ID de délibération
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de l\'initialisation de la délibération.'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur.'
                });
            });
        }
    });
}

// Fonction pour continuer une délibération existante
function continuerDeliberation(id) {
    deliberationId = id;
    chargerResultatsBruts();
}

// Fonction pour charger les résultats bruts
function chargerResultatsBruts() {
    if (!deliberationId) return;
    
    // Afficher un indicateur de chargement
    document.getElementById('tbodyResultatsBruts').innerHTML = `
        <tr>
            <td colspan="12" class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Chargement...</span>
                </div>
                <p class="mt-2">Chargement des résultats...</p>
            </td>
        </tr>
    `;
    
    // Charger les données
    fetch(`controller/get_resultats_bruts.php?deliberation=${deliberationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour les en-têtes du tableau avec les ECUE réels
            updateTableHeaders(data.ecues);
            
            // Afficher les résultats bruts
            afficherResultatsBruts(data.etudiants, data.cotes);
            
            // Charger la liste des étudiants pour le sélecteur de relevés
            chargerListeEtudiants(data.etudiants);
            
            // Si la délibération a déjà été lancée, charger aussi les résultats post-délibération
            if (data.deliberation && data.deliberation.statut !== 'En préparation') {
                chargerResultatsPostDeliberation();
            }
        } else {
            document.getElementById('tbodyResultatsBruts').innerHTML = `
                <tr>
                    <td colspan="12" class="text-center">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Une erreur est survenue lors du chargement des résultats.'}
                        </div>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        document.getElementById('tbodyResultatsBruts').innerHTML = `
            <tr>
                <td colspan="12" class="text-center">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Une erreur est survenue lors de la communication avec le serveur.
                    </div>
                </td>
            </tr>
        `;
    });
}

// Fonction pour mettre à jour les en-têtes du tableau avec les ECUE réels
function updateTableHeaders(ecues) {
    if (!ecues || ecues.length === 0) return;
    
    // Construire les en-têtes pour les ECUE
    let headerRow1 = `
        <th rowspan="2" style="vertical-align: middle;">#</th>
        <th rowspan="2" style="vertical-align: middle;">Matricule</th>
        <th rowspan="2" style="vertical-align: middle;">Nom de l'étudiant</th>
    `;
    
    let headerRow2 = '';
    
    ecues.forEach(ecue => {
        headerRow1 += `<th colspan="3" class="text-center">${ecue.designationECUE}</th>`;
        headerRow2 += `
            <th>CC</th>
            <th>EX</th>
            <th>MF</th>
        `;
    });
    
    // Mettre à jour les en-têtes du tableau
    const tableHead = document.querySelector('#tableResultatsBruts thead');
    tableHead.innerHTML = `
        <tr>${headerRow1}</tr>
        <tr>${headerRow2}</tr>
    `;
}

// Fonction pour afficher les résultats bruts
function afficherResultatsBruts(etudiants, cotes) {
    if (!etudiants || etudiants.length === 0) {
        document.getElementById('tbodyResultatsBruts').innerHTML = `
            <tr>
                <td colspan="12" class="text-center">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucun étudiant trouvé pour cette délibération.
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Organiser les cotes par étudiant et par ECUE
    const cotesByEtudiantEcue = {};
    cotes.forEach(cote => {
        if (!cotesByEtudiantEcue[cote.matricule]) {
            cotesByEtudiantEcue[cote.matricule] = {};
        }
        cotesByEtudiantEcue[cote.matricule][cote.idECUE] = cote;
    });
    
    // Récupérer la liste des ECUE
    const ecueIds = [...new Set(cotes.map(cote => cote.idECUE))];
    
    // Générer les lignes du tableau
    let tableHtml = '';
    etudiants.forEach((etudiant, index) => {
        const matricule = etudiant.matricule;
        
        let rowHtml = `
            <tr data-matricule="${matricule}">
                <td>${index + 1}</td>
                <td>${matricule}</td>
                <td>${etudiant.noms}</td>
        `;
        
        // Ajouter les cotes pour chaque ECUE
        ecueIds.forEach(ecueId => {
            const cote = cotesByEtudiantEcue[matricule] && cotesByEtudiantEcue[matricule][ecueId] 
                ? cotesByEtudiantEcue[matricule][ecueId] 
                : { CC: '', EX: '', MF: '' };
            
            const ccClass = cote.CC !== '' && cote.CC < 10 ? 'text-danger' : '';
            const exClass = cote.EX !== '' && cote.EX < 10 ? 'text-danger' : '';
            const mfClass = cote.MF !== '' && cote.MF < 10 ? 'text-danger' : '';
            
            rowHtml += `
                <td class="${ccClass}">${cote.CC !== null ? cote.CC : ''}</td>
                <td class="${exClass}">${cote.EX !== null ? cote.EX : ''}</td>
                <td class="${mfClass}">${cote.MF !== null ? cote.MF : ''}</td>
            `;
        });
        
        rowHtml += '</tr>';
        tableHtml += rowHtml;
    });
    
    document.getElementById('tbodyResultatsBruts').innerHTML = tableHtml;
}

// Fonction pour charger la liste des étudiants pour le sélecteur de relevés
function chargerListeEtudiants(etudiants) {
    if (!etudiants || etudiants.length === 0) return;
    
    const selectEtudiant = document.getElementById('etudiantReleve');
    if (!selectEtudiant) return;
    
    let optionsHtml = '<option value="">Sélectionner un étudiant</option>';
    
    etudiants.forEach(etudiant => {
        optionsHtml += `<option value="${etudiant.matricule}">${etudiant.noms} (${etudiant.matricule})</option>`;
    });
    
    selectEtudiant.innerHTML = optionsHtml;
}

// Fonction pour lancer la délibération automatique
function lancerDeliberation() {
    if (!deliberationId) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Aucune délibération sélectionnée. Veuillez d\'abord initier une délibération.'
        });
        return;
    }
    
    Swal.fire({
        title: 'Lancer la délibération automatique',
        text: 'Êtes-vous sûr de vouloir lancer le processus de délibération automatique? Cette opération peut prendre plusieurs minutes.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, lancer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Afficher la barre de progression
            document.getElementById('progressDeliberation').style.display = 'block';
            document.getElementById('etapeDeliberation').style.display = 'block';
            document.getElementById('btnLancerDeliberation').disabled = true;
            
            // Initialiser la progression
            document.getElementById('progressBarDeliberation').style.width = '0%';
            document.getElementById('progressBarDeliberation').textContent = '0%';
            document.getElementById('etapeDeliberation').innerHTML = '<span class="badge bg-info">Initialisation...</span>';
            
            // Lancer le processus de délibération
            lancerProcessusDeliberation();
        }
    });
}

// Fonction pour lancer le processus de délibération par étapes
function lancerProcessusDeliberation() {
    // Étapes du processus de délibération
    const etapes = [
        { id: 'initialisation', nom: 'Initialisation', pourcentage: 5 },
        { id: 'calcul_ecue', nom: 'Calcul des moyennes ECUE', pourcentage: 15 },
        { id: 'calcul_ue', nom: 'Calcul des moyennes UE', pourcentage: 30 },
        { id: 'compensation_intra_ue', nom: 'Compensation intra-UE', pourcentage: 45 },
        { id: 'compensation_inter_ue', nom: 'Compensation inter-UE', pourcentage: 60 },
        { id: 'compensation_inter_semestre', nom: 'Compensation inter-semestre', pourcentage: 75 },
        { id: 'decisions_jury', nom: 'Application des décisions du jury', pourcentage: 85 },
        { id: 'finalisation', nom: 'Finalisation des résultats', pourcentage: 95 },
        { id: 'termine', nom: 'Processus terminé', pourcentage: 100 }
    ];
    
    // Fonction pour mettre à jour la progression
    function updateProgress(etapeIndex) {
        if (etapeIndex >= etapes.length) {
            // Processus terminé
            document.getElementById('progressBarDeliberation').style.width = '100%';
            document.getElementById('progressBarDeliberation').textContent = '100%';
            document.getElementById('etapeDeliberation').innerHTML = '<span class="badge bg-success">Processus terminé avec succès</span>';
            document.getElementById('btnLancerDeliberation').disabled = false;
            
            // Charger les résultats post-délibération
            chargerResultatsPostDeliberation();
            return;
        }
        
        const etape = etapes[etapeIndex];
        
        // Mettre à jour la barre de progression
        document.getElementById('progressBarDeliberation').style.width = etape.pourcentage + '%';
        document.getElementById('progressBarDeliberation').textContent = etape.pourcentage + '%';
        document.getElementById('etapeDeliberation').innerHTML = `<span class="badge bg-info">${etape.nom}</span>`;
        
        // Exécuter l'étape actuelle
        executerEtape(etape.id, etapeIndex, etapes.length)
            .then(success => {
                if (success) {
                    // Passer à l'étape suivante
                    setTimeout(() => updateProgress(etapeIndex + 1), 500);
                } else {
                    // Erreur dans l'étape
                    document.getElementById('etapeDeliberation').innerHTML = `<span class="badge bg-danger">Erreur lors de l'étape: ${etape.nom}</span>`;
                    document.getElementById('btnLancerDeliberation').disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur lors de l\'exécution de l\'étape:', error);
                document.getElementById('etapeDeliberation').innerHTML = `<span class="badge bg-danger">Erreur lors de l'étape: ${etape.nom}</span>`;
                document.getElementById('btnLancerDeliberation').disabled = false;
            });
    }
    
    // Démarrer le processus
    updateProgress(0);
}

// Fonction pour exécuter une étape du processus de délibération
function executerEtape(etapeId, etapeIndex, totalEtapes) {
    return new Promise((resolve, reject) => {
        fetch('controller/executer_etape_deliberation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                deliberationId: deliberationId,
                etape: etapeId,
                etapeIndex: etapeIndex,
                totalEtapes: totalEtapes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resolve(true);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: data.message || 'Une erreur est survenue lors de l\'exécution de l\'étape.'
                });
                resolve(false);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            reject(error);
        });
    });
}

// Fonction pour charger les résultats post-délibération
function chargerResultatsPostDeliberation() {
    if (!deliberationId) return;
    
    // Charger les moyennes par UE
    fetch(`controller/get_moyennes_ue.php?deliberation=${deliberationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            afficherMoyennesUE(data.ues, data.moyennes);
        } else {
            document.getElementById('tbodyMoyennesUE').innerHTML = `
                <tr>
                    <td colspan="12" class="text-center">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Une erreur est survenue lors du chargement des moyennes par UE.'}
                        </div>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        document.getElementById('tbodyMoyennesUE').innerHTML = `
            <tr>
                <td colspan="12" class="text-center">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Une erreur est survenue lors de la communication avec le serveur.
                    </div>
                </td>
            </tr>
        `;
    });
    
    // Charger les moyennes par semestre
    fetch(`controller/get_moyennes_semestre.php?deliberation=${deliberationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            afficherMoyennesSemestre(data.semestres, data.moyennes);
        } else {
            document.getElementById('tbodyMoyennesSemestre').innerHTML = `
                <tr>
                    <td colspan="15" class="text-center">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Une erreur est survenue lors du chargement des moyennes par semestre.'}
                        </div>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        document.getElementById('tbodyMoyennesSemestre').innerHTML = `
            <tr>
                <td colspan="15" class="text-center">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Une erreur est survenue lors de la communication avec le serveur.
                    </div>
                </td>
            </tr>
        `;
    });
    
    // Charger les résultats finaux
    fetch(`controller/get_resultats_finaux.php?deliberation=${deliberationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            afficherResultatsFinaux(data.resultats);
        } else {
            document.getElementById('tbodyResultatsFinaux').innerHTML = `
                <tr>
                    <td colspan="9" class="text-center">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Une erreur est survenue lors du chargement des résultats finaux.'}
                        </div>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        document.getElementById('tbodyResultatsFinaux').innerHTML = `
            <tr>
                <td colspan="9" class="text-center">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Une erreur est survenue lors de la communication avec le serveur.
                    </div>
                </td>
            </tr>
        `;
    });
    
    // Charger l'historique des modifications
    fetch(`controller/get_historique_modifications.php?deliberation=${deliberationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            afficherHistoriqueModifications(data.historique);
        } else {
            document.getElementById('tbodyHistorique').innerHTML = `
                <tr>
                    <td colspan="11" class="text-center">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Une erreur est survenue lors du chargement de l\'historique des modifications.'}
                        </div>
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        document.getElementById('tbodyHistorique').innerHTML = `
            <tr>
                <td colspan="11" class="text-center">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Une erreur est survenue lors de la communication avec le serveur.
                    </div>
                </td>
            </tr>
        `;
    });
}

// Fonction pour afficher les moyennes par UE
function afficherMoyennesUE(ues, moyennes) {
    if (!ues || ues.length === 0 || !moyennes || Object.keys(moyennes).length === 0) {
        document.getElementById('tbodyMoyennesUE').innerHTML = `
            <tr>
                <td colspan="12" class="text-center">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucune moyenne par UE disponible.
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Mettre à jour les en-têtes du tableau avec les UE réelles
    let headerRow1 = `
        <th rowspan="2" style="vertical-align: middle;">#</th>
        <th rowspan="2" style="vertical-align: middle;">Matricule</th>
        <th rowspan="2" style="vertical-align: middle;">Nom de l'étudiant</th>
    `;
    
    let headerRow2 = '';
    
    ues.forEach(ue => {
        headerRow1 += `<th colspan="3" class="text-center">${ue.designationUE}</th>`;
        headerRow2 += `
            <th>Moyenne</th>
            <th>Crédits</th>
            <th>Validation</th>
        `;
    });
    
    // Mettre à jour les en-têtes du tableau
    const tableHead = document.querySelector('#tableMoyennesUE thead');
    tableHead.innerHTML = `
        <tr>${headerRow1}</tr>
        <tr>${headerRow2}</tr>
    `;
    
    // Organiser les moyennes par étudiant
    const moyennesByEtudiant = {};
    Object.keys(moyennes).forEach(matricule => {
        moyennesByEtudiant[matricule] = moyennes[matricule];
    });
    
    // Générer les lignes du tableau
    let tableHtml = '';
    let index = 1;
    
    Object.keys(moyennesByEtudiant).forEach(matricule => {
        const etudiantMoyennes = moyennesByEtudiant[matricule];
        
        let rowHtml = `
            <tr data-matricule="${matricule}">
                <td>${index++}</td>
                <td>${matricule}</td>
                <td>${etudiantMoyennes.nom || 'N/A'}</td>
        `;
        
        // Ajouter les moyennes pour chaque UE
        ues.forEach(ue => {
            const ueId = ue.idUE;
            const moyenneUE = etudiantMoyennes.ues && etudiantMoyennes.ues[ueId] 
                ? etudiantMoyennes.ues[ueId] 
                : { moyenne_brute: '', moyenne_deliberee: '', credits_obtenus: 0, est_validee: false };
            
            const moyenneClass = moyenneUE.moyenne_deliberee !== '' && moyenneUE.moyenne_deliberee < 10 ? 'text-danger' : '';
            const validationClass = moyenneUE.est_validee ? 'text-success' : 'text-danger';
            const validationText = moyenneUE.est_validee ? 'Validée' : 'Non validée';
            
            rowHtml += `
                <td class="${moyenneClass}">${moyenneUE.moyenne_deliberee !== null ? moyenneUE.moyenne_deliberee : ''}</td>
                <td>${moyenneUE.credits_obtenus || 0}/${ue.nombre_credits || 0}</td>
                <td class="${validationClass}">${validationText}</td>
            `;
        });
        
        rowHtml += '</tr>';
        tableHtml += rowHtml;
    });
    
    document.getElementById('tbodyMoyennesUE').innerHTML = tableHtml;
}

// Fonction pour afficher les moyennes par semestre
function afficherMoyennesSemestre(semestres, moyennes) {
    if (!semestres || semestres.length === 0 || !moyennes || Object.keys(moyennes).length === 0) {
        document.getElementById('tbodyMoyennesSemestre').innerHTML = `
            <tr>
                <td colspan="15" class="text-center">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucune moyenne par semestre disponible.
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Générer les lignes du tableau
    let tableHtml = '';
    let index = 1;
    
    Object.keys(moyennes).forEach(matricule => {
        const etudiantMoyennes = moyennes[matricule];
        
        let rowHtml = `
            <tr data-matricule="${matricule}">
                <td>${index++}</td>
                <td>${matricule}</td>
                <td>${etudiantMoyennes.nom || 'N/A'}</td>
        `;
        
        // Ajouter les moyennes pour chaque semestre
        semestres.forEach(semestre => {
            const semestreId = semestre.idsemestre;
            const moyenneSemestre = etudiantMoyennes.semestres && etudiantMoyennes.semestres[semestreId] 
                ? etudiantMoyennes.semestres[semestreId] 
                : { moyenne_brute: '', moyenne_deliberee: '', credits_obtenus: 0, credits_total: 0, est_valide: false };
            
            const moyenneClass = moyenneSemestre.moyenne_deliberee !== '' && moyenneSemestre.moyenne_deliberee < 10 ? 'text-danger' : '';
            const validationClass = moyenneSemestre.est_valide ? 'text-success' : 'text-danger';
            const validationText = moyenneSemestre.est_valide ? 'Validé' : 'Non validé';
            
            rowHtml += `
                <td class="${moyenneClass}">${moyenneSemestre.moyenne_deliberee !== null ? moyenneSemestre.moyenne_deliberee : ''}</td>
                <td>${moyenneSemestre.credits_obtenus || 0}</td>
                <td>${moyenneSemestre.credits_total || 0}</td>
                <td class="${validationClass}">${validationText}</td>
            `;
        });
        
        // Ajouter les moyennes annuelles
        const moyenneAnnuelle = etudiantMoyennes.annuel || { moyenne_deliberee: '', credits_obtenus: 0, credits_total: 0, est_admis: false, decision: 'N/A' };
        
        const moyenneAnnuelleClass = moyenneAnnuelle.moyenne_deliberee !== '' && moyenneAnnuelle.moyenne_deliberee < 10 ? 'text-danger' : '';
        const decisionClass = moyenneAnnuelle.est_admis ? 'text-success' : 'text-danger';
        
        rowHtml += `
            <td class="${moyenneAnnuelleClass}">${moyenneAnnuelle.moyenne_deliberee !== null ? moyenneAnnuelle.moyenne_deliberee : ''}</td>
            <td>${moyenneAnnuelle.credits_obtenus || 0}</td>
            <td>${moyenneAnnuelle.credits_total || 0}</td>
            <td class="${decisionClass}">${moyenneAnnuelle.decision || 'N/A'}</td>
        `;
        
        rowHtml += '</tr>';
        tableHtml += rowHtml;
    });
    
    document.getElementById('tbodyMoyennesSemestre').innerHTML = tableHtml;
}

// Fonction pour afficher les résultats finaux
function afficherResultatsFinaux(resultats) {
    if (!resultats || resultats.length === 0) {
        document.getElementById('tbodyResultatsFinaux').innerHTML = `
            <tr>
                <td colspan="9" class="text-center">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucun résultat final disponible.
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Générer les lignes du tableau
    let tableHtml = '';
    
    resultats.forEach((resultat, index) => {
        const moyenneClass = resultat.moyenne_generale < 10 ? 'text-danger' : '';
        const decisionClass = resultat.decision === 'Admis' ? 'text-success' : 
                             (resultat.decision === 'Admis par compensation' ? 'text-warning' : 'text-danger');
        
        tableHtml += `
            <tr data-matricule="${resultat.matricule}">
                <td>${index + 1}</td>
                <td>${resultat.matricule}</td>
                <td>${resultat.nom_etudiant}</td>
                <td class="${moyenneClass}">${resultat.moyenne_generale}</td>
                <td>${resultat.credits_acquis}</td>
                <td>${resultat.credits_total}</td>
                <td>${resultat.mention || 'N/A'}</td>
                <td class="${decisionClass}">${resultat.decision}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-primary" onclick="ouvrirModalIntervention('${resultat.matricule}', '${resultat.nom_etudiant}')">
                        <i class="bi bi-pencil-square"></i> Intervention
                    </button>
                </td>
            </tr>
        `;
    });
    
    document.getElementById('tbodyResultatsFinaux').innerHTML = tableHtml;
}

// Fonction pour afficher l'historique des modifications
function afficherHistoriqueModifications(historique) {
    if (!historique || historique.length === 0) {
        document.getElementById('tbodyHistorique').innerHTML = `
            <tr>
                <td colspan="11" class="text-center">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucun historique de modification disponible.
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    // Générer les lignes du tableau
    let tableHtml = '';
    
    historique.forEach((modification, index) => {
        tableHtml += `
            <tr>
                <td>${index + 1}</td>
                <td>${formatDate(modification.date_modification)}</td>
                <td>${modification.matricule}</td>
                <td>${modification.nom_etudiant}</td>
                <td>${modification.type_element}</td>
                <td>${modification.element_designation}</td>
                <td>${modification.note_avant !== null ? modification.note_avant : 'N/A'}</td>
                <td>${modification.note_apres !== null ? modification.note_apres : 'N/A'}</td>
                <td>${modification.type_modification}</td>
                <td>${modification.justification}</td>
                <td>${modification.nom_utilisateur}</td>
            </tr>
        `;
    });
    
    document.getElementById('tbodyHistorique').innerHTML = tableHtml;
}

// Fonction pour formater une date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Fonction pour ouvrir le modal d'intervention
function ouvrirModalIntervention(matricule, nomEtudiant) {
    // Réinitialiser le formulaire
    document.getElementById('formIntervention').reset();
    
    // Définir les valeurs de base
    document.getElementById('matriculeIntervention').value = matricule;
    document.getElementById('iddeliberationIntervention').value = deliberationId;
    document.getElementById('infoEtudiant').innerHTML = `
        <strong>Étudiant:</strong> ${nomEtudiant}<br>
        <strong>Matricule:</strong> ${matricule}
    `;
    
    // Réinitialiser les sélecteurs
    document.getElementById('elementContainer').style.display = 'none';
    document.getElementById('idElement').innerHTML = '<option value="">Sélectionner...</option>';
    document.getElementById('noteOriginale').value = '';
    document.getElementById('noteModifiee').value = '';
    
    // Ouvrir le modal
    interventionModal.show();
}

// Fonction pour charger les éléments en fonction du type sélectionné
function chargerElements(typeElement) {
    if (!typeElement) return;
    
    const matricule = document.getElementById('matriculeIntervention').value;
    
    // Afficher un loader
    document.getElementById('elementContainer').style.display = 'block';
    document.getElementById('idElement').innerHTML = '<option value="">Chargement...</option>';
    
    // Charger les éléments
    fetch(`controller/get_elements_intervention.php?type=${typeElement}&matricule=${matricule}&deliberation=${deliberationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let optionsHtml = '<option value="">Sélectionner...</option>';
            
            data.elements.forEach(element => {
                optionsHtml += `<option value="${element.id}" data-note="${element.note}">${element.designation}</option>`;
            });
            
            document.getElementById('idElement').innerHTML = optionsHtml;
        } else {
            document.getElementById('idElement').innerHTML = '<option value="">Erreur de chargement</option>';
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors du chargement des éléments.'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        document.getElementById('idElement').innerHTML = '<option value="">Erreur de chargement</option>';
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la communication avec le serveur.'
        });
    });
}

// Fonction pour charger la note originale
function chargerNoteOriginale() {
    const selectElement = document.getElementById('idElement');
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    
    if (selectedOption && selectedOption.dataset.note) {
        document.getElementById('noteOriginale').value = selectedOption.dataset.note;
        document.getElementById('noteModifiee').value = selectedOption.dataset.note;
    } else {
        document.getElementById('noteOriginale').value = '';
        document.getElementById('noteModifiee').value = '';
    }
}

// Fonction pour sauvegarder l'intervention
function sauvegarderIntervention() {
    const form = document.getElementById('formIntervention');
    
    // Vérifier si le formulaire est valide
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Récupérer les données du formulaire
    const formData = {
        matricule: document.getElementById('matriculeIntervention').value,
        iddeliberation: document.getElementById('iddeliberationIntervention').value,
        type_element: document.getElementById('typeElement').value,
        id_element: document.getElementById('idElement').value,
        note_originale: document.getElementById('noteOriginale').value,
        note_modifiee: document.getElementById('noteModifiee').value,
        motif: document.getElementById('motif').value,
        justification: document.getElementById('justification').value
    };
    
    // Envoyer les données au serveur
    fetch('controller/sauvegarder_intervention.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: 'L\'intervention a été enregistrée avec succès.'
            }).then(() => {
                // Fermer le modal
                interventionModal.hide();
                
                // Recharger les résultats
                chargerResultatsPostDeliberation();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de l\'enregistrement de l\'intervention.'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la communication avec le serveur.'
        });
    });
}

// Fonction pour exporter les résultats finaux
function exportResultatsFinaux(format) {
    if (!deliberationId) return;
    
    window.location.href = `controller/export_resultats.php?deliberation=${deliberationId}&format=${format}`;
}

// Fonction pour imprimer les résultats finaux
function printResultatsFinaux() {
    if (!deliberationId) return;
    
    // Ouvrir une nouvelle fenêtre pour l'impression
    const printWindow = window.open('', '_blank');
    
    // Récupérer les données de la délibération
    fetch(`controller/get_print_data.php?deliberation=${deliberationId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Construire le contenu HTML à imprimer
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Résultats de délibération</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1, h2 { text-align: center; }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        .text-danger { color: red; }
                        .text-success { color: green; }
                        .text-warning { color: orange; }
                        .header-info { margin-bottom: 20px; }
                        .footer { margin-top: 30px; text-align: center; }
                    </style>
                </head>
                <body>
                    <h1>${data.universite.nom}</h1>
                    <h2>Résultats de délibération</h2>
                    
                    <div class="header-info">
                        <p><strong>Promotion:</strong> ${data.promotion.designationPromotion}</p>
                        <p><strong>Session:</strong> ${data.session.designSession}</p>
                        <p><strong>Année académique:</strong> ${data.annee.designation}</p>
                        <p><strong>Date de délibération:</strong> ${formatDate(data.deliberation.date_deliberation)}</p>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Matricule</th>
                                <th>Nom de l'étudiant</th>
                                <th>Moyenne Générale</th>
                                <th>Crédits Acquis</th>
                                <th>Crédits Total</th>
                                <th>Mention</th>
                                <th>Décision</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            // Ajouter les lignes pour chaque étudiant
            data.resultats.forEach((resultat, index) => {
                const moyenneClass = resultat.moyenne_generale < 10 ? 'text-danger' : '';
                const decisionClass = resultat.decision === 'Admis' ? 'text-success' : 
                                     (resultat.decision === 'Admis par compensation' ? 'text-warning' : 'text-danger');
                
                printContent += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${resultat.matricule}</td>
                        <td>${resultat.nom_etudiant}</td>
                        <td class="${moyenneClass}">${resultat.moyenne_generale}</td>
                        <td>${resultat.credits_acquis}</td>
                        <td>${resultat.credits_total}</td>
                        <td>${resultat.mention || 'N/A'}</td>
                        <td class="${decisionClass}">${resultat.decision}</td>
                    </tr>
                `;
            });
            
            printContent += `
                        </tbody>
                    </table>
                    
                    <div class="footer">
                        <p>Document généré le ${formatDate(new Date().toISOString())}</p>
                        <p>Signature du président du jury: _______________________</p>
                    </div>
                </body>
                </html>
            `;
            
            // Écrire le contenu dans la nouvelle fenêtre
            printWindow.document.write(printContent);
            printWindow.document.close();
            
                        // Lancer l'impression après le chargement complet de la page
                        printWindow.onload = function() {
                printWindow.print();
                // printWindow.close(); // Optionnel: fermer la fenêtre après l'impression
            };
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de la récupération des données pour l\'impression.'
            });
            printWindow.close();
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la communication avec le serveur.'
        });
        printWindow.close();
    });
}

// Fonction pour générer un PV de délibération
function genererPV(type) {
    if (!deliberationId) return;
    
    window.location.href = `controller/generer_pv.php?deliberation=${deliberationId}&type=${type}`;
}

// Fonction pour générer un palmarès
function genererPalmares(type) {
    if (!deliberationId) return;
    
    window.location.href = `controller/generer_palmares.php?deliberation=${deliberationId}&type=${type}`;
}

// Fonction pour générer des statistiques
function genererStatistiques(type) {
    if (!deliberationId) return;
    
    window.location.href = `controller/generer_statistiques.php?deliberation=${deliberationId}&type=${type}`;
}

// Fonction pour générer un relevé de notes
function genererReleve(type) {
    if (!deliberationId) return;
    
    if (type === 'individuel') {
        const matricule = document.getElementById('etudiantReleve').value;
        if (!matricule) {
            Swal.fire({
                icon: 'warning',
                title: 'Attention',
                text: 'Veuillez sélectionner un étudiant.'
            });
            return;
        }
        
        window.location.href = `controller/generer_releve.php?deliberation=${deliberationId}&matricule=${matricule}`;
    } else if (type === 'tous') {
        window.location.href = `controller/generer_releves_zip.php?deliberation=${deliberationId}`;
    }
}

// Fonction pour valider la délibération
function validerDeliberation() {
    if (!deliberationId) return;
    
    // Ouvrir le modal de confirmation
    confirmationModal.show();
}

// Fonction pour valider définitivement la délibération
function validerDefinitivement() {
    // Fermer le modal de confirmation
    confirmationModal.hide();
    
    // Afficher un indicateur de chargement
    Swal.fire({
        title: 'Validation en cours...',
        text: 'Veuillez patienter pendant la validation de la délibération.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Envoyer la requête pour valider la délibération
    fetch('controller/valider_deliberation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            deliberationId: deliberationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Délibération validée',
                text: 'La délibération a été validée avec succès.',
                confirmButtonText: 'Continuer'
            }).then(() => {
                // Recharger la page
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: data.message || 'Une erreur est survenue lors de la validation de la délibération.'
            });
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur est survenue lors de la communication avec le serveur.'
        });
    });
}

// Fonction pour recalculer les résultats
function recalculerResultats() {
    if (!deliberationId) return;
    
    Swal.fire({
        title: 'Recalculer les résultats',
        text: 'Êtes-vous sûr de vouloir recalculer tous les résultats? Cette opération peut prendre plusieurs minutes.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Oui, recalculer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            // Lancer le processus de délibération à nouveau
            lancerDeliberation();
        }
    });
}

// Fonction pour annuler la délibération
function annulerDeliberation() {
    if (!deliberationId) return;
    
    Swal.fire({
        title: 'Annuler la délibération',
        text: 'Êtes-vous sûr de vouloir annuler cette délibération? Toutes les modifications seront perdues.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui, annuler',
        cancelButtonText: 'Non, conserver',
        confirmButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
            // Afficher un indicateur de chargement
            Swal.fire({
                title: 'Annulation en cours...',
                text: 'Veuillez patienter pendant l\'annulation de la délibération.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Envoyer la requête pour annuler la délibération
            fetch('controller/annuler_deliberation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    deliberationId: deliberationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Délibération annulée',
                        text: 'La délibération a été annulée avec succès.',
                        confirmButtonText: 'Continuer'
                    }).then(() => {
                        // Rediriger vers la page de sélection
                        window.location.href = 'deliberation/deliberation_process';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Une erreur est survenue lors de l\'annulation de la délibération.'
                    });
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la communication avec le serveur.'
                });
            });
        }
    });
}
</script>

<?php include "./views/include/footer.php"; ?>


