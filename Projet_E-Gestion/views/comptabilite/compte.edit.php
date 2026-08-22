<?php
include "./views/include/header.php";

$structureModel = new Structure();
$search = isset($_GET['search']) ? $_GET['search'] : '';
$structures = $structureModel->getStructures($search);
?>

<main id="main" class="main">

    <div class="pagetitle">
        <h1>Liste des Comptes</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Comptes</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des Comptes</h5>

                        <!-- Search Form -->
                        <form method="GET" action="" class="mb-3">
                            <div class="input-group">
                                <input type="hidden" name="view" value="comptabilite/compte.edit">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher une structure...">
                                <button type="submit" class="btn btn-primary">Rechercher</button>
                            </div>
                        </form>

                        <table class="table table-striped table-bordered" id="structureTable">
                            <thead>
                                <tr>
                                    <th>Structure</th>
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
                                        $hasResults = true;
                                        echo "
                                            <tr>
                                                <td>{$structure['designation']}</td>
                                                <td>
                                                    <button type='button' class='btn btn-secondary btn-sm' data-bs-toggle='collapse' data-bs-target='#accounts{$structure['idStructure']}'>
                                                        <i class='bi bi-eye-fill'></i> Afficher les Comptes
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class='collapse' id='accounts{$structure['idStructure']}'>
                                                <td colspan='2'>
                                                    <table class='table table-sm'>
                                                        <thead>
                                                            <tr>
                                                                <th>Type de Compte</th>
                                                                <th>Numéro de Compte</th>
                                                                <th>Intitulé</th>
                                                                <th>Classe</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                        ";

                                        $accounts = $structureModel->getComptes();
                                        usort($accounts, function($a, $b) {
                                            return strcmp($a['typeCompte'], $b['typeCompte']);
                                        });

                                        $currentType = '';
                                        foreach ($accounts as $account) {
                                            if ($account['Structure_idStructure'] == $structure['idStructure']) {
                                                if ($currentType !== $account['typeCompte']) {
                                                    $currentType = $account['typeCompte'];
                                                    echo "<tr><td colspan='5' class='font-weight-bold'>{$currentType}</td></tr>";
                                                }
                                                echo "
                                                    <tr>
                                                        <td></td>
                                                        <td>{$account['numeroCompte']}</td>
                                                        <td>{$account['intituleCompte']}</td>
                                                        <td>{$account['classeCompte']}</td>
                                                        <td>
                                                            <button type='button' class='btn btn-warning btn-sm' data-bs-toggle='modal' data-bs-target='#editAccountModal'
                                                                data-account-id='{$account['idCompte']}'
                                                                data-numero-compte='{$account['numeroCompte']}'
                                                                data-intitule-compte='{$account['intituleCompte']}'
                                                                data-type-compte='{$account['typeCompte']}'
                                                                data-classe-compte='{$account['classeCompte']}'
                                                                data-structure-id='{$account['Structure_idStructure']}'
                                                                >
                                                                Modifier
                                                            </button>
                                                            <form action='controller/delete_compte.php' method='POST' class='delete-account-form' style='display:inline;'>
                                                                <input type='hidden' name='idCompte' value='{$account['idCompte']}'>
                                                                <button type='button' class='btn btn-danger btn-sm delete-account-btn'>Supprimer</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                ";
                                            }
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

                        <!-- Modal for editing an account -->
    <div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAccountModalLabel">Modifier un compte</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAccountForm" action="controller/update_compte.php" method="POST">
                        <input type="hidden" name="idCompte" id="editIdCompte">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editNumeroCompte" class="form-label">Numéro de Compte <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editNumeroCompte" name="numeroCompte" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editIntituleCompte" class="form-label">Intitulé <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editIntituleCompte" name="intituleCompte" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editTypeCompte" class="form-label">Type de Compte <span class="text-danger">*</span></label>
                                <select class="form-select" id="editTypeCompte" name="typeCompte" required>
                                    <option value="">Sélectionner un type de compte</option>
                                    <option value="Actif">Actif</option>
                                    <option value="Passif">Passif</option>
                                    <option value="Charges">Charges</option>
                                    <option value="Produits">Produits</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="editClasseCompte" class="form-label">Classe du Compte <span class="text-danger">*</span></label>
                                <select class="form-select" id="editClasseCompte" name="classeCompte" required>
                                    <option value="">Sélectionner une classe</option>
                                    <option value="1">1ère classe</option>
                                    <option value="2">2ème classe</option>
                                    <option value="3">3ème classe</option>
                                    <option value="4">4ème classe</option>
                                    <option value="5">5ème classe</option>
                                    <option value="6">6ème classe</option>
                                    <option value="7">7ème classe</option>
                                    <option value="8">8ème classe</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editStructureId" class="form-label">Structure <span class="text-danger">*</span></label>
                            <select class="form-select" id="editStructureId" name="structureId" required>
                                <option value="">Sélectionner une structure</option>
                                <?php foreach ($structures as $structure): ?>
                                    <?php
                                    // Check permission for each structure
                                    $ver1 = $structureModel->getUserPermissionStructure($userId, $structure['idStructure']);
                                    if ($ver1->fetch()):
                                    ?>
                                        <option value="<?= $structure['idStructure'] ?>"><?= $structure['designation'] ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
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
                            document.addEventListener('DOMContentLoaded', function () {
                                document.querySelectorAll('.delete-account-btn').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const form = this.closest('.delete-account-form');
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

                                document.querySelectorAll('[data-bs-target="#editAccountModal"]').forEach(button => {
                                    button.addEventListener('click', function () {
                                        const accountId = this.getAttribute('data-account-id');
                                        const numeroCompte = this.getAttribute('data-numero-compte');
                                        const intituleCompte = this.getAttribute('data-intitule-compte');
                                        const typeCompte = this.getAttribute('data-type-compte');
                                        const classeCompte = this.getAttribute('data-classe-compte');
                                        const structureId = this.getAttribute('data-structure-id');

                                        document.getElementById('editIdCompte').value = accountId;
                                        document.getElementById('editNumeroCompte').value = numeroCompte;
                                        document.getElementById('editIntituleCompte').value = intituleCompte;
                                        document.getElementById('editTypeCompte').value = typeCompte;
                                        document.getElementById('editClasseCompte').value = classeCompte;
                                        document.getElementById('editStructureId').value = structureId;
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