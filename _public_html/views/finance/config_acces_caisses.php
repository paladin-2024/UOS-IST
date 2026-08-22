<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer tous les droits d'accès pour les caisses
$stmt = $connexion->query("SELECT d.*, u.\"nomUser\", c.designation as caisse_nom, 
                          CONCAT(a.noms, ' (', a.matricule, ')') as agent_nom 
                          FROM droits_acces_finances d 
                          LEFT JOIN t_users u ON d.\"idUser\" = u.\"idUser\" 
                          LEFT JOIN caisses c ON d.entite_id = c.id 
                          LEFT JOIN agent a ON u.\"idAgent\" = a.\"idAgent\" 
                          WHERE d.type = 'Caisse' 
                          ORDER BY d.est_actif DESC, d.date_debut DESC");
$droits_acces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer tous les utilisateurs
$stmt = $connexion->query("SELECT u.\"idUser\", u.\"nomUser\", CONCAT(a.noms, ' (', a.matricule, ')') as agent_nom 
                          FROM t_users u 
                          LEFT JOIN agent a ON u.\"idAgent\" = a.\"idAgent\" 
                          WHERE u.\"etatUser\" = 1 
                          ORDER BY u.\"nomUser\" ASC");
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer toutes les caisses
$stmt = $connexion->query("SELECT id, designation FROM caisses WHERE est_actif = 1 ORDER BY designation ASC");
$caisses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'alerte s'ils existent
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['message']);
unset($_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Accès aux Caisses</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Accès Caisses</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Liste des Droits d'Accès aux Caisses
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAccessModal">
                                <i class="bi bi-plus-circle"></i> Nouveau Droit d'Accès
                            </button>
                        </h5>
                        
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                                <?= $message ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Utilisateur</th>
                                        <th>Agent</th>
                                        <th>Caisse</th>
                                        <th>Niveau d'accès</th>
                                        <th>Date début</th>
                                        <th>Date fin</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($droits_acces)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Aucun droit d'accès enregistré</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($droits_acces as $droit): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($droit['nomUser']) ?></td>
                                            <td><?= htmlspecialchars($droit['agent_nom'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($droit['caisse_nom'] ?? 'Toutes les caisses') ?></td>
                                            <td>
                                                <?php 
                                                    $badgeClass = '';
                                                    switch($droit['niveau']) {
                                                        case 'Lecture': $badgeClass = 'bg-info'; break;
                                                        case 'Écriture': $badgeClass = 'bg-success'; break;
                                                        case 'Validation': $badgeClass = 'bg-warning'; break;
                                                        case 'Administration': $badgeClass = 'bg-danger'; break;
                                                    }
                                                ?>
                                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($droit['niveau']) ?></span>
                                            </td>
                                            <td><?= $droit['date_debut'] ? date('d/m/Y', strtotime($droit['date_debut'])) : '-' ?></td>
                                            <td><?= $droit['date_fin'] ? date('d/m/Y', strtotime($droit['date_fin'])) : '-' ?></td>
                                            <td>
                                                <?php if ($droit['est_actif']): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info edit-access" 
                                                        data-id="<?= $droit['id'] ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editAccessModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-access" 
                                                        data-id="<?= $droit['id'] ?>"
                                                        data-name="<?= htmlspecialchars($droit['nomUser']) ?>">
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

<!-- Modal Ajout Droit d'Accès -->
<div class="modal fade" id="addAccessModal" tabindex="-1" aria-labelledby="addAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/update_acces_caisse.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAccessModalLabel">Ajouter un droit d'accès</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for=idUser class="form-label">Utilisateur <span class="text-danger">*</span></label>
                        <select class="form-select" id=idUser name=idUser required>
                            <option value="">Sélectionner un utilisateur</option>
                            <?php foreach ($utilisateurs as $user): ?>
                                <option value="<?= $user['idUser'] ?>"><?= htmlspecialchars($user['nomUser']) ?> - <?= htmlspecialchars($user['agent_nom'] ?? 'N/A') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="entite_id" class="form-label">Caisse</label>
                        <select class="form-select" id="entite_id" name="entite_id">
                            <option value="">Toutes les caisses</option>
                            <?php foreach ($caisses as $caisse): ?>
                                <option value="<?= $caisse['id'] ?>"><?= htmlspecialchars($caisse['designation']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="niveau" class="form-label">Niveau d'accès <span class="text-danger">*</span></label>
                        <select class="form-select" id="niveau" name="niveau" required>
                            <option value="Lecture">Lecture seule</option>
                            <option value="Écriture">Écriture</option>
                            <option value="Validation">Validation</option>
                            <option value="Administration">Administration</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin" class="form-label">Date de fin (optionnel)</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="est_actif" name="est_actif" value="1" checked>
                        <label class="form-check-label" for="est_actif">Accès actif</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
                    </div>
                    
                    <input type="hidden" name="type" value="Caisse">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modification Droit d'Accès -->
<div class="modal fade" id="editAccessModal" tabindex="-1" aria-labelledby="editAccessModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/update_acces_caisse.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAccessModalLabel">Modifier un droit d'accès</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_idUser" class="form-label">Utilisateur <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_idUser" name=idUser required>
                        <?php foreach ($utilisateurs as $user): ?>
                                <option value="<?= $user['idUser'] ?>"><?= htmlspecialchars($user['nomUser']) ?> - <?= htmlspecialchars($user['agent_nom'] ?? 'N/A') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_entite_id" class="form-label">Caisse</label>
                        <select class="form-select" id="edit_entite_id" name="entite_id">
                            <option value="">Toutes les caisses</option>
                            <?php foreach ($caisses as $caisse): ?>
                                <option value="<?= $caisse['id'] ?>"><?= htmlspecialchars($caisse['designation']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_niveau" class="form-label">Niveau d'accès <span class="text-danger">*</span></label>
                        <select class="form-select" id="edit_niveau" name="niveau" required>
                            <option value="Lecture">Lecture seule</option>
                            <option value="Écriture">Écriture</option>
                            <option value="Validation">Validation</option>
                            <option value="Administration">Administration</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_debut" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="edit_date_debut" name="date_debut">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_date_fin" class="form-label">Date de fin (optionnel)</label>
                                <input type="date" class="form-control" id="edit_date_fin" name="date_fin">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_est_actif" name="est_actif" value="1">
                        <label class="form-check-label" for="edit_est_actif">Accès actif</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="edit_commentaire" name="commentaire" rows="3"></textarea>
                    </div>
                    
                    <input type="hidden" name="type" value="Caisse">
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
    const editButtons = document.querySelectorAll('.edit-access');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Requête AJAX pour récupérer les données du droit d'accès
            fetch(`controller/get_acces_caisse.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // Remplir le formulaire avec les données
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_idUser').value = data.idUser;
                    document.getElementById('edit_entite_id').value = data.entite_id || '';
                    document.getElementById('edit_niveau').value = data.niveau;
                    
                    if (data.date_debut) {
                        document.getElementById('edit_date_debut').value = data.date_debut.split(' ')[0]; // Garder seulement la date
                    } else {
                        document.getElementById('edit_date_debut').value = '';
                    }
                    
                    if (data.date_fin) {
                        document.getElementById('edit_date_fin').value = data.date_fin.split(' ')[0];
                    } else {
                        document.getElementById('edit_date_fin').value = '';
                    }
                    
                    document.getElementById('edit_commentaire').value = data.commentaire || '';
                    document.getElementById('edit_est_actif').checked = data.est_actif === '1';
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des données:', error);
                    alert('Une erreur est survenue lors de la récupération des données du droit d\'accès.');
                });
        });
    });
    
    // Gestion de la suppression avec SweetAlert
    const deleteButtons = document.querySelectorAll('.delete-access');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: `Voulez-vous vraiment supprimer le droit d'accès pour "${name}" ? Cette action est irréversible.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_acces_caisse.php?id=${id}`;
                }
            });
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
