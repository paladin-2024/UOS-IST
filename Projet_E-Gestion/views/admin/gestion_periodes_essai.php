<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();
$idUser = $_SESSION['id'];

// Récupérer toutes les périodes d'essai
$stmt = $connexion->prepare("
    SELECT id, client_nom, client_email, date_debut, date_fin, statut, 
           nombre_connexions, derniere_connexion,
           CASE 
               WHEN date_fin > NOW() AND statut = 'Actif' THEN (date_fin::date - NOW()::date)
               ELSE 0 
           END as jours_restants,
           CASE 
               WHEN date_fin > NOW() AND statut = 'Actif' THEN 'Actif'
               WHEN date_fin <= NOW() THEN 'Expiré'
               ELSE statut
           END as statut_reel
    FROM periodes_essai 
    ORDER BY date_debut DESC
");
$stmt->execute();
$periodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'alerte
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';
unset($_SESSION['message'], $_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Périodes d'Essai</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Administration</li>
                <li class="breadcrumb-item active">Périodes d'Essai</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiques rapides -->
        <div class="row mb-4">
            <?php
            $stats = [
                'total' => count($periodes),
                'actifs' => count(array_filter($periodes, function($p) { return $p['statut_reel'] === 'Actif'; })),
                'expires' => count(array_filter($periodes, function($p) { return $p['statut_reel'] === 'Expiré'; })),
                'suspendus' => count(array_filter($periodes, function($p) { return $p['statut'] === 'Suspendu'; }))
            ];
            ?>
            
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-primary"><?= $stats['total'] ?></h5>
                        <p class="card-text">Total Périodes</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?= $stats['actifs'] ?></h5>
                        <p class="card-text">Actives</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><?= $stats['expires'] ?></h5>
                        <p class="card-text">Expirées</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><?= $stats['suspendus'] ?></h5>
                        <p class="card-text">Suspendues</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions principales -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Actions</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nouvellePeriodeModal">
                            <i class="bi bi-plus-circle"></i> Nouvelle Période d'Essai
                        </button>
                        <button type="button" class="btn btn-info" onclick="window.location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Actualiser
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des périodes d'essai -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Périodes d'Essai Enregistrées</h5>
                        
                        <?php if (empty($periodes)): ?>
                        <div class="alert alert-info">
                            Aucune période d'essai n'a été configurée.
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable" id="periodesTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Email</th>
                                        <th>Date Début</th>
                                        <th>Date Fin</th>
                                        <th>Jours Restants</th>
                                        <th>Connexions</th>
                                        <th>Dernière Connexion</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($periodes as $periode): ?>
                                    <tr class="<?= $periode['statut_reel'] === 'Actif' ? 'table-success' : ($periode['statut_reel'] === 'Expiré' ? 'table-danger' : 'table-warning') ?>">
                                        <td><?= $periode['id'] ?></td>
                                        <td><?= htmlspecialchars($periode['client_nom']) ?></td>
                                        <td><?= htmlspecialchars($periode['client_email'] ?? 'N/A') ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($periode['date_debut'])) ?></td>
                                        <td><?= date('d/m/Y H:i', strtotime($periode['date_fin'])) ?></td>
                                        <td>
                                            <?php if ($periode['jours_restants'] > 0): ?>
                                                <span class="badge bg-success"><?= $periode['jours_restants'] ?> jour(s)</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Expiré</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $periode['nombre_connexions'] ?></td>
                                        <td>
                                            <?= $periode['derniere_connexion'] ? date('d/m/Y H:i', strtotime($periode['derniere_connexion'])) : 'Jamais' ?>
                                        </td>
                                        <td>
                                            <?php
                                            $statutClass = match($periode['statut_reel']) {
                                                'Actif' => 'bg-success',
                                                'Expiré' => 'bg-danger',
                                                'Suspendu' => 'bg-warning',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $statutClass ?>"><?= $periode['statut_reel'] ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <?php if ($periode['statut_reel'] === 'Actif'): ?>
                                                    <button type="button" class="btn btn-sm btn-warning" onclick="prolongerPeriode(<?= $periode['id'] ?>, '<?= htmlspecialchars($periode['client_nom']) ?>')">
                                                        <i class="bi bi-clock"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="suspendrePeriode(<?= $periode['id'] ?>)">
                                                        <i class="bi bi-pause"></i>
                                                    </button>
                                                <?php elseif ($periode['statut'] === 'Suspendu'): ?>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="reactiverPeriode(<?= $periode['id'] ?>)">
                                                        <i class="bi bi-play"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-sm btn-info" onclick="voirDetails(<?= $periode['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
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
            </div>
        </div>
    </section>
</main>

<!-- Modal pour créer une nouvelle période d'essai -->
<div class="modal fade" id="nouvellePeriodeModal" tabindex="-1" aria-labelledby="nouvellePeriodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/gestion_periode_essai.php" method="POST">
                <input type="hidden" name="action" value="creer_periode">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="nouvellePeriodeModalLabel">Créer une nouvelle période d'essai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="client_nom" class="form-label">Nom du client <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="client_nom" name="client_nom" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="client_email" class="form-label">Email du client</label>
                        <input type="email" class="form-control" id="client_email" name="client_email">
                    </div>
                    
                    <div class="mb-3">
                        <label for="duree_jours" class="form-label">Durée de la période d'essai (en jours) <span class="text-danger">*</span></label>
                        <select class="form-select" id="duree_jours" name="duree_jours" required>
                            <option value="">Sélectionner une durée</option>
                            <option value="7">7 jours</option>
                            <option value="15">15 jours</option>
                            <option value="30" selected>30 jours</option>
                            <option value="60">60 jours</option>
                            <option value="90">90 jours</option>
                        </select>
                        <div class="form-text">Ou saisissez une durée personnalisée:</div>
                        <input type="number" class="form-control mt-2" id="duree_personnalisee" name="duree_personnalisee" min="1" max="365" placeholder="Nombre de jours personnalisé">
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Note:</strong> La période d'essai commencera immédiatement après la création.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer la période d'essai</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour prolonger une période d'essai -->
<div class="modal fade" id="prolongerPeriodeModal" tabindex="-1" aria-labelledby="prolongerPeriodeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/gestion_periode_essai.php" method="POST">
                <input type="hidden" name="action" value="prolonger_periode">
                <input type="hidden" name="periode_id" id="prolonger_periode_id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="prolongerPeriodeModalLabel">Prolonger la période d'essai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Client: <span id="prolonger_client_nom" class="fw-bold"></span></p>
                    
                    <div class="mb-3">
                        <label for="jours_supplementaires" class="form-label">Jours supplémentaires <span class="text-danger">*</span></label>
                                                <select class="form-select" id="jours_supplementaires" name="jours_supplementaires" required>
                            <option value="">Sélectionner</option>
                            <option value="7">7 jours</option>
                            <option value="15">15 jours</option>
                            <option value="30">30 jours</option>
                            <option value="60">60 jours</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        Cette action prolongera la période d'essai à partir de la date de fin actuelle.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Prolonger</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour voir les détails d'une période -->
<div class="modal fade" id="detailsPeriodeModal" tabindex="-1" aria-labelledby="detailsPeriodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsPeriodeModalLabel">Détails de la période d'essai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailsPeriodeContainer">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Gérer la sélection de durée personnalisée
    const dureeSelect = document.getElementById('duree_jours');
    const dureePersonnalisee = document.getElementById('duree_personnalisee');
    
    if (dureeSelect && dureePersonnalisee) {
        dureePersonnalisee.addEventListener('input', function() {
            if (this.value) {
                dureeSelect.value = '';
                dureeSelect.name = 'duree_jours_old';
                this.name = 'duree_jours';
            }
        });
        
        dureeSelect.addEventListener('change', function() {
            if (this.value) {
                dureePersonnalisee.value = '';
                dureePersonnalisee.name = 'duree_personnalisee';
                this.name = 'duree_jours';
            }
        });
    }
});

// Fonction pour prolonger une période
function prolongerPeriode(id, clientNom) {
    document.getElementById('prolonger_periode_id').value = id;
    document.getElementById('prolonger_client_nom').textContent = clientNom;
    
    const modal = new bootstrap.Modal(document.getElementById('prolongerPeriodeModal'));
    modal.show();
}

// Fonction pour suspendre une période
function suspendrePeriode(id) {
    if (confirm('Êtes-vous sûr de vouloir suspendre cette période d\'essai ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'controller/gestion_periode_essai.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'suspendre_periode';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'periode_id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Fonction pour réactiver une période
function reactiverPeriode(id) {
    if (confirm('Êtes-vous sûr de vouloir réactiver cette période d\'essai ?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'controller/gestion_periode_essai.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'reactiver_periode';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'periode_id';
        idInput.value = id;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Fonction pour voir les détails d'une période
function voirDetails(id) {
    const container = document.getElementById('detailsPeriodeContainer');
    
    container.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p>Chargement des détails...</p>
        </div>
    `;
    
    fetch(`controller/gestion_periode_essai.php?action=get_periode&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            const dateDebut = new Date(data.date_debut);
            const dateFin = new Date(data.date_fin);
            const maintenant = new Date();
            
            let statutBadge = '';
            let joursRestants = 0;
            
            if (dateFin > maintenant && data.statut === 'Actif') {
                joursRestants = Math.ceil((dateFin - maintenant) / (1000 * 60 * 60 * 24));
                statutBadge = '<span class="badge bg-success">Actif</span>';
            } else if (dateFin <= maintenant) {
                statutBadge = '<span class="badge bg-danger">Expiré</span>';
            } else if (data.statut === 'Suspendu') {
                statutBadge = '<span class="badge bg-warning">Suspendu</span>';
            }
            
            container.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="bi bi-person-circle text-primary"></i> Informations Client</h6>
                        <p><strong>Nom:</strong> ${data.client_nom}</p>
                        <p><strong>Email:</strong> ${data.client_email || 'Non renseigné'}</p>
                        <p><strong>Statut:</strong> ${statutBadge}</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-calendar-check text-info"></i> Période</h6>
                        <p><strong>Date début:</strong> ${dateDebut.toLocaleDateString('fr-FR')} ${dateDebut.toLocaleTimeString('fr-FR')}</p>
                        <p><strong>Date fin:</strong> ${dateFin.toLocaleDateString('fr-FR')} ${dateFin.toLocaleTimeString('fr-FR')}</p>
                        ${joursRestants > 0 ? `<p><strong>Jours restants:</strong> <span class="badge bg-success">${joursRestants}</span></p>` : ''}
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="bi bi-graph-up text-success"></i> Statistiques d'utilisation</h6>
                        <p><strong>Nombre de connexions:</strong> ${data.nombre_connexions}</p>
                        <p><strong>Dernière connexion:</strong> ${data.derniere_connexion ? new Date(data.derniere_connexion).toLocaleDateString('fr-FR') + ' ' + new Date(data.derniere_connexion).toLocaleTimeString('fr-FR') : 'Jamais'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="bi bi-clock-history text-warning"></i> Historique</h6>
                        <p><strong>Créé le:</strong> ${new Date(data.created_at).toLocaleDateString('fr-FR')} ${new Date(data.created_at).toLocaleTimeString('fr-FR')}</p>
                        <p><strong>Dernière modification:</strong> ${new Date(data.updated_at).toLocaleDateString('fr-FR')} ${new Date(data.updated_at).toLocaleTimeString('fr-FR')}</p>
                    </div>
                </div>
                
                ${joursRestants > 0 && joursRestants <= 7 ? `
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Attention:</strong> Cette période d'essai expire dans ${joursRestants} jour(s). 
                    Pensez à contacter le client pour le renouvellement.
                </div>
                ` : ''}
            `;
        })
        .catch(error => {
            console.error('Erreur:', error);
            container.innerHTML = `<div class="alert alert-danger">Une erreur s'est produite lors du chargement des données.</div>`;
        });
    
    const modal = new bootstrap.Modal(document.getElementById('detailsPeriodeModal'));
    modal.show();
}
</script>

<?php include "./views/include/footer.php"; ?>
