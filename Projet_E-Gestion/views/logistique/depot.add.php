<?php
include "./views/include/header.php";
$structure = new Structure();
$userId = $_SESSION['id'];

$structures = $structure->getStructures();

// Handle search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$depots = $structure->getDepotsByStructure($userId, $searchQuery, 20);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES DÉPÔTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Gestion des Dépôts</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addDepotModal">
                    <i class="bi bi-plus"></i> Nouveau Dépôt
                </button>
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un dépôt..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Dépôts</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Désignation</th>
                                    <th scope="col">Adresse</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="depotTableBody">
                                <?php
                                $i = 1;
                                foreach ($depots as $depot) {
                                    echo "
                                    <tr>
                                        <td>{$i}</td>
                                        <td>{$depot['designation']}</td>
                                        <td>{$depot['adresse']}</td>
                                        <td>{$depot['typeDepot']}</td>
                                        <td>
                                            <button class='btn btn-sm btn-warning' onclick='editDepot(
                                                {$depot['idDepot']}, 
                                                \"{$depot['designation']}\",
                                                \"{$depot['adresse']}\",
                                                \"{$depot['typeDepot']}\",
                                                \"{$depot['Structure_idStructure']}\"
                                            )'>
                                                <i class='bi bi-pencil-square'></i> Modifier
                                            </button>

                                            <button class='btn btn-sm btn-info' data-bs-toggle='collapse' 
                                                data-bs-target='#collapseUsers{$depot['idDepot']}'>
                                                <i class='bi bi-people'></i> Utilisateurs
                                            </button>

                                            <button class='btn btn-sm btn-success' data-bs-toggle='modal' 
                                                data-bs-target='#modalAddUser{$depot['idDepot']}'>
                                                <i class='bi bi-person-plus'></i> Ajouter Utilisateur
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class='collapse-row'>
                                        <td colspan='5' class='p-0'>
                                            <div class='collapse' id='collapseUsers{$depot['idDepot']}'>
                                                <table class='table table-sm table-bordered m-0'>
                                                    <thead>
                                                        <tr>
                                                            <th>Nom utilisateur</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>";
                                    
                                    $users = $structure->getUsersByDepot($depot['idDepot']);
                                    foreach ($users as $user) {
                                        echo "
                                        <tr>
                                            <td>{$user['nomUser']}</td>
                                            <td>
                                                <a class='btn btn-sm btn-danger' onclick='confirmDeleteUser({$user['iduser_depot']})'>
                                                    <i class='bi bi-trash'></i> Supprimer
                                                </a>
                                            </td>
                                        </tr>";
                                    }
                                    echo "
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>";
                                    $i++;
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal for adding a depot -->
<div class="modal fade" id="addDepotModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Dépôt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addDepotForm" action="controller/create_depot.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="designation" name="designation" required>
                        </div>
                        <div class="col-md-6">
                            <label for="adresse" class="form-label">Adresse <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="adresse" name="adresse" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="typeDepot" class="form-label">Type de Dépôt <span class="text-danger">*</span></label>
                            <select class="form-select" id="typeDepot" name="typeDepot" required>
                                <option value="">Sélectionner un type</option>
                                <option value="Principal">Principal</option>
                                <option value="Secondaire">Secondaire</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="Structure_idStructure" class="form-label">Structure <span class="text-danger">*</span></label>
                            <select class="form-select" id="Structure_idStructure" name="Structure_idStructure" required>
                                <option value="">Sélectionner une structure</option>
                                <?php foreach ($structures as $struct): ?>
                                    <?php
                                    $ver1 = $structure->getUserPermissionStructure($userId, $struct['idStructure']);
                                    if ($ver1->fetch()):
                                    ?>
                                        <option value="<?= $struct['idStructure'] ?>"><?= $struct['designation'] ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Ajouter
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for editing a depot -->
<div class="modal fade" id="editDepotModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Dépôt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_depot.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idDepot" id="editDepotId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDepotDesignation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" id="editDepotDesignation" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editDepotAdresse" class="form-label">Adresse <span class="text-danger">*</span></label>
                            <input type="text" name="adresse" id="editDepotAdresse" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDepotType" class="form-label">Type de Dépôt <span class="text-danger">*</span></label>
                            <select class="form-select" id="editDepotType" name="typeDepot" required>
                                <option value="Principal">Principal</option>
                                <option value="Secondaire">Secondaire</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editStructure_idStructure" class="form-label">Structure <span class="text-danger">*</span></label>
                            <select class="form-select" id="editStructure_idStructure" name="Structure_idStructure" required>
                                <option value="">Sélectionner une structure</option>
                                <?php foreach ($structures as $struct): ?>
                                    <?php
                                    $ver1 = $structure->getUserPermissionStructure($userId, $struct['idStructure']);
                                    if ($ver1->fetch()):
                                    ?>
                                        <option value="<?= $struct['idStructure'] ?>"><?= $struct['designation'] ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editDepotBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding users to depot -->
<?php foreach ($depots as $depot): ?>
<div class="modal fade" id="modalAddUser<?php echo $depot['idDepot']; ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un utilisateur au dépôt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/add_user_to_depot.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idDepot" value="<?php echo $depot['idDepot']; ?>">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="userId" class="form-label">Utilisateur <span class="text-danger">*</span></label>
                            <select name="userId" class="form-select" required>
                                <option value="">Sélectionner un utilisateur</option>
                                <?php
                                $allUsers = $structure->getUsers();
                                foreach ($allUsers as $user) {
                                    echo "<option value='{$user['idUser']}'>{$user['nomUser']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addUserBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function editDepot(id, designation, adresse, type, structureId) {
    document.getElementById('editDepotId').value = id;
    document.getElementById('editDepotDesignation').value = designation;
    document.getElementById('editDepotAdresse').value = adresse;
    document.getElementById('editDepotType').value = type;
    document.getElementById('editStructure_idStructure').value = structureId;

    const modal = new bootstrap.Modal(document.getElementById('editDepotModal'));
    modal.show();
}

function confirmDeleteUser(id_user_depot) {
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
            window.location.href = 'controller/deleteUserDepot.php?id=' + id_user_depot;
        }
    })
}

document.getElementById('searchInput').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        const searchValue = this.value.trim();
        window.location.href = 'logistique/depot.add?search=' + encodeURIComponent(searchValue);
    }
});
</script>

<?php include "./views/include/footer.php"; ?>