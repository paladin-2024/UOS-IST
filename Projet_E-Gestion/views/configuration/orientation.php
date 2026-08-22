<?php
include "./views/include/header.php";
$universite = new Universite();

// Fetch only teacher users for the dropdown
$structure = new Structure();
$users = $structure->getTeacherUsers();
$userss = $structure->getTeacherUsers();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer l'année académique active
$activeYear = $universite->getActiveAcademicYear();
$activeYearId = $activeYear ? $activeYear['idannee_acad'] : null;

// Récupérer l'année sélectionnée dans le filtre (par défaut l'année active)
$selectedYearId = isset($_GET['annee_acad']) ? $_GET['annee_acad'] : $activeYearId;

// Récupérer toutes les années académiques pour le filtre
$allYears = $universite->getAcademicYears();
?>
<style>
    .spinner-loading {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        pointer-events: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .position-relative select {
        padding-right: 40px;
    }
</style>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>ORIENTATIONS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Orientations</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Messages de session -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="col-lg-12">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php unset($_SESSION['success']);
            endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="col-lg-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-octagon me-1"></i>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php unset($_SESSION['error']);
            endif; ?>

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
                    <!-- Table orientations -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des orientations
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createOrientationModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                        | <a data-bs-toggle="modal" data-bs-target="#exportOrientationsModal" class="btnPage">
                                            <i class="bi bi-file-excel-fill"></i> Exporter
                                        </a>
                                    </span>
                                </h5>

                                <!-- Formulaire de recherche et filtre -->
                                <form method="GET" action="" class="mb-3">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="annee_acad" class="form-label">Année Académique</label>
                                                <select name="annee_acad" id="annee_acad" class="form-select" onchange="this.form.submit()">
                                                    <option value="">Toutes les années</option>
                                                    <?php
                                                    foreach ($allYears as $year) {
                                                        $selected = ($selectedYearId == $year['idannee_acad']) ? 'selected' : '';
                                                        $activeLabel = ($year['idannee_acad'] == $activeYearId) ? ' (En cours)' : '';
                                                        echo "<option value='{$year['idannee_acad']}' {$selected}>{$year['designation']}{$activeLabel}</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label for="search" class="form-label">Recherche</label>
                                                <div class="input-group">
                                                    <input type="hidden" name="view" value="configuration/orientation">
                                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par désignation...">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="bi bi-search"></i> Rechercher
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Chef de Département</th>
                                            <th scope="col">Section</th>
                                            <th scope="col">Année académique</th>
                                            <th scope="col">Date de Création</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeOrientations = $universite->getOrientations($search, $selectedYearId);
                                        $i = 1;

                                        foreach ($listeOrientations as $l) {
                                            $dc = date('d/m/Y H:i:s', strtotime($l['dateCreation']));

                                            // Affichage du chef d'orientation
                                            $chefOrientation = !empty($l['chef_orientation']) ? $l['chef_orientation'] : '<span class="text-muted">Non défini</span>';

                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$l['designationOrientation']}</td>
                                                <td>{$chefOrientation}</td>
                                                <td>{$l['sectionDesignation']}</td>
                                                <td>{$l['anneeDesignation']}</td>
                                                <td>{$dc}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='editOrientation(
                                                        {$l['idorientation']}, 
                                                        \"{$l['designationOrientation']}\",
                                                        \"{$l['section_idsection']}\"
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$l['idorientation']})'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#managers-{$l['idorientation']}'>
                                                        <i class='bi bi-eye'></i> Voir Responsables
                                                    </button>
                                                    <button class='btn btn-sm btn-success' data-bs-toggle='modal' data-bs-target='#addManagerModal' onclick='setOrientationId({$l['idorientation']})'>
                                                        <i class='bi bi-plus-circle'></i> Ajouter Responsable
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class='collapse' id='managers-{$l['idorientation']}'>
                                                <td colspan='7'>
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
                                            $managers = $universite->getManagersByOrientation($l['idorientation']);
                                            foreach ($managers as $manager) {
                                                echo "
                                                            <tr>
                                                                <td>{$manager['noms']}</td>
                                                                <td>{$manager['fonction']}</td>
                                                                <td><img src='uploads/{$manager['signature']}' alt='Signature' width='100'></td>
                                                                <td>{$manager['anneeDesignation']}</td>
                                                                <td>
                                                                    <button class='btn btn-sm btn-warning' onclick='editManager(
                                                                        {$manager['idresponsable_orientation']}, 
                                                                        \"{$manager['noms']}\",
                                                                        \"{$manager['fonction']}\",
                                                                        \"{$manager['signature']}\",
                                                                        \"{$manager['idUser']}\",
                                                                        \"{$manager['annee_acad_idannee_acad']}\"
                                                                    )'>
                                                                        <i class='bi bi-pencil-square'></i> Modifier
                                                                    </button>
                                                                    <button class='btn btn-sm btn-danger' onclick='confirmDeleteManager({$manager['idresponsable_orientation']})'>
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

