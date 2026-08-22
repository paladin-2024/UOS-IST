<?php
require_once "head_student.php";

// Set page title for mobile header
$pageTitle = 'Portail Étudiant';
$currentPage = 'student';

function getGradePrefix($grade) {
    if (empty($grade)) return '';
    return $grade . ' ';
}
?>

<?php include "includes/mobile_header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Content Area -->
<div class="content-area">
    <!-- Tabs (scrollable on mobile) -->
    <div class="nav-pills-wrapper">
        <ul class="nav nav-pills mb-4" id="mainTab" role="tablist">
            <?php if ($estPromotionTerminale): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="subjects-tab" data-bs-toggle="tab"
                        data-bs-target="#subjects" type="button">
                        <i class="fas fa-folder me-1"></i> Sujets
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tasks-tab" data-bs-toggle="tab"
                        data-bs-target="#tasks" type="button">
                        <i class="fas fa-list me-1"></i> Tâches
                    </button>
                </li>
                <?php if ($sujetAssigne && $sujetAssigne['statut_validation'] === 'Validé'): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="plan-tab" data-bs-toggle="tab"
                            data-bs-target="#plan" type="button">
                            <i class="fas fa-clipboard-list me-1"></i> Plan de Travail
                        </button>
                    </li>
                <?php endif; ?>
            <?php endif; ?>
            <!-- Ajouter un nouvel onglet pour les cours -->
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= !$estPromotionTerminale ? 'active' : '' ?>" id="courses-tab" data-bs-toggle="tab"
                    data-bs-target="#courses" type="button">
                    <i class="fas fa-book me-1"></i> Cours
                </button>
            </li>
            <?php
            // Vérifier si l'étudiant est chef de promotion
            $connexion = Connexion::getInstance()->getPDO();
            $estChefPromotion = false;

            try {
                // Requête améliorée pour vérifier le chef de promotion
                $queryChef = "SELECT cp.id_chef, cp.promotion_idpromotion, cp.annee_acad_idannee_acad,
                                     e.noms as nom_etudiant, p.\"designationPromotion\", aa.designation as annee_acad
                              FROM chef_promotion cp 
                              INNER JOIN etudiant e ON cp.idetudiant = e.idetudiant 
                              INNER JOIN promotion p ON cp.promotion_idpromotion = p.idpromotion
                              INNER JOIN annee_acad aa ON cp.annee_acad_idannee_acad = aa.idannee_acad
                              WHERE e.idetudiant = :student_id 
                              AND cp.est_actif = 1
                              ORDER BY cp.date_nomination DESC
                              LIMIT 1";

                $stmtChef = $connexion->prepare($queryChef);
                $stmtChef->bindParam(':student_id', $studentId, PDO::PARAM_INT);
                $stmtChef->execute();
                $chefInfo = $stmtChef->fetch(PDO::FETCH_ASSOC);

                if ($chefInfo) {
                    $estChefPromotion = $chefInfo;
                }
            } catch (Exception $e) {
                // En cas d'erreur, log l'erreur et continuer
                error_log("Erreur lors de la vérification du chef de promotion: " . $e->getMessage());
                $estChefPromotion = false;

                if (isset($_GET['debug']) && $_GET['debug'] == '1') {
                    echo "<!-- DEBUG ERROR: " . $e->getMessage() . " -->";
                }
            }
            ?>
            <?php if ($estChefPromotion): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="suivi-enseignements-tab" data-bs-toggle="tab"
                        data-bs-target="#suivi-enseignements" type="button">
                        <i class="fas fa-chalkboard-teacher me-1"></i> Suivi Enseignements
                    </button>
                </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="evaluations-tab" data-bs-toggle="tab"
                    data-bs-target="#evaluations" type="button">
                    <i class="fas fa-chart-bar me-1"></i> Points
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="schedule-tab" data-bs-toggle="tab"
                    data-bs-target="#schedule" type="button">
                    <i class="fas fa-calendar-alt me-1"></i> Horaire
                </button>
            </li>
            <?php if ($deliberationPubliee): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="recours-tab" data-bs-toggle="tab"
                        data-bs-target="#recours" type="button">
                        <i class="fas fa-gavel me-1"></i> Recours
                    </button>
                </li>
            <?php endif; ?>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="paiements-tab" data-bs-toggle="tab"
                    data-bs-target="#paiements" type="button">
                    <i class="fas fa-mobile-alt me-1"></i> Paiements
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="messages-tab" data-bs-toggle="tab"
                    data-bs-target="#messages" type="button">
                    <i class="fas fa-comments me-1"></i> Messages
                </button>
            </li>
        </ul>
    </div><!-- /.nav-pills-wrapper -->

    <!-- Tab Content -->
    <div class="tab-content" id="mainTabContent">
        <?php if ($estPromotionTerminale): ?>
            <!-- Subjects Tab -->
            <div class="tab-pane fade show active" id="subjects" role="tabpanel">
                <?php
                // Vérifier si l'étudiant a déjà un sujet
                $aSujetEnAttente = false;
                $sujetStatus = '';
                if (!empty($sujetAssigne)) {
                    $sujetStatus = $sujetAssigne['statut_validation'] ?? '';
                    $aSujetEnAttente = ($sujetStatus !== 'A reformulé');
                }
                ?>
                
                <?php if ($aSujetEnAttente): ?>
                    <div class="alert alert-warning d-flex align-items-center mb-4">
                        <i class="fas fa-clock me-3 fs-4"></i>
                        <div>
                            <strong>Vous avez déjà un sujet en attente de validation.</strong><br>
                            <span class="text-muted">Sujet: <?= htmlspecialchars($sujetAssigne['intitule'] ?? '') ?></span><br>
                            <span class="badge bg-<?= $sujetStatus === 'Validé' ? 'success' : ($sujetStatus === 'A reformulé' ? 'danger' : 'warning') ?>">
                                <?= htmlspecialchars($sujetStatus) ?>
                            </span>
                            <p class="mb-0 mt-2 small">Vous ne pouvez pas proposer un nouveau sujet tant que le sujet actuel n'est pas rejeté par la commission.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    // Vérifier les frais requis pour le sujet
                    $sujetFeesPaid = $etudiantModel->hasStudentPaidSujetFees($studentId);
                    $sujetFeesStatus = $etudiantModel->getSujetFeesStatus($studentId);
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0 fw-bold text-primary">Sujets disponibles</h4>
                        <?php if ($sujetFeesPaid): ?>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#proposerSujetModal">
                            <i class="fas fa-plus-circle"></i><span class="d-none d-sm-inline ms-2">Proposer un sujet</span>
                        </button>
                        <?php else: ?>
                        <button type="button" class="btn btn-secondary" disabled title="Vous devez d'abord payer les frais requis">
                            <i class="fas fa-lock"></i><span class="d-none d-sm-inline ms-2">Proposer un sujet</span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!$sujetFeesPaid && !empty($sujetFeesStatus)): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        <strong>Paiement requis:</strong> Vous ne pouvez pas proposer de sujet tant que les frais suivants ne sont pas payé:
                        <ul class="mb-0 mt-2">
                            <?php foreach ($sujetFeesStatus as $fee): ?>
                            <li>
                                <?php echo htmlspecialchars($fee['designation']); ?>
                                (<?php echo number_format($fee['montant'], 2); ?> <?php echo htmlspecialchars($fee['devise']); ?>)
                                <?php if ($fee['statut_paiement'] === 'paye'): ?>
                                    <span class="badge bg-success ms-2">✓ Payé</span>
                                <?php elseif ($fee['statut_paiement'] === 'partiel'): ?>
                                    <span class="badge bg-warning ms-2">Paiement partiel (<?php echo number_format($fee['montantPaye'], 2); ?> / <?php echo number_format($fee['montant'], 2); ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-2">À payer</span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <small class="d-block mt-2">Accédez à la section "Frais Académiques" pour effectuer vos paiements.</small>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (empty($sujetsDisponibles) && !$aSujetEnAttente): ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle me-3 fs-4"></i>
                        <div>
                            Aucun sujet disponible pour le moment. Vous pouvez proposer votre propre sujet en cliquant sur le bouton ci-dessus.
                        </div>
                    </div>
                <?php elseif (!empty($sujetsDisponibles) && !$aSujetEnAttente): ?>
                    <div class="row">
                        <?php foreach ($sujetsDisponibles as $sujet): ?>
                            <div class="col-md-6 mb-4">
                                <div class="subject-card h-100">
                                    <h5 class="card-title d-flex align-items-center">
                                        <i class="fas fa-bookmark text-primary me-2"></i>
                                        <?= htmlspecialchars($sujet['intitule']) ?>
                                    </h5>
                                    <div class="mb-3">
                                        <span class="badge bg-info mb-2">
                                            <i class="fas fa-microscope me-1"></i>
                                            <?= htmlspecialchars($sujet['unite_recherche']) ?>
                                        </span>
                                        <p class="text-muted mb-0 small">
                                            <i class="fas fa-tag me-1"></i>
                                            Spécialisation: <?= htmlspecialchars($sujet['specialisation']) ?>
                                        </p>
                                    </div>
                                    <div class="d-grid">
                                        <button type="button" class="btn btn-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#choixEncadrantsModal<?= $sujet['idsujets'] ?>">
                                            <i class="fas fa-check-circle me-2"></i>Choisir ce sujet
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Modals pour chaque sujet -->
                    <?php foreach ($sujetsDisponibles as $sujet): ?>
                        <div class="modal fade" id="choixEncadrantsModal<?= $sujet['idsujets'] ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-user-check me-2"></i>
                                            Choisir les encadrants
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="../controller/choisir_sujet.php" method="POST"
                                        id="formChoixSujet<?= $sujet['idsujets'] ?>" class="needs-validation" novalidate>
                                        <div class="modal-body">
                                            <input type="hidden" name="sujet_id" value="<?= $sujet['idsujets'] ?>">

                                            <!-- Détails du sujet -->
                                            <div class="mb-4 p-3 bg-light rounded">
                                                <h6 class="fw-bold text-primary mb-3">Sujet sélectionné :</h6>
                                                <p class="mb-1 fw-bold fs-5"><?= htmlspecialchars($sujet['intitule']) ?></p>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-microscope text-primary me-2"></i>
                                                    <span class="text-muted">
                                                        <?= htmlspecialchars($sujet['unite_recherche']) ?>
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-tag text-primary me-2"></i>
                                                    <span class="text-muted">
                                                        <?= htmlspecialchars($sujet['specialisation']) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="row g-3">
                                                <!-- Sélection du Directeur -->
                                                <div class="col-md-6">
                                                    <label for="directeur<?= $sujet['idsujets'] ?>" class="form-label required">
                                                        <i class="fas fa-user-tie me-1"></i> Directeur de mémoire
                                                    </label>
                                                    <select class="form-select select2" id="directeur<?= $sujet['idsujets'] ?>"
                                                        name="directeur_id" required>
                                                        <option value="">Sélectionnez un directeur</option>
                                                        <?php
                                                        if (isset($estFinalistePremierCycle) && $estFinalistePremierCycle) {
                                                            // Premier cycle finaliste : toutes les catégories d'enseignant
                                                            $directeursFiltres = $agentModel->getAgentsByType('Enseignant');
                                                            $autresTypes = ['Assistant', 'Chef de travaux', 'ATER', 'Vacataire'];
                                                            foreach ($autresTypes as $type) {
                                                                $agents = $agentModel->getAgentsByType($type);
                                                                if (is_array($agents)) {
                                                                    $directeursFiltres = array_merge($directeursFiltres, $agents);
                                                                }
                                                            }
                                                        } else {
                                                            // Récupération des enseignants depuis la table agent
                                                            $directeurs = $agentModel->getAgentsByType('Enseignant');
                                                            // Filtrer les directeurs pour n'avoir que les grades Dr, Prof, PA, PO
                                                            $directeursFiltres = array_filter($directeurs, function($d) {
                                                                $grade = $d['gradeDesignation'] ?? '';
                                                                return in_array($grade, ['Dr', 'Prof', 'PA', 'PO']);
                                                            });
                                                        }
                                                        foreach ($directeursFiltres as $directeur):
                                                        ?>
                                                            <option value="<?= $directeur['idAgent'] ?>">
                                                                <?= htmlspecialchars($directeur['gradeDesignation'] . " " . $directeur['noms']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="invalid-feedback">Veuillez sélectionner un directeur.</div>
                                                </div>

                                                <!-- Sélection de l'Encadrant -->
                                                <div class="col-md-6">
                                                    <label for="encadreur<?= $sujet['idsujets'] ?>" class="form-label">
                                                        <i class="fas fa-user-graduate me-1"></i> Encadrant (optionnel)
                                                    </label>
                                                    <select class="form-select select2" id="encadreur<?= $sujet['idsujets'] ?>"
                                                        name="encadreur_id">
                                                        <option value="">Sélectionnez un encadrant</option>
                                                        <?php
                                                        // Pour l'encadreur, tous les grades sont autorisés - récupérer tous les agents avec roles d'enseignement
                                                        $db = Connexion::getInstance()->getPDO();
                                                        $queryEnseignants = "SELECT a.\"idAgent\", a.noms, g.designation as gradeDesignation 
                                                                            FROM agent a 
                                                                            LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                                                            WHERE a.type_agent IN ('Enseignant', 'Assistant', 'Chef de travaux', 'ATER', 'Vacataire')
                                                                            ORDER BY g.designation, a.noms ASC";
                                                        $stmtEnseignants = $db->prepare($queryEnseignants);
                                                        $stmtEnseignants->execute();
                                                        $tousEnseignants = $stmtEnseignants->fetchAll(PDO::FETCH_ASSOC);
                                                        
                                                        foreach ($tousEnseignants as $encadreur): 
                                                            $grade = $encadreur['gradeDesignation'] ?? '';
                                                            $gradePrefix = getGradePrefix($grade);
                                                        ?>
                                                            <option value="<?= $encadreur['idAgent'] ?>">
                                                                <?= htmlspecialchars($gradePrefix . $encadreur['noms']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                                    <div class="alert alert-info mt-4">
                                                <div class="d-flex">
                                                    <i class="fas fa-info-circle me-3 fs-4"></i>
                                                    <div>
                                                        <strong>Information importante</strong>
                                                        <p class="mb-0 small">
                                                            Le choix du sujet et des encadrants devra être validé par l'administration.
                                                            Assurez-vous de choisir des encadrants différents pour le directeur et l'encadrant.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                <i class="fas fa-times me-2"></i>Annuler
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-check me-2"></i>Confirmer le choix
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($aSujetEnAttente): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">Vous pouvez néanmoins consulter les sujets disponibles proposés par d'autres étudiants.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tasks Tab -->
            <div class="tab-pane fade" id="tasks" role="tabpanel">
                <?php if ($sujetAssigne): ?>
                    <div class="card mb-4 border-0 shadow-sm">
                        <div class="card-header bg-primary text-white py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bookmark me-2"></i>Mon sujet
                                </h5>
                                <div class="d-flex gap-2">
                                    <!-- Bouton de modification (visible seulement si pas validé) -->
                                    <?php if ($sujetAssigne['statut_validation'] !== 'Validé'): ?>
                                        <?php if ($sujetAssigne['statut_validation'] === 'A reformulé'): ?>
                                            <!-- Bouton pour proposer une reformulation -->
                                            <button type="button" class="btn btn-info btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#proposerReformulationModal">
                                                <i class="fas fa-lightbulb me-2"></i>Proposer une reformulation
                                            </button>
                                            <!-- Bouton pour voir l'historique -->
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                onclick="voirHistoriqueSujet(<?= $sujetAssigne['idsujets'] ?>)">
                                                <i class="fas fa-history me-2"></i>Historique
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-warning btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modifierSujetModal">
                                                <i class="fas fa-edit me-2"></i>Modifier le sujet
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <!-- Bouton pour télécharger la fiche de dépôt (toujours disponible) -->
                                    <a target="_blank" href="../controller/export_fiche_depot.php?etudiant_id=<?= $studentId ?>"
                                        class="btn btn-light btn-sm">
                                        <i class="fas fa-file-pdf me-2"></i>Fiche de dépôt PDF
                                    </a>

                                    <!-- Bouton pour exporter la fiche d'avancement (seulement si validé) -->
                                    <?php if ($sujetAssigne && $sujetAssigne['statut_validation'] === 'Validé'): ?>
                                        <a target="_blank" href="export_fiche_avancement?etudiant_id=<?= $studentId ?>"
                                            class="btn btn-light btn-sm">
                                            <i class="fas fa-file-export me-2"></i>Fiche d'avancement PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="fw-bold mb-3"><?= htmlspecialchars($sujetAssigne['intitule']) ?></h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-microscope text-primary me-2"></i>
                                        <span>Unité de recherche : <?= htmlspecialchars($sujetAssigne['unite_recherche'] ?? '') ?></span>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-user-tie text-primary me-2"></i>
                                        <span>Directeur : <?= htmlspecialchars($sujetAssigne['directeur'] ?? '') ?></span>
                                    </div>
                                    <?php if (!empty($sujetAssigne['encadreur'])): ?>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-graduate text-primary me-2"></i>
                                            <span>Encadrant : <?= htmlspecialchars($sujetAssigne['encadreur']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 text-md-end">
                                    <span class="badge bg-<?= getStatusColor($sujetAssigne['statut_validation']) ?> p-2">
                                        <i class="fas fa-<?= $sujetAssigne['statut_validation'] === 'Validé' ? 'check-circle' : ($sujetAssigne['statut_validation'] === 'A reformulé' ? 'times-circle' : 'clock') ?> me-1"></i>
                                        <?= htmlspecialchars($sujetAssigne['statut_validation']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($sujetAssigne['statut_validation'] === 'Validé'): ?>
                        <!-- Liste des tâches -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-primary">
                                <i class="fas fa-tasks me-2"></i>Mes tâches
                            </h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                                <i class="fas fa-plus-circle me-2"></i>Nouvelle tâche
                            </button>
                        </div>

                        <?php if (empty($taches)): ?>
                            <div class="alert alert-info d-flex align-items-center">
                                <i class="fas fa-info-circle me-3 fs-4"></i>
                                <div>
                                    Aucune tâche pour le moment. Vous pouvez créer une nouvelle tâche en cliquant sur le bouton ci-dessus.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($taches as $tache):
                                    $echanges = $etudiantModel->getEchangesTache($tache['idtaches']);
                                ?>
                                    <div class="col-md-12 mb-4">
                                        <div class="task-card">
                                            <!-- En-tête de la tâche -->
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h5 class="fw-bold text-primary mb-1">
                                                        <i class="fas fa-clipboard-list me-2"></i><?= htmlspecialchars($tache['description']) ?>
                                                    </h5>
                                                    <div class="d-flex align-items-center text-muted">
                                                        <i class="fas fa-calendar-alt me-2"></i>
                                                        <span>Créée le <?= date('d/m/Y', strtotime($tache['dateTache'])) ?></span>
                                                    </div>
                                                </div>
                                                <span class="status-badge bg-<?= getStatusColor($tache['validation']) ?>">
                                                    <i class="fas fa-<?= $tache['validation'] === 'Validé' ? 'check-circle' : ($tache['validation'] === 'A reformulé' ? 'times-circle' : 'clock') ?> me-1"></i>
                                                    <?= htmlspecialchars($tache['validation']) ?>
                                                </span>
                                            </div>

                                            <!-- Fichier initial de la tâche -->
                                            <?php if ($tache['fichierTache']): ?>
                                                <div class="mb-3 p-3 bg-light rounded">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-file-alt text-primary me-3 fs-4"></i>
                                                        <div>
                                                            <h6 class="mb-1">Fichier initial</h6>
                                                            <a href="../uploads/taches/<?= htmlspecialchars($tache['fichierTache']) ?>"
                                                                class="btn btn-sm btn-outline-primary" target="_blank">
                                                                <i class="fas fa-download me-1"></i> Télécharger
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Timeline des échanges -->
                                            <?php if (!empty($echanges)): ?>
                                                <h6 class="fw-bold mb-3">
                                                    <i class="fas fa-comments me-2"></i>Échanges (<?= count($echanges) ?>)
                                                </h6>
                                                <div class="timeline mb-3">
                                                    <?php foreach ($echanges as $echange): ?>
                                                        <?php
                                                        // Vérifie si l'auteur du message est l'utilisateur actuel
                                                        $isCurrentUser = ($echange['idAuteur'] == $_SESSION['student_id']);
                                                        $typeClass = getTypeAuteurClass($echange['type_auteur'], $isCurrentUser);
                                                        $auteurLabel = getTypeAuteurLabel($echange['type_auteur'], $isCurrentUser);
                                                        ?>
                                                        <div class="timeline-item">
                                                            <div class="timeline-marker bg-<?= $typeClass ?>"></div>
                                                            <div class="timeline-content">
                                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                                    <strong class="text-<?= $typeClass ?>">
                                                                        <i class="fas fa-<?= $isCurrentUser ? 'user' : ($echange['type_auteur'] === 'Directeur' ? 'user-tie' : ($echange['type_auteur'] === 'Encadrant' ? 'user-graduate' : 'user')) ?> me-1"></i>
                                                                        <?= htmlspecialchars($auteurLabel) ?>
                                                                    </strong>
                                                                    <small class="text-muted">
                                                                        <i class="fas fa-clock me-1"></i>
                                                                        <?= date('d/m/Y H:i', strtotime($echange['dateEchange'])) ?>
                                                                    </small>
                                                                </div>
                                                                <div class="p-3 bg-white rounded mb-2">
                                                                    <?= nl2br(htmlspecialchars($echange['commentaire'])) ?>
                                                                </div>
                                                                <?php if ($echange['"fichierJoint"']): ?>
                                                                    <div class="d-flex align-items-center">
                                                                        <i class="fas fa-paperclip text-muted me-2"></i>
                                                                        <a href="../uploads/echanges/<?= htmlspecialchars($echange['"fichierJoint"']) ?>"
                                                                            class="btn btn-sm btn-outline-secondary" target="_blank">
                                                                            <i class="fas fa-file-download me-1"></i>
                                                                            Voir le fichier joint
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Bouton de réponse -->
                                            <div class="d-grid mt-3">
                                                <button class="btn btn-primary"
                                                    onclick="showReplyModal(<?= $tache['idtaches'] ?>)">
                                                    <i class="fas fa-reply me-2"></i> Répondre à cette tâche
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Modal d'ajout de tâche -->
                        <div class="modal fade" id="addTaskModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            <i class="fas fa-plus-circle me-2"></i>Nouvelle tâche
                                        </h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="../controller/ajouter_tache.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="description" class="form-label required">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                                <div class="form-text">Décrivez clairement l'objectif de cette tâche.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="fichier" class="form-label">Fichier (optionnel)</label>
                                                <input type="file" class="form-control" id="fichier" name="fichier">
                                                <div class="form-text">Vous pouvez joindre un document, une image ou tout autre fichier pertinent.</div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                <i class="fas fa-times me-2"></i>Annuler
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Ajouter la tâche
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                            <div>
                                <strong>Attention!</strong> Votre sujet est en attente de validation. Vous pourrez ajouter des tâches une fois qu'il sera validé.
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle me-3 fs-4"></i>
                        <div>
                            <strong>Information</strong>
                            <p class="mb-0">Vous n'avez pas encore de sujet assigné. Veuillez d'abord choisir un sujet dans l'onglet "Sujets".</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Plan de Travail Tab -->
            <?php if ($sujetAssigne && $sujetAssigne['statut_validation'] === 'Validé'): ?>
                <div class="tab-pane fade" id="plan" role="tabpanel">
                    <?php
                    // Intégration native du plan de travail
                    $planModel = new PlanTravail();
                    $planExistant = $planModel->getPlanBySujet($sujetAssigne['idsujets']);

                    // Messages d'action
                    $planMessage = '';
                    $planMessageType = '';
                    if (isset($_SESSION['plan_message'])) {
                        $planMessage = $_SESSION['plan_message'];
                        $planMessageType = $_SESSION['plan_message_type'] ?? 'info';
                        unset($_SESSION['plan_message'], $_SESSION['plan_message_type']);
                    }
                    ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="text-primary fw-bold mb-1">
                            <i class="fas fa-clipboard-list me-2"></i>Plan de Travail
                        </h4>
                    </div>

                    <!-- Messages -->
                    <?php if ($planMessage): ?>
                        <div class="alert alert-<?= $planMessageType ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $planMessageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                            <?= htmlspecialchars($planMessage) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Information du sujet -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-book me-2"></i>Mon Sujet de Recherche</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="fw-bold text-dark"><?= htmlspecialchars($sujetAssigne['intitule']) ?></h6>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <small class="text-muted">Directeur:</small><br>
                                            <span class="fw-semibold"><?= htmlspecialchars($sujetAssigne['directeur'] ?? 'Non assigné') ?></span>
                                        </div>
                                        <div class="col-md-6">
                                            <small class="text-muted">Encadrant:</small><br>
                                            <span class="fw-semibold"><?= htmlspecialchars($sujetAssigne['encadreur'] ?? 'Aucun') ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <span class="badge bg-success fs-6">
                                        <i class="fas fa-check-circle me-1"></i>Validé
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($planExistant): ?>
                        <!-- Plan existant -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0" <i class="fas fa-file-alt me-2"></i>Mon Plan de Travail
                                    <span class="badge bg-<?= $planExistant['statut_validation'] === 'Validé' ? 'success' : ($planExistant['statut_validation'] === 'A reformulé' ? 'danger' : 'warning') ?> ms-2">
                                        <?= htmlspecialchars($planExistant['statut_validation']) ?>
                                    </span>
                                </h5>
                                <div class="btn-group" role="group">
                                    <!-- Bouton pour télécharger le plan -->
                                    <button type="button" class="btn btn-outline-info" onclick="telechargerPlan(<?= $planExistant['idplan_travail'] ?>)">
                                        <i class="fas fa-download me-1"></i>Télécharger
                                    </button>
                                    <!-- Bouton pour voir l'historique -->
                                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#historiqueModal">
                                        <i class="fas fa-history me-1"></i>Historique
                                    </button>
                                    <!-- Bouton modifier (si autorisé) -->
                                    <?php if ($planExistant['statut_validation'] !== 'Validé'): ?>
                                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPlanModal">
                                            <i class="fas fa-edit me-1"></i>Modifier
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Titre du plan:</strong><br>
                                        <?= htmlspecialchars($planExistant['titre_plan']) ?>
                                    </div>
                                    <div class="col-md-2">
                                        <strong>Version:</strong><br>
                                        v<?= $planExistant['version'] ?>
                                    </div>
                                    <div class="col-md-2">
                                        <strong>Avancement:</strong><br>
                                        <?php
                                        // Récupérer les statistiques d'avancement
                                        $avancement = $planModel->getAvancementPlan($planExistant['idplan_travail']);

                                        // Calculer le pourcentage global basé sur les chapitres validés
                                        $pourcentage = 0;
                                        if ($avancement && $avancement['total_chapitres'] > 0) {
                                            $pourcentage = round(($avancement['chapitres_termines'] / $avancement['total_chapitres']) * 100);
                                        }
                                        ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-<?= $pourcentage >= 75 ? 'success' : ($pourcentage >= 50 ? 'warning' : 'danger') ?>"
                                                role="progressbar" style="width: <?= $pourcentage ?>%">
                                                <?= $pourcentage ?>%
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <strong>Date de soumission:</strong><br>
                                        <small><?= date('d/m/Y', strtotime($planExistant['date_soumission'])) ?></small>
                                    </div>
                                </div>

                                <?php if ($avancement && $avancement['total_chapitres'] > 0): ?>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <div class="alert alert-light border">
                                                <div class="row text-center">
                                                    <div class="col-md-3">
                                                        <strong class="text-primary"><?= $avancement['total_chapitres'] ?></strong><br>
                                                        <small>Total chapitres</small>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong class="text-success"><?= $avancement['chapitres_termines'] ?></strong><br>
                                                        <small>Terminés</small>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong class="text-warning"><?= $avancement['chapitres_en_revision'] ?></strong><br>
                                                        <small>En révision</small>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong class="text-info"><?= $avancement['chapitres_en_cours'] ?></strong><br>
                                                        <small>En cours</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($planExistant['commentaire_directeur']): ?>
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-comment me-2"></i>Commentaire du directeur:</h6>
                                        <?= nl2br(htmlspecialchars($planExistant['commentaire_directeur'])) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Chapitres du plan -->
                                <?php
                                $chapitres = $planModel->getChapitresByPlan($planExistant['idplan_travail']);
                                if (!empty($chapitres)):
                                ?>
                                    <h6 class="mt-4 mb-3"><i class="fas fa-list-ol me-2"></i>Structure du Plan</h6>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="10%">Chapitre</th>
                                                    <th width="40%">Titre</th>
                                                    <th width="15%">Deadline</th>
                                                    <th width="15%">Statut</th>
                                                    <th width="20%">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($chapitres as $chapitre): ?>
                                                    <tr>
                                                        <td class="text-center fw-bold"><?= $chapitre['numero_chapitre'] ?></td>
                                                        <td><?= htmlspecialchars($chapitre['titre_chapitre']) ?></td>
                                                        <td>
                                                            <?php if ($chapitre['deadline']): ?>
                                                                <span class="badge bg-<?= strtotime($chapitre['deadline']) < time() ? 'danger' : 'primary' ?>">
                                                                    <?= date('d/m/Y', strtotime($chapitre['deadline'])) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Non définie</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?=
                                                                                    $chapitre['statut'] === 'Terminé' ? 'success' : ($chapitre['statut'] === 'En cours' ? 'primary' : ($chapitre['statut'] === 'En révision' ? 'warning' : 'secondary')) ?>">
                                                                <?= htmlspecialchars($chapitre['statut']) ?>
                                                            </span>
                                                            <?php if ($chapitre['pourcentage_avancement'] > 0): ?>
                                                                <small class="d-block text-muted"><?= $chapitre['pourcentage_avancement'] ?>%</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                                onclick="voirChapitre(<?= $chapitre['idchapitre_plan'] ?>)">
                                                                <i class="fas fa-eye me-1"></i>Voir
                                                            </button>
                                                            <?php if ($planExistant['statut_validation'] === 'Validé' && $chapitre['statut'] !== 'Terminé'): ?>
                                                                <?php
                                                                // Vérifier si le deadline est dépassé
                                                                $deadlineDepasse = false;
                                                                if ($chapitre['deadline'] && strtotime($chapitre['deadline']) < time()) {
                                                                    $deadlineDepasse = true;
                                                                }
                                                                ?>
                                                                <?php if ($deadlineDepasse): ?>
                                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" disabled>
                                                                        <i class="fas fa-exclamation-triangle me-1"></i>Deadline dépassée
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" class="btn btn-sm btn-outline-success ms-1"
                                                                        onclick="soumettreCharpitre(<?= $chapitre['idchapitre_plan'] ?>)">
                                                                        <i class="fas fa-upload me-1"></i>Soumettre
                                                                    </button>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Formulaire de création du plan -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Soumettre Mon Plan de Travail</h5>
                            </div>
                            <div class="card-body">
                                <form action="../controller/plan_travail_controller.php" method="POST" id="planForm">
                                    <input type="hidden" name="action" value="creer_plan">
                                    <input type="hidden" name="sujet_id" value="<?= $sujetAssigne['idsujets'] ?>">

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="titre_plan" class="form-label required">Titre du Plan de Travail</label>
                                            <input type="text" class="form-control" id="titre_plan" name="titre_plan" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="introduction" class="form-label">Introduction</label>
                                            <textarea class="form-control" id="introduction" name="introduction" rows="4"
                                                placeholder="Présentez le contexte général de votre recherche..."></textarea>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="problematique" class="form-label">Problématique</label>
                                            <textarea class="form-control" id="problematique" name="problematique" rows="4"
                                                placeholder="Formulez clairement votre problématique de recherche..."></textarea>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="objectifs" class="form-label">Objectifs</label>
                                            <textarea class="form-control" id="objectifs" name="objectifs" rows="4"
                                                placeholder="Énumérez vos objectifs général et spécifiques..."></textarea>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="methodologie" class="form-label">Méthodologie</label>
                                            <textarea class="form-control" id="methodologie" name="methodologie" rows="4"
                                                placeholder="Décrivez votre approche méthodologique..."></textarea>
                                        </div>
                                    </div>

                                    <!-- Section des chapitres -->
                                    <div class="card mt-4">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="fas fa-list-ol me-2"></i>Structure du Plan (Chapitres)</h6>
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="ajouterChapitre()">
                                                <i class="fas fa-plus me-1"></i>Ajouter Chapitre
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div id="chapitres-container">
                                                <!-- Les chapitres seront ajoutés ici dynamiquement -->
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                        <?php
                                        // Vérifier si il y a un deadline global pour les plans de travail
                                        $deadlineGlobal = false; // À implémenter selon votre logique
                                        ?>
                                        <?php if ($deadlineGlobal && strtotime($deadlineGlobal) < time()): ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                La date limite de soumission est dépassée (<?= date('d/m/Y', strtotime($deadlineGlobal)) ?>).
                                            </div>
                                            <button type="button" class="btn btn-secondary" disabled>
                                                <i class="fas fa-times me-2"></i>Soumission fermée
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>Soumettre le Plan
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Modal pour l'historique du plan -->
            <?php if (isset($planExistant)): ?>
                <div class="modal fade" id="historiqueModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-history me-2"></i>Historique du Plan de Travail
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="historiqueContent">
                                    <div class="text-center p-3">
                                        <div class="spinner-border text-info" role="status">
                                            <span class="visually-hidden">Chargement...</span>
                                        </div>
                                        <p class="mt-2">Chargement de l'historique...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal pour modifier le plan -->
                <div class="modal fade" id="editPlanModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-edit me-2"></i>Modifier le Plan de Travail
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="../controller/plan_travail_controller.php" method="POST" id="editPlanForm">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="modifier_plan">
                                    <input type="hidden" name="plan_id" value="<?= $planExistant['idplan_travail'] ?>">

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        La modification remettra votre plan en attente de validation.
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="edit_titre_plan" class="form-label required">Titre du Plan de Travail</label>
                                            <input type="text" class="form-control" id="edit_titre_plan" name="titre_plan"
                                                value="<?= htmlspecialchars($planExistant['titre_plan']) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="edit_introduction" class="form-label">Introduction</label>
                                            <textarea class="form-control" id="edit_introduction" name="introduction" rows="4"><?= htmlspecialchars($planExistant['introduction']) ?></textarea>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="edit_problematique" class="form-label">Problématique</label>
                                            <textarea class="form-control" id="edit_problematique" name="problematique" rows="4"><?= htmlspecialchars($planExistant['problematique']) ?></textarea>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="edit_objectifs" class="form-label">Objectifs</label>
                                            <textarea class="form-control" id="edit_objectifs" name="objectifs" rows="4"><?= htmlspecialchars($planExistant['objectifs']) ?></textarea>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="edit_methodologie" class="form-label">Méthodologie</label>
                                            <textarea class="form-control" id="edit_methodologie" name="methodologie" rows="4"><?= htmlspecialchars($planExistant['methodologie']) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
        <!-- Onglet Cours -->
        <div class="tab-pane fade <?= !$estPromotionTerminale ? 'show active' : '' ?>" id="courses" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">
                    <i class="fas fa-book me-2"></i>Mes cours
                </h4>
            </div>

            <?php
            // Récupérer les cours de l'étudiant
            $promotion = isset($_SESSION['promotion_id']) ? $_SESSION['promotion_id'] : 0;
            $cours = $coursModel->getCoursByPromotion($promotion, $currentYear['idannee_acad']);

            if (empty($cours)):
            ?>
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle me-3 fs-4"></i>
                    <div>
                        Aucun cours disponible pour le moment.
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($cours as $c): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-primary mb-2">
                                        <i class="fas fa-book-open me-2"></i><?= htmlspecialchars($c['designationECUE']) ?>
                                    </h5>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span class="badge bg-info">
                                            <i class="fas fa-layer-group me-1"></i><?= htmlspecialchars($c['designationUE']) ?>
                                        </span>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-calendar-alt me-1"></i>Semestre <?= htmlspecialchars($c['numeroSemestre']) ?>
                                        </span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-chalkboard-teacher me-1"></i>CMI: <?= $c['CMI'] ?>h
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-users me-1"></i>TD: <?= $c['TD'] ?>h
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-flask me-1"></i>TP: <?= $c['TP'] ?>h
                                        </span>
                                    </div>
                                    <div class="d-grid">
                                        <button class="btn btn-outline-primary"
                                            onclick="loadCourseDetails(<?= $c['idECUE'] ?>)">
                                            <i class="fas fa-eye me-2"></i>Voir les détails
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Modal pour les détails d'un cours -->
            <div class="modal fade" id="courseDetailsModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="courseTitle">
                                <i class="fas fa-book-open me-2"></i>Détails du cours
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="courseContent">
                                <div class="text-center p-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="mt-3">Chargement des détails du cours...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal pour soumettre un devoir -->
            <div class="modal fade" id="submitAssignmentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-paper-plane me-2"></i>Soumettre une réponse
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="submitAssignmentForm" method="POST" action="../controller/submit_assignment.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                                <input type="hidden" name="iddevoir" id="submission_iddevoir">
                                <input type="hidden" name="idetudiant" value="<?= $studentId ?>">

                                <div class="mb-3">
                                    <label for="submission_commentaire" class="form-label">Commentaire (optionnel)</label>
                                    <textarea class="form-control" id="submission_commentaire" name="commentaire" rows="3"></textarea>
                                    <div class="form-text">Vous pouvez ajouter des précisions sur votre travail.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="submission_fichier" class="form-label required">Fichier (PDF, DOCX, ZIP, etc.)</label>
                                    <input type="file" class="form-control" id="submission_fichier" name="fichier" required>
                                    <div class="invalid-feedback">Veuillez sélectionner un fichier à soumettre.</div>
                                    <div class="form-text">Taille maximale: 10 Mo.</div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Soumettre
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($estChefPromotion): ?>
            <!-- Onglet Suivi des Enseignements -->
            <div class="tab-pane fade" id="suivi-enseignements" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold text-primary">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Suivi des Enseignements
                    </h4>
                    <div>
                        <button type="button" class="btn btn-outline-info me-2" onclick="chargerSuiviEnseignements()">
                            <i class="fas fa-sync-alt me-2"></i>Recharger
                        </button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterSuiviModal">
                            <i class="fas fa-plus-circle me-2"></i>Nouvelle séance
                        </button>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="row mb-4" id="statistiques-suivi">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check text-primary fs-2 mb-2"></i>
                                <h5 class="card-title mb-1" id="total-seances">0</h5>
                                <p class="card-text text-muted">Séances totales</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-book text-success fs-2 mb-2"></i>
                                <h5 class="card-title mb-1" id="matieres-enseignees">0</h5>
                                <p class="card-text text-muted">Matières enseignées</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-clock text-warning fs-2 mb-2"></i>
                                <h5 class="card-title mb-1" id="total-heures">0h</h5>
                                <p class="card-text text-muted">Heures totales</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-day text-info fs-2 mb-2"></i>
                                <h5 class="card-title mb-1" id="jours-cours">0</h5>
                                <p class="card-text text-muted">Jours de cours</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Liste des suivis -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-list me-2"></i>Historique des séances
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="liste-suivis">
                            <div class="text-center p-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Chargement...</span>
                                </div>
                                <p class="mt-3">Chargement des données...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal pour ajouter un suivi -->
                <div class="modal fade" id="ajouterSuiviModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-plus-circle me-2"></i>Ajouter une séance de cours
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="../controller/soumettre_suivi_enseignement.php" method="POST" class="needs-validation" novalidate>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="idECUE" class="form-label required">Matière</label>
                                            <select class="form-select select2" id="idECUE" name="idECUE" required>
                                                <option value="">Sélectionnez une matière</option>
                                                <?php
                                                // Récupérer les matières de la promotion de l'étudiant
                                                $queryMatieres = "SELECT e.\"idECUE\", e.\"designationECUE\", u.\"designationUE\"
                                                                 FROM ecue e
                                                                 INNER JOIN ue u ON e.\"UE_idUE\" = u.\"idUE\"
                                                                 INNER JOIN semestre s ON u.semestre_idsemestre = s.idsemestre
                                                                 INNER JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                                                                 INNER JOIN etudiant et ON et.promotion_idpromotion = p.idpromotion
                                                                 WHERE et.idetudiant = :student_id
                                                                 AND et.annee_acad_idannee_acad = :annee_acad
                                                                 ORDER BY u.\"designationUE\", e.\"designationECUE\"";
                                                $stmtMatieres = $connexion->prepare($queryMatieres);
                                                $stmtMatieres->bindParam(':student_id', $studentId);
                                                $stmtMatieres->bindParam(':annee_acad', $currentYear['idannee_acad']);
                                                $stmtMatieres->execute();
                                                $matieres = $stmtMatieres->fetchAll(PDO::FETCH_ASSOC);

                                                foreach ($matieres as $matiere): ?>
                                                    <option value="<?= $matiere['idECUE'] ?>">
                                                        <?= htmlspecialchars($matiere['designationECUE']) ?> (<?= htmlspecialchars($matiere['designationUE']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">Veuillez sélectionner une matière.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="date_cours" class="form-label required">Date du cours</label>
                                            <input type="date" class="form-control" id="date_cours" name="date_cours"
                                                max="<?= date('Y-m-d') ?>" required>
                                            <div class="invalid-feedback">Veuillez sélectionner une date valide.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="heure_debut" class="form-label required">Heure de début</label>
                                            <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                                            <div class="invalid-feedback">Veuillez saisir l'heure de début.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="heure_fin" class="form-label required">Heure de fin</label>
                                            <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                                            <div class="invalid-feedback">Veuillez saisir l'heure de fin.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="type_cours" class="form-label required">Type de cours</label>
                                            <select class="form-select" id="type_cours" name="type_cours" required>
                                                <option value="">Sélectionnez le type</option>
                                                <option value="CM">Cours Magistral (CM)</option>
                                                <option value="TD">Travaux Dirigés (TD)</option>
                                                <option value="TP">Travaux Pratiques (TP)</option>
                                                <option value="Evaluation">Évaluation</option>
                                            </select>
                                            <div class="invalid-feedback">Veuillez sélectionner le type de cours.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="enseignant_id" class="form-label">Enseignant</label>
                                            <select class="form-select select2" id="enseignant_id" name="enseignant_id">
                                                <option value="">Sélectionnez un enseignant (optionnel)</option>
                                                <?php
                                                // Récupérer la liste des enseignants
                                                $queryEnseignants = "SELECT a.\"idAgent\", a.noms, g.designation as grade
                                                                    FROM agent a
                                                                    LEFT JOIN grade g ON a.grade_id = g.idgrade
                                                                    WHERE a.type_agent = 'Enseignant'
                                                                    ORDER BY a.noms";
                                                $stmtEnseignants = $connexion->prepare($queryEnseignants);
                                                $stmtEnseignants->execute();
                                                $enseignants = $stmtEnseignants->fetchAll(PDO::FETCH_ASSOC);

                                                foreach ($enseignants as $enseignant): ?>
                                                    <option value="<?= $enseignant['idAgent'] ?>">
                                                        <?= htmlspecialchars(($enseignant['grade'] ? $enseignant['grade'] . ' ' : '') . $enseignant['noms']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label for="salle" class="form-label">Salle</label>
                                            <input type="text" class="form-control" id="salle" name="salle"
                                                placeholder="Ex: Amphi A, Salle 101, Labo Informatique...">
                                        </div>
                                        <div class="col-md-12">
                                            <label for="commentaire" class="form-label">Commentaires/Observations</label>
                                            <textarea class="form-control" id="commentaire" name="commentaire" rows="3"
                                                placeholder="Observations particulières, contenu abordé, etc."></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-2"></i>Annuler
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>



        <!-- Onglet Notes/Évaluations -->
        <div class="tab-pane fade" id="evaluations" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">
                    <i class="fas fa-chart-bar me-2"></i>Mes notes
                </h4>
            </div>

            <?php
            // Récupérer les cours de l'étudiant
            $promotion = isset($_SESSION['promotion_id']) ? $_SESSION['promotion_id'] : 0;
            $coursEtudiant = $coursModel->getCoursByPromotion($promotion, $currentYear['idannee_acad']);

            // Vérifier si des cours existent
            if (empty($coursEtudiant)):
            ?>
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle me-3 fs-4"></i>
                    <div>
                        Aucun cours disponible pour afficher les notes.
                    </div>
                </div>
            <?php else: ?>
                <div class="accordion" id="accordionEvaluations">
                    <?php
                    $matricule = isset($_SESSION['student_matricule']) ? $_SESSION['student_matricule'] : '';
                    $allCourses = [];

                    // Récupérer les données des cours et de leurs évaluations
                    foreach ($coursEtudiant as $index => $cours):
                        $evaluations = $ecueModel->getEvaluationsByEcue($cours['idECUE'], $currentYear['idannee_acad']);
                        $evaluationsPubliques = array_filter($evaluations, function ($eval) {
                            return $eval['est_visible'] == 1;
                        });

                        if (!empty($evaluationsPubliques)):
                            $allCourses[] = [
                                'cours' => $cours,
                                'evaluations' => $evaluationsPubliques
                            ];
                        endif;
                    endforeach;

                    if (empty($allCourses)):
                    ?>
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-3 fs-4"></i>
                            <div>
                                Aucune évaluation publiée pour le moment.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allCourses as $index => $courseData):
                            $cours = $courseData['cours'];
                            $evaluations = $courseData['evaluations'];

                            // Récupérer les notes de l'étudiant pour ce cours
                            $notes = $ecueModel->getStudentGrades($studentId, $cours['idECUE'], $currentYear['idannee_acad']);

                            // Regrouper les évaluations par session
                            $evalsBySession = [];
                            foreach ($evaluations as $eval) {
                                $sessionId = $eval['session_idsession'];
                                if (!isset($evalsBySession[$sessionId])) {
                                    $evalsBySession[$sessionId] = [
                                        'session' => $eval['designSession'],
                                        'evaluations' => []
                                    ];
                                }
                                $evalsBySession[$sessionId]['evaluations'][] = $eval;
                            }
                        ?>
                            <div class="accordion-item mb-3 border-0 shadow-sm">
                                <h2 class="accordion-header" id="heading<?= $cours['idECUE'] ?>">
                                    <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapse<?= $cours['idECUE'] ?>"
                                        aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>"
                                        aria-controls="collapse<?= $cours['idECUE'] ?>">
                                        <div class="d-flex align-items-center w-100">
                                            <i class="fas fa-book-open me-3 text-primary"></i>
                                            <div>
                                                <strong><?= htmlspecialchars($cours['designationECUE']) ?></strong>
                                                <div class="small text-muted"><?= htmlspecialchars($cours['designationUE']) ?></div>
                                            </div>
                                            <span class="ms-auto badge bg-primary">
                                                Semestre <?= htmlspecialchars($cours['numeroSemestre']) ?>
                                            </span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?= $cours['idECUE'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>"
                                    aria-labelledby="heading<?= $cours['idECUE'] ?>" data-bs-parent="#accordionEvaluations">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th colspan="4" class="text-center bg-light">
                                                            <i class="fas fa-graduation-cap me-2"></i>
                                                            <?= htmlspecialchars($cours['designationECUE']) ?> - <?= htmlspecialchars($cours['designationUE']) ?>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($evalsBySession as $sessionId => $sessionData): ?>
                                                        <tr class="table-secondary">
                                                            <th colspan="4">
                                                                <i class="fas fa-calendar-alt me-2"></i>
                                                                Session: <?= htmlspecialchars($sessionData['session']) ?>
                                                            </th>
                                                        </tr>
                                                        <tr>
                                                            <th width="5%" class="text-center">#</th>
                                                            <th width="45%">Évaluation</th>
                                                            <th width="20%">Date</th>
                                                            <th width="30%">Note</th>
                                                        </tr>
                                                        <?php
                                                        $evalCount = 1;
                                                        $sessionTotal = 0;
                                                        $coeffTotal = 0;

                                                        foreach ($sessionData['evaluations'] as $eval):
                                                            // Trouver la note pour cette évaluation
                                                            $note = null;
                                                            foreach ($notes as $n) {
                                                                if ($n['idevaluation'] == $eval['idevaluation']) {
                                                                    $note = $n['coteObtenu'];
                                                                    if ($note !== null) {
                                                                        $sessionTotal += $note * $eval['ponderation'];
                                                                        $coeffTotal += $eval['ponderation'];
                                                                    }
                                                                    break;
                                                                }
                                                            }

                                                            // Déterminer la classe CSS selon la note
                                                            $noteClass = '';
                                                            if ($note !== null) {
                                                                if ($note < 10) $noteClass = 'text-danger';
                                                                elseif ($note >= 16) $noteClass = 'text-success';
                                                            }
                                                        ?>
                                                            <tr>
                                                                <td class="text-center"><?= $evalCount++ ?></td>
                                                                <td>
                                                                    <div class="d-flex flex-column">
                                                                        <strong><?= htmlspecialchars($eval['titre']) ?></strong>
                                                                        <div class="d-flex align-items-center mt-1">
                                                                            <span class="badge bg-info me-2"><?= htmlspecialchars($eval['designationT']) ?></span>
                                                                            <small class="text-muted">Pondération: <?= $eval['ponderation'] ?></small>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td><?= date('d/m/Y', strtotime($eval['date_evaluation'])) ?></td>
                                                                <td class="<?= $noteClass ?>">
                                                                    <?php if ($note !== null): ?>
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                                                <div class="progress-bar bg-<?= $note < 10 ? 'danger' : ($note >= 16 ? 'success' : 'primary') ?>"
                                                                                    role="progressbar"
                                                                                    style="width: <?= min(100, ($note / 20) * 100) ?>%"
                                                                                    aria-valuenow="<?= $note ?>"
                                                                                    aria-valuemin="0"
                                                                                    aria-valuemax="20">
                                                                                </div>
                                                                            </div>
                                                                            <strong><?= number_format($note, 2) ?>/20</strong>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-muted">
                                                                            <i class="fas fa-minus-circle me-1"></i>
                                                                            Non disponible
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>

                                                        <?php if ($coeffTotal > 0):
                                                            $moyenne = $sessionTotal / $coeffTotal;
                                                            $moyenneClass = '';
                                                            if ($moyenne < 10) $moyenneClass = 'text-danger';
                                                            elseif ($moyenne >= 16) $moyenneClass = 'text-success';
                                                        ?>
                                                            <tr class="table-light">
                                                                <td colspan="3" class="text-end"><strong>Moyenne:</strong></td>
                                                                <td class="<?= $moyenneClass ?>">
                                                                    <div class="d-flex align-items-center">
                                                                        <div class="progress flex-grow-1 me-2" style="height: 10px;">
                                                                            <div class="progress-bar bg-<?= $moyenne < 10 ? 'danger' : ($moyenne >= 16 ? 'success' : 'primary') ?>"
                                                                                role="progressbar"
                                                                                style="width: <?= min(100, ($moyenne / 20) * 100) ?>%"
                                                                                aria-valuenow="<?= $moyenne ?>"
                                                                                aria-valuemin="0"
                                                                                aria-valuemax="20">
                                                                            </div>
                                                                        </div>
                                                                        <strong><?= number_format($moyenne, 2) ?>/20</strong>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        <?php endif; ?>
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
            <?php endif; ?>




            <!-- Affichage spécifique pour les étudiants du troisième cycle -->
            <?php if (isset($_SESSION['cycle']) && $_SESSION['cycle'] == 'Troisieme'): ?>
                <div class="card mt-4 border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Cotes détaillées (Troisième cycle)</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Connexion à la base de données
                        $connexion = Connexion::getInstance()->getPDO();

                        // Récupérer le matricule de l'étudiant
                        $matricule = isset($_SESSION['student_matricule']) ? $_SESSION['student_matricule'] : '';
                        $anneeAcadId = $currentYear['idannee_acad'];

                        if (!empty($matricule)) {
                            // Requête pour récupérer les cotes de l'étudiant
                            $query = "SELECT cg.*, e.\"designationECUE\", s.\"designSession\",s.description 
                                        FROM cotes_grille cg
                                        JOIN ecue e ON cg.\"ECUE_idECUE\" = e.\"idECUE\"
                                        JOIN session s ON cg.session_idsession = s.idsession
                                        WHERE cg.matricule = :matricule 
                                        AND cg.annee_acad_id = :annee_acad_id
                                        ORDER BY s.\"designSession\", e.\"designationECUE\"";

                            $stmt = $connexion->prepare($query);
                            $stmt->bindParam(':matricule', $matricule);
                            $stmt->bindParam(':annee_acad_id', $anneeAcadId);
                            $stmt->execute();

                            $cotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if (count($cotes) > 0) {
                                // Grouper les cotes par session
                                $cotesBySession = [];
                                foreach ($cotes as $cote) {
                                    $sessionId = $cote['session_idsession'];
                                    if (!isset($cotesBySession[$sessionId])) {
                                        $cotesBySession[$sessionId] = [
                                            'session' => $cote['description'],
                                            'cotes' => []
                                        ];
                                    }
                                    $cotesBySession[$sessionId]['cotes'][] = $cote;
                                }

                                // Afficher les cotes par session
                                echo '<div class="accordion" id="accordionCotes">';
                                $index = 0;
                                foreach ($cotesBySession as $sessionId => $sessionData) {
                                    $sessionName = htmlspecialchars($sessionData['session']);
                                    echo '
                                        <div class="accordion-item mb-3 border-0 shadow-sm">
                                            <h2 class="accordion-header" id="heading-session-' . $sessionId . '">
                                                <button class="accordion-button ' . ($index > 0 ? 'collapsed' : '') . '" type="button" 
                                                        data-bs-toggle="collapse" data-bs-target="#collapse-session-' . $sessionId . '" 
                                                        aria-expanded="' . ($index === 0 ? 'true' : 'false') . '" 
                                                        aria-controls="collapse-session-' . $sessionId . '">
                                                    <div class="d-flex align-items-center w-100">
                                                        <i class="fas fa-calendar-alt me-3 text-primary"></i>
                                                        <strong>' . $sessionName . '</strong>
                                                        <span class="ms-auto badge bg-primary">
                                                            ' . count($sessionData['cotes']) . ' cours
                                                        </span>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="collapse-session-' . $sessionId . '" class="accordion-collapse collapse ' . ($index === 0 ? 'show' : '') . '" 
                                                aria-labelledby="heading-session-' . $sessionId . '" data-bs-parent="#accordionCotes">
                                                <div class="accordion-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-striped">
                                                            <thead>
                                                                <tr>
                                                                    <th>Cours</th>
                                                                    <th class="text-center">CC</th>
                                                                    <th class="text-center">EX</th>
                                                                    <th class="text-center">MF</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>';

                                    foreach ($sessionData['cotes'] as $cote) {
                                        $noteCC = $cote['CC'] !== null ? number_format($cote['CC'], 2) : '-';
                                        $noteEX = $cote['EX'] !== null ? number_format($cote['EX'], 2) : '-';
                                        $noteMF = $cote['MF'] !== null ? number_format($cote['MF'], 2) : '-';

                                        // Déterminer la classe CSS selon la note finale
                                        $mfClass = '';
                                        if ($cote['MF'] !== null) {
                                            if ($cote['MF'] < 10) $mfClass = 'text-danger';
                                            elseif ($cote['MF'] >= 16) $mfClass = 'text-success';
                                            else $mfClass = 'text-primary';
                                        }

                                        echo '<tr>
                                                                    <td>' . htmlspecialchars($cote['designationECUE']) . '</td>
                                                                    <td class="text-center">' . $noteCC . '</td>
                                                                    <td class="text-center">' . $noteEX . '</td>
                                                                    <td class="text-center ' . $mfClass . '"><strong>' . $noteMF . '</strong></td>
                                                                </tr>';
                                    }

                                    echo '</tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>';
                                    $index++;
                                }
                                echo '</div>';
                            } else {
                                echo '<div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Aucune cote disponible pour cette année académique.
                                    </div>';
                            }
                        } else {
                            echo '<div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Impossible de récupérer votre matricule. Veuillez contacter l\'administration.
                                </div>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>





        </div>

        <!-- Onglet Horaire -->
        <div class="tab-pane fade" id="schedule" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">
                    <i class="fas fa-calendar-alt me-2"></i>Horaires
                </h4>
            </div>

            <?php
            $promotionId = isset($_SESSION['promotion_id']) ? $_SESSION['promotion_id'] : 0;

            if (empty($promotionId)) {
                echo '<div class="alert alert-warning d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                        <div>
                            Impossible de récupérer votre promotion. Veuillez contacter l\'administration.
                        </div>
                    </div>';
            } else {
                // Récupérer tous les horaires pour le calendrier (mois en cours)
                date_default_timezone_set('Africa/Lubumbashi');
                $today = date('Y-m-01');
                $endOfMonth = date('Y-m-t');

                $horaires = $horaireModel->getHorairesByPromotionAndDates(
                    $promotionId,
                    $currentYear['idannee_acad'],
                    $today,
                    $endOfMonth
                );

                // Convertir les horaires en événements FullCalendar
                $events = [];
                $typeColors = [
                    'CMI' => '#0d6efd',
                    'TD' => '#198754',
                    'TP' => '#ffc107',
                    'Cours' => '#0d6efd',
                    'TD' => '#198754',
                    'TP' => '#ffc107',
                    'Évaluation' => '#dc3545',
                    'Examen' => '#dc3545'
                ];

                foreach ($horaires as $h) {
                    // Déterminer la date du cours
                    $coursDate = !empty($h['date_cours']) ? $h['date_cours'] : date('Y-m-d');

                    // Déterminer la couleur selon le type
                    $typeCours = strtolower($h['type_cours'] ?? 'cours');
                    $color = '#0d6efd'; // default blue
                    if (strpos($typeCours, 'td') !== false) {
                        $color = '#198754'; // green
                    } elseif (strpos($typeCours, 'tp') !== false) {
                        $color = '#ffc107'; // yellow
                    } elseif (strpos($typeCours, 'eval') !== false || strpos($typeCours, 'examen') !== false) {
                        $color = '#dc3545'; // red
                    }

                    $events[] = [
                        'title' => ($h['designationECUE'] ?? 'Cours') . ' - ' . ($h['type_cours'] ?? 'Cours'),
                        'start' => $coursDate . 'T' . $h['heure_debut'],
                        'end' => $coursDate . 'T' . $h['heure_fin'],
                        'backgroundColor' => $color,
                        'borderColor' => $color,
                        'extendedProps' => [
                            'ecue' => $h['designationECUE'] ?? '',
                            'type_cours' => $h['type_cours'] ?? '',
                            'salle' => $h['salle'] ?? '',
                            'enseignant' => $h['enseignant_nom'] ?? ''
                        ]
                    ];
                }
                ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div id="calendar"></div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var calendarEl = document.getElementById('calendar');
                    if (!calendarEl) return;

                    // Déterminer la vue selon la largeur de l'écran
                    function getInitialView() {
                        var width = window.innerWidth;
                        if (width < 768) {
                            return 'timeGridDay'; // Mobile: vue jour
                        } else if (width < 1024) {
                            return 'timeGridWeek'; // Tablette: vue semaine
                        }
                        return 'timeGridWeek'; // Desktop: vue semaine
                    }

                    // Déterminer les boutons à afficher selon l'écran
                    function getHeaderToolbar() {
                        var width = window.innerWidth;
                        if (width < 576) {
                            return {
                                left: 'prev,next',
                                center: 'title',
                                right: 'timeGridDay,timeGridWeek'
                            };
                        }
                        return {
                            left: 'prev,next today',
                            center: 'title',
                            right: 'dayGridMonth,timeGridWeek,timeGridDay'
                        };
                    }

                    var calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: getInitialView(),
                        locale: 'fr',
                        firstDay: 1,
                        headerToolbar: getHeaderToolbar(),
                        buttonText: {
                            today: 'Aujourd\'hui',
                            month: 'Mois',
                            week: 'Semaine',
                            day: 'Jour'
                        },
                        allDayText: 'Toute la journée',
                        slotMinTime: '07:00:00',
                        slotMaxTime: '20:00:00',
                        slotDuration: '00:30:00',
                        height: 'auto',
                        dayMaxEvents: 3,
                        nowIndicator: true,
                        eventTimeFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            meridiem: false
                        },
                        slotLabelFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            meridiem: false
                        },
                        events: <?= json_encode($events) ?>,
                        eventClick: function(info) {
                            var props = info.event.extendedProps;
                            Swal.fire({
                                title: info.event.title,
                                html: `
                                    <div class="text-start">
                                        <p><strong><i class="fas fa-book me-2"></i>ECUE:</strong> ${props.ecue || 'N/A'}</p>
                                        <p><strong><i class="fas fa-tag me-2"></i>Type:</strong> ${props.type_cours || 'Cours'}</p>
                                        <p><strong><i class="fas fa-door me-2"></i>Salle:</strong> ${props.salle || 'N/A'}</p>
                                        <p><strong><i class="fas fa-user me-2"></i>Enseignant:</strong> ${props.enseignant || 'N/A'}</p>
                                        <p><strong><i class="fas fa-clock me-2"></i>Horaire:</strong> ${info.event.startStr.substring(11, 16)} - ${info.event.endStr ? info.event.endStr.substring(11, 16) : ''}</p>
                                    </div>
                                `,
                                icon: 'info',
                                confirmButtonText: 'Fermer'
                            });
                        },
                        eventDidMount: function(info) {
                            info.el.title = info.event.title + '\n' + 
                                (info.event.extendedProps.salle ? 'Salle: ' + info.event.extendedProps.salle : '');
                        }
                    });

                    calendar.render();

                    // Gestion du changement d'onglet pour initialiser le calendrier
                    document.querySelector('#schedule-tab').addEventListener('shown.bs.tab', function() {
                        calendar.updateSize();
                    });
                });
                </script>
                <?php
            }
            ?>
        </div>


        <?php if ($deliberationPubliee): ?>
            <!-- Recours Tab -->
            <div class="tab-pane fade" id="recours" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold text-primary">
                        <i class="fas fa-gavel me-2"></i>Mes recours académiques
                    </h4>

                    <!-- Button to create new recours -->
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRecoursModal">
                        <i class="fas fa-plus-circle me-2"></i>Nouveau recours
                    </button>
                </div>

                <?php if (empty($recours)): ?>
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="fas fa-info-circle me-3 fs-4"></i>
                        <div>
                            Vous n'avez soumis aucun recours pour le moment. Pour contester une note, cliquez sur le bouton "Nouveau recours".
                        </div>
                    </div>
                <?php else: ?>
                    <!-- List of existing recours -->
                    <div class="row">
                        <?php foreach ($recours as $r): ?>
                            <div class="col-md-12 mb-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-<?= getStatusColor($r['statut']) ?> text-white py-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">
                                                <i class="fas fa-file-alt me-2"></i>
                                                Recours pour <?= htmlspecialchars($r['designationECUE']) ?>
                                            </h5>
                                            <span class="badge bg-white text-<?= getStatusColor($r['statut']) ?>" <i class="fas fa-<?= $r['statut'] === 'Validé' ? 'check-circle' : ($r['statut'] === 'A reformulé' ? 'times-circle' : 'clock') ?> me-1"></i>
                                                <?= htmlspecialchars($r['statut']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-tag text-primary me-2"></i>
                                                    <strong>Motif:</strong>&nbsp;<?= htmlspecialchars($r['motif']) ?>
                                                </div>
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                                                    <strong>Soumis le:</strong>&nbsp;<?= date('d/m/Y H:i', strtotime($r['date_creation'])) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-layer-group text-primary me-2"></i>
                                                    <strong>Session:</strong>&nbsp;<?= htmlspecialchars($r['descSession'] ?? 'Non spécifiée') ?>
                                                </div>
                                                <?php if ($r['preuve']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-paperclip text-primary me-2"></i>
                                                        <strong>Preuve:</strong>&nbsp;
                                                        <a href="../uploads/recours/<?= htmlspecialchars($r['preuve']) ?>"
                                                            class="btn btn-sm btn-outline-primary ms-2" target="_blank">
                                                            <i class="fas fa-file-download me-1"></i> Voir le document
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="p-3 bg-light rounded mb-3">
                                            <h6 class="fw-bold mb-2">
                                                <i class="fas fa-comment-alt me-2"></i>Description
                                            </h6>
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($r['description'])) ?></p>
                                        </div>

                                        <?php if ($r['statut'] !== 'En attente'): ?>
                                            <!-- Show response if available -->
                                            <?php
                                            $reponse = $recoursModel->getRecoursReponse($r['id_recours']);
                                            if ($reponse):
                                            ?>
                                                <div class="p-3 bg-<?= $r['statut'] === 'Validé' ? 'success' : 'danger' ?>-light rounded mb-3">
                                                    <h6 class="fw-bold mb-2 text-<?= $r['statut'] === 'Validé' ? 'success' : 'danger' ?>">
                                                        <i class="fas fa-reply me-2"></i>Réponse
                                                    </h6>
                                                    <p class="mb-3"><?= nl2br(htmlspecialchars($reponse['commentaire'])) ?></p>
                                                    <?php if ($reponse['nouvelle_note_cc'] !== null || $reponse['nouvelle_note_ex'] !== null): ?>
                                                        <div class="mb-2">
                                                            <strong>Nouvelles notes:</strong>
                                                            <div class="d-flex flex-wrap gap-2 mt-2">
                                                                <?php if ($reponse['nouvelle_note_cc'] !== null): ?>
                                                                    <span class="badge bg-info p-2">
                                                                        <i class="fas fa-pen me-1"></i>
                                                                        Contrôle continu: <?= $reponse['nouvelle_note_cc'] ?>/20
                                                                    </span>
                                                                <?php endif; ?>
                                                                <?php if ($reponse['nouvelle_note_ex'] !== null): ?>
                                                                    <span class="badge bg-info p-2">
                                                                        <i class="fas fa-file-alt me-1"></i>
                                                                        Examen: <?= $reponse['nouvelle_note_ex'] ?>/20
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>
                                                            Réponse du <?= date('d/m/Y', strtotime($reponse['date_reponse'])) ?>
                                                        </small>
                                                        <?php if ($reponse['valide_jury']): ?>
                                                            <span class="badge bg-success p-2">
                                                                <i class="fas fa-check-circle me-1"></i>
                                                                Validé par le jury
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-end">
                                            <a href="../controller/export_recours_pdf.php?id=<?= $r['id_recours'] ?>"
                                                class="btn btn-outline-primary" target="_blank">
                                                <i class="fas fa-file-pdf me-2"></i>Exporter en PDF
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Onglet Paiements Mobile -->
        <div class="tab-pane fade" id="paiements" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold text-primary">
                    <i class="fas fa-mobile-alt me-2"></i>Paiements Mobile
                </h4>
            </div>

            <!-- Frais impayés -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Frais à payer</h6>
                </div>
                <div class="card-body">
                    <div id="liste-frais-flexpay">
                        <div class="text-center p-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <p class="mt-3">Chargement des frais...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historique des transactions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Historique des transactions</h6>
                </div>
                <div class="card-body">
                    <div id="historique-transactions-flexpay">
                        <div class="text-center p-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <p class="mt-3">Chargement de l'historique...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages Tab -->
        <div class="tab-pane fade" id="messages" role="tabpanel">
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-comments text-primary" style="font-size: 3.5rem; opacity: 0.3;"></i>
                </div>
                <h5 class="fw-bold text-muted mb-2">Messagerie</h5>
                <p class="text-muted mb-0">
                    <i class="fas fa-tools me-1"></i>En cours de développement
                </p>
                <small class="text-muted">Cette fonctionnalité sera bientôt disponible.</small>
            </div>
        </div>
    </div>
</div>

<!-- Modal de réponse -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-reply me-2"></i>Répondre
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../controller/ajouter_echange.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="tache_id" id="reply_tache_id">
                    <input type="hidden" name="type_auteur" value="Etudiant">
                    <input type="hidden" name="id_auteur" value="<?= $studentId ?>">

                    <div class="mb-3">
                        <label for="commentaire" class="form-label required">Votre réponse</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="4" required></textarea>
                        <div class="invalid-feedback">Veuillez saisir votre réponse.</div>
                    </div>

                    <div class="mb-3">
                        <label for="fichier" class="form-label">Fichier (optionnel)</label>
                        <input type="file" class="form-control" id="fichier" name="fichier">
                        <div class="form-text">Vous pouvez joindre un document, une image ou tout autre fichier pertinent.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for new recours -->
<?php if ($deliberationPubliee): ?>
    <div class="modal fade" id="newRecoursModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-gavel me-2"></i>Soumettre un recours académique
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="../controller/create_recours_student.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="matricule" value="<?= htmlspecialchars($studentMatricule) ?>">
                        <input type="hidden" name="id_annee_acad" value="<?= $currentYear['idannee_acad'] ?>">
                        <input type="hidden" name="id_createur" value="<?= $studentId ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="id_ecue" class="form-label required">
                                    <i class="fas fa-book me-1"></i>Cours concerné
                                </label>
                                <select class="form-select select2" id="id_ecue" name="id_ecue" required>
                                    <option value="">Sélectionnez un cours</option>
                                    <?php
                                    // Get courses for the student's promotion
                                    $etuCours = $coursModel->getCoursByPromotion($promotionId, $currentYear['idannee_acad']);
                                    foreach ($etuCours as $cours):
                                    ?>
                                        <option value="<?= $cours['idECUE'] ?>">
                                            <?= htmlspecialchars($cours['designationECUE']) ?>
                                            (<?= htmlspecialchars($cours['designationUE']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un cours.</div>
                            </div>

                            <label for="id_session" class="form-label required">
                                <i class="fas fa-calendar-alt me-1"></i>Session
                            </label>
                            <select class="form-select" id="id_session" name="id_session" required>
                                <option value="">Sélectionnez une session</option>
                                <?php
                                // Get available sessions
                                $sessions = $universite->getSessions();
                                foreach ($sessions as $session):
                                ?>
                                    <option value="<?= $session['idsession'] ?>">
                                        <?= htmlspecialchars($session['description']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une session.</div>
                        </div>

                        <div class="col-md-12">
                            <label for="motif" class="form-label required">
                                <i class="fas fa-tag me-1"></i>Motif du recours
                            </label>
                            <select class="form-select" id="motif" name="motif" required>
                                <option value="">Sélectionnez un motif</option>
                                <option value="Omission de cote">Omission de cote</option>
                                <option value="Calcul inexact">Calcul inexact</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un motif.</div>
                        </div>

                        <div class="col-md-12">
                            <label for="description" class="form-label required">
                                <i class="fas fa-comment-alt me-1"></i>Description détaillée
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                            <div class="form-text">
                                Veuillez décrire précisément votre demande en fournissant tous les détails pertinents.
                            </div>
                            <div class="invalid-feedback">Veuillez fournir une description détaillée.</div>
                        </div>

                        <div class="col-md-12">
                            <label for="preuve" class="form-label">
                                <i class="fas fa-file-pdf me-1"></i>Document de preuve (PDF)
                            </label>
                            <input type="file" class="form-control" id="preuve" name="preuve" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text">
                                Si vous disposez d'une preuve (copie corrigée, bulletin, etc.), veuillez la joindre.
                                Formats acceptés: PDF, JPG, JPEG, PNG. Taille maximale: 5 Mo.
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4">
                        <div class="d-flex">
                            <i class="fas fa-info-circle me-3 fs-4"></i>
                            <div>
                                <strong>Information importante</strong>
                                <p class="mb-0">
                                    Des frais administratifs peuvent s'appliquer pour le traitement de votre recours.
                                    Votre demande sera examinée par le jury académique.
                                </p>
                            </div>
                        </div>
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane me-2"></i>Soumettre le recours
                </button>
            </div>
            </form>
        </div>
    </div>
    </div>
<?php endif; ?>

<?php if ($estPromotionTerminale && isset($sujetFeesPaid) && $sujetFeesPaid): ?>
    <!-- Floating Action Button -->
    <a href="#" class="fab" data-bs-toggle="modal" data-bs-target="#proposerSujetModal">
        <i class="fas fa-plus"></i>
    </a>
<?php endif; ?>

<?php if ($estPromotionTerminale): ?>
    <!-- Modal Proposition Sujet -->
    <div class="modal fade" id="proposerSujetModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-lightbulb me-2"></i>Proposer un sujet de recherche
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="proposerSujetForm" method="POST" action="../controller/proposer_sujet.php" class="needs-validation" novalidate>
                    <div class="modal-body">
                        <input type="hidden" name="etudiant_id" value="<?= $studentId ?>">

                        <!-- Section 1 : Sujet -->
                        <div class="modal-section">
                            <div class="modal-section-title">
                                <span class="modal-section-number">1</span>
                                Informations du sujet
                            </div>
                            <div class="mb-3">
                                <label for="intitule" class="form-label required">
                                    <i class="fas fa-heading me-1 text-primary"></i>Intitulé du sujet
                                </label>
                                <input type="text" class="form-control" id="intitule" name="intitule" 
                                    placeholder="Ex: Étude de l'impact de l'IA sur..." required>
                                <div class="form-text">Soyez précis et concis dans la formulation de votre sujet.</div>
                                <div class="invalid-feedback">Veuillez saisir l'intitulé du sujet.</div>
                            </div>
                            <div class="mb-3">
                                <label for="resume" class="form-label">
                                    <i class="fas fa-align-left me-1 text-primary"></i>Résumé / Problématique
                                </label>
                                <textarea class="form-control" id="resume" name="resume" rows="4" 
                                    placeholder="Décrivez brièvement le contexte, la problématique et les objectifs de votre recherche..."></textarea>
                                <div class="form-text">Ce résumé permettra à la commission de mieux comprendre votre travail.</div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="idSpecialisation" class="form-label required">
                                        <i class="fas fa-microscope me-1 text-primary"></i>Spécialisation
                                    </label>
                                    <select class="form-select select2" id="idSpecialisation" name="idSpecialisation" required>
                                        <option value="">Sélectionner une spécialisation</option>
                                        <?php
                                        $connexion = Connexion::getInstance()->getPDO();
                                        $etudiantId = $_SESSION['student_id'] ?? 0;

                                        $queryOrientation = "SELECT p.orientation_idorientation 
                                                        FROM etudiant e 
                                                        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                                                        WHERE e.idetudiant = :etudiantId";
                                        $stmtOrientation = $connexion->prepare($queryOrientation);
                                        $stmtOrientation->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
                                        $stmtOrientation->execute();
                                        $orientationInfo = $stmtOrientation->fetch(PDO::FETCH_ASSOC);
                                        $orientationId = $orientationInfo ? $orientationInfo['orientation_idorientation'] : 0;

                                        $query = "SELECT s.*, ur.\"designation_UR\" 
                                                FROM specialisation s 
                                                JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
                                                WHERE s.idorientation = :orientationId 
                                                ORDER BY ur.\"designation_UR\", s.designation ASC";
                                        $stmt = $connexion->prepare($query);
                                        $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
                                        $stmt->execute();
                                        $specialisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        $specialisationsByUR = [];
                                        foreach ($specialisations as $specialisation) {
                                            $urName = $specialisation['designation_UR'];
                                            if (!isset($specialisationsByUR[$urName])) {
                                                $specialisationsByUR[$urName] = [];
                                            }
                                            $specialisationsByUR[$urName][] = $specialisation;
                                        }

                                        if (!empty($specialisationsByUR)):
                                            foreach ($specialisationsByUR as $urName => $specs):
                                        ?>
                                                <optgroup label="<?= htmlspecialchars($urName) ?>">
                                                    <?php foreach ($specs as $specialisation): ?>
                                                        <option value="<?= $specialisation['idSpecialisation'] ?>">
                                                            <?= htmlspecialchars($specialisation['designation']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php
                                            endforeach;
                                        else:
                                            ?>
                                            <option value="" disabled>Aucune spécialisation disponible</option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner une spécialisation.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="annee_acad" class="form-label required">
                                        <i class="fas fa-calendar-alt me-1 text-primary"></i>Année Académique
                                    </label>
                                    <select class="form-select" id="annee_acad" name="annee_acad" required>
                                        <?php
                                        $currentYear = $universite->getAnneeAcademiqueById($_SESSION['annee_acad']);
                                        if ($currentYear):
                                        ?>
                                            <option value="<?= $currentYear['idannee_acad'] ?>">
                                                <?= htmlspecialchars($currentYear['designation']) ?>
                                            </option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2 : Encadrement -->
                        <div class="modal-section">
                            <div class="modal-section-title">
                                <span class="modal-section-number">2</span>
                                Encadrement
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="directeur" class="form-label required">
                                        <i class="fas fa-user-tie me-1 text-primary"></i>Directeur de mémoire
                                    </label>
                                    <select class="form-select select2" id="directeur" name="directeur_id" required>
                                        <option value="">Sélectionner un directeur</option>
                                        <?php
                                        $connexion = Connexion::getInstance()->getPDO();
                                        $cycle = isset($_SESSION['cycle']) ? $_SESSION['cycle'] : "";

                                        if (isset($estFinalistePremierCycle) && $estFinalistePremierCycle) {
                                            $query = "SELECT a.\"idAgent\", a.noms, g.designation as gradeDesignation 
                                                    FROM agent a 
                                                    LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                                    WHERE a.type_agent IN ('Enseignant', 'Assistant', 'Chef de travaux', 'ATER', 'Vacataire')
                                                    ORDER BY g.designation, a.noms ASC";
                                        } else {
                                            $query = "SELECT a.\"idAgent\", a.noms, g.designation as gradeDesignation 
                                                    FROM agent a 
                                                    LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                                    WHERE a.type_agent = 'Enseignant' 
                                                    AND g.designation IN ('Dr', 'Prof', 'PA', 'PO')
                                                    ORDER BY g.designation, a.noms ASC";
                                        }
                                        $stmt = $connexion->prepare($query);
                                        $stmt->execute();
                                        $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        if ($enseignants):
                                            foreach ($enseignants as $enseignant):
                                                $grade = $enseignant['gradeDesignation'] ?? '';
                                                $gradePrefix = getGradePrefix($grade);
                                                $isrestricted = false;
                                                if ($cycle == "Deuxieme" || $cycle == "Troisieme") {
                                                    if (!empty($grade) && (strpos($grade, 'Ass.1') !== false || strpos($grade, 'CT') !== false ||
                                                        strpos($grade, 'Ass.2') !== false || strpos($grade, 'Ass2') !== false)) {
                                                        $isrestricted = true;
                                                    }
                                                }
                                                if ($isrestricted) {
                                                    continue;
                                                }
                                        ?>
                                                <option value="<?= $enseignant['idAgent'] ?>">
                                                    <?= htmlspecialchars($gradePrefix . $enseignant['noms']) ?>
                                                </option>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner un directeur.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="encadreur" class="form-label">
                                        <i class="fas fa-user-graduate me-1 text-primary"></i>Encadrant <small class="text-muted">(optionnel)</small>
                                    </label>
                                    <select class="form-select select2" id="encadreur" name="encadreur_id">
                                        <option value="">Sélectionner un encadreur</option>
                                        <?php
                                        // Pour l'encadreur, récupérer tous les agents avec roles d'enseignement
                                        $queryEnseignantsAll = "SELECT a.\"idAgent\", a.noms, g.designation as gradeDesignation 
                                                            FROM agent a 
                                                            LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                                            WHERE a.type_agent IN ('Enseignant', 'Assistant', 'Chef de travaux', 'ATER', 'Vacataire')
                                                            ORDER BY g.designation, a.noms ASC";
                                        $stmtEnseignantsAll = $connexion->prepare($queryEnseignantsAll);
                                        $stmtEnseignantsAll->execute();
                                        $tousEnseignants = $stmtEnseignantsAll->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if ($tousEnseignants):
                                            foreach ($tousEnseignants as $enseignant):
                                                $grade = $enseignant['gradeDesignation'] ?? '';
                                                $gradePrefix = getGradePrefix($grade);
                                        ?>
                                                <option value="<?= $enseignant['idAgent'] ?>">
                                                    <?= htmlspecialchars($gradePrefix . $enseignant['noms']) ?>
                                                </option>
                                        <?php
                                            endforeach;
                                        endif;
                                        ?>
                                    </select>
                                    <div class="form-text">Recommandé pour certains types de recherche.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="modal-section-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Votre proposition sera soumise à validation. Le directeur et le encadrant doivent être différents.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Soumettre la proposition
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal pour proposer une reformulation -->
<div class="modal fade" id="proposerReformulationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-lightbulb me-2"></i>Proposer une reformulation de sujet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="../controller/proposer_reformulation_sujet.php" class="needs-validation" novalidate>
                    <input type="hidden" name="sujet_id" value="<?= $sujetAssigne['idsujets'] ?>">
                    <input type="hidden" name="etudiant_id" value="<?= $studentId ?>">

                    <!-- Affichage du motif de reformulation -->
                    <?php if ($sujetAssigne['statut_validation'] === 'A reformulé' && !empty($sujetAssigne['commentaire_commission'])): ?>
                        <div class="alert alert-warning mb-4">
                            <div class="d-flex">
                                <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                                <div>
                                    <strong>Motif de la reformulation demandée :</strong>
                                    <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($sujetAssigne['commentaire_commission'])) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sujet actuel -->
                    <div class="card mb-4 border-secondary">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Sujet actuel</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <strong>Intitulé actuel :</strong>
                                    <p class="text-muted mt-1"><?= htmlspecialchars($sujetAssigne['intitule']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Directeur actuel :</strong>
                                    <p class="text-muted mt-1"><?= htmlspecialchars($sujetAssigne['directeur'] ?? 'Non défini') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <strong>Encadrant actuel :</strong>
                                    <p class="text-muted mt-1"><?= htmlspecialchars($sujetAssigne['encadreur'] ?? 'Aucun') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Proposition de reformulation -->
                    <div class="card mb-4 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Votre proposition de reformulation</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label for="intitule_propose" class="form-label required">
                                    <i class="fas fa-heading me-1"></i>Nouvel intitulé proposé
                                </label>
                                <textarea class="form-control" id="intitule_propose" name="intitule_propose" rows="3" required><?= htmlspecialchars($sujetAssigne['intitule']) ?></textarea>
                                <div class="form-text">Proposez un nouvel intitulé en tenant compte des remarques de la commission.</div>
                                <div class="invalid-feedback">Veuillez saisir le nouvel intitulé du sujet.</div>
                            </div>

                            <div class="row mb-4">
                                <!-- Champ de sélection pour la spécialisation -->
                                <div class="col-md-6">
                                    <label for="idSpecialisation_propose" class="form-label required">
                                        <i class="fas fa-microscope me-1"></i>Spécialisation proposée
                                    </label>
                                    <select class="form-select" id="idSpecialisation_propose" name="idSpecialisation_propose" required>
                                        <option value="">Sélectionner une spécialisation</option>
                                        <?php
                                        // Récupérer les spécialisations de l'orientation de l'étudiant
                                        $connexion = Connexion::getInstance()->getPDO();

                                        // Récupérer l'orientation de l'étudiant connecté
                                        $etudiantId = $_SESSION['student_id'] ?? 0;

                                        // Requête pour obtenir l'orientation de l'étudiant
                                        $queryOrientation = "SELECT p.orientation_idorientation 
                                                            FROM etudiant e 
                                                            JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                                                            WHERE e.idetudiant = :etudiantId";

                                        $stmtOrientation = $connexion->prepare($queryOrientation);
                                        $stmtOrientation->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
                                        $stmtOrientation->execute();
                                        $orientationInfo = $stmtOrientation->fetch(PDO::FETCH_ASSOC);

                                        $orientationId = $orientationInfo ? $orientationInfo['orientation_idorientation'] : 0;

                                        // Requête pour obtenir les spécialisations de cette orientation
                                        $query = "SELECT s.*, ur.\"designation_UR\" 
                                                    FROM specialisation s 
                                                    JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
                                                    WHERE s.idorientation = :orientationId 
                                                    ORDER BY ur.\"designation_UR\", s.designation ASC";

                                        $stmt = $connexion->prepare($query);
                                        $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
                                        $stmt->execute();
                                        $specialisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        // Regrouper les spécialisations par unité de recherche
                                        $specialisationsByUR = [];
                                        foreach ($specialisations as $specialisation) {
                                            $urName = $specialisation['designation_UR'];
                                            if (!isset($specialisationsByUR[$urName])) {
                                                $specialisationsByUR[$urName] = [];
                                            }
                                            $specialisationsByUR[$urName][] = $specialisation;
                                        }

                                        if (!empty($specialisationsByUR)):
                                            foreach ($specialisationsByUR as $urName => $specs):
                                        ?>
                                                <optgroup label="<?= htmlspecialchars($urName) ?>">
                                                    <?php foreach ($specs as $specialisation): ?>
                                                        <option value="<?= $specialisation['idSpecialisation'] ?>"
                                                            <?= ($specialisation['idSpecialisation'] == $sujetAssigne['idSpecialisation']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($specialisation['designation']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php
                                            endforeach;
                                        else:
                                            ?>
                                            <option value="" disabled>Aucune spécialisation disponible pour votre orientation</option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner une spécialisation.</div>
                                </div>
                            </div>

                            <!-- Sélection du Directeur -->
                            <div class="mb-4">
                                <label for="directeur_id_propose" class="form-label required">
                                    <i class="fas fa-user-tie me-1"></i>Directeur de mémoire proposé
                                </label>
                                <select class="form-select" id="directeur_id_propose" name="directeur_id_propose" required>
                                    <option value="">Sélectionner un directeur</option>
                                    <?php
                                    // Récupération directe des enseignants depuis la base de données
                                    $cycle = isset($_SESSION['cycle']) ? $_SESSION['cycle'] : "";

                                    $query = "SELECT a.\"idAgent\", a.noms, g.designation as gradeDesignation 
                                                FROM agent a 
                                                LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                                WHERE a.type_agent = 'Enseignant' 
                                                ORDER BY g.designation, a.noms ASC";

                                    $stmt = $connexion->prepare($query);
                                    $stmt->execute();
                                    $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    if ($enseignants):
                                        foreach ($enseignants as $enseignant):
                                            $grade = $enseignant['gradeDesignation'] ?? '';
                                            $gradePrefix = getGradePrefix($grade);
                                            $isrestricted = false;
                                            if ($cycle == "Deuxieme" || $cycle == "Troisieme") {
                                                if (!empty($grade) && (strpos($grade, 'Ass.1') !== false || strpos($grade, 'CT') !== false ||
                                                    strpos($grade, 'Ass.2') !== false || strpos($grade, 'Ass2') !== false)) {
                                                    $isrestricted = true;
                                                }
                                            }
                                            if ($isrestricted) {
                                                continue;
                                            }
                                    ?>
                                            <option value="<?= $enseignant['idAgent'] ?>"
                                                <?= ($enseignant['idAgent'] == $sujetAssigne['idDirecteur']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($gradePrefix . $enseignant['noms']) ?>
                                            </option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                                <div class="invalid-feedback">Veuillez sélectionner un directeur de mémoire.</div>
                            </div>

                            <!-- Sélection de l'Encadrant -->
                            <div class="mb-4">
                                <label for="encadreur_id_propose" class="form-label">
                                    <i class="fas fa-user-graduate me-1"></i>Encadrant proposé (optionnel)
                                </label>
                                <select class="form-select" id="encadreur_id_propose" name="encadreur_id_propose">
                                    <option value="">Sélectionner un encadreur</option>
                                    <?php
                                    // Récupérer tous les agents avec roles d'enseignement pour l'encadreur
                                    $queryEnseignantsAll = "SELECT a.\"idAgent\", a.noms, g.designation as gradeDesignation 
                                                        FROM agent a 
                                                        LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                                        WHERE a.type_agent IN ('Enseignant', 'Assistant', 'Chef de travaux', 'ATER', 'Vacataire')
                                                        ORDER BY g.designation, a.noms ASC";
                                    $stmtEnseignantsAll = $connexion->prepare($queryEnseignantsAll);
                                    $stmtEnseignantsAll->execute();
                                    $tousEnseignants = $stmtEnseignantsAll->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if ($tousEnseignants):
                                        foreach ($tousEnseignants as $enseignant):
                                            $grade = $enseignant['gradeDesignation'] ?? '';
                                            $gradePrefix = getGradePrefix($grade);
                                    ?>
                                            <option value="<?= $enseignant['idAgent'] ?>"
                                                <?= ($enseignant['idAgent'] == ($sujetAssigne['idEncadrant'] ?? $sujetAssigne['idEncadreur'] ?? null)) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($gradePrefix . $enseignant['noms']) ?>
                                            </option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                                <div class="form-text">L'encadrant est facultatif mais recommandé pour certains types de recherche.</div>
                            </div>

                            <!-- Justification -->
                            <div class="mb-4">
                                <label for="justification_etudiant" class="form-label required">
                                    <i class="fas fa-comment-dots me-1"></i>Justification de votre reformulation
                                </label>
                                <textarea class="form-control" id="justification_etudiant" name="justification_etudiant" rows="5" required></textarea>
                                <div class="form-text">
                                    Expliquez en détail pourquoi vous proposez ces modifications et comment elles répondent aux remarques de la commission.
                                    Soyez précis et argumentez vos choix.
                                </div>
                                <div class="invalid-feedback">Veuillez fournir une justification détaillée.</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <div class="d-flex">
                            <i class="fas fa-info-circle me-3 fs-4"></i>
                            <div>
                                <strong>Information importante</strong>
                                <p class="mb-0">
                                    Votre proposition de reformulation sera examinée par l'administration.
                                    Si elle est acceptée, votre sujet sera mis à jour avec les nouvelles informations.
                                    Si elle est refusée, vous devrez proposer une nouvelle reformulation.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Annuler
                        </button>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-paper-plane me-2"></i>Soumettre la reformulation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour l'historique du sujet -->
<div class="modal fade" id="historiqueSujetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-history me-2"></i>Historique du sujet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historiqueSujetContent">
                    <div class="text-center p-3">
                        <div class="spinner-border text-secondary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2">Chargement de l'historique...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de modification du sujet -->
<div class="modal fade" id="modifierSujetModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Modifier mon sujet de recherche
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="../controller/modifier_sujet_etudiant.php" class="needs-validation" novalidate>
                    <input type="hidden" name="sujet_id" value="<?= $sujetAssigne['idsujets'] ?>">
                    <input type="hidden" name="etudiant_id" value="<?= $studentId ?>">

                    <div class="mb-4">
                        <label for="intitule_modif" class="form-label required">
                            <i class="fas fa-heading me-1"></i>Intitulé du sujet
                        </label>
                        <textarea class="form-control" id="intitule_modif" name="intitule" rows="3" required><?= htmlspecialchars($sujetAssigne['intitule']) ?></textarea>
                        <div class="form-text">Soyez précis et concis dans la formulation de votre sujet.</div>
                        <div class="invalid-feedback">Veuillez saisir l'intitulé du sujet.</div>
                    </div>

                    <div class="row mb-4">
                        <!-- Champ de sélection pour la spécialisation -->
                        <div class="col-md-6">
                            <label for="idSpecialisation_modif" class="form-label required">
                                <i class="fas fa-microscope me-1"></i>Spécialisation
                            </label>
                            <select class="form-select" id="idSpecialisation_modif" name="idSpecialisation" required>
                                <option value="">Sélectionner une spécialisation</option>
                                <?php
                                // Récupérer les spécialisations de l'orientation de l'étudiant
                                $connexion = Connexion::getInstance()->getPDO();

                                // Récupérer l'orientation de l'étudiant connecté
                                $etudiantId = $_SESSION['student_id'] ?? 0;

                                // Requête pour obtenir l'orientation de l'étudiant
                                $queryOrientation = "SELECT p.orientation_idorientation 
                                                    FROM etudiant e 
                                                    JOIN promotion p ON e.promotion_idpromotion = p.idpromotion 
                                                    WHERE e.idetudiant = :etudiantId";

                                $stmtOrientation = $connexion->prepare($queryOrientation);
                                $stmtOrientation->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
                                $stmtOrientation->execute();
                                $orientationInfo = $stmtOrientation->fetch(PDO::FETCH_ASSOC);

                                $orientationId = $orientationInfo ? $orientationInfo['orientation_idorientation'] : 0;

                                // Requête pour obtenir les spécialisations de cette orientation
                                $query = "SELECT s.*, ur.\"designation_UR\" 
                                            FROM specialisation s 
                                            JOIN unite_recherche ur ON s.\"idUnite_recherche\" = ur.idunite_recherche
                                            WHERE s.idorientation = :orientationId 
                                            ORDER BY ur.\"designation_UR\", s.designation ASC";

                                $stmt = $connexion->prepare($query);
                                $stmt->bindParam(':orientationId', $orientationId, PDO::PARAM_INT);
                                $stmt->execute();
                                $specialisations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                // Regrouper les spécialisations par unité de recherche
                                $specialisationsByUR = [];
                                foreach ($specialisations as $specialisation) {
                                    $urName = $specialisation['designation_UR'];
                                    if (!isset($specialisationsByUR[$urName])) {
                                        $specialisationsByUR[$urName] = [];
                                    }
                                    $specialisationsByUR[$urName][] = $specialisation;
                                }

                                if (!empty($specialisationsByUR)):
                                    foreach ($specialisationsByUR as $urName => $specs):
                                ?>
                                        <optgroup label="<?= htmlspecialchars($urName) ?>">
                                            <?php foreach ($specs as $specialisation): ?>
                                                <option value="<?= $specialisation['idSpecialisation'] ?>"
                                                    <?= ($specialisation['idSpecialisation'] == $sujetAssigne['idSpecialisation']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($specialisation['designation']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php
                                    endforeach;
                                else:
                                    ?>
                                    <option value="" disabled>Aucune spécialisation disponible pour votre orientation</option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une spécialisation.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="annee_acad_modif" class="form-label required">
                                <i class="fas fa-calendar-alt me-1"></i>Année Académique
                            </label>
                            <select class="form-select" id="annee_acad_modif" name="annee_acad" required>
                                <?php
                                // Récupérer l'année académique actuelle
                                $currentYear = $universite->getAnneeAcademiqueById($_SESSION['annee_acad']);
                                if ($currentYear):
                                ?>
                                    <option value="<?= $currentYear['idannee_acad'] ?>">
                                        <?= htmlspecialchars($currentYear['designation']) ?>
                                    </option>
                                <?php endif; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>

                    <!-- Sélection du Directeur -->
                    <div class="mb-4">
                        <label for="directeur_modif" class="form-label required">
                            <i class="fas fa-user-tie me-1"></i>Directeur de mémoire
                        </label>
                        <select class="form-select select2" id="directeur_modif" name="directeur_id" required>
                            <option value="">Sélectionner un directeur</option>
                            <?php
                            // Récupération directe des enseignants depuis la base de données
                            $cycle = isset($_SESSION['cycle']) ? $_SESSION['cycle'] : "";

                            $query = "SELECT a.\"idAgent\", a.noms, g.designation as gradeDesignation 
                                        FROM agent a 
                                        LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                        WHERE a.type_agent = 'Enseignant' 
                                        AND g.designation IN ('Dr', 'Prof', 'PA', 'PO')
                                        ORDER BY g.designation, a.noms ASC";

                            $stmt = $connexion->prepare($query);
                            $stmt->execute();
                            $enseignants = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            if ($enseignants):
                                foreach ($enseignants as $enseignant):
                                    $grade = $enseignant['gradeDesignation'] ?? '';
                                    $gradePrefix = getGradePrefix($grade);
                                    $isrestricted = false;
                                    if ($cycle == "Deuxieme" || $cycle == "Troisieme") {
                                        if (!empty($grade) && (strpos($grade, 'Ass.1') !== false || strpos($grade, 'CT') !== false ||
                                            strpos($grade, 'Ass.2') !== false || strpos($grade, 'Ass2') !== false)) {
                                            $isrestricted = true;
                                        }
                                    }
                                    if ($isrestricted) {
                                        continue;
                                    }
                            ?>
                                    <option value="<?= $enseignant['idAgent'] ?>"
                                        <?= ($enseignant['idAgent'] == $sujetAssigne['idDirecteur']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($gradePrefix . $enseignant['noms']) ?>
                                    </option>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un directeur de mémoire.</div>
                    </div>

                    <!-- Sélection de l'Encadrant -->
                    <div class="mb-4">
                        <label for="encadreur_modif" class="form-label">
                            <i class="fas fa-user-graduate me-1"></i>Encadrant (optionnel)
                        </label>
                        <select class="form-select select2" id="encadreur_modif" name="encadreur_id">
                            <option value="">Sélectionner un encadrant</option>
                            <?php
                            // Récupérer tous les agents avec roles d'enseignement pour l'encadreur
                            $queryEnseignantsAll2 = "SELECT a.\"idAgent\", a.noms, g.designation as gradeDesignation 
                                                FROM agent a 
                                                LEFT JOIN grade g ON a.grade_id = g.idgrade 
                                                WHERE a.type_agent IN ('Enseignant', 'Assistant', 'Chef de travaux', 'ATER', 'Vacataire')
                                                ORDER BY g.designation, a.noms ASC";
                            $stmtEnseignantsAll2 = $connexion->prepare($queryEnseignantsAll2);
                            $stmtEnseignantsAll2->execute();
                            $tousEnseignants2 = $stmtEnseignantsAll2->fetchAll(PDO::FETCH_ASSOC);
                            
                            if ($tousEnseignants2):
                                foreach ($tousEnseignants2 as $enseignant):
                                    $grade = $enseignant['gradeDesignation'] ?? '';
                                    $gradePrefix = getGradePrefix($grade);
                            ?>
                                    <option value="<?= $enseignant['idAgent'] ?>"
                                        <?= ($enseignant['idAgent'] == ($sujetAssigne['idEncadrant'] ?? $sujetAssigne['idEncadreur'] ?? null)) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($gradePrefix . $enseignant['noms']) ?>
                                    </option>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </select>
                        <div class="form-text">L'encadrant est facultatif mais recommandé pour certains types de recherche.</div>
                    </div>

                    <?php if ($sujetAssigne['statut_validation'] === 'A reformulé' && !empty($sujetAssigne['commentaire_commission'])): ?>
                        <div class="alert alert-warning mb-4">
                            <div class="d-flex">
                                <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                                <div<strong>Motif de la reformulation :</strong>
                                    <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($sujetAssigne['commentaire_commission'])) ?></p>
                            </div>
                        </div>
            </div>
        <?php endif; ?>

        <div class="alert alert-info">
            <div class="d-flex">
                <i class="fas fa-info-circle me-3 fs-4"></i>
                <div>
                    <strong>Information importante</strong>
                    <p class="mb-0">
                        La modification de votre sujet le remettra en statut "En attente" pour une nouvelle validation par l'administration.
                        Assurez-vous que le directeur et le encadrant soient différents.
                    </p>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                <i class="fas fa-times me-2"></i>Annuler
            </button>
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-save me-2"></i>Modifier le sujet
            </button>
        </div>
        </form>
        </div>
    </div>
</div>
</div>


<script>
    // Fonction pour voir l'historique du sujet
    function voirHistoriqueSujet(sujetId) {
        const modal = new bootstrap.Modal(document.getElementById('historiqueSujetModal'));
        const historiqueContent = document.getElementById('historiqueSujetContent');

        // Afficher le modal
        modal.show();

        // Afficher un indicateur de chargement
        historiqueContent.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-3">Chargement de l'historique...</p>
        </div>
    `;

        // Charger les données
        fetch(`../controller/get_sujet_reformulations.php?sujet_id=${sujetId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    let html = '';

                    // Afficher l'historique
                    if (data.historique && data.historique.length > 0) {
                        html += '<h6 class="mb-3"><i class="fas fa-history me-2"></i>Historique des modifications</h6>';
                        html += '<div class="timeline">';

                        data.historique.forEach(item => {
                            const dateFormatee = new Date(item.date_action).toLocaleDateString('fr-FR', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });

                            const badgeClass = {
                                'Création': 'primary',
                                'Modification': 'info',
                                'Validation': 'success',
                                'Reformulation demandée': 'warning',
                                'Reformulation acceptée': 'success',
                                'Reformulation refusée': 'danger'
                            } [item.action] || 'secondary';

                            html += `
                            <div class="timeline-item">
                                <div class="timeline-marker bg-${badgeClass}"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong class="text-${badgeClass}">
                                            <i class="fas fa-${getActionIcon(item.action)} me-1"></i>
                                            ${item.action}
                                        </strong>
                                        <small class="text-muted">${dateFormatee}</small>
                                    </div>
                                    ${item.intitule_avant && item.intitule_apres ? `
                                        <div class="mb-2">
                                            <small class="text-muted">Intitulé:</small><br>
                                            <del class="text-muted">${item.intitule_avant}</del><br>
                                            <strong class="text-success">${item.intitule_apres}</strong>
                                        </div>
                                    ` : ''}
                                    ${item.statut_avant && item.statut_apres ? `
                                        <div class="mb-2">
                                            <small class="text-muted">Statut:</small>
                                            <span class="badge bg-secondary me-2">${item.statut_avant}</span>
                                            <i class="fas fa-arrow-right me-2"></i>
                                            <span class="badge bg-${badgeClass}">${item.statut_apres}</span>
                                        </div>
                                    ` : ''}
                                    ${item.commentaire ? `
                                        <div class="p-2 bg-light rounded">
                                            <small><strong>Commentaire:</strong> ${item.commentaire}</small>
                                        </div>
                                    ` : ''}
                                    ${item.auteur_nom ? `
                                        <small class="text-muted">Par: ${item.auteur_nom} (${item.type_utilisateur})</small>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                        });
                        html += '</div>';
                    }

                    // Afficher les reformulations
                    if (data.reformulations && data.reformulations.length > 0) {
                        html += '<h6 class="mb-3 mt-4"><i class="fas fa-lightbulb me-2"></i>Propositions de reformulation</h6>';

                        data.reformulations.forEach(reformulation => {
                            const dateProposition = new Date(reformulation.date_proposition).toLocaleDateString('fr-FR', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });

                            const statusClass = {
                                'En attente': 'warning',
                                'Acceptée': 'success',
                                'Refusée': 'danger'
                            } [reformulation.statut_reformulation] || 'secondary';

                            html += `
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Proposition de reformulation</h6>
                                    <span class="badge bg-${statusClass}">${reformulation.statut_reformulation}</span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Nouvel intitulé proposé:</strong><br>
                                            <p class="text-primary">${reformulation.intitule_propose}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Date de proposition:</strong><br>
                                            <small class="text-muted">${dateProposition}</small>
                                        </div>
                                    </div>
                                    
                                    ${reformulation.specialisation_nom ? `
                                        <div class="mb-2">
                                            <strong>Spécialisation:</strong> ${reformulation.specialisation_nom}
                                        </div>
                                    ` : ''}
                                    
                                    ${reformulation.directeur_nom ? `
                                        <div class="mb-2">
                                            <strong>Directeur proposé:</strong> ${reformulation.directeur_nom}
                                        </div>
                                    ` : ''}
                                    
                                    ${reformulation.encadreur_nom ? `
                                        <div class="mb-2">
                                            <strong>Encadrant proposé:</strong> ${reformulation.encadreur_nom}
                                        </div>
                                    ` : ''}
                                    
                                    <div class="mb-3">
                                        <strong>Justification:</strong>
                                        <div class="p-2 bg-light rounded mt-1">
                                            ${reformulation.justification_etudiant.replace(/\n/g, '<br>')}
                                        </div>
                                    </div>
                                    
                                    ${reformulation.commentaire_reponse ? `
                                        <div class="alert alert-${statusClass === 'success' ? 'success' : 'danger'}">
                                            <strong>Réponse de l'administration:</strong><br>
                                            ${reformulation.commentaire_reponse.replace(/\n/g, '<br>')}
                                            ${reformulation.date_traitement ? `
                                                <br><small class="text-muted">Le ${new Date(reformulation.date_traitement).toLocaleDateString('fr-FR')}</small>
                                            ` : ''}
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                        });
                    }

                    if (!html) {
                        html = '<div class="alert alert-info">Aucun historique disponible pour ce sujet.</div>';
                    }

                    historiqueContent.innerHTML = html;
                } else {
                    historiqueContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Erreur lors du chargement: ${data.error || 'Erreur inconnue'}
                    </div>
                `;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                historiqueContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur de connexion lors du chargement de l'historique.
                    <br><small>Détails: ${error.message}</small>
                </div>
            `;
            });
    }

    // Fonction pour obtenir l'icône selon l'action
    function getActionIcon(action) {
        const icons = {
            'Création': 'plus-circle',
            'Modification': 'edit',
            'Validation': 'check-circle',
            'Reformulation demandée': 'exclamation-triangle',
            'Reformulation acceptée': 'check-circle',
            'Reformulation refusée': 'times-circle'
        };
        return icons[action] || 'circle';
    }

    // Gestion du suivi des enseignements
    document.addEventListener('DOMContentLoaded', function() {
        // Afficher les messages de session (succès/erreur)
        <?php if (isset($_SESSION['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '<?= addslashes($_SESSION['success']) ?>',
                timer: 3000,
                showConfirmButton: false
            });
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '<?= addslashes($_SESSION['error']) ?>',
                confirmButtonText: 'OK'
            });
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        // Charger les données de suivi si l'onglet est actif
        const suiviTab = document.getElementById('suivi-enseignements-tab');
        if (suiviTab) {
            // Ajouter un event listener pour le clic sur l'onglet
            suiviTab.addEventListener('click', function() {
                console.log('Onglet suivi enseignements cliqué');
                setTimeout(() => {
                    chargerSuiviEnseignements();
                }, 100);
            });

            // Ajouter un event listener pour l'événement Bootstrap tab shown
            suiviTab.addEventListener('shown.bs.tab', function() {
                console.log('Onglet suivi enseignements affiché');
                chargerSuiviEnseignements();
            });

            // Charger automatiquement si l'onglet est dans l'URL ou le hash
            const urlParams = new URLSearchParams(window.location.search);
            const hash = window.location.hash;
            if (urlParams.get('tab') === 'suivi_enseignements' || hash === '#suivi-enseignements') {
                setTimeout(() => {
                    console.log('Activation automatique de l\'onglet suivi enseignements');
                    suiviTab.click();
                    setTimeout(() => {
                        chargerSuiviEnseignements();
                    }, 200);
                }, 500);
            }
        }

        // Vérifier si l'onglet est déjà actif au chargement de la page
        setTimeout(() => {
            const suiviTabPane = document.getElementById('suivi-enseignements');
            if (suiviTabPane && suiviTabPane.classList.contains('show') && suiviTabPane.classList.contains('active')) {
                console.log('Onglet suivi enseignements déjà actif au chargement');
                chargerSuiviEnseignements();
            }
        }, 1000);

        // Validation du formulaire de suivi
        const formSuivi = document.querySelector('#ajouterSuiviModal form');
        if (formSuivi) {
            formSuivi.addEventListener('submit', function(e) {
                if (!formSuivi.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                } else {
                    // Validation des heures
                    const heureDebut = document.getElementById('heure_debut').value;
                    const heureFin = document.getElementById('heure_fin').value;

                    if (heureDebut && heureFin && heureDebut >= heureFin) {
                        e.preventDefault();
                        alert('L\'heure de début doit être antérieure à l\'heure de fin.');
                        return;
                    }
                }
                formSuivi.classList.add('was-validated');
            });
        }

        // === FONCTIONS POUR LE PLAN DE TRAVAIL ===

        // Variables globales pour le plan de travail
        let chapitreCounter = 1;

        // Ajouter un chapitre dynamiquement
        window.ajouterChapitre = function() {
            const container = document.getElementById('chapitres-container');
            const chapitreDiv = document.createElement('div');
            chapitreDiv.className = 'chapitre-item mb-3 p-3 border rounded';
            chapitreDiv.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Chapitre ${chapitreCounter}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="supprimerChapitre(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-12 mb-2">
                    <label class="form-label">Titre du chapitre</label>
                    <input type="text" class="form-control" name="chapitres[${chapitreCounter}][titre]" required>
                </div>
                <div class="col-md-12 mb-2">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="chapitres[${chapitreCounter}][description]" rows="2"></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Objectifs du chapitre</label>
                    <textarea class="form-control" name="chapitres[${chapitreCounter}][objectifs]" rows="2"></textarea>
                </div>
                <input type="hidden" name="chapitres[${chapitreCounter}][numero]" value="${chapitreCounter}">
            </div>
        `;
            container.appendChild(chapitreDiv);
            chapitreCounter++;
        };

        // Supprimer un chapitre
        window.supprimerChapitre = function(button) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce chapitre ?')) {
                button.closest('.chapitre-item').remove();
            }
        };

        // Voir les détails d'un chapitre
        window.voirChapitre = function(idChapitre) {
            // Charger les détails du chapitre via AJAX
            fetch(`../controller/plan_travail_controller.php?action=get_chapitre&id=${idChapitre}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Afficher les détails dans une modal
                        showChapitreModal(data.chapitre);
                    } else {
                        alert('Erreur lors du chargement du chapitre');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur de connexion');
                });
        };

        // Soumettre un chapitre
        window.soumettreCharpitre = function(idChapitre) {
            // Ouvrir une modal pour la soumission
            showSoumissionChapitreModal(idChapitre);
        };

        // Afficher la modal des détails d'un chapitre
        function showChapitreModal(chapitre) {
            let modalHtml = `
            <div class="modal fade" id="chapitreDetailModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-book-open me-2"></i>Chapitre ${chapitre.numero_chapitre}: ${chapitre.titre_chapitre}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Statut:</strong>
                                    <span class="badge bg-${getStatusColor(chapitre.statut)} ms-2">${chapitre.statut}</span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Avancement:</strong>
                                    <span class="ms-2">${chapitre.pourcentage_avancement || 0}%</span>
                                </div>
                            </div>`;

            if (chapitre.deadline) {
                modalHtml += `
                <div class="alert alert-info">
                    <strong>Deadline:</strong> ${new Date(chapitre.deadline).toLocaleDateString('fr-FR')}
                    ${chapitre.commentaire_deadline ? '<br><small>' + chapitre.commentaire_deadline + '</small>' : ''}
                </div>`;
            }

            if (chapitre.description) {
                modalHtml += `
                <div class="mb-3">
                    <h6>Description:</h6>
                    <p>${chapitre.description.replace(/\n/g, '<br>')}</p>
                </div>`;
            }

            if (chapitre.objectifs_chapitre) {
                modalHtml += `
                <div class="mb-3">
                    <h6>Objectifs:</h6>
                    <p>${chapitre.objectifs_chapitre.replace(/\n/g, '<br>')}</p>
                </div>`;
            }

            if (chapitre.commentaire_directeur) {
                modalHtml += `
                <div class="alert alert-warning">
                    <h6>Commentaire du directeur:</h6>
                    <p>${chapitre.commentaire_directeur.replace(/\n/g, '<br>')}</p>
                </div>`;
            }

            modalHtml += `
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>`;

            if (chapitre.statut !== 'Terminé') {
                modalHtml += `
                <button type="button" class="btn btn-primary" onclick="soumettreCharpitre(${chapitre.idchapitre_plan})">
                    <i class="fas fa-upload me-1"></i>Soumettre
                </button>`;
            }

            modalHtml += `
                        </div>
                    </div>
                </div>
            </div>
        `;

            // Supprimer la modal existante si elle existe
            const existingModal = document.getElementById('chapitreDetailModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Ajouter la nouvelle modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Afficher la modal
            const modal = new bootstrap.Modal(document.getElementById('chapitreDetailModal'));
            modal.show();
        }

        // Obtenir la couleur du statut
        function getStatusColor(statut) {
            switch (statut) {
                case 'Terminé':
                    return 'success';
                case 'En cours':
                    return 'primary';
                case 'En révision':
                    return 'warning';
                default:
                    return 'secondary';
            }
        }

        // Afficher la modal de soumission d'un chapitre
        function showSoumissionChapitreModal(idChapitre) {
            const modalHtml = `
            <div class="modal fade" id="soumissionChapitreModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-upload me-2"></i>Soumettre le Chapitre
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="../controller/plan_travail_controller.php" method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="soumettre_chapitre">
                                <input type="hidden" name="chapitre_id" value="${idChapitre}">
                                

                                
                                <div class="mb-3">
                                    <label for="fichier_chapitre" class="form-label">
                                        Fichier du chapitre 
                                        <i class="fas fa-info-circle text-info ms-1" 
                                           data-bs-toggle="tooltip" 
                                           title="Si vous joignez un fichier, une tâche sera automatiquement créée avec le titre du chapitre"></i>
                                    </label>
                                    <input type="file" class="form-control" name="fichier_chapitre" accept=".pdf,.doc,.docx">
                                    <div class="form-text">Formats acceptés: PDF, DOC, DOCX. Une tâche sera automatiquement créée si un fichier est joint.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="commentaire_soumission" class="form-label">Commentaire (optionnel)</label>
                                    <textarea class="form-control" name="commentaire_soumission" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i>Soumettre
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

            // Supprimer la modal existante si elle existe
            const existingModal = document.getElementById('soumissionChapitreModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Ajouter la nouvelle modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Afficher la modal
            const modal = new bootstrap.Modal(document.getElementById('soumissionChapitreModal'));
            modal.show();

            // Initialiser les tooltips Bootstrap
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
        }

        // Initialiser les chapitres par défaut lors du chargement
        if (document.getElementById('chapitres-container')) {
            // Ajouter 3 chapitres par défaut
            for (let i = 0; i < 3; i++) {
                ajouterChapitre();
            }
        }
    });

    function chargerSuiviEnseignements() {
        const listeSuivis = document.getElementById('liste-suivis');

        // Afficher un indicateur de chargement
        listeSuivis.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-3">Chargement des données...</p>
        </div>
    `;

        fetch('../controller/get_suivi_enseignements.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                return response.text().then(text => {
                    console.log('Raw response:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                    }
                });
            })
            .then(data => {
                console.log('Parsed data:', data);

                if (data.error) {
                    listeSuivis.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.error}
                        <br><small>Debug info: ${JSON.stringify(data.debug_info || {})}</small>
                    </div>
                `;
                    return;
                }

                // Mettre à jour les statistiques
                if (data.stats) {
                    const totalSeances = document.getElementById('total-seances');
                    const matieresEnseignees = document.getElementById('matieres-enseignees');
                    const totalHeures = document.getElementById('total-heures');
                    const joursCours = document.getElementById('jours-cours');

                    if (totalSeances) totalSeances.textContent = data.stats.total_seances || 0;
                    if (matieresEnseignees) matieresEnseignees.textContent = data.stats.matieres_enseignees || 0;
                    if (totalHeures) totalHeures.textContent = (parseFloat(data.stats.total_heures) || 0).toFixed(1) + 'h';
                    if (joursCours) joursCours.textContent = data.stats.jours_cours || 0;
                }

                // Afficher la liste des suivis
                if (data.suivis && data.suivis.length > 0) {
                    let html = '';

                    // Ajouter un message informatif si des enregistrements d'anciens chefs sont présents
                    const hasOldRecords = data.suivis.some(s => !s.est_chef_actuel);
                    if (hasOldRecords) {
                        html += `
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Cette liste inclut l'historique complet des séances enregistrées par tous les chefs de promotion de votre classe.
                            Les enregistrements marqués <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Vous</span> sont ceux que vous avez créés.
                        </div>
                    `;
                    }

                    html += '<div class="table-responsive">';
                    html += '<table class="table table-striped table-hover">';
                    html += `
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Matière</th>
                            <th>Horaire</th>
                            <th>Type</th>
                            <th>Enseignant</th>
                            <th>Salle</th>
                            <th>Durée</th>
                            <th>Enregistré par</th>
                        </tr>
                    </thead>
                    <tbody>
                `;

                    data.suivis.forEach(suivi => {
                        const dateFormatee = new Date(suivi.date_cours).toLocaleDateString('fr-FR');
                        const heureDebut = suivi.heure_debut.substring(0, 5);
                        const heureFin = suivi.heure_fin.substring(0, 5);

                        // Calculer la durée
                        const debut = new Date(`2000-01-01T${suivi.heure_debut}`);
                        const fin = new Date(`2000-01-01T${suivi.heure_fin}`);
                        const dureeMs = fin - debut;
                        const dureeHeures = Math.floor(dureeMs / (1000 * 60 * 60));
                        const dureeMinutes = Math.floor((dureeMs % (1000 * 60 * 60)) / (1000 * 60));
                        const dureeTexte = `${dureeHeures}h${dureeMinutes > 0 ? dureeMinutes.toString().padStart(2, '0') : ''}`;

                        const enseignant = suivi.nom_enseignant ?
                            `${suivi.grade_enseignant || ''} ${suivi.nom_enseignant}`.trim() :
                            '<span class="text-muted">Non spécifié</span>';

                        const typeClass = {
                            'CM': 'primary',
                            'TD': 'success',
                            'TP': 'warning',
                            'Evaluation': 'danger'
                        } [suivi.type_cours] || 'secondary';

                        // Ajouter un indicateur si c'est le chef actuel qui a enregistré
                        const estChefActuel = suivi.est_chef_actuel;
                        const badgeChef = estChefActuel ?
                            '<span class="badge bg-success ms-2"><i class="fas fa-check-circle me-1"></i>Vous</span>' :
                            '';

                        html += `
                        <tr>
                            <td>${dateFormatee}</td>
                            <td>
                                <div>
                                    <strong>${suivi.designationECUE}</strong>
                                    <br><small class="text-muted">${suivi.designationUE}</small>
                                </div>
                            </td>
                            <td>${heureDebut} - ${heureFin}</td>
                            <td><span class="badge bg-${typeClass}">${suivi.type_cours}</span></td>
                            <td>${enseignant}</td>
                            <td>${suivi.salle || '<span class="text-muted">Non spécifiée</span>'}</td>
                            <td>${dureeTexte}</td>
                            <td>
                                <div>
                                    ${suivi.nom_chef_promotion || 'Chef de promotion'}
                                    ${badgeChef}
                                    ${suivi.date_nomination_chef ? 
                                        `<br><small class="text-muted">Nommé le ${new Date(suivi.date_nomination_chef).toLocaleDateString('fr-FR')}</small>` : 
                                        ''}
                                </div>
                            </td>
                        </tr>
                    `;

                        if (suivi.commentaire) {
                            html += `
                            <tr class="table-light">
                                <td colspan="8">
                                    <small><strong>Commentaire:</strong> ${suivi.commentaire}</small>
                                </td>
                            </tr>
                        `;
                        }
                    });

                    html += '</tbody></table></div>';

                    // Ajouter les statistiques par type
                    if (data.stats_par_type && data.stats_par_type.length > 0) {
                        html += '<div class="mt-4"><h6>Répartition par type de cours</h6>';
                        html += '<div class="row">';

                        data.stats_par_type.forEach(stat => {
                            const typeClass = {
                                'CM': 'primary',
                                'TD': 'success',
                                'TP': 'warning',
                                'Evaluation': 'danger'
                            } [stat.type_cours] || 'secondary';

                            html += `
                            <div class="col-md-3 mb-2">
                                <div class="card border-${typeClass}">
                                    <div class="card-body text-center p-2">
                                        <span class="badge bg-${typeClass} mb-1">${stat.type_cours}</span>
                                        <div><strong>${stat.nombre_seances}</strong> séances</div>
                                        <div><small>${parseFloat(stat.total_heures).toFixed(1)}h</small></div>
                                    </div>
                                </div>
                            </div>
                        `;
                        });

                        html += '</div></div>';
                    }

                    listeSuivis.innerHTML = html;
                } else {
                    listeSuivis.innerHTML = `
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucune séance de cours enregistrée pour le moment.
                        <br><small>Utilisez le bouton "Nouvelle séance" pour commencer le suivi.</small>
                    </div>
                `;
                }
            })
            .catch(error => {
                console.error('Erreur complète:', error);
                listeSuivis.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erreur lors du chargement des données: ${error.message}
                    <br><small>Vérifiez la console du navigateur pour plus de détails.</small>
                    <br><button class="btn btn-sm btn-outline-primary mt-2" onclick="chargerSuiviEnseignements()">
                        <i class="fas fa-redo me-1"></i>Réessayer
                    </button>
                </div>
            `;
            });
    }

    // Fonctions pour les plans de travail
    function telechargerPlan(planId) {
        // Rediriger vers le contrôleur de téléchargement
        window.open(`../controller/telecharger_plan.php?plan_id=${planId}`, '_blank');
    }

    function chargerHistoriquePlan(planId) {
        const historiqueContent = document.getElementById('historiqueContent');

        fetch(`../controller/plan_travail_controller.php?action=get_historique&plan_id=${planId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (data.historique && data.historique.length > 0) {
                        html = '<div class="timeline">';
                        data.historique.forEach(item => {
                            const dateFormatee = new Date(item.date_action).toLocaleDateString('fr-FR', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });

                            const badgeClass = {
                                'Validé': 'success',
                                'A reformulé': 'danger',
                                'En attente': 'warning',
                                'Modifié': 'info'
                            } [item.statut] || 'secondary';

                            html += `
                            <div class="timeline-item">
                                <div class="timeline-marker bg-${badgeClass}"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong class="text-${badgeClass}">
                                            ${item.statut} ${item.version_plan > 1 ? `(v${item.version_plan})` : ''}
                                        </strong>
                                        <small class="text-muted">${dateFormatee}</small>
                                    </div>
                                    ${item.commentaire ? `<div class="p-2 bg-light rounded">${item.commentaire}</div>` : ''}
                                    ${item.auteur_nom ? `<small class="text-muted">Par: ${item.auteur_nom}</small>` : ''}
                                </div>
                            </div>
                        `;
                        });
                        html += '</div>';
                    } else {
                        html = '<div class="alert alert-info">Aucun historique disponible.</div>';
                    }
                    historiqueContent.innerHTML = html;
                } else {
                    historiqueContent.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement de l\'historique.</div>';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                historiqueContent.innerHTML = '<div class="alert alert-danger">Erreur de connexion.</div>';
            });
    }

    // Charger l'historique quand le modal s'ouvre
    document.addEventListener('DOMContentLoaded', function() {
        const historiqueModal = document.getElementById('historiqueModal');
        if (historiqueModal) {
            historiqueModal.addEventListener('show.bs.modal', function() {
                <?php if ($planExistant): ?>
                    chargerHistoriquePlan(<?= $planExistant['idplan_travail'] ?>);
                <?php endif; ?>
            });
        }
    });

    // Fonction pour voir l'historique du sujet
    function voirHistoriqueSujet(sujetId) {
        const modal = new bootstrap.Modal(document.getElementById('historiqueSujetModal'));
        const content = document.getElementById('historiqueSujetContent');

        // Afficher le loader
        content.innerHTML = `
        <div class="text-center p-3">
            <div class="spinner-border text-secondary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement de l'historique...</p>
        </div>
    `;

        modal.show();

        // Charger les données
        fetch(`../controller/get_sujet_reformulations.php?sujet_id=${sujetId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';

                    // Afficher les reformulations
                    if (data.reformulations && data.reformulations.length > 0) {
                        html += '<h6 class="fw-bold mb-3"><i class="fas fa-lightbulb me-2"></i>Propositions de reformulation</h6>';

                        data.reformulations.forEach(reformulation => {
                            const statusClass = {
                                'En attente': 'warning',
                                'Acceptée': 'success',
                                'Refusée': 'danger'
                            } [reformulation.statut_reformulation] || 'secondary';

                            html += `
                            <div class="card mb-3 border-${statusClass}">
                                <div class="card-header bg-${statusClass} text-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Reformulation du ${new Date(reformulation.date_proposition).toLocaleDateString('fr-FR')}</h6>
                                        <span class="badge bg-white text-${statusClass}">${reformulation.statut_reformulation}</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <strong>Intitulé proposé :</strong>
                                            <p class="mt-1">${reformulation.intitule_propose}</p>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Spécialisation :</strong>
                                            <p class="mt-1">${reformulation.specialisation_nom || 'Non spécifiée'}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Directeur proposé :</strong>
                                            <p class="mt-1">${reformulation.grade_directeur ? reformulation.grade_directeur + ' ' : ''}${reformulation.directeur_nom || 'Non spécifié'}</p>
                                        </div>
                                    </div>
                                    ${reformulation.encadreur_nom ? `
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <strong>Encadrant proposé :</strong>
                                                <p class="mt-1">${reformulation.grade_encadreur ? reformulation.grade_encadreur + ' ' : ''}${reformulation.encadreur_nom}</p>
                                            </div>
                                        </div>
                                    ` : ''}
                                    <div class="mb-3">
                                        <strong>Justification :</strong>
                                        <div class="p-3 bg-light rounded mt-1">
                                            ${reformulation.justification_etudiant.replace(/\n/g, '<br>')}
                                        </div>
                                    </div>
                                    ${reformulation.commentaire_reponse ? `
                                        <div class="alert alert-${statusClass === 'success' ? 'success' : 'danger'}">
                                            <strong>Réponse de l'administration :</strong>
                                            <p class="mb-0 mt-1">${reformulation.commentaire_reponse.replace(/\n/g, '<br>')}</p>
                                            ${reformulation.date_traitement ? `<small class="text-muted">Traitée le ${new Date(reformulation.date_traitement).toLocaleDateString('fr-FR')}</small>` : ''}
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                        });
                    }

                    // Afficher l'historique général
                    if (data.historique && data.historique.length > 0) {
                        html += '<h6 class="fw-bold mb-3 mt-4"><i class="fas fa-history me-2"></i>Historique des modifications</h6>';
                        html += '<div class="timeline">';

                        data.historique.forEach(item => {
                            const actionClass = {
                                'Création': 'primary',
                                'Modification': 'info',
                                'Validation': 'success',
                                'Reformulation demandée': 'warning',
                                'Reformulation acceptée': 'success',
                                'Reformulation refusée': 'danger'
                            } [item.action] || 'secondary';

                            html += `
                            <div class="timeline-item">
                                <div class="timeline-marker bg-${actionClass}"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <strong class="text-${actionClass}">${item.action}</strong>
                                        <small class="text-muted">${new Date(item.date_action).toLocaleDateString('fr-FR', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}</small>
                                    </div>
                                    ${item.intitule_avant && item.intitule_apres && item.intitule_avant !== item.intitule_apres ? `
                                        <div class="mb-2">
                                            <small class="text-muted">Intitulé modifié :</small>
                                            <div class="p-2 bg-light rounded">
                                                <del class="text-danger">${item.intitule_avant}</del><br>
                                                <ins class="text-success">${item.intitule_apres}</ins>
                                            </div>
                                        </div>
                                    ` : ''}
                                    ${item.commentaire ? `
                                        <div class="p-2 bg-light rounded">
                                            ${item.commentaire.replace(/\n/g, '<br>')}
                                        </div>
                                    ` : ''}
                                    ${item.auteur_nom ? `<small class="text-muted">Par: ${item.auteur_nom}</small>` : ''}
                                </div>
                            </div>
                        `;
                        });

                        html += '</div>';
                    }

                    if (!html) {
                        html = '<div class="alert alert-info">Aucun historique disponible pour ce sujet.</div>';
                    }

                    content.innerHTML = html;
                } else {
                    content.innerHTML = `<div class="alert alert-danger">Erreur lors du chargement de l'historique: ${data.error}</div>`;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                content.innerHTML = '<div class="alert alert-danger">Erreur de connexion lors du chargement de l\'historique.</div>';
            });
    }

    // Profile form handling
    document.addEventListener('DOMContentLoaded', function() {
        // Photo preview functionality
        const fileUpload = document.getElementById('fileUpload');
        const profilePreview = document.getElementById('profilePreview');
        const placeholderIcon = document.getElementById('placeholderIcon');

        if (fileUpload) {
            fileUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                    if (!allowedTypes.includes(file.type)) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Type de fichier non autorisé',
                            text: 'Veuillez sélectionner une image JPG ou PNG.',
                            confirmButtonColor: '#4361ee'
                        });
                        this.value = '';
                        return;
                    }

                    // Validate file size (2MB max)
                    if (file.size > 2 * 1024 * 1024) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Fichier trop volumineux',
                            text: 'La taille du fichier ne doit pas dépasser 2 Mo.',
                            confirmButtonColor: '#4361ee'
                        });
                        this.value = '';
                        return;
                    }

                    // Preview image
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePreview.src = e.target.result;
                        profilePreview.style.display = 'block';
                        placeholderIcon.style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Profile form submission
        const profileForm = document.getElementById('profileForm');
        if (profileForm) {
            profileForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Check if phone verification is required
                const currentPhoneField = document.getElementById('telephone');
                if (currentPhoneField.value && currentPhoneField.value !== originalPhoneValue && !phoneVerified) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Vérification requise',
                        text: 'Veuillez vérifier votre numéro de téléphone avant de continuer.',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }

                // Basic validation
                const requiredFields = ['noms', 'sexe', 'nationalite', 'adressemail', 'telephone'];
                let isValid = true;

                requiredFields.forEach(field => {
                    const element = document.getElementById(field);
                    if (!element.value.trim()) {
                        element.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        element.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Champs requis',
                        text: 'Veuillez remplir tous les champs obligatoires.',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }

                // Email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const emailField = document.getElementById('adressemail');
                if (!emailRegex.test(emailField.value)) {
                    emailField.classList.add('is-invalid');
                    Swal.fire({
                        icon: 'error',
                        title: 'Email invalide',
                        text: 'Veuillez saisir une adresse email valide.',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }

                // Phone validation (9 digits, no leading 0)
                const phoneField = document.getElementById('telephone');
                const phoneRegex = /^[1-9][0-9]{8}$/;
                if (!phoneRegex.test(phoneField.value)) {
                    phoneField.classList.add('is-invalid');
                    Swal.fire({
                        icon: 'error',
                        title: 'Numéro de téléphone invalide',
                        text: 'Le numéro doit contenir 9 chiffres et ne pas commencer par 0.',
                        confirmButtonColor: '#4361ee'
                    });
                    return;
                }

                // Show loading
                Swal.fire({
                    title: 'Enregistrement...',
                    text: 'Nous enregistrons vos informations',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // Create FormData
                const formData = new FormData(profileForm);

                // Add 243 prefix to phone
                const phoneInput = document.getElementById('telephone').value;
                formData.set('telephone', '243' + phoneInput);

                // Submit form
                fetch('../controller/update_profile_etudiant.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Profil mis à jour!',
                                text: 'Vos informations ont été enregistrées avec succès.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#4CAF50'
                            }).then(() => {
                                // Update session data if needed
                                // Reload page to reflect changes
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de la mise à jour.',
                                confirmButtonText: 'Réessayer',
                                confirmButtonColor: '#4361ee'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de connexion',
                            text: 'Vérifiez votre connexion internet et réessayez.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#4361ee'
                        });
                    });
            });
        }

        // Phone verification variables
        let otpSent = false;
        let phoneVerified = <?= !empty($studentData['telephone']) ? 'true' : 'false' ?>;
        let originalPhoneValue = '<?= htmlspecialchars(substr($studentData['telephone'] ?? '', 3)) ?>';
        let otpValue = '';
        let countdown = 0;
        let countdownTimer;

        // Initialize phone verification state
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('telephone');
            const verifyBtn = document.getElementById('sendOtpBtn');

            // Disable verify button if phone is already verified
            if (phoneVerified) {
                phoneInput.setAttribute('readonly', 'readonly');
                verifyBtn.disabled = true;
                verifyBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Vérifié';
            }

            // Enable verify button when phone changes
            phoneInput.addEventListener('input', function() {
                if (this.value !== originalPhoneValue && !phoneVerified) {
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="fas fa-shield-check me-1"></i>Vérifier';
                    document.getElementById('phoneVerificationStatus').innerHTML = '';
                    phoneVerified = false;
                    otpSent = false;
                    document.querySelector('.otp-verification-section').style.display = 'none';
                }
            });
        });

        // Send OTP button click handler
        document.getElementById('sendOtpBtn').addEventListener('click', function() {
            const phoneInput = document.getElementById('telephone').value;

            // Validate phone format (9 digits, no leading 0)
            const phoneRegex = /^[1-9][0-9]{8}$/;
            if (!phoneInput || !phoneRegex.test(phoneInput)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Numéro invalide',
                    text: 'Veuillez saisir un numéro de téléphone valide (9 chiffres et ne commençant pas par 0).',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            // Add 243 prefix for sending
            const phone = "243" + phoneInput;

            // Show loading
            Swal.fire({
                title: 'Envoi en cours...',
                text: 'Nous envoyons un code de vérification à votre numéro',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Generate OTP
            otpValue = Math.floor(100000 + Math.random() * 900000).toString();

            // Prepare message
            const msg = `Votre code de verification est : ${otpValue}. Ne partagez ce code avec personne.`;

            // Send SMS
            fetch('../controller/send_otp.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `phone=${encodeURIComponent(phone)}&message=${encodeURIComponent(msg)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show OTP verification section
                        document.querySelector('.otp-verification-section').style.display = 'block';
                        otpSent = true;

                        // Start countdown for resend
                        startOtpCountdown();

                        Swal.fire({
                            icon: 'success',
                            title: 'Code envoyé',
                            text: 'Un code de vérification a été envoyé à votre numéro de téléphone.',
                            confirmButtonColor: '#4CAF50'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Impossible d\'envoyer le code de vérification. Veuillez réessayer.',
                            confirmButtonColor: '#4361ee'
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Une erreur est survenue. Veuillez réessayer plus tard.',
                        confirmButtonColor: '#4361ee'
                    });
                });
        });

        // Verify OTP button click handler
        document.getElementById('verifyOtpBtn').addEventListener('click', function() {
            const enteredOtp = document.getElementById('otp').value;

            if (!enteredOtp) {
                document.getElementById('otp').classList.add('is-invalid');
                return;
            }

            if (enteredOtp === otpValue) {
                // OTP valid
                phoneVerified = true;

                document.getElementById('phoneVerificationStatus').innerHTML =
                    '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Numéro vérifié</span>';

                document.getElementById('telephone').setAttribute('readonly', 'readonly');
                document.getElementById('sendOtpBtn').disabled = true;
                document.getElementById('sendOtpBtn').innerHTML = '<i class="fas fa-check-circle me-1"></i>Vérifié';
                document.querySelector('.otp-verification-section').style.display = 'none';

                // Store original verified value
                originalPhoneValue = document.getElementById('telephone').value;

                Swal.fire({
                    icon: 'success',
                    title: 'Vérifié!',
                    text: 'Votre numéro de téléphone a été vérifié avec succès.',
                    confirmButtonColor: '#4CAF50'
                });
            } else {
                // OTP invalid
                Swal.fire({
                    icon: 'error',
                    title: 'Code incorrect',
                    text: 'Le code de vérification saisi est incorrect. Veuillez réessayer.',
                    confirmButtonColor: '#4361ee'
                });
            }
        });

        // Resend OTP button click handler
        document.getElementById('resendOtpBtn').addEventListener('click', function() {
            if (countdown > 0) return;

            // Reset and resend
            document.getElementById('sendOtpBtn').click();
        });

        // Function to start OTP countdown
        function startOtpCountdown() {
            countdown = 60;
            document.getElementById('resendOtpBtn').disabled = true;

            countdownTimer = setInterval(function() {
                countdown--;
                document.getElementById('otpTimer').textContent = `(${countdown}s)`;

                if (countdown <= 0) {
                    clearInterval(countdownTimer);
                    document.getElementById('resendOtpBtn').disabled = false;
                    document.getElementById('otpTimer').textContent = '';
                }
            }, 1000);
        }

        // Real-time validation
        document.querySelectorAll('#profileForm input[required], #profileForm select[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });

            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });

        // Email validation
        document.getElementById('adressemail')?.addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.classList.add('is-invalid');
            } else if (this.value) {
                this.classList.remove('is-invalid');
            }
        });

        // Phone validation
        document.getElementById('telephone')?.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');

            // Don't allow leading 0
            if (this.value.startsWith('0')) {
                this.value = this.value.substring(1);
            }

            // Limit to 9 digits
            if (this.value.length > 9) {
                this.value = this.value.substring(0, 9);
            }

            // Validate format
            const phoneRegex = /^[1-9][0-9]{8}$/;
            if (this.value && !phoneRegex.test(this.value)) {
                this.classList.add('is-invalid');
            } else if (this.value) {
                this.classList.remove('is-invalid');
            }
        });

        // OTP input validation
        document.getElementById('otp')?.addEventListener('input', function() {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');

            // Limit to 6 digits
            if (this.value.length > 6) {
                this.value = this.value.substring(0, 6);
            }

            // Remove invalid class when user types
            if (this.value) {
                this.classList.remove('is-invalid');
            }
        });
    });
