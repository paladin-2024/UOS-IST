<?php
include "./views/include/header.php";
$service = new Service();
$structure = new Structure();
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>SERVICES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Services</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table services -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des services
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
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Responsable</th>
                                            <th scope="col">Structure</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeService = $service->getService();
                                        $i = 1;

                                        foreach ($listeService as $l) {
                                            $ver1 = $structure->getUserPermissionStructure($_SESSION['id'], $l['idStructure']);
                                            if ($ver1->fetch()) {
                                                    echo "
                                                    <tr>
                                                        <td>{$i}</td>
                                                        <td>{$l['designationService']}</td>
                                                        <td>{$l['responsable']}</td>
                                                        <td>{$l['designationStructure']}</td>
                                                        <td>
                                                            <button class='btn btn-sm btn-warning' onclick='editService(
                                                                {$l['idService']}, 
                                                                \"{$l['designationService']}\",
                                                                \"{$l['responsable']}\",
                                                                {$l['idStructure']}
                                                            )'>
                                                                <i class='bi bi-pencil-square'></i> Modifier
                                                            </button>
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
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->



<!-- Modal pour modifier un service -->
<div class="modal fade" id="editServiceModal" tabindex="-1" role="dialog" aria-labelledby="editServiceModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_service.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idService" id="editServiceId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" id="editServiceDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="responsable" class="form-label">Responsable</label>
                            <input type="text" name="responsable" id="editServiceResponsable" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="structure" class="form-label">Structure <span class="text-danger">*</span></label>
                            <select name="idStructure" id="editServiceStructure" class="form-control" required>
                                <?php
                                $util = $structure->getStructures();
                                foreach ($util as $a) {
                                    $ver = $structure->getUserPermissionStructure($_SESSION['id'], $a['idStructure']);
                                    if ($ver->fetch()) {
                                        echo "<option value='{$a['idStructure']}'>{$a['designation']}</option>";
                                    }
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une structure.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editServiceBtn2" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function editService(id, designation, responsable, idStructure) {
        document.getElementById('editServiceId').value = id;
        document.getElementById('editServiceDesignation').value = designation;
        document.getElementById('editServiceResponsable').value = responsable;

        // Pré-sélectionner la structure concernée
        let structureSelect = document.getElementById('editServiceStructure');
        for (let i = 0; i < structureSelect.options.length; i++) {
            if (structureSelect.options[i].value == idStructure) {
                structureSelect.options[i].selected = true;
                break;
            }
        }

        new bootstrap.Modal(document.getElementById('editServiceModal')).show();
    }

    function confirmDelete(idService) {
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
                window.location.href = 'controller/delete_service.php?idService=' + idService;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>