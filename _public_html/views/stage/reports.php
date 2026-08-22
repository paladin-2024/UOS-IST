<?php
include "./views/include/header.php";

$stage = new Stage();

// Get submitted reports without readers
$reports = [];
try {
    $reports = $stage->getSubmittedReports();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des rapports: " . $e->getMessage());
    $reports = [];
}

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>GESTION DES RAPPORTS DE STAGE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=stage">Stages</a></li>
                <li class="breadcrumb-item active">Rapports</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Rapports Soumis</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Étudiant</th>
                                    <th>Promotion</th>
                                    <th>Rapport</th>
                                    <th>Lecteur Assigné</th>
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
                                    <td><?php echo $report['idlecteur'] ? 'Assigné' : 'Non assigné'; ?></td>
                                    <td>
                                        <?php if (!$report['idlecteur']): ?>
                                        <button class="btn btn-sm btn-primary" onclick="assignReader(<?php echo $report['idstage']; ?>)">
                                            <i class="fas fa-user-plus"></i> Assigner Lecteur
                                        </button>
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

<!-- Modal for assigning reader -->
<div class="modal fade" id="assignReaderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assigner un Lecteur</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="controller/assign_reader.php">
                    <input type="hidden" name="stage_id" id="assignStageId">
                    <div class="mb-3">
                        <label for="reader_id" class="form-label">Lecteur</label>
                        <select name="reader_id" id="reader_id" class="form-select" required>
                            <option value="">Sélectionner un lecteur</option>
                            <?php
                            $readers = $stage->getAvailableSupervisors(); // Reuse supervisors as readers
                            foreach ($readers as $reader):
                            ?>
                            <option value="<?php echo $reader['idenseignant']; ?>">
                                <?php echo htmlspecialchars($reader['nom'] . ' ' . $reader['postnom']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="assignReaderBtn" class="btn btn-primary">Assigner</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function assignReader(stageId) {
    document.getElementById('assignStageId').value = stageId;
    new bootstrap.Modal(document.getElementById('assignReaderModal')).show();
}
</script>

<?php include "./views/include/footer.php"; ?>