</script>



<style>
    /* Styles pour la timeline de l'historique */
    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }

    .timeline-marker {
        position: absolute;
        left: -35px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #dee2e6;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid #dee2e6;
        position: relative;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: -30px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    /* Classes de couleurs pour les marqueurs */
    .timeline-marker.bg-success {
        background-color: #198754 !important;
    }

    .timeline-marker.bg-danger {
        background-color: #dc3545 !important;
    }

    .timeline-marker.bg-warning {
        background-color: #ffc107 !important;
    }

    .timeline-marker.bg-info {
        background-color: #0dcaf0 !important;
    }

    .timeline-marker.bg-secondary {
        background-color: #6c757d !important;
    }
</style>

<!-- Modal pour l'historique du sujet -->
<div class="modal fade" id="historiqueSujetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-history me-2"></i>Historique du Sujet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historiqueSujetContent">
                    <div class="text-center p-4">
                        <div class="spinner-border text-info" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-3">Chargement de l'historique...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Fermer
                </button>
            </div>
        </div>
    </div>

</div>
</div>

<!-- FlexPay Payment Modal -->
<div class="modal fade" id="flexPayModal" tabindex="-1" aria-labelledby="flexPayModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="flexPayModalLabel">
                    <i class="fas fa-mobile-alt me-2"></i>Paiement Mobile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="fp_btn_close_header"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="fp_affectation_id">
                <input type="hidden" id="fp_montant">
                <input type="hidden" id="fp_devise">

                <div class="text-center mb-4">
                    <h6 class="text-muted" id="fp_frais_nom"></h6>
                    <h3 class="fw-bold text-primary" id="fp_montant_display"></h3>
                </div>

                <!-- Zone de formulaire initiale -->
                <div id="fp_zone_formulaire">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mode de paiement</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="fp_type_paiement"
                                    id="fp_type_mobile" value="mobile_money" checked>
                                <label class="form-check-label" for="fp_type_mobile">
                                    <i class="fas fa-mobile-alt me-1"></i> Mobile Money
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="fp_type_paiement"
                                    id="fp_type_carte" value="carte_bancaire">
                                <label class="form-check-label" for="fp_type_carte">
                                    <i class="fas fa-credit-card me-1"></i> Carte Bancaire
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="fp_phone_group">
                        <label for="fp_telephone" class="form-label fw-bold">Numéro de téléphone</label>
                        <input type="tel" class="form-control" id="fp_telephone"
                            placeholder="+243..." maxlength="15">
                        <div class="form-text">Entrez le numéro associé à votre compte Mobile Money.</div>
                    </div>
                </div>

                <!-- Zone d'attente du paiement -->
                <div id="fp_zone_attente" class="text-center py-4" style="display: none;">
                    <div class="mb-3">
                        <div class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                    </div>
                    <h5 class="text-primary">En attente de confirmation...</h5>
                    <p class="text-muted small">
                        Veuillez confirmer le paiement sur votre téléphone.<br>
                        Entrez votre mot de passe M-Pesa ou Airtel Money.<br>
                        Cette fenêtre se fermera automatiquement une fois le paiement confirmé.
                    </p>
                    <div class="mb-2">
                        <span class="badge bg-primary" id="fp_compteur_attente">Vérification en cours...</span>
                    </div>
                    <div class="progress mb-3" style="height: 5px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                             role="progressbar" style="width: 100%"></div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="fp_btn_annuler_attente">
                        <i class="fas fa-times me-1"></i>Annuler et revenir
                    </button>
                </div>

                <!-- Zone de résultat -->
                <div id="fp_zone_resultat" class="alert" style="display: none;"></div>
            </div>
            <div class="modal-footer" id="fp_footer_initial">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="fp_btn_fermer_initial">
                    <i class="fas fa-times me-2"></i>Annuler
                </button>
                <button type="button" class="btn btn-primary" id="fp_btn_confirmer"
                    onclick="confirmerPaiementFlexPay()">
                    <i class="fas fa-check me-2"></i>Confirmer le paiement
                </button>
            </div>
            <div class="modal-footer" id="fp_footer_attente" style="display: none;">
                <button type="button" class="btn btn-secondary" id="fp_btn_fermer_resultat" style="display: none;">
                    <i class="fas fa-times me-2"></i>Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let fpIntervalCheckStatut = null;
let fpCurrentOrderNumber = null;
let fpCurrentReference = null;

// Open FlexPay payment modal
function ouvrirPaiementFlexPay(affectationId, fraisNom, montant, devise) {
    document.getElementById('fp_affectation_id').value = affectationId;
    document.getElementById('fp_montant_display').textContent = new Intl.NumberFormat('fr-FR').format(montant) + ' ' + devise;
    document.getElementById('fp_frais_nom').textContent = fraisNom;
    document.getElementById('fp_montant').value = montant;
    document.getElementById('fp_devise').value = devise;
    document.getElementById('fp_telephone').value = '';
    document.getElementById('fp_type_mobile').checked = true;
    document.getElementById('fp_phone_group').style.display = 'block';
    
    // Reset UI
    document.getElementById('fp_zone_formulaire').style.display = 'block';
    document.getElementById('fp_zone_attente').style.display = 'none';
    document.getElementById('fp_zone_resultat').style.display = 'none';
    document.getElementById('fp_footer_initial').style.display = 'flex';
    document.getElementById('fp_footer_attente').style.display = 'none';
    document.getElementById('fp_btn_confirmer').disabled = false;
    document.getElementById('fp_btn_fermer_resultat').style.display = 'none';
    
    // Clear any existing polling
    if (fpIntervalCheckStatut) {
        clearInterval(fpIntervalCheckStatut);
        fpIntervalCheckStatut = null;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('flexPayModal'));
    modal.show();
}

// Toggle phone field visibility based on payment type
document.querySelectorAll('input[name="fp_type_paiement"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('fp_phone_group').style.display =
            this.value === 'mobile_money' ? 'block' : 'none';
    });
});

