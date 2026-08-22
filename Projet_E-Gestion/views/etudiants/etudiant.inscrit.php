<?php
include "./views/include/header.php";
$universite = new Universite();

$search = isset($_GET['search']) ? $_GET['search'] : '';

// Filters: default to active academic year
$activeYear = $universite->getActiveAcademicYear();
$academicYears = $universite->getAcademicYears();
$defaultAnneeId = $activeYear && isset($activeYear['idannee_acad']) ? (int)$activeYear['idannee_acad'] : (isset($academicYears[0]['idannee_acad']) ? (int)$academicYears[0]['idannee_acad'] : null);

$anneeId = isset($_GET['annee']) ? ($_GET['annee'] !== '' ? (int) $_GET['annee'] : null) : $defaultAnneeId;
$orientationFilterId = isset($_GET['orientation']) && $_GET['orientation'] !== '' ? $_GET['orientation'] : '';
$promotionFilterId = isset($_GET['promotion']) && $_GET['promotion'] !== '' ? $_GET['promotion'] : '';

// Preload filter options - get orientations for the selected year (or all if no year selected)
$filterOrientations = $universite->getOrientations('', $anneeId);

$filterPromotions = [];
// Load all promotions for the selected year (not filtered by orientation yet)
// This ensures the promotion dropdown is populated even before an orientation is selected
if (!empty($anneeId)) {
    $allYearPromotions = $universite->getPromotionsByAnneeAcad($anneeId);
    // If orientation is selected, filter promotions by orientation
    if (!empty($orientationFilterId)) {
        foreach ($allYearPromotions as $p) {
            if (isset($p['orientation_idorientation']) && (int)$p['orientation_idorientation'] === (int)$orientationFilterId) {
                $filterPromotions[] = $p;
            }
        }
    } else {
        // No orientation selected yet, show all promotions for the year
        $filterPromotions = $allYearPromotions;
    }
}

// Function to generate the next matricule
function generateNextMatricule($universite) {
    $count = 1;
    $prefix = "ET-A";
    do {
        $matricule = $prefix . str_pad($count, 8, '0', STR_PAD_LEFT);
        $existingStudent = $universite->getStudentByMatricule($matricule);
        $count++;
    } while ($existingStudent);

    return $matricule;
}

$nextMatricule = generateNextMatricule($universite);

