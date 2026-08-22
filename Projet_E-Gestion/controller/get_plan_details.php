<?php
session_start();
require_once '../config/Connexion.php';
require_once '../models/PlanTravail.php';

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Accès non autorisé";
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "ID du plan manquant";
    exit();
}

$planId = (int)$_GET['id'];
$planModel = new PlanTravail();

try {
    // Récupérer les détails du plan
    $plan = $planModel->getPlanById($planId);
    
    if (!$plan) {
        http_response_code(404);
        echo "Plan non trouvé";
        exit();
    }
    
    // Vérifier que l'utilisateur est le directeur du sujet
    if ($plan['idDirecteur'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo "Vous n'êtes pas autorisé à voir ce plan";
        exit();
    }
    
    // Récupérer les chapitres
    $chapitres = $planModel->getChapitresByPlan($planId);
    
    // Récupérer l'historique
    $historique = $planModel->getHistoriquePlan($planId);
    
    ?>
    <div class="row">
        <div class="col-md-8">
            <h5 class="text-primary mb-3"><?= htmlspecialchars($plan['titre_plan']) ?></h5>
            
            <div class="mb-4">
                <h6 class="fw-bold">Informations générales</h6>
                <table class="table table-sm">
                    <tr>
                        <td width="30%"><strong>Étudiant:</strong></td>
                        <td><?= htmlspecialchars($plan['etudiant_nom']) ?> (<?= htmlspecialchars($plan['matricule']) ?>)</td>
                    </tr>
                    <tr>
                        <td><strong>Sujet:</strong></td>
                        <td><?= htmlspecialchars($plan['sujet_intitule']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Spécialisation:</strong></td>
                        <td><?= htmlspecialchars($plan['specialisation'] ?? 'Non spécifiée') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Version:</strong></td>
                        <td>v<?= $plan['version'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>Date de soumission:</strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($plan['date_soumission'])) ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if (!empty($plan['introduction'])): ?>
                <div class="mb-4">
                    <h6 class="fw-bold">Introduction</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(htmlspecialchars($plan['introduction'])) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($plan['problematique'])): ?>
                <div class="mb-4">
                    <h6 class="fw-bold">Problématique</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(htmlspecialchars($plan['problematique'])) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($plan['objectifs'])): ?>
                <div class="mb-4">
                    <h6 class="fw-bold">Objectifs</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(htmlspecialchars($plan['objectifs'])) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($plan['methodologie'])): ?>
                <div class="mb-4">
                    <h6 class="fw-bold">Méthodologie</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(htmlspecialchars($plan['methodologie'])) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($chapitres)): ?>
                <div class="mb-4">
                    <h6 class="fw-bold">Structure du plan (Chapitres)</h6>
                    <div class="list-group">
                        <?php foreach ($chapitres as $chapitre): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1">
                                            Chapitre <?= $chapitre['numero_chapitre'] ?>: 
                                            <?= htmlspecialchars($chapitre['titre_chapitre']) ?>
                                        </h6>
                                        <?php if (!empty($chapitre['description'])): ?>
                                            <p class="mb-0 text-muted">
                                                <?= nl2br(htmlspecialchars($chapitre['description'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($chapitre['deadline']): ?>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?= date('d/m/Y', strtotime($chapitre['deadline'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Historique</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($historique)): ?>
                        <p class="text-muted mb-0">Aucun historique disponible</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($historique as $h): ?>
                                <div class="timeline-item mb-3">
                                    <div class="d-flex">
                                        <div class="timeline-marker bg-<?= 
                                            $h['statut'] === 'Validé' ? 'success' : 
                                            ($h['statut'] === 'Rejeté' ? 'danger' : 'primary') ?> 
                                            rounded-circle p-1 me-2" style="width: 10px; height: 10px;">
                                        </div>
                                        <div class="flex-grow-1">
                                            <small class="text-muted d-block">
                                                <?= date('d/m/Y H:i', strtotime($h['date_action'])) ?>
                                            </small>
                                            <strong><?= htmlspecialchars($h['statut']) ?></strong>
                                            <?php if ($h['commentaire']): ?>
                                                <p class="mb-0 small text-muted">
                                                    <?= htmlspecialchars($h['commentaire']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                Par: <?= htmlspecialchars($h['auteur_nom'] ?? 'Système') ?>
                                            </small>
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
    <?php
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Erreur: " . $e->getMessage();
}
?>