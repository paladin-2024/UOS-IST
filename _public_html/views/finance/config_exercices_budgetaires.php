<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer tous les exercices budgétaires
$stmt = $connexion->query("
    SELECT * FROM exercices_budgetaires 
    ORDER BY date_debut DESC
");
$exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'alerte s'ils existent
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['message']);
unset($_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Exercices Budgétaires</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Exercices Budgétaires</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Liste des Exercices Budgétaires
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExerciceModal">
                                <i class="bi bi-plus-circle"></i> Nouvel Exercice
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
                                        <th>Période</th>
                                        <th>Statut</th>
                                        <th>Date de création</th>
                                        <th>Commentaire</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($exercices)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucun exercice budgétaire enregistré</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($exercices as $exercice): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($exercice['designation']) ?></td>
                                            <td>
                                                <?= date('d/m/Y', strtotime($exercice['date_debut'])) ?> - 
                                                <?= date('d/m/Y', strtotime($exercice['date_fin'])) ?>
                                            </td>
                                            <td>
                                                <?php if ($exercice['est_cloture']): ?>
                                                    <span class="badge bg-secondary">Clôturé</span>
                                                <?php elseif ($exercice['est_actif']): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($exercice['date_creation'])) ?></td>
                                            <td><?= htmlspecialchars(substr($exercice['commentaire'] ?? '', 0, 50)) ?><?= strlen($exercice['commentaire'] ?? '') > 50 ? '...' : '' ?></td>
                                            <td>
                                                <a href="?view=finance/config_budget&exercice_id=<?= $exercice['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-calculator"></i> Budget
                                                </a>
                                                
                                                <button type="button" class="btn btn-sm btn-primary edit-exercice" 
                                                        data-id="<?= $exercice['id'] ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editExerciceModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                
                                                <?php if (!$exercice['est_cloture']): ?>
                                                    <?php if ($exercice['est_actif']): ?>
                                                        <button type="button" class="btn btn-sm btn-warning toggle-status" 
                                                                data-id="<?= $exercice['id'] ?>"
                                                                data-action="desactiver">
                                                            <i class="bi bi-toggle-off"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-success toggle-status" 
                                                                data-id="<?= $exercice['id'] ?>"
                                                                data-action="activer">
                                                            <i class="bi bi-toggle-on"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn btn-sm btn-secondary cloturer-exercice" 
                                                            data-id="<?= $exercice['id'] ?>"
                                                            data-designation="<?= htmlspecialchars($exercice['designation']) ?>">
                                                        <i class="bi bi-lock"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (!$exercice['est_actif'] && !$exercice['est_cloture']): ?>
                                                    <button type="button" class="btn btn-sm btn-danger delete-exercice" 
                                                            data-id="<?= $exercice['id'] ?>"
                                                            data-designation="<?= htmlspecialchars($exercice['designation']) ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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

<!-- Modal Ajout Exercice Budgétaire -->
<div class="modal fade" id="addExerciceModal" tabindex="-1" aria-labelledby="addExerciceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
        <form action="controller/update_exercice_budgetaire.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addExerciceModalLabel">Ajouter un exercice budgétaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="designation" name="designation" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_fin" class="form-label">Date de fin <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin" required>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" value="1">
                        <label class="form-check-label" for="est_actif">
                            Exercice actif
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"></textarea>
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

<!-- Modal Modification Exercice Budgétaire -->
<div class="modal fade" id="editExerciceModal" tabindex="-1" aria-labelledby="editExerciceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="controller/update_exercice_budgetaire.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editExerciceModalLabel">Modifier un exercice budgétaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_designation" name="designation" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date_debut" class="form-label">Date de début <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="edit_date_debut" name="date_debut" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_date_fin" class="form-label">Date de fin <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="edit_date_fin" name="date_fin" required>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_est_actif" name="est_actif" value="1">
                        <label class="form-check-label" for="edit_est_actif">
                            Exercice actif
                        </label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="edit_commentaire" name="commentaire" rows="3"></textarea>
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
    const editButtons = document.querySelectorAll('.edit-exercice');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Requête AJAX pour récupérer les données de l'exercice
            fetch(`controller/get_exercice_budgetaire.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // Remplir le formulaire avec les données
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_designation').value = data.designation;
                    document.getElementById('edit_date_debut').value = data.date_debut.split(' ')[0]; // Garder seulement la date
                    document.getElementById('edit_date_fin').value = data.date_fin.split(' ')[0];
                    document.getElementById('edit_est_actif').checked = data.est_actif === '1';
                    document.getElementById('edit_commentaire').value = data.commentaire || '';
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des données:', error);
                    alert('Une erreur est survenue lors de la récupération des données de l\'exercice.');
                });
        });
    });
    
    // Gestion de l'activation/désactivation d'un exercice
    const toggleButtons = document.querySelectorAll('.toggle-status');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const action = this.getAttribute('data-action');
            
            let message = action === 'activer' 
                ? "Voulez-vous vraiment activer cet exercice ? Cela désactivera tout autre exercice actif."
                : "Voulez-vous vraiment désactiver cet exercice ?";
            
            Swal.fire({
                title: 'Confirmation',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui',
                cancelButtonText: 'Non'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/toggle_exercice_budgetaire.php?id=${id}&action=${action}`;
                }
            });
        });
    });
    
    // Gestion de la clôture d'un exercice
    const cloturerButtons = document.querySelectorAll('.cloturer-exercice');
    cloturerButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const designation = this.getAttribute('data-designation');
            
            Swal.fire({
                title: 'Clôturer l\'exercice',
                text: `Voulez-vous vraiment clôturer l'exercice "${designation}" ? Cette action est irréversible.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, clôturer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/cloturer_exercice_budgetaire.php?id=${id}`;
                }
            });
        });
    });
    
    // Gestion de la suppression
    const deleteButtons = document.querySelectorAll('.delete-exercice');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const designation = this.getAttribute('data-designation');
            
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: `Voulez-vous vraiment supprimer l'exercice "${designation}" ? Cette action est irréversible.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_exercice_budgetaire.php?id=${id}`;
                }
            });
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
