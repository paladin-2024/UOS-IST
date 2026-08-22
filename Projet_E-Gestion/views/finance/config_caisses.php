<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer toutes les caisses
$stmt = $connexion->query("SELECT c.*, CONCAT(a.noms) as responsable_nom 
                           FROM caisses c 
                           LEFT JOIN agent a ON c.idAgent_responsable = a.idAgent 
                           ORDER BY c.est_actif DESC, c.designation ASC");
$caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Récupérer les messages d'alerte s'ils existent
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['message']);
unset($_SESSION['messageType']);

// Récupérer les devises disponibles depuis la configuration finance
$stmt = $connexion->query("SELECT devise_principale, devise_secondaire FROM config_finance WHERE est_actif = 1 LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);
$devises = [];
if ($config) {
    $devises[] = $config['devise_principale'];
    if (!empty($config['devise_secondaire'])) {
        $devises[] = $config['devise_secondaire'];
    }
}
// Ajouter d'autres devises courantes
$devises_supplementaires = ['USD', 'EUR', 'CDF', 'GBP', 'CAD'];
foreach ($devises_supplementaires as $dev) {
    if (!in_array($dev, $devises)) {
        $devises[] = $dev;
    }
}

// Récupérer tous les agents pour le sélecteur de responsables
$stmt = $connexion->query("SELECT idAgent as id, noms AS nom_complet FROM agent WHERE 1 ORDER BY noms");
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Caisses</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Caisses</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Liste des Caisses
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCaisseModal">
                                <i class="bi bi-plus-circle"></i> Nouvelle Caisse
                            </button>
                        </h5>
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Désignation</th>
                                        <th>Responsable</th>
                                        <th>Devise</th>
                                        <th>Solde actuel</th>
                                        <th>Plafond</th>
                                        <th>Localisation</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($caisses)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Aucune caisse enregistrée</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($caisses as $caisse): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($caisse['designation']) ?></td>
                                            <td><?= htmlspecialchars($caisse['responsable_nom']) ?></td>
                                            <td><?= htmlspecialchars($caisse['devise']) ?></td>
                                            <td class="text-end"><?= number_format($caisse['solde_actuel'], 2) ?></td>
                                            <td class="text-end"><?= $caisse['plafond_caisse'] ? number_format($caisse['plafond_caisse'], 2) : '-' ?></td>
                                            <td><?= htmlspecialchars($caisse['localisation'] ?: '-') ?></td>
                                            <td>
                                                <?php if ($caisse['est_actif']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="finance/caisse_detail.view&id=<?= $caisse['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-info edit-caisse" 
                                                        data-id="<?= $caisse['id'] ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editCaisseModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-caisse" 
                                                        data-id="<?= $caisse['id'] ?>"
                                                        data-name="<?= htmlspecialchars($caisse['designation']) ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal Ajout Caisse -->
<div class="modal fade" id="addCaisseModal" tabindex="-1" aria-labelledby="addCaisseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/update_caisse.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCaisseModalLabel">Ajouter une caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="designation" name="designation" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="idAgent_responsable" class="form-label">Responsable <span class="text-danger">*</span></label>
                                <select class="form-select" id="idAgent_responsable" name="idAgent_responsable" required>
                                    <option value="">Sélectionner un responsable</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['nom_complet']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="devise" class="form-label">Devise <span class="text-danger">*</span></label>
                                <select class="form-select" id="devise" name="devise" required>
                                    <?php foreach ($devises as $dev): ?>
                                        <option value="<?= htmlspecialchars($dev) ?>"><?= htmlspecialchars($dev) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="solde_initial" class="form-label">Solde initial</label>
                                <input type="number" step="0.01" class="form-control" id="solde_initial" name="solde_initial" value="0.00">
                            </div>
                            
                            <div class="mb-3">
                                <label for="plafond_caisse" class="form-label">Plafond de caisse</label>
                                <input type="number" step="0.01" class="form-control" id="plafond_caisse" name="plafond_caisse">
                                <small class="text-muted">Montant maximum autorisé dans la caisse</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="localisation" class="form-label">Localisation</label>
                                <input type="text" class="form-control" id="localisation" name="localisation">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" value="1" checked>
                                <label class="form-check-label" for="est_actif">
                                    Caisse active
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modification Caisse -->
<div class="modal fade" id="editCaisseModal" tabindex="-1" aria-labelledby="editCaisseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/update_caisse.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCaisseModalLabel">Modifier une caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_designation" name="designation" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_idAgent_responsable" class="form-label">Responsable <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_idAgent_responsable" name="idAgent_responsable" required>
                                    <option value="">Sélectionner un responsable</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['nom_complet']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_devise" class="form-label">Devise <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_devise" name="devise" required>
                                    <?php foreach ($devises as $dev): ?>
                                        <option value="<?= htmlspecialchars($dev) ?>"><?= htmlspecialchars($dev) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                            <label for="edit_solde_initial" class="form-label">Solde initial</label>
                                <input type="number" step="0.01" class="form-control" id="edit_solde_initial" name="solde_initial">
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_plafond_caisse" class="form-label">Plafond de caisse</label>
                                <input type="number" step="0.01" class="form-control" id="edit_plafond_caisse" name="plafond_caisse">
                                <small class="text-muted">Montant maximum autorisé dans la caisse</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_localisation" class="form-label">Localisation</label>
                                <input type="text" class="form-control" id="edit_localisation" name="localisation">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="edit_est_actif" name="est_actif" value="1">
                                <label class="form-check-label" for="edit_est_actif">
                                    Caisse active
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du chargement des données pour l'édition
    const editButtons = document.querySelectorAll('.edit-caisse');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Requête AJAX pour récupérer les données de la caisse
            fetch(`controller/get_caisse.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // Remplir le formulaire avec les données
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_designation').value = data.designation;
                    document.getElementById('edit_idAgent_responsable').value = data.idAgent_responsable;
                    document.getElementById('edit_devise').value = data.devise;
                    document.getElementById('edit_solde_initial').value = data.solde_initial;
                    document.getElementById('edit_plafond_caisse').value = data.plafond_caisse || '';
                    document.getElementById('edit_localisation').value = data.localisation || '';
                    document.getElementById('edit_description').value = data.description || '';
                    document.getElementById('edit_est_actif').checked = data.est_actif === '1';
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des données:', error);
                    alert('Une erreur est survenue lors de la récupération des données de la caisse.');
                });
        });
    });
    
    // Gestion de la suppression avec SweetAlert
    const deleteButtons = document.querySelectorAll('.delete-caisse');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: `Voulez-vous vraiment supprimer la caisse "${name}" ? Cette action est irréversible.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_caisse.php?id=${id}`;
                }
            });
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
