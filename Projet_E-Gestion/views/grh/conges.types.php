<?php include "./views/include/header.php"; 

// Initialiser les modèles nécessaires
$congeModel = new Conge();

// Récupérer tous les types de congés
$typesConges = $congeModel->getAllTypeConges();

// Vérifier si l'utilisateur est administrateur
$isAdmin = isset($_SESSION['idRole']) && ($_SESSION['idRole'] == 1 || $_SESSION['idRole'] == 3);
if (!$isAdmin) {
    header('Location: index');
    exit;
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Types de congés</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item">GRH</li>
                <li class="breadcrumb-item"><a href="grh/conges.list">Congés</a></li>
                <li class="breadcrumb-item active">Types de congés</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Liste des types de congés</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTypeCongeModal">
                                <i class="bi bi-plus-circle"></i> Ajouter un type
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Désignation</th>
                                        <th>Durée standard</th>
                                        <th>Cumulable</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($typesConges)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Aucun type de congé défini</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($typesConges as $type): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($type['designation']) ?></td>
                                                <td>
                                                    <?php if ($type['duree_standard']): ?>
                                                        <?= $type['duree_standard'] ?> jours
                                                    <?php else: ?>
                                                        <span class="text-muted">Non défini</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($type['est_cumulable']): ?>
                                                        <span class="badge bg-success">Oui</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($type['description'] ?? 'Aucune description') ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary edit-type-conge" 
                                                            data-id="<?= $type['idtype_conge'] ?>"
                                                            data-designation="<?= htmlspecialchars($type['designation']) ?>"
                                                            data-duree="<?= $type['duree_standard'] ?>"
                                                            data-cumulable="<?= $type['est_cumulable'] ?>"
                                                            data-description="<?= htmlspecialchars($type['description'] ?? '') ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger delete-type-conge" data-id="<?= $type['idtype_conge'] ?>">
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

<!-- Modal pour ajouter un type de congé -->
<div class="modal fade" id="addTypeCongeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addTypeCongeForm" action="controller/create_type_conge.php" method="post" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un type de congé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="designation" name="designation" required>
                        <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                    </div>
                    <div class="mb-3">
                        <label for="duree_standard" class="form-label">Durée standard (jours)</label>
                        <input type="number" class="form-control" id="duree_standard" name="duree_standard" min="0">
                        <small class="text-muted">Laisser vide si la durée est variable</small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="est_cumulable" name="est_cumulable" value="1">
                            <label class="form-check-label" for="est_cumulable">
                                Cumulable d'une année à l'autre
                            </label>
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

<!-- Modal pour modifier un type de congé -->
<div class="modal fade" id="editTypeCongeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTypeCongeForm" action="controller/update_type_conge.php" method="post" class="needs-validation" novalidate>
                <input type="hidden" name="idTypeConge" id="edit_idTypeConge">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier un type de congé</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_designation" name="designation" required>
                        <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_duree_standard" class="form-label">Durée standard (jours)</label>
                        <input type="number" class="form-control" id="edit_duree_standard" name="duree_standard" min="0">
                        <small class="text-muted">Laisser vide si la durée est variable</small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_est_cumulable" name="est_cumulable" value="1">
                            <label class="form-check-label" for="edit_est_cumulable">
                                Cumulable d'une année à l'autre
                            </label>
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
    // Validation des formulaires
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Gestion du modal d'édition
    const editBtns = document.querySelectorAll('.edit-type-conge');
    const editModal = new bootstrap.Modal(document.getElementById('editTypeCongeModal'));
    
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const designation = this.getAttribute('data-designation');
            const duree = this.getAttribute('data-duree');
            const cumulable = this.getAttribute('data-cumulable') === '1';
            const description = this.getAttribute('data-description');
            
            document.getElementById('edit_idTypeConge').value = id;
            document.getElementById('edit_designation').value = designation;
            document.getElementById('edit_duree_standard').value = duree || '';
            document.getElementById('edit_est_cumulable').checked = cumulable;
            document.getElementById('edit_description').value = description;
            
            editModal.show();
        });
    });
    
    // Gestion de la suppression
    const deleteBtns = document.querySelectorAll('.delete-type-conge');
    
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Cette action supprimera définitivement ce type de congé!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `controller/delete_type_conge.php?id=${id}`;
                }
            });
        });
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
