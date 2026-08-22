<?php
include "./views/include/header.php";

$structureModel = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$structures = $structureModel->getStructures();

?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Fournisseurs</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Fournisseurs</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Fournisseurs</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/fournisseur.edit">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un fournisseur...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="fournisseurTable">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Structure</th>
                                    <th>Compte</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $userId = $_SESSION['id'];
                                $hasResults = false;

                                foreach ($structures as $structure) {
                                    $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                    if ($ver1->fetch()) {
                                        $fournisseurs = $structureModel->getFournisseursByStructure($structure['idStructure'], $search);
                                        foreach ($fournisseurs as $fournisseur) {
                                            $hasResults = true;
                                            echo "
                                                <tr>
                                                    <td>{$fournisseur['nom']}</td>
                                                    <td>{$fournisseur['email']}</td>
                                                    <td>{$fournisseur['telephone']}</td>
                                                    <td>{$structure['designation']}</td>
                                                    <td>{$fournisseur['numeroCompte']} - {$fournisseur['intituleCompte']}</td>
                                                    <td>
                                                        <button type='button' class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editFournisseurModal'
                                                            data-fournisseur-id='{$fournisseur['idFournisseur']}'
                                                            data-nom='{$fournisseur['nom']}'
                                                            data-email='{$fournisseur['email']}'
                                                            data-telephone='{$fournisseur['telephone']}'
                                                            data-adresse='{$fournisseur['adresse']}'
                                                            data-solde='{$fournisseur['solde']}'
                                                            data-structure-id='{$fournisseur['Structure_idStructure']}'
                                                            data-compte-id='{$fournisseur['Compte_idCompte']}'
                                                            >
                                                            Modifier
                                                        </button>
                                                        <form action='controller/delete_fournisseur.php' method='POST' class='delete-fournisseur-form' style='display:inline;'>
                                                            <input type='hidden' name='idFournisseur' value='{$fournisseur['idFournisseur']}'>
                                                            <button type='button' class='btn btn-danger btn-sm delete-fournisseur-btn'>Supprimer</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            ";
                                        }
                                    }
                                }

                                if (!$hasResults) {
                                    echo "<tr><td colspan='6' class='text-center'>Aucun résultat trouvé</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>

                        <!-- Modal for editing a supplier -->
    <div class="modal fade" id="editFournisseurModal" tabindex="-1" aria-labelledby="editFournisseurModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFournisseurModalLabel">Modifier un fournisseur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editFournisseurForm" action="controller/update_fournisseur.php" method="POST">
                        <input type="hidden" name="idFournisseur" id="editIdFournisseur">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editNom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editNom" name="nom" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editTelephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editTelephone" name="telephone" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editAdresse" class="form-label">Adresse</label>
                                <input type="text" class="form-control" id="editAdresse" name="adresse">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editSolde" class="form-label">Solde</label>
                                <input type="number" step="0.01" class="form-control" id="editSolde" name="solde">
                            </div>
                            <div class="col-md-6">
                                <label for="editStructureId" class="form-label">Structure <span class="text-danger">*</span></label>
                                <select class="form-select" id="editStructureId" name="structureId" required onchange="fetchAccounts(this.value)">
                                    <option value="">Sélectionner une structure</option>
                                    <?php foreach ($structures as $structure): ?>
                                        <?php
                                        $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                        if ($ver1->fetch()):
                                        ?>
                                            <option value="<?= $structure['idStructure'] ?>"><?= $structure['designation'] ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editCompteId" class="form-label">Compte</label>
                            <select class="form-select" id="editCompteId" name="compteId">
                                <option value="">Sélectionner un compte</option>
                                <!-- Options will be populated based on the selected structure -->
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

                        <script>
                            function fetchAccounts(structureId, currentCompteId = null) {
                                const compteIdSelect = document.getElementById("editCompteId");

                                if (structureId === "") {
                                    compteIdSelect.innerHTML = "<option value=''>Sélectionner un compte</option>";
                                    return;
                                }

                                const xhr = new XMLHttpRequest();
                                xhr.open("GET", "controller/get_accounts.php?structureId=" + structureId, true);
                                xhr.onreadystatechange = function() {
                                    if (xhr.readyState === 4 && xhr.status === 200) {
                                        const accounts = JSON.parse(xhr.responseText);
                                        let options = "<option value=''>Sélectionner un compte</option>";
                                        accounts.forEach(account => {
                                            const selected = account.idCompte === currentCompteId ? "selected" : "";
                                            options += `<option value="${account.idCompte}" ${selected}>${account.numeroCompte} - ${account.intituleCompte}</option>`;
                                        });
                                        compteIdSelect.innerHTML = options;
                                    }
                                };
                                xhr.send();
                            }

                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('.delete-fournisseur-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const form = this.closest('.delete-fournisseur-form');
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

                                document.querySelectorAll('[data-bs-target="#editFournisseurModal"]').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const fournisseurId = this.getAttribute('data-fournisseur-id');
                                        const nom = this.getAttribute('data-nom');
                                        const email = this.getAttribute('data-email');
                                        const telephone = this.getAttribute('data-telephone');
                                        const adresse = this.getAttribute('data-adresse');
                                        const solde = this.getAttribute('data-solde');
                                        const structureId = this.getAttribute('data-structure-id');
                                        const compteId = parseInt(this.getAttribute('data-compte-id'), 10);

                                        document.getElementById('editIdFournisseur').value = fournisseurId;
                                        document.getElementById('editNom').value = nom;
                                        document.getElementById('editEmail').value = email;
                                        document.getElementById('editTelephone').value = telephone;
                                        document.getElementById('editAdresse').value = adresse;
                                        document.getElementById('editSolde').value = solde;
                                        document.getElementById('editStructureId').value = structureId;

                                        // Fetch accounts for the selected structure and set the current account as selected
                                        fetchAccounts(structureId, compteId);
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