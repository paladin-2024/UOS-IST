<?php include "./views/include/header.php"; 

// Initialiser les modèles nécessaires
$congeModel = new Conge();
$agentModel = new Agent();
$serviceModel = new Service();

// Récupérer l'ID du service si spécifié
$idService = isset($_GET['service']) ? intval($_GET['service']) : null;

// Récupérer les demandes en attente
$demandesEnAttente = $congeModel->getDemandesCongeEnAttente($idService);

// Récupérer l'historique des demandes
$historiqueDemandes = $congeModel->getHistoriqueDemandesConge($idService);

// Récupérer les statistiques pour le tableau de bord
$dashboard = $congeModel->getDashboardConges($idService);

// Récupérer la liste des services pour le filtre
$services = $serviceModel->getService();
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Congés</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">GRH</li>
                <li class="breadcrumb-item active">Congés</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <!-- Tableau de bord des congés -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Tableau de bord des congés</h5>
                        
                        

                        <!-- Après le filtre par service, ajoutez ces boutons d'export -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <form method="GET" action="" class="d-flex">
                                    <input type="hidden" name="page" value="grh/conges.list">
                                    <select name="service" class="form-select me-2">
                                        <option value="">Tous les services</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?= $service['idService'] ?>" <?= $idService == $service['idService'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($service['designationService']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">Filtrer</button>
                                </form>
                            </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group me-2">
                                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-file-pdf"></i> Exporter PDF
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a target="_blank" class="dropdown-item" href="controller/export_conges_pdf.php?type=en_conge<?= $idService ? '&service='.$idService : '' ?>">
                                                <i class="bi bi-person-check me-2"></i>
                                    <li>
                                            <a target="_blank" class="dropdown-item" href="controller/export_conges_pdf.php?type=en_conge<?= $idService ? '&service='.$idService : '' ?>">
                                                <i class="bi bi-person-check me-2"></i>
                                                Agents en congé
                                            </a>
                                        </li>
                                        <li>
                                            <a target="_blank" class="dropdown-item" href="controller/export_conges_pdf.php?type=en_attente<?= $idService ? '&service='.$idService : '' ?>">
                                                <i class="bi bi-hourglass-split me-2"></i>
                                                Demandes en attente
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <a href="grh/conges.add" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Nouvelle demande
                                </a>
                            </div>
                        </div>


                        <!-- Statistiques -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card info-card sales-card">
                                    <div class="card-body">
                                        <h5 class="card-title">En attente</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-hourglass-split text-warning"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $dashboard['stats']['nb_en_attente'] ?? 0 ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card info-card revenue-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Approuvés</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-check-circle text-success"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $dashboard['stats']['nb_approuve'] ?? 0 ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card info-card customers-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Refusés</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-x-circle text-danger"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= $dashboard['stats']['nb_refuse'] ?? 0 ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card info-card customers-card">
                                    <div class="card-body">
                                        <h5 class="card-title">Agents en congé</h5>
                                        <div class="d-flex align-items-center">
                                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                                <i class="bi bi-person-check text-primary"></i>
                                            </div>
                                            <div class="ps-3">
                                                <h6><?= count($dashboard['agents_en_conge']) ?></h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Liste des agents actuellement en congé -->
                        <?php if (!empty($dashboard['agents_en_conge'])): ?>
                            <div class="mt-4">
                                <h5>Agents actuellement en congé <a href="controller/export_conges_pdf.php?type=en_conge<?= $idService ? '&service='.$idService : '' ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bi bi-file-pdf"></i> Exporter PDF
                                </a></h5>
                                
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Agent</th>
                                                <th>Type de congé</th>
                                                <th>Début</th>
                                                <th>Fin</th>
                                                <th>Jours restants</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dashboard['agents_en_conge'] as $agent): ?>
                                                <?php 
                                                    $dateFin = new DateTime($agent['date_fin']);
                                                    $aujourdhui = new DateTime();
                                                    $interval = $aujourdhui->diff($dateFin);
                                                    $joursRestants = $interval->days;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($agent['noms']) ?></strong><br>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($agent['matricule']) ?>
                                                        </small>
                                                    </td>
                                                    <td><?= htmlspecialchars($agent['type_conge_nom']) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($agent['date_debut'])) ?></td>
                                                    <td><?= date('d/m/Y', strtotime($agent['date_fin'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-info"><?= $joursRestants ?> jour(s)</span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Demandes en attente -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Demandes en attente <a href="controller/export_conges_pdf.php?type=en_attente<?= $idService ? '&service='.$idService : '' ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="bi bi-file-pdf"></i> Exporter PDF
                        </a></h5>
                        
                        <?php if (empty($demandesEnAttente)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucune demande en attente.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Agent</th>
                                            <th>Service</th>
                                            <th>Type de congé</th>
                                            <th>Période</th>
                                            <th>Durée</th>
                                            <th>Date demande</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($demandesEnAttente as $demande): ?>
                                            <?php 
                                                $dateDebut = new DateTime($demande['date_debut']);
                                                $dateFin = new DateTime($demande['date_fin']);
                                                $joursOuvrables = 0;
                                                
                                                for ($date = clone $dateDebut; $date <= $dateFin; $date->modify('+1 day')) {
                                                    $jour = $date->format('N'); // 1 (lundi) à 7 (dimanche)
                                                    if ($jour < 6) { // Si ce n'est pas samedi (6) ou dimanche (7)
                                                        $joursOuvrables++;
                                                    }
                                                }
                                                $duree = $joursOuvrables;
                                            ?>

                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($demande['nom_agent']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($demande['matricule']) ?>
                                                    </small>
                                                </td>
                                                <td><?= htmlspecialchars($demande['service_nom']) ?></td>
                                                <td><?= htmlspecialchars($demande['type_conge_nom']) ?></td>
                                                <td>
                                                    Du <?= date('d/m/Y', strtotime($demande['date_debut'])) ?><br>
                                                    Au <?= date('d/m/Y', strtotime($demande['date_fin'])) ?>
                                                </td>
                                                <td><?= $duree ?> jour(s)</td>
                                                <td><?= date('d/m/Y H:i', strtotime($demande['date_demande'])) ?></td>
                                                <td>
                                                    <a href="grh/conges.view&id=<?= $demande['iddemande_conge'] ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($demande['statut'] == 'Approuvé'): ?>
                                                        <a href="controller/generate_attestation_conge.php?id=<?= $demande['iddemande_conge'] ?>" class="btn btn-sm btn-primary" title="Générer attestation" target="_blank">
                                                            <i class="bi bi-file-earmark-pdf"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-success approuver-conge" data-id="<?= $demande['iddemande_conge'] ?>">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger refuser-conge" data-id="<?= $demande['iddemande_conge'] ?>">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Historique des demandes -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Historique des demandes</h5>
                        
                        <?php if (empty($historiqueDemandes)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Aucun historique disponible.
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Agent</th>
                                            <th>Service</th>
                                            <th>Type de congé</th>
                                            <th>Période</th>
                                            <th>Statut</th>
                                            <th>Décision par</th>
                                            <th>Date décision</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($historiqueDemandes as $demande): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($demande['nom_agent']) ?></strong><br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($demande['matricule']) ?>
                                                    </small>
                                                </td>
                                                <td><?= htmlspecialchars($demande['service_nom']) ?></td>
                                                <td><?= htmlspecialchars($demande['type_conge_nom']) ?></td>
                                                <td>
                                                    Du <?= date('d/m/Y', strtotime($demande['date_debut'])) ?><br>
                                                    Au <?= date('d/m/Y', strtotime($demande['date_fin'])) ?>
                                                </td>
                                                <td>
                                                    <?php if ($demande['statut'] == 'Approuvé'): ?>
                                                        <span class="badge bg-success">Approuvé</span>
                                                    <?php elseif ($demande['statut'] == 'Refusé'): ?>
                                                        <span class="badge bg-danger">Refusé</span>
                                                    <?php elseif ($demande['statut'] == 'Annulé'): ?>
                                                        <span class="badge bg-secondary">Annulé</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($demande['decideur_nom'] ?? 'N/A') ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($demande['date_decision'])) ?></td>
                                                <td>
                                                    <a href="grh/conges.view&id=<?= $demande['iddemande_conge'] ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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
                <input type="hidden" name="idDemande" id="approuverIdDemande">
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
                <input type="hidden" name="idDemande" id="refuserIdDemande">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal d'approbation
    const approuverBtns = document.querySelectorAll('.approuver-conge');
    const approuverModal = new bootstrap.Modal(document.getElementById('approuverCongeModal'));
    
    approuverBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const idDemande = this.getAttribute('data-id');
            document.getElementById('approuverIdDemande').value = idDemande;
            approuverModal.show();
        });
    });
    
    // Gestion du modal de refus
    const refuserBtns = document.querySelectorAll('.refuser-conge');
    const refuserModal = new bootstrap.Modal(document.getElementById('refuserCongeModal'));
    
    refuserBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const idDemande = this.getAttribute('data-id');
            document.getElementById('refuserIdDemande').value = idDemande;
            refuserModal.show();
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
