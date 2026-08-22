<?php
include "./views/include/header.php";

// Initialisation de la connexion à la base de données
$connexion = Connexion::getInstance()->getPDO();

// Récupérer la liste des documents obligatoires
$stmt = $connexion->query("
    SELECT * FROM documents_obligatoires
    ORDER BY cycle, designation
");
$documentsObligatoires = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gestion de la pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 10;
$total = count($documentsObligatoires);
$pages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$documentsPage = array_slice($documentsObligatoires, $offset, $perPage);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Gestion des Documents Obligatoires</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=dashboard">Accueil</a></li>
                <li class="breadcrumb-item">Enseignement</li>
                <li class="breadcrumb-item active">Documents Obligatoires</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title">Liste des Documents Obligatoires</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDocumentObligatoireModal">
                                <i class="bi bi-plus-circle me-1"></i> Ajouter un document
                            </button>
                        </div>

                        <?php if (empty($documentsObligatoires)): ?>
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle me-2"></i>
                                Aucun document obligatoire n'est défini. Commencez par en ajouter un.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Désignation</th>
                                            <th>Description</th>
                                            <th>Cycle</th>
                                            <th>Obligatoire</th>
                                            <th>Délai (jours)</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documentsPage as $index => $doc): ?>
                                            <tr>
                                                <td><?= $offset + $index + 1 ?></td>
                                                <td><?= htmlspecialchars($doc['designation']) ?></td>
                                                <td><?= htmlspecialchars($doc['description'] ?? '') ?></td>
                                                <td>
                                                    <?php 
                                                    switch($doc['cycle']) {
                                                        case 'Premier': echo '<span class="badge bg-primary">1er Cycle</span>'; break;
                                                        case 'Deuxieme': echo '<span class="badge bg-success">2ème Cycle</span>'; break;
                                                        case 'Troisieme': echo '<span class="badge bg-info">3ème Cycle</span>'; break;
                                                        default: echo '<span class="badge bg-secondary">Tous</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= $doc['est_obligatoire'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>' ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= $doc['delai_jours'] ? $doc['delai_jours'] . ' jours' : '-' ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editDocumentObligatoireModal" 
                                                            data-id="<?= $doc['id'] ?>" 
                                                            data-designation="<?= htmlspecialchars($doc['designation']) ?>" 
                                                            data-description="<?= htmlspecialchars($doc['description'] ?? '') ?>"
                                                            data-cycle="<?= $doc['cycle'] ?>"
                                                            data-obligatoire="<?= $doc['est_obligatoire'] ?>"
                                                            data-delai="<?= $doc['delai_jours'] ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger" onclick="confirmDeleteDocumentObligatoire(<?= $doc['id'] ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($pages > 1): ?>
                                <nav>
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?view=enseignement/documents_obligatoires&page=<?= $page - 1 ?>" aria-label="Précédent">
                                                <span aria-hidden="true">«</span>
                                            </a>
                                        </li>
                                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                                <a class="page-link" href="?view=enseignement/documents_obligatoires&page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?= ($page >= $pages) ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?view=enseignement/documents_obligatoires&page=<?= $page + 1 ?>" aria-label="Suivant">
                                                <span aria-hidden="true">»</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour ajouter un document obligatoire -->
<div class="modal fade" id="addDocumentObligatoireModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un document obligatoire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/ajouter_document_obligatoire.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="designation" class="form-label">Désignation</label>
                        <input type="text" class="form-control" id="designation" name="designation" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cycle" class="form-label">Cycle concerné</label>
                        <select class="form-select" id="cycle" name="cycle" required>
                            <option value="Tous">Tous les cycles</option>
                            <option value="Premier">1er Cycle</option>
                            <option value="Deuxieme">2ème Cycle</option>
                            <option value="Troisieme">3ème Cycle</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="est_obligatoire" name="est_obligatoire" value="1" checked>
                        <label class="form-check-label" for="est_obligatoire">Document obligatoire</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="delai_jours" class="form-label">Délai de fourniture (jours)</label>
                        <input type="number" class="form-control" id="delai_jours" name="delai_jours" min="0">
                        <div class="form-text">Laissez vide si aucun délai spécifique.</div>
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

<!-- Modal pour modifier un document obligatoire -->
<div class="modal fade" id="editDocumentObligatoireModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un document obligatoire</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="controller/modifier_document_obligatoire.php" method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Désignation</label>
                        <input type="text" class="form-control" id="edit_designation" name="designation" required>
                        </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_cycle" class="form-label">Cycle concerné</label>
                        <select class="form-select" id="edit_cycle" name="cycle" required>
                            <option value="Tous">Tous les cycles</option>
                            <option value="Premier">1er Cycle</option>
                            <option value="Deuxieme">2ème Cycle</option>
                            <option value="Troisieme">3ème Cycle</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_est_obligatoire" name="est_obligatoire" value="1">
                        <label class="form-check-label" for="edit_est_obligatoire">Document obligatoire</label>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_delai_jours" class="form-label">Délai de fourniture (jours)</label>
                        <input type="number" class="form-control" id="edit_delai_jours" name="delai_jours" min="0">
                        <div class="form-text">Laissez vide si aucun délai spécifique.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion du modal d'édition
    const editModal = document.getElementById('editDocumentObligatoireModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const designation = button.getAttribute('data-designation');
            const description = button.getAttribute('data-description');
            const cycle = button.getAttribute('data-cycle');
            const estObligatoire = button.getAttribute('data-obligatoire');
            const delai = button.getAttribute('data-delai');
            
            // Mettre à jour les champs du formulaire
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_designation').value = designation;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_cycle').value = cycle;
            document.getElementById('edit_est_obligatoire').checked = (estObligatoire === '1');
            document.getElementById('edit_delai_jours').value = delai || '';
        });
    }
});

// Fonction pour confirmer la suppression
function confirmDeleteDocumentObligatoire(id) {
    Swal.fire({
        title: 'Êtes-vous sûr?',
        text: "Cette action supprimera définitivement ce document obligatoire!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `controller/supprimer_document_obligatoire.php?id=${id}`;
        }
    });
}
</script>

<?php include "./views/include/footer.php"; ?>
