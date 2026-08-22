<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer toutes les catégories de budget
$stmt = $connexion->query("
    SELECT c.*, p.designation as parent_designation, pc.code as compte_code, pc.designation as compte_designation
    FROM categories_budget c
    LEFT JOIN categories_budget p ON c.parent_id = p.id
    LEFT JOIN plan_comptable pc ON c.compte_comptable_id = pc.id
    ORDER BY c.type, c.niveau, c.code
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les comptes du plan comptable
$stmt = $connexion->query("SELECT id, code, designation, type FROM plan_comptable ORDER BY code");
$comptes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages d'alerte s'ils existent
$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$messageType = isset($_SESSION['messageType']) ? $_SESSION['messageType'] : '';

// Effacer les messages après les avoir récupérés
unset($_SESSION['message']);
unset($_SESSION['messageType']);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Catégories Budgétaires</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=index">Accueil</a></li>
                <li class="breadcrumb-item">Finance</li>
                <li class="breadcrumb-item active">Catégories Budgétaires</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex justify-content-between align-items-center">
                            Liste des Catégories Budgétaires
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="bi bi-plus-circle"></i> Nouvelle Catégorie
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
                                        <th>Code</th>
                                        <th>Désignation</th>
                                        <th>Type</th>
                                        <th>Niveau</th>
                                        <th>Catégorie Parent</th>
                                        <th>Compte Comptable</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Aucune catégorie budgétaire enregistrée</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $categorie): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($categorie['code']) ?></td>
                                            <td>
                                                <?php if ($categorie['niveau'] > 1): ?>
                                                    <?= str_repeat('    ', $categorie['niveau'] - 1) ?>
                                                    <i class="bi bi-arrow-return-right"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($categorie['designation']) ?>
                                            </td>
                                            <td>
                                                <?php if ($categorie['type'] == 'Recette'): ?>
                                                    <span class="badge bg-success">Recette</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Dépense</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $categorie['niveau'] ?></td>
                                            <td><?= $categorie['parent_designation'] ? htmlspecialchars($categorie['parent_designation']) : '-' ?></td>
                                            <td><?= $categorie['compte_code'] ? htmlspecialchars($categorie['compte_code'] . ' - ' . $categorie['compte_designation']) : '-' ?></td>
                                            <td>
                                                <?php if ($categorie['est_actif']): ?>
                                                    <span class="badge bg-success">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info edit-category" 
                                                        data-id="<?= $categorie['id'] ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editCategoryModal">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger delete-category" 
                                                        data-id="<?= $categorie['id'] ?>"
                                                        data-name="<?= htmlspecialchars($categorie['designation']) ?>">
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

