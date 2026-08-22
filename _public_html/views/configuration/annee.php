<?php
include "./views/include/header.php";
$academicYear = new Universite();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer l'année académique active
$activeYear = $academicYear->getActiveAcademicYear();
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>ANNÉES ACADÉMIQUES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Années Académiques</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Affichage de l'année académique active -->
            <div class="col-lg-12">
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="bi bi-calendar-check-fill me-2"></i>
                    <div>
                        <strong>Année académique en cours :</strong> 
                        <?php 
                        if ($activeYear) {
                            echo htmlspecialchars($activeYear['designation']);
                        } else {
                            echo '<span class="text-warning">Aucune année académique active</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table années académiques -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des années académiques
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createAcademicYearModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="configuration/annee">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par designation...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Date de Création</th>
                                            <th scope="col">Statut</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeAnneeAcad = $academicYear->getAcademicYears($search);
                                        $i = 1;

                                        foreach ($listeAnneeAcad as $l){
                                            $dc = date('d/m/Y H:i:s', strtotime($l['dateCreation']));
                                            $isActive = isset($l['est_active']) && $l['est_active'] == 1;
                                            $statusBadge = $isActive ? 
                                                '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Active</span>' : 
                                                '<span class="badge bg-secondary"><i class="bi bi-circle"></i> Inactive</span>';
                                            
                                            $activateButton = $isActive ? 
                                                '<button class="btn btn-sm btn-outline-secondary" onclick="toggleAcademicYear(' . $l['idannee_acad'] . ', 0)" title="Désactiver">
                                                    <i class="bi bi-pause-circle"></i> Désactiver
                                                </button>' :
                                                '<button class="btn btn-sm btn-success" onclick="toggleAcademicYear(' . $l['idannee_acad'] . ', 1)" title="Activer">
                                                    <i class="bi bi-play-circle"></i> Activer
                                                </button>';
                                            
                                            echo "
                                            <tr" . ($isActive ? " class='table-success'" : "") . ">
                                                <td>{$i}</td>
                                                <td>
                                                    {$l['designation']}
                                                    " . ($isActive ? '<i class="bi bi-star-fill text-warning ms-2" title="Année en cours"></i>' : '') . "
                                                </td>
                                                <td>{$dc}</td>
                                                <td>{$statusBadge}</td>
                                                <td>
                                                {$activateButton}
                                                <button class='btn btn-sm btn-info ms-1' onclick='copyAcademicYearData(
                                                {$l['idannee_acad']},
                                                \"{$l['designation']}\"
                                                )' title='Copier données'>
                                                <i class='bi bi-copy'></i> Copier
                                                </button>
                                                <button class='btn btn-sm btn-warning ms-1' onclick='editAcademicYear(
                                                {$l['idannee_acad']},
                                                    \"{$l['designation']}\"
                                                    )' title='Modifier'>
                                                         <i class='bi bi-pencil-square'></i> Modifier
                                                     </button>
                                                     <button class='btn btn-sm btn-danger ms-1' onclick='confirmDelete({$l['idannee_acad']})' title='Supprimer'>
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

<!-- Modal pour ajouter une année académique -->
<div class="modal fade" id="createAcademicYearModal" tabindex="-1" role="dialog" aria-labelledby="createAcademicYearModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Année Académique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_academic_year.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    
                    <!-- Option pour définir comme année active -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="setAsActive" name="set_as_active" value="1">
                                <label class="form-check-label" for="setAsActive">
                                    Définir comme année académique en cours
                                </label>
                                <div class="form-text">
                                    <i class="bi bi-info-circle"></i> Si cochée, cette année deviendra l'année académique active et désactivera automatiquement les autres.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php
                    $years = $academicYear->getAcademicYears();
                    if (count($years) > 0) {
                        echo '<div class="mt-4 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="copierDonnees" name="copier_donnees" value="1">
                                    <label class="form-check-label" for="copierDonnees">Copier les données d\'une année existante</label>
                                </div>
                            </div>';
                        
                        echo '<div id="optionsCopie" style="display: none;">';
                        
                        // Sélection de l'année source
                        echo '<div class="row mb-3">
                                <div class="col-md-12">
                                    <label for="annee_source" class="form-label">Année source</label>
                                    <select name="annee_source" id="annee_source" class="form-select">
                                        <option value="">Sélectionnez une année académique</option>';
                                        foreach ($years as $year) {
                                            echo '<option value="' . $year['idannee_acad'] . '">' . $year['designation'] . '</option>';
                                        }
                        echo '</select>
                                </div>
                            </div>';
                        
                        // Options de données à copier
                        echo '<div class="row">
                                <div class="col-md-12">
                                    <label class="form-label">Données à copier :</label>
                                </div>
                            </div>';
                        
                        echo '<div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_sections" name="copier_sections" value="1">
                                        <label class="form-check-label" for="copier_sections">Sections</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_orientations" name="copier_orientations" value="1">
                                        <label class="form-check-label" for="copier_orientations">Orientations</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_promotions" name="copier_promotions" value="1">
                                        <label class="form-check-label" for="copier_promotions">Promotions</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_semestres" name="copier_semestres" value="1">
                                        <label class="form-check-label" for="copier_semestres">Semestres</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_ue" name="copier_ue" value="1">
                                        <label class="form-check-label" for="copier_ue">Unités d\'enseignement (UE)</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_ecue" name="copier_ecue" value="1">
                                        <label class="form-check-label" for="copier_ecue">Éléments Constitutifs (ECUE)</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_ur" name="copier_ur" value="1">
                                        <label class="form-check-label" for="copier_ur">Unités de Recherche (UR)</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_specialisations" name="copier_specialisations" value="1">
                                        <label class="form-check-label" for="copier_specialisations">Spécialisations</label>
                                    </div>
                                </div>
                            </div>';
                            
                        echo '</div>'; // Fin de optionsCopie
                    }
                    ?>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addAcademicYearBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une année académique -->
