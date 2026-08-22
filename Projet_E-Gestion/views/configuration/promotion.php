<?php
include "./views/include/header.php";
$universite = new Universite();
$connexion = Connexion::getInstance()->getPDO();

// Fetch users for the dropdown
$structure = new Structure();
$users = $structure->getUsers();

// Récupérer l'année académique actuelle (active)
$anneeActuelle = $universite->getActiveAcademicYear();

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filterAnnee = isset($_GET['filter_annee']) ? $_GET['filter_annee'] : ($anneeActuelle ? $anneeActuelle['idannee_acad'] : '');
$filterSection = isset($_GET['filter_section']) ? $_GET['filter_section'] : '';

// Récupérer toutes les années académiques
$anneesAcademiques = $universite->getAcademicYears();

// Récupérer toutes les sections
$sections = $universite->getSections();

// Récupérer les orientations de l'année actuelle pour les modals
$orientations = $universite->getOrientations('', $anneeActuelle ? $anneeActuelle['idannee_acad'] : null);
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
        <h1>PROMOTIONS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Promotions</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table promotions -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des promotions
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createPromotionModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                    <input type="hidden" name="view" value="configuration/promotion">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par designation...">
                                        </div>
                                        <div class="col-md-2">
                                            <select name="filter_annee" class="form-select">
                                                <option value="">Toutes les années</option>
                                                <?php foreach ($anneesAcademiques as $annee): ?>
                                                    <option value="<?= $annee['idannee_acad'] ?>" <?= $filterAnnee == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($annee['designation']) ?>
                                                        <?php if ($anneeActuelle && $annee['idannee_acad'] == $anneeActuelle['idannee_acad']): ?>
                                                            (En cours)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="filter_section" class="form-select">
                                                <option value="">Toutes les sections</option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?= $section['idsection'] ?>" <?= $filterSection == $section['idsection'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($section['designationSection']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary">Filtrer</button>
                                        </div>
                                        <div class="col-md-3">
                                            <a href="?view=configuration/promotion" class="btn btn-secondary">Réinitialiser</a>
                                        </div>
                                    </div>
                                </form>

                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Désignation</th>
                                            <th scope="col">Cycle</th>
                                            <th scope="col">Orientation</th>
                                            <th scope="col">Année Académique</th>
                                            <th scope="col">Date de Création</th>
                                            <th scope="col">Finaliste</th>
                                            <th scope="col">Étudiants</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Construire la requête avec filtres
                                        $query = "SELECT p.*, o.\"designationOrientation\" as orientationDesignation, aa.designation as anneeDesignation
                                                   FROM promotion p
                                                   LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                                                   LEFT JOIN section sec ON o.section_idsection = sec.idsection
                                                   LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
                                                   WHERE 1=1";

                                        $params = [];

                                        if (!empty($search)) {
                                            $query .= " AND p.\"designationPromotion\" LIKE ?";
                                            $params[] = '%' . $search . '%';
                                        }

                                        if (!empty($filterAnnee)) {
                                            $query .= " AND p.annee_acad_idannee_acad = ?";
                                            $params[] = $filterAnnee;
                                        }

                                        if (!empty($filterSection)) {
                                            $query .= " AND sec.idsection = ?";
                                            $params[] = $filterSection;
                                        }

                                        $query .= " ORDER BY p.\"designationPromotion\"";

                                        $stmt = $connexion->prepare($query);
                                        $stmt->execute($params);
                                        $listePromotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        $i = 1;

                                        foreach ($listePromotions as $promotion) {
                                            $dc = date('d/m/Y H:i:s', strtotime($promotion['dateCreation']));
                                            $estFinalisteBadge = $promotion['est_terminale'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-secondary">Non</span>';

                                            // Compter les étudiants de cette promotion
                                            $countStmt = $connexion->prepare("SELECT COUNT(*) as total FROM etudiant WHERE promotion_idpromotion = ?");
                                            $countStmt->execute([$promotion['idpromotion']]);
                                            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
                                            $totalEtudiants = $countResult['total'];

                                            echo "
                                            <tr>
                                                <td>{$i}</td>
                                                <td>{$promotion['designationPromotion']}</td>
                                                <td>{$promotion['cycle']}</td>
                                                <td>{$promotion['orientationDesignation']}</td>
                                                <td>{$promotion['anneeDesignation']}</td>
                                                <td>{$dc}</td>
                                                <td>{$estFinalisteBadge}</td>
                                                <td>
                                                    <span class='badge bg-info'>{$totalEtudiants}</span>
                                                </td>
                                                <td>
                                                    <button class='btn btn-sm btn-warning' onclick='editPromotion(
                                                        {$promotion['idpromotion']}, 
                                                        \"{$promotion['designationPromotion']}\",
                                                        \"{$promotion['cycle']}\",
                                                        \"{$promotion['orientation_idorientation']}\",
                                                        \"{$promotion['annee_acad_idannee_acad']}\",
                                                        {$promotion['est_terminale']}
                                                    )'>
                                                        <i class='bi bi-pencil-square'></i>
                                                    </button>
                                                    <button class='btn btn-sm btn-danger' onclick='confirmDelete({$promotion['idpromotion']})'>
                                                        <i class='bi bi-trash'></i>
                                                    </button>";

                                            if ($totalEtudiants > 0) {
                                                echo "<button class='btn btn-sm btn-outline-danger' onclick='showRemoveStudentsModal({$promotion['idpromotion']}, \"{$promotion['designationPromotion']}\")' title='Vider la promotion'>
                                                             <i class='bi bi-person-x'></i> Vider étudiant
                                                         </button>";
                                            }

                                            // Bouton pour configurer le choix d'orientation
                                            echo "<button class='btn btn-sm btn-info' onclick='configureChoixOrientation({$promotion['idpromotion']}, \"{$promotion['designationPromotion']}\", {$promotion['annee_acad_idannee_acad']})' title='Configurer le choix d\'orientation'>
                                                         <i class='bi bi-arrow-left-right'></i> Orientation
                                                     </button>";

                                            echo "</td>
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

<!-- Modal pour configurer le choix d'orientation -->
<div class="modal fade" id="configureChoixOrientationModal" tabindex="-1" role="dialog" aria-labelledby="configureChoixOrientationModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="bi bi-arrow-left-right me-2"></i>
                    Configurer le choix d'orientation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Promotion source :</strong> <span id="choixOrientationPromotionName"></span>
                </div>
                
                <input type="hidden" id="choixOrientationPromotionId">
                <input type="hidden" id="choixOrientationAnneeId">
                
                <div class="mb-4">
                    <label for="choixOrientationPromotions" class="form-label fw-bold">
                        <i class="bi bi-collection me-2"></i>Promotions disponibles pour le choix <span class="text-danger">*</span>
                    </label>
                    <select id="choixOrientationPromotions" class="form-select" multiple size="8" required>
                        <option value="">Chargement...</option>
                    </select>
                    <small class="text-muted">Maintenez Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs promotions</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-currency-dollar me-2"></i>Frais requis pour le choix d'orientation
                    </label>
                    <div id="choixOrientationFeesList" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                        <p class="text-muted mb-0">Chargement...</p>
                    </div>
                    <small class="text-muted">Sélectionnez les frais que l'étudiant doit payer avant de pouvoir faire son choix d'orientation</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-2"></i>Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="saveConfigurationChoixOrientation()">
                    <i class="bi bi-save me-2"></i>Enregistrer la configuration
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour supprimer les étudiants d'une promotion -->
<div class="modal fade" id="removeStudentsModal" tabindex="-1" role="dialog" aria-labelledby="removeStudentsModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Vider la promotion</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <strong>Attention !</strong> Cette opération va supprimer TOUS les étudiants de la promotion sélectionnée.
                </div>

                <p><strong>Promotion :</strong> <span id="promotionNameDisplay"></span></p>
                <p><strong>Nombre d'étudiants à supprimer :</strong> <span id="studentCountDisplay" class="badge bg-danger"></span></p>

                <div class="alert alert-info" role="alert">
                    <h6>Données liées qui seront supprimées :</h6>
                    <div id="dependenciesContainer" class="mt-3">
                        <p class="text-muted">Chargement des dépendances...</p>
                    </div>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="confirmRemovalCheckbox" value="yes">
                    <label class="form-check-label" for="confirmRemovalCheckbox">
                        Je comprends que cette action est irréversible et je confirme la suppression
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="confirmRemoveBtn" disabled onclick="confirmRemoveAllStudents()">
                    <i class="bi bi-trash"></i> Supprimer tous les étudiants
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour ajouter une promotion -->
<div class="modal fade" id="createPromotionModal" tabindex="-1" role="dialog" aria-labelledby="createPromotionModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-show="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter une Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_promotion.php" class="needs-validation" novalidate onsubmit="resetAddPromotionModal()">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="designationPromotion" class="form-label">Désignation en abbregé (Ex : L1 INFO) <span class="text-danger">*</span></label>
                            <input type="text" name="designationPromotion" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="cycle" class="form-label">Cycle <span class="text-danger">*</span></label>
                            <select name="cycle" class="form-control" required>
                                <option value="Premier">Premier</option>
                                <option value="Deuxieme">Deuxième</option>
                                <option value="Troisieme">Troisième</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un cycle.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="idAnnee" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="idAnnee" id="idAnnee" class="form-control" required onchange="loadOrientationsByYear(this.value, 'orientationId')">
                                <!-- Populate with academic years -->
                                <?php
                                $academicYears = $universite->getAcademicYears();
                                foreach ($academicYears as $year) {
                                    $selected = (!empty($filterAnnee) && $year['idannee_acad'] == $filterAnnee) ? 'selected' : '';
                                    echo "<option value='{$year['idannee_acad']}' $selected>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="orientationId" class="form-label">Orientation Ou filière<span class="text-danger">*</span></label>
                            <div class="position-relative">
                                <select name="orientationId" id="orientationId" class="form-control" required>
                                    <!-- Populate with orientations from current academic year -->
                                    <?php
                                    foreach ($orientations as $orientation) {
                                        echo "<option value='{$orientation['idorientation']}'>{$orientation['designationOrientation']}</option>";
                                    }
                                    ?>
                                </select>
                                <div id="orientationSpinner" class="spinner-loading" style="display: none;">
                                    <span class="spinner-border spinner-border-sm text-primary"></span>
                                </div>
                            </div>
                            <div class="invalid-feedback">Veuillez sélectionner une orientation.</div>
                        </div>
                        <!-- Dans le modal d'ajout, ajoutez ce champ après l'année académique -->
                        <div class="col-md-12 mt-3">
                            <div class="form-check">
                                <input type="checkbox" name="estTerminale" id="estTerminale" class="form-check-input">
                                <label for="estTerminale" class="form-check-label">Promotion terminale/finaliste</label>
                            </div>
                            <small class="text-muted">Cochez cette case si la promotion est terminale (dernière année du cycle).</small>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addPromotionBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour modifier une promotion -->
<div class="modal fade" id="editPromotionModal" tabindex="-1" role="dialog" aria-labelledby="editPromotionModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier une Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_promotion.php" class="needs-validation" novalidate>
                    <input type="hidden" name="editPromotionId" id="editPromotionId">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editPromotionDesignation" class="form-label">Désignation en abbregé (Ex : L1 INFO)<span class="text-danger">*</span></label>
                            <input type="text" name="editPromotionDesignation" id="editPromotionDesignation" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir une désignation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="editCycle" class="form-label">Cycle <span class="text-danger">*</span></label>
                            <select name="editCycle" id="editCycle" class="form-control" required>
                                <option value="Premier">Premier</option>
                                <option value="Deuxieme">Deuxième</option>
                                <option value="Troisieme">Troisième</option>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner un cycle.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="editOrientationId" class="form-label">Orientation ou filière<span class="text-danger">*</span></label>
                            <select name="editOrientationId" id="editOrientationId" class="form-control" required>
                                <!-- Populate with orientations from current academic year -->
                                <?php
                                foreach ($orientations as $orientation) {
                                    echo "<option value='{$orientation['idorientation']}'>{$orientation['designationOrientation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une orientation.</div>
                        </div>
                        <div class="col-md-12">
                            <label for="editAnneeId" class="form-label">Année Académique <span class="text-danger">*</span></label>
                            <select name="editAnneeId" id="editAnneeId" class="form-control" required onchange="loadOrientationsByYear(this.value, 'editOrientationId')">
                                <!-- Populate with academic years -->
                                <?php
                                foreach ($academicYears as $year) {
                                    echo "<option value='{$year['idannee_acad']}'>{$year['designation']}</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Veuillez sélectionner une année académique.</div>
                        </div>
                        <!-- Dans le modal d'édition, ajoutez ce champ après l'année académique -->
                        <div class="col-md-12 mt-3">
                            <div class="form-check">
                                <input type="checkbox" name="editEstTerminale" id="editEstTerminale" class="form-check-input">
                                <label for="editEstTerminale" class="form-check-label">Promotion terminale/finaliste</label>
                            </div>
                            <small class="text-muted">Cochez cette case si la promotion est terminale (dernière année du cycle).</small>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="updatePromotionBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let currentRemovalPromotionId = null;

    // Charger les orientations basées sur l'année académique sélectionnée
    function loadOrientationsByYear(anneeId, selectFieldId) {
        return new Promise((resolve, reject) => {
            if (!anneeId) {
                document.getElementById(selectFieldId).innerHTML = '<option value="">Sélectionnez une année d\'abord</option>';
                hideOrientationSpinner();
                reject('Pas d\'année sélectionnée');
                return;
            }

            // Afficher le spinner de chargement
            const selectField = document.getElementById(selectFieldId);
            selectField.innerHTML = '<option value="">Chargement...</option>';
            selectField.disabled = true;
            showOrientationSpinner();

            // Appel AJAX pour charger les orientations
            fetch('controller/get_orientations_by_year.php?annee_id=' + anneeId)
                .then(response => response.json())
                .then(data => {
                    selectField.disabled = false;
                    hideOrientationSpinner();

                    if (data.success && data.orientations.length > 0) {
                        let html = '';
                        data.orientations.forEach(orientation => {
                            html += `<option value="${orientation.idorientation}">${orientation.designationOrientation}</option>`;
                        });
                        selectField.innerHTML = html;
                        resolve();
                    } else {
                        selectField.innerHTML = '<option value="">Aucune orientation disponible pour cette année</option>';
                        resolve();
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    selectField.innerHTML = '<option value="">Erreur lors du chargement</option>';
                    selectField.disabled = false;
                    hideOrientationSpinner();
                    reject(error);
                });
        });
    }

    // Afficher le spinner du chargement des orientations
    function showOrientationSpinner() {
        const spinner = document.getElementById('orientationSpinner');
        if (spinner) {
            spinner.style.display = 'inline-flex';
        }
    }

    // Masquer le spinner du chargement des orientations
    function hideOrientationSpinner() {
        const spinner = document.getElementById('orientationSpinner');
        if (spinner) {
            spinner.style.display = 'none';
        }
    }

    function editPromotion(id, designation, cycle, orientationId, anneeId, estTerminale) {
        document.getElementById('editPromotionId').value = id;
        document.getElementById('editPromotionDesignation').value = designation;
        document.getElementById('editCycle').value = cycle;
        document.getElementById('editAnneeId').value = anneeId;
        document.getElementById('editEstTerminale').checked = estTerminale === 1;

        // Charger les orientations pour cette année académique
        loadOrientationsByYear(anneeId, 'editOrientationId').then(() => {
            // Après avoir chargé les orientations, définir l'orientation sélectionnée
            setTimeout(() => {
                document.getElementById('editOrientationId').value = orientationId;
            }, 100);
        }).catch(() => {
            // Si le chargement échoue, définir l'orientation directement
            document.getElementById('editOrientationId').value = orientationId;
        });

        new bootstrap.Modal(document.getElementById('editPromotionModal')).show();
    }

    function confirmDelete(idPromotion) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            text: "Cette action supprimera également tous les cours et étudiants associés à cette promotion. Cette action est irréversible !",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'controller/delete_promotion.php?idpromotion=' + idPromotion;
            }
        });
    }

    function showRemoveStudentsModal(promotionId, promotionName) {
        currentRemovalPromotionId = promotionId;

        // Réinitialiser le formulaire
        document.getElementById('confirmRemovalCheckbox').checked = false;
        document.getElementById('confirmRemoveBtn').disabled = true;

        // Afficher les infos de la promotion
        document.getElementById('promotionNameDisplay').textContent = promotionName;

        // Charger les dépendances
        loadDependencies(promotionId);

        // Afficher le modal
        new bootstrap.Modal(document.getElementById('removeStudentsModal')).show();
    }

    function loadDependencies(promotionId) {
        fetch('controller/get_promotion_dependencies.php?idpromotion=' + promotionId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDependencies(data);
                } else {
                    document.getElementById('dependenciesContainer').innerHTML =
                        '<div class="alert alert-danger">Erreur lors du chargement des dépendances</div>';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('dependenciesContainer').innerHTML =
                    '<div class="alert alert-danger">Erreur lors du chargement des dépendances</div>';
            });
    }

    function displayDependencies(data) {
        let html = '';
        let hasDependencies = false;

        if (data.total_students > 0) {
            html += `<div class="mb-3">
                        <strong>Étudiants :</strong> <span class="badge bg-danger">${data.total_students}</span>
                     </div>`;
            hasDependencies = true;

            // Afficher le nombre d'étudiants
            document.getElementById('studentCountDisplay').textContent = data.total_students;
        }

        if (data.inscriptions > 0) {
            html += `<div class="mb-2">
                        <i class="bi bi-circle-fill text-warning" style="font-size: 0.5rem;"></i>
                        <strong>${data.inscriptions}</strong> inscription(s) aux cours
                     </div>`;
        }

        if (data.notes > 0) {
            html += `<div class="mb-2">
                        <i class="bi bi-circle-fill text-warning" style="font-size: 0.5rem;"></i>
                        <strong>${data.notes}</strong> note(s) enregistrée(s)
                     </div>`;
        }

        if (data.absences > 0) {
            html += `<div class="mb-2">
                        <i class="bi bi-circle-fill text-warning" style="font-size: 0.5rem;"></i>
                        <strong>${data.absences}</strong> absence(s) enregistrée(s)
                     </div>`;
        }

        if (data.examinations > 0) {
            html += `<div class="mb-2">
                        <i class="bi bi-circle-fill text-warning" style="font-size: 0.5rem;"></i>
                        <strong>${data.examinations}</strong> composition(s) d'examen
                     </div>`;
        }

        if (data.fees > 0) {
            html += `<div class="mb-2">
                        <i class="bi bi-circle-fill text-warning" style="font-size: 0.5rem;"></i>
                        <strong>${data.fees}</strong> frais/paiement(s)
                     </div>`;
        }

        if (data.internships > 0) {
            html += `<div class="mb-2">
                        <i class="bi bi-circle-fill text-warning" style="font-size: 0.5rem;"></i>
                        <strong>${data.internships}</strong> stage(s)
                     </div>`;
        }

        if (data.dissertation > 0) {
            html += `<div class="mb-2">
                        <i class="bi bi-circle-fill text-warning" style="font-size: 0.5rem;"></i>
                        <strong>${data.dissertation}</strong> mémoire(s)/soutenance(s)
                     </div>`;
        }

        if (!hasDependencies && html === '') {
            html = '<p class="text-muted">Aucune donnée liée trouvée. Les étudiants peuvent être supprimés sans impact.</p>';
        } else if (hasDependencies) {
            html += '<p class="text-danger mt-2"><strong>Note :</strong> Toutes ces données seront également supprimées.</p>';
        }

        document.getElementById('dependenciesContainer').innerHTML = html;
    }

    document.getElementById('confirmRemovalCheckbox').addEventListener('change', function() {
        document.getElementById('confirmRemoveBtn').disabled = !this.checked;
    });

    // Initialiser le modal de création avec les bonnes orientations
    function initCreatePromotionModal() {
        const anneeSelect = document.getElementById('idAnnee');
        if (anneeSelect && anneeSelect.value) {
            loadOrientationsByYear(anneeSelect.value, 'orientationId');
        }
    }

    // Réinitialiser le modal après la création
    function resetAddPromotionModal() {
        setTimeout(() => {
            document.querySelector('#createPromotionModal form').reset();
            const anneeSelect = document.getElementById('idAnnee');
            if (anneeSelect && anneeSelect.value) {
                loadOrientationsByYear(anneeSelect.value, 'orientationId');
            }
        }, 500);
    }

    // Ajouter un listener pour initialiser le modal quand il s'ouvre
    document.addEventListener('DOMContentLoaded', function() {
        const createModal = document.getElementById('createPromotionModal');
        if (createModal) {
            createModal.addEventListener('shown.bs.modal', initCreatePromotionModal);
        }
    });

    function confirmRemoveAllStudents() {
        if (!currentRemovalPromotionId) return;

        Swal.fire({
            title: 'Confirmation finale',
            text: 'Êtes-vous absolument certain de vouloir supprimer TOUS les étudiants et leurs données associées ?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Oui, supprimer définitivement',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Afficher un loader
                Swal.fire({
                    title: 'Suppression en cours...',
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });

                // Envoyer la requête
                fetch('controller/remove_all_students_promotion.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            idpromotion: currentRemovalPromotionId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Succès',
                                text: data.message,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Recharger la page
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Erreur',
                                text: data.message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de la suppression.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
            }
        });
    }

    // Variables pour stocker les selections existantes
    let existingPromotions = [];
    let existingFees = [];

    // Fonction pour configurer le choix d'orientation
    function configureChoixOrientation(promotionId, promotionName, anneeId) {
        currentChoixOrientationPromotionId = promotionId;
        currentChoixOrientationAnneeId = anneeId;
        
        document.getElementById('choixOrientationPromotionName').textContent = promotionName;
        document.getElementById('choixOrientationPromotionId').value = promotionId;
        document.getElementById('choixOrientationAnneeId').value = anneeId;
        
        // Afficher le modal
        const modal = new bootstrap.Modal(document.getElementById('configureChoixOrientationModal'));
        modal.show();
        
        // Charger la configuration existante d'abord
        loadExistingConfiguration(promotionId, anneeId);
    }

    // Charger la configuration existante
    function loadExistingConfiguration(promotionId, anneeId) {
        fetch('controller/ajax/get_choix_orientation_config.php?promotion_id=' + promotionId + '&annee_id=' + anneeId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.selected_promotions) {
                    existingPromotions = data.selected_promotions;
                    existingFees = data.selected_fees || [];
                }
                // Charger les promotions et frais après avoir récupéré la config existante
                loadAvailablePromotionsForChoice(promotionId, anneeId);
                loadAvailableFees(promotionId);
            })
            .catch(error => {
                console.error('Erreur chargement config:', error);
                loadAvailablePromotionsForChoice(promotionId, anneeId);
                loadAvailableFees(promotionId);
            });
    }

    // Charger les promotions disponibles pour le choix d'orientation
    function loadAvailablePromotionsForChoice(promotionId, anneeId) {
        console.log('Loading promotions for choice:', promotionId, anneeId);
        fetch('controller/ajax/get_promotions_for_choice.php?promotion_id=' + promotionId + '&annee_id=' + anneeId)
            .then(response => {
                console.log('Response:', response);
                return response.json();
            })
            .then(data => {
                console.log('Data:', data);
                if (data.success && data.promotions && data.promotions.length > 0) {
                    let html = '';
                    data.promotions.forEach(function(promo) {
                        // Pré-sélectionner si c'est dans la configuration existante
                        let selected = existingPromotions.includes(promo.idpromotion) ? 'selected' : '';
                        html += `<option value="${promo.idpromotion}" ${selected}>${promo.designationPromotion} - ${promo.designationOrientation || 'Sans orientation'} (${promo.cycle})</option>`;
                    });
                    document.getElementById('choixOrientationPromotions').innerHTML = html;
                } else if (data.success) {
                    document.getElementById('choixOrientationPromotions').innerHTML = '<option value="">Aucune promotion disponible</option>';
                } else {
                    document.getElementById('choixOrientationPromotions').innerHTML = '<option value="">Erreur: ' + (data.message || 'Inconnu') + '</option>';
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('choixOrientationPromotions').innerHTML = '<option value="">Erreur lors du chargement</option>';
            });
    }

    // Charger les frais disponibles pour la promotion
    function loadAvailableFees(promotionId) {
        console.log('Loading fees for promotion:', promotionId);
        fetch('controller/ajax/get_frais_by_promotion.php?promotion_id=' + promotionId)
            .then(response => {
                console.log('Fees response:', response);
                return response.json();
            })
            .then(data => {
                console.log('Fees data:', data);
                if (data.success && data.frais && data.frais.length > 0) {
                    let html = '';
                    data.frais.forEach(function(frais) {
                        // Pré-sélectionner si c'est dans la configuration existante
                        let checked = existingFees.includes(frais.id) ? 'checked' : '';
                        html += `<div class="form-check">
                            <input class="form-check-input" type="checkbox" name="choixOrientationFees[]" value="${frais.id}" id="fee_${frais.id}" ${checked}>
                            <label class="form-check-label" for="fee_${frais.id}">
                                ${frais.designation} - ${parseFloat(frais.montant).toFixed(2)} ${frais.devise}
                            </label>
                        </div>`;
                    });
                    document.getElementById('choixOrientationFeesList').innerHTML = html;
                } else {
                    document.getElementById('choixOrientationFeesList').innerHTML = '<p class="text-muted">Aucun frais assigné à cette promotion</p>';
                }
            })
            .catch(error => {
                console.error('Erreur fees:', error);
                document.getElementById('choixOrientationFeesList').innerHTML = '<p class="text-danger">Erreur lors du chargement</p>';
            });
    }

    // Sauvegarder la configuration du choix d'orientation
    function saveConfigurationChoixOrientation() {
        const promotionId = document.getElementById('choixOrientationPromotionId').value;
        const anneeId = document.getElementById('choixOrientationAnneeId').value;
        
        // Récupérer les promotions cibles sélectionnées avec Select2
        const selectedPromotions = $('#choixOrientationPromotions').val() || [];
        
        // Récupérer les frais sélectionnés
        const selectedFees = [];
        document.querySelectorAll('input[name="choixOrientationFees[]"]:checked').forEach(function(checkbox) {
            selectedFees.push(checkbox.value);
        });
        
        if (selectedPromotions.length === 0) {
            Swal.fire({
                title: 'Erreur',
                text: 'Veuillez sélectionner au moins une promotion cible.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Envoyer la configuration au serveur
        const formData = new FormData();
        formData.append('promotion_source_id', promotionId);
        formData.append('annee_acad_id', anneeId);
        formData.append('promotions_cibles', JSON.stringify(selectedPromotions));
        formData.append('frais_ids', JSON.stringify(selectedFees));
        
        fetch('controller/ajax/save_choix_orientation_config.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Succès',
                    text: 'Configuration du choix d\'orientation enregistrée avec succès.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    bootstrap.Modal.getInstance(document.getElementById('configureChoixOrientationModal')).hide();
                });
            } else {
                Swal.fire({
                    title: 'Erreur',
                    text: data.message || 'Erreur lors de l\'enregistrement.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            Swal.fire({
                title: 'Erreur',
                text: 'Une erreur est survenue lors de l\'enregistrement.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        });
    }
</script>

<?php include "./views/include/footer.php"; ?>