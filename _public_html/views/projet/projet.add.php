<?php
include "./views/include/header.php";
$structure = new Structure();

$projet = new Projet();
$userId = $_SESSION['id'];

$structures = $structure->getStructuresByUserAccess($userId);

// Handle search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$projects = $projet->getProjetByUserStructure($userId, $searchQuery, 20);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES PROJETS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Gestion des Projets</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                    <i class="bi bi-plus"></i> Nouveau Projet
                </button>
                <form method="GET" action="" class="mb-3">
                    <div class="input-group">
                        <input type="hidden" name="view" value="projet/projet.add">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control" placeholder="Rechercher un projet...">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Projets</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Nom</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Date Début</th>
                                    <th scope="col">Date Fin</th>
                                    <th scope="col">Statut</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="projectTableBody">
                                <?php
                                $i = 1;
                                foreach ($projects as $project) {
                                    $dd=date('d/m/Y',strtotime($project['dateDebut']));
                                    $df=date('d/m/Y',strtotime($project['dateFin']));
                                    echo "
                                    <tr>
                                        <td>{$i}</td>
                                        <td>{$project['nomProjet']}</td>
                                        <td>{$project['description']}</td>
                                        <td>{$dd}</td>
                                        <td>{$df}</td>
                                        <td>{$project['statut']}</td>
                                        <td>
                                            <button class='btn btn-sm btn-warning' onclick='editProject(
                                                {$project['idProjet']}, 
                                                \"{$project['nomProjet']}\",
                                                \"{$project['description']}\",
                                                \"{$project['dateDebut']}\",
                                                \"{$project['dateFin']}\",
                                                \"{$project['statut']}\",
                                                \"{$project['Structure_idStructure']}\"
                                            )'>
                                                <i class='bi bi-pencil-square'></i>
                                            </button>

                                            <button class='btn btn-sm btn-info' data-bs-toggle='collapse' 
                                                data-bs-target='#collapseUsers{$project['idProjet']}'>
                                                <i class='bi bi-people'></i>
                                            </button>

                                            <button class='btn btn-sm btn-success' data-bs-toggle='modal' 
                                                data-bs-target='#modalAddUser{$project['idProjet']}'>
                                                <i class='bi bi-person-plus'></i> Utilisateur
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class='collapse-row'>
                                        <td colspan='7' class='p-0'>
                                            <div class='collapse' id='collapseUsers{$project['idProjet']}'>
                                                <table class='table table-sm table-bordered m-0'>
                                                    <thead>
                                                        <tr>
                                                            <th>Nom utilisateur</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>";
                                    
                                    $users = $projet->getUsersByProject($project['idProjet']);
                                    foreach ($users as $user) {
                                        echo "
                                        <tr>
                                            <td>{$user['nomUser']}</td>
                                            <td>
                                                <a class='btn btn-sm btn-danger' onclick='confirmDeleteUser({$user['iduser_projet']})'>
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

<!-- Modal for adding a project -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Projet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addProjectForm" action="controller/create_project.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nomProjet" class="form-label">Nom du Projet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nomProjet" name="nomProjet" required>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="description" name="description" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="dateDebut" class="form-label">Date de Début <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateDebut" name="dateDebut" required>
                        </div>
                        <div class="col-md-6">
                            <label for="dateFin" class="form-label">Date de Fin <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateFin" name="dateFin" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="statut" class="form-label">Statut <span class="text-danger">*</span></label>
                            <select class="form-select" id="statut" name="statut" required>
                                <option value="">Sélectionner un statut</option>
                                <option value="En cours">En cours</option>
                                <option value="Terminé">Terminé</option>
                                <option value="Suspendu">Suspendu</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="Structure_idStructure" class="form-label">Structure <span class="text-danger">*</span></label>
                            <select class="form-select" id="Structure_idStructure" name="Structure_idStructure" required>
                                <option value="">Sélectionner une structure</option>
                                <?php foreach ($structures as $struct): ?>
                                        <option value="<?= $struct['idStructure'] ?>"><?= $struct['designation'] ?></option>
                                   
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

<!-- Modal for editing a project -->
<div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Projet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_project.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idProjet" id="editProjectId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editProjectNom" class="form-label">Nom du Projet <span class="text-danger">*</span></label>
                            <input type="text" name="nomProjet" id="editProjectNom" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editProjectDescription" class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" name="description" id="editProjectDescription" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editProjectDateDebut" class="form-label">Date de Début <span class="text-danger">*</span></label>
                            <input type="date" name="dateDebut" id="editProjectDateDebut" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editProjectDateFin" class="form-label">Date de Fin <span class="text-danger">*</span></label>
                            <input type="date" name="dateFin" id="editProjectDateFin" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editProjectStatut" class="form-label">Statut <span class="text-danger">*</span></label>
                            <select class="form-select" id="editProjectStatut" name="statut" required>
                                <option value="En cours">En cours</option>
                                <option value="Terminé">Terminé</option>
                                <option value="Suspendu">Suspendu</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editStructure_idStructure" class="form-label">Structure <span class="text-danger">*</span></label>
                            <select class="form-select" id="editStructure_idStructure" name="Structure_idStructure" required>
                                <option value="">Sélectionner une structure</option>
                                <?php foreach ($structures as $struct): ?>
                                        <option value="<?= $struct['idStructure'] ?>"><?= $struct['designation'] ?></option>
                                    
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editProjectBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding users to project -->
<?php foreach ($projects as $project): ?>
<div class="modal fade" id="modalAddUser<?php echo $project['idProjet']; ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un utilisateur au projet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/add_user_to_project.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idProjet" value="<?php echo $project['idProjet']; ?>">
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
function editProject(id, nom, description, dateDebut, dateFin, statut, structureId) {
    document.getElementById('editProjectId').value = id;
    document.getElementById('editProjectNom').value = nom;
    document.getElementById('editProjectDescription').value = description;
    document.getElementById('editProjectDateDebut').value = dateDebut;
    document.getElementById('editProjectDateFin').value = dateFin;
    document.getElementById('editProjectStatut').value = statut;
    document.getElementById('editStructure_idStructure').value = structureId;

    const modal = new bootstrap.Modal(document.getElementById('editProjectModal'));
    modal.show();
}

function confirmDeleteUser(id_user_projet) {
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
            window.location.href = 'controller/deleteUserProject.php?id=' + id_user_projet;
        }
    })
}

document.getElementById('searchInput').addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
        const searchValue = this.value.trim();
        window.location.href = 'projet/projet.add?search=' + encodeURIComponent(searchValue);
    }
});
</script>

<?php include "./views/include/footer.php"; ?>