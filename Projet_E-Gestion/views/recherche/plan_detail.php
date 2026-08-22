<?php
// Vérification de l'accès
if (!isset($_SESSION['user_id']) && !isset($_SESSION['student_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'models/PlanTravail.php';
require_once 'models/Agent.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$planModel = new PlanTravail();
$planId = (int)$_GET['id'];

// Récupérer le plan avec toutes les informations
$plan = $planModel->getPlanById($planId);
if (!$plan) {
    header('Location: index.php');
    exit();
}

// Vérifier les autorisations
$isEtudiant = isset($_SESSION['student_id']);
$isDirecteur = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $plan['idDirecteur'];
$isEncadreur = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $plan['idEncadreur'];

if ($isEtudiant && $_SESSION['student_id'] != $plan['etudiant_idetudiant']) {
    header('Location: index.php');
    exit();
}

if (!$isEtudiant && !$isDirecteur && !$isEncadreur) {
    header('Location: index.php');
    exit();
}

// Récupérer les chapitres et l'historique
$chapitres = $planModel->getChapitresByPlan($planId);
$historique = $planModel->getHistoriquePlan($planId);

// Messages d'action
$message = '';
$messageType = '';
if (isset($_SESSION['plan_message'])) {
    $message = $_SESSION['plan_message'];
    $messageType = $_SESSION['plan_message_type'] ?? 'info';
    unset($_SESSION['plan_message'], $_SESSION['plan_message_type']);
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="text-primary fw-bold mb-1">
                        <i class="fas fa-file-alt me-2"></i>Détail du Plan de Travail
                    </h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="?view=<?= $isEtudiant ? 'student' : 'plan_directeur' ?>">
                                    <?= $isEtudiant ? 'Portail' : 'Plans' ?>
                                </a>
                            </li>
                            <li class="breadcrumb-item active">Détail du Plan</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <?php if ($isDirecteur && $plan['statut_validation'] === 'En attente'): ?>
                        <button type="button" class="btn btn-success me-2" 
                                onclick="validerPlan(<?= $planId ?>, 'Validé')">
                            <i class="fas fa-check me-1"></i>Valider
                        </button>
                        <button type="button" class="btn btn-danger" 
                                onclick="validerPlan(<?= $planId ?>, 'Rejeté')">
                            <i class="fas fa-times me-1"></i>Rejeter
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Informations du plan -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Informations Générales
                        </h5>
                        <span class="badge bg-<?= 
                            $plan['statut_validation'] === 'Validé' ? 'success' : 
                            ($plan['statut_validation'] === 'Rejeté' ? 'danger' : 'warning') ?> fs-6">
                            <?= htmlspecialchars($plan['statut_validation']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5 class="text-primary mb-3"><?= htmlspecialchars($plan['titre_plan']) ?></h5>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted">Sujet de Recherche:</h6>
                                    <p><?= htmlspecialchars($plan['sujet_intitule']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-muted">Spécialisation:</h6>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($plan['specialisation'] ?? 'Non définie') ?></span>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <h6 class="fw-bold text-muted">Étudiant:</h6>
                                    <p class="mb-0"><?= htmlspecialchars($plan['etudiant_nom']) ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($plan['matricule']) ?></small>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="fw-bold text-muted">Directeur:</h6>
                                    <p class="mb-0"><?= htmlspecialchars($plan['directeur_nom']) ?></p>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="fw-bold text-muted">Encadreur:</h6>
                                    <p class="mb-0"><?= htmlspecialchars($plan['encadreur_nom'] ?? 'Aucun') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-3 rounded">
                                <h6 class="fw-bold mb-3">Détails du Plan</h6>
                                <div class="mb-2">
                                    <small class="text-muted">Version:</small>
                                    <span class="float-end fw-bold">v<?= $plan['version'] ?></span>
                                </div>
                                <div class="mb-2">
                                    <small class="text-muted">Date de soumission:</small>
                                    <span class="float-end fw-bold"><?= date('d/m/Y', strtotime($plan['date_soumission'])) ?></span>
                                </div>
                                <?php if ($plan['date_validation']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Date de validation:</small>
                                        <span class="float-end fw-bold"><?= date('d/m/Y', strtotime($plan['date_validation'])) ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-2">
                                    <small class="text-muted">Nombre de chapitres:</small>
                                    <span class="float-end fw-bold"><?= count($chapitres) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Commentaire du directeur -->
                    <?php if ($plan['commentaire_directeur']): ?>
                        <div class="alert alert-<?= $plan['statut_validation'] === 'Validé' ? 'success' : 'warning' ?> mt-3">
                            <h6><i class="fas fa-comment me-2"></i>Commentaire du directeur:</h6>
                            <?= nl2br(htmlspecialchars($plan['commentaire_directeur'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contenu du plan -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-file-text me-2"></i>Introduction</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($plan['introduction']): ?>
                                <?= nl2br(htmlspecialchars($plan['introduction'])) ?>
                            <?php else: ?>
                                <p class="text-muted fst-italic">Aucune introduction fournie</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Problématique</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($plan['problematique']): ?>
                                <?= nl2br(htmlspecialchars($plan['problematique'])) ?>
                            <?php else: ?>
                                <p class="text-muted fst-italic">Aucune problématique fournie</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-bullseye me-2"></i>Objectifs</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($plan['objectifs']): ?>
                                <?= nl2br(htmlspecialchars($plan['objectifs'])) ?>
                            <?php else: ?>
                                <p class="text-muted fst-italic">Aucun objectif fourni</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Méthodologie</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($plan['methodologie']): ?>
                                <?= nl2br(htmlspecialchars($plan['methodologie'])) ?>
                            <?php else: ?>
                                <p class="text-muted fst-italic">Aucune méthodologie fournie</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Structure des chapitres -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list-ol me-2"></i>Structure du Plan (<?= count($chapitres) ?> chapitres)
                    </h5>
                    <?php if ($isDirecteur && $plan['statut_validation'] === 'Validé'): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                data-bs-toggle="modal" data-bs-target="#assignerDeadlineModal">
                            <i class="fas fa-calendar-plus me-1"></i>Assigner Deadline
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($chapitres)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Aucun chapitre défini dans ce plan.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="10%">N°</th>
                                        <th width="30%">Titre</th>
                                        <th width="25%">Description</th>
                                        <th width="15%">Deadline</th>
                                        <th width="10%">Statut</th>
                                        <th width="10%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chapitres as $chapitre): ?>
                                        <tr>
                                            <td class="text-center fw-bold fs-5">
                                                <?= $chapitre['numero_chapitre'] ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($chapitre['titre_chapitre']) ?></strong>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars(substr($chapitre['description'] ?? '', 0, 100)) ?><?= strlen($chapitre['description'] ?? '') > 100 ? '...' : '' ?></small>
                                            </td>
                                            <td>
                                                <?php if ($chapitre['deadline']): ?>
                                                    <?php
                                                    $deadlineClass = strtotime($chapitre['deadline']) < time() ? 'danger' : 'primary';
                                                    $jours = ceil((strtotime($chapitre['deadline']) - time()) / (24 * 3600));
                                                    ?>
                                                    <span class="badge bg-<?= $deadlineClass ?>">
                                                        <?= date('d/m/Y', strtotime($chapitre['deadline'])) ?>
                                                    </span>
                                                    <?php if ($jours >= 0): ?>
                                                        <small class="d-block text-<?= $jours <= 3 ? 'danger' : 'muted' ?>">
                                                            <?= $jours ?> jour(s) restant(s)
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="d-block text-danger">En retard</small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Non définie</span>
                                                    <?php if ($isDirecteur && $plan['statut_validation'] === 'Validé'): ?>
                                                        <br><button type="button" class="btn btn-sm btn-outline-primary mt-1" 
                                                                onclick="assignerDeadlineSpecifique(<?= $chapitre['idchapitre_plan'] ?>)">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $chapitre['statut'] === 'Terminé' ? 'success' : 
                                                    ($chapitre['statut'] === 'En cours' ? 'primary' : 
                                                    ($chapitre['statut'] === 'En révision' ? 'warning' : 'secondary')) ?>">
                                                    <?= htmlspecialchars($chapitre['statut']) ?>
                                                </span>
                                                <?php if ($chapitre['pourcentage_avancement'] > 0): ?>
                                                    <div class="progress mt-1" style="height: 5px;">
                                                        <div class="progress-bar" style="width: <?= $chapitre['pourcentage_avancement'] ?>%"></div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="voirChapitre(<?= $chapitre['idchapitre_plan'] ?>)" 
                                                            title="Voir détails">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($isEtudiant && $plan['statut_validation'] === 'Validé'): ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                onclick="soumettreCharpitre(<?= $chapitre['idchapitre_plan'] ?>)" 
                                                                title="Soumettre travail">
                                                            <i class="fas fa-upload"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historique -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Historique des Actions
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($historique)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Aucun historique disponible.
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($historique as $action): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?= 
                                        $action['statut'] === 'Validé' ? 'success' : 
                                        ($action['statut'] === 'Rejeté' ? 'danger' : 'primary') ?>"></div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0 text-<?= 
                                                $action['statut'] === 'Validé' ? 'success' : 
                                                ($action['statut'] === 'Rejeté' ? 'danger' : 'primary') ?>">
                                                <?= htmlspecialchars($action['statut']) ?>
                                                <?php if ($action['version_plan'] > 1): ?>
                                                    <span class="badge bg-secondary ms-2">v<?= $action['version_plan'] ?></span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?= date('d/m/Y H:i', strtotime($action['date_action'])) ?>
                                            </small>
                                        </div>
                                        <?php if ($action['commentaire']): ?>
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($action['commentaire'])) ?></p>
                                        <?php endif; ?>
                                        <?php if ($action['auteur_nom']): ?>
                                            <small class="text-muted">par <?= htmlspecialchars($action['auteur_nom']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de validation (pour directeurs) -->
<?php if ($isDirecteur && $plan['statut_validation'] === 'En attente'): ?>
<div class="modal fade" id="validationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="validationModalTitle">Validation du Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="controller/plan_travail_controller.php" method="POST">
                <input type="hidden" name="action" value="valider_plan">
                <input type="hidden" name="plan_id" value="<?= $planId ?>">
                <input type="hidden" name="statut" id="validation_statut">
                
                <div class="modal-body">
                    <div class="alert" id="validation_info"></div>
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="4"></textarea>
                        <div class="form-text" id="commentaire_help"></div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn" id="validation_submit_btn">Confirmer</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal d'assignation de deadline -->
<?php if ($isDirecteur && $plan['statut_validation'] === 'Validé'): ?>
<div class="modal fade" id="assignerDeadlineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assigner une Deadline</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="controller/plan_travail_controller.php" method="POST">
                <input type="hidden" name="action" value="assigner_deadline">
                <input type="hidden" name="chapitre_id" id="deadline_chapitre_id">
                <input type="hidden" name="redirect" value="plan_detail&id=<?= $planId ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="deadline_chapitre" class="form-label">Chapitre</label>
                        <select class="form-select" id="deadline_chapitre" name="chapitre_id" required>
                            <option value="">Sélectionner un chapitre</option>
                            <?php foreach ($chapitres as $chapitre): ?>
                                <option value="<?= $chapitre['idchapitre_plan'] ?>">
                                    Chapitre <?= $chapitre['numero_chapitre'] ?> - <?= htmlspecialchars($chapitre['titre_chapitre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deadline_date" class="form-label required">Date limite</label>
                        <input type="date" class="form-control" id="deadline_date" name="deadline" required 
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="deadline_priorite" class="form-label">Priorité</label>
                        <select class="form-select" id="deadline_priorite" name="priorite">
                            <option value="Faible">Faible</option>
                            <option value="Moyenne" selected>Moyenne</option>
                            <option value="Haute">Haute</option>
                            <option value="Critique">Critique</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deadline_description" class="form-label">Description</label>
                        <textarea class="form-control" id="deadline_description" name="description" rows="3" 
                                  placeholder="Instructions ou précisions sur cette deadline..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-1"></i>Assigner
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
<?php if ($isDirecteur && $plan['statut_validation'] === 'En attente'): ?>
function validerPlan(planId, statut) {
    document.getElementById('validation_statut').value = statut;
    
    const info = document.getElementById('validation_info');
    const submitBtn = document.getElementById('validation_submit_btn');
    const commentaireHelp = document.getElementById('commentaire_help');
    const commentaireField = document.getElementById('commentaire');
    
    if (statut === 'Validé') {
        info.innerHTML = '<i class="fas fa-check-circle me-2"></i>Validation du plan de travail';
        info.className = 'alert alert-success';
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="fas fa-check me-1"></i>Valider';
        commentaireHelp.textContent = 'Commentaire optionnel';
        commentaireField.required = false;
    } else {
        info.innerHTML = '<i class="fas fa-times-circle me-2"></i>Rejet du plan de travail';
        info.className = 'alert alert-danger';
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = '<i class="fas fa-times me-1"></i>Rejeter';
        commentaireHelp.textContent = 'Commentaire obligatoire expliquant le rejet';
        commentaireField.required = true;
    }
    
    new bootstrap.Modal(document.getElementById('validationModal')).show();
}
<?php endif; ?>

function voirChapitre(chapitreId) {
    // TODO: Implémenter vue détaillée du chapitre
    console.log('Voir chapitre:', chapitreId);
}

function soumettreCharpitre(chapitreId) {
    // TODO: Implémenter modal de soumission
    console.log('Soumettre chapitre:', chapitreId);
}

function assignerDeadlineSpecifique(chapitreId) {
    document.getElementById('deadline_chapitre_id').value = chapitreId;
    document.getElementById('deadline_chapitre').value = chapitreId;
    new bootstrap.Modal(document.getElementById('assignerDeadlineModal')).show();
}
</script>

<style>
.timeline {
    position: relative;
    padding-left: 3rem;
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: -1.5rem;
    top: 0.5rem;
    bottom: -2rem;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item:last-child:before {
    bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -2rem;
    top: 0.25rem;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid white;
    box-shadow: 0 0 0 1px #e9ecef;
}

.timeline-content {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    border-left: 3px solid #007bff;
}

.card h6 {
    font-weight: 600;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

.progress {
    background-color: #e9ecef;
}

.badge {
    font-size: 0.75em;
}

.required::after {
    content: ' *';
    color: red;
}
</style>
