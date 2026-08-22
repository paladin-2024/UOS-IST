<?php
include "./views/include/header.php";
$section = new Universite();

$structure = new Structure();

// Fetch users for the dropdown
$users = $structure->getUsers(); // Assuming this method exists to fetch users

$userss = $structure->getUsers(); 

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Récupérer l'année académique active
$activeYear = $section->getActiveAcademicYear();
$activeYearId = $activeYear ? $activeYear['idannee_acad'] : null;

// Récupérer l'année sélectionnée dans le filtre (par défaut l'année active)
$selectedYearId = isset($_GET['annee_acad']) ? $_GET['annee_acad'] : $activeYearId;

// Récupérer toutes les années académiques pour le filtre
$allYears = $section->getAcademicYears();
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>SECTIONS / FACULTÉS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Sections / Facultés</li>
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
                    <!-- Table sections -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des sections / facultés
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createSectionModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
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
                                                    <input type="hidden" name="view" value="configuration/faculte">
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
                                            <th scope="col">Chef de Section / Doyen</th>
                                            <th scope="col">Contact</th>
                                            <th scope="col">Année Académique</th>
                                            <th scope="col">Date de Création</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $listeSections = $section->getSections($search, $selectedYearId);
                                        $i = 1;

                                        foreach ($listeSections as $l){
                                            $dc = date('d/m/Y H:i:s', strtotime($l['dateCreation']));
                                            
                                            // Affichage des informations de contact
                                            $contact = '';
                                            if (!empty($l['telephone'])) {
                                                $contact .= '<i class="bi bi-telephone"></i> ' . $l['telephone'] . '<br>';
                                            }
                                            if (!empty($l['email'])) {
                                                $contact .= '<i class="bi bi-envelope"></i> ' . $l['email'];
                                            }
                                            if (empty($contact)) {
                                                $contact = '<span class="text-muted">Non défini</span>';
                                            }
                                            
                                            // Affichage du chef de section
                                            $chefSection = !empty($l['chef_section']) ? $l['chef_section'] : '<span class="text-muted">Non défini</span>';
                                            
                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$l['designationSection']}</td>
                                                <td>{$chefSection}</td>
                                                <td>{$contact}</td>
                                                <td>{$l['anneeDesignation']}</td>
                                                <td>{$dc}</td>
                                                <td>
                                                    <button class='btn btn-sm btn-primary' onclick='viewSectionDetails({$l['idsection']})' title='Voir détails'>
                                                        <i class='bi bi-eye'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-warning' onclick='editSection({$l['idsection']})' title='Modifier'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$l['idsection']})' title='Supprimer'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-info' data-bs-toggle='collapse' data-bs-target='#managers-{$l['idsection']}' title='Voir Responsables'>
                                                        <i class='bi bi-people'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-success' data-bs-toggle='modal' data-bs-target='#addManagerModal' onclick='setSectionId({$l['idsection']})' title='Ajouter Responsable'>
                                                        <i class='bi bi-person-plus'></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr class='collapse' id='managers-{$l['idsection']}'>
                                                <td colspan='7'>
                                                    <div class='card'>
                                                        <div class='card-header bg-light'>
                                                            <h6 class='mb-0'>Responsables de la section</h6>
                                                        </div>
                                                        <div class='card-body'>
                                                            <table class='table table-sm'>
                                                                <thead>
                                                                    <tr>
                                                                        <th>Nom</th>
                                                                        <th>Fonction</th>
                                                                        <th>Chef de Section</th>
                                                                        <th>Contact</th>
                                                                        <th>Période</th>
                                                                        <th>Signature</th>
                                                                        <th>Année académique</th>
                                                                        <th>Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>";
                                            $managers = $section->getManagersBySection($l['idsection']);
                                            foreach ($managers as $manager) {
                                                $estChef = isset($manager['est_chef']) && $manager['est_chef'] == 1 
                                                    ? '<span class="badge bg-success">Oui</span>' 
                                                    : '<span class="badge bg-secondary">Non</span>';
                                                
                                                $periode = '';
                                                if (!empty($manager['date_debut'])) {
                                                    $periode = date('d/m/Y', strtotime($manager['date_debut']));
                                                    if (!empty($manager['date_fin'])) {
                                                        $periode .= ' - ' . date('d/m/Y', strtotime($manager['date_fin']));
                                                    } else {
                                                        $periode .= ' - En cours';
                                                    }
                                                }
                                                
                                                $contactManager = '';
                                                if (!empty($manager['telephone'])) {
                                                    $contactManager .= '<i class="bi bi-telephone"></i> ' . $manager['telephone'] . '<br>';
                                                }
                                                if (!empty($manager['email'])) {
                                                    $contactManager .= '<i class="bi bi-envelope"></i> ' . $manager['email'];
                                                }
                                                if (empty($contactManager)) {
                                                    $contactManager = '-';
                                                }
                                                
                                                echo "
                                                            <tr>
                                                                <td>{$manager['noms']}</td>
                                                                <td>{$manager['fonction']}</td>
                                                                <td>{$estChef}</td>
                                                                <td>{$contactManager}</td>
                                                                <td>{$periode}</td>
                                                                <td><img src='uploads/{$manager['signature']}' alt='Signature' width='100'></td>
                                                                <td>{$manager['anneeDesignation']}</td>
                                                                <td>
                                                                    <button class='btn btn-sm btn-warning' onclick='editManager({$manager['idresponsable_section']})' title='Modifier'>
                                                                        <i class='bi bi-pencil-square'></i>
                                                                    </button>
                                                                    <button class='btn btn-sm btn-danger' onclick='confirmDeleteManager({$manager['idresponsable_section']})' title='Supprimer'>
                                                                        <i class='bi bi-trash'></i>
                                                                    </button>
                                                                </td>
                                                            </tr>";
                                            }
                                            echo "
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
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

