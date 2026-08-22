<?php
include "./views/include/header.php";

$stage = new Stage();
$userId = $_SESSION['idUser'];

// Get teacher id from user
$teacher = $stage->getTeacherByUserId($userId);
$teacherId = $teacher ? $teacher['idenseignant'] : null;

if (!$teacherId) {
    echo "<script>alert('Enseignant non trouvé'); window.location.href='?';</script>";
    exit;
}

// Get supervised stages
$stages = [];
try {
    $stages = $stage->getStagesForSupervisor($teacherId);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des stages supervisés: " . $e->getMessage());
    $stages = [];
}

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES STAGES SUPERVISÉS</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Encadreur - Stages</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Stages Supervisés</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Promotion</th>
                                    <th>Lieu</th>
                                    <th>Lecteur</th>
                                    <th>Cote Lecteur</th>
                                    <th>Cote Entreprise</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stages as $stg): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stg['nom'] . ' ' . $stg['postnom']); ?></td>
                                    <td><?php echo htmlspecialchars($stg['promotion']); ?></td>
                                    <td><?php echo htmlspecialchars($stg['lieu_stage'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($stg['lecteur_nom'] ?? '-'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($stg['cote_lecteur'] ?? '-'); ?>
                                        <?php if ($stg['cote_lecteur']): ?>
                                        <button class="btn btn-sm btn-warning ms-2" onclick="modifyReaderGrade(<?php echo $stg['idstage']; ?>, '<?php echo $stg['cote_lecteur']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($stg['est_terminale']): ?>
                                        <?php echo htmlspecialchars($stg['cote_entreprise'] ?? '-'); ?>
                                        <button class="btn btn-sm btn-success ms-2" onclick="enterCompanyGrade(<?php echo $stg['idstage']; ?>, '<?php echo $stg['cote_entreprise'] ?? ''; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($stg['rapport_path']): ?>
                                        <a href="?view=download&file=<?php echo urlencode($stg['rapport_path']); ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-file-pdf"></i> Rapport
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Modal for modifying reader grade -->
<div class="modal fade" id="modifyGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier Cote Lecteur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/modify_reader_grade.php">
                    <input type="hidden" name="stage_id" id="modifyStageId">
                    <div class="mb-3">
                        <label for="reader_grade" class="form-label">Cote Lecteur</label>
                        <input type="number" name="reader_grade" id="reader_grade" class="form-control" min="0" max="20" step="0.01" required>
                    </div>
                    <button type="submit" name="modifyGradeBtn" class="btn btn-primary">Modifier</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for entering company grade -->
<div class="modal fade" id="companyGradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cote Entreprise</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/enter_company_grade.php">
                    <input type="hidden" name="stage_id" id="companyStageId">
                    <div class="mb-3">
                        <label for="company_grade" class="form-label">Cote Entreprise</label>
                        <input type="number" name="company_grade" id="company_grade" class="form-control" min="0" max="20" step="0.01" required>
                    </div>
                    <button type="submit" name="companyGradeBtn" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function modifyReaderGrade(stageId, currentGrade) {
    document.getElementById('modifyStageId').value = stageId;
    document.getElementById('reader_grade').value = currentGrade;
    new bootstrap.Modal(document.getElementById('modifyGradeModal')).show();
}

function enterCompanyGrade(stageId, currentGrade) {
    document.getElementById('companyStageId').value = stageId;
    document.getElementById('company_grade').value = currentGrade;
    new bootstrap.Modal(document.getElementById('companyGradeModal')).show();
}
</script>

<?php include "./views/include/footer.php"; ?>