<!-- Modal pour exporter les orientations -->
<div class="modal fade" id="exportOrientationsModal" tabindex="-1" role="dialog" aria-labelledby="exportOrientationsModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Exporter les Orientations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/export_orientations.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="exportSectionId" class="form-label">Section</label>
                            <select name="idSection" id="exportSectionId" class="form-control">
                                <option value="all">Toutes les sections</option>
                                <?php
                                $sections = $universite->getSections();
                                foreach ($sections as $section) {
                                    echo "<option value='{$section['idsection']}'>{$section['designationSection']} - {$section['anneeDesignation']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="exportOrientationId" class="form-label">Orientation</label>
                            <select name="idOrientation" id="exportOrientationId" class="form-control">
                                <option value="all">Toutes les orientations</option>
                                <?php
                                $allOrientations = $universite->getOrientations();
                                foreach ($allOrientations as $orientation) {
                                    echo "<option value='{$orientation['idorientation']}'>{$orientation['designationOrientation']} ({$orientation['sectionDesignation']})</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="includeManagers" id="includeManagers" checked>
                                <label class="form-check-label" for="includeManagers">
                                    Inclure les responsables
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="exportBtn" class="btn btn-primary">
                            <i class="bi bi-file-excel"></i> Exporter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour ajouter une orientation -->
<div class="modal fade" id="createOrientationModal" tabindex="-1" role="dialog" aria-labelledby="createOrientationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Orientation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_orientation.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designationOrientation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designationOrientation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="createAnneeId" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="createAnneeId" id="createAnneeId" class="form-control" required onchange="loadSectionsByYear(this.value, 'createSectionId')">
                                <!-- Populate with academic years -->
                                <?php
                                $academicYears = $universite->getAcademicYears();
                                foreach ($academicYears as $year) {
                                    $selected = ($year['idannee_acad'] == $activeYearId) ? 'selected' : '';
                                    echo "<option value='{$year['idannee_acad']}' $selected>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="createSectionId" class="form-label">Section <span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <select name="sectionId" id="createSectionId" class="form-control" required>
                                    <!-- Populate with sections from current academic year -->
                                    <?php
                                    $currentYearSections = $universite->getSections('', $activeYearId);
                                    foreach ($currentYearSections as $section) {
                                        echo "<option value='{$section['idsection']}'>{$section['designationSection']}</option>";
                                    }
                                    ?>
                                </select>
                                <div id="createSectionSpinner" class="spinner-loading" style="display: none;">
                                    <span class="spinner-border spinner-border-sm text-primary"></span>
                                </div>
                            </div>
                            <div class="invalid-feedback">Veuillez sélectionner une section.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addOrientationBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une orientation -->
<div class="modal fade" id="editOrientationModal" tabindex="-1" role="dialog" aria-labelledby="editOrientationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Orientation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_orientation.php" class="needs-validation" novalidate>
                    <input type="hidden" name="editOrientationId" id="editOrientationId">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editOrientationDesignation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="editOrientationDesignation" id="editOrientationDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
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
                        <div class="col-md-6">
                            <label for="editChefDepartement" class="form-label">Chef de Département</label>
                            <select name="editChefDepartement" id="editChefDepartement" class="form-control">
                                <option value="">-- Aucun chef défini --</option>
                                <?php
                                foreach ($users as $user) {
                                    echo "<option value='{$user['idUser']}'>{$user['nomUser']}</option>";
                                }
                                ?>
                            </select>
                            <small class="text-muted">Sélectionner un enseignant comme chef de département</small>
                        </div>
                    </div>
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle"></i> Si vous sélectionnez un chef de département, il sera automatiquement ajouté comme responsable avec la fonction "Chef de Département" pour l'année académique active.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="updateOrientationBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour ajouter un responsable -->
<div class="modal fade" id="addManagerModal" tabindex="-1" role="dialog" aria-labelledby="addManagerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Responsable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_manager_orientation.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="userId" class="form-label">Utilisateur <span class="text-danger">*</span></label>
                            <select name="userId" class="form-control" required>
                                <option value="">Sélectionner un utilisateur</option>
                                <?php
                                foreach ($users as $user) {
                                    echo "<option value='{$user['idUser']}'>{$user['nomUser']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un utilisateur.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="fonction" class="form-label">Fonction <span class="text-danger">*</span></label>
                            <select name="fonction" class="form-control" required>
                                <option value="">Sélectionner une fonction</option>
                                <option value="Chef de Département">Chef de Département</option>
                                <option value="Chef de Département Adjoint">Chef de Département Adjoint</option>
                                <option value="Secrétaire Académique">Secrétaire Académique</option>
                                <option value="Secrétaire Administratif">Secrétaire Administratif</option>
                                <option value="Responsable des Stages">Responsable des Stages</option>
                                <option value="Responsable Pédagogique">Responsable Pédagogique</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une fonction.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="est_chef" class="form-label">Chef de Département</label>
                            <select name="est_chef" class="form-control">
                                <option value="0">Non</option>
                                <option value="1">Oui</option>
                            </select>
                            <small class="text-muted">Définir comme chef de département pour cette orientation</small>
                        </div>
                        <div class="col-md-6">
                            <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="idAnnee" class="form-control" required>
                                <option value="">Sélectionner une année</option>
                                <?php
                                $academicYears = $universite->getAcademicYears();
                                foreach ($academicYears as $year) {
                                    $selected = ($year['idannee_acad'] == $activeYearId) ? 'selected' : '';
                                    echo "<option value='{$year['idannee_acad']}' {$selected}>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_debut" class="form-label">Date de début</label>
                            <input type="date" name="date_debut" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="date_fin" class="form-label">Date de fin</label>
                            <input type="date" name="date_fin" class="form-control">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" placeholder="+243 XXX XXX XXX">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="chef.departement@universite.cd">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="signature" class="form-label">Signature <span class="text-danger">*</span></label>
                            <input type="file" name="signature" class="form-control" accept="image/*" required>
                            <div class="invalid-feedback">Veuillez importer une signature.</div>
                        </div>
                    </div>

                    <input type="hidden" name="orientationId" id="managerOrientationId">
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

<!-- Modal pour modifier un responsable -->
<div class="modal fade" id="editManagerModal" tabindex="-1" role="dialog" aria-labelledby="editManagerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Responsable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/update_manager_orientation.php" enctype="multipart/form-data" class="needs-validation" novalidate>
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
                                $academicYears = $universite->getAcademicYears();
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
    // Charger les sections basées sur l'année académique sélectionnée
    function loadSectionsByYear(anneeId, selectFieldId) {
        return new Promise((resolve, reject) => {
            if (!anneeId) {
                document.getElementById(selectFieldId).innerHTML = '<option value="">Sélectionnez une année d\'abord</option>';
                hideSectionSpinner(selectFieldId);
                reject('Pas d\'année sélectionnée');
                return;
            }

            // Afficher le spinner de chargement
            const selectField = document.getElementById(selectFieldId);
            selectField.innerHTML = '<option value="">Chargement...</option>';
            selectField.disabled = true;
            showSectionSpinner(selectFieldId);

            // Appel AJAX pour charger les sections
            fetch('controller/get_sections_by_year.php?annee_id=' + anneeId)
                .then(response => response.json())
                .then(data => {
                    selectField.disabled = false;
                    hideSectionSpinner(selectFieldId);

                    if (data.success && data.sections.length > 0) {
                        let html = '';
                        data.sections.forEach(section => {
                            html += `<option value="${section.idsection}">${section.designationSection}</option>`;
                        });
                        selectField.innerHTML = html;
                        resolve();
                    } else {
                        selectField.innerHTML = '<option value="">Aucune section disponible pour cette année</option>';
                        resolve();
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    selectField.innerHTML = '<option value="">Erreur lors du chargement</option>';
                    selectField.disabled = false;
                    hideSectionSpinner(selectFieldId);
                    reject(error);
                });
        });
    }

    // Afficher le spinner du chargement des sections
    function showSectionSpinner(spinnerId) {
        const spinner = document.getElementById(spinnerId.replace('Id', 'Spinner'));
        if (spinner) {
            spinner.style.display = 'inline-flex';
        }
    }

    // Masquer le spinner du chargement des sections
    function hideSectionSpinner(selectFieldId) {
        const spinnerId = selectFieldId.replace('Id', 'Spinner');
        const spinner = document.getElementById(spinnerId);
        if (spinner) {
            spinner.style.display = 'none';
        }
    }

    // Initialiser le modal de création avec les bonnes sections
    function initCreateOrientationModal() {
        const anneeSelect = document.getElementById('createAnneeId');
        if (anneeSelect && anneeSelect.value) {
            loadSectionsByYear(anneeSelect.value, 'createSectionId');
        }
    }

    // Ajouter un listener pour initialiser le modal quand il s'ouvre
    document.addEventListener('DOMContentLoaded', function() {
        const createModal = document.getElementById('createOrientationModal');
        if (createModal) {
            createModal.addEventListener('shown.bs.modal', initCreateOrientationModal);
        }
    });

    function editOrientation(id, designation, sectionId) {
        document.getElementById('editOrientationId').value = id;
        document.getElementById('editOrientationDesignation').value = designation;
        document.getElementById('editSectionId').value = sectionId;

        // Récupérer le chef actuel via AJAX
        fetch('controller/get_orientation_chef.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.chefId) {
                    document.getElementById('editChefDepartement').value = data.chefId;
                } else {
                    document.getElementById('editChefDepartement').value = '';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('editChefDepartement').value = '';
            });

        new bootstrap.Modal(document.getElementById('editOrientationModal')).show();
    }

    function confirmDelete(idOrientation) {
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
                window.location.href = 'controller/delete_orientation.php?idorientation=' + idOrientation;
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
                window.location.href = 'controller/delete_manager_orientation.php?idresponsable_orientation=' + idManager;
            }
        });
    }

    function setOrientationId(orientationId) {
        document.getElementById('managerOrientationId').value = orientationId;
    }
</script>

<script>
    // Autres scripts existants...

    // Filtrer les orientations par section
    document.getElementById('exportSectionId').addEventListener('change', function() {
        const sectionId = this.value;
        const orientationSelect = document.getElementById('exportOrientationId');

        // Réinitialiser l'option "Toutes les orientations"
        orientationSelect.innerHTML = '<option value="all">Toutes les orientations</option>';

        // Si "Toutes les sections" est sélectionné, afficher toutes les orientations
        if (sectionId === 'all') {
            <?php foreach ($allOrientations as $orientation): ?>
                orientationSelect.innerHTML += `<option value="<?= $orientation['idorientation'] ?>"><?= $orientation['designationOrientation'] ?> (<?= $orientation['sectionDesignation'] ?>)</option>`;
            <?php endforeach; ?>
        } else {
            // Sinon, filtrer les orientations par section
            <?php foreach ($allOrientations as $orientation): ?>
                if ('<?= $orientation['section_idsection'] ?>' === sectionId) {
                    orientationSelect.innerHTML += `<option value="<?= $orientation['idorientation'] ?>"><?= $orientation['designationOrientation'] ?></option>`;
                }
            <?php endforeach; ?>
        }
    });
</script>


<?php include "./views/include/footer_file.php"; ?>