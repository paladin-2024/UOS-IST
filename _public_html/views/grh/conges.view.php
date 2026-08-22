<?php include "./views/include/header.php"; 

// Initialiser les modèles nécessaires
$congeModel = new Conge();
$agentModel = new Agent();

// Récupérer l'ID de la demande
$idDemande = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Récupérer les détails de la demande
$demande = $congeModel->getDemandeCongeById($idDemande);

// Vérifier si la demande existe
if (!$demande) {
    header('Location: grh/conges.list');
    exit;
}

// Calculer la durée en jours ouvrables
$dateDebut = new DateTime($demande['date_debut']);
$dateFin = new DateTime($demande['date_fin']);
$interval = $dateDebut->diff($dateFin);
$duree = $interval->days + 1;

// Calculer le nombre de jours ouvrables
$joursOuvrables = 0;
for ($d = clone $dateDebut; $d <= $dateFin; $d->modify('+1 day')) {
    if ($d->format('N') < 6) { // 1 (lundi) à 5 (vendredi) sont des jours ouvrables
        $joursOuvrables++;
    }
}

// Vérifier si l'utilisateur est autorisé à voir cette demande
$isAdmin = isset($_SESSION['idRole']) && ($_SESSION['idRole'] == 1 || $_SESSION['idRole'] == 3);
$isOwner = isset($_SESSION['idAgent']) && $_SESSION['idAgent'] == $demande['idAgent'];

if (!$isAdmin && !$isOwner) {
    header('Location: index');
    exit;
}

