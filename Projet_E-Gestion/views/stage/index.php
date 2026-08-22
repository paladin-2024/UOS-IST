<?php
include "./views/include/header.php";

$stage = new Stage(); // Assuming a Stage model exists

// Vérification des droits d'accès
$userId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

// Fetch user's responsibilities (only if not admin)
$userResponsibilities = [];
if (!$hasFullAccess) {
    try {
        $userResponsibilities = $stage->getUserResponsibilities($userId);
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des responsabilités: " . $e->getMessage());
        $userResponsibilities = [];
    }
}

// Si l'utilisateur n'est pas admin et n'a aucune responsabilité, refuser l'accès
if (!$hasFullAccess && empty($userResponsibilities)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    include "./views/include/footer.php";
    exit;
}

// Get active academic year
$activeYear = $stage->getActiveAcademicYear();
$activeYearId = $activeYear ? $activeYear['idannee_acad'] : null;

// Selected year
$selectedYearId = isset($_GET['annee_acad']) ? $_GET['annee_acad'] : $activeYearId;

// Selected promotion for filtering
$selectedPromotionId = isset($_GET['promotion']) ? $_GET['promotion'] : '';

// Get all years
$allYears = $stage->getAcademicYears();

// Get promotions for the user in selected year (or all promotions for admin)
$promotions = [];
try {
    if ($hasFullAccess) {
        // Admin voit toutes les promotions de l'année sélectionnée
        $universite = new Universite();
        $promotions = $universite->getPromotionsByAnneeAcad($selectedYearId);
    } else {
        // Utilisateur normal voit seulement ses promotions
        $promotions = $stage->getUserPromotions($userId, $selectedYearId);
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des promotions: " . $e->getMessage());
    $promotions = [];
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
?>

<!-- Espace de travail -->
<main id="main" class="main">

    <div class="pagetitle">
        <h1>GESTION DES STAGES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Stages</li>
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

            <!-- Filtres -->
            <div class="col-lg-12 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="yearFilter">Année Académique:</label>
                                <select id="yearFilter" class="form-select" onchange="updateYearFilter(this.value)">
                                    <?php foreach ($allYears as $year): ?>
                                        <option value="<?php echo $year['idannee_acad']; ?>" <?php echo ($selectedYearId == $year['idannee_acad']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year['designation']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="promotionFilter">Filtrer par Promotion:</label>
                                <select id="promotionFilter" class="form-select" onchange="updatePromotionFilter(this.value)">
                                    <option value="">Toutes les promotions</option>
                                    <?php foreach ($promotions as $promotion): ?>
                                        <option value="<?php echo $promotion['idpromotion']; ?>" <?php echo ($selectedPromotionId == $promotion['idpromotion']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($promotion['designationPromotion']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="searchFilter">Rechercher par nom d'étudiant:</label>
                                <input type="text" id="searchFilter" class="form-control" placeholder="Tapez pour rechercher...">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button id="clearFilters" class="btn btn-secondary">Effacer filtres</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fetch data for statistics and table -->
            <?php
            $allStudentsWithStages = [];
            $db = Connexion::getInstance()->getPDO();

            try {
                // Récupérer tous les étudiants affectés à un stage
                $sql = "SELECT
                            e.idetudiant,
                            e.matricule,
                            e.noms,
                            p.\"designationPromotion\",
                            p.idpromotion,
                            s.idstage,
                            s.lieu_stage,
                            s.date_debut,
                            s.date_fin,
                            s.rapport_path,
                            s.cote_lecteur,
                            s.cote_entreprise,
                            a.noms as encadreur_nom,
                            al.noms as lecteur_nom
                        FROM stage_assignments s
                        JOIN etudiant e ON s.idetudiant = e.idetudiant
                        JOIN promotion p ON e.promotion_idpromotion = p.idpromotion
                        LEFT JOIN agent a ON s.idencadreur = a.\"idAgent\"
                        LEFT JOIN agent al ON s.idlecteur = al.\"idAgent\"
                        WHERE e.annee_acad_idannee_acad = :yearId";

                $params = ['yearId' => $selectedYearId];

                // Filtrer par promotion si nécessaire
                if ($selectedPromotionId) {
                    $sql .= " AND p.idpromotion = :promotionId";
                    $params['promotionId'] = $selectedPromotionId;
                }

                // Filtrer par les promotions de l'utilisateur si pas admin
                if (!$hasFullAccess && !empty($promotions)) {
                    $promotionIds = array_column($promotions, 'idpromotion');
                    $placeholders = implode(',', array_fill(0, count($promotionIds), '?'));
                    $sql .= " AND p.idpromotion IN ($placeholders)";
                    $params = array_merge($params, $promotionIds);
                }

                $sql .= " ORDER BY p.\"designationPromotion\", e.noms";

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $allStudentsWithStages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Erreur lors de la récupération des étudiants en stage: " . $e->getMessage());
                $allStudentsWithStages = [];
            }
            ?>

            <!-- Statistiques globales ou de la promotion filtrée -->
            <div class="col-lg-12 mb-3">
                <div class="row">
                    <?php
                    $assignedStudents = count($allStudentsWithStages);
                    $reportsReceived = count(array_filter($allStudentsWithStages, function ($student) {
                        return !empty($student['rapport_path']);
                    }));

                    if ($selectedPromotionId) {
                        $promotionName = '';
                        foreach ($promotions as $promo) {
                            if ($promo['idpromotion'] == $selectedPromotionId) {
                                $promotionName = $promo['designationPromotion'];
                                break;
                            }
                        }
                        $statTitle = 'Statistiques de la Promotion';
                        $statSubtitle = htmlspecialchars($promotionName);
                    } else {
                        $statTitle = 'Statistiques Globales';
                        $statSubtitle = 'Toutes les promotions';
                    }
                    ?>
                    <div class="col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary">
                                    <i class="bi bi-person-check-fill me-2"></i><?php echo $statTitle; ?>
                                </h5>
                                <h3 class="text-success mb-0"><?php echo $assignedStudents; ?></h3>
                                <small class="text-muted"><?php echo $statSubtitle; ?></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-info">
                                    <i class="bi bi-file-earmark-check-fill me-2"></i>Rapports Reçus
                                </h5>
                                <h3 class="text-info mb-0"><?php echo $reportsReceived; ?></h3>
                                <small class="text-muted">Sur <?php echo $assignedStudents; ?> étudiants</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions globales -->
            <div class="col-lg-12 mb-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <?php if ($hasFullAccess): ?>
                            <a href="stage/assign" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Affecter Étudiants
                            </a>
                            <a href="stage/assign_lecteurs" class="btn btn-primary">
                                <i class="bi bi-person-badge"></i> Affecter Lecteurs
                            </a>
                            <a href="stage/fees" class="btn btn-warning">
                                <i class="bi bi-cash"></i> Configurer Frais
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tableau de données unique -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Étudiants en Stage</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered datatable" id="stagesTable">
                                <thead>
                                    <tr>
                                        <th>Matricule</th>
                                        <th>Étudiant</th>
                                        <th>Promotion</th>
                                        <th>Statut</th>
                                        <th>Lieu de Stage</th>
                                        <th>Encadreur</th>
                                        <th>Lecteur</th>
                                        <th>Cote/20</th>
                                        <th>Date Début</th>
                                        <th>Date Fin</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($allStudentsWithStages as $student):
                                        $hasReport = !empty($student['rapport_path']);
                                        $dateDebut = $student['date_debut'] ? date('d/m/Y', strtotime($student['date_debut'])) : '-';
                                        $dateFin = $student['date_fin'] ? date('d/m/Y', strtotime($student['date_fin'])) : '-';
                                    ?>
                                        <tr data-promotion="<?php echo htmlspecialchars($student['idpromotion'] ?? ''); ?>">
                                            <td><?php echo htmlspecialchars($student['matricule'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($student['noms']); ?></td>
                                            <td><?php echo htmlspecialchars($student['designationPromotion'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($hasReport): ?>
                                                    <span class="badge bg-success">Rapport déposé</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">En cours</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['lieu_stage'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($student['encadreur_nom'] ?? '-'); ?></td>
                                            <td>
                                                <?php if ($hasReport): ?>
                                                    <?php echo htmlspecialchars($student['lecteur_nom'] ?? '-'); ?>
                                                    <?php if ($hasFullAccess && empty($student['lecteur_nom'])): ?>
                                                        <button class="btn btn-sm btn-primary"
                                                            onclick="assignLecteur(<?php echo $student['idstage']; ?>)"
                                                            title="Attribuer un lecteur">
                                                            <i class="bi bi-person-plus"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($hasReport && !empty($student['lecteur_nom'])): ?>
                                                    <?php if ($student['cote_lecteur']): ?>
                                                        <span class="badge bg-success"><?php echo $student['cote_lecteur']; ?>/20</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">En attente</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $dateDebut; ?></td>
                                            <td><?php echo $dateFin; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewStageDetails(<?php echo $student['idstage']; ?>)" title="Voir détails">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if ($hasReport): ?>
                                                    <a href="<?php echo $student['rapport_path']; ?>"
                                                        class="btn btn-sm btn-success"
                                                        target="_blank"
                                                        title="Télécharger le rapport">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($hasFullAccess): ?>
                                                    <button class="btn btn-sm btn-warning" onclick="editStage(<?php echo $student['idstage']; ?>)" title="Modifier">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($allStudentsWithStages)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center text-muted">
                                                Aucun étudiant affecté à un stage pour le moment.
                                                <?php if ($hasFullAccess): ?>
                                                    <br><a href="?view=stage/assign" class="btn btn-sm btn-primary mt-2">
                                                        <i class="bi bi-plus-circle"></i> Affecter des étudiants
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div><!-- End table-responsive -->
                    </div><!-- End card-body -->
                </div><!-- End card -->
            </div><!-- End col-lg-12 -->
        </div>
    </section>

</main><!-- End #main -->

<!-- Modal for stage details -->
<div class="modal fade" id="stageDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails du Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="stageDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Modal for assigning lecteur -->
<div class="modal fade" id="assignLecteurModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus me-2"></i>Attribuer un Lecteur
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignLecteurForm">
                <div class="modal-body">
                    <input type="hidden" id="stage_id_lecteur" name="stage_id">

                    <div class="mb-3">
                        <label for="lecteur_id" class="form-label required">
                            <i class="bi bi-person-badge me-1"></i>Enseignant Lecteur
                        </label>
                        <select class="form-select" id="lecteur_id" name="lecteur_id" required>
                            <option value="">Sélectionner un enseignant...</option>
                            <?php
                            // Récupérer la liste des enseignants
                            try {
                                $agentModel = new Agent();
                                $enseignants = $agentModel->getAgentsByType('Enseignant');
                                foreach ($enseignants as $ens):
                            ?>
                                    <option value="<?= $ens['idAgent'] ?>">
                                        <?= htmlspecialchars(($ens['gradeDesignation'] ? $ens['gradeDesignation'] . ' ' : '') . $ens['noms']) ?>
                                    </option>
                            <?php
                                endforeach;
                            } catch (Exception $e) {
                                error_log("Erreur récupération enseignants: " . $e->getMessage());
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un lecteur.</div>
                    </div>

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Le lecteur aura accès au rapport de stage et pourra attribuer une cote sur 20.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Attribuer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function viewStageDetails(idStage) {
        // Afficher un loader dans le modal
        const modalContent = document.getElementById('stageDetailsContent');
        modalContent.innerHTML = `
        <div class="text-center p-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des détails...</p>
        </div>
    `;

        // Ouvrir le modal
        const modal = new bootstrap.Modal(document.getElementById('stageDetailsModal'));
        modal.show();

        // Charger les détails via AJAX
        fetch('controller/get_stage_details.php?id=' + idStage)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stage = data.stage;
                    modalContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-person me-2"></i>Étudiant</h6>
                            <p><strong>${stage.matricule || '-'}</strong><br>${stage.nom_etudiant || '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-building me-2"></i>Lieu de Stage</h6>
                            <p>${stage.lieu_stage || '-'}</p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-person-badge me-2"></i>Encadreur</h6>
                            <p>${stage.encadreur_nom || 'Non attribué'}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="bi bi-calendar-range me-2"></i>Période</h6>
                            <p>
                                Du: ${stage.date_debut ? new Date(stage.date_debut).toLocaleDateString('fr-FR') : '-'}<br>
                                Au: ${stage.date_fin ? new Date(stage.date_fin).toLocaleDateString('fr-FR') : '-'}
                            </p>
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-file-earmark-text me-2"></i>Rapport</h6>
                            <p>${stage.rapport_path ? '<span class="badge bg-success">Déposé</span>' : '<span class="badge bg-warning">En attente</span>'}</p>
                            ${stage.rapport_path ? `<a href="${stage.rapport_path}" class="btn btn-sm btn-primary" target="_blank"><i class="bi bi-download me-1"></i>Télécharger</a>` : ''}
                            
                            <h6 class="text-primary mt-3"><i class="bi bi-person-check me-2"></i>Lecteur</h6>
                            <p>${stage.lecteur_nom || 'Non attribué'}</p>
                            
                            ${stage.cote_lecteur ? `
                            <h6 class="text-primary mt-3"><i class="bi bi-star me-2"></i>Cote</h6>
                            <p><span class="badge bg-success fs-5">${stage.cote_lecteur}/20</span></p>
                            ` : ''}
                        </div>
                    </div>
                `;
                } else {
                    modalContent.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${data.message || 'Erreur lors du chargement des détails'}
                    </div>
                `;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                modalContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur de connexion au serveur
                </div>
            `;
            });
    }

    // Fonction pour attribuer un lecteur
    function assignLecteur(stageId) {
        document.getElementById('stage_id_lecteur').value = stageId;
        const modal = new bootstrap.Modal(document.getElementById('assignLecteurModal'));
        modal.show();
    }

    // Gestion du formulaire d'attribution de lecteur
    document.addEventListener('DOMContentLoaded', function() {
        const assignLecteurForm = document.getElementById('assignLecteurForm');

        if (assignLecteurForm) {
            assignLecteurForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData(this);

                Swal.fire({
                    title: 'Traitement...',
                    text: 'Attribution du lecteur en cours',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('controller/assign_lecteur_stage.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Lecteur attribué!',
                                text: data.message || 'Le lecteur a été attribué avec succès.',
                                confirmButtonColor: '#4CAF50'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Erreur',
                                text: data.message || 'Une erreur est survenue lors de l\'attribution.',
                                confirmButtonColor: '#d33'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur de connexion',
                            text: 'Impossible de communiquer avec le serveur.',
                            confirmButtonColor: '#d33'
                        });
                    });
            });
        }
    });

    function editStage(idStage) {
        // Rediriger vers la page de modification
        window.location.href = 'stage/edit&id=' + idStage;
    }

    function updateYearFilter(value) {
        let url = new URL(window.location);
        url.searchParams.set('annee_acad', value);
        url.searchParams.delete('promotion'); // Reset promotion when year changes
        window.location.href = url.toString();
    }

    function updatePromotionFilter(value) {
        let url = new URL(window.location);
        if (value) {
            url.searchParams.set('promotion', value);
        } else {
            url.searchParams.delete('promotion');
        }
        window.location.href = url.toString();
    }

    // Filtrage du tableau (seulement recherche)
    document.addEventListener('DOMContentLoaded', function() {
        const searchFilter = document.getElementById('searchFilter');
        const clearFilters = document.getElementById('clearFilters');
        const table = document.getElementById('stagesTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        function filterTable() {
            const searchValue = searchFilter.value.toLowerCase();

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const studentName = row.cells[1].textContent.toLowerCase(); // Colonne Étudiant

                let showRow = true;

                if (searchValue && !studentName.includes(searchValue)) {
                    showRow = false;
                }

                row.style.display = showRow ? '' : 'none';
            }
        }

        searchFilter.addEventListener('input', filterTable);

        clearFilters.addEventListener('click', function() {
            searchFilter.value = '';
            filterTable();
            updatePromotionFilter('');
        });
    });
</script>

<?php include "./views/include/footer_file.php"; ?>