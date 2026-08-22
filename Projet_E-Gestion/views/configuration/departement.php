<?php
include "./views/include/header.php";
$departement = new Universite();

// Fetch users for the dropdown
$structure = new Structure();
$users = $structure->getUsers();
$userss = $structure->getUsers();

$search = isset($_GET['search']) ? $_GET['search'] : '';
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>DÉPARTEMENTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Départements</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table departments -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des départements
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createDepartementModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="configuration/departement">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par designation...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Section</th>
                                            <th scope="col">Date de Création</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeDepartements = $departement->getDepartements($search);
                                        $i = 1;

                                        foreach ($listeDepartements as $l){
                                            $dc = date('d/m/Y H:i:s', strtotime($l['dateCreation']));
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$l['designationDepartement']}</td>
                                                <td>{$l['sectionDesignation']}</td>
                                                <td>{$dc}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='editDepartement(
                                                        {$l['iddepartement']}, 
                                                        \"{$l['designationDepartement']}\",
                                                        \"{$l['section_idsection']}\"
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$l['iddepartement']})'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#managers-{$l['iddepartement']}'>
                                                        <i class='bi bi-eye'></i> Voir Managers
                                                    </button>
                                                    <button class='btn btn-sm btn-success' data-bs-toggle='modal' data-bs-target='#addManagerModal' onclick='setDepartementId({$l['iddepartement']})'>
                                                        <i class='bi bi-plus-circle'></i> Ajouter Manager
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class='collapse' id='managers-{$l['iddepartement']}'>
                                                <td colspan='6'>
                                                    <table class='table table-sm'>
                                                        <thead>
                                                            <tr>
                                                                <th>Nom</th>
                                                                <th>Fonction</th>
                                                                <th>Signature</th>
                                                                <th>Année académique</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>";
                                            $managers = $departement->getManagersByDepartement($l['iddepartement']);
                                            foreach ($managers as $manager) {
                                                echo "
                                                            <tr>
                                                                <td>{$manager['noms']}</td>
                                                                <td>{$manager['fonction']}</td>
                                                                <td><img src='uploads/{$manager['signature']}' alt='Signature' width='100'></td>
                                                                <td>{$manager['anneeDesignation']}</td>
                                                                <td>
                                                                    <button class='btn btn-sm btn-warning' onclick='editManager(
                                                                        {$manager['idresponsable_departement']}, 
                                                                        \"{$manager['noms']}\",
                                                                        \"{$manager['fonction']}\",
                                                                        \"{$manager['signature']}\",
                                                                        \"{$manager['idUser']}\",
                                                                        \"{$manager['annee_acad_idannee_acad']}\"
                                                                    )'>
                                                                        <i class='bi bi-pencil-square'></i> Modifier
                                                                    </button>
                                                                    <button class='btn btn-sm btn-danger' onclick='confirmDeleteManager({$manager['idresponsable_departement']})'>
                                                                        <i class='bi bi-trash'></i> Supprimer
                                                                    </button>
                                                                </td>
                                                            </tr>";
                                            }
                                            echo "
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>";
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