<!-- Modal Ajout Catégorie Budgétaire -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/update_categorie_budget.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">Ajouter une catégorie budgétaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code" name="code" required maxlength="20">
                                <small class="text-muted">Le code doit être unique (ex: R01, D01.01)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="designation" name="designation" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="Recette">Recette</option>
                                    <option value="Dépense">Dépense</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="parent_id" class="form-label">Catégorie parent</label>
                                <select class="form-select" id="parent_id" name="parent_id">
                                    <option value="">-- Aucun parent (catégorie principale) --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" data-type="<?= $cat['type'] ?>" data-niveau="<?= $cat['niveau'] ?>"
                                                <?= ($cat['niveau'] >= 3) ? 'disabled' : '' ?>>
                                            <?= str_repeat('    ', $cat['niveau'] - 1) ?>
                                            <?= htmlspecialchars($cat['code'] . ' - ' . $cat['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Seules les catégories de niveaux 1 et 2 peuvent avoir des sous-catégories</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="compte_comptable_id" class="form-label">Compte comptable</label>
                                <select class="form-select" id="compte_comptable_id" name="compte_comptable_id">
                                    <option value="">-- Sélectionner un compte (optionnel) --</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= $compte['id'] ?>" data-type="<?= $compte['type'] ?>">
                                            <?= htmlspecialchars($compte['code'] . ' - ' . $compte['designation']) ?>
                                            <?= $compte['type'] ? ' (' . $compte['type'] . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" value="1" checked>
                                <label class="form-check-label" for="est_actif">
                                    Catégorie active
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

<!-- Modal Modification Catégorie Budgétaire -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="controller/update_categorie_budget.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Modifier une catégorie budgétaire</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                            <label for="edit_code" class="form-label">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_code" name="code" required maxlength="20">
                                <small class="text-muted">Le code doit être unique (ex: R01, D01.01)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_designation" name="designation" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_type" class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_type" name="type" required>
                                    <option value="Recette">Recette</option>
                                    <option value="Dépense">Dépense</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_parent_id" class="form-label">Catégorie parent</label>
                                <select class="form-select" id="edit_parent_id" name="parent_id">
                                    <option value="">-- Aucun parent (catégorie principale) --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" data-type="<?= $cat['type'] ?>" data-niveau="<?= $cat['niveau'] ?>">
                                            <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $cat['niveau'] - 1) ?>
                                            <?= htmlspecialchars($cat['code'] . ' - ' . $cat['designation']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Seules les catégories de niveaux 1 et 2 peuvent avoir des sous-catégories</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="edit_compte_comptable_id" class="form-label">Compte comptable</label>
                                <select class="form-select" id="edit_compte_comptable_id" name="compte_comptable_id">
                                    <option value="">-- Sélectionner un compte (optionnel) --</option>
                                    <?php foreach ($comptes as $compte): ?>
                                        <option value="<?= $compte['id'] ?>" data-type="<?= $compte['type'] ?>">
                                            <?= htmlspecialchars($compte['code'] . ' - ' . $compte['designation']) ?>
                                            <?= $compte['type'] ? ' (' . $compte['type'] . ')' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="edit_est_actif" name="est_actif" value="1">
                                <label class="form-check-label" for="edit_est_actif">
                                    Catégorie active
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
    // Filtrer les options de parent en fonction du type sélectionné
    document.getElementById('type').addEventListener('change', function() {
        filterParentOptions(this.value, 'parent_id');
    });
    
    document.getElementById('edit_type').addEventListener('change', function() {
        filterParentOptions(this.value, 'edit_parent_id');
    });
    
    // Fonction pour filtrer les options de parent
    function filterParentOptions(selectedType, selectId) {
        const parentSelect = document.getElementById(selectId);
        const options = parentSelect.options;
        
        for (let i = 0; i < options.length; i++) {
            if (i === 0) continue; // Skip the "None" option
            
            const optionType = options[i].getAttribute('data-type');
            const optionNiveau = parseInt(options[i].getAttribute('data-niveau') || 1);
            
            if (optionType !== selectedType || optionNiveau >= 3) {
                options[i].style.display = 'none';
                
                // Si l'option actuellement sélectionnée devient invisible, désélectionner
                if (options[i].selected) {
                    options[0].selected = true;
                }
            } else {
                options[i].style.display = '';
            }
        }
    }
    
    // Gestion du chargement des données pour l'édition
    const editButtons = document.querySelectorAll('.edit-category');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Requête AJAX pour récupérer les données du compte
            fetch(`controller/get_categorie_budget.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // Remplir le formulaire avec les données
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_code').value = data.code;
                    document.getElementById('edit_designation').value = data.designation;
                    document.getElementById('edit_type').value = data.type;
                    document.getElementById('edit_description').value = data.description || '';
                    
                    // Sélectionner le parent
                    const parentSelect = document.getElementById('edit_parent_id');
                    parentSelect.value = data.parent_id || '';
                    
                    // Sélectionner le compte comptable
                    const compteSelect = document.getElementById('edit_compte_comptable_id');
                    compteSelect.value = data.compte_comptable_id || '';
                    
                    // Cocher si actif
                    document.getElementById('edit_est_actif').checked = data.est_actif === '1';
                    
                    // Filtrer les options de parent
                    filterParentOptions(data.type, 'edit_parent_id');
                    
                    // Désactiver l'option du parent qui est la catégorie elle-même
                    Array.from(parentSelect.options).forEach(option => {
                        if (option.value === data.id) {
                            option.disabled = true;
                        } else {
                            option.disabled = false;
                        }
                    });
                })
                .catch(error => {
                    console.error('Erreur lors de la récupération des données:', error);
                    alert('Une erreur est survenue lors de la récupération des données de la catégorie.');
                });
        });
    });
    
    // Filtrer initialement les options de parent
    filterParentOptions(document.getElementById('type').value, 'parent_id');
    
    // Gestion de la suppression avec SweetAlert
    const deleteButtons = document.querySelectorAll('.delete-category');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: `Voulez-vous vraiment supprimer la catégorie "${name}" ? Cette action est irréversible.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_categorie_budget.php?id=${id}`;
                }
            });
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
