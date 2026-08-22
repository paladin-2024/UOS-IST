<?php
include "./views/include/header.php";
$structure = new Structure();
$userId = $_SESSION['id']; // Assuming user ID is stored in session

// Handle search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$groupesDepenses = $structure->getGroupesDepenseByUserAccess2($userId, $searchQuery, 20); // Retrieve groups with search and limit
$comptesComptables = $structure->getComptesComptablesByUserAccess($userId); // Use the new method for comptes comptables

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>GROUPES DE DÉPENSES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Groupes de Dépenses</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12 mb-3">
            <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addGroupeModal">
                    <i class="bi bi-plus"></i> Nouveau Groupe de Dépense
                </button>
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un groupe..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>

            <div class="col-lg-12">
                <div class="card overflow-auto">
                    <div class="card-body">
                        <h5 class="card-title">Gestion des groupes de dépenses</h5>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nom du Groupe</th>
                                    <th>Montant</th>
                                    <th>Budget</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i = 1;
                                foreach ($groupesDepenses as $groupe) {
                                    echo "<tr>
                                        <td>{$i}</td>
                                        <td>{$groupe['designationGD']}</td>
                                        <td>{$groupe['soldeGD']}</td>
                                        <td>{$groupe['designation_budget']}</td>
                                        <td>
                                            <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#collapseLignes{$groupe['idGroupe_depense_structure']}'>
                                                <i class='bi bi-list'></i> Lignes
                                            </button>
                                            <button class='btn btn-sm btn-warning' onclick='editGroupe({$groupe['idGroupe_depense_structure']},{$groupe['soldeGD']}, \"{$groupe['designationGD']}\")'>
                                                <i class='bi bi-pencil'></i> Modifier
                                            </button>
                                            <button class='btn btn-sm btn-success' onclick='addLigne({$groupe['idGroupe_depense_structure']})'>
                                                <i class='bi bi-plus'></i> Ajouter Ligne
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class='collapse-row'>
                                        <td colspan='5'>
                                            <div class='collapse' id='collapseLignes{$groupe['idGroupe_depense_structure']}'>
                                                <table class='table table-sm table-bordered m-0'>
                                                    <thead>
                                                        <tr>
                                                            <th>Compte</th>
                                                            <th>Code Budgétaire</th>
                                                            <th>Désignation</th>
                                                            <th>Montant</th>
                                                            <th>Actions</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>";
                                    $lignes = $structure->getLignesByGroupe($groupe['idGroupe_depense_structure']);
                                    foreach ($lignes as $ligne) {
                                        echo "<tr>
                                                <td>{$ligne['numeroCompte']}</td>
                                                <td>{$ligne['codeLigne']}</td>
                                                <td>{$ligne['designation']}</td>
                                                <td>{$ligne['montant']}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='editLigne({$ligne['idligne_depense_structure']}, \"{$ligne['designation']}\", \"{$ligne['montant']}\",\"{$ligne['codeLigne']}\")'>
                                                        <i class='bi bi-pencil'></i> Modifier
                                                    </button>
                                                    <a class='btn btn-sm btn-danger' onclick='confirmDeleteLigne({$ligne['idligne_depense_structure']})'>
                                                        <i class='bi bi-trash'></i> Supprimer
                                                    </a>
                                                </td>
                                            </tr>";
                                    }
                                    echo "</tbody>
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

<!-- Modal Ajouter Ligne -->
<div class="modal fade" id="addLigneModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Ligne de Dépense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addLigneDepenseForm" action="controller/create_ligne_depense.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="Groupe_depense_structure_idGroupe_depense_structure" id="addGroupeId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="codeLigne" class="form-label">Code Ligne <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="codeLigne" name="codeLigne" required>
                            <div class="invalid-feedback">Veuillez saisir un code ligne.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="designation" name="designation" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="montant" class="form-label">Montant <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="montant" name="montant" required>
                            <div class="invalid-feedback">Veuillez saisir un montant.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="Compte_idCompte" class="form-label">Compte de charge <span class="text-danger">*</span></label>
                            <select class="form-select" id="Compte_idCompte" name="Compte_idCompte" required>
                                <option value="">Sélectionner un compte de charge</option>
                                <?php foreach ($comptesComptables as $compte) {
                                    if ($compte['classeCompte'] == 6) {
                                        ?>
                                        <option value="<?= $compte['idCompte'] ?>"><?= $compte['numeroCompte'] . ' ' . $compte['intituleCompte'] ?></option>
                                    <?php }
                                } ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un compte de charge.</div>
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

<!-- Modal Modifier Groupe -->
<div class="modal fade" id="editGroupeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Groupe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_groupe.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idGroupe" id="editGroupeId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nomGroupe" class="form-label">Nom du Groupe <span class="text-danger">*</span></label>
                            <input type="text" name="nomGroupe" id="editGroupeNom" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un nom de groupe.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="soldeGD" class="form-label">Solde <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="soldeGD" id="editSoldeGD" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un solde.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Modifier Ligne -->
<div class="modal fade" id="editLigneModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Ligne</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_ligne.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idLigne" id="editLigneId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="codeLigne" class="form-label">Code Ligne <span class="text-danger">*</span></label>
                            <input type="text" name="codeLigne" id="editCodeLigne" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un code ligne.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="nomLigne" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="nomLigne" id="editLigneNom" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="montantLigne" class="form-label">Montant <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="montantLigne" id="editLigneMontant" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un montant.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter Groupe -->
<div class="modal fade" id="addGroupeModal" tabindex="-1" aria-labelledby="addGroupeModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGroupeModalLabel">Ajouter un Groupe de Dépense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addGroupeDepenseForm" action="controller/create_groupe_depense.php" method="POST" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designationGD" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="designationGD" name="designationGD" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="soldeGD" class="form-label">Solde Initial <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="soldeGD" name="soldeGD" required>
                            <div class="invalid-feedback">Veuillez saisir un solde initial.</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="budgetDepenseStructureId" class="form-label">Budget de Dépense <span class="text-danger">*</span></label>
                        <select class="form-select" id="budgetDepenseStructureId" name="budgetDepenseStructureId" required>
                            <option value="">Sélectionner un budget</option>
                            <?php
                            $budgets = $structure->getBudgetsByUser($userId);
                            foreach ($budgets as $budget):
                            ?>
                                <option value="<?= $budget['idBudget_depense_structure'] ?>"><?= $budget['designation'] ?></option>
                            <?php endforeach;?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un budget.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Ajouter
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function editGroupe(id, solde,nom) {
        document.getElementById('editGroupeId').value = id;
        document.getElementById('editGroupeNom').value = nom;
        document.getElementById('editSoldeGD').value = solde;
        const modal = new bootstrap.Modal(document.getElementById('editGroupeModal'));
        modal.show();
    }

    function editLigne(id, nom, montant,code) {
        document.getElementById('editLigneId').value = id;
        document.getElementById('editLigneNom').value = nom;
        document.getElementById('editLigneMontant').value = montant;
        document.getElementById('editCodeLigne').value = code;
        const modal = new bootstrap.Modal(document.getElementById('editLigneModal'));
        modal.show();
    }

    function confirmDeleteLigne(id) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_ligne.php?id=' + id;
            }
        });
    }

    function addLigne(groupeId) {
        document.getElementById('addGroupeId').value = groupeId;
        const modal = new bootstrap.Modal(document.getElementById('addLigneModal'));
        modal.show();
    }

    document.getElementById('searchInput').addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            window.location.href = 'budget/budget.depense.groupe.edit?search=' + encodeURIComponent(this.value.trim());
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>