<?php
include "./views/include/header.php";
$structure = new Structure();

$projet = new Projet();
$userId = $_SESSION['id'];

// Fetch projects the user has access to
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$projects = $projet->getProjetByUserAccess($userId, '', 200);
?>

<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES ACTIVITÉS DE PROJET</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Gestion des Activités</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                    <i class="bi bi-plus"></i> Nouvelle Activité
                </button>
                <form method="GET" action="" class="mb-3">
                    <div class="input-group">
                        <input type="hidden" name="view" value="projet/activite.add">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" class="form-control" placeholder="Rechercher une activité...">
                        <button type="submit" class="btn btn-primary">Rechercher</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Liste des Activités</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Intitulé</th>
                                    <th scope="col">Date Début</th>
                                    <th scope="col">Date Fin</th>
                                    <th scope="col">Budget</th>
                                    <th scope="col">État</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="activityTableBody">
                                <?php
                                $i = 1;
                                foreach ($projects as $project) {
                                    $activities = $projet->getActivitiesByProject($project['idProjet'],$searchQuery);
                                    foreach ($activities as $activity) {
                                        $dd = date('d/m/Y', strtotime($activity['dateDebut']));
                                        $df = date('d/m/Y', strtotime($activity['dateFin']));
                                        echo "
                                        <tr>
                                            <td>{$i}</td>
                                            <td>{$activity['intitule']}</td>
                                            <td>{$dd}</td>
                                            <td>{$df}</td>
                                            <td>{$activity['budget']}</td>
                                            <td>{$activity['etatActivite']}</td>
                                            <td>
                                                <button class='btn btn-sm btn-warning' onclick='editActivity(
                                                    {$activity['idActivite_projet']}, 
                                                    \"{$activity['intitule']}\",
                                                    \"{$activity['dateDebut']}\",
                                                    \"{$activity['dateFin']}\",
                                                    \"{$activity['budget']}\",
                                                    \"{$activity['etatActivite']}\",
                                                    \"{$activity['Projet_idProjet']}\"
                                                )'>
                                                    <i class='bi bi-pencil-square'></i>
                                                </button>

                                                <button class='btn btn-sm btn-info' data-bs-toggle='collapse' 
                                                    data-bs-target='#collapseUsers{$activity['idActivite_projet']}'>
                                                    <i class='bi bi-people'></i>
                                                </button>

                                                <button class='btn btn-sm btn-success' data-bs-toggle='modal' 
                                                    data-bs-target='#modalAddUser{$activity['idActivite_projet']}'>
                                                    <i class='bi bi-person-plus'></i> Utilisateur
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class='collapse-row'>
                                            <td colspan='7' class='p-0'>
                                                <div class='collapse' id='collapseUsers{$activity['idActivite_projet']}'>
                                                    <table class='table table-sm table-bordered m-0'>
                                                        <thead>
                                                            <tr>
                                                                <th>Nom utilisateur</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>";
                                        
                                        $users = $projet->getUsersByActivity($activity['idActivite_projet']);
                                        foreach ($users as $user) {
                                            echo "
                                            <tr>
                                                <td>{$user['nomUser']}</td>
                                                <td>
                                                    <a class='btn btn-sm btn-danger' onclick='confirmDeleteUser({$user['iduser_activite_projet']})'>
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

<!-- Modal for adding an activity -->
<div class="modal fade" id="addActivityModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Activité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addActivityForm" action="controller/create_activity.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="intitule" class="form-label">Intitulé <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="intitule" name="intitule" required>
                        </div>
                        <div class="col-md-6">
                            <label for="budget" class="form-label">Budget <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="budget" name="budget" required>
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
                            <label for="etatActivite" class="form-label">État <span class="text-danger">*</span></label>
                            <select class="form-select" id="etatActivite" name="etatActivite" required>
                                <option value="">Sélectionner un état</option>
                                <option value="En cours">En cours</option>
                                <option value="Terminé">Terminé</option>
                                <option value="Suspendu">Suspendu</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="Projet_idProjet" class="form-label">Projet <span class="text-danger">*</span></label>
                            <select class="form-select" id="Projet_idProjet" name="Projet_idProjet" required>
                                <option value="">Sélectionner un projet</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['idProjet'] ?>"><?= $project['nomProjet'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Ajouter
                    </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for editing an activity -->
<div class="modal fade" id="editActivityModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Activité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_activity.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idActivite_projet" id="editActivityId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editActivityIntitule" class="form-label">Intitulé <span class="text-danger">*</span></label>
                            <input type="text" name="intitule" id="editActivityIntitule" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editActivityBudget" class="form-label">Budget <span class="text-danger">*</span></label>
                            <input type="number" name="budget" id="editActivityBudget" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editActivityDateDebut" class="form-label">Date de Début <span class="text-danger">*</span></label>
                            <input type="date" name="dateDebut" id="editActivityDateDebut" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editActivityDateFin" class="form-label">Date de Fin <span class="text-danger">*</span></label>
                            <input type="date" name="dateFin" id="editActivityDateFin" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editActivityEtat" class="form-label">État <span class="text-danger">*</span></label>
                            <select class="form-select" id="editActivityEtat" name="etatActivite" required>
                                <option value="En cours">En cours</option>
                                <option value="Terminé">Terminé</option>
                                <option value="Suspendu">Suspendu</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editProjet_idProjet" class="form-label">Projet <span class="text-danger">*</span></label>
                            <select class="form-select" id="editProjet_idProjet" name="Projet_idProjet" required>
                                <option value="">Sélectionner un projet</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?= $project['idProjet'] ?>"><?= $project['nomProjet'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editActivityBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for adding users to activity -->
<?php foreach ($projects as $project): ?>
    <?php 
    $activities = $projet->getActivitiesByProject($project['idProjet']);
    foreach ($activities as $activity): ?>
        <div class="modal fade" id="modalAddUser<?php echo $activity['idActivite_projet']; ?>" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter un utilisateur à l'activité</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="controller/add_user_to_activity.php" class="needs-validation" novalidate>
                            <input type="hidden" name="idActivite_projet" value="<?php echo $activity['idActivite_projet']; ?>">
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
<?php endforeach; ?>

<script>
function editActivity(id, intitule, dateDebut, dateFin, budget, etat,projet) {
    document.getElementById('editActivityId').value = id;
    document.getElementById('editActivityIntitule').value = intitule;
    document.getElementById('editActivityDateDebut').value = dateDebut;
    document.getElementById('editActivityDateFin').value = dateFin;
    document.getElementById('editActivityBudget').value = budget;
    document.getElementById('editActivityEtat').value = etat;
    document.getElementById('editProjet_idProjet').value = projet;

    const modal = new bootstrap.Modal(document.getElementById('editActivityModal'));
    modal.show();
}

function confirmDeleteUser(id_user_activite_projet) {
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
            window.location.href = 'controller/deleteUserActivity.php?id=' + id_user_activite_projet;
        }
    })
}
</script>

<?php include "./views/include/footer.php"; ?>