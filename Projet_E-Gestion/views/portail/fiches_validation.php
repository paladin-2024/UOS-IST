<?php
require_once "head_student.php";

$pageTitle = 'Fiches de Validation';
$currentPage = 'fiches_validation';

$studentId = $_SESSION['student_id'] ?? 0;
$studentMatricule = $_SESSION['student_matricule'] ?? '';
$promotionId = $_SESSION['promotion_id'] ?? 0;
$currentYear = isset($_SESSION['annee_acad']) ? $universite->getAnneeAcademiqueById($_SESSION['annee_acad']) : null;

$connexion = Connexion::getInstance()->getPDO();

$deliberationPubliee = false;
$deliberationsDisponibles = [];

try {
    $stmt_pub = $connexion->prepare("
        SELECT DISTINCT 
            d.id AS deliberation_id,
            aa.designation AS annee_academique,
            aa.idannee_acad,
            sess.description AS session_nom,
            sess.idsession,
            b.designation AS bureau_nom
        FROM deliberation d
        INNER JOIN bureau_jury b ON d.bureau_jury_id = b.idbureau
        INNER JOIN session sess ON d.session_idsession = sess.idsession
        INNER JOIN annee_acad aa ON d.annee_acad_id = aa.idannee_acad
        WHERE b.promotion_idpromotion = :promotion_id
        AND d.statut = 'publiée'
        ORDER BY aa.designation DESC, sess.description DESC
    ");
    $stmt_pub->bindParam(':promotion_id', $promotionId, PDO::PARAM_INT);
    $stmt_pub->execute();
    $deliberationsDisponibles = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);
    
    $deliberationPubliee = !empty($deliberationsDisponibles);
} catch (Exception $e) {
    error_log("Erreur délibérations: " . $e->getMessage());
}

