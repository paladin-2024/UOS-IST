<?php
include "./views/include/header.php";
$universite = new Universite();
$sujetModel = new Sujet();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer les données nécessaires
$academicYears = $universite->getAcademicYears();
$specialisations = $universite->getAllSpecialisations(); // Récupération des spécialisations
$sujets = $sujetModel->getAllSujets($search); // Utilisation du nouveau modèle
$enseignants = $sujetModel->getEnseignants(); // Récupération des enseignants

?>

<style>
    /* Style pour s'assurer que le modal est toujours au-dessus */
    .modal-super {
        z-index: 9999 !important; /* Valeur élevée pour être sûr d'être au-dessus */
    }
    
    /* Style pour le backdrop (fond sombre) */
    .modal-backdrop-super {
        z-index: 9998 !important;
    }
</style>
<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>SUJETS DE RECHERCHE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Sujets de Recherche</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="row">
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Liste des Sujets de Recherche
                                    <button type="button" class="btn btn-primary float-end" data-bs-toggle="modal" data-bs-target="#createSujetModal">
                                        <i class="bi bi-plus-circle"></i> Nouveau Sujet
                                    </button>
                                    <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#exportModal">
                                        <i class="bi bi-file-excel"></i> Exporter
                                    </button>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <div class="input-group">
                                        <input type="hidden" name="view" value="recherche/sujets">
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher un sujet...">
                                        <button type="submit" class="btn btn-primary">Rechercher</button>
                                    </div>
                                </form>

                                
                                <!-- Modal pour l'exportation -->
                                <div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true" data-bs-backdrop="static">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Exporter les Sujets de Recherche</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" action="controller/export_sujets.php">
                                                    <div class="mb-3">
                                                        <label for="annee_export" class="form-label">Sélectionner l'année académique</label>
                                                        <select name="annee_export" id="annee_export" class="form-control" required>
                                                            <option value="">Sélectionner une année académique</option>
                                                            <?php foreach ($academicYears as $year): ?>
                                                                <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                        <button type="submit" class="btn btn-success">
                                                            <i class="bi bi-file-excel"></i> Exporter
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Intitulé</th>
                                            <th scope="col">Cycle</th>
                                            <th scope="col">Spécialisation</th>
                                            <th scope="col">Directeur</th>
                                            <th scope="col">Encadreur</th>
                                            <th scope="col">État</th>
                                            <th scope="col">Année Académique</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $i = 1;
                                        foreach ($sujets as $sujet) {
                                            // Récupérer les noms des enseignants
                                            $directeurNom = "Non assigné";
                                            $encadreurNom = "Non assigné";
                                            
                                            if (!empty($sujet['idDirecteur'])) {
                                                foreach ($enseignants as $enseignant) {
                                                    if ($enseignant['idAgent'] == $sujet['idDirecteur']) {
                                                        $directeurNom = $enseignant['noms'];
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            if (!empty($sujet['idEncadreur'])) {
                                                foreach ($enseignants as $enseignant) {
                                                    if ($enseignant['idAgent'] == $sujet['idEncadreur']) {
                                                        $encadreurNom = $enseignant['noms'];
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // Échapper proprement l'intitulé pour JavaScript
                                            $intituleJS = htmlspecialchars(addslashes($sujet['intitule']), ENT_QUOTES);
                                            
                                            $idDirecteur = isset($sujet['idDirecteur']) ? $sujet['idDirecteur'] : 'null';
                                            $idEncadreur = isset($sujet['idEncadreur']) ? $sujet['idEncadreur'] : 'null';
                                            
                                            echo "<tr>
                                                <td>{$i}</td>
                                                <td>{$sujet['intitule']}</td>
                                                <td>{$sujet['cycle']}</td>
                                                <td>{$sujet['specialisation']}</td>
                                                <td>{$directeurNom}</td>
                                                <td>{$encadreurNom}</td>
                                                <td>{$sujet['etatSujet']}</td>
                                                <td>{$sujet['annee']}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-primary' onclick='openEditSujetModal({$sujet['idsujets']}, \"{$intituleJS}\", \"{$sujet['cycle']}\", {$sujet['idSpecialisation']}, {$sujet['annee_acad_idannee_acad']}, {$idDirecteur}, {$idEncadreur})'>
                                                        <i class='bi bi-pencil'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDeleteSujet({$sujet['idsujets']})'>
                                                        <i class='bi bi-trash'></i>
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
                    </div>
                </div>
            </div>
        </div>
    </section>    </section>
</main>

<!-- Modal pour ajouter un sujet -->
<div class="modal fade modal-supe" id="createSujetModal" tabindex="-1" role="dialog" aria-labelledby="createSujetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Sujet de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="sujetForm" method="POST" action="controller/create_sujet.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="intitule" class="form-label">Intitulé du Sujet</label>
                            <textarea name="intitule" id="intitule" class="form-control" rows="3" required></textarea>
                            <div class="invalid-feedback">Veuillez entrer l'intitulé du sujet.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="cycle" class="form-label">Cycle</label>
                            <select name="cycle" id="cycle" class="form-control" required>
                                <option value="">Sélectionner un cycle</option>
                                <option value="Premier">Premier</option>
                                <option value="Deuxieme">Deuxième</option>
                                <option value="Troisieme">Troisième</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un cycle.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="idSpecialisation" class="form-label">Spécialisation</label>
                            <select name="idSpecialisation" id="idSpecialisation" class="form-control" required>
                                <option value="">Sélectionner une spécialisation</option>
                                <?php foreach ($specialisations as $specialisation): ?>
                                    <option value="<?= $specialisation['idSpecialisation'] ?>"><?= $specialisation['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une spécialisation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="directeur" class="form-label">Directeur (optionnel)</label>
                            <select name="directeur" id="directeur" class="form-control">
                                <option value="">Sélectionner un directeur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>"><?= $enseignant['noms'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="encadreur" class="form-label">Encadreur (optionnel)</label>
                            <select name="encadreur" id="encadreur" class="form-control">
                                <option value="">Sélectionner un encadreur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>"><?= $enseignant['noms'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="annee_acad" class="form-label">Année Académique</label>
                            <select name="annee_acad" id="annee_acad" class="form-control" required>
                                <option value="">Sélectionner une année académique</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addSujetBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un sujet -->
<div class="modal fade" id="editSujetModal" tabindex="-1" role="dialog" aria-labelledby="editSujetModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Sujet de Recherche</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editSujetForm" method="POST" action="controller/edit_sujet.php" class="needs-validation" novalidate>
                    <input type="hidden" name="idsujets" id="editIdSujet">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editIntitule" class="form-label">Intitulé du Sujet</label>
                            <textarea name="intitule" id="editIntitule" class="form-control" rows="3" required></textarea>
                            <div class="invalid-feedback">Veuillez entrer l'intitulé du sujet.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editCycle" class="form-label">Cycle</label>
                            <select name="cycle" id="editCycle" class="form-control" required>
                                <option value="">Sélectionner un cycle</option>
                                <option value="Premier">Premier</option>
                                <option value="Deuxieme">Deuxième</option>
                                <option value="Troisieme">Troisième</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un cycle.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editIdSpecialisation" class="form-label">Spécialisation</label>
                            <select name="idSpecialisation" id="editIdSpecialisation" class="form-control" required>
                                <option value="">Sélectionner une spécialisation</option>
                                <?php foreach ($specialisations as $specialisation): ?>
                                    <option value="<?= $specialisation['idSpecialisation'] ?>"><?= $specialisation['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une spécialisation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editDirecteur" class="form-label">Directeur (optionnel)</label>
                            <select name="directeur" id="editDirecteur" class="form-control">
                                <option value="">Sélectionner un directeur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>"><?= $enseignant['noms'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editEncadreur" class="form-label">Encadreur (optionnel)</label>
                            <select name="encadreur" id="editEncadreur" class="form-control">
                                <option value="">Sélectionner un encadreur</option>
                                <?php foreach ($enseignants as $enseignant): ?>
                                    <option value="<?= $enseignant['idAgent'] ?>"><?= $enseignant['noms'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="editAnneeAcad" class="form-label">Année Académique</label>
                            <select name="annee_acad_idannee_acad" id="editAnneeAcad" class="form-control" required>
                                <option value="">Sélectionner une année académique</option>
                                <?php foreach ($academicYears as $year): ?>
                                    <option value="<?= $year['idannee_acad'] ?>"><?= $year['designation'] ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editSujetBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Validation des formulaires Bootstrap
    (function() {
        'use strict';
        
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll('.needs-validation');
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function(form) {
            form.addEventListener('submit', function(event) {
                // Vérification que le directeur et l'encadreur ne sont pas la même personne
                const directeur = form.querySelector('[name="directeur"]');
                const encadreur = form.querySelector('[name="encadreur"]');
                
                if (directeur && encadreur && 
                    directeur.value && encadreur.value && 
                    directeur.value === encadreur.value) {
                    event.preventDefault();
                    event.stopPropagation();
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Le directeur et l\'encadreur ne peuvent pas être la même personne.'
                    });
                    return;
                }
                
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    })();

    function openEditSujetModal(id, intitule, cycle, idSpecialisation, anneeAcad, directeur, encadreur) {
        document.getElementById('editIdSujet').value = id;
        document.getElementById('editIntitule').value = intitule;
        document.getElementById('editCycle').value = cycle;
        document.getElementById('editIdSpecialisation').value = idSpecialisation;
        document.getElementById('editAnneeAcad').value = anneeAcad;
        
        // Gestion des valeurs null pour directeur et encadreur
        if (directeur && directeur !== 'null') {
            document.getElementById('editDirecteur').value = directeur;
        } else {
            document.getElementById('editDirecteur').value = '';
        }
        
        if (encadreur && encadreur !== 'null') {
            document.getElementById('editEncadreur').value = encadreur;
        } else {
            document.getElementById('editEncadreur').value = '';
        }
        
        new bootstrap.Modal(document.getElementById('editSujetModal')).show();
    }

    function confirmDeleteSujet(idSujet) {
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
                window.location.href = 'controller/delete_sujet.php?idsujets=' + idSujet;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modals = document.querySelectorAll('.modal-super');
        modals.forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => {
                    backdrop.classList.add('modal-backdrop-super');
                });
            });
        });
    });
</script>

<?php include "./views/include/footer_file.php"; ?>