// Récupérer les informations de l'agent
$agent = $agentModel->getAgentById($demande['idAgent']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Détails de la demande de congé</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">GRH</li>
                <li class="breadcrumb-item"><a href="grh/conges.list">Congés</a></li>
                <li class="breadcrumb-item active">Détails</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            Demande de congé #<?= $idDemande ?>
                            <span class="float-end">
                                <?php if ($demande['statut'] == 'En attente'): ?>
                                    <span class="badge bg-warning">En attente</span>
                                <?php elseif ($demande['statut'] == 'Approuvé'): ?>
                                    <span class="badge bg-success">Approuvé</span>
                                <?php elseif ($demande['statut'] == 'Refusé'): ?>
                                    <span class="badge bg-danger">Refusé</span>
                                <?php elseif ($demande['statut'] == 'Annulé'): ?>
                                    <span class="badge bg-secondary">Annulé</span>
                                <?php endif; ?>
                            </span>
                        </h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Informations de l'agent</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%;">Nom</th>
                                        <td><?= htmlspecialchars($demande['nom_agent']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Matricule</th>
                                        <td><?= htmlspecialchars($agent['matricule']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Service</th>
                                        <td><?= htmlspecialchars($agent['service_nom'] ?? 'N/A') ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Détails de la demande</h6>
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%;">Type de congé</th>
                                        <td><?= htmlspecialchars($demande['type_conge_nom']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>Période</th>
                                        <td>
                                            Du <?= date('d/m/Y', strtotime($demande['date_debut'])) ?><br>
                                            Au <?= date('d/m/Y', strtotime($demande['date_fin'])) ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Durée</th>
                                        <td><?= $joursOuvrables ?> jours ouvrables</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h6>Motif</h6>
                                <div class="border p-3 rounded bg-light">
                                    <?= nl2br(htmlspecialchars($demande['motif'] ?? 'Aucun motif spécifié')) ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($demande['document_justificatif'])): ?>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h6>Document justificatif</h6>
                                    <div class="border p-3 rounded bg-light">
                                        <a href="uploads/conges/<?= htmlspecialchars($demande['document_justificatif']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark"></i> Voir le document
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($demande['statut'] != 'En attente'): ?>
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <h6>Décision</h6>
                                    <table class="table table-bordered">
                                        <tr>
                                            <th style="width: 30%;">Statut</th>
                                            <td>
                                                <?php if ($demande['statut'] == 'Approuvé'): ?>
                                                    <span class="badge bg-success">Approuvé</span>
                                                <?php elseif ($demande['statut'] == 'Refusé'): ?>
                                                    <span class="badge bg-danger">Refusé</span>
                                                <?php elseif ($demande['statut'] == 'Annulé'): ?>
                                                    <span class="badge bg-secondary">Annulé</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Date de décision</th>
                                            <td><?= date('d/m/Y H:i', strtotime($demande['date_decision'])) ?></td>
                                        </tr>
                                        <?php if (!empty($demande['commentaire_decision'])): ?>
                                            <tr>
                                                <th>Commentaire</th>
                                                <td><?= nl2br(htmlspecialchars($demande['commentaire_decision'])) ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="d-flex justify-content-between">
                                <a href="grh/conges.list" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Retour à la liste
                                    </a>
                                    
                                    <div>
                                        <?php if ($demande['statut'] == 'En attente'): ?>
                                            <?php if ($isAdmin): ?>
                                                <button type="button" class="btn btn-success approuver-conge" data-id="<?= $idDemande ?>">
                                                    <i class="bi bi-check-lg"></i> Approuver
                                                </button>
                                                <button type="button" class="btn btn-danger refuser-conge" data-id="<?= $idDemande ?>">
                                                    <i class="bi bi-x-lg"></i> Refuser
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($isOwner): ?>
                                                <button type="button" class="btn btn-warning annuler-conge" data-id="<?= $idDemande ?>">
                                                    <i class="bi bi-x-circle"></i> Annuler ma demande
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($isAdmin && $demande['statut'] == 'Approuvé'): ?>
                                            <a href="controller/generate_attestation_conge.php?id=<?= $idDemande ?>" target="_blank" class="btn btn-info">
                                                <i class="bi bi-file-earmark-pdf"></i> Générer attestation
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour approuver un congé -->
<div class="modal fade" id="approuverCongeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="approuverCongeForm" action="controller/approuver_conge.php" method="post">
                <input type="hidden" name="idDemande" id="approuverIdDemande" value="<?= $idDemande ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Approuver la demande de congé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="commentaireApprobation" class="form-label">Commentaire (optionnel)</label>
                        <textarea class="form-control" id="commentaireApprobation" name="commentaire" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Approuver</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour refuser un congé -->
<div class="modal fade" id="refuserCongeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="refuserCongeForm" action="controller/refuser_conge.php" method="post">
                <input type="hidden" name="idDemande" id="refuserIdDemande" value="<?= $idDemande ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Refuser la demande de congé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="commentaireRefus" class="form-label">Motif du refus <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="commentaireRefus" name="commentaire" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Refuser</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour annuler une demande -->
<div class="modal fade" id="annulerCongeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="annulerCongeForm" action="controller/annuler_conge.php" method="post">
                <input type="hidden" name="idDemande" id="annulerIdDemande" value="<?= $idDemande ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Annuler ma demande de congé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir annuler cette demande de congé ?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Non</button>
                    <button type="submit" class="btn btn-warning">Oui, annuler ma demande</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal d'approbation
    const approuverBtns = document.querySelectorAll('.approuver-conge');
    const approuverModal = new bootstrap.Modal(document.getElementById('approuverCongeModal'));
    
    approuverBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            approuverModal.show();
        });
    });
    
    // Gestion du modal de refus
    const refuserBtns = document.querySelectorAll('.refuser-conge');
    const refuserModal = new bootstrap.Modal(document.getElementById('refuserCongeModal'));
    
    refuserBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            refuserModal.show();
        });
    });
    
    // Gestion du modal d'annulation
    const annulerBtns = document.querySelectorAll('.annuler-conge');
    const annulerModal = new bootstrap.Modal(document.getElementById('annulerCongeModal'));
    
    annulerBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            annulerModal.show();
        });
    });
    
    // Validation du formulaire de refus
    const refuserForm = document.getElementById('refuserCongeForm');
    if (refuserForm) {
        refuserForm.addEventListener('submit', function(event) {
            const commentaire = document.getElementById('commentaireRefus').value.trim();
            if (!commentaire) {
                event.preventDefault();
                alert('Veuillez indiquer le motif du refus.');
            }
        });
    }
});
</script>

<?php include "./views/include/footer.php"; ?>
