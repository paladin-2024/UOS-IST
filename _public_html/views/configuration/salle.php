<?php
include "./views/include/header.php";
$universite = new Universite();

$search = isset($_GET['search']) ? $_GET['search'] : '';
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>GESTION DES SALLES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Salles</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table des salles -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des salles
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createSalleModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="configuration/salle">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par désignation...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Date de Création</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeSalles = $universite->getSalles($search);
                                        $i = 1;

                                        foreach ($listeSalles as $salle){
                                            $dc = date('d/m/Y H:i:s', strtotime($salle['dateCreation']));
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$salle['designationSalle']}</td>
                                                <td>{$dc}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='editSalle(
                                                        {$salle['idSalle']}, 
                                                        \"{$salle['designationSalle']}\"
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i> Modifier
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$salle['idSalle']})'>
                                                        <i class='bi bi-trash'></i> Supprimer
                                                    </button>
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

<!-- Modal pour ajouter une salle -->
<div class="modal fade" id="createSalleModal" tabindex="-1" role="dialog" aria-labelledby="createSalleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Salle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_salle.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designationSalle" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designationSalle" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addSalleBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une salle -->
<div class="modal fade" id="editSalleModal" tabindex="-1" role="dialog" aria-labelledby="editSalleModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Salle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_salle.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idSalle" id="editSalleId">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designationSalle" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designationSalle" id="editSalleDesignation" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editSalleBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function editSalle(id, designation) {
        document.getElementById('editSalleId').value = id;
        document.getElementById('editSalleDesignation').value = designation;

        new bootstrap.Modal(document.getElementById('editSalleModal')).show();
    }

    function confirmDelete(idSalle) {
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
                window.location.href = 'controller/delete_salle.php?idSalle=' + idSalle;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>
