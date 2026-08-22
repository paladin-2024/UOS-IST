<?php
include "./views/include/header.php";
$structure = new Structure();
$userId = $_SESSION['id']; // Assuming user ID is stored in session

$structures = $structure->getStructures();

// Handle search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$budgets = $structure->getBudgetsByStructure2($userId, $searchQuery, 20); // Retrieve budgets with search and limit

?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>BUDGETS DES DÉPENSES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Budgets des Dépenses</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Search Bar -->
            <div class="col-lg-12 mb-3">
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addBudgetModal">
                    <i class="bi bi-plus"></i> Nouveau Budget de Dépense
                </button>
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un budget..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table budget depense -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des budgets des dépenses
                                </h5>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Année</th>
                                            <th scope="col">Solde</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="budgetTableBody">
                                        <?php
                                        $i = 1;
                                        foreach ($budgets as $budget) {
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$budget['designation']}</td>
                                                <td>{$budget['annee']}</td>
                                                <td>{$budget['solde_b_depense']}</td>
                                                <td>
                                                    <!-- Bouton pour modifier le budget -->
                                                    <button class='btn btn-sm btn-warning' onclick='editBudget(
                                                        {$budget['idBudget_depense_structure']}, 
                                                        \"{$budget['designation']}\",
                                                        \"{$budget['annee']}\",
                                                        \"{$budget['solde_b_depense']}\"
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i> Modifier
                                                    </button>

                                                    <!-- Bouton pour afficher les utilisateurs -->
                                                    <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#collapseUsers{$budget['idBudget_depense_structure']}' aria-expanded='false' aria-controls='collapseUsers{$budget['idBudget_depense_structure']}'>
                                                        <i class='bi bi-people'></i> Utilisateurs
                                                    </button>

                                                    <!-- Bouton pour ajouter un utilisateur -->
                                                    <button class='btn btn-sm btn-success' data-bs-toggle='modal' data-bs-target='#modalAddUser{$budget['idBudget_depense_structure']}'>
                                                        <i class='bi bi-person-plus'></i> Ajouter Utilisateur
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class='collapse-row'>
                                                <td colspan='5' class='p-0'>
                                                    <div class='collapse' id='collapseUsers{$budget['idBudget_depense_structure']}'>
                                                        <table class='table table-sm table-bordered m-0'>
                                                            <thead>
                                                                <tr>
                                                                    <th>Nom utilisateur</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>";
                                            // Retrieve users with access to the budget
                                            $users = $structure->getUsersByBudget($budget['idBudget_depense_structure']);
                                            foreach ($users as $user) {
                                                echo "
                                                                <tr>
                                                                    <td>{$user['nomUser']}</td>
                                                                    <td>
                                                                        <a class='btn btn-sm btn-danger' onclick='confirmDeleteUser({$user['iduser_budget_depense']})'>
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
                                            </tr>
                                            ";
                                            $i++;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal for adding a budget -->
<div class="modal fade" id="addBudgetModal" tabindex="-1" aria-labelledby="addBudgetModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBudgetModalLabel">Ajouter un Budget de Dépense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addBudgetDepenseForm" action="controller/create_budget_depense.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="designation" name="designation" required>
                            </div>
                            <div class="col-md-6">
                                <label for="annee" class="form-label">Année <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="annee" name="annee" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="solde_b_depense" class="form-label">Solde Initial <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" id="solde_b_depense" name="solde_b_depense" required>
                            </div>
                            <div class="col-md-6">
                                <label for="Structure_idStructure" class="form-label">Structure <span class="text-danger">*</span></label>
                                <select class="form-select" id="Structure_idStructure" name="Structure_idStructure" required>
                                    <option value="">Sélectionner une structure</option>
                                    <?php foreach ($structures as $structuree): ?>
                                        <?php
                                        // Check permission for each structure
                                        $ver1 = $structure->getUserPermissionStructure($userId, $structuree['idStructure']);
                                        if ($ver1->fetch()):
                                        ?>
                                            <option value="<?= $structuree['idStructure'] ?>"><?= $structuree['designation'] ?></option>
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

    <!-- Modal for editing a budget -->
    <div class="modal fade" id="editBudgetModal" tabindex="-1" role="dialog" aria-labelledby="editBudgetModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier un budget</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="controller/edit_budget.php" class="needs-validation" novalidate>
                        <input type="hidden" name="idBudget" id="editBudgetId">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editBudgetDesignation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" name="designation" id="editBudgetDesignation" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="editBudgetAnnee" class="form-label">Année <span class="text-danger">*</span></label>
                                <input type="text" name="annee" id="editBudgetAnnee" class="form-control" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editBudgetSolde" class="form-label">Solde <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="solde" id="editBudgetSolde" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" name="editBudgetBtn" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<!-- Modal pour ajouter un utilisateur -->
<?php foreach ($budgets as $budget): ?>
    <div class="modal fade" id="modalAddUser<?php echo $budget['idBudget_depense_structure']; ?>" tabindex="-1" role="dialog" aria-labelledby="modalAddUserLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un utilisateur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/add_user_to_budget.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idBudget" value="<?php echo $budget['idBudget_depense_structure']; ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="userId" class="form-label">Utilisateur <span class="text-danger">*</span></label>
                            <select name="userId" class="form-select" required>
                                <option value="">Sélectionner un utilisateur</option>
                                <?php
                                $allUsers = $structure->getUsers(); // Assuming this method retrieves all users
                                foreach ($allUsers as $user) {
                                    echo "<option value='{$user['idUser']}'>{$user['nomUser']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un utilisateur.</div>
                        </div>
                        <div class="col-md-6">
                            <!-- Add another field here if needed -->
                            <!-- Example: -->
                            <label for="role" class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="">Sélectionner un rôle</option>
                                <option value="1">Peut Tout voir</option>
                                <option value="2">Ne peut tout voir</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un rôle.</div>
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
    // Function to pre-fill the edit budget form
    function editBudget(id, designation, annee, solde) {
        document.getElementById('editBudgetId').value = id;
        document.getElementById('editBudgetDesignation').value = designation;
        document.getElementById('editBudgetAnnee').value = annee;
        document.getElementById('editBudgetSolde').value = solde;

        // Open the modal
        const modal = new bootstrap.Modal(document.getElementById('editBudgetModal'));
        modal.show();
    }

    function confirmDeleteUser(id_user_budget) {
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
                // Redirect to the PHP script for deletion
                window.location.href = 'controller/deleteUserBudget.php?id=' + id_user_budget;
            }
        })
    }

    // Search functionality with Enter key
    document.getElementById('searchInput').addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            const searchValue = this.value.trim();
            window.location.href = 'budget/budget.depense.edit?search=' + encodeURIComponent(searchValue);
        }
    });
</script>
<?php include "./views/include/footer.php"; ?>