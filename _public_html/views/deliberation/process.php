<?php
include "./views/include/header.php";

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id'])) {
    echo "<script>window.location.href = 'login';</script>";
    exit();
}

// Vérifier les droits d'accès
$universite = new Universite();
$agent = new Agent();
$userId = $_SESSION['id'];
$agentId = $agent->getAgentIdByUserId($userId);
$isAdmin = isset($_SESSION['idRole']) && $_SESSION['idRole'] == 1;
$isJuryPresident = $universite->isJuryPresident($agentId);

if (!$isAdmin && !$isJuryPresident) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Accès refusé',
            text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    exit();
}

// Récupérer les paramètres
$deliberationId = isset($_GET['deliberation_id']) ? intval($_GET['deliberation_id']) : 0;
$bureauId = isset($_GET['bureau_id']) ? intval($_GET['bureau_id']) : 0;
$promotionId = isset($_GET['promotion_id']) ? intval($_GET['promotion_id']) : 0;
$sessionId = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$anneeId = isset($_GET['annee_id']) ? intval($_GET['annee_id']) : 0;

// Vérifier si tous les paramètres sont présents
if (!$deliberationId || !$bureauId || !$promotionId || !$sessionId || !$anneeId) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Paramètres manquants pour la délibération.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les informations de la délibération
$deliberationInfo = $universite->getDeliberationById($deliberationId);
if (!$deliberationInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Délibération introuvable.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les informations de la promotion
$promotionInfo = $universite->getPromotionById($promotionId);
if (!$promotionInfo) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Promotion introuvable.'
        }).then(() => {
            window.location.href = 'index.php?view=deliberation/seances';
        });
    </script>";
    exit();
}

// Récupérer les semestres de la promotion
$semestres = $universite->getSemestresByPromotion($promotionId);