<div class="modal fade" id="editAcademicYearModal" tabindex="-1" role="dialog" aria-labelledby="editAcademicYearModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Année Académique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_academic_year.php" class="needs-validation" novalidate id="editAcademicYearForm">
                    <input type="hidden" name="idannee_acad" id="editAcademicYearId">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" id="editAcademicYearDesignation" class="form-control" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="editAcademicYearForm" name="editAcademicYearBtn" class="btn btn-primary">
                    <i class="bi bi-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour copier les données d'une année académique -->
<div class="modal fade" id="copyAcademicYearDataModal" tabindex="-1" role="dialog" aria-labelledby="copyAcademicYearDataModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Copier les Données vers une Année Académique</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/copy_academic_year_data.php" class="needs-validation" novalidate>
                    <input type="hidden" name="target_year_id" id="copyTargetYearId">
                    <div class="alert alert-info">
                        <strong>Année cible :</strong> <span id="copyTargetYearDesignation"></span>
                        <br><small>Cette opération copiera les données depuis une autre année sans créer de doublons.</small>
                    </div>

                    <?php
                    $years = $academicYear->getAcademicYears();
                    if (count($years) > 0) {
                        echo '<div class="mt-4 mb-3">
                                <label class="form-label">Sélectionner l\'année source :</label>
                                <select name="annee_source" id="annee_source_copy" class="form-select" required>
                                    <option value="">Sélectionnez une année académique</option>';
                                    foreach ($years as $year) {
                                        echo '<option value="' . $year['idannee_acad'] . '">' . $year['designation'] . '</option>';
                                    }
                        echo '</select>
                            </div>';

                        // Options de données à copier
                        echo '<div class="row">
                                <div class="col-md-12">
                                    <label class="form-label">Données à copier (seules les données manquantes seront ajoutées) :</label>
                                </div>
                            </div>';

                        echo '<div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_sections_copy" name="copier_sections" value="1">
                                        <label class="form-check-label" for="copier_sections_copy">Sections</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_orientations_copy" name="copier_orientations" value="1">
                                        <label class="form-check-label" for="copier_orientations_copy">Orientations</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_promotions_copy" name="copier_promotions" value="1">
                                        <label class="form-check-label" for="copier_promotions_copy">Promotions</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_semestres_copy" name="copier_semestres" value="1">
                                        <label class="form-check-label" for="copier_semestres_copy">Semestres</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_ue_copy" name="copier_ue" value="1">
                                        <label class="form-check-label" for="copier_ue_copy">Unités d\'enseignement (UE)</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_ecue_copy" name="copier_ecue" value="1">
                                        <label class="form-check-label" for="copier_ecue_copy">Éléments Constitutifs (ECUE)</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_ur_copy" name="copier_ur" value="1">
                                        <label class="form-check-label" for="copier_ur_copy">Unités de Recherche (UR)</label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="copier_specialisations_copy" name="copier_specialisations" value="1">
                                        <label class="form-check-label" for="copier_specialisations_copy">Spécialisations</label>
                                    </div>
                                </div>
                            </div>';
                    }
                    ?>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="copyAcademicYearDataBtn" class="btn btn-primary">
                            <i class="bi bi-copy"></i> Copier les données
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function editAcademicYear(id, designation) {
        document.getElementById('editAcademicYearId').value = id;
        document.getElementById('editAcademicYearDesignation').value = designation;

        new bootstrap.Modal(document.getElementById('editAcademicYearModal')).show();
    }

    function copyAcademicYearData(id, designation) {
        document.getElementById('copyTargetYearId').value = id;
        document.getElementById('copyTargetYearDesignation').textContent = designation;

        new bootstrap.Modal(document.getElementById('copyAcademicYearDataModal')).show();
    }

    function confirmDelete(idAnneeAcad) {
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
                window.location.href = 'controller/delete_academic_year.php?idannee_acad=' + idAnneeAcad;
            }
        });
    }

    function toggleAcademicYear(idAnneeAcad, newStatus) {
        const action = newStatus === 1 ? 'activer' : 'désactiver';
        const actionText = newStatus === 1 ? 'Activer' : 'Désactiver';
        
        Swal.fire({
            title: 'Confirmation',
            text: `Voulez-vous vraiment ${action} cette année académique ?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus === 1 ? '#28a745' : '#6c757d',
            cancelButtonColor: '#d33',
            confirmButtonText: `Oui, ${action}`,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Afficher un loader
                Swal.fire({
                    title: 'Traitement en cours...',
                    text: `${actionText} de l'année académique`,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Rediriger vers le contrôleur
                window.location.href = `controller/toggle_academic_year.php?idannee_acad=${idAnneeAcad}&status=${newStatus}`;
            }
        });
    }

    // Ajout du script pour les options de copie
    document.addEventListener('DOMContentLoaded', function() {
        const copierDonneesCheckbox = document.getElementById('copierDonnees');
        const optionsCopie = document.getElementById('optionsCopie');
        
        if (copierDonneesCheckbox) {
            copierDonneesCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    optionsCopie.style.display = 'block';
                } else {
                    optionsCopie.style.display = 'none';
                }
            });
        }
    });
</script>

<?php include "./views/include/footer.php"; ?>