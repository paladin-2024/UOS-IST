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

// Get assigned reports
$reports = [];
try {
    $reports = $stage->getReportsForReader($teacherId);
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des rapports pour lecteur: " . $e->getMessage());
    $reports = [];
}

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>ÉVALUATION DES RAPPORTS DE STAGE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Lecteur - Rapports</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Rapports Assignés</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Promotion</th>
                                    <th>Rapport</th>
                                    <th>Cote Actuelle</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($report['nom'] . ' ' . $report['postnom']); ?></td>
                                    <td><?php echo htmlspecialchars($report['promotion']); ?></td>
                                    <td>
                                        <a href="?view=download&file=<?php echo urlencode($report['rapport_path']); ?>" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Voir Rapport
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($report['cote_lecteur'] ?? 'Non noté'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="gradeReport(<?php echo $report['idstage']; ?>, '<?php echo addslashes($report['cote_lecteur'] ?? ''); ?>')">
                                            <i class="fas fa-edit"></i> Noter
                                        </button>
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

<!-- Modal for grading -->
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Attribuer une Cote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/grade_report.php">
                    <input type="hidden" name="stage_id" id="gradeStageId">
                    <div class="mb-3">
                        <label for="grade" class="form-label">Cote</label>
                        <input type="number" name="grade" id="grade" class="form-control" min="0" max="20" step="0.01" required>
                    </div>
                    <button type="submit" name="gradeBtn" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function gradeReport(stageId, currentGrade) {
    document.getElementById('gradeStageId').value = stageId;
    document.getElementById('grade').value = currentGrade;
    new bootstrap.Modal(document.getElementById('gradeModal')).show();
}
</script>

<?php include "./views/include/footer.php"; ?>
