<?php
include "./views/include/header.php";

$banqueModel = new Banque();
$structureModel = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$structures = $structureModel->getStructures($search);
$userId = $_SESSION['id']; // Assuming user ID is stored in session

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Banques</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Banques</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Banques</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/banque.edit">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher une structure...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="banqueTable">
                            <thead>
                                <tr>
                                    <th>Structure</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hasResults = false;

                                foreach ($structures as $structure) {
                                    $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                    if ($ver1->fetch()) {
                                        $hasResults = true;
                                        echo "
                                            <tr>
                                                <td>{$structure['designation']}</td>
                                                <td>
                                                    <button type='button' class='btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='#banks{$structure['idStructure']}'>
                                                        <i class='bi bi-eye-fill'></i> Afficher les Banques
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class='collapse' id='banks{$structure['idStructure']}' >
                                                <td colspan='2'>
                                                    <table class='table table-sm'>
                                                        <thead>
                                                            <tr>
                                                                <th>Désignation</th>
                                                                <th>Numéro de Compte</th>
                                                                <th>Solde</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                        ";

                                        $banks = $banqueModel->getBanksByStructure($structure['idStructure']);
                                        foreach ($banks as $bank) {
                                            echo "
                                                <tr>
                                                    <td>{$bank['designation']}</td>
                                                    <td>{$bank['numeroCompte']}</td>
                                                    <td>{$bank['solde']}</td>
                                                    <td>
                                                        <button type='button' class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editBankModal'
                                                            data-bank-id='{$bank['idBanque']}'
                                                            data-designation='{$bank['designation']}'
                                                            data-numero-compte='{$bank['numeroCompte']}'
                                                            data-solde='{$bank['solde']}'
                                                            data-structure-id='{$bank['Compte_idCompte']}'
                                                            >
                                                            Modifier
                                                        </button>
                                                        <button type='button' class='btn btn-info btn-sm' data-bs-toggle='modal' data-bs-target='#userBankModal'
                                                            data-bank-id='{$bank['idBanque']}'>
                                                            Utilisateurs
                                                        </button>
                                                        
                                                        <form action='controller/delete_banque.php' method='POST' class='delete-bank-form' style='display:inline;'>
                                                            <input type='hidden' name='idBanque' value='{$bank['idBanque']}'>
                                                            <button type='button' class='btn btn-danger btn-sm delete-bank-btn'>Supprimer</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            ";
                                        }

                                        echo "
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        ";
                                    }
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='2' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Modal for editing a bank -->
    <div class="modal fade" id="editBankModal" tabindex="-1" aria-labelledby="editBankModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBankModalLabel">Modifier une banque</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editBankForm" action="controller/update_banque.php" method="POST">
                        <input type="hidden" name="idBanque" id="editIdBanque">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editDesignation" class="form-label">Désignation <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editDesignation" name="designation" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editNumeroCompte" class="form-label">Numéro de Compte <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editNumeroCompte" name="numeroCompte" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editSolde" class="form-label">Solde</label>
                                <input type="number" step="0.01" class="form-control" id="editSolde" name="solde">
                            </div>
                            <div class="col-md-6">
                                <label for="editCompteId" class="form-label">Compte Comptable <span class="text-danger">*</span></label>
                                <select class="form-control" id="editCompteId" name="compteId" required>
                                    <option value="">Sélectionner un compte</option>
                                    <?php 
                                    $comptes = $structureModel->getAuthorizedComptes($userId);
                                    foreach ($comptes as $compte): ?>
                                        <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte']." : ".$compte['intituleCompte'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

                        <!-- Modal for managing users of a bank -->
                        <div class="modal fade" id="userBankModal" tabindex="-1" aria-labelledby="userBankModalLabel" aria-hidden="true" data-bs-backdrop="static">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="userBankModalLabel">Utilisateurs autorisés</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <ul id="userList" class="list-group mb-3">
                                            <!-- User list will be populated here -->
                                        </ul>
                                        <form id="addUserForm" action="controller/add_user_banque.php" method="POST">
                                            <input type="hidden" name="banqueId" id="banqueIdForUser">
                                            <div class="mb-3">
                                                <label for="userId" class="form-label">Ajouter un utilisateur</label>
                                                <select class="form-select" id="userId" name="userId" required>
                                                    <option value="">Sélectionner un utilisateur</option>
                                                    <?php foreach ($structureModel->getUsers() as $user): ?>
                                                        <option value="<?= $user['idUser'] ?>"><?= $user['nomUser'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Ajouter</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('.delete-bank-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const form = this.closest('.delete-bank-form');
                                        Swal.fire({
                                            title: 'Êtes-vous sûr?',
                                            text: "Cette action est irréversible!",
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#3085d6',
                                            cancelButtonColor: '#d33',
                                            confirmButtonText: 'Oui, supprimer!',
                                            cancelButtonText: 'Annuler'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                form.submit();
                                            }
                                        });
                                    });
                                });

                                document.querySelectorAll('[data-bs-target="#editBankModal"]').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const bankId = this.getAttribute('data-bank-id');
                                        const designation = this.getAttribute('data-designation');
                                        const numeroCompte = this.getAttribute('data-numero-compte');
                                        const solde = this.getAttribute('data-solde');
                                        const compteId = this.getAttribute('data-structure-id');

                                        document.getElementById('editIdBanque').value = bankId;
                                        document.getElementById('editDesignation').value = designation;
                                        document.getElementById('editNumeroCompte').value = numeroCompte;
                                        document.getElementById('editSolde').value = solde;
                                        document.getElementById('editCompteId').value = compteId;
                                    });
                                });

                                document.querySelectorAll('[data-bs-target="#userBankModal"]').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const bankId = this.getAttribute('data-bank-id');
                                        document.getElementById('banqueIdForUser').value = bankId;

                                        // Fetch and display users for the selected bank
                                        fetch(`controller/get_users_by_banque.php?banqueId=${bankId}`)
                                            .then(response => response.json())
                                            .then(users => {
                                                const userList = document.getElementById('userList');
                                                userList.innerHTML = '';
                                                users.forEach(user => {
                                                    const li = document.createElement('li');
                                                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                                                    li.textContent = user.nomUser;
                                                    const removeButton = document.createElement('button');
                                                    removeButton.className = 'btn btn-danger btn-sm';
                                                    removeButton.textContent = 'Supprimer';
                                                    removeButton.onclick = function () {
                                                        const formData = new FormData();
                                                        formData.append('userBanqueId', user.iduser_banque);

                                                        fetch(`controller/remove_user_banque.php`, {
                                                            method: 'POST',
                                                            body: formData
                                                        })
                                                        .then(response => response.json())
                                                        .then(data => {
                                                            if (data.success) {
                                                                li.remove();
                                                            } else {
                                                                Swal.fire({
                                                                    icon: 'error',
                                                                    title: 'Erreur',
                                                                    text: data.error || 'Erreur lors de la suppression.'
                                                                });
                                                            }
                                                        })
                                                        .catch(() => {
                                                            Swal.fire({
                                                                icon: 'error',
                                                                title: 'Erreur',
                                                                text: 'Erreur lors de la communication avec le serveur.'
                                                            });
                                                        });
                                                    };
                                                    li.appendChild(removeButton);
                                                    userList.appendChild(li);
                                                });
                                            });
                                    });
                                });
                            });
                        </script>

                    </div>
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<?php include "./views/include/footer.php"; ?>