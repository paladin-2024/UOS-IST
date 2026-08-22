<?php
include "./views/include/header.php";

$structureModel = new Structure();
$userId = $_SESSION['id']; // Assuming the user ID is stored in the session

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch categories the user has access to
$categories = $structureModel->getCategoriesByUserAccess($userId,$search);

// Fetch structures for dropdown
$structures = $structureModel->getStructuresByUserAccess($userId);

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Gestion des Catégories de Documents</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Classeur Numérique</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Catégories</h5>

                        <!-- Add Category Button -->
                        <button type="button" class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <span class="bi bi-plus-circle"></span> Ajouter une Catégorie
                        </button>

                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="document/categorie.add">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par designation...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="categoryTable">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Description</th>
                                    <th>Date de Création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasResults = false;
                                foreach ($categories as $category) {
                                    $dateCreation = date('d/m/Y', strtotime($category['date_creation']));
                                    $hasResults = true;
                                    echo "<tr>
                                        <td>" . htmlspecialchars($category['nom']) . "</td>
                                        <td>" . htmlspecialchars($category['description']) . "</td>
                                        <td>{$dateCreation}</td>
                                        <td>
                                            <button class='btn btn-sm btn-warning' onclick='editCategory(" . 
                                                $category['id_categorie'] . ", \"" . 
                                                addslashes(htmlspecialchars($category['nom'])) . "\", \"" . 
                                                addslashes(htmlspecialchars($category['description'])) . "\", " . 
                                                $category['idStructure'] . 
                                            ")'>
                                                <i class='bi bi-pencil-square'></i> Modifier
                                            </button>
                                            <button type='button' class='btn btn-danger btn-sm' onclick='confirmDeleteCategory(" . $category['id_categorie'] . ")'>
                                                <i class='bi bi-trash'></i>
                                            </button>
                                        </td>
                                    </tr>";
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='4' class='text-center'>Aucune catégorie trouvée</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Add Category Modal -->
                        <div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addCategoryModalLabel">Ajouter une Catégorie</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="addCategoryForm" method="POST" action="controller/addCategory.php">
                                            <div class="mb-3">
                                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="nom" name="nom" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="description" class="form-label">Description</label>
                                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label for="structure" class="form-label">Campus <span class="text-danger">*</span></label>
                                                <select class="form-select" id="structure" name="idStructure" required>
                                                    <option value="">Sélectionner un campus</option>
                                                    <?php foreach ($structures as $structure): ?>
                                                        <option value="<?= $structure['idStructure'] ?>"><?= htmlspecialchars($structure['designation']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary"><span class="bi bi-save"></span> Enregistrer</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Modifier la Catégorie</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editCategoryForm" method="POST" action="controller/updateCategory.php">
                        <input type="hidden" id="edit_id_categorie" name="id_categorie">
                        <div class="mb-3">
                            <label for="edit_nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_nom" name="nom" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_structure" class="form-label">Campus <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_structure" name="idStructure" required>
                                <option value="">Sélectionner un campus</option>
                                <?php foreach ($structures as $structure): ?>
                                    <option value="<?= $structure['idStructure'] ?>"><?= htmlspecialchars($structure['designation']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
function editCategory(categoryId, nom, description, structure) {
    // Nettoyer et échapper les valeurs
    const cleanNom = nom.replace(/"/g, '&quot;');
    const cleanDescription = description.replace(/"/g, '&quot;');
    
    // Remplir le modal avec les données
    document.getElementById('edit_id_categorie').value = categoryId;
    document.getElementById('edit_nom').value = cleanNom;
    document.getElementById('edit_description').value = cleanDescription;
    document.getElementById('edit_structure').value = structure;

    // Afficher le modal
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

function confirmDeleteCategory(categoryId) {
    Swal.fire({
        title: 'Êtes-vous sûr ?',
        text: "Cette action est irréversible !",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Oui, supprimer!',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'controller/deleteCategory.php?id=' + categoryId;
        }
    });
}
</script>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>