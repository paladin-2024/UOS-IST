<?php
include "./views/include/header.php";

$soutenance = new Soutenance();

// Vérification des droits d'accès
$userId = $_SESSION['id'];
$hasFullAccess = $_SESSION['idRole'] == 1; // Administrateur

// Fetch user's responsibilities (only if not admin)
$userResponsibilities = [];
if (!$hasFullAccess) {
    try {
        $connexion = Connexion::getInstance()->getPDO();
        $query = "SELECT DISTINCT section_idsection FROM responsable_section 
                  WHERE \"idUser\" = :userId";
        $stmt = $connexion->prepare($query);
        $stmt->execute(['userId' => $userId]);
        $userResponsibilities = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
        text: 'Vous n\'avez pas les droits pour accéder à cette page.'
        }).then(() => {
            window.location.href = 'index';
        });
    </script>";
    include "./views/include/footer.php";
    exit;
}

// Get active academic year
$connexion = Connexion::getInstance()->getPDO();
$query = "SELECT * FROM annee_acad WHERE est_active = 1 LIMIT 1";
$stmt = $connexion->prepare($query);
$stmt->execute();
$activeYear = $stmt->fetch(PDO::FETCH_ASSOC);
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
        // Responsable de section - seulement les promotions de ses sections
        $connexion = Connexion::getInstance()->getPDO();
        $sectionsParams = str_repeat('?,', count($userResponsibilities) - 1) . '?';
        $query = "SELECT DISTINCT p.* 
                  FROM promotion p
                  LEFT JOIN orientation o ON p.orientation_idorientation = o.idorientation
                  LEFT JOIN section sec ON o.section_idsection = sec.idsection
                  WHERE sec.idsection IN ($sectionsParams)
                  ORDER BY p.\"designationPromotion\"";
        $stmt = $connexion->prepare($query);
        $stmt->execute($userResponsibilities);
        $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    $promotion = $soutenance->getPromotion($promotionId);
    
    // Get fees assigned to this promotion
    $universite = new Universite();
    $assignedFees = $universite->getFeesForPromotion($promotionId);
    
    // Get fees required for memoire
    $memoireRequiredFees = $soutenance->getRequiredFeesForMemoire($promotionId);
    $memoireRequiredFeeIds = array_column($memoireRequiredFees, 'frais_id');
    
    // Get fees required for sujet
    $sujetRequiredFees = $soutenance->getRequiredFeesForSujet($promotionId);
    $sujetRequiredFeeIds = array_column($sujetRequiredFees, 'frais_id');
} catch (Exception $e) {
        error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    $assignedFees = [];
    $memoireRequiredFeeIds = [];
    $sujetRequiredFeeIds = [];
}
}

?>

<main id="main" class="main">
    <?php if (isset($_SESSION['success_message'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Succès',
            text: '<?php echo htmlspecialchars($_SESSION['success_message']); ?>'
        });
    </script>
    <?php unset($_SESSION['success_message']); endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: '<?php echo htmlspecialchars($_SESSION['error_message']); ?>'
        });
    </script>
    <?php unset($_SESSION['error_message']); endif; ?>

    <div class="pagetitle">
    <h1>CONFIGURATION DES FRAIS POUR LES MÉMOIRES</h1>
    <nav>
    <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="memoire/index">Mémoires</a></li>
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
                                    <?php 
                                    $query = "SELECT * FROM annee_acad ORDER BY designation DESC";
                                    $stmt = $connexion->prepare($query);
                                    $stmt->execute();
                                    $years = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($years as $year): 
                                    ?>
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
                        <h5 class="card-title">Configuration des Frais de Mémoire - <?php echo htmlspecialchars($promotion['designationPromotion']); ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Instructions:</strong> Sélectionnez les frais qui doivent être payé par les étudiants avant de pouvoir déposer leur sujet de mémoire et leur mémoire.
                        </div>
                    <form method="POST" action="controller/configure_memoire_fees.php">
                    <input type="hidden" name="promotion_id" value="<?php echo $promotionId; ?>">
                    <input type="hidden" name="annee_acad" value="<?php echo $selectedYearId; ?>">
                    <table class="table table-striped table-hover">
                    <thead class="table-light">
                    <tr>
                        <th>Frais</th>
                        <th>Montant</th>
                        <th>Devise</th>
                        <th>Requis pour Sujet</th>
                        <th>Requis pour Mémoire</th>
                        </tr>
                        </thead>
                    <tbody>
                        <?php if (!empty($assignedFees)): foreach ($assignedFees as $fee): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($fee['designation']); ?></strong>
                            <?php if (!empty($fee['description'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($fee['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($fee['montant'], 2); ?></td>
                        <td><?php echo htmlspecialchars($fee['devise']); ?></td>
                        <td>
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="checkbox" 
                                name="sujet_fees[]" 
                                value="<?php echo $fee['id']; ?>" 
                                id="sujet_fee_<?php echo $fee['id']; ?>"
                                <?php echo in_array($fee['id'], $sujetRequiredFeeIds) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="sujet_fee_<?php echo $fee['id']; ?>"></label>
                        </div>
                        </td>
                        <td>
                        <div class="form-check">
                            <input 
                                class="form-check-input" 
                                type="checkbox" 
                                name="memoire_fees[]" 
                                value="<?php echo $fee['id']; ?>" 
                                id="memoire_fee_<?php echo $fee['id']; ?>"
                                <?php echo in_array($fee['id'], $memoireRequiredFeeIds) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="memoire_fee_<?php echo $fee['id']; ?>"></label>
                        </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Aucun frais assigné à cette promotion.
                    </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                    </table>
                    
                    <?php if (!empty($assignedFees)): ?>
                    <div class="mt-4">
                        <button type="submit" name="saveFeesBtn" class="btn btn-primary btn-lg">
                            <i class="bi bi-save me-2"></i> Sauvegarder la configuration des frais
                        </button>
                        <a href="memoire/index" class="btn btn-secondary btn-lg ms-2">
                            <i class="bi bi-arrow-left me-2"></i> Retour aux Mémoires
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mt-4">
                        <a href="memoire/index" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i> Retour aux Mémoires
                        </a>
                    </div>
                    <?php endif; ?>
                    </form>

                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="col-lg-12">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Sélectionnez une promotion pour configurer les frais requis pour le dépôt de mémoire.
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
