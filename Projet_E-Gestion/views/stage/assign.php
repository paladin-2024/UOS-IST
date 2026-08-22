<?php
include "./views/include/header.php";

if (!isset($_SESSION['id'])) {
    echo "<script>window.location.href='?view=login';</script>";
    exit;
}

$stage = new Stage();

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

// Selected promotion
$promotionId = isset($_GET['promotion']) ? intval($_GET['promotion']) : null;
error_log("DEBUG assign.php - Promotion ID from GET: " . var_export($promotionId, true));
error_log("DEBUG assign.php - Full GET params: " . print_r($_GET, true));

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

// Get promotion details if selected
$promotion = null;
$requiredFees = [];
$allStudents = [];
$eligibleStudentIds = [];
$supervisors = [];

if ($promotionId) {
    try {
        $promotion = $stage->getPromotion($promotionId);

        $requiredFees = $stage->getRequiredFeesForPromotion($promotionId, $selectedYearId);

        // Get all students in the promotion - first try without year filter to see all students
        $db = Connexion::getInstance()->getPDO();

        // First, let's check how many students exist in this promotion regardless of year
        $sqlCheck = "SELECT COUNT(*) as total FROM etudiant WHERE promotion_idpromotion = ? AND est_actif=1";
        $stmtCheck = $db->prepare($sqlCheck);
        $stmtCheck->execute([$promotionId]);
        $totalInPromotion = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
        error_log("DEBUG: Total students in promotion $promotionId (all years): " . $totalInPromotion);

        // Now get students - excluding those already assigned to a stage
        $sql = "SELECT e.idetudiant, e.noms, e.matricule, e.annee_acad_idannee_acad
FROM etudiant e
LEFT JOIN stage_assignments sa ON e.idetudiant = sa.idetudiant
WHERE e.promotion_idpromotion = ?
AND e.annee_acad_idannee_acad = ?
AND e.est_actif=1
AND sa.idstage IS NULL
        ORDER BY e.noms";
        $stmt = $db->prepare($sql);
        $stmt->execute([$promotionId, $selectedYearId]);
        $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("DEBUG: Found " . count($allStudents) . " unassigned students for promotion $promotionId in year $selectedYearId");

        // If no students found with year filter, get all students from the promotion (still excluding assigned ones)
        if (empty($allStudents) && $totalInPromotion > 0) {
            error_log("DEBUG: No students found with year filter, getting all unassigned students from promotion");
            $sql = "SELECT e.idetudiant, e.noms, e.matricule, e.annee_acad_idannee_acad
    FROM etudiant e
    LEFT JOIN stage_assignments sa ON e.idetudiant = sa.idetudiant
    WHERE e.promotion_idpromotion = ?
    AND e.est_actif=1
    AND sa.idstage IS NULL
            ORDER BY e.noms";
            $stmt = $db->prepare($sql);
            $stmt->execute([$promotionId]);
            $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("DEBUG: Found " . count($allStudents) . " unassigned students without year filter");
        }

        // Get count of already assigned students for information
        $sqlAssigned = "SELECT COUNT(DISTINCT e.idetudiant) as assigned_count
FROM etudiant e
INNER JOIN stage_assignments sa ON e.idetudiant = sa.idetudiant
WHERE e.promotion_idpromotion = ?
                AND e.est_actif=1";
        $stmtAssigned = $db->prepare($sqlAssigned);
        $stmtAssigned->execute([$promotionId]);
        $assignedCount = $stmtAssigned->fetch(PDO::FETCH_ASSOC)['assigned_count'];
        error_log("DEBUG: $assignedCount students already assigned to stage in this promotion");

        // Get eligible students (those who paid required fees)
        $eligibleStudents = $stage->getEligibleStudentsForStage($promotionId, $requiredFees);
        $eligibleStudentIds = array_column($eligibleStudents, 'idetudiant');

        $supervisors = $stage->getAvailableSupervisors();
    } catch (Exception $e) {
        $allStudents = [];
        $eligibleStudentIds = [];
        $supervisors = [];
    }
}

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>AFFECTATION DES ÉTUDIANTS EN STAGE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="stage">Stages</a></li>
                <li class="breadcrumb-item active">Affectation<?php echo $promotion ? ' - ' . htmlspecialchars($promotion['designationPromotion']) : ''; ?></li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <!-- Display messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Sélection de promotion -->
            <div class="col-lg-12 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Sélectionner une Promotion</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="yearSelect">Année Académique:</label>
                                <select id="yearSelect" class="form-select" onchange="updateYear(this.value)">
                                    <?php foreach ($stage->getAcademicYears() as $year): ?>
                                        <option value="<?php echo $year['idannee_acad']; ?>" <?php echo ($selectedYearId == $year['idannee_acad']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year['designation']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="promotionSelect">Promotion:</label>
                                <select id="promotionSelect" class="form-select" onchange="updatePromotion(this.value)">
                                    <option value="">Choisir une promotion...</option>
                                    <?php foreach ($promotions as $promo): ?>
                                        <option value="<?php echo $promo['idpromotion']; ?>" <?php echo ($promotionId == $promo['idpromotion']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($promo['designationPromotion']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            echo "<!-- DEBUG: promotionId = $promotionId, promotion = " . ($promotion ? 'exists' : 'null') . ", allStudents count = " . count($allStudents) . " -->";
            ?>
            <?php if ($promotionId && $promotion): ?>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Affectation des Étudiants - <?php echo htmlspecialchars($promotion['designationPromotion']); ?></h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $eligibleCount = count($eligibleStudentIds);
                            $totalCount = count($allStudents);
                            ?>

                            <?php if (!empty($requiredFees)): ?>
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Frais requis pour le stage :</strong>
                                    <ul class="mb-0 mt-2">
                                        <?php foreach ($requiredFees as $fee): ?>
                                            <li><?php echo htmlspecialchars($fee['designation'] ?? 'Frais de stage'); ?> -
                                                <?php echo htmlspecialchars($fee['montant'] ?? '0'); ?>
                                                <?php echo htmlspecialchars($fee['devise'] ?? 'USD'); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <div class="alert alert-primary mb-3">
                                <i class="bi bi-people"></i>
                                <strong>Statistiques :</strong>
                                <?php echo $eligibleCount; ?> étudiant(s) éligible(s) sur <?php echo $totalCount; ?> non affectés
                                (<?php echo $totalCount > 0 ? round(($eligibleCount / $totalCount) * 100, 1) : 0; ?>%)
                                <?php if (isset($assignedCount) && $assignedCount > 0): ?>
                                    <br><small class="text-muted">
                                        <i class="bi bi-info-circle"></i>
                                        <?php echo $assignedCount; ?> étudiant(s) déjà affecté(s) dans cette promotion
                                    </small>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($allStudents)): ?>
                                <!-- Bulk assignment form -->
                                <form id="bulkAssignForm" method="POST" action="controller/assign_students_to_stage.php" style="display: none;">
                                    <input type="hidden" name="promotion_id" value="<?php echo $promotionId; ?>">
                                    <input type="hidden" name="bulk_assign" value="1">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="bulk_supervisor" class="form-label">Encadreur de Stage</label>
                                            <select name="supervisor_id" id="bulk_supervisor" class="form-select" required>
                                                <option value="">Sélectionner un encadreur</option>
                                                <?php foreach ($supervisors as $supervisor): ?>
                                                    <option value="<?php echo $supervisor['idAgent']; ?>">
                                                        <?php echo htmlspecialchars($supervisor['nom_complet'] ?? 'Encadreur ' . $supervisor['idAgent']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="bulk_stage_location" class="form-label">Lieu de Stage</label>
                                            <input type="text" name="stage_location" id="bulk_stage_location" class="form-control" placeholder="Ex: Entreprise XYZ" required>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="bulk_date_debut" class="form-label">Date de Début</label>
                                            <input type="date" name="date_debut" id="bulk_date_debut" class="form-control" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="bulk_date_fin" class="form-label">Date de Fin Prévue</label>
                                            <input type="date" name="date_fin" id="bulk_date_fin" class="form-control" required>
                                        </div>
                                    </div>
                                    <input type="hidden" name="students" id="bulk_students" value="">
                                    <button type="submit" name="bulkAssignBtn" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Affecter les Étudiants Sélectionnés
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="cancelBulkAssign()">
                                        <i class="bi bi-x-circle"></i> Annuler
                                    </button>
                                </form>

                                <!-- Bulk assignment button (shown when students selected) -->
                                <div id="bulkAssignBtn" style="display: none;" class="mb-3">
                                    <button type="button" class="btn btn-success" onclick="showBulkAssignForm()">
                                        <i class="bi bi-people"></i> Affecter les Étudiants Sélectionnés
                                    </button>
                                </div>

                                <!-- Students table -->
                                <div class="table-responsive">
                                    <table class="table table-striped" id="studentsTable">
                                        <thead>
                                            <tr>
                                                <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
                                                <th>Matricule</th>
                                                <th>Nom Complet</th>
                                                <th>Statut Paiement</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allStudents as $student):
                                                $isEligible = in_array($student['idetudiant'], $eligibleStudentIds);
                                                // Activer la checkbox seulement si:
                                                // 1. Pas de frais requis OU
                                                // 2. Il y a des frais requis ET l'étudiant est éligible
                                                $canSelect = empty($requiredFees) || (!empty($requiredFees) && $isEligible);
                                            ?>
                                                <tr class="<?php echo !$canSelect ? 'table-light' : ''; ?>">
                                                    <td>
                                                        <input type="checkbox"
                                                            class="student-checkbox"
                                                            value="<?php echo $student['idetudiant']; ?>"
                                                            onclick="updateBulkButton()"
                                                            <?php echo !$canSelect ? 'disabled' : ''; ?>
                                                            data-eligible="<?php echo $canSelect ? '1' : '0'; ?>">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($student['matricule']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['noms']); ?></td>
                                                    <td>
                                                        <?php if ($isEligible): ?>
                                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Éligible</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Frais non payés</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        // Activer le bouton seulement si:
                                                        // 1. Pas de frais requis OU
                                                        // 2. Il y a des frais requis ET l'étudiant est éligible
                                                        $canAssign = empty($requiredFees) || (!empty($requiredFees) && $isEligible);
                                                        ?>
                                                        <?php if ($canAssign): ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary"
                                                                onclick="openAssignModal(<?php echo $student['idetudiant']; ?>, '<?php echo htmlspecialchars(addslashes($student['noms'])); ?>')">
                                                                <i class="bi bi-person-plus"></i> Affecter
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button"
                                                                class="btn btn-sm btn-secondary" disabled
                                                                title="L'étudiant doit d'abord payer les frais de stage">
                                                                <i class="bi bi-lock"></i> Non éligible
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <?php if (isset($assignedCount) && $assignedCount > 0 && $totalCount == 0): ?>
                                        Tous les étudiants de cette promotion sont déjà affectés à un stage.
                                        <br><small>Total des étudiants affectés : <?php echo $assignedCount; ?></small>
                                    <?php else: ?>
                                        Aucun étudiant disponible pour affectation dans cette promotion.
                                    <?php endif; ?>
                                    <br><a href="?view=stage" class="btn btn-sm btn-primary mt-2">
                                        <i class="bi bi-list"></i> Voir les affectations existantes
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="mt-3">
                                <a href="?view=stage" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Individual Assignment Modal -->
        <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignModalLabel">Affecter Étudiant en Stage</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="individualAssignForm" method="POST" action="controller/assign_students_to_stage.php">
                        <div class="modal-body">
                            <input type="hidden" name="student_id" id="modal_student_id">
                            <input type="hidden" name="promotion_id" value="<?php echo $promotionId; ?>">
                            <input type="hidden" name="individual_assign" value="1">

                            <div class="mb-3">
                                <label for="modal_student_name" class="form-label">Étudiant</label>
                                <input type="text" class="form-control" id="modal_student_name" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="modal_supervisor" class="form-label">Encadreur de Stage</label>
                                <select name="supervisor_id" id="modal_supervisor" class="form-select" required>
                                    <option value="">Sélectionner un encadreur</option>
                                    <?php foreach ($supervisors as $supervisor): ?>
                                        <option value="<?php echo $supervisor['idAgent']; ?>">
                                            <?php echo htmlspecialchars($supervisor['nom_complet'] ?? 'Encadreur ' . $supervisor['idAgent']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="modal_stage_location" class="form-label">Lieu de Stage</label>
                                <input type="text" name="stage_location" id="modal_stage_location" class="form-control" placeholder="Ex: Entreprise XYZ" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="modal_date_debut" class="form-label">Date de Début</label>
                                        <input type="date" name="date_debut" id="modal_date_debut" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="modal_date_fin" class="form-label">Date de Fin Prévue</label>
                                        <input type="date" name="date_fin" id="modal_date_fin" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Affecter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    function updateYear(value) {
        let url = new URL(window.location);
        url.searchParams.set('annee_acad', value);
        url.searchParams.delete('promotion'); // Reset promotion when year changes
        window.location.href = url.toString();
    }

    function updatePromotion(value) {
        let url = new URL(window.location);
        if (value) {
            url.searchParams.set('promotion', value);
        } else {
            url.searchParams.delete('promotion');
        }
        window.location.href = url.toString();
    }

    function openAssignModal(studentId, studentName) {
        // Reset form first to clear previous values
        document.getElementById('individualAssignForm').reset();

        // Then set the student information
        document.getElementById('modal_student_id').value = studentId;
        document.getElementById('modal_student_name').value = studentName;

        // Keep the promotion_id hidden field value
        document.querySelector('input[name="promotion_id"]').value = '<?php echo $promotionId; ?>';

        // Show modal
        var modal = new bootstrap.Modal(document.getElementById('assignModal'));
        modal.show();
    }

    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        // Ne sélectionner que les checkboxes des étudiants éligibles (non désactivées)
        const checkboxes = document.querySelectorAll('.student-checkbox:not(:disabled)');
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updateBulkButton();
    }

    function updateBulkButton() {
        const checkboxes = document.querySelectorAll('.student-checkbox:checked');
        const bulkBtn = document.getElementById('bulkAssignBtn');
        if (checkboxes.length > 0) {
            bulkBtn.style.display = 'block';
        } else {
            bulkBtn.style.display = 'none';
        }
    }

    function showBulkAssignForm() {
        const checkboxes = document.querySelectorAll('.student-checkbox:checked');
        const studentIds = Array.from(checkboxes).map(cb => cb.value);

        if (studentIds.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Aucun étudiant sélectionné',
                text: 'Veuillez sélectionner au moins un étudiant éligible.'
            });
            return;
        }

        document.getElementById('bulk_students').value = studentIds.join(',');
        document.getElementById('bulkAssignForm').style.display = 'block';
        document.getElementById('bulkAssignBtn').style.display = 'none';
        // Hide table
        document.getElementById('studentsTable').style.display = 'none';
    }

    function cancelBulkAssign() {
        document.getElementById('bulkAssignForm').style.display = 'none';
        document.getElementById('bulkAssignBtn').style.display = 'none';
        document.getElementById('studentsTable').style.display = 'table';
        // Uncheck all
        document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
    }
</script>

<?php include "./views/include/footer.php"; ?>