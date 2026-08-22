<?php
include "./views/include/header.php";
$grade = new Grade();
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>GRADES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Grades</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table grades -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des grades
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createGradeModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
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
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Description</th>
                                            <th scope="col">Type d'agent</th>
                                            <th scope="col">Date de création</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeGrades = $grade->getGrades();
                                        $i = 1;

                                        foreach ($listeGrades as $g) {
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$g['designation']}</td>
                                                <td>" . (empty($g['description']) ? '-' : $g['description']) . "</td>
                                                <td>{$g['type_agent']}</td>
                                                <td>" . date('d/m/Y H:i', strtotime($g['dateCreation'])) . "</td>
                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='editGrade(
                                                        {$g['idgrade']}, 
                                                        \"" . addslashes($g['designation']) . "\",
                                                        \"" . addslashes($g['description']) . "\",
                                                        \"" . addslashes($g['type_agent']) . "\"
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i> Modifier
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$g['idgrade']})'>
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

<!-- Modal pour ajouter un grade -->
<div class="modal fade" id="createGradeModal" tabindex="-1" role="dialog" aria-labelledby="createGradeModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_grade.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designation" class="form-label">Abbréviation Grade <span class="text-danger">*</span></label>
                            <input type="text" name="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="type_agent" class="form-label">Type d'agent <span class="text-danger">*</span></label>
                            <select name="type_agent" class="form-control" required>
                                <option value="">Sélectionner un type</option>
                                <option value="Enseignant">Enseignant</option>
                                <option value="Administratif">Administratif</option>
                                <option value="Recherche">Recherche</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un type d'agent.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Désignation complète</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addGradeBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un grade -->
<div class="modal fade" id="editGradeModal" tabindex="-1" role="dialog" aria-labelledby="editGradeModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Grade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_grade.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idgrade" id="editGradeId">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designation" class="form-label">Abbréviation Grade <span class="text-danger">*</span></label>
                            <input type="text" name="designation" id="editGradeDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="type_agent" class="form-label">Type d'agent <span class="text-danger">*</span></label>
                            <select name="type_agent" id="editGradeType" class="form-control" required>
                                <option value="">Sélectionner un type</option>
                                <option value="Enseignant">Enseignant</option>
                                <option value="Administratif">Administratif</option>
                                <option value="Recherche">Recherche</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un type d'agent.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="description" class="form-label">Désignation complète</label>
                            <textarea name="description" id="editGradeDescription" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editGradeBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function editGrade(id, designation, description, type_agent) {
        document.getElementById('editGradeId').value = id;
        document.getElementById('editGradeDesignation').value = designation;
        document.getElementById('editGradeDescription').value = description || '';
        
        // Pré-sélectionner le type d'agent
        let typeSelect = document.getElementById('editGradeType');
        for (let i = 0; i < typeSelect.options.length; i++) {
            if (typeSelect.options[i].value === type_agent) {
                typeSelect.options[i].selected = true;
                break;
            }
        }

        new bootstrap.Modal(document.getElementById('editGradeModal')).show();
    }

    function confirmDelete(idgrade) {
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
                window.location.href = 'controller/delete_grade.php?idgrade=' + idgrade;
            }
        });
    }

    // Recherche dans le tableau
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let input = this.value.toLowerCase();
        let rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(input) ? '' : 'none';
        });
    });

    // Validation des formulaires
    (function() {
        'use strict';
        
        // Fetch all forms we want to apply custom validation styles to
        var forms = document.querySelectorAll('.needs-validation');
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>

<?php include "./views/include/footer.php"; ?>

