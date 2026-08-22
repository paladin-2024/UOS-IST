<?php
include "./views/include/header.php";
$roles=new Role();
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>ROLES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Rôles</li>
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
                                    Gestion des Rôles
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createRoleModal" class="btnPage"><i class="bi bi-plus-circle-fill"></i> Ajouter</a>
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
                                            <th scope="col">Rôle</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <!-- Les données seront chargées ici dynamiquement -->
                                        <?php
                                        $listeRole = $roles->getAllRoles(); // Récupération de tous les rôles
                                        $i = 1; // Compteur initialisé à 1

                                        while ($l = $listeRole->fetch()) { // Parcours des rôles
                                            // Utilisation de l'échappement et des accolades pour une bonne interpolation
                                            echo "
                                                <tr>
                                                    <td>{$i}</td>
                                                    <td>{$l['nomRole']}</td>
                                                    <td>
                                                        <!-- Bouton pour modifier le rôle -->
                                                        <button class='btn btn-primary btn-sm me-1' 
                                                                onclick='editRole({$l['idRole']}, \"{$l['nomRole']}\")'>
                                                            <span class='bi bi-pencil-square'></span> Modifier
                                                        </button>
                                                        
                                                        <!-- Lien pour les permissions -->
                                                        <a href='configuration/userPermissions&r={$l['idRole']}' 
                                                        class='btn btn-warning btn-sm'>
                                                            <span class='bi bi-gear-wide-connected'></span> Permissions
                                                        </a>
                                                    </td>
                                                </tr>
                                            ";
                                            $i++; // Incrémentation du compteur
                                        }
                                        ?>


                                    </tbody>
                                </table>
                                <div id="loading" class="text-center d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                </div>


                            </div>

                        </div>
                    </div><!-- End Recent messages -->

                </div>
            </div><!-- End table data -->
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour ajouter un rôle -->
<div class="modal fade" id="createRoleModal" tabindex="-1" role="dialog" aria-labelledby="createRoleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un rôle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_role.php" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nomRole" class="form-label">Nom du rôle <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="text" name="nomRole" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addRoleBtn" class="btnModSave ladda-button" data-style="zoom-out">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un rôle -->
<div class="modal fade" id="editRoleModal" tabindex="-1" role="dialog" aria-labelledby="editRoleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un rôle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_role.php" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nomRole" class="form-label">Nom du rôle <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <input type="hidden" name="idRole" id="editRoleId">
                                <input type="text" name="nomRole" id="editRoleNomMod" class="form-control" required>
                                <div class="invalid-feedback">Veuillez saisir quelque chose SVP !</div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" name="description" id="editRoleDescription" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editRoleBtn" class="btnModSave ladda-button" data-style="zoom-out">
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
    function editRole(id, nomRole) {
        document.getElementById('editRoleId').value = id;
        document.getElementById('editRoleNomMod').value = nomRole;
        $('#editRoleModal').modal('show');
    }
</script>

<?php include "./views/include/footer.php"; ?>