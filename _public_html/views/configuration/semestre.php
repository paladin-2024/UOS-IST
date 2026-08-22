<?php
include "./views/include/header.php";
$universite = new Universite();
$connexion = Connexion::getInstance()->getPDO();

// Récupérer l'année académique actuelle (active)
$currentYear = $universite->getCurrentAcademicYear();

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filterAnnee = isset($_GET['filter_annee']) ? $_GET['filter_annee'] : ($currentYear ? $currentYear['idannee_acad'] : '');

$promotionss = $universite->getPromotions();
?>
<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>SEMESTRES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Semestres</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->

    <!-- Section Dashboard -->
    <section class="section dashboard">
        <div class="row">
            <!-- Tableau de données -->
            <div class="col-lg-12">
                <div class="row">
                    <!-- Table semestres -->
                    <div class="col-12">
                        <div class="card overflow-auto">
                            <div class="card-body">
                                <h5 class="card-title">
                                    Gestion des semestres
                                    <span>
                                        | <a data-bs-toggle="modal" data-bs-target="#createSemestreModal" class="btnPage">
                                            <i class="bi bi-plus-circle-fill"></i> Ajouter
                                        </a>
                                    </span>
                                </h5>

                                <form method="GET" action="" class="mb-3">
                                <input type="hidden" name="view" value="configuration/semestre">
                                <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Rechercher par numéro...">
                                    </div>
                                        <div class="col-md-4">
                                            <select name="filter_annee" class="form-select">
                                                <option value="">Toutes les années</option>
                                                <?php
                                                $anneesAcademiques = $universite->getAcademicYears();
                                                foreach ($anneesAcademiques as $annee): ?>
                                                    <option value="<?= $annee['idannee_acad'] ?>" <?= $filterAnnee == $annee['idannee_acad'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($annee['designation']) ?>
                                                        <?php if ($currentYear && $annee['idannee_acad'] == $currentYear['idannee_acad']): ?>
                                                            (En cours)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary">Filtrer</button>
                                        </div>
                                    </div>
                                </form>

                                <!-- Remplacer la section du tableau des semestres par celle-ci -->
                                <table class="table table-striped table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Numéro du Semestre</th>
                                            <th scope="col">Promotions associées</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Construire la requête pour récupérer les semestres filtrés
                                        $query = "SELECT DISTINCT s.numeroSemestre
                                                  FROM semestre s
                                                  JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                                                  LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
                                                  WHERE 1=1";

                                        $params = [];

                                        if (!empty($search)) {
                                            $query .= " AND s.numeroSemestre LIKE ?";
                                            $params[] = '%' . $search . '%';
                                        }

                                        if (!empty($filterAnnee)) {
                                            $query .= " AND p.annee_acad_idannee_acad = ?";
                                            $params[] = $filterAnnee;
                                        }

                                        $query .= " ORDER BY s.numeroSemestre";

                                        $connexion = Connexion::getInstance()->getPDO();
                                        $stmt = $connexion->prepare($query);
                                        $stmt->execute($params);
                                        $listeSemestresGroupes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        $i = 1;

                                        foreach ($listeSemestresGroupes as $semestre) {
                                            // Récupérer toutes les instances de ce semestre filtrées
                                            $queryInstances = "SELECT s.*, p.designationPromotion, aa.designation as annee
                                                               FROM semestre s
                                                               JOIN promotion p ON s.promotion_idpromotion = p.idpromotion
                                                               LEFT JOIN annee_acad aa ON p.annee_acad_idannee_acad = aa.idannee_acad
                                                               WHERE s.numeroSemestre = ?";

                                            $paramsInstances = [$semestre['numeroSemestre']];

                                            if (!empty($search)) {
                                                $queryInstances .= " AND s.numeroSemestre LIKE ?";
                                                $paramsInstances[] = '%' . $search . '%';
                                            }

                                            if (!empty($filterAnnee)) {
                                                $queryInstances .= " AND p.annee_acad_idannee_acad = ?";
                                                $paramsInstances[] = $filterAnnee;
                                            }

                                            $queryInstances .= " ORDER BY p.designationPromotion";

                                            $stmtInstances = $connexion->prepare($queryInstances);
                                            $stmtInstances->execute($paramsInstances);
                                            $instancesSemestre = $stmtInstances->fetchAll(PDO::FETCH_ASSOC);

                                            // Construire la liste des promotions
                                            $promotionsHtml = '';
                                            $idsSemestres = [];

                                            foreach ($instancesSemestre as $instance) {
                                                $promotionsHtml .= "<span class='badge bg-info me-1 mb-1'>{$instance['designationPromotion']} / {$instance['annee']}</span> ";
                                                $idsSemestres[] = $instance['idsemestre'];
                                            }

                                            // Convertir le tableau d'IDs en chaîne JSON pour les actions
                                            $idsSemestresJson = json_encode($idsSemestres);

                                            echo "
            <tr>
                <td>{$i}</td>
                <td><strong>{$semestre['numeroSemestre']}</strong></td>
                <td>{$promotionsHtml}</td>
                <td>
                    <button class='btn btn-sm btn-warning' onclick='editSemestreGroupe(\"{$semestre['numeroSemestre']}\", {$idsSemestresJson})'>
                        <i class='bi bi-pencil-square'></i> Modifier
                    </button>
                    <button class='btn btn-sm btn-danger' onclick='confirmDeleteGroupe(\"{$semestre['numeroSemestre']}\", {$idsSemestresJson})'>
                        <i class='bi bi-trash'></i> Supprimer
                    </button>
                    <button class='btn btn-sm btn-info' onclick='viewSemestreDetails(\"{$semestre['numeroSemestre']}\", {$idsSemestresJson})'>
                        <i class='bi bi-eye'></i> Détails
                    </button>
                </td>
            </tr>";
                                            $i++;
                                        }

                                        if (empty($listeSemestresGroupes)) {
                                            echo "<tr><td colspan='4' class='text-center'>Aucun semestre trouvé</td></tr>";
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

<!-- Modal pour ajouter un semestre -->
<!-- Remplacer le modal d'ajout de semestre par celui-ci -->
<div class="modal fade" id="createSemestreModal" tabindex="-1" role="dialog" aria-labelledby="createSemestreModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ajouter un Semestre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/create_semestre.php" class="needs-validation" id="createSemestreForm" novalidate>
                    <input type="hidden" name="action" value="create_multiple">

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="numeroSemestre" class="form-label">Numéro du Semestre <span class="text-danger">*</span></label>
                            <input type="text" name="numeroSemestre" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un numéro de semestre.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Sélection des promotions <span class="text-danger">*</span></label>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Sélectionnez les promotions dans lesquelles vous souhaitez ajouter ce semestre.
                            </div>

                            <div class="card">
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <div class="mb-3">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input type="text" class="form-control" id="promotionSearch"
                                                placeholder="Rechercher une promotion...">
                                        </div>
                                    </div>

                                    <div class="btn-group mb-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="selectAllPromotions">
                                            Tout sélectionner
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllPromotions">
                                            Tout désélectionner
                                        </button>
                                    </div>

                                    <div id="promotionsContainer" class="border rounded p-3">
                                    <?php
                                    // Récupérer les sections de l'année académique en cours
                                    $sections = $universite->getSections('', $currentYear ? $currentYear['idannee_acad'] : null);
                                    foreach ($sections as $section) {
                                        echo "<h6 class='mt-2'>{$section['designationSection']}</h6>";

                                        // Récupérer les promotions pour cette section filtrées par année
                                        $promotionsBySection = $universite->getPromotionsBySection($section['idsection']);

                                        // Filtrer par année si nécessaire
                                        if (!empty($filterAnnee)) {
                                        $promotionsBySection = array_filter($promotionsBySection, function($promotion) use ($filterAnnee) {
                                        return $promotion['annee_acad_idannee_acad'] == $filterAnnee;
                                        });
                                        }

                                        if (!empty($promotionsBySection)) {
                                        echo "<div class='ms-3 mb-3 row'>";
                                        foreach ($promotionsBySection as $promotion) {
                                            echo "
                                            <div class='form-check col-md-4 mb-2'>
                                                    <input class='form-check-input' type='checkbox' name='promotions[]' value='{$promotion['idpromotion']}' id='promotion_{$promotion['idpromotion']}'>
                                                <label class='form-check-label' for='promotion_{$promotion['idpromotion']}'>
                                                        {$promotion['designationPromotion']} ({$promotion['anneeDesignation']})
                                                        </label>
                                                    </div>";
                                                 }
                                                 echo "</div>";
                                             } else {
                                                 echo "<div class='ms-3 text-muted'>Aucune promotion disponible</div>";
                                             }
                                         }
                                         ?>
                                    </div>
                                </div>
                            </div>
                            <div class="invalid-feedback">Veuillez sélectionner au moins une promotion.</div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="addSemestreBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>




<!-- Ajouter ce modal pour les détails du semestre -->
<div class="modal fade" id="semestreDetailsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Semestre <span id="detailSemestreNumero"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Promotion</th>
                                <th>Année Académique</th>
                                <th>Date d'Enregistrement</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="detailSemestreBody">
                            <!-- Les détails seront chargés dynamiquement -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Ajouter ce modal pour modifier un groupe de semestres -->
<div class="modal fade" id="editSemestreGroupeModal" tabindex="-1" role="dialog" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un Groupe de Semestres</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/edit_semestre_groupe.php" class="needs-validation">
                    <input type="hidden" name="semestre_ids" id="editSemestreIds">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="editSemestreNumero" class="form-label">Numéro du Semestre <span class="text-danger">*</span></label>
                            <input type="text" name="numeroSemestre" id="editSemestreNumero" class="form-control" required>
                            <div class="invalid-feedback">Veuillez saisir un numéro de semestre.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="editSemestreGroupeBtn" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>



<script>
    function editSemestre(id, numero, promotionId) {
        document.getElementById('editSemestreId').value = id;
        document.getElementById('editSemestreNumero').value = numero;
        document.getElementById('editSemestrePromotion').value = promotionId;

        new bootstrap.Modal(document.getElementById('editSemestreModal')).show();
    }

    function confirmDelete(idSemestre) {
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
                window.location.href = 'controller/delete_semestre.php?idsemestre=' + idSemestre;
            }
        });
    }


    // Gestion de la recherche et de la sélection des promotions
    document.addEventListener('DOMContentLoaded', function() {
        // Recherche de promotion
        const promotionSearch = document.getElementById('promotionSearch');
        if (promotionSearch) {
            promotionSearch.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();

                // Rechercher dans les promotions
                document.querySelectorAll('#promotionsContainer .form-check-label').forEach(label => {
                    const promotionText = label.textContent.toLowerCase();
                    const promotionItem = label.closest('.form-check');
                    const sectionContainer = promotionItem.closest('.ms-3.mb-3.row');
                    const sectionTitle = sectionContainer ? sectionContainer.previousElementSibling : null;

                    const visible = promotionText.includes(searchTerm);

                    // Afficher/masquer les promotions
                    promotionItem.style.display = visible ? 'block' : 'none';

                    // Gérer la visibilité des sections
                    if (sectionContainer && sectionTitle) {
                        // Vérifier si au moins une promotion est visible dans cette section
                        const hasVisiblePromotion = Array.from(sectionContainer.querySelectorAll('.form-check'))
                            .some(item => item.style.display !== 'none');

                        sectionTitle.style.display = hasVisiblePromotion ? 'block' : 'none';
                        sectionContainer.style.display = hasVisiblePromotion ? 'flex' : 'none';
                    }
                });
            });
        }

        // Sélection/désélection de toutes les promotions
        const selectAllBtn = document.getElementById('selectAllPromotions');
        const deselectAllBtn = document.getElementById('deselectAllPromotions');

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                document.querySelectorAll('input[name="promotions[]"]').forEach(checkbox => {
                    checkbox.checked = true;
                });
            });
        }

        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                document.querySelectorAll('input[name="promotions[]"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
            });
        }

        // Validation du formulaire
        const createSemestreForm = document.getElementById('createSemestreForm');
        if (createSemestreForm) {
            createSemestreForm.addEventListener('submit', function(event) {
                // Vérifier si au moins une promotion est sélectionnée
                const promotionsChecked = document.querySelectorAll('input[name="promotions[]"]:checked');
                if (promotionsChecked.length === 0) {
                    event.preventDefault();
                    event.stopPropagation();

                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Veuillez sélectionner au moins une promotion.'
                    });

                    return false;
                }
            });
        }
    });