// Récupérer la configuration de délibération
$configDeliberation = $universite->getDeliberationConfig($bureauId, $sessionId, $anneeId);
if (!$configDeliberation) {
    // Si aucune configuration n'existe, utiliser les valeurs par défaut
    $configDeliberation = [
        'compensation_intra_ue' => 0,
        'seuil_compensation_intra_ue' => 8.00,
        'compensation_inter_ue' => 0,
        'seuil_compensation_inter_ue' => 8.00,
        'exiger_meme_credit_ue' => 1,
        'compensation_inter_semestre' => 0,
        'seuil_compensation_inter_semestre' => 8.00,
        'limiter_compensation_annee' => 1,
        'note_passage' => 10.00,
        'pourcentage_passage_semestre' => 50.00,
        'calculer_moyenne_avec_notes_vides' => 0
    ];
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Processus de délibération</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index">Accueil</a></li>
                <li class="breadcrumb-item"><a href="index.php?view=deliberation/seances">Délibération</a></li>
                <li class="breadcrumb-item active">Processus</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-gear-fill me-1"></i>
                            Configuration de la délibération
                        </h5>
                        
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center mb-2">
                                <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                                <h5 class="mb-0">Informations sur la délibération</h5>
                            </div>
                            <p><strong>Promotion:</strong> <?= htmlspecialchars($promotionInfo['designationPromotion']) ?></p>
                            <p><strong>Session:</strong> <?= htmlspecialchars($_GET['session_id']) ?></p>
                            <p><strong>Année académique:</strong> <?= htmlspecialchars($deliberationInfo['annee_acad_id']) ?></p>
                            <p><strong>Date prévue:</strong> <?= date('d/m/Y H:i', strtotime($deliberationInfo['date_deliberation'])) ?></p>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Paramètres de délibération</h5>
                                        <ul class="list-group">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Compensation intra-UE
                                                <span class="badge bg-<?= $configDeliberation['compensation_intra_ue'] ? 'success' : 'danger' ?> rounded-pill">
                                                    <?= $configDeliberation['compensation_intra_ue'] ? 'Activée' : 'Désactivée' ?>
                                                </span>
                                            </li>
                                            <?php if ($configDeliberation['compensation_intra_ue']): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Seuil de compensation intra-UE
                                                <span class="badge bg-primary rounded-pill">
                                                    <?= number_format($configDeliberation['seuil_compensation_intra_ue'], 2) ?>/20
                                                </span>
                                            </li>
                                            <?php endif; ?>
                                            
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Compensation inter-UE
                                                <span class="badge bg-<?= $configDeliberation['compensation_inter_ue'] ? 'success' : 'danger' ?> rounded-pill">
                                                    <?= $configDeliberation['compensation_inter_ue'] ? 'Activée' : 'Désactivée' ?>
                                                </span>
                                            </li>
                                            
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Compensation inter-semestre
                                                <span class="badge bg-<?= $configDeliberation['compensation_inter_semestre'] ? 'success' : 'danger' ?> rounded-pill">
                                                    <?= $configDeliberation['compensation_inter_semestre'] ? 'Activée' : 'Désactivée' ?>
                                                </span>
                                            </li>
                                            
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Note de passage
                                                <span class="badge bg-primary rounded-pill">
                                                    <?= number_format($configDeliberation['note_passage'], 2) ?>/20
                                                </span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Choix du périmètre de délibération</h5>
                                        <form id="deliberationForm" action="controller/run_deliberation.php" method="POST">
                                            <input type="hidden" name="deliberation_id" value="<?= $deliberationId ?>">
                                            <input type="hidden" name="bureau_id" value="<?= $bureauId ?>">
                                            <input type="hidden" name="promotion_id" value="<?= $promotionId ?>">
                                            <input type="hidden" name="session_id" value="<?= $sessionId ?>">
                                            <input type="hidden" name="annee_id" value="<?= $anneeId ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Type de délibération</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="type_deliberation" id="type_semestre" value="semestre" checked>
                                                    <label class="form-check-label" for="type_semestre">
                                                        Délibération par semestre
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="type_deliberation" id="type_annuelle" value="annuelle">
                                                    <label class="form-check-label" for="type_annuelle">
                                                        Délibération annuelle
                                                    </label>
                                                </div>
                                            </div>
                                            <div id="semestre_selection" class="mb-3">
                                                <label for="semestre_id" class="form-label">Semestre à délibérer</label>
                                                <select class="form-select" id="semestre_id" name="semestre_id">
                                                    <?php foreach ($semestres as $semestre): ?>
                                                    <option value="<?= $semestre['idsemestre'] ?>">
                                                        <?= htmlspecialchars($semestre['numeroSemestre']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Étapes de délibération à exécuter</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="etapes[]" id="etape_intra_ue" value="intra_ue" checked <?= !$configDeliberation['compensation_intra_ue'] ? 'disabled' : '' ?>>
                                                    <label class="form-check-label" for="etape_intra_ue">
                                                        Compensation intra-UE
                                                        <?php if (!$configDeliberation['compensation_intra_ue']): ?>
                                                        <small class="text-muted">(désactivée dans la configuration)</small>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="etapes[]" id="etape_inter_ue" value="inter_ue" checked <?= !$configDeliberation['compensation_inter_ue'] ? 'disabled' : '' ?>>
                                                    <label class="form-check-label" for="etape_inter_ue">
                                                        Compensation inter-UE
                                                        <?php if (!$configDeliberation['compensation_inter_ue']): ?>
                                                        <small class="text-muted">(désactivée dans la configuration)</small>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="etapes[]" id="etape_inter_semestre" value="inter_semestre" checked <?= !$configDeliberation['compensation_inter_semestre'] ? 'disabled' : '' ?>>
                                                    <label class="form-check-label" for="etape_inter_semestre">
                                                        Compensation inter-semestre
                                                        <?php if (!$configDeliberation['compensation_inter_semestre']): ?>
                                                        <small class="text-muted">(désactivée dans la configuration)</small>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="etapes[]" id="etape_calcul_credits" value="calcul_credits" checked>
                                                    <label class="form-check-label" for="etape_calcul_credits">
                                                        Calcul des crédits
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="etapes[]" id="etape_decision_jury" value="decision_jury" checked>
                                                    <label class="form-check-label" for="etape_decision_jury">
                                                        Décisions du jury
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid gap-2 mt-4">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-play-fill me-1"></i> Lancer la délibération
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de l'affichage du sélecteur de semestre
    const typeDeliberationRadios = document.querySelectorAll('input[name="type_deliberation"]');
    const semestreSelection = document.getElementById('semestre_selection');
    
    typeDeliberationRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'semestre') {
                semestreSelection.style.display = 'block';
            } else {
                semestreSelection.style.display = 'none';
            }
        });
    });
    
    // Validation du formulaire avant soumission
    document.getElementById('deliberationForm').addEventListener('submit', function(e) {
        const etapesChecked = document.querySelectorAll('input[name="etapes[]"]:checked:not(:disabled)');
        
        if (etapesChecked.length === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Attention',
                text: 'Veuillez sélectionner au moins une étape de délibération à exécuter.'
            });
        }
    });
});
</script>

<?php include "./views/include/footer.php"; ?>
