<?php
// Vérification de l'accès
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once dirname(__DIR__, 2) . '/models/PlanTravail.php';
require_once dirname(__DIR__, 2) . '/models/Agent.php';

$planModel = new PlanTravail();
$agentModel = new Agent();

$directeurId = $_SESSION['user_id'];
$directeur = $agentModel->getAgentById($directeurId);

// Récupérer les plans en attente
$plansEnAttente = $planModel->getPlansEnAttenteParDirecteur($directeurId);

// Récupérer tous les plans
$tousLesPlans = $planModel->getPlansParDirecteur($directeurId);

// Récupérer les deadlines proches
$deadlinesProches = $planModel->getDeadlinesProchaines($directeurId, 14);

// Statistiques
$stats = $planModel->getStatistiquesPlans($directeurId);

// Messages d'action
$message = '';
$messageType = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message'], $_SESSION['message_type']);
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="text-primary fw-bold mb-1">
                        <i class="fas fa-clipboard-list me-2"></i>Gestion des Plans de Travail
                    </h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="?view=dashboard">Tableau de bord</a></li>
                            <li class="breadcrumb-item active">Plans de Travail</li>
                        </ol>
                    </nav>
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

            <!-- Statistiques -->
            <div class="row mb-4">
                <?php 
                $totalPlans = 0;
                $plansEnAttenteStat = 0;
                $plansValides = 0;
                $plansRejetes = 0;
                
                foreach ($stats as $stat) {
                    $totalPlans += $stat['nombre'];
                    switch ($stat['statut_validation']) {
                        case 'En attente':
                            $plansEnAttenteStat = $stat['nombre'];
                            break;
                        case 'Validé':
                            $plansValides = $stat['nombre'];
                            break;
                        case 'Rejeté':
                            $plansRejetes = $stat['nombre'];
                            break;
                    }
                }
                ?>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clipboard-list text-primary fs-2 mb-2"></i>
                            <h5 class="card-title mb-1"><?= $totalPlans ?></h5>
                            <p class="card-text text-muted">Total Plans</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clock text-warning fs-2 mb-2"></i>
                            <h5 class="card-title mb-1"><?= $plansEnAttenteStat ?></h5>
                            <p class="card-text text-muted">En attente</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success fs-2 mb-2"></i>
                            <h5 class="card-title mb-1"><?= $plansValides ?></h5>
                            <p class="card-text text-muted">Validés</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-check text-info fs-2 mb-2"></i>
                            <h5 class="card-title mb-1"><?= count($deadlinesProches) ?></h5>
                            <p class="card-text text-muted">Deadlines proches</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="planTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="attente-tab" data-bs-toggle="tab" data-bs-target="#attente" type="button">
                        <i class="fas fa-clock me-1"></i>En attente (<?= count($plansEnAttente) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tous-tab" data-bs-toggle="tab" data-bs-target="#tous" type="button">
                        <i class="fas fa-list me-1"></i>Tous les plans
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="deadlines-tab" data-bs-toggle="tab" data-bs-target="#deadlines" type="button">
                        <i class="fas fa-calendar-alt me-1"></i>Deadlines
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="planTabContent">
                <!-- Plans en attente -->
                <div class="tab-pane fade show active" id="attente" role="tabpanel">
                    <?php if (empty($plansEnAttente)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucun plan en attente de validation.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($plansEnAttente as $plan): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-header bg-warning text-dark">
                                            <h6 class="mb-0">
                                                <i class="fas fa-user-graduate me-2"></i>
                                                <?= htmlspecialchars($plan['etudiant_nom']) ?> - <?= htmlspecialchars($plan['matricule']) ?>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title text-primary"><?= htmlspecialchars($plan['titre_plan']) ?></h5>
                                            <p class="text-muted mb-2">
                                                <i class="fas fa-book me-1"></i>
                                                <?= htmlspecialchars($plan['sujet_intitule']) ?>
                                            </p>
                                            <p class="text-muted mb-3">
                                                <i class="fas fa-calendar me-1"></i>
                                                Soumis le <?= date('d/m/Y', strtotime($plan['date_soumission'])) ?>
                                            </p>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-primary" 
                                                        onclick="examinerPlan(<?= $plan['idplan_travail'] ?>)">
                                                    <i class="fas fa-eye me-1"></i>Examiner
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tous les plans -->
                <div class="tab-pane fade" id="tous" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Titre du plan</th>
                                    <th>Statut</th>
                                    <th>Progression</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tousLesPlans as $plan): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($plan['etudiant_nom']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($plan['matricule']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($plan['titre_plan']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($plan['sujet_intitule']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $plan['statut_validation'] === 'Validé' ? 'success' : 
                                                ($plan['statut_validation'] === 'Rejeté' ? 'danger' : 'warning') ?>">
                                                <?= htmlspecialchars($plan['statut_validation']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($plan['nb_chapitres'] > 0): ?>
                                                <div class="progress" style="height: 20px;">
                                                    <?php $pourcentage = ($plan['nb_chapitres_termines'] / $plan['nb_chapitres']) * 100; ?>
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?= $pourcentage ?>%">
                                                        <?= round($pourcentage) ?>%
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($plan['date_soumission'])) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="voirPlan(<?= $plan['idplan_travail'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Deadlines -->
                <div class="tab-pane fade" id="deadlines" role="tabpanel">
                    <?php if (empty($deadlinesProches)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Aucune deadline dans les 14 prochains jours.
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($deadlinesProches as $deadline): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <div>
                                            <h6 class="mb-1">
                                                <?= htmlspecialchars($deadline['etudiant_nom']) ?> - 
                                                Chapitre <?= $deadline['numero_chapitre'] ?>: <?= htmlspecialchars($deadline['titre_chapitre']) ?>
                                            </h6>
                                            <p class="mb-1"><?= htmlspecialchars($deadline['titre_plan']) ?></p>
                                            <small class="text-muted"><?= htmlspecialchars($deadline['description_deadline'] ?? '') ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?= 
                                                $deadline['jours_restants'] <= 3 ? 'danger' : 
                                                ($deadline['jours_restants'] <= 7 ? 'warning' : 'primary') ?> p-2">
                                                <?= $deadline['jours_restants'] ?> jours
                                            </span>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <?= date('d/m/Y', strtotime($deadline['deadline'])) ?>
                                                </small>
                                            </div>
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
</div>

<!-- Modal d'examen du plan -->
<div class="modal fade" id="examinerPlanModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i>Examiner le Plan de Travail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="planContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <form action="../controller/plan_travail_controller.php" method="POST" id="validationForm">
                    <input type="hidden" name="action" value="valider_plan">
                    <input type="hidden" name="plan_id" id="plan_id_validation">
                    
                    <div class="row w-100">
                        <div class="col-md-8">
                            <textarea class="form-control" name="commentaire" rows="2" 
                                      placeholder="Commentaire (obligatoire en cas de rejet)"></textarea>
                        </div>
                        <div class="col-md-4 text-end">
                            <button type="submit" name="statut" value="Rejeté" class="btn btn-danger">
                                <i class="fas fa-times me-1"></i>Rejeter
                            </button>
                            <button type="submit" name="statut" value="Validé" class="btn btn-success">
                                <i class="fas fa-check me-1"></i>Valider
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function examinerPlan(planId) {
    // Charger le contenu du plan
    fetch(`../controller/get_plan_details.php?id=${planId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('planContent').innerHTML = html;
            document.getElementById('plan_id_validation').value = planId;
            new bootstrap.Modal(document.getElementById('examinerPlanModal')).show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement du plan');
        });
}

function voirPlan(planId) {
    window.location.href = `?view=plan_detail&id=${planId}`;
}

// Validation du formulaire
document.getElementById('validationForm').addEventListener('submit', function(e) {
    const statut = e.submitter.value;
    const commentaire = this.commentaire.value.trim();
    
    if (statut === 'Rejeté' && !commentaire) {
        e.preventDefault();
        alert('Un commentaire est obligatoire lors du rejet d\'un plan');
        this.commentaire.focus();
    }
});
</script>

<style>
.progress {
    background-color: #e9ecef;
}

.list-group-item {
    border-left: 4px solid transparent;
    transition: all 0.3s ease;
}

.list-group-item:hover {
    border-left-color: var(--bs-primary);
    background-color: #f8f9fa;
}

.badge {
    font-size: 0.875em;
}
</style>