// Submit payment
function confirmerPaiementFlexPay() {
    const affectationId = document.getElementById('fp_affectation_id').value;
    const typePaiement = document.querySelector('input[name="fp_type_paiement"]:checked').value;
    const telephone = document.getElementById('fp_telephone').value;

    if (typePaiement === 'mobile_money' && !telephone) {
        Swal.fire('Erreur', 'Veuillez entrer votre numéro de téléphone.', 'error');
        return;
    }

    const btn = document.getElementById('fp_btn_confirmer');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';

    fetch('../controller/flexpay_controller.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            affectation_id: affectationId,
            telephone: telephone,
            type_paiement: typePaiement,
            paiement_nature: window.currentPaiementType || 'frais',
            groupe_id: window.currentGroupeId || null
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Stocker les informations pour le polling
            fpCurrentOrderNumber = data.order_number;
            fpCurrentReference = data.reference;
            
            // Masquer le formulaire et afficher la zone d'attente
            document.getElementById('fp_zone_formulaire').style.display = 'none';
            document.getElementById('fp_zone_resultat').style.display = 'none';
            document.getElementById('fp_footer_initial').style.display = 'none';
            document.getElementById('fp_zone_attente').style.display = 'block';
            document.getElementById('fp_footer_attente').style.display = 'flex';
            
            // Démarrer le polling toutes les 1 seconde
            let attempts = 0;
            const maxAttempts = 60; // Maximum 60 secondes
            
            fpIntervalCheckStatut = setInterval(async function() {
                attempts++;
                
                // Mettre à jour le compteur visuel
                const compteurEl = document.getElementById('fp_compteur_attente');
                if (compteurEl) {
                    compteurEl.textContent = 'Vérification #' + attempts + ' en cours...';
                }
                
                try {
                    const response = await fetch(`../controller/flexpay_check.php?order_number=${encodeURIComponent(fpCurrentOrderNumber)}`);
                    const statutResult = await response.json();
                    
                    if (statutResult && statutResult.success) {
                        if (statutResult.statut === 'reussi') {
                            clearInterval(fpIntervalCheckStatut);
                            fpIntervalCheckStatut = null;
                            
                            document.getElementById('fp_zone_attente').style.display = 'none';
                            const resultatDiv = document.getElementById('fp_zone_resultat');
                            resultatDiv.style.display = 'block';
                            resultatDiv.className = 'alert alert-success';
                            resultatDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + 
                                '<strong>Paiement confirmé avec succès!</strong><br>' +
                                '<small>Référence: ' + fpCurrentReference + '</small>';
                            
                            document.getElementById('fp_btn_fermer_resultat').style.display = 'inline-block';
                            document.getElementById('fp_btn_fermer_resultat').onclick = function() {
                                window.location.reload();
                            };
                            document.getElementById('fp_btn_fermer_resultat').innerHTML = '<i class="fas fa-check me-2"></i>Fermer et actualiser';
                        } else if (statutResult.statut === 'echoue') {
                            clearInterval(fpIntervalCheckStatut);
                            fpIntervalCheckStatut = null;
                            
                            document.getElementById('fp_zone_attente').style.display = 'none';
                            const resultatDiv = document.getElementById('fp_zone_resultat');
                            resultatDiv.style.display = 'block';
                            const messageErreur = statutResult.message_detaille || 'Le paiement a échoué.';
                            resultatDiv.className = 'alert alert-danger';
                            resultatDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i>' + 
                                '<strong>Paiement échoué</strong><br>' +
                                '<small>' + messageErreur + '</small>';
                            
                            document.getElementById('fp_btn_fermer_resultat').style.display = 'inline-block';
                        }
                        // Si en_attente, continuer à attendre
                    }
                    
                    // Timeout après maxAttempts
                    if (attempts >= maxAttempts) {
                        clearInterval(fpIntervalCheckStatut);
                        fpIntervalCheckStatut = null;
                        
                        document.getElementById('fp_zone_attente').style.display = 'none';
                        const resultatDiv = document.getElementById('fp_zone_resultat');
                        resultatDiv.style.display = 'block';
                        resultatDiv.className = 'alert alert-warning';
                        resultatDiv.innerHTML = '<i class="fas fa-clock me-2"></i>' + 
                            '<strong>Délai d\'attente dépassé</strong><br>' +
                            '<small>Le paiement n\'a pas été confirmé. Veuillez vérifier votre téléphone.</small><br>' +
                            '<button class="btn btn-outline-primary btn-sm mt-2" id="fp_btn_verifier_timeout">' +
                            '<i class="fas fa-sync-alt me-1"></i>Vérifier le statut</button>';
                        
                        document.getElementById('fp_btn_verifier_timeout').onclick = async function() {
                            const finalResult = await fetch(`../controller/flexpay_check.php?order_number=${encodeURIComponent(fpCurrentOrderNumber)}`).then(r => r.json());
                            if (finalResult && finalResult.success && finalResult.statut === 'reussi') {
                                resultatDiv.className = 'alert alert-success';
                                resultatDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i><strong>Paiement confirmé!</strong>';
                                setTimeout(() => window.location.reload(), 2000);
                            } else if (finalResult && finalResult.success && finalResult.statut === 'echoue') {
                                resultatDiv.className = 'alert alert-danger';
                                resultatDiv.innerHTML = '<i class="fas fa-times-circle me-2"></i><strong>Paiement échoué</strong>';
                            } else {
                                // Revenir à la zone d'attente
                                document.getElementById('fp_zone_resultat').style.display = 'none';
                                document.getElementById('fp_zone_attente').style.display = 'block';
                            }
                        };
                        
                        document.getElementById('fp_btn_fermer_resultat').style.display = 'inline-block';
                    }
                } catch (error) {
                    console.error('Erreur vérification:', error);
                }
            }, 1000);
            
        } else {
            Swal.fire('Erreur', data.message || 'Une erreur est survenue.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(err => {
        Swal.fire('Erreur', 'Erreur de connexion au serveur.', 'error');
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

// Bouton annuler pendant l'attente
document.getElementById('fp_btn_annuler_attente')?.addEventListener('click', function() {
    if (fpIntervalCheckStatut) {
        clearInterval(fpIntervalCheckStatut);
        fpIntervalCheckStatut = null;
    }
    // Réinitialiser l'affichage
    document.getElementById('fp_zone_attente').style.display = 'none';
    document.getElementById('fp_zone_formulaire').style.display = 'block';
    document.getElementById('fp_footer_initial').style.display = 'flex';
    document.getElementById('fp_btn_confirmer').disabled = false;
});

// Fermer le modal pendant l'attente
document.getElementById('flexPayModal')?.addEventListener('hide.bs.modal', function() {
    if (fpIntervalCheckStatut) {
        clearInterval(fpIntervalCheckStatut);
        fpIntervalCheckStatut = null;
    }
});

// Check payment status (kept for backward compatibility)
function verifierStatutPaiement(orderNumber) {
    Swal.fire({
        title: 'Vérification en cours...',
        html: '<div class="spinner-border text-primary"></div><p class="mt-3">Vérification du statut de votre paiement...</p>',
        showConfirmButton: false,
        allowOutsideClick: false
    });

    fetch(`../controller/flexpay_check.php?order_number=${encodeURIComponent(orderNumber)}`)
    .then(res => res.json())
    .then(data => {
        if (data.statut === 'reussi') {
            Swal.fire({
                icon: 'success',
                title: 'Paiement réussi !',
                text: 'Votre paiement a été confirmé avec succès.',
                confirmButtonText: 'OK'
            }).then(() => {
                location.reload();
            });
        } else if (data.statut === 'en_attente') {
            Swal.fire({
                icon: 'info',
                title: 'Paiement en attente',
                html: '<p>Votre paiement est encore en cours de traitement.</p><p>Veuillez confirmer sur votre téléphone puis réessayer.</p>',
                confirmButtonText: 'Revérifier',
                showCancelButton: true,
                cancelButtonText: 'Fermer'
            }).then((result) => {
                if (result.isConfirmed) {
                    verifierStatutPaiement(orderNumber);
                }
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Paiement échoué',
                text: data.message || 'Le paiement n\'a pas abouti.',
                confirmButtonText: 'OK'
            });
        }
    })
    .catch(() => {
        Swal.fire('Erreur', 'Impossible de vérifier le statut.', 'error');
    });
}

// Load unpaid fees for the payment tab
function chargerFraisImpayesFlexPay() {
    const container = document.getElementById('liste-frais-flexpay');
    if (!container) return;

    fetch('../controller/flexpay_controller.php?action=get_frais_impayes')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.frais.length > 0) {
            let html = '<div class="row">';
            data.frais.forEach(f => {
                const pourcentage = Math.round((f.montant_paye / f.montant_total) * 100);
                let badgeColor, badgeIcon, badgeText;
                if (pourcentage >= 100) {
                    badgeColor = 'success';
                    badgeIcon = 'check-circle';
                    badgeText = 'Soldé';
                } else if (pourcentage > 0) {
                    badgeColor = 'warning';
                    badgeIcon = 'clock';
                    badgeText = 'En cours';
                } else {
                    badgeColor = 'danger';
                    badgeIcon = 'exclamation-circle';
                    badgeText = 'Impayé';
                }
                html += `
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title text-primary mb-0">
                                    <i class="fas fa-file-invoice-dollar me-2"></i>${f.frais_designation}
                                </h6>
                                <span class="badge bg-${badgeColor}">
                                    <i class="fas fa-${badgeIcon} me-1"></i>${badgeText}
                                </span>
                            </div>
                            <div class="d-none d-md-block mb-3">
                                <small class="text-muted">Montant total:</small>
                                <span class="fw-bold">${new Intl.NumberFormat('fr-FR').format(f.montant_total)} ${f.devise}</span>
                                <br>
                                <small class="text-muted">Déjà payé:</small>
                                <span class="text-success fw-bold">${new Intl.NumberFormat('fr-FR').format(f.montant_paye)} ${f.devise}</span>
                                <br>
                                <small class="text-muted">Reste à payer:</small>
                                <span class="text-danger fw-bold">${new Intl.NumberFormat('fr-FR').format(f.montant_total - f.montant_paye)} ${f.devise}</span>
                            </div>
                            <div class="d-md-none mb-3 text-center">
                                <span class="badge bg-${badgeColor} fs-6">
                                    <i class="fas fa-${badgeIcon} me-1"></i>${pourcentage}% payé
                                </span>
                            </div>
                            <button class="btn btn-primary btn-sm w-100"
                                onclick="ouvrirPaiementFlexPay(${f.affectation_id}, '${f.frais_designation.replace(/'/g, "\\'")}', ${f.montant_total - f.montant_paye}, '${f.devise}')">
                                <i class="fas fa-mobile-alt me-2"></i>Payer maintenant
                            </button>
                        </div>
                    </div>
                </div>`;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="alert alert-success d-flex align-items-center">
                    <i class="fas fa-check-circle me-3 fs-4"></i>
                    <div>Tous vos frais sont à jour. Aucun paiement en attente.</div>
                </div>`;
        }
    })
    .catch(() => {
        container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Erreur lors du chargement des frais.
            </div>`;
    });
}

// Load transaction history
function chargerHistoriqueTransactions() {
    const container = document.getElementById('historique-transactions-flexpay');
    if (!container) return;

    fetch('../controller/flexpay_check.php?action=historique')
    .then(res => res.json())
    .then(data => {
        if (data.success && data.transactions.length > 0) {
            let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr>';
            html += '<th>Date</th><th>Référence</th><th>Montant</th><th>Type</th><th>Statut</th><th>Actions</th>';
            html += '</tr></thead><tbody>';
            data.transactions.forEach(t => {
                const statutBadge = {
                    'en_attente': '<span class="badge bg-warning">En attente</span>',
                    'reussi': '<span class="badge bg-success">Réussi</span>',
                    'echoue': '<span class="badge bg-danger">Échoué</span>',
                    'annule': '<span class="badge bg-secondary">Annulé</span>'
                };
                let motifEchec = '';
                if (t.statut === 'echoue' && t.message_reponse) {
                    motifEchec = `<br><small class="text-danger" title="${t.message_reponse}"><i class="fas fa-info-circle me-1"></i>${truncateMessage(t.message_reponse, 40)}</small>`;
                }
                html += `<tr>
                    <td><small>${t.date_creation}</small></td>
                    <td><small class="text-muted">${t.reference}</small></td>
                    <td class="fw-bold">${new Intl.NumberFormat('fr-FR').format(t.montant)} ${t.devise}</td>
                    <td>${t.type_paiement === 'mobile_money' ? '<i class="fas fa-mobile-alt text-primary"></i> Mobile' : '<i class="fas fa-credit-card text-info"></i> Carte'}</td>
                    <td>${statutBadge[t.statut] || t.statut}${motifEchec}</td>
                    <td>${t.statut === 'en_attente' ? `<button class="btn btn-sm btn-outline-primary" onclick="verifierStatutPaiement('${t.order_number}')"><i class="fas fa-sync-alt"></i></button>` : ''}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="text-muted text-center py-3"><i class="fas fa-info-circle me-2"></i>Aucune transaction FlexPay pour le moment.</p>';
        }
    })
    .catch(() => {
        container.innerHTML = '<p class="text-danger text-center py-3">Erreur de chargement.</p>';
    });
}

function truncateMessage(msg, maxLength) {
    if (!msg) return '';
    if (msg.length <= maxLength) return msg;
    return msg.substring(0, maxLength) + '...';
}

// Auto-load when Paiements tab is shown
document.getElementById('paiements-tab')?.addEventListener('shown.bs.tab', function() {
    chargerFraisImpayesFlexPay();
    chargerHistoriqueTransactions();
});
</script>

<?php
// Vérifier si l'étudiant doit faire un choix d'orientation
$universite = new Universite();
$promotionsDisponiblesChoix = [];
$showChoixOrientation = false;

if (isset($studentId) && $studentId) {
    $aDejaChoisi = $universite->aDejaChoisiOrientation($studentId);
    
    if (!$aDejaChoisi) {
        $promotionsDisponiblesChoix = $universite->getPromotionsDisponiblesChoixOrientation($studentId);
        $showChoixOrientation = !empty($promotionsDisponiblesChoix);
    }
}
?>

<?php if ($showChoixOrientation): ?>
<div class="modal fade" id="choixOrientationModal" tabindex="-1" aria-labelledby="choixOrientationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="choixOrientationModalLabel">
                    <i class="fas fa-arrow-right-arrow-left me-2"></i>
                    Choix d'Orientation
                </h5>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Information:</strong> Vous devez choisir votre orientation pour cette année académique.
                    Une fois le choix effectué, vous serez déconnecté et devrez vous reconnecter avec votre nouvelle promotion.
                </div>
                
                <?php
                if (!empty($promotionsDisponiblesChoix)) {
                    $firstConfig = $promotionsDisponiblesChoix[0];
                    $fraisRequis = $universite->getFraisRequisChoixOrientation($firstConfig['config_id']);
                    $fraisPayes = $universite->hasStudentPaidOrientationChoiceFees($studentId, $firstConfig['config_id']);
                    $fraisStatus = $universite->getOrientationChoiceFeesStatus($studentId, $firstConfig['config_id']);
                    ?>
                    
                    <?php if (!$fraisPayes && !empty($fraisStatus)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Paiement requis:</strong> Vous devez payer les frais suivants avant de faire votre choix:
                        <ul class="mb-0 mt-2">
                            <?php foreach ($fraisStatus as $fee): ?>
                            <li>
                                <?php echo htmlspecialchars($fee['designation']); ?> 
                                (<?php echo number_format($fee['montant'], 2); ?> <?php echo htmlspecialchars($fee['devise']); ?>)
                                <?php if ($fee['statut_paiement'] === 'paye'): ?>
                                    <span class="badge bg-success ms-2">✓ Payé</span>
                                <?php elseif ($fee['statut_paiement'] === 'partiel'): ?>
                                    <span class="badge bg-warning ms-2">Paiement partiel (<?php echo number_format($fee['montantPaye'], 2); ?> / <?php echo number_format($fee['montant'], 2); ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger ms-2">À payer</span>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <small class="d-block mt-2">Accédez à la section "Paiements" pour effectuer vos paiements.</small>
                    </div>
                    <?php endif; ?>
                    
                    <form id="choixOrientationForm">
                        <input type="hidden" id="choixOrientationConfigId" value="<?php echo $firstConfig['config_id']; ?>">
                        <div class="mb-3">
                            <label for="choixOrientationSelect" class="form-label fw-bold">
                                <i class="fas fa-school me-2"></i>Sélectionnez votre orientation <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="choixOrientationSelect" required>
                                <option value="">Choisir une orientation...</option>
                                <?php foreach ($promotionsDisponiblesChoix as $promo): ?>
                                <option value="<?php echo $promo['idpromotion']; ?>">
                                    <?php echo htmlspecialchars($promo['designationPromotion']); ?> - <?php echo htmlspecialchars($promo['designationOrientation'] ?? 'Sans orientation'); ?> (<?php echo htmlspecialchars($promo['cycle']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-secondary">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Attention:</strong> Après validation, votre inscription sera migrée vers la nouvelle promotion. Vous serez déconnecté et devrez vous reconnecter.
                        </div>
                    </form>
                    <?php } ?>
            </div>
            <div class="modal-footer">
                <?php if ($fraisPayes || empty($fraisRequis)): ?>
                <button type="button" class="btn btn-primary" id="confirmChoixOrientationBtn" onclick="submitChoixOrientation()">
                    <i class="fas fa-check me-2"></i>Confirmer mon choix
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-secondary" onclick="window.location.href='?view=portail/frais_academiques'">
                    <i class="fas fa-credit-card me-2"></i>Aller aux paiements
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let choixOrientationData = <?php echo json_encode($promotionsDisponiblesChoix); ?>;

document.addEventListener('DOMContentLoaded', function() {
    <?php if ($showChoixOrientation): ?>
    const choixModal = new bootstrap.Modal(document.getElementById('choixOrientationModal'));
    choixModal.show();
    document.getElementById('choixOrientationModal').addEventListener('hidden.bs.modal', function() {});
    <?php endif; ?>
});

function submitChoixOrientation() {
    const promotionCibleId = document.getElementById('choixOrientationSelect').value;
    const configId = document.getElementById('choixOrientationConfigId').value;
    
    if (!promotionCibleId) {
        Swal.fire({icon: 'error', title: 'Erreur', text: 'Veuillez sélectionner une orientation.'});
        return;
    }
    
    const formData = new FormData();
    formData.append('promotion_cible_id', promotionCibleId);
    formData.append('config_id', configId);
    
    fetch('../controller/choix_orientation.php', {method: 'POST', body: formData})
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({icon: 'success', title: 'Succès', text: data.message, allowOutsideClick: false, allowEscapeKey: false, showConfirmButton: false, timer: 2000, timerProgressBar: true})
            .then(() => { window.location.href = '?view=portail/login'; });
        } else {
            Swal.fire({icon: 'error', title: 'Erreur', text: data.message});
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        Swal.fire({icon: 'error', title: 'Erreur', text: 'Une erreur est survenue lors de la soumission.'});
    });
}
</script>
<?php endif; ?>

<?php include "includes/bottom_nav.php"; ?>

<?php include __DIR__ . "/includes/main_scripts.php"; ?>

<?php
require_once "footer_student.php";
?>