<?php
include "./views/include/header.php";

$stage = new Stage();
$promotionId = isset($_GET['promotion']) ? $_GET['promotion'] : null;

if (!$promotionId) {
    echo "<script>alert('Promotion non spécifiée'); window.location.href='?view=stage';</script>";
    exit;
}

// Get promotion details
$promotion = $stage->getPromotion($promotionId);

// Get current stage fee for this promotion
$currentFee = $stage->getRequiredFeeForPromotion($promotionId);

// Get all available fees that could be stage fees
$fraisModel = new Universite();
$allFees = $fraisModel->getAllFees(); // Assuming this method exists

?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>CONFIGURATION DES FRAIS DE STAGE</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="?view=stage">Stages</a></li>
                <li class="breadcrumb-item active">Configuration Frais - <?php echo htmlspecialchars($promotion['designationPromotion']); ?></li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Frais de Stage pour <?php echo htmlspecialchars($promotion['designationPromotion']); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($currentFee): ?>
                            <div class="alert alert-success">
                                <strong>Frais actuel :</strong> <?php echo htmlspecialchars($currentFee['designation']); ?> - <?php echo htmlspecialchars($currentFee['montant']); ?> <?php echo htmlspecialchars($currentFee['devise']); ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                Aucun frais de stage configuré pour cette promotion.
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="controller/configure_stage_fees.php">
                            <input type="hidden" name="promotion_id" value="<?php echo $promotionId; ?>">

                            <div class="mb-3">
                                <label for="stage_fee" class="form-label">Sélectionner un frais de stage</label>
                                <select name="fee_id" id="stage_fee" class="form-select">
                                    <option value="">Aucun frais requis</option>
                                    <?php foreach ($allFees as $fee): ?>
                                        <?php if (stripos($fee['designation'], 'stage') !== false): ?>
                                            <option value="<?php echo $fee['id']; ?>" <?php echo ($currentFee && $currentFee['id'] == $fee['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($fee['designation']); ?> - <?php echo htmlspecialchars($fee['montant']); ?> <?php echo htmlspecialchars($fee['devise']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <button type="submit" name="saveBtn" class="btn btn-primary">Enregistrer</button>
                                </div>
                                <div class="col-md-6 text-end">
                                    <a href="?view=stage" class="btn btn-secondary">Retour</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include "./views/include/footer.php"; ?>
