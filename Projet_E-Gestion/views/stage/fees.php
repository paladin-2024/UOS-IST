<?php
include "./views/include/header.php";

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
$promotionId = isset($_GET['promotion']) ? $_GET['promotion'] : null;

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
$availableFees = [];
$currentRequiredFee = null;

if ($promotionId) {
try {
$promotion = $stage->getPromotion($promotionId);

// Get fees assigned to this promotion
require_once 'models/Universite.php';
$universite = new Universite();
$assignedFees = $universite->getFeesForPromotion($promotionId);

// Get fees required for stage
$stageRequiredFees = $stage->getRequiredFeesForPromotion($promotionId);
$stageRequiredFeeIds = array_column($stageRequiredFees, 'id');
} catch (Exception $e) {
        error_log("Erreur lors de la récupération des données: " . $e->getMessage());
$assignedFees = [];
$stageRequiredFeeIds = [];
}
}

?>

<main id="main" class="main">
    <div class="pagetitle">
    <h1>CONFIGURATION DES FRAIS POUR LE STAGE</h1>
    <nav>
    <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="?view=stage">Stages</a></li>
    <li class="breadcrumb-item active">Configuration Frais<?php echo $promotion ? ' - ' . htmlspecialchars($promotion['designationPromotion']) : ''; ?></li>
    </ol>
    </nav>
    </div>

    <section class="section dashboard">
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

            <?php if ($promotionId && $promotion): ?>
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Configuration des Frais de Stage - <?php echo htmlspecialchars($promotion['designationPromotion']); ?></h5>
                    </div>
                    <div class="card-body">
                    <form method="POST" action="controller/configure_stage_fees.php">
                    <input type="hidden" name="promotion_id" value="<?php echo $promotionId; ?>">
                    <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Frais</th>
                        <th>Montant</th>
                            <th>Requis pour Stage</th>
                            </tr>
                        </thead>
                    <tbody>
                        <?php foreach ($assignedFees as $fee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($fee['designation']); ?></td>
                        <td><?php echo number_format($fee['montant'], 2); ?> <?php echo htmlspecialchars($fee['devise']); ?></td>
                        <td>
                        <input type="checkbox" name="stage_fees[]" value="<?php echo $fee['id']; ?>" <?php echo in_array($fee['id'], $stageRequiredFeeIds) ? 'checked' : ''; ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($assignedFees)): ?>
                    <tr>
                    <td colspan="3" class="text-center text-muted">Aucun frais assigné à cette promotion.</td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                    </table>
                    <?php if (!empty($assignedFees)): ?>
                    <div class="mt-3">
                    <button type="submit" name="saveStageFeesBtn" class="btn btn-primary">
                        <i class="bi bi-save"></i> Sauvegarder les frais requis pour le stage
                    </button>
                    </div>
                    <?php endif; ?>
                    </form>

                                    <div class="mt-3">
                                    <a href="?view=stage" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour aux Stages
                                    </a>
                                    </div>
                                        </div>
                                            </div>
                                                </div>
                                                        <?php endif; ?>
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
</script>

<?php include "./views/include/footer.php"; ?>
