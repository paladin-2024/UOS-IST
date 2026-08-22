<?php
include "./views/include/header.php";

$stage = new Stage();
$userId = $_SESSION['id'];

// Get teacher info
$teacher = $stage->getTeacherByUserId($userId);
$teacherId = $teacher ? $teacher['idenseignant'] : null;

if (!$teacherId) {
    echo "<script>alert('Enseignant non trouvé'); window.location.href='?';</script>";
    exit;
}

// Get current academic year
$activeYear = $stage->getActiveAcademicYear();
$activeYearId = $activeYear ? $activeYear['idannee_acad'] : null;

// Get teacher's stage-related data
$supervisedStages = $stage->getStagesForSupervisor($teacherId);
$reportsToRead = $stage->getReportsForReader($teacherId);

// Get teacher's section responsibilities for context
$userResponsibilities = $stage->getUserResponsibilities($userId);
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>TABLEAU DE BORD ENSEIGNANT - STAGES</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="#">Accueil</a></li>
                <li class="breadcrumb-item active">Stages - Mon Espace</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <!-- Active Year Info -->
            <div class="col-lg-12">
                <div class="alert alert-info d-flex align-items-center">
                    <i class="bi bi-calendar-check-fill me-2"></i>
                    <div>
                        <strong>Année académique active :</strong>
                        <?php echo $activeYear ? htmlspecialchars($activeYear['designation']) : 'Aucune année active'; ?>
                    </div>
                </div>
            </div>

            <!-- Responsibilities Summary -->
            <?php if (!empty($userResponsibilities)): ?>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Mes Responsabilités</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($userResponsibilities as $resp): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6><?php echo htmlspecialchars($resp['promotionDesignation']); ?></h6>
                                        <p class="mb-1">Section: <?php echo htmlspecialchars($resp['designationSection']); ?></p>
                                        <span class="badge bg-primary">Responsable</span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Supervised Stages -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-people-fill me-2"></i>
                            Stages Supervisés
                            <span class="badge bg-primary ms-2"><?php echo count($supervisedStages); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($supervisedStages)): ?>
                            <p class="text-muted">Aucun stage supervisé pour le moment.</p>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($supervisedStages as $stage_info): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                    <div>
                                        <strong><?php echo htmlspecialchars($stage_info['nom'] . ' ' . $stage_info['postnom']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($stage_info['promotion']); ?> |
                                            Lieu: <?php echo htmlspecialchars($stage_info['lieu_stage'] ?? 'Non défini'); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($stage_info['lecteur_nom']): ?>
                                            <small class="text-success">Lecteur: <?php echo htmlspecialchars($stage_info['lecteur_nom']); ?></small><br>
                                        <?php endif; ?>
                                        <a href="?view=stage/supervisor" class="btn btn-sm btn-outline-primary">Gérer</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Reports to Read -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-file-earmark-text me-2"></i>
                            Rapports à Évaluer
                            <span class="badge bg-warning ms-2"><?php echo count($reportsToRead); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reportsToRead)): ?>
                            <p class="text-muted">Aucun rapport à évaluer pour le moment.</p>
                        <?php else: ?>
                            <div style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($reportsToRead as $report): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3 p-2 border rounded">
                                    <div>
                                        <strong><?php echo htmlspecialchars($report['nom'] . ' ' . $report['postnom']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($report['promotion']); ?>
                                            <?php if ($report['cote_lecteur']): ?>
                                                | Cote actuelle: <?php echo htmlspecialchars($report['cote_lecteur']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <a href="?view=stage/reader" class="btn btn-sm btn-outline-success">Évaluer</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Actions Rapides</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="?view=stage/supervisor" class="btn btn-primary w-100">
                                    <i class="bi bi-people-fill me-2"></i>
                                    Gérer Supervisions
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="?view=stage/reader" class="btn btn-success w-100">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    Évaluer Rapports
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="?view=stage" class="btn btn-info w-100">
                                    <i class="bi bi-eye me-2"></i>
                                    Vue d'Ensemble
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="?view=enseignant/mes_recours" class="btn btn-warning w-100">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    Mes Recours
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