<!-- Modal pour ajouter un département -->
<div class="modal fade" id="createDepartementModal" tabindex="-1" role="dialog" aria-labelledby="createDepartementModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Département</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_departement.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designationDepartement" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designationDepartement" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="sectionId" class="form-label">Section <span class="text-danger">*</span></label>
                            <select name="sectionId" class="form-control" required>
                                <!-- Populate with sections -->
                                <?php
                                $sections = $departement->getSections();
                                foreach ($sections as $section) {
                                    echo "<option value='{$section['idsection']}'>{$section['designationSection']} - {$section['anneeDesignation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addDepartementBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un manager -->
<div class="modal fade" id="addManagerModal" tabindex="-1" role="dialog" aria-labelledby="addManagerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_manager_depart.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="userId" class="form-label">Utilisateur <span class="text-danger">*</span></label>
                            <select name="userId" class="form-control" required>
                                <?php
                                foreach ($users as $user) {
                                    echo "<option value='{$user['idUser']}'>{$user['nomUser']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un utilisateur.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="fonction" class="form-label">Fonction <span class="text-danger">*</span></label>
                            <input type="text" name="fonction" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une fonction.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="signature" class="form-label">Signature <span class="text-danger">*</span></label>
                            <input type="file" name="signature" class="form-control" accept="image/*" required>
                            <div class="invalid-feedback">Veuillez importer une signature.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="idAnnee" class="form-control" required>
                                <!-- Populate with academic years -->
                                <?php
                                $academicYears = $departement->getAcademicYears();
                                foreach ($academicYears as $year) {
                                    echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                        <input type="hidden" name="departementId" id="managerDepartementId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addManagerBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un département -->
<div class="modal fade" id="editDepartementModal" tabindex="-1" role="dialog" aria-labelledby="editDepartementModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Département</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_departement.php" class="needs-validation" novalidate>
                    <input type="hidden" name="editDepartementId" id="editDepartementId">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editDepartementDesignation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="editDepartementDesignation" id="editDepartementDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="editSectionId" class="form-label">Section <span class="text-danger">*</span></label>
                            <select name="editSectionId" id="editSectionId" class="form-control" required>
                                <!-- Populate with sections -->
                                <?php
                                foreach ($sections as $section) {
                                    echo "<option value='{$section['idsection']}'>{$section['designationSection']} - {$section['anneeDesignation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="updateDepartementBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un manager -->
<div class="modal fade" id="editManagerModal" tabindex="-1" role="dialog" aria-labelledby="editManagerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/update_manager_depart.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="editManagerId" id="editManagerId">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editUserId" class="form-label">Utilisateur <span class="text-danger">*</span></label>
                            <select name="editUserId" id="editUserId" class="form-control" required>
                                <?php
                                foreach ($userss as $user) {
                                    echo "<option value='{$user['idUser']}'>{$user['nomUser']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un utilisateur.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="editFonction" class="form-label">Fonction <span class="text-danger">*</span></label>
                            <input type="text" name="editFonction" id="editFonction" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une fonction.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="currentSignature" class="form-label">Signature Actuelle</label>
                            <div id="currentSignature">
                                <img src="" alt="Signature actuelle" id="currentSignatureImg" width="100">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label for="editSignature" class="form-label">Signature <span class="text-danger">*</span></label>
                            <input type="file" name="editSignature" id="editSignature" class="form-control" accept="image/*">
                            <div class="invalid-feedback">Veuillez importer une signature.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="idAnnee" id="idAnnee" class="form-control" required>
                                <!-- Populate with academic years -->
                                <?php
                                $academicYears = $departement->getAcademicYears();
                                foreach ($academicYears as $year) {
                                    echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="updateManagerBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function editDepartement(id, designation, sectionId) {
        document.getElementById('editDepartementId').value = id;
        document.getElementById('editDepartementDesignation').value = designation;
        document.getElementById('editSectionId').value = sectionId;

        new bootstrap.Modal(document.getElementById('editDepartementModal')).show();
    }

    function confirmDelete(idDepartement) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_departement.php?iddepartement=' + idDepartement;
            }
        });
    }

    function editManager(id, noms, fonction, signature, idUser, annee) {
        document.getElementById('editManagerId').value = id;
        document.getElementById('editUserId').value = idUser;
        document.getElementById('editFonction').value = fonction;
        document.getElementById('idAnnee').value = annee;
        document.getElementById('currentSignatureImg').src = 'uploads/' + signature;

        new bootstrap.Modal(document.getElementById('editManagerModal')).show();
    }

    function confirmDeleteManager(idManager) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_manager_departement.php?idresponsable_departement=' + idManager;
            }
        });
    }

    function setDepartementId(departementId) {
        document.getElementById('managerDepartementId').value = departementId;
    }
</script>

<?php include "./views/include/footer.php"; ?>