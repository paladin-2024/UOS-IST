<?php
include "./views/include/header.php";
$mod=new Module();
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>MODULES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Modules</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashbord -->
    <section class="section dashboard">
        <div class="row">
            <!-- TAbele data -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table service -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des Modules
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createModuleModal" class="btnPage"><i class="bi bi-plus-circle-fill"></i> Ajouter</a>
                                    </span>
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <input type="text" id="searchInput" class="form-control" placeholder="Rechercher...">
                                    </div>
                                </div>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Module</th>
                                            <th scope="col">Package</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                    <?php
                                    $listeMod = $mod->getModules(); // Récupération de la liste des modules
                                    $i = 1; // Compteur
                                    while ($l = $listeMod->fetch()) {
                                        echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$l['nomMod']}</td>
                                                <td>{$l['package']}</td>
                                                <td>
                                                    <button class='btn btn-primary btn-sm me-1' 
                                                        onclick=\"editModule({$l['idMod']}, '{$l['nomMod']}', '{$l['package']}')\">
                                                        <span class='bi bi-pencil-square'></span> Modifier
                                                    </button>
                                                    <a href='configuration/permissions&m={$l['idMod']}' class='btn btn-warning'>
                                                        <span class='bi bi-gear-wide-connected'></span> Permissions
                                                    </a>
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
                    </div><!-- End Recent messages -->

                </div>
            </div><!-- End table data -->
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour ajouter un module -->
<div class="modal fade" id="createModuleModal" tabindex="-1" role="dialog" aria-labelledby="createModuleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_module.php" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="titre" class="form-label">Nom du module <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" name="nomMod" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="titre" class="form-label">Package <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" name="package" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addClasseBtn" class="btnModSave ladda-button" data-style="zoom-out">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un module -->
<div class="modal fade" id="editModuleModal" tabindex="-1" role="dialog" aria-labelledby="editModuleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un module</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_module.php" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="titre" class="form-label">Nom du module <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="hidden" name="idMod" id="editModuleId">
                                <input type="text" name="nomMod" id="editNomMod" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="titre" class="form-label">Package <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" name="package" id="editPackage" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addClasseBtn" class="btnModSave ladda-button" data-style="zoom-out">
                            <i class="bi bi-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
        

    // Fonction pour ouvrir la modale de modification avec les données actuelles du module
    function editModule(id, nomMod, packages) {
        document.getElementById('editModuleId').value = id;
        document.getElementById('editNomMod').value = nomMod;
        document.getElementById('editPackage').value = packages;
        $('#editModuleModal').modal('show');
    }
</script>

<?php include "./views/include/footer.php"; ?>