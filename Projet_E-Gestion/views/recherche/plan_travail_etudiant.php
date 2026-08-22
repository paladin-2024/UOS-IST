<?php
// Vérification de l'accès
if (!isset($_SESSION['student_id'])) {
    header('Location: index.php');
    exit();
}

$etudiantModel = new Etudiant();
$sujetModel = new Sujet();
$planModel = new PlanTravail();

$etudiant_id = $_SESSION['student_id'];
$etudiant = $etudiantModel->getEtudiantById($etudiant_id);

// Récupérer le sujet validé de l'étudiant
$sujetAssigne = $sujetModel->getSujetByEtudiant($etudiant_id);

// Vérifier si l'étudiant a un sujet validé
if (!$sujetAssigne || $sujetAssigne['statut_validation'] !== 'Validé') {
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Vous devez avoir un sujet validé par la commission avant de pouvoir soumettre un plan de travail.</div>';
    return;
}

// Vérifier si un plan existe déjà
$planExistant = $planModel->getPlanBySujet($sujetAssigne['idsujets']);

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
                        <i class="fas fa-clipboard-list me-2"></i>Plan de Travail
                    </h4>
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
                                    <span class="fw-semibold"><?= htmlspecialchars($sujetAssigne['directeur_nom'] ?? 'Non assigné') ?></span>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">Encadreur:</small><br>
                                    <span class="fw-semibold"><?= htmlspecialchars($sujetAssigne['encadreur_nom'] ?? 'Aucun') ?></span>
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
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>Mon Plan de Travail
                            <span class="badge bg-<?= $planExistant['statut_validation'] === 'Validé' ? 'success' : 
                                ($planExistant['statut_validation'] === 'Rejeté' ? 'danger' : 'warning') ?> ms-2">
                                <?= htmlspecialchars($planExistant['statut_validation']) ?>
                            </span>
                        </h5>
                        <?php if ($planExistant['statut_validation'] !== 'Validé'): ?>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPlanModal">
                                <i class="fas fa-edit me-1"></i>Modifier
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Titre du plan:</strong><br>
                                <?= htmlspecialchars($planExistant['titre_plan']) ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Version:</strong><br>
                                v<?= $planExistant['version'] ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Date de soumission:</strong><br>
                                <?= date('d/m/Y H:i', strtotime($planExistant['date_soumission'])) ?>
                            </div>
                        </div>

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
                                                        $chapitre['statut'] === 'Terminé' ? 'success' : 
                                                        ($chapitre['statut'] === 'En cours' ? 'primary' : 
                                                        ($chapitre['statut'] === 'En révision' ? 'warning' : 'secondary')) ?>">
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
                                                        <button type="button" class="btn btn-sm btn-outline-success ms-1" 
                                                                onclick="soumettreCharpitre(<?= $chapitre['idchapitre_plan'] ?>)">
                                                            <i class="fas fa-upload me-1"></i>Soumettre
                                                        </button>
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

                            <div class="row mb-4">
                                <div class="col-12">
                                    <label for="titre_plan" class="form-label required">
                                        <i class="fas fa-heading me-1"></i>Titre du Plan de Travail
                                    </label>
                                    <input type="text" class="form-control" id="titre_plan" name="titre_plan" 
                                           placeholder="Ex: Plan de développement d'une application mobile..." required>
                                    <div class="form-text">Donnez un titre descriptif à votre plan de travail</div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="introduction" class="form-label">
                                        <i class="fas fa-file-text me-1"></i>Introduction
                                    </label>
                                    <textarea class="form-control" id="introduction" name="introduction" rows="5" 
                                              placeholder="Contexte général, justification du sujet..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="problematique" class="form-label">
                                        <i class="fas fa-question-circle me-1"></i>Problématique
                                    </label>
                                    <textarea class="form-control" id="problematique" name="problematique" rows="5" 
                                              placeholder="Question de recherche principale..."></textarea>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="objectifs" class="form-label">
                                        <i class="fas fa-bullseye me-1"></i>Objectifs
                                    </label>
                                    <textarea class="form-control" id="objectifs" name="objectifs" rows="5" 
                                              placeholder="Objectif général et objectifs spécifiques..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="methodologie" class="form-label">
                                        <i class="fas fa-cogs me-1"></i>Méthodologie
                                    </label>
                                    <textarea class="form-control" id="methodologie" name="methodologie" rows="5" 
                                              placeholder="Approche méthodologique, outils, techniques..."></textarea>
                                </div>
                            </div>

                            <!-- Chapitres dynamiques -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <label class="form-label required">
                                        <i class="fas fa-list-ol me-1"></i>Structure du Plan (Chapitres)
                                    </label>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="ajouterChapitre()">
                                        <i class="fas fa-plus me-1"></i>Ajouter un chapitre
                                    </button>
                                </div>
                                
                                <div id="chapitres-container">
                                    <!-- Les chapitres seront ajoutés ici dynamiquement -->
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" onclick="history.back()">
                                    <i class="fas fa-arrow-left me-1"></i>Retour
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-1"></i>Soumettre le Plan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour modification du plan -->
<?php if ($planExistant && $planExistant['statut_validation'] !== 'Validé'): ?>
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier le Plan de Travail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="../controller/plan_travail_controller.php" method="POST">
                <input type="hidden" name="action" value="modifier_plan">
                <input type="hidden" name="plan_id" value="<?= $planExistant['idplan_travail'] ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_titre_plan" class="form-label required">Titre du Plan</label>
                        <input type="text" class="form-control" id="edit_titre_plan" name="titre_plan" 
                               value="<?= htmlspecialchars($planExistant['titre_plan']) ?>" required>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_introduction" class="form-label">Introduction</label>
                            <textarea class="form-control" id="edit_introduction" name="introduction" rows="4"><?= htmlspecialchars($planExistant['introduction'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_problematique" class="form-label">Problématique</label>
                            <textarea class="form-control" id="edit_problematique" name="problematique" rows="4"><?= htmlspecialchars($planExistant['problematique'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_objectifs" class="form-label">Objectifs</label>
                            <textarea class="form-control" id="edit_objectifs" name="objectifs" rows="4"><?= htmlspecialchars($planExistant['objectifs'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_methodologie" class="form-label">Méthodologie</label>
                            <textarea class="form-control" id="edit_methodologie" name="methodologie" rows="4"><?= htmlspecialchars($planExistant['methodologie'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let chapitreCounter = 1;

function ajouterChapitre() {
    const container = document.getElementById('chapitres-container');
    const chapitreDiv = document.createElement('div');
    chapitreDiv.className = 'chapitre-item border rounded p-3 mb-3';
    chapitreDiv.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0 text-primary">Chapitre ${chapitreCounter}</h6>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="supprimerChapitre(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="row">
            <div class="col-md-8">
                <label class="form-label required">Titre du chapitre</label>
                <input type="text" class="form-control" name="chapitres[${chapitreCounter}][titre]" 
                       placeholder="Ex: État de l'art, Analyse des besoins..." required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Numéro</label>
                <input type="number" class="form-control" name="chapitres[${chapitreCounter}][numero]" 
                       value="${chapitreCounter}" min="1" required>
            </div>
        </div>
        <div class="mt-3">
            <label class="form-label">Description du chapitre</label>
            <textarea class="form-control" name="chapitres[${chapitreCounter}][description]" rows="3" 
                      placeholder="Description des objectifs et contenu de ce chapitre..."></textarea>
        </div>
    `;
    container.appendChild(chapitreDiv);
    chapitreCounter++;
}

function supprimerChapitre(button) {
    button.closest('.chapitre-item').remove();
}

function voirChapitre(chapitreId) {
    // Ouvrir modal ou rediriger vers la vue détaillée du chapitre
    window.location.href = `?view=chapitre_detail&id=${chapitreId}`;
}

function soumettreCharpitre(chapitreId) {
    // Ouvrir modal de soumission de chapitre
    // TODO: Implémenter modal de soumission
}

// Ajouter automatiquement le premier chapitre
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$planExistant): ?>
        ajouterChapitre();
    <?php endif; ?>
});
</script>

<style>
.chapitre-item {
    background-color: #f8f9fa;
    transition: all 0.3s ease;
}

.chapitre-item:hover {
    background-color: #e9ecef;
}

.required::after {
    content: ' *';
    color: red;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.badge {
    font-size: 0.75em;
}
</style>