$resultatsValidation = [];
if ($deliberationPubliee && $studentMatricule && $currentYear) {
    try {
        $stmt_sem = $connexion->prepare("
            SELECT ms.*, s.\"numeroSemestre\", aa.designation AS annee_academique
            FROM moyenne_semestre ms
            INNER JOIN semestre s ON ms.idsemestre = s.idsemestre
            INNER JOIN annee_acad aa ON ms.annee_acad_idannee_acad = aa.idannee_acad
            WHERE ms.matricule = :matricule
            AND ms.annee_acad_idannee_acad = :annee_id
            ORDER BY s.idsemestre
        ");
        $stmt_sem->bindParam(':matricule', $studentMatricule);
        $stmt_sem->bindParam(':annee_id', $currentYear['idannee_acad'], PDO::PARAM_INT);
        $stmt_sem->execute();
        $moyennesSemestrielles = $stmt_sem->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt_ann = $connexion->prepare("
            SELECT ma.*, aa.designation AS annee_academique
            FROM moyenne_annuelle ma
            INNER JOIN annee_acad aa ON ma.annee_acad_idannee_acad = aa.idannee_acad
            WHERE ma.matricule = :matricule
            AND ma.annee_acad_idannee_acad = :annee_id
            ORDER BY aa.designation DESC
        ");
        $stmt_ann->bindParam(':matricule', $studentMatricule);
        $stmt_ann->bindParam(':annee_id', $currentYear['idannee_acad'], PDO::PARAM_INT);
        $stmt_ann->execute();
        $moyennesAnnuelles = $stmt_ann->fetchAll(PDO::FETCH_ASSOC);
        
        $resultatsValidation = [
            'semestrielles' => $moyennesSemestrielles,
            'annuelles' => $moyennesAnnuelles
        ];
    } catch (Exception $e) {
        error_log("Erreur résultats: " . $e->getMessage());
    }
}

$grillesAnciennes = [];
try {
    $stmt_anciennes = $connexion->prepare("
        SELECT DISTINCT gi.id AS import_id, gi.annee_academique, gi.session, gi.promotion, ge.id AS etudiant_id
        FROM grilles_anciennes_imports gi
        INNER JOIN grilles_anciennes_etudiants ge ON gi.id = ge.import_id
        WHERE ge.matricule = :matricule
        ORDER BY gi.annee_academique DESC
    ");
    $stmt_anciennes->bindParam(':matricule', $studentMatricule);
    $stmt_anciennes->execute();
    $grillesAnciennes = $stmt_anciennes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur grilles anciennes: " . $e->getMessage());
}

$ficheValidationPayee = $etudiantModel->hasStudentPaidFicheValidationFees($studentId);
$fraisStatus = $etudiantModel->getFicheValidationFeesStatus($studentId);
$infoPromotion = $universite->getPromotionById($promotionId);
?>

<?php include "includes/mobile_header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<div class="content-area">
    <div class="pagetitle">
        <h1><i class="fas fa-file-signature me-2"></i>Fiches de Validation</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="student">Accueil</a></li>
                <li class="breadcrumb-item active">Fiches de Validation</li>
            </ol>
        </nav>
    </div>

    <?php if (!$ficheValidationPayee && !empty($fraisStatus)): ?>
        <div class="alert alert-warning d-flex align-items-start">
            <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
            <div class="flex-grow-1">
                <strong><i class="fas fa-lock me-1"></i> Paiement requis pour accéder aux fiches</strong>
                <p class="mb-2 mt-2">Vous devez payer les frais suivants pour pouvoir télécharger vos fiches de validation:</p>
                <ul class="mb-3">
                    <?php foreach ($fraisStatus as $fee): ?>
                        <li>
                            <?= htmlspecialchars($fee['designation']) ?>
                            - <strong><?= number_format($fee['montant'], 2) ?> <?= htmlspecialchars($fee['devise'] ?? 'USD') ?></strong>
                            <?php if ($fee['statut_paiement'] === 'paye'): ?>
                                <span class="badge bg-success ms-2"><i class="fas fa-check-circle me-1"></i> Payé</span>
                            <?php elseif ($fee['statut_paiement'] === 'partiel'): ?>
                                <span class="badge bg-warning ms-2"><i class="fas fa-clock me-1"></i> Partiel (<?= number_format($fee['montantPaye'] ?? 0, 2) ?> / <?= number_format($fee['montant'], 2) ?>)</span>
                            <?php else: ?>
                                <span class="badge bg-danger ms-2"><i class="fas fa-times-circle me-1"></i> Non payé</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a href="frais_academiques" class="btn btn-warning">
                    <i class="fas fa-credit-card me-2"></i> Effectuer le paiement
                </a>
            </div>
        </div>
    <?php elseif (!$deliberationPubliee && empty($grillesAnciennes)): ?>
        <div class="alert alert-info d-flex align-items-center">
            <i class="fas fa-info-circle me-3 fs-4"></i>
            <div>
                <strong>Information</strong>
                <p class="mb-0">Aucun résultat de délibération n'est disponible pour le moment. Les fiches de validation seront accessibles une fois les résultats publiés.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($ficheValidationPayee && ($deliberationPubliee || !empty($grillesAnciennes))): ?>
        <div class="alert alert-success d-flex align-items-center mb-4">
            <i class="fas fa-check-circle me-3 fs-4"></i>
            <div>
                <strong><i class="fas fa-unlock me-1"></i> Accès autorisé</strong>
                <p class="mb-0">Vous avez accès à vos fiches de validation. Cliquez sur "Télécharger" pour obtenir le document PDF.</p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($deliberationPubliee && !empty($resultatsValidation['semestrielles']) && $ficheValidationPayee): ?>
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Résultats - <?= htmlspecialchars($currentYear['designation'] ?? '') ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($resultatsValidation['semestrielles'])): ?>
                    <h6 class="text-primary mb-3"><i class="fas fa-calendar-alt me-1"></i> Moyennes Semestrielles</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Semestre</th>
                                    <th>Moyenne</th>
                                    <th>Credits</th>
                                    <th>Statut</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultatsValidation['semestrielles'] as $sem): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($sem['numeroSemestre']) ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?= ($sem['moyenne_deliberee'] ?? 0) >= 10 ? 'success' : 'danger' ?>">
                                                <?= number_format($sem['moyenne_deliberee'] ?? 0, 2) ?>/20
                                            </span>
                                        </td>
                                        <td><?= $sem['credits_obtenus'] ?? 0 ?>/<?= $sem['credits_total'] ?? 0 ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($sem['est_valide'] ?? false) ? 'success' : 'danger' ?>">
                                                <?= ($sem['est_valide'] ?? false) ? 'Validée' : 'Non validée' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../controller/export_fiche_validation_student.php?import_id=0&semestre=<?= urlencode($sem['numeroSemestre']) ?>" 
                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-download"></i> Télécharger
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($resultatsValidation['annuelles'])): ?>
                    <h6 class="text-primary mb-3"><i class="fas fa-calendar-check me-1"></i> Moyennes Annuelles</h6>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Année</th>
                                    <th>Moyenne</th>
                                    <th>Credits</th>
                                    <th>Mention</th>
                                    <th>Statut</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resultatsValidation['annuelles'] as $ann): 
                                    $mention = $ann['mention'] ?? '';
                                    $mentionColor = match($mention) {
                                        'A', 'Excellent' => 'success',
                                        'B', 'Très bien' => 'primary',
                                        'C', 'Bien' => 'info',
                                        'D', 'Assez bien' => 'warning',
                                        'E', 'Passable' => 'secondary',
                                        default => 'dark'
                                    };
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($ann['annee_academique']) ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?= ($ann['moyenne_deliberee'] ?? 0) >= 10 ? 'success' : 'danger' ?>">
                                                <?= number_format($ann['moyenne_deliberee'] ?? 0, 2) ?>/20
                                            </span>
                                        </td>
                                        <td><?= $ann['credits_obtenus'] ?? 0 ?>/<?= $ann['credits_total'] ?? 0 ?></td>
                                        <td><span class="badge bg-<?= $mentionColor ?>"><?= htmlspecialchars($mention ?: '-') ?></span></td>
                                        <td>
                                            <span class="badge bg-<?= ($ann['est_admis'] ?? false) ? 'success' : 'danger' ?>">
                                                <?= ($ann['est_admis'] ?? false) ? 'Admis(e)' : 'Non Admis(e)' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../controller/export_fiche_validation_student.php?import_id=0&annee=<?= urlencode($ann['annee_academique']) ?>" 
                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-download"></i> Télécharger
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($resultatsValidation['annuelles'])): 
            $totalCreditsObtenus = 0;
            $totalCreditsGlobal = 0;
            foreach ($resultatsValidation['annuelles'] as $ann) {
                $totalCreditsObtenus += $ann['credits_obtenus'] ?? 0;
                $totalCreditsGlobal += $ann['credits_total'] ?? 0;
            }
            $pourcentageValidation = $totalCreditsGlobal > 0 ? ($totalCreditsObtenus / $totalCreditsGlobal) * 100 : 0;
        ?>
            <div class="card mb-4 border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Récapitulatif des Credits</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <i class="fas fa-trophy text-warning fs-2 mb-2"></i>
                            <h3><?= $totalCreditsObtenus ?></h3>
                            <small class="text-muted">Obtenus</small>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-layer-group text-primary fs-2 mb-2"></i>
                            <h3><?= $totalCreditsGlobal ?></h3>
                            <small class="text-muted">Totaux</small>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-percentage <?= $pourcentageValidation >= 50 ? 'text-success' : 'text-danger' ?> fs-2 mb-2"></i>
                            <h3><?= number_format($pourcentageValidation, 1) ?>%</h3>
                            <small class="text-muted">Validation</small>
                        </div>
                    </div>
                    <?php if ($totalCreditsGlobal > 0): ?>
                        <div class="progress mt-3" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: <?= $pourcentageValidation ?>%"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($grillesAnciennes) && $ficheValidationPayee): ?>
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Historique (Systèmes Antérieurs)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Année</th>
                                <th>Session</th>
                                <th>Promotion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grillesAnciennes as $grille): ?>
                                <tr>
                                    <td><?= htmlspecialchars($grille['annee_academique']) ?></td>
                                    <td><?= htmlspecialchars($grille['session']) ?></td>
                                    <td><?= htmlspecialchars($grille['promotion']) ?></td>
                                    <td>
                                        <?php if ($ficheValidationPayee): ?>
                                            <a href="../controller/export_fiche_validation_student.php?import_id=<?= $grille['import_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-download me-1"></i> Fiche
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled><i class="fas fa-lock"></i> Bloqué</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="text-center text-muted small mt-4">
        <p class="mb-0">
            <i class="fas fa-university me-1"></i>
            <?= htmlspecialchars($configUniversitee['nom'] ?? 'Université') ?>
            - <?= htmlspecialchars($infoPromotion['designationPromotion'] ?? '') ?>
        </p>
    </div>
</div>

<?php include "includes/bottom_nav.php"; ?>

<?php include __DIR__ . "/includes/main_scripts.php"; ?>

<?php require_once "footer_student.php"; ?>
