<?php
include "./views/include/header.php";

// Récupérer l'idMod à partir de la requête GET
$idMod = isset($_GET['m']) ? intval($_GET['m']) : 0;

$search = isset($_GET['search']) ? $_GET['search'] : ''; // Requête de recherche

$perm = new Module();

// Vérifiez si l'ID est valide, sinon redirigez ou affichez un message d'erreur
if ($idMod == 0) {
    echo "<script>
        window.history.back();
        </script>";
    exit;
}

$moduleModel = new Module();
$modules = $moduleModel->getAllModules();

$moduleData = $moduleModel->getModuleById($idMod);
$nomModule = $moduleData['nomMod'];
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1><?= mb_strtoupper($nomModule) ?></h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Permissions</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashbord -->
    <section class="section dashboard">
        <div class="row">
            <!-- Table data -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table service -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des Permissions pour un module
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createPermissionModal" class="btnPage"><i class="bi bi-plus-circle-fill"></i> Ajouter</a>
                                        | <a href="index.php?view=configuration/modules" class="btnPageReturn"><i class="bi bi-arrow-return-left"></i> Retour</a>
                                    </span>
                                </h5>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <form method="GET" action="" class="d-flex">
                                            <!-- Conserver le paramètre `view` -->
                                            <input type="hidden" name="view" value="configuration/permissions">
                                            <!-- Conserver l'identifiant du module -->
                                            <input type="hidden" name="m" value="<?= htmlspecialchars($idMod) ?>">

                                            <!-- Champ de recherche -->
                                            <input
                                                type="text"
                                                name="search"
                                                value="<?= htmlspecialchars($search ?? '') ?>"
                                                class="form-control me-2"
                                                placeholder="Rechercher...">

                                            <!-- Bouton de recherche -->
                                            <button type="submit" class="btn btn-primary">Rechercher</button>
                                        </form>
                                    </div>
                                </div>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th>N°</th>
                                            <th>Permission</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php

                                        $limit = 20; // Nombre de résultats à afficher
                                        $listePerm = $perm->getPermissionByMod($idMod, $search, $limit);
                                        $i = 1; // Assurez-vous d'initialiser la variable $i
                                        while ($l = $listePerm->fetch()) {
                                            echo "
    <tr>
        <td>{$i}</td>
        <td>{$l['package']}/{$l['nomPerm']}</td>
        <td>{$l['codePerm']}</td>
        <td>{$l['descPerm']}</td>
        <td>
            <button class='btn btn-primary btn-sm me-1' 
                    onclick=\"editPermission({$l['idPerm']}, {$l['idMod']}, '" . addslashes($l['codePerm']) . "', '" . addslashes($l['nomPerm']) . "', '" . addslashes($l['descPerm']) . "')\">
                <span class='bi bi-pencil-square'></span> Modifier
            </button>
            <button class='btn btn-danger btn-sm' 
                    onclick=\"deletePermission({$l['idPerm']})\">
                <span class='bi bi-trash'></span>
            </button>
        </td>
    </tr>
";

                                            $i++;
                                        }
                                        // Si aucun résultat trouvé
                                        if ($i === 1) {
                                            echo "<tr><td colspan='5' class='text-center'>Aucun résultat trouvé</td></tr>";
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

<!-- Modal pour ajouter une permission -->
<div class="modal fade" id="createPermissionModal" tabindex="-1" role="dialog" aria-labelledby="createPermissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une permission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_permission.php" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="titre" class="form-label">Action</label>
                            <div class="input-group has-validation">
                                <input type="hidden" name="idMod" value="<?= $idMod ?>">
                                <input type="text" name="codePerm" class="form-control" required>
                                <div class="invalid-feedback">Veillez saisir quelquue chose SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="titre" class="form-label">Nom du fichier</label>
                            <div class="input-group has-validation">
                                <input type="text" name="nomPerm" class="form-control" required>
                                <div class="invalid-feedback">Veillez saisir quelquue chose SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="titre" class="form-label">Affichage</label>
                            <div class="input-group has-validation">
                                <textarea name="descPerm" class="form-control" rows="3" required></textarea>
                                <div class="invalid-feedback">Veillez saisir quelquue chose SVP !</div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addClasseBtn" class="btnModSave ladda-button" data-style="zoom-out">
                            <div class="ladda-label">Enregistrer</div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une permission -->
<div class="modal fade" id="editPermissionModal" tabindex="-1" role="dialog" aria-labelledby="editPermissionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une permission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <form method="POST" action="controller/edit_permission.php" class="tab-pane needs-validation ladda-form" novalidate>
                    <div class="row">
                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="titre" class="form-label">Action</label>
                            <div class="input-group has-validation">
                                <input type="hidden" name="idPerm" id="editPermissionId">
                                <input type="hidden" name="idMod" id="editPermissionModule">
                                <input type="text" name="codePerm" id="editCodePerm" class="form-control" required>
                                <div class="invalid-feedback">Veillez saisir quelquue chose SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="titre" class="form-label">Nom du fichier</label>
                            <div class="input-group has-validation">
                                <input type="text" name="nomPerm" id="editNomPerm" class="form-control" required>
                                <div class="invalid-feedback">Veillez saisir quelquue chose SVP !</div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 mb-3">
                            <label for="titre" class="form-label">Affichage</label>
                            <div class="input-group has-validation">
                                <textarea name="descPerm" id="editDescPerm" class="form-control" rows="3" required></textarea>
                                <div class="invalid-feedback">Veillez saisir quelquue chose SVP !</div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btnModClose" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addClasseBtn" class="btnModSave ladda-button" data-style="zoom-out">
                            <div class="ladda-label">Enregistrer</div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Fonction pour ouvrir la modale de modification avec les données actuelles de la permission
    function editPermission(id, idMod, codePerm, nomPerm, descPerm) {
        document.getElementById('editPermissionId').value = id;
        document.getElementById('editPermissionModule').value = idMod;
        document.getElementById('editCodePerm').value = codePerm;
        document.getElementById('editNomPerm').value = nomPerm;
        document.getElementById('editDescPerm').value = descPerm;
        $('#editPermissionModal').modal('show');
    }

    // Fonction pour supprimer une permission
    function deletePermission(idPerm) {

        Swal.fire({
            title: 'Êtes-vous sûr de vouloir supprimer cette permission ?',
            text: "Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer!',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_permission.php?idPerm=' + idPerm;
            }
        })
    }
</script>

<?php include "./views/include/footer.php"; ?>