<!-- Modal pour ajouter une section -->
<div class="modal fade" id="createSectionModal" tabindex="-1" role="dialog" aria-labelledby="createSectionModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Section / Faculté</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_section.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="designationSection" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="designationSection" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="idAnnee" class="form-control" required>
                                <option value="">Sélectionner une année</option>
                                <?php
                                $academicYears = $section->getAcademicYears();
                                foreach ($academicYears as $year) {
                                    echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea name="adresse" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" placeholder="+243 XXX XXX XXX">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="section@universite.cd">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="boite_postale" class="form-label">Boîte Postale</label>
                            <input type="text" name="boite_postale" class="form-control" placeholder="B.P. XXX">
                        </div>
                        <div class="col-md-6">
                            <label for="site_web" class="form-label">Site Web</label>
                            <input type="url" name="site_web" class="form-control" placeholder="https://www.section.cd">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addSectionBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un manager -->
<div class="modal fade" id="addManagerModal" tabindex="-1" role="dialog" aria-labelledby="addManagerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Responsable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_manager.php" enctype="multipart/form-data" class="needs-validation" novalidate>
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
                            <select name="fonction" id="fonction" class="form-control" required>
                                <option value="Doyen">Doyen</option>
                                <option value="Vice-Doyen">Vice-Doyen</option>
                                <option value="Chef de Section">Chef de Section</option>
                                <option value="Chargé de la recherche">Chargé de la recherche</option>
                                <option value="Chargé des enseignements">Chargé des enseignements</option>
                                <option value="Secrétaire Académique">Secrétaire Académique</option>
                                <option value="Secrétaire Administratif">Secrétaire Administratif</option>
                                <option value="Appariteur">Appariteur</option>
                                <option value="Percepteur">Percepteur</option>
                                <option value="Recouvreur">Recouvreur</option>
                                <option value="Caissier-Comptable">Caissier-Comptable</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une fonction.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="est_chef" class="form-label">Chef de Section / Doyen</label>
                            <select name="est_chef" class="form-control">
                                <option value="0">Non</option>
                                <option value="1">Oui</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="idAnnee" class="form-control" required>
                                <option value="">Sélectionner une année</option>
                                <?php
                                $academicYears = $section->getAcademicYears();
                                foreach ($academicYears as $year) {
                                    echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
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
                            <input type="email" name="email" class="form-control" placeholder="responsable@universite.cd">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="signature" class="form-label">Signature <span class="text-danger">*</span></label>
                            <input type="file" name="signature" class="form-control" accept="image/*" required>
                            <div class="invalid-feedback">Veuillez importer une signature.</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="sectionId" id="managerSectionId">
                    
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

<!-- Modal pour modifier une section -->
<div class="modal fade" id="editSectionModal" tabindex="-1" role="dialog" aria-labelledby="editSectionModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Section / Faculté</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_section.php" class="needs-validation" novalidate>
                    <input type="hidden" name="editSectionId" id="editSectionId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editSectionDesignation" class="form-label">Désignation <span class="text-danger">*</span></label>
                            <input type="text" name="editSectionDesignation" id="editSectionDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editSectionAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="editSectionAnnee" id="editSectionAnnee" class="form-control" required>
                                <?php
                                foreach ($academicYears as $year) {
                                    echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editAdresse" class="form-label">Adresse</label>
                            <textarea name="editAdresse" id="editAdresse" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editTelephone" class="form-label">Téléphone</label>
                            <input type="text" name="editTelephone" id="editTelephone" class="form-control" placeholder="+243 XXX XXX XXX">
                        </div>
                        <div class="col-md-6">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" name="editEmail" id="editEmail" class="form-control" placeholder="section@universite.cd">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editBoitePostale" class="form-label">Boîte Postale</label>
                            <input type="text" name="editBoitePostale" id="editBoitePostale" class="form-control" placeholder="B.P. XXX">
                        </div>
                        <div class="col-md-6">
                            <label for="editSiteWeb" class="form-label">Site Web</label>
                            <input type="text" name="editSiteWeb" id="editSiteWeb" class="form-control" placeholder="https://www.section.cd">
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="updateSectionBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier un manager -->
<div class="modal fade" id="editManagerModal" tabindex="-1" role="dialog" aria-labelledby="editManagerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Responsable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/update_manager.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="editManagerId" id="editManagerId">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editUserId" class="form-label">Utilisateur <span class="text-danger">*</span></label>
                            <select name="editUserId" id="editUserId" class="form-control" required>
                                <?php
                                foreach ($userss as $user2) {
                                    echo "<option value='{$user2['idUser']}'>{$user2['nomUser']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un utilisateur.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editFonction" class="form-label">Fonction <span class="text-danger">*</span></label>
                            <select name="editFonction" id="editFonction" class="form-control" required>
                                <option value="Doyen">Doyen</option>
                                <option value="Vice-Doyen">Vice-Doyen</option>
                                <option value="Chef de Section">Chef de Section</option>
                                <option value="Chargé de la recherche">Chargé de la recherche</option>
                                <option value="Chargé des enseignements">Chargé des enseignements</option>
                                <option value="Secrétaire Académique">Secrétaire Académique</option>
                                <option value="Secrétaire Administratif">Secrétaire Administratif</option>
                                <option value="Appariteur">Appariteur</option>
                                <option value="Percepteur">Percepteur</option>
                                <option value="Recouvreur">Recouvreur</option>
                                <option value="Caissier-Comptable">Caissier-Comptable</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une fonction.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editEstChef" class="form-label">Chef de Section / Doyen</label>
                            <select name="editEstChef" id="editEstChef" class="form-control">
                                <option value="0">Non</option>
                                <option value="1">Oui</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="idAnnee" id="idAnnee" class="form-control" required>
                                <?php
                                $academicYears = $section->getAcademicYears();
                                foreach ($academicYears as $year) {
                                    echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editDateDebut" class="form-label">Date de début</label>
                            <input type="date" name="editDateDebut" id="editDateDebut" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="editDateFin" class="form-label">Date de fin</label>
                            <input type="date" name="editDateFin" id="editDateFin" class="form-control">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editManagerTelephone" class="form-label">Téléphone</label>
                            <input type="text" name="editManagerTelephone" id="editManagerTelephone" class="form-control" placeholder="+243 XXX XXX XXX">
                        </div>
                        <div class="col-md-6">
                            <label for="editManagerEmail" class="form-label">Email</label>
                            <input type="email" name="editManagerEmail" id="editManagerEmail" class="form-control" placeholder="responsable@universite.cd">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="currentSignature" class="form-label">Signature Actuelle</label>
                            <div id="currentSignature">
                                <img src="" alt="Signature actuelle" id="currentSignatureImg" width="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="editSignature" class="form-label">Nouvelle Signature</label>
                            <input type="file" name="editSignature" id="editSignature" class="form-control" accept="image/*">
                            <small class="text-muted">Laissez vide pour conserver la signature actuelle</small>
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

<!-- Modal pour voir les détails d'une section -->
<div class="modal fade" id="viewSectionModal" tabindex="-1" role="dialog" aria-labelledby="viewSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la Section / Faculté</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sectionDetailsContent">
                <!-- Le contenu sera chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Variable pour stocker les données des managers
    let managersData = {};
    
    <?php
    // Charger toutes les données des managers pour JavaScript
    // Commenté temporairement pour éviter les erreurs
    /*
    foreach ($listeSections as $sectionItem) {
        $managers = $section->getManagersBySection($sectionItem['idsection']);
        echo "managersData[{$sectionItem['idsection']}] = " . json_encode($managers) . ";\n";
    }
    */
    ?>
    
    function viewSectionDetails(id) {
        // Faire une requête AJAX pour obtenir les détails de la section
        fetch('controller/get_section_details.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                let content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Informations générales</h6>
                            <p><strong>Désignation:</strong> ${data.designationSection}</p>
                            <p><strong>Année Académique:</strong> ${data.anneeDesignation}</p>
                            <p><strong>Date de création:</strong> ${data.dateCreation}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Contacts</h6>
                            <p><strong>Adresse:</strong> ${data.adresse || 'Non définie'}</p>
                            <p><strong>Téléphone:</strong> ${data.telephone || 'Non défini'}</p>
                            <p><strong>Email:</strong> ${data.email || 'Non défini'}</p>
                            <p><strong>Boîte Postale:</strong> ${data.boite_postale || 'Non définie'}</p>
                            <p><strong>Site Web:</strong> ${data.site_web || 'Non défini'}</p>
                        </div>
                    </div>
                    <hr>
                    <h6>Chef de Section / Doyen actuel</h6>
                    <p>${data.chef_section || 'Non défini'}</p>
                `;
                
                document.getElementById('sectionDetailsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('viewSectionModal')).show();
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des détails');
            });
    }
    
    function editSection(id) {
        // Faire une requête AJAX pour obtenir les détails de la section
        fetch('controller/get_section_details.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('editSectionId').value = data.idsection;
                document.getElementById('editSectionDesignation').value = data.designationSection;
                document.getElementById('editSectionAnnee').value = data.idAnnee;
                document.getElementById('editAdresse').value = data.adresse || '';
                document.getElementById('editTelephone').value = data.telephone || '';
                document.getElementById('editEmail').value = data.email || '';
                document.getElementById('editBoitePostale').value = data.boite_postale || '';
                document.getElementById('editSiteWeb').value = data.site_web || '';
                
                new bootstrap.Modal(document.getElementById('editSectionModal')).show();
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des données');
            });
    }

    function confirmDelete(idSection) {
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
                window.location.href = 'controller/delete_section.php?idsection=' + idSection;
            }
        });
    }

    function editManager(id) {
        // Faire une requête AJAX pour obtenir les détails du manager
        fetch('controller/get_manager_details.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('editManagerId').value = data.idresponsable_section;
                document.getElementById('editUserId').value = data.idUser;
                document.getElementById('editFonction').value = data.fonction;
                document.getElementById('editEstChef').value = data.est_chef || 0;
                document.getElementById('idAnnee').value = data.annee_acad_idannee_acad;
                document.getElementById('editDateDebut').value = data.date_debut || '';
                document.getElementById('editDateFin').value = data.date_fin || '';
                document.getElementById('editManagerTelephone').value = data.telephone || '';
                document.getElementById('editManagerEmail').value = data.email || '';
                document.getElementById('currentSignatureImg').src = 'uploads/' + data.signature;
                
                new bootstrap.Modal(document.getElementById('editManagerModal')).show();
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des données');
            });
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
                window.location.href = 'controller/delete_manager.php?idresponsable_section=' + idManager;
            }
        });
    }

    function setSectionId(sectionId) {
        document.getElementById('managerSectionId').value = sectionId;
    }
    
    // Validation des formulaires Bootstrap
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByClassName('needs-validation');
            var validation = Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();
</script>

<?php include "./views/include/footer.php"; ?>