// Récupérer toutes les orientations pour les formulaires
$orientations = $universite->getOrientations();
?>
<!-- Espace de travail -->
<main id="main" class="main">
    <div class="pagetitle">
        <h1>ÉTUDIANTS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Étudiants</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table students -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des étudiants
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createStudentModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                        | <a data-bs-toggle="modal" data-bs-target="#importStudentModal" class="btnPage">
                                            <i class="bi bi-file-earmark-excel-fill"></i> Importer
                                        </a>
                                        | <a data-bs-toggle="modal" data-bs-target="#importPrepStudentModal" class="btnPage">
                                            <i class="bi bi-file-earmark-excel-fill"></i> Importer Préparatoires
                                        </a>
                                        | 
                                        <div class="dropdown d-inline">
                                            <button class="btnPage dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-file-earmark-excel-fill"></i> Exporter
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                                                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#exportStudentModal">Étudiants par promotion</a></li>
                                                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#exportPreparatoireModal">Étudiants préparatoires</a></li>
                                            </ul>
                                        </div>

                                        
                                    </span>
                                </h5>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Affichage des 100 derniers étudiants enregistrés
                                </div>

                                <iframe id="downloadFrame" name="downloadFrame" style="display:none;"></iframe>

                                <form id="filtersForm" method="GET" action="" class="mb-3">
                                    <input type="hidden" name="view" value="etudiants/etudiant.inscrit">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label">Année académique</label>
                                            <select id="filterAnnee" name="annee" class="form-control">
                                            <option value="" <?= ($anneeId === null) ? 'selected' : '' ?>>Toutes</option>
                                            <?php foreach ($academicYears as $year): ?>
                                            <option value="<?= (int)$year['idannee_acad'] ?>" <?= ($anneeId == (int)$year['idannee_acad']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($year['designation']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Orientation</label>
                                            <select id="filterOrientation" name="orientation" class="form-control">
                                                <option value="">Toutes</option>
                                                <?php foreach ($filterOrientations as $o): ?>
                                                    <option value="<?= (int)$o['idorientation'] ?>" <?= ((string)$orientationFilterId !== '' && (int)$orientationFilterId === (int)$o['idorientation']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($o['designationOrientation']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Promotion</label>
                                            <select id="filterPromotion" name="promotion" class="form-control">
                                                <option value="">Toutes</option>
                                                <?php foreach ($filterPromotions as $p): ?>
                                                    <option value="<?= (int)$p['idpromotion'] ?>" <?= ((string)$promotionFilterId !== '' && (int)$promotionFilterId === (int)$p['idpromotion']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($p['designationPromotion']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Recherche</label>
                                            <div class="input-group">
                                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Nom ou matricule">
                                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Matricule</th>
                                            <th scope="col">Noms</th>
                                            <th scope="col">Promotion</th>
                                            <th scope="col">Année</th>
                                            <th scope="col">Téléphone</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="studentsTableBody">
                                        <?php
                                        // Initial load of 50 students
                                        // Pass true to include all students (active and inactive) to match export behavior
                                        $limit = 50;
                                        $listeEtudiants = $universite->getStudents($search, $limit, 0, $anneeId, $orientationFilterId, $promotionFilterId, true);
                                        $i = 1;

                                        foreach ($listeEtudiants as $etudiant){
                                             // Prepare JSON-encoded parameters for safe JS injection
                                             $editParamsJson = json_encode([
                                                 $etudiant['idetudiant'],
                                                 $etudiant['matricule'],
                                                 $etudiant['noms'],
                                                 $etudiant['lieuNaissance'] ?? '',
                                                 $etudiant['dateNaissance'] ?? '',
                                                 $etudiant['adressemail'] ?? '',
                                                 $etudiant['telephone'] ?? '',
                                                 $etudiant['sexe'] ?? '',
                                                 $etudiant['nationalite'] ?? '',
                                                 $etudiant['annee_acad_idannee_acad'] ?? '',
                                                 $etudiant['promotion_idpromotion'] ?? ''
                                             ]);

                                             echo "
                                             <tr>
                                                 <td class='row-index'>{$i}</td>
                                                 <td>{$etudiant['matricule']}</td>
                                                 <td>{$etudiant['noms']}</td>
                                                 <td>{$etudiant['designationPromotion']}</td>
                                                 <td>{$etudiant['annee']}</td>
                                                 <td>{$etudiant['telephone']}</td>
                                                 
                                                 <td>
                                                     <div class='btn-group btn-group-sm' role='group'>
                                                         <button class='btn btn-warning' data-edit='" . htmlspecialchars($editParamsJson, ENT_QUOTES, 'UTF-8') . "' type='button' title='Modifier'>
                                                             <i class='bi bi-pencil-square'></i>
                                                         </button>
                                                         <button class='btn btn-info text-white' onclick=\"voirHistoriqueInscriptions('" . htmlspecialchars($etudiant['matricule'], ENT_QUOTES, 'UTF-8') . "', '" . htmlspecialchars($etudiant['noms'], ENT_QUOTES, 'UTF-8') . "')\" type='button' title='Historique inscriptions'>
                                                             <i class='bi bi-clock-history'></i>
                                                         </button>
                                                         <button class='btn btn-success' onclick=\"generateECard({$etudiant['idetudiant']})\" type='button' title='E-Carte'>
                                                             <i class='bi bi-credit-card'></i>
                                                         </button>
                                                         <button class='btn btn-danger' onclick=\"confirmDelete({$etudiant['idetudiant']})\" type='button' title='Supprimer'>
                                                             <i class='bi bi-trash'></i>
                                                         </button>
                                                     </div>
                                                 </td>
                                             </tr>";
                                             $i++;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                                <div id="loadingIndicator" class="text-center my-3" style="display: none;">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Chargement...</span>
                                    </div>
                                    <p class="text-muted mt-2">Chargement d'autres étudiants...</p>
                                </div>
                                <div id="noMoreData" class="text-center my-3 text-muted" style="display: none;">
                                    <p>Plus d'étudiants à afficher</p>
                                </div>
                                <div id="loadingSentinel" style="height: 20px; background-color: transparent;"></div>
                            </div>
                        </div>
                    </div><!-- End Table -->
                </div>
            </div>
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal pour ajouter un étudiant -->
<div class="modal fade" id="createStudentModal" tabindex="-1" role="dialog" aria-labelledby="createStudentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Étudiant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="studentForm" method="POST" action="controller/create_etudiant.php" class="needs-validation" novalidate>
                    <div class="row mb-3">
                    <div class="col-md-6">
                            <label for="matricule" class="form-label">Matricule <span class="text-danger">*</span></label>
                            <input type="text" name="matricule" class="form-control" value="<?= $nextMatricule ?>">
                            <div class="invalid-feedback">Veuillez saisir un matricule.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="noms" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="noms" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un nom.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="lieuNaissance" class="form-label">Lieu de Naissance</label>
                            <input type="text" name="lieuNaissance" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="dateNaissance" class="form-label">Date de Naissance</label>
                            <input type="date" name="dateNaissance" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="adressemail" class="form-label">Email</label>
                            <input type="email" name="adressemail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="sexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                            <select name="sexe" class="form-control" required>
                                <option value="Masculin">Masculin</option>
                                <option value="Feminin">Féminin</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner le sexe.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="nationalite" class="form-label">Nationalité <span class="text-danger">*</span></label>
                            <input type="text" name="nationalite" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir la nationalité.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="idAnnee" class="form-control" required>
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
                        
                        <div class="col-md-6">
                            <label for="orientationId" class="form-label">Orientation <span class="text-danger">*</span></label>
                            <select id="orientationId" class="form-control" required>
                                <option value="">Sélectionner une orientation</option>
                                <?php
                                foreach ($orientations as $orientation) {
                                    echo "<option value='{$orientation['idorientation']}'>{$orientation['designationOrientation']} / {$orientation['anneeDesignation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une orientation.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="promotionId" class="form-label">Promotion <span class="text-danger">*</span></label>
                            <select name="promotionId" id="promotionId" class="form-control" required>
                                <option value="">Sélectionner d'abord une orientation</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addStudentBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour importer les étudiants préparatoires -->
<div class="modal fade" id="importPrepStudentModal" tabindex="-1" role="dialog" aria-labelledby="importPrepStudentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des Étudiants Préparatoires</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/import_etudiant_tempon.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="importPrepFile" class="form-label">Fichier Excel <span class="text-danger">*</span></label>
                            <input type="file" name="importPrepFile" class="form-control" accept=".xls,.xlsx" required>
                            <div class="invalid-feedback">Veuillez sélectionner un fichier Excel.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="importPrepIdAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="importPrepIdAnnee" class="form-control" required>
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
                        <div class="col-md-6">
                            <label for="startRow" class="form-label">Ligne de départ <span class="text-danger">*</span></label>
                            <input type="number" name="startRow" class="form-control" min="1" value="2" required>
                            <div class="invalid-feedback">Veuillez indiquer la ligne de départ.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="matriculeColumn" class="form-label">Colonne Matricule <span class="text-danger">*</span></label>
                            <input type="number" name="matriculeColumn" class="form-control" min="1" value="2" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne des matricules.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="nomsColumn" class="form-label">Colonne Noms <span class="text-danger">*</span></label>
                            <input type="number" name="nomsColumn" class="form-control" min="1" value="3" required>
                            <div class="invalid-feedback">Veuillez indiquer la colonne des noms.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="promotion_designation" class="form-label">Promotion <span class="text-danger">*</span></label>
                            <input type="text" name="promotion_designation" class="form-control" value="PRÉPARATOIRE" required>
                            <div class="invalid-feedback">Veuillez indiquer la promotion.</div>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Importez uniquement les matricules et noms des étudiants préparatoires. Les étudiants pourront choisir leur classe (A, B ou C) lors de leur première connexion.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="importPrepStudentBtn" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Importer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<!-- Modal for exporting students by promotion -->
<div class="modal fade" id="exportStudentModal" tabindex="-1" role="dialog" aria-labelledby="exportStudentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Exporter les Étudiants par Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form  method="POST" action="controller/export_etudiants.php" class="needs-validation">
                    <div class="row mb-3">
                        <div class="col-md-12">
                        <input type="hidden" name="exportType" value="regular">
                        <label for="exportPromotionId" class="form-label">Promotion <span class="text-danger">*</span></label>
                        <select name="promotionId" id="exportPromotionId" class="form-control" required>
                            <option value="">Chargement...</option>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="exportStudentBtn" class="btn btn-primary">
                            <i class="bi bi-download"></i> Exporter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour exporter les étudiants préparatoires -->
<div class="modal fade" id="exportPreparatoireModal" tabindex="-1" role="dialog" aria-labelledby="exportPreparatoireModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Exporter les Étudiants Préparatoires</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportPreparatoireForm" method="POST" action="controller/export_preparatoire.php" class="needs-validation" target="downloadFrame">
                    <input type="hidden" name="exportType" value="preparatoire">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="preparatoireClass" class="form-label">Classe Préparatoire <span class="text-danger">*</span></label>
                            <select name="preparatoireClass" id="preparatoireClass" class="form-control" required>
                                <option value="">Sélectionner une classe</option>
                                <option value="PREPARATOIRE A">Préparatoire A</option>
                                <option value="PREPARATOIRE B">Préparatoire B</option>
                                <option value="PREPARATOIRE C">Préparatoire C</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une classe préparatoire.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="exportPreparatoireBtn" class="btn btn-primary">
                            <i class="bi bi-download"></i> Exporter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour importer des étudiants -->
<div class="modal fade" id="importStudentModal" tabindex="-1" role="dialog" aria-labelledby="importStudentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Importer des Étudiants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/import_etudiant.php" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="importFile" class="form-label">Fichier Excel <span class="text-danger">*</span></label>
                            <input type="file" name="importFile" class="form-control" accept=".xls,.xlsx" required>
                            <div class="invalid-feedback">Veuillez sélectionner un fichier Excel.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="importType" class="form-label">Type d'importation <span class="text-danger">*</span></label>
                            <select name="importType" id="importType" class="form-control" required>
                                <option value="regular">Étudiants réguliers</option>
                                <option value="preparatoire">Étudiants préparatoires</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner le type d'importation.</div>
                        </div>
                        
                        <!-- Champs spécifiques aux étudiants réguliers -->
                        <div id="regularFields" class="col-12 mt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="importIdAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                                    <select name="importIdAnnee" id="importIdAnnee" class="form-control" required>
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
                                <div class="col-md-6">
                                    <label for="importPromotionId" class="form-label">Promotion <span class="text-danger">*</span></label>
                                    <select name="importPromotionId" id="importPromotionId" class="form-control" required>
                                    <!-- Populate with promotions from current academic year -->
                                    <?php
                                    $promotions = $universite->getPromotionsByAnneeAcad($activeYear['idannee_acad']);
                                    foreach ($promotions as $promotion) {
                                            echo "<option value='{$promotion['idpromotion']}'>{$promotion['designationPromotion']} - {$promotion['anneeDesignation']}</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Champs spécifiques aux étudiants préparatoires -->
                        <div id="preparatoireFields" class="col-12 mt-3" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="preparatoirePrefix" class="form-label">Préfixe Matricule <span class="text-danger">*</span></label>
                                    <input type="text" name="preparatoirePrefix" id="preparatoirePrefix" class="form-control" value="ET-P" placeholder="Ex: ET-P">
                                    <div class="invalid-feedback">Veuillez saisir un préfixe pour les matricules.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="anneeAcademique" class="form-label">Année Académique <span class="text-danger">*</span></label>
                                    <select name="anneeAcademique" id="anneeAcademique" class="form-control">
                                        <?php
                                        foreach ($academicYears as $year) {
                                            echo "<option value='{$year['designation']}'>{$year['designation']}</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="startRow" class="form-label">Ligne de départ <span class="text-danger">*</span></label>
                            <input type="number" name="startRow" class="form-control" min="1" value="2" required>
                            <div class="invalid-feedback">Veuillez indiquer la ligne de départ.</div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Correspondance des colonnes</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Indiquez le numéro de colonne dans votre fichier Excel pour chaque information (A=1, B=2, etc.)</p>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="matriculeColumn" class="form-label">Matricule</label>
                                    <input type="number" name="matriculeColumn" class="form-control" min="1" value="1">
                                    <small class="form-text text-muted">Laissez vide pour générer automatiquement</small>
                                </div>
                                <div class="col-md-4">
                                    <label for="nomsColumn" class="form-label">Noms <span class="text-danger">*</span></label>
                                    <input type="number" name="nomsColumn" class="form-control" min="1" value="2" required>
                                    <div class="invalid-feedback">Obligatoire</div>
                                </div>
                                <div class="col-md-4">
                                    <label for="sexeColumn" class="form-label">Sexe</label>
                                    <input type="number" name="sexeColumn" class="form-control" min="1" value="3">
                                </div>
                                <div class="col-md-4">
                                    <label for="dateNaissanceColumn" class="form-label">Date de Naissance</label>
                                    <input type="number" name="dateNaissanceColumn" class="form-control" min="1" value="4">
                                </div>
                                <div class="col-md-4">
                                    <label for="lieuNaissanceColumn" class="form-label">Lieu de Naissance</label>
                                    <input type="number" name="lieuNaissanceColumn" class="form-control" min="1" value="5">
                                </div>
                                <div class="col-md-4">
                                    <label for="nationaliteColumn" class="form-label">Nationalité</label>
                                    <input type="number" name="nationaliteColumn" class="form-control" min="1" value="6">
                                </div>
                                <div class="col-md-4">
                                    <label for="adressemailColumn" class="form-label">Email</label>
                                    <input type="number" name="adressemailColumn" class="form-control" min="1" value="7">
                                </div>
                                <div class="col-md-4">
                                    <label for="telephoneColumn" class="form-label">Téléphone</label>
                                    <input type="number" name="telephoneColumn" class="form-control" min="1" value="8">
                                </div>
                                <div class="col-md-4">
                                    <label for="adresseColumn" class="form-label">Adresse</label>
                                    <input type="number" name="adresseColumn" class="form-control" min="1" value="9">
                                </div>
                                <div class="col-md-4">
                                    <label for="personneContactColumn" class="form-label">Personne à contacter</label>
                                    <input type="number" name="personneContactColumn" class="form-control" min="1" value="10">
                                </div>
                                <div class="col-md-4">
                                    <label for="telephoneContactColumn" class="form-label">Téléphone contact</label>
                                    <input type="number" name="telephoneContactColumn" class="form-control" min="1" value="11">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Conseil:</strong> Assurez-vous que votre fichier Excel est correctement formaté. Les dates doivent être au format YYYY-MM-DD.
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="importStudentBtn" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Importer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Modal pour modifier un étudiant -->
<div class="modal fade" id="editStudentModal" tabindex="-1" role="dialog" aria-labelledby="editStudentModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Étudiant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Affichage des informations de l'étudiant -->
                <div class="alert alert-light border mb-3" id="studentInfoDisplay" style="display: none;">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Matricule:</strong> <span id="displayMatricule"></span></p>
                            <p><strong>Nom:</strong> <span id="displayNoms"></span></p>
                            <p><strong>Lieu de Naissance:</strong> <span id="displayLieuNaissance">-</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Date de Naissance:</strong> <span id="displayDateNaissance">-</span></p>
                            <p><strong>Sexe:</strong> <span id="displaySexe">-</span></p>
                            <p><strong>Nationalité:</strong> <span id="displayNationalite">-</span></p>
                        </div>
                    </div>
                    <hr>
                </div>

                <form id="editStudentForm" method="POST" action="controller/update_etudiant.php" class="needs-validation" novalidate>
                    <input type="hidden" id="editStudentId" name="id">
                    <div class="row mb-3">
                    <div class="col-md-6">
                            <label for="editMatricule" class="form-label">Matricule <span class="text-danger">*</span></label>
                            <input type="text" id="editMatricule" name="matricule" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un matricule.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editNoms" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" id="editNoms" name="noms" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un nom.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editLieuNaissance" class="form-label">Lieu de Naissance</label>
                            <input type="text" id="editLieuNaissance" name="lieuNaissance" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="editDateNaissance" class="form-label">Date de Naissance</label>
                            <input type="date" id="editDateNaissance" name="dateNaissance" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="editAdressemail" class="form-label">Email</label>
                            <input type="email" id="editAdressemail" name="adressemail" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="editTelephone" class="form-label">Téléphone</label>
                            <input type="text" id="editTelephone" name="telephone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="editSexe" class="form-label">Sexe <span class="text-danger">*</span></label>
                            <select id="editSexe" name="sexe" class="form-control" required>
                                <option value="Masculin">Masculin</option>
                                <option value="Feminin">Féminin</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner le sexe.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editNationalite" class="form-label">Nationalité <span class="text-danger">*</span></label>
                            <input type="text" id="editNationalite" name="nationalite" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir la nationalité.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="editIdAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select id="editIdAnnee" name="idAnnee" class="form-control" required>
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
                        
                        <div class="col-md-6">
                            <label for="editOrientationId" class="form-label">Orientation <span class="text-danger">*</span></label>
                            <select id="editOrientationId" class="form-control" required>
                                <option value="">Sélectionner une orientation</option>
                                <?php
                                foreach ($orientations as $orientation) {
                                    echo "<option value='{$orientation['idorientation']}'>{$orientation['designationOrientation']} / {$orientation['anneeDesignation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une orientation.</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="editPromotionId" class="form-label">Promotion <span class="text-danger">*</span></label>
                            <select id="editPromotionId" name="promotionId" class="form-control" required>
                                <option value="">Sélectionner d'abord une orientation</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une promotion.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editStudentBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
  <script>
      function editStudent(id, matricule, noms, lieuNaissance, dateNaissance, adressemail, telephone, sexe, nationalite, annee, promotion) {
          $('#editStudentId').val(id);
          $('#editMatricule').val(matricule);
          $('#editNoms').val(noms);
          $('#editLieuNaissance').val(lieuNaissance);
          $('#editDateNaissance').val(dateNaissance);
          $('#editAdressemail').val(adressemail);
          $('#editTelephone').val(telephone);
          $('#editSexe').val(sexe);
          $('#editNationalite').val(nationalite);
          $('#editIdAnnee').val(annee);
          
          // Afficher les informations de l'étudiant
          $('#displayMatricule').text(matricule);
          $('#displayNoms').text(noms);
          $('#displayLieuNaissance').text(lieuNaissance || '-');
          $('#displayDateNaissance').text(dateNaissance || '-');
          $('#displaySexe').text(sexe || '-');
          $('#displayNationalite').text(nationalite || '-');
          $('#studentInfoDisplay').show();
        
          // Récupérer l'orientation de la promotion sélectionnée
          $.ajax({
              url: 'controller/get_promotion_orientation.php',
              type: 'GET',
              data: { promotionId: promotion },
              dataType: 'json',
              success: function(data) {
                  $('#editOrientationId').val(data.orientationId).trigger('change');
                
                  // Attendre que le changement d'orientation soit traité
                  setTimeout(function() {
                      loadPromotionsWithjQuery('editOrientationId', 'editPromotionId', promotion);
                  }, 500);
              },
              error: function(xhr, status, error) {
                  console.error('Erreur lors de la récupération de l\'orientation:', error);
              }
          });
        
          new bootstrap.Modal(document.getElementById('editStudentModal')).show();
      }

      // Event listener for data-edit buttons
      document.addEventListener('click', function(e) {
         if (e.target.closest('button[data-edit]')) {
             const button = e.target.closest('button[data-edit]');
             const data = JSON.parse(button.getAttribute('data-edit'));
             editStudent(...data);
         }
      });

      // Global function to load promotions with jQuery
      function loadPromotionsWithjQuery(orientationSelectId, promotionSelectId, selectedPromotionId = null) {
         const orientationId = $('#' + orientationSelectId).val();
         const $promotionSelect = $('#' + promotionSelectId);
         
         if (!orientationId) {
             $promotionSelect.html('<option value="">Sélectionner d\'abord une orientation</option>');
             $promotionSelect.trigger('change');
             return;
         }
         
         $promotionSelect.html('<option value="">Chargement...</option>');
         $promotionSelect.trigger('change');
         
         $.ajax({
             url: 'controller/get_promotions_by_orientation.php',
             type: 'GET',
             data: { orientationId: orientationId },
             dataType: 'json',
             success: function(data) {
                 $promotionSelect.empty();
                 
                 if (!Array.isArray(data) || data.length === 0) {
                     $promotionSelect.html('<option value="">Aucune promotion disponible</option>');
                 } else {
                     data.forEach(function(promotion) {
                         const selected = (selectedPromotionId && promotion.idpromotion == selectedPromotionId) ? 'selected' : '';
                         $promotionSelect.append(`<option value="${promotion.idpromotion}" ${selected}>${promotion.designationPromotion} - ${promotion.anneeDesignation}</option>`);
                     });
                 }
                 
                 $promotionSelect.trigger('change');
             },
             error: function(xhr, status, error) {
                 console.error('Erreur AJAX:', error);
                 $promotionSelect.html('<option value="">Erreur de chargement</option>');
                 $promotionSelect.trigger('change');
             }
         });
      }

      // Fonction pour récupérer l'orientation d'une promotion
      async function fetchPromotionOrientation(promotionId) {
          try {
              const response = await fetch(`controller/get_promotion_orientation.php?promotionId=${promotionId}`);
              if (!response.ok) {
                  throw new Error(`Erreur HTTP: ${response.status}`);
              }
              const data = await response.json();
              console.log('Orientation récupérée:', data); // Débogage
              return data.orientationId;
          } catch (error) {
              console.error('Erreur lors de la récupération de l\'orientation:', error);
              return null;
          }
      }
    
      // Fonction pour charger les promotions en fonction de l'orientation sélectionnée
      function loadPromotions(orientationSelectId, promotionSelectId, selectedPromotionId = null) {
          const orientationSelect = document.getElementById(orientationSelectId);
          const promotionSelect = document.getElementById(promotionSelectId);
          const orientationId = orientationSelect.value;
    
          if (!orientationId) {
              promotionSelect.innerHTML = '<option value="">Sélectionner d\'abord une orientation</option>';
              return;
          }
    
          // Vider la liste des promotions
          promotionSelect.innerHTML = '<option value="">Chargement...</option>';
    
          // Utiliser un chemin absolu depuis la racine du site
          fetch(`controller/get_promotions_by_orientation.php?orientationId=${orientationId}`)
              .then(response => {
                  if (!response.ok) {
                      throw new Error(`Erreur HTTP: ${response.status}`);
                  }
                  return response.json();
              })
              .then(data => {
                  console.log('Données reçues:', data); // Débogage
            
                  promotionSelect.innerHTML = '';
            
                  if (!Array.isArray(data) || data.length === 0) {
                      promotionSelect.innerHTML = '<option value="">Aucune promotion disponible</option>';
                      return;
                  }
            
                  // Ajouter les options
                  data.forEach(promotion => {
                      const option = document.createElement('option');
                      option.value = promotion.idpromotion;
                      option.textContent = `${promotion.designationPromotion} - ${promotion.anneeDesignation}`;
                
                      // Sélectionner la promotion si elle correspond
                      if (selectedPromotionId && promotion.idpromotion == selectedPromotionId) {
                          option.selected = true;
                      }
                
                      promotionSelect.appendChild(option);
                  });
              })
              .catch(error => {
                  console.error('Erreur lors du chargement des promotions:', error);
                  promotionSelect.innerHTML = '<option value="">Erreur de chargement</option>';
              });
      }
    document.addEventListener('DOMContentLoaded', function() {
        // Pour le formulaire d'ajout
        $('#orientationId').on('change', function() {
            const orientationId = $(this).val();
            loadPromotionsWithjQuery('orientationId', 'promotionId');
        });
        
        // Pour le formulaire de modification
         $('#editOrientationId').on('change', function() {
             loadPromotionsWithjQuery('editOrientationId', 'editPromotionId');
         });
        });
        </script>

        <script>
        function confirmDelete(idEtudiant) {
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
                window.location.href = 'controller/delete_etudiant.php?idetudiant=' + idEtudiant;
            }
        });
    }
</script>

<script>
// Safe assignment for downloadForm
var downloadForm = document.getElementById('downloadForm');
if (downloadForm) {
    downloadForm.onsubmit = function() {
        setTimeout(function() {
            window.location.href = 'etudiants/etudiant.inscrit';
        }, 1000);
    };
}

// Safe assignment for exportPreparatoireForm
var exportPreparatoireForm = document.getElementById('exportPreparatoireForm');
if (exportPreparatoireForm) {
    exportPreparatoireForm.onsubmit = function() {
        setTimeout(function() {
            window.location.href = 'etudiants/etudiant.inscrit';
        }, 1000);
    };
}


function generateECard(idEtudiant) {
    window.location.href = 'controller/generate_ecard.php?id=' + idEtudiant;
}

// Gestion du changement de type d'importation
document.getElementById('importType').addEventListener('change', function() {
    const regularFields = document.getElementById('regularFields');
    const preparatoireFields = document.getElementById('preparatoireFields');
    
    if (this.value === 'regular') {
        regularFields.style.display = 'block';
        preparatoireFields.style.display = 'none';
        
        // Rendre les champs obligatoires
        document.getElementById('importIdAnnee').setAttribute('required', 'required');
        document.getElementById('importPromotionId').setAttribute('required', 'required');
        
        // Enlever l'obligation pour les champs préparatoires
        document.getElementById('preparatoirePrefix').removeAttribute('required');
        document.getElementById('anneeAcademique').removeAttribute('required');
    } else {
        regularFields.style.display = 'none';
        preparatoireFields.style.display = 'block';
        
        // Enlever l'obligation pour les champs réguliers
        document.getElementById('importIdAnnee').removeAttribute('required');
        document.getElementById('importPromotionId').removeAttribute('required');
        
        // Rendre les champs obligatoires
        document.getElementById('preparatoirePrefix').setAttribute('required', 'required');
        document.getElementById('anneeAcademique').setAttribute('required', 'required');
    }
});




</script>

<script>
// Dynamic filters (list view) and modal dependent selects
$(document).ready(function() {
    var DEFAULT_ACTIVE_YEAR_ID = <?= isset($defaultAnneeId) && $defaultAnneeId ? (int)$defaultAnneeId : 'null' ?>;
    function populateSelect($select, items, placeholder, valueKey, labelKey) {
        $select.empty();
        if (placeholder !== null) {
            $select.append($('<option>', { value: '', text: placeholder }));
        }
        if (Array.isArray(items)) {
            items.forEach(function(it) {
                $select.append($('<option>', { value: it[valueKey], text: it[labelKey] }));
            });
        }
        $select.trigger('change.select2');
    }

    // -------- Filters in listing --------
    var $fAnnee = $('#filterAnnee');
    var $fOrientation = $('#filterOrientation');
    var $fPromotion = $('#filterPromotion');
    var $filtersForm = $('#filtersForm');

    $fAnnee.on('change', function() {
        var anneeId = $(this).val();
        // Reset orientation and promotion to trigger re-render
        populateSelect($fOrientation, [], 'Toutes', 'idorientation', 'designationOrientation');
        populateSelect($fPromotion, [], 'Toutes', 'idpromotion', 'designationPromotion');
        // Auto-submit to refresh filters server-side after a small delay to allow UI update
        if ($filtersForm.length) { 
            setTimeout(function() { $filtersForm.trigger('submit'); }, 100); 
        }
    });

    $fOrientation.on('change', function() {
        // Auto-submit so server repopulates promotions based on selected orientation
        if ($filtersForm.length) { 
            setTimeout(function() { $filtersForm.trigger('submit'); }, 100); 
        }
    });

    $fPromotion.on('change', function() {
        if ($filtersForm.length) { 
            setTimeout(function() { $filtersForm.trigger('submit'); }, 100); 
        }
    });

    // -------- Modal dependent selects (AJAX, no submit here) --------

    // Export modal: load promotions when shown
    $('#exportStudentModal').on('shown.bs.modal', function() {
        var currentAnneeId = $('#filterAnnee').val();
        $.getJSON('controller/ajax_get_promotions.php', { annee_id: currentAnneeId })
            .done(function(resp) {
                var $select = $('#exportPromotionId');
                $select.empty();
                if (resp.success && resp.promotions) {
                    $select.append($('<option>', { value: '', text: 'Sélectionner une promotion' }));
                    resp.promotions.forEach(function(p) {
                        $select.append($('<option>', { value: p.idpromotion, text: p.designationPromotion + ' - ' + p.anneeDesignation }));
                    });
                } else {
                    $select.append($('<option>', { value: '', text: 'Aucune promotion trouvée' }));
                }
            })
            .fail(function() {
                $('#exportPromotionId').html('<option value="">Erreur de chargement</option>');
            });
    });

    var $modal = $('#createStudentModal');
    $modal.on('shown.bs.modal', function() {
        var $mAnnee = $modal.find('select[name="idAnnee"]');
        var $mOrientation = $('#orientationId');
        var $mPromotion = $('#promotionId');

        // Ensure defaults
        if (typeof DEFAULT_ACTIVE_YEAR_ID !== 'undefined' && DEFAULT_ACTIVE_YEAR_ID && $mAnnee.val() !== String(DEFAULT_ACTIVE_YEAR_ID)) {
            $mAnnee.val(String(DEFAULT_ACTIVE_YEAR_ID)).trigger('change');
        }

        function loadOrientationsForYear(anneeId) {
            $mOrientation.empty().append($('<option>', { value: '', text: 'Sélectionner une orientation' }));
            $mPromotion.empty().append($('<option>', { value: '', text: "Sélectionner d'abord une orientation" }));
            if (!anneeId) return;
            $.getJSON('controller/ajax_get_orientations.php', { annee_id: anneeId })
             .done(function(resp) {
                 var items = (resp && resp.orientations) ? resp.orientations : [];
                 items.forEach(function(o) {
                     $mOrientation.append($('<option>', { value: o.idorientation, text: o.designationOrientation }));
                 });
                 $mOrientation.trigger('change.select2');
             });
        }

        function loadPromotionsForOrientation(orientationId, anneeId) {
            $mPromotion.empty().append($('<option>', { value: '', text: 'Sélectionner une promotion' }));
            if (!orientationId) return;
            $.getJSON('controller/ajax_get_promotions_by_orientation.php', { orientation_id: orientationId, annee_id: anneeId })
             .done(function(resp) {
                 var items = (resp && resp.promotions) ? resp.promotions : [];
                 items.forEach(function(p) {
                     $mPromotion.append($('<option>', { value: p.idpromotion, text: p.designationPromotion }));
                 });
                 $mPromotion.trigger('change.select2');
             });
        }

        // Bind changes
        $mAnnee.off('change.e').on('change.e', function() {
            var anneeId = $(this).val();
            loadOrientationsForYear(anneeId);
        });

        $mOrientation.off('change.e').on('change.e', function() {
            var orientationId = $(this).val();
            var anneeId = $mAnnee.val();
            loadPromotionsForOrientation(orientationId, anneeId);
        });

        // Initial load
        loadOrientationsForYear($mAnnee.val());
    });

    // Import modal: set default year and load promotions
    $('#importStudentModal').on('shown.bs.modal', function() {
        var $iAnnee = $('#importIdAnnee');
        var $iPromotion = $('#importPromotionId');

        // Ensure defaults
        if (typeof DEFAULT_ACTIVE_YEAR_ID !== 'undefined' && DEFAULT_ACTIVE_YEAR_ID && $iAnnee.val() !== String(DEFAULT_ACTIVE_YEAR_ID)) {
            $iAnnee.val(String(DEFAULT_ACTIVE_YEAR_ID)).trigger('change.i');
        }

        function loadPromotionsForYear(anneeId) {
            $iPromotion.empty().append($('<option>', { value: '', text: 'Sélectionner une promotion' }));
            if (!anneeId) return;
            $.getJSON('controller/ajax_get_promotions.php', { annee_id: anneeId })
             .done(function(resp) {
                 var items = (resp && resp.promotions) ? resp.promotions : [];
                 items.forEach(function(p) {
                     $iPromotion.append($('<option>', { value: p.idpromotion, text: p.designationPromotion + ' - ' + p.anneeDesignation }));
                 });
                 $iPromotion.trigger('change.select2');
             });
        }

        // Bind change
        $iAnnee.off('change.i').on('change.i', function() {
            var anneeId = $(this).val();
            loadPromotionsForYear(anneeId);
        });

        // Initial load
        loadPromotionsForYear($iAnnee.val());
    });
});
</script>


<script>
(function() {
    'use strict';
    
    let currentPage = 1;
    const limit = 50;
    let isLoading = false;
    let hasMore = true;
    let rowIndex = <?= $i ?>; // Start index from PHP counter
    
    // Wait for DOM to be fully ready
    function initInfiniteScroll() {
        const sentinel = document.getElementById('loadingSentinel');
        const loadingIndicator = document.getElementById('loadingIndicator');
        const noMoreData = document.getElementById('noMoreData');
        const tableBody = document.getElementById('studentsTableBody');

        // Check if required elements exist
        if (!sentinel || !tableBody) {
            console.error('Required elements not found. Sentinel:', sentinel, 'TableBody:', tableBody);
            return;
        }

        console.log('Infinite scroll initialized', { sentinel, tableBody });
        
        function safeJsStr(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");
        }
        
        function safeHtml(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function loadMoreStudents() {
            if (isLoading || !hasMore) {
                console.log('Load blocked. isLoading:', isLoading, 'hasMore:', hasMore);
                return;
            }
            
            isLoading = true;
            loadingIndicator.style.display = 'block';
            
            // Build URL with existing filters
            const params = new URLSearchParams(window.location.search);
            params.set('page', currentPage + 1);
            params.set('limit', limit);
            
            const fetchUrl = 'controller/ajax_get_etudiants.php?' + params.toString();
            
            console.log('Fetching URL:', fetchUrl);
            
            fetch(fetchUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (!data.success) {
                        console.error('API Error:', data.message);
                        throw new Error(data.message || 'Unknown error');
                    }
                    
                    const students = data.students || [];
                    console.log('Students received:', students.length);
                    
                    if (students.length > 0) {
                        students.forEach(student => {
                            const tr = document.createElement('tr');
                            
                            // Build onclick handler with safe parameters
                            const editCall = `editStudent(${student.idetudiant}, ` +
                                `"${safeJsStr(student.matricule)}", ` +
                                `"${safeJsStr(student.noms)}", ` +
                                `"${safeJsStr(student.lieuNaissance || '')}", ` +
                                `"${student.dateNaissance || ''}", ` +
                                `"${safeJsStr(student.adressemail || '')}", ` +
                                `"${safeJsStr(student.telephone || '')}", ` +
                                `"${student.sexe || ''}", ` +
                                `"${safeJsStr(student.nationalite || '')}", ` +
                                `"${student.annee_acad_idannee_acad || ''}", ` +
                                `"${student.promotion_idpromotion || ''}")`;

                            tr.innerHTML = `
                                <td class='row-index'>${rowIndex++}</td>
                                <td>${safeHtml(student.matricule)}</td>
                                <td>${safeHtml(student.noms)}</td>
                                <td>${safeHtml(student.designationPromotion || '')}</td>
                                <td>${safeHtml(student.annee || '')}</td>
                                <td>${safeHtml(student.telephone || '')}</td>
                                <td>
                                    <div class='btn-group btn-group-sm' role='group'>
                                        <button class='btn btn-warning' onclick='${editCall}' type='button' title='Modifier'>
                                            <i class='bi bi-pencil-square'></i>
                                        </button>
                                        <button class='btn btn-info text-white' onclick='voirHistoriqueInscriptions("${safeJsStr(student.matricule)}", "${safeJsStr(student.noms)}")' type='button' title='Historique inscriptions'>
                                            <i class='bi bi-clock-history'></i>
                                        </button>
                                        <button class='btn btn-success' onclick='generateECard(${student.idetudiant})' type='button' title='E-Carte'>
                                            <i class='bi bi-credit-card'></i>
                                        </button>
                                        <button class='btn btn-danger' onclick='confirmDelete(${student.idetudiant})' type='button' title='Supprimer'>
                                            <i class='bi bi-trash'></i>
                                        </button>
                                    </div>
                                </td>
                            `;
                            
                            tableBody.appendChild(tr);
                        });
                        
                        currentPage++;
                        
                        // Check if we've reached the end
                        if (students.length < limit) {
                            hasMore = false;
                            noMoreData.style.display = 'block';
                            console.log('No more data available');
                        }
                    } else {
                        hasMore = false;
                        noMoreData.style.display = 'block';
                        console.log('Empty response, marking as no more data');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    isLoading = false;
                    loadingIndicator.style.display = 'none';
                })
                .finally(() => {
                    isLoading = false;
                    loadingIndicator.style.display = 'none';
                });
        }

        // Create Intersection Observer for infinite scroll
        const observerOptions = {
            root: null,
            rootMargin: '100px', // Start loading 100px before sentinel is visible
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            console.log('IntersectionObserver callback', entries[0]);
            if (entries[0].isIntersecting) {
                console.log('Sentinel is visible, loading more...');
                loadMoreStudents();
            }
        }, observerOptions);

        observer.observe(sentinel);
        console.log('Observer attached to sentinel');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInfiniteScroll);
    } else {
        initInfiniteScroll();
    }
})();
</script>

<!-- Modal Historique des Inscriptions -->
<div class="modal fade" id="historiqueInscriptionsModal" tabindex="-1" aria-labelledby="historiqueInscriptionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="historiqueInscriptionsModalLabel">
                    <i class="bi bi-clock-history"></i> Historique des inscriptions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="historiqueStudentName" class="mb-3"></h6>
                <div id="historiqueLoading" class="text-center py-4" style="display:none;">
                    <div class="spinner-border text-info" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2 text-muted">Chargement de l'historique...</p>
                </div>
                <div id="historiqueContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
function voirHistoriqueInscriptions(matricule, noms) {
    var modal = new bootstrap.Modal(document.getElementById('historiqueInscriptionsModal'));
    document.getElementById('historiqueStudentName').innerHTML = 
        '<i class="bi bi-person"></i> ' + noms + ' <span class="badge bg-secondary">' + matricule + '</span>';
    document.getElementById('historiqueLoading').style.display = 'block';
    document.getElementById('historiqueContent').innerHTML = '';
    modal.show();

    fetch('controller/ajax_get_historique_inscriptions.php?matricule=' + encodeURIComponent(matricule))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('historiqueLoading').style.display = 'none';
            if (!data.success || !data.inscriptions || data.inscriptions.length === 0) {
                document.getElementById('historiqueContent').innerHTML = 
                    '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Aucune inscription trouvée.</div>';
                return;
            }
            var html = '<div class="alert alert-light border"><strong>' + data.total + '</strong> inscription(s) trouvée(s)</div>';
            html += '<table class="table table-striped table-bordered table-sm">';
            html += '<thead class="table-dark"><tr>';
            html += '<th>#</th><th>Année académique</th><th>Section</th><th>Orientation</th><th>Promotion</th><th>Statut</th><th>Date enregistrement</th>';
            html += '</tr></thead><tbody>';
            data.inscriptions.forEach(function(insc, idx) {
                var statut = insc.est_actif == 1 
                    ? '<span class="badge bg-success">Active</span>' 
                    : '<span class="badge bg-secondary">Ancienne</span>';
                var dateEnreg = insc.dateEnregistrement 
                    ? new Date(insc.dateEnregistrement).toLocaleDateString('fr-FR') 
                    : '-';
                html += '<tr' + (insc.est_actif == 1 ? ' class="table-success"' : '') + '>';
                html += '<td>' + (idx + 1) + '</td>';
                html += '<td>' + (insc.annee || '-') + '</td>';
                html += '<td>' + (insc.designationSection || '-') + '</td>';
                html += '<td>' + (insc.designationOrientation || '-') + '</td>';
                html += '<td>' + (insc.designationPromotion || '-') + '</td>';
                html += '<td>' + statut + '</td>';
                html += '<td>' + dateEnreg + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('historiqueContent').innerHTML = html;
        })
        .catch(function(err) {
            document.getElementById('historiqueLoading').style.display = 'none';
            document.getElementById('historiqueContent').innerHTML = 
                '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Erreur lors du chargement.</div>';
            console.error('Erreur historique inscriptions:', err);
        });
}
</script>

<?php include "./views/include/footer_file.php"; ?>
