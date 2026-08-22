<?php
include_once "./views/include/header.php";

$conn = Connexion::getInstance()->getPDO();

// Déterminer l'année académique en cours (la plus récente)
$query_annee_encours = "SELECT idannee_acad, designation FROM annee_acad ORDER BY dateCreation DESC LIMIT 1";
$stmt_annee = $conn->prepare($query_annee_encours);
$stmt_annee->execute();
$annee_encours = $stmt_annee->fetch(PDO::FETCH_ASSOC);
$id_annee_encours = $annee_encours['idannee_acad'];

// Récupérer les étudiants de l'année académique en cours
$query_etudiants = "SELECT e.idetudiant, e.matricule, e.noms, p.idpromotion, p.designationPromotion 
                   FROM etudiant e
                   JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                   WHERE p.annee_acad_idannee_acad = :id_annee
                   ORDER BY e.noms";
$stmt_etudiants = $conn->prepare($query_etudiants);
$stmt_etudiants->bindParam(':id_annee', $id_annee_encours);
$stmt_etudiants->execute();
$etudiants = $stmt_etudiants->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les sessions
$query_sessions = "SELECT idsession, designSession, description FROM session ORDER BY idsession";
$stmt_sessions = $conn->prepare($query_sessions);
$stmt_sessions->execute();
$sessions = $stmt_sessions->fetchAll(PDO::FETCH_ASSOC);

// Vérifier si un étudiant est sélectionné
$etudiant_selectionne = isset($_GET['etudiant']) ? intval($_GET['etudiant']) : null;
$promotion_id = null;

// Si un étudiant est sélectionné, récupérer sa promotion
if ($etudiant_selectionne) {
    $query_promotion = "SELECT p.idpromotion 
                       FROM etudiant e 
                       JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                       WHERE e.idetudiant = :idetudiant";
    $stmt_promotion = $conn->prepare($query_promotion);
    $stmt_promotion->bindParam(':idetudiant', $etudiant_selectionne);
    $stmt_promotion->execute();
    $result = $stmt_promotion->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $promotion_id = $result['idpromotion'];
    }
}

// Récupérer les ECUEs pour la promotion sélectionnée
$ecues = [];
if ($promotion_id) {
    $query_ecues = "SELECT e.idECUE, e.designationECUE, u.designationUE 
                   FROM ecue e 
                   JOIN ue u ON e.UE_idUE = u.idUE
                   JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                   WHERE s.promotion_idpromotion = :promotion_id
                   AND e.estVisible = 1
                   ORDER BY u.designationUE, e.designationECUE";
    $stmt_ecues = $conn->prepare($query_ecues);
    $stmt_ecues->bindParam(':promotion_id', $promotion_id);
    $stmt_ecues->execute();
    $ecues = $stmt_ecues->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer les recours récemment encodés
$query_recours = "SELECT r.id_recours, r.matricule, e.noms as nom_etudiant,
                  ec.designationECUE, r.motif, r.date_creation, r.statut,
                  s.designSession
                  FROM recours r
                  LEFT JOIN etudiant e ON r.matricule = e.matricule
                  LEFT JOIN ecue ec ON r.id_ecue = ec.idECUE
                  LEFT JOIN session s ON r.id_session = s.idsession
                  WHERE r.id_annee_acad = :id_annee
                  ORDER BY r.date_creation DESC
                  LIMIT 10";
$stmt_recours = $conn->prepare($query_recours);
$stmt_recours->bindParam(':id_annee', $id_annee_encours);
$stmt_recours->execute();
$recours = $stmt_recours->fetchAll(PDO::FETCH_ASSOC);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Encoder un Recours Physique</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">Délibération</li>
                <li class="breadcrumb-item active">Recours</li>
            </ol>
        </nav>
    </div>

    <section class="section">
    <div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Formulaire d'encodage de recours</h5>
                
                <!-- Mettre la liste déroulante et l'année académique sur la même ligne -->
                <div class="row align-items-center mb-4">
                    <div class="col-md-6">
                        <form method="GET" action="depot/recours" id="form-selection-etudiant">
                            <label for="etudiant" class="form-label">Sélectionnez un étudiant <span class="text-danger">*</span></label>
                            <select class="form-select" id="etudiant" name="etudiant" required onchange="this.form.submit()">
                                <option value="">Sélectionnez un étudiant...</option>
                                <?php foreach($etudiants as $etud): ?>
                                    <option value="<?= $etud['idetudiant'] ?>" 
                                        <?= ($etudiant_selectionne == $etud['idetudiant']) ? 'selected' : '' ?>>
                                        <?= $etud['noms'] ?> (<?= $etud['matricule'] ?>) - <?= $etud['designationPromotion'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-0 mt-md-4"><strong>Année académique en cours:</strong> <?= $annee_encours['designation'] ?></p>
                    </div>
                </div>
                
                <?php if ($etudiant_selectionne && $promotion_id): ?>
                <!-- Formulaire d'encodage de recours -->
                <form class="row g-3 needs-validation" action="controller/create_recours.php" method="POST" enctype="multipart/form-data" novalidate>
                    <!-- Champs cachés -->
                    <input type="hidden" name="annee_acad" value="<?= $id_annee_encours ?>">
                    <input type="hidden" name="id_createur" value="<?= $_SESSION['id'] ?>">
                    
                    <?php
                    // Trouver le matricule de l'étudiant sélectionné
                    $matricule_etudiant = '';
                    foreach($etudiants as $etud) {
                        if ($etud['idetudiant'] == $etudiant_selectionne) {
                            $matricule_etudiant = $etud['matricule'];
                            break;
                        }
                    }
                    ?>
                    <input type="hidden" name="matricule" value="<?= $matricule_etudiant ?>">
                    
                    <!-- Première ligne: Session et ECUE -->
                    <div class="col-md-6">
                        <label for="session" class="form-label">Session <span class="text-danger">*</span></label>
                        <select class="form-select" id="session" name="session" required>
                            <option value="">Sélectionnez...</option>
                            <?php foreach($sessions as $session): ?>
                                <option value="<?= $session['idsession'] ?>">
                                    <?= $session['designSession'] ?> 
                                    <?= !empty($session['description']) ? '- '.$session['description'] : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner une session.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="ecue" class="form-label">ECUE concerné <span class="text-danger">*</span></label>
                        <select class="form-select" id="ecue" name="ecue" required>
                            <option value="">Sélectionnez...</option>
                            <?php foreach($ecues as $ecue): ?>
                                <option value="<?= $ecue['idECUE'] ?>">
                                    <?= $ecue['designationECUE'] ?> (<?= $ecue['designationUE'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un ECUE.</div>
                    </div>
                    
                    <!-- Deuxième ligne: Motif et Date de dépôt -->
                    <div class="col-md-6">
                        <label for="motif" class="form-label">Motif du recours <span class="text-danger">*</span></label>
                        <select class="form-select" id="motif" name="motif" required>
                            <option value="">Sélectionnez...</option>
                            <option value="Omission de cote">Omission de cote</option>
                            <option value="Calcul inexact">Calcul inexact</option>
                            <option value="Autre">Autre</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un motif.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="date_depot" class="form-label">Date de dépôt physique <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_depot" name="date_depot" 
                               value="<?= date('Y-m-d') ?>" required>
                        <div class="invalid-feedback">Veuillez sélectionner une date.</div>
                    </div>
                    
                    <!-- Troisième ligne: Statut de paiement -->
                    <div class="col-md-6">
                        <label for="est_paye" class="form-label">Frais de recours payés <span class="text-danger">*</span></label>
                        <select class="form-select" id="est_paye" name="est_paye" required>
                            <option value="">Sélectionnez...</option>
                            <option value="1">Oui</option>
                            <option value="0">Non</option>
                        </select>
                        <div class="invalid-feedback">Veuillez indiquer si les frais de recours ont été payés.</div>
                        <div class="form-text">Si le paiement a été effectué, le recours sera immédiatement mis "En traitement".</div>
                    </div>
                    
                    <!-- Description détaillée -->
                    <div class="col-md-12 mt-3">
                        <label for="description" class="form-label">Description détaillée</label>
                        <textarea class="form-control" id="description" name="description" rows="3" 
                                  placeholder="Informations complémentaires sur le recours..."></textarea>
                    </div>
                    
                    <!-- Fichier preuve -->
                    <div class="col-md-12 mt-2">
                        <label for="preuve" class="form-label">Document numérisé (PDF)</label>
                        <input class="form-control" type="file" id="preuve" name="preuve" accept=".pdf">
                        <div class="form-text">Taille maximale: 5 MB. Format accepté: PDF</div>
                    </div>
                    
                    <!-- Boutons de soumission -->
                    <div class="col-12 d-flex justify-content-end mt-4">
                        <button class="btn btn-secondary me-2" type="reset">
                            <i class="bi bi-x-circle me-1"></i> Réinitialiser
                        </button>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-1"></i> Enregistrer le recours
                        </button>
                    </div>
                </form>
                <?php else: ?>
                    <?php if($etudiant_selectionne): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Impossible de trouver les cours pour cet étudiant. Veuillez vérifier que l'étudiant est correctement inscrit.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-1"></i>
                            Veuillez sélectionner un étudiant pour encoder un recours.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Section exportation avec système de déroulement (collapsible) -->
<div class="row mt-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header bg-light" id="headingExport">
                <h5 class="mb-0 d-flex justify-content-between align-items-center">
                    <button class="btn btn-link text-decoration-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExport" aria-expanded="false" aria-controls="collapseExport">
                        <i class="bi bi-file-earmark-pdf me-2"></i>
                        Exportation des recours en PDF
                    </button>
                    <i class="bi bi-chevron-down"></i>
                </h5>
            </div>
            <div id="collapseExport" class="collapse" aria-labelledby="headingExport">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="exportTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">Export général</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="enseignant-tab" data-bs-toggle="tab" data-bs-target="#enseignant" type="button" role="tab" aria-controls="enseignant" aria-selected="false">Export pour enseignant</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content pt-3" id="exportTabContent">
                        <!-- Export général (existant) -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                            <form method="GET" action="controller/export_recours_list.php" target="_blank" class="row g-3">
                                <div class="col-md-4">
                                    <label for="date_debut" class="form-label">Période du</label>
                                    <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                           value="<?= date('Y-m-d', strtotime('-30 days')) ?>" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="date_fin" class="form-label">au</label>
                                    <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="statut_paiement" class="form-label">Statut de paiement</label>
                                    <select class="form-select" id="statut_paiement" name="statut_paiement">
                                        <option value="tous" selected>Tous les recours</option>
                                        <option value="1">Recours payés</option>
                                        <option value="0">Recours non payés</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="statut_recours" class="form-label">Statut du recours</label>
                                    <select class="form-select" id="statut_recours" name="statut_recours">
                                        <option value="tous" selected>Tous les statuts</option>
                                        <option value="En attente">En attente</option>
                                        <option value="En traitement">En traitement</option>
                                        <option value="Approuvé">Approuvé</option>
                                        <option value="Rejeté">Rejeté</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="id_session_filter" class="form-label">Session</label>
                                    <select class="form-select" id="id_session_filter" name="id_session">
                                        <option value="0" selected>Toutes les sessions</option>
                                        <?php foreach($sessions as $session): ?>
                                            <option value="<?= $session['idsession'] ?>">
                                                <?= $session['designSession'] ?> 
                                                <?= !empty($session['description']) ? '- '.$session['description'] : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <input type="hidden" name="annee_acad" value="<?= $id_annee_encours ?>">
                                
                                <div class="col-md-12 d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> Exporter en PDF
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Nouvel export pour enseignant -->
                        <div class="tab-pane fade" id="enseignant" role="tabpanel" aria-labelledby="enseignant-tab">
                            <form method="GET" action="controller/export_recours_enseignant.php" target="_blank" class="row g-3">
                                <!-- Sélection de l'ECUE (cours) -->
                                <div class="col-md-6">
                                    <label for="id_ecue" class="form-label">Sélectionnez un cours (ECUE)</label>
                                    <select class="form-select" id="id_ecue" name="id_ecue" required>
                                        <option value="">Choisir un cours...</option>
                                        <?php
                                        // Récupération des ECUEs avec au moins un recours
                                        $query_ecues_recours = "SELECT DISTINCT e.idECUE, e.designationECUE, u.designationUE, 
                                                               COUNT(r.id_recours) as nb_recours
                                                        FROM ecue e 
                                                        JOIN ue u ON e.UE_idUE = u.idUE
                                                        JOIN recours r ON r.id_ecue = e.idECUE
                                                        WHERE r.id_annee_acad = :id_annee
                                                        AND r.statut = 'En traitement'
                                                        GROUP BY e.idECUE, e.designationECUE, u.designationUE
                                                        ORDER BY u.designationUE, e.designationECUE";
                                        $stmt_ecues_recours = $conn->prepare($query_ecues_recours);
                                        $stmt_ecues_recours->bindParam(':id_annee', $id_annee_encours);
                                        $stmt_ecues_recours->execute();
                                        $ecues_recours = $stmt_ecues_recours->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach($ecues_recours as $ecue):
                                        ?>
                                            <option value="<?= $ecue['idECUE'] ?>">
                                                <?= htmlspecialchars($ecue['designationECUE']) ?> 
                                                (<?= htmlspecialchars($ecue['designationUE']) ?>) 
                                                - <?= $ecue['nb_recours'] ?> recours
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Sélection de la session -->
                                <div class="col-md-6">
                                    <label for="id_session_ens" class="form-label">Session</label>
                                    <select class="form-select" id="id_session_ens" name="id_session" required>
                                        <option value="">Choisir une session...</option>
                                        <?php foreach($sessions as $session): ?>
                                            <option value="<?= $session['idsession'] ?>">
                                                <?= $session['designSession'] ?> 
                                                <?= !empty($session['description']) ? '- '.$session['description'] : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Titre et options supplémentaires -->
                                <div class="col-md-12">
                                    <label for="titre_document" class="form-label">Titre du document</label>
                                    <input type="text" class="form-control" id="titre_document" name="titre_document" 
                                           value="Liste des recours à traiter" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="date_limite" class="form-label">Date limite de réponse</label>
                                    <input type="date" class="form-control" id="date_limite" name="date_limite" 
                                           value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                                    <small class="text-muted">Laissez vide si pas de date limite</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="hauteur_zone" class="form-label">Hauteur de la zone de réponse (mm)</label>
                                    <input type="number" class="form-control" id="hauteur_zone" name="hauteur_zone" 
                                           value="40" min="20" max="100">
                                    <small class="text-muted">Espace pour la réponse de l'enseignant</small>
                                </div>
                                
                                <input type="hidden" name="annee_acad" value="<?= $id_annee_encours ?>">
                                
                                <div class="col-md-12 d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> Générer pour l'enseignant
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




        <!-- Nouvelle section pour rechercher et approuver les paiements de recours -->
    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recherche et approbation des paiements de recours</h5>
                    
                    <!-- Formulaire de recherche -->
                    <form method="GET" action="" class="row g-3 mb-4">
                        <input type="hidden" name="action" value="recherche_recours">
                        
                        <div class="col-md-4">
                            <label for="search_term" class="form-label">Rechercher un étudiant</label>
                            <input type="text" class="form-control" id="search_term" name="search_term" 
                                   placeholder="Matricule ou nom de l'étudiant"
                                   value="<?= isset($_GET['search_term']) ? htmlspecialchars($_GET['search_term']) : '' ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="statut_filter" class="form-label">Filtrer par statut</label>
                            <select class="form-select" id="statut_filter" name="statut_filter">
                                <option value="" <?= !isset($_GET['statut_filter']) || $_GET['statut_filter'] === '' ? 'selected' : '' ?>>Tous</option>
                                <option value="En attente" <?= isset($_GET['statut_filter']) && $_GET['statut_filter'] === 'En attente' ? 'selected' : '' ?>>En attente</option>
                                <option value="En traitement" <?= isset($_GET['statut_filter']) && $_GET['statut_filter'] === 'En traitement' ? 'selected' : '' ?>>En traitement</option>
                                <option value="Approuvé" <?= isset($_GET['statut_filter']) && $_GET['statut_filter'] === 'Approuvé' ? 'selected' : '' ?>>Approuvé</option>
                                <option value="Rejeté" <?= isset($_GET['statut_filter']) && $_GET['statut_filter'] === 'Rejeté' ? 'selected' : '' ?>>Rejeté</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i> Rechercher
                            </button>
                        </div>
                    </form>
                    
                    <?php
                    // Traitement de la recherche si demandé
                    if (isset($_GET['action']) && $_GET['action'] === 'recherche_recours' && !empty($_GET['search_term'])) {
                        $search_term = trim($_GET['search_term']);
                        $statut_filter = isset($_GET['statut_filter']) ? trim($_GET['statut_filter']) : '';
                        
                        // Construire la requête de recherche
                        $query_search = "
                            SELECT r.id_recours, r.matricule, e.noms as nom_etudiant, p.designationPromotion,
                                  ec.designationECUE, r.motif, r.date_creation, r.statut, r.est_paye,
                                  s.designSession
                            FROM recours r
                            JOIN etudiant e ON r.matricule = e.matricule
                            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                            JOIN ecue ec ON r.id_ecue = ec.idECUE
                            JOIN session s ON r.id_session = s.idsession
                            WHERE (r.matricule LIKE :search_term OR e.noms LIKE :search_term_like)
                            AND r.id_annee_acad = :id_annee";
                        
                        if (!empty($statut_filter)) {
                            $query_search .= " AND r.statut = :statut_filter";
                        }
                        
                        $query_search .= " ORDER BY r.date_creation DESC";
                        
                        $stmt_search = $conn->prepare($query_search);
                        $stmt_search->bindValue(':search_term', $search_term);
                        $stmt_search->bindValue(':search_term_like', '%' . $search_term . '%');
                        $stmt_search->bindValue(':id_annee', $id_annee_encours);
                        
                        if (!empty($statut_filter)) {
                            $stmt_search->bindValue(':statut_filter', $statut_filter);
                        }
                        
                        $stmt_search->execute();
                        $resultats_recherche = $stmt_search->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($resultats_recherche) > 0) {
                            ?>
                            <h6 class="mt-4">Résultats de la recherche (<?= count($resultats_recherche) ?> recours trouvés)</h6>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Matricule</th>
                                            <th>Nom étudiant</th>
                                            <th>ECUE</th>
                                            <th>Session</th>
                                            <th>Date de dépôt</th>
                                            <th>Statut</th>
                                            <th>Payé</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($resultats_recherche as $rec): ?>
                                            <?php
                                            // Définir une classe CSS pour le statut
                                            $statut_class = '';
                                            switch($rec['statut']) {
                                                case 'En attente': $statut_class = 'badge bg-warning'; break;
                                                case 'En traitement': $statut_class = 'badge bg-info'; break;
                                                case 'Approuvé': $statut_class = 'badge bg-success'; break;
                                                case 'Rejeté': $statut_class = 'badge bg-danger'; break;
                                                default: $statut_class = 'badge bg-secondary';
                                            }
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($rec['matricule']) ?></td>
                                                <td><?= htmlspecialchars($rec['nom_etudiant']) ?></td>
                                                <td><?= htmlspecialchars($rec['designationECUE']) ?></td>
                                                <td><?= htmlspecialchars($rec['designSession']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($rec['date_creation'])) ?></td>
                                                <td><span class="<?= $statut_class ?>"><?= htmlspecialchars($rec['statut']) ?></span></td>
                                                <td>
                                                    <?php if ($rec['est_paye'] == 1): ?>
                                                        <span class="badge bg-success">Oui</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Non</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($rec['est_paye'] == 0 && $rec['statut'] == 'En attente'): ?>
                                                            <button type="button" class="btn btn-sm btn-success approuverPaiement" 
                                                                    data-id="<?= $rec['id_recours'] ?>" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#approuverPaiementModal"
                                                                    title="Approuver le paiement">
                                                                <i class="bi bi-cash-coin"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                        } else {
                            echo '<div class="alert alert-info mt-3">Aucun recours trouvé pour cette recherche.</div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>



        <!-- Section pour afficher les recours récemment encodés -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recours récemment encodés</h5>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Nom étudiant</th>
                                        <th>ECUE</th>
                                        <th>Session</th>
                                        <th>Motif</th>
                                        <th>Date de dépôt</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (empty($recours)) {
                                        echo '<tr><td colspan="8" class="text-center">Aucun recours encodé récemment</td></tr>';
                                    } else {
                                        foreach ($recours as $r) {
                                            // Définir une classe CSS pour le statut
                                            $statut_class = '';
                                            switch($r['statut']) {
                                                case 'En attente': $statut_class = 'badge bg-warning'; break;
                                                case 'En traitement': $statut_class = 'badge bg-info'; break;
                                                case 'Approuvé': $statut_class = 'badge bg-success'; break;
                                                case 'Rejeté': $statut_class = 'badge bg-danger'; break;
                                                default: $statut_class = 'badge bg-secondary';
                                            }
                                            
                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($r['matricule']) . '</td>';
                                            echo '<td>' . htmlspecialchars($r['nom_etudiant']) . '</td>';
                                            echo '<td>' . htmlspecialchars($r['designationECUE']) . '</td>';
                                            echo '<td>' . htmlspecialchars($r['designSession']) . '</td>';
                                            echo '<td>' . htmlspecialchars($r['motif']) . '</td>';
                                            echo '<td>' . date('d/m/Y', strtotime($r['date_creation'])) . '</td>';
                                            echo '<td><span class="' . $statut_class . '">' . htmlspecialchars($r['statut']) . '</span></td>';
                                            
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


        <div class="modal fade" id="approuverPaiementModal" tabindex="-1" aria-labelledby="approuverPaiementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approuverPaiementModalLabel">Approuver le paiement du recours</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="controller/approuver_paiement_recours.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="id_recours" id="id_recours_modal" value="">
                        <p>Êtes-vous sûr de vouloir confirmer que ce recours a été payé?</p>
                        <p>Le statut du recours passera à "En traitement".</p>
                        
                        <div class="mb-3">
                            <label for="reference_paiement" class="form-label">Référence du paiement (optionnel)</label>
                            <input type="text" class="form-control" id="reference_paiement" name="reference_paiement" placeholder="Ex: Reçu #12345">
                        </div>
                        
                        <div class="mb-3">
                            <label for="date_paiement" class="form-label">Date du paiement</label>
                            <input type="date" class="form-control" id="date_paiement" name="date_paiement" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">Confirmer le paiement</button>
                    </div>
                </form>
            </div>
        </div>
    </div> 

        



    </section>
</main>

<script>

// Gestion du modal d'approbation de paiement
document.querySelectorAll('.approuverPaiement').forEach(function(button) {
    button.addEventListener('click', function() {
        const recours_id = this.getAttribute('data-id');
        document.getElementById('id_recours_modal').value = recours_id;
    });
});

document.addEventListener('DOMContentLoaded', function() {
    // Validation du formulaire Bootstrap
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
    
    // Ajouter une validation spécifique pour le fichier PDF
    document.getElementById('preuve')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Vérifier l'extension du fichier
            const fileName = file.name;
            const fileExt = fileName.split('.').pop().toLowerCase();
            
            if (fileExt !== 'pdf') {
                alert('Seuls les fichiers PDF sont autorisés.');
                this.value = ''; // Réinitialiser le champ
                return;
            }
            
            // Vérifier la taille du fichier (5MB max)
            const fileSize = file.size / 1024 / 1024; // en MB
            if (fileSize > 5) {
                alert('La taille du fichier ne doit pas dépasser 5 MB.');
                this.value = ''; // Réinitialiser le champ
            }
        }
    });
    
    
});
</script>

<?php include_once "./views/include/footer_file.php"; ?>