</script>

<!-- Ajouter ces fonctions JavaScript -->
<script>
    // Fonction pour modifier un groupe de semestres
    function editSemestreGroupe(numeroSemestre, idsSemestres) {
        // Ouvrir le modal d'édition avec le numéro de semestre
        document.getElementById('editSemestreNumero').value = numeroSemestre;

        // Stocker les IDs des semestres pour la mise à jour
        document.getElementById('editSemestreIds').value = JSON.stringify(idsSemestres);

        new bootstrap.Modal(document.getElementById('editSemestreGroupeModal')).show();
    }

    // Fonction pour confirmer la suppression d'un groupe de semestres
    function confirmDeleteGroupe(numeroSemestre, idsSemestres) {
        Swal.fire({
            title: 'Êtes-vous sûr ?',
            html: `Cette action supprimera le semestre <strong>${numeroSemestre}</strong> de toutes les promotions associées.<br>Cette action est irréversible !`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Oui, supprimer',
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                // Rediriger vers le contrôleur de suppression avec les IDs
                window.location.href = 'controller/delete_semestre_groupe.php?ids=' + JSON.stringify(idsSemestres);
            }
        });
    }

    // Fonction pour afficher les détails d'un semestre
    function viewSemestreDetails(numeroSemestre, idsSemestres) {
        document.getElementById('detailSemestreNumero').textContent = numeroSemestre;

        // Charger les détails via AJAX
        fetch(`controller/get_semestre_details.php?ids=${JSON.stringify(idsSemestres)}`)
            .then(response => response.json())
            .then(data => {
                let html = '';
                data.forEach(semestre => {
                    html += `
                    <tr>
                        <td>${semestre.idsemestre}</td>
                        <td>${semestre.designationPromotion}</td>
                        <td>${semestre.annee}</td>
                        <td>${new Date(semestre.dateEnregistrement).toLocaleString()}</td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editSemestre(
                                ${semestre.idsemestre}, 
                                '${semestre.numeroSemestre}',
                                ${semestre.promotion_idpromotion}
                            )">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="confirmDelete(${semestre.idsemestre})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                document.getElementById('detailSemestreBody').innerHTML = html;
            })
            .catch(error => {
                console.error('Erreur:', error);
                document.getElementById('detailSemestreBody').innerHTML =
                    '<tr><td colspan="5" class="text-center text-danger">Erreur lors du chargement des détails</td></tr>';
            });

        new bootstrap.Modal(document.getElementById('semestreDetailsModal')).show();
    }
</script>


<?php include "./views/include/footer.php"; ?>