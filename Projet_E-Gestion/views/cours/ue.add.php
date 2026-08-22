<?php
include "./views/include/header.php";
$unite = new Unite();

$search = isset($_GET['search']) ? $_GET['search'] : '';
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>UNITÉS D'ENSEIGNEMENT</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Unités d'Enseignement</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table UE -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des unités d'enseignement
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createUEModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="configuration/ue">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par code, désignation...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Code UE</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Semestre</th>
                                            <th scope="col">Promotion</th>
                                            <th scope="col">Volume horaire</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeUEs = $unite->getUEs($search);
                                        $i = 1;

                                        foreach ($listeUEs as $ue) {
                                            $volumeHoraire = "CM: {$ue['CMI']}h, TD: {$ue['TD']}h, TP: {$ue['TP']}h";
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$ue['codeUE']}</td>
                                                <td>{$ue['designationUE']}</td>
                                                <td>{$ue['numeroSemestre']}</td>
                                                <td>{$ue['designationPromotion']} / {$ue['annee']}</td>
                                                <td>{$volumeHoraire}</td>
                                                <td>
                                                    <a href='index.php?view=configuration/ecue&idUE={$ue['idUE']}' class='btn btn-sm btn-info'>
                                                        <i class='bi bi-list-check'></i> ECUEs
                                                    </a>
                                                    <button class='btn btn-sm btn-warning' onclick='editUE(
                                                        {$ue['idUE']}, 
                                                        \"{$ue['codeUE']}\",
                                                        \"{$ue['designationUE']}\",
                                                        {$ue['CMI']},
                                                        {$ue['TD']},
                                                        {$ue['TP']},
                                                        {$ue['semestre_idsemestre']}
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i> Modifier
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$ue['idUE']})'>
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

<!-- Modal pour ajouter une UE -->
<div class="modal fade" id="createUEModal" tabindex="-1" role="dialog" aria-labelledby="createUEModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Unité d'Enseignement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_ue.php" class="needs-validation">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="codeUE" class="form-label">Code UE <span class="text-danger">*</span></label>
                            <input type="text" name="codeUE" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un code.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="semestre_idsemestre" class="form-label">Semestre <span class="text-danger">*</span></label>
                            <select name="semestre_idsemestre" class="form-select" required>
                                <option value="">Sélectionnez un semestre</option>
                                <?php
                                $semestres = $universite->getSemestres();
                                foreach ($semestres as $semestre) {
                                    echo "<option value='{$semestre['idsemestre']}'>{$semestre['numeroSemestre']} - {$semestre['designationPromotion']} / {$semestre['annee']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un semestre.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designationUE" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designationUE" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="CMI" class="form-label">CM (heures)</label>
                            <input type="number" name="CMI" class="form-control" value="0" min="0" step="0.5">
                        </div>
                        <div class="col-md-4">
                            <label for="TD" class="form-label">TD (heures)</label>
                            <input type="number" name="TD" class="form-control" value="0" min="0" step="0.5">
                        </div>
                        <div class="col-md-4">
                            <label for="TP" class="form-label">TP (heures)</label>
                            <input type="number" name="TP" class="form-control" value="0" min="0" step="0.5">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addUEBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une UE -->
<div class="modal fade" id="editUEModal" tabindex="-1" role="dialog" aria-labelledby="editUEModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Unité d'Enseignement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_ue.php" class="needs-validation">
                    <input type="hidden" name="idUE" id="editUEId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="codeUE" class="form-label">Code UE <span class="text-danger">*</span></label>
                            <input type="text" name="codeUE" id="editUECode" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un code.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="semestre_idsemestre" class="form-label">Semestre <span class="text-danger">*</span></label>
                            <select name="semestre_idsemestre" id="editUESemestre" class="form-select" required>
                                <option value="">Sélectionnez un semestre</option>
                                <?php
                                foreach ($semestres as $semestre) {
                                    echo "<option value='{$semestre['idsemestre']}'>{$semestre['numeroSemestre']} - {$semestre['designationPromotion']} / {$semestre['annee']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un semestre.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designationUE" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designationUE" id="editUEDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="CMI" class="form-label">CM (heures)</label>
                            <input type="number" name="CMI" id="editUECMI" class="form-control" value="0" min="0" step="0.5">
                        </div>
                        <div class="col-md-4">
                            <label for="TD" class="form-label">TD (heures)</label>
                            <input type="number" name="TD" id="editUETD" class="form-control" value="0" min="0" step="0.5">
                        </div>
                        <div class="col-md-4">
                            <label for="TP" class="form-label">TP (heures)</label>
                            <input type="number" name="TP" id="editUETP" class="form-control" value="0" min="0" step="0.5">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editUEBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function editUE(id, code, designation, cmi, td, tp, semestreId) {
        document.getElementById('editUEId').value = id;
        document.getElementById('editUECode').value = code;
        document.getElementById('editUEDesignation').value = designation;
        document.getElementById('editUECMI').value = cmi;
        document.getElementById('editUETD').value = td;
        document.getElementById('editUETP').value = tp;
        document.getElementById('editUESemestre').value = semestreId;

        new bootstrap.Modal(document.getElementById('editUEModal')).show();
    }

    function confirmDelete(idUE) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action supprimera également tous les ECUEs associés à cette UE !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_ue.php?idUE=' + idUE;
            }